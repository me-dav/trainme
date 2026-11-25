---
format: 1
id: sec-hardening-server-access
course:
  slug: devsecops
  title: DevSecOps
  description: Keamanan untuk DevOps dari dua sisi — cara kerja red team dan cara blue team mendeteksi serta menutupnya. Model ancaman, hardening server & container, keamanan web, ketersediaan (anti-DDoS), deteksi, respons insiden, dan keamanan pipeline. Semua praktik di lab terisolasi milik sendiri.
title: Hardening Server & Akses
summary: "Menutup jalan masuk paling umum ke server — login. Sisi red: brute-force & dictionary attack ke SSH. Sisi blue: autentikasi kunci (mematikan password), larang login root, MaxAuthTries & AllowUsers, firewall default-deny, fail2ban yang mem-ban IP penyerang otomatis lewat iptables, serta least privilege (non-root + sudo). Semua dibuktikan di lab: brute-force gagal total setelah password dimatikan, dan IP penyerang diblokir setelah 3 kegagalan."
order: 3
version: 1
status: verified
updated: '2026-11-25'
tested_on:
  - Docker Desktop (Windows) — target alpine:3.20 (OpenSSH + PAM, fail2ban, iptables, busybox syslogd), cap NET_ADMIN
  - attacker alpine:3.20 — openssh-client, sshpass, nmap; brute-force via loop sshpass di network lab lokal
objectives:
  - id: ssh-keys
    text: Memahami autentikasi kunci SSH dan mengapa lebih kuat dari password
  - id: ssh-harden
    text: Mengeraskan sshd — larang root, matikan password, batasi user & percobaan
  - id: firewall
    text: Menerapkan firewall default-deny yang hanya membuka port yang perlu
  - id: brute-defense
    text: Memasang fail2ban untuk mendeteksi dan memblokir brute-force otomatis
  - id: least-priv
    text: Menjalankan dan mengakses server dengan hak seminimal mungkin (non-root + sudo)
