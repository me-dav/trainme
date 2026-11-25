---
format: 1
id: docker-storage
course:
  slug: docker
  title: Docker
  description: Container dari nol sampai production — CLI, image, storage, network, Compose, keamanan, dan operasional.
title: Storage — Volume, Bind Mount & tmpfs
summary: Tiga cara menyimpan data container, kapan memakai masing-masing, permission dan UID, backup/restore volume, jebakan upgrade PostgreSQL 18, dan performa bind mount di Docker Desktop Windows.
order: 5
version: 1
status: verified
updated: '2026-11-23'
tested_on:
  - Docker Desktop (Windows) — Docker Engine 29.4.1, driver volume local
  - Image alpine:3.20, nginx:1.27-alpine, postgres:17-alpine, postgres:18-alpine
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: types
    text: Membedakan named volume, bind mount, tmpfs, dan writable layer
  - id: volume-cli
    text: Mengelola volume (create, ls, inspect, rm, filter)
  - id: syntax
    text: Menulis -v dan --mount dengan benar (read-only, tmpfs, opsi driver)
  - id: population
    text: Memahami pengisian volume dari image dan efek bind mount yang menutupi isi image
  - id: permissions
    text: Mengatasi masalah permission/UID pada volume dan bind mount
  - id: databases
    text: Menyimpan data database dengan aman (PostgreSQL, termasuk perubahan di versi 18)
  - id: backup
    text: Backup, restore, dan migrasi data volume
  - id: performance
    text: Memilih jenis mount berdasarkan performa dan platform
