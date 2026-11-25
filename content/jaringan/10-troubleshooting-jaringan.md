---
format: 1
id: net-troubleshooting
course:
  slug: jaringan
  title: Jaringan
  description: Konsep jaringan untuk DevOps — TCP/IP, subnetting, routing, TCP/UDP, DNS, HTTP, TLS, proxy, firewall, VPN, dan troubleshooting.
title: Troubleshooting Jaringan Metodologis
summary: Cara mencari masalah jaringan secara sistematis — lapis demi lapis dan bisect jalur — dengan traceroute/tracepath/mtr (termasuk hop `* * *` dan loss palsu karena ICMP rate limit), tcpdump di beberapa titik (file pcap, filter flag TCP, alamat MAC), ss/nstat untuk RTT dan retransmisi, latency vs throughput dengan iperf3 dan tc netem, serta memecah waktu request dengan curl -w.
order: 10
version: 1
status: verified
updated: '2026-11-25'
tested_on:
  - Docker Desktop (Windows), kernel 6.18 — client → router → server (alpine:3.20, iproute2-6.9.0)
  - tcpdump 4.99.4, mtr 0.95, iperf 3.17.1, tc netem, BusyBox traceroute, iputils tracepath
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: method
    text: Menerapkan metode lapis demi lapis dan bisect untuk melokalisasi masalah
  - id: path
    text: Membaca hasil traceroute, tracepath, dan mtr dengan benar
  - id: capture
    text: Menangkap dan memfilter paket dengan tcpdump di beberapa titik
  - id: socket
    text: Membaca state dan info internal TCP dengan ss dan nstat
  - id: perf
    text: Membedakan latency dan throughput, mengukur dengan iperf3, mensimulasikan dengan tc netem
  - id: timing
    text: Memecah waktu request (DNS, connect, TTFB) dengan curl -w
