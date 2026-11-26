---
format: 1
id: rt-setup-lab-etika
course:
  slug: redteam
  title: Red Team (Lab Pentest)
  description: Sisi ofensif dari DevSecOps — metodologi red team dipelajari di lab terisolasi milik sendiri (VirtualBox host-only, Kali Linux + Metasploitable 2) untuk memahami cara penyerang bekerja dan membangun pertahanannya (purple team). Recon, enumerasi, analisis kerentanan, eksploitasi, post-exploitation, dan pelaporan. SEMUA hanya pada target milik sendiri dengan izin; teknik dipetakan ke kontrol pertahanan di board DevSecOps.
title: Setup Lab & Etika
summary: "Sebelum menyentuh satu alat pun: bangun lab yang benar dan pahami aturan mainnya. Modul ini menyiapkan lab pentest terisolasi (VirtualBox host-only, Kali sebagai attacker, Metasploitable 2 sebagai target), menegakkan etika & hukum (izin, scope, rules of engagement), dan memperkenalkan metodologi pentest (recon → enumerasi → eksploitasi → post-ex → reporting). Prinsip kunci: latihan ofensif HANYA di lab terisolasi milik sendiri, dan tujuannya memahami serangan untuk membangun pertahanan (purple team)."
order: 1
version: 1
status: verified
updated: '2026-11-25'
tested_on:
  - 'Konsep lab VirtualBox (host-only, Kali + Metasploitable 2) — langkah setup dijalankan learner di VirtualBox, bukan oleh penulis'
  - 'Host discovery diverifikasi di jaringan Docker terisolasi milik penulis - nmap -sn 10.10.10.0/24 menemukan 3 host up dalam 2.38s (mirror menemukan Metasploitable di 192.168.56.x)'
  - Semua praktik HANYA di lab terisolasi milik sendiri dengan izin; dipetakan ke pertahanan di board DevSecOps
objectives:
  - id: lab
    text: Membangun lab pentest terisolasi (VirtualBox host-only, Kali + Metasploitable)
  - id: ethics
    text: Menegakkan etika & hukum — izin, scope, rules of engagement
  - id: methodology
    text: Memahami metodologi pentest (recon → eksploitasi → post-ex → reporting)
  - id: discovery
    text: Menemukan host di jaringan lab (host discovery)
  - id: isolation
    text: Memastikan lab tidak bocor ke jaringan nyata
  - id: purple
    text: Menghubungkan tiap teknik ofensif ke pertahanannya (purple team)
