---
format: 1
id: linux-user-group-sudo
course:
  slug: linux
  title: Linux
  description: Fondasi Linux untuk DevOps/DevSecOps — dari filesystem sampai hardening.
title: User, Group & sudo
summary: Mengelola akun dan grup, user sistem untuk service, menonaktifkan akun dengan benar (termasuk SSH key), dan menulis aturan sudo least-privilege tanpa celah shell escape.
order: 6
version: 1
status: verified
updated: '2026-11-20'
tested_on:
  - Ubuntu 24.04.5 LTS (container Docker) — shadow-utils/passwd 4.13, adduser, sudo 1.9.15p5, OpenSSH 9.6p1, PAM yescrypt
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: account-db
    text: Membaca /etc/passwd, /etc/shadow, /etc/group
  - id: manage-users
    text: Membuat, mengubah, dan menghapus user (termasuk user sistem untuk service)
  - id: groups
    text: Mengelola keanggotaan grup tanpa menghapus grup lain secara tidak sengaja
  - id: disable
    text: Menonaktifkan akun dengan benar — password, SSH key, dan kedaluwarsa akun
  - id: sudo-use
    text: Memakai sudo dan su dengan benar (-u, -i, env, redirect)
  - id: sudoers
    text: Menulis aturan sudoers least-privilege dan memvalidasinya
  - id: sudo-security
    text: Mengenali aturan sudo yang bisa dieskalasi (shell escape, wildcard)
  - id: uid-hygiene
    text: Menangani file tanpa pemilik dan UID yang dipakai ulang
