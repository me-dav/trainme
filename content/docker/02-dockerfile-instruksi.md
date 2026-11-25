---
format: 1
id: docker-dockerfile-instructions
course:
  slug: docker
  title: Docker
  description: Container dari nol sampai production — CLI, image, storage, network, Compose, keamanan, dan operasional.
title: 'Dockerfile: Semua Instruksi'
summary: Pembahasan detail setiap instruksi Dockerfile — FROM, ARG, ENV, LABEL, WORKDIR, COPY, ADD, RUN, USER, EXPOSE, VOLUME, CMD, ENTRYPOINT, HEALTHCHECK, STOPSIGNAL, SHELL, ONBUILD — beserta perilaku yang diuji di lab.
order: 2
version: 1
status: verified
updated: '2026-11-20'
tested_on:
  - Docker Desktop (Windows) — Docker Engine 29.4.1, BuildKit (default builder), Dockerfile syntax 1
  - Base image alpine:3.20 (busybox ash), debian:bookworm-slim (dash), nginx:1.27-alpine, node:22-alpine
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: structure
    text: Memahami struktur Dockerfile, parser directive, dan urutan instruksi
  - id: from-arg
    text: Memakai FROM dan ARG (scope sebelum/sesudah FROM, build-arg)
  - id: env-label
    text: Membedakan ENV vs ARG dan memakai LABEL
  - id: files
    text: Menyalin file dengan COPY/ADD (semantik path, --chown, --chmod, .dockerignore)
  - id: run
    text: Menulis RUN yang benar (shell/exec form, SHELL, heredoc, layer)
  - id: user-workdir
    text: Mengatur WORKDIR dan USER
  - id: cmd-entrypoint
    text: Menguasai CMD, ENTRYPOINT, dan kombinasinya
  - id: runtime-meta
    text: Memakai EXPOSE, VOLUME, HEALTHCHECK, STOPSIGNAL, ONBUILD
  - id: review
    text: Menulis dan me-review Dockerfile yang benar untuk aplikasi nyata
