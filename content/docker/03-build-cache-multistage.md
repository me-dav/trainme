---
format: 1
id: docker-build-cache-multistage
course:
  slug: docker
  title: Docker
  description: Container dari nol sampai production — CLI, image, storage, network, Compose, keamanan, dan operasional.
title: 'Dockerfile: Build, Cache & Multi-stage'
summary: Cara kerja build cache, urutan layer, multi-stage, fitur BuildKit (cache mount, secret, target, export, multi-platform), dan Dockerfile Laravel production yang diukur langsung di lab.
order: 3
version: 1
status: verified
updated: '2026-11-20'
tested_on:
  - Docker Desktop (Windows) — Docker Engine 29.4.1, BuildKit v0.29.0, containerd image store
  - Aplikasi Laravel 13 nyata (project devops-dojo) — composer:2 (2.11), node:22-alpine, php:8.3-fpm-alpine, nginx:1.27-alpine
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: cache
    text: Memahami cara kerja build cache dan invalidasinya
  - id: ordering
    text: Mengurutkan instruksi agar cache efektif
  - id: multistage
    text: Membangun image kecil dengan multi-stage (COPY --from, --target)
  - id: buildkit-mounts
    text: Mempercepat build dengan cache mount
  - id: secrets
    text: Memakai secret saat build tanpa membocorkannya
  - id: size
    text: Menganalisis dan mengecilkan ukuran image
  - id: platform
    text: Build multi-platform dan export hasil build
  - id: laravel
    text: Menulis dan me-review Dockerfile Laravel production
