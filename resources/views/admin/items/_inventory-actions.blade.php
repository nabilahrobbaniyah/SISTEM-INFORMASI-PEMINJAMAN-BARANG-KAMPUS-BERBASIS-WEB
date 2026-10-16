
{{-- =========================================================
     1. CATAT PERUBAHAN STOK — ADMIN SAJA
========================================================= --}}
@if (auth()->user()->isAdmin())
    <div class="card mt-3 mb-4">
        <div class="card-header fw-semibold">
            <i class="bi bi-box-seam me-1"></i>
            Catat Perubahan Stok Barang
        </div>

        <div class="card-body">
            <p class="text-muted small">
                Gunakan bagian ini untuk mencatat penambahan, pengurangan,
                koreksi, atau penghapusan stok. Setiap transaksi harus
                disertai alasan yang jelas.
            </p>

            <form method="POST"
                  action="{{ route('admin.items.adjust-stock', $item) }}"
                  class="row g-3"
                  id="inventory-stock-form">
                @csrf

                <div class="col-md-4">
                    <label for="inventory_action" class="form-label">
                        Jenis perubahan
                    </label>
                    <select id="inventory_action"
                            name="action"
                            class="form-select"
                            required>
                        <option value="penambahan"
                            @selected(old('action') === 'penambahan')>
                            Penambahan barang
                        </option>
                        <option value="pengurangan"
                            @selected(old('action') === 'pengurangan')>
                            Pengurangan barang
                        </option>
                        <option value="koreksi"
                            @selected(old('action') === 'koreksi')>
                            Koreksi jumlah layak dipinjam
                        </option>
                        <option value="penghapusan"
                            @selected(old('action') === 'penghapusan')>
                            Penghapusan / pemusnahan
                        </option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="inventory_quantity" class="form-label">
                        Jumlah unit
                    </label>
                    <input type="number"
                           id="inventory_quantity"
                           name="quantity"
                           class="form-control"
                           value="{{ old('quantity') }}"
                           min="1"
                           required>
                    <div class="form-text" id="inventory-quantity-help">
                        Masukkan jumlah unit yang akan diproses.
                    </div>
                </div>

                <div class="col-md-4"
                     id="inventory-source-state-field"
                     hidden>
                    <label for="inventory_source_state" class="form-label">
                        Kondisi barang yang dihapuskan
                    </label>
                    <select id="inventory_source_state"
                            name="source_state"
                            class="form-select">
                        <option value="">Pilih kondisi barang</option>
                        @foreach ([
                            'tersedia',
                            'rusak ringan',
                            'pemeriksaan',
                            'perbaikan',
                            'rusak berat',
                            'hilang'
                        ] as $sourceState)
                            <option value="{{ $sourceState }}"
                                @selected(old('source_state') === $sourceState)>
                                {{ ucfirst($sourceState) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <label for="inventory_reason" class="form-label">
                        Keterangan / nomor dokumen
                    </label>
                    <textarea id="inventory_reason"
                              name="reason"
                              class="form-control"
                              rows="2"
                              maxlength="2000"
                              placeholder="Contoh: berita acara penambahan atau penghapusan barang">{{ old('reason') }}</textarea>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        Simpan Transaksi Inventaris
                    </button>
                </div>
            </form>

            @foreach (['action', 'quantity', 'source_state', 'reason'] as $field)
                @error($field)
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
            @endforeach
        </div>
    </div>
@endif


{{-- =========================================================
     2. TINDAKAN LAINNYA
     Melaporkan kerusakan dan mencatat barang ditemukan
========================================================= --}}
<h2 class="h5 mb-3">
    Tindakan Inventaris: {{ $item->name }}
</h2>

<div class="card mb-4">
    <div class="card-header">
        <button class="btn btn-link text-decoration-none text-start p-0 fw-semibold"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#other-inventory-actions"
                aria-expanded="false"
                aria-controls="other-inventory-actions">
            <i class="bi bi-tools me-1"></i>
            Tindakan Lainnya
            <span class="text-muted fw-normal small ms-1">
                — laporkan kerusakan atau catat barang ditemukan
            </span>
        </button>
    </div>

    <div id="other-inventory-actions" class="collapse">
        <div class="card-body">
            <div class="row g-3">

                {{-- Laporkan kerusakan --}}
                @if ($item->available_quantity > 0)
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100">
                            <h3 class="h6 text-danger">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Laporkan Kerusakan
                            </h3>

                            <p class="small text-muted">
                                Catat unit yang rusak agar tidak dianggap
                                sebagai barang yang siap dipinjam.
                            </p>

                            <form method="POST"
                                  action="{{ route('admin.items.report-damage', $item) }}"
                                  enctype="multipart/form-data">
                                @csrf

                                <label class="form-label">
                                    Jumlah unit (maks. {{ $item->available_quantity }})
                                </label>
                                <input type="number"
                                       name="quantity"
                                       min="1"
                                       max="{{ $item->available_quantity }}"
                                       class="form-control mb-3"
                                       required>

                                <label class="form-label">Kondisi kerusakan</label>
                                <select name="condition"
                                        class="form-select mb-3"
                                        required>
                                    <option value="">Pilih kondisi</option>
                                    <option value="rusak ringan">Rusak ringan</option>
                                    <option value="rusak berat">Rusak berat</option>
                                </select>

                                <label class="form-label">Waktu kejadian</label>
                                <input type="datetime-local"
                                       name="occurred_at"
                                       value="{{ now()->format('Y-m-d\TH:i') }}"
                                       class="form-control mb-3"
                                       required>

                                <label class="form-label">Keterangan</label>
                                <textarea name="description"
                                          class="form-control mb-3"
                                          rows="2"
                                          maxlength="2000"
                                          placeholder="Jelaskan jenis kerusakan"></textarea>

                                <label class="form-label">Bukti foto (opsional)</label>
                                <input type="file"
                                       name="evidence"
                                       accept="image/*"
                                       class="form-control mb-3">

                                <button type="submit" class="btn btn-outline-danger">
                                    Laporkan Kerusakan
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                {{-- Catat barang ditemukan --}}
                @if ($item->lost_quantity > 0)
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100">
                            <h3 class="h6 text-primary">
                                <i class="bi bi-search me-1"></i>
                                Catat Barang Ditemukan
                            </h3>

                            <p class="small text-muted">
                                Barang yang ditemukan masuk ke tahap pemeriksaan
                                terlebih dahulu, bukan langsung menjadi stok siap dipinjam.
                            </p>

                            <form method="POST"
                                  action="{{ route('admin.items.found', $item) }}"
                                  enctype="multipart/form-data">
                                @csrf

                                <label class="form-label">
                                    Jumlah ditemukan (maks. {{ $item->lost_quantity }})
                                </label>
                                <input type="number"
                                       name="quantity"
                                       min="1"
                                       max="{{ $item->lost_quantity }}"
                                       class="form-control mb-3"
                                       required>

                                <label class="form-label">Waktu ditemukan</label>
                                <input type="datetime-local"
                                       name="occurred_at"
                                       value="{{ now()->format('Y-m-d\TH:i') }}"
                                       class="form-control mb-3"
                                       required>

                                <label class="form-label">Keterangan</label>
                                <textarea name="description"
                                          class="form-control mb-3"
                                          rows="2"
                                          maxlength="2000"
                                          placeholder="Lokasi atau kondisi saat ditemukan"></textarea>

                                <label class="form-label">Bukti foto (opsional)</label>
                                <input type="file"
                                       name="evidence"
                                       accept="image/*"
                                       class="form-control mb-3">

                                <button type="submit" class="btn btn-outline-primary">
                                    Catat Barang Ditemukan
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                @if ($item->available_quantity < 1 && $item->lost_quantity < 1)
                    <div class="col-12">
                        <div class="alert alert-light border mb-0">
                            Tidak ada unit tersedia untuk dilaporkan rusak
                            dan tidak ada unit hilang yang menunggu pencatatan ditemukan.
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>


{{-- =========================================================
     3. TINDAK LANJUT KERUSAKAN
     Rusak ringan: mulai perbaikan
     Rusak berat: mulai perbaikan atau usulkan penghapusan
========================================================= --}}

@if ($item->light_damage_quantity > 0)
    <div class="card mb-4">
        <div class="card-header fw-semibold">
            <i class="bi bi-wrench-adjustable me-1"></i>
            Mulai Perbaikan Barang Rusak Ringan
        </div>

        <div class="card-body">
            <p class="text-muted small">
                Unit yang mulai diperbaiki berpindah ke status perbaikan.
                Unit tersebut belum boleh dihitung sebagai siap dipinjam.
            </p>

            <form method="POST"
                  action="{{ route('admin.items.repair-start', $item) }}"
                  enctype="multipart/form-data"
                  class="row g-3">
                @csrf

                <div class="col-md-4">
                    <label class="form-label">
                        Jumlah unit (maks. {{ $item->light_damage_quantity }})
                    </label>
                    <input type="number"
                           name="quantity"
                           min="1"
                           max="{{ $item->light_damage_quantity }}"
                           class="form-control"
                           required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Waktu mulai perbaikan</label>
                    <input type="datetime-local"
                           name="started_at"
                           value="{{ now()->format('Y-m-d\TH:i') }}"
                           class="form-control"
                           required>
                </div>

                <div class="col-12">
                    <label class="form-label">Keterangan pekerjaan</label>
                    <textarea name="description"
                              class="form-control"
                              rows="2"
                              maxlength="2000"
                              placeholder="Jelaskan pekerjaan perbaikan yang dilakukan"></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Bukti foto (opsional)</label>
                    <input type="file"
                           name="evidence"
                           accept="image/*"
                           class="form-control">
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-outline-primary">
                        Mulai Perbaikan
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif


@if ($item->heavy_damage_quantity > 0)
    @php
        $pendingDisposalQuantity = $item->disposalProposals
            ->where('status', 'pending')
            ->sum('quantity');

        $heavyDamageAvailable = max(
            0,
            $item->heavy_damage_quantity - $pendingDisposalQuantity
        );
    @endphp

    <div class="card mb-4">
        <div class="card-header fw-semibold">
            <i class="bi bi-cone-striped me-1"></i>
            Tindak Lanjut Barang Rusak Berat
        </div>

        <div class="card-body">
            <p class="text-muted small">
                Pilih tindakan sesuai kondisi unit. Penghapusan stok
                baru dilakukan setelah usulan disetujui admin.
            </p>

            @if ($heavyDamageAvailable > 0)
                <div class="row g-3">

                    {{-- Mulai perbaikan rusak berat --}}
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100">
                            <h3 class="h6">Mulai Perbaikan</h3>
                            <p class="small text-muted">
                                Gunakan jika barang masih memungkinkan untuk diperbaiki.
                            </p>

                            <form method="POST"
                                  action="{{ route('admin.items.heavy-damage-follow-up', $item) }}">
                                @csrf
                                <input type="hidden" name="action" value="start_repair">

                                <label class="form-label">
                                    Jumlah unit (maks. {{ $heavyDamageAvailable }})
                                </label>
                                <input type="number"
                                       name="quantity"
                                       min="1"
                                       max="{{ $heavyDamageAvailable }}"
                                       class="form-control mb-3"
                                       required>

                                <label class="form-label">Waktu mulai</label>
                                <input type="datetime-local"
                                       name="occurred_at"
                                       value="{{ now()->format('Y-m-d\TH:i') }}"
                                       class="form-control mb-3"
                                       required>

                                <label class="form-label">Keterangan</label>
                                <textarea name="description"
                                          class="form-control mb-3"
                                          rows="2"
                                          maxlength="2000"
                                          placeholder="Rencana atau tindakan perbaikan"></textarea>

                                <button type="submit" class="btn btn-outline-primary">
                                    Mulai Perbaikan
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- Usulkan penghapusan --}}
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100">
                            <h3 class="h6 text-danger">Usulkan Penghapusan</h3>
                            <p class="small text-muted">
                                Ajukan jika barang tidak layak atau tidak ekonomis
                                untuk diperbaiki. Stok tidak boleh dihapus sebelum disetujui.
                            </p>

                            <form method="POST"
                                  action="{{ route('admin.items.heavy-damage-follow-up', $item) }}">
                                @csrf
                                <input type="hidden" name="action" value="propose_disposal">

                                <label class="form-label">
                                    Jumlah unit (maks. {{ $heavyDamageAvailable }})
                                </label>
                                <input type="number"
                                       name="quantity"
                                       min="1"
                                       max="{{ $heavyDamageAvailable }}"
                                       class="form-control mb-3"
                                       required>

                                <label class="form-label">Waktu pengajuan</label>
                                <input type="datetime-local"
                                       name="occurred_at"
                                       value="{{ now()->format('Y-m-d\TH:i') }}"
                                       class="form-control mb-3"
                                       required>

                                <label class="form-label">Alasan usulan</label>
                                <textarea name="description"
                                          class="form-control mb-3"
                                          rows="2"
                                          maxlength="2000"
                                          required
                                          placeholder="Jelaskan alasan penghapusan"></textarea>

                                <button type="submit" class="btn btn-outline-danger">
                                    Ajukan Penghapusan
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            @else
                <div class="alert alert-info mb-0">
                    Seluruh unit rusak berat yang tercatat sedang menunggu
                    proses usulan penghapusan.
                </div>
            @endif
        </div>
    </div>
