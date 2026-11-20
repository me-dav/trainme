---
format: 1
id: linux-systemd-service
course:
  slug: linux
  title: Linux
  description: Fondasi Linux untuk DevOps/DevSecOps — dari filesystem sampai hardening.
title: Service & systemd
summary: Mengelola service dengan systemctl, menulis unit file yang benar, membaca journal, timer pengganti cron, hardening, dan troubleshooting service yang gagal start.
order: 4
version: 1
status: verified
updated: '2026-11-20'
tested_on:
  - Ubuntu 24.04.5 LTS — systemd 255 (255.4-1ubuntu8.19), dijalankan sebagai PID 1 di container Docker --privileged
  - Docker Engine 29.4.1
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: systemctl
    text: Mengelola service dengan systemctl (start, stop, reload, enable, mask)
  - id: unit-file
    text: Menulis unit file service yang benar ([Unit], [Service], [Install])
  - id: drop-in
    text: Mengubah unit milik paket lewat drop-in tanpa menyentuh file aslinya
  - id: journal
    text: Membaca dan memfilter log dengan journalctl
  - id: restart
    text: Mengatur restart otomatis, start limit, dan timeout stop
  - id: timers
    text: Menjadwalkan job dengan systemd timer
  - id: hardening
    text: Mengamankan service (sandboxing & secret)
  - id: troubleshoot
    text: Mendiagnosis service yang gagal start atau berhenti
