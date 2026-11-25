---
format: 1
id: docker-compose-anatomy
course:
  slug: docker
  title: Docker
  description: Container dari nol sampai production — CLI, image, storage, network, Compose, keamanan, dan operasional.
title: 'Compose: Anatomi compose.yaml'
summary: Pembahasan detail setiap bagian compose.yaml — struktur top-level, setiap key service (image, build, command, environment, env_file, ports, volumes, networks, depends_on, healthcheck, restart, deploy, logging, keamanan), interpolasi dasar, dan perintah docker compose sehari-hari.
order: 7
version: 1
status: verified
updated: '2026-11-23'
tested_on:
  - Docker Desktop (Windows) — Docker Engine 29.4.1, Docker Compose v5.1.3
  - Image postgres:17-alpine, redis:7-alpine, nginx:1.27-alpine, alpine:3.20
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: structure
    text: Memahami struktur file Compose, nama project, dan resource yang dibuat
  - id: image-build
    text: Menulis image, build (context, dockerfile, target, args), command, entrypoint
  - id: env
    text: Mengatur environment dan env_file (urutan prioritas, kutip, tipe YAML)
  - id: ports-volumes
    text: Menulis ports, volumes (sintaks pendek & panjang), tmpfs, read_only
  - id: networks
    text: Mendefinisikan networks, alias, dan network internal
  - id: lifecycle
    text: Mengatur depends_on, healthcheck, restart, dan urutan start
  - id: runtime
    text: Memakai deploy.resources, logging, init, user, stop_grace_period, extra_hosts
  - id: cli
    text: Memakai docker compose up/down/ps/logs/exec/run/config dengan benar
  - id: review
    text: Menulis dan me-review compose.yaml untuk aplikasi nyata