sources:
  - title: Docker Docs — Build cache
    url: https://docs.docker.com/build/cache/
  - title: Docker Docs — Cache invalidation
    url: https://docs.docker.com/build/cache/invalidation/
  - title: Docker Docs — Optimize cache usage (cache mounts)
    url: https://docs.docker.com/build/cache/optimize/
  - title: Docker Docs — Multi-stage builds
    url: https://docs.docker.com/build/building/multi-stage/
  - title: Docker Docs — Build secrets
    url: https://docs.docker.com/build/building/secrets/
  - title: Dockerfile reference — RUN --mount
    url: https://docs.docker.com/reference/dockerfile/#run---mount
  - title: Docker Docs — Build context (.dockerignore, Dockerfile-specific ignore)
    url: https://docs.docker.com/build/concepts/context/
  - title: Docker Docs — Multi-platform builds
    url: https://docs.docker.com/build/building/multi-platform/
  - title: Docker Docs — Exporters (-o / --output)
    url: https://docs.docker.com/build/exporters/
  - title: Docker Docs — Cache storage backends (--cache-to / --cache-from)
    url: https://docs.docker.com/build/cache/backends/
  - title: Docker Official Image php — How to install more PHP extensions
    url: https://github.com/docker-library/docs/blob/master/php/README.md#how-to-install-more-php-extensions
  - title: Laravel 13 — Deployment
    url: https://laravel.com/docs/13.x/deployment
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: cache
    prompt: Dengan `--progress=plain`, kata apa yang muncul di output untuk langkah yang **dipakai ulang dari cache** (tidak dijalankan lagi)?
    answers: ['CACHED']
    tests:
      fail: ['SKIPPED', 'REUSED', 'DONE', 'HIT']
    explanation: 'Di lab, setelah satu file PHP diubah: `[vendor 4/6] RUN composer install` → `CACHED`, sedangkan `[vendor 5/6] COPY . .` dijalankan ulang. `--progress=plain` juga menampilkan waktu setiap langkah (`#18 DONE 23.5s`).'
    source: https://docs.docker.com/build/cache/

  - id: a02
    tier: A
    type: command
    objective: cache
    prompt: Build image `app` dari context saat ini **tanpa memakai cache sama sekali**.
    answers: ['docker build --no-cache -t app .']
    patterns: ['^docker (image |buildx )?build (?=.*--no-cache( |$))(?=.*(-t|--tag)[= ]?app( |$))((--no-cache|(-t|--tag)[= ]?app|--pull) )+\.$']
    tests:
      pass: ['docker build -t app --no-cache .', 'docker build --no-cache --pull -t app .']
      fail: ['docker build -t app .', 'docker build --rebuild -t app .', 'docker builder prune']
    explanation: 'Berguna untuk membuktikan build benar-benar bisa dibuat dari nol. Temuan lab: `--no-cache` juga **mengabaikan cache mount** (soal tier B), jadi build dingin di lab memakan 114 detik. Untuk perilaku sehari-hari, cukup biarkan cache bekerja.'
    source: https://docs.docker.com/build/cache/invalidation/

  - id: a03
    tier: A
    type: command
    objective: cache
    prompt: Build image `app` sambil **memaksa pengecekan versi terbaru** base image dari registry (tag sama, mis. patch keamanan baru).
    answers: ['docker build --pull -t app .']
    patterns: ['^docker (image |buildx )?build (?=.*--pull( |$))(?=.*(-t|--tag)[= ]?app( |$))((--pull|--no-cache|(-t|--tag)[= ]?app) )+\.$']
    tests:
      pass: ['docker build -t app --pull .']
      fail: ['docker pull app', 'docker build -t app .', 'docker build --refresh -t app .']
    explanation: 'Tanpa `--pull`, builder memakai base image yang sudah ada di cache lokal, walaupun registry sudah merilis rebuild dengan patch CVE. Di CI yang berjalan di runner baru, efeknya kecil. Di server build yang permanen, `--pull` mencegah image tertinggal berbulan-bulan.'
    source: https://docs.docker.com/reference/cli/docker/buildx/build/#pull

  - id: a04
    tier: A
    type: command
    objective: multistage
    prompt: Build image `app` dari context saat ini memakai Dockerfile di `docker/Dockerfile.prod`.
    answers: ['docker build -f docker/Dockerfile.prod -t app .']
    patterns: ['^docker (image |buildx )?build (?=.*(-f|--file)[= ]?docker/Dockerfile\.prod( |$))(?=.*(-t|--tag)[= ]?app( |$))(((-f|--file)[= ]?docker/Dockerfile\.prod|(-t|--tag)[= ]?app) )+\.$']
    tests:
      pass: ['docker build -t app --file docker/Dockerfile.prod .']
      fail: ['docker build docker/Dockerfile.prod -t app', 'docker build -t app docker/Dockerfile.prod', 'docker build --file -t app .']
    explanation: 'Dockerfile tidak harus berada di root context, bahkan boleh di luar context (di lab, Dockerfile di folder lain memakai source project sebagai context). Argumen terakhir tetap **context**.'
    source: https://docs.docker.com/reference/cli/docker/buildx/build/#file

  - id: a05
    tier: A
    type: command
    objective: multistage
    prompt: Build **hanya sampai** stage bernama `assets`, lalu beri tag `app-assets`.
    answers: ['docker build --target assets -t app-assets .']
    patterns: ['^docker (image |buildx )?build (?=.*--target[= ]assets( |$))(?=.*(-t|--tag)[= ]?app-assets( |$))((--target[= ]assets|(-t|--tag)[= ]?app-assets) )+\.$']
    tests:
      pass: ['docker build -t app-assets --target=assets .']
      fail: ['docker build --stage assets -t app-assets .', 'docker build -t app-assets assets', 'docker build --from assets -t app-assets .']
    explanation: 'Di lab, `--target build` hanya menjalankan stage `build` (stage `final` dan `unused` tidak berjalan). Kegunaan: image dev/test dari Dockerfile yang sama, atau mengambil artefak saja. Dockerfile devops-dojo memakai `--target app` dan `--target nginx` untuk dua image berbeda.'
    source: https://docs.docker.com/build/building/multi-stage/#stop-at-a-specific-build-stage

  - id: a06
    tier: A
    type: command
    objective: multistage
    prompt: Di stage final, salin `/app/public/build` dari stage bernama `assets` ke `./public/build`.
    answers: ['COPY --from=assets /app/public/build ./public/build']
    patterns: ['^COPY (--chown=\S+ )?--from=assets (--chown=\S+ )?/app/public/build \./public/build/?$']
    tests:
      pass: ['COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build']
      fail: ['COPY assets:/app/public/build ./public/build', 'COPY --stage=assets /app/public/build ./public/build', 'ADD --from=assets /app/public/build ./public/build']
    explanation: 'Inilah inti multi-stage: Node, npm, dan `node_modules` (ratusan MB) hanya hidup di stage `assets`, dan image final hanya menerima hasil build. Di lab, image nginx menerima `public/build/assets/*.js` tanpa satu pun file Node.'
    source: https://docs.docker.com/build/building/multi-stage/

  - id: a07
    tier: A
    type: command
    objective: multistage
    prompt: Salin binary `composer` dari image resmi `composer:2` ke `/usr/bin/composer`, tanpa menginstall composer.
    answers: ['COPY --from=composer:2 /usr/bin/composer /usr/bin/composer']
    patterns: ['^COPY --from=composer:2(\.\d+)* /usr/bin/composer /usr/(local/)?bin/composer$']
    tests:
      pass: ['COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer']
      fail: ['RUN curl -sS https://getcomposer.org/installer | php', 'FROM composer:2', 'COPY composer:2 /usr/bin/composer /usr/bin/composer']
    explanation: '`--from` bisa menunjuk image mana pun, tidak hanya stage di Dockerfile yang sama. Di lab, versi perbaikan memakai trik ini untuk menjalankan `composer check-platform-reqs` di stage runtime.'
    source: https://docs.docker.com/build/building/multi-stage/#use-an-external-image-as-a-stage

  - id: a08
    tier: A
    type: command
    objective: multistage
    prompt: Beri nama stage `build` pada instruksi `FROM node:22-alpine`.
    answers: ['FROM node:22-alpine AS build']
    patterns: ['^FROM node:22-alpine AS build$']
    tests:
      fail: ['FROM node:22-alpine:build', 'FROM build=node:22-alpine', 'STAGE build', 'FROM node:22-alpine as build']
    explanation: 'Nama stage dipakai oleh `COPY --from=build` dan `--target build`. Stage tanpa nama tetap bisa dirujuk dengan indeks (`--from=0`), tetapi rapuh bila urutan berubah. `as` huruf kecil tetap jalan, tetapi memicu peringatan `FromAsCasing`.'
    source: https://docs.docker.com/build/building/multi-stage/#name-your-build-stages

  - id: a09
    tier: A
    type: text
    objective: multistage
    prompt: Pada multi-stage build, apakah tool di stage `build` (npm, compiler, `node_modules`) ikut masuk image final bila **tidak** di-COPY? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Hanya stage terakhir (atau `--target`) yang menjadi image, dan stage lain hanya menyumbang file yang di-`COPY --from`. Di lab, image `nginx` devops-dojo berukuran 80,7 MB, meski stage `assets` di belakangnya memakai Node dan menjalankan `npm ci` untuk dependency Vite/Tailwind/Mermaid.'
    source: https://docs.docker.com/build/building/multi-stage/

  - id: a10
    tier: A
    type: command
    objective: buildkit-mounts
    prompt: Tulis `RUN` yang menjalankan `npm ci` dengan **cache mount** BuildKit di `/root/.npm`.
    answers: ['RUN --mount=type=cache,target=/root/.npm npm ci']
    patterns: ['^RUN --mount=type=cache,target=/root/\.npm(,\S+)? npm ci( \S+)*$']
    tests:
      pass: ['RUN --mount=type=cache,target=/root/.npm npm ci --no-audit --no-fund']
      fail: ['RUN npm ci', 'RUN --cache /root/.npm npm ci', 'VOLUME /root/.npm']
    explanation: 'Isi cache mount bertahan antar build, tetapi **tidak** masuk ke image. Di lab, setelah `package.json` berubah, `npm ci` turun dari 46 detik menjadi **7,1 detik**, dan `composer install` dengan `--mount=type=cache,target=/tmp/cache` dari 23,5 detik menjadi **2,4 detik** (download 79 → 0).'
    source: https://docs.docker.com/build/cache/optimize/#use-cache-mounts

  - id: a11
    tier: A
    type: command
    objective: secrets
    prompt: Build image `app` dengan **secret** bernama `npm_token` yang diambil dari environment variable `NPM_TOKEN` di mesin build.
    answers: ['docker build --secret id=npm_token,env=NPM_TOKEN -t app .']
    patterns: ['^docker (image |buildx )?build (?=.*--secret[= ]id=npm_token,(env=NPM_TOKEN|src=\S+)( |$))(?=.*(-t|--tag)[= ]?app( |$))((--secret[= ]id=npm_token,(env=NPM_TOKEN|src=\S+)|(-t|--tag)[= ]?app) )+\.$']
    tests:
      pass: ['docker build -t app --secret id=npm_token,src=./token.txt .']
      fail: ['docker build --build-arg NPM_TOKEN=$NPM_TOKEN -t app .', 'docker build -e NPM_TOKEN -t app .', 'docker build --secret NPM_TOKEN -t app .']
    explanation: '`env=` membaca dari environment, `src=` dari file. Bandingkan dengan modul sebelumnya: `--build-arg` membuat token terlihat di `docker history`, sedangkan secret tidak (lab: 0 kemunculan).'
    source: https://docs.docker.com/build/building/secrets/

  - id: a12
    tier: A
    type: command
    objective: secrets
    prompt: Tulis `RUN` yang menjalankan `npm ci` dengan secret `npm_token` tersedia sebagai **environment variable** `NPM_TOKEN` hanya selama perintah itu.
    answers: ['RUN --mount=type=secret,id=npm_token,env=NPM_TOKEN npm ci']
    patterns: ['^RUN --mount=type=secret,id=npm_token(,env=NPM_TOKEN|,required(=true)?)+ npm ci( \S+)*$', '^RUN --mount=type=secret,id=npm_token,env=NPM_TOKEN(,\S+)? npm ci( \S+)*$']
    tests:
      pass: ['RUN --mount=type=secret,id=npm_token,env=NPM_TOKEN,required npm ci']
      fail: ['RUN NPM_TOKEN=$NPM_TOKEN npm ci', 'ARG NPM_TOKEN', 'RUN --mount=type=cache,id=npm_token npm ci']
    explanation: 'Di lab: `env-mode token-len=14` di RUN itu, sedangkan di RUN berikutnya `NPM_TOKEN=[]` dan `/run/secrets` tidak ada. Tanpa `env=`, secret tersedia sebagai file `/run/secrets/npm_token` (lab: `file-mode bytes=14`). `required` membuat build gagal bila secret tidak diberikan.'
    source: https://docs.docker.com/reference/dockerfile/#run---mounttypesecret

  - id: a13
    tier: A
    type: text
    objective: cache
    prompt: Tulis baris **parser directive** (baris pertama Dockerfile) yang memilih syntax Dockerfile stabil terbaru, agar fitur seperti heredoc dan `RUN --mount` tersedia.
    answers: ['# syntax=docker/dockerfile:1', '#syntax=docker/dockerfile:1']
    tests:
      fail: ['FROM docker/dockerfile:1', '# version=1', 'syntax 1', '# syntax: docker/dockerfile:1']
    explanation: 'Directive ini membuat BuildKit memakai frontend Dockerfile versi 1.x terbaru, terlepas dari versi Docker di mesin build. Harus berada di baris paling atas, sebelum komentar atau instruksi lain.'
    source: https://docs.docker.com/reference/dockerfile/#syntax

  - id: a14
    tier: A
    type: command
    objective: size
    prompt: Tampilkan daftar layer image `app:1.0` beserta ukuran dan instruksi pembuatnya.
    answers: ['docker history app:1.0']
    patterns: ['^docker (image )?history( --no-trunc| --format \S+)* app:1\.0$']
    tests:
      pass: ['docker history --no-trunc app:1.0', 'docker image history app:1.0']
      fail: ['docker inspect app:1.0', 'docker image ls app:1.0', 'docker logs app:1.0']
    explanation: 'Alat pertama untuk mencari penyebab image gemuk. Di lab, `docker history` Dockerfile devops-dojo langsung menunjuk satu layer: `508MB RUN apk add --no-cache postgresql-dev ...` (studi kasus C).'
    source: https://docs.docker.com/reference/cli/docker/image/history/

  - id: a15
    tier: A
    type: command
    objective: platform
    prompt: Build stage `assets` lalu **export filesystem hasilnya** ke direktori lokal `./out` (bukan menjadi image).
    answers: ['docker build --target assets -o type=local,dest=./out .']
    patterns: ['^docker (image |buildx )?build (?=.*--target[= ]assets( |$))(?=.*(-o|--output)[= ]?(type=local,dest=)?(\./)?out/?( |$))((--target[= ]assets|(-o|--output)[= ]?(type=local,dest=)?(\./)?out/?) )+\.$']
    tests:
      pass: ['docker build -o ./out --target assets .', 'docker build --output type=local,dest=out --target=assets .']
      fail: ['docker build --target assets -t out .', 'docker cp assets:/app ./out', 'docker build --export out .']
    explanation: 'Berguna untuk memakai Docker sebagai build tool yang reproducible (mis. menghasilkan `public/build` atau binary) tanpa menginstall Node/Go di CI. Di lab, `out/app/public/build/` berisi `assets/` dan `manifest.json`. Yang diekspor adalah seluruh filesystem stage itu.'
    source: https://docs.docker.com/build/exporters/local-tar/

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: ordering
    prompt: 'Urutan: `COPY composer.json composer.lock ./` → `RUN composer install` → `COPY . .`. Kamu hanya mengubah satu file PHP di `app/`. Apakah `composer install` dijalankan ulang? (ya/tidak)'
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Cache key COPY adalah checksum file yang disalin. File PHP tidak termasuk di COPY pertama, sehingga layer itu dan RUN setelahnya tetap valid. Di lab: `composer install` CACHED, dan yang dijalankan ulang hanya `COPY . .` dan `composer dump-autoload`. Rebuild total 12 detik.'
    source: https://docs.docker.com/build/cache/invalidation/

  - id: b02
    tier: B
    type: text
    objective: ordering
    prompt: Bila urutannya dibalik — `COPY . .` dulu, baru `RUN composer install` — apakah mengubah satu file PHP membuat `composer install` dijalankan ulang? (ya/tidak)
    answers: ['ya', 'yes']
    tests:
      fail: ['tidak', 'no']
    explanation: 'Begitu satu layer berubah, **semua** layer setelahnya di stage itu ikut dibangun ulang. `COPY . .` berubah setiap kali ada file yang diubah, sehingga install dependency ikut berjalan ulang. Aturan umum: urutkan dari yang paling jarang berubah ke yang paling sering. Base dan paket OS dulu, manifest dependency, install, baru kode.'
    source: https://docs.docker.com/build/cache/invalidation/

  - id: b03
    tier: B
    type: text
    objective: buildkit-mounts
    prompt: Apakah `docker build --no-cache` juga **mengabaikan isi** cache mount (`RUN --mount=type=cache`)? (ya/tidak)
    answers: ['ya', 'yes']
    tests:
      fail: ['tidak', 'no']
    explanation: 'Diuji di lab (BuildKit 0.29): RUN dengan cache mount yang menghitung file → build biasa: `0` lalu `1` (isi bertahan); dengan `--no-cache`: kembali `0`. Akibatnya, mengukur manfaat cache mount dengan `--no-cache` memberi hasil yang menyesatkan. Ukur dengan mengubah file manifest, lalu build biasa.'
    source: https://docs.docker.com/build/cache/optimize/#use-cache-mounts

  - id: b04
    tier: B
    type: text
    objective: buildkit-mounts
    prompt: 'Layer `RUN --mount=type=cache,target=/tmp/cache composer install` di-invalidate karena `composer.json` berubah (build biasa). Apakah paket yang sebelumnya sudah diunduh tetap tersedia di cache? (ya/tidak)'
    answers: ['ya', 'yes']
    tests:
      fail: ['tidak', 'no']
    explanation: 'Inilah bedanya dengan layer cache. Layer cache "semua atau tidak sama sekali", sedangkan cache mount tetap ada walau RUN-nya dijalankan ulang. Di lab, Dockerfile tanpa cache mount mengunduh 79 paket (23,5 detik), dan versi dengan cache mount mengunduh **0** paket (2,4 detik). Pastikan target-nya direktori cache tool itu sendiri (composer image: `/tmp/cache`, npm: `/root/.npm`, apk: `/var/cache/apk`).'
    source: https://docs.docker.com/build/cache/optimize/#use-cache-mounts

  - id: b05
    tier: B
    type: text
    objective: secrets
    prompt: Apakah nilai secret dari `RUN --mount=type=secret` muncul di `docker history --no-trunc` image hasilnya? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab: `docker history --no-trunc | grep -c supersecret` → `0`, dan pencarian di filesystem image juga kosong. Secret di-mount ke tmpfs hanya selama satu RUN. Catatan: bila perintahmu sendiri menulis secret ke file atau ke output, secret tetap bocor. Mount tidak bisa mencegah hal itu.'
    source: https://docs.docker.com/build/building/secrets/

  - id: b06
    tier: B
    type: text
    objective: secrets
    prompt: 'Tanpa opsi `env=` atau `target=`, secret dengan `id=npm_token` tersedia di dalam RUN sebagai **file** di path apa?'
    answers: ['/run/secrets/npm_token']
    tests:
      fail: ['/etc/secrets/npm_token', '/run/npm_token', '/secrets/npm_token', '/var/run/secrets/npm_token']
    explanation: 'Default `/run/secrets/<id>` (lab: `wc -c < /run/secrets/npm_token` → 14). `target=/root/.npmrc` me-mount secret langsung ke path yang dibaca tool, mis. `.npmrc`, atau `auth.json` untuk Composer dengan repository privat.'
    source: https://docs.docker.com/reference/dockerfile/#run---mounttypesecret

  - id: b07
    tier: B
    type: command
    objective: size
    prompt: |
      Tulis **satu** `RUN` (php Alpine) yang: memasang library runtime `libpq`, memasang build deps sebagai virtual package
      `.build-deps` (`$PHPIZE_DEPS postgresql-dev`), meng-install ekstensi `pdo_pgsql`, lalu menghapus `.build-deps`.
    answers: ['RUN apk add --no-cache libpq && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev && docker-php-ext-install pdo_pgsql && apk del .build-deps']
    patterns: ['^RUN apk add --no-cache libpq && apk add (--no-cache --virtual|--virtual \.build-deps --no-cache|--no-cache -t|-t \.build-deps --no-cache) (\.build-deps )?\$PHPIZE_DEPS postgresql-dev && docker-php-ext-install (-j\S+ )?pdo_pgsql && apk del( --no-network)? \.build-deps$']
    tests:
      pass: ['RUN apk add --no-cache libpq && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev && docker-php-ext-install -j"$(nproc)" pdo_pgsql && apk del .build-deps']
      fail:
        - 'RUN apk add --no-cache postgresql-dev && docker-php-ext-install pdo_pgsql'
        - 'RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev && docker-php-ext-install pdo_pgsql && apk del .build-deps'
        - 'RUN apk add --no-cache libpq && docker-php-ext-install pdo_pgsql && apk del .build-deps'
    explanation: 'Compiler dan header hanya dibutuhkan saat compile ekstensi. Di lab, perubahan ini mengecilkan layer ekstensi devops-dojo dari **508 MB** menjadi **18,6 MB**. `libpq` wajib dipasang eksplisit. Di lab, versi tanpa `libpq` **lolos build**, tetapi saat runtime `php -m` → `Unable to load dynamic library ''pdo_pgsql'' ... libpq.so.5: No such file`, karena libpq ikut terhapus bersama `.build-deps`.'
    source: https://github.com/docker-library/docs/blob/master/php/README.md#how-to-install-more-php-extensions

  - id: b08
    tier: B
    type: text
    objective: size
    prompt: 'Di Alpine terbaru, `apk add postgresql-dev` di image final membuatnya membengkak ratusan MB. Paket toolchain compiler apa yang ikut tertarik sebagai dependency? (sebutkan salah satu)'
    answers: ['clang', 'llvm', 'gcc', 'clang dan llvm']
    patterns: ['(clang|llvm|gcc)']
    tests:
      pass: ['clang/llvm', 'llvm (170 MB)']
      fail: ['python', 'perl', 'java', 'git']
    explanation: 'Di lab, `apk info -R postgresql-dev` → `clang`, `llvm`, `icu-dev`, `openssl-dev`, dst. (untuk JIT PostgreSQL). Image devops-dojo membawa `llvm20-libs` 170 MiB dan `gcc` 159 MiB, totalnya 885 MB di disk. Paket `-dev` adalah build dependency dan tidak boleh tertinggal di image runtime.'
    source: https://github.com/docker-library/docs/blob/master/php/README.md#how-to-install-more-php-extensions

  - id: b09
    tier: B
    type: text
    objective: multistage
    prompt: Dockerfile punya stage `unused` yang tidak di-COPY oleh stage final. Saat `docker build` (BuildKit) tanpa `--target`, apakah stage itu **dieksekusi**? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'BuildKit membangun graph dependency dari stage target dan hanya menjalankan stage yang dibutuhkan. Di lab, output memuat `FINAL-RAN`, tetapi tidak ada `UNUSED-STAGE-RAN`. Stage yang independen juga dijalankan **paralel** (di lab, stage `vendor` dan `assets` berjalan bersamaan). Builder lama (legacy) menjalankan semua stage berurutan.'
    source: https://docs.docker.com/build/building/multi-stage/#differences-between-legacy-builder-and-buildkit

  - id: b10
    tier: B
    type: text
    objective: cache
    prompt: Dockerfile ada di `docker/prod.Dockerfile`. Apa nama file ignore yang **khusus** untuk Dockerfile itu (diletakkan di sebelahnya), yang dipakai menggantikan `.dockerignore` di root context?
    answers: ['prod.Dockerfile.dockerignore', 'docker/prod.Dockerfile.dockerignore']
    tests:
      fail: ['.dockerignore', 'prod.dockerignore', 'Dockerfile.dockerignore', 'docker/.dockerignore']
    explanation: 'Format `<nama-dockerfile>.dockerignore`. Di lab, Dockerfile perbaikan dibangun dari folder lain dengan `Dockerfile.dockerignore` di sebelahnya, dan `README.md` serta `docker-compose.yml` tidak lagi masuk ke image. Berguna saat satu repo punya beberapa Dockerfile dengan kebutuhan context berbeda.'
    source: https://docs.docker.com/build/concepts/context/#filename-and-location

  - id: b11
    tier: B
    type: text
    objective: laravel
    prompt: 'Build image Laravel gagal di `npm run build`: `laravel-vite-plugin: Failed to fetch "https://fonts.bunny.net/css2?...": 504 Gateway Timeout`. Apa akar masalahnya secara umum?'
    answers: ['build bergantung pada layanan eksternal', 'ketergantungan jaringan saat build']
    patterns: ['(eksternal|pihak ketiga|third.?party|cdn|jaringan|network|internet|bunny|remote)']
    tests:
      pass: ['font diunduh dari CDN pihak ketiga saat build', 'build butuh internet']
      fail: ['dockerfile salah', 'npm ci gagal', 'memory habis', 'node versi salah']
    explanation: 'Dialami langsung di lab: konfigurasi `fonts: [bunny(...)]` di `vite.config.js` mengunduh CSS font **setiap** build, dan saat itu bunny.net membalas 504, juga dari laptop. Build yang tadinya sukses tiba-tiba gagal tanpa perubahan kode. Mitigasi: self-host font di repo, retry di CI, atau pastikan hasil fetch ter-cache. Prinsipnya: setiap ketergantungan jaringan saat build adalah titik kegagalan dan risiko supply chain.'
    source: https://laravel.com/docs/13.x/vite

  - id: b12
    tier: B
    type: command
    objective: platform
    prompt: 'Build dan **push** image `ghcr.io/acme/app:1.0` untuk dua arsitektur sekaligus: `linux/amd64` dan `linux/arm64`.'
    answers: ['docker buildx build --platform linux/amd64,linux/arm64 -t ghcr.io/acme/app:1.0 --push .']
    patterns: ['^docker (buildx )?build (?=.*--platform[= ](linux/amd64,linux/arm64|linux/arm64,linux/amd64)( |$))(?=.*--push( |$))(?=.*(-t|--tag)[= ]?ghcr\.io/acme/app:1\.0( |$))((--platform[= ]\S+|--push|(-t|--tag)[= ]?ghcr\.io/acme/app:1\.0) )+\.$']
    tests:
      pass: ['docker build --push --platform linux/amd64,linux/arm64 -t ghcr.io/acme/app:1.0 .']
      fail: ['docker buildx build --arch amd64,arm64 -t ghcr.io/acme/app:1.0 --push .', 'docker build --platform linux/amd64,linux/arm64 -t ghcr.io/acme/app:1.0 .', 'docker push --platform linux/amd64,linux/arm64 ghcr.io/acme/app:1.0']
    explanation: 'Di lab (containerd image store), build `--platform linux/amd64,linux/arm64` berhasil secara lokal. `docker run --platform linux/arm64` mencetak `arch=aarch64 TARGETARCH=arm64` lewat emulasi QEMU. ARG bawaan `TARGETARCH` dipakai untuk mengunduh binary yang sesuai arsitektur. Penting bila server production adalah ARM (AWS Graviton) sedangkan laptop x86. Bagian push ke registry tidak kami uji di lab.'
    source: https://docs.docker.com/build/building/multi-platform/

  - id: b13
    tier: B
    type: text
    objective: ordering
    prompt: 'Di stage final, `COPY --from=vendor ...` (yang berubah) ditulis **sebelum** `COPY --from=assets ...` (yang tidak berubah). Apakah `COPY --from=assets` dijalankan ulang? (ya/tidak)'
    answers: ['ya', 'yes']
    tests:
      fail: ['tidak', 'no']
    explanation: 'Di lab, setelah satu file PHP diubah, stage `assets` seluruhnya CACHED, tetapi di stage final `[app 8/10] COPY --from=assets` tetap **dijalankan ulang**, karena layer sebelumnya (`COPY --from=vendor`) berubah. Langkah murah seperti ini tidak masalah, tetapi jangan menaruh langkah mahal (compile, install) setelah COPY yang sering berubah.'
    source: https://docs.docker.com/build/cache/invalidation/

  - id: b14
    tier: B
    type: text
    objective: cache
    prompt: Nilai sebuah ARG diganti lewat `--build-arg`. Apakah itu meng-invalidate cache RUN yang **memakai** ARG tersebut? (ya/tidak)
    answers: ['ya', 'yes']
    tests:
      fail: ['tidak', 'no']
    explanation: 'Nilai ARG ikut menjadi bagian cache key RUN setelah ARG dideklarasikan. Di lab, `--build-arg BUST=1` lalu `BUST=2` membuat RUN berjalan ulang. Ini bisa disengaja ("cache bust" untuk memaksa `apt-get update` terbaru), tetapi juga bisa tidak sengaja, mis. ARG berisi timestamp/commit SHA yang dideklarasikan di awal Dockerfile, sehingga semua langkah setelahnya selalu dibangun ulang. Deklarasikan ARG seperti itu sedekat mungkin dengan tempat pemakaiannya.'
    source: https://docs.docker.com/build/cache/invalidation/#build-arguments

  - id: b15
    tier: B
    type: command
    objective: size
    prompt: Tampilkan berapa disk yang dipakai **build cache** BuildKit (tanpa menghapus apa pun).
    answers: ['docker buildx du']
    patterns: ['^docker (buildx du|builder du)( -v| --verbose)?$', '^docker system df( -v)?$']
    tests:
      pass: ['docker buildx du --verbose', 'docker system df']
      fail: ['docker builder prune -a', 'docker images', 'du -sh /var/lib/docker']
    explanation: 'Di lab, Docker Desktop menunjukkan `Reclaimable: 5.666GB` build cache. Untuk membersihkannya: `docker builder prune` (hanya cache yang tidak dipakai) atau `--filter until=168h`. Prune berlaku untuk **seluruh** builder di mesin itu, termasuk cache project lain, jadi periksa dulu dengan `du`.'
    source: https://docs.docker.com/reference/cli/docker/buildx/du/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: laravel
    prompt: |
      Tulis Dockerfile multi-stage production (satu instruksi per baris) untuk aplikasi Laravel + Vite dengan PostgreSQL:
      - stage dependency PHP dengan composer (tanpa dev dependency)
      - stage build aset dengan Node
      - stage runtime php-fpm Alpine dengan ekstensi `pdo_pgsql` + `opcache`, tanpa build tools tertinggal
      - cache mount untuk composer/npm
      - file aplikasi tidak dimiliki root, proses tidak berjalan sebagai root
      - entrypoint/cmd exec form
    checklist:
      - id: vendor-stage
        label: Stage composer (FROM composer:... AS ...)
        required: true
        patterns: ['^from\s+composer:\S+\s+as\s+\w+']
      - id: deps-first
        label: COPY composer.json composer.lock sebelum kode
        patterns: ['^copy\s+composer\.json\s+composer\.lock']
      - id: no-dev
        label: composer install --no-dev
        patterns: ['composer\s+install\s+.*--no-dev']
      - id: cache-mount
        label: RUN --mount=type=cache untuk composer/npm
        patterns: ['--mount=type=cache']
      - id: assets-stage
        label: Stage Node (FROM node:... AS ...) + npm run build
        patterns: ['^from\s+node:\S+\s+as\s+\w+', 'npm\s+run\s+build']
      - id: runtime
        label: Stage runtime php:...-fpm(-alpine)
        required: true
        patterns: ['^from\s+php:\S+-fpm\S*(\s+as\s+\w+)?$']
      - id: no-build-tools
        label: Build deps dihapus di RUN yang sama (apk del / virtual)
        patterns: ['apk\s+del\s+\.?[\w-]+', '--virtual\s+\.?[\w-]+']
      - id: runtime-lib
        label: Library runtime (libpq) dipasang eksplisit
        patterns: ['apk\s+add\s+.*\blibpq\b']
      - id: copy-from
        label: COPY --from= stage lain ke image final
        required: true
        patterns: ['^copy\s+(--\S+\s+)*--from=\w+']
      - id: ownership
        label: --chown ke www-data / USER www-data
        patterns: ['--chown=www-data', '^user\s+(www-data|\d+)']
      - id: exec-form
        label: ENTRYPOINT/CMD exec form
        patterns: ['^(cmd|entrypoint)\s+\[']
      - id: no-env-file
        label: Tidak menyalin .env ke image
        forbid: true
        patterns: ['^copy\s+(--\S+\s+)*\.env\b']
      - id: no-secret-arg
        label: Tidak menaruh secret di ARG/ENV
        forbid: true
        patterns: ['^(env|arg)\s+\w*(app_key|token|password|secret)\w*\s*=']
    pass_score: 0.6
    solution: |
      ```dockerfile
      # syntax=docker/dockerfile:1
      ARG PHP_VERSION=8.3

      FROM composer:2 AS vendor
      WORKDIR /app
      COPY composer.json composer.lock ./
      RUN --mount=type=cache,target=/tmp/cache \
          composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
      COPY . .
      RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

      FROM node:22-alpine AS assets
      WORKDIR /app
      COPY package.json package-lock.json ./
      RUN --mount=type=cache,target=/root/.npm npm ci --no-audit --no-fund
      COPY vite.config.js ./
      COPY resources ./resources
      RUN npm run build

      FROM php:${PHP_VERSION}-fpm-alpine AS app
      RUN apk add --no-cache libpq libzip icu-libs fcgi \
       && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev libzip-dev icu-dev \
       && docker-php-ext-install -j"$(nproc)" pdo_pgsql zip intl opcache \
       && apk del .build-deps
      COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
      WORKDIR /var/www/html
      COPY --from=vendor --chown=www-data:www-data /app ./
      COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
      COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/entrypoint.sh
      RUN composer check-platform-reqs --no-dev --no-interaction
      USER www-data
      ENTRYPOINT ["entrypoint.sh"]
      CMD ["php-fpm"]

      FROM nginx:1.27-alpine AS nginx
      COPY --from=app /var/www/html/public /var/www/html/public
      ```
      Versi ini (ditambah gd/bcmath dan healthcheck ping php-fpm) di-build di lab dengan source devops-dojo:
      - Image **229 MB** di disk / **53 MB** terkompresi, dibanding 885 MB / 223 MB untuk versi tanpa cache mount dan
        dengan build deps tertinggal.
      - `(healthy)`, dengan master php-fpm berjalan sebagai `uid=82(www-data)`.
      - Ekstensi `pdo_pgsql intl gd zip bcmath OPcache` terdeteksi, dan `composer check-platform-reqs` lulus.

      `--ignore-platform-reqs` di stage composer wajar (stage itu tidak punya ekstensi aplikasi), tetapi
      `check-platform-reqs` di stage runtime yang memastikan ekstensi yang dibutuhkan lockfile benar-benar ada.
    tests:
      pass:
        - |
          FROM composer:2 AS vendor
          COPY composer.json composer.lock ./
          RUN --mount=type=cache,target=/tmp/cache composer install --no-dev --no-scripts
          FROM node:22-alpine AS assets
          RUN npm run build
          FROM php:8.3-fpm-alpine AS app
          RUN apk add --no-cache libpq && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev && docker-php-ext-install pdo_pgsql opcache && apk del .build-deps
          COPY --from=vendor --chown=www-data:www-data /app ./
          COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
          USER www-data
          CMD ["php-fpm"]
        - |
          FROM composer:2.8 AS deps
          COPY composer.json composer.lock ./
          RUN composer install --no-dev
          FROM php:8.3-fpm
          COPY --from=deps /app/vendor ./vendor
          USER www-data
          CMD ["php-fpm"]
          FROM node:22 AS front
      fail:
        - |
          FROM php:8.3-fpm-alpine
          RUN apk add --no-cache postgresql-dev nodejs npm
          COPY . .
          RUN composer install && npm ci && npm run build
          CMD php-fpm
        - |
          FROM composer:2 AS vendor
          RUN composer install --no-dev
          FROM php:8.3-fpm-alpine
          COPY .env .env
          COPY --from=vendor /app ./
          CMD ["php-fpm"]
        - |
          FROM composer:2 AS vendor
          FROM php:8.3-fpm-alpine
          ENV APP_KEY=base64:abc
          COPY --from=vendor /app ./
          CMD ["php-fpm"]
    source: https://docs.docker.com/build/building/multi-stage/

  - id: c02
    tier: C
    type: scenario
    objective: size
    prompt: |
      Image `app` Laravel di bawah ini 885 MB di disk dan terasa lambat di-pull. Tulis perbaikan untuk instruksi
      yang bermasalah, perbaikan `.dockerignore`, dan perintah verifikasi (satu per baris).
    context: |
      $ docker history --format '{{.Size}}  {{.CreatedBy}}' app | head -n 9 | tail -n 1
      508MB  RUN /bin/sh -c apk add --no-cache postgresql-dev libzip-dev icu-dev libpng-dev ... && docker-php-ext-install pdo_pgsql zip intl gd bcmath opcache
      $ docker run --rm --entrypoint sh app -c 'ls /var/www/html'
      README.md app artisan ... docker docker-compose.yml package.json phpunit.xml public ... vendor
      $ grep compose .dockerignore
      compose.yaml
      $ grep -E '^FROM' Dockerfile
      FROM composer:2 AS vendor
      FROM node:22-alpine AS assets
      FROM php:8.3-fpm-alpine AS app
      FROM nginx:alpine AS nginx
    checklist:
      - id: virtual
        label: Build deps sebagai virtual package lalu apk del di RUN yang sama
        required: true
        patterns: ['--virtual\s+\.?[\w-]+.*apk\s+del', 'apk\s+add\s+.*--virtual\s+\.?[\w-]+']
      - id: runtime-libs
        label: Library runtime dipasang eksplisit (libpq, libzip, icu-libs, ...)
        required: true
        patterns: ['apk\s+add\s+.*\blibpq\b']
      - id: dockerignore
        label: .dockerignore mencakup docker-compose.yml (dan file non-runtime lain)
        patterns: ['^docker-compose\S*\.ya?ml$', '^\*compose\*', '^readme\.md$', '^phpunit\.xml$']
      - id: pin-nginx
        label: Pin tag nginx
        patterns: ['^from\s+nginx:\d']
      - id: platform-check
        label: composer check-platform-reqs di stage runtime
        patterns: ['check-platform-reqs']
      - id: verify
        label: Verifikasi ukuran/isi (docker history / images / run php -m)
        patterns: ['docker\s+(history|images|image\s+(ls|inspect))\b', 'php\s+-m']
      - id: no-separate-del
        label: Tidak menghapus build deps di RUN terpisah (layer lama tetap ada)
        forbid: true
        patterns: ['^run\s+apk\s+del\b']
    pass_score: 0.5
    solution: |
      ```dockerfile
      FROM php:8.3-fpm-alpine AS app
      RUN apk add --no-cache libpq libzip icu-libs libpng libjpeg-turbo freetype \
       && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev libzip-dev icu-dev libpng-dev libjpeg-turbo-dev freetype-dev \
       && docker-php-ext-configure gd --with-freetype --with-jpeg \
       && docker-php-ext-install -j"$(nproc)" pdo_pgsql zip intl gd bcmath opcache \
       && apk del .build-deps
      COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
      RUN composer check-platform-reqs --no-dev
      FROM nginx:1.27-alpine AS nginx
      ```
      ```text
      # .dockerignore (tambahan)
      docker-compose*.yml
      README.md
      phpunit.xml
      ```
      ```bash
      docker history app | head
      docker run --rm app php -m | grep -E 'pdo_pgsql|intl|gd'
      ```
      Ini Dockerfile nyata (project devops-dojo) yang diukur di lab:
      - `postgresql-dev` menarik clang/llvm/gcc, sehingga satu layer berukuran 508 MB.
      - Setelah build deps dibuang di RUN yang **sama** dan library runtime dipasang eksplisit, layer itu menjadi
        **18,6 MB** dan image menjadi 229 MB (53 MB terkompresi, 4× lebih cepat di-pull).
      - `.dockerignore` mengecualikan `compose.yaml`, padahal file-nya bernama `docker-compose.yml`, jadi file itu ikut
        masuk image. Perbaikannya menghilangkannya (lab: `No such file`).
      - `nginx:alpine` tanpa versi bisa berganti versi mayor sendiri.
      - `apk del` di RUN terpisah tidak mengurangi ukuran sama sekali (modul 02: layer append-only).
      - Tanpa `libpq` eksplisit, build lolos tetapi `pdo_pgsql` gagal dimuat saat runtime. Itulah gunanya
        `check-platform-reqs` dan `php -m` di pipeline.
    tests:
      pass:
        - |
          RUN apk add --no-cache libpq libzip icu-libs && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev libzip-dev icu-dev && docker-php-ext-install pdo_pgsql zip intl opcache && apk del .build-deps
          FROM nginx:1.27-alpine AS nginx
          docker-compose*.yml
          README.md
          docker history app
        - |
          RUN apk add --no-cache libpq && apk add --virtual .build-deps $PHPIZE_DEPS postgresql-dev && docker-php-ext-install pdo_pgsql && apk del .build-deps
          RUN composer check-platform-reqs --no-dev
          docker run --rm app php -m
      fail:
        - |
          RUN apk add --no-cache postgresql-dev && docker-php-ext-install pdo_pgsql
          RUN apk del postgresql-dev
          docker history app
        - |
          FROM nginx:1.27-alpine
          README.md
        - |
          RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev && docker-php-ext-install pdo_pgsql && apk del .build-deps
          docker images app
    source: https://github.com/docker-library/docs/blob/master/php/README.md#how-to-install-more-php-extensions

  - id: c03
    tier: C
    type: scenario
    objective: secrets
    prompt: |
      Di CI, build image Laravel butuh token GitHub untuk satu paket Composer privat, dan setiap build memakan
      5 menit karena runner selalu baru (tanpa cache lokal). Tulis perintah build CI dan baris Dockerfile terkait
      (satu per baris) yang memakai token dengan aman, berbagi cache build antar run, memberi tag yang bisa
      dilacak ke commit, dan mendorong image ke registry.
    checklist:
      - id: cli-secret
        label: --secret id=... (env= atau src=) di docker build
        required: true
        patterns: ['--secret\s+id=\S+']
      - id: mount-secret
        label: RUN --mount=type=secret di Dockerfile (env= / target=auth.json)
        required: true
        patterns: ['--mount=type=secret']
      - id: cache-to
        label: --cache-to (registry/gha/local)
        required: true
        patterns: ['--cache-to[= ]\s*type=(registry|gha|local|inline)']
      - id: cache-from
        label: --cache-from
        patterns: ['--cache-from[= ]\s*type=(registry|gha|local)', '--cache-from[= ]\s*\S+']
      - id: tag-sha
        label: Tag dengan commit SHA
        patterns: [':\$\{?\{?\s*(github\.sha|github_sha|git_sha|ci_commit_sha|ci_commit_short_sha|sha)\s*\}?\}?', '\$\(git\s+rev-parse']
      - id: push
        label: --push ke registry
        patterns: ['--push\b']
      - id: no-build-arg-token
        label: Tidak mengirim token lewat --build-arg / ARG / COPY auth.json
        forbid: true
        patterns: ['--build-arg\s+\w*(token|auth|password|secret)', '^arg\s+\w*(token|auth|password)', '^copy\s+\S*auth\.json']
    pass_score: 0.6
    solution: |
      ```bash
      docker buildx build \
        --secret id=composer_auth,env=COMPOSER_AUTH \
        --cache-from type=registry,ref=ghcr.io/acme/app:buildcache \
        --cache-to type=registry,ref=ghcr.io/acme/app:buildcache,mode=max \
        -t ghcr.io/acme/app:${GITHUB_SHA} \
        --push .
      ```
      ```dockerfile
      RUN --mount=type=secret,id=composer_auth,env=COMPOSER_AUTH \
          --mount=type=cache,target=/tmp/cache \
          composer install --no-dev --prefer-dist --no-interaction
      ```
      Composer membaca kredensial dari env `COMPOSER_AUTH` (isi JSON seperti `auth.json`). Secret mount hanya
      memberikannya ke RUN ini. Di lab, secret tidak ada di `docker history`, tidak ada di filesystem image, dan
      tidak ada di RUN berikutnya.

      Runner CI yang selalu baru tidak punya layer cache lokal. `--cache-to/--cache-from` menyimpan cache di
      registry (atau `type=gha` di GitHub Actions). Di lab, export cache `type=local` menghasilkan direktori OCI
      (`blobs`, `index.json`). Ekspor ke registry tidak kami uji di lab. `mode=max` ikut menyimpan layer stage
      perantara (vendor/assets), bukan hanya stage final.

      Tag commit SHA membuat setiap image bisa dilacak ke kode sumber dan bisa di-rollback dengan pasti.
      `latest` saja tidak cukup.
    tests:
      pass:
        - |
          docker buildx build --secret id=composer_auth,env=COMPOSER_AUTH --cache-from type=registry,ref=ghcr.io/acme/app:buildcache --cache-to type=registry,ref=ghcr.io/acme/app:buildcache,mode=max -t ghcr.io/acme/app:${GITHUB_SHA} --push .
          RUN --mount=type=secret,id=composer_auth,env=COMPOSER_AUTH composer install --no-dev
        - |
          docker build --secret id=gh,src=$HOME/.composer/auth.json --cache-to type=gha,mode=max --cache-from type=gha -t ghcr.io/acme/app:$(git rev-parse --short HEAD) --push .
          RUN --mount=type=secret,id=gh,target=/tmp/auth.json composer install
      fail:
        - |
          docker build --build-arg GITHUB_TOKEN=$GITHUB_TOKEN -t ghcr.io/acme/app:latest --push .
          ARG GITHUB_TOKEN
        - |
          docker build -t ghcr.io/acme/app:latest .
          COPY auth.json /root/.composer/auth.json
        - |
          docker build --secret id=gh,env=GH_TOKEN -t app .
          RUN composer install
    source: https://docs.docker.com/build/cache/backends/

