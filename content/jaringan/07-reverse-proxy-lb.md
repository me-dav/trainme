---
format: 1
id: net-reverse-proxy-lb
course:
  slug: jaringan
  title: Jaringan
  description: Konsep jaringan untuk DevOps — TCP/IP, subnetting, routing, TCP/UDP, DNS, HTTP, TLS, proxy, firewall, VPN, dan troubleshooting.
title: Reverse Proxy & Load Balancing
summary: Apa yang berubah saat request melewati proxy (IP klien, Host, skema), header X-Forwarded-* dan pemalsuannya, real_ip dan trusted proxies (termasuk Laravel), algoritma load balancing, failover dan retry, backup server, WebSocket, buffering, dan status 499 — diukur dengan nginx dan tiga backend.
order: 7
version: 1
status: verified
updated: '2026-11-24'
tested_on:
  - Docker Desktop (Windows) — nginx 1.27.5 (LB + edge proxy), 3 backend python:3.12-alpine, klien alpine + curl
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: headers
    text: Meneruskan identitas klien (IP, Host, skema) lewat header proxy
  - id: trust
    text: Mempercayai header proxy dengan benar (real_ip, trusted proxies) dan mencegah pemalsuan
  - id: algorithms
    text: Memilih algoritma load balancing
  - id: failover
    text: Memahami failover, retry, health check pasif, dan backup
  - id: protocols
    text: Mem-proxy WebSocket dan streaming
  - id: debug
    text: Membaca log proxy (upstream_addr, upstream_status, 499)