sources:
  - title: traceroute(8)
    url: https://man7.org/linux/man-pages/man8/traceroute.8.html
  - title: tracepath(8)
    url: https://man7.org/linux/man-pages/man8/tracepath.8.html
  - title: mtr — dokumentasi dan man page
    url: https://github.com/traviscross/mtr
  - title: icmp(7) — icmp_ratelimit, icmp_ratemask
    url: https://man7.org/linux/man-pages/man7/icmp.7.html
  - title: tcpdump(1)
    url: https://www.tcpdump.org/manpages/tcpdump.1.html
  - title: pcap-filter(7) — tcp[tcpflags], tcp-rst
    url: https://www.tcpdump.org/manpages/pcap-filter.7.html
  - title: ss(8)
    url: https://man7.org/linux/man-pages/man8/ss.8.html
  - title: nstat(8)
    url: https://man7.org/linux/man-pages/man8/nstat.8.html
  - title: tc-netem(8)
    url: https://man7.org/linux/man-pages/man8/tc-netem.8.html
  - title: iperf3 — invoking
    url: https://software.es.net/iperf/invoking.html
  - title: curl — -w/--write-out dan --resolve
    url: https://curl.se/docs/manpage.html#-w
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: command
    objective: path
    prompt: Jalankan `traceroute` ke `10.80.2.20` **tanpa** reverse DNS lookup (tampilkan IP saja).
    answers: ['traceroute -n 10.80.2.20']
    patterns: ['^(?=.*\s-[a-zA-Z]*n)traceroute( -[a-zA-Z]+( \d+)?)* 10\.80\.2\.20$']
    tests:
      pass: ['traceroute -n -w 1 -q 1 10.80.2.20', 'traceroute -In 10.80.2.20']
      fail: ['traceroute 10.80.2.20', 'tracert -d 10.80.2.20', 'ping -n 10.80.2.20']
    explanation: 'Di lab → ` 1  10.80.1.254  0.005 ms` / ` 2  10.80.2.20  0.001 ms`. Tanpa `-n`, setiap hop di-lookup PTR-nya, dan bila DNS lambat atau mati traceroute terlihat "macet" padahal jalurnya sehat. Saat troubleshooting, matikan semua variabel yang tidak perlu (DNS termasuk) agar hasilnya hanya tentang jalur.'
    source: https://man7.org/linux/man-pages/man8/traceroute.8.html

  - id: a02
    tier: A
    type: command
    objective: path
    prompt: Jalankan `mtr` ke `10.80.2.20` dalam **mode laporan** (non-interaktif) dengan **10 siklus** probe.
    answers: ['mtr -n -r -c 10 10.80.2.20']
    patterns: ['^(?=.*\s(-[a-zA-Z]*r[a-zA-Z]*|--report)(\s|$))(?=.*\s(-c ?10|--report-cycles[= ]10)(\s|$))mtr( (-[a-zA-Z]+|-c ?\d+|--[a-z-]+([= ]\d+)?))* 10\.80\.2\.20$']
    tests:
      pass: ['mtr -r -c 10 10.80.2.20', 'mtr --report --report-cycles 10 10.80.2.20', 'mtr -rn -c 10 10.80.2.20']
      fail: ['mtr 10.80.2.20', 'mtr -c 10 10.80.2.20', 'mtr -r 10.80.2.20', 'traceroute -r -c 10 10.80.2.20']
    explanation: 'Di lab → kolom `Loss% Snt Last Avg Best Wrst StDev` per hop, misalnya `2.|-- 10.80.2.20  0.0%  10  0.1 ...`. mtr = traceroute + ping berulang, jadi ia memperlihatkan loss dan jitter per hop dari waktu ke waktu, bukan satu sampel. Mode `-r` cocok ditempel ke tiket atau dikirim ke ISP. Jalankan dari **kedua arah** bila bisa, karena jalur pergi dan pulang bisa berbeda.'
    source: https://github.com/traviscross/mtr

  - id: a03
    tier: A
    type: text
    objective: path
    prompt: traceroute mengirim probe dengan TTL 1, 2, 3, ... Pesan ICMP **jenis apa** yang dikirim balik oleh router saat TTL probe habis?
    answers: ['Time Exceeded']
    patterns: ['time[- ]?exceeded', 'ttl exceeded', 'type 11', 'ttl habis']
    tests:
      fail: ['Echo Reply', 'Destination Unreachable', 'Port Unreachable', 'Redirect']
    explanation: 'Setiap router mengurangi TTL. Saat TTL menjadi 0, router membuang paket dan mengirim **ICMP Time Exceeded** (type 11), sehingga IP router itu terlihat. Di lab, router yang membuang Time Exceeded keluar (`iptables -A OUTPUT -p icmp --icmp-type time-exceeded -j DROP`) → ` 1  *  *  *`, sedangkan hop 2 (tujuan) tetap terjawab. Artinya `* * *` di tengah **bukan** bukti paket dibuang. Itu hanya berarti router tersebut tidak mau menjawab.'
    source: https://man7.org/linux/man-pages/man8/traceroute.8.html

  - id: a04
    tier: A
    type: text
    objective: path
    prompt: mtr menunjukkan hop tengah dengan `Loss% 85%`, tetapi hop terakhir (tujuan) `0.0%`. Apa penyebab paling umum?
    answers: ['ICMP rate limiting di router']
    patterns: ['rate[- ]?limit', 'pembatas(an)? (laju|rate)', 'dibatasi']
    tests:
      fail: ['kabel rusak', 'MTU terlalu kecil', 'DNS lambat', 'firewall di tujuan']
    explanation: 'Di lab, tanpa firewall apa pun, `mtr -c 50 -i 0.1` → hop 1 `80.0%`, tujuan `0.0%`. Dengan interval 1 s → hop 1 `0.0%`. Router (Linux default `net.ipv4.icmp_ratelimit = 1000` ms) membatasi pembuatan ICMP Time Exceeded, tetapi tetap meneruskan paket dengan normal. **Loss yang nyata selalu berlanjut sampai hop terakhir.** Loss yang hanya muncul di satu hop lalu hilang adalah artefak.'
    source: https://man7.org/linux/man-pages/man7/icmp.7.html

  - id: a05
    tier: A
    type: command
    objective: path
    prompt: Lacak jalur ke `10.80.2.20` sekaligus temukan **path MTU**-nya, tanpa perlu root.
    answers: ['tracepath -n 10.80.2.20']
    patterns: ['^tracepath( -n| -[bl] ?\d+)* 10\.80\.2\.20(/\d+)?$']
    tests:
      pass: ['tracepath 10.80.2.20']
      fail: ['traceroute -n 10.80.2.20', 'ping -M do -s 1472 10.80.2.20', 'mtr -n 10.80.2.20']
    explanation: 'Di lab → ` 1?: [LOCALHOST]  pmtu 1500` … ` 2:  10.80.2.20  0.033ms reached` / `Resume: pmtu 1500 hops 2 back 2`. tracepath memakai UDP dengan flag DF, sehingga hop yang MTU-nya lebih kecil muncul sebagai `pmtu` baru. Ini alat pertama untuk gejala "request kecil lancar, upload besar macet" (modul 02). `back 2` adalah estimasi jumlah hop jalur balik dari TTL balasan.'
    source: https://man7.org/linux/man-pages/man8/tracepath.8.html

  - id: a06
    tier: A
    type: command
    objective: capture
    prompt: Tangkap semua paket dari/ke host `10.80.2.20` di interface `eth0`, lalu **simpan ke file** `/tmp/c.pcap` (bukan ditampilkan).
    answers: ['tcpdump -nn -i eth0 -w /tmp/c.pcap host 10.80.2.20']
    patterns: ['^(?=.*\s-i ?eth0(\s|$))(?=.*\s-w ?/tmp/c\.pcap(\s|$))tcpdump( (-[a-zA-Z]+|-[iwc] ?\S+|-s ?\d+))* (ip )?host 10\.80\.2\.20$']
    tests:
      pass: ['tcpdump -i eth0 -w /tmp/c.pcap host 10.80.2.20', "tcpdump -i eth0 -nn -w /tmp/c.pcap 'host 10.80.2.20'"]
      fail: ['tcpdump -nn -r /tmp/c.pcap host 10.80.2.20', 'tcpdump -i eth0 host 10.80.2.20 > /tmp/c.pcap', 'tcpdump -i eth0 -w /tmp/c.pcap port 8080']
    explanation: '`-w` menulis paket mentah (format pcap), yang bisa dibaca ulang dengan filter berbeda atau dibuka di Wireshark. Redirect `>` hanya menyimpan teks ringkasan dan kehilangan detailnya. Di lab, satu file berisi 21 paket dari tiga percobaan (port 8080 sukses, 7777 ditolak, 9999 di-DROP), lalu dibedah dengan berbagai filter tanpa mengulang kejadiannya. Di production, tambahkan `-c N` atau filter sempit agar file tidak membengkak.'
    source: https://www.tcpdump.org/manpages/tcpdump.1.html

  - id: a07
    tier: A
    type: command
    objective: capture
    prompt: Baca file `/tmp/c.pcap` dan tampilkan hanya paket **port 8080**, tanpa resolusi nama/port.
    answers: ['tcpdump -nn -r /tmp/c.pcap port 8080']
    patterns: ['^(?=.*\s-r ?/tmp/c\.pcap(\s|$))tcpdump( (-[a-zA-Z]+|-r ?\S+))* (tcp )?port 8080$']
    tests:
      pass: ['tcpdump -r /tmp/c.pcap tcp port 8080', "tcpdump -nn -A -r /tmp/c.pcap 'port 8080'"]
      fail: ['tcpdump -nn -w /tmp/c.pcap port 8080', 'tcpdump -nn -r /tmp/c.pcap host 8080', 'grep 8080 /tmp/c.pcap']
    explanation: '`-r` membaca file dan menerapkan filter BPF yang sama seperti saat capture. `-nn` mematikan resolusi nama host **dan** nama port (agar `8080` tidak menjadi `http-alt`). Di lab, ditambah `-A` → isi request terlihat: `GET / HTTP/1.1` / `Host: 10.80.2.20:8080` / `User-Agent: curl/8.14.1`. HTTP polos terbaca, sedangkan HTTPS hanya memperlihatkan handshake TLS.'
    source: https://www.tcpdump.org/manpages/tcpdump.1.html

  - id: a08
    tier: A
    type: text
    objective: capture
    prompt: Di output tcpdump, balasan `Flags [R.]` dari server adalah segmen TCP jenis apa?
    answers: ['RST']
    patterns: ['^rst', 'reset']
    tests:
      fail: ['SYN', 'FIN', 'ACK', 'PSH']
    explanation: 'Di lab, `curl` ke port 7777 (tidak ada yang listen) → `Failed to connect ... after 0 ms`, dan pcap-nya: `10.80.2.20.7777 > 10.80.1.10.55260: Flags [R.], seq 0, ack ...`. Notasinya: `S` = SYN, `S.` = SYN-ACK, `.` = ACK, `P` = PSH, `F` = FIN, `R` = RST. RST yang **cepat** berarti host hidup dan terjangkau tetapi port tertutup (atau firewall REJECT). **Diam** (timeout) berarti paket dibuang di suatu tempat.'
    source: https://www.tcpdump.org/manpages/tcpdump.1.html

  - id: a09
    tier: A
    type: command
    objective: socket
    prompt: Tampilkan socket TCP (numerik) **beserta info internal TCP** (rtt, cwnd, retrans) untuk koneksi ke `10.80.2.20`.
    answers: ['ss -tin dst 10.80.2.20']
    patterns: ['^(?=ss( -[a-zA-Z]+)* -[a-zA-Z]*t)(?=ss( -[a-zA-Z]+)* -[a-zA-Z]*i)ss( -[a-zA-Z]+)+ dst 10\.80\.2\.20(:\d+)?$']
    tests:
      pass: ['ss -ti dst 10.80.2.20', 'ss -t -i -n dst 10.80.2.20', 'ss -tni dst 10.80.2.20:5201']
      fail: ['ss -tln dst 10.80.2.20', 'ss -s', 'netstat -tin dst 10.80.2.20']
    explanation: 'Di lab, saat iperf3 berjalan dengan 1% loss → `cubic ... rtt:52.405/3.503 mss:1448 cwnd:12 ssthresh:11 ... bytes_retrans:30408 ... retrans:0/21 ... notsent:482184`. Dari satu baris ini terlihat RTT nyata yang diukur TCP, ukuran congestion window (kecil = TCP sedang mengerem karena loss), dan jumlah retransmisi. Info ini tidak bisa diperoleh dari ping.'
    source: https://man7.org/linux/man-pages/man8/ss.8.html

  - id: a10
    tier: A
    type: text
    objective: perf
    prompt: Tool client–server (satu sisi `-s`, sisi lain `-c`) untuk mengukur **throughput** TCP/UDP antar dua host?
    answers: ['iperf3']
    patterns: ['^iperf3?$']
    tests:
      fail: ['ping', 'mtr', 'traceroute', 'curl']
    explanation: 'Di lab: `iperf3 -s -D` di server, `iperf3 -c 10.80.2.20 -t 5` di client → `263 Mbits/sec ... sender` / `258 Mbits/sec ... receiver` (RTT 100 ms). ping dan mtr hanya mengukur **latency** dan loss, tidak bisa menjawab "seberapa banyak data per detik". Kolom `Retr` di baris sender adalah jumlah retransmisi TCP selama tes.'
    source: https://software.es.net/iperf/invoking.html

  - id: a11
    tier: A
    type: command
    objective: perf
    prompt: Jalankan iperf3 sebagai **client** ke server `10.80.2.20` selama **5 detik**.
    answers: ['iperf3 -c 10.80.2.20 -t 5']
    patterns: ['^(?=.*\s-c ?10\.80\.2\.20(\s|$))(?=.*\s-t ?5(\s|$))iperf3( (-[a-zA-Z]+|-[ctfP] ?\S+))+$']
    tests:
      pass: ['iperf3 -t 5 -c 10.80.2.20 -f m']
      fail: ['iperf3 -s -t 5', 'iperf3 -c 10.80.2.20', 'iperf -c 10.80.2.20 -t 5', 'iperf3 -c 10.80.2.20 -t 50']
    explanation: 'Default-nya client mengirim ke server (upload). `-R` membalik arah (server mengirim) karena masalah sering hanya terjadi di satu arah. `-u -b 50M` menguji UDP pada laju tertentu dan melaporkan jitter serta loss (lab: `50.0 Mbits/sec 0.034 ms 0/12946 (0%)`). Port default-nya 5201/TCP, jadi buka di firewall saat tes antar-server.'
    source: https://software.es.net/iperf/invoking.html

  - id: a12
    tier: A
    type: command
    objective: perf
    prompt: Simulasikan **delay 50 ms** pada trafik keluar interface `eth1` dengan tc netem.
    answers: ['tc qdisc add dev eth1 root netem delay 50ms']
    patterns: ['^tc qdisc (add|replace) dev eth1 root netem delay 50 ?ms$']
    tests:
      pass: ['tc qdisc replace dev eth1 root netem delay 50ms']
      fail: ['tc qdisc add dev eth1 root netem loss 50%', 'tc qdisc del dev eth1 root', 'tc qdisc add dev eth1 root tbf delay 50ms']
    explanation: 'netem hanya bekerja pada trafik **keluar** (egress). Di lab, router mendapat `delay 50ms` di eth0 dan eth1 (kedua arah) → `ping` `rtt avg 100.891 ms`. Dengan netem, kondisi jaringan "jelek" (WAN, seluler, lintas benua) bisa ditiru di lab untuk menguji timeout, retry, dan throughput aplikasi sebelum production. Hapus dengan `tc qdisc del dev eth1 root`.'
    source: https://man7.org/linux/man-pages/man8/tc-netem.8.html

  - id: a13
    tier: A
    type: text
    objective: timing
    prompt: Variabel `curl -w` yang berisi waktu (detik) sejak awal sampai **TCP connect selesai**?
    answers: ['time_connect']
    patterns: ['^%?\{?time_connect\}?$']
    tests:
      fail: ['time_namelookup', 'time_starttransfer', 'time_total', 'time_appconnect']
    explanation: 'Semua nilainya kumulatif sejak awal: `time_namelookup` (DNS) ≤ `time_connect` (TCP) ≤ `time_appconnect` (TLS) ≤ `time_starttransfer` (byte pertama/TTFB) ≤ `time_total`. Di lab dengan RTT 100 ms: `connect=0.101188 ttfb=0.203306`. Connect memakan 1 RTT (SYN → SYN-ACK), lalu request–response memakan 1 RTT lagi. Selisih antar-variabel menunjukkan fase mana yang lambat.'
    source: https://curl.se/docs/manpage.html#-w

  - id: a14
    tier: A
    type: text
    objective: method
    prompt: Teknik mencari lokasi masalah dengan **membagi jalur jadi dua**, mengecek titik tengah, lalu mengulang di separuh yang bermasalah, disebut apa?
    answers: ['bisect']
    patterns: ['bisect', 'bisection', 'divide and conquer', 'belah dua', 'binary search', 'biseksi']
    tests:
      fail: ['top-down', 'restart', 'trial and error', 'brute force']
    explanation: 'Di lab, jalur client → router(eth0 → eth1) → server di-capture di beberapa titik sekaligus. SYN terlihat di router `eth0` tetapi **tidak** di `eth1` maupun server, sehingga masalahnya pasti di dalam router, dan `iptables -L FORWARD -v -n` → `3  180 DROP ... tcp dpt:8080`. Tanpa bisect, orang menebak-nebak (restart app, ganti DNS). Dengan bisect, setiap pengecekan memotong separuh kemungkinan.'
    source: https://www.tcpdump.org/manpages/tcpdump.1.html

  - id: a15
    tier: A
    type: text
    objective: method
    prompt: '`ping 10.80.2.20` berhasil, tetapi `curl http://app.lab:8080/` gagal dengan "Could not resolve host". Lapisan/komponen mana yang pertama dicek?'
    answers: ['DNS']
    patterns: ['dns', 'resolv', 'name ?resolution', 'resolusi nama']
    tests:
      fail: ['TCP', 'TLS', 'routing', 'ARP']
    explanation: 'Metode lapis demi lapis: setiap jawaban mengeliminasi lapisan di bawahnya. Ping ke IP berhasil berarti L3 (routing) sehat. "Could not resolve host" terjadi **sebelum** satu paket pun dikirim ke tujuan, jadi masalahnya ada di resolusi nama (`/etc/resolv.conf`, resolver, record). Cek dengan `dig app.lab` atau `getent hosts app.lab`, dan lewati DNS dengan `curl --resolve` untuk memastikan aplikasinya sendiri sehat.'
    source: https://curl.se/docs/manpage.html#--resolve

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: path
    prompt: traceroute default di Linux mengirim probe memakai protokol apa, sehingga hasilnya `* * *` sampai akhir bila tujuan membuang protokol itu?
    answers: ['UDP']
    patterns: ['^udp']
    tests:
      fail: ['ICMP', 'TCP', 'SCTP']
    explanation: 'traceroute default mengirim UDP ke port tinggi (mulai 33434), dan tujuan menjawab dengan ICMP Port Unreachable. Di lab, server `iptables -A INPUT -p udp -j DROP` → ` 2  *`, ` 3  *` … sampai max hop, seolah tujuan tidak terjangkau. Dengan `traceroute -I` (ICMP Echo) → ` 2  10.80.2.20  0.005 ms`. Windows `tracert` memakai ICMP, sehingga hasil dari laptop Windows dan server Linux bisa berbeda.'
    source: https://man7.org/linux/man-pages/man8/traceroute.8.html

  - id: b02
    tier: B
    type: command
    objective: path
    prompt: Jalankan traceroute ke `10.80.2.20` memakai probe **ICMP Echo**, bukan UDP.
    answers: ['traceroute -I 10.80.2.20']
    patterns: ['^(?=.*\s-[a-zA-Z]*I)traceroute( -[a-zA-Z]+( \d+)?)* 10\.80\.2\.20$']
    tests:
      pass: ['traceroute -I -n 10.80.2.20', 'traceroute -nI -w 1 10.80.2.20']
      fail: ['traceroute -i 10.80.2.20', 'traceroute -U 10.80.2.20', 'ping -I 10.80.2.20']
    explanation: '`-I` (huruf besar) = ICMP Echo, sedangkan `-i` (kecil) = pilih interface. Versi traceroute penuh (paket `traceroute`) juga punya `-T` (TCP SYN, butuh root) yang paling mirip trafik aplikasi dan biasanya lolos firewall yang hanya membuka port 443. Pilih protokol probe yang **sama** dengan yang diizinkan firewall di jalur. Kalau tidak, yang terukur adalah kebijakan firewall, bukan jalurnya.'
    source: https://man7.org/linux/man-pages/man8/traceroute.8.html

  - id: b03
    tier: B
    type: command
    objective: path
    prompt: Jalankan mtr ke `10.80.2.20` dengan probe **TCP** ke port **8080** (meniru trafik aplikasi).
    answers: ['mtr -T -P 8080 10.80.2.20']
    patterns: ['^(?=.*\s(-[a-zA-Z]*T[a-zA-Z]*|--tcp)(\s|$))(?=.*\s(-P ?8080|--port[= ]8080)(\s|$))mtr( (-[a-zA-Z]+|-[Pc] ?\d+|--[a-z-]+([= ]\d+)?))* 10\.80\.2\.20$']
    tests:
      pass: ['mtr -n -r -c 5 -T -P 8080 10.80.2.20', 'mtr --tcp --port 8080 10.80.2.20']
      fail: ['mtr -u -P 8080 10.80.2.20', 'mtr -T 10.80.2.20', 'traceroute -T -p 8080 10.80.2.20', 'mtr -T -p 8080 10.80.2.20']
    explanation: 'Di lab → `2.|-- 10.80.2.20  0.0%  5  0.4 ...`. Bandingkan dengan `mtr -u` saat server membuang UDP → `2.|-- ???  100.0`. Jalurnya sama, hasilnya bertolak belakang, hanya karena protokol probe. Dalam mtr, `-P` (besar) adalah port, `-p` (kecil) adalah mode output split. Gunakan `-T -P 443` untuk menguji jalur ke web server di balik firewall yang hanya membuka HTTPS.'
    source: https://github.com/traviscross/mtr

  - id: b04
    tier: B
    type: text
    objective: path
    prompt: Nama sysctl Linux yang menentukan **jeda minimum** (ms) antar pesan ICMP seperti Time Exceeded ke tujuan yang sama (default `1000`)?
    answers: ['net.ipv4.icmp_ratelimit']
    patterns: ['icmp_ratelimit']
    tests:
      fail: ['net.ipv4.ip_forward', 'net.ipv4.icmp_echo_ignore_all', 'net.ipv4.tcp_syn_retries']
    explanation: 'Di router lab: `icmp_ratelimit = 1000`, `icmp_ratemask = 6168` (bit 3 Dest Unreachable, 4 Source Quench, 11 Time Exceeded, 12 Parameter Problem), `icmp_msgs_per_sec = 1000`. Akibatnya `mtr -i 0.1` → hop router `80.0%` loss tanpa satu pun paket data yang hilang. Router hardware memakai control-plane policing dengan efek serupa. Jangan pernah melapor ke ISP "loss 80% di hop 5" bila hop terakhir 0%.'
    source: https://man7.org/linux/man-pages/man7/icmp.7.html

  - id: b05
    tier: B
    type: command
    objective: capture
    prompt: Dari file `/tmp/c.pcap`, tampilkan **hanya segmen yang ber-flag RST**, memakai filter flag TCP pcap.
    answers: ["tcpdump -nn -r /tmp/c.pcap 'tcp[tcpflags] & tcp-rst != 0'"]
    patterns: ['^(?=.*\s-r ?/tmp/c\.pcap(\s|$))tcpdump( (-[a-zA-Z]+|-r ?\S+))* tcp\[(tcpflags|13)\] ?& ?(tcp-rst|4|0x0?4) ?(!= ?0|== ?(tcp-rst|4|0x0?4))$']
    tests:
      pass: ['tcpdump -r /tmp/c.pcap tcp[13] & 4 != 0', "tcpdump -nn -r /tmp/c.pcap 'tcp[tcpflags] & tcp-rst == tcp-rst'"]
      fail: ['tcpdump -nn -r /tmp/c.pcap tcp-rst', "tcpdump -nn -r /tmp/c.pcap 'tcp[tcpflags] & tcp-syn != 0'", "tcpdump -nn -w /tmp/c.pcap 'tcp[tcpflags] & tcp-rst != 0'"]
    explanation: 'Di lab dari 21 paket → hanya satu baris: `10.80.2.20.7777 > 10.80.1.10.55260: Flags [R.]`. Variasi lain: `tcp[tcpflags] & (tcp-syn|tcp-ack) == tcp-syn` (SYN awal saja, lab memperlihatkan SYN ke port 9999 diulang pada `:16.94`, `:17.94`, `:18.97`, … karena tidak pernah dijawab). Bungkus filter dengan kutip satu agar `&`, `[`, dan `!` tidak ditafsirkan shell.'
    source: https://www.tcpdump.org/manpages/pcap-filter.7.html

  - id: b06
    tier: B
    type: command
    objective: capture
    prompt: Di router, tangkap trafik `tcp port 8080` di **semua interface sekaligus** agar terlihat paket masuk dan keluarnya.
    answers: ['tcpdump -nn -i any tcp port 8080']
    patterns: ['^(?=.*\s-i ?any(\s|$))tcpdump( (-[a-zA-Z]+|-i ?any|-c ?\d+))* (tcp )?port 8080$']
    tests:
      pass: ['tcpdump -i any port 8080', 'tcpdump -nn -i any -c 4 tcp port 8080']
      fail: ['tcpdump -nn -i eth0 tcp port 8080', 'tcpdump -nn -i all tcp port 8080', 'tcpdump -nn tcp port 8080']
    explanation: 'Di lab → `eth0  In  IP 10.80.1.10.33342 > 10.80.2.20.8080: Flags [S]` lalu `eth1  Out IP ... Flags [S]`, `eth1  In ... [S.]`, `eth0  Out ... [S.]`. Satu capture langsung membuktikan router menerima **dan** meneruskan kedua arah. Bila hanya ada `In` tanpa `Out`, paket dibuang di router (firewall, route, rp_filter). Dengan `-i any`, link-type menjadi `LINUX_SLL2` (cooked), jadi alamat MAC tujuan tidak ada.'
    source: https://www.tcpdump.org/manpages/tcpdump.1.html

  - id: b07
    tier: B
    type: command
    objective: capture
    prompt: Baca file `/tmp/s.pcap` dengan menampilkan **alamat MAC** (header link-layer) setiap paket.
    answers: ['tcpdump -e -nn -r /tmp/s.pcap']
    patterns: ['^(?=.*\s-[a-zA-Z]*e[a-zA-Z]*(\s|$))(?=.*\s-r ?/tmp/s\.pcap(\s|$))tcpdump( (-[a-zA-Z]+|-r ?\S+))*( \S.*)?$']
    tests:
      pass: ['tcpdump -nne -r /tmp/s.pcap', 'tcpdump -e -r /tmp/s.pcap tcp port 8080']
      fail: ['tcpdump -nn -r /tmp/s.pcap', 'tcpdump -E -r /tmp/s.pcap', 'tcpdump -e -w /tmp/s.pcap']
    explanation: 'Di lab, server yang salah route balik → `76:2f:d7:5b:c8:2d > fa:08:e1:35:59:a7 ... 10.80.2.20.8080 > 10.80.1.10: Flags [S.]`, padahal SYN-nya datang dari MAC router `12:70:43:79:66:72`. IP tujuan benar, tetapi frame dikirim ke **next hop yang salah** (`dj-x10`, yang tidak meneruskan). Hal seperti ini tidak terlihat tanpa `-e`. Cocokkan MAC dengan `ip neigh` / `ip link`.'
    source: https://www.tcpdump.org/manpages/tcpdump.1.html

  - id: b08
    tier: B
    type: text
    objective: socket
    prompt: Client timeout. Di server, `ss -tan` menunjukkan koneksi dari client itu tertahan di state apa bila SYN-ACK server **tidak pernah sampai** ke client?
    answers: ['SYN-RECV']
    patterns: ['syn[-_ ]?r(ecv|eceived)']
    tests:
      fail: ['SYN-SENT', 'ESTAB', 'TIME-WAIT', 'LISTEN']
    explanation: 'Di lab → server `SYN-RECV ... 10.80.2.20:8080  10.80.1.10:53642`, dengan tcpdump server memperlihatkan SYN masuk dan `[S.]` keluar berulang (`:33`, `:34`, `:35`, `:36`, `:38`), sedangkan client hanya mengulang SYN. Artinya jalur **pergi** sehat dan aplikasi hidup, sehingga masalahnya di jalur **balik**. Cek `ip route get <IP client>` di server (lab: `via 10.80.2.30` → seharusnya `via 10.80.2.254`).'
    source: https://man7.org/linux/man-pages/man8/ss.8.html

  - id: b09
    tier: B
    type: command
    objective: socket
    prompt: Dengan `nstat`, tampilkan counter kernel **jumlah segmen TCP yang diretransmisi**.
    answers: ['nstat -az TcpRetransSegs']
    patterns: ['^nstat( -[a-zA-Z]+)* TcpRetransSegs$', '^nstat( -[a-zA-Z]+)* \| grep (-[a-zA-Z]+ )*(TcpRetransSegs|-i retrans)$']
    tests:
      pass: ['nstat TcpRetransSegs', 'nstat -az | grep -i retrans']
      fail: ['nstat -az TcpOutSegs', 'ss -s', 'netstat TcpRetransSegs']
    explanation: 'Di lab → `TcpOutSegs 8320491`, `TcpRetransSegs 2859`. Rasio retransmisi/keluar adalah indikator loss **per host** (semua koneksi), cocok dipantau di metrik (node_exporter mengekspor `node_netstat_Tcp_RetransSegs`). `nstat` tanpa `-a` menampilkan **selisih** sejak pemanggilan terakhir, sehingga dua kali panggil berjarak 10 detik langsung memberi laju retransmisi saat itu.'
    source: https://man7.org/linux/man-pages/man8/nstat.8.html

  - id: b10
    tier: B
    type: text
    objective: perf
    prompt: Link 100 Mbit/s dengan RTT 100 ms. Berapa **bandwidth-delay product** (BDP) dalam **byte**?
    answers: ['1250000']
    patterns: ['^1[.,]?250[.,]?000( ?(byte|bytes|b))?$', '^1[.,]25 ?(mb|megabyte|juta byte)$']
    tests:
      fail: ['12500000', '10000000', '125000', '1,25 gb']
    explanation: 'BDP = bandwidth × RTT = 100.000.000 bit/s × 0,1 s = 10.000.000 bit = **1.250.000 byte**. Itulah jumlah data yang harus "di udara" (belum di-ACK) agar pipa penuh. Bila window TCP lebih kecil dari BDP, link tidak akan pernah penuh berapa pun bandwidth-nya. Lab RTT 100 ms: window default (autotuning hingga MB-an) → `263 Mbits/sec`, `-w 64K` → `6.29 Mbits/sec`.'
    source: https://software.es.net/iperf/invoking.html

  - id: b11
    tier: B
    type: text
    objective: perf
    prompt: Tulis rumus **throughput maksimum** satu koneksi TCP yang dibatasi ukuran window.
    answers: ['window / RTT']
    patterns: ['^(window|rwnd|cwnd|jendela)( size)? ?/ ?rtt$', '^(window|jendela) dibagi rtt$']
    tests:
      fail: ['RTT / window', 'bandwidth × RTT', 'window × RTT']
    explanation: 'TCP hanya boleh mengirim sebanyak window, lalu menunggu ACK (1 RTT). Throughput ≤ window / RTT. Di lab, `-w 64K` (kernel menggandakan buffer, efektif ~80 KB terbang) / 0,1 s ≈ 6,3 Mbit/s → hasil `6.29 Mbits/sec`. Window yang sama tanpa delay → `18047 Mbits/sec`. **Latency yang tinggi menurunkan throughput** walaupun bandwidth besar, sehingga transfer lintas region terasa lambat meski "link-nya 1 Gbps".'
    source: https://software.es.net/iperf/invoking.html

  - id: b12
    tier: B
    type: command
    objective: perf
    prompt: Jalankan iperf3 client ke `10.80.2.20` dengan **4 stream paralel**.
    answers: ['iperf3 -c 10.80.2.20 -P 4']
    patterns: ['^(?=.*\s-c ?10\.80\.2\.20(\s|$))(?=.*\s-P ?4(\s|$))iperf3( (-[a-zA-Z]+|-[ctfPw] ?\S+))+$']
    tests:
      pass: ['iperf3 -c 10.80.2.20 -t 5 -w 64K -P 4']
      fail: ['iperf3 -c 10.80.2.20 -p 4', 'iperf3 -s -P 4', 'iperf3 -c 10.80.2.20 -P 40']
    explanation: 'Di lab RTT 100 ms dengan `-w 64K` → 1 stream `6.29`, 4 stream `[SUM] 27.7 Mbits/sec` (≈4×). Bila paralel jauh lebih cepat daripada satu stream, batasnya ada **per koneksi** (window/RTT atau loss), bukan kapasitas link. Ini alasan tool seperti `rclone`, `aws s3 cp`, dan `aria2` memakai banyak koneksi. Di iperf3, `-p` (kecil) adalah port.'
    source: https://software.es.net/iperf/invoking.html

  - id: b13
    tier: B
    type: command
    objective: perf
    prompt: Qdisc netem `delay 25ms` sudah terpasang di `eth1`. **Ubah** (tanpa hapus) agar juga membuang **1%** paket.
    answers: ['tc qdisc change dev eth1 root netem delay 25ms loss 1%']
    patterns: ['^tc qdisc (change|replace) dev eth1 root netem (delay 25 ?ms loss 1%|loss 1% delay 25 ?ms)$']
    tests:
      pass: ['tc qdisc replace dev eth1 root netem loss 1% delay 25ms']
      fail: ['tc qdisc add dev eth1 root netem loss 1%', 'tc qdisc change dev eth1 root netem loss 1%', 'tc qdisc change dev eth0 root netem delay 25ms loss 1%']
    explanation: 'Parameter netem di `change` **menggantikan** semuanya, sehingga `change ... loss 1%` saja akan menghapus delay-nya. Hasil lab (RTT 50 ms): tanpa loss `603 Mbits/sec`, dengan **1% loss** `5.50 Mbits/sec` dan `Retr 30`, turun lebih dari 100×. Pada saat yang sama, `ping -c 50` → `0% packet loss`, karena 50 sampel terlalu sedikit untuk melihat 1%. `tc -s qdisc` memperlihatkan jumlah yang dibuang (`dropped 11` dari 200 ping saat 5% loss).'
    source: https://man7.org/linux/man-pages/man8/tc-netem.8.html

  - id: b14
    tier: B
    type: command
    objective: timing
    prompt: Lewati DNS untuk satu request, yaitu akses `http://app.lab:8080/` dengan memaksa `app.lab` (port 8080) ke `10.80.2.20`, tanpa mengubah `/etc/hosts`.
    answers: ['curl --resolve app.lab:8080:10.80.2.20 http://app.lab:8080/']
    patterns: ['^curl( -[a-zA-Z]+| -o \S+)* --resolve[= ]app\.lab:8080:10\.80\.2\.20( -[a-zA-Z]+| -o \S+)* http://app\.lab:8080/?$']
    tests:
      pass: ['curl -s -o /dev/null --resolve app.lab:8080:10.80.2.20 http://app.lab:8080/']
      fail: ['curl --resolve app.lab:10.80.2.20 http://app.lab:8080/', 'curl --dns-servers 10.80.2.20 http://app.lab:8080/', 'curl --resolve app.lab:8080:10.80.2.20 http://10.80.2.20:8080/']
    explanation: 'Di lab, resolver lambat → `dns=1.503103 connect=1.503693 ttfb=1.505927`. Dengan `--resolve` → `dns=0.000009 total=0.002310`. Ini bisect dalam satu perintah: DNS dikeluarkan dari persamaan, sementara Host header, SNI TLS, dan URL tetap sama seperti aslinya. Format `host:port:alamat` wajib menyertakan port.'
    source: https://curl.se/docs/manpage.html#--resolve

  - id: b15
    tier: B
    type: text
    objective: timing
    prompt: 'Request HTTP polos: `dns=0.000 connect=0.001 ttfb=2.800 total=2.801`. Bagian mana yang paling mungkin lambat?'
    answers: ['aplikasi di server']
    patterns: ['aplikasi', 'server', 'backend', '\bapp\b', 'upstream', 'database']
    tests:
      fail: ['DNS', 'jaringan', 'TCP handshake', 'TLS']
    explanation: 'Connect 1 ms berarti RTT jaringan kecil. Waktu dari connect ke TTFB (2,8 s) adalah waktu **server memproses** request sebelum mengirim byte pertama (aplikasi, query DB, upstream). Bandingkan dengan lab RTT 100 ms: `connect=0.101 ttfb=0.203`, di mana TTFB − connect ≈ 1 RTT (murni jaringan). Masalah seperti ini diselidiki dengan log/APM aplikasi, bukan tcpdump. Untuk HTTPS, `time_appconnect` memisahkan durasi handshake TLS.'
    source: https://curl.se/docs/manpage.html#-w

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: method
    prompt: |
      Setelah perubahan firewall, `curl http://10.80.2.20:8080/` dari client `10.80.1.10` timeout (`Connection timed out`),
      padahal `ping 10.80.2.20` masih berhasil. Jalurnya client → gateway Linux (eth0 `10.80.1.254`, eth1 `10.80.2.254`)
      → server. Jelaskan langkah mencari lokasinya, kesimpulan, dan perbaikannya (satu poin per baris).
    checklist:
      - id: layer
        label: Ping sukses berarti L3 sehat; masalah spesifik TCP/port 8080
        patterns: ['ping[^\n]*(sukses|berhasil|ok|jalan)', '(spesifik|khusus|hanya)[^\n]*(8080|tcp|port)', 'l3[^\n]*(sehat|ok)']
      - id: capture
        label: tcpdump di beberapa titik (client, gateway eth0 & eth1, server)
        required: true
        patterns: ['tcpdump[^\n]*-i ?(eth0|eth1|any)', '(eth0|eth1)[^\n]*(eth1|eth0|server)', 'beberapa titik', 'bisect']
      - id: locate
        label: SYN masuk eth0 tetapi tidak keluar eth1 → dibuang di gateway
        required: true
        patterns: ['syn[^\n]*(eth0|masuk)[^\n]*(tidak|tak|bukan|ga|gak)[^\n]*(eth1|keluar|server)', '(dibuang|di-?drop|drop)[^\n]*(gateway|router)', '(gateway|router)[^\n]*(membuang|drop)']
      - id: counters
        label: Cek counter aturan (iptables -L FORWARD -v -n / nft list ruleset)
        patterns: ['iptables[^\n]*-[a-z]*l[^\n]*forward', 'iptables[^\n]*-v', 'nft list', 'counter']
      - id: fix
        label: Perbaiki aturan spesifik (ACCEPT 8080 / hapus rule DROP tertentu)
        required: true
        patterns: ['iptables[^\n]*-[ai] forward[^\n]*8080[^\n]*accept', 'iptables[^\n]*-d forward', '(hapus|delete|ubah|perbaiki)[^\n]*(rule|aturan)[^\n]*(8080|drop)', 'accept[^\n]*8080', '8080[^\n]*accept']
      - id: verify
        label: Verifikasi ulang (curl / tcpdump) setelah perbaikan
        patterns: ['(verifikasi|cek ulang|uji ulang|tes ulang|ulangi)', 'curl[^\n]*(200|lagi|ulang)']
      - id: no-flush
        label: Tidak mem-flush seluruh firewall / policy ACCEPT semua
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|hindari|tidak boleh|tanpa))[^\n]*(iptables -f\b|iptables --flush|-p forward accept|matikan firewall|disable firewall|nft flush ruleset)']
    pass_score: 0.5
    solution: |
      ```text
      1. Lapis demi lapis: ping OK → routing L3 sehat; gagal hanya TCP/8080 → curigai filter port.
      2. Bisect dengan tcpdump serentak:
           gateway: tcpdump -nn -i eth0 'tcp port 8080'   → SYN terlihat (diulang tiap ~1 s)
           gateway: tcpdump -nn -i eth1 'tcp port 8080'   → kosong
           server : tcpdump -nn -i eth0 'tcp port 8080'   → kosong
         → paket hilang DI DALAM gateway (antara eth0 dan eth1).
      3. iptables -L FORWARD -v -n -x  → "3  180  DROP  tcp dpt:8080" (counter naik setiap percobaan)
      4. Perbaiki rule spesifik: iptables -D FORWARD -p tcp --dport 8080 -j DROP
         (atau sisipkan ACCEPT 8080 untuk sumber yang semestinya, sesuai kebijakan)
      5. Verifikasi: curl → 200, counter DROP berhenti naik; catat perubahan di tiket.
      ```
      Langkah-langkah ini sama persis dengan lab. Timeout (bukan RST) berarti paket dibuang diam-diam (DROP), dan
      capture di dua sisi interface gateway memotong kemungkinan menjadi satu kotak. Jangan `iptables -F` atau mengubah
      policy menjadi ACCEPT: masalah memang "hilang", tetapi semua proteksi lain ikut hilang, dan aturan yang salah
      tidak pernah ditemukan.
    tests:
      pass:
        - |
          ping berhasil jadi L3 sehat, masalah khusus port 8080
          tcpdump -nn -i eth0 tcp port 8080 di gateway: SYN masuk
          tcpdump -nn -i eth1 tcp port 8080: SYN masuk eth0 tapi tidak keluar eth1, berarti dibuang di gateway
          iptables -L FORWARD -v -n lihat counter DROP dpt:8080
          iptables -D FORWARD -p tcp --dport 8080 -j DROP
          verifikasi curl dapat 200
        - |
          bisect pakai tcpdump di eth0 dan eth1 gateway
          paket di-drop di gateway
          hapus rule DROP 8080 yang baru ditambahkan
      fail:
        - |
          tcpdump -i eth0 di gateway
          dibuang di gateway
          iptables -F supaya semua lancar
        - |
          restart aplikasi di server
          ganti DNS
        - |
          tcpdump di client saja
          matikan firewall gateway
    source: https://www.tcpdump.org/manpages/tcpdump.1.html

  - id: c02
    tier: C
    type: scenario
    objective: capture
    prompt: |
      Setelah migrasi, client `10.80.1.10` timeout ke `http://10.80.2.20:8080/`. Di server, `tcpdump -nn -e -i eth0 port 8080`
      menunjukkan SYN dari client masuk dan server membalas `[S.]` berulang kali, sedangkan `ss -tan` di server menunjukkan
      `SYN-RECV`. Aplikasi sendiri sehat (`curl localhost:8080` di server → 200). Analisis dan perbaiki (satu poin per baris).
    checklist:
      - id: syn-recv
        label: SYN-RECV/SYN-ACK berulang = SYN-ACK tidak sampai / jalur balik bermasalah
        required: true
        patterns: ['(jalur|route|rute|arah) (balik|pulang|kembali)', 'return (path|route)', 'syn-?ack[^\n]*(tidak|tak|gak|ga) (sampai|diterima|tiba)', 'asimetri|asymmetric']
      - id: forward-ok
        label: Jalur pergi dan aplikasi sehat (SYN sampai, app lokal 200)
        patterns: ['(jalur|arah) pergi[^\n]*(sehat|ok|aman)', 'syn[^\n]*sampai', 'aplikasi[^\n]*(sehat|ok|bukan masalah)']
      - id: route-get
        label: Cek route balik di server (ip route get <client> / ip route)
        required: true
        patterns: ['ip route get', 'ip r(oute)?( show)?\b', 'tabel routing|routing table']
      - id: mac
        label: Cocokkan MAC tujuan SYN-ACK dengan MAC gateway (tcpdump -e / ip neigh)
        patterns: ['mac', 'ip neigh', '-e\b', 'next ?hop']
      - id: fix
        label: Perbaiki route ke subnet client lewat gateway yang benar
        required: true
        patterns: ['ip route (replace|add|change)[^\n]*10\.80\.1\.0/24[^\n]*via 10\.80\.2\.254', 'route[^\n]*via 10\.80\.2\.254', '(ganti|perbaiki|ubah) (route|rute|gateway)']
      - id: other
        label: Kemungkinan lain di jalur balik (firewall stateful, rp_filter, NAT)
        patterns: ['rp_filter', 'stateful', 'conntrack', '\bnat\b', 'firewall[^\n]*(balik|keluar|output)']
      - id: verify
        label: Verifikasi (ss ESTAB, curl 200, route persisten)
        patterns: ['(verifikasi|cek ulang|uji ulang|tes ulang)', 'curl[^\n]*200', 'persist|permanen|netplan|config']
      - id: blame-app
        label: Tidak menyalahkan aplikasi / menaikkan timeout
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|hindari|tidak boleh|tidak perlu))[^\n]*(restart (aplikasi|app|service)|naikkan timeout|tambah timeout|perbesar backlog)']
    pass_score: 0.4
    solution: |
      ```text
      Fakta: SYN masuk server → jalur pergi OK. Server membalas [S.] berulang + SYN-RECV → SYN-ACK tidak pernah di-ACK
             → SYN-ACK tidak sampai ke client = masalah JALUR BALIK. Aplikasi sehat (localhost 200).
      Cek:   ip route get 10.80.1.10      → "via 10.80.2.30"   (seharusnya via 10.80.2.254)
             tcpdump -e: SYN datang dari MAC 12:70:43:79:66:72 (router), [S.] dikirim ke fa:08:e1:35:59:a7 (host lain)
             ip neigh show 10.80.2.30     → MAC itu milik host yang tidak meneruskan paket
      Fix:   ip route replace 10.80.1.0/24 via 10.80.2.254
             lalu jadikan permanen di konfigurasi jaringan (netplan / systemd-networkd / ifupdown)
      Cek:   curl → 200, ss → ESTAB; capture router eth1 kini melihat [S.]
      Lainnya bila route benar: firewall stateful di jalur balik yang tidak melihat SYN (asimetris), rp_filter, NAT.
      ```
      Ini persis lab: dengan route balik `via 10.80.2.30` (host tanpa forwarding), server berada di `SYN-RECV` dengan
      SYN-ACK berulang ke MAC yang salah. Setelah `ip route replace`, langsung `200`. Catatan jujur: di Docker Desktop,
      bila route khusus dihapus dan server jatuh ke default gateway Docker, koneksi **tetap jalan** karena host Docker
      ikut me-routing. Gejala ini baru muncul bila next hop benar-benar tidak meneruskan. Menaikkan timeout atau
      me-restart aplikasi tidak akan pernah memperbaikinya.
    tests:
      pass:
        - |
          SYN sampai ke server jadi jalur pergi sehat, aplikasi juga sehat
          SYN-RECV dan SYN-ACK berulang berarti jalur balik bermasalah
          ip route get 10.80.1.10 di server
          cocokkan MAC tujuan SYN-ACK pakai tcpdump -e dan ip neigh
          ip route replace 10.80.1.0/24 via 10.80.2.254
          cek juga rp_filter dan firewall stateful
          verifikasi curl dapat 200 dan buat route permanen
        - |
          ini masalah return path
          cek ip route di server
          ganti route ke gateway yang benar
      fail:
        - |
          restart aplikasi di server
          naikkan timeout curl
          ip route get 10.80.1.10
        - |
          server SYN-RECV berarti backlog penuh
          perbesar backlog
        - |
          jalur balik bermasalah
          ip route get 10.80.1.10
    source: https://man7.org/linux/man-pages/man8/ss.8.html

  - id: c03
    tier: C
    type: scenario
    objective: perf
    prompt: |
      Backup harian Jakarta → Singapura (link 1 Gbit/s, RTT ~50 ms) hanya mencapai ~5 Mbit/s. Data yang ada:
      `iperf3 -c` satu stream → `5.50 Mbits/sec`, `Retr 30`; `ping -c 50` → `0% packet loss`;
      `mtr -r -c 100 -i 0.1` → hop 3 `85.0%` loss, hop terakhir `1.0%`. Interpretasikan datanya dan tentukan langkah
      berikutnya (satu poin per baris).
    checklist:
      - id: real-loss
        label: Retr > 0 dan loss di hop terakhir = loss nyata ~1%
        required: true
        patterns: ['retr[^\n]*(loss|nyata|hilang|retrans)', 'hop (terakhir|akhir|tujuan)[^\n]*1', 'loss (nyata|sungguhan|riil|asli)', 'retransmi']
      - id: midhop
        label: Loss hop tengah (85%) artefak ICMP rate limit, abaikan
        required: true
        patterns: ['rate[- ]?limit', 'hop 3[^\n]*(abaikan|artefak|bukan|palsu|tidak nyata)', 'icmp_ratelimit']
      - id: ping-sample
        label: ping 50 paket terlalu sedikit untuk mendeteksi 1% loss
        patterns: ['(50|sampel|sample)[^\n]*(sedikit|kurang|kecil)', '(terlalu sedikit|tidak cukup)', 'ping[^\n]*(-c ?[1-9]\d{2,}|lebih banyak|lebih lama)']
      - id: loss-throughput
        label: Loss kecil menjatuhkan throughput TCP drastis (cwnd mengecil)
        patterns: ['cwnd', 'congestion', '(1 ?%|loss)[^\n]*(drastis|turun|jatuh|100)', 'ss -t[a-z]*i']
      - id: bdp
        label: BDP 1 Gbit/s × 50 ms ≈ 6,25 MB → window/buffer cukup
        patterns: ['bdp', '6[.,]25 ?mb', '6250000', 'tcp_[rw]mem', 'window']
      - id: localize
        label: Lokalisasi loss (mtr dua arah, iperf3 -R/UDP, ip -s link, tc -s qdisc, laporkan ke ISP)
        patterns: ['-r\b[^\n]*(arah|reverse)|iperf3[^\n]*-r\b', 'dua arah|kedua arah|reverse', 'ip -s link', 'tc -s', '(isp|provider)', '-u -b']
      - id: workaround
        label: Mitigasi sementara (stream paralel / BBR)
        patterns: ['-p ?\d', 'paralel', 'parallel', 'bbr', 'multi[- ]?stream']
      - id: blame-midhop
        label: Tidak menyalahkan hop 3 sebagai penyebab
        forbid: true
        patterns: ['^(?![^\n]*(bukan|jangan|abaikan|artefak|palsu|tidak nyata|rate))[^\n]*hop 3[^\n]*(penyebab|biang|rusak|masalah(nya)?\b)']
    pass_score: 0.4
    solution: |
      ```text
      Baca data:
        - iperf3 Retr 30 + hop TERAKHIR 1.0% → ada loss nyata ~1% di jalur
        - hop 3 85% tetapi hop setelahnya turun → artefak ICMP rate limit (icmp_ratelimit 1000 ms vs -i 0.1), abaikan
        - ping -c 50 0% → sampel terlalu kecil untuk 1% (pakai -c 1000 / mtr -c 300+)
      Kenapa 5 Mbit/s: TCP (cubic) menganggap loss = kemacetan → cwnd kecil (ss -ti: cwnd:12 ssthresh:11).
        Lab RTT 50 ms: 0% loss → 603 Mbit/s, 1% loss → 5.5 Mbit/s.
      Cek window juga: BDP = 1 Gbit/s × 0,05 s = 6,25 MB → tcp_rmem/tcp_wmem max harus ≥ itu.
      Lokalisasi: mtr dari kedua arah, iperf3 -R dan -u -b 100M (loss% UDP langsung terlihat),
        ip -s link / ethtool -S (error interface), tc -s qdisc (drop lokal) → bawa bukti ke ISP/provider.
      Mitigasi sambil menunggu: stream paralel (-P 8 / rclone --transfers), congestion control BBR.
      ```
      Setiap angka diambil dari lab (netem `delay 25ms` di dua arah + `loss 1%`): satu persen loss menurunkan
      throughput lebih dari 100×, sementara `ping -c 50` tetap `0%`. Dengan `mtr -i 0.1`, router Linux tanpa firewall
      pun tampak `80–85%` loss karena rate limit ICMP. Melapor "hop 3 rusak" ke ISP hanya membuang waktu. Bukti yang
      kuat adalah loss di hop terakhir dan `Retr`/`TcpRetransSegs`.
    tests:
      pass:
        - |
          Retr 30 dan hop terakhir 1% berarti ada loss nyata
          hop 3 85% itu artefak ICMP rate limit, abaikan
          ping 50 paket terlalu sedikit untuk lihat 1%
          loss membuat cwnd kecil, cek ss -ti
          BDP 6,25 MB cek tcp_rmem
          mtr dari kedua arah dan iperf3 -R, bawa bukti ke ISP
          sementara pakai stream paralel -P 8 atau BBR
        - |
          loss nyata di jalur karena retransmisi
          hop 3 bukan masalah, cuma rate limit
          coba paralel
      fail:
        - |
          hop 3 penyebab loss, lapor ke ISP
          upgrade bandwidth
        - |
          ping 0% jadi jaringan sehat
          masalah di aplikasi backup
        - |
          retransmisi menunjukkan loss nyata
          hop 3 router rusak jadi penyebab utama
          rate limit tidak relevan
    source: https://man7.org/linux/man-pages/man8/tc-netem.8.html