@endif


{{-- =========================================================
     4. PEMERIKSAAN AKHIR
     Satu bagian visual, dua form dengan route berbeda
========================================================= --}}

@if ($item->inspection_quantity > 0 || $item->repair_quantity > 0)
    <div class="card mb-4 border-primary">
        <div class="card-header fw-semibold">
            <i class="bi bi-clipboard-check me-1"></i>
            Pemeriksaan Akhir
        </div>

        <div class="card-body">
            <p class="text-muted small">
                Periksa barang yang baru ditemukan atau yang telah selesai
                diperbaiki. Barang hanya boleh kembali tersedia setelah
                hasil pemeriksaan dan transisi stok berhasil dicatat.
            </p>

            <div class="row g-3">

                {{-- Pemeriksaan barang ditemukan --}}
                @if ($item->inspection_quantity > 0)
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                <h3 class="h6 mb-0">Barang Ditemukan</h3>
                                <span class="badge bg-warning text-dark">
                                    {{ $item->inspection_quantity }} unit diperiksa
                                </span>
                            </div>

                            <form method="POST"
                                  action="{{ route('admin.items.inspect-found', $item) }}"
                                  enctype="multipart/form-data">
                                @csrf

                                <label class="form-label">
                                    Jumlah unit (maks. {{ $item->inspection_quantity }})
                                </label>
                                <input type="number"
                                       name="quantity"
                                       min="1"
                                       max="{{ $item->inspection_quantity }}"
                                       class="form-control mb-3"
                                       required>

                                <label class="form-label">Hasil pemeriksaan</label>
                                <select name="condition"
                                        class="form-select mb-3"
                                        required>
                                    <option value="">Pilih kondisi</option>
                                    @foreach ($inspectionConditions as $condition)
                                        <option value="{{ $condition }}">
                                            {{ ucfirst($condition) }}
                                        </option>
                                    @endforeach
                                </select>

                                <label class="form-label">Waktu pemeriksaan</label>
                                <input type="datetime-local"
                                       name="occurred_at"
                                       value="{{ now()->format('Y-m-d\TH:i') }}"
                                       class="form-control mb-3"
                                       required>

                                <label class="form-label">Catatan pemeriksaan</label>
                                <textarea name="description"
                                          class="form-control mb-3"
                                          rows="2"
                                          maxlength="2000"
                                          placeholder="Kondisi fisik, kelengkapan, dan hasil pemeriksaan"></textarea>

                                <label class="form-label">Bukti foto (opsional)</label>
                                <input type="file"
                                       name="evidence"
                                       accept="image/*"
                                       class="form-control mb-3">

                                <button type="submit" class="btn btn-primary">
                                    Simpan Hasil Pemeriksaan
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                {{-- Pemeriksaan setelah perbaikan --}}
                @if ($item->repair_quantity > 0)
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                <h3 class="h6 mb-0">Barang Selesai Diperbaiki</h3>
                                <span class="badge bg-info text-dark">
                                    {{ $item->repair_quantity }} unit menunggu pemeriksaan
                                </span>
                            </div>

                            <form method="POST"
                                  action="{{ route('admin.items.repair-complete', $item) }}"
                                  enctype="multipart/form-data">
                                @csrf

                                <label class="form-label">
                                    Jumlah unit (maks. {{ $item->repair_quantity }})
                                </label>
                                <input type="number"
                                       name="quantity"
                                       min="1"
                                       max="{{ $item->repair_quantity }}"
                                       class="form-control mb-3"
                                       required>

                                <label class="form-label">Hasil pemeriksaan</label>
                                <select name="result"
                                        class="form-select mb-3"
                                        required>
                                    <option value="">Pilih hasil</option>
                                    <option value="layak">Layak digunakan</option>
                                    <option value="perlu_perbaikan">
                                        Belum layak, perlu perbaikan lagi
                                    </option>
                                </select>

                                <label class="form-label">Tanggal pemeriksaan</label>
                                <input type="datetime-local"
                                       name="completed_at"
                                       value="{{ now()->format('Y-m-d\TH:i') }}"
                                       class="form-control mb-3"
                                       required>

                                <label class="form-label">Catatan tindakan dan hasil</label>
                                <textarea name="action_notes"
                                          class="form-control mb-3"
                                          rows="2"
                                          maxlength="2000"
                                          placeholder="Tindakan perbaikan dan hasil pemeriksaan"></textarea>

                                <label class="form-label">Bukti foto (opsional)</label>
                                <input type="file"
                                       name="evidence"
                                       accept="image/*"
                                       class="form-control mb-3">

                                <button type="submit" class="btn btn-success">
                                    Catat Hasil Perbaikan
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
@endif