sources:
  - title: nginx — ngx_http_upstream_module (round robin, weight, least_conn, ip_hash, max_fails, backup, zone)
    url: https://nginx.org/en/docs/http/ngx_http_upstream_module.html
  - title: nginx — ngx_http_proxy_module (proxy_set_header, proxy_next_upstream, timeouts, buffering)
    url: https://nginx.org/en/docs/http/ngx_http_proxy_module.html
  - title: nginx — ngx_http_realip_module
    url: https://nginx.org/en/docs/http/ngx_http_realip_module.html
  - title: nginx — WebSocket proxying
    url: https://nginx.org/en/docs/http/websocket.html
  - title: nginx — Using nginx as HTTP load balancer
    url: https://nginx.org/en/docs/http/load_balancing.html
  - title: Laravel — Requests, Configuring Trusted Proxies
    url: https://laravel.com/docs/requests#configuring-trusted-proxies
  - title: RFC 7239 — Forwarded HTTP Extension
    url: https://www.rfc-editor.org/rfc/rfc7239
  - title: MDN — X-Forwarded-For (security considerations)
    url: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-Forwarded-For
  - title: RFC 9110 — hop-by-hop headers (Connection, Upgrade)
    url: https://www.rfc-editor.org/rfc/rfc9110#section-7.6.1
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: headers
    prompt: Header de facto yang membawa daftar IP klien dan proxy yang dilewati request?
    answers: ['X-Forwarded-For', 'XFF']
    patterns: ['^(x-forwarded-for|xff)$']
    tests:
      pass: ['XFF']
      fail: ['X-Real-IP', 'Forwarded-Host', 'Via', 'X-Client']
    explanation: 'Di lab, lewat dua proxy, backend melihat `"xff": "6.6.6.6, 10.65.0.10, 10.65.0.10"`, dengan setiap proxy menambahkan IP yang ia lihat di ujung kanan. Standar resminya adalah header `Forwarded` (RFC 7239), tetapi XFF jauh lebih banyak dipakai. Tanpa header ini, backend hanya melihat IP proxy (`"peer": "10.65.0.80"`).'
    source: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-Forwarded-For

  - id: a02
    tier: A
    type: text
    objective: headers
    prompt: Variabel nginx yang berisi `X-Forwarded-For` dari request **ditambah** `$remote_addr` di ujungnya?
    answers: ['$proxy_add_x_forwarded_for']
    patterns: ['^\$?proxy_add_x_forwarded_for$']
    tests:
      pass: ['proxy_add_x_forwarded_for']
      fail: ['$remote_addr', '$http_x_forwarded_for', '$realip_remote_addr', '$x_forwarded_for']
    explanation: 'Di lab, `proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;` → klien langsung: `"xff": "10.65.0.10"`, klien yang mengirim XFF palsu: `"1.2.3.4, 10.65.0.10"`. Ini mempertahankan rantai, tetapi bagian kiri berasal dari klien dan **tidak bisa dipercaya** (tier B).'
    source: https://nginx.org/en/docs/http/ngx_http_proxy_module.html#var_proxy_add_x_forwarded_for

  - id: a03
    tier: A
    type: text
    objective: headers
    prompt: Header yang memberi tahu backend skema asli request klien (`http` atau `https`), penting saat TLS dihentikan di proxy?
    answers: ['X-Forwarded-Proto']
    patterns: ['^x-forwarded-proto(col)?$']
    tests:
      pass: ['x-forwarded-proto']
      fail: ['X-Forwarded-Host', 'X-Scheme-Port', 'Upgrade', 'X-Forwarded-For']
    explanation: 'Bila TLS berakhir di proxy, backend menerima HTTP biasa. Tanpa `X-Forwarded-Proto: https` (dan trusted proxy), Laravel mengira request-nya HTTP. Akibatnya `url()` menghasilkan `http://`, terjadi redirect loop ke HTTPS, dan cookie `Secure` gagal (modul 05). Di lab, proxy dalam yang memakai `$scheme` menimpa `https` dari edge menjadi `"xfp": "http"` (tier B).'
    source: https://laravel.com/docs/requests#configuring-trusted-proxies

  - id: a04
    tier: A
    type: text
    objective: algorithms
    prompt: Algoritma load balancing default nginx untuk sebuah `upstream`?
    answers: ['round robin', 'round-robin']
    patterns: ['^(weighted )?round[- ]?robin$']
    tests:
      pass: ['round-robin']
      fail: ['least_conn', 'ip_hash', 'random', 'hash']
    explanation: 'Tanpa direktif apa pun, nginx bergiliran ke setiap server (dengan bobot `weight`, default 1). Di lab, dengan shared memory `zone` → tepat `10 be1, 10 be2, 10 be3` dari 30 request. Tanpa `zone` hasilnya tidak rata (tier B).'
    source: https://nginx.org/en/docs/http/load_balancing.html

  - id: a05
    tier: A
    type: text
    objective: algorithms
    prompt: Direktif `upstream` nginx yang mengirim request ke server dengan **koneksi aktif paling sedikit**?
    answers: ['least_conn']
    patterns: ['^least_conn;?$']
    tests:
      pass: ['least_conn;']
      fail: ['least_time', 'ip_hash', 'round_robin', 'fair']
    explanation: 'Di lab, dua request `/slow` (3 detik) menahan be1, lalu 6 request berikutnya dengan `least_conn` → `3 be2, 3 be3`, sehingga be1 yang sibuk dihindari. Cocok untuk durasi request yang bervariasi (upload, laporan berat). `least_time` hanya ada di nginx Plus.'
    source: https://nginx.org/en/docs/http/ngx_http_upstream_module.html#least_conn

  - id: a06
    tier: A
    type: text
    objective: algorithms
    prompt: Direktif `upstream` nginx yang membuat klien dengan IP yang sama selalu diarahkan ke server yang sama (sticky)?
    answers: ['ip_hash']
    patterns: ['^ip_hash;?$']
    tests:
      pass: ['ip_hash;']
      fail: ['sticky', 'least_conn', 'hash $cookie', 'session']
    explanation: 'Di lab: `ip_hash`, 20 request → `20 be1`. Ini berguna bila session disimpan lokal di server, tetapi semua pengguna di belakang satu NAT/proxy kantor berakhir di satu server (tier B). `hash $cookie_x consistent` memakai kunci lain. Solusi yang lebih baik adalah session bersama (Redis/database).'
    source: https://nginx.org/en/docs/http/ngx_http_upstream_module.html#ip_hash

  - id: a07
    tier: A
    type: text
    objective: failover
    prompt: Parameter `server` di blok `upstream` yang membuat server itu **hanya** dipakai bila semua server utama tidak tersedia?
    answers: ['backup']
    tests:
      fail: ['down', 'standby', 'weight=0', 'max_fails']
    explanation: 'Di lab, `be3 backup`: dengan be1 mati, semua ke be2 (`6 be2`). Setelah be2 juga mati → `4 be3`. `down` menandai server nonaktif secara manual (untuk maintenance/drain). `backup` tidak bisa dipakai bersama `ip_hash`, `hash`, atau `random`.'
    source: https://nginx.org/en/docs/http/ngx_http_upstream_module.html#server

  - id: a08
    tier: A
    type: text
    objective: debug
    prompt: Status code yang dicatat nginx di access log ketika **klien** menutup koneksi sebelum respons dikirim?
    answers: ['499']
    tests:
      fail: ['408', '504', '502', '444']
    explanation: 'Di lab: `curl -m 1` ke endpoint 3 detik → curl rc=28, dan log nginx `st=499 ... ust=- rt=1.001`. 499 adalah kode khusus nginx, tidak pernah dikirim ke klien. Lonjakan 499 berarti klien (atau load balancer di depan) menyerah lebih dulu, biasanya karena timeout klien lebih pendek dari waktu proses aplikasi.'
    source: https://nginx.org/en/docs/http/ngx_http_proxy_module.html

  - id: a09
    tier: A
    type: text
    objective: protocols
    prompt: '`proxy_http_version` berapa yang dibutuhkan nginx untuk mem-proxy WebSocket (beserta header Upgrade/Connection)?'
    answers: ['1.1']
    patterns: ['^(http/)?1\.1$']
    tests:
      pass: ['HTTP/1.1']
      fail: ['1.0', '2', '2.0', '3']
    explanation: 'Default nginx ke upstream adalah HTTP/1.0 tanpa header hop-by-hop. Di lab, lokasi `/ws/` dengan `proxy_http_version 1.1; proxy_set_header Upgrade $http_upgrade; proxy_set_header Connection "upgrade";` → backend melihat `"upgrade": "websocket", "conn": "upgrade"`. Tanpa itu (`/ws-bad/`) → `"upgrade": null, "conn": "close"`, sehingga handshake WebSocket gagal.'
    source: https://nginx.org/en/docs/http/websocket.html

  - id: a10
    tier: A
    type: text
    objective: algorithms
    prompt: Upstream `be1 weight=3; be2; be3;` menerima 50 request. Berapa **persen** kira-kira yang menuju be1?
    answers: ['60%', '60']
    patterns: ['^(~ ?)?60 ?%?$']
    tests:
      pass: ['60 %', '~60%']
      fail: ['33%', '50%', '75%', '30%']
    explanation: 'Bobot 3 : 1 : 1 → be1 mendapat 3/5 = 60%. Di lab: `31 be1, 10 be2, 9 be3` dari 50. Bobot berguna saat server tidak setara (CPU berbeda) atau untuk canary (server versi baru diberi bobot kecil).'
    source: https://nginx.org/en/docs/http/ngx_http_upstream_module.html#server

  - id: a11
    tier: A
    type: text
    objective: algorithms
    prompt: Load balancer yang bisa memilih backend berdasarkan **path URL, Host, atau header** bekerja di layer berapa (OSI)?
    answers: ['7', 'L7', 'layer 7']
    patterns: ['^(l|layer ?)?7$']
    tests:
      pass: ['L7', 'layer 7']
      fail: ['4', 'L4', 'layer 3', '2']
    explanation: 'L7 (HTTP) membaca request, sehingga bisa routing per path/Host, menambah header, dan menghentikan TLS. Di lab, nginx memilih upstream berbeda untuk `/rr/`, `/lc/`, `/sticky/`. L4 (TCP/UDP) hanya melihat IP:port. Lebih cepat dan protokol-agnostik (mis. Postgres), tetapi tidak bisa melihat isi HTTP atau menambah XFF.'
    source: https://nginx.org/en/docs/http/load_balancing.html

  - id: a12
    tier: A
    type: text
    objective: trust
    prompt: Direktif modul `realip` nginx untuk menentukan alamat proxy yang header-nya (mis. XFF) **dipercaya**?
    answers: ['set_real_ip_from']
    patterns: ['^set_real_ip_from']
    tests:
      pass: ['set_real_ip_from 10.65.0.81;']
      fail: ['real_ip_header', 'proxy_set_header', 'allow', 'trusted_proxy']
    explanation: 'Di lab, `set_real_ip_from 10.65.0.81; real_ip_header X-Forwarded-For; real_ip_recursive on;`: request lewat edge → `$remote_addr` = `10.65.0.10` (klien asli) dengan `$realip_remote_addr` = `10.65.0.81`, sedangkan request langsung dari klien yang mengirim XFF palsu tetap tercatat dengan IP aslinya. Isi daftar ini **hanya** dengan IP proxy milikmu (tier B).'
    source: https://nginx.org/en/docs/http/ngx_http_realip_module.html

  - id: a13
    tier: A
    type: text
    objective: trust
    prompt: Di Laravel 11+, method middleware (di `bootstrap/app.php`) untuk mengatur proxy tepercaya agar `request()->ip()` dan skema HTTPS dibaca dari header proxy?
    answers: ['trustProxies']
    patterns: ['^(\$middleware->)?trustproxies(\(.*\))?;?$']
    tests:
      pass: ['$middleware->trustProxies(at: [''10.0.0.0/8''])']
      fail: ['trustHosts', 'TrustProxies::class', 'setTrustedHosts', 'validateCsrfTokens']
    explanation: 'Contoh: `->withMiddleware(function (Middleware $middleware) { $middleware->trustProxies(at: [''10.65.0.80'']); })`. Hanya header dari IP di daftar `at` yang dipakai. `at: ''*''` hanya aman bila aplikasi **tidak bisa** diakses kecuali lewat proxy (mis. php-fpm di network internal). Bila tidak, siapa pun bisa memalsukan IP dan skema.'
    source: https://laravel.com/docs/requests#configuring-trusted-proxies

  - id: a14
    tier: A
    type: text
    objective: failover
    prompt: Nilai default parameter `max_fails` di `server` upstream nginx?
    answers: ['1']
    tests:
      fail: ['0', '3', '5', '10']
    explanation: 'Default `max_fails=1 fail_timeout=10s`. Satu kegagalan (error/timeout) membuat server dianggap tidak tersedia selama 10 detik. Di lab, setelah satu request ke be1 yang mati gagal, request berikutnya langsung ke server lain. Ini **health check pasif**: kegagalan baru terdeteksi saat request sungguhan gagal.'
    source: https://nginx.org/en/docs/http/ngx_http_upstream_module.html#max_fails

  - id: a15
    tier: A
    type: text
    objective: headers
    prompt: 'Kenapa `proxy_set_header X-Real-IP $remote_addr;` tidak bisa dipalsukan klien yang langsung terhubung, sedangkan bagian kiri `X-Forwarded-For` bisa?'
    answers: ['X-Real-IP ditimpa dengan IP koneksi TCP yang dilihat nginx, bukan nilai dari klien']
    patterns: ['(timpa|overwrite|ditimpa|mengganti|diganti|koneksi|tcp|remote_addr|dilihat nginx)']
    tests:
      pass: ['ditimpa dengan remote_addr dari koneksi tcp', 'nginx mengganti nilainya dengan ip koneksi']
      fail: ['dienkripsi', 'karena https', 'tidak ada bedanya']
    explanation: 'Di lab, klien mengirim `X-Real-IP: 1.2.3.4` dan `X-Forwarded-For: 1.2.3.4` → backend: `"xri": "10.65.0.10"` (ditimpa), `"xff": "1.2.3.4, 10.65.0.10"` (ditambah). IP sumber TCP tidak bisa dipalsukan begitu koneksi terbentuk, sedangkan header bisa diisi apa saja oleh klien.'
    source: https://nginx.org/en/docs/http/ngx_http_proxy_module.html#proxy_set_header

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: headers
    prompt: Backend di belakang `proxy_pass http://rr/;` **tanpa** `proxy_set_header` apa pun. Apa yang dilihatnya sebagai IP klien dan Host?
    answers: ['IP proxy nginx dan Host = nama upstream (rr)']
    patterns: ['(?=.*(proxy|nginx|10\.65\.0\.80))(?=.*(rr|upstream))']
    tests:
      pass: ['ip nginx 10.65.0.80 dan host rr', 'ip proxy, host nama upstream']
      fail: ['ip klien asli', 'host dojo.test', 'tidak ada ip']
    explanation: 'Di lab: `"peer": "10.65.0.80", "host": "rr", "xff": null`. Semua pengguna terlihat sebagai satu IP, sehingga rate limit, audit log, dan geo-IP rusak. Link yang dibuat aplikasi pun menunjuk `http://rr/...`. Minimal kirim `Host`, `X-Real-IP`, `X-Forwarded-For`, dan `X-Forwarded-Proto`.'
    source: https://nginx.org/en/docs/http/ngx_http_proxy_module.html#proxy_set_header

  - id: b02
    tier: B
    type: text
    objective: trust
    prompt: 'Backend menerima `X-Forwarded-For: 1.2.3.4, 10.65.0.10` dari proxy-mu. Entry mana yang bisa dipercaya sebagai IP klien?'
    answers: ['yang paling kanan, ditambahkan proxy tepercaya (10.65.0.10)']
    patterns: ['(10\.65\.0\.10|kanan|terakhir|right|last|ditambah(kan)? proxy)']
    tests:
      pass: ['10.65.0.10', 'entry paling kanan dari proxy kita']
      fail: ['1.2.3.4', 'yang pertama', 'paling kiri', 'semuanya']
    explanation: 'Di lab, `1.2.3.4` dikirim klien sendiri lewat header palsu, dan proxy hanya menambahkan IP yang benar-benar ia lihat (`10.65.0.10`). Baca XFF dari **kanan ke kiri**, dan lewati IP proxy milikmu. Entry pertama yang bukan proxy tepercaya adalah klien. Mengambil entry paling kiri adalah celah klasik untuk memalsukan IP (bypass rate limit, allowlist).'
    source: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-Forwarded-For

  - id: b03
    tier: B
    type: text
    objective: trust
    prompt: '`set_real_ip_from` diisi seluruh subnet `10.65.0.0/24`, termasuk subnet tempat klien berada. Klien langsung mengirim `X-Forwarded-For: 6.6.6.6`. IP apa yang dicatat nginx sebagai `$remote_addr`?'
    answers: ['6.6.6.6 (palsu)']
    patterns: ['6\.6\.6\.6', 'palsu', 'spoof']
    tests:
      pass: ['6.6.6.6', 'ip palsu dari header']
      fail: ['10.65.0.10', 'ip proxy', 'tidak berubah']
    explanation: 'Di lab (konfigurasi awal): log `6.6.6.6 real=10.65.0.10`, sehingga IP palsu diterima sebagai IP klien, karena klien berada di rentang yang "dipercaya". Setelah diperbaiki menjadi `set_real_ip_from 10.65.0.81` (edge saja) → log `10.65.0.10 real=10.65.0.10`, dan header palsu diabaikan. Trust berdasarkan **IP proxy yang spesifik**, bukan "jaringan internal".'
    source: https://nginx.org/en/docs/http/ngx_http_realip_module.html

  - id: b04
    tier: B
    type: text
    objective: trust
    prompt: 'Rantai `klien → edge (set X-Forwarded-Proto https) → nginx dalam (proxy_set_header X-Forwarded-Proto $scheme) → app`. Apa nilai X-Forwarded-Proto yang diterima app, dan apa akibatnya?'
    answers: ['http, karena proxy dalam menimpanya; app mengira request tidak aman (redirect loop/URL http/cookie Secure gagal)']
    patterns: ['(?=.*\bhttp\b)(?=.*(timpa|overwrite|ditimpa|loop|url http|secure|mixed|tidak aman|redirect))']
    tests:
      pass: ['http karena ditimpa $scheme, app membuat url http dan redirect loop', 'http, ditimpa proxy dalam sehingga cookie secure gagal']
      fail: ['https', 'tidak ada header', 'https dan semua normal']
    explanation: 'Di lab, edge mengirim `https`, tetapi backend menerima `"xfp": "http"`, karena `$scheme` di proxy dalam adalah skema koneksi **ke dirinya** (HTTP dari edge). Proxy dalam harus meneruskan nilai dari proxy tepercaya (`$http_x_forwarded_proto`), bukan menimpanya. Di Laravel, akibatnya `url()`/`route()` menjadi `http://`, `secure()` gagal, dan middleware HTTPS memicu redirect loop.'
    source: https://laravel.com/docs/requests#configuring-trusted-proxies

  - id: b05
    tier: B
    type: text
    objective: algorithms
    prompt: 'Upstream round robin tanpa `zone`, 30 request → `17 be1, 7 be2, 6 be3`. nginx punya 14 worker. Apa penyebab pembagian tidak rata, dan perbaikannya?'
    answers: ['setiap worker punya penghitung round robin sendiri; tambahkan zone (shared memory) di upstream']
    patterns: ['(?=.*(worker|per proses|masing-masing))(?=.*(zone|shared memory|memori bersama))']
    tests:
      pass: ['tiap worker punya state sendiri, tambahkan zone', 'state per worker, pakai zone shared memory']
      fail: ['be1 lebih cepat', 'bug nginx', 'tambah weight']
    explanation: 'Di lab: `rr` tanpa zone → `17/7/6`, sedangkan `rrz` dengan `zone rrz 64k;` → `10/10/10`. Tanpa zone, setiap worker memulai giliran dari server pertama, dan status `max_fails` juga tidak dibagi antar worker, sehingga worker lain terus mencoba server yang mati. `zone` juga syarat untuk fitur upstream dinamis.'
    source: https://nginx.org/en/docs/http/ngx_http_upstream_module.html#zone

  - id: b06
    tier: B
    type: text
    objective: failover
    prompt: Container be1 dihentikan (IP-nya hilang dari network). Satu request yang kebetulan diarahkan ke be1 menggantung **60 detik** sebelum dijawab 200 oleh server lain. Direktif nginx apa yang menentukan 60 detik itu?
    answers: ['proxy_connect_timeout']
    patterns: ['proxy_connect_timeout']
    tests:
      pass: ['proxy_connect_timeout (default 60s)']
      fail: ['proxy_read_timeout', 'fail_timeout', 'keepalive_timeout', 'proxy_send_timeout']
    explanation: 'Di lab: `200/60.060322`, dengan log `up=10.65.0.2:8000, 10.65.0.3:8000 ust=504, 200 rt=60.060`. IP yang hilang tidak membalas RST, sehingga nginx menunggu `proxy_connect_timeout` (default **60s**) sebelum mencoba server berikutnya. Untuk LAN/datacenter, set 2–5 detik, karena koneksi TCP yang sehat terbentuk dalam milidetik.'
    source: https://nginx.org/en/docs/http/ngx_http_proxy_module.html#proxy_connect_timeout

  - id: b07
    tier: B
    type: text
    objective: failover
    prompt: Pada kejadian di atas, upstream pertama gagal (timeout) lalu nginx mencoba server berikutnya. Status apa yang akhirnya diterima **klien**?
    answers: ['200']
    tests:
      fail: ['504', '502', '499', '500']
    explanation: 'Di lab: `ust=504, 200`, dan klien menerima `200` (hanya terlambat 60 detik). `proxy_next_upstream` default `error timeout` membuat nginx mencoba server lain bila koneksi gagal atau timeout. Kolom `$upstream_addr`/`$upstream_status` di log memperlihatkan percobaan yang tersembunyi dari klien. Tambahkan keduanya ke `log_format`.'
    source: https://nginx.org/en/docs/http/ngx_http_proxy_module.html#proxy_next_upstream

  - id: b08
    tier: B
    type: text
    objective: failover
    prompt: Backend membalas **500**. Apakah nginx (default) mencoba ulang ke server lain? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab: `/rr/err` tiga kali → `500 500 500`, dengan log `up=10.65.0.3:8000 ust=500`, satu upstream per request. Default `proxy_next_upstream error timeout` tidak mencakup `http_500`. Menambahkan `http_500 http_502` bisa membantu, tetapi berbahaya untuk request **non-idempotent** (POST). nginx tidak mengulang POST yang sudah terkirim kecuali `non_idempotent` diaktifkan, karena POST yang diulang bisa membuat order dobel.'
    source: https://nginx.org/en/docs/http/ngx_http_proxy_module.html#proxy_next_upstream

  - id: b09
    tier: B
    type: text
    objective: algorithms
    prompt: Kenapa `ip_hash` bisa membuat beban **sangat timpang** untuk aplikasi B2B yang penggunanya dari beberapa kantor besar?
    answers: ['semua pengguna di belakang satu NAT/proxy kantor punya satu IP, sehingga ke satu server']
    patterns: ['(nat|satu ip|ip( publik)? yang sama|berbagi ip|proxy kantor|gateway kantor|cgnat)']
    tests:
      pass: ['satu kantor keluar lewat satu ip NAT, semua ke server yang sama', 'pengguna berbagi IP publik yang sama']
      fail: ['ip_hash acak', 'karena https', 'server lambat']
    explanation: 'Di lab, satu klien → `20 be1`, artinya hash IP selalu sama. Ribuan karyawan di belakang satu NAT/proxy (atau CGNAT seluler) terlihat sebagai satu IP, sehingga satu server kewalahan sementara yang lain menganggur. Bila server itu mati, semua session mereka ikut hilang. Lebih baik session bersama (Redis/database) + `least_conn`/round robin.'
    source: https://nginx.org/en/docs/http/ngx_http_upstream_module.html#ip_hash

  - id: b10
    tier: B
    type: text
    objective: failover
    prompt: Upstream `be1; be2; be3 backup;`. be1 mati. Ke mana request pergi? Dan kapan be3 menerima trafik?
    answers: ['semua ke be2; be3 hanya bila be1 dan be2 sama-sama tidak tersedia']
    patterns: ['(?=.*be2)(?=.*be3.*(semua|keduanya|be1 dan be2|be2 (juga )?mati|tidak tersedia))']
    tests:
      pass: ['semua ke be2, be3 hanya saat be1 dan be2 mati', 'be2 saja; be3 dipakai bila semua primary down']
      fail: ['be2 dan be3 bergantian', 'be3 langsung', 'error 502']
    explanation: 'Di lab: be1 mati → `6 be2` (be3 tidak menerima apa pun), be2 juga mati → `4 be3`. Backup cocok untuk server cadangan atau halaman "maintenance". Perhatikan kapasitasnya: semua beban be1 pindah ke be2 saja, dan be3 tidak membantu sampai be2 ikut tumbang.'
    source: https://nginx.org/en/docs/http/ngx_http_upstream_module.html#backup

  - id: b11
    tier: B
    type: text
    objective: protocols
    prompt: 'Lokasi proxy tanpa `proxy_http_version 1.1` dan header Upgrade. Klien mengirim `Connection: Upgrade`, `Upgrade: websocket`. Apa yang diterima backend?'
    answers: ['tanpa header Upgrade, dengan Connection: close']
    patterns: ['(upgrade.*(null|tidak ada|hilang|kosong|dibuang)|(tidak ada|tanpa|hilang).*upgrade|connection.*close)']
    tests:
      pass: ['upgrade null, connection close', 'header upgrade dibuang']
      fail: ['upgrade websocket', '101 switching protocols', 'sama persis']
    explanation: 'Di lab, `/ws-bad/` → `"upgrade": null, "conn": "close"`. `Connection` dan `Upgrade` adalah header **hop-by-hop** (RFC 9110), yang hanya berlaku untuk satu sambungan dan tidak diteruskan proxy kecuali diset ulang secara eksplisit. Gejala di aplikasi: WebSocket (Laravel Reverb, Socket.IO) gagal connect atau jatuh ke long polling.'
    source: https://nginx.org/en/docs/http/websocket.html

  - id: b12
    tier: B
    type: text
    objective: protocols
    prompt: Apa tujuan utama `proxy_buffering on` (default) di nginx?
    answers: ['nginx membaca respons upstream secepatnya ke buffer agar worker aplikasi cepat bebas, lalu mengirimkannya ke klien lambat']
    patterns: ['(bebas|lepas|cepat selesai|free|klien lambat|slow client|melindungi upstream|worker aplikasi)']
    tests:
      pass: ['membebaskan upstream dari klien yang lambat', 'php-fpm cepat bebas, nginx yang menunggu klien lambat']
      fail: ['mengompres respons', 'cache respons', 'menambah keamanan tls']
    explanation: 'Dengan buffering, php-fpm selesai menulis ke nginx dalam milidetik, lalu nginx yang melayani klien seluler yang lambat. Tanpa buffering, worker php-fpm tertahan selama transfer. Catatan lab: stream chunked sederhana tetap tiba per detik (`+0s, +1s, +2s`) dengan buffering on maupun off. Dokumentasi tetap menyarankan `proxy_buffering off` atau header `X-Accel-Buffering: no` untuk SSE/long polling.'
    source: https://nginx.org/en/docs/http/ngx_http_proxy_module.html#proxy_buffering

  - id: b13
    tier: B
    type: text
    objective: debug
    prompt: 'Access log: `st=200 up=10.65.0.2:8000, 10.65.0.3:8000 ust=504, 200 rt=60.060`. Apa yang terjadi?'
    answers: ['upstream pertama timeout (504), nginx mencoba upstream kedua yang berhasil (200); total 60 detik']
    patterns: ['(?=.*(pertama|first|10\.65\.0\.2).*(timeout|gagal|504))(?=.*(kedua|second|lain|10\.65\.0\.3|retry|coba))']
    tests:
      pass: ['upstream pertama timeout 504, lalu dicoba ke upstream kedua dan sukses 200', 'retry: 10.65.0.2 gagal timeout, 10.65.0.3 berhasil']
      fail: ['dua request berbeda', 'klien mendapat 504', 'cache hit']
    explanation: 'Satu request, dua percobaan upstream, dipisah koma, dengan status sesuai urutan. `rt` (request_time) 60 detik menunjukkan klien menunggu selama connect timeout. Tanpa `$upstream_addr`/`$upstream_status` di log, kejadian ini hanya terlihat sebagai "request 200 yang lambat sekali".'
    source: https://nginx.org/en/docs/http/ngx_http_upstream_module.html#variables

  - id: b14
    tier: B
    type: text
    objective: algorithms
    prompt: Di lab, dua request lambat sedang diproses be1. Dengan `least_conn`, ke mana 6 request cepat berikutnya diarahkan?
    answers: ['ke be2 dan be3 (be1 dihindari)']
    patterns: ['(?=.*be2)(?=.*be3)', '(bukan|hindari|tidak ke).*be1']
    tests:
      pass: ['be2 dan be3', 'be1 dihindari, bagi ke be2/be3']
      fail: ['be1 saja', 'bergiliran ke ketiganya', 'be1 dua kali lebih banyak']
    explanation: 'Di lab: `3 be2, 3 be3`, dan be1 dengan 2 koneksi aktif dilewati. Dengan round robin, sebagian request tetap ke be1 dan ikut mengantre di belakang pekerjaan berat. least_conn menghitung koneksi aktif per server (dengan `zone`, dibagi antar worker).'
    source: https://nginx.org/en/docs/http/ngx_http_upstream_module.html#least_conn

  - id: b15
    tier: B
    type: text
    objective: debug
    prompt: Log menunjukkan banyak `499` dengan `rt` tepat sekitar 30 detik untuk endpoint laporan. Apa kemungkinan penyebabnya?
    answers: ['timeout klien/load balancer di depan 30 detik lebih pendek dari waktu proses laporan']
    patterns: ['(timeout|batas waktu).*(klien|client|lb|load balancer|depan|browser|cloudflare)', '(klien|client|lb|load balancer).*(menyerah|timeout|memutus|tutup)']
    tests:
      pass: ['timeout load balancer di depan 30 detik', 'klien menyerah setelah 30 detik']
      fail: ['php-fpm crash', 'dns', 'sertifikat']
    explanation: 'Di lab, `curl -m 1` → `st=499 rt=1.001`, dan `rt` sama dengan timeout klien. Pola rt yang seragam menunjuk batas waktu di depan nginx: ALB default 60 s, Cloudflare 100 s, atau timeout di JavaScript/HTTP client. Aplikasi tetap bekerja sampai selesai, sia-sia. Pindahkan pekerjaan panjang ke queue dan beri status/notifikasi.'
    source: https://nginx.org/en/docs/http/ngx_http_proxy_module.html

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: trust
    prompt: |
      Laravel berjalan di belakang load balancer (`10.0.1.5`, menghentikan TLS) → nginx (container) → php-fpm.
      Gejala: `request()->ip()` selalu `10.0.1.5`, `route()` menghasilkan `http://...`, dan login di HTTPS kadang gagal
      karena cookie. Tulis perbaikan di nginx dan Laravel, plus cara memverifikasinya (satu poin per baris).
    checklist:
      - id: realip
        label: real_ip di nginx hanya untuk IP LB (set_real_ip_from 10.0.1.5)
        required: true
        patterns: ['set_real_ip_from\s+10\.0\.1\.5']
      - id: headers
        label: Meneruskan Host, X-Forwarded-For, dan Proto (tanpa menimpa https dari LB)
        required: true
        patterns: ['x-forwarded-proto', 'fastcgi_param\s+https', 'http_x_forwarded_proto']
      - id: laravel
        label: Laravel trustProxies dengan IP proxy yang spesifik
        required: true
        patterns: ['trustproxies']
      - id: verify
        label: Verifikasi (log, dd(request()->ip()), url(), header)
        patterns: ['(verifikasi|cek|log|curl|request\(\)->(ip|secure)|url\(\))']
      - id: network
        label: Pastikan app/nginx tidak bisa diakses langsung tanpa LB (firewall/network internal)
        patterns: ['(langsung|bypass|internal|firewall|security group|hanya dari lb|127\.0\.0\.1)']
      - id: no-trust-all
        label: Tidak mempercayai semua sumber (0.0.0.0/0) di nginx real_ip
        forbid: true
        patterns: ['set_real_ip_from\s+0\.0\.0\.0/0']
    pass_score: 0.6
    solution: |
      ```nginx
      # nginx (di belakang LB 10.0.1.5)
      set_real_ip_from 10.0.1.5;            # hanya LB yang dipercaya
      real_ip_header   X-Forwarded-For;
      real_ip_recursive on;
      location ~ \.php$ {
          fastcgi_pass app:9000;
          include fastcgi_params;
          fastcgi_param HTTPS $fwd_https;   # map: $http_x_forwarded_proto = https → on
          fastcgi_param HTTP_X_FORWARDED_PROTO $http_x_forwarded_proto;   # teruskan, jangan timpa dengan $scheme
      }
      ```
      ```php
      // bootstrap/app.php
      ->withMiddleware(function (Middleware $middleware) {
          $middleware->trustProxies(at: ['10.0.1.5']);
      })
      ```
      Verifikasi:
      - Access log nginx menampilkan IP klien asli (`$remote_addr`), dan `$realip_remote_addr` = 10.0.1.5.
      - `request()->ip()` = IP klien, `request()->secure()` = true, `url('/')` = `https://...`.
      - nginx/php-fpm hanya bisa dijangkau dari LB (network internal/security group). Tanpa itu, siapa pun bisa
        mengirim XFF/Proto palsu.

      Temuan lab yang mendasarinya:
      - Tanpa header → `peer` = IP proxy.
      - `$scheme` di proxy dalam menimpa `https` menjadi `http`.
      - `set_real_ip_from` yang terlalu luas → IP palsu `6.6.6.6` diterima.
      - Cookie `Secure` gagal bila aplikasi mengira HTTP (modul 05).
    tests:
      pass:
        - |
          set_real_ip_from 10.0.1.5; real_ip_header X-Forwarded-For;
          teruskan X-Forwarded-Proto dari LB, jangan timpa dengan $scheme
          laravel: $middleware->trustProxies(at: ['10.0.1.5'])
          verifikasi dd(request()->ip()) dan url() harus https
          pastikan nginx tidak bisa diakses langsung, hanya dari lb
        - |
          set_real_ip_from 10.0.1.5
          fastcgi_param HTTPS on bila http_x_forwarded_proto https
          trustProxies at 10.0.1.5
      fail:
        - |
          set_real_ip_from 0.0.0.0/0
          x-forwarded-proto
          trustProxies at *
        - |
          pakai request()->header('X-Forwarded-For') langsung
          redirect ke https di nginx
        - |
          trustProxies
          restart php-fpm
    source: https://laravel.com/docs/requests#configuring-trusted-proxies

  - id: c02
    tier: C
    type: scenario
    objective: failover
    prompt: |
      Satu dari tiga server aplikasi di belakang nginx mati karena hardware. Sejak itu sebagian kecil pengguna mengalami
      halaman yang menggantung sekitar 1 menit lalu terbuka normal. Tulis diagnosis dari log dan perbaikan konfigurasi
      upstream (satu poin per baris).
    checklist:
      - id: logs
        label: Melihat $upstream_addr / $upstream_status / $request_time di access log
        required: true
        patterns: ['upstream_(addr|status)', 'request_time', 'ust=', 'up=']
      - id: diagnose
        label: Menyimpulkan connect timeout default 60s ke server mati (tanpa RST) lalu retry
        required: true
        patterns: ['proxy_connect_timeout', '60 ?(s|detik)']
      - id: timeout
        label: Menurunkan proxy_connect_timeout (mis. 2–5s)
        required: true
        patterns: ['proxy_connect_timeout\s+[1-9]s?\b']
      - id: zone
        label: zone agar status gagal dibagi antar worker
        patterns: ['\bzone\b']
      - id: failparams
        label: max_fails / fail_timeout disesuaikan
        patterns: ['max_fails', 'fail_timeout']
      - id: remove
        label: Mengeluarkan server mati dari upstream (down/hapus) + health check aktif
        patterns: ['\bdown\b', '(hapus|keluarkan|remove).*(server|upstream)', 'health ?check (aktif|active)']
      - id: no-read-timeout
        label: Tidak "memperbaiki" dengan menaikkan proxy_read_timeout
        forbid: true
        patterns: ['proxy_read_timeout\s+\d{3,}']
    pass_score: 0.5
    solution: |
      ```text
      log_format dengan $upstream_addr $upstream_status $request_time →
        up=10.0.2.11:9000, 10.0.2.12:9000 ust=504, 200 rt=60.0   ← percobaan pertama ke server mati, timeout 60 s, lalu retry
      Penyebab: server mati tidak membalas RST (IP hilang/host mati) → nginx menunggu proxy_connect_timeout default 60s;
                tanpa zone, setiap worker (lab: 14) harus "menemukan" sendiri server mati itu → banyak request kena 60 s.
      Perbaikan:
        upstream app { zone app 64k; least_conn;
                       server 10.0.2.11:9000 max_fails=2 fail_timeout=30s;
                       server 10.0.2.12:9000 max_fails=2 fail_timeout=30s;
                       server 10.0.2.13:9000 down; }      # keluarkan yang mati
        proxy_connect_timeout 3s;   (fastcgi_connect_timeout 3s untuk php-fpm)
        health check aktif (HAProxy, nginx Plus, atau load balancer cloud) untuk mendeteksi tanpa korban request
      ```
      Lab mereproduksinya persis:
      - be1 dihentikan → `200/60.060322`, log `ust=504, 200 rt=60.060`.
      - Tanpa `zone`, state dipegang per worker (round robin `17/7/6`). Dengan `zone` → `10/10/10`.

      Menaikkan `proxy_read_timeout` tidak relevan, karena yang lambat adalah fase connect.
    tests:
      pass:
        - |
          tambahkan $upstream_addr dan $upstream_status ke log_format
          request lambat 60 detik: proxy_connect_timeout default 60s ke server mati lalu retry
          proxy_connect_timeout 3s
          zone app 64k di upstream
          max_fails=2 fail_timeout=30s
          tandai server mati dengan down
        - |
          log ust=504, 200 rt=60
          connect timeout 60 s
          set proxy_connect_timeout 2s
      fail:
        - |
          log ust=504, 200
          proxy_connect_timeout default 60s
          proxy_read_timeout 600
          proxy_connect_timeout 5s
        - |
          restart nginx
          tambah server baru
        - |
          proxy_read_timeout 300
    source: https://nginx.org/en/docs/http/ngx_http_proxy_module.html#proxy_connect_timeout

  - id: c03
    tier: C
    type: scenario
    objective: algorithms
    prompt: |
      Aplikasi Laravel akan diskalakan dari 1 menjadi 3 server di belakang nginx. Saat ini session disimpan di `file`
      (default lokal) dan ada fitur notifikasi realtime lewat WebSocket. Rancang konfigurasi load balancing dan perubahan
      aplikasi yang dibutuhkan (satu poin per baris).
    checklist:
      - id: session
        label: Session (dan cache/lock) di store bersama (redis/database), bukan file lokal
        required: true
        patterns: ['session.*(redis|database|memcached)', '(redis|database).*session']
      - id: algo
        label: Algoritma (least_conn/round robin) dengan zone
        required: true
        patterns: ['least_conn', 'round[- ]?robin', '\bzone\b']
      - id: ws
        label: WebSocket di-proxy dengan HTTP/1.1 + Upgrade/Connection
        required: true
        patterns: ['upgrade', 'proxy_http_version\s+1\.1']
      - id: storage
        label: File upload ke storage bersama (S3/volume) bukan disk lokal
        patterns: ['(s3|object storage|storage bersama|nfs|shared|disk bersama)']
      - id: failover
        label: max_fails/connect timeout pendek
        patterns: ['max_fails', 'proxy_connect_timeout', 'fail_timeout']
      - id: scheduler
        label: Scheduler hanya di satu server / onOneServer
        patterns: ['ononeserver', 'scheduler.*(satu|1)', 'schedule:work.*(satu|1)']
      - id: no-ip-hash-only
        label: Tidak mengandalkan ip_hash sambil tetap session file
        forbid: true
        patterns: ['session.*file', 'session_driver\s*=\s*file']
    pass_score: 0.5
    solution: |
      ```text
      Aplikasi:
        SESSION_DRIVER=redis (atau database), CACHE_STORE=redis — session/cache/lock dibagi ketiga server
        FILESYSTEM_DISK=s3 — upload tidak tersimpan di satu server saja
        scheduler hanya di satu server (atau ->onOneServer() dengan cache lock bersama); worker queue boleh di semua
      nginx:
        upstream app { zone app 64k; least_conn;
                       server app1:9000 max_fails=2 fail_timeout=30s; server app2:9000 ...; server app3:9000 ...; }
        proxy_connect_timeout 3s;
        location /app/ (WebSocket Reverb) { proxy_pass http://reverb; proxy_http_version 1.1;
                       proxy_set_header Upgrade $http_upgrade; proxy_set_header Connection "upgrade";
                       proxy_read_timeout 1h; }      # koneksi WS panjang
        proxy_set_header Host/X-Forwarded-*; Laravel trustProxies(at: <IP nginx>)
      ```
      Dengan session `file`, round robin membuat pengguna "logout acak", karena request berikutnya jatuh ke server lain
      yang tidak punya file session-nya. `ip_hash` hanya menutupi masalah itu, dan ikut timpang di belakang NAT (lab:
      satu IP → `20 be1`).

      Temuan lab yang relevan:
      - `least_conn` menghindari server sibuk (lab: `3 be2, 3 be3`).
      - `zone` meratakan pembagian (lab: `10/10/10`).
      - WebSocket tanpa header Upgrade → `"upgrade": null`.
    tests:
      pass:
        - |
          SESSION_DRIVER=redis dan CACHE_STORE=redis
          upstream dengan zone dan least_conn
          location websocket: proxy_http_version 1.1, Upgrade dan Connection upgrade
          FILESYSTEM_DISK=s3
          max_fails=2 dan proxy_connect_timeout 3s
          scheduler dengan onOneServer
        - |
          session database bersama
          round robin dengan zone
          proxy_set_header Upgrade $http_upgrade
      fail:
        - |
          tetap session file
          ip_hash
          proxy_http_version 1.1 upgrade
        - |
          tambah 2 server
          round robin
        - |
          session redis
          ip_hash
    source: https://nginx.org/en/docs/http/load_balancing.html

