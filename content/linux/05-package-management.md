---
format: 1
id: linux-package-management
course:
  slug: linux
  title: Linux
  description: Fondasi Linux untuk DevOps/DevSecOps — dari filesystem sampai hardening.
title: Package Management (apt & dpkg)
summary: Memasang, memperbarui, menahan, dan menelusuri paket di Ubuntu/Debian; repository bertanda tangan, apt di Dockerfile, patch keamanan, dan pemulihan dpkg yang macet.
order: 5
version: 1
status: verified
updated: '2026-11-20'
tested_on:
  - Ubuntu 24.04.5 LTS (container Docker) — apt 2.8.4, dpkg 1.22.6
  - Rocky Linux 9 (container Docker) — dnf/rpm, untuk tabel padanan
  - Docker Engine 29.4.1 — perbandingan ukuran image
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: apt-basics
    text: Memakai apt untuk update index, install, upgrade, remove, purge, autoremove
  - id: query
    text: Menelusuri paket dengan dpkg dan apt-cache (milik siapa file ini, versi mana yang terpasang)
  - id: versions
    text: Memasang versi tertentu dan menahan paket (hold)
  - id: repos
    text: Menambah repository pihak ketiga dengan key yang di-scope (signed-by)
  - id: docker
    text: Menulis instruksi apt yang benar di Dockerfile
  - id: security-updates
    text: Menerapkan patch keamanan dan me-restart proses yang masih memakai library lama
  - id: troubleshoot
    text: Memulihkan lock, dpkg yang terputus, dan dependency yang rusak
  - id: rhel
    text: Mengenali padanan dnf/rpm di keluarga RHEL
