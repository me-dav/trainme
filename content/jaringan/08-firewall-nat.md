---
format: 1
id: net-firewall-nat
course:
  slug: jaringan
  title: Jaringan
  description: Konsep jaringan untuk DevOps — TCP/IP, subnetting, routing, TCP/UDP, DNS, HTTP, TLS, proxy, firewall, VPN, dan troubleshooting.
title: Firewall & NAT
summary: Netfilter dari dalam — tabel dan chain (INPUT/FORWARD/OUTPUT, PREROUTING/POSTROUTING), urutan rule, policy, conntrack dan stateful filtering, SNAT/MASQUERADE dan DNAT, iptables-nft vs nftables, persistensi, serta bagaimana Docker memasang rule-nya sendiri (port yang melewati ufw, DOCKER-USER) — diuji dengan firewall container dan dibaca langsung dari VM Docker.
order: 8
version: 1
status: verified
updated: '2026-11-24'
tested_on:
  - Docker Desktop (Windows) — container firewall alpine:3.20 + iptables 1.8.10 (nf_tables), nftables, conntrack-tools, tcpdump
  - Rule Docker Engine 29.4.1 dibaca read-only dari VM Docker Desktop (--net host --privileged)
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: chains
    text: Memahami tabel, chain, dan jalur paket di netfilter
  - id: rules
    text: Menulis rule, policy, dan memahami urutan evaluasi
  - id: state
    text: Memahami conntrack dan stateful filtering
  - id: nat
    text: Memahami SNAT/MASQUERADE dan DNAT (port forwarding)
  - id: tools
    text: Memakai iptables, nftables, dan conntrack, serta membuat rule permanen
  - id: docker
    text: Memahami rule Docker dan cara membatasi port yang di-publish
