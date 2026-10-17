# Design — Portal Pernyataan Benturan Kepentingan

**Kode formulir:** FI/SP/GRC/973 — Rev. 01
**Acuan:** `design.md` di root workspace (dokumen produk lengkap).

Dokumen ini adalah desain teknis implementasi yang sudah menyesuaikan keputusan terkonfirmasi: PostgreSQL (Laragon), PDF via Dompdf (template HTML/CSS A4), OTP dev via mail `log`/Mailpit, dan role admin minimal.

## 1. Arsitektur

- Laravel 11 (PHP 8.4), pola MVC + Form Request + Service.
- Blade + Tailwind CSS (via Vite) untuk UI; Alpine.js untuk interaksi form (field kondisional Bagian B/C, baris rincian dinamis).
- PostgreSQL sebagai datastore. Semua constraint penting di level DB.
- Dompdf (`barryvdh/laravel-dompdf`) untuk render Blade A4 → PDF.
- Penyimpanan PDF: disk privat `storage/app/private/declarations`.

### Lapisan

```
Routes (web.php)
  -> Controllers (thin)
       -> Form Requests (validasi server)
       -> Services (OtpService, DeclarationService, PdfService, AuditService)
            -> Eloquent Models + DB transaction
  -> Policies (DeclarationPolicy) untuk otorisasi
```

## 2. Konfigurasi lingkungan

- `.env`: `DB_CONNECTION=pgsql`, host `127.0.0.1`, port `5432`, kredensial Laragon.
- `APP_TIMEZONE=Asia/Jakarta`.
- `MAIL_MAILER=log` (dev) atau Mailpit; SMTP produksi diisi belakangan.
- PostgreSQL Laragon di `D:\laragon\bin\postgresql` (tambahkan `bin` ke PATH atau gunakan HeidiSQL untuk inspeksi).

## 3. Model data & migration

Sesuai requirements. Tabel:

- `users`: `id, email (unique, lowercase), nik (varchar16 unique), password, email_verified_at, role (default employee), is_active (bool default true), timestamps`.
- `registration_otps`: `id, email (index), code_hash, expires_at, attempt_count (default 0), consumed_at (nullable), last_sent_at, ip_address (nullable), timestamps`.
- `employee_profiles`: `id, user_id (unique FK), full_name, position, work_unit, timestamps`.
- `conflict_declarations`: `id, user_id (FK), employee_name, employee_nik, employee_position, employee_work_unit, declaration_status (enum none|has_conflict), approved_label, submitted_at, pdf_path (nullable), timestamps`.
- `conflict_declaration_categories`: `id, declaration_id (FK), category_key, unique(declaration_id, category_key)`.
- `conflict_declaration_details`: `id, declaration_id (FK), category_key, related_party_name, situation_explanation, timestamps`.
- `audit_logs`: `id, user_id (nullable FK), event, target_type, target_id (nullable), created_at`.

Index: FK columns, `users.email`, `users.nik`, `registration_otps.email`.

Kategori tetap (enum logis di aplikasi): `family_relationship`, `multiple_positions`, `hr_process`, `procurement_finance`, `financial_interest` dengan label Indonesia pada config/lang.

## 4. Services

### OtpService
- `send(email, ip)`: validasi domain `@sig.id`, rate limit (5/jam/email + per IP), jeda 60 detik, invalidasi kode lama, buat OTP 6 digit via `random_int`, simpan `hash('sha256', code)` (atau `Hash::make`), kirim mail `OtpMail`.
- `verify(email, code)`: cek record aktif, cek expired/consumed/attempt<=5, bandingkan hash; naikkan `attempt_count` saat gagal; set `consumed_at` saat berhasil.
- Tidak pernah log kode.

### DeclarationService
- `store(user, dto)`: dalam `DB::transaction` — ambil profil dari DB untuk snapshot, buat declaration, kategori, rincian; validasi konsistensi status↔kategori↔rincian; tulis audit.

### PdfService
- `generate(declaration)`: render Blade `pdf.declaration` → Dompdf → simpan ke disk privat `declarations/{id}.pdf`, set `pdf_path`. Idempoten (boleh regenerate). Tidak menghapus data bila gagal.

### AuditService
- `log(event, target)`: tulis `audit_logs` tanpa data sensitif.

## 5. Rute (web.php)

