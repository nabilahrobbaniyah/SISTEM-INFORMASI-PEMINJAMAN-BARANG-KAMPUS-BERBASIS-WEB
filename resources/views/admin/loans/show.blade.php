@extends('layouts.admin')

@section('title', $loan->loan_code)
@section('heading', 'Detail Peminjaman')

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach (array_unique($errors->all()) as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @include('loans._detail', ['loan' => $loan, 'availability' => $availability])

    {{-- Menunggu: setujui atau tolak --}}
    @if ($loan->status === 'menunggu')
        <div class="row g-3 mb-3">
            <div class="col-md-5">
                <div class="card h-100"><div class="card-body">
                    <h2 class="h6">Setujui pengajuan</h2>
                    <p class="small text-muted">Sistem akan mengecek ulang ketersediaan barang pada periode ini sebelum menyetujui.</p>
                    <form method="POST" action="{{ route('admin.loans.approve', $loan) }}"
                          onsubmit="return confirm('Setujui pengajuan ini?')">
                        @csrf
                        <button type="submit" class="btn btn-success">Setujui</button>
                    </form>
                </div></div>
            </div>
            <div class="col-md-7">
                <div class="card h-100"><div class="card-body">
                    <h2 class="h6">Tolak pengajuan</h2>
                    <p class="small text-muted">Tindakan untuk {{ $loan->loan_code }} atas nama {{ $loan->user->name }}.</p>
                    <button type="button" class="btn btn-outline-danger" id="open-rejection-dialog">Tolak Pengajuan</button>
                </div></div>
            </div>
        </div>
        <dialog id="rejection-dialog" class="w-100 border rounded shadow" style="max-width: 36rem">
            <form method="POST" action="{{ route('admin.loans.reject', $loan) }}" id="rejection-form">
                @csrf
                <h2 class="h5">Konfirmasi penolakan</h2>
                <p>Pengajuan <strong>{{ $loan->loan_code }}</strong> dari <strong>{{ $loan->user->name }}</strong> akan ditolak.</p>
                <label for="reason_code" class="form-label">Alasan penolakan</label>
                <select id="reason_code" name="reason_code" class="form-select" required>
                    <option value="">Pilih alasan</option>
                    @foreach ($rejectionReasons as $reason)
                        <option value="{{ $reason }}" @selected(old('reason_code') === $reason)>{{ ucfirst($reason) }}</option>
                    @endforeach
                </select>
                <textarea id="custom_reason" name="custom_reason" rows="3" maxlength="500" minlength="5"
                          class="form-control mt-2" placeholder="Jelaskan alasan lainnya" hidden>{{ old('custom_reason') }}</textarea>
                @error('reason_code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @error('custom_reason')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-outline-secondary" id="cancel-rejection">Kembali</button>
                    <button type="submit" class="btn btn-danger">Konfirmasi Penolakan</button>
                </div>
            </form>
        </dialog>
    @endif

    {{-- Disetujui: serah terima --}}
    @if ($loan->status === 'disetujui')
        <div class="card mb-3"><div class="card-body">
            <h2 class="h6">Serah terima barang</h2>
            <p class="small text-muted">Catat saat barang diserahkan ke peminjam. Stok di tempat akan berkurang.</p>
            <form method="POST" action="{{ route('admin.loans.handover', $loan) }}"
                  onsubmit="return confirm('Barang sudah diserahkan ke peminjam?')">
                @csrf
                <button type="submit" class="btn btn-primary">Catat Serah Terima</button>
            </form>
        </div></div>
    @endif

    {{-- Dipinjam: pengembalian --}}
    @if ($loan->status === 'dipinjam')
        <div class="card mb-3">
            <div class="card-header">Catat pengembalian</div>
            <form method="POST" action="{{ route('admin.loans.return', $loan) }}" enctype="multipart/form-data">
                @csrf
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>Barang</th>
                            <th class="text-center">Dipinjam</th>
                            <th class="text-center">Belum kembali</th>
                            <th>Baik</th>
                            <th>Rusak ringan</th>
                            <th>Rusak berat</th>
                            <th>Hilang</th>
                            <th>Catatan / bukti</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($loan->details as $detail)
                            <tr>
                                <td>{{ $detail->item->name }}</td>
                                <td class="text-center">{{ $detail->quantity }}</td>
                                <td class="text-center">{{ $detail->outstandingQuantity() }}</td>
                                @php
                                    $returnFields = ['good_quantity', 'light_damage_quantity', 'heavy_damage_quantity', 'lost_quantity'];
                                @endphp
                                @foreach ($returnFields as $field)
                                    <td>
                                        <input type="number" min="0" max="{{ $detail->outstandingQuantity() }}"
                                               name="returns[{{ $detail->id }}][{{ $field }}]"
                                               value="{{ old("returns.{$detail->id}.{$field}") }}"
                                               class="form-control form-control-sm return-quantity"
                                               data-outstanding="{{ $detail->outstandingQuantity() }}"
                                               aria-label="{{ ucfirst(str_replace('_quantity', '', $field)) }}">
                                    </td>
                                @endforeach
                                <td>
                                    <input type="text" name="returns[{{ $detail->id }}][note]" maxlength="255"
                                           value="{{ old("returns.{$detail->id}.note") }}" class="form-control form-control-sm"
                                           placeholder="Catatan (opsional)">
                                    <input type="file" name="returns[{{ $detail->id }}][photo]" accept="image/*"
                                           class="form-control form-control-sm mt-1" aria-label="Foto bukti kondisi">
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body">
                    <p class="small text-muted">Untuk setiap barang, isi jumlah unit yang kembali dalam tiap kondisi. Total “Baik”, “Rusak ringan”, “Rusak berat”, dan “Hilang” tidak boleh melebihi jumlah yang belum kembali.</p>
                    <textarea name="return_notes" rows="2" maxlength="500" class="form-control mb-2"
                              placeholder="Catatan umum pengembalian (opsional)">{{ old('return_notes') }}</textarea>
                    <button type="submit" class="btn btn-success">Simpan Pengembalian</button>
                </div>
            </form>
        </div>
    @endif

    <div class="d-flex gap-2">
        <a href="{{ route('admin.loans.index') }}" class="btn btn-outline-secondary">Kembali</a>
        @if (in_array($loan->status, ['disetujui', 'dipinjam', 'dikembalikan'], true))
            <a href="{{ route('loans.print', $loan) }}" target="_blank" class="btn btn-outline-primary">Cetak Bukti</a>
        @endif
    </div>
@endsection

@push('scripts')
    @if ($loan->status === 'dipinjam')
        <script>
            document.querySelectorAll('.return-quantity').forEach((changedInput) => {
                const row = changedInput.closest('tr');
                const inputs = Array.from(row.querySelectorAll('.return-quantity'));
                const outstanding = Number(changedInput.dataset.outstanding);

                const updateMaximums = (inputToClamp = null) => {
                    if (inputToClamp) {
                        const otherValues = inputs
                            .filter((input) => input !== inputToClamp)
                            .reduce((total, input) => total + (Number(input.value) || 0), 0);
                        inputToClamp.max = String(Math.max(0, outstanding - otherValues));
                        if (inputToClamp.value !== '') {
                            inputToClamp.value = String(Math.max(
                                0,
                                Math.min(Number(inputToClamp.value) || 0, Number(inputToClamp.max))
                            ));
                        }
                    }

                    inputs.forEach((input) => {
                        const otherValues = inputs
                            .filter((otherInput) => otherInput !== input)
                            .reduce((total, otherInput) => total + (Number(otherInput.value) || 0), 0);
                        input.max = String(Math.max(0, outstanding - otherValues));
                    });
                };

                inputs.forEach((input) => input.addEventListener('input', () => updateMaximums(input)));
                updateMaximums();
            });
        </script>
    @endif
    @if ($loan->status === 'menunggu')
        <script>
            const rejectionDialog = document.getElementById('rejection-dialog');
            const reasonCode = document.getElementById('reason_code');
            const customReason = document.getElementById('custom_reason');
            document.getElementById('open-rejection-dialog').addEventListener('click', () => rejectionDialog.showModal());
            document.getElementById('cancel-rejection').addEventListener('click', () => rejectionDialog.close());
            reasonCode.addEventListener('change', () => {
                customReason.hidden = reasonCode.value !== 'lainnya';
                customReason.required = reasonCode.value === 'lainnya';
            });
            reasonCode.dispatchEvent(new Event('change'));
            @if ($errors->has('reason_code') || $errors->has('custom_reason'))
                rejectionDialog.showModal();
            @endif
        </script>
    @endif
@endpush