sources:
  - title: netfilter — Packet flow in Netfilter and General Networking
    url: https://wiki.nftables.org/wiki-nftables/index.php/Netfilter_hooks
  - title: iptables(8) dan iptables-extensions(8) (conntrack, DNAT, SNAT, MASQUERADE)
    url: https://man7.org/linux/man-pages/man8/iptables-extensions.8.html
  - title: nftables wiki — Quick reference
    url: https://wiki.nftables.org/wiki-nftables/index.php/Quick_reference-nftables_in_10_minutes
  - title: conntrack(8)
    url: https://man7.org/linux/man-pages/man8/conntrack.8.html
  - title: Linux kernel — nf_conntrack sysctl
    url: https://docs.kernel.org/networking/nf_conntrack-sysctl.html
  - title: Docker Docs — Packet filtering and firewalls (DOCKER-USER, ufw)
    url: https://docs.docker.com/engine/network/packet-filtering-firewalls/
  - title: Docker Docs — Port publishing and mapping
    url: https://docs.docker.com/engine/network/port-publishing/
  - title: Ubuntu — ufw
    url: https://help.ubuntu.com/community/UFW
  - title: Debian — iptables-persistent / netfilter-persistent
    url: https://wiki.debian.org/iptables
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: chains
    prompt: Chain netfilter yang dilewati paket yang **ditujukan ke host firewall itu sendiri** (mis. SSH ke server)?
    answers: ['INPUT']
    tests:
      fail: ['FORWARD', 'OUTPUT', 'PREROUTING', 'POSTROUTING']
    explanation: 'Di lab, dengan `FORWARD` policy DROP, klien masih bisa ping firewall itu sendiri (`ttl=` diterima), karena ping itu lewat INPUT. Yang terblokir hanya lalu lintas yang **melewati** firewall ke server (`000`). INPUT melindungi host, FORWARD melindungi jaringan di belakangnya (termasuk container Docker).'
    source: https://wiki.nftables.org/wiki-nftables/index.php/Netfilter_hooks

  - id: a02
    tier: A
    type: text
    objective: chains
    prompt: Chain filter yang dilewati paket yang **di-route melewati** host (dari satu interface ke interface lain)?
    answers: ['FORWARD']
    tests:
      fail: ['INPUT', 'OUTPUT', 'ROUTE', 'TRANSIT']
    explanation: 'Di lab, `iptables -P FORWARD DROP` → klien ke server `000`, lalu dibuka dengan rule FORWARD. Hanya berlaku bila `ip_forward=1` (modul 02). Lalu lintas ke container Docker yang port-nya di-publish juga lewat FORWARD, bukan INPUT (tier B).'
    source: https://wiki.nftables.org/wiki-nftables/index.php/Netfilter_hooks

  - id: a03
    tier: A
    type: text
    objective: nat
    prompt: Tabel iptables tempat rule DNAT, SNAT, dan MASQUERADE ditulis (`-t ...`)?
    answers: ['nat']
    tests:
      fail: ['filter', 'mangle', 'raw', 'security']
    explanation: 'Di lab: `iptables -t nat -A PREROUTING ... -j DNAT`, `iptables -t nat -A POSTROUTING ... -j MASQUERADE`. Tanpa `-t`, iptables memakai tabel `filter` (ACCEPT/DROP/REJECT). `mangle` untuk mengubah header (mis. TCPMSS di modul 02). Hanya paket **pertama** sebuah koneksi yang dievaluasi tabel nat, dan sisanya mengikuti conntrack.'
    source: https://man7.org/linux/man-pages/man8/iptables.8.html

  - id: a04
    tier: A
    type: text
    objective: nat
    prompt: Chain tempat **DNAT** (mengubah alamat tujuan, port forwarding) dilakukan untuk paket yang datang dari luar?
    answers: ['PREROUTING']
    tests:
      fail: ['POSTROUTING', 'INPUT', 'FORWARD', 'OUTPUT']
    explanation: 'DNAT harus terjadi **sebelum** keputusan routing, agar kernel tahu paket ini diteruskan ke server internal, bukan diterima host sendiri. Di lab, `-t nat -A PREROUTING -d 10.66.1.254 -p tcp --dport 8080 -j DNAT --to-destination 10.66.2.20:80`, dan tcpdump firewall menunjukkan `eth0 In ... > 10.66.1.254.8080`, lalu `eth1 Out ... > 10.66.2.20.80`.'
    source: https://man7.org/linux/man-pages/man8/iptables-extensions.8.html

  - id: a05
    tier: A
    type: text
    objective: nat
    prompt: Chain tempat **SNAT/MASQUERADE** (mengubah alamat sumber) dilakukan?
    answers: ['POSTROUTING']
    tests:
      fail: ['PREROUTING', 'OUTPUT', 'FORWARD', 'INPUT']
    explanation: 'SNAT dilakukan **setelah** routing, saat interface keluar sudah diketahui. Di lab, `-t nat -A POSTROUTING ... -j MASQUERADE` → log server mencatat klien sebagai `10.66.2.254` (IP firewall), bukan `10.66.1.10`. Router rumah dan Docker (container → internet) memakai MASQUERADE di POSTROUTING.'
    source: https://man7.org/linux/man-pages/man8/iptables-extensions.8.html

  - id: a06
    tier: A
    type: command
    objective: rules
    prompt: Set **policy default** chain FORWARD menjadi DROP dengan iptables.
    answers: ['iptables -P FORWARD DROP']
    patterns: ['^iptables (-P|--policy) FORWARD DROP$']
    tests:
      pass: ['iptables --policy FORWARD DROP']
      fail: ['iptables -A FORWARD -j DROP', 'iptables -P FORWARD REJECT', 'iptables -D FORWARD DROP']
    explanation: 'Policy adalah keputusan untuk paket yang **tidak cocok** dengan rule mana pun. Di lab: `Chain FORWARD (policy DROP 7 packets, 420 bytes)`, dengan counter policy terlihat di header. Policy hanya boleh ACCEPT atau DROP (bukan REJECT). Pola aman: policy DROP + allowlist eksplisit.'
    source: https://man7.org/linux/man-pages/man8/iptables.8.html

  - id: a07
    tier: A
    type: command
    objective: state
    prompt: Tambahkan rule di FORWARD yang menerima semua paket milik koneksi yang sudah **ESTABLISHED** atau **RELATED** (pakai modul conntrack).
    answers: ['iptables -A FORWARD -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT']
    patterns: ['^iptables -(A|I) FORWARD( 1)? -m conntrack --ctstate (ESTABLISHED,RELATED|RELATED,ESTABLISHED) -j ACCEPT$', '^iptables -(A|I) FORWARD( 1)? -m state --state (ESTABLISHED,RELATED|RELATED,ESTABLISHED) -j ACCEPT$']
    tests:
      pass: ['iptables -I FORWARD 1 -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT', 'iptables -A FORWARD -m state --state ESTABLISHED,RELATED -j ACCEPT']
      fail: ['iptables -A FORWARD --ctstate ESTABLISHED -j ACCEPT', 'iptables -A FORWARD -m conntrack --ctstate NEW -j ACCEPT', 'iptables -A INPUT -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT']
    explanation: 'Di lab, policy DROP + allow `NEW tcp/80` saja → `000`, karena SYN-ACK balasan server ikut dibuang. Setelah rule ini ditambahkan di posisi 1 → `200`. Rule ini biasanya diletakkan **pertama**, karena hampir semua paket adalah bagian dari koneksi yang sudah ada, sehingga cepat diputuskan. `-m state` adalah sintaks lama yang setara.'
    source: https://man7.org/linux/man-pages/man8/iptables-extensions.8.html

  - id: a08
    tier: A
    type: command
    objective: tools
    prompt: Tampilkan rule chain FORWARD beserta **counter** paket/byte, angka (tanpa resolve), dan **nomor baris**.
    answers: ['iptables -L FORWARD -v -n --line-numbers']
    patterns: ['^iptables (?=.*-L FORWARD)(?=.*(-v|-\w*v))(?=.*(-n|-\w*n))(?=.*--line-numbers)((-L FORWARD|-v|-n|-vn|-nv|--line-numbers) ?)+$']
    tests:
      pass: ['iptables -L FORWARD -nv --line-numbers', 'iptables -v -n -L FORWARD --line-numbers']
      fail: ['iptables -S FORWARD', 'iptables -L FORWARD', 'iptables -L -v -n']
    explanation: 'Di lab: `1  11 1507 ACCEPT ... ctstate RELATED,ESTABLISHED` dan `2  4 240 ACCEPT ... 10.66.2.20 tcp dpt:80`. Counter menjawab "apakah rule ini pernah kena?", jadi rule yang counternya 0 saat kamu mengetes berarti tidak pernah dicapai. `-S` mencetak format `iptables-save` (tanpa counter), yang cocok untuk disalin.'
    source: https://man7.org/linux/man-pages/man8/iptables.8.html

  - id: a09
    tier: A
    type: command
    objective: rules
    prompt: Hapus rule **nomor 2** di chain FORWARD.
    answers: ['iptables -D FORWARD 2']
    patterns: ['^iptables (-D|--delete) FORWARD 2$']
    tests:
      pass: ['iptables --delete FORWARD 2']
      fail: ['iptables -R FORWARD 2', 'iptables -F FORWARD 2', 'iptables -X FORWARD 2']
    explanation: 'Nomor rule berasal dari `--line-numbers`, dan nomornya bergeser setelah setiap penghapusan, jadi cek ulang sebelum menghapus berikutnya. Alternatifnya, hapus dengan spesifikasi lengkap (`-D FORWARD -p tcp --dport 80 -j DROP`). `-F` menghapus **semua** rule di chain, dan berbahaya lewat SSH bila policy-nya DROP.'
    source: https://man7.org/linux/man-pages/man8/iptables.8.html

  - id: a10
    tier: A
    type: text
    objective: rules
    prompt: Apa beda `iptables -I` dan `iptables -A`?
    answers: ['-I menyisipkan di awal (atau posisi tertentu), -A menambah di akhir chain']
    patterns: ['(?=.*-i.*(awal|atas|depan|sisip|insert|posisi))(?=.*-a.*(akhir|bawah|belakang|append))']
    tests:
      pass: ['-I insert di atas/posisi tertentu, -A append di akhir', '-I sisipkan di awal, -A tambah di akhir']
      fail: ['sama saja', '-I untuk input, -A untuk all', '-A di awal, -I di akhir']
    explanation: 'Urutan penting karena rule pertama yang cocok menang (tier B). Di lab, `-I FORWARD 2 -p tcp --dport 80 -j DROP` (disisipkan sebelum ACCEPT port 80) langsung memblokir (`000`), sedangkan rule DROP yang sama dengan `-A` di akhir tidak berpengaruh, karena ACCEPT sudah cocok lebih dulu.'
    source: https://man7.org/linux/man-pages/man8/iptables.8.html

  - id: a11
    tier: A
    type: command
    objective: state
    prompt: Tampilkan tabel connection tracking kernel dengan alat `conntrack`.
    answers: ['conntrack -L']
    patterns: ['^(sudo )?conntrack (-L|--dump)( -p tcp| -n| -o extended)*$']
    tests:
      pass: ['conntrack --dump', 'conntrack -L -p tcp']
      fail: ['ss -tan', 'cat /proc/net/tcp', 'iptables -L conntrack']
    explanation: 'Di lab: `tcp 6 111 TIME_WAIT src=10.66.1.10 dst=10.66.2.20 sport=45970 dport=80 src=10.66.2.20 dst=10.66.1.10 sport=80 dport=45970 [ASSURED]`. Setiap entry berisi tuple arah asli dan arah balasan (berguna untuk melihat NAT, tier B). Hapus entry dengan `conntrack -D`, dan hitung dengan `conntrack -C`.'
    source: https://man7.org/linux/man-pages/man8/conntrack.8.html

  - id: a12
    tier: A
    type: text
    objective: tools
    prompt: '`iptables -V` menampilkan `iptables v1.8.10 (nf_tables)`. Apa artinya?'
    answers: ['perintah iptables adalah frontend yang menulis rule ke nftables di kernel (iptables-nft)']
    patterns: ['(nftables|nf_tables|iptables-nft|backend|frontend|di atas nft)']
    tests:
      pass: ['iptables-nft: sintaks iptables, backend nftables', 'rule ditulis ke nf_tables']
      fail: ['iptables legacy', 'tidak memakai kernel', 'iptables versi lama']
    explanation: 'Di lab, rule yang dibuat dengan `iptables` muncul di `nft list chain ip filter FORWARD`: `type filter hook forward priority filter; policy drop;` dan `ip saddr 10.66.1.0/24 ip daddr 10.66.2.20 tcp dport 80 counter ... accept`. Distro modern (Debian 11+, Ubuntu 20.10+, RHEL 8+) memakai backend ini. Jangan mencampur `iptables-legacy` dan `iptables-nft` di satu host, karena rule-nya tidak saling terlihat.'
    source: https://wiki.nftables.org/wiki-nftables/index.php/Legacy_xtables_tools

  - id: a13
    tier: A
    type: text
    objective: nat
    prompt: Apa beda target `MASQUERADE` dengan `SNAT --to-source`?
    answers: ['MASQUERADE memakai IP interface keluar secara otomatis (cocok IP dinamis); SNAT memakai IP tetap yang ditentukan']
    patterns: ['(?=.*masquerade.*(otomatis|dinamis|interface|dynamic|ip keluar))']
    tests:
      pass: ['masquerade otomatis memakai ip interface keluar, snat ip tetap', 'masquerade untuk ip dinamis']
      fail: ['sama persis', 'masquerade mengubah tujuan', 'snat untuk udp saja']
    explanation: 'Di lab, `MASQUERADE` di firewall mengganti sumber menjadi `10.66.2.254` tanpa menyebut IP itu. Cocok untuk IP yang bisa berubah (DHCP, PPPoE, router rumah). `SNAT --to-source 203.0.113.10` sedikit lebih efisien untuk IP statis. Docker memakai MASQUERADE untuk lalu lintas container ke luar.'
    source: https://man7.org/linux/man-pages/man8/iptables-extensions.8.html

  - id: a14
    tier: A
    type: text
    objective: docker
    prompt: Nama chain yang disediakan Docker untuk rule **buatan pengguna**, dievaluasi sebelum rule Docker sendiri dan tidak ditimpa Docker?
    answers: ['DOCKER-USER']
    tests:
      fail: ['DOCKER', 'DOCKER-FORWARD', 'DOCKER-ISOLATION-STAGE-1', 'USER']
    explanation: 'Di lab (VM Docker Desktop): `-A FORWARD -j DOCKER-USER` muncul **sebelum** `-A FORWARD -j DOCKER-FORWARD`. Rule di chain `DOCKER` dikelola Docker dan dibuat ulang setiap kali container/daemon berubah. Rule pembatasan akses ke container yang di-publish ditaruh di DOCKER-USER.'
    source: https://docs.docker.com/engine/network/packet-filtering-firewalls/

  - id: a15
    tier: A
    type: command
    objective: tools
    prompt: Tampilkan **seluruh** ruleset nftables (semua tabel dan chain).
    answers: ['nft list ruleset']
    patterns: ['^(sudo )?nft (-a )?list ruleset$']
    tests:
      pass: ['nft -a list ruleset']
      fail: ['nft list tables', 'iptables -S', 'nft show ruleset']
    explanation: 'Dengan backend nf_tables, ini satu-satunya tampilan yang memuat **semua** rule: dari iptables-nft, Docker, firewalld, dan nftables langsung. Di lab, tabel `ip nat` container firewall ternyata sudah berisi chain `DOCKER_OUTPUT`/`DOCKER_POSTROUTING` milik DNS embedded Docker (127.0.0.11). `-a` menampilkan handle untuk menghapus rule tertentu.'
    source: https://wiki.nftables.org/wiki-nftables/index.php/Quick_reference-nftables_in_10_minutes

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: chains
    prompt: '`iptables -P FORWARD DROP` di firewall. Apakah klien di belakangnya masih bisa ping **firewall itu sendiri**? (ya/tidak)'
    answers: ['ya', 'yes', 'bisa']
    tests:
      fail: ['tidak', 'no']
    explanation: 'Di lab: ke server di belakang firewall → `000`, tetapi ping ke `10.66.1.254` (firewall) → berhasil. Paket ke firewall sendiri lewat INPUT, bukan FORWARD. Kebalikannya juga sering membingungkan: membuka port di INPUT tidak membuat layanan di **balik** firewall bisa diakses.'
    source: https://wiki.nftables.org/wiki-nftables/index.php/Netfilter_hooks

  - id: b02
    tier: B
    type: text
    objective: state
    prompt: 'FORWARD policy DROP. Hanya ada satu rule: terima TCP dport 80 dari klien ke server. Kenapa `curl` tetap gagal?'
    answers: ['balasan server (SYN-ACK, sport 80) tidak cocok rule mana pun dan terbuang; butuh rule ESTABLISHED,RELATED']
    patterns: ['(balasan|reply|syn-?ack|arah balik|return)', 'established']
    tests:
      pass: ['balasan SYN-ACK dari server terbuang, perlu rule established', 'tidak ada rule untuk arah balik']
      fail: ['port 80 salah', 'dns gagal', 'server mati']
    explanation: 'Di lab: rule NEW port 80 saja → `000`. Ditambah `-m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT` → `200`. Rule `--dport 80` hanya cocok untuk arah klien → server. Balasan server memakai **sport** 80 ke port ephemeral klien. Firewall stateful (conntrack) mengenali balasan itu sebagai bagian koneksi yang sudah diizinkan.'
    source: https://man7.org/linux/man-pages/man8/iptables-extensions.8.html

  - id: b03
    tier: B
    type: text
    objective: rules
    prompt: Di chain ada `ACCEPT tcp dpt:80` (baris 2). Kamu menyisipkan `DROP tcp dpt:80` di baris 2 (ACCEPT turun ke baris 3). Apakah port 80 masih bisa diakses? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab: `-I FORWARD 2 -p tcp --dport 80 -j DROP` → `000`. netfilter berhenti di rule **pertama** yang cocok dengan target final (ACCEPT/DROP/REJECT), sehingga rule di bawahnya tidak pernah dievaluasi untuk paket itu. Saat debugging, baca chain dari atas, dan lihat counter rule mana yang naik.'
    source: https://man7.org/linux/man-pages/man8/iptables.8.html

  - id: b04
    tier: B
    type: text
    objective: state
    prompt: Sebuah koneksi TCP sudah berjalan. Lalu ditambahkan rule `DROP` untuk paket **NEW** ke port itu. Apa yang terjadi pada koneksi lama dan koneksi baru?
    answers: ['koneksi lama tetap jalan (ESTABLISHED), koneksi baru diblokir']
    patterns: ['(?=.*(lama|existing|yang sudah).*(tetap|jalan|hidup|lanjut|aman))(?=.*(baru|new).*(blok|drop|gagal|ditolak))']
    tests:
      pass: ['koneksi lama tetap jalan, yang baru diblokir', 'existing tetap hidup karena established, new di-drop']
      fail: ['keduanya putus', 'keduanya jalan', 'koneksi lama putus']
    explanation: 'Di lab, koneksi Python yang sudah terbuka tetap mendapat `HTTP/1.1 200 OK` di setiap request selama rule DROP NEW aktif, sementara `curl` baru → `000`. Ini menyelamatkan sesi SSH-mu saat mengubah firewall. Kebalikannya juga berlaku: memblokir penyerang tidak memutus koneksinya yang sudah ada. Pakai `conntrack -D -s <ip>` untuk memutusnya.'
    source: https://man7.org/linux/man-pages/man8/conntrack.8.html

  - id: b05
    tier: B
    type: text
    objective: state
    prompt: 'Kernel lab menunjukkan `nf_conntrack_max=262144`. Apa yang terjadi bila tabel conntrack penuh (mis. saat banjir koneksi)?'
    answers: ['koneksi baru dibuang (nf_conntrack: table full, dropping packet)']
    patterns: ['(dibuang|drop|dropping|ditolak|gagal|table full)']
    tests:
      pass: ['paket koneksi baru di-drop, log table full', 'koneksi baru gagal']
      fail: ['entry lama dihapus otomatis tanpa efek', 'kernel crash', 'tidak ada pengaruh']
    explanation: 'Setiap koneksi (termasuk yang TIME_WAIT/CLOSE, lab: entry `TIME_WAIT ... 111` detik) memakan satu slot. Bila penuh, kernel mencatat `nf_conntrack: table full, dropping packet`, dan koneksi baru gagal seperti "jaringan putus acak". Pantau `conntrack -C` vs `nf_conntrack_max`, dan naikkan sesuai memori di host dengan banyak koneksi (load balancer, NAT gateway).'
    source: https://docs.kernel.org/networking/nf_conntrack-sysctl.html

  - id: b06
    tier: B
    type: text
    objective: nat
    prompt: Firewall memakai `MASQUERADE` untuk lalu lintas klien → server. IP klien apa yang tercatat di log nginx server?
    answers: ['IP firewall (10.66.2.254), bukan IP klien asli']
    patterns: ['(10\.66\.2\.254|firewall|router|gateway|nat)']
    tests:
      pass: ['10.66.2.254', 'ip firewall, ip klien hilang']
      fail: ['10.66.1.10', 'ip klien asli', '0.0.0.0']
    explanation: 'Di lab: `server log client = 10.66.2.254`. SNAT/MASQUERADE menyembunyikan klien di balik IP firewall. Itu memang tujuannya untuk akses internet, tetapi untuk layanan yang perlu IP klien (audit, rate limit, allowlist), hindari SNAT dan pastikan route balik server lewat firewall. Di HTTP, alternatifnya adalah header proxy (modul 07).'
    source: https://man7.org/linux/man-pages/man8/iptables-extensions.8.html

  - id: b07
    tier: B
    type: text
    objective: nat
    prompt: Port forwarding `firewall:8080 → server:80` dengan DNAT di PREROUTING. Counter rule `INPUT ... dpt:8080 ACCEPT` di firewall setelah request — bertambah atau tetap 0?
    answers: ['tetap 0']
    patterns: ['(tetap )?0', 'nol', 'tidak (bertambah|naik)']
    tests:
      pass: ['0', 'tidak bertambah, lewat FORWARD']
      fail: ['bertambah', 'naik', '1']
    explanation: 'Di lab: `0 0 ACCEPT ... tcp dpt:8080`. Setelah DNAT di PREROUTING, tujuan paket sudah `10.66.2.20:80`, sehingga paket di-route lewat **FORWARD**, bukan INPUT. Rule izin port forwarding harus ada di FORWARD (dengan alamat/port **setelah** DNAT). Inilah juga alasan ufw (chain INPUT) tidak menyaring port Docker.'
    source: https://wiki.nftables.org/wiki-nftables/index.php/Netfilter_hooks

  - id: b08
    tier: B
    type: text
    objective: docker
    prompt: Host memakai ufw `default deny incoming`, lalu `docker run -p 18778:80 nginx`. Port 18778 bisa diakses dari luar. Kenapa ufw tidak memblokirnya?
    answers: ['Docker memasang DNAT di nat PREROUTING; paket diteruskan lewat FORWARD ke container, bukan lewat INPUT tempat rule ufw']
    patterns: ['(?=.*(dnat|prerouting|nat))(?=.*(forward|bukan input|tidak lewat input|melewati input))']
    tests:
      pass: ['docker pakai DNAT di PREROUTING lalu FORWARD, ufw hanya INPUT', 'nat prerouting docker, paket ke forward bukan input']
      fail: ['ufw rusak', 'docker mematikan ufw', 'port di atas 1024 tidak difilter']
    explanation: 'Di lab (VM Docker Desktop, read-only): `-A DOCKER -p tcp -m tcp --dport 18778 -j DNAT --to-destination 172.17.0.2:80`. Persis seperti uji DNAT lab, counter INPUT tetap 0. Perbaikannya: publish ke `127.0.0.1` saja, jangan publish port yang tidak perlu (network internal), atau batasi di `DOCKER-USER`.'
    source: https://docs.docker.com/engine/network/packet-filtering-firewalls/

  - id: b09
    tier: B
    type: text
    objective: docker
    prompt: '`docker run -p 127.0.0.1:18777:80`. Seperti apa rule DNAT yang dibuat Docker, dan apa artinya?'
    answers: ['DNAT dengan -d 127.0.0.1/32, hanya koneksi ke localhost yang diteruskan']
    patterns: ['127\.0\.0\.1', 'localhost']
    tests:
      pass: ['-d 127.0.0.1/32 ... DNAT, hanya dari localhost', 'hanya localhost']
      fail: ['semua interface', '0.0.0.0', 'tidak ada rule']
    explanation: 'Di lab: `-A DOCKER -d 127.0.0.1/32 -p tcp -m tcp --dport 18777 -j DNAT --to-destination 172.17.0.2:80`, sedangkan tanpa IP: `-A DOCKER -p tcp ... --dport 18778` (semua alamat). Untuk database, admin panel, dan Redis, bind ke `127.0.0.1` lalu akses lewat SSH tunnel (modul berikutnya).'
    source: https://docs.docker.com/engine/network/port-publishing/

  - id: b10
    tier: B
    type: command
    objective: docker
    prompt: Di host Docker, sisipkan rule di awal `DOCKER-USER` yang **menolak** akses ke container dari interface publik `eth0` kecuali dari subnet `203.0.113.0/24`.
    answers: ['iptables -I DOCKER-USER -i eth0 ! -s 203.0.113.0/24 -j DROP']
    patterns: ['^iptables -I DOCKER-USER( 1)? (-i eth0 ! -s 203\.0\.113\.0/24|! -s 203\.0\.113\.0/24 -i eth0) -j (DROP|REJECT)$']
    tests:
      pass: ['iptables -I DOCKER-USER ! -s 203.0.113.0/24 -i eth0 -j DROP', 'iptables -I DOCKER-USER 1 -i eth0 ! -s 203.0.113.0/24 -j REJECT']
      fail: ['iptables -I INPUT -i eth0 ! -s 203.0.113.0/24 -j DROP', 'iptables -I DOCKER -i eth0 ! -s 203.0.113.0/24 -j DROP', 'ufw deny from any to any port 80']
    explanation: 'Rule di `DOCKER-USER` dievaluasi sebelum rule Docker (lab: urutan di FORWARD) dan tidak dihapus saat Docker membuat ulang rule-nya. Ingat, DNAT sudah terjadi, sehingga `--dport` di sini adalah port **container** (mis. 80), bukan port host. Untuk mencocokkan port asli, pakai `-m conntrack --ctorigdstport 18778`.'
    source: https://docs.docker.com/engine/network/packet-filtering-firewalls/

  - id: b11
    tier: B
    type: text
    objective: nat
    prompt: 'Entry conntrack: `src=10.66.1.10 dst=10.66.1.254 sport=43338 dport=8080 ... src=10.66.2.20 dst=10.66.1.10 sport=80 dport=43338`. NAT apa yang terlihat?'
    answers: ['DNAT: tujuan 10.66.1.254:8080 diterjemahkan ke 10.66.2.20:80']
    patterns: ['dnat', '(10\.66\.1\.254:?8080|8080).*(10\.66\.2\.20|:80)', 'port forward']
    tests:
      pass: ['DNAT 10.66.1.254:8080 ke 10.66.2.20:80', 'port forwarding ke server']
      fail: ['snat', 'masquerade', 'tidak ada nat']
    explanation: 'Tuple pertama = arah asli (yang klien kirim), tuple kedua = balasan yang **diharapkan**. Balasan dari `10.66.2.20:80` (bukan dari `10.66.1.254:8080`) berarti tujuan diterjemahkan, yaitu DNAT. Kalau yang berbeda adalah alamat tujuan balasan, itu SNAT. conntrack adalah cara paling cepat melihat NAT yang sebenarnya terjadi.'
    source: https://man7.org/linux/man-pages/man8/conntrack.8.html

  - id: b12
    tier: B
    type: text
    objective: state
    prompt: 'Entry conntrack berstatus `SYN_SENT ... [UNREPLIED]`. Apa artinya?'
    answers: ['SYN sudah lewat, tetapi balasan belum pernah terlihat di firewall ini']
    patterns: ['(balasan|reply|jawaban).*(belum|tidak|tak)', '(belum|tidak|tak).*(balasan|reply|dijawab|dibalas)']
    tests:
      pass: ['balasan belum pernah terlihat', 'syn terkirim tapi tidak ada reply']
      fail: ['koneksi sukses', 'sudah ditutup', 'diblok firewall pasti']
    explanation: 'Di lab, uji DNAT pertama gagal dengan entry `SYN_SENT ... [UNREPLIED]`. Firewall meneruskan SYN (tcpdump: `eth1 Out ... > 10.66.2.20.80`), tetapi SYN-ACK tidak kembali lewatnya. Penyebab umumnya: server tidak membalas, atau balasannya mengambil jalur lain (asimetris). Lanjutkan dengan tcpdump di hop berikutnya (soal berikutnya).'
    source: https://man7.org/linux/man-pages/man8/conntrack.8.html

  - id: b13
    tier: B
    type: text
    objective: nat
    prompt: 'Di lab, DNAT di container firewall gagal. tcpdump di **server** menunjukkan SYN datang dari `10.66.2.1` (gateway bridge Docker), bukan dari klien. Pelajaran apa yang diambil?'
    answers: ['ada NAT lain di jalur (host Docker) yang mengubah alamat; verifikasi dengan tcpdump di setiap hop']
    patterns: ['(nat lain|nat (di )?host|double nat|middlebox|perangkat lain|hop lain|setiap hop|tiap hop|di kedua sisi|host docker)']
    tests:
      pass: ['ada nat lain di host docker, cek tcpdump di setiap hop', 'double nat oleh host, verifikasi tiap hop']
      fail: ['dnat tidak bisa dipakai', 'server rusak', 'harus pakai nftables']
    explanation: 'tcpdump firewall: paket keluar benar (`10.66.1.10 > 10.66.2.20.80`). tcpdump server: datang sebagai `10.66.2.1.43338 > 10.66.2.20.80`, lalu `[R]`. Netfilter host Docker Desktop ikut men-NAT paket itu, sehingga balasan tidak kembali lewat firewall. Dengan DNAT + MASQUERADE di firewall → `200`. Di jaringan nyata, "NAT tersembunyi" seperti ini datang dari cloud, ISP, atau perangkat lain. Jangan menyimpulkan dari satu titik capture.'
    source: https://docs.docker.com/engine/network/packet-filtering-firewalls/

  - id: b14
    tier: B
    type: text
    objective: tools
    prompt: Rule yang dibuat dengan perintah `iptables` hilang setelah reboot. Bagaimana membuatnya permanen (Debian/Ubuntu tanpa ufw)?
    answers: ['simpan dengan iptables-save ke /etc/iptables/rules.v4 dan pakai netfilter-persistent (atau nftables.conf)']
    patterns: ['(iptables-save|netfilter-persistent|iptables-persistent|rules\.v4|nftables\.conf|nft -f)']
    tests:
      pass: ['iptables-save > /etc/iptables/rules.v4 dengan netfilter-persistent', 'tulis /etc/nftables.conf dan enable nftables.service']
      fail: ['iptables otomatis permanen', 'reboot dua kali', 'crontab @reboot iptables -F']
    explanation: 'Rule netfilter hanya ada di memori kernel. Pilihannya: `iptables-save > /etc/iptables/rules.v4` + paket `iptables-persistent`, `/etc/nftables.conf` + `systemctl enable nftables`, atau frontend (ufw/firewalld) yang menyimpan sendiri. Perhatikan bahwa Docker memasang rule-nya sendiri saat start, jadi jangan menyimpan rule Docker ke file persisten.'
    source: https://wiki.debian.org/iptables

  - id: b15
    tier: B
    type: text
    objective: nat
    prompt: Port forwarding DNAT `publik:8080 → 10.0.0.20:80` dipasang di gateway, tanpa SNAT. Agar balasan server sampai ke klien internet, apa yang harus benar di sisi server `10.0.0.20`?
    answers: ['route balik / default gateway server harus lewat gateway yang melakukan DNAT']
    patterns: ['(default gateway|gateway|route balik|return route|jalur balik).*(gateway|router|firewall|yang (sama|melakukan))', 'lewat (gateway|router|firewall)']
    tests:
      pass: ['default gateway server harus router/gateway yang melakukan DNAT', 'route balik lewat firewall']
      fail: ['server harus punya IP publik', 'tidak perlu apa-apa', 'buka INPUT di server saja']
    explanation: 'Gateway harus melihat balasan untuk membalik terjemahan (dari `10.0.0.20:80` menjadi `publik:8080`). Bila server membalas lewat jalur lain, klien menerima paket dari alamat yang tidak ia kenal, lalu RST/timeout. Itu persis yang terlihat di lab saat jalur balik terganggu (`UNREPLIED`, `[R]`). Bila route balik tidak bisa diatur, tambahkan SNAT/MASQUERADE (lab: `200`), dengan konsekuensi server melihat IP gateway.'
    source: https://man7.org/linux/man-pages/man8/iptables-extensions.8.html

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: rules
    prompt: |
      VPS baru (Ubuntu, akses SSH saja, IP admin `198.51.100.7`) akan menjalankan web di 80/443. Tulis firewall
      host-nya dengan iptables atau nftables: default deny masuk, tanpa mengunci dirimu sendiri saat menerapkannya, dan
      permanen setelah reboot (satu perintah/poin per baris).
    checklist:
      - id: established
        label: Izinkan ESTABLISHED,RELATED dan loopback lebih dulu
        required: true
        patterns: ['established', 'ct state']
      - id: ssh
        label: Izinkan SSH (22) — idealnya hanya dari IP admin
        required: true
        patterns: ['(dport|port)\s*22\b', 'ssh']
      - id: web
        label: Izinkan 80 dan 443
        required: true
        patterns: ['(dport|port)[^\n]*\b443\b']
      - id: policy
        label: Policy/aturan akhir INPUT drop
        required: true
        patterns: ['-P\s+INPUT\s+DROP', 'policy drop', 'default deny', 'hook input[^\n]*drop']
      - id: icmp
        label: Izinkan ICMP penting (ping / frag-needed)
        patterns: ['icmp']
      - id: safety
        label: Jaring pengaman (sesi kedua, at/sleep rollback, iptables-apply, console)
        patterns: ['(sesi kedua|second session|rollback|iptables-apply|netplan try|at now|sleep \d+.*(flush|restore|accept)|console)']
      - id: persist
        label: Simpan permanen (iptables-save/netfilter-persistent/nftables.conf)
        patterns: ['(iptables-save|netfilter-persistent|nftables\.conf|systemctl enable nftables|ufw enable)']
      - id: no-flush
        label: Tidak flush semua rule saat policy DROP aktif
        forbid: true
        patterns: ['iptables\s+-F\s*$', 'iptables\s+-F\s+INPUT']
    pass_score: 0.5
    solution: |
      ```bash
      # jaring pengaman dulu: kembalikan otomatis bila terkunci
      sudo sh -c 'sleep 300 && iptables -P INPUT ACCEPT' &          # atau: iptables-apply
      iptables -A INPUT -i lo -j ACCEPT
      iptables -A INPUT -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
      iptables -A INPUT -p tcp -s 198.51.100.7 --dport 22 -j ACCEPT
      iptables -A INPUT -p tcp -m multiport --dports 80,443 -j ACCEPT
      iptables -A INPUT -p icmp --icmp-type echo-request -j ACCEPT
      iptables -A INPUT -p icmp --icmp-type fragmentation-needed -j ACCEPT
      iptables -P INPUT DROP                                         # terakhir, setelah semua izin ada
      # uji dari sesi SSH KEDUA; bila OK, batalkan rollback (kill %1)
      apt install iptables-persistent && netfilter-persistent save   # /etc/iptables/rules.v4
      ```
      Prinsip dari lab:
      - `ESTABLISHED,RELATED` di atas, karena tanpa itu balasan terbuang.
      - Rule pertama yang cocok menang, jadi policy DROP dipasang **terakhir**.
      - Koneksi SSH yang sudah ada tetap hidup saat rule berubah, tetapi sesi baru (mis. setelah putus) mengikuti rule
        baru. Karena itu uji dari sesi kedua sebelum menutup yang pertama.
      - ICMP frag-needed diizinkan untuk mencegah PMTU blackhole (modul 02).

      Docker (bila nanti dipasang) melewati INPUT untuk port yang di-publish. Lihat studi kasus berikutnya.
    tests:
      pass:
        - |
          sleep 300 && iptables -P INPUT ACCEPT sebagai rollback
          iptables -A INPUT -i lo -j ACCEPT
          iptables -A INPUT -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
          iptables -A INPUT -p tcp -s 198.51.100.7 --dport 22 -j ACCEPT
          iptables -A INPUT -p tcp -m multiport --dports 80,443 -j ACCEPT
          iptables -A INPUT -p icmp -j ACCEPT
          iptables -P INPUT DROP
          netfilter-persistent save
        - |
          nft add rule inet filter input ct state established,related accept
          nft add rule inet filter input tcp dport 22 ip saddr 198.51.100.7 accept
          nft add rule inet filter input tcp dport { 80, 443 } accept
          chain input policy drop
      fail:
        - |
          iptables -P INPUT DROP
          iptables -A INPUT -p tcp --dport 22 -j ACCEPT
          iptables -F
        - |
          ufw disable
          iptables -A INPUT -p tcp --dport 443 -j ACCEPT
        - |
          iptables -A INPUT -p tcp --dport 80 -j ACCEPT
          iptables -A INPUT -p tcp --dport 443 -j ACCEPT
    source: https://man7.org/linux/man-pages/man8/iptables.8.html

  - id: c02
    tier: C
    type: scenario
    objective: docker
    prompt: |
      Audit menemukan Postgres di server Docker bisa diakses dari internet di port 5432, padahal `ufw status` menunjukkan
      `default deny (incoming)` dan tidak ada rule untuk 5432. Compose berisi `ports: ["5432:5432"]` untuk service `db`.
      Jelaskan penyebabnya, perbaiki, dan verifikasi (satu poin per baris).
    checklist:
      - id: cause
        label: Penyebab DNAT/FORWARD Docker melewati INPUT/ufw
        required: true
        patterns: ['(dnat|prerouting|forward)', 'melewati (ufw|input)', 'bypass']
      - id: inspect
        label: Melihat rule Docker (iptables -t nat -S DOCKER / nft list ruleset)
        patterns: ['iptables\s+-t\s+nat\s+-(S|L)', 'nft\s+list\s+ruleset', 'docker\s+port']
      - id: fix-bind
        label: Hapus publish atau bind ke 127.0.0.1 (akses lewat network internal/SSH tunnel)
        required: true
        patterns: ['127\.0\.0\.1:5432', 'hapus (ports|publish)', 'tanpa ports', '(jangan|tidak perlu) publish', 'network internal']
      - id: docker-user
        label: Atau batasi di DOCKER-USER
        patterns: ['docker-user']
      - id: verify
        label: Verifikasi dari luar (nc/nmap dari host lain) dan ss/docker ps
        required: true
        patterns: ['(nc\s+-zv|nmap|telnet|dari luar|from outside|host lain)']
      - id: rotate
        label: Rotasi password DB (anggap sudah terekspos) / cek log akses
        patterns: ['(rotasi|ganti|rotate).*(password|kredensial)', 'log (akses|koneksi)']
      - id: no-disable-iptables
        label: Tidak mematikan pengelolaan iptables Docker sebagai solusi
        forbid: true
        patterns: ['"iptables"\s*:\s*false', 'iptables=false', 'iptables\s+-F']
    pass_score: 0.6
    solution: |
      ```text
      Penyebab: "5432:5432" = publish di semua alamat. Docker memasang DNAT di nat PREROUTING
                (-A DOCKER -p tcp --dport 5432 -j DNAT --to 172.x.x.x:5432) → paket diteruskan lewat FORWARD,
                tidak pernah melewati INPUT tempat ufw bekerja (lab: counter INPUT untuk port yang di-DNAT tetap 0).
      Cek     : sudo iptables -t nat -S DOCKER | grep 5432 ; docker compose port db 5432
      Perbaiki: hapus `ports:` dari db — app mengakses lewat network internal Compose (db:5432);
                bila admin perlu akses: ports: ["127.0.0.1:5432:5432"] + ssh -L 5432:127.0.0.1:5432 server
                (lab: publish 127.0.0.1 → DNAT hanya untuk -d 127.0.0.1/32)
                opsional berlapis: iptables -I DOCKER-USER -i eth0 -p tcp -m conntrack --ctorigdstport 5432 -j DROP
      Verifikasi: dari host LAIN: nc -zv <ip-server> 5432 → harus timeout/refused; di server: docker compose ps
      Insiden : anggap password DB terekspos → rotasi; periksa log koneksi Postgres untuk IP asing
      ```
      Mematikan `"iptables": false` di daemon.json merusak networking container (NAT keluar, publish) dan bukan
      perbaikan yang aman.
    tests:
      pass:
        - |
          docker publish 5432 memasang DNAT di PREROUTING, paket lewat FORWARD sehingga melewati ufw INPUT
          iptables -t nat -S DOCKER | grep 5432
          hapus ports dari service db, app pakai network internal
          admin pakai ports 127.0.0.1:5432:5432 dan ssh tunnel
          verifikasi dari host lain: nc -zv 203.0.113.10 5432
          rotasi password database
        - |
          dnat docker bypass ufw
          ubah ke 127.0.0.1:5432:5432
          tambah rule DOCKER-USER
          nmap dari luar
      fail:
        - |
          dnat docker bypass ufw
          set "iptables": false di daemon.json
          127.0.0.1:5432:5432
          nc -zv dari luar
        - |
          ufw deny 5432
          restart docker
        - |
          ganti port ke 15432
    source: https://docs.docker.com/engine/network/packet-filtering-firewalls/

  - id: c03
    tier: C
    type: scenario
    objective: nat
    prompt: |
      Gateway Linux kantor (publik `203.0.113.10` di `eth0`, LAN `10.0.0.1` di `eth1`) harus mem-forward
      `203.0.113.10:8080` ke server internal `10.0.0.20:80`. Server memakai gateway itu sebagai default gateway.
      Tulis rule-nya, beserta cara memastikan dan men-debug bila tidak bekerja (satu perintah/poin per baris).
    checklist:
      - id: forward
        label: ip_forward=1
        patterns: ['ip_forward\s*=\s*1']
      - id: dnat
        label: DNAT di PREROUTING ke 10.0.0.20:80
        required: true
        patterns: ['prerouting[^\n]*dnat[^\n]*10\.0\.0\.20:80', 'dnat[^\n]*to(-destination)?\s+10\.0\.0\.20:80']
      - id: allow
        label: Izin di FORWARD ke 10.0.0.20 port 80 (alamat setelah DNAT) + established
        required: true
        patterns: ['forward[^\n]*10\.0\.0\.20[^\n]*(80|accept)', 'forward[^\n]*established']
      - id: return
        label: Menjelaskan route balik lewat gateway (atau SNAT bila tidak bisa)
        patterns: ['(default gateway|route balik|jalur balik|return|masquerade|snat)']
      - id: debug
        label: Debug dengan conntrack -L / tcpdump di kedua sisi / counter
        required: true
        patterns: ['conntrack', 'tcpdump', '-v\s+-n|-nv|-vn|counter']
      - id: test-outside
        label: Uji dari luar (bukan dari LAN/gateway sendiri)
        patterns: ['(dari luar|from outside|internet|host luar|hp|seluler|hairpin)']
      - id: no-open-all
        label: Tidak membuka FORWARD untuk semua (policy ACCEPT)
        forbid: true
        patterns: ['-P\s+FORWARD\s+ACCEPT', 'iptables\s+-F\s+FORWARD']
    pass_score: 0.4
    solution: |
      ```bash
      sysctl -w net.ipv4.ip_forward=1
      iptables -t nat -A PREROUTING -i eth0 -d 203.0.113.10 -p tcp --dport 8080 -j DNAT --to-destination 10.0.0.20:80
      iptables -A FORWARD -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
      iptables -A FORWARD -i eth0 -o eth1 -d 10.0.0.20 -p tcp --dport 80 -m conntrack --ctstate NEW -j ACCEPT   # port SETELAH DNAT
      # server 10.0.0.20: default gateway = 10.0.0.1 → balasan kembali lewat gateway, sehingga NAT bisa dibalik
      # debug
      conntrack -L | grep 8080                      # tuple balasan dari 10.0.0.20:80? [UNREPLIED] = balasan tak kembali
      tcpdump -nn -i eth1 tcp port 80              # di gateway: SYN keluar ke server?
      tcpdump -nn -i eth0 tcp port 80              # di server: SYN datang dari IP klien? SYN-ACK keluar ke gateway?
      iptables -L FORWARD -v -n --line-numbers     # counter rule naik?
      # uji dari luar jaringan (HP via data seluler) — dari dalam LAN butuh hairpin NAT
      ```
      Semua elemennya diuji di lab:
      - DNAT di PREROUTING memindahkan paket ke FORWARD (counter INPUT 0).
      - Rule FORWARD memakai alamat setelah DNAT.
      - Ketika jalur balik terganggu, conntrack menunjukkan `SYN_SENT [UNREPLIED]`, dan tcpdump server menunjukkan
        sumber yang tidak diharapkan.
      - Menambahkan MASQUERADE menyelesaikan jalur balik (`200`), tetapi server kehilangan IP klien.
    tests:
      pass:
        - |
          sysctl -w net.ipv4.ip_forward=1
          iptables -t nat -A PREROUTING -i eth0 -p tcp --dport 8080 -j DNAT --to-destination 10.0.0.20:80
          iptables -A FORWARD -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
          iptables -A FORWARD -d 10.0.0.20 -p tcp --dport 80 -j ACCEPT
          default gateway server harus 10.0.0.1 agar balasan lewat gateway
          debug: conntrack -L | grep 8080 dan tcpdump di gateway dan server
          uji dari luar lewat hp
        - |
          prerouting dnat --to-destination 10.0.0.20:80
          forward established dan forward 10.0.0.20 port 80 accept
          tcpdump -nn -i eth1 port 80
      fail:
        - |
          iptables -t nat -A PREROUTING -p tcp --dport 8080 -j DNAT --to-destination 10.0.0.20:80
          iptables -P FORWARD ACCEPT
          tcpdump
        - |
          iptables -A INPUT -p tcp --dport 8080 -j ACCEPT
          restart server
        - |
          port forward 8080
    source: https://man7.org/linux/man-pages/man8/iptables-extensions.8.html

