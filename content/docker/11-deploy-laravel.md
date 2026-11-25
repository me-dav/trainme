---
format: 1
id: docker-deploy-laravel
course:
  slug: docker
  title: Docker
  description: Container dari nol sampai production — CLI, image, storage, network, Compose, keamanan, dan operasional.
title: Deploy Laravel dengan Docker Compose
summary: Menyatukan semua modul Docker untuk men-deploy Laravel ke production — satu image untuk app/worker/scheduler/migrate, secret, migrasi aman, log, worker yang berhenti dengan benar, update, rollback, jebakan DNS nginx, backup/restore Postgres, dan rootfs read-only.
order: 11
version: 1
status: verified
updated: '2026-11-23'
tested_on:
  - Docker Desktop (Windows) — Docker Engine 29.4.1, Compose v5.1.3
  - Aplikasi devops-dojo (Laravel 13, PHP 8.3-fpm-alpine), postgres:17-alpine, nginx:1.27-alpine
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: topology
    text: Menyusun stack production (db, migrate, app, worker, scheduler, nginx) dari satu image
  - id: config
    text: Mengatur konfigurasi production dan secret Laravel
  - id: migrate
    text: Menjalankan migrasi dengan aman
  - id: workers
    text: Menjalankan queue worker dan scheduler dengan benar (sinyal, grace period)
  - id: release
    text: Melakukan update dan rollback dengan tag image
  - id: proxy
    text: Menghubungkan nginx ke php-fpm dan menghindari jebakan DNS upstream
  - id: data
    text: Backup dan restore database Postgres
  - id: harden
    text: Menerapkan rootfs read-only dan hardening pada container Laravel
