<?php

namespace App\Http\Controllers\Peminjam;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'loan_date' => ['nullable', 'date_format:Y-m-d', 'required_with:due_date'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:loan_date', 'required_with:loan_date'],
            'view' => ['nullable', Rule::in(['cards', 'table'])],
        ]);

        $search = $request->input('q');
        $categoryId = $request->input('category_id');
        $start = $request->input('loan_date');
        $end = $request->input('due_date');
        $hasPeriod = $start && $end;

        $items = Item::with('category')
            ->where('is_active', true)
            ->where('condition', '!=', 'rusak berat')
            ->when($search, function ($query, $value) {
                $query->where(function ($w) use ($value) {
                    $w->where('name', 'like', "%{$value}%")
                        ->orWhere('item_code', 'like', "%{$value}%");
                });
            })
            ->when($categoryId, fn ($query, $value) => $query->where('category_id', $value))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        // Ketersediaan sesuai periode yang dipilih (BR-01); tanpa periode tampilkan stok di tempat.
        $items->getCollection()->each(function (Item $item) use ($hasPeriod, $start, $end) {
            $item->shown_available = $hasPeriod
                ? $item->availableBetween($start, $end)
                : $item->available_quantity;
        });

        return view('peminjam.catalog', [
            'items' => $items,
            'categories' => Category::orderBy('name')->get(),
            'hasPeriod' => $hasPeriod,
            'catalogView' => $request->input('view', 'cards'),
        ]);
    }
}
