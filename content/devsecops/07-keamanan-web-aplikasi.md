---
format: 1
id: sec-web-app-security
course:
  slug: devsecops
  title: DevSecOps
  description: Keamanan untuk DevOps dari dua sisi — cara kerja red team dan cara blue team mendeteksi serta menutupnya. Model ancaman, hardening server & container, keamanan web, ketersediaan (anti-DDoS), deteksi, respons insiden, dan keamanan pipeline. Semua praktik di lab terisolasi milik sendiri.
title: Keamanan Web Aplikasi (OWASP)
summary: "Lapisan aplikasi adalah tempat penyerang paling sering mendapat pijakan pertama. Sisi red: injection (SQLi), broken access control (IDOR), XSS, dan SSRF — semua berakar pada input tak tepercaya yang dipercaya. Sisi blue: prepared statement, otorisasi server-side deny-by-default, output encoding + CSP, allowlist SSRF, security headers, dan WAF (ModSecurity + OWASP CRS) sebagai lapisan tambahan. Setiap kelas dibuktikan rentan lalu ditutup di lab."
order: 7
version: 1
status: verified
updated: '2026-11-25'
tested_on:
  - Docker Desktop (Windows), Engine 29.4.1 — app PHP 8.3-cli rentan + Postgres 16 di jaringan terisolasi dj-sec07
  - WAF owasp/modsecurity-crs:nginx (libmodsecurity 3.0.17, OWASP CRS, 855 rule) di depan app yang sama
  - Toggle HARDEN=0/1 pada app yang identik untuk membandingkan versi rentan vs aman; semua target milik sendiri
objectives:
  - id: injection
    text: Mencegah injection (SQLi) dengan query ter-parameter
  - id: access
    text: Menegakkan access control (otorisasi) server-side, deny-by-default
  - id: xss
    text: Menutup XSS dengan output encoding, CSP, dan flag cookie
  - id: ssrf
    text: Mencegah SSRF dengan allowlist dan memblokir jaringan internal
  - id: headers
    text: Memasang security headers yang benar
  - id: waf
    text: Memakai WAF (ModSecurity/CRS) sebagai lapisan, bukan pengganti perbaikan kode
