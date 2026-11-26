---
format: 1
id: rt-reconnaissance-scanning
course:
  slug: redteam
  title: Red Team (Lab Pentest)
  description: Sisi ofensif dari DevSecOps — metodologi red team dipelajari di lab terisolasi milik sendiri (VirtualBox host-only, Kali Linux + Metasploitable 2) untuk memahami cara penyerang bekerja dan membangun pertahanannya (purple team). Recon, enumerasi, analisis kerentanan, eksploitasi, post-exploitation, dan pelaporan. SEMUA hanya pada target milik sendiri dengan izin; teknik dipetakan ke kontrol pertahanan di board DevSecOps.
title: Reconnaissance & Scanning
summary: "Enumeration is key — eksploitasi hanya sebaik recon yang mendahuluinya. Modul ini memetakan target dengan nmap: host discovery, port scanning (-p-, -Pn, timing), deteksi servis & versi (-sV), skrip NSE (-sC/--script), dan deteksi OS (-A). Hasilnya daftar port terbuka + versi servis — bahan baku analisis kerentanan (modul berikutnya). Semua teknik diverifikasi di lab, dan dipetakan ke sisi blue: scanning itu berisik & terdeteksi."
order: 2
version: 1
status: verified
updated: '2026-11-25'
tested_on:
  - 'Diverifikasi penulis di jaringan Docker terisolasi (10.10.20.0/24): target Debian 12 dengan SSH+Apache+vsftpd, attacker ber-nmap'
  - 'nmap -Pn -p- → 21/22/80 open; nmap -sV → vsftpd 3.0.3, OpenSSH 9.2p1, Apache 2.4.71; nmap -sC → ssh-hostkey/http-title/http-server-header'
  - 'Versi servis di lab penulis berbeda dari Metasploitable 2 (yg punya vsftpd 2.3.4 dll), TAPI perintah & format output nmap identik — jalankan perintah yang sama ke IP Metasploitable-mu'
objectives:
  - id: discovery
    text: Menemukan host & melewati blokir ping (-Pn)
  - id: portscan
    text: Memindai port (semua port, timing, tipe scan)
  - id: version
    text: Mendeteksi servis & versi (-sV) untuk mengarahkan serangan
  - id: scripts
    text: Memakai skrip NSE (-sC/--script) untuk enumerasi otomatis
  - id: osdetect
    text: Mendeteksi OS & memakai scan agresif (-A)
  - id: output
    text: Menyimpan output & memahami jejak scanning (purple)
