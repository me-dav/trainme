---
format: 1
id: net-tunneling-vpn
course:
  slug: jaringan
  title: Jaringan
  description: Konsep jaringan untuk DevOps — TCP/IP, subnetting, routing, TCP/UDP, DNS, HTTP, TLS, proxy, firewall, VPN, dan troubleshooting.
title: Tunneling & VPN
summary: SSH sebagai alat tunneling (local, remote, dynamic/SOCKS, jump host) beserta pengaturan sshd-nya, lalu WireGuard dari nol — kunci, peer, AllowedIPs sebagai cryptokey routing, site-to-site, split vs full tunnel, apa yang terlihat di jaringan, dan MTU — semua dibuktikan dengan bastion, host privat, dan dua peer WireGuard di container.
order: 9
version: 1
status: verified
updated: '2026-11-24'
tested_on:
  - Docker Desktop (Windows) — OpenSSH (alpine:3.20) laptop → bastion → host di network internal
  - WireGuard (modul kernel 6.18 + wireguard-tools) dua peer + LAN di belakang peer B
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: ssh-forward
    text: Membuat local, remote, dan dynamic port forwarding dengan SSH
  - id: ssh-jump
    text: Menjangkau host privat lewat jump host (bastion)
  - id: sshd
    text: Mengatur dan memverifikasi izin forwarding di sshd
  - id: wg-basics
    text: Menyiapkan WireGuard (kunci, peer, endpoint, handshake)
  - id: wg-routing
    text: Memahami AllowedIPs (cryptokey routing), site-to-site, split vs full tunnel
  - id: wg-ops
    text: Memahami apa yang terlihat di jaringan, MTU, keepalive, dan troubleshooting VPN