sources:
  - title: Dockerfile reference
    url: https://docs.docker.com/reference/dockerfile/
  - title: Dockerfile reference — Shell and exec form
    url: https://docs.docker.com/reference/dockerfile/#shell-and-exec-form
  - title: Dockerfile reference — Understand how CMD and ENTRYPOINT interact
    url: https://docs.docker.com/reference/dockerfile/#understand-how-cmd-and-entrypoint-interact
  - title: Dockerfile reference — Understand how ARG and FROM interact
    url: https://docs.docker.com/reference/dockerfile/#understand-how-arg-and-from-interact
  - title: Dockerfile reference — COPY / ADD
    url: https://docs.docker.com/reference/dockerfile/#copy
  - title: Dockerfile reference — VOLUME (notes about specifying volumes)
    url: https://docs.docker.com/reference/dockerfile/#notes-about-specifying-volumes
  - title: Dockerfile reference — HEALTHCHECK
    url: https://docs.docker.com/reference/dockerfile/#healthcheck
  - title: Build checks (docker build --check)
    url: https://docs.docker.com/reference/build-checks/
  - title: Docker Docs — Build context & .dockerignore
    url: https://docs.docker.com/build/concepts/context/
  - title: Docker Docs — Building best practices
    url: https://docs.docker.com/build/building/best-practices/
  - title: OCI Image Spec — annotations (org.opencontainers.image.*)
    url: https://github.com/opencontainers/image-spec/blob/main/annotations.md
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: structure
    prompt: Instruksi apa yang memulai sebuah build stage dan menentukan base image (hanya boleh didahului ARG, komentar, dan parser directive)?
    answers: ['FROM']
    tests:
      fail: ['RUN', 'WORKDIR', 'BASE', 'IMAGE']
    explanation: 'Setiap `FROM` memulai stage baru. Satu Dockerfile bisa punya banyak `FROM` (multi-stage, modul berikutnya). `FROM scratch` = mulai dari image kosong. Parser directive seperti `# syntax=docker/dockerfile:1` harus berada di baris paling atas.'
    source: https://docs.docker.com/reference/dockerfile/#from

  - id: a02
    tier: A
    type: command
    objective: from-arg
    prompt: Tulis instruksi Dockerfile untuk memakai base image PHP-FPM `php:8.3-fpm-alpine`.
    answers: ['FROM php:8.3-fpm-alpine']
    patterns: ['^FROM php:8\.3-fpm-alpine( (AS|as) \w+)?$']
    tests:
      pass: ['FROM php:8.3-fpm-alpine AS base']
      fail: ['FROM php', 'IMAGE php:8.3-fpm-alpine', 'FROM php:latest']
    explanation: 'Pin tag versi (bahkan digest: `FROM alpine:3.20@sha256:d9e8...`, lab). `FROM php` = `php:latest`, yang bisa melompat ke versi mayor baru tanpa kamu sadari. Varian `-alpine` kecil tetapi memakai musl libc dan busybox, sehingga perilaku shell dan beberapa ekstensi berbeda dari varian Debian.'
    source: https://docs.docker.com/reference/dockerfile/#from

  - id: a03
    tier: A
    type: command
    objective: user-workdir
    prompt: Set direktori kerja `/var/www/html` untuk semua instruksi berikutnya **dan** saat container berjalan.
    answers: ['WORKDIR /var/www/html']
    patterns: ['^WORKDIR /var/www/html/?$']
    tests:
      fail: ['RUN cd /var/www/html', 'CD /var/www/html', 'ENV PWD=/var/www/html']
    explanation: '`WORKDIR` membuat direktori bila belum ada (lab: dibuat milik root). `RUN cd /app` **tidak** bertahan: setiap RUN adalah shell baru. Di lab, `RUN mkdir /app && cd /app` lalu `RUN pwd` → `/`. WORKDIR relatif ditumpuk: `/srv` → `app` → `sub` = `/srv/app/sub`.'
    source: https://docs.docker.com/reference/dockerfile/#workdir

  - id: a04
    tier: A
    type: command
    objective: files
    prompt: Salin **seluruh isi** build context ke WORKDIR saat ini.
    answers: ['COPY . .']
    patterns: ['^COPY (--chown=\S+ |--chmod=\S+ )*\. \.(/)?$']
    tests:
      pass: ['COPY --chown=node:node . .', 'COPY . ./']
      fail: ['COPY * .', 'ADD . /', 'COPY .. .']
    explanation: 'Sumber relatif terhadap **build context** (biasanya `.` pada `docker build .`), tujuan relatif terhadap WORKDIR. Context tidak bisa keluar dari dirinya sendiri (`..` ditolak). `.dockerignore` menentukan apa yang tidak ikut terkirim. Di lab, `.env` dan `*.md` tidak ada di image.'
    source: https://docs.docker.com/reference/dockerfile/#copy

  - id: a05
    tier: A
    type: command
    objective: env-label
    prompt: Set environment variable `APP_ENV` bernilai `production` yang berlaku saat build **dan** saat container berjalan, dengan format `key=value`.
    answers: ['ENV APP_ENV=production']
    patterns: ['^ENV APP_ENV=production$']
    tests:
      fail: ['ENV APP_ENV production', 'ARG APP_ENV=production', 'RUN export APP_ENV=production']
    explanation: 'Format lama `ENV KEY value` masih jalan, tetapi `docker build --check` memberi peringatan `LegacyKeyValueFormat` (lab). `RUN export` hanya berlaku di RUN itu sendiri. ENV tersimpan di metadata image dan terlihat lewat `docker image inspect` (lab: `DB_PASSWORD=hunter2`), jadi jangan untuk secret.'
    source: https://docs.docker.com/reference/dockerfile/#env

  - id: a06
    tier: A
    type: command
    objective: from-arg
    prompt: Deklarasikan build argument `PHP_VERSION` dengan nilai default `8.3`.
    answers: ['ARG PHP_VERSION=8.3']
    patterns: ['^ARG PHP_VERSION=8\.3$']
    tests:
      fail: ['ENV PHP_VERSION=8.3', 'ARG PHP_VERSION 8.3', 'BUILD_ARG PHP_VERSION=8.3']
    explanation: '`ARG` hanya ada **saat build**. Di lab, nilai ARG tersedia di RUN, tetapi di container yang berjalan kosong (`BUILD_ONLY=[]`). Nilainya diganti dengan `docker build --build-arg PHP_VERSION=8.4` (lab: `overridden`).'
    source: https://docs.docker.com/reference/dockerfile/#arg

  - id: a07
    tier: A
    type: command
    objective: from-arg
    prompt: Build image dari context direktori saat ini dengan tag `myapp`, mengganti build arg `PHP_VERSION` menjadi `8.4`.
    answers: ['docker build --build-arg PHP_VERSION=8.4 -t myapp .']
    patterns: ['^docker (image |buildx )?build (?=.*--build-arg[= ]PHP_VERSION=8\.4( |$))(?=.*(-t|--tag)[= ]?myapp( |$))((--build-arg[= ]PHP_VERSION=8\.4|(-t|--tag)[= ]?myapp) )+\.$']
    tests:
      pass: ['docker build -t myapp --build-arg PHP_VERSION=8.4 .', 'docker buildx build --build-arg=PHP_VERSION=8.4 --tag myapp .']
      fail: ['docker build -e PHP_VERSION=8.4 -t myapp .', 'docker build --build-arg PHP_VERSION=8.4 -t myapp', 'docker run --build-arg PHP_VERSION=8.4 myapp']
    explanation: 'Argumen terakhir adalah **context** (`.`), dan wajib ada. `--build-arg` untuk ARG yang tidak dideklarasikan di Dockerfile memberi peringatan dan diabaikan.'
    source: https://docs.docker.com/reference/cli/docker/buildx/build/

  - id: a08
    tier: A
    type: command
    objective: cmd-entrypoint
    prompt: Tulis `CMD` dalam **exec form** untuk menjalankan `php-fpm` dengan opsi `-F` (foreground).
    answers: ['CMD ["php-fpm", "-F"]']
    patterns: ['^CMD \[php-fpm, ?-F\]$']
    tests:
      pass: ['CMD ["php-fpm","-F"]']
      fail: ['CMD php-fpm -F', 'RUN ["php-fpm", "-F"]', 'CMD [php-fpm -F]']
    explanation: 'Exec form adalah array JSON, dan **harus** memakai kutip ganda. `["a","b"]` dengan kutip tunggal bukan JSON yang valid, sehingga Docker diam-diam memperlakukannya sebagai shell form. Exec form tidak memakai shell: program menjadi PID 1 dan menerima sinyal langsung. Peringatan `JSONArgsRecommended` dari `docker build --check` (lab) muncul untuk CMD shell form.'
    source: https://docs.docker.com/reference/dockerfile/#shell-and-exec-form

  - id: a09
    tier: A
    type: command
    objective: user-workdir
    prompt: Jalankan instruksi berikutnya (dan container) sebagai user `www-data`.
    answers: ['USER www-data']
    patterns: ['^USER www-data(:www-data)?$']
    tests:
      pass: ['USER www-data:www-data']
      fail: ['RUN su www-data', 'USER root', 'RUN chown www-data /']
    explanation: 'Berlaku untuk RUN, CMD, dan ENTRYPOINT setelahnya. Di lab, setelah `USER app`, RUN dan container berjalan sebagai `uid=10001(app)`, dan `touch /etc/x` ditolak. User harus sudah ada di image (dibuat dengan `adduser`/`useradd`), atau ditulis dalam bentuk angka (`USER 10001`). `docker run -u 0` masih bisa menimpanya.'
    source: https://docs.docker.com/reference/dockerfile/#user

  - id: a10
    tier: A
    type: command
    objective: runtime-meta
    prompt: Dokumentasikan di Dockerfile bahwa aplikasi listen di port **9000/tcp**.
    answers: ['EXPOSE 9000']
    patterns: ['^EXPOSE 9000(/tcp)?$']
    tests:
      pass: ['EXPOSE 9000/tcp']
      fail: ['PORT 9000', 'EXPOSE 9000:9000', 'PUBLISH 9000']
    explanation: 'EXPOSE adalah metadata saja; publish ke host tetap butuh `-p` (modul 01). Bisa menulis banyak port dan protokol, mis. `EXPOSE 8080 53/udp` (lab: `{"53/udp":{},"8080/tcp":{}}`). Format `HOST:CONTAINER` tidak berlaku di sini.'
    source: https://docs.docker.com/reference/dockerfile/#expose

  - id: a11
    tier: A
    type: text
    objective: cmd-entrypoint
    prompt: Instruksi apa yang memberi **perintah default** container, yang otomatis **diganti** bila kamu menulis argumen setelah nama image di `docker run`?
    answers: ['CMD']
    tests:
      fail: ['ENTRYPOINT', 'RUN', 'EXEC', 'START']
    explanation: '`docker run img echo hi` mengganti CMD. ENTRYPOINT tidak diganti oleh argumen (hanya oleh `--entrypoint`). Bila ada beberapa CMD, hanya yang **terakhir** berlaku (lab: `second`).'
    source: https://docs.docker.com/reference/dockerfile/#cmd

  - id: a12
    tier: A
    type: text
    objective: run
    prompt: Instruksi apa yang dieksekusi **saat build** dan hasilnya disimpan sebagai layer baru di image?
    answers: ['RUN']
    tests:
      fail: ['CMD', 'ENTRYPOINT', 'EXEC', 'COPY']
    explanation: 'RUN = saat build (install paket, compile). CMD/ENTRYPOINT = saat container dijalankan. Setiap RUN, COPY, dan ADD menambah layer. Instruksi lain (ENV, LABEL, CMD, ...) hanya mengubah metadata (lab: layer `0B`).'
    source: https://docs.docker.com/reference/dockerfile/#run

  - id: a13
    tier: A
    type: command
    objective: structure
    prompt: Build image dari direktori saat ini dengan tag `myapp:1.0`.
    answers: ['docker build -t myapp:1.0 .']
    patterns: ['^docker (image |buildx )?build (-t|--tag)[= ]?myapp:1\.0 \.$', '^docker (image |buildx )?build \. (-t|--tag)[= ]?myapp:1\.0$']
    tests:
      pass: ['docker build --tag myapp:1.0 .', 'docker buildx build -t myapp:1.0 .']
      fail: ['docker build myapp:1.0', 'docker build -t myapp:1.0', 'docker run -t myapp:1.0 .']
    explanation: '`-f path/Dockerfile` untuk Dockerfile di lokasi lain. `--progress=plain` menampilkan output setiap langkah (lab: baris `CACHED`). Tag tambahan: `-t myapp:1.0 -t myapp:latest`.'
    source: https://docs.docker.com/reference/cli/docker/buildx/build/

  - id: a14
    tier: A
    type: command
    objective: env-label
    prompt: Tambahkan label OCI `org.opencontainers.image.version` bernilai `1.4.2` ke image.
    answers: ['LABEL org.opencontainers.image.version="1.4.2"']
    patterns: ['^LABEL org\.opencontainers\.image\.version=1\.4\.2$']
    tests:
      pass: ['LABEL org.opencontainers.image.version=1.4.2']
      fail: ['ENV org.opencontainers.image.version=1.4.2', 'LABEL version 1.4.2', 'TAG 1.4.2']
    explanation: 'Label adalah metadata key=value (lab: `docker inspect -f ''{{json .Config.Labels}}''`). Kunci standar OCI: `.source` (repo), `.version`, `.revision` (commit), `.created`. Registry seperti GHCR memakai `.source` untuk menautkan image ke repository.'
    source: https://github.com/opencontainers/image-spec/blob/main/annotations.md

  - id: a15
    tier: A
    type: text
    objective: files
    prompt: Nama file di root build context yang menentukan file/direktori yang **tidak** dikirim ke builder?
    answers: ['.dockerignore']
    tests:
      fail: ['.gitignore', '.dockerexclude', 'Dockerfile.ignore', '.buildignore']
    explanation: 'Syntax mirip `.gitignore` (`node_modules`, `.env`, `*.md`, `!README.md`). Fungsinya dua: context lebih kecil (build lebih cepat) dan secret tidak ikut masuk ke image lewat `COPY . .`. Di lab, `.env` tidak ada di image walau ada `COPY . /ctx`. `.dockerignore` dan `Dockerfile` sendiri tetap ikut ter-COPY kecuali ikut di-ignore.'
    source: https://docs.docker.com/build/concepts/context/#dockerignore-files

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: cmd-entrypoint
    prompt: 'Image `debian:bookworm-slim` dengan `CMD sleep 300` (shell form). Proses apa yang menjadi **PID 1** di container?'
    answers: ['/bin/sh -c sleep 300', '/bin/sh', 'sh']
    patterns: ['^(/bin/)?sh( -c( sleep 300)?)?$']
    tests:
      pass: ['/bin/sh -c', 'sh -c sleep 300']
      fail: ['sleep', 'sleep 300', 'tini', 'bash']
    explanation: 'Shell form disimpan sebagai `["/bin/sh","-c","sleep 300"]` (lab). Di Debian, `/bin/sh` = dash, yang tetap menjadi PID 1 dengan `sleep` sebagai anaknya (lab: `1 /bin/sh -c sleep 300`, `7 sleep 300`). Di Alpine, busybox ash meng-exec perintah tunggal, sehingga `sleep` yang menjadi PID 1. Perilakunya bergantung base image. Exec form menghilangkan ketidakpastian ini.'
    source: https://docs.docker.com/reference/dockerfile/#shell-and-exec-form

  - id: b02
    tier: B
    type: text
    objective: cmd-entrypoint
    prompt: '`ENTRYPOINT ["echo", "ep:"]` dan `CMD ["default-arg"]`. Apa output `docker run img custom1`?'
    answers: ['ep: custom1']
    tests:
      fail: ['ep: default-arg', 'custom1', 'ep: default-arg custom1', 'ep:']
    explanation: 'Dengan ENTRYPOINT exec form, CMD menjadi **argumen default**, dan argumen `docker run` menggantikan CMD. Di lab: tanpa argumen → `ep: default-arg`, dengan `custom1 custom2` → `ep: custom1 custom2`. Pola ini dipakai image seperti `nginx` dan `redis`: ENTRYPOINT berisi skrip, CMD berisi perintah default.'
    source: https://docs.docker.com/reference/dockerfile/#understand-how-cmd-and-entrypoint-interact

  - id: b03
    tier: B
    type: text
    objective: cmd-entrypoint
    prompt: '`ENTRYPOINT echo ep-shell` (**shell form**) dan `CMD ["x"]`. Apa output `docker run img arg-too`?'
    answers: ['ep-shell']
    tests:
      fail: ['ep-shell arg-too', 'arg-too', 'ep-shell x', 'x']
    explanation: 'ENTRYPOINT shell form mengabaikan CMD **dan** argumen `docker run`, karena semuanya hanya menjadi argumen `sh -c` yang tidak dipakai. Di lab, kedua run mencetak `ep-shell`. Ditambah masalah PID 1/sinyal, hampir tidak ada alasan memakai ENTRYPOINT shell form.'
    source: https://docs.docker.com/reference/dockerfile/#understand-how-cmd-and-entrypoint-interact

  - id: b04
    tier: B
    type: command
    objective: cmd-entrypoint
    prompt: Image `myapp` punya ENTRYPOINT. Jalankan image itu secara interaktif dengan ENTRYPOINT **diganti** `sh`, untuk debugging.
    answers: ['docker run --rm -it --entrypoint sh myapp']
    patterns: ['^docker (container )?run (?=.*--entrypoint[= ]?(sh|/bin/sh)( |$))((--rm|-it|-ti|-i -t|--entrypoint[= ]?(sh|/bin/sh)) )+myapp$']
    tests:
      pass: ['docker run -it --rm --entrypoint=/bin/sh myapp', 'docker run -it --entrypoint sh myapp']
      fail: ['docker run --rm -it myapp sh', 'docker exec -it myapp sh', 'docker run --rm -it --cmd sh myapp']
    explanation: 'Bila image punya ENTRYPOINT, `docker run -it myapp sh` hanya mengirim `sh` sebagai **argumen** ke entrypoint, bukan membuka shell. `--entrypoint` menggantinya. Di lab, `--entrypoint date dj-lab/epcmd +%Y` → `2026`: argumen setelah image menjadi argumen untuk entrypoint baru.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#entrypoint

  - id: b05
    tier: B
    type: text
    objective: cmd-entrypoint
    prompt: '`ENV GREETING=halo` lalu `CMD ["echo", "$GREETING"]`. Apa output container?'
    answers: ['$GREETING']
    tests:
      fail: ['halo', '', 'GREETING']
    explanation: 'Exec form tidak melewati shell, sehingga variabel **tidak** diekspansi (lab: `$GREETING`). Butuh ekspansi? Pakai `CMD ["sh", "-c", "echo $GREETING"]` (lab: `halo`) atau skrip entrypoint. Variabel di instruksi Dockerfile sendiri (`WORKDIR ${APP_HOME}`, `COPY ... ${APP_HOME}/`) diekspansi oleh builder (lab: `running from /opt/app`).'
    source: https://docs.docker.com/reference/dockerfile/#shell-and-exec-form

  - id: b06
    tier: B
    type: text
    objective: from-arg
    prompt: 'Dockerfile berisi `ARG BUILD_ONLY=x`, dan nilainya dipakai di RUN. Di container yang **berjalan**, apa hasil `echo $BUILD_ONLY`?'
    answers: ['kosong', 'empty', 'string kosong']
    patterns: ['^(\(?kosong\)?|empty|tidak ada( apa-apa)?|string kosong|nothing|"")$']
    tests:
      pass: ['(kosong)', 'tidak ada']
      fail: ['x', 'from-arg', 'error']
    explanation: 'Di lab: `build: BUILD_ONLY=from-arg`, tetapi `runtime: BUILD_ONLY=[]`. Bila perlu di runtime, salin ARG ke ENV: `ARG APP_VERSION` lalu `ENV APP_VERSION=${APP_VERSION}`.'
    source: https://docs.docker.com/reference/dockerfile/#arg

  - id: b07
    tier: B
    type: text
    objective: from-arg
    prompt: '`ARG V=3.20` ditulis **sebelum** `FROM alpine:${V}`. Tanpa menulis `ARG V` lagi setelah FROM, berapa nilai `$V` di RUN pada stage itu?'
    answers: ['kosong', 'empty', 'string kosong']
    patterns: ['^(\(?kosong\)?|empty|tidak ada( apa-apa)?|string kosong|nothing|"")$']
    tests:
      fail: ['3.20', 'alpine:3.20', 'V']
    explanation: 'ARG sebelum FROM hanya bisa dipakai **di baris FROM**. Untuk memakainya di dalam stage, deklarasikan ulang `ARG V` (tanpa nilai) setelah FROM. Di lab: tanpa deklarasi ulang → `V after FROM=[]`, dengan `ARG ALPINE_VERSION` setelah FROM → `3.20`.'
    source: https://docs.docker.com/reference/dockerfile/#understand-how-arg-and-from-interact

  - id: b08
    tier: B
    type: text
    objective: from-arg
    prompt: 'Token dikirim lewat `docker build --build-arg NPM_TOKEN=...` dan dipakai di `RUN`. Apakah nilainya bisa dilihat di image lewat `docker history`? (ya/tidak)'
    answers: ['ya', 'yes', 'bisa']
    tests:
      fail: ['tidak', 'no', 'tidak bisa']
    explanation: 'Di lab, `docker history --no-trunc` menampilkan `NPM_TOKEN=supersecret123` di baris RUN. Siapa pun yang bisa pull image bisa membacanya. `docker build --check` memberi peringatan `SecretsUsedInArgOrEnv`. Cara yang benar: `RUN --mount=type=secret` (modul berikutnya).'
    source: https://docs.docker.com/reference/build-checks/secrets-used-in-arg-or-env/

  - id: b09
    tier: B
    type: text
    objective: files
    prompt: 'Setelah `USER app`, instruksi `COPY src/a.txt /home/app/` (tanpa `--chown`) menghasilkan file **milik** siapa?'
    answers: ['root', 'root:root']
    tests:
      fail: ['app', 'app:app', 'www-data', 'node']
    explanation: '`USER` tidak memengaruhi COPY/ADD. File selalu milik root kecuali diberi `--chown` (lab: `a-default.txt` → `root root`, `a-chown.txt` → `app app`). Akibatnya, aplikasi non-root sering gagal menulis ke direktori yang di-COPY. `--chmod=640` mengatur mode (lab: `-rw-r-----`).'
    source: https://docs.docker.com/reference/dockerfile/#copy---chown---chmod

  - id: b10
    tier: B
    type: command
    objective: files
    prompt: Salin seluruh context ke WORKDIR dengan pemilik `www-data:www-data` dalam **satu** instruksi (tanpa RUN chown terpisah).
    answers: ['COPY --chown=www-data:www-data . .']
    patterns: ['^COPY (--chmod=\S+ )?--chown=www-data(:www-data)? (--chmod=\S+ )?\. \.$']
    tests:
      pass: ['COPY --chown=www-data . .']
      fail: ['COPY . . --chown=www-data:www-data', 'COPY --user=www-data . .', 'COPY --chown www-data . .']
    explanation: '`COPY . .` lalu `RUN chown -R www-data .` **menggandakan** ukuran data. RUN chown membuat salinan setiap file di layer baru, sama seperti jebakan `RUN rm` (soal b13). `--chown` menetapkan pemilik di layer yang sama.'
    source: https://docs.docker.com/reference/dockerfile/#copy---chown---chmod

  - id: b11
    tier: B
    type: text
    objective: files
    prompt: '`src/` berisi `a.txt`. Setelah `COPY src /dest1`, di path mana `a.txt` berada?'
    answers: ['/dest1/a.txt']
    tests:
      fail: ['/dest1/src/a.txt', '/src/a.txt', '/dest1']
    explanation: 'COPY sebuah direktori menyalin **isinya**, bukan direktori itu sendiri, berbeda dengan `cp -r` (lab: `/dest1/a.txt`, `/dest1/inner/b.txt`). Untuk satu file: tujuan berakhiran `/` berarti "masuk ke direktori ini" (lab: `/etc/app/app.conf`). Tanpa `/`, tujuan menjadi nama file (lab: `/etc/app2` adalah file).'
    source: https://docs.docker.com/reference/dockerfile/#copy

  - id: b12
    tier: B
    type: text
    objective: files
    prompt: '`ADD pkg.tar.gz /added/` untuk file tar **lokal** di context. Apa yang ada di `/added/` hasilnya?'
    answers: ['isi arsip yang diekstrak', 'diekstrak', 'pkg/file.txt']
    patterns: ['^(isi(nya)? )?(arsip |tar )?(yang )?(di)?ekstrak', '^/?(added/)?pkg/file\.txt$', 'extract']
    tests:
      pass: ['/added/pkg/file.txt', 'isinya diekstrak']
      fail: ['pkg.tar.gz', '/added/pkg.tar.gz', 'file tar utuh']
    explanation: 'ADD otomatis mengekstrak arsip lokal (lab: `/added/pkg/file.txt`), sedangkan COPY menyalin file apa adanya (lab: `/copied/pkg.tar.gz`). ADD juga bisa mengunduh URL atau Git repo. Rekomendasi resmi: pakai **COPY** kecuali memang butuh fitur ADD, supaya perilakunya jelas. Untuk URL, pakai `ADD --checksum=sha256:...`.'
    source: https://docs.docker.com/reference/dockerfile/#add

  - id: b13
    tier: B
    type: text
    objective: run
    prompt: 'Satu `RUN` membuat file 30 MB, lalu `RUN rm` di instruksi **berikutnya** menghapusnya. Apakah image lebih kecil dibanding tanpa `rm`? (ya/tidak)'
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Layer bersifat append-only, dan `rm` di layer baru hanya menyembunyikan file itu. Di lab dengan data acak: dua RUN → image **33,6 MB**, satu RUN (`buat && rm`) → **3,6 MB** (sama dengan alpine). `docker history` tetap menunjukkan layer 31.5MB. Karena itu, cleanup (`rm -rf /var/lib/apt/lists/*`, cache) harus ada di RUN yang sama.'
    source: https://docs.docker.com/build/building/best-practices/

  - id: b14
    tier: B
    type: command
    objective: runtime-meta
    prompt: 'Tulis HEALTHCHECK yang memanggil `http://127.0.0.1/health` dengan `wget -qO-` setiap **30s**, timeout **3s**, dan dianggap unhealthy setelah **3** kali gagal.'
    answers: ['HEALTHCHECK --interval=30s --timeout=3s --retries=3 CMD wget -qO- http://127.0.0.1/health || exit 1']
    patterns: ['^HEALTHCHECK (?=.*--interval=30s( |$))(?=.*--timeout=3s( |$))(?=.*--retries=3( |$))((--interval=30s|--timeout=3s|--retries=3|--start-period=\d+s|--start-interval=\d+s) )+CMD (wget -q(O-| -O ?-|O ?-) |wget --spider -q |curl -fsS? |curl -f )http://(127\.0\.0\.1|localhost)/health( >/dev/null)?( \|\| exit 1)?$']
    tests:
      pass: ['HEALTHCHECK --retries=3 --interval=30s --timeout=3s --start-period=10s CMD wget -qO- http://127.0.0.1/health || exit 1', 'HEALTHCHECK --interval=30s --timeout=3s --retries=3 CMD curl -f http://localhost/health']
      fail: ['HEALTHCHECK CMD wget -qO- http://127.0.0.1/health', 'HEALTHCHECK --interval=30 --timeout=3 --retries=3 CMD wget -qO- http://127.0.0.1/health', 'RUN wget -qO- http://127.0.0.1/health']
    explanation: 'Exit 0 = sehat, 1 = tidak sehat. Durasi wajib bersatuan (`30s`). Di lab, statusnya `(health: starting)` → `(healthy)`, lalu setelah `index.html` dihapus → `(unhealthy)` dengan `FailingStreak=3`. Image alpine biasanya tidak punya curl, jadi pakai wget. `--start-period` memberi waktu aplikasi untuk boot.'
    source: https://docs.docker.com/reference/dockerfile/#healthcheck

  - id: b15
    tier: B
    type: text
    objective: runtime-meta
    prompt: Container berstatus `(unhealthy)`. Apakah Docker Engine biasa (tanpa Swarm/orchestrator) **otomatis me-restart**-nya? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab, container `unhealthy` tetap `status=running`. Restart policy hanya bereaksi saat proses **keluar**. Status health dipakai oleh orchestrator (Swarm, Kubernetes punya probe sendiri), `depends_on: condition: service_healthy` di Compose, load balancer, dan monitoring.'
    source: https://docs.docker.com/reference/dockerfile/#healthcheck

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: review
    prompt: |
      Tulis Dockerfile (satu instruksi per baris) untuk API Node.js `server.js` yang listen di port 3000 dan punya
      endpoint `/health`. Syarat:
      - base image versi pasti (Alpine)
      - dependency di-install dengan cara reproducible, memanfaatkan cache layer
      - berjalan sebagai user non-root bawaan image (`node`)
      - mode production
      - ada healthcheck
      - proses utama menerima sinyal dengan benar
    checklist:
      - id: from
        label: FROM node:<versi>-alpine (bukan latest)
        required: true
        patterns: ['^from\s+node:\d+(\.\d+)*-alpine\S*(\s+as\s+\w+)?$']
      - id: workdir
        label: WORKDIR /app
        patterns: ['^workdir\s+/\S+']
      - id: deps-first
        label: COPY package*.json lebih dulu (cache dependency)
        patterns: ['^copy\s+(--\S+\s+)*package(\*|-lock)?\.json']
      - id: npm-ci
        label: RUN npm ci (lockfile, reproducible)
        patterns: ['^run\s+npm\s+ci\b']
      - id: copy-rest
        label: COPY sisa kode (dengan --chown=node:node)
        patterns: ['^copy\s+(--chown=node(:node)?\s+)?\.\s+\.']
      - id: env
        label: ENV NODE_ENV=production
        patterns: ['^env\s+node_env=production']
      - id: user
        label: USER node
        required: true
        patterns: ['^user\s+node$']
      - id: expose
        label: EXPOSE 3000
        patterns: ['^expose\s+3000']
      - id: health
        label: HEALTHCHECK ke /health
        patterns: ['^healthcheck\s+.*/health']
      - id: cmd
        label: CMD exec form ["node", "server.js"]
        required: true
        patterns: ['^cmd\s+\[\s*"node"\s*,\s*"server\.js"\s*\]']
      - id: no-latest
        label: Tidak memakai node tanpa tag / latest
        forbid: true
        patterns: ['^from\s+node(:latest)?\s*$']
      - id: no-secret
        label: Tidak menaruh secret di ARG/ENV
        forbid: true
        patterns: ['^(env|arg)\s+\w*(token|password|secret)\w*\s*=']
    pass_score: 0.6
    solution: |
      ```dockerfile
      FROM node:22-alpine
      WORKDIR /app
      ENV NODE_ENV=production
      COPY package.json package-lock.json ./
      RUN npm ci --omit=dev
      COPY --chown=node:node . .
      USER node
      EXPOSE 3000
      HEALTHCHECK --interval=30s --timeout=3s --retries=3 CMD wget -qO- http://127.0.0.1:3000/health || exit 1
      CMD ["node", "server.js"]
      ```
      ```text
      # .dockerignore
      node_modules
      .env
      .git
      ```
      Hasil build di lab:
      - Container `(healthy)`.
      - `ps` → `1 node node server.js`: PID 1 adalah node dan berjalan sebagai `uid=1000(node)`.
      - `docker stop` memicu handler SIGTERM aplikasi (`SIGTERM, bye`, exit 0 dalam 1 detik).
      - `.env` tidak ada di `/app`.

      Urutan instruksi mengikuti seberapa sering sesuatu berubah. Setelah `server.js` diubah, build ulang
      menunjukkan `WORKDIR`, `COPY package*.json`, dan `RUN npm ci` **CACHED**, dan hanya `COPY . .` yang dijalankan
      ulang. `npm ci` memakai lockfile persis, sedangkan `npm install` bisa mengubah versi.
    tests:
      pass:
        - |
          FROM node:22-alpine
          WORKDIR /app
          ENV NODE_ENV=production
          COPY package.json package-lock.json ./
          RUN npm ci --omit=dev
          COPY --chown=node:node . .
          USER node
          EXPOSE 3000
          HEALTHCHECK --interval=30s CMD wget -qO- http://127.0.0.1:3000/health || exit 1
          CMD ["node", "server.js"]
        - |
          FROM node:22.11-alpine3.20
          WORKDIR /srv/api
          COPY package*.json ./
          RUN npm ci --omit=dev
          COPY . .
          USER node
          CMD ["node", "server.js"]
      fail:
        - |
          FROM node
          COPY . /app
          RUN npm install
          CMD node server.js
        - |
          FROM node:22-alpine
          WORKDIR /app
          COPY . .
          RUN npm ci
          CMD ["node", "server.js"]
        - |
          FROM node:22-alpine
          ENV NPM_TOKEN=abc123
          WORKDIR /app
          COPY package*.json ./
          RUN npm ci
          COPY . .
          USER node
          CMD ["node", "server.js"]
    source: https://docs.docker.com/build/building/best-practices/

  - id: c02
    tier: C
    type: scenario
    objective: review
    prompt: |
      Review Dockerfile di bawah. Tulis versi perbaikannya (instruksi per baris), dan sertakan isi `.dockerignore`.
      Token `API_TOKEN` dibutuhkan aplikasi saat **runtime**.
    context: |
      FROM node
      COPY . /app
      RUN cd /app
      RUN npm install
      ENV API_TOKEN=sk_live_123
      CMD npm start
    checklist:
      - id: pin
        label: FROM dengan tag versi
        required: true
        patterns: ['^from\s+node:\d+']
      - id: workdir
        label: WORKDIR menggantikan RUN cd
        required: true
        patterns: ['^workdir\s+/app']
      - id: deps-cache
        label: COPY package*.json sebelum COPY kode
        patterns: ['^copy\s+(--\S+\s+)*package']
      - id: ci
        label: npm ci (lockfile)
        patterns: ['^run\s+npm\s+ci\b']
      - id: dockerignore
        label: .dockerignore berisi node_modules dan .env
        patterns: ['^node_modules/?$', '^\.env\S*$']
      - id: user
        label: USER non-root
        patterns: ['^user\s+(node|\d{3,})']
      - id: cmd-exec
        label: CMD exec form
        patterns: ['^cmd\s+\[']
      - id: no-run-cd
        label: Tidak mengandalkan RUN cd (tidak bertahan antar RUN)
        forbid: true
        patterns: ['^run\s+cd\s+\S+\s*$']
      - id: no-env-secret
        label: Tidak menyimpan token di ENV image
        forbid: true
        patterns: ['^env\s+api_token\s*=']
      - id: no-untagged
        label: Tidak FROM node tanpa tag
        forbid: true
        patterns: ['^from\s+node\s*$']
    pass_score: 0.6
    solution: |
      ```dockerfile
      FROM node:22-alpine
      WORKDIR /app
      ENV NODE_ENV=production
      COPY package.json package-lock.json ./
      RUN npm ci --omit=dev
      COPY --chown=node:node . .
      USER node
      CMD ["node", "server.js"]
      ```
      ```text
      # .dockerignore
      node_modules
      .env
      .git
      ```
      ```bash
      docker run -d --env-file .env.production myorg/api:1.0     # token saat runtime
      ```
      Masalah di file asli:
      1. `FROM node` = `latest`.
      2. `RUN cd /app` tidak berefek pada RUN berikutnya (lab: `pwd` → `/`), sehingga `npm install` berjalan di `/`.
      3. `COPY . .` sebelum install membuat cache dependency hangus setiap kali kode berubah.
      4. Tanpa `.dockerignore`, `node_modules` lokal (biner OS lain) dan `.env` ikut masuk ke image.
      5. `ENV API_TOKEN` membakar token ke metadata image. Di lab, `docker image inspect` menampilkan ENV apa adanya.
      6. Berjalan sebagai root.
      7. Shell form CMD menjalankan `npm` lewat `sh` (di image Debian, `sh` = PID 1), dan npm sendiri tidak meneruskan
         sinyal dengan andal. Jalankan `node` langsung.
    tests:
      pass:
        - |
          FROM node:22-alpine
          WORKDIR /app
          COPY package*.json ./
          RUN npm ci --omit=dev
          COPY --chown=node:node . .
          USER node
          CMD ["node", "server.js"]
          node_modules
          .env
        - |
          FROM node:20.18-alpine
          WORKDIR /app
          COPY package.json package-lock.json ./
          RUN npm ci
          COPY . .
          CMD ["npm", "start"]
      fail:
        - |
          FROM node
          WORKDIR /app
          COPY . .
          RUN npm install
          CMD ["node", "server.js"]
        - |
          FROM node:22-alpine
          COPY . /app
          RUN cd /app
          RUN npm ci
          CMD ["node", "server.js"]
        - |
          FROM node:22-alpine
          WORKDIR /app
          COPY . .
          ENV API_TOKEN=sk_live_123
          CMD ["node", "server.js"]
    source: https://docs.docker.com/build/building/best-practices/

  - id: c03
    tier: C
    type: scenario
    objective: cmd-entrypoint
    prompt: |
      Image Laravel (`php:8.3-fpm-alpine`) harus: menjalankan `php artisan migrate --force` hanya saat start sebagai
      server (perintah default `php-fpm`), tetapi tetap bisa dipakai untuk perintah lain seperti
      `docker run img php artisan tinker` **tanpa** migrasi. Tulis `docker-entrypoint.sh` dan baris Dockerfile yang
      terkait (satu per baris), dengan sinyal sampai ke php-fpm.
    checklist:
      - id: shebang
        label: Shebang sh
        patterns: ['^#!\s*/bin/(sh|bash)']
      - id: set-e
        label: set -e (gagal migrasi = container berhenti)
        required: true
        patterns: ['^set\s+-\S*e']
      - id: conditional
        label: Migrasi hanya bila perintahnya php-fpm (cek $1)
        required: true
        patterns: ['(if|\[\[?|case).*"?\$\{?1\}?"?\s*(=|==|in)\s*["'']?php-fpm', 'case\s+"?\$\{?1\}?"?\s+in']
      - id: migrate
        label: php artisan migrate --force
        patterns: ['php\s+artisan\s+migrate\s+--force']
      - id: exec
        label: exec "$@" (proses utama menggantikan shell)
        required: true
        patterns: ['^exec\s+"\$@"']
      - id: copy-chmod
        label: COPY skrip dengan permission executable
        patterns: ['^copy\s+--chmod=(0?755|\+x|u\+x|a\+x)\s+\S*entrypoint', 'chmod\s+(\+x|0?755)\s+\S*entrypoint']
      - id: entrypoint
        label: ENTRYPOINT exec form ke skrip
        required: true
        patterns: ['^entrypoint\s+\[\s*"[^"]*entrypoint[^"]*"\s*\]']
      - id: cmd
        label: CMD ["php-fpm"] sebagai default
        patterns: ['^cmd\s+\[\s*"php-fpm"']
      - id: no-shell-entrypoint
        label: Tidak memakai ENTRYPOINT shell form
        forbid: true
        patterns: ['^entrypoint\s+[^\[\s]']
    pass_score: 0.6
    solution: |
      ```sh
      #!/bin/sh
      set -e
      if [ "$1" = "php-fpm" ]; then
        php artisan migrate --force
        php artisan config:cache
      fi
      exec "$@"
      ```
      ```dockerfile
      COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
      ENTRYPOINT ["docker-entrypoint.sh"]
      CMD ["php-fpm"]
      ```
      Pola ini dipakai image resmi seperti postgres dan redis:
      - ENTRYPOINT (exec form) menjalankan skrip.
      - CMD menjadi `$@`, yaitu perintah default.
      - `exec "$@"` membuat php-fpm **menggantikan** shell sebagai PID 1, sehingga sinyal dari `docker stop` sampai.
        Di lab, `ps` menunjukkan `1 sleep 300` (perintah CMD), bukan skrip entrypoint.
      - `docker run img php artisan tinker` mengganti CMD, sehingga `$1` = `php` dan migrasi dilewati. Di lab,
        `docker run dj-lab/entry echo override-cmd` tetap menjalankan entrypoint, lalu perintah pengganti.
      - `set -e` membuat migrasi yang gagal menghentikan container, alih-alih menjalankan app dengan skema yang salah.
      - `COPY --chmod=755` penting, karena file dari Windows/Git bisa kehilangan bit executable. Sebaliknya, file dari
        context Windows di lab malah mendapat 755 untuk semua file. Jangan andalkan permission host.

      Di production dengan banyak replica, jalankan migrasi sebagai job terpisah (modul deploy) agar tidak berlomba.
    tests:
      pass:
        - |
          #!/bin/sh
          set -e
          if [ "$1" = "php-fpm" ]; then
            php artisan migrate --force
          fi
          exec "$@"
          COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
          ENTRYPOINT ["docker-entrypoint.sh"]
          CMD ["php-fpm"]
        - |
          #!/bin/sh
          set -eu
          case "$1" in
            php-fpm) php artisan migrate --force ;;
          esac
          exec "$@"
          ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
      fail:
        - |
          #!/bin/sh
          php artisan migrate --force
          php-fpm
          ENTRYPOINT /usr/local/bin/docker-entrypoint.sh
        - |
          #!/bin/sh
          set -e
          php artisan migrate --force
          exec "$@"
          ENTRYPOINT ["docker-entrypoint.sh"]
        - |
          set -e
          if [ "$1" = "php-fpm" ]; then php artisan migrate --force; fi
          "$@"
          ENTRYPOINT ["docker-entrypoint.sh"]
    source: https://docs.docker.com/reference/dockerfile/#entrypoint

