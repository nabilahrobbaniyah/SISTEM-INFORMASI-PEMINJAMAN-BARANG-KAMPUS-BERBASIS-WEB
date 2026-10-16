<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Loan;
use App\Services\LoanWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class LoanController extends Controller
{
    public const REJECTION_REASONS = [
        'Barang tidak tersedia',
        'Jadwal tidak dapat dipenuhi',
        'Informasi pengajuan tidak lengkap',
        'Tidak sesuai ketentuan peminjaman',
        'lainnya',
    ];

    public function __construct(private LoanWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $status = $request->input('status');
        $search = $request->input('q');

        $query = Loan::with(['user', 'details'])
            ->when($status === 'terlambat', function ($query) {
                $query->where('status', 'dipinjam')->whereDate('due_date', '<', now()->toDateString());
            })
            ->when($status && $status !== 'terlambat', fn ($query) => $query->where('status', $status))
            ->when($search, function ($query, $value) {
                $query->where(function ($w) use ($value) {
                    $w->where('loan_code', 'like', "%{$value}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$value}%"));
                });
            });

        if ($status === 'menunggu') {
            $query->orderBy('created_at')->orderBy('id');
        } elseif ($status === 'disetujui') {
            $query->orderBy('loan_date')->orderBy('id');
        } elseif (in_array($status, ['dipinjam', 'terlambat'], true)) {
            $query->orderBy('due_date')->orderBy('id');
        } elseif ($status === 'dikembalikan') {
            $query->orderByDesc('returned_at')->orderByDesc('id');
        } else {
            $query->orderByRaw("CASE status WHEN 'menunggu' THEN 0 WHEN 'disetujui' THEN 1 WHEN 'dipinjam' THEN 2 WHEN 'dikembalikan' THEN 3 ELSE 4 END")
                ->orderByRaw("CASE WHEN status = 'menunggu' THEN created_at END ASC")
                ->orderByRaw("CASE WHEN status = 'disetujui' THEN loan_date END ASC")
                ->orderByRaw("CASE WHEN status = 'dipinjam' THEN due_date END ASC")
                ->orderByRaw("CASE WHEN status = 'dikembalikan' THEN returned_at END DESC")
                ->orderByDesc('created_at')
                ->orderByDesc('id');
        }

        $loans = $query->paginate(10)->withQueryString();

        return view('admin.loans.index', [
            'loans' => $loans,
            'statuses' => Loan::STATUS_LABELS,
        ]);
    }

    public function show(Loan $loan): View
    {
        $loan->load(['details.item', 'details.returnInspector', 'user', 'processor', 'handedOverBy', 'returnedTo']);

        // Ketersediaan per periode ditampilkan saat petugas memutuskan persetujuan.
        $availability = [];
        if ($loan->status === 'menunggu') {
            foreach ($loan->details as $detail) {
                $availability[$detail->id] = $detail->item->availableBetween(
                    $loan->loan_date->toDateString(),
                    $loan->due_date->toDateString(),
                    $loan->id
                );
            }
        }

        return view('admin.loans.show', [
            'loan' => $loan,
            'availability' => $availability,
            'conditions' => Item::INSPECTION_CONDITIONS,
            'rejectionReasons' => self::REJECTION_REASONS,
        ]);
    }

    public function approve(Loan $loan): RedirectResponse
    {
        return $this->run(fn () => $this->workflow->approve($loan, auth()->user()), 'Pengajuan disetujui.');
    }

    public function reject(Request $request, Loan $loan): RedirectResponse
    {
        $data = $request->validate([
            'reason_code' => ['required', Rule::in(self::REJECTION_REASONS)],
            'custom_reason' => ['required_if:reason_code,lainnya', 'nullable', 'string', 'min:5', 'max:500'],
        ], [
            'reason_code.required' => 'Pilih alasan penolakan.',
            'custom_reason.required_if' => 'Jelaskan alasan penolakan lainnya.',
            'custom_reason.min' => 'Alasan penolakan terlalu singkat.',
        ]);
        $reason = $data['reason_code'] === 'lainnya' ? $data['custom_reason'] : $data['reason_code'];

        return $this->run(
            fn () => $this->workflow->reject($loan, auth()->user(), $reason),
            'Pengajuan ditolak.'
        );
    }

    public function handover(Loan $loan): RedirectResponse
    {
        return $this->run(fn () => $this->workflow->handover($loan, auth()->user()), 'Serah terima barang dicatat.');
    }

    public function returnItems(Request $request, Loan $loan): RedirectResponse
    {
        $data = $request->validate([
            'returns' => ['required', 'array'],
            'returns.*.good_quantity' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'returns.*.light_damage_quantity' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'returns.*.heavy_damage_quantity' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'returns.*.lost_quantity' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'returns.*.note' => ['nullable', 'string', 'max:255'],
            'returns.*.photo' => ['nullable', 'image', 'max:5120'],
            'return_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $storedFiles = [];
        foreach ($data['returns'] as $detailId => &$return) {
            if ($request->file("returns.{$detailId}.photo")) {
                $path = $request->file("returns.{$detailId}.photo")->store('returns', 'local');
                $return['photo_path'] = $path;
                $storedFiles[] = $path;
            }
            unset($return['photo']);
        }
        unset($return);

        try {
            $this->workflow->receiveReturn($loan, auth()->user(), $data['returns'], $data['return_notes'] ?? null);
        } catch (RuntimeException $exception) {
            foreach ($storedFiles as $path) {
                Storage::disk('local')->delete($path);
            }

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Jumlah dan kondisi pengembalian berhasil dicatat.');
    }

    private function run(callable $action, string $successMessage): RedirectResponse
    {
        try {
            $action();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $successMessage);
    }
}