sources:
  - title: Laravel — Deployment
    url: https://laravel.com/docs/deployment
  - title: Laravel — Configuration (caching, APP_KEY, debug mode)
    url: https://laravel.com/docs/configuration
  - title: Laravel — Queues (Running the queue worker, Supervisor, timeouts, pcntl)
    url: https://laravel.com/docs/queues#running-the-queue-worker
  - title: Laravel — Task Scheduling (schedule:work, onOneServer)
    url: https://laravel.com/docs/scheduling
  - title: Laravel — Migrations (--force, --isolated)
    url: https://laravel.com/docs/migrations#running-migrations
  - title: Laravel — Logging (stderr channel)
    url: https://laravel.com/docs/logging
  - title: Docker Official Images — php (STOPSIGNAL SIGQUIT, docker-php-ext-install)
    url: https://hub.docker.com/_/php
  - title: Compose file reference — depends_on (condition, restart)
    url: https://docs.docker.com/reference/compose-file/services/#depends_on
  - title: Compose file reference — stop_grace_period, stop_signal, init
    url: https://docs.docker.com/reference/compose-file/services/#stop_grace_period
  - title: nginx — resolver directive
    url: https://nginx.org/en/docs/http/ngx_http_core_module.html#resolver
  - title: PostgreSQL — pg_dump
    url: https://www.postgresql.org/docs/17/app-pgdump.html
  - title: PostgreSQL — pg_restore
    url: https://www.postgresql.org/docs/17/app-pgrestore.html
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: command
    objective: migrate
    prompt: Jalankan migrasi database Laravel **sekali pakai** dengan Compose, memakai image service `app` (container dihapus setelah selesai).
    answers: ['docker compose run --rm app php artisan migrate --force']
    patterns: ['^docker compose run --rm (--no-deps )?app php artisan migrate --force( --no-interaction| --isolated)*$']
    tests:
      pass: ['docker compose run --rm app php artisan migrate --force --no-interaction']
      fail: ['docker compose exec app php artisan migrate', 'docker compose run app php artisan migrate --force', 'docker compose run --rm app php artisan migrate']
    explanation: 'Di lab, service `migrate` dengan `command: ["php","artisan","migrate","--force"]` menghasilkan `Creating migration table ... DONE` dan `0001_01_01_000000_create_users_table ... DONE`, lalu `exited 0`. `run --rm` cocok untuk migrasi manual. Service khusus + `service_completed_successfully` (soal a06) cocok untuk deploy otomatis.'
    source: https://laravel.com/docs/migrations#running-migrations

  - id: a02
    tier: A
    type: text
    objective: migrate
    prompt: Opsi `php artisan migrate` yang wajib saat `APP_ENV=production` agar tidak berhenti meminta konfirmasi?
    answers: ['--force']
    tests:
      fail: ['--yes', '-y', '--no-interaction', '--production']
    explanation: 'Di production, Laravel menolak migrasi tanpa konfirmasi. Di container tidak ada yang bisa mengetik "yes", sehingga tanpa `--force` perintahnya batal dan deploy terhenti. `--no-interaction` saja tidak cukup.'
    source: https://laravel.com/docs/migrations#forcing-migrations-to-run-in-production

  - id: a03
    tier: A
    type: text
    objective: config
    prompt: Nilai `LOG_CHANNEL` agar log Laravel muncul di `docker logs` (bukan di file `storage/logs`)?
    answers: ['stderr']
    tests:
      fail: ['stack', 'single', 'daily', 'stdout', 'syslog']
    explanation: 'Di lab, dengan `LOG_CHANNEL=stderr`, tulisan ke stderr dari worker php-fpm muncul di `docker compose logs app`, karena image php resmi mengaktifkan `catch_workers_output`. Log di file `storage/logs` hilang saat container diganti, tidak ikut rotasi log Docker, dan memakan disk volume.'
    source: https://laravel.com/docs/logging

  - id: a04
    tier: A
    type: text
    objective: config
    prompt: Nilai `APP_DEBUG` untuk production?
    answers: ['false']
    tests:
      fail: ['true', '0', 'off', 'production']
    explanation: 'Dengan `APP_DEBUG=true`, halaman error menampilkan stack trace, query, dan nilai environment (termasuk password) ke siapa pun yang memicu error. Di lab, `app.env` production memakai `APP_ENV=production` + `APP_DEBUG=false`.'
    source: https://laravel.com/docs/configuration#debug-mode

  - id: a05
    tier: A
    type: command
    objective: config
    prompt: Generate nilai `APP_KEY` baru dan **tampilkan saja** (tanpa menulis ke file `.env`), menggunakan artisan.
    answers: ['php artisan key:generate --show']
    patterns: ['^(docker compose (run --rm|exec) app )?php artisan key:generate --show$']
    tests:
      pass: ['docker compose run --rm app php artisan key:generate --show']
      fail: ['php artisan key:generate', 'php artisan key:show', 'openssl rand 32']
    explanation: 'Hasilnya `base64:...` disimpan sebagai secret di server/CI. Di lab, entrypoint menolak start bila kosong: `APP_KEY belum diset. Buat dengan: php artisan key:generate --show` (exit 1). Jangan ganti `APP_KEY` di app yang sudah jalan, karena semua session, cookie terenkripsi, dan data `encrypt()` tidak bisa dibaca lagi.'
    source: https://laravel.com/docs/encryption#configuration

  - id: a06
    tier: A
    type: text
    objective: migrate
    prompt: Nilai `condition` di `depends_on` agar `app` baru start setelah service one-off `migrate` **selesai dengan exit 0**?
    answers: ['service_completed_successfully']
    tests:
      fail: ['service_healthy', 'service_started', 'completed', 'service_exited']
    explanation: 'Di lab: `migrate exited 0`, lalu `app`, `worker`, dan `scheduler` start. Bila migrasi gagal (exit ≠ 0), Compose tidak men-start app versi baru yang bergantung pada skema baru. Itu jauh lebih aman daripada app yang jalan dengan skema setengah jadi.'
    source: https://docs.docker.com/reference/compose-file/services/#depends_on

  - id: a07
    tier: A
    type: text
    objective: proxy
    prompt: Path route health bawaan Laravel 11+ (didaftarkan di `bootstrap/app.php` lewat `health:`)?
    answers: ['/up', 'up']
    tests:
      fail: ['/health', '/ping', '/status', '/healthz']
    explanation: 'Di lab, `GET /up` lewat nginx → `200`. Route ini mem-boot aplikasi lengkap, sehingga lebih bermakna daripada ping php-fpm (`/ping` → `pong`), yang hanya membuktikan php-fpm hidup. Pakai `/up` untuk monitoring eksternal, dan ping fpm untuk healthcheck container.'
    source: https://laravel.com/docs/routing#health-check

  - id: a08
    tier: A
    type: command
    objective: data
    prompt: Buat backup database `dojo` (user `dojo`) dari service `db` dalam **format custom** ke file `dojo.dump` di host.
    answers: ['docker compose exec -T db pg_dump -U dojo -d dojo -Fc > dojo.dump']
    patterns: ['^docker compose exec -T db pg_dump (?=.*(-U|--username)[= ]?dojo( |$))(?=.*(-F ?c|--format[= ]c(ustom)?)( |$))((-U|--username)[= ]?dojo|(-d|--dbname)[= ]?dojo|-F ?c|--format[= ]c(ustom)?) ((-U|--username)[= ]?dojo |(-d|--dbname)[= ]?dojo |-F ?c |--format[= ]c(ustom)? )*(dojo )?>dojo\.dump$']
    tests:
      pass: ['docker compose exec -T db pg_dump -Fc -U dojo dojo > dojo.dump', 'docker compose exec -T db pg_dump -U dojo --format=custom -d dojo >dojo.dump']
      fail: ['docker compose exec db pg_dump -U dojo -d dojo -Fc > dojo.dump', 'docker compose exec -T db pg_dump -U dojo -d dojo > dojo.dump', 'docker compose exec -T db pg_dumpall -U dojo > dojo.dump']
    explanation: 'Di lab: file 35 KB, diawali signature `PGDMP` (format custom). Format custom terkompresi dan bisa di-restore selektif dengan `pg_restore`. `-T` wajib (soal berikutnya). Backup harus disimpan di luar server, dan baru berarti bila restore-nya pernah diuji.'
    source: https://www.postgresql.org/docs/17/app-pgdump.html

  - id: a09
    tier: A
    type: text
    objective: data
    prompt: Kenapa `docker compose exec` untuk `pg_dump > file` harus memakai `-T`?
    answers: ['mematikan pseudo-TTY agar output biner tidak rusak']
    patterns: ['(tty|terminal)']
    tests:
      pass: ['tanpa tty', 'menonaktifkan pseudo-tty', 'agar tidak dialokasikan terminal']
      fail: ['agar lebih cepat', 'untuk timeout', 'supaya jalan di background']
    explanation: '`compose exec` mengalokasikan TTY secara default. TTY mengubah byte (mis. `\n` → `\r\n`) dan mencampur stream, sehingga dump biner rusak dan baru ketahuan saat restore gagal. `-T` juga diperlukan untuk menjalankan perintah dari cron/CI yang tidak punya terminal.'
    source: https://docs.docker.com/reference/cli/docker/compose/exec/

  - id: a10
    tier: A
    type: text
    objective: workers
    prompt: Extension PHP yang dibutuhkan `queue:work` untuk menangani sinyal (berhenti setelah job selesai, timeout job)?
    answers: ['pcntl']
    tests:
      fail: ['posix', 'sockets', 'opcache', 'redis', 'signal']
    explanation: 'Image php resmi tidak memasang `pcntl` secara default. Di lab, image tanpa `pcntl` → worker di-stop setelah 16 detik dengan exit **137** (dibunuh di tengah job). Dengan `docker-php-ext-install pcntl` → berhenti dalam 0 detik, exit **0**. Tanpa pcntl, `--timeout` job juga tidak bekerja.'
    source: https://laravel.com/docs/queues#timeout

  - id: a11
    tier: A
    type: command
    objective: release
    prompt: 'Deploy versi `v1.2.0` (compose memakai `image: ...:${TAG:?}`), dan tunggu sampai semua service running/healthy.'
    answers: ['TAG=v1.2.0 docker compose up -d --wait']
    patterns: ['^(export )?TAG=v1\.2\.0( &&|;)? docker compose up ((-d|--detach|--wait|--remove-orphans) )*(-d|--detach|--wait|--remove-orphans)$']
    tests:
      pass: ['TAG=v1.2.0 docker compose up --wait -d', 'TAG=v1.2.0 docker compose up -d --wait --remove-orphans']
      fail: ['docker compose up -d --wait', 'TAG=v1.2.0 docker compose restart', 'docker compose up -d v1.2.0']
    explanation: 'Di lab, `TAG=2 docker compose up -d --wait` me-recreate `migrate`, `app`, `worker`, dan `scheduler` (image berubah), menjalankan migrasi dulu, lalu menunggu healthy, total 10 detik. `db` dan `nginx` tidak disentuh. Exit code ≠ 0 dari `--wait` bisa dipakai CI untuk menandai deploy gagal.'
    source: https://docs.docker.com/reference/cli/docker/compose/up/

  - id: a12
    tier: A
    type: text
    objective: workers
    prompt: Perintah artisan untuk menjalankan scheduler di foreground (tanpa cron), cocok sebagai command container?
    answers: ['php artisan schedule:work', 'schedule:work']
    tests:
      fail: ['php artisan schedule:run', 'php artisan cron', 'php artisan queue:work', 'php artisan schedule:list']
    explanation: '`schedule:run` dirancang untuk dipanggil cron setiap menit. `schedule:work` berjalan terus dan memanggilnya setiap menit, sehingga cocok menjadi PID 1 sebuah container. Di lab, service `scheduler` memakai image yang sama dengan `app`, hanya command-nya yang berbeda.'
    source: https://laravel.com/docs/scheduling#running-the-scheduler-locally

  - id: a13
    tier: A
    type: command
    objective: proxy
    prompt: Muat ulang konfigurasi nginx di service `nginx` **tanpa** me-restart container.
    answers: ['docker compose exec nginx nginx -s reload']
    patterns: ['^docker compose exec (-T )?nginx nginx -s reload$']
    tests:
      pass: ['docker compose exec -T nginx nginx -s reload']
      fail: ['docker compose restart nginx', 'docker compose exec nginx reload', 'docker compose exec nginx systemctl reload nginx']
    explanation: 'Di lab: `signal process started`, lalu 502 berubah menjadi 200, karena reload membuat nginx me-resolve ulang nama `app`. Ini perbaikan darurat untuk jebakan DNS upstream (tier B). Perbaikan permanennya adalah `resolver` atau `depends_on.restart`.'
    source: https://nginx.org/en/docs/control.html

  - id: a14
    tier: A
    type: text
    objective: proxy
    prompt: Direktori Laravel yang harus menjadi `root` nginx (satu-satunya yang boleh terekspos ke web)?
    answers: ['public', '/var/www/html/public']
    patterns: ['^(/var/www/html/)?public/?$']
    tests:
      pass: ['/var/www/html/public/']
      fail: ['/var/www/html', 'storage', 'app', 'resources']
    explanation: 'Bila `root` diarahkan ke folder project, `.env`, `storage/`, dan `vendor/` bisa diunduh. Di lab, `root /var/www/html/public` + `location ~ /\.(?!well-known) { deny all; }` → `GET /.env` → `403`. File upload publik diekspos lewat `location /storage/ { alias ... }`, bukan dengan memindahkan root.'
    source: https://laravel.com/docs/deployment#nginx

  - id: a15
    tier: A
    type: command
    objective: data
    prompt: Restore `dojo.dump` (format custom) ke database `dojo` di service `db`, **menghapus objek yang sudah ada** lebih dulu tanpa error bila belum ada.
    answers: ['docker compose exec -T db pg_restore -U dojo -d dojo --clean --if-exists < dojo.dump']
    patterns: ['^docker compose exec -T db pg_restore (?=.*(-U|--username)[= ]?dojo( |$))(?=.*(-d|--dbname)[= ]?dojo( |$))(?=.*(--clean|-c)( |$))(?=.*--if-exists( |$))(((-U|--username)[= ]?dojo|(-d|--dbname)[= ]?dojo|--clean|-c|--if-exists|--no-owner|-O) )+<dojo\.dump$']
    tests:
      pass: ['docker compose exec -T db pg_restore --clean --if-exists --no-owner -U dojo -d dojo < dojo.dump']
      fail: ['docker compose exec -T db pg_restore -U dojo -d dojo < dojo.dump', 'docker compose exec -T db psql -U dojo -d dojo < dojo.dump', 'docker compose exec db pg_restore -U dojo -d dojo --clean --if-exists < dojo.dump']
    explanation: 'Di lab, restore ke database yang sudah berisi tanpa `--clean` → `pg_restore: warning: errors ignored on restore: 63`. Restore ke database **kosong** → rc=0, `users=1 migrations=8`, sesuai data asli. Dump format custom tidak bisa dibaca `psql`.'
    source: https://www.postgresql.org/docs/17/app-pgrestore.html

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: workers
    prompt: Image `php:*-fpm` resmi menetapkan `STOPSIGNAL` apa, yang juga diwarisi container worker/scheduler dari image yang sama?
    answers: ['SIGQUIT', 'QUIT']
    tests:
      fail: ['SIGTERM', 'SIGKILL', 'SIGINT', 'SIGHUP']
    explanation: 'Di lab: `image StopSignal=SIGQUIT`, dan container worker juga `StopSignal=SIGQUIT`. Untuk php-fpm, SIGQUIT berarti graceful shutdown. Worker Laravel dengan `pcntl` juga menangani SIGQUIT. Tanpa `pcntl` dan sebagai PID 1, sinyal itu diabaikan. Bisa diganti per service dengan `stop_signal: SIGTERM`.'
    source: https://hub.docker.com/_/php

  - id: b02
    tier: B
    type: text
    objective: workers
    prompt: 'Worker `php artisan queue:work` (PID 1, **tanpa** `pcntl`) di-stop dengan `docker compose stop -t 15 worker`. Exit code-nya?'
    answers: ['137']
    tests:
      fail: ['0', '143', '131', '1']
    explanation: 'Di lab: `took 16s exit=137`. PID 1 tanpa handler mengabaikan sinyal stop, Docker menunggu 15 detik, lalu SIGKILL. Job yang sedang berjalan terputus di tengah jalan (email terkirim dua kali, pembayaran setengah diproses). Dengan `init: true` → `exit=131` dalam 1 detik (SIGQUIT diteruskan tini, tetapi job tetap terputus).'
    source: https://laravel.com/docs/queues#queue-workers-and-deployment

  - id: b03
    tier: B
    type: text
    objective: workers
    prompt: Worker yang sama setelah `pcntl` dipasang, di-stop dengan cara yang sama. Exit code-nya?
    answers: ['0']
    tests:
      fail: ['137', '143', '131', '1']
    explanation: 'Di lab: `took 0s exit=0`. Laravel menangkap sinyal, menyelesaikan job yang sedang berjalan, lalu keluar bersih. Perbedaannya hanya satu baris Dockerfile (`docker-php-ext-install pcntl`). Pasangkan dengan `stop_grace_period` yang lebih panjang dari job terlama (soal berikutnya).'
    source: https://laravel.com/docs/queues#timeout

  - id: b04
    tier: B
    type: text
    objective: workers
    prompt: Key Compose untuk memberi worker waktu menyelesaikan job sebelum di-SIGKILL saat stop/deploy?
    answers: ['stop_grace_period']
    tests:
      fail: ['stop_timeout', 'grace_period', 'timeout', 'stop_signal']
    explanation: 'Contoh: `stop_grace_period: 120s`, sedikit lebih besar dari `--timeout` job terlama. Di Docker Desktop lab, nilai bawaan container (`StopTimeout=1`) bahkan lebih pendek dari default 10 detik di Engine Linux. Jangan bergantung pada default.'
    source: https://docs.docker.com/reference/compose-file/services/#stop_grace_period

  - id: b05
    tier: B
    type: text
    objective: proxy
    prompt: Setelah container `app` dibuat ulang dan mendapat **IP baru**, nginx (`fastcgi_pass app:9000;` statis) mengembalikan status HTTP berapa?
    answers: ['502']
    tests:
      fail: ['200', '504', '404', '503']
    explanation: 'Di lab: app pindah dari `172.18.0.5` ke `172.18.0.7`, lalu `502 502 502` dengan log `connect() failed (111: Connection refused) while connecting to upstream`. nginx me-resolve nama upstream **sekali saat start** dan terus memakai IP lama. Rollback berikutnya pun kembali 502 karena IP berubah lagi. IP sering kebetulan sama (lab update pertama: tetap `.5`), sehingga bug ini muncul acak.'
    source: https://nginx.org/en/docs/http/ngx_http_core_module.html#resolver

  - id: b06
    tier: B
    type: text
    objective: proxy
    prompt: 'Direktif nginx (dengan alamat DNS internal Docker) yang, digabung dengan `set $php_upstream app:9000; fastcgi_pass $php_upstream;`, membuat nginx me-resolve ulang nama upstream?'
    answers: ['resolver 127.0.0.11']
    patterns: ['^resolver 127\.0\.0\.11( valid=\d+s)?( ipv6=off)?;?$']
    tests:
      pass: ['resolver 127.0.0.11 valid=10s;', 'resolver 127.0.0.11 valid=10s ipv6=off;']
      fail: ['resolver 8.8.8.8', 'proxy_pass app', 'upstream app', 'resolver 127.0.0.1']
    explanation: '`127.0.0.11` adalah DNS embedded Docker di network user-defined (modul Networking). Dengan `resolver` + variabel, nginx me-resolve saat runtime sesuai `valid`. Di lab, setelah app pindah IP → tetap `200 200 200`. Tanpa variabel, `resolver` saja tidak mengubah apa pun untuk `fastcgi_pass` statis.'
    source: https://nginx.org/en/docs/http/ngx_http_core_module.html#resolver

  - id: b07
    tier: B
    type: text
    objective: proxy
    prompt: Opsi di `depends_on` (Compose) yang membuat `nginx` di-restart otomatis saat Compose meng-update service `app`?
    answers: ['restart: true']
    patterns: ['^restart:\s*true$']
    tests:
      pass: ['restart:true']
      fail: ['restart: always', 'restart: unless-stopped', 'condition: service_restarted', 'recreate: true']
    explanation: 'Di lab, dengan `depends_on: { app: { condition: service_healthy, restart: true } }`, `up -d` versi baru menghasilkan `nginx Stopping → Started → Healthy`, lalu `200 200 200`. Ini berbeda dari `restart:` di level service (restart policy). Alternatifnya: `resolver` di nginx (soal sebelumnya), yang tidak membuat nginx restart.'
    source: https://docs.docker.com/reference/compose-file/services/#depends_on

  - id: b08
    tier: B
    type: text
    objective: config
    prompt: Setelah entrypoint menjalankan `php artisan config:cache`, apakah nilai `DB_PASSWORD` tersimpan **plaintext** di `bootstrap/cache/config.php` di dalam container? (ya/tidak)
    answers: ['ya', 'yes']
    tests:
      fail: ['tidak', 'no']
    explanation: 'Di lab, `grep -c <password> bootstrap/cache/config.php` → `4`, walaupun password datang dari secret file (`/run/secrets/db_password`) dan tidak ada di env proses `exec`. Konsekuensinya: **jangan pernah** `config:cache` saat `docker build` (secret ikut tertanam di layer image dan registry). Lakukan saat start (entrypoint), dan perlakukan filesystem container sebagai sensitif.'
    source: https://laravel.com/docs/configuration#configuration-caching

  - id: b09
    tier: B
    type: text
    objective: config
    prompt: '`docker compose exec app php artisan tinker --execute=''logger()->error("x")''` dengan `LOG_CHANNEL=stderr`. Apakah log itu muncul di `docker compose logs app`? (ya/tidak)'
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab, log tampil di terminal `exec` (`production.ERROR: dojo-exec-log`), tetapi `docker compose logs app | grep -c` → `0`. `docker logs` hanya menangkap output PID 1 dan turunannya (php-fpm master + worker). Artisan yang dijalankan manual lewat `exec` adalah proses terpisah. Hasilnya hanya di terminalmu, jadi simpan bila perlu.'
    source: https://docs.docker.com/engine/logging/

  - id: b10
    tier: B
    type: text
    objective: harden
    prompt: 'Service app dengan `read_only: true` + tmpfs `/tmp` gagal start (`Read-only file system`) saat entrypoint membuat cache. Dua path Laravel apa yang perlu tmpfs (milik www-data)?'
    answers: ['bootstrap/cache dan storage/framework/views']
    patterns: ['(?=.*bootstrap/cache)(?=.*storage/framework/views)']
    tests:
      pass: ['/var/www/html/bootstrap/cache, /var/www/html/storage/framework/views', 'storage/framework/views dan bootstrap/cache']
      fail: ['bootstrap/cache', 'storage', '/tmp dan /var/run']
    explanation: 'Di lab:
      - Hanya `/tmp` → `Read-only file system` (config:cache).
      - Ditambah `bootstrap/cache` → config & route `cached successfully`, tetapi view:cache tetap gagal.
      - Ditambah `storage/framework/views` → sukses.

      Pakai opsi `uid=82,gid=82` agar www-data bisa menulis. Session, cache, dan queue di database, ditambah log ke stderr, membuat `storage/` lain tidak perlu writable. Upload ke volume `storage/app`.'
    source: https://docs.docker.com/reference/compose-file/services/#tmpfs

  - id: b11
    tier: B
    type: text
    objective: migrate
    prompt: 'Semua replica app menjalankan `migrate --force` saat start (`RUN_MIGRATIONS=true`, `replicas: 3`). Opsi `migrate` apa (Laravel 11+) yang memakai cache lock agar hanya satu yang benar-benar menjalankan migrasi?'
    answers: ['--isolated']
    tests:
      fail: ['--force', '--lock', '--once', '--step']
    explanation: '`--isolated` mengambil atomic lock lewat cache store (database/redis, bukan `file`/`array` per container). Pola yang lebih sederhana dan dipakai di lab: satu service `migrate` one-off, dengan app menunggu lewat `service_completed_successfully`. Dengan begitu migrasi berjalan tepat sekali, terlihat di log, dan bisa menggagalkan deploy.'
    source: https://laravel.com/docs/migrations#running-migrations

  - id: b12
    tier: B
    type: text
    objective: release
    prompt: Rilis v2 menambah migrasi yang **menghapus** kolom. Lalu kamu rollback image ke v1 (`TAG=v1 docker compose up -d`). Apakah skema database ikut kembali? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Rollback image hanya mengganti kode. Di lab, rollback `TAG=1` selesai dalam 9 detik, dan tabel/data tetap sesuai migrasi terakhir. v1 yang membaca kolom yang sudah dihapus akan error. Karena itu migrasi harus **backward-compatible** (expand → deploy → contract di rilis berikutnya), dan backup dibuat sebelum migrasi yang destruktif.'
    source: https://laravel.com/docs/migrations#rolling-back-migrations

  - id: b13
    tier: B
    type: text
    objective: release
    prompt: Dengan **satu** container app, apakah `docker compose up -d` versi baru berjalan tanpa downtime sama sekali? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab, curl setiap 0.2 detik selama update: `147× 200`, `3× 000` (gagal/timeout). Compose menghentikan container lama sebelum yang baru siap. Untuk mayoritas aplikasi kecil, kurang dari 1 detik itu bisa diterima. Zero-downtime sungguhan butuh blue/green (dua project + switch di reverse proxy) atau orkestrator (Swarm/Kubernetes rolling update).'
    source: https://docs.docker.com/reference/cli/docker/compose/up/

  - id: b14
    tier: B
    type: text
    objective: workers
    prompt: Berapa replica service `scheduler` (`schedule:work`) yang seharusnya dijalankan agar task tidak dieksekusi ganda?
    answers: ['1', 'satu']
    tests:
      fail: ['2', '3', 'sebanyak app', '0']
    explanation: 'Setiap instance `schedule:work` menjalankan semua task yang jatuh tempo. Dua replica berarti laporan terkirim dua kali. Bila memang butuh lebih dari satu server, pakai `->onOneServer()` (butuh cache lock bersama). Worker queue sebaliknya bisa diskalakan bebas, karena setiap job hanya diambil satu worker.'
    source: https://laravel.com/docs/scheduling#running-tasks-on-one-server

  - id: b15
    tier: B
    type: text
    objective: release
    prompt: Setelah deploy kode baru, kenapa container `worker` juga **harus** dibuat ulang/di-restart, walaupun `app` (php-fpm) sudah versi baru?
    answers: ['worker adalah proses long-running yang menyimpan kode lama di memori']
    patterns: ['(memori|memory|long[- ]?running|kode lama|tetap jalan|tidak (reload|memuat ulang)|boot sekali|daemon)']
    tests:
      pass: ['kode lama masih di memori', 'proses long-running tidak reload kode', 'worker boot sekali dan tetap jalan']
      fail: ['agar database ter-migrasi', 'karena port berubah', 'supaya log bersih']
    explanation: '`queue:work` mem-boot framework sekali, lalu memproses job terus-menerus dengan kode di memori. Di lab, `up -d` dengan TAG baru otomatis me-recreate `worker` dan `scheduler`, karena image yang sama dengan app ikut berubah. Di luar container, ini setara dengan `php artisan queue:restart`. `--max-time=3600` juga membuat worker berganti berkala.'
    source: https://laravel.com/docs/queues#queue-workers-and-deployment

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: topology
    prompt: |
      Tulis `compose.yaml` production untuk Laravel + Postgres: db, migrate (one-off), app (php-fpm), worker, scheduler,
      nginx. Satu image untuk semua service PHP, tag wajib diisi, password DB sebagai secret, nginx hanya di localhost
      (di belakang reverse proxy TLS). Tulis YAML-nya (cukup bagian penting).
    checklist:
      - id: tag
        label: Tag image wajib (${TAG:?}), bukan latest
        required: true
        patterns: ['\$\{TAG:\?']
      - id: migrate
        label: Service migrate menjalankan migrate --force
        required: true
        patterns: ['migrate.*--force', '"--force"']
      - id: completed
        label: app/worker menunggu service_completed_successfully
        required: true
        patterns: ['service_completed_successfully']
      - id: worker
        label: Worker queue:work + scheduler schedule:work
        patterns: ['queue:work']
      - id: scheduler
        label: Scheduler schedule:work
        patterns: ['schedule:work']
      - id: secret
        label: Password DB lewat secrets / *_FILE
        patterns: ['^secrets:', 'password_file', '/run/secrets/']
      - id: db-health
        label: db healthcheck (pg_isready) + service_healthy
        patterns: ['pg_isready', 'service_healthy']
      - id: localhost
        label: Port nginx hanya 127.0.0.1
        patterns: ['127\.0\.0\.1:\d+:\d+']
      - id: volume
        label: Volume untuk data Postgres
        patterns: [':/var/lib/postgresql/data']
      - id: grace
        label: stop_grace_period untuk worker
        patterns: ['stop_grace_period']
      - id: no-latest
        label: Tidak memakai latest / default latest
        forbid: true
        patterns: [':latest\b', '\$\{TAG:-latest\}']
      - id: no-public-db
        label: Tidak mem-publish port Postgres ke publik
        forbid: true
        patterns: ['"?(0\.0\.0\.0:)?5432:5432"?']
    pass_score: 0.5
    solution: |
      ```yaml
      name: trainme
      x-app: &app
        image: ghcr.io/acme/dojo-app:${TAG:?set TAG}
        env_file: app.env                     # APP_ENV=production, APP_DEBUG=false, LOG_CHANNEL=stderr, APP_KEY=...
        environment: { DB_PASSWORD_FILE: /run/secrets/db_password }
        secrets: [db_password]
        volumes: [storage:/var/www/html/storage/app]
        networks: [internal]
        restart: unless-stopped
      services:
        db:
          image: postgres:17-alpine
          environment: { POSTGRES_DB: dojo, POSTGRES_USER: dojo, POSTGRES_PASSWORD_FILE: /run/secrets/db_password }
          secrets: [db_password]
          volumes: [pgdata:/var/lib/postgresql/data]
          networks: [internal]
          healthcheck: { test: ["CMD-SHELL", "pg_isready -U dojo -d dojo"], interval: 5s, retries: 10 }
        migrate:
          <<: *app
          command: ["php", "artisan", "migrate", "--force"]
          restart: "no"
          depends_on: { db: { condition: service_healthy } }
        app:
          <<: *app
          depends_on: { migrate: { condition: service_completed_successfully } }
        worker:
          <<: *app
          command: ["php", "artisan", "queue:work", "--tries=3", "--timeout=90", "--max-time=3600"]
          stop_grace_period: 120s
          healthcheck: { disable: true }
          depends_on: { migrate: { condition: service_completed_successfully } }
        scheduler:
          <<: *app
          command: ["php", "artisan", "schedule:work"]
          healthcheck: { disable: true }
          depends_on: { migrate: { condition: service_completed_successfully } }
        nginx:
          image: ghcr.io/acme/dojo-nginx:${TAG:?set TAG}
          ports: ["127.0.0.1:8080:80"]
          volumes: [storage:/var/www/html/storage/app:ro]
          networks: [internal]
          restart: unless-stopped
          depends_on: { app: { condition: service_healthy, restart: true } }
      secrets: { db_password: { file: ./secrets/db_password.txt } }
      volumes: { pgdata: {}, storage: {} }
      networks: { internal: {} }
      ```
      Stack ini identik dengan lab: `up -d --wait` selesai 12 detik, `migrate exited 0`, `/up` → 200, `/.env` → 403.

      Laravel tidak membaca `DB_PASSWORD_FILE` sendiri. Entrypoint perlu menambahkan:
      `[ -f "${DB_PASSWORD_FILE:-}" ] && export DB_PASSWORD="$(cat "$DB_PASSWORD_FILE")"` sebelum `config:cache`.

      Image harus memasang `pcntl` agar `stop_grace_period` benar-benar dipakai worker untuk menyelesaikan job.
    tests:
      pass:
        - |
          x-app: &app
            image: ghcr.io/acme/dojo-app:${TAG:?set TAG}
            secrets: [db_password]
            environment: { DB_PASSWORD_FILE: /run/secrets/db_password }
          services:
            db:
              image: postgres:17-alpine
              volumes: [pgdata:/var/lib/postgresql/data]
              healthcheck: { test: ["CMD-SHELL", "pg_isready -U dojo"] }
            migrate:
              <<: *app
              command: ["php", "artisan", "migrate", "--force"]
              depends_on: { db: { condition: service_healthy } }
            app:
              <<: *app
              depends_on: { migrate: { condition: service_completed_successfully } }
            worker:
              <<: *app
              command: ["php", "artisan", "queue:work"]
              stop_grace_period: 120s
            scheduler:
              <<: *app
              command: ["php", "artisan", "schedule:work"]
            nginx:
              ports: ["127.0.0.1:8080:80"]
        - |
          image: myapp:${TAG:?}
          command: php artisan migrate --force
          condition: service_completed_successfully
          command: php artisan queue:work
          command: php artisan schedule:work
      fail:
        - |
          image: myapp:${TAG:-latest}
          command: php artisan migrate --force
          condition: service_completed_successfully
        - |
          image: myapp:${TAG:?}
          command: php artisan migrate --force
          condition: service_completed_successfully
          ports: ["5432:5432"]
        - |
          image: myapp:latest
          command: php artisan queue:work
    source: https://laravel.com/docs/deployment

  - id: c02
    tier: C
    type: scenario
    objective: release
    prompt: |
      Tulis runbook deploy rilis `v1.4.0` yang berisi migrasi baru, di server dengan stack Compose di atas (satu
      perintah/poin per baris): persiapan, backup, deploy, verifikasi, dan rencana rollback bila gagal.
    checklist:
      - id: config
        label: Validasi konfigurasi (compose config) dengan TAG baru
        patterns: ['docker\s+compose\s+.*config']
      - id: pull
        label: Pull image lebih dulu
        patterns: ['docker\s+compose\s+.*pull']
      - id: backup
        label: Backup DB sebelum migrasi (pg_dump -T)
        required: true
        patterns: ['pg_dump']
      - id: deploy
        label: Deploy dengan TAG + up -d --wait
        required: true
        patterns: ['tag=v?1\.4\.0.*docker\s+compose\s+.*up\b', 'docker\s+compose\s+.*up\s+.*--wait']
      - id: verify
        label: Verifikasi (ps, logs migrate, curl /up)
        required: true
        patterns: ['curl\s+.*/up\b', 'docker\s+compose\s+(ps|logs)\b']
      - id: rollback
        label: Rollback ke tag sebelumnya
        required: true
        patterns: ['tag=v?1\.3\.\d+', 'rollback', 'tag\s+(lama|sebelumnya)']
      - id: schema
        label: Menyadari rollback image tidak me-rollback skema (migrasi backward-compatible / restore)
        patterns: ['backward', 'kompatibel', 'compatible', 'pg_restore', 'skema']
      - id: no-down-v
        label: Tidak memakai down -v / menghapus volume
        forbid: true
        patterns: ['down\s+-v\b', 'down\s+--volumes', 'volume\s+(rm|prune)']
      - id: no-latest
        label: Tidak deploy latest
        forbid: true
        patterns: ['tag=latest', ':latest\b']
    pass_score: 0.5
    solution: |
      ```bash
      export TAG=v1.4.0 PREV=v1.3.2
      docker compose config -q                                  # TAG terisi, YAML valid
      docker compose pull                                       # download dulu, downtime tidak menunggu jaringan
      docker compose exec -T db pg_dump -U dojo -d dojo -Fc > backups/dojo-$(date +%F-%H%M)-pre-$TAG.dump
      docker compose up -d --wait --remove-orphans              # migrate → app/worker/scheduler; nginx restart (depends_on.restart)
      docker compose ps -a                                      # migrate exited 0? semua healthy?
      docker compose logs --no-log-prefix migrate               # migrasi apa saja yang jalan
      curl -fsS -o /dev/null -w '%{http_code}\n' https://dojo.example.com/up
      # gagal → rollback kode:
      TAG=$PREV docker compose up -d --wait
      # skema TIDAK ikut kembali; bila migrasi tidak backward-compatible → restore:
      #   docker compose exec -T db pg_restore -U dojo -d dojo --clean --if-exists < backups/dojo-...-pre-v1.4.0.dump
      ```
      Temuan lab yang menjadi dasar setiap langkah:
      - Update dengan `--wait` selesai 10 detik, dan hanya ~0.6 detik request gagal.
      - Rollback 9 detik.
      - nginx bisa 502 setelah IP app berubah, sehingga dibutuhkan `depends_on.restart` atau `resolver`.
      - `pg_restore` tanpa `--clean` ke database berisi → 63 error.

      Backup diambil **sebelum** migrasi. Restore hanya untuk migrasi destruktif, dan semua data yang masuk setelah
      backup hilang, sehingga migrasi expand/contract tetap jalan terbaik.
    tests:
      pass:
        - |
          export TAG=v1.4.0
          docker compose config -q
          docker compose pull
          docker compose exec -T db pg_dump -U dojo -d dojo -Fc > pre-v1.4.0.dump
          docker compose up -d --wait
          docker compose ps -a
          curl -fsS https://dojo.example.com/up
          rollback: TAG=v1.3.2 docker compose up -d --wait
          migrasi harus backward-compatible karena skema tidak ikut rollback
        - |
          docker compose exec -T db pg_dump -U dojo -Fc dojo > backup.dump
          TAG=v1.4.0 docker compose up -d --wait
          docker compose logs migrate
          bila gagal TAG=v1.3.0 docker compose up -d --wait
      fail:
        - |
          docker compose down -v
          TAG=v1.4.0 docker compose up -d
        - |
          TAG=latest docker compose up -d --wait
          docker compose exec -T db pg_dump -U dojo dojo > b.dump
          curl /up
          rollback TAG=v1.3.0
        - |
          TAG=v1.4.0 docker compose up -d --wait
          curl https://dojo.example.com/up
    source: https://laravel.com/docs/deployment

  - id: c03
    tier: C
    type: scenario
    objective: workers
    prompt: |
      Setelah setiap deploy dua masalah muncul:
      - Kadang website 502 selama berjam-jam sampai ada yang me-restart nginx, padahal `app` healthy.
      - Beberapa job queue (kirim email) terputus di tengah dan tercatat dobel. `docker inspect` worker lama: `ExitCode 137`.

      Diagnosis keduanya dan tulis perbaikannya (satu poin/baris).
    checklist:
      - id: dns-cause
        label: 502 karena nginx menyimpan IP lama app (resolve sekali saat start)
        required: true
        patterns: ['(ip|dns|resolve).*(lama|cache|sekali|berubah|start)', '(lama|cache|sekali|berubah).*(ip|dns|resolve)']
      - id: dns-fix
        label: Fix resolver 127.0.0.11 + variabel / depends_on restart true / reload
        required: true
        patterns: ['resolver[ \t]+127\.0\.0\.11', 'restart:\s*true', 'nginx\s+-s\s+reload']
      - id: worker-cause
        label: 137 = worker tidak berhenti graceful (tanpa pcntl / sinyal diabaikan / grace terlalu pendek)
        required: true
        patterns: ['pcntl', 'sigquit|sigterm|sinyal|signal', 'sigkill']
      - id: pcntl
        label: Pasang ext pcntl
        patterns: ['docker-php-ext-install[ \t]+.*pcntl', 'pcntl']
      - id: grace
        label: stop_grace_period > durasi job terlama (dan --timeout)
        patterns: ['stop_grace_period']
      - id: idempotent
        label: Job dibuat idempotent / tries & timeout yang sesuai
        patterns: ['idempoten', '--timeout', '--tries', 'retry_after', 'unique']
      - id: no-hostnet
        label: Tidak memakai network_mode host / IP statis sebagai "solusi" utama
        forbid: true
        patterns: ['network_mode:\s*"?host']
    pass_score: 0.6
    solution: |
      ```text
      502:
        - nginx me-resolve "app" sekali saat start; setelah app di-recreate dengan IP baru, nginx tetap ke IP lama
          (lab: .5 → .7, "connect() failed (111: Connection refused) while connecting to upstream").
        - Fix A (nginx.conf): resolver 127.0.0.11 valid=10s ipv6=off; set $php_upstream app:9000; fastcgi_pass $php_upstream;
        - Fix B (compose): nginx.depends_on.app: { condition: service_healthy, restart: true }
      Job terputus (exit 137):
        - Image php-fpm → STOPSIGNAL SIGQUIT; php tanpa pcntl sebagai PID 1 mengabaikannya → SIGKILL setelah timeout
          (lab: 16 s, exit 137). Dengan pcntl: 0 s, exit 0, job diselesaikan dulu.
        - Dockerfile: RUN docker-php-ext-install pcntl
        - compose worker: stop_grace_period: 120s (> --timeout=90 job terlama)
        - Job pengiriman email dibuat idempotent (cek "sudah terkirim?") karena retry tetap bisa terjadi.
      ```
      Kedua masalah ini direproduksi di lab dan kedua perbaikannya terbukti:
      - `resolver` → tetap 200 setelah IP app berubah.
      - `depends_on.restart` → nginx di-restart otomatis, lalu 200.
      - `pcntl` → exit 0.

      Keduanya acak di production: IP sering kebetulan sama, dan job jarang sedang berjalan saat deploy. Karena itu
      masalah semacam ini lama tidak ketahuan.
    tests:
      pass:
        - |
          nginx menyimpan ip lama app karena resolve sekali saat start
          tambah resolver 127.0.0.11 valid=10s dan set $php_upstream app:9000
          worker exit 137 karena php tanpa pcntl mengabaikan SIGQUIT
          RUN docker-php-ext-install pcntl
          stop_grace_period: 120s
          job email dibuat idempotent
        - |
          dns di-cache nginx, ip app berubah setelah recreate
          depends_on app restart: true
          pcntl belum terpasang sehingga sinyal diabaikan lalu sigkill
      fail:
        - |
          restart nginx tiap deploy manual
          tambah memory worker
        - |
          ip lama di-cache nginx
          pakai network_mode: host
          pasang pcntl
        - |
          worker kehabisan memori, naikkan limit
          nginx error, ganti ke apache
    source: https://laravel.com/docs/queues#queue-workers-and-deployment