---

# Dockerfile: Semua Instruksi

Dockerfile adalah resep image: teks yang dibaca builder dari atas ke bawah, satu instruksi per baris. Setiap
instruksi entah menambah **layer** filesystem (RUN, COPY, ADD), entah mengubah **metadata** image (ENV, CMD, USER, ...).
Modul ini membahas **setiap** instruksi secara detail. Optimasi build (cache, multi-stage, BuildKit) dibahas di
modul berikutnya.

> [!NOTE]
> Semua perilaku diuji dengan Docker Engine 29.4.1 (BuildKit) pada base image `alpine:3.20`, `debian:bookworm-slim`,
> `nginx:1.27-alpine`, dan `node:22-alpine`. Contoh "lab:" di bawah adalah output asli. Instruksi tidak peka huruf
> besar/kecil (lab: `from`/`expose`/`cmd` huruf kecil tetap jalan), tetapi konvensinya HURUF BESAR.

## 0. Struktur file dan peta instruksi

```dockerfile
# syntax=docker/dockerfile:1          ← parser directive: baris pertama, sebelum apa pun
ARG PHP_VERSION=8.3                   ← ARG sebelum FROM: hanya untuk baris FROM
FROM php:${PHP_VERSION}-fpm-alpine AS base
ARG PHP_VERSION                        ← deklarasi ulang agar bisa dipakai di stage ini
LABEL org.opencontainers.image.source="https://github.com/acme/app"
ENV APP_ENV=production
WORKDIR /var/www/html
RUN apk add --no-cache icu-libs
COPY --chown=www-data:www-data . .
USER www-data
EXPOSE 9000
HEALTHCHECK CMD php-fpm-healthcheck || exit 1
STOPSIGNAL SIGQUIT
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
```

