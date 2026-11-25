---
format: 1
id: docker-compose-advanced
course:
  slug: docker
  title: Docker
  description: Container dari nol sampai production — CLI, image, storage, network, Compose, keamanan, dan operasional.
title: 'Compose Lanjutan: Interpolasi, Override, Secrets & Production'
summary: Interpolasi .env dan urutan prioritasnya, profiles, file override dan aturan merge (!reset/!override), extends dan include, secrets dan configs, replicas, watch untuk development, serta alur deploy production dengan Compose.
order: 8
version: 1
status: verified
updated: '2026-11-23'
tested_on:
  - Docker Desktop (Windows) — Docker Engine 29.4.1, Docker Compose v5.1.3
  - Image nginx:1.27-alpine, postgres:17-alpine, redis:7-alpine, alpine:3.20
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: interpolation
    text: Menguasai interpolasi variabel, .env, --env-file, dan urutan prioritasnya
  - id: profiles
    text: Memakai profiles untuk service opsional
  - id: merge
    text: Menggabungkan beberapa file (override, -f, COMPOSE_FILE) dan memahami aturan merge
  - id: reuse
    text: Memakai ulang konfigurasi dengan extends dan include
  - id: secrets-configs
    text: Memberikan secret dan config ke container tanpa environment variable
  - id: scaling
    text: Menjalankan beberapa replica dan beberapa lingkungan dari satu file
  - id: dev-loop
    text: Mempercepat development dengan compose watch
  - id: production
    text: Mendeploy dan memelihara stack Compose di production
