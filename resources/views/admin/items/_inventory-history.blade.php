<div class="card">
    <div class="card-header">Riwayat perubahan stok dan pemeriksaan</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead>
            <tr>
                <th>Waktu</th>
                <th>Tindakan</th>
                <th>Jumlah unit</th>
                <th>Perubahan Total</th>
                <th>Petugas</th>
                <th>Keterangan</th>
                <th>Bukti</th>
            </tr>
            </thead>
            <tbody>
            @php
                $eventActionLabels = [
                    'stock_added' => 'Barang ditambahkan',
                    'stock_removed' => 'Barang dikurangi',
                    'stock_corrected' => 'Jumlah stok dibetulkan',
                    'stock_disposed' => 'Barang dihapuskan',
                    'damage_reported' => 'Kerusakan dilaporkan',
                    'loan_handover' => 'Barang diserahkan untuk dipinjam',
                    'returned_good' => 'Barang kembali dalam kondisi baik',
                    'returned_light_damage' => 'Barang kembali rusak ringan',
                    'returned_heavy_damage' => 'Barang kembali rusak berat',
                    'lost_reported' => 'Barang hilang saat pengembalian',
                    'found_waiting_inspection' => 'Barang ditemukan, menunggu pemeriksaan',
                    'found_inspected' => 'Barang ditemukan diperiksa',
                    'repair_started' => 'Perbaikan dimulai',
                    'repair_completed' => 'Perbaikan selesai dan barang layak',
                    'repair_inspection_failed' => 'Pemeriksaan gagal, perlu perbaikan lagi',
                    'heavy_damage_returned_to_shelf' => 'Barang rusak berat dikembalikan ke rak',
                    'heavy_damage_disposal_proposed' => 'Usulan penghapusan rusak berat diajukan',
                    'heavy_damage_disposal_rejected' => 'Usulan penghapusan rusak berat ditolak',
                ];
                $eventStateLabels = [
                    'tersedia' => 'tersedia',
                    'dipinjam' => 'dipinjam',
                    'rusak ringan' => 'Rusak ringan',
                    'pemeriksaan' => 'Menunggu pemeriksaan',
                    'perbaikan' => 'Dalam perbaikan',
                    'rusak berat' => 'Rusak berat',
                    'hilang' => 'Hilang',
                    'dihapuskan' => 'Dihapuskan',
                    'belum tercatat' => 'Belum tercatat',
                    'usulan penghapusan' => 'Menunggu persetujuan penghapusan',
                ];
                $eventResultLabels = [
                    'baik' => 'Layak digunakan',
                    'layak' => 'Layak digunakan',
                    'perlu_perbaikan' => 'Belum layak, perlu perbaikan lagi',
                    'menunggu_persetujuan' => 'Menunggu persetujuan admin',
                    'disetujui' => 'Disetujui admin',
                    'ditolak' => 'Ditolak admin',
                ];
            @endphp
            @forelse ($item->inventoryEvents->sortByDesc('occurred_at') as $event)
                <tr>
                    <td>{{ $event->occurred_at->format('d/m/Y H:i') }}</td>
                    <td>
                        {{ $eventActionLabels[$event->action] ?? str_replace('_', ' ', $event->action) }}
                        @if ($event->state_before || $event->state_after)
                            <div class="small text-muted">
                                {{ $eventStateLabels[$event->state_before ?? ''] ?? $event->state_before ?? '-' }}
                                → {{ $eventStateLabels[$event->state_after ?? ''] ?? $event->state_after ?? '-' }}
                            </div>
                        @endif
                    </td>
                    <td>{{ $event->quantity }}</td>
                    <td>
                        @if ($event->quantity_before !== null && $event->quantity_after !== null)
                            @php($inventoryDelta = $event->quantity_after - $event->quantity_before)
                            {{ $inventoryDelta === 0 ? '0' : sprintf('%+d', $inventoryDelta) }}
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $event->actor?->name ?? '-' }}</td>
                    <td>
                        {{ $event->description }}
                        @if ($event->result)
                            <span class="badge bg-secondary">
                                {{ $eventResultLabels[$event->result] ?? str_replace('_', ' ', $event->result) }}
                            </span>
                        @endif
                    </td>
                    <td>
                        @if ($event->evidence_path)
                            <a href="{{ route('admin.items.evidence', [$item, $event]) }}">Lihat</a>
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-3">Belum ada riwayat perubahan inventaris.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