sources:
  - title: apt(8)
    url: https://manpages.ubuntu.com/manpages/noble/man8/apt.8.html
  - title: apt-get(8)
    url: https://manpages.ubuntu.com/manpages/noble/man8/apt-get.8.html
  - title: apt-cache(8)
    url: https://manpages.ubuntu.com/manpages/noble/man8/apt-cache.8.html
  - title: apt-mark(8)
    url: https://manpages.ubuntu.com/manpages/noble/man8/apt-mark.8.html
  - title: dpkg(1)
    url: https://manpages.ubuntu.com/manpages/noble/man1/dpkg.1.html
  - title: sources.list(5) — format deb822 & Signed-By
    url: https://manpages.ubuntu.com/manpages/noble/man5/sources.list.5.html
  - title: apt-key(8) — deprecated
    url: https://manpages.ubuntu.com/manpages/noble/man8/apt-key.8.html
  - title: Ubuntu Server — Package management
    url: https://documentation.ubuntu.com/server/how-to/software/package-management/
  - title: Ubuntu Server — Automatic updates
    url: https://documentation.ubuntu.com/server/how-to/software/automatic-updates/
  - title: Docker Docs — Install Docker Engine on Ubuntu
    url: https://docs.docker.com/engine/install/ubuntu/
  - title: Docker Docs — Building best practices (apt-get)
    url: https://docs.docker.com/build/building/best-practices/#apt-get
  - title: Debian Wiki — DebianRepository/UseThirdParty
    url: https://wiki.debian.org/DebianRepository/UseThirdParty
  - title: dnf(8)
    url: https://dnf.readthedocs.io/en/latest/command_ref.html
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: command
    objective: apt-basics
    prompt: Perbarui **daftar paket** dari semua repository (belum memasang update apa pun).
    answers: ['apt update']
    patterns: ['^apt(-get)? update( -q+)?$']
    tests:
      pass: ['apt-get update', 'sudo apt update']
      fail: ['apt upgrade', 'apt-get upgrade', 'apt refresh']
    explanation: '`update` hanya mengunduh index (`/var/lib/apt/lists`, di lab 4 KB → 53 MB). Di container baru index masih kosong, sehingga `apt-get install curl` langsung gagal: `E: Unable to locate package curl`.'
    source: https://manpages.ubuntu.com/manpages/noble/man8/apt-get.8.html

  - id: a02
    tier: A
    type: command
    objective: apt-basics
    prompt: Pasang `nginx` dengan apt-get **tanpa prompt konfirmasi** (cocok untuk skrip).
    answers: ['apt-get install -y nginx']
    patterns: ['^apt(-get)? install (-y|--yes|--assume-yes) nginx$', '^apt(-get)? install nginx (-y|--yes)$', '^apt(-get)? (-y|--yes) install nginx$']
    tests:
      pass: ['apt-get -y install nginx', 'apt install nginx -y', 'apt-get install --yes nginx']
      fail: ['apt-get install nginx', 'dpkg -i nginx', 'apt-get get nginx']
    explanation: '`-y` menjawab "ya" otomatis. Di skrip/Dockerfile tambahkan juga `DEBIAN_FRONTEND=noninteractive`, supaya paket seperti `tzdata` tidak berhenti menunggu input.'
    source: https://manpages.ubuntu.com/manpages/noble/man8/apt-get.8.html

  - id: a03
    tier: A
    type: command
    objective: apt-basics
    prompt: Pasang semua update untuk paket yang sudah terpasang, **tanpa** menghapus paket apa pun.
    answers: ['apt upgrade']
    patterns: ['^apt(-get)? upgrade( -y)?$', '^apt(-get)? -y upgrade$']
    tests:
      pass: ['apt-get upgrade -y', 'apt upgrade']
      fail: ['apt update', 'apt-get dist-upgrade', 'apt full-upgrade']
    explanation: '`upgrade` tidak pernah menghapus paket. `full-upgrade`/`dist-upgrade` boleh menghapus atau memasang paket baru demi menyelesaikan dependency — biasanya untuk kernel baru atau upgrade rilis. Selalu jalankan `update` lebih dulu.'
    source: https://manpages.ubuntu.com/manpages/noble/man8/apt.8.html

  - id: a04
    tier: A
    type: command
    objective: apt-basics
    prompt: Hapus `nginx` **beserta file konfigurasinya** di `/etc`.
    answers: ['apt purge nginx']
    patterns: ['^apt(-get)? (purge|remove --purge) (-y )?nginx( -y)?$']
    tests:
      pass: ['apt-get purge -y nginx', 'apt-get remove --purge nginx']
      fail: ['apt remove nginx', 'dpkg -r nginx', 'apt autoremove nginx']
    explanation: 'Di lab, setelah `remove cron`, `dpkg -l` menampilkan status `rc` (removed, config masih ada) dan `/etc/default/cron` tetap ada. Setelah `purge`, file itu hilang dan statusnya `un`. Hati-hati: file milik paket **lain** tetap ada — `/etc/crontab` ternyata milik `cron-daemon-common`.'
    source: https://manpages.ubuntu.com/manpages/noble/man8/apt-get.8.html

  - id: a05
    tier: A
    type: command
    objective: apt-basics
    prompt: Hapus dependency yang dulu terpasang otomatis tetapi sekarang **tidak dibutuhkan** paket mana pun.
    answers: ['apt autoremove']
    patterns: ['^apt(-get)? autoremove( -y| --purge)*$']
    tests:
      pass: ['apt-get autoremove -y', 'apt autoremove --purge']
      fail: ['apt autoclean', 'apt clean', 'apt remove']
    explanation: 'apt mencatat paket "auto" (ditarik sebagai dependency) dan "manual". Di lab, setelah `cron` dihapus, `autoremove` menawarkan `cron-daemon-common`. Periksa daftarnya sebelum menyetujui — kadang ada paket yang ternyata kamu pakai langsung (tandai dengan `apt-mark manual`).'
    source: https://manpages.ubuntu.com/manpages/noble/man8/apt-mark.8.html

  - id: a06
    tier: A
    type: command
    objective: query
    prompt: File `/usr/bin/curl` berasal dari paket apa? Cari dengan dpkg.
    answers: ['dpkg -S /usr/bin/curl']
    patterns: ['^dpkg(-query)? (-S|--search) /usr/bin/curl$']
    tests:
      pass: ['dpkg-query -S /usr/bin/curl', 'dpkg --search /usr/bin/curl']
      fail: ['dpkg -L curl', 'which curl', 'apt show curl']
    explanation: 'Di lab: `curl: /usr/bin/curl`. Hanya untuk paket yang **terpasang**. Untuk file dari paket yang belum terpasang, pakai `apt-file search`. Ubuntu 24.04: `dpkg -S /bin/ls` gagal (`no path found`), karena paket terdaftar dengan path `/usr/bin/ls`.'
    source: https://manpages.ubuntu.com/manpages/noble/man1/dpkg.1.html

  - id: a07
    tier: A
    type: command
    objective: query
    prompt: Tampilkan semua file yang dipasang oleh paket `curl`.
    answers: ['dpkg -L curl']
    patterns: ['^dpkg(-query)? (-L|--listfiles) curl$']
    tests:
      pass: ['dpkg-query -L curl', 'dpkg --listfiles curl']
      fail: ['dpkg -S curl', 'apt show curl', 'dpkg -l curl']
    explanation: '`-L` (list files) kebalikan dari `-S` (search). Huruf besar/kecil penting: `-l` kecil adalah *list packages*.'
    source: https://manpages.ubuntu.com/manpages/noble/man1/dpkg.1.html

  - id: a08
    tier: A
    type: command
    objective: query
    prompt: Tampilkan detail paket `nginx` dari repository (versi, dependency, ukuran, deskripsi) — belum tentu terpasang.
    answers: ['apt show nginx']
    patterns: ['^(apt|apt-cache) show nginx$']
    tests:
      pass: ['apt-cache show nginx']
      fail: ['apt search nginx', 'dpkg -L nginx', 'apt policy nginx', 'dpkg -s nginx']
    explanation: '`dpkg -s` hanya untuk paket terpasang. `apt show` membaca index repository.'
    source: https://manpages.ubuntu.com/manpages/noble/man8/apt-cache.8.html

  - id: a09
    tier: A
    type: command
    objective: query
    prompt: Cari paket yang nama atau deskripsinya mengandung kata `nginx`.
    answers: ['apt search nginx']
    patterns: ['^(apt|apt-cache) search nginx$']
    tests:
      pass: ['apt-cache search nginx']
      fail: ['apt show nginx', 'apt find nginx', 'dpkg -S nginx']
    explanation: 'Hasilnya bisa panjang. `apt-cache search --names-only ''^nginx''` membatasi pencarian ke nama paket.'
    source: https://manpages.ubuntu.com/manpages/noble/man8/apt-cache.8.html

  - id: a10
    tier: A
    type: command
    objective: troubleshoot
    prompt: Pasang file lokal `app.deb` di direktori saat ini **sekaligus** menarik dependency-nya dari repository.
    answers: ['apt install ./app.deb']
    patterns: ['^apt(-get)? install (-y )?\./app\.deb( -y)?$']
    tests:
      pass: ['apt-get install -y ./app.deb', 'apt install ./app.deb -y']
      fail: ['dpkg -i app.deb', 'apt install app.deb', 'apt-get install app']
    explanation: '`./` wajib. Di lab, tanpa `./` apt mengira itu nama paket: `Couldn''t find any package by regex ''app.deb''`. `dpkg -i` **tidak** mengambil dependency. Di lab paket jadi `iU` (unconfigured) dengan `dependency problems`.'
    source: https://manpages.ubuntu.com/manpages/noble/man8/apt.8.html

  - id: a11
    tier: A
    type: command
    objective: security-updates
    prompt: Tampilkan daftar paket terpasang yang punya **versi lebih baru** di repository.
    answers: ['apt list --upgradable']
    patterns: ['^apt list (--upgradable|--upgradeable)$']
    tests:
      fail: ['apt upgrade', 'apt update', 'apt list --installed']
    explanation: 'Jalankan setelah `apt update`. Di lab, image `ubuntu:24.04` yang baru saja di-pull sudah punya update `libc6` dan `perl-base` — image dasar tidak otomatis ter-patch.'
    source: https://manpages.ubuntu.com/manpages/noble/man8/apt.8.html

  - id: a12
    tier: A
    type: text
    objective: query
    prompt: Di output `dpkg -l`, dua huruf status apa yang menandai paket **terpasang normal**?
    answers: ['ii']
    tests:
      fail: ['rc', 'un', 'iu', 'hi']
    explanation: 'Huruf 1 = keinginan (`i` install, `r` remove, `p` purge, `h` hold), huruf 2 = status (`i` installed, `c` config-files, `U` unpacked, `F` half-configured, `n` not-installed). `rc` = sudah di-remove, config masih ada.'
    source: https://manpages.ubuntu.com/manpages/noble/man1/dpkg-query.1.html

  - id: a13
    tier: A
    type: text
    objective: repos
    prompt: Di direktori mana definisi repository tambahan disimpan (file `.sources` / `.list`)?
    answers: ['/etc/apt/sources.list.d', '/etc/apt/sources.list.d/']
    tests:
      fail: ['/etc/apt', '/var/lib/apt/lists', '/etc/apt/keyrings', '/etc/apt/sources.list']
    explanation: 'Ubuntu 24.04 memakai format deb822: repository bawaan ada di `/etc/apt/sources.list.d/ubuntu.sources`, dan `sources.list` lama berisi komentar saja. Satu file per repository memudahkan otomatisasi (Ansible, cloud-init) dan penghapusan.'
    source: https://manpages.ubuntu.com/manpages/noble/man5/sources.list.5.html

  - id: a14
    tier: A
    type: command
    objective: versions
    prompt: Tampilkan versi `nginx` yang **terpasang**, versi **kandidat**, dan dari repository mana masing-masing versi berasal.
    answers: ['apt-cache policy nginx']
    patterns: ['^apt(-cache)? policy nginx$']
    tests:
      pass: ['apt policy nginx']
      fail: ['apt show nginx', 'dpkg -s nginx', 'apt list nginx']
    explanation: 'Di lab, `apt-cache policy curl` menampilkan `Installed`, `Candidate`, dan tabel versi dengan prioritas (`500 http://archive.ubuntu.com/ubuntu noble-updates/main`). Ini alat utama untuk menjawab "kenapa versi ini yang terpasang?" dan memeriksa pinning.'
    source: https://manpages.ubuntu.com/manpages/noble/man8/apt-cache.8.html

  - id: a15
    tier: A
    type: command
    objective: query
    prompt: Tampilkan status dan versi paket `curl` yang **terpasang** dengan dpkg.
    answers: ['dpkg -s curl']
    patterns: ['^dpkg(-query)? (-s|--status) curl$']
    tests:
      pass: ['dpkg-query -s curl', 'dpkg --status curl']
      fail: ['dpkg -S curl', 'dpkg -L curl', 'apt show curl']
    explanation: 'Di lab: `Status: install ok installed`, `Version: 8.5.0-2ubuntu10.16`. Untuk skrip: `dpkg-query -W -f=''${Version}'' curl`.'
    source: https://manpages.ubuntu.com/manpages/noble/man1/dpkg-query.1.html

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: command
    objective: versions
    prompt: Tahan `postgresql-16` agar **tidak ikut ter-upgrade** oleh `apt upgrade`.
    answers: ['apt-mark hold postgresql-16']
    patterns: ['^apt-mark hold postgresql-16$']
    tests:
      fail: ['apt hold postgresql-16', 'apt-mark manual postgresql-16', 'apt-get remove postgresql-16']
    explanation: 'Di lab, paket yang di-hold muncul di bagian `The following packages have been kept back`. Lepaskan dengan `apt-mark unhold`. Hold bukan pengganti patch: catat alasannya dan tinjau secara berkala.'
    source: https://manpages.ubuntu.com/manpages/noble/man8/apt-mark.8.html

  - id: b02
    tier: B
    type: command
    objective: versions
    prompt: Tampilkan semua paket yang sedang di-**hold**.
    answers: ['apt-mark showhold']
    patterns: ['^apt-mark showhold$']
    tests:
      fail: ['apt-mark hold', 'dpkg -l', 'apt list --upgradable']
    explanation: 'Penting saat audit: hold yang terlupakan membuat server tertinggal patch keamanan tanpa ada yang sadar.'
    source: https://manpages.ubuntu.com/manpages/noble/man8/apt-mark.8.html

  - id: b03
    tier: B
    type: command
    objective: versions
    prompt: Pasang `nginx` versi **persis** `1.24.0-2ubuntu7` dengan apt-get.
    answers: ['apt-get install nginx=1.24.0-2ubuntu7']
    patterns: ['^apt(-get)? install (-y |--allow-downgrades )*nginx=1\.24\.0-2ubuntu7( -y| --allow-downgrades)*$']
    tests:
      pass: ['apt-get install -y nginx=1.24.0-2ubuntu7', 'apt install nginx=1.24.0-2ubuntu7']
      fail: ['apt-get install nginx 1.24.0-2ubuntu7', 'apt-get install nginx==1.24.0-2ubuntu7', 'apt-get install nginx-1.24.0']
    explanation: 'Syntax `paket=versi`, dengan versi lengkap seperti di `apt-cache madison nginx`. Untuk turun versi butuh `--allow-downgrades`. Di lab, `tzdata=2024a-2ubuntu1` berhasil. Paket yang dependency-nya mengunci versi yang sama (mis. `curl` ↔ `libcurl4t64`) harus diturunkan bersama-sama.'
    source: https://manpages.ubuntu.com/manpages/noble/man8/apt-get.8.html

  - id: b04
    tier: B
    type: command
    objective: docker
    prompt: Pasang `git` tanpa prompt dan **tanpa** paket *Recommends* (untuk image container yang ramping).
    answers: ['apt-get install -y --no-install-recommends git']
    patterns: ['^apt(-get)? install (?=.*--no-install-recommends)(?=.*(-y|--yes|--assume-yes) )((-y|--yes|--assume-yes|--no-install-recommends|-qq?) )+git$']
    tests:
      pass: ['apt-get install --no-install-recommends -y git', 'apt-get install -qq -y --no-install-recommends git']
      fail: ['apt-get install -y git', 'apt-get install --no-install-recommends git', 'apt-get install -y --no-install-suggests git']
    explanation: 'Di lab (simulasi): `git` biasa menarik **47** paket, dengan `--no-install-recommends` hanya **24**. Recommends adalah paket yang "biasanya berguna" tapi tidak wajib. Kalau ternyata dibutuhkan (mis. `ca-certificates` untuk HTTPS), pasang secara eksplisit.'
    source: https://manpages.ubuntu.com/manpages/noble/man8/apt-get.8.html

  - id: b05
    tier: B
    type: command
    objective: docker
    prompt: |
      Tulis isi **satu** instruksi `RUN` (tanpa kata RUN) yang: memperbarui index, memasang `curl` secara
      non-interaktif tanpa Recommends, lalu menghapus index apt — semuanya dalam **layer yang sama**.
    answers: ['apt-get update && apt-get install -y --no-install-recommends curl && rm -rf /var/lib/apt/lists/*']
    patterns: ['^(RUN )?apt-get update && apt-get install (?=[^&]*--no-install-recommends)(?=[^&]*(-y|--yes) )((-y|--yes|--no-install-recommends|-qq?) )+curl && rm -rf /var/lib/apt/lists/\*$']
    tests:
      pass:
        - 'apt-get update && apt-get install --no-install-recommends -y curl && rm -rf /var/lib/apt/lists/*'
        - "apt-get update \\\n  && apt-get install -y --no-install-recommends curl \\\n  && rm -rf /var/lib/apt/lists/*"
      fail:
        - 'apt-get install -y curl && rm -rf /var/lib/apt/lists/*'
        - 'apt-get update && apt-get install -y --no-install-recommends curl'
        - 'apt update && apt install -y curl'
    explanation: 'Kalau `update` berada di RUN terpisah, layer cache-nya bisa dipakai ulang berminggu-minggu kemudian dengan index basi, sehingga install gagal 404 atau memasang versi lama. Menghapus `/var/lib/apt/lists` di RUN berikutnya tidak mengecilkan image, karena datanya sudah tersimpan di layer sebelumnya. Di lab, index yang tertinggal 53 MB.'
    source: https://docs.docker.com/build/building/best-practices/#apt-get

  - id: b06
    tier: B
    type: command
    objective: troubleshoot
    prompt: 'apt menolak bekerja: `E: dpkg was interrupted, you must manually run ''dpkg --configure -a'' to correct the problem.` Pulihkan.'
    answers: ['dpkg --configure -a']
    patterns: ['^dpkg --configure (-a|--pending)$']
    tests:
      pass: ['sudo dpkg --configure -a']
      fail: ['apt-get install -f', 'rm /var/lib/dpkg/lock-frontend', 'dpkg --configure', 'apt-get update']
    explanation: 'Direproduksi di lab dengan membunuh `dpkg` saat postinst berjalan: `dpkg --audit` melaporkan paket `half configured`, dan semua `apt-get install` menolak. `--configure -a` menjalankan ulang konfigurasi semua paket yang tertunda. Bila setelahnya masih ada dependency yang rusak, lanjutkan dengan `apt --fix-broken install`.'
    source: https://manpages.ubuntu.com/manpages/noble/man1/dpkg.1.html

  - id: b07
    tier: B
    type: command
    objective: troubleshoot
    prompt: |
      Setelah `dpkg -i app.deb`, paket berstatus `iU` dengan `dependency problems - leaving unconfigured`,
      dan apt menyarankan perbaikan otomatis. Jalankan perbaikan itu.
    answers: ['apt --fix-broken install']
    patterns: ['^apt(-get)? (--fix-broken|-f) install( -y)?$', '^apt(-get)? install (-y )?(-f|--fix-broken)( -y)?$', '^apt(-get)? -y (-f|--fix-broken) install$']
    tests:
      pass: ['apt-get -f install', 'apt-get install -f -y', 'apt --fix-broken install -y']
      fail: ['dpkg --configure -a', 'dpkg -i --force-depends app.deb', 'apt-get update']
    explanation: 'Di lab, `apt-get install -f` memasang `jq` yang dibutuhkan, lalu `needjq` menjadi `ii`. Pesan aslinya: `E: Unmet dependencies. Try ''apt --fix-broken install'' with no packages`. `--force-depends` hanya menyembunyikan masalah.'
    source: https://manpages.ubuntu.com/manpages/noble/man8/apt-get.8.html

  - id: b08
    tier: B
    type: text
    objective: troubleshoot
    prompt: '`E: Could not get lock /var/lib/dpkg/lock-frontend. It is held by process 2408 (dpkg)`. Bolehkah kamu langsung menghapus file lock itu dengan `rm`? (ya/tidak)'
    answers: ['tidak', 'no', 'jangan']
    tests:
      fail: ['ya', 'yes', 'boleh']
    explanation: 'Lock berarti ada dpkg/apt yang sedang berjalan. Di server yang baru boot, itu sering `unattended-upgrades`. Menghapus lock lalu menjalankan apt kedua bisa merusak database dpkg di tengah transaksi. Tunggu sampai selesai (`ps -fp 2408`), atau pakai `apt-get -o DPkg::Lock::Timeout=300 ...` (default di lab: 120 detik untuk binary `apt`).'
    source: https://manpages.ubuntu.com/manpages/noble/man1/dpkg.1.html

  - id: b09
    tier: B
    type: text
    objective: repos
    prompt: 'Di file repository format deb822 (`.sources`), field apa yang menautkan repository ke **key-nya sendiri**, sehingga key itu tidak dipercaya untuk repository lain?'
    answers: ['Signed-By', 'signed-by:', 'Signed-By:']
    tests:
      fail: ['Key', 'Trusted', 'GPG-Key', 'Components', 'Signed']
    explanation: 'Di lab, repo Docker tanpa key gagal dengan `NO_PUBKEY 7EA0A9C3F273FCD8` dan `is not signed`. Setelah key diunduh ke `/etc/apt/keyrings/docker.asc` dan `Signed-By:` ditambahkan, `apt-get update` berhasil. `apt-key add` ditandai deprecated (di lab: `Warning: apt-key is deprecated`), karena menaruh key ke keyring global yang dipercaya untuk **semua** repository.'
    source: https://wiki.debian.org/DebianRepository/UseThirdParty

  - id: b10
    tier: B
    type: command
    objective: query
    prompt: Tampilkan paket **terpasang** yang bergantung pada `libssl3t64` (reverse dependency), sebelum kamu mengutak-atiknya.
    answers: ['apt-cache rdepends --installed libssl3t64']
    patterns: ['^apt-cache rdepends --installed libssl3t64$', '^apt-cache rdepends libssl3t64 --installed$']
    tests:
      pass: ['apt-cache rdepends libssl3t64 --installed']
      fail: ['apt-cache depends libssl3t64', 'dpkg -L libssl3t64', 'apt-cache rdepends libssl3t64']
    explanation: 'Tanpa `--installed`, daftarnya berisi ratusan paket di repository. Di lab: `libkrb5-3`, `openssl`. `depends` adalah kebalikannya — apa yang dibutuhkan paket itu.'
    source: https://manpages.ubuntu.com/manpages/noble/man8/apt-cache.8.html

  - id: b11
    tier: B
    type: command
    objective: security-updates
    prompt: '**Simulasikan** `apt-get upgrade` — tampilkan apa yang akan dipasang/ditahan tanpa mengubah apa pun.'
    answers: ['apt-get upgrade -s']
    patterns: ['^apt(-get)? (-s|--simulate|--dry-run|--just-print|--no-act) upgrade$', '^apt(-get)? upgrade (-s|--simulate|--dry-run|--just-print|--no-act)$']
    tests:
      pass: ['apt-get -s upgrade', 'apt-get upgrade --dry-run']
      fail: ['apt list --upgradable', 'apt-get upgrade -y', 'apt-get check']
    explanation: 'Simulasi boleh dijalankan tanpa root dan menampilkan baris `Inst`/`Conf`, plus bagian `kept back`. Bagus untuk change review sebelum maintenance window.'
    source: https://manpages.ubuntu.com/manpages/noble/man8/apt-get.8.html

  - id: b12
    tier: B
    type: text
    objective: versions
    prompt: 'Paket yang di-hold (atau yang butuh paket baru) tidak ikut `apt-get upgrade`. Dua kata apa yang dipakai output apt untuk paket-paket itu?'
    answers: ['kept back']
    patterns: ['^(the following packages (have been|were) )?kept back:?$']
    tests:
      pass: ['the following packages have been kept back']
      fail: ['held', 'upgraded', 'not upgraded', 'held back']
    explanation: 'Di lab, setelah `apt-mark hold libc-bin`: `The following packages have been kept back: libc-bin`. Penyebab lain: upgrade yang membutuhkan paket **baru**, yang hanya mau dipasang oleh `full-upgrade` atau `apt-get install paket`.'
    source: https://manpages.ubuntu.com/manpages/noble/man8/apt-get.8.html

  - id: b13
    tier: B
    type: command
    objective: security-updates
    prompt: Periksa apakah file milik paket `openssh-server` sudah **diubah** dibanding versi paketnya (berdasarkan checksum).
    answers: ['dpkg -V openssh-server']
    patterns: ['^dpkg (-V|--verify) openssh-server$', '^debsums (-c |-s )?openssh-server$']
    tests:
      pass: ['dpkg --verify openssh-server', 'debsums -c openssh-server']
      fail: ['dpkg -L openssh-server', 'dpkg -s openssh-server', 'sha256sum /usr/sbin/sshd']
    explanation: 'Di lab, setelah mengubah `/etc/ssh/ssh_config` dan `/usr/bin/scp`: `??5?????? c /etc/ssh/ssh_config` (`c` = conffile, wajar diubah) dan `??5?????? /usr/bin/scp` (binary berubah, mencurigakan). Di image Docker Ubuntu muncul banyak `missing` untuk man/doc, karena image memang membuangnya (`/etc/dpkg/dpkg.cfg.d/excludes`). Ini bukan bukti integritas mutlak, karena penyerang yang menjadi root bisa ikut memalsukan database dpkg.'
    source: https://manpages.ubuntu.com/manpages/noble/man1/dpkg.1.html

  - id: b14
    tier: B
    type: text
    objective: docker
    prompt: '`apt` mencetak `WARNING: apt does not have a stable CLI interface. Use with caution in scripts.` Perintah apa yang sebaiknya dipakai di skrip dan Dockerfile?'
    answers: ['apt-get', 'apt-get dan apt-cache', 'apt-get/apt-cache']
    patterns: ['^apt-get( ?(dan|/|and|&|,) ?apt-cache)?$']
    tests:
      fail: ['apt', 'dpkg', 'aptitude', 'snap']
    explanation: '`apt` dirancang untuk manusia (progress bar, warna, output yang bisa berubah antar versi). `apt-get`/`apt-cache` punya output dan perilaku yang stabil untuk otomatisasi. Peringatan itu muncul di stderr saat output bukan terminal (di lab, ketika `apt list` di-pipe).'
    source: https://manpages.ubuntu.com/manpages/noble/man8/apt.8.html

  - id: b15
    tier: B
    type: command
    objective: rhel
    prompt: Di Rocky/RHEL, apa padanan `dpkg -S /usr/bin/curl` (paket mana pemilik file yang terpasang)?
    answers: ['rpm -qf /usr/bin/curl']
    patterns: ['^(rpm (-qf|--query --file|-q --file)|dnf provides|yum provides|yum whatprovides) /usr/bin/curl$']
    tests:
      pass: ['dnf provides /usr/bin/curl', 'yum whatprovides /usr/bin/curl']
      fail: ['rpm -ql curl', 'dnf info curl', 'rpm -q curl']
    explanation: 'Di Rocky 9 (lab): `curl-minimal-7.76.1-43.el9.x86_64`, sedangkan `rpm -q curl` → `package curl is not installed`. Nama paket bisa berbeda dari nama binary-nya. `dnf provides` juga bisa mencari paket yang belum terpasang.'
    source: https://dnf.readthedocs.io/en/latest/command_ref.html

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: troubleshoot
    prompt: |
      Server baru saja di-boot. Skrip provisioning gagal memasang nginx. Rekan setim menyarankan
      "hapus saja lock-nya lalu `kill -9` prosesnya". Tulis langkah yang benar (satu perintah per baris):
      identifikasi pemegang lock, tunggu dengan aman, pulihkan bila dpkg sempat terputus, lalu pasang nginx.
    context: |
      $ sudo apt-get install -y nginx
      E: Could not get lock /var/lib/dpkg/lock-frontend. It is held by process 1123 (unattended-upgr)
      E: Unable to acquire the dpkg frontend lock (/var/lib/dpkg/lock-frontend), is another process using it?
      $ uptime
       09:02:11 up 3 min,  1 user,  load average: 1.10, 0.52, 0.20
    checklist:
      - id: identify
        label: Mengidentifikasi pemegang lock (ps -fp 1123, fuser/lsof lock, status unattended-upgrades)
        patterns: ['ps\s+.*(1123|unattended)', 'pgrep\s+.*(unattended|apt|dpkg)', 'lsof\s+.*/var/lib/dpkg/lock', 'fuser\s+(-\S+\s+)*/var/lib/dpkg/lock', 'systemctl\s+status\s+\S*(apt-daily|unattended)']
      - id: wait
        label: Menunggu dengan aman (loop + sleep, DPkg::Lock::Timeout, atau stop unattended-upgrades dengan benar)
        patterns: ['while\s+.*(fuser|pgrep|lsof|kill\s+-0)', 'dpkg::lock::timeout', 'systemctl\s+stop\s+unattended-upgrades', '^sleep\s+\d+']
      - id: configure
        label: Memulihkan dpkg yang terputus (dpkg --configure -a)
        required: true
        patterns: ['dpkg\s+--configure\s+(-a|--pending)']
      - id: fix
        label: Memperbaiki dependency bila perlu (apt --fix-broken install)
        patterns: ['apt(-get)?\s+(-f|--fix-broken)\s+install', 'apt(-get)?\s+install\s+(-y\s+)?(-f|--fix-broken)']
      - id: install
        label: Memasang nginx
        required: true
        patterns: ['apt(-get)?\s+(\S+\s+)*install\s+(-\S+\s+)*nginx']
      - id: no-rm-lock
        label: Tidak menghapus file lock
        forbid: true
        patterns: ['rm\s+(-\S+\s+)*\S*/var/lib/dpkg/lock', 'rm\s+(-\S+\s+)*\S*/var/lib/apt/lists/lock', 'rm\s+(-\S+\s+)*\S*/var/cache/apt/archives/lock']
      - id: no-kill9
        label: Tidak kill -9 proses apt/dpkg
        forbid: true
        patterns: ['kill\s+(-9|-kill|-sigkill|-s\s+kill)\s+1123', 'pkill\s+(-9|-kill)\s+.*(dpkg|apt|unattended)', 'killall\s+(-9\s+)?(dpkg|apt|unattended)']
    solution: |
      ```bash
      ps -fp 1123                                   # unattended-upgrades: patch otomatis setelah boot
      while sudo fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1; do sleep 5; done
      sudo dpkg --configure -a                      # aman dijalankan; wajib bila pernah terputus
      sudo apt-get -o DPkg::Lock::Timeout=300 install -y nginx
      ```
      Nama proses di pesan error terpotong menjadi 15 karakter (`unattended-upgr`) — itu `unattended-upgrades`,
      yang di Ubuntu dijalankan timer `apt-daily-upgrade` tak lama setelah boot. Membunuh atau menghapus lock-nya
      bisa memutus dpkg di tengah transaksi. Di lab kondisi itu menghasilkan `half configured` dan
      `dpkg was interrupted`. Untuk provisioning, `DPkg::Lock::Timeout` membuat apt menunggu lock dengan sendirinya.
    tests:
      pass:
        - |
          ps -fp 1123
          while fuser /var/lib/dpkg/lock-frontend; do sleep 5; done
          dpkg --configure -a
          apt-get install -y nginx
        - |
          systemctl status unattended-upgrades
          sudo apt-get -o DPkg::Lock::Timeout=300 install -y nginx
          sudo dpkg --configure -a
          sudo apt --fix-broken install
      fail:
        - |
          rm /var/lib/dpkg/lock-frontend /var/lib/dpkg/lock
          apt-get install -y nginx
        - |
          kill -9 1123
          dpkg --configure -a
          apt-get install -y nginx
        - |
          ps -fp 1123
          sleep 60
          apt-get install -y nginx
    source: https://documentation.ubuntu.com/server/how-to/software/automatic-updates/

  - id: c02
    tier: C
    type: scenario
    objective: docker
    prompt: |
      Review Dockerfile di bawah: image-nya besar, dan build ulang minggu depan bisa gagal 404 atau memasang
      versi basi. Tulis ulang bagian instalasi paketnya dengan benar (baris Dockerfile apa adanya; boleh
      memakai `\` untuk sambung baris).
    context: |
      FROM ubuntu:24.04
      RUN apt-get update
      RUN apt-get install -y curl git python3
      $ docker images dojo-apt-bad --format '{{.Size}}'
      400MB
    checklist:
      - id: combine
        label: update dan install dalam RUN yang sama (update && install)
        required: true
        patterns: ['apt-get\s+update\s*(-\S+\s*)*(&&|\\$)']
      - id: no-recommends
        label: --no-install-recommends
        patterns: ['--no-install-recommends']
      - id: yes
        label: Non-interaktif (-y)
        patterns: ['apt-get\s+install\s+(.*\s)?(-y|--yes)\b']
      - id: cleanup
        label: Hapus /var/lib/apt/lists/* di RUN yang sama
        required: true
        patterns: ['rm\s+-rf\s+/var/lib/apt/lists/\*']
      - id: frontend
        label: DEBIAN_FRONTEND=noninteractive (sebaiknya ARG/inline, bukan ENV permanen)
        patterns: ['debian_frontend=noninteractive']
      - id: certs
        label: ca-certificates dipasang eksplisit (hilang saat Recommends dimatikan)
        patterns: ['ca-certificates']
      - id: no-separate-update
        label: Tidak meninggalkan RUN apt-get update sendirian
        forbid: true
        patterns: ['^run\s+apt-get\s+update\s*$']
      - id: no-unauthenticated
        label: Tidak mematikan verifikasi tanda tangan
        forbid: true
        patterns: ['trusted=yes', 'allow-unauthenticated', 'allowunauthenticated', 'allowinsecurerepositories']
    pass_score: 0.6
    solution: |
      ```dockerfile
      FROM ubuntu:24.04
      ARG DEBIAN_FRONTEND=noninteractive
      RUN apt-get update \
       && apt-get install -y --no-install-recommends ca-certificates curl git python3 \
       && rm -rf /var/lib/apt/lists/*
      ```
      Diuji di lab dengan Docker 29: versi asli **400 MB** (layer `update` 55,5 MB + layer install 146 MB),
      versi perbaikan **292 MB** (satu layer 136 MB), index apt tersisa 4 KB, dan `curl` ke HTTPS tetap jalan.
      `update` di RUN terpisah adalah masalah correctness, bukan sekadar ukuran: layer itu di-cache, sehingga
      install berikutnya memakai index lama. `ARG` (bukan `ENV`) membuat `DEBIAN_FRONTEND` hanya berlaku saat
      build, tidak terbawa ke container. Untuk build yang benar-benar reproducible, pin versi (`curl=8.5.0-...`)
      atau pin digest image dasar.
    tests:
      pass:
        - |
          FROM ubuntu:24.04
          ARG DEBIAN_FRONTEND=noninteractive
          RUN apt-get update \
           && apt-get install -y --no-install-recommends ca-certificates curl git python3 \
           && rm -rf /var/lib/apt/lists/*
        - |
          RUN apt-get update && apt-get install -y curl git python3 && rm -rf /var/lib/apt/lists/*
          # --no-install-recommends bisa ditambahkan setelah dicek
          ENV DEBIAN_FRONTEND=noninteractive
      fail:
        - |
          RUN apt-get update
          RUN apt-get install -y --no-install-recommends curl git python3 && rm -rf /var/lib/apt/lists/*
        - |
          RUN apt-get update && apt-get install -y --no-install-recommends curl git python3
        - |
          RUN echo 'APT::Get::AllowUnauthenticated "true";' > /etc/apt/apt.conf.d/99insecure
          RUN apt-get update && apt-get install -y curl && rm -rf /var/lib/apt/lists/*
    source: https://docs.docker.com/build/building/best-practices/#apt-get

  - id: c03
    tier: C
    type: scenario
    objective: security-updates
    prompt: |
      Ada advisory keamanan OpenSSL; versi yang sudah diperbaiki untuk Ubuntu 24.04 adalah `3.0.13-0ubuntu3.16`.
      Di server, nginx terus berjalan. Tulis perintah (satu per baris) untuk memeriksa versi terpasang,
      memasang update **hanya** untuk OpenSSL (tanpa `upgrade` seluruh sistem di jam kerja), menemukan proses
      yang masih memakai library lama, dan menyelesaikannya.
    context: |
      $ apt-cache policy libssl3t64 | head -3
      libssl3t64:
        Installed: 3.0.13-0ubuntu3
        Candidate: 3.0.13-0ubuntu3.16
    checklist:
      - id: check
        label: Memeriksa versi terpasang/kandidat (apt-cache policy, dpkg -s, apt list)
        required: true
        patterns: ['apt(-cache)?\s+policy\s+\S*(libssl|openssl)', 'dpkg(-query)?\s+(-s|-l|-W)\s+.*(libssl|openssl)', 'apt\s+list\s+.*(libssl|openssl)']
      - id: refresh
        label: Memperbarui index (apt-get update)
        patterns: ['apt(-get)?\s+update']
      - id: targeted
        label: Upgrade terarah (install --only-upgrade libssl3t64 openssl)
        required: true
        patterns: ['apt(-get)?\s+install\s+(-\S+\s+)*--only-upgrade\s+.*(libssl|openssl)', 'apt(-get)?\s+install\s+(-y\s+)?(libssl3t64|openssl)\b']
      - id: find-stale
        label: Menemukan proses yang memakai library lama (needrestart, lsof DEL, /proc/*/maps)
        patterns: ['needrestart', 'lsof\s+.*(del|deleted|libssl)', 'grep\s+.*libssl.*/proc/', 'grep\s+.*deleted.*/proc/']
      - id: restart
        label: Me-restart service terkait (nginx)
        patterns: ['systemctl\s+(restart|reload)\s+\S*nginx', 'needrestart\s+-r\s+a']
      - id: no-hold
        label: Tidak menahan OpenSSL di versi rentan
        forbid: true
        patterns: ['apt-mark\s+hold\s+\S*(libssl|openssl)']
      - id: no-force
        label: Tidak memaksa dpkg atau memakai repo tanpa tanda tangan
        forbid: true
        patterns: ['dpkg\s+.*--force', 'trusted=yes', 'allow-unauthenticated']
    pass_score: 0.6
    solution: |
      ```bash
      apt-get update
      apt-cache policy libssl3t64 openssl
      apt-get install -y --only-upgrade libssl3t64 openssl
      dpkg -s libssl3t64 | grep Version                  # 3.0.13-0ubuntu3.16
      lsof -n 2>/dev/null | grep -E 'DEL.*libssl'         # proses yang masih memetakan lib lama
      systemctl restart nginx                             # atau: needrestart -r l (daftar) / -r a (otomatis)
      ```
      Diuji di lab: `libssl3t64` diturunkan ke `3.0.13-0ubuntu3`, proses `openssl` dijalankan, lalu
      `--only-upgrade` menaikkannya ke `3.0.13-0ubuntu3.16`. Proses lama **tetap** memakai library versi lama:
      `/proc/PID/maps` menunjukkan `/usr/lib/x86_64-linux-gnu/libssl.so.3 (deleted)` dan `lsof` menandainya `DEL`.
      Jadi patch library belum efektif sampai prosesnya di-restart. `--only-upgrade` tidak memasang paket yang
      belum ada (lab: `Skipping jq, it is not installed and only upgrades are requested`). Di Ubuntu Server,
      `needrestart` membantu menemukan service yang perlu di-restart setelah upgrade.
    tests:
      pass:
        - |
          apt-get update
          apt-cache policy libssl3t64
          apt-get install -y --only-upgrade libssl3t64 openssl
          lsof -n | grep DEL | grep libssl
          systemctl restart nginx
        - |
          dpkg -s libssl3t64
          apt install -y libssl3t64 openssl
          needrestart -r a
      fail:
        - apt-get upgrade -y
        - |
          apt-cache policy libssl3t64
          apt-mark hold libssl3t64
        - |
          apt-get install -y --only-upgrade libssl3t64
          systemctl restart nginx
    source: https://documentation.ubuntu.com/server/how-to/software/package-management/

