---
format: 1
id: net-tcpip-addressing
course:
  slug: jaringan
  title: Jaringan
  description: Konsep jaringan untuk DevOps — TCP/IP, subnetting, routing, TCP/UDP, DNS, HTTP, TLS, proxy, firewall, VPN, dan troubleshooting.
title: Model TCP/IP & Pengalamatan IP
summary: Lapisan TCP/IP dilihat langsung di paket, IPv4 dan CIDR, menghitung subnet (network, broadcast, jumlah host), rentang alamat khusus, merencanakan subnet tanpa bentrok (termasuk dengan network Docker), dan dasar IPv6.
order: 1
version: 1
status: verified
updated: '2026-11-23'
tested_on:
  - Docker Desktop (Windows) — Docker Engine 29.4.1, container alpine:3.20 (busybox ipcalc, iproute2, tcpdump)
  - Python 3.13 modul ipaddress (verifikasi semua hitungan subnet)
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: layers
    text: Memahami lapisan model TCP/IP dan header-nya di paket nyata
  - id: ipv4
    text: Membaca alamat IPv4, netmask, dan notasi CIDR
  - id: subnet
    text: Menghitung network, broadcast, rentang host, dan membagi subnet
  - id: special
    text: Mengenali rentang alamat khusus (private, loopback, link-local, CGNAT, dokumentasi)
  - id: planning
    text: Merencanakan subnet tanpa bentrok, termasuk dengan Docker dan VPN
  - id: ipv6
    text: Memahami dasar IPv6 (notasi, prefix, link-local)
