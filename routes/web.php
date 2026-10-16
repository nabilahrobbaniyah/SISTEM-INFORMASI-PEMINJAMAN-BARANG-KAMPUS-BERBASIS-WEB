<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ItemController;
use App\Http\Controllers\Admin\LoanController as AdminLoanController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\InventoryEvidenceController;
use App\Http\Controllers\LoanPrintController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Peminjam\CatalogController;
use App\Http\Controllers\Peminjam\DashboardController as BorrowerDashboardController;
use App\Http\Controllers\Peminjam\LoanController as BorrowerLoanController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Halaman /dashboard bawaan Breeze diarahkan sesuai peran.
Route::get('/dashboard', function () {
    return auth()->user()->isStaff()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('peminjam.dashboard');
})->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Notifikasi dalam aplikasi
    Route::get('/notifikasi', [NotificationController::class, 'index'])->middleware('verified')->name('notifications.index');
    Route::get('/notifikasi/{notification}/buka', [NotificationController::class, 'open'])->middleware('verified')->name('notifications.open');
    Route::post('/notifikasi/baca-semua', [NotificationController::class, 'readAll'])->middleware('verified')->name('notifications.read-all');

    // Asisten ketersediaan barang
    Route::get('/asisten', [AssistantController::class, 'index'])->middleware('verified')->name('assistant.index');
    Route::post('/asisten/tanya', [AssistantController::class, 'ask'])->middleware(['verified', 'throttle:10,1'])->name('assistant.ask');
    Route::post('/asisten/hapus', [AssistantController::class, 'clear'])->middleware('verified')->name('assistant.clear');

    // Bukti peminjaman (peminjam pemilik atau petugas)
    Route::get('/peminjaman/{loan}/cetak', [LoanPrintController::class, 'show'])->middleware('verified')->name('loans.print');
    Route::get('/bukti-pengembalian/{loanDetail}', [InventoryEvidenceController::class, 'loanReturn'])
        ->middleware('verified')
        ->name('loans.evidence');
});

// Area peminjam
Route::middleware(['auth', 'verified', 'role:peminjam'])->prefix('peminjam')->name('peminjam.')->group(function () {
    Route::get('/dashboard', [BorrowerDashboardController::class, 'index'])->name('dashboard');
    Route::get('/katalog', [CatalogController::class, 'index'])->name('catalog');

    Route::get('/peminjaman', [BorrowerLoanController::class, 'index'])->name('loans.index');
    Route::get('/peminjaman/buat', [BorrowerLoanController::class, 'create'])->name('loans.create');
    Route::post('/peminjaman/tinjau', [BorrowerLoanController::class, 'review'])->middleware('throttle:10,1')->name('loans.review');
    Route::get('/peminjaman/konfirmasi', [BorrowerLoanController::class, 'confirmation'])->name('loans.confirmation');
    Route::post('/peminjaman', [BorrowerLoanController::class, 'store'])->name('loans.store');
    Route::get('/peminjaman/{loan}', [BorrowerLoanController::class, 'show'])->name('loans.show');
    Route::post('/peminjaman/{loan}/batal', [BorrowerLoanController::class, 'cancel'])->name('loans.cancel');
});

// Area admin dan petugas
Route::middleware(['auth', 'verified', 'role:admin,petugas'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('barang/{item}/riwayat/{event}/bukti', [InventoryEvidenceController::class, 'inventory'])
        ->name('items.evidence');

    Route::resource('kategori', CategoryController::class)
        ->parameters(['kategori' => 'category'])
        ->names('categories')
        ->except(['show']);

    Route::get('barang', [ItemController::class, 'index'])->name('items.index');
    Route::get('barang/tambah', [ItemController::class, 'create'])
        ->middleware('role:admin')
        ->name('items.create');
    Route::get('barang/{item}/inventaris', [ItemController::class, 'inventory'])->name('items.inventory');
    Route::get('barang/{item}', [ItemController::class, 'show'])->name('items.show');
    Route::post('barang/{item}/ditemukan', [ItemController::class, 'reportFound'])->name('items.found');
    Route::post('barang/{item}/laporkan-kerusakan', [ItemController::class, 'reportDamage'])->name('items.report-damage');
    Route::post('barang/{item}/periksa-temuan', [ItemController::class, 'inspectFound'])->name('items.inspect-found');
    Route::post('barang/{item}/mulai-perbaikan', [ItemController::class, 'startRepair'])->name('items.repair-start');
    Route::post('barang/{item}/selesai-perbaikan', [ItemController::class, 'completeRepair'])->name('items.repair-complete');
    Route::post('barang/{item}/tindak-lanjut-rusak-berat', [ItemController::class, 'resolveHeavyDamage'])
        ->name('items.heavy-damage-follow-up');

    // Alur peminjaman
    Route::get('peminjaman', [AdminLoanController::class, 'index'])->name('loans.index');
    Route::get('peminjaman/{loan}', [AdminLoanController::class, 'show'])->name('loans.show');
    Route::post('peminjaman/{loan}/setujui', [AdminLoanController::class, 'approve'])->name('loans.approve');
    Route::post('peminjaman/{loan}/tolak', [AdminLoanController::class, 'reject'])->name('loans.reject');
    Route::post('peminjaman/{loan}/serah-terima', [AdminLoanController::class, 'handover'])->name('loans.handover');
    Route::post('peminjaman/{loan}/pengembalian', [AdminLoanController::class, 'returnItems'])->name('loans.return');

    // Laporan
    Route::get('laporan', [ReportController::class, 'index'])->name('reports.index');
    Route::get('laporan/cetak', [ReportController::class, 'print'])->name('reports.print');
    Route::get('laporan/csv', [ReportController::class, 'csv'])->name('reports.csv');

    // Khusus admin
    Route::middleware('role:admin')->group(function () {
        Route::post('barang', [ItemController::class, 'store'])->name('items.store');
        Route::get('barang/{item}/ubah', [ItemController::class, 'edit'])->name('items.edit');
        Route::put('barang/{item}', [ItemController::class, 'update'])->name('items.update');
        Route::patch('barang/{item}/status', [ItemController::class, 'toggle'])->name('items.toggle');
        Route::post('barang/{item}/koreksi-stok', [ItemController::class, 'adjustStock'])->name('items.adjust-stock');
        Route::post('barang/{item}/usulan-penghapusan/{proposal}', [ItemController::class, 'reviewHeavyDamageDisposal'])
            ->name('items.disposal-proposals.review');

        Route::resource('pengguna', UserController::class)
            ->parameters(['pengguna' => 'user'])
            ->names('users')
            ->except(['show', 'destroy']);

        Route::patch('pengguna/{user}/status', [UserController::class, 'toggle'])->name('users.toggle');
        Route::patch('pengguna/{user}/role', [UserController::class, 'updateRole'])
            ->middleware('throttle:5,1')
            ->name('users.role');

        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit.index');
    });
});

require __DIR__.'/auth.php';