---

# Troubleshooting Jaringan Metodologis

Masalah jaringan jarang berupa "jaringannya mati". Biasanya yang terjadi hanya satu port, satu arah, satu jenis paket,
atau hanya saat data besar. Menebak-nebak (restart, ganti DNS, matikan firewall) membuang waktu dan sering
menimbulkan masalah baru. Modul penutup ini menyatukan alat dari modul 01–09 menjadi **metode**: membuat hipotesis,
mengukur, lalu memotong kemungkinan menjadi separuh.

> [!NOTE]
> Lab: client `dj-c10` (`10.80.1.10`) → router `dj-r10` (eth0 `10.80.1.254`, eth1 `10.80.2.254`) → server `dj-s10`
> (`10.80.2.20`, HTTP `:8080`, iperf3 `:5201`). Gangguan dibuat dengan iptables, route yang salah, dan `tc netem`.

## 1. Metodenya

```mermaid
flowchart TD
    A[Gejala spesifik: siapa, ke mana, port apa, sejak kapan] --> B{Nama ter-resolve?}
    B -- tidak --> D[DNS: dig, resolv.conf, curl --resolve]
    B -- ya --> C{IP terjangkau? ping/mtr}
    C -- tidak --> R[Routing/ARP: ip route get, ip neigh, traceroute]
    C -- ya --> T{TCP port terbuka? RST vs timeout}
    T -- timeout --> F[Bisect dengan tcpdump di beberapa titik]
    T -- RST --> P[Service tidak listen / REJECT]
    T -- ya --> H{TLS/HTTP? curl -w, curl -v}
    H --> Q[Lambat? latency vs throughput vs aplikasi]
```

