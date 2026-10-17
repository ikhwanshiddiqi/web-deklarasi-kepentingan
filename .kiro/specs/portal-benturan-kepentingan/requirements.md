# Requirements — Portal Pernyataan Benturan Kepentingan

**Kode formulir:** FI/SP/GRC/973 — Rev. 01
**Bahasa antarmuka:** Bahasa Indonesia
**Zona waktu aplikasi:** `Asia/Jakarta`

## Pendahuluan

Portal internal untuk karyawan PT Semen Padang (domain email `@sig.id`) membuat akun, verifikasi email dengan OTP, login (email atau NIK), melengkapi profil, mengisi pernyataan benturan kepentingan, dan menghasilkan PDF sesuai format formulir resmi. Versi awal memakai database mandiri (PostgreSQL). Konversi PDF memakai template HTML/CSS A4 yang dirender dengan Dompdf (karena LibreOffice tidak tersedia pada lingkungan ini).

## Keputusan Teknologi (dikonfirmasi)

- Backend: Laravel (PHP 8.4), Blade + Tailwind CSS, Alpine.js.
- Database: PostgreSQL (bundel Laragon).
- PDF: Template HTML/CSS A4 → Dompdf (`barryvdh/laravel-dompdf`).
- Email/OTP (dev): mail driver `log`/Mailpit; SMTP produksi dikonfigurasi belakangan.
- Role: `employee` (default) dan `admin` (opsional, kelola status akun).

## Daftar Kebutuhan

### Requirement 1 — Registrasi akun dengan verifikasi OTP email

**User Story:** Sebagai karyawan, saya ingin membuat akun dengan email perusahaan `@sig.id` yang diverifikasi OTP, agar hanya pemilik inbox resmi yang dapat mendaftar.

#### Acceptance Criteria

1. WHEN pengguna mengirim email registrasi THEN sistem SHALL memvalidasi bahwa email berakhiran `@sig.id` (validasi server), menormalkan email (trim, lowercase), dan menolak domain lain.
2. WHEN email valid dan belum terdaftar THEN sistem SHALL membuat OTP numerik 6 digit menggunakan generator acak kriptografis, menyimpan hanya hash OTP, menetapkan `expires_at` 5 menit, dan mengirim kode ke email.
3. WHEN pengguna meminta kirim ulang OTP THEN sistem SHALL menerapkan jeda minimal 60 detik, membuat kode baru, dan membatalkan kode sebelumnya.
4. THE sistem SHALL membatasi maksimal 5 pengiriman per email per jam dan rate limit per IP.
5. WHEN pengguna memasukkan OTP THEN sistem SHALL memverifikasi hash, menolak jika kedaluwarsa/sudah dipakai/melebihi 5 percobaan salah, dan menandai `consumed_at` saat berhasil.
6. WHEN OTP terverifikasi THEN pengguna SHALL melanjutkan mengisi NIK (tepat 16 digit angka `^[0-9]{16}$`), password, dan konfirmasi password.
7. WHEN data registrasi dikirim THEN sistem SHALL memvalidasi keunikan email dan NIK (validasi aplikasi DAN unique constraint database), menyimpan password dengan hashing bawaan Laravel, mengisi `email_verified_at`, dan membuat akun aktif.
8. THE sistem SHALL TIDAK PERNAH menulis OTP teks biasa ke log atau menyimpannya sebagai teks biasa.
9. THE respons registrasi/OTP SHALL TIDAK membocorkan apakah email sudah terdaftar.
10. THE sistem SHALL mengedaluwarsakan/membersihkan data registrasi/OTP yang tidak selesai setelah periode terbatas.

### Requirement 2 — Login dengan email atau NIK

**User Story:** Sebagai karyawan, saya ingin login dengan email atau NIK dan password, agar fleksibel.

#### Acceptance Criteria

