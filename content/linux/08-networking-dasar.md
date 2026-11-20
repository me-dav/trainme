---
format: 1
id: linux-networking-basics
course:
  slug: linux
  title: Linux
  description: Fondasi Linux untuk DevOps/DevSecOps — dari filesystem sampai hardening.
title: Networking Dasar (ip, ss, DNS, curl, firewall)
summary: Membaca IP dan routing, melihat port yang listen, men-debug DNS, menguji HTTP/TLS dengan curl dan openssl, firewall ufw/nftables, dan troubleshooting koneksi lapis demi lapis.
order: 8
version: 1
status: verified
updated: '2026-11-20'
tested_on:
  - Ubuntu 24.04.5 LTS (container Docker --privileged) — iproute2 6.1, BIND dig 9.18, curl 8.5.0, OpenSSL 3.0.13, nftables 1.0.9, ufw 0.36.2, tcpdump 4.99, PostgreSQL 16
  - Docker Desktop (jaringan bridge default 172.17.0.0/16)
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: ip
    text: Membaca alamat IP, interface, dan routing dengan ip
  - id: ports
    text: Melihat port yang listen dan koneksi aktif dengan ss
  - id: dns
    text: Men-debug DNS dengan dig dan getent, termasuk /etc/hosts dan TTL
  - id: http
    text: Menguji HTTP dengan curl (status, header, timing, --resolve)
  - id: tls
    text: Memeriksa sertifikat TLS tanpa mematikan verifikasi
  - id: connectivity
    text: Membedakan connection refused vs timeout dan menguji port
  - id: firewall
    text: Mengatur firewall ufw/nftables tanpa mengunci diri sendiri
  - id: troubleshoot
    text: Troubleshooting koneksi secara berlapis (DNS → TCP → TLS → HTTP)
