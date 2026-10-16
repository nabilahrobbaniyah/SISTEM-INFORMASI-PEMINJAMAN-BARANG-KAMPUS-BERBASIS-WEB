@extends('layouts.admin')

@section('title', 'Data Barang')
@section('heading', 'Data Barang')

@section('content')
    @if (auth()->user()->isAdmin())
        <div class="mb-3">
            <a href="{{ route('admin.items.create') }}" class="btn btn-primary">Tambah Barang</a>
        </div>
    @endif

    <form method="GET" action="{{ route('admin.items.index') }}" class="card card-body mb-3">
        <div class="row g-2">
            <div class="col-md-4">
                <input type="text" name="q" value="{{ request('q') }}" class="form-control"
                       placeholder="Cari nama atau kode barang">
            </div>
            <div class="col-md-2">
                <select name="category_id" class="form-select">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">Semua status</option>
                    <option value="aktif" @selected(request('status') === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-secondary flex-fill">Cari</button>
                <a href="{{ route('admin.items.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama barang</th>
                    <th>Kategori</th>
                    <th class="text-center">Total</th>
                    <th class="text-center">Tersedia</th>
                    <th>Kondisi</th>
                    <th class="text-end">Aksi</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($items as $item)
                    <tr class="{{ $item->is_active ? '' : 'table-secondary' }}">
                        <td>{{ $item->item_code }}</td>
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->category->name }}</td>
                        <td class="text-center">{{ $item->inventoryQuantity() - $item->disposed_quantity }}</td>
                        <td class="text-center">{{ $item->available_quantity }}</td>
                        <td>
                            @php
                                $problemQuantity = $item->light_damage_quantity + $item->inspection_quantity
                                    + $item->repair_quantity + $item->heavy_damage_quantity + $item->lost_quantity;
                            @endphp
                            <span class="badge {{ ! $item->is_active ? 'bg-secondary' : ($problemQuantity > 0 ? 'bg-warning text-dark' : 'bg-success') }}">
                                {{ ! $item->is_active ? 'Nonaktif' : ($problemQuantity > 0 ? $problemQuantity.' bermasalah' : 'Baik') }}
                            </span>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.items.show', $item) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada barang yang cocok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $items->links('pagination::bootstrap-5') }}</div>
@endsection
