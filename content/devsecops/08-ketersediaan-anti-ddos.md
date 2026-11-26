---
format: 1
id: sec-availability-anti-ddos
course:
  slug: devsecops
  title: DevSecOps
  description: Keamanan untuk DevOps dari dua sisi — cara kerja red team dan cara blue team mendeteksi serta menutupnya. Model ancaman, hardening server & container, keamanan web, ketersediaan (anti-DDoS), deteksi, respons insiden, dan keamanan pipeline. Semua praktik di lab terisolasi milik sendiri.
title: Ketersediaan & Anti-DDoS
summary: "Availability adalah huruf A dalam CIA — layanan yang tak bisa diakses sama saja dengan jebol. Sisi red: flood L3/L4 (SYN/UDP) yang menjenuhkan uplink, dan serangan L7 (HTTP flood, slowloris) yang menghabiskan worker. Sisi blue: rate limiting (limit_req token bucket), connection limiting (limit_conn), timeout anti-slowloris, caching/microcache untuk menyerap flood baca, SYN cookies + backlog di kernel, dan — jujur — scrubbing upstream/CDN untuk volumetrik. Semua efeknya dibuktikan di lab."
order: 8
version: 1
status: verified
updated: '2026-11-25'
tested_on:
  - Docker Desktop (Windows), Engine 29.4.1, kernel 6.18 (WSL2) — nginx edge + backend + client (ab) di net dj-sec08
  - nginx:alpine limit_req rate=5r/s burst=10 nodelay, limit_conn 10, proxy_cache, return 444, timeout 5s
  - ab (apache2-utils) sebagai generator beban ke target milik sendiri; sysctl tcp_syncookies via --sysctl
objectives:
  - id: layers
    text: Membedakan serangan L3/L4 (volumetrik) dan L7 (aplikasi)
  - id: ratelimit
    text: Membatasi laju request dengan limit_req (token bucket, burst, nodelay)
  - id: connlimit
    text: Membatasi koneksi (limit_conn) dan menangkal slowloris dengan timeout
  - id: caching
    text: Memakai caching/microcache untuk menyerap flood baca
  - id: kernel
    text: Mengeraskan kernel terhadap SYN flood (syncookies, backlog)
  - id: upstream
    text: Memahami bahwa volumetrik butuh mitigasi upstream (CDN/scrubbing)