Prinsipnya:
1. **Spesifikkan gejala.** "Timeout dari pod A ke 10.80.2.20:8080 sejak pukul 14.00" lebih berguna daripada
   "jaringan lemot".
2. **Lapis demi lapis.** Setiap tes yang lolos menghapus satu lapisan dari daftar tersangka.
3. **Bisect.** Ukur di tengah jalur, lalu lanjut ke separuh yang bermasalah.
4. **Ubah satu hal, ukur ulang, catat.** Tanpa langkah ini, kamu tidak tahu perubahan mana yang sebenarnya menolong.

## 2. Jalur: traceroute, tracepath, mtr

| Alat | Probe default | Kegunaan | Lab |
|---|---|---|---|
| `traceroute -n` | UDP 33434+ | Hop demi hop | Server DROP UDP → `2 *`, `3 *` … |
| `traceroute -I` | ICMP Echo | Bila UDP diblok | → `2  10.80.2.20` |
| `tracepath -n` | UDP + DF | Jalur + PMTU, tanpa root | `Resume: pmtu 1500 hops 2 back 2` |
| `mtr -n -r -c 100` | ICMP | Loss/jitter per hop dari waktu ke waktu | `Loss% Snt Avg Best Wrst StDev` |
| `mtr -T -P 443` | TCP SYN | Meniru trafik aplikasi | Tetap jalan saat UDP diblok |

