{{-- Detail peminjaman, dipakai di halaman peminjam dan petugas. Butuh $loan (relasi sudah di-load). --}}
{{-- Opsional: $availability (array detail_id => jumlah tersedia pada periode) --}}
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>{{ $loan->loan_code }}</span>
        <span>
            <span class="badge bg-{{ $loan->statusColor() }}">{{ $loan->statusLabel() }}</span>
            @if ($loan->isOverdue())
                <span class="badge bg-danger">Terlambat</span>
            @endif
        </span>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless table-sm mb-0">
                    <tr><th class="w-50">Peminjam</th><td>{{ $loan->user->name }}</td></tr>
                    <tr><th>NIM/NIP</th><td>{{ $loan->user->identity_number ?: '-' }}</td></tr>
                    <tr><th>Kontak</th><td>{{ $loan->user->phone ?: $loan->user->email }}</td></tr>
                    <tr><th>Keperluan</th><td>{{ $loan->purpose }}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless table-sm mb-0">
                    <tr><th class="w-50">Tanggal pinjam</th><td>{{ $loan->loan_date->format('d/m/Y') }}</td></tr>
                    <tr><th>Batas kembali</th><td>{{ $loan->due_date->format('d/m/Y') }}</td></tr>
                    <tr><th>Diajukan</th><td>{{ $loan->created_at->format('d/m/Y H:i') }}</td></tr>
                    @if ($loan->processed_at)
                        <tr>
                            <th>{{ $loan->status === 'ditolak' ? 'Ditolak' : 'Disetujui' }} oleh</th>
                            <td>{{ $loan->processor?->name ?? '-' }} ({{ $loan->processed_at->format('d/m/Y H:i') }})</td>
                        </tr>
                    @endif
                    @if ($loan->handed_over_at)
                        <tr><th>Diserahkan</th><td>{{ $loan->handedOverBy?->name ?? '-' }} ({{ $loan->handed_over_at->format('d/m/Y H:i') }})</td></tr>
                    @endif
                    @if ($loan->returned_at)
                        <tr><th>Dikembalikan</th><td>{{ $loan->returnedTo?->name ?? '-' }} ({{ $loan->returned_at->format('d/m/Y H:i') }})</td></tr>
                    @endif
                </table>
            </div>
        </div>

        @if ($loan->status === 'ditolak')
            <div class="alert alert-danger mt-3 mb-0"><strong>Alasan penolakan:</strong> {{ $loan->rejection_reason }}</div>
        @endif
        @if ($loan->return_notes)
            <div class="alert alert-secondary mt-3 mb-0"><strong>Catatan pengembalian:</strong> {{ $loan->return_notes }}</div>
        @endif
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Barang yang dipinjam</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead>
            <tr>
                <th>Kode</th><th>Nama</th><th class="text-center">Jumlah</th>
                @isset($availability)
                    @if (count($availability))
                        <th class="text-center">Tersedia pada periode</th>
                    @endif
                @endisset
                <th>Baik</th><th>Rusak ringan</th><th>Rusak berat</th><th>Hilang</th><th>Belum kembali</th><th>Pemeriksa / waktu</th><th>Catatan</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($loan->details as $detail)
                <tr>
                    <td>{{ $detail->item->item_code }}</td>
                    <td>{{ $detail->item->name }}</td>
                    <td class="text-center">{{ $detail->quantity }}</td>
                    @isset($availability)
                        @if (count($availability))
                            @php $tersedia = $availability[$detail->id] ?? 0; @endphp
                            <td class="text-center">
                                <span class="badge {{ $tersedia >= $detail->quantity ? 'bg-success' : 'bg-danger' }}">{{ $tersedia }}</span>
                            </td>
                        @endif
                    @endisset
                    <td>{{ $detail->returned_good_quantity }}</td>
                    <td>{{ $detail->returned_light_damage_quantity }}</td>
                    <td>{{ $detail->returned_heavy_damage_quantity }}</td>
                    <td>{{ $detail->returned_lost_quantity }}</td>
                    <td>{{ $detail->outstandingQuantity() }}</td>
                    <td>{{ $detail->returnInspector?->name ?? '-' }} @if ($detail->return_inspected_at) ({{ $detail->return_inspected_at->format('d/m/Y H:i') }}) @endif</td>
                    <td>
                        {{ $detail->return_note ?: '-' }}
                        @if ($detail->return_photo)
                            <a href="{{ route('loans.evidence', $detail) }}">Bukti</a>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
