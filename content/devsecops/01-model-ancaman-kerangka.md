---
format: 1
id: sec-threat-model-frameworks
course:
  slug: devsecops
  title: DevSecOps
  description: Keamanan untuk DevOps dari dua sisi — cara kerja red team dan cara blue team mendeteksi serta menutupnya. Model ancaman, hardening server & container, keamanan web, ketersediaan (anti-DDoS), deteksi, respons insiden, dan keamanan pipeline. Semua praktik di lab terisolasi milik sendiri.
title: Model Ancaman & Kerangka Kerja
summary: Fondasi berpikir keamanan dari dua sisi — Cyber Kill Chain dan MITRE ATT&CK sebagai peta cara kerja penyerang, CIA triad dan STRIDE/DREAD untuk memodelkan ancaman, peran red/blue/purple team, prinsip defense-in-depth dan least privilege, serta aturan etis dan cara membangun lab yang benar-benar terisolasi dari internet supaya latihan serangan tidak pernah bocor keluar.
order: 1
version: 1
status: verified
updated: '2026-11-25'
tested_on:
  - Docker Desktop (Windows), Engine 29.4.1 — network `--internal` (dj-sec-internal) + bridge (dj-sec-bridge)
  - alpine:3.20 — nmap 7.95, curl, bind-tools, python3 http.server sebagai target lab
objectives:
  - id: adversary-mind
    text: Memakai Cyber Kill Chain dan MITRE ATT&CK untuk memetakan cara kerja penyerang
  - id: threat-model
    text: Memodelkan ancaman dengan CIA triad, STRIDE, dan DREAD
  - id: teams
    text: Membedakan peran red, blue, dan purple team serta prinsip pertahanan berlapis
  - id: rules
    text: Menerapkan aturan etis, scope, dan otorisasi dalam latihan keamanan
  - id: lab-setup
    text: Membangun lab terisolasi dari internet untuk latihan serangan yang aman
  - id: evidence
    text: Menyadari bahwa setiap serangan meninggalkan jejak yang bisa dideteksi blue team
