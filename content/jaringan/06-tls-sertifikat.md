---
format: 1
id: net-tls-certificates
course:
  slug: jaringan
  title: Jaringan
  description: Konsep jaringan untuk DevOps — TCP/IP, subnetting, routing, TCP/UDP, DNS, HTTP, TLS, proxy, firewall, VPN, dan troubleshooting.
title: TLS & Sertifikat
summary: Cara kerja TLS dan rantai kepercayaan, membuat CA sendiri, SAN dan wildcard, SNI, ALPN, versi TLS, HSTS, mTLS, serta mereproduksi setiap error sertifikat yang umum (intermediate hilang, salah nama, kedaluwarsa, self-signed, key tidak cocok) dengan pesan aslinya dari curl, openssl, dan nginx.
order: 6
version: 1
status: verified
updated: '2026-11-24'
tested_on:
  - Docker Desktop (Windows) — OpenSSL 3.3.7, nginx 1.27.5, curl (alpine:3.20)
  - PKI lab — Root CA → Intermediate CA → sertifikat leaf ECDSA P-256
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: trust
    text: Memahami rantai kepercayaan (root, intermediate, leaf) dan cara memverifikasinya
  - id: names
    text: Memahami SAN, wildcard, dan pencocokan nama
  - id: handshake
    text: Memahami handshake TLS, SNI, ALPN, dan versi TLS
  - id: errors
    text: Mengenali dan memperbaiki error sertifikat yang umum
  - id: ops
    text: Memasang, memverifikasi, dan memantau sertifikat di nginx
  - id: mtls
    text: Memahami mTLS dan HSTS
