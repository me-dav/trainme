---
format: 1
id: rt-enumeration
course:
  slug: redteam
  title: Red Team (Lab Pentest)
  description: Sisi ofensif dari DevSecOps — metodologi red team dipelajari di lab terisolasi milik sendiri (VirtualBox host-only, Kali Linux + Metasploitable 2) untuk memahami cara penyerang bekerja dan membangun pertahanannya (purple team). Recon, enumerasi, analisis kerentanan, eksploitasi, post-exploitation, dan pelaporan. SEMUA hanya pada target milik sendiri dengan izin; teknik dipetakan ke kontrol pertahanan di board DevSecOps.
title: Enumeration Mendalam
summary: "Setelah tahu port & versi (modul 02), gali tiap servis untuk detail yang bisa dimanfaatkan. Modul ini meng-enumerasi per servis: web (direktori tersembunyi, robots, header, teknologi), SMB/Samba (share, user, info OS via enum4linux/smbclient), FTP (anonymous), SMTP (VRFY user), SNMP, NFS. Hasilnya daftar user, share, direktori, dan konfigurasi lemah — bahan langsung untuk analisis kerentanan & eksploitasi. Enumerasi web diverifikasi di lab; enumerasi SMB/dll dijalankan learner di Metasploitable."
order: 3
version: 1
status: verified
updated: '2026-11-25'
tested_on:
  - 'Enumerasi WEB & direktori diverifikasi penulis di net Docker terisolasi: curl robots.txt membocorkan /admin & /backup; curl -I → Server Apache/2.4.71; nmap --script http-enum menemukan /admin/ /uploads/ (listing) /robots.txt; probe manual /admin/→200 vs /backup/→404 (dasar dir brute-force)'
  - 'Enumerasi SMB/Samba, FTP-anon, SMTP VRFY, SNMP, NFS (enum4linux, smbclient, dll) DIDOKUMENTASIKAN untuk Metasploitable 2 — dijalankan learner di Kali/VirtualBox, bukan oleh penulis'
  - Semua pada target milik sendiri di lab terisolasi; dipetakan ke pertahanan board DevSecOps
objectives:
  - id: http
    text: Meng-enumerasi aplikasi web (direktori, robots, header, teknologi)
  - id: smb
    text: Meng-enumerasi SMB/Samba (share, user, info OS)
  - id: ftp
    text: Meng-enumerasi FTP (anonymous & listing)
  - id: services
    text: Meng-enumerasi servis lain (SMTP, SNMP, NFS)
  - id: creds
    text: Mengumpulkan user & info untuk langkah akses berikutnya
  - id: purple
    text: Memahami cara blue mengurangi kebocoran informasi