sources:
  - title: RFC 1122 — Requirements for Internet Hosts (lapisan TCP/IP)
    url: https://www.rfc-editor.org/rfc/rfc1122
  - title: RFC 4632 — Classless Inter-domain Routing (CIDR)
    url: https://www.rfc-editor.org/rfc/rfc4632
  - title: RFC 1918 — Address Allocation for Private Internets
    url: https://www.rfc-editor.org/rfc/rfc1918
  - title: RFC 6598 — Shared Address Space (100.64.0.0/10)
    url: https://www.rfc-editor.org/rfc/rfc6598
  - title: RFC 3927 — Dynamic Configuration of IPv4 Link-Local Addresses (169.254.0.0/16)
    url: https://www.rfc-editor.org/rfc/rfc3927
  - title: RFC 5737 — IPv4 Address Blocks Reserved for Documentation
    url: https://www.rfc-editor.org/rfc/rfc5737
  - title: RFC 3021 — Using 31-Bit Prefixes on IPv4 Point-to-Point Links
    url: https://www.rfc-editor.org/rfc/rfc3021
  - title: IANA IPv4 Special-Purpose Address Registry
    url: https://www.iana.org/assignments/iana-ipv4-special-registry/
  - title: RFC 5952 — A Recommendation for IPv6 Address Text Representation
    url: https://www.rfc-editor.org/rfc/rfc5952
  - title: RFC 4291 — IP Version 6 Addressing Architecture
    url: https://www.rfc-editor.org/rfc/rfc4291
  - title: Python docs — ipaddress
    url: https://docs.python.org/3/library/ipaddress.html
  - title: Docker Docs — daemon default-address-pools
    url: https://docs.docker.com/reference/cli/dockerd/#daemon-configuration-file
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: layers
    prompt: Di model TCP/IP, protokol **IP** berada di lapisan apa?
    answers: ['internet', 'network', 'layer 3', 'lapisan internet', 'network layer']
    patterns: ['^(lapisan |layer )?(internet|network|3|jaringan)( layer)?$']
    tests:
      pass: ['Internet', 'layer 3', 'network layer']
      fail: ['transport', 'link', 'application', 'layer 4']
    explanation: 'Model TCP/IP punya 4 lapisan: Link (Ethernet/Wi-Fi, alamat MAC), Internet (IP, routing antar network), Transport (TCP/UDP, port), dan Application (HTTP, DNS, SSH). Di model OSI, IP berada di layer 3. Istilah "L3/L4/L7" (mis. load balancer L4 vs L7) berasal dari penomoran OSI.'
    source: https://www.rfc-editor.org/rfc/rfc1122

  - id: a02
    tier: A
    type: text
    objective: layers
    prompt: Alamat **MAC** (mis. `fe:e8:af:fb:f5:4c`) dipakai di lapisan TCP/IP yang mana?
    answers: ['link', 'link layer', 'data link', 'layer 2']
    patterns: ['^(lapisan |layer )?(link|data ?link|2|network access)( layer)?$']
    tests:
      pass: ['Link', 'data link', 'layer 2']
      fail: ['internet', 'transport', 'layer 3', 'application']
    explanation: 'Di lab, tcpdump `-e` memperlihatkan setiap frame membawa MAC sumber dan tujuan (`fe:e8:af:fb:f5:4c > 42:c9:33:05:d9:98`) sebelum header IP. MAC hanya berlaku dalam satu segmen (satu "kabel"/bridge), sedangkan IP berlaku end-to-end melewati router.'
    source: https://www.rfc-editor.org/rfc/rfc1122

  - id: a03
    tier: A
    type: text
    objective: ipv4
    prompt: Netmask dalam notasi desimal untuk prefix **/26**?
    answers: ['255.255.255.192']
    tests:
      fail: ['255.255.255.0', '255.255.255.128', '255.255.255.224', '255.255.192.0']
    explanation: '/26 = 26 bit pertama bernilai 1. Tiga oktet penuh (24 bit) + 2 bit di oktet keempat (`11000000` = 128+64 = **192**). Di lab, `ipcalc -m 192.168.10.77/26` → `NETMASK=255.255.255.192`. Nilai oktet yang mungkin: 128, 192, 224, 240, 248, 252, 254, 255.'
    source: https://www.rfc-editor.org/rfc/rfc4632

  - id: a04
    tier: A
    type: text
    objective: subnet
    prompt: Alamat **network** untuk host `192.168.10.77/26`?
    answers: ['192.168.10.64', '192.168.10.64/26']
    tests:
      fail: ['192.168.10.0', '192.168.10.77', '192.168.10.127', '192.168.10.65']
    explanation: 'Ukuran blok /26 = 2^(32−26) = 64, jadi network dimulai di kelipatan 64: 0, **64**, 128, 192. 77 jatuh di blok 64–127. Di lab: `NETWORK=192.168.10.64`.'
    source: https://www.rfc-editor.org/rfc/rfc4632

  - id: a05
    tier: A
    type: text
    objective: subnet
    prompt: Alamat **broadcast** untuk `192.168.10.77/26`?
    answers: ['192.168.10.127']
    tests:
      fail: ['192.168.10.255', '192.168.10.128', '192.168.10.126', '192.168.10.64']
    explanation: 'Broadcast = alamat terakhir di blok, yaitu network + ukuran blok − 1 = 64 + 64 − 1 = **127**. Di lab: `BROADCAST=192.168.10.127`. Host yang bisa dipakai adalah .65–.126.'
    source: https://www.rfc-editor.org/rfc/rfc4632

  - id: a06
    tier: A
    type: text
    objective: subnet
    prompt: Berapa alamat **host yang bisa dipakai** di subnet /26?
    answers: ['62']
    tests:
      fail: ['64', '63', '30', '126']
    explanation: '2^6 = 64 alamat, dikurangi alamat network dan broadcast = **62**. Python `ipaddress` di lab: `total 64 usable 62 first 192.168.10.65 last 192.168.10.126`. Rumus umum: 2^(32−prefix) − 2, kecuali /31 dan /32 (tier B).'
    source: https://docs.python.org/3/library/ipaddress.html

  - id: a07
    tier: A
    type: text
    objective: subnet
    prompt: Alamat **network** untuk `10.20.30.40/20`?
    answers: ['10.20.16.0', '10.20.16.0/20']
    tests:
      fail: ['10.20.0.0', '10.20.30.0', '10.20.32.0', '10.0.0.0']
    explanation: '/20 → oktet ketiga punya 4 bit network (mask `255.255.240.0`), dengan ukuran blok 16 di oktet ketiga: 0, **16**, 32... 30 jatuh di blok 16–31. Di lab: `NETWORK=10.20.16.0`, `BROADCAST=10.20.31.255`, 4094 host.'
    source: https://www.rfc-editor.org/rfc/rfc4632

  - id: a08
    tier: A
    type: command
    objective: subnet
    prompt: Dengan `ipcalc` (busybox), tampilkan alamat **network** dan **broadcast** untuk `192.168.10.77/26`.
    answers: ['ipcalc -nb 192.168.10.77/26']
    patterns: ['^ipcalc (-nb|-bn|-n -b|-b -n|--network --broadcast|--broadcast --network) 192\.168\.10\.77/26$']
    tests:
      pass: ['ipcalc -bn 192.168.10.77/26', 'ipcalc -n -b 192.168.10.77/26']
      fail: ['ipcalc 192.168.10.77/26', 'ipcalc -m 192.168.10.77/26', 'ipcalc -nb 192.168.10.77']
    explanation: 'Busybox `ipcalc`: `-n` network, `-b` broadcast, `-m` netmask, `-p` prefix. Di lab, `ipcalc -nbmp 192.168.10.77/26` mencetak keempatnya. `ipcalc` versi distro lain (Debian/RHEL) punya format output berbeda. Python `ipaddress` bisa jadi verifikasi kedua.'
    source: https://docs.python.org/3/library/ipaddress.html

  - id: a09
    tier: A
    type: text
    objective: special
    prompt: Rentang private RFC 1918 yang dimulai `172.16.0.0` memakai prefix berapa?
    answers: ['/12', '12', '172.16.0.0/12']
    tests:
      fail: ['/16', '/8', '/24', '/20']
    explanation: 'Tiga rentang RFC 1918: `10.0.0.0/8`, `172.16.0.0/12` (172.16.0.0–**172.31**.255.255), dan `192.168.0.0/16`. Banyak orang mengira "172.x semuanya private", padahal salah (soal berikutnya).'
    source: https://www.rfc-editor.org/rfc/rfc1918

  - id: a10
    tier: A
    type: text
    objective: special
    prompt: Apakah `172.32.0.1` termasuk alamat private RFC 1918? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab, Python: `172.31.255.254 private True`, `172.32.0.1 private False global True`. 172.32.0.0 berada di luar /12. Aturan firewall seperti "izinkan 172.0.0.0/8 karena internal" ikut membuka alamat publik milik orang lain.'
    source: https://www.rfc-editor.org/rfc/rfc1918

  - id: a11
    tier: A
    type: text
    objective: special
    prompt: Server mendapat alamat `169.254.10.1`. Jenis alamat apa ini, dan biasanya menandakan apa?
    answers: ['link-local, DHCP gagal']
    patterns: ['link[- ]?local', 'apipa', 'dhcp']
    tests:
      pass: ['link-local', 'APIPA, dhcp gagal', 'tidak dapat IP dari dhcp']
      fail: ['private', 'loopback', 'publik', 'cgnat']
    explanation: 'Di lab, Python: `169.254.10.1 linklocal True`. Host memberi dirinya alamat 169.254.0.0/16 bila tidak mendapat jawaban DHCP, dan alamat ini tidak bisa di-route. Pengecualian penting di cloud: `169.254.169.254` adalah endpoint **metadata** instance (AWS/GCP/Azure), target favorit serangan SSRF.'
    source: https://www.rfc-editor.org/rfc/rfc3927

  - id: a12
    tier: A
    type: text
    objective: special
    prompt: Rentang `100.64.0.0/10` dipakai untuk apa?
    answers: ['CGNAT / shared address space']
    patterns: ['(cg-?nat|carrier[- ]grade|shared address|nat isp|isp nat)']
    tests:
      pass: ['CGNAT', 'carrier-grade NAT', 'shared address space']
      fail: ['private', 'loopback', 'multicast', 'dokumentasi']
    explanation: 'RFC 6598: alamat antara router pelanggan dan NAT milik ISP. Di lab, Python: `100.64.1.1 private False global False`, jadi bukan private RFC 1918 dan juga bukan publik. Bila "IP publik" router rumahmu 100.64.x.x, kamu berada di belakang CGNAT dan tidak bisa menerima koneksi masuk (port forwarding tidak bekerja). Tailscale juga memakai rentang ini.'
    source: https://www.rfc-editor.org/rfc/rfc6598

  - id: a13
    tier: A
    type: text
    objective: special
    prompt: '`/etc/resolv.conf` berisi `nameserver 127.0.0.53`. Alamat jenis apa itu?'
    answers: ['loopback']
    patterns: ['(loopback|localhost|lokal mesin)']
    tests:
      pass: ['loopback (localhost)']
      fail: ['private', 'link-local', 'publik', 'dns google']
    explanation: 'Seluruh `127.0.0.0/8` adalah loopback (lab: `127.0.0.53 loopback True`), bukan hanya 127.0.0.1. 127.0.0.53 adalah stub resolver systemd-resolved (modul Linux Networking). Di container, `127.0.0.11` adalah DNS embedded Docker.'
    source: https://www.iana.org/assignments/iana-ipv4-special-registry/

  - id: a14
    tier: A
    type: text
    objective: ipv6
    prompt: Tulis `2001:0db8:0000:0000:0000:ff00:0042:8329` dalam bentuk **ringkas** standar.
    answers: ['2001:db8::ff00:42:8329']
    tests:
      fail: ['2001:db8:0:0:0:ff00:42:8329', '2001:db8::ff00::42:8329', '2001:0db8::ff00:0042:8329', '2001:db8:ff00:42:8329']
    explanation: 'Aturan RFC 5952: buang nol di depan setiap grup (`0db8` → `db8`, `0042` → `42`), dan ganti **satu** deretan grup nol terpanjang dengan `::`. Di lab, Python → `2001:db8::ff00:42:8329`. `::` hanya boleh muncul sekali, karena bila dua kali, panjang setiap bagian jadi ambigu.'
    source: https://www.rfc-editor.org/rfc/rfc5952

  - id: a15
    tier: A
    type: text
    objective: ipv6
    prompt: Prefix alamat **link-local** IPv6 (yang otomatis ada di setiap interface)?
    answers: ['fe80::/10', 'fe80::/64', 'fe80::']
    patterns: ['^fe80(::)?(/(10|64))?$']
    tests:
      pass: ['fe80::/10', 'fe80']
      fail: ['fd00::/8', '::1', '2001:db8::/32', 'ff00::/8']
    explanation: 'Di lab, container di network IPv6 Docker punya dua alamat: `fd00:50::2/64 scope global` dan `fe80::44a7:2fff:fefd:e20a/64 scope link`, dengan route `fe80::/64`. Blok yang dialokasikan adalah fe80::/10. Lainnya: `::1` loopback, `fd00::/8` ULA (setara private), dan `2001:db8::/32` dokumentasi.'
    source: https://www.rfc-editor.org/rfc/rfc4291

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: planning
    prompt: Network Docker dibuat dengan `--subnet 10.50.0.0/29`. Berapa container maksimal yang bisa terhubung?
    answers: ['5']
    tests:
      fail: ['6', '8', '7', '4']
    explanation: '/29 = 8 alamat. Dikurangi network (.0) dan broadcast (.7) tersisa 6 host, lalu Docker memakai satu untuk **gateway** (`gw=10.50.0.1`). Di lab: container 1–5 mendapat `.2`–`.6`, dan yang ke-6 gagal (soal berikutnya). Router/gateway selalu memakan satu alamat host di setiap subnet.'
    source: https://docs.docker.com/reference/cli/docker/network/create/

  - id: b02
    tier: B
    type: text
    objective: planning
    prompt: Pesan error dari Docker saat container ke-6 dijalankan di network /29 yang sudah penuh?
    answers: ['no available IPv4 addresses']
    patterns: ['no available ipv4 address', '(tidak ada|habis).*(ip|alamat)', '(ip|alamat).*(habis|penuh)']
    tests:
      pass: ['no available IPv4 addresses on this network''s address pools', 'alamat ip habis']
      fail: ['pool overlaps', 'network not found', 'port is already allocated']
    explanation: 'Di lab: `failed to set up container networking: no available IPv4 addresses on this network''s address pools: dj-n29`. Subnet terlalu kecil adalah masalah yang muncul belakangan (saat scaling), jadi pilih ukuran dengan cadangan. Di cloud, setiap subnet juga dipotong beberapa alamat cadangan provider (AWS: 5 per subnet).'
    source: https://docs.docker.com/reference/cli/docker/network/create/

  - id: b03
    tier: B
    type: text
    objective: subnet
    prompt: Berapa alamat yang bisa dipakai host di subnet **/31**?
    answers: ['2']
    tests:
      fail: ['0', '1', '4', '6']
    explanation: 'Menurut rumus 2^1 − 2 = 0, tetapi RFC 3021 mengizinkan /31 untuk link point-to-point **tanpa** alamat network/broadcast. Di lab, Python: `10.0.0.0/31 total 2 usable 2`. /32 berarti tepat satu alamat (host route), umum untuk loopback router atau IP mengambang.'
    source: https://www.rfc-editor.org/rfc/rfc3021

  - id: b04
    tier: B
    type: text
    objective: subnet
    prompt: '`10.10.0.0/22` dibagi menjadi subnet **/24**. Apa subnet /24 yang **terakhir**?'
    answers: ['10.10.3.0/24', '10.10.3.0']
    tests:
      fail: ['10.10.4.0/24', '10.10.2.0/24', '10.10.0.255/24', '10.10.22.0/24']
    explanation: 'Selisih prefix 24 − 22 = 2 bit → 2^2 = **4** subnet: `10.10.0.0/24` sampai `10.10.3.0/24` (lab: `subnets(new_prefix=24)`). Kebalikannya: empat /24 yang berurutan dan rata di batas /22 bisa diringkas (summarize) menjadi satu route /22.'
    source: https://docs.python.org/3/library/ipaddress.html

  - id: b05
    tier: B
    type: text
    objective: subnet
    prompt: Apakah `192.168.10.130` berada di subnet `192.168.10.64/26`? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: '`192.168.10.64/26` = .64–.127. Di lab, Python: `.70 in net → True`, `.130 in net → False`. Dua host yang tampak "satu network" (sama-sama 192.168.10.x) bisa berada di subnet berbeda, sehingga komunikasinya harus lewat router. Bila router tidak ada, keduanya tidak bisa bicara sama sekali.'
    source: https://docs.python.org/3/library/ipaddress.html

  - id: b06
    tier: B
    type: text
    objective: planning
    prompt: Saat `10.50.0.0/29` sudah dipakai network Docker, `docker network create --subnet 10.50.0.0/28 ...` gagal dengan pesan apa?
    answers: ['Pool overlaps with other one on this address space']
    patterns: ['overlap', 'tumpang tindih', 'bentrok']
    tests:
      pass: ['pool overlaps', 'subnet tumpang tindih']
      fail: ['no available ipv4 addresses', 'network already exists', 'permission denied']
    explanation: 'Di lab: `invalid pool request: Pool overlaps with other one on this address space`. /28 (.0–.15) mencakup /29 (.0–.7). Docker menolak overlap antar network-nya sendiri, tetapi **tidak** mengecek bentrok dengan LAN kantor atau VPN (studi kasus C).'
    source: https://docs.docker.com/reference/cli/docker/network/create/

  - id: b07
    tier: B
    type: text
    objective: ipv4
    prompt: 'Container `10.51.0.2/24` bisa ping `10.51.0.10`. Alamatnya lalu diganti menjadi `10.51.0.2/32`. Hasil `ping 10.51.0.10` sekarang?'
    answers: ['Network unreachable']
    patterns: ['(network |net(work)? )?unreachable', 'tidak (bisa|dapat) dijangkau', 'gagal']
    tests:
      pass: ['ping: sendto: Network unreachable', 'unreachable']
      fail: ['berhasil', 'reply normal', 'timeout biasa']
    explanation: 'Di lab: `ping: sendto: Network unreachable`, dan `ip route` kosong. Prefix menentukan siapa yang dianggap "tetangga langsung". /32 berarti tidak ada tetangga, sehingga connected route hilang, dan default route (yang gateway-nya ada di subnet itu) ikut hilang. Salah prefix saat konfigurasi IP statis (netplan/ifcfg) adalah penyebab klasik server "tidak bisa ke mana-mana".'
    source: https://www.rfc-editor.org/rfc/rfc1122

  - id: b08
    tier: B
    type: text
    objective: ipv4
    prompt: '`ip route` di container menampilkan `10.50.0.0/29 dev eth0 scope link src 10.50.0.2`. Dari mana route ini berasal?'
    answers: ['dibuat otomatis oleh kernel dari alamat + prefix interface (connected route)']
    patterns: ['(otomatis|kernel|connected|directly|prefix|dari alamat|dari ip)']
    tests:
      pass: ['connected route dari prefix alamat eth0', 'kernel membuatnya otomatis saat alamat ditambahkan']
      fail: ['dari dhcp server', 'ditambah manual oleh admin', 'dari dns']
    explanation: 'Begitu alamat `10.50.0.2/29` dipasang, kernel otomatis menambah route ke `10.50.0.0/29` lewat `eth0` (`scope link` = langsung, tanpa gateway). Di lab, saat prefix diubah ke /28, route-nya otomatis menjadi `10.51.0.0/28`. Semua di luar route ini dikirim ke `default via 10.50.0.1`.'
    source: https://man7.org/linux/man-pages/man8/ip-route.8.html

  - id: b09
    tier: B
    type: text
    objective: layers
    prompt: 'Frame TCP SYN di lab panjangnya 74 byte: header Ethernet + IP (20) + TCP dengan opsi (40), tanpa payload. Berapa byte header Ethernet?'
    answers: ['14']
    tests:
      fail: ['18', '20', '8', '6']
    explanation: '74 − 20 − 40 = **14** byte: MAC tujuan (6) + MAC sumber (6) + EtherType (2). Di lab, paket HTTP GET: `length 152 ... length 86` = 14 Ethernet + 20 IP + 32 TCP (dengan timestamp) + 86 byte HTTP. Setiap lapisan membungkus lapisan di atasnya (enkapsulasi).'
    source: https://www.rfc-editor.org/rfc/rfc1122

  - id: b10
    tier: B
    type: text
    objective: layers
    prompt: Dengan MTU Ethernet 1500, berapa **MSS** (payload TCP maksimum per segmen) yang diumumkan host IPv4 dengan header standar?
    answers: ['1460']
    tests:
      fail: ['1500', '1480', '1472', '1440']
    explanation: '1500 − 20 (IP) − 20 (TCP) = **1460**. Di lab, SYN membawa `options [mss 1460,...]`. Bila ada enkapsulasi tambahan (VPN, VXLAN overlay Kubernetes), MTU efektif mengecil. Kalau MSS/MTU tidak disesuaikan, koneksi kecil lancar tetapi transfer besar macet. Detail MTU dibahas di modul Routing.'
    source: https://www.rfc-editor.org/rfc/rfc879

  - id: b11
    tier: B
    type: text
    objective: layers
    prompt: Di header Ethernet, `ethertype 0x0800` menandakan payload frame adalah protokol apa?
    answers: ['IPv4']
    patterns: ['^(ip ?v?4|ip versi 4|internet protocol (version )?4)$']
    tests:
      pass: ['ipv4', 'ip v4']
      fail: ['ipv6', 'arp', 'tcp', 'ethernet']
    explanation: 'EtherType memberi tahu penerima cara membaca isi frame: `0x0800` IPv4, `0x0806` ARP, `0x86DD` IPv6. Di lab, setiap frame HTTP: `ethertype IPv4 (0x0800)`. Pola yang sama berulang di setiap lapisan: IP punya field "protocol" (6 = TCP, 17 = UDP), dan TCP punya port.'
    source: https://www.iana.org/assignments/ieee-802-numbers/

  - id: b12
    tier: B
    type: text
    objective: layers
    prompt: 'Di tcpdump `10.51.0.2.42794 > 10.51.0.10.80: Flags [S]`, angka `42794` adalah apa?'
    answers: ['port sumber ephemeral milik klien']
    patterns: ['(port )?(sumber|source|asal|klien|client|ephemeral|acak|sementara)']
    tests:
      pass: ['source port', 'port ephemeral klien', 'port acak sisi client']
      fail: ['nomor sequence', 'pid', 'port server', 'ukuran paket']
    explanation: 'Format tcpdump: `IP.port`. 80 adalah port server (nginx), dan 42794 adalah port **ephemeral** yang dipilih kernel klien dari rentang `ip_local_port_range` (umumnya 32768–60999). Kombinasi IP+port sumber dan tujuan (4-tuple) yang mengidentifikasi satu koneksi TCP.'
    source: https://man7.org/linux/man-pages/man7/ip.7.html

  - id: b13
    tier: B
    type: text
    objective: planning
    prompt: Nama setelan di `daemon.json` untuk mengubah rentang subnet yang otomatis dipakai Docker saat membuat network (default dari 172.17.0.0/16 dst.)?
    answers: ['default-address-pools']
    tests:
      fail: ['bip', 'fixed-cidr', 'subnet', 'address-pools']
    explanation: 'Di lab, bridge default `172.17.0.0/16`, dan network baru diambil berurutan dari 172.18+, 172.19+, dst. Rentang itu berada di dalam 172.16.0.0/12 yang sering dipakai kantor/VPN. Contoh: `"default-address-pools": [{"base": "10.250.0.0/16", "size": 24}]`. `bip` hanya mengatur bridge `docker0`.'
    source: https://docs.docker.com/reference/cli/dockerd/#daemon-configuration-file

  - id: b14
    tier: B
    type: text
    objective: ipv6
    prompt: Prefix standar untuk satu subnet LAN IPv6 (yang dibutuhkan SLAAC)?
    answers: ['/64', '64']
    tests:
      fail: ['/48', '/56', '/128', '/24']
    explanation: 'Di lab, network Docker IPv6 `fd00:50::/64` dan link-local `fe80::/64`. 64 bit terakhir adalah interface ID, sehingga satu /64 berisi 2^64 = 18.446.744.073.709.551.616 alamat (lab: Python). Di IPv6 kamu tidak menghemat alamat dalam subnet. Yang direncanakan adalah jumlah subnet (/48 atau /56 per site).'
    source: https://www.rfc-editor.org/rfc/rfc4291

  - id: b15
    tier: B
    type: text
    objective: special
    prompt: Rentang `192.0.2.0/24` (TEST-NET-1) diperuntukkan untuk apa?
    answers: ['dokumentasi dan contoh']
    patterns: ['(dokumentasi|documentation|contoh|example|test-?net)']
    tests:
      pass: ['dokumentasi', 'contoh di dokumentasi']
      fail: ['private lan', 'loopback', 'cgnat', 'multicast']
    explanation: 'RFC 5737: `192.0.2.0/24`, `198.51.100.0/24`, `203.0.113.0/24` (dan `2001:db8::/32` untuk IPv6) hanya untuk contoh di dokumentasi dan tidak pernah di-route. Pakai rentang ini di materi atau tiket agar contoh tidak menunjuk server orang lain. Catatan lab: Python `ipaddress` menandainya `is_private True`, karena yang dicek adalah "bukan global", bukan khusus RFC 1918.'
    source: https://www.rfc-editor.org/rfc/rfc5737

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: planning
    prompt: |
      Kamu merancang VPC baru `10.20.0.0/16` di 2 availability zone. Tiap zone butuh subnet **public** (load balancer,
      ≤ 50 host), **private** (app, hingga 400 host), dan **db** (≤ 20 host). Kantor memakai `10.0.0.0/16`, VPN `10.8.0.0/24`.
      Tulis rencana subnetnya (satu subnet per baris, CIDR + peran) dan catatan pentingnya.
    checklist:
      - id: inside
        label: Semua subnet di dalam 10.20.0.0/16
        required: true
        patterns: ['10\.20\.\d{1,3}\.\d{1,3}/\d{2}']
      - id: private-size
        label: Subnet private cukup untuk 400 host (/23 atau lebih besar)
        required: true
        patterns: ['10\.20\.\d{1,3}\.\d{1,3}/(23|22|21|20)\b']
      - id: public-size
        label: Public ≥ /26 (≥ 62 host)
        patterns: ['10\.20\.\d{1,3}\.\d{1,3}/(26|25|24)\b']
      - id: two-az
        label: Dua zone (a/b) masing-masing punya set subnet
        patterns: ['(az|zone)[ \t-]*(b|2)', '\b[a-z]{2}-[a-z]+-\d[ab]\b', 'zone b']
      - id: reserve
        label: Menyisakan ruang cadangan / ukuran dengan cadangan
        patterns: ['(cadangan|sisa|reserve|spare|pertumbuhan|growth|ruang kosong)']
      - id: no-overlap
        label: Menyebut tidak overlap dengan kantor/VPN (dan Docker 172.17/16)
        patterns: ['(overlap|bentrok|tumpang)', '172\.17']
      - id: office-overlap
        label: Tidak memakai rentang kantor 10.0.x.x / VPN 10.8.0.x
        forbid: true
        patterns: ['(public|private|db)[^\n]*\b10\.(0|8)\.\d{1,3}\.\d{1,3}/\d{1,2}', '\b10\.(0|8)\.\d{1,3}\.\d{1,3}/\d{1,2}[^\n]*(public|private|db)']
    pass_score: 0.6
    solution: |
      ```text
      zone a  public   10.20.0.0/24     (254 host; LB butuh ≤50 → ruang tumbuh)
      zone a  db       10.20.1.0/26     (62 host)
      zone a  private  10.20.16.0/20    (4094 host; app 400 → cadangan untuk autoscaling)
      zone b  public   10.20.2.0/24
      zone b  db       10.20.1.64/26
      zone b  private  10.20.32.0/20
      cadangan: 10.20.48.0/20 ke atas untuk zone ketiga / layanan baru
      ```
      Catatan:
      - Pilih ukuran dengan cadangan. Lab /29 Docker: container ke-6 → `no available IPv4 addresses`. Di cloud, setiap
        subnet juga dipotong alamat cadangan provider.
      - Private /23 (510 host) sudah memenuhi 400. /20 dipilih agar tidak perlu memindah subnet saat app tumbuh.
      - Tidak ada overlap dengan kantor `10.0.0.0/16` dan VPN `10.8.0.0/24`, sehingga VPN site-to-site atau peering
        tetap bisa dibuat. Rentang yang bentrok tidak bisa di-route ke dua arah sekaligus.
      - Docker di VM memakai `172.17.0.0/16`+ secara default. Itu tidak bentrok dengan rencana ini, tetapi bisa
        bentrok dengan jaringan 172.16/12 lain. Set `default-address-pools` bila perlu.
      - Verifikasi rencana dengan Python `ipaddress` (`overlaps()`, `subnet_of()`) sebelum dibuat.
    tests:
      pass:
        - |
          zone a public 10.20.0.0/24
          zone a private 10.20.16.0/20
          zone a db 10.20.1.0/26
          zone b public 10.20.2.0/24
          zone b private 10.20.32.0/20
          zone b db 10.20.1.64/26
          sisanya cadangan, tidak overlap dengan kantor 10.0.0.0/16 dan vpn
        - |
          az-a: 10.20.0.0/26 public, 10.20.2.0/23 private, 10.20.0.64/27 db
          az-b: 10.20.1.0/26 public, 10.20.4.0/23 private, 10.20.1.64/27 db
      fail:
        - |
          zone a private 10.0.16.0/20
          zone b private 10.0.32.0/20
        - |
          zone a public 10.20.0.0/28
          zone a private 10.20.1.0/25
          zone a db 10.20.2.0/29
        - |
          10.20.0.0/24 public
          10.20.1.0/24 private
          10.8.0.0/24 db
    source: https://www.rfc-editor.org/rfc/rfc4632

  - id: c02
    tier: C
    type: scenario
    objective: ipv4
    prompt: |
      Setelah IP statis diubah di server baru (`10.30.5.20`, gateway `10.30.5.1`), server tidak bisa ping gateway maupun
      internet: `ping 10.30.5.1` → `Network unreachable`. Tulis langkah diagnosis dan perbaikannya (satu poin per baris).
    checklist:
      - id: addr
        label: Cek alamat + prefix dengan ip addr
        required: true
        patterns: ['ip\s+(-\d\s+)?(a|addr|address)\b']
      - id: route
        label: Cek tabel routing (connected + default)
        required: true
        patterns: ['ip\s+(-\d\s+)?(r|route)\b']
      - id: prefix
        label: Mengenali prefix salah (/32 atau tidak mencakup gateway)
        required: true
        patterns: ['/32', 'prefix', 'netmask', 'subnet.*(salah|tidak mencakup)', '(salah|keliru).*(prefix|mask|subnet)']
      - id: fix
        label: Memperbaiki ke prefix yang benar (mis. /24) di config permanen
        patterns: ['10\.30\.5\.20/2[0-9]', 'netplan', 'nmcli', 'ifcfg', 'systemd-networkd', 'interfaces']
      - id: verify
        label: Verifikasi ulang (ping gateway / ip route get)
        patterns: ['ping\s+10\.30\.5\.1', 'ip\s+route\s+get']
      - id: no-flush
        label: Tidak mematikan firewall / flush semua rule sebagai "solusi"
        forbid: true
        patterns: ['iptables\s+-F', 'ufw\s+disable', 'nft\s+flush\s+ruleset']
    pass_score: 0.6
    solution: |
      ```bash
      ip -4 addr show                      # inet 10.30.5.20/32 ?  ← tidak ada tetangga
      ip route                             # tidak ada "10.30.5.0/24 dev ..." dan tidak ada default
      ip route get 10.30.5.1               # RTNETLINK answers: Network is unreachable
      # perbaiki di konfigurasi permanen, mis. netplan:
      #   addresses: [10.30.5.20/24]
      #   routes: [{ to: default, via: 10.30.5.1 }]
      sudo netplan try                     # rollback otomatis bila koneksi SSH putus
      ping -c3 10.30.5.1 && ip route get 1.1.1.1
      ```
      Di lab, `/24` → ping berhasil. Setelah diganti `/32` → `ip route` kosong dan `ping: sendto: Network unreachable`.
      Kernel hanya mengirim langsung ke alamat yang tercakup prefix interface. Default route yang gateway-nya tidak
      ada di subnet mana pun tidak bisa dipakai.

      "Network unreachable" muncul seketika dan berasal dari host itu sendiri (tidak ada route), bukan dari firewall
      atau jaringan. Jadi mematikan firewall tidak akan membantu.

      `netplan try` (atau console provider) penting: salah konfigurasi jaringan lewat SSH bisa mengunci kamu di luar
      server.
    tests:
      pass:
        - |
          ip addr show eth0
          ip route
          prefix /32 salah, gateway tidak masuk subnet
          ubah netplan addresses 10.30.5.20/24
          netplan try
          ping 10.30.5.1
        - |
          ip -4 a
          ip r
          netmask keliru, harus /24
          ip route get 10.30.5.1
      fail:
        - |
          ufw disable
          iptables -F
          ip addr
          ip route
          prefix /32 salah
        - |
          ping 8.8.8.8
          restart server
        - |
          cek kabel
          ganti dns ke 1.1.1.1
    source: https://man7.org/linux/man-pages/man8/ip-route.8.html

  - id: c03
    tier: C
    type: scenario
    objective: planning
    prompt: |
      Developer melapor: saat Docker Desktop/Engine berjalan di laptopnya, ia **tidak bisa** mengakses server internal
      kantor `172.18.4.10` lewat VPN. Saat Docker dimatikan, akses normal. Tulis diagnosis dan perbaikan (satu poin per
      baris).
    checklist:
      - id: inspect
        label: Melihat subnet network Docker (docker network inspect / ls)
        required: true
        patterns: ['docker\s+network\s+(inspect|ls)']
      - id: route
        label: Melihat routing (ip route / route print / ip route get 172.18.4.10)
        required: true
        patterns: ['ip\s+route', 'route\s+print', 'ip\s+r\b']
      - id: overlap
        label: Menyimpulkan overlap 172.18.0.0/16 Docker dengan 172.16.0.0/12 kantor
        required: true
        patterns: ['(overlap|bentrok|tumpang)', '172\.18\.0\.0/16']
      - id: specific
        label: Menjelaskan route yang lebih spesifik/lokal menang (longest prefix match)
        patterns: ['(longest prefix|lebih spesifik|paling spesifik|prefix terpanjang|more specific)']
      - id: pools
        label: Mengatur default-address-pools ke rentang yang tidak dipakai kantor
        required: true
        patterns: ['default-address-pools']
      - id: recreate
        label: Membuat ulang network/project lama setelah perubahan
        patterns: ['(recreate|buat ulang|dibuat ulang|hapus network|network\s+rm|compose\s+down)']
      - id: no-prune
        label: Tidak menyarankan prune global
        forbid: true
        patterns: ['system\s+prune', 'network\s+prune', 'volume\s+prune']
    pass_score: 0.6
    solution: |
      ```text
      docker network ls ; docker network inspect -f '{{.Name}} {{range .IPAM.Config}}{{.Subnet}}{{end}}' $(docker network ls -q)
        → ada network compose "myproj_default" 172.18.0.0/16
      ip route get 172.18.4.10        → dev br-xxxx (bridge Docker), bukan tun0 (VPN)
      Diagnosis: 172.18.0.0/16 Docker berada di dalam 172.16.0.0/12 kantor; route /16 ke bridge lokal lebih spesifik
                 daripada route VPN /12 → paket ke 172.18.4.10 dikirim ke container, bukan ke kantor.
      Perbaikan: daemon.json  "default-address-pools": [{"base": "10.250.0.0/16", "size": 24}]
                 restart Docker, lalu docker compose down && docker compose up -d per project
                 agar network dibuat ulang dari pool baru (volume tidak terhapus tanpa -v).
      ```
      Temuan lab yang mendasarinya:
      - Bridge default `172.17.0.0/16`, dan network berikutnya dari 172.18+.
      - Docker hanya menolak overlap dengan network-nya sendiri (`Pool overlaps ...`), bukan dengan LAN atau VPN.
      - Kernel memilih route dengan prefix **terpanjang** yang cocok, jadi /16 lokal mengalahkan /12 VPN.

      Pilih pool dari rentang yang dikonfirmasi tidak dipakai jaringan kantor. Untuk satu project saja, set
      `networks.default.ipam.config: [{subnet: 10.250.10.0/24}]` di Compose.
    tests:
      pass:
        - |
          docker network inspect myproj_default → 172.18.0.0/16
          ip route get 172.18.4.10 menunjukkan br-xxxx bukan vpn
          overlap dengan 172.16.0.0/12 kantor, route /16 lebih spesifik menang
          set default-address-pools 10.250.0.0/16 size 24 di daemon.json
          docker compose down lalu up agar network dibuat ulang
        - |
          docker network ls
          route print
          subnet docker bentrok dengan kantor
          ganti default-address-pools
      fail:
        - |
          docker network ls
          ip route
          bentrok
          docker system prune -af
          default-address-pools
        - |
          matikan vpn
          restart laptop
        - |
          docker network inspect bridge
          ip route
          ganti dns
    source: https://docs.docker.com/reference/cli/dockerd/#daemon-configuration-file