sources:
  - title: ssh(1) — -L, -R, -D, -J, -N, -f
    url: https://man.openbsd.org/ssh.1
  - title: ssh_config(5) — ProxyJump, LocalForward
    url: https://man.openbsd.org/ssh_config.5
  - title: sshd_config(5) — AllowTcpForwarding, GatewayPorts, PermitOpen (first obtained value)
    url: https://man.openbsd.org/sshd_config.5
  - title: sshd(8) — -T (extended test / effective config)
    url: https://man.openbsd.org/sshd.8
  - title: WireGuard — Quick Start dan Conceptual Overview (cryptokey routing)
    url: https://www.wireguard.com/quickstart/
  - title: WireGuard whitepaper (protocol, message sizes)
    url: https://www.wireguard.com/papers/wireguard.pdf
  - title: wg(8) dan wg-quick(8)
    url: https://man7.org/linux/man-pages/man8/wg.8.html
  - title: curl — --socks5-hostname
    url: https://curl.se/docs/manpage.html#--socks5-hostname
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: command
    objective: ssh-forward
    prompt: Buat **local port forwarding**, yaitu port `15432` di laptop diteruskan ke `127.0.0.1:5432` milik server `10.67.0.22` (user `dev`), tanpa menjalankan perintah remote.
    answers: ['ssh -N -L 15432:127.0.0.1:5432 dev@10.67.0.22']
    patterns: ['^ssh ((-N|-f|-fN|-Nf) )*-L ?15432:(127\.0\.0\.1|localhost):5432 ((-N|-f|-fN|-Nf) )*dev@10\.67\.0\.22$']
    tests:
      pass: ['ssh -f -N -L 15432:127.0.0.1:5432 dev@10.67.0.22', 'ssh -L 15432:localhost:5432 -N dev@10.67.0.22']
      fail: ['ssh -R 15432:127.0.0.1:5432 dev@10.67.0.22', 'ssh -L 5432:15432 dev@10.67.0.22', 'ssh -D 15432 dev@10.67.0.22']
    explanation: 'Di lab, service di bastion yang hanya listen di `127.0.0.1:5432` tidak bisa dijangkau langsung, tetapi lewat tunnel `curl http://127.0.0.1:15432/` → `bastion-local-only`. Format `-L [bind:]portlokal:tujuan:porttujuan`, dengan `tujuan` di-resolve **dari sisi server**, sehingga `127.0.0.1` berarti localhost milik server. Inilah cara aman mengakses database production.'
    source: https://man.openbsd.org/ssh.1

  - id: a02
    tier: A
    type: text
    objective: ssh-forward
    prompt: Opsi `ssh` yang berarti "jangan jalankan perintah remote, hanya buat tunnel"?
    answers: ['-N']
    patterns: ['^-n$']
    tests:
      fail: ['-f', '-T', '-q', '-v']
    explanation: '`-N` membuat koneksi hanya untuk forwarding, tanpa shell. `-f` memindahkan ssh ke background setelah autentikasi (lab: `ssh -f -N -L ...`). Kombinasikan dengan `-o ExitOnForwardFailure=yes` agar ssh keluar bila forwarding gagal, bukan diam-diam berjalan tanpa tunnel (tier B).'
    source: https://man.openbsd.org/ssh.1

  - id: a03
    tier: A
    type: command
    objective: ssh-forward
    prompt: Buka **SOCKS proxy dinamis** di port lokal `1080` lewat server `dev@10.67.0.22`.
    answers: ['ssh -N -D 1080 dev@10.67.0.22']
    patterns: ['^ssh ((-N|-f|-fN|-Nf) )*-D ?((127\.0\.0\.1|localhost):)?1080 ((-N|-f|-fN|-Nf) )*dev@10\.67\.0\.22$']
    tests:
      pass: ['ssh -f -N -D 1080 dev@10.67.0.22', 'ssh -D 127.0.0.1:1080 -N dev@10.67.0.22']
      fail: ['ssh -L 1080 dev@10.67.0.22', 'ssh -S 1080 dev@10.67.0.22', 'ssh -R 1080 dev@10.67.0.22']
    explanation: 'Di lab, `curl --socks5-hostname 127.0.0.1:1080 http://10.68.0.30:8080/` → `intra-web`, sedangkan tanpa SOCKS → `unreachable`. Berbeda dari `-L` yang hanya ke satu tujuan, `-D` meneruskan ke tujuan **mana pun** yang diminta aplikasi. Cocok untuk membuka beberapa dashboard internal di browser lewat satu SSH.'
    source: https://man.openbsd.org/ssh.1

  - id: a04
    tier: A
    type: command
    objective: ssh-forward
    prompt: Dengan curl, akses `http://10.68.0.30:8080/` lewat SOCKS5 di `127.0.0.1:1080`, dengan resolusi nama dilakukan di **sisi proxy**.
    answers: ['curl --socks5-hostname 127.0.0.1:1080 http://10.68.0.30:8080/']
    patterns: ['^curl (-s )?(--socks5-hostname[= ]127\.0\.0\.1:1080|-x socks5h://127\.0\.0\.1:1080|--proxy socks5h://127\.0\.0\.1:1080) (-s )?http://10\.68\.0\.30:8080/?$']
    tests:
      pass: ['curl -x socks5h://127.0.0.1:1080 http://10.68.0.30:8080/', 'curl -s --socks5-hostname 127.0.0.1:1080 http://10.68.0.30:8080']
      fail: ['curl --socks5 127.0.0.1:1080 http://10.68.0.30:8080/', 'curl -x http://127.0.0.1:1080 http://10.68.0.30:8080/', 'curl http://10.68.0.30:8080/']
    explanation: '`--socks5-hostname` (atau skema `socks5h://`) mengirim **nama host** ke proxy, sehingga DNS di-resolve di sisi server. Nama internal seperti `grafana.internal` hanya bisa di-resolve di jaringan server. Dengan `--socks5` biasa, laptop yang me-resolve, lalu gagal untuk nama internal dan membocorkan query DNS ke resolver lokal.'
    source: https://curl.se/docs/manpage.html#--socks5-hostname

  - id: a05
    tier: A
    type: command
    objective: ssh-jump
    prompt: SSH ke host privat `dev@10.68.0.30` lewat **jump host** `dev@10.67.0.22` dalam satu perintah.
    answers: ['ssh -J dev@10.67.0.22 dev@10.68.0.30']
    patterns: ['^ssh (-J|-o ProxyJump=)[ ]?dev@10\.67\.0\.22 dev@10\.68\.0\.30( \S+)?$']
    tests:
      pass: ['ssh -o ProxyJump=dev@10.67.0.22 dev@10.68.0.30', 'ssh -J dev@10.67.0.22 dev@10.68.0.30 hostname']
      fail: ['ssh dev@10.67.0.22 ssh dev@10.68.0.30', 'ssh -L dev@10.67.0.22 dev@10.68.0.30', 'ssh -A dev@10.68.0.30']
    explanation: 'Di lab → `dj-intra`, `10.68.0.30/24`, padahal host itu berada di network internal yang tidak bisa dijangkau laptop. Dengan `-J`, koneksi SSH ke tujuan terenkripsi end-to-end dari laptop, sehingga bastion hanya meneruskan byte. Kunci privat tetap di laptop, tanpa perlu agent forwarding (`-A`) yang berisiko.'
    source: https://man.openbsd.org/ssh.1

  - id: a06
    tier: A
    type: text
    objective: ssh-jump
    prompt: Direktif `~/.ssh/config` yang setara dengan opsi `-J`?
    answers: ['ProxyJump']
    tests:
      fail: ['ProxyCommand', 'JumpHost', 'ForwardAgent', 'Bastion']
    explanation: 'Di lab: `Host intra` / `HostName 10.68.0.30` / `User dev` / `ProxyJump bastion` → cukup `ssh intra` → `dj-intra`. `scp`, `rsync -e ssh`, dan Ansible ikut memakai konfigurasi ini. `ProxyCommand ssh -W %h:%p` adalah cara lama yang setara.'
    source: https://man.openbsd.org/ssh_config.5

  - id: a07
    tier: A
    type: command
    objective: ssh-forward
    prompt: Buat **remote port forwarding**, yaitu port `13000` di server `dev@10.67.0.22` diteruskan ke `127.0.0.1:3000` di laptop.
    answers: ['ssh -N -R 13000:127.0.0.1:3000 dev@10.67.0.22']
    patterns: ['^ssh ((-N|-f|-fN|-Nf) )*-R ?((127\.0\.0\.1|localhost|0\.0\.0\.0|\*):)?13000:(127\.0\.0\.1|localhost):3000 ((-N|-f|-fN|-Nf) )*dev@10\.67\.0\.22$']
    tests:
      pass: ['ssh -f -N -R 13000:localhost:3000 dev@10.67.0.22']
      fail: ['ssh -L 13000:127.0.0.1:3000 dev@10.67.0.22', 'ssh -R 3000:13000 dev@10.67.0.22', 'ssh -D 13000 dev@10.67.0.22']
    explanation: 'Di lab, dev server laptop (`127.0.0.1:3000`) bisa diakses dari bastion lewat `127.0.0.1:13000` → `laptop-dev-server`. Arahnya kebalikan dari `-L`: port dibuka di **server**, tujuannya di sisi klien. Kegunaannya: demo ke rekan, webhook ke laptop, atau akses balik ke mesin di belakang NAT.'
    source: https://man.openbsd.org/ssh.1

  - id: a08
    tier: A
    type: text
    objective: sshd
    prompt: Opsi `sshd_config` yang mengizinkan atau melarang TCP port forwarding oleh klien?
    answers: ['AllowTcpForwarding']
    tests:
      fail: ['GatewayPorts', 'PermitTunnel', 'AllowAgentForwarding', 'X11Forwarding']
    explanation: 'Di lab, `AllowTcpForwarding no` → klien mendapat `channel 2: open failed: administratively prohibited: open failed`. Nilai `local`/`remote` mengizinkan satu arah saja. Untuk membatasi tujuan, pakai `PermitOpen host:port` (untuk `-L`) atau `PermitListen` (untuk `-R`). Perhatikan, user yang punya shell tetap bisa membuat tunnel dengan cara lain (mis. `socat`).'
    source: https://man.openbsd.org/sshd_config.5

  - id: a09
    tier: A
    type: text
    objective: wg-basics
    prompt: WireGuard berjalan di atas protokol transport apa?
    answers: ['UDP']
    tests:
      fail: ['TCP', 'ICMP', 'ESP', 'GRE']
    explanation: 'Di lab, tcpdump underlay hanya melihat `10.70.0.10.51820 > 10.70.0.20.51820: UDP, length 148` (handshake), tanpa isi yang terbaca. Port default yang umum dipakai adalah 51820 (dapat diubah). Firewall harus membuka UDP itu ke endpoint yang listen. Jaringan yang memblokir UDP (beberapa Wi-Fi publik/kantor) membuat WireGuard tidak bisa dipakai tanpa tunnel lain.'
    source: https://www.wireguard.com/protocol/

  - id: a10
    tier: A
    type: command
    objective: wg-basics
    prompt: Buat pasangan kunci WireGuard dalam satu baris, yaitu private key ke file `privatekey` dan public key ke file `publickey`.
    answers: ['wg genkey | tee privatekey | wg pubkey > publickey']
    patterns: ['^(umask 077[;&]+ ?)?wg genkey \| tee privatekey \| wg pubkey >publickey$']
    tests:
      pass: ['umask 077; wg genkey | tee privatekey | wg pubkey > publickey']
      fail: ['wg genkey > publickey', 'wg pubkey | tee privatekey', 'ssh-keygen -t wireguard']
    explanation: 'Di lab: `wg genkey | tee /k | wg pubkey > /p`, sehingga setiap peer punya kunci Curve25519. Public key dibagikan ke peer lain, sedangkan private key tidak pernah meninggalkan mesinnya (pakai `umask 077`). Identitas peer di WireGuard **adalah** public key-nya, tanpa username, password, atau sertifikat.'
    source: https://www.wireguard.com/quickstart/

  - id: a11
    tier: A
    type: command
    objective: wg-ops
    prompt: Tampilkan status semua interface WireGuard (peer, endpoint, handshake terakhir, transfer).
    answers: ['wg show']
    patterns: ['^(sudo )?wg( show( all| wg0)?)?$']
    tests:
      pass: ['wg', 'wg show wg0', 'sudo wg show all']
      fail: ['ip link show', 'wg-quick status', 'wg genkey']
    explanation: 'Di lab: `peer: v/f9+... endpoint: 10.70.0.20:51820 allowed ips: 10.99.0.2/32 ... persistent keepalive: every 25 seconds`, dan setelah trafik: `latest handshake ... ago`, `transfer: 2184 B received, 3124 B sent`. Baris **latest handshake** adalah hal pertama yang dicek saat VPN bermasalah (studi kasus C).'
    source: https://man7.org/linux/man-pages/man8/wg.8.html

  - id: a12
    tier: A
    type: text
    objective: wg-ops
    prompt: MTU default interface WireGuard yang terlihat di lab (`ip link show wg0`)?
    answers: ['1420']
    tests:
      fail: ['1500', '1400', '1280', '1460']
    explanation: 'Di lab: `mtu 1420`. Nilai ini memberi ruang untuk header luar (IP + UDP + header WireGuard) di link 1500. Menjalankan WireGuard di atas link yang sudah lebih kecil (PPPoE 1492, VPN lain) butuh MTU lebih kecil lagi, atau MSS clamping (modul 02).'
    source: https://man7.org/linux/man-pages/man8/wg-quick.8.html

  - id: a13
    tier: A
    type: text
    objective: wg-ops
    prompt: Opsi peer WireGuard yang mengirim paket kecil berkala agar mapping NAT/firewall stateful tidak kedaluwarsa (penting bila peer ada di belakang NAT)?
    answers: ['PersistentKeepalive', 'persistent-keepalive']
    patterns: ['^persistent[- ]?keepalive( ?= ?\d+)?$']
    tests:
      pass: ['PersistentKeepalive = 25']
      fail: ['KeepAlive', 'Endpoint', 'ListenPort', 'tcp_keepalive']
    explanation: 'Di lab, `persistent-keepalive 25` → `persistent keepalive: every 25 seconds`. WireGuard sengaja "diam" bila tidak ada trafik, sehingga peer di belakang NAT bisa tidak terjangkau dari luar setelah mapping NAT habis (sering 30–120 detik). 25 detik adalah nilai yang umum direkomendasikan. Peer dengan IP publik tidak membutuhkannya.'
    source: https://www.wireguard.com/quickstart/#nat-and-firewall-traversal-persistence

  - id: a14
    tier: A
    type: text
    objective: wg-routing
    prompt: Di sisi klien, `AllowedIPs = 0.0.0.0/0` untuk peer server VPN artinya apa?
    answers: ['full tunnel: semua trafik IPv4 dikirim lewat VPN']
    patterns: ['(full tunnel|semua (trafik|lalu lintas|paket)|seluruh (trafik|internet)|all traffic)']
    tests:
      pass: ['full tunnel', 'semua trafik lewat vpn']
      fail: ['izinkan semua ip masuk tanpa kunci', 'split tunnel', 'tidak ada routing']
    explanation: 'Di lab, `allowed-ips 0.0.0.0/0` di peer A. AllowedIPs sekaligus menjadi tabel routing keluar ("semua tujuan ke peer ini") dan filter sumber masuk. wg-quick memasang route/rule agar trafik internet lewat tunnel, kecuali endpoint VPN itu sendiri. **Split tunnel** = hanya subnet tertentu (lab: `10.99.0.2/32, 10.71.0.0/24`).'
    source: https://www.wireguard.com/#cryptokey-routing

  - id: a15
    tier: A
    type: text
    objective: ssh-jump
    prompt: Apa itu **bastion host** (jump host)?
    answers: ['satu host yang diperkuat dan terbuka ke luar sebagai satu-satunya pintu masuk ke jaringan privat']
    patterns: ['(pintu masuk|gerbang|gateway|satu-satunya|entry|perantara|lompatan|jump)']
    tests:
      pass: ['satu-satunya pintu masuk ssh ke jaringan privat', 'host perantara untuk masuk ke server internal']
      fail: ['server database', 'load balancer', 'dns server']
    explanation: 'Di lab, bastion `10.67.0.22` punya kaki di network publik dan network internal (`10.68.0.22`), sedangkan `dj-intra` hanya di network internal (`--internal`, tanpa akses keluar). Hanya bastion yang membuka SSH ke luar, sehingga audit dan pengamanan terpusat di satu titik. Alternatif modern: VPN (WireGuard) atau akses berbasis identitas (SSM, Teleport, Tailscale).'
    source: https://man.openbsd.org/ssh.1

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: ssh-forward
    prompt: '`ssh -L 18080:10.68.0.30:8080 ...` membuka port 18080 di alamat apa di laptop? Bagaimana membuatnya bisa dipakai host lain di LAN laptop?'
    answers: ['127.0.0.1 saja; pakai -L 0.0.0.0:18080:... (atau GatewayPorts di klien)']
    patterns: ['(?=.*(127\.0\.0\.1|localhost|loopback))(?=.*(0\.0\.0\.0|\*:|gatewayports|bind))']
    tests:
      pass: ['127.0.0.1; pakai -L 0.0.0.0:18080:10.68.0.30:8080', 'localhost saja, bind ke 0.0.0.0 dengan GatewayPorts']
      fail: ['0.0.0.0', 'semua interface', 'tidak bisa sama sekali']
    explanation: 'Di lab: default → `127.0.0.1:18080` (dan `::1`), sedangkan `-L 0.0.0.0:28080:...` → `0.0.0.0:28080 LISTEN`. Default yang aman ini mencegah tunnel ke database production tanpa sengaja terbuka untuk seluruh Wi-Fi kafe. Buka ke 0.0.0.0 hanya bila memang diperlukan, dan sadari risikonya.'
    source: https://man.openbsd.org/ssh.1

  - id: b02
    tier: B
    type: text
    objective: ssh-forward
    prompt: Lewat tunnel `-L 18080:10.68.0.30:8080` (via bastion), koneksi ke `dj-intra` terlihat berasal dari IP siapa?
    answers: ['IP bastion (10.68.0.22)']
    patterns: ['(10\.68\.0\.22|bastion)']
    tests:
      pass: ['10.68.0.22', 'dari bastion']
      fail: ['10.67.0.10', 'ip laptop', '127.0.0.1']
    explanation: 'Di lab, `netstat` di intra → peer `10.68.0.22:46822`. Bagian "server → tujuan" adalah koneksi TCP biasa yang dibuat sshd di bastion, dan bagian itu **tidak** terenkripsi SSH. Allowlist dan firewall di tujuan harus mengizinkan IP bastion. Log di tujuan tidak tahu laptop mana di baliknya, jadi audit harus dilakukan di bastion.'
    source: https://man.openbsd.org/ssh.1

  - id: b03
    tier: B
    type: text
    objective: ssh-forward
    prompt: Kenapa `curl --socks5-hostname` (atau `socks5h://`) lebih tepat daripada `--socks5` untuk mengakses nama host internal lewat `ssh -D`?
    answers: ['nama di-resolve di sisi server SSH, sehingga nama internal bisa di-resolve dan DNS tidak bocor ke resolver lokal']
    patterns: ['(resolve|dns|nama).*(server|remote|sisi proxy|ujung|bastion)', '(bocor|leak)']
    tests:
      pass: ['dns di-resolve di sisi server/proxy', 'nama internal hanya dikenal di jaringan server, dan dns tidak bocor']
      fail: ['lebih cepat', 'lebih terenkripsi', 'tidak ada bedanya']
    explanation: 'Dengan `--socks5`, laptop me-resolve nama lebih dulu. Nama seperti `grafana.svc.internal` gagal, atau lebih buruk, query-nya bocor ke DNS Wi-Fi publik. Dengan `-hostname`, nama dikirim apa adanya ke sshd, yang me-resolve-nya dengan resolver jaringan internal. Hal yang sama berlaku untuk pengaturan proxy SOCKS di browser ("Proxy DNS when using SOCKS v5").'
    source: https://curl.se/docs/manpage.html#--socks5-hostname

  - id: b04
    tier: B
    type: text
    objective: sshd
    prompt: '`ssh -R 13000:127.0.0.1:3000` berhasil, tetapi host lain di network server **tidak** bisa membuka `bastion:13000` (`Connection refused`). Kenapa?'
    answers: ['port remote forward di-bind ke 127.0.0.1 karena GatewayPorts default no']
    patterns: ['gatewayports', '(127\.0\.0\.1|localhost|loopback)']
    tests:
      pass: ['GatewayPorts no, jadi bind ke 127.0.0.1', 'hanya listen di localhost server']
      fail: ['firewall', 'laptop mati', 'allowtcpforwarding no']
    explanation: 'Di lab: `netstat` di bastion → `127.0.0.1:13000 LISTEN`, dan dari intra → `Connection refused`. Setelah `GatewayPorts yes` diterapkan dengan benar → `0.0.0.0:13003 LISTEN`, dan intra mendapat `laptop-dev-server`. Mengekspos port laptop ke jaringan server lewat `-R` punya implikasi keamanan, karena siapa pun di network itu bisa mengaksesnya.'
    source: https://man.openbsd.org/sshd_config.5

  - id: b05
    tier: B
    type: text
    objective: sshd
    prompt: '`GatewayPorts yes` ditambahkan di **akhir** `sshd_config`, sshd di-reload, tetapi tidak berefek. File sudah berisi `GatewayPorts no` di baris 91. Kenapa?'
    answers: ['sshd memakai nilai pertama yang ditemukan; baris 91 menang']
    patterns: ['(pertama|first).*(menang|dipakai|berlaku|wins|obtained)', '(menang|dipakai).*(pertama|first|baris 91)']
    tests:
      pass: ['nilai pertama yang ditemukan yang dipakai sshd', 'first obtained value wins, baris 91 menang']
      fail: ['harus restart server', 'yang terakhir menang', 'salah huruf besar']
    explanation: 'Di lab: `sshd -T | grep gatewayports` → `gatewayports no`, bahkan setelah restart. Menurut sshd_config(5), untuk setiap keyword **nilai pertama yang ditemukan** yang dipakai, termasuk dari file `Include` di awal (`/etc/ssh/sshd_config.d/*.conf`). Ubah baris yang ada, atau taruh override di drop-in yang di-include lebih awal, lalu verifikasi dengan `sshd -T`.'
    source: https://man.openbsd.org/sshd_config.5

  - id: b06
    tier: B
    type: command
    objective: sshd
    prompt: Tampilkan konfigurasi **efektif** sshd (hasil akhir semua file dan default) untuk memverifikasi nilai sebuah opsi.
    answers: ['sshd -T']
    patterns: ['^(sudo )?(/usr/sbin/)?sshd -T( \| grep -i \S+)?$']
    tests:
      pass: ['sudo sshd -T', 'sshd -T | grep -i gatewayports']
      fail: ['cat /etc/ssh/sshd_config', 'sshd -t', 'ssh -G localhost']
    explanation: 'Di lab, `sshd -T` memperlihatkan nilai yang benar-benar berlaku (`gatewayports no`), berbeda dengan yang terlihat di akhir file. `sshd -t` (huruf kecil) hanya memeriksa sintaks, jadi selalu jalankan sebelum reload agar tidak terkunci. `ssh -G host` adalah padanannya di sisi klien (konfigurasi efektif `ssh_config`).'
    source: https://man.openbsd.org/sshd.8

  - id: b07
    tier: B
    type: text
    objective: sshd
    prompt: 'Dengan `AllowTcpForwarding no` di server, `ssh -N -L 19080:10.68.0.30:8080` tetap tersambung, tetapi `curl 127.0.0.1:19080` gagal. Pesan apa yang muncul di klien ssh?'
    answers: ['channel open failed: administratively prohibited']
    patterns: ['administratively prohibited', 'open failed']
    tests:
      pass: ['channel 2: open failed: administratively prohibited: open failed']
      fail: ['connection refused', 'permission denied (publickey)', 'host key verification failed']
    explanation: 'Di lab: `channel 2: open failed: administratively prohibited: open failed`, dengan `curl rc=56` (koneksi direset). Port lokal tetap terbuka karena sesi SSH-nya sah, dan penolakan baru terjadi saat channel forwarding diminta. Gunakan `-o ExitOnForwardFailure=yes`, agar ssh langsung keluar bila forwarding ditolak saat setup.'
    source: https://man.openbsd.org/sshd_config.5

  - id: b08
    tier: B
    type: text
    objective: wg-routing
    prompt: '`ping 10.71.0.50` lewat `wg0`, tetapi `10.71.0.0/24` tidak ada di AllowedIPs peer mana pun. Error apa yang muncul?'
    answers: ['Required key not available (ENOKEY)']
    patterns: ['required key not available', 'enokey']
    tests:
      pass: ['ping: sendmsg: Required key not available']
      fail: ['network unreachable', 'timeout', 'permission denied']
    explanation: 'Di lab: `ping: sendmsg: Required key not available`. Route ke `wg0` ada, tetapi WireGuard tidak tahu peer mana yang boleh menerima tujuan itu. Inilah **cryptokey routing**: AllowedIPs memetakan tujuan → public key peer. Setelah `allowed-ips 10.99.0.2/32,10.71.0.0/24` → ping `ttl=63` dan web LAN `200`.'
    source: https://www.wireguard.com/#cryptokey-routing

  - id: b09
    tier: B
    type: text
    objective: wg-routing
    prompt: 'Peer A mengirim paket dengan sumber `10.99.0.77` (bukan alamatnya), sedangkan di B, AllowedIPs untuk A hanya `10.99.0.1/32`. Apa yang terjadi di B?'
    answers: ['paket dibuang diam-diam karena sumber tidak termasuk AllowedIPs peer A']
    patterns: ['(dibuang|drop|ditolak|diabaikan|tidak (diterima|sampai|muncul))']
    tests:
      pass: ['dibuang karena sumber tidak ada di allowed ips', 'drop diam-diam']
      fail: ['diterima', 'dibalas', 'diteruskan ke lan']
    explanation: 'Di lab: ping dengan `-I 10.99.0.77` → `0 received`, ping biasa dari `10.99.0.1` → `2 received`, dan tcpdump di `wg0` B hanya melihat paket dari `10.99.0.1`. Ke arah masuk, AllowedIPs berfungsi sebagai filter sumber. Peer yang sah sekalipun tidak bisa memalsukan IP lain di dalam tunnel. Ini sebabnya `AllowedIPs = 0.0.0.0/0` di **server** untuk satu klien adalah kesalahan.'
    source: https://www.wireguard.com/#cryptokey-routing

  - id: b10
    tier: B
    type: text
    objective: wg-ops
    prompt: Dari tcpdump di jaringan underlay, apa yang bisa diketahui pengamat tentang lalu lintas WireGuard, dan apa yang tidak?
    answers: ['terlihat IP/port endpoint, ukuran, dan waktu paket UDP; isi dan alamat di dalam tunnel tidak terlihat']
    patterns: ['(?=.*(ip|endpoint|port|ukuran|size|waktu|timing|metadata))(?=.*(isi|konten|payload|dalam tunnel|tidak terlihat|terenkripsi))']
    tests:
      pass: ['terlihat ip dan port endpoint serta ukuran udp, isi terenkripsi tidak terlihat', 'metadata terlihat, payload tidak']
      fail: ['semuanya terlihat', 'tidak ada apa pun yang terlihat', 'alamat dalam tunnel terlihat']
    explanation: 'Di lab: `10.70.0.10.51820 > 10.70.0.20.51820: UDP, length 148`, lalu `92`, lalu `32`, dan ping di dalam tunnel (`10.99.0.x`) tidak terlihat sama sekali. 148/92 cocok dengan ukuran pesan handshake initiation/response di whitepaper WireGuard. Metadata (siapa bicara dengan siapa, kapan, seberapa banyak) tetap bocor. VPN menyembunyikan isi, bukan keberadaan.'
    source: https://www.wireguard.com/papers/wireguard.pdf

  - id: b11
    tier: B
    type: text
    objective: wg-routing
    prompt: 'Site-to-site: klien A mengakses server LAN `10.71.0.50` di belakang peer B. Di lab, server LAN mencatat klien sebagai `10.71.0.2` (IP B). Kenapa, dan apa alternatifnya agar IP tunnel klien terlihat?'
    answers: ['B melakukan MASQUERADE; alternatifnya route di LAN ke 10.99.0.0/24 via B tanpa NAT']
    patterns: ['(?=.*(masquerade|snat|nat))(?=.*(route|rute|routing|gateway))']
    tests:
      pass: ['karena masquerade di B; alternatif tambahkan route 10.99.0.0/24 via B di LAN', 'nat di B, atau routing balik lewat B tanpa nat']
      fail: ['wireguard selalu menyembunyikan ip', 'server salah konfigurasi', 'dns']
    explanation: 'Di lab, B memakai `MASQUERADE -s 10.99.0.0/24 -o eth1`, sehingga log nginx LAN berisi `10.71.0.2`. Ini cara paling sederhana, karena server LAN otomatis membalas ke B. Tanpa NAT, setiap host LAN (atau router LAN-nya) butuh route `10.99.0.0/24 via B`, karena jalur balik adalah syarat yang sama seperti DNAT di modul 08. Hasilnya IP klien VPN asli terlihat untuk audit.'
    source: https://www.wireguard.com/quickstart/

  - id: b12
    tier: B
    type: text
    objective: wg-ops
    prompt: Lewat `wg0` (MTU 1420), berapa nilai `ping -M do -s` **terbesar** yang lolos?
    answers: ['1392']
    tests:
      fail: ['1420', '1472', '1393', '1400']
    explanation: '1420 − 20 (IP) − 8 (ICMP) = **1392**. Di lab: `s=1392: 1 received`, dan `s=1393: message too long, mtu=1420`. Error-nya langsung dari kernel lokal, karena interface wg0 sendiri yang terlalu kecil. Untuk TCP melewati tunnel, MSS = 1420 − 40 = 1380. Gateway site-to-site sebaiknya memasang MSS clamping untuk host LAN yang ber-MTU 1500 (modul 02).'
    source: https://man7.org/linux/man-pages/man8/wg-quick.8.html

  - id: b13
    tier: B
    type: text
    objective: wg-ops
    prompt: Kenapa MTU WireGuard default 1420, bukan 1500 − (header IPv4 luar + UDP + WireGuard) = 1440?
    answers: ['untuk memberi ruang header luar IPv6 (40 byte), total overhead 80']
    patterns: ['ipv6', '80']
    tests:
      pass: ['agar aman juga untuk endpoint IPv6 (overhead 80 byte)', 'header luar ipv6 40 byte']
      fail: ['karena enkripsi lebih besar', 'bug', 'karena tcp']
    explanation: 'Overhead WireGuard = IP luar (20 untuk IPv4, **40** untuk IPv6) + UDP 8 + header data WireGuard 32 (16 header + 16 auth tag). 1500 − 80 = 1420 aman untuk kedua versi IP. Di link IPv4 murni, 1440 masih muat, tetapi default yang lebih konservatif menghindari masalah saat endpoint berpindah ke IPv6.'
    source: https://www.wireguard.com/papers/wireguard.pdf

  - id: b14
    tier: B
    type: text
    objective: wg-routing
    prompt: Peer klien memakai `AllowedIPs = 10.99.0.0/24, 10.71.0.0/24`. Lewat mana trafik ke internet (mis. `1.1.1.1`) dikirim?
    answers: ['lewat koneksi internet biasa, bukan VPN (split tunnel)']
    patterns: ['(split|bukan (lewat )?(vpn|tunnel)|tidak lewat (vpn|tunnel)|internet (biasa|lokal|langsung)|default gateway (lokal|biasa))']
    tests:
      pass: ['split tunnel: internet lewat jalur biasa', 'tidak lewat vpn']
      fail: ['lewat vpn', 'diblokir', 'Required key not available']
    explanation: 'Hanya tujuan yang cocok dengan AllowedIPs yang dikirim ke peer itu. Sisanya mengikuti tabel routing biasa (lab: `1.1.1.1` tidak ada di AllowedIPs). Split tunnel hemat bandwidth VPN dan latensi, tetapi trafik internet pengguna tidak melewati kontrol perusahaan. Pilih sesuai kebijakan keamanan.'
    source: https://www.wireguard.com/#cryptokey-routing

  - id: b15
    tier: B
    type: text
    objective: ssh-jump
    prompt: Kenapa `ssh -J bastion target` lebih aman daripada login ke bastion lalu `ssh target` dari sana dengan agent forwarding (`-A`)?
    answers: ['dengan -J kunci dan sesi terenkripsi end-to-end dari laptop; dengan -A, root di bastion bisa memakai agent-mu untuk login ke server lain']
    patterns: ['(agent|-a).*(disalahgunakan|dipakai|bajak|abuse|hijack|root|admin bastion)', '(end-to-end|kunci (tetap )?di laptop|tidak (ada|perlu) kunci di bastion)']
    tests:
      pass: ['dengan -A root bastion bisa memakai agent kita; -J end-to-end dan kunci tetap di laptop', 'agent forwarding bisa dibajak di bastion']
      fail: ['lebih cepat', 'tidak ada bedanya', '-J tidak terenkripsi']
    explanation: 'Dengan `-A`, socket agent-mu tersedia di bastion selama sesi, sehingga siapa pun yang root di sana (atau penyerang yang menguasainya) bisa memakai agent untuk masuk ke server lain atas namamu. Dengan `-J` (lab: `ssh -J dev@10.67.0.22 dev@10.68.0.30`), bastion hanya meneruskan stream terenkripsi, sehingga tidak pernah melihat kunci maupun isi sesi.'
    source: https://man.openbsd.org/ssh.1

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: ssh-forward
    prompt: |
      Developer perlu membuka database production Postgres (`db.internal`, listen hanya di `127.0.0.1:5432`) dengan
      GUI di laptopnya. Server hanya bisa dicapai lewat bastion `bastion.example.com`. Rancang aksesnya (config SSH,
      perintah, dan pengaturan sisi server) seaman mungkin (satu poin per baris).
    checklist:
      - id: jump
        label: ProxyJump/-J lewat bastion
        required: true
        patterns: ['proxyjump', '\s-J\s']
      - id: local
        label: Local forward ke 127.0.0.1:5432 di db
        required: true
        patterns: ['-L\s*(127\.0\.0\.1:)?\d+:(127\.0\.0\.1|localhost):5432', 'localforward\s+\S+\s+(127\.0\.0\.1|localhost):5432']
      - id: bind-local
        label: Port lokal hanya di 127.0.0.1 (default) / tidak 0.0.0.0
        patterns: ['127\.0\.0\.1:\d+:', 'localhost', 'jangan 0\.0\.0\.0', 'hanya (di )?laptop']
      - id: keys
        label: Autentikasi kunci (tanpa password) & tanpa agent forwarding
        patterns: ['(kunci|key|ed25519|identityfile)', 'forwardagent no', 'tanpa -a']
      - id: server
        label: Pembatasan di server (PermitOpen/AllowTcpForwarding untuk user tertentu)
        patterns: ['permitopen', 'allowtcpforwarding', 'match user']
      - id: readonly
        label: User DB read-only / least privilege
        patterns: ['(read-?only|readonly|least privilege|hak minimal|role terbatas)']
      - id: no-publish
        label: Tidak membuka 5432 ke publik / firewall
        forbid: true
        patterns: ['0\.0\.0\.0:5432', 'ports:?[^\n]*"?5432:5432', 'listen_addresses\s*=\s*.\*']
    pass_score: 0.5
    solution: |
      ```text
      ~/.ssh/config (laptop):
        Host bastion
          HostName bastion.example.com
          User dev
          IdentityFile ~/.ssh/id_ed25519
          ForwardAgent no
        Host db
          HostName db.internal
          User dev
          ProxyJump bastion
          LocalForward 127.0.0.1:15432 127.0.0.1:5432
      Perintah:   ssh -N db        → GUI ke 127.0.0.1:15432 (user Postgres read-only)
      Server db (sshd_config):
        Match User dev
          AllowTcpForwarding local
          PermitOpen 127.0.0.1:5432
      Postgres tetap listen_addresses = 'localhost'; tidak ada port 5432 yang di-publish ke internet
      ```
      Setiap pilihan didasarkan pada lab:
      - `-L` membuat service yang hanya di `127.0.0.1` server bisa diakses (`bastion-local-only`).
      - Default bind `127.0.0.1` di laptop (dengan `0.0.0.0` siapa pun di Wi-Fi bisa memakai tunnel).
      - `-J` end-to-end, tanpa agent di bastion.
      - `AllowTcpForwarding`/`PermitOpen` membatasi tunnel (tanpa izin → `administratively prohibited`).

      Akses ini tetap mengarah ke production: pakai role DB read-only, dan catat siapa yang membuka tunnel (log
      bastion).
    tests:
      pass:
        - |
          Host db dengan ProxyJump bastion
          LocalForward 127.0.0.1:15432 127.0.0.1:5432
          IdentityFile ~/.ssh/id_ed25519 dan ForwardAgent no
          ssh -N db lalu GUI ke 127.0.0.1:15432 dengan user read-only
          server: Match User dev, AllowTcpForwarding local, PermitOpen 127.0.0.1:5432
        - |
          ssh -J dev@bastion.example.com -N -L 15432:127.0.0.1:5432 dev@db.internal
          pakai kunci ed25519
          user postgres readonly
      fail:
        - |
          ubah listen_addresses = '*'
          buka firewall 5432
          ssh -J bastion -L 15432:127.0.0.1:5432 db
        - |
          ssh bastion
          lalu psql dari bastion pakai password admin
        - |
          docker ports "5432:5432"
          proxyjump bastion
          -L 15432:localhost:5432
    source: https://man.openbsd.org/ssh_config.5

  - id: c02
    tier: C
    type: scenario
    objective: wg-routing
    prompt: |
      Hubungkan LAN kantor `192.168.10.0/24` (gateway Linux kantor, di belakang NAT ISP) dengan VPC cloud `10.20.0.0/16`
      (VM gateway dengan IP publik `203.0.113.50`) memakai WireGuard site-to-site, tunnel `10.99.0.0/30`. Tulis
      konfigurasi kedua sisi dan langkah verifikasinya (satu poin per baris).
    checklist:
      - id: keys
        label: Kunci per sisi (genkey/pubkey), public key saling ditukar
        patterns: ['genkey', 'pubkey', 'publickey']
      - id: allowed-office
        label: Di gateway kantor, AllowedIPs peer cloud = 10.20.0.0/16 (+ IP tunnel)
        required: true
        patterns: ['allowedips[^\n]*10\.20\.0\.0/16']
      - id: allowed-cloud
        label: Di gateway cloud, AllowedIPs peer kantor = 192.168.10.0/24 (+ IP tunnel)
        required: true
        patterns: ['allowedips[^\n]*192\.168\.10\.0/24']
      - id: endpoint
        label: Kantor memakai Endpoint 203.0.113.50:51820 + PersistentKeepalive (di belakang NAT)
        required: true
        patterns: ['persistentkeepalive', 'endpoint[^\n]*203\.0\.113\.50']
      - id: forward
        label: ip_forward=1 dan route di LAN/VPC (route table menunjuk ke gateway WG) atau NAT
        patterns: ['ip_forward', 'route table', 'route[^\n]*(via|ke) (gateway|192\.168\.10\.\d+|10\.20\.\d+\.\d+)', 'masquerade']
      - id: firewall
        label: Buka UDP 51820 di sisi cloud (security group)
        patterns: ['udp[^\n]*51820', '51820[^\n]*udp']
      - id: mtu
        label: MTU/MSS clamping untuk host LAN
        patterns: ['mtu', 'mss', 'tcpmss']
      - id: verify
        label: Verifikasi wg show (latest handshake) + ping lintas LAN
        patterns: ['wg show', 'latest handshake', 'handshake']
      - id: no-full
        label: Tidak memakai AllowedIPs 0.0.0.0/0 di gateway site
        forbid: true
        patterns: ['allowedips\s*=\s*0\.0\.0\.0/0']
    pass_score: 0.3
    solution: |
      ```ini
      # cloud gateway /etc/wireguard/wg0.conf
      [Interface]
      Address = 10.99.0.1/30
      ListenPort = 51820
      PrivateKey = <cloud.key>
      [Peer]                       # kantor
      PublicKey = <office.pub>
      AllowedIPs = 10.99.0.2/32, 192.168.10.0/24

      # office gateway /etc/wireguard/wg0.conf
      [Interface]
      Address = 10.99.0.2/30
      PrivateKey = <office.key>
      [Peer]                       # cloud
      PublicKey = <cloud.pub>
      Endpoint = 203.0.113.50:51820
      AllowedIPs = 10.99.0.1/32, 10.20.0.0/16
      PersistentKeepalive = 25     # kantor di belakang NAT ISP
      ```
      ```text
      kedua gateway: sysctl net.ipv4.ip_forward=1 ; wg-quick up wg0 ; systemctl enable wg-quick@wg0
      routing: route table VPC 192.168.10.0/24 → instance gateway (matikan source/dest check);
               router LAN kantor 10.20.0.0/16 → gateway kantor   (atau MASQUERADE bila tidak bisa)
      security group cloud: izinkan UDP 51820 dari IP publik kantor
      MSS clamping di kedua gateway: iptables -t mangle -A FORWARD -o wg0 -p tcp --syn -j TCPMSS --clamp-mss-to-pmtu
      verifikasi: wg show (latest handshake < 2 menit), ping 10.20.x.x dari host LAN, curl layanan di VPC
      ```
      Konfigurasi ini mengikuti persis apa yang dibuktikan lab:
      - AllowedIPs adalah tabel routing sekaligus filter. Tujuan di luar AllowedIPs → `Required key not available`,
        dan sumber di luar AllowedIPs dibuang diam-diam.
      - Site-to-site butuh forwarding dan jalur balik (lab: MASQUERADE → server LAN melihat IP gateway).
      - MTU tunnel 1420 (`-s 1393` → `message too long`).

      `0.0.0.0/0` di gateway site akan menarik semua trafik ke tunnel.
    tests:
      pass:
        - |
          wg genkey | tee key | wg pubkey > pub di kedua sisi
          cloud: AllowedIPs = 10.99.0.2/32, 192.168.10.0/24
          kantor: Endpoint = 203.0.113.50:51820, AllowedIPs = 10.99.0.1/32, 10.20.0.0/16, PersistentKeepalive = 25
          ip_forward=1 dan route table VPC 192.168.10.0/24 ke instance gateway
          security group izinkan udp 51820
          mss clamping karena mtu 1420
          wg show cek latest handshake lalu ping antar lan
        - |
          AllowedIPs = 192.168.10.0/24 di cloud
          AllowedIPs = 10.20.0.0/16 di kantor
          PersistentKeepalive = 25
      fail:
        - |
          AllowedIPs = 0.0.0.0/0 di kedua sisi
          PersistentKeepalive = 25
          AllowedIPs 192.168.10.0/24
          AllowedIPs 10.20.0.0/16
        - |
          buka port 51820 tcp
          pakai password
        - |
          AllowedIPs = 10.99.0.0/30
          wg show
    source: https://www.wireguard.com/quickstart/

  - id: c03
    tier: C
    type: scenario
    objective: wg-ops
    prompt: |
      Pengguna VPN WireGuard melapor tiga keluhan berbeda:
      - A: "sama sekali tidak tersambung"
      - B: "tersambung, ping ke gateway VPN bisa, tapi server internal 10.20.5.10 tidak bisa"
      - C: "SSH ke server lancar, tapi buka halaman web besar/`git clone` macet"

      Untuk masing-masing, tulis apa yang dicek dan kemungkinan penyebabnya (satu poin per baris).
    checklist:
      - id: handshake
        label: A — cek wg show latest handshake (tidak ada handshake = UDP/endpoint/kunci)
        required: true
        patterns: ['(latest )?handshake']
      - id: a-causes
        label: A — penyebab UDP diblokir / endpoint salah / public key salah
        patterns: ['(udp|51820|endpoint|public ?key|kunci)']
      - id: allowedips
        label: B — AllowedIPs tidak mencakup 10.20.5.10 (Required key not available) / route
        required: true
        patterns: ['allowedips', 'required key not available', 'cryptokey']
      - id: b-return
        label: B — forwarding/jalur balik/NAT di gateway VPN atau firewall internal
        patterns: ['(ip_forward|route balik|jalur balik|return|masquerade|forward)']
      - id: mtu
        label: C — MTU/PMTU (ping -M do -s, MSS clamping, MTU lebih kecil)
        required: true
        patterns: ['mtu', 'mss', '-m\s*do']
      - id: no-disable
        label: Tidak menyarankan mematikan firewall total
        forbid: true
        patterns: ['ufw\s+disable', 'iptables\s+-F', 'matikan (semua )?firewall']
    pass_score: 0.6
    solution: |
      ```text
      A (tidak tersambung) → wg show: "latest handshake" tidak ada / sangat lama
         → UDP 51820 diblokir (Wi-Fi/firewall/security group), Endpoint salah, public key tertukar/salah,
           jam sistem sangat salah; cek tcpdump -nn udp port 51820 di server: paket datang? dibalas?
           (lab: handshake terlihat sebagai UDP length 148 → 92)
      B (gateway ok, server internal tidak) → di klien: ip route get 10.20.5.10 ; ping → "Required key not available"
           berarti AllowedIPs klien tidak mencakup 10.20.0.0/16 (lab persis); bila handshake & AllowedIPs benar:
           gateway VPN ip_forward=1? firewall FORWARD? server internal punya jalur balik ke 10.99.0.0/24 (atau
           MASQUERADE di gateway — lab: server LAN melihat IP gateway)? AllowedIPs di SERVER mencakup IP klien?
      C (kecil lancar, besar macet) → MTU/PMTU: ping -M do -s 1392 10.20.5.10 (lab: 1392 ok, 1393 message too long
           di wg0 1420); jalur di atas tunnel lebih kecil (PPPoE/seluler) → turunkan MTU wg0 (mis. 1380) atau
           MSS clamping di gateway (modul 02)
      ```
    tests:
      pass:
        - |
          A: wg show tidak ada latest handshake, cek udp 51820 diblokir atau endpoint dan public key salah
          B: ping memberi Required key not available, AllowedIPs klien tidak mencakup 10.20.0.0/16; cek ip_forward dan route balik di gateway
          C: masalah mtu, uji ping -M do -s 1392, pasang mss clamping atau turunkan mtu wg0
        - |
          handshake tidak ada: firewall udp
          allowedips kurang subnet internal
          mtu terlalu besar
      fail:
        - |
          handshake tidak ada
          allowedips salah
          mtu
          ufw disable di server
        - |
          restart vpn
          ganti dns
        - |
          reinstall wireguard
    source: https://man7.org/linux/man-pages/man8/wg.8.html