sources:
  - title: OWASP Top 10 (2021)
    url: https://owasp.org/www-project-top-ten/
  - title: OWASP — SQL Injection Prevention Cheat Sheet
    url: https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html
  - title: OWASP — Cross Site Scripting Prevention Cheat Sheet
    url: https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html
  - title: OWASP — Server Side Request Forgery Prevention Cheat Sheet
    url: https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html
  - title: OWASP — Authorization Cheat Sheet
    url: https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html
  - title: MDN — Content-Security-Policy
    url: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy
  - title: OWASP ModSecurity Core Rule Set (CRS)
    url: https://coreruleset.org/
  - title: MITRE ATT&CK — Exploit Public-Facing Application (T1190)
    url: https://attack.mitre.org/techniques/T1190/
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: injection
    prompt: Kerentanan di mana input penyerang mengubah makna sebuah query/perintah (mis. SQL) disebut kelas apa?
    answers: ['injection']
    patterns: ['injection', 'injeksi', 'sql ?injection', 'sqli']
    tests:
      fail: ['xss', 'broken access control', 'ssrf', 'clickjacking']
    explanation: 'Injection (OWASP A03) terjadi ketika data tak tepercaya digabung mentah ke dalam sebuah interpreter — SQL, perintah OS, LDAP, NoSQL — sehingga input dibaca sebagai *kode*, bukan *data*. Di lab, input `'' OR ''1''=''1` pada query `... WHERE name=''$u''` membuat login mengembalikan **3 baris** (semua user), dan `'' UNION SELECT ...` menarik kolom `ssn`. Akar masalahnya satu: pencampuran kode dan data. Lihat ATT&CK T1190 (Exploit Public-Facing Application) — jalur pijakan pertama paling umum.'
    source: https://owasp.org/www-project-top-ten/

  - id: a02
    tier: A
    type: text
    objective: injection
    prompt: Pertahanan utama terhadap SQL injection — mengirim query dan data secara terpisah ke database — disebut apa?
    answers: ['prepared statement']
    patterns: ['prepared statement', 'parameter(ized|isasi)? quer', 'query ter-?parameter', 'parameter(ized)? statement', 'bind(ing)? parameter']
    tests:
      fail: ['escaping manual', 'blacklist tanda kutip', 'validasi panjang input', 'enkripsi database']
    explanation: 'Prepared statement (query ter-parameter) mengirim struktur query lebih dulu dengan placeholder (`WHERE name = :n`), lalu nilai dikirim terpisah. Database tidak pernah mem-parse nilai sebagai kode SQL, jadi `'' OR ''1''=''1` hanya dicari sebagai nama literal. Di lab, versi `HARDEN=1` memakai `$pdo->prepare(...)->execute([...])` dan payload yang tadinya mengembalikan 3 baris jadi **0 baris**. Jauh lebih andal daripada escaping manual atau blacklist, yang selalu bisa di-bypass.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html

  - id: a03
    tier: A
    type: text
    objective: injection
    prompt: Secara prinsip, injection muncul karena aplikasi mencampur dua hal yang seharusnya dipisah — apa dua hal itu?
    answers: ['kode dan data']
    patterns: ['kode[^\n]*data', 'data[^\n]*kode', 'code[^\n]*data', 'perintah[^\n]*(input|data)', 'query[^\n]*(input|data)']
    tests:
      fail: ['user dan admin', 'frontend dan backend', 'request dan response', 'enkripsi dan hashing']
    explanation: 'Semua injection berakar pada pencampuran **kode** (struktur query/perintah) dengan **data** (input user). Begitu input bisa mengubah struktur, penyerang mengontrol interpreter. Solusinya selalu sama bentuknya: pisahkan keduanya — prepared statement untuk SQL, API berargumen (bukan shell string) untuk perintah OS, template yang auto-escape untuk HTML. Begitu kamu melihat string digabung lalu dikirim ke sebuah interpreter, curigai injection.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html

  - id: a04
    tier: A
    type: text
    objective: access
    prompt: Mengakses data milik user lain dengan mengganti `id=1` menjadi `id=2` di URL (tanpa cek hak) disebut apa?
    answers: ['idor']
    patterns: ['idor', 'insecure direct object reference', 'broken access control', 'broken object level', 'bola']
    tests:
      fail: ['sql injection', 'xss', 'ssrf', 'brute force']
    explanation: 'IDOR (Insecure Direct Object Reference) adalah bentuk **Broken Access Control** (OWASP A01, kategori #1): endpoint menerima identitas objek dari user tapi tidak memeriksa apakah user *berhak* atas objek itu. Di lab, `/?r=profile&id=3` mengembalikan email + `ssn` milik admin padahal yang login adalah `alice` (id=1). Tidak butuh exploit canggih — cukup ganti angka. Inilah sebabnya access control harus dicek di server untuk **setiap** objek, bukan diasumsikan dari UI.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html

  - id: a05
    tier: A
    type: text
    objective: access
    prompt: Keputusan "apakah user ini boleh mengakses resource itu" (otorisasi) harus ditegakkan di sisi mana?
    answers: ['server']
    patterns: ['\bserver(-| )?side\b', 'di (sisi )?server', 'backend', 'sisi server']
    tests:
      fail: ['client', 'browser', 'frontend', 'di javascript']
    explanation: 'Otorisasi WAJIB ditegakkan **server-side**. Apa pun yang berada di client — tombol disembunyikan, field `role=user` tersembunyi, pengecekan JavaScript — bisa diubah penyerang dengan DevTools atau `curl`. Di lab, blokir IDOR dilakukan di server (`if ($id !== $me) 403`), bukan dengan menyembunyikan link. Aturan: client boleh menyembunyikan UI demi kenyamanan, tapi server adalah satu-satunya tempat keputusan keamanan dibuat.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html

  - id: a06
    tier: A
    type: text
    objective: xss
    prompt: Menyuntikkan script yang dieksekusi di browser korban karena output tidak di-escape disebut serangan apa?
    answers: ['xss']
    patterns: ['xss', 'cross[- ]?site scripting', 'cross site scripting']
    tests:
      fail: ['csrf', 'sql injection', 'ssrf', 'session fixation']
    explanation: 'XSS (Cross-Site Scripting) menyuntikkan HTML/JS yang berjalan di browser korban dengan hak sesi korban — bisa mencuri cookie, mengubah halaman, atau bertindak atas nama user. Di lab, `/?r=search&q=<script>alert(1)</script>` memantulkan tag `<script>` **mentah** ke HTML (reflected XSS). Akarnya sama dengan injection: data user dibaca sebagai kode (kali ini kode HTML/JS di browser), bukan sebagai teks.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html

  - id: a07
    tier: A
    type: text
    objective: xss
    prompt: Pertahanan utama XSS — mengubah karakter berbahaya seperti `<` `>` jadi entitas saat menampilkan data — disebut apa?
    answers: ['output encoding']
    patterns: ['output encoding', 'encoding output', 'escaping output', 'html ?escap(e|ing)', 'htmlspecialchars|htmlentities', 'escape (output|html)']
    tests:
      fail: ['input sanitization saja', 'minify javascript', 'enkripsi tls', 'blacklist kata script']
    explanation: 'Output encoding (contextual escaping) mengubah `<` menjadi `&lt;`, `>` menjadi `&gt;`, dst. saat data ditampilkan, sesuai konteks (HTML body, atribut, JS, URL). Di lab, versi `HARDEN=1` memakai `htmlspecialchars()` dan `<script>` berubah jadi `&lt;script&gt;` — tampil sebagai teks, bukan tereksekusi. Kuncinya: encode saat **output** sesuai konteksnya; framework modern (Blade, React) auto-escape secara default — jangan matikan itu (`{!! !!}`, `dangerouslySetInnerHTML`) tanpa alasan kuat.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html

  - id: a08
    tier: A
    type: text
    objective: xss
    prompt: Response header yang membatasi dari sumber mana script boleh dimuat/dijalankan (lapisan tambahan anti-XSS) adalah?
    answers: ['content-security-policy']
    patterns: ['content[- ]security[- ]policy', '\bcsp\b']
    tests:
      fail: ['x-frame-options', 'strict-transport-security', 'x-xss-protection', 'referrer-policy']
    explanation: 'Content-Security-Policy (CSP) memberi tahu browser sumber mana yang sah untuk script, style, dll. — mis. `default-src ''self''` menolak script inline dan dari domain lain. Di lab, versi `HARDEN=1` mengirim `Content-Security-Policy: default-src ''self''`. CSP adalah **defense-in-depth**: kalau satu XSS lolos encoding, CSP yang baik bisa mencegah script jahat benar-benar berjalan. Catatan: CSP bukan pengganti output encoding — ia lapisan kedua, bukan pertama.'
    source: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy

  - id: a09
    tier: A
    type: text
    objective: ssrf
    prompt: Memaksa server mengambil (fetch) URL yang dikendalikan penyerang untuk menjangkau sistem internal disebut apa?
    answers: ['ssrf']
    patterns: ['ssrf', 'server[- ]side request forgery']
    tests:
      fail: ['csrf', 'xss', 'open redirect', 'dns spoofing']
    explanation: 'SSRF (Server-Side Request Forgery, OWASP A10) membuat server melakukan request ke tujuan pilihan penyerang. Karena request berasal dari server, ia menembus firewall dan menjangkau layanan internal yang tak terekspos ke internet. Di lab, `/?r=fetch&url=http://dj-sec07-internal/latest.txt` membuat app mengambil dan membocorkan token dari layanan internal yang seharusnya tidak bisa diakses user. Target klasik: endpoint metadata cloud.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html

  - id: a10
    tier: A
    type: text
    objective: ssrf
    prompt: Alamat IP endpoint metadata cloud (AWS/GCP/Azure) yang jadi target favorit SSRF untuk mencuri kredensial adalah?
    answers: ['169.254.169.254']
    patterns: ['169\.254\.169\.254']
    tests:
      fail: ['127.0.0.1', '10.0.0.1', '192.168.1.1', '8.8.8.8']
    explanation: 'Endpoint metadata `169.254.169.254` (link-local) memberi instance detail konfigurasi dan — berbahaya — **kredensial IAM sementara**. SSRF yang menjangkaunya bisa mencuri kredensial cloud lalu bergerak lateral. Pertahanan: allowlist host tujuan, blokir rentang internal/link-local (`169.254.0.0/16`, `127.0.0.0/8`, RFC1918), dan pakai IMDSv2 (butuh token, menyulitkan SSRF polos). Di lab kita pakai layanan internal sebagai pengganti metadata untuk membuktikan jalurnya.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html

  - id: a11
    tier: A
    type: command
    objective: headers
    prompt: Lihat **hanya response header** dari `https://app.lab` dengan curl (tanpa body).
    answers: ['curl -I https://app.lab']
    patterns: ['^(?=.*[ \t](-[A-Za-z]*I[A-Za-z]*|--head)([ \t]|$))curl[ \t].*app\.lab']
    tests:
      pass:
        - 'curl -I https://app.lab'
        - 'curl --head https://app.lab'
        - 'curl -sI https://app.lab'
      fail:
        - 'curl https://app.lab'
        - 'curl -X POST https://app.lab'
        - 'wget https://app.lab'
    explanation: '`curl -I` (atau `--head`) mengirim request `HEAD` dan menampilkan hanya status + header — cara cepat mengaudit security headers. Di lab, versi rentan membocorkan `X-Powered-By: PHP/8.3.37` dan **tidak** punya CSP/HSTS/X-Frame-Options/X-Content-Type-Options; versi hardened menambah keempat header itu dan menghapus `X-Powered-By`. Pakai ini rutin untuk mengecek header di staging sebelum rilis.'
    source: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers

  - id: a12
    tier: A
    type: text
    objective: headers
    prompt: Header yang mencegah browser "menebak" tipe konten (MIME sniffing) adalah `X-Content-Type-Options` dengan nilai apa?
    answers: ['nosniff']
    patterns: ['nosniff']
    tests:
      fail: ['deny', 'same-origin', 'max-age', 'strict']
    explanation: '`X-Content-Type-Options: nosniff` memaksa browser menghormati `Content-Type` yang dikirim server dan tidak menebak-nebak. MIME sniffing bisa membuat file yang diunggah sebagai "gambar" dieksekusi sebagai HTML/JS (jalur XSS). Nilainya hanya satu: `nosniff`. Di lab, versi hardened mengirimnya; ini header murah yang menutup kelas bug sniffing.'
    source: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-Content-Type-Options

  - id: a13
    tier: A
    type: text
    objective: headers
    prompt: Serangan membungkus situsmu dalam `<iframe>` tak terlihat agar korban salah-klik disebut apa (dicegah X-Frame-Options/CSP frame-ancestors)?
    answers: ['clickjacking']
    patterns: ['clickjack', 'click[- ]?jacking', 'ui redress']
    tests:
      fail: ['xss', 'csrf', 'phishing email', 'session hijack']
    explanation: 'Clickjacking (UI redressing) menaruh halamanmu di `<iframe>` transparan di atas halaman jebakan, sehingga klik korban sebenarnya mengenai tombol di situsmu (mis. "Transfer"). Pertahanan: `X-Frame-Options: DENY` (atau `SAMEORIGIN`), atau yang lebih modern `Content-Security-Policy: frame-ancestors ''none''`. Di lab, versi hardened mengirim `X-Frame-Options: DENY`.'
    source: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-Frame-Options

  - id: a14
    tier: A
    type: text
    objective: waf
    prompt: Lapisan di depan aplikasi yang memeriksa lalu lintas HTTP dan memblokir pola serangan (SQLi, XSS, dll.) disebut apa?
    answers: ['waf']
    patterns: ['\bwaf\b', 'web application firewall']
    tests:
      fail: ['load balancer', 'reverse proxy biasa', 'ids jaringan', 'antivirus']
    explanation: 'WAF (Web Application Firewall) duduk di depan aplikasi dan menyaring request HTTP berdasarkan aturan, memblokir payload mencurigakan sebelum mencapai app. Di lab, ModSecurity + OWASP CRS di depan app yang **masih rentan** memblokir SQLi/XSS/traversal dengan **403**, sementara request sah tetap **200**. Penting: WAF adalah lapisan tambahan (defense-in-depth), bukan pengganti perbaikan kode — ia bisa di-bypass.'
    source: https://coreruleset.org/

  - id: a15
    tier: A
    type: text
    objective: waf
    prompt: Kumpulan aturan open-source standar untuk WAF ModSecurity (mendeteksi SQLi, XSS, dll.) bernama apa?
    answers: ['owasp crs']
    patterns: ['owasp crs', 'core rule set', '\bcrs\b', 'coreruleset']
    tests:
      fail: ['owasp top 10', 'owasp zap', 'mod_security rules', 'snort rules']
    explanation: 'OWASP CRS (Core Rule Set) adalah paket aturan generik untuk ModSecurity yang mendeteksi serangan web umum. Di lab, image `owasp/modsecurity-crs:nginx` memuat **855 aturan** (libmodsecurity 3.0.17). CRS memakai **anomaly scoring**: tiap aturan yang cocok menambah skor; bila total melewati ambang (default 5), aturan `949110` memblokir request. Lab menunjukkan skor 5/10/15/20 pada payload berbeda — satu aturan CRITICAL (5 poin) saja sudah cukup memicu blokir.'
    source: https://coreruleset.org/

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: injection
    prompt: Selain SQL, sebutkan satu jenis injection lain yang muncul dari pencampuran kode-data yang sama.
    answers: ['os command injection']
    patterns: ['(os )?command injection', 'injeksi perintah', 'ldap injection', 'nosql injection', 'xpath injection', 'template injection|ssti']
    tests:
      fail: ['brute force', 'phishing', 'ddos', 'man in the middle']
    explanation: 'Injection bukan hanya SQL: **OS command injection** (input masuk ke `system()`/shell), **LDAP**, **NoSQL** (mis. operator `$ne` di Mongo), **XPath**, dan **template injection (SSTI)**. Semuanya pola yang sama — input tak tepercaya diinterpretasikan sebagai kode. Pertahanannya juga sepola: pakai API berargumen (bukan shell string), driver yang memisah query & data, dan jangan pernah bangun perintah dari string mentah.'
    source: https://owasp.org/www-project-top-ten/

  - id: b02
    tier: B
    type: text
    objective: injection
    prompt: Prinsip least privilege di database — agar SQLi yang lolos tetap terbatas dampaknya — berarti aplikasi login DB sebagai user yang bagaimana?
    answers: ['user dengan hak minimal']
    patterns: ['(hak|privilege)[^\n]*(minimal|terbatas|secukupnya|seperlunya)', 'least privilege', '(bukan|tanpa)[^\n]*(superuser|admin|root|owner)', 'non-?superuser', 'tidak (pakai )?(superuser|postgres|root)']
    tests:
      fail: ['user superuser agar fleksibel', 'root database', 'user dengan akses semua schema', 'satu user untuk semua app']
    explanation: 'Even dengan prepared statement, berikan DB user aplikasi hanya hak yang dibutuhkan (SELECT/INSERT/UPDATE pada tabel tertentu) — **bukan** superuser/owner. Kalau suatu saat injection lolos (atau ada bug lain), user ber-hak minimal membatasi kerusakan: tidak bisa `DROP TABLE`, baca tabel lain, atau `COPY ... TO PROGRAM` (eksekusi perintah di Postgres). Ini defense-in-depth: kurangi *blast radius* setiap kompromi. Senada dengan temuan di modul 04 soal kredensial.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html

  - id: b03
    tier: B
    type: text
    objective: access
    prompt: Apa beda authentication dan authorization secara singkat?
    answers: ['authentication: siapa kamu; authorization: apa yang boleh kamu akses']
    patterns: ['auth(entikasi|entication|n)[^\n]*(siapa|identitas|who)[^\n]*(author|otorisasi)[^\n]*(boleh|akses|hak|izin)', '(siapa|identitas)[^\n]*(boleh|hak|izin|akses)']
    tests:
      fail: ['sama saja', 'keduanya soal password', 'authorization lebih dulu dari authentication', 'authentication mengatur hak akses']
    explanation: '**Authentication** = membuktikan *siapa* kamu (login, password, token). **Authorization** = menentukan *apa yang boleh* kamu lakukan/akses setelah terbukti. IDOR (a04) adalah kegagalan **authorization**: user sudah login (authenticated) tapi server tidak cek apakah ia *berhak* atas objek yang diminta. Keduanya wajib — autentikasi kuat tanpa otorisasi per-objek tetap bocor.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html

  - id: b04
    tier: B
    type: text
    objective: access
    prompt: Prinsip di mana akses **ditolak secara default** dan hanya diizinkan secara eksplisit disebut apa?
    answers: ['deny by default']
    patterns: ['deny[- ]by[- ]default', 'default deny', 'tolak (secara )?default', 'default(nya)? (tolak|ditolak|deny)', 'fail closed|fail-closed']
    tests:
      fail: ['allow by default', 'izinkan dulu lalu blokir', 'default terbuka', 'open by default']
    explanation: 'Deny-by-default (fail-closed): setiap resource ditolak kecuali ada aturan yang secara eksplisit mengizinkan user ini. Lawannya (allow-by-default) berbahaya — endpoint baru otomatis terbuka sampai seseorang ingat mengamankannya. Di lab, blokir IDOR adalah bentuk ini: kecuali `id == me`, langsung `403`. Terapkan di setiap endpoint dan setiap objek, bukan sekadar di menu.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html

  - id: b05
    tier: B
    type: text
    objective: xss
    prompt: Sebutkan dua dari tiga tipe XSS berdasarkan cara payload sampai ke korban.
    answers: ['reflected dan stored']
    patterns: ['reflected[^\n]*(stored|dom|tersimpan)', 'stored[^\n]*(reflected|dom)', 'dom[^\n]*(reflected|stored)', '(tersimpan|persisten)[^\n]*(reflected|refleksi|dom)']
    tests:
      fail: ['besar dan kecil', 'internal dan eksternal', 'client dan server', 'aktif dan pasif saja']
    explanation: 'Tiga tipe XSS: **Reflected** (payload di request langsung dipantulkan ke response — yang kita buktikan di lab lewat `?q=<script>`), **Stored/persistent** (payload tersimpan di DB lalu tampil ke banyak korban — paling berbahaya), dan **DOM-based** (JS di client menulis input ke DOM tanpa encoding). Pertahanan dasarnya sama: contextual output encoding di tempat data ditampilkan + CSP sebagai lapisan.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html

  - id: b06
    tier: B
    type: text
    objective: xss
    prompt: Flag cookie yang membuat cookie **tidak bisa dibaca JavaScript** (mengurangi pencurian sesi lewat XSS) adalah?
    answers: ['httponly']
    patterns: ['http[- ]?only']
    tests:
      fail: ['secure', 'samesite', 'domain', 'path']
    explanation: 'Flag `HttpOnly` pada `Set-Cookie` membuat cookie tak terbaca `document.cookie`, sehingga XSS tidak bisa langsung mencuri cookie sesi. Pasangkan dengan `Secure` (hanya dikirim via HTTPS) dan `SameSite=Lax/Strict` (mitigasi CSRF). Catatan: `HttpOnly` mengurangi dampak XSS, bukan menyembuhkannya — XSS masih bisa bertindak atas nama korban. Perbaiki tetap di output encoding.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html

  - id: b07
    tier: B
    type: text
    objective: xss
    prompt: Mengapa CSP disebut defense-in-depth, bukan pengganti output encoding?
    answers: ['karena encoding mencegah xss muncul; csp hanya membatasi dampak jika ada yang lolos, dan csp bisa salah konfigurasi/di-bypass']
    patterns: ['(encoding|escap)[^\n]*(cegah|pertama|utama|akar)', 'csp[^\n]*(lapisan|kedua|dampak|jika lolos|backup|bisa (di-?bypass|lemah|salah))', '(bukan|tidak)[^\n]*(pengganti|akar)']
    tests:
      fail: ['karena csp selalu memblokir semua xss', 'karena encoding sudah usang', 'csp menggantikan encoding sepenuhnya', 'keduanya sama saja']
    explanation: 'Output encoding mencegah XSS *terbentuk* (data tak pernah jadi kode). CSP bekerja di lapisan browser: bila satu XSS lolos, CSP yang ketat bisa mencegah script jahat benar-benar jalan. Tapi CSP sering salah konfigurasi (`unsafe-inline`, domain CDN terlalu longgar) dan bisa di-bypass, jadi ia **lapisan kedua**. Andalkan encoding sebagai pertahanan utama, CSP sebagai jaring pengaman. Pola yang sama berulang: jangan bergantung pada satu kontrol.'
    source: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy

  - id: b08
    tier: B
    type: text
    objective: ssrf
    prompt: Sebutkan pertahanan SSRF yang paling kuat untuk menentukan tujuan request yang boleh diambil server.
    answers: ['allowlist host tujuan']
    patterns: ['allow ?list', 'whitelist', 'daftar (host )?(yang )?(diizinkan|boleh|putih)', '(hanya )?izinkan host tertentu']
    tests:
      fail: ['blacklist localhost saja', 'blokir kata internal', 'percaya url dari user', 'filter ekstensi file']
    explanation: 'Pertahanan SSRF terkuat adalah **allowlist** host/domain yang benar-benar perlu diakses — tolak sisanya. Di lab, versi hardened mem-parse host dari URL dan hanya mengizinkan `images.lab.local`; request ke layanan internal jadi `403`. Blocklist ("blokir 127.0.0.1/localhost") lemah: bisa di-bypass lewat `169.254.169.254`, IP desimal (`2130706433`), DNS rebinding, atau redirect. Tambahan: blokir rentang internal di layer jaringan dan larang app mengikuti redirect ke host baru.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html

  - id: b09
    tier: B
    type: text
    objective: ssrf
    prompt: Mengapa blocklist "tolak 127.0.0.1 dan localhost" lemah sebagai pertahanan SSRF? Sebutkan satu cara bypass.
    answers: ['bisa di-bypass lewat 169.254.169.254, ip desimal, dns rebinding, atau redirect']
    patterns: ['169\.254', 'ip (desimal|oktal|heksa|alternatif)|desimal|0x|oktal', 'dns rebinding', 'redirect', 'ipv6|\[::1\]|::1', '(banyak|beragam) (cara|notasi) (menulis|tulis) ip']
    tests:
      fail: ['blocklist tidak pernah lemah', 'karena localhost aman', 'tidak ada cara bypass', 'cukup blokir kata localhost']
    explanation: 'Blocklist mencoba menyebut semua yang "jahat" — selalu ada yang terlewat. Loopback/internal bisa ditulis sebagai `127.0.0.1`, `localhost`, `[::1]`, `0.0.0.0`, IP desimal `2130706433`, oktal/heksa, atau dicapai lewat `169.254.169.254` (metadata) dan RFC1918. Ditambah **DNS rebinding** (domain yang resolve ke IP internal setelah pengecekan) dan **redirect** ke host internal. Karena itu pakai **allowlist** (sebut yang boleh) dan validasi IP hasil resolve, bukan string URL.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html

  - id: b10
    tier: B
    type: text
    objective: headers
    prompt: Header `Strict-Transport-Security` (HSTS) menyuruh browser melakukan apa terhadap situsmu?
    answers: ['selalu memakai https, tidak boleh http']
    patterns: ['(selalu|wajib|hanya|paksa)[^\n]*https', 'https[^\n]*(saja|only|paksa|wajib)', '(tolak|tidak|jangan)[^\n]*(http biasa|plain http|downgrade)', 'cegah downgrade']
    tests:
      fail: ['memakai http lebih cepat', 'menyimpan cookie', 'memblokir iframe', 'menebak tipe konten']
    explanation: 'HSTS (`Strict-Transport-Security: max-age=31536000; includeSubDomains`) memberi tahu browser: untuk domain ini, **selalu** pakai HTTPS selama `max-age`, tolak HTTP biasa dan sertifikat invalid. Ini mencegah serangan downgrade/SSL-stripping (MITM memaksa korban ke HTTP). Di lab, versi hardened mengirimnya. Syaratnya situs memang sudah HTTPS; pertimbangkan HSTS preload untuk proteksi kunjungan pertama.'
    source: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Strict-Transport-Security

  - id: b11
    tier: B
    type: text
    objective: headers
    prompt: Atribut `Set-Cookie` yang memitigasi CSRF dengan membatasi pengiriman cookie pada request lintas-situs adalah?
    answers: ['samesite']
    patterns: ['same[- ]?site']
    tests:
      fail: ['httponly', 'secure', 'max-age', 'path']
    explanation: '`SameSite=Lax` (default modern) atau `Strict` membuat cookie tidak ikut terkirim pada request lintas-situs, sehingga memitigasi **CSRF** — serangan yang memakai cookie korban untuk aksi yang tidak ia kehendaki dari situs lain. Lengkapnya cookie sesi sebaiknya `HttpOnly; Secure; SameSite=Lax`. Untuk aksi sensitif, tetap pasang **anti-CSRF token** per form; SameSite adalah lapisan, bukan jaminan tunggal.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html

  - id: b12
    tier: B
    type: text
    objective: waf
    prompt: CRS memakai anomaly scoring. Jelaskan singkat cara kerjanya dan apa yang terjadi saat ambang terlampaui.
    answers: ['tiap aturan yang cocok menambah skor; jika total melewati ambang, request diblokir']
    patterns: ['(tiap|setiap|set-?iap) (aturan|rule)[^\n]*(skor|poin|score)', 'skor[^\n]*(ambang|threshold|lewat|lebih)', '(ambang|threshold)[^\n]*(blokir|403|tolak|ditolak)', 'akumulasi skor']
    tests:
      fail: ['satu aturan langsung memblokir selalu', 'memblokir semua request', 'skor tidak berpengaruh', 'hanya mencatat log tanpa blokir']
    explanation: 'Dalam anomaly scoring, tiap aturan yang cocok menambah skor sesuai severity (CRITICAL = 5). Request tidak langsung diblokir per aturan; skor diakumulasi, lalu aturan `949110` memblokir bila total **≥ ambang** (default inbound = 5). Di lab, payload berbeda memberi skor 5/10/15/20 — satu aturan CRITICAL saja sudah cukup. Keuntungan model ini: mengurangi false positive (butuh akumulasi), dan ambang bisa disetel saat tuning. Lawannya model "satu aturan = blokir" yang lebih kaku.'
    source: https://coreruleset.org/

  - id: b13
    tier: B
    type: text
    objective: waf
    prompt: Mengapa WAF tidak boleh dijadikan satu-satunya pertahanan untuk kerentanan web?
    answers: ['waf bisa di-bypass dan tidak memperbaiki akar bug; kode tetap harus diperbaiki']
    patterns: ['(bisa|dapat)[^\n]*(di-?bypass|dilewati|dielakkan)', '(tidak|bukan)[^\n]*(perbaiki|menutup|hapus)[^\n]*(bug|akar|kerentanan|kode)', 'kode[^\n]*(tetap|harus)[^\n]*(perbaiki|fix)', 'tambahan|lapisan, bukan pengganti']
    tests:
      fail: ['waf sempurna memblokir semua', 'kode tidak perlu diperbaiki jika ada waf', 'waf menggantikan prepared statement', 'cukup pasang waf lalu lupakan']
    explanation: 'WAF menyaring pola yang *diketahui* dan bisa di-bypass (encoding aneh, payload baru, request yang lolos signature). Ia **tidak** menghapus bug — app di baliknya tetap rentan. Di lab, app di belakang CRS tetap bisa di-SQLi bila diakses langsung (tanpa lewat WAF): `curl` ke app = 200. Jadi WAF adalah **lapisan** (beri waktu & redam serangan massal), sementara perbaikan sejati tetap di kode: prepared statement, otorisasi, encoding. Keduanya dipakai bersama.'
    source: https://coreruleset.org/

  - id: b14
    tier: B
    type: text
    objective: access
    prompt: Serangan yang menyalahgunakan sesi login korban untuk mengirim request yang tidak ia kehendaki dari situs lain disebut apa?
    answers: ['csrf']
    patterns: ['csrf', 'cross[- ]site request forgery', 'xsrf']
    tests:
      fail: ['xss', 'ssrf', 'sql injection', 'clickjacking']
    explanation: 'CSRF (Cross-Site Request Forgery) menipu browser korban yang sedang login untuk mengirim request berbahaya (mis. transfer, ubah email) — browser otomatis melampirkan cookie sesi. Pertahanan: **anti-CSRF token** unik per sesi/form yang tidak bisa ditebak situs lain, plus cookie `SameSite`. Beda dengan SSRF (server yang ditipu memanggil URL) — CSRF menipu *browser korban*. Jangan tertukar.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html

  - id: b15
    tier: B
    type: command
    objective: waf
    prompt: Uji WAF milikmu sendiri — kirim request dan tampilkan **hanya kode status HTTP**-nya dengan curl (ke `https://app.lab/?q=test`).
    answers: ['curl -o /dev/null -w "%{http_code}" https://app.lab/?q=test']
    patterns: ['^(?=.*[ \t]-o[ \t]+/dev/null([ \t]|$))(?=.*[ \t]-w[ \t])(?=.*%\{http_code\})curl[ \t].*app\.lab']
    tests:
      pass:
        - 'curl -o /dev/null -w "%{http_code}" https://app.lab/?q=test'
        - 'curl -s -o /dev/null -w "%{http_code}\n" https://app.lab/?q=test'
      fail:
        - 'curl https://app.lab/?q=test'
        - 'curl -I https://app.lab/?q=test'
        - 'curl -w "%{http_code}" https://app.lab/?q=test'
    explanation: '`curl -o /dev/null -w "%{http_code}"` membuang body dan hanya mencetak kode status — cara ringkas memverifikasi apakah WAF memblokir (403) atau meneruskan (200) sebuah payload **pada target milik sendiri**. Di lab persis begini kita ukur: login sah → 200, payload SQLi/XSS/traversal → 403. Pakai untuk regression-test aturan WAF setelah tuning, bukan untuk menyerang sistem orang lain.'
    source: https://coreruleset.org/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: injection
    prompt: |
      Saat review kamu menemukan endpoint login membangun query dengan menggabung string:
      `"SELECT * FROM users WHERE name = '" . $input . "'"`. Rancang perbaikan berlapis untuk menutup SQL injection
      di sini (satu poin per baris).
    checklist:
      - id: prepared
        label: Pakai prepared statement / query ter-parameter (akar perbaikan)
        required: true
        patterns: ['prepared statement', 'parameter(ized|isasi)? quer', 'query ter-?parameter', 'bind(ing)? parameter', 'placeholder']
      - id: leastpriv
        label: DB user ber-hak minimal (bukan superuser), batasi dampak
        required: true
        patterns: ['(hak|privilege)[^\n]*(minimal|terbatas|seperlunya)', 'least privilege', '(bukan|tanpa)[^\n]*(superuser|root|owner)', 'non-?superuser']
      - id: validate
        label: Validasi/normalisasi input (allowlist tipe & format) sebagai lapisan
        patterns: ['validasi[^\n]*input', 'allow ?list|whitelist', '(cek|batasi)[^\n]*(tipe|format|panjang)', 'input validation']
      - id: errors
        label: Jangan bocorkan pesan error SQL mentah ke user
        patterns: ['(jangan|tidak)[^\n]*(bocor|tampilkan|leak)[^\n]*(error|sql)', 'sembunyikan[^\n]*error', 'generic error|pesan error umum', 'matikan[^\n]*(debug|display_errors)']
      - id: waf
        label: WAF (CRS) sebagai lapisan tambahan, bukan pengganti
        patterns: ['waf', 'modsecurity', 'crs', 'core rule set']
      - id: logging
        label: Logging & alert percobaan injection (deteksi)
        patterns: ['(log|catat|monitor|alert|pantau)[^\n]*(injection|serangan|percobaan|query mencurigakan)', 'logging|monitoring']
      - id: no-blacklist
        label: Tidak "cukup escape/blacklist tanda kutip" sebagai satu-satunya solusi
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|hindari|selain))[^\n]*(cukup|hanya|sekadar)[^\n]*(escape|blacklist|filter)[^\n]*(kutip|quote|'')']
    pass_score: 0.5
    solution: |
      ```text
      1. AKAR: ganti ke prepared statement — $pdo->prepare("... WHERE name = :n"); ->execute([':n'=>$input]).
         (lab: payload ' OR '1'='1 yang tadinya 3 baris jadi 0 baris.)
      2. DB user ber-hak minimal: hanya SELECT pada tabel yang perlu, bukan superuser/owner → SQLi yang lolos
         tidak bisa DROP/baca tabel lain/COPY ... TO PROGRAM.
      3. Validasi input (allowlist tipe/format/panjang) sebagai lapisan, bukan pengganti prepared statement.
      4. Jangan tampilkan error SQL mentah ke user (matikan display_errors di prod) — error membocorkan skema.
      5. WAF (ModSecurity + CRS) di depan untuk meredam serangan massal & memberi waktu (lab: payload → 403).
      6. Logging + alert pola injection untuk deteksi dini.
      ```
      Akarnya satu — pisahkan kode dari data (prepared statement). Sisanya memperkecil blast radius dan mendeteksi.
      "Cukup escape tanda kutip" selalu bisa di-bypass dan BUKAN solusi; itulah jebakan yang dicari penyerang.
    tests:
      pass:
        - |
          ganti ke prepared statement atau query ter-parameter dengan placeholder
          db user ber-hak minimal bukan superuser agar dampak terbatas
          validasi input allowlist tipe dan format sebagai lapisan
          jangan bocorkan pesan error sql mentah ke user
          pasang waf modsecurity crs sebagai lapisan tambahan
          logging dan alert untuk percobaan injection
          jangan hanya escape tanda kutip sebagai satu-satunya solusi
        - |
          pakai prepared statement, bind parameter, jangan gabung string
          user database least privilege non superuser
          waf crs di depan plus logging percobaan serangan
      fail:
        - |
          cukup escape tanda kutip di input saja
          yang penting login jalan
        - |
          blacklist kata select dan union di input
          db tetap pakai user superuser biar gampang
        - |
          percaya saja input dari form login
          tidak perlu prepared statement
    source: https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html

  - id: c02
    tier: C
    type: scenario
    objective: access
    prompt: |
      Endpoint API `GET /api/orders/{id}` mengembalikan order apa pun selama `{id}` valid — user bisa membaca order
      milik orang lain hanya dengan menebak angka (IDOR). Rancang perbaikan access control (satu poin per baris).
    checklist:
      - id: serverside
        label: Cek otorisasi di server untuk SETIAP objek (apakah order ini milik user)
        required: true
        patterns: ['(cek|periksa|verifikasi)[^\n]*(otorisasi|kepemilikan|hak|milik)', 'object[- ]level', 'server[- ]?side[^\n]*(cek|otorisasi)', 'pastikan order[^\n]*milik']
      - id: denydefault
        label: Deny-by-default — tolak kecuali eksplisit diizinkan
        required: true
        patterns: ['deny[- ]by[- ]default', 'default deny', 'tolak (secara )?default', 'fail closed|fail-closed', 'default(nya)? (tolak|ditolak)']
      - id: notobscurity
        label: Jangan andalkan id yang "sulit ditebak" (UUID) sebagai kontrol akses
        patterns: ['(bukan|jangan|tidak cukup)[^\n]*(tebak|obscurity|uuid|acak)', 'id (acak|uuid)[^\n]*(bukan|tidak)[^\n]*(kontrol|akses|keamanan)', 'security by obscurity']
      - id: everyendpoint
        label: Terapkan di semua endpoint/aksi (baca, ubah, hapus), bukan satu saja
        patterns: ['(semua|setiap|tiap)[^\n]*(endpoint|aksi|method|rute)', '(read|write|update|delete|baca|ubah|hapus)[^\n]*(semua|semuanya|dicek)', 'konsisten[^\n]*endpoint']
      - id: logging
        label: Log & alert akses yang ditolak (deteksi enumerasi)
        patterns: ['(log|catat|alert|monitor|pantau)[^\n]*(tolak|denied|403|akses|enumerasi)', 'deteksi[^\n]*(enumerasi|penebakan)', 'logging|monitoring']
      - id: no-clientside
        label: Tidak mengandalkan client (sembunyikan tombol/field) sebagai kontrol akses
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|hindari))[^\n]*(client|browser|frontend|javascript|sembunyikan (tombol|link|ui))[^\n]*(kontrol|akses|cukup|saja|cek)']
    pass_score: 0.5
    solution: |
      ```text
      1. Cek kepemilikan di server untuk setiap objek: WHERE order.id = :id AND order.user_id = :current_user —
         atau tolak 403/404 bila bukan miliknya. (lab: cek $id !== $me → 403.)
      2. Deny-by-default: handler menolak kecuali ada aturan yang mengizinkan user ini atas objek ini.
      3. Jangan andalkan id sulit ditebak (UUID bagus untuk mengurangi enumerasi, TAPI bukan kontrol akses).
      4. Terapkan di SEMUA endpoint & aksi (GET/PUT/DELETE), termasuk endpoint internal/admin — IDOR sering di
         endpoint yang "jarang dipakai".
      5. Log + alert akses yang ditolak → deteksi penyerang yang meng-enumerasi id.
      6. Opsi: indirect object reference (map id → referensi per-sesi) untuk lapisan tambahan.
      ```
      Inti Broken Access Control: autentikasi saja tidak cukup; server harus memutuskan OTORISASI per objek, selalu
      di server. Menyembunyikan tombol di UI atau memakai id acak saja hanyalah security-by-obscurity — bukan kontrol.
    tests:
      pass:
        - |
          cek otorisasi di server untuk setiap objek apakah order ini milik user yang login
          deny by default tolak kecuali eksplisit diizinkan
          jangan andalkan id yang sulit ditebak seperti uuid sebagai kontrol akses
          terapkan di semua endpoint dan aksi baca ubah hapus bukan satu saja
          log dan alert akses yang ditolak untuk deteksi enumerasi
          jangan andalkan client menyembunyikan tombol sebagai kontrol akses
        - |
          server cek kepemilikan objek where user_id sama dengan current user
          default deny, konsisten di semua endpoint, logging akses ditolak
          uuid bukan pengganti kontrol akses
      fail:
        - |
          cukup sembunyikan tombol order milik orang lain di frontend
          id-nya juga susah ditebak kok
        - |
          pakai uuid saja biar tidak bisa ditebak
          tidak perlu cek kepemilikan di server
        - |
          cek di javascript browser saja
          yang penting user biasa tidak lihat linknya
    source: https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html

  - id: c03
    tier: C
    type: scenario
    objective: waf
    prompt: |
      Aplikasi legacy publik yang belum bisa segera ditulis ulang mulai dibanjiri lalu lintas bot yang mencoba SQLi
      dan XSS. Rancang pertahanan web berlapis sebagai penanganan sekarang — dan tegaskan batasnya (satu poin per baris).
    checklist:
      - id: waf
        label: Pasang WAF (ModSecurity + OWASP CRS) di depan untuk memblokir pola serangan
        required: true
        patterns: ['waf', 'modsecurity', 'crs', 'core rule set']
      - id: headers
        label: Pasang security headers (CSP, HSTS, X-Content-Type-Options, X-Frame-Options)
        required: true
        patterns: ['security header', 'csp|content-security-policy', 'hsts|strict-transport', 'x-frame-options|x-content-type-options|nosniff']
      - id: fixcode
        label: Tetap jadwalkan perbaikan akar di kode (WAF bukan pengganti)
        required: true
        patterns: ['(perbaiki|fix|tulis ulang|patch)[^\n]*(kode|akar|aplikasi|bug|kerentanan)', 'waf (bukan|tidak)[^\n]*(pengganti|cukup|permanen)', 'akar (masalah|penyebab)']
      - id: ratelimit
        label: Rate limiting / throttling terhadap bot (kaitan ke modul 08)
        patterns: ['rate[- ]?limit', 'throttl', 'batasi (laju|request)', 'limit request per']
      - id: logging
        label: Logging & monitoring/alert serangan (deteksi)
        patterns: ['(log|catat|monitor|alert|pantau)[^\n]*(serangan|waf|403|anomali)', 'logging|monitoring|siem']
      - id: validate
        label: Virtual patch di WAF / input validation untuk endpoint rentan yang diketahui
        patterns: ['virtual patch', 'validasi input', 'aturan (khusus|custom)[^\n]*waf', 'input validation']
      - id: no-wafonly
        label: Tidak menganggap WAF sudah cukup dan melupakan perbaikan kode
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain|tetap))[^\n]*(cukup|hanya|sekadar)[^\n]*waf[^\n]*(selesai|cukup|aman|beres)']
    pass_score: 0.5
    solution: |
      ```text
      SEKARANG (meredam):
        - WAF ModSecurity + OWASP CRS di depan app (lab: SQLi/XSS/traversal → 403, request sah → 200).
        - Security headers di reverse proxy: CSP, HSTS, X-Content-Type-Options: nosniff, X-Frame-Options: DENY.
        - Rate limiting / throttling untuk meredam bot & scanning (jembatan ke modul 08 — availability).
        - Virtual patch: aturan WAF khusus untuk endpoint rentan yang sudah diketahui.
        - Logging + alert (skor anomali CRS, spike 403) → deteksi & tuning.
      SEJATI (akar):
        - Jadwalkan perbaikan kode: prepared statement, otorisasi server-side, output encoding. WAF memberi WAKTU,
          bukan kesembuhan — ia bisa di-bypass dan app tetap rentan bila diakses langsung (lab membuktikannya).
      ```
      Ini defense-in-depth yang jujur: lapisan luar meredam serangan massal hari ini, tapi "pasang WAF lalu lupakan"
      adalah jebakan — akar bug tetap ada. WAF = pereda, perbaikan kode = obat.
    tests:
      pass:
        - |
          pasang waf modsecurity dengan owasp crs di depan untuk memblokir pola sqli dan xss
          pasang security headers csp hsts x-content-type-options nosniff dan x-frame-options
          tetap jadwalkan perbaikan akar di kode karena waf bukan pengganti dan bisa di-bypass
          rate limiting untuk meredam bot menyambung ke modul 08
          logging dan monitoring serangan untuk deteksi dan tuning
          virtual patch aturan waf khusus untuk endpoint rentan yang diketahui
          jangan anggap waf sudah cukup lalu lupakan perbaikan kode
        - |
          waf crs di depan plus security headers lengkap
          rate limit bot, logging spike 403, virtual patch endpoint rentan
          tetap perbaiki kode prepared statement dan otorisasi sebagai akar
      fail:
        - |
          cukup pasang waf saja sudah aman
          kode tidak perlu diperbaiki lagi
        - |
          blokir semua bot di firewall dan selesai
          tidak usah mikir headers atau kode
        - |
          matikan aplikasi saja sampai ada yang menulis ulang
          tidak perlu lapisan apa-apa
    source: https://coreruleset.org/