sources:
  - title: Nmap — Reference Guide
    url: https://nmap.org/book/man.html
  - title: Nmap — Host Discovery & Port Scanning
    url: https://nmap.org/book/man-host-discovery.html
  - title: Nmap Scripting Engine (NSE)
    url: https://nmap.org/book/nse.html
  - title: Metasploitable 2 — daftar servis
    url: https://docs.rapid7.com/metasploit/metasploitable-2/
  - title: MITRE ATT&CK — Active Scanning (T1595) & Network Service Discovery (T1046)
    url: https://attack.mitre.org/techniques/T1046/
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: version
    prompt: Mengapa mendeteksi versi servis (bukan sekadar "port 21 terbuka") sangat penting dalam recon?
    answers: ['versi spesifik menentukan kerentanan/exploit yang cocok; "ftp terbuka" belum berguna, "vsftpd 2.3.4" mengarah ke exploit tertentu']
    patterns: ['versi[^\n]*(spesifik|tepat|menentukan)[^\n]*(kerentanan|exploit|cve)', '(exploit|cve|kerentanan)[^\n]*(cocok|sesuai|spesifik)[^\n]*versi', '(tahu|tanpa) versi[^\n]*(exploit|cve|rentan)']
    tests:
      fail: ['versi tidak penting', 'cukup tahu port terbuka', 'semua versi sama rentannya', 'versi hanya kosmetik']
    explanation: 'Port terbuka saja ("21/ftp open") belum mengarahkanmu ke mana pun — tapi **versi spesifik** ("vsftpd 2.3.4") langsung mengarah ke kerentanan/exploit yang diketahui (vsftpd 2.3.4 punya backdoor terkenal). Di lab penulis, `nmap -sV` mengungkap `vsftpd 3.0.3`, `OpenSSH 9.2p1`, `Apache 2.4.71`; di Metasploitable-mu akan muncul versi tua (vsftpd 2.3.4, dll). Versi = kunci ke tahap analisis kerentanan (modul berikutnya). Sisi blue: inilah kenapa menyembunyikan versi/banner (board DevSecOps modul 02) menyulitkan penyerang.'
    source: https://nmap.org/book/man.html

  - id: a02
    tier: A
    type: command
    objective: portscan
    prompt: Pindai SEMUA 65535 port TCP pada target `192.168.56.101`.
    answers: ['nmap -p- 192.168.56.101']
    patterns: ['^nmap[ \t].*-p-[ \t]*.*192\.168\.56\.101', '^nmap[ \t].*(-p-|-p 1-65535)[ \t]']
    tests:
      pass:
        - 'nmap -p- 192.168.56.101'
        - 'nmap -p- -T4 192.168.56.101'
        - 'nmap -p 1-65535 192.168.56.101'
      fail:
        - 'nmap 192.168.56.101'
        - 'nmap -p 80 192.168.56.101'
        - 'nmap -F 192.168.56.101'
    explanation: '`nmap -p-` memindai semua 65535 port TCP (default nmap hanya ~1000 port teratas — bisa melewatkan servis di port tak lazim). Di lab penulis, `nmap -Pn -p-` menemukan 21/22/80 dalam <1 detik. Penyerang teliti selalu scan full-port minimal sekali: admin sering menyembunyikan servis di port tinggi (security by obscurity — yang BUKAN pertahanan nyata, board DevSecOps). `-p 1-65535` setara. Tambah `-T4`/`--min-rate` untuk mempercepat. Jangan hanya scan default lalu menyimpulkan "cuma 3 port".'
    source: https://nmap.org/book/man-port-scanning.html

  - id: a03
    tier: A
    type: command
    objective: version
    prompt: Deteksi servis DAN versinya pada port target `192.168.56.101`.
    answers: ['nmap -sV 192.168.56.101']
    patterns: ['^nmap[ \t].*-sV[ \t]*.*192\.168\.56\.101', '^nmap[ \t].*-sV([ \t]|$)']
    tests:
      pass:
        - 'nmap -sV 192.168.56.101'
        - 'nmap -sV -p- 192.168.56.101'
        - 'nmap -sС -sV 192.168.56.101'
      fail:
        - 'nmap 192.168.56.101'
        - 'nmap -sn 192.168.56.101'
        - 'nmap -p- 192.168.56.101'
    explanation: '`nmap -sV` melakukan version detection — nmap berinteraksi dengan tiap port terbuka untuk menebak servis & versinya dari banner/respons. Di lab penulis menghasilkan `vsftpd 3.0.3`, `OpenSSH 9.2p1 Debian`, `Apache httpd 2.4.71`. Ini langkah recon paling berharga: versi → kerentanan yang diketahui. Gabungkan dengan `-p-` untuk scan semua port + versi sekaligus. Tambah `--version-intensity` untuk kedalaman. Output versi inilah yang kamu bawa ke `searchsploit` di modul analisis kerentanan.'
    source: https://nmap.org/book/vscan.html

  - id: a04
    tier: A
    type: command
    objective: discovery
    prompt: Pindai target `192.168.56.101` dengan MELEWATI host discovery (anggap host hidup walau ping diblokir firewall).
    answers: ['nmap -Pn 192.168.56.101']
    patterns: ['^nmap[ \t].*-Pn[ \t]*.*192\.168\.56\.101', '^nmap[ \t].*-Pn([ \t]|$)']
    tests:
      pass:
        - 'nmap -Pn 192.168.56.101'
        - 'nmap -Pn -sV 192.168.56.101'
      fail:
        - 'nmap -sn 192.168.56.101'
        - 'nmap -A 192.168.56.101'
        - 'ping 192.168.56.101'
    explanation: '`-Pn` ("no ping") menyuruh nmap **melewati** host discovery dan langsung scan port, menganggap host hidup. Berguna saat host memblokir ICMP (firewall) sehingga ping sweep biasa menyimpulkan "mati" padahal servisnya jalan. Tanpa `-Pn`, nmap yang tak dapat balasan ping bisa melewatkan host sepenuhnya. Lawannya `-sn` (host discovery SAJA, tanpa port scan — modul 01). Catatan: `-Pn` lebih lambat (scan semua port meski host benar-benar mati) dan lebih berisik. Di lab penulis dipakai karena cepat & pasti.'
    source: https://nmap.org/book/man-host-discovery.html

  - id: a05
    tier: A
    type: command
    objective: scripts
    prompt: Jalankan skrip NSE default nmap terhadap target `192.168.56.101` (enumerasi otomatis dasar).
    answers: ['nmap -sC 192.168.56.101']
    patterns: ['^nmap[ \t].*-sC[ \t]*.*192\.168\.56\.101', '^nmap[ \t].*(-sC|--script[ =]default)([ \t]|$)']
    tests:
      pass:
        - 'nmap -sC 192.168.56.101'
        - 'nmap -sC -sV 192.168.56.101'
        - 'nmap --script default 192.168.56.101'
      fail:
        - 'nmap 192.168.56.101'
        - 'nmap -sV 192.168.56.101'
        - 'nmap -p- 192.168.56.101'
    explanation: '`-sC` menjalankan set skrip NSE "default" (aman & berguna) terhadap port terbuka — enumerasi otomatis: judul HTTP, header server, hostkey SSH, cek anonymous FTP, dll. Di lab penulis, `-sC` memunculkan `ssh-hostkey`, `http-title: Apache2 Debian Default Page`, `http-server-header`. Setara `--script default`. NSE (Nmap Scripting Engine) sangat kuat: ada ratusan skrip (`--script vuln` untuk cek kerentanan, `--script smb-enum-*`, dll). Di Kali, skrip tersedia; di Alpine perlu paket `nmap-scripts`. Kombinasi populer: `-sC -sV` (sering disingkat jadi bagian `-A`).'
    source: https://nmap.org/book/nse.html

  - id: a06
    tier: A
    type: command
    objective: osdetect
    prompt: Lakukan scan "agresif" (OS detection + versi + skrip default + traceroute) pada `192.168.56.101`.
    answers: ['nmap -A 192.168.56.101']
    patterns: ['^nmap[ \t].*-A[ \t]*.*192\.168\.56\.101', '^nmap[ \t].*-A([ \t]|$)']
    tests:
      pass:
        - 'nmap -A 192.168.56.101'
        - 'nmap -A -p- 192.168.56.101'
      fail:
        - 'nmap -sV 192.168.56.101'
        - 'nmap -sC 192.168.56.101'
        - 'nmap -O 192.168.56.101'
    explanation: '`-A` = scan agresif: menggabungkan **OS detection (`-O`) + version (`-sV`) + skrip default (`-sC`) + traceroute** dalam satu perintah. Praktis untuk gambaran lengkap cepat, tapi **berisik** (banyak probe → mudah terdeteksi, board DevSecOps modul 09). OS detection (`-O` sendiri) menebak sistem operasi dari karakteristik stack TCP/IP — berguna untuk memilih exploit yang cocok OS-nya. Di lab/VirtualBox hasilnya akurat; di balik firewall/NAT bisa meleset. Untuk recon senyap, lebih baik scan bertahap daripada langsung `-A`.'
    source: https://nmap.org/book/man.html

  - id: a07
    tier: A
    type: text
    objective: portscan
    prompt: Apa beda status port "open", "closed", dan "filtered" dalam hasil nmap?
    answers: ['open = ada servis menerima koneksi; closed = host balas tapi tak ada servis; filtered = firewall memblokir sehingga tak ada jawaban jelas']
    patterns: ['open[^\n]*(servis|menerima|mendengar|listen)', 'closed[^\n]*(balas|rst|tak ada servis|tertutup)', 'filtered[^\n]*(firewall|blokir|tak ada (jawaban|balasan)|drop)']
    tests:
      fail: ['ketiganya sama', 'filtered berarti pasti open', 'closed berarti host mati', 'open berarti host mati']
    explanation: 'Tiga status utama: **open** = ada servis aktif menerima koneksi (sasaran utama). **closed** = host membalas (host hidup) tapi tak ada servis di port itu (balas RST). **filtered** = nmap tak dapat jawaban jelas — biasanya **firewall** men-drop paket (board DevSecOps modul 03: iptables default-deny membuat port tampak filtered, bukan closed). Membedakannya penting: "filtered" memberi petunjuk ada firewall; "closed" berarti host hidup tanpa servis di situ. Ada juga open|filtered (umum di UDP) saat nmap tak yakin.'
    source: https://nmap.org/book/man-port-scanning.html

  - id: a08
    tier: A
    type: command
    objective: output
    prompt: Simpan hasil scan ke semua format sekaligus dengan basename `scan` (normal + XML + greppable).
    answers: ['nmap -oA scan 192.168.56.101']
    patterns: ['^nmap[ \t].*-oA[ \t]+\S+', '^nmap[ \t].*-oA[ \t]']
    tests:
      pass:
        - 'nmap -oA scan 192.168.56.101'
        - 'nmap -sV -oA scan 192.168.56.101'
      fail:
        - 'nmap -oN scan 192.168.56.101'
        - 'nmap 192.168.56.101 > scan.txt'
        - 'nmap -o scan 192.168.56.101'
    explanation: '`-oA <basename>` menyimpan output ke **tiga format** sekaligus: `.nmap` (normal/terbaca manusia), `.xml` (untuk tool lain/Metasploit `db_import`), `.gnmap` (greppable). Dokumentasi adalah bagian pentest profesional (modul 01) — simpan SEMUA output scan untuk laporan & telusur ulang. `-oN` (normal saja), `-oX` (XML saja), `-oG` (greppable saja) jika hanya butuh satu. Output XML berguna untuk mengimpor temuan ke Metasploit (modul eksploitasi) agar tidak mengetik ulang target.'
    source: https://nmap.org/book/man-output.html

  - id: a09
    tier: A
    type: text
    objective: version
    prompt: Setelah tahu "vsftpd 2.3.4" di target, apa langkah logis berikutnya dalam metodologi?
    answers: ['cari kerentanan/exploit yang diketahui untuk versi itu (vuln analysis — mis. searchsploit/cve)']
    patterns: ['(cari|searchsploit|lookup)[^\n]*(exploit|kerentanan|cve)', 'vuln(erability)? analysis|analisis kerentanan', '(cocokkan|cek)[^\n]*versi[^\n]*(cve|exploit|database)']
    tests:
      fail: ['langsung matikan target', 'abaikan versinya', 'scan ulang dari awal', 'reboot kali']
    explanation: 'Alur metodologi (modul 01): recon/enumerasi → **analisis kerentanan** → eksploitasi. Setelah `-sV` memberi versi spesifik, langkah berikutnya **mencari kerentanan/exploit yang diketahui** untuk versi itu: `searchsploit vsftpd 2.3.4`, basis data CVE, atau `nmap --script vuln`. vsftpd 2.3.4 misalnya punya backdoor terdokumentasi. Ini menghubungkan recon ke eksploitasi: versi → CVE/exploit → akses. Jangan loncat ke "exploit acak" — biarkan temuan versi mengarahkanmu (modul analisis kerentanan berikutnya).'
    source: https://nmap.org/book/man.html

  - id: a10
    tier: A
    type: text
    objective: output
    prompt: Dari sisi blue team, bagaimana aktivitas port scanning (-p-, -sV) terlihat/terdeteksi?
    answers: ['banyak koneksi/probe ke banyak port dari satu sumber dalam waktu singkat — pola scan yang terlihat di log/ids']
    patterns: ['(banyak|ribuan)[^\n]*(koneksi|probe|paket)[^\n]*(port|dari satu sumber)', '(log|ids|firewall)[^\n]*(scan|probe|pola)', 'pola scan[^\n]*(terlihat|terdeteksi)']
    tests:
      fail: ['port scan tidak terlihat', 'blue team tidak bisa mendeteksi', 'scanning senyap sempurna', 'tidak meninggalkan jejak']
    explanation: 'Port scan (apalagi `-p-` + `-A`) menghasilkan **banyak koneksi/probe ke banyak port dari satu IP dalam waktu singkat** — pola yang sangat khas di log firewall/servis dan IDS (board DevSecOps modul 09: deteksi scanning). Blue team melihat lonjakan koneksi gagal/SYN ke berbagai port = seseorang memetakan kita. Pertahanan: rate-limit/deteksi scan (fail2ban `recidive`, IDS), kurangi eksposur, segmentasi. Red team senyap memperlambat scan (`-T1`, probe terbatas) untuk mengurangi jejak — tapi senyap sempurna tak ada. Memahami jejakmu = memahami cara mendeteksinya.'
    source: https://attack.mitre.org/techniques/T1046/

  - id: a11
    tier: A
    type: command
    objective: scripts
    prompt: Jalankan skrip NSE kategori "vuln" (mendeteksi kerentanan yang diketahui) terhadap `192.168.56.101`.
    answers: ['nmap --script vuln 192.168.56.101']
    patterns: ['^nmap[ \t].*--script[ =]vuln([ \t]|$).*192\.168|^nmap[ \t].*--script[ =]vuln']
    tests:
      pass:
        - 'nmap --script vuln 192.168.56.101'
        - 'nmap -sV --script vuln 192.168.56.101'
      fail:
        - 'nmap -sC 192.168.56.101'
        - 'nmap --script safe 192.168.56.101'
        - 'nmap -A 192.168.56.101'
    explanation: '`--script vuln` menjalankan kategori skrip NSE yang mengecek **kerentanan yang diketahui** (mis. `http-vuln-*`, cek CVE tertentu). Menjembatani recon dan analisis kerentanan secara otomatis. Catatan: skrip vuln lebih berisik & kadang intrusif (beberapa benar-benar menguji exploit ringan) — pakai dalam scope. Hasilnya petunjuk, tetap verifikasi manual (bisa false-positive). Kombinasi kuat: `nmap -sV --script vuln`. Selain `vuln`, ada kategori `default`, `safe`, `auth`, `brute`, `discovery` — jalankan `--script-help <nama>` untuk detail.'
    source: https://nmap.org/book/nse-usage.html

  - id: a12
    tier: A
    type: text
    objective: portscan
    prompt: Mengapa scan default nmap (tanpa -p-) bisa menyesatkan, dan kapan kamu wajib -p-?
    answers: ['default hanya ~1000 port teratas; servis di port tak lazim terlewat — wajib -p- untuk pemetaan menyeluruh']
    patterns: ['default[^\n]*(1000|seribu|teratas|top)[^\n]*port', '(port (tak lazim|tinggi|aneh)|servis tersembunyi)[^\n]*(terlewat|lewat|miss)', '-p-[^\n]*(menyeluruh|lengkap|semua)']
    tests:
      fail: ['default sudah mencakup semua port', 'port tinggi tidak pernah dipakai', 'tidak ada bedanya', '-p- tidak berguna']
    explanation: 'nmap default hanya memindai ~1000 port paling umum — cepat, tapi **melewatkan** servis yang sengaja/tak sengaja ada di port tak lazim (mis. SSH di 2222, web admin di 8443, backdoor di port tinggi). Admin kadang "menyembunyikan" servis di port aneh (security by obscurity — bukan pertahanan nyata). Jadi lakukan `-p-` minimal sekali untuk pemetaan menyeluruh, lalu `-sV`/`-sC` pada port yang ditemukan. Menyimpulkan "cuma 3 port" dari scan default adalah kesalahan recon klasik yang membuatmu melewatkan jalan masuk.'
    source: https://nmap.org/book/man-port-scanning.html

  - id: a13
    tier: A
    type: text
    objective: version
    prompt: Apa "banner grabbing", dan bagaimana hubungannya dengan -sV?
    answers: ['membaca banner/identitas yang dikirim servis saat koneksi untuk tahu software & versinya; -sV mengotomasikannya']
    patterns: ['(baca|ambil|grab)[^\n]*banner[^\n]*(servis|versi|software)', 'banner[^\n]*(identitas|versi|software)[^\n]*(servis)?', '-sV[^\n]*(otomat|banner)']
    tests:
      fail: ['banner grabbing menghapus banner', 'tidak berhubungan dengan versi', 'hanya untuk web', 'banner grabbing adalah exploit']
    explanation: 'Banyak servis mengirim **banner** (teks identitas) saat kamu terhubung — mis. server FTP membalas `220 (vsFTPd 2.3.4)`, SSH mengirim `SSH-2.0-OpenSSH_9.2p1`. "Banner grabbing" = membaca banner itu untuk tahu software & versi (bisa manual via `nc <ip> 21` atau `telnet`). `nmap -sV` **mengotomasikan & memperkuat** ini (plus probe aktif untuk servis yang tak memberi banner jelas). Sisi blue: inilah kenapa `server_tokens off` & menyembunyikan banner versi (board DevSecOps modul 02) menyulitkan recon — kamu menghapus petunjuk gratis ini.'
    source: https://nmap.org/book/vscan.html

  - id: a14
    tier: A
    type: command
    objective: portscan
    prompt: Percepat scan dengan template timing agresif (T4) pada `192.168.56.101`.
    answers: ['nmap -T4 192.168.56.101']
    patterns: ['^nmap[ \t].*-T4[ \t]*.*192\.168|^nmap[ \t].*-T[45]([ \t]|$)']
    tests:
      pass:
        - 'nmap -T4 192.168.56.101'
        - 'nmap -T4 -p- 192.168.56.101'
        - 'nmap -T5 192.168.56.101'
      fail:
        - 'nmap -T0 192.168.56.101'
        - 'nmap 192.168.56.101'
        - 'nmap --slow 192.168.56.101'
    explanation: '`-T<0-5>` mengatur template timing: `-T0`/`-T1` sangat lambat & senyap (menghindari deteksi IDS), `-T3` default, `-T4` agresif (cepat, cocok untuk LAN/lab), `-T5` tercepat (bisa tak akurat/kehilangan paket). Di lab tertutup `-T4`/`-T5` aman & cepat. Di engagement nyata, timing adalah trade-off kecepatan vs **kesenyapan**: scan cepat = lebih berisik = lebih mudah terdeteksi blue team (modul 09). Kontrol lain: `--min-rate`/`--max-rate` (paket per detik). Pilih timing sesuai tujuan: lab = cepat, operasi senyap = lambat.'
    source: https://nmap.org/book/man-performance.html

  - id: a15
    tier: A
    type: text
    objective: osdetect
    prompt: Mengapa mengetahui OS target (via -O/-A) berguna bagi penyerang?
    answers: ['exploit & teknik sering spesifik OS/versi; tahu OS mempersempit exploit yang cocok dan cara post-ex']
    patterns: ['exploit[^\n]*(spesifik|cocok|sesuai)[^\n]*(os|sistem operasi|platform)', '(mempersempit|pilih|arah)[^\n]*(exploit|teknik)[^\n]*os', '(os|sistem operasi)[^\n]*menentukan[^\n]*(exploit|teknik)']
    tests:
      fail: ['os tidak berpengaruh', 'semua exploit lintas-os', 'os hanya kosmetik', 'tidak berguna']
    explanation: 'Exploit & teknik post-exploitation sering **spesifik OS/arsitektur**: exploit kernel Linux beda dengan Windows; payload Meterpreter, jalur privilege escalation, dan perintah pasca-akses bergantung OS. Mengetahui "ini Ubuntu Linux 2.6.x" (seperti Metasploitable) mempersempit pilihan ke exploit yang cocok dan menghindari membuang waktu pada yang salah platform. `-O` menebak OS dari sidik jari stack TCP/IP; `-A` menyertakannya. Di dunia nyata di balik firewall/NAT tebakan bisa meleset — korelasikan dengan petunjuk lain (banner, servis khas OS).'
    source: https://nmap.org/book/osdetect.html

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: command
    objective: version
    prompt: Lakukan scan menyeluruh dalam satu perintah - semua port, deteksi versi, dan skrip default - pada `192.168.56.101`.
    answers: ['nmap -p- -sV -sC 192.168.56.101']
    patterns: ['^(?=.*-p-)(?=.*(-sV|-A))(?=.*(-sC|-A))nmap[ \t].*192\.168', '^(?=.*-p-)(?=.*(-sV|-A))(?=.*(-sC|-A))nmap[ \t]']
    tests:
      pass:
        - 'nmap -p- -sV -sC 192.168.56.101'
        - 'nmap -sC -sV -p- 192.168.56.101'
        - 'nmap -p- -A 192.168.56.101'
      fail:
        - 'nmap -sV 192.168.56.101'
        - 'nmap -p- 192.168.56.101'
        - 'nmap -sC 192.168.56.101'
    explanation: 'Menggabungkan `-p-` (semua port) + `-sV` (versi) + `-sC` (skrip default) memberi pemetaan lengkap dalam satu jalan — pola kerja umum pentester: "scan dalam sekali, analisis lama". `-p- -A` serupa (A sudah termasuk -sV -sC -O). Catatan praktik: scan `-p-` dulu untuk menemukan SEMUA port, lalu jalankan `-sV -sC` **hanya pada port yang terbuka** (lebih cepat daripada -sV ke semua 65535). Di lab, urutan ini mengungkap 21/22/80 lalu versinya (vsftpd/OpenSSH/Apache). Simpan dengan `-oA` (a08) untuk dokumentasi.'
    source: https://nmap.org/book/man.html

  - id: b02
    tier: B
    type: text
    objective: portscan
    prompt: Apa beda TCP SYN scan (-sS) dan TCP connect scan (-sT), dan kapan masing-masing dipakai?
    answers: ['-sS (half-open) tak menyelesaikan handshake, lebih cepat/senyap tapi butuh root; -sT menyelesaikan handshake penuh, tak butuh root tapi lebih berisik/terlog']
    patterns: ['-sS[^\n]*(half-open|setengah|tak (selesai|lengkap)|syn)[^\n]*(cepat|senyap|root)?', '-sT[^\n]*(penuh|lengkap|connect|full)[^\n]*(root|berisik|terlog)?', '(root|privilege)[^\n]*(-sS|syn scan)']
    tests:
      fail: ['keduanya identik', '-sS butuh menyelesaikan handshake penuh', '-sT lebih senyap dari -sS', 'tidak ada bedanya']
    explanation: '`-sS` (SYN/half-open scan) mengirim SYN, melihat SYN-ACK (port open) atau RST (closed), lalu **tak menyelesaikan** handshake (kirim RST) — lebih cepat, lebih senyap (koneksi tak penuh, sering tak ter-log aplikasi), TAPI butuh **root** (raw socket). `-sT` (connect scan) melakukan full TCP handshake via OS — tak butuh root, tapi lebih lambat & **ter-log** oleh servis (koneksi lengkap). Default nmap: `-sS` jika root, `-sT` jika tidak. Di Kali kamu root → SYN scan. Sisi blue: `-sT` meninggalkan jejak koneksi di log aplikasi; `-sS` lebih terlihat di level firewall/IDS.'
    source: https://nmap.org/book/man-port-scanning.html

  - id: b03
    tier: B
    type: command
    objective: portscan
    prompt: Pindai port UDP umum (mis. 53,69,161) pada `192.168.56.101` — banyak servis penting hanya di UDP.
    answers: ['nmap -sU -p 53,69,161 192.168.56.101']
    patterns: ['^(?=.*-sU)nmap[ \t].*192\.168', '^nmap[ \t].*-sU([ \t]|$)']
    tests:
      pass:
        - 'nmap -sU -p 53,69,161 192.168.56.101'
        - 'nmap -sU --top-ports 20 192.168.56.101'
      fail:
        - 'nmap -sS -p 53,69,161 192.168.56.101'
        - 'nmap -p 53,69,161 192.168.56.101'
        - 'nmap -sV 192.168.56.101'
    explanation: '`-sU` memindai port **UDP** — sering terlewat karena scan TCP (default) tak menyentuhnya, padahal servis penting ada di UDP: DNS (53), TFTP (69), SNMP (161), NTP (123). SNMP khususnya sering bocor info sistem (community string `public`). UDP scan **lambat** (tak ada handshake; nmap harus menunggu timeout) — jadi batasi port (`-p` atau `--top-ports`). Banyak hasil "open|filtered" karena UDP tak selalu membalas. Recon lengkap mencakup TCP **dan** UDP; melewatkan UDP bisa melewatkan jalan masuk (mis. SNMP yang membocorkan kredensial).'
    source: https://nmap.org/book/scan-methods-udp-scan.html

  - id: b04
    tier: B
    type: text
    objective: scripts
    prompt: NSE punya kategori seperti default, safe, vuln, brute, auth. Mengapa kategori "intrusive"/"brute"/"vuln" harus dipakai hati-hati?
    answers: ['bisa mengganggu/merusak servis atau melakukan aksi aktif (brute login, uji exploit) — berisiko & berisik, harus dalam scope']
    patterns: ['(ganggu|rusak|crash|down)[^\n]*(servis|target)', '(aksi aktif|brute|uji exploit|login)[^\n]*(berisik|berisiko|intrusif)', '(dalam scope|izin|hati-hati)[^\n]*(brute|vuln|intrusive)']
    tests:
      fail: ['semua skrip nse aman', 'kategori tidak berpengaruh', 'brute tidak menyentuh target', 'vuln hanya membaca']
    explanation: 'Skrip NSE dikategorikan; `default`/`safe` tidak merusak, tapi `intrusive`/`brute`/`vuln`/`exploit` **berinteraksi aktif**: `brute` mencoba login berulang (bisa mengunci akun & sangat berisik — terlihat seperti brute force di blue, modul 03/09), `vuln`/`exploit` kadang benar-benar menguji/memicu kerentanan (bisa men-crash servis rapuh). Pakai hanya **dalam scope** & dengan kesadaran risiko (di produksi bisa menjatuhkan layanan — RoE!). Di lab Metasploitable bebas. Prinsip: ketahui apa yang dilakukan skrip sebelum menjalankannya (`--script-help`).'
    source: https://nmap.org/book/nse-usage.html

  - id: b05
    tier: B
    type: command
    objective: scripts
    prompt: Jalankan skrip NSE spesifik untuk enumerasi FTP (cek anonymous login) pada port 21 target `192.168.56.101`.
    answers: ['nmap --script ftp-anon -p 21 192.168.56.101']
    patterns: ['^(?=.*--script[ =]ftp-anon)nmap[ \t].*192\.168', '^nmap[ \t].*--script[ =]ftp-anon']
    tests:
      pass:
        - 'nmap --script ftp-anon -p 21 192.168.56.101'
        - 'nmap -p 21 --script ftp-anon 192.168.56.101'
      fail:
        - 'nmap --script vuln 192.168.56.101'
        - 'nmap -sC -p 21 192.168.56.101'
        - 'ftp 192.168.56.101'
    explanation: '`--script <nama>` menjalankan skrip NSE spesifik. `ftp-anon` mengecek apakah FTP mengizinkan **anonymous login** (kredensial `anonymous:apa saja`) — temuan umum di target rentan yang membocorkan file. Metasploitable 2 punya vsftpd dengan anonymous terbuka. Pola `--script <nama>` memberimu presisi: `smb-enum-shares`, `http-enum`, `ssh-auth-methods`, dll. Gunakan nama/kategori yang sesuai servis yang ditemukan `-sV`. Ini jembatan dari "tahu port 21 FTP" ke "enumerasi mendalam servis itu" (modul 03).'
    source: https://nmap.org/book/nse-usage.html

  - id: b06
    tier: B
    type: text
    objective: version
    prompt: Di lab penulis nmap menemukan vsftpd 3.0.3; di Metasploitable 2 kamu akan lihat vsftpd 2.3.4. Apa makna perbedaan ini?
    answers: ['versi 2.3.4 jauh lebih tua & punya kerentanan terkenal (backdoor); 3.0.3 sudah ditambal — versi menentukan apakah ada exploit yang cocok']
    patterns: ['2\.3\.4[^\n]*(tua|lama|rentan|backdoor|exploit|cve)', '3\.0\.3[^\n]*(baru|ditambal|aman|patched)', 'versi[^\n]*(menentukan|beda)[^\n]*(rentan|exploit|aman)']
    tests:
      fail: ['versi tidak berpengaruh', 'keduanya sama rentannya', '3.0.3 lebih rentan dari 2.3.4', 'versi hanya angka']
    explanation: 'Perbedaan versi = perbedaan kerentanan. **vsftpd 2.3.4** (Metasploitable) punya **backdoor terkenal** (seseorang sempat menyusupkan backdoor ke rilis aslinya — memicu shell saat username berakhiran `:)`) → ada modul Metasploit siap pakai. **vsftpd 3.0.3** (lab penulis, versi modern Debian 12) sudah bersih/ditambal → exploit itu tak jalan. Inilah inti recon: angka versi menentukan apakah jalan masuk ada. Pelajaran blue (board DevSecOps modul 05): **patching** menutup justru kelas kerentanan ini — servis usang = jalan masuk, servis ter-update = pintu tertutup.'
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: b07
    tier: B
    type: command
    objective: discovery
    prompt: Lakukan scan cepat hanya port paling umum (top 100) pada `192.168.56.101` untuk gambaran awal.
    answers: ['nmap --top-ports 100 192.168.56.101']
    patterns: ['^(?=.*--top-ports[ \t]+[0-9]+)nmap[ \t].*192\.168', '^nmap[ \t].*(--top-ports[ \t]+[0-9]+|-F)([ \t]|$)']
    tests:
      pass:
        - 'nmap --top-ports 100 192.168.56.101'
        - 'nmap -F 192.168.56.101'
      fail:
        - 'nmap -p- 192.168.56.101'
        - 'nmap 192.168.56.101'
        - 'nmap -sU 192.168.56.101'
    explanation: '`--top-ports N` memindai N port paling umum (berdasarkan statistik nmap); `-F` ("fast") = top 100. Berguna untuk **gambaran awal cepat** sebelum scan `-p-` penuh yang lebih lama. Alur praktis: `-F`/`--top-ports` untuk orientasi kilat → `-p-` menyeluruh di latar → `-sV -sC` pada port terbuka. Jangan berhenti di scan cepat (bisa melewatkan port tinggi, a12), tapi bagus untuk langkah pertama saat kamu punya banyak host. Menyeimbangkan kecepatan & kelengkapan adalah seni recon.'
    source: https://nmap.org/book/man-port-scanning.html

  - id: b08
    tier: B
    type: text
    objective: output
    prompt: Mengapa menyimpan output nmap dalam format XML (-oX) berguna untuk tahap selanjutnya?
    answers: ['xml bisa diimpor ke tool lain seperti metasploit (db_import) sehingga target/servis tidak perlu diketik ulang']
    patterns: ['xml[^\n]*(impor|import|tool lain|metasploit|db_import)', '(metasploit|msf)[^\n]*(impor|db_import|baca)', '(tidak perlu|tanpa)[^\n]*(ketik ulang|input ulang)']
    tests:
      fail: ['xml hanya untuk arsip', 'format tidak berpengaruh', 'xml tidak bisa dibaca tool', 'harus ketik ulang semua']
    explanation: 'Output XML (`-oX`) terstruktur sehingga **tool lain bisa mengimpornya**. Contoh penting: Metasploit `db_import scan.xml` memasukkan semua host/port/servis ke database msf — lalu kamu bisa `hosts`, `services`, dan menargetkan exploit tanpa mengetik ulang IP/port (modul eksploitasi). Juga bisa dikonversi jadi laporan HTML (`xsltproc`). Ini bagian alur kerja rapi: scan sekali (`-oA`), pakai hasilnya di banyak tahap. Terstruktur = bisa diotomasi — tema yang sama dengan logging terstruktur di board DevSecOps (modul 09).'
    source: https://nmap.org/book/man-output.html

  - id: b09
    tier: B
    type: text
    objective: osdetect
    prompt: Metasploitable 2 akan terdeteksi sebagai Linux kernel 2.6.x yang sangat tua. Mengapa ini sinyal besar bagi penyerang?
    answers: ['kernel/os sangat tua = banyak kerentanan tak ditambal, termasuk exploit privilege escalation kernel yang diketahui']
    patterns: ['(tua|lama|usang|eol)[^\n]*(banyak|segudang|penuh)[^\n]*(kerentanan|exploit|cve)', '(banyak|segudang|penuh)[^\n]*(exploit|cve|kerentanan)[^\n]*(diketahui|publik|tak ditambal|belum ditambal)', '(exploit|privilege escalation|privesc)[^\n]*kernel']
    tests:
      fail: ['os tua lebih aman', 'tidak ada artinya', 'kernel lama tidak punya exploit', 'justru sulit dieksploitasi']
    explanation: 'OS/kernel yang sangat tua (Metasploitable: Ubuntu lawas, kernel 2.6.x dari belasan tahun lalu) = **segudang kerentanan terdokumentasi yang tak pernah ditambal**, termasuk **kernel exploit untuk privilege escalation** (dari user biasa → root). Bagi penyerang ini sinyal besar: setelah dapat shell user (modul eksploitasi), kemungkinan besar ada kernel exploit publik untuk jadi root (modul post-ex). Pelajaran blue (board DevSecOps): sistem usang tanpa patching adalah risiko berlapis — rentan di servis DAN di kernel. "Jangan jalankan yang sudah EOL" (modul 05) persis karena ini.'
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: b10
    tier: B
    type: text
    objective: output
    prompt: Sebagai purple team, bagaimana blue team bisa mempersulit tahap recon ini secara signifikan?
    answers: ['kurangi eksposur (tutup port tak perlu, sembunyikan versi/banner), segmentasi & firewall, plus deteksi & blokir pola scan']
    patterns: ['(kurangi|tutup|minim)[^\n]*(port|eksposur|servis)', '(sembunyi|server_tokens|banner)[^\n]*(versi|banner)', '(segmentasi|firewall)[^\n]*(batasi|blokir)|deteksi[^\n]*scan']
    tests:
      fail: ['recon tidak bisa dipersulit', 'blue team tak berdaya', 'biarkan semua terbuka', 'tidak ada gunanya']
    explanation: 'Recon memanfaatkan apa yang terekspos — jadi blue mempersulitnya dengan **mengurangi & menyembunyikan**: tutup port/servis yang tak perlu (perkecil attack surface), sembunyikan versi/banner (`server_tokens off`, board DevSecOps modul 02 — menghapus petunjuk versi gratis), **segmentasi jaringan & firewall** (batasi siapa bisa scan apa; port jadi `filtered` bukan `open`), dan **deteksi + blokir pola scan** (fail2ban/IDS, modul 09). Tiap langkah membuat `-sV` penyerang kurang informatif dan scan mereka lebih mudah ketahuan. Recon yang gagal/berisik = serangan yang tersendat di tahap pertama.'
    source: https://attack.mitre.org/techniques/T1046/

  - id: b11
    tier: B
    type: command
    objective: version
    prompt: Naikkan intensitas deteksi versi ke maksimum (lebih akurat untuk servis yang sulit) pada `192.168.56.101`.
    answers: ['nmap -sV --version-intensity 9 192.168.56.101']
    patterns: ['^(?=.*-sV)(?=.*--version-intensity[ \t]+[0-9])nmap[ \t]', '^(?=.*-sV)(?=.*--version-all)nmap[ \t]']
    tests:
      pass:
        - 'nmap -sV --version-intensity 9 192.168.56.101'
        - 'nmap -sV --version-all 192.168.56.101'
      fail:
        - 'nmap -sV 192.168.56.101'
        - 'nmap --version-intensity 9 192.168.56.101'
        - 'nmap -A 192.168.56.101'
    explanation: '`--version-intensity <0-9>` mengatur seberapa banyak probe yang dikirim untuk menebak versi (0=ringan/cepat, 9=maksimum/akurat; `--version-all`=9, `--version-light`=2). Intensitas tinggi membantu mengidentifikasi servis yang tak memberi banner jelas atau tak lazim, dengan biaya lebih lambat & berisik. `-sV` saja memakai intensitas default (7). Berguna saat scan awal memberi "unknown" atau tebakan ragu untuk sebuah port — naikkan intensitas untuk memastikan sebelum mencari exploit. Presisi versi penting: salah tebak versi = salah pilih exploit.'
    source: https://nmap.org/book/vscan-technique.html

  - id: b12
    tier: B
    type: text
    objective: scripts
    prompt: Apa keuntungan mengimpor hasil scan nmap (XML) ke dalam database Metasploit?
    answers: ['semua host/port/servis tersimpan & bisa dikueri (hosts/services); exploit bisa menargetkan langsung tanpa input ulang, memudahkan engagement besar']
    patterns: ['(host|port|servis)[^\n]*(tersimpan|database|db|dikueri|hosts|services)', '(exploit|modul)[^\n]*(target|langsung|tanpa (ketik|input) ulang)', 'db_import|workspace']
    tests:
      fail: ['tidak ada gunanya', 'harus ketik ulang semua', 'metasploit tidak punya database', 'xml tidak kompatibel']
    explanation: 'Metasploit punya database (via `db_import scan.xml`) yang menyimpan host, port, servis, dan nanti kredensial/loot. Keuntungannya: perintah `hosts`, `services`, `vulns` untuk mengkueri temuan; modul exploit bisa `set RHOSTS` dari database; dan `workspace` memisahkan per-engagement. Di target tunggal (Metasploitable) manfaatnya kecil, tapi di engagement banyak host ini menghemat waktu besar & menjaga keteraturan. Mengimpor recon ke msf menyambungkan tahap scanning (modul ini) ke eksploitasi (modul berikutnya) dengan mulus.'
    source: https://docs.rapid7.com/metasploit/

  - id: b13
    tier: B
    type: text
    objective: portscan
    prompt: Untuk recon yang lebih senyap (menghindari deteksi), teknik/timing apa yang bisa dipakai, dan apa komprominya?
    answers: ['timing lambat (-T0/-T1), batasi probe/port, scan bertahap; kompromi: jauh lebih lambat dan tetap tak menjamin tak terdeteksi']
    patterns: ['(-T0|-T1|lambat|slow)[^\n]*(senyap|hindari deteksi|probe|port)', '(batasi|kurangi)[^\n]*(probe|port|paket|rate)', '(jauh lebih lambat|lebih lama|trade-?off|komprominya)']
    tests:
      fail: ['scan cepat paling senyap', 'tidak ada kompromi', 'senyap sempurna mungkin', 'timing tidak berpengaruh']
    explanation: 'Recon senyap: `-T0`/`-T1` (timing lambat, menyebar probe agar tak memicu ambang IDS), batasi port & probe (`--top-ports`, hanya yang perlu), hindari `-A` yang berisik, dan scan bertahap dari banyak sumber. Komprominya: **jauh lebih lambat** (bisa berjam-jam) dan tetap **tak menjamin** luput dari deteksi (IDS modern & log korelasi tetap bisa menangkap). Di lab tertutup tak perlu senyap (`-T4`). Di operasi nyata, kesenyapan adalah trade-off sadar dengan kecepatan. Sisi blue (purple): memahami teknik evasi ini membantu men-tuning deteksi agar menangkap scan lambat sekalipun (modul 09).'
    source: https://nmap.org/book/man-performance.html

  - id: b14
    tier: B
    type: text
    objective: version
    prompt: Setelah enumerasi penuh target lab, apa output konkret yang kamu bawa ke tahap berikutnya?
    answers: ['daftar port terbuka + servis + versi spesifik (mis. 21 vsftpd 2.3.4, 22 openssh, 80 apache, dll) dan temuan NSE — jadi daftar target potensial untuk vuln analysis']
    patterns: ['(daftar|list)[^\n]*(port|servis|versi)', '(port terbuka)[^\n]*(servis|versi)', '(versi spesifik|temuan)[^\n]*(exploit|vuln|analisis)|bahan[^\n]*(exploit|analisis)']
    tests:
      fail: ['tidak ada output berguna', 'cukup ingat di kepala', 'tidak perlu dibawa ke tahap lain', 'hanya satu port saja']
    explanation: 'Produk recon yang kamu bawa ke analisis kerentanan & eksploitasi: **daftar terstruktur port terbuka + servis + versi spesifik** (mis. Metasploitable: 21/vsftpd 2.3.4, 22/OpenSSH, 23/telnet, 80/Apache, 139-445/Samba, 3306/MySQL, 5432/PostgreSQL, 8180/Tomcat, dll) **plus temuan NSE** (anonymous FTP, share terbuka). Daftar ini = peta jalan: tiap baris adalah kandidat yang dicocokkan ke exploit di tahap berikutnya. Recon yang baik menghasilkan dokumen ini (disimpan `-oA`, a08), bukan kesan samar — dari sinilah eksploitasi yang terarah dimulai.'
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: b15
    tier: B
    type: command
    objective: scripts
    prompt: Enumerasi share SMB pada target `192.168.56.101` menggunakan skrip NSE kategori smb-enum.
    answers: ['nmap --script smb-enum-shares -p 445 192.168.56.101']
    patterns: ['^(?=.*--script[ =]smb-enum)nmap[ \t].*192\.168', '^nmap[ \t].*--script[ =]smb-enum']
    tests:
      pass:
        - 'nmap --script smb-enum-shares -p 445 192.168.56.101'
        - 'nmap --script smb-enum-shares,smb-enum-users -p 139,445 192.168.56.101'
      fail:
        - 'nmap -sC 192.168.56.101'
        - 'nmap --script ftp-anon 192.168.56.101'
        - 'smbclient -L 192.168.56.101'
    explanation: '`--script smb-enum-shares` (dan `smb-enum-users`, `smb-os-discovery`) meng-enumerasi SMB/Samba di port 139/445 — menemukan share jaringan, user, dan info OS. Metasploitable menjalankan Samba yang bocor banyak info & punya share terbuka. SMB adalah target enumerasi kaya (modul 03 akan memperdalam dengan `enum4linux`/`smbclient`). Pola `--script <kategori-servis>` cocokkan dengan servis yang `-sV` temukan: lihat 445 open → jalankan skrip smb. Ini contoh menyambung "temuan port" ke "enumerasi servis spesifik" — inti kerja recon→enum.'
    source: https://nmap.org/book/nse-usage.html

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: portscan
    prompt: |
      Kamu baru menemukan IP Metasploitable (192.168.56.101) di lab. Rancang alur scanning/enumerasi yang metodis
      untuk memetakannya secara menyeluruh (satu poin per baris).
    checklist:
      - id: fullport
        label: Scan semua port TCP (-p-) agar tak ada servis terlewat
        required: true
        patterns: ['-p-|-p 1-65535|semua port', 'full port|65535']
      - id: version
        label: Deteksi servis & versi (-sV) pada port yang terbuka
        required: true
        patterns: ['-sV|version detection|deteksi versi|servis & versi|servis dan versi']
      - id: scripts
        label: Jalankan skrip NSE (-sC / --script) untuk enumerasi otomatis
        patterns: ['-sC|--script|nse|skrip']
      - id: udp
        label: "Jangan lupa port UDP umum (-sU: DNS/SNMP/TFTP)"
        patterns: ['-sU|udp', 'snmp|tftp|dns']
      - id: output
        label: Simpan semua output (-oA) untuk dokumentasi & tahap lanjut
        patterns: ['-oA|-oN|-oX|simpan output|dokumentasi']
      - id: analyze
        label: Dari versi yang ditemukan → cari kerentanan/exploit (jembatan ke vuln analysis)
        patterns: ['(cari|searchsploit|cve)[^\n]*(exploit|kerentanan)', 'vuln analysis|analisis kerentanan']
      - id: no-default-only
        label: Tidak berhenti di scan default (~1000 port) lalu menyimpulkan selesai
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain))[^\n]*(cukup|hanya)[^\n]*(scan default|nmap polos|1000 port)[^\n]*(selesai|cukup|saja)?']
    pass_score: 0.5
    solution: |
      ```text
      1. nmap -Pn -p- -T4 192.168.56.101           → temukan SEMUA port TCP terbuka (jangan cuma default ~1000).
      2. nmap -sV -sC -p <port-terbuka> 192.168.56.101 → versi servis + skrip NSE default pada port yg ditemukan.
      3. nmap --script <servis>-* -p <port>         → enumerasi spesifik (ftp-anon, smb-enum-shares, http-enum).
      4. nmap -sU --top-ports 20 192.168.56.101     → jangan lupa UDP (SNMP/DNS/TFTP sering bocor info).
      5. nmap ... -oA scan                          → simpan semua output (normal+XML+greppable) utk dokumentasi & msf.
      6. Dari versi (mis. vsftpd 2.3.4, Samba, dll) → searchsploit/CVE → daftar kandidat exploit (vuln analysis).
      ```
      Alur metodis: menyeluruh (full port + UDP) → mendalam (versi + NSE per servis) → terdokumentasi → mengarah ke
      exploit. Berhenti di scan default lalu menyimpulkan "cuma 3 port" adalah kesalahan recon yang membuatmu buta.
    tests:
      pass:
        - |
          scan semua port tcp dengan nmap -p- agar tidak ada servis terlewat
          deteksi servis dan versi dengan -sV pada port yang terbuka
          jalankan skrip nse -sC atau --script untuk enumerasi otomatis
          jangan lupa port udp umum dengan -sU untuk dns snmp tftp
          simpan semua output dengan -oA untuk dokumentasi dan tahap lanjut
          dari versi yang ditemukan cari kerentanan atau exploit dengan searchsploit
          jangan berhenti di scan default 1000 port lalu simpulkan selesai
        - |
          nmap -Pn -p- dulu, lalu -sV -sC pada port terbuka, --script per servis
          scan udp -sU untuk snmp, simpan -oA, lalu searchsploit versi untuk cari exploit
      fail:
        - |
          cukup jalankan nmap polos lalu simpulkan selesai
          tidak perlu versi atau udp
        - |
          scan default saja sudah cukup
          tidak usah simpan output atau cari exploit
        - |
          langsung exploit tanpa tahu versi
          scan satu port 80 saja
    source: https://nmap.org/book/man.html

  - id: c02
    tier: C
    type: scenario
    objective: version
    prompt: |
      nmap -sV pada Metasploitable mengembalikan antara lain: 21/vsftpd 2.3.4, 22/OpenSSH, 139-445/Samba, 80/Apache,
      3306/MySQL. Jelaskan cara kamu memprioritaskan & menindaklanjuti temuan ini menuju eksploitasi (satu poin per baris).
    checklist:
      - id: matchcve
        label: Cocokkan tiap versi ke kerentanan/exploit yang diketahui (searchsploit/CVE)
        required: true
        patterns: ['(cocokkan|cari|lookup)[^\n]*(versi|servis)[^\n]*(cve|exploit|kerentanan)', 'searchsploit']
      - id: prioritize
        label: Prioritaskan yang punya exploit matang/mudah (mis. vsftpd 2.3.4 backdoor terkenal)
        patterns: ['prioritas|dahulukan|mulai dari', '(vsftpd 2\.3\.4|backdoor|exploit (matang|mudah|terkenal|siap))']
      - id: enumdeeper
        label: Enumerasi lebih dalam per servis (smb shares, FTP anon, web dirs, MySQL creds)
        patterns: ['(enumeras|enum)[^\n]*(smb|ftp|web|http|mysql|samba)', 'ftp-anon|smb-enum|dir(b|search)|http-enum|anonymous']
      - id: document
        label: Catat tiap temuan + rencana (dokumentasi untuk laporan)
        patterns: ['(catat|dokumentasi|notes|laporan)[^\n]*(temuan|langkah|rencana)?', 'simpan temuan']
      - id: purple
        label: Catat juga sisi defensif (patch versi usang, tutup servis tak perlu)
        patterns: ['(patch|tutup|hardening|perbaiki)[^\n]*(versi|servis|usang)', 'sisi (blue|defensif|pertahanan)|remediasi']
      - id: no-random
        label: Tidak menembak exploit acak tanpa mencocokkan ke versi/temuan
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain))[^\n]*(tembak|coba|jalankan)[^\n]*(exploit)[^\n]*(acak|asal|random|sembarang)']
    pass_score: 0.5
    solution: |
      ```text
      1. Cocokkan tiap versi ke exploit/CVE: searchsploit vsftpd 2.3.4, samba, dll (vuln analysis, modul berikutnya).
      2. Prioritaskan "buah rendah": vsftpd 2.3.4 punya backdoor terkenal (modul MSF siap pakai) → kandidat pertama.
         Samba versi tua (usermap_script), UnrealIRCd backdoor (6667) juga klasik di Metasploitable.
      3. Enumerasi lebih dalam per servis: smb-enum-shares/enum4linux (Samba), ftp-anon (FTP), dirb/nikto (Apache 80),
         coba kredensial lemah MySQL (3306).
      4. Dokumentasikan tiap temuan + rencana serangan (untuk laporan, modul 10).
      5. (Purple) catat remediasinya: patch/upgrade servis usang, matikan servis tak perlu, batasi akses — inilah
         yang kamu rekomendasikan sebagai blue (board DevSecOps modul 05/03).
      ```
      Metodis: versi → cocokkan exploit → prioritaskan yang matang → enumerasi mendalam → dokumentasi + remediasi.
      Menembak exploit acak tanpa mencocokkan versi buang waktu & berisik — biarkan temuan recon yang mengarahkan.
    tests:
      pass:
        - |
          cocokkan tiap versi ke kerentanan atau exploit yang diketahui pakai searchsploit dan cve
          prioritaskan yang punya exploit matang seperti vsftpd 2.3.4 backdoor terkenal
          enumerasi lebih dalam per servis smb shares ftp anon web dirs dan coba kredensial mysql
          catat tiap temuan dan rencana untuk dokumentasi laporan
          catat juga sisi defensif patch versi usang dan tutup servis tak perlu
          jangan menembak exploit acak tanpa mencocokkan ke versi atau temuan
        - |
          searchsploit tiap versi, dahulukan vsftpd 2.3.4 backdoor yang mudah
          enum4linux dan ftp-anon dan dirb, dokumentasikan, catat remediasi patch, jangan exploit asal
      fail:
        - |
          tembak semua exploit acak sampai ada yang jalan
          tidak perlu cocokkan versi
        - |
          jalankan exploit sembarang tanpa enumerasi
          tidak usah dokumentasi atau remediasi
        - |
          abaikan versi, coba random saja
          dokumentasi tidak penting
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: c03
    tier: C
    type: scenario
    objective: output
    prompt: |
      Dengan pola pikir purple: kamu baru saja mem-fingerprint target lewat scanning agresif. Rancang bagaimana sisi
      BLUE team akan mendeteksi & mempersulit recon seperti ini (satu poin per baris).
    checklist:
      - id: detect
        label: Deteksi pola scan (banyak probe/port dari satu sumber) di IDS/log
        required: true
        patterns: ['(deteksi|ids|log)[^\n]*(scan|probe|pola|sweep)', '(banyak|lonjakan)[^\n]*(probe|koneksi|port)[^\n]*(satu sumber|ip)']
      - id: reduce
        label: Kurangi attack surface — tutup port/servis tak perlu
        required: true
        patterns: ['(tutup|kurangi|matikan)[^\n]*(port|servis)[^\n]*(tak perlu|tidak perlu)?', 'attack surface|permukaan serangan']
      - id: hidebanner
        label: Sembunyikan versi/banner (server_tokens off) agar -sV kurang informatif
        patterns: ['(sembunyi|server_tokens|banner)[^\n]*(versi|banner|off)', 'versi (disembunyikan|tidak bocor)']
      - id: firewall
        label: Firewall & segmentasi (port jadi filtered, batasi siapa bisa scan)
        patterns: ['firewall|segmentasi|iptables', '(port)[^\n]*filtered|batasi[^\n]*(akses|scan|jangkau)']
      - id: block
        label: Blokir sumber scan otomatis (fail2ban/rate-limit)
        patterns: ['fail2ban|rate[- ]?limit|blokir[^\n]*(ip|sumber|scan)']
      - id: patch
        label: Patch/upgrade servis usang agar versi yang terdeteksi tak punya exploit
        patterns: ['patch|upgrade|update[^\n]*(servis|versi|usang)', '(tutup|hilang)[^\n]*(kerentanan|exploit)[^\n]*(versi|servis)']
      - id: no-ignore
        label: Tidak mengabaikan scanning sebagai "cuma noise tak berbahaya"
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain))[^\n]*(abaikan|biarkan)[^\n]*(scan|scanning)[^\n]*(noise|tak berbahaya|biasa|wajar)']
    pass_score: 0.5
    solution: |
      ```text
      1. DETEKSI: IDS/log menangkap pola scan (banyak SYN/probe ke banyak port dari satu IP) → alert dini (DevSecOps 09).
      2. KURANGI eksposur: tutup port & servis yang tak perlu → lebih sedikit yang bisa ditemukan -p-/-sV.
      3. Sembunyikan versi/banner (server_tokens off, dll, DevSecOps 02) → -sV penyerang kurang informatif, tak mengarah ke CVE.
      4. Firewall & segmentasi (DevSecOps 03/08 jaringan) → port jadi filtered, batasi siapa yang bisa menjangkau/scan.
      5. Blokir sumber scan otomatis (fail2ban recidive / rate-limit) → scanner diputus sebelum selesai memetakan.
      6. Patch/upgrade servis usang (DevSecOps 05) → meski versi terdeteksi, tak ada exploit yang cocok.
      ```
      Recon memanfaatkan apa yang terlihat; blue mengecilkan, menyamarkan, membatasi, dan mendeteksinya. Mengabaikan
      scanning sebagai "noise biasa" membuang peringatan dini — scan sering pembuka serangan nyata.
    tests:
      pass:
        - |
          deteksi pola scan banyak probe ke banyak port dari satu sumber di ids dan log
          kurangi attack surface dengan menutup port dan servis yang tak perlu
          sembunyikan versi dan banner dengan server_tokens off agar -sV kurang informatif
          pasang firewall dan segmentasi sehingga port jadi filtered dan batasi siapa bisa scan
          blokir sumber scan otomatis dengan fail2ban atau rate limit
          patch dan upgrade servis usang agar versi yang terdeteksi tidak punya exploit
          jangan abaikan scanning sebagai cuma noise tak berbahaya
        - |
          ids mendeteksi pola scan lalu alert, kurangi port terbuka, sembunyikan banner versi
          firewall bikin port filtered, fail2ban blokir scanner, patch servis usang tutup exploit
      fail:
        - |
          abaikan saja scanning itu cuma noise biasa
          tidak perlu deteksi atau firewall
        - |
          biarkan semua port terbuka dan banner versi tampil
          scanning tidak berbahaya
        - |
          tidak usah patch atau segmentasi
          scan wajar tidak perlu ditanggapi
    source: https://attack.mitre.org/techniques/T1046/