sources:
  - title: OpenSSH — sshd_config(5)
    url: https://man.openbsd.org/sshd_config.5
  - title: OpenSSH — sshd(8) -T (effective config) & -t (test)
    url: https://man.openbsd.org/sshd.8
  - title: ssh-keygen(1)
    url: https://man.openbsd.org/ssh-keygen.1
  - title: fail2ban — jail configuration
    url: https://github.com/fail2ban/fail2ban/wiki/Proper-fail2ban-configuration
  - title: netfilter/iptables — packet filtering HOWTO
    url: https://www.netfilter.org/documentation/HOWTO/packet-filtering-HOWTO.html
  - title: MITRE ATT&CK — Brute Force (T1110)
    url: https://attack.mitre.org/techniques/T1110/
  - title: CIS Benchmarks — Linux server hardening
    url: https://www.cisecurity.org/cis-benchmarks
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: brute-defense
    prompt: Serangan menebak password dengan mencoba banyak kombinasi berulang kali (dari wordlist) disebut apa?
    answers: ['brute-force']
    patterns: ['brute[- ]?force', 'brute', 'dictionary attack', 'tebak(an)? password']
    tests:
      fail: ['phishing', 'sql injection', 'man in the middle', 'xss']
    explanation: 'Di lab, attacker mencoba `123456 password admin root letmein ...` ke SSH; server mencatat `Failed password for dev from 172.18.0.3` tiap percobaan. Brute-force (ATT&CK T1110) adalah serangan paling tua dan paling umum ke layanan login. Variannya: dictionary attack (wordlist), credential stuffing (pakai kredensial bocor dari situs lain), dan password spraying (satu password ke banyak akun). Pertahanan utamanya: hilangkan password sebagai faktor (a05).'
    source: https://attack.mitre.org/techniques/T1110/

  - id: a02
    tier: A
    type: command
    objective: ssh-keys
    prompt: Buat pasangan kunci SSH bertipe **ed25519** dengan ssh-keygen.
    answers: ['ssh-keygen -t ed25519']
    patterns: ['^(?=.*\s-t ed25519(\s|$))ssh-keygen( \S+)*$']
    tests:
      pass: ['ssh-keygen -t ed25519 -C laptop-dev', 'ssh-keygen -t ed25519 -f /root/.ssh/id_ed25519']
      fail: ['ssh-keygen -t rsa', 'ssh-keygen', 'ssh -t ed25519', 'ssh-keygen -t dsa']
    explanation: 'Di lab, kunci ed25519 dibuat di attacker lalu public key-nya dipasang di `~dev/.ssh/authorized_keys` server. ed25519 modern, pendek, dan cepat — lebih disukai daripada RSA lama. Kunci privat tidak pernah meninggalkan mesinmu; hanya public key yang disebar. Inilah dasar autentikasi tanpa password: penyerang tidak bisa menebak kunci 256-bit seperti menebak password.'
    source: https://man.openbsd.org/ssh-keygen.1

  - id: a03
    tier: A
    type: text
    objective: ssh-harden
    prompt: Direktif `sshd_config` untuk **melarang login sebagai root** lewat SSH?
    answers: ['PermitRootLogin']
    patterns: ['permitrootlogin( no)?']
    tests:
      fail: ['AllowRootLogin', 'RootLogin', 'DenyUsers', 'PermitLogin']
    explanation: 'Di lab, default `permitrootlogin without-password`; setelah `PermitRootLogin no`, `ssh root@server` → `Permission denied`. root adalah target nomor satu brute-force karena selalu ada dan super-powerful. Dengan melarangnya, penyerang harus menebak **nama user yang benar dulu** baru passwordnya. Admin login sebagai user biasa lalu `sudo` (least privilege, a12).'
    source: https://man.openbsd.org/sshd_config.5

  - id: a04
    tier: A
    type: command
    objective: ssh-harden
    prompt: Tampilkan **konfigurasi efektif** sshd (nilai yang benar-benar berlaku) untuk memverifikasi hasil hardening.
    answers: ['sshd -T']
    patterns: ['^(/usr/sbin/)?sshd -T$']
    tests:
      pass: ['/usr/sbin/sshd -T']
      fail: ['sshd -t', 'sshd -D', 'cat /etc/ssh/sshd_config', 'ssh -T']
    explanation: 'Di lab, `sshd -T` memperlihatkan `permitrootlogin no`, `passwordauthentication no`, `maxauthtries 3`, `allowusers dev` setelah hardening. Penting karena `sshd_config` memakai **nilai pertama yang ditemukan** dan menarik `Include` dari `sshd_config.d/*` — jadi apa yang kamu tulis belum tentu yang berlaku (pelajaran dari board Jaringan modul 09). `sshd -T` (besar) menampilkan config efektif; `sshd -t` (kecil) hanya menguji sintaks.'
    source: https://man.openbsd.org/sshd.8

  - id: a05
    tier: A
    type: text
    objective: ssh-harden
    prompt: Direktif `sshd_config` yang, bila di-set `no`, **mematikan login password** sehingga brute-force password jadi sia-sia?
    answers: ['PasswordAuthentication']
    patterns: ['passwordauthentication( no)?']
    tests:
      fail: ['PubkeyAuthentication', 'ChallengeResponseAuthentication', 'PermitEmptyPasswords', 'UsePAM']
    explanation: 'Di lab, setelah `PasswordAuthentication no`, brute-force → `Permission denied (publickey,keyboard-interactive)` untuk semua password, sedangkan login kunci tetap jalan. Inilah pertahanan terkuat terhadap brute-force: kalau tidak ada password untuk ditebak, serangan tebak-password mati total. Pastikan kunci sudah terpasang dan teruji **sebelum** mematikan password, agar tidak mengunci diri sendiri.'
    source: https://man.openbsd.org/sshd_config.5

  - id: a06
    tier: A
    type: command
    objective: ssh-harden
    prompt: Uji **sintaks** berkas `sshd_config` sebelum me-reload service (agar tidak mengunci diri karena salah ketik).
    answers: ['sshd -t']
    patterns: ['^(/usr/sbin/)?sshd -t$']
    tests:
      pass: ['/usr/sbin/sshd -t']
      fail: ['sshd -T', 'sshd -D', 'systemctl reload sshd', 'ssh -t']
    explanation: 'Di lab, `sshd -t && echo syntax_ok` dijalankan sebelum restart. Kesalahan ketik di `sshd_config` bisa membuat sshd gagal start — dan kalau itu satu-satunya akses ke server remote, kamu terkunci. Alur aman: edit → `sshd -t` (cek sintaks) → reload → **buka sesi baru untuk tes** sambil sesi lama tetap terbuka sebagai jaring pengaman.'
    source: https://man.openbsd.org/sshd.8

  - id: a07
    tier: A
    type: text
    objective: ssh-harden
    prompt: Direktif `sshd_config` yang membatasi **jumlah percobaan autentikasi** per koneksi (lab di-set 3)?
    answers: ['MaxAuthTries']
    patterns: ['maxauthtries']
    tests:
      fail: ['MaxSessions', 'MaxStartups', 'LoginGraceTime', 'MaxTries']
    explanation: 'Di lab, default `maxauthtries 6`; diturunkan ke `3`. Nilai ini membatasi berapa kali satu koneksi boleh gagal sebelum diputus, memperlambat brute-force (penyerang harus membuka koneksi baru tiap beberapa percobaan). Bukan pertahanan utama (penyerang tinggal reconnect), tapi memperbesar jejak di log dan memperlambat. Lengkapi dengan `LoginGraceTime` kecil dan `MaxStartups` untuk membatasi koneksi setengah-terbuka.'
    source: https://man.openbsd.org/sshd_config.5

  - id: a08
    tier: A
    type: text
    objective: brute-defense
    prompt: Tool yang memantau log autentikasi dan otomatis mem-**ban IP** yang gagal login berkali-kali (lewat firewall)?
    answers: ['fail2ban']
    patterns: ['fail2ban']
    tests:
      fail: ['logrotate', 'rsyslog', 'auditd', 'iptables']
    explanation: 'Di lab, fail2ban dengan jail `sshd` (`maxretry 3`) memantau `/var/log/messages`; setelah 5 kegagalan dari `172.18.0.3`, statusnya `Currently banned: 1, Banned IP list: 172.18.0.3`. Ia membaca log → mencocokkan pola gagal → memerintah firewall memblokir IP untuk `bantime` tertentu. Ini pertahanan yang bagus bahkan ketika password sudah dimatikan, karena mengurangi kebisingan scan dan memperlambat penyerang.'
    source: https://github.com/fail2ban/fail2ban/wiki/Proper-fail2ban-configuration

  - id: a09
    tier: A
    type: command
    objective: brute-defense
    prompt: Tampilkan **status jail `sshd`** di fail2ban (berapa yang gagal dan IP mana yang diban).
    answers: ['fail2ban-client status sshd']
    patterns: ['^fail2ban-client status sshd$']
    tests:
      pass: ['sudo fail2ban-client status sshd']
      fail: ['fail2ban-client status', 'fail2ban-client reload sshd', 'systemctl status fail2ban', 'fail2ban status sshd']
    explanation: 'Di lab → `Total failed: 5`, `Currently banned: 1`, `Banned IP list: 172.18.0.3`. `fail2ban-client status` (tanpa jail) menampilkan daftar jail; dengan nama jail, menampilkan detailnya. Untuk melepas ban (mis. kamu salah terblokir): `fail2ban-client set sshd unbanip <IP>`. Pantau status ini untuk melihat serangan yang sedang berlangsung.'
    source: https://github.com/fail2ban/fail2ban/wiki/Proper-fail2ban-configuration

  - id: a10
    tier: A
    type: command
    objective: firewall
    prompt: Set **kebijakan default** chain `INPUT` iptables menjadi `DROP` (tolak semua yang tidak diizinkan eksplisit).
    answers: ['iptables -P INPUT DROP']
    patterns: ['^iptables -P INPUT DROP$']
    tests:
      pass: ['sudo iptables -P INPUT DROP']
      fail: ['iptables -P INPUT ACCEPT', 'iptables -A INPUT -j DROP', 'iptables -P OUTPUT DROP', 'iptables -F INPUT']
    explanation: 'Di lab, `iptables -P INPUT DROP` membuat semua paket masuk ditolak kecuali yang diizinkan aturan berikutnya. Inilah default-deny (modul 02): aman secara bawaan. **Peringatan:** jalankan allow untuk SSH/loopback/established **lebih dulu** atau bersamaan — men-DROP default pada sesi remote sebelum mengizinkan SSH akan langsung memutus koneksimu. Praktik aman: pakai skrip yang auto-rollback (mis. `iptables-apply`).'
    source: https://www.netfilter.org/documentation/HOWTO/packet-filtering-HOWTO.html

  - id: a11
    tier: A
    type: command
    objective: firewall
    prompt: Tambahkan aturan iptables yang **mengizinkan** koneksi masuk ke port TCP `22` (SSH) pada chain INPUT.
    answers: ['iptables -A INPUT -p tcp --dport 22 -j ACCEPT']
    patterns: ['^iptables -A INPUT -p tcp( -m tcp)? --dport 22 -j ACCEPT$']
    tests:
      pass: ['iptables -A INPUT -p tcp -m tcp --dport 22 -j ACCEPT', 'sudo iptables -A INPUT -p tcp --dport 22 -j ACCEPT']
      fail: ['iptables -A INPUT -p tcp --dport 22 -j DROP', 'iptables -A INPUT --dport 22 -j ACCEPT', 'iptables -A OUTPUT -p tcp --dport 22 -j ACCEPT']
    explanation: 'Di lab, dengan default `INPUT DROP`, aturan ini membuka hanya port 22. Lengkapnya: izinkan `lo` (loopback), lalu `conntrack ESTABLISHED,RELATED` (agar balasan koneksi keluar bisa masuk), baru port layanan. Setelah itu, port lain seperti 8080 otomatis tertutup — nmap melihatnya `filtered`, bukan `closed`. Idealnya batasi juga sumbernya: `-s <IP admin>` untuk SSH, agar hanya dari alamat tepercaya.'
    source: https://www.netfilter.org/documentation/HOWTO/packet-filtering-HOWTO.html

  - id: a12
    tier: A
    type: text
    objective: least-priv
    prompt: Alih-alih login sebagai root, praktik yang dianjurkan adalah login sebagai user biasa lalu menaikkan hak sementara dengan perintah apa?
    answers: ['sudo']
    patterns: ['^sudo$', '\bsudo\b']
    tests:
      fail: ['su root', 'doas saja', 'chmod', 'setuid']
    explanation: 'Least privilege di server: akun harian tidak punya hak root; saat butuh, pakai `sudo` yang tercatat di log (siapa, kapan, perintah apa). Ini membatasi kerusakan bila akun itu jatuh dan memberi jejak audit. Gabungkan dengan `PermitRootLogin no` (a03): root tidak bisa diakses langsung dari jaringan sama sekali, hanya lewat user → sudo.'
    source: https://www.cisecurity.org/cis-benchmarks

  - id: a13
    tier: A
    type: text
    objective: brute-defense
    prompt: Di lab, fail2ban memblokir IP penyerang dengan memasang aturan di firewall mana?
    answers: ['iptables']
    patterns: ['iptables', 'netfilter', 'nftables', 'firewall']
    tests:
      fail: ['hosts.deny saja', 'DNS', 'nginx', 'selinux']
    explanation: 'Di lab, fail2ban memasang chain `f2b-sshd` dan aturan `-A f2b-sshd -s 172.18.0.3/32 -j REJECT --reject-with icmp-port-unreachable`. Jadi fail2ban sendiri tidak memblokir apa pun — ia **memerintah firewall** (iptables/nftables) untuk melakukannya. Akibatnya attacker, bahkan dengan kunci valid sekalipun, mendapat `Connection refused`. Action ini bisa diganti (mis. `nftables`, `route`, atau memblokir lewat cloud firewall).'
    source: https://github.com/fail2ban/fail2ban/wiki/Proper-fail2ban-configuration

  - id: a14
    tier: A
    type: text
    objective: ssh-keys
    prompt: Berkas di direktori `~/.ssh/` milik user di **server** yang berisi daftar public key yang boleh login sebagai user itu?
    answers: ['authorized_keys']
    patterns: ['authorized_keys']
    tests:
      fail: ['known_hosts', 'id_ed25519', 'config', 'authorized_hosts']
    explanation: 'Di lab, public key attacker ditaruh di `/home/dev/.ssh/authorized_keys` (chmod 600, folder 700) agar bisa login tanpa password. Bedakan: `authorized_keys` (di server, daftar kunci yang boleh masuk), `known_hosts` (di klien, sidik jari server yang dikenal), `id_ed25519`/`.pub` (pasangan kunci klien). Permission yang terlalu longgar pada file/folder ini membuat sshd menolak memakainya — fitur keamanan, bukan bug.'
    source: https://man.openbsd.org/sshd_config.5

  - id: a15
    tier: A
    type: text
    objective: firewall
    prompt: Setelah default-deny firewall aktif, `nmap` melihat port yang diblokir (tanpa balasan) dengan status apa — yang berbeda dari `closed`?
    answers: ['filtered']
    patterns: ['^filtered$', 'ter-?filter', 'difilter']
    tests:
      fail: ['closed', 'open', 'unreachable', 'refused']
    explanation: 'Di lab, setelah default-deny, `nmap -Pn -p 22,8080` → `22/tcp open`, `8080/tcp filtered`. `filtered` = firewall mem-DROP paket tanpa balasan (nmap tidak tahu port terbuka/tertutup). Bandingkan `closed` (modul 01/02) = host hidup membalas RST, tanpa firewall. Jadi default-deny tidak hanya menutup port, tapi juga membuat recon penyerang lebih buram — mereka tidak dapat konfirmasi cepat.'
    source: https://www.netfilter.org/documentation/HOWTO/packet-filtering-HOWTO.html

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: brute-defense
    prompt: Serangan yang memakai pasangan username+password yang **bocor dari situs lain** (dengan asumsi orang memakai ulang password) disebut apa?
    answers: ['credential stuffing']
    patterns: ['credential[- ]?stuffing', 'stuffing', 'isian kredensial']
    tests:
      fail: ['password spraying', 'brute-force murni', 'phishing', 'rainbow table']
    explanation: 'Credential stuffing berbeda dari brute-force buta: penyerang memakai kredensial nyata yang bocor dari breach lain, sehingga sedikit percobaan sudah bisa berhasil dan sulit dibedakan dari login sah. Pertahanan: jangan pakai ulang password, wajibkan MFA (b02), dan deteksi login dari lokasi/perangkat baru. fail2ban kurang efektif di sini karena percobaannya sedikit — MFA-lah kuncinya.'
    source: https://attack.mitre.org/techniques/T1110/

  - id: b02
    tier: B
    type: text
    objective: ssh-harden
    prompt: Faktor autentikasi **kedua** (selain kunci/password) — mis. kode TOTP atau hardware key — secara umum disebut apa (singkatan)?
    answers: ['MFA']
    patterns: ['^mfa$', '2fa', 'multi[- ]?factor', 'two[- ]?factor', 'otentikasi dua faktor']
    tests:
      fail: ['SSO', 'PAM', 'OTP saja', 'CAPTCHA']
    explanation: 'MFA/2FA menambah faktor "sesuatu yang kamu miliki" (TOTP, YubiKey) di atas "sesuatu yang kamu tahu/punya". Untuk SSH, bisa lewat PAM (`pam_google_authenticator`) atau sertifikat + hardware key. MFA mematikan credential stuffing dan phishing password: kredensial bocor saja tidak cukup. Di konteks cloud/panel admin, MFA adalah kontrol tunggal paling berdampak terhadap akun yang jatuh.'
    source: https://www.cisecurity.org/cis-benchmarks

  - id: b03
    tier: B
    type: text
    objective: ssh-harden
    prompt: Direktif `sshd_config` yang membatasi **daftar user** yang boleh login via SSH (lab di-set `dev`)?
    answers: ['AllowUsers']
    patterns: ['allowusers']
    tests:
      fail: ['AllowGroups', 'DenyUsers', 'PermitUsers', 'Match User']
    explanation: 'Di lab, `AllowUsers dev` → hanya user `dev` yang bisa SSH; `sshd -T` memastikannya. Ini whitelist (default-deny untuk login): akun sistem (www-data, postgres, dll) tidak bisa dipakai login interaktif meski punya shell. Pasangannya `AllowGroups`. Dengan ini, penyerang yang menebak nama user acak selalu gagal di tahap paling awal, bahkan sebelum autentikasi.'
    source: https://man.openbsd.org/sshd_config.5

  - id: b04
    tier: B
    type: text
    objective: firewall
    prompt: Dalam firewall stateful, aturan yang mengizinkan paket milik koneksi yang sudah terbentuk (agar balasan bisa masuk) memakai state apa?
    answers: ['ESTABLISHED,RELATED']
    patterns: ['establish', 'related', 'ctstate', 'conntrack']
    tests:
      fail: ['NEW', 'INVALID', 'LISTEN', 'SYN-SENT']
    explanation: 'Di lab: `iptables -A INPUT -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT`. Tanpa aturan ini, default-deny INPUT akan memblokir **balasan** dari koneksi yang server mulai (mis. update paket, query DNS), karena balasan itu masuk ke INPUT. ESTABLISHED = bagian dari koneksi yang sudah ada; RELATED = koneksi terkait (mis. data FTP). Ini pola wajib pertama di setiap ruleset default-deny (lihat board Jaringan modul 08).'
    source: https://www.netfilter.org/documentation/HOWTO/packet-filtering-HOWTO.html

  - id: b05
    tier: B
    type: text
    objective: brute-defense
    prompt: fail2ban di lab memakai action REJECT (balas `icmp-port-unreachable`). Dibanding DROP, kekurangan REJECT untuk mem-ban penyerang adalah apa?
    answers: ['penyerang langsung tahu diblokir']
    patterns: ['(langsung |segera )?(tahu|sadar|dapat (feedback|respons))', 'tidak memperlambat', 'balasan (cepat|langsung)', 'refused (cepat|langsung)']
    tests:
      fail: ['memakan lebih banyak CPU', 'membuka port lain', 'membocorkan password', 'tidak bisa di-log']
    explanation: 'Di lab, REJECT membuat attacker langsung dapat `Connection refused` — cepat, tapi memberi tahu penyerang bahwa mereka diblokir, sehingga mereka bisa pindah IP segera. DROP (diam, tanpa balasan) membuat koneksi penyerang **menggantung sampai timeout**, memperlambat mereka dan tidak mengonfirmasi apa pun. Untuk mem-ban penyerang, DROP umumnya lebih disukai; REJECT lebih ramah untuk memblokir trafik internal yang sah agar tidak menunggu.'
    source: https://www.netfilter.org/documentation/HOWTO/packet-filtering-HOWTO.html

  - id: b06
    tier: B
    type: text
    objective: brute-defense
    prompt: fail2ban butuh log ber-**timestamp** agar bisa mem-parse. Di lab, kenapa `sshd -e` (log ke stderr) tidak cukup, sehingga dipakai syslog?
    answers: ['stderr tidak memberi timestamp']
    patterns: ['(tidak|tanpa|ga|gak)[^\n]*(timestamp|waktu|tanggal)', 'stderr[^\n]*(tanpa|tidak)[^\n]*(waktu|timestamp)', 'tidak ada (cap )?waktu']
    tests:
      fail: ['stderr terlalu cepat', 'stderr terenkripsi', 'syslog lebih aman', 'stderr tidak ada isinya']
    explanation: 'Di lab, `sshd -e` mencetak `Failed password for dev from 172.18.0.3` **tanpa** tanggal/jam, sedangkan via busybox `syslogd` barisnya menjadi `Nov 25 04:12:52 host sshd[194]: Failed password ...`. fail2ban memakai timestamp untuk menghitung "N kegagalan dalam `findtime`". Tanpa timestamp, ia tidak bisa menentukan apakah kegagalan terjadi dalam jendela waktu. Pelajaran: deteksi bergantung pada kualitas & format log (modul 09).'
    source: https://github.com/fail2ban/fail2ban/wiki/Proper-fail2ban-configuration

  - id: b07
    tier: B
    type: text
    objective: brute-defense
    prompt: Dua parameter jail fail2ban yang menentukan "berapa kegagalan dalam jendela waktu berapa lama" sebelum mem-ban adalah `maxretry` dan apa?
    answers: ['findtime']
    patterns: ['findtime']
    tests:
      fail: ['bantime', 'retrytime', 'maxtime', 'window']
    explanation: 'Di lab: `maxretry 3`, `findtime 600`, `bantime 600`. Artinya: 3 kegagalan dalam 600 detik → ban selama 600 detik. `findtime` adalah jendela deteksi; `bantime` adalah lama hukuman. Setel terlalu ketat → admin sah bisa terblokir (false positive); terlalu longgar → penyerang lolos. Praktik: naikkan `bantime` secara progresif untuk pelanggar berulang (`bantime.increment = true`), dan masukkan IP admin ke `ignoreip`.'
    source: https://github.com/fail2ban/fail2ban/wiki/Proper-fail2ban-configuration

  - id: b08
    tier: B
    type: command
    objective: brute-defense
    prompt: Lepaskan (unban) IP `10.90.0.20` dari jail `sshd` di fail2ban (mis. karena admin salah terblokir).
    answers: ['fail2ban-client set sshd unbanip 10.90.0.20']
    patterns: ['^fail2ban-client set sshd unbanip 10\.90\.0\.20$']
    tests:
      pass: ['sudo fail2ban-client set sshd unbanip 10.90.0.20']
      fail: ['fail2ban-client unban 10.90.0.20', 'fail2ban-client set sshd banip 10.90.0.20', 'iptables -D INPUT -s 10.90.0.20 -j REJECT']
    explanation: 'Lawannya `banip`. Kamu akan butuh ini saat salah terblokir (mis. salah ketik password berkali-kali dari IP kantor). Karena itu masukkan IP/subnet admin tepercaya ke `ignoreip` di jail agar tidak pernah diban. Untuk melepas dari semua jail sekaligus: `fail2ban-client unban <IP>` (versi baru) atau `fail2ban-client unban --all`.'
    source: https://github.com/fail2ban/fail2ban/wiki/Proper-fail2ban-configuration

  - id: b09
    tier: B
    type: text
    objective: firewall
    prompt: Akar masalah kenapa service seperti Postgres/Redis bisa terekspos ke internet biasanya adalah ia listen di alamat apa (yang seharusnya `127.0.0.1`)?
    answers: ['0.0.0.0']
    patterns: ['0\.0\.0\.0', 'semua interface', 'all interfaces', 'any']
    tests:
      fail: ['127.0.0.1', '::1', '192.168.1.1', 'localhost']
    explanation: 'Service yang `bind 0.0.0.0` listen di **semua** interface, termasuk yang menghadap publik. Banyak breach Redis/Postgres/Elasticsearch berawal dari sini: database tanpa auth yang tak sengaja menghadap internet. Dua lapis pertahanan: (1) `bind 127.0.0.1` atau interface internal saja di config service; (2) firewall default-deny sebagai jaring pengaman. Di Docker, jangan `-p 5432:5432` (publish ke host) untuk DB — biarkan hanya di network internal.'
    source: https://www.netfilter.org/documentation/HOWTO/packet-filtering-HOWTO.html

  - id: b10
    tier: B
    type: command
    objective: firewall
    prompt: Izinkan koneksi masuk milik **koneksi yang sudah terbentuk** (ESTABLISHED,RELATED) di chain INPUT dengan iptables + conntrack.
    answers: ['iptables -A INPUT -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT']
    patterns: ['^iptables -A INPUT -m (conntrack --ctstate|state --state) (ESTABLISHED,RELATED|RELATED,ESTABLISHED) -j ACCEPT$']
    tests:
      pass: ['iptables -A INPUT -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT', 'iptables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT']
      fail: ['iptables -A INPUT -m conntrack --ctstate NEW -j ACCEPT', 'iptables -A INPUT -j ACCEPT', 'iptables -A OUTPUT -m conntrack --ctstate ESTABLISHED -j ACCEPT']
    explanation: 'Ini baris wajib pertama dalam ruleset default-deny (lihat b04). Modul `conntrack` (atau `state` yang lebih lama) melacak status koneksi. Tanpa baris ini, balasan dari koneksi yang server inisiasi (update, DNS, API call keluar) akan di-DROP oleh policy INPUT DROP. Urutan penting: letakkan aturan ESTABLISHED,RELATED di atas agar paket balasan cepat diterima tanpa dievaluasi aturan lain.'
    source: https://www.netfilter.org/documentation/HOWTO/packet-filtering-HOWTO.html

  - id: b11
    tier: B
    type: text
    objective: least-priv
    prompt: Akun sistem untuk service (www-data, postgres, nginx) sebaiknya dibuat **tanpa** kemampuan apa, agar tidak bisa dipakai login interaktif?
    answers: ['shell login']
    patterns: ['(tanpa )?(shell|login shell)', 'nologin', '/usr/sbin/nologin', '/bin/false', 'shell login']
    tests:
      fail: ['password', 'home directory', 'UID', 'group']
    explanation: 'Service account diberi shell `/usr/sbin/nologin` atau `/bin/false` sehingga walau kredensialnya bocor, penyerang tidak mendapat shell interaktif. Gabungkan dengan `AllowUsers` (b03) yang tidak memasukkannya. Prinsip least privilege: service hanya butuh menjalankan prosesnya, bukan login. Ini juga membatasi gerak penyerang (Lateral Movement) bila satu service dikuasai.'
    source: https://www.cisecurity.org/cis-benchmarks

  - id: b12
    tier: B
    type: text
    objective: ssh-harden
    prompt: Selain SSH biasa, pendekatan yang **tidak mengekspos port 22 ke internet sama sekali** (akses lewat perantara terpusat) memakai apa?
    answers: ['bastion host']
    patterns: ['bastion', 'jump (host|server)', 'vpn', 'tunnel']
    tests:
      fail: ['port knocking', 'honeypot', 'reverse proxy', 'load balancer']
    explanation: 'Bastion/jump host (board Jaringan modul 09) atau VPN membuat server backend tidak punya port 22 yang menghadap internet — hanya bastion/VPN yang terekspos, dan itu di-hardening ketat + dipantau. Penyerang harus menembus perantara dulu. Ini menerapkan "assume breach" + least exposure: kurangi jumlah pintu publik menjadi satu yang terkontrol, bukan satu pintu per server.'
    source: https://man.openbsd.org/sshd_config.5

  - id: b13
    tier: B
    type: text
    objective: brute-defense
    prompt: 'Benar/salah: setelah `PasswordAuthentication no`, fail2ban jadi tidak berguna sama sekali untuk SSH.'
    answers: ['salah']
    patterns: ['^salah$', '^false$', '^tidak( benar)?$']
    tests:
      fail: ['benar', 'true', 'ya']
    explanation: 'Salah. Meski password dimatikan (brute-force password mati), fail2ban tetap berguna: mem-ban IP yang terus mencoba (failed pubkey, invalid user, scan) mengurangi kebisingan log, beban CPU sshd, dan mempersempit jendela serangan lain. Pertahanan berlapis: key-only **dan** fail2ban **dan** firewall. Masing-masing menutup hal berbeda; tidak ada yang menggantikan yang lain.'
    source: https://github.com/fail2ban/fail2ban/wiki/Proper-fail2ban-configuration

  - id: b14
    tier: B
    type: text
    objective: ssh-harden
    prompt: Risiko keamanan dari `ssh -A` (agent forwarding) ke server yang tidak sepenuhnya kamu percaya adalah apa?
    answers: ['root server bisa memakai kunci kita']
    patterns: ['(root|admin|penyerang)[^\n]*(server|remote)[^\n]*(pakai|memakai|curi|gunakan)[^\n]*(kunci|agent|key)', 'kunci (kita )?(bisa )?(dipakai|disalahgunakan|dicuri)', 'menyalahgunakan agent']
    tests:
      fail: ['koneksi jadi lambat', 'password bocor ke log', 'kunci terhapus', 'tidak ada risiko']
    explanation: 'Dengan agent forwarding, root di server tujuan bisa memakai socket agent-mu untuk mengautentikasi ke sistem lain **atas namamu** selama sesi aktif. Bila server itu disusupi, kunci-mu efektif disalahgunakan tanpa pernah meninggalkan laptop. Lebih aman: `ProxyJump` (`-J`, board Jaringan modul 09) yang membuat koneksi end-to-end tanpa menaruh agent di perantara. Nyalakan `-A` hanya ke host yang benar-benar tepercaya.'
    source: https://man.openbsd.org/sshd_config.5

  - id: b15
    tier: B
    type: text
    objective: firewall
    prompt: Teknik yang menyembunyikan port (mis. SSH) hingga klien mengirim urutan "ketukan" ke beberapa port tertutup dulu, baru port dibuka, disebut apa?
    answers: ['port knocking']
    patterns: ['port[- ]?knocking', 'ketukan port']
    tests:
      fail: ['port forwarding', 'port scanning', 'nat', 'port mirroring']
    explanation: 'Port knocking (atau Single Packet Authorization) menyembunyikan service sampai urutan ketukan yang benar diterima. Ini lapisan **obscurity** yang menambah friksi, bukan pengganti auth kuat — bila urutannya bocor atau trafik disniff, ia jebol. Berguna untuk mengurangi kebisingan scan/brute di internet, tetapi SSH tetap harus key-only + firewall. Jangan jadikan satu-satunya pertahanan.'
    source: https://www.netfilter.org/documentation/HOWTO/packet-filtering-HOWTO.html

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: ssh-harden
    prompt: |
      Server baru (`ssh root@IP` dengan password aktif) terlihat di log sudah kena ribuan percobaan brute-force dari
      banyak IP. Rancang hardening SSH-nya langkah demi langkah dengan aman (tanpa mengunci diri), beserta
      verifikasinya (satu poin per baris).
    checklist:
      - id: keys-first
        label: Siapkan & uji login kunci (ed25519) LEBIH DULU
        required: true
        patterns: ['(kunci|key|ed25519)[^\n]*(dulu|pertama|lebih dulu|setup|siapkan|uji)', '(uji|tes|pastikan)[^\n]*(kunci|key)[^\n]*(jalan|bisa|dulu|sebelum)', 'ssh-keygen']
      - id: disable-pw
        label: PasswordAuthentication no (matikan password)
        required: true
        patterns: ['passwordauthentication no', '(matikan|nonaktif|disable)[^\n]*(password|kata sandi)']
      - id: no-root
        label: PermitRootLogin no
        required: true
        patterns: ['permitrootlogin no', '(larang|matikan|disable)[^\n]*(login )?root']
      - id: limit-user
        label: AllowUsers / batasi user + MaxAuthTries kecil
        patterns: ['allowusers', 'maxauthtries', '(batasi|whitelist)[^\n]*user']
      - id: test-syntax
        label: sshd -t (cek sintaks) sebelum reload + buka sesi baru untuk tes
        required: true
        patterns: ['sshd -t', '(sesi|session) (baru|kedua|cadangan)', '(jangan tutup|tetap buka)[^\n]*sesi', 'sshd -T[^\n]*verifikasi']
      - id: f2b-fw
        label: Pasang fail2ban + firewall default-deny (batasi sumber SSH)
        patterns: ['fail2ban', 'default[- ]?deny', 'firewall[^\n]*(22|ssh)', '-s [^\n]*(22|ssh|admin)']
      - id: no-lockout
        label: Tidak men-DROP default / matikan password sebelum kunci teruji
        forbid: true
        patterns: ['^(?![^\n]*(setelah|jangan|bukan|sesudah|dulu kunci|sudah teruji))[^\n]*(passwordauthentication no|iptables -p input drop|permitrootlogin no)[^\n]*(dulu|pertama|langsung|sebelum)']
    pass_score: 0.5
    solution: |
      ```text
      1. ssh-keygen -t ed25519; pasang public key ke ~/.ssh/authorized_keys; UJI login kunci berhasil — DULU.
         (jangan sentuh password sebelum kunci terbukti jalan, agar tidak terkunci)
      2. Edit sshd_config:
           PermitRootLogin no
           PasswordAuthentication no
           PubkeyAuthentication yes
           MaxAuthTries 3
           AllowUsers dev
      3. sshd -t   (cek sintaks) → reload. BUKA SESI BARU untuk tes, biarkan sesi lama tetap terbuka.
         sshd -T | grep -E 'permitroot|passwordauth|allowusers'   → verifikasi nilai efektif.
      4. Firewall default-deny: allow lo + ESTABLISHED,RELATED + tcp/22 (idealnya -s IP admin), sisanya DROP.
      5. fail2ban jail sshd (maxretry 3, ignoreip = IP admin) untuk mem-ban sisa brute-force.
      ```
      Urutan ini mencegah lockout: login kunci diuji **sebelum** password dimatikan, `sshd -t` menangkap salah ketik,
      dan sesi lama dibiarkan terbuka sebagai jaring pengaman. Hasil lab: setelah `PasswordAuthentication no`,
      brute-force → `Permission denied (publickey)`, dan root → `Permission denied`. fail2ban + firewall menambal
      sisanya. Mematikan password atau DROP default sebelum kunci teruji adalah cara klasik mengunci diri sendiri.
    tests:
      pass:
        - |
          pertama ssh-keygen -t ed25519 dan pasang public key lalu uji login kunci berhasil lebih dulu
          baru set PasswordAuthentication no untuk matikan password
          PermitRootLogin no supaya root tidak bisa login
          AllowUsers dev dan MaxAuthTries 3 untuk batasi user dan percobaan
          sshd -t cek sintaks lalu reload sambil buka sesi baru untuk tes, sesi lama tetap terbuka
          pasang fail2ban dan firewall default-deny yang hanya buka 22 dari IP admin
        - |
          setup kunci ed25519 dan tes dulu sampai bisa login tanpa password
          matikan password authentication dan larang root login
          verifikasi dengan sshd -T, jangan tutup sesi lama
          tambah fail2ban dan default-deny firewall
      fail:
        - |
          langsung set PasswordAuthentication no dulu
          baru nanti bikin kunci kalau sempat
        - |
          iptables -P INPUT DROP dulu biar aman
          urus ssh dan kunci belakangan
        - |
          PermitRootLogin no
          selesai, password biarkan aktif
    source: https://man.openbsd.org/sshd_config.5

  - id: c02
    tier: C
    type: scenario
    objective: brute-defense
    prompt: |
      Walau SSH sudah key-only, log masih penuh percobaan dari banyak IP, dan panel admin web (`/admin`) mulai kena
      brute-force login juga. Rancang pertahanan brute-force berlapis untuk SSH dan aplikasi web (satu poin per baris).
    checklist:
      - id: f2b-ssh
        label: fail2ban jail sshd (ban IP setelah N gagal via firewall)
        required: true
        patterns: ['fail2ban[^\n]*sshd', 'fail2ban[^\n]*(ssh|ban)', 'jail sshd']
      - id: f2b-web
        label: fail2ban/rate-limit untuk login web (jail dari log nginx/app)
        required: true
        patterns: ['(fail2ban|rate[- ]?limit)[^\n]*(web|nginx|login|app|admin|http)', 'jail[^\n]*(nginx|web|http)', 'limit_req']
      - id: mfa
        label: MFA pada login web/admin (lawan credential stuffing)
        patterns: ['mfa', '2fa', 'multi[- ]?factor', 'two[- ]?factor', 'otp']
      - id: lockout
        label: Account lockout / backoff + CAPTCHA pada login app
        patterns: ['(lockout|kunci akun|backoff|delay)', 'captcha', '(perlambat|rate)[^\n]*login']
      - id: ignoreip
        label: ignoreip untuk IP admin tepercaya (hindari self-lockout)
        patterns: ['ignoreip', '(allowlist|whitelist)[^\n]*(admin|ip)', '(hindari|cegah)[^\n]*(self-?lock|terblokir sendiri)']
      - id: monitor
        label: Alert saat lonjakan kegagalan (deteksi serangan berjalan)
        patterns: ['(alert|monitor|notifikasi|pantau)[^\n]*(gagal|login|brute|lonjakan)', '(deteksi|siem)[^\n]*(brute|login)']
      - id: not-only-block
        label: Tidak mengandalkan hanya mem-ban IP (penyerang ganti IP / botnet)
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak cukup|selain|lebih dari))[^\n]*(cukup|hanya)[^\n]*(ban|blokir)[^\n]*ip']
    pass_score: 0.5
    solution: |
      ```text
      SSH:
        - fail2ban jail sshd: maxretry 3, ban IP via iptables/nftables; ignoreip = IP/subnet admin.
        - key-only sudah (brute password mati); firewall batasi sumber 22 ke allowlist/VPN bila bisa.
      Web/admin:
        - fail2ban jail dari log nginx/app (pola gagal login) ATAU rate limit di nginx (limit_req, modul 08)
          pada endpoint /login.
        - MFA pada login admin → mematikan credential stuffing (kredensial bocor saja tidak cukup).
        - Account lockout/backoff progresif + CAPTCHA setelah beberapa gagal; jangan bedakan pesan
          "user salah" vs "password salah" (user enumeration).
      Lintas:
        - Alert saat lonjakan kegagalan (deteksi serangan berjalan, modul 09).
        - ignoreip/allowlist untuk admin agar tidak terkunci sendiri.
      ```
      Kuncinya berlapis: mem-ban IP (fail2ban) memperlambat, tetapi penyerang bisa ganti IP atau pakai botnet, jadi
      itu **tidak cukup sendirian**. MFA + account lockout bekerja di level identitas, bukan IP, sehingga tahan
      terhadap perpindahan IP. Rate limit melindungi dari lonjakan. Dan `ignoreip` mencegah pertahanan itu mengunci
      admin sendiri.
    tests:
      pass:
        - |
          fail2ban jail sshd mem-ban IP setelah 3 gagal lewat iptables
          fail2ban jail dari log nginx untuk login web atau rate limit limit_req di endpoint login
          pasang MFA pada login admin untuk melawan credential stuffing
          account lockout dan backoff plus captcha setelah beberapa kali gagal
          ignoreip untuk IP admin supaya tidak terkunci sendiri
          alert saat lonjakan kegagalan untuk deteksi serangan berjalan
        - |
          jail sshd fail2ban dan jail web dari log nginx
          mfa di login admin dan lockout akun progresif
          rate limit login, ignoreip admin, dan monitoring lonjakan gagal
      fail:
        - |
          cukup ban semua ip yang gagal login
          selesai
        - |
          pasang fail2ban untuk ssh saja
          web tidak perlu karena beda sistem
        - |
          blokir ip penyerang satu per satu manual
    source: https://github.com/fail2ban/fail2ban/wiki/Proper-fail2ban-configuration

  - id: c03
    tier: C
    type: scenario
    objective: firewall
    prompt: |
      Server production Linux di cloud hanya perlu melayani web (80/443) dan diadministrasi olehmu. Saat ini SSH (22),
      Postgres (5432), dan Redis (6379) semua listen di `0.0.0.0` dan firewall policy `ACCEPT`. Rancang firewall
      default-deny + bind yang benar, aman dari lockout (satu poin per baris).
    checklist:
      - id: bind-local
        label: Bind Postgres/Redis ke 127.0.0.1/internal, bukan 0.0.0.0
        required: true
        patterns: ['(bind|listen)[^\n]*(127\.0\.0\.1|localhost|internal)', '(postgres|redis|5432|6379)[^\n]*(127\.0\.0\.1|localhost|internal|jangan 0\.0\.0\.0)']
      - id: allow-web
        label: Allow 80/443 dari mana saja (layanan publik)
        required: true
        patterns: ['(80|443)[^\n]*(accept|allow|izin|buka)', '(accept|allow|buka)[^\n]*(80|443)']
      - id: allow-ssh-restricted
        label: Allow 22 hanya dari IP admin/VPN (bukan dunia)
        patterns: ['(22|ssh)[^\n]*(-s |dari )[^\n]*(admin|vpn|ip)', '-s [^\n]*(22|ssh)', '(allowlist|batasi)[^\n]*(22|ssh)']
      - id: established
        label: Allow lo + ESTABLISHED,RELATED sebelum default DROP
        required: true
        patterns: ['(establish|conntrack|ctstate)', '(lo|loopback)', 'related']
      - id: default-drop
        label: Policy INPUT DROP (default-deny)
        required: true
        patterns: ['(-p input drop|policy[^\n]*drop|input drop)', 'default[- ]?deny']
      - id: no-lockout
        label: Terapkan allow SSH SEBELUM DROP / pakai iptables-apply agar tak terkunci
        patterns: ['(allow|izin)[^\n]*(22|ssh)[^\n]*(sebelum|dulu)', 'iptables-apply', '(sesi|session)[^\n]*(baru|cadangan)', 'tidak terkunci|hindari lockout']
      - id: verify
        label: Verifikasi dari luar dengan nmap — hanya 80/443/22 terlihat, sisanya filtered
        patterns: ['(nmap|scan|verifikasi|cek)[^\n]*(luar|80|443|filtered)', 'filtered']
      - id: no-allow-all
        label: Tidak membiarkan policy ACCEPT / membuka DB ke publik
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak boleh|hindari))[^\n]*(-p input accept|policy accept|(5432|6379|postgres|redis)[^\n]*(0\.0\.0\.0|publik|allow (any|all)))']
    pass_score: 0.5
    solution: |
      ```text
      1. Bind service data ke internal: Postgres listen_addresses='localhost', Redis bind 127.0.0.1
         (akar masalah: 0.0.0.0 = semua interface). Akses DB dari luar hanya via SSH tunnel/VPN.
      2. Firewall default-deny (urutan penting agar tidak lockout):
           iptables -A INPUT -i lo -j ACCEPT
           iptables -A INPUT -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
           iptables -A INPUT -p tcp --dport 22 -s <IP_admin/VPN> -j ACCEPT      # SSH terbatas
           iptables -A INPUT -p tcp --dport 80  -j ACCEPT
           iptables -A INPUT -p tcp --dport 443 -j ACCEPT
           iptables -P INPUT DROP                                               # default-deny TERAKHIR
         (pakai iptables-apply / sesi cadangan agar auto-rollback bila salah).
      3. Verifikasi dari luar: nmap -Pn -p- → hanya 22 (dari IP admin), 80, 443 terlihat; 5432/6379 filtered.
      ```
      Prinsip: default-deny + least exposure. Postgres/Redis tidak punya alasan menghadap publik — bind ke localhost
      adalah pertahanan pertama, firewall jaring kedua. Urutan aturan krusial: izinkan SSH (dan lo + ESTABLISHED)
      **sebelum** mengubah policy ke DROP, atau pakai `iptables-apply` yang otomatis rollback kalau koneksi putus —
      inilah cara menghindari mengunci diri di server cloud yang hanya bisa diakses lewat SSH.
    tests:
      pass:
        - |
          bind Postgres dan Redis ke 127.0.0.1 atau jaringan internal bukan 0.0.0.0, akses via tunnel
          allow 80 dan 443 dari mana saja untuk layanan web publik
          allow 22 hanya dari IP admin atau VPN bukan dari dunia
          allow lo dan ESTABLISHED,RELATED sebelum policy diubah
          policy INPUT DROP default-deny diterapkan terakhir
          terapkan allow ssh sebelum drop dan pakai iptables-apply agar tidak terkunci
          verifikasi dari luar dengan nmap, 5432 dan 6379 jadi filtered
        - |
          Postgres dan Redis bind localhost saja, akses lewat vpn
          buka 80 443 untuk publik, 22 dibatasi -s ip admin
          allow loopback dan conntrack established dulu baru iptables -P INPUT DROP
          verifikasi nmap dari luar hanya 80 443 22 yang terlihat sisanya filtered
      fail:
        - |
          iptables -P INPUT ACCEPT biar semua jalan
          yang penting ada password di database
        - |
          buka 80 443 22 5432 6379 semua dari 0.0.0.0
          supaya mudah diakses dari mana saja
        - |
          iptables -P INPUT DROP dulu
          baru pikirkan aturan ssh kalau masih bisa masuk
    source: https://www.netfilter.org/documentation/HOWTO/packet-filtering-HOWTO.html