sources:
  - title: systemctl(1)
    url: https://www.freedesktop.org/software/systemd/man/latest/systemctl.html
  - title: systemd.unit(5) — lokasi unit & drop-in
    url: https://www.freedesktop.org/software/systemd/man/latest/systemd.unit.html
  - title: systemd.service(5) — Type=, Restart=, ExecStart=
    url: https://www.freedesktop.org/software/systemd/man/latest/systemd.service.html
  - title: systemd.exec(5) — User=, Environment=, sandboxing, kode exit 2xx
    url: https://www.freedesktop.org/software/systemd/man/latest/systemd.exec.html
  - title: systemd.kill(5) — KillMode=, KillSignal=
    url: https://www.freedesktop.org/software/systemd/man/latest/systemd.kill.html
  - title: systemd.timer(5)
    url: https://www.freedesktop.org/software/systemd/man/latest/systemd.timer.html
  - title: systemd.time(7) — format OnCalendar & waktu relatif
    url: https://www.freedesktop.org/software/systemd/man/latest/systemd.time.html
  - title: journalctl(1)
    url: https://www.freedesktop.org/software/systemd/man/latest/journalctl.html
  - title: systemd-analyze(1) — verify, calendar, security
    url: https://www.freedesktop.org/software/systemd/man/latest/systemd-analyze.html
  - title: systemd Credentials
    url: https://systemd.io/CREDENTIALS/
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: command
    objective: systemctl
    prompt: Jalankan service `nginx` **sekarang** (tanpa mengubah perilaku saat boot).
    answers: ['systemctl start nginx']
    patterns: ['^systemctl start nginx(\.service)?$']
    tests:
      pass: ['sudo systemctl start nginx.service']
      fail: ['systemctl enable nginx', 'service nginx', 'nginx start']
    explanation: '`start` hanya untuk sekarang. Ikut jalan saat boot atau tidak diatur oleh `enable`/`disable` — dua hal yang terpisah.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemctl.html

  - id: a02
    tier: A
    type: command
    objective: systemctl
    prompt: Dalam **satu** perintah, aktifkan `nginx` agar jalan saat boot **dan** langsung jalankan sekarang.
    answers: ['systemctl enable --now nginx']
    patterns: ['^systemctl (enable --now|--now enable) nginx(\.service)?$', '^systemctl enable nginx(\.service)? --now$']
    tests:
      pass: ['systemctl --now enable nginx', 'systemctl enable nginx --now']
      fail: ['systemctl enable nginx', 'systemctl start nginx', 'systemctl restart nginx']
    explanation: '`enable` saja hanya membuat symlink di `/etc/systemd/system/multi-user.target.wants/` (terlihat di lab: `Created symlink ...`), tanpa menjalankan service. `--now` menambahkan start. Pasangannya: `disable --now`.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemctl.html

  - id: a03
    tier: A
    type: command
    objective: systemctl
    prompt: Tampilkan status `nginx` — aktif atau tidak, PID utama, cgroup, dan beberapa baris log terakhir.
    answers: ['systemctl status nginx']
    patterns: ['^systemctl status nginx(\.service)?( --no-pager| -l)*$']
    tests:
      pass: ['systemctl status nginx.service --no-pager']
      fail: ['systemctl show nginx', 'journalctl nginx', 'service status nginx']
    explanation: '`status` adalah titik awal troubleshooting: baris `Active:`, `Process:` (exit code), dan cuplikan journal. Untuk log lengkap pakai `journalctl -u`.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemctl.html

  - id: a04
    tier: A
    type: command
    objective: journal
    prompt: Tampilkan log journal milik unit `nginx` saja.
    answers: ['journalctl -u nginx']
    patterns: ['^journalctl (-u ?|--unit[= ])nginx(\.service)?( --no-pager)?$']
    tests:
      pass: ['journalctl --unit=nginx', 'journalctl -u nginx.service']
      fail: ['journalctl nginx', 'tail /var/log/nginx', 'systemctl logs nginx']
    explanation: 'Semua stdout/stderr service masuk ke journal, dengan field `_SYSTEMD_UNIT`. Di lab, `echo` dari skrip service muncul sebagai `app.sh[92]: ...`.'
    source: https://www.freedesktop.org/software/systemd/man/latest/journalctl.html

  - id: a05
    tier: A
    type: command
    objective: journal
    prompt: Ikuti log `nginx` secara **real-time** (seperti `tail -f`).
    answers: ['journalctl -u nginx -f']
    patterns: ['^journalctl ((-u ?|--unit[= ])nginx(\.service)? (-f|--follow)|(-f|--follow) (-u ?|--unit[= ])nginx(\.service)?|-fu ?nginx(\.service)?)$']
    tests:
      pass: ['journalctl -f -u nginx', 'journalctl -fu nginx', 'journalctl --unit=nginx --follow']
      fail: ['journalctl -u nginx', 'tail -f nginx', 'journalctl -f']
    explanation: '`-f` = follow. Kombinasi umum lainnya: `-n 100` (100 baris terakhir), `-e` (lompat ke akhir), `-x` (tambahan penjelasan).'
    source: https://www.freedesktop.org/software/systemd/man/latest/journalctl.html

  - id: a06
    tier: A
    type: command
    objective: unit-file
    prompt: Kamu baru mengubah file unit di `/etc/systemd/system/`. Perintah apa yang membuat systemd **membaca ulang** semua file unit?
    answers: ['systemctl daemon-reload']
    patterns: ['^systemctl daemon-reload$']
    tests:
      fail: ['systemctl reload', 'systemctl restart systemd', 'systemctl reload-daemon', 'systemctl daemon-reexec']
    explanation: 'Tanpa ini, systemd tetap memakai versi lama. Di lab, `restart` setelah mengedit unit memunculkan `Warning: The unit file ... changed on disk. Run ''systemctl daemon-reload''`, dan nilai lama yang dipakai.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemctl.html

  - id: a07
    tier: A
    type: command
    objective: systemctl
    prompt: Minta `nginx` membaca ulang **konfigurasinya** tanpa memutus koneksi yang sedang berjalan (bukan restart).
    answers: ['systemctl reload nginx']
    patterns: ['^systemctl reload nginx(\.service)?$']
    tests:
      fail: ['systemctl restart nginx', 'systemctl daemon-reload', 'systemctl reload-or-restart']
    explanation: '`reload` menjalankan `ExecReload=` milik unit (untuk nginx: kirim SIGHUP). Jangan tertukar dengan `daemon-reload`, yang membaca ulang **file unit**, bukan config aplikasi. `reload-or-restart` cocok bila tidak yakin unit mendukung reload.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemctl.html

  - id: a08
    tier: A
    type: text
    objective: unit-file
    prompt: Di direktori mana admin seharusnya menaruh unit file **buatan sendiri** (dan override), terpisah dari unit bawaan paket?
    answers: ['/etc/systemd/system', '/etc/systemd/system/']
    tests:
      fail: ['/lib/systemd/system', '/usr/lib/systemd/system', '/run/systemd/system', '/etc/init.d']
    explanation: 'Urutan prioritas: `/etc/systemd/system` (admin) > `/run/systemd/system` (runtime) > `/usr/lib/systemd/system` (paket; `/lib` adalah symlink ke sana di Ubuntu). File paket bisa ditimpa saat upgrade, jadi jangan diedit.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemd.unit.html

  - id: a09
    tier: A
    type: command
    objective: troubleshoot
    prompt: Tampilkan semua unit yang berstatus **failed**.
    answers: ['systemctl --failed']
    patterns: ['^systemctl (--failed|list-units --failed|list-units --state=failed|--state=failed)( --no-pager)?$']
    tests:
      pass: ['systemctl list-units --failed', 'systemctl list-units --state=failed']
      fail: ['systemctl status', 'journalctl -p err', 'systemctl list-units']
    explanation: 'Cek cepat setelah reboot atau deploy. Di lab muncul `bad1.service loaded failed failed`, dst.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemctl.html

  - id: a10
    tier: A
    type: text
    objective: unit-file
    prompt: 'Nilai `WantedBy=` di bagian `[Install]` yang umum dipakai agar service jalan saat boot pada server tanpa GUI?'
    answers: ['multi-user.target', 'wantedby=multi-user.target']
    tests:
      fail: ['network.target', 'basic.target', 'sysinit.target', 'multi-user']
    explanation: '`multi-user.target` kira-kira setara runlevel 3. `enable` membaca bagian `[Install]` ini untuk menentukan di mana symlink dibuat. Tanpa `[Install]`, `enable` tidak bisa bekerja.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemd.unit.html

  - id: a11
    tier: A
    type: command
    objective: systemctl
    prompt: Cek apakah `nginx` diatur **jalan saat boot** (enabled/disabled), cukup satu kata jawabannya.
    answers: ['systemctl is-enabled nginx']
    patterns: ['^systemctl is-enabled nginx(\.service)?$']
    tests:
      fail: ['systemctl is-active nginx', 'systemctl status nginx', 'systemctl enable nginx']
    explanation: '`is-active` menjawab "sedang jalan?", `is-enabled` menjawab "jalan saat boot?". Di lab, setelah `disable`, service tetap `active` tapi `disabled`. Keduanya mengembalikan exit code, sehingga cocok untuk skrip (`is-active` → 3 bila inactive).'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemctl.html

  - id: a12
    tier: A
    type: text
    objective: restart
    prompt: 'Nilai `Restart=` yang me-restart service saat **crash/exit non-zero**, tetapi **tidak** setelah ia keluar dengan bersih (exit 0)?'
    answers: ['on-failure', 'restart=on-failure']
    tests:
      fail: ['always', 'yes', 'on-abnormal', 'no']
    explanation: 'Di lab, `kill -9` pada service `Restart=on-failure` → `NRestarts=1` dan aktif lagi, sedangkan `/bin/true` (exit 0) berhenti dengan `Result=success` tanpa restart. `always` me-restart dalam semua kasus, kecuali dihentikan lewat `systemctl stop`.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemd.service.html

  - id: a13
    tier: A
    type: command
    objective: journal
    prompt: Tampilkan log unit `nginx` **sejak 1 jam terakhir**.
    answers: ['journalctl -u nginx --since "1 hour ago"']
    patterns:
      - '^journalctl (-u ?|--unit[= ])nginx(\.service)? (--since|-S)[= ]?(1 hour ago|-1h|-1 ?hour|-60 ?min)$'
      - '^journalctl (--since|-S)[= ]?(1 hour ago|-1h|-1 ?hour|-60 ?min) (-u ?|--unit[= ])nginx(\.service)?$'
    tests:
      pass: ['journalctl -u nginx --since -1h', 'journalctl --since "1 hour ago" -u nginx', 'journalctl -u nginx -S -60min']
      fail: ['journalctl -u nginx -n 60', 'journalctl --since "1 hour ago"', 'journalctl -u nginx --until "1 hour ago"']
    explanation: 'Format waktu fleksibel: `"2026-11-20 09:00"`, `yesterday`, `"1 hour ago"`, `-1h` (keduanya diuji di lab). Pasangannya `--until`.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemd.time.html

  - id: a14
    tier: A
    type: command
    objective: systemctl
    prompt: Cegah service `cups` di-start **sama sekali** — baik manual maupun sebagai dependency unit lain.
    answers: ['systemctl mask cups']
    patterns: ['^systemctl mask (--now )?cups(\.service)?( --now)?$']
    tests:
      pass: ['systemctl mask --now cups', 'systemctl mask cups.service']
      fail: ['systemctl disable cups', 'systemctl stop cups', 'systemctl disable --now cups']
    explanation: '`mask` menautkan unit ke `/dev/null`. Di lab: `Created symlink /etc/systemd/system/cron.service → /dev/null`, lalu `start` gagal dengan `Unit cron.service is masked`. `disable` hanya mencegah start saat boot. Kebalikannya `unmask`.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemctl.html

  - id: a15
    tier: A
    type: command
    objective: journal
    prompt: Tampilkan hanya log berprioritas **error ke atas** sejak **boot saat ini**.
    answers: ['journalctl -p err -b']
    patterns: ['^journalctl(?=.*( -p ?| --priority[= ])(err|3)(\s|$))(?=.*( -b| --boot)(\s|$))( (-p ?(err|3)|--priority[= ](err|3)|-b|--boot|-x|--no-pager))+$']
    tests:
      pass: ['journalctl -b -p err', 'journalctl -p 3 -b', 'journalctl --priority=err --boot']
      fail: ['journalctl -p err', 'journalctl -b', 'journalctl -p warning -b']
    explanation: 'Prioritas syslog: 0 emerg … 3 err, 4 warning … 7 debug. `-p err` = err dan yang lebih parah. `-b -1` = boot sebelumnya, berguna setelah crash (butuh journal persisten di `/var/log/journal`).'
    source: https://www.freedesktop.org/software/systemd/man/latest/journalctl.html

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: command
    objective: drop-in
    prompt: Buat/ubah **override** untuk unit `nginx` milik paket, tanpa menyentuh file unit aslinya. Pakai perintah bawaan systemctl.
    answers: ['systemctl edit nginx']
    patterns: ['^systemctl edit nginx(\.service)?$']
    tests:
      fail: ['vim /lib/systemd/system/nginx.service', 'systemctl edit --full nginx', 'nano /etc/systemd/system/nginx.service']
    explanation: '`systemctl edit` membuka `/etc/systemd/system/nginx.service.d/override.conf` dan otomatis menjalankan daemon-reload setelah disimpan. `--full` menyalin seluruh unit ke `/etc`, sehingga perbaikan dari upgrade paket tidak lagi terpakai. Butuh TTY; di skrip, tulis file drop-in langsung lalu `daemon-reload`.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemctl.html

  - id: b02
    tier: B
    type: text
    objective: drop-in
    prompt: 'Di file drop-in, kamu ingin **mengganti** `ExecStart=` dari unit asli. Baris apa yang harus ditulis tepat **sebelum** `ExecStart=` yang baru?'
    answers: ['ExecStart=']
    tests:
      fail: ['ExecStart=/bin/true', 'ExecStop=', 'ExecStartPre=', 'ExecStart']
    explanation: 'Setting yang bisa diulang (ExecStart, Environment list, dll.) **ditambahkan**, bukan diganti. Baris kosong `ExecStart=` mereset daftarnya. Di lab, tanpa baris itu restart gagal: `Service has more than one ExecStart= setting, which is only allowed for Type=oneshot services. Refusing.`'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemd.unit.html

  - id: b03
    tier: B
    type: text
    objective: troubleshoot
    prompt: '`systemctl status` menunjukkan `(code=exited, status=217/USER)`. Directive unit mana yang hampir pasti salah?'
    answers: ['User', 'User=']
    tests:
      fail: ['ExecStart', 'WorkingDirectory', 'Group', 'Environment']
    explanation: 'Kode 2xx berasal dari systemd sendiri, sebelum program dijalankan. Diuji di lab: `User=nosuchuser` → 217/USER, `WorkingDirectory=/nope` → 200/CHDIR, binary tidak ada → 203/EXEC. Grup yang salah → 216/GROUP.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemd.exec.html

  - id: b04
    tier: B
    type: text
    objective: troubleshoot
    prompt: 'Status `203/EXEC` artinya systemd gagal meng-exec program. Directive mana yang perlu kamu periksa (beserta file yang ditunjuknya)?'
    answers: ['ExecStart', 'ExecStart=']
    tests:
      fail: ['User', 'WorkingDirectory', 'ExecStop', 'Type']
    explanation: 'Penyebab umum: path salah, file tidak punya bit `x`, shebang menunjuk interpreter yang tidak ada, atau `noexec` di mount. Cek `ls -l`, `head -1`, dan `namei -l` pada path di `ExecStart`.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemd.exec.html

  - id: b05
    tier: B
    type: command
    objective: restart
    prompt: Tampilkan **hanya** properti `MainPID` dan `NRestarts` dari service `app`.
    answers: ['systemctl show -p MainPID,NRestarts app']
    patterns:
      - '^systemctl show (-p ?|--property[= ])(MainPID,NRestarts|NRestarts,MainPID)( --value)? app(\.service)?$'
      - '^systemctl show app(\.service)? (-p ?|--property[= ])(MainPID,NRestarts|NRestarts,MainPID)( --value)?$'
      - '^systemctl show (-p ?MainPID -p ?NRestarts|-p ?NRestarts -p ?MainPID) app(\.service)?$'
    tests:
      pass: ['systemctl show app -p MainPID,NRestarts', 'systemctl show -p MainPID -p NRestarts app', 'systemctl show --property=NRestarts,MainPID app']
      fail: ['systemctl status app', 'systemctl show app', 'systemctl cat app']
    explanation: '`show` memberi format `Key=Value` yang mudah diparsing; `--value` hanya nilainya (`systemctl show -p MainPID --value app`). `NRestarts` yang terus naik = service crash-loop.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemctl.html

  - id: b06
    tier: B
    type: command
    objective: restart
    prompt: |
      Service `crash` sudah kamu perbaiki, tapi sebelumnya ia crash-loop dan kena
      `Start request repeated too quickly`. Reset status gagal & penghitung start-nya supaya bisa langsung di-start lagi.
    answers: ['systemctl reset-failed crash']
    patterns: ['^systemctl reset-failed crash(\.service)?$']
    tests:
      fail: ['systemctl restart crash', 'systemctl daemon-reload', 'systemctl unmask crash', 'systemctl reset-failed']
    explanation: 'Default `StartLimitBurst=5` dalam `StartLimitIntervalSec=10s` (lab: `NRestarts=5`, lalu berhenti). Di lab, setelah file diperbaiki, `start` masih gagal; setelah `reset-failed crash` langsung `active`. `reset-failed` tanpa nama mereset **semua** unit.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemd.unit.html

  - id: b07
    tier: B
    type: command
    objective: drop-in
    prompt: Tampilkan isi unit `nginx` yang **benar-benar berlaku** — file utama beserta semua drop-in-nya.
    answers: ['systemctl cat nginx']
    patterns: ['^systemctl cat nginx(\.service)?$']
    tests:
      fail: ['cat /lib/systemd/system/nginx.service', 'systemctl show nginx', 'systemctl status nginx']
    explanation: 'Di lab, `systemctl cat app` menampilkan `# /etc/systemd/system/app.service` lalu `# /etc/systemd/system/app.service.d/override.conf`. Ini cara cepat melihat override yang mungkin terlupakan. `systemd-delta` mendaftar semua override di sistem.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemctl.html

  - id: b08
    tier: B
    type: command
    objective: timers
    prompt: Tampilkan semua systemd **timer** yang aktif beserta jadwal berikutnya dan kapan terakhir jalan.
    answers: ['systemctl list-timers']
    patterns: ['^systemctl list-timers( --all| --no-pager)*$']
    tests:
      pass: ['systemctl list-timers --all']
      fail: ['crontab -l', 'systemctl list-units', 'systemctl status timers.target']
    explanation: 'Kolom NEXT, LEFT, LAST, PASSED, UNIT, ACTIVATES. Di lab, `report.timer` dengan `RandomizedDelaySec=5m` dijadwalkan pukul `02:04:51`, bukan tepat `02:00`.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemctl.html

  - id: b09
    tier: B
    type: command
    objective: timers
    prompt: Sebelum memakainya di timer, cek kapan ekspresi `Mon..Fri *-*-* 02:00:00` akan terpicu berikutnya.
    answers: ['systemd-analyze calendar ''Mon..Fri *-*-* 02:00:00''']
    patterns: ['^systemd-analyze calendar Mon\.\.Fri \*-\*-\* 02:00(:00)?( --iterations[= ]?\d+)?$']
    tests:
      pass: ['systemd-analyze calendar "Mon..Fri *-*-* 02:00" --iterations=3']
      fail: ['systemctl list-timers', 'date', 'systemd-analyze verify Mon..Fri *-*-* 02:00:00']
    explanation: 'Outputnya menampilkan `Normalized form` dan `Next elapse`, sehingga kesalahan format langsung ketahuan. Di lab: `daily` → `*-*-* 00:00:00`, `*:0/15` → `*-*-* *:00/15:00`. Kutip ekspresinya, karena berisi `*`.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemd-analyze.html

  - id: b10
    tier: B
    type: text
    objective: timers
    prompt: 'Tulis nilai `OnCalendar=` untuk menjalankan job **setiap 15 menit**.'
    answers: ['*:0/15', '*:00/15', '*-*-* *:00/15:00']
    patterns: ['^(oncalendar=)?(\*-\*-\* )?\*:0{1,2}/15(:00)?$']
    tests:
      pass: ['OnCalendar=*:0/15', '*-*-* *:0/15']
      fail: ['*/15 * * * *', '15min', '*:15', '*:*/15']
    explanation: 'Sintaks cron (`*/15 * * * *`) **tidak** berlaku di systemd. `*:15` berarti setiap jam pada menit ke-15. Alternatif monotonic: `OnUnitActiveSec=15min` (15 menit setelah job terakhir jalan).'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemd.time.html

  - id: b11
    tier: B
    type: command
    objective: unit-file
    prompt: Periksa file `/etc/systemd/system/app.service` dari **typo dan kesalahan** sebelum dipakai.
    answers: ['systemd-analyze verify /etc/systemd/system/app.service']
    patterns: ['^systemd-analyze verify (/etc/systemd/system/)?app\.service$']
    tests:
      pass: ['systemd-analyze verify app.service']
      fail: ['systemctl daemon-reload', 'systemctl cat app', 'systemd-analyze security app']
    explanation: 'systemd **mengabaikan** key yang tidak dikenal dengan peringatan, tanpa error. Di lab, `Restar=always` (typo) hanya menghasilkan `Unknown key name ''Restar'' ... ignoring` — service berjalan tanpa restart otomatis dan tidak ada yang sadar. `verify` menangkapnya.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemd-analyze.html

  - id: b12
    tier: B
    type: command
    objective: hardening
    prompt: Tampilkan **skor paparan keamanan** (exposure level) service `app` beserta rekomendasi sandboxing per directive.
    answers: ['systemd-analyze security app']
    patterns: ['^systemd-analyze security app(\.service)?( --no-pager)?$']
    tests:
      pass: ['systemd-analyze security app.service']
      fail: ['systemd-analyze verify app', 'systemctl show app', 'systemd-analyze blame']
    explanation: 'Skala 0 (aman) sampai 10. Di lab, unit polos mendapat `9.2 UNSAFE`. Setelah `NoNewPrivileges`, `ProtectSystem=strict`, `ProtectHome`, `PrivateTmp`, dan `ReadWritePaths`, skornya turun ke `8.3 EXPOSED`. Skor ini panduan, bukan nilai mutlak.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemd-analyze.html

  - id: b13
    tier: B
    type: text
    objective: restart
    prompt: Secara default, berapa lama systemd menunggu setelah SIGTERM sebelum mengirim SIGKILL saat `systemctl stop` (`TimeoutStopSec`)?
    answers: ['90', '90 detik', '90s', '1min 30s']
    patterns: ['^(90 ?(detik|s|sec|seconds)?|1 ?min(ute)? ?30 ?s(ec)?|1m ?30s|1 menit 30 detik)$']
    tests:
      fail: ['10', '30', '60', '5']
    explanation: 'Di lab: `TimeoutStopUSec=1min 30s`, `KillMode=control-group`, `KillSignal=15`. Service yang mengabaikan TERM dengan `TimeoutStopSec=3` dibunuh 4 detik kemudian: `Killing process 558 (sleep) with signal SIGKILL` dan `Result ''timeout''`. `KillMode=control-group` ikut membunuh semua anak proses.'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemd.kill.html

  - id: b14
    tier: B
    type: text
    objective: unit-file
    prompt: 'Dengan `Type=simple`, `systemctl start` tetap sukses walau binary di `ExecStart` tidak ada. Nilai `Type=` apa yang membuat `start` **langsung gagal** dalam kasus itu, tanpa aplikasi perlu mengirim notifikasi?'
    answers: ['exec', 'type=exec']
    tests:
      fail: ['simple', 'oneshot', 'forking', 'idle']
    explanation: 'Di lab, binary yang tidak ada dengan `Type=simple` → `start rc=0`, dan baru kemudian unit menjadi failed (203/EXEC). Dengan `Type=exec` → `Job for bad1.service failed`, `rc=1`. Skrip deploy yang mengandalkan exit code `systemctl start` perlu `Type=exec` (atau `notify` bila aplikasi mendukung sd_notify).'
    source: https://www.freedesktop.org/software/systemd/man/latest/systemd.service.html

  - id: b15
    tier: B
    type: command
    objective: journal
    prompt: Journal memakan terlalu banyak disk. Kurangi file journal yang diarsipkan sampai totalnya paling banyak **500 MB**.
    answers: ['journalctl --vacuum-size=500M']
    patterns: ['^journalctl --vacuum-size[= ]500M$']
    tests:
      fail: ['rm -rf /var/log/journal', 'journalctl --disk-usage', 'journalctl --vacuum-time=500M']
    explanation: 'Cek dulu dengan `journalctl --disk-usage` (lab: `Archived and active journals take up 8.0M`). Batas permanen diatur dengan `SystemMaxUse=` di `/etc/systemd/journald.conf`. Alternatif: `--vacuum-time=14d`.'
    source: https://www.freedesktop.org/software/systemd/man/latest/journalctl.html

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: troubleshoot
    prompt: |
      Setelah deploy, `worker.service` gagal dan tidak mau jalan lagi. Tulis perintah (satu per baris) untuk
      memeriksa log, memperbaiki penyebabnya, menyalakan service kembali, dan memverifikasi hasilnya.
    context: |
      $ systemctl status worker --no-pager
      × worker.service - Queue worker
           Loaded: loaded (/etc/systemd/system/worker.service; enabled; preset: enabled)
           Active: failed (Result: exit-code) since Fri 2026-11-20 07:22:40 UTC; 1s ago
          Process: 872 ExecStart=/srv/app/bin/worker (code=exited, status=203/EXEC)
         Main PID: 872 (code=exited, status=203/EXEC)
      $ ls -l /srv/app/bin/worker
      -rw-r--r-- 1 deploy deploy 31 Nov 20 07:22 /srv/app/bin/worker
      $ sudo systemctl start worker
      Job for worker.service failed because the control process exited with error code.
    checklist:
      - id: logs
        label: Membaca log unit (journalctl -u worker / -xeu)
        patterns: ['journalctl\s+(-\S+\s+)*(-u\s*|--unit[= ]|-xeu\s*)\S*worker']
      - id: fix-exec
        label: Memberi bit eksekusi pada file ExecStart (chmod +x / 755)
        required: true
        patterns: ['chmod\s+(-\S+\s+)*\S*(\+x|[75][0-7][0-7])\s+\S*bin/worker']
      - id: reset
        label: Mereset start limit (systemctl reset-failed)
        patterns: ['systemctl\s+reset-failed(\s+\S*worker)?']
      - id: start
        label: Menyalakan kembali service
        required: true
        patterns: ['systemctl\s+(re)?start\s+\S*worker']
      - id: verify
        label: Memverifikasi (status / is-active / journal)
        patterns: ['systemctl\s+(status|is-active)\s+\S*worker', 'journalctl\s+.*-f']
      - id: no-777
        label: Tidak memakai chmod 777
        forbid: true
        patterns: ['chmod\s+(-\S+\s+)*0?777']
      - id: no-root
        label: Tidak "memperbaiki" dengan menjalankan worker sebagai root
        forbid: true
        patterns: ['^user\s*=\s*root\s*$']
    solution: |
      ```bash
      journalctl -xeu worker --no-pager | tail -n 20
      ls -l /srv/app/bin/worker && head -1 /srv/app/bin/worker   # bit x? shebang benar?
      chmod 755 /srv/app/bin/worker
      systemctl reset-failed worker
      systemctl start worker
      systemctl status worker --no-pager
      ```
      `203/EXEC` = systemd gagal meng-exec program: file tidak executable (`-rw-r--r--`), path salah, shebang
      rusak, atau mount `noexec`. Karena unit memakai `Restart=always`, crash-loop cepat membuatnya kena
      start limit. Di lab, setelah `chmod` pun `start` masih gagal sampai dijalankan `reset-failed worker`.
      Perbaikan jangka panjang: pastikan pipeline deploy mempertahankan mode file (mis. `git` menyimpan bit x
      lewat `git update-index --chmod=+x`).
    tests:
      pass:
        - |
          journalctl -xeu worker
          chmod +x /srv/app/bin/worker
          systemctl reset-failed worker
          systemctl start worker
          systemctl status worker
        - |
          sudo chmod 755 /srv/app/bin/worker
          sudo systemctl restart worker
          systemctl is-active worker
      fail:
        - chmod 777 /srv/app/bin/worker
        - |
          journalctl -u worker
          systemctl restart worker
        - |
          systemctl reset-failed worker
          systemctl start worker
    source: https://www.freedesktop.org/software/systemd/man/latest/systemd.exec.html

  - id: c02
    tier: C
    type: scenario
    objective: unit-file
    prompt: |
      Tulis unit `/etc/systemd/system/queue-worker.service` untuk `php artisan queue:work` (PHP di `/usr/bin/php`)
      dengan syarat berikut, lalu perintah untuk mengaktifkannya. Tulis isi unit apa adanya, satu directive per baris.
      - jalan sebagai user `app` di `/srv/app`, membaca env dari `/etc/app.env`
      - start setelah network dan `postgresql.service`
      - restart otomatis bila crash, jeda 5 detik
      - diberi 60 detik untuk menyelesaikan job saat stop
      - ikut jalan saat boot
    checklist:
      - id: user
        label: User=app
        required: true
        patterns: ['^user\s*=\s*app$']
      - id: exec
        label: ExecStart dengan path absolut ke php dan artisan queue:work
        required: true
        patterns: ['^execstart\s*=\s*/\S*php\S*\s+(/srv/app/)?artisan\s+queue:work']
      - id: workdir
        label: WorkingDirectory=/srv/app
        patterns: ['^workingdirectory\s*=\s*/srv/app/?$']
      - id: envfile
        label: EnvironmentFile=/etc/app.env
        patterns: ['^environmentfile\s*=\s*-?/etc/app\.env$']
      - id: after
        label: After= network & postgresql
        patterns: ['^after\s*=.*(postgresql|network)']
      - id: restart
        label: Restart=on-failure / always
        required: true
        patterns: ['^restart\s*=\s*(on-failure|always)$']
      - id: restartsec
        label: RestartSec=5
        patterns: ['^restartsec\s*=\s*5(s)?$']
      - id: stop-timeout
        label: TimeoutStopSec=60
        patterns: ['^timeoutstopsec\s*=\s*(60|1min)(s)?$']
      - id: install
        label: '[Install] WantedBy=multi-user.target'
        patterns: ['^wantedby\s*=\s*multi-user\.target$']
      - id: activate
        label: daemon-reload + enable --now
        patterns: ['systemctl\s+enable\s+(--now\s+)?\S*queue-worker', 'systemctl\s+daemon-reload']
      - id: no-root
        label: Tidak berjalan sebagai root
        forbid: true
        patterns: ['^user\s*=\s*root$']
      - id: no-background
        label: Tidak memakai nohup/sudo/& di ExecStart (systemd yang mengelola proses)
        forbid: true
        patterns: ['^execstart\s*=\s*(sudo|nohup)\b', '^execstart\s*=.*&\s*$']
    pass_score: 0.6
    solution: |
      ```ini
      [Unit]
      Description=Laravel queue worker
      After=network.target postgresql.service
      Wants=postgresql.service

      [Service]
      User=app
      Group=app
      WorkingDirectory=/srv/app
      EnvironmentFile=/etc/app.env
      ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
      Restart=on-failure
      RestartSec=5
      TimeoutStopSec=60

      [Install]
      WantedBy=multi-user.target
      ```
      ```bash
      systemd-analyze verify /etc/systemd/system/queue-worker.service
      systemctl daemon-reload
      systemctl enable --now queue-worker
      ```
      Unit serupa (dengan `sleep` sebagai pengganti php) lolos `verify` dan aktif di lab. `After=` hanya mengatur
      **urutan**; `Wants=`/`Requires=` yang menarik dependency ikut start. Jangan pakai `&` atau `nohup`:
      systemd sendiri yang menjaga proses foreground. `TimeoutStopSec` sebaiknya lebih besar dari durasi job
      terpanjang (atau `--timeout` worker), supaya SIGTERM sempat diproses Laravel.
    tests:
      pass:
        - |
          [Unit]
          After=network.target postgresql.service
          [Service]
          User=app
          WorkingDirectory=/srv/app
          EnvironmentFile=/etc/app.env
          ExecStart=/usr/bin/php artisan queue:work
          Restart=on-failure
          RestartSec=5
          TimeoutStopSec=60
          [Install]
          WantedBy=multi-user.target
          systemctl daemon-reload
          systemctl enable --now queue-worker
        - |
          User=app
          WorkingDirectory=/srv/app
          ExecStart=/usr/bin/php /srv/app/artisan queue:work --tries=3
          Restart=always
          RestartSec=5s
          WantedBy=multi-user.target
      fail:
        - |
          User=root
          ExecStart=/usr/bin/php artisan queue:work
          Restart=always
          WorkingDirectory=/srv/app
          RestartSec=5
          WantedBy=multi-user.target
        - |
          User=app
          ExecStart=nohup php artisan queue:work &
          Restart=always
        - |
          User=app
          WorkingDirectory=/srv/app
          ExecStart=/usr/bin/php artisan queue:work
          WantedBy=multi-user.target
    source: https://www.freedesktop.org/software/systemd/man/latest/systemd.service.html

  - id: c03
    tier: C
    type: scenario
    objective: hardening
    prompt: |
      Audit keamanan menemukan dua masalah pada `api.service`: password database bisa dibaca user mana pun,
      dan skor sandbox-nya buruk. Tulis langkah (perintah atau baris unit/drop-in, satu per baris) untuk
      memindahkan secret dengan aman, menambahkan sandboxing (aplikasi hanya perlu menulis ke `/srv/api/storage`),
      menerapkan, dan memverifikasi.
    context: |
      $ systemctl cat api | grep -E 'User|Environment|Exec'
      User=api
      Environment=DB_PASSWORD=Sup3rS3cret
      ExecStart=/srv/api/bin/api
      $ sudo -u nobody systemctl show -p Environment api
      Environment=DB_PASSWORD=Sup3rS3cret
      $ systemd-analyze security api | tail -n 1
      → Overall exposure level for api.service: 9.2 UNSAFE
    checklist:
      - id: move-secret
        label: Memindahkan secret ke EnvironmentFile atau LoadCredential
        required: true
        patterns: ['^environmentfile\s*=\s*-?/\S+', '^loadcredential\s*=\s*\S+']
      - id: perm
        label: File secret hanya terbaca root/pemilik (chmod 600/640, chown)
        patterns: ['chmod\s+(0?600|0?640|0?400|u=rw,go=|go-rwx)\s+\S+', 'chown\s+root:\S+\s+\S+']
      - id: nnp
        label: NoNewPrivileges=yes
        patterns: ['^nonewprivileges\s*=\s*(yes|true|1)$']
      - id: protect-system
        label: ProtectSystem=strict (atau full)
        required: true
        patterns: ['^protectsystem\s*=\s*(strict|full)$']
      - id: rw-paths
        label: ReadWritePaths=/srv/api/storage
        patterns: ['^readwritepaths\s*=\s*\S*/srv/api/storage']
      - id: tmp-home
        label: PrivateTmp / ProtectHome
        patterns: ['^(privatetmp|protecthome)\s*=\s*(yes|true|read-only|tmpfs)$']
      - id: apply
        label: daemon-reload + restart
        patterns: ['systemctl\s+daemon-reload', 'systemctl\s+restart\s+\S*api']
      - id: verify
        label: Verifikasi (systemd-analyze security / systemctl show -p Environment)
        patterns: ['systemd-analyze\s+security\s+\S*api', 'systemctl\s+show\s+.*environment']
      - id: no-inline-secret
        label: Tidak tetap menaruh secret di Environment=
        forbid: true
        patterns: ['^environment\s*=.*(password|secret|token)\s*=']
      - id: no-world-readable
        label: Tidak membuat file secret world-readable
        forbid: true
        patterns: ['chmod\s+(-\S+\s+)*(0?644|0?666|0?755|0?777|a\+r|o\+r)\s+\S*(env|secret|credential|password)']
    pass_score: 0.6
    solution: |
      ```bash
      install -m 600 -o root -g root /dev/null /etc/api/api.env
      echo 'DB_PASSWORD=Sup3rS3cret' > /etc/api/api.env   # lalu ROTASI password ini
      systemctl edit api
      ```
      ```ini
      [Service]
      Environment=
      EnvironmentFile=/etc/api/api.env
      NoNewPrivileges=yes
      ProtectSystem=strict
      ProtectHome=yes
      PrivateTmp=yes
      ReadWritePaths=/srv/api/storage
      ```
      ```bash
      systemctl daemon-reload && systemctl restart api
      sudo -u nobody systemctl show -p Environment api   # tidak ada lagi password
      systemd-analyze security api
      ```
      Di lab, `Environment=` terlihat oleh user `nobody` lewat `systemctl show`, sedangkan nilai dari
      `EnvironmentFile=` (mode 600, dibaca systemd sebagai root) tidak muncul di properti itu, meskipun tetap ada di
      environment proses. Dokumentasi systemd menyarankan `LoadCredential=` untuk secret. Variabel itu tidak
      diwariskan ke proses anak dan dibaca aplikasi dari file di `$CREDENTIALS_DIRECTORY`. Fitur ini butuh
      `/run/credentials` dan **tidak bisa diuji** di container lab kami. `ProtectSystem=strict` membuat seluruh
      filesystem read-only bagi service. Di lab, menulis ke `/etc` dan `/usr` gagal dengan `Read-only file system`,
      sedangkan path di `ReadWritePaths` tetap bisa ditulis. Karena password sudah pernah terekspos, **rotasi** password itu.
    tests:
      pass:
        - |
          chmod 600 /etc/api/api.env
          EnvironmentFile=/etc/api/api.env
          NoNewPrivileges=yes
          ProtectSystem=strict
          ReadWritePaths=/srv/api/storage
          PrivateTmp=yes
          systemctl daemon-reload
          systemctl restart api
          systemd-analyze security api
        - |
          LoadCredential=db_password:/etc/api/db_password
          chown root:root /etc/api/db_password
          ProtectSystem=strict
          ProtectHome=yes
          ReadWritePaths=/srv/api/storage
          systemctl daemon-reload
      fail:
        - |
          Environment=DB_PASSWORD=NewS3cret
          ProtectSystem=strict
          systemctl daemon-reload
        - |
          EnvironmentFile=/etc/api/api.env
          chmod 644 /etc/api/api.env
          ProtectSystem=strict
          NoNewPrivileges=yes
          systemctl daemon-reload
        - |
          EnvironmentFile=/etc/api/api.env
          chmod 600 /etc/api/api.env
          systemctl restart api
    source: https://www.freedesktop.org/software/systemd/man/latest/systemd.exec.html