sources:
  - title: passwd(5)
    url: https://man7.org/linux/man-pages/man5/passwd.5.html
  - title: shadow(5)
    url: https://man7.org/linux/man-pages/man5/shadow.5.html
  - title: useradd(8)
    url: https://man7.org/linux/man-pages/man8/useradd.8.html
  - title: usermod(8)
    url: https://man7.org/linux/man-pages/man8/usermod.8.html
  - title: userdel(8)
    url: https://man7.org/linux/man-pages/man8/userdel.8.html
  - title: chage(1)
    url: https://man7.org/linux/man-pages/man1/chage.1.html
  - title: gpasswd(1)
    url: https://man7.org/linux/man-pages/man1/gpasswd.1.html
  - title: login.defs(5)
    url: https://man7.org/linux/man-pages/man5/login.defs.5.html
  - title: adduser(8) — Ubuntu
    url: https://manpages.ubuntu.com/manpages/noble/man8/adduser.8.html
  - title: sudoers(5)
    url: https://www.sudo.ws/docs/man/sudoers.man/
  - title: sudo(8)
    url: https://www.sudo.ws/docs/man/sudo.man/
  - title: visudo(8)
    url: https://www.sudo.ws/docs/man/visudo.man/
  - title: sshd_config(5) — UsePAM
    url: https://man.openbsd.org/sshd_config#UsePAM
  - title: GTFOBins — binary yang bisa dipakai keluar ke shell lewat sudo
    url: https://gtfobins.github.io/
  - title: Docker Docs — Manage Docker as a non-root user (grup docker setara root)
    url: https://docs.docker.com/engine/install/linux-postinstall/
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: account-db
    prompt: File apa yang menyimpan **hash password** user (hanya terbaca root dan grup shadow)?
    answers: ['/etc/shadow']
    tests:
      fail: ['/etc/passwd', '/etc/group', '/etc/gshadow', 'shadow']
    explanation: '`/etc/passwd` harus terbaca semua program (nama ↔ UID), jadi kolom password-nya hanya `x`. Hash disimpan di `/etc/shadow` yang ber-mode `640 root:shadow` (dicek di lab).'
    source: https://man7.org/linux/man-pages/man5/shadow.5.html

  - id: a02
    tier: A
    type: text
    objective: account-db
    prompt: 'Baris `/etc/passwd`: `bob:x:1002:1002::/home/bob:/bin/bash`. Berapa **UID** bob?'
    answers: ['1002']
    tests:
      fail: ['1000', '0', 'bob']
    explanation: 'Kolom: nama : password (`x` = di shadow) : UID : GID utama : GECOS : home : shell. Gunakan `getent passwd bob`, yang juga membaca LDAP/SSSD, bukan hanya file lokal.'
    source: https://man7.org/linux/man-pages/man5/passwd.5.html

  - id: a03
    tier: A
    type: command
    objective: account-db
    prompt: Tampilkan UID, GID utama, dan **semua grup** user `bob` dalam satu baris.
    answers: ['id bob']
    patterns: ['^id bob$']
    tests:
      fail: ['whoami bob', 'groups', 'getent group bob']
    explanation: 'Di lab: `uid=1002(bob) gid=1002(bob) groups=1002(bob),27(sudo)`. `groups bob` hanya menampilkan nama grup.'
    source: https://man7.org/linux/man-pages/man1/id.1.html

  - id: a04
    tier: A
    type: command
    objective: manage-users
    prompt: Dengan `useradd`, buat user `deploy` beserta **home directory**-nya dan shell `/bin/bash`.
    answers: ['useradd -m -s /bin/bash deploy']
    patterns: ['^useradd (?=.*(-m|--create-home)( |$))(?=.*(-s|--shell) /bin/bash( |$))((-m|--create-home|-s /bin/bash|--shell /bin/bash) )+deploy$']
    tests:
      pass: ['useradd -s /bin/bash -m deploy', 'useradd --create-home --shell /bin/bash deploy']
      fail: ['useradd deploy', 'useradd -s /bin/bash deploy', 'useradd -m deploy']
    explanation: 'Di lab, `useradd alice` tanpa opsi membuat user **tanpa** home dan dengan shell `/bin/sh`. `-m` menyalin `/etc/skel` (`.bashrc`, `.profile`). Di Debian/Ubuntu, `adduser deploy` (interaktif) melakukan semuanya dengan default yang lebih ramah.'
    source: https://man7.org/linux/man-pages/man8/useradd.8.html

  - id: a05
    tier: A
    type: command
    objective: groups
    prompt: Tambahkan `deploy` ke grup `docker` **tanpa** mengeluarkannya dari grup lain yang sudah ia ikuti.
    answers: ['usermod -aG docker deploy']
    patterns:
      - '^usermod (-aG docker|-a -G docker|-G docker -a|--append --groups docker|-a --groups docker|--groups docker --append|--append -G docker) deploy$'
      - '^(gpasswd (-a|--add) deploy docker|adduser deploy docker)$'
    tests:
      pass: ['usermod -a -G docker deploy', 'gpasswd -a deploy docker', 'adduser deploy docker']
      fail: ['usermod -G docker deploy', 'usermod -g docker deploy', 'groupadd docker deploy']
    explanation: '`-a` (append) wajib. Grup baru baru berlaku di sesi login berikutnya (atau `newgrp`). Ingat: anggota grup `docker` praktis setara root — dokumentasi Docker menyebut grup ini memberi hak setingkat root.'
    source: https://man7.org/linux/man-pages/man8/usermod.8.html

  - id: a06
    tier: A
    type: command
    objective: manage-users
    prompt: Buat **user sistem** `app` untuk menjalankan service, tanpa shell login (`/usr/sbin/nologin`).
    answers: ['useradd -r -s /usr/sbin/nologin app']
    patterns:
      - '^useradd (?=.*(-r|--system)( |$))(?=.*(-s|--shell) /usr/sbin/nologin( |$))((-r|--system|-s /usr/sbin/nologin|--shell /usr/sbin/nologin|-M|--no-create-home|-U|--user-group|-d /\S+|--home-dir /\S+) )+app$'
      - '^adduser (?=.*--system)(--(system|group|no-create-home|disabled-login)( |$)|--home /\S+ |--shell /usr/sbin/nologin )+app$'
    tests:
      pass: ['useradd --system --shell /usr/sbin/nologin -d /srv/app -M app', 'adduser --system --group app']
      fail: ['useradd app', 'useradd -m app', 'useradd -s /usr/sbin/nologin app']
    explanation: '`-r` memberi UID di bawah `UID_MIN` (lab: `app:x:999:995::/srv/app:/usr/sbin/nologin`). `su - app` menghasilkan `This account is currently not available`, tapi service tetap bisa berjalan sebagai user ini (`User=app` di systemd).'
    source: https://man7.org/linux/man-pages/man8/useradd.8.html

  - id: a07
    tier: A
    type: command
    objective: manage-users
    prompt: Sebagai root, atur/ganti password user `deploy` secara interaktif.
    answers: ['passwd deploy']
    patterns: ['^passwd deploy$']
    tests:
      fail: ['passwd', 'chpasswd deploy', 'usermod -p deploy']
    explanation: '`passwd` tanpa nama mengganti password **kamu sendiri**. Untuk skrip: `echo ''deploy:...'' | chpasswd`. `usermod -p` mengharapkan hash, bukan password biasa.'
    source: https://man7.org/linux/man-pages/man1/passwd.1.html

  - id: a08
    tier: A
    type: command
    objective: disable
    prompt: Kunci **password** user `deploy` supaya tidak bisa login dengan password.
    answers: ['passwd -l deploy']
    patterns: ['^(passwd (-l|--lock)|usermod (-L|--lock)) deploy$']
    tests:
      pass: ['usermod -L deploy', 'passwd --lock deploy']
      fail: ['passwd -d deploy', 'userdel deploy', 'passwd -u deploy']
    explanation: 'Lock menambahkan `!` di depan hash (lab: `!$y$j...`, `passwd -S` → `L`). **Hanya password** yang terkunci — lihat soal tier B tentang SSH key. `passwd -d` justru menghapus password (bisa berarti tanpa password!).'
    source: https://man7.org/linux/man-pages/man1/passwd.1.html

  - id: a09
    tier: A
    type: command
    objective: manage-users
    prompt: Hapus user `carol` **beserta** home directory dan mail spool-nya.
    answers: ['userdel -r carol']
    patterns: ['^(userdel (-r|--remove)|deluser --remove-home) carol$']
    tests:
      pass: ['deluser --remove-home carol', 'userdel --remove carol']
      fail: ['userdel carol', 'rm -rf /home/carol', 'deluser carol']
    explanation: 'Tanpa `-r`, `/home/carol` tetap ada (lab). `-r` hanya menghapus home dan mail; file milik carol **di tempat lain** tetap ada dengan UID angka — lihat bagian UID hygiene.'
    source: https://man7.org/linux/man-pages/man8/userdel.8.html

  - id: a10
    tier: A
    type: command
    objective: sudo-use
    prompt: Tampilkan perintah apa saja yang **boleh** kamu jalankan lewat sudo.
    answers: ['sudo -l']
    patterns: ['^sudo (-l|--list)$']
    tests:
      fail: ['sudo su', 'id', 'cat /etc/sudoers']
    explanation: 'Root bisa memeriksa user lain: `sudo -l -U alice`. Di lab: `(root) NOPASSWD: /usr/bin/systemctl restart nginx`. Ini juga langkah pertama penyerang setelah mendapat shell — aturan longgar langsung terlihat.'
    source: https://www.sudo.ws/docs/man/sudo.man/

  - id: a11
    tier: A
    type: command
    objective: sudoers
    prompt: Edit `/etc/sudoers` dengan aman — syntax dicek sebelum disimpan.
    answers: ['visudo']
    patterns: ['^visudo$', '^visudo -f /etc/sudoers\.d/\S+$']
    tests:
      pass: ['visudo -f /etc/sudoers.d/deploy']
      fail: ['vim /etc/sudoers', 'nano /etc/sudoers', 'sudoedit /etc/sudoers']
    explanation: 'Satu kesalahan syntax di sudoers bisa mengunci semua orang dari sudo. `visudo` mengunci file, memvalidasi, dan menolak menyimpan bila salah. Di lab: `syntax error` dengan penunjuk kolom.'
    source: https://www.sudo.ws/docs/man/visudo.man/

  - id: a12
    tier: A
    type: command
    objective: sudo-use
    prompt: Jalankan `php artisan cache:clear` **sebagai user `www-data`** dengan sudo.
    answers: ['sudo -u www-data php artisan cache:clear']
    patterns: ['^sudo (-u ?|--user[= ])www-data php artisan cache:clear$']
    tests:
      pass: ['sudo --user=www-data php artisan cache:clear']
      fail: ['su www-data php artisan cache:clear', 'sudo php artisan cache:clear', 'sudo -i php artisan cache:clear']
    explanation: 'Menjalankan sebagai root membuat file cache baru milik root, sehingga web server (`www-data`) gagal menulisnya nanti — penyebab klasik error 500 setelah maintenance.'
    source: https://www.sudo.ws/docs/man/sudo.man/

  - id: a13
    tier: A
    type: text
    objective: sudoers
    prompt: Di Ubuntu, user yang dimasukkan ke grup apa **otomatis** boleh memakai sudo (aturan `%...` bawaan)?
    answers: ['sudo', '%sudo']
    tests:
      fail: ['wheel', 'root', 'adm', 'docker']
    explanation: '`/etc/sudoers` Ubuntu berisi `%sudo ALL=(ALL:ALL) ALL` dan `@includedir /etc/sudoers.d` (lab). Di RHEL/Rocky grupnya `wheel`. `adm` hanya memberi akses baca log.'
    source: https://www.sudo.ws/docs/man/sudoers.man/

  - id: a14
    tier: A
    type: command
    objective: sudo-use
    prompt: Pindah menjadi user `bob` dengan **login shell** penuh (HOME, env, dan direktori kerja milik bob).
    answers: ['su - bob']
    patterns: ['^su (-|-l|--login) bob$', '^sudo (-i -u ?bob|-u ?bob -i|-iu ?bob|--login --user[= ]bob)$']
    tests:
      pass: ['su -l bob', 'sudo -iu bob', 'sudo -u bob -i']
      fail: ['su bob', 'sudo -u bob', 'sudo -s']
    explanation: 'Di lab dari `/tmp`: `su bob` tetap di direktori lama, `su - bob` pindah ke `/home/bob` dan memuat profil bob. Tanpa login shell, `PATH` dan variabel user lama bisa terbawa.'
    source: https://man7.org/linux/man-pages/man1/su.1.html

  - id: a15
    tier: A
    type: text
    objective: account-db
    prompt: Di Ubuntu, UID user biasa (bukan sistem) dimulai dari angka berapa (`UID_MIN`)?
    answers: ['1000']
    tests:
      fail: ['500', '100', '0', '999']
    explanation: 'Dari `/etc/login.defs` (lab: `UID_MIN 1000`, `UID_MAX 60000`). User sistem (`-r`) mendapat UID di bawahnya, mis. `999`. RHEL lama (≤6) memakai 500.'
    source: https://man7.org/linux/man-pages/man5/login.defs.5.html

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: groups
    prompt: 'bob ada di grup `sudo` dan `docker`. Admin menjalankan `usermod -G devs bob` (tanpa `-a`). Setelah itu, bob ada di grup tambahan apa saja?'
    answers: ['devs', 'hanya devs', 'cuma devs']
    tests:
      fail: ['sudo docker devs', 'sudo, docker, devs', 'sudo devs', 'docker devs']
    explanation: '`-G` tanpa `-a` **mengganti** seluruh daftar grup tambahan. Di lab: sebelum `bob sudo docker`, sesudah `bob devs` — akses sudo bob hilang. Kalau itu terjadi pada akunmu sendiri di server tanpa root password, kamu terkunci.'
    source: https://man7.org/linux/man-pages/man8/usermod.8.html

  - id: b02
    tier: B
    type: command
    objective: groups
    prompt: Keluarkan `bob` dari grup `docker` saja (grup lain tetap).
    answers: ['gpasswd -d bob docker']
    patterns: ['^(gpasswd (-d|--delete) bob docker|deluser bob docker)$']
    tests:
      pass: ['deluser bob docker', 'gpasswd --delete bob docker']
      fail: ['usermod -G docker bob', 'groupdel docker', 'userdel bob docker']
    explanation: 'Di lab: `Removing user bob from group docker`, sisa grup `bob sudo devs`. `groupdel docker` menghapus grupnya untuk semua orang.'
    source: https://man7.org/linux/man-pages/man1/gpasswd.1.html

  - id: b03
    tier: B
    type: command
    objective: disable
    prompt: Nonaktifkan akun `alice` sepenuhnya — termasuk login dengan **SSH key** — dengan mengatur **tanggal kedaluwarsa akun** ke masa lalu.
    answers: ['chage -E 0 alice']
    patterns: ['^(chage (-E|--expiredate) ?(0|1970-01-0[12])|usermod (-e|--expiredate) ?(1|1970-01-0[12])) alice$']
    tests:
      pass: ['usermod -e 1 alice', 'chage --expiredate 1970-01-02 alice']
      fail: ['passwd -l alice', 'chage -M 0 alice', 'usermod -L alice']
    explanation: 'Di lab, setelah `chage -E 0 alice` login SSH key ditolak (`Connection closed`). `chage -l` menampilkan `Account expires: Jan 01, 1970`. Batalkan dengan `chage -E -1`. `-M 0` mengatur umur **password**, bukan akun.'
    source: https://man7.org/linux/man-pages/man1/chage.1.html

  - id: b04
    tier: B
    type: text
    objective: disable
    prompt: 'Di Ubuntu (default `UsePAM yes`), setelah `passwd -l alice`, apakah alice **masih bisa** login SSH dengan key yang ada di `authorized_keys`? (ya/tidak)'
    answers: ['ya', 'yes', 'bisa', 'masih bisa']
    tests:
      fail: ['tidak', 'no', 'tidak bisa']
    explanation: 'Diuji di lab: `passwd -S alice` → `L`, tetapi `ssh -i key alice@host` → `LOGIN-OK as alice`. Dengan `UsePAM no`, sshd sendiri menolak akun yang password-nya terkunci (lab: `Permission denied`), tapi itu bukan default Ubuntu. Untuk offboarding: `chage -E 0`, shell `nologin`, dan hapus `authorized_keys`.'
    source: https://man.openbsd.org/sshd_config#UsePAM

  - id: b05
    tier: B
    type: command
    objective: sudoers
    prompt: Validasi syntax file `/etc/sudoers.d/deploy` **tanpa** membuka editor.
    answers: ['visudo -cf /etc/sudoers.d/deploy']
    patterns: ['^visudo (-cf|-c -f|-fc|--check --file[= ]?|-c --file[= ]?) ?/etc/sudoers\.d/deploy$', '^visudo -f /etc/sudoers\.d/deploy -c$']
    tests:
      pass: ['visudo -c -f /etc/sudoers.d/deploy', 'visudo --check --file=/etc/sudoers.d/deploy']
      fail: ['visudo', 'cat /etc/sudoers.d/deploy', 'sudo -l']
    explanation: 'Di lab: file benar → `parsed OK` (exit 0), file salah → `syntax error` (exit 1). Cocok untuk Ansible (`validate: visudo -cf %s`) dan CI. `visudo -c` tanpa `-f` memeriksa sudoers beserta semua include.'
    source: https://www.sudo.ws/docs/man/visudo.man/

  - id: b06
    tier: B
    type: text
    objective: sudoers
    prompt: 'File `/etc/sudoers.d/deploy.conf` isinya benar, tapi aturannya **tidak berlaku sama sekali**. Karakter apa di nama file yang membuat sudo mengabaikannya? (jawab dengan kata)'
    answers: ['titik', 'dot', 'tanda titik']
    patterns: ['^(karakter |tanda )?(titik|dot)( \(\.\))?$']
    tests:
      pass: ['karakter titik', 'dot (.)']
      fail: ['tilde', 'conf', 'spasi', 'garis bawah']
    explanation: '`@includedir` melewati file yang namanya mengandung `.` atau berakhiran `~` (untuk menghindari backup editor dan file `.dpkg-old`). Di lab, `alice.conf` → `alice is not allowed to run sudo`. Setelah di-rename jadi `alice`, aturannya langsung berlaku. Tidak ada error apa pun.'
    source: https://www.sudo.ws/docs/man/sudoers.man/

  - id: b07
    tier: B
    type: command
    objective: sudoers
    prompt: Atur permission `/etc/sudoers.d/deploy` sesuai konvensi `visudo` (read-only untuk owner root dan grup).
    answers: ['chmod 440 /etc/sudoers.d/deploy']
    patterns: ['^chmod (0?440|ug=r,o=|u=r,g=r,o=) /etc/sudoers\.d/deploy$']
    tests:
      pass: ['chmod 0440 /etc/sudoers.d/deploy']
      fail: ['chmod 666 /etc/sudoers.d/deploy', 'chmod 777 /etc/sudoers.d/deploy', 'chmod 644 /etc/sudoers.d/deploy']
    explanation: '`visudo` membuat file dengan mode `0440`. sudo sendiri menolak file yang **world-writable**. Di lab, mode 666 → `sudo: /etc/sudoers.d/alice is world writable`, dan aturannya tidak dipakai. Mode 644/664 masih diterima di lab, tapi tidak ada alasan untuk tidak memakai 0440. Cara ringkas: `install -m 440 file /etc/sudoers.d/deploy`.'
    source: https://www.sudo.ws/docs/man/visudo.man/

  - id: b08
    tier: B
    type: command
    objective: sudoers
    prompt: 'Tulis satu baris sudoers: user `deploy` boleh menjalankan **hanya** `/usr/bin/systemctl restart nginx` sebagai root, tanpa password.'
    answers: ['deploy ALL=(root) NOPASSWD: /usr/bin/systemctl restart nginx']
    patterns: ['^deploy ALL ?= ?(\((root|ALL)(:\w+)?\) ?)?NOPASSWD: ?/usr/bin/systemctl restart nginx(\.service)?$']
    tests:
      pass: ['deploy ALL=NOPASSWD: /usr/bin/systemctl restart nginx', 'deploy ALL = (root) NOPASSWD: /usr/bin/systemctl restart nginx.service']
      fail: ['deploy ALL=(ALL) NOPASSWD: ALL', 'deploy ALL=(root) NOPASSWD: /usr/bin/systemctl', 'deploy ALL=(root) NOPASSWD: systemctl restart nginx']
    explanation: 'Argumen yang ditulis membatasi perintah persis itu. Di lab, `sudo systemctl stop nginx` ditolak (`a password is required`). Path wajib absolut; `systemctl` saja ditolak visudo: `expected a fully-qualified path name`. `/usr/bin/systemctl` tanpa argumen = boleh semua argumen, termasuk `systemctl edit`.'
    source: https://www.sudo.ws/docs/man/sudoers.man/

  - id: b09
    tier: B
    type: text
    objective: sudo-security
    prompt: 'Aturan `deploy ALL=(root) NOPASSWD: /usr/bin/vi` — secara efektif, deploy mendapat akses apa?'
    answers: ['root', 'shell root', 'akses root penuh']
    patterns: ['^(shell |akses |jadi |menjadi )?(root|uid=0)( penuh| shell)?$']
    tests:
      pass: ['jadi root', 'akses root penuh']
      fail: ['edit file saja', 'hanya vi', 'tidak ada', 'read only']
    explanation: 'Banyak program bisa menjalankan perintah lain: vi (`:!sh`), less (`!sh`), find (`-exec`), tar, git, awk, man. Di lab, `sudo vi -c '':!id''` dan `sudo find ... -exec id \;` keduanya mencetak `uid=0(root)`. GTFOBins mendata ratusan binary semacam ini. Untuk mengedit file, pakai `sudoedit`.'
    source: https://gtfobins.github.io/

  - id: b10
    tier: B
    type: command
    objective: sudo-security
    prompt: 'Ubah aturan `deploy ALL=(root) NOPASSWD: /usr/bin/find` supaya `find` **tidak bisa** menjalankan program lain (tag sudoers).'
    answers: ['deploy ALL=(root) NOPASSWD:NOEXEC: /usr/bin/find']
    patterns: ['^deploy ALL ?= ?(\((root|ALL)\) ?)?(NOPASSWD: ?NOEXEC|NOEXEC: ?NOPASSWD): ?/usr/bin/find$']
    tests:
      pass: ['deploy ALL=(root) NOEXEC:NOPASSWD: /usr/bin/find']
      fail: ['deploy ALL=(root) NOPASSWD: /usr/bin/find', 'deploy ALL=(root) NOPASSWD: !/usr/bin/find']
    explanation: 'Di lab dengan `NOEXEC`: `find: ''id'': Permission denied`. NOEXEC bekerja dengan mencegah `execve` dari program itu. Ini mitigasi, bukan jaminan: program yang bisa menulis file sebagai root (tee, cp, editor) tetap berbahaya walau tidak meng-exec apa pun.'
    source: https://www.sudo.ws/docs/man/sudoers.man/

  - id: b11
    tier: B
    type: command
    objective: sudo-use
    prompt: Dari user biasa, tulis teks `maintenance` ke `/etc/motd.d/99-note` (milik root) memakai sudo.
    answers: ['echo maintenance | sudo tee /etc/motd.d/99-note']
    patterns:
      - '^(echo|printf) maintenance ?\| ?sudo tee (-a )?/etc/motd\.d/99-note( >/dev/null)?$'
      - '^(sh|bash) -c echo maintenance ?> ?/etc/motd\.d/99-note$'
    tests:
      pass: ['echo maintenance | sudo tee /etc/motd.d/99-note > /dev/null', 'sudo sh -c ''echo maintenance > /etc/motd.d/99-note''']
      fail: ['sudo echo maintenance > /etc/motd.d/99-note', 'echo maintenance > /etc/motd.d/99-note']
    explanation: 'Redirect `>` dikerjakan shell **kamu** (user biasa) sebelum sudo jalan. Di lab, `sudo echo x > /etc/motd.d/99-note` → `Permission denied`, sedangkan `| sudo tee` berhasil. `tee -a` untuk menambahkan.'
    source: https://www.sudo.ws/docs/man/sudo.man/

  - id: b12
    tier: B
    type: text
    objective: sudo-use
    prompt: 'Kamu `export FOO=1`, lalu `sudo env | grep FOO` kosong. Opsi `Defaults` bawaan apa yang membuang environment saat sudo?'
    answers: ['env_reset', 'defaults env_reset']
    tests:
      fail: ['secure_path', 'env_keep', 'NOPASSWD', 'use_pty']
    explanation: 'Di lab: `sudo env | grep -c FOO` → 0, `sudo -E env` → `FOO=1` (bila diizinkan). `secure_path` juga mengganti `PATH`, sehingga program di `~/bin` tidak terlihat oleh sudo. Ini fitur keamanan: environment user (mis. `LD_PRELOAD`) tidak boleh memengaruhi proses root.'
    source: https://www.sudo.ws/docs/man/sudoers.man/

  - id: b13
    tier: B
    type: command
    objective: uid-hygiene
    prompt: Cari file di filesystem root (tanpa menyeberang ke mount lain) yang **pemiliknya sudah tidak ada** di database user.
    answers: ['find / -xdev -nouser']
    patterns: ['^find / (-xdev -nouser|-nouser -xdev|-mount -nouser|-xdev \( -nouser -o -nogroup \))( 2>/dev/null)?$']
    tests:
      pass: ['find / -nouser -xdev 2>/dev/null', 'find / -xdev ( -nouser -o -nogroup )']
      fail: ['find / -user root', 'find / -nouser -delete', 'ls -ln /']
    explanation: 'Setelah `userdel` (lab), file carol tampil sebagai `1003 1005`. Jalankan sebelum UID itu dipakai lagi. Kalau terlambat, `-nouser` tidak menemukan apa-apa karena file itu sudah "dimiliki" user baru.'
    source: https://man7.org/linux/man-pages/man1/find.1.html

  - id: b14
    tier: B
    type: command
    objective: disable
    prompt: Tampilkan info umur password dan kedaluwarsa akun `bob` dalam format tanggal yang mudah dibaca.
    answers: ['chage -l bob']
    patterns: ['^chage (-l|--list) bob$']
    tests:
      fail: ['passwd bob', 'id bob', 'chage bob']
    explanation: 'Baris penting: `Password expires`, `Password inactive`, `Account expires`. `chage bob` tanpa `-l` masuk mode interaktif untuk **mengubah** nilai.'
    source: https://man7.org/linux/man-pages/man1/chage.1.html

  - id: b15
    tier: B
    type: text
    objective: account-db
    prompt: 'Hash password di `/etc/shadow` Ubuntu 24.04 diawali `$y$`. Algoritma apa itu?'
    answers: ['yescrypt']
    tests:
      fail: ['sha512', 'md5', 'bcrypt', 'sha256']
    explanation: 'Awalan: `$1$` MD5, `$5$` SHA-256, `$6$` SHA-512, `$2b$` bcrypt, `$y$` yescrypt. Temuan lab: `login.defs` masih menulis `ENCRYPT_METHOD SHA512`, tapi hasilnya `$y$`, karena `chpasswd` memakai PAM (`pam_unix.so obscure yescrypt`). Selalu cek konfigurasi PAM, bukan hanya login.defs.'
    source: https://man7.org/linux/man-pages/man5/shadow.5.html

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: disable
    prompt: |
      Karyawan `rina` keluar hari ini dan sedang login. Akunnya harus dimatikan **sekarang**, tetapi file dan
      jejaknya harus tetap ada untuk audit. Tulis langkah (satu perintah per baris) untuk memutus semua jalur
      akses (password, SSH key, sesi berjalan, hak istimewa) dan memeriksa apa yang ia tinggalkan.
    context: |
      $ id rina
      uid=1007(rina) gid=1007(rina) groups=1007(rina),27(sudo),999(docker)
      $ ls /etc/sudoers.d/
      README  rina-deploy
      $ who
      rina     pts/1        2026-11-20 09:14 (10.0.4.22)
    checklist:
      - id: expire
        label: Mengunci akun total (chage -E 0 / usermod -e 1)
        required: true
        patterns: ['chage\s+(-E|--expiredate)\s*(0|1970-01-0[12])\s+rina', 'usermod\s+(\S+\s+)*(-e|--expiredate)\s*(1|1970-01-0[12])\s+(\S+\s+)*rina\s*$']
      - id: shell
        label: Shell ke nologin
        patterns: ['usermod\s+(\S+\s+)*(-s|--shell)\s+/(usr/)?s?bin/(nologin|false)\s+(\S+\s+)*rina\s*$', 'chsh\s+-s\s+\S*(nologin|false)\s+rina']
      - id: keys
        label: Mencabut SSH key (authorized_keys)
        required: true
        patterns: ['(rm|mv|truncate|cp\s+/dev/null|>).*(/home/rina|~rina)/\.ssh/authorized_keys', 'authorized_keys.*(disabled|\.bak|\.revoked)']
      - id: privileges
        label: Mencabut sudo & docker (gpasswd -d, hapus sudoers.d/rina-deploy)
        required: true
        patterns: ['gpasswd\s+(-d|--delete)\s+rina\s+(sudo|docker)', 'deluser\s+rina\s+(sudo|docker)', '(rm|mv)\s+(-\S+\s+)*/etc/sudoers\.d/rina']
      - id: sessions
        label: Memutus sesi berjalan (pkill -u / loginctl terminate-user)
        patterns: ['pkill\s+(-\S+\s+)*-u\s*rina', 'loginctl\s+(terminate|kill)-user\s+rina', 'killall\s+(-\S+\s+)*-u\s+rina']
      - id: scheduled
        label: Memeriksa job terjadwal milik rina (crontab -l -u, at, timer user)
        patterns: ['crontab\s+(-u\s+rina\s+-l|-l\s+-u\s+rina)', 'crontab\s+(-u\s+rina\s+-r|-r\s+-u\s+rina)', '/var/spool/cron', 'atq', 'systemctl\s+--user']
      - id: files
        label: Menginventaris file milik rina (find -user rina / -uid 1007)
        patterns: ['find\s+/\S*\s+.*(-user\s+rina|-uid\s+1007)']
      - id: no-userdel
        label: Tidak langsung userdel -r (menghapus bukti dan file kerja)
        forbid: true
        patterns: ['userdel\s+(-\S+\s+)*(-r|--remove)\s+(-\S+\s+)*rina']
      - id: no-777
        label: Tidak membuka permission sembarangan
        forbid: true
        patterns: ['chmod\s+(-\S+\s+)*0?777']
    pass_score: 0.6
    solution: |
      ```bash
      chage -E 0 rina                                   # akun kedaluwarsa: semua login ditolak
      usermod -s /usr/sbin/nologin rina
      mv /home/rina/.ssh/authorized_keys /home/rina/.ssh/authorized_keys.revoked
      gpasswd -d rina sudo; gpasswd -d rina docker
      mv /etc/sudoers.d/rina-deploy /root/offboarding-rina-deploy.sudoers
      loginctl terminate-user rina || pkill -KILL -u rina
      crontab -l -u rina; ls /var/spool/cron/crontabs/
      find / -xdev -user rina -printf '%TY-%Tm-%Td %p\n' 2>/dev/null | sort > /root/rina-files.txt
      ```
      Urutan penting: putus semua jalur masuk dulu, baru putus sesi, supaya rina tidak bisa langsung login lagi.
      `passwd -l` saja **tidak cukup**: di lab, akun yang terkunci password-nya tetap bisa login SSH dengan key.
      Grup `docker` setara root, jadi wajib ikut dicabut. File sudoers dipindahkan, bukan diedit. Bila kamu
      menyimpannya di `/etc/sudoers.d` dengan nama berisi titik, sudo akan mengabaikannya.
      Jangan `userdel -r` dulu: file kerja dan jejak aktivitasnya dibutuhkan untuk audit dan serah-terima.
      Setelah selesai, hapus akun dengan prosedur yang menangani file sisa (lihat studi kasus UID).
    tests:
      pass:
        - |
          chage -E 0 rina
          usermod -s /usr/sbin/nologin rina
          rm /home/rina/.ssh/authorized_keys
          gpasswd -d rina sudo
          gpasswd -d rina docker
          rm /etc/sudoers.d/rina-deploy
          pkill -KILL -u rina
          crontab -l -u rina
          find / -xdev -user rina
        - |
          usermod -e 1 -s /usr/sbin/nologin rina
          mv ~rina/.ssh/authorized_keys ~rina/.ssh/authorized_keys.revoked
          deluser rina sudo
          loginctl terminate-user rina
          find /srv -uid 1007
      fail:
        - |
          passwd -l rina
          pkill -u rina
        - userdel -r rina
        - |
          chage -E 0 rina
          pkill -u rina
          crontab -l -u rina
    source: https://man7.org/linux/man-pages/man1/chage.1.html

  - id: c02
    tier: C
    type: scenario
    objective: sudoers
    prompt: |
      Pipeline CI login sebagai `deploy` dan, menurut developer, "butuh sudo". Setelah dicek, yang benar-benar dibutuhkan:
      restart `php8.3-fpm`, reload `nginx`, dan menjalankan `/usr/bin/php /srv/app/artisan migrate --force` sebagai
      `www-data`. Developer mengusulkan `deploy ALL=(ALL) NOPASSWD: ALL`. Tulis isi file `/etc/sudoers.d/deploy`
      yang least-privilege, lalu perintah untuk memasang dan memvalidasinya.
    checklist:
      - id: restart-fpm
        label: systemctl restart php8.3-fpm sebagai root, path absolut
        required: true
        patterns: ['^deploy\s+all\s*=\s*(\((root|all)(:\w+)?\)\s*)?nopasswd:\s*.*/usr/bin/systemctl\s+restart\s+php8\.3-fpm']
      - id: reload-nginx
        label: systemctl reload nginx
        patterns: ['/usr/bin/systemctl\s+reload\s+nginx']
      - id: artisan
        label: artisan migrate --force sebagai www-data (bukan root)
        required: true
        patterns: ['^deploy\s+all\s*=\s*\(www-data(:\S+)?\)\s*nopasswd:\s*/usr/bin/php\s+/srv/app/artisan\s+migrate\s+--force']
      - id: validate
        label: Validasi dengan visudo -c(f)
        patterns: ['visudo\s+(-cf|-c\s+-f|-c|--check)']
      - id: perm
        label: Mode 0440 (chmod 440 / install -m 440)
        patterns: ['chmod\s+0?440\s+/etc/sudoers\.d/\S+', 'install\s+.*-m\s*0?440']
      - id: no-all
        label: Tidak memberi NOPASSWD ALL
        forbid: true
        patterns: ['^deploy\s+all\s*=\s*(\([^)]*\)\s*)?nopasswd:\s*all\s*$']
      - id: no-escape
        label: Tidak mengizinkan binary yang bisa keluar ke shell atau systemctl tanpa argumen
        forbid: true
        patterns: ['nopasswd:.*/usr/bin/(vi|vim|less|more|find|bash|sh|tee|cp|chmod|chown|env|awk|tar)\b', '/usr/bin/systemctl\s*(,|$)', 'nopasswd:.*\*']
    pass_score: 0.6
    solution: |
      ```text
      # /etc/sudoers.d/deploy  (tanpa titik di nama file)
      deploy ALL=(root) NOPASSWD: /usr/bin/systemctl restart php8.3-fpm, /usr/bin/systemctl reload nginx
      deploy ALL=(www-data) NOPASSWD: /usr/bin/php /srv/app/artisan migrate --force
      ```
      ```bash
      visudo -cf /tmp/deploy.sudoers
      install -m 440 -o root -g root /tmp/deploy.sudoers /etc/sudoers.d/deploy
      sudo -l -U deploy
      ```
      Diuji di lab: `parsed OK`, dan `sudo -l -U deploy` menampilkan tepat dua baris aturan itu. CI lalu menjalankan
      `sudo -u www-data /usr/bin/php /srv/app/artisan migrate --force`. Hindari wildcard: di lab, aturan
      `/usr/bin/cat /var/log/*` bisa dipakai membaca `/etc/shadow` lewat `sudo cat /var/log/../../etc/shadow`,
      karena `*` di sudoers cocok dengan karakter apa pun, termasuk `/` dan `..`. Menjalankan artisan sebagai
      `www-data` (bukan root) juga mencegah file cache/log milik root yang membuat aplikasi error.
    tests:
      pass:
        - |
          deploy ALL=(root) NOPASSWD: /usr/bin/systemctl restart php8.3-fpm, /usr/bin/systemctl reload nginx
          deploy ALL=(www-data) NOPASSWD: /usr/bin/php /srv/app/artisan migrate --force
          visudo -cf /tmp/deploy.sudoers
          install -m 440 /tmp/deploy.sudoers /etc/sudoers.d/deploy
        - |
          deploy ALL=(root) NOPASSWD: /usr/bin/systemctl restart php8.3-fpm
          deploy ALL=(www-data) NOPASSWD: /usr/bin/php /srv/app/artisan migrate --force
          chmod 440 /etc/sudoers.d/deploy
      fail:
        - 'deploy ALL=(ALL) NOPASSWD: ALL'
        - |
          deploy ALL=(root) NOPASSWD: /usr/bin/systemctl
          deploy ALL=(www-data) NOPASSWD: /usr/bin/php /srv/app/artisan migrate --force
          visudo -cf /etc/sudoers.d/deploy
        - |
          deploy ALL=(root) NOPASSWD: /usr/bin/systemctl restart php8.3-fpm, /usr/bin/php /srv/app/artisan *
          visudo -cf /etc/sudoers.d/deploy
    source: https://www.sudo.ws/docs/man/sudoers.man/

  - id: c03
    tier: C
    type: scenario
    objective: uid-hygiene
    prompt: |
      Akun `rina` (UID 1007) dihapus bulan lalu dengan `userdel rina`. Hari ini user baru `budi` dibuat — dan budi
      ternyata bisa membuka semua file di `/srv/finance`. Tulis perintah (satu per baris) untuk membuktikan
      penyebabnya, menemukan semua file yang terdampak, menyerahkan file itu ke pemilik yang benar
      (`fin:finance`), dan mencegah kejadian serupa.
    context: |
      $ id budi
      uid=1007(budi) gid=1007(budi) groups=1007(budi)
      $ ls -l /srv/finance | head -n 3
      total 8
      -rw-r--r-- 1 budi budi 5120 Oct 02 10:11 payroll-2026-09.xlsx
      -rw-r--r-- 1 budi budi 4096 Oct 03 08:40 vendor-bank.xlsx
    checklist:
      - id: prove
        label: Membuktikan lewat UID numerik (ls -ln / stat -c %u / getent passwd 1007)
        patterns: ['ls\s+-\S*n', 'stat\s+(-c|--format)\s*\S*%u', 'getent\s+passwd\s+(1007|budi)', 'id\s+(-u\s+)?budi']
      - id: find
        label: Menemukan semua file UID 1007 di seluruh sistem
        required: true
        patterns: ['find\s+/\S*\s+(.*\s)?-uid\s+1007', 'find\s+/\S*\s+(.*\s)?-user\s+budi']
      - id: fix
        label: Menyerahkan ke fin:finance (chown --from / find -exec chown)
        required: true
        patterns: ['chown\s+(-\S+\s+)*--from[= ]?\S*(1007|budi)', 'find\s+.*(-uid\s+1007|-user\s+budi).*-exec\s+chown', 'chown\s+(-R\s+)?fin:finance\s+\S*/srv/finance']
      - id: restrict
        label: Menutup akses other pada data sensitif (o-rwx / 750 / 640 / g+s)
        patterns: ['chmod\s+(-\S+\s+)*\S*(o-rwx|o=|750|770|2770|2750|640|660)\s+\S*/srv/finance']
      - id: prevent
        label: Pencegahan (find -nouser saat offboarding, UID eksplisit/tidak dipakai ulang)
        patterns: ['find\s+/\S*\s+.*-nouser', 'useradd\s+(-\S+\s+)*(-u|--uid)\s*\d+', '--from']
      - id: no-delete-budi
        label: Tidak menghapus user budi (masalahnya file lama, bukan budi)
        forbid: true
        patterns: ['userdel\s+(-\S+\s+)*budi']
      - id: no-777
        label: Tidak chmod 777
        forbid: true
        patterns: ['chmod\s+(-\S+\s+)*0?777']
    pass_score: 0.6
    solution: |
      ```bash
      ls -ln /srv/finance | head -n 3                 # pemilik = 1007
      find / -xdev -uid 1007 -not -path '/home/budi/*' > /root/uid1007.txt
      chown -R --from=budi:budi fin:finance /srv/finance
      chmod -R o-rwx /srv/finance
      ```
      Kernel tidak mengenal nama, hanya **angka** UID. `userdel` tidak menyentuh file di luar home, sehingga
      file rina tetap ber-UID 1007. `useradd` memberi UID baru = UID terbesar yang ada + 1. Karena rina
      pemegang UID tertinggi, budi mendapat 1007 dan otomatis "memiliki" semua file rina. Direproduksi di lab:
      `carol` (1003) dihapus, `dave` dibuat → `dave uid=1003`, dan file carol kini tampil milik `dave`.
      `chown --from` hanya mengganti file yang pemiliknya memang 1007 (diuji di lab).
      Pencegahan:
      - Saat offboarding, jalankan `find / -xdev -user USER` lalu pindahkan file **sebelum** `userdel`, atau
        jalankan `find / -nouser` sesudahnya.
      - Di lingkungan dengan banyak server, kelola UID secara eksplisit/terpusat (LDAP/IdM, `useradd -u`) supaya
        UID tidak dipakai ulang.
    tests:
      pass:
        - |
          ls -ln /srv/finance
          find / -xdev -uid 1007
          chown -R --from=1007 fin:finance /srv/finance
          chmod -R o-rwx /srv/finance
        - |
          getent passwd 1007
          find /srv -user budi -exec chown fin:finance {} +
          find / -xdev -nouser
      fail:
        - userdel -r budi
        - |
          chown -R fin:finance /srv/finance
          chmod -R 777 /srv/finance
        - |
          ls -l /srv/finance
          id budi
    source: https://man7.org/linux/man-pages/man8/userdel.8.html