sources:
  - title: nginx — limiting the request rate (limit_req)
    url: https://nginx.org/en/docs/http/ngx_http_limit_req_module.html
  - title: nginx — limiting the number of connections (limit_conn)
    url: https://nginx.org/en/docs/http/ngx_http_limit_conn_module.html
  - title: nginx blog — rate limiting with NGINX
    url: https://blog.nginx.org/blog/rate-limiting-nginx
  - title: nginx — content caching (proxy_cache)
    url: https://docs.nginx.com/nginx/admin-guide/content-cache/content-caching/
  - title: Cloudflare — what is a DDoS attack
    url: https://www.cloudflare.com/learning/ddos/what-is-a-ddos-attack/
  - title: Linux — TCP SYN cookies (tcp_syncookies)
    url: https://www.kernel.org/doc/Documentation/networking/ip-sysctl.txt
  - title: MITRE ATT&CK — Network Denial of Service (T1498)
    url: https://attack.mitre.org/techniques/T1498/
  - title: MITRE ATT&CK — Endpoint Denial of Service (T1499)
    url: https://attack.mitre.org/techniques/T1499/
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: layers
    prompt: Apa yang membedakan DDoS dari DoS biasa?
    answers: ['distributed — serangan datang dari banyak sumber sekaligus']
    patterns: ['(banyak|ribuan|berbagai|tersebar)[^\n]*(sumber|host|ip|mesin|komputer|bot)', 'distributed', 'botnet', 'dari banyak tempat']
    tests:
      fail: ['lebih cepat saja', 'memakai enkripsi', 'hanya dari satu ip', 'menyerang database']
    explanation: 'DoS (Denial of Service) membuat layanan tak bisa diakses. **DDoS** (Distributed DoS) melakukannya dari **banyak sumber sekaligus** — biasanya botnet ribuan mesin. Konsekuensinya besar untuk pertahanan: kamu tidak bisa sekadar "blokir satu IP jahat", karena serangan datang dari ribuan IP (sering tersebar global, sebagian ter-spoof). Inilah mengapa mitigasi bertingkat — dari rate limit per-IP sampai scrubbing upstream — diperlukan. Lihat ATT&CK T1498 (Network DoS) & T1499 (Endpoint DoS).'
    source: https://www.cloudflare.com/learning/ddos/what-is-a-ddos-attack/

  - id: a02
    tier: A
    type: text
    objective: layers
    prompt: Apa beda serangan volumetrik L3/L4 (mis. SYN/UDP flood) dengan serangan aplikasi L7 (mis. HTTP flood)?
    answers: ['l3/l4 menjenuhkan bandwidth/koneksi jaringan; l7 menghabiskan resource aplikasi dengan request yang tampak sah']
    patterns: ['(l3|l4|volumetrik|jaringan)[^\n]*(bandwidth|uplink|koneksi|paket)', '(l7|aplikasi|http)[^\n]*(resource|cpu|worker|request|sah|aplikasi)', 'bandwidth[^\n]*request|paket[^\n]*http']
    tests:
      fail: ['tidak ada bedanya', 'l7 lebih lemah dari l3', 'keduanya hanya soal bandwidth', 'l3 menyerang dns saja']
    explanation: '**L3/L4 (volumetrik)** membanjiri lapisan jaringan/transport — SYN flood (isi tabel koneksi half-open), UDP/amplification (jenuhkan bandwidth uplink). **L7 (aplikasi)** mengirim request HTTP yang tampak sah tapi mahal (query berat, login berulang, slowloris) untuk menghabiskan CPU/worker/koneksi aplikasi — sering volumenya kecil tapi mematikan. Pertahanannya beda lapisan: L7 dilawan di reverse proxy/app (rate limit, cache, timeout); volumetrik L3/L4 butuh kapasitas & scrubbing **upstream** karena uplink-mu bisa penuh sebelum paket sampai ke server.'
    source: https://attack.mitre.org/techniques/T1498/

  - id: a03
    tier: A
    type: text
    objective: ratelimit
    prompt: Direktif nginx untuk membatasi **laju request** per klien (mis. 5 request/detik) adalah?
    answers: ['limit_req']
    patterns: ['limit_req(_zone)?']
    tests:
      fail: ['limit_conn', 'limit_rate', 'client_max_body_size', 'keepalive_timeout']
    explanation: '`limit_req_zone` mendefinisikan zona + laju (mis. `limit_req_zone $binary_remote_addr zone=reqs:10m rate=5r/s`), dan `limit_req zone=reqs ...` menerapkannya di `location`. Di lab, dengan `rate=5r/s burst=10 nodelay`, flood 200 request (concurrency 20) menghasilkan **189 dari 200 ditolak 503** — hanya ~11 (burst + token) yang lolos. Tanpa limit, backend menerima **seluruh** 200. Jangan tertukar dengan `limit_rate` (membatasi *bandwidth* per koneksi, bukan laju request).'
    source: https://nginx.org/en/docs/http/ngx_http_limit_req_module.html

  - id: a04
    tier: A
    type: text
    objective: ratelimit
    prompt: Algoritma di balik `limit_req` nginx — "ember" yang bocor dengan laju tetap dan menolak saat penuh — disebut apa?
    answers: ['leaky bucket']
    patterns: ['leaky bucket', 'token bucket', 'ember bocor', '(ember|bucket)[^\n]*(bocor|token)']
    tests:
      fail: ['round robin', 'least connections', 'random drop', 'fifo murni']
    explanation: '`limit_req` memakai algoritma **leaky bucket**: request masuk ke ember yang "bocor" dengan laju tetap (`rate`). Jika ember penuh, request baru ditolak (atau diantre bila ada `burst`). Ini menghaluskan lonjakan jadi laju stabil ke backend. Konsep ini sama dengan **token bucket** (sudut pandang token yang terisi ulang). Yang penting dipahami: laju ke backend dijaga konstan, apa pun lonjakan di depan.'
    source: https://blog.nginx.org/blog/rate-limiting-nginx

  - id: a05
    tier: A
    type: text
    objective: ratelimit
    prompt: Parameter `burst` pada `limit_req` berfungsi untuk apa?
    answers: ['mengizinkan lonjakan singkat di atas rate untuk diantre, bukan langsung ditolak']
    patterns: ['(lonjakan|burst|ledakan|sesaat|singkat)[^\n]*(izin|antre|queue|tolerir|boleh)', 'antre(kan)?[^\n]*(request|kelebihan)', '(toleransi|buffer)[^\n]*(lonjakan|rate)']
    tests:
      fail: ['menaikkan rate permanen', 'mematikan rate limit', 'membatasi bandwidth', 'menghapus log']
    explanation: '`burst=N` memberi *buffer antrean* berukuran N untuk request yang melebihi `rate` sesaat — alih-alih langsung ditolak, hingga N request diantre dan dilayani sesuai laju. Ini mencegah false positive pada trafik sah yang "menggumpal" (mis. halaman memuat banyak aset). Di lab, `rate=5r/s burst=10` → kira-kira 10 (burst) + beberapa token awal lolos, sisanya 503. Pilih `burst` sesuai pola trafik normalmu, lalu pantau.'
    source: https://blog.nginx.org/blog/rate-limiting-nginx

  - id: a06
    tier: A
    type: text
    objective: ratelimit
    prompt: Tambahan `nodelay` pada `limit_req ... burst=10 nodelay` mengubah perilaku burst menjadi apa?
    answers: ['melayani burst segera (tanpa menahan/menjeda), bukan mengantre dengan jeda']
    patterns: ['(segera|langsung|tanpa (jeda|delay|tunda))[^\n]*(burst|dilayani|layani)', 'tidak (di)?(antre|tunda|jeda)[^\n]*(burst)?', 'burst[^\n]*(segera|langsung|tanpa jeda)']
    tests:
      fail: ['menolak semua burst', 'menambah delay besar', 'mematikan burst', 'menggandakan rate']
    explanation: 'Tanpa `nodelay`, request burst **diantre dan dilayani ber-jeda** sesuai `rate` (lambat tapi tidak ditolak). Dengan `nodelay`, slot burst **dilayani segera** (tanpa penundaan), sambil slot tetap "terisi ulang" dengan laju `rate`. `nodelay` biasanya yang diinginkan untuk API: user tidak merasakan lag saat normal, tapi penyerang yang melebihi burst tetap kena 503. Di lab, konfigurasi memakai `nodelay` sehingga ~11 request pertama lolos instan dan sisanya langsung ditolak.'
    source: https://blog.nginx.org/blog/rate-limiting-nginx

  - id: a07
    tier: A
    type: text
    objective: connlimit
    prompt: Direktif nginx untuk membatasi **jumlah koneksi bersamaan** dari satu klien adalah?
    answers: ['limit_conn']
    patterns: ['limit_conn(_zone)?']
    tests:
      fail: ['limit_req', 'limit_rate', 'worker_connections', 'keepalive_requests']
    explanation: '`limit_conn_zone $binary_remote_addr zone=conns:10m;` + `limit_conn conns 10;` membatasi koneksi **simultan** per IP. Berguna melawan klien yang membuka banyak koneksi (slowloris, scraper agresif). Di lab, dengan `limit_conn 10` dan 30 unduhan lambat bersamaan dari satu IP, **20 ditolak 503** ("limiting connections by zone conns"), 10 dilayani. Beda dengan `limit_req` (laju request/waktu); `limit_conn` membatasi koneksi hidup pada satu saat.'
    source: https://nginx.org/en/docs/http/ngx_http_limit_conn_module.html

  - id: a08
    tier: A
    type: text
    objective: connlimit
    prompt: Serangan yang membuka banyak koneksi lalu mengirim request **sangat lambat/tak selesai** untuk menahan worker server disebut apa?
    answers: ['slowloris']
    patterns: ['slowloris', 'slow loris', 'slow (http|header|read|post)', 'koneksi lambat']
    tests:
      fail: ['syn flood', 'udp amplification', 'sql injection', 'brute force']
    explanation: 'Slowloris membuka banyak koneksi dan mengirim header/body **sepotong-sepotong, sangat lambat**, tidak pernah menyelesaikan request — menahan slot worker/koneksi terus terbuka hingga server kehabisan kapasitas melayani klien sah. Volumenya kecil (hemat bandwidth penyerang) tapi efektif pada server yang menunggu request selesai tanpa batas waktu. Ini serangan **L7** klasik terhadap availability.'
    source: https://attack.mitre.org/techniques/T1499/

  - id: a09
    tier: A
    type: text
    objective: connlimit
    prompt: Pengaturan nginx apa yang paling langsung menangkal slowloris dengan memutus request yang terlalu lambat?
    answers: ['client_header_timeout dan client_body_timeout']
    patterns: ['client_(header|body)_timeout', 'timeout[^\n]*(header|body|klien|request)', '(batas|limit) waktu[^\n]*(request|header|body)']
    tests:
      fail: ['worker_processes auto', 'keepalive 65', 'gzip on', 'sendfile on']
    explanation: '`client_header_timeout` dan `client_body_timeout` (mis. `5s`) memutus koneksi yang tidak mengirim header/body lengkap dalam batas waktu — tepat membunuh slowloris yang menahan koneksi "menetes". Di lab, request dengan header tak lengkap yang "menggantung" ditutup nginx dengan **408 (Request Timeout)**. Lengkapi dengan `limit_conn` (batasi koneksi per IP) agar satu penyerang tak bisa membuka banyak koneksi lambat sekaligus.'
    source: https://nginx.org/en/docs/http/ngx_http_core_module.html

  - id: a10
    tier: A
    type: text
    objective: caching
    prompt: Mengapa caching di reverse proxy membantu bertahan terhadap flood HTTP (banyak request baca yang sama)?
    answers: ['response dilayani dari cache tanpa memukul backend, jadi backend tetap ringan']
    patterns: ['(dari|lewat) cache[^\n]*(tanpa|tidak)[^\n]*(backend|origin|app|database)', 'backend[^\n]*(tidak|tak|ringan|terlindung|hemat)', '(tanpa|tak) (memukul|membebani|hit)[^\n]*(backend|origin)']
    tests:
      fail: ['cache memperlambat server', 'cache membuat backend bekerja dua kali', 'tidak ada hubungannya', 'cache menonaktifkan rate limit']
    explanation: 'Jika banyak klien meminta konten sama, proxy melayaninya dari **cache** — backend/origin hanya diproses sekali per periode. Di lab, `/static` pertama `X-Cache-Status: MISS` lalu `HIT`, `HIT` — permintaan berikutnya tidak menyentuh backend. Flood baca jadi masalah murah (serve dari memori/disk cache) alih-alih mahal (render + query DB tiap request). Inilah kenapa men-cache konten yang bisa di-cache adalah pertahanan availability yang murah dan ampuh.'
    source: https://docs.nginx.com/nginx/admin-guide/content-cache/content-caching/

  - id: a11
    tier: A
    type: text
    objective: caching
    prompt: Variabel nginx yang menunjukkan status cache (HIT/MISS/EXPIRED) sebuah response adalah?
    answers: ['$upstream_cache_status']
    patterns: ['\$?upstream_cache_status']
    tests:
      fail: ['$request_time', '$status', '$upstream_addr', '$cache_control']
    explanation: '`$upstream_cache_status` berisi `MISS`, `HIT`, `EXPIRED`, `STALE`, `BYPASS`, dll. Ekspos lewat `add_header X-Cache-Status $upstream_cache_status;` untuk memverifikasi cache bekerja. Di lab, header ini menunjukkan `MISS` pada request pertama lalu `HIT` berikutnya — bukti backend tidak lagi disentuh. Monitoring rasio HIT berguna: HIT rendah saat flood berarti cache-mu tidak menangkap pola serangan (mungkin query string unik).'
    source: https://docs.nginx.com/nginx/admin-guide/content-cache/content-caching/

  - id: a12
    tier: A
    type: text
    objective: kernel
    prompt: Fitur kernel Linux yang menangkal SYN flood dengan tidak menyimpan state koneksi half-open sampai handshake tuntas adalah?
    answers: ['syn cookies']
    patterns: ['syn[- ]?cookies?', 'tcp_syncookies']
    tests:
      fail: ['tcp fast open', 'tcp window scaling', 'nagle algorithm', 'tcp keepalive']
    explanation: 'SYN flood mengirim banyak SYN tanpa menyelesaikan handshake, mengisi antrean koneksi half-open hingga koneksi sah ditolak. **SYN cookies** (`net.ipv4.tcp_syncookies=1`) membuat server meng-encode state ke dalam sequence number SYN-ACK alih-alih menyimpannya, sehingga antrean tak bisa dipenuhi — state baru dibangun setelah ACK sah datang. Di lab, container Docker Desktop punya `tcp_syncookies=1` secara default. Ini pertahanan L3/L4 di kernel, pelengkap (bukan pengganti) kapasitas uplink.'
    source: https://www.kernel.org/doc/Documentation/networking/ip-sysctl.txt

  - id: a13
    tier: A
    type: command
    objective: kernel
    prompt: Tampilkan nilai kernel `net.ipv4.tcp_syncookies` saat ini dengan `sysctl`.
    answers: ['sysctl net.ipv4.tcp_syncookies']
    patterns: ['^sysctl[ \t]+net\.ipv4\.tcp_syncookies$', '^cat[ \t]+/proc/sys/net/ipv4/tcp_syncookies$']
    tests:
      pass:
        - 'sysctl net.ipv4.tcp_syncookies'
        - 'cat /proc/sys/net/ipv4/tcp_syncookies'
      fail:
        - 'sysctl -w net.ipv4.tcp_syncookies=1'
        - 'sysctl net.ipv4.ip_forward'
        - 'echo net.ipv4.tcp_syncookies'
    explanation: '`sysctl net.ipv4.tcp_syncookies` membaca nilai (0/1/2); setara `cat /proc/sys/net/ipv4/tcp_syncookies`. `1` = aktif saat antrean penuh (default umum). Di lab nilainya `1`. Untuk mengubah sementara pakai `sysctl -w ...=1`, dan permanen lewat `/etc/sysctl.d/`. Catatan: di dalam container, `/proc/sys` sering read-only — setel di host atau pakai `--sysctl` saat `docker run` (lab: `docker run --sysctl net.ipv4.tcp_syncookies=1`).'
    source: https://www.kernel.org/doc/Documentation/networking/ip-sysctl.txt

  - id: a14
    tier: A
    type: text
    objective: upstream
    prompt: Untuk serangan volumetrik besar yang bisa menjenuhkan uplink server, pertahanan paling efektif ada di mana?
    answers: ['di upstream — cdn/scrubbing/provider, bukan di server itu sendiri']
    patterns: ['upstream', 'cdn', 'scrubbing', 'cloudflare|akamai|provider', '(sebelum|di depan|di luar)[^\n]*(server|uplink|origin)', 'penyedia (jaringan|cloud|isp)']
    tests:
      fail: ['iptables di server saja', 'menaikkan ram server', 'mematikan log', 'firewall lokal sudah cukup']
    explanation: 'Jika banjir paket cukup besar untuk **menjenuhkan uplink/bandwidth**-mu, paket sudah memenuhi pipa sebelum sampai ke server — `iptables` atau rate limit di kotak itu **tidak menolong** (CPU/NIC sudah tenggelam). Mitigasi harus **upstream**: CDN/anycast (Cloudflare, Akamai) dan layanan *scrubbing* yang menyerap & menyaring di jaringan berkapasitas besar, atau proteksi DDoS dari cloud provider. Jujurlah soal ini: pertahanan host (bab berikut) melawan L7 dan flood kecil, tapi volumetrik butuh kapasitas yang tak dimiliki satu server.'
    source: https://www.cloudflare.com/learning/ddos/what-is-a-ddos-attack/

  - id: a15
    tier: A
    type: text
    objective: connlimit
    prompt: Dalam nginx, `return 444` pada sebuah `location` membuat server melakukan apa terhadap request yang cocok?
    answers: ['menutup koneksi tanpa mengirim response apa pun']
    patterns: ['(tutup|putus|drop|close)[^\n]*koneksi', '(tanpa|tidak)[^\n]*(response|balasan|kirim|header)', 'no response|empty reply']
    tests:
      fail: ['mengirim halaman 404', 'redirect ke https', 'mengembalikan 200', 'menampilkan error 500']
    explanation: '`return 444` adalah kode non-standar khusus nginx yang **menutup koneksi tanpa mengirim response** — klien dapat "empty reply". Di lab, `curl` ke `/badbot` menghasilkan `http_code=000`, `size=0`, koneksi diputus. Berguna untuk membuang pola jelas-jahat (user-agent bot tertentu, path serangan) semurah mungkin tanpa membuang resource membuat response. Pakai hati-hati — pola harus benar-benar spesifik agar tidak memutus trafik sah.'
    source: https://nginx.org/en/docs/http/ngx_http_rewrite_module.html

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: command
    objective: ratelimit
    prompt: Uji sintaks konfigurasi nginx sebelum menerapkannya.
    answers: ['nginx -t']
    patterns: ['^(?=.*[ \t]-t([ \t]|$))nginx([ \t]|$)']
    tests:
      pass:
        - 'nginx -t'
        - 'nginx -t -c /etc/nginx/nginx.conf'
      fail:
        - 'nginx -s reload'
        - 'nginx'
        - 'systemctl restart nginx'
    explanation: '`nginx -t` memvalidasi konfigurasi tanpa menerapkannya — wajib dijalankan sebelum reload, apalagi saat sedang diserang: config rusak + reload = downtime yang kamu timbulkan sendiri. Di lab, `nginx -t` mengembalikan "syntax is ok" / "test is successful". Alur aman: edit → `nginx -t` → `nginx -s reload`.'
    source: https://nginx.org/en/docs/switches.html

  - id: b02
    tier: B
    type: command
    objective: ratelimit
    prompt: Muat ulang konfigurasi nginx yang sudah diuji **tanpa menjatuhkan koneksi** (graceful).
    answers: ['nginx -s reload']
    patterns: ['^(?=.*[ \t]-s[ \t]+reload([ \t]|$))nginx([ \t]|$)']
    tests:
      pass:
        - 'nginx -s reload'
      fail:
        - 'nginx -t'
        - 'nginx -s stop'
        - 'systemctl restart nginx'
        - 'kill -9 nginx'
    explanation: '`nginx -s reload` memberi sinyal master untuk memuat config baru dan memunculkan worker baru secara bertahap; worker lama menyelesaikan request yang sedang jalan lalu keluar — **tanpa downtime**. Jauh lebih baik daripada `restart` (menjatuhkan koneksi). Di lab, reload dipakai untuk menerapkan aturan `limit_conn`/`limit_rate` baru tanpa memutus unduhan yang berjalan. Selalu `nginx -t` dulu.'
    source: https://nginx.org/en/docs/control.html

  - id: b03
    tier: B
    type: command
    objective: ratelimit
    prompt: Beri beban uji **200 request, 20 bersamaan** ke endpoint milikmu sendiri `http://localhost/api/` dengan ApacheBench.
    answers: ['ab -n 200 -c 20 http://localhost/api/']
    patterns: ['^(?=.*[ \t]-n[ \t]+[0-9]+)(?=.*[ \t]-c[ \t]+[0-9]+)ab[ \t].*https?://']
    tests:
      pass:
        - 'ab -n 200 -c 20 http://localhost/api/'
        - 'ab -c 20 -n 200 http://localhost/api/'
      fail:
        - 'ab http://localhost/api/'
        - 'ab -n 200 http://localhost/api/'
        - 'curl http://localhost/api/'
    explanation: '`ab -n <total> -c <concurrency> <url>` (ApacheBench) mengukur bagaimana endpoint bertahan di bawah beban. Di lab, `ab -n 200 -c 20` ke endpoint ter-rate-limit menghasilkan 189 non-2xx (503) sementara endpoint tanpa proteksi melayani semua 200 — bukti rate limit bekerja. Gunakan **hanya pada target milik sendiri** untuk uji kapasitas/regression aturan, bukan menyerang sistem orang lain. Alat lain: `wrk`, `hey`, `vegeta`.'
    source: https://httpd.apache.org/docs/current/programs/ab.html

  - id: b04
    tier: B
    type: text
    objective: ratelimit
    prompt: Mengapa zona rate limit biasanya memakai key `$binary_remote_addr` (alamat IP klien)?
    answers: ['agar batas diterapkan per-ip klien, bukan global, dan hemat memori']
    patterns: ['per[- ]?ip', '(tiap|setiap|masing-masing) (ip|klien)', '(batas|limit)[^\n]*(per|tiap)[^\n]*(ip|klien)', 'hemat memori|lebih ringkas']
    tests:
      fail: ['agar semua user berbagi satu batas', 'untuk memblokir semua ip', 'karena wajib pakai domain', 'agar rate limit mati']
    explanation: 'Key menentukan "batas berlaku per apa". `$binary_remote_addr` (bentuk biner `$remote_addr`, lebih hemat memori) membuat batas berlaku **per IP klien** — tiap IP punya jatahnya sendiri, bukan satu jatah global yang bisa dihabiskan satu penyerang. Catatan penting: di belakang proxy/CDN, `$remote_addr` bisa jadi IP proxy — pakai `$http_x_forwarded_for`/`real_ip` yang sudah divalidasi agar key benar-benar IP klien asli.'
    source: https://blog.nginx.org/blog/rate-limiting-nginx

  - id: b05
    tier: B
    type: text
    objective: ratelimit
    prompt: Kode status mana yang lebih tepat dikembalikan untuk request yang kena rate limit, dan bagaimana mengaturnya di nginx?
    answers: ['429 too many requests, via limit_req_status 429']
    patterns: ['429', 'too many requests', 'limit_req_status']
    tests:
      fail: ['200 ok', '404 not found', '500 server error', '301 redirect']
    explanation: 'Default nginx mengembalikan **503** saat `limit_req` menolak, tapi **429 Too Many Requests** lebih tepat secara semantik (memberi tahu klien/alat bahwa ini soal laju, bukan server tumbang) dan ramah untuk retry/backoff. Atur dengan `limit_req_status 429;` (dan `limit_conn_status 429;`). Di lab kita memakai 503 sebagai default; di produksi pertimbangkan 429 + header `Retry-After` agar klien sah mundur dengan sopan.'
    source: https://nginx.org/en/docs/http/ngx_http_limit_req_module.html

  - id: b06
    tier: B
    type: text
    objective: ratelimit
    prompt: Sebutkan satu keterbatasan rate limit per-IP ketika menghadapi DDoS yang sesungguhnya.
    answers: ['ribuan ip berbeda (botnet) masing-masing di bawah batas, atau banyak user sah di balik satu nat ikut kena']
    patterns: ['(ribuan|banyak|berbagai) ip[^\n]*(bawah|di bawah|masing)', 'botnet[^\n]*(tiap|masing|bawah)', 'nat[^\n]*(berbagi|banyak user|satu ip)', 'ip (di-?)?spoof', 'per-ip (tidak|tak) (cukup|mempan)']
    tests:
      fail: ['rate limit selalu cukup', 'tidak ada keterbatasan', 'botnet mudah diblokir per ip', 'nat tidak berpengaruh']
    explanation: 'Rate limit per-IP mengasumsikan penyerang terbatas pada sedikit IP. Dalam DDoS nyata: **ribuan IP botnet** masing-masing mengirim di bawah ambang (lolos), dan sebaliknya **banyak user sah di balik satu NAT/kampus/kantor** berbagi satu IP bisa ikut terblokir (false positive). IP juga bisa di-spoof pada serangan L3/L4. Karena itu rate limit per-IP adalah **satu lapisan** — lengkapi dengan deteksi perilaku, CAPTCHA/JS-challenge, reputasi IP, dan scrubbing upstream.'
    source: https://www.cloudflare.com/learning/ddos/what-is-a-ddos-attack/

  - id: b07
    tier: B
    type: command
    objective: connlimit
    prompt: Cari entri rate-limit pada log error nginx `/var/log/nginx/error.log` dengan grep (cari teks `limiting requests`).
    answers: ['grep "limiting requests" /var/log/nginx/error.log']
    patterns: ['^grep[ \t].*limiting requests[^\n]*/var/log/nginx/error\.log', '^grep[ \t].*limiting requests']
    tests:
      pass:
        - 'grep "limiting requests" /var/log/nginx/error.log'
        - 'grep -i "limiting requests" /var/log/nginx/error.log'
      fail:
        - 'cat /var/log/nginx/error.log'
        - 'grep 200 /var/log/nginx/access.log'
        - 'tail /var/log/nginx/error.log'
    explanation: 'Saat `limit_req` menolak, nginx menulis ke error.log: `limiting requests, excess: ... by zone "reqs", client: <IP> ...`. Di lab, flood menghasilkan **189 baris** ini (cocok dengan 189 request yang ditolak), dan `limit_conn` menulis `limiting connections by zone "conns"`. Grep baris ini (atau hitung dengan `grep -c`) untuk mendeteksi & mengukur serangan, dan untuk tuning ambang. Alirkan log ini ke monitoring (modul 09).'
    source: https://nginx.org/en/docs/http/ngx_http_limit_req_module.html

  - id: b08
    tier: B
    type: text
    objective: caching
    prompt: Apa itu "microcaching" dan kenapa berguna untuk konten dinamis saat lonjakan/serangan?
    answers: ['men-cache response dinamis untuk waktu sangat singkat (mis. 1 detik) agar backend hanya diproses sekali per detik']
    patterns: ['(cache|simpan)[^\n]*(singkat|sebentar|1 ?detik|detik|pendek)', 'micro[- ]?cache[^\n]*(detik|singkat|sebentar|sekali|backend|pendek|1s)', '(backend|origin)[^\n]*(sekali|satu kali)[^\n]*(detik|periode)', 'dinamis[^\n]*cache']
    tests:
      fail: ['mematikan cache', 'cache selamanya tanpa kedaluwarsa', 'cache hanya untuk gambar', 'microcache memperlambat server']
    explanation: 'Microcaching men-cache bahkan konten dinamis untuk periode sangat singkat (mis. `proxy_cache_valid 200 1s`). Efeknya dramatis saat lonjakan: untuk endpoint populer, backend diproses **maksimal sekali per detik** sementara ribuan request lain dilayani dari cache — perbedaan yang tak terlihat user (data basi ≤1 detik) tapi menyelamatkan origin. Cocok untuk halaman yang sama bagi banyak orang (beranda, daftar produk), bukan untuk data per-user.'
    source: https://docs.nginx.com/nginx/admin-guide/content-cache/content-caching/

  - id: b09
    tier: B
    type: text
    objective: caching
    prompt: Direktif `proxy_cache_use_stale` memungkinkan nginx melakukan apa saat backend error/overload?
    answers: ['menyajikan versi cache lama (stale) alih-alih gagal']
    patterns: ['(sajikan|serve|kirim|pakai)[^\n]*(stale|cache lama|basi|kedaluwarsa)', 'stale[^\n]*(daripada|alih|bukan)[^\n]*(error|gagal|500)', 'data (lama|basi)[^\n]*(error|backend)']
    tests:
      fail: ['menghapus cache', 'menolak semua request', 'mematikan backend', 'menggandakan beban backend']
    explanation: '`proxy_cache_use_stale error timeout updating http_500 ...` menyuruh nginx menyajikan **salinan cache lama (stale)** ketika backend error, timeout, atau sedang diperbarui — user tetap dapat halaman (sedikit basi) alih-alih error 5xx. Ditambah `proxy_cache_lock on` (hanya satu request yang menembus ke backend untuk mengisi cache; sisanya menunggu) ini menangkal **cache stampede** — ledakan request serempak ke backend tepat saat entri kedaluwarsa. Keduanya menjaga availability saat backend tertekan.'
    source: https://docs.nginx.com/nginx/admin-guide/content-cache/content-caching/

  - id: b10
    tier: B
    type: command
    objective: kernel
    prompt: Aktifkan SYN cookies kernel secara runtime (set `net.ipv4.tcp_syncookies` ke `1`) dengan `sysctl`.
    answers: ['sysctl -w net.ipv4.tcp_syncookies=1']
    patterns: ['^(?=.*[ \t]-w([ \t]|$))sysctl[ \t].*net\.ipv4\.tcp_syncookies=1$']
    tests:
      pass:
        - 'sysctl -w net.ipv4.tcp_syncookies=1'
      fail:
        - 'sysctl net.ipv4.tcp_syncookies'
        - 'sysctl -w net.ipv4.tcp_syncookies=0'
        - 'echo 1 net.ipv4.tcp_syncookies'
    explanation: '`sysctl -w net.ipv4.tcp_syncookies=1` mengaktifkan SYN cookies saat itu juga (hilang saat reboot). Untuk permanen, tulis di `/etc/sysctl.d/99-hardening.conf` lalu `sysctl -p`. Di dalam container, `/proc/sys/net` umumnya read-only — setel di **host**, atau untuk container tertentu pakai `docker run --sysctl net.ipv4.tcp_syncookies=1` (lab membuktikan ini berhasil mengeset nilai di namespace net container). Kaitkan juga dengan `net.core.somaxconn` dan `tcp_max_syn_backlog`.'
    source: https://www.kernel.org/doc/Documentation/networking/ip-sysctl.txt

  - id: b11
    tier: B
    type: text
    objective: kernel
    prompt: Parameter kernel `net.core.somaxconn` dan `net.ipv4.tcp_max_syn_backlog` menaikkan apa untuk menahan lonjakan koneksi?
    answers: ['ukuran antrean koneksi (backlog) yang menunggu diterima/diselesaikan']
    patterns: ['(antrean|queue|backlog)[^\n]*(koneksi|connection)', 'backlog', '(kapasitas|ukuran)[^\n]*(antrean|queue|koneksi)']
    tests:
      fail: ['kecepatan cpu', 'jumlah ram', 'ukuran disk', 'bandwidth jaringan']
    explanation: '`net.core.somaxconn` membatasi antrean koneksi yang sudah established menunggu di-`accept()` aplikasi; `tcp_max_syn_backlog` membatasi antrean koneksi half-open (SYN diterima, belum selesai handshake). Menaikkannya memberi ruang menampung lonjakan koneksi sah tanpa menolak, dan bekerja bersama SYN cookies. Di lab, `somaxconn` default container `4096`. Catatan: aplikasi juga harus meminta backlog besar saat `listen()` (mis. `listen 80 backlog=...` di nginx) agar nilai kernel terpakai.'
    source: https://www.kernel.org/doc/Documentation/networking/ip-sysctl.txt

  - id: b12
    tier: B
    type: text
    objective: upstream
    prompt: Mengapa `iptables`/firewall di server yang diserang **tidak bisa** menghentikan flood volumetrik yang menjenuhkan uplink?
    answers: ['paket sudah memenuhi bandwidth/uplink sebelum sampai ke server, jadi memblokirnya di server tak mengosongkan pipa']
    patterns: ['(uplink|bandwidth|pipa|pipe|jalur)[^\n]*(penuh|jenuh|habis|tenggelam)', 'paket[^\n]*(sampai|tiba)[^\n]*(server|sudah)', '(sebelum|sudah) (sampai|masuk)[^\n]*server', 'nic|cpu[^\n]*(penuh|tenggelam|overload)']
    tests:
      fail: ['iptables selalu cukup', 'firewall lokal menghentikan semua ddos', 'bandwidth tidak relevan', 'cukup drop di server']
    explanation: 'Firewall di server hanya bisa memproses paket yang **sudah tiba** — dan untuk tiba, paket sudah melewati (dan memenuhi) uplink/bandwidth-mu serta membebani NIC/CPU. Saat pipa sudah jenuh, trafik sah tak bisa masuk sama sekali, tak peduli seberapa cepat kamu `DROP`. Karena itu volumetrik harus disaring **sebelum** mencapaimu — di jaringan upstream berkapasitas besar (CDN/anycast/scrubbing). Firewall & rate limit host tetap berguna untuk L7 dan flood kecil, tapi bukan obat volumetrik.'
    source: https://attack.mitre.org/techniques/T1498/

  - id: b13
    tier: B
    type: text
    objective: upstream
    prompt: Sebutkan dua mekanisme upstream/arsitektur untuk menyerap atau menyebar beban DDoS volumetrik.
    answers: ['cdn/anycast dan layanan scrubbing; ditambah autoscaling kapasitas']
    patterns: ['anycast', 'cdn', 'scrubbing', 'autoscal(e|ing)|auto-?scale', 'cloudflare|akamai', 'load balanc[^\n]*(global|geo)']
    tests:
      fail: ['satu server besar saja', 'matikan logging', 'pakai http saja', 'hapus rate limit']
    explanation: 'Mekanisme upstream: **CDN + Anycast** (menyebar trafik ke banyak PoP global — serangan terpecah dan diserap dekat sumbernya, bukan menuju satu origin); **traffic scrubbing** (trafik dialihkan melalui pusat pembersih yang menyaring paket jahat lalu meneruskan yang bersih); dan **autoscaling** kapasitas untuk L7. Dipadukan dengan rate limit/cache di origin, ini membentuk pertahanan availability berlapis — tak ada satu kontrol tunggal yang cukup.'
    source: https://www.cloudflare.com/learning/ddos/what-is-a-ddos-attack/

  - id: b14
    tier: B
    type: text
    objective: ratelimit
    prompt: Apa risiko utama rate limit yang terlalu ketat, dan bagaimana menyeimbangkannya?
    answers: ['memblokir user sah (false positive); setel burst/rate dari trafik nyata dan pantau sebelum diperketat']
    patterns: ['(user|pengguna|trafik) sah[^\n]*(blokir|terblokir|kena|tolak)', 'false positive', '(setel|atur|tuning)[^\n]*(burst|rate|ambang)[^\n]*(nyata|baseline|normal|pantau)', 'pantau|monitor[^\n]*sebelum']
    tests:
      fail: ['tidak ada risiko sama sekali', 'perketat sampai nol', 'rate limit tidak pernah salah', 'abaikan trafik normal']
    explanation: 'Rate limit yang terlalu ketat **menolak user sah** (false positive) — halaman memuat banyak aset, aplikasi mobile yang retry, atau banyak user di balik satu NAT. Seimbangkan: ukur trafik normal (baseline) dulu, set `rate`/`burst` di atasnya dengan margin, mulai dalam mode log-only/`limit_req ... ` yang longgar sambil memantau error.log, lalu perketat bertahap. Lengkapi dengan **graceful degradation** (halaman statis "sedang sibuk", antrean) alih-alih error keras saat batas tercapai.'
    source: https://blog.nginx.org/blog/rate-limiting-nginx

  - id: b15
    tier: B
    type: text
    objective: layers
    prompt: Mengapa availability disebut bagian dari keamanan (huruf A di CIA), bukan sekadar urusan performa?
    answers: ['layanan yang tak bisa diakses = tujuan penyerang tercapai; ddos adalah serangan terhadap availability']
    patterns: ['(tidak|tak) (bisa )?(diakses|tersedia|jalan)[^\n]*(serang|tujuan|sama saja|jebol|gagal)', 'cia[^\n]*(availability|ketersediaan)', 'ddos[^\n]*(availability|ketersediaan)', 'availability[^\n]*(keamanan|cia|tujuan penyerang)']
    tests:
      fail: ['availability hanya soal kecepatan', 'tidak ada hubungan dengan keamanan', 'hanya urusan tim infra', 'ddos bukan serangan']
    explanation: 'CIA = Confidentiality, Integrity, **Availability** (modul 01). Jika penyerang membuat layananmu tak bisa diakses, tujuannya tercapai sama seperti mencuri data — bisnis berhenti, SLA jebol, reputasi rusak, dan sering jadi pengalih perhatian untuk serangan lain. DDoS adalah serangan **langsung terhadap availability**. Karena itu kapasitas, rate limiting, caching, dan rencana mitigasi adalah kontrol keamanan, bukan sekadar tuning performa — dan masuk dalam threat model & runbook insiden (modul 10).'
    source: https://attack.mitre.org/techniques/T1498/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: ratelimit
    prompt: |
      Endpoint `/login` dan `/api` kamu dibanjiri HTTP flood + credential stuffing dari banyak IP, membuat backend
      kewalahan dan user sah tak bisa masuk. Rancang pertahanan L7 di reverse proxy (satu poin per baris).
    checklist:
      - id: limitreq
        label: limit_req per-IP (rate + burst + nodelay) pada endpoint mahal
        required: true
        patterns: ['limit_req', '(rate[- ]?limit|batasi laju)[^\n]*(request|ip)', 'burst[^\n]*nodelay|token bucket']
      - id: limitconn
        label: limit_conn per-IP untuk membatasi koneksi bersamaan
        required: true
        patterns: ['limit_conn', '(batasi|limit)[^\n]*koneksi[^\n]*(ip|bersamaan|simultan)']
      - id: timeouts
        label: Timeout klien (client_header/body_timeout) untuk klien lambat/slowloris
        patterns: ['client_(header|body)_timeout', 'timeout[^\n]*(klien|lambat|slowloris|request)', 'batas waktu[^\n]*request']
      - id: status
        label: Kembalikan 429/503 (+ Retry-After) atau 444 untuk pola jelas-jahat
        patterns: ['429|503|limit_req_status', 'return 444', 'retry-?after']
      - id: cache
        label: Cache endpoint baca agar flood baca tak memukul backend
        patterns: ['cache|proxy_cache|microcache', '(sajikan|layani)[^\n]*cache']
      - id: monitor
        label: Logging + alert (hitung "limiting requests", spike 4xx/5xx)
        patterns: ['(log|monitor|alert|pantau)[^\n]*(limiting|rate|4xx|5xx|serangan|spike)', 'logging|monitoring']
      - id: no-overblock
        label: Tuning dari baseline agar tidak memblokir semua user sah (hindari false positive)
        patterns: ['(tuning|setel|atur)[^\n]*(baseline|normal|trafik nyata)', 'false positive', '(jangan|hindari)[^\n]*(blokir|tolak)[^\n]*(semua )?(user|pengguna) sah']
      - id: no-justscale
        label: Tidak sekadar "besarkan server" tanpa limit sama sekali
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain|tambah juga))[^\n]*(cukup|hanya|tinggal)[^\n]*(besarkan|upgrade|tambah)[^\n]*(server|ram|cpu|spek)[^\n]*(saja|selesai|beres)']
    pass_score: 0.5
    solution: |
      ```text
      1. limit_req_zone $binary_remote_addr rate=Xr/s; limit_req zone=... burst=N nodelay; di /login & /api.
         (lab: flood 200 → 189 ditolak 503, hanya ~11 lolos.)
      2. limit_conn per-IP (mis. 10) untuk membatasi koneksi bersamaan satu klien.
      3. client_header_timeout/client_body_timeout 5s → potong klien lambat/slowloris (lab: 408).
      4. limit_req_status 429 + Retry-After (ramah retry); return 444 untuk pola jelas-jahat (lab: koneksi diputus).
      5. Cache/microcache endpoint baca (bukan /login) agar flood GET tak memukul backend (lab: HIT≠backend).
      6. Logging + alert: hitung "limiting requests" di error.log, pantau spike 4xx/5xx (ke modul 09).
      7. Tuning dari baseline trafik nyata + margin → hindari memblokir user sah; mulai longgar lalu perketat.
      ```
      Kunci: rate limit + conn limit + timeout menutup L7 flood & slowloris; cache menyerap flood baca; status &
      monitoring menjaga visibilitas. "Besarkan server saja" tanpa limit hanya menunda kejatuhan — penyerang
      tinggal menaikkan volume. Kapasitas itu pelengkap kontrol, bukan pengganti.
    tests:
      pass:
        - |
          pasang limit_req per ip dengan rate burst dan nodelay di endpoint login dan api
          limit_conn per ip untuk membatasi koneksi bersamaan
          client_header_timeout dan client_body_timeout untuk memotong klien lambat slowloris
          kembalikan 429 dengan retry-after atau 444 untuk pola jelas jahat
          cache endpoint baca agar flood baca tidak memukul backend
          logging dan alert hitung limiting requests dan pantau spike 5xx
          tuning dari baseline trafik nyata agar tidak memblokir user sah
          jangan sekadar besarkan server tanpa limit
        - |
          limit_req token bucket burst nodelay plus limit_conn per ip
          timeout header dan body untuk slowloris, return 444 pola jahat, 429 untuk rate
          microcache endpoint baca, monitoring error log, setel dari baseline hindari false positive
      fail:
        - |
          cukup besarkan server dan tambah ram saja
          tidak perlu rate limit
        - |
          blokir semua ip yang masuk biar aman
          user sah nanti juga bisa coba lagi
        - |
          matikan endpoint login sementara
          tidak usah pakai limit atau cache
    source: https://blog.nginx.org/blog/rate-limiting-nginx

  - id: c02
    tier: C
    type: scenario
    objective: caching
    prompt: |
      Situs berita (konten sebagian besar sama untuk semua pengunjung) terkena lonjakan trafik + flood baca sampai
      backend/DB tumbang. Rancang strategi caching & edge agar origin bertahan (satu poin per baris).
    checklist:
      - id: proxycache
        label: proxy_cache di reverse proxy untuk konten yang bisa di-cache
        required: true
        patterns: ['proxy[ _-]?cache', 'cache[^\n]*(reverse proxy|nginx|edge)', 'content cach']
      - id: cdn
        label: CDN/edge untuk melayani & menyebar trafik dekat pengunjung
        required: true
        patterns: ['cdn', 'edge', 'anycast', 'cloudflare|akamai|fastly']
      - id: microcache
        label: Microcache konten dinamis (mis. 1 detik) untuk endpoint populer
        patterns: ['micro[- ]?cache', 'cache[^\n]*(singkat|1 ?detik|pendek)', 'proxy_cache_valid[^\n]*(1s|[0-9]+s)']
      - id: stale
        label: Serve stale saat backend error (proxy_cache_use_stale) + cache_lock anti-stampede
        patterns: ['stale|proxy_cache_use_stale', 'cache[- ]?lock|proxy_cache_lock', 'anti[- ]?stampede|cache stampede']
      - id: cachekey
        label: Cache key benar (abaikan query/cookie tak relevan) agar HIT tinggi
        patterns: ['cache key|proxy_cache_key', '(abaikan|buang|normalisasi)[^\n]*(query|cookie|utm)', 'hit (tinggi|rate)']
      - id: monitor
        label: Pantau cache HIT ratio & kesehatan origin
        patterns: ['(pantau|monitor)[^\n]*(hit|cache|origin)', 'upstream_cache_status', 'hit ratio']
      - id: no-alldynamic
        label: Tidak melayani semuanya dinamis dari origin tanpa cache
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|hindari))[^\n]*(semua(nya)?|setiap request)[^\n]*(dinamis|tanpa cache|langsung)[^\n]*(origin|backend|db)']
    pass_score: 0.5
    solution: |
      ```text
      1. proxy_cache di nginx untuk halaman/aset yang sama bagi banyak orang (lab: MISS lalu HIT, backend tak disentuh).
      2. CDN + Anycast di depan → trafik dilayani & disebar di PoP global dekat pengunjung, origin terlindung.
      3. Microcache konten dinamis populer (proxy_cache_valid 200 1s) → backend maksimal 1x/detik per endpoint.
      4. proxy_cache_use_stale (error/timeout/updating) → sajikan versi lama saat backend sakit; proxy_cache_lock on
         → hanya 1 request mengisi cache, cegah stampede saat entri kedaluwarsa.
      5. Cache key yang benar (buang query/cookie tak relevan spt utm_*) → HIT ratio tinggi, bukan MISS karena URL unik.
      6. Monitor $upstream_cache_status / HIT ratio & kesehatan origin; HIT turun = cache meleset, segera investigasi.
      ```
      Inti: untuk konten yang sama bagi banyak orang, caching + CDN mengubah "N juta request ke DB" jadi "beberapa
      request ke origin". Melayani semuanya dinamis tanpa cache berarti tiap pengunjung = kerja penuh di origin —
      persis yang membuat backend tumbang. Cache dulu; biarkan origin hanya mengerjakan yang benar-benar personal.
    tests:
      pass:
        - |
          pasang proxy_cache di nginx untuk konten yang bisa di-cache
          taruh cdn dan edge anycast di depan untuk menyebar trafik dekat pengunjung
          microcache konten dinamis populer satu detik agar backend maksimal sekali per detik
          proxy_cache_use_stale sajikan versi lama saat backend error plus cache lock anti stampede
          cache key benar abaikan query dan cookie tak relevan agar hit tinggi
          pantau upstream_cache_status hit ratio dan kesehatan origin
          jangan layani semuanya dinamis dari origin tanpa cache
        - |
          proxy cache plus cdn anycast di depan
          microcache 1s, serve stale saat error, cache lock cegah stampede
          normalisasi cache key, monitor hit ratio, origin hanya untuk konten personal
      fail:
        - |
          layani semua halaman dinamis langsung dari origin tiap request
          tidak perlu cache
        - |
          besarkan database saja biar kuat
          cdn tidak penting
        - |
          matikan situs saat ramai
          nanti nyalakan lagi kalau sepi
    source: https://docs.nginx.com/nginx/admin-guide/content-cache/content-caching/

  - id: c03
    tier: C
    type: scenario
    objective: upstream
    prompt: |
      Layananmu terkena flood volumetrik L3/L4 (SYN/UDP) yang menjenuhkan uplink; `iptables` di server tidak menolong.
      Rancang pertahanan availability berlapis dan jelaskan batas tiap lapisan dengan jujur (satu poin per baris).
    checklist:
      - id: upstream
        label: Mitigasi upstream (CDN/anycast/scrubbing/proteksi DDoS provider) — wajib untuk volumetrik
        required: true
        patterns: ['upstream|scrubbing|cdn|anycast', 'cloudflare|akamai|provider|isp', 'proteksi ddos[^\n]*(cloud|provider|upstream)']
      - id: kernel
        label: "Kernel: tcp_syncookies + backlog (somaxconn/tcp_max_syn_backlog) untuk SYN flood"
        required: true
        patterns: ['syncookies|syn cookies', 'somaxconn|tcp_max_syn_backlog|backlog', 'tcp_syncookies']
      - id: l7
        label: Rate limit/conn limit + cache di origin sebagai pelengkap (bukan obat volumetrik)
        patterns: ['limit_req|limit_conn|rate limit', 'cache', '(pelengkap|lapisan|complement)[^\n]*(l7|origin)']
      - id: capacity
        label: Kapasitas/autoscale & overprovision bandwidth untuk menyerap sebagian
        patterns: ['autoscal(e|ing)|auto-?scale', 'kapasitas|overprovision|headroom', 'tambah (bandwidth|kapasitas|instance)']
      - id: runbook
        label: Monitoring, alert, dan runbook insiden (deteksi dini + langkah mitigasi)
        patterns: ['(monitor|alert|pantau)[^\n]*(trafik|uplink|serangan)', 'runbook|playbook|rencana (insiden|mitigasi)', 'deteksi dini']
      - id: degrade
        label: Graceful degradation (fitur non-inti dimatikan, halaman statis) agar inti tetap hidup
        patterns: ['graceful degradation|degradasi', '(matikan|nonaktifkan)[^\n]*(fitur|non-?inti)', 'halaman statis[^\n]*(fallback|sibuk)']
      - id: no-firewallonly
        label: Tidak mengandalkan iptables/firewall server saja melawan volumetrik
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain|tak cukup))[^\n]*(iptables|firewall)[^\n]*(server|lokal|host)[^\n]*(saja|cukup|sudah)']
    pass_score: 0.45
    solution: |
      ```text
      LAPISAN (dan batasnya):
        - Upstream (WAJIB utk volumetrik): CDN/anycast + scrubbing / proteksi DDoS provider menyerap & menyaring di
          jaringan besar SEBELUM mencapai uplink-mu. Tanpa ini, pipa penuh dan tak ada yang bisa dilakukan di server.
        - Kernel: tcp_syncookies=1 (lab: default 1) + naikkan somaxconn/tcp_max_syn_backlog → tahan SYN flood kecil-menengah.
          Batas: tidak menolong kalau bandwidth sudah jenuh.
        - Origin L7: limit_req/limit_conn + cache → pelengkap untuk komponen HTTP, BUKAN obat volumetrik L3/L4.
        - Kapasitas: autoscale + bandwidth headroom → serap lonjakan sah & serangan kecil; mahal untuk volumetrik murni.
        - Monitoring + alert + runbook insiden → deteksi dini, tahu kapan mengalihkan ke scrubbing, siapa dihubungi.
        - Graceful degradation → matikan fitur non-inti, sajikan halaman statis, jaga fungsi inti tetap hidup.
      ```
      Kejujuran teknis: `iptables` di server yang diserang tidak bisa mengosongkan uplink yang sudah penuh (paket
      sudah "membayar" bandwidth untuk tiba). Volumetrik dimenangkan di upstream; host hanya mengeraskan sisanya.
      Defense-in-depth availability = upstream (volumetrik) + kernel (SYN) + origin (L7) + kapasitas + proses.
    tests:
      pass:
        - |
          pakai mitigasi upstream cdn anycast dan scrubbing atau proteksi ddos provider karena volumetrik menang di upstream
          keraskan kernel dengan tcp_syncookies dan naikkan somaxconn serta tcp_max_syn_backlog untuk syn flood
          rate limit dan conn limit plus cache di origin sebagai pelengkap bukan obat volumetrik
          autoscale dan overprovision bandwidth untuk menyerap sebagian lonjakan
          monitoring alert dan runbook insiden untuk deteksi dini dan langkah mitigasi
          graceful degradation matikan fitur non inti sajikan halaman statis agar inti tetap hidup
          jangan andalkan iptables firewall server saja melawan volumetrik
        - |
          volumetrik butuh scrubbing dan cdn anycast di upstream, iptables server tidak mengosongkan uplink penuh
          kernel syncookies dan backlog untuk syn flood, limit_req dan cache origin sebagai pelengkap
          autoscale kapasitas, monitoring plus runbook, graceful degradation fitur non inti
      fail:
        - |
          cukup pasang iptables di server dan drop semua paket
          firewall lokal sudah pasti cukup
        - |
          tambah ram server saja
          tidak perlu upstream atau syncookies
        - |
          matikan server sampai serangan berhenti
          tidak usah mitigasi apa-apa
    source: https://www.cloudflare.com/learning/ddos/what-is-a-ddos-attack/
