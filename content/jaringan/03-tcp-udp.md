---
format: 1
id: net-tcp-udp
course:
  slug: jaringan
  title: Jaringan
  description: Konsep jaringan untuk DevOps — TCP/IP, subnetting, routing, TCP/UDP, DNS, HTTP, TLS, proxy, firewall, VPN, dan troubleshooting.
title: TCP & UDP
summary: Handshake dan penutupan koneksi di paket nyata, state TCP (TIME_WAIT, CLOSE_WAIT, FIN-WAIT-2), RST vs DROP vs REJECT, retransmisi SYN, backlog listen, kehabisan port ephemeral, keepalive, efek packet loss, dan perilaku UDP — semua diukur di lab.
order: 3
version: 1
status: verified
updated: '2026-11-24'
tested_on:
  - Docker Desktop (Windows) — kernel 6.18 (WSL2), container python:3.12-alpine + tcpdump, iptables, iproute2 (ss, nstat, tc netem)
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: handshake
    text: Membaca handshake, transfer data, dan penutupan koneksi TCP
  - id: states
    text: Memahami state TCP (TIME_WAIT, CLOSE_WAIT, FIN-WAIT-2) dan siapa yang mendapatkannya
  - id: errors
    text: Membedakan RST, DROP, dan REJECT dari error di sisi klien
  - id: listen
    text: Memahami backlog listen dan overflow
  - id: ports
    text: Memahami port ephemeral dan kehabisannya
  - id: keepalive
    text: Memahami keepalive dan deteksi peer mati
  - id: reliability
    text: Memahami retransmisi dan efek packet loss
  - id: udp
    text: Memahami perilaku UDP dan jebakan saat mengujinya