---

# Deploy Laravel dengan Docker Compose

Modul penutup ini menyatukan semua yang sudah dipelajari menjadi deployment Laravel yang bisa dipakai di server
sungguhan: satu image untuk semua peran PHP, migrasi yang aman, worker yang berhenti dengan benar, update dan
rollback berbasis tag, serta backup yang sudah diuji restore-nya. Semua angka di bawah berasal dari lab yang
menjalankan aplikasi devops-dojo ini sendiri.

> [!NOTE]
> Lab: image `app`/`nginx` di-build dari salinan project ini (Dockerfile versi perbaikan dari modul 03, 229 MB), stack
> lengkap dijalankan dengan Compose v5.1.3 di Docker Desktop. File project aslimu tidak diubah. Path secret dan
> perintah server (`systemctl`, `/etc/docker`) berlaku untuk server Linux.

## 1. Topologi

```mermaid
flowchart LR
    U[Reverse proxy TLS<br/>Caddy/Traefik/nginx host] -->|127.0.0.1:8080| N[nginx<br/>file statis + fastcgi]
    N -->|app:9000| A[app<br/>php-fpm]
    W[worker<br/>queue:work] --> DB[(db<br/>postgres)]
    S[scheduler<br/>schedule:work] --> DB
    A --> DB
    M[migrate<br/>one-off, exit 0] --> DB
    subgraph satu image: app:TAG
      A
      W
      S
      M
    end
```