sources:
  - title: Docker Docs — Storage overview
    url: https://docs.docker.com/engine/storage/
  - title: Docker Docs — Volumes
    url: https://docs.docker.com/engine/storage/volumes/
  - title: Docker Docs — Bind mounts
    url: https://docs.docker.com/engine/storage/bind-mounts/
  - title: Docker Docs — tmpfs mounts
    url: https://docs.docker.com/engine/storage/tmpfs/
  - title: Docker Docs — Back up, restore, or migrate data volumes
    url: https://docs.docker.com/engine/storage/volumes/#back-up-restore-or-migrate-data-volumes
  - title: docker volume (create, ls, inspect, rm, prune)
    url: https://docs.docker.com/reference/cli/docker/volume/
  - title: docker container run — --read-only, --tmpfs, --mount
    url: https://docs.docker.com/reference/cli/docker/container/run/
  - title: Docker Official Image postgres — PGDATA & initialization
    url: https://github.com/docker-library/docs/blob/master/postgres/README.md
  - title: docker-library/postgres PR 1259 — perubahan path data di PostgreSQL 18
    url: https://github.com/docker-library/postgres/pull/1259
  - title: Docker Desktop — WSL 2 best practices (file system performance)
    url: https://docs.docker.com/desktop/features/wsl/best-practices/
  - title: Docker Docs — Bind mounts & SELinux (:z / :Z)
    url: https://docs.docker.com/engine/storage/bind-mounts/#configure-the-selinux-label
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: command
    objective: volume-cli
    prompt: Buat named volume bernama `pgdata`.
    answers: ['docker volume create pgdata']
    patterns: ['^docker volume create( --driver[= ]local)? pgdata$']
    tests:
      fail: ['docker create volume pgdata', 'docker volume new pgdata', 'mkdir pgdata']
    explanation: 'Volume juga dibuat otomatis saat pertama kali dipakai (`-v pgdata:/...`). Membuatnya eksplisit berguna untuk memberi opsi driver atau label. Di lab: `driver=local mountpoint=/var/lib/docker/volumes/dj-data/_data`.'
    source: https://docs.docker.com/reference/cli/docker/volume/create/

  - id: a02
    tier: A
    type: command
    objective: volume-cli
    prompt: Tampilkan daftar semua volume Docker.
    answers: ['docker volume ls']
    patterns: ['^docker volume (ls|list)( (-q|--quiet))?$']
    tests:
      fail: ['docker volumes', 'docker ls volume', 'docker ps -v']
    explanation: 'Filter yang berguna: `--filter dangling=true` (tidak dipakai container mana pun), `--filter name=pg`, `--filter label=app=api`. Anonymous volume muncul dengan nama hash 64 karakter.'
    source: https://docs.docker.com/reference/cli/docker/volume/ls/

  - id: a03
    tier: A
    type: command
    objective: syntax
    prompt: Jalankan `alpine:3.20` sekali pakai (`--rm`) dengan named volume `appdata` di-mount ke `/data`, lalu jalankan `ls /data`.
    answers: ['docker run --rm -v appdata:/data alpine:3.20 ls /data']
    patterns: ['^docker (container )?run --rm ((-v|--volume)[= ]?appdata:/data|--mount[= ]type=volume,(src|source)=appdata,(dst|target|destination)=/data) alpine:3\.20 ls /data$', '^docker (container )?run ((-v|--volume)[= ]?appdata:/data) --rm alpine:3\.20 ls /data$']
    tests:
      pass: ['docker run -v appdata:/data --rm alpine:3.20 ls /data', 'docker run --rm --mount type=volume,src=appdata,dst=/data alpine:3.20 ls /data']
      fail: ['docker run --rm alpine:3.20 -v appdata:/data ls /data', 'docker run --rm -v /data:appdata alpine:3.20 ls /data', 'docker run --rm --volumes-from appdata alpine:3.20 ls /data']
    explanation: 'Format `-v SUMBER:TUJUAN[:opsi]`, dengan tujuan selalu path di dalam container. Di lab, data yang ditulis satu container (`persisted`) terbaca oleh container lain yang me-mount volume yang sama, walau container pertama sudah dihapus.'
    source: https://docs.docker.com/engine/storage/volumes/

  - id: a04
    tier: A
    type: command
    objective: syntax
    prompt: Jalankan `alpine:3.20` sekali pakai dengan folder host `./config` di-bind ke `/etc/app` secara **read-only**, lalu jalankan `ls /etc/app`.
    answers: ['docker run --rm -v ./config:/etc/app:ro alpine:3.20 ls /etc/app']
    patterns:
      - '^docker (container )?run --rm (-v|--volume)[= ]?(\./config|\$\{?PWD\}?/config|\$\(pwd\)/config):/etc/app:ro alpine:3\.20 ls /etc/app$'
      - '^docker (container )?run --rm --mount[= ]type=bind,(src|source)=(\./config|\$\{?PWD\}?/config|\$\(pwd\)/config),(dst|target|destination)=/etc/app,(readonly|ro)(=true)? alpine:3\.20 ls /etc/app$'
    tests:
      pass: ['docker run --rm -v "$PWD/config":/etc/app:ro alpine:3.20 ls /etc/app', 'docker run --rm --mount type=bind,src=$(pwd)/config,dst=/etc/app,readonly alpine:3.20 ls /etc/app']
      fail: ['docker run --rm -v ./config:/etc/app alpine:3.20 ls /etc/app', 'docker run --rm -v /etc/app:./config:ro alpine:3.20 ls /etc/app', 'docker run --rm -v config:/etc/app:ro alpine:3.20 ls /etc/app']
    explanation: 'Path relatif (`./config`) didukung di `docker run -v` versi modern (lab: `-v ./rel:/r` berhasil). `:ro` membuat penulisan gagal dengan `Read-only file system` (lab). Tanpa `./` atau `/`, `config` dianggap **nama volume**, bukan folder (soal berikutnya).'
    source: https://docs.docker.com/engine/storage/bind-mounts/

  - id: a05
    tier: A
    type: text
    objective: syntax
    prompt: 'Pada `docker run -v config:/etc/app ...`, sumber `config` (tanpa `./` atau `/`) dianggap apa oleh Docker?'
    answers: ['named volume', 'volume bernama config']
    patterns: ['(named volume|volume (bernama|dengan nama)|nama (sebuah )?volume|^volume$)']
    tests:
      pass: ['nama volume', 'volume']
      fail: ['folder config', 'bind mount', 'file config', 'path relatif']
    explanation: 'Sumber yang tidak diawali `/`, `./`, `../`, atau huruf drive (`C:\`) adalah nama volume. Kesalahan klasiknya: kamu mengira config dari folder sudah ter-mount, padahal yang dibuat adalah volume kosong baru bernama `config`, dan aplikasi diam-diam memakai config default.'
    source: https://docs.docker.com/engine/storage/volumes/

  - id: a06
    tier: A
    type: command
    objective: syntax
    prompt: Jalankan `alpine:3.20` sekali pakai dengan `/tmp` sebagai **tmpfs** (di memori), lalu jalankan `df -h /tmp`.
    answers: ['docker run --rm --tmpfs /tmp alpine:3.20 df -h /tmp']
    patterns: ['^docker (container )?run --rm (--tmpfs[= ]/tmp(:\S+)?|--mount[= ]type=tmpfs,(dst|target|destination)=/tmp(,\S+)?) alpine:3\.20 df -h /tmp$']
    tests:
      pass: ['docker run --rm --tmpfs /tmp:size=16m alpine:3.20 df -h /tmp', 'docker run --rm --mount type=tmpfs,dst=/tmp,tmpfs-size=16m alpine:3.20 df -h /tmp']
      fail: ['docker run --rm -v /tmp alpine:3.20 df -h /tmp', 'docker run --rm --tmp /tmp alpine:3.20 df -h /tmp', 'docker run --rm -v tmpfs:/tmp alpine:3.20 df -h /tmp']
    explanation: 'Di lab: `tmpfs 16.0M ... /tmp`. `-v /tmp` (tanpa sumber) membuat **anonymous volume**, bukan tmpfs. `-v tmpfs:/tmp` membuat volume bernama `tmpfs`. Batasi ukuran dengan `size=`, karena tmpfs memakai RAM dan dihitung ke limit memori container.'
    source: https://docs.docker.com/engine/storage/tmpfs/

  - id: a07
    tier: A
    type: command
    objective: volume-cli
    prompt: Tampilkan detail volume `pgdata` (driver, mountpoint, label, waktu dibuat).
    answers: ['docker volume inspect pgdata']
    patterns: ['^docker volume inspect( (-f|--format)[= ]?\S+)? pgdata$']
    tests:
      fail: ['docker inspect volume pgdata', 'ls /var/lib/docker/volumes', 'docker volume ls pgdata']
    explanation: '`Mountpoint` berada di dalam VM Docker Desktop (lab: `/var/lib/docker/volumes/dj-data/_data`), bukan di folder Windows. Di Linux, path itu ada di host, tetapi jangan diubah langsung. Akses datanya lewat container.'
    source: https://docs.docker.com/reference/cli/docker/volume/inspect/

  - id: a08
    tier: A
    type: command
    objective: volume-cli
    prompt: Hapus volume `pgdata`.
    answers: ['docker volume rm pgdata']
    patterns: ['^docker volume (rm|remove) pgdata$']
    tests:
      fail: ['docker rm pgdata', 'docker rmi pgdata', 'docker volume delete pgdata']
    explanation: 'Volume yang sedang dipakai container (berjalan maupun berhenti) ditolak. Di lab: `volume is in use - [dada55d2...]`. Menghapus volume berarti menghapus datanya secara **permanen**, dan Docker tidak punya tempat sampah.'
    source: https://docs.docker.com/reference/cli/docker/volume/rm/

  - id: a09
    tier: A
    type: command
    objective: volume-cli
    prompt: Hapus container `web` yang sudah berhenti **beserta anonymous volume** miliknya.
    answers: ['docker rm -v web']
    patterns: ['^docker (container )?rm (-v|--volumes)( -f| --force)? web$', '^docker (container )?rm (-f|--force) (-v|--volumes) web$', '^docker (container )?rm -(fv|vf) web$']
    tests:
      pass: ['docker rm --volumes web', 'docker rm -fv web']
      fail: ['docker rm web', 'docker volume rm web', 'docker rm -f web']
    explanation: 'Di lab, `postgres:17-alpine` tanpa `-v` membuat anonymous volume (karena image punya `VOLUME /var/lib/postgresql/data`). Setelah `docker rm -f`, volume itu **masih ada**; dengan `rm -f -v`, ikut terhapus. Named volume tidak pernah dihapus oleh `-v`.'
    source: https://docs.docker.com/reference/cli/docker/container/rm/

  - id: a10
    tier: A
    type: text
    objective: types
    prompt: Jenis mount apa yang datanya hanya ada di **memori** dan hilang saat container berhenti?
    answers: ['tmpfs']
    tests:
      fail: ['volume', 'bind', 'overlay', 'named volume']
    explanation: 'Cocok untuk cache sementara, file lock, dan data sensitif yang tidak boleh menyentuh disk. Hanya tersedia di container Linux. Di lab, setelah `docker restart`, isi tmpfs `0` file, sedangkan volume dan writable layer masih berisi.'
    source: https://docs.docker.com/engine/storage/tmpfs/

  - id: a11
    tier: A
    type: text
    objective: volume-cli
    prompt: Apa nama **driver volume default** Docker?
    answers: ['local']
    tests:
      fail: ['nfs', 'overlay2', 'tmpfs', 'bind']
    explanation: 'Driver `local` menyimpan data di disk host, dan dengan opsi `type`/`device`/`o` ia juga bisa me-mount tmpfs, NFS, atau CIFS (lab: volume tmpfs 8 MB). Plugin pihak ketiga menyediakan driver untuk storage jaringan/cloud. `overlay2` adalah storage driver untuk **layer image**, bukan driver volume.'
    source: https://docs.docker.com/engine/storage/volumes/#use-a-volume-driver

  - id: a12
    tier: A
    type: command
    objective: syntax
    prompt: Jalankan `alpine:3.20` dengan **root filesystem read-only**, tetapi `/tmp` tetap bisa ditulis lewat tmpfs, lalu jalankan `touch /tmp/x`.
    answers: ['docker run --rm --read-only --tmpfs /tmp alpine:3.20 touch /tmp/x']
    patterns: ['^docker (container )?run (?=.*--read-only( |$))(?=.*(--tmpfs[= ]/tmp(:\S+)?|--mount[= ]type=tmpfs,(dst|target|destination)=/tmp\S*)( |$))((--rm|--read-only|--tmpfs[= ]/tmp(:\S+)?|--mount[= ]type=tmpfs,\S+) )+alpine:3\.20 touch /tmp/x$']
    tests:
      pass: ['docker run --read-only --tmpfs /tmp:size=16m --rm alpine:3.20 touch /tmp/x']
      fail: ['docker run --rm --read-only alpine:3.20 touch /tmp/x', 'docker run --rm --ro --tmpfs /tmp alpine:3.20 touch /tmp/x', 'docker run --rm -v /:/:ro alpine:3.20 touch /tmp/x']
    explanation: 'Di lab: `--read-only` saja membuat `touch /tmp/x` dan `/etc/x` gagal (`Read-only file system`); dengan `--tmpfs /tmp` → `tmp-ok`. Ini hardening yang bagus. Penyerang yang masuk tidak bisa menanam binary atau mengubah file aplikasi. Direktori yang memang harus bisa ditulis (cache, upload) di-mount secara eksplisit.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#read-only

  - id: a13
    tier: A
    type: text
    objective: volume-cli
    prompt: Di host Linux, di direktori mana Docker menyimpan data named volume (driver local)?
    answers: ['/var/lib/docker/volumes', '/var/lib/docker/volumes/']
    tests:
      fail: ['/var/lib/docker/overlay2', '/mnt/docker', '/home/docker', '/var/lib/containers']
    explanation: 'Setiap volume ada di `/var/lib/docker/volumes/<nama>/_data` (lab: `Mountpoint`). Partisi tempat `/var/lib/docker` berada harus cukup besar, karena database di volume akan tumbuh di sana. Docker rootless dan Podman memakai lokasi lain.'
    source: https://docs.docker.com/engine/storage/volumes/

  - id: a14
    tier: A
    type: text
    objective: types
    prompt: Container `db` memakai named volume `pgdata`. Setelah `docker rm -f db` (tanpa `-v`), apakah data di `pgdata` masih ada? (ya/tidak)
    answers: ['ya', 'yes']
    tests:
      fail: ['tidak', 'no']
    explanation: 'Siklus hidup volume terpisah dari container. Di lab, tabel yang dibuat di PostgreSQL masih ada (`select x from t` → `42`) setelah container dihapus dan dibuat ulang dengan volume yang sama. Inilah alasan utama memakai volume: container boleh dibuang dan diganti versinya, data tetap ada.'
    source: https://docs.docker.com/engine/storage/volumes/

  - id: a15
    tier: A
    type: command
    objective: syntax
    prompt: Seperti soal sebelumnya, tetapi pakai sintaks **`--mount`**, bukan `-v`. Mount named volume `appdata` ke `/data` di `alpine:3.20` (`--rm`), jalankan `ls /data`.
    answers: ['docker run --rm --mount type=volume,src=appdata,dst=/data alpine:3.20 ls /data']
    patterns: ['^docker (container )?run --rm --mount[= ]type=volume,((src|source)=appdata,(dst|target|destination)=/data|(dst|target|destination)=/data,(src|source)=appdata) alpine:3\.20 ls /data$']
    tests:
      pass: ['docker run --rm --mount type=volume,source=appdata,target=/data alpine:3.20 ls /data', 'docker run --rm --mount type=volume,dst=/data,src=appdata alpine:3.20 ls /data']
      fail: ['docker run --rm --mount appdata:/data alpine:3.20 ls /data', 'docker run --rm --mount type=bind,src=appdata,dst=/data alpine:3.20 ls /data', 'docker run --rm --mount type=volume,src=/data,dst=appdata alpine:3.20 ls /data']
    explanation: '`--mount` lebih panjang, tetapi eksplisit (key=value) dan mendukung opsi yang tidak ada di `-v` (`volume-opt`, `tmpfs-size`, `volume-subpath`). Dokumentasi Docker merekomendasikannya untuk skrip.'
    source: https://docs.docker.com/engine/storage/volumes/#choose-the--v-or---mount-flag

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: population
    prompt: Sebuah named volume **kosong** di-mount ke `/usr/share/nginx/html` pada image nginx. Apa isi direktori itu di dalam container?
    answers: ['file bawaan image (index.html, 50x.html)']
    patterns: ['(index\.html|50x|isi (dari )?image|file (bawaan|dari|milik) image|disalin dari image|terisi|copied from image)']
    tests:
      pass: ['index.html dan 50x.html', 'diisi dari image']
      fail: ['kosong', 'error', 'tidak ada file']
    explanation: 'Docker menyalin isi image di path itu ke volume yang **kosong**, termasuk ownership dan permission. Di lab: `50x.html index.html`. Hal ini terjadi hanya sekali, saat volume kosong. Volume yang sudah berisi tidak disentuh, sehingga file baru di image versi baru **tidak** muncul di volume lama.'
    source: https://docs.docker.com/engine/storage/volumes/#populate-a-volume-using-a-container

  - id: b02
    tier: B
    type: text
    objective: population
    prompt: Folder host yang **kosong** di-bind mount ke `/usr/share/nginx/html`. Apa isi direktori itu di dalam container?
    answers: ['kosong', 'empty']
    patterns: ['^(kosong|empty|tidak ada (file|apa-apa)|0( file)?)$']
    tests:
      pass: ['tidak ada file', '0']
      fail: ['index.html', 'isi image', '50x.html index.html']
    explanation: 'Bind mount **menutupi** isi image di path itu, sama seperti `mount` di Linux (modul Linux Filesystem). Di lab: `ls -A` → `0` file. Ini penyebab umum "file aplikasi hilang" saat bind-mount folder kosong ke `/var/www/html`, atau saat folder di host belum ter-checkout.'
    source: https://docs.docker.com/engine/storage/bind-mounts/#mount-into-a-non-empty-directory-on-the-container

  - id: b03
    tier: B
    type: text
    objective: permissions
    prompt: 'Container dijalankan `-u 1000` dengan volume baru di `/data` (image **tidak** punya direktori `/data`). Apa hasil `touch /data/f`?'
    answers: ['permission denied']
    patterns: ['(permission denied|ditolak|gagal|tidak (bisa|boleh|diizinkan)|denied)']
    tests:
      pass: ['ditolak', 'gagal karena permission']
      fail: ['berhasil', 'sukses', 'ok']
    explanation: 'Di lab: `/data` dibuat milik `root root` dengan mode `drwxr-xr-x`, sehingga user 1000 → `Permission denied`. Ini masalah klasik image non-root (modul Linux Permission). Solusinya ada di soal berikutnya: siapkan direktorinya di image, milik user aplikasi.'
    source: https://docs.docker.com/engine/storage/volumes/

  - id: b04
    tier: B
    type: command
    objective: permissions
    prompt: Tulis satu `RUN` di Dockerfile (sebelum `USER 1000`) yang membuat `/data` dimiliki UID/GID `1000`, supaya volume yang di-mount ke sana bisa ditulis user aplikasi.
    answers: ['RUN mkdir -p /data && chown 1000:1000 /data']
    patterns: ['^RUN mkdir (-p )?/data && chown (-R )?1000(:1000)? /data$', '^RUN install -d (-o 1000 -g 1000|-g 1000 -o 1000)( -m \d+)? /data$']
    tests:
      pass: ['RUN install -d -o 1000 -g 1000 /data', 'RUN mkdir /data && chown 1000:1000 /data']
      fail: ['RUN chmod 777 /data', 'VOLUME /data', 'RUN chown 1000:1000']
    explanation: 'Karena volume kosong diisi dari image **beserta ownership**-nya, volume baru langsung milik 1000 (lab: `drwxr-xr-x 1000 1000 /data`, `write-ok`). Bahkan volume lama yang **kosong** ikut terisi ownership ini (lab). Volume yang sudah berisi data milik root tidak berubah, dan perlu `chown` sekali jalan: `docker run --rm -u 0 -v vol:/data alpine chown -R 1000:1000 /data`.'
    source: https://docs.docker.com/engine/storage/volumes/#populate-a-volume-using-a-container

  - id: b05
    tier: B
    type: text
    objective: databases
    prompt: Pada image `postgres:18`, instruksi `VOLUME` dideklarasikan di path apa?
    answers: ['/var/lib/postgresql']
    tests:
      fail: ['/var/lib/postgresql/data', '/var/lib/postgresql/18/docker', '/data', '/var/lib/postgres']
    explanation: 'Di lab, `postgres:17-alpine` → `Volumes={"/var/lib/postgresql/data":{}}`, `PGDATA=/var/lib/postgresql/data`. `postgres:18-alpine` → `Volumes={"/var/lib/postgresql":{}}`, `PGDATA=/var/lib/postgresql/18/docker`, yaitu direktori per versi mayor agar upgrade dengan `pg_upgrade` lebih mudah. Contoh Compose lama yang me-mount `/var/lib/postgresql/data` tidak lagi cocok untuk 18.'
    source: https://github.com/docker-library/postgres/pull/1259

  - id: b06
    tier: B
    type: text
    objective: databases
    prompt: 'Kamu mengganti `image: postgres:17-alpine` menjadi `postgres:18-alpine`, dengan volume tetap di-mount ke `/var/lib/postgresql/data`. Apa yang terjadi saat container start?'
    answers: ['container berhenti dengan error, meminta pg_upgrade']
    patterns: ['(error|exit|gagal|menolak|berhenti|crash|tidak (mau )?(start|jalan|berjalan)|pg_upgrade)']
    tests:
      pass: ['Exited (1) dengan pesan error', 'menolak start']
      fail: ['data otomatis di-upgrade', 'jalan normal', 'data lama dipakai begitu saja']
    explanation: 'Di lab: `Exited (1)` dengan pesan `Error: in 18+, these Docker images are configured to store database data in a format which is compatible with "pg_ctlcluster"... there appears to be PostgreSQL data in: /var/lib/postgresql/data (unused mount/volume)`. Perilaku ini aman, karena image menolak start daripada membuat cluster baru yang kosong. Upgrade versi mayor PostgreSQL selalu butuh migrasi: `pg_dump`/restore, atau `pg_upgrade`. Tidak cukup hanya mengganti tag.'
    source: https://github.com/docker-library/postgres/pull/1259

  - id: b07
    tier: B
    type: text
    objective: databases
    prompt: 'Kamu mengganti nilai `POSTGRES_PASSWORD`, lalu membuat ulang container dengan volume data lama yang sudah berisi database. Apakah password baru berlaku? (ya/tidak)'
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Variabel `POSTGRES_*` hanya dipakai saat **inisialisasi** volume kosong. Di lab: `PostgreSQL Database directory appears to contain a database; Skipping initialization`. Untuk mengganti password, jalankan `ALTER USER ... PASSWORD ...` di database. Ini juga menjelaskan kenapa "aku sudah ganti env tapi tetap tidak bisa login".'
    source: https://github.com/docker-library/docs/blob/master/postgres/README.md

  - id: b08
    tier: B
    type: command
    objective: backup
    prompt: 'Backup isi volume `pgdata` (mount read-only) menjadi `pgdata.tgz` di direktori saat ini, memakai container `alpine:3.20` sementara dan `tar`.'
    answers: ['docker run --rm -v pgdata:/data:ro -v "$PWD":/backup alpine:3.20 tar czf /backup/pgdata.tgz -C /data .']
    patterns: ['^docker (container )?run (?=.*(-v|--volume)[= ]?pgdata:/data(:ro)?( |$))(?=.*(-v|--volume)[= ]?(\$\{?PWD\}?|\$\(pwd\)|\.|\./):/backup( |$))((--rm|(-v|--volume)[= ]?\S+) )+alpine(:\S+)? tar (-?czf|-?zcf|-czvf|-zcvf|-cvzf) /backup/pgdata\.tgz -C /data \.$']
    tests:
      pass: ['docker run --rm -v "$(pwd)":/backup -v pgdata:/data:ro alpine:3.20 tar -czf /backup/pgdata.tgz -C /data .']
      fail: ['docker cp pgdata:/data ./pgdata.tgz', 'tar czf pgdata.tgz /var/lib/docker/volumes/pgdata', 'docker run --rm -v pgdata:/data alpine:3.20 tar czf /data/pgdata.tgz /data']
    explanation: 'Pola resmi dari dokumentasi Docker: satu container sementara me-mount volume dan folder host, lalu menjalankan tar. Restore-nya: `tar xzf /backup/pgdata.tgz -C /data` ke volume baru (lab: `persisted` terbaca di volume hasil restore). Untuk **database yang sedang berjalan**, salinan file bisa tidak konsisten. Hentikan container dulu, atau pakai tool database (`pg_dump`).'
    source: https://docs.docker.com/engine/storage/volumes/#back-up-restore-or-migrate-data-volumes

  - id: b09
    tier: B
    type: text
    objective: performance
    prompt: 'Docker Desktop di Windows, operasi 3.000 file kecil (buat, baca, hapus). Mana yang **jauh** lebih cepat: bind mount dari folder Windows, atau named volume?'
    answers: ['named volume', 'volume']
    patterns: ['^(named |docker )?volume']
    tests:
      pass: ['volume docker']
      fail: ['bind mount', 'sama saja', 'bind', 'folder windows']
    explanation: 'Diukur di lab: bind dari folder Windows **19.347 ms**, named volume **639 ms**, sekitar 30× lebih cepat. File Windows harus melewati batas filesystem ke VM Linux Docker Desktop. Inilah sebabnya `composer install` atau Laravel dengan `vendor/` di bind mount terasa lambat di Windows. Dokumentasi Docker menyarankan menaruh source di filesystem WSL 2, atau menaruh direktori berat (`vendor`, `node_modules`) di named volume.'
    source: https://docs.docker.com/desktop/features/wsl/best-practices/

  - id: b10
    tier: B
    type: text
    objective: types
    prompt: Data yang ditulis ke **tmpfs** container, setelah `docker restart` container yang sama?
    answers: ['hilang']
    patterns: ['(hilang|tidak ada|kosong|lenyap|gone|terhapus)']
    tests:
      pass: ['tidak ada lagi', 'kosong']
      fail: ['masih ada', 'tetap', 'tersimpan']
    explanation: 'Di lab, setelah restart: `tmpfs:0 volume:1 writable-layer:1`. tmpfs dibuat ulang setiap kali container start.'
    source: https://docs.docker.com/engine/storage/tmpfs/

  - id: b11
    tier: B
    type: text
    objective: types
    prompt: File yang ditulis ke **writable layer** container (bukan volume, bukan tmpfs), setelah `docker restart` container yang **sama**?
    answers: ['masih ada']
    patterns: ['(masih ada|tetap( ada)?|^ada$|bertahan|persist|tersimpan)']
    tests:
      pass: ['tetap ada', 'bertahan']
      fail: ['hilang', 'tidak ada', 'kosong']
    explanation: 'Writable layer milik container itu dan baru hilang saat container **dihapus** (`rm`), bukan saat restart (lab: `writable-layer:1`). Karena itu "bekerja setelah restart" bukan bukti data aman, karena deploy biasanya membuat ulang container. Writable layer juga lebih lambat (copy-on-write) dan tidak cocok untuk data yang banyak ditulis.'
    source: https://docs.docker.com/engine/storage/

  - id: b12
    tier: B
    type: command
    objective: syntax
    prompt: Buat volume `cache` dengan driver `local` yang sebenarnya **tmpfs** berukuran maksimal 64 MB (pakai `--opt`).
    answers: ['docker volume create --driver local --opt type=tmpfs --opt device=tmpfs --opt o=size=64m cache']
    patterns: ['^docker volume create (?=.*--opt[= ]type=tmpfs( |$))(?=.*--opt[= ]device=tmpfs( |$))(?=.*--opt[= ]o=size=64m( |$))((--driver[= ]local|-d local|--opt[= ]\S+|-o \S+) )+cache$']
    tests:
      pass: ['docker volume create --opt type=tmpfs --opt device=tmpfs --opt o=size=64m cache']
      fail: ['docker volume create --tmpfs cache', 'docker volume create cache --size 64m', 'docker run --tmpfs cache']
    explanation: 'Opsi driver local diteruskan ke `mount(8)`: `type`, `device`, `o`. Di lab: `tmpfs 8.0M ... /t`. Pola yang sama dipakai untuk NFS: `--opt type=nfs --opt o=addr=10.0.0.5,rw --opt device=:/export/data`. Bedanya dengan `--tmpfs`, volume ini punya nama dan bisa dipakai banyak container, tetapi isinya tetap hilang saat tidak ada yang me-mount.'
    source: https://docs.docker.com/reference/cli/docker/volume/create/#driver-specific-options

  - id: b13
    tier: B
    type: command
    objective: volume-cli
    prompt: '`docker volume rm pgdata` gagal karena volume sedang dipakai. Tampilkan semua container (termasuk yang berhenti) yang memakai volume `pgdata`.'
    answers: ['docker ps -a --filter volume=pgdata']
    patterns: ['^docker (ps|container ls) (-a |--all )?--filter[= ]volume=pgdata( -a| --all)?$', '^docker (ps|container ls) -a -f volume=pgdata$']
    tests:
      pass: ['docker ps --all --filter volume=pgdata', 'docker ps --filter volume=pgdata -a']
      fail: ['docker volume inspect pgdata', 'docker ps --volume pgdata', 'docker volume ls --filter used=pgdata']
    explanation: 'Di lab, pesan error hanya menampilkan ID container (`volume is in use - [dada55d2...]`). Filter `volume=` menunjukkan nama dan statusnya. Tanpa `-a`, container yang **berhenti** tidak tampil, padahal ia tetap mengunci volume.'
    source: https://docs.docker.com/reference/cli/docker/container/ls/#filter

  - id: b14
    tier: B
    type: command
    objective: permissions
    prompt: 'Di host dengan SELinux enforcing (RHEL/Fedora), bind-mount `./data` ke `/data` di `alpine:3.20` (`--rm`, `ls /data`) dengan relabel **private** untuk container ini saja.'
    answers: ['docker run --rm -v ./data:/data:Z alpine:3.20 ls /data']
    patterns: ['^docker (container )?run --rm (-v|--volume)[= ]?(\./data|\$\{?PWD\}?/data|\$\(pwd\)/data):/data:Z alpine:3\.20 ls /data$']
    tests:
      pass: ['docker run --rm -v "$PWD/data":/data:Z alpine:3.20 ls /data']
      fail: ['docker run --rm -v ./data:/data:z alpine:3.20 ls /data', 'docker run --rm -v ./data:/data alpine:3.20 ls /data', 'docker run --rm --privileged -v ./data:/data alpine:3.20 ls /data']
    explanation: 'Tanpa label yang benar, SELinux menolak akses walau permission Unix benar (`Permission denied`). `:z` (kecil) memberi label **shared** untuk beberapa container, `:Z` (besar) **private** untuk satu container. Jangan memakai `:Z` pada direktori sistem seperti `/home` atau `/usr`, karena relabel-nya bisa merusak akses host. (Tidak diuji di lab; Docker Desktop tidak memakai SELinux.)'
    source: https://docs.docker.com/engine/storage/bind-mounts/#configure-the-selinux-label

  - id: b15
    tier: B
    type: text
    objective: syntax
    prompt: 'Di Docker Desktop (lab), `docker run -v C:\path\yang-belum-ada:/x ...`. Apakah Docker membuat folder host itu secara otomatis? (ya/tidak)'
    answers: ['ya', 'yes']
    tests:
      fail: ['tidak', 'no']
    explanation: 'Di lab, `-v` **dan** `--mount type=bind` dengan sumber yang belum ada sama-sama membuat folder kosong, tanpa error. Menurut dokumentasi, di Docker Engine Linux `--mount` menolak dengan `bind source path does not exist`, sedangkan `-v` membuat direktori, milik root. Typo di path bind mount bisa diam-diam menghasilkan folder kosong, sehingga aplikasi berjalan tanpa config/data yang kamu kira ada. Biasakan memeriksa dengan `docker inspect -f ''{{json .Mounts}}''`.'
    source: https://docs.docker.com/engine/storage/bind-mounts/#differences-between--v-and---mount-behavior

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: databases
    prompt: |
      Developer menjalankan PostgreSQL di server staging dengan `docker run -d --name db -e POSTGRES_PASSWORD=... postgres:17-alpine`
      (tanpa `-v`). Kemarin ia menjalankan `docker rm -f db`, lalu `docker run` lagi dengan perintah yang sama, dan sekarang
      database **kosong**. Tulis langkah (satu perintah per baris) untuk mencoba memulihkan data lama, lalu menjalankan
      database dengan benar dan memasang backup.
    context: |
      $ docker volume ls --filter dangling=true
      DRIVER    VOLUME NAME
      local     3e48ea032f9a1d4c0b3b0b5d0a7c1f6c2b8d9e1f0a4b5c6d7e8f9a0b1c2d3e4f
      local     9b1c77de01f4a8b2c3d4e5f60718293a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e
      $ docker inspect -f '{{range .Mounts}}{{.Name}}{{end}}' db
      9b1c77de01f4a8b2c3d4e5f60718293a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e
    checklist:
      - id: find-anon
        label: Mencari anonymous volume yang tertinggal (volume ls --filter dangling=true)
        required: true
        patterns: ['docker\s+volume\s+ls(\s+.*(dangling|-f|--filter))?']
      - id: inspect
        label: Memeriksa volume (inspect CreatedAt) untuk memilih yang lama
        patterns: ['docker\s+volume\s+inspect']
      - id: reattach
        label: Menjalankan postgres dengan volume lama (hash 3e48…) di /var/lib/postgresql/data
        required: true
        patterns: ['(-v|--volume)\s+3e48ea032f9a\w*:/var/lib/postgresql/data', '--mount\s+type=volume,(src|source)=3e48ea032f9a\w*']
      - id: dump
        label: Mengamankan data dengan pg_dump
        patterns: ['pg_dump(all)?\b']
      - id: named
        label: Menjalankan database dengan named volume yang jelas
        required: true
        patterns: ['(-v|--volume)\s+[a-z][\w-]*:/var/lib/postgresql/data', '--mount\s+type=volume,(src|source)=[a-z][\w-]*,']
      - id: no-prune
        label: Tidak menjalankan volume prune / system prune --volumes (menghapus data yang ingin dipulihkan)
        forbid: true
        patterns: ['docker\s+volume\s+prune', 'docker\s+system\s+prune\s+.*--volumes']
      - id: no-rm-v
        label: Tidak menghapus volume lama sebelum data dipulihkan
        forbid: true
        patterns: ['docker\s+volume\s+rm\s+3e48ea032f9a']
    pass_score: 0.6
    solution: |
      ```bash
      docker volume ls --filter dangling=true
      docker volume inspect -f '{{.Name}} {{.CreatedAt}}' 3e48ea032f9a1d4c0b3b0b5d0a7c1f6c2b8d9e1f0a4b5c6d7e8f9a0b1c2d3e4f
      docker rm -f db
      docker run -d --name db-recover -e POSTGRES_PASSWORD=... \
        -v 3e48ea032f9a1d4c0b3b0b5d0a7c1f6c2b8d9e1f0a4b5c6d7e8f9a0b1c2d3e4f:/var/lib/postgresql/data postgres:17-alpine
      docker exec db-recover pg_dumpall -U postgres > recover.sql
      docker volume create pgdata
      docker run -d --name db -e POSTGRES_PASSWORD=... -v pgdata:/var/lib/postgresql/data postgres:17-alpine
      docker exec -i db psql -U postgres < recover.sql
      ```
      Image postgres mendeklarasikan `VOLUME`, sehingga `docker run` tanpa `-v` membuat **anonymous volume**. Di lab,
      volume itu tetap ada setelah `docker rm -f` (hanya `rm -v` yang menghapusnya), dan `docker run` berikutnya membuat
      volume anonim **baru** yang kosong. Data lama biasanya masih bisa diselamatkan, **selama tidak ada yang menjalankan**
      `docker volume prune`. Setelah pulih:
      - Pakai named volume yang jelas.
      - Pasang `pg_dump` terjadwal ke lokasi di luar server. Volume bukan backup, karena disk server yang mati ikut membawa
        volumenya.
    tests:
      pass:
        - |
          docker volume ls --filter dangling=true
          docker volume inspect 3e48ea032f9a1d4c0b3b0b5d0a7c1f6c2b8d9e1f0a4b5c6d7e8f9a0b1c2d3e4f
          docker run -d --name old -v 3e48ea032f9a1d4c0b3b0b5d0a7c1f6c2b8d9e1f0a4b5c6d7e8f9a0b1c2d3e4f:/var/lib/postgresql/data postgres:17-alpine
          docker exec old pg_dumpall -U postgres > dump.sql
          docker run -d --name db -v pgdata:/var/lib/postgresql/data postgres:17-alpine
        - |
          docker volume ls
          docker run -d --mount type=volume,src=3e48ea032f9a1d4c0b3b0b5d0a7c1f6c2b8d9e1f0a4b5c6d7e8f9a0b1c2d3e4f,dst=/var/lib/postgresql/data postgres:17-alpine
          docker run -d --name db --mount type=volume,source=pgdata,target=/var/lib/postgresql/data postgres:17-alpine
      fail:
        - |
          docker volume prune -f
          docker run -d --name db -v pgdata:/var/lib/postgresql/data postgres:17-alpine
        - |
          docker volume ls
          docker volume rm 3e48ea032f9a1d4c0b3b0b5d0a7c1f6c2b8d9e1f0a4b5c6d7e8f9a0b1c2d3e4f
          docker run -d --name db -v pgdata:/var/lib/postgresql/data postgres:17-alpine
        - |
          docker restart db
          docker logs db
    source: https://docs.docker.com/reference/cli/docker/container/rm/

  - id: c02
    tier: C
    type: scenario
    objective: permissions
    prompt: |
      Rancang storage untuk Laravel di container production (php-fpm `www-data` + nginx), satu baris per keputusan
      (opsi `-v`/`--mount`, baris Dockerfile, atau env). Data upload pengguna harus bertahan saat deploy dan dilayani nginx,
      log harus terbaca tanpa masuk ke container, dan kode aplikasi **tidak** boleh diubah dari luar image.
    checklist:
      - id: uploads
        label: Named volume untuk storage/app (upload)
        required: true
        patterns: ['[a-z][\w-]*:/var/www/html/storage/app(/public)?(\s|:|$)', '(src|source)=[a-z][\w-]*,(dst|target|destination)=/var/www/html/storage/app']
      - id: nginx-ro
        label: Volume upload di-mount read-only ke nginx
        patterns: [':/var/www/html/storage/app(/public)?:ro\b', 'storage/app\S*,readonly']
      - id: logs
        label: Log ke stdout/stderr (LOG_CHANNEL=stderr), bukan file di container
        patterns: ['log_channel\s*=\s*stderr', 'log_channel=stderr', 'stderr']
      - id: ownership
        label: Direktori storage milik www-data (Dockerfile chown/--chown)
        patterns: ['chown\s+(-r\s+)?www-data', '--chown=www-data', 'install\s+-d\s+-o\s+www-data']
      - id: backup
        label: Backup volume upload (tar/restic/rclone ke luar server)
        patterns: ['\btar\s+', 'restic', 'rclone', 'backup']
      - id: no-code-bind
        label: Tidak bind-mount source aplikasi ke /var/www/html di production
        forbid: true
        patterns: ['(\./?|\$\{?pwd\}?/?|\$\(pwd\)/?)\S*:/var/www/html(\s|:|$)']
      - id: no-777
        label: Tidak chmod 777
        forbid: true
        patterns: ['chmod\s+(-\S+\s+)*0?777']
    pass_score: 0.6
    solution: |
      ```text
      app:   --mount type=volume,src=uploads,dst=/var/www/html/storage/app
      nginx: --mount type=volume,src=uploads,dst=/var/www/html/storage/app,readonly
      env:   LOG_CHANNEL=stderr
      Dockerfile (runtime): COPY --from=vendor --chown=www-data:www-data /app ./
      Dockerfile (runtime): RUN install -d -o www-data -g www-data storage/app/public storage/framework/{cache,sessions,views} bootstrap/cache
      backup: docker run --rm -v uploads:/data:ro -v /srv/backup:/backup alpine tar czf /backup/uploads-$(date +%F).tgz -C /data .
      ```
      Prinsipnya: **kode di image** (immutable, sama di semua lingkungan), **data di volume**, **log ke stdout**.

      Volume `uploads` yang kosong diisi dari image beserta ownership `www-data` (lab: volume kosong mewarisi
      `1000:1000` dari image), sehingga php-fpm bisa menulis tanpa `chmod 777`. nginx hanya butuh membaca, jadi mount
      `readonly`. Dengan itu, nginx yang terkompromi pun tidak bisa menulis ke data.

      Bind mount kode ke `/var/www/html` cocok untuk development, tetapi di production ia membuat isi container berbeda
      dari image yang sudah diuji. Bind mount itu juga menutupi `vendor/` dan `public/build` dari image (lab: bind
      mount folder kosong → `0` file). Session/cache sebaiknya ke Redis/database, karena direktori `framework/` di
      volume membuat banyak replica berbagi file lock.
    tests:
      pass:
        - |
          -v uploads:/var/www/html/storage/app
          -v uploads:/var/www/html/storage/app:ro
          LOG_CHANNEL=stderr
          RUN chown -R www-data:www-data storage bootstrap/cache
          docker run --rm -v uploads:/data:ro -v /srv/backup:/backup alpine tar czf /backup/uploads.tgz -C /data .
        - |
          --mount type=volume,src=uploads,dst=/var/www/html/storage/app
          --mount type=volume,src=uploads,dst=/var/www/html/storage/app/public,readonly
          COPY --chown=www-data:www-data . .
          restic backup /srv/backup
      fail:
        - |
          -v ./:/var/www/html
          chmod -R 777 storage
        - |
          -v uploads:/var/www/html/storage/app
          chmod 777 /var/www/html/storage
        - |
          LOG_CHANNEL=stderr
          -v $PWD:/var/www/html
          -v uploads:/var/www/html/storage/app
    source: https://docs.docker.com/engine/storage/volumes/

  - id: c03
    tier: C
    type: scenario
    objective: backup
    prompt: |
      Container `redis` (volume `redisdata` di `/data`) harus dipindah dari server lama ke server baru tanpa kehilangan data.
      Tulis langkahnya (satu perintah per baris): hentikan penulisan agar konsisten, backup volume ke file, pindahkan,
      restore ke volume baru di server tujuan, jalankan, dan verifikasi.
    checklist:
      - id: stop
        label: Hentikan container (atau minta Redis menyimpan snapshot lalu stop) agar konsisten
        required: true
        patterns: ['docker\s+(container\s+)?stop\s+redis', 'docker\s+compose\s+stop', 'redis-cli\s+.*(save|shutdown)']
      - id: backup
        label: tar isi volume lewat container sementara (volume di-mount)
        required: true
        patterns: ['-v\s+redisdata:/\S+.*\btar\s+-?c\S*f', '--mount\s+type=volume,(src|source)=redisdata.*\btar\s+-?c\S*f']
      - id: ro
        label: Volume sumber di-mount read-only saat backup
        patterns: ['redisdata:/\S+:ro\b', '(src|source)=redisdata,\S*readonly']
      - id: transfer
        label: Pindahkan file (scp/rsync)
        patterns: ['\b(scp|rsync)\b']
      - id: restore
        label: Restore ke volume baru (tar x ke volume)
        required: true
        patterns: ['-v\s+redisdata\S*:/\S+.*\btar\s+-?x\S*f', '--mount\s+type=volume,(src|source)=redisdata.*\btar\s+-?x\S*f']
      - id: verify
        label: Verifikasi (redis-cli dbsize/get, ls isi volume, logs)
        patterns: ['redis-cli\s+.*(dbsize|get|keys|info)', 'docker\s+(logs|exec)\s+', '\bls\s+']
      - id: no-internals
        label: Tidak menyalin langsung /var/lib/docker/volumes
        forbid: true
        patterns: ['(cp|rsync|scp|tar)\s+.*?/var/lib/docker/volumes']
    pass_score: 0.6
    solution: |
      ```bash
      # server lama
      docker exec redis redis-cli SAVE
      docker stop redis
      docker run --rm -v redisdata:/data:ro -v "$PWD":/backup alpine:3.20 tar czf /backup/redisdata.tgz -C /data .
      scp redisdata.tgz ops@new-server:/tmp/
      # server baru
      docker volume create redisdata
      docker run --rm -v redisdata:/data -v /tmp:/backup:ro alpine:3.20 tar xzf /backup/redisdata.tgz -C /data
      docker run -d --name redis -v redisdata:/data redis:7-alpine
      docker exec redis redis-cli DBSIZE
      ```
      Pola backup/restore ini diuji di lab: file `a.txt` (`persisted`) dari volume asli terbaca di volume hasil restore.
      - Menghentikan container memastikan tidak ada penulisan di tengah proses tar.
      - Mount `:ro` mencegah kecelakaan dari sisi backup.
      - `tar -C /data .` mempertahankan permission dan ownership di dalam arsip. Penting untuk image non-root seperti
        redis (UID 999).

      Menyalin langsung `/var/lib/docker/volumes/...` bergantung pada detail internal Docker, tidak berlaku di Docker
      Desktop, dan berisiko saat daemon berjalan. Untuk database, utamakan tool-nya sendiri (`redis-cli --rdb`,
      `pg_dump`). Backup file-level dari volume adalah cara umum untuk data apa pun.
    tests:
      pass:
        - |
          docker stop redis
          docker run --rm -v redisdata:/data:ro -v "$PWD":/backup alpine tar czf /backup/redisdata.tgz -C /data .
          scp redisdata.tgz new:/tmp/
          docker run --rm -v redisdata:/data -v /tmp:/backup alpine tar xzf /backup/redisdata.tgz -C /data
          docker run -d --name redis -v redisdata:/data redis:7-alpine
          docker exec redis redis-cli dbsize
        - |
          docker exec redis redis-cli save
          docker stop redis
          docker run --rm --mount type=volume,src=redisdata,dst=/data,readonly -v /srv/bk:/bk alpine tar cf /bk/r.tar -C /data .
          rsync -av /srv/bk/r.tar new:/srv/bk/
          docker run --rm --mount type=volume,src=redisdata,dst=/data -v /srv/bk:/bk alpine tar xf /bk/r.tar -C /data
      fail:
        - |
          docker stop redis
          rsync -a /var/lib/docker/volumes/redisdata new:/var/lib/docker/volumes/
        - |
          docker run --rm -v redisdata:/data -v "$PWD":/backup alpine tar czf /backup/r.tgz -C /data .
          scp r.tgz new:/tmp/
        - |
          docker cp redis:/data ./data
          scp -r data new:/tmp/
    source: https://docs.docker.com/engine/storage/volumes/#back-up-restore-or-migrate-data-volumes

