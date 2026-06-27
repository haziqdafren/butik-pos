# Butik POS

Laravel Blade POS untuk butik, tanpa React dan tanpa proses build Node di server.

## Akun demo

- Owner: `owner@butik.test` / `password`
- Kasir: `kasir@butik.test` / `password`

## Fitur utama

- Role kasir: transaksi POS, request diskon, input barang, pembelian/restock.
- Role owner: dashboard pendapatan, profit, stok menipis, approval diskon, laporan penjualan dan stok.
- Kategori butik: celana, rok, vest, kemeja, gaun, one set, tunik, kaos rajut, sendal, tas, gasper, sepatu, kacamata, kaos, manset.
- Diskon manual kasir wajib disetujui owner sebelum checkout.
- Checkout mengunci stok, menghitung HPP, profit, total, pembayaran, dan kembalian dari backend.

## Setup lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

## Deploy cPanel/Rumahweb

1. Upload semua file Laravel ke folder aplikasi di hosting.
2. Arahkan document root domain/subdomain ke folder `public`.
3. Buat database MySQL di cPanel.
4. Isi `.env` dari `.env.example`, terutama `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD`.
5. Jalankan:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Pastikan versi PHP hosting minimal 8.3 karena project ini memakai Laravel 13.
