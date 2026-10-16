@extends('layouts.admin')

@section('title', 'Katalog Barang')
@section('heading', 'Katalog Barang')

@section('content')
    <form method="GET" action="{{ route('peminjam.catalog') }}" class="card card-body mb-3">
        @if ($errors->any())
            <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
        @endif
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="catalog-search" class="form-label small mb-1">Cari barang</label>
                <input id="catalog-search" type="search" name="q" value="{{ request('q') }}" class="form-control"
                       placeholder="Nama atau kode barang">
            </div>
            <div class="col-md-2">
                <label for="catalog-category" class="form-label small mb-1">Kategori</label>
                <select id="catalog-category" name="category_id" class="form-select">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="catalog-loan-date" class="form-label small mb-1">Tanggal pinjam</label>
                <input id="catalog-loan-date" type="date" name="loan_date" value="{{ request('loan_date') }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label for="catalog-due-date" class="form-label small mb-1">Tanggal kembali</label>
                <input id="catalog-due-date" type="date" name="due_date" value="{{ request('due_date') }}" class="form-control">
            </div>
            <input type="hidden" name="view" value="{{ $catalogView }}">
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">Cari barang</button>
                <a href="{{ route('peminjam.catalog') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
        <div class="form-text mt-2">
            @if ($hasPeriod)
                Jumlah tersedia menunjukkan berapa unit yang bisa dipinjam selama tanggal yang dipilih, setelah memperhitungkan peminjaman lain.
            @else
                Isi tanggal pinjam dan kembali untuk mengetahui jumlah yang bisa dipinjam pada periode tersebut. Tanpa tanggal, jumlah tersedia menunjukkan stok yang saat ini ada di rak.
            @endif
        </div>
    </form>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="small text-muted">{{ $items->total() }} barang ditemukan</span>
        <div class="btn-group btn-group-sm" role="group" aria-label="Tampilan katalog">
            <a href="{{ request()->fullUrlWithQuery(['view' => 'cards', 'page' => null]) }}"
               class="btn {{ $catalogView === 'cards' ? 'btn-primary' : 'btn-outline-primary' }}">Kartu</a>
            <a href="{{ request()->fullUrlWithQuery(['view' => 'table', 'page' => null]) }}"
               class="btn {{ $catalogView === 'table' ? 'btn-primary' : 'btn-outline-primary' }}">Tabel</a>
        </div>
    </div>

    @if ($catalogView === 'cards')
        <div class="row g-3">
            @forelse ($items as $item)
                <div class="col-sm-6 col-xl-4">
                    <article class="card h-100">
                        @if ($item->image)
                            <img src="{{ asset('storage/'.$item->image) }}" class="card-img-top" alt="Foto {{ $item->name }}"
                                 loading="lazy" style="height: 190px; object-fit: cover;">
                        @else
                            <div class="bg-body-secondary d-flex align-items-center justify-content-center text-muted"
                                 role="img" aria-label="Tidak ada foto barang" style="height: 190px;">Foto tidak tersedia</div>
                        @endif
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between gap-2 mb-2">
                                <span class="badge text-bg-light">{{ $item->item_code }}</span>
                                <span class="badge {{ $item->shown_available > 0 ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $item->shown_available }} tersedia
                                </span>
                            </div>
                            <h2 class="h5 card-title">{{ $item->name }}</h2>
                            <p class="small text-muted mb-1">{{ $item->category->name }}</p>
                            <p class="small text-muted">{{ $item->location }}</p>
                            <p class="card-text flex-grow-1">{{ \Illuminate\Support\Str::limit($item->description ?: 'Tidak ada deskripsi.', 120) }}</p>
                            @if ($item->shown_available > 0)
                                <a href="{{ route('peminjam.loans.create', array_filter([
                                    'item' => $item->id,
                                    'loan_date' => request('loan_date'),
                                    'due_date' => request('due_date'),
                                ])) }}" class="btn btn-primary">Ajukan peminjaman</a>
                            @else
                                <button type="button" class="btn btn-outline-secondary" disabled>Tidak tersedia</button>
                            @endif
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-12"><div class="alert alert-light text-center">Tidak ada barang yang cocok.</div></div>
            @endforelse
        </div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                    <tr><th>Barang</th><th>Kategori</th><th>Lokasi</th><th class="text-center">Tersedia</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if ($item->image)
                                        <img src="{{ asset('storage/'.$item->image) }}" alt="" loading="lazy"
                                             class="rounded" style="width: 48px; height: 48px; object-fit: cover;">
                                    @endif
                                    <span><span class="d-block fw-semibold">{{ $item->name }}</span><small class="text-muted">{{ $item->item_code }}</small></span>
                                </div>
                            </td>
                            <td>{{ $item->category->name }}</td>
                            <td>{{ $item->location }}</td>
                            <td class="text-center"><span class="badge {{ $item->shown_available > 0 ? 'bg-success' : 'bg-secondary' }}">{{ $item->shown_available }}</span></td>
                            <td class="text-end">
                                @if ($item->shown_available > 0)
                                    <a href="{{ route('peminjam.loans.create', array_filter([
                                        'item' => $item->id,
                                        'loan_date' => request('loan_date'),
                                        'due_date' => request('due_date'),
                                    ])) }}" class="btn btn-sm btn-primary">Ajukan</a>
                                @else
                                    <span class="text-muted small">Tidak tersedia</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada barang yang cocok.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="mt-3">{{ $items->links('pagination::bootstrap-5') }}</div>
@endsection