---

# Tunneling & VPN

Banyak server penting sengaja tidak bisa dijangkau dari internet: database, panel admin, jaringan privat VPC. Tunnel
adalah cara aman untuk sampai ke sana. SSH cocok untuk akses ad-hoc satu orang ke satu layanan, sedangkan WireGuard
untuk koneksi jaringan ke jaringan yang permanen. Modul ini membangun keduanya di lab dan memperlihatkan perilaku
yang sering membingungkan.

> [!NOTE]
> Lab SSH: laptop (`10.67.0.10`) → bastion (`10.67.0.22` / `10.68.0.22`) → host privat `dj-intra` (`10.68.0.30`,
> network `--internal`). Lab WireGuard: peer A (`10.70.0.10`) ↔ peer B (`10.70.0.20`, juga di LAN `10.71.0.0/24`
> dengan server nginx `10.71.0.50`), tunnel `10.99.0.0/24`.

## 1. SSH port forwarding

```mermaid
flowchart LR
    subgraph laptop
      A["app → 127.0.0.1:18080"]
    end
    A -- "-L 18080:10.68.0.30:8080<br/>terenkripsi" --> B[bastion sshd]
    B -- "TCP biasa (sumber: bastion)" --> I["dj-intra:8080"]
```

| Mode | Perintah (lab) | Port dibuka di | Hasil |
|---|---|---|---|
| Local | `ssh -f -N -L 15432:127.0.0.1:5432 dev@bastion` | laptop `127.0.0.1` | Service localhost-only di bastion terjangkau |
| Local ke host lain | `-L 18080:10.68.0.30:8080` | laptop | `intra-web`, dengan intra melihat sumber **10.68.0.22** |
| Dynamic (SOCKS) | `-D 1080` + `curl --socks5-hostname` | laptop | Tujuan apa pun di jaringan bastion |
| Remote | `-R 13000:127.0.0.1:3000` | **server** `127.0.0.1` | Bastion melihat `laptop-dev-server` |
| Jump | `ssh -J dev@bastion dev@10.68.0.30` | — | `dj-intra`, end-to-end |