sources:
  - title: RFC 8446 — TLS 1.3
    url: https://www.rfc-editor.org/rfc/rfc8446
  - title: RFC 5280 — X.509 PKI Certificate Profile
    url: https://www.rfc-editor.org/rfc/rfc5280
  - title: RFC 6125 — Service Identity (nama di sertifikat, wildcard)
    url: https://www.rfc-editor.org/rfc/rfc6125
  - title: RFC 6066 — TLS Extensions (Server Name Indication)
    url: https://www.rfc-editor.org/rfc/rfc6066
  - title: RFC 7301 — TLS ALPN
    url: https://www.rfc-editor.org/rfc/rfc7301
  - title: RFC 6797 — HTTP Strict Transport Security (HSTS)
    url: https://www.rfc-editor.org/rfc/rfc6797
  - title: RFC 8996 — Deprecating TLS 1.0 and 1.1
    url: https://www.rfc-editor.org/rfc/rfc8996
  - title: OpenSSL — s_client, x509, verify, req, ca
    url: https://docs.openssl.org/3.3/man1/
  - title: nginx — ngx_http_ssl_module
    url: https://nginx.org/en/docs/http/ngx_http_ssl_module.html
  - title: curl — SSL certificate verification
    url: https://curl.se/docs/sslcerts.html
  - title: Mozilla — Server Side TLS recommendations
    url: https://wiki.mozilla.org/Security/Server_Side_TLS
  - title: Let's Encrypt — Chain of trust
    url: https://letsencrypt.org/certificates/
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: handshake
    prompt: Extension TLS yang dipakai klien untuk memberi tahu **nama host** yang ingin diakses di awal handshake, agar server bisa memilih sertifikat yang tepat?
    answers: ['SNI', 'Server Name Indication']
    patterns: ['^(sni|server name indication)$']
    tests:
      pass: ['Server Name Indication']
      fail: ['ALPN', 'Host', 'OCSP', 'SAN']
    explanation: 'Di lab, satu IP:443 melayani banyak sertifikat. `openssl s_client -servername dojo.test` → `subject=CN=dojo.test`, sedangkan tanpa SNI (`-noservername`) → `subject=CN=other.test` (sertifikat `default_server`). Header HTTP `Host` baru dikirim **setelah** TLS terbentuk, jadi server butuh SNI untuk memilih sertifikat.'
    source: https://www.rfc-editor.org/rfc/rfc6066

  - id: a02
    tier: A
    type: text
    objective: names
    prompt: Field (extension) sertifikat tempat nama host yang valid **harus** dicantumkan (CN saja diabaikan klien modern)?
    answers: ['SAN', 'subjectAltName', 'Subject Alternative Name']
    patterns: ['^(san|subject ?alt(ernative)? ?name)$']
    tests:
      pass: ['subjectAltName', 'Subject Alternative Name']
      fail: ['CN', 'Common Name', 'Issuer', 'OU']
    explanation: 'Di lab: `X509v3 Subject Alternative Name: DNS:dojo.test, DNS:www.dojo.test`, dan curl `-v` menampilkan `subjectAltName: host "dojo.test" matched cert''s "dojo.test"`. Satu sertifikat bisa memuat banyak nama. Sertifikat tanpa SAN ditolak browser dan curl modern, walaupun CN-nya benar.'
    source: https://www.rfc-editor.org/rfc/rfc6125

  - id: a03
    tier: A
    type: text
    objective: trust
    prompt: Di file `fullchain.pem` untuk nginx, sertifikat apa yang harus berada di **urutan pertama**?
    answers: ['sertifikat leaf (server)']
    patterns: ['(leaf|server|domain|situs|end[- ]entity|dojo\.test|milik (domain|situs))']
    tests:
      pass: ['leaf', 'sertifikat server/domain itu sendiri']
      fail: ['root', 'intermediate', 'root ca', 'ca']
    explanation: 'Urutannya: leaf → intermediate (root tidak perlu, karena klien sudah punya). Di lab, `wrongorder.pem` (intermediate dulu, lalu leaf) membuat `nginx -t` gagal dengan `key values mismatch`, karena nginx memasangkan key dengan sertifikat **pertama** di file. Let''s Encrypt sudah menyediakan `fullchain.pem` dengan urutan yang benar.'
    source: https://nginx.org/en/docs/http/configuring_https_servers.html#chains

  - id: a04
    tier: A
    type: command
    objective: handshake
    prompt: Buka koneksi TLS ke `10.64.0.43:443` dengan `openssl`, kirim SNI `dojo.test`.
    answers: ['openssl s_client -connect 10.64.0.43:443 -servername dojo.test']
    patterns: ['^(echo \| )?openssl s_client ((-connect 10\.64\.0\.43:443|-servername dojo\.test|-CAfile \S+|-showcerts|-brief) ?)+$']
    tests:
      pass: ['openssl s_client -servername dojo.test -connect 10.64.0.43:443', 'echo | openssl s_client -connect 10.64.0.43:443 -servername dojo.test -CAfile root.pem']
      fail: ['openssl s_client 10.64.0.43:443', 'openssl x509 -connect 10.64.0.43:443', 'curl -k https://10.64.0.43']
    explanation: 'Di lab, output-nya menampilkan rantai (`0 s:CN=dojo.test i:CN=Dojo Intermediate CA`, `1 s:CN=Dojo Intermediate CA i:CN=Dojo Root CA`), `New, TLSv1.3, Cipher is TLS_AES_256_GCM_SHA384`, dan `Verify return code`. OpenSSL 3 mengirim SNI dari `-connect` bila berupa nama, tetapi selalu tulis `-servername` saat memakai IP.'
    source: https://docs.openssl.org/3.3/man1/openssl-s_client/

  - id: a05
    tier: A
    type: command
    objective: ops
    prompt: Tampilkan tanggal **kedaluwarsa** sertifikat di file `site.pem` dengan `openssl x509`.
    answers: ['openssl x509 -in site.pem -noout -enddate']
    patterns: ['^openssl x509 (?=.*-in site\.pem( |$))(?=.*-(enddate|dates)( |$))((-in site\.pem|-noout|-enddate|-dates) ?)+$']
    tests:
      pass: ['openssl x509 -noout -enddate -in site.pem', 'openssl x509 -in site.pem -noout -dates']
      fail: ['openssl x509 -in site.pem -text', 'openssl verify site.pem', 'openssl x509 -enddate']
    explanation: 'Di lab: `notAfter=Feb 22 04:36:48 2027 GMT` (90 hari, seperti Let''s Encrypt). `-dates` menampilkan `notBefore` dan `notAfter`. Field berguna lain: `-subject -issuer -ext subjectAltName -fingerprint`. Untuk server live, gabungkan: `echo | openssl s_client ... | openssl x509 -noout -enddate` (modul Linux 08).'
    source: https://docs.openssl.org/3.3/man1/openssl-x509/

  - id: a06
    tier: A
    type: command
    objective: trust
    prompt: Verifikasi `site.pem` terhadap root `root.pem`, dengan intermediate `int.pem` sebagai sertifikat perantara (tidak dipercaya langsung).
    answers: ['openssl verify -CAfile root.pem -untrusted int.pem site.pem']
    patterns: ['^openssl verify ((-CAfile root\.pem|-untrusted int\.pem) ){2}site\.pem$']
    tests:
      pass: ['openssl verify -untrusted int.pem -CAfile root.pem site.pem']
      fail: ['openssl verify -CAfile root.pem site.pem', 'openssl verify -CAfile int.pem site.pem root.pem', 'openssl x509 -verify site.pem']
    explanation: 'Di lab: `site.pem: OK`. Tanpa `-untrusted int.pem` → `error 20 at 0 depth lookup: unable to get local issuer certificate`, karena root tidak menandatangani leaf secara langsung. Menaruh intermediate di `-CAfile` juga "berhasil", tetapi membuatnya dipercaya sebagai anchor. Di produksi, kepercayaan hanya berakar di root.'
    source: https://docs.openssl.org/3.3/man1/openssl-verify/

  - id: a07
    tier: A
    type: command
    objective: ops
    prompt: Cek apakah `site.pem` akan kedaluwarsa dalam **30 hari** ke depan (exit code ≠ 0 bila ya), cocok untuk skrip monitoring.
    answers: ['openssl x509 -in site.pem -noout -checkend 2592000']
    patterns: ['^openssl x509 (?=.*-in site\.pem( |$))(?=.*-checkend 2592000( |$))((-in site\.pem|-noout|-checkend 2592000) ?)+$']
    tests:
      pass: ['openssl x509 -noout -checkend 2592000 -in site.pem']
      fail: ['openssl x509 -in site.pem -checkend 30', 'openssl x509 -in site.pem -noout -enddate', 'openssl verify -checkend 2592000 site.pem']
    explanation: '`-checkend` menerima **detik**: 30 × 86400 = 2592000. Di lab: `site 30d rc=0` (`Certificate will not expire`), sertifikat kedaluwarsa `-checkend 0` → rc=1, dan sertifikat 90 hari dengan `-checkend 7776000` → rc=1 (akan habis dalam jendela itu). Exit code inilah yang dipakai alert cron/monitoring.'
    source: https://docs.openssl.org/3.3/man1/openssl-x509/

  - id: a08
    tier: A
    type: command
    objective: trust
    prompt: Dengan curl, akses `https://dojo.test/` dan percayai CA dari file `root.pem` (bukan store sistem).
    answers: ['curl --cacert root.pem https://dojo.test/']
    patterns: ['^curl (-sS? |-v )?--cacert[= ]root\.pem (-sS? |-v )?https://dojo\.test/?$']
    tests:
      pass: ['curl -s --cacert root.pem https://dojo.test/', 'curl --cacert=root.pem https://dojo.test']
      fail: ['curl -k https://dojo.test/', 'curl --cert root.pem https://dojo.test/', 'curl --capath root.pem https://dojo.test/']
    explanation: 'Di lab: dengan `--cacert /pki/root.pem` → `200`, tanpanya (store sistem) → `curl: (60) SSL certificate problem: unable to get local issuer certificate`, karena CA privat tidak dikenal sistem. Untuk aplikasi di container, pasang CA perusahaan ke store sistem (`update-ca-certificates`), jangan mematikan verifikasi.'
    source: https://curl.se/docs/sslcerts.html

  - id: a09
    tier: A
    type: text
    objective: errors
    prompt: Apa efek opsi `curl -k` (`--insecure`)?
    answers: ['mematikan verifikasi sertifikat (rantai dan nama)']
    patterns: ['(tidak|tanpa|skip|lewati|matikan|abaikan|nonaktif).*(verifikasi|validasi|cek|periksa|verify)', 'insecure']
    tests:
      pass: ['mematikan verifikasi sertifikat', 'skip verify']
      fail: ['memakai kunci lebih kuat', 'memaksa tls 1.3', 'mengenkripsi lebih aman']
    explanation: 'Di lab, `curl -k https://self.dojo.test/` → `self`, berhasil walaupun self-signed. Koneksi tetap terenkripsi, tetapi tidak ada jaminan **dengan siapa** kamu bicara, jadi man-in-the-middle tidak terdeteksi. `-k`, `verify=False`, dan `CURLOPT_SSL_VERIFYPEER=false` di production adalah celah keamanan, bukan perbaikan.'
    source: https://curl.se/docs/sslcerts.html

  - id: a10
    tier: A
    type: text
    objective: handshake
    prompt: Extension TLS yang dipakai klien dan server untuk menyepakati protokol aplikasi (mis. `h2` atau `http/1.1`) di dalam handshake?
    answers: ['ALPN']
    patterns: ['^(alpn|application[- ]layer protocol negotiation)$']
    tests:
      pass: ['Application-Layer Protocol Negotiation']
      fail: ['SNI', 'NPN', 'Upgrade', 'OCSP']
    explanation: 'Di lab, curl `-v`: `ALPN: curl offers h2,http/1.1`, lalu `ALPN: server accepted h2`, dan `http_version 2`. Inilah cara browser mendapat HTTP/2. Tanpa TLS tidak ada ALPN, jadi browser tidak memakai h2 (modul 05).'
    source: https://www.rfc-editor.org/rfc/rfc7301

  - id: a11
    tier: A
    type: text
    objective: mtls
    prompt: Header respons yang memerintahkan browser agar **selalu** memakai HTTPS untuk domain itu selama periode tertentu?
    answers: ['Strict-Transport-Security', 'HSTS']
    patterns: ['^(strict-transport-security|hsts)$']
    tests:
      pass: ['HSTS']
      fail: ['Content-Security-Policy', 'Upgrade-Insecure-Requests', 'X-Frame-Options', 'Location']
    explanation: 'Di lab: `strict-transport-security: max-age=31536000; includeSubDomains`. Setelah browser melihatnya (lewat HTTPS), ia menolak HTTP biasa ke domain itu dan tidak bisa di-bypass dengan "lanjutkan" saat ada error sertifikat. Ini meredam serangan SSL stripping pada request pertama yang masih HTTP.'
    source: https://www.rfc-editor.org/rfc/rfc6797

  - id: a12
    tier: A
    type: text
    objective: handshake
    prompt: Versi TLS **minimum** yang direkomendasikan untuk server publik saat ini?
    answers: ['TLS 1.2', '1.2', 'TLSv1.2']
    patterns: ['^(tls ?v?)?1\.2$']
    tests:
      pass: ['TLSv1.2']
      fail: ['TLS 1.0', 'TLS 1.1', 'SSLv3', '1.1']
    explanation: 'TLS 1.0 dan 1.1 sudah dinyatakan usang (RFC 8996). Di lab, `ssl_protocols TLSv1.2 TLSv1.3;`, sehingga klien `--tls-max 1.1` → `curl: (35) ... no protocols available`. Negosiasi default memilih TLS 1.3 (`TLS_AES_256_GCM_SHA384`), dan TLS 1.2 tetap bisa (`ECDHE-ECDSA-AES256-GCM-SHA384`) untuk klien lama.'
    source: https://www.rfc-editor.org/rfc/rfc8996

  - id: a13
    tier: A
    type: text
    objective: names
    prompt: Apakah sertifikat `*.apps.dojo.test` valid untuk nama `apps.dojo.test` itu sendiri? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab: `curl: (60) SSL: no alternative certificate subject name matches target hostname ''apps.dojo.test''`. Wildcard harus menggantikan tepat satu label. Untuk mencakup apex juga, tambahkan SAN kedua: `DNS:apps.dojo.test, DNS:*.apps.dojo.test` (Let''s Encrypt: `-d apps.dojo.test -d ''*.apps.dojo.test''`).'
    source: https://www.rfc-editor.org/rfc/rfc6125

  - id: a14
    tier: A
    type: text
    objective: names
    prompt: Apakah sertifikat `*.apps.dojo.test` valid untuk `foo.bar.apps.dojo.test` (dua label)? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab: `x.apps.dojo.test` → `200`, tetapi `foo.bar.apps.dojo.test` → `no alternative certificate subject name matches` (openssl: `Verify return code: 62 (hostname mismatch)`). Bandingkan dengan wildcard **DNS** di modul 04, yang mencocokkan satu atau lebih label. Record DNS-nya ada, tetapi sertifikatnya tidak cocok.'
    source: https://www.rfc-editor.org/rfc/rfc6125

  - id: a15
    tier: A
    type: text
    objective: mtls
    prompt: Direktif nginx yang **mewajibkan** klien mengirim sertifikat yang valid (mTLS)?
    answers: ['ssl_verify_client on', 'ssl_verify_client']
    patterns: ['^ssl_verify_client( on)?;?$']
    tests:
      pass: ['ssl_verify_client on;']
      fail: ['ssl_client_certificate', 'ssl_verify_depth', 'ssl_trusted_certificate', 'ssl_verify_client off']
    explanation: 'Di lab, `ssl_client_certificate /pki/root.pem` (CA yang dipercaya untuk klien) + `ssl_verify_client on`: tanpa sertifikat → `400 No required SSL certificate was sent`, dengan `client1` → `mtls ok: CN=client1`. `optional` = minta tapi tidak wajib (cek `$ssl_client_verify` di aplikasi).'
    source: https://nginx.org/en/docs/http/ngx_http_ssl_module.html#ssl_verify_client

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: errors
    prompt: Server hanya mengirim sertifikat **leaf** (tanpa intermediate). Error apa yang ditampilkan curl?
    answers: ['unable to get local issuer certificate']
    patterns: ['unable to get local issuer', 'unable to verify the first certificate']
    tests:
      pass: ['curl: (60) SSL certificate problem: unable to get local issuer certificate']
      fail: ['certificate has expired', 'self-signed certificate', 'hostname mismatch']
    explanation: 'Di lab, server port 8443 dengan `ssl_certificate site.pem` (leaf saja) → `curl: (60) SSL certificate problem: unable to get local issuer certificate`. `openssl s_client` → hanya `0 s:CN=dojo.test`, dengan `Verify return code: 21 (unable to verify the first certificate)`. Pesan curl yang sama juga muncul untuk CA privat yang tidak dipercaya, jadi cek dulu dengan s_client berapa sertifikat yang dikirim.'
    source: https://curl.se/docs/sslcerts.html

  - id: b02
    tier: B
    type: text
    objective: errors
    prompt: Situs dengan intermediate hilang tetap terbuka di Chrome desktop, tetapi gagal di curl, aplikasi PHP, dan Android lama. Kenapa?
    answers: ['browser melengkapi intermediate sendiri (cache/AIA fetch), klien lain tidak']
    patterns: ['(cache|aia|mengunduh|download|fetch|sudah punya|melengkapi|menyimpan).*intermediate', 'intermediate.*(cache|aia|disimpan|diunduh)']
    tests:
      pass: ['browser punya intermediate di cache / AIA fetching', 'chrome mengunduh intermediate lewat AIA']
      fail: ['chrome tidak memverifikasi sertifikat', 'curl rusak', 'android tidak mendukung tls']
    explanation: 'Di lab, leaf-only gagal dengan root saja, tetapi **berhasil** bila intermediate ditambahkan ke file CA klien (`leafonly`). Browser desktop sering sudah menyimpan intermediate dari situs lain atau mengunduhnya lewat AIA, sehingga konfigurasi salah tersamarkan. Uji selalu dengan `openssl s_client` atau curl, bukan browser.'
    source: https://nginx.org/en/docs/http/configuring_https_servers.html#chains

  - id: b03
    tier: B
    type: text
    objective: handshake
    prompt: '`openssl s_client -connect IP:443 -noservername` ke server nginx dengan banyak situs. Sertifikat milik siapa yang dikirim?'
    answers: ['sertifikat default_server (bukan situs yang dimaksud)']
    patterns: ['(default|other\.test|pertama|first)']
    tests:
      pass: ['milik default_server', 'other.test (default)']
      fail: ['dojo.test', 'semua sertifikat', 'tidak ada sertifikat']
    explanation: 'Di lab: tanpa SNI → `subject=CN=other.test` (blok `default_server`), dengan `-servername dojo.test` → `subject=CN=dojo.test`. Klien tua tanpa SNI, health check load balancer, dan scanner yang memakai IP melihat sertifikat default. Akses langsung lewat IP → `no alternative certificate subject name matches target ipv4 address` (tier B).'
    source: https://www.rfc-editor.org/rfc/rfc6066

  - id: b04
    tier: B
    type: text
    objective: errors
    prompt: Error curl untuk sertifikat yang `notAfter`-nya sudah lewat?
    answers: ['certificate has expired']
    patterns: ['certificate has expired', 'expired']
    tests:
      pass: ['SSL certificate problem: certificate has expired']
      fail: ['not yet valid', 'unable to get local issuer', 'self-signed']
    explanation: 'Di lab: sertifikat 2024-01-01 → 2025-01-01 → `curl: (60) SSL certificate problem: certificate has expired`, openssl `Verify return code: 10 (certificate has expired)`. Jam sistem yang salah (VM/container tanpa NTP) menghasilkan error yang sama, atau `not yet valid` untuk sertifikat baru, jadi cek `date` dulu.'
    source: https://docs.openssl.org/3.3/man1/openssl-verify/

  - id: b05
    tier: B
    type: text
    objective: errors
    prompt: Error curl untuk sertifikat **self-signed** (tidak ditandatangani CA yang dipercaya)?
    answers: ['self-signed certificate']
    patterns: ['self[- ]signed']
    tests:
      pass: ['SSL certificate problem: self-signed certificate', 'self signed certificate']
      fail: ['certificate has expired', 'unable to get local issuer', 'hostname mismatch']
    explanation: 'Di lab: `curl: (60) SSL certificate problem: self-signed certificate` (openssl: kode 18). Untuk layanan internal, lebih baik buat CA internal (seperti PKI lab) dan distribusikan root-nya ke klien. Satu root bisa menandatangani banyak sertifikat, dan rotasi leaf tidak perlu menyentuh klien.'
    source: https://curl.se/docs/sslcerts.html

  - id: b06
    tier: B
    type: text
    objective: names
    prompt: 'Klien mengakses `https://10.64.0.43/` (pakai IP). Sertifikat hanya berisi `DNS:...`. Error apa, dan apa yang dibutuhkan agar valid lewat IP?'
    answers: ['no alternative certificate subject name matches target ipv4 address; butuh SAN IP:10.64.0.43']
    patterns: ['(ip:|san ip|ip address|ipv4 address|ip san|iP SAN)']
    tests:
      pass: ['butuh SAN IP:10.64.0.43', 'no alternative ... ipv4 address, tambahkan ip ke SAN']
      fail: ['tambahkan cn ip', 'pakai wildcard', 'matikan sni']
    explanation: 'Di lab: `curl: (60) SSL: no alternative certificate subject name matches target ipv4 address ''10.64.0.43''`. Pencocokan IP memakai entry SAN tipe `IP:`, bukan `DNS:`. CA publik umumnya tidak menerbitkan sertifikat untuk IP privat. Untuk layanan internal yang diakses lewat IP, pakai CA internal dengan `subjectAltName=IP:10.64.0.43`.'
    source: https://www.rfc-editor.org/rfc/rfc5280

  - id: b07
    tier: B
    type: text
    objective: ops
    prompt: '`nginx -t` gagal dengan `SSL_CTX_use_PrivateKey(...) failed (... key values mismatch)`. Dua penyebab yang terbukti di lab?'
    answers: ['key bukan pasangan sertifikat, atau urutan fullchain salah (intermediate di depan)']
    patterns: ['(?=.*(key|kunci).*(bukan|tidak|beda|salah|lain|pasangan))(?=.*(urutan|order|intermediate.*(dulu|depan|pertama)|pertama))']
    tests:
      pass: ['key tidak cocok dengan sertifikat, atau urutan chain salah dengan intermediate di depan']
      fail: ['sertifikat kedaluwarsa', 'izin file salah', 'versi tls']
    explanation: 'Di lab, keduanya menghasilkan pesan yang sama:
      - `site.full.pem` + `other.key` → `key values mismatch`.
      - `wrongorder.pem` (intermediate → leaf) + `site.key` → `key values mismatch`, walaupun key-nya benar, karena sertifikat pertama dianggap leaf.

      Cek dengan membandingkan public key (soal berikutnya).'
    source: https://nginx.org/en/docs/http/ngx_http_ssl_module.html#ssl_certificate

  - id: b08
    tier: B
    type: text
    objective: ops
    prompt: Bagaimana memastikan `site.key` adalah pasangan `site.pem` tanpa memasangnya di server?
    answers: ['bandingkan hash public key dari keduanya']
    patterns: ['(public ?key|pubkey|pubout|modulus).*(sama|banding|cocok|hash|sha256)', '(banding|cocok|sama).*(public ?key|pubkey|pubout)']
    tests:
      pass: ['bandingkan sha256 public key: openssl x509 -pubkey vs openssl pkey -pubout', 'hash pubkey keduanya harus sama']
      fail: ['bandingkan nama file', 'cek tanggal', 'openssl verify']
    explanation: 'Di lab: `openssl x509 -in site.pem -noout -pubkey | sha256sum` → `e47d122a...`, `openssl pkey -in site.key -pubout | sha256sum` → `e47d122a...` (cocok), sedangkan `other.key` → `03596546...` (bukan pasangan). Cara ini bekerja untuk RSA maupun ECDSA. Cara lama `-modulus` hanya untuk RSA.'
    source: https://docs.openssl.org/3.3/man1/openssl-pkey/

  - id: b09
    tier: B
    type: text
    objective: handshake
    prompt: '`curl --tls-max 1.1` ke server dengan `ssl_protocols TLSv1.2 TLSv1.3`. Hasilnya?'
    answers: ['gagal handshake (no protocols available)']
    patterns: ['(no protocols available|gagal|error|handshake failure|protocol version|tidak (bisa|ada))']
    tests:
      pass: ['curl: (35) no protocols available', 'gagal handshake']
      fail: ['berhasil dengan tls 1.1', 'turun ke http', 'berhasil dengan tls 1.3']
    explanation: 'Di lab: `curl: (35) TLS connect error: error:0A0000BF:SSL routines::no protocols available`. OpenSSL 3 di sisi klien juga menolak TLS < 1.2 secara default di level keamanan default. Klien yang sangat lama (Java 7, Android 4) tidak bisa terhubung, dan itu harga yang wajar untuk keamanan.'
    source: https://www.rfc-editor.org/rfc/rfc8996

  - id: b10
    tier: B
    type: text
    objective: handshake
    prompt: Cipher TLS 1.2 di lab adalah `ECDHE-ECDSA-AES256-GCM-SHA384`. Sifat keamanan apa yang diberikan pertukaran kunci **ECDHE** (ephemeral)?
    answers: ['forward secrecy']
    patterns: ['(perfect )?forward secrecy', '\bpfs\b', 'kunci sesi.*(tidak|tak).*(bisa|dapat).*(didekripsi|dibuka)']
    tests:
      pass: ['perfect forward secrecy', 'PFS']
      fail: ['kompresi', 'autentikasi klien', 'integritas saja', 'kecepatan']
    explanation: 'Dengan ECDHE, setiap sesi memakai kunci sementara. Bila private key server bocor kelak, rekaman lalu lintas lama **tidak** bisa didekripsi. TLS 1.3 mewajibkan pertukaran kunci ephemeral (lab: `x25519`) dan menghapus RSA key exchange statis. `ECDSA` di nama cipher adalah jenis tanda tangan sertifikat (lab: kunci EC P-256).'
    source: https://www.rfc-editor.org/rfc/rfc8446

  - id: b11
    tier: B
    type: text
    objective: mtls
    prompt: mTLS diaktifkan. Klien mengirim sertifikat dari CA yang **tidak** dipercaya server (self-signed). Respons nginx?
    answers: ['400 The SSL certificate error']
    patterns: ['(400|ssl certificate error|ditolak)']
    tests:
      pass: ['400 The SSL certificate error', 'ditolak 400']
      fail: ['200', '403', 'No required SSL certificate was sent']
    explanation: 'Di lab: tanpa sertifikat → `400 No required SSL certificate was sent`, sertifikat self-signed → `400 The SSL certificate error`, `client1` dari CA lab → `mtls ok: CN=client1`. Dengan mTLS, identitas klien dibuktikan sebelum request HTTP pertama. Aplikasi bisa membaca `$ssl_client_s_dn` untuk otorisasi per klien.'
    source: https://nginx.org/en/docs/http/ngx_http_ssl_module.html#ssl_verify_client

  - id: b12
    tier: B
    type: text
    objective: handshake
    prompt: Variabel `curl -w` yang mengukur waktu sampai handshake **TLS** selesai?
    answers: ['time_appconnect', '%{time_appconnect}']
    patterns: ['time_appconnect']
    tests:
      pass: ['%{time_appconnect}']
      fail: ['time_connect', 'time_starttransfer', 'time_pretransfer', 'time_namelookup']
    explanation: 'Di lab (LAN): `connect=0.000217 appconnect=0.005481`, jadi TLS memakan ~5 ms dibanding TCP 0.2 ms. Di internet, TLS 1.3 butuh 1 RTT tambahan dan TLS 1.2 butuh 2 RTT. Itu alasan lain memakai keep-alive/pooling (modul 05): handshake dibayar sekali, bukan per request.'
    source: https://curl.se/docs/manpage.html#-w

  - id: b13
    tier: B
    type: text
    objective: mtls
    prompt: 'Apa risiko `Strict-Transport-Security: max-age=31536000; includeSubDomains` bila ada subdomain internal yang **belum** mendukung HTTPS?'
    answers: ['subdomain itu tidak bisa diakses lewat browser selama setahun']
    patterns: ['(tidak bisa|tak bisa|gagal|terblokir|diblokir).*(diakses|dibuka|akses)', '(setahun|1 tahun|max-age|lama)']
    tests:
      pass: ['subdomain http tidak bisa diakses browser sampai max-age habis', 'terblokir setahun']
      fail: ['tidak ada risiko', 'lebih cepat', 'cookie hilang']
    explanation: 'HSTS di-cache browser selama `max-age` (lab: 31536000 detik = 1 tahun), dan `includeSubDomains` memperluasnya ke **semua** subdomain. Subdomain yang hanya HTTP langsung ditolak, tanpa tombol bypass, dan tidak bisa ditarik kembali dari browser yang sudah menyimpannya. Mulai dengan `max-age` kecil, dan tambahkan `includeSubDomains`/`preload` setelah semua subdomain siap.'
    source: https://www.rfc-editor.org/rfc/rfc6797

  - id: b14
    tier: B
    type: text
    objective: trust
    prompt: Dalam rantai lab `Dojo Root CA → Dojo Intermediate CA → dojo.test`, kenapa CA memakai **intermediate** untuk menandatangani sertifikat server, bukan root langsung?
    answers: ['agar kunci root bisa disimpan offline; intermediate bisa dicabut/diganti tanpa mengganti root di semua klien']
    patterns: ['(offline|aman|terlindung|jarang dipakai|disimpan|risiko|bocor|compromise)', '(cabut|revoke|ganti|rotasi).*(intermediate|tanpa)']
    tests:
      pass: ['root key disimpan offline, intermediate bisa dicabut tanpa mengganti root', 'membatasi risiko bila kunci bocor']
      fail: ['lebih cepat', 'root tidak bisa menandatangani', 'agar sertifikat lebih kecil']
    explanation: 'Root tertanam di jutaan perangkat dan sulit diganti, sehingga kuncinya dijaga offline. Intermediate (lab: `basicConstraints=critical,CA:TRUE,pathlen:0`, yang berarti tidak boleh membuat CA lain di bawahnya) dipakai sehari-hari, dan bila bocor cukup dicabut. Konsekuensinya, server **wajib** mengirim intermediate (soal b01).'
    source: https://www.rfc-editor.org/rfc/rfc5280

  - id: b15
    tier: B
    type: text
    objective: names
    prompt: 'Sertifikat lab punya `extendedKeyUsage=serverAuth`, sedangkan sertifikat klien mTLS punya `clientAuth`. Apa fungsi Extended Key Usage?'
    answers: ['membatasi untuk apa sertifikat boleh dipakai (server vs klien)']
    patterns: ['(membatasi|batas|fungsi|kegunaan|tujuan|dipakai untuk|boleh dipakai|peran)']
    tests:
      pass: ['membatasi kegunaan sertifikat: autentikasi server atau klien', 'menentukan sertifikat boleh dipakai untuk apa']
      fail: ['enkripsi lebih kuat', 'masa berlaku', 'nama host']
    explanation: 'EKU mencegah sertifikat dipakai di luar perannya, misalnya sertifikat klien dipakai untuk menyamar sebagai server. Di lab, `client1` diterbitkan dengan `clientAuth` dan diterima nginx mTLS, sedangkan sertifikat server memakai `serverAuth` ("TLS Web Server Authentication"). CA publik untuk website hanya menerbitkan `serverAuth`.'
    source: https://www.rfc-editor.org/rfc/rfc5280

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: ops
    prompt: |
      Kamu menerima sertifikat baru dari CA untuk `dojo.example.com`: `cert.pem` (leaf), `chain.pem` (intermediate),
      dan `privkey.pem`. Tulis langkah memasangnya di nginx dengan aman, dari pemeriksaan sebelum pasang sampai
      verifikasi setelah reload dan pemantauan kedaluwarsa (satu perintah/poin per baris).
    checklist:
      - id: fullchain
        label: Membuat fullchain dengan urutan leaf → intermediate
        required: true
        patterns: ['cat\s+cert\.pem\s+chain\.pem', 'fullchain']
      - id: keymatch
        label: Mencocokkan public key sertifikat dengan private key
        required: true
        patterns: ['(pubkey|pubout)', 'modulus']
      - id: inspect
        label: Memeriksa SAN/tanggal sertifikat sebelum pasang
        patterns: ['-ext\s+subjectaltname', '-enddate', '-dates', '-text']
      - id: nginx-test
        label: nginx -t sebelum reload
        required: true
        patterns: ['nginx\s+-t']
      - id: reload
        label: Reload (bukan restart keras)
        patterns: ['nginx\s+-s\s+reload', 'systemctl\s+reload', 'reload']
      - id: verify
        label: Verifikasi live (s_client -servername / curl tanpa -k)
        required: true
        patterns: ['s_client\s+.*-servername', 'curl\s+(-\S+\s+)*https://dojo\.example\.com']
      - id: monitor
        label: Pemantauan kedaluwarsa (checkend / alert)
        patterns: ['checkend', '(monitor|alert|pantau)']
      - id: no-insecure
        label: Tidak memakai -k / verify off untuk "menguji"
        forbid: true
        patterns: ['curl\s+(-\S+\s+)*-k\b', '--insecure', 'verify\s*=\s*false']
    pass_score: 0.6
    solution: |
      ```bash
      openssl x509 -in cert.pem -noout -subject -issuer -dates -ext subjectAltName   # nama & tanggal benar?
      openssl verify -untrusted chain.pem cert.pem                                    # rantai lengkap (store sistem)
      [ "$(openssl x509 -in cert.pem -noout -pubkey | sha256sum)" = "$(openssl pkey -in privkey.pem -pubout | sha256sum)" ] && echo key-match
      cat cert.pem chain.pem > fullchain.pem                                          # leaf DULU, lalu intermediate
      install -m 600 privkey.pem /etc/nginx/tls/ && install -m 644 fullchain.pem /etc/nginx/tls/
      sudo nginx -t && sudo nginx -s reload
      echo | openssl s_client -connect dojo.example.com:443 -servername dojo.example.com 2>/dev/null | grep -E ' s:|Verify return code'
      curl -sS -o /dev/null -w '%{http_code}\n' https://dojo.example.com/                # tanpa -k
      echo | openssl s_client -connect dojo.example.com:443 -servername dojo.example.com 2>/dev/null | openssl x509 -noout -checkend 2592000 || alert
      ```
      Setiap langkah mencegah error yang direproduksi di lab:
      - Urutan salah atau key salah → `key values mismatch` di `nginx -t`.
      - Lupa intermediate → `unable to get local issuer certificate` (lab: s_client hanya depth 0, kode 21).
      - Nama salah → `no alternative certificate subject name matches`.
      - Lupa perpanjang → `certificate has expired`.

      `nginx -t` sebelum reload menjamin konfigurasi lama tetap jalan bila ada yang salah.
    tests:
      pass:
        - |
          openssl x509 -in cert.pem -noout -dates -ext subjectAltName
          openssl x509 -in cert.pem -noout -pubkey | sha256sum dibanding openssl pkey -in privkey.pem -pubout | sha256sum
          cat cert.pem chain.pem > fullchain.pem
          nginx -t
          nginx -s reload
          echo | openssl s_client -connect dojo.example.com:443 -servername dojo.example.com
          cron checkend 2592000 untuk alert
        - |
          cat cert.pem chain.pem > fullchain.pem
          bandingkan pubkey cert dan key
          nginx -t && systemctl reload nginx
          curl -sS https://dojo.example.com/
      fail:
        - |
          cat chain.pem cert.pem > fullchain.pem
          nginx -s reload
          curl -k https://dojo.example.com/
        - |
          cat cert.pem chain.pem > fullchain.pem
          cek pubkey
          nginx -t
          curl --insecure https://dojo.example.com/
        - |
          copy cert.pem ke nginx
          systemctl restart nginx
    source: https://nginx.org/en/docs/http/configuring_https_servers.html

  - id: c02
    tier: C
    type: scenario
    objective: errors
    prompt: |
      Setelah ganti sertifikat, website tetap terbuka di Chrome desktop, tetapi job Laravel yang memanggil API itu dengan
      Guzzle gagal (`cURL error 60: SSL certificate problem: unable to get local issuer certificate`), begitu pula
      aplikasi Android. Seorang developer mengusulkan `'verify' => false`. Tulis diagnosis dan perbaikan yang benar
      (satu poin/perintah per baris).
    checklist:
      - id: sclient
        label: Memeriksa rantai yang dikirim server (s_client -servername / -showcerts)
        required: true
        patterns: ['s_client']
      - id: diagnose
        label: Menyimpulkan intermediate tidak dikirim (hanya depth 0 / kode 21)
        required: true
        patterns: ['intermediate', 'chain (tidak|belum) lengkap', 'kode 21', 'depth 0']
      - id: browser
        label: Menjelaskan kenapa browser tetap jalan (cache/AIA)
        patterns: ['(cache|aia)']
      - id: fix
        label: Pasang fullchain (leaf + intermediate) di server
        required: true
        patterns: ['fullchain', 'cat\s+\S+\s+\S*(chain|intermediate)']
      - id: verify
        label: Verifikasi ulang tanpa menonaktifkan verifikasi
        patterns: ['verify return code: 0', 'curl\s+(-\S+\s+)*https://', 'openssl\s+verify']
      - id: no-disable
        label: Menolak verify=false / -k
        forbid: true
        patterns: ['^(?![^\n]*(jangan|tolak|bukan|hindari|tidak boleh))[^\n]*(pakai|gunakan|set|aktifkan|ubah ke)[^\n]*(verify[''" =>]*false|--insecure|curl -k)']
    pass_score: 0.6
    solution: |
      ```bash
      echo | openssl s_client -connect api.dojo.example.com:443 -servername api.dojo.example.com -showcerts 2>/dev/null \
        | grep -E '^ *[0-9] s:|Verify return code'
      #  0 s:CN=api.dojo.example.com        ← hanya leaf
      #  Verify return code: 21 (unable to verify the first certificate)
      ```
      Diagnosis: server hanya mengirim leaf. Chrome desktop melengkapi intermediate dari cache/AIA, sedangkan curl,
      Guzzle, OpenSSL, dan Android tidak. Lab: leaf-only → error yang sama, tetapi berhasil bila klien kebetulan
      memegang intermediate.

      Perbaikan di **server**, bukan di klien:
      ```bash
      cat cert.pem chain.pem > fullchain.pem      # ssl_certificate /etc/nginx/tls/fullchain.pem;
      nginx -t && nginx -s reload
      echo | openssl s_client -connect api.dojo.example.com:443 -servername api.dojo.example.com 2>/dev/null | grep 'Verify return code'   # 0 (ok)
      ```
      `'verify' => false` mematikan pemeriksaan identitas server untuk semua request job itu, sehingga MITM tidak
      terdeteksi, dan masalah sebenarnya (rantai tidak lengkap) tetap ada untuk semua klien lain.
    tests:
      pass:
        - |
          echo | openssl s_client -connect api.dojo.example.com:443 -servername api.dojo.example.com -showcerts
          hanya depth 0, intermediate tidak dikirim, verify return code 21
          chrome jalan karena intermediate di cache/AIA
          pasang fullchain: cat cert.pem chain.pem > fullchain.pem lalu reload nginx
          cek lagi verify return code: 0
          jangan pakai verify false
        - |
          openssl s_client -servername api.dojo.example.com -connect api.dojo.example.com:443
          rantai tidak lengkap, intermediate hilang
          ganti ssl_certificate ke fullchain
      fail:
        - |
          openssl s_client -connect api.dojo.example.com:443
          intermediate hilang
          sementara pakai verify false di guzzle
          fullchain
        - |
          update ca-certificates di semua klien
          restart job
        - |
          set 'verify' => false
    source: https://curl.se/docs/sslcerts.html

  - id: c03
    tier: C
    type: scenario
    objective: mtls
    prompt: |
      Dua layanan internal (`billing` memanggil `ledger`) harus saling terautentikasi tanpa password/API key, lewat
      jaringan yang dianggap tidak tepercaya. Rancang solusi mTLS dengan CA internal: komponen, sertifikat apa saja
      (dengan SAN/EKU), konfigurasi sisi server dan klien, dan rotasinya (satu poin per baris).
    checklist:
      - id: ca
        label: CA internal (root offline + intermediate)
        required: true
        patterns: ['(ca internal|internal ca|root ca|intermediate)']
      - id: server-cert
        label: Sertifikat server ledger dengan SAN nama layanan + EKU serverAuth
        required: true
        patterns: ['(subjectaltname|san).*ledger', 'serverauth']
      - id: client-cert
        label: Sertifikat klien billing dengan EKU clientAuth
        required: true
        patterns: ['clientauth']
      - id: server-cfg
        label: Server mewajibkan sertifikat klien (ssl_verify_client on + CA klien)
        required: true
        patterns: ['ssl_verify_client', 'ssl_client_certificate', 'verify.*client']
      - id: authz
        label: Otorisasi berdasar identitas sertifikat (DN/SAN klien)
        patterns: ['ssl_client_s_dn', '(cn|dn|san).*(klien|client|billing).*(izin|otorisasi|allow|cek)', 'otorisasi']
      - id: client-cfg
        label: Klien memverifikasi server dengan CA internal (cacert) dan mengirim cert+key
        patterns: ['--cacert', 'cacert', '--cert\s', 'cert.*key']
      - id: rotate
        label: Masa berlaku pendek + rotasi otomatis sebelum habis
        patterns: ['(rotasi|rotate|perpanjang|renew)', '(pendek|short|hari|days|checkend)']
      - id: no-insecure
        label: Tidak mematikan verifikasi di sisi mana pun
        forbid: true
        patterns: ['curl\s+(-\S+\s+)*-k\b', '--insecure', 'ssl_verify_client\s+off', 'verify\s*=\s*false']
    pass_score: 0.5
    solution: |
      ```text
      CA      : root "Dojo Internal Root" (kunci offline) → intermediate "Dojo Services CA" (pathlen:0) untuk menandatangani
      Server  : ledger.svc.internal — SAN DNS:ledger.svc.internal, EKU serverAuth, berlaku 30–90 hari
      Klien   : billing — CN/SAN billing, EKU clientAuth, berlaku 30–90 hari
      ledger (nginx):
        ssl_certificate     ledger.fullchain.pem;  ssl_certificate_key ledger.key;
        ssl_client_certificate services-ca.pem;    ssl_verify_client on;  ssl_verify_depth 2;
        if ($ssl_client_s_dn !~ "CN=billing") { return 403; }      # otorisasi per layanan
      billing (klien):
        curl --cacert internal-root.pem --cert billing.fullchain.pem --key billing.key https://ledger.svc.internal/
      Rotasi  : terbitkan ulang otomatis (mis. cron/agent) saat sisa < 1/3 masa berlaku; pantau dengan -checkend;
                rotasi CA intermediate dengan periode dua CA dipercaya bersamaan
      ```
      Pola ini identik dengan lab:
      - PKI dua tingkat.
      - `client1` (EKU `clientAuth`) diterima → `mtls ok: CN=client1`.
      - Tanpa sertifikat → `400 No required SSL certificate was sent`.
      - Sertifikat dari CA lain → `400 The SSL certificate error`.

      Autentikasi (sertifikat valid) belum otorisasi. Batasi layanan mana yang boleh memanggil endpoint mana
      berdasarkan DN/SAN klien. Service mesh (Istio/Linkerd) mengotomasi persis pola ini.
    tests:
      pass:
        - |
          ca internal: root offline dan intermediate untuk menandatangani
          sertifikat server ledger dengan subjectAltName DNS:ledger.svc.internal dan EKU serverAuth
          sertifikat klien billing dengan EKU clientAuth
          nginx ledger: ssl_client_certificate services-ca.pem dan ssl_verify_client on
          otorisasi cek $ssl_client_s_dn harus CN=billing
          billing: curl --cacert internal-root.pem --cert billing.pem --key billing.key
          masa berlaku pendek 30 hari dan rotasi otomatis
        - |
          intermediate ca internal
          san ledger, serverAuth
          clientAuth untuk billing
          ssl_verify_client on
      fail:
        - |
          ca internal
          san ledger serverAuth
          clientAuth billing
          ssl_verify_client on
          sementara klien pakai curl -k
        - |
          pakai api key di header
          https biasa
        - |
          self-signed di kedua sisi
          ssl_verify_client off
    source: https://nginx.org/en/docs/http/ngx_http_ssl_module.html#ssl_verify_client