---

# Ketersediaan & Anti-DDoS

Tiga modul sebelumnya menutup *kerentanan*. Modul ini menjaga **availability** — huruf A dalam CIA (modul 01):
layanan yang tak bisa diakses sama saja dengan jebol. DDoS menyerang availability secara langsung (ATT&CK T1498
Network DoS, T1499 Endpoint DoS). Pertahanannya berlapis karena tak ada satu kontrol pun yang cukup sendirian.

> [!NOTE]
> Lab: nginx edge + backend + client (`ab`) di jaringan `dj-sec08` (Docker Desktop, kernel 6.18 WSL2). Beban diuji
> dengan ApacheBench **hanya pada target milik sendiri**. "Volumetrik upstream" tidak bisa disimulasikan di satu
> host — bagian itu dijelaskan jujur, bukan di-lab.

## 1. Dua lapisan serangan

| | L3/L4 — volumetrik | L7 — aplikasi |
|---|---|---|
| Contoh | SYN flood, UDP/amplification | HTTP flood, slowloris, query mahal |
| Menghabiskan | bandwidth/uplink, tabel koneksi | CPU/worker/koneksi aplikasi |
| Dilawan di | **upstream** (CDN/anycast/scrubbing) + kernel | reverse proxy/app (rate limit, cache, timeout) |
| Catatan | pipa bisa penuh sebelum sampai ke server | volume kecil pun bisa mematikan |