---

# Service & systemd

Hampir semua distro server modern (Ubuntu, Debian, RHEL, Fedora, SUSE) memakai **systemd** sebagai PID 1.
systemd menyalakan service saat boot, menjaganya tetap hidup, mengumpulkan log, menjalankan job terjadwal, dan
membatasi apa yang boleh dilakukan service. Modul ini membahas `systemctl`, unit file, drop-in, journal,
timer, hardening, dan cara membaca kegagalan service.

> [!NOTE]
> Semua perilaku di modul ini diuji ulang di Ubuntu 24.04 dengan systemd 255 yang berjalan sebagai PID 1 di
> container Docker `--privileged`. Distro lain dengan versi systemd berbeda bisa punya default atau fitur yang
> sedikit lain. Cek `systemctl --version`. Alpine memakai OpenRC, bukan systemd.

## 1. Unit dan lokasinya

systemd mengelola **unit**. Jenis yang paling sering dipakai:

| Jenis | Contoh | Fungsi |
|---|---|---|
| `.service` | `nginx.service` | Proses/daemon |
| `.timer` | `logrotate.timer` | Jadwal yang memicu service |
| `.socket` | `docker.socket` | Socket activation |
| `.target` | `multi-user.target` | Kelompok unit (pengganti runlevel) |
| `.mount` | `srv.mount` | Mount point |