---

# Reconnaissance & Scanning

"**Enumeration is key**" — eksploitasi hanya sebaik recon yang mendahuluinya. Setelah lab siap (modul 01), modul ini
memetakan target dengan **nmap**: port apa terbuka, servis apa, versi berapa. Output-nya jadi bahan baku semua
langkah berikutnya — kamu tak bisa mengeksploitasi apa yang tak kamu temukan.

> [!NOTE]
> Diverifikasi penulis di net Docker terisolasi (`10.10.20.0/24`): target Debian 12 (SSH+Apache+vsftpd), attacker
> ber-nmap. Perintah & format output nmap **identik** dengan yang akan kamu lihat di VirtualBox — hanya nomor versi
> servisnya berbeda (lab penulis modern; Metasploitable sengaja tua). Jalankan perintah yang sama ke IP
> Metasploitable-mu.

## 1. Perintah inti (hasil lab)

```text
nmap -sn 192.168.56.0/24        host discovery (ping sweep) — temukan IP target (modul 01)
nmap -Pn 192.168.56.101         skip host discovery (anggap hidup; lewati blokir ping)
nmap -p- 192.168.56.101         SEMUA 65535 port (default cuma ~1000 → bisa melewatkan)
nmap -sV 192.168.56.101         deteksi servis & VERSI  → vsftpd 3.0.3, OpenSSH 9.2p1, Apache 2.4.71 (lab)
nmap -sC 192.168.56.101         skrip NSE default → ssh-hostkey, http-title, http-server-header (lab)
nmap -A  192.168.56.101         agresif: -O (OS) + -sV + -sC + traceroute (lengkap tapi BERISIK)
nmap -oA scan 192.168.56.101    simpan output: .nmap + .xml + .gnmap (dokumentasi, impor ke msf)
```