## 2. Rate limiting L7 — `limit_req` (hasil lab)

```text
limit_req_zone $binary_remote_addr zone=reqs:10m rate=5r/s;
limit_req zone=reqs burst=10 nodelay;  limit_req_status 503;

ab -n 200 -c 20  TANPA proteksi (:8080/)  → 200/200 lolos (backend kena semua)
ab -n 200 -c 20  DENGAN limit_req (:8081/api) → 189 ditolak 503, ~11 lolos (burst+token)
error.log: "limiting requests, excess: 10.990 by zone reqs, client: ..."  ×189
```

- **leaky/token bucket**: laju ke backend dijaga `rate`; `burst` = antrean lonjakan; `nodelay` = layani burst segera.
- Key `$binary_remote_addr` = batas **per IP**. Di belakang CDN, pakai IP klien asli (`real_ip`/XFF tervalidasi).

## 3. Connection limit & slowloris (hasil lab)

```text
limit_conn_zone $binary_remote_addr zone=conns:10m;  limit_conn conns 10;
client_header_timeout 5s;  client_body_timeout 5s;

30 koneksi lambat bersamaan (1 IP) → 20 ditolak 503 ("limiting connections by zone conns"), 10 dilayani
request header tak selesai (slowloris) → nginx tutup dengan 408 (timeout)
return 444 (/badbot) → koneksi diputus tanpa response (curl: http_code=000, empty reply)
```

