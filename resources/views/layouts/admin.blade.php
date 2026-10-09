@php
    $authUser = auth()->user();
    $isStaff = $authUser->isStaff();
    $unread = $authUser->unreadNotificationCount();
    $pendingCount = $isStaff ? \App\Models\Loan::where('status', 'menunggu')->count() : 0;
    $home = $isStaff ? route('admin.dashboard') : route('peminjam.dashboard');
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - Peminjaman Barang Kampus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="{{ $home }}">Peminjaman Barang Kampus</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuUtama"
                aria-controls="menuUtama" aria-expanded="false" aria-label="Buka menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menuUtama">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                @if ($isStaff)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                           href="{{ route('admin.dashboard') }}">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.loans.*') ? 'active' : '' }}"
                           href="{{ route('admin.loans.index') }}">
                            Peminjaman
                            @if ($pendingCount > 0)
                                <span class="badge bg-warning text-dark">{{ $pendingCount }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}"
                           href="{{ route('admin.categories.index') }}">Kategori Barang</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.items.*') ? 'active' : '' }}"
                           href="{{ route('admin.items.index') }}">Data Barang</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}"
                           href="{{ route('admin.reports.index') }}">Laporan</a>
                    </li>
                    @if ($authUser->isAdmin())
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('admin.users.*', 'admin.audit.*') ? 'active' : '' }}"
                               href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Pengelolaan</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('admin.users.index') }}">Pengguna</a></li>
                                <li><a class="dropdown-item" href="{{ route('admin.audit.index') }}">Audit Log</a></li>
                            </ul>
                        </li>
                    @endif
                @else
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('peminjam.dashboard') ? 'active' : '' }}"
                           href="{{ route('peminjam.dashboard') }}">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('peminjam.catalog') ? 'active' : '' }}"
                           href="{{ route('peminjam.catalog') }}">Katalog Barang</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('peminjam.loans.index', 'peminjam.loans.show') ? 'active' : '' }}"
                           href="{{ route('peminjam.loans.index') }}">Peminjaman Saya</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('peminjam.loans.create') ? 'active' : '' }}"
                           href="{{ route('peminjam.loans.create') }}">Ajukan Peminjaman</a>
                    </li>
                @endif
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('assistant.*') ? 'active' : '' }}"
                       href="{{ route('assistant.index') }}">Asisten AI</a>
                </li>
            </ul>

            <a href="{{ route('notifications.index') }}" class="btn btn-outline-light btn-sm me-2 position-relative">
                Notifikasi
                @if ($unread > 0)
                    <span class="badge bg-danger">{{ $unread }}</span>
                @endif
            </a>

            <div class="dropdown">
                <a class="btn btn-outline-light btn-sm dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                   aria-expanded="false">
                    {{ $authUser->name }} ({{ ucfirst($authUser->role) }})
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Profil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">Keluar</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<main class="container py-4">
    <h1 class="h3 mb-3">@yield('heading')</h1>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif

    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
