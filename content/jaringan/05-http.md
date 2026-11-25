---
format: 1
id: net-http
course:
  slug: jaringan
  title: Jaringan
  description: Konsep jaringan untuk DevOps — TCP/IP, subnetting, routing, TCP/UDP, DNS, HTTP, TLS, proxy, firewall, VPN, dan troubleshooting.
title: HTTP
summary: HTTP sebagai protokol teks, method dan status code, redirect (301/302/307/308 dan efeknya pada POST), virtual host lewat Host, caching (Cache-Control, ETag, 304), kompresi dan Vary, keep-alive dan HTTP/2, range request, cookie, CORS, serta membaca 502 vs 504 — dibuktikan dengan nginx, backend Python, dan curl.
order: 5
version: 1
status: verified
updated: '2026-11-24'
tested_on:
  - Docker Desktop (Windows) — nginx 1.27.5, backend python:3.12-alpine (http.server), klien alpine:3.20 + curl
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: protocol
    text: Memahami struktur request/response HTTP dan header Host
  - id: status
    text: Membaca status code dan memetakannya ke penyebab
  - id: redirect
    text: Memilih jenis redirect yang tepat
  - id: caching
    text: Mengatur caching (Cache-Control, ETag, 304, Vary)
  - id: connection
    text: Memahami keep-alive, HTTP/2, kompresi, dan range
  - id: browser
    text: Memahami cookie dan CORS dari sisi server
  - id: debug
    text: Mendiagnosis error proxy (502/504) dan latency (TTFB)