---

# Model TCP/IP & Pengalamatan IP

Semua perintah di board Linux dan Docker (`ip`, `ss`, `curl`, network Docker) berdiri di atas beberapa konsep dasar:
bagaimana data dibungkus berlapis, dan bagaimana alamat IP serta prefix menentukan siapa yang bisa dihubungi
langsung dan siapa yang harus lewat router. Modul ini membahas konsep itu dengan paket dan network nyata dari lab.

> [!NOTE]
> Diuji di Docker Desktop (Windows) dengan container `alpine:3.20` (busybox `ipcalc`, `iproute2`, `tcpdump`). Semua
> hitungan subnet diverifikasi dua kali, dengan `ipcalc` dan modul Python `ipaddress`.

## 1. Lapisan TCP/IP

| Lapisan TCP/IP | OSI | Contoh | Alamat/identitas |
|---|---|---|---|
| Application | 5–7 | HTTP, DNS, SSH, TLS | URL, nama host |
| Transport | 4 | TCP, UDP | **Port** |
| Internet | 3 | IP, ICMP | **Alamat IP** |
| Link | 1–2 | Ethernet, Wi-Fi, ARP | **Alamat MAC** |

Satu request HTTP di lab (`tcpdump -e -nn`):

```text
fe:e8:af:fb:f5:4c > 42:c9:33:05:d9:98, ethertype IPv4 (0x0800), length 152:      ← Link (14 byte)
  10.51.0.2.42794 > 10.51.0.10.80:                                                ← Internet (20) + Transport (32)
  Flags [P.], ... length 86: HTTP: GET / HTTP/1.1                                 ← Application (86)
```