sources:
  - title: RFC 9293 — Transmission Control Protocol (TCP)
    url: https://www.rfc-editor.org/rfc/rfc9293
  - title: RFC 768 — User Datagram Protocol
    url: https://www.rfc-editor.org/rfc/rfc768
  - title: Linux kernel — IP sysctl (tcp_syn_retries, tcp_syn_linear_timeouts, tcp_keepalive_*, ip_local_port_range, tcp_tw_reuse)
    url: https://docs.kernel.org/networking/ip-sysctl.html
  - title: tcp(7) — Linux manual (TCP_KEEPIDLE, TIME_WAIT, errors)
    url: https://man7.org/linux/man-pages/man7/tcp.7.html
  - title: udp(7) — Linux manual (ICMP errors and connected sockets)
    url: https://man7.org/linux/man-pages/man7/udp.7.html
  - title: listen(2) — backlog
    url: https://man7.org/linux/man-pages/man2/listen.2.html
  - title: ss(8)
    url: https://man7.org/linux/man-pages/man8/ss.8.html
  - title: iptables-extensions(8) — REJECT target
    url: https://man7.org/linux/man-pages/man8/iptables-extensions.8.html
  - title: tc-netem(8)
    url: https://man7.org/linux/man-pages/man8/tc-netem.8.html
  - title: RFC 9000 — QUIC (transport di atas UDP)
    url: https://www.rfc-editor.org/rfc/rfc9000
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: handshake
    prompt: Urutan flag tiga paket **three-way handshake** TCP?
    answers: ['SYN, SYN-ACK, ACK']
    patterns: ['^syn[ ,>-]+syn[ -/]?ack[ ,>-]+ack$', '^\[?s\]?[ ,>-]+\[?s\.\]?[ ,>-]+\[?\.\]?$']
    tests:
      pass: ['syn, syn-ack, ack', 'SYN > SYN/ACK > ACK', '[S] [S.] [.]']
      fail: ['syn, ack, fin', 'ack, syn, syn-ack', 'syn, syn, ack']
    explanation: 'Di lab tcpdump: `Flags [S]` dari klien, `Flags [S.]` (SYN+ACK) dari server, lalu `Flags [.]` (ACK) dari klien. Baru setelah itu data dikirim (`[P.]` = PUSH+ACK). Setiap koneksi butuh satu round-trip sebelum byte pertama dikirim. Itu sebabnya koneksi baru ke server jauh terasa lambat, dan connection pooling menghemat waktu.'
    source: https://www.rfc-editor.org/rfc/rfc9293

  - id: a02
    tier: A
    type: text
    objective: errors
    prompt: Flag TCP apa yang dikirim host saat menerima SYN ke port yang **tidak ada** yang listen?
    answers: ['RST', 'reset']
    patterns: ['^(rst|reset|\[r\.?\]|rst[ /]?ack)$']
    tests:
      pass: ['RST', '[R.]', 'rst ack']
      fail: ['FIN', 'SYN', 'ACK', 'ICMP']
    explanation: 'Di lab: `10.61.0.3.49754 > 10.61.0.2.8001: Flags [S]`, lalu langsung `10.61.0.2.8001 > ...: Flags [R.]`. Penolakan ini instan dan membuktikan host-nya **hidup** dan bisa dijangkau. Yang tertutup hanya port-nya.'
    source: https://www.rfc-editor.org/rfc/rfc9293

  - id: a03
    tier: A
    type: text
    objective: errors
    prompt: Error apa yang dilihat aplikasi klien saat `connect()` dibalas RST?
    answers: ['Connection refused', 'ECONNREFUSED']
    patterns: ['(connection refused|econnrefused|errno 111|refused)']
    tests:
      pass: ['ConnectionRefusedError [Errno 111] Connection refused', 'ECONNREFUSED']
      fail: ['timed out', 'host is unreachable', 'connection reset by peer', 'address not available']
    explanation: 'Di lab (Python): `ConnectionRefusedError [Errno 111] Connection refused`. Penyebab umumnya: service belum jalan, listen di alamat lain (127.0.0.1 vs 0.0.0.0), atau salah port. Firewall dengan `REJECT --reject-with tcp-reset` juga menghasilkan error yang sama (tier B).'
    source: https://man7.org/linux/man-pages/man7/tcp.7.html

  - id: a04
    tier: A
    type: command
    objective: states
    prompt: Dengan `ss`, tampilkan semua socket TCP (angka, tanpa resolve) yang berstatus **TIME-WAIT**.
    answers: ['ss -tan state time-wait']
    patterns: ['^ss -(tan|tna|atn|ant|nta|nat|tn|nt) state time-wait$']
    tests:
      pass: ['ss -tn state time-wait', 'ss -ant state time-wait']
      fail: ['ss -tan | grep TIME', 'ss -tan state timewait', 'ss -uan state time-wait']
    explanation: 'Di lab, setelah server menutup koneksi lebih dulu, `ss -tan state time-wait` di server menampilkan `10.61.0.2:8000 10.61.0.3:46730`, sedangkan di klien kosong. Filter `state` lain: `established`, `close-wait`, `fin-wait-2`, `syn-sent`, `listening`.'
    source: https://man7.org/linux/man-pages/man8/ss.8.html

  - id: a05
    tier: A
    type: text
    objective: states
    prompt: Sisi mana yang masuk ke state **TIME_WAIT** setelah koneksi TCP ditutup?
    answers: ['sisi yang menutup lebih dulu (active close)']
    patterns: ['(menutup (lebih )?dulu|pertama menutup|duluan|active[ -]?clos|aktif menutup|yang (mengirim|kirim) fin (pertama|dulu))']
    tests:
      pass: ['yang menutup lebih dulu', 'active closer', 'yang kirim FIN pertama']
      fail: ['selalu klien', 'selalu server', 'keduanya', 'yang menerima FIN']
    explanation: 'Di lab, server memanggil `close()` lebih dulu (`Flags [F.]` dari 10.61.0.2), sehingga TIME_WAIT ada di **server**, bukan klien. TIME_WAIT menjaga agar paket terlambat dari koneksi lama tidak tercampur ke koneksi baru dengan 4-tuple sama. Bukan "selalu klien", karena ditentukan oleh siapa yang menutup lebih dulu.'
    source: https://www.rfc-editor.org/rfc/rfc9293

  - id: a06
    tier: A
    type: text
    objective: states
    prompt: Berapa lama socket berada di TIME_WAIT di Linux?
    answers: ['60 detik', '60', '60s', '1 menit']
    patterns: ['^(60 ?(detik|s|sec|seconds)?|1 menit|satu menit)$']
    tests:
      pass: ['60 detik', '60s']
      fail: ['120', '2 jam', '240 detik', '30']
    explanation: 'Linux memakai nilai tetap `TCP_TIMEWAIT_LEN` 60 detik (RFC menyarankan 2×MSL). Nilai ini **tidak** bisa diubah lewat sysctl. `tcp_fin_timeout` (lab: `60`) mengatur FIN-WAIT-2, bukan TIME_WAIT, sehingga menurunkannya tidak mengurangi TIME_WAIT.'
    source: https://man7.org/linux/man-pages/man7/tcp.7.html

  - id: a07
    tier: A
    type: text
    objective: keepalive
    prompt: Nilai default `net.ipv4.tcp_keepalive_time` (detik idle sebelum probe keepalive pertama)?
    answers: ['7200', '2 jam']
    patterns: ['^(7200( detik| s)?|2 jam|dua jam)$']
    tests:
      pass: ['7200 detik', '2 jam']
      fail: ['75', '60', '300', '9']
    explanation: 'Default di lab: `tcp_keepalive_time=7200`, `tcp_keepalive_intvl=75`, `tcp_keepalive_probes=9`. Dengan default ini, peer mati baru terdeteksi setelah sekitar 2 jam 11 menit. Firewall/NAT umumnya membuang koneksi idle jauh lebih cepat (beberapa menit), sehingga koneksi DB idle putus diam-diam (tier B).'
    source: https://docs.kernel.org/networking/ip-sysctl.html

  - id: a08
    tier: A
    type: text
    objective: ports
    prompt: Rentang default `net.ipv4.ip_local_port_range` (port ephemeral untuk koneksi keluar) di Linux?
    answers: ['32768 60999', '32768-60999']
    patterns: ['^32768[ \t,-]+60999$']
    tests:
      pass: ['32768 - 60999', '32768,60999']
      fail: ['1024-65535', '49152-65535', '0-65535', '32768-65535']
    explanation: 'Di lab: `ip_local_port_range=32768 60999`, sekitar 28 ribu port. Windows/IANA memakai 49152–65535. Port sumber di tcpdump (mis. `46730`) diambil dari rentang ini. Satu klien ke **satu** tujuan (IP:port sama) hanya bisa punya koneksi sebanyak port di rentang ini.'
    source: https://docs.kernel.org/networking/ip-sysctl.html

  - id: a09
    tier: A
    type: text
    objective: udp
    prompt: Apakah UDP melakukan handshake sebelum mengirim data? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab, satu echo UDP hanya terdiri dari dua paket: `UDP, length 2` (request) dan `UDP, length 7` (balasan). Tidak ada SYN, ACK, atau FIN. UDP tidak menjamin urutan, pengiriman, atau ketiadaan duplikasi. Kalau dibutuhkan, aplikasi harus mengurusnya sendiri (DNS dengan retry, QUIC dengan reliability-nya sendiri).'
    source: https://www.rfc-editor.org/rfc/rfc768

  - id: a10
    tier: A
    type: text
    objective: udp
    prompt: Pesan ICMP apa yang dikirim host saat menerima datagram UDP ke port yang tidak ada yang listen?
    answers: ['port unreachable']
    patterns: ['port unreachable', 'destination unreachable', 'type 3 code 3']
    tests:
      pass: ['ICMP port unreachable', 'destination unreachable (port)']
      fail: ['rst', 'time exceeded', 'echo reply', 'frag needed']
    explanation: 'Di lab: `ICMP 10.61.0.2 udp port 9001 unreachable`. UDP tidak punya RST, jadi kernel memakai ICMP type 3 code 3 sebagai gantinya. Bila firewall men-DROP port itu, tidak ada balasan apa pun, sehingga dari luar port "tertutup" dan "difilter" tidak bisa dibedakan.'
    source: https://www.rfc-editor.org/rfc/rfc792

  - id: a11
    tier: A
    type: command
    objective: udp
    prompt: Dengan `ss`, tampilkan socket **UDP** yang sedang menunggu datagram (bound/listen), dalam bentuk angka.
    answers: ['ss -uln']
    patterns: ['^ss -(uln|unl|lun|lnu|nlu|nul|ulnp|unlp|lunp|ulpn)$']
    tests:
      pass: ['ss -lun', 'ss -ulnp']
      fail: ['ss -tln', 'ss -ua', 'ss -uan state listening']
    explanation: 'Di lab: `UNCONN 0 0 0.0.0.0:9000 0.0.0.0:*`. UDP tidak punya state LISTEN, sehingga socket yang bound terlihat sebagai `UNCONN`. Gunakan `-p` untuk melihat prosesnya (butuh root untuk proses milik user lain).'
    source: https://man7.org/linux/man-pages/man8/ss.8.html

  - id: a12
    tier: A
    type: command
    objective: states
    prompt: Dengan `ss`, tampilkan semua socket TCP (angka) yang berstatus **CLOSE-WAIT**.
    answers: ['ss -tan state close-wait']
    patterns: ['^ss -(tan|tna|atn|ant|nta|nat|tn|nt|tanp|tnp) state close-wait$']
    tests:
      pass: ['ss -tn state close-wait', 'ss -tanp state close-wait']
      fail: ['ss -tan state closewait', 'ss -tan state time-wait', 'netstat close-wait']
    explanation: 'Di lab, server yang menerima koneksi tetapi tidak pernah memanggil `close()` menghasilkan `server CLOSE-WAIT count: 5` setelah lima klien menutup koneksi. Tambahkan `-p` untuk melihat proses pemiliknya. CLOSE-WAIT yang terus bertambah hampir selalu berarti bug di aplikasi (studi kasus C).'
    source: https://man7.org/linux/man-pages/man8/ss.8.html

  - id: a13
    tier: A
    type: text
    objective: reliability
    prompt: Nilai default `net.ipv4.tcp_syn_retries` di Linux?
    answers: ['6']
    tests:
      fail: ['3', '5', '15', '2']
    explanation: 'Di lab: `tcp_syn_retries=6`. Bila SYN tidak pernah dijawab (firewall DROP), kernel mencoba ulang 6 kali sebelum `connect()` menyerah, kira-kira lebih dari 1 menit kalau aplikasi tidak memasang timeout sendiri. Selalu set connect timeout di aplikasi (lab: `timeout=7.5` → `TimeoutError` setelah 7.5 s).'
    source: https://docs.kernel.org/networking/ip-sysctl.html

  - id: a14
    tier: A
    type: text
    objective: udp
    prompt: HTTP/3 (QUIC) berjalan di atas protokol transport apa?
    answers: ['UDP']
    tests:
      fail: ['TCP', 'SCTP', 'TLS', 'ICMP']
    explanation: 'QUIC (RFC 9000) memakai UDP port 443 dan membangun reliability, ordering, dan enkripsi TLS 1.3 sendiri di user space. Konsekuensi operasional: firewall yang hanya membuka TCP 443 membuat browser jatuh ke HTTP/2. DNS, NTP, WireGuard, dan syslog klasik juga berbasis UDP.'
    source: https://www.rfc-editor.org/rfc/rfc9000

  - id: a15
    tier: A
    type: text
    objective: ports
    prompt: Empat komponen yang secara unik mengidentifikasi satu koneksi TCP (4-tuple)?
    answers: ['IP sumber, port sumber, IP tujuan, port tujuan']
    patterns: ['(?=.*((ip|alamat)[ \t]*(sumber|asal|source|src)|(source|src)[ \t]*ip))(?=.*(port[ \t]*(sumber|asal|source|src)|(source|src)[ \t]*port))(?=.*((ip|alamat)[ \t]*(tujuan|dest|dst)|(dest\w*|dst)[ \t]*ip))(?=.*(port[ \t]*(tujuan|dest|dst)|(dest\w*|dst)[ \t]*port))']
    tests:
      pass: ['ip sumber, port sumber, ip tujuan, port tujuan', 'source ip, source port, dest ip, dest port']
      fail: ['ip sumber dan ip tujuan', 'mac, ip, port, protokol', 'port sumber dan port tujuan']
    explanation: 'Satu server di port 80 bisa melayani ribuan klien karena setiap koneksi berbeda di IP/port sumber. Di lab, klien yang kehabisan port ke `:8010` masih bisa membuka 7 koneksi baru ke `:8000` dengan port lokal yang sama, karena 4-tuple-nya berbeda.'
    source: https://www.rfc-editor.org/rfc/rfc9293

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: errors
    prompt: Firewall server men-**DROP** SYN ke port 8002. Apa yang dialami klien yang `connect()` dengan timeout 7.5 detik?
    answers: ['timeout setelah 7.5 detik, SYN dikirim ulang']
    patterns: ['(timeout|timed out|time out|menunggu|tidak ada (balasan|jawaban)|hang)']
    tests:
      pass: ['TimeoutError timed out', 'menunggu lalu timeout', 'SYN diulang, akhirnya timeout']
      fail: ['connection refused', 'host is unreachable', 'connection reset', 'langsung gagal']
    explanation: 'Di lab: `TimeoutError timed out 7.5 s`, dengan tcpdump menunjukkan SYN dikirim ulang di +1, +2, +3, +4, +5, +7 detik. DROP tidak memberi informasi apa pun, sehingga klien terus mencoba. **Refused = host hidup, port tertutup. Timeout = paket dibuang di jalan** (firewall, security group, host mati, route).'
    source: https://man7.org/linux/man-pages/man7/tcp.7.html

  - id: b02
    tier: B
    type: text
    objective: errors
    prompt: 'Firewall memakai `REJECT --reject-with icmp-host-prohibited`. Error apa yang dilihat klien TCP?'
    answers: ['Host is unreachable', 'No route to host']
    patterns: ['(host is unreachable|no route to host|ehostunreach|errno 113|unreachable)']
    tests:
      pass: ['OSError [Errno 113] Host is unreachable', 'no route to host']
      fail: ['connection refused', 'timed out', 'connection reset by peer']
    explanation: 'Hasil lab untuk tiga varian REJECT:
      - `icmp-port-unreachable` (default iptables) → `Connection refused`.
      - `tcp-reset` → `Connection refused`.
      - `icmp-host-prohibited` → `[Errno 113] Host is unreachable`.

      Karena "No route to host" bisa berasal dari firewall, cek firewall dulu sebelum menyalahkan routing. firewalld (RHEL) memakai `icmp-host-prohibited` secara default.'
    source: https://man7.org/linux/man-pages/man8/iptables-extensions.8.html

  - id: b03
    tier: B
    type: text
    objective: reliability
    prompt: 'Di kernel lab (6.18), SYN yang tidak dijawab dikirim ulang dengan jeda sekitar 1 detik secara linear dulu, baru kemudian berlipat. Sysctl apa yang mengatur jumlah retransmisi linear ini?'
    answers: ['net.ipv4.tcp_syn_linear_timeouts']
    patterns: ['tcp_syn_linear_timeouts']
    tests:
      pass: ['tcp_syn_linear_timeouts', 'net.ipv4.tcp_syn_linear_timeouts=4']
      fail: ['tcp_syn_retries', 'tcp_retries2', 'tcp_fin_timeout', 'tcp_synack_retries']
    explanation: 'Di lab: `tcp_syn_linear_timeouts=4`, dan SYN terlihat di +0, +1.0, +2.1, +3.1, +4.1, +5.1, +7.1 detik. Buku lama dan kernel lama menunjukkan backoff eksponensial (1, 2, 4, 8 detik). Kernel baru mempercepat beberapa percobaan pertama. Ini contoh kenapa perilaku harus diverifikasi di versi yang dipakai, bukan dihafal.'
    source: https://docs.kernel.org/networking/ip-sysctl.html

  - id: b04
    tier: B
    type: text
    objective: states
    prompt: Server menunjukkan ratusan socket **CLOSE-WAIT** yang terus bertambah. Di mana bug-nya?
    answers: ['aplikasi di server tidak memanggil close() setelah peer menutup']
    patterns: ['(aplikasi|kode|program|app|server).*(tidak|lupa|belum|never|tak).*(close|menutup|tutup)', '(tidak|lupa|belum|tak) (memanggil )?close', 'leak|bocor']
    tests:
      pass: ['aplikasi server tidak menutup socket', 'lupa memanggil close()', 'connection leak di aplikasi']
      fail: ['firewall', 'klien tidak menutup', 'kernel kurang memori', 'tcp_fin_timeout terlalu besar']
    explanation: 'CLOSE-WAIT berarti peer sudah mengirim FIN, kernel sudah meng-ACK-nya, dan socket menunggu **aplikasi lokal** memanggil `close()`. Kernel tidak akan menutupnya sendiri. Di lab, server yang menyimpan socket tanpa close → `CLOSE-WAIT count: 5`. Penyebab umum: koneksi HTTP/DB yang tidak dikembalikan ke pool, atau exception sebelum close. Akhirnya muncul `Too many open files`.'
    source: https://man7.org/linux/man-pages/man7/tcp.7.html

  - id: b05
    tier: B
    type: text
    objective: states
    prompt: Untuk koneksi yang sama (server tertahan di CLOSE-WAIT), state apa yang terlihat di sisi **klien** yang sudah menutup?
    answers: ['FIN-WAIT-2']
    patterns: ['fin[-_ ]?wait[-_ ]?2']
    tests:
      pass: ['FIN_WAIT2', 'fin-wait-2']
      fail: ['time-wait', 'close-wait', 'established', 'fin-wait-1']
    explanation: 'Di lab: `client FIN-WAIT-2 count: 5`. Klien sudah mengirim FIN dan menerima ACK, lalu menunggu FIN dari server yang tidak pernah datang. Kernel melepas socket FIN-WAIT-2 yang sudah di-close setelah `tcp_fin_timeout` (60 detik), sedangkan CLOSE-WAIT di server tetap menumpuk.'
    source: https://www.rfc-editor.org/rfc/rfc9293

  - id: b06
    tier: B
    type: text
    objective: listen
    prompt: Server memanggil `listen(2)` tetapi **tidak pernah** `accept()`. Klien mencoba 8 koneksi. Berapa yang berhasil `connect()`?
    answers: ['3']
    tests:
      fail: ['2', '8', '0', '1']
    explanation: 'Di lab: `connected 3`, dan koneksi ke-4 sampai ke-8 `TimeoutError`. Handshake diselesaikan kernel, bukan aplikasi, lalu koneksi masuk antrean accept. Linux menerima sampai backlog + 1. Setelah antrean penuh, SYN baru dibuang diam-diam, sehingga klien mengalami **timeout**, bukan refused. Server yang lambat accept (event loop macet) terlihat seperti jaringan putus.'
    source: https://man7.org/linux/man-pages/man2/listen.2.html

  - id: b07
    tier: B
    type: text
    objective: listen
    prompt: '`ss -ltn` untuk socket itu menampilkan `LISTEN  3  2  0.0.0.0:8020`. Apa arti angka `3` (Recv-Q) dan `2` (Send-Q) pada socket LISTEN?'
    answers: ['Recv-Q = koneksi menunggu accept, Send-Q = batas backlog']
    patterns: ['(?=.*(antri|antre|queue|menunggu|belum di-?accept|accept))(?=.*(backlog|batas|maks|limit))']
    tests:
      pass: ['recv-q jumlah koneksi menunggu accept, send-q batas backlog', 'antrean accept 3, backlog maks 2']
      fail: ['byte belum dibaca', 'jumlah paket terkirim', 'retransmisi']
    explanation: 'Untuk socket LISTEN, maknanya berbeda dari socket biasa: Recv-Q = panjang antrean accept saat ini, Send-Q = backlog maksimum (dibatasi `net.core.somaxconn`, lab: 4096). Recv-Q yang mendekati Send-Q berarti aplikasi tidak sanggup accept secepat koneksi datang. Untuk socket ESTABLISHED, Recv-Q/Send-Q adalah byte yang belum dibaca/di-ACK.'
    source: https://man7.org/linux/man-pages/man8/ss.8.html

  - id: b08
    tier: B
    type: text
    objective: listen
    prompt: Counter `nstat` yang naik saat koneksi dibuang karena antrean accept penuh?
    answers: ['TcpExtListenOverflows']
    patterns: ['listenoverflows', 'listendrops']
    tests:
      pass: ['TcpExtListenOverflows', 'TcpExtListenDrops']
      fail: ['TcpRetransSegs', 'TcpExtTCPTimeouts', 'TcpActiveOpens']
    explanation: 'Di lab: `TcpExtListenOverflows 10`, `TcpExtListenDrops 10`, sesuai SYN yang dibuang (termasuk retransmisinya). Counter ini berlaku untuk seluruh host. Bila naik di server production, naikkan backlog aplikasi (mis. nginx `listen ... backlog=`, php-fpm `listen.backlog`) atau tambah kapasitas worker.'
    source: https://docs.kernel.org/networking/ip-sysctl.html

  - id: b09
    tier: B
    type: text
    objective: errors
    prompt: Server menutup socket yang masih punya data **belum dibaca** di buffer penerimaannya. Error apa yang diterima klien pada operasi berikutnya?
    answers: ['Connection reset by peer']
    patterns: ['(connection reset|reset by peer|econnreset|errno 104|rst)']
    tests:
      pass: ['ConnectionResetError [Errno 104] Connection reset by peer', 'econnreset']
      fail: ['connection refused', 'broken pipe tanpa reset', 'timed out', 'eof normal']
    explanation: 'Di lab: klien mengirim 100 byte, server `close()` tanpa membaca, lalu tcpdump menunjukkan `Flags [R.]` (bukan FIN) dan klien mendapat `[Errno 104] Connection reset by peer`. Kernel mengirim RST untuk memberi tahu bahwa data hilang. Di proxy/load balancer, "reset by peer" sering berarti upstream crash, timeout, atau menolak request di tengah jalan.'
    source: https://man7.org/linux/man-pages/man7/tcp.7.html

  - id: b10
    tier: B
    type: text
    objective: ports
    prompt: Klien membuka banyak koneksi ke `server:8010` dan akhirnya `connect()` gagal dengan `[Errno 99]`. Apa pesan errornya dan penyebabnya?
    answers: ['Address not available — port ephemeral habis']
    patterns: ['(address not available|cannot assign requested address|eaddrnotavail)', '(port|ephemeral).*(habis|penuh|exhaust)']
    tests:
      pass: ['Cannot assign requested address, port ephemeral habis', 'EADDRNOTAVAIL', 'port ephemeral habis']
      fail: ['connection refused', 'too many open files', 'host is unreachable']
    explanation: 'Di lab, dengan `--sysctl net.ipv4.ip_local_port_range="40000 40009"`: 9 koneksi ke `:8010` berhasil, lalu `OSError [Errno 99] Address not available` (C: "Cannot assign requested address"). Ini terjadi pada klien yang membuat koneksi pendek dengan sangat cepat ke satu tujuan (API, DB tanpa pool), karena setiap port tertahan 60 detik di TIME_WAIT.'
    source: https://docs.kernel.org/networking/ip-sysctl.html

  - id: b11
    tier: B
    type: text
    objective: keepalive
    prompt: Dengan default Linux (7200 + 75 × 9 detik), berapa lama kira-kira sampai koneksi ke peer yang **mati diam-diam** terdeteksi lewat keepalive?
    answers: ['sekitar 2 jam 11 menit (7875 detik)']
    patterns: ['(7875|2 jam 1[01]|2\.1 jam|131 menit|130 menit|lebih dari 2 jam|~ ?2 jam)']
    tests:
      pass: ['7875 detik', '2 jam 11 menit', 'lebih dari 2 jam']
      fail: ['75 detik', '9 menit', '60 detik', 'langsung']
    explanation: '7200 + 75 × 9 = 7875 detik. Di lab, dengan `TCP_KEEPIDLE=5`, `KEEPINTVL=2`, `KEEPCNT=3`, `ss -o` menunjukkan `timer:(keepalive,...)`, dan probe terlihat sebagai ACK kosong setiap 5 detik. Setelah peer di-DROP, socket hilang sebelum detik ke-14 (5 + 3×2 = 11 s). Pool koneksi DB sebaiknya memakai keepalive atau validasi koneksi yang lebih pendek dari timeout idle firewall/NAT.'
    source: https://man7.org/linux/man-pages/man7/tcp.7.html

  - id: b12
    tier: B
    type: text
    objective: udp
    prompt: Socket UDP yang **tidak di-connect** memakai `sendto()` ke port tertutup. ICMP port unreachable kembali. Apa yang dilihat aplikasi saat menunggu balasan?
    answers: ['hanya timeout, error ICMP tidak dilaporkan']
    patterns: ['(timeout|timed out|tidak ada (error|pesan)|hanya menunggu|tidak dilaporkan)']
    tests:
      pass: ['timeout saja', 'tidak ada error, hanya timed out']
      fail: ['connection refused', 'port unreachable error', 'reset']
    explanation: 'Di lab: tcpdump melihat `ICMP ... udp port 9001 unreachable`, tetapi socket unconnected hanya mendapat `TimeoutError`. Socket yang sama dengan `connect()` → `ConnectionRefusedError`. Menurut udp(7), error ICMP hanya dilaporkan ke socket yang di-connect. Perbedaan ini menjelaskan kenapa tool UDP berbeda-beda hasilnya.'
    source: https://man7.org/linux/man-pages/man7/udp.7.html

  - id: b13
    tier: B
    type: text
    objective: udp
    prompt: '`nc -zuv host 9002` ke port UDP yang di-**DROP** firewall. Apa yang dilaporkan nc?'
    answers: ['open (false positive)']
    patterns: ['(open|terbuka|false positive|positif palsu|salah)']
    tests:
      pass: ['open, padahal di-drop', 'false positive: open']
      fail: ['closed', 'refused', 'filtered']
    explanation: 'Di lab: `10.61.0.2 (10.61.0.2:9002) open`, rc=0, padahal tidak ada service dan paket di-DROP. Untuk port yang tertutup tanpa firewall (ICMP kembali) → rc=1. Bagi nc, "tidak ada jawaban" sama dengan "open". Uji UDP dengan protokol aslinya (`dig @host` untuk DNS, klien WireGuard, dst.) atau tcpdump di server.'
    source: https://man7.org/linux/man-pages/man7/udp.7.html

  - id: b14
    tier: B
    type: text
    objective: reliability
    prompt: Lab mengunduh 20 MB di LAN. 0% loss → 0.04 s. Dengan `tc netem loss 10%` di server → ? (pilih yang paling mendekati, dalam detik)
    answers: ['8', '8.26', 'sekitar 8 detik']
    patterns: ['^(~ ?)?(sekitar |kira-kira )?8([.,]\d+)?( ?(s|detik))?$']
    tests:
      pass: ['8.26 detik', '~8s']
      fail: ['0.04', '0.4', '80', '4']
    explanation: 'Di lab: 0% → 0.04 s, 2% → 0.04 s (dengan 243 segmen di-retransmit di run lain), dan **10% → 8.26 s**, sekitar 200× lebih lambat. Setiap kehilangan dianggap tanda kemacetan, sehingga congestion window dipotong, ditambah jeda retransmission timeout. Loss kecil masih tertutupi fast retransmit, sedangkan loss besar membuat throughput TCP runtuh. Latency 50 ms → 0.78 s.'
    source: https://man7.org/linux/man-pages/man8/tc-netem.8.html

  - id: b15
    tier: B
    type: text
    objective: ports
    prompt: Setelah klien kehabisan port ke `server:8010`, apakah klien yang sama masih bisa membuka koneksi baru ke `server:8000`? (ya/tidak)
    answers: ['ya', 'yes', 'bisa']
    tests:
      fail: ['tidak', 'no']
    explanation: 'Di lab: `to :8010 (9, Address not available)`, lalu `to :8000 after (7, ...)` dengan port lokal yang sama dipakai ulang. Kehabisan port terjadi **per tujuan** (IP:port), karena 4-tuple-nya berbeda. Mitigasi di skala besar: lebih banyak IP tujuan (beberapa backend/IP), lebih banyak IP sumber, dan yang terpenting, koneksi yang dipakai ulang (keep-alive/pool).'
    source: https://www.rfc-editor.org/rfc/rfc9293

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: states
    prompt: |
      Server API (Laravel worker yang memanggil layanan eksternal) mulai error `Too many open files` setelah beberapa
      jam berjalan, lalu normal lagi setelah di-restart. Tulis langkah diagnosis dan perbaikan (satu perintah/poin per baris).
    checklist:
      - id: count
        label: Menghitung socket per state (ss -tan | ringkasan)
        required: true
        patterns: ['ss\s+-\S*t\S*', 'netstat']
      - id: closewait
        label: Menemukan CLOSE-WAIT menumpuk
        required: true
        patterns: ['close[-_ ]?wait']
      - id: process
        label: Mengaitkan dengan proses (ss -p / lsof / /proc/PID/fd)
        patterns: ['ss\s+.*-\S*p', 'lsof', '/proc/\S+/fd']
      - id: fix-code
        label: Memperbaiki kode (close/pool/timeout) sebagai akar masalah
        required: true
        patterns: ['(close|tutup|menutup|pool|finally|timeout).*(koneksi|socket|client|response)?', 'leak|bocor']
      - id: ulimit
        label: Menaikkan batas fd hanya sebagai mitigasi sementara
        patterns: ['ulimit', 'limitnofile', 'nofile']
      - id: monitor
        label: Memantau jumlah CLOSE-WAIT / fd setelah perbaikan
        patterns: ['(pantau|monitor|alert|grafik|metrik)']
      - id: no-sysctl-magic
        label: Tidak "memperbaiki" dengan tcp_tw_recycle / fin_timeout
        forbid: true
        patterns: ['tcp_tw_recycle', 'tcp_fin_timeout\s*=?\s*\d']
    pass_score: 0.6
    solution: |
      ```bash
      ss -tan | awk 'NR>1{print $1}' | sort | uniq -c | sort -rn      # CLOSE-WAIT ribuan?
      ss -tanp state close-wait | head                                # proses mana, ke tujuan mana
      ls /proc/$(pgrep -f queue:work | head -1)/fd | wc -l            # jumlah fd proses itu
      cat /proc/$(pgrep -f queue:work | head -1)/limits | grep 'open files'
      ```
      Diagnosis: CLOSE-WAIT berarti layanan eksternal sudah menutup koneksi, tetapi aplikasi tidak pernah `close()`.
      Kernel tidak membersihkannya (lab: 5 koneksi → 5 CLOSE-WAIT yang tetap ada). Setiap socket memakai satu file
      descriptor sampai batas `nofile` tercapai.

      Perbaikan:
      - Akar masalah ada di kode: pastikan response/stream ditutup (`finally`/`try-with`), pakai satu HTTP client
        yang di-reuse dengan pool, dan set connect/read timeout.
      - Mitigasi sementara: `LimitNOFILE`/ulimit lebih tinggi, plus `--max-jobs`/`--max-time` agar worker berganti
        berkala.
      - Pantau `ss -tan state close-wait | wc -l` sebagai metrik. Nilainya harus tetap rendah.

      `tcp_tw_recycle` (sudah dihapus dari kernel) dan `tcp_fin_timeout` tidak menyentuh CLOSE-WAIT sama sekali.
    tests:
      pass:
        - |
          ss -tan | awk '{print $1}' | sort | uniq -c
          ribuan CLOSE-WAIT ke api eksternal
          ss -tanp state close-wait
          ls /proc/1234/fd | wc -l
          aplikasi tidak menutup koneksi, perbaiki dengan close di finally dan pakai pool
          ulimit nofile dinaikkan sementara
          pantau jumlah close-wait
        - |
          ss -tn state close-wait
          lsof -p 1234
          leak koneksi di kode, tambahkan timeout dan close
      fail:
        - |
          ss -tan
          close-wait banyak
          sysctl -w net.ipv4.tcp_fin_timeout=10
          perbaiki close koneksi
        - |
          restart worker tiap jam
          ulimit -n 100000
        - |
          ss -tan
          tcp_tw_recycle=1
    source: https://man7.org/linux/man-pages/man7/tcp.7.html

  - id: c02
    tier: C
    type: scenario
    objective: ports
    prompt: |
      Job batch mengirim ribuan request HTTP per detik ke satu API internal (`10.0.5.20:443`), satu koneksi baru per
      request. Setelah beberapa menit, request gagal dengan `Cannot assign requested address`. Tulis diagnosis dan
      perbaikannya (satu poin/perintah per baris), dari yang paling tepat.
    checklist:
      - id: timewait
        label: Melihat TIME-WAIT menumpuk di sisi klien
        required: true
        patterns: ['time[-_ ]?wait']
      - id: diagnose
        label: Menyimpulkan port ephemeral habis untuk satu tujuan
        required: true
        patterns: ['(ephemeral|port lokal|local port|ip_local_port_range).*', '(port).*(habis|exhaust|penuh)']
      - id: reuse
        label: Akar perbaikan — keep-alive / connection pooling
        required: true
        patterns: ['keep-?alive', 'pool', 'reuse koneksi', 'pakai ulang koneksi', 'persistent']
      - id: range
        label: Memperlebar ip_local_port_range
        patterns: ['ip_local_port_range']
      - id: twreuse
        label: tcp_tw_reuse=1 untuk koneksi keluar
        patterns: ['tcp_tw_reuse']
      - id: more-tuples
        label: Menambah IP tujuan/sumber (beberapa backend / IP)
        patterns: ['(beberapa|lebih banyak|tambah).*(ip|backend|tujuan)', 'ip sumber']
      - id: no-recycle
        label: Tidak memakai tcp_tw_recycle
        forbid: true
        patterns: ['tcp_tw_recycle']
    pass_score: 0.5
    solution: |
      ```bash
      ss -tan state time-wait dst 10.0.5.20 | wc -l     # mendekati ~28 ribu?
      sysctl net.ipv4.ip_local_port_range               # 32768 60999 → ~28k port
      ```
      Setiap koneksi pendek ke `10.0.5.20:443` menahan satu port lokal selama 60 detik di TIME_WAIT (klien yang
      menutup lebih dulu). Sekitar 28k port ÷ 60 s ≈ 470 koneksi baru/detik ke **satu** tujuan. Di atas itu muncul
      `EADDRNOTAVAIL` (lab: rentang 10 port → koneksi ke-10 `[Errno 99] Address not available`).

      Perbaikan, urut dari yang paling tepat:
      1. Pakai ulang koneksi: HTTP keep-alive dan connection pool di HTTP client (satu client untuk seluruh job).
         Bonus: tidak ada handshake TCP+TLS per request.
      2. `sysctl -w net.ipv4.tcp_tw_reuse=1`: izinkan port TIME_WAIT dipakai ulang untuk koneksi keluar baru
         (aman dengan TCP timestamp).
      3. `net.ipv4.ip_local_port_range = 10240 65535` memberi lebih banyak port.
      4. Sebar ke beberapa IP backend/IP sumber. Lab: port yang sama bisa dipakai ke tujuan lain.

      `tcp_tw_recycle` sudah dihapus sejak Linux 4.12 karena merusak koneksi di belakang NAT.
    tests:
      pass:
        - |
          ss -tan state time-wait | wc -l
          ribuan time-wait, port ephemeral habis ke satu tujuan
          pakai keep-alive dan connection pool di http client
          sysctl -w net.ipv4.tcp_tw_reuse=1
          perlebar ip_local_port_range 10240 65535
          tambah beberapa ip backend
        - |
          ss -tn state time-wait dst 10.0.5.20
          local port habis
          gunakan connection pool
      fail:
        - |
          ss -tan state time-wait
          port ephemeral habis
          sysctl net.ipv4.tcp_tw_recycle=1
          pakai pool
        - |
          naikkan ulimit
          restart job
        - |
          ss -tan state time-wait
          perlebar ip_local_port_range
    source: https://docs.kernel.org/networking/ip-sysctl.html

  - id: c03
    tier: C
    type: scenario
    objective: errors
    prompt: |
      Tiga tim melapor masalah koneksi ke database `10.0.9.10:5432` dengan error berbeda:
      tim A `Connection refused`, tim B `Connection timed out`, tim C `No route to host`.
      Untuk masing-masing, tulis arti error itu dan apa yang pertama kali dicek (satu poin per baris).
    checklist:
      - id: refused
        label: Refused = host hidup, tidak ada yang listen (atau REJECT tcp-reset) → cek service/listen address
        required: true
        patterns: ['refused.*(listen|service|jalan|port|127\.0\.0\.1|0\.0\.0\.0|ss -)', '(listen|service).*refused']
      - id: timeout
        label: Timeout = paket dibuang (DROP/security group/host mati/route) → cek firewall
        required: true
        patterns: ['time(d)? ?out.*(drop|firewall|security group|sg|filter|dibuang|mati)', '(drop|firewall|security group|dibuang).*time(d)? ?out']
      - id: noroute
        label: No route = tidak ada route ATAU REJECT icmp-host-prohibited → cek routing dan firewall
        required: true
        patterns: ['no route.*(route|routing|ip route|icmp-host-prohibited|reject|firewall)', '(ip route|routing|host-prohibited).*no route']
      - id: tools
        label: Memakai alat yang tepat (ss -ltn, nc -zv, tcpdump, ip route get)
        patterns: ['ss\s+-\S*l', 'nc\s+-zv', 'tcpdump', 'ip\s+route\s+get']
      - id: no-disable
        label: Tidak mematikan firewall seluruhnya
        forbid: true
        patterns: ['ufw\s+disable', 'iptables\s+-F', 'systemctl\s+stop\s+firewalld']
    pass_score: 0.6
    solution: |
      ```text
      A "Connection refused" → host terjangkau, membalas RST: tidak ada proses listen di 5432, atau listen hanya di
          127.0.0.1 (listen_addresses Postgres), atau firewall REJECT tcp-reset/port-unreachable.
          Cek di server: ss -ltn 'sport = :5432'; dari klien: nc -zv 10.0.9.10 5432
      B "Connection timed out" → SYN tidak pernah dijawab: firewall DROP, security group, host mati, atau route salah.
          Cek: tcpdump -nn -i any port 5432 di server (SYN sampai?), aturan firewall/security group
      C "No route to host" → tidak ada route ke host (atau ARP gagal di subnet yang sama) ATAU firewall REJECT
          icmp-host-prohibited (default firewalld). Cek: ip route get 10.0.9.10, lalu firewall server
      ```
      Ketiganya direproduksi di lab:
      - Port tertutup → `[R.]` → refused.
      - `DROP` → SYN diulang lalu `TimeoutError`.
      - `REJECT --reject-with icmp-host-prohibited` → `[Errno 113] Host is unreachable`.
      - `REJECT --reject-with tcp-reset` dan `icmp-port-unreachable` → refused.

      Pesan error menentukan lapisan mana yang diperiksa lebih dulu. Mematikan firewall bukan cara diagnosis.
    tests:
      pass:
        - |
          A refused: host hidup tapi tidak ada yang listen, cek ss -ltn di server dan listen_addresses
          B timed out: paket di-drop firewall atau security group, cek tcpdump di server
          C no route to host: cek ip route get dan firewall icmp-host-prohibited
        - |
          refused karena service tidak listen di 0.0.0.0
          timeout karena firewall drop
          no route: routing salah atau reject icmp-host-prohibited
      fail:
        - |
          refused karena firewall
          timeout karena dns
          no route karena password salah
        - |
          ufw disable
          refused: service tidak listen
          timeout: firewall drop
          no route: cek ip route
        - |
          restart database
    source: https://man7.org/linux/man-pages/man7/tcp.7.html