Membaca hasilnya:

```text
Router membuang ICMP Time Exceeded keluar:     1  *  *  *            ← hop "hilang"
                                               2  10.80.2.20 0.008 ms ← tujuan sehat
mtr -i 0.1, router TANPA firewall:             1.|-- 10.80.1.254  80.0%   ← icmp_ratelimit 1000 ms
                                               2.|-- 10.80.2.20    0.0%   ← tidak ada loss nyata
netem loss 1% di jalur:                        2.|-- 10.80.2.20    1.0%   ← loss nyata muncul di AKHIR
```

> [!TIP]
> Aturan praktis: **loss yang nyata selalu berlanjut sampai hop terakhir.** Loss atau `* * *` yang hanya muncul di
> hop tengah adalah kebijakan router (rate limit, ICMP diblok), bukan paket yang hilang.

## 3. tcpdump di beberapa titik

```bash
tcpdump -nn -i eth0 -w /tmp/c.pcap host 10.80.2.20            # simpan mentah
tcpdump -nn -r /tmp/c.pcap port 8080                          # baca ulang dengan filter lain
tcpdump -nn -r /tmp/c.pcap 'tcp[tcpflags] & tcp-rst != 0'     # hanya RST
tcpdump -nn -r /tmp/c.pcap 'tcp[tcpflags] & (tcp-syn|tcp-ack) == tcp-syn'   # hanya SYN awal
tcpdump -nn -i any tcp port 8080                              # router: lihat In & Out
tcpdump -e -nn -r /tmp/s.pcap                                 # tampilkan MAC
```

