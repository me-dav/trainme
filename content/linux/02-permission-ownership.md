---
format: 1
id: linux-permission-ownership
course:
  slug: linux
  title: Linux
  description: Fondasi Linux untuk DevOps/DevSecOps — dari filesystem sampai hardening.
title: Permission & Ownership
summary: Cara Linux memutuskan siapa boleh membaca, menulis, dan mengeksekusi — plus cara troubleshoot "Permission denied" tanpa chmod 777.
order: 2
version: 1
status: verified
updated: '2026-11-19'
tested_on:
  - Ubuntu 24.04.5 LTS — GNU coreutils 9.4, findutils 4.9.0, util-linux 2.39.3, acl, OpenSSH 9.6p1
  - Docker Engine 29.4 (named volume & non-root container)
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: read-mode
    text: Membaca string permission dari `ls -l` dan mengonversinya ke oktal
  - id: dir-semantics
    text: Memahami arti r/w/x pada direktori (berbeda dengan file)
  - id: chmod-symbolic
    text: Mengubah permission dengan mode simbolik (u/g/o/a, +/-/=, X)
  - id: chmod-numeric
    text: Mengubah permission dengan mode oktal
  - id: ownership
    text: Mengubah owner/group dengan chown & chgrp, termasuk aturan siapa yang boleh
  - id: umask
    text: Menghitung permission default dari umask
  - id: special-bits
    text: Memahami setuid, setgid, dan sticky bit
  - id: recursive
    text: Mengubah permission secara rekursif dengan aman
  - id: acl
    text: Memberi akses granular dengan POSIX ACL
  - id: troubleshoot
    text: Mendiagnosis "Permission denied" secara sistematis
  - id: security
    text: Menerapkan permission yang aman untuk SSH, secret, dan container