---

# Storage — Volume, Bind Mount & tmpfs

Container dirancang untuk bisa dibuang: dibuat, dihapus, lalu dibuat ulang dari image. Data yang harus bertahan
(database, upload, state) perlu disimpan **di luar** writable layer container. Modul ini membahas tiga jenis mount,
kapan memakai masing-masing, jebakan permission dan inisialisasi, cara backup/restore, dan hal-hal yang khusus terjadi
di Docker Desktop Windows.

> [!NOTE]
> Diuji di Docker Desktop (Windows) dengan Docker Engine 29.4.1. Di Docker Desktop, volume berada di dalam VM Linux,
> sedangkan bind mount menghubungkan folder Windows ke VM itu. Ini memengaruhi performa dan perilaku path (bagian 7).
> Perilaku volume sama dengan Docker Engine di Linux, kecuali yang ditandai.

## 1. Tiga jenis mount (plus writable layer)

```mermaid
flowchart TB
    subgraph Container
        WL[writable layer<br/>hilang saat container dihapus]
        V["/var/lib/postgresql/data"]
        B["/etc/app"]
        T["/tmp"]
    end
    V --- NV[(named volume<br/>dikelola Docker)]
    B --- HF[folder host<br/>bind mount]
    T --- RAM[RAM<br/>tmpfs]
```

