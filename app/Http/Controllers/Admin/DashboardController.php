<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanDetail;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $aktif = Item::where('is_active', true)->where('condition', '!=', 'rusak berat');
        $firstMonth = now()->startOfMonth()->subMonths(5);
        $months = collect(range(0, 5))->map(function (int $offset) use ($firstMonth) {
            $start = $firstMonth->copy()->addMonths($offset);
            $end = $start->copy()->endOfMonth();

            $overdueCount = Loan::whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
                ->where(function ($query) {
                    $query->where(function ($active) {
                        $active->where('status', 'dipinjam')
                            ->whereDate('due_date', '<', now()->toDateString());
                    })->orWhere(function ($returned) {
                        $returned->where('status', 'dikembalikan')
                            ->whereRaw('DATE(returned_at) > DATE(due_date)');
                    });
                })
                ->count();

            return [
                'label' => $start->format('M Y'),
                'loans' => Loan::whereBetween('loan_date', [$start->toDateString(), $end->toDateString()])->count(),
                'overdue' => $overdueCount,
            ];
        });

        $statusCounts = Loan::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $statusDistribution = collect(Loan::STATUS_LABELS)
            ->map(fn ($label, $status) => ['label' => $label, 'total' => (int) ($statusCounts[$status] ?? 0)])
            ->values();
        $mostBorrowedItems = LoanDetail::query()
            ->selectRaw('item_id, SUM(quantity) as loaned_quantity')
            ->whereHas('loan', fn ($query) => $query->whereIn('status', ['dipinjam', 'dikembalikan']))
            ->with('item')
            ->groupBy('item_id')
            ->orderByDesc('loaned_quantity')
            ->limit(5)
            ->get();

        return view('admin.dashboard', [
            'totalItems' => (clone $aktif)->count(),
            'totalUnits' => (int) (clone $aktif)->sum('total_quantity'),
            'availableUnits' => (int) (clone $aktif)->sum('available_quantity'),
            'totalCategories' => Category::count(),
            'inactiveItems' => Item::where('is_active', false)->count(),
            'latestItems' => Item::with('category')->latest()->take(5)->get(),

            'waitingLoans' => Loan::where('status', 'menunggu')->count(),
            'approvedLoans' => Loan::where('status', 'disetujui')->count(),
            'borrowedLoans' => Loan::where('status', 'dipinjam')->count(),
            'overdueLoans' => Loan::where('status', 'dipinjam')->whereDate('due_date', '<', now()->toDateString())->count(),
            'pendingLoans' => Loan::with(['user', 'details'])
                ->where('status', 'menunggu')
                ->oldest()
                ->take(5)
                ->get(),
            'chartData' => [
                'months' => $months->pluck('label')->all(),
                'loans' => $months->pluck('loans')->all(),
                'overdue' => $months->pluck('overdue')->all(),
                'popularItems' => $mostBorrowedItems->map(fn ($detail) => [
                    'name' => $detail->item->name,
                    'quantity' => (int) $detail->loaned_quantity,
                ])->all(),
                'statuses' => $statusDistribution->all(),
            ],
        ]);
    }
}