---

# Hardening Server & Akses

Jalan masuk paling tua dan paling sering dicoba ke sebuah server adalah **login**-nya. Bot menyapu internet
men-brute-force SSH 24 jam sehari. Modul ini menutup pintu itu dari sisi blue: autentikasi kunci, larang root,
batasi percobaan, firewall default-deny, dan fail2ban — semua dibuktikan melawan brute-force sungguhan di lab.

> [!NOTE]
> Lab: target `alpine` (OpenSSH + PAM, fail2ban, iptables, busybox syslogd, cap `NET_ADMIN`) dengan user `dev`.
> Attacker `alpine` dengan `sshpass` melakukan brute-force password. Semua di network lab lokal, hanya milik sendiri.

## 1. Brute-force dan log-nya

```text
# attacker: sshpass -p <tebakan> ssh dev@server   (berulang)
# server:   Failed password for dev from 172.18.0.3 port 44356 ssh2   (×7)
```

Brute-force (ATT&CK T1110) dan variannya: dictionary attack, **credential stuffing** (kredensial bocor dari situs
lain), password spraying. Pertahanan terkuat: hilangkan password sebagai faktor.

## 2. Hardening sshd (hasil lab)

```text
# /etc/ssh/sshd_config
PermitRootLogin no
PasswordAuthentication no      # brute-force password → Permission denied (publickey)
PubkeyAuthentication yes
MaxAuthTries 3
AllowUsers dev
```