| Yang terlihat | Arti | Lab |
|---|---|---|
| SYN → `[R.]` seketika | Host hidup, port tertutup/REJECT | port 7777, `after 0 ms` |
| SYN diulang (~1 s, lalu 2 s) | Paket dibuang di jalur (DROP) | port 9999, `curl: (28)` |
| Router `eth0 In` tanpa `eth1 Out` | Dibuang di router | `FORWARD ... DROP tcp dpt:8080` |
| Server: SYN masuk, `[S.]` berulang, `SYN-RECV` | Jalur **balik** rusak | route `via 10.80.2.30`, MAC tujuan salah |

## 4. Socket dan counter

```text
ss -tin dst 10.80.2.20
  cubic rtt:52.405/3.503 mss:1448 cwnd:12 ssthresh:11 bytes_retrans:30408 retrans:0/21 notsent:482184
nstat -az TcpRetransSegs TcpOutSegs
  TcpOutSegs 8320491     TcpRetransSegs 2859
tc -s qdisc show dev eth1
  Sent 18522 bytes 189 pkt (dropped 11, ...)
```

`cwnd` kecil disertai `retrans` naik berarti TCP sedang mengerem karena loss. Jika `Send-Q`/`notsent` besar,
data menumpuk di pengirim karena jalurnya tidak sanggup mengalirkan.