{{-- =========================================================
     5. RIWAYAT USULAN PENGHAPUSAN
     Diletakkan paling bawah agar mudah ditelusuri
========================================================= --}}

@if ($item->disposalProposals->isNotEmpty())
    <div class="card mb-4">
        <div class="card-header fw-semibold">
            <i class="bi bi-clock-history me-1"></i>
            Riwayat Usulan Penghapusan
        </div>

        <div class="card-body">
            @foreach ($item->disposalProposals->sortByDesc('created_at') as $proposal)
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-semibold">
                                {{ $proposal->quantity }} unit
                            </div>
                            <div class="text-muted small">
                                {{ $proposal->reason }}
                            </div>
                        </div>

                        <span class="badge
                            {{ $proposal->status === 'pending'
                                ? 'bg-warning text-dark'
                                : ($proposal->status === 'approved'
                                    ? 'bg-success'
                                    : 'bg-secondary') }}">
                            {{ match ($proposal->status) {
                                'pending' => 'Menunggu persetujuan',
                                'approved' => 'Disetujui',
                                'rejected' => 'Ditolak',
                                default => $proposal->status
                            } }}
                        </span>
                    </div>

                    <div class="small text-muted mt-2">
                        Diajukan oleh {{ $proposal->proposer?->name ?? '-' }}
                        pada {{ $proposal->created_at->format('d/m/Y H:i') }}

                        @if ($proposal->reviewer)
                            <br>
                            Ditinjau oleh {{ $proposal->reviewer->name }}
                            @if ($proposal->reviewed_at)
                                pada {{ $proposal->reviewed_at->format('d/m/Y H:i') }}
                            @endif
                        @endif
                    </div>

                    @if ($proposal->review_note)
                        <div class="alert alert-light border mt-3 mb-0">
                            <strong>Catatan admin:</strong>
                            {{ $proposal->review_note }}
                        </div>
                    @endif

                    @if ($proposal->status === 'pending' && auth()->user()->isAdmin())
                        <form method="POST"
                              action="{{ route('admin.items.disposal-proposals.review', [$item, $proposal]) }}"
                              class="mt-3">
                            @csrf

                            <label class="form-label">Catatan admin (opsional)</label>
                            <textarea name="review_note"
                                      class="form-control mb-2"
                                      rows="2"
                                      maxlength="2000"
                                      placeholder="Alasan persetujuan atau penolakan"></textarea>

                            <div class="d-flex flex-wrap gap-2">
                                <button type="submit"
                                        name="decision"
                                        value="approve"
                                        class="btn btn-success btn-sm"
                                        onclick="return confirm('Setujui penghapusan {{ $proposal->quantity }} unit barang ini?')">
                                    Setujui Penghapusan
                                </button>

                                <button type="submit"
                                        name="decision"
                                        value="reject"
                                        class="btn btn-outline-danger btn-sm"
                                        onclick="return confirm('Tolak usulan penghapusan barang ini?')">
                                    Tolak Usulan
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif


