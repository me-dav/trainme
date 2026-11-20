---
format: 1
id: linux-ssh-hardening
course:
  slug: linux
  title: Linux
  description: Fondasi Linux untuk DevOps/DevSecOps — dari filesystem sampai hardening.
title: SSH & Hardening
summary: Key SSH, ~/.ssh/config, bastion dengan ProxyJump, tunnel, transfer file, hardening sshd yang benar-benar berlaku, pembatasan per key, fail2ban, dan respons insiden login.
order: 10
version: 1
status: verified
updated: '2026-11-20'
tested_on:
  - Ubuntu 24.04.5 LTS (container Docker) — OpenSSH 9.6p1, rsync, fail2ban 1.0.2, rsyslog
  - Dua sshd (port 22 sebagai bastion, 2222 sebagai target) untuk ProxyJump; dua container terpisah untuk uji fail2ban
  - systemd 255 (image dojo-systemd) untuk perilaku ssh.socket
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: keys
    text: Membuat dan memasang key SSH dengan aman
  - id: client-config
    text: Memakai ~/.ssh/config, ProxyJump, dan agent tanpa membocorkan akses
  - id: host-keys
    text: Memverifikasi host key dan menangani peringatan host key berubah
  - id: tunnels
    text: Membuat local/remote port forwarding
  - id: transfer
    text: Mentransfer file dengan scp dan rsync
  - id: sshd-hardening
    text: Mengeraskan sshd dan memastikan konfigurasi efektifnya
  - id: key-restrictions
    text: Membatasi key per kegunaan (restrict, command, from)
  - id: bruteforce
    text: Menghadapi brute force dengan fail2ban dan analisis log
  - id: incident
    text: Merespons dugaan akses tidak sah lewat SSH