## 5. Latency vs throughput

| Kondisi lab (iperf3, 1 stream) | Hasil |
|---|---|
| RTT ~0, `-w 64K` | `18047 Mbits/sec` |
| RTT 100 ms, window default | `263 Mbits/sec` |
| RTT 100 ms, `-w 64K` | `6.29 Mbits/sec` (≈ window / RTT) |
| RTT 100 ms, `-w 64K -P 4` | `27.7 Mbits/sec` (≈ 4×) |
| RTT 50 ms, 0% loss | `603 Mbits/sec` |
| RTT 50 ms, **1% loss** | `5.50 Mbits/sec`, `Retr 30` |

- **Latency** (RTT) adalah waktu bolak-balik satu paket. Ukur dengan ping, mtr, atau `curl time_connect`.
- **Throughput** adalah jumlah data per detik. Ukur dengan iperf3 dan transfer nyata.
- Throughput satu koneksi TCP ≤ **window / RTT**, dan bisa jatuh drastis karena loss.
- BDP = bandwidth × RTT, yaitu ukuran window yang dibutuhkan agar pipa penuh.

```bash
tc qdisc add dev eth1 root netem delay 50ms             # tiru WAN
tc qdisc change dev eth1 root netem delay 25ms loss 1%  # change menggantikan SEMUA parameter
tc qdisc del dev eth1 root
```

