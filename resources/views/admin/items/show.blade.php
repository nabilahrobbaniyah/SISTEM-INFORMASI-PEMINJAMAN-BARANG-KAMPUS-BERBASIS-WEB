@extends('layouts.admin')

@section('title', $item->name)
@section('heading', 'Detail Barang')

@section('content')
    <ul class="nav nav-tabs mb-3" id="itemDetailTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-top {{ $activeTab === 'identitas' ? 'active' : '' }}" id="identitas-tab"
                    data-bs-toggle="tab" data-bs-target="#identitas-pane" type="button" role="tab"
                    data-item-tab="identitas"
                    aria-controls="identitas-pane" aria-selected="{{ $activeTab === 'identitas' ? 'true' : 'false' }}">
                Identitas Barang
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-top {{ $activeTab === 'stok' ? 'active' : '' }}" id="stok-tab"
                    data-bs-toggle="tab" data-bs-target="#stok-pane" type="button" role="tab"
                    data-item-tab="stok"
                    aria-controls="stok-pane" aria-selected="{{ $activeTab === 'stok' ? 'true' : 'false' }}">
                Ringkasan Stok
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-top {{ $activeTab === 'tindakan' ? 'active' : '' }}" id="tindakan-tab"
                    data-bs-toggle="tab" data-bs-target="#tindakan-pane" type="button" role="tab"
                    data-item-tab="tindakan"
                    aria-controls="tindakan-pane" aria-selected="{{ $activeTab === 'tindakan' ? 'true' : 'false' }}">
                Tindakan
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-top {{ $activeTab === 'riwayat' ? 'active' : '' }}" id="riwayat-tab"
                    data-bs-toggle="tab" data-bs-target="#riwayat-pane" type="button" role="tab"
                    data-item-tab="riwayat"
                    aria-controls="riwayat-pane" aria-selected="{{ $activeTab === 'riwayat' ? 'true' : 'false' }}">
                Riwayat
            </button>
        </li>
    </ul>

    <div class="tab-content" id="itemDetailTabContent">
        <section class="tab-pane fade {{ $activeTab === 'identitas' ? 'show active' : '' }}" id="identitas-pane"
                 role="tabpanel" aria-labelledby="identitas-tab" tabindex="0">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center g-4">
                        @if ($item->image)
                            <div class="col-sm-4 col-lg-3">
                                <img src="{{ asset('storage/'.$item->image) }}" alt="Foto {{ $item->name }}" class="img-fluid rounded">
                            </div>
                        @endif
                        <div class="col">
                            <h2 class="h4 mb-4">{{ $item->name }}</h2>
                            <dl class="row mb-4">
                                <dt class="col-sm-3">Kode barang</dt>
                                <dd class="col-sm-9">{{ $item->item_code }}</dd>
                                <dt class="col-sm-3">Kategori</dt>
                                <dd class="col-sm-9">{{ $item->category->name }}</dd>
                                <dt class="col-sm-3">Lokasi</dt>
                                <dd class="col-sm-9">{{ $item->location }}</dd>
                                <dt class="col-sm-3">Status katalog</dt>
                                <dd class="col-sm-9">
                                    <span class="badge {{ $item->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </dd>
                                <dt class="col-sm-3">Deskripsi</dt>
                                <dd class="col-sm-9">{{ $item->description ?: '-' }}</dd>
                            </dl>
                            <div class="d-flex flex-wrap gap-2">
                                @if (auth()->user()->isAdmin())
                                    <a href="{{ route('admin.items.edit', $item) }}" class="btn btn-outline-primary">Ubah identitas</a>
                                    <form method="POST" action="{{ route('admin.items.toggle', $item) }}"
                                          onsubmit="return confirm('{{ $item->is_active ? 'Nonaktifkan' : 'Aktifkan' }} barang ini?')">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn {{ $item->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                            {{ $item->is_active ? 'Nonaktifkan dari katalog' : 'Aktifkan di katalog' }}
                                        </button>
                                    </form>
                                @endif
                                <a href="{{ route('admin.items.index') }}" class="btn btn-outline-secondary">Kembali ke daftar barang</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="tab-pane fade {{ $activeTab === 'stok' ? 'show active' : '' }}" id="stok-pane"
                 role="tabpanel" aria-labelledby="stok-tab" tabindex="0">
            <div class="row g-3 mb-3">
                @foreach ([
                    ['Total', $item->inventoryQuantity() - $item->disposed_quantity, null],
                    ['Kondisi baik', $item->total_quantity, null],
                    ['Tersedia', $item->available_quantity, null],
                    ['Dipinjam', $item->checkedOutQuantity(), null],
                ] as [$label, $quantity, $description])
                    <div class="col-sm-6 col-xl-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="small text-muted">{{ $label }}</div>
                                <div class="display-6 fw-semibold">{{ $quantity }}</div>
                                @if ($description)
                                    <div class="small text-muted">{{ $description }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="card">
                <div class="card-header fw-semibold">Rincian kondisi barang</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                        <tr><th>Rusak ringan</th><td class="text-end">{{ $item->light_damage_quantity }}</td></tr>
                        <tr><th>Rusak berat</th><td class="text-end">{{ $item->heavy_damage_quantity }}</td></tr>
                        <tr><th>Hilang</th><td class="text-end">{{ $item->lost_quantity }}</td></tr>
                        <tr><th>Menunggu pemeriksaan</th><td class="text-end">{{ $item->inspection_quantity }}</td></tr>
                        <tr><th>Dalam perbaikan</th><td class="text-end">{{ $item->repair_quantity }}</td></tr>                      
                        <tr><th>Dihapuskan</th><td class="text-end">{{ $item->disposed_quantity }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="tab-pane fade {{ $activeTab === 'tindakan' ? 'show active' : '' }}" id="tindakan-pane"
                 role="tabpanel" aria-labelledby="tindakan-tab" tabindex="0">
            @include('admin.items._inventory-actions')
        </section>
        <section class="tab-pane fade {{ $activeTab === 'riwayat' ? 'show active' : '' }}" id="riwayat-pane"
                 role="tabpanel" aria-labelledby="riwayat-tab" tabindex="0">
            @include('admin.items._inventory-history')
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-item-tab]').forEach((tab) => {
            tab.addEventListener('shown.bs.tab', (event) => {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', event.target.dataset.itemTab);
                window.history.replaceState({}, '', url);
            });
        });
    </script>
@endpush