14 + 20 + 32 + 86 = **152** byte. Setiap lapisan membungkus lapisan di atasnya dan punya field yang menunjuk isi
berikutnya: EtherType `0x0800` → IPv4, protocol 6 → TCP, port 80 → HTTP.

```mermaid
flowchart LR
    A["HTTP<br/>GET / (86 B)"] --> T["TCP header<br/>port 42794 → 80"]
    T --> I["IP header<br/>10.51.0.2 → 10.51.0.10"]
    I --> E["Ethernet header<br/>MAC → MAC, 0x0800"]
```

## 2. IPv4 dan CIDR

Alamat IPv4 terdiri dari 32 bit. Prefix `/n` = n bit pertama adalah bagian **network**, sisanya bagian **host**.

| Prefix | Netmask | Ukuran blok | Host terpakai |
|---|---|---|---|
| /8 | 255.0.0.0 | 16.777.216 | 16.777.214 |
| /16 | 255.255.0.0 | 65.536 | 65.534 |
| /20 | 255.255.240.0 | 4.096 | 4.094 |
| /24 | 255.255.255.0 | 256 | 254 |
| /26 | 255.255.255.192 | 64 | 62 |
| /29 | 255.255.255.248 | 8 | 6 |
| /30 | 255.255.255.252 | 4 | 2 |
| /31 | 255.255.255.254 | 2 | 2 (point-to-point, RFC 3021) |
| /32 | 255.255.255.255 | 1 | 1 (host route) |