---

# Keamanan Web Aplikasi (OWASP)

Setelah host dan container di-hardening (modul 03 & 06), penyerang beralih ke lapisan yang paling sering memberi
pijakan pertama: **aplikasi** (ATT&CK T1190, Exploit Public-Facing Application). Hampir semua kelas kerentanan web
berakar pada satu pola — **input tak tepercaya yang dipercaya sebagai kode**. Modul ini membuktikan empat kelas
utama rentan, lalu menutupnya, dengan WAF sebagai lapisan tambahan.

> [!NOTE]
> Lab: app PHP 8.3 + Postgres 16 di jaringan terisolasi `dj-sec07`, dengan toggle `HARDEN=0/1` pada kode yang sama
> untuk membandingkan versi rentan vs aman. WAF `owasp/modsecurity-crs:nginx` (libmodsecurity 3.0.17, 855 rule CRS)
> di depan app yang sama. Semua target milik sendiri — jangan uji pada sistem orang lain.

## 1. Empat kelas, satu akar (hasil lab)

| Kelas (OWASP) | Payload rentan (HARDEN=0) | Hasil | Perbaikan (HARDEN=1) | Hasil |
|---|---|---|---|---|
| Injection — SQLi (A03) | `name=' OR '1'='1` | 3 baris (semua user) | prepared statement | 0 baris |
| Injection — SQLi (A03) | `' UNION SELECT id,ssn,role ...` | bocor kolom `ssn` | prepared statement | gagal |
| Broken Access Control — IDOR (A01) | `profile&id=3` (login sbg id=1) | baca `ssn` admin | cek kepemilikan server-side | `403` |
| XSS reflected (A03) | `q=<script>alert(1)</script>` | tag `<script>` mentah | `htmlspecialchars()` | `&lt;script&gt;` |
| SSRF (A10) | `fetch&url=http://internal/...` | bocor token internal | allowlist host | `403` |