| | Named volume | Bind mount | tmpfs | Writable layer |
|---|---|---|---|---|
| Disimpan di | `/var/lib/docker/volumes/<nama>` | Path host pilihanmu | Memori | Layer container |
| Hidup sampai | `docker volume rm` | Folder host dihapus | Container berhenti | Container dihapus |
| Isi awal | Disalin dari image bila kosong | Isi folder host (menutupi image) | Kosong | Isi image |
| Lab setelah restart | tetap | tetap | **hilang** | tetap |
| Cocok untuk | Database, upload, data aplikasi | Dev (kode), config dari host | Cache, secret sementara | Tidak untuk data penting |

## 2. Sintaks `-v` vs `--mount`

```bash
docker run -v pgdata:/var/lib/postgresql/data postgres:17-alpine                       # named volume
docker run --mount type=volume,src=pgdata,dst=/var/lib/postgresql/data postgres:17-alpine
docker run -v ./config:/etc/app:ro alpine                                              # bind, read-only
docker run --mount type=bind,src="$PWD/config",dst=/etc/app,readonly alpine
docker run --tmpfs /tmp:size=64m alpine
docker run --mount type=tmpfs,dst=/tmp,tmpfs-size=67108864 alpine
docker run --read-only --tmpfs /tmp alpine                                             # rootfs read-only
```