sources:
  - title: GNU Coreutils Manual — File permissions
    url: https://www.gnu.org/software/coreutils/manual/html_node/File-permissions.html
  - title: GNU Coreutils Manual — chown invocation
    url: https://www.gnu.org/software/coreutils/manual/html_node/chown-invocation.html
  - title: path_resolution(7) — cara kernel mengecek izin
    url: https://man7.org/linux/man-pages/man7/path_resolution.7.html
  - title: chown(2) — kapan bit setuid/setgid dihapus
    url: https://man7.org/linux/man-pages/man2/chown.2.html
  - title: execve(2) — setuid diabaikan pada script
    url: https://man7.org/linux/man-pages/man2/execve.2.html
  - title: acl(5), setfacl(1), getfacl(1)
    url: https://man7.org/linux/man-pages/man5/acl.5.html
  - title: namei(1)
    url: https://man7.org/linux/man-pages/man1/namei.1.html
  - title: chattr(1)
    url: https://man7.org/linux/man-pages/man1/chattr.1.html
  - title: mount(8) — opsi noexec
    url: https://man7.org/linux/man-pages/man8/mount.8.html
  - title: ssh(1) — bagian FILES
    url: https://man.openbsd.org/ssh#FILES
  - title: sshd_config(5) — StrictModes
    url: https://man.openbsd.org/sshd_config#StrictModes
  - title: Debian openssh — user-group-modes.patch
    url: https://sources.debian.org/src/openssh/1%3A10.2p1-3/debian/patches/user-group-modes.patch
  - title: Docker Docs — Linux post-installation steps
    url: https://docs.docker.com/engine/install/linux-postinstall/
  - title: Dockerfile reference — USER dan COPY --chown
    url: https://docs.docker.com/reference/dockerfile/
  - title: Laravel 13 — Deployment (Directory Permissions)
    url: https://laravel.com/docs/13.x/deployment
  - title: Server Fault — Why is "chmod -R 777 /" destructive?
    url: https://serverfault.com/questions/364677/why-is-chmod-r-777-destructive
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: read-mode
    prompt: Sebuah file tampil sebagai `-rwxr-x---` di `ls -l`. Berapa mode oktalnya (3 digit)?
    answers: ['750', '0750']
    tests:
      fail: ['570', '755', '740']
    explanation: 'Owner `rwx` = 4+2+1 = 7, group `r-x` = 4+1 = 5, other `---` = 0. Cek cepat: `stat -c %a file`.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/Numeric-Modes.html

  - id: a02
    tier: A
    type: text
    objective: read-mode
    prompt: Sebuah direktori tampil `drwxr-x--x`. Berapa mode oktalnya (3 digit)?
    answers: ['751', '0751']
    tests:
      fail: ['750', '755']
    explanation: '`d` hanya tipe (direktori), bukan bagian mode. `rwx`=7, `r-x`=5, `--x`=1. Other bisa masuk (traverse) tapi tidak bisa melihat daftar isinya.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/Numeric-Modes.html

  - id: a03
    tier: A
    type: command
    objective: chmod-symbolic
    prompt: Tambahkan izin eksekusi **hanya untuk owner** pada `deploy.sh`, pakai mode simbolik.
    answers: ['chmod u+x deploy.sh']
    patterns: ['^chmod u\+x (\./)?deploy\.sh$']
    tests:
      pass: ['chmod u+x ./deploy.sh', '$ chmod u+x deploy.sh']
      fail: ['chmod +x deploy.sh', 'chmod a+x deploy.sh', 'chmod 744 deploy.sh']
    explanation: '`u` = user (owner). `chmod +x` tanpa huruf siapa akan menambah x untuk semua kelas yang tidak diblokir umask — dengan umask 022 hasilnya x untuk u, g, dan o.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/Symbolic-Modes.html

  - id: a04
    tier: A
    type: command
    objective: chmod-numeric
    prompt: Set `config.ini` menjadi owner baca-tulis, group baca saja, other tanpa akses — pakai mode **oktal**.
    answers: ['chmod 640 config.ini']
    patterns: ['^chmod 0?640 (\./)?config\.ini$']
    tests:
      pass: ['chmod 0640 config.ini']
      fail: ['chmod 644 config.ini', 'chmod 604 config.ini', 'chmod u=rw,g=r,o= config.ini']
    explanation: '`rw-`=6, `r--`=4, `---`=0 → 640. Pola umum untuk file config yang dibaca service lewat group.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/Numeric-Modes.html

  - id: a05
    tier: A
    type: command
    objective: chmod-symbolic
    prompt: Cabut **semua** izin untuk *other* pada `secrets.env` tanpa menyentuh izin owner dan group.
    answers: ['chmod o-rwx secrets.env', 'chmod o= secrets.env']
    patterns: ['^chmod o(=|-(?=[rwx]{3}(?: |$))(?=\S*r)(?=\S*w)(?=\S*x)[rwx]{3}) (\./)?secrets\.env$']
    tests:
      pass: ['chmod o-wrx secrets.env', 'chmod o= ./secrets.env']
      fail: ['chmod 600 secrets.env', 'chmod o-rw secrets.env', 'chmod go-rwx secrets.env']
    explanation: '`o-rwx` dan `o=` sama-sama mengosongkan kelas other. Mode oktal seperti `600` ikut mengubah group, jadi tidak sesuai soal.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/Symbolic-Modes.html

  - id: a06
    tier: A
    type: command
    objective: ownership
    prompt: Ubah owner **dan** group `app.log` menjadi `www-data`.
    answers: ['chown www-data:www-data app.log']
    patterns: ['^chown www-data:(www-data)? (\./)?app\.log$']
    tests:
      pass: ['sudo chown www-data:www-data app.log', 'chown www-data: app.log']
      fail: ['chown www-data app.log', 'chgrp www-data app.log', 'chown www-data.www-data app.log']
    explanation: 'Format `owner:group`. `owner:` (group dikosongkan) mengubah group ke login group user tersebut. Pemisah titik (`www-data.www-data`) masih diterima GNU tapi sudah deprecated — coreutils 9.4 mencetak `warning: ''.'' should be '':''`.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/chown-invocation.html

  - id: a07
    tier: A
    type: command
    objective: ownership
    prompt: Ubah **hanya group** direktori `reports` menjadi `finance`, memakai `chgrp`.
    answers: ['chgrp finance reports']
    patterns: ['^chgrp finance (\./)?reports/?$']
    tests:
      pass: ['chgrp finance reports/']
      fail: ['chown finance reports', 'chgrp -R finance reports']
    explanation: 'Alternatif dengan chown: `chown :finance reports`. Owner file boleh chgrp ke group yang ia anggotai; mengganti owner hanya bisa oleh root (CAP_CHOWN).'
    source: https://man7.org/linux/man-pages/man2/chown.2.html

  - id: a08
    tier: A
    type: command
    objective: troubleshoot
    prompt: Tampilkan UID, GID, dan semua group dari user yang sedang kamu pakai.
    answers: ['id']
    patterns: ['^id( -a)?( (\$USER|\$\(whoami\)))?$']
    tests:
      pass: ['id $USER']
      fail: ['whoami', 'groups']
    explanation: '`id` adalah langkah pertama troubleshooting permission: kernel memakai angka UID/GID dan daftar group proses, bukan nama.'
    source: https://man7.org/linux/man-pages/man7/credentials.7.html

  - id: a09
    tier: A
    type: command
    objective: read-mode
    prompt: Tampilkan permission **direktori** `/var/www` itu sendiri (bukan isinya) dalam format panjang, memakai `ls`.
    answers: ['ls -ld /var/www', 'ls --directory -l /var/www']
    flags_any_order: true
    patterns: ['^ls -(ld|dl) /var/www/$']
    tests:
      pass: ['ls -dl /var/www', 'ls -l -d /var/www']
      fail: ['ls -l /var/www', 'ls -la /var/www']
    explanation: 'Tanpa `-d`, `ls -l` menampilkan isi direktori. Saat debugging, permission direktori induk sering jadi penyebab — selalu cek dengan `-d`.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/Which-files-are-listed.html

  - id: a10
    tier: A
    type: text
    objective: umask
    prompt: Dengan `umask 022`, berapa permission default file baru yang dibuat `touch`? (oktal)
    answers: ['644', '0644']
    tests:
      fail: ['755', '022']
    explanation: 'File dibuat dengan mode dasar 666 lalu bit di umask dimatikan: 666 & ~022 = 644. Direktori memakai dasar 777 → 755.'
    source: https://man7.org/linux/man-pages/man2/umask.2.html

  - id: a11
    tier: A
    type: text
    objective: umask
    prompt: Dengan `umask 027`, berapa permission default **direktori** baru dari `mkdir`? (oktal)
    answers: ['750', '0750']
    tests:
      fail: ['640', '755']
    explanation: '777 & ~027 = 750. File baru akan jadi 640. Umask 027 umum dipakai untuk service agar "other" tidak bisa membaca apa pun.'
    source: https://man7.org/linux/man-pages/man2/umask.2.html

  - id: a12
    tier: A
    type: command
    objective: umask
    prompt: Tampilkan umask saat ini dalam format simbolik (mis. `u=rwx,g=rx,o=rx`).
    answers: ['umask -S']
    tests:
      fail: ['umask']
    explanation: '`umask` tanpa opsi menampilkan oktal (`0022`). `-S` menampilkan izin yang *diizinkan*, bukan yang dimatikan — lebih mudah dibaca.'
    source: https://www.gnu.org/software/bash/manual/html_node/Bourne-Shell-Builtins.html

  - id: a13
    tier: A
    type: text
    objective: special-bits
    prompt: '`ls -ld /tmp` menampilkan `drwxrwxrwt`. Huruf `t` di posisi terakhir menandakan bit apa?'
    answers: ['sticky', 'sticky bit']
    tests:
      pass: ['Sticky Bit']
      fail: ['setuid', 'setgid']
    explanation: 'Sticky bit (1000) pada direktori: hanya owner file, owner direktori, atau root yang boleh menghapus/rename file di dalamnya. Karena itu /tmp bisa 1777 tanpa user saling hapus file.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/Mode-Structure.html

  - id: a14
    tier: A
    type: text
    objective: dir-semantics
    prompt: 'Kamu bisa menghapus file read-only (444) milik user lain asalkan punya izin `w` dan `x` pada ... ? (jawab singkat)'
    answers: ['direktori', 'direktorinya', 'directory', 'direktori induk', 'parent directory']
    patterns: ['^(direktori( induk| parent|nya)?|folder(nya)?|(parent )?directory|parent dir)$']
    tests:
      pass: ['Direktori induk', 'folder']
      fail: ['file', 'filenya']
    explanation: 'Menghapus = mengubah isi direktori (daftar entri), jadi yang dicek adalah `w`+`x` pada direktori, bukan permission file. Pengecualiannya sticky bit.'
    source: https://man7.org/linux/man-pages/man7/path_resolution.7.html

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: command
    objective: recursive
    prompt: Set semua **direktori** di bawah `/srv/app` (termasuk `/srv/app`) ke `755` memakai `find`, tanpa menyentuh file.
    answers: ['find /srv/app -type d -exec chmod 755 {} +']
    patterns:
      - '^find /srv/app/? -type d -exec chmod 0?755 \{\} (\+|;)$'
      - '^find /srv/app/? -type d -print0 \| xargs -0 chmod 0?755$'
    tests:
      pass: ['find /srv/app -type d -exec chmod 755 ''{}'' \;', 'find /srv/app -type d -print0 | xargs -0 chmod 755']
      fail: ['chmod -R 755 /srv/app', 'find /srv/app -type f -exec chmod 755 {} +']
    explanation: '`{} +` mengumpulkan banyak path per eksekusi (lebih cepat dari `{} \;`). Pasangannya untuk file: `find /srv/app -type f -exec chmod 644 {} +`. `chmod -R 755` salah karena membuat semua file ikut executable.'
    source: https://man7.org/linux/man-pages/man1/find.1.html

  - id: b02
    tier: B
    type: command
    objective: recursive
    prompt: |
      Dalam **satu** perintah `chmod` rekursif pada `/srv/app`:
      owner `rw`, group & other `r`, dan `x` **hanya** untuk direktori (atau file yang memang sudah executable).
    answers: ['chmod -R u=rwX,go=rX /srv/app']
    flags_any_order: true
    patterns: ['^chmod (-R|--recursive) (u=rwX,go=rX|u=rwX,og=rX|u=rwX,g=rX,o=rX|go=rX,u=rwX|a=rX,u\+w) /srv/app/?$']
    tests:
      pass: ['chmod -R u=rwX,g=rX,o=rX /srv/app', 'chmod --recursive a=rX,u+w /srv/app', 'chmod u=rwX,go=rX -R /srv/app']
      fail: ['chmod -R 755 /srv/app', 'chmod -R u=rwx,go=rx /srv/app']
    explanation: '`X` besar = beri x hanya jika target direktori atau sudah punya x untuk salah satu kelas. Hasil di lab: direktori 755, file biasa 644, script yang sudah executable tetap 755.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/Conditional-Executability.html

  - id: b03
    tier: B
    type: command
    objective: special-bits
    prompt: Aktifkan **setgid** pada direktori `/srv/shared` (tanpa mengubah bit lain) agar file baru mewarisi group direktori.
    answers: ['chmod g+s /srv/shared']
    patterns: ['^chmod g\+s /srv/shared/?$']
    tests:
      fail: ['chmod 2775 /srv/shared', 'chmod u+s /srv/shared', 'chmod +t /srv/shared']
    explanation: 'Mode oktal `2775` ikut menimpa bit lain. Tanpa setgid, file baru mendapat primary group pembuatnya — sumber klasik masalah "file teman satu tim tidak bisa diedit".'
    source: https://www.gnu.org/software/coreutils/manual/html_node/Directory-Setuid-and-Setgid.html

  - id: b04
    tier: B
    type: command
    objective: security
    prompt: Cari semua file **regular** yang punya bit SUID di seluruh filesystem (`/`), sembunyikan pesan error.
    answers: ['find / -type f -perm -4000 2>/dev/null']
    patterns: ['^find / (?:-xdev )?(?:-type f (?:-xdev )?-perm [-/](?:0?4000|u[=+]s)|-perm [-/](?:0?4000|u[=+]s) (?:-xdev )?-type f)(?: -xdev)? 2>/dev/null$']
    tests:
      pass: ['find / -perm -4000 -type f 2>/dev/null', 'find / -xdev -type f -perm /4000 2> /dev/null', 'find / -type f -perm -u=s 2>/dev/null']
      fail: ['find / -perm -4000 2>/dev/null', 'find / -type f -perm 4000 2>/dev/null', 'find / -type f -perm -2000 2>/dev/null']
    explanation: '`-perm -4000` = minimal bit 4000 ada; `-perm 4000` tanpa tanda berarti mode *persis* 4000 (hampir tidak pernah cocok). Audit SUID adalah langkah standar hardening — binary SUID yang tidak dikenal adalah jalur privilege escalation.'
    source: https://man7.org/linux/man-pages/man1/find.1.html

  - id: b05
    tier: B
    type: command
    objective: security
    prompt: Cari file regular **world-writable** (other punya `w`) di bawah `/srv`.
    answers: ['find /srv -type f -perm -0002']
    patterns: ['^find /srv/? (?:-type f -perm [-/](?:0{0,3}2|o[=+]w)|-perm [-/](?:0{0,3}2|o[=+]w) -type f)$']
    tests:
      pass: ['find /srv -type f -perm -o=w', 'find /srv -perm -2 -type f']
      fail: ['find /srv -type f -perm 777', 'find /srv -type d -perm -0002']
    explanation: 'Di lab, `-perm -0002`, `-perm -o=w`, dan `-perm -o+w` memberi hasil yang sama. File world-writable di direktori aplikasi = siapa pun di server bisa menyuntikkan kode.'
    source: https://man7.org/linux/man-pages/man1/find.1.html

  - id: b06
    tier: B
    type: command
    objective: acl
    prompt: Beri user `alice` izin `rwx` pada direktori `/srv/data` lewat **ACL**, tanpa mengubah owner, group, atau mode dasar.
    answers: ['setfacl -m u:alice:rwx /srv/data']
    patterns: ['^setfacl (?:-m |--modify[= ])(?:u|user):alice:rwx /srv/data/?$']
    tests:
      pass: ['setfacl --modify=user:alice:rwx /srv/data', 'setfacl -m user:alice:rwx /srv/data']
      fail: ['chmod 777 /srv/data', 'chown alice /srv/data', 'setfacl -x u:alice /srv/data']
    explanation: 'Setelah ada ACL, `ls -l` menampilkan tanda `+` di ujung mode (`drwxrwxr-x+`). Perhatikan entri `mask::` — ia membatasi izin efektif semua entri ACL named user/group.'
    source: https://man7.org/linux/man-pages/man1/setfacl.1.html

  - id: b07
    tier: B
    type: command
    objective: acl
    prompt: Tampilkan ACL lengkap dari `/srv/data`.
    answers: ['getfacl /srv/data']
    patterns: ['^getfacl (-p )?/srv/data/?$']
    tests:
      fail: ['ls -l /srv/data', 'setfacl /srv/data']
    explanation: 'Kalau `ls -l` menunjukkan `+`, mode 9 karakter tidak menceritakan semuanya — selalu cek `getfacl`.'
    source: https://man7.org/linux/man-pages/man1/getfacl.1.html

  - id: b08
    tier: B
    type: command
    objective: acl
    prompt: Buat **default ACL** di `/srv/share` supaya file/direktori baru otomatis memberi group `devs` akses `rw`, plus `x` hanya untuk direktori.
    answers: ['setfacl -d -m g:devs:rwX /srv/share']
    patterns: ['^setfacl (?:(?:-d|--default) (?:-m|--modify)|-dm|--default --modify) (?:g|group):devs:rwX /srv/share/?$']
    tests:
      pass: ['setfacl -dm g:devs:rwX /srv/share', 'setfacl --default --modify group:devs:rwX /srv/share']
      fail: ['setfacl -m g:devs:rwX /srv/share', 'setfacl -md g:devs:rwX /srv/share', 'setfacl -d -m g:devs:rwx /srv/share']
    explanation: 'Default ACL hanya berlaku di direktori dan diwariskan ke item baru. Urutan opsi penting: `-md` gagal (`Option -m incomplete`) karena `-m` langsung butuh argumen.'
    source: https://man7.org/linux/man-pages/man1/setfacl.1.html

  - id: b09
    tier: B
    type: text
    objective: umask
    prompt: Dengan `umask 023`, berapa permission default file baru? (Petunjuk — umask bukan pengurangan.)
    answers: ['644', '0644']
    tests:
      fail: ['643', '754']
    explanation: '666 & ~023: other 6 (rw-) & ~3 (-wx) = 4 (r--). Hasil 644, bukan 643. Untuk direktori: 754. Terbukti di lab Ubuntu 24.04.'
    source: https://man7.org/linux/man-pages/man2/umask.2.html

  - id: b10
    tier: B
    type: command
    objective: troubleshoot
    prompt: Tampilkan owner, group, dan mode **setiap komponen path** `/srv/app/storage/logs/laravel.log` dalam satu perintah.
    answers: ['namei -l /srv/app/storage/logs/laravel.log', 'namei --long /srv/app/storage/logs/laravel.log', 'namei -mo /srv/app/storage/logs/laravel.log']
    flags_any_order: true
    tests:
      pass: ['namei -om /srv/app/storage/logs/laravel.log']
      fail: ['ls -l /srv/app/storage/logs/laravel.log', 'namei /srv/app/storage/logs/laravel.log']
    explanation: 'File bisa 666 tapi tetap tidak bisa diakses kalau salah satu direktori di path tidak memberi `x`. `namei -l` memperlihatkan seluruh rantai sekaligus.'
    source: https://man7.org/linux/man-pages/man1/namei.1.html

  - id: b11
    tier: B
    type: command
    objective: troubleshoot
    prompt: Buktikan apakah user `www-data` benar-benar bisa membuat file di `/srv/app/storage`, dengan membuat file `/srv/app/storage/.probe` **sebagai** user tersebut.
    answers: ['sudo -u www-data touch /srv/app/storage/.probe']
    patterns:
      - '^sudo (?:-u www-data|--user=www-data|--user www-data) touch /srv/app/storage/\.probe$'
      - '^runuser -u www-data (?:-- )?touch /srv/app/storage/\.probe$'
    tests:
      pass: ['sudo --user=www-data touch /srv/app/storage/.probe', 'runuser -u www-data -- touch /srv/app/storage/.probe']
      fail: ['touch /srv/app/storage/.probe', 'sudo touch /srv/app/storage/.probe']
    explanation: 'Menguji sebagai root tidak membuktikan apa-apa — root melewati hampir semua cek DAC. Uji dengan identitas proses yang sebenarnya.'
    source: https://www.sudo.ws/docs/man/sudo.man/

  - id: b12
    tier: B
    type: command
    objective: troubleshoot
    prompt: Tampilkan atribut filesystem khusus (mis. flag immutable `i`) dari file `config.yml`.
    answers: ['lsattr config.yml']
    patterns: ['^lsattr (\./)?config\.yml$']
    tests:
      fail: ['ls -l config.yml', 'stat config.yml', 'chattr config.yml']
    explanation: 'File dengan `chattr +i` tidak bisa diubah, dihapus, atau di-chmod — bahkan oleh root (`Operation not permitted`). Mode-nya terlihat normal di `ls -l`, jadi mudah terlewat.'
    source: https://man7.org/linux/man-pages/man1/chattr.1.html

  - id: b13
    tier: B
    type: text
    objective: special-bits
    prompt: Di GNU coreutils, setelah `chmod 2775 dir` lalu `chmod 755 dir`, berapa hasil `stat -c %a dir`?
    answers: ['2755']
    tests:
      fail: ['755', '0755']
    explanation: 'GNU chmod **mempertahankan** setuid/setgid direktori untuk mode oktal ≤4 digit. Untuk menghapusnya pakai `chmod g-s dir` atau 5 digit `chmod 00755 dir`. Terbukti di lab (2755 → 755 hanya dengan 00755).'
    source: https://www.gnu.org/software/coreutils/manual/html_node/Directory-Setuid-and-Setgid.html

  - id: b14
    tier: B
    type: command
    objective: ownership
    prompt: Sebagai root, ubah owner `/srv/app` **beserta seluruh isinya** menjadi user `deploy` dan group `www-data`.
    answers: ['chown -R deploy:www-data /srv/app']
    flags_any_order: true
    patterns: ['^chown (?:-R|--recursive) deploy:www-data /srv/app/?$']
    tests:
      pass: ['chown --recursive deploy:www-data /srv/app', 'chown deploy:www-data -R /srv/app']
      fail: ['chown deploy:www-data /srv/app', 'chmod -R 777 /srv/app']
    explanation: 'Pola umum deploy: kode milik user deploy (bukan milik web server), web server cukup membaca lewat group. Catatan: chown menghapus bit setuid/setgid pada file executable — terbukti di lab (4755 → 755).'
    source: https://www.gnu.org/software/coreutils/manual/html_node/chown-invocation.html

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: troubleshoot
    prompt: |
      Setelah deploy, halaman Laravel error 500. Kode di-deploy oleh user `deploy`, PHP-FPM berjalan sebagai `www-data`.
      Tulis perintah diagnosis dan perbaikan (satu perintah per baris) supaya `www-data` bisa menulis ke
      `storage` dan `bootstrap/cache` **tanpa** membuat seluruh project writable.
    context: |
      $ tail -n 2 /srv/app/storage/logs/laravel.log
      tail: cannot open '/srv/app/storage/logs/laravel.log' for reading: No such file or directory
      $ sudo tail -n 1 /var/log/nginx/error.log
      ... PHP message: PHP Fatal error: Uncaught UnexpectedValueException: The stream or file "/srv/app/storage/logs/laravel.log" could not be opened in append mode: Failed to open stream: Permission denied
      $ ls -ld /srv/app /srv/app/storage /srv/app/storage/logs
      drwxr-xr-x 12 root root 4096 Nov 19 10:00 /srv/app
      drwxr-xr-x  5 root root 4096 Nov 19 10:00 /srv/app/storage
      drwxr-xr-x  2 root root 4096 Nov 19 10:00 /srv/app/storage/logs
    checklist:
      - id: diagnose
        label: Memeriksa permission sepanjang path (namei / ls -ld / stat)
        patterns: ['namei\s+-\S*[lmo]', 'ls\s+-\S*d\S*\s+.*storage', 'stat\s+.*storage']
      - id: give-access
        label: Memberi akses ke www-data hanya pada storage/bootstrap/cache (chown/chgrp atau setfacl)
        required: true
        patterns: ['(chown|chgrp)\s+(-\S+\s+)*\S*www-data\S*\s+.*storage', 'setfacl\s+.*www-data.*storage']
      - id: write-bit
        label: Memastikan izin tulis untuk group/ACL (g+w, ug+rwX, 2775, atau ACL rwX)
        required: true
        patterns: ['chmod\s+(-\S+\s+)*\S*(g\+w|ug\+rw|g=rwx|g\+rwx|ug=rwx|2?775)', 'setfacl\s+.*www-data:rw']
      - id: bootstrap-cache
        label: Ikut menangani bootstrap/cache
        patterns: ['bootstrap/cache']
      - id: verify
        label: Memverifikasi sebagai www-data (sudo -u www-data ...)
        patterns: ['sudo\s+(-u\s*|--user[= ])www-data', 'runuser\s+-u\s+www-data']
      - id: no-777
        label: Tidak memakai 777 / world-writable
        forbid: true
        patterns: ['chmod\s+(-\S+\s+)*0?777\b', 'chmod\s+(-\S+\s+)*\S*(a\+w|o\+w|a\+rwx|o=rwx)']
      - id: no-whole-project
        label: Tidak menyerahkan seluruh /srv/app ke www-data
        forbid: true
        patterns: ['chown\s+(-\S+\s+)*www-data(:\S*)?\s+/srv/app/?\s*$']
    solution: |
      ```bash
      namei -l /srv/app/storage/logs/laravel.log          # cari komponen yang menolak
      chown -R deploy:www-data /srv/app/storage /srv/app/bootstrap/cache
      chmod -R ug+rwX /srv/app/storage /srv/app/bootstrap/cache
      find /srv/app/storage /srv/app/bootstrap/cache -type d -exec chmod g+s {} +
      sudo -u www-data touch /srv/app/storage/logs/.probe && sudo -u www-data rm /srv/app/storage/logs/.probe
      ```
      Kode tetap milik `deploy`/`root` dan read-only bagi web server; hanya dua direktori yang memang
      wajib writable menurut dokumentasi Laravel. `g+s` membuat file baru tetap ber-group `www-data`.
      Alternatif tanpa ubah owner: `setfacl -R -m u:www-data:rwX ...` + `setfacl -R -d -m u:www-data:rwX ...`.
      (Kedua varian diuji di Ubuntu 24.04.)
    tests:
      pass:
        - |
          namei -l /srv/app/storage/logs/laravel.log
          chown -R deploy:www-data /srv/app/storage /srv/app/bootstrap/cache
          chmod -R ug+rwX /srv/app/storage /srv/app/bootstrap/cache
          sudo -u www-data touch /srv/app/storage/logs/.probe
        - |
          ls -ld /srv/app/storage /srv/app/storage/logs
          setfacl -R -m u:www-data:rwX /srv/app/storage /srv/app/bootstrap/cache
          setfacl -R -d -m u:www-data:rwX /srv/app/storage /srv/app/bootstrap/cache
          sudo -u www-data touch /srv/app/storage/logs/test
      fail:
        - chmod -R 777 /srv/app/storage
        - |
          chown -R www-data /srv/app
          chmod -R 775 /srv/app
        - |
          chown -R deploy:www-data /srv/app/storage
          chmod -R 777 /srv/app/storage
    source: https://laravel.com/docs/13.x/deployment

  - id: c02
    tier: C
    type: scenario
    objective: security
    prompt: |
      Kamu menjalankan `ssh -i ~/.ssh/id_ed25519 deploy@10.0.0.5` dari laptop dan mendapat output di bawah.
      Tulis perintah untuk memperbaikinya di sisi **klien**, lalu cara memastikan hasilnya.
    context: |
      @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
      @         WARNING: UNPROTECTED PRIVATE KEY FILE!          @
      @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
      Permissions 0644 for '/home/dev/.ssh/id_ed25519' are too open.
      It is required that your private key files are NOT accessible by others.
      This private key will be ignored.
      Load key "/home/dev/.ssh/id_ed25519": bad permissions
      deploy@10.0.0.5: Permission denied (publickey).
    checklist:
      - id: key-600
        label: Private key dibuat hanya bisa diakses owner (600/400 atau go-rwx)
        required: true
        patterns: ['chmod\s+(0?600|0?400|go-rwx|og-rwx|go=|u=rw,go=|u=rw,g=,o=|g-r,o-r)\s+\S*id_ed25519(?!\.pub)\b']
      - id: dir-700
        label: Memastikan direktori ~/.ssh 700
        patterns: ['chmod\s+(0?700|go-rwx|u=rwx,go=)\s+\S*\.ssh/?(\s|$)']
      - id: check
        label: Mengecek ulang permission (ls -l / stat)
        patterns: ['ls\s+-\S*l\S*\s+.*\.ssh', 'stat\s+.*(\.ssh|id_ed25519)']
      - id: retry
        label: Mencoba koneksi lagi (idealnya dengan -v)
        patterns: ['ssh\s+.*deploy@']
      - id: no-open
        label: Tidak membuka permission lebih lebar
        forbid: true
        patterns: ['chmod\s+(-\S+\s+)*0?[0-7]?7[0-7]7\b', 'chmod\s+(-\S+\s+)*\S*(a\+r|o\+r)']
    solution: |
      ```bash
      chmod 600 ~/.ssh/id_ed25519
      chmod 700 ~/.ssh
      ls -l ~/.ssh
      ssh -v -i ~/.ssh/id_ed25519 deploy@10.0.0.5
      ```
      ssh(1): klien **mengabaikan** private key yang bisa diakses user lain. File `.pub` boleh 644.
      Di lab, pesan di atas muncul persis untuk key 0644 dan login berhasil setelah `chmod 600`.
    tests:
      pass:
        - |
          chmod 600 ~/.ssh/id_ed25519
          chmod 700 ~/.ssh
          ls -l ~/.ssh
          ssh -v -i ~/.ssh/id_ed25519 deploy@10.0.0.5
        - |
          chmod go-rwx ~/.ssh/id_ed25519
          stat -c %a ~/.ssh/id_ed25519
          ssh -i ~/.ssh/id_ed25519 deploy@10.0.0.5
      fail:
        - chmod 600 ~/.ssh/id_ed25519.pub
        - |
          chmod 777 ~/.ssh/id_ed25519
          ssh deploy@10.0.0.5
    source: https://man.openbsd.org/ssh#FILES

  - id: c03
    tier: C
    type: scenario
    objective: security
    prompt: |
      Di sisi klien semuanya benar, tapi login key ke server tetap ditolak. Kamu punya akses root via console.
      Tulis perintah perbaikan di **server** tanpa mematikan `StrictModes`.
    context: |
      # journalctl -u ssh -n 3
      Nov 19 10:31:02 web01 sshd[2211]: Authentication refused: bad ownership or modes for directory /home/deploy
      # ls -ld /home/deploy /home/deploy/.ssh /home/deploy/.ssh/authorized_keys
      drwxrwxr-x 6 deploy devs   4096 Nov 19 10:02 /home/deploy
      drwx------ 2 deploy deploy 4096 Nov 19 10:02 /home/deploy/.ssh
      -rw------- 1 deploy deploy  101 Nov 19 10:02 /home/deploy/.ssh/authorized_keys
      # getent group devs
      devs:x:1005:deploy,alice,bob
    checklist:
      - id: fix-home
        label: Menghapus izin tulis group/other di home directory
        required: true
        patterns: ['chmod\s+(0?755|0?750|0?700|g-w|go-w|g-w,o-w|o-w,g-w|u=rwx,go=rx|u=rwx,g=rx,o=)\s+/home/deploy/?(\s|$)']
      - id: check-ssh
        label: Memeriksa ~/.ssh dan authorized_keys juga (owner & mode)
        patterns: ['ls\s+-\S*l\S*.*\.ssh', 'namei\s+-\S*\s+.*authorized_keys', 'stat\s+.*\.ssh', 'chmod\s+\S+\s+.*\.ssh']
      - id: verify
        label: Menguji ulang login / melihat log
        patterns: ['journalctl', 'auth\.log', 'ssh\s+.*deploy@', 'sshd\s+-T']
      - id: keep-strict
        label: Tidak mematikan StrictModes
        forbid: true
        patterns: ['strictmodes\s+no']
      - id: no-777
        label: Tidak memakai 777
        forbid: true
        patterns: ['chmod\s+(-\S+\s+)*0?777\b']
    solution: |
      ```bash
      chmod 755 /home/deploy          # atau 750 / g-w
      ls -ld /home/deploy/.ssh /home/deploy/.ssh/authorized_keys
      journalctl -u ssh -f            # lalu coba login lagi dari klien
      ```
      sshd dengan `StrictModes yes` (default) menolak key bila home, `~/.ssh`, atau `authorized_keys`
      bisa ditulis orang lain. **Catatan Debian/Ubuntu:** patch `user-group-modes` mengizinkan group-writable
      bila group tersebut **hanya berisi user itu sendiri**. Di sini group `devs` punya anggota lain, jadi ditolak
      — persis yang kami reproduksi di lab OpenSSH 9.6p1 Ubuntu.
    tests:
      pass:
        - |
          chmod g-w /home/deploy
          ls -ld /home/deploy/.ssh /home/deploy/.ssh/authorized_keys
          journalctl -u ssh -f
        - |
          chmod 750 /home/deploy
          stat -c '%a %U:%G' /home/deploy/.ssh /home/deploy/.ssh/authorized_keys
          ssh deploy@10.0.0.5
      fail:
        - |
          echo "StrictModes no" >> /etc/ssh/sshd_config
          systemctl restart ssh
        - chmod 777 /home/deploy
    source: https://man.openbsd.org/sshd_config#StrictModes

  - id: c04
    tier: C
    type: scenario
    objective: troubleshoot
    prompt: |
      Script vendor sudah executable, tapi tetap "Permission denied". Diagnosis penyebabnya dan jalankan script
      dengan aman **tanpa** mengubah opsi mount `/tmp` di server production.
    context: |
      $ ls -l /tmp/install.sh
      -rwxr-xr-x 1 ops ops 2048 Nov 19 10:12 /tmp/install.sh
      $ /tmp/install.sh
      -bash: /tmp/install.sh: Permission denied
      $ id
      uid=1000(ops) gid=1000(ops) groups=1000(ops),27(sudo)
    checklist:
      - id: check-mount
        label: Memeriksa opsi mount (findmnt / mount / /proc/mounts) → noexec
        required: true
        patterns: ['findmnt', '^\s*mount\s*(\|.*)?$', '/proc/(self/)?mounts', '/proc/self/mountinfo']
      - id: read-first
        label: Membaca isi script dulu sebelum dijalankan
        patterns: ['(less|cat|head|more|vim?|nano|view)\s+\S*install\.sh']
      - id: run-safely
        label: Menjalankan lewat interpreter atau memindahkan ke lokasi lain
        required: true
        patterns: ['(^|\s)(ba|da|z)?sh\s+\S*install\.sh', '(mv|cp|install)\s+(-\S+\s+)*\S*install\.sh\s+\S+']
      - id: no-remount
        label: Tidak me-remount /tmp dengan exec
        forbid: true
        patterns: ['remount,\s*exec', 'exec,\s*remount']
    solution: |
      ```bash
      findmnt -T /tmp                 # OPTIONS mengandung noexec
      less /tmp/install.sh            # baca dulu isinya
      bash /tmp/install.sh            # interpreter tidak terhalang noexec
      ```
      `noexec` melarang eksekusi **langsung**; bit `x` tidak berarti apa-apa di mount itu. Di lab, `./install.sh`
      gagal dengan exit code 126 sementara `sh install.sh` berhasil. Artinya `noexec` adalah lapisan pertahanan
      tambahan, **bukan** pengaman yang kuat — interpreter tetap bisa menjalankan isinya.
    tests:
      pass:
        - |
          findmnt -T /tmp
          less /tmp/install.sh
          bash /tmp/install.sh
        - |
          mount | grep /tmp
          cp /tmp/install.sh ~/install.sh
          ~/install.sh
      fail:
        - |
          chmod +x /tmp/install.sh
          /tmp/install.sh
        - |
          findmnt -T /tmp
          mount -o remount,exec /tmp
          /tmp/install.sh
    source: https://man7.org/linux/man-pages/man8/mount.8.html

  - id: c05
    tier: C
    type: scenario
    objective: security
    prompt: |
      Container `worker` (image kamu sendiri, `USER app` dengan UID 1000) crash karena tidak bisa menulis ke
      named volume `/data`. Perbaiki **tanpa** menjalankan container sebagai root secara permanen dan tanpa 777.
      Tulis perintah dan/atau baris Dockerfile/compose yang kamu pakai.
    context: |
      $ docker compose logs worker | tail -1
      worker-1  | PermissionError: [Errno 13] Permission denied: '/data/cache.db'
      $ docker compose run --rm worker id
      uid=1000(app) gid=1000(app) groups=1000(app)
      $ docker compose run --rm worker ls -ldn /data
      drwxr-xr-x 2 0 0 4096 Nov 19 10:20 /data
    checklist:
      - id: compare-ids
        label: Membandingkan UID proses dengan UID pemilik (angka)
        patterns: ['(^|\s)id(\s|$)', 'ls\s+-\S*n', 'stat\s+.*%u']
      - id: fix-owner
        label: Menyamakan owner /data dengan UID 1000 (chown sekali jalan atau di Dockerfile)
        required: true
        patterns: ['chown\s+(-\S+\s+)*(1000|app)(:(1000|app))?\s+\S*/data', 'copy\s+--chown=']
      - id: persist
        label: Perbaikan permanen di image (RUN mkdir/chown sebelum USER) agar volume baru langsung benar
        patterns: ['^\s*run\s+.*chown', 'copy\s+--chown']
      - id: no-root
        label: Tidak menjalankan service sebagai root secara permanen
        forbid: true
        patterns: ['^\s*user:\s*["'']?(root|0)\b', '^\s*user\s+(root|0)\s*$']
      - id: no-777
        label: Tidak memakai 777
        forbid: true
        patterns: ['chmod\s+(-\S+\s+)*0?777\b']
    solution: |
      Perbaikan permanen di Dockerfile (sebelum `USER`):
      ```dockerfile
      RUN groupadd -g 1000 app && useradd -u 1000 -g app -M app \
          && mkdir /data && chown app:app /data
      USER app
      ```
      Volume **baru** yang kosong disalin dari isi image termasuk ownership-nya — di lab `/data` langsung
      milik UID aplikasi dan `touch` berhasil. Untuk volume lama yang sudah terlanjur milik root, sekali jalan:
      ```bash
      docker compose run --rm --user root worker chown -R 1000:1000 /data
      ```
      Kunci konsepnya: kernel membandingkan **angka** UID/GID. Nama `app` di container dan user di host
      tidak ada hubungannya — cek dengan `ls -n`.
    tests:
      pass:
        - |
          docker compose run --rm worker id
          docker compose run --rm --user root worker chown -R 1000:1000 /data
          docker compose up -d worker
        - |
          # Dockerfile
          RUN mkdir /data && chown app:app /data
          USER app
      fail:
        - |
          # compose.yaml
          user: root
        - docker compose run --rm --user root worker chmod -R 777 /data
    source: https://docs.docker.com/reference/dockerfile/#user
