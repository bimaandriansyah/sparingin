# Backlog SparingIn

Fitur yang sudah disepakati tapi belum dikerjakan. Urutan kasar dari yang paling dibutuhkan.

## Sebelum online (production)
- [ ] `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, `SESSION_SECURE_COOKIE=true`.
- [ ] Wajib HTTPS (header HSTS otomatis aktif di HTTPS) — juga syarat PWA.
- [ ] `php artisan storage:link` di server; pastikan `storage/app/private` (bukti transfer) **tidak** bisa diakses publik.
- [ ] Backup database terjadwal.
- [ ] Pertimbangkan Content-Security-Policy setelah semua asset final.

## Akun admin
- [ ] **Reset kata sandi via OTP WhatsApp**: pengganti "Lupa kata sandi" yang dimatikan saat login pindah ke nomor HP. Butuh layanan pengirim WA (mis. Fonnte, Wablas, atau WhatsApp Cloud API).
- [ ] **Verifikasi nomor HP saat daftar** (OTP WA): sekarang siapa pun bisa daftar dengan nomor orang lain. Bisa memakai layanan OTP yang sama dengan reset kata sandi.
- [ ] **Rapikan halaman login & daftar** mengikuti design system baru (card, btn, display).

## Pemain & event
- [ ] **Feed "Butuh Pemain"**: daftar publik event yang masih punya slot kosong, bisa difilter per kota.
- [ ] **Riwayat main pemain**: dikelompokkan dari nomor WA di `registrations`.
- [ ] **QR check-in** di lapangan.
- [ ] **Unduh gambar line-up** per tim.
- [ ] **Badge verified** untuk komunitas.

## Pembayaran & notifikasi
- [ ] **Payment gateway (QRIS)**, mis. Xendit/Midtrans, supaya konfirmasi bayar otomatis.
- [ ] **Pengingat WA** ke pemain sebelum kick-off.
- [ ] **Push notification** ke admin saat ada pendaftar baru (PWA, setelah online dengan HTTPS).

## Ditunda
- [ ] **Cari Lawan**: tim pasang jadwal sparing, tim lain mengajukan diri. Tambah tabel `teams` dan `match_requests` yang terhubung ke `communities`.
