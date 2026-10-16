@extends('layouts.admin')

@section('title', 'Konfirmasi Pengajuan')
@section('heading', 'Konfirmasi Pengajuan Peminjaman')

@section('content')
    @php
        $loanDate = \Illuminate\Support\Carbon::parse($draft['loan_date']);
        $dueDate = \Illuminate\Support\Carbon::parse($draft['due_date']);
    @endphp

    <div class="card card-body mb-3">
        <h2 class="h5">Periksa kembali pengajuan Anda</h2>
        <dl class="row mb-0">
            <dt class="col-sm-3">Tanggal pinjam</dt><dd class="col-sm-9">{{ $loanDate->format('d/m/Y') }}</dd>
            <dt class="col-sm-3">Tanggal kembali</dt><dd class="col-sm-9">{{ $dueDate->format('d/m/Y') }}</dd>
            <dt class="col-sm-3">Keperluan</dt><dd class="col-sm-9">{{ $draft['purpose'] }}</dd>
        </dl>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Kode barang</th><th>Nama barang</th><th class="text-end">Jumlah</th></tr></thead>
                <tbody>
                @foreach ($draft['items'] as $row)
                    @php $item = $items->get((int) $row['item_id']); @endphp
                    <tr>
                        <td>{{ $item?->item_code ?? 'Barang tidak ditemukan' }}</td>
                        <td>{{ $item?->name ?? '-' }}</td>
                        <td class="text-end">{{ $row['quantity'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="small text-muted mt-3 mb-0">Ketersediaan akan diperiksa kembali saat konfirmasi akhir.</p>
    </div>

    <div class="d-flex gap-2">
        <a href="{{ route('peminjam.loans.create') }}" class="btn btn-outline-secondary">Kembali untuk Mengedit</a>
        <form id="confirm-loan-form" method="POST" action="{{ route('peminjam.loans.store') }}">
            @csrf
            <input type="hidden" name="submission_key" value="{{ session('loan_confirmation.key') }}">
            <button type="submit" class="btn btn-primary">Konfirmasi Pengajuan</button>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('confirm-loan-form').addEventListener('submit', function () {
            const button = this.querySelector('button[type="submit"]');
            button.disabled = true;
            button.textContent = 'Mengirim...';
        });
    </script>
@endpush
