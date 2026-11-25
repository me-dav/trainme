---
format: 1
id: sec-recon-exposure
course:
  slug: devsecops
  title: DevSecOps
  description: Keamanan untuk DevOps dari dua sisi — cara kerja red team dan cara blue team mendeteksi serta menutupnya. Model ancaman, hardening server & container, keamanan web, ketersediaan (anti-DDoS), deteksi, respons insiden, dan keamanan pipeline. Semua praktik di lab terisolasi milik sendiri.
title: Recon & Exposure
summary: "Bagaimana penyerang memetakan target pada tahap Reconnaissance — scan port & versi, banner yang bocor, berkas sensitif yang ikut ter-publish (.env, .git, backup), robots.txt yang justru jadi peta, dan beda status code yang membocorkan keberadaan resource — lalu sisi blue-nya, yaitu attack surface reduction: menyembunyikan banner, memblokir berkas, menutup port dengan default-deny, dan menjaga inventori aset."
order: 2
version: 1
status: verified
updated: '2026-11-25'
tested_on:
  - Docker Desktop (Windows) — nginx:alpine (nginx/1.31.10) sebagai target dengan kebocoran yang sengaja dipasang
  - alpine:3.20 attacker — nmap 7.95 + nmap-scripts, curl; semua di network lab lokal
objectives:
  - id: enumerate
    text: Memetakan layanan, versi, dan path dengan scan serta enumerasi
  - id: leak
    text: Mengenali kebocoran informasi lewat banner, berkas sensitif, dan status code
  - id: osint
    text: Memahami sumber recon pasif (robots.txt, .git, metadata) dan batas robots.txt
  - id: reduce
    text: Mengurangi attack surface — sembunyikan banner, blokir berkas, tutup port
  - id: inventory
    text: Menjaga inventori aset agar tahu apa yang ter-ekspos