---

# TCP & UDP

Hampir semua error jaringan yang dilihat aplikasi (refused, timeout, reset, "address not available") adalah TCP yang
sedang bercerita tentang apa yang terjadi. Modul ini membedah TCP dan UDP di paket nyata dan memetakan setiap
perilaku ke error yang muncul di aplikasi.

> [!NOTE]
> Lab: dua container `python:3.12-alpine` (server dan klien) di satu network Docker. Python dipakai agar perilaku
> socket bisa dikendalikan persis: tidak accept, tidak close, backlog kecil. Paket ditangkap tcpdump, kondisi
> jaringan disimulasikan `tc netem`, dan firewall memakai iptables. Kernel: 6.18 (WSL2).

## 1. Siklus hidup koneksi TCP

```text
cli.46730 > srv.8000: Flags [S]        ← SYN
srv.8000 > cli.46730: Flags [S.]       ← SYN+ACK
cli.46730 > srv.8000: Flags [.]        ← ACK          (handshake selesai: 1 RTT)
cli.46730 > srv.8000: Flags [P.] 1:5   ← data "ping"
srv.8000 > cli.46730: Flags [P.] 1:5   ← data "pong"
srv.8000 > cli.46730: Flags [F.]       ← server menutup lebih dulu
cli.46730 > srv.8000: Flags [F.]
srv.8000 > cli.46730: Flags [.]        → server masuk TIME_WAIT (60 s)
```