---

# Firewall & NAT

Firewall Linux (iptables, nftables, ufw, firewalld, dan rule Docker) semuanya berdiri di atas **netfilter** di kernel.
Memahami jalur paket di netfilter menjelaskan banyak hal yang membingungkan: kenapa port Docker lolos dari ufw, kenapa
satu rule "tidak berefek", kenapa koneksi lama tetap hidup setelah diblokir, dan kenapa NAT merusak jalur balik.

> [!NOTE]
> Lab: container firewall (alpine, `iptables v1.8.10 (nf_tables)`, `ip_forward=1`) di antara klien `10.66.1.10` dan
> server nginx `10.66.2.20`. Rule Docker dibaca **read-only** dari VM Docker Desktop. Satu temuan lab (DNAT yang
> terganggu NAT di host Docker) dijelaskan apa adanya di bagian 5.

## 1. Jalur paket

```mermaid
flowchart LR
    IN([paket masuk]) --> PRE["PREROUTING<br/>(nat: DNAT)"]
    PRE --> R{routing:<br/>untuk host ini?}
    R -- ya --> INP["INPUT<br/>(filter)"] --> P[proses lokal]
    R -- tidak --> FWD["FORWARD<br/>(filter)"] --> POST["POSTROUTING<br/>(nat: SNAT/MASQUERADE)"] --> OUT([keluar])
    P --> OUTC["OUTPUT"] --> POST
```