| Verifikasi | Hasil |
|---|---|
| `sshd -t` | cek sintaks sebelum reload (hindari lockout) |
| `sshd -T \| grep ...` | `permitrootlogin no`, `passwordauthentication no`, `allowusers dev` |
| login kunci | ✅ `dev` |
| brute-force password | ❌ `Permission denied (publickey,keyboard-interactive)` |
| `ssh root@server` | ❌ `Permission denied` |

> [!WARNING]
> Uji login kunci **sebelum** mematikan password, jalankan `sshd -t` sebelum reload, dan biarkan sesi lama terbuka
> saat menguji sesi baru. Ini mencegah mengunci diri sendiri di server remote.

## 3. fail2ban: ban otomatis (hasil lab)

```text
# /etc/fail2ban/jail.local
[sshd]
enabled = true
logpath = /var/log/messages      # butuh log ber-TIMESTAMP (syslog), bukan stderr sshd -e
maxretry = 3
findtime = 600
bantime  = 600
```

Setelah brute-force:

```text
fail2ban-client status sshd  →  Total failed: 5, Currently banned: 1, Banned IP list: 172.18.0.3
iptables -S                  →  -A f2b-sshd -s 172.18.0.3/32 -j REJECT --reject-with icmp-port-unreachable
attacker (kunci valid pun)   →  ssh: connect ... port 22: Connection refused
```