### Cara cepat menghitung

1. Ukuran blok = 2^(32 − prefix), dihitung di oktet tempat prefix "berhenti".
2. Network = kelipatan ukuran blok terdekat di bawah alamat.
3. Broadcast = network + ukuran blok − 1.
4. Host = network + 1 sampai broadcast − 1.

Contoh `192.168.10.77/26`: blok 64, maka network `.64`, broadcast `.127`, host `.65`–`.126` (62).

```bash
docker run --rm alpine:3.20 ipcalc -nbmp 192.168.10.77/26
python -c "import ipaddress as i; n=i.ip_interface('192.168.10.77/26').network; print(n, n.broadcast_address, n.num_addresses-2)"
```

## 3. Prefix menentukan siapa "tetangga"

```text
inet 10.50.0.2/29 brd 10.50.0.7 scope global eth0
default via 10.50.0.1 dev eth0
10.50.0.0/29 dev eth0 scope link  src 10.50.0.2     ← connected route, dibuat otomatis dari prefix
```

Alamat yang tercakup connected route dikirim langsung, sedangkan sisanya dikirim ke default gateway. Di lab:

| Prefix di container | `ip route` | `ping 10.51.0.10` |
|---|---|---|
| `/24` | connected + default | berhasil |
| `/32` | **kosong** | `Network unreachable` |
| `/28` | `10.51.0.0/28` | berhasil (kebetulan .10 masih di dalam .0–.15) |

