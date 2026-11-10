# Deploy SparingIn ke Shared Hosting (cPanel, tanpa SSH)

Panduan ini untuk hosting **cPanel** yang hanya punya **File Manager / FTP** (tanpa Terminal/SSH).
Semua yang biasanya dijalankan di server (`composer install`, `npm run build`, `php artisan migrate`) sudah dikerjakan di laptop dan dimasukkan ke paket.

---

## 0. Syarat hosting

| Kebutuhan | Keterangan |
|---|---|
| PHP | **8.3 atau lebih baru** (8.4 juga bisa) |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `ctype`, `tokenizer`, `xml`, `curl` |
| Database | MySQL 8 / MariaDB 10.4+ |
| Lainnya | Bisa mengatur versi PHP (MultiPHP Manager / Select PHP Version) |

> HTTPS (SSL) sangat disarankan, dan **wajib** supaya PWA bisa di-install. Kebanyakan cPanel punya **AutoSSL / Let's Encrypt** gratis begitu domain terpasang.

---

## 1. Buat paket di laptop

Jalankan dari folder proyek:

```bash
cd /Users/bimaandriansyah/Projects/sparingin
```

```bash
bash deploy/build.sh
```

Hasilnya: `deploy/dist/sparingin-deploy.zip` (±15 MB), berisi:

| Isi zip | Diletakkan di hosting |
|---|---|
| `sparingin/` | `/home/USERNAME/sparingin` (kode aplikasi, **di luar** `public_html`) |
| `public_html/` | isinya ke `/home/USERNAME/public_html` |
| `schema.sql` | di-import lewat phpMyAdmin |

> ⚠️ Zip ini berisi `APP_KEY` baru di `sparingin/.env`. **Jangan dibagikan.** Buat ulang paket kalau pernah bocor.

---

## 2. Atur PHP di cPanel

1. cPanel → **MultiPHP Manager** (atau **Select PHP Version**).
2. Pilih domain → set ke **PHP 8.3** atau **8.4**.
3. Di **Extensions**, pastikan yang ada di tabel syarat sudah tercentang (terutama `gd`, `fileinfo`, `pdo_mysql`).

---

## 3. Buat database

1. cPanel → **MySQL® Databases**.
2. **Create New Database** → misalnya `sparingin` (nama lengkapnya jadi `USERNAME_sparingin`).
3. **Add New User** → buat user + password kuat (catat).
4. **Add User To Database** → pilih user & database tadi → centang **ALL PRIVILEGES**.
5. cPanel → **phpMyAdmin** → klik database `USERNAME_sparingin` di kiri → tab **Import** → pilih `schema.sql` dari zip → **Go**.
   - Harus muncul 15 tabel (users, communities, events, registrations, dll).

---

## 4. Upload file

1. cPanel → **File Manager** → buka folder **home** (`/home/USERNAME`, satu tingkat di atas `public_html`).
2. **Upload** `sparingin-deploy.zip` ke folder home itu.
3. Klik kanan zip → **Extract** → ke `/home/USERNAME`. Akan muncul folder `sparingin/`, `public_html/` (digabung dengan yang sudah ada), dan `schema.sql`.
   - Kalau `public_html` sudah berisi file bawaan hosting (mis. `index.html`, `default.html`), **hapus** file-file itu.
4. Hapus `sparingin-deploy.zip` dan `schema.sql` dari server setelah selesai.

Struktur akhirnya:

```
/home/USERNAME/
├── sparingin/          ← kode aplikasi + .env (tidak bisa diakses dari web)
│   ├── app/ bootstrap/ config/ vendor/ storage/ ...
│   └── .env
└── public_html/        ← web root
    ├── index.php       ← sudah diarahkan ke ../sparingin
    ├── .htaccess
    ├── build/  images/  storage/
    └── manifest.webmanifest, sw.js, ...
```

> Di File Manager, aktifkan **Settings → Show Hidden Files** supaya `.env` dan `.htaccess` terlihat.

---

## 5. Isi `.env`

Buka `sparingin/.env` → **Edit**. Ganti semua bagian bertanda `<<< GANTI`:

| Variabel | Isi |
|---|---|
| `APP_URL` | Alamat website persis, mis. `https://sparingin.com` (tanpa `/` di akhir). Kalau masih domain sementara dari hosting, isi alamat sementara itu. |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Dari langkah 3 |
| `PUBLIC_STORAGE_PATH` | `/home/USERNAME/public_html/storage` (ganti USERNAME dengan username cPanel) |
| `SESSION_SECURE_COOKIE` | Biarkan `false` dulu; ubah ke `true` setelah HTTPS aktif |