---

# TLS & Sertifikat

TLS menjawab dua pertanyaan: apakah percakapan ini rahasia, dan apakah aku benar-benar bicara dengan server yang
kumaksud. Jawaban kedua bergantung pada sertifikat dan rantai kepercayaan, yang juga merupakan sumber hampir semua
error HTTPS. Modul ini membangun PKI kecil sendiri, lalu sengaja memancing setiap error yang umum.

> [!NOTE]
> Lab: OpenSSL 3.3.7 membuat Root CA → Intermediate CA → sertifikat leaf ECDSA P-256 (normal, wildcard,
> kedaluwarsa, self-signed, klien). Satu nginx 1.27.5 melayani semuanya di 443 dengan SNI (plus 8443 leaf-only dan
> 9443 mTLS). Diuji dengan curl dan `openssl s_client`.

## 1. Rantai kepercayaan

```mermaid
flowchart TD
    R["Dojo Root CA<br/>(self-signed, ada di trust store klien, kunci offline)"] -->|menandatangani| I["Dojo Intermediate CA<br/>CA:TRUE, pathlen:0"]
    I -->|menandatangani| L["dojo.test<br/>SAN: dojo.test, www.dojo.test<br/>EKU serverAuth, 90 hari"]
    S["server mengirim:<br/>leaf + intermediate (fullchain)"] -.-> L
```

