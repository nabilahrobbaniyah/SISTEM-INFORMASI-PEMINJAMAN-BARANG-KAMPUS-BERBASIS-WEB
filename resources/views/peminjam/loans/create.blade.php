@extends('layouts.admin')

@section('title', 'Ajukan Peminjaman')
@section('heading', 'Ajukan Peminjaman')

@section('content')
    @php
        $draftRows = $draft['items'] ?? null;
        $rows = old('items', $draftRows ?? ($prefillItem ? [['item_id' => $prefillItem, 'quantity' => 1]] : [['item_id' => '', 'quantity' => 1]]));
        $rows = array_values($rows);
    @endphp

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach (array_unique($errors->all()) as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('peminjam.loans.review') }}" class="card card-body" id="loan-request-form">
        @csrf

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label for="loan_date" class="form-label">Tanggal pinjam</label>
                <input type="date" id="loan_date" name="loan_date" value="{{ old('loan_date', $draft['loan_date'] ?? $loanDate) }}"
                       min="{{ now()->toDateString() }}" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label for="due_date" class="form-label">Tanggal kembali</label>
                <input type="date" id="due_date" name="due_date" value="{{ old('due_date', $draft['due_date'] ?? $dueDate) }}"
                       min="{{ now()->toDateString() }}" class="form-control" required>
                <div class="form-text">Maksimal {{ $maxDays }} hari.</div>
            </div>
            <div class="col-md-6">
                <label for="purpose" class="form-label">Keperluan</label>
                <input type="text" id="purpose" name="purpose" value="{{ old('purpose', $draft['purpose'] ?? '') }}" maxlength="500"
                       class="form-control" placeholder="Contoh: Presentasi tugas akhir" required>
            </div>
        </div>

        <label class="form-label">Barang yang dipinjam</label>
        <div id="rows">
            @foreach ($rows as $i => $row)
                <div class="row g-2 mb-2 item-row">
                    <div class="col-md-8">
                        <select name="items[{{ $i }}][item_id]" class="form-select" required>
                            <option value="">Pilih barang</option>
                            @foreach ($items as $item)
                                <option value="{{ $item->id }}" @selected(($row['item_id'] ?? '') == $item->id)>
                                    {{ $item->item_code }} - {{ $item->name }} ({{ $item->category->name }}, total {{ $item->total_quantity }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="number" min="1" name="items[{{ $i }}][quantity]" value="{{ $row['quantity'] ?? 1 }}"
                               class="form-control" placeholder="Jumlah" required>
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-outline-danger w-100 remove-row" title="Hapus baris">&times;</button>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mb-3">
            <button type="button" id="add-row" class="btn btn-sm btn-outline-secondary">+ Tambah barang</button>
        </div>

        <div class="form-text mb-3">
            Pengajuan tidak langsung mengurangi stok. Stok dialokasikan setelah petugas menyetujui.
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Tinjau Pengajuan</button>
            <a href="{{ route('peminjam.loans.index') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        var form = document.getElementById('loan-request-form');
        var rows = document.getElementById('rows');
        var index = {{ count($rows) }};

        document.getElementById('add-row').addEventListener('click', function () {
            var clone = rows.querySelector('.item-row').cloneNode(true);
            clone.querySelectorAll('select, input').forEach(function (el) {
                el.name = el.name.replace(/items\[\d+\]/, 'items[' + index + ']');
                if (el.tagName === 'SELECT') { el.selectedIndex = 0; } else { el.value = 1; }
            });
            index++;
            rows.appendChild(clone);
        });

        rows.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-row') && rows.querySelectorAll('.item-row').length > 1) {
                e.target.closest('.item-row').remove();
            }
        });

        form.addEventListener('submit', function () {
            var submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = 'Memeriksa...';
        });
    })();
</script>
@endpush