sources:
  - title: RFC 9110 — HTTP Semantics (methods, status codes, redirects, conditional, range)
    url: https://www.rfc-editor.org/rfc/rfc9110
  - title: RFC 9111 — HTTP Caching
    url: https://www.rfc-editor.org/rfc/rfc9111
  - title: RFC 9112 — HTTP/1.1 (message syntax, Host, chunked)
    url: https://www.rfc-editor.org/rfc/rfc9112
  - title: RFC 9113 — HTTP/2
    url: https://www.rfc-editor.org/rfc/rfc9113
  - title: RFC 6265 — HTTP State Management (cookies)
    url: https://www.rfc-editor.org/rfc/rfc6265
  - title: MDN — Cross-Origin Resource Sharing (CORS)
    url: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
  - title: MDN — Cache-Control
    url: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control
  - title: nginx — ngx_http_proxy_module (proxy_set_header, timeouts)
    url: https://nginx.org/en/docs/http/ngx_http_proxy_module.html
  - title: nginx — ngx_http_core_module (server_name, default_server, client_max_body_size)
    url: https://nginx.org/en/docs/http/ngx_http_core_module.html
  - title: nginx — ngx_http_v2_module (http2 on)
    url: https://nginx.org/en/docs/http/ngx_http_v2_module.html
  - title: curl man page (-w variables, --resolve, -r)
    url: https://curl.se/docs/manpage.html
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: protocol
    prompt: Header request apa yang **wajib** ada di HTTP/1.1, dan dipakai server untuk memilih virtual host?
    answers: ['Host']
    tests:
      fail: ['User-Agent', 'Accept', 'Connection', 'Origin']
    explanation: 'Di lab, request mentah lewat `nc` dengan `Host: dojo.test` → `HTTP/1.1 200 OK`, sedangkan tanpa Host → `HTTP/1.1 400 Bad Request`. Satu IP bisa melayani banyak situs karena server membaca Host. Di HTTP/2, perannya digantikan pseudo-header `:authority`.'
    source: https://www.rfc-editor.org/rfc/rfc9112

  - id: a02
    tier: A
    type: text
    objective: caching
    prompt: Status code yang berarti "isi tidak berubah sejak versi yang kamu punya, pakai cache-mu"?
    answers: ['304', '304 Not Modified']
    patterns: ['^304( not modified)?$']
    tests:
      pass: ['304 Not Modified']
      fail: ['200', '204', '301', '412']
    explanation: 'Di lab, `If-None-Match: "6b051144-22bd"` (ETag saat ini) → `304`, `body=0B`. Dengan ETag yang salah → `200`, `body=8893B`. 304 menghemat bandwidth, tetapi tetap butuh satu round-trip, berbeda dengan cache `max-age` yang tidak bertanya sama sekali.'
    source: https://www.rfc-editor.org/rfc/rfc9110

  - id: a03
    tier: A
    type: text
    objective: status
    prompt: '`POST` ke file statis di nginx (`/data.txt`) menghasilkan status apa?'
    answers: ['405', '405 Method Not Allowed']
    patterns: ['^405( method not allowed| not allowed)?$']
    tests:
      pass: ['405 Not Allowed']
      fail: ['403', '404', '400', '501']
    explanation: 'Di lab: `POST static 405`. Resource ada, tetapi method itu tidak didukung di sana. Bedakan dengan 404 (resource tidak ada, lab: `/nope`), 403 (dilarang), dan 501 (method tidak dikenal server sama sekali). Di API, 405 sering berarti route ada tetapi untuk method lain.'
    source: https://www.rfc-editor.org/rfc/rfc9110

  - id: a04
    tier: A
    type: text
    objective: status
    prompt: Direktif nginx yang membatasi ukuran body request (di atasnya nginx membalas 413)?
    answers: ['client_max_body_size']
    tests:
      fail: ['client_body_buffer_size', 'max_body_size', 'upload_max_filesize', 'proxy_buffer_size']
    explanation: 'Di lab, dengan `client_max_body_size 1k`: upload 2 KB → `413`, 500 B → `200`. Default nginx **1 MB**. Upload Laravel butuh tiga batas yang selaras: `client_max_body_size` (nginx), serta `upload_max_filesize` dan `post_max_size` (PHP). Mana pun yang terkecil yang menang.'
    source: https://nginx.org/en/docs/http/ngx_http_core_module.html#client_max_body_size

  - id: a05
    tier: A
    type: text
    objective: debug
    prompt: nginx sebagai proxy, upstream **menolak koneksi** (tidak ada yang listen di port-nya). Status apa yang diterima klien?
    answers: ['502', '502 Bad Gateway']
    patterns: ['^502( bad gateway)?$']
    tests:
      pass: ['502 Bad Gateway']
      fail: ['504', '503', '500', '404']
    explanation: 'Di lab: `refused upstream 502 in 0.0018s`, dengan error log `connect() failed (111: Connection refused) while connecting to upstream`. 502 = proxy berhasil bertanya, tetapi jawaban dari upstream tidak valid atau tidak ada (refused, crash, reset). Waktu yang sangat cepat adalah petunjuk: penolakan langsung, bukan menunggu.'
    source: https://www.rfc-editor.org/rfc/rfc9110

  - id: a06
    tier: A
    type: text
    objective: debug
    prompt: Upstream tidak menjawab dalam batas waktu proxy (koneksi atau respons terlalu lama). Status apa yang diterima klien?
    answers: ['504', '504 Gateway Timeout']
    patterns: ['^504( gateway time-?out)?$']
    tests:
      pass: ['504 Gateway Timeout']
      fail: ['502', '408', '503', '500']
    explanation: 'Di lab, dua penyebab berbeda menghasilkan `504` dalam ~2 detik:
      - Upstream IP yang tidak ada → `upstream timed out ... while connecting to upstream`.
      - Backend yang tidur 5 detik dengan `proxy_read_timeout 2s` → `... while reading response header from upstream`.

      Frasa di error log membedakan keduanya (tier B).'
    source: https://nginx.org/en/docs/http/ngx_http_proxy_module.html

  - id: a07
    tier: A
    type: command
    objective: protocol
    prompt: Dengan curl, kirim request ke `http://10.63.0.80/` dengan header `Host` diisi `other.test`.
    answers: ['curl -H "Host: other.test" http://10.63.0.80/']
    patterns: ['^curl (-s )?(-H|--header) ?Host: ?other\.test (-s )?http://10\.63\.0\.80/?$']
    tests:
      pass: ['curl -s -H "Host: other.test" http://10.63.0.80/', 'curl --header "Host:other.test" http://10.63.0.80']
      fail: ['curl http://other.test/', 'curl -H "Origin: other.test" http://10.63.0.80/', 'curl --host other.test http://10.63.0.80/']
    explanation: 'Di lab → `other vhost`, karena nginx memilih `server_name other.test` dari Host, padahal IP-nya sama. Cara ini berguna untuk menguji situs sebelum DNS-nya diarahkan. Untuk HTTPS, header Host saja tidak cukup karena SNI TLS ikut dikirim. Pakai `--resolve` (soal berikutnya).'
    source: https://nginx.org/en/docs/http/request_processing.html

  - id: a08
    tier: A
    type: command
    objective: protocol
    prompt: Dengan curl, akses `http://other.test/` tetapi paksa nama `other.test` port 80 diarahkan ke IP `10.63.0.80` (tanpa mengubah DNS atau `/etc/hosts`).
    answers: ['curl --resolve other.test:80:10.63.0.80 http://other.test/']
    patterns: ['^curl (-s )?--resolve[= ]other\.test:80:10\.63\.0\.80 (-s )?http://other\.test/?$']
    tests:
      pass: ['curl -s --resolve other.test:80:10.63.0.80 http://other.test/', 'curl --resolve=other.test:80:10.63.0.80 http://other.test']
      fail: ['curl -H "Host: other.test" http://10.63.0.80/', 'curl --resolve other.test:10.63.0.80 http://other.test/', 'curl --connect-to other.test http://other.test/']
    explanation: 'Di lab → `other vhost`. `--resolve host:port:ip` mengganti hasil DNS hanya untuk perintah itu, sehingga Host, SNI, dan validasi sertifikat semuanya memakai nama asli. Ini cara paling akurat untuk menguji server baru sebelum migrasi DNS (modul 04, studi kasus migrasi).'
    source: https://curl.se/docs/manpage.html#--resolve

  - id: a09
    tier: A
    type: command
    objective: connection
    prompt: Dengan curl, ambil hanya **byte 0 sampai 9** dari `http://10.63.0.80/data.txt`.
    answers: ['curl -r 0-9 http://10.63.0.80/data.txt']
    patterns: ['^curl (-s )?(-r ?0-9|--range[= ]0-9|-H Range: ?bytes=0-9) (-s )?http://10\.63\.0\.80/data\.txt$']
    tests:
      pass: ['curl -s --range 0-9 http://10.63.0.80/data.txt', 'curl -H "Range: bytes=0-9" http://10.63.0.80/data.txt']
      fail: ['curl --max-filesize 10 http://10.63.0.80/data.txt', 'curl -r 10 http://10.63.0.80/data.txt', 'curl http://10.63.0.80/data.txt | head -c 10']
    explanation: 'Di lab → `HTTP/1.1 206 Partial Content`, `Content-Range: bytes 0-9/8893`, body 10 byte. Server mengiklankan dukungan lewat `Accept-Ranges: bytes`. Range dipakai untuk melanjutkan unduhan yang putus (`curl -C -`) dan seek video. `head -c` tetap mengunduh lebih banyak dari yang dibutuhkan.'
    source: https://www.rfc-editor.org/rfc/rfc9110

  - id: a10
    tier: A
    type: text
    objective: connection
    prompt: Status code untuk respons yang hanya berisi **sebagian** resource (hasil Range request)?
    answers: ['206', '206 Partial Content']
    patterns: ['^206( partial content)?$']
    tests:
      pass: ['206 Partial Content']
      fail: ['200', '204', '416', '304']
    explanation: 'Di lab: `HTTP/1.1 206 Partial Content`. Bila range di luar ukuran file → `416 Range Not Satisfiable`. Bila server tidak mendukung range, ia membalas `200` dengan file penuh, jadi klien harus memeriksa status sebelum menyambung data.'
    source: https://www.rfc-editor.org/rfc/rfc9110

  - id: a11
    tier: A
    type: text
    objective: connection
    prompt: Header **respons** yang menyatakan body sudah dikompresi (mis. gzip)?
    answers: ['Content-Encoding']
    tests:
      fail: ['Accept-Encoding', 'Transfer-Encoding', 'Content-Type', 'Compression']
    explanation: 'Klien menawarkan lewat `Accept-Encoding: gzip`, dan server menjawab dengan `Content-Encoding: gzip`. Di lab, `data.txt` 8893 B → **3750 B** dengan gzip. Tanpa `Accept-Encoding`, nginx mengirim versi asli. `Transfer-Encoding` adalah hal lain: cara pengiriman (chunked), bukan isi.'
    source: https://www.rfc-editor.org/rfc/rfc9110

  - id: a12
    tier: A
    type: text
    objective: caching
    prompt: Header respons yang memberi tahu cache bahwa isi respons bergantung pada header request tertentu (mis. `Accept-Encoding`)?
    answers: ['Vary']
    tests:
      fail: ['ETag', 'Cache-Control', 'Content-Encoding', 'Accept']
    explanation: 'Di lab: `Vary: Accept-Encoding` (dari `gzip_vary on`). Tanpa Vary, CDN atau proxy bisa menyimpan versi gzip lalu mengirimkannya ke klien yang tidak mendukung gzip, atau sebaliknya. `Vary: Cookie`/`Authorization` mencegah respons milik user tertentu dibagikan ke orang lain.'
    source: https://www.rfc-editor.org/rfc/rfc9111

  - id: a13
    tier: A
    type: text
    objective: caching
    prompt: Nilai `Cache-Control` yang melarang respons **disimpan** di cache mana pun (browser maupun proxy)?
    answers: ['no-store']
    tests:
      fail: ['no-cache', 'private', 'max-age=0', 'must-revalidate']
    explanation: 'Di lab, `/api/` → `Cache-Control: no-store`. Jebakan umum: `no-cache` **bukan** berarti tidak di-cache. Artinya "boleh disimpan, tetapi wajib divalidasi ulang (ETag/304) sebelum dipakai". `private` = hanya browser, bukan CDN/proxy bersama. Data sensitif (token, profil) memakai `no-store`.'
    source: https://www.rfc-editor.org/rfc/rfc9111

  - id: a14
    tier: A
    type: text
    objective: browser
    prompt: Method HTTP yang dipakai browser untuk **preflight** CORS sebelum request lintas origin yang "tidak sederhana"?
    answers: ['OPTIONS']
    tests:
      fail: ['HEAD', 'GET', 'CONNECT', 'TRACE']
    explanation: 'Di lab: `OPTIONS /cors` dengan `Origin` dan `Access-Control-Request-Method: PUT` → `204`, `Access-Control-Allow-Origin: https://app.dojo.test`, `Allow-Methods: GET, POST, PUT`, `Max-Age: 600`. Request dengan `PUT`/`DELETE`, header `Authorization`, atau JSON `Content-Type` memicu preflight. Firewall/WAF yang memblokir OPTIONS merusak CORS.'
    source: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS

  - id: a15
    tier: A
    type: text
    objective: browser
    prompt: Atribut cookie yang mencegah cookie dibaca oleh JavaScript (`document.cookie`)?
    answers: ['HttpOnly']
    tests:
      fail: ['Secure', 'SameSite', 'Path', 'Domain']
    explanation: 'Di lab: `Set-Cookie: session=abc123; Path=/; HttpOnly; Secure; SameSite=Lax`. `HttpOnly` meredam pencurian session lewat XSS, `Secure` = hanya dikirim lewat HTTPS (tier B), dan `SameSite=Lax` = tidak dikirim di sebagian besar request lintas situs (meredam CSRF). Cookie session Laravel memakai ketiganya bila dikonfigurasi benar.'
    source: https://www.rfc-editor.org/rfc/rfc6265

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: redirect
    prompt: '`curl -L -d x=1 /r301` (POST) mengikuti redirect **301** ke `/echo`. Method dan body apa yang diterima `/echo`?'
    answers: ['GET tanpa body']
    patterns: ['get']
    tests:
      pass: ['GET, body hilang', 'berubah jadi GET']
      fail: ['POST dengan body', 'PUT', 'POST']
    explanation: 'Di lab: `r301 → {"method": "GET", ..., "body_len": 0}`, dan sama untuk 302. Karena alasan historis, klien boleh (dan browser maupun curl memang) mengubah POST menjadi GET pada 301/302, sehingga body hilang. Redirect HTTP→HTTPS dengan 301 untuk endpoint API POST membuat data klien hilang tanpa error.'
    source: https://www.rfc-editor.org/rfc/rfc9110

  - id: b02
    tier: B
    type: text
    objective: redirect
    prompt: Status redirect **permanen** yang menjamin method dan body (POST) tetap dipertahankan?
    answers: ['308', '308 Permanent Redirect']
    patterns: ['^308( permanent redirect)?$']
    tests:
      pass: ['308 Permanent Redirect']
      fail: ['301', '307', '302', '303']
    explanation: 'Di lab: `r307` dan `r308` → `{"method": "POST", "body_len": 3}`. 307 = sementara, 308 = permanen, dan keduanya mempertahankan method. 303 See Other sengaja mengubah ke GET, yang tepat untuk pola Post/Redirect/Get setelah submit form. Ringkasnya: 301/302 ambigu, 303 paksa GET, 307/308 pertahankan.'
    source: https://www.rfc-editor.org/rfc/rfc9110

  - id: b03
    tier: B
    type: text
    objective: connection
    prompt: 'Respons gzip dari nginx tidak punya `Content-Length`. Header apa yang menggantikannya karena ukuran akhir belum diketahui saat mulai mengirim?'
    answers: ['Transfer-Encoding: chunked']
    patterns: ['(transfer-encoding|chunked)']
    tests:
      pass: ['transfer-encoding chunked', 'chunked']
      fail: ['content-encoding', 'content-range', 'connection: close', 'vary']
    explanation: 'Di lab: tanpa gzip → `Content-Length: 8893`. Dengan gzip → `Content-Encoding: gzip` + `Transfer-Encoding: chunked`. nginx mengompres sambil mengirim, sehingga body dikirim dalam potongan dengan ukuran per potongan. HTTP/2 tidak memakai chunked, karena framing-nya sudah menangani hal itu.'
    source: https://www.rfc-editor.org/rfc/rfc9112

  - id: b04
    tier: B
    type: text
    objective: connection
    prompt: '`curl -v` dengan dua URL ke host yang sama menampilkan `Re-using existing http: connection` untuk request kedua. Apa yang terjadi bila kamu menambahkan `-H "Connection: close"`?'
    answers: ['setiap request membuka koneksi TCP baru']
    patterns: ['(koneksi (tcp )?baru|connect(ed)? (lagi|ulang|dua kali)|tidak (di)?pakai ulang|new connection|handshake (lagi|ulang))']
    tests:
      pass: ['setiap request koneksi tcp baru', 'connected dua kali, tidak dipakai ulang']
      fail: ['tetap re-using', 'error', 'http/2']
    explanation: 'Di lab: default → satu `Connected to` lalu `Re-using existing ... connection`. Dengan `Connection: close` → dua kali `Connected to`. Keep-alive menghemat handshake TCP (dan TLS) per request. Tanpa itu, klien yang banyak request menumpuk TIME_WAIT dan bisa kehabisan port (modul 03).'
    source: https://www.rfc-editor.org/rfc/rfc9112

  - id: b05
    tier: B
    type: text
    objective: connection
    prompt: Apakah browser mendukung HTTP/2 di atas TCP **tanpa TLS** (h2c)? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab, h2c hanya bisa diakses dengan `curl --http2-prior-knowledge` ke port `listen 81` + `http2 on` → `HTTP/2 200` dengan header huruf kecil (`server:`, `content-type:`). Browser hanya memakai HTTP/2 lewat TLS, dengan negosiasi ALPN `h2` (modul TLS). Di port 80, curl biasa tetap `http_version 1.1`.'
    source: https://www.rfc-editor.org/rfc/rfc9113

  - id: b06
    tier: B
    type: text
    objective: protocol
    prompt: 'Request dengan `Host: random.example` (tidak cocok dengan `server_name` mana pun) ke nginx dijawab `200`. Server block mana yang melayaninya?'
    answers: ['default_server (server block default untuk listen itu)']
    patterns: ['default[_ ]?server', '(pertama|first|default)']
    tests:
      pass: ['default_server', 'server block default']
      fail: ['other.test', 'tidak ada, 404', 'error 400']
    explanation: 'Di lab: `unknown host -> 200 (default_server)`. Tanpa `default_server` eksplisit, nginx memakai server block **pertama** untuk listen itu. Siapa pun yang mengarahkan domain mereka ke IP-mu, atau scanner yang memakai IP langsung, mendapat situs utamamu. Pola aman: `server { listen 80 default_server; return 444; }`.'
    source: https://nginx.org/en/docs/http/request_processing.html

  - id: b07
    tier: B
    type: text
    objective: browser
    prompt: Server mengirim `Set-Cookie ... Secure` lewat **HTTP biasa** (bukan HTTPS). Apakah curl (seperti browser) menyimpannya di cookie jar? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab, header `Set-Cookie: session=abc123; ... Secure ...` terlihat, tetapi `grep -c session /tmp/jar` → `0`. Cookie `Secure` hanya diterima dan dikirim lewat HTTPS. Aplikasi di belakang proxy TLS yang mengira dirinya HTTP (proxy header tidak diteruskan, modul 07) bisa gagal login karena cookie-nya ditolak.'
    source: https://www.rfc-editor.org/rfc/rfc6265

  - id: b08
    tier: B
    type: text
    objective: browser
    prompt: '`curl -H "Origin: https://evil.test" http://.../cors` tetap mendapat body `cors ok`. Apakah CORS melindungi API dari klien non-browser seperti curl atau server lain? (ya/tidak)'
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab, server tetap menjawab, dan header `Access-Control-Allow-Origin` hanyalah **instruksi untuk browser** agar tidak memberikan respons ke JavaScript dari origin lain. CORS melindungi pengguna browser, bukan API-mu. Autentikasi, otorisasi, dan rate limiting tetap wajib. Kebalikannya juga berlaku: `Access-Control-Allow-Origin: *` + kredensial membuka data user ke situs mana pun.'
    source: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS

  - id: b09
    tier: B
    type: text
    objective: debug
    prompt: 'Error log nginx `upstream timed out (110) while reading response header from upstream` berarti masalahnya di mana?'
    answers: ['aplikasi upstream terlalu lambat menjawab (koneksi sudah berhasil)']
    patterns: ['(lambat|slow|lama|terlalu lama|aplikasi|query|proses)']
    tests:
      pass: ['aplikasi upstream lambat', 'koneksi berhasil tapi app lama menjawab']
      fail: ['upstream tidak bisa dijangkau', 'dns gagal', 'port tertutup', 'firewall']
    explanation: 'Di lab, backend yang tidur 5 detik dengan `proxy_read_timeout 2s` → frasa ini, dan `504` dalam 2.0 detik. Bandingkan `... while connecting to upstream` (IP tidak ada → tidak bisa terhubung) dan `connect() failed (111: Connection refused)` (port tertutup → 502). Frasa terakhir di log menunjuk fase yang gagal: connecting, sending, atau reading.'
    source: https://nginx.org/en/docs/http/ngx_http_proxy_module.html#proxy_read_timeout

  - id: b10
    tier: B
    type: text
    objective: debug
    prompt: Variabel `curl -w` yang mengukur waktu sampai **byte pertama** respons diterima (TTFB)?
    answers: ['time_starttransfer', '%{time_starttransfer}']
    patterns: ['time_starttransfer']
    tests:
      pass: ['%{time_starttransfer}']
      fail: ['time_total', 'time_connect', 'time_appconnect', 'time_namelookup']
    explanation: 'Di lab, endpoint yang tidur 1 detik: `connect=0.000167s ttfb=1.001695s total=1.001763s`. Koneksi instan dan TTFB 1 detik berarti waktunya habis di server/aplikasi, bukan di jaringan. Sebaliknya, `time_connect` besar berarti jaringan atau handshake. `time_total − time_starttransfer` = durasi transfer body.'
    source: https://curl.se/docs/manpage.html#-w

  - id: b11
    tier: B
    type: text
    objective: caching
    prompt: '`expires 1h` di nginx menghasilkan `Expires: <tanggal>` dan `Cache-Control: max-age=3600`. Bila keduanya ada, mana yang dipakai cache modern?'
    answers: ['Cache-Control max-age']
    patterns: ['(cache-control|max-age)']
    tests:
      pass: ['max-age', 'cache-control: max-age=3600']
      fail: ['expires', 'keduanya dijumlah', 'yang lebih kecil']
    explanation: 'Di lab, keduanya muncul. Menurut RFC 9111, `max-age` (relatif, detik) mengalahkan `Expires` (tanggal absolut, bergantung jam klien yang benar). `Expires` hanya untuk klien HTTP/1.0 yang sangat tua. Untuk aset ber-hash dari build Vite: `max-age=31536000, immutable`.'
    source: https://www.rfc-editor.org/rfc/rfc9111

  - id: b12
    tier: B
    type: text
    objective: caching
    prompt: Request dengan `If-None-Match` berisi ETag yang **sudah tidak cocok** (file berubah). Apa yang dikirim server?
    answers: ['200 dengan isi lengkap terbaru']
    patterns: ['200', '(isi|body|konten) (lengkap|penuh|baru)']
    tests:
      pass: ['200 dan body baru', '200']
      fail: ['304', '412', '404']
    explanation: 'Di lab: `wrong etag -> 200 body=8893B`. Validasi bersyarat aman karena server mengirim isi lengkap bila tidak cocok, dan 304 hanya bila sama. ETag nginx dibentuk dari waktu modifikasi + ukuran (`"6b051144-22bd"`), sehingga server berbeda di belakang load balancer dengan file yang di-deploy di waktu berbeda menghasilkan ETag berbeda.'
    source: https://www.rfc-editor.org/rfc/rfc9110

  - id: b13
    tier: B
    type: text
    objective: debug
    prompt: Dua request ke nginx sama-sama gagal. A → `502` dalam 0.002 detik, B → `504` dalam 2.0 detik. Petunjuk apa yang diberikan **waktu**-nya?
    answers: ['A ditolak langsung (refused/crash), B menunggu sampai timeout (tidak terjangkau atau lambat)']
    patterns: ['(?=.*(langsung|cepat|instan|refused|tolak))(?=.*(tunggu|menunggu|timeout|lambat|tidak terjangkau))']
    tests:
      pass: ['A langsung ditolak, B menunggu timeout', 'A refused cepat, B timeout karena lambat']
      fail: ['sama saja', 'A lebih lambat', 'jaringan klien']
    explanation: 'Di lab: `refused upstream 502 in 0.001836s`, `blackhole upstream 504 in 2.001969s`, `slow upstream 504 in 2.003620s`. Durasi error yang persis sama dengan nilai timeout (2s) adalah tanda timeout. Error seketika berarti penolakan aktif. Pola yang sama berlaku untuk refused vs timeout di TCP (modul 03).'
    source: https://nginx.org/en/docs/http/ngx_http_proxy_module.html

  - id: b14
    tier: B
    type: text
    objective: protocol
    prompt: 'Backend di belakang `proxy_pass http://backend;` melihat `"host": "backend"` (nama upstream), bukan host yang diminta klien. Direktif nginx apa yang memperbaikinya?'
    answers: ['proxy_set_header Host $host']
    patterns: ['proxy_set_header\s+host\s+\$(host|http_host)']
    tests:
      pass: ['proxy_set_header Host $host;', 'proxy_set_header Host $http_host']
      fail: ['proxy_pass_header Host', 'add_header Host $host', 'proxy_set_header X-Forwarded-For $host']
    explanation: 'Di lab, semua echo backend menampilkan `"host": "backend"`. Secara default, nginx mengirim Host = nama di `proxy_pass`. Akibatnya aplikasi (Laravel `url()`, redirect, signed URL) membuat link ke `http://backend/...`. Pasangannya `X-Forwarded-Proto`/`X-Forwarded-For` dibahas di modul Reverse Proxy.'
    source: https://nginx.org/en/docs/http/ngx_http_proxy_module.html#proxy_set_header

  - id: b15
    tier: B
    type: text
    objective: caching
    prompt: 'Apa beda `Cache-Control: no-cache` dengan `no-store`?'
    answers: ['no-cache boleh disimpan tapi wajib divalidasi ulang sebelum dipakai; no-store tidak boleh disimpan sama sekali']
    patterns: ['(?=.*no-cache.*(validasi|revalidat|cek|tanya|304|etag))(?=.*no-store.*(tidak|jangan|never|tak).*(simpan|disimpan|store|cache))']
    tests:
      pass: ['no-cache boleh disimpan tapi harus validasi ulang, no-store tidak boleh disimpan', 'no-cache wajib cek ke server (304), no-store jangan simpan sama sekali']
      fail: ['sama saja', 'no-cache tidak disimpan, no-store disimpan', 'no-store hanya browser']
    explanation: '`no-cache` = simpan, tetapi tanyakan dulu ke server (ETag → 304) setiap kali dipakai. Cocok untuk HTML aplikasi yang harus selalu segar, tetapi hemat bandwidth. `no-store` = jangan pernah menyimpan, untuk data sensitif (lab: `/api/` → `no-store`). Salah memilih membuat data user tersimpan di cache bersama, atau HTML basi setelah deploy.'
    source: https://www.rfc-editor.org/rfc/rfc9111

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: caching
    prompt: |
      Aplikasi Laravel + Vite: aset build ber-hash di `/build/assets/app-3f9a1c.js`, halaman HTML, dan API `/api/me`
      (data user login). Pengguna sering melihat tampilan lama setelah deploy, dan audit menemukan `/api/me` tersimpan
      di CDN. Tulis kebijakan header cache per jenis resource (satu baris per jenis), plus cara memverifikasinya.
    checklist:
      - id: assets
        label: Aset ber-hash → max-age panjang + immutable (public)
        required: true
        patterns: ['max-age=(31536000|\d{7,})', 'immutable']
      - id: html
        label: HTML → no-cache (validasi ETag/304) atau max-age=0
        required: true
        patterns: ['(html|halaman).*(no-cache|max-age=0|must-revalidate)', '(no-cache|max-age=0).*(html|halaman)']
      - id: api
        label: API data user → no-store / private
        required: true
        patterns: ['(api|/api/me|user).*(no-store|private)', '(no-store|private).*(api|user)']
      - id: vary
        label: Vary (Accept-Encoding / Cookie / Authorization) untuk respons yang berbeda per klien
        patterns: ['vary']
      - id: verify
        label: Verifikasi header dengan curl -I / -D
        patterns: ['curl\s+.*-(I|D)\b', 'curl\s+-\S*I']
      - id: no-public-api
        label: Tidak menandai API user sebagai public/max-age panjang
        forbid: true
        patterns: ['(api|/api/me)[^\n]*(public|max-age=[1-9]\d{3,}|immutable)']
    pass_score: 0.6
    solution: |
      ```text
      /build/assets/*-<hash>.js|css → Cache-Control: public, max-age=31536000, immutable
                                      (nama berubah tiap build → aman di-cache selamanya)
      HTML (/, halaman Blade)       → Cache-Control: no-cache   (+ ETag → browser revalidasi, dapat 304 bila sama)
      /api/me (data user)           → Cache-Control: private, no-store ; Vary: Cookie, Authorization
      semua teks                    → gzip + Vary: Accept-Encoding
      verifikasi: curl -sI https://dojo.example.com/build/assets/app-3f9a1c.js | grep -i cache-control
                  curl -sI -H 'If-None-Match: "<etag>"' https://dojo.example.com/   → 304
                  curl -sI https://dojo.example.com/api/me | grep -iE 'cache-control|vary'
      ```
      Akar kedua masalah: HTML lama di-cache dengan `max-age`, sehingga browser tidak pernah bertanya, dan halaman itu
      merujuk aset lama. Sementara itu `/api/me` tanpa `no-store`/`private` dianggap boleh disimpan CDN.

      Mekanismenya dibuktikan di lab:
      - `If-None-Match` cocok → `304 body=0B`.
      - `expires 1h` → `max-age=3600`.
      - `/api/` → `no-store`.
      - `gzip_vary` → `Vary: Accept-Encoding`.
    tests:
      pass:
        - |
          aset /build/assets ber-hash: Cache-Control public, max-age=31536000, immutable
          html: Cache-Control no-cache dengan etag supaya revalidasi 304
          /api/me: Cache-Control private, no-store dan Vary: Cookie
          verifikasi curl -sI https://dojo.example.com/api/me
        - |
          assets max-age=31536000 immutable
          halaman html must-revalidate max-age=0
          api user no-store
      fail:
        - |
          assets max-age=31536000 immutable
          html no-cache
          /api/me public, max-age=3600
        - |
          semua resource no-store
          curl -I
        - |
          html max-age=86400
          assets max-age=86400
    source: https://www.rfc-editor.org/rfc/rfc9111

  - id: c02
    tier: C
    type: scenario
    objective: redirect
    prompt: |
      Setelah memaksa HTTPS dengan `return 301 https://$host$request_uri;`, dua masalah muncul:
      - Klien API lama yang mem-POST ke `http://api.dojo.test/orders` mengirim order kosong.
      - Setelah submit form, pengguna yang menekan refresh mengirim form dua kali.

      Jelaskan penyebab dan perbaikan masing-masing (satu poin per baris), serta cara memverifikasinya.
    checklist:
      - id: cause-301
        label: 301/302 mengubah POST menjadi GET (body hilang)
        required: true
        patterns: ['(301|302).*(get|body hilang|kehilangan body)', '(post).*(jadi|menjadi|berubah).*get']
      - id: fix-308
        label: Pakai 308 (atau 307) untuk endpoint yang menerima POST / perbarui klien ke https
        required: true
        patterns: ['\b30[78]\b']
      - id: prg
        label: Pola Post/Redirect/Get dengan 303 setelah submit form
        required: true
        patterns: ['\b303\b', 'post/redirect/get', '\bprg\b']
      - id: verify
        label: Verifikasi dengan curl -L -d / -v (method di tujuan)
        patterns: ['curl\s+.*(-L|-v|-d)']
      - id: hsts
        label: Klien diarahkan ke https langsung (update base URL / HSTS untuk browser)
        patterns: ['hsts', 'strict-transport-security', '(ubah|update|ganti).*(url|base|klien).*https']
      - id: no-keep-http
        label: Tidak menyarankan mematikan redirect / tetap HTTP untuk API
        forbid: true
        patterns: ['(matikan|hapus|nonaktifkan).*(https|redirect)', 'tetap (pakai )?http\b']
    pass_score: 0.6
    solution: |
      ```text
      1. Order kosong: 301 (dan 302) boleh — dan oleh curl/browser memang — diubah dari POST menjadi GET saat
         diikuti, body hilang. Lab: r301/r302 → {"method":"GET","body_len":0}; r307/r308 → {"method":"POST","body_len":3}.
         Perbaikan: untuk API pakai `return 308 https://$host$request_uri;`, dan perbarui klien agar langsung ke https://
         (redirect hanya jaring pengaman).
      2. Form terkirim dua kali: halaman hasil POST yang di-refresh mengulang POST.
         Perbaikan: pola Post/Redirect/Get — setelah POST sukses balas 303 See Other ke halaman hasil (GET);
         Laravel: return redirect()->route(...) (302, browser mengubahnya ke GET) atau 303 eksplisit.
      Verifikasi: curl -s -L -d 'x=1' http://api.dojo.test/orders → backend harus melihat POST dengan body;
                  curl -sv -d 'x=1' https://.../form 2>&1 | grep '< HTTP' → 303
      Browser: tambahkan HSTS (Strict-Transport-Security) agar request berikutnya langsung https tanpa redirect.
      ```
    tests:
      pass:
        - |
          301 mengubah POST menjadi GET sehingga body hilang
          ganti ke return 308 https://$host$request_uri untuk api
          update base url klien ke https
          form: pakai post/redirect/get dengan 303 see other
          verifikasi curl -L -d x=1 http://api.dojo.test/orders
        - |
          post berubah jadi get karena 301
          pakai 307 atau 308
          prg 303
      fail:
        - |
          301 mengubah post jadi get
          matikan redirect https untuk api
          prg 303
        - |
          naikkan client_max_body_size
          restart nginx
        - |
          301 mengubah post jadi get
          pakai 302
    source: https://www.rfc-editor.org/rfc/rfc9110

  - id: c03
    tier: C
    type: scenario
    objective: debug
    prompt: |
      Monitoring melaporkan sebagian request ke `https://dojo.example.com` gagal dengan **502** dan sebagian lagi
      **504**, keduanya dari nginx di depan php-fpm. Tulis langkah diagnosis (satu perintah/poin per baris) untuk
      membedakan penyebabnya, beserta perbaikan yang sesuai untuk setiap kemungkinan.
    checklist:
      - id: timing
        label: Mengukur waktu (curl -w time_starttransfer/time_total)
        patterns: ['curl\s+.*-w', 'time_(starttransfer|total|connect)']
      - id: errorlog
        label: Membaca error log nginx (frasa connecting/reading/refused)
        required: true
        patterns: ['error\.log', 'docker\s+(compose\s+)?logs\s+.*nginx', 'error log']
      - id: map-502
        label: 502 → refused/reset (fpm mati, crash, salah port/socket, pm.max_children habis)
        required: true
        patterns: ['502.*(refused|crash|mati|down|reset|port|socket|max_children)', '(refused|crash|max_children).*502']
      - id: map-504
        label: 504 → upstream lambat (reading response header) / tidak terjangkau (connecting)
        required: true
        patterns: ['504.*(lambat|slow|timeout|reading|connecting|query)', '(lambat|slow|reading response).*504']
      - id: app
        label: Memeriksa sisi aplikasi (log php-fpm/Laravel, slow log, query lambat)
        patterns: ['(slowlog|slow log|slow query|log (laravel|php-fpm|aplikasi)|php-fpm.*log|max_children)']
      - id: no-blind-timeout
        label: Tidak sekadar menaikkan timeout sangat besar sebagai solusi
        forbid: true
        patterns: ['(proxy|fastcgi)_read_timeout\s+\d{4,}', 'timeout.*(3600|9999|tak terbatas|unlimited)']
    pass_score: 0.6
    solution: |
      ```bash
      curl -s -o /dev/null -w 'code=%{http_code} connect=%{time_connect} ttfb=%{time_starttransfer} total=%{time_total}\n' https://dojo.example.com/
      docker compose logs --since 1h nginx | grep -E 'upstream|connect\(\)' | sort | uniq -c | sort -rn | head
      docker compose logs --since 1h app | grep -iE 'max_children|exited|segfault|timeout'
      ```
      Petakan frasa error log ke penyebab (lab: semua frasa ini muncul persis):
      - `connect() failed (111: Connection refused) while connecting to upstream` → **502**, seketika. php-fpm mati
        atau restart, salah host/port/socket, atau semua worker penuh dan backlog meluap (`pm.max_children` reached).
        Perbaikan: cek status container app, `pm.max_children` sesuai memori, healthcheck, dan restart policy.
      - `upstream prematurely closed connection` / `reset by peer` → **502**. Worker crash/OOM di tengah request
        (exit 137, modul Docker 10).
      - `upstream timed out ... while connecting to upstream` → **504**. Upstream tidak terjangkau (network/IP salah,
        jebakan IP basi nginx di modul Docker 11).
      - `upstream timed out ... while reading response header` → **504** tepat di nilai timeout. Aplikasi lambat:
        cek php-fpm slowlog, query lambat, panggilan API eksternal tanpa timeout. Perbaiki di aplikasi (index, cache,
        queue untuk kerja berat). Timeout cukup dinaikkan sedikit bila memang wajar.
    tests:
      pass:
        - |
          curl -w 'ttfb=%{time_starttransfer} total=%{time_total}' https://dojo.example.com/
          docker compose logs nginx | grep upstream
          502 karena connection refused: php-fpm mati atau max_children penuh
          504 karena reading response header: aplikasi lambat, cek slow log dan query lambat
        - |
          cek error.log nginx
          502 refused atau crash
          504 timeout reading response header karena query lambat
      fail:
        - |
          cek error.log nginx
          502 refused
          504 lambat
          set fastcgi_read_timeout 3600
        - |
          restart nginx
          tambah server
        - |
          502 dan 504 sama saja
          naikkan timeout
    source: https://nginx.org/en/docs/http/ngx_http_proxy_module.html