Slowloris (L7) menahan worker dengan koneksi menetes; **timeout + `limit_conn`** memotongnya. `return 444` membuang
pola jelas-jahat semurah mungkin.

## 4. Caching menyerap flood baca (hasil lab)

```text
proxy_cache_path ...; proxy_cache cache; proxy_cache_valid 200 60s;
add_header X-Cache-Status $upstream_cache_status;

/static → req1: MISS   req2: HIT   req3: HIT   (backend hanya disentuh sekali)
```

- **Microcache** (mis. `proxy_cache_valid 200 1s`) menyerap flood bahkan untuk konten dinamis populer.
- `proxy_cache_use_stale` + `proxy_cache_lock on` → sajikan versi lama saat backend sakit & cegah **cache stampede**.

## 5. Kernel: SYN flood (hasil lab)

```text
net.ipv4.tcp_syncookies = 1   (default container Docker Desktop; sysctl / cat /proc/sys/...)
net.core.somaxconn       = 4096
docker run --sysctl net.ipv4.tcp_syncookies=1 ...   # set di namespace net container
```

SYN cookies meng-encode state ke SYN-ACK alih-alih menyimpan half-open → antrean tak bisa dipenuhi. Naikkan
`somaxconn`/`tcp_max_syn_backlog` untuk menampung lonjakan koneksi sah. **Catatan:** `/proc/sys` read-only di dalam
container — setel di host atau via `--sysctl`.