Default bind-nya `127.0.0.1` di kedua arah (lab: `-L` → `127.0.0.1:18080`, `-R` → `127.0.0.1:13000` dengan
`GatewayPorts no`). Membuka ke `0.0.0.0` harus dilakukan secara sengaja.

## 2. Mengatur sshd

```text
AllowTcpForwarding no  →  channel 2: open failed: administratively prohibited: open failed
GatewayPorts yes (ditambah di AKHIR file)  →  sshd -T: gatewayports no   ← baris 91 "GatewayPorts no" menang
GatewayPorts yes (mengganti baris 91)      →  0.0.0.0:13003 LISTEN
```

> [!WARNING]
> Di `sshd_config`, **nilai pertama yang ditemukan yang berlaku**, termasuk dari `Include
> /etc/ssh/sshd_config.d/*.conf` di bagian atas. Menambah baris di akhir file sering tidak berefek. Selalu
> verifikasi dengan `sshd -T`, dan cek sintaks dengan `sshd -t` sebelum reload.

Pembatasan yang lebih halus: `Match User dev` + `AllowTcpForwarding local` + `PermitOpen 127.0.0.1:5432`.

## 3. WireGuard dari nol

```bash
umask 077; wg genkey | tee privatekey | wg pubkey > publickey
ip link add wg0 type wireguard
wg set wg0 private-key ./privatekey listen-port 51820 \
   peer <PUB_B> endpoint 10.70.0.20:51820 allowed-ips 10.99.0.2/32 persistent-keepalive 25
ip addr add 10.99.0.1/24 dev wg0 && ip link set wg0 up
wg show
```

