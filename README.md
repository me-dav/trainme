# devops-dojo

Platform latihan pribadi untuk Linux, Docker, Kubernetes, jaringan, dan DevSecOps. Materinya dikurasi
dari dokumentasi, dan soalnya dijawab dengan **mengetik perintah**, bukan pilihan ganda.
Modul dikelompokkan per board (Linux, Docker, ...). Tiap modul punya tiga tier: A (basic),
B (advanced), dan C (studi kasus). Lulus minimal 80% membuka tier berikutnya. Selama tes berjalan,
materi, bank soal, dan hasil lama modul itu dikunci sampai tes selesai atau diakhiri.

Tidak ada AI di runtime. Konten ditulis sebagai file modul (`content/**/*.md`, format di
[`content/FORMAT.md`](content/FORMAT.md)), divalidasi, lalu diimpor.

## Stack

Laravel 13 (PHP 8.3), PostgreSQL 17, Blade + Tailwind v4, Mermaid, dan highlight.js.
Untuk production dipakai FrankenPHP dalam container non-root.

## Development lokal

```bash
cp .env.example .env            # isi DB_PASSWORD dengan string acak
composer install && npm install
php artisan key:generate
docker compose up -d db         # PostgreSQL di 127.0.0.1:5432
php artisan migrate --seed      # seeder mengimpor semua modul di content/
php artisan dojo:user kamu@example.com
npm run build                   # atau: npm run dev
php artisan serve
```

## Konten

```bash
php artisan dojo:import --dry-run                 # validasi + self-test semua modul
php artisan dojo:import content/linux             # impor satu folder
php artisan dojo:import content/linux/02-permission-ownership.md
```

Lewat aplikasi: buat board, lalu klik **Impor modul** di board itu. Modal berisi tiga langkah: unduh
template, salin prompt untuk AI, lalu unggah file `.md` hasilnya. Setelah itu muncul pratinjau.
Kalau file ditolak, tombol **Salin error untuk AI** menyiapkan pesan perbaikan. Modul hasil unggahan
masuk sebagai `draft` sampai kamu tandai terverifikasi.

Template ada di `resources/templates/module-template.md` dan dijaga lewat test agar selalu lolos validasi.

## Test

```bash
php artisan test --compact
```

## Menjalankan full stack di Docker

```bash
docker compose up -d --build    # app di http://127.0.0.1:8080, migrasi otomatis
docker compose exec app php artisan dojo:import
docker compose exec app php artisan dojo:user kamu@example.com
```

Container app memakai user non-root (UID 10001), filesystem read-only, `cap_drop: ALL`, dan
`no-new-privileges`. Hanya `storage/` dan `bootstrap/cache/` yang writable.