| Instruksi | Kapan berlaku | Menambah layer? | Inti |
|---|---|---|---|
| `FROM` | build | — (base) | Base image / mulai stage |
| `ARG` | build | tidak | Variabel saat build, bisa di-`--build-arg` |
| `ENV` | build + runtime | tidak | Environment permanen di image |
| `LABEL` | metadata | tidak | Key=value untuk tooling |
| `WORKDIR` | build + runtime | ya (bila membuat dir) | Direktori kerja |
| `COPY` / `ADD` | build | **ya** | Salin file ke image |
| `RUN` | build | **ya** | Jalankan perintah saat build |
| `USER` | build + runtime | tidak | User untuk RUN/CMD/ENTRYPOINT |
| `EXPOSE` | metadata | tidak | Dokumentasi port |
| `VOLUME` | runtime | tidak | Mount point anonymous volume |
| `CMD` | runtime | tidak | Perintah/argumen default |
| `ENTRYPOINT` | runtime | tidak | Program utama |
| `HEALTHCHECK` | runtime | tidak | Pemeriksaan kesehatan |
| `STOPSIGNAL` | runtime | tidak | Sinyal untuk `docker stop` |
| `SHELL` | build | tidak | Shell untuk shell form |
| `ONBUILD` | build turunan | tidak | Instruksi yang dijalankan di image turunan |

