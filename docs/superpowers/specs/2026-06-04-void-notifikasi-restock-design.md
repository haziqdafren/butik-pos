# Design: Void Kasir, Notifikasi Stok, Laporan Email, Restock Owner

**Tanggal:** 2026-06-04  
**Status:** Approved

---

## Ringkasan

Tiga fitur utama yang dibangun bersama:

1. **Void langsung oleh kasir** — tanpa perlu approval owner, tapi wajib alasan dan semua tercatat
2. **Notifikasi in-app + email** — owner selalu tahu ada void transaksi dan stok menipis
3. **Laporan harian email jam 20:00** — ringkasan mudah dipahami orang awam
4. **Restock hanya owner** — dengan detail supplier, jumlah, harga, catatan untuk pembukuan

---

## 1. Void Transaksi oleh Kasir

### Perilaku
- Kasir bisa void transaksi **miliknya sendiri kapan saja** dari halaman History Transaksi
- Wajib mengisi alasan (min 8 karakter, max 500 karakter)
- Stok semua item dalam transaksi **dikembalikan secara atomik** dalam satu DB transaction
- Status transaksi berubah dari `completed` → `voided`
- Void dicatat di tabel `sale_corrections` dengan: `requested_by`, `reason`, `approved_by = null`, `status = self_voided`, `approved_at = waktu void`
- Owner mendapat notifikasi in-app seketika

### Flow
```
Kasir klik "Void" pada transaksi
  → Modal: input alasan
    ├── [Alasan < 8 karakter] → Error inline
    └── [Alasan valid] → Konfirmasi dialog
          ├── [Batal] → Tidak ada perubahan
          └── [Konfirmasi]
                ├── [Transaksi bukan milik kasir] → 403
                ├── [Transaksi sudah voided] → Error flash
                └── [OK] → DB transaction:
                          stok dikembalikan semua item
                          sale.status = voided
                          sale_corrections dibuat (status: self_voided)
                          notifikasi owner dibuat
                          → Flash sukses kasir
```

### Anti-Kecurangan
- `abort_unless($sale->user_id === auth()->id(), 403)` di controller
- Void hanya bisa untuk status `completed` — tidak bisa void dua kali
- Semua void tersimpan permanen di `sale_corrections`, tidak ada DELETE
- Transaksi voided tetap muncul di laporan owner dengan tanda "VOID"
- Profit/revenue voided tidak dihitung dalam summary

---

## 2. Notifikasi In-App Owner

### Skema Tabel `notifications`
```
id, type (void_transaction | low_stock), title, body,
data (JSON: sale_id atau product_id), read_at (nullable),
created_at, updated_at
```

### Trigger Notifikasi
| Event | Notifikasi |
|---|---|
| Kasir void transaksi | `void_transaction`: "Void oleh [Nama Kasir] — INV-xxx — [alasan]" |
| Stok produk ≤ min_stock setelah checkout | `low_stock`: "Stok [Nama Produk] tinggal [N], supplier: [supplier]" |
| Restock dilakukan owner | Notifikasi low_stock produk tersebut → mark resolved (read_at diisi) |

### Deduplikasi Stok
- Sebelum buat notifikasi `low_stock`, cek: apakah sudah ada notifikasi `low_stock` untuk produk ini dengan `read_at = null`?
  - Ya → skip, tidak duplikat
  - Tidak → buat notifikasi baru

### UI Dashboard Owner
- Badge angka di navbar/header (jumlah `read_at = null`)
- Panel notifikasi: list terbaru, klik → mark read, link ke detail
- Tombol "Tandai semua dibaca"

---

## 3. Laporan Harian Email (Cron 20:00)

### Jadwal
- Setiap hari jam 20:00 WIB via Laravel Scheduler (`schedule:run`)
- Dikirim ke email owner (dari `users` where `role = owner`)

### Isi Email (awam-friendly)
```
Halo [Nama Owner],

Berikut ringkasan toko hari ini ([tanggal]):

PENJUALAN HARI INI
- Total transaksi: X kali
- Pemasukan: Rp X.XXX.XXX
- Keuntungan bersih: Rp X.XXX.XXX

PEMBATALAN HARI INI
- [Jika ada void] INV-xxx dibatalkan oleh [Kasir] — [alasan]
- [Jika nihil] Tidak ada transaksi yang dibatalkan hari ini.

BARANG STOK MENIPIS
- [Nama Produk] — sisa [N] pcs, supplier: [supplier]
- [Jika nihil] Semua stok aman.

Salam,
Sistem Butik POS
```

### Edge Cases
| Skenario | Penanganan |
|---|---|
| Tidak ada transaksi hari ini | Email tetap dikirim, isi "Tidak ada transaksi hari ini" |
| Email owner kosong/null | Log error, tidak crash aplikasi |
| Gagal kirim email | Log error + retry tidak otomatis (manual trigger opsional) |

---

## 4. Restock (Owner Only)

### Akses
- Hanya owner (`abort_unless(auth()->user()->isOwner(), 403)`)
- Form ada di halaman owner (dashboard atau halaman baru `/owner/restock`)

### Form Restock
| Field | Validasi |
|---|---|
| Produk | `required, exists:products,id` |
| Supplier | `nullable, string, max:120` |
| Jumlah | `required, integer, min:1` |
| Harga per unit | `required, integer, min:0` |
| Catatan barang | `nullable, string, max:1000` |

### Setelah Restock
- Stok produk bertambah (`product->increment('stock', qty)`)
- Harga modal produk diupdate ke `unit_cost` baru
- Disimpan ke tabel `purchases` untuk pembukuan
- Notifikasi `low_stock` produk ini otomatis di-resolve (`read_at = now()`)
- Flash sukses owner

---

## 5. Perubahan dari Sistem Lama

| Komponen Lama | Perubahan |
|---|---|
| `SaleCorrection` status `pending` → owner approve | Dihapus — void sekarang langsung oleh kasir |
| Route `POST /owner/koreksi/{id}/approve` | Dihapus |
| `pendingCorrections` di dashboard | Diganti dengan panel notifikasi |
| `SaleCorrection.status` | Nilai baru: `self_voided` (kasir void sendiri) |
| Restock di halaman `/barang` (semua user) | Dipindah ke halaman owner only |

---

## 6. Skema Database Baru

### Tabel `notifications` (baru)
```sql
id
type          VARCHAR  -- void_transaction | low_stock
title         VARCHAR
body          TEXT
data          JSON     -- { sale_id: X } atau { product_id: X }
read_at       TIMESTAMP nullable
created_at
updated_at
```

### Perubahan `sale_corrections`
- Kolom `status` tambah nilai: `self_voided` (selain `pending`, `approved`)
- `approved_by` nullable (null = void sendiri oleh kasir)

---

## 7. Security Checklist

- [x] Void: `abort_unless($sale->user_id === auth()->id(), 403)`
- [x] Restock: `abort_unless(auth()->user()->isOwner(), 403)`
- [x] Notifikasi mark-read: `abort_unless(auth()->user()->isOwner(), 403)`
- [x] Semua form pakai `$request->validate()` + `@csrf`
- [x] Tidak ada raw SQL — semua Eloquent ORM
- [x] Tidak ada route DELETE untuk transaksi/koreksi/notifikasi
- [x] DB transaction untuk void (atomik: stok + status + koreksi + notifikasi)
- [x] Cron job berjalan server-side, tidak ada HTTP endpoint publik
- [x] Audit trail void tidak bisa dihapus kasir