---

# Dockerfile: Build, Cache & Multi-stage

Modul sebelumnya membahas **apa** arti setiap instruksi. Modul ini membahas **bagaimana** image dibangun dengan
cepat, kecil, dan aman: cara builder memutuskan langkah mana yang dipakai ulang, multi-stage untuk membuang alat
build, fitur BuildKit (cache mount, secret, target, export, multi-platform), dan Dockerfile Laravel production.

> [!NOTE]
> Semua angka di modul ini diukur di lab dengan Docker Engine 29.4.1 / BuildKit 0.29 pada **aplikasi Laravel 13
> nyata**: source project devops-dojo (aplikasi yang sedang kamu pakai). Source-nya disalin ke folder lab tanpa
> `.env`, `vendor`, dan `node_modules`. Dockerfile asli project dipakai sebagai baseline, lalu dibandingkan dengan versi
> perbaikan. Satu-satunya perubahan di salinan lab: font bunny dimatikan di `vite.config.js`, karena fonts.bunny.net
> sedang down (lihat bagian 6).

## 1. Build context dan alur build

```mermaid
flowchart LR
    C[Build context<br/>direktori + .dockerignore] -- dikirim --> B[BuildKit]
    D[Dockerfile] --> B
    B --> G{Graph dependency<br/>antar stage}
    G --> S1[stage vendor] & S2[stage assets]
    S1 & S2 --> F[stage final]
    F -- --load / --push / -o --> O[(image / registry / file)]
```