Yang terlihat di jaringan (tcpdump underlay, lab):

```text
10.70.0.10.51820 > 10.70.0.20.51820: UDP, length 148    ← handshake initiation
10.70.0.20.51820 > 10.70.0.10.51820: UDP, length 92     ← handshake response
10.70.0.10.51820 > 10.70.0.20.51820: UDP, length 32     ← keepalive/data
```

Alamat `10.99.0.x` dan isi paket tidak terlihat, tetapi siapa bicara dengan siapa, kapan, dan seberapa banyak tetap
terlihat.

## 4. AllowedIPs = cryptokey routing

| Arah | Fungsi AllowedIPs | Lab |
|---|---|---|
| Keluar | Tujuan → peer mana | `10.71.0.0/24` tidak ada → `ping: sendmsg: Required key not available` |
| Masuk | Filter sumber dari peer itu | Sumber palsu `10.99.0.77` → dibuang diam-diam (`0 received`), sumber sah → `2 received` |

| Pola | AllowedIPs klien | Efek |
|---|---|---|
| Split tunnel | `10.99.0.0/24, 10.71.0.0/24` | Hanya subnet itu lewat VPN |
| Full tunnel | `0.0.0.0/0` | Semua trafik lewat VPN |

Site-to-site (lab): AllowedIPs A ditambah `10.71.0.0/24`, B `ip_forward=1` + MASQUERADE, lalu ping `ttl=63`, web
LAN `200`, dan server LAN melihat **10.71.0.2**. Tanpa NAT, LAN butuh route `10.99.0.0/24 via B`.