sources:
  - title: Lockheed Martin — Cyber Kill Chain
    url: https://www.lockheedmartin.com/en-us/capabilities/cyber/cyber-kill-chain.html
  - title: MITRE ATT&CK — Enterprise Matrix (tactics & techniques)
    url: https://attack.mitre.org/
  - title: MITRE ATT&CK — Tactics
    url: https://attack.mitre.org/tactics/enterprise/
  - title: OWASP — Threat Modeling (STRIDE)
    url: https://owasp.org/www-community/Threat_Modeling_Process
  - title: Microsoft — The STRIDE Threat Model
    url: https://learn.microsoft.com/en-us/azure/security/develop/threat-modeling-tool-threats
  - title: NIST SP 800-115 — Technical Guide to Information Security Testing (rules of engagement)
    url: https://csrc.nist.gov/pubs/sp/800/115/final
  - title: Docker — internal networks (disable egress)
    url: https://docs.docker.com/reference/cli/docker/network/create/#internal
  - title: Nmap — Host discovery (-Pn) & version detection (-sV)
    url: https://nmap.org/book/man-host-discovery.html
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: adversary-mind
    prompt: Model 7 tahap dari Lockheed Martin yang menggambarkan alur serangan dari pengintaian sampai aksi di tujuan disebut apa?
    answers: ['Cyber Kill Chain']
    patterns: ['(cyber )?kill[- ]?chain']
    tests:
      fail: ['MITRE ATT&CK', 'OWASP Top 10', 'STRIDE', 'CIA triad']
    explanation: 'Cyber Kill Chain: Reconnaissance → Weaponization → Delivery → Exploitation → Installation → Command & Control (C2) → Actions on Objectives. Gunanya untuk bertahan: makin awal rantai diputus, makin murah. Mendeteksi di tahap Recon jauh lebih baik daripada baru sadar saat data sudah dicuri (Actions on Objectives). Bedakan dengan ATT&CK yang lebih rinci dan tidak linear.'
    source: https://www.lockheedmartin.com/en-us/capabilities/cyber/cyber-kill-chain.html

  - id: a02
    tier: A
    type: text
    objective: adversary-mind
    prompt: Tahap **pertama** Cyber Kill Chain, saat penyerang mengumpulkan informasi tentang target sebelum menyerang?
    answers: ['Reconnaissance']
    patterns: ['recon(naissance)?', 'pengintaian', 'pengumpulan informasi']
    tests:
      fail: ['Delivery', 'Exploitation', 'Weaponization', 'Impact']
    explanation: 'Di lab, `nmap -Pn` dari container attacker menemukan port 80 dan 8080 target dalam 0,12 detik — itu Reconnaissance. Recon bisa pasif (mencari info publik tanpa menyentuh target) atau aktif (scan port, yang ramai di log). Blue team menutupnya dengan mengurangi attack surface dan memantau scan. Di modul 02 kita dalami exposure ini.'
    source: https://nmap.org/book/man-host-discovery.html

  - id: a03
    tier: A
    type: text
    objective: adversary-mind
    prompt: Basis pengetahuan MITRE yang mengkatalogkan taktik dan teknik serangan nyata (TA0001, T1190, dst) disebut apa?
    answers: ['ATT&CK']
    patterns: ['att&ck', 'attack', 'mitre att&?ck']
    tests:
      fail: ['Kill Chain', 'CVE', 'CVSS', 'STRIDE']
    explanation: 'MITRE ATT&CK menyusun serangan menjadi **taktik** (tujuan penyerang, mis. Persistence) dan **teknik** (cara mencapainya). Beda dari Kill Chain yang linear, ATT&CK adalah matriks: penyerang bisa melompat antar-taktik. Blue team memakainya untuk memetakan deteksi ("teknik mana yang kita bisa lihat?") dan menemukan celah pemantauan.'
    source: https://attack.mitre.org/

  - id: a04
    tier: A
    type: text
    objective: threat-model
    prompt: 'Dalam CIA triad (Confidentiality, Integrity, Availability), huruf **A** adalah apa?'
    answers: ['Availability']
    patterns: ['availabilit(y|as)', 'ketersediaan']
    tests:
      fail: ['Authentication', 'Authorization', 'Accounting', 'Auditing']
    explanation: 'CIA triad adalah tiga sifat yang dilindungi keamanan: **Confidentiality** (data hanya dilihat yang berhak), **Integrity** (data tidak diubah diam-diam), **Availability** (layanan tetap bisa diakses). Serangan DDoS menyerang Availability — itulah alasan modul 08 (anti-DDoS) ada di board ini. Setiap kontrol keamanan sebaiknya bisa dipetakan ke sifat mana yang dijaganya.'
    source: https://owasp.org/www-community/Threat_Modeling_Process

  - id: a05
    tier: A
    type: text
    objective: threat-model
    prompt: 'Dalam STRIDE, huruf **D** mewakili kategori ancaman apa?'
    answers: ['Denial of Service']
    patterns: ['denial[- ]?of[- ]?service', '\bdos\b', 'penolakan layanan']
    tests:
      fail: ['Data breach', 'Disclosure', 'Decryption', 'Downgrade']
    explanation: 'STRIDE memecah ancaman jadi 6 kategori: **S**poofing (menyamar), **T**ampering (mengubah data), **R**epudiation (menyangkal aksi), **I**nformation disclosure (kebocoran), **D**enial of Service (melumpuhkan), **E**levation of privilege (naik hak akses). Tiap kategori lawannya satu properti keamanan — Denial of Service melawan Availability. STRIDE membantu kita bertanya sistematis "apa yang bisa salah?" per komponen.'
    source: https://learn.microsoft.com/en-us/azure/security/develop/threat-modeling-tool-threats

  - id: a06
    tier: A
    type: text
    objective: teams
    prompt: Tim yang menyatukan red team (penyerang) dan blue team (bertahan) agar saling belajar dalam satu loop disebut tim apa?
    answers: ['purple team']
    patterns: ['purple( team)?', 'tim ungu']
    tests:
      fail: ['green team', 'white team', 'red team', 'blue team']
    explanation: 'Red team menyerang untuk menemukan celah; blue team bertahan dan mendeteksi; **purple team** adalah kolaborasi keduanya: red menunjukkan teknik, blue memverifikasi apakah deteksinya jalan, lalu diperbaiki. Board ini sengaja berpasangan (serang → deteksi → tutup) karena itulah pola purple team. Tujuanmu memahami cara kerja red justru untuk memperkuat sisi blue.'
    source: https://attack.mitre.org/

  - id: a07
    tier: A
    type: text
    objective: rules
    prompt: Dokumen yang menetapkan target apa saja yang **boleh** diuji, kapan, dan sampai sebatas apa dalam sebuah pengujian keamanan disebut apa (singkatan 3 huruf)?
    answers: ['RoE']
    patterns: ['\broe\b', 'rules of engagement', 'aturan keterlibatan']
    tests:
      fail: ['SLA', 'NDA', 'EULA', 'MOU']
    explanation: 'Rules of Engagement (RoE) + otorisasi tertulis adalah batas hukum dan etis. Menguji sistem tanpa izin adalah ilegal, sekalipun niatnya belajar. Di board ini, RoE-mu sederhana dan mutlak: **hanya target milikmu sendiri di lab terisolasi**. Itu sebabnya modul-modul serangan dibangun di container `dj-` yang tidak terhubung ke internet.'
    source: https://csrc.nist.gov/pubs/sp/800/115/final

  - id: a08
    tier: A
    type: command
    objective: lab-setup
    prompt: Buat Docker network bernama `dj-sec-internal` yang **tidak punya akses keluar ke internet** (terisolasi), untuk lab serangan.
    answers: ['docker network create --internal dj-sec-internal']
    patterns: ['^docker network create( (--driver bridge|-d bridge))? --internal( (--driver bridge|-d bridge))? dj-sec-internal$']
    tests:
      pass: ['docker network create -d bridge --internal dj-sec-internal', 'docker network create --internal --driver bridge dj-sec-internal']
      fail: ['docker network create dj-sec-internal', 'docker network create --isolated dj-sec-internal', 'docker run --internal dj-sec-internal']
    explanation: 'Di lab, container di network `--internal` → `ping 8.8.8.8` memberi `Network unreachable`, `curl https://example.com` gagal (exit 6), dan DNS eksternal `SERVFAIL`. Inilah dinding pengaman: serangan apa pun tidak bisa nyasar ke luar. Tapi `--internal` juga memblokir `apk add`, jadi pasang tool lewat network biasa dulu, baru pindahkan container ke `--internal` (lihat a15).'
    source: https://docs.docker.com/reference/cli/docker/network/create/#internal

  - id: a09
    tier: A
    type: command
    objective: adversary-mind
    prompt: Scan port host target `10.90.0.10` dengan nmap **tanpa** melakukan ping dulu (anggap host pasti hidup).
    answers: ['nmap -Pn 10.90.0.10']
    patterns: ['^(?=.*\s-Pn(\s|$))nmap( (-[A-Za-z-]+|-p ?\S+|-T\d))* 10\.90\.0\.10$']
    tests:
      pass: ['nmap -Pn -T4 10.90.0.10', 'nmap -Pn -p 1-100 10.90.0.10']
      fail: ['nmap 10.90.0.10', 'nmap -sn 10.90.0.10', 'nmap -pn 10.90.0.10', 'ping 10.90.0.10']
    explanation: '`-Pn` (huruf P besar, n kecil) melewati host discovery dan langsung scan port — berguna saat target membuang ICMP ping tetapi port-nya tetap merespons. Di lab → `80/tcp open http`, `8080/tcp open http-proxy`, port lain `closed (reset)`. Bedakan dari `-sn` (hanya ping sweep, tanpa scan port) dan `-pn` yang bukan opsi valid. Ini aksi Reconnaissance yang ramai — blue team seharusnya melihat lonjakan koneksi ke banyak port.'
    source: https://nmap.org/book/man-host-discovery.html

  - id: a10
    tier: A
    type: command
    objective: adversary-mind
    prompt: Ambil **hanya header HTTP** (banner) dari `http://10.90.0.10/` dengan curl, untuk melihat software server yang dipakai.
    answers: ['curl -I http://10.90.0.10/']
    patterns: ['^(?=.*\s(-[A-Za-z]*I[A-Za-z]*|--head)(\s|$))curl( -[A-Za-z]+| --head)* http://10\.90\.0\.10/?$']
    tests:
      pass: ['curl -sI http://10.90.0.10/', 'curl --head http://10.90.0.10/', 'curl -I http://10.90.0.10']
      fail: ['curl http://10.90.0.10/', 'curl -i http://10.90.0.10/', 'wget http://10.90.0.10/', 'curl -X HEAD http://10.90.0.10/']
    explanation: 'Di lab → `Server: SimpleHTTP/0.6 Python/3.12.13`. Itu **information disclosure** (STRIDE "I"): penyerang kini tahu teknologi dan versinya, lalu mencari CVE yang cocok. `-I` (besar) mengirim request HEAD; `-i` (kecil) justru menampilkan body + header. Blue team menutupnya dengan menyembunyikan/menyeragamkan banner (`server_tokens off` di nginx, bahas modul 07).'
    source: https://nmap.org/book/man-host-discovery.html

  - id: a11
    tier: A
    type: text
    objective: adversary-mind
    prompt: Saat nmap men-scan port TCP yang **tertutup**, flag TCP apa yang dikirim balik oleh host?
    answers: ['RST']
    patterns: ['^rst', 'reset']
    tests:
      fail: ['SYN', 'FIN', 'ACK', 'ICMP unreachable']
    explanation: 'Di lab, nmap melaporkan `Not shown: 100 closed tcp ports (reset)` — port tertutup membalas RST, sehingga penyerang tahu host hidup tapi port itu kosong. Bandingkan dengan port yang **di-filter** firewall (DROP): tidak ada balasan sama sekali, dan nmap menandainya `filtered`. Perbedaan `closed` vs `filtered` ini (dibahas di board Jaringan modul 03 & 10) memberi tahu penyerang apakah ada firewall.'
    source: https://nmap.org/book/man-host-discovery.html

  - id: a12
    tier: A
    type: text
    objective: evidence
    prompt: Di sebuah web server, berkas mana yang merekam IP, waktu, dan path setiap request — sehingga jejak scan penyerang bisa dilihat?
    answers: ['access log']
    patterns: ['access[ ._-]?log', 'log akses']
    tests:
      fail: ['error log', 'dmesg', 'crontab', 'bash history']
    explanation: 'Di lab, request penyerang ke `/secret-path` terekam di access log: `172.18.0.2 - - [25/Nov/2026 ...] "GET /secret-path HTTP/1.1" 404`. Inti sisi blue: **setiap serangan meninggalkan jejak**. Tapi perhatikan — log default `http.server` tidak merekam User-Agent, jadi "evil-scanner/1.0" hilang. Logging yang kurang lengkap adalah celah deteksi tersendiri (bahas modul 09).'
    source: https://attack.mitre.org/

  - id: a13
    tier: A
    type: text
    objective: teams
    prompt: Prinsip memasang **banyak lapis** kontrol keamanan agar jebolnya satu lapis tidak langsung berarti kalah, disebut apa?
    answers: ['defense in depth']
    patterns: ['defen[cs]e[- ]?in[- ]?depth', 'pertahanan berlapis', 'lapis(an)? pertahanan']
    tests:
      fail: ['zero trust', 'least privilege', 'fail2ban', 'air gap']
    explanation: 'Defense in depth: firewall + hardening + rate limit + deteksi + backup, sehingga satu celah tidak fatal. Board ini membangunnya lapis demi lapis: akses server (03), image (05), runtime (06), aplikasi (07), ketersediaan (08), deteksi (09), respons (10). Penyerang hanya perlu satu jalan masuk; karena itu bertahan berarti menutup banyak jalan sekaligus, bukan mengandalkan satu benteng.'
    source: https://attack.mitre.org/

  - id: a14
    tier: A
    type: text
    objective: teams
    prompt: Pola pikir keamanan yang berasumsi "penyerang mungkin **sudah** di dalam", sehingga fokusnya ke deteksi dan pembatasan gerak, bukan hanya mencegah masuk, disebut apa?
    answers: ['assume breach']
    patterns: ['assume[- ]?breach', 'anggap (sudah )?(jebol|tertembus|disusupi)']
    tests:
      fail: ['defense in depth', 'least privilege', 'security through obscurity', 'perimeter defense']
    explanation: '"Assume breach" melengkapi defense in depth: karena pencegahan tidak pernah 100%, kita juga berinvestasi pada deteksi, segmentasi, dan least privilege agar penyerang yang sudah masuk sulit bergerak (Lateral Movement) dan cepat ketahuan. Ini dasar Zero Trust. Praktiknya muncul di modul 06 (membatasi gerak dalam container) dan 09–10 (deteksi & respons).'
    source: https://attack.mitre.org/

  - id: a15
    tier: A
    type: command
    objective: lab-setup
    prompt: Lepaskan container `dj-atk` dari network `dj-sec-bridge` (yang punya internet) supaya bisa dipindahkan ke network terisolasi.
    answers: ['docker network disconnect dj-sec-bridge dj-atk']
    patterns: ['^docker network disconnect( -f| --force)? dj-sec-bridge dj-atk$']
    tests:
      pass: ['docker network disconnect -f dj-sec-bridge dj-atk']
      fail: ['docker network connect dj-sec-bridge dj-atk', 'docker network rm dj-sec-bridge', 'docker network disconnect dj-atk dj-sec-bridge']
    explanation: 'Urutan argumen: `disconnect <network> <container>`. Alur lab yang aman: (1) jalankan container di `dj-sec-bridge` yang punya internet, `apk add` tool yang diperlukan; (2) `disconnect` dari bridge; (3) `connect` ke `dj-sec-internal`. Setelah itu container bersenjata lengkap tetapi terkurung tanpa jalan ke internet. Ini mengakali keterbatasan `--internal` yang memblokir instalasi paket.'
    source: https://docs.docker.com/reference/cli/docker/network/create/#internal

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: adversary-mind
    prompt: Taktik MITRE ATT&CK yang bertujuan agar akses penyerang **bertahan** meski sistem reboot atau kredensial diganti, disebut apa?
    answers: ['Persistence']
    patterns: ['persisten(ce|si)', 'bertahan']
    tests:
      fail: ['Execution', 'Initial Access', 'Impact', 'Collection']
    explanation: 'Persistence (TA0003) = cara penyerang kembali tanpa mengulang eksploit: cron job, service, SSH key tambahan, akun baru, atau image/container yang dimodifikasi. Blue team mendeteksinya dengan memantau perubahan pada mekanisme autostart dan membandingkan dengan baseline. Dalam konteks container, image yang immutable dan read-only filesystem mempersulit persistence (modul 06).'
    source: https://attack.mitre.org/tactics/enterprise/

  - id: b02
    tier: B
    type: text
    objective: adversary-mind
    prompt: Taktik ATT&CK saat penyerang berpindah dari satu host/sistem yang sudah dikuasai ke host lain di dalam jaringan, disebut apa?
    answers: ['Lateral Movement']
    patterns: ['lateral[- ]?movement', 'pergerakan lateral', 'gerak(an)? (ke )?samping']
    tests:
      fail: ['Privilege Escalation', 'Discovery', 'Exfiltration', 'Persistence']
    explanation: 'Lateral Movement (TA0008): dari satu pijakan, penyerang melompat ke host lain memakai kredensial curian atau service internal. Di sinilah segmentasi jaringan berperan — network `--internal` dan subnet terpisah (board Jaringan) membatasi ke mana penyerang bisa melompat. "Assume breach" (a14) menganggap satu host sudah jatuh dan berusaha menahan gerak berikutnya.'
    source: https://attack.mitre.org/tactics/enterprise/

  - id: b03
    tier: B
    type: text
    objective: adversary-mind
    prompt: Taktik ATT&CK saat penyerang menaikkan level akses, misalnya dari user biasa menjadi root, disebut apa?
    answers: ['Privilege Escalation']
    patterns: ['privilege[- ]?escalation', 'eskalasi (hak )?(akses|privilege)', 'peningkatan hak']
    tests:
      fail: ['Lateral Movement', 'Initial Access', 'Credential Access', 'Execution']
    explanation: 'Privilege Escalation (TA0004): dari akses terbatas ke lebih tinggi, lewat misconfig sudo, SUID, capability berlebih, atau kernel exploit. Di dunia container, inilah "container escape" — keluar dari container ke host. Lawannya: least privilege (b07), drop capabilities, non-root user, seccomp/AppArmor (modul 06). Di board Docker modul 09 kita sudah lihat `bash -p` tidak bisa eskalasi di Docker Desktop.'
    source: https://attack.mitre.org/tactics/enterprise/

  - id: b04
    tier: B
    type: text
    objective: adversary-mind
    prompt: Dalam ATT&CK, singkatan **TTP** (yang dipakai untuk mendeskripsikan perilaku penyerang) adalah kepanjangan dari apa?
    answers: ['Tactics, Techniques, and Procedures']
    patterns: ['tactics[, ]+techniques[, ]+(and )?procedures', 'taktik[, ]+teknik[, ]+(dan )?prosedur']
    tests:
      fail: ['Tools, Tactics, Procedures', 'Threats, Targets, Payloads', 'Tactics, Threats, Protocols']
    explanation: 'TTP = Tactics (tujuan, mis. Persistence), Techniques (cara umum, mis. cron job), Procedures (implementasi spesifik satu kelompok penyerang). Semakin ke "procedure" semakin mudah penyerang mengubahnya, tetapi "tactic" relatif stabil. Deteksi berbasis perilaku (TTP) lebih tahan lama daripada berbasis indikator statis seperti hash file atau IP, yang gampang diganti.'
    source: https://attack.mitre.org/

  - id: b05
    tier: B
    type: text
    objective: threat-model
    prompt: Dalam STRIDE, kategori **Tampering** melanggar properti keamanan yang mana?
    answers: ['Integrity']
    patterns: ['integrit(y|as)', 'keutuhan']
    tests:
      fail: ['Confidentiality', 'Availability', 'Authentication', 'Authorization']
    explanation: 'Pemetaan STRIDE ke properti: Spoofing→Authentication, Tampering→**Integrity**, Repudiation→Non-repudiation, Information disclosure→Confidentiality, Denial of Service→Availability, Elevation of privilege→Authorization. Tampering = mengubah data/kode tanpa izin; lawannya kontrol integritas: checksum, signing (cosign untuk image, modul 05), dan file-integrity monitoring.'
    source: https://learn.microsoft.com/en-us/azure/security/develop/threat-modeling-tool-threats

  - id: b06
    tier: B
    type: text
    objective: threat-model
    prompt: Model penilaian risiko dengan 5 faktor (Damage, Reproducibility, Exploitability, Affected users, Discoverability) untuk memprioritaskan ancaman, disebut apa?
    answers: ['DREAD']
    patterns: ['^dread$']
    tests:
      fail: ['STRIDE', 'CVSS', 'DREAD-X', 'PASTA']
    explanation: 'DREAD memberi skor tiap ancaman (hasil STRIDE) agar yang paling berbahaya ditangani dulu: **D**amage, **R**eproducibility, **E**xploitability, **A**ffected users, **D**iscoverability. Alternatif industrinya CVSS (skor per kerentanan). Intinya sama: sumber daya terbatas, jadi urutkan berdasarkan risiko = dampak × kemungkinan, bukan menambal semuanya sekaligus secara acak.'
    source: https://owasp.org/www-community/Threat_Modeling_Process

  - id: b07
    tier: B
    type: text
    objective: teams
    prompt: Prinsip memberi setiap user/proses **hanya hak akses seminimal** yang diperlukan untuk tugasnya disebut apa?
    answers: ['least privilege']
    patterns: ['least[- ]?privilege', 'hak (akses )?(paling )?minim(al|um)', 'privilege minim(al|um)']
    tests:
      fail: ['defense in depth', 'need to know', 'separation of duties', 'zero trust']
    explanation: 'Least privilege membatasi kerusakan bila satu akun/komponen jatuh: user DB read-only, container non-root dengan capability di-drop, runner CI dengan token ber-scope sempit. Ini langsung memangkas Privilege Escalation (b03) dan Lateral Movement (b02). Prinsip ini berulang di seluruh board: setiap kali bertanya "apakah ini butuh akses sebesar itu?", jawabannya biasanya tidak.'
    source: https://csrc.nist.gov/pubs/sp/800/115/final

  - id: b08
    tier: B
    type: command
    objective: adversary-mind
    prompt: Scan **seluruh 65535 port TCP** host `10.90.0.10` dengan nmap (tanpa ping), bukan hanya 1000 port populer.
    answers: ['nmap -Pn -p- 10.90.0.10']
    patterns: ['^(?=.*\s-Pn(\s|$))(?=.*\s(-p-|-p ?1-65535)(\s|$))nmap( (-[A-Za-z-]+|-p-|-p ?1-65535|-T\d))* 10\.90\.0\.10$']
    tests:
      pass: ['nmap -p- -Pn 10.90.0.10', 'nmap -Pn -p 1-65535 -T4 10.90.0.10']
      fail: ['nmap -Pn 10.90.0.10', 'nmap -Pn -p 1000 10.90.0.10', 'nmap -Pn -F 10.90.0.10']
    explanation: 'Default nmap hanya memindai ~1000 port umum, sehingga service pada port tidak lazim (mis. admin panel di 8443 atau 9000) bisa terlewat. `-p-` = semua port 1–65535. Dari sisi blue, inilah alasan "security through obscurity" (menyembunyikan service di port aneh) bukan pertahanan — scan penuh tetap menemukannya. Yang melindungi adalah firewall default-deny, bukan nomor port yang tidak biasa.'
    source: https://nmap.org/book/man-host-discovery.html

  - id: b09
    tier: B
    type: command
    objective: adversary-mind
    prompt: Deteksi **versi service** yang berjalan di port terbuka host `10.90.0.10` dengan nmap.
    answers: ['nmap -sV 10.90.0.10']
    patterns: ['^(?=.*\s-sV(\s|$))nmap( (-[A-Za-z-]+|-p ?\S+|-T\d))* 10\.90\.0\.10$']
    tests:
      pass: ['nmap -sV -Pn 10.90.0.10', 'nmap -Pn -sV -p 80,8080 10.90.0.10']
      fail: ['nmap -sv 10.90.0.10', 'nmap -A 10.90.0.10 -sVersion', 'nmap 10.90.0.10 --version']
    explanation: '`-sV` (s kecil, V besar) menyelidiki tiap port terbuka untuk menebak software + versinya, memperkuat info banner (a10). Dari versi itu penyerang memetakan CVE yang relevan. Ini jembatan ke modul 05 (image & supply chain): menjaga versi tetap ter-patch menghapus kerentanan sebelum penyerang bisa mencocokkannya. Hati-hati, `-sV` lebih berisik/lambat dan sangat terlihat di log.'
    source: https://nmap.org/book/man-host-discovery.html

  - id: b10
    tier: B
    type: text
    objective: evidence
    prompt: Access log default Python `http.server` mencatat IP, waktu, request line, dan status — tetapi **tidak** mencatat satu field penting yang sering dipakai mengenali tool scanner. Field apa itu?
    answers: ['User-Agent']
    patterns: ['user[- ]?agent']
    tests:
      fail: ['Referer', 'Cookie', 'X-Forwarded-For', 'Host']
    explanation: 'Di lab, request dikirim dengan `-A ''evil-scanner/1.0''`, tetapi log hanya memuat `172.18.0.2 ... "GET /secret-path HTTP/1.1" 404` — **User-Agent hilang**. Pelajarannya: deteksi hanya sebaik logging-nya. Blue team mengaktifkan log format yang lengkap (User-Agent, Referer, waktu respons, X-Forwarded-For dari proxy tepercaya) sebelum insiden terjadi, karena log yang tidak dikumpulkan tidak bisa diputar ulang.'
    source: https://attack.mitre.org/

  - id: b11
    tier: B
    type: text
    objective: threat-model
    prompt: 'Header `Server: SimpleHTTP/0.6 Python/3.12.13` yang membocorkan software dan versi termasuk kategori STRIDE yang mana?'
    answers: ['Information Disclosure']
    patterns: ['information[- ]?disclosure', 'kebocoran (informasi|data)', 'pengungkapan informasi']
    tests:
      fail: ['Spoofing', 'Tampering', 'Denial of Service', 'Elevation of Privilege']
    explanation: 'Membocorkan detail internal (versi software, path, stack trace, pesan error rinci) adalah Information Disclosure — melanggar Confidentiality. Sendirian tidak merusak, tapi mempercepat tahap Reconnaissance penyerang. Mitigasi: `server_tokens off`, halaman error generik, dan tidak menampilkan stack trace ke user. Ini contoh bahwa "kebocoran kecil" tetap memperbesar risiko total.'
    source: https://learn.microsoft.com/en-us/azure/security/develop/threat-modeling-tool-threats

  - id: b12
    tier: B
    type: text
    objective: adversary-mind
    prompt: Taktik **terakhir** dalam matriks ATT&CK — tujuan merusak, yang mencakup penghancuran data, enkripsi ransomware, dan DDoS — disebut apa?
    answers: ['Impact']
    patterns: ['^impact$', 'dampak']
    tests:
      fail: ['Exfiltration', 'Actions on Objectives', 'Collection', 'Command and Control']
    explanation: 'Impact (TA0040) mencakup teknik seperti Data Destruction, Data Encrypted for Impact (ransomware), dan Network Denial of Service. Ini menyerang Integrity dan Availability. Di Kill Chain, padanannya adalah "Actions on Objectives". Modul 08 (anti-DDoS) dan modul 10 (backup & IR) adalah pertahanan untuk taktik Impact — memastikan layanan dan data bisa pulih.'
    source: https://attack.mitre.org/tactics/enterprise/

  - id: b13
    tier: B
    type: text
    objective: adversary-mind
    prompt: Saluran yang dipakai malware/penyerang untuk menerima perintah dan mengirim data dari host korban, sering disingkat **C2**, kepanjangannya apa?
    answers: ['Command and Control']
    patterns: ['command[- ]?(and|&|n)?[- ]?control', 'komando (dan )?kendali', 'kendali (dan )?komando']
    tests:
      fail: ['Compromise and Control', 'Common Controls', 'Control Center', 'Command Console']
    explanation: 'Command and Control (C2 / C&C) adalah tahap Kill Chain tempat host yang sudah dikuasai "menelepon pulang" ke server penyerang untuk menerima instruksi. Blue team mendeteksinya dari trafik keluar yang aneh (beaconing berkala, domain mencurigakan, tujuan tak dikenal). Di lab kita, network `--internal` memutus C2 total — host yang disusupi tidak punya jalan keluar untuk menghubungi siapa pun.'
    source: https://www.lockheedmartin.com/en-us/capabilities/cyber/cyber-kill-chain.html

  - id: b14
    tier: B
    type: text
    objective: lab-setup
    prompt: Dari container di network `--internal`, perintah `nslookup example.com` mengembalikan status apa, yang membuktikan tidak ada jalan ke resolver luar?
    answers: ['SERVFAIL']
    patterns: ['servfail', 'server fail(ure)?', 'gagal']
    tests:
      fail: ['NXDOMAIN', 'NOERROR', 'REFUSED', 'timeout']
    explanation: 'Di lab → `** server can''t find example.com: SERVFAIL`, sementara `curl https://example.com` keluar dengan kode 6 ("couldn''t resolve host") dan `ping 8.8.8.8` → `Network unreachable`. Tiga bukti independen bahwa egress mati. Bedakan dari NXDOMAIN (nama memang tidak ada) — SERVFAIL di sini berarti resolver tidak bisa dihubungi sama sekali. Selalu verifikasi isolasi lab sebelum memulai latihan serangan.'
    source: https://docs.docker.com/reference/cli/docker/network/create/#internal

  - id: b15
    tier: B
    type: text
    objective: adversary-mind
    prompt: ATT&CK membedakan **Reconnaissance** (sebelum akses, dari luar) dengan taktik enumerasi **setelah** penyerang masuk untuk mengenali lingkungan internal. Taktik internal itu disebut apa?
    answers: ['Discovery']
    patterns: ['discovery', 'penemuan', 'enumerasi internal']
    tests:
      fail: ['Reconnaissance', 'Collection', 'Initial Access', 'Exfiltration']
    explanation: 'Reconnaissance (TA0043) terjadi di luar, sebelum masuk (scan port, OSINT). **Discovery** (TA0007) terjadi di dalam, setelah Initial Access: penyerang memetakan user, proses, jaringan, dan share dari host yang sudah dikuasai (`whoami`, `ip route`, `ps`, `env`). Membedakan keduanya penting untuk deteksi: Discovery terlihat sebagai aktivitas enumerasi yang tidak wajar dari sebuah akun/host internal.'
    source: https://attack.mitre.org/tactics/enterprise/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: rules
    prompt: |
      Kamu ingin belajar teknik serangan agar bisa membangun pertahanannya, tetapi hanya punya satu laptop dan tidak
      boleh menyentuh sistem orang lain. Rancang lab latihan yang **aman dan etis** untuk mempraktikkan serangan ke
      sebuah aplikasi web contoh (satu poin per baris: isolasi, otorisasi, dan kebersihan lab).
    checklist:
      - id: isolate
        label: Jaringan terisolasi tanpa egress ke internet (docker --internal)
        required: true
        patterns: ['--internal', '(terisolasi|isolasi|tanpa (akses )?internet|tanpa egress)', 'air[- ]?gap']
      - id: own-target
        label: Hanya target milik sendiri / aplikasi contoh yang sengaja dibuat
        required: true
        patterns: ['(target|sistem|aplikasi)[^\n]*(sendiri|milik(ku)?|punya sendiri)', '(aplikasi|app) (contoh|lab|rentan|dummy)', 'sengaja dibuat']
      - id: verify-iso
        label: Verifikasi isolasi sebelum mulai (curl/ping/nslookup keluar harus gagal)
        patterns: ['(verifikasi|cek|pastikan|uji)[^\n]*(isolasi|egress|internet|keluar)', 'network unreachable', 'servfail', 'curl[^\n]*gagal']
      - id: snapshot
        label: Pakai container/VM sekali pakai atau snapshot agar mudah di-reset
        patterns: ['(snapshot|reset|sekali pakai|disposable|ephemeral)', '(docker rm|hapus container|bangun ulang|rebuild)']
      - id: no-realdata
        label: Tidak memakai data/kredensial produksi di lab
        patterns: ['(tanpa|tidak (pakai|memakai|menggunakan))[^\n]*(data|kredensial|secret)[^\n]*(produksi|asli|nyata)', 'data (dummy|palsu|contoh)']
      - id: contain
        label: Cleanup setelah selesai (hapus container & network dj-)
        patterns: ['(cleanup|bersih(kan)?|hapus)[^\n]*(container|network|lab)', 'docker (rm|network rm)']
      - id: no-scan-public
        label: Tidak men-scan / menyerang sistem publik atau pihak lain
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|hindari|tidak boleh|tanpa izin|dilarang))[^\n]*((scan|serang|nmap|curl|ddos)[^\n]*(internet|publik|orang lain|pihak lain|produksi|situs)|target (publik|internet|orang))']
    pass_score: 0.5
    solution: |
      ```text
      1. Jaringan: docker network create --internal dj-sec-internal   (tidak ada egress ke internet)
      2. Target: aplikasi web contoh yang SENGAJA dibuat/dipilih untuk lab, hanya milik sendiri
         (bukan sistem produksi, bukan milik pihak lain)
      3. Pasang tool di network biasa dulu, lalu disconnect + connect ke dj-sec-internal
      4. Verifikasi isolasi SEBELUM mulai:
           ping 8.8.8.8        → Network unreachable
           curl https://...    → exit 6 (couldn't resolve)
           nslookup example.com→ SERVFAIL
      5. Pakai container sekali pakai / snapshot → mudah reset kalau "rusak"
      6. Tidak ada data atau kredensial produksi di dalam lab; gunakan data dummy
      7. Selesai: docker rm -f dj-* && docker network rm dj-sec-internal dj-sec-bridge
      ```
      Aturan etis mutlak: **hanya target milikmu sendiri, di lab tertutup.** Menyerang/scan sistem orang lain atau
      publik tanpa otorisasi tertulis adalah ilegal, sekalipun untuk belajar. Isolasi `--internal` yang terbukti
      (Network unreachable, SERVFAIL) memastikan serangan tidak bisa bocor keluar, dan container sekali pakai membuat
      latihan bisa diulang tanpa risiko. Verifikasi isolasi dulu — jangan berasumsi.
    tests:
      pass:
        - |
          docker network create --internal dj-sec-internal supaya tidak ada egress
          target aplikasi contoh yang sengaja dibuat, hanya milik sendiri, bukan produksi
          pasang tool lewat bridge lalu disconnect dan connect ke internal
          verifikasi isolasi dulu: ping 8.8.8.8 Network unreachable, nslookup SERVFAIL
          pakai container sekali pakai supaya mudah reset
          tidak memakai data atau kredensial produksi, pakai data dummy
          selesai cleanup docker rm -f dj- dan network rm
        - |
          jaringan terisolasi tanpa internet dengan --internal
          hanya menyerang aplikasi lab milik sendiri
          cek egress keluar harus gagal sebelum mulai
          container disposable, data dummy, cleanup setelah selesai
      fail:
        - |
          scan dulu beberapa situs publik buat latihan
          baru bikin lab sendiri
        - |
          pakai database produksi biar realistis
          target aplikasi di internet
          tidak perlu isolasi yang penting hati-hati
        - |
          buat container target
          langsung serang
    source: https://csrc.nist.gov/pubs/sp/800/115/final

  - id: c02
    tier: C
    type: scenario
    objective: adversary-mind
    prompt: |
      Post-mortem sebuah insiden: (1) penyerang men-scan server dan menemukan panel admin lama, (2) login dengan
      password default, (3) memasang cron job agar tetap punya akses, (4) dari server itu masuk ke database internal,
      (5) menyalin data keluar. Petakan tiap langkah ke taktik Kill Chain/ATT&CK dan tunjukkan di mana blue team bisa
      memutus rantai (satu poin per baris).
    checklist:
      - id: recon
        label: Langkah 1 = Reconnaissance (scan menemukan panel)
        required: true
        patterns: ['(1|langkah 1|scan)[^\n]*recon', 'recon[^\n]*(scan|panel|1)', 'reconnaissance']
      - id: access
        label: Langkah 2 = Initial Access (password default)
        required: true
        patterns: ['(2|langkah 2|login|password default)[^\n]*(initial access|akses awal|exploitation)', '(initial access|akses awal)[^\n]*(password|login|2)']
      - id: persist
        label: Langkah 3 = Persistence (cron job)
        required: true
        patterns: ['(3|langkah 3|cron)[^\n]*persisten', 'persisten[^\n]*(cron|3)']
      - id: lateral
        label: Langkah 4 = Lateral Movement (masuk ke DB internal)
        patterns: ['(4|langkah 4|database|db)[^\n]*lateral', 'lateral[^\n]*(movement|db|database|4)']
      - id: exfil
        label: Langkah 5 = Exfiltration (menyalin data keluar)
        patterns: ['(5|langkah 5|menyalin|salin|copy)[^\n]*(exfil|exfiltration)', 'exfil(tration)?[^\n]*(data|keluar|5)']
      - id: break-early
        label: Blue team memutus sedini mungkin (hapus panel lama / ganti password default)
        required: true
        patterns: ['(hapus|matikan|pensiun|nonaktifkan)[^\n]*(panel|admin)( lama)?', '(ganti|ubah)[^\n]*password default', '(sedini|seawal|paling awal|recon|exposure)[^\n]*(putus|tutup|cegah)']
      - id: detect
        label: Titik deteksi (brute/login anomali, cron baru, trafik DB/egress tak wajar)
        patterns: ['(deteksi|alert|log)[^\n]*(login|brute|cron|egress|db|trafik)', '(login anomali|cron baru|egress)', 'monitoring']
      - id: not-only-end
        label: Tidak hanya bergantung mencegah di langkah terakhir (exfiltration saja)
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak cukup|tidak boleh|selain))[^\n]*(cukup|hanya)[^\n]*(blokir|cegah|tahan)[^\n]*(exfil|data keluar|langkah (terakhir|5))']
    pass_score: 0.5
    solution: |
      ```text
      1. Scan menemukan panel admin lama      → Reconnaissance (TA0043)
      2. Login dengan password default        → Initial Access (TA0001) via Valid Accounts / default creds
      3. Pasang cron job                       → Persistence (TA0003)
      4. Masuk ke database internal            → Lateral Movement (TA0008) (+ Credential Access/Discovery)
      5. Menyalin data keluar                  → Exfiltration (TA0010) / Impact bila dirusak

      Memutus rantai (makin awal makin murah):
        - Recon/Exposure : pensiunkan & hapus panel admin lama; firewall default-deny; kurangi attack surface.
        - Initial Access : hilangkan password default, wajib MFA, fail2ban + rate limit login.
        - Persistence    : pantau cron/service baru, file-integrity monitoring, image immutable + read-only.
        - Lateral Movement: segmentasi jaringan (DB di subnet internal), least privilege, kredensial unik per layanan.
        - Exfiltration   : batasi & pantau egress; network internal memutus jalur keluar (C2 & exfil).
      ```
      Kill Chain mengajarkan: bertahan berlapis, dan utamakan memutus di tahap awal. Kalau panel lama sudah dihapus
      (Recon) atau password default sudah diganti (Initial Access), rantai putus sebelum ada cron, lateral movement,
      atau exfiltration. Mengandalkan satu deteksi di langkah terakhir (data sudah mau keluar) berarti sudah terlambat
      — itu sebabnya defense in depth dan deteksi di banyak titik lebih baik daripada satu benteng tunggal.
    tests:
      pass:
        - |
          langkah 1 scan panel = Reconnaissance
          langkah 2 password default = Initial Access
          langkah 3 cron job = Persistence
          langkah 4 masuk database = Lateral Movement
          langkah 5 menyalin data = Exfiltration
          blue team putus sedini mungkin: hapus panel admin lama dan ganti password default
          deteksi login anomali, cron baru, dan egress tak wajar ke DB
        - |
          scan = recon, login default = initial access, cron = persistence
          masuk db = lateral movement, salin data = exfiltration
          cara terbaik ganti password default dan pensiunkan panel lama sejak awal
          pasang deteksi untuk brute force dan cron baru
      fail:
        - |
          semua ini cuma serangan biasa
          yang penting blokir data keluar di langkah 5 saja sudah cukup
        - |
          langkah 1 sampai 5 adalah hacking
          pasang antivirus
        - |
          scan itu reconnaissance
          sisanya tidak perlu dipetakan
    source: https://attack.mitre.org/

  - id: c03
    tier: C
    type: scenario
    objective: teams
    prompt: |
      Pimpinan bertanya "kenapa tidak cukup pasang satu firewall saja?" untuk sebuah web server publik. Jelaskan konsep
      defense in depth dan peran purple team dengan menyusun lapisan pertahanan web server tersebut (satu poin per
      lapis; ini peta jalan board, belum implementasi).
    checklist:
      - id: network
        label: Lapis jaringan (firewall default-deny, segmentasi, rate limit)
        required: true
        patterns: ['(firewall|jaringan|network)[^\n]*(default[- ]?deny|segmentasi|rate)', 'default[- ]?deny', 'segmentasi']
      - id: host
        label: Lapis server/host (hardening SSH, patch, fail2ban, least privilege)
        patterns: ['(ssh|host|server)[^\n]*(harden|patch|fail2ban|least priv)', 'fail2ban', 'hardening']
      - id: app
        label: Lapis aplikasi (validasi input, secure headers, auth kuat)
        required: true
        patterns: ['(aplikasi|app|web)[^\n]*(validasi|input|header|auth|owasp)', 'secure header', 'validasi input']
      - id: avail
        label: Lapis ketersediaan (anti-DDoS, rate/connection limit, caching/CDN)
        patterns: ['(ddos|availability|ketersediaan)', 'rate[- ]?limit', '(cdn|caching|connection limit)']
      - id: detect
        label: Lapis deteksi & respons (logging, alerting, IR, backup)
        required: true
        patterns: ['(deteksi|logging|monitor|alert|siem|ir|backup|respons)', '(falco|auditd|log)']
      - id: purple
        label: Loop purple team (red uji → blue verifikasi deteksi → perbaiki)
        patterns: ['purple', '(red[^\n]*blue|uji[^\n]*deteksi|verifikasi deteksi)', '(loop|siklus|berulang)[^\n]*(serang|deteksi|perbaik)']
      - id: not-single
        label: Tidak menyimpulkan satu kontrol saja sudah cukup
        forbid: true
        patterns: ['^(?![^\n]*(bukan|jangan|tidak|lebih dari))[^\n]*(satu (firewall|kontrol|lapis)[^\n]*(cukup|sudah aman)|cukup (satu|firewall)(nya)? saja)']
    pass_score: 0.5
    solution: |
      ```text
      Satu firewall tidak cukup karena penyerang hanya butuh SATU celah; firewall hanya menjaga lapis jaringan dan
      tidak menghentikan, mis., SQL injection lewat port 443 yang memang terbuka. Defense in depth = banyak lapis:

        Jaringan      : firewall default-deny, segmentasi (DB di subnet internal), rate limit di edge
        Server/host   : SSH key-only + no-root, patch rutin, fail2ban, least privilege        (modul 03)
        Image/supply  : scan image (trivy), pinning, signing cosign                            (modul 05)
        Runtime       : container non-root, drop capabilities, read-only, seccomp/AppArmor     (modul 06)
        Aplikasi      : validasi input, secure headers, auth kuat + MFA, anti-OWASP Top 10     (modul 07)
        Ketersediaan  : anti-DDoS, limit_req/limit_conn, caching/CDN, graceful degradation     (modul 08)
        Deteksi/respons: logging lengkap, alerting, falco/auditd, IR playbook, backup teruji   (modul 09-10)

      Purple team = loop: red mendemonstrasikan teknik → blue cek apakah deteksi/kontrolnya bekerja → tutup celah →
      ulangi. Jadi tiap lapis divalidasi nyata, bukan diasumsikan aman.
      ```
      Jawaban untuk pimpinan: satu kontrol = satu titik gagal. Lapisan yang saling menutupi berarti penyerang harus
      menembus semuanya, sementara blue team punya banyak kesempatan mendeteksi. Purple team memastikan tiap lapis
      benar-benar berfungsi dengan menguji dari sisi penyerang secara berkala. Ini persis struktur board DevSecOps.
    tests:
      pass:
        - |
          satu firewall tidak cukup karena penyerang cuma butuh satu celah dan firewall tidak menahan serangan di layer aplikasi
          lapis jaringan: firewall default-deny, segmentasi, rate limit
          lapis server: hardening ssh, patch, fail2ban, least privilege
          lapis aplikasi: validasi input, secure headers, auth kuat
          lapis ketersediaan: anti-ddos, limit_req, caching cdn
          lapis deteksi dan respons: logging, alerting, ir, backup
          loop purple team: red uji lalu blue verifikasi deteksi lalu perbaiki
        - |
          defense in depth berlapis bukan satu benteng
          jaringan firewall default-deny dan segmentasi
          aplikasi validasi input dan secure headers
          deteksi logging dan alerting plus backup
          purple team red menyerang blue memverifikasi deteksi berulang
      fail:
        - |
          pasang satu firewall yang bagus sudah cukup aman
          selesai
        - |
          beli WAF mahal
          tidak perlu lapis lain
        - |
          firewall di jaringan
          itu saja yang penting
    source: https://attack.mitre.org/