sources:
  - title: Compose Specification
    url: https://docs.docker.com/reference/compose-file/
  - title: Compose file reference — services
    url: https://docs.docker.com/reference/compose-file/services/
  - title: Compose file reference — version and name (obsolete version)
    url: https://docs.docker.com/reference/compose-file/version-and-name/
  - title: Compose file reference — networks
    url: https://docs.docker.com/reference/compose-file/networks/
  - title: Compose file reference — volumes
    url: https://docs.docker.com/reference/compose-file/volumes/
  - title: Compose file reference — build
    url: https://docs.docker.com/reference/compose-file/build/
  - title: Compose file reference — deploy (resources)
    url: https://docs.docker.com/reference/compose-file/deploy/
  - title: Compose — Interpolation
    url: https://docs.docker.com/reference/compose-file/interpolation/
  - title: Compose — Fragments & extensions (x-, YAML anchors)
    url: https://docs.docker.com/reference/compose-file/extension/
  - title: Docker Docs — Control startup order (depends_on conditions)
    url: https://docs.docker.com/compose/how-tos/startup-order/
  - title: Docker Docs — Environment variables precedence
    url: https://docs.docker.com/compose/how-tos/environment-variables/envvars-precedence/
  - title: Docker Docs — Specify a project name
    url: https://docs.docker.com/compose/how-tos/project-name/
  - title: docker compose CLI reference
    url: https://docs.docker.com/reference/cli/docker/compose/
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: structure
    prompt: Nama file Compose yang **disarankan** oleh Compose Specification saat ini?
    answers: ['compose.yaml']
    tests:
      fail: ['docker-compose.yml', 'compose.json', 'Composefile', 'docker.yaml']
    explanation: 'Compose mencari `compose.yaml`, `compose.yml`, lalu (demi kompatibilitas) `docker-compose.yaml`/`docker-compose.yml`. Bila beberapa ada sekaligus, yang pertama dipakai. File lain dipilih dengan `-f`.'
    source: https://docs.docker.com/compose/intro/compose-application-model/#the-compose-file

  - id: a02
    tier: A
    type: text
    objective: structure
    prompt: 'Bagaimana status atribut top-level `version:` (mis. `version: "3.8"`) di Compose modern?'
    answers: ['obsolete, diabaikan']
    patterns: ['(obsolete|diabaikan|ignored|usang|tidak (dipakai|diperlukan|perlu)|deprecated|dihapus saja)']
    tests:
      pass: ['diabaikan', 'tidak perlu lagi']
      fail: ['wajib', '3.8', 'harus ada', 'menentukan fitur']
    explanation: 'Di lab (Compose v5.1.3), setiap perintah mencetak `the attribute version is obsolete, it will be ignored, please remove it`. Fitur ditentukan oleh versi Compose yang dipakai, bukan field ini. Hapus saja.'
    source: https://docs.docker.com/reference/compose-file/version-and-name/

  - id: a03
    tier: A
    type: command
    objective: cli
    prompt: Buat dan jalankan semua service di `compose.yaml` direktori saat ini di **background**.
    answers: ['docker compose up -d']
    patterns: ['^docker compose up (-d|--detach)( --wait| --build| --remove-orphans)*$']
    tests:
      pass: ['docker compose up --detach', 'docker compose up -d --wait']
      fail: ['docker compose start', 'docker compose run -d', 'docker up -d']
    explanation: '`up` membuat network/volume/container yang belum ada dan hanya me-recreate service yang konfigurasinya berubah (lab: env `web` diubah → hanya `djc-web-1 Recreate`). `--wait` menunggu semua service running/healthy. `start` hanya menyalakan container yang sudah ada.'
    source: https://docs.docker.com/reference/cli/docker/compose/up/

  - id: a04
    tier: A
    type: command
    objective: cli
    prompt: Hentikan dan **hapus** container serta network project (volume bernama tetap dipertahankan).
    answers: ['docker compose down']
    patterns: ['^docker compose down( --remove-orphans)?$']
    tests:
      fail: ['docker compose stop', 'docker compose down -v', 'docker compose rm']
    explanation: 'Di lab: `down` menghapus container dan network, tetapi `djcv_rdata` (volume) tetap ada. `stop` hanya menghentikan container (status `exited`), tanpa menghapusnya.'
    source: https://docs.docker.com/reference/cli/docker/compose/down/

  - id: a05
    tier: A
    type: command
    objective: cli
    prompt: Seperti `down`, tetapi **sekaligus menghapus** volume bernama yang didefinisikan di file Compose.
    answers: ['docker compose down -v']
    patterns: ['^docker compose down (-v|--volumes)( --remove-orphans)?$', '^docker compose down --remove-orphans (-v|--volumes)$']
    tests:
      pass: ['docker compose down --volumes']
      fail: ['docker compose down', 'docker compose rm -v', 'docker volume prune']
    explanation: 'Di lab: `Volume djcv_rdata Removed`. **Data database ikut hilang permanen.** Aman untuk lingkungan dev yang ingin di-reset, tetapi berbahaya di server. Volume `external: true` tidak ikut dihapus.'
    source: https://docs.docker.com/reference/cli/docker/compose/down/

  - id: a06
    tier: A
    type: command
    objective: cli
    prompt: Ikuti log service `web` secara real-time.
    answers: ['docker compose logs -f web']
    patterns: ['^docker compose logs ((-f|--follow)( (--tail|-n)[= ]?\d+)?|(--tail|-n)[= ]?\d+ (-f|--follow)) web$']
    tests:
      pass: ['docker compose logs --follow web', 'docker compose logs -f --tail 100 web']
      fail: ['docker compose logs web', 'docker logs -f web', 'docker compose attach web']
    explanation: 'Tanpa nama service, log semua service digabung dengan prefix nama (lab: `api-1 | api started`). `--no-log-prefix` menghilangkan prefix. `docker logs -f web` gagal, karena nama containernya `<project>-web-1`, bukan `web`.'
    source: https://docs.docker.com/reference/cli/docker/compose/logs/

  - id: a07
    tier: A
    type: command
    objective: cli
    prompt: Buka shell `sh` di container service `web` yang sedang berjalan.
    answers: ['docker compose exec web sh']
    patterns: ['^docker compose exec( -T| -it)? web (sh|/bin/sh|bash|ash)$']
    tests:
      pass: ['docker compose exec -it web sh']
      fail: ['docker compose run web sh', 'docker exec web sh', 'docker compose attach web']
    explanation: 'Compose otomatis memberi TTY. `-T` mematikannya, mis. di skrip/CI (lab memakai `exec -T`). `run` membuat container **baru**, sedangkan `exec` masuk ke container yang sudah berjalan.'
    source: https://docs.docker.com/reference/cli/docker/compose/exec/

  - id: a08
    tier: A
    type: command
    objective: cli
    prompt: Jalankan perintah sekali pakai `php artisan migrate --force` memakai konfigurasi service `app`, di container baru yang **dihapus** setelah selesai.
    answers: ['docker compose run --rm app php artisan migrate --force']
    patterns: ['^docker compose run (--rm( -T)?|-T --rm) app php artisan migrate --force$']
    tests:
      pass: ['docker compose run --rm -T app php artisan migrate --force']
      fail: ['docker compose exec --rm app php artisan migrate --force', 'docker compose run app php artisan migrate --force', 'docker run --rm app php artisan migrate --force']
    explanation: '`run` memakai image, env, volume, dan network service itu, tetapi **tidak** mem-publish `ports:` secara default (lab: `ports=[80/tcp]`, kecuali dengan `--service-ports`). Tanpa `--rm`, container `<project>-app-run-xxxx` menumpuk.'
    source: https://docs.docker.com/reference/cli/docker/compose/run/

  - id: a09
    tier: A
    type: command
    objective: structure
    prompt: Tampilkan konfigurasi akhir yang sudah dinormalisasi dan diinterpolasi (variabel diganti nilainya) dari file Compose.
    answers: ['docker compose config']
    patterns: ['^docker compose config( (-q|--quiet|--services|--volumes))?$']
    tests:
      pass: ['docker compose config -q']
      fail: ['cat compose.yaml', 'docker compose ps', 'docker compose ls']
    explanation: 'Alat debugging terpenting. Ia memperlihatkan apa yang **benar-benar** dipakai Compose: port panjang (`published: "2222"`), environment sebagai string (`COUNT: "8"`), dan nilai variabel. `-q` hanya memvalidasi, dan cocok untuk CI (lab: menangkap `refers to undefined network internal`).'
    source: https://docs.docker.com/reference/cli/docker/compose/config/

  - id: a10
    tier: A
    type: command
    objective: cli
    prompt: Tampilkan container milik project Compose ini beserta statusnya (termasuk status health).
    answers: ['docker compose ps']
    patterns: ['^docker compose ps( (-a|--all))?$']
    tests:
      pass: ['docker compose ps -a']
      fail: ['docker ps', 'docker compose ls', 'docker compose top']
    explanation: 'Di lab: `db | Up 6 seconds (healthy) | 5432/tcp`. `-a` ikut menampilkan service yang sudah selesai (mis. `migrate | exited | Exited (0)`). `docker compose ls` mendaftar **project**, bukan container.'
    source: https://docs.docker.com/reference/cli/docker/compose/ps/

  - id: a11
    tier: A
    type: text
    objective: networks
    prompt: Di dalam project Compose, service lain memanggil service bernama `db` dengan hostname apa?
    answers: ['db']
    tests:
      fail: ['localhost', '127.0.0.1', 'compose-db', 'host.docker.internal']
    explanation: 'Nama service otomatis menjadi nama DNS di network project (lab: `getent hosts db` → `172.18.0.3 db`). Nama container (`djc-db-1`) juga bisa di-resolve, tetapi nama service lebih stabil, karena tetap sama walau project diganti nama atau di-scale.'
    source: https://docs.docker.com/compose/how-tos/networking/

  - id: a12
    tier: A
    type: text
    objective: structure
    prompt: Project Compose bernama `djc` tanpa definisi network. Apa nama network yang dibuat otomatis?
    answers: ['djc_default']
    tests:
      fail: ['default', 'djc-default', 'bridge', 'djc']
    explanation: 'Lab: `djc_default`. Volume bernama diberi prefix yang sama (`pgdata` → `djc_pgdata`), dan container `djc-<service>-<nomor>`. Prefix ini mencegah dua project di satu server saling bertabrakan, dan karena itu `name:` yang unik itu penting.'
    source: https://docs.docker.com/compose/how-tos/networking/

  - id: a13
    tier: A
    type: text
    objective: structure
    prompt: Di project `djc`, volume yang ditulis `pgdata` di file Compose memiliki nama asli apa di Docker?
    answers: ['djc_pgdata']
    tests:
      fail: ['pgdata', 'djc-pgdata', 'djc_default_pgdata']
    explanation: 'Lab: `docker volume ls` → `djc_pgdata`. Akibatnya, mengganti nama project (folder dipindah, `name:` diubah) membuat Compose memakai volume **baru** yang kosong, dan database terlihat "hilang", padahal volume lamanya masih ada. Untuk volume yang harus dipakai lintas project, pakai `external: true` + `name:`.'
    source: https://docs.docker.com/reference/compose-file/volumes/

  - id: a14
    tier: A
    type: text
    objective: env
    prompt: Di `compose.yaml`, bagaimana menulis tanda dolar **literal** agar tidak diinterpolasi oleh Compose (mis. untuk variabel yang harus dibaca shell di dalam container)?
    answers: ['$$']
    tests:
      fail: ['\$', '$', '${}', '$${}']
    explanation: 'Contoh di lab: `test: ["CMD-SHELL", "pg_isready -U $${POSTGRES_USER:-postgres}"]`, yang hasil `config`-nya tetap `$${...}`, lalu diekspansi oleh shell di container. Tanpa `$$`, Compose mencoba mengisi `POSTGRES_USER` dari environment **host**, yang biasanya kosong.'
    source: https://docs.docker.com/reference/compose-file/interpolation/

  - id: a15
    tier: A
    type: text
    objective: runtime
    prompt: Key service apa (beserta nilainya) yang menjalankan init kecil sebagai PID 1, setara `docker run --init`?
    answers: ['init: true']
    patterns: ['^init(: ?true)?$']
    tests:
      pass: ['init']
      fail: ['tini', 'entrypoint: tini', 'pid: 1', 'init: false']
    explanation: 'Di lab: `docker compose top web` → `/sbin/docker-init -- /docker-entrypoint.sh nginx ...`, dengan nginx sebagai anaknya. Berguna untuk proses yang tidak me-reap zombie atau tidak punya handler sinyal (modul Linux Process & Signal).'
    source: https://docs.docker.com/reference/compose-file/services/#init

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: lifecycle
    prompt: 'Dengan `depends_on: [db]` (bentuk **pendek**), apakah Compose menunggu `db` sampai **healthy** sebelum menjalankan service ini? (ya/tidak)'
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Bentuk pendek = `condition: service_started`, artinya hanya menunggu container **dimulai**, bukan siap menerima koneksi. PostgreSQL butuh beberapa detik untuk siap, dan app yang konek di detik pertama mendapat `Connection refused`. Bentuk panjang `condition: service_healthy` + `healthcheck` di db yang benar-benar menunggu (lab: `Waiting` → `Healthy` → baru `web Starting`).'
    source: https://docs.docker.com/compose/how-tos/startup-order/

  - id: b02
    tier: B
    type: text
    objective: lifecycle
    prompt: '`condition` apa di `depends_on` yang menunggu service lain **selesai dengan exit 0** (mis. job migrasi) sebelum menjalankan service ini?'
    answers: ['service_completed_successfully']
    tests:
      fail: ['service_healthy', 'service_started', 'completed', 'service_exited']
    explanation: 'Di lab, service `migrate` (build sekali jalan) → `Exited (0)`, lalu `api` start. Kombinasi umum: `db` → healthy → `migrate` → completed_successfully → `app`.'
    source: https://docs.docker.com/reference/compose-file/services/#depends_on

  - id: b03
    tier: B
    type: text
    objective: lifecycle
    prompt: '`migrate` keluar dengan exit 1, sedangkan `api` punya `depends_on: { migrate: { condition: service_completed_successfully } }`. Apa status `api` setelah `docker compose up -d`?'
    answers: ['created (tidak dijalankan)']
    patterns: ['(created|tidak (di)?(jalankan|start|mulai|berjalan)|tidak dimulai|not started|belum (di)?jalankan)']
    tests:
      pass: ['created', 'tidak dijalankan']
      fail: ['running', 'jalan normal', 'restarting']
    explanation: 'Di lab: `service "migrate" didn''t complete successfully: exit 1`, `api created Created`, dan perintah `up` gagal. Hasil ini diinginkan, karena aplikasi tidak pernah berjalan dengan skema database yang setengah jadi.'
    source: https://docs.docker.com/compose/how-tos/startup-order/

  - id: b04
    tier: B
    type: text
    objective: lifecycle
    prompt: 'Healthcheck `test: ["CMD", "test", "-f", "$$TARGET"]` (bentuk `CMD`, `TARGET` diset di environment). Apakah `$TARGET` diekspansi menjadi path-nya? (ya/tidak)'
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Bentuk `CMD` menjalankan program langsung tanpa shell, sehingga `$TARGET` diteruskan sebagai teks literal. Di lab: `h1` (CMD) → `unhealthy`, `h2` (`["CMD-SHELL", "test -f $$TARGET"]`) → `healthy`. Pakai `CMD-SHELL` bila butuh variabel, pipe, atau `||`.'
    source: https://docs.docker.com/reference/compose-file/services/#healthcheck

  - id: b05
    tier: B
    type: text
    objective: env
    prompt: '`APP_ENV=fromfile` ada di `env_file`, dan `APP_ENV: fromenvironment` ada di `environment:` service yang sama. Nilai mana yang dipakai di container?'
    answers: ['fromenvironment']
    tests:
      fail: ['fromfile', 'keduanya', 'error']
    explanation: 'Di lab: `APP_ENV=fromenvironment`, sedangkan `APP_NAME=fromfile` tetap dari file. `environment:` menimpa `env_file`. Variabel tanpa nilai (`FROM_SHELL:`) diambil dari shell yang menjalankan compose (lab: `FROM_SHELL=hello-from-shell`).'
    source: https://docs.docker.com/compose/how-tos/environment-variables/envvars-precedence/

  - id: b06
    tier: B
    type: text
    objective: env
    prompt: 'File `env_file` berisi `QUOTED="with quotes"`. Di container (lewat Compose), apakah tanda kutip ikut menjadi bagian nilai? (ya/tidak)'
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab: `QUOTED=with quotes`, karena Compose membuang kutip pembungkus. Bandingkan dengan modul 01: `docker run --env-file` **mempertahankan** kutip (`"with quotes"`). File `.env` yang sama memberi nilai berbeda tergantung tool-nya, sehingga password bisa benar di Compose tetapi salah di `docker run`.'
    source: https://docs.docker.com/compose/how-tos/environment-variables/variable-interpolation/#env-file-syntax

  - id: b07
    tier: B
    type: text
    objective: env
    prompt: '`environment:` ditulis sebagai map: `COUNT: 08` (tanpa kutip). Berapa nilai `COUNT` di container?'
    answers: ['8']
    tests:
      fail: ['08', '"08"', '0', 'error']
    explanation: 'YAML membaca `08` sebagai angka, lalu Compose mengubahnya kembali ke string `"8"` (lab: `COUNT: "8"`), dan nol di depannya hilang. Masalah ini mengenai PIN, kode pos, nomor telepon, dan versi seperti `1.10` (→ `1.1`). `APP_DEBUG: false` aman (lab: `"false"`). Kutip semua nilai environment yang bukan teks biasa.'
    source: https://docs.docker.com/reference/compose-file/services/#environment

  - id: b08
    tier: B
    type: text
    objective: env
    prompt: '`image: alpine:${NOT_SET}`, dan `NOT_SET` tidak di-set di mana pun. Apa yang terjadi saat `docker compose config`?'
    answers: ['diganti string kosong dengan peringatan']
    patterns: ['(kosong|blank|empty|peringatan|warning|alpine:\s*$)']
    tests:
      pass: ['jadi alpine: dengan warning', 'string kosong']
      fail: ['error dan berhenti', 'alpine:latest', 'nilai default dipakai']
    explanation: 'Di lab: `The "NOT_SET_VAR" variable is not set. Defaulting to a blank string.`, menghasilkan `image: ''alpine:''`. Hanya peringatan, sehingga mudah terlewat di CI. Pakai `${VAR:-default}` untuk nilai default, atau `${VAR:?pesan}` untuk mewajibkan (soal berikutnya).'
    source: https://docs.docker.com/reference/compose-file/interpolation/

  - id: b09
    tier: B
    type: text
    objective: env
    prompt: 'Tulis ekspresi interpolasi yang membuat Compose **gagal** dengan pesan `set TAG dulu` bila `TAG` kosong atau tidak di-set.'
    answers: ['${TAG:?set TAG dulu}']
    patterns: ['^\$\{tag:\?set tag dulu\}$']
    tests:
      fail: ['${TAG:-set TAG dulu}', '${TAG}', '$TAG?', '${TAG?}']
    explanation: 'Di lab: `error while interpolating services.s.image: required variable NOT_SET_VAR is missing a value: set NOT_SET_VAR dulu`. Cocok untuk tag image deploy: lebih baik gagal daripada diam-diam memakai `latest` atau tag kosong. `:-` = default, `:?` = wajib, `:+` = ganti bila di-set.'
    source: https://docs.docker.com/reference/compose-file/interpolation/

  - id: b10
    tier: B
    type: text
    objective: cli
    prompt: Apakah `docker compose run web ...` mem-publish `ports:` milik service `web` secara default? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab: container dari `run` hanya menunjukkan `80/tcp` tanpa pemetaan. Tujuannya supaya perintah sekali jalan tidak bentrok port dengan service yang sedang berjalan. `--service-ports` atau `-p` menyalakannya bila memang perlu.'
    source: https://docs.docker.com/reference/cli/docker/compose/run/

  - id: b11
    tier: B
    type: text
    objective: structure
    prompt: 'Service dengan `container_name: fixed-web` dijalankan dengan `docker compose up --scale w=2`. Apa yang terjadi?'
    answers: ['gagal/peringatan karena nama container harus unik']
    patterns: ['(gagal|tidak bisa|error|warning|peringatan|konflik|unik|unique|hanya satu)']
    tests:
      pass: ['tidak bisa di-scale', 'warning nama harus unik']
      fail: ['jalan dua container', 'otomatis fixed-web-2']
    explanation: 'Di lab: `The "w" service is using the custom container name "fixed-web". Docker requires each container to have a unique name. Remove the custom name to scale the service`. `container_name` juga menghilangkan prefix project, sehingga dua project di server yang sama bisa bertabrakan. Hindari kecuali benar-benar perlu.'
    source: https://docs.docker.com/reference/compose-file/services/#container_name

  - id: b12
    tier: B
    type: text
    objective: structure
    prompt: 'Nama project bisa datang dari `-p`, env `COMPOSE_PROJECT_NAME`, `name:` di file, dan nama folder. Bila semuanya ada, mana yang **menang**?'
    answers: ['-p']
    patterns: ['^(opsi |flag )?(-p|--project-name)( flag| opsi)?$']
    tests:
      pass: ['--project-name', 'opsi -p']
      fail: ['name:', 'folder', 'COMPOSE_PROJECT_NAME']
    explanation: 'Di lab: `-p djcx` → `name: djcx`, `COMPOSE_PROJECT_NAME=djcenv` → `djcenv`, dan tanpa keduanya pada file tanpa `name:` → nama folder (`cmp`). Menulis `name:` di file adalah cara paling eksplisit (project devops-dojo memakai `name: trainme`), karena tidak bergantung pada lokasi folder.'
    source: https://docs.docker.com/compose/how-tos/project-name/

  - id: b13
    tier: B
    type: text
    objective: ports-volumes
    prompt: Setelah `docker compose down` (tanpa `-v`), apa yang terjadi pada named volume project?
    answers: ['tetap ada']
    patterns: ['((tetap|masih) ada|tidak (di)?hapus|bertahan|aman|tersimpan)']
    tests:
      pass: ['masih ada', 'tidak dihapus']
      fail: ['terhapus', 'hilang', 'dikosongkan']
    explanation: 'Di lab: setelah `down`, `djcv_rdata` masih ada di `docker volume ls`. Baru dengan `down -v`: `Volume djcv_rdata Removed`. Anonymous volume juga tidak dihapus `down` tanpa `-v`.'
    source: https://docs.docker.com/reference/cli/docker/compose/down/

  - id: b14
    tier: B
    type: text
    objective: runtime
    prompt: '`deploy.resources.limits.memory: 64m`. Berapa nilai `HostConfig.Memory` (dalam byte) di container hasilnya?'
    answers: ['67108864']
    tests:
      fail: ['64', '64000000', '6400000', '0']
    explanation: 'Di lab: `mem=67108864` (64 × 1024 × 1024), dan `cpus: "0.5"` → `nano_cpus=500000000`. Compose v2+ menerapkan `deploy.resources` tanpa Swarm. Bentuk lama `mem_limit:` juga masih didukung (dipakai di compose devops-dojo). Melewati limit memori → OOM kill, exit 137 (modul Linux Process).'
    source: https://docs.docker.com/reference/compose-file/deploy/#resources

  - id: b15
    tier: B
    type: text
    objective: ports-volumes
    prompt: '`volumes: { shared: { external: true, name: djc-ext-vol } }`, tetapi volume itu belum pernah dibuat. Apa hasil `docker compose up`?'
    answers: ['error, volume tidak ditemukan']
    patterns: ['(error|gagal|not found|tidak (di)?temukan|tidak ada)']
    tests:
      pass: ['gagal: external volume not found']
      fail: ['dibuat otomatis', 'jalan normal', 'memakai volume kosong']
    explanation: 'Di lab: `external volume "djc-ext-vol" not found`. `external` berarti volume dikelola di luar project ini, sehingga Compose tidak membuat dan tidak menghapusnya (`down -v` pun tidak). Cocok untuk data penting yang tidak boleh ikut terhapus oleh kesalahan perintah project.'
    source: https://docs.docker.com/reference/compose-file/volumes/#external

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: review
    prompt: |
      Tulis `compose.yaml` (baris YAML apa adanya) untuk stack Laravel di satu server: `nginx` (publik), `app` (php-fpm),
      `db` (PostgreSQL 17), `redis`. Syarat:
      - db dan redis hanya bisa diakses dari app
      - app baru start setelah db benar-benar siap
      - data db bertahan saat deploy
      - semua service restart otomatis kecuali dihentikan manual
      - setiap service dibatasi memorinya
      - hanya nginx yang di-publish, dan hanya ke `127.0.0.1` (ada reverse proxy di depannya)
    checklist:
      - id: services
        label: Blok services
        required: true
        patterns: ['^\s*services:\s*$']
      - id: db-health
        label: Healthcheck db dengan pg_isready (CMD-SHELL / $$)
        required: true
        patterns: ['pg_isready']
      - id: depends-healthy
        label: app depends_on db dengan condition service_healthy
        required: true
        patterns: ['condition:\s*service_healthy']
      - id: pg-volume
        label: Named volume ke /var/lib/postgresql/data
        patterns: ['^\s*-\s*"?[a-z][\w-]*:/var/lib/postgresql/data"?\s*$', 'target:\s*/var/lib/postgresql/data']
      - id: internal-net
        label: 'Network backend internal: true'
        patterns: ['internal:\s*true']
      - id: nginx-port
        label: Port nginx di-bind 127.0.0.1
        patterns: ['^\s*-\s*"?127\.0\.0\.1:\d+:(80|443)"?\s*$', 'host_ip:\s*127\.0\.0\.1']
      - id: restart
        label: restart unless-stopped
        patterns: ['restart:\s*unless-stopped']
      - id: limits
        label: Limit memori (deploy.resources.limits.memory / mem_limit)
        patterns: ['memory:\s*"?\d+[kmg]"?', 'mem_limit:\s*\d+']
      - id: top-volumes
        label: Volume bernama dideklarasikan di top-level volumes
        patterns: ['^volumes:\s*$']
      - id: no-publish-data
        label: Tidak mem-publish port db/redis
        forbid: true
        patterns: ['"?(\d+\.\d+\.\d+\.\d+:)?(5432|6379):(5432|6379)"?']
      - id: no-host-net
        label: Tidak network_mode host
        forbid: true
        patterns: ['network_mode:\s*"?host']
    pass_score: 0.5
    solution: |
      ```yaml
      name: dojo
      services:
        db:
          image: postgres:17-alpine
          restart: unless-stopped
          env_file: [db.env]
          volumes: [pgdata:/var/lib/postgresql/data]
          networks: [backend]
          healthcheck:
            test: ["CMD-SHELL", "pg_isready -U $${POSTGRES_USER} -d $${POSTGRES_DB}"]
            interval: 5s
            retries: 10
            start_period: 10s
          deploy: { resources: { limits: { memory: 384m } } }
        redis:
          image: redis:7-alpine
          restart: unless-stopped
          networks: [backend]
          deploy: { resources: { limits: { memory: 128m } } }
        app:
          image: ghcr.io/acme/dojo-app:${TAG:?set TAG}
          restart: unless-stopped
          env_file: [app.env]
          depends_on:
            db: { condition: service_healthy }
            redis: { condition: service_started }
          networks: [backend, frontend]
          deploy: { resources: { limits: { memory: 384m } } }
        nginx:
          image: ghcr.io/acme/dojo-nginx:${TAG:?set TAG}
          restart: unless-stopped
          depends_on: [app]
          ports: ["127.0.0.1:8080:80"]
          networks: [frontend]
          deploy: { resources: { limits: { memory: 64m } } }
      networks:
        frontend: {}
        backend: { internal: true }
      volumes:
        pgdata: {}
      ```
      Setiap bagian sudah diuji di lab:
      - `service_healthy` menunggu `Healthy`.
      - `$$` membuat variabel diekspansi di dalam container.
      - `internal: true` memblokir akses keluar dan port publishing.
      - `deploy.resources` → `HostConfig.Memory`.
      - Named volume bertahan setelah `down`.

      `env_file` terpisah per service (`db.env` hanya berisi `POSTGRES_*`) membuat database tidak menerima `APP_KEY` dan
      secret aplikasi lainnya.
    tests:
      pass:
        - |
          services:
            db:
              image: postgres:17-alpine
              restart: unless-stopped
              volumes:
                - pgdata:/var/lib/postgresql/data
              healthcheck:
                test: ["CMD-SHELL", "pg_isready -U $${POSTGRES_USER}"]
              networks: [backend]
              mem_limit: 384m
            app:
              depends_on:
                db:
                  condition: service_healthy
            nginx:
              ports:
                - "127.0.0.1:8080:80"
          networks:
            backend:
              internal: true
          volumes:
            pgdata:
        - |
          services:
            db:
              healthcheck:
                test: ["CMD-SHELL", "pg_isready"]
              deploy: { resources: { limits: { memory: 256m } } }
            app:
              depends_on: { db: { condition: service_healthy } }
              restart: unless-stopped
      fail:
        - |
          services:
            db:
              image: postgres:17-alpine
              ports: ["5432:5432"]
            app:
              depends_on: [db]
        - |
          services:
            app:
              network_mode: host
              depends_on:
                db:
                  condition: service_healthy
        - |
          services:
            db:
              image: postgres
            app:
              depends_on: [db]
              restart: always
    source: https://docs.docker.com/reference/compose-file/services/

  - id: c02
    tier: C
    type: scenario
    objective: lifecycle
    prompt: |
      Setiap kali server reboot atau stack di-deploy ulang, `app` (Laravel) crash beberapa kali dengan error koneksi database
      sebelum akhirnya jalan, dan kadang migrasi dijalankan dua kali karena ada di entrypoint semua replica.
      Tulis perubahan `compose.yaml` dan perintah deploy (satu per baris) yang memperbaiki urutan start dan menjadikan
      migrasi langkah tersendiri yang terkontrol.
    context: |
      services:
        db:
          image: postgres:17-alpine
        app:
          image: ghcr.io/acme/api:1.4.2
          depends_on: [db]
          restart: always
          command: ["sh", "-c", "sleep 15; php artisan migrate --force; php-fpm"]
    checklist:
      - id: healthcheck
        label: Healthcheck db (pg_isready)
        required: true
        patterns: ['pg_isready']
      - id: healthy
        label: depends_on db condition service_healthy
        required: true
        patterns: ['condition:\s*service_healthy']
      - id: migrate-service
        label: Service/job migrasi terpisah (service_completed_successfully atau compose run --rm)
        required: true
        patterns: ['service_completed_successfully', 'docker\s+compose\s+run\s+--rm\s+\S+\s+php\s+artisan\s+migrate']
      - id: plain-cmd
        label: Perintah app kembali ke php-fpm saja
        patterns: ['command:\s*\[\s*"php-fpm"\s*\]', '^\s*-\s*php-fpm\s*$', 'cmd\s*\[\s*"php-fpm"']
      - id: wait
        label: Deploy dengan up --wait (dan cek ps/logs)
        patterns: ['docker\s+compose\s+up\s+(-d\s+)?--wait', 'docker\s+compose\s+up\s+--wait']
      - id: no-sleep-hack
        label: Tidak memakai sleep sebagai penunda
        forbid: true
        patterns: ['sleep\s+\d+\s*;']
    pass_score: 0.6
    solution: |
      ```yaml
      services:
        db:
          image: postgres:17-alpine
          healthcheck:
            test: ["CMD-SHELL", "pg_isready -U $${POSTGRES_USER:-postgres}"]
            interval: 3s
            retries: 20
            start_period: 10s
        migrate:
          image: ghcr.io/acme/api:1.4.2
          command: ["php", "artisan", "migrate", "--force"]
          depends_on:
            db: { condition: service_healthy }
          restart: "no"
        app:
          image: ghcr.io/acme/api:1.4.2
          command: ["php-fpm"]
          restart: unless-stopped
          depends_on:
            db: { condition: service_healthy }
            migrate: { condition: service_completed_successfully }
      ```
      ```bash
      docker compose up -d --wait
      docker compose ps -a        # migrate: Exited (0), app: running
      ```
      `depends_on: [db]` hanya menunggu container db **dimulai**. `sleep 15` adalah tebakan: terlalu lama di hari biasa,
      dan terlalu singkat saat disk lambat. Di lab, urutan `db Healthy` → `migrate Exited (0)` → `api` start, dan migrasi yang
      gagal (exit 1) menahan app di `Created`. Migrasi di service tersendiri berjalan **sekali** per deploy, bukan sekali per
      replica. Alternatif: `docker compose run --rm app php artisan migrate --force` sebagai langkah pipeline sebelum `up`.
    tests:
      pass:
        - |
          healthcheck:
            test: ["CMD-SHELL", "pg_isready -U postgres"]
          migrate:
            command: ["php", "artisan", "migrate", "--force"]
            depends_on:
              db: { condition: service_healthy }
          app:
            command: ["php-fpm"]
            depends_on:
              db: { condition: service_healthy }
              migrate: { condition: service_completed_successfully }
          docker compose up -d --wait
        - |
          healthcheck: { test: ["CMD-SHELL", "pg_isready"] }
          depends_on: { db: { condition: service_healthy } }
          docker compose run --rm app php artisan migrate --force
          docker compose up -d
      fail:
        - |
          command: ["sh", "-c", "sleep 30; php artisan migrate --force; php-fpm"]
          restart: always
        - |
          depends_on: [db]
          docker compose up -d
        - |
          healthcheck: { test: ["CMD-SHELL", "pg_isready"] }
          command: ["sh", "-c", "sleep 10; php-fpm"]
    source: https://docs.docker.com/compose/how-tos/startup-order/

  - id: c03
    tier: C
    type: scenario
    objective: review
    prompt: |
      Review `docker-compose.yml` di bawah (diambil dari project nyata, placeholder sudah diisi). `docker compose config`
      menolaknya. Tulis perbaikan (baris YAML/perintah, satu per baris) supaya valid dan lebih aman.
    context: |
      name: trainme
      services:
        db:
          image: postgres:17-alpine
          env_file: .env
          volumes: [pgdata:/var/lib/postgresql/data]
          networks: [internal]
        app:
          image: ghcr.io/acme/dojo-app:${TAG:-latest}
          env_file: .env
          depends_on:
            db: { condition: service_healthy }
          volumes: [storage:/var/www/html/storage/app]
          networks: [internal]
        nginx:
          image: ghcr.io/acme/dojo-nginx:${TAG:-latest}
          depends_on: [app]
          ports: ["127.0.0.1:8080:80"]
          volumes:
            - storage:/var/www/html/storage/app:ro
          networks: [internal]
      $ docker compose config -q
      service "nginx" refers to undefined network internal: invalid compose project
    checklist:
      - id: top-networks
        label: Deklarasi top-level networks (internal)
        required: true
        patterns: ['^networks:\s*$', '^networks:\s*\{']
      - id: top-volumes
        label: Deklarasi top-level volumes pgdata & storage
        required: true
        patterns: ['^volumes:\s*$', '^volumes:\s*\{']
      - id: validate
        label: Validasi ulang dengan docker compose config -q
        patterns: ['docker\s+compose\s+config(\s+-q|\s+--quiet)?\s*$']
      - id: separate-env
        label: env_file terpisah untuk db (tidak menerima secret aplikasi)
        patterns: ['env_file:\s*\[?\s*"?\.?/?db\.env', 'env_file:\s*\[?\s*"?\.?/?(postgres|pg)\S*\.env']
      - id: tag-required
        label: TAG wajib (tanpa default latest)
        patterns: ['\$\{tag:\?[^}]*\}', '\$\{tag\?\}']
      - id: app-health
        label: Healthcheck untuk app / depends_on nginx dengan condition
        patterns: ['^\s*healthcheck:', 'condition:\s*service_(healthy|started)']
      - id: db-health
        label: db punya healthcheck (diperlukan oleh condition service_healthy)
        patterns: ['pg_isready']
      - id: no-latest
        label: Tidak mempertahankan default :-latest
        forbid: true
        patterns: ['\$\{tag:-latest\}']
    pass_score: 0.5
    solution: |
      ```yaml
      services:
        db:
          env_file: [db.env]                 # hanya POSTGRES_USER/PASSWORD/DB
          healthcheck:
            test: ["CMD-SHELL", "pg_isready -U $${POSTGRES_USER} -d $${POSTGRES_DB}"]
            interval: 10s
            retries: 5
        app:
          image: ghcr.io/acme/dojo-app:${TAG:?set TAG ke commit SHA}
          env_file: [app.env]
        nginx:
          image: ghcr.io/acme/dojo-nginx:${TAG:?set TAG ke commit SHA}
          depends_on:
            app: { condition: service_started }
      networks:
        internal: {}
      volumes:
        pgdata: {}
        storage: {}
      ```
      ```bash
      docker compose config -q && echo valid
      ```
      Hasil validasi salinan file ini di lab:
      - Error pertama `refers to undefined network internal`.
      - Setelah network ditambahkan, muncul `refers to undefined volume storage`.
      - Baru setelah `pgdata` **dan** `storage` dideklarasikan di top-level, `config -q` lolos.

      Compose hanya membuat network dan volume bernama yang dideklarasikan di top-level. Perbaikan tambahan:
      - Satu `.env` yang sama untuk semua service berarti database ikut menerima `APP_KEY` dan secret lain. Pisahkan
        file per service.
      - `${TAG:-latest}` diam-diam men-deploy `latest` bila `TAG` lupa di-set. `:?` menggagalkan deploy.
      - Catatan: `.env` di folder project juga dipakai untuk **interpolasi** file Compose (modul 08). Itu tujuan yang
        berbeda dari `env_file`.
    tests:
      pass:
        - |
          networks:
            internal: {}
          volumes:
            pgdata: {}
            storage: {}
          docker compose config -q
          env_file: [db.env]
          image: ghcr.io/acme/dojo-app:${TAG:?set TAG}
        - |
          networks:
            internal:
          volumes:
            pgdata:
            storage:
          healthcheck:
            test: ["CMD-SHELL", "pg_isready"]
      fail:
        - |
          networks:
            internal: {}
          image: ghcr.io/acme/dojo-app:${TAG:-latest}
        - |
          docker compose up -d
          docker compose logs
        - |
          env_file: [db.env]
          image: ghcr.io/acme/dojo-app:${TAG:-latest}
    source: https://docs.docker.com/reference/compose-file/networks/