## 5. MTU dan keepalive

| | Nilai | Lab |
|---|---|---|
| MTU wg0 | 1420 (overhead 80 = IPv6 40 + UDP 8 + WG 32) | `ip link`: `mtu 1420` |
| `ping -M do -s` maksimum | 1392 | 1392 OK, 1393 → `message too long, mtu=1420` |
| MSS TCP lewat tunnel | 1380 | — |
| PersistentKeepalive | 25 s untuk peer di belakang NAT | `every 25 seconds` |

## 6. Ringkasan troubleshooting

| Gejala | Cek | Kemungkinan |
|---|---|---|
| SSH tunnel tersambung, tapi koneksi gagal | Output `-v`: `administratively prohibited` | `AllowTcpForwarding`/`PermitOpen` |
| Host lain tidak bisa memakai port tunnel | `netstat -ltn`: `127.0.0.1` | Bind default / `GatewayPorts` |
| Opsi sshd tidak berefek | `sshd -T` | Nilai pertama menang |
| VPN tidak tersambung | `wg show`: tidak ada handshake | UDP diblokir, endpoint, atau kunci |
| `Required key not available` | AllowedIPs | Tujuan tidak dipetakan ke peer |
| Gateway bisa, LAN tidak | Forwarding, jalur balik, AllowedIPs server | NAT/route |
| Kecil lancar, besar macet | `ping -M do -s` | MTU/MSS |