---

# Model Ancaman & Kerangka Kerja

Untuk bertahan dengan baik, kamu harus paham cara penyerang berpikir — tetapi dengan tujuan **menutup**, bukan
merusak. Modul ini membangun fondasinya: peta cara kerja serangan, cara memodelkan ancaman secara sistematis, peran
tiap tim, dan — paling penting — aturan etis serta lab terisolasi supaya latihanmu tidak pernah membahayakan siapa
pun.

> [!WARNING]
> **Aturan mutlak board ini:** hanya menyerang/menguji target **milikmu sendiri** di lab tertutup. Men-scan atau
> menyerang sistem orang lain tanpa izin tertulis adalah ilegal, sekalipun niatnya belajar. Semua lab memakai
> container `dj-` di jaringan `--internal` tanpa akses internet.

## 1. Cara kerja penyerang: dua peta

**Cyber Kill Chain** (Lockheed Martin) — linear, 7 tahap:

```text
Reconnaissance → Weaponization → Delivery → Exploitation → Installation → Command & Control → Actions on Objectives
```

Prinsip bertahannya: **putus rantai sedini mungkin**. Mendeteksi di Recon jauh lebih murah daripada saat data sudah
dicuri.

**MITRE ATT&CK** — matriks taktik (tujuan) × teknik (cara), tidak linear:

| Taktik (contoh) | Tujuan penyerang | Pertahanan di board ini |
|---|---|---|
| Reconnaissance | Memetakan target dari luar | Kurangi attack surface (02) |
| Initial Access | Masuk pertama kali | Hilangkan kredensial lemah (03) |
| Persistence | Tetap punya akses | Image immutable, pantau autostart (06) |
| Privilege Escalation | Naik ke root / keluar container | Least privilege, seccomp (06) |
| Lateral Movement | Pindah ke host lain | Segmentasi jaringan (Jaringan 01, 08) |
| Exfiltration / Impact | Mencuri / merusak / DDoS | Batasi egress, anti-DDoS, backup (08, 10) |

## 2. Memodelkan ancaman

- **CIA triad** — tiga sifat yang dijaga: **C**onfidentiality, **I**ntegrity, **A**vailability.
- **STRIDE** — enam kategori ancaman, tiap satu melawan satu properti:

  | STRIDE | Melawan | Contoh |
  |---|---|---|
  | **S**poofing | Authentication | Menyamar jadi user lain |
  | **T**ampering | Integrity | Mengubah data/kode |
  | **R**epudiation | Non-repudiation | Menyangkal aksi (log hilang) |
  | **I**nformation disclosure | Confidentiality | Banner versi bocor |
  | **D**enial of Service | Availability | DDoS, resource exhaustion |
  | **E**levation of privilege | Authorization | User → root, container escape |