sources:
  - title: ssh(1)
    url: https://man.openbsd.org/ssh
  - title: ssh_config(5)
    url: https://man.openbsd.org/ssh_config
  - title: sshd_config(5)
    url: https://man.openbsd.org/sshd_config
  - title: sshd(8) — AUTHORIZED_KEYS FILE FORMAT (restrict, command, from)
    url: https://man.openbsd.org/sshd#AUTHORIZED_KEYS_FILE_FORMAT
  - title: ssh-keygen(1)
    url: https://man.openbsd.org/ssh-keygen
  - title: rsync(1)
    url: https://download.samba.org/pub/rsync/rsync.1
  - title: Ubuntu Server — OpenSSH server
    url: https://documentation.ubuntu.com/server/how-to/security/openssh-server/
  - title: Ubuntu Discourse — sshd now uses socket-based activation (22.10+)
    url: https://discourse.ubuntu.com/t/sshd-now-uses-socket-based-activation-ubuntu-22-10-and-later/30189
  - title: fail2ban — fail2ban-client & jail.conf
    url: https://github.com/fail2ban/fail2ban/blob/master/config/jail.conf
  - title: Mozilla — OpenSSH security guidelines
    url: https://infosec.mozilla.org/guidelines/openssh
  - title: CIS Ubuntu Linux 24.04 LTS Benchmark (bagian SSH Server)
    url: https://www.cisecurity.org/benchmark/ubuntu_linux
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: command
    objective: keys
    prompt: Buat pasangan key SSH bertipe **Ed25519** dengan komentar `dev@laptop`.
    answers: ['ssh-keygen -t ed25519 -C "dev@laptop"']
    patterns: ['^ssh-keygen (?=.*-t ed25519( |$))(?=.*-C dev@laptop( |$))((-t ed25519|-C dev@laptop|-f \S+|-a \d+) ?)+$']
    tests:
      pass: ['ssh-keygen -C dev@laptop -t ed25519', 'ssh-keygen -t ed25519 -C dev@laptop -f ~/.ssh/id_ed25519']
      fail: ['ssh-keygen -t rsa -b 1024 -C dev@laptop', 'ssh-keygen', 'ssh-keygen -t dsa -C dev@laptop']
    explanation: 'Ed25519: key pendek, cepat, dan aman. Di lab, private key otomatis ber-mode `-rw-------` dan `.pub` `-rw-r--r--`. Komentar membantu mengenali key di `authorized_keys`. Beri **passphrase** pada key laptop, lalu pakai ssh-agent supaya tidak mengetiknya berulang kali.'
    source: https://man.openbsd.org/ssh-keygen

  - id: a02
    tier: A
    type: command
    objective: keys
    prompt: Pasang public key-mu ke `authorized_keys` user `ops` di `10.0.0.5` (selagi login password masih aktif).
    answers: ['ssh-copy-id ops@10.0.0.5']
    patterns: ['^ssh-copy-id (-i \S+\.pub )?ops@10\.0\.0\.5$']
    tests:
      pass: ['ssh-copy-id -i ~/.ssh/id_ed25519.pub ops@10.0.0.5']
      fail: ['scp ~/.ssh/id_ed25519 ops@10.0.0.5:', 'ssh ops@10.0.0.5', 'ssh-add ops@10.0.0.5']
    explanation: '`ssh-copy-id` menambahkan key ke `~/.ssh/authorized_keys` dengan permission yang benar (lab: `-rw------- ops ops`). Jangan pernah menyalin **private key** (`id_ed25519` tanpa `.pub`) ke server. Satu key per orang/per perangkat memudahkan pencabutan.'
    source: https://man.openbsd.org/ssh-copy-id

  - id: a03
    tier: A
    type: text
    objective: keys
    prompt: Permission (oktal) yang benar untuk private key `~/.ssh/id_ed25519`?
    answers: ['600', '0600', '400', '0400']
    tests:
      fail: ['644', '664', '777', '755']
    explanation: 'Klien ssh menolak private key yang bisa dibaca orang lain (`UNPROTECTED PRIVATE KEY FILE!`). `~/.ssh` 700, `authorized_keys` 600 — dibahas detail di modul Permission & Ownership.'
    source: https://man.openbsd.org/ssh#FILES

  - id: a04
    tier: A
    type: command
    objective: client-config
    prompt: Login sebagai `deploy` ke `203.0.113.10` di **port 2222** memakai key `~/.ssh/deploy_key`.
    answers: ['ssh -i ~/.ssh/deploy_key -p 2222 deploy@203.0.113.10']
    patterns: ['^ssh (?=.*-i ~/\.ssh/deploy_key( |$))(?=.*-p ?2222( |$))((-i ~/\.ssh/deploy_key|-p ?2222) )+deploy@203\.0\.113\.10$']
    tests:
      pass: ['ssh -p 2222 -i ~/.ssh/deploy_key deploy@203.0.113.10']
      fail: ['ssh deploy@203.0.113.10:2222', 'ssh -P 2222 -i ~/.ssh/deploy_key deploy@203.0.113.10', 'ssh -i ~/.ssh/deploy_key deploy@203.0.113.10']
    explanation: 'ssh memakai `-p` kecil untuk port, sedangkan **scp** memakai `-P` besar. Syntax `host:port` tidak berlaku untuk ssh. Daripada mengetik ini setiap kali, simpan di `~/.ssh/config` (soal berikutnya).'
    source: https://man.openbsd.org/ssh

  - id: a05
    tier: A
    type: text
    objective: client-config
    prompt: File konfigurasi **klien** SSH per user (alias host, user, port, key, ProxyJump)?
    answers: ['~/.ssh/config', '$HOME/.ssh/config']
    tests:
      fail: ['/etc/ssh/sshd_config', '~/.ssh/known_hosts', '~/.ssh/authorized_keys', '/etc/ssh/ssh_config']
    explanation: 'Di lab, blok `Host app01` dengan `HostName`, `Port 2222`, `User ops`, `ProxyJump bastion` membuat `ssh app01` langsung tembus lewat bastion. `/etc/ssh/ssh_config` berlaku untuk semua user di mesin itu. `sshd_config` adalah konfigurasi **server**.'
    source: https://man.openbsd.org/ssh_config

  - id: a06
    tier: A
    type: text
    objective: keys
    prompt: File di **server** (di home user) yang berisi public key yang diizinkan login?
    answers: ['~/.ssh/authorized_keys', 'authorized_keys', '.ssh/authorized_keys']
    tests:
      fail: ['known_hosts', 'id_ed25519.pub', '~/.ssh/config', '/etc/ssh/sshd_config']
    explanation: 'Satu baris per key; opsi pembatasan (`restrict`, `from=`, `command=`) ditulis di depan key (tier B). `known_hosts` ada di sisi **klien** dan menyimpan host key server yang dipercaya.'
    source: https://man.openbsd.org/sshd#AUTHORIZED_KEYS_FILE_FORMAT

  - id: a07
    tier: A
    type: command
    objective: host-keys
    prompt: Hapus entri host key lama untuk `203.0.113.10` dari `known_hosts` (setelah kamu **memverifikasi** server memang di-reinstall).
    answers: ['ssh-keygen -R 203.0.113.10']
    patterns: ['^ssh-keygen -R 203\.0\.113\.10( -f \S+)?$']
    tests:
      fail: ['rm ~/.ssh/known_hosts', 'ssh -o StrictHostKeyChecking=no 203.0.113.10', 'ssh-keygen -F 203.0.113.10']
    explanation: '`known_hosts` Ubuntu di-hash (lab: `HashKnownHosts yes`, baris diawali `|1|...`), sehingga mengedit manual sulit. `-R` menghapus entri yang tepat. Menghapus seluruh file berarti kehilangan verifikasi untuk semua server lain.'
    source: https://man.openbsd.org/ssh-keygen

  - id: a08
    tier: A
    type: command
    objective: transfer
    prompt: Salin `backup.sql.gz` dari laptop ke direktori `/tmp/` di `ops@10.0.0.5` dengan scp.
    answers: ['scp backup.sql.gz ops@10.0.0.5:/tmp/']
    patterns: ['^scp (-\S+ )*backup\.sql\.gz ops@10\.0\.0\.5:/tmp/?$']
    tests:
      pass: ['scp -C backup.sql.gz ops@10.0.0.5:/tmp']
      fail: ['scp ops@10.0.0.5:/tmp/ backup.sql.gz', 'cp backup.sql.gz ops@10.0.0.5:/tmp/', 'scp backup.sql.gz ops@10.0.0.5/tmp/']
    explanation: 'Format `scp SUMBER TUJUAN`, dan sisi remote ditulis `user@host:path`. Tanpa `:`, scp menganggapnya nama file lokal. Untuk direktori atau transfer berulang, rsync lebih efisien.'
    source: https://man.openbsd.org/scp

  - id: a09
    tier: A
    type: command
    objective: transfer
    prompt: Sinkronkan **isi** `./site/` ke `/var/www/site/` di `deploy@10.0.0.5` dengan rsync (mode arsip, verbose, terkompresi).
    answers: ['rsync -avz ./site/ deploy@10.0.0.5:/var/www/site/']
    patterns: ['^rsync -(?=[a-zA-Z]*a)[avzhP]+( -e ssh)? (\./)?site/ deploy@10\.0\.0\.5:/var/www/site/?$']
    tests:
      pass: ['rsync -az site/ deploy@10.0.0.5:/var/www/site/', 'rsync -avzP -e ssh ./site/ deploy@10.0.0.5:/var/www/site']
      fail: ['scp -r site deploy@10.0.0.5:/var/www/site', 'rsync ./site/ deploy@10.0.0.5:/var/www/site/', 'rsync -avz ./site deploy@10.0.0.5:/var/www/site/']
    explanation: '**Garis miring di akhir sumber penting.** `site/` = isi direktori, `site` = direktori itu sendiri. Di lab, `rsync ~/site bastion:/tmp/nosl/` menghasilkan `/tmp/nosl/site/index.html`. rsync hanya mengirim yang berubah, dan `-a` mempertahankan permission dan timestamp.'
    source: https://download.samba.org/pub/rsync/rsync.1

  - id: a10
    tier: A
    type: text
    objective: sshd-hardening
    prompt: Tulis baris `sshd_config` (directive dan nilainya) yang **mematikan login dengan password**.
    answers: ['PasswordAuthentication no']
    tests:
      fail: ['PermitRootLogin no', 'PubkeyAuthentication no', 'UsePAM no', 'PasswordAuthentication yes']
    explanation: 'Di lab: sebelum hardening, `sshpass` login dengan password berhasil (`pw-login-ok`). Sesudahnya: `Permission denied (publickey)`, sedangkan login key tetap jalan. Matikan juga `KbdInteractiveAuthentication`, karena lewat PAM ia bisa menjadi jalur password alternatif.'
    source: https://man.openbsd.org/sshd_config#PasswordAuthentication

  - id: a11
    tier: A
    type: command
    objective: sshd-hardening
    prompt: Validasi syntax konfigurasi sshd sebelum reload.
    answers: ['sshd -t']
    patterns: ['^(/usr/sbin/)?sshd -t$']
    tests:
      fail: ['sshd -T', 'systemctl status ssh', 'ssh -t localhost']
    explanation: 'Config yang rusak + reload/restart = sshd tidak mau start, dan kamu terkunci dari server. Di modul Text Processing, typo `PermitRootLogn` tertangkap: `Bad configuration option`, exit 255.'
    source: https://man.openbsd.org/sshd

  - id: a12
    tier: A
    type: command
    objective: sshd-hardening
    prompt: Tampilkan nilai **efektif** `passwordauthentication` setelah sshd menggabungkan semua file config.
    answers: ['sshd -T | grep -i passwordauthentication']
    patterns: ['^(/usr/sbin/)?sshd -T ?\| ?grep (-i |-E |-Ei |-iE )?\^?passwordauthentication\S*$', '^(/usr/sbin/)?sshd -T ?\| ?grep (-E |-Ei |-iE )?\^?PasswordAuthentication\S*$']
    tests:
      pass: ['sshd -T | grep passwordauthentication']
      fail: ['grep PasswordAuthentication /etc/ssh/sshd_config', 'sshd -t', 'cat /etc/ssh/sshd_config']
    explanation: '`sshd -T` mencetak config final dalam huruf kecil, termasuk isi `sshd_config.d/*.conf` dan default bawaan. Di lab setelah hardening: `passwordauthentication no`, `permitrootlogin no`, `maxauthtries 3`. `grep` pada satu file tidak memperlihatkan drop-in yang menimpanya.'
    source: https://man.openbsd.org/sshd

  - id: a13
    tier: A
    type: command
    objective: sshd-hardening
    prompt: Di Ubuntu, terapkan perubahan `sshd_config` (selain port) tanpa memutus sesi yang sedang berjalan.
    answers: ['systemctl reload ssh']
    patterns: ['^systemctl (reload|restart) (ssh|sshd)(\.service)?$']
    tests:
      pass: ['systemctl restart ssh', 'systemctl reload ssh.service']
      fail: ['service ssh stop', 'ssh reload', 'systemctl reload sshd.conf']
    explanation: 'Di Ubuntu nama unitnya `ssh` (alias `sshd` juga ada). Sesi yang sudah terbuka tidak terputus oleh reload/restart. Tetap uji login dari **terminal baru** sebelum menutup sesi lama. Untuk perubahan **port**, lihat soal ssh.socket di tier B.'
    source: https://documentation.ubuntu.com/server/how-to/security/openssh-server/

  - id: a14
    tier: A
    type: command
    objective: bruteforce
    prompt: Tampilkan status jail fail2ban `sshd` — jumlah gagal dan daftar IP yang di-ban.
    answers: ['fail2ban-client status sshd']
    patterns: ['^fail2ban-client status sshd$']
    tests:
      fail: ['fail2ban-client status', 'systemctl status fail2ban', 'fail2ban sshd']
    explanation: 'Di lab, setelah 3 password salah dari container lain: `Total failed: 3`, `Banned IP list: 172.18.0.3`. `fail2ban-client status` tanpa nama jail hanya mendaftar jail yang aktif.'
    source: https://github.com/fail2ban/fail2ban/blob/master/config/jail.conf

  - id: a15
    tier: A
    type: command
    objective: client-config
    prompt: Tambahkan private key `~/.ssh/id_ed25519` ke **ssh-agent** yang sedang berjalan.
    answers: ['ssh-add ~/.ssh/id_ed25519']
    patterns: ['^ssh-add (~|\$HOME|/home/\w+)/\.ssh/id_ed25519$']
    tests:
      pass: ['ssh-add $HOME/.ssh/id_ed25519']
      fail: ['ssh-agent ~/.ssh/id_ed25519', 'ssh-add -l', 'ssh-add -D']
    explanation: 'Agent menyimpan key yang sudah dibuka passphrase-nya di memori. Di lab: `Identity added: ... (dev@laptop)`, lalu `ssh-add -l` menampilkan fingerprint-nya. `-D` menghapus semua key dari agent. `ssh-add -t 1h` memberi batas waktu.'
    source: https://man.openbsd.org/ssh-add

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: command
    objective: client-config
    prompt: Login ke `deploy@10.0.4.11` (jaringan privat) **melewati** bastion `ops@bastion.example.com` dalam satu perintah, tanpa agent forwarding.
    answers: ['ssh -J ops@bastion.example.com deploy@10.0.4.11']
    patterns: ['^ssh -J ?ops@bastion\.example\.com deploy@10\.0\.4\.11$', '^ssh -o ProxyJump=ops@bastion\.example\.com deploy@10\.0\.4\.11$']
    tests:
      pass: ['ssh -o ProxyJump=ops@bastion.example.com deploy@10.0.4.11']
      fail: ['ssh -A ops@bastion.example.com', 'ssh ops@bastion.example.com ssh deploy@10.0.4.11', 'ssh -L 22:10.0.4.11:22 ops@bastion.example.com']
    explanation: 'ProxyJump membuat koneksi SSH end-to-end dari laptopmu ke target, dengan bastion hanya meneruskan TCP. Key privat tidak pernah ada di bastion. Di lab, log target menulis `Accepted publickey for ops from 127.0.0.1` (bastion), tetapi autentikasinya memakai key dari laptop. Di `~/.ssh/config`: `ProxyJump bastion`.'
    source: https://man.openbsd.org/ssh#J

  - id: b02
    tier: B
    type: text
    objective: client-config
    prompt: 'Tulis directive `~/.ssh/config` (beserta nilainya) yang **mematikan agent forwarding**.'
    answers: ['ForwardAgent no']
    tests:
      fail: ['ForwardAgent yes', 'ProxyJump none', 'AddKeysToAgent no', 'IdentitiesOnly yes']
    explanation: 'Dengan agent forwarding (`-A`), siapa pun yang menjadi root di server tujuan bisa memakai agent-mu selama sesi terbuka, untuk login ke server lain atas namamu. Key-nya memang tidak tercuri, tetapi kemampuannya bisa dipakai. ProxyJump hampir selalu menggantikan kebutuhan `-A`. Di sisi server, padanannya adalah `AllowAgentForwarding no`.'
    source: https://man.openbsd.org/ssh_config#ForwardAgent

  - id: b03
    tier: B
    type: command
    objective: client-config
    prompt: Tampilkan konfigurasi **efektif** untuk host alias `app01` (hostname, user, port, proxyjump) hasil evaluasi `~/.ssh/config`, tanpa melakukan koneksi.
    answers: ['ssh -G app01']
    patterns: ['^ssh -G app01( ?\| ?grep .*)?$']
    tests:
      pass: ['ssh -G app01 | grep -E "^(hostname|port|proxyjump) "']
      fail: ['cat ~/.ssh/config', 'ssh -v app01', 'ssh -T app01']
    explanation: 'Berguna saat config punya banyak blok `Host *`/wildcard, karena aturan pertama yang cocok yang menang. Di lab: `hostname 127.0.0.1`, `port 2222`, `user ops`, `proxyjump bastion`.'
    source: https://man.openbsd.org/ssh#G

  - id: b04
    tier: B
    type: command
    objective: tunnels
    prompt: Buka **local forward**, sehingga `localhost:5433` di laptop diteruskan ke PostgreSQL `127.0.0.1:5432` di server `ops@db01`. Tanpa shell remote (hanya tunnel).
    answers: ['ssh -N -L 5433:127.0.0.1:5432 ops@db01']
    patterns: ['^ssh (?=.*-L ?5433:(127\.0\.0\.1|localhost):5432( |$))((-N|-f|-fN|-Nf|-L ?5433:(127\.0\.0\.1|localhost):5432) )+ops@db01$']
    tests:
      pass: ['ssh -fN -L 5433:localhost:5432 ops@db01', 'ssh -L 5433:127.0.0.1:5432 -N ops@db01']
      fail: ['ssh -R 5433:127.0.0.1:5432 ops@db01', 'ssh -N -L 5432:127.0.0.1:5433 ops@db01', 'ssh -D 5433 ops@db01']
    explanation: 'Format `-L PORT_LOKAL:HOST_DILIHAT_DARI_SERVER:PORT`. `127.0.0.1` di sini adalah localhost **milik server**. Di lab: tunnel `-L 9000:127.0.0.1:8000` → `curl localhost:9000` = 200 ke service yang hanya listen di localhost server. Port tunnel bind ke `127.0.0.1` laptop secara default. Database tidak perlu dibuka ke jaringan.'
    source: https://man.openbsd.org/ssh#L

  - id: b05
    tier: B
    type: text
    objective: tunnels
    prompt: Kamu ingin **server remote** bisa mengakses port 3000 yang berjalan di laptopmu. Opsi forwarding ssh apa yang dipakai?
    answers: ['-R']
    patterns: ['^(ssh )?-r( \S+)?$']
    tests:
      fail: ['-L', '-D', '-J', '-N']
    explanation: '`-R` (remote forward): port di **server** diteruskan ke tujuan di sisi **klien**. `-L` kebalikannya. `-D` membuat SOCKS proxy dinamis. Remote forward dari laptop ke server publik bisa membuka layanan lokal ke internet bila `GatewayPorts` aktif, jadi pakai dengan sadar.'
    source: https://man.openbsd.org/ssh#R

  - id: b06
    tier: B
    type: command
    objective: host-keys
    prompt: Muncul `REMOTE HOST IDENTIFICATION HAS CHANGED`. Dari **konsol server** (bukan lewat jaringan), tampilkan fingerprint host key Ed25519 server untuk dicocokkan.
    answers: ['ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub']
    patterns: ['^ssh-keygen (-lf|-l -f|-fl) /etc/ssh/ssh_host_ed25519_key\.pub$']
    tests:
      pass: ['ssh-keygen -l -f /etc/ssh/ssh_host_ed25519_key.pub']
      fail: ['ssh-keyscan 127.0.0.1', 'cat /etc/ssh/ssh_host_ed25519_key', 'ssh-keygen -R localhost']
    explanation: 'Di lab, setelah host key diganti, klien menolak dengan `Host key verification failed` dan menunjuk `Offending ... key in known_hosts:3`. Fingerprint dari konsol (`SHA256:/tdNMYlh...`) cocok dengan `ssh-keyscan ... | ssh-keygen -lf -`, baru kemudian `ssh-keygen -R` aman dijalankan. Tanpa verifikasi out-of-band, peringatan itu persis yang terlihat saat terjadi MITM.'
    source: https://man.openbsd.org/ssh-keygen

  - id: b07
    tier: B
    type: command
    objective: key-restrictions
    prompt: |
      Tulis **awalan opsi** untuk baris `authorized_keys` (sebelum `ssh-ed25519 AAAA...`) yang mematikan semua forwarding,
      PTY, dan agent, sekaligus **memaksa** key itu hanya menjalankan `/usr/local/bin/backup-receive`.
    answers: ['restrict,command="/usr/local/bin/backup-receive"']
    patterns: ['^(restrict,command=/usr/local/bin/backup-receive|command=/usr/local/bin/backup-receive,restrict)$']
    tests:
      pass: ['command="/usr/local/bin/backup-receive",restrict']
      fail: ['command="/usr/local/bin/backup-receive"', 'restrict', 'no-pty,command="/usr/local/bin/backup-receive"']
    explanation: 'Di lab, dengan `restrict,command="/usr/bin/uptime"`, `ssh host "cat /etc/shadow"` malah menjalankan `uptime`. Perintah klien diabaikan, dan tersedia di `$SSH_ORIGINAL_COMMAND`. Port forward juga ditolak (`refused local port forward`). Cocok untuk key CI, backup, dan monitoring: kalau key itu bocor, dampaknya terbatas pada satu perintah.'
    source: https://man.openbsd.org/sshd#AUTHORIZED_KEYS_FILE_FORMAT

  - id: b08
    tier: B
    type: command
    objective: key-restrictions
    prompt: 'Tulis opsi `authorized_keys` (tanpa key-nya) yang membuat key **hanya diterima** bila koneksi datang dari `10.0.4.0/24`.'
    answers: ['from="10.0.4.0/24"']
    patterns: ['^from=10\.0\.4\.0/24$']
    tests:
      fail: ['allow=10.0.4.0/24', 'permitopen=10.0.4.0/24', 'host=10.0.4.0/24', 'from=10.0.4.0']
    explanation: 'Di lab, key dengan `from="10.9.9.0/24"` yang dipakai dari 127.0.0.1 ditolak: `Permission denied (publickey,password)`. Kombinasikan: `from="10.0.4.0/24",restrict,command="..."`. Key CI yang dicuri jadi tidak berguna dari luar jaringan runner.'
    source: https://man.openbsd.org/sshd#AUTHORIZED_KEYS_FILE_FORMAT

  - id: b09
    tier: B
    type: text
    objective: sshd-hardening
    prompt: Tulis directive `sshd_config` yang membatasi login SSH **hanya** untuk user `ops` dan `deploy`.
    answers: ['AllowUsers ops deploy', 'AllowUsers deploy ops']
    patterns: ['^allowusers (ops deploy|deploy ops)$']
    tests:
      fail: ['AllowGroups ops deploy', 'PermitUsers ops deploy', 'AllowUsers ops,deploy', 'DenyUsers ops deploy']
    explanation: 'Nama dipisah **spasi**. Di lab dengan `AllowUsers ops`, user `dev` yang punya key valid tetap ditolak, dan log mencatat `User dev from 127.0.0.1 not allowed because not listed in AllowUsers`. Untuk tim, `AllowGroups sshusers` lebih mudah dikelola. Pastikan user-mu sendiri ada di daftar sebelum reload.'
    source: https://man.openbsd.org/sshd_config#AllowUsers

  - id: b10
    tier: B
    type: text
    objective: sshd-hardening
    prompt: '`/etc/ssh/sshd_config` berisi `PasswordAuthentication no`, tapi `/etc/ssh/sshd_config.d/50-cloud-init.conf` berisi `PasswordAuthentication yes`. Di Ubuntu 24.04, nilai efektifnya `yes` atau `no`?'
    answers: ['yes']
    tests:
      fail: ['no']
    explanation: 'sshd memakai nilai **pertama** yang ia temukan, dan `Include /etc/ssh/sshd_config.d/*.conf` berada di **awal** `sshd_config` (baris 12). Jadi drop-in menang atas file utama. Di modul Text Processing, mekanisme ini direproduksi dengan `PermitRootLogin`. Taruh hardening di drop-in dengan nama yang terurut lebih awal (mis. `10-hardening.conf`), lalu cek dengan `sshd -T`.'
    source: https://man.openbsd.org/sshd_config

  - id: b11
    tier: B
    type: command
    objective: sshd-hardening
    prompt: Dari sisi **klien**, tampilkan detail proses autentikasi ke `ops@10.0.0.5` untuk men-debug kenapa key ditolak.
    answers: ['ssh -v ops@10.0.0.5']
    patterns: ['^ssh -v{1,3} ops@10\.0\.0\.5$']
    tests:
      pass: ['ssh -vvv ops@10.0.0.5']
      fail: ['ssh -V ops@10.0.0.5', 'ssh -q ops@10.0.0.5', 'ssh -t ops@10.0.0.5']
    explanation: 'Baris kunci di lab: `Authentications that can continue: publickey`, `Offering public key: ... ED25519 SHA256:...`, `Authenticated to ... using "publickey"`. Bila key yang ditawarkan salah, set `IdentityFile` + `IdentitiesOnly yes`. Sisi server: `journalctl -u ssh` atau `/var/log/auth.log`. `-V` besar hanya mencetak versi.'
    source: https://man.openbsd.org/ssh#v

  - id: b12
    tier: B
    type: command
    objective: bruteforce
    prompt: Tampilkan log unit `ssh` **sejak hari ini** yang berisi `Failed password`.
    answers: ['journalctl -u ssh --since today | grep "Failed password"']
    patterns:
      - '^journalctl (-u ?|--unit[= ])ssh(\.service)? (--since|-S)[= ]?today ?\| ?grep (-c |-i )?Failed password$'
      - '^journalctl (--since|-S)[= ]?today (-u ?|--unit[= ])ssh(\.service)? ?\| ?grep (-c |-i )?Failed password$'
    tests:
      pass: ['journalctl --since today -u ssh | grep -c "Failed password"', 'journalctl -u ssh.service -S today | grep ''Failed password''']
      fail: ['journalctl -u ssh', 'last -n 20', 'journalctl -u sshd --since today']
    explanation: 'Di Ubuntu unitnya `ssh`, bukan `sshd`. Untuk ranking IP: `... | grep -oE ''from [0-9.]+'' | sort | uniq -c | sort -rn` (lab: `4 172.17.0.2`). Di server dengan rsyslog, log yang sama ada di `/var/log/auth.log`. `lastb` menampilkan login gagal dari `/var/log/btmp`.'
    source: https://documentation.ubuntu.com/server/how-to/security/openssh-server/

  - id: b13
    tier: B
    type: command
    objective: bruteforce
    prompt: IP kantor `203.0.113.7` ter-ban oleh jail `sshd` karena salah ketik password berkali-kali. Lepaskan ban-nya.
    answers: ['fail2ban-client set sshd unbanip 203.0.113.7']
    patterns: ['^fail2ban-client set sshd unbanip 203\.0\.113\.7$', '^fail2ban-client unban 203\.0\.113\.7$']
    tests:
      pass: ['fail2ban-client unban 203.0.113.7']
      fail: ['fail2ban-client unbanip 203.0.113.7', 'fail2ban-client set sshd banip 203.0.113.7', 'systemctl restart ssh']
    explanation: 'Di lab, selama di-ban, bahkan password yang **benar** ditolak dengan `Connection refused`. Setelah unban, login berhasil. Untuk IP tepercaya permanen: `ignoreip = 127.0.0.1/8 203.0.113.7` di `jail.local` (jangan edit `jail.conf`).'
    source: https://github.com/fail2ban/fail2ban/blob/master/config/jail.conf

  - id: b14
    tier: B
    type: text
    objective: bruteforce
    prompt: 'Saat menguji fail2ban, kamu mencoba login salah berkali-kali **dari server itu sendiri**, tetapi `Total failed` tetap 0. Opsi jail apa (default `true`) penyebabnya?'
    answers: ['ignoreself']
    patterns: ['^ignoreself( ?= ?true)?$']
    tests:
      fail: ['bantime', 'maxretry', 'findtime', 'banaction']
    explanation: 'Direproduksi di lab: serangan dari IP container itu sendiri → `Total failed: 0`; serangan yang sama dari container lain → `Total failed: 3` dan IP di-ban. `ignoreself` mencegah fail2ban mem-ban alamat milik host. Uji fail2ban selalu dari mesin lain.'
    source: https://github.com/fail2ban/fail2ban/blob/master/config/jail.conf

  - id: b15
    tier: B
    type: text
    objective: sshd-hardening
    prompt: 'Ubuntu 24.04: kamu mengganti `Port 22` menjadi `Port 2222` di `sshd_config` lalu `systemctl restart ssh`, tetapi sshd masih listen di port 22. Unit apa yang harus di-restart (setelah `systemctl daemon-reload`)?'
    answers: ['ssh.socket']
    patterns: ['^(systemctl (restart )?)?ssh\.socket$']
    tests:
      fail: ['ssh.service', 'sshd', 'ssh', 'networking']
    explanation: 'Sejak Ubuntu 22.10, sshd dijalankan lewat **socket activation**. Port diambil dari `sshd_config` oleh generator saat `daemon-reload`, lalu dipegang `ssh.socket`. Di lab (systemd 255): setelah `restart ssh` port tetap `:22`; setelah `daemon-reload` + `restart ssh.socket` port menjadi `:2222`, dengan file `/run/systemd/generator/ssh.socket.d/addresses.conf`. Buka port baru di firewall **sebelum** pindah.'
    source: https://discourse.ubuntu.com/t/sshd-now-uses-socket-based-activation-ubuntu-22-10-and-later/30189

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: sshd-hardening
    prompt: |
      Server Ubuntu 24.04 baru: login masih dengan password, root bisa login, dan image cloud membawa drop-in
      `50-cloud-init.conf`. Tulis langkah hardening sshd (perintah dan baris config, satu per baris) yang aman:
      pastikan key-mu jalan dulu, terapkan kebijakan lewat drop-in, pastikan tidak ada drop-in lain yang menimpa,
      validasi, terapkan, dan uji dari sesi baru.
    checklist:
      - id: key-first
        label: Memastikan login key berhasil lebih dulu (ssh-copy-id / uji ssh dengan BatchMode/password off)
        required: true
        patterns: ['ssh-copy-id', 'ssh\s+.*-o\s*(passwordauthentication=no|batchmode=yes|pubkeyauthentication=yes)']
      - id: dropin
        label: Menulis drop-in di /etc/ssh/sshd_config.d/
        patterns: ['/etc/ssh/sshd_config\.d/\S+\.conf']
      - id: pw-off
        label: PasswordAuthentication no
        required: true
        patterns: ['^passwordauthentication\s+no$']
      - id: kbd-off
        label: KbdInteractiveAuthentication no
        patterns: ['^kbdinteractiveauthentication\s+no$']
      - id: root-off
        label: PermitRootLogin no / prohibit-password
        patterns: ['^permitrootlogin\s+(no|prohibit-password)$']
      - id: allow
        label: Membatasi user (AllowUsers/AllowGroups)
        patterns: ['^allow(users|groups)\s+\S+']
      - id: others
        label: Memeriksa drop-in lain / config efektif (grep sshd_config.d, sshd -T)
        patterns: ['grep\s+.*sshd_config\.d', 'sshd\s+-T', 'ls\s+.*sshd_config\.d']
      - id: validate
        label: sshd -t sebelum reload
        required: true
        patterns: ['sshd\s+-t\b']
      - id: apply
        label: Reload ssh
        patterns: ['systemctl\s+(reload|restart)\s+ssh(d)?(\.service)?\b']
      - id: no-weaken
        label: Tidak mengaktifkan password/root login
        forbid: true
        patterns: ['^passwordauthentication\s+yes', '^permitrootlogin\s+yes']
      - id: no-firewall-lockout
        label: Tidak memblokir port SSH sendiri
        forbid: true
        patterns: ['ufw\s+deny\s+(22|ssh)', 'ufw\s+default\s+deny\s+incoming\s*&&\s*ufw\s+enable']
    pass_score: 0.5
    solution: |
      ```bash
      ssh-copy-id ops@server && ssh -o PasswordAuthentication=no ops@server true   # key OK?
      sudo tee /etc/ssh/sshd_config.d/10-hardening.conf >/dev/null <<'EOF'
      PasswordAuthentication no
      KbdInteractiveAuthentication no
      PermitRootLogin no
      AllowUsers ops deploy
      MaxAuthTries 3
      X11Forwarding no
      EOF
      grep -rn -i 'passwordauthentication\|permitrootlogin' /etc/ssh/sshd_config.d/
      sudo sshd -t && sudo sshd -T | grep -E '^(passwordauthentication|permitrootlogin|allowusers|maxauthtries) '
      sudo systemctl reload ssh
      ssh ops@server true          # dari TERMINAL BARU, sesi lama tetap terbuka
      ```
      Diuji di lab dengan drop-in `10-hardening.conf`:
      - `sshd -T` → `passwordauthentication no`, `permitrootlogin no`, `allowusers ops`, `maxauthtries 3`.
      - Login password → `Permission denied (publickey)`.
      - Root dengan key yang valid tetap ditolak.
      - User di luar `AllowUsers` ditolak dengan log `not listed in AllowUsers`.

      Nama `10-` membuat file ini dibaca **sebelum** `50-cloud-init.conf`. Karena sshd memakai nilai pertama,
      kebijakanmu yang menang. Tetap periksa isi drop-in lain dan hapus nilai yang bertentangan.
    tests:
      pass:
        - |
          ssh-copy-id ops@server
          ssh -o PasswordAuthentication=no ops@server true
          /etc/ssh/sshd_config.d/10-hardening.conf
          PasswordAuthentication no
          KbdInteractiveAuthentication no
          PermitRootLogin no
          AllowUsers ops
          grep -r PasswordAuthentication /etc/ssh/sshd_config.d/
          sshd -t
          systemctl reload ssh
        - |
          ssh -o BatchMode=yes ops@server true
          PasswordAuthentication no
          PermitRootLogin prohibit-password
          sshd -t
          sshd -T | grep passwordauthentication
      fail:
        - |
          PasswordAuthentication no
          systemctl restart ssh
        - |
          ssh-copy-id ops@server
          PermitRootLogin yes
          PasswordAuthentication no
          sshd -t
        - |
          ssh-copy-id ops@server
          PasswordAuthentication no
          ufw deny 22
          sshd -t
    source: https://man.openbsd.org/sshd_config

  - id: c02
    tier: C
    type: scenario
    objective: tunnels
    prompt: |
      Database PostgreSQL `db01` (10.0.4.20) hanya bisa dijangkau dari jaringan privat, dan bastion publiknya
      `bastion.example.com` (user `ops`). Kamu perlu menjalankan `psql` dari laptop. Tulis isi `~/.ssh/config` dan
      perintah-perintahnya (satu per baris) untuk masuk lewat bastion **tanpa** agent forwarding, membuka tunnel ke
      PostgreSQL, dan konek dengan psql — tanpa membuka port database ke internet.
    checklist:
      - id: host-bastion
        label: Blok Host bastion (HostName, User)
        patterns: ['^host\s+bastion\b']
      - id: host-db
        label: Blok Host db01 dengan HostName privat
        patterns: ['^host\s+db01\b', '^hostname\s+10\.0\.4\.20$']
      - id: proxyjump
        label: ProxyJump lewat bastion
        required: true
        patterns: ['^proxyjump\s+\S+', 'ssh\s+(\S+\s+)*-J\s*\S+']
      - id: tunnel
        label: Local forward ke 5432 (-L PORT:127.0.0.1:5432 / LocalForward)
        required: true
        patterns: ['-L\s*\d+:(127\.0\.0\.1|localhost|10\.0\.4\.20):5432', '^localforward\s+\d+\s+(127\.0\.0\.1|localhost|10\.0\.4\.20):5432']
      - id: no-agent
        label: ForwardAgent no
        patterns: ['^forwardagent\s+no$']
      - id: identity
        label: IdentityFile (+ IdentitiesOnly)
        patterns: ['^identityfile\s+\S+', '^identitiesonly\s+yes$']
      - id: psql
        label: psql ke localhost:port tunnel
        patterns: ['psql\s+.*(-h\s*(127\.0\.0\.1|localhost)|(127\.0\.0\.1|localhost):\d+)']
      - id: no-agent-forward
        label: Tidak ForwardAgent yes
        forbid: true
        patterns: ['^forwardagent\s+yes']
      - id: no-insecure-hostkey
        label: Tidak mematikan verifikasi host key
        forbid: true
        patterns: ['stricthostkeychecking\s*=?\s*no', 'userknownhostsfile\s*=?\s*/dev/null']
      - id: no-open-db
        label: Tidak membuka 5432 di firewall
        forbid: true
        patterns: ['ufw\s+allow\s+5432']
    pass_score: 0.5
    solution: |
      ```text
      # ~/.ssh/config
      Host bastion
          HostName bastion.example.com
          User ops
          IdentityFile ~/.ssh/id_ed25519
          IdentitiesOnly yes
          ForwardAgent no
      Host db01
          HostName 10.0.4.20
          User ops
          ProxyJump bastion
          LocalForward 5433 127.0.0.1:5432
      ```
      ```bash
      ssh -G db01 | grep -E '^(hostname|proxyjump|localforward) '
      ssh -fN db01                       # tunnel saja, di background
      psql -h 127.0.0.1 -p 5433 -U app appdb
      ```
      Diuji di lab dengan dua sshd (bastion :22, target :2222):
      - `ssh app01` lewat `ProxyJump bastion` berhasil.
      - Tunnel `-L 9000:127.0.0.1:8000` menjangkau service yang hanya listen di localhost target (`200`).

      `127.0.0.1:5432` di `LocalForward` dievaluasi **di db01**, jadi PostgreSQL boleh tetap listen di
      localhost. Database tidak terekspos, bastion tidak pernah memegang key-mu, dan host key tiap server tetap
      diverifikasi. Untuk menutup tunnel, cari prosesnya (`pgrep -af 'ssh -fN db01'`) lalu `kill`.
    tests:
      pass:
        - |
          Host bastion
          HostName bastion.example.com
          User ops
          ForwardAgent no
          Host db01
          HostName 10.0.4.20
          ProxyJump bastion
          ssh -fN -L 5433:127.0.0.1:5432 db01
          psql -h 127.0.0.1 -p 5433 appdb
        - |
          ssh -N -J ops@bastion.example.com -L 5433:localhost:5432 ops@10.0.4.20
          psql "postgresql://app@localhost:5433/appdb"
          IdentitiesOnly yes
      fail:
        - |
          ssh -A ops@bastion.example.com
          ForwardAgent yes
          ssh ops@10.0.4.20
        - |
          ufw allow 5432
          psql -h 10.0.4.20 appdb
        - |
          ProxyJump bastion
          ssh -o StrictHostKeyChecking=no -L 5433:127.0.0.1:5432 db01
    source: https://man.openbsd.org/ssh_config#ProxyJump

  - id: c03
    tier: C
    type: scenario
    objective: incident
    prompt: |
      Alert: lonjakan login gagal di `web01`, dan ada satu login **berhasil** yang mencurigakan. Tulis langkah respons
      (satu perintah per baris): analisis sumber serangan, temukan sesi/login yang berhasil, periksa key yang ditanam,
      cabut akses penyerang, dan tutup celahnya — tanpa menghilangkan bukti.
    context: |
      $ sudo grep -c 'Failed password' /var/log/auth.log
      4812
      $ sudo grep 'Accepted' /var/log/auth.log | tail -n 2
      2026-11-20T03:14:07 web01 sshd[9921]: Accepted publickey for ops from 10.0.4.5 port 50112 ssh2: ED25519 SHA256:WhP5...
      2026-11-20T03:41:55 web01 sshd[9988]: Accepted password for deploy from 203.0.113.7 port 41822 ssh2
    checklist:
      - id: rank-ip
        label: Ranking IP sumber login gagal (grep | awk/grep -o | sort | uniq -c)
        patterns: ['grep\s+.*failed password.*\|.*sort.*\|\s*uniq\s+-c', 'journalctl.*\|.*failed password.*\|.*uniq\s+-c']
      - id: accepted
        label: Menelusuri login berhasil / sesi aktif (grep Accepted, last, who)
        required: true
        patterns: ['grep\s+.*(accepted|203\.0\.113\.7)', '^last\b', '^who\b', '^w$', 'loginctl\s+list-sessions']
      - id: keys
        label: Memeriksa authorized_keys user deploy (fingerprint)
        required: true
        patterns: ['authorized_keys']
      - id: contain
        label: Mengunci akun & memutus sesi (chage -E 0 / passwd -l + pkill -u / loginctl terminate-user)
        required: true
        patterns: ['chage\s+-E\s*0\s+deploy', 'usermod\s+.*-e\s*1\s+.*deploy', 'pkill\s+(-\S+\s+)*-u\s*deploy', 'loginctl\s+terminate-user\s+deploy']
      - id: pw-off
        label: Menutup celah (PasswordAuthentication no)
        patterns: ['passwordauthentication\s+no']
      - id: fail2ban
        label: fail2ban / blok IP penyerang
        patterns: ['fail2ban', 'ufw\s+(deny|insert\s+\d+\s+deny)\s+from\s+203\.0\.113\.7', 'nft\s+.*203\.0\.113\.7']
      - id: persistence
        label: Mencari persistence lain (crontab -l -u deploy, file baru)
        patterns: ['crontab\s+(-l\s+-u\s+deploy|-u\s+deploy\s+-l)', 'find\s+.*(-newer|-mmin|-mtime)']
      - id: no-destroy-evidence
        label: Tidak menghapus/mengosongkan log atau history
        forbid: true
        patterns: ['rm\s+(-\S+\s+)*/var/log/(auth|wtmp|btmp|syslog)', '>\s*/var/log/auth\.log', 'history\s+-c', 'journalctl\s+--vacuum', 'truncate\s+.*/var/log/']
    pass_score: 0.5
    solution: |
      ```bash
      sudo cp -a /var/log/auth.log /root/ir-auth.log.$(date +%s)        # amankan bukti dulu
      sudo grep 'Failed password' /var/log/auth.log | grep -oE 'from [0-9.]+' | sort | uniq -c | sort -rn | head
      sudo grep -E 'Accepted .* from 203\.0\.113\.7' /var/log/auth.log
      last -i deploy | head; who
      sudo chage -E 0 deploy && sudo loginctl terminate-user deploy      # kunci TOTAL, lalu putus sesi
      sudo ssh-keygen -lf /home/deploy/.ssh/authorized_keys              # key asing?
      sudo crontab -l -u deploy; sudo find / -xdev -newer /root/ir-auth.log.* -user deploy 2>/dev/null | head
      # sshd drop-in: PasswordAuthentication no → sshd -t → systemctl reload ssh
      sudo fail2ban-client status sshd
      ```
      Urutan berdasarkan bukti di context:
      - 4.812 kali password salah, lalu `Accepted password for deploy from 203.0.113.7`, artinya password
        `deploy` tertebak. Tanda pertama adalah login password yang berhasil dari IP publik, padahal tim memakai key.
      - `chage -E 0` (bukan `passwd -l`, yang tidak memblokir key di Ubuntu, lihat modul User & sudo) mengunci
        akun termasuk key yang mungkin sudah ditanam penyerang.
      - `ssh-keygen -lf authorized_keys` menampilkan fingerprint dan komentar setiap key (lab: satu baris per key),
        sehingga key asing mudah dikenali.
      - Cek persistence: cron, service baru, dan file yang baru diubah.

      Jangan menghapus log atau history, karena itu bukti. Perbaikan permanen: password login mati, fail2ban aktif,
      **rotasi** semua secret yang bisa diakses user `deploy`, dan pertimbangkan reinstall bila ada tanda eskalasi ke root.
    tests:
      pass:
        - |
          grep 'Failed password' /var/log/auth.log | grep -oE 'from [0-9.]+' | sort | uniq -c | sort -rn
          grep 'Accepted' /var/log/auth.log | grep 203.0.113.7
          chage -E 0 deploy
          loginctl terminate-user deploy
          ssh-keygen -lf /home/deploy/.ssh/authorized_keys
          crontab -l -u deploy
          PasswordAuthentication no
          fail2ban-client status sshd
        - |
          last -i deploy
          pkill -KILL -u deploy
          cat /home/deploy/.ssh/authorized_keys
          ufw insert 1 deny from 203.0.113.7
      fail:
        - |
          passwd -l deploy
          rm /var/log/auth.log
        - |
          grep Accepted /var/log/auth.log
          history -c
          chage -E 0 deploy
        - |
          ufw deny from 203.0.113.7
          systemctl restart ssh
    source: https://man.openbsd.org/sshd#AUTHORIZED_KEYS_FILE_FORMAT