sources:
  - title: Metasploitable 2 — panduan resmi (Rapid7)
    url: https://docs.rapid7.com/metasploit/metasploitable-2/
  - title: Kali Linux — dokumentasi
    url: https://www.kali.org/docs/
  - title: PTES — Penetration Testing Execution Standard
    url: http://www.pentest-standard.org/
  - title: OWASP — Penetration Testing Methodologies
    url: https://owasp.org/www-project-web-security-testing-guide/
  - title: VirtualBox — host-only networking
    url: https://www.virtualbox.org/manual/ch06.html
  - title: Lockheed Martin — Cyber Kill Chain
    url: https://www.lockheedmartin.com/en-us/capabilities/cyber/cyber-kill-chain.html
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: ethics
    prompt: Satu syarat mutlak sebelum melakukan pentest terhadap sistem apa pun adalah?
    answers: ['izin tertulis / otorisasi dari pemilik sistem (authorization)']
    patterns: ['izin[^\n]*(tertulis|pemilik|resmi)', 'otorisasi|authorization|authorized', 'persetujuan[^\n]*pemilik', 'kontrak|scope[^\n]*disetujui']
    tests:
      fail: ['tidak perlu izin apa pun', 'cukup niat baik', 'asal tidak ketahuan', 'izin lisan dari teman']
    explanation: 'Tanpa izin, "pentest" = akses ilegal (unauthorized access) — pidana di hampir semua yurisdiksi, sekeren apa pun skill-nya. Syarat mutlak: **otorisasi tertulis** dari pemilik sistem yang menetapkan apa yang boleh diuji. Di lab ini syaratnya otomatis terpenuhi karena target (Metasploitable) **milikmu sendiri** dan terisolasi. Di dunia nyata, otorisasi datang lewat kontrak engagement, program bug bounty, atau izin eksplisit. Ini garis yang membedakan pentester profesional dari kriminal — bukan tekniknya, tapi izinnya.'
    source: http://www.pentest-standard.org/

  - id: a02
    tier: A
    type: text
    objective: ethics
    prompt: Dokumen yang mendefinisikan batasan sebuah engagement — target apa, teknik apa, kapan, dan apa yang dilarang — disebut apa?
    answers: ['rules of engagement / scope']
    patterns: ['rules of engagement|roe', 'scope|ruang lingkup|lingkup', 'batasan engagement|aturan main']
    tests:
      fail: ['readme', 'changelog', 'lisensi software', 'dokumen marketing']
    explanation: 'Rules of Engagement (RoE) + scope mendefinisikan batas: IP/domain mana yang boleh (dan tidak boleh) diuji, teknik yang diizinkan (mis. boleh exploit tapi tidak boleh DoS), jendela waktu, kontak darurat, dan penanganan data sensitif. Keluar dari scope — meski "tidak sengaja menemukan" sistem lain — bisa ilegal dan melanggar kontrak. Di lab, scope-mu adalah IP Metasploitable di jaringan host-only, titik. Membiasakan diri menghormati scope sejak di lab membangun disiplin yang wajib di dunia nyata.'
    source: http://www.pentest-standard.org/

  - id: a03
    tier: A
    type: text
    objective: isolation
    prompt: Mengapa lab pentest HARUS terisolasi dari jaringan nyata/internet?
    answers: ['agar serangan & target rentan tidak menyentuh/membahayakan sistem lain atau internet, dan target rentan tidak bisa diakses pihak luar']
    patterns: ['(tidak|tak)[^\n]*(bocor|menyentuh|membahayakan|sampai)[^\n]*(jaringan nyata|internet|sistem lain|luar)', '(target|metasploitable)[^\n]*(rentan)[^\n]*(tidak|jangan)[^\n]*(diakses|terekspos)[^\n]*(luar|internet)', 'isolasi|terisolasi[^\n]*(aman|cegah)']
    tests:
      fail: ['agar lebih cepat', 'supaya bisa diakses publik', 'tidak ada alasan khusus', 'biar hemat bandwidth']
    explanation: 'Dua bahaya kalau lab bocor: (1) **target rentan** (Metasploitable penuh lubang by design) jika terekspos ke internet bisa dibobol pihak luar dan dipakai menyerang orang lain — kamu jadi sumber masalah; (2) **serangan/exploit-mu** bisa salah sasaran atau menyebar ke sistem nyata. Karena itu pakai jaringan **host-only** (atau internal) di VirtualBox: Kali & Metasploitable bisa saling bicara, tapi terputus dari internet & LAN rumah/kampus. Ini juga aturan etika praktis — konsekuensi latihan tetap di dalam kotak.'
    source: https://www.virtualbox.org/manual/ch06.html

  - id: a04
    tier: A
    type: text
    objective: lab
    prompt: Dalam lab ini, peran Kali Linux dan Metasploitable 2 masing-masing adalah?
    answers: ['kali = mesin penyerang (attacker); metasploitable = target rentan']
    patterns: ['kali[^\n]*(penyerang|attacker|menyerang|offensive)', 'metasploitable[^\n]*(target|korban|rentan|vulnerable)']
    tests:
      fail: ['keduanya target', 'kali adalah target', 'metasploitable menyerang kali', 'keduanya firewall']
    explanation: '**Kali Linux** = mesin penyerang — distro berisi ratusan tool pentest siap pakai (nmap, Metasploit, Burp, Hydra, John). **Metasploitable 2** = target latihan — VM Linux yang **sengaja dibuat rentan** (servis usang, kredensial lemah, aplikasi web bolong) oleh Rapid7 khusus untuk belajar. Keduanya di jaringan host-only yang sama sehingga Kali bisa menyerang Metasploitable dengan aman. Ini "sparring partner" yang memang untuk dipukul — bukan sistem produksi.'
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: a05
    tier: A
    type: command
    objective: discovery
    prompt: Dari Kali, temukan host yang hidup di subnet lab `192.168.56.0/24` dengan nmap (ping sweep, tanpa port scan).
    answers: ['nmap -sn 192.168.56.0/24']
    patterns: ['^nmap[ \t].*-sn[ \t]+\S+']
    tests:
      pass:
        - 'nmap -sn 192.168.56.0/24'
        - 'nmap -sn 192.168.56.0/24 -oN hosts.txt'
      fail:
        - 'nmap 192.168.56.0/24'
        - 'nmap -sS 192.168.56.0/24'
        - 'ping 192.168.56.0/24'
    explanation: '`nmap -sn <subnet>` melakukan host discovery (ping sweep) — menemukan host yang hidup **tanpa** memindai port. Langkah pertama pentest internal: kamu belum tahu IP target, jadi temukan dulu apa yang ada di jaringan. Di lab (diverifikasi penulis di jaringan Docker terisolasi `10.10.10.0/24`), `nmap -sn` menemukan 3 host dalam ~2 detik. Di VirtualBox-mu, ini akan memunculkan IP Kali dan Metasploitable (mis. `192.168.56.101`). Catat IP target itu — ia jadi fokus langkah berikutnya (modul 02).'
    source: https://www.kali.org/docs/

  - id: a06
    tier: A
    type: command
    objective: discovery
    prompt: Lihat alamat IP interface mesin Kali-mu (untuk tahu kamu ada di subnet lab yang benar).
    answers: ['ip addr']
    patterns: ['^ip[ \t]+a(ddr)?( show)?$', '^ifconfig$', '^ip[ \t]+-4[ \t]+a(ddr)?']
    tests:
      pass:
        - 'ip addr'
        - 'ip a'
        - 'ifconfig'
      fail:
        - 'ping localhost'
        - 'nmap localhost'
        - 'cat /etc/hosts'
    explanation: '`ip addr` (atau `ip a`, atau `ifconfig` lawas) menampilkan interface & IP-mu. Penting di awal: pastikan Kali punya IP di subnet host-only (mis. `192.168.56.x`) sehingga bisa menjangkau Metasploitable. Jika Kali hanya punya IP NAT/internet dan tidak di subnet lab, host discovery (a05) tak akan menemukan target. Mengetahui "aku ada di mana di jaringan" adalah dasar orientasi — baik untuk menyerang maupun (sisi blue) memahami dari mana serangan datang.'
    source: https://www.kali.org/docs/

  - id: a07
    tier: A
    type: text
    objective: methodology
    prompt: Sebutkan urutan tahap metodologi pentest secara garis besar.
    answers: ['reconnaissance → scanning/enumeration → exploitation → post-exploitation → reporting']
    patterns: ['(recon|reconnaissance|pengintaian)[^\n]*(scan|enumeras)[^\n]*(eksploit|exploit)', '(eksploit|exploit)[^\n]*(post|pasca)[^\n]*(report|laporan|pelaporan)', 'recon[^\n]*exploit[^\n]*report']
    tests:
      fail: ['install, konfigurasi, deploy', 'langsung exploit tanpa recon', 'reporting lalu recon', 'build, test, release']
    explanation: 'Metodologi pentest (PTES/garis besar): **Reconnaissance** (kumpulkan info target), **Scanning/Enumeration** (petakan port, servis, versi), **Exploitation** (manfaatkan kerentanan untuk akses), **Post-Exploitation** (eskalasi hak, pivot, kumpulkan bukti dampak), **Reporting** (dokumentasikan temuan + remediasi). Urutan ini penting: recon yang baik membuat eksploitasi terarah (bukan nembak buta). Ini cermin dari Kill Chain dan memetakan langsung ke pertahanan — tiap tahap ofensif punya kontrol blue-nya (board DevSecOps).'
    source: http://www.pentest-standard.org/

  - id: a08
    tier: A
    type: text
    objective: methodology
    prompt: Dalam pentest, apa beda "vulnerability scanning" dengan "exploitation"?
    answers: ['scanning menemukan/menduga kerentanan; exploitation benar-benar memanfaatkannya untuk mendapatkan akses']
    patterns: ['scan(ning)?[^\n]*(temukan|menemukan|menduga|identifikasi|deteksi)[^\n]*(kerentanan|vuln)', 'exploit(ation|asi)?[^\n]*(manfaat|gunakan|buktikan|akses|masuk)', 'temukan[^\n]*(vs|versus|sedangkan)[^\n]*(manfaat|akses)']
    tests:
      fail: ['keduanya sama', 'scanning lebih berbahaya dari exploit', 'exploitation hanya membaca log', 'tidak ada bedanya']
    explanation: 'Vulnerability scanning **menemukan/menduga** kelemahan (mis. "servis X versi Y, kemungkinan rentan CVE-Z") — belum membuktikan bisa ditembus. **Exploitation** benar-benar **memanfaatkan** kelemahan itu untuk mendapat akses/eksekusi. Scanner bisa false-positive; exploitation membuktikan risiko nyata. Di pentest profesional, eksploitasi dilakukan hati-hati dalam scope (beberapa exploit berisiko merusak). Di lab Metasploitable kamu bebas mencoba karena memang target latihan. Membedakan keduanya penting untuk pelaporan: "berpotensi rentan" vs "terbukti bisa dibobol" beda bobotnya.'
    source: http://www.pentest-standard.org/

  - id: a09
    tier: A
    type: text
    objective: lab
    prompt: Mengapa kredensial default Metasploitable (mis. `msfadmin:msfadmin`) TIDAK boleh dibiarkan jika VM-nya pernah terekspos?
    answers: ['kredensial default mudah ditebak siapa saja; bila terekspos, target rentan langsung bisa dikuasai pihak luar']
    patterns: ['(default|bawaan)[^\n]*(mudah|gampang)[^\n]*(tebak|diketahui|publik)', '(terekspos|bocor)[^\n]*(dikuasai|dibobol|diakses)[^\n]*(luar|pihak)', 'siapa saja (tahu|bisa)']
    tests:
      fail: ['kredensial default aman', 'tidak ada yang tahu default', 'metasploitable tidak punya login', 'default lebih kuat dari password acak']
    explanation: 'Kredensial default (`msfadmin:msfadmin`, `root:root`, dll) terdokumentasi publik — siapa pun tahu. Metasploitable memang dirancang dengan kredensial lemah untuk latihan, TAPI itu justru alasan ia **tidak boleh pernah terekspos** ke jaringan nyata (a03): siapa pun yang menemukannya langsung bisa masuk. Ini pelajaran yang transfer langsung ke dunia nyata (modul 03 DevSecOps): kredensial default/lemah adalah salah satu jalur bobol paling umum — ganti selalu. Di lab, biarkan default agar bisa berlatih; di produksi, tidak pernah.'
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: a10
    tier: A
    type: text
    objective: purple
    prompt: Apa inti pendekatan "purple team"?
    answers: ['menggabungkan red (serangan) dan blue (pertahanan) agar temuan ofensif langsung memperkuat deteksi & pertahanan']
    patterns: ['(gabung|satukan|kolaborasi)[^\n]*(red|serang)[^\n]*(blue|pertahan|defens)', '(temuan|serangan)[^\n]*(perkuat|tingkatkan|perbaiki)[^\n]*(pertahanan|deteksi|blue)', 'red dan blue bekerja sama']
    tests:
      fail: ['hanya menyerang tanpa belajar', 'red dan blue tidak boleh bicara', 'warna tim tidak penting', 'hanya bertahan tanpa paham serangan']
    explanation: 'Purple team menyatukan **red** (ofensif) dan **blue** (defensif): setiap teknik serangan yang dipelajari langsung dipakai untuk **memperkuat pertahanan & deteksi**. Inilah tujuan board ini — kamu belajar cara red team bekerja bukan untuk menyerang, tapi untuk membangun counter-nya (persis yang kamu minta). Tiap modul red-team dipetakan ke kontrol board DevSecOps: brute force (nanti) → fail2ban (blue modul 03); SQLi → prepared statement (blue modul 07). Menyerang untuk bertahan lebih baik — itu esensi DevSecOps.'
    source: https://www.lockheedmartin.com/en-us/capabilities/cyber/cyber-kill-chain.html

  - id: a11
    tier: A
    type: command
    objective: discovery
    prompt: Cek apakah host target `192.168.56.101` hidup/terjangkau dengan mengirim 4 paket ICMP.
    answers: ['ping -c 4 192.168.56.101']
    patterns: ['^ping[ \t].*-c[ \t]*4?[ \t]*\S*192\.168\.56\.101', '^ping[ \t].*-c[ \t]+[0-9]+[ \t]+\S+']
    tests:
      pass:
        - 'ping -c 4 192.168.56.101'
        - 'ping -c 4 192.168.56.101'
      fail:
        - 'ping 192.168.56.101'
        - 'nmap 192.168.56.101'
        - 'ping -t 192.168.56.101'
    explanation: 'Di Linux, `ping -c 4 <ip>` mengirim 4 paket ICMP lalu berhenti (tanpa `-c` ia ping tak terbatas — di Windows sebaliknya, `-n`). Cek cepat apakah host hidup & terjangkau dari Kali sebelum scan lebih dalam. Catatan: beberapa host memblokir ICMP (firewall), jadi "tidak membalas ping" belum tentu "mati" — makanya `nmap -Pn` (skip ping) ada (modul 02). Tapi di lab Metasploitable merespons ping, jadi ini konfirmasi cepat konektivitas lab-mu benar.'
    source: https://www.kali.org/docs/

  - id: a12
    tier: A
    type: text
    objective: isolation
    prompt: Jenis jaringan VirtualBox apa yang tepat untuk lab ini agar VM saling terhubung tapi terisolasi dari internet?
    answers: ['host-only (atau internal network)']
    patterns: ['host[- ]?only', 'internal network|jaringan internal']
    tests:
      fail: ['bridged', 'nat ke internet', 'public network', 'wifi langsung']
    explanation: 'VirtualBox **Host-Only** membuat jaringan privat antara host (laptopmu) dan VM — Kali & Metasploitable saling terhubung, host bisa mengaksesnya, tapi **tidak** tembus ke internet/LAN. Alternatif lebih ketat: **Internal Network** (VM-ke-VM saja, host pun tidak ikut). HINDARI **Bridged** (VM masuk ke LAN nyata — Metasploitable rentan jadi terekspos!) dan **NAT ke internet** untuk target rentan. Pilihan jaringan ini adalah penerapan praktis prinsip isolasi (a03) — sama idenya dengan Docker `--internal` di board DevSecOps modul 01.'
    source: https://www.virtualbox.org/manual/ch06.html

  - id: a13
    tier: A
    type: text
    objective: methodology
    prompt: Mengapa tahap "reconnaissance/enumeration" sering disebut tahap paling menentukan dalam pentest?
    answers: ['semakin banyak info yang terkumpul, semakin terarah & berhasil eksploitasinya; eksploitasi buta tanpa recon jarang berhasil']
    patterns: ['(info|informasi|data)[^\n]*(banyak|lengkap)[^\n]*(terarah|berhasil|mudah)[^\n]*(exploit|eksploit|serang)', '(tanpa|kurang) recon[^\n]*(buta|gagal|susah|jarang)', 'eksploit[^\n]*(bergantung|tergantung)[^\n]*(info|recon)']
    tests:
      fail: ['recon tidak penting', 'langsung exploit lebih baik', 'info tidak berpengaruh', 'reporting lebih menentukan dari recon']
    explanation: 'Pepatah pentest: "enumeration is key". Eksploitasi hanya sebaik informasi yang mendahuluinya — kamu tak bisa mengeksploitasi apa yang tak kamu ketahui. Recon/enumerasi yang teliti (port terbuka, versi servis, aplikasi web, user, share) mengungkap **permukaan serangan** dan mengarahkanmu ke kerentanan spesifik, bukan menembak buta. Pentester pemula sering buru-buru ke exploit dan gagal; yang berpengalaman menghabiskan lebih banyak waktu di enumerasi. (Sisi blue: inilah kenapa mengurangi eksposur — modul 02 DevSecOps — sangat ampuh: kamu membuat recon penyerang gagal.)'
    source: http://www.pentest-standard.org/

  - id: a14
    tier: A
    type: text
    objective: ethics
    prompt: Di dunia nyata, selain kontrak pentest, sebutkan satu konteks sah lain untuk menggunakan teknik ofensif ini.
    answers: ['program bug bounty / vdp, ctf, sistem milik sendiri, atau kerja defensif (blue team) di organisasi sendiri']
    patterns: ['bug bounty|vdp|vulnerability disclosure', '\bctf\b|capture the flag', '(sistem|lab)[^\n]*(sendiri|milik sendiri)', '(blue team|defensif|pertahanan)[^\n]*(organisasi|sendiri)']
    tests:
      fail: ['menyerang siapa saja', 'sistem orang tanpa izin', 'situs pemerintah acak', 'jaringan publik tetangga']
    explanation: 'Konteks sah memakai teknik ofensif: **engagement pentest** berkontrak, **program bug bounty/VDP** (perusahaan mengundang pengujian dengan aturan), **CTF** (kompetisi di target yang disediakan), **sistem/lab milik sendiri**, dan **kerja blue team** (menguji pertahanan organisasimu sendiri). Yang menyatukan semuanya: **ada izin/otorisasi yang jelas**. Di luar itu — sistem orang tanpa izin — ilegal, tak peduli motivasinya. Skill-nya bernilai tinggi justru karena ada jalur sah yang membayarnya; jangan buang karier (dan kebebasan) dengan memakainya tanpa izin.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: a15
    tier: A
    type: text
    objective: lab
    prompt: Sebelum eksperimen berisiko di VM target, fitur VirtualBox apa yang bijak dipakai agar bisa kembali ke kondisi bersih?
    answers: ['snapshot — ambil snapshot sebelum eksperimen, restore bila rusak']
    patterns: ['snapshot', '(simpan|ambil)[^\n]*(kondisi|state)[^\n]*(bersih|awal)', '(restore|kembalikan|rollback)[^\n]*(bersih|awal|snapshot)']
    tests:
      fail: ['hapus vm tiap kali', 'install ulang dari awal selalu', 'tidak ada cara kembali', 'backup manual file satu-satu']
    explanation: 'Ambil **snapshot** VM (Kali dan Metasploitable) di kondisi bersih sebelum bereksperimen. Jika exploit merusak target, konfigurasi kacau, atau kamu ingin mengulang latihan dari nol, tinggal **restore snapshot** — hitungan detik, tanpa install ulang. Ini mempercepat belajar (bebas bereksperimen tanpa takut merusak permanen) dan mencerminkan praktik nyata (lingkungan yang bisa di-reset). Analog dengan "cattle, not pets" & redeploy-from-clean di board DevSecOps modul 10 — infrastruktur yang bisa dikembalikan ke kondisi diketahui-baik.'
    source: https://www.virtualbox.org/manual/ch06.html

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: ethics
    prompt: Dalam engagement nyata, mengapa teknik yang berisiko membuat layanan down (mis. DoS) biasanya dilarang kecuali disetujui eksplisit?
    answers: ['bisa mengganggu operasi/bisnis target yang sedang berjalan; harus ada persetujuan & jendela waktu khusus']
    patterns: ['(ganggu|down|rusak|hentikan)[^\n]*(layanan|operasi|bisnis|produksi)', '(persetujuan|izin|disetujui)[^\n]*(eksplisit|khusus)', 'jendela waktu|maintenance window|di luar jam']
    tests:
      fail: ['dos selalu boleh', 'tidak ada risiko ke bisnis', 'target tidak keberatan down', 'tidak perlu persetujuan']
    explanation: 'Pentest di sistem **produksi** menguji sistem yang sedang melayani user nyata. Teknik yang berisiko menjatuhkan layanan (DoS, exploit tak stabil, brute force agresif) bisa menyebabkan **downtime & kerugian bisnis** — jadi dilarang kecuali ada persetujuan eksplisit + jendela waktu (mis. tengah malam) + rencana rollback. RoE (a02) mengatur ini. Di lab Metasploitable kamu bebas karena tak ada yang dirugikan; di dunia nyata, "bisa" tidak berarti "boleh". Pentester profesional melindungi operasi klien, bukan hanya mencari lubang.'
    source: http://www.pentest-standard.org/

  - id: b02
    tier: B
    type: text
    objective: methodology
    prompt: Apa beda "active" dan "passive" reconnaissance?
    answers: ['passive: kumpulkan info tanpa menyentuh target langsung (osint); active: berinteraksi langsung dgn target (scan/probe)']
    patterns: ['passive[^\n]*(tanpa|tidak)[^\n]*(sentuh|interaksi|kontak)[^\n]*(target|langsung)', 'active[^\n]*(interaksi|sentuh|scan|probe|langsung)[^\n]*(target)', 'osint']
    tests:
      fail: ['keduanya sama', 'passive lebih berisik dari active', 'active tidak menyentuh target', 'tidak ada bedanya']
    explanation: '**Passive recon** mengumpulkan info **tanpa berinteraksi langsung** dengan target — OSINT: WHOIS, DNS publik, Google dorking, LinkedIn, sertifikat TLS, kebocoran data. Tak terdeteksi target. **Active recon** **berinteraksi langsung** — port scan, banner grabbing, probe servis (nmap, dll) — lebih informatif tapi **terlihat** di log target (sisi blue: inilah scanning yang dideteksi di DevSecOps modul 09). Pentester mulai passive (diam-diam, luas) lalu active (detail, dalam scope). Di lab internal kita langsung active karena target sudah diketahui & milik sendiri.'
    source: http://www.pentest-standard.org/

  - id: b03
    tier: B
    type: command
    objective: discovery
    prompt: Simpan hasil host discovery ke file `hosts.txt` dalam format normal nmap (untuk dokumentasi).
    answers: ['nmap -sn 192.168.56.0/24 -oN hosts.txt']
    patterns: ['^(?=.*-sn)(?=.*-oN[ \t]+\S+)nmap[ \t]']
    tests:
      pass:
        - 'nmap -sn 192.168.56.0/24 -oN hosts.txt'
        - 'nmap -sn -oN hosts.txt 192.168.56.0/24'
      fail:
        - 'nmap -sn 192.168.56.0/24'
        - 'nmap -sn 192.168.56.0/24 > hosts.txt'
        - 'nmap -sn 192.168.56.0/24 -o hosts.txt'
    explanation: '`-oN <file>` menyimpan output nmap dalam format "normal" (teks seperti di layar). Dokumentasi adalah bagian pentest yang sering diremehkan pemula — catat SEMUA yang kamu temukan & kapan, karena laporan akhir (modul 10) dibangun dari catatan ini, dan kamu perlu bisa menelusuri ulang. nmap punya beberapa format: `-oN` (normal), `-oX` (XML, untuk tool lain), `-oG` (greppable), `-oA` (ketiganya sekaligus). Biasakan menyimpan output sejak langkah pertama.'
    source: https://www.kali.org/docs/

  - id: b04
    tier: B
    type: text
    objective: purple
    prompt: Teknik "host discovery" (nmap -sn) yang kamu lakukan — bagaimana sisi blue team mendeteksi/menghadapinya?
    answers: ['deteksi lonjakan arp/icmp dari satu sumber di log/ids, dan kurangi eksposur; firewall bisa membatasi/menyembunyikan host']
    patterns: ['(deteksi|monitor|ids)[^\n]*(arp|icmp|ping sweep|scan)', '(firewall|segmentasi)[^\n]*(batasi|blokir|sembunyi)', '(log|alert)[^\n]*(scan|sweep|probe)']
    tests:
      fail: ['tidak bisa dideteksi', 'blue team tidak peduli scanning', 'host discovery tidak terlihat', 'tidak ada pertahanan']
    explanation: 'Sisi blue (purple team mindset): host discovery menghasilkan pola **banyak ARP/ICMP dari satu sumber ke banyak IP** dalam waktu singkat — terdeteksi IDS/log jaringan sebagai sweep (board DevSecOps modul 09: deteksi scanning). Pertahanan: **segmentasi jaringan** & firewall (batasi siapa bisa menjangkau apa), kurangi host yang menjawab, dan **alert** pada pola sweep. Memahami bahwa recon-mu berisik & terdeteksi mengajarkan dua hal: sebagai red, scanning punya jejak; sebagai blue, scanning adalah peringatan dini serangan. Itulah nilai purple.'
    source: https://www.lockheedmartin.com/en-us/capabilities/cyber/cyber-kill-chain.html

  - id: b05
    tier: B
    type: text
    objective: lab
    prompt: Mengapa Kali sebaiknya tetap di-update (apt update && apt upgrade) sebelum dipakai, tapi Metasploitable TIDAK?
    answers: ['kali butuh tool & exploit terbaru agar relevan; metasploitable sengaja dibiarkan rentan/usang sebagai target latihan']
    patterns: ['kali[^\n]*(butuh|perlu|di-?update)[^\n]*(tool|exploit|terbaru|relevan)', 'metasploitable[^\n]*(sengaja|dibiarkan|tetap)[^\n]*(rentan|usang|lama)', '(target|latihan)[^\n]*(rentan|usang)']
    tests:
      fail: ['keduanya harus di-patch', 'metasploitable harus di-update', 'kali tidak perlu update', 'keduanya dibiarkan usang']
    explanation: '**Kali** (attacker) sebaiknya di-update agar punya definisi exploit, database (searchsploit), dan tool terbaru — relevan dengan ancaman terkini. **Metasploitable** (target) justru **sengaja dibiarkan usang & rentan** — mem-patch-nya akan menghancurkan tujuannya sebagai sasaran latihan. Ini membalik naluri sysadmin biasa ("patch semuanya!"), dan tepat menggarisbawahi pelajaran blue: kerentanan Metasploitable ADA karena tak di-patch — di dunia nyata, patching (board DevSecOps modul 05) menutup justru kelas kerentanan yang akan kamu eksploitasi di sini.'
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: b06
    tier: B
    type: text
    objective: ethics
    prompt: Jika saat engagement nyata kamu tak sengaja menemukan sistem/data DI LUAR scope, apa yang benar dilakukan?
    answers: ['berhenti, jangan akses lebih jauh, laporkan ke klien/pemilik; jangan eksploitasi di luar scope']
    patterns: ['(berhenti|stop|jangan)[^\n]*(akses|eksploit|lanjut)[^\n]*(lebih|luar scope)?', 'lapor(kan)?[^\n]*(klien|pemilik|poc)', '(tidak|jangan)[^\n]*(eksploit|gali)[^\n]*(luar scope|di luar)']
    tests:
      fail: ['lanjut eksploitasi karena sudah menemukan', 'ambil datanya diam-diam', 'abaikan dan jangan lapor', 'anggap itu bonus']
    explanation: 'Keluar scope bisa ilegal meski "tak sengaja". Yang benar: **berhenti, jangan akses lebih jauh, dan laporkan** temuan itu ke klien/pemilik agar mereka memutuskan (mungkin memperluas scope secara resmi). Menggali lebih dalam atau mengambil data di luar scope melanggar RoE & hukum — dan menghancurkan kepercayaan yang jadi modal profesi ini. Disiplin "temukan → laporkan, jangan eksploitasi tanpa mandat" adalah tanda pentester profesional. Biasakan sejak lab: hormati batas, meski tangan gatal.'
    source: http://www.pentest-standard.org/

  - id: b07
    tier: B
    type: command
    objective: discovery
    prompt: Tambahkan entri di `/etc/hosts` konseptnya; tapi untuk sekarang, uji resolusi/konektivitas cepat ke target dengan satu paket ping ke `192.168.56.101`.
    answers: ['ping -c 1 192.168.56.101']
    patterns: ['^ping[ \t].*-c[ \t]*1[ \t]+\S*192\.168\.56\.101', '^ping[ \t].*-c[ \t]+1[ \t]+\S+']
    tests:
      pass:
        - 'ping -c 1 192.168.56.101'
        - 'ping -c 1 192.168.56.101'
      fail:
        - 'ping 192.168.56.101'
        - 'ping -c 1'
        - 'traceroute 192.168.56.101'
    explanation: '`ping -c 1 <ip>` mengirim tepat satu paket — cek konektivitas kilat (sukses/gagal) tanpa menunggu. Berguna dalam skrip atau saat kamu hanya ingin konfirmasi cepat "host ini masih hidup?". Di lab, pastikan Kali bisa ping Metasploitable sebelum lanjut — jika gagal, periksa konfigurasi jaringan host-only (a12) & IP (a06) dulu. Troubleshooting konektivitas dasar ini (dari board jaringan) adalah prasyarat: semua tool pentest percuma kalau paketmu tak sampai ke target.'
    source: https://www.kali.org/docs/

  - id: b08
    tier: B
    type: text
    objective: methodology
    prompt: Apa tujuan tahap "post-exploitation" setelah berhasil mendapatkan akses?
    answers: ['eskalasi hak, pertahankan/perluas akses (pivot), dan kumpulkan bukti dampak — menunjukkan seberapa jauh kompromi bisa pergi']
    patterns: ['eskalasi (hak|privilege)|privilege escalation', '(pivot|bergerak lateral|perluas akses|lateral movement)', '(bukti|dampak|data)[^\n]*(kumpul|tunjuk|buktikan|seberapa jauh)']
    tests:
      fail: ['langsung berhenti setelah masuk', 'hanya screenshot lalu selesai', 'menghapus semua jejak lalu pergi', 'tidak ada tujuan']
    explanation: 'Post-exploitation menjawab "setelah masuk, lalu apa?": **eskalasi hak** (dari user biasa ke root/admin — modul nanti), **pertahankan/perluas akses** (persistence, pivot ke host lain di jaringan), dan **kumpulkan bukti dampak** (data sensitif apa yang bisa dijangkau) untuk menunjukkan **risiko nyata** ke klien. Tujuannya bukan "merusak" tapi "membuktikan seberapa jauh kompromi bisa pergi" — itulah yang meyakinkan manajemen untuk memperbaiki. Sisi blue: tahap ini dilawan dengan least privilege, segmentasi, deteksi runtime (board DevSecOps modul 06/09/10).'
    source: http://www.pentest-standard.org/

  - id: b09
    tier: B
    type: text
    objective: isolation
    prompt: Risiko apa yang muncul jika Metasploitable dijalankan dengan adapter "Bridged" alih-alih host-only?
    answers: ['metasploitable yang penuh kerentanan jadi terekspos ke lan/internet nyata dan bisa dibobol/disalahgunakan pihak lain']
    patterns: ['(terekspos|terbuka|tembus)[^\n]*(lan|internet|jaringan nyata)', '(dibobol|diakses|dikuasai|disalahgunakan)[^\n]*(pihak lain|orang lain|luar)', 'bridged[^\n]*(bahaya|risiko|terekspos)']
    tests:
      fail: ['bridged lebih aman', 'tidak ada risiko', 'metasploitable jadi lebih terlindung', 'bridged sama dengan host-only']
    explanation: 'Adapter **Bridged** menaruh VM langsung di LAN nyata (dapat IP dari router rumah/kampus) — artinya **Metasploitable yang sengaja bolong** kini terlihat oleh siapa pun di jaringan itu (dan mungkin internet). Siapa pun bisa membobolnya dalam menit dan memakainya untuk menyerang orang lain, dan kamu bertanggung jawab. Ini kesalahan lab yang berbahaya & melanggar prinsip isolasi (a03/a12). Selalu host-only/internal untuk target rentan. Analoginya di dunia nyata: jangan pernah menaruh sistem rentan/usang di segmen yang bisa dijangkau internet (board DevSecOps: segmentasi & reduksi eksposur).'
    source: https://www.virtualbox.org/manual/ch06.html

  - id: b10
    tier: B
    type: text
    objective: purple
    prompt: Bagaimana mempelajari sisi red (menyerang Metasploitable) membuatmu lebih baik sebagai blue team/DevSecOps?
    answers: ['kamu paham cara nyata penyerang menemukan & memanfaatkan celah, sehingga bisa memprioritaskan & merancang pertahanan yang tepat sasaran']
    patterns: ['(paham|tahu|mengerti)[^\n]*(cara|bagaimana)[^\n]*(penyerang|attacker)[^\n]*(temukan|manfaat|kerja)', '(pertahanan|kontrol|deteksi)[^\n]*(tepat sasaran|prioritas|efektif|relevan)', 'berpikir seperti penyerang']
    tests:
      fail: ['tidak ada hubungan', 'menyerang tidak membantu bertahan', 'blue team tak perlu paham red', 'justru membuat lebih buruk']
    explanation: 'Kamu tak bisa bertahan efektif dari sesuatu yang tak kamu pahami. Mempelajari red team mengajarkan **cara nyata** penyerang: bagaimana mereka recon, celah mana yang dikejar duluan, bagaimana satu kelemahan kecil dirangkai jadi kompromi penuh. Pengetahuan itu membuat pertahananmu **tepat sasaran & terprioritaskan** (risk-based) — kamu tahu kontrol mana yang benar-benar menghentikan serangan, bukan menebak. Setiap modul red-team ini akan ditutup dengan "jadi, bagaimana blue menutupnya" yang memetakan ke board DevSecOps. Itulah DevSecOps: Dev + Sec + Ops yang paham kedua sisi.'
    source: https://www.lockheedmartin.com/en-us/capabilities/cyber/cyber-kill-chain.html

  - id: b11
    tier: B
    type: text
    objective: lab
    prompt: Selain Metasploitable, sebutkan satu jenis target latihan legal lain untuk mengasah skill pentest.
    answers: ['vm/aplikasi rentan lain (dvwa, owasp juice shop, vulnhub, hack the box, tryhackme) — semua disediakan untuk latihan legal']
    patterns: ['dvwa|juice shop|vulnhub|hack the box|htb|tryhackme|thm|owasp.{0,3}bwa', 'vm rentan|aplikasi rentan[^\n]*(latihan|legal)', 'ctf|platform latihan']
    tests:
      fail: ['situs perusahaan acak', 'server kampus tanpa izin', 'router tetangga', 'aplikasi bank sungguhan']
    explanation: 'Banyak target latihan legal: **DVWA** & **OWASP Juice Shop** (web app rentan), **VulnHub** (VM rentan untuk diunduh), **Hack The Box** & **TryHackMe** (platform lab online dengan target yang disediakan & diizinkan), **OWASP BWA**. Semuanya **disediakan khusus untuk dipukul** — izinnya melekat. Ini cara mengasah skill secara legal & berkelanjutan di luar Metasploitable. Yang TIDAK pernah boleh: sistem orang/organisasi tanpa izin (server kampus, situs acak, perangkat tetangga) — itu kriminal, bukan latihan. Pilih sparring partner yang memang mengundangmu.'
    source: https://owasp.org/www-project-web-security-testing-guide/

  - id: b12
    tier: B
    type: text
    objective: methodology
    prompt: Mengapa dokumentasi & pencatatan (notes/screenshot) penting dilakukan SEPANJANG pentest, bukan di akhir?
    answers: ['laporan & bukti dibangun dari catatan langkah-demi-langkah; detail mudah terlupa, dan perlu bisa menelusuri ulang apa yang dilakukan']
    patterns: ['(laporan|report|bukti)[^\n]*(dari|berdasar)[^\n]*(catatan|notes|langkah)', '(lupa|terlupa|hilang)[^\n]*(detail|langkah)', '(telusur|reproduksi|ulangi|jejak)[^\n]*(apa|langkah|dilakukan)']
    tests:
      fail: ['dokumentasi hanya buang waktu', 'ingat saja semuanya di kepala', 'catat di akhir sudah cukup', 'screenshot tidak berguna']
    explanation: 'Pentest menghasilkan banyak langkah, output, dan temuan dalam waktu padat — detail **cepat terlupa**. Mencatat sepanjang jalan (perintah yang dijalankan, output, screenshot bukti, waktu) berarti: laporan akhir (modul 10) tinggal menyusun dari catatan; kamu bisa **menelusuri ulang** & membuktikan temuan; dan jika terjadi masalah (sistem down) ada jejak apa yang kamu lakukan kapan. Simpan output nmap (`-oN`, b03), salin sesi terminal, screenshot shell yang didapat. Dokumentasi yang baik membedakan laporan pentest profesional dari "aku berhasil masuk, percaya deh".'
    source: http://www.pentest-standard.org/

  - id: b13
    tier: B
    type: text
    objective: ethics
    prompt: Apa yang dimaksud "do no harm" dalam pentest profesional, dan satu contoh penerapannya?
    answers: ['minimalkan dampak negatif ke sistem/data klien: hindari merusak data, jangan biarkan backdoor, bersihkan artefak, utamakan keamanan operasi']
    patterns: ['(minimal|hindari|cegah)[^\n]*(rusak|kerusakan|dampak|ganggu)[^\n]*(sistem|data|klien)', '(jangan|hapus|bersih)[^\n]*(backdoor|artefak|jejak uji|akun uji)', '(utamakan|jaga)[^\n]*(keamanan|operasi|data) klien']
    tests:
      fail: ['rusak sebanyak mungkin', 'tinggalkan backdoor untuk nanti', 'curi data klien', 'biarkan sistem berantakan']
    explanation: '"Do no harm" = tujuanmu membuktikan risiko, bukan menimbulkan kerusakan. Penerapannya: jangan menghapus/mengubah data produksi, jangan menjalankan exploit tak stabil di sistem kritis tanpa izin, **jangan tinggalkan backdoor/akun uji** (bersihkan setelah selesai), lindungi data sensitif yang kamu temui (jangan exfiltrasi lebih dari perlu untuk bukti), dan utamakan keamanan operasi klien. Di lab kamu bebas merusak Metasploitable (tinggal restore snapshot), tapi membangun kebiasaan "bersih & minim dampak" sejak sekarang penting untuk engagement nyata. Pentester dipercaya masuk justru karena disiplin ini.'
    source: http://www.pentest-standard.org/

  - id: b14
    tier: B
    type: text
    objective: purple
    prompt: Cyber Kill Chain dan MITRE ATT&CK berguna untuk red maupun blue. Jelaskan singkat bagaimana.
    answers: ['red memakainya untuk merencanakan tahap serangan; blue memakainya untuk memetakan deteksi & pertahanan ke tiap taktik/teknik']
    patterns: ['red[^\n]*(rencana|tahap|serangan|langkah)', 'blue[^\n]*(deteksi|pertahanan|petakan|kontrol)', '(bahasa|kerangka) (bersama|yang sama)']
    tests:
      fail: ['hanya untuk red', 'hanya untuk blue', 'tidak berguna untuk keduanya', 'kill chain dan att&ck tidak berhubungan']
    explanation: 'Kill Chain (tahap: recon→weaponize→deliver→exploit→install→C2→actions) dan **MITRE ATT&CK** (katalog taktik & teknik nyata) adalah **bahasa bersama** red & blue. **Red** memakainya untuk merencanakan & menstrukturkan serangan (dan melaporkan teknik yang dipakai dengan ID ATT&CK). **Blue** memakainya untuk memetakan **cakupan deteksi & pertahanan**: "teknik T1110 (brute force) — apakah kita punya deteksi & mitigasinya?". Di board ini, tiap teknik red akan diberi referensi ATT&CK dan dipetakan ke kontrol blue — persis cara purple team memakai kerangka ini untuk menutup celah secara sistematis.'
    source: https://attack.mitre.org/

  - id: b15
    tier: B
    type: command
    objective: discovery
    prompt: Lakukan host discovery yang MELEWATI firewall yang memblokir ping, dengan menganggap semua host hidup — tapi untuk langkah ini cukup scan daftar host dari file `targets.txt` (input list nmap).
    answers: ['nmap -sn -iL targets.txt']
    patterns: ['^(?=.*-iL[ \t]+\S+)nmap[ \t]']
    tests:
      pass:
        - 'nmap -sn -iL targets.txt'
        - 'nmap -iL targets.txt -sn'
      fail:
        - 'nmap -sn 192.168.56.0/24'
        - 'cat targets.txt'
        - 'nmap targets.txt'
    explanation: '`-iL <file>` menyuruh nmap membaca daftar target dari file (satu per baris) — berguna saat kamu sudah punya daftar IP (mis. dari host discovery sebelumnya) dan ingin fokus. Ini bagian dari alur rapi: temukan host (`-sn`), simpan (`-oN`/olah jadi list), lalu scan mendalam hanya yang relevan (`-iL`). Catatan: host yang memblokir ICMP tak muncul di ping sweep biasa — untuk itu nanti pakai `-Pn` (skip host discovery, anggap hidup) di modul 02. Mengelola daftar target dengan rapi = pentest terorganisir, bukan acak.'
    source: https://www.kali.org/docs/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: lab
    prompt: |
      Kamu menyiapkan lab pentest dari nol untuk belajar: Kali sebagai attacker, Metasploitable 2 sebagai target.
      Rancang setup lab yang benar & aman (satu poin per baris).
    checklist:
      - id: hostonly
        label: Jaringan host-only/internal, BUKAN bridged ke LAN nyata
        required: true
        patterns: ['host[- ]?only|internal network', '(bukan|jangan|hindari)[^\n]*bridged']
      - id: roles
        label: Kali = attacker (ber-tool), Metasploitable = target rentan
        required: true
        patterns: ['kali[^\n]*(attacker|penyerang)', 'metasploitable[^\n]*(target|rentan|korban)']
      - id: snapshot
        label: Ambil snapshot kondisi bersih sebelum eksperimen
        patterns: ['snapshot', '(simpan|restore)[^\n]*(bersih|awal|kondisi)']
      - id: kali-update
        label: Update Kali (tool terbaru); JANGAN patch Metasploitable (biar rentan)
        patterns: ['update[^\n]*kali', '(jangan|tidak)[^\n]*(patch|update)[^\n]*metasploitable']
      - id: verify
        label: Verifikasi konektivitas (ping/host discovery) & IP di subnet lab
        patterns: ['(ping|nmap -sn|host discovery)[^\n]*(konektivitas|target|cek)', 'ip[^\n]*(subnet|lab|benar)', 'verifikasi[^\n]*konektivitas']
      - id: no-internet
        label: Tidak mengekspos Metasploitable ke internet/LAN nyata
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|hindari))[^\n]*(ekspos|buka|sambung)[^\n]*(metasploitable|target)[^\n]*(internet|lan|publik|nyata)']
    pass_score: 0.5
    solution: |
      ```text
      1. Jaringan VirtualBox HOST-ONLY (atau Internal) — Kali & Metasploitable saling terhubung, terputus dari
         internet/LAN. JANGAN Bridged (mengekspos target rentan ke jaringan nyata).
      2. Peran jelas: Kali = attacker (nmap, Metasploit, dll), Metasploitable 2 = target yang sengaja rentan.
      3. Ambil SNAPSHOT kedua VM di kondisi bersih → bisa restore cepat setelah eksperimen merusak.
      4. Update Kali (searchsploit/tool terbaru); JANGAN patch Metasploitable (mem-patch merusak tujuannya).
      5. Verifikasi: ip addr (Kali di subnet lab), ping -c / nmap -sn untuk konfirmasi Metasploitable terjangkau.
      6. Pastikan target TIDAK terekspos ke internet/LAN nyata — konsekuensi latihan tetap di dalam kotak.
      ```
      Lab yang benar = terisolasi, peran jelas, bisa di-reset, dan terverifikasi terhubung. Mengekspos target rentan
      ke jaringan nyata adalah kesalahan berbahaya (orang lain bisa membobolnya & menyalahgunakannya atas namamu).
    tests:
      pass:
        - |
          pakai jaringan host-only atau internal bukan bridged ke lan nyata
          kali sebagai attacker bertool dan metasploitable sebagai target rentan
          ambil snapshot kondisi bersih sebelum eksperimen agar bisa restore
          update kali untuk tool terbaru tapi jangan patch metasploitable biar tetap rentan
          verifikasi konektivitas dengan ping dan nmap -sn serta cek ip di subnet lab
          jangan ekspos metasploitable ke internet atau lan nyata
        - |
          host only network, kali attacker metasploitable target
          snapshot bersih, update kali jangan patch target, verifikasi ping dan ip subnet
          target tidak terekspos ke internet
      fail:
        - |
          pakai bridged biar metasploitable dapat internet
          ekspos target ke lan supaya bisa diakses dari mana saja
        - |
          tidak perlu snapshot atau verifikasi
          patch metasploitable biar aman
        - |
          sambungkan metasploitable langsung ke internet
          kali dan target tidak perlu di subnet yang sama
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: c02
    tier: C
    type: scenario
    objective: ethics
    prompt: |
      Seorang teman memintamu "mengetes keamanan" akun/website orang lain memakai skill yang kamu pelajari, karena
      "kan buat belajar". Jelaskan sikap yang benar dan alasannya (satu poin per baris).
    checklist:
      - id: refuse
        label: Tolak — tanpa izin pemilik, itu akses ilegal (bukan "belajar")
        required: true
        patterns: ['(tolak|menolak|jangan)[^\n]*(lakukan|setuju)?', '(tanpa izin|tanpa otorisasi)[^\n]*(ilegal|kriminal|melanggar)', 'bukan (belajar|latihan)[^\n]*(ilegal|izin)']
      - id: law
        label: Jelaskan konsekuensi hukum (pidana akses tak sah) & etika
        required: true
        patterns: ['(hukum|pidana|ilegal|kriminal|uu ite|cfaa)', '(konsekuensi|sanksi|penjara|denda)']
      - id: legal-alt
        label: Arahkan ke target legal (lab sendiri, CTF, bug bounty, HTB/THM)
        patterns: ['(lab sendiri|metasploitable|ctf|bug bounty|hack the box|htb|tryhackme|thm|vulnhub)', 'target (legal|sah|diizinkan)']
      - id: authorized-way
        label: Jika pemilik benar ingin diuji → minta izin tertulis/scope dulu
        patterns: ['izin tertulis|otorisasi|scope|persetujuan[^\n]*pemilik', '(kalau|jika) (pemilik|dia)[^\n]*(mau|ingin)[^\n]*(uji|izin)']
      - id: no-justdoit
        label: Tidak melakukannya diam-diam "karena cuma belajar / tak akan ketahuan"
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|tolak))[^\n]*(lakukan|kerjakan|bantu|tes)[^\n]*(diam-diam|toh|cuma belajar|tak ketahuan|tidak ketahuan)']
    pass_score: 0.5
    solution: |
      ```text
      1. TOLAK. Menguji akun/website orang lain tanpa izin pemiliknya = akses ilegal, apa pun motifnya. "Buat belajar"
         bukan pembenaran hukum maupun etika.
      2. Jelaskan konsekuensinya: akses tak sah adalah tindak pidana (di Indonesia mis. UU ITE; di AS CFAA) —
         bisa berujung denda/penjara & menghancurkan karier keamanan yang justru butuh reputasi bersih.
      3. Arahkan ke target LEGAL: lab sendiri (Metasploitable/DVWA), CTF, bug bounty resmi, HTB/TryHackMe —
         semua mengundangmu menguji, izinnya melekat.
      4. Jika pemilik SUNGGUH ingin sistemnya diuji → lakukan dengan benar: minta izin tertulis + scope/RoE lebih dulu.
      ```
      Garis yang membedakan pentester dari kriminal bukan skill, tapi IZIN. Skill ini bernilai tinggi justru karena
      ada jalur sah; memakainya diam-diam "karena tak akan ketahuan" membuang karier & kebebasan demi hal sepele.
    tests:
      pass:
        - |
          tolak karena tanpa izin pemilik itu akses ilegal bukan belajar
          jelaskan konsekuensi hukum pidana akses tak sah seperti uu ite dan etikanya
          arahkan ke target legal seperti lab sendiri ctf bug bounty atau hack the box
          kalau pemilik memang ingin diuji minta izin tertulis dan scope dulu
          jangan lakukan diam-diam dengan alasan cuma belajar atau tak akan ketahuan
        - |
          menolak, tanpa otorisasi itu kriminal, jelaskan sanksi hukum
          arahkan ke metasploitable ctf atau bug bounty, kalau serius minta izin tertulis dan scope
      fail:
        - |
          bantu saja toh cuma buat belajar dan tidak akan ketahuan
          skill harus dipakai
        - |
          lakukan diam-diam, kalau ketahuan baru minta maaf
          tidak perlu izin
        - |
          tes website-nya dulu baru pikir izin belakangan
          namanya juga latihan
    source: http://www.pentest-standard.org/

  - id: c03
    tier: C
    type: scenario
    objective: purple
    prompt: |
      Jelaskan bagaimana kamu akan menjalani board red-team ini dengan pola pikir "purple team" — yakni agar setiap
      yang dipelajari memperkuat kemampuan bertahan (DevSecOps), bukan sekadar belajar menyerang (satu poin per baris).
    checklist:
      - id: mapdefense
        label: Petakan tiap teknik serangan ke kontrol pertahanan/deteksi (board DevSecOps)
        required: true
        patterns: ['(petakan|dipetakan|hubungkan|kaitkan)[^\n]*(pertahanan|kontrol|deteksi|blue)', 'map[^\n]*defense|devsecops']
      - id: detection
        label: Perhatikan jejak/deteksi serangan (apa yang dilihat blue di log/IDS)
        patterns: ['(jejak|log|ids|deteksi|terdeteksi)[^\n]*(serangan|scan|exploit)', '(apa yang dilihat|sisi) blue']
      - id: rootcause
        label: Tanya "kenapa ini bisa dieksploitasi" → akar & cara menutupnya
        required: true
        patterns: ['(kenapa|mengapa)[^\n]*(bisa|rentan|dieksploitasi)', '(akar|root cause)[^\n]*(tutup|perbaiki|patch)', '(cara|bagaimana) menutup']
      - id: ethics
        label: Tetap etis & lab-only sepanjang belajar
        patterns: ['(etis|etika|izin|lab-?only|lab sendiri|terisolasi)', 'hanya[^\n]*(lab|target sendiri)']
      - id: document
        label: Dokumentasikan temuan + remediasi (bukan hanya "cara nembus")
        patterns: ['dokumentasi|catat|laporan', '(remediasi|perbaikan|rekomendasi)[^\n]*(bukan hanya|selain)?']
      - id: no-attackonly
        label: Tidak memperlakukannya sebagai "belajar nyerang saja" tanpa sisi bertahan
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain))[^\n]*(cuma|hanya|sekadar)[^\n]*(nyerang|menyerang|exploit|nembus)[^\n]*(saja|tanpa)?']
    pass_score: 0.5
    solution: |
      ```text
      1. Untuk tiap teknik yang dipelajari, langsung petakan ke kontrol blue: brute force → fail2ban/MFA (DevSecOps 03),
         SQLi → prepared statement (07), container escape → cap-drop/no-new-priv (06).
      2. Perhatikan JEJAK serangan: apa yang muncul di log/IDS saat aku scan/exploit? → itulah sinyal yang blue pantau (09).
      3. Setiap kali berhasil, tanya "KENAPA ini bisa?" → temukan akar (versi usang, kredensial lemah, input tak divalidasi)
         dan cara menutupnya — itu pelajaran defensif sebenarnya.
      4. Tetap etis & lab-only sepanjang jalan (target sendiri, terisolasi, izin melekat).
      5. Dokumentasikan temuan DAN remediasinya (seperti laporan pentest), bukan hanya "langkah nembus".
      ```
      Purple team = setiap serangan diakhiri pertanyaan "jadi bagaimana menutupnya & mendeteksinya?". Belajar nyerang
      tanpa sisi bertahan hanya setengah ilmu — dan sisi bertahanlah yang jadi profesi DevSecOps-mu.
    tests:
      pass:
        - |
          petakan tiap teknik serangan ke kontrol pertahanan dan deteksi di board devsecops
          perhatikan jejak dan deteksi serangan apa yang dilihat blue di log dan ids
          selalu tanya kenapa ini bisa dieksploitasi lalu cari akar dan cara menutupnya
          tetap etis dan lab only sepanjang belajar di target sendiri yang terisolasi
          dokumentasikan temuan beserta remediasinya bukan hanya cara nembus
          jangan memperlakukan ini sebagai belajar nyerang saja tanpa sisi bertahan
        - |
          tiap serangan dipetakan ke kontrol blue, perhatikan jejak di log ids
          tanya kenapa bisa dieksploitasi cari akar dan patch, tetap etis lab only, dokumentasi plus remediasi
      fail:
        - |
          fokus belajar nyerang saja yang penting bisa nembus
          tidak usah mikir pertahanan
        - |
          kumpulkan sebanyak mungkin exploit tanpa paham akarnya
          dokumentasi dan remediasi tidak perlu
        - |
          pakai di sistem orang biar lebih nyata
          pertahanan urusan tim lain
    source: https://www.lockheedmartin.com/en-us/capabilities/cyber/cyber-kill-chain.html