sources:
  - title: Compose — Interpolation
    url: https://docs.docker.com/reference/compose-file/interpolation/
  - title: Docker Docs — Set environment variables & .env (interpolation)
    url: https://docs.docker.com/compose/how-tos/environment-variables/variable-interpolation/
  - title: Docker Docs — Environment variables precedence
    url: https://docs.docker.com/compose/how-tos/environment-variables/envvars-precedence/
  - title: Docker Docs — Using profiles
    url: https://docs.docker.com/compose/how-tos/profiles/
  - title: Docker Docs — Merge Compose files
    url: https://docs.docker.com/compose/how-tos/multiple-compose-files/merge/
  - title: Compose file reference — Merge and override (!reset, !override)
    url: https://docs.docker.com/reference/compose-file/merge/
  - title: Docker Docs — Extend (extends)
    url: https://docs.docker.com/compose/how-tos/multiple-compose-files/extends/
  - title: Docker Docs — Include
    url: https://docs.docker.com/compose/how-tos/multiple-compose-files/include/
  - title: Docker Docs — Secrets in Compose
    url: https://docs.docker.com/compose/how-tos/use-secrets/
  - title: Compose file reference — configs
    url: https://docs.docker.com/reference/compose-file/configs/
  - title: Docker Docs — Compose Watch
    url: https://docs.docker.com/compose/how-tos/file-watch/
  - title: Docker Docs — Use Compose in production
    url: https://docs.docker.com/compose/how-tos/production/
  - title: Docker Docs — Pre-defined environment variables (COMPOSE_FILE, COMPOSE_PROFILES, COMPOSE_PATH_SEPARATOR)
    url: https://docs.docker.com/compose/how-tos/environment-variables/envvars/
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: interpolation
    prompt: File apa (di folder project) yang otomatis dibaca Compose untuk mengisi variabel `${VAR}` **di dalam file Compose**?
    answers: ['.env']
    tests:
      fail: ['env_file', 'compose.env', '.envrc', 'environment']
    explanation: 'Di lab, `.env` berisi `TAG=from-dotenv`, sehingga `image: nginx:${TAG}` menjadi `nginx:from-dotenv`. `.env` ini untuk **interpolasi**, dan isinya tidak otomatis masuk ke container (soal tier B).'
    source: https://docs.docker.com/compose/how-tos/environment-variables/variable-interpolation/

  - id: a02
    tier: A
    type: command
    objective: interpolation
    prompt: Render konfigurasi Compose memakai file variabel `prod.env` (sebagai pengganti `.env`) untuk interpolasi.
    answers: ['docker compose --env-file prod.env config']
    patterns: ['^docker compose --env-file[= ]\.?/?prod\.env config( -q)?$']
    tests:
      pass: ['docker compose --env-file ./prod.env config']
      fail: ['docker compose config --env-file prod.env', 'docker compose -e prod.env config', 'docker compose config prod.env']
    explanation: '`--env-file` adalah opsi global, ditulis **sebelum** sub-perintah. Di lab: `image: nginx:from-prodenv`. Bisa diulang, dan file terakhir yang menang.'
    source: https://docs.docker.com/compose/how-tos/environment-variables/variable-interpolation/#substitute-with---env-file

  - id: a03
    tier: A
    type: text
    objective: interpolation
    prompt: 'Tulis ekspresi interpolasi yang memakai `$PORT`, atau `8080` bila `PORT` kosong/tidak di-set.'
    answers: ['${PORT:-8080}']
    patterns: ['^\$\{port:-8080\}$']
    tests:
      fail: ['${PORT:?8080}', '${PORT-8080}', '$PORT:-8080', '${PORT:=8080}']
    explanation: 'Sintaksnya sama dengan shell: `:-` default bila kosong/unset, `-` default hanya bila unset, `:?` wajib, `:+` alternatif bila di-set. Compose tidak mendukung `:=` (assign).'
    source: https://docs.docker.com/reference/compose-file/interpolation/

  - id: a04
    tier: A
    type: command
    objective: profiles
    prompt: Jalankan stack sekaligus mengaktifkan service yang punya profile `tools`.
    answers: ['docker compose --profile tools up -d']
    patterns: ['^docker compose --profile[= ]tools up( -d| --detach)*$']
    tests:
      pass: ['docker compose --profile tools up']
      fail: ['docker compose up -d --profile tools', 'docker compose up -d tools-profile', 'docker compose -p tools up -d']
    explanation: 'Service dengan `profiles:` tidak dijalankan secara default. Di lab, `config --services` → `web` saja, dengan `--profile tools` → `tool web`. Bisa juga lewat env `COMPOSE_PROFILES=tools,debug` (lab: `debug tool web`). `-p` adalah **project name**, bukan profile.'
    source: https://docs.docker.com/compose/how-tos/profiles/

  - id: a05
    tier: A
    type: text
    objective: merge
    prompt: Nama file override yang **otomatis** digabung dengan `compose.yaml` saat menjalankan `docker compose` tanpa `-f`?
    answers: ['compose.override.yaml', 'compose.override.yml', 'docker-compose.override.yml']
    tests:
      fail: ['compose.dev.yaml', 'override.yaml', 'compose.prod.yaml', '.env']
    explanation: 'Di lab, `compose.override.yaml` mengubah `command` menjadi `nginx-debug` dan menambah `EXTRA`. Dengan `-f compose.yaml` saja, override **tidak** dibaca (lab: 0 kemunculan). Pola umum: override berisi setelan dev (bind mount source, port debug), sehingga jangan sampai ikut ter-deploy ke production.'
    source: https://docs.docker.com/compose/how-tos/multiple-compose-files/merge/

  - id: a06
    tier: A
    type: command
    objective: merge
    prompt: Render konfigurasi dari `compose.yaml` digabung `compose.prod.yaml` (file belakang menimpa yang depan), tanpa memakai file override otomatis.
    answers: ['docker compose -f compose.yaml -f compose.prod.yaml config']
    patterns: ['^docker compose (-f|--file)[= ]?compose\.yaml (-f|--file)[= ]?compose\.prod\.yaml config( -q)?$']
    tests:
      pass: ['docker compose --file compose.yaml --file compose.prod.yaml config']
      fail: ['docker compose -f compose.prod.yaml -f compose.yaml config', 'docker compose -f compose.yaml,compose.prod.yaml config', 'docker compose config compose.prod.yaml']
    explanation: 'Urutan penting: file yang ditulis belakangan menimpa yang depan. Path relatif di semua file dihitung dari direktori file **pertama**. Selalu periksa hasil gabungan dengan `config` sebelum deploy.'
    source: https://docs.docker.com/compose/how-tos/multiple-compose-files/merge/

  - id: a07
    tier: A
    type: text
    objective: secrets-configs
    prompt: Di dalam container, di direktori mana secret Compose di-mount secara default?
    answers: ['/run/secrets', '/run/secrets/']
    tests:
      fail: ['/etc/secrets', '/var/run/docker/secrets', '/secrets', '/run/docker']
    explanation: 'Di lab, `ls -l /run/secrets` → `api_key`, `db_password`. Setiap secret menjadi file bernama sesuai key-nya. Gunanya: aplikasi membaca dari file, bukan dari environment variable yang terlihat di `docker inspect`.'
    source: https://docs.docker.com/compose/how-tos/use-secrets/

  - id: a08
    tier: A
    type: text
    objective: secrets-configs
    prompt: Image resmi PostgreSQL membaca password dari **file** bila diberikan variabel apa (pengganti `POSTGRES_PASSWORD`)?
    answers: ['POSTGRES_PASSWORD_FILE']
    tests:
      fail: ['POSTGRES_PASSWORD', 'PGPASSFILE', 'POSTGRES_SECRET', 'PASSWORD_FILE']
    explanation: 'Di lab: `POSTGRES_PASSWORD_FILE=/run/secrets/db_password`, dan login dengan password dari secret berhasil (`login-with-secret-ok`), sementara `docker inspect` hanya menampilkan **path**-nya, bukan password. Konvensi `*_FILE` didukung banyak image resmi (mysql, mariadb, ...).'
    source: https://github.com/docker-library/docs/blob/master/postgres/README.md#docker-secrets

  - id: a09
    tier: A
    type: command
    objective: scaling
    prompt: Jalankan stack dengan service `worker` sebanyak **3** container.
    answers: ['docker compose up -d --scale worker=3']
    patterns: ['^docker compose up (-d |--detach )?--scale[= ]worker=3( -d| --detach)?$']
    tests:
      pass: ['docker compose up --scale worker=3 -d']
      fail: ['docker compose scale worker 3', 'docker compose up -d --replicas worker=3', 'docker compose up -d worker=3']
    explanation: 'Atau deklaratif: `deploy: { replicas: 3 }` (lab: `djc2r-w-1`, `-w-2`, `-w-3`). `--scale w=1` mengecilkan kembali (lab: 1 container). Service yang di-scale tidak boleh memakai `container_name` atau mem-publish port host yang sama.'
    source: https://docs.docker.com/reference/cli/docker/compose/up/

  - id: a10
    tier: A
    type: command
    objective: dev-loop
    prompt: Jalankan stack sambil **mengawasi perubahan file** sesuai aturan `develop.watch` (sync/rebuild otomatis).
    answers: ['docker compose watch']
    patterns: ['^docker compose (watch|up --watch|up -d --watch)$']
    tests:
      pass: ['docker compose up --watch']
      fail: ['docker compose up -d', 'docker compose logs -f', 'docker compose sync']
    explanation: 'Di lab: `Watch enabled` → file `index.html` diubah → `Syncing service "web" after 1 changes were detected` → nginx langsung menyajikan `v2`, tanpa bind mount. Ini alternatif bind mount yang tidak terkena lambatnya filesystem Windows (modul Storage).'
    source: https://docs.docker.com/compose/how-tos/file-watch/

  - id: a11
    tier: A
    type: command
    objective: production
    prompt: Di server production, **unduh** versi image terbaru semua service di project tanpa menjalankan apa pun.
    answers: ['docker compose pull']
    patterns: ['^docker compose pull( (-q|--quiet|--ignore-pull-failures))*$']
    tests:
      fail: ['docker compose build', 'docker compose up --pull', 'docker pull compose']
    explanation: 'Memisahkan `pull` dari `up` memperkecil downtime: image sudah ada di lokal sebelum container lama dihentikan. Bila pull gagal (registry down, rate limit), stack lama tetap berjalan.'
    source: https://docs.docker.com/reference/cli/docker/compose/pull/

  - id: a12
    tier: A
    type: command
    objective: production
    prompt: Jalankan `up -d` sambil **menghapus container yatim**, yaitu container dari service yang sudah dihapus dari file Compose.
    answers: ['docker compose up -d --remove-orphans']
    patterns: ['^docker compose up (?=.*(-d|--detach)( |$))(?=.*--remove-orphans( |$))((-d|--detach|--remove-orphans|--wait|--no-build) ?)+$']
    tests:
      pass: ['docker compose up --remove-orphans -d', 'docker compose up -d --wait --remove-orphans']
      fail: ['docker compose up -d', 'docker compose down --remove-orphans', 'docker compose rm -f']
    explanation: 'Di lab, setelah service `b` dihapus dari file, `up` memperingatkan `Found orphan containers ([djc2o-b-1])`, tetapi container itu tetap berjalan. Dengan `--remove-orphans`: `Container djc2o-b-1 Removed`. Service lama yang tertinggal bisa diam-diam memegang port, memakai resource, atau menjalankan versi kode lama.'
    source: https://docs.docker.com/reference/cli/docker/compose/up/

  - id: a13
    tier: A
    type: command
    objective: scaling
    prompt: Jalankan file Compose yang sama sebagai project **terpisah** bernama `app-staging` (container, network, volume tidak bercampur dengan project lain).
    answers: ['docker compose -p app-staging up -d']
    patterns: ['^docker compose (-p|--project-name)[= ]?app-staging( (-f|--file)[= ]?\S+)* up( -d| --detach)*$']
    tests:
      pass: ['docker compose --project-name app-staging up -d']
      fail: ['docker compose up -d app-staging', 'docker compose --profile app-staging up -d', 'docker compose -n app-staging up -d']
    explanation: 'Di lab, `rep.yaml` dijalankan sebagai `djc2stg` dan `djc2prd` di host yang sama, dan `docker compose ls` menampilkan keduanya `running(1)`. Port host yang di-publish tetap harus berbeda per project (biasanya lewat variabel `${PORT}` + `--env-file`).'
    source: https://docs.docker.com/compose/how-tos/project-name/

  - id: a14
    tier: A
    type: command
    objective: production
    prompt: Tampilkan semua project Compose yang ada di host ini beserta statusnya.
    answers: ['docker compose ls']
    patterns: ['^docker compose ls( (-a|--all))?$']
    tests:
      pass: ['docker compose ls -a']
      fail: ['docker compose ps', 'docker ps', 'docker compose list-projects']
    explanation: 'Di lab: `djc2prd running(1) ...\rep.yaml`. Kolom terakhir menunjukkan file Compose yang dipakai. Berguna di server yang dikelola banyak orang ("project ini dijalankan dari folder mana?").'
    source: https://docs.docker.com/reference/cli/docker/compose/ls/

  - id: a15
    tier: A
    type: text
    objective: reuse
    prompt: Key top-level apa yang menyertakan seluruh file Compose lain (beserta service, network, dan volume-nya) ke project ini?
    answers: ['include']
    tests:
      fail: ['extends', 'import', 'merge', 'require']
    explanation: 'Di lab: `include: [infra/compose.yaml]` → `config --services` → `redis app`. Path relatif dihitung dari file yang di-include, sehingga tim lain bisa memelihara `infra/` secara mandiri. `extends` mewarisi **satu service**, sedangkan `include` membawa satu file utuh.'
    source: https://docs.docker.com/compose/how-tos/multiple-compose-files/include/

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: interpolation
    prompt: '`.env` berisi `TAG=from-dotenv`, dan kamu menjalankan `TAG=from-shell docker compose config`. Nilai `TAG` mana yang dipakai?'
    answers: ['from-shell']
    tests:
      fail: ['from-dotenv', 'keduanya', 'error']
    explanation: 'Di lab: `image: nginx:from-shell`. Urutan interpolasi: environment shell > `--env-file`/`.env` > default di `${VAR:-...}`. Variabel yang tertinggal di shell CI atau `export` yang terlupa bisa diam-diam menimpa `.env`.'
    source: https://docs.docker.com/compose/how-tos/environment-variables/envvars-precedence/

  - id: b02
    tier: B
    type: text
    objective: interpolation
    prompt: '`.env` berisi `TAG`, `PORT`, `GREETING`, sedangkan `prod.env` hanya `TAG`. Dengan `docker compose --env-file prod.env config`, apakah `PORT` dari `.env` masih terbaca? (ya/tidak)'
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: '`--env-file` **mengganti** `.env`, tidak menambah. Di lab: `The "PORT" variable is not set. Defaulting to a blank string.` dan GREETING juga kosong. Untuk menumpuk, tulis dua kali: `--env-file .env --env-file prod.env` (lab: TAG dari prod.env, PORT dari .env).'
    source: https://docs.docker.com/compose/how-tos/environment-variables/variable-interpolation/

  - id: b03
    tier: B
    type: text
    objective: interpolation
    prompt: Variabel di file `.env` project (bukan `env_file:`), apakah otomatis tersedia sebagai environment variable **di dalam container**? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab, container yang mencetak `$TAG` dan `$GREETING` → `TAG_in_container=[] GREETING=[]`, padahal keduanya ada di `.env`. Agar masuk ke container, sebutkan di `environment:` (`GREETING: ${GREETING}`) atau pakai `env_file:`. Pemisahan ini disengaja, supaya variabel deploy (tag, port) tidak bocor ke aplikasi.'
    source: https://docs.docker.com/compose/how-tos/environment-variables/variable-interpolation/

  - id: b04
    tier: B
    type: text
    objective: profiles
    prompt: 'Service `tool` punya `profiles: [tools]`. Kamu menjalankan `docker compose run --rm tool` **tanpa** `--profile`. Apakah service itu dijalankan? (ya/tidak)'
    answers: ['ya', 'yes']
    tests:
      fail: ['tidak', 'no']
    explanation: 'Menyebut service secara eksplisit di `run` (atau `up tool`) otomatis mengaktifkan profile-nya. Di lab: `tool ran`. Pola ini cocok untuk tool sekali jalan (migrasi manual, seeder, backup, `artisan tinker`) yang tidak boleh ikut `up` biasa.'
    source: https://docs.docker.com/compose/how-tos/profiles/#auto-starting-profiles-and-dependency-resolution

  - id: b05
    tier: B
    type: text
    objective: merge
    prompt: '`compose.yaml` punya `ports: ["127.0.0.1:8201:80"]`, dan `compose.override.yaml` punya `ports: ["127.0.0.1:8202:80"]` untuk service yang sama. Setelah merge, berapa pemetaan port yang aktif?'
    answers: ['2', 'dua', 'dua-duanya']
    tests:
      fail: ['1', 'satu', '0', 'hanya 8202']
    explanation: 'Di lab, hasil `config` berisi `published: "8201"` **dan** `published: "8202"`. Untuk `ports`, `expose`, `volumes`, dan `dns`, nilai dari file override **digabung**, bukan diganti. Sebaliknya, `command` diganti (lab: `nginx-debug`) dan `environment` digabung per key (lab: `EXTRA` ditambahkan, `GREETING` tetap). Banyak orang mengira port lama "ter-override", padahal masih terbuka.'
    source: https://docs.docker.com/reference/compose-file/merge/

  - id: b06
    tier: B
    type: text
    objective: merge
    prompt: Tag YAML apa yang ditulis di file override agar sebuah list (mis. `ports`) **mengganti** sepenuhnya nilai dari file dasar, bukan digabung?
    answers: ['!override']
    tests:
      fail: ['!reset', '!replace', '!merge', 'override: true']
    explanation: 'Di lab: `ports: !override ["127.0.0.1:8300:80"]` → hanya `published: "8300"`. `!reset null` **menghapus** nilai dari file dasar (lab: `GREETING: !reset null` → GREETING hilang). Keduanya tersedia di Compose versi baru. Periksa hasilnya dengan `config`.'
    source: https://docs.docker.com/reference/compose-file/merge/#reset-value

  - id: b07
    tier: B
    type: text
    objective: merge
    prompt: Di **Windows**, karakter pemisah apa yang dipakai saat menulis beberapa file di env `COMPOSE_FILE` (mis. `compose.yaml?compose.prod.yaml`)?
    answers: [';']
    tests:
      fail: [':', ',', '|', '+']
    explanation: 'Di lab (Windows): `COMPOSE_FILE="compose.yaml;compose.prod.yaml"` → hasil gabungan dengan port `8300`. Di Linux/macOS pemisahnya `:`. Bisa diatur seragam dengan `COMPOSE_PATH_SEPARATOR`. Ini sering menyebabkan skrip deploy yang ditulis di Windows rusak saat dijalankan di server Linux, dan sebaliknya.'
    source: https://docs.docker.com/compose/how-tos/environment-variables/envvars/#compose_file

  - id: b08
    tier: B
    type: text
    objective: reuse
    prompt: 'Service `worker` memakai `extends: { file: common.yaml, service: base-php }`. base-php punya `environment: { APP_ENV: production, TZ: Asia/Jakarta }`, dan worker sendiri menambahkan `QUEUE: emails`. Berapa variabel environment yang dimiliki worker?'
    answers: ['3', 'tiga']
    tests:
      fail: ['1', '2', 'satu', 'dua']
    explanation: 'Di lab, hasil `config` worker: `APP_ENV: production`, `QUEUE: emails`, `TZ: Asia/Jakarta`. Mapping di-merge per key dengan aturan yang sama seperti file override. `extends` cocok untuk beberapa service yang berbagi basis (app, worker, scheduler dari image Laravel yang sama). `depends_on` dari service induk tidak ikut diwariskan.'
    source: https://docs.docker.com/compose/how-tos/multiple-compose-files/extends/

  - id: b09
    tier: B
    type: text
    objective: reuse
    prompt: 'File utama mendefinisikan service `redis: { image: redis:8-alpine }`, sementara file yang di-`include` juga mendefinisikan `redis: { image: redis:7-alpine }`. Di Compose v5 (lab), image redis mana yang dipakai?'
    answers: ['redis:8-alpine']
    tests:
      fail: ['redis:7-alpine', 'error', 'keduanya']
    explanation: 'Di lab (Compose v5.1.3), `config` tanpa error menghasilkan `image: redis:8-alpine`, sehingga definisi di file utama menang. Dokumentasi lama menyebut konflik `include` sebagai error, jadi perilaku ini berbeda antar versi Compose. Jangan mengandalkannya. Hindari nama service yang sama di file yang di-include, dan validasi dengan `config` di versi Compose yang dipakai server.'
    source: https://docs.docker.com/compose/how-tos/multiple-compose-files/include/

  - id: b10
    tier: B
    type: text
    objective: secrets-configs
    prompt: 'Secret Compose didefinisikan dengan `environment: HOST_API_KEY`, lalu di-mount ke container. Di container, apakah ada **environment variable** yang berisi key itu? (ya/tidak)'
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab: `cat /run/secrets/api_key` → `key-from-host-env`, tetapi `env | grep -c API_KEY` → `0`. Nilai diambil dari environment **host** saat `up`, lalu ditulis sebagai file. Hasilnya, secret tidak terlihat di `docker inspect`, `/proc/1/environ`, atau dump error aplikasi yang mencetak env.'
    source: https://docs.docker.com/reference/compose-file/secrets/

  - id: b11
    tier: B
    type: text
    objective: secrets-configs
    prompt: Pada Compose biasa (bukan Swarm), secret berbasis `file:` disediakan ke container dengan mekanisme apa?
    answers: ['bind mount']
    patterns: ['(bind ?mount|di-?mount|mount bind|bind)']
    tests:
      pass: ['bind', 'di-mount sebagai bind mount']
      fail: ['dienkripsi', 'environment variable', 'disalin ke image', 'swarm raft']
    explanation: 'Di lab: `docker inspect .Mounts` → `{"Type":"bind","Source":"...\\db_password.txt","Destination":"/run/secrets/db_password","RW":false}`. Secret itu read-only, tetapi tidak terenkripsi, dan permission file-nya mengikuti host (lab, dari Windows: `-rwxrwxrwx`). Lindungi file sumbernya di host (mode 600, di luar repo git). Enkripsi at-rest hanya ada di Swarm.'
    source: https://docs.docker.com/compose/how-tos/use-secrets/

  - id: b12
    tier: B
    type: text
    objective: secrets-configs
    prompt: 'Di bagian top-level `configs:`, atribut apa yang memungkinkan isi config ditulis **langsung** di file Compose (tanpa file terpisah)?'
    answers: ['content']
    tests:
      fail: ['file', 'inline', 'data', 'value']
    explanation: 'Di lab: `configs: { app_ini: { content: | ... env=${APP_ENV:-production} } }`, di-mount dengan `target: /etc/app/app.ini`, menghasilkan `[app] env=production`. `content` juga diinterpolasi. Configs untuk data tidak rahasia (nginx.conf, php.ini), sedangkan secrets untuk data rahasia.'
    source: https://docs.docker.com/reference/compose-file/configs/

  - id: b13
    tier: B
    type: text
    objective: dev-loop
    prompt: 'Dengan `develop.watch` action `sync`, apakah `docker compose watch` menyinkronkan isi folder **saat start**, atau hanya file yang **berubah** setelah watch berjalan?'
    answers: ['hanya yang berubah']
    patterns: ['(hanya |cuma )?(file )?yang berubah|perubahan|setelah (ada )?perubahan|changes?']
    tests:
      pass: ['hanya file yang berubah', 'perubahan saja']
      fail: ['saat start', 'semua isi folder', 'keduanya']
    explanation: 'Di lab, sebelum ada perubahan nginx masih menyajikan halaman bawaan image. Setelah `index.html` diubah, baru log menampilkan `Syncing service "web" after 1 changes` dan isinya `v2`. Untuk kondisi awal yang benar, file sebaiknya sudah ada di image (COPY saat build). `sync+restart` dan `rebuild` adalah action lain untuk config dan dependency.'
    source: https://docs.docker.com/compose/how-tos/file-watch/

  - id: b14
    tier: B
    type: command
    objective: production
    prompt: 'Deploy di production: jalankan perubahan **tanpa** build di server, menunggu semua service sehat, dan sekaligus membuang container yatim.'
    answers: ['docker compose up -d --no-build --wait --remove-orphans']
    patterns: ['^docker compose up (?=.*(-d|--detach)( |$))(?=.*--no-build( |$))(?=.*--wait( |$))(?=.*--remove-orphans( |$))((-d|--detach|--no-build|--wait|--remove-orphans|--pull[= ]\S+) ?)+$']
    tests:
      pass: ['docker compose up --wait --no-build --remove-orphans -d']
      fail: ['docker compose up -d --build', 'docker compose up -d --wait', 'docker compose restart']
    explanation: 'Image dibuat di CI dan hanya di-pull di server (modul Image & Registry: build sekali, deploy digest yang sama). `--no-build` mencegah server diam-diam membangun image sendiri. `--wait` membuat perintah deploy gagal bila service tidak sehat, sehingga pipeline tahu. Compose me-recreate container satu per satu tanpa rolling update, jadi ada jeda singkat. Untuk zero-downtime, butuh proxy + dua instance atau orchestrator.'
    source: https://docs.docker.com/compose/how-tos/production/

  - id: b15
    tier: B
    type: text
    objective: merge
    prompt: Dengan `docker compose -f compose.yaml config` (hanya satu `-f`), apakah `compose.override.yaml` di folder yang sama tetap ikut digabung? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab: dengan `-f compose.yaml` saja, kemunculan `EXTRA`/`8202` dari override → `0`. Override otomatis hanya dibaca bila **tidak** ada `-f`. Ini cara aman memastikan setelan dev tidak ikut ke production: deploy selalu dengan `-f compose.yaml -f compose.prod.yaml`.'
    source: https://docs.docker.com/compose/how-tos/multiple-compose-files/merge/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: merge
    prompt: |
      Satu repo dipakai untuk development (Windows, Laravel, hot reload) dan production (Linux, image dari GHCR).
      Rancang pembagian file Compose dan perintahnya (baris YAML/perintah, satu per baris):
      - file dasar berisi yang sama untuk semua lingkungan
      - override dev otomatis untuk laptop
      - file prod yang dipakai eksplisit di server, dengan port debug dev yang tidak ikut terbuka di prod
      - variabel per lingkungan di file env yang berbeda
    checklist:
      - id: base
        label: compose.yaml sebagai dasar
        required: true
        patterns: ['compose\.yaml']
      - id: dev-override
        label: compose.override.yaml untuk dev (build/watch/port debug)
        required: true
        patterns: ['compose\.override\.ya?ml']
      - id: prod-file
        label: Deploy prod dengan -f compose.yaml -f compose.prod.yaml
        required: true
        patterns: ['-f\s+compose\.yaml\s+-f\s+compose\.prod\.yaml', 'compose_file\s*=\s*"?compose\.yaml[;:]compose\.prod\.yaml']
      - id: env-file
        label: --env-file per lingkungan (prod.env / staging.env)
        patterns: ['--env-file[= ]\S*(prod|production|staging)\S*\.env']
      - id: override-tag
        label: '!override/!reset untuk membuang port/volume dev, atau port tidak ditaruh di file dasar'
        patterns: ['!override', '!reset']
      - id: watch
        label: develop.watch / docker compose watch untuk dev di Windows
        patterns: ['docker\s+compose\s+watch', 'develop:', 'watch:']
      - id: verify
        label: Validasi hasil gabungan (config)
        patterns: ['docker\s+compose\s+.*\bconfig\b']
      - id: no-override-in-prod
        label: Tidak deploy prod tanpa -f (override dev ikut terbaca)
        forbid: true
        patterns: ['^ssh\s+\S+\s+["'']?docker\s+compose\s+up\s+-d["'']?\s*$', '^#\s*prod:\s*docker\s+compose\s+up\s+-d\s*$']
    pass_score: 0.5
    solution: |
      ```text
      compose.yaml           # service, network, volume, healthcheck — sama untuk semua
      compose.override.yaml  # DEV: build lokal, develop.watch, port debug, Xdebug   (dibaca otomatis)
      compose.prod.yaml      # PROD: image GHCR ${TAG:?}, restart, limits, logging
      dev.env / prod.env     # variabel interpolasi per lingkungan (tidak di-commit bila berisi rahasia)
      ```
      ```yaml
      # compose.prod.yaml
      services:
        app:
          image: ghcr.io/acme/app:${TAG:?set TAG}
          ports: !reset []           # buang port debug yang mungkin ada di dasar
          restart: unless-stopped
      ```
      ```bash
      # laptop
      docker compose watch
      # server
      docker compose -f compose.yaml -f compose.prod.yaml --env-file prod.env config -q
      docker compose -f compose.yaml -f compose.prod.yaml --env-file prod.env pull
      docker compose -f compose.yaml -f compose.prod.yaml --env-file prod.env up -d --no-build --wait --remove-orphans
      ```
      Fakta lab yang menentukan desain ini:
      - Override dibaca otomatis hanya bila **tanpa** `-f` (lab: 0 kemunculan dengan `-f compose.yaml`).
      - `ports` dari override **ditambahkan**, bukan diganti (lab: 8201 + 8202). `!override`/`!reset` adalah cara membuang
        nilai warisan (lab: hanya 8300).
      - `--env-file` mengganti `.env` (lab: PORT kosong), jadi file env prod harus lengkap.
      - Di Windows, `watch` menyinkronkan perubahan tanpa bind mount (lab: `v2`), sehingga tidak terkena bind mount yang
        30× lebih lambat.
    tests:
      pass:
        - |
          compose.yaml
          compose.override.yaml
          docker compose watch
          ports: !reset []
          docker compose -f compose.yaml -f compose.prod.yaml --env-file prod.env config -q
          docker compose -f compose.yaml -f compose.prod.yaml --env-file prod.env up -d --wait
        - |
          compose.yaml
          compose.override.yml
          COMPOSE_FILE=compose.yaml:compose.prod.yaml
          docker compose --env-file prod.env config
      fail:
        - |
          compose.yaml
          docker compose up -d
        - |
          compose.override.yaml
          # prod: docker compose up -d
        - |
          docker-compose.yml
          docker compose up -d --build
    source: https://docs.docker.com/compose/how-tos/multiple-compose-files/merge/

  - id: c02
    tier: C
    type: scenario
    objective: secrets-configs
    prompt: |
      Security review menemukan password database dan API key terlihat lewat `docker inspect` pada stack Compose:
      `environment: { POSTGRES_PASSWORD: S3cr3t, STRIPE_KEY: sk_live_... }`. Tulis perubahan Compose (baris YAML, satu per baris)
      dan langkah pendukung supaya kedua nilai itu tidak lagi ada di environment, tetapi aplikasi dan database tetap jalan.
    checklist:
      - id: top-secrets
        label: Deklarasi top-level secrets (file:/environment:)
        required: true
        patterns: ['^secrets:\s*$', '^file:\s*\S+', '^environment:\s*[a-z_]+\s*$']
      - id: pg-file
        label: POSTGRES_PASSWORD_FILE=/run/secrets/...
        required: true
        patterns: ['postgres_password_file\s*[:=]\s*/run/secrets/\S+']
      - id: service-secrets
        label: secrets dipasang di service (secrets:)
        patterns: ['^secrets:\s*\[', '^-\s*(source:\s*)?(db_password|stripe_key|api_key)\s*$']
      - id: app-reads-file
        label: Aplikasi membaca dari /run/secrets (bukan env)
        patterns: ['/run/secrets/(stripe|api)\S*', 'file_get_contents\(.*/run/secrets']
      - id: protect-file
        label: File secret di host dilindungi (chmod 600, .gitignore)
        patterns: ['chmod\s+(0?600|0?400)', '\.gitignore']
      - id: verify
        label: Verifikasi env tidak lagi berisi secret (docker inspect / compose exec env)
        patterns: ['docker\s+inspect\s+.*env', 'docker\s+compose\s+exec\s+\S+\s+env']
      - id: no-env-secret
        label: Tidak tetap menaruh nilai rahasia di environment
        forbid: true
        patterns: ['^(postgres_password|stripe_key)[ \t]*:[ \t]*\S+', '-[ \t]*(postgres_password|stripe_key)=\S+']
    pass_score: 0.5
    solution: |
      ```yaml
      services:
        db:
          image: postgres:17-alpine
          environment:
            POSTGRES_PASSWORD_FILE: /run/secrets/db_password
          secrets: [db_password]
        app:
          image: ghcr.io/acme/app:1.4.2
          secrets:
            - db_password
            - source: stripe_key
      secrets:
        db_password:
          file: ./secrets/db_password.txt
        stripe_key:
          environment: STRIPE_KEY     # diambil dari env host saat deploy (mis. secret CI)
      ```
      ```bash
      chmod 600 secrets/db_password.txt && echo "secrets/" >> .gitignore
      # Laravel: config/services.php → 'stripe' => ['secret' => trim(file_get_contents('/run/secrets/stripe_key'))]
      docker compose up -d --wait
      docker inspect -f '{{range .Config.Env}}{{println .}}{{end}}' $(docker compose ps -q db) | grep -i pass
      ```
      Diuji di lab:
      - `POSTGRES_PASSWORD_FILE` membuat PostgreSQL membaca password dari file (`login-with-secret-ok`).
      - `docker inspect` hanya menampilkan `POSTGRES_PASSWORD_FILE=/run/secrets/db_password`.
      - Secret dari `environment:` host menjadi file `/run/secrets/api_key` dengan `env | grep -c API_KEY` → 0.

      Pada Compose non-Swarm, secret file adalah bind mount read-only (lab: `"Type":"bind","RW":false`), jadi
      perlindungannya bergantung pada file sumber di host. Karena nilai lama sudah terekspos, **rotasi** password dan key.
    tests:
      pass:
        - |
          secrets:
            db_password:
              file: ./secrets/db_password.txt
          POSTGRES_PASSWORD_FILE: /run/secrets/db_password
          secrets: [db_password, stripe_key]
          chmod 600 secrets/db_password.txt
          docker inspect -f '{{.Config.Env}}' dojo-db-1
        - |
          secrets:
            stripe_key:
              environment: STRIPE_KEY
          - POSTGRES_PASSWORD_FILE=/run/secrets/db_password
          file_get_contents('/run/secrets/stripe_key')
      fail:
        - |
          POSTGRES_PASSWORD: S3cr3t
          STRIPE_KEY: sk_live_123
        - |
          secrets:
            db_password:
              file: ./db.txt
          POSTGRES_PASSWORD: S3cr3t
        - |
          env_file: secrets.env
          docker compose up -d
    source: https://docs.docker.com/compose/how-tos/use-secrets/

  - id: c03
    tier: C
    type: scenario
    objective: production
    prompt: |
      Tulis runbook deploy rilis baru stack Compose di server production (satu perintah per baris), dengan syarat:
      - versi image dipilih eksplisit
      - konfigurasi divalidasi sebelum apa pun berubah
      - image diunduh dulu agar downtime minimal
      - deploy gagal dengan jelas bila service tidak sehat
      - service lama yang sudah dihapus dari file ikut dibersihkan
      - rollback ke versi sebelumnya bisa dilakukan dengan cepat
    checklist:
      - id: tag
        label: Versi dipilih eksplisit (TAG=... / --env-file berisi TAG)
        required: true
        patterns: ['^(export\s+)?tag=\S+', 'tag=\S+\s+docker\s+compose', '--env-file[= ]\S+']
      - id: config
        label: Validasi dengan config -q sebelum perubahan
        required: true
        patterns: ['docker\s+compose\s+.*\bconfig\s+(-q|--quiet)']
      - id: pull
        label: Pull image lebih dulu
        required: true
        patterns: ['docker\s+compose\s+.*\bpull\b']
      - id: up
        label: up -d --wait --remove-orphans (--no-build)
        required: true
        patterns: ['docker\s+compose\s+.*\bup\b.*--wait', 'docker\s+compose\s+.*\bup\b.*--remove-orphans']
      - id: verify
        label: Verifikasi (ps / logs / curl healthcheck)
        patterns: ['docker\s+compose\s+.*\b(ps|logs)\b', '\bcurl\s+']
      - id: rollback
        label: Rollback dengan TAG sebelumnya / digest tercatat
        patterns: ['(tag=\S+.*\bup\b|rollback|@sha256:|previous|sebelumnya)']
      - id: no-down-v
        label: Tidak memakai down -v / volume prune dalam deploy
        forbid: true
        patterns: ['docker\s+compose\s+.*\bdown\s+.*(-v|--volumes)\b', 'docker\s+(volume|system)\s+prune']
      - id: no-build-on-server
        label: Tidak build di server produksi
        forbid: true
        patterns: ['docker\s+compose\s+.*\bup\b.*\s--build(\s|$)', 'docker\s+compose\s+(.*\s)?build(\s|$)']
    pass_score: 0.6
    solution: |
      ```bash
      cd /srv/dojo
      export TAG=4f2c9a1                                   # commit SHA rilis (catat juga digest-nya)
      docker compose -f compose.yaml -f compose.prod.yaml --env-file prod.env config -q
      docker compose -f compose.yaml -f compose.prod.yaml --env-file prod.env pull
      docker compose -f compose.yaml -f compose.prod.yaml --env-file prod.env up -d --no-build --wait --remove-orphans
      docker compose -f compose.yaml -f compose.prod.yaml ps
      curl -fsS https://dojo.example.com/up
      # rollback: TAG=<rilis-sebelumnya> lalu ulangi pull + up dengan perintah yang sama
      ```
      Setiap langkah punya alasan yang diuji di modul ini dan sebelumnya:
      - `config -q` menangkap file yang invalid, seperti compose devops-dojo yang lupa `networks:`, sebelum stack lama
        disentuh.
      - `pull` memisahkan unduhan dari restart.
      - `--wait` membuat perintah gagal bila healthcheck tidak lolos.
      - `--remove-orphans` membuang service yang sudah dihapus dari file (lab: `djc2o-b-1 Removed`).
      - `--no-build` memastikan yang berjalan adalah image hasil CI.

      Rollback = jalankan ulang dengan tag sebelumnya, karena image lama masih ada di registry (modul Image & Registry).
      Migrasi database yang tidak backward-compatible membuat rollback tidak sesederhana itu. Rancang migrasi agar
      kompatibel dengan versi sebelumnya (expand–contract).
    tests:
      pass:
        - |
          export TAG=4f2c9a1
          docker compose -f compose.yaml -f compose.prod.yaml config -q
          docker compose -f compose.yaml -f compose.prod.yaml pull
          docker compose -f compose.yaml -f compose.prod.yaml up -d --no-build --wait --remove-orphans
          docker compose ps
          # rollback: TAG=previous lalu up lagi
        - |
          TAG=1.4.2 docker compose --env-file prod.env config -q
          docker compose --env-file prod.env pull
          docker compose --env-file prod.env up -d --wait --remove-orphans
          curl -fsS https://dojo.example.com/up
      fail:
        - |
          git pull
          docker compose up -d --build
        - |
          export TAG=1.4.2
          docker compose down -v
          docker compose up -d
        - |
          docker compose pull
          docker compose up -d
    source: https://docs.docker.com/compose/how-tos/production/

