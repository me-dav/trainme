---
format: 1
id: linux-filesystem-navigation
course:
  slug: linux
  title: Linux
  description: Fondasi Linux untuk DevOps/DevSecOps — dari filesystem sampai hardening.
title: Filesystem & Navigasi
summary: Peta direktori Linux, path, operasi file yang aman, inode & link, find, serta cara troubleshoot "disk penuh" yang tidak terlihat oleh du.
order: 1
version: 1
status: verified
updated: '2026-11-20'
tested_on:
  - Ubuntu 24.04.5 LTS — GNU coreutils 9.4, findutils 4.9.0, util-linux 2.39.3, lsof 4.95.0, file 5.45, bash 5.2
  - Docker Engine 29.4 (container biasa & --privileged untuk uji mount)
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: fhs
    text: Mengenali fungsi direktori utama menurut FHS
  - id: paths
    text: Memakai path absolut/relatif, `.`, `..`, `~`, `cd -`, dan `pwd -P`
  - id: listing
    text: Membaca isi direktori dengan opsi `ls` yang tepat
  - id: file-ops
    text: Membuat, menyalin, memindah, dan me-rename file dengan aman
  - id: links
    text: Membedakan inode, hard link, dan symbolic link, termasuk mengganti symlink deploy
  - id: inspect
    text: Membaca metadata file dengan `stat`, `file`, `readlink`
  - id: find
    text: Mencari file berdasarkan nama, tipe, waktu, dan ukuran dengan `find`
  - id: disk
    text: Mengukur pemakaian disk dan inode dengan `df` dan `du`
  - id: glob-quote
    text: Memahami globbing, quoting, dan batas panjang argumen
  - id: safety
    text: Menghapus file tanpa kecelakaan (`--`, `--preserve-root`, `${VAR:?}`)
  - id: troubleshoot
    text: Mendiagnosis "No space left on device" secara sistematis