| Service | Command | Catatan lab |
|---|---|---|
| `db` | (default) | Healthcheck `pg_isready`, volume `pgdata` |
| `migrate` | `php artisan migrate --force` | `restart: "no"`, `exited 0`, gerbang untuk service lain |
| `app` | `php-fpm` | Healthcheck ping fpm, `/up` → 200 |
| `worker` | `queue:work --tries=3 --max-time=3600` | Butuh `pcntl` + `stop_grace_period` |
| `scheduler` | `schedule:work` | **Satu** replica |
| `nginx` | (default) | `root .../public`, `/.env` → 403 |

`up -d --wait` sampai semua healthy: **12 detik**.

## 2. Konfigurasi production

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...            # php artisan key:generate --show, simpan sebagai secret
LOG_CHANNEL=stderr            # ke docker logs, bukan storage/logs
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
DB_HOST=db
```

Secret DB lewat file, dengan entrypoint yang mengubahnya menjadi env sebelum cache dibuat:

```sh
#!/bin/sh
set -eu
if [ -n "${DB_PASSWORD_FILE:-}" ] && [ -f "$DB_PASSWORD_FILE" ]; then
  DB_PASSWORD="$(cat "$DB_PASSWORD_FILE")"; export DB_PASSWORD
fi
[ -n "${APP_KEY:-}" ] || { echo "APP_KEY belum diset" >&2; exit 1; }
php artisan config:cache && php artisan route:cache && php artisan view:cache
exec "$@"
```

> [!WARNING]
> `config:cache` menulis semua nilai konfigurasi, **termasuk password**, ke `bootstrap/cache/config.php` (lab: 4
> kemunculan password, plaintext). Jalankan di entrypoint saat start, **jangan** di `docker build`, karena secret akan
> tertanam di layer image dan ikut terunggah ke registry.

## 3. Migrasi

- Satu service `migrate` one-off, dengan app/worker/scheduler menunggu `service_completed_successfully`. Bila migrasi
  gagal, versi baru tidak start.
- Hindari `migrate` di entrypoint setiap replica. Bila terpaksa, pakai `--isolated` dengan cache store bersama.
- Migrasi harus **backward-compatible**. Rollback image tidak mengembalikan skema (lab: rollback 9 detik, skema
  tetap).

## 4. Worker dan scheduler

| Kondisi worker (lab) | `docker compose stop -t 15` | Hasil |
|---|---|---|
| PHP **tanpa** `pcntl`, PID 1 | 16 s, exit **137** | Job terputus di tengah |
| Tanpa `pcntl` + `init: true` | 1 s, exit **131** (SIGQUIT) | Cepat, tetapi job tetap terputus |
| **Dengan** `pcntl` | 0 s, exit **0** | Job selesai dulu, lalu keluar |

> [!WARNING]
> Image `php:*-fpm` menetapkan `STOPSIGNAL SIGQUIT`, dan worker/scheduler dari image yang sama ikut mewarisinya. Pasang
> `pcntl` di Dockerfile (`docker-php-ext-install pcntl`), set `stop_grace_period` lebih besar dari `--timeout` job
> terlama, dan buat job penting idempotent.

Scheduler: satu replica saja (atau `->onOneServer()`). Worker: boleh diskalakan, dan wajib dibuat ulang setiap deploy
karena kode lama ada di memori. Compose melakukannya otomatis bila tag image berubah.

## 5. Log

- `LOG_CHANNEL=stderr`: log request dari worker php-fpm masuk ke `docker compose logs app` (lab: `dojo-fpm-stderr`).
- Artisan via `docker compose exec` adalah proses terpisah, sehingga lognya **tidak** masuk `docker logs` (lab: 0).
- Pasang rotasi log (modul 10): `logging.options.max-size`.

## 6. Update dan rollback

```bash
export TAG=v1.4.0
docker compose config -q && docker compose pull
docker compose exec -T db pg_dump -U dojo -d dojo -Fc > backups/pre-$TAG.dump
docker compose up -d --wait --remove-orphans
curl -fsS -o /dev/null -w '%{http_code}\n' https://dojo.example.com/up
# rollback:
TAG=v1.3.2 docker compose up -d --wait
```

- Lab update: 10 detik. Dari 150 request: **147× 200, 3× gagal**, jadi tidak benar-benar zero-downtime dengan satu
  replica.
- Lab rollback: 9 detik.

## 7. Jebakan DNS nginx → php-fpm

nginx me-resolve `fastcgi_pass app:9000` **sekali saat start**. Di lab, setelah app dibuat ulang dengan IP baru
(`.5` → `.7`), hasilnya `502` terus-menerus (`connect() failed (111: Connection refused)`), termasuk setelah rollback.

| Perbaikan | Hasil lab |
|---|---|
| `resolver 127.0.0.11 valid=10s ipv6=off;` + `set $php_upstream app:9000; fastcgi_pass $php_upstream;` | 200 setelah IP berubah |
| `depends_on: { app: { condition: service_healthy, restart: true } }` | nginx di-restart otomatis → 200 |
| Darurat: `docker compose exec nginx nginx -s reload` | 502 → 200 |

## 8. Backup dan restore

```bash
docker compose exec -T db pg_dump -U dojo -d dojo -Fc > dojo.dump        # lab: 35 KB, header PGDMP
docker compose exec -T db pg_restore -U dojo -d dojo --clean --if-exists < dojo.dump
```

Di lab:
- Restore ke database kosong → rc=0, `users=1 migrations=8`.
- Restore ulang ke database berisi tanpa `--clean` → `errors ignored on restore: 63`.

Uji restore secara berkala ke container terpisah. Backup yang belum pernah di-restore belum terbukti bisa dipakai.

## 9. Hardening

```yaml
app:
  read_only: true
  tmpfs:
    - /tmp
    - /var/www/html/bootstrap/cache:uid=82,gid=82
    - /var/www/html/storage/framework/views:uid=82,gid=82
  cap_drop: [ALL]
  security_opt: ["no-new-privileges:true"]