| Tujuan paket | Chain filter | Contoh |
|---|---|---|
| Host ini (SSH ke server) | INPUT | ufw bekerja di sini |
| Melewati host (router, **container Docker**) | FORWARD | port forwarding, publish Docker |
| Dibuat host ini | OUTPUT | curl dari server |

## 2. Rule, urutan, dan policy

```text
Chain FORWARD (policy DROP 7 packets, 420 bytes)
num  pkts bytes target  prot  source          destination
1      11  1507 ACCEPT  0     0.0.0.0/0       0.0.0.0/0     ctstate RELATED,ESTABLISHED
2       4   240 ACCEPT  6     10.66.1.0/24    10.66.2.20    tcp dpt:80
```

| Langkah lab | Hasil |
|---|---|
| `-P FORWARD DROP` | Ke server `000`. Ping ke firewall sendiri tetap OK (INPUT) |
| \+ ACCEPT NEW tcp/80 saja | `000`: balasan SYN-ACK terbuang |
| \+ `ESTABLISHED,RELATED` di atas | `200` |
| `-I FORWARD 2 ... --dport 80 -j DROP` | `000`: rule pertama yang cocok menang |

## 3. Conntrack: firewall yang ingat

```text
tcp 6 111 TIME_WAIT src=10.66.1.10 dst=10.66.2.20 sport=45970 dport=80
                    src=10.66.2.20 dst=10.66.1.10 sport=80 dport=45970 [ASSURED]
nf_conntrack_max=262144
```