---

# Compose: Anatomi compose.yaml

Docker Compose mendefinisikan **seluruh aplikasi**: service, network, volume, beserta hubungan di antaranya, dalam satu file
deklaratif yang bisa di-commit, di-review, dan dijalankan ulang secara identik. Modul ini membedah `compose.yaml` bagian
demi bagian, dari struktur top-level sampai setiap key service yang penting, beserta perilaku yang diuji langsung di lab.
Fitur lanjutan (interpolasi `.env` detail, profiles, override & merge, include, secrets/configs, watch, production)
dibahas di modul berikutnya.

> [!NOTE]
> Diuji dengan **Docker Compose v5.1.3** (plugin `docker compose`, bukan `docker-compose` v1 yang sudah tidak dipelihara)
> di Docker Desktop. Setiap tabel "hasil lab" berasal dari project uji `djc`. File dari project devops-dojo dipakai di studi
> kasus C3. Salinannya divalidasi di folder lab, dan file aslinya tidak diubah.

## 1. Struktur file

```yaml
name: dojo                 # nama project (prefix semua resource)
services:                  # WAJIB — container yang dijalankan
  web: { ... }
  db: { ... }
networks:                  # network bernama yang dipakai service
  frontend: {}
  backend: { internal: true }
volumes:                   # volume bernama
  pgdata: {}
configs: { ... }           # file konfigurasi (modul 08)
secrets: { ... }           # secret (modul 08)
x-common: &common          # extension field: blok yang bisa dipakai ulang
  restart: unless-stopped
```