---

# Reverse Proxy & Load Balancing

Begitu ada proxy di depan aplikasi (nginx, load balancer cloud, Cloudflare), aplikasi tidak lagi melihat klien
secara langsung. IP, Host, dan skema yang ia lihat adalah milik proxy, kecuali proxy meneruskannya lewat header,
dan aplikasi tahu header mana yang boleh dipercaya. Modul ini membahas masalah itu, lalu cara membagi beban ke
banyak backend dan apa yang terjadi saat salah satunya mati.

```mermaid
flowchart LR
    C[klien 10.65.0.10] --> E["edge proxy 10.65.0.81<br/>(TLS di sini)"]
    C -. langsung .-> L
    E --> L["nginx LB 10.65.0.80<br/>real_ip, upstream"]
    L --> B1[be1]
    L --> B2[be2]
    L --> B3["be3 (backup)"]
```

> [!NOTE]
> Lab: nginx 1.27.5 sebagai load balancer (14 worker), nginx kedua sebagai edge proxy, dan tiga backend Python yang
> membalas JSON berisi nama dirinya, IP peer, Host, X-Forwarded-For/Real-IP/Proto, dan header Upgrade yang diterima.

## 1. Apa yang dilihat backend

| Konfigurasi proxy (lab) | `peer` | `host` | `xff` | `xri` | `xfp` |
|---|---|---|---|---|---|
| `proxy_pass` saja | 10.65.0.80 | `rr` | — | — | — |
| \+ header standar | 10.65.0.80 | 10.65.0.80 | 10.65.0.10 | 10.65.0.10 | http |
| Klien mengirim XFF/X-Real-IP palsu `1.2.3.4` | 10.65.0.80 | — | **1.2.3.4**, 10.65.0.10 | 10.65.0.10 | http |