## 6. Yang tidak bisa dilakukan sendiri di server

> [!WARNING]
> `iptables`/rate limit di server **tidak** menghentikan flood volumetrik yang menjenuhkan uplink: paket sudah
> memenuhi pipa sebelum sampai ke kotakmu. Volumetrik dimenangkan **upstream** — CDN/anycast + scrubbing + proteksi
> DDoS provider. Kontrol host melawan L7 dan flood kecil; bukan obat volumetrik. Jujuri batas ini dalam desain.

## 7. Ringkasan: pertahanan availability berlapis

| Lapisan | Kontrol | Lawan | Batas |
|---|---|---|---|
| Upstream | CDN/anycast, scrubbing, proteksi provider | volumetrik L3/L4 besar | biaya/ketergantungan vendor |
| Kernel | `tcp_syncookies`, backlog | SYN flood | tak menolong jika uplink jenuh |
| Reverse proxy | `limit_req`, `limit_conn`, timeout, `return 444` | HTTP flood, slowloris | per-IP bisa dilewati botnet/NAT |
| Cache/CDN | `proxy_cache`, microcache, serve-stale | flood baca | tak untuk data per-user |
| Kapasitas | autoscale, bandwidth headroom | lonjakan sah & kecil | mahal untuk volumetrik murni |
| Proses | monitoring, alert, runbook, degradasi | semua | butuh latihan sebelum insiden |

> [!NOTE]
> Lanjut ke modul 09 (Deteksi & logging) — setelah mencegah dan menahan, melihat serangan saat terjadi: log
> terpusat, metrik, dan alerting (termasuk sinyal `limiting requests`/spike 5xx dari modul ini).