sources:
  - title: ip(8), ip-address(8), ip-route(8)
    url: https://man7.org/linux/man-pages/man8/ip.8.html
  - title: ss(8)
    url: https://man7.org/linux/man-pages/man8/ss.8.html
  - title: dig — BIND 9 Administrator Reference Manual
    url: https://bind9.readthedocs.io/en/stable/manpages.html#dig-dns-lookup-utility
  - title: getent(1) & nsswitch.conf(5)
    url: https://man7.org/linux/man-pages/man5/nsswitch.conf.5.html
  - title: resolv.conf(5)
    url: https://man7.org/linux/man-pages/man5/resolv.conf.5.html
  - title: curl man page (--resolve, -w, --cacert)
    url: https://curl.se/docs/manpage.html
  - title: Everything curl — timing variables
    url: https://everything.curl.dev/usingcurl/verbose/writeout.html
  - title: openssl-s_client(1)
    url: https://docs.openssl.org/3.0/man1/openssl-s_client/
  - title: tcpdump(1)
    url: https://www.tcpdump.org/manpages/tcpdump.1.html
  - title: Ubuntu Server — Firewall (ufw)
    url: https://documentation.ubuntu.com/server/how-to/security/firewalls/
  - title: nftables wiki — Quick reference
    url: https://wiki.nftables.org/wiki-nftables/index.php/Quick_reference-nftables_in_10_minutes
  - title: Docker Docs — Packet filtering and firewalls (Docker and ufw)
    url: https://docs.docker.com/engine/network/packet-filtering-firewalls/
  - title: PostgreSQL 16 — listen_addresses
    url: https://www.postgresql.org/docs/16/runtime-config-connection.html
  - title: IANA — Service Name and Port Number Registry
    url: https://www.iana.org/assignments/service-names-port-numbers/service-names-port-numbers.xhtml
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: command
    objective: ip
    prompt: Dengan `ip` (iproute2), tampilkan alamat IP semua interface.
    answers: ['ip a']
    patterns: ['^ip (-br |-4 |-6 |-c )*(a|addr|address)( show)?$']
    tests:
      pass: ['ip addr', 'ip -br a', 'ip address show']
      fail: ['ip r', 'ip link', 'ss -a']
    explanation: '`ip -br a` memberi ringkasan satu baris per interface (lab: `eth0@if78 UP 172.17.0.2/16`). `ifconfig` (net-tools) sudah lama dianggap usang dan tidak terpasang di image minimal.'
    source: https://man7.org/linux/man-pages/man8/ip-address.8.html

  - id: a02
    tier: A
    type: command
    objective: ip
    prompt: Tampilkan tabel routing, termasuk **default gateway**.
    answers: ['ip r']
    patterns: ['^ip (-4 |-6 )?(r|ro|route)( show)?$']
    tests:
      pass: ['ip route', 'ip route show']
      fail: ['ip a', 'ip link', 'ip neigh']
    explanation: 'Di lab: `default via 172.17.0.1 dev eth0` dan `172.17.0.0/16 dev eth0 ... src 172.17.0.2`. Tanpa baris `default`, host hanya bisa menjangkau subnet lokalnya.'
    source: https://man7.org/linux/man-pages/man8/ip-route.8.html

  - id: a03
    tier: A
    type: command
    objective: ports
    prompt: Tampilkan semua port **TCP** yang sedang **listen**, dalam bentuk angka (tanpa resolve nama), beserta **proses**-nya.
    answers: ['ss -tlnp']
    flags_any_order: true
    patterns: ['^ss -(?=[tlnp]*t)(?=[tlnp]*l)(?=[tlnp]*n)(?=[tlnp]*p)[tlnp]{4}$']
    tests:
      pass: ['ss -ltnp', 'ss -t -l -n -p', 'ss -plnt']
      fail: ['ss -tan', 'ss -l', 'ss -tln']
    explanation: '`-t` TCP, `-l` listening, `-n` numerik, `-p` proses (perlu root untuk proses milik user lain). Kolom penting: **Local Address**. Di lab: `127.0.0.1:8000` vs `0.0.0.0:8001`. Tambah `-u` untuk UDP.'
    source: https://man7.org/linux/man-pages/man8/ss.8.html

  - id: a04
    tier: A
    type: command
    objective: dns
    prompt: Dengan `dig`, tampilkan **hanya** alamat IP (record A) untuk `example.com`.
    answers: ['dig +short example.com']
    patterns: ['^dig (\+short (A )?example\.com( A)?|example\.com( A)? \+short)$']
    tests:
      pass: ['dig +short example.com A', 'dig example.com +short']
      fail: ['dig example.com MX', 'nslookup', 'ping example.com']
    explanation: 'Tanpa `+short`, dig menampilkan header, section QUESTION/ANSWER, dan server yang menjawab. Satu nama bisa punya banyak A record (lab: dua IP).'
    source: https://bind9.readthedocs.io/en/stable/manpages.html#dig-dns-lookup-utility

  - id: a05
    tier: A
    type: command
    objective: dns
    prompt: Tanya DNS server `1.1.1.1` **langsung** untuk record A `example.com` (bukan resolver default sistem).
    answers: ['dig @1.1.1.1 example.com']
    patterns: ['^dig (\+short )?@1\.1\.1\.1 (\+short )?example\.com( A)?( \+short)?$', '^dig (\+short )?example\.com( A)? @1\.1\.1\.1( \+short)?$']
    tests:
      pass: ['dig +short @1.1.1.1 example.com', 'dig example.com @1.1.1.1']
      fail: ['dig example.com', 'dig 1.1.1.1 example.com', 'nslookup example.com']
    explanation: 'Membandingkan jawaban resolver lokal dengan resolver publik atau nameserver otoritatif adalah cara tercepat membuktikan masalah cache/propagasi. Tanpa `@`, `1.1.1.1` dianggap nama yang ingin dicari.'
    source: https://bind9.readthedocs.io/en/stable/manpages.html#dig-dns-lookup-utility

  - id: a06
    tier: A
    type: command
    objective: dns
    prompt: Tampilkan record **MX** (mail server) untuk `gmail.com`, ringkas.
    answers: ['dig +short MX gmail.com']
    patterns: ['^dig (\+short )?(MX gmail\.com|gmail\.com MX|-t ?MX gmail\.com)( \+short)?$']
    tests:
      pass: ['dig gmail.com MX +short', 'dig -t MX gmail.com']
      fail: ['dig gmail.com', 'dig +short gmail.com', 'dig -x gmail.com']
    explanation: 'Angka di depan adalah prioritas (lebih kecil = diutamakan). Di lab: `10 alt1.gmail-smtp-in.l.google.com.`. Tipe lain: `AAAA`, `TXT` (SPF/DKIM/verifikasi), `CNAME`, `NS`, dan `-x IP` untuk reverse lookup.'
    source: https://bind9.readthedocs.io/en/stable/manpages.html#dig-dns-lookup-utility

  - id: a07
    tier: A
    type: command
    objective: http
    prompt: Dengan curl, ambil **hanya header** respons dari `https://example.com`.
    answers: ['curl -I https://example.com']
    patterns: ['^curl (-[sL]*I[sL]*|--head|-s --head|--head -s|-s -I|-I -s|-L -I|-I -L) https://example\.com/?$']
    tests:
      pass: ['curl -sI https://example.com', 'curl --head https://example.com/']
      fail: ['curl https://example.com', 'curl -i https://example.com', 'wget https://example.com']
    explanation: '`-I` mengirim request HEAD. `-i` kecil menampilkan header **plus** body. Di lab: `HTTP/2 200`. Sebagian aplikasi memperlakukan HEAD berbeda dari GET; bila ragu, pakai `curl -s -o /dev/null -D - URL`.'
    source: https://curl.se/docs/manpage.html

  - id: a08
    tier: A
    type: command
    objective: connectivity
    prompt: Cek apakah port TCP **5432** di `db.internal` bisa dijangkau dengan `nc`, tanpa mengirim data, dengan output verbose.
    answers: ['nc -zv db.internal 5432']
    patterns: ['^nc (-w ?\d+ )?-(zv|vz)( -w ?\d+)? db\.internal 5432$', '^nc (-z -v|-v -z)( -w ?\d+)? db\.internal 5432$']
    tests:
      pass: ['nc -vz db.internal 5432', 'nc -zv -w 3 db.internal 5432']
      fail: ['ping db.internal', 'nc db.internal', 'nc -l 5432']
    explanation: '`-z` hanya mencoba koneksi, `-v` mencetak hasilnya, `-w 3` memberi batas waktu. Di lab: `succeeded!`, `Connection refused`, atau `timed out` — tiga hasil dengan tiga arti berbeda (bagian 5). `ping` hanya menguji ICMP, bukan port.'
    source: https://manpages.ubuntu.com/manpages/noble/man1/nc_openbsd.1.html

  - id: a09
    tier: A
    type: command
    objective: connectivity
    prompt: Kirim **tepat 4** ping ke `10.0.0.1` lalu berhenti.
    answers: ['ping -c 4 10.0.0.1']
    patterns: ['^ping -c ?4 10\.0\.0\.1$', '^ping 10\.0\.0\.1 -c ?4$']
    tests:
      pass: ['ping -c4 10.0.0.1']
      fail: ['ping 10.0.0.1', 'ping -n 4 10.0.0.1', 'ping -t 4 10.0.0.1']
    explanation: 'Di Linux, ping berjalan terus tanpa `-c`. `-n 4` adalah syntax **Windows** (di Linux, `-n` berarti numerik). Ping yang gagal tidak selalu berarti host mati, karena ICMP sering diblokir firewall.'
    source: https://manpages.ubuntu.com/manpages/noble/man8/ping.8.html

  - id: a10
    tier: A
    type: text
    objective: dns
    prompt: File apa yang menentukan **DNS server** (nameserver) yang dipakai resolver sistem?
    answers: ['/etc/resolv.conf']
    tests:
      fail: ['/etc/hosts', '/etc/nsswitch.conf', '/etc/hostname', '/etc/dns.conf']
    explanation: 'Di Ubuntu dengan systemd-resolved isinya `nameserver 127.0.0.53` (stub lokal); cek upstream sebenarnya dengan `resolvectl status`. Di container Docker lab: `nameserver 192.168.65.7` (DNS Docker Desktop). Jangan edit langsung bila file ini dikelola tool lain, karena perubahanmu akan ditimpa.'
    source: https://man7.org/linux/man-pages/man5/resolv.conf.5.html

  - id: a11
    tier: A
    type: text
    objective: dns
    prompt: File apa yang berisi pemetaan nama → IP statis dan (dengan konfigurasi default) dicek **sebelum** DNS?
    answers: ['/etc/hosts']
    tests:
      fail: ['/etc/resolv.conf', '/etc/hostname', '/etc/nsswitch.conf', '/etc/networks']
    explanation: 'Urutannya diatur `/etc/nsswitch.conf` (lab: `hosts: files dns`, jadi `files` = `/etc/hosts` lebih dulu). Entri yang terlupakan di `/etc/hosts` adalah penyebab klasik "di server ini saja salah IP".'
    source: https://man7.org/linux/man-pages/man5/nsswitch.conf.5.html

  - id: a12
    tier: A
    type: text
    objective: ports
    prompt: Port TCP default **PostgreSQL**?
    answers: ['5432']
    tests:
      fail: ['3306', '6379', '5433', '27017']
    explanation: 'Port umum: 22 SSH, 80/443 HTTP/HTTPS, 3306 MySQL, 5432 PostgreSQL, 6379 Redis, 27017 MongoDB, 9200 Elasticsearch. Cek di mesin: `getent services 5432` (lab: `postgresql 5432/tcp postgres`).'
    source: https://www.iana.org/assignments/service-names-port-numbers/service-names-port-numbers.xhtml

  - id: a13
    tier: A
    type: command
    objective: firewall
    prompt: Izinkan SSH (TCP port 22) masuk lewat ufw.
    answers: ['ufw allow 22/tcp']
    patterns: ['^ufw (allow|limit) (22/tcp|ssh|OpenSSH)$']
    tests:
      pass: ['ufw allow ssh', 'ufw allow OpenSSH', 'ufw limit 22/tcp']
      fail: ['ufw enable', 'ufw deny 22/tcp', 'ufw allow 22/udp']
    explanation: 'Selalu izinkan SSH **sebelum** `ufw enable`, supaya sesi remote tidak terputus. `ufw limit 22/tcp` (lab: `LIMIT`) menolak IP yang membuka 6+ koneksi dalam 30 detik — mitigasi brute force sederhana.'
    source: https://documentation.ubuntu.com/server/how-to/security/firewalls/

  - id: a14
    tier: A
    type: command
    objective: firewall
    prompt: Tampilkan status ufw **beserta default policy** (incoming/outgoing) dan logging.
    answers: ['ufw status verbose']
    patterns: ['^ufw status verbose$']
    tests:
      fail: ['ufw status', 'ufw show', 'iptables -L', 'ufw status numbered']
    explanation: 'Di lab: `Default: deny (incoming), allow (outgoing), deny (routed)`. `ufw status` biasa tidak menampilkan default policy, padahal itulah yang menentukan semua yang tidak punya rule.'
    source: https://documentation.ubuntu.com/server/how-to/security/firewalls/

  - id: a15
    tier: A
    type: command
    objective: http
    prompt: Dengan curl, tampilkan **hanya kode status HTTP** dari `https://example.com` (body dibuang, tanpa progress bar).
    answers: ['curl -s -o /dev/null -w ''%{http_code}'' https://example.com']
    patterns: ['^curl (?=.*(-o ?/dev/null|--output /dev/null))(?=.*(-w|--write-out) ?%\{http_code\})((-s|-S|-sS|-L|-sL|-o ?/dev/null|--output /dev/null|(-w|--write-out) ?%\{http_code\}(\\n)?) )+https://example\.com/?$']
    tests:
      pass: ['curl -sS -o /dev/null -w "%{http_code}\n" https://example.com', 'curl -w ''%{http_code}'' -s -o /dev/null https://example.com/']
      fail: ['curl -I https://example.com', 'curl https://example.com', 'curl -s -w ''%{http_code}'' https://example.com']
    explanation: 'Pola standar untuk healthcheck dan smoke test di CI: `[ "$(curl -s -o /dev/null -w ''%{http_code}'' URL)" = 200 ]`. Tanpa `-o /dev/null`, body halaman ikut tercetak sebelum kode status.'
    source: https://everything.curl.dev/usingcurl/verbose/writeout.html

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: command
    objective: ip
    prompt: Tanyakan kernel **interface, gateway, dan IP sumber** mana yang akan dipakai untuk menjangkau `1.1.1.1`.
    answers: ['ip route get 1.1.1.1']
    patterns: ['^ip (r|ro|route) get 1\.1\.1\.1$']
    tests:
      pass: ['ip r get 1.1.1.1']
      fail: ['ip r', 'traceroute 1.1.1.1', 'ping 1.1.1.1']
    explanation: 'Di lab: `1.1.1.1 via 172.17.0.1 dev eth0 src 172.17.0.2`. Sangat berguna di server dengan banyak interface/VPN: kamu langsung melihat rute yang benar-benar dipilih, tanpa menebak dari tabel routing.'
    source: https://man7.org/linux/man-pages/man8/ip-route.8.html

  - id: b02
    tier: B
    type: text
    objective: ports
    prompt: '`ss -tlnp` menunjukkan `LISTEN ... 127.0.0.1:8000`. Bisakah host **lain** di jaringan mengakses port 8000 itu? (ya/tidak)'
    answers: ['tidak', 'no', 'tidak bisa']
    tests:
      fail: ['ya', 'yes', 'bisa']
    explanation: 'Di lab, `curl 127.0.0.1:8000` → 200, tapi `curl 172.17.0.2:8000` (IP eth0 host yang sama) → `Couldn''t connect to server`. `0.0.0.0:8001` bisa dijangkau dari IP mana pun. Default PostgreSQL 16 juga `localhost` (lab: `127.0.0.1:5432`). Ini sering jadi jawaban "kenapa app server tidak bisa konek ke DB".'
    source: https://man7.org/linux/man-pages/man8/ss.8.html

  - id: b03
    tier: B
    type: text
    objective: connectivity
    prompt: '`nc -zv host 9001` langsung gagal dengan `Connection refused`. Penyebab yang paling mungkin: port **tertutup** (tidak ada yang listen) atau paket **di-drop** firewall?'
    answers: ['tertutup', 'port tertutup', 'tidak ada yang listen']
    patterns: ['^(port )?(tertutup|closed)$', '^tidak ada (yang listen|listener|proses yang listen)$', '^no listener$']
    tests:
      pass: ['closed', 'tidak ada listener']
      fail: ['firewall', 'di-drop', 'drop', 'filtered']
    explanation: 'Refused = host membalas TCP RST, jadi paketnya sampai tetapi tidak ada listener (atau firewall memakai `reject`). Timeout = tidak ada balasan sama sekali, biasanya DROP di firewall atau security group. Di lab: port tanpa listener → refused dalam 0 ms; port dengan rule nftables `drop` → `timed out` setelah 3 detik.'
    source: https://wiki.nftables.org/wiki-nftables/index.php/Quick_reference-nftables_in_10_minutes

  - id: b04
    tier: B
    type: command
    objective: ports
    prompt: Tampilkan koneksi **TCP** yang berstatus **established**, alamat dalam bentuk angka, memakai filter state milik `ss`.
    answers: ['ss -tn state established']
    patterns: ['^ss -(tn|nt|t -n|n -t)( -p| -i)? state established$', '^ss state established -(tn|nt)$', '^ss -(tnp|tpn|ntp|npt|ptn|pnt) state established$']
    tests:
      pass: ['ss -nt state established', 'ss -tnp state established']
      fail: ['ss -tlnp', 'ss -tan', 'netstat -an']
    explanation: 'Filter lain: `state time-wait`, `''( dport = :5432 )''`, `''sport = :443''`. `ss -s` memberi ringkasan (lab: `TCP: 46 (estab 0, closed 42, orphaned 0, timewait 17)`). Ribuan TIME-WAIT/CLOSE-WAIT adalah petunjuk masalah connection pooling.'
    source: https://man7.org/linux/man-pages/man8/ss.8.html

  - id: b05
    tier: B
    type: command
    objective: dns
    prompt: '`/etc/hosts` berisi `10.9.9.9 api.internal`, tetapi `dig +short api.internal` kosong. Tampilkan hasil resolusi **seperti yang dilihat aplikasi** (lewat nsswitch, termasuk `/etc/hosts`).'
    answers: ['getent hosts api.internal']
    patterns: ['^getent (hosts|ahosts|ahostsv4) api\.internal$']
    tests:
      pass: ['getent ahosts api.internal']
      fail: ['dig api.internal', 'nslookup api.internal', 'host api.internal', 'cat /etc/resolv.conf']
    explanation: '`dig`, `host`, dan `nslookup` bicara langsung ke DNS server, sehingga **tidak membaca** `/etc/hosts`. Di lab: `getent` → `10.9.9.9`, `dig` → kosong, dan `curl http://api.internal` mencoba 10.9.9.9 (karena curl memakai resolver sistem). Kalau dig dan aplikasi tidak sepakat, cek `getent`.'
    source: https://man7.org/linux/man-pages/man5/nsswitch.conf.5.html

  - id: b06
    tier: B
    type: command
    objective: http
    prompt: |
      Situs `shop.example.com` akan dipindah ke server baru `203.0.113.10`. Uji HTTPS ke server baru **sebelum** DNS diubah,
      tanpa mengedit `/etc/hosts`, dengan SNI dan header Host yang benar.
    answers: ['curl --resolve shop.example.com:443:203.0.113.10 https://shop.example.com/']
    patterns: ['^curl ((-s|-S|-sS|-I|-sI|-v|-o ?/dev/null|-w ?\S+) )*--resolve[= ]?shop\.example\.com:443:203\.0\.113\.10 ((-s|-S|-sS|-I|-sI|-v) )*https://shop\.example\.com(/\S*)?$']
    tests:
      pass: ['curl -sI --resolve shop.example.com:443:203.0.113.10 https://shop.example.com/', 'curl --resolve=shop.example.com:443:203.0.113.10 https://shop.example.com']
      fail: ['curl https://203.0.113.10/', 'curl -H ''Host: shop.example.com'' https://203.0.113.10/', 'curl -k https://203.0.113.10/', 'curl --resolve shop.example.com:80:203.0.113.10 https://shop.example.com/']
    explanation: '`--resolve host:port:ip` hanya mengganti hasil DNS untuk request itu, sehingga SNI, sertifikat, dan header Host tetap `shop.example.com`. Di lab: `--resolve myapp.test:8001:127.0.0.1` → `200 127.0.0.1`. `-H Host:` saja tidak cukup untuk HTTPS, karena sertifikat dicek terhadap IP. Port di `--resolve` harus cocok (443 untuk https).'
    source: https://curl.se/docs/manpage.html#--resolve

  - id: b07
    tier: B
    type: text
    objective: http
    prompt: 'Variabel `-w` curl apa yang mengukur waktu (dari awal) sampai **handshake TLS** selesai?'
    answers: ['%{time_appconnect}', 'time_appconnect']
    patterns: ['^%?\{?time_appconnect\}?$']
    tests:
      fail: ['time_connect', 'time_total', 'time_starttransfer', 'time_namelookup']
    explanation: 'Di lab: `dns=0.002 connect=0.076 tls=0.244 ttfb=0.345 total=0.345`. Selisih antar-angka menunjukkan bagian yang lambat: DNS (`time_namelookup`), TCP (`time_connect`), TLS (`time_appconnect`), lalu aplikasi (`time_starttransfer` = TTFB). TTFB tinggi dengan TLS cepat = backend yang lambat.'
    source: https://everything.curl.dev/usingcurl/verbose/writeout.html

  - id: b08
    tier: B
    type: command
    objective: tls
    prompt: Tampilkan tanggal **kedaluwarsa** sertifikat TLS `example.com:443` dengan `openssl` (s_client lalu x509), dengan SNI yang benar.
    answers: ['echo | openssl s_client -connect example.com:443 -servername example.com 2>/dev/null | openssl x509 -noout -enddate']
    patterns: ['^(echo ?\| ?)?openssl s_client(?=.*-connect example\.com:443)( -connect example\.com:443| -servername example\.com){1,2}( </dev/null)?( 2>/dev/null)? ?\| ?openssl x509 -noout (-enddate|-dates)( -subject| -issuer)*$']
    tests:
      pass: ['openssl s_client -connect example.com:443 -servername example.com </dev/null 2>/dev/null | openssl x509 -noout -dates']
      fail: ['curl -I https://example.com', 'openssl s_client -connect example.com:443', 'openssl x509 -noout -enddate example.com']
    explanation: '`echo |` atau `</dev/null` menutup koneksi setelah handshake. `-servername` mengirim SNI. Tanpa SNI, server dengan banyak domain bisa memberi sertifikat default yang salah. Di lab: `notAfter=Dec 25 22:56:35 2026 GMT`. Alternatif cepat: `curl -vsI URL 2>&1 | grep ''expire date''`.'
    source: https://docs.openssl.org/3.0/man1/openssl-s_client/

  - id: b09
    tier: B
    type: text
    objective: tls
    prompt: 'curl gagal: `SSL certificate problem: self-signed certificate` ke service internal yang memakai CA perusahaan. Opsi curl apa yang **benar** untuk mempercayai CA itu (bukan mematikan verifikasi)?'
    answers: ['--cacert']
    patterns: ['^(curl )?--cacert( \S+)?$']
    tests:
      pass: ['--cacert ca.pem', 'curl --cacert']
      fail: ['-k', '--insecure', '--no-verify', '--cert']
    explanation: 'Di lab: tanpa opsi → error 60, `--cacert c.pem` → 200, `-k` → 200 tanpa verifikasi apa pun. `-k` membuat koneksi rentan MITM. Untuk permanen, pasang CA ke trust store sistem: `cp ca.crt /usr/local/share/ca-certificates/ && update-ca-certificates`. `--cert` adalah sertifikat **klien** (mTLS).'
    source: https://curl.se/docs/manpage.html#--cacert

  - id: b10
    tier: B
    type: command
    objective: troubleshoot
    prompt: Tangkap **20 paket** TCP port 443 di **semua interface**, tanpa resolve nama host maupun port.
    answers: ['tcpdump -i any -nn -c 20 tcp port 443']
    patterns: ['^tcpdump (?=.*-i ?any( |$))(?=.*-c ?20( |$))(?=.*-nn?( |$))((-i ?any|-nn?|-c ?20) )+(tcp port 443|port 443|tcp and port 443)$']
    tests:
      pass: ['tcpdump -nn -i any -c 20 port 443', 'tcpdump -c 20 -nn -i any ''tcp port 443''']
      fail: ['tcpdump port 443', 'tcpdump -i any -c 20 udp port 443', 'ss -tn']
    explanation: 'Di lab, satu koneksi curl memperlihatkan three-way handshake: `Flags [S]`, `[S.]`, `[.]`. Kalau hanya `[S]` berulang tanpa balasan, paketmu di-drop. `[R]` berarti ditolak. `-w file.pcap` menyimpan hasil tangkapan untuk Wireshark. Hati-hati: payload yang tidak terenkripsi (HTTP, token) ikut tertangkap.'
    source: https://www.tcpdump.org/manpages/tcpdump.1.html

  - id: b11
    tier: B
    type: command
    objective: firewall
    prompt: Set default policy ufw agar **semua koneksi masuk ditolak** (kecuali yang diizinkan rule).
    answers: ['ufw default deny incoming']
    patterns: ['^ufw default deny( incoming)?$']
    tests:
      pass: ['ufw default deny']
      fail: ['ufw deny all', 'ufw default reject outgoing', 'ufw enable', 'ufw default allow incoming']
    explanation: 'Model allowlist: tolak semua, lalu buka yang perlu. Di lab, ufw membalas `Default incoming policy changed to ''deny'' (be sure to update your rules accordingly)`. Urutan aman di server remote: `default deny incoming` → `allow 22/tcp` → `enable`.'
    source: https://documentation.ubuntu.com/server/how-to/security/firewalls/

  - id: b12
    tier: B
    type: command
    objective: firewall
    prompt: Izinkan PostgreSQL (TCP 5432) **hanya** dari subnet aplikasi `10.0.0.0/8`.
    answers: ['ufw allow from 10.0.0.0/8 to any port 5432 proto tcp']
    patterns: ['^ufw allow from 10\.0\.0\.0/8 to any port 5432( proto tcp)?$', '^ufw allow proto tcp from 10\.0\.0\.0/8 to any port 5432$']
    tests:
      pass: ['ufw allow from 10.0.0.0/8 to any port 5432', 'ufw allow proto tcp from 10.0.0.0/8 to any port 5432']
      fail: ['ufw allow 5432', 'ufw allow 5432/tcp', 'ufw allow from 10.0.0.0/8']
    explanation: 'Di lab: `5432/tcp ALLOW IN 10.0.0.0/8`. `ufw allow 5432` membuka database ke internet. Banyak insiden kebocoran data berawal dari database yang listen di `0.0.0.0` tanpa batas sumber. `from 10.0.0.0/8` tanpa port membuka **semua** port untuk subnet itu.'
    source: https://documentation.ubuntu.com/server/how-to/security/firewalls/

  - id: b13
    tier: B
    type: command
    objective: firewall
    prompt: Hapus rule ufw **nomor 2** sesuai daftar di `ufw status numbered`.
    answers: ['ufw delete 2']
    patterns: ['^ufw (--force )?delete 2$']
    tests:
      pass: ['ufw --force delete 2']
      fail: ['ufw remove 2', 'ufw deny 2', 'ufw delete allow 2']
    explanation: 'Di lab, setelah konfirmasi `Proceed with operation (y|n)?`, rule 5432 hilang dan rule IPv6 naik ke nomor 2. Nomor **bergeser** setelah setiap penghapusan, jadi selalu jalankan `status numbered` lagi sebelum menghapus rule berikutnya. Alternatif: `ufw delete allow 22/tcp` (sebut rule-nya).'
    source: https://documentation.ubuntu.com/server/how-to/security/firewalls/

  - id: b14
    tier: B
    type: text
    objective: firewall
    prompt: 'Host memakai ufw `default deny incoming` tanpa rule untuk 8080. Kamu menjalankan `docker run -d -p 8080:80 nginx`. Menurut dokumentasi Docker, apakah port 8080 tetap bisa diakses dari luar? (ya/tidak)'
    answers: ['ya', 'yes', 'bisa', 'tetap bisa']
    tests:
      fail: ['tidak', 'no', 'tidak bisa']
    explanation: 'Docker memasang aturan NAT/forward sendiri. Trafik ke port yang di-publish dialihkan sebelum mencapai chain INPUT yang diatur ufw. Dokumentasi Docker menyatakan ufw tidak memfilter port container yang di-publish. Solusinya: publish ke `127.0.0.1:8080:80` lalu pasang reverse proxy, atau buat rule di chain `DOCKER-USER`. (Klaim ini dari dokumentasi Docker, tidak kami uji di lab.)'
    source: https://docs.docker.com/engine/network/packet-filtering-firewalls/

  - id: b15
    tier: B
    type: command
    objective: firewall
    prompt: Jalankan container `nginx` (detached) dengan port 80-nya di-publish ke host **hanya di 127.0.0.1:8080**.
    answers: ['docker run -d -p 127.0.0.1:8080:80 nginx']
    patterns: ['^docker run (-d )?(-p|--publish)[= ]?127\.0\.0\.1:8080:80 (-d )?nginx$']
    tests:
      pass: ['docker run -p 127.0.0.1:8080:80 -d nginx', 'docker run -d --publish 127.0.0.1:8080:80 nginx']
      fail: ['docker run -d -p 8080:80 nginx', 'docker run -d -p 80:8080 nginx', 'docker run -d --network host nginx']
    explanation: 'Format `-p [IP:]HOST_PORT:CONTAINER_PORT`. Tanpa IP, Docker bind ke `0.0.0.0` (semua interface) dan, seperti soal sebelumnya, melewati ufw. Untuk database/Redis di Compose: `ports: ["127.0.0.1:5432:5432"]`, atau tanpa `ports` sama sekali bila hanya diakses container lain di network yang sama.'
    source: https://docs.docker.com/engine/network/packet-filtering-firewalls/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: troubleshoot
    prompt: |
      Aplikasi di `app01` (10.0.4.11) tidak bisa konek ke PostgreSQL di `db01` (`db.internal`, 10.0.4.20) setelah DB baru
      dipasang. Tulis langkah troubleshooting dan perbaikan (satu perintah/baris config per baris): DNS, port, apa yang
      listen di db01, perbaikan config, firewall, dan verifikasi — tanpa membuka database ke seluruh internet.
    context: |
      app01$ php artisan migrate
      SQLSTATE[08006] [7] connection to server at "db.internal" (10.0.4.20), port 5432 failed: Connection refused
      db01$ sudo ss -tlnp | grep 5432
      LISTEN 0      200        127.0.0.1:5432      0.0.0.0:*    users:(("postgres",pid=5592,fd=7))
    checklist:
      - id: dns
        label: Memastikan resolusi nama (getent hosts / dig)
        patterns: ['getent\s+(a?hosts)\s+db\.internal', 'dig\s+.*db\.internal']
      - id: port
        label: Menguji port dari app01 (nc -zv / pg_isready)
        required: true
        patterns: ['nc\s+(\S+\s+)*-\S*z\S*\s+(\S+\s+)*(db\.internal|10\.0\.4\.20)\s+5432', 'pg_isready\s+.*-h\s*\S*(db\.internal|10\.0\.4\.20)']
      - id: listen
        label: Memeriksa alamat listen di db01 (ss -tlnp)
        patterns: ['ss\s+-\S*l\S*']
      - id: fix-listen
        label: listen_addresses ke IP privat / '*'
        required: true
        patterns: ["listen_addresses\\s*=\\s*'?(\\*|0\\.0\\.0\\.0|10\\.0\\.4\\.20|localhost,\\s*10\\.0\\.4\\.20)"]
      - id: hba
        label: pg_hba.conf hanya untuk subnet aplikasi
        patterns: ['pg_hba\.conf', '^host\s+\S+\s+\S+\s+10\.0\.\d+\.\d+/\d+\s+(scram-sha-256|md5)']
      - id: firewall
        label: Firewall hanya mengizinkan dari subnet/host aplikasi
        required: true
        patterns: ['ufw\s+allow\s+(proto\s+tcp\s+)?from\s+10\.0\.\S+\s+to\s+any\s+port\s+5432']
      - id: restart
        label: Restart PostgreSQL lalu verifikasi
        patterns: ['systemctl\s+restart\s+postgresql']
      - id: no-open
        label: Tidak membuka 5432 ke semua sumber atau mematikan firewall
        forbid: true
        patterns: ['ufw\s+allow\s+5432(/tcp)?\s*$', 'ufw\s+disable', 'ufw\s+allow\s+from\s+any\s+to\s+any\s+port\s+5432']
      - id: no-trust
        label: Tidak memakai trust / 0.0.0.0/0 di pg_hba
        forbid: true
        patterns: ['^host\s+\S+\s+\S+\s+0\.0\.0\.0/0', '\btrust\s*$']
    pass_score: 0.4
    solution: |
      ```bash
      app01$ getent hosts db.internal              # 10.0.4.20 → DNS benar
      app01$ nc -zv -w 3 10.0.4.20 5432            # refused cepat → paket sampai, tidak ada listener
      db01$  sudo ss -tlnp | grep 5432             # 127.0.0.1:5432 → hanya localhost
      ```
      ```ini
      # /etc/postgresql/16/main/postgresql.conf
      listen_addresses = 'localhost,10.0.4.20'
      # /etc/postgresql/16/main/pg_hba.conf
      host  appdb  appuser  10.0.4.11/32  scram-sha-256
      ```
      ```bash
      db01$  sudo ufw allow from 10.0.4.11 to any port 5432 proto tcp
      db01$  sudo systemctl restart postgresql
      app01$ nc -zv -w 3 10.0.4.20 5432            # succeeded
      ```
      Direproduksi di lab dengan PostgreSQL 16: default `#listen_addresses = 'localhost'` → `127.0.0.1:5432`, dan
      dari IP lain `Connection refused`. Setelah diubah, `0.0.0.0:5432` dan `succeeded`. Ada tiga lapisan, dan
      **ketiganya** harus benar:
      - **listen**: proses mau menerima di interface itu
      - **firewall**: paket boleh masuk
      - **pg_hba**: PostgreSQL mau mengautentikasi sumber itu

      Bila gejalanya timeout, bukan refused, curigai firewall atau security group lebih dulu.
    tests:
      pass:
        - |
          getent hosts db.internal
          nc -zv -w 3 10.0.4.20 5432
          ss -tlnp | grep 5432
          listen_addresses = 'localhost,10.0.4.20'
          host appdb appuser 10.0.4.11/32 scram-sha-256
          ufw allow from 10.0.4.11 to any port 5432 proto tcp
          systemctl restart postgresql
        - |
          nc -zv db.internal 5432
          listen_addresses = '*'
          ufw allow from 10.0.4.0/24 to any port 5432
      fail:
        - |
          ufw allow 5432
          listen_addresses = '*'
          nc -zv db.internal 5432
        - |
          nc -zv db.internal 5432
          ufw disable
        - |
          ping db.internal
          systemctl restart postgresql
    source: https://www.postgresql.org/docs/16/runtime-config-connection.html

  - id: c02
    tier: C
    type: scenario
    objective: dns
    prompt: |
      Setelah migrasi `shop.example.com` ke server baru `203.0.113.10`, sebagian pengguna (dan kamu sendiri) masih
      diarahkan ke server lama. Tulis perintah (satu per baris) untuk membuktikan apakah ini masalah cache/propagasi
      DNS, menguji server baru secara langsung, memeriksa sertifikatnya, dan membersihkan cache lokal.
    context: |
      $ dig +noall +answer shop.example.com
      shop.example.com.	2931	IN	A	198.51.100.7
      $ dig +short NS example.com
      ns1.dns-provider.net.
      ns2.dns-provider.net.
    checklist:
      - id: authoritative
        label: Bertanya ke nameserver otoritatif / resolver lain (dig @ns1...)
        required: true
        patterns: ['dig\s+(\+\S+\s+)*@\S+\s+(\+\S+\s+)*shop\.example\.com', 'dig\s+(\+\S+\s+)*shop\.example\.com.*\s@\S+']
      - id: ttl
        label: Membaca TTL (dig +noall +answer) — sisa waktu cache
        patterns: ['dig\s+.*\+noall\s+\+answer', '\bttl\b']
      - id: resolve-test
        label: Menguji server baru langsung (curl --resolve ...:443:203.0.113.10)
        required: true
        patterns: ['curl\s+.*--resolve[= ]?shop\.example\.com:443:203\.0\.113\.10']
      - id: cert
        label: Memeriksa sertifikat di server baru dengan SNI
        patterns: ['openssl\s+s_client\s+.*-servername\s+shop\.example\.com', 'curl\s+(-\S+\s+)*-v']
      - id: flush
        label: Membersihkan cache resolver lokal (resolvectl flush-caches)
        patterns: ['resolvectl\s+flush-caches', 'systemd-resolve\s+--flush-caches']
      - id: no-insecure
        label: Tidak menguji dengan -k/--insecure
        forbid: true
        patterns: ['curl\s+(\S+\s+)*(-k|--insecure)\b', 'curl\s+-\S*k']
    solution: |
      ```bash
      dig +short @ns1.dns-provider.net shop.example.com      # otoritatif: 203.0.113.10?
      dig +noall +answer shop.example.com                    # resolver: 198.51.100.7, TTL 2931 detik tersisa
      curl -sI --resolve shop.example.com:443:203.0.113.10 https://shop.example.com/
      openssl s_client -connect 203.0.113.10:443 -servername shop.example.com </dev/null 2>/dev/null | openssl x509 -noout -subject -enddate
      sudo resolvectl flush-caches                           # cache lokal saja
      ```
      Jika nameserver otoritatif sudah menjawab IP baru tetapi resolver masih memberi IP lama, record lama masih
      berada di cache sampai TTL-nya habis (di sini ±49 menit). Cache resolver ISP dan browser pengguna tidak
      bisa kamu flush. Pelajaran untuk migrasi berikutnya: **turunkan TTL** (mis. 300 detik) satu hari sebelum
      pindah, dan biarkan server lama tetap hidup selama TTL lama. `--resolve` membuktikan server baru siap
      (sertifikat, vhost) tanpa menunggu DNS.
    tests:
      pass:
        - |
          dig +short @ns1.dns-provider.net shop.example.com
          dig +noall +answer shop.example.com
          curl -sI --resolve shop.example.com:443:203.0.113.10 https://shop.example.com/
          resolvectl flush-caches
        - |
          dig shop.example.com @1.1.1.1
          curl -v --resolve shop.example.com:443:203.0.113.10 https://shop.example.com/
          openssl s_client -connect 203.0.113.10:443 -servername shop.example.com
      fail:
        - |
          ping shop.example.com
          curl -k https://203.0.113.10/
        - |
          dig shop.example.com
          echo '203.0.113.10 shop.example.com' >> /etc/hosts
        - |
          dig +short @ns1.dns-provider.net shop.example.com
          curl -k --resolve shop.example.com:443:203.0.113.10 https://shop.example.com/
    source: https://curl.se/docs/manpage.html#--resolve

  - id: c03
    tier: C
    type: scenario
    objective: firewall
    prompt: |
      VPS baru (akses hanya lewat SSH) akan menjadi web server publik. Ada Redis untuk cache aplikasi di host yang sama.
      Tulis langkah (satu per baris) untuk menginventaris port yang terbuka, mengamankan Redis, lalu menyalakan
      firewall ufw yang hanya membuka SSH dan web — **tanpa mengunci dirimu sendiri**.
    context: |
      $ sudo ss -tlnp
      LISTEN 0  511   0.0.0.0:6379   0.0.0.0:*  users:(("redis-server",pid=812,fd=6))
      LISTEN 0  4096  0.0.0.0:22     0.0.0.0:*  users:(("sshd",pid=640,fd=3))
      LISTEN 0  511   0.0.0.0:80     0.0.0.0:*  users:(("nginx",pid=901,fd=6))
      $ sudo ufw status
      Status: inactive
    checklist:
      - id: inventory
        label: Inventaris port listen (ss -tlnp)
        patterns: ['ss\s+-\S*l\S*']
      - id: ssh-first
        label: Mengizinkan SSH (allow/limit 22) sebelum enable
        required: true
        patterns: ['ufw\s+(allow|limit)\s+(22(/tcp)?|ssh|openssh)\b']
      - id: web
        label: Membuka 80/443
        patterns: ['ufw\s+allow\s+(80|443|http|https|80,443/tcp|80/tcp|443/tcp|"?nginx full"?|''nginx full'')']
      - id: default
        label: Default deny incoming
        required: true
        patterns: ['ufw\s+default\s+deny(\s+incoming)?']
      - id: enable
        label: Mengaktifkan ufw lalu cek status
        required: true
        patterns: ['ufw\s+(--force\s+)?enable']
      - id: redis-bind
        label: Redis bind ke localhost (bind 127.0.0.1 / protected-mode yes) + restart
        patterns: ['bind\s+127\.0\.0\.1', 'protected-mode\s+yes']
      - id: verify
        label: Verifikasi (ufw status verbose, ss lagi, uji dari sesi/ host lain)
        patterns: ['ufw\s+status', 'nc\s+-\S*z']
      - id: no-redis-open
        label: Tidak membuka Redis ke publik
        forbid: true
        patterns: ['ufw\s+allow\s+6379', 'ufw\s+allow\s+from\s+any\s+to\s+any\s+port\s+6379']
      - id: no-allow-all
        label: Tidak default allow incoming / disable firewall
        forbid: true
        patterns: ['ufw\s+default\s+allow(\s+incoming)?\s*$', 'ufw\s+disable']
    pass_score: 0.6
    solution: |
      ```bash
      sudo ss -tlnp                                   # Redis di 0.0.0.0:6379 = terbuka ke internet
      # /etc/redis/redis.conf:
      #   bind 127.0.0.1 -::1
      #   protected-mode yes
      sudo systemctl restart redis-server
      sudo ufw default deny incoming
      sudo ufw default allow outgoing
      sudo ufw limit 22/tcp                           # SSH DULU
      sudo ufw allow 80/tcp
      sudo ufw allow 443/tcp
      sudo ufw enable
      sudo ufw status verbose
      sudo ss -tlnp                                   # Redis kini 127.0.0.1:6379
      ```
      Ada dua lapis pertahanan untuk Redis:
      - Service hanya listen di localhost.
      - Firewall tidak membuka port-nya.

      Redis tanpa password yang terbuka ke internet adalah target otomatis: bot memindai port 6379 terus-menerus.
      Uji login SSH dari **terminal kedua** sebelum menutup sesi yang aktif. Kalau ada container dengan `-p`,
      ingat bahwa port yang di-publish Docker tidak difilter ufw (lihat bagian 7).
    tests:
      pass:
        - |
          ss -tlnp
          bind 127.0.0.1 -::1
          systemctl restart redis-server
          ufw default deny incoming
          ufw allow 22/tcp
          ufw allow 80/tcp
          ufw allow 443/tcp
          ufw enable
          ufw status verbose
        - |
          ufw limit ssh
          ufw default deny
          ufw allow 'Nginx Full'
          ufw --force enable
          protected-mode yes
      fail:
        - |
          ufw default deny incoming
          ufw enable
        - |
          ufw allow 22/tcp
          ufw allow 6379
          ufw enable
        - |
          ss -tlnp
          ufw disable
    source: https://documentation.ubuntu.com/server/how-to/security/firewalls/

