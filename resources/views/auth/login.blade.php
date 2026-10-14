<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk - {{ config('app.name', 'Peminjaman Barang Kampus') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container min-vh-100 d-flex align-items-center justify-content-center py-5">
    <div class="card shadow-sm border-0 w-100" style="max-width: 440px">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="text-primary fw-bold text-uppercase small mb-2">{{ config('campus.name') }}</div>
                <h1 class="h3 mb-2">Peminjaman Barang Kampus</h1>
                <p class="text-muted mb-0">Masuk menggunakan akun kampus Anda.</p>
            </div>

            @if (session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label">Email kampus</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}"
                           class="form-control @error('email') is-invalid @enderror" required autofocus
                           autocomplete="username" maxlength="255">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Kata sandi</label>
                    <input id="password" name="password" type="password"
                           class="form-control @error('password') is-invalid @enderror" required
                           autocomplete="current-password">
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <label for="remember" class="form-check-label">
                        <input id="remember" name="remember" type="checkbox" class="form-check-input me-1">
                        Ingat saya
                    </label>
                    @if (Route::has('password.request'))
                        <a class="small text-decoration-none" href="{{ route('password.request') }}">Lupa kata sandi?</a>
                    @endif
                </div>

                <button type="submit" class="btn btn-primary w-100">Masuk</button>
            </form>

            @if (Route::has('register'))
                <p class="text-center text-muted small mt-4 mb-0">
                    Belum punya akun?
                    <a href="{{ route('register') }}" class="text-decoration-none">Daftar dengan email kampus</a>
                </p>
            @endif
        </div>
    </div>
</main>
</body>
</html>