---

# SSH & Hardening

SSH adalah pintu masuk utama ke server Linux, dan karena itu juga pintu yang paling sering dicoba penyerang. Modul
ini membahas penggunaan SSH sehari-hari yang aman (key, config, bastion, tunnel, transfer file), cara mengeraskan
sshd dengan konfigurasi yang benar-benar berlaku, dan cara merespons ketika ada yang berhasil masuk. Modul ini
juga menutup board Linux: banyak kebiasaan dari modul sebelumnya bertemu di sini.

> [!NOTE]
> Semua perilaku diuji di Ubuntu 24.04 dengan OpenSSH 9.6p1. Lab memakai dua sshd (port 22 sebagai bastion dan
> 2222 sebagai target) untuk ProxyJump, dua container terpisah untuk fail2ban, dan image systemd untuk ssh.socket.
> IP `203.0.113.x`, `bastion.example.com`, dan `db01` di soal adalah contoh.

## 1. Key SSH

```bash
ssh-keygen -t ed25519 -C "dev@laptop"        # beri passphrase
ssh-copy-id ops@10.0.0.5                     # pasang ke authorized_keys server
ssh-keygen -lf ~/.ssh/id_ed25519.pub         # fingerprint: 256 SHA256:... dev@laptop (ED25519)
eval "$(ssh-agent -s)" && ssh-add ~/.ssh/id_ed25519
```

