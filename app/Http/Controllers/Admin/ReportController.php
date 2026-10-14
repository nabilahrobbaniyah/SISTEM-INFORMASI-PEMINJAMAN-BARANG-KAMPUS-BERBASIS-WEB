<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.reports.index', $this->data($request) + [
            'loans' => $this->query($request)->paginate(15)->withQueryString(),
            'statuses' => Loan::STATUS_LABELS,
        ]);
    }

    public function print(Request $request): View
    {
        return view('admin.reports.print', $this->data($request) + [
            'loans' => $this->query($request)->get(),
            'statuses' => Loan::STATUS_LABELS,
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $this->data($request);
        $loans = $this->query($request)->get();

        return response()->streamDownload(function () use ($loans) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, ['Kode', 'Peminjam', 'NIM/NIP', 'Tanggal Pinjam', 'Tanggal Kembali', 'Status', 'Barang', 'Dikembalikan Pada']);

            foreach ($loans as $loan) {
                fputcsv($out, [
                    $this->csvCell($loan->loan_code),
                    $this->csvCell($loan->user->name),
                    $this->csvCell($loan->user->identity_number),
                    $loan->loan_date->format('d/m/Y'),
                    $loan->due_date->format('d/m/Y'),
                    $this->csvCell($loan->statusLabel()),
                    $this->csvCell($loan->details->map(fn ($d) => $d->item->name.' x'.$d->quantity)->implode('; ')),
                    $loan->returned_at?->format('d/m/Y H:i'),
                ]);
            }

            fclose($out);
        }, 'laporan-peminjaman-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function query(Request $request)
    {
        $filters = $this->filters($request);

        return Loan::with(['user', 'details.item'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('loan_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('loan_date', '<=', $v))
            ->orderBy('loan_date', 'desc');
    }

    private function data(Request $request): array
    {
        $filters = $this->filters($request);

        $summary = (clone $this->query($request))
            ->reorder()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'summary' => $summary,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
        ];
    }

    /**
     * @return array{from: ?string, to: ?string, status: ?string}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(array_keys(Loan::STATUS_LABELS))],
        ]);

        return [
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'status' => $validated['status'] ?? null,
        ];
    }

    private function csvCell(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[\t\r\n ]*[=+\-@]/u', $value) === 1
            ? "'".$value
            : $value;
    }
}