fail2ban tidak memblokir sendiri — ia **memerintah firewall** (iptables). Berguna bahkan setelah password dimatikan,
karena mengurangi kebisingan scan. Catatan: `REJECT` memberi tahu penyerang (mereka pindah IP cepat); `DROP` membuat
mereka menunggu timeout — umumnya `DROP` lebih baik untuk mem-ban penyerang.

## 4. Firewall default-deny (hasil lab)

```bash
iptables -A INPUT -i lo -j ACCEPT
iptables -A INPUT -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT   # wajib: balasan koneksi keluar
iptables -A INPUT -p tcp --dport 22 -j ACCEPT                            # idealnya -s <IP admin>
iptables -P INPUT DROP                                                   # default-deny (terakhir!)
```

| | Sebelum | Sesudah |
|---|---|---|
| port 22 | open | open |
| port 8080 | open | **filtered** (nmap) — bukan `closed` |

`filtered` berarti firewall mem-DROP tanpa balasan, membuat recon penyerang buram.

## 5. Least privilege di server

- Login sebagai user biasa, naikkan hak dengan **`sudo`** (tercatat di log), jangan login root.
- Service account (www-data, postgres) diberi shell `/usr/sbin/nologin` — tidak bisa login interaktif meski
  kredensialnya bocor.
- Kurangi pintu publik: SSH dibatasi ke IP admin/VPN, atau lewat **bastion** (board Jaringan modul 09), bukan 22
  terbuka ke seluruh internet.

## 6. Ringkasan: pertahanan berlapis login

| Lapis | Menutup | Catatan |
|---|---|---|
| Key-only (`PasswordAuthentication no`) | Brute-force password | Pertahanan terkuat |
| `PermitRootLogin no` + `AllowUsers` | Target root & user acak | Whitelist login |
| MFA | Credential stuffing & phishing | Level identitas, bukan IP |
| fail2ban | Percobaan berulang (noise) | Mem-ban via firewall |
| Firewall default-deny | Port tak perlu, sumber tak dikenal | Jaring pengaman |
| Bastion/VPN | Eksposur port 22 ke publik | Kurangi pintu |

> [!TIP]
> Tidak ada satu lapis yang cukup: penyerang bisa ganti IP (melewati fail2ban), jadi key-only + MFA + firewall +
> deteksi bekerja bersama. Lanjut ke modul 04 (Secrets & kredensial) — karena kredensial yang bocor melewati
> semua lapis ini sekaligus.