---

# HTTP

HTTP adalah protokol teks yang dibaca dan ditulis setiap web server, proxy, CDN, dan aplikasi. Hampir semua masalah
web (redirect yang menghilangkan data, cache basi, cookie hilang, 502/504) terlihat jelas begitu kamu membaca header
request dan respons-nya. Modul ini membahasnya dengan nginx, backend Python yang menggemakan request, dan `curl`.

> [!NOTE]
> Lab: nginx 1.27.5 dengan beberapa `location` uji, backend `python:3.12-alpine` yang membalas JSON berisi method,
> path, ukuran body, dan Host yang diterimanya (bisa dibuat lambat), serta klien alpine + curl. HTTPS/TLS dibahas di
> modul 06.

## 1. HTTP adalah teks

```text
$ printf 'GET /data.txt HTTP/1.1\r\nHost: dojo.test\r\nConnection: close\r\n\r\n' | nc 10.63.0.80 80
HTTP/1.1 200 OK
Server: nginx/1.27.5
Content-Type: text/plain
Content-Length: 8893
Last-Modified: Tue, 24 Nov 2026 04:23:32 GMT
ETag: "6b051144-22bd"
Accept-Ranges: bytes
Vary: Accept-Encoding

1
2
...
```

Tanpa `Host` → `400 Bad Request`. Host juga menentukan virtual host:

