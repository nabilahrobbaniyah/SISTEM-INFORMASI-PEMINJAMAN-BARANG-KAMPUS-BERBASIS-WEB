<?php

namespace App\Http\Controllers\Peminjam;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Loan;
use App\Services\LoanWorkflow;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
            ->where('total_quantity', '>', 0)
            ->orderBy('name')
            ->get();

        return view('peminjam.loans.create', [
            'items' => $items,
            'prefillItem' => $request->input('item'),
            'draft' => $request->session()->get('loan_draft'),
            'loanDate' => $request->input('loan_date', now()->toDateString()),
            'dueDate' => $request->input('due_date', now()->addDay()->toDateString()),
            'maxDays' => Loan::MAX_DAYS,
        ]);
    }

    public function review(Request $request): RedirectResponse
    {
        $data = $this->validateLoanData($request->all());
        $shortages = $this->workflow->shortages($data['items'], $data['loan_date'], $data['due_date']);

        if ($shortages) {
            throw ValidationException::withMessages(['items' => $shortages]);
        }

        $request->session()->put('loan_draft', $data);
        $request->session()->put('loan_confirmation', [
            'key' => (string) Str::uuid(),
            'draft' => $data,
        ]);

        return redirect()->route('peminjam.loans.confirmation');
    }

    public function confirmation(Request $request): View|RedirectResponse
    {
        $confirmation = $request->session()->get('loan_confirmation');
        if (! is_array($confirmation) || ! isset($confirmation['key'], $confirmation['draft'])) {
            return redirect()->route('peminjam.loans.create')
                ->with('error', 'Tidak ada pengajuan yang menunggu konfirmasi.');
        }

        $draft = $confirmation['draft'];
        $items = Item::with('category')
            ->whereIn('id', collect($draft['items'])->pluck('item_id'))
            ->get()
            ->keyBy('id');

        return view('peminjam.loans.confirm', compact('draft', 'items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $key = $request->input('submission_key');
        $confirmation = $request->session()->get('loan_confirmation');
        if (! is_string($key) || $key === '') {
            throw ValidationException::withMessages(['submission_key' => 'Konfirmasi pengajuan tidak valid. Silakan tinjau ulang pengajuan.']);
        }

        if (! is_array($confirmation) || ! isset($confirmation['key'], $confirmation['draft'])) {
            $existing = Loan::where('submission_key', $key)->where('user_id', $request->user()->id)->first();
            if ($existing) {
                return redirect()->route('peminjam.loans.show', $existing)
                    ->with('success', 'Pengajuan ini sudah pernah dikirim.');
            }

            throw ValidationException::withMessages(['submission_key' => 'Sesi konfirmasi berakhir. Silakan ajukan kembali.']);
        }

        if (! hash_equals($confirmation['key'], $key)) {
            throw ValidationException::withMessages(['submission_key' => 'Data konfirmasi tidak cocok. Silakan tinjau ulang pengajuan.']);
        }

        $data = $this->validateLoanData($confirmation['draft']);
        $shortages = $this->workflow->shortages($data['items'], $data['loan_date'], $data['due_date']);
        if ($shortages) {
            $request->session()->forget('loan_confirmation');

            return redirect()->route('peminjam.loans.create')
                ->withErrors(['items' => 'Ketersediaan berubah sejak pengajuan ditinjau. '.implode(' ', $shortages)]);
        }

        try {
            $loan = $this->workflow->submit($request->user(), $data, $key);
        } catch (RuntimeException $exception) {
            $request->session()->forget('loan_confirmation');

            return redirect()->route('peminjam.loans.create')
                ->withErrors(['items' => $exception->getMessage()]);
        } catch (QueryException $exception) {
            $loan = Loan::where('submission_key', $key)->where('user_id', $request->user()->id)->first();
            if (! $loan) {
                throw $exception;
            }
        }

        $request->session()->forget(['loan_confirmation', 'loan_draft']);

        return redirect()->route('peminjam.loans.show', $loan)
            ->with('success', 'Pengajuan berhasil dikirim. Tunggu persetujuan petugas.');
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{loan_date: string, due_date: string, purpose: string, items: array<int, array{item_id: int|string, quantity: int|string}>}
     */
    private function validateLoanData(array $input): array
    {
        $data = Validator::make($input, [
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
        ])->validate();

        if (Carbon::parse($data['loan_date'])->addDays(Loan::MAX_DAYS)->lt(Carbon::parse($data['due_date']))) {
            throw ValidationException::withMessages([
                'due_date' => 'Lama peminjaman maksimal '.Loan::MAX_DAYS.' hari.',
            ]);
        }

        return $data;
    }

    public function show(Loan $loan): View
    {
        abort_unless($loan->user_id === auth()->id(), 403);

        $loan->load(['details.item', 'details.returnInspector', 'user', 'processor', 'handedOverBy', 'returnedTo']);

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