| Lokasi | Pemilik | Prioritas |
|---|---|---|
| `/etc/systemd/system/` | **Admin** (kamu) | Tertinggi |
| `/run/systemd/system/` | Runtime | Menengah |
| `/usr/lib/systemd/system/` | Paket | Terendah — jangan diedit |

## 2. `systemctl` sehari-hari

```bash
systemctl status nginx
systemctl start|stop|restart nginx
systemctl reload nginx              # baca ulang config aplikasi (ExecReload)
systemctl enable --now nginx        # boot + sekarang
systemctl disable --now nginx
systemctl is-active nginx; systemctl is-enabled nginx
systemctl mask cups                 # tidak bisa di-start sama sekali
systemctl --failed
systemctl daemon-reload             # setelah mengubah FILE UNIT
```

> [!WARNING]
> Ada dua pasang perintah yang sering tertukar:
> - `enable` ≠ `start`. Di lab, setelah `disable`, service tetap `active` sampai di-stop.
> - `reload` ≠ `daemon-reload`. `reload` untuk config aplikasi, `daemon-reload` untuk file unit.
>
> Lupa `daemon-reload` memunculkan peringatan `changed on disk`, dan systemd tetap memakai unit versi lama.

## 3. Anatomi unit service

```ini
[Unit]
Description=Demo app
After=network.target postgresql.service   # urutan
Wants=postgresql.service                  # dependency lunak

[Service]
Type=exec
User=app
Group=app
WorkingDirectory=/srv/app
EnvironmentFile=/etc/app.env
ExecStart=/usr/local/bin/app --port 8080
Restart=on-failure
RestartSec=2
TimeoutStopSec=60

[Install]
WantedBy=multi-user.target
```