| Hal | Hasil lab |
|---|---|
| `version: "3.8"` | Peringatan `the attribute version is obsolete, it will be ignored` |
| Nama network default | `djc_default` |
| Nama volume `pgdata` | `djc_pgdata` |
| Nama container | `djc-db-1`, `djc-web-1` |
| Nama project | `-p` > `COMPOSE_PROJECT_NAME` > `name:` > nama folder |
| Network/volume dipakai tapi tidak dideklarasikan | `refers to undefined network internal: invalid compose project` |

### Extension field & anchor YAML

```yaml
x-common: &common
  restart: unless-stopped
  logging:
    driver: json-file
    options: { max-size: "10m", max-file: "3" }
services:
  web:
    <<: *common            # gabungkan blok di atas
    image: nginx:1.30-alpine
```

Key berawalan `x-` diabaikan Compose, sehingga aman dipakai sebagai "variabel" YAML. Di lab, `web` mewarisi
`restart=unless-stopped` dan log `{"max-file":"3","max-size":"10m"}`.

## 2. Referensi key service

### Image, build, dan perintah

```yaml
services:
  app:
    image: ghcr.io/acme/app:${TAG:?set TAG}     # nama image (dan tag hasil build bila ada build:)
    build:
      context: .                               # build context
      dockerfile: docker/app.Dockerfile
      target: app                              # stage multi-stage
      args: { PHP_VERSION: "8.3" }             # ARG
    pull_policy: missing                       # always | missing | never | build
    platform: linux/amd64
    command: ["php-fpm"]                       # menimpa CMD image
    entrypoint: ["docker-entrypoint.sh"]       # menimpa ENTRYPOINT image
    working_dir: /var/www/html
    user: "1000:1000"
    init: true
```