sources:
  - title: OWASP WSTG — Information Gathering
    url: https://owasp.org/www-project-web-security-testing-guide/
  - title: enum4linux / enum4linux-ng
    url: https://github.com/cddmp/enum4linux-ng
  - title: Gobuster — directory/DNS brute-forcing
    url: https://github.com/OJ/gobuster
  - title: Nikto — web server scanner
    url: https://github.com/sullo/nikto
  - title: Metasploitable 2 — servis & enumerasi
    url: https://docs.rapid7.com/metasploit/metasploitable-2/
  - title: MITRE ATT&CK — Network Service Discovery (T1046) & Account Discovery (T1087)
    url: https://attack.mitre.org/techniques/T1087/
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: command
    objective: http
    prompt: Ambil file `robots.txt` dari web server target `http://192.168.56.101/` (sering membocorkan path tersembunyi).
    answers: ['curl http://192.168.56.101/robots.txt']
    patterns: ['^curl[ \t].*192\.168\.56\.101/robots\.txt', '^(curl|wget)[ \t].*robots\.txt']
    tests:
      pass:
        - 'curl http://192.168.56.101/robots.txt'
        - 'curl -s http://192.168.56.101/robots.txt'
      fail:
        - 'curl http://192.168.56.101/'
        - 'nmap 192.168.56.101'
        - 'cat robots.txt'
    explanation: '`robots.txt` memberi tahu crawler path mana yang TIDAK boleh diindeks — ironisnya, ini sering **membocorkan** justru path menarik (`/admin`, `/backup`). Di lab penulis, `curl .../robots.txt` mengembalikan `Disallow: /admin` & `/backup` — langsung memberi target. Langkah enumerasi web paling murah & pertama. Lanjutkan dengan cek `/sitemap.xml`, header (`curl -I`), dan komentar HTML. Sisi blue (board DevSecOps modul 02): jangan taruh path sensitif di robots.txt berpikir itu "menyembunyikan" — itu malah petunjuk; lindungi dengan otorisasi, bukan obscurity.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a02
    tier: A
    type: command
    objective: http
    prompt: Brute-force direktori/file tersembunyi di `http://192.168.56.101` memakai gobuster dengan wordlist `common.txt`.
    answers: ['gobuster dir -u http://192.168.56.101 -w common.txt']
    patterns: ['^(?=.*dir)(?=.*-u[ \t]+\S+)(?=.*-w[ \t]+\S+)gobuster[ \t]']
    tests:
      pass:
        - 'gobuster dir -u http://192.168.56.101 -w common.txt'
        - 'gobuster dir -u http://192.168.56.101 -w /usr/share/wordlists/dirb/common.txt'
      fail:
        - 'gobuster http://192.168.56.101'
        - 'gobuster dir -u http://192.168.56.101'
        - 'dirb http://192.168.56.101'
    explanation: '`gobuster dir -u <url> -w <wordlist>` mencoba ribuan nama direktori/file dari wordlist dan melaporkan yang ada (status 200/301/403) vs tidak (404). Ini menemukan halaman yang tak tertaut di mana pun — panel admin, backup, file config. Dasarnya persis yang diverifikasi penulis: `/admin/`→200 (ada), `/backup/`→404 (tidak). Wordlist umum: `dirb/common.txt`, SecLists. Alternatif: `dirb`, `feroxbuster`, `ffuf`. Berisik (banyak request → terdeteksi, board DevSecOps modul 09). Temuan dir = permukaan serangan web tambahan untuk modul eksploitasi web.'
    source: https://github.com/OJ/gobuster

  - id: a03
    tier: A
    type: command
    objective: http
    prompt: Pindai web server `http://192.168.56.101` untuk masalah umum & file berbahaya dengan Nikto.
    answers: ['nikto -h http://192.168.56.101']
    patterns: ['^nikto[ \t].*-h[ \t]+\S*192\.168\.56\.101', '^nikto[ \t].*-h[ \t]+\S+']
    tests:
      pass:
        - 'nikto -h http://192.168.56.101'
        - 'nikto -h 192.168.56.101'
      fail:
        - 'nikto 192.168.56.101'
        - 'nmap 192.168.56.101'
        - 'gobuster -h http://192.168.56.101'
    explanation: '`nikto -h <url>` memindai web server untuk ribuan masalah diketahui: file/direktori berbahaya (phpinfo, backup), header keamanan hilang, versi software usang, konfigurasi default. Cepat memberi gambaran "apa yang salah" di web server. Di Metasploitable (Apache + PHP + aplikasi web rentan seperti DVWA/Mutillidae) Nikto menghasilkan banyak temuan. Berisik (jelas terlihat di log, board DevSecOps modul 09). Lengkapi dengan `whatweb` (identifikasi teknologi) dan gobuster (direktori). Nikto memberi petunjuk; verifikasi temuan sebelum menyimpulkan.'
    source: https://github.com/sullo/nikto

  - id: a04
    tier: A
    type: command
    objective: smb
    prompt: Enumerasi SMB/Samba target `192.168.56.101` secara menyeluruh (share, user, OS) dengan enum4linux.
    answers: ['enum4linux 192.168.56.101']
    patterns: ['^enum4linux(-ng)?[ \t].*192\.168\.56\.101', '^enum4linux(-ng)?[ \t].*-a[ \t]+\S+', '^enum4linux(-ng)?[ \t]+\S+']
    tests:
      pass:
        - 'enum4linux 192.168.56.101'
        - 'enum4linux -a 192.168.56.101'
        - 'enum4linux-ng 192.168.56.101'
      fail:
        - 'nmap 192.168.56.101'
        - 'smbclient 192.168.56.101'
        - 'enum4linux'
    explanation: '`enum4linux <ip>` (atau `-a` untuk "all", atau versi modern `enum4linux-ng`) meng-enumerasi SMB/Samba secara otomatis: daftar **share**, **user & group**, kebijakan password, info OS, dan session null. Metasploitable menjalankan Samba tua yang bocor banyak info ini. Daftar **user** yang didapat jadi bahan untuk brute force/password spraying (modul berikutnya) — ini contoh Account Discovery (ATT&CK T1087). Sangat berisik. Sisi blue: batasi SMB (jangan ekspos 139/445), nonaktifkan null session, dan jangan jalankan Samba usang (board DevSecOps modul 03/05).'
    source: https://github.com/cddmp/enum4linux-ng

  - id: a05
    tier: A
    type: command
    objective: smb
    prompt: Daftar share SMB yang tersedia di `192.168.56.101` tanpa kredensial (null session) dengan smbclient.
    answers: ['smbclient -L //192.168.56.101/ -N']
    patterns: ['^(?=.*-L)(?=.*-N)smbclient[ \t].*192\.168\.56\.101', '^smbclient[ \t].*-L[ \t].*192\.168\.56\.101']
    tests:
      pass:
        - 'smbclient -L //192.168.56.101/ -N'
        - 'smbclient -L 192.168.56.101 -N'
      fail:
        - 'smbclient //192.168.56.101/share'
        - 'smbclient 192.168.56.101'
        - 'nmap --script smb-enum 192.168.56.101'
    explanation: '`smbclient -L //<ip>/ -N` mendaftar share yang diekspos; `-L` = list, `-N` = no password (coba null/anonymous session). Jika target mengizinkan null session (seperti Samba Metasploitable), kamu melihat share tanpa kredensial — dan mungkin bisa mengaksesnya (`smbclient //ip/share -N`) untuk membaca/menulis file. Share terbuka sering berisi file sensitif atau jadi jalur upload. Sisi blue (board DevSecOps): nonaktifkan null/guest session, batasi share, jangan ekspos SMB ke jaringan tak tepercaya. Ini enumerasi klasik yang mengubah "445 terbuka" jadi "akses file".'
    source: https://www.samba.org/samba/docs/current/man-html/smbclient.1.html

  - id: a06
    tier: A
    type: command
    objective: ftp
    prompt: Coba login FTP anonymous ke `192.168.56.101` (user `anonymous`, password kosong/apa saja).
    answers: ['ftp 192.168.56.101']
    patterns: ['^ftp[ \t]+\S*192\.168\.56\.101', '^ftp[ \t]+anonymous@192\.168\.56\.101']
    tests:
      pass:
        - 'ftp 192.168.56.101'
        - 'ftp anonymous@192.168.56.101'
      fail:
        - 'nmap 192.168.56.101'
        - 'ssh 192.168.56.101'
        - 'ftp'
    explanation: '`ftp <ip>` membuka sesi FTP; coba login `anonymous` dengan password apa saja. Jika diizinkan (Metasploitable vsftpd mengizinkan anonymous), kamu bisa menjelajah & mengunduh file tanpa kredensial — sering bocor data atau memberi info. Bisa juga dicek otomatis: `nmap --script ftp-anon -p 21`. Di lab penulis (vsftpd 3.0.3 modern) anonymous ditolak — itu justru konfigurasi aman. Perbedaan ini = pelajaran blue (board DevSecOps): matikan anonymous FTP kecuali benar-benar perlu. Setelah masuk, perhatikan versi vsftpd (2.3.4 di Metasploitable punya backdoor terkenal — modul eksploitasi).'
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: a07
    tier: A
    type: command
    objective: http
    prompt: Identifikasi teknologi/framework yang dipakai web server `http://192.168.56.101` dengan whatweb.
    answers: ['whatweb http://192.168.56.101']
    patterns: ['^whatweb[ \t]+\S*192\.168\.56\.101', '^whatweb[ \t]+\S+']
    tests:
      pass:
        - 'whatweb http://192.168.56.101'
        - 'whatweb -v http://192.168.56.101'
      fail:
        - 'nmap 192.168.56.101'
        - 'curl 192.168.56.101'
        - 'whatweb'
    explanation: '`whatweb <url>` mengidentifikasi teknologi web: server (Apache/nginx + versi), bahasa (PHP), CMS (WordPress/Joomla + versi), framework, library JS, dan header. Mengetahui "ini WordPress 4.x" atau "PHP 5.x" langsung mengarahkan ke exploit/plugin rentan yang spesifik. Serupa dengan `-sV` tapi fokus web & lebih kaya untuk aplikasi. Di lab penulis mengidentifikasi Apache + default page. Sisi blue: menyembunyikan versi & header `X-Powered-By` (board DevSecOps modul 07) mengurangi info gratis ini. Teknologi yang teridentifikasi = jalan ke kerentanan spesifik aplikasi.'
    source: https://github.com/urbanadventurer/WhatWeb

  - id: a08
    tier: A
    type: text
    objective: creds
    prompt: Mengapa mengumpulkan daftar username (dari SMB/SMTP/web) berharga bagi penyerang?
    answers: ['username valid adalah separuh dari kredensial; jadi target brute force/password spraying dan mengurangi tebakan']
    patterns: ['username[^\n]*(separuh|setengah|target|brute|spraying|login)', '(brute force|password spraying|tebak password)[^\n]*(user|akun)', '(kurangi|persempit)[^\n]*(tebakan|kemungkinan)']
    tests:
      fail: ['username tidak berguna', 'hanya password yang penting', 'daftar user tidak membantu', 'username selalu publik dan tak bernilai']
    explanation: 'Login butuh **dua** hal: username + password. Menemukan **username valid** (via enum4linux SMB, SMTP VRFY, pesan error login, halaman web) sudah separuh jalan — dan menargetkan brute force/password spraying (modul berikutnya) hanya ke akun yang ADA, bukan menebak buta. Ini Account Discovery (ATT&CK T1087). Contoh: enum4linux mengungkap user `msfadmin`, `user`, dll di Metasploitable → kandidat untuk coba password lemah. Sisi blue: jangan bocorkan apakah username valid (pesan login generik), batasi enumerasi user di SMTP/SMB, dan wajibkan password kuat + MFA + lockout (board DevSecOps modul 03).'
    source: https://attack.mitre.org/techniques/T1087/

  - id: a09
    tier: A
    type: command
    objective: services
    prompt: Enumerasi NFS — daftar direktori yang di-export oleh `192.168.56.101`.
    answers: ['showmount -e 192.168.56.101']
    patterns: ['^(?=.*-e)showmount[ \t].*192\.168\.56\.101', '^showmount[ \t]+-e[ \t]+\S+']
    tests:
      pass:
        - 'showmount -e 192.168.56.101'
        - 'showmount -e 192.168.56.101'
      fail:
        - 'showmount 192.168.56.101'
        - 'mount 192.168.56.101'
        - 'nmap 192.168.56.101'
    explanation: '`showmount -e <ip>` menampilkan direktori NFS yang di-export sebuah host. Jika sebuah export terbuka lebar (mis. `/ *` — seluruh root ke siapa saja, seperti kesalahan di Metasploitable), penyerang bisa me-*mount*-nya dan membaca/menulis file host — termasuk `~/.ssh/authorized_keys` untuk menanam kunci (jalur akses!). NFS yang salah konfigurasi adalah temuan kritis. Sisi blue (board DevSecOps): batasi export ke host tertentu (bukan `*`), pakai `root_squash`, jangan ekspor direktori sensitif. Enumerasi ini mengubah "port 2049 terbuka" jadi "akses filesystem".'
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: a10
    tier: A
    type: text
    objective: http
    prompt: Dalam directory brute-forcing, apa arti respons 200 vs 403 vs 404?
    answers: ['200 = ada & bisa diakses; 403 = ada tapi akses dilarang (tetap ada!); 404 = tidak ada']
    patterns: ['200[^\n]*(ada|bisa diakses|ketemu)', '403[^\n]*(dilarang|forbidden|tetap ada|tapi ada|akses ditolak)', '404[^\n]*(tidak ada|tak ada|absen)']
    tests:
      fail: ['semua kode sama artinya', '403 berarti tidak ada', '404 berarti ada', '200 berarti error']
    explanation: 'Status code membedakan apa yang ada: **200** = resource ada & bisa diakses (temuan langsung). **403 Forbidden** = resource ADA tapi aksesnya dilarang — tetap informasi berharga (ada `/admin` meski tak bisa dibuka → target untuk bypass/kredensial). **404** = tidak ada. Penyerang memperhatikan 403 sama seriusnya dengan 200 — ia mengonfirmasi keberadaan. Di lab penulis, `/admin/`→200, `/backup/`→404. Sisi blue (board DevSecOps modul 02): untuk menyembunyikan keberadaan, kembalikan **404** (bukan 403) pada path sensitif — 403 malah mengonfirmasi "ada sesuatu di sini".'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a11
    tier: A
    type: command
    objective: http
    prompt: Lihat header respons HTTP dari `http://192.168.56.101/` (banner server, teknologi, header keamanan).
    answers: ['curl -I http://192.168.56.101/']
    patterns: ['^(?=.*[ \t](-[A-Za-z]*I[A-Za-z]*|--head)([ \t]|$))curl[ \t].*192\.168\.56\.101']
    tests:
      pass:
        - 'curl -I http://192.168.56.101/'
        - 'curl -sI http://192.168.56.101/'
      fail:
        - 'curl http://192.168.56.101/'
        - 'nmap 192.168.56.101'
        - 'wget http://192.168.56.101/'
    explanation: '`curl -I` menampilkan header respons — di lab penulis membocorkan `Server: Apache/2.4.71 (Debian)` (versi!). Header mengungkap: software server & versi (`Server`), teknologi (`X-Powered-By: PHP/...`), dan **tiadanya header keamanan** (CSP/HSTS/X-Frame-Options — artinya aplikasi rentan clickjacking/XSS). Ini enumerasi web dua-arah: sebagai red kamu panen info; sebagai blue (board DevSecOps modul 02/07) kamu menyembunyikan versi (`server_tokens off`) & menambah header keamanan. Perintah kecil yang sama, dua sudut pandang — persis cara pikir purple.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a12
    tier: A
    type: text
    objective: services
    prompt: Protokol SNMP (UDP 161) dengan community string default `public` bisa membocorkan apa?
    answers: ['banyak info sistem: proses berjalan, user, interface jaringan, software terpasang, bahkan kadang kredensial']
    patterns: ['(info|informasi)[^\n]*(sistem|proses|user|jaringan|software|interface)', '(proses|user|interface|software)[^\n]*(berjalan|terpasang|jaringan)', 'snmp[^\n]*(bocor|ungkap)[^\n]*(sistem|info)']
    tests:
      fail: ['tidak membocorkan apa pun', 'hanya waktu sistem', 'snmp aman secara default', 'tidak ada gunanya']
    explanation: 'SNMP (Simple Network Management Protocol) dengan community string default **`public`** (read) adalah tambang emas enumerasi: `snmpwalk -c public -v1 <ip>` bisa membocorkan proses yang berjalan, daftar user, interface & tabel routing, software terpasang, port TCP/UDP aktif, bahkan kadang kredensial di config. Sering terlupa karena di UDP (perlu `-sU`, modul 02). Sisi blue (board DevSecOps): ganti community string default, pakai SNMPv3 (terautentikasi & terenkripsi), atau matikan SNMP jika tak perlu, dan jangan ekspos 161 ke jaringan tak tepercaya. Community string `public` = "silakan baca semua".'
    source: https://attack.mitre.org/techniques/T1046/

  - id: a13
    tier: A
    type: command
    objective: services
    prompt: Enumerasi apakah user `root` valid di server SMTP `192.168.56.101` memakai skrip NSE smtp-enum-users.
    answers: ['nmap --script smtp-enum-users -p 25 192.168.56.101']
    patterns: ['^(?=.*--script[ =]smtp-enum-users)nmap[ \t].*192\.168', '^nmap[ \t].*--script[ =]smtp-enum']
    tests:
      pass:
        - 'nmap --script smtp-enum-users -p 25 192.168.56.101'
        - 'nmap -p 25 --script smtp-enum-users 192.168.56.101'
      fail:
        - 'nmap --script ftp-anon 192.168.56.101'
        - 'smtp-user-enum 192.168.56.101'
        - 'nmap -sV 192.168.56.101'
    explanation: 'Server SMTP tua sering mendukung perintah `VRFY`/`EXPN`/`RCPT` yang mengonfirmasi apakah sebuah user ada ("VRFY root" → 250 ada / 550 tidak). `nmap --script smtp-enum-users` (atau tool `smtp-user-enum`) mengotomasikannya untuk memanen **daftar user valid** sistem (ATT&CK T1087) — bahan brute force. Metasploitable menjalankan Postfix yang bisa di-enumerasi begini. Sisi blue (board DevSecOps): nonaktifkan `VRFY`/`EXPN`, beri respons seragam agar user tak bisa dibedakan ada/tidak. Enumerasi user = separuh kredensial (a08).'
    source: https://attack.mitre.org/techniques/T1087/

  - id: a14
    tier: A
    type: text
    objective: creds
    prompt: Apa produk konkret dari tahap enumerasi yang kamu bawa ke tahap akses/eksploitasi?
    answers: ['daftar user, share/direktori terbuka, path web tersembunyi, versi aplikasi, dan konfigurasi lemah — semua jadi target spesifik']
    patterns: ['(daftar|list)[^\n]*(user|share|direktori|path)', '(user|share|direktori|path|versi)[^\n]*(terbuka|tersembunyi|lemah|valid)', '(target|bahan)[^\n]*(exploit|akses|serangan)']
    tests:
      fail: ['tidak ada produk berguna', 'cukup port terbuka saja', 'tidak membawa apa-apa', 'hanya nama host']
    explanation: 'Enumerasi menghasilkan **daftar konkret** yang jadi target langsung: username valid (→ brute force), share/NFS terbuka (→ baca/tulis file), direktori web tersembunyi (→ panel admin, aplikasi rentan), versi aplikasi spesifik (→ exploit yang cocok), dan konfigurasi lemah (anonymous FTP, null SMB, SNMP public). Tiap item memetakan ke langkah serangan berikutnya. Enumerasi yang dangkal → eksploitasi menebak-nebak; enumerasi teliti → serangan terarah & berhasil. Inilah mengapa "enumeration is key" — dokumentasikan semua temuan ini (untuk laporan, modul 10) sebelum melangkah ke eksploitasi.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a15
    tier: A
    type: text
    objective: purple
    prompt: Secara umum, bagaimana blue team mengurangi hasil enumerasi yang bisa dipanen penyerang?
    answers: ['kurangi info yang bocor: sembunyikan versi/banner, matikan anonymous/null/default, batasi servis & akses, dan deteksi aktivitas enumerasi']
    patterns: ['(sembunyi|kurangi|minim)[^\n]*(versi|banner|info)', '(matikan|nonaktif|tutup)[^\n]*(anonymous|null|default|vrfy|servis)', '(batasi|segmentasi|firewall)[^\n]*(akses|servis)|deteksi[^\n]*enumeras']
    tests:
      fail: ['biarkan semua info terbuka', 'blue team tak bisa apa-apa', 'enumerasi tidak bisa dikurangi', 'tampilkan versi sejelas mungkin']
    explanation: 'Enumerasi memanen **informasi yang bocor** — jadi blue menguranginya: **sembunyikan versi/banner** (`server_tokens off`, hapus `X-Powered-By`), **matikan fitur bocor** (anonymous FTP, null SMB session, SMTP VRFY, SNMP `public`, autoindex), **batasi servis & akses** (tutup port tak perlu, segmentasi, firewall), dan **deteksi** aktivitas enumerasi (banyak probe/query = sinyal, board DevSecOps modul 09). Tiap kebocoran yang ditutup membuat serangan penyerang kurang terarah. Prinsipnya sama dengan modul 02: kecilkan & samarkan permukaan. Enumerasi yang hampa membuat penyerang kembali menebak buta.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: command
    objective: smb
    prompt: Akses (connect) ke sebuah share SMB bernama `tmp` di `192.168.56.101` tanpa password (null session).
    answers: ['smbclient //192.168.56.101/tmp -N']
    patterns: ['^(?=.*-N)smbclient[ \t]+//192\.168\.56\.101/\S+', '^smbclient[ \t]+//192\.168\.56\.101/\S+[ \t]+-N']
    tests:
      pass:
        - 'smbclient //192.168.56.101/tmp -N'
        - 'smbclient //192.168.56.101/tmp -N'
      fail:
        - 'smbclient -L //192.168.56.101/ -N'
        - 'smbclient //192.168.56.101/tmp'
        - 'ftp 192.168.56.101'
    explanation: 'Setelah mendaftar share (`-L`, a05), hubungkan ke share spesifik: `smbclient //<ip>/<share> -N`. Jika null session diizinkan, kamu dapat prompt `smb: \>` untuk `ls`, `get <file>` (unduh), `put <file>` (unggah — jalur upload webshell bila share dipetakan ke docroot!). Share yang writable + dipetakan ke web/startup adalah jalur eksekusi kode. Metasploitable punya Samba dengan share terbuka & bahkan kerentanan RCE (usermap_script — modul eksploitasi). Sisi blue: nonaktifkan null/guest, buat share read-only bila mungkin, jangan petakan share ke jalur berbahaya.'
    source: https://www.samba.org/samba/docs/current/man-html/smbclient.1.html

  - id: b02
    tier: B
    type: text
    objective: http
    prompt: Metasploitable menjalankan aplikasi web seperti DVWA, Mutillidae, dan phpMyAdmin. Mengapa menemukannya saat enumerasi penting?
    answers: ['masing-masing adalah aplikasi dengan kerentanan sendiri (sqli, upload, rce, default creds) — jadi permukaan serangan web yang kaya']
    patterns: ['(dvwa|mutillidae|phpmyadmin)[^\n]*(rentan|kerentanan|vuln|exploit)', 'aplikasi[^\n]*(kerentanan|rentan)[^\n]*(sendiri|masing)', '(sqli|upload|rce|default cred)[^\n]*(aplikasi|web)']
    tests:
      fail: ['aplikasi web tidak penting', 'hanya satu halaman statis', 'dvwa aman', 'tidak perlu enumerasi web']
    explanation: 'Enumerasi web (gobuster/nikto/whatweb) menemukan aplikasi yang tak tertaut — di Metasploitable: **DVWA** (Damn Vulnerable Web App — lengkap dengan SQLi/XSS/upload/command injection bertingkat), **Mutillidae** (OWASP Top 10 playground), **phpMyAdmin** (sering dengan kredensial default/kosong → akses DB), **TWiki**, dll. Masing-masing adalah target eksploitasi web tersendiri (modul berikutnya). Menemukannya = menemukan pintu-pintu. Ini kenapa enumerasi web tak berhenti di homepage — gali direktori untuk aplikasi tersembunyi. Pelajaran blue: jangan tinggalkan aplikasi usang/default di server (board DevSecOps modul 07).'
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: b03
    tier: B
    type: command
    objective: http
    prompt: Fuzz direktori dengan ffuf pada `http://192.168.56.101/FUZZ` memakai wordlist `common.txt`.
    answers: ['ffuf -u http://192.168.56.101/FUZZ -w common.txt']
    patterns: ['^(?=.*-u[ \t]+\S*FUZZ)(?=.*-w[ \t]+\S+)ffuf[ \t]']
    tests:
      pass:
        - 'ffuf -u http://192.168.56.101/FUZZ -w common.txt'
        - 'ffuf -w common.txt -u http://192.168.56.101/FUZZ'
      fail:
        - 'ffuf -u http://192.168.56.101 -w common.txt'
        - 'ffuf http://192.168.56.101'
        - 'gobuster http://192.168.56.101'
    explanation: '`ffuf` (Fuzz Faster U Fool) mengganti keyword `FUZZ` di URL dengan tiap entri wordlist — sangat cepat & fleksibel. `ffuf -u http://host/FUZZ -w list` = brute direktori (seperti gobuster). Tapi `FUZZ` bisa di mana saja: parameter (`?id=FUZZ`), subdomain (`FUZZ.host`), header, data POST — menjadikannya alat fuzzing serbaguna (nanti untuk menemukan parameter tersembunyi, modul web). Filter hasil dengan `-fc 404` (sembunyikan 404) atau `-mc 200,403`. Bersama gobuster/dirb/feroxbuster, ini inti penemuan konten web. Berisik — terdeteksi sebagai banyak request (board DevSecOps modul 09).'
    source: https://github.com/ffuf/ffuf

  - id: b04
    tier: B
    type: text
    objective: services
    prompt: Metasploitable punya NFS yang mengekspor `/` ke semua host. Bagaimana ini bisa menjadi jalur akses penuh?
    answers: ['mount seluruh filesystem lalu baca/tulis file sensitif — mis. tanam kunci ke ~/.ssh/authorized_keys untuk login ssh sebagai root']
    patterns: ['mount[^\n]*(filesystem|/|root|export)', '(tanam|tulis|tambah)[^\n]*(kunci|key|authorized_keys)', '(baca|tulis|akses)[^\n]*(file sensitif|shadow|ssh)']
    tests:
      fail: ['nfs terbuka tidak berbahaya', 'tidak bisa diapa-apakan', 'hanya baca satu file', 'nfs tidak memberi akses']
    explanation: 'Export NFS `/ *` (seluruh root ke siapa saja, tanpa `root_squash`) = bencana: penyerang me-*mount* filesystem target ke mesinnya, lalu **membaca** file sensitif (`/etc/shadow`, config berisi kredensial) DAN **menulis** — mis. menambahkan public key-nya ke `/root/.ssh/authorized_keys`, lalu `ssh root@target` tanpa password. Satu misconfig NFS → akses root penuh. Ini menunjukkan enumerasi (`showmount -e`, a09) langsung mengarah ke akses. Sisi blue (board DevSecOps): export hanya ke host spesifik, pakai `root_squash`, jangan pernah ekspor `/`, batasi ke read-only bila cukup. Misconfig sering lebih fatal dari CVE.'
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: b05
    tier: B
    type: command
    objective: services
    prompt: Walk SNMP pada `192.168.56.101` dengan community string `public` (SNMP v1) untuk memanen info sistem.
    answers: ['snmpwalk -c public -v1 192.168.56.101']
    patterns: ['^(?=.*-c[ \t]+public)(?=.*-v[ ]?1)snmpwalk[ \t].*192\.168', '^snmpwalk[ \t].*-c[ \t]+public']
    tests:
      pass:
        - 'snmpwalk -c public -v1 192.168.56.101'
        - 'snmpwalk -v1 -c public 192.168.56.101'
      fail:
        - 'snmpwalk 192.168.56.101'
        - 'snmpget 192.168.56.101'
        - 'nmap -sU -p 161 192.168.56.101'
    explanation: '`snmpwalk -c <community> -v<versi> <ip>` menelusuri seluruh MIB SNMP target. Dengan community default `public` (read-only tapi sering cukup), ia memanen: proses berjalan, daftar user, interface jaringan, software terpasang, koneksi TCP/UDP aktif. Semua tanpa kredensial "nyata". Temukan 161/UDP terbuka dulu (`nmap -sU`, modul 02), lalu walk. Hasilnya memperkaya peta target & bisa membocorkan kredensial di string config. Sisi blue (board DevSecOps): ganti community default, SNMPv3 (auth+enkripsi), atau matikan SNMP. `public` adalah "pintu baca tanpa kunci".'
    source: https://attack.mitre.org/techniques/T1046/

  - id: b06
    tier: B
    type: text
    objective: creds
    prompt: Apa beda "brute force", "password spraying", dan "credential stuffing" sebagai pemanfaatan info enumerasi?
    answers: ['brute force: banyak password untuk satu user; spraying: satu password umum ke banyak user (hindari lockout); stuffing: kredensial bocoran dari breach lain']
    patterns: ['brute force[^\n]*(banyak password|satu user)', '(spraying|spray)[^\n]*(satu password|banyak user|lockout)', '(stuffing)[^\n]*(bocor|breach|leaked|kredensial lama)']
    tests:
      fail: ['ketiganya sama', 'spraying mencoba banyak password satu user', 'stuffing menebak acak', 'tidak ada bedanya']
    explanation: 'Tiga teknik memakai daftar user hasil enumerasi: **Brute force** = coba banyak password untuk satu user (cepat memicu lockout/deteksi). **Password spraying** = coba satu-dua password umum (`Password123`) ke BANYAK user — menghindari lockout per-akun (karena tiap akun cuma dicoba sekali) dan efektif karena selalu ada yang pakai password lemah. **Credential stuffing** = pakai pasangan user:password dari **breach lain** (orang pakai ulang password). Semua memerlukan daftar user (a08). Sisi blue (board DevSecOps modul 03/07): MFA, lockout cerdas, deteksi, dan larang password umum/bocoran mematikan ketiganya.'
    source: https://attack.mitre.org/techniques/T1087/

  - id: b07
    tier: B
    type: command
    objective: http
    prompt: Cek metode HTTP yang diizinkan (mis. PUT/DELETE berbahaya) di `192.168.56.101` dengan skrip NSE http-methods.
    answers: ['nmap --script http-methods -p 80 192.168.56.101']
    patterns: ['^(?=.*--script[ =]http-methods)nmap[ \t].*192\.168', '^nmap[ \t].*--script[ =]http-methods']
    tests:
      pass:
        - 'nmap --script http-methods -p 80 192.168.56.101'
        - 'nmap -p 80 --script http-methods 192.168.56.101'
      fail:
        - 'nmap --script http-enum 192.168.56.101'
        - 'curl -X GET 192.168.56.101'
        - 'nmap -sV 192.168.56.101'
    explanation: '`--script http-methods` mendaftar metode HTTP yang didukung server. Metode berbahaya seperti **PUT** (unggah file → webshell!), **DELETE** (hapus), atau **TRACE** (XST) yang aktif adalah temuan penting. Mis. PUT aktif di direktori writable = kamu bisa `curl -X PUT ...` mengunggah shell langsung. Cek juga manual: `curl -X OPTIONS -I <url>`. Sisi blue (board DevSecOps modul 07): batasi metode ke yang diperlukan (GET/POST), nonaktifkan PUT/DELETE/TRACE kecuali API memang butuh. Metode yang terlalu permisif = jalur upload/manipulasi.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: b08
    tier: B
    type: text
    objective: smb
    prompt: "Apa itu \"null session\" SMB dan kenapa ia berbahaya untuk enumerasi?"
    answers: ['koneksi smb tanpa kredensial (user & password kosong) yang pada sistem usang mengizinkan enumerasi user, share, dan kebijakan']
    patterns: ['(koneksi|sesi)[^\n]*smb[^\n]*(tanpa|kosong)[^\n]*(kredensial|password|user)', '(user|password) kosong', '(enumerasi|baca)[^\n]*(user|share|kebijakan)[^\n]*(tanpa (login|kredensial))?']
    tests:
      fail: ['null session butuh password admin', 'tidak berbahaya', 'hanya untuk koneksi terenkripsi', 'null session adalah fitur keamanan']
    explanation: 'Null session = koneksi SMB dengan username & password **kosong** (anonymous). Pada Windows/Samba lama (seperti Metasploitable) ini mengizinkan enumerasi tanpa kredensial: daftar user, group, share, kebijakan password, info domain — semua yang dipakai enum4linux (a04) & `smbclient -N` (a05). Berbahaya karena memberi penyerang peta lengkap identitas & sumber daya **sebelum** punya satu kredensial pun. Sisi blue (board DevSecOps): nonaktifkan null/anonymous session (`restrict anonymous`, `RestrictNullSessAccess`), jangan ekspos SMB ke jaringan tak tepercaya, dan modernkan dari SMBv1/Samba usang.'
    source: https://github.com/cddmp/enum4linux-ng

  - id: b09
    tier: B
    type: command
    objective: creds
    prompt: Enumerasi metode autentikasi SSH yang didukung `192.168.56.101` (apakah password auth aktif) via NSE.
    answers: ['nmap --script ssh-auth-methods -p 22 192.168.56.101']
    patterns: ['^(?=.*--script[ =]ssh-auth-methods)nmap[ \t].*192\.168', '^nmap[ \t].*--script[ =]ssh-auth-methods']
    tests:
      pass:
        - 'nmap --script ssh-auth-methods -p 22 192.168.56.101'
        - 'nmap -p 22 --script ssh-auth-methods 192.168.56.101'
      fail:
        - 'ssh 192.168.56.101'
        - 'nmap --script ftp-anon 192.168.56.101'
        - 'nmap -sV -p 22 192.168.56.101'
    explanation: '`--script ssh-auth-methods` mengungkap metode auth yang diterima SSH: `password`, `publickey`, `keyboard-interactive`. Jika **password** aktif, SSH jadi kandidat brute force (modul berikutnya) — jika hanya `publickey`, brute password percuma (harus cari kunci). Ini mengarahkan strategi serangan. Sisi blue (board DevSecOps modul 03, yang sudah kamu buktikan!): matikan `PasswordAuthentication`, hanya izinkan kunci — persis yang membuat brute force attacker gagal ("Permission denied (publickey)"). Enumerasi metode auth = red mengetahui apakah pintu bisa di-brute; blue menutup metode yang bisa di-brute.'
    source: https://attack.mitre.org/techniques/T1087/

  - id: b10
    tier: B
    type: text
    objective: purple
    prompt: Enumerasi menghasilkan banyak request/koneksi. Bagaimana blue mendeteksinya sebagai sinyal serangan dini?
    answers: ['lonjakan request 404/koneksi ke banyak path/share/port dari satu sumber di log/ids = pola enumerasi, picu alert sebelum eksploitasi']
    patterns: ['(lonjakan|banyak)[^\n]*(404|request|koneksi|query)[^\n]*(path|share|user|port)?', '(log|ids|siem)[^\n]*(enumeras|scan|pola|probe)', '(alert|peringatan dini)[^\n]*(sebelum|eksploit)']
    tests:
      fail: ['enumerasi tidak terdeteksi', 'blue tak bisa melihat enumerasi', 'tidak ada sinyal', 'enumerasi senyap sempurna']
    explanation: 'Enumerasi itu berisik: directory brute (gobuster/ffuf) = ratusan/ribuan **404 dari satu IP** (board DevSecOps modul 09 — persis sinyal scanning yang kamu buktikan di lab biru: 404 per IP mengidentifikasi penyerang); enum4linux/smbwalk/snmpwalk = banyak query SMB/SNMP; smtp-enum = banyak VRFY. Blue mendeteksi pola ini di log/IDS/SIEM dan memicu **alert dini** — enumerasi mendahului eksploitasi, jadi menangkapnya memberi waktu merespons (board DevSecOps modul 10). Red yang sadar ini memperlambat/menyebar enumerasi; blue men-tuning deteksi agar tetap menangkap. Itu putaran purple.'
    source: https://attack.mitre.org/techniques/T1046/

  - id: b11
    tier: B
    type: command
    objective: http
    prompt: Enumerasi file & direktori umum web via NSE http-enum pada `192.168.56.101`.
    answers: ['nmap --script http-enum -p 80 192.168.56.101']
    patterns: ['^(?=.*--script[ =]http-enum)nmap[ \t].*192\.168', '^nmap[ \t].*--script[ =]http-enum']
    tests:
      pass:
        - 'nmap --script http-enum -p 80 192.168.56.101'
        - 'nmap -p 80 --script http-enum 192.168.56.101'
      fail:
        - 'nmap -sC 192.168.56.101'
        - 'gobuster dir -u http://192.168.56.101 -w list'
        - 'nmap --script smb-enum 192.168.56.101'
    explanation: '`--script http-enum` adalah directory/file enumeration bawaan nmap — mencoba path umum (admin, backup, aplikasi diketahui) dan melaporkan yang ada. Diverifikasi penulis: menemukan `/admin/`, `/admin/index.html`, `/robots.txt`, `/uploads/` (dengan directory listing!) di target lab. Berguna sebagai pass cepat sebelum gobuster/ffuf yang lebih dalam. Directory listing yang aktif (seperti `/uploads/`) membocorkan isi folder — temuan tersendiri. Sisi blue (board DevSecOps modul 02): matikan autoindex/directory listing, jangan tinggalkan file/aplikasi usang di docroot, lindungi path admin dengan auth.'
    source: https://nmap.org/book/nse.html

  - id: b12
    tier: B
    type: text
    objective: creds
    prompt: Metasploitable punya kredensial default/lemah seperti msfadmin:msfadmin. Mengapa ini pelajaran dunia nyata yang penting?
    answers: ['kredensial default/lemah masih salah satu jalur bobol teratas di dunia nyata; enumerasi user + coba default/umum sering langsung berhasil']
    patterns: ['(default|lemah)[^\n]*(jalur|penyebab|bobol|breach)[^\n]*(teratas|umum|utama)', '(coba|tebak)[^\n]*(default|umum)[^\n]*(berhasil|masuk)', 'dunia nyata[^\n]*(default|lemah|kredensial)']
    tests:
      fail: ['kredensial default tidak relevan di dunia nyata', 'semua orang sudah ganti default', 'tidak pernah berhasil', 'hanya masalah di lab']
    explanation: 'Kredensial default/lemah (`admin:admin`, `root:toor`, `msfadmin:msfadmin`, password kosong di phpMyAdmin) adalah salah satu **jalur bobol teratas di dunia nyata** — perangkat IoT, panel admin, database, dan aplikasi sering dibiarkan default. Alur: enumerasi user (a08) → coba default/password umum → sering langsung masuk, tanpa exploit canggih sama sekali. Metasploitable mengajarkannya secara aman. Pelajaran blue (board DevSecOps modul 03/04): ganti SEMUA default, wajibkan password kuat + MFA, dan larang kredensial umum. Serangan paling sukses sering paling membosankan: cuma coba password yang bisa ditebak.'
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: b13
    tier: B
    type: text
    objective: services
    prompt: Mengapa enumerasi harus mencakup servis di port TIDAK umum/UDP, bukan hanya web (80/443)?
    answers: ['jalan masuk sering di servis non-web (smb, nfs, snmp, db, ftp) atau port tak lazim; fokus hanya web melewatkan banyak permukaan serangan']
    patterns: ['(servis non-?web|smb|nfs|snmp|database|ftp|port (tak lazim|tinggi|aneh|udp))', '(jalan masuk|celah|exploit)[^\n]*(sering|banyak)[^\n]*(non-?web|servis lain|udp)', '(fokus|hanya) web[^\n]*(lewat|melewatkan|miss)']
    tests:
      fail: ['hanya web yang penting', 'servis lain tidak bisa dieksploitasi', 'udp tidak relevan', 'cukup enumerasi port 80']
    explanation: 'Pemula sering fokus hanya ke web (80/443) dan melewatkan jalan masuk termudah. Di Metasploitable, kompromi paling cepat justru lewat **servis non-web**: vsftpd 2.3.4 backdoor (21), Samba usermap_script (139/445), UnrealIRCd backdoor (6667), kredensial lemah MySQL/PostgreSQL (3306/5432), NFS terbuka (2049), SNMP public (161/UDP). Enumerasi menyeluruh mencakup semua servis yang ditemukan di modul 02 (termasuk UDP & port tinggi), bukan hanya web. Permukaan serangan = SEMUA servis, bukan hanya yang paling terlihat. Sisi blue: setiap servis yang dibiarkan = permukaan tambahan (board DevSecOps: kurangi eksposur).'
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: b14
    tier: B
    type: text
    objective: http
    prompt: Saat enumerasi web menemukan phpMyAdmin, apa yang pertama kali dicoba dan kenapa sering berhasil?
    answers: ['coba login default/kosong (root tanpa password / root:root) — phpMyAdmin/MySQL sering dibiarkan dengan kredensial default → akses penuh database']
    patterns: ['(login|kredensial)[^\n]*(default|kosong|root)[^\n]*(tanpa password|root)?', '(phpmyadmin|mysql)[^\n]*(default|kosong|lemah)', 'root[^\n]*(tanpa|kosong)[^\n]*password']
    tests:
      fail: ['langsung exploit zero-day', 'phpmyadmin selalu aman', 'tidak ada yang bisa dicoba', 'butuh password kuat selalu']
    explanation: 'phpMyAdmin = antarmuka web ke MySQL. Yang pertama dicoba: **login default/kosong** — `root` tanpa password, atau `root:root`/`root:toor`. Sering berhasil karena admin lupa/malas mengeset password MySQL (terutama di dev/lab seperti Metasploitable). Akses phpMyAdmin = akses penuh database (baca data sensitif, ubah user aplikasi, dan via `INTO OUTFILE`/`sys_exec` kadang → eksekusi perintah di server!). Alur klasik: enumerasi menemukan phpMyAdmin → coba default → akses DB → eskalasi ke RCE. Sisi blue (board DevSecOps modul 04/07): password DB kuat, batasi phpMyAdmin (jangan ekspos, butuh auth tambahan), MySQL user hak minimal.'
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: b15
    tier: B
    type: command
    objective: creds
    prompt: Enumerasi user SMTP spesifik dengan tool smtp-user-enum memakai wordlist `users.txt` di `192.168.56.101`.
    answers: ['smtp-user-enum -M VRFY -U users.txt -t 192.168.56.101']
    patterns: ['^(?=.*-U[ \t]+\S+)(?=.*-t[ \t]+\S*192\.168)smtp-user-enum[ \t]', '^smtp-user-enum[ \t].*-U[ \t]+\S+']
    tests:
      pass:
        - 'smtp-user-enum -M VRFY -U users.txt -t 192.168.56.101'
        - 'smtp-user-enum -M VRFY -U users.txt -t 192.168.56.101'
      fail:
        - 'smtp-user-enum 192.168.56.101'
        - 'nmap -p 25 192.168.56.101'
        - 'hydra smtp 192.168.56.101'
    explanation: '`smtp-user-enum -M VRFY -U <userlist> -t <target>` mencoba tiap nama dari wordlist lewat perintah SMTP `VRFY` (atau `-M EXPN`/`RCPT`) dan melaporkan mana yang valid. Memanen daftar user sistem (ATT&CK T1087) untuk brute force. Alternatif otomatis: `nmap --script smtp-enum-users` (a13). Metasploitable Postfix rentan enumerasi ini. Hasilnya (user valid seperti `root`, `msfadmin`) → input untuk Hydra (modul password attacks). Sisi blue (board DevSecOps): nonaktifkan `VRFY`/`EXPN`, respons seragam untuk user ada/tidak, dan rate-limit koneksi SMTP agar enumerasi massal gagal/terdeteksi.'
    source: https://attack.mitre.org/techniques/T1087/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: services
    prompt: |
      Dari scanning (modul 02) kamu tahu Metasploitable membuka: 21 FTP, 22 SSH, 80 HTTP, 139/445 Samba, 2049 NFS,
      161/UDP SNMP, 3306 MySQL. Rancang enumerasi mendalam per servis (satu poin per baris).
    checklist:
      - id: web
        label: Web (80) — robots, direktori (gobuster/ffuf), teknologi (whatweb), nikto, aplikasi tersembunyi
        required: true
        patterns: ['(robots|gobuster|ffuf|dirb|nikto|whatweb|http-enum)', 'web|direktori|aplikasi tersembunyi']
      - id: smb
        label: Samba (139/445) — enum4linux/smbclient untuk share, user, null session
        required: true
        patterns: ['(enum4linux|smbclient|smb-enum)', 'share|null session|samba']
      - id: ftp
        label: FTP (21) — cek anonymous & versi (vsftpd 2.3.4?)
        patterns: ['ftp[^\n]*(anonymous|anon)', 'ftp-anon|vsftpd']
      - id: nfs
        label: NFS (2049) — showmount -e untuk export terbuka
        patterns: ['showmount|nfs[^\n]*(export|mount)']
      - id: snmp
        label: SNMP (161/UDP) — snmpwalk community public untuk info sistem
        patterns: ['snmpwalk|snmp[^\n]*(public|community)']
      - id: creds
        label: Kumpulkan user valid (SMB/SMTP) & coba kredensial default/lemah (MySQL)
        patterns: ['(user valid|daftar user|username)', '(default|lemah|kosong)[^\n]*(kredensial|password|root)|mysql[^\n]*(default|lemah)']
      - id: no-webonly
        label: Tidak hanya enumerasi web lalu mengabaikan servis lain
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain))[^\n]*(cukup|hanya)[^\n]*(web|port 80)[^\n]*(saja|abaikan)']
    pass_score: 0.5
    solution: |
      ```text
      Web (80):   curl robots.txt (lab: bocor /admin,/backup) → gobuster/ffuf direktori → whatweb (teknologi) →
                  nikto (masalah umum) → temukan DVWA/Mutillidae/phpMyAdmin (aplikasi rentan tersembunyi).
      Samba(445): enum4linux -a (share+user+OS) → smbclient -L -N (null session) → connect share writable.
      FTP (21):   nmap --script ftp-anon / ftp anonymous → catat versi (vsftpd 2.3.4 = backdoor terkenal).
      NFS (2049): showmount -e → cek export terbuka (/ * = jalur akses root via authorized_keys).
      SNMP(161U): snmpwalk -c public -v1 → proses/user/software/kredensial.
      MySQL(3306):coba root tanpa password / default; kumpulkan user valid dari SMB/SMTP untuk brute (modul berikutnya).
      ```
      Enumerasi menyeluruh = tiap servis digali, bukan hanya web. Jalan masuk termudah di Metasploitable justru di
      servis non-web (vsftpd/Samba/NFS). Fokus hanya web = melewatkan pintu yang terbuka lebar.
    tests:
      pass:
        - |
          web port 80 ambil robots.txt lalu gobuster atau ffuf untuk direktori whatweb untuk teknologi nikto dan cari aplikasi tersembunyi seperti dvwa phpmyadmin
          samba 139 445 pakai enum4linux dan smbclient untuk share user dan null session
          ftp 21 cek anonymous dan catat versi vsftpd 2.3.4
          nfs 2049 showmount -e untuk melihat export terbuka
          snmp 161 udp snmpwalk community public untuk info sistem
          kumpulkan user valid dari smb dan smtp lalu coba kredensial default lemah mysql
          jangan hanya enumerasi web lalu mengabaikan servis lain
        - |
          enumerasi web gobuster nikto whatweb robots, samba enum4linux smbclient null session
          ftp anonymous dan versi, nfs showmount export, snmpwalk public, user valid plus default creds mysql, jangan web saja
      fail:
        - |
          cukup enumerasi web port 80 saja abaikan servis lain
          tidak perlu smb atau nfs
        - |
          langsung exploit tanpa enumerasi servis
          snmp dan nfs tidak penting
        - |
          hanya buka homepage di browser
          servis lain tidak usah digali
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: c02
    tier: C
    type: scenario
    objective: creds
    prompt: |
      Enumerasi mengungkap daftar user valid (msfadmin, user, service) dan beberapa servis login (SSH, FTP, MySQL).
      Rancang cara memanfaatkan info ini menuju akses — dan catat pertahanannya (satu poin per baris).
    checklist:
      - id: default
        label: Coba kredensial default/umum dulu (msfadmin:msfadmin, root kosong) sebelum brute
        required: true
        patterns: ['(default|umum|lemah)[^\n]*(kredensial|password)', '(msfadmin|root)[^\n]*(kosong|default|msfadmin)', 'coba[^\n]*(default|umum)[^\n]*(dulu|sebelum)']
      - id: spray
        label: Password spraying (satu password umum ke banyak user) untuk hindari lockout
        patterns: ['(spraying|spray)', '(satu password|password umum)[^\n]*(banyak user)', 'hindari lockout']
      - id: targetservice
        label: Arahkan brute ke servis yang menerima password (cek ssh-auth-methods dulu)
        patterns: ['(ssh|ftp|mysql)[^\n]*(password|brute)', 'ssh-auth-methods|cek[^\n]*(auth|metode)', 'servis[^\n]*(terima|password auth)']
      - id: document
        label: Dokumentasikan kredensial yang didapat (untuk akses & laporan)
        patterns: ['(dokumentasi|catat|simpan)[^\n]*(kredensial|password|user)', 'laporan|notes']
      - id: defense
        label: Catat pertahanan (purple) - MFA, lockout, larang password umum, matikan password-auth SSH
        patterns: ['mfa|lockout|larang password (umum|lemah)', 'matikan password[^\n]*ssh|passwordauthentication no|key-?only', 'pertahanan|blue|remediasi']
      - id: no-blindbrute
        label: Tidak langsung brute force masif tanpa coba default & tanpa cek metode auth
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain|sebelum))[^\n]*(langsung|asal)[^\n]*(brute)[^\n]*(masif|semua|tanpa)']
    pass_score: 0.5
    solution: |
      ```text
      1. Coba kredensial DEFAULT/umum dulu: msfadmin:msfadmin, root tanpa password (MySQL), admin:admin — paling cepat,
         sering langsung berhasil tanpa brute sama sekali (b12).
      2. Password spraying: satu password umum (mis. "password") ke SEMUA user valid → hindari lockout per-akun (b06).
      3. Cek servis mana menerima password (nmap --script ssh-auth-methods, b09) → arahkan brute ke sana (SSH/FTP/MySQL).
      4. Dokumentasikan setiap kredensial yang didapat (untuk akses lanjut & laporan, modul 10).
      5. (Purple) pertahanannya: MFA, account lockout cerdas, larang password umum/bocoran, matikan PasswordAuthentication
         SSH (key-only — board DevSecOps modul 03 yang sudah kamu buktikan!), rate-limit login.
      ```
      Alur efektif: default dulu (gratis) → spraying (aman dari lockout) → brute terarah (cek metode auth dulu).
      Brute masif buta tanpa coba default itu lambat, berisik, dan sering tak perlu.
    tests:
      pass:
        - |
          coba kredensial default dan umum dulu seperti msfadmin msfadmin dan root kosong sebelum brute
          lakukan password spraying satu password umum ke banyak user untuk hindari lockout
          cek dulu servis mana menerima password dengan ssh-auth-methods lalu arahkan brute ke ssh ftp atau mysql
          dokumentasikan kredensial yang didapat untuk akses dan laporan
          catat pertahanan purple seperti mfa lockout larang password umum dan matikan password auth ssh key only
          jangan langsung brute force masif tanpa coba default dan cek metode auth
        - |
          default dulu msfadmin dan root kosong, lalu spraying hindari lockout, cek ssh-auth-methods lalu brute terarah
          dokumentasikan kredensial, pertahanan mfa lockout dan key only ssh, jangan brute buta
      fail:
        - |
          langsung brute force masif semua user tanpa coba default
          tidak usah cek metode auth
        - |
          tebak acak tanpa daftar user
          pertahanan tidak perlu dicatat
        - |
          asal brute semua servis sekaligus
          default dan spraying tidak penting
    source: https://attack.mitre.org/techniques/T1087/

  - id: c03
    tier: C
    type: scenario
    objective: purple
    prompt: |
      Pola pikir purple: kamu baru meng-enumerasi Metasploitable dan memanen banyak info (versi, user, share, path).
      Rancang bagaimana blue team MENGURANGI panen ini & mendeteksi enumerasinya (satu poin per baris).
    checklist:
      - id: hide
        label: Sembunyikan versi/banner (server_tokens off, hapus X-Powered-By)
        required: true
        patterns: ['(sembunyi|server_tokens|banner|x-powered-by)[^\n]*(versi|off|hapus)', 'versi[^\n]*(disembunyikan|tidak bocor)']
      - id: disable
        label: Matikan fitur bocor (anonymous FTP, null SMB, SMTP VRFY, SNMP public, autoindex)
        required: true
        patterns: ['(matikan|nonaktif|tutup)[^\n]*(anonymous|null|vrfy|snmp|public|autoindex|directory listing)', 'anonymous ftp|null session|vrfy|snmp public']
      - id: restrict
        label: Batasi servis & akses (tutup port tak perlu, segmentasi, firewall)
        patterns: ['(tutup|batasi)[^\n]*(port|servis)', 'segmentasi|firewall|least exposure|kurangi eksposur']
      - id: hardencreds
        label: Hilangkan kredensial default/lemah + MFA (serangan user-enum jadi sia-sia)
        patterns: ['(ganti|hilang|larang)[^\n]*(default|lemah)[^\n]*(kredensial|password)', 'mfa|password kuat']
      - id: detect
        label: Deteksi pola enumerasi (lonjakan 404/query/koneksi) di log/IDS/SIEM
        required: true
        patterns: ['(deteksi|log|ids|siem)[^\n]*(enumeras|404|scan|probe|pola)', 'alert[^\n]*(enumeras|scan|404)']
      - id: patch
        label: Patch/upgrade servis usang (hilangkan versi rentan yang bocor)
        patterns: ['patch|upgrade|update[^\n]*(servis|versi|usang)']
      - id: no-ignore
        label: Tidak menganggap enumerasi "tak berbahaya karena belum ada eksploitasi"
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain))[^\n]*(abaikan|biarkan)[^\n]*(enumeras|recon)[^\n]*(tak berbahaya|belum|aman|biasa)']
    pass_score: 0.45
    solution: |
      ```text
      1. Sembunyikan versi/banner: server_tokens off, hapus X-Powered-By → -sV/whatweb penyerang kurang informatif (DevSecOps 02/07).
      2. Matikan fitur bocor: anonymous FTP, null SMB session, SMTP VRFY/EXPN, SNMP community public, autoindex/dir listing.
      3. Batasi servis & akses: tutup port tak perlu, segmentasi jaringan, firewall → lebih sedikit yang bisa di-enum (DevSecOps 03).
      4. Hilangkan kredensial default/lemah + MFA → daftar user yang dipanen jadi tak berguna untuk brute (DevSecOps 03/04).
      5. Deteksi pola enumerasi: lonjakan 404/query SMB/SNMP/koneksi dari satu sumber di log/IDS/SIEM → alert DINI (DevSecOps 09).
      6. Patch/upgrade servis usang → versi yang (jika) bocor tak punya exploit yang cocok (DevSecOps 05).
      ```
      Enumerasi memanen info & mendahului eksploitasi — jadi blue mengurangi panennya (sembunyi/matikan/batasi) DAN
      mendeteksinya (alert dini). Menganggap enumerasi "belum berbahaya" membuang peringatan paling awal: ia justru
      saat terbaik menghentikan serangan sebelum eksploitasi.
    tests:
      pass:
        - |
          sembunyikan versi dan banner dengan server_tokens off dan hapus x-powered-by
          matikan fitur bocor anonymous ftp null smb session smtp vrfy snmp public dan autoindex
          batasi servis dan akses tutup port tak perlu segmentasi dan firewall
          hilangkan kredensial default dan lemah serta terapkan mfa agar user-enum sia-sia
          deteksi pola enumerasi lonjakan 404 query dan koneksi di log ids siem lalu alert dini
          patch dan upgrade servis usang untuk hilangkan versi rentan
          jangan anggap enumerasi tak berbahaya karena belum ada eksploitasi
        - |
          server_tokens off dan hapus banner, matikan anonymous null vrfy snmp public autoindex
          tutup port segmentasi firewall, ganti default creds plus mfa, deteksi lonjakan 404 di siem, patch servis usang
      fail:
        - |
          abaikan enumerasi karena belum ada eksploitasi dan tak berbahaya
          biarkan banner versi tampil
        - |
          tidak perlu sembunyikan versi atau matikan fitur
          enumerasi tidak bisa dideteksi
        - |
          biarkan anonymous dan null session aktif
          default creds tidak masalah
    source: https://owasp.org/www-project-web-security-testing-guide/