```bash
docker build -t app .                              # context = direktori saat ini
docker build -f docker/prod.Dockerfile -t app .    # Dockerfile di tempat lain
docker build --target assets -t app-assets .       # berhenti di satu stage
docker build --progress=plain -t app .             # tampilkan semua output & CACHED
```

- `.dockerignore` di root context berlaku untuk semua Dockerfile. `<nama>.Dockerfile.dockerignore` di sebelah
  Dockerfile berlaku khusus untuk Dockerfile itu (lab: dipakai untuk membangun dari folder terpisah).
- BuildKit hanya menjalankan stage yang dibutuhkan target (lab: stage `unused` tidak berjalan), dan stage yang
  independen berjalan **paralel** (lab: `vendor` dan `assets` bersamaan).

## 2. Cara kerja cache

| Instruksi | Cache key |
|---|---|
| `RUN` | Teks perintah + layer induk + nilai ARG/ENV yang dipakai |
| `COPY`/`ADD` | **Checksum isi file** yang disalin + metadata |
| `FROM` | Digest base image (yang ada di lokal, kecuali `--pull`) |

Begitu satu langkah **miss**, semua langkah setelahnya di stage itu ikut dibangun ulang. Hasil lab setelah satu file PHP
diubah (versi perbaikan, total 12 detik):