Di lab, service `migrate` dengan `build.args.GREETING: "migrated!"` di-build dan mencetak `migrated!`. Bila ada `image:`
**dan** `build:`, hasil build diberi tag sesuai `image:`.

### Environment

```yaml
    environment:            # bentuk map
      APP_ENV: production
      APP_DEBUG: "false"    # kutip nilai yang bukan teks biasa
      FROM_SHELL:           # tanpa nilai = ambil dari shell yang menjalankan compose
    environment:            # bentuk list (setara)
      - APP_ENV=production
    env_file:
      - app.env
      - path: local.env
        required: false     # tidak error bila file tidak ada
```

| Hasil lab | |
|---|---|
| `environment:` vs `env_file` | `environment:` menang (`fromenvironment`) |
| `QUOTED="with quotes"` di env_file | `with quotes`, kutip dibuang (≠ `docker run --env-file`) |
| `COUNT: 08` tanpa kutip | `"8"`, nol hilang |
| `APP_DEBUG: false` | `"false"` |
| `FROM_SHELL:` | Diisi dari shell: `hello-from-shell` |
| `env_file` dengan `required: false` yang hilang | Diabaikan tanpa error |

> [!WARNING]
> Ada dua mekanisme `.env` yang berbeda:
> - **`env_file:`** mengirim variabel **ke dalam container**.
> - **File `.env` di folder project** dipakai Compose untuk **interpolasi** `${VAR}` di file Compose itu sendiri, dan
>   **tidak** otomatis masuk ke container.
>
> Memakai satu `.env` untuk keduanya (seperti compose devops-dojo: `env_file: .env` di semua service) membuat setiap
> service, termasuk database, menerima semua secret aplikasi.