---

# Setup Lab & Etika

Sebelum menyentuh satu alat pun, dua hal wajib beres: **lab yang benar** dan **aturan main**. Board ini mempelajari
sisi ofensif (red team) dari DevSecOps — tapi dengan satu tujuan: memahami cara penyerang bekerja agar bisa
membangun pertahanannya (**purple team**). Semua latihan HANYA di lab terisolasi milik sendiri.

> [!WARNING]
> Teknik di board ini **hanya** sah pada target milik sendiri (lab ini) atau yang secara eksplisit mengizinkan
> (CTF, bug bounty, engagement berkontrak). Menggunakannya pada sistem orang lain tanpa izin adalah **akses ilegal**
> — pidana, apa pun motifnya. Garis yang membedakan pentester dari kriminal adalah **izin**, bukan skill.

## 1. Arsitektur lab

```text
VirtualBox — jaringan HOST-ONLY (mis. 192.168.56.0/24), TERPUTUS dari internet/LAN
┌───────────────────────┐        ┌────────────────────────────┐
│  Kali Linux            │        │  Metasploitable 2          │
│  (attacker, ber-tool)  │◀──────▶│  (target, sengaja rentan)  │
│  nmap, msf, hydra, ... │        │  servis usang, kred lemah  │
└───────────────────────┘        └────────────────────────────┘
```

