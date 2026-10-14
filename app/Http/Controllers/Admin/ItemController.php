<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Item;
use App\Models\LoanDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('q');
        $categoryId = $request->input('category_id');
        $condition = $request->input('condition');
        $status = $request->input('status');

        $items = Item::with('category')
            ->when($search, function ($query, $value) {
                $query->where(function ($w) use ($value) {
                    $w->where('name', 'like', "%{$value}%")
                        ->orWhere('item_code', 'like', "%{$value}%");
                });
            })
            ->when($categoryId, fn ($query, $value) => $query->where('category_id', $value))
            ->when($condition, fn ($query, $value) => $query->where('condition', $value))
            ->when($status === 'aktif', fn ($query) => $query->where('is_active', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.items.index', [
            'items' => $items,
            'categories' => Category::orderBy('name')->get(),
            'conditions' => Item::CONDITIONS,
        ]);
    }

    public function create(): View
    {
        return view('admin.items.create', [
            'item' => new Item(),
            'categories' => Category::orderBy('name')->get(),
            'conditions' => Item::CONDITIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'item_code' => ['required', 'string', 'max:50', 'unique:items,item_code'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'total_quantity' => ['required', 'integer', 'min:1'],
            'condition' => ['required', Rule::in(Item::CONDITIONS)],
            'location' => ['required', 'string', 'max:150'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        $data['available_quantity'] = $data['total_quantity'];
        $data['is_active'] = true;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('items', 'public');
        }

        $item = Item::create($data);

        AuditLog::record('barang', "Menambah barang {$item->item_code} - {$item->name}.");

        return redirect()->route('admin.items.index')->with('success', 'Barang berhasil ditambahkan.');
    }

    public function show(Item $item): View
    {
        $item->load('category');

        return view('admin.items.show', compact('item'));
    }

    public function edit(Item $item): View
    {
        return view('admin.items.edit', [
            'item' => $item,
            'categories' => Category::orderBy('name')->get(),
            'conditions' => Item::CONDITIONS,
        ]);
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'item_code' => ['required', 'string', 'max:50', Rule::unique('items', 'item_code')->ignore($item->id)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'total_quantity' => ['required', 'integer', 'min:0'],
            'condition' => ['required', Rule::in(Item::CONDITIONS)],
            'location' => ['required', 'string', 'max:150'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        DB::transaction(function () use ($request, $item, &$data) {
            $item = Item::lockForUpdate()->findOrFail($item->id);
            $activeLoans = LoanDetail::where('item_id', $item->id)
                ->whereHas('loan', fn ($query) => $query->whereIn('status', ['disetujui', 'dipinjam']))
                ->exists();
            $newTotal = (int) $data['total_quantity'];

            if ($newTotal < $item->total_quantity && $activeLoans) {
                throw ValidationException::withMessages([
                    'total_quantity' => 'Jumlah total tidak dapat dikurangi selama masih ada peminjaman aktif atau disetujui.',
                ]);
            }

            $newAvailable = $item->available_quantity + ($newTotal - $item->total_quantity);

            if ($newAvailable < 0) {
                throw ValidationException::withMessages([
                    'total_quantity' => 'Jumlah total tidak boleh lebih kecil dari stok yang sedang tersedia.',
                ]);
            }

            if ($request->hasFile('image')) {
                if ($item->image) {
                    Storage::disk('public')->delete($item->image);
                }
                $data['image'] = $request->file('image')->store('items', 'public');
            } else {
                unset($data['image']);
            }

            $data['available_quantity'] = $newAvailable;
            $item->update($data);
        });

        AuditLog::record('barang', "Mengubah barang {$item->item_code} - {$item->name}.");

        return redirect()->route('admin.items.index')->with('success', 'Barang berhasil diperbarui.');
    }

    public function toggle(Item $item): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Hanya admin yang dapat mengubah status barang.');

        $item->update(['is_active' => ! $item->is_active]);

        AuditLog::record('barang', ($item->is_active ? 'Mengaktifkan' : 'Menonaktifkan')." barang {$item->item_code}.");

        $pesan = $item->is_active ? 'Barang diaktifkan kembali.' : 'Barang dinonaktifkan.';

        return back()->with('success', $pesan);
    }
}