- Rule DROP NEW yang ditambahkan saat koneksi sedang berjalan → koneksi lama **tetap** `200 OK`, koneksi baru
  `000` (lab). Ini menyelamatkan SSH-mu, tetapi juga tidak memutus penyerang yang sudah terhubung
  (`conntrack -D`).
- Tabel penuh → `nf_conntrack: table full, dropping packet`.

## 4. NAT

| Jenis | Chain | Lab | Efek |
|---|---|---|---|
| MASQUERADE / SNAT | POSTROUTING | Log server: klien = `10.66.2.254` | Klien tersembunyi di balik firewall |
| DNAT (port forwarding) | PREROUTING | `10.66.1.254:8080 → 10.66.2.20:80`, counter INPUT 8080 = 0 | Lewat FORWARD, bukan INPUT |

```bash
iptables -t nat -A PREROUTING  -d 10.66.1.254 -p tcp --dport 8080 -j DNAT --to-destination 10.66.2.20:80
iptables -A FORWARD -d 10.66.2.20 -p tcp --dport 80 -j ACCEPT        # alamat SETELAH DNAT
iptables -t nat -A POSTROUTING -s 10.66.1.0/24 -o eth1 -j MASQUERADE
```

## 5. Temuan lab: NAT tersembunyi di jalur