---

# Enumeration Mendalam

Modul 02 memberi peta kasar (port, servis, versi). Modul ini **menggali tiap servis** untuk detail yang bisa
dimanfaatkan: direktori web tersembunyi, share SMB, user valid, export NFS, konfigurasi lemah. "Enumeration is key" —
makin dalam enumerasi, makin terarah eksploitasi.

> [!NOTE]
> Enumerasi **web & direktori diverifikasi penulis** di lab Docker terisolasi (curl robots → bocor `/admin`,`/backup`;
> `nmap --script http-enum` → `/admin/`,`/uploads/` listing; probe `/admin/`→200 vs `/backup/`→404). Enumerasi
> **SMB/NFS/SMTP/SNMP** (enum4linux, smbclient, snmpwalk, dll) didokumentasikan untuk Metasploitable — kamu jalankan
> di Kali. Semua pada target milik sendiri.

## 1. Enumerasi per servis (peta)

| Servis | Tool/perintah | Panen |
|---|---|---|
| Web 80 | `curl robots.txt`, `gobuster/ffuf`, `whatweb`, `nikto`, `nmap --script http-enum` | direktori, teknologi, aplikasi rentan (DVWA/phpMyAdmin) |
| SMB 139/445 | `enum4linux -a`, `smbclient -L //ip/ -N` | share, user, info OS, null session |
| FTP 21 | `nmap --script ftp-anon`, `ftp <ip>` | anonymous login, file, versi (vsftpd 2.3.4!) |
| NFS 2049 | `showmount -e <ip>` | export terbuka (`/ *` → akses root) |
| SNMP 161/UDP | `snmpwalk -c public -v1 <ip>` | proses, user, software, kredensial |
| SMTP 25 | `nmap --script smtp-enum-users`, `smtp-user-enum` | daftar user valid (VRFY) |
| SSH 22 | `nmap --script ssh-auth-methods` | apakah password-auth aktif (→ brute?) |
| MySQL 3306 | coba `root` tanpa password | akses DB via kredensial default |