Baris berawalan `#` adalah komentar, dan `\` di akhir baris menyambung ke baris berikutnya.

## 1. FROM

```dockerfile
FROM alpine:3.20
FROM alpine:3.20@sha256:d9e853e87e55526f6b2917df91a2115c36dd7c696a35be12163d44e6e2a4b6bc   # pin digest (lab)
FROM --platform=linux/amd64 php:8.3-fpm-alpine AS build
FROM scratch                                   # image kosong — untuk binary statis
```

- **Tag** bisa dipindah oleh pemiliknya. **Digest** tidak berubah. Digest + tag = mudah dibaca dan tetap pasti.
- `AS nama` memberi nama stage (dipakai multi-stage dan `--target`). Huruf besar/kecilnya harus konsisten:
  `FROM ... as base` memicu peringatan `FromAsCasing` (lab).
- Pilih base yang sesuai. `-alpine`: kecil, musl, busybox. `-slim` (Debian): lebih kompatibel. Distroless/scratch:
  paling kecil, tanpa shell.

## 2. ARG

```dockerfile
ARG NODE_VERSION=22
FROM node:${NODE_VERSION}-alpine
ARG NODE_VERSION                  # wajib dideklarasikan ulang untuk dipakai di dalam stage
ARG APP_VERSION=dev
ENV APP_VERSION=${APP_VERSION}    # salin ke ENV bila perlu di runtime
```

```bash
docker build --build-arg NODE_VERSION=20 --build-arg APP_VERSION=1.4.2 -t api .
```

| Fakta (lab) | |
|---|---|
| ARG tersedia di RUN | `build: BUILD_ONLY=from-arg` |
| ARG **tidak** ada di container | `runtime: BUILD_ONLY=[]` |
| ARG sebelum FROM tanpa deklarasi ulang | `V after FROM=[]` |
| `--build-arg` mengganti default | `BUILD_ONLY=overridden` |
| Nilai ARG yang dipakai RUN **terlihat** di `docker history` | `NPM_TOKEN=supersecret123` |

Docker juga menyediakan ARG bawaan, mis. `TARGETPLATFORM`, `TARGETARCH`, `BUILDPLATFORM` (untuk multi-arsitektur),
dan `HTTP_PROXY`.

## 3. ENV dan LABEL

```dockerfile
ENV APP_ENV=production \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0
LABEL org.opencontainers.image.source="https://github.com/acme/app" \
      org.opencontainers.image.version="1.4.2"
