<?php

namespace App\Http\Controllers\Peminjam;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Loan;
use App\Services\LoanWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class LoanController extends Controller
{
    public function __construct(private LoanWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $status = $request->input('status');

        $query = Loan::with('details')
            ->where('user_id', auth()->id())
            ->when($status, fn ($query, $value) => $query->where('status', $value));

        if ($status === 'menunggu') {
            $query->orderBy('created_at')->orderBy('id');
        } elseif ($status === 'disetujui') {
            $query->orderBy('loan_date')->orderBy('id');
        } elseif ($status === 'dipinjam') {
            $query->orderBy('due_date')->orderBy('id');
        } elseif ($status === 'dikembalikan') {
            $query->orderByRaw('COALESCE(returned_at, updated_at, created_at) DESC')->orderByDesc('id');
        } else {
            $query->orderByRaw("CASE WHEN status = 'dikembalikan' THEN 0 ELSE 1 END")
                ->orderByRaw("CASE WHEN status = 'dikembalikan' THEN returned_at END DESC")
                ->orderByDesc('created_at')
                ->orderByDesc('id');
        }

        $loans = $query->paginate(10)->withQueryString();

        return view('peminjam.loans.index', [
            'loans' => $loans,
            'statuses' => Loan::STATUS_LABELS,
        ]);
    }

    public function create(Request $request): View
    {
        $items = Item::with('category')
            ->where('is_active', true)
            ->where('condition', '!=', 'rusak berat')
            ->orderBy('name')
            ->get();

        return view('peminjam.loans.create', [
            'items' => $items,
            'prefillItem' => $request->input('item'),
            'loanDate' => $request->input('loan_date', now()->toDateString()),
            'dueDate' => $request->input('due_date', now()->addDay()->toDateString()),
            'maxDays' => Loan::MAX_DAYS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'loan_date' => ['required', 'date', 'after_or_equal:today'],
            'due_date' => ['required', 'date', 'after_or_equal:loan_date'],
            'purpose' => ['required', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'distinct', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ], [
            'loan_date.after_or_equal' => 'Tanggal pinjam tidak boleh sebelum hari ini.',
            'due_date.after_or_equal' => 'Tanggal kembali tidak boleh sebelum tanggal pinjam.',
            'items.*.item_id.distinct' => 'Barang yang sama tidak boleh dipilih dua kali.',
            'items.*.item_id.required' => 'Pilih barang pada setiap baris.',
            'items.*.quantity.min' => 'Jumlah minimal 1 unit.',
        ]);

        if (Carbon::parse($data['loan_date'])->addDays(Loan::MAX_DAYS)->lt(Carbon::parse($data['due_date']))) {
            throw ValidationException::withMessages([
                'due_date' => 'Lama peminjaman maksimal '.Loan::MAX_DAYS.' hari.',
            ]);
        }

        $kurang = $this->workflow->shortages($data['items'], $data['loan_date'], $data['due_date']);

        if ($kurang) {
            throw ValidationException::withMessages(['items' => $kurang]);
        }

        $loan = $this->workflow->submit($request->user(), $data);

        return redirect()->route('peminjam.loans.show', $loan)
            ->with('success', 'Pengajuan berhasil dikirim. Tunggu persetujuan petugas.');
    }

    public function show(Loan $loan): View
    {
        abort_unless($loan->user_id === auth()->id(), 403);

        $loan->load(['details.item', 'user', 'processor', 'handedOverBy', 'returnedTo']);

        return view('peminjam.loans.show', compact('loan'));
    }

    public function cancel(Loan $loan): RedirectResponse
    {
        try {
            $this->workflow->cancel($loan, auth()->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('peminjam.loans.index')->with('success', 'Pengajuan dibatalkan.');
    }
}