- **Host-only / Internal**, **bukan Bridged** — target rentan tak boleh terekspos ke jaringan nyata.
- **Kali** di-update (tool/exploit terbaru). **Metasploitable** JANGAN di-patch (itu memang sasaran latihan).
- **Snapshot** kedua VM di kondisi bersih → bebas bereksperimen, restore dalam detik.

## 2. Etika & hukum (tidak bisa ditawar)

- **Izin/otorisasi** adalah syarat mutlak (di lab: terpenuhi karena target milikmu & terisolasi).
- **Scope & Rules of Engagement** mendefinisikan apa yang boleh/dilarang; hormati batasnya.
- **Do no harm**: tujuanmu membuktikan risiko, bukan merusak; bersihkan artefak, lindungi data.
- Di luar scope → **berhenti & laporkan**, jangan gali/ambil.
- Konteks sah di dunia nyata: pentest berkontrak, bug bounty, CTF, sistem sendiri, blue team.

## 3. Metodologi pentest (peta board ini)

```text
Recon → Scanning/Enumeration → Vuln Analysis → Exploitation → Post-Exploitation → Reporting
 (02)        (02/03)               (04)            (05/06/07)        (08)            (10)
```

"Enumeration is key" — eksploitasi hanya sebaik recon yang mendahuluinya. Modul 09 menutup board dengan pemetaan
ofensif→defensif, modul 10 dengan pelaporan profesional.