```bash
curl -H 'Host: other.test' http://10.63.0.80/                 # other vhost
curl --resolve other.test:80:10.63.0.80 http://other.test/    # sama, tapi DNS, Host, dan SNI semuanya benar
```

Host yang tidak dikenal dilayani `default_server` (lab: `200`). Untuk server publik, tambahkan blok default yang
menolak (`return 444;`).

## 2. Status code yang paling sering kamu temui

| Kode | Arti | Lab |
|---|---|---|
| 200 / 204 / 206 | OK / tanpa body / sebagian | `206` untuk `-r 0-9` |
| 301 / 308 | Pindah permanen (308 pertahankan method) | — |
| 302 / 307 | Pindah sementara (307 pertahankan method) | — |
| 303 | Lihat lain, paksa GET (pola PRG) | — |
| 304 | Tidak berubah, pakai cache | `If-None-Match` cocok |
| 400 | Request rusak | tanpa Host |
| 404 / 405 | Tidak ada / method tidak didukung | `/nope`, POST ke file statis |
| 413 | Body terlalu besar | 2 KB > `client_max_body_size 1k` |
| **502** | Upstream menolak atau jawabannya rusak | `refused` dalam 0.002 s |
| **504** | Upstream tidak menjawab tepat waktu | 2.0 s = nilai timeout |

