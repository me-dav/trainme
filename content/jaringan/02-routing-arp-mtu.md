---
format: 1
id: net-routing-arp-mtu
course:
  slug: jaringan
  title: Jaringan
  description: Konsep jaringan untuk DevOps — TCP/IP, subnetting, routing, TCP/UDP, DNS, HTTP, TLS, proxy, firewall, VPN, dan troubleshooting.
title: Routing, ARP & MTU
summary: Bagaimana paket berpindah antar subnet — tabel routing, longest prefix match, router Linux (ip_forward), route balik dan jalur asimetris, TTL dan traceroute, ARP, serta MTU, Path MTU Discovery, PMTU blackhole, dan MSS clamping — dibuktikan di topologi 3 container.
order: 2
version: 1
status: verified
updated: '2026-11-23'
tested_on:
  - Docker Desktop (Windows) — Docker Engine 29.4.1; topologi host A (10.60.1.0/24) — router — host B (10.60.2.0/24)
  - alpine:3.20 + iproute2, iputils, tcpdump, iptables; nginx:1.27-alpine sebagai server
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: table
    text: Membaca dan mengubah tabel routing (static route, default, blackhole)
  - id: lpm
    text: Memahami longest prefix match
  - id: forward
    text: Menjadikan Linux sebagai router dan memahami route balik
  - id: ttl
    text: Memahami TTL, ICMP Time Exceeded, dan traceroute
  - id: arp
    text: Memahami ARP dan tabel neighbor
  - id: mtu
    text: Memahami MTU, Path MTU Discovery, PMTU blackhole, dan MSS clamping