## 2. Alur recon yang metodis

```text
1. -sn  (temukan host)         → dapatkan IP target
2. -Pn -p- -T4 (semua port)    → daftar port terbuka lengkap   (lab: 21,22,80)
3. -sV -sC pada port terbuka   → servis + versi + enumerasi NSE
4. --script <servis>-* / -sU   → enumerasi spesifik (ftp-anon, smb-enum) + UDP (SNMP/DNS)
5. -oA                         → simpan semua
6. versi → searchsploit/CVE    → daftar kandidat exploit (modul berikutnya)
```

## 3. Status port & versi = kunci

- **open** (servis aktif) / **closed** (host balas, tak ada servis) / **filtered** (firewall men-drop — petunjuk ada firewall).
- **Versi spesifik** mengarah ke kerentanan: lab penulis `vsftpd 3.0.3` (modern, ditambal) vs Metasploitable `vsftpd 2.3.4` (backdoor terkenal). Angka versi menentukan ada-tidaknya jalan masuk.

## 4. Kesenyapan vs kecepatan

`-T4`/`-T5` cepat (lab), `-T0`/`-T1` senyap (hindari IDS). `-A` & `-p-` berisik. Trade-off sadar: scan cepat =
lebih mudah terdeteksi. Senyap sempurna tak ada.

## 5. Purple — scanning itu berisik & terdeteksi

| Recon (red) | Pertahanan/deteksi (blue — DevSecOps) |
|---|---|
| `-p-`/`-A` (banyak probe) | deteksi pola scan di IDS/log (09), fail2ban/rate-limit |
| `-sV` (banner/versi) | sembunyikan versi/banner `server_tokens off` (02) |
| menemukan port terbuka | kurangi attack surface, tutup servis tak perlu |
| menjangkau target | firewall & segmentasi → port `filtered` (03) |
| cocokkan versi→CVE | patch/upgrade servis usang → tak ada exploit cocok (05) |

> [!WARNING]
> Semua di IP target milik sendiri (Metasploitable) di lab terisolasi. Memindai jaringan/host orang tanpa izin =
> Active Scanning tak sah (ATT&CK T1595) — bisa ilegal. Scan hanya yang kamu miliki atau diizinkan.

> [!NOTE]
> Lanjut ke modul 03 (Enumeration mendalam) — menggali tiap servis yang ditemukan (SMB, FTP, HTTP, SMTP) untuk user,
> share, direktori, dan konfigurasi lemah: bahan langsung untuk eksploitasi.