## 3. Redirect dan method

| Redirect | POST + `curl -L` → tujuan melihat (lab) |
|---|---|
| 301 | **GET**, body 0 |
| 302 | **GET**, body 0 |
| 307 | POST, body 3 |
| 308 | POST, body 3 |

Untuk API pakai 308/307. Setelah submit form pakai 303 (Post/Redirect/Get). Redirect HTTP→HTTPS dengan 301 hanya
aman untuk GET.

## 4. Caching

```text
expires 1h          → Cache-Control: max-age=3600  (+ Expires)
/api/               → Cache-Control: no-store
If-None-Match benar → 304, body 0 B
If-None-Match salah → 200, body 8893 B
gzip_vary on        → Vary: Accept-Encoding
```

| Header | Arti |
|---|---|
| `max-age=N` | Pakai tanpa bertanya selama N detik |
| `immutable` | Tidak akan pernah berubah (aset ber-hash) |
| `no-cache` | Boleh disimpan, **wajib validasi** (ETag → 304) |
| `no-store` | Jangan disimpan sama sekali |
| `private` / `public` | Hanya browser / boleh di CDN/proxy |
| `Vary` | Kunci cache ikut header request ini |

## 5. Koneksi, kompresi, dan HTTP/2

| Fitur | Lab |
|---|---|
| Keep-alive | Request kedua: `Re-using existing http: connection` |
| `Connection: close` | Dua kali `Connected to`, handshake ulang |
| gzip | 8893 B → 3750 B, `Content-Encoding: gzip` + `Transfer-Encoding: chunked` |
| HTTP/2 (h2c, port 81) | `HTTP/2 200`, header huruf kecil, satu koneksi untuk banyak request |
| Range | `206`, `Content-Range: bytes 0-9/8893` |