```

Di lab, hanya `/tmp` → `Read-only file system`. Ditambah `bootstrap/cache` → view cache masih gagal. Ditambah
`storage/framework/views` → sukses. User `www-data` (UID 82) sudah diset di image.

## 10. Checklist production

| Item | Kenapa (lab) |
|---|---|
| Tag image wajib (`${TAG:?}`) | Tidak pernah deploy `latest` tanpa sengaja |
| `APP_DEBUG=false`, `LOG_CHANNEL=stderr` | Stack trace tidak bocor, log ada di Docker |
| Secret DB via file + entrypoint | Tidak di env Compose. `config:cache` hanya saat start |
| Service `migrate` + `service_completed_successfully` | Migrasi tepat sekali, gagal = deploy berhenti |
| `pcntl` + `stop_grace_period` | Worker exit 0, bukan 137 |
| Scheduler 1 replica | Task tidak dobel |
| `resolver` atau `depends_on.restart` di nginx | Tidak ada 502 setelah IP app berubah |
| Backup sebelum migrasi + uji restore | Rollback image tidak mengembalikan skema |
| `read_only` + tmpfs `bootstrap/cache`, `storage/framework/views` | Rootfs tidak bisa ditulis penyerang |
| nginx `root .../public`, port `127.0.0.1` | `/.env` → 403, nginx hanya lewat reverse proxy TLS |