sources:
  - title: ip-route(8)
    url: https://man7.org/linux/man-pages/man8/ip-route.8.html
  - title: ip-neighbour(8)
    url: https://man7.org/linux/man-pages/man8/ip-neighbour.8.html
  - title: Linux kernel — IP sysctl (ip_forward, PMTU)
    url: https://docs.kernel.org/networking/ip-sysctl.html
  - title: RFC 1812 — Requirements for IP Version 4 Routers (longest match, TTL)
    url: https://www.rfc-editor.org/rfc/rfc1812
  - title: RFC 792 — Internet Control Message Protocol
    url: https://www.rfc-editor.org/rfc/rfc792
  - title: RFC 826 — An Ethernet Address Resolution Protocol
    url: https://www.rfc-editor.org/rfc/rfc826
  - title: RFC 1191 — Path MTU Discovery
    url: https://www.rfc-editor.org/rfc/rfc1191
  - title: RFC 2923 — TCP Problems with Path MTU Discovery (PMTU black hole)
    url: https://www.rfc-editor.org/rfc/rfc2923
  - title: iptables-extensions(8) — TCPMSS target
    url: https://man7.org/linux/man-pages/man8/iptables-extensions.8.html
  - title: ping(8) — iputils (-M, -s, -t)
    url: https://man7.org/linux/man-pages/man8/ping.8.html
  - title: traceroute(8)
    url: https://man7.org/linux/man-pages/man8/traceroute.8.html
  - title: docker container run — --sysctl
    url: https://docs.docker.com/reference/cli/docker/container/run/#sysctl
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: command
    objective: table
    prompt: Tambahkan route statis ke subnet `10.60.2.0/24` lewat gateway `10.60.1.254`.
    answers: ['ip route add 10.60.2.0/24 via 10.60.1.254']
    patterns: ['^ip (-4 )?r(oute)? add 10\.60\.2\.0/24 via 10\.60\.1\.254( dev eth0)?$']
    tests:
      pass: ['ip r add 10.60.2.0/24 via 10.60.1.254', 'ip route add 10.60.2.0/24 via 10.60.1.254 dev eth0']
      fail: ['ip route add 10.60.2.0/24 gw 10.60.1.254', 'route add 10.60.2.0/24 10.60.1.254', 'ip route add default via 10.60.1.254']
    explanation: 'Di lab, setelah route ini ditambahkan, `ip route get 10.60.2.20` → `via 10.60.1.254 dev eth0`. Route yang ditambah dengan `ip` hilang saat reboot. Untuk permanen, pakai konfigurasi distro (netplan `routes:`, NetworkManager, systemd-networkd). Sintaks lama `route add ... gw` dari net-tools sudah usang.'
    source: https://man7.org/linux/man-pages/man8/ip-route.8.html

  - id: a02
    tier: A
    type: command
    objective: table
    prompt: Hapus route statis ke `10.60.2.0/24`.
    answers: ['ip route del 10.60.2.0/24']
    patterns: ['^ip (-4 )?r(oute)? (del|delete) 10\.60\.2\.0/24( via 10\.60\.1\.254)?( dev eth0)?$']
    tests:
      pass: ['ip route delete 10.60.2.0/24', 'ip r del 10.60.2.0/24 via 10.60.1.254']
      fail: ['ip route remove 10.60.2.0/24', 'ip route flush', 'ip route del 10.60.2.20']
    explanation: 'Route diidentifikasi oleh prefix tujuannya. `ip route flush` menghapus **semua** route, termasuk default, sehingga sesi SSH bisa langsung putus. Jangan memakainya di server remote.'
    source: https://man7.org/linux/man-pages/man8/ip-route.8.html

  - id: a03
    tier: A
    type: text
    objective: forward
    prompt: Parameter sysctl kernel yang mengizinkan host Linux **meneruskan** paket IPv4 antar interface (menjadi router)?
    answers: ['net.ipv4.ip_forward']
    patterns: ['^(net\.ipv4\.ip_forward|/proc/sys/net/ipv4/ip_forward)(=1)?$']
    tests:
      pass: ['net.ipv4.ip_forward=1', '/proc/sys/net/ipv4/ip_forward']
      fail: ['net.ipv4.conf.all.forwarding_off', 'net.ipv4.ip_local_port_range', 'ip_forwarding', 'net.core.forward']
    explanation: 'Di lab, router dengan `ip_forward=0` diam-diam membuang paket A → B (`100% packet loss`, tanpa ICMP error). Dengan `1`, ping berhasil dengan `ttl=63`. Docker sendiri menyalakan `ip_forward` di host agar container bisa keluar. Itu sebabnya mengaktifkan Docker di server mengubah perilaku firewall (modul Firewall).'
    source: https://docs.kernel.org/networking/ip-sysctl.html

  - id: a04
    tier: A
    type: text
    objective: forward
    prompt: Opsi `docker run` untuk menyetel parameter sysctl namespace container (mis. `ip_forward`) **saat container dibuat**?
    answers: ['--sysctl']
    tests:
      fail: ['--privileged', '--cap-add NET_ADMIN', '--net-opt', '-e']
    explanation: 'Di lab: `--sysctl net.ipv4.ip_forward=1` → `/proc/sys/net/ipv4/ip_forward` = `1`. Mencoba `sysctl -w` dari dalam container yang sudah jalan → `Read-only file system` (soal tier B). Di Compose: `sysctls: { net.ipv4.ip_forward: 1 }`.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#sysctl

  - id: a05
    tier: A
    type: text
    objective: ttl
    prompt: Host Linux (TTL awal 64) membalas ping. Bila paket melewati **satu** router, berapa nilai `ttl` di balasan yang terlihat?
    answers: ['63']
    tests:
      fail: ['64', '62', '128', '1']
    explanation: 'Setiap router mengurangi TTL sebanyak 1. Di lab: ping ke router (satu subnet) → `ttl=64`, dan ke host B di balik router → `ttl=63`. TTL awal juga memberi petunjuk OS: Linux/macOS 64, Windows 128, beberapa perangkat jaringan 255. Jadi `ttl=118` biasanya berarti Windows 10 hop jauhnya.'
    source: https://www.rfc-editor.org/rfc/rfc1812

  - id: a06
    tier: A
    type: command
    objective: ttl
    prompt: Tampilkan jalur (hop demi hop) ke `10.60.2.20` dengan `traceroute`, tanpa resolve nama.
    answers: ['traceroute -n 10.60.2.20']
    patterns: ['^traceroute (-n( -w ?\d+| -q ?\d+| -m ?\d+)*|(-w ?\d+ |-q ?\d+ |-m ?\d+ )+-n( -w ?\d+| -q ?\d+| -m ?\d+)*) 10\.60\.2\.20$']
    tests:
      pass: ['traceroute -n -w1 -q1 10.60.2.20', 'traceroute -m 5 -n 10.60.2.20']
      fail: ['traceroute 10.60.2.20', 'tracert -n 10.60.2.20', 'ping -n 10.60.2.20']
    explanation: 'Di lab: `1  10.60.1.254` (router), `2  10.60.2.20` (tujuan). Saat router tidak meneruskan paket, semua baris menjadi `*`. traceroute mengirim paket dengan TTL 1, 2, 3, ..., dan setiap router yang menjatuhkan paket karena TTL habis membalas dengan ICMP Time Exceeded. Di Windows: `tracert -d`.'
    source: https://man7.org/linux/man-pages/man8/traceroute.8.html

  - id: a07
    tier: A
    type: text
    objective: ttl
    prompt: Pesan ICMP apa yang dikirim router saat TTL paket habis (mis. `ping -t 1` ke host di balik router)?
    answers: ['Time to live exceeded', 'time exceeded']
    patterns: ['(time to live exceeded|time exceeded|ttl exceeded|ttl habis)']
    tests:
      pass: ['ICMP Time Exceeded', 'ttl exceeded']
      fail: ['destination unreachable', 'echo reply', 'frag needed', 'redirect']
    explanation: 'Di lab, `ping -t1 10.60.2.20` → `From 10.60.1.254 icmp_seq=1 Time to live exceeded`, dikirim oleh router (bukan tujuan). TTL mencegah paket berputar selamanya bila ada routing loop. Mekanisme inilah yang dipakai traceroute.'
    source: https://www.rfc-editor.org/rfc/rfc792

  - id: a08
    tier: A
    type: command
    objective: arp
    prompt: Tampilkan tabel **neighbor** (cache ARP, IP → MAC) dengan iproute2.
    answers: ['ip neigh']
    patterns: ['^ip (-4 )?n(eigh(bour|bor)?)?( show| list| ls)?$']
    tests:
      pass: ['ip neighbour show', 'ip n']
      fail: ['ip addr', 'ip link', 'arp -a -n eth0', 'ip route']
    explanation: 'Di lab: `10.60.1.254 dev eth0 lladdr 1a:f6:d5:53:62:5c REACHABLE`. Kolom terakhir adalah status entry: REACHABLE, STALE (perlu dicek lagi), INCOMPLETE (sedang/gagal resolve), FAILED. Padanan lama: `arp -n`.'
    source: https://man7.org/linux/man-pages/man8/ip-neighbour.8.html

  - id: a09
    tier: A
    type: text
    objective: arp
    prompt: ARP request `who-has 10.60.1.254` dikirim ke **MAC tujuan** apa?
    answers: ['ff:ff:ff:ff:ff:ff', 'broadcast']
    patterns: ['^(ff:ff:ff:ff:ff:ff|broadcast|ff-ff-ff-ff-ff-ff)$']
    tests:
      pass: ['FF:FF:FF:FF:FF:FF', 'broadcast']
      fail: ['00:00:00:00:00:00', 'mac router', '10.60.1.254', 'multicast']
    explanation: 'Di lab: `be:c0:d8:b0:23:67 > ff:ff:ff:ff:ff:ff, ethertype ARP (0x0806) ... Request who-has 10.60.1.254 tell 10.60.1.10`. Balasan dikirim **unicast**: `76:77:32:80:18:05 > be:c0:... Reply 10.60.1.254 is-at 76:77:32:80:18:05`. Karena request berupa broadcast, ARP tidak melewati router dan hanya bekerja dalam satu segmen L2.'
    source: https://www.rfc-editor.org/rfc/rfc826

  - id: a10
    tier: A
    type: text
    objective: arp
    prompt: Status apa yang ditampilkan `ip neigh` untuk IP di subnet yang sama yang **tidak ada** (tidak pernah menjawab ARP)?
    answers: ['INCOMPLETE', 'FAILED']
    tests:
      fail: ['REACHABLE', 'STALE', 'PERMANENT', 'DELAY']
    explanation: 'Di lab, `ping 10.60.1.77` (tidak ada host) → `10.60.1.77 dev eth0 INCOMPLETE`, lalu berubah menjadi FAILED setelah percobaan habis. Ping ke host seperti ini gagal **tanpa** satu paket ICMP pun terkirim, karena frame tidak bisa dibuat tanpa MAC tujuan. Bila IP gateway INCOMPLETE, masalahnya ada di L2 (kabel, VLAN, bridge), bukan routing.'
    source: https://man7.org/linux/man-pages/man8/ip-neighbour.8.html

  - id: a11
    tier: A
    type: text
    objective: mtu
    prompt: MTU default interface Ethernet (byte)?
    answers: ['1500']
    tests:
      fail: ['1460', '1472', '9000', '1400']
    explanation: 'Di lab, `cat /sys/class/net/eth0/mtu` → `1500`. MTU adalah ukuran paket IP maksimum di satu link, dihitung tanpa header Ethernet 14 byte. Jumbo frame (9000) hanya bekerja bila **semua** perangkat di jalur itu mendukungnya.'
    source: https://docs.kernel.org/networking/ip-sysctl.html

  - id: a12
    tier: A
    type: command
    objective: mtu
    prompt: Dengan ping iputils, kirim **satu** ping ke `10.60.1.10` dengan payload **1472** byte dan bit **Don't Fragment** (larang fragmentasi).
    answers: ['ping -c1 -M do -s 1472 10.60.1.10']
    patterns: ['^ping (?=.*-c ?1( |$))(?=.*-M ?do( |$))(?=.*-s ?1472( |$))((-c ?1|-M ?do|-s ?1472|-W ?\d+) )+10\.60\.1\.10$']
    tests:
      pass: ['ping -M do -s 1472 -c 1 10.60.1.10', 'ping -c1 -W1 -M do -s 1472 10.60.1.10']
      fail: ['ping -c1 -s 1472 10.60.1.10', 'ping -c1 -M dont -s 1472 10.60.1.10', 'ping -c1 -M do -s 1500 10.60.1.10']
    explanation: '1472 + 8 (ICMP) + 20 (IP) = 1500, tepat MTU Ethernet. Di lab, lewat link 1400 → `From 10.60.2.254 icmp_seq=1 Frag needed and DF set (mtu = 1400)`, sedangkan dengan `-s 1372` → berhasil. Ping busybox tidak punya `-M`, jadi pasang `iputils`. Di Windows: `ping -f -l 1472`.'
    source: https://man7.org/linux/man-pages/man8/ping.8.html

  - id: a13
    tier: A
    type: text
    objective: mtu
    prompt: Berapa byte header ICMP echo (yang harus dikurangkan selain header IP saat menghitung `ping -s` maksimum)?
    answers: ['8']
    tests:
      fail: ['20', '14', '28', '40']
    explanation: '`-s` maksimum = MTU − 20 (IP) − 8 (ICMP). Untuk MTU 1500 → 1472, dan MTU 1400 → **1372** (lab: lolos). Untuk TCP: MSS = MTU − 20 − 20, jadi 1500 → 1460 dan 1400 → 1360.'
    source: https://www.rfc-editor.org/rfc/rfc792

  - id: a14
    tier: A
    type: command
    objective: mtu
    prompt: Ubah MTU interface `eth0` menjadi `1400`.
    answers: ['ip link set eth0 mtu 1400']
    patterns: ['^ip l(ink)? set (dev )?eth0 mtu 1400$']
    tests:
      pass: ['ip link set dev eth0 mtu 1400', 'ip l set eth0 mtu 1400']
      fail: ['ip addr set eth0 mtu 1400', 'ifconfig eth0 1400', 'ip link set eth0 1400']
    explanation: 'Di lab, router diberi `mtu 1400` di sisi A untuk mensimulasikan link sempit (VPN, PPPoE 1492, overlay VXLAN 1450). Perubahan ini tidak permanen. Di Docker, MTU network diatur dengan `--opt com.docker.network.driver.mtu=1400` atau `"mtu"` di `daemon.json`.'
    source: https://man7.org/linux/man-pages/man8/ip-link.8.html

  - id: a15
    tier: A
    type: text
    objective: table
    prompt: Tipe route (di `ip route add <tipe> 203.0.113.0/24`) yang **membuang** paket ke tujuan itu tanpa mengirim apa pun?
    answers: ['blackhole']
    tests:
      fail: ['unreachable', 'prohibit', 'drop', 'null']
    explanation: 'Di lab, `blackhole 203.0.113.0/24` → ping gagal lokal dengan `ping: connect: Invalid argument`. Tipe `unreachable` memberi `Host is unreachable`, dan `prohibit` memberi "administratively prohibited". Blackhole route dipakai untuk menolak rentang yang sedang diserang, atau sebagai "route cadangan" agar prefix tetap diumumkan.'
    source: https://man7.org/linux/man-pages/man8/ip-route.8.html

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: lpm
    prompt: |
      Tabel routing host A:
      `default via 10.60.1.1`, `10.60.0.0/16 via 10.60.1.1`, `10.60.2.0/24 via 10.60.1.254`.
      Lewat gateway mana paket ke `10.60.2.20` dikirim?
    answers: ['10.60.1.254']
    tests:
      fail: ['10.60.1.1', 'default', '10.60.0.1', '10.60.2.1']
    explanation: 'Kernel memilih route yang **cocok dengan prefix terpanjang** (longest prefix match). `10.60.2.20` cocok dengan /0, /16, dan /24, sehingga /24 menang. Di lab, `ip route get 10.60.2.20` → `via 10.60.1.254`. Urutan route di tabel tidak berpengaruh.'
    source: https://www.rfc-editor.org/rfc/rfc1812

  - id: b02
    tier: B
    type: text
    objective: lpm
    prompt: Dengan tabel routing yang sama, lewat gateway mana paket ke `10.60.9.9` dikirim?
    answers: ['10.60.1.1']
    tests:
      fail: ['10.60.1.254', '10.60.9.1', 'tidak ada route']
    explanation: '`10.60.9.9` tidak cocok dengan /24 (10.60.2.x), tetapi cocok dengan `10.60.0.0/16` → `via 10.60.1.1` (lab: `ip route get 10.60.9.9`). Prinsip yang sama menjelaskan VPN "split tunnel": route /8 milik VPN mengalahkan default /0, dan network Docker /16 mengalahkan route VPN /12 (modul 01).'
    source: https://man7.org/linux/man-pages/man8/ip-route.8.html

  - id: b03
    tier: B
    type: text
    objective: table
    prompt: Ada route `unreachable 198.51.100.0/24`. Pesan apa yang muncul saat `ping 198.51.100.5`?
    answers: ['Host is unreachable']
    patterns: ['(host|network) is unreachable', 'unreachable']
    tests:
      pass: ['ping: connect: Host is unreachable', 'unreachable']
      fail: ['invalid argument', 'timeout', 'time to live exceeded', 'connection refused']
    explanation: 'Di lab: `ping: connect: Host is unreachable`, ditolak seketika oleh kernel lokal. Bandingkan dengan `blackhole` → `Invalid argument`, dan host yang tidak ada di subnet → timeout setelah ARP gagal. Kecepatan munculnya error memberi petunjuk: penolakan seketika berarti keputusan lokal, sedangkan menunggu berarti paket sudah dikirim.'
    source: https://man7.org/linux/man-pages/man8/ip-route.8.html

  - id: b04
    tier: B
    type: text
    objective: forward
    prompt: Router dijalankan dengan `ip_forward=0`. Host A sudah punya route ke B lewat router. Hasil `ping -c2` dari A ke B?
    answers: ['100% packet loss']
    patterns: ['(100% packet loss|100%|timeout|tidak ada balasan|semua hilang|gagal|0 received)']
    tests:
      pass: ['timeout, 100% loss', '2 transmitted 0 received']
      fail: ['network unreachable', 'berhasil', 'connection refused', 'ttl exceeded']
    explanation: 'Di lab: `2 packets transmitted, 0 received, 100% packet loss`. Router yang tidak meneruskan paket membuangnya diam-diam, dan traceroute hanya menampilkan `*`. Bila route di host sudah benar tetapi paket hilang di hop pertama, cek `ip_forward` dan firewall FORWARD di router.'
    source: https://docs.kernel.org/networking/ip-sysctl.html

  - id: b05
    tier: B
    type: text
    objective: forward
    prompt: '`sysctl -w net.ipv4.ip_forward=1` dijalankan di dalam container (dengan NET_ADMIN) yang sudah berjalan. Error apa yang muncul?'
    answers: ['Read-only file system']
    patterns: ['read-?only']
    tests:
      pass: ['sysctl: error setting key: Read-only file system']
      fail: ['permission denied', 'operation not permitted', 'berhasil', 'unknown key']
    explanation: 'Di lab: `sysctl: error setting key ''net.ipv4.ip_forward'': Read-only file system`. Docker me-mount `/proc/sys` read-only di container, walaupun dengan `NET_ADMIN`. sysctl namespace jaringan harus diberikan saat create (`--sysctl`, `sysctls:` di Compose). Container harus dibuat ulang untuk mengubahnya.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#sysctl

  - id: b06
    tier: B
    type: text
    objective: arp
    prompt: Host A (10.60.1.10) ping host B (10.60.2.20) lewat router 10.60.1.254. Setelah itu, entry MAC milik siapa yang muncul di `ip neigh` A untuk perjalanan ini?
    answers: ['router (10.60.1.254)']
    patterns: ['(router|gateway|10\.60\.1\.254|next[- ]?hop)']
    tests:
      pass: ['10.60.1.254', 'MAC router / gateway']
      fail: ['10.60.2.20', 'host B', 'tidak ada', 'ff:ff:ff:ff:ff:ff']
    explanation: 'Di lab, `ip neigh` A hanya berisi `10.60.1.254 ... REACHABLE` (dan gateway Docker `10.60.1.1`), tanpa `10.60.2.20`. Untuk tujuan di luar subnet, IP tujuan di header tetap B, tetapi MAC tujuan di frame adalah MAC **next hop**. ARP tidak pernah dipakai untuk mencari MAC host di subnet lain.'
    source: https://www.rfc-editor.org/rfc/rfc826

  - id: b07
    tier: B
    type: text
    objective: forward
    prompt: 'A punya route ke B lewat router, tetapi B **tidak** punya route balik ke `10.60.1.0/24` (hanya `default via 10.60.2.1`, gateway Docker). Lewat mana balasan B ke A dikirim?'
    answers: ['lewat default gateway 10.60.2.1, bukan router']
    patterns: ['(default|10\.60\.2\.1|gateway docker|host)']
    tests:
      pass: ['default gateway 10.60.2.1', 'lewat gateway docker, jalur berbeda']
      fail: ['10.60.2.254', 'lewat router yang sama', 'tidak dikirim']
    explanation: 'Di lab, `ip route get 10.60.1.10` di B → `via 10.60.2.1`. Request lewat router, sedangkan balasan lewat host Docker. Ini **routing asimetris**. Di lab kebetulan tetap sampai (ping 0% loss, terlihat di tcpdump B), tetapi di jaringan nyata asimetri sering memutus koneksi, karena firewall stateful atau NAT di satu jalur hanya melihat setengah percakapan. Setiap route statis butuh pasangan route balik.'
    source: https://www.rfc-editor.org/rfc/rfc1812

  - id: b08
    tier: B
    type: text
    objective: forward
    prompt: Tanpa route statis, A (network Docker `dj-neta`) ping B (network `dj-netb`) lewat gateway default Docker. Hasilnya 100% loss. Kenapa?
    answers: ['Docker mengisolasi network bridge yang berbeda']
    patterns: ['(isolasi|isolation|isolate|terisolasi|network (berbeda|lain)|antar (network|bridge)|docker-isolation)']
    tests:
      pass: ['isolasi antar network docker', 'DOCKER-ISOLATION memblokir antar bridge']
      fail: ['dns gagal', 'mtu terlalu kecil', 'arp gagal', 'ttl habis']
    explanation: 'Di lab: `ip route get 10.60.2.20` → `via 10.60.1.1` (gateway Docker), lalu `100% packet loss`. Host punya route ke kedua bridge, tetapi aturan isolasi Docker memblokir lalu lintas antar network user-defined (modul Docker Networking). Router container yang terpasang di kedua network adalah jalan resminya, setara `docker network connect`.'
    source: https://docs.docker.com/engine/network/drivers/bridge/

  - id: b09
    tier: B
    type: text
    objective: mtu
    prompt: Jalur B → A melewati link dengan MTU 1400. Berapa nilai `-s` **terbesar** untuk `ping -M do` yang masih lolos?
    answers: ['1372']
    tests:
      fail: ['1400', '1472', '1380', '1360']
    explanation: '1400 − 20 (IP) − 8 (ICMP) = **1372**. Di lab: `-s 1472` → `Frag needed and DF set (mtu = 1400)` dari router `10.60.2.254`, dan `-s 1372` → `1 received`. Cara mencari path MTU: turunkan `-s` sampai lolos, lalu tambahkan 28.'
    source: https://man7.org/linux/man-pages/man8/ping.8.html

  - id: b10
    tier: B
    type: text
    objective: mtu
    prompt: 'Setelah B menerima ICMP "Frag needed (mtu = 1400)", `ip route get 10.60.1.10` di B menampilkan `cache expires 599sec mtu 1400`. Mekanisme apa ini?'
    answers: ['Path MTU Discovery']
    patterns: ['(path mtu discovery|pmtud|pmtu)']
    tests:
      pass: ['PMTUD', 'path mtu discovery, mtu jalur di-cache']
      fail: ['arp', 'fragmentasi', 'mss clamping', 'routing dinamis']
    explanation: 'Dengan PMTUD (RFC 1191), pengirim memasang DF dan router yang tidak bisa meneruskan membalas dengan ICMP type 3 code 4 berisi MTU-nya. Pengirim lalu menyimpan MTU itu per tujuan (lab: 600 detik), dan transfer 3 MB berhasil. Mekanisme ini sepenuhnya bergantung pada ICMP yang sampai ke pengirim (soal berikutnya).'
    source: https://www.rfc-editor.org/rfc/rfc1191

  - id: b11
    tier: B
    type: text
    objective: mtu
    prompt: 'Router memblokir ICMP "fragmentation-needed" (type 3 code 4). Dari A: `wget small.txt` (6 byte) dan `wget big.bin` (3 MB). Mana yang gagal?'
    answers: ['big.bin']
    patterns: ['(big|3 ?mb|besar|yang besar)']
    tests:
      pass: ['big.bin macet', 'yang besar']
      fail: ['small.txt', 'keduanya', 'tidak ada']
    explanation: 'Di lab: `small.txt OK`, `big.bin FAILED/HANG`, dan B tidak pernah mempelajari PMTU (`no pmtu learned`). Paket kecil muat di link 1400, tetapi segmen penuh 1500 + DF dibuang router, ICMP pemberitahuannya juga dibuang, sehingga koneksi menggantung. Inilah **PMTU blackhole** (RFC 2923): SSH login lancar, tetapi `git clone`, upload, atau response API besar macet.'
    source: https://www.rfc-editor.org/rfc/rfc2923

  - id: b12
    tier: B
    type: command
    objective: mtu
    prompt: Di router, pasang aturan iptables yang **menyesuaikan MSS** pada setiap SYN TCP yang diteruskan dengan path MTU (MSS clamping).
    answers: ['iptables -t mangle -A FORWARD -p tcp --tcp-flags SYN,RST SYN -j TCPMSS --clamp-mss-to-pmtu']
    patterns: ['^iptables -t mangle -(A|I) FORWARD -p tcp --tcp-flags SYN,RST SYN -j TCPMSS --clamp-mss-to-pmtu$', '^iptables -t mangle -(A|I) FORWARD -p tcp --syn -j TCPMSS --clamp-mss-to-pmtu$']
    tests:
      pass: ['iptables -t mangle -I FORWARD -p tcp --tcp-flags SYN,RST SYN -j TCPMSS --clamp-mss-to-pmtu', 'iptables -t mangle -A FORWARD -p tcp --syn -j TCPMSS --clamp-mss-to-pmtu']
      fail: ['iptables -A FORWARD -p tcp -j TCPMSS --clamp-mss-to-pmtu', 'iptables -t nat -A FORWARD -p tcp --syn -j TCPMSS --clamp-mss-to-pmtu', 'iptables -t mangle -A INPUT -p tcp --syn -j TCPMSS --clamp-mss-to-pmtu']
    explanation: 'Di lab, dengan ICMP tetap diblokir, aturan ini membuat `big.bin OK`. Router mengubah opsi MSS di SYN/SYN-ACK sehingga kedua ujung sepakat memakai segmen yang muat (MTU − 40), tanpa bergantung pada ICMP. Hasil lab menunjukkan kernel Linux memakai MTU terkecil dari kedua arah, sehingga satu aturan cukup untuk download maupun upload. Padanan nftables: `tcp flags syn tcp option maxseg size set rt mtu`.'
    source: https://man7.org/linux/man-pages/man8/iptables-extensions.8.html

  - id: b13
    tier: B
    type: text
    objective: mtu
    prompt: Bila path MTU 1400 dan kamu memakai `--set-mss` (nilai tetap), berapa MSS yang tepat?
    answers: ['1360']
    tests:
      fail: ['1400', '1372', '1460', '1380']
    explanation: '1400 − 20 (IP) − 20 (TCP) = **1360**. Di lab, `-j TCPMSS --set-mss 1360` juga membuat `big.bin OK`. `--set-mss` berguna bila MTU link sempit tidak terlihat oleh router, mis. MTU di tengah jalur milik ISP. `--clamp-mss-to-pmtu` otomatis, tetapi hanya tahu MTU interface router itu sendiri.'
    source: https://man7.org/linux/man-pages/man8/iptables-extensions.8.html

  - id: b14
    tier: B
    type: text
    objective: mtu
    prompt: Selain MSS clamping, apa perbaikan **akar** PMTU blackhole bila kamu yang mengontrol firewall-nya?
    answers: ['izinkan ICMP type 3 code 4 (fragmentation-needed)']
    patterns: ['(izinkan|allow|buka|jangan blok|jangan memblokir|accept).*(icmp|frag)', '(icmp|frag).*(diizinkan|diizinkan|allow|accept|dibuka)', 'type 3']
    tests:
      pass: ['izinkan icmp fragmentation-needed', 'jangan blok icmp type 3 code 4', 'accept icmp frag-needed']
      fail: ['blok semua icmp', 'naikkan mtu ke 9000', 'matikan tcp', 'restart router']
    explanation: 'Memblokir "semua ICMP demi keamanan" adalah penyebab klasik PMTU blackhole. Di lab, satu aturan `DROP icmptype 3 code 4` sudah cukup untuk membuat unduhan besar macet. Izinkan minimal ICMP type 3 (destination unreachable, termasuk code 4) dan type 11 (time exceeded). Echo boleh dibatasi.'
    source: https://www.rfc-editor.org/rfc/rfc2923

  - id: b15
    tier: B
    type: text
    objective: ttl
    prompt: Saat router tidak meneruskan paket (`ip_forward=0`), seperti apa output `traceroute -n -q1` ke host di baliknya?
    answers: ['semua hop bintang (*)']
    patterns: ['(\*|bintang|asterisk|tidak ada balasan|timeout)']
    tests:
      pass: ['* * * di setiap hop', 'semua bintang']
      fail: ['hop 1 router lalu tujuan', 'network unreachable', 'ttl exceeded dari tujuan']
    explanation: 'Di lab, 30 baris `*` (hop 1 sampai 30), karena paket TTL=1 pun tidak dibalas: router yang forwarding-nya mati membuang paket sebelum memproses TTL. Setelah forwarding aktif → `1  10.60.1.254`, `2  10.60.2.20`. Catatan: banyak router di internet sengaja tidak membalas traceroute, jadi `*` di tengah jalur tidak selalu berarti putus.'
    source: https://man7.org/linux/man-pages/man8/traceroute.8.html

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: forward
    prompt: |
      Dua subnet: `10.60.1.0/24` (app, host A `10.60.1.10`) dan `10.60.2.0/24` (db, host B `10.60.2.20`). Sebuah VM Linux
      punya kaki di keduanya: `10.60.1.254` dan `10.60.2.254`. Tulis perintah (satu per baris) untuk menjadikan VM itu
      router dan membuat A ↔ B saling terhubung, termasuk verifikasinya.
    checklist:
      - id: forward
        label: Mengaktifkan net.ipv4.ip_forward=1 (dan permanen)
        required: true
        patterns: ['ip_forward\s*=\s*1']
      - id: route-a
        label: Route di A ke 10.60.2.0/24 via 10.60.1.254
        required: true
        patterns: ['route\s+add\s+10\.60\.2\.0/24\s+via\s+10\.60\.1\.254']
      - id: route-b
        label: Route balik di B ke 10.60.1.0/24 via 10.60.2.254
        required: true
        patterns: ['route\s+add\s+10\.60\.1\.0/24\s+via\s+10\.60\.2\.254']
      - id: persist
        label: Membuat permanen (sysctl.d / netplan routes)
        patterns: ['sysctl\.d', '/etc/sysctl\.conf', 'netplan', 'routes:']
      - id: verify
        label: Verifikasi (traceroute / ping ttl / ip route get)
        patterns: ['traceroute', 'ip\s+route\s+get', 'ping\s+.*10\.60\.(1\.10|2\.20)']
      - id: no-nat-hide
        label: Tidak menyembunyikan masalah dengan flush routing
        forbid: true
        patterns: ['ip\s+route\s+flush', 'iptables\s+-F']
    pass_score: 0.6
    solution: |
      ```bash
      # router
      sudo sysctl -w net.ipv4.ip_forward=1
      echo 'net.ipv4.ip_forward = 1' | sudo tee /etc/sysctl.d/90-router.conf
      # host A
      sudo ip route add 10.60.2.0/24 via 10.60.1.254
      # host B (route balik — wajib)
      sudo ip route add 10.60.1.0/24 via 10.60.2.254
      # permanen: netplan  routes: [{ to: 10.60.2.0/24, via: 10.60.1.254 }]  (dan sebaliknya di B)
      # verifikasi dari A
      ip route get 10.60.2.20          # via 10.60.1.254
      traceroute -n 10.60.2.20         # 1 10.60.1.254, 2 10.60.2.20
      ping -c2 10.60.2.20              # ttl=63
      ```
      Setiap langkah dibuktikan di lab:
      - Tanpa `ip_forward` → 100% loss dan traceroute penuh `*`.
      - Tanpa route balik di B → balasan lewat gateway lain (asimetris). Di lab kebetulan sampai, tetapi rapuh.
      - Dengan semuanya → `ttl=63` dan dua hop.

      Bila router juga menjalankan firewall (FORWARD policy DROP, mis. karena Docker), izinkan lalu lintas kedua subnet
      di chain FORWARD.
    tests:
      pass:
        - |
          sysctl -w net.ipv4.ip_forward=1
          echo net.ipv4.ip_forward=1 > /etc/sysctl.d/90-router.conf
          ip route add 10.60.2.0/24 via 10.60.1.254
          ip route add 10.60.1.0/24 via 10.60.2.254
          traceroute -n 10.60.2.20
        - |
          sysctl net.ipv4.ip_forward=1
          ip route add 10.60.2.0/24 via 10.60.1.254
          ip route add 10.60.1.0/24 via 10.60.2.254
          ping -c2 10.60.2.20
      fail:
        - |
          sysctl -w net.ipv4.ip_forward=1
          ip route add 10.60.2.0/24 via 10.60.1.254
          ping 10.60.2.20
        - |
          ip route add 10.60.2.0/24 via 10.60.1.254
          ip route add 10.60.1.0/24 via 10.60.2.254
          traceroute 10.60.2.20
        - |
          ip_forward=1
          ip route flush
          ip route add 10.60.2.0/24 via 10.60.1.254
          ip route add 10.60.1.0/24 via 10.60.2.254
    source: https://docs.kernel.org/networking/ip-sysctl.html

  - id: c02
    tier: C
    type: scenario
    objective: mtu
    prompt: |
      Setelah kantor cabang terhubung lewat VPN site-to-site, user melapor: SSH ke server di pusat lancar, `ping` normal,
      tetapi `git clone`, upload file, dan halaman web besar **macet** tanpa error. Tulis diagnosis dan perbaikannya
      (satu perintah/poin per baris).
    checklist:
      - id: probe
        label: Mengukur path MTU dengan ping -M do -s (atau tracepath)
        required: true
        patterns: ['ping\s+.*-M\s*do', 'tracepath', 'ping\s+-f\s+-l']
      - id: identify
        label: Mengenali PMTU blackhole (paket besar + DF dibuang, ICMP frag-needed tidak sampai)
        required: true
        patterns: ['(pmtu|path mtu|blackhole|black hole)', 'frag(mentation)?[- ]?needed', 'type 3']
      - id: icmp
        label: Mengizinkan ICMP type 3 code 4 di firewall
        patterns: ['(izinkan|allow|accept|buka).*(icmp|frag)', 'icmp.*(type 3|frag).*(accept|allow)']
      - id: clamp
        label: MSS clamping di router/VPN gateway
        required: true
        patterns: ['tcpmss', 'clamp-mss', 'set-mss', 'maxseg']
      - id: mtu
        label: Atau menurunkan MTU interface tunnel
        patterns: ['mtu\s+1[34]\d\d', 'ip\s+link\s+set\s+.*mtu']
      - id: verify
        label: Verifikasi transfer besar berhasil
        patterns: ['(curl|wget|git\s+clone|scp).*', 'verifikasi', 'uji ulang']
      - id: no-block-icmp
        label: Tidak memblokir semua ICMP
        forbid: true
        patterns: ['-p\s+icmp\s+.*-j\s+drop', 'blok(ir)?\s+semua\s+icmp']
    pass_score: 0.6
    solution: |
      ```bash
      ping -c1 -M do -s 1472 10.0.5.10      # Frag needed (mtu = 1400)?  atau diam (ICMP diblok)
      ping -c1 -M do -s 1372 10.0.5.10      # turunkan sampai lolos → path MTU = s + 28
      tracepath -n 10.0.5.10                # menampilkan pmtu per hop
      # diagnosis: PMTU blackhole — segmen penuh (MSS 1460) + DF dibuang di tunnel 1400,
      #            ICMP fragmentation-needed diblok firewall → pengirim tidak pernah tahu
      # perbaikan (di VPN gateway kedua sisi):
      iptables -t mangle -A FORWARD -p tcp --tcp-flags SYN,RST SYN -j TCPMSS --clamp-mss-to-pmtu
      # dan/atau izinkan ICMP: iptables -A INPUT -p icmp --icmp-type fragmentation-needed -j ACCEPT (juga FORWARD/OUTPUT)
      # alternatif: ip link set tun0 mtu 1400  (WireGuard default MTU 1420)
      curl -o /dev/null https://intranet.pusat/large.iso   # verifikasi transfer besar
      ```
      Gejala ini persis direproduksi di lab:
      - Blokir ICMP type 3 code 4 di router → `small.txt OK`, `big.bin FAILED/HANG`, B tidak pernah mempelajari PMTU.
      - MSS clamping → `big.bin OK` walaupun ICMP tetap diblokir.
      - `--set-mss 1360` → juga OK.

      SSH interaktif dan ping kecil tidak pernah mengirim paket penuh, sehingga terlihat normal.
    tests:
      pass:
        - |
          ping -c1 -M do -s 1472 10.0.5.10
          ping -c1 -M do -s 1372 10.0.5.10
          PMTU blackhole karena icmp fragmentation-needed diblok
          iptables -t mangle -A FORWARD -p tcp --syn -j TCPMSS --clamp-mss-to-pmtu
          izinkan icmp type 3 code 4
          uji ulang git clone
        - |
          tracepath 10.0.5.10
          path mtu blackhole di tunnel
          set-mss 1360 di gateway vpn
          ip link set tun0 mtu 1400
      fail:
        - |
          ping 10.0.5.10
          restart vpn
          ganti dns
        - |
          ping -c1 -M do -s 1472 10.0.5.10
          pmtu blackhole
          iptables -A INPUT -p icmp -j DROP
          clamp-mss-to-pmtu
        - |
          ping -M do -s 1472 10.0.5.10
          naikkan mtu ke 9000
    source: https://www.rfc-editor.org/rfc/rfc2923

  - id: c03
    tier: C
    type: scenario
    objective: table
    prompt: |
      Host A `10.60.1.10` tidak bisa menjangkau B `10.60.2.20` di subnet lain (`ping` 100% loss). Tulis langkah
      troubleshooting berurutan dari sisi A sampai B (satu perintah/poin per baris), dan apa yang dicari di setiap langkah.
    checklist:
      - id: route-get
        label: ip route get untuk melihat next hop yang dipilih
        required: true
        patterns: ['ip\s+route\s+get', 'ip\s+r\s+g\b']
      - id: neigh
        label: ip neigh untuk cek MAC gateway (REACHABLE vs INCOMPLETE)
        required: true
        patterns: ['ip\s+n(eigh)?\b', 'arp\s+-n']
      - id: traceroute
        label: traceroute untuk melihat di hop mana berhenti
        patterns: ['traceroute', 'tracepath', 'mtr']
      - id: forward
        label: Cek ip_forward / FORWARD di router
        patterns: ['ip_forward', 'forward']
      - id: return
        label: Cek route balik di B
        required: true
        patterns: ['(route balik|return route|jalur balik|balik)', 'ip\s+route\s+get\s+10\.60\.1\.10']
      - id: tcpdump
        label: tcpdump di B/router untuk melihat apakah request sampai
        patterns: ['tcpdump']
      - id: no-destroy
        label: Tidak flush routing / mematikan firewall total
        forbid: true
        patterns: ['ip\s+route\s+flush', 'iptables\s+-F', 'ufw\s+disable']
    pass_score: 0.5
    solution: |
      ```bash
      # di A
      ip route get 10.60.2.20          # next hop benar? (via 10.60.1.254, bukan default 10.60.1.1)
      ip neigh show 10.60.1.254        # REACHABLE = L2 ke router OK; INCOMPLETE/FAILED = masalah L2
      traceroute -n 10.60.2.20         # berhenti di mana? semua * → router tidak meneruskan
      # di router
      cat /proc/sys/net/ipv4/ip_forward   # harus 1
      iptables -S FORWARD                 # policy DROP? (Docker men-set DROP)
      tcpdump -nn -i any icmp and host 10.60.2.20   # request masuk & keluar?
      # di B
      tcpdump -nn -i eth0 icmp         # request sampai? reply keluar?
      ip route get 10.60.1.10          # route balik lewat router, bukan default lain?
      ```
      Setiap kegagalan di lab punya tanda yang berbeda:
      - Route salah → `ip route get` menunjuk gateway Docker, lalu diblokir isolasi.
      - Forwarding mati → traceroute penuh `*`.
      - Host tidak ada → `INCOMPLETE`.
      - Route balik hilang → tcpdump B melihat request, tetapi balasan pergi lewat `10.60.2.1`.

      Bekerja dari satu ujung ke ujung lain dan mencari di hop mana paket berhenti lebih cepat daripada mencoba
      perbaikan acak.
    tests:
      pass:
        - |
          ip route get 10.60.2.20
          ip neigh show 10.60.1.254
          traceroute -n 10.60.2.20
          cek ip_forward di router
          tcpdump -nn -i eth0 icmp di B
          cek route balik di B: ip route get 10.60.1.10
        - |
          ip r g 10.60.2.20
          ip n
          route balik B tidak ada
      fail:
        - |
          ping 10.60.2.20
          ip route flush
          reboot
        - |
          ip route get 10.60.2.20
          ufw disable
          ip neigh
          route balik
        - |
          traceroute 10.60.2.20
          ganti dns
    source: https://man7.org/linux/man-pages/man8/ip-route.8.html