sources:
  - title: Filesystem Hierarchy Standard 3.0
    url: https://refspecs.linuxfoundation.org/FHS_3.0/fhs/index.html
  - title: hier(7) — deskripsi hierarki filesystem
    url: https://man7.org/linux/man-pages/man7/hier.7.html
  - title: path_resolution(7)
    url: https://man7.org/linux/man-pages/man7/path_resolution.7.html
  - title: inode(7)
    url: https://man7.org/linux/man-pages/man7/inode.7.html
  - title: symlink(7)
    url: https://man7.org/linux/man-pages/man7/symlink.7.html
  - title: unlink(2) — file tetap ada selama masih dibuka
    url: https://man7.org/linux/man-pages/man2/unlink.2.html
  - title: rename(2) — penggantian nama atomik
    url: https://man7.org/linux/man-pages/man2/rename.2.html
  - title: execve(2) — E2BIG / batas argumen
    url: https://man7.org/linux/man-pages/man2/execve.2.html
  - title: proc(5) — /proc/PID/fd
    url: https://man7.org/linux/man-pages/man5/proc.5.html
  - title: mount(8) — isi direktori mount point tersembunyi
    url: https://man7.org/linux/man-pages/man8/mount.8.html
  - title: GNU Coreutils Manual — ls, cp, mv, rm, ln, stat, readlink, df, du
    url: https://www.gnu.org/software/coreutils/manual/coreutils.html
  - title: GNU Findutils Manual
    url: https://www.gnu.org/software/findutils/manual/html_mono/find.html
  - title: find(1)
    url: https://man7.org/linux/man-pages/man1/find.1.html
  - title: GNU Bash Manual — Quoting & Filename Expansion
    url: https://www.gnu.org/software/bash/manual/html_node/Filename-Expansion.html
  - title: lsof (lsof-org)
    url: https://github.com/lsof-org/lsof
  - title: Steam for Linux issue #3671 — skrip menghapus seluruh file user
    url: https://github.com/valvesoftware/steam-for-linux/issues/3671
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: fhs
    prompt: Menurut FHS, di direktori mana file **konfigurasi** sistem yang khusus untuk host ini disimpan (mis. `nginx.conf`, `fstab`)?
    answers: ['/etc', '/etc/', 'etc']
    tests:
      fail: ['/usr', '/var', '/opt', '/etc/conf']
    explanation: '`/etc` = konfigurasi host, bukan program. Biner ada di `/usr/bin`, data yang berubah-ubah di `/var`.'
    source: https://refspecs.linuxfoundation.org/FHS_3.0/fhs/ch03s07.html

  - id: a02
    tier: A
    type: text
    objective: fhs
    prompt: Di direktori mana (path lengkap) log sistem dan banyak service tradisional ditulis?
    answers: ['/var/log', '/var/log/']
    tests:
      fail: ['/var', '/log', '/tmp/log', '/usr/log']
    explanation: '`/var` = data variabel (log, cache, spool, state). Log ada di `/var/log`. Di sistem systemd, journal persisten juga di `/var/log/journal`.'
    source: https://refspecs.linuxfoundation.org/FHS_3.0/fhs/ch05s10.html

  - id: a03
    tier: A
    type: command
    objective: paths
    prompt: Kamu baru saja `cd /etc` lalu `cd /tmp`. Kembali ke direktori **sebelumnya** (`/etc`) dengan perintah terpendek.
    answers: ['cd -']
    tests:
      fail: ['cd ..', 'cd ~', 'cd', 'cd /']
    explanation: '`cd -` pindah ke `$OLDPWD` dan mencetak path tujuannya. `cd ..` naik satu level (ke `/`), `cd` tanpa argumen ke home.'
    source: https://www.gnu.org/software/bash/manual/html_node/Bourne-Shell-Builtins.html

  - id: a04
    tier: A
    type: command
    objective: listing
    prompt: Tampilkan isi direktori saat ini **termasuk file tersembunyi**, format panjang, ukuran mudah dibaca (K/M/G).
    answers: ['ls -lah', 'ls -lAh']
    flags_any_order: true
    tests:
      pass: ['ls -alh', 'ls -l -a -h', 'ls -hal']
      fail: ['ls -lh', 'ls -la', 'ls -a']
    explanation: '`-l` format panjang, `-a` termasuk dotfile (`.env`, `.git`), `-h` human-readable. `-A` sama seperti `-a` tapi tanpa `.` dan `..`.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/ls-invocation.html

  - id: a05
    tier: A
    type: command
    objective: listing
    prompt: Tampilkan permission & owner **direktori** `/srv/app` itu sendiri — bukan daftar isinya.
    answers: ['ls -ld /srv/app']
    flags_any_order: true
    patterns: ['^ls -(ld|dl) /srv/app/?$']
    tests:
      pass: ['ls -dl /srv/app', 'ls -l -d /srv/app', 'ls -ld /srv/app/']
      fail: ['ls -l /srv/app', 'ls -la /srv/app', 'ls -d /srv/app']
    explanation: 'Tanpa `-d`, `ls -l dir` menampilkan isi direktori. `-d` memperlakukan direktori sebagai entri tunggal. Alternatif: `stat /srv/app`.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/Which-files-are-listed.html

  - id: a06
    tier: A
    type: command
    objective: file-ops
    prompt: Buat direktori `/srv/app/releases/v1` sekaligus dengan semua parent yang belum ada, dalam satu perintah.
    answers: ['mkdir -p /srv/app/releases/v1']
    patterns: ['^mkdir (-p|--parents|-pv|-vp|-p -v|-v -p) /srv/app/releases/v1/?$']
    tests:
      pass: ['mkdir --parents /srv/app/releases/v1', 'mkdir -pv /srv/app/releases/v1/']
      fail: ['mkdir /srv/app/releases/v1', 'mkdir -r /srv/app/releases/v1']
    explanation: 'Tanpa `-p`: `mkdir: cannot create directory ''/srv/app/releases/v1'': No such file or directory`. Dengan `-p`, perintah juga tidak error bila direktori sudah ada — aman dipakai di skrip.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/mkdir-invocation.html

  - id: a07
    tier: A
    type: command
    objective: file-ops
    prompt: |
      Salin direktori `config` beserta isinya ke `/backup/config` (belum ada), dengan **mempertahankan**
      permission, ownership, timestamp, dan symlink.
    answers: ['cp -a config /backup/config']
    patterns: ['^cp (-a|--archive|-av|-va) (\./)?config/? /backup/config/?$']
    tests:
      pass: ['cp --archive config /backup/config', 'cp -av ./config /backup/config']
      fail: ['cp -r config /backup/config', 'cp config /backup/config', 'mv config /backup/config']
    explanation: '`-a` = `-dR --preserve=all`. `-r` saja menyalin rekursif tapi owner/timestamp jadi milik yang menyalin. Hati-hati: kalau `/backup/config` **sudah ada**, hasilnya `/backup/config/config` (diuji di lab).'
    source: https://www.gnu.org/software/coreutils/manual/html_node/cp-invocation.html

  - id: a08
    tier: A
    type: command
    objective: links
    prompt: Buat symbolic link bernama `current` yang menunjuk ke `releases/v2` (di direktori saat ini, `current` belum ada).
    answers: ['ln -s releases/v2 current']
    patterns: ['^ln (-s|--symbolic|-s[fnTv]+|-[fnTv]+s[fnTv]*) (\./)?releases/v2/? current$']
    tests:
      pass: ['ln --symbolic releases/v2 current', 'ln -sfn releases/v2 current']
      fail: ['ln releases/v2 current', 'ln -s current releases/v2']
    explanation: 'Urutannya sama seperti `cp`: **target dulu, nama link belakangan** (`ln -s TARGET LINK`). Tanpa `-s` jadi hard link — dan hard link ke direktori tidak diizinkan.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/ln-invocation.html

  - id: a09
    tier: A
    type: text
    objective: links
    prompt: Setelah `ln data.txt backup.txt` (hard link), kamu menjalankan `rm data.txt`. Apakah isi file masih bisa dibaca lewat `backup.txt`? (ya/tidak)
    answers: ['ya', 'yes', 'bisa', 'masih']
    tests:
      fail: ['tidak', 'no', 'tidak bisa']
    explanation: 'Hard link = nama lain untuk **inode yang sama**. `rm` hanya menghapus satu nama dan menurunkan link count (di lab: 2 → 1). Data baru dibebaskan saat link count 0 **dan** tidak ada proses yang masih membukanya.'
    source: https://man7.org/linux/man-pages/man2/unlink.2.html

  - id: a10
    tier: A
    type: command
    objective: find
    prompt: Cari semua **file** (bukan direktori) berakhiran `.log` di bawah `/var/log`.
    answers: ['find /var/log -type f -name ''*.log''']
    patterns: ['^find /var/log/? (-type f -name \*\.log|-name \*\.log -type f)$']
    tests:
      pass: ['find /var/log -name "*.log" -type f', 'find /var/log/ -type f -name \*.log']
      fail: ['find /var/log -name *.log', 'find /var/log -type d -name *.log', 'ls /var/log/*.log']
    explanation: 'Kutip pola (`''*.log''`) supaya yang mengekspansi adalah `find`, bukan shell. Tanpa kutip, kalau ada `x.log` di direktori saat ini, shell akan mengganti pola menjadi `x.log`.'
    source: https://www.gnu.org/software/findutils/manual/html_mono/find.html#Shell-Pattern-Matching

  - id: a11
    tier: A
    type: command
    objective: inspect
    prompt: Tampilkan metadata lengkap `app.conf` — inode, ukuran, jumlah link, permission, dan timestamp Access/Modify/Change.
    answers: ['stat app.conf']
    patterns: ['^stat (\./)?app\.conf$']
    tests:
      fail: ['ls -l app.conf', 'file app.conf', 'ls -i app.conf']
    explanation: '`stat` menampilkan semua field inode. Format khusus: `stat -c ''%a %U:%G %s %n'' app.conf`.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/stat-invocation.html

  - id: a12
    tier: A
    type: command
    objective: disk
    prompt: Tampilkan sisa ruang semua filesystem yang ter-mount, dengan ukuran mudah dibaca.
    answers: ['df -h']
    patterns: ['^df (-h|--human-readable)$']
    tests:
      fail: ['du -h', 'df', 'df -i']
    explanation: '`df` bertanya ke filesystem (berapa blok terpakai), `du` menjumlahkan file yang bisa ia temukan. Perbedaan keduanya adalah petunjuk troubleshooting yang penting.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/df-invocation.html

  - id: a13
    tier: A
    type: command
    objective: disk
    prompt: Tampilkan **total** ukuran `/var/log` dalam satu baris, human-readable.
    answers: ['du -sh /var/log']
    flags_any_order: true
    patterns: ['^du (-sh|-hs|-s -h|-h -s|--summarize --human-readable|--human-readable --summarize) /var/log/?$']
    tests:
      pass: ['du -hs /var/log', 'du -s -h /var/log/']
      fail: ['du -h /var/log', 'df -h /var/log', 'ls -lh /var/log']
    explanation: '`-s` (summarize) hanya mencetak total, tanpa itu `du` mencetak setiap subdirektori.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/du-invocation.html

  - id: a14
    tier: A
    type: text
    objective: fhs
    prompt: Filesystem virtual mana yang berisi direktori per-PID dan file seperti `cpuinfo` dan `meminfo`?
    answers: ['/proc', '/proc/', 'proc', 'procfs']
    tests:
      fail: ['/sys', '/dev', '/run', '/var']
    explanation: '`/proc` dibuat kernel di memori: tidak memakan disk. `/sys` (sysfs) berisi device & driver, `/dev` berisi device node, `/run` berisi state runtime (PID file, socket).'
    source: https://man7.org/linux/man-pages/man5/proc.5.html

  - id: a15
    tier: A
    type: command
    objective: paths
    prompt: Kamu masuk lewat symlink (`cd /srv/app/current`). Tampilkan path **fisik** direktori saat ini, dengan symlink sudah di-resolve.
    answers: ['pwd -P']
    patterns: ['^(pwd -P|realpath \.|readlink (-f|-e) \.)$']
    tests:
      pass: ['realpath .', 'readlink -f .']
      fail: ['pwd', 'pwd -L']
    explanation: 'Di lab, setelah `cd link`: `pwd` → `/lab/link`, `pwd -P` → `/lab/real`. Berguna untuk memastikan release mana yang benar-benar aktif.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/pwd-invocation.html

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: command
    objective: find
    prompt: Cari file di bawah `/var/log` yang ukurannya **lebih dari 100 MiB**.
    answers: ['find /var/log -type f -size +100M']
    patterns: ['^find /var/log/? (-type f -size \+100M|-size \+100M -type f|-size \+100M)$']
    tests:
      pass: ['find /var/log -size +100M', 'find /var/log/ -size +100M -type f']
      fail: ['find /var/log -size 100M', 'find /var/log -size -100M', 'find /var/log -type f -size +100m', 'du -sh /var/log']
    explanation: '`+N` = lebih dari, `-N` = kurang dari, `N` = tepat. Satuan peka huruf besar: `M`, `G`, `k`. Di lab, `-size +100m` ditolak: `find: invalid -size type `m''`.'
    source: https://man7.org/linux/man-pages/man1/find.1.html

  - id: b02
    tier: B
    type: command
    objective: find
    prompt: |
      Hapus file `*.log` di `/var/log/app` yang terakhir diubah **lebih dari 7 hari** lalu, dalam satu perintah
      `find` (pakai aksi `-delete` atau `-exec rm`).
    answers: ['find /var/log/app -type f -name ''*.log'' -mtime +7 -delete']
    patterns:
      - '^find /var/log/app/? (?=.*-name \*\.log )(?=.*-mtime \+7 )((-type f|-name \*\.log|-mtime \+7) ){2,3}(-delete|-exec rm (-f )?\{\} (\+|;))$'
    tests:
      pass:
        - 'find /var/log/app -name "*.log" -mtime +7 -type f -delete'
        - 'find /var/log/app -type f -mtime +7 -name *.log -exec rm {} +'
        - 'find /var/log/app -name ''*.log'' -mtime +7 -exec rm -f {} \;'
      fail:
        - 'find /var/log/app -delete -name *.log -mtime +7'
        - 'find /var/log/app -name *.log -mtime -7 -delete'
        - 'find /var/log/app -name *.log -mtime 7 -delete'
        - 'rm /var/log/app/*.log'
    explanation: '**`-delete` harus di akhir.** Ekspresi `find` dievaluasi kiri ke kanan: `find dir -delete -name ...` menghapus *semuanya*, termasuk `dir` itu sendiri (direproduksi di lab). `-mtime +7` = umur ≥ 8×24 jam, karena pecahan hari dibuang.'
    source: https://man7.org/linux/man-pages/man1/find.1.html

  - id: b03
    tier: B
    type: command
    objective: links
    prompt: |
      `current` adalah symlink ke direktori `releases/v1`. Arahkan ulang `current` ke `releases/v2` dalam satu perintah
      `ln`, tanpa membuat link nyasar **di dalam** `releases/v1`.
    answers: ['ln -sfn releases/v2 current']
    flags_any_order: true
    patterns: ['^ln -(?=[sfnTv]*s)(?=[sfnTv]*f)(?=[sfnTv]*[nT])[sfnTv]{3,4} (\./)?releases/v2/? current$']
    tests:
      pass: ['ln -snf releases/v2 current', 'ln -s -f -n releases/v2 current', 'ln -sfT releases/v2 current']
      fail: ['ln -sf releases/v2 current', 'ln -s releases/v2 current', 'ln -sfn current releases/v2']
    explanation: 'Dengan `ln -sf` saja, `current` di-dereference sebagai direktori, sehingga link baru dibuat di `releases/v1/v2` dan `current` tetap menunjuk v1 (direproduksi di lab). `-n` (atau `-T`) memperlakukan `current` sebagai file biasa.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/ln-invocation.html

  - id: b04
    tier: B
    type: command
    objective: links
    prompt: Dengan `ls`, tampilkan **nomor inode** `a.txt` dan `b.txt` sekaligus untuk mengecek apakah keduanya hard link ke data yang sama.
    answers: ['ls -i a.txt b.txt']
    patterns: ['^ls (-i|--inode|-li|-il|-l -i|-i -l) a\.txt b\.txt$']
    tests:
      pass: ['ls -li a.txt b.txt', 'ls --inode a.txt b.txt']
      fail: ['ls -l a.txt b.txt', 'stat a.txt', 'ls -la a.txt b.txt']
    explanation: 'Nomor inode sama (di filesystem yang sama) = hard link. Kolom ke-2 `ls -l` (link count) juga menunjukkan berapa nama yang dimiliki inode itu.'
    source: https://man7.org/linux/man-pages/man7/inode.7.html

  - id: b05
    tier: B
    type: command
    objective: disk
    prompt: Aplikasi error `No space left on device`, tapi `df -h` hanya 40%. Tampilkan pemakaian **inode** semua filesystem.
    answers: ['df -i']
    patterns: ['^df (-i|--inodes|-ih|-hi|-i -h|-h -i|--inodes -h|-h --inodes)$']
    tests:
      pass: ['df -hi', 'df --inodes']
      fail: ['df -h', 'du --inodes', 'df']
    explanation: 'Setiap file butuh satu inode. Jutaan file kecil (session, cache) bisa menghabiskan inode lebih dulu daripada ruang. Kolom yang dilihat: `IUse%`.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/df-invocation.html

  - id: b06
    tier: B
    type: command
    objective: disk
    prompt: |
      Tampilkan **5 entri terbesar** langsung di bawah `/var`, ukuran human-readable, urut dari yang terbesar.
      Error permission dibuang ke `/dev/null`. Gunakan pipeline `du | sort | head`.
    answers: ['du -sh /var/* 2>/dev/null | sort -rh | head -5']
    patterns:
      - '^du (-sh|-hs|-s -h|-h -s) /var/\* (2>/dev/null )?\| ?sort (-rh|-hr|-r -h|-h -r) ?\| ?head (-5|-n ?5)$'
      - '^du (-h -d ?1|-hd ?1|-d ?1 -h|-h --max-depth=1|--max-depth=1 -h) /var/? (2>/dev/null )?\| ?sort (-rh|-hr|-r -h|-h -r) ?\| ?head (-5|-n ?5|-6|-n ?6)$'
    tests:
      pass:
        - 'du -hs /var/* 2> /dev/null | sort -hr | head -n 5'
        - 'du -h --max-depth=1 /var 2>/dev/null | sort -rh | head -n 5'
      fail:
        - 'du -sh /var/* | sort -r | head -5'
        - 'du -sh /var/* | sort -rn | head -5'
        - 'du -sh /var/* | sort -h | head -5'
    explanation: '`sort -h` memahami sufiks K/M/G. Di lab, `sort -r` dan `sort -rn` sama-sama menaruh `900M` dan `15M` di atas `1.2G`. Varian `--max-depth=1` ikut mencetak baris total `/var` di urutan pertama.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/sort-invocation.html

  - id: b07
    tier: B
    type: command
    objective: troubleshoot
    prompt: '`df` bilang disk penuh, tapi `du` tidak menemukan file sebesar itu. Tampilkan file yang **sudah dihapus tapi masih dibuka** proses (link count < 1) dengan `lsof`.'
    answers: ['lsof +L1']
    patterns:
      - '^lsof (-[nP]+ )*\+L ?1( /\S*)?$'
      - '^lsof( -[nP]+)* ?\| ?grep (-\S+ )*\(?deleted\)?$'
    tests:
      pass: ['lsof -nP +L1', 'lsof +L1 /', 'lsof | grep deleted']
      fail: ['lsof -i', 'du -sh /', 'lsof +L']
    explanation: 'File yang di-`rm` saat masih dibuka tetap memakan blok sampai file descriptor terakhir ditutup. Di lab `lsof +L1` menampilkan `... NLINK 0 ... /var/log/app/app.log (deleted)`. Tanpa lsof: `ls -l /proc/*/fd | grep deleted`.'
    source: https://man7.org/linux/man-pages/man2/unlink.2.html

  - id: b08
    tier: B
    type: command
    objective: safety
    prompt: Ada file yang **benar-benar bernama** `-rf` di direktori saat ini. Hapus file itu saja.
    answers: ['rm -- -rf']
    patterns:
      - '^rm (-[fiv]+ )?(-- )?\./-rf$'
      - '^rm (-[fiv]+ )?-- (\./)?-rf$'
    tests:
      pass: ['rm ./-rf', 'rm -f -- -rf']
      fail: ['rm -rf', 'rm "-rf"', 'rm \-rf']
    explanation: 'Kutip atau backslash tidak membantu: shell membuangnya sebelum `rm` melihat argumen, jadi `rm` tetap membaca `-rf` sebagai opsi. `--` menandai akhir opsi, `./-rf` membuat argumen tidak diawali `-`.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/rm-invocation.html

  - id: b09
    tier: B
    type: text
    objective: glob-quote
    prompt: 'Ada file `laporan akhir.pdf`. Kamu menjalankan `cp laporan akhir.pdf /tmp/` **tanpa tanda kutip**. Berapa banyak path *sumber* yang diterima `cp`?'
    answers: ['2', 'dua']
    tests:
      fail: ['1', '3', 'satu']
    explanation: 'Shell memecah kata di spasi, jadi `cp` menerima `laporan` dan `akhir.pdf` sebagai dua sumber. Di lab: `cannot stat ''laporan''` dan `cannot stat ''akhir.pdf''`. Solusinya `cp "laporan akhir.pdf" /tmp/` — dan di skrip selalu tulis `"$file"`.'
    source: https://www.gnu.org/software/bash/manual/html_node/Word-Splitting.html

  - id: b10
    tier: B
    type: command
    objective: find
    prompt: Cari **symlink rusak** (targetnya sudah tidak ada) di bawah `/etc`.
    answers: ['find /etc -xtype l']
    patterns: ['^find /etc/? -xtype l$', '^find -L /etc/? -type l$']
    tests:
      pass: ['find -L /etc -type l', 'find /etc/ -xtype l']
      fail: ['find /etc -type l', 'ls -l /etc']
    explanation: '`-type l` menampilkan **semua** symlink. `-xtype l` mengecek tipe setelah link diikuti: kalau hasilnya masih "link", berarti targetnya tidak ada. Di lab hanya `broken` yang muncul, `ok` tidak.'
    source: https://man7.org/linux/man-pages/man1/find.1.html

  - id: b11
    tier: B
    type: text
    objective: inspect
    prompt: Kamu menjalankan `chmod 600 t.txt` tanpa mengubah isinya. Timestamp mana di `stat` yang berubah — atime, mtime, atau ctime?
    answers: ['ctime', 'change', 'change time']
    tests:
      fail: ['mtime', 'atime', 'modify', 'birth']
    explanation: '`mtime` = isi berubah, `ctime` = metadata inode berubah (permission, owner, link count — dan juga saat isi berubah). Di lab, setelah `chmod` hanya `Change` yang bergeser. `ctime` **bukan** creation time; itu `Birth`.'
    source: https://man7.org/linux/man-pages/man7/inode.7.html

  - id: b12
    tier: B
    type: command
    objective: find
    prompt: Cari file di `/srv/app` yang isinya diubah dalam **60 menit terakhir**.
    answers: ['find /srv/app -mmin -60']
    patterns: ['^find /srv/app/? (-type f )?-mmin -60( -type f)?$']
    tests:
      pass: ['find /srv/app -type f -mmin -60', 'find /srv/app/ -mmin -60']
      fail: ['find /srv/app -mmin +60', 'find /srv/app -mtime -60', 'find /srv/app -mmin 60', 'find /srv/app -amin -60']
    explanation: '`-mmin` dalam menit, `-mtime` dalam hari (`-mtime -60` = 60 hari). Berguna saat incident response: "file apa yang berubah barusan?"'
    source: https://man7.org/linux/man-pages/man1/find.1.html

  - id: b13
    tier: B
    type: command
    objective: file-ops
    prompt: Rename `config.yml` menjadi `config.yml.bak`, tapi **jangan menimpa** bila `config.yml.bak` sudah ada. Tanpa prompt interaktif.
    answers: ['mv -n config.yml config.yml.bak']
    patterns: ['^mv (-n|--no-clobber|--update=none) config\.yml config\.yml\.bak$']
    tests:
      pass: ['mv --no-clobber config.yml config.yml.bak', 'mv --update=none config.yml config.yml.bak']
      fail: ['mv config.yml config.yml.bak', 'mv -f config.yml config.yml.bak', 'cp -n config.yml config.yml.bak']
    explanation: '`mv` default menimpa tanpa bertanya. Di coreutils 9.4 (lab), `mv -n` menolak dengan `not replacing` dan **exit 1**, sedangkan `--update=none` diam dengan exit 0. `cp -n` memberi peringatan "non-portable". Perhatikan exit code bila dipakai di skrip dengan `set -e`.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/mv-invocation.html

  - id: b14
    tier: B
    type: command
    objective: inspect
    prompt: '`/usr/bin/awk` adalah symlink berantai. Tampilkan path **akhir** setelah semua link di-resolve.'
    answers: ['readlink -f /usr/bin/awk']
    patterns: ['^(readlink (-f|-e|-m|--canonicalize|--canonicalize-existing)|realpath) /usr/bin/awk$']
    tests:
      pass: ['realpath /usr/bin/awk', 'readlink -e /usr/bin/awk']
      fail: ['readlink /usr/bin/awk', 'ls -l /usr/bin/awk', 'file /usr/bin/awk']
    explanation: 'Di Ubuntu 24.04: `readlink` hanya satu langkah → `/etc/alternatives/awk`; `readlink -f` → `/usr/bin/mawk`. Mekanisme `alternatives` Debian memang memakai rantai symlink.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/readlink-invocation.html

  - id: b15
    tier: B
    type: text
    objective: find
    prompt: '`find /data -type f -size -1M` hanya mengembalikan file berukuran berapa?'
    answers: ['0', '0 byte', 'kosong', 'file kosong']
    patterns: ['^(0|nol|0 ?b(yte)?s?|(hanya )?(file )?(yang )?kosong( saja)?)$']
    tests:
      pass: ['0 bytes', 'hanya file kosong', 'nol']
      fail: ['kurang dari 1mb', '1mb', '< 1m', '1048575']
    explanation: 'Ukuran dibulatkan **ke atas** ke satuan yang dipakai: file 1 byte = 1M, jadi "kurang dari 1M" hanya cocok untuk ukuran 0. Di lab, `-size 1M` bahkan ikut mencocokkan direktori 4 KB. Untuk presisi pakai byte: `-size -1048576c`.'
    source: https://man7.org/linux/man-pages/man1/find.1.html

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: troubleshoot
    prompt: |
      Worker antrean (`app-worker.service`) gagal menulis dan disk root penuh. Tadi rekan kerjamu "membersihkan"
      log dengan `rm`, tapi ruang tidak kembali. Tulis perintah (satu per baris) untuk menemukan apa yang
      masih memakan ruang, membebaskannya tanpa reboot, memverifikasi, dan mencegahnya terulang.
    context: |
      $ tail -n 1 /srv/app/storage/logs/worker-error.log
      file_put_contents(): Write of 412 bytes failed with errno=28 No space left on device
      $ df -h /
      Filesystem      Size  Used Avail Use% Mounted on
      /dev/sda1        40G   40G     0 100% /
      $ sudo du -xsh / 2>/dev/null
      21G     /
      $ history | tail -n 2
        311  sudo rm /var/log/app/worker.log
        312  df -h /
    checklist:
      - id: find-deleted
        label: Menemukan file terhapus yang masih dibuka (lsof +L1 / lsof | grep deleted / /proc/*/fd)
        required: true
        patterns: ['lsof\s+.*\+L\s*1', 'lsof.*\|\s*grep\s+(-\S+\s+)*\S*deleted', 'ls\s+-\S*l\S*\s+/proc/\S*fd.*deleted', 'find\s+/proc/.*deleted']
      - id: release
        label: Membebaskan ruang (restart service, atau truncate lewat /proc/PID/fd/N)
        required: true
        patterns: ['systemctl\s+restart\s+\S+', 'service\s+\S+\s+restart', '>\s*/proc/\S+/fd/\S+', 'truncate\s+(-s\s*0|--size[= ]0)\s+/proc/\S+/fd/\S+']
      - id: verify
        label: Memverifikasi ruang kembali (df -h)
        patterns: ['df\s+-\S*h']
      - id: prevent
        label: Pencegahan (logrotate dengan copytruncate/postrotate, atau batas journal)
        patterns: ['logrotate', 'copytruncate', 'postrotate', 'journalctl\s+--vacuum']
      - id: no-wipe
        label: Tidak menghapus seluruh /var/log
        forbid: true
        patterns: ['rm\s+(-\S+\s+)*/var/log/?\*?\s*$', 'rm\s+(-\S+\s+)*/var/log/app/?\*?\s*$']
      - id: no-reboot
        label: Tidak me-reboot server (satu service saja yang memegang file)
        forbid: true
        patterns: ['^(reboot|shutdown|init\s+6)\b']
    solution: |
      ```bash
      lsof -nP +L1                         # cari pemegang file (deleted)
      # php  2214 app  3w  REG  8,1  20401094656  0  ... /var/log/app/worker.log (deleted)
      sudo systemctl restart app-worker    # menutup fd → blok dibebaskan
      # atau tanpa restart: sudo truncate -s 0 /proc/2214/fd/3
      df -h /
      ```
      `rm` hanya menghapus **nama**. Selama proses masih memegang file descriptor, inode dan bloknya tetap
      ada ([unlink(2)](https://man7.org/linux/man-pages/man2/unlink.2.html)) — itu sebabnya `du` (yang
      menjumlahkan nama) 19G lebih kecil dari `df`. Truncate lewat `/proc/PID/fd/N` mengosongkan file tanpa
      menghentikan proses (diuji di lab). Catatan: `sudo : > /proc/...` **tidak** jalan, karena redirect
      dikerjakan shell kamu sendiri, bukan oleh sudo; pakai `sudo truncate -s 0 ...`.
      Pencegahan: rotasi lewat `logrotate` dengan `postrotate` (kirim sinyal/restart) atau `copytruncate`,
      bukan `rm` manual.
    tests:
      pass:
        - |
          lsof +L1
          sudo truncate -s 0 /proc/2214/fd/3
          df -h /
        - |
          sudo lsof -nP +L1 | grep worker
          sudo systemctl restart app-worker
          df -h
          # cegah: logrotate dengan copytruncate
      fail:
        - rm -rf /var/log/*
        - |
          du -sh /var/log
          sudo reboot
        - |
          lsof +L1
          df -h /
    source: https://man7.org/linux/man-pages/man2/unlink.2.html

  - id: c02
    tier: C
    type: scenario
    objective: links
    prompt: |
      Nginx melayani `/srv/app/current/public`. Skrip deploy baru saja menjalankan `ln -sf` untuk release
      `20261120`, tapi situs masih versi lama. Tulis perintah (satu per baris) untuk memeriksa, membersihkan
      sisa kesalahan, mengalihkan `current` dengan benar (idealnya atomik), dan memverifikasi.
    context: |
      $ ls -l /srv/app
      lrwxrwxrwx 1 deploy deploy   26 Nov 19 09:00 current -> /srv/app/releases/20261119
      drwxr-xr-x 4 deploy deploy 4096 Nov 20 09:58 releases
      $ ln -sf /srv/app/releases/20261120 /srv/app/current      # baris dari skrip deploy
      $ readlink /srv/app/current
      /srv/app/releases/20261119
      $ ls -l /srv/app/releases/20261119 | tail -n 1
      lrwxrwxrwx 1 deploy deploy   26 Nov 20 10:01 20261120 -> /srv/app/releases/20261120
    checklist:
      - id: inspect
        label: Memeriksa/memverifikasi target link (readlink, realpath, ls -l, stat)
        patterns: ['(readlink|realpath)\s+(-\S+\s+)?\S*current', 'ls\s+-\S*l\S*\s+\S*(current|20261119)', 'stat\s+\S*current']
      - id: cleanup
        label: Menghapus link nyasar releases/20261119/20261120 (tanpa garis miring di akhir)
        required: true
        patterns: ['^(rm|unlink)\s+(-[fv]+\s+)?\S*20261119/20261120\s*$']
      - id: switch
        label: Mengalihkan current dengan ln -sfn/-sfT atau ln -s + mv -T (atomik)
        required: true
        patterns:
          - '^ln(?=.*\s-\S*[nT])(?=.*\s-\S*s|.*--symbolic)\s+(-\S+\s+)+\S*releases/20261120/?\s+\S*current\s*$'
          - '^mv\s+(-\S*T\S*|--no-target-directory)\s+\S+\s+\S*current\s*$'
      - id: reload
        label: Reload PHP-FPM agar cache realpath tidak memakai path release lama
        patterns: ['systemctl\s+reload\s+(php\S*|nginx)', 'service\s+php\S*\s+reload']
      - id: no-slash-rm
        label: Tidak memakai rm -r pada link dengan garis miring di akhir (ikut menghapus isi target)
        forbid: true
        patterns: ['rm\s+(-\S*r\S*\s+)+\S*20261119/20261120/\s*$']
      - id: no-delete-release
        label: Tidak menghapus release baru atau seluruh releases
        forbid: true
        patterns: ['rm\s+-\S*r\S*\s+\S*releases/20261120/?\s*$', 'rm\s+-\S*r\S*\s+\S*releases/?\s*$']
    solution: |
      ```bash
      readlink /srv/app/current
      rm /srv/app/releases/20261119/20261120             # link nyasar, TANPA "/" di akhir
      ln -s /srv/app/releases/20261120 /srv/app/current.tmp
      mv -T /srv/app/current.tmp /srv/app/current         # rename(2): atomik
      readlink -f /srv/app/current
      sudo systemctl reload php8.3-fpm
      ```
      `ln -sf` mengikuti `current` karena menunjuk direktori, jadi link baru jatuh **di dalam** release lama.
      `ln -sfn` memperbaikinya, tapi di baliknya tetap unlink + symlink (ada jeda singkat tanpa `current`).
      `ln -s` ke nama sementara + `mv -T` memakai [rename(2)](https://man7.org/linux/man-pages/man2/rename.2.html)
      yang atomik: request selalu melihat release lama atau baru, tidak pernah "tidak ada".
      Bahaya yang direproduksi di lab: `rm -r .../20261119/20261120/` (dengan `/`) mengikuti link dan
      **menghapus isi release 20261120**, baru kemudian gagal dengan `Not a directory`.
      Reload PHP-FPM disarankan karena PHP menyimpan hasil resolve path selama `realpath_cache_ttl`
      ([php.net](https://www.php.net/manual/en/ini.core.php#ini.realpath-cache-ttl)); bagian ini tidak kami uji di lab.
    tests:
      pass:
        - |
          readlink /srv/app/current
          rm /srv/app/releases/20261119/20261120
          ln -sfn /srv/app/releases/20261120 /srv/app/current
          readlink -f /srv/app/current
        - |
          ls -l /srv/app/releases/20261119
          unlink /srv/app/releases/20261119/20261120
          ln -s /srv/app/releases/20261120 /srv/app/current.new
          mv -T /srv/app/current.new /srv/app/current
          systemctl reload php8.3-fpm
      fail:
        - ln -sf /srv/app/releases/20261120 /srv/app/current
        - |
          rm -r /srv/app/releases/20261119/20261120/
          ln -sfn /srv/app/releases/20261120 /srv/app/current
          readlink /srv/app/current
        - |
          readlink /srv/app/current
          rm -rf /srv/app/releases/20261120
          ln -sfn /srv/app/releases/20261119 /srv/app/current
    source: https://www.gnu.org/software/coreutils/manual/html_node/ln-invocation.html

  - id: c03
    tier: C
    type: scenario
    objective: troubleshoot
    prompt: |
      Aplikasi Laravel (session driver `file`) tiba-tiba gagal login dengan error di bawah, padahal disk `/srv`
      baru terisi 40%. Tulis perintah (satu per baris) untuk memastikan penyebabnya, menemukan direktori
      biang masalah, membersihkan session **lama** dengan aman, dan satu langkah pencegahan.
    context: |
      file_put_contents(/srv/app/storage/framework/sessions/Q2x9...): Failed to open stream: No space left on device
      $ df -h /srv
      Filesystem      Size  Used Avail Use% Mounted on
      /dev/sdb1        50G   19G   29G  40% /srv
      $ rm /srv/app/storage/framework/sessions/*
      bash: /usr/bin/rm: Argument list too long
    checklist:
      - id: inodes
        label: Memeriksa pemakaian inode (df -i)
        required: true
        patterns: ['df\s+-\S*i']
      - id: locate
        label: Menemukan direktori dengan file terbanyak (du --inodes / find | wc / sort | uniq -c)
        patterns: ['du\s+.*--inodes', 'find\s+.*\|\s*(sort|uniq|wc|cut)', 'ls\s+.*\|\s*wc\s+-l']
      - id: clean
        label: Menghapus session lama dengan find + filter umur (-mmin/-mtime +N) + -delete/-exec rm
        required: true
        patterns: ['find\s+\S*sessions\S*\s+.*-m(time|min)\s+\+\d+.*(-delete|-exec\s+rm|xargs\s+(-\S+\s+)*rm)']
      - id: prevent
        label: Pencegahan (cron/systemd-tmpfiles, lottery, atau pindah ke driver redis/database)
        patterns: ['cron', 'systemd-tmpfiles', 'tmpfiles\.d', 'lottery', 'session_driver', 'redis', 'gc_probability']
      - id: no-glob-rm
        label: Tidak memakai rm dengan glob * (Argument list too long, dan menghapus session yang masih aktif)
        forbid: true
        patterns: ['rm\s+(-\S+\s+)*\S*sessions/\*', 'rm\s+(-\S+\s+)*\S*sessions/?\s*$']
      - id: no-delete-first
        label: Tidak menaruh -delete sebelum filter find
        forbid: true
        patterns: ['find\s+.*-delete\s+.*-(name|m(time|min)|type)\b']
    pass_score: 0.6
    solution: |
      ```bash
      df -i /srv                                            # IUse% 100% → inode habis
      sudo du --inodes -x -d 3 /srv | sort -n | tail -5     # direktori dengan file terbanyak
      find /srv/app/storage/framework/sessions -type f -mmin +120 -delete
      df -i /srv
      ```
      Ruang masih ada, tapi tiap file butuh satu inode, dan jutaan file session kecil menghabiskannya.
      `rm sessions/*` gagal karena glob diekspansi shell menjadi jutaan argumen yang melebihi batas
      `execve` (`E2BIG`). Di lab 200.000 file sudah cukup untuk memicunya. `find -delete` menghapus satu per
      satu tanpa batas itu, dan filter umur (`-mmin +120`, sesuai `SESSION_LIFETIME` default 120 menit)
      menjaga session yang masih aktif. Pencegahan: pastikan pembersihan berjalan (cron/systemd timer),
      atau pindah ke `SESSION_DRIVER=redis`/`database`.
    tests:
      pass:
        - |
          df -i /srv
          sudo du --inodes -x -d 3 /srv | sort -n | tail
          find /srv/app/storage/framework/sessions -type f -mmin +120 -delete
          df -i /srv
        - |
          df -i
          find /srv -xdev -type f | cut -d/ -f2-5 | sort | uniq -c | sort -n | tail
          find /srv/app/storage/framework/sessions -type f -mtime +1 -delete
          # jangka panjang: SESSION_DRIVER=redis
      fail:
        - rm -rf /srv/app/storage/framework/sessions/*
        - |
          df -h
          du -sh /srv/*
        - |
          df -i /srv
          find /srv/app/storage/framework/sessions -delete -mmin +120
    source: https://www.gnu.org/software/coreutils/manual/html_node/df-invocation.html

---

# Filesystem & Navigasi

Semua pekerjaan DevOps berujung di filesystem: membaca config di `/etc`, mengejar log di `/var/log`, mengganti
release lewat symlink, dan — cukup sering — memadamkan alarm "disk penuh". Modul ini membahas peta direktori
Linux, cara bergerak di dalamnya, operasi file yang aman, inode & link, `find`, dan troubleshooting ruang disk.

> [!NOTE]
> Semua contoh output dan klaim perilaku di modul ini dijalankan ulang di Ubuntu 24.04 (GNU coreutils 9.4,
> findutils 4.9.0) di dalam container Docker. Alpine (BusyBox) dan macOS (BSD) punya opsi yang berbeda di
> beberapa tempat, misalnya `du --inodes` dan `find -xtype` tidak ada di semua implementasi.

## 1. Peta filesystem (FHS)

Linux hanya punya **satu pohon** yang berawal di `/`. Disk lain, partisi, `tmpfs`, atau volume network
"ditempelkan" (di-*mount*) ke salah satu direktori di pohon itu.

| Direktori | Isi | Contoh yang sering kamu sentuh |
|---|---|---|
| `/etc` | Konfigurasi host | `nginx/`, `ssh/sshd_config`, `fstab`, `hosts` |
| `/var` | Data yang berubah-ubah | `log/`, `lib/docker/`, `lib/postgresql/`, `cache/` |
| `/usr` | Program & library (read-only secara konsep) | `bin/`, `lib/`, `share/` |
| `/home`, `/root` | Home user biasa dan root | `~/.ssh/`, `~/.bashrc` |
| `/tmp` | File sementara, bisa dibersihkan saat boot | socket, file upload sementara |
| `/opt` | Paket pihak ketiga yang berdiri sendiri | `/opt/vendor-app/` |
| `/srv` | Data yang dilayani server | `/srv/app`, `/srv/www` |
| `/proc`, `/sys` | Filesystem virtual dari kernel | `/proc/cpuinfo`, `/proc/PID/fd` |
| `/dev` | Device node | `/dev/sda`, `/dev/null` |
| `/run` | State runtime sejak boot (tmpfs) | PID file, socket `php-fpm.sock` |

> [!TIP]
> Di Ubuntu 24.04, `/bin`, `/sbin`, dan `/lib` hanyalah symlink ke `/usr/bin`, `/usr/sbin`, `/usr/lib`
> ("usrmerge"). Di lab: `lrwxrwxrwx 1 root root 7 Apr 22 2024 /bin -> usr/bin`. Jadi `/bin/bash` dan
> `/usr/bin/bash` adalah file yang sama.

## 2. Path dan cara bergerak

| Bentuk | Arti |
|---|---|
| `/etc/nginx` | Path **absolut** — selalu dari `/` |
| `nginx/sites-enabled` | Path **relatif** — dari direktori saat ini |
| `.` / `..` | Direktori ini / parent |
| `~` | Home user saat ini (diekspansi oleh shell, bukan oleh kernel) |
| `cd -` | Kembali ke direktori sebelumnya (`$OLDPWD`) |

```bash
cd /etc; cd /tmp; cd -     # mencetak /etc
pwd                        # path logis, bisa mengandung symlink
pwd -P                     # path fisik, symlink di-resolve
```

Di lab, setelah `cd /lab/link` (symlink ke `real`): `pwd` → `/lab/link`, `pwd -P` → `/lab/real`.

> [!WARNING]
> Di skrip dan cron, **selalu pakai path absolut** atau `cd` ke direktori yang pasti terlebih dulu. Cron
> menjalankan job dari home user dengan `PATH` minimal, jadi path relatif yang jalan di terminal sering
> gagal di sana.

## 3. `ls` yang benar-benar berguna

| Opsi | Fungsi |
|---|---|
| `-l` | Format panjang (permission, link count, owner, ukuran, waktu) |
| `-a` / `-A` | Termasuk dotfile (`-A` tanpa `.` dan `..`) |
| `-h` | Ukuran K/M/G |
| `-t` / `-r` | Urut berdasarkan waktu / balik urutan (`ls -ltr`: terbaru di bawah) |
| `-d` | Tampilkan direktori itu sendiri, bukan isinya |
| `-i` | Nomor inode |
| `-S` | Urut berdasarkan ukuran |

```bash
ls -lah /srv/app          # semua isi, termasuk .env
ls -ld /srv/app           # permission direktorinya
ls -ltr /var/log | tail   # log yang paling baru berubah
```

## 4. Membuat, menyalin, memindah, menghapus

```bash
mkdir -p /srv/app/releases/v1      # buat beserta parent, tidak error bila sudah ada
touch app.log                      # buat file kosong / perbarui mtime
cp -a config /backup/config        # salinan "arsip": owner, mode, timestamp, symlink
mv config.yml config.yml.bak       # rename (menimpa tanpa bertanya!)
rm -r build/                       # hapus direktori beserta isinya
```

Perilaku yang terbukti di lab dan sering mengejutkan:

- `cp -a config /backup/config` dua kali → percobaan kedua menghasilkan `/backup/config/config`, karena
  tujuan yang sudah ada dianggap direktori **tempat** menyalin.
- `mv` di filesystem yang sama hanya mengganti nama (cepat, tidak menyalin data). Lintas filesystem, `mv`
  menyalin lalu menghapus.
- `mv -n` di coreutils 9.4 menolak dengan `mv: not replacing 'config.yml.bak'` dan **exit 1**;
  `mv --update=none` diam dan exit 0. Penting kalau skripmu memakai `set -e`.

## 5. Inode, hard link, dan symbolic link

Nama file hanyalah entri di direktori yang menunjuk ke sebuah **inode**. Inode menyimpan metadata (mode,
owner, ukuran, timestamp, lokasi blok), tetapi **tidak menyimpan nama**.

```mermaid
flowchart LR
    A["data.txt"] --> I[(inode 574<br/>links: 2)]
    B["backup.txt<br/>(hard link)"] --> I
    I --> D[blok data]
    S["current<br/>(symlink)"] -. berisi teks path .-> R["releases/v2"]
```

| | Hard link | Symbolic link |
|---|---|---|
| Isinya | Nama lain untuk inode yang sama | File kecil berisi **teks path** target |
| Lintas filesystem | Tidak bisa | Bisa |
| Ke direktori | Tidak diizinkan | Bisa |
| Target dihapus | Data tetap ada (link count turun) | Link jadi rusak (dangling) |
| Dibuat dengan | `ln target nama` | `ln -s target nama` |

```bash
ln data.txt backup.txt
ls -li data.txt backup.txt
# 574 -rw-r--r-- 2 root root 5 ... backup.txt
# 574 -rw-r--r-- 2 root root 5 ... data.txt
rm data.txt; cat backup.txt      # masih "data", link count jadi 1
```

### Mengganti symlink release dengan benar

Pola deploy "release per direktori + symlink `current`" dipakai banyak tool deploy. Jebakannya ada di `ln`:

```bash
ln -s releases/v1 current
ln -sf releases/v2 current      # SALAH: current masih → v1, muncul link releases/v1/v2
ln -sfn releases/v2 current     # benar: -n = jangan ikuti current
ln -s releases/v2 current.tmp && mv -T current.tmp current   # benar dan atomik
```

`mv -T` memakai `rename(2)`, yang mengganti nama secara atomik. Tidak ada jeda ketika `current` tidak ada,
sedangkan `ln -sfn` di baliknya melakukan unlink lalu symlink.

> [!DANGER]
> **Jangan pernah** `rm -r` sebuah symlink ke direktori dengan garis miring di akhir. Di lab,
> `rm -r releases/20261119/20261120/` mengikuti link dan **menghapus isi direktori target**
> (`index.php` di release baru hilang), baru setelah itu gagal dengan `Not a directory`. Garis miring di akhir
> memaksa kernel me-resolve link sebagai direktori. Hapus link dengan `rm nama-link` atau `unlink nama-link`.
> Sumber: [path_resolution(7)](https://man7.org/linux/man-pages/man7/path_resolution.7.html)

## 6. Membaca metadata: `stat`, `file`, `readlink`

```text
$ stat /etc/hostname
  File: /etc/hostname
  Size: 13          Blocks: 8          IO Block: 4096   regular file
Device: 8,48  Inode: 525         Links: 1
Access: (0644/-rw-r--r--)  Uid: (    0/    root)   Gid: (    0/    root)
Access: 2026-11-20 06:56:49.394250929 +0000
Modify: 2026-11-20 06:56:49.394250929 +0000
Change: 2026-11-20 06:56:49.396590318 +0000
 Birth: 2026-11-20 06:56:49.394250929 +0000
```

| Timestamp | Berubah saat | Opsi `find` |
|---|---|---|
| Access (atime) | Isi dibaca (sering dibatasi oleh opsi mount `relatime`) | `-atime`, `-amin` |
| Modify (mtime) | **Isi** berubah | `-mtime`, `-mmin` |
| Change (ctime) | **Metadata** berubah: chmod, chown, rename, link — juga saat isi berubah | `-ctime`, `-cmin` |
| Birth | File dibuat (tidak semua filesystem mendukung) | `-newerBt` |

```bash
file /usr/bin/ls /bin           # ELF 64-bit ... / symbolic link to usr/bin
readlink /usr/bin/awk           # /etc/alternatives/awk  (satu langkah)
readlink -f /usr/bin/awk        # /usr/bin/mawk          (sampai ujung)
```

## 7. `find`: cari berdasarkan nama, tipe, waktu, ukuran

```bash
find /var/log -type f -name '*.log'          # kutip pola!
find /var/log -type f -size +100M            # > 100 MiB
find /srv/app -mmin -60                       # berubah < 60 menit lalu
find /etc -xtype l                            # symlink rusak
find / -xdev -type f -size +1G 2>/dev/null    # jangan menyeberang ke filesystem lain
find /var/log/app -type f -name '*.log' -mtime +7 -delete
find /srv -type f -name '*.tmp' -print0 | xargs -0 rm   # aman untuk nama berspasi
```

| Bagian | Catatan |
|---|---|
| `-mtime +7` | Umur ≥ 8×24 jam — pecahan hari dibuang. `-mtime 7` = umur 7–8 hari, `-mtime -7` < 7 hari |
| `-size` | Satuan `c` (byte), `k`, `M`, `G`, **dibulatkan ke atas**. `-size -1M` hanya cocok untuk file 0 byte |
| `-exec cmd {} +` | Banyak path per eksekusi (cepat). `{} \;` = satu proses per file |
| `-xdev` | Jangan turun ke mount lain (`/proc`, volume network) |
| `-print0 \| xargs -0` | Di lab, `find ... \| xargs rm` gagal untuk `a b.log`; dengan `-print0`/`-0` berhasil |

> [!DANGER]
> `find` mengevaluasi ekspresi dari kiri ke kanan, dan `-delete` adalah **aksi**, bukan filter. Di lab,
> `find del -delete -name '*.log' -mtime +7` menghapus seluruh isi `del` **beserta direktori `del` itu sendiri**.
> Manual find memperingatkan hal ini secara eksplisit. Biasakan menjalankan perintahnya tanpa `-delete`
> lebih dulu, cek daftarnya, baru tambahkan `-delete` di **akhir**.
> Sumber: [find(1) — -delete](https://man7.org/linux/man-pages/man1/find.1.html)

## 8. Ruang disk dan inode: `df` vs `du`

```bash
df -h                 # ruang per filesystem
df -i                 # inode per filesystem (IUse%)
du -sh /var/log       # total satu direktori
du -sh /var/* 2>/dev/null | sort -rh | head -5    # 5 terbesar di /var
du --inodes -x -d 3 /srv | sort -n | tail         # direktori dengan file terbanyak
findmnt -T /var/lib/docker                         # path ini ada di filesystem mana?
```

`df` bertanya ke filesystem berapa blok yang terpakai. `du` berjalan menyusuri **nama-nama** file dan
menjumlahkan ukurannya. Kalau angkanya jauh berbeda, ada ruang yang terpakai oleh sesuatu yang tidak punya
nama yang bisa dijangkau `du`.

> [!TIP]
> Selalu `sort -h`, bukan `sort -n` atau `sort -r` saja. Di lab, `sort -r` dan `sort -rn` sama-sama
> mengurutkan `900M`, `15M`, `1.2G`, sedangkan `sort -rh` yang benar: `1.2G`, `900M`, `15M`.

## 9. Globbing, quoting, dan batas argumen

| Pola | Cocok dengan |
|---|---|
| `*` | String apa pun, **kecuali** nama berawalan titik |
| `?` | Tepat satu karakter |
| `[abc]`, `[0-9]` | Satu karakter dari himpunan |

Ekspansi dilakukan **shell** sebelum program dijalankan. Akibatnya:

- `echo g/*` di lab menampilkan `g/app.php` tetapi **tidak** `g/.env`.
- Nama berspasi dipecah jadi beberapa argumen kecuali dikutip: `cp laporan akhir.pdf /tmp/` gagal dengan
  `cannot stat 'laporan'` dan `cannot stat 'akhir.pdf'`. Di skrip, selalu tulis `"$file"`.
- Glob yang menghasilkan terlalu banyak nama melebihi batas `execve`. Di lab, `rm many/*` dengan 200.000 file
  gagal: `/usr/bin/rm: Argument list too long`. `find many -type f -delete` berhasil.

## 10. Menghapus dengan aman

```bash
rm -- -rf               # file yang namanya diawali "-"
rm ./-rf                # alternatif
rm -i *.bak             # konfirmasi satu per satu
rm -rf "${BUILD_DIR:?}"/*    # berhenti bila variabel kosong/tidak ada
```

GNU `rm` punya `--preserve-root` sebagai default. Di lab, `rm -rf /` ditolak dengan
`it is dangerous to operate recursively on '/'`. Tapi proteksi itu **hanya untuk `/` persis**. Jadi
`rm -rf "$DIR"/*` dengan `DIR` kosong menjadi `rm -rf /*` dan tidak ditolak.

> [!INSIGHT]
> Skrip Steam for Linux pernah memuat `rm -rf "$STEAMROOT/"*`. Ketika `$STEAMROOT` kosong (setelah
> direktori Steam dipindah), perintah itu menjadi `rm -rf /*` dan menghapus seluruh file milik user, termasuk
> drive backup yang ter-mount. Pertahanannya murah: `set -u`, `${VAR:?}`, dan validasi path sebelum menghapus.
> Di lab, `rm -rf "${DIR:?}"/*` dengan `DIR=""` berhenti: `DIR: parameter null or not set`.
> Sumber: [valvesoftware/steam-for-linux#3671](https://github.com/valvesoftware/steam-for-linux/issues/3671)

## 11. Troubleshooting "No space left on device"

```mermaid
flowchart TD
    E[No space left on device] --> H{df -h<br/>Use% 100%?}
    H -- tidak --> I{df -i<br/>IUse% 100%?}
    I -- ya --> IN[Inode habis: du --inodes / find,<br/>hapus file kecil lama dengan find -delete]
    I -- tidak --> Q[Cek quota, filesystem read-only,<br/>atau path di filesystem lain: findmnt -T]
    H -- ya --> D{du -xsh / ≈ df?}
    D -- ya --> B[Cari file besar:<br/>du -sh ... sort -rh, find -size +1G]
    D -- tidak, du jauh lebih kecil --> L{lsof +L1<br/>ada '(deleted)'?}
    L -- ya --> R[Restart service pemegang file,<br/>atau truncate /proc/PID/fd/N]
    L -- tidak --> M[File tersembunyi di bawah mount point:<br/>bind mount / ke direktori lain lalu du]
```

> [!INSIGHT]
> `rm` pada log yang sedang ditulis **tidak** membebaskan ruang. Nama hilang, tetapi inode tetap ada selama
> proses masih membuka file itu. Di lab, setelah `rm /var/log/app/app.log`, `lsof +L1` menampilkan
> `sleep 404 root 3w REG ... 50000000 0 ... /var/log/app/app.log (deleted)`. Setelah
> `truncate -s 0 /proc/404/fd/3`, ukurannya 0 tanpa menghentikan proses.
> Sumber: [unlink(2)](https://man7.org/linux/man-pages/man2/unlink.2.html),
> [proc(5)](https://man7.org/linux/man-pages/man5/proc.5.html)

> [!INSIGHT]
> Mount menyembunyikan isi lama direktori mount point. Kalau aplikasi menulis ke `/data` **sebelum** disk
> data ter-mount (misalnya saat boot gagal), file itu tetap memakan ruang di root filesystem tetapi tidak terlihat
> oleh `du`. Di lab: file 29M di `/data`, lalu `mount -t tmpfs tmpfs /data` → `du -sh /data` = 0. Lewat
> `mount --bind / /mnt/root`, file itu terlihat lagi di `/mnt/root/data`.
> Sumber: [mount(8)](https://man7.org/linux/man-pages/man8/mount.8.html) — "The previous contents (if any) and
> owner and mode of dir become invisible"

## 12. Ringkasan kebiasaan aman

| Kebiasaan | Kenapa |
|---|---|
| Kutip pola `find` dan variabel (`'*.log'`, `"$f"`) | Shell tidak mengekspansi/memecah sebelum waktunya |
| Jalankan `find` tanpa `-delete` dulu, lalu taruh `-delete` di akhir | `-delete` di depan menghapus semuanya |
| `ln -sfn` atau `ln -s` + `mv -T` untuk symlink release | `ln -sf` mengikuti link direktori |
| Hapus symlink tanpa `/` di akhir | `rm -r link/` menghapus isi target |
| `set -u` dan `${VAR:?}` di skrip yang menghapus | Variabel kosong → `rm -rf /*` |
| `df -h`, lalu `df -i`, lalu `lsof +L1` | Tiga penyebab "disk penuh" yang berbeda |
| Rotasi log dengan `logrotate`, bukan `rm` | Proses yang membuka file tetap memegang ruang |
