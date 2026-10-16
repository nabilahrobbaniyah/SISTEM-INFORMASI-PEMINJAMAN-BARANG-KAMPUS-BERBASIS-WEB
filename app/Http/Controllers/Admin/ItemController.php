<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\InventoryDisposalProposal;
use App\Models\Item;
use App\Services\LoanWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('q');
        $categoryId = $request->input('category_id');
        $status = $request->input('status');

        $items = Item::with('category')
            ->when($search, function ($query, $value) {
                $query->where(function ($w) use ($value) {
                    $w->where('name', 'like', "%{$value}%")
                        ->orWhere('item_code', 'like', "%{$value}%");
                });
            })
            ->when($categoryId, fn ($query, $value) => $query->where('category_id', $value))
            ->when($status === 'aktif', fn ($query) => $query->where('is_active', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.items.index', [
            'items' => $items,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorizeAdminForManagementRoutes();

        return view('admin.items.create', [
            'item' => new Item,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdminForManagementRoutes();
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'item_code' => ['required', 'string', 'max:50', 'unique:items,item_code'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'initial_quantity' => ['required', 'integer', 'min:1'],
            'location' => ['required', 'string', 'max:150'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        $initialQuantity = (int) $data['initial_quantity'];
        unset($data['initial_quantity']);
        $data['total_quantity'] = $initialQuantity;
        $data['available_quantity'] = $initialQuantity;
        $data['is_active'] = true;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('items', 'public');
        }

        $item = DB::transaction(function () use ($data, $initialQuantity, $request) {
            $item = Item::create($data);
            $item->inventoryEvents()->create([
                'user_id' => $request->user()->id,
                'action' => 'stock_added',
                'quantity' => $initialQuantity,
                'occurred_at' => now(),
                'description' => 'Saldo awal inventaris saat barang didaftarkan.',
                'state_before' => 'belum tercatat',
                'state_after' => 'tersedia',
                'quantity_before' => 0,
                'quantity_after' => $initialQuantity,
            ]);
            AuditLog::record('barang', "Menambahkan saldo awal {$initialQuantity} unit untuk {$item->item_code}.", $request->user()->id);

            return $item;
        });

        return redirect()->route('admin.items.index')->with('success', 'Barang berhasil ditambahkan.');
    }

    public function show(Request $request, Item $item): View
    {
        $activeTab = $request->query('tab', 'identitas');
        $activeTab = in_array($activeTab, ['identitas', 'stok', 'tindakan', 'riwayat'], true) ? $activeTab : 'identitas';

        return $this->itemDetailView($item, $activeTab);
    }

    public function inventory(Request $request, Item $item): View
    {
        $activeTab = $request->query('tab', 'tindakan');
        $activeTab = in_array($activeTab, ['tindakan', 'riwayat'], true) ? $activeTab : 'tindakan';

        return $this->itemDetailView($item, $activeTab);
    }

    private function itemDetailView(Item $item, string $activeTab): View
    {
        $item->load([
            'category',
            'inventoryEvents.actor',
            'disposalProposals.proposer',
            'disposalProposals.reviewer',
        ]);

        return view('admin.items.show', [
            'item' => $item,
            'inspectionConditions' => Item::INSPECTION_CONDITIONS,
            'activeTab' => $activeTab,
        ]);
    }

    public function reportFound(Request $request, Item $item, LoanWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'occurred_at' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['nullable', 'image', 'max:5120'],
        ]);
        $path = $request->file('evidence')?->store('inventory-evidence', 'local');

        try {
            $workflow->reportFound(
                $item,
                $request->user(),
                $data['quantity'],
                $data['description'] ?? '',
                $path,
                Carbon::parse($data['occurred_at'])
            );
        } catch (RuntimeException $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Barang tercatat ditemukan dan menunggu pemeriksaan.');
    }

    public function reportDamage(Request $request, Item $item, LoanWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'condition' => ['required', Rule::in(['rusak ringan', 'rusak berat'])],
            'occurred_at' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['nullable', 'image', 'max:5120'],
        ]);
        $path = $request->file('evidence')?->store('inventory-evidence', 'local');

        try {
            $workflow->reportDamage(
                $item,
                $request->user(),
                $data['quantity'],
                $data['condition'],
                $data['description'] ?? '',
                $path,
                Carbon::parse($data['occurred_at'])
            );
        } catch (RuntimeException $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Kerusakan tercatat dan stok tersedia telah disesuaikan.');
    }

    public function inspectFound(Request $request, Item $item, LoanWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'condition' => ['required', Rule::in(Item::INSPECTION_CONDITIONS)],
            'occurred_at' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['nullable', 'image', 'max:5120'],
        ]);
        $path = $request->file('evidence')?->store('inventory-evidence', 'local');

        try {
            $workflow->inspectFound(
                $item,
                $request->user(),
                $data['quantity'],
                $data['condition'],
                $data['description'] ?? '',
                $path,
                Carbon::parse($data['occurred_at'])
            );
        } catch (RuntimeException $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            return back()->with('error', $exception->getMessage());
        }

        return back()->with(
            'success',
            $data['condition'] === 'baik'
                ? 'Hasil pemeriksaan layak; barang kembali tersedia.'
                : 'Hasil pemeriksaan tercatat. Barang tidak ditambahkan ke stok tersedia.'
        );
    }

    public function startRepair(Request $request, Item $item, LoanWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'started_at' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['nullable', 'image', 'max:5120'],
        ]);
        $path = $request->file('evidence')?->store('inventory-evidence', 'local');

        try {
            $workflow->startRepair(
                $item,
                $request->user(),
                $data['quantity'],
                $data['description'] ?? '',
                $path,
                Carbon::parse($data['started_at'])
            );
        } catch (RuntimeException $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Barang rusak ringan dipindahkan ke proses perbaikan.');
    }

    public function completeRepair(Request $request, Item $item, LoanWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'result' => ['required', Rule::in(['layak', 'perlu_perbaikan'])],
            'completed_at' => ['required', 'date'],
            'action_notes' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['nullable', 'image', 'max:5120'],
        ]);
        $path = $request->file('evidence')?->store('inventory-evidence', 'local');

        try {
            $workflow->completeRepair(
                $item,
                $request->user(),
                $data['quantity'],
                $data['result'],
                $data['action_notes'] ?? '',
                $path,
                Carbon::parse($data['completed_at'])
            );
        } catch (RuntimeException $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            return back()->with('error', $exception->getMessage());
        }

        return back()->with(
            'success',
            $data['result'] === 'layak'
                ? 'Barang dinyatakan layak dan kembali tersedia tanpa menambah total fisik inventaris.'
                : 'Hasil pemeriksaan tercatat; barang tetap dalam daftar perbaikan.'
        );
    }

    public function resolveHeavyDamage(Request $request, Item $item, LoanWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['return_to_shelf', 'start_repair', 'propose_disposal'])],
            'quantity' => ['required', 'integer', 'min:1'],
            'occurred_at' => ['required', 'date'],
            'description' => ['required_if:action,propose_disposal', 'nullable', 'string', 'max:2000'],
        ]);

        try {
            if ($data['action'] === 'return_to_shelf') {
                $workflow->returnHeavyDamageToShelf(
                    $item,
                    $request->user(),
                    (int) $data['quantity'],
                    $data['description'] ?? '',
                    Carbon::parse($data['occurred_at'])
                );
                $message = 'Barang rusak berat dinyatakan layak dan dikembalikan ke rak.';
            } elseif ($data['action'] === 'start_repair') {
                $workflow->startRepair(
                    $item,
                    $request->user(),
                    (int) $data['quantity'],
                    $data['description'] ?? '',
                    null,
                    Carbon::parse($data['occurred_at']),
                    'rusak berat'
                );
                $message = 'Barang rusak berat dipindahkan ke proses perbaikan.';
            } else {
                $workflow->proposeHeavyDamageDisposal(
                    $item,
                    $request->user(),
                    (int) $data['quantity'],
                    $data['description'] ?? '',
                    Carbon::parse($data['occurred_at'])
                );
                $message = 'Usulan penghapusan dicatat dan menunggu persetujuan admin.';
            }
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return back()->with('success', $message);
    }

    public function reviewHeavyDamageDisposal(
        Request $request,
        Item $item,
        InventoryDisposalProposal $proposal,
        LoanWorkflow $workflow
    ): RedirectResponse {
        $this->authorizeAdminForManagementRoutes();
        abort_unless($proposal->item_id === $item->id, 404);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $approve = $data['decision'] === 'approve';
            $workflow->reviewHeavyDamageDisposal(
                $proposal,
                $request->user(),
                $approve,
                $data['review_note'] ?? ''
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return back()->with(
            'success',
            $approve ? 'Usulan penghapusan disetujui dan barang dihapuskan.' : 'Usulan penghapusan ditolak.'
        );
    }

    public function edit(Item $item): View
    {
        $this->authorizeAdminForManagementRoutes();

        return view('admin.items.edit', [
            'item' => $item,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $this->authorizeAdminForManagementRoutes();
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'item_code' => ['required', 'string', 'max:50', Rule::unique('items', 'item_code')->ignore($item->id)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'location' => ['required', 'string', 'max:150'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        DB::transaction(function () use ($request, $item, &$data) {
            $item = Item::lockForUpdate()->findOrFail($item->id);
            if ($request->hasFile('image')) {
                if ($item->image) {
                    Storage::disk('public')->delete($item->image);
                }
                $data['image'] = $request->file('image')->store('items', 'public');
            } else {
                unset($data['image']);
            }

            $item->update($data);
        });

        AuditLog::record('barang', "Mengubah barang {$item->item_code} - {$item->name}.");

        return redirect()->route('admin.items.index')->with('success', 'Barang berhasil diperbarui.');
    }

    public function adjustStock(Request $request, Item $item, LoanWorkflow $workflow): RedirectResponse
    {
        $this->authorizeAdminForManagementRoutes();
        $data = $request->validate([
            'action' => ['required', Rule::in(['penambahan', 'pengurangan', 'koreksi', 'penghapusan'])],
            'quantity' => ['required', 'integer', 'min:0'],
            'source_state' => ['required_if:action,penghapusan', 'nullable', Rule::in([
                'tersedia', 'rusak ringan', 'pemeriksaan', 'perbaikan', 'rusak berat', 'hilang',
            ])],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['action'] !== 'koreksi' && (int) $data['quantity'] < 1) {
            throw ValidationException::withMessages(['quantity' => 'Jumlah harus lebih besar dari nol.']);
        }

        try {
            $workflow->adjustInventory(
                $item,
                $request->user(),
                $data['action'],
                (int) $data['quantity'],
                $data['reason'] ?? '',
                $data['source_state'] ?? null
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return back()->with('success', 'Perubahan jumlah inventaris berhasil dicatat.');
    }

    public function toggle(Item $item): RedirectResponse
    {
        $this->authorizeAdminForManagementRoutes();

        $item->update(['is_active' => ! $item->is_active]);

        AuditLog::record('barang', ($item->is_active ? 'Mengaktifkan' : 'Menonaktifkan')." barang {$item->item_code}.");

        $pesan = $item->is_active ? 'Barang diaktifkan kembali.' : 'Barang dinonaktifkan.';

        return back()->with('success', $pesan);
    }

    private function authorizeAdminForManagementRoutes(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'Hanya admin yang dapat mengelola data dan jumlah inventaris.');
    }
}