---

# Networking Dasar (ip, ss, DNS, curl, firewall)

"Aplikasi tidak bisa konek" adalah tiket paling umum yang diterima tim DevOps. Penyebabnya bisa ada di mana saja:
DNS yang menjawab IP lama, service yang hanya listen di localhost, firewall yang membuang paket, sertifikat yang
salah, atau aplikasi yang lambat. Modul ini membahas alat untuk memeriksa setiap lapisan itu satu per satu, dari
dalam server Linux.

> [!NOTE]
> Semua output diuji ulang di container `ubuntu:24.04` (`--privileged`, agar nftables/ufw/tcpdump bisa dipakai) pada
> jaringan bridge Docker Desktop. IP lab: `172.17.0.2/16`, gateway `172.17.0.1`. Nama dan IP seperti
> `db.internal`, `203.0.113.x`, dan `198.51.100.x` di soal adalah contoh (rentang dokumentasi RFC 5737).

## 1. Model berlapis

```mermaid
flowchart TD
    A[Aplikasi tidak bisa konek] --> D{1. DNS<br/>getent hosts / dig}
    D -- salah/NXDOMAIN --> DF[Perbaiki record / cache / /etc/hosts]
    D -- benar --> R{2. Routing<br/>ip route get IP}
    R --> T{3. TCP<br/>nc -zv host port}
    T -- refused --> L[Tidak ada listener:<br/>service mati / bind 127.0.0.1]
    T -- timeout --> F[Firewall / security group DROP]
    T -- succeeded --> S{4. TLS<br/>openssl s_client / curl -v}
    S -- error sertifikat --> C[Nama, rantai, kedaluwarsa]
    S -- ok --> H{5. HTTP<br/>curl -w status & timing}
    H --> AP[4xx/5xx/lambat → log aplikasi]
```