---

# Package Management (apt & dpkg)

Software di server Ubuntu/Debian dipasang, diperbarui, dan di-patch lewat **paket**. Untuk DevOps, paket
menyentuh banyak hal sekaligus: image Docker yang ramping, provisioning yang tidak macet, patch keamanan
yang benar-benar efektif, dan repository pihak ketiga yang tidak membuka celah supply chain. Modul ini
fokus pada apt/dpkg (Ubuntu 24.04), dengan tabel padanan dnf/rpm di akhir.

> [!NOTE]
> Semua output dan perilaku diuji ulang di container `ubuntu:24.04` (apt 2.8.4, dpkg 1.22.6) dan `rockylinux:9`.
> Image Docker Ubuntu adalah versi *minimal*: index apt kosong, dan man page/dokumentasi sengaja tidak
> dipasang. Server penuh berperilaku sedikit berbeda di dua hal itu.

## 1. Lapisan: dpkg vs apt

```mermaid
flowchart LR
    R[(Repository<br/>archive.ubuntu.com)] -- apt update --> I[Index lokal<br/>/var/lib/apt/lists]
    I -- apt install --> D[Unduh .deb<br/>/var/cache/apt/archives]
    D --> K[dpkg<br/>pasang + jalankan postinst]
    K --> DB[(Database<br/>/var/lib/dpkg/status)]
```