---

# Routing, ARP & MTU

Modul 01 menjelaskan bagaimana prefix menentukan siapa "tetangga langsung". Modul ini membahas bagaimana paket
mencapai yang **bukan** tetangga: kernel memilih route, router meneruskan, ARP mencari MAC next hop, dan setiap link
membatasi ukuran paket. Semua dibuktikan di topologi tiga container:

```mermaid
flowchart LR
    A["host A<br/>10.60.1.10<br/>MTU 1500"] --- R1["eth0 10.60.1.254<br/>(MTU 1400 di uji MTU)"]
    subgraph router["router (ip_forward=1)"]
      R1
      R2["eth1 10.60.2.254"]
    end
    R2 --- B["host B (nginx)<br/>10.60.2.20"]
```

> [!NOTE]
> Lab: Docker Desktop, container alpine dengan `NET_ADMIN`/`NET_RAW`, ditambah `iproute2`, `iputils`, `tcpdump`,
> `iptables`. Perilaku kernel (routing, ARP, PMTU, TCPMSS) sama dengan server Linux biasa.

## 1. Tabel routing dan longest prefix match

```text
default via 10.60.1.1 dev eth0
10.60.0.0/16 via 10.60.1.1 dev eth0
10.60.1.0/24 dev eth0 proto kernel scope link src 10.60.1.10     ← connected (modul 01)
10.60.2.0/24 via 10.60.1.254 dev eth0                            ← static
unreachable 198.51.100.0/24
blackhole 203.0.113.0/24
```