## 2. IP dan routing

```bash
ip -br a                  # ringkas: interface, status, IP
ip r                      # tabel routing (default via ...)
ip route get 1.1.1.1      # rute yang BENAR-BENAR dipakai
ip link                   # status link (UP/DOWN), MTU
ip neigh                  # tabel ARP (IP ↔ MAC di LAN)
```

```text
lo               UNKNOWN        127.0.0.1/8 ::1/128
eth0@if78        UP             172.17.0.2/16
default via 172.17.0.1 dev eth0
1.1.1.1 via 172.17.0.1 dev eth0 src 172.17.0.2
```

## 3. Port dan koneksi: `ss`

```bash
ss -tlnp                       # TCP listening + proses
ss -ulnp                       # UDP
ss -tn state established
ss -tlnp 'sport = :8001'
ss -s                          # ringkasan
```

| Local Address | Bisa dijangkau dari |
|---|---|
| `127.0.0.1:8000` | Host itu saja |
| `0.0.0.0:8001` / `*:8001` | Semua interface IPv4 |
| `[::]:80` | Semua interface IPv6 (sering juga IPv4) |
| `10.0.4.20:5432` | Hanya lewat IP itu |

Di lab, satu host dengan dua server Python: `curl 127.0.0.1:8000` → 200, `curl 172.17.0.2:8000` →
`Couldn't connect to server`, `curl 172.17.0.2:8001` → 200.