```mermaid
stateDiagram-v2
    [*] --> ESTABLISHED: handshake
    ESTABLISHED --> FIN_WAIT_1: close() duluan
    FIN_WAIT_1 --> FIN_WAIT_2: ACK
    FIN_WAIT_2 --> TIME_WAIT: FIN peer
    TIME_WAIT --> [*]: 60 s
    ESTABLISHED --> CLOSE_WAIT: FIN dari peer
    CLOSE_WAIT --> LAST_ACK: aplikasi close()
    LAST_ACK --> [*]
```

| State | Artinya | Lab | Normal? |
|---|---|---|---|
| TIME_WAIT | Penutup pertama menunggu 60 s | Ada di server, karena server yang close duluan | Ya, kecuali menghabiskan port |
| CLOSE_WAIT | Peer sudah tutup, **aplikasi lokal belum close()** | 5 dari 5 koneksi | **Tidak**: bug aplikasi |
| FIN-WAIT-2 | Menunggu FIN peer | 5 di klien (pasangan CLOSE_WAIT) | Sementara |

## 2. Refused, timeout, reset, unreachable

| Error klien (lab) | Penyebab di jaringan | Arti |
|---|---|---|
| `Connection refused` (111) | RST, atau REJECT `tcp-reset`/`icmp-port-unreachable` | Host hidup, port tertutup/ditolak |
| `timed out` | DROP. SYN diulang di +1, +2, +3, +4, +5, +7 s | Paket dibuang di jalan |
| `Host is unreachable` (113) | REJECT `icmp-host-prohibited`, atau tidak ada route | Routing **atau** firewall |
| `Connection reset by peer` (104) | RST di tengah koneksi (close dengan data belum dibaca) | Peer membatalkan |
| `Address not available` (99) | Port ephemeral habis | Masalah di sisi **klien** |