---

# User, Group & sudo

Setiap proses berjalan sebagai **user**, dan kernel mengecek akses berdasarkan **UID** dan **GID**, bukan nama.
Untuk DevOps/DevSecOps, area ini menentukan banyak hal sekaligus: user sistem untuk service, akses tim ke
server, offboarding yang benar-benar memutus akses, dan aturan sudo yang tidak diam-diam memberi root ke
semua orang. Modul ini melanjutkan modul Permission & Ownership.

> [!NOTE]
> Semua perilaku diuji ulang di container `ubuntu:24.04` (shadow 4.13, sudo 1.9.15p5, OpenSSH 9.6p1, PAM yescrypt).
> Di RHEL/Rocky, grup admin bernama `wheel`, perintah `adduser` adalah alias `useradd`, dan sshd tidak memakai
> patch Debian. Di lingkungan dengan LDAP/SSSD, pakai `getent` alih-alih membaca file di `/etc`.

## 1. Database akun

```text
/etc/passwd   bob:x:1002:1002::/home/bob:/bin/bash        (644, terbaca semua)
/etc/shadow   bob:$y$j9T...:20777:0:99999:7:::            (640 root:shadow)
/etc/group    sudo:x:27:ubuntu,bob                         (644)
```

| `/etc/passwd` | Isi |
|---|---|
| 1 nama · 2 `x` · 3 UID · 4 GID utama | `bob:x:1002:1002` |
| 5 GECOS · 6 home · 7 shell | `::/home/bob:/bin/bash` |