| Directive | Catatan dari lab |
|---|---|
| `Type=simple` (default) | `start` sukses **segera setelah fork** — binary yang tidak ada tetap `rc=0` |
| `Type=exec` | `start` gagal bila exec gagal (`rc=1`) — lebih jujur untuk skrip deploy |
| `Type=oneshot` | Untuk job yang selesai (dipakai timer); boleh banyak `ExecStart` |
| `Type=notify` | Aplikasi memberi tahu "siap" lewat sd_notify |
| `User=` | Lingkungan service minimal: `PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin`, **tanpa** `.bashrc` |
| `ExecStart=` | Path relatif diterima systemd 255 (dicari di PATH tetap), tapi path absolut tetap lebih jelas |
| `%` | Specifier. Di lab, `date +%F` membuat unit **ditolak** (`Failed to resolve unit specifiers`); tulis `%%F` |
| `Restart=on-failure` | Lab: `kill -9` → restart; exit 0 → tidak |

## 4. Drop-in: mengubah unit milik paket

```bash
systemctl edit nginx        # → /etc/systemd/system/nginx.service.d/override.conf
systemctl cat nginx         # lihat unit + semua drop-in yang berlaku
systemd-delta --type=extended
```

```ini
# override.conf
[Service]
ExecStart=
ExecStart=/usr/sbin/nginx -g 'daemon on; master_process on;' -c /etc/nginx/custom.conf
LimitNOFILE=65535
```