Akar semua baris di atas sama: **data dibaca sebagai kode**, atau **objek diakses tanpa cek hak**. Pola pertahanan
pun sepola: pisahkan kode dari data, dan tolak secara default.

## 2. Injection → query ter-parameter

```text
rentan : "SELECT ... WHERE name = '$u'"          →  ' OR '1'='1  = 3 baris, UNION = bocor ssn
aman   : prepare("... WHERE name = :n")->execute →  input jadi literal, 0 baris
```

Prepared statement mengirim struktur query dan data secara terpisah; input tak pernah di-parse sebagai SQL.
Lapisan tambahan: **DB user ber-hak minimal** (bukan superuser) agar SQLi yang lolos tetap terbatas.

## 3. Broken Access Control → otorisasi server-side

IDOR dibuktikan: user `alice` (id=1) membaca `ssn` admin hanya dengan `id=3`. Perbaikannya **di server**, bukan di UI:

```php
if ($id !== $currentUser) { http_response_code(403); exit; }   // deny-by-default, per objek
```

- Otorisasi WAJIB server-side — client (tombol disembunyikan, field tersembunyi) bisa diubah dengan `curl`/DevTools.
- Deny-by-default dan cek **setiap objek di setiap endpoint**. Id acak/UUID mengurangi penebakan, tapi **bukan**
  kontrol akses.