Uji DNAT pertama gagal (`000`):

```text
firewall eth1 Out: 10.66.1.10.48410 > 10.66.2.20.80: [S]        ← DNAT di firewall benar
server   eth0 In : 10.66.2.1.43338  > 10.66.2.20.80: [S]        ← sumber berubah jadi gateway Docker!
                   10.66.2.20.80 > 10.66.2.1.43338: [S.]   →   10.66.2.1 ... [R]
conntrack firewall: SYN_SENT ... [UNREPLIED]
```

Netfilter di host Docker Desktop men-NAT ulang paket itu, sehingga balasan server tidak kembali lewat firewall.
Lalu lintas routed biasa (tanpa DNAT) tidak terkena (server melihat `10.66.1.10`), dan DNAT + MASQUERADE di firewall
langsung berhasil (`200`). Pelajarannya berlaku di mana pun: **tcpdump di setiap hop**, karena NAT tambahan dari
cloud, ISP, atau perangkat lain sering tidak terlihat dari satu titik.

## 6. Docker dan firewall host

Dibaca langsung dari VM Docker Desktop:

```text
-A DOCKER -d 127.0.0.1/32 -p tcp -m tcp --dport 18777 -j DNAT --to-destination 172.17.0.2:80   ← -p 127.0.0.1:18777:80
-A DOCKER -p tcp -m tcp --dport 18778 -j DNAT --to-destination 172.17.0.2:80                   ← -p 18778:80 (semua alamat)
-A FORWARD -j DOCKER-USER                                                                       ← rule-mu, dievaluasi dulu
-A FORWARD -j DOCKER-FORWARD
```