`APP_KEY` sudah terisi otomatis — jangan diubah.

> Username cPanel terlihat di pojok kanan atas cPanel atau di bagian **General Information**. Path home-nya biasanya `/home/USERNAME` (beberapa hosting memakai `/home2/` atau `/home3/` — cek di File Manager).

---

## 6. Izin folder

Di File Manager, klik kanan → **Change Permissions**:

| Folder | Permission |
|---|---|
| `sparingin/storage` (beserta isinya) | `755` (atau `775` jika upload gagal) |
| `sparingin/bootstrap/cache` | `755` |
| `public_html/storage` | `755` |

---

## 7. Cek

1. Buka alamat website → beranda SparingIn tampil.
2. Buka `/register` → buat akun admin pertama (pakai nomor HP).
   - Di production, kata sandi wajib **min. 8 karakter** dengan huruf besar, huruf kecil, angka, dan simbol.
3. Buat komunitas → upload logo → logo harus tampil. Kalau tidak: cek `PUBLIC_STORAGE_PATH` dan permission `public_html/storage`.
4. Buat event → buka link event dari HP → coba alur Bergabung.

---

## 8. Setelah domain + HTTPS aktif

1. cPanel → **SSL/TLS Status** → jalankan **AutoSSL** untuk domain.
2. Edit `sparingin/.env`:
   - `APP_URL=https://domainkamu.com`
   - `SESSION_SECURE_COOKIE=true`
3. Buka website lewat `https://` → PWA (pemain & admin) sekarang bisa di-install.

---

## Deploy dari laptop (satu perintah, lewat FTP)

Sama seperti CI/CD, tapi dijalankan dari laptop — dipakai selama GitHub Actions belum bisa jalan (mis. akun GitHub terkunci karena billing).

**Sekali saja:**

1. Install `lftp`:

```bash
brew install lftp
```

2. Isi kredensial FTP di `deploy/ftp.env` (file ini **tidak** ikut ke git):

```bash
cp deploy/ftp.env.example deploy/ftp.env
```

   Isi `FTP_HOST`, `FTP_USER`, `FTP_PASSWORD` dengan akun FTP **utama** cPanel (sama seperti secret GitHub).

3. Pasang **Cron Job** `sparingin:post-deploy` di cPanel (lihat bagian *CI/CD → C* di bawah) supaya migrate jalan otomatis.

**Setiap mau deploy:**

```bash
bash deploy/ftp-deploy.sh
```

Skrip ini: menjalankan semua test (batal kalau ada yang gagal) → build → upload **hanya file yang berubah** → memberi tanda ke cron untuk migrate + bersihkan cache.

| Perintah | Fungsi |
|---|---|
| `bash deploy/ftp-deploy.sh --dry-run` | Lihat file apa saja yang akan di-upload/dihapus, tanpa mengirim apa pun |
| `bash deploy/ftp-deploy.sh --skip-tests` | Lewati test (tidak disarankan) |

Catatan:

- **Deploy pertama** meng-upload semua file (±9.400 file termasuk `vendor`), bisa 10–30 menit tergantung koneksi. Berikutnya hanya file yang berubah.
- Catatan "apa yang sudah ada di server" disimpan di `deploy/dist/last-deployed/`. Jangan dihapus; kalau terhapus, deploy berikutnya akan meng-upload semua file lagi (aman, hanya lebih lama).
- Kalau FTPS gagal karena sertifikat (sertifikat hosting dibuat untuk nama server, bukan `ftp.domain`), set `FTP_VERIFY_CERT=no` di `deploy/ftp.env`.
- `.env`, log, sesi, bukti transfer, dan `public_html/storage` di server **tidak pernah** diubah atau dihapus.

---

## CI/CD otomatis (GitHub Actions + FTP)

Setelah ini aktif, update cukup dengan **push ke branch `main`**:

1. GitHub menjalankan semua test. Kalau ada yang gagal, deploy dibatalkan.
2. Aplikasi di-build (asset + `vendor` production).
3. File yang **berubah saja** di-upload lewat FTP ke `~/sparingin` dan `~/public_html`.
4. Cron di cPanel menjalankan `php artisan sparingin:post-deploy`: migrate database + bersihkan cache, **sekali** per deploy.

`.env`, log, sesi, dan file upload pengguna (`public_html/storage`, bukti transfer) **tidak pernah disentuh**.

### A. Simpan kode di GitHub (sekali)

1. Buat repository **private** baru di GitHub (mis. `sparingin`), tanpa README.
2. Push kode dari laptop (repo git lokal sudah disiapkan):