### Ports dan expose

```yaml
    ports:
      - "127.0.0.1:8080:80"        # pendek: [IP:]HOST:CONTAINER[/protokol] — selalu kutip
      - target: 80                 # panjang
        published: "8080"
        host_ip: 127.0.0.1
        protocol: tcp
    expose: ["9000"]               # dokumentasi saja, antar service tidak butuh publish
```

Compose v5 mem-parse `2222:22` tanpa kutip dengan benar (lab: `published: "2222"`, `target: 22`). Parser YAML lama bisa
membacanya sebagai angka basis-60. Tetap kutip demi keamanan dan keterbacaan.

### Volumes, tmpfs, read_only

```yaml
    volumes:
      - pgdata:/var/lib/postgresql/data          # named volume (harus ada di top-level volumes:)
      - ./conf:/etc/nginx/conf.d:ro              # bind mount relatif terhadap file compose
      - type: bind                               # panjang
        source: ./conf
        target: /etc/nginx/conf.d
        read_only: true
    tmpfs: [/tmp]
    read_only: true
```

Di lab: bind `./conf` → nginx memakai config dari host. Service `api` dengan `read_only: true` + `tmpfs: [/tmp]` →
`touch /x` gagal (`Read-only file system`), sedangkan `/tmp` bisa ditulis.