- **DREAD** — memberi skor risiko agar yang paling berbahaya ditangani dulu: Damage, Reproducibility,
  Exploitability, Affected users, Discoverability.

## 3. Tim: red, blue, purple

- **Red** menyerang untuk menemukan celah nyata.
- **Blue** bertahan, mendeteksi, dan merespons.
- **Purple** adalah loop kolaborasi: red mendemonstrasikan teknik → blue memverifikasi deteksinya jalan → tutup celah
  → ulangi. Board ini berpasangan (serang → deteksi → tutup) karena itulah pola purple team.

Dua prinsip pembingkai: **defense in depth** (banyak lapis, satu jebol tidak fatal) dan **assume breach** (anggap
penyerang mungkin sudah di dalam, jadi investasi juga pada deteksi & pembatasan gerak).

## 4. Lab yang terisolasi (verifikasi dulu, jangan berasumsi)

```bash
docker network create --internal dj-sec-internal     # tanpa egress ke internet
# pasang tool lewat network biasa dulu, karena --internal memblokir apk add:
docker run -d --name dj-atk --network dj-sec-bridge alpine:3.20 sleep 7200
docker exec dj-atk apk add curl nmap bind-tools
docker network disconnect dj-sec-bridge dj-atk
docker network connect  dj-sec-internal dj-atk        # kini bersenjata tapi terkurung
```