## 2. Enumerasi web (hasil lab)

```text
curl http://target/robots.txt     → Disallow: /admin  /backup   (bocor path!)
curl -I http://target/            → Server: Apache/2.4.71 (versi); header keamanan hilang
nmap --script http-enum -p 80     → /admin/ (200), /uploads/ (listing!), /robots.txt
dir brute-force (gobuster/ffuf)   → /admin/→200 (ada), /backup/→404 (tidak)
```

**200** = ada & terbuka · **403** = ada tapi dilarang (tetap ada!) · **404** = tidak ada.

## 3. User = separuh kredensial

Enum4linux (SMB), smtp-user-enum (SMTP VRFY), pesan login → **daftar username valid** (ATT&CK T1087). Itu separuh
jalan: tinggal cari password lewat default/spraying/brute (modul berikutnya). Metasploitable bocor `msfadmin`, `user`,
dll. **Kredensial default/lemah** (`msfadmin:msfadmin`, phpMyAdmin kosong) sering langsung berhasil tanpa exploit —
jalur bobol dunia nyata paling umum.

## 4. Jangan hanya web

Jalan masuk termudah Metasploitable justru **non-web**: vsftpd 2.3.4 (backdoor), Samba (usermap_script), NFS `/ *`
(tanam `authorized_keys` → ssh root), SNMP `public`, MySQL default. Enumerasi SEMUA servis & UDP, bukan hanya 80.

## 5. Purple — kurangi panen & deteksi

| Enumerasi (red) | Pertahanan/deteksi (blue — DevSecOps) |
|---|---|
| versi/banner (`-sV`, whatweb, `curl -I`) | `server_tokens off`, hapus `X-Powered-By` (02/07) |
| anonymous FTP / null SMB / SMTP VRFY / SNMP public | matikan fitur bocor itu |
| dir brute (ratusan 404) | deteksi lonjakan 404 per IP (09) — persis lab biru-mu |
| daftar user → brute | MFA, lockout, larang password umum (03/04) |
| versi usang → exploit | patch/upgrade servis (05) |

> [!WARNING]
> Semua di Metasploitable milik sendiri, lab terisolasi. Enumerasi user/servis pada sistem orang tanpa izin tetap
> tak sah. Dan ingat: enumerasi itu **berisik** — ratusan 404/query dari satu IP adalah sinyal serangan dini yang
> kamu (sebagai blue) justru ingin tangkap.

> [!NOTE]
> Lanjut ke modul 04 (Analisis Kerentanan) — mengubah hasil enumerasi (versi & konfigurasi) jadi daftar kerentanan
> & exploit yang cocok: searchsploit, database CVE, dan NSE vuln.