Setting yang berupa daftar (`ExecStart`, `Environment`, `ReadWritePaths`, ...) **ditambahkan** oleh drop-in.
Baris kosong `ExecStart=` mengosongkannya dulu. Di lab, tanpa baris itu: `Service has more than one ExecStart=
setting ... Refusing.`

## 5. Journal

```bash
journalctl -u nginx -f                # follow
journalctl -u nginx --since -1h       # atau "1 hour ago", "2026-11-20 09:00"
journalctl -u nginx -n 100 --no-pager
journalctl -p err -b                  # error sejak boot ini; -b -1 = boot sebelumnya
journalctl -k                         # pesan kernel (OOM killer, disk error)
journalctl -xeu nginx                 # akhir log + penjelasan (saran dari systemctl sendiri)
journalctl -u app -o json -n 1        # untuk dikirim ke pipeline log
journalctl --disk-usage; journalctl --vacuum-size=500M
```

> [!TIP]
> Di Ubuntu, journal persisten disimpan di `/var/log/journal`. Kalau direktori itu tidak ada (sering di image
> minimal), log hilang setiap reboot dan `-b -1` kosong. Batasi ukurannya lewat `SystemMaxUse=` di
> `/etc/systemd/journald.conf`.

## 6. Restart, start limit, dan stop