| Tool | Tugas | Mengurus dependency? |
|---|---|---|
| `dpkg` | Memasang/menghapus **file .deb** dan menyimpan database paket | Tidak — hanya mengecek |
| `apt-get`, `apt-cache` | Mengambil dari repository, menyelesaikan dependency, memanggil dpkg | Ya — stabil untuk skrip |
| `apt` | Antarmuka yang sama untuk manusia (progress bar, warna) | Ya — CLI tidak dijamin stabil |

## 2. Operasi harian

```bash
apt update                         # perbarui index (bukan paket!)
apt list --upgradable
apt upgrade                        # update paket, tidak menghapus apa pun
apt full-upgrade                   # boleh menambah/menghapus paket (kernel, rilis)
apt install -y nginx
apt install ./app.deb              # .deb lokal + dependency (./ wajib)
apt remove nginx                   # status rc: config di /etc tetap ada
apt purge nginx                    # termasuk config
apt autoremove                     # dependency yang sudah tidak dipakai
apt-get clean                      # kosongkan /var/cache/apt/archives
```

| Status `dpkg -l` | Arti |
|---|---|
| `ii` | Terpasang normal |
| `rc` | Sudah di-remove, file config masih ada |
| `iU` | Di-unpack tapi belum dikonfigurasi (dependency kurang) |
| `iF` | Konfigurasi gagal/terputus |
| `hi` | Terpasang dan di-hold |

