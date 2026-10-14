@php($isEdit = $item->exists)

<div class="row g-3">
    <div class="col-md-4">
        <label for="item_code" class="form-label">Kode barang</label>
        <input type="text" id="item_code" name="item_code" value="{{ old('item_code', $item->item_code) }}"
               class="form-control @error('item_code') is-invalid @enderror" required maxlength="50">
        @error('item_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-8">
        <label for="name" class="form-label">Nama barang</label>
        <input type="text" id="name" name="name" value="{{ old('name', $item->name) }}"
               class="form-control @error('name') is-invalid @enderror" required maxlength="150">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="category_id" class="form-label">Kategori</label>
        <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
            <option value="">Pilih kategori</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id', $item->category_id) == $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
        @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label for="condition" class="form-label">Kondisi</label>
        <select id="condition" name="condition" class="form-select @error('condition') is-invalid @enderror" required>
            @foreach ($conditions as $kondisi)
                <option value="{{ $kondisi }}" @selected(old('condition', $item->condition ?? 'baik') === $kondisi)>
                    {{ ucfirst($kondisi) }}
                </option>
            @endforeach
        </select>
        @error('condition')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="total_quantity" class="form-label">Jumlah total layak pinjam</label>
        <input type="number" id="total_quantity" name="total_quantity" min="{{ $isEdit ? 0 : 1 }}"
               value="{{ old('total_quantity', $item->total_quantity) }}"
               class="form-control @error('total_quantity') is-invalid @enderror" required>
        @error('total_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Stok di tempat dihitung otomatis. Stok total tidak dapat dikurangi selama ada peminjaman aktif atau disetujui.</div>
    </div>

    <div class="col-12">
        <label for="location" class="form-label">Lokasi penyimpanan</label>
        <input type="text" id="location" name="location" value="{{ old('location', $item->location) }}"
               class="form-control @error('location') is-invalid @enderror" required maxlength="150">
        @error('location')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Deskripsi</label>
        <textarea id="description" name="description" rows="3" maxlength="1000"
                  class="form-control @error('description') is-invalid @enderror">{{ old('description', $item->description) }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="image" class="form-label">Foto barang (opsional, maks 2 MB)</label>
        <input type="file" id="image" name="image" accept="image/*"
               class="form-control @error('image') is-invalid @enderror">
        @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @if ($isEdit && $item->image)
            <img src="{{ asset('storage/'.$item->image) }}" alt="Foto {{ $item->name }}" class="img-thumbnail mt-2" style="max-height: 120px;">
        @endif
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('admin.items.index') }}" class="btn btn-outline-secondary">Batal</a>
</div>