```mermaid
flowchart LR
    A[proses keluar] --> B{Restart= cocok?}
    B -- tidak --> F[inactive / failed]
    B -- ya --> C[tunggu RestartSec]
    C --> D{> StartLimitBurst<br/>dalam StartLimitIntervalSec?}
    D -- tidak --> S[start lagi]
    D -- ya --> L[failed: start request<br/>repeated too quickly]
    L --> R[perbaiki → systemctl reset-failed]
```

| Properti | Default (lab) | Arti |
|---|---|---|
| `StartLimitBurst` / `StartLimitIntervalSec` | 5 / 10s | Lebih dari 5 start dalam 10 detik → berhenti mencoba |
| `TimeoutStartSec` / `TimeoutStopSec` | 1min 30s | Batas waktu start / stop |
| `KillSignal` | 15 (SIGTERM) | Sinyal pertama saat stop |
| `KillMode` | `control-group` | Semua proses di cgroup ikut dihentikan |

Di lab:
- Service dengan `Restart=always` yang crash berulang berhenti setelah `NRestarts=5`.
- Service yang mengabaikan SIGTERM di-SIGKILL setelah `TimeoutStopSec`.
- Semua anak `sleep` ikut hilang saat stop, karena `KillMode=control-group`.

## 7. Timer: pengganti cron