Salah prefix bisa "kebetulan bekerja" sampai ada host di luar rentang yang salah itu, sehingga sulit dilacak.

## 4. Rentang alamat khusus

| Rentang | Kegunaan | Catatan lab |
|---|---|---|
| `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16` | Private (RFC 1918) | `172.32.0.1` → **bukan** private |
| `127.0.0.0/8` | Loopback | `127.0.0.53` (systemd-resolved), `127.0.0.11` (DNS Docker) |
| `169.254.0.0/16` | Link-local (DHCP gagal) | `169.254.169.254` = metadata cloud |
| `100.64.0.0/10` | CGNAT / shared | bukan private, bukan global |
| `192.0.2.0/24`, `198.51.100.0/24`, `203.0.113.0/24` | Dokumentasi | pakai untuk contoh |
| `224.0.0.0/4` | Multicast | — |

## 5. Merencanakan subnet

- Beri cadangan. Di lab, Docker `/29` hanya muat **5** container (6 host − gateway). Container ke-6 →
  `no available IPv4 addresses`.
- Jangan overlap. Docker menolak overlap antar network-nya sendiri (`Pool overlaps ...`), tetapi tidak tahu
  apa-apa tentang LAN kantor, VPN, atau VPC lain.
- Default Docker: bridge `172.17.0.0/16`, lalu network berikutnya dari 172.18+. Rentang ini berada di dalam
  `172.16.0.0/12` yang sering dipakai kantor.

