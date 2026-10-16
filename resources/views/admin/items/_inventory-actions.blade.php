    @if (auth()->user()->isAdmin())
        <div class="card mt-3">
            <div class="card-header">Catat perubahan stok barang</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.items.adjust-stock', $item) }}" class="row g-2">
                    @csrf
                    <div class="col-md-3">
                        <label for="action" class="form-label">Jenis perubahan</label>
                        <select id="action" name="action" class="form-select" required>
                            <option value="penambahan">Penambahan barang</option>
                            <option value="pengurangan">Pengurangan barang</option>
                            <option value="koreksi">Betulkan jumlah layak dipinjam</option>
                            <option value="penghapusan">Penghapusan / pemusnahan</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="quantity" class="form-label">Jumlah unit</label>
                        <input type="number" id="quantity" name="quantity" class="form-control" value="{{ old('quantity') }}" required>
                    </div>
                    <div class="col-md-3" id="source-state-field" hidden>
                        <label for="source_state" class="form-label">Kondisi barang yang dihapuskan</label>
                        <select id="source_state" name="source_state" class="form-select">
                            <option value="">Pilih kondisi barang</option>
                            @foreach (['tersedia', 'rusak ringan', 'pemeriksaan', 'perbaikan', 'rusak berat', 'hilang'] as $sourceState)
                                <option value="{{ $sourceState }}">{{ ucfirst($sourceState) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="reason" class="form-label">Keterangan / nomor dokumen (opsional)</label>
                        <textarea id="reason" name="reason" class="form-control" rows="2" maxlength="2000">{{ old('reason') }}</textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary">Catat transaksi inventaris</button>
                    </div>
                </form>
                @error('action')<div class="text-danger mt-2">{{ $message }}</div>@enderror
                @error('quantity')<div class="text-danger mt-2">{{ $message }}</div>@enderror
                @error('source_state')<div class="text-danger mt-2">{{ $message }}</div>@enderror
                @error('reason')<div class="text-danger mt-2">{{ $message }}</div>@enderror
            </div>
        </div>
    @endif

    <h2 class="h5 mb-3">Tindakan untuk {{ $item->name }}</h2>
    <div id="inventory-primary-actions">
        <div class="d-flex flex-wrap gap-2 mb-3">
            <button class="btn btn-outline-danger" type="button" data-bs-toggle="collapse"
                    data-bs-target="#report-damage-form" aria-expanded="false" aria-controls="report-damage-form"
                    @disabled($item->available_quantity < 1)>
                Laporkan kerusakan
            </button>
            <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse"
                    data-bs-target="#found-item-actions"
                    aria-expanded="{{ $item->inspection_quantity > 0 ? 'true' : 'false' }}"
                    aria-controls="found-item-actions"
                    @disabled($item->lost_quantity < 1 && $item->inspection_quantity < 1)>
                Barang ditemukan
            </button>
        </div>
        <div class="row g-3">
            @if ($item->available_quantity > 0)
                <div class="col-12 collapse" id="report-damage-form">
                    <div class="card"><div class="card-body">
                        <h3 class="h6">Laporkan kerusakan</h3>
                        <form method="POST" action="{{ route('admin.items.report-damage', $item) }}" enctype="multipart/form-data">
                            @csrf
                            <label class="form-label">Jumlah unit (maks. {{ $item->available_quantity }})</label>
                            <input type="number" name="quantity" min="1" max="{{ $item->available_quantity }}" class="form-control mb-2" required>
                            <label class="form-label">Kondisi kerusakan</label>
                            <select name="condition" class="form-select mb-2" required>
                                <option value="">Pilih kondisi</option>
                                <option value="rusak ringan">Rusak ringan</option>
                                <option value="rusak berat">Rusak berat</option>
                            </select>
                            <label class="form-label">Waktu kejadian</label>
                            <input type="datetime-local" name="occurred_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="form-control mb-2" required>
                            <label class="form-label">Keterangan (opsional)</label>
                            <textarea name="description" class="form-control mb-2" maxlength="2000" placeholder="Jelaskan kerusakan"></textarea>
                            <label class="form-label">Bukti foto (opsional)</label>
                            <input type="file" name="evidence" accept="image/*" class="form-control mb-2">
                            <button class="btn btn-outline-danger">Laporkan Kerusakan</button>
                        </form>
                    </div></div>
                </div>
            @endif
            @if ($item->lost_quantity > 0 || $item->inspection_quantity > 0)
                <div class="col-12 collapse {{ $item->inspection_quantity > 0 ? 'show' : '' }}" id="found-item-actions">
                    <div class="card"><div class="card-body">
                        @if ($item->lost_quantity > 0)
                            <h3 class="h6">Catat barang ditemukan</h3>
                            <form method="POST" action="{{ route('admin.items.found', $item) }}" enctype="multipart/form-data">
                                @csrf
                                <label class="form-label">Jumlah ditemukan (maks. {{ $item->lost_quantity }})</label>
                                <input type="number" name="quantity" min="1" max="{{ $item->lost_quantity }}" class="form-control mb-2" required>
                                <label class="form-label">Waktu ditemukan</label>
                                <input type="datetime-local" name="occurred_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="form-control mb-2" required>
                                <label class="form-label">Keterangan (opsional)</label>
                                <textarea name="description" class="form-control mb-2" maxlength="2000" placeholder="Keterangan penemuan"></textarea>
                                <input type="file" name="evidence" accept="image/*" class="form-control mb-2">
                                <button class="btn btn-outline-primary">Catat Barang Ditemukan</button>
                            </form>
                        @endif
                        @if ($item->inspection_quantity > 0)
                            <hr>
                            <h3 class="h6">Barang ditemukan</h3>
                            <form method="POST" action="{{ route('admin.items.inspect-found', $item) }}" enctype="multipart/form-data">
                                @csrf
                                <label class="form-label">Jumlah (maks. {{ $item->inspection_quantity }})</label>
                                <input type="number" name="quantity" min="1" max="{{ $item->inspection_quantity }}" class="form-control mb-2" required>
                                <label class="form-label">Hasil pemeriksaan</label>
                                <select name="condition" class="form-select mb-2" required>
                                    <option value="">Pilih kondisi</option>
                                    @foreach ($inspectionConditions as $condition)
                                        <option value="{{ $condition }}">{{ ucfirst($condition) }}</option>
                                    @endforeach
                                </select>
                                <label class="form-label">Waktu pemeriksaan</label>
                                <input type="datetime-local" name="occurred_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="form-control mb-2" required>
                                <label class="form-label">Keterangan pemeriksaan (opsional)</label>
                                <textarea name="description" class="form-control mb-2" maxlength="2000" placeholder="Hasil/catatan pemeriksaan"></textarea>
                                <input type="file" name="evidence" accept="image/*" class="form-control mb-2">
                                <button class="btn btn-primary">Simpan Hasil Pemeriksaan</button>
                            </form>
                        @endif
                    </div></div>
                </div>
            @endif
        </div>
    </div>

    @if ($item->heavy_damage_quantity > 0)
        @php
            $pendingDisposalQuantity = $item->disposalProposals
                ->where('status', 'pending')
                ->sum('quantity');
            $heavyDamageAvailable = max(0, $item->heavy_damage_quantity - $pendingDisposalQuantity);
        @endphp
        <div class="card mb-3">
            <div class="card-header fw-semibold">Tindak lanjut barang rusak berat</div>
            <div class="card-body">
                <p class="text-muted">Pilih tindakan setelah memastikan kondisi barang: kembalikan ke rak, mulai perbaikan, atau usulkan penghapusan kepada admin.</p>
                @if ($heavyDamageAvailable > 0)
                    <div class="row g-3">
                        <div class="col-lg-4">
                            <form method="POST" action="{{ route('admin.items.heavy-damage-follow-up', $item) }}" class="border rounded p-3 h-100">
                                @csrf
                                <input type="hidden" name="action" value="return_to_shelf">
                                <h3 class="h6">Kembalikan ke rak</h3>
                                <p class="small text-muted">Gunakan hanya jika barang sudah dipastikan aman dan layak digunakan.</p>
                                <label class="form-label">Jumlah (maks. {{ $heavyDamageAvailable }})</label>
                                <input type="number" name="quantity" min="1" max="{{ $heavyDamageAvailable }}" class="form-control mb-2" required>
                                <label class="form-label">Waktu tindak lanjut</label>
                                <input type="datetime-local" name="occurred_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="form-control mb-2" required>
                                <label class="form-label">Keterangan (opsional)</label>
                                <textarea name="description" class="form-control mb-2" maxlength="2000"></textarea>
                                <button class="btn btn-outline-success">Kembalikan ke rak</button>
                            </form>
                        </div>
                        <div class="col-lg-4">
                            <form method="POST" action="{{ route('admin.items.heavy-damage-follow-up', $item) }}" class="border rounded p-3 h-100">
                                @csrf
                                <input type="hidden" name="action" value="start_repair">
                                <h3 class="h6">Mulai perbaikan</h3>
                                <label class="form-label">Jumlah (maks. {{ $heavyDamageAvailable }})</label>
                                <input type="number" name="quantity" min="1" max="{{ $heavyDamageAvailable }}" class="form-control mb-2" required>
                                <label class="form-label">Waktu mulai</label>
                                <input type="datetime-local" name="occurred_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="form-control mb-2" required>
                                <label class="form-label">Keterangan (opsional)</label>
                                <textarea name="description" class="form-control mb-2" maxlength="2000"></textarea>
                                <button class="btn btn-outline-primary">Mulai perbaikan</button>
                            </form>
                        </div>
                        <div class="col-lg-4">
                            <form method="POST" action="{{ route('admin.items.heavy-damage-follow-up', $item) }}" class="border rounded p-3 h-100">
                                @csrf
                                <input type="hidden" name="action" value="propose_disposal">
                                <h3 class="h6">Usulkan penghapusan</h3>
                                <p class="small text-muted">Stok baru dihapus setelah usulan disetujui admin.</p>
                                <label class="form-label">Jumlah (maks. {{ $heavyDamageAvailable }})</label>
                                <input type="number" name="quantity" min="1" max="{{ $heavyDamageAvailable }}" class="form-control mb-2" required>
                                <label class="form-label">Waktu pengajuan</label>
                                <input type="datetime-local" name="occurred_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="form-control mb-2" required>
                                <label class="form-label">Alasan usulan</label>
                                <textarea name="description" class="form-control mb-2" maxlength="2000" required></textarea>
                                <button class="btn btn-outline-danger">Ajukan penghapusan</button>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="alert alert-info">Seluruh barang rusak berat sedang menunggu persetujuan usulan penghapusan.</div>
                @endif

                @if ($item->disposalProposals->isNotEmpty())
                    <h3 class="h6 mt-4">Riwayat usulan penghapusan</h3>
                    @foreach ($item->disposalProposals->sortByDesc('created_at') as $proposal)
                        <div class="border rounded p-3 mb-2">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <strong>{{ $proposal->quantity }} unit — {{ $proposal->reason }}</strong>
                                <span class="badge {{ $proposal->status === 'pending' ? 'bg-warning text-dark' : ($proposal->status === 'approved' ? 'bg-success' : 'bg-secondary') }}">
                                    {{ match ($proposal->status) { 'pending' => 'Menunggu persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', default => $proposal->status } }}
                                </span>
                            </div>
                            <div class="small text-muted mt-1">
                                Diajukan {{ $proposal->proposer?->name ?? '-' }} pada {{ $proposal->created_at->format('d/m/Y H:i') }}
                                @if ($proposal->reviewer)
                                    · Ditinjau {{ $proposal->reviewer->name }} pada {{ $proposal->reviewed_at->format('d/m/Y H:i') }}
                                @endif
                            </div>
                            @if ($proposal->review_note)
                                <div class="small mt-1">Catatan admin: {{ $proposal->review_note }}</div>
                            @endif
                            @if ($proposal->status === 'pending' && auth()->user()->isAdmin())
                                <form method="POST" action="{{ route('admin.items.disposal-proposals.review', [$item, $proposal]) }}" class="mt-3">
                                    @csrf
                                    <label class="form-label">Catatan admin (opsional)</label>
                                    <textarea name="review_note" class="form-control mb-2" maxlength="2000"></textarea>
                                    <button class="btn btn-success btn-sm" name="decision" value="approve">Setujui penghapusan</button>
                                    <button class="btn btn-outline-danger btn-sm" name="decision" value="reject">Tolak usulan</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    @endif

    @if ($item->repair_quantity > 0)
        <div class="card mb-3">
            <div class="card-body">
                <h3 class="h6">Pemeriksaan setelah perbaikan</h3>
                <form method="POST" action="{{ route('admin.items.repair-complete', $item) }}" enctype="multipart/form-data">
                    @csrf
                    <label class="form-label">Jumlah diperiksa (maks. {{ $item->repair_quantity }})</label>
                    <input type="number" name="quantity" min="1" max="{{ $item->repair_quantity }}" class="form-control mb-2" required>
                    <label class="form-label">Hasil pemeriksaan</label>
                    <select name="result" class="form-select mb-2" required>
                        <option value="">Pilih hasil</option>
                        <option value="layak">Layak digunakan</option>
                        <option value="perlu_perbaikan">Belum layak, perlu perbaikan lagi</option>
                    </select>
                    <label class="form-label">Tanggal selesai diperiksa</label>
                    <input type="datetime-local" name="completed_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="form-control mb-2" required>
                    <label class="form-label">Keterangan tindakan dan hasil pemeriksaan (opsional)</label>
                    <textarea name="action_notes" class="form-control mb-2" maxlength="2000" placeholder="Tindakan perbaikan dan hasil pemeriksaan"></textarea>
                    <input type="file" name="evidence" accept="image/*" class="form-control mb-2">
                    <button class="btn btn-success">Catat Hasil Perbaikan</button>
                </form>
            </div>
        </div>
    @endif
    @if ($item->light_damage_quantity > 0)
        <div class="card mb-3">
            <div class="card-body">
                <h3 class="h6">Mulai perbaikan barang rusak ringan</h3>
                <form method="POST" action="{{ route('admin.items.repair-start', $item) }}" enctype="multipart/form-data">
                    @csrf
                    <label class="form-label">Jumlah (maks. {{ $item->light_damage_quantity }})</label>
                    <input type="number" name="quantity" min="1" max="{{ $item->light_damage_quantity }}" class="form-control mb-2" required>
                    <label class="form-label">Waktu mulai</label>
                    <input type="datetime-local" name="started_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="form-control mb-2" required>
                    <label class="form-label">Keterangan (opsional)</label>
                    <textarea name="description" class="form-control mb-2" maxlength="2000" placeholder="Keterangan pekerjaan perbaikan"></textarea>
                    <label class="form-label">Bukti foto (opsional)</label>
                    <input type="file" name="evidence" accept="image/*" class="form-control mb-2">
                    <button class="btn btn-outline-primary">Mulai perbaikan</button>
                </form>
            </div>
        </div>
    @endif

@push('scripts')
    @if (auth()->user()->isAdmin())
        <script>
            const inventoryAction = document.getElementById('action');
            const sourceStateField = document.getElementById('source-state-field');
            const sourceState = document.getElementById('source_state');
            const inventoryQuantity = document.getElementById('quantity');

            const updateInventoryActionFields = () => {
                const isDisposal = inventoryAction.value === 'penghapusan';
                sourceStateField.hidden = !isDisposal;
                sourceState.required = isDisposal;
                inventoryQuantity.min = inventoryAction.value === 'koreksi' ? '0' : '1';
                inventoryQuantity.placeholder = inventoryAction.value === 'koreksi' ? 'Jumlah layak pinjam yang benar' : 'Jumlah positif';
            };

            inventoryAction.addEventListener('change', updateInventoryActionFields);
            updateInventoryActionFields();
        </script>
    @endif
@endpush
