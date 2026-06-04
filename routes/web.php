<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function (): void {
    // Kasir
    Route::get('/kasir', [AppController::class, 'pos'])->name('cashier.pos');
    Route::get('/kasir/history', [AppController::class, 'cashierHistory'])->name('cashier.history');
    Route::post('/kasir/checkout', [AppController::class, 'checkout'])->name('sales.checkout');
    Route::post('/transaksi/{sale}/void', [AppController::class, 'selfVoid'])->name('sales.self-void');

    // Barang
    Route::get('/barang', [AppController::class, 'products'])->name('products.index');
    Route::post('/barang', [AppController::class, 'storeProduct'])->name('products.store');

    // Owner only
    Route::get('/owner/dashboard', [AppController::class, 'ownerDashboard'])->name('owner.dashboard');
    Route::get('/owner/laporan', [AppController::class, 'reports'])->name('owner.reports');
    Route::post('/owner/restock', [AppController::class, 'ownerRestock'])->name('owner.restock');
    Route::post('/owner/notifikasi/baca-semua', [AppController::class, 'markNotificationsRead'])->name('owner.notifications.read-all');
});