```json
// /etc/docker/daemon.json
{ "default-address-pools": [ { "base": "10.250.0.0/16", "size": 24 } ] }
```

Verifikasi rencana dengan Python:

```python
import ipaddress as i
i.ip_network("192.168.1.0/24").overlaps(i.ip_network("192.168.1.128/25"))   # True
list(i.ip_network("10.10.0.0/22").subnets(new_prefix=24))                   # 4 subnet /24
```

## 6. IPv6 singkat

| Hal | Nilai |
|---|---|
| Panjang | 128 bit, 8 grup heksadesimal |
| Ringkas | `2001:0db8:0000:0000:0000:ff00:0042:8329` → `2001:db8::ff00:42:8329` |
| Subnet LAN | **/64** (2^64 alamat) |
| Link-local | `fe80::/10`, otomatis di setiap interface |
| Loopback | `::1` |
| ULA (setara private) | `fd00::/8` |
| Dokumentasi | `2001:db8::/32` |

Di lab, container di network `--ipv6 --subnet fd00:50::/64` mendapat `fd00:50::2/64` (global) dan `fe80::.../64`
(link), dengan `default via fd00:50::1`.

## 7. Ringkasan

| Pertanyaan | Jawaban cepat |
|---|---|
| IP ini di subnet mana? | Blok 2^(32−prefix), kelipatan terdekat di bawahnya |
| Berapa host? | 2^(32−prefix) − 2, dikurangi gateway/cadangan provider |
| Kenapa "Network unreachable"? | Tidak ada route. Cek prefix dan `ip route` |
| Alamat ini private? | Hanya 10/8, 172.16/12, 192.168/16 |
| Kenapa VPN putus saat Docker jalan? | Subnet Docker overlap, dan route yang lebih spesifik menang |