| File | Sisi | Mode |
|---|---|---|
| `~/.ssh/id_ed25519` | Klien — **rahasia** | 600 |
| `~/.ssh/id_ed25519.pub` | Klien — boleh dibagikan | 644 |
| `~/.ssh/known_hosts` | Klien — host key server yang dipercaya | 644 |
| `~/.ssh/config` | Klien — alias & opsi | 600 |
| `~/.ssh/authorized_keys` | Server — key yang boleh login | 600 |

## 2. `~/.ssh/config`, bastion, dan agent

```text
Host bastion
    HostName bastion.example.com
    User ops
    IdentityFile ~/.ssh/id_ed25519
    IdentitiesOnly yes
    ForwardAgent no

Host app01
    HostName 10.0.4.11
    User deploy
    ProxyJump bastion
```

```bash
ssh app01                    # lab: "via-jump", log target: Accepted publickey ... from bastion
ssh -G app01 | grep -E '^(hostname|port|user|proxyjump) '
ssh -J ops@bastion.example.com deploy@10.0.4.11     # tanpa config
```

```mermaid
flowchart LR
    L[Laptop<br/>key privat + agent] -- "TCP diteruskan (ProxyJump)" --> B[Bastion]
    B --> T[app01]
    L -. "SSH end-to-end: autentikasi & enkripsi" .-> T
```

