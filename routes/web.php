<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store')->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function (): void {
    // Kasir
    Route::get('/kasir', [AppController::class, 'pos'])->name('cashier.pos');
    Route::get('/kasir/history', [AppController::class, 'cashierHistory'])->name('cashier.history');
    Route::get('/kasir/struk/{sale}', [AppController::class, 'receipt'])->name('sales.receipt');
    Route::post('/kasir/checkout', [AppController::class, 'checkout'])->name('sales.checkout');
    Route::post('/transaksi/{sale}/void', [AppController::class, 'selfVoid'])->name('sales.self-void');

    // Barang
    Route::get('/barang', [AppController::class, 'products'])->name('products.index');
    Route::post('/barang', [AppController::class, 'storeProduct'])->name('products.store');
    Route::post('/barang/bulk', [AppController::class, 'storeBulkProducts'])->name('products.bulk-store');
    Route::put('/barang/{product}', [AppController::class, 'updateProduct'])->name('products.update');
    Route::delete('/barang/{product}', [AppController::class, 'destroyProduct'])->name('products.destroy');

    // Owner only
    Route::get('/owner/dashboard', [AppController::class, 'ownerDashboard'])->name('owner.dashboard');
    Route::get('/owner/laporan', [AppController::class, 'reports'])->name('owner.reports');
    Route::get('/owner/laporan/stok', [AppController::class, 'stockReport'])->name('owner.stock-report');
    Route::post('/owner/restock', [AppController::class, 'ownerRestock'])->name('owner.restock');
    Route::post('/owner/notifikasi/baca-semua', [AppController::class, 'markNotificationsRead'])->name('owner.notifications.read-all');
    Route::get('/owner/history', [AppController::class, 'ownerHistory'])->name('owner.history');
    Route::get('/owner/settings', [AppController::class, 'settings'])->name('owner.settings');
    Route::post('/owner/settings', [AppController::class, 'saveSettings'])->name('owner.settings.save');
    Route::get('/owner/pengguna', [AppController::class, 'users'])->name('owner.users');
    Route::post('/owner/pengguna', [AppController::class, 'storeUser'])->name('owner.users.store');
    Route::post('/owner/pengguna/{user}/password', [AppController::class, 'changeUserPassword'])->name('owner.users.password');
    Route::delete('/owner/pengguna/{user}', [AppController::class, 'destroyUser'])->name('owner.users.destroy');
});