---

# Compose Lanjutan: Interpolasi, Override, Secrets & Production

Modul sebelumnya membedah isi satu `compose.yaml`. Di dunia nyata, satu aplikasi berjalan di beberapa lingkungan
(laptop, staging, production) dengan perbedaan kecil, dan punya rahasia yang tidak boleh berceceran. Modul ini membahas
cara memisahkan konfigurasi per lingkungan tanpa menyalin file, cara memberi secret dengan benar, dan cara memakai
Compose dengan aman di server.

> [!NOTE]
> Diuji dengan Docker Compose v5.1.3 di Docker Desktop (Windows). Aturan merge dan interpolasi berasal dari Compose
> Specification. Hasil lab ditandai di setiap bagian. Satu perilaku (`include` yang bentrok) berbeda dari dokumentasi
> lama, sehingga ditandai khusus.

## 1. Interpolasi variabel

```yaml
services:
  web:
    image: "nginx:${TAG:?set TAG}"
    ports: ["127.0.0.1:${PORT:-8080}:80"]
    environment:
      GREETING: ${GREETING}         # dari interpolasi, lalu dikirim ke container
      LITERAL: "$${HOME}"           # $$ = $ literal, diekspansi di container
```

| Sintaks | Arti |
|---|---|
| `${VAR}` / `$VAR` | Nilai VAR (kosong + peringatan bila tidak ada) |
| `${VAR:-default}` | Default bila kosong/unset |
| `${VAR-default}` | Default hanya bila unset |
| `${VAR:?pesan}` | **Gagal** bila kosong/unset |
| `${VAR:+alt}` | `alt` bila VAR di-set |
| `$$` | Tanda dolar literal |