```nginx
proxy_set_header Host              $host;
proxy_set_header X-Real-IP         $remote_addr;                 # ditimpa → tidak bisa dipalsukan
proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;   # ditambah → kiri bisa palsu
proxy_set_header X-Forwarded-Proto $scheme;                      # hanya di proxy TERDEPAN
```

## 2. Mempercayai header dengan benar

| `set_real_ip_from` (lab) | Request | `$remote_addr` di log |
|---|---|---|
| `10.65.0.0/24` (terlalu luas, termasuk klien) | Klien langsung + XFF `6.6.6.6` | **6.6.6.6** (palsu diterima) |
| `10.65.0.81` (edge saja) | Lewat edge | 10.65.0.10 (klien asli) |
| `10.65.0.81` (edge saja) | Klien langsung + XFF `6.6.6.6` | 10.65.0.10 (palsu diabaikan) |

> [!WARNING]
> - Baca XFF dari **kanan**, dan lewati hanya IP proxy milikmu.
> - Percayai proxy berdasarkan IP **spesifik**, bukan "seluruh jaringan internal".
> - Di lab, proxy dalam yang memakai `$scheme` menimpa `https` dari edge menjadi `http`. Proxy di belakang proxy
>   harus **meneruskan** `X-Forwarded-Proto`, bukan menimpanya.

