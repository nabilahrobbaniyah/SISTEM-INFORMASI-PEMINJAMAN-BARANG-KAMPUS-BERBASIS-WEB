<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ItemController;
use App\Http\Controllers\Admin\LoanController as AdminLoanController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\LoanPrintController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Peminjam\CatalogController;
use App\Http\Controllers\Peminjam\DashboardController as BorrowerDashboardController;
use App\Http\Controllers\Peminjam\LoanController as BorrowerLoanController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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
    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifikasi/{notification}/buka', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    // Asisten ketersediaan barang
    Route::get('/asisten', [AssistantController::class, 'index'])->name('assistant.index');
    Route::post('/asisten/tanya', [AssistantController::class, 'ask'])->middleware('throttle:20,1')->name('assistant.ask');
    Route::post('/asisten/hapus', [AssistantController::class, 'clear'])->name('assistant.clear');

    // Bukti peminjaman (peminjam pemilik atau petugas)
    Route::get('/peminjaman/{loan}/cetak', [LoanPrintController::class, 'show'])->name('loans.print');
});

// Area peminjam
Route::middleware(['auth', 'role:peminjam'])->prefix('peminjam')->name('peminjam.')->group(function () {
    Route::get('/dashboard', [BorrowerDashboardController::class, 'index'])->name('dashboard');
    Route::get('/katalog', [CatalogController::class, 'index'])->name('catalog');

    Route::get('/peminjaman', [BorrowerLoanController::class, 'index'])->name('loans.index');
    Route::get('/peminjaman/buat', [BorrowerLoanController::class, 'create'])->name('loans.create');
    Route::post('/peminjaman', [BorrowerLoanController::class, 'store'])->name('loans.store');
    Route::get('/peminjaman/{loan}', [BorrowerLoanController::class, 'show'])->name('loans.show');
    Route::post('/peminjaman/{loan}/batal', [BorrowerLoanController::class, 'cancel'])->name('loans.cancel');
});

// Area admin dan petugas
Route::middleware(['auth', 'role:admin,petugas'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('kategori', CategoryController::class)
        ->parameters(['kategori' => 'category'])
        ->names('categories')
        ->except(['show']);

    Route::resource('barang', ItemController::class)
        ->parameters(['barang' => 'item'])
        ->names('items')
        ->except(['destroy']);

    Route::patch('barang/{item}/status', [ItemController::class, 'toggle'])->name('items.toggle');

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
        Route::resource('pengguna', UserController::class)
            ->parameters(['pengguna' => 'user'])
            ->names('users')
            ->except(['show', 'destroy']);

        Route::patch('pengguna/{user}/status', [UserController::class, 'toggle'])->name('users.toggle');

        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit.index');
    });
});

require __DIR__.'/auth.php';