## 6. Memecah waktu request dengan curl

```bash
curl -s -o /dev/null -w 'dns=%{time_namelookup} connect=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total}\n' URL
```

| Hasil lab | Kesimpulan |
|---|---|
| `dns=1.503 connect=1.5037 ttfb=1.5059` | DNS lambat, lalu buktikan dengan `--resolve` → `total=0.0023` |
| `connect=0.101 ttfb=0.203` (RTT 100 ms) | Murni jaringan: connect 1 RTT, request 1 RTT |
| `connect=0.001 ttfb=2.8` | Server/aplikasi lambat, bukan jaringan |

## 7. Ringkasan gejala → langkah

| Gejala | Langkah pertama | Modul |
|---|---|---|
| `Could not resolve host` | `dig`, `curl --resolve` | 04 |
| Timeout, ping OK | tcpdump di beberapa titik, counter firewall | 08 |
| RST seketika | `ss -ltn` di server, aturan REJECT | 03, 08 |
| Server `SYN-RECV` | `ip route get <client>`, tcpdump `-e` | 02 |
| Kecil lancar, besar macet | `tracepath`, `ping -M do -s` | 02 |
| Lambat, connect cepat | `curl -w` (TTFB), log aplikasi | 05 |
| Throughput rendah | iperf3 (`-P`, `-R`), `ss -ti`, loss di hop terakhir | 10 |
| `* * *` / loss di hop tengah | Abaikan bila hop akhir sehat | 10 |