Browser hanya memakai HTTP/2 lewat TLS (ALPN). h2c hanya untuk tool dan koneksi antar server.

## 6. Cookie dan CORS

```text
Set-Cookie: session=abc123; Path=/; HttpOnly; Secure; SameSite=Lax
```

- `Secure` lewat HTTP biasa → cookie **tidak disimpan** (lab: jar kosong).
- CORS adalah instruksi untuk browser. Di lab, curl dengan `Origin: https://evil.test` tetap mendapat `cors ok`.
  CORS tidak menggantikan autentikasi.
- Preflight: `OPTIONS` → `204` + `Access-Control-Allow-*`. Jangan blokir OPTIONS di WAF.

## 7. Membaca 502 vs 504 dari nginx

| Error log (lab) | Status | Waktu | Artinya |
|---|---|---|---|
| `connect() failed (111: Connection refused) while connecting to upstream` | 502 | 0.002 s | Tidak ada yang listen |
| `upstream timed out (110) while connecting to upstream` | 504 | 2.0 s | Upstream tidak terjangkau |
| `upstream timed out (110) while reading response header from upstream` | 504 | 2.0 s | Aplikasi lambat |

Ukur dari sisi klien:

```bash
curl -s -o /dev/null -w 'connect=%{time_connect} ttfb=%{time_starttransfer} total=%{time_total}\n' URL
# lab: connect=0.000167s ttfb=1.001695s → waktu habis di aplikasi, bukan jaringan
```

## 8. Ringkasan

| Gejala | Cek |
|---|---|
| Data POST hilang setelah redirect | 301/302 → pakai 308/307 |
| Tampilan lama setelah deploy | HTML ber-`max-age` → `no-cache` + aset ber-hash `immutable` |
| Data user di CDN | `private, no-store` + `Vary` |
| Login gagal di balik proxy TLS | Cookie `Secure` + aplikasi mengira HTTP |
| Link aplikasi ke `http://backend/` | `proxy_set_header Host $host` |
| 502 / 504 | Frasa error log + durasi |