---

# Permission & Ownership

Hampir setiap insiden "aplikasi tidak bisa menulis log", "deploy gagal", atau "SSH key ditolak" berujung pada
satu hal: **siapa** prosesnya, **milik siapa** file-nya, dan **izin apa** yang berlaku di setiap direktori
sepanjang path. Modul ini membahas model permission klasik Linux (DAC), ACL, bit khusus, dan cara
troubleshoot yang benar — bukan `chmod 777`.

> [!NOTE]
> Semua contoh output dan klaim perilaku di modul ini dijalankan ulang di Ubuntu 24.04 (GNU coreutils 9.4,
> OpenSSH 9.6p1) dan Docker Engine 29. Distro lain (Alpine/BusyBox, RHEL) bisa berbeda di detail kecil.

## 1. Membaca output `ls -l`

```text
-rwxr-x--- 1 deploy www-data 1234 Nov 19 10:00 deploy.sh
drwxr-xr-x 2 root   root     4096 Nov 19 10:00 public
```

| Bagian | Contoh | Arti |
|---|---|---|
| Karakter ke-1 | `-` / `d` / `l` | Tipe: file biasa, direktori, symlink (juga `c`, `b`, `s`, `p`) |
| Karakter 2–4 | `rwx` | Izin **user** (owner) |
| Karakter 5–7 | `r-x` | Izin **group** |
| Karakter 8–10 | `---` | Izin **other** (semua yang lain) |
| Penanda ekstra | `+` / `.` | Ada ACL (`+`) atau konteks SELinux (`.`) |
| Kolom 3 & 4 | `deploy www-data` | Owner dan group |