> [!TIP]
> Retransmisi SYN di kernel lab bukan 1, 2, 4, 8 detik seperti di buku. Empat percobaan pertama berjarak ~1 detik
> (`tcp_syn_linear_timeouts=4`), baru setelah itu berlipat. Selalu set **connect timeout** di aplikasi, karena
> default kernel (`tcp_syn_retries=6`) bisa menahan `connect()` lebih dari satu menit.

## 3. Backlog listen

```text
server: listen(2), tidak pernah accept()
klien : 8 koneksi → 3 berhasil, 5 TimeoutError
ss -ltn : LISTEN  Recv-Q 3  Send-Q 2  0.0.0.0:8020
nstat   : TcpExtListenOverflows 10
```

Antrean penuh menghasilkan SYN yang **dibuang**, bukan ditolak, sehingga klien melihat timeout. Recv-Q yang
mendekati Send-Q di socket LISTEN berarti aplikasi kewalahan.

## 4. Port ephemeral

- Rentang default `32768–60999` (~28 ribu).
- Kehabisan terjadi **per tujuan**. Di lab, rentang 10 port → 9 koneksi ke `:8010`, lalu `[Errno 99]`. Port yang
  sama masih bisa dipakai ke `:8000`.
- Dengan koneksi pendek, setiap port tertahan 60 s di TIME_WAIT, sehingga batasnya sekitar 470 koneksi baru per
  detik ke satu IP:port.