| Method | Rute | Controller@aksi | Akses |
|---|---|---|---|
| GET | `/login` | AuthController@showLogin | guest |
| POST | `/login` | AuthController@login | guest, throttle |
| GET | `/register` | RegisterController@show | guest |
| POST | `/register/send-otp` | RegisterController@sendOtp | guest, throttle |
| POST | `/register/verify-otp` | RegisterController@verifyOtp | guest, throttle |
| POST | `/register` | RegisterController@store | guest, throttle |
| POST | `/logout` | AuthController@logout | auth |
| GET | `/account/settings` | ProfileController@edit | auth |
| PUT | `/account/settings` | ProfileController@update | auth |
| GET | `/declarations/create` | DeclarationController@create | auth + profile.complete |
| POST | `/declarations` | DeclarationController@store | auth + profile.complete |
| GET | `/declarations/{declaration}` | DeclarationController@show | auth + policy |
| GET | `/declarations/{declaration}/pdf` | DeclarationController@pdf | auth + policy |
| GET | `/admin/users` | Admin\UserController@index | auth + role:admin |
| PUT | `/admin/users/{user}/status` | Admin\UserController@updateStatus | auth + role:admin |

Middleware kustom: `EnsureProfileComplete`, `EnsureRole`.

## 6. UI / Blade

- Layout `layouts/app.blade.php`: header logo + judul, nav (Isi Form, Setting Akun, Logout), konten max-width ~1000px, Tailwind.
- `auth/login`, `auth/register` (3 langkah via Alpine: email→OTP→detail akun).
- `account/settings`: panel readonly email/NIK + field editable, badge status profil, nav Isi Form disabled bila belum lengkap.
- `declarations/create`: Section A (readonly), B (radio + checkbox kondisional Alpine), C (tabel rincian dinamis, Tambah/Hapus, konfirmasi hapus kategori), pernyataan + APPROVED hijau, tombol Simpan dan Buat PDF (loading state).
- `declarations/show`: status, tanggal, tombol Unduh PDF.
- `pdf/declaration`: template cetak A4 (CSS khusus print), footer kode dokumen.

## 7. Validasi (Form Requests)

- `SendOtpRequest`: email `regex:/@sig\.id$/i`.
- `VerifyOtpRequest`: email + code 6 digit.
- `RegisterRequest`: email `@sig.id` unik, nik `regex:/^[0-9]{16}$/` unik, password confirmed + rules kekuatan, memastikan email sudah terverifikasi OTP.
- `ProfileUpdateRequest`: full_name, position, work_unit required.
- `StoreDeclarationRequest`: status in none|has_conflict; jika has_conflict → categories min 1 (subset dari daftar valid), details min 1, tiap detail category∈categories terpilih + nama + penjelasan; jika none → categories/details harus kosong.

## 8. Otorisasi

- `DeclarationPolicy@view` dan `@download`: `declaration.user_id === auth id` ATAU `user.role==='admin'`.
- Route model binding + `authorize()` di controller.

## 9. Alur PDF

1. Validasi (Form Request). 2. Snapshot profil dari DB. 3. Simpan dalam transaksi. 4. Render Blade→Dompdf. 5. Simpan ke disk privat + `pdf_path`. 6. Unduh via route berotorisasi (streamDownload). 7. Jika gagal pada langkah 4–5, data tetap ada; tombol "Buat PDF" bisa diulang.

Uji skenario: none, satu kategori, banyak kategori, banyak rincian, nama/uraian panjang, pergantian halaman.

## 10. Keamanan

- CSRF default Laravel, session, `SESSION_SECURE_COOKIE` di produksi (HTTPS).
- Rate limiting login & OTP (RateLimiter).
- OTP: `random_int`, hash tersimpan, expired/sekali pakai/attempt cap, invalidasi saat resend, tidak di-log.
- Respons OTP/registrasi netral (tidak membocorkan keberadaan akun).
- PDF privat; akses lewat policy.
- Snapshot employee data; tidak percaya input browser untuk identitas.

## 11. Testing

- Feature tests: registrasi+OTP (happy/expired/attempt cap/resend), login email&NIK, guard profil, validasi Bagian B/C (konsistensi), transaksi deklarasi, otorisasi detail/PDF (IDOR), audit log tercatat.
- Unit tests: OtpService (hash, expiry, attempts), validasi StoreDeclarationRequest.
- Jalankan `php artisan test`.

## 12. Catatan terbuka (dari produk)

Tetap mengacu pada bagian 14 `design.md` root: pencocokan NIK ke daftar karyawan resmi, kebijakan multi-deklarasi, makna APPROVED, dan jumlah halaman PDF final — dikonfirmasi ke pemilik proses sebelum produksi. Untuk versi ini: APPROVED = penanda sistem setelah submit; satu PDF per deklarasi; multi-deklarasi diizinkan (riwayat sederhana).