Verifikasi isolasi (hasil lab) sebelum mulai:

```text
ping 8.8.8.8          → Network unreachable
curl https://example.com → exit 6 (couldn't resolve host)
nslookup example.com  → SERVFAIL
attacker → target internal → HTTP 200   (serangan tetap bekerja di dalam)
```

## 5. Setiap serangan meninggalkan jejak

Recon di lab (attacker → target):

```text
nmap -Pn 10.90.0.10  → 80/tcp open http, 8080/tcp open http-proxy, sisanya closed (reset) — 0,12 detik
curl -I 10.90.0.10   → Server: SimpleHTTP/0.6 Python/3.12.13   (information disclosure)
```

Jejak di sisi target (access log):

```text
172.18.0.2 - - [25/Nov/2026 ...] "GET /secret-path HTTP/1.1" 404
```

Perhatikan log default **tidak** merekam User-Agent, sehingga `evil-scanner/1.0` hilang. Deteksi hanya sebaik
logging-nya — ini bekal untuk modul 09 (deteksi & logging).

## 6. Peta board DevSecOps

| Modul | Fokus | Sisi red → blue |
|---|---|---|
| 01 (ini) | Model ancaman & kerangka | Kill Chain/ATT&CK → defense in depth |
| 02 | Recon & exposure | Scan/OSINT → attack surface reduction |
| 03 | Hardening server & akses | Brute-force → SSH/firewall/fail2ban |
| 04 | Secrets & kredensial | Kebocoran secret → scanning & vault |
| 05 | Image & supply chain | Image rentan → trivy, SBOM, signing |
| 06 | Hardening container & runtime | Container escape → seccomp, non-root |
| 07 | Keamanan web aplikasi | OWASP Top 10 → validasi, headers, WAF |
| 08 | Ketersediaan (anti-DDoS) | Flood L3/L7 → rate limit, caching |
| 09 | Deteksi & logging | Jejak serangan → falco, auditd, alert |
| 10 | IR & forensik dasar | Pasca-insiden → triage, playbook, backup |
| 11 | Pipeline & IaC security | Risiko CI/CD → policy-as-code |
| 12 | Capstone | Skenario bertahap → main di dua sisi |

> [!NOTE]
> Modul ini sengaja konseptual: ia memberi kerangka berpikir yang dipakai ulang di semua modul berikutnya. Mulai
> modul 02, tiap teknik serangan selalu dipasangkan dengan deteksi dan penutupnya.