```

- ENV berlaku untuk instruksi berikutnya **dan** container. Bisa ditimpa saat runtime dengan `docker run -e`.
- ENV tersimpan di config image. Di lab, `docker image inspect -f '{{json .Config.Env}}'` →
  `"DB_PASSWORD=hunter2"`. **Jangan pernah untuk secret.**
- `ENV KEY value` (tanpa `=`) adalah format lama yang memicu peringatan `LegacyKeyValueFormat`.
- LABEL tidak memengaruhi perilaku. Ia dipakai registry, scanner, dan `docker ps --filter label=...`.

## 4. WORKDIR

```dockerfile
WORKDIR /srv
WORKDIR app          # relatif → /srv/app
WORKDIR sub          # → /srv/app/sub
```

- Membuat direktori bila belum ada, dimiliki **root** (lab: `drwxr-xr-x root root /srv/app/sub`).
- Menjadi direktori awal `CMD`/`ENTRYPOINT` dan `docker exec`.
- `RUN cd` tidak bertahan. Di lab, `RUN mkdir /app && cd /app`, lalu `RUN pwd` → `/`.

## 5. COPY dan ADD

```dockerfile
COPY package.json package-lock.json ./         # beberapa sumber → tujuan harus diakhiri /
COPY src /app/src                              # isi direktori src → /app/src
COPY --chown=www-data:www-data . .
COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/
COPY --from=build /app/dist /usr/share/nginx/html   # dari stage lain (modul berikutnya)
COPY <<EOF /etc/app.conf                        # heredoc (syntax=docker/dockerfile:1)
port=8080
EOF
ADD pkg.tar.gz /opt/                            # arsip lokal → DIEKSTRAK
ADD --checksum=sha256:<hash> https://example.com/tool.tar.gz /tmp/
```

| Perilaku (lab) | Hasil |
|---|---|
| `COPY src /dest1` | `/dest1/a.txt`, `/dest1/inner/b.txt` — **isi**, bukan direktori `src` |
| `COPY cfg/app.conf /etc/app/` | `/etc/app/app.conf` |
| `COPY cfg/app.conf /etc/app2` | `/etc/app2` adalah **file** |
| `COPY` setelah `USER app` | Tetap milik **root** |
| `--chown=app:app` / `--chmod=640` | `app app` / `-rw-r-----` |
| File dari context Windows | Mode `-rwxr-xr-x` (755): permission host Windows tidak bermakna |
| `ADD pkg.tar.gz /added/` | Diekstrak: `/added/pkg/file.txt` |
| `COPY pkg.tar.gz /copied/` | Tetap `/copied/pkg.tar.gz` |
| `.dockerignore` berisi `.env`, `*.md` | Tidak ada di `COPY . /ctx` |

Aturan praktis: gunakan **COPY**, dan ADD hanya bila memang butuh ekstraksi otomatis atau unduhan dengan `--checksum`.

```text
# .dockerignore yang umum untuk aplikasi web
.git
node_modules
vendor
.env
.env.*
!.env.example
storage/logs/*
*.log
Dockerfile*
docker-compose*.yml
```

## 6. RUN

```dockerfile
RUN apk add --no-cache curl                    # shell form → /bin/sh -c "..."
RUN ["/bin/sh", "-c", "echo exec form"]        # exec form (jarang untuk RUN)
RUN apt-get update \
 && apt-get install -y --no-install-recommends ca-certificates \
 && rm -rf /var/lib/apt/lists/*                 # bersih-bersih di RUN yang SAMA
RUN <<EOF                                       # heredoc — beberapa baris tanpa &&
set -e
echo line1 > /h.txt
echo line2 >> /h.txt
EOF
```

> [!DANGER]
> **Layer bersifat append-only.** File yang dibuat di satu RUN lalu dihapus di RUN berikutnya tetap ikut terkirim di
> layer lama. Di lab, file acak 30 MB:
> - Dua RUN (buat; hapus) → image **33,6 MB**, dan `docker history` tetap menunjukkan layer `31.5MB`.
> - Satu RUN (`buat && rm`) → **3,6 MB**.
>
> Hal yang sama berlaku untuk secret yang di-COPY lalu di-`rm`. File itu tetap bisa diambil dari layer image.
> Sumber: [Building best practices](https://docs.docker.com/build/building/best-practices/)

Shell default untuk shell form adalah `/bin/sh -c`, yang **tidak** memakai `pipefail`. Di lab, `RUN false | true`
lolos build. Ubah dengan SHELL (bagian 12).

## 7. USER

```dockerfile
RUN addgroup -S app && adduser -S -G app -u 10001 app     # Alpine
# RUN useradd --system --uid 10001 --no-create-home app   # Debian/Ubuntu
USER app            # atau USER 10001:10001
```

- Berlaku untuk RUN, CMD, ENTRYPOINT setelahnya. Di lab: `uid=10001(app)` di RUN maupun runtime, dan `touch /etc/x`
  → `Permission denied`.
- **Tidak** berlaku untuk COPY/ADD (pakai `--chown`).
- Banyak image resmi sudah punya user: `node` (1000), `www-data` (php, 82 di Alpine / 33 di Debian), `nginx` (101).
- UID numerik lebih aman untuk Kubernetes (`runAsNonRoot` memeriksa UID, bukan nama).

## 8. EXPOSE

```dockerfile
EXPOSE 8080
EXPOSE 53/udp
```

Hanya metadata (lab: `{"53/udp":{},"8080/tcp":{}}`). Port baru terbuka dengan `docker run -p` atau `ports:` di Compose.

## 9. VOLUME

```dockerfile
VOLUME /var/lib/app
```

- Setiap `docker run` tanpa `-v` untuk path itu membuat **anonymous volume** baru (lab: `volume ... -> /data`, nama
  64 karakter acak). Volume semacam ini menumpuk dan sulit dikenali.
- Perubahan ke path itu **setelah** VOLUME: builder lama membuangnya, sedangkan BuildKit (default sekarang)
  menyimpannya (lab: `a.txt` dan `b.txt` ada). Jangan bergantung pada salah satu perilaku ini.
- Banyak tim lebih memilih **tidak** memakai VOLUME di Dockerfile, dan mendefinisikan volume secara eksplisit di
  Compose/runtime.

## 10. CMD dan ENTRYPOINT

```dockerfile
CMD ["php-fpm"]                                  # exec form: program langsung jadi PID 1
CMD php-fpm                                      # shell form: ["/bin/sh","-c","php-fpm"]
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]                                  # → argumen default untuk entrypoint
```

| | Tanpa ENTRYPOINT | `ENTRYPOINT ["ep"]` (exec) | `ENTRYPOINT ep` (shell) |
|---|---|---|---|
| Tanpa CMD | error / tidak ada perintah | `ep` | `/bin/sh -c ep` |
| `CMD ["c1","c2"]` | `c1 c2` | `ep c1 c2` | `/bin/sh -c ep` (CMD diabaikan) |
| `CMD c1 c2` | `/bin/sh -c "c1 c2"` | `ep /bin/sh -c "c1 c2"` | `/bin/sh -c ep` |
| `docker run img x` | `x` | `ep x` | `/bin/sh -c ep` (x diabaikan) |

Hasil lab:
- `ENTRYPOINT ["echo","ep:"]` + `CMD ["default-arg"]` → `ep: default-arg`, dan dengan argumen → `ep: custom1 custom2`.
- ENTRYPOINT shell form → selalu `ep-shell`.
- Dua CMD → hanya `second`.
- `CMD ["echo","$GREETING"]` → `$GREETING` literal, sedangkan `["sh","-c","echo $GREETING"]` → `halo`.
- `--entrypoint` mengganti ENTRYPOINT saat runtime.

> [!INSIGHT]
> **Shell form + PID 1 bergantung base image.** Di lab, `CMD sleep 300` di Alpine menghasilkan `1 sleep 300`
> (busybox ash meng-exec perintah tunggal), sedangkan di `debian:bookworm-slim` menghasilkan `1 /bin/sh -c sleep 300`
> dengan sleep sebagai **anak**. Di Debian, sinyal `docker stop` berhenti di `sh` (modul Linux Process & Signal). Exec
> form memberi perilaku yang sama di semua base image. Karena itu `docker build --check` memperingatkan
> `JSONArgsRecommended`.
> Sumber: [Dockerfile reference — Shell and exec form](https://docs.docker.com/reference/dockerfile/#shell-and-exec-form)

Pola entrypoint script yang benar (diuji di lab: PID 1 = perintah CMD, bukan skrip):

```sh
#!/bin/sh
set -e
# persiapan: tunggu dependency, render config, migrasi (kondisional)
exec "$@"
```

## 11. HEALTHCHECK

```dockerfile
HEALTHCHECK --interval=30s --timeout=3s --start-period=20s --retries=3 \
  CMD wget -qO- http://127.0.0.1:8080/health || exit 1
HEALTHCHECK NONE                                   # matikan healthcheck warisan base image
```

| Opsi | Default | Arti |
|---|---|---|
| `--interval` | 30s | Jarak antar pemeriksaan |
| `--timeout` | 30s | Batas satu pemeriksaan |
| `--start-period` | 0s | Masa boot, kegagalan tidak dihitung |
| `--start-interval` | 5s | Interval selama start-period |
| `--retries` | 3 | Gagal berturut-turut → unhealthy |

Di lab: `(health: starting)` → `(healthy)` → (index.html dihapus) → `(unhealthy)`, `FailingStreak=3`, tapi
container **tetap running**. Healthcheck berjalan **di dalam** container, jadi tool-nya (wget/curl) harus ada di image,
dan alamatnya `127.0.0.1` milik container.

## 12. STOPSIGNAL dan SHELL

```dockerfile
STOPSIGNAL SIGQUIT                     # nginx & php-fpm: graceful shutdown dengan SIGQUIT
SHELL ["/bin/ash", "-eo", "pipefail", "-c"]
RUN curl -fsSL https://example.com/install.sh | sh    # sekarang gagal bila curl gagal
```

- Image nginx resmi memakai `StopSignal=SIGQUIT` (lab modul 01). Di lab: `STOPSIGNAL SIGINT` → `StopSignal=SIGINT`.
- Dengan SHELL pipefail, `RUN false | true` **gagal** build (lab). Tanpa pipefail, lolos. Ini penting untuk pola
  `curl ... | sh`.

## 13. ONBUILD

```dockerfile
# image dasar perusahaan
FROM alpine:3.20
ONBUILD COPY . /app
ONBUILD RUN make -C /app
```

Instruksi ONBUILD dijalankan saat **image lain** memakai image ini sebagai FROM. Di lab, child image berisi
`onbuild fired`. Fitur ini membuat Dockerfile turunan sulit dipahami ("sihir" tersembunyi) dan jarang dipakai
sekarang. Multi-stage lebih jelas.

## 14. Validasi: `docker build --check`

```text
$ docker build --check .
WARNING: FromAsCasing - 'as' and 'FROM' keywords' casing do not match
WARNING: LegacyKeyValueFormat - "ENV key=value" should be used instead of legacy "ENV key value" format
WARNING: SecretsUsedInArgOrEnv
WARNING: JSONArgsRecommended
```

Jalankan di CI bersama linter seperti hadolint.

## 15. Ringkasan kebiasaan aman

| Kebiasaan | Kenapa |
|---|---|
| Pin tag (dan digest untuk production) | Build reproducible |
| `.dockerignore` sejak awal | Context kecil, `.env`/`.git` tidak bocor |
| Urutkan dari yang jarang ke yang sering berubah | Cache efektif |
| COPY, bukan ADD (kecuali butuh fiturnya) | Perilaku jelas |
| `--chown`/`--chmod` di COPY, bukan RUN chown | Tidak menggandakan layer |
| Buat & bersihkan di RUN yang sama | Layer tidak membawa sampah |
| WORKDIR, bukan `RUN cd` | `cd` tidak bertahan |
| ENV/ARG bukan untuk secret | Terlihat di inspect/history |
| USER non-root | Batasi dampak kompromi |
| CMD/ENTRYPOINT exec form + `exec "$@"` di skrip | PID 1 menerima sinyal |
| HEALTHCHECK dengan tool yang ada di image | Status sehat yang berarti |
| `docker build --check` di CI | Tangkap anti-pattern lebih awal |