| Sumber di `-v` | Dianggap |
|---|---|
| `pgdata` | **Named volume** — dibuat bila belum ada |
| `./config`, `/srv/config`, `C:\data` | Bind mount |
| (kosong) `-v /data` | **Anonymous volume** |

> [!WARNING]
> Typo pada sumber tidak menghasilkan error. `-v config:/etc/app` (lupa `./`) diam-diam membuat volume kosong bernama
> `config`. Path bind yang salah ketik juga diam-diam membuat folder kosong baru: di lab, Docker Desktop membuat folder
> untuk `-v` **maupun** `--mount`. Hasilnya, aplikasi berjalan dengan config default dan kamu mengira config-mu terpakai.
> Selalu cek dengan `docker inspect -f '{{json .Mounts}}' <container>`.

## 3. Pengisian volume dari image, dan bind mount yang menutupi

- **Volume kosong** di-mount ke path yang berisi file di image → isi image **disalin** ke volume, beserta ownership.
  Lab: `50x.html index.html`.
- **Bind mount** → isi image di path itu **tertutup**. Lab: folder kosong → `0` file.
- Penyalinan hanya terjadi saat volume kosong. Volume yang sudah berisi tidak menerima file baru dari image versi baru.
  Jangan simpan aset aplikasi (mis. `public/build`) di volume, karena deploy versi baru tidak akan memperbaruinya.