Perbaikan terbaik adalah memakai ulang koneksi (keep-alive/pool). `tcp_tw_reuse=1` dan rentang port yang lebih lebar
hanya membantu.

## 5. Keepalive

| Sysctl | Default (lab) | Per socket |
|---|---|---|
| `tcp_keepalive_time` | 7200 s | `TCP_KEEPIDLE` |
| `tcp_keepalive_intvl` | 75 s | `TCP_KEEPINTVL` |
| `tcp_keepalive_probes` | 9 | `TCP_KEEPCNT` |

Keepalive tidak aktif kecuali aplikasi menyalakan `SO_KEEPALIVE`. Di lab, dengan 5/2/3 → probe ACK kosong setiap 5
detik, dan peer yang di-DROP terdeteksi kurang dari 14 detik. Dengan default, butuh ~2 jam 11 menit, jauh lebih lama
dari timeout idle NAT/firewall/load balancer.

## 6. Packet loss dan latency

| Kondisi (`tc netem` di server) | Unduh 20 MB |
|---|---|
| Normal | 0.04 s |
| loss 2% | 0.04 s (243 segmen di-retransmit) |
| **loss 10%** | **8.26 s** |
| delay 50 ms | 0.78 s |

TCP menganggap kehilangan sebagai kemacetan dan memperlambat diri. Sedikit loss tertutupi, tetapi banyak loss
membuat throughput runtuh. Saat transfer lambat tanpa error, cek retransmisi (`nstat TcpRetransSegs`,
`ss -ti` → `retrans:`).