## 4. XSS → output encoding + CSP

```text
rentan : echo $q                 →  <script>alert(1)</script>   (tereksekusi)
aman   : echo htmlspecialchars($q) →  &lt;script&gt;...         (tampil sebagai teks)
```

- **Output encoding kontekstual** adalah pertahanan utama (akar). Framework modern auto-escape — jangan matikan.
- **CSP** (`default-src 'self'`) adalah lapisan kedua: kalau satu XSS lolos, script jahat tetap bisa ditolak browser.
- Cookie sesi: `HttpOnly; Secure; SameSite=Lax` → mengurangi pencurian sesi (HttpOnly) & CSRF (SameSite).

## 5. SSRF → allowlist, bukan blocklist

```text
rentan : file_get_contents($url)              →  ambil http://internal → bocor token
aman   : host allowlist ['images.lab.local']  →  host lain = 403
```

Blocklist (`tolak 127.0.0.1/localhost`) lemah — bisa di-bypass lewat `169.254.169.254` (metadata), IP desimal,
IPv6 `[::1]`, DNS rebinding, atau redirect. Pakai **allowlist** host tujuan, validasi IP hasil resolve, blokir
rentang internal di jaringan, dan larang mengikuti redirect ke host baru.

## 6. Security headers (hasil lab, `curl -I`)