## 4. Permission dan UID

```dockerfile
RUN mkdir -p /data && chown 10001:10001 /data
USER 10001
```

| Situasi (lab) | Hasil |
|---|---|
| `-u 1000` + volume baru, image tanpa `/data` | `/data` milik root → `Permission denied` |
| Image menyiapkan `/data` milik 1000 | Volume baru mewarisi `1000:1000` → `write-ok` |
| Volume lama yang **kosong** + image di atas | Ikut terisi ownership 1000 |
| Volume lama yang berisi file milik root | Tidak berubah. Perlu `chown` sekali jalan |
| Bind mount folder Windows (Docker Desktop) | Terlihat `-rwxrwxrwx 0 0`, user 1000 tetap bisa menulis |

Di Linux, bind mount memakai UID/GID **numerik** apa adanya (modul Linux User & sudo). File yang dibuat container sebagai
UID 1000 akan dimiliki "siapa pun UID 1000 di host". Di host SELinux, tambahkan label `:z`/`:Z`.

## 5. Database di container: PostgreSQL

```bash
docker volume create pgdata
docker run -d --name db -e POSTGRES_PASSWORD=... -v pgdata:/var/lib/postgresql/data postgres:17-alpine
docker exec db pg_dump -U postgres appdb > appdb.sql          # backup logis, konsisten
```