| Tujuan | Cocok dengan | Menang | Lab `ip route get` |
|---|---|---|---|
| 10.60.2.20 | /0, /16, /24 | **/24** | `via 10.60.1.254` |
| 10.60.9.9 | /0, /16 | **/16** | `via 10.60.1.1` |
| 198.51.100.5 | unreachable /24 | — | `Host is unreachable` |
| 203.0.113.5 | blackhole /24 | — | `Invalid argument` |

```bash
ip route add 10.60.2.0/24 via 10.60.1.254
ip route del 10.60.2.0/24
ip route get 10.60.2.20
```

## 2. Linux sebagai router

```bash
sysctl -w net.ipv4.ip_forward=1                                   # host biasa
echo 'net.ipv4.ip_forward = 1' > /etc/sysctl.d/90-router.conf     # permanen
docker run --sysctl net.ipv4.ip_forward=1 ...                     # container: hanya saat create
```

| Kondisi (lab) | Hasil ping A → B |
|---|---|
| Tanpa route statis (lewat gateway Docker) | 100% loss (isolasi antar network Docker) |
| Route di A, router `ip_forward=0` | 100% loss, traceroute penuh `*` |
| Router `ip_forward=1`, B tanpa route balik | Berhasil, tetapi balasan lewat `10.60.2.1` (**asimetris**) |
| Route di kedua sisi | Berhasil, `ttl=63`, dua hop |

