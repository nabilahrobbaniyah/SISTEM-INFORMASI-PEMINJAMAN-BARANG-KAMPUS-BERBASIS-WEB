@extends('layouts.admin')

@section('title', 'Audit Log')
@section('heading', 'Audit Log')

@section('content')
    <form method="GET" action="{{ route('admin.audit.index') }}" class="card card-body mb-3">
        <div class="row g-2">
            <div class="col-md-6">
                <input type="text" name="q" value="{{ request('q') }}" class="form-control"
                       placeholder="Cari deskripsi atau nama pengguna">
            </div>
            <div class="col-md-3">
                <select name="action" class="form-select">
                    <option value="">Semua aksi</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(request('action') === $action)>{{ ucfirst($action) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-secondary flex-fill">Cari</button>
                <a href="{{ route('admin.audit.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead><tr><th>Waktu</th><th>Pelaku</th><th>Target</th><th>Aksi</th><th>Keterangan</th><th>Perubahan</th><th>Alasan / hasil</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $log->user?->name ?? '-' }}</td>
                        <td>{{ $log->targetUser?->name ?? '-' }}</td>
                        <td><span class="badge bg-secondary">{{ $log->action }}</span></td>
                        <td>{{ $log->description }}</td>
                        <td>{{ $log->old_value && $log->new_value ? "{$log->old_value} -> {$log->new_value}" : '-' }}</td>
                        <td>
                            {{ $log->reason ?: '-' }}
                            @if ($log->result)
                                <span class="badge bg-{{ $log->result === 'berhasil' ? 'success' : 'danger' }}">{{ $log->result }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada catatan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $logs->links('pagination::bootstrap-5') }}</div>
@endsection