```ini
# /etc/systemd/system/report.service
[Service]
Type=oneshot
User=app
ExecStart=/usr/bin/php /srv/app/artisan report:generate

# /etc/systemd/system/report.timer
[Timer]
OnCalendar=Mon..Fri *-*-* 02:00:00
Persistent=true             # jalankan yang terlewat saat server mati
RandomizedDelaySec=5m       # hindari semua server jalan bersamaan

[Install]
WantedBy=timers.target
```

```bash
systemctl enable --now report.timer
systemctl list-timers
systemd-analyze calendar 'Mon..Fri *-*-* 02:00:00'
systemctl start report.service      # uji jalankan sekarang
```

| Cron | systemd `OnCalendar` |
|---|---|
| `0 2 * * 1-5` | `Mon..Fri *-*-* 02:00:00` |
| `*/15 * * * *` | `*:0/15` |
| `@daily` | `daily` (= `*-*-* 00:00:00`) |

Keunggulan timer: log masuk journal (`journalctl -u report`), tidak tumpang tindih (service oneshot yang masih
jalan tidak di-start ulang), bisa memakai semua sandboxing systemd, dan ada `Persistent=`.

## 8. Hardening dan secret

```ini
[Service]
User=app
NoNewPrivileges=yes
ProtectSystem=strict
ReadWritePaths=/srv/app/storage
ProtectHome=yes
PrivateTmp=yes
```

```bash
systemd-analyze security app     # lab: 9.2 UNSAFE → 8.3 EXPOSED setelah 5 directive di atas
```

Di lab, dengan `ProtectSystem=strict` + `ReadWritePaths=/srv/app`, service bisa menulis ke `/srv/app`, tetapi
`touch /etc/x` dan `touch /usr/local/x` gagal: `Read-only file system`. Aplikasi yang terkompromi tidak bisa
mengubah binary atau config sistem.

> [!DANGER]
> Jangan taruh secret di `Environment=`. Di lab, user tanpa hak apa pun (`nobody`) membaca
> `Environment=... DB_PASSWORD=inline` hanya dengan `systemctl show -p Environment app`, karena properti unit
> terbuka lewat D-Bus. Nilai dari `EnvironmentFile=` (file mode 600) tidak tampil di properti itu. Dokumentasi
> systemd sendiri menyatakan environment variable tidak cocok untuk secret dan menyarankan `LoadCredential=`.
> Sumber: [systemd.exec(5) — Environment=](https://www.freedesktop.org/software/systemd/man/latest/systemd.exec.html#Environment=),
> [systemd Credentials](https://systemd.io/CREDENTIALS/)

## 9. Troubleshooting service yang gagal

```mermaid
flowchart TD
    S[service gagal] --> ST[systemctl status x]
    ST --> C{status=2xx?}
    C -- 203/EXEC --> E[ExecStart: path, bit x, shebang, noexec]
    C -- 217/USER --> U[User= tidak ada]
    C -- 200/CHDIR --> W[WorkingDirectory tidak ada]
    C -- exit lain --> J[journalctl -xeu x<br/>error dari aplikasi]
    ST --> L{start request<br/>repeated too quickly?}
    L -- ya --> R[perbaiki → reset-failed → start]
    ST --> T{Result: timeout?}
    T -- ya --> TS[TimeoutStartSec / Type= salah<br/>forking vs simple]
    J --> V[systemd-analyze verify unit]
```

| Kode | Arti (diuji di lab) |
|---|---|
| `200/CHDIR` | `WorkingDirectory=` tidak ada |
| `203/EXEC` | `ExecStart=` tidak bisa di-exec |
| `216/GROUP` | `Group=` tidak ada |
| `217/USER` | `User=` tidak ada |
| `status=9/KILL` + `Result: timeout` | Tidak berhenti dalam `TimeoutStopSec` |

> [!INSIGHT]
> systemd hanya memperingatkan key yang tidak dikenal, lalu tetap menjalankan unit. Di lab, typo `Restar=always`
> menghasilkan satu baris `Unknown key name 'Restar' ... ignoring` di journal, dan service berjalan **tanpa**
> restart otomatis. Masalahnya baru ketahuan saat service crash di production. Jalankan
> `systemd-analyze verify` di pipeline CI untuk setiap unit file.
> Sumber: [systemd-analyze(1)](https://www.freedesktop.org/software/systemd/man/latest/systemd-analyze.html)

## 10. Ringkasan kebiasaan aman

| Kebiasaan | Kenapa |
|---|---|
| Unit sendiri di `/etc/systemd/system`, override lewat `systemctl edit` | File paket bisa ditimpa saat upgrade |
| `daemon-reload` setelah mengubah unit | Kalau lupa, unit lama yang dipakai |
| `Type=exec` (atau `notify`) daripada `simple` | `systemctl start` melaporkan kegagalan |
| `systemd-analyze verify` di CI | Typo directive diabaikan diam-diam |
| `Restart=on-failure` + `RestartSec` | Pulih dari crash tanpa loop yang terlalu cepat |
| `TimeoutStopSec` ≥ durasi job terpanjang | Graceful shutdown tidak dipotong SIGKILL |
| `User=` non-root + `ProtectSystem=strict` + `ReadWritePaths` | Membatasi dampak bila service dibobol |
| Secret di `LoadCredential`/`EnvironmentFile` 600, bukan `Environment=` | `systemctl show` terbuka untuk semua user |