> [!WARNING]
> **Selalu pasang route balik.** Jalur asimetris bisa terlihat bekerja (di lab tetap 0% loss), tetapi firewall
> stateful, NAT, dan filter `rp_filter` di jalur nyata hanya melihat setengah percakapan, lalu membuang sisanya.

## 3. TTL dan traceroute

```text
traceroute -n 10.60.2.20
 1  10.60.1.254  0.007 ms
 2  10.60.2.20   0.002 ms
ping -t1 10.60.2.20  →  From 10.60.1.254 icmp_seq=1 Time to live exceeded
```

Setiap router mengurangi TTL satu (lab: 64 → 63). Saat TTL mencapai 0, router membalas dengan ICMP Time Exceeded,
dan traceroute memanfaatkan itu untuk memetakan jalur. Baris `*` berarti hop tidak membalas, entah karena
forwarding mati atau karena router sengaja diam.

## 4. ARP

```text
be:c0:d8:b0:23:67 > ff:ff:ff:ff:ff:ff, ARP, Request who-has 10.60.1.254 tell 10.60.1.10
76:77:32:80:18:05 > be:c0:d8:b0:23:67, ARP, Reply 10.60.1.254 is-at 76:77:32:80:18:05
```

- Request dikirim broadcast, reply dikirim unicast. ARP hanya bekerja dalam satu segmen L2.
- Untuk tujuan di subnet lain, MAC yang dicari adalah MAC **next hop**. Di lab, `ip neigh` A hanya berisi router,
  tidak pernah B.