Di Laravel 11+:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(at: ['10.65.0.80']);   // IP proxy, bukan '*' bila app bisa diakses langsung
})
```

## 3. Algoritma load balancing

| Algoritma | Lab | Cocok untuk |
|---|---|---|
| Round robin (default) **tanpa `zone`** | 30 req → 17/7/6 (state per worker) | — |
| Round robin **dengan `zone`** | 30 req → **10/10/10** | Request seragam |
| `weight=3` | 50 req → 31/10/9 | Server tidak setara, canary |
| `least_conn` | Server sibuk dihindari → 3 be2 / 3 be3 | Durasi bervariasi |
| `ip_hash` | 20 req → 20 be1 | Session lokal (lebih baik dihindari) |

```nginx
upstream app {
    zone app 64k;
    least_conn;
    server app1:8000 max_fails=2 fail_timeout=30s;
    server app2:8000 max_fails=2 fail_timeout=30s;
    server app3:8000 backup;
}
```

## 4. Failover, retry, dan timeout

| Kejadian (lab) | Hasil |
|---|---|
| be1 dihentikan (IP hilang) | Satu request: `ust=504, 200 rt=60.060`, menunggu `proxy_connect_timeout` default **60 s**, lalu retry |
| Request berikutnya | Langsung ke server lain (`max_fails=1`, `fail_timeout=10s`) |
| Backend membalas 500 | **Tidak** di-retry: `500 500 500` |
| be1 mati, be3 `backup` | Semua ke be2. be3 baru dipakai saat be2 ikut mati |

```nginx
proxy_connect_timeout 3s;          # bukan 60s
proxy_next_upstream error timeout; # default; hati-hati menambah http_500 untuk POST
```

## 5. WebSocket dan streaming

```nginx
location /ws/ {
    proxy_pass http://app;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_read_timeout 1h;
}
```

- Tanpa ini (lab `/ws-bad/`), backend menerima `"upgrade": null, "conn": "close"`, karena header hop-by-hop
  dibuang.
- `proxy_buffering`: di lab, stream chunked tetap tiba per detik dengan buffering on maupun off. Fungsi utamanya
  adalah membebaskan backend dari klien yang lambat. Untuk SSE/long polling, dokumentasi menyarankan
  `proxy_buffering off` atau `X-Accel-Buffering: no`.

## 6. Log yang membantu

```nginx
log_format lb '$remote_addr real=$realip_remote_addr st=$status up=$upstream_addr ust=$upstream_status rt=$request_time "$request"';
```

| Baris log (lab) | Artinya |
|---|---|
| `st=200 up=A, B ust=504, 200 rt=60.060` | A timeout, retry ke B yang berhasil, klien menunggu 60 s |
| `st=499 ust=- rt=1.001` | Klien menyerah setelah 1 s, sebelum upstream menjawab |
| `6.6.6.6 real=10.65.0.10` | real_ip mempercayai sumber yang salah |

## 7. Ringkasan

| Gejala | Penyebab | Perbaikan |
|---|---|---|
| Semua IP = IP proxy | Header tidak diteruskan/dipercaya | `proxy_set_header` + real_ip/trustProxies |
| IP bisa dipalsukan | Trust terlalu luas / baca XFF dari kiri | Trust IP proxy spesifik |
| URL `http://` / redirect loop | `X-Forwarded-Proto` hilang atau tertimpa | Teruskan dari proxy terdepan |
| Beban tidak rata | Tanpa `zone`, `ip_hash` di belakang NAT | `zone`, `least_conn`, session bersama |
| Hang 60 s saat server mati | `proxy_connect_timeout` default | 2–5 s, `max_fails`, keluarkan server |
| WebSocket gagal | Header Upgrade dibuang | HTTP/1.1 + Upgrade/Connection |
| Banyak 499 | Timeout klien/LB depan | Kerja panjang ke queue |