```text
CACHED  [vendor 3/6] COPY composer.json composer.lock ./
CACHED  [vendor 4/6] RUN --mount=type=cache,target=/tmp/cache composer install ...
RAN     [vendor 5/6] COPY . .
RAN     [vendor 6/6] RUN composer dump-autoload ...
CACHED  [assets 4/7] RUN npm ci ...           ← stage assets tidak menyalin app/
CACHED  [assets 7/7] RUN npm run build
RAN     [app 7/10]  COPY --from=vendor ...
RAN     [app 8/10]  COPY --from=assets ...    ← ikut jalan ulang karena layer sebelumnya berubah
```

> [!WARNING]
> Cache bisa membuat build **basi**. `RUN apt-get update` yang CACHED berarti index paket berumur sejak cache itu dibuat,
> dan base image lokal bisa tertinggal patch keamanan. Untuk build rilis, pakai `--pull`. Sesekali build tanpa cache untuk
> membuktikan Dockerfile masih bisa dibangun dari nol.

## 3. Urutan instruksi

```dockerfile
FROM php:8.3-fpm-alpine        # 1. base — paling jarang berubah
RUN apk add ... && docker-php-ext-install ...     # 2. paket OS & ekstensi
COPY composer.json composer.lock ./               # 3. manifest dependency
RUN composer install --no-dev ...                 # 4. install dependency
COPY . .                                          # 5. kode — paling sering berubah
```