## 7. UDP

| Uji (lab) | Hasil |
|---|---|
| Kirim ke port terbuka | Dua paket saja, tanpa handshake |
| Kirim ke port tertutup | ICMP `port unreachable` |
| Socket **connected** ke port tertutup | `ConnectionRefusedError` |
| Socket **unconnected** ke port tertutup | Hanya timeout (error ICMP tidak dilaporkan) |
| Port di-DROP | Timeout |
| `nc -zuv` ke port di-DROP | **`open`** (rc=0), positif palsu |

Uji UDP dengan protokol aslinya (`dig`, klien WireGuard, dst.) atau lihat tcpdump di server. Jangan mengandalkan
`nc -zu`.

## 8. Ringkasan

| Gejala | Cek pertama |
|---|---|
| Refused | `ss -ltn` di server: listen? di alamat mana? |
| Timeout | tcpdump di server: SYN sampai? lalu firewall/SG |
| No route to host | `ip route get`, lalu firewall `icmp-host-prohibited` |
| Reset by peer | Log upstream: crash, timeout, menolak? |
| CLOSE_WAIT menumpuk | Kode aplikasi: close/pool/timeout |
| Address not available | TIME_WAIT di klien → keep-alive/pool |
| Lambat tanpa error | Retransmisi, loss, RTT |