1. WHEN pengguna mengirim identitas (email atau NIK) dan password THEN sistem SHALL mengautentikasi terhadap akun yang `email_verified_at`-nya terisi.
2. IF identitas mengandung `@` THEN sistem SHALL memperlakukannya sebagai email (dinormalkan) ELSE sebagai NIK.
3. WHEN kredensial salah THEN sistem SHALL menampilkan pesan generik tanpa membocorkan field mana yang salah.
4. THE login SHALL dibatasi rate limiting (misal 5 percobaan gagal lalu throttle).
5. WHEN login berhasil DAN profil lengkap THEN arahkan ke `Isi Form`; IF profil belum lengkap THEN arahkan ke `Setting Akun`.

### Requirement 3 — Profil karyawan (Setting Akun)

**User Story:** Sebagai karyawan, saya ingin melengkapi profil, agar bisa mengisi form deklarasi.

#### Acceptance Criteria

1. THE halaman Setting Akun SHALL menampilkan Nama Lengkap, NIK (readonly), Jabatan/Posisi, Unit Kerja, dan Email (readonly dari akun).
2. WHEN pengguna menyimpan profil THEN sistem SHALL memvalidasi di server bahwa Nama Lengkap, Jabatan/Posisi, dan Unit Kerja terisi.
3. THE sistem SHALL menandai status profil `Belum lengkap` atau `Lengkap` berdasarkan kelengkapan Nama Lengkap, NIK, Jabatan/Posisi, dan Unit Kerja.
4. WHILE profil belum lengkap THE menu `Isi Form` SHALL terlihat namun dinonaktifkan dengan penjelasan.
5. THE backend SHALL mengambil profil dari database berdasarkan user login, bukan dari input browser.

### Requirement 4 — Form deklarasi Bagian A/B/C

**User Story:** Sebagai karyawan dengan profil lengkap, saya ingin mengisi deklarasi benturan kepentingan sesuai formulir resmi.

#### Acceptance Criteria

1. THE Bagian A SHALL menampilkan data karyawan (nama, NIK, jabatan, unit kerja) baca-saja yang diambil backend dari user login.
2. THE Bagian B SHALL menyediakan radio status: `Tidak memiliki` atau `Memiliki` benturan kepentingan.
3. WHEN memilih `Memiliki` THEN sistem SHALL menampilkan 5 checkbox kategori: `family_relationship`, `multiple_positions`, `hr_process`, `procurement_finance`, `financial_interest`.
4. IF status `Memiliki` THEN pengguna SHALL wajib memilih minimal satu kategori; backend memvalidasi hal ini.
5. IF status `Tidak memiliki` THEN seluruh kategori SHALL kosong dan Bagian C disembunyikan/dinonaktifkan; backend menolak kombinasi `Tidak memiliki` + kategori/rincian.
6. WHEN pengguna beralih dari `Memiliki` ke `Tidak memiliki` THEN UI SHALL meminta konfirmasi bahwa kategori dan rincian Bagian C akan dihapus.
7. THE Bagian C SHALL aktif hanya jika minimal satu kategori dicentang, dengan kolom: No. (otomatis), Kondisi (kategori), Nama Pihak Terkait, Penjelasan Situasi.
8. THE Bagian C SHALL menyediakan tombol Tambah/Hapus baris; nomor urut diperbarui otomatis.
9. THE setiap rincian SHALL hanya boleh memakai kategori yang dicentang di Bagian B; backend memvalidasi konsistensi ini.
10. IF ada kategori dicentang THEN minimal satu rincian SHALL diisi, dan setiap rincian wajib kategori + nama pihak + penjelasan.
11. WHEN deklarasi disimpan THEN sistem SHALL menyimpan deklarasi, kategori, dan rincian dalam satu transaksi database.
12. THE sistem SHALL menyimpan snapshot `employee_name`, `employee_nik`, `employee_position`, `employee_work_unit` pada deklarasi agar PDF historis tidak berubah.
13. THE pernyataan tetap, `submitted_at` (zona `Asia/Jakarta`), dan label `APPROVED` SHALL disimpan pada deklarasi.

### Requirement 5 — Pembuatan dan unduh PDF

**User Story:** Sebagai pemilik deklarasi, saya ingin menghasilkan dan mengunduh PDF sesuai format resmi.