sources:
  - title: MITRE ATT&CK — Reconnaissance (TA0043) & Active Scanning (T1595)
    url: https://attack.mitre.org/tactics/TA0043/
  - title: OWASP — Information exposure & fingerprinting
    url: https://owasp.org/www-project-web-security-testing-guide/
  - title: nginx — server_tokens
    url: https://nginx.org/en/docs/http/ngx_http_core_module.html#server_tokens
  - title: nginx — location & access control (deny)
    url: https://nginx.org/en/docs/http/ngx_http_access_module.html
  - title: Nmap — version detection (-sV)
    url: https://nmap.org/book/vscan.html
  - title: Google — robots.txt specification (bukan mekanisme keamanan)
    url: https://developers.google.com/search/docs/crawling-indexing/robots/intro
  - title: curl — --write-out (%{http_code})
    url: https://curl.se/docs/manpage.html#-w
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: enumerate
    prompt: Recon yang **menyentuh** target secara langsung (scan port, request HTTP) sehingga bisa terekam di log, disebut recon apa?
    answers: ['aktif']
    patterns: ['^(recon )?aktif$', '^active( recon(naissance)?)?$']
    tests:
      fail: ['pasif', 'passive', 'osint', 'statis']
    explanation: 'Recon **aktif** mengirim paket ke target (nmap, curl, brute path) — cepat dan lengkap, tapi ramai di log sehingga blue team bisa melihatnya. Recon **pasif** mengumpulkan info tanpa menyentuh target (OSINT, DNS publik, GitHub, mesin pencari) — nyaris tak terdeteksi. Penyerang serius mulai dari pasif agar tidak terlihat, baru aktif saat perlu. Blue team tidak bisa mendeteksi recon pasif, jadi fokusnya mengurangi apa yang bisa ditemukan.'
    source: https://attack.mitre.org/tactics/TA0043/

  - id: a02
    tier: A
    type: command
    objective: leak
    prompt: Ambil berkas konfigurasi `.env` yang mungkin ter-ekspos di `http://10.90.0.10/` dengan curl.
    answers: ['curl http://10.90.0.10/.env']
    patterns: ['^curl( -[A-Za-z]+)* http://10\.90\.0\.10/\.env$']
    tests:
      pass: ['curl -s http://10.90.0.10/.env']
      fail: ['curl http://10.90.0.10/env', 'curl http://10.90.0.10/.git', 'wget http://10.90.0.10/.env']
    explanation: 'Di lab → `APP_KEY=base64:SECRET123` dan `DB_PASSWORD=supersecret` langsung terbaca (HTTP 200). Berkas `.env` sering tak sengaja masuk web root saat deploy, dan karena diawali titik orang mengira "tersembunyi" — padahal web server menyajikannya seperti berkas biasa. Ini kebocoran paling parah pada tahap Recon: kredensial langsung di tangan penyerang tanpa perlu eksploit apa pun.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a03
    tier: A
    type: command
    objective: osint
    prompt: Ambil berkas `/.git/config` di `http://10.90.0.10/` untuk melihat apakah folder `.git` ikut ter-publish.
    answers: ['curl http://10.90.0.10/.git/config']
    patterns: ['^curl( -[A-Za-z]+)* http://10\.90\.0\.10/\.git/config$']
    tests:
      pass: ['curl -s http://10.90.0.10/.git/config']
      fail: ['curl http://10.90.0.10/git/config', 'curl http://10.90.0.10/.gitconfig', 'curl http://10.90.0.10/.git']
    explanation: 'Di lab → `[remote "origin"] url = https://github.com/acme/private-repo.git`, membocorkan keberadaan repo privat. Bila seluruh folder `.git` ter-publish, penyerang dapat mengunduh objek-objeknya dan **merekonstruksi seluruh source code** beserta riwayatnya — termasuk secret yang pernah di-commit lalu "dihapus". Jangan pernah men-deploy folder `.git` ke web root.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a04
    tier: A
    type: text
    objective: reduce
    prompt: Direktif nginx yang mematikan tampilan nomor versi di header `Server` dan halaman error?
    answers: ['server_tokens']
    patterns: ['server_tokens( off)?']
    tests:
      fail: ['server_name', 'autoindex', 'server_version', 'hide_version']
    explanation: 'Di lab, default → `Server: nginx/1.31.10`; setelah `server_tokens off;` → `Server: nginx` saja. Versi yang terbuka memudahkan penyerang mencocokkan CVE spesifik. Menyembunyikannya bukan pengaman utama (versi tetap bisa ditebak dari perilaku), tapi memperlambat recon dan mengurangi info gratis. Pertahanan sebenarnya tetap: patch versinya.'
    source: https://nginx.org/en/docs/http/ngx_http_core_module.html#server_tokens

  - id: a05
    tier: A
    type: text
    objective: leak
    prompt: Saat enumerasi path, status HTTP `403 Forbidden` (bukan `404`) justru membocorkan apa kepada penyerang?
    answers: ['bahwa resource itu ada']
    patterns: ['(resource|path|folder|berkas|file|direktori|sumber daya)[^\n]*(ada|exist|terdapat)', 'keberadaan', 'memang ada']
    tests:
      fail: ['bahwa server mati', 'bahwa tidak ada apa-apa', 'password', 'versi server']
    explanation: 'Di lab, `/uploads/` (folder tanpa index) → `403`, sedangkan `/nope` → `404`. Perbedaan ini sendiri adalah kebocoran: `403` berarti "ada tapi dilarang", `404` berarti "tidak ada". Penyerang memakai beda status code untuk memetakan struktur tanpa pernah melihat isinya. Karena itu config yang baik mengembalikan `404` untuk berkas sensitif, bukan `403` — menyembunyikan keberadaannya sekalian.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a06
    tier: A
    type: text
    objective: osint
    prompt: 'Benar atau salah: menuliskan `Disallow: /admin` di `robots.txt` membuat `/admin` aman karena penyerang tidak bisa mengaksesnya.'
    answers: ['salah']
    patterns: ['^salah$', '^false$', '^tidak( benar)?$']
    tests:
      fail: ['benar', 'true', 'ya']
    explanation: 'Di lab, `robots.txt` memuat `Disallow: /admin` dan `Disallow: /backup.sql`, dan `/admin/` tetap bisa diakses (HTTP 200/301). `robots.txt` hanya **meminta** crawler sopan untuk tidak mengindeks; ia tidak memblokir siapa pun. Malah sebaliknya: ia menjadi **peta** yang memberi tahu penyerang path sensitif mana yang ingin kamu sembunyikan. Lindungi path dengan autentikasi dan kontrol akses, bukan robots.txt.'
    source: https://developers.google.com/search/docs/crawling-indexing/robots/intro

  - id: a07
    tier: A
    type: command
    objective: enumerate
    prompt: Deteksi versi layanan di port `80` host `10.90.0.10` dengan nmap (service/version detection).
    answers: ['nmap -sV -p 80 10.90.0.10']
    patterns: ['^(?=.*\s-sV(\s|$))(?=.*\s-p ?80(\s|$))nmap( (-[A-Za-z-]+|-p ?80|-T\d))* 10\.90\.0\.10$']
    tests:
      pass: ['nmap -Pn -sV -p 80 10.90.0.10', 'nmap -sV -p80 10.90.0.10']
      fail: ['nmap -p 80 10.90.0.10', 'nmap -sV 10.90.0.10', 'nmap -sv -p 80 10.90.0.10']
    explanation: 'Di lab → `80/tcp open http nginx 1.31.10`. `-sV` menyelidiki isi respons untuk menebak software + versi, lebih dalam daripada sekadar membaca banner (karena banner bisa dipalsukan/disembunyikan). Dari versi ini penyerang melangkah ke pencarian CVE. Di sisi blue, inilah alasan menjaga service tetap ter-patch (modul 05) lebih penting daripada sekadar `server_tokens off`.'
    source: https://nmap.org/book/vscan.html

  - id: a08
    tier: A
    type: text
    objective: reduce
    prompt: Istilah untuk **keseluruhan titik** (port, endpoint, berkas, service) yang bisa dicoba penyerang untuk masuk, disebut apa?
    answers: ['attack surface']
    patterns: ['attack[- ]?surface', 'permukaan serang(an)?']
    tests:
      fail: ['threat model', 'kill chain', 'blast radius', 'perimeter']
    explanation: 'Attack surface = jumlah semua "pintu" yang terekspos. Mengurangi attack surface (attack surface reduction) adalah pertahanan paling dasar: makin sedikit yang terekspos, makin sedikit yang bisa diserang. Praktiknya: tutup port yang tidak dipakai, hapus berkas/endpoint yang tidak perlu, matikan fitur yang tak terpakai, dan jangan publikasikan apa pun yang tidak harus publik.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a09
    tier: A
    type: text
    objective: reduce
    prompt: Kebijakan firewall yang **menolak semua secara default** dan hanya mengizinkan yang diperlukan secara eksplisit, disebut apa?
    answers: ['default-deny']
    patterns: ['default[- ]?deny', 'tolak (semua )?(secara )?default', 'deny (by|secara) default', 'whitelist']
    tests:
      fail: ['default-allow', 'blacklist', 'permit all', 'stateful']
    explanation: 'Default-deny (whitelist): tutup semuanya, buka hanya port/sumber yang benar-benar dibutuhkan. Lawannya default-allow (blacklist) rapuh karena kamu harus mengingat semua hal buruk untuk diblokir — satu yang terlupa = celah. Dengan default-deny, service baru yang tak sengaja listen tidak otomatis terekspos ke internet. Ini mengecilkan attack surface secara langsung dan dibahas lebih teknis di modul 03.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a10
    tier: A
    type: text
    objective: leak
    prompt: Kebocoran paling kritis dari sebuah berkas `.env` yang ter-publish adalah jenis data apa?
    answers: ['kredensial']
    patterns: ['kredensial', 'credential', 'secret', 'password', 'kata sandi', 'kunci|key']
    tests:
      fail: ['alamat IP', 'versi framework', 'nama kolom', 'log']
    explanation: 'Di lab, `.env` membocorkan `DB_PASSWORD=supersecret` dan `APP_KEY`. Kredensial yang bocor melewati semua lapisan pertahanan lain — penyerang tinggal login. Ini menyambung ke modul 04 (Secrets): secret tidak boleh berada di web root, idealnya tidak di dalam image sama sekali, melainkan di-inject saat runtime dari secret manager.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a11
    tier: A
    type: command
    objective: enumerate
    prompt: Ambil `robots.txt` dari `http://10.90.0.10/` dengan curl untuk melihat path yang "disembunyikan" pemilik situs.
    answers: ['curl http://10.90.0.10/robots.txt']
    patterns: ['^curl( -[A-Za-z]+)* http://10\.90\.0\.10/robots\.txt$']
    tests:
      pass: ['curl -s http://10.90.0.10/robots.txt']
      fail: ['curl http://10.90.0.10/robot.txt', 'curl http://10.90.0.10/sitemap.xml', 'curl http://10.90.0.10/.robots']
    explanation: 'Di lab → `Disallow: /admin` dan `Disallow: /backup.sql`. Membaca `robots.txt` adalah langkah recon pertama yang murah: pemilik situs sering mencantumkan justru path yang paling ingin disembunyikan. Ini contoh bahwa "menyembunyikan" tanpa kontrol akses malah membocorkan. Jangan menaruh path sensitif di `robots.txt`; lindungi dengan autentikasi.'
    source: https://developers.google.com/search/docs/crawling-indexing/robots/intro

  - id: a12
    tier: A
    type: text
    objective: inventory
    prompt: Daftar terkini semua aset (host, service, port, domain, berkas publik) yang kamu miliki, dasar untuk tahu apa yang ter-ekspos, disebut apa?
    answers: ['asset inventory']
    patterns: ['asset[- ]?inventor(y|i)', 'inventori(s)?( aset)?', 'daftar aset']
    tests:
      fail: ['threat model', 'runbook', 'changelog', 'SBOM']
    explanation: 'Kamu tidak bisa melindungi yang tidak kamu tahu ada. Server lama yang terlupa, subdomain uji coba, port debug yang masih terbuka — semuanya "shadow IT" yang jadi pintu masuk favorit. Asset inventory yang terus diperbarui (ditambah scan berkala dari luar, seperti yang dilakukan penyerang) membuat exposure tak terduga cepat ketahuan. Ini pasangan defensif dari Reconnaissance.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a13
    tier: A
    type: text
    objective: enumerate
    prompt: Teknik recon yang mencoba banyak nama path/berkas dari sebuah wordlist untuk menemukan yang ada (mis. `/admin`, `/backup`), disebut apa?
    answers: ['directory brute-force']
    patterns: ['(directory|dir|content|path)[- ]?(brute|bruteforce|brute-force|busting|enumeration|discovery)', 'brute[- ]?force (direktori|path|folder)', 'fuzzing (path|direktori)']
    tests:
      fail: ['port scanning', 'sql injection', 'phishing', 'sniffing']
    explanation: 'Di lab, loop curl atas wordlist kecil membedakan yang ada dari yang tidak lewat status code: `admin→301`, `uploads→301`, `backup.sql→200`, `.env→200`, `nope→404`. Tool nyata (ffuf, gobuster, dirb) melakukan ini ribuan kali per detik. Pertahanannya: jangan biarkan berkas sensitif bisa ditebak dan diakses, kembalikan `404` seragam, serta pasang rate limit agar brute-force melambat dan terdeteksi (modul 08).'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a14
    tier: A
    type: command
    objective: leak
    prompt: Dengan curl, tampilkan **hanya kode status HTTP** (tanpa body) saat mengakses `http://10.90.0.10/admin`, untuk enumerasi diam-diam.
    answers: ['curl -s -o /dev/null -w %{http_code} http://10.90.0.10/admin']
    patterns: ['^(?=.*\s-o /dev/null(\s|$))(?=.*\s-w %\{http_code\}(\s|$))curl( (-[A-Za-z]+|-o /dev/null|-w %\{http_code\}))* http://10\.90\.0\.10/admin$']
    tests:
      pass: ["curl -s -o /dev/null -w '%{http_code}' http://10.90.0.10/admin", 'curl -o /dev/null -s -w %{http_code} http://10.90.0.10/admin']
      fail: ['curl -w %{http_code} http://10.90.0.10/admin', 'curl -s http://10.90.0.10/admin', 'curl -I http://10.90.0.10/admin']
    explanation: 'Di lab → `301` (nginx mengalihkan `/admin` ke `/admin/`, menandakan folder itu ada). `-o /dev/null` membuang body, `-w %{http_code}` hanya mencetak angka status, `-s` menyenyapkan progress. Inilah bentuk mesin dari enumerasi: ribuan path dibedakan hanya dari angka status. Kutip `''%{http_code}''` agar `{`/`}` tidak diotak-atik shell.'
    source: https://curl.se/docs/manpage.html#-w

  - id: a15
    tier: A
    type: text
    objective: reduce
    prompt: Untuk berkas sensitif yang tidak boleh publik, nginx sebaiknya mengembalikan status `404` alih-alih `403`. Alasan utamanya apa?
    answers: ['agar tidak mengonfirmasi berkas itu ada']
    patterns: ['(tidak|tak|jangan|bukan)[^\n]*(konfirmasi|mengakui|membocorkan|menunjukkan)[^\n]*(ada|keberadaan|exist)', 'sembunyikan keberadaan', 'seolah (tidak ada|404)']
    tests:
      fail: ['agar lebih cepat', 'karena 403 error', 'agar bisa di-cache', 'supaya SEO bagus']
    explanation: 'Di lab hardening, `return 404` dipakai untuk `.env`, `.git`, `backup.sql`, dan `/admin`, bukan `deny all` (yang memberi `403`). `403` berarti "ada tapi dilarang" — mengonfirmasi keberadaannya ke penyerang. `404` berarti "tidak ada", menyembunyikan sekalian. Prinsipnya: jangan membocorkan informasi lewat perbedaan respons, sekecil apa pun.'
    source: https://nginx.org/en/docs/http/ngx_http_access_module.html

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: osint
    prompt: Folder `.git` yang ter-publish berbahaya karena, selain source code, ia juga menyimpan apa yang sering memuat secret yang "sudah dihapus"?
    answers: ['riwayat commit']
    patterns: ['(riwayat|history|histori|log)[^\n]*(commit|git)', 'commit history', 'seluruh (versi|riwayat)']
    tests:
      fail: ['cache browser', 'cookie', 'file .env saja', 'database']
    explanation: 'Git menyimpan **seluruh riwayat** di objek-objeknya. Secret yang pernah di-commit lalu "dihapus" di commit berikutnya tetap ada di riwayat dan bisa dipulihkan dari `.git` yang bocor (alat seperti git-dumper merekonstruksinya). Pelajaran ganda: (1) jangan publikasikan `.git`; (2) secret yang pernah ter-commit harus dianggap bocor selamanya dan dirotasi, bukan sekadar dihapus (modul 04).'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: b02
    tier: B
    type: text
    objective: enumerate
    prompt: Default nmap hanya memindai ~1000 port populer. Opsi apa yang memindai **seluruh** 65535 port TCP?
    answers: ['-p-']
    patterns: ['^-p-$', '^-p ?1-65535$', '^--?p ?1-65535$']
    tests:
      fail: ['-p 1000', '-F', '-sV', '--top-ports']
    explanation: '`-p-` = port 1–65535. Service pada port tak lazim (admin di 8443, metrics di 9100, debugger di 5005) terlewat oleh scan default, sehingga admin sering salah mengira "aman karena port aneh". Scan penuh menemukan semuanya. Pelajaran blue: security through obscurity bukan pertahanan; yang melindungi adalah default-deny firewall yang menutup port itu, bukan nomornya yang tidak biasa.'
    source: https://nmap.org/book/vscan.html

  - id: b03
    tier: B
    type: text
    objective: leak
    prompt: Selain header `Server`, satu header respons HTTP yang sering dipasang framework (mis. `X-Powered-By`) juga membocorkan apa?
    answers: ['teknologi/framework yang dipakai']
    patterns: ['(teknologi|framework|bahasa|runtime|software)[^\n]*(dipakai|digunakan|server)?', 'php|express|asp', 'stack']
    tests:
      fail: ['alamat email admin', 'password', 'session user', 'alamat IP internal']
    explanation: '`X-Powered-By: PHP/8.1` atau `Express` memberi tahu stack server gratis — fingerprinting. Headernya sebaiknya dimatikan (`expose_php = Off`, `app.disable(''x-powered-by'')`, atau dibersihkan di reverse proxy). Ini satu paket dengan `server_tokens off`: hilangkan semua header yang memberi tahu "aku pakai apa dan versi berapa" agar pencocokan CVE lebih sulit.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: b04
    tier: B
    type: text
    objective: leak
    prompt: Halaman error yang menampilkan **stack trace** lengkap (path berkas, nomor baris, query) ke user adalah kebocoran kategori STRIDE apa?
    answers: ['Information Disclosure']
    patterns: ['information[- ]?disclosure', 'kebocoran (informasi|data)', 'pengungkapan informasi']
    tests:
      fail: ['Tampering', 'Spoofing', 'Denial of Service', 'Elevation of Privilege']
    explanation: 'Stack trace membocorkan struktur internal: path absolut, nama framework & versi, potongan query SQL, bahkan secret. Ini Information Disclosure (modul 01). Di production, matikan debug mode (`APP_DEBUG=false` di Laravel) dan sajikan halaman error generik; kirim detailnya ke log internal, bukan ke user. Penyerang memanfaatkan stack trace untuk menyempurnakan serangan (mis. menyusun SQL injection dari error yang bocor).'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: b05
    tier: B
    type: text
    objective: reduce
    prompt: Di nginx, blok `location ~ /\. { deny all; }` dipakai untuk memblokir akses ke berkas jenis apa?
    answers: ['dotfiles']
    patterns: ['dotfile', 'berkas (ber)?awalan titik', 'berkas (yang )?(diawali|dimulai) (titik|\.)', '\.env|\.git']
    tests:
      fail: ['berkas besar', 'berkas .html', 'berkas gambar', 'symlink']
    explanation: 'Regex `/\.` cocok dengan path yang mengandung komponen berawalan titik: `.env`, `.git/`, `.htaccess`, `.ssh/`. Di lab, blok ini (dengan `return 404`) mengubah `/.env` dan `/.git/config` dari `200` menjadi `404`. Satu aturan menutup sekelompok kebocoran umum sekaligus. Pasangkan dengan aturan untuk ekstensi backup (`\.(sql|bak|zip|gz)$`) agar dump database tidak bisa diunduh.'
    source: https://nginx.org/en/docs/http/ngx_http_access_module.html

  - id: b06
    tier: B
    type: text
    objective: reduce
    prompt: Direktif nginx yang, bila `on`, menampilkan **daftar isi folder** saat tidak ada index — dan harus dipastikan `off`?
    answers: ['autoindex']
    patterns: ['autoindex( off)?']
    tests:
      fail: ['dirlist', 'server_tokens', 'index', 'fancyindex']
    explanation: 'Dengan `autoindex on`, membuka `/uploads/` menampilkan semua berkas di dalamnya — penyerang bisa menjelajah seluruh folder. Di lab, `autoindex` default `off` sehingga `/uploads/` → `403` (tetap membocorkan bahwa folder itu ada, lihat a05). Pastikan `autoindex off` di semua lokasi, terutama folder upload dan backup. Lebih baik lagi, folder upload tidak berada di web root yang bisa dieksekusi.'
    source: https://nginx.org/en/docs/http/ngx_http_core_module.html#server_tokens

  - id: b07
    tier: B
    type: text
    objective: osint
    prompt: Record DNS jenis apa yang, bila di-enumerasi, sering membocorkan subdomain internal/uji coba (mis. `dev.`, `staging.`, `vpn.`) kepada penyerang?
    answers: ['subdomain']
    patterns: ['subdomain', '\bcname\b', '\ba record\b', 'dns record']
    tests:
      fail: ['MX', 'SOA', 'PTR', 'SPF']
    explanation: 'Enumerasi subdomain (via sertifikat Certificate Transparency, brute DNS, atau zone transfer yang salah konfigurasi) membuka aset yang dikira tersembunyi: `staging.contoh.com`, `jenkins.contoh.com`. Subdomain lama/uji sering kurang di-hardening dan jadi pintu masuk. Blue: masukkan semua subdomain ke asset inventory (a12), matikan yang tidak dipakai, dan jangan andalkan nama yang tidak bisa ditebak.'
    source: https://attack.mitre.org/tactics/TA0043/

  - id: b08
    tier: B
    type: command
    objective: enumerate
    prompt: Scan **seluruh port TCP** host `10.90.0.10` tanpa host discovery (anggap hidup), untuk menemukan service di port tak lazim.
    answers: ['nmap -Pn -p- 10.90.0.10']
    patterns: ['^(?=.*\s-Pn(\s|$))(?=.*\s(-p-|-p ?1-65535)(\s|$))nmap( (-[A-Za-z-]+|-p-|-p ?1-65535|-T\d))* 10\.90\.0\.10$']
    tests:
      pass: ['nmap -p- -Pn -T4 10.90.0.10', 'nmap -Pn -p 1-65535 10.90.0.10']
      fail: ['nmap -Pn 10.90.0.10', 'nmap -p- 10.90.0.10 -sn', 'nmap -Pn -F 10.90.0.10']
    explanation: 'Gabungan dari modul 01: `-Pn` melewati ping, `-p-` memindai semua port. Dari sisi blue, lonjakan koneksi ke ribuan port dalam waktu singkat adalah tanda scan yang mudah dideteksi (IDS, log firewall). Yang penting: pastikan hasil scan-mu dari luar **sama** dengan yang kamu harapkan — port tak terduga yang terbuka berarti ada service yang lupa ditutup.'
    source: https://nmap.org/book/vscan.html

  - id: b09
    tier: B
    type: text
    objective: leak
    prompt: Sertifikat TLS sebuah situs memuat daftar **SAN** (Subject Alternative Names). Informasi apa yang bisa dipanen penyerang dari situ?
    answers: ['daftar hostname/domain lain']
    patterns: ['(hostname|host|domain|subdomain)[^\n]*(lain|internal|tambahan)?', 'nama (domain|host)', 'daftar domain']
    tests:
      fail: ['private key', 'password', 'versi server', 'alamat IP publik']
    explanation: 'Satu sertifikat sering mencakup banyak SAN: `app.contoh.com`, `api.contoh.com`, `internal.contoh.com`. Log Certificate Transparency bersifat publik, jadi penyerang memanen subdomain dari situ secara **pasif** tanpa menyentuh targetmu. Ini memperluas peta aset mereka. Blue: sadari setiap sertifikat yang kamu terbitkan mengumumkan nama di dalamnya; pisahkan sertifikat untuk host yang benar-benar internal.'
    source: https://attack.mitre.org/tactics/TA0043/

  - id: b10
    tier: B
    type: text
    objective: enumerate
    prompt: Nmap menandai port sebagai `filtered` (bukan `open` atau `closed`) saat tidak ada balasan sama sekali. Apa yang biasanya menyebabkannya?
    answers: ['firewall yang mem-DROP paket']
    patterns: ['firewall', 'drop', 'paket (di-?)?buang', 'difilter']
    tests:
      fail: ['service mati', 'port terbuka', 'DNS gagal', 'kabel putus']
    explanation: 'Tiga keadaan nmap: `open` (ada yang listen), `closed` (host hidup, balas RST — modul 01 a11), `filtered` (tak ada balasan, biasanya firewall DROP). Dari penyerang, `filtered` menandakan ada firewall — dan `closed` di banyak port menandakan host hidup tanpa firewall yang menyaring. Blue: default-deny membuat port yang tidak dibuka menjadi `filtered`, bukan `closed`, sehingga penyerang tidak langsung tahu host-nya hidup.'
    source: https://nmap.org/book/vscan.html

  - id: b11
    tier: B
    type: text
    objective: reduce
    prompt: Prinsip "jangan jalankan/pasang apa pun yang tidak diperlukan" (service, port, paket, fitur) untuk memperkecil attack surface disebut apa?
    answers: ['minimalisasi']
    patterns: ['minimal(isasi|is|isme)?', 'minimize', 'least functionality', 'hardening minimal']
    tests:
      fail: ['maksimalisasi', 'redundansi', 'defense in depth', 'obfuscation']
    explanation: 'Minimalisasi/least functionality: base image kecil (distroless/alpine, modul 05), hanya paket yang perlu, service yang tak dipakai dimatikan, port debug ditutup di production. Setiap komponen yang ada adalah komponen yang bisa rentan dan harus di-patch. "Kode yang tidak ada tidak bisa dieksploitasi." Ini sebabnya image ramping bukan sekadar soal ukuran, tapi soal keamanan.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: b12
    tier: B
    type: command
    objective: leak
    prompt: Lihat **semua header respons** (bukan hanya ringkasan) dari `http://10.90.0.10/` secara verbose dengan curl untuk fingerprinting manual.
    answers: ['curl -v http://10.90.0.10/']
    patterns: ['^curl( -[A-Za-z]+)* (-v|--verbose)( -[A-Za-z]+)* http://10\.90\.0\.10/?$', '^curl( -[A-Za-z]*v[A-Za-z]*) http://10\.90\.0\.10/?$']
    tests:
      pass: ['curl -sv http://10.90.0.10/', 'curl --verbose http://10.90.0.10/']
      fail: ['curl http://10.90.0.10/', 'curl -V http://10.90.0.10/', 'curl -s http://10.90.0.10/']
    explanation: '`-v` menampilkan seluruh percakapan: baris request, semua header respons (`Server`, `X-Powered-By`, `Set-Cookie`, dsb), dan info TLS. Hati-hati, `-V` (besar) justru menampilkan versi curl, bukan header. Setiap header adalah petunjuk fingerprint; blue team meninjau header keluar dan membuang yang membocorkan teknologi, sambil menambahkan security header (modul 07).'
    source: https://curl.se/docs/manpage.html#-w

  - id: b13
    tier: B
    type: text
    objective: inventory
    prompt: Aset terlupa yang masih online tapi tak terpantau (server uji lama, subdomain tak terpakai, port debug) sering disebut dengan istilah apa?
    answers: ['shadow IT']
    patterns: ['shadow[- ]?it', 'aset (bayangan|terlupa|hantu)', 'shadow asset', 'zombie (server|asset)']
    tests:
      fail: ['honeypot', 'bastion', 'canary', 'sandbox']
    explanation: 'Shadow IT / aset bayangan adalah sumber breach yang umum: tidak ada yang mem-patch atau memantaunya karena tidak ada yang ingat ia ada. Penyerang menemukannya justru karena mereka memetakan semua (scan penuh, enumerasi subdomain). Pertahanannya adalah asset inventory aktif + scan rutin dari luar — "serang dirimu sendiri" secara terjadwal agar exposure tak terduga ketahuan sebelum penyerang menemukannya.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: b14
    tier: B
    type: text
    objective: reduce
    prompt: Mengurangi banner & header versi membuat pencocokan CVE lebih sulit, tetapi **bukan** pengaman sejati. Apa pertahanan yang sebenarnya terhadap kerentanan versi lama?
    answers: ['patching']
    patterns: ['patch(ing|es)?', 'update|memperbarui|pembaruan', 'upgrade (versi)?', 'tambal']
    tests:
      fail: ['server_tokens off', 'ganti port', 'robots.txt', 'rename service']
    explanation: 'Menyembunyikan versi (`server_tokens off`) hanya memperlambat recon — penyerang bisa menebak versi dari perilaku, atau sekadar mencoba semua exploit. Satu-satunya pertahanan nyata terhadap CVE adalah **mem-patch**: perbarui software ke versi yang sudah diperbaiki. Modul 05 mengotomatiskan ini dengan scan image (trivy) + rebuild berkala. Obscurity adalah lapisan tambahan, bukan pengganti patch.'
    source: https://nmap.org/book/vscan.html

  - id: b15
    tier: B
    type: text
    objective: enumerate
    prompt: Dalam ATT&CK, teknik mengirim probe/scan langsung ke infrastruktur target (T1595) termasuk sub-teknik dari taktik Reconnaissance dengan nama apa?
    answers: ['Active Scanning']
    patterns: ['active[- ]?scanning', 'scan(ning)? aktif', 'pemindaian aktif']
    tests:
      fail: ['Gather Victim Host Information', 'Phishing for Information', 'Search Open Databases', 'Passive Scanning']
    explanation: 'Active Scanning (T1595) mencakup IP block scanning, vulnerability scanning, dan wordlist scanning — persis yang kita lakukan di lab (nmap, enumerasi path). Karena aktif, ia menyentuh target dan terekam, sehingga bisa dideteksi (lonjakan koneksi, 404 beruntun, User-Agent scanner). Bandingkan dengan sub-teknik pasif seperti "Search Open Technical Databases" yang tidak menyentuh target sama sekali.'
    source: https://attack.mitre.org/tactics/TA0043/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: leak
    prompt: |
      Audit cepat menemukan web server kamu menyajikan `http://situs/.env` (berisi `DB_PASSWORD`), `http://situs/.git/`,
      `http://situs/backup.sql`, header `Server: nginx/1.31.10`, dan `robots.txt` yang mencantumkan `/admin`. Susun
      langkah remediasi beserta urutan prioritasnya (satu poin per baris).
    checklist:
      - id: rotate
        label: Prioritas 1 — anggap kredensial di .env sudah bocor, rotasi segera
        required: true
        patterns: ['(rotasi|ganti|rotate|ubah)[^\n]*(kredensial|password|secret|db)', '(kredensial|password|secret)[^\n]*(bocor|dianggap bocor|dirotasi)']
      - id: block-dot
        label: Blokir dotfiles & .git (location ~ /\. deny / return 404)
        required: true
        patterns: ['location ~ /\\?\.', 'blokir[^\n]*(dotfile|\.env|\.git)', 'deny[^\n]*(\.|dot)', 'return 404[^\n]*(\.|dot)']
      - id: block-backup
        label: Blokir berkas backup (.sql/.bak/.zip) & keluarkan dari web root
        patterns: ['\.(sql|bak|zip|gz|tar)', 'backup[^\n]*(blokir|hapus|keluarkan|pindah)', '(hapus|pindah)[^\n]*backup']
      - id: tokens
        label: server_tokens off + hilangkan X-Powered-By (sembunyikan versi)
        patterns: ['server_tokens', 'x-powered-by', '(sembunyikan|matikan)[^\n]*(versi|banner)']
      - id: patch
        label: Patch/upgrade nginx ke versi yang didukung (pertahanan sejati)
        patterns: ['patch|upgrade|update|perbarui', '(nginx|versi)[^\n]*(baru|terbaru|didukung|ter-?patch)']
      - id: robots
        label: Lindungi /admin dengan auth; jangan andalkan robots.txt
        patterns: ['(auth|login|autentikasi|password)[^\n]*(admin)?', '(robots\.txt)[^\n]*(bukan|jangan|hapus|tidak)', 'admin[^\n]*(auth|dilindungi)']
      - id: verify
        label: Verifikasi ulang dari luar (semua kini 404) + scan berkala
        patterns: ['(verifikasi|cek ulang|uji ulang|scan ulang)[^\n]*(luar|404|curl|nmap)', 'curl[^\n]*404', 'scan (berkala|rutin)']
      - id: no-robots-fix
        label: Tidak menganggap menambah robots.txt sebagai solusi
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain))[^\n]*(tambah(kan)?|pakai|andalkan)[^\n]*robots\.txt[^\n]*(aman|lindung|solusi|cukup)']
    pass_score: 0.5
    solution: |
      ```text
      P1 (segera): Kredensial di .env HARUS dianggap sudah bocor → rotasi DB_PASSWORD, APP_KEY, token lain SEKARANG.
                   Menutup aksesnya saja tidak cukup; siapa pun mungkin sudah menyalinnya.
      P2: nginx — blokir sumber kebocoran, kembalikan 404 (bukan 403):
            location ~ /\.            { return 404; }   # .env, .git, .htaccess, .ssh
            location ~* \.(sql|bak|zip|gz|tar)$ { return 404; }
          dan keluarkan backup.sql serta folder .git dari web root sama sekali.
      P3: server_tokens off;  + buang header X-Powered-By (kurangi fingerprint).
      P4: Upgrade nginx ke versi yang didukung & ter-patch — ini pertahanan sejati terhadap CVE, bukan sekadar
          menyembunyikan versi.
      P5: Lindungi /admin dengan autentikasi (+ IP allowlist bila mungkin). Hapus path sensitif dari robots.txt;
          robots.txt bukan kontrol akses dan malah jadi peta.
      P6: Verifikasi dari luar: curl .env/.git/backup.sql → 404, Server → "nginx". Jadwalkan scan berkala.
      ```
      Urutan ini mencerminkan prinsip modul: dampak kredensial bocor paling besar (lewati semua pertahanan lain), jadi
      rotasi lebih dulu daripada menutup akses. `return 404` menyembunyikan keberadaan berkas. Dan menyembunyikan versi
      tidak menggantikan patch. Menambahkan `robots.txt` bukan perbaikan — di lab ia justru membocorkan `/admin`.
    tests:
      pass:
        - |
          prioritas 1 rotasi DB_PASSWORD dan APP_KEY karena kredensial di .env dianggap sudah bocor
          blokir dotfiles dan .git dengan location ~ /\. return 404
          blokir backup .sql .bak .zip dan keluarkan dari web root
          server_tokens off dan buang X-Powered-By
          upgrade nginx ke versi ter-patch sebagai pertahanan sejati
          lindungi /admin dengan autentikasi, jangan andalkan robots.txt
          verifikasi dari luar semua jadi 404 lalu scan berkala
        - |
          rotasi kredensial dulu karena sudah dianggap bocor
          return 404 untuk .env .git backup.sql
          sembunyikan versi server_tokens off tapi yang utama patch nginx
          admin pakai login bukan robots.txt
          cek ulang dari luar
      fail:
        - |
          tambahkan /.env dan /.git ke robots.txt supaya aman
          selesai
        - |
          blokir .env di nginx
          tidak perlu ganti password karena sudah ditutup
        - |
          server_tokens off saja sudah cukup
          versi nginx tidak usah diupdate
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: c02
    tier: C
    type: scenario
    objective: reduce
    prompt: |
      Scan dari luar terhadap server production kamu (`nmap -Pn -p- IP`) menemukan port terbuka: 22 (SSH), 80 (HTTP),
      443 (HTTPS), 5432 (Postgres), 9090 (metrics), 8025 (mailhog dev), 6379 (Redis). Server hanya perlu melayani web
      publik. Tentukan mana yang harus terekspos dan rancang pengurangan attack surface-nya (satu poin per baris).
    checklist:
      - id: keep
        label: Hanya 80/443 (dan 22 terbatas) yang boleh publik
        required: true
        patterns: ['(80|443|http)[^\n]*(publik|tetap|boleh|ekspos)', 'hanya[^\n]*(80|443|web)', '443[^\n]*publik', 'publik[^\n]*(80|443)']
      - id: close-data
        label: Postgres 5432 & Redis 6379 jangan terekspos publik (hanya internal/localhost)
        required: true
        patterns: ['(5432|postgres|6379|redis)[^\n]*(tutup|internal|localhost|jangan (publik|ekspos)|bind)', '(tutup|internal|localhost)[^\n]*(5432|6379|postgres|redis|database)']
      - id: remove-dev
        label: Hapus service dev (mailhog 8025) dari production
        patterns: ['(8025|mailhog)[^\n]*(hapus|matikan|cabut|jangan|tidak (boleh|ada))', '(hapus|matikan)[^\n]*(dev|mailhog|8025)']
      - id: metrics
        label: Metrics 9090 jangan publik (auth / IP allowlist / internal)
        patterns: ['(9090|metrics|prometheus)[^\n]*(internal|auth|allowlist|vpn|tutup|jangan publik)', '(internal|auth|allowlist)[^\n]*(9090|metrics)']
      - id: ssh
        label: SSH 22 dibatasi (key-only, allowlist IP / VPN / bastion)
        patterns: ['(22|ssh)[^\n]*(batasi|allowlist|vpn|bastion|key|kunci|terbatas)', '(allowlist|vpn|bastion|key-only)[^\n]*ssh']
      - id: default-deny
        label: Firewall default-deny, buka hanya yang perlu (+ bind ke interface tepat)
        required: true
        patterns: ['default[- ]?deny', '(firewall|iptables|ufw|nft)[^\n]*(deny|tolak|tutup semua)', 'bind[^\n]*(localhost|127\.0\.0\.1|internal)']
      - id: verify
        label: Scan ulang dari luar untuk konfirmasi hanya 80/443 (+22) terbuka
        patterns: ['(scan ulang|nmap|verifikasi|cek)[^\n]*(luar|80|443)', 'nmap[^\n]*-p-']
      - id: no-obscure
        label: Tidak sekadar memindahkan service ke port tak lazim sebagai "pengaman"
        forbid: true
        patterns: ['^(?![^\n]*(bukan|jangan|tidak cukup|selain))[^\n]*(pindah(kan)?|ganti|ubah)[^\n]*port[^\n]*(aman|sembunyi|cukup|biar tidak ketahuan)']
    pass_score: 0.5
    solution: |
      ```text
      Publik (perlu):   80, 443  → layanan web. 22 (SSH) dibatasi: key-only + allowlist IP / lewat VPN / bastion.
      TIDAK boleh publik:
        5432 Postgres  → bind ke localhost/jaringan internal; akses via SSH tunnel/VPN (board Jaringan modul 09).
        6379 Redis     → sama; Redis tanpa auth yang terekspos = pintu masuk langsung. bind 127.0.0.1.
        9090 metrics   → internal only / di balik auth / IP allowlist; metrics bocor = info recon.
        8025 mailhog   → service DEV, HAPUS dari production (seharusnya tidak pernah ada di sana).
      Cara:
        - Firewall default-deny di edge: INPUT drop, allow 80/443 dari mana saja, 22 dari allowlist saja.
        - Bind service data ke 127.0.0.1 / interface internal, bukan 0.0.0.0 (akar masalah: service listen di semua
          interface). Di Docker, jangan publish port DB/Redis ke host.
        - Verifikasi: nmap -Pn -p- dari luar → hanya 80/443 (+22) yang terlihat; sisanya filtered.
      ```
      Prinsipnya attack surface reduction: tiap port terbuka adalah pintu. Postgres/Redis/metrics/mailhog tidak punya
      alasan untuk publik — banyak breach berawal dari Redis/Postgres tanpa auth yang terekspos ke internet.
      Memindahkan service ke port aneh (obscurity) bukan solusi: `-p-` tetap menemukannya. Yang menutup pintu adalah
      firewall default-deny + bind ke interface yang benar.
    tests:
      pass:
        - |
          hanya 80 dan 443 yang publik untuk web, SSH 22 dibatasi key-only dan allowlist IP atau lewat bastion
          Postgres 5432 dan Redis 6379 jangan terekspos publik, bind ke localhost atau jaringan internal
          hapus mailhog 8025 karena itu service dev tidak boleh ada di production
          metrics 9090 internal only atau di balik auth dan IP allowlist
          firewall default-deny buka hanya yang perlu, bind service data ke 127.0.0.1 bukan 0.0.0.0
          scan ulang dari luar pastikan hanya 80 443 dan 22 terbuka
        - |
          publik cukup 80 443, ssh 22 lewat vpn saja
          tutup postgres redis metrics dari publik, bind internal
          cabut mailhog dari production
          default-deny firewall lalu nmap -p- ulang dari luar untuk verifikasi
      fail:
        - |
          pindahkan semua service ke port tak lazim biar penyerang tidak menemukannya
          selesai
        - |
          biarkan semua port terbuka tapi pasang password kuat
          yang penting ada autentikasi
        - |
          buka 80 dan 443
          port lain tidak masalah terbuka
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: c03
    tier: C
    type: scenario
    objective: inventory
    prompt: |
      Perusahaanmu beberapa kali kena insiden lewat aset yang "tidak ada yang tahu masih hidup" (subdomain staging lama,
      server uji dengan port debug terbuka). Rancang program pengurangan exposure berkelanjutan dari sisi blue, dengan
      meminjam cara kerja penyerang (satu poin per baris).
    checklist:
      - id: inventory
        label: Bangun & jaga asset inventory (host, domain, subdomain, port, service)
        required: true
        patterns: ['(asset )?inventor(y|i)', 'daftar aset', '(catat|data)[^\n]*(host|aset|domain|subdomain)']
      - id: external-scan
        label: Scan rutin dari luar seperti penyerang (nmap, enumerasi subdomain/CT logs)
        required: true
        patterns: ['scan[^\n]*(rutin|berkala|terjadwal)[^\n]*(luar|eksternal)', 'scan[^\n]*(eksternal|luar)', '(nmap|enumerasi)[^\n]*(rutin|berkala|subdomain)', 'certificate transparency|ct logs', 'serang diri sendiri']
      - id: diff
        label: Bandingkan hasil scan dengan inventory → temukan aset tak terduga
        patterns: ['(banding|diff|cocok)[^\n]*(inventory|inventori|scan)', 'aset (tak terduga|tidak dikenal|baru)', '(selisih|gap)[^\n]*inventory']
      - id: decommission
        label: Matikan/pensiunkan aset yang tak terpakai (shadow IT)
        required: true
        patterns: ['(matikan|pensiun|hapus|decommission|cabut|nonaktif)[^\n]*(aset|server|subdomain|staging|dev|debug)', 'shadow it']
      - id: reduce
        label: Aset yang tetap ada — tutup port debug, default-deny, hilangkan banner
        patterns: ['(tutup|default-deny|batasi)[^\n]*(port|debug|service)', 'kurangi (attack surface|exposure)', 'server_tokens|banner']
      - id: ownership
        label: Setiap aset punya pemilik & lifecycle (siapa bertanggung jawab + kapan dimatikan)
        patterns: ['(pemilik|owner|penanggung jawab|lifecycle|masa pakai)', '(siapa )?bertanggung jawab', 'expiry|kedaluwarsa']
      - id: automate
        label: Otomatkan & jadwalkan (bukan sekali lalu lupa)
        patterns: ['(otomat|automat|jadwal|terjadwal|berkala|cron|pipeline)', 'rutin|berkelanjutan|continuous']
      - id: no-once
        label: Tidak menganggap audit sekali jalan sudah cukup
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak cukup|selain|berkala|rutin))[^\n]*(audit|scan|cek)[^\n]*(sekali|satu kali|cukup sekali)']
    pass_score: 0.5
    solution: |
      ```text
      1. Asset inventory sebagai sumber kebenaran: semua host, domain, subdomain, port, service, + pemiliknya.
      2. Scan eksternal rutin & terjadwal, meniru penyerang:
           - nmap -Pn -p- ke seluruh rentang IP milikmu,
           - enumerasi subdomain (Certificate Transparency logs, brute DNS),
           - "serang dirimu sendiri" secara berkala.
      3. Diff hasil scan vs inventory → apa pun yang muncul di scan tapi tidak di inventory = aset tak terduga,
         selidiki segera (inilah staging lama / port debug itu).
      4. Pensiunkan (decommission) aset yang tak terpakai — mematikannya menghapus seluruh risikonya.
      5. Aset yang tetap ada: tutup port debug, default-deny, hilangkan banner, pastikan ter-patch.
      6. Setiap aset punya pemilik & lifecycle: siapa bertanggung jawab, dan kapan dijadwalkan dimatikan
         (staging/dev harus punya tanggal kedaluwarsa).
      7. Otomatkan semua (pipeline/cron + alert saat ada aset baru tak dikenal) — exposure berubah terus, jadi
         audit sekali jalan tidak cukup.
      ```
      Inti: penyerang menemukan shadow IT karena mereka memetakan **semua**. Blue team menang dengan melakukan hal yang
      sama lebih dulu, terus-menerus, lalu membandingkannya dengan inventory. Mematikan aset tak terpakai adalah
      pengurangan attack surface paling efektif — risiko nol untuk sesuatu yang sudah tidak ada. Ini Reconnaissance
      yang dibalik menjadi pertahanan.
    tests:
      pass:
        - |
          bangun dan jaga asset inventory berisi semua host domain subdomain port service dan pemiliknya
          scan rutin terjadwal dari luar meniru penyerang pakai nmap -p- dan enumerasi subdomain dari certificate transparency
          bandingkan hasil scan dengan inventory untuk menemukan aset tak terduga
          pensiunkan aset tak terpakai seperti staging lama dan port debug
          aset yang tetap ada tutup port debug default-deny dan hilangkan banner
          tiap aset punya pemilik dan lifecycle dengan tanggal kedaluwarsa
          otomatkan dan jadwalkan supaya berkelanjutan bukan sekali lalu lupa
        - |
          jaga inventory aset lengkap dengan pemilik
          scan eksternal berkala seperti penyerang lalu diff dengan inventory
          matikan shadow it yang tak terpakai
          sisanya kurangi exposure dan patch
          otomatkan terjadwal bukan audit sekali jalan
      fail:
        - |
          lakukan audit sekali untuk mendata semua server
          setelah itu dianggap aman
        - |
          pasang firewall di semua server
          tidak perlu inventory atau scan ulang
        - |
          scan sekali, catat hasilnya
          selesai
    source: https://owasp.org/www-project-web-security-testing-guide/
