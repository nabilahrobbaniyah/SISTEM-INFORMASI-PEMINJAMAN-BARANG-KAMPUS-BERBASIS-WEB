@extends('layouts.admin')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
    <p class="text-muted">Halo, {{ auth()->user()->name }}. Anda masuk sebagai {{ auth()->user()->role }}.</p>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <a href="{{ route('admin.loans.index', ['status' => 'menunggu']) }}" class="text-decoration-none">
                <div class="card h-100 border-warning"><div class="card-body">
                    <div class="text-muted small">Menunggu persetujuan</div>
                    <div class="display-6 text-body">{{ $waitingLoans }}</div>
                </div></div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('admin.loans.index', ['status' => 'disetujui']) }}" class="text-decoration-none">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">Siap diserahkan</div>
                    <div class="display-6 text-body">{{ $approvedLoans }}</div>
                </div></div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('admin.loans.index', ['status' => 'dipinjam']) }}" class="text-decoration-none">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">Sedang dipinjam</div>
                    <div class="display-6 text-body">{{ $borrowedLoans }}</div>
                </div></div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('admin.loans.index', ['status' => 'terlambat']) }}" class="text-decoration-none">
                <div class="card h-100 {{ $overdueLoans > 0 ? 'border-danger' : '' }}"><div class="card-body">
                    <div class="text-muted small">Terlambat dikembalikan</div>
                    <div class="display-6 text-body">{{ $overdueLoans }}</div>
                </div></div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">Unit Siap Dipinjam</div>
                <div class="display-6">{{ $availableUnits }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('admin.loans.index', ['status' => 'dipinjam']) }}" class="text-decoration-none">
                <div class="card h-100 {{ $dueTodayCount > 0 ? 'border-warning' : '' }}"><div class="card-body">
                    <div class="text-muted small">Jatuh tempo hari ini</div>
                    <div class="display-6 text-body">{{ $dueTodayCount }}</div>
                </div></div>
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Tren peminjaman dan keterlambatan (6 bulan)</div>
                <div class="card-body"><canvas id="loanTrendChart" role="img" aria-label="Grafik peminjaman dan keterlambatan"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Barang paling sering dipinjam</div>
                <div class="card-body">
                    @if (count($chartData['popularItems']))
                        <canvas id="popularItemsChart" role="img" aria-label="Grafik barang paling sering dipinjam"></canvas>
                    @else
                        <p class="text-muted mb-0">Belum ada data serah-terima peminjaman.</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Distribusi status peminjaman</div>
                <div class="card-body" style="height: 250px;"><canvas id="loanStatusChart" role="img" aria-label="Grafik distribusi status peminjaman"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Pengajuan yang menunggu</span>
                    <a href="{{ route('admin.loans.index') }}" class="btn btn-sm btn-outline-secondary">Semua peminjaman</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                        <tr><th>Kode</th><th>Peminjam</th><th>Periode</th><th class="text-center">Barang</th><th class="text-end">Aksi</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($pendingLoans as $loan)
                            <tr>
                                <td>{{ $loan->loan_code }}</td>
                                <td>{{ $loan->user->name }}</td>
                                <td>{{ $loan->loan_date->format('d/m/Y') }} - {{ $loan->due_date->format('d/m/Y') }}</td>
                                <td class="text-center">{{ $loan->details->count() }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.loans.show', $loan) }}" class="btn btn-sm btn-primary">Proses</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">Tidak ada pengajuan yang menunggu.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">
                    <span>Serah-terima terjadwal hari ini</span>
                    <span class="badge bg-info">{{ $handoverTodayCount }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead><tr><th>Kode</th><th>Peminjam</th><th>Barang</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($handoverToday as $loan)
                            <tr>
                                <td>{{ $loan->loan_code }}</td>
                                <td>{{ $loan->user->name }}</td>
                                <td>{{ $loan->details->count() }}</td>
                                <td class="text-end"><a href="{{ route('admin.loans.show', $loan) }}" class="btn btn-sm btn-outline-primary">Proses</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada serah-terima terjadwal hari ini.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100 {{ $overdueLoans > 0 ? 'border-danger' : '' }}">
                <div class="card-header">Pengembalian terlambat</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead><tr><th>Kode</th><th>Peminjam</th><th>Jatuh tempo</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($overdueLoanRecords as $loan)
                            <tr>
                                <td>{{ $loan->loan_code }}</td>
                                <td>{{ $loan->user->name }}</td>
                                <td>{{ $loan->due_date->format('d/m/Y') }}</td>
                                <td class="text-end"><a href="{{ route('admin.loans.show', $loan) }}" class="btn btn-sm btn-outline-danger">Detail</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada pengembalian yang terlambat.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Barang terbaru</span>
            <a href="{{ route('admin.items.index') }}" class="btn btn-sm btn-outline-secondary">Lihat semua</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                <tr><th>Kode</th><th>Nama</th><th>Kategori</th><th class="text-end">Di tempat</th></tr>
                </thead>
                <tbody>
                @forelse ($latestItems as $item)
                    <tr>
                        <td>{{ $item->item_code }}</td>
                        <td><a href="{{ route('admin.items.show', $item) }}">{{ $item->name }}</a></td>
                        <td>{{ $item->category->name }}</td>
                        <td class="text-end">{{ $item->available_quantity }} / {{ $item->total_quantity }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">Belum ada data barang.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        const chartData = @js($chartData);
        const statusColors = ['#f0ad4e', '#0dcaf0', '#dc3545', '#0d6efd', '#198754', '#6c757d'];

        new Chart(document.getElementById('loanTrendChart'), {
            type: 'line',
            data: {
                labels: chartData.months,
                datasets: [
                    { label: 'Peminjaman', data: chartData.loans, borderColor: '#0d6efd', backgroundColor: '#0d6efd22', fill: true, tension: 0.25 },
                    { label: 'Terlambat', data: chartData.overdue, borderColor: '#dc3545', backgroundColor: '#dc354522', fill: true, tension: 0.25 },
                ],
            },
            options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
        });

        if (document.getElementById('popularItemsChart')) {
            new Chart(document.getElementById('popularItemsChart'), {
                type: 'bar',
                data: {
                    labels: chartData.popularItems.map((item) => item.name),
                    datasets: [{ label: 'Unit dipinjam', data: chartData.popularItems.map((item) => item.quantity), backgroundColor: '#0d6efd' }],
                },
                options: { indexAxis: 'y', responsive: true, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } },
            });
        }

        new Chart(document.getElementById('loanStatusChart'), {
            type: 'doughnut',
            data: {
                labels: chartData.statuses.map((status) => status.label),
                datasets: [{ data: chartData.statuses.map((status) => status.total), backgroundColor: statusColors }],
            },
            options: {responsive: true, maintainAspectRatio: false, plugins: { legend: {position: 'right'} } },
        });
    </script>
@endpush