Prinsipnya: semakin sering berubah, semakin bawah. ARG yang berubah setiap build (commit SHA, timestamp) juga
meng-invalidate RUN yang memakainya (lab: `--build-arg BUST=2` menjalankan ulang RUN). Deklarasikan ARG seperti itu
sedekat mungkin dengan pemakaiannya.

## 4. Multi-stage

```dockerfile
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM nginx:1.27-alpine
COPY --from=assets /app/public/build /usr/share/nginx/html/build
```

- Image final hanya berisi stage terakhir (atau `--target`). Tool build, `node_modules`, dan compiler tertinggal di
  stage perantara.
- `COPY --from=<stage>` atau `COPY --from=<image>` (lab: `COPY --from=composer:2 /usr/bin/composer ...`).
- Satu Dockerfile, beberapa image: devops-dojo membangun `--target app` (php-fpm) dan `--target nginx` (file statis).

## 5. Fitur BuildKit: `RUN --mount`

### Cache mount: cache tool yang bertahan antar build

```dockerfile
RUN --mount=type=cache,target=/tmp/cache composer install --no-dev --prefer-dist   # image composer
RUN --mount=type=cache,target=/root/.npm npm ci
RUN --mount=type=cache,target=/var/cache/apk apk add gcc                           # tanpa --no-cache
RUN --mount=type=cache,target=/root/.cache/pip pip install -r requirements.txt
```

