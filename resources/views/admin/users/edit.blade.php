@extends('layouts.admin')

@section('title', 'Ubah Akun')
@section('heading', 'Ubah Akun')

@section('content')
    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="card card-body">
        @csrf
        @method('PUT')
        @include('admin.users._form')
        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </form>

    @if ($user->id !== auth()->id())
        <section class="card card-body mt-4">
            <h2 class="h5">Perubahan role dengan konfirmasi keamanan</h2>
            <p>
                Target: <strong>{{ $user->name }} ({{ $user->email }})</strong>.
                Role lama: <strong>{{ ucfirst($user->role) }}</strong>.
                Role baru: <strong id="new-role-label">{{ ucfirst(old('role', $user->role)) }}</strong>.
            </p>
            <form id="role-change-form" method="POST" action="{{ route('admin.users.role', $user) }}">
                @csrf
                @method('PATCH')
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="role" class="form-label">Role baru</label>
                        <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
                            @foreach ($roles as $role)
                                <option value="{{ $role }}" @selected(old('role', $user->role) === $role)>{{ ucfirst($role) }}</option>
                            @endforeach
                        </select>
                        @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label for="reason" class="form-label">Alasan perubahan</label>
                        <input id="reason" name="reason" value="{{ old('reason') }}" class="form-control @error('reason') is-invalid @enderror"
                               minlength="5" maxlength="500" required>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="current_password" class="form-label">Password admin untuk autentikasi ulang</label>
                        <input id="current_password" name="current_password" type="password" autocomplete="current-password"
                               class="form-control @error('current_password') is-invalid @enderror" required>
                        @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <button type="submit" class="btn btn-warning mt-3">Konfirmasi perubahan role</button>
            </form>
        </section>
        <script>
            const roleSelect = document.getElementById('role');
            const roleLabel = document.getElementById('new-role-label');
            roleSelect.addEventListener('change', () => {
                roleLabel.textContent = roleSelect.options[roleSelect.selectedIndex].text;
            });
            document.getElementById('role-change-form').addEventListener('submit', (event) => {
                const oldRole = @js(ucfirst($user->role));
                const newRole = roleSelect.options[roleSelect.selectedIndex].text;
                const reason = document.getElementById('reason').value;
                const targetName = @js($user->name);
                if (!confirm(`Ubah role ${targetName} dari ${oldRole} menjadi ${newRole}?\nAlasan: ${reason}`)) {
                    event.preventDefault();
                }
            });
        </script>
    @endif
@endsection