Hasil lab:
- Tabel tetap ada setelah container dihapus dan dibuat ulang (`42`), selama volume yang sama dipakai.
- Tanpa `-v`, image membuat **anonymous volume**. `docker rm -f` meninggalkannya (data masih bisa diselamatkan),
  sedangkan `rm -f -v` menghapusnya.
- `POSTGRES_PASSWORD` baru diabaikan pada volume yang sudah berisi: `Skipping initialization`.

> [!DANGER]
> **PostgreSQL 18 mengubah lokasi data.** Di lab, `postgres:18-alpine` punya `VOLUME /var/lib/postgresql` dan
> `PGDATA=/var/lib/postgresql/18/docker`, sedangkan versi 17: `/var/lib/postgresql/data`. Mengganti tag 17 → 18 dengan mount
> lama membuat container berhenti: `Error: in 18+, these Docker images are configured to store database data in a format
> which is compatible with "pg_ctlcluster"... there appears to be PostgreSQL data in: /var/lib/postgresql/data`. Upgrade
> mayor PostgreSQL selalu butuh migrasi (dump/restore atau `pg_upgrade`). Untuk 18+, mount volume ke `/var/lib/postgresql`.
> Sumber: [docker-library/postgres#1259](https://github.com/docker-library/postgres/pull/1259)

## 6. Backup, restore, migrasi

```bash
# backup (container sementara + tar)
docker run --rm -v pgdata:/data:ro -v "$PWD":/backup alpine:3.20 tar czf /backup/pgdata.tgz -C /data .
# restore ke volume baru
docker volume create pgdata-restored
docker run --rm -v pgdata-restored:/data -v "$PWD":/backup:ro alpine:3.20 tar xzf /backup/pgdata.tgz -C /data
```

- Hentikan service yang menulis (atau pakai tool database) supaya salinannya konsisten.
- Volume **bukan** backup: disk atau server yang rusak ikut membawa volumenya. Kirim hasil backup ke luar server,
  dan uji restore secara berkala.
- Jangan menyalin `/var/lib/docker/volumes` langsung. Itu detail internal Docker, dan di Docker Desktop path itu ada di
  dalam VM.

## 7. Performa: bind mount di Docker Desktop Windows

| Lab: 3.000 file kecil (buat, baca, hapus) | Waktu |
|---|---|
| Bind mount dari folder Windows (`C:\...`) | **19.347 ms** |
| Named volume | **639 ms** |

> [!INSIGHT]
> Di Docker Desktop, setiap akses ke bind mount dari folder Windows melewati batas filesystem ke VM Linux. Untuk beban
> yang menyentuh banyak file kecil (`vendor/`, `node_modules/`, cache Laravel), perbedaannya sekitar 30× di lab. Pilihannya:
> - Taruh source di filesystem WSL 2 (`\\wsl$\...`), seperti yang disarankan dokumentasi Docker.
> - Bind mount hanya kode, sedangkan `vendor`/`node_modules` disimpan di named volume.
> - Di production Linux, masalah ini tidak ada, karena bind mount di sana adalah mount biasa.
>
> Sumber: [Docker Desktop — WSL 2 best practices](https://docs.docker.com/desktop/features/wsl/best-practices/)

## 8. Mengelola volume

```bash
docker volume ls --filter dangling=true          # tidak dipakai container mana pun
docker volume inspect pgdata
docker ps -a --filter volume=pgdata              # siapa yang memakai
docker system df -v                              # ukuran per volume
docker volume rm pgdata                          # PERMANEN
```

> [!WARNING]
> `docker volume prune` (dan `docker system prune --volumes`) menghapus **semua** volume yang tidak terpasang ke container,
> termasuk data database yang containernya sedang dihapus untuk di-upgrade, dan anonymous volume yang masih ingin kamu
> selamatkan (studi kasus C1). Di server, beri label pada volume penting dan jangan pernah prune volume tanpa filter.

## 9. Ringkasan kebiasaan aman

| Kebiasaan | Kenapa |
|---|---|
| Named volume untuk data, beri nama yang jelas | Anonymous volume mudah hilang atau terlupa |
| Kode dan aset di image, bukan volume | Volume tidak menerima update dari image baru |
| Config dari host: bind mount `:ro` | Container tidak bisa mengubah config |
| `--read-only` + tmpfs/volume untuk path yang perlu ditulis | Hardening murah |
| Siapkan ownership direktori data di Dockerfile | Volume baru mewarisi ownership, tanpa 777 |
| Cek `.Mounts` setelah deploy | Typo path = folder/volume kosong baru, tanpa error |
| Backup logis (`pg_dump`) + uji restore, simpan di luar server | Volume bukan backup |
| Baca catatan rilis image database sebelum upgrade mayor | PostgreSQL 18 mengubah path data |
| Di Windows: named volume / WSL untuk file kecil yang banyak | 19 s vs 0,6 s di lab |
| Jangan prune volume tanpa filter | Data hilang permanen |