```text
$ echo | openssl s_client -connect 10.64.0.43:443 -servername dojo.test -CAfile root.pem
 0 s:CN=dojo.test
   i:CN=Dojo Intermediate CA
 1 s:CN=Dojo Intermediate CA
   i:CN=Dojo Root CA
New, TLSv1.3, Cipher is TLS_AES_256_GCM_SHA384
Verify return code: 0 (ok)
```

Klien memverifikasi bahwa setiap sertifikat ditandatangani yang di atasnya, sampai ke root yang ia percaya.
Kemudian ia memeriksa nama (SAN), tanggal, dan kegunaan (EKU).

## 2. Membuat CA dan sertifikat (ringkas)

```bash
# root
openssl req -x509 -newkey ec -pkeyopt ec_paramgen_curve:P-256 -nodes -keyout root.key -out root.pem -days 3650 \
  -subj "/CN=Dojo Root CA" -addext "basicConstraints=critical,CA:TRUE" -addext "keyUsage=critical,keyCertSign,cRLSign"
# leaf: CSR lalu tanda tangani dengan intermediate + extension
openssl req -newkey ec -pkeyopt ec_paramgen_curve:P-256 -nodes -keyout site.key -out site.csr -subj "/CN=dojo.test"
printf "basicConstraints=CA:FALSE\nkeyUsage=critical,digitalSignature\nextendedKeyUsage=serverAuth\nsubjectAltName=DNS:dojo.test,DNS:www.dojo.test\n" > site.ext
openssl x509 -req -in site.csr -CA int.pem -CAkey int.key -CAcreateserial -out site.pem -days 90 -extfile site.ext
cat site.pem int.pem > site.full.pem
```