### Sumber nilai dan prioritasnya (hasil lab)

```mermaid
flowchart LR
    S["environment shell<br/>TAG=from-shell"] --> W{menang}
    E["--env-file prod.env<br/>(menggantikan .env)"] --> W
    D[".env di folder project"] --> W
    F["${VAR:-default}"] --> W
```

| Kasus lab | Hasil |
|---|---|
| `.env` saja | `nginx:from-dotenv` |
| `TAG=from-shell` + `.env` | `nginx:from-shell` |
| `--env-file prod.env` (hanya TAG) | `nginx:from-prodenv`, PORT/GREETING **kosong** |
| `--env-file .env --env-file prod.env` | TAG dari prod.env, PORT dari .env |
| `.env` di dalam container | **Tidak ada** (`TAG_in_container=[]`) |

> [!WARNING]
> **`.env` ≠ `env_file`.** Yang pertama mengisi `${...}` di file Compose (versi image, port), sedangkan yang kedua mengirim
> variabel ke container. Lab membuktikan isi `.env` tidak masuk ke container kecuali disebut di `environment:`. Pisahkan
> keduanya: `.env`/`prod.env` untuk variabel deploy, dan `app.env` (atau secrets) untuk konfigurasi aplikasi.

## 2. Profiles

```yaml
services:
  app: { image: ghcr.io/acme/app:1.4.2 }
  mailpit:
    image: axllent/mailpit
    profiles: [dev]
  backup:
    image: alpine:3.20
    profiles: [tools]
    command: ["sh", "-c", "tar czf /backup/uploads.tgz -C /data ."]
```