## 4. DNS

```bash
dig +short example.com
dig +noall +answer example.com          # dengan TTL: example.com. 89 IN A 104.20.23.154
dig +short @1.1.1.1 example.com AAAA
dig +short MX gmail.com
dig +short -x 1.1.1.1                   # reverse
dig nosuch.invalid | grep status        # NXDOMAIN
getent hosts api.internal               # seperti aplikasi (nsswitch)
resolvectl status                       # Ubuntu + systemd-resolved: upstream DNS sebenarnya
```

> [!INSIGHT]
> `dig`, `host`, dan `nslookup` **tidak** membaca `/etc/hosts`; mereka bicara langsung ke DNS server. Aplikasi
> (curl, PHP, Java) memakai resolver sistem yang mengikuti `/etc/nsswitch.conf` (`hosts: files dns`). Di lab, dengan
> `10.9.9.9 api.internal` di `/etc/hosts`: `getent hosts` → `10.9.9.9`, `dig +short` → kosong, dan `curl` mencoba
> konek ke `10.9.9.9`. Kalau "dig benar tapi aplikasi salah", hampir selalu ada entri tertinggal di `/etc/hosts`.
> Sumber: [nsswitch.conf(5)](https://man7.org/linux/man-pages/man5/nsswitch.conf.5.html)

**TTL** adalah lama (detik) sebuah jawaban boleh di-cache. Saat migrasi, record lama tetap dipakai resolver
sampai TTL-nya habis. Turunkan TTL **sebelum** pindah.

## 5. Refused vs timeout

| Gejala | Arti | Di lab |
|---|---|---|
| `succeeded` | TCP handshake berhasil | port 8001 |
| `Connection refused` (cepat) | Paket sampai, dibalas RST: tidak ada listener, atau firewall `reject` | port 9001, 0 ms |
| `timed out` | Tidak ada balasan: firewall `drop`, security group, route salah | port 9000 + `nft ... drop`, 3 detik |
| `No route to host` | Routing/ARP gagal, atau ICMP unreachable | — |

```bash
nc -zv -w 3 host 5432
curl -sS --connect-timeout 3 http://host:9000/
```

curl 8.5 menulis refused sebagai `Failed to connect ... after 0 ms: Couldn't connect to server`, dan timeout
sebagai `Connection timed out after 3002 milliseconds`.

## 6. HTTP dan TLS dari terminal

```bash
curl -sI https://example.com/
curl -s -o /dev/null -w '%{http_code}\n' https://example.com/
curl -s -o /dev/null -w 'dns=%{time_namelookup} connect=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total}\n' https://example.com/
curl --resolve shop.example.com:443:203.0.113.10 https://shop.example.com/   # uji server baru
curl -H 'Host: shop.example.com' http://127.0.0.1/                           # vhost lewat HTTP
curl --cacert /etc/ssl/internal-ca.pem https://api.internal/                 # CA internal
echo | openssl s_client -connect example.com:443 -servername example.com 2>/dev/null \
  | openssl x509 -noout -subject -issuer -enddate
```

Hasil di lab:
- `dns=0.002 connect=0.076 tls=0.244 ttfb=0.345 total=0.345`
- Server Python yang mencetak header Host: `host=127.0.0.1:8002`, dan dengan `-H 'Host: shop.example.com'` →
  `host=shop.example.com`

> [!DANGER]
> `curl -k` / `--insecure` mematikan **seluruh** verifikasi sertifikat, sehingga siapa pun di jalur jaringan bisa
> menyamar sebagai servermu. Di lab, `-k` menerima sertifikat self-signed tanpa protes. Untuk CA internal, pakai
> `--cacert`, atau pasang CA ke trust store sistem (`update-ca-certificates`). Jangan pernah membiarkan `-k` ada di
> skrip deploy atau healthcheck production.
> Sumber: [curl — --insecure](https://curl.se/docs/manpage.html#-k)

## 7. Firewall: ufw dan nftables

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw limit 22/tcp                         # SSH dulu!
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow from 10.0.0.0/8 to any port 5432 proto tcp
sudo ufw enable
sudo ufw status verbose
sudo ufw status numbered && sudo ufw delete 2
sudo nft list ruleset                          # aturan sebenarnya di kernel
```

ufw adalah frontend: di lab, setelah beberapa rule ufw, `nft list ruleset` menghasilkan 320 baris aturan.
nftables adalah pengganti modern iptables. Rule ad-hoc untuk menguji DROP di lab:

```bash
nft add table inet lab
nft add chain inet lab input '{ type filter hook input priority 0; }'
nft add rule inet lab input tcp dport 9000 drop
nft delete table inet lab
```

> [!WARNING]
> **Docker vs ufw.** Port yang di-publish dengan `docker run -p 8080:80` diproses aturan NAT/forward milik Docker
> sebelum chain yang diatur ufw, sehingga port itu terbuka walaupun ufw `deny incoming`. Ini tercatat di
> dokumentasi Docker (bagian "Docker and ufw"), tidak kami uji di lab. Publish ke `127.0.0.1:PORT:PORT` untuk service
> internal, dan taruh reverse proxy di depan untuk yang publik.
> Sumber: [Docker Docs — Packet filtering and firewalls](https://docs.docker.com/engine/network/packet-filtering-firewalls/)

## 8. Melihat paket: tcpdump

```bash
sudo tcpdump -i any -nn -c 20 tcp port 443
sudo tcpdump -i eth0 -nn host 10.0.4.20 and port 5432
sudo tcpdump -i any -nn -w /tmp/cap.pcap port 80    # buka dengan Wireshark
```

```text
IP 127.0.0.1.50648 > 127.0.0.1.8001: Flags [S], ...       ← SYN
IP 127.0.0.1.8001 > 127.0.0.1.50648: Flags [S.], ...      ← SYN-ACK
IP 127.0.0.1.50648 > 127.0.0.1.8001: Flags [.], ack 1 ... ← ACK
```

SYN tanpa SYN-ACK = paket hilang/di-drop. `[R]` = ditolak (refused).

## 9. Ringkasan kebiasaan aman

| Kebiasaan | Kenapa |
|---|---|
| Diagnosis berlapis: DNS → route → TCP → TLS → HTTP | Langsung tahu lapisan yang rusak |
| `getent hosts`, bukan hanya `dig` | Aplikasi juga membaca `/etc/hosts` |
| Bedakan refused vs timeout | Tidak ada listener vs firewall |
| Database/Redis listen di localhost/IP privat + firewall per sumber | Dua lapis pertahanan |
| `ufw allow 22` sebelum `ufw enable`, uji dari sesi kedua | Tidak mengunci diri sendiri |
| `--resolve` untuk menguji migrasi, turunkan TTL sebelum pindah | Migrasi tanpa downtime yang membingungkan |
| `--cacert`, bukan `-k` | Verifikasi TLS tetap hidup |
| Publish port Docker ke `127.0.0.1` bila tidak publik | Docker melewati ufw |
