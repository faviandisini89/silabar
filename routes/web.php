<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Borrower;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LoanDocumentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Staff;
use App\Http\Middleware\EnsureRole;
use Illuminate\Support\Facades\Route;

/* ---------- public / landing ---------- */
Route::get('/', [LandingController::class, 'index'])->name('root');

/* ---------- guests ---------- */
Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/masuk', [AuthController::class, 'login'])->middleware('throttle:8,1')->name('login.post');
    Route::get('/daftar', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/daftar', [AuthController::class, 'register'])->middleware('throttle:6,1')->name('register.post');
});

/* ---------- any signed-in user ---------- */
Route::middleware('auth')->group(function () {
    Route::post('/keluar', [AuthController::class, 'logout'])->name('logout');

    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifikasi/baca', [NotificationController::class, 'readAll'])->name('notifications.read');
    Route::get('/notifikasi/{id}', [NotificationController::class, 'open'])->name('notifications.open');

    Route::get('/tiket/{loan}/berita-acara', [LoanDocumentController::class, 'show'])->name('loans.doc');
});

/* ---------- borrower ---------- */
Route::middleware(['auth', EnsureRole::class.':peminjam'])->group(function () {
    Route::get('/beranda', [Borrower\DashboardController::class, 'index'])->name('home');
    Route::get('/katalog', [Borrower\CatalogController::class, 'index'])->name('catalog');
    Route::get('/katalog/{item}/kalender', [Borrower\CatalogController::class, 'calendar'])->name('catalog.calendar');

    Route::post('/keranjang', [Borrower\CartController::class, 'add'])->name('cart.add');
    Route::patch('/keranjang/{item}', [Borrower\CartController::class, 'update'])->name('cart.update');
    Route::delete('/keranjang/{item}', [Borrower\CartController::class, 'remove'])->name('cart.remove');
    Route::post('/keranjang/jadwal', [Borrower\CartController::class, 'window'])->name('cart.window');
    Route::post('/keranjang/paket', [Borrower\CartController::class, 'bundle'])->name('cart.bundle');
    Route::post('/ajukan', [Borrower\LoanRequestController::class, 'store'])->name('loans.store');

    Route::get('/tiket', [Borrower\MyLoanController::class, 'index'])->name('loans.index');
    Route::post('/tiket/{loan}/batal', [Borrower\MyLoanController::class, 'cancel'])->name('loans.cancel');
    Route::post('/tiket/{loan}/perpanjang', [Borrower\MyLoanController::class, 'extend'])->name('loans.extend');
    Route::get('/riwayat', [Borrower\HistoryController::class, 'index'])->name('history');
});

/* ---------- staff (Petugas) and Admin ---------- */
Route::middleware(['auth', EnsureRole::class.':petugas,admin'])->prefix('petugas')->name('staff.')->group(function () {
    Route::get('/agenda', [Staff\AgendaController::class, 'index'])->name('agenda');

    Route::get('/persetujuan', [Staff\ApprovalController::class, 'index'])->name('approvals');
    Route::post('/persetujuan/{loan}/setujui', [Staff\ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/persetujuan/{loan}/tolak', [Staff\ApprovalController::class, 'reject'])->name('approvals.reject');
    Route::post('/perpanjangan/{extension}', [Staff\ApprovalController::class, 'decideExtension'])->name('extensions.decide');

    Route::get('/loket', [Staff\CounterController::class, 'index'])->name('counter');
    Route::post('/loket/{loan}/serah', [Staff\CounterController::class, 'handover'])->name('counter.handover');
    Route::post('/loket/{loan}/kembali', [Staff\CounterController::class, 'receive'])->name('counter.return');

    Route::get('/barang', [Staff\InventoryController::class, 'index'])->name('inventory');
    Route::post('/barang', [Staff\InventoryController::class, 'store'])->middleware(EnsureRole::class.':admin')->name('inventory.store');
    Route::get('/barang/{item}', [Staff\InventoryController::class, 'show'])->name('inventory.show');
    Route::put('/barang/{item}', [Staff\InventoryController::class, 'update'])->middleware(EnsureRole::class.':admin')->name('inventory.update');
    Route::post('/barang/{item}/unit', [Staff\InventoryController::class, 'addUnits'])->middleware(EnsureRole::class.':admin')->name('inventory.units');
    Route::get('/barang/{item}/label', [Staff\InventoryController::class, 'labels'])->name('inventory.labels');
    Route::post('/unit/{unit}/rusak', [Staff\InventoryController::class, 'repair'])->name('units.repair');

    Route::get('/perawatan', [Staff\MaintenanceController::class, 'index'])->name('maint');
    Route::post('/perawatan', [Staff\MaintenanceController::class, 'store'])->name('maint.store');
    Route::post('/perawatan/{log}/selesai', [Staff\MaintenanceController::class, 'finish'])->name('maint.finish');

    Route::get('/denda', [Staff\PenaltyController::class, 'index'])->name('penalties');
    Route::post('/denda/{penalty}/lunas', [Staff\PenaltyController::class, 'paid'])->name('penalties.paid');
    Route::post('/denda/{penalty}/hapus', [Staff\PenaltyController::class, 'waive'])->name('penalties.waive');

    Route::get('/laporan', [Staff\ReportController::class, 'index'])->name('reports');
    Route::get('/laporan/harian', [Staff\ReportController::class, 'dailyPrint'])->name('reports.daily');
    Route::get('/laporan/csv/{kind}', [Staff\ReportController::class, 'csv'])->name('reports.csv');
});

/* ---------- Admin only ---------- */
Route::middleware(['auth', EnsureRole::class.':admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/analitik', [Admin\AnalyticsController::class, 'index'])->name('analytics');
    Route::post('/analitik/jalankan', [Admin\AnalyticsController::class, 'run'])->name('analytics.run');

    Route::get('/pengadaan', [Admin\ProcurementController::class, 'index'])->name('procure');
    Route::post('/pengadaan/jalankan', [Admin\ProcurementController::class, 'run'])->name('procure.run');
    Route::post('/pengadaan/{rec}/putuskan', [Admin\ProcurementController::class, 'decide'])->name('procure.decide');
    Route::post('/pengadaan/{rec}/catat', [Admin\ProcurementController::class, 'fulfill'])->name('procure.fulfill');

    Route::get('/pengguna', [Admin\UserController::class, 'index'])->name('users');
    Route::post('/pengguna', [Admin\UserController::class, 'store'])->name('users.store');
    Route::patch('/pengguna/{user}', [Admin\UserController::class, 'update'])->name('users.update');

    Route::get('/pengaturan', [Admin\SettingController::class, 'index'])->name('settings');
    Route::post('/pengaturan', [Admin\SettingController::class, 'update'])->name('settings.update');
    Route::post('/pengaturan/master', [Admin\SettingController::class, 'master'])->name('settings.master');

    Route::get('/audit', [Admin\AuditController::class, 'index'])->name('audit');
    Route::post('/impor-barang', [Staff\ReportController::class, 'import'])->name('import');
});