## 3. Menelusuri paket

| Pertanyaan | Perintah | Contoh hasil di lab |
|---|---|---|
| File ini milik paket apa? | `dpkg -S /usr/bin/curl` | `curl: /usr/bin/curl` |
| Paket ini memasang file apa? | `dpkg -L curl` | `/usr/bin/curl`, ... |
| Versi berapa yang terpasang? | `dpkg -s curl` | `Version: 8.5.0-2ubuntu10.16` |
| Versi mana yang akan dipasang, dari repo mana? | `apt-cache policy curl` | `Candidate: ... 500 noble-updates/main` |
| Versi apa saja yang tersedia? | `apt list -a curl` / `apt-cache madison curl` | `8.5.0-2ubuntu10.16`, `8.5.0-2ubuntu10` |
| Siapa yang bergantung padanya? | `apt-cache rdepends --installed libssl3t64` | `libkrb5-3`, `openssl` |
| File-file paket berubah? | `dpkg -V openssh-server` | `??5?????? c /etc/ssh/ssh_config` |
| Siapa yang memasang apa, kapan? | `/var/log/apt/history.log` | `Commandline: apt-get install ...` |

> [!WARNING]
> Ubuntu 24.04 memakai *usrmerge*: `/bin` adalah symlink ke `/usr/bin`, tetapi database dpkg mencatat path aslinya.
> Di lab, `dpkg -S /bin/ls` → `no path found matching pattern`, sedangkan `dpkg -S /usr/bin/ls` → `coreutils`.
> Kalau `dpkg -S` gagal, coba `dpkg -S "$(readlink -f /bin/ls)"`.