## 4. Host discovery (hasil lab)

```text
ip addr                       → pastikan Kali ada di subnet lab (192.168.56.x)
nmap -sn 192.168.56.0/24      → ping sweep: temukan host hidup (Kali + Metasploitable)
   (diverifikasi penulis di net Docker terisolasi 10.10.10.0/24 → 3 host up dalam 2.38s)
ping -c 4 <ip-target>         → konfirmasi target terjangkau
```

Catat IP Metasploitable yang ditemukan — itu fokus modul 02 (scanning & enumerasi mendalam).

## 5. Pola pikir purple team

Setiap teknik ofensif di board ini akan ditutup dengan **"jadi, bagaimana blue menutup & mendeteksinya?"** dan
dipetakan ke board DevSecOps. Kamu belajar menyerang **untuk** bertahan lebih baik.

| Nanti kamu pelajari (red) | Pertahanannya (blue — DevSecOps) |
|---|---|
| Scanning/enumerasi (02/03) | reduksi eksposur, deteksi scan (02, 09) |
| Brute force (nanti) | fail2ban, MFA, rate limit (03, 08) |
| Eksploit servis usang (05) | patching, scan image/CVE (05) |
| Eksploit web/SQLi (07) | prepared stmt, WAF, headers (07) |
| Privilege escalation (08) | least privilege, hardening (03, 06) |

> [!NOTE]
> Lanjut ke modul 02 (Reconnaissance & Scanning) — setelah lab siap & aturan dipahami, memetakan target secara
> mendalam dengan nmap: port terbuka, servis, versi — bahan baku semua langkah berikutnya.
