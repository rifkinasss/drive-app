# Drive by NasLabs

Version 2.0.0

Drive by NasLabs adalah aplikasi penyimpanan dan pengelolaan file pribadi melalui browser. Pengguna dapat mengatur file dalam folder, mencari dan mengurutkan isi folder, mengelola file yang baru dibuka, menyimpan item favorit, serta berbagi akses dengan pengguna lain atau melalui tautan publik.

Repository ini memuat frontend web dan backend API dalam satu direktori. Frontend menjadi antarmuka pengguna, sedangkan Laravel API dan PostgreSQL menjadi sumber data utama. File disimpan pada disk privat backend, bukan disajikan sebagai URL publik.

## Untuk siapa

Cloud ditujukan bagi individu dan tim kecil yang perlu menyimpan file, mengaturnya dalam folder, dan mengendalikan siapa yang dapat melihat atau mengunduhnya. Admin mengelola akun, kuota, dan pengaturan aplikasi. Setiap akun memiliki ruang file sendiri; peran admin tidak memberikan akses otomatis ke file pengguna lain.

## Kemampuan produk

### File dan penyimpanan

- Menjelajahi, mencari, mengurutkan, membuat, mengganti nama, dan memindahkan folder serta file.
- Mengunggah file dengan pemeriksaan kepemilikan folder, kuota, batas ukuran, dan strategi konflik nama.
- Mengunduh file privat dan melihat pratinjau tipe file yang diizinkan.
- Melihat penggunaan dan sisa kuota, serta menandai file atau folder sebagai favorit.
- Melihat file terbaru berdasarkan waktu perubahan metadata. Ini bukan riwayat file yang dibuka.
- Memindahkan item ke Trash, memulihkannya, atau menghapusnya permanen. Item di Trash tetap dihitung dalam pemakaian kuota sampai dihapus permanen.

### Akun dan akses

- Masuk menggunakan sesi browser Laravel Sanctum, pemulihan kata sandi, verifikasi email, dan penerimaan undangan.
- Status akun yang tersedia adalah pending, active, dan disabled. Hanya akun aktif yang dapat menggunakan area aplikasi yang dilindungi.
- Berbagi file atau folder dengan akun Drive lain sebagai viewer atau editor. Hak editor pada v1 mencakup penggantian nama file, bukan pengelolaan folder.
- Membuat tautan publik hanya-baca untuk file atau folder. Tautan folder membatasi penjelajahan ke subtree yang dibagikan.
- Memperbarui nama profil, mengatur preferensi tampilan/file, dan meminta email reset kata sandi dari halaman pengaturan. Pengelolaan sesi aktif belum tersedia melalui API.

### Aktivitas, notifikasi, dan administrasi

- Melihat timeline aktivitas pribadi untuk tindakan file dan folder yang didukung.
- Menerima notifikasi untuk peristiwa yang memerlukan perhatian, termasuk perubahan berbagi dan peringatan kuota.
- Admin dapat mencari dan mengelola akun, peran, status, serta kuota. Sistem melindungi admin aktif terakhir dari tindakan yang akan menghilangkan akses admin terakhir.
- Admin dapat mengatur kelompok konfigurasi aplikasi yang didukung. Rahasia dan kredensial tetap berada di konfigurasi lingkungan, bukan di pengaturan melalui API.
- Admin dapat mengaktifkan mode maintenance untuk API aplikasi sesuai aturan akses yang dikonfigurasi.

## Batasan yang diketahui

Fitur berikut belum menjadi bagian dari cakupan v1 yang didokumentasikan backend:

- Kolaborasi atau pengeditan file bersama secara langsung.
- Berbagi ke alamat email eksternal.
- Kata sandi, masa berlaku, batas unduhan, analitik, atau kemampuan unggah pada tautan publik.
- Pengubahan email oleh admin, antivirus, dan pemindaian otomatis untuk file berbahaya.
- Pratinjau inline untuk HTML, XHTML, dan SVG.

Tautan publik memberi akses viewer tanpa akun, jadi pemilik perlu membagikannya dengan hati-hati. Backend menggunakan penyimpanan privat dan pemeriksaan akses, tetapi deployment tetap harus menyiapkan HTTPS, kredensial, konfigurasi proxy, mail, backup, serta scheduler sesuai dokumentasi operasional.

## Teknologi

- Frontend: Next.js 16, React 19, TypeScript, Tailwind CSS 4.
- Backend: Laravel 13, PHP 8.3+, Laravel Sanctum, dan Scramble untuk dokumentasi API.
- Database: PostgreSQL.
- Autentikasi browser: cookie sesi Sanctum dan perlindungan CSRF. Frontend tidak menyimpan bearer token.
- Queue, session, dan cache lokal menggunakan dukungan database Laravel. Penyimpanan Drive memakai disk privat yang lokasinya dapat dikonfigurasi.

Versi dependency mengikuti file manifest dan lockfile masing-masing aplikasi. Periksa versi yang benar-benar terpasang pada mesin sebelum rilis.

## Menjalankan secara lokal

Prasyarat: PHP 8.3+, Composer, Node.js/npm, dan PostgreSQL. Gunakan dua terminal dari direktori repository ini.

### 1. Siapkan backend