Konversi ke oktal: `r`=4, `w`=2, `x`=1, dijumlahkan per kelas. `rwxr-x---` → 7 5 0 → **750**.

```bash
stat -c '%a %A %U:%G %n' deploy.sh
# 750 -rwxr-x--- deploy:www-data deploy.sh
```

### Bagaimana kernel memutuskan

```mermaid
flowchart TD
    A[Proses mengakses file] --> R{UID efektif = 0<br/>atau punya capability?}
    R -- ya --> RB[Hampir semua cek DAC dilewati<br/>eksekusi tetap butuh minimal satu bit x]
    R -- tidak --> O{UID proses = owner file?}
    O -- ya --> OU[Pakai bit USER saja]
    O -- tidak --> G{GID proses atau salah satu<br/>supplementary group = group file?}
    G -- ya --> GU[Pakai bit GROUP saja]
    G -- tidak --> OT[Pakai bit OTHER]
```

> [!INSIGHT]
> Hanya **satu** kelas yang dipakai, dan owner dicek pertama. File `-r--rwx---` milik `alice:devs`:
> anggota `devs` lain bisa menulis, tetapi `alice` sendiri **tidak** — bit group tidak "ditambahkan" ke owner.
> Kami reproduksi di lab: owner mendapat `Permission denied`, anggota group berhasil.
> Sumber: [path_resolution(7)](https://man7.org/linux/man-pages/man7/path_resolution.7.html)

## 2. `r`, `w`, `x` pada file vs direktori

| Bit | Pada file | Pada direktori |
|---|---|---|
| `r` | Membaca isi | Melihat **daftar nama** entri (`ls`) |
| `w` | Mengubah isi | Membuat, menghapus, rename entri di dalamnya |
| `x` | Menjalankan | **Masuk/melewati** (`cd`, akses entri yang namanya diketahui) |

Konsekuensi yang sering mengejutkan (semuanya terbukti di lab):

- Direktori `r` tanpa `x`: `ls` bisa menampilkan nama, tetapi `ls -l` menampilkan `-????????? ? ? ?` dan
  file tidak bisa dibuka.
- Direktori `x` tanpa `r`: `ls` gagal, tetapi `cat dir/file` **berhasil** kalau kamu tahu namanya.
- Menghapus file tergantung `w`+`x` pada **direktori**, bukan pada file. File 444 milik orang lain bisa
  terhapus — kecuali direktorinya ber-sticky bit.

## 3. `chmod` mode simbolik

Format: `[siapa][operator][izin]` — siapa: `u` `g` `o` `a`; operator: `+` `-` `=`.

```bash
chmod u+x deploy.sh               # tambah x untuk owner
chmod g-w,o-rwx secrets.env       # beberapa klausa, pisahkan dengan koma
chmod u=rw,g=r,o= config.ini      # set persis (o= berarti kosong)
chmod -R u=rwX,go=rX /srv/app     # X besar: x hanya untuk direktori / yang sudah executable
```

> [!INSIGHT]
> Tanpa huruf "siapa", chmod menghormati umask. Dengan `umask 022`, `chmod +w` pada file 444 menghasilkan
> **644** (hanya owner), sedangkan `chmod a+w` menghasilkan 666. `chmod +x` pada file 600 menjadi 711.
> Sumber: [GNU Coreutils — Symbolic Modes](https://www.gnu.org/software/coreutils/manual/html_node/Symbolic-Modes.html)

## 4. `chmod` mode oktal

| Oktal | Simbolik | Pemakaian umum |
|---|---|---|
| `755` | `rwxr-xr-x` | Direktori publik, binary/script |
| `644` | `rw-r--r--` | File biasa, public key |
| `750` / `640` | `rwxr-x---` / `rw-r-----` | Aplikasi/config yang dibaca service lewat group |
| `700` / `600` | `rwx------` / `rw-------` | `~/.ssh`, private key, file secret |
| `2775` | `rwxrwsr-x` | Direktori kolaborasi tim (setgid) |
| `1777` | `rwxrwxrwt` | `/tmp` (sticky) |

Digit keempat di depan adalah bit khusus: 4 = setuid, 2 = setgid, 1 = sticky.

## 5. Ownership: `chown` dan `chgrp`

```bash
chown deploy file                  # ganti owner
chown deploy:www-data file         # owner dan group
chown :www-data file               # hanya group (sama dengan chgrp www-data file)
chown -R deploy:www-data /srv/app  # rekursif
ls -ln file                        # tampilkan UID/GID angka
```

- Hanya root (CAP_CHOWN) yang bisa mengganti owner. Owner file boleh `chgrp` ke group yang ia anggotai.
- `chown` **menghapus bit setuid/setgid** pada file executable — di lab, file 4755 menjadi 755 setelah
  di-chown oleh root. Set ulang bit khusus *setelah* chown.
- Pemisah titik (`user.group`) sudah deprecated; coreutils 9.4 mencetak peringatan.

> [!INSIGHT]
> Kernel hanya mengenal **angka**. Di container, `app` (UID 1000) dan user `ubuntu` (UID 1000) di image lain
> adalah "orang yang sama" bagi filesystem. Selalu bandingkan dengan `id` dan `ls -n` saat debugging volume.
> Sumber: [credentials(7)](https://man7.org/linux/man-pages/man7/credentials.7.html)

## 6. `umask` — permission default

File baru dibuat dengan dasar `666`, direktori `777`, lalu bit yang ada di umask **dimatikan** (AND NOT,
bukan pengurangan).

| umask | File baru | Direktori baru |
|---|---|---|
| `022` | 644 | 755 |
| `027` | 640 | 750 |
| `077` | 600 | 700 |
| `002` | 664 | 775 |
| `023` | 644 (bukan 643!) | 754 |

```bash
umask        # 0022
umask -S     # u=rwx,g=rx,o=rx
```

> [!TIP]
> Untuk service systemd, set umask di unit (`UMask=0027`), bukan di `.bashrc` — service tidak membaca
> profil shell. Sumber: [systemd.exec(5)](https://www.freedesktop.org/software/systemd/man/latest/systemd.exec.html)

## 7. Bit khusus: setuid, setgid, sticky

| Bit | Pada file | Pada direktori | Tampilan |
|---|---|---|---|
| setuid (4000) | Program berjalan sebagai **owner** file (contoh `/usr/bin/passwd`) | — | `rws` (`rwS` bila tanpa x) |
| setgid (2000) | Berjalan sebagai group file | File baru **mewarisi group** direktori | `rws` di kolom group |
| sticky (1000) | — | Hanya owner file/direktori/root yang boleh hapus | `t` di akhir |

> [!INSIGHT]
> Linux **mengabaikan** setuid pada script (`#!/bin/sh`). Di lab, script 4755 milik root tetap mencetak UID
> pemanggil. Setuid hanya bermakna untuk binary.
> Sumber: [execve(2)](https://man7.org/linux/man-pages/man2/execve.2.html)

> [!INSIGHT]
> GNU chmod **tidak menghapus** setgid direktori lewat mode oktal biasa: `chmod 755 dir` pada direktori 2775
> menghasilkan **2755**. Untuk benar-benar mencabut, pakai `chmod g-s dir` atau `chmod 00755 dir`.
> Sumber: [GNU Coreutils — Directories and the Set-User-ID and Set-Group-ID Bits](https://www.gnu.org/software/coreutils/manual/html_node/Directory-Setuid-and-Setgid.html)

## 8. Mengubah permission secara rekursif dengan aman

```bash
# Direktori 755, file 644 — dua langkah eksplisit
find /srv/app -type d -exec chmod 755 {} +
find /srv/app -type f -exec chmod 644 {} +

# Atau satu langkah dengan X besar
chmod -R u=rwX,go=rX /srv/app
```

> [!WARNING]
> `chmod -R 777` membuat **semua** file bisa ditulis siapa pun (injeksi kode) dan semua file jadi executable.
> Pada `/`, perintah ini ikut merusak setuid/setgid sistem sehingga `sudo`/`su` tidak berfungsi — diskusi
> klasiknya ada di [Server Fault #364677](https://serverfault.com/questions/364677/why-is-chmod-r-777-destructive).
> Pemulihan yang realistis: restore dari backup.

> [!INSIGHT]
> Untuk Laravel, dokumentasi resminya hanya meminta `storage` dan `bootstrap/cache` writable oleh proses
> web server — bukan seluruh project. Pola yang kami uji: kode milik `deploy`, dua direktori itu
> `deploy:www-data` + `ug+rwX` + setgid.
> Sumber: [Laravel 13 — Deployment](https://laravel.com/docs/13.x/deployment)

## 9. ACL — saat owner/group/other tidak cukup

```bash
setfacl -m u:alice:rwx /srv/data        # tambah entri user
setfacl -d -m g:devs:rwX /srv/share     # default ACL: diwariskan ke item baru
getfacl /srv/data                       # lihat semua entri
setfacl -x u:alice /srv/data            # hapus satu entri
setfacl -b /srv/data                    # hapus semua ACL
```

Output `getfacl` di lab:

```text
# file: srv/data
# owner: root
# group: root
user::rwx
user:alice:rwx
group::r-x
mask::rwx
other::r-x
```

> [!INSIGHT]
> Entri `mask::` adalah batas atas izin semua named user/group. Di lab, file baru di direktori ber-default-ACL
> mendapat `group:devs:rwx #effective:rw-` karena mask-nya `rw-`. Kalau ACL "tidak berpengaruh", cek mask dulu.
> Sumber: [acl(5)](https://man7.org/linux/man-pages/man5/acl.5.html)

## 10. Troubleshooting "Permission denied" — sistematis

```mermaid
flowchart TD
    S[Permission denied] --> W[1. Siapa prosesnya?<br/>id, ps -o user,group]
    W --> P[2. Cek seluruh path<br/>namei -l /path/ke/file]
    P --> F[3. Cek file/dir target<br/>stat, ls -ld]
    F --> A[4. Ada ACL? tanda + → getfacl]
    A --> I[5. Atribut khusus? lsattr → i immutable]
    I --> M[6. Opsi mount? findmnt -T path → ro / noexec]
    M --> X[7. MAC? SELinux/AppArmor → audit log]
    X --> T[8. Uji sebagai user itu<br/>sudo -u www-data touch ...]
    T --> Z[Perbaiki dengan least privilege:<br/>group, setgid, ACL — bukan 777]
```

```bash
id www-data
namei -l /srv/app/storage/logs/laravel.log
getfacl /srv/app/storage
lsattr /etc/app/config.yml
findmnt -T /tmp
sudo -u www-data touch /srv/app/storage/.probe
```

Contoh `namei -l` di lab — langsung terlihat `storage` adalah penghalangnya:

```text
f: /srv/app/storage/logs/laravel.log
drwxr-xr-x root root /
drwxr-xr-x root root srv
drwxr-xr-x root root app
drwx------ root root storage
drwxr-xr-x root root logs
-rw-r--r-- root root laravel.log
```

> [!INSIGHT]
> Setelah `usermod -aG docker user`, sesi yang sudah terbuka **tidak** langsung mendapat group baru —
> daftar group ditetapkan saat login. Di lab, `id -nG` pada proses lama tetap tanpa group baru, sedangkan sesi
> baru langsung punya. Dokumentasi Docker meminta logout/login atau `newgrp docker`.
> Sumber: [Docker Docs — Linux post-installation steps](https://docs.docker.com/engine/install/linux-postinstall/)

> [!INSIGHT]
> `chattr +i` membuat file kebal bahkan untuk root: `chmod`, `rm`, dan menulis semuanya gagal dengan
> `Operation not permitted`, padahal `ls -l` terlihat normal. Biasanya dipasang tim security atau
> tool hardening — cek `lsattr` sebelum menyimpulkan "permission rusak".
> Sumber: [chattr(1)](https://man7.org/linux/man-pages/man1/chattr.1.html)

## 11. Checklist keamanan (DevSecOps)

| Target | Permission | Kenapa |
|---|---|---|
| `~/.ssh` | 700 | sshd StrictModes & mencegah orang lain menaruh key |
| Private key | 600 | Klien ssh **mengabaikan** key yang terbuka bagi orang lain |
| `authorized_keys` | 600 (644 masih diterima) | Tidak boleh writable oleh group/other |
| `.env` / file secret | 600 atau 640 `app:app` | Tidak terbaca user lain di server |
| `/etc/shadow` | 640 `root:shadow` (Ubuntu) | Hash password |
| Kode aplikasi | milik user deploy, read-only bagi web server | Kompromi aplikasi tidak bisa menulis ulang kode |
| Container | `USER` non-root + `COPY --chown` | Membatasi dampak kompromi |

```bash
find / -xdev -type f -perm -4000 2>/dev/null   # audit binary SUID
find /srv -type f -perm -0002                  # file world-writable
```

> [!INSIGHT]
> Debian/Ubuntu menambal OpenSSH (`user-group-modes.patch`) sehingga home/`~/.ssh`/`authorized_keys` yang
> group-writable **tetap diterima** jika group itu hanya berisi user tersebut. Di lab, `chmod 775 /home/dev`
> tetap bisa login; begitu group-nya punya anggota lain, sshd menolak dengan
> `Authentication refused: bad ownership or modes`. Distro lain tanpa patch ini akan langsung menolak.
> Sumber: [Debian openssh — user-group-modes.patch](https://sources.debian.org/src/openssh/1%3A10.2p1-3/debian/patches/user-group-modes.patch)

> [!INSIGHT]
> Named volume Docker yang **baru dan kosong** diisi dari isi image di path tersebut, termasuk ownership-nya.
> Jadi `RUN mkdir /data && chown app:app /data` sebelum `USER app` membuat volume baru langsung writable.
> Volume lama yang sudah milik root tidak ikut berubah — perlu `chown` sekali jalan.
> Sumber: [Docker Docs — Populate a volume using a container](https://docs.docker.com/engine/storage/volumes/#populate-a-volume-using-a-container)
> (diverifikasi di lab Docker 29)