### Networks

```yaml
    networks:
      backend:
        aliases: [api-internal]      # nama DNS tambahan, hanya di network ini
      frontend: {}
networks:
  frontend: {}
  backend: { internal: true }
```

Di lab: `web` (front) me-resolve `api` (front+back), tetapi tidak me-resolve alias `backend` milik network `back`.

### depends_on dan healthcheck

```yaml
    depends_on:
      db:
        condition: service_healthy              # tunggu healthcheck lolos
        restart: true                           # restart service ini bila db di-restart compose
      migrate:
        condition: service_completed_successfully
      cache:
        condition: service_started              # = bentuk pendek [cache]
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U $${POSTGRES_USER}"]
      interval: 5s
      timeout: 3s
      retries: 10
      start_period: 10s
      start_interval: 1s
```

| Hasil lab | |
|---|---|
| `service_healthy` | `db Waiting` → `db Healthy` → `web Starting` |
| `service_completed_successfully` + exit 0 | `migrate Exited (0)` → `api` start |
| `service_completed_successfully` + exit 1 | `didn't complete successfully: exit 1`, `api` tetap `Created` |
| `["CMD", "test", "-f", "$$TARGET"]` | `unhealthy`, karena variabel tidak diekspansi |
| `["CMD-SHELL", "test -f $$TARGET"]` | `healthy` |
| `up --wait` | Menunggu semua service running/healthy, lalu keluar |