| Rentang UID (Ubuntu, `login.defs`) | Untuk |
|---|---|
| `0` | root |
| `1`–`999` | User sistem (service, paket) |
| `1000`–`60000` | User manusia |
| `65534` | `nobody` |

Awalan hash di `/etc/shadow`: `$y$` yescrypt (default Ubuntu 24.04), `$6$` SHA-512, `!` = terkunci,
`*` = tidak pernah punya password.

## 2. Membuat dan mengubah user

```bash
useradd -m -s /bin/bash -G sudo bob             # manusia: home + shell + grup
useradd -r -s /usr/sbin/nologin -d /srv/app -M app   # service: tanpa login, UID < 1000
adduser --system --group svc                     # versi Debian/Ubuntu (default nologin)
usermod -aG docker bob                           # tambah grup (APPEND!)
gpasswd -d bob docker                            # keluarkan dari satu grup
usermod -s /usr/sbin/nologin bob                 # ganti shell
passwd bob                                       # set password
userdel -r carol                                 # hapus + home
```

> [!DANGER]
> `usermod -G grup user` tanpa `-a` **mengganti** seluruh daftar grup tambahan. Di lab, bob yang tadinya
> `sudo docker` menjadi hanya `devs`, dan akses sudo-nya hilang. Kalau terjadi pada akunmu sendiri di server
> tanpa password root, satu-satunya jalan adalah console/recovery. Selalu `-aG`, atau pakai
> `gpasswd -a`/`adduser user grup`.