## 3. Error yang umum, dengan pesan aslinya

| Kasus (lab) | curl | `openssl s_client` | Perbaikan |
|---|---|---|---|
| Leaf tanpa intermediate | `(60) unable to get local issuer certificate` | `21 (unable to verify the first certificate)` | Pasang fullchain |
| CA privat tidak dipercaya | `(60) unable to get local issuer certificate` | `20` | `--cacert` / pasang root ke store |
| Nama tidak ada di SAN | `(60) no alternative certificate subject name matches target hostname` | `62 (hostname mismatch)` | Terbitkan ulang dengan SAN benar |
| Akses lewat IP | `... matches target ipv4 address '10.64.0.43'` | — | SAN `IP:` atau pakai nama |
| Kedaluwarsa | `(60) certificate has expired` | `10` | Perpanjang. Cek jam sistem |
| Self-signed | `(60) self-signed certificate` | `18` | CA internal |
| Key tidak cocok / urutan salah | — | — | `nginx -t`: `key values mismatch` |
| TLS terlalu lama | `(35) no protocols available` | — | Klien ≥ TLS 1.2 |

> [!WARNING]
> **Browser bukan alat uji.** Di lab, leaf-only gagal di curl, tetapi berhasil begitu klien kebetulan memegang
> intermediate, persis seperti browser desktop yang menyimpan intermediate di cache atau mengunduhnya lewat AIA. Uji
> dengan `openssl s_client` dan curl.

