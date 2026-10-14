<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bukti Peminjaman {{ $loan->loan_code }}</title>
    <style>
        body { font-family: Calibri, Arial, sans-serif; font-size: 12pt; color: #000; margin: 0; padding: 24px; }
        .sheet { max-width: 760px; margin: 0 auto; }
        h1 { font-size: 16pt; text-align: center; margin: 0 0 4px; }
        .sub { text-align: center; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.info td { padding: 2px 4px; vertical-align: top; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 4px 8px; text-align: left; }
        table.grid th.c, table.grid td.c { text-align: center; }
        .sign { display: flex; justify-content: space-between; margin-top: 40px; text-align: center; }
        .sign div { width: 40%; }
        .sign .space { height: 70px; }
        .toolbar { text-align: center; margin-bottom: 16px; }
        .muted { color: #444; }
        .section-title { font-weight: bold; margin: 18px 0 6px; }
        @media print { .toolbar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
<div class="sheet">
    <div class="toolbar">
        <button onclick="window.print()">Cetak / Simpan sebagai PDF</button>
    </div>

    <h1>BUKTI PEMINJAMAN BARANG KAMPUS</h1>
    <div class="sub">{{ config('campus.name') }}</div>

    <table class="info">
        <tr><td style="width:30%">Kode peminjaman</td><td>: {{ $loan->loan_code }}</td></tr>
        <tr><td>Nama peminjam</td><td>: {{ $loan->user->name }}</td></tr>
        <tr><td>{{ $loan->user->isStaff() ? 'NIP' : 'NIM' }}</td><td>: {{ $loan->user->identity_number ?: '-' }}</td></tr>
        <tr><td>Keperluan</td><td>: {{ $loan->purpose }}</td></tr>
        <tr><td>Tanggal pinjam</td><td>: {{ $loan->loan_date->format('d/m/Y') }}</td></tr>
        <tr><td>Batas kembali</td><td>: {{ $loan->due_date->format('d/m/Y') }}</td></tr>
        <tr><td>Status</td><td>: {{ $loan->statusLabel() }}</td></tr>
        <tr><td>Disetujui oleh</td><td>: {{ $loan->processor?->name ?? '-' }}{{ $loan->processed_at ? ' ('.$loan->processed_at->format('d/m/Y H:i').')' : '' }}</td></tr>
        <tr><td>Serah terima</td><td>: {{ $loan->handedOverBy?->name ?? '-' }}{{ $loan->handed_over_at ? ' ('.$loan->handed_over_at->format('d/m/Y H:i').')' : '' }}</td></tr>
        <tr><td>Pengembalian diterima</td><td>: {{ $loan->returnedTo?->name ?? '-' }}{{ $loan->returned_at ? ' ('.$loan->returned_at->format('d/m/Y H:i').')' : '' }}</td></tr>
    </table>

    <div class="section-title">Rincian barang</div>
    <table class="grid">
        <thead>
        <tr>
            <th class="c" style="width:6%">No</th><th>Kode</th><th>Nama barang</th><th class="c">Jumlah</th>
            @if ($loan->status === 'dikembalikan')
                <th>Kondisi kembali</th>
            @endif
        </tr>
        </thead>
        <tbody>
        @foreach ($loan->details as $detail)
            <tr>
                <td class="c">{{ $loop->iteration }}</td>
                <td>{{ $detail->item->item_code }}</td>
                <td>{{ $detail->item->name }}</td>
                <td class="c">{{ $detail->quantity }}</td>
                @if ($loan->status === 'dikembalikan')
                    <td>{{ ucfirst($detail->return_condition ?? '-') }}</td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>

    @if ($loan->returned_at)
        <div class="section-title">Informasi pengembalian</div>
        <p class="muted">{{ $loan->return_notes ?: 'Tidak ada catatan umum.' }}</p>
    @endif

    <p style="font-size:10.5pt">
        Peminjam bertanggung jawab atas barang selama masa peminjaman dan wajib mengembalikannya paling lambat pada batas kembali.
        Kerusakan atau kehilangan dilaporkan kepada petugas.
    </p>

    <div class="sign">
        <div>
            Peminjam<br>
            <div class="space"></div>
            ( {{ $loan->user->name }} )<br>
            <span class="muted">{{ $loan->handed_over_at?->format('d/m/Y H:i') ?? '' }}</span>
        </div>
        <div>
            Petugas<br>
            <div class="space"></div>
            ( {{ $loan->returnedTo?->name ?? $loan->handedOverBy?->name ?? $loan->processor?->name ?? '........................' }} )<br>
            <span class="muted">{{ $loan->returned_at?->format('d/m/Y H:i') ?? '' }}</span>
        </div>
    </div>

    <p style="font-size:9pt; margin-top:24px">Dicetak {{ now()->format('d/m/Y H:i') }}</p>
</div>
</body>
</html>
