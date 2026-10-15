<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Nama lengkap</label>
        <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="150"
               class="form-control @error('name') is-invalid @enderror" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" maxlength="150"
               class="form-control @error('email') is-invalid @enderror" required>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="identity_number" class="form-label">NIM/NIP</label>
        <input type="text" id="identity_number" name="identity_number" value="{{ old('identity_number', $user->identity_number) }}"
               maxlength="12" pattern="(?:[0-9]{8}|[0-9]{12})" class="form-control @error('identity_number') is-invalid @enderror">
        <div class="form-text">NIM 12 digit atau NIP 8 digit angka.</div>
        @error('identity_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="phone" class="form-label">No. telepon</label>
        <input type="text" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="30"
               class="form-control @error('phone') is-invalid @enderror">
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" id="is_active" name="is_active" value="1" class="form-check-input"
                   @checked(old('is_active', $user->is_active))>
            <label for="is_active" class="form-check-label">Akun aktif</label>
        </div>
    </div>
</div>