## 4. Nama: SAN dan wildcard

| Sertifikat | `x.apps.dojo.test` | `foo.bar.apps.dojo.test` | `apps.dojo.test` |
|---|---|---|---|
| `*.apps.dojo.test` | ✅ | ❌ | ❌ |

Wildcard TLS = tepat satu label. Untuk apex, tambahkan SAN kedua. Bandingkan dengan wildcard DNS (modul 04), yang
mencocokkan banyak label.

## 5. Handshake: SNI, ALPN, versi

```text
* ALPN: curl offers h2,http/1.1
* SSL connection using TLSv1.3 / TLS_AES_256_GCM_SHA384 / x25519 / id-ecPublicKey
* ALPN: server accepted h2
*  subjectAltName: host "dojo.test" matched cert's "dojo.test"
connect=0.000217  appconnect=0.005481   ← biaya handshake TLS
```

- SNI memilih sertifikat. Tanpa SNI → sertifikat `default_server` (lab: `CN=other.test`).
- ALPN memilih protokol aplikasi (`h2`).
- `ssl_protocols TLSv1.2 TLSv1.3;`. Lab: TLS 1.3 default, TLS 1.2 dengan ECDHE (forward secrecy).

## 6. Operasional di nginx

```nginx
server {
    listen 443 ssl;
    http2 on;
    server_name dojo.test www.dojo.test;
    ssl_certificate     /etc/nginx/tls/fullchain.pem;   # leaf → intermediate
    ssl_certificate_key /etc/nginx/tls/privkey.pem;     # chmod 600
    ssl_protocols TLSv1.2 TLSv1.3;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
}
```