## 4. Versi, hold, dan pinning

```bash
apt-cache madison nginx                   # versi yang tersedia
apt-get install nginx=1.24.0-2ubuntu7     # versi persis
apt-mark hold postgresql-16               # jangan ikut upgrade
apt-mark showhold
apt-mark unhold postgresql-16
apt-get upgrade -s                        # simulasi: lihat "kept back"
```

Hold cocok untuk komponen yang upgrade-nya harus terjadwal (database, driver, Kubernetes `kubelet`/`kubeadm`).
Tetapi setiap hold adalah utang patch: catat alasannya, dan periksa `apt-mark showhold` saat audit.

## 5. Repository pihak ketiga dengan aman

Cara modern (sesuai dokumentasi Docker untuk Ubuntu): key disimpan per repository dan direferensikan lewat
`Signed-By`.

```bash
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
```

```text
# /etc/apt/sources.list.d/docker.sources
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: noble
Components: stable
Signed-By: /etc/apt/keyrings/docker.asc
```

Di lab:
- Tanpa key: `NO_PUBKEY 7EA0A9C3F273FCD8`, lalu `E: The repository ... is not signed`.
- Dengan `Signed-By`: index terunduh dan `docker-ce` muncul sebagai kandidat.

> [!DANGER]
> Jangan "memperbaiki" `NO_PUBKEY` dengan `[trusted=yes]` atau `AllowUnauthenticated`. Itu mematikan verifikasi
> tanda tangan: siapa pun yang bisa memanipulasi jalur jaringan atau mirror bisa menyuntikkan paket yang
> dijalankan sebagai root. Hindari juga `apt-key add`. Key yang dimasukkan ke keyring global dipercaya untuk
> **semua** repository, sehingga pemilik key itu bisa menandatangani paket pengganti `openssl` dari repo Ubuntu.
> Di lab, `apt-key` sendiri sudah memperingatkan: `Warning: apt-key is deprecated`.
> Sumber: [Debian Wiki — UseThirdParty](https://wiki.debian.org/DebianRepository/UseThirdParty)

## 6. apt di Dockerfile

```dockerfile
FROM ubuntu:24.04
ARG DEBIAN_FRONTEND=noninteractive
RUN apt-get update \
 && apt-get install -y --no-install-recommends ca-certificates curl git python3 \
 && rm -rf /var/lib/apt/lists/*
```

| Aturan | Alasan (hasil lab) |
|---|---|
| `update` dan `install` di RUN yang sama | Layer `update` yang terpisah di-cache, sehingga install berikutnya memakai index basi |
| `--no-install-recommends` | `git`: 47 → 24 paket |
| `rm -rf /var/lib/apt/lists/*` di RUN yang sama | Index 53 MB; menghapusnya di RUN lain tidak mengecilkan image |
| `ca-certificates` eksplisit | Ikut hilang saat Recommends dimatikan, padahal dibutuhkan untuk HTTPS |
| `ARG DEBIAN_FRONTEND` | Hanya berlaku saat build, tidak terbawa ke container |
| `apt-get`, bukan `apt` | Output `apt` tidak stabil untuk skrip |

Hasil gabungannya di lab: image **400 MB → 292 MB**.

## 7. Patch keamanan yang benar-benar efektif

```bash
apt-get update
apt list --upgradable
apt-get install -y --only-upgrade libssl3t64 openssl   # terarah
lsof -n | grep DEL | grep libssl                        # proses yang masih memakai versi lama
systemctl restart nginx
```

> [!INSIGHT]
> Meng-upgrade library tidak memperbaiki proses yang sudah berjalan: proses itu tetap memetakan file lama yang
> sudah dihapus. Di lab, setelah `libssl3t64` naik dari `3.0.13-0ubuntu3` ke `3.0.13-0ubuntu3.16`, proses
> `openssl` yang berjalan masih memakai `/usr/lib/x86_64-linux-gnu/libssl.so.3 (deleted)`, dan `lsof` menandainya
> `DEL`. Patch baru berlaku setelah service di-restart. Ubuntu Server menyertakan `needrestart` untuk mendeteksi hal ini.
> Sumber: [Ubuntu Server — Package management](https://documentation.ubuntu.com/server/how-to/software/package-management/)

Untuk patch otomatis, Ubuntu memakai `unattended-upgrades`. Di lab, setelah paket itu dipasang,
`/etc/apt/apt.conf.d/20auto-upgrades` berisi `Update-Package-Lists "1"` dan `Unattended-Upgrade "1"`. Dalam
konfigurasinya, `50unattended-upgrades` mengaktifkan origin `${distro_codename}` dan
`${distro_codename}-security`. Konsekuensinya: tak lama setelah boot, apt mungkin sedang dipakai (bagian 8).

## 8. Troubleshooting

```mermaid
flowchart TD
    E[apt gagal] --> L{Could not get lock?}
    L -- ya --> W[ps -fp PID → tunggu<br/>DPkg::Lock::Timeout<br/>JANGAN rm lock]
    E --> I{dpkg was interrupted?}
    I -- ya --> C[dpkg --configure -a]
    E --> U{Unmet dependencies?}
    U -- ya --> F[apt --fix-broken install]
    E --> N{NO_PUBKEY / not signed?}
    N -- ya --> K[Key ke /etc/apt/keyrings<br/>+ Signed-By]
    E --> P{Unable to locate package?}
    P -- ya --> UP[apt-get update<br/>cek nama: apt search]
    E --> H{404 Not Found?}
    H -- ya --> UP2[Index basi → update<br/>rilis EOL → old-releases]
```

| Pesan (direproduksi di lab) | Penyebab | Tindakan |
|---|---|---|
| `Could not get lock /var/lib/dpkg/lock-frontend. It is held by process 2408 (dpkg)` | dpkg/apt lain sedang berjalan | Tunggu; cek prosesnya |
| `dpkg was interrupted, you must manually run 'dpkg --configure -a'` | dpkg terbunuh di tengah jalan | `dpkg --configure -a` |
| `dependency problems - leaving unconfigured` | `dpkg -i` tanpa dependency | `apt --fix-broken install` |
| `Unable to locate package curl` | Index kosong/basi | `apt-get update` |
| `NO_PUBKEY ...` / `is not signed` | Key repo belum dipasang | `Signed-By` + keyring |

## 9. Padanan di keluarga RHEL (dnf/rpm)

| Tujuan | Debian/Ubuntu | RHEL/Rocky/Fedora |
|---|---|---|
| Install | `apt-get install -y nginx` | `dnf install -y nginx` |
| Hapus | `apt purge nginx` | `dnf remove nginx` |
| Cek update | `apt list --upgradable` | `dnf check-update` (exit 100 bila ada) |
| File milik paket apa | `dpkg -S /usr/bin/curl` | `rpm -qf /usr/bin/curl` / `dnf provides` |
| Isi paket | `dpkg -L curl` | `rpm -ql curl` |
| Tahan versi | `apt-mark hold` | `dnf versionlock add` (plugin) |
| Riwayat | `/var/log/apt/history.log` | `dnf history` |

Di Rocky 9 (lab), `rpm -qf /usr/bin/curl` menghasilkan `curl-minimal-7.76.1-43.el9.x86_64`. Nama paket tidak selalu sama dengan nama perintahnya.

## 10. Ringkasan kebiasaan aman

| Kebiasaan | Kenapa |
|---|---|
| `apt-get`/`apt-cache` di skrip | CLI `apt` tidak stabil |
| `update` + `install` + `rm lists` dalam satu RUN | Index tidak basi, image ramping |
| Key per repo + `Signed-By`; tidak pernah `trusted=yes` | Membatasi kepercayaan, mencegah paket palsu |
| Tidak menghapus lock / `kill -9` apt | Database dpkg bisa rusak |
| `dpkg --configure -a` → `apt --fix-broken install` | Urutan pemulihan standar |
| Restart service setelah upgrade library | Proses lama tetap memakai versi rentan |
| Audit `apt-mark showhold` | Hold yang terlupakan = patch yang tertinggal |