{{-- =========================================================
     6. JAVASCRIPT — FORM PERUBAHAN STOK ADMIN
========================================================= --}}
@push('scripts')
    @if (auth()->user()->isAdmin())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const inventoryAction = document.getElementById('inventory_action');
                const sourceStateField = document.getElementById('inventory-source-state-field');
                const sourceState = document.getElementById('inventory_source_state');
                const inventoryQuantity = document.getElementById('inventory_quantity');
                const quantityHelp = document.getElementById('inventory-quantity-help');

                if (
                    !inventoryAction ||
                    !sourceStateField ||
                    !sourceState ||
                    !inventoryQuantity
                ) {
                    return;
                }

                function updateInventoryActionFields() {
                    const action = inventoryAction.value;
                    const isDisposal = action === 'penghapusan';
                    const isCorrection = action === 'koreksi';

                    sourceStateField.hidden = !isDisposal;
                    sourceState.required = isDisposal;

                    if (!isDisposal) {
                        sourceState.value = '';
                    }

                    inventoryQuantity.min = isCorrection ? '0' : '1';

                    if (isCorrection) {
                        quantityHelp.textContent =
                            'Masukkan jumlah layak dipinjam yang benar, sesuai aturan backend.';
                    } else if (isDisposal) {
                        quantityHelp.textContent =
                            'Masukkan jumlah unit yang dihapuskan dari kondisi yang dipilih.';
                    } else if (action === 'penambahan') {
                        quantityHelp.textContent =
                            'Masukkan jumlah unit baru yang benar-benar ditambahkan.';
                    } else {
                        quantityHelp.textContent =
                            'Masukkan jumlah unit yang dikurangi.';
                    }
                }

                inventoryAction.addEventListener('change', updateInventoryActionFields);
                updateInventoryActionFields();
            });
        </script>
    @endif
@endpush