- Host yang tidak ada → `INCOMPLETE`, lalu `FAILED`. Tidak ada paket IP yang pernah terkirim.

## 5. MTU dan Path MTU Discovery

| Lapisan | Rumus | MTU 1500 | MTU 1400 |
|---|---|---|---|
| `ping -s` maksimum | MTU − 20 − 8 | 1472 | 1372 |
| MSS TCP | MTU − 20 − 20 | 1460 | 1360 |

```text
ping -M do -s 1472 10.60.1.10  →  From 10.60.2.254: Frag needed and DF set (mtu = 1400)
ip route get 10.60.1.10        →  cache expires 599sec mtu 1400                 ← PMTUD bekerja
```

### PMTU blackhole

```mermaid
sequenceDiagram
    participant B as B (server)
    participant R as Router (link 1400)
    participant A as A (client)
    A->>B: SYN (MSS 1460)
    B->>A: SYN-ACK
    A->>B: GET big.bin
    B->>R: segmen 1500 + DF
    R--xB: ICMP frag-needed (DIBLOKIR)
    Note over B,A: B terus mengirim ulang 1500 → koneksi menggantung
```

| Lab | `small.txt` | `big.bin` (3 MB) |
|---|---|---|
| ICMP type 3 code 4 diblokir di router | OK | **FAILED/HANG** |
| \+ `TCPMSS --clamp-mss-to-pmtu` | OK | OK |
| \+ `TCPMSS --set-mss 1360` | OK | OK |

```bash
iptables -t mangle -A FORWARD -p tcp --tcp-flags SYN,RST SYN -j TCPMSS --clamp-mss-to-pmtu
```

Perbaikan terbaik: jangan blokir ICMP type 3 dan 11. MSS clamping di gateway VPN/tunnel adalah jaring pengaman
yang wajib, karena kamu tidak mengontrol firewall di tengah internet.

## 6. Ringkasan

| Gejala | Kemungkinan | Cek |
|---|---|---|
| `Network/Host is unreachable` seketika | Tidak ada route / route unreachable | `ip route get` |
| Timeout, `ip neigh` gateway INCOMPLETE | Masalah L2 ke gateway | kabel/VLAN/bridge |
| traceroute berhenti di router | Forwarding/firewall router | `ip_forward`, chain FORWARD |
| Request sampai, balasan tidak | Route balik / asimetris | `ip route get` di tujuan, tcpdump |
| Kecil lancar, besar macet | PMTU blackhole | `ping -M do -s`, MSS clamping |