```bash
docker compose up -d                     # hanya app
docker compose --profile dev up -d       # app + mailpit
COMPOSE_PROFILES=dev,tools docker compose up -d
docker compose run --rm backup           # profile otomatis aktif (lab: "tool ran")
```

## 3. Menggabungkan file: override & aturan merge

```bash
docker compose up -d                                         # compose.yaml + compose.override.yaml (otomatis)
docker compose -f compose.yaml -f compose.prod.yaml up -d    # eksplisit, override TIDAK dibaca
COMPOSE_FILE="compose.yaml;compose.prod.yaml"                # Windows: pemisah ;   (Linux/macOS: :)
```

| Jenis key | Aturan merge | Hasil lab |
|---|---|---|
| Skalar (`image`, `command`, `restart`) | **Diganti** | `command` → `nginx-debug` |
| Mapping (`environment`, `labels`) | Digabung per key | `EXTRA` ditambah, `GREETING` tetap |
| List unik (`ports`, `expose`, `volumes`, `dns`) | **Digabung** | 8201 **dan** 8202 aktif |
| `!override` | Mengganti nilai | `ports: !override [...]` → hanya 8300 |
| `!reset` | Menghapus nilai | `GREETING: !reset null` → hilang |

> [!INSIGHT]
> Kesalahan merge yang paling umum: menambahkan port di file override dengan harapan port dasar "tertimpa". Di lab,
> kedua port tetap terbuka (`8201` + `8202`), karena untuk `ports` Compose **menggabung** list, bukan mengganti. Untuk
> production, port debug di dasar yang dianggap sudah diganti ternyata tetap ter-publish. Selalu jalankan
> `docker compose -f ... config` dan baca hasil akhirnya, atau pakai `!override`.
> Sumber: [Compose — Merge](https://docs.docker.com/reference/compose-file/merge/)

## 4. Pakai ulang: extends dan include

```yaml
# common.yaml
services:
  base-php:
    image: ghcr.io/acme/app:${TAG:?}
    environment: { APP_ENV: production, TZ: Asia/Jakarta }
    restart: unless-stopped

# compose.yaml
include:
  - infra/compose.yaml             # redis, mailpit, dll. dipelihara terpisah
services:
  app:
    extends: { file: common.yaml, service: base-php }
  worker:
    extends: { file: common.yaml, service: base-php }
    command: ["php", "artisan", "queue:work"]
    environment: { QUEUE: emails }
```

- `extends`: satu service mewarisi service lain, dengan aturan merge yang sama. Lab: worker punya `APP_ENV`, `TZ`, `QUEUE`.
- `include`: satu file utuh (service + network + volume). Lab: `redis` dan `app` dalam satu project.
- Konflik nama di `include`: di Compose v5 (lab) definisi file utama menang tanpa error (`redis:8-alpine`). Jangan
  mengandalkan perilaku ini, dan hindari nama yang sama.

## 5. Secrets dan configs

```yaml
services:
  db:
    image: postgres:17-alpine
    environment:
      POSTGRES_PASSWORD_FILE: /run/secrets/db_password
    secrets: [db_password]
  app:
    secrets:
      - db_password
      - source: api_key
        target: api_key
    configs:
      - source: app_ini
        target: /etc/app/app.ini
secrets:
  db_password:
    file: ./secrets/db_password.txt
  api_key:
    environment: HOST_API_KEY       # diambil dari env host saat up
configs:
  app_ini:
    content: |
      [app]
      env=${APP_ENV:-production}
```

| Hasil lab | |
|---|---|
| `/run/secrets` | `api_key`, `db_password` |
| `env \| grep -c API_KEY` di container | `0`, tidak ada di environment |
| `docker inspect` db | Hanya `POSTGRES_PASSWORD_FILE=/run/secrets/db_password` |
| Login PostgreSQL dengan password dari secret | `login-with-secret-ok` |
| Mekanisme secret `file:` | Bind mount read-only. Permission mengikuti host (Windows: `rwxrwxrwx`) |
| `configs.content` | Diinterpolasi: `env=production` |

Secret di Compose biasa **tidak dienkripsi**, jadi lindungi file sumbernya (`chmod 600`, di luar git). Manfaat utamanya
adalah nilai rahasia tidak terlihat di `docker inspect`, `/proc/*/environ`, log, atau halaman error yang mencetak environment.

## 6. Replicas dan banyak lingkungan dari satu file

```yaml
services:
  worker:
    image: ghcr.io/acme/app:${TAG:?}
    command: ["php", "artisan", "queue:work"]
    deploy: { replicas: 3 }          # lab: djc2r-w-1..3
```

```bash
docker compose up -d --scale worker=1
docker compose -p dojo-staging --env-file staging.env up -d
docker compose -p dojo-prod    --env-file prod.env    up -d
docker compose ls                   # lab: djc2prd running(1), djc2stg running(1)
```

## 7. Development loop: `compose watch`

```yaml
services:
  app:
    build: .
    develop:
      watch:
        - action: sync               # salin file yang berubah ke container
          path: ./resources
          target: /var/www/html/resources
        - action: sync+restart       # salin lalu restart (config)
          path: ./config
          target: /var/www/html/config
        - action: rebuild            # build ulang image
          path: composer.lock
```

Di lab: `Watch enabled` → ubah `index.html` → `Syncing service "web" after 1 changes` → halaman berubah menjadi `v2`. Yang
disinkronkan hanya file yang **berubah**, sedangkan kondisi awal tetap dari image. Di Windows, ini jauh lebih cepat daripada
bind mount folder Windows (modul Storage: 19 s vs 0,6 s).

## 8. Compose di production

```mermaid
flowchart LR
    CI[CI: build & push ghcr.io/acme/app:SHA] --> S1
    S1[config -q] --> S2[pull] --> S3[up -d --no-build --wait --remove-orphans] --> S4[ps / curl]
    S4 -- gagal --> R[rollback: TAG lama → pull → up]
```

| Praktik | Kenapa |
|---|---|
| Build di CI, server hanya `pull` (`--no-build`) | Yang berjalan = yang diuji |
| `config -q` sebelum perubahan | File invalid ditolak sebelum stack lama disentuh |
| `--wait` | Deploy gagal bila healthcheck tidak lolos |
| `--remove-orphans` | Service lama tidak tertinggal (lab: peringatan orphan) |
| `restart: unless-stopped`, `deploy.resources`, rotasi log | Pulih otomatis, tidak menghabiskan RAM/disk |
| Tag SHA + catat digest | Rollback pasti |
| Jangan `down -v` / `prune` dalam skrip deploy | Data hilang permanen |
| Sadari batasnya | Tidak ada rolling update atau multi-host. Untuk itu: Swarm/Kubernetes |

## 9. Ringkasan kebiasaan aman

| Kebiasaan | Kenapa |
|---|---|
| `.env` untuk interpolasi, `env_file`/secrets untuk container | Mekanisme berbeda (lab: `.env` tidak masuk container) |
| File env prod lengkap | `--env-file` mengganti `.env`, tidak menambah |
| Deploy prod selalu dengan `-f` eksplisit | Override dev otomatis tidak ikut |
| Baca `config` setelah merge, pakai `!override` / `!reset` | `ports` digabung, bukan diganti |
| Pemisah `COMPOSE_FILE` sesuai OS (`;` Windows, `:` Linux) | Skrip lintas OS rusak |
| Profiles untuk tool/dev, `run --rm` untuk tugas sekali jalan | `up` biasa tetap bersih |
| Secrets + `*_FILE`, bukan password di `environment:` | Tidak terlihat di inspect/env |
| `watch` untuk dev di Windows | Cepat tanpa bind mount |
| Runbook: config → pull → up --wait --remove-orphans → verifikasi | Deploy yang bisa diulang dan di-rollback |