#### Acceptance Criteria

1. WHEN deklarasi tersimpan THEN sistem SHALL merender template HTML/CSS A4 menjadi PDF via Dompdf.
2. THE PDF SHALL memuat Bagian A, status Bagian B, kategori terpilih, rincian Bagian C, pernyataan, tanggal, tulisan `APPROVED` hijau dengan nama pengguna, serta footer `FI/SP/GRC/973` dan `Rev. 01`.
3. THE PDF SHALL disimpan di penyimpanan privat (bukan folder publik) dan path-nya disimpan di `pdf_path`.
4. IF pembuatan PDF gagal THEN deklarasi SHALL tetap tersimpan dan pengguna dapat mencoba membuat PDF lagi.
5. WHEN pengguna mengunduh PDF THEN sistem SHALL memeriksa otorisasi (pemilik atau admin berizin) sebelum mengirim file.

### Requirement 6 — Otorisasi dan kepemilikan data

**User Story:** Sebagai perusahaan, saya ingin data deklarasi hanya diakses pemiliknya, agar kerahasiaan terjaga.

#### Acceptance Criteria

1. THE endpoint detail (`/declarations/{id}`) dan unduh PDF SHALL menggunakan policy/authorization, bukan sekadar menyembunyikan tombol.
2. WHEN pengguna mengubah ID URL ke deklarasi milik akun lain THEN sistem SHALL menolak akses (403/404).
3. THE sistem SHALL TIDAK memercayai `user_id`, nama, NIK, posisi, atau unit kerja dari browser; nilai diambil dari sesi/database.
4. THE PDF privat SHALL hanya dapat diakses melalui route berotorisasi.

### Requirement 7 — Audit log

**User Story:** Sebagai pengelola, saya ingin aktivitas penting tercatat, untuk kebutuhan audit.

#### Acceptance Criteria

1. THE sistem SHALL mencatat event: registrasi, perubahan profil, pengiriman deklarasi, pembuatan ulang PDF, dan unduhan dokumen.
2. THE audit log SHALL menyimpan `user_id`, `event`, `target_type`, `target_id`, `created_at`.
3. THE audit log SHALL TIDAK menyimpan password, token sesi, atau OTP.

### Requirement 8 — Role admin minimal (opsional)

**User Story:** Sebagai admin, saya ingin mengelola status akun secara terbatas tanpa mengubah isi deklarasi tanpa jejak audit.

#### Acceptance Criteria

1. THE akun admin SHALL dapat melihat daftar akun dan mengubah status aktif/nonaktif akun.
2. THE admin SHALL TIDAK dapat mengubah isi deklarasi tanpa pencatatan audit.
3. THE akses admin SHALL dibatasi role `admin` melalui middleware/policy.

### Requirement 9 — Keamanan dan validasi lintas fitur

#### Acceptance Criteria

1. THE semua validasi penting SHALL dijalankan di server.
2. THE aplikasi SHALL menerapkan perlindungan CSRF, validasi sesi, cookie aman via HTTPS, dan hashing password bawaan Laravel.
3. THE `users.email` dan `users.nik` SHALL memiliki unique constraint database; NIK disimpan sebagai `VARCHAR(16)`.
4. THE foreign key dan index SHALL diterapkan pada relasi deklarasi/kategori/rincian.
5. THE kegagalan server SHALL dicatat tanpa memasukkan password/token/OTP ke log.

### Requirement 10 — Responsif dan rancangan visual

#### Acceptance Criteria

1. THE antarmuka SHALL responsif; pada ponsel field menjadi satu kolom dan tombol utama mudah ditekan.
2. THE gaya SHALL formal: latar putih/abu muda, teks gelap, aksen biru korporat; hijau hanya untuk `APPROVED`.
3. THE tombol `Simpan dan Buat PDF` SHALL memiliki state loading untuk mencegah pengiriman ganda.
4. THE pesan kesalahan SHALL muncul dekat field terkait dan tidak hanya mengandalkan warna.