### Restart, resource, logging, keamanan, dan lain-lain

```yaml
    restart: unless-stopped                  # no | always | on-failure[:N] | unless-stopped
    stop_signal: SIGQUIT
    stop_grace_period: 20s                   # lab: Config.StopTimeout=20
    deploy:
      resources:
        limits: { memory: 64m, cpus: "0.5" } # lab: Memory=67108864, NanoCpus=500000000
    mem_limit: 64m                           # bentuk lama, masih didukung
    logging:
      driver: json-file
      options: { max-size: "10m", max-file: "3" }
    cap_drop: [ALL]                          # lab: CapDrop=["ALL"]
    security_opt: ["no-new-privileges:true"]
    extra_hosts: ["host.docker.internal:host-gateway"]
    labels: { com.acme.team: "web" }
    container_name: fixed                    # HINDARI — tidak bisa di-scale, tanpa prefix project
    profiles: [tools]                        # hanya jalan dengan --profile tools (modul 08)
```

> [!TIP]
> Tanpa `logging.options.max-size`, driver `json-file` menyimpan log container **tanpa batas**, dan server yang
> berjalan berbulan-bulan bisa kehabisan disk karena satu container yang cerewet (modul Linux Filesystem: "disk penuh").
> Pasang rotasi log di `x-common` agar semua service mendapatkannya.

## 3. Perintah sehari-hari

| Perintah | Fungsi |
|---|---|
| `docker compose up -d [--wait] [--build]` | Buat/perbarui dan jalankan, hanya recreate yang berubah |
| `docker compose down [-v] [--remove-orphans]` | Hapus container + network (`-v`: volume juga) |
| `docker compose stop` / `start` / `restart` | Tanpa membuat ulang container |
| `docker compose ps [-a]` | Status container project |
| `docker compose logs -f [--tail N] [service]` | Log gabungan |
| `docker compose exec [-T] service cmd` | Masuk ke container yang berjalan |
| `docker compose run --rm service cmd` | Container sekali jalan (tanpa `ports:`) |
| `docker compose config [-q]` | Render/validasi file akhir |
| `docker compose pull` / `build` | Siapkan image |
| `docker compose top` / `port service 80` / `ls` | Proses / port / daftar project |

```mermaid
flowchart LR
    F[compose.yaml + .env] --> C[docker compose config<br/>interpolasi & validasi]
    C --> U[up]
    U --> N[network]
    U --> V[volume]
    U --> S1[db] --> H{healthy?}
    H -- ya --> S2[migrate] --> E{exit 0?}
    E -- ya --> S3[app] --> S4[nginx]
```

## 4. Ringkasan kebiasaan aman

| Kebiasaan | Kenapa |
|---|---|
| `compose.yaml` tanpa `version:`, dengan `name:` eksplisit | `version` obsolete. Nama project menentukan nama volume |
| Deklarasikan semua network/volume di top-level | Bila tidak, project invalid |
| `docker compose config -q` di CI | Menangkap error sebelum deploy |
| Healthcheck + `condition: service_healthy`, bukan `sleep` | Urutan start yang pasti |
| Migrasi sebagai service/`run --rm` terpisah | Sekali per deploy, bukan per replica |
| `env_file` per service, kutip nilai environment | Secret tidak bocor ke service lain. `08` tidak menjadi `8` |
| `$$` untuk variabel yang harus diekspansi di container | `$VAR` diinterpolasi Compose dari host |
| `${TAG:?...}` untuk tag deploy | Tidak diam-diam memakai `latest` |
| Publish hanya proxy, bind `127.0.0.1`. Data di network `internal: true` | Permukaan serangan minimal |
| `deploy.resources.limits` + rotasi log | Satu service tidak bisa menghabiskan RAM/disk |
| Hindari `container_name` | Bisa bentrok, tidak bisa di-scale |
| `down -v` hanya bila memang ingin menghapus data | Volume ikut terhapus permanen |