> [!DANGER]
> **Agent forwarding (`ssh -A`)** membuka socket agent-mu di server tujuan. Root di server itu (atau penyerang yang
> menjadi root) bisa memakai agent-mu untuk login ke server lain atas namamu selama sesimu terbuka. ProxyJump
> memberi hasil yang sama (login ke host di belakang bastion) tanpa membawa agent ke mana pun. Set
> `ForwardAgent no` sebagai default, dan `AllowAgentForwarding no` di bastion.
> Sumber: [ssh_config(5) — ForwardAgent](https://man.openbsd.org/ssh_config#ForwardAgent)

## 3. Host key dan `known_hosts`

Saat pertama konek, ssh menyimpan host key server. Bila key itu berubah, ssh menolak dengan peringatan besar. Di lab:

```text
@    WARNING: REMOTE HOST IDENTIFICATION HAS CHANGED!     @
Offending ECDSA key in /home/dev/.ssh/known_hosts:3
Host key verification failed.
```

Penyebabnya bisa server yang di-reinstall atau ada yang menyamar (MITM). Prosedurnya:
1. Cocokkan fingerprint dari jalur lain (konsol cloud, orang di lokasi): `ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub`.
2. Baru setelah cocok, jalankan `ssh-keygen -R host`.

Jangan memakai `StrictHostKeyChecking=no` atau `UserKnownHostsFile=/dev/null` sebagai kebiasaan. Untuk
otomatisasi, pakai `accept-new` (hanya menerima host yang belum dikenal) atau distribusikan `known_hosts`.

## 4. Tunnel

| Opsi | Arah | Contoh |
|---|---|---|
| `-L 5433:127.0.0.1:5432` | Port **lokal** → tujuan dari sudut pandang server | Akses DB yang listen di localhost server |
| `-R 9000:127.0.0.1:3000` | Port **di server** → tujuan di sisi klien | Tunjukkan app lokal ke server |
| `-D 1080` | SOCKS proxy dinamis | Browsing lewat server |
| `-N` / `-f` | Tanpa shell / ke background | Tunnel saja |

Di lab: `ssh -fN -L 9000:127.0.0.1:8000 bastion`, lalu `curl localhost:9000` → `200` ke service yang hanya listen di
`127.0.0.1:8000` milik server.

## 5. Transfer file

```bash
scp file.gz ops@10.0.0.5:/tmp/              # scp: port pakai -P besar
rsync -az --delete --dry-run -v site/ deploy@10.0.0.5:/var/www/site/   # lihat dulu
rsync -az --delete site/ deploy@10.0.0.5:/var/www/site/
```

Di lab:
- `rsync site/` mengirim **isi** direktori, sedangkan `rsync site` membuat `/tujuan/site/`.
- File yang dihapus di sumber tetap ada di tujuan, sampai rsync dijalankan dengan `--delete`.
- `--dry-run -v` mencetak `deleting old.txt` sebelum benar-benar menghapus.

## 6. Hardening sshd

```text
# /etc/ssh/sshd_config.d/10-hardening.conf
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin no
AllowUsers ops deploy
MaxAuthTries 3
X11Forwarding no
AllowAgentForwarding no          # opsional, untuk bastion
```

```bash
sudo sshd -t                                    # syntax
sudo sshd -T | grep -E '^(passwordauthentication|permitrootlogin|allowusers|maxauthtries) '
sudo systemctl reload ssh                       # uji dari terminal BARU
```

| Directive | Hasil lab setelah diterapkan |
|---|---|
| `PasswordAuthentication no` | Login password → `Permission denied (publickey)` |
| `PermitRootLogin no` | root dengan key valid → ditolak |
| `AllowUsers ops` | `dev` dengan key valid → `not listed in AllowUsers` |
| `MaxAuthTries 3` | Batas percobaan per koneksi |

> [!WARNING]
> Dua jebakan khas Ubuntu 24.04, keduanya direproduksi di lab:
> 1. **Drop-in menang.** `Include /etc/ssh/sshd_config.d/*.conf` ada di awal `sshd_config`, dan sshd memakai nilai
>    **pertama**. `50-cloud-init.conf` berisi `PasswordAuthentication yes` akan mengalahkan `no` di file utama. Selalu
>    cek dengan `sshd -T`.
> 2. **Socket activation.** Port dipegang `ssh.socket`. Setelah mengganti `Port`, `systemctl restart ssh` saja tetap
>    listen di `:22`. Perlu `systemctl daemon-reload && systemctl restart ssh.socket`. Buka port baru di firewall dulu.
> Sumber: [Ubuntu Discourse — socket-based activation](https://discourse.ubuntu.com/t/sshd-now-uses-socket-based-activation-ubuntu-22-10-and-later/30189)

## 7. Membatasi key per kegunaan

```text
# ~/.ssh/authorized_keys
ssh-ed25519 AAAA... dev@laptop
from="10.0.4.0/24",restrict,command="/usr/local/bin/backup-receive" ssh-ed25519 AAAA... backup@ci
restrict,port-forwarding,permitopen="127.0.0.1:5432" ssh-ed25519 AAAA... dbtunnel@analyst
```

Di lab:
- `restrict,command="/usr/bin/uptime"` membuat `ssh host "cat /etc/shadow"` hanya mencetak `uptime`, dan port
  forward ditolak (`refused local port forward`).
- `from="10.9.9.0/24"` dari alamat lain → `Permission denied`.

Key untuk mesin (CI, backup, monitoring) sebaiknya **selalu** dibatasi seperti ini.

## 8. Brute force: fail2ban dan log

```ini
# /etc/fail2ban/jail.local
[sshd]
enabled  = true
maxretry = 3
findtime = 10m
bantime  = 1h
ignoreip = 127.0.0.1/8 10.0.0.0/8
```

```bash
sudo fail2ban-client status sshd
sudo fail2ban-client set sshd unbanip 203.0.113.7
journalctl -u ssh --since today | grep 'Failed password' | grep -oE 'from [0-9.]+' | sort | uniq -c | sort -rn
last -i | head; sudo lastb | head
```

> [!INSIGHT]
> Di lab, fail2ban **tidak** mem-ban apa pun saat serangan datang dari IP server itu sendiri (`ignoreself`, default
> true). Ketika diuji dari container lain, setelah 3 password salah: `Banned IP list: 172.18.0.3`, dan percobaan
> berikutnya, **termasuk dengan password yang benar**, langsung `Connection refused`. fail2ban mengurangi kebisingan dan
> memperlambat brute force, tetapi bukan pengganti `PasswordAuthentication no`. Dengan password mati, brute force
> password tidak punya peluang sama sekali.
> Sumber: [fail2ban jail.conf](https://github.com/fail2ban/fail2ban/blob/master/config/jail.conf)

## 9. Checklist hardening server Linux (rangkuman board)

| Area | Minimal | Modul |
|---|---|---|
| Akses | Key-only, tanpa root login, `AllowUsers/Groups`, MFA/bastion bila perlu | SSH & Hardening |
| Akun | User per orang, offboarding `chage -E 0`, sudo least-privilege | User, Group & sudo |
| Patch | `unattended-upgrades`, restart service setelah upgrade library | Package Management |
| Jaringan | Service internal listen di localhost/privat, ufw default deny, hati-hati Docker `-p` | Networking Dasar |
| Service | User non-root, sandbox systemd, secret bukan di `Environment=` | Service & systemd |
| File | Tanpa 777, secret 600/640, audit SUID & world-writable | Permission & Ownership |
| Deteksi | Log terpusat, fail2ban, alert login password yang berhasil | SSH & Hardening |
| Otomasi | Skrip `set -euo pipefail`, shellcheck, tanpa secret di argumen | Bash Scripting, Process |

Tool audit seperti **Lynis** atau CIS Benchmark untuk Ubuntu 24.04 bisa memeriksa ratusan item di atas secara
otomatis. Hasilnya adalah daftar tugas, bukan skor yang harus 100.

## 10. Ringkasan kebiasaan aman

| Kebiasaan | Kenapa |
|---|---|
| Ed25519 + passphrase + agent; satu key per orang/perangkat | Mudah dicabut, aman bila laptop hilang |
| ProxyJump, bukan `-A` | Agent tidak terekspos di server lain |
| Verifikasi fingerprint sebelum `ssh-keygen -R` | Peringatan host key bisa berarti MITM |
| Hardening di drop-in `10-*.conf` + `sshd -t` + `sshd -T` | Drop-in lain bisa menimpa; config rusak = terkunci |
| Uji dari terminal baru sebelum menutup sesi | Rollback masih mungkin |
| `restrict,command=,from=` untuk key mesin | Key bocor berdampak minimal |
| Password login mati + fail2ban + alert `Accepted password` | Brute force jadi tidak relevan |
| Respons insiden: amankan log, `chage -E 0`, cek key & cron, rotasi secret | `passwd -l` tidak memblokir key |