## 3. Menonaktifkan akun dengan benar

| Langkah | Memblokir | Hasil di lab |
|---|---|---|
| `passwd -l` / `usermod -L` | Login **password** | SSH key **tetap bisa** (UsePAM yes) |
| `usermod -s /usr/sbin/nologin` | Shell interaktif | `This account is currently not available` |
| `chage -E 0` | **Semua** login (akun kedaluwarsa) | SSH key ditolak |
| Hapus `~/.ssh/authorized_keys` | Login key | — |
| `gpasswd -d user sudo/docker`, hapus `sudoers.d` | Hak istimewa | — |
| `loginctl terminate-user` / `pkill -u` | Sesi berjalan | — |

> [!INSIGHT]
> `passwd -l` hanya menambahkan `!` di depan hash password. Di lab, setelah `passwd -l alice`, perintah
> `ssh -i key alice@host` tetap berhasil (`LOGIN-OK as alice`), karena autentikasi public key tidak memakai hash itu
> dan Ubuntu menjalankan sshd dengan `UsePAM yes`. Dengan `UsePAM no`, sshd sendiri menolak akun yang terkunci
> (`Permission denied`). Yang andal di keduanya: `chage -E 0` (akun kedaluwarsa).
> Sumber: [sshd_config(5) — UsePAM](https://man.openbsd.org/sshd_config#UsePAM),
> [chage(1)](https://man7.org/linux/man-pages/man1/chage.1.html)

## 4. `su` dan `sudo`

| Perintah | Hasil |
|---|---|
| `sudo cmd` | `cmd` sebagai root, dengan password **kamu** |
| `sudo -u www-data cmd` | Sebagai user lain |
| `sudo -i` | Login shell root (env root, `cd /root`) |
| `sudo -s` | Shell root, tetap di direktori saat ini |
| `su - bob` | Login shell bob, dengan password **bob** |
| `sudo -l` | Apa yang boleh kamu jalankan |
| `sudoedit file` | Edit file root dengan editor sebagai **kamu** (aman dari shell escape) |

```bash
echo maintenance | sudo tee /etc/motd.d/99-note >/dev/null   # bukan: sudo echo ... > file
sudo -u www-data php artisan cache:clear                      # bukan: sudo php artisan ...
```

`Defaults env_reset` dan `secure_path` (default Ubuntu) membuang environment dan mengganti `PATH`. Di lab,
`FOO` yang di-export hilang di bawah sudo.

## 5. Menulis aturan sudoers

```text
# user  host=(runas)        tag:        perintah (path absolut + argumen)
deploy  ALL=(root)          NOPASSWD:   /usr/bin/systemctl restart nginx
deploy  ALL=(www-data)      NOPASSWD:   /usr/bin/php /srv/app/artisan migrate --force
%ops    ALL=(ALL:ALL)       ALL
```

```bash
visudo -cf /tmp/deploy.sudoers                         # validasi dulu
install -m 440 -o root -g root /tmp/deploy.sudoers /etc/sudoers.d/deploy
sudo -l -U deploy
```

| Aturan | Kenapa (hasil lab) |
|---|---|
| Path absolut | `systemctl ...` ditolak visudo: `expected a fully-qualified path name` |
| Tulis argumennya | `/usr/bin/systemctl` tanpa argumen = boleh `systemctl` apa pun |
| Nama file tanpa `.` dan tanpa akhiran `~` | `deploy.conf` **diabaikan** tanpa error |
| Bukan world-writable (konvensi 0440) | Mode 666 → `is world writable`, aturan tidak dipakai |
| Selalu `visudo -c` | Satu syntax error bisa mematikan sudo untuk semua orang |

## 6. Aturan sudo yang diam-diam memberi root

```mermaid
flowchart LR
    R["deploy ALL=(root) NOPASSWD: X"] --> Q{X bisa menjalankan<br/>perintah lain atau<br/>menulis file bebas?}
    Q -- vi, less, find, awk, tar, git, man --> S[shell root]
    Q -- tee, cp, chmod, chown --> W[tulis /etc/sudoers, /etc/shadow → root]
    Q -- wildcard * di argumen --> P[path traversal / argumen tambahan]
    Q -- systemctl tanpa argumen --> U[systemctl edit / unit baru → root]
    Q -- tidak --> OK[aman-ish: perintah + argumen tetap]
```

> [!DANGER]
> Di lab, kedua aturan "sempit" ini menghasilkan `uid=0(root)`:
> - `NOPASSWD: /usr/bin/vi` → `sudo vi -c ':!id'`
> - `NOPASSWD: /usr/bin/find` → `sudo find /etc/hostname -exec id \;`
>
> Aturan `NOPASSWD: /usr/bin/cat /var/log/*` juga bisa dipakai membaca `/etc/shadow` lewat
> `sudo cat /var/log/../../etc/shadow`, karena `*` di sudoers cocok dengan apa pun, termasuk `/` dan `..`.
> Periksa setiap binary di aturan sudo terhadap GTFOBins. Pakai `sudoedit` untuk mengedit file, dan tag `NOEXEC:`
> sebagai lapisan tambahan (lab: `find: 'id': Permission denied`).
> Sumber: [GTFOBins](https://gtfobins.github.io/), [sudoers(5) — Wildcards](https://www.sudo.ws/docs/man/sudoers.man/)

## 7. UID hygiene: file tanpa pemilik dan UID yang dipakai ulang

```bash
userdel carol                     # home & file lain tetap ada
ls -l /srv/data/carol.txt         # -rw-r--r-- 1 1003 1005 ... (UID angka)
find / -xdev -nouser              # cari sebelum UID dipakai lagi
chown -R --from=1003 fin:finance /srv/data
```

> [!INSIGHT]
> `useradd` memberi UID = UID terbesar yang sedang dipakai + 1. Menghapus user dengan UID tertinggi membuat UID itu
> langsung dipakai user berikutnya. Di lab, `carol` (1003) dihapus, lalu `dave` dibuat dan mendapat `uid=1003`. File
> carol di `/srv/data` kini tampil `dave dave`, dan `find -nouser` tidak lagi menemukannya. Kernel hanya mengenal
> angka. Hal yang sama terjadi pada bind mount Docker: file milik UID 1000 di container tampil sebagai siapa pun
> yang ber-UID 1000 di host.
> Sumber: [useradd(8)](https://man7.org/linux/man-pages/man8/useradd.8.html),
> [userdel(8)](https://man7.org/linux/man-pages/man8/userdel.8.html)

## 8. Ringkasan kebiasaan aman

| Kebiasaan | Kenapa |
|---|---|
| Service berjalan sebagai user sistem `nologin` | Kompromi service ≠ shell login |
| `usermod -aG`, bukan `-G` | `-G` menghapus grup lain |
| Offboarding: `chage -E 0` + cabut key + cabut sudo/docker + putus sesi | `passwd -l` tidak memblokir SSH key |
| Inventaris file (`find -user`) sebelum `userdel` | UID dipakai ulang → user baru memiliki file lama |
| sudoers: path absolut + argumen, tanpa wildcard, tanpa binary GTFOBins | Aturan "sempit" bisa jadi root penuh |
| File di `sudoers.d` tanpa titik, mode 0440, divalidasi `visudo -cf` | Diabaikan diam-diam atau mematikan sudo |
| `sudo -u www-data` untuk perintah aplikasi | File milik root membuat aplikasi error |
| Anggap grup `docker` = root | Bisa mount `/` host ke container |