| Header | Fungsi |
|---|---|
| `Content-Security-Policy: default-src 'self'` | batasi sumber script (lapisan anti-XSS) |
| `Strict-Transport-Security: max-age=31536000` | paksa HTTPS, cegah downgrade/SSL-strip |
| `X-Content-Type-Options: nosniff` | matikan MIME sniffing |
| `X-Frame-Options: DENY` | cegah clickjacking (atau CSP `frame-ancestors`) |
| (hapus) `X-Powered-By: PHP/8.3.37` | jangan bocorkan versi stack (modul 02) |

Versi rentan di lab tidak punya satu pun dari empat header pertama **dan** membocorkan `X-Powered-By`; versi hardened
menutup semuanya.

## 7. WAF — lapisan, bukan obat (hasil lab)

```text
owasp/modsecurity-crs:nginx (libmodsecurity 3.0.17, 855 rule), app di belakang MASIH rentan:
  login sah        → 200
  ' OR '1'='1      → 403    (anomaly score 5)
  UNION SELECT     → 403    (score 20)
  <script>...      → 403
  file:///etc/passwd → 403
  langsung ke app (tanpa WAF) → 200  ← app tetap rentan
```

CRS memakai **anomaly scoring**: tiap aturan yang cocok menambah skor; bila total ≥ ambang (default 5), aturan
`949110` memblokir. WAF meredam serangan massal dan memberi **waktu** — tapi bisa di-bypass dan tidak memperbaiki
bug. Akar tetap diperbaiki di kode (bagian 2–5).

## 8. Ringkasan: tutup T1190

| Kelas (OWASP) | Pertahanan utama (kode) | Lapisan tambahan |
|---|---|---|
| Injection / SQLi (A03) | prepared statement | DB least-privilege, WAF, logging |
| Broken Access Control (A01) | otorisasi server-side, deny-by-default | logging akses ditolak |
| XSS (A03) | output encoding kontekstual | CSP, HttpOnly, WAF |
| SSRF (A10) | allowlist host + blokir internal | IMDSv2, tanpa follow-redirect |
| CSRF | anti-CSRF token | `SameSite` cookie |
| (semua) | security headers, perbaikan kode | WAF (CRS), monitoring |

> [!WARNING]
> WAF, security headers, dan DB least-privilege memperkecil *dampak* — tapi akar tiap kelas ada di **kode**. Jangan
> pernah menukar "perbaiki kode" dengan "pasang WAF": app di lab tetap jebol saat diakses langsung.

> [!NOTE]
> Lanjut ke modul 08 (Ketersediaan / anti-DDoS) — setelah menutup kelas kerentanan, menjaga agar layanan tetap
> *hidup* di bawah banjir lalu lintas: rate limiting, caching, dan mitigasi DDoS pada server & web.