```bash
openssl x509 -in cert.pem -noout -pubkey | sha256sum ; openssl pkey -in privkey.pem -pubout | sha256sum   # harus sama
nginx -t && nginx -s reload
openssl x509 -in cert.pem -noout -checkend 2592000 || echo "habis < 30 hari"
```

## 7. mTLS dan HSTS

| mTLS (lab) | Hasil |
|---|---|
| Tanpa sertifikat klien | `400 No required SSL certificate was sent` |
| Sertifikat dari CA lain | `400 The SSL certificate error` |
| `client1` (EKU clientAuth, CA lab) | `mtls ok: CN=client1` |

HSTS: setelah dilihat browser, domain hanya bisa diakses lewat HTTPS selama `max-age`. `includeSubDomains` berlaku ke
semua subdomain. Naikkan bertahap.

## 8. Ringkasan

| Pertanyaan | Alat |
|---|---|
| Sertifikat apa yang dikirim server? | `openssl s_client -connect host:443 -servername host -showcerts` |
| Rantai valid? | `Verify return code: 0`, `openssl verify -untrusted` |
| Nama dan tanggal? | `openssl x509 -noout -ext subjectAltName -dates` |
| Key cocok? | Bandingkan hash public key |
| Akan habis? | `openssl x509 -checkend` |
| Biaya handshake? | `curl -w '%{time_appconnect}'` |