```bash
git remote add origin git@github.com:USERNAME-GITHUB/sparingin.git
git push -u origin main
```

### B. Isi secret FTP di GitHub (sekali)

Repository → **Settings → Secrets and variables → Actions → New repository secret**:

| Secret | Isi |
|---|---|
| `FTP_SERVER` | Host FTP dari cPanel → **FTP Accounts** → *Configure FTP Client* (mis. `ftp.domainkamu.com` atau IP server) |
| `FTP_USERNAME` | Username FTP **akun utama cPanel** (folder awalnya `/home/USERNAME`) |
| `FTP_PASSWORD` | Password akun FTP tersebut |

Opsional, di tab **Variables**:

| Variable | Default | Kapan diubah |
|---|---|---|
| `FTP_PROTOCOL` | `ftps` | Isi `ftp` kalau hosting tidak mendukung FTPS (lebih tidak aman) |
| `FTP_APP_DIR` | `sparingin/` | Kalau folder aplikasi di server bernama lain |
| `FTP_WEB_DIR` | `public_html/` | Kalau web root bukan `public_html` (mis. subdomain) |

> Pakai akun FTP **utama**. Akun FTP tambahan biasanya dikunci di dalam `public_html`, sehingga tidak bisa menulis ke `~/sparingin`.

### C. Pasang Cron Job di cPanel (sekali)

cPanel → **Cron Jobs** → *Add New Cron Job*:

- **Common Settings**: *Once Per Five Minutes* (`*/5 * * * *`)
- **Command**:

```bash
/usr/local/bin/php /home/USERNAME/sparingin/artisan sparingin:post-deploy >> /dev/null 2>&1
```

> Path PHP berbeda-beda antar hosting. Kalau cron tidak jalan, ganti `/usr/local/bin/php` dengan versi yang sesuai, mis. `/opt/cpanel/ea-php83/root/usr/bin/php` (lihat di **MultiPHP Manager** / tanya support hosting). Pastikan versinya **8.3+**.

Cron ini ringan: kalau tidak ada deploy baru, ia langsung selesai tanpa melakukan apa-apa. Hasil tiap deploy tercatat di `sparingin/storage/logs/laravel.log` ("Post-deploy … done").

### D. Deploy pertama

- Jalankan dari tab **Actions → Deploy → Run workflow**, atau cukup push ke `main`.
- Deploy pertama meng-upload **semua** file (termasuk `vendor`), bisa makan **10–30 menit**. Deploy berikutnya hanya file yang berubah, biasanya 1–3 menit.
- Setelah selesai, tunggu maksimal 5 menit sampai cron menjalankan migrate.

---

## Update aplikasi (versi baru)

> Sudah pakai CI/CD? Cukup push ke `main`, langkah di bawah tidak perlu.

1. Di laptop: `bash deploy/build.sh`.
2. Upload & extract **hanya** folder yang berubah, biasanya:
   - `sparingin/app`, `sparingin/resources`, `sparingin/routes`, `sparingin/config`, `sparingin/lang`, `sparingin/vendor` (kalau dependency berubah)
   - `public_html/build` (hapus `build` lama dulu) dan file lain di `public_html` kecuali `storage/`
3. **Jangan timpa** `sparingin/.env` dan `public_html/storage/` (berisi upload pengguna).
4. Kalau ada **migration baru**: upload juga file kosong `sparingin/storage/app/deploy-pending`, maka cron (bagian C di atas) akan menjalankan migrate. Tanpa cron: jalankan SQL perubahannya lewat phpMyAdmin.

---

## Masalah umum

| Gejala | Penyebab & solusi |
|---|---|
| Halaman putih / error 500 | Cek `sparingin/storage/logs/laravel.log`. Biasanya versi PHP < 8.3, ekstensi kurang, atau isi `.env` salah. |
| "No application encryption key" | `APP_KEY` di `.env` kosong/terhapus → buat ulang paket, salin baris `APP_KEY`-nya. |
| Error koneksi database | `DB_HOST=localhost`, cek nama DB & user lengkap dengan prefix `USERNAME_`. |
| CSS/tampilan berantakan | Folder `public_html/build` belum ter-upload. |
| Logo/foto tidak tampil setelah upload | `PUBLIC_STORAGE_PATH` salah atau folder `public_html/storage` belum ada/tidak bisa ditulis. |
| Selalu kembali ke halaman login | `SESSION_SECURE_COOKIE=true` tapi website masih `http://` → set `false` sampai HTTPS aktif. |
| 404 di semua halaman selain beranda | `.htaccess` tidak ikut ter-upload (file tersembunyi) atau `mod_rewrite` mati. |
