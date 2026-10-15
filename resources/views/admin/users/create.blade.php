@extends('layouts.admin')

@section('title', 'Tambah Akun')
@section('heading', 'Tambah Akun')

@section('content')
    <form method="POST" action="{{ route('admin.users.store') }}" class="card card-body">
        @csrf
        <p class="text-muted">Akun baru dibuat dengan role peminjam. Tautan verifikasi email dan pengaturan password akan dikirim ke alamat email tersebut.</p>
        @include('admin.users._form')
        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </form>
@endsection