---

# Recon & Exposure

Sebelum menyerang, penyerang memetakan target: port apa terbuka, software apa dan versi berapa, berkas apa yang
tak sengaja ter-publish, path apa yang ada. Makin banyak yang bisa mereka temukan, makin mudah langkah berikutnya.
Sisi blue dari tahap ini adalah **attack surface reduction**: menampilkan sesedikit mungkin, dan tahu persis apa yang
kamu ekspos.

> [!NOTE]
> Lab: target `nginx:alpine` (nginx/1.31.10) dengan kebocoran yang sengaja dipasang — `.env`, folder `.git`,
> `backup.sql`, `/admin`, `robots.txt`. Attacker `alpine` dengan `nmap` + `curl`. Semua di network lab lokal, hanya
> milik sendiri.

## 1. Aktif vs pasif

- **Pasif** — tanpa menyentuh target: OSINT, log Certificate Transparency (subdomain), GitHub, DNS publik. Nyaris
  tak terdeteksi.
- **Aktif** — mengirim paket ke target: `nmap`, `curl`, brute path. Cepat & lengkap, tapi terekam di log.

Blue team tidak bisa mendeteksi recon pasif, jadi kuncinya **mengurangi apa yang bisa ditemukan**.

## 2. Apa yang bocor (hasil lab)