> [!DANGER]
> `ports: ["5432:5432"]` membuka port itu ke **semua** alamat. Lalu lintasnya di-DNAT dan lewat FORWARD, sehingga
> ufw (INPUT) tidak pernah melihatnya. Untuk layanan internal: jangan publish (pakai network Compose), atau bind ke
> `127.0.0.1`, atau batasi di `DOCKER-USER`.

## 7. Alat

| Tugas | iptables | nftables |
|---|---|---|
| Lihat semua | `iptables -S`, `iptables -t nat -S` | `nft list ruleset` |
| Lihat counter | `iptables -L FORWARD -v -n --line-numbers` | `nft list chain ip filter FORWARD` |
| Simpan permanen | `iptables-save > /etc/iptables/rules.v4` | `/etc/nftables.conf` |
| Lihat koneksi | `conntrack -L` | `conntrack -L` |

`iptables (nf_tables)` = sintaks iptables, backend nftables (lab: rule iptables muncul di `nft list`).

## 8. Ringkasan

| Gejala | Penyebab | Cek |
|---|---|---|
| Rule "tidak berefek" | Rule lain cocok lebih dulu / chain salah | Urutan + counter |
| Satu arah saja yang lewat | Tidak ada `ESTABLISHED,RELATED` | Rule state di atas |
| Port Docker lolos ufw | DNAT → FORWARD | `iptables -t nat -S DOCKER`, `DOCKER-USER` |
| Port forwarding gagal | Rule FORWARD / jalur balik | `conntrack -L` (UNREPLIED), tcpdump di kedua sisi |
| Server melihat IP gateway | SNAT/MASQUERADE | Hindari SNAT bila IP klien penting |
| Blokir tidak memutus koneksi | Conntrack ESTABLISHED | `conntrack -D` |