| Hasil lab: `composer.json` & `package.json` diubah | Tanpa cache mount | Dengan cache mount |
|---|---|---|
| `composer install` | 23,5 s (79 paket diunduh) | **2,4 s** (0 diunduh) |
| `npm ci` | 46,0 s | **7,1 s** |

> [!INSIGHT]
> `docker build --no-cache` **juga mengosongkan** cache mount. Di lab, RUN yang menghitung isi cache mount mencetak 0 → 1
> pada build biasa, tetapi kembali 0 dengan `--no-cache`. Percobaan pertamaku membandingkan cache mount dengan
> `--no-cache`, dan hasilnya (composer 11,6 s vs 11,9 s) menyesatkan. Ukur cache mount dengan mengubah file manifest,
> lalu build biasa.
> Sumber: [Docker Docs — Optimize cache usage](https://docs.docker.com/build/cache/optimize/)

### Secret mount: kredensial hanya selama satu RUN

```dockerfile
RUN --mount=type=secret,id=npm_token,env=NPM_TOKEN npm ci          # sebagai env
RUN --mount=type=secret,id=npm_token cat /run/secrets/npm_token    # sebagai file (default)
RUN --mount=type=secret,id=composer_auth,target=/root/.composer/auth.json composer install
```

```bash
docker build --secret id=npm_token,env=NPM_TOKEN -t app .
docker build --secret id=composer_auth,src=$HOME/.composer/auth.json -t app .
```

Hasil lab:
- RUN dengan mount: `token-len=14`.
- RUN berikutnya: `NPM_TOKEN=[]` dan `/run/secrets` tidak ada.
- `docker history --no-trunc | grep supersecret` → 0.

Bandingkan dengan `--build-arg` (modul 02): token tercetak di history.

### Mount lain

| Tipe | Untuk |
|---|---|
| `type=bind` | Memakai file dari context/stage lain **tanpa** COPY (tidak menambah layer) |
| `type=ssh` | Meneruskan ssh-agent untuk `git clone` repo privat (`docker build --ssh default`) |
| `type=tmpfs` | Direktori sementara di memori |

## 6. Ukuran image: diukur di aplikasi nyata

| Image devops-dojo (lab) | Disk | Terkompresi (push/pull) |
|---|---|---|
| Dockerfile asli, target `app` | 885 MB | 223 MB |
| Dockerfile perbaikan, target `app` | **229 MB** | **53 MB** |
| Target `nginx` asli / perbaikan | 101 MB / 80,7 MB | — |

```text
$ docker history --format '{{.Size}}  {{.CreatedBy}}' app
508MB  RUN /bin/sh -c apk add --no-cache postgresql-dev libzip-dev icu-dev ... && docker-php-ext-install ...
```

Penyebabnya: di Alpine, `postgresql-dev` bergantung pada `clang`, `llvm` (llvm20-libs 170 MiB), dan `gcc` (159 MiB).
Semuanya build dependency yang tertinggal di image runtime. Perbaikannya:

```dockerfile
RUN apk add --no-cache libpq libzip icu-libs libpng libjpeg-turbo freetype \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev libzip-dev icu-dev libpng-dev libjpeg-turbo-dev freetype-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql zip intl gd bcmath opcache \
 && apk del .build-deps
```

> [!DANGER]
> **Build lolos ≠ image benar.** Di lab, variasi tanpa `libpq` eksplisit lolos build tanpa error, tetapi saat runtime:
> `Unable to load dynamic library 'pdo_pgsql' ... Error loading shared library libpq.so.5`, karena libpq ikut terhapus
> bersama `.build-deps`. Aplikasi baru gagal saat pertama kali konek ke database. Tambahkan pemeriksaan di build:
> `RUN composer check-platform-reqs --no-dev` (lab: lulus untuk versi perbaikan) dan/atau `php -m` di CI.

> [!INSIGHT]
> **Ketergantungan jaringan saat build adalah titik kegagalan.** Saat lab, build devops-dojo yang sebelumnya sukses
> tiba-tiba gagal: `laravel-vite-plugin: Failed to fetch "https://fonts.bunny.net/...": 504 Gateway Timeout`. Konfigurasi
> `fonts: [bunny(...)]` di `vite.config.js` mengunduh font setiap `npm run build`. Tidak ada kode yang berubah, tetapi
> pipeline merah karena layanan pihak ketiga. Self-host aset yang dibutuhkan build, dan perlakukan setiap unduhan saat
> build sebagai risiko ketersediaan dan supply chain.

## 7. Multi-platform dan export

```bash
docker buildx build --platform linux/amd64,linux/arm64 -t ghcr.io/acme/app:1.0 --push .
docker run --rm --platform linux/arm64 app        # lab: arch=aarch64 TARGETARCH=arm64 (emulasi QEMU)
docker build --target assets -o type=local,dest=./out .    # lab: out/app/public/build/{assets,manifest.json}
docker buildx du                                    # lab: 5.666GB build cache
```

```dockerfile
ARG TARGETARCH
RUN wget -O /usr/local/bin/tool "https://example.com/tool-linux-${TARGETARCH}" && chmod +x /usr/local/bin/tool
```

## 8. Dockerfile Laravel production (versi yang diuji di lab)

```dockerfile
# syntax=docker/dockerfile:1
ARG PHP_VERSION=8.3

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/cache \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

FROM php:${PHP_VERSION}-fpm-alpine AS app
RUN apk add --no-cache libpq libzip icu-libs libpng libjpeg-turbo freetype fcgi \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev libzip-dev icu-dev libpng-dev libjpeg-turbo-dev freetype-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql zip intl gd bcmath opcache \
 && apk del .build-deps
COPY <<EOF /usr/local/etc/php/conf.d/app.ini
memory_limit=256M
opcache.enable=1
opcache.validate_timestamps=0
EOF
RUN printf '[www]\nping.path = /ping\n' > /usr/local/etc/php-fpm.d/zz-health.conf
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app ./
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN composer check-platform-reqs --no-dev --no-interaction
USER www-data
HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 \
  CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

FROM nginx:1.27-alpine AS nginx
COPY --from=app /var/www/html/public /var/www/html/public
```

Hasil runtime di lab:
- Tanpa `APP_KEY`, entrypoint berhenti dengan pesan yang jelas.
- Dengan `APP_KEY`: `(healthy)`, log `GET /ping 200`, dan `1 www-data php-fpm: master process`. Master pun non-root,
  sehingga php-fpm memberi peringatan bahwa directive `user` diabaikan.
- `bootstrap/cache` berisi `config.php`, `routes-v7.php`, dll., hasil `config:cache` dan `route:cache` di entrypoint.
- `docker-compose.yml` dan `README.md` tidak lagi ada di image.

## 9. Ringkasan kebiasaan aman

| Kebiasaan | Kenapa |
|---|---|
| `# syntax=docker/dockerfile:1` | Fitur BuildKit terbaru (mount, heredoc) |
| Manifest dependency → install → kode | Cache install dependency bertahan saat kode berubah |
| Multi-stage: tool build tidak masuk image final | 885 MB → 229 MB di lab |
| Build deps `--virtual` + `apk del` di RUN yang sama, runtime lib eksplisit | Layer 508 MB → 18,6 MB, tanpa merusak ekstensi |
| `check-platform-reqs` / `php -m` saat build | Build lolos ≠ runtime benar |
| `RUN --mount=type=cache` | Rebuild dependency 23,5 s → 2,4 s |
| `RUN --mount=type=secret`, bukan ARG/ENV/COPY | Secret tidak ada di history maupun layer |
| `--cache-to/--cache-from` di CI | Runner baru tetap memakai cache |
| Tag commit SHA, pin base image, `--pull` untuk rilis | Bisa dilacak, reproducible, ter-patch |
| Hindari unduhan jaringan saat build | Pipeline tidak gagal karena pihak ketiga |