```sh
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Atur koneksi PostgreSQL dan nilai lokal lain di `backend/.env`. Pastikan database sudah dibuat, lalu jalankan:

```sh
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Di terminal backend kedua, jalankan worker agar email dan pekerjaan queue diproses:

```sh
cd internal-projects/Drive-App/backend
php artisan queue:work database
```

Untuk email lokal, konfigurasi contoh menggunakan `MAIL_MAILER=log`; pesan email dapat diperiksa di log Laravel. Endpoint pemeriksaan kesehatan tersedia di `http://127.0.0.1:8000/api/health`.

### 2. Siapkan frontend

```sh
cd frontend
npm install
cp .env.example .env.local
```

Nilai lokal pada `.env.example` mengarahkan frontend ke API `http://localhost:8000` dan aplikasi `http://localhost:3000`. Jalankan:

```sh
npm run dev
```

Buka `http://localhost:3000`. Untuk autentikasi cookie lintas origin lokal, pertahankan origin frontend pada `SANCTUM_STATEFUL_DOMAINS` dan `CORS_ALLOWED_ORIGINS` backend. Jangan menyalin konfigurasi cookie lokal ke produksi.

### Akun pengembangan

Seeder tersedia untuk lingkungan non-produksi:

```sh
cd backend
php artisan db:seed
```

Seeder menggunakan kredensial contoh yang tidak aman untuk deployment. Atur `SEED_ADMIN_EMAIL`, `SEED_ADMIN_NAME`, `SEED_ADMIN_PASSWORD`, `SEED_USER_EMAIL`, `SEED_USER_NAME`, dan `SEED_USER_PASSWORD` di konfigurasi lokal sebelum menjalankannya. `DatabaseSeeder` menolak berjalan saat `APP_ENV=production`. Jangan gunakan kata sandi bawaan atau menyimpan rahasia di repository.

## Konfigurasi penting

Salin template lingkungan, lalu isi nilai yang sesuai. Jangan commit file `.env`, `APP_KEY`, password, atau kredensial SMTP.

| Bagian | Variabel utama |
| --- | --- |
| Frontend | `NEXT_PUBLIC_API_URL`, `NEXT_PUBLIC_APP_URL` |
| Aplikasi Laravel | `APP_ENV`, `APP_KEY`, `APP_URL`, `FRONTEND_URL`, `APP_DEBUG` |
| PostgreSQL | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| Browser session dan CORS | `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`, `SANCTUM_STATEFUL_DOMAINS`, `CORS_ALLOWED_ORIGINS`, `TRUSTED_PROXIES` |
| File dan kuota | `CLOUD_STORAGE_DISK`, `CLOUD_STORAGE_ROOT`, `CLOUD_MAX_UPLOAD_SIZE_BYTES`, `CLOUD_DEFAULT_USER_QUOTA_BYTES` |
| Queue dan mail | `QUEUE_CONNECTION`, `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` |

`NEXT_PUBLIC_API_URL` adalah origin API tanpa akhiran `/api`. Untuk deployment, gunakan HTTPS dan domain frontend/API yang benar. Atur domain cookie Sanctum, daftar origin CORS, serta proxy tepercaya sesuai topologi aktual. Simpan storage Cloud di luar webroot dan gunakan lokasi privat yang persisten.

## Pengujian dan dokumentasi API

Backend:

```sh
cd backend
php artisan test
vendor/bin/pint --test
```

Frontend:

```sh
cd frontend
npm run lint
npm run build
```

Dokumentasi API Laravel tersedia pada `/docs`, referensi endpoint pada `/docs/api`, dan spesifikasi OpenAPI pada `/docs/api.json` saat dokumentasi diaktifkan. Variabel `API_DOCS_ENABLED=false` menonaktifkan dokumentasi tersebut.

## Struktur repository

```text
Drive-App/
├── backend/   Laravel API, migrasi, seeder, pengujian, dan runbook operasi
└── frontend/  Aplikasi web Next.js
```

Runbook backend berada di `backend/docs/`, termasuk autentikasi produksi, pengiriman email, pemeliharaan storage, serta prosedur backup dan restore. Dokumen backup menjelaskan prosedur operasi, bukan layanan backup otomatis yang disediakan aplikasi. Scheduler Laravel juga memerlukan konfigurasi scheduler pada host deployment.

## Catatan keamanan dan operasi

- File memakai disk privat. API tidak mengembalikan path fisik atau URL publik storage.
- Hak akses file diperiksa berdasarkan pemilik atau share eksplisit; role admin tidak melewati batas kepemilikan.
- Undangan, verifikasi email, reset kata sandi, dan tautan publik menggunakan token yang tidak disimpan sebagai token plaintext.
- Atur mail provider dan worker queue di lingkungan deployment agar email terkirim.
- Jalankan scheduler Laravel pada host untuk tugas maintenance terjadwal. Tinjau hasil dry-run sebelum menjalankan perintah cleanup destruktif.
- Jalankan backup PostgreSQL dan storage sebagai satu prosedur konsisten, simpan backup terenkripsi dan terpisah dari host aplikasi, lalu lakukan restore drill.

Detail endpoint, konfigurasi, perilaku penyimpanan, serta prosedur operasional ada di `backend/README.md` dan dokumen dalam `backend/docs/`.