| Recon | Perintah | Hasil |
|---|---|---|
| Banner versi | `curl -I` / `nmap -sV -p 80` | `Server: nginx/1.31.10`, `nginx 1.31.10` |
| Secret ter-publish | `curl http://tgt/.env` | `DB_PASSWORD=supersecret` (HTTP 200) |
| Source code | `curl http://tgt/.git/config` | URL repo privat; `.git` penuh = seluruh source + riwayat |
| Peta dari pemilik | `curl http://tgt/robots.txt` | `Disallow: /admin`, `/backup.sql` |
| Enumerasi path | loop `curl -w %{http_code}` | `admin→301 uploads→301 backup.sql→200 nope→404` |

Status code sendiri membocorkan: `200`/`301`/`403` = ada, `404` = tidak ada.

## 3. Menutupnya (hasil lab)

```nginx
server {
    server_tokens off;                              # Server: nginx (tanpa versi)
    location ~ /\.                     { return 404; }   # .env, .git, .htaccess
    location ~* \.(sql|bak|zip|gz|tar)$ { return 404; }  # dump backup
    location /admin                    { return 404; }   # tak publik (pakai auth)
}
```

| | Sebelum | Sesudah |
|---|---|---|
| `Server:` | `nginx/1.31.10` | `nginx` |
| `/.env`, `/.git/config`, `/backup.sql`, `/admin/` | `200` | `404` |
| `/index.html` | `200` | `200` |

> [!TIP]
> Kembalikan **`404` bukan `403`** untuk berkas sensitif. `403` berarti "ada tapi dilarang" — mengonfirmasi
> keberadaannya. `404` menyembunyikannya sekalian.

## 4. Prinsip pertahanan

1. **Kurangi attack surface** — tutup port tak terpakai (default-deny), hapus berkas/endpoint yang tak perlu,
   minimalkan paket & fitur. Kode yang tidak ada tidak bisa dieksploitasi.
2. **Jangan bocorkan informasi** — sembunyikan banner & header versi, matikan stack trace, seragamkan respons.
   Ini memperlambat recon, tapi **bukan pengganti patch** (pertahanan sejati terhadap CVE).
3. **Jangan andalkan obscurity** — `robots.txt`, port tak lazim, nama sulit ditebak bukan kontrol akses. `nmap -p-`
   dan enumerasi tetap menemukannya. Lindungi dengan autentikasi & firewall.
4. **Kenali exposure-mu** — jaga asset inventory, scan dirimu sendiri dari luar secara rutin, dan matikan shadow IT.
   Penyerang memetakan semua; menanglah dengan memetakannya lebih dulu.

> [!WARNING]
> Secret yang pernah ter-ekspos (di `.env` publik, di riwayat `.git`) harus dianggap **bocor permanen** dan
> dirotasi — menutup aksesnya saja tidak cukup. Lanjut ke modul 04 (Secrets & kredensial).
