---
format: 1
id: rt-vulnerability-analysis
course:
  slug: redteam
  title: Red Team (Lab Pentest)
  description: Sisi ofensif dari DevSecOps — metodologi red team dipelajari di lab terisolasi milik sendiri (VirtualBox host-only, Kali Linux + Metasploitable 2) untuk memahami cara penyerang bekerja dan membangun pertahanannya (purple team). Recon, enumerasi, analisis kerentanan, eksploitasi, post-exploitation, dan pelaporan. SEMUA hanya pada target milik sendiri dengan izin; teknik dipetakan ke kontrol pertahanan di board DevSecOps.
title: Analisis Kerentanan
summary: "Jembatan antara enumerasi dan eksploitasi: ubah 'servis X versi Y' menjadi 'exploit Z yang cocok'. Modul ini mencocokkan versi/konfigurasi ke kerentanan diketahui — searchsploit (Exploit-DB lokal), basis data CVE & skor CVSS, nmap --script vuln, dan pencarian di Metasploit. Lalu memprioritaskan temuan (dampak × kemudahan) dan memverifikasinya (hindari false positive, cocokkan versi & platform). Perintah dijalankan learner di Kali; konsep dipetakan ke sisi blue (patch, SCA, manajemen kerentanan)."
order: 4
version: 1
status: draft
updated: '2026-11-25'
tested_on:
  - 'Modul ini DIDOKUMENTASIKAN dari referensi standar (Exploit-DB/searchsploit, NVD/CVE, Metasploit) — perintah dijalankan learner di Kali + Metasploitable, bukan dieksekusi penulis (status draft)'
  - 'nmap --script vuln sudah dicontohkan & formatnya konsisten dengan modul 02 (nmap diverifikasi di lab Docker penulis)'
  - Semua pada target milik sendiri di lab terisolasi; konsep dipetakan ke manajemen kerentanan di board DevSecOps
objectives:
  - id: searchsploit
    text: Mencari exploit yang cocok dengan searchsploit (Exploit-DB)
  - id: cve
    text: Memahami CVE & skor CVSS untuk menilai kerentanan
  - id: nmapvuln
    text: Mendeteksi kerentanan otomatis dengan nmap --script vuln
  - id: msfsearch
    text: Mencari modul & auxiliary scanner di Metasploit
  - id: verify
    text: Memverifikasi temuan (false positive, cocokkan versi/platform)
  - id: prioritize
    text: Memprioritaskan kerentanan (dampak × kemudahan)
sources:
  - title: Exploit-DB & searchsploit
    url: https://www.exploit-db.com/searchsploit
  - title: NVD — National Vulnerability Database (CVE)
    url: https://nvd.nist.gov/
  - title: FIRST — CVSS (Common Vulnerability Scoring System)
    url: https://www.first.org/cvss/
  - title: Metasploit — search & modules
    url: https://docs.rapid7.com/metasploit/
  - title: Metasploitable 2 — kerentanan diketahui
    url: https://docs.rapid7.com/metasploit/metasploitable-2/
  - title: MITRE ATT&CK — Exploit Public-Facing Application (T1190)
    url: https://attack.mitre.org/techniques/T1190/
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: command
    objective: searchsploit
    prompt: Cari exploit yang tersedia untuk "vsftpd 2.3.4" dengan searchsploit.
    answers: ['searchsploit vsftpd 2.3.4']
    patterns: ['^searchsploit[ \t]+\S+', '^searchsploit[ \t].*vsftpd']
    tests:
      pass:
        - 'searchsploit vsftpd 2.3.4'
        - 'searchsploit vsftpd'
      fail:
        - 'search vsftpd'
        - 'nmap vsftpd'
        - 'searchsploit'
    explanation: '`searchsploit <kata kunci>` mencari di salinan lokal **Exploit-DB** (database exploit publik) yang terpasang di Kali — offline, cepat. `searchsploit vsftpd 2.3.4` akan menemukan exploit backdoor vsftpd 2.3.4 yang terkenal. Ini langkah inti analisis kerentanan: ambil versi dari `nmap -sV` (modul 02) → cari exploit yang cocok. Hasilnya menampilkan judul exploit + path file. Tips: cari dengan nama servis dulu (luas), lalu persempit dengan versi. `searchsploit --update` menyegarkan database. Dari sini kamu tahu apakah ada jalan masuk siap pakai.'
    source: https://www.exploit-db.com/searchsploit

  - id: a02
    tier: A
    type: text
    objective: cve
    prompt: Apa itu CVE?
    answers: ['identifier unik untuk sebuah kerentanan keamanan yang diketahui publik (mis. cve-2021-44228)']
    patterns: ['(id|identifier|identitas|penanda)[^\n]*(unik|standar)[^\n]*(kerentanan|vulnerability)', 'kerentanan[^\n]*(diketahui|publik|terdaftar)[^\n]*(id|nomor|penanda)?', 'cve-\d{4}-\d+']
    tests:
      fail: ['jenis malware', 'tool scanning', 'protokol jaringan', 'sertifikat tls']
    explanation: 'CVE (Common Vulnerabilities and Exposures) = **identifier unik** untuk sebuah kerentanan yang diketahui publik, formatnya `CVE-<tahun>-<nomor>` (mis. `CVE-2021-44228` = Log4Shell). Ia bahasa bersama: alih-alih mendeskripsikan bug panjang-lebar, semua orang merujuk ID yang sama. Dari versi servis (modul 02) kamu bisa mencari CVE yang berlaku di **NVD** (nvd.nist.gov) atau database vendor. Tiap CVE punya deskripsi, versi terdampak, dan biasanya skor CVSS (a03). Sisi blue (board DevSecOps modul 05): scanner image/dependency (trivy) melaporkan CVE — kamu menambal berdasarkan ID yang sama yang dicari penyerang.'
    source: https://nvd.nist.gov/

  - id: a03
    tier: A
    type: text
    objective: cve
    prompt: Skor CVSS (0.0–10.0) mengukur apa pada sebuah kerentanan?
    answers: ['tingkat keparahan/severity — seberapa mudah dieksploitasi & seberapa besar dampaknya']
    patterns: ['(keparahan|severity|tingkat (bahaya|kritis))', '(mudah|kemudahan)[^\n]*(eksploit|exploit)[^\n]*(dampak|impact)', '(seberapa besar|tingkat|skala)[^\n]*dampak']
    tests:
      fail: ['harga exploit', 'jumlah sistem terdampak persis', 'usia kerentanan', 'popularitas cve']
    explanation: 'CVSS (Common Vulnerability Scoring System) memberi skor **0.0–10.0** untuk **keparahan** kerentanan, dari metrik seperti vektor serangan (jaringan/lokal), kompleksitas, hak yang dibutuhkan, dan dampak ke Confidentiality/Integrity/Availability. Rentang kasar: 0.1–3.9 Low, 4.0–6.9 Medium, 7.0–8.9 High, 9.0–10.0 Critical. Berguna untuk **prioritas** (a15): kerentanan 9.8 yang remote & tanpa auth jauh lebih mendesak dari 4.0 yang butuh akses lokal. Sisi blue (board DevSecOps modul 05): trivy/scanner melaporkan CVSS → tambal CRITICAL/HIGH dulu. Catatan: CVSS adalah severity teknis, bukan risiko aktual (tergantung konteks aset-mu).'
    source: https://www.first.org/cvss/

  - id: a04
    tier: A
    type: command
    objective: nmapvuln
    prompt: Deteksi kerentanan diketahui pada `192.168.56.101` secara otomatis dengan skrip NSE kategori vuln.
    answers: ['nmap --script vuln 192.168.56.101']
    patterns: ['^nmap[ \t].*--script[ =]vuln([ \t]|$).*192\.168|^nmap[ \t].*--script[ =]vuln']
    tests:
      pass:
        - 'nmap --script vuln 192.168.56.101'
        - 'nmap -sV --script vuln 192.168.56.101'
      fail:
        - 'nmap -sC 192.168.56.101'
        - 'nmap --script default 192.168.56.101'
        - 'searchsploit 192.168.56.101'
    explanation: '`nmap --script vuln` menjalankan skrip NSE yang mengecek kerentanan diketahui (http-vuln-*, smb-vuln-*, cek CVE tertentu) — deteksi semi-otomatis yang menjembatani enumerasi & analisis. Format output konsisten dengan nmap yang diverifikasi penulis (modul 02). Berguna sebagai pass cepat, TAPI: bisa **false-positive/negative** (verifikasi manual, a13), kadang intrusif (men-crash servis rapuh — hati-hati di produksi), dan tak selengkap scanner khusus. Gabungkan dengan `-sV` agar skrip tahu versi. Ini salah satu dari banyak sumber temuan; searchsploit & CVE lookup melengkapinya.'
    source: https://nmap.org/book/nse.html

  - id: a05
    tier: A
    type: command
    objective: searchsploit
    prompt: Salin (copy) file exploit dari Exploit-DB ber-ID `12345` ke direktori kerja dengan searchsploit.
    answers: ['searchsploit -m 12345']
    patterns: ['^searchsploit[ \t].*-m[ \t]+\S+']
    tests:
      pass:
        - 'searchsploit -m 12345'
        - 'searchsploit -m exploits/unix/remote/12345.rb'
      fail:
        - 'searchsploit 12345'
        - 'cp 12345'
        - 'searchsploit -x 12345'
    explanation: '`searchsploit -m <id>` ("mirror") menyalin file exploit ke direktori kerjamu agar bisa dibaca & dijalankan (banyak exploit butuh disesuaikan: IP target, port, offset). `searchsploit -x <id>` ("examine") menampilkan isinya tanpa menyalin — **selalu baca exploit sebelum menjalankan**, apalagi yang dari internet (bisa berbahaya/berisi jebakan). Alur: cari (`searchsploit vsftpd`) → periksa (`-x`) → salin (`-m`) → baca & sesuaikan → jalankan di lab. Untuk exploit yang sudah ada di Metasploit, sering lebih mudah pakai modul msf langsung (modul 05).'
    source: https://www.exploit-db.com/searchsploit

  - id: a06
    tier: A
    type: text
    objective: verify
    prompt: Mengapa temuan scanner kerentanan (nmap vuln / scanner otomatis) harus diverifikasi, tidak langsung dipercaya?
    answers: ['scanner bisa false-positive (lapor rentan padahal tidak) atau false-negative; perlu verifikasi manual/exploit untuk memastikan risiko nyata']
    patterns: ['false[- ]?positive', '(lapor|deteksi)[^\n]*(rentan|vuln)[^\n]*(padahal|belum tentu|tidak)', '(verifikasi|buktikan|konfirmasi)[^\n]*(manual|exploit|nyata)']
    tests:
      fail: ['scanner selalu benar', 'tidak perlu verifikasi', 'hasil scanner pasti akurat', 'langsung percaya semua temuan']
    explanation: 'Scanner otomatis mengandalkan heuristik & deteksi versi yang bisa keliru: **false-positive** (melaporkan rentan padahal sudah di-patch/tak berlaku — mis. versi di banner menyesatkan, atau fitur rentan tak aktif) dan **false-negative** (melewatkan yang ada). Karena itu temuan harus **diverifikasi** — cek manual, cocokkan versi & platform persis (a13), atau buktikan dengan exploit (hati-hati). Dalam laporan, bedakan "berpotensi rentan" (dari scanner) vs "terbukti dieksploitasi" (modul 01 a08). Blindly trusting scanner = laporan yang salah & waktu terbuang mengejar hantu. Scanner memberi petunjuk, kamu memverifikasi.'
    source: https://nvd.nist.gov/

  - id: a07
    tier: A
    type: command
    objective: msfsearch
    prompt: Di dalam msfconsole, cari modul Metasploit yang terkait "vsftpd".
    answers: ['search vsftpd']
    patterns: ['^search[ \t]+\S*vsftpd', '^search[ \t]+\S+']
    tests:
      pass:
        - 'search vsftpd'
        - 'search type:exploit vsftpd'
        - 'search name:vsftpd'
    explanation: '`search <kata kunci>` di dalam `msfconsole` mencari modul Metasploit yang cocok — exploit, auxiliary (scanner/enum), payload. `search vsftpd` menemukan `exploit/unix/ftp/vsftpd_234_backdoor`. Bisa difilter: `search type:exploit`, `search cve:2021-44228`, `search platform:linux`, `search name:samba`. Metasploit sering menjadi jalur eksploitasi termudah karena exploit sudah dibungkus jadi modul siap pakai (modul 05). Alur: dari versi (`-sV`) → `search` di msf → `use` modul → set opsi → jalankan. Metasploit memetakan temuan recon ke exploit yang ter-maintain & teruji.'
    source: https://docs.rapid7.com/metasploit/

  - id: a08
    tier: A
    type: text
    objective: prioritize
    prompt: "Dua kerentanan ditemukan: (A) RCE remote tanpa auth skor 9.8, (B) info-leak lokal skor 3.1. Mana diprioritaskan & kenapa?"
    answers: ['a — rce remote tanpa auth, dampak & kemudahan jauh lebih tinggi (langsung akses penuh dari jarak jauh)']
    patterns: ['\ba\b|rce|9\.8|yang pertama', '(dampak|kemudahan|severity|remote|tanpa auth)[^\n]*(tinggi|besar|langsung)', '(akses penuh|eksekusi kode|remote)[^\n]*(jauh|langsung)?']
    tests:
      fail: ['b dulu karena lebih mudah diabaikan', 'keduanya sama prioritas', 'b karena skornya kecil menarik', 'acak saja']
    explanation: 'Prioritaskan **A** (RCE remote tanpa auth, CVSS 9.8): ia memberi **eksekusi kode jarak jauh tanpa kredensial** — dampak maksimum (akses penuh) dengan kemudahan maksimum (dari jaringan, tanpa login). B (info-leak lokal 3.1) butuh akses lokal lebih dulu & dampaknya kecil. Prioritas = **dampak × kemudahan/eksploitabilitas** (tercermin di CVSS + konteks). Sebagai red, A adalah jalan masuk tercepat; sebagai blue (board DevSecOps modul 05), A ditambal lebih dulu. Jangan buang waktu di kerentanan skor rendah selagi ada yang critical & remote menganga — kejar "buah yang menggantung rendah tapi berdampak tinggi".'
    source: https://www.first.org/cvss/

  - id: a09
    tier: A
    type: command
    objective: searchsploit
    prompt: Perbarui database lokal Exploit-DB agar searchsploit punya exploit terbaru.
    answers: ['searchsploit --update']
    patterns: ['^searchsploit[ \t]+--update', '^searchsploit[ \t]+-u([ \t]|$)']
    tests:
      pass:
        - 'searchsploit --update'
        - 'searchsploit -u'
      fail:
        - 'apt update'
        - 'searchsploit update'
        - 'searchsploit vsftpd'
    explanation: '`searchsploit --update` (atau `-u`) menyegarkan salinan lokal Exploit-DB. Penting karena exploit baru terus ditambahkan — database basi bisa melewatkan exploit untuk kerentanan terkini. Analog dengan memperbarui tool (modul 01 b05: update Kali). Di dunia nyata, analisis kerentanan bergantung pada data terbaru: database exploit, feed CVE, intel. Sisi blue (board DevSecOps modul 05): hal yang sama berlaku defensif — scanner (trivy) perlu database CVE terbaru, dan pemindaian **berkala** penting karena CVE baru muncul untuk software yang tak berubah. Data basi = titik buta, di kedua sisi.'
    source: https://www.exploit-db.com/searchsploit

  - id: a10
    tier: A
    type: text
    objective: verify
    prompt: Mengapa mencocokkan versi DAN platform/arsitektur exploit dengan target itu krusial sebelum menjalankannya?
    answers: ['exploit spesifik versi/platform; menjalankan yang tak cocok gagal atau malah men-crash target (offset/payload salah)']
    patterns: ['(spesifik|cocok)[^\n]*(versi|platform|arsitektur|os)', '(tak cocok|salah)[^\n]*(gagal|crash|error|tidak jalan)', '(offset|payload|target)[^\n]*(salah|beda)']
    tests:
      fail: ['semua exploit lintas-versi', 'platform tidak berpengaruh', 'coba saja semua exploit', 'versi tidak penting']
    explanation: 'Exploit sering **sangat spesifik**: ditulis untuk versi, OS, arsitektur (x86/x64), bahkan build tertentu — karena bergantung pada offset memori, path, atau perilaku yang berbeda antar versi. Menjalankan exploit yang tak cocok biasanya **gagal diam-diam**, atau lebih buruk, **men-crash servis/target** (payload/offset salah → segfault). Karena itu cocokkan versi (`-sV`) & platform (`-O`, modul 02) sebelum memilih exploit, dan baca exploit (`searchsploit -x`) untuk syarat & target yang didukung. Di produksi (RoE, modul 01), menjalankan exploit tak cocok yang menjatuhkan layanan adalah pelanggaran. Presisi > coba-coba.'
    source: https://www.exploit-db.com/searchsploit

  - id: a11
    tier: A
    type: text
    objective: cve
    prompt: Di mana kamu mencari detail resmi sebuah CVE (deskripsi, versi terdampak, skor)?
    answers: ['nvd (national vulnerability database, nvd.nist.gov) atau advisory vendor/mitre']
    patterns: ['nvd|nist|national vulnerability database', 'mitre|cve\.org', '(advisory|bulletin)[^\n]*vendor|vendor advisory']
    tests:
      fail: ['di stackoverflow saja', 'tebak sendiri', 'tidak ada sumber resmi', 'hanya di forum']
    explanation: 'Sumber resmi CVE: **NVD** (nvd.nist.gov — National Vulnerability Database, berisi deskripsi, versi terdampak, skor CVSS, referensi), **MITRE** (cve.org — pendaftar CVE), dan **advisory vendor** (mis. advisory Apache/Microsoft untuk detail & patch). Dari versi servis target → cari CVE yang berlaku → baca di NVD untuk memastikan versimu benar-benar terdampak & apakah ada exploit publik. Ini verifikasi penting (a06): jangan sekadar percaya "versi X konon rentan" — konfirmasi di sumber resmi. Sisi blue: tim keamanan memakai feed CVE/NVD yang sama untuk memprioritaskan patching (board DevSecOps modul 05).'
    source: https://nvd.nist.gov/

  - id: a12
    tier: A
    type: command
    objective: msfsearch
    prompt: Di msfconsole, cari modul yang terkait CVE tertentu, mis. `cve:2007-2447` (Samba usermap_script).
    answers: ['search cve:2007-2447']
    patterns: ['^search[ \t]+cve:\S+', '^search[ \t].*cve:2007-2447']
    tests:
      pass:
        - 'search cve:2007-2447'
        - 'search cve:2007-2447'
      fail:
        - 'search samba'
        - 'searchsploit cve 2007-2447'
        - 'search 2007-2447'
    explanation: '`search cve:<id>` di msfconsole menemukan modul Metasploit untuk CVE spesifik. `cve:2007-2447` menemukan exploit Samba "usermap_script" (RCE pada Samba 3.0.x — persis yang ada di Metasploitable). Filter CVE sangat berguna ketika kamu sudah mengidentifikasi CVE dari analisis (NVD/searchsploit) dan ingin tahu apakah Metasploit punya modulnya. Filter lain: `type:`, `platform:`, `rank:` (keandalan exploit — `excellent`/`great` lebih aman dijalankan). Alur: versi → CVE → `search cve:` → `use` → eksploitasi (modul 05). Ini menghubungkan database kerentanan ke tool eksploitasi dengan presisi.'
    source: https://docs.rapid7.com/metasploit/

  - id: a13
    tier: A
    type: text
    objective: verify
    prompt: Apa beda "potentially vulnerable" (dari scanner) dan "confirmed exploitable" dalam laporan?
    answers: ['potentially = scanner menduga berdasarkan versi/heuristik; confirmed = sudah dibuktikan bisa dieksploitasi — bobot & urgensinya berbeda']
    patterns: ['potentially[^\n]*(duga|versi|heuristik|belum (dibuktikan|pasti))', 'confirmed[^\n]*(bukti|dibuktikan|eksploit|terbukti)', '(bobot|urgensi|kepastian)[^\n]*(beda|berbeda)']
    tests:
      fail: ['keduanya sama', 'potentially lebih pasti dari confirmed', 'tidak perlu dibedakan', 'scanner selalu confirmed']
    explanation: '**Potentially vulnerable** = scanner **menduga** berdasarkan versi/heuristik (belum dibuktikan — bisa false-positive, a06). **Confirmed exploitable** = kamu sudah **membuktikan** bisa dieksploitasi (mendapat shell/akses). Bedanya besar dalam laporan (modul 10): "confirmed critical RCE" memaksa tindakan segera; "potentially" perlu investigasi lebih lanjut. Pentester profesional membuktikan temuan penting (dalam scope & tanpa merusak) agar laporan kredibel & actionable — "percaya scanner" menghasilkan laporan penuh false-positive yang membuat klien kehilangan kepercayaan. Bedakan dugaan dari bukti; itu inti integritas pelaporan.'
    source: https://nvd.nist.gov/

  - id: a14
    tier: A
    type: text
    objective: prioritize
    prompt: Apa yang dimaksud "low-hanging fruit" dalam analisis kerentanan, dan kenapa dikejar lebih dulu?
    answers: ['kerentanan yang mudah dieksploitasi dengan dampak tinggi (mis. exploit matang/kredensial default) — jalan masuk tercepat & paling pasti']
    patterns: ['(mudah|gampang)[^\n]*(eksploit|exploit)[^\n]*(dampak (tinggi|besar))?', '(exploit matang|siap pakai|kredensial default|misconfig)', '(cepat|pasti|efisien)[^\n]*(masuk|akses|hasil)']
    tests:
      fail: ['kerentanan tersulit dikejar dulu', 'low-hanging fruit tidak berguna', 'abaikan yang mudah', 'tidak ada prioritas']
    explanation: '"Low-hanging fruit" = kerentanan yang **mudah dieksploitasi dengan dampak tinggi**: exploit matang/siap-pakai (vsftpd backdoor, Samba usermap), kredensial default/lemah (a14 modul 03), misconfig fatal (NFS `/ *`). Dikejar dulu karena memberi akses **tercepat & paling pasti** dengan usaha minimal — efisiensi. Setelah pijakan didapat, baru kejar yang lebih sulit bila perlu. Sebagai red ini pragmatis; sebagai blue (board DevSecOps) ini mengkhawatirkan — artinya hal termudah untuk diperbaiki (ganti default, patch, benahi misconfig) justru yang paling sering jadi jalan bobol. Tutup buah rendah lebih dulu, di kedua sisi.'
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: a15
    tier: A
    type: text
    objective: cve
    prompt: Selain CVSS, faktor konteks apa yang menentukan risiko SEBENARNYA sebuah kerentanan bagi suatu organisasi?
    answers: ['nilai/sensitivitas aset terdampak, apakah terekspos ke internet, ada exploit publik/aktif dieksploitasi, dan kontrol mitigasi yang ada']
    patterns: ['(nilai|sensitivitas|kritis)[^\n]*(aset|data|sistem)', '(terekspos|exposure)[^\n]*(internet|publik)', '(exploit (publik|aktif)|in the wild|dieksploitasi)', '(mitigasi|kontrol)[^\n]*(ada|kompensasi)']
    tests:
      fail: ['hanya cvss yang menentukan', 'konteks tidak penting', 'semua kerentanan sama risikonya', 'lokasi aset tidak relevan']
    explanation: 'CVSS adalah severity **teknis** generik — risiko **nyata** bergantung konteks: **nilai/sensitivitas aset** (DB pelanggan vs halaman statis), **eksposur** (terekspos internet vs internal saja), **eksploitasi aktif** (apakah ada exploit publik / sedang dieksploitasi "in the wild" — mis. KEV CISA), dan **kontrol mitigasi** yang sudah ada (WAF, segmentasi). Kerentanan CVSS 9.8 di sistem internal tanpa exploit publik bisa kurang mendesak dari 7.5 di server internet dengan exploit aktif. Sebagai red ini membantu memprediksi apa yang paling berharga; sebagai blue (board DevSecOps) ini dasar manajemen kerentanan berbasis risiko (bukan sekadar "tambal semua 9+"). Risiko = severity × konteks.'
    source: https://www.first.org/cvss/

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: command
    objective: searchsploit
    prompt: Periksa (lihat isi) sebuah exploit Exploit-DB ber-ID `12345` TANPA menyalinnya, untuk membacanya dulu.
    answers: ['searchsploit -x 12345']
    patterns: ['^searchsploit[ \t].*-x[ \t]+\S+']
    tests:
      pass:
        - 'searchsploit -x 12345'
        - 'searchsploit -x exploits/unix/remote/12345.rb'
      fail:
        - 'searchsploit -m 12345'
        - 'cat 12345'
        - 'searchsploit 12345'
    explanation: '`searchsploit -x <id>` ("examine") menampilkan isi file exploit di pager — untuk **membacanya sebelum menjalankan**. Ini kebiasaan keamanan penting: exploit (terutama yang diunduh) bisa berisi kode berbahaya, backdoor, atau merusak target dengan cara tak terduga. Baca untuk memahami: apa yang dilakukannya, target/versi yang didukung, variabel yang perlu disesuaikan (IP, port), dan apakah aman di lab-mu. `-x` membaca, `-m` menyalin (a05). Prinsip "do no harm" (modul 01): jangan pernah menjalankan kode yang belum kamu pahami, apalagi di sistem yang (di dunia nyata) milik klien.'
    source: https://www.exploit-db.com/searchsploit

  - id: b02
    tier: B
    type: text
    objective: msfsearch
    prompt: Dalam hasil search Metasploit ada kolom "rank" (excellent, great, normal, ...). Apa maknanya & kenapa penting?
    answers: ['rank = keandalan/keamanan modul; excellent/great jarang merusak target, rank rendah lebih berisiko crash — pilih rank tinggi dulu']
    patterns: ['rank[^\n]*(keandalan|reliabilitas|keamanan|stabil)', '(excellent|great)[^\n]*(andal|aman|jarang (crash|rusak))', '(rendah|low)[^\n]*(berisiko|crash|rusak|tak stabil)']
    tests:
      fail: ['rank tidak penting', 'rank adalah harga', 'rank rendah lebih aman', 'semua rank sama']
    explanation: 'Di Metasploit, **rank** menunjukkan keandalan & keamanan modul exploit: `excellent` (tak akan men-crash target, mis. command injection), `great`, `good`, `normal`, `average`, `low`, `manual`. Rank tinggi = lebih aman dijalankan (jarang merusak layanan); rank rendah/`manual` = berisiko men-crash target atau butuh penyesuaian. Penting untuk **do no harm** (modul 01) & RoE (terutama di produksi): pilih exploit rank tinggi dulu. `search rank:excellent type:exploit samba`. Ini juga petunjuk prioritas: exploit `excellent` + CVSS tinggi = low-hanging fruit ideal. Keandalan exploit adalah pertimbangan nyata, bukan hanya "apakah ada exploit".'
    source: https://docs.rapid7.com/metasploit/

  - id: b03
    tier: B
    type: text
    objective: nmapvuln
    prompt: Sebutkan dua keterbatasan mengandalkan nmap --script vuln (atau scanner otomatis) sebagai satu-satunya sumber temuan.
    answers: ['false positive/negative (akurasi terbatas) dan cakupan terbatas (tak menemukan logic flaw/konfigurasi/kerentanan baru); perlu analisis manual']
    patterns: ['false (positive|negative)', '(cakupan|coverage)[^\n]*(terbatas|tak (semua|lengkap))|tak menemukan[^\n]*(logic|konfigurasi|baru)', '(manual|verifikasi)[^\n]*(perlu|tetap)']
    tests:
      fail: ['scanner menemukan semua kerentanan', 'tidak ada keterbatasan', 'scanner menggantikan analisis manual', 'selalu 100% akurat']
    explanation: 'Keterbatasan scanner otomatis: (1) **akurasi** — false-positive (lapor rentan padahal tidak) & false-negative (lewatkan yang ada), karena mengandalkan versi/heuristik (a06); (2) **cakupan** — hanya menemukan yang ada di database/signature-nya; **tak menemukan** logic flaw, kesalahan otorisasi (IDOR), misconfig spesifik, chaining kreatif, atau kerentanan baru. Karena itu pentest memadukan scanner (cepat, luas) dengan **analisis manual** (dalam, kontekstual). Sisi blue (board DevSecOps): sama — scanner (trivy, modul 05/11) adalah lapisan, bukan jaminan; tetap butuh review & pengujian manual. Alat mempercepat, manusia memahami.'
    source: https://nmap.org/book/nse.html

  - id: b04
    tier: B
    type: text
    objective: verify
    prompt: vsftpd 2.3.4 terkenal punya backdoor. Mengapa kamu tetap mengonfirmasi versinya DAN bahwa backdoor itu memang ada sebelum menyimpulkan?
    answers: ['banner versi bisa dipalsukan/menyesatkan dan tak semua build 2.3.4 punya backdoor; konfirmasi (uji/baca) agar tak false-positive']
    patterns: ['banner[^\n]*(palsu|dipalsukan|menyesatkan|tidak akurat)', '(tak semua|belum tentu)[^\n]*(build|versi)[^\n]*(backdoor|rentan)', '(konfirmasi|verifikasi|uji)[^\n]*(backdoor|rentan|benar)']
    tests:
      fail: ['versi di banner selalu benar', 'semua 2.3.4 pasti rentan', 'tidak perlu konfirmasi', 'langsung simpulkan dari banner']
    explanation: 'Dua alasan konfirmasi: (1) **banner versi bisa menyesatkan** — admin bisa memalsukan/mengubah banner (atau backport patch tanpa mengubah nomor versi), jadi "banner bilang 2.3.4" belum pasti benar-benar 2.3.4 rentan; (2) **tak semua** distribusi/build 2.3.4 membawa backdoor (backdoor itu ada di satu rilis yang dikompromikan di server distribusi, bukan semua). Jadi konfirmasi: baca exploit (`-x`), uji di lab, atau cari indikator lain. Ini mencegah **false-positive** dalam laporan (a06/a13). Di Metasploitable memang rentan — tapi disiplin "verifikasi, jangan asumsi" adalah yang kamu bawa ke dunia nyata, tempat banner tak selalu jujur.'
    source: https://nvd.nist.gov/

  - id: b05
    tier: B
    type: command
    objective: searchsploit
    prompt: Cari exploit Samba dan tampilkan hanya yang berupa exploit remote (filter dengan grep pada output searchsploit).
    answers: ['searchsploit samba | grep -i remote']
    patterns: ['^searchsploit[ \t]+\S*samba[^\n]*\|[^\n]*grep[^\n]*remote', '^searchsploit[ \t].*\|[ \t]*grep']
    tests:
      pass:
        - 'searchsploit samba | grep -i remote'
        - 'searchsploit samba | grep -i remote'
      fail:
        - 'searchsploit samba'
        - 'grep samba'
        - 'searchsploit remote'
    explanation: 'Menyalurkan output searchsploit ke `grep` menyaring hasil yang banyak: `searchsploit samba | grep -i remote` fokus ke exploit remote (yang bisa dipakai tanpa akses lokal — lebih menarik sebagai jalan masuk awal). Idiom Unix yang sama (pipe + grep) dari board Linux/DevSecOps. Bisa juga filter via opsi searchsploit: `searchsploit --exclude="dos"` (buang DoS), `-t` (cari di judul saja). Menyaring membantu memprioritaskan (a08/a14): dari puluhan hasil, fokus ke remote + rank tinggi + versi cocok. Analisis kerentanan yang efisien = menyaring kebisingan ke kandidat terbaik dengan cepat.'
    source: https://www.exploit-db.com/searchsploit

  - id: b06
    tier: B
    type: text
    objective: prioritize
    prompt: Dalam memilih urutan eksploitasi di Metasploitable, mengapa "exploit yang memberi akses root langsung" lebih diprioritaskan dari yang memberi user biasa?
    answers: ['akses root langsung melewati tahap privilege escalation — lebih cepat ke kontrol penuh; tapi tetap pertimbangkan keandalan & kesenyapan']
    patterns: ['root[^\n]*(langsung|lewati|skip)[^\n]*(privilege escalation|privesc|eskalasi)', '(cepat|langsung)[^\n]*(kontrol penuh|akses penuh|root)', '(keandalan|rank|senyap)[^\n]*(tetap|juga)?']
    tests:
      fail: ['user biasa selalu lebih baik', 'root tidak berguna', 'tidak ada bedanya', 'privilege escalation tidak perlu dipikirkan']
    explanation: 'Exploit yang langsung memberi **root** (mis. banyak servis Metasploitable berjalan sebagai root → RCE = shell root) **melewati** tahap privilege escalation (modul 08) — lebih cepat ke kontrol penuh. Exploit yang memberi **user biasa** masih perlu langkah eskalasi tambahan. Jadi, bila ada pilihan, root langsung lebih efisien. TAPI tetap timbang **keandalan** (rank, b02 — jangan pilih exploit root yang sering men-crash) & **kesenyapan**. Catatan blue (board DevSecOps modul 06): inilah kenapa menjalankan servis sebagai **non-root** krusial — kalaupun servis jebol, penyerang hanya dapat user biasa, bukan langsung root. Least privilege mengubah "RCE = game over" jadi "RCE = awal perjuangan".'
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: b07
    tier: B
    type: text
    objective: cve
    prompt: Apa itu "n-day" vs "zero-day" vulnerability?
    answers: ['zero-day: belum diketahui vendor/belum ada patch; n-day: sudah diketahui & ada patch tapi sistem belum menambalnya']
    patterns: ['zero[- ]?day[^\n]*(belum (diketahui|ada patch)|tidak ada patch|baru)', 'n-?day[^\n]*(sudah (diketahui|ada patch)|belum (ditambal|di-?patch))', '(patch|tambal)[^\n]*(belum|ada)']
    tests:
      fail: ['keduanya sama', 'zero-day sudah ada patch', 'n-day tidak diketahui siapa pun', 'tidak ada bedanya']
    explanation: '**Zero-day (0-day)**: kerentanan yang **belum diketahui vendor / belum ada patch** — berbahaya karena tak ada perbaikan & deteksi sulit (langka, mahal, sering disimpan aktor canggih). **N-day**: kerentanan yang **sudah diketahui publik & ada patch**, tapi sistem **belum menambalnya** — ini mayoritas kompromi dunia nyata, dan persis yang kamu latih di Metasploitable (semua kerentanannya n-day, bahkan sangat tua). Pelajaran blue terbesar (board DevSecOps modul 05): **patching menutup n-day** — kebanyakan breach bukan 0-day canggih, tapi n-day yang tak ditambal. Analisis kerentanan pentest terutama berburu n-day di sistem yang tertinggal patch.'
    source: https://nvd.nist.gov/

  - id: b08
    tier: B
    type: command
    objective: msfsearch
    prompt: Di msfconsole, cari modul AUXILIARY (scanner/enum, bukan exploit) yang terkait "smb".
    answers: ['search type:auxiliary smb']
    patterns: ['^search[ \t].*type:auxiliary[^\n]*smb|^search[ \t].*smb[^\n]*type:auxiliary', '^search[ \t].*type:auxiliary']
    tests:
      pass:
        - 'search type:auxiliary smb'
        - 'search smb type:auxiliary'
      fail:
        - 'search smb'
        - 'search type:exploit smb'
        - 'searchsploit smb'
    explanation: 'Metasploit bukan hanya exploit — modul **auxiliary** mencakup scanner, enumerator, fuzzer, dan brute-forcer. `search type:auxiliary smb` menemukan modul seperti `smb_version`, `smb_enumshares`, `smb_login` (brute). Ini memperkaya analisis kerentanan di dalam msf: scan versi, enum share, cek kredensial — lalu lanjut ke exploit. Jenis modul: `exploit` (dapat akses), `auxiliary` (scan/enum/dos/brute), `post` (post-exploitation, modul 08), `payload`, `encoder`. Memahami taksonomi ini membuat Metasploit jadi platform lengkap recon→exploit→post, bukan sekadar "tombol exploit".'
    source: https://docs.rapid7.com/metasploit/

  - id: b09
    tier: B
    type: text
    objective: verify
    prompt: Mengapa menjalankan exploit yang belum dipahami (mis. dari internet) pada target bisa berbahaya, bahkan di lab?
    answers: ['exploit bisa berisi kode jahat/backdoor, merusak target tak terduga, atau menyerang sistem lain; baca & pahami dulu (searchsploit -x)']
    patterns: ['(kode jahat|backdoor|malware|berisi jahat)', '(merusak|crash|rusak)[^\n]*(target|sistem)', '(baca|pahami|periksa)[^\n]*(dulu|sebelum)']
    tests:
      fail: ['exploit dari internet selalu aman', 'tidak perlu membaca exploit', 'jalankan saja semua', 'exploit tidak bisa berbahaya']
    explanation: 'Exploit publik (Exploit-DB, GitHub, forum) adalah **kode tak tepercaya**. Bahayanya: bisa berisi **backdoor** (menyerang KAMU, bukan target — "proof of concept" yang diam-diam exfiltrasi), **merusak target** dengan cara tak terduga (payload destruktif, wipe), atau **menyerang sistem lain** (hardcoded target). Karena itu: **baca dulu** (`searchsploit -x`, b01), pahami apa yang dilakukannya, jalankan di lab terisolasi (modul 01), dan curigai yang di-obfuscate. Bahkan di lab, exploit berbahaya bisa lolos dari isolasi atau merusak VM-mu. Prinsip "jangan jalankan kode yang belum dipahami" (tema berulang: supply chain, modul DevSecOps 05/11) berlaku juga untuk tool-mu sendiri.'
    source: https://www.exploit-db.com/searchsploit

  - id: b10
    tier: B
    type: text
    objective: prioritize
    prompt: Bagaimana hasil analisis kerentanan red-team seharusnya memandu prioritas PATCHING tim blue (DevSecOps)?
    answers: ['tambal dulu yang terbukti/mudah dieksploitasi & berdampak tinggi pada aset penting/terekspos — low-hanging fruit penyerang = prioritas pertahanan']
    patterns: ['(tambal|patch)[^\n]*(dulu|prioritas)[^\n]*(terbukti|mudah|dampak tinggi|critical)', 'low-?hanging fruit[^\n]*(penyerang|prioritas)', '(aset (penting|terekspos)|terekspos internet)[^\n]*(dulu|prioritas)']
    tests:
      fail: ['tambal acak', 'red dan blue tidak berhubungan', 'tambal yang paling sulit dulu', 'prioritas tidak penting']
    explanation: 'Inti purple team: temuan red = daftar prioritas blue. Yang paling **mudah & berdampak** dieksploitasi penyerang (low-hanging fruit, a14: exploit matang, kredensial default, misconfig fatal, CVE critical di aset terekspos) harus **ditambal/ditutup lebih dulu** — karena itu yang akan dipakai menyerang. Red membuktikan "ini jalan masuk nyata & termudah"; blue menutupnya sesuai urutan itu. Ini manajemen kerentanan berbasis risiko (board DevSecOps modul 05/11): bukan "tambal semua sekaligus" (mustahil), tapi "tambal yang paling mungkin & merusak dulu". Pentest yang baik memberi klien daftar prioritas yang jelas, bukan ratusan temuan tanpa urutan.'
    source: https://www.first.org/cvss/

  - id: b11
    tier: B
    type: text
    objective: nmapvuln
    prompt: Selain nmap --script vuln, sebutkan satu jenis scanner kerentanan yang lebih komprehensif untuk analisis mendalam.
    answers: ['vulnerability scanner khusus seperti openvas/greenbone, nessus, atau nuclei (web)']
    patterns: ['openvas|greenbone|nessus|nexpose|nuclei|qualys', '(scanner|pemindai) (kerentanan|vulnerability)[^\n]*(khusus|dedicated|komprehensif)']
    tests:
      fail: ['hanya nmap yang ada', 'tidak ada scanner lain', 'ping adalah scanner', 'searchsploit adalah scanner']
    explanation: 'Scanner kerentanan khusus lebih komprehensif dari `nmap --script vuln`: **OpenVAS/Greenbone** (open-source, ribuan tes), **Nessus** (komersial, standar industri), **Nuclei** (cepat, berbasis template, kuat untuk web), **Nexpose/Qualys**. Mereka punya database besar, pelaporan terstruktur, dan deteksi lebih dalam. Dipakai di pentest & operasi keamanan rutin. TAPI keterbatasan sama berlaku (b03): false-positive, butuh verifikasi, tak menemukan logic flaw. Di Metasploitable, `nmap --script vuln` + searchsploit + pengetahuan sudah cukup. Sisi blue: scanner ini (plus SCA seperti trivy, board DevSecOps modul 05/11) adalah tulang punggung manajemen kerentanan berkelanjutan.'
    source: https://nvd.nist.gov/

  - id: b12
    tier: B
    type: text
    objective: cve
    prompt: Apa itu CISA KEV (Known Exploited Vulnerabilities), dan kenapa relevan untuk prioritas?
    answers: ['katalog cve yang TERBUKTI sedang dieksploitasi "in the wild"; prioritaskan tambal ini karena ancamannya nyata & aktif, bukan teoretis']
    patterns: ['(dieksploitasi|exploited)[^\n]*(in the wild|aktif|nyata|liar)', '(terbukti|known)[^\n]*(eksploit|exploited)', 'prioritas[^\n]*(aktif|nyata|dieksploitasi)']
    tests:
      fail: ['daftar cve acak', 'cve yang belum pernah dieksploitasi', 'tidak relevan untuk prioritas', 'sama dengan semua cve']
    explanation: 'CISA KEV (Known Exploited Vulnerabilities) adalah katalog CVE yang **terbukti sedang dieksploitasi secara nyata** ("in the wild") — bukan sekadar ada secara teoretis. Relevan karena mempertajam prioritas (a15): CVE di KEV = ancaman **aktif & nyata**, harus ditambal lebih dulu daripada CVE skor tinggi yang belum ada exploit aktif. Ini melengkapi CVSS dengan dimensi "apakah penjahat benar-benar memakainya sekarang". Sebagai red, KEV menunjukkan teknik yang terbukti berhasil; sebagai blue (board DevSecOps modul 05), KEV adalah daftar patch paling mendesak. Risiko nyata = severity × eksposur × **eksploitasi aktif**.'
    source: https://www.first.org/cvss/

  - id: b13
    tier: B
    type: text
    objective: searchsploit
    prompt: Metasploitable punya banyak kerentanan n-day sangat tua. Apa pelajaran dunia-nyata terpenting dari fakta ini?
    answers: ['mayoritas kompromi nyata bukan 0-day canggih tapi n-day lama yang tak ditambal; patching rutin menutup sebagian besar jalan masuk']
    patterns: ['(mayoritas|kebanyakan|sebagian besar)[^\n]*(kompromi|breach|serangan)[^\n]*(n-?day|lama|tak ditambal|bukan 0-?day)', 'patching[^\n]*(rutin|menutup|penting)', '(bukan|jarang)[^\n]*(0-?day|zero-?day)[^\n]*(canggih)?']
    tests:
      fail: ['semua serangan pakai 0-day canggih', 'patching tidak membantu', 'kerentanan lama tidak berbahaya', 'n-day tidak relevan']
    explanation: 'Pelajaran terbesar: **mayoritas kompromi dunia nyata bukan 0-day canggih, melainkan n-day lama yang tak ditambal** (b07) — persis seperti Metasploitable (semua kerentanannya tua & terdokumentasi). Penyerang tak perlu 0-day mahal jika targetnya masih menjalankan servis usang. Konsekuensi blue (board DevSecOps modul 05): **patching rutin & manajemen kerentanan menutup sebagian besar jalan masuk** — lebih berdampak dari mengejar ancaman eksotis. "Patch your stuff" terdengar membosankan tapi menggagalkan sebagian besar serangan. Inilah mengapa board ini melatih n-day: karena itulah yang nyata, dan menutupnya adalah pertahanan paling berdampak.'
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: b14
    tier: B
    type: text
    objective: verify
    prompt: Bagaimana mengimpor/mengelola temuan kerentanan agar tahap eksploitasi & pelaporan rapi?
    answers: ['pakai database (mis. metasploit workspace / db_import hasil scan) atau catatan terstruktur per host-servis-kerentanan-exploit; dokumentasikan konsisten']
    patterns: ['(database|workspace|db_import|metasploit)[^\n]*(temuan|scan|host)?', '(catatan|dokumentasi)[^\n]*(terstruktur|per host|konsisten)', 'per (host|servis|kerentanan)[^\n]*(exploit|temuan)']
    tests:
      fail: ['ingat semua di kepala', 'tidak perlu dikelola', 'catat acak', 'dokumentasi tidak penting']
    explanation: 'Pentest menghasilkan banyak temuan — kelola rapi agar eksploitasi terarah & laporan (modul 10) mudah disusun: impor hasil scan ke **Metasploit workspace** (`db_import scan.xml`, modul 02 b08 — lalu `vulns`, `services`), atau pakai catatan terstruktur (spreadsheet/markdown) per **host → servis → versi → kerentanan → exploit → status**. Dokumentasi konsisten (modul 01) berarti: kamu tahu apa yang sudah dicoba, bisa menelusuri ulang, dan laporan tinggal menyusun. Ini mengubah "aku menemukan banyak hal entah di mana" jadi daftar actionable. Terstruktur sejak analisis = efisien di eksploitasi & kredibel di pelaporan.'
    source: https://docs.rapid7.com/metasploit/

  - id: b15
    tier: B
    type: text
    objective: prioritize
    prompt: Jelaskan bagaimana modul ini (analisis kerentanan) adalah jembatan dua-arah antara red dan blue.
    answers: ['data yang sama (versi, cve, cvss, exploit) dipakai red untuk memilih serangan dan blue untuk memprioritaskan patch — searchsploit/nvd red = trivy/sca blue']
    patterns: ['(data|cve|cvss|versi)[^\n]*(sama)[^\n]*(red|blue|serang|patch)', '(red)[^\n]*(pilih|serang)[^\n]*(blue)[^\n]*(patch|prioritas)', '(searchsploit|exploit-db|nvd)[^\n]*(trivy|sca|scanner)|trivy[^\n]*searchsploit']
    tests:
      fail: ['red dan blue pakai data berbeda', 'tidak ada hubungan', 'analisis kerentanan hanya untuk red', 'blue tidak butuh cve']
    explanation: 'Analisis kerentanan memakai **data yang sama** dari kedua sisi: versi servis, CVE, skor CVSS, ketersediaan exploit. **Red** memakainya untuk memilih serangan termudah/berdampak (searchsploit, NVD, msf search). **Blue** memakai data identik untuk memprioritaskan patch (SCA/trivy, feed CVE, KEV — board DevSecOps modul 05/11). `searchsploit vsftpd 2.3.4` (red) dan `trivy image` yang melaporkan CVE vsftpd (blue) adalah dua sisi koin yang sama. Inilah purple paling murni: **berpikir dengan data kerentanan yang sama**. Red menunjukkan "ini yang akan kupakai"; blue menjawab "maka ini yang kutambal duluan". Memahami satu sisi mempertajam sisi lain.'
    source: https://www.first.org/cvss/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: prioritize
    prompt: |
      Enumerasi Metasploitable memberi: vsftpd 2.3.4 (21), OpenSSH tua (22), Samba 3.x (445), UnrealIRCd (6667),
      Apache+PHP dengan DVWA (80), MySQL root tanpa password (3306). Rancang analisis kerentanan & prioritasnya
      (satu poin per baris).
    checklist:
      - id: match
        label: Cocokkan tiap versi ke exploit/CVE (searchsploit, NVD, msf search cve:)
        required: true
        patterns: ['(searchsploit|search cve|nvd|cocokkan)[^\n]*(versi|exploit|cve)', 'searchsploit|search cve:']
      - id: prioritize
        label: Prioritaskan low-hanging fruit berdampak tinggi (dampak × kemudahan)
        required: true
        patterns: ['(prioritas|dahulukan)[^\n]*(low-?hanging|mudah|dampak tinggi|root langsung)', 'dampak[^\n]*kemudahan|buah (yang )?(menggantung )?rendah']
      - id: easywins
        label: Identifikasi kemenangan mudah (vsftpd backdoor, Samba usermap, MySQL root kosong)
        patterns: ['(vsftpd|samba|unrealircd|mysql)[^\n]*(backdoor|usermap|kosong|default|exploit)', '(kredensial|password) (default|kosong|lemah)']
      - id: rank
        label: Pilih exploit andal (rank tinggi) & cocok versi/platform untuk hindari crash
        patterns: ['rank[^\n]*(tinggi|excellent|great|andal)', '(cocok|sesuai)[^\n]*(versi|platform)', '(hindari|cegah)[^\n]*crash']
      - id: verify
        label: Verifikasi temuan (bukan hanya percaya banner/scanner)
        patterns: ['verifikasi|konfirmasi', '(bukan|jangan)[^\n]*(percaya|asumsi)[^\n]*(banner|scanner)|false positive']
      - id: blue
        label: Catat urutan patch untuk blue (temuan red = prioritas pertahanan)
        patterns: ['(patch|tambal|remediasi)[^\n]*(prioritas|urutan|dulu)', '(blue|pertahanan|devsecops)[^\n]*(prioritas|patch)']
      - id: no-random
        label: Tidak menembak semua exploit acak tanpa mencocokkan & memprioritaskan
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain))[^\n]*(tembak|coba|jalankan)[^\n]*(semua )?exploit[^\n]*(acak|asal|random|sembarang)']
    pass_score: 0.5
    solution: |
      ```text
      1. Cocokkan versi→exploit/CVE: searchsploit vsftpd 2.3.4 / samba / unrealircd; NVD untuk CVE; msf search cve:2007-2447.
      2. Prioritaskan low-hanging fruit berdampak tinggi:
         - MySQL root tanpa password (3306) → akses DB instan, mungkin → RCE. Tercepat.
         - vsftpd 2.3.4 backdoor (21) → shell root langsung, exploit matang di msf.
         - Samba usermap_script CVE-2007-2447 (445) → RCE root, rank excellent.
         - UnrealIRCd backdoor (6667) → RCE.
         - DVWA (80) → latihan eksploitasi web bertingkat (modul web).
      3. Pilih exploit rank tinggi & cocok versi/platform (hindari crash, do no harm).
      4. Verifikasi (baca exploit -x, konfirmasi versi; jangan percaya banner buta).
      5. (Blue) catat urutan patch = urutan prioritas ini: ganti kredensial default MySQL, patch vsftpd/Samba/UnrealIRCd,
         amankan DVWA — temuan red jadi daftar prioritas pertahanan (board DevSecOps modul 05).
      ```
      Analisis yang baik = cocokkan, prioritaskan (dampak×kemudahan), verifikasi — bukan menembak exploit acak.
    tests:
      pass:
        - |
          cocokkan tiap versi ke exploit dan cve dengan searchsploit nvd dan msf search cve
          prioritaskan low-hanging fruit berdampak tinggi berdasarkan dampak kali kemudahan
          identifikasi kemenangan mudah seperti vsftpd 2.3.4 backdoor samba usermap dan mysql root tanpa password
          pilih exploit rank tinggi yang cocok versi dan platform untuk hindari crash
          verifikasi temuan jangan hanya percaya banner atau scanner
          catat urutan patch untuk blue karena temuan red jadi prioritas pertahanan
          jangan menembak semua exploit acak tanpa mencocokkan dan memprioritaskan
        - |
          searchsploit dan search cve tiap versi, dahulukan mysql root kosong dan vsftpd backdoor yang mudah berdampak
          pilih rank excellent cocok versi, verifikasi jangan percaya banner, catat urutan patch blue, jangan exploit asal
      fail:
        - |
          tembak semua exploit acak sampai ada yang jalan
          tidak perlu prioritas atau verifikasi
        - |
          jalankan exploit sembarang tanpa cocokkan versi
          urutan patch blue tidak penting
        - |
          coba random dari exploit-db tanpa baca
          tidak usah cocokkan cve
    source: https://docs.rapid7.com/metasploit/metasploitable-2/

  - id: c02
    tier: C
    type: scenario
    objective: verify
    prompt: |
      Scanner otomatis (nmap --script vuln) melaporkan 15 "potentially vulnerable". Rancang cara memverifikasi &
      menyaringnya menjadi temuan yang kredibel untuk laporan (satu poin per baris).
    checklist:
      - id: confirmversion
        label: Konfirmasi versi & platform persis (banner bisa menyesatkan)
        required: true
        patterns: ['(konfirmasi|cek|pastikan)[^\n]*(versi|platform)', 'banner[^\n]*(menyesatkan|palsu|tidak akurat|belum tentu)']
      - id: crosscheck
        label: Cross-check ke sumber resmi (NVD/advisory) apakah versimu terdampak
        patterns: ['(nvd|advisory|mitre|sumber resmi)[^\n]*(cek|cross-?check|konfirmasi)?', 'cocokkan[^\n]*(cve|versi terdampak)']
      - id: prove
        label: Buktikan yang penting (uji/exploit di lab) → "confirmed" bukan "potentially"
        required: true
        patterns: ['(buktikan|uji|exploit)[^\n]*(lab|aman)?', 'confirmed[^\n]*(bukan|vs)[^\n]*potentially', 'terbukti dieksploitasi']
      - id: falsepositive
        label: Buang false-positive (fitur tak aktif, sudah di-patch/backport)
        patterns: ['false[- ]?positive', '(buang|singkirkan|eliminasi)[^\n]*(false|tidak berlaku|tak relevan)', '(backport|patched|tak aktif)']
      - id: document
        label: Dokumentasikan tiap temuan dengan bukti & tingkat kepastian
        patterns: ['(dokumentasi|catat)[^\n]*(bukti|kepastian|confirmed|potentially)', 'laporan[^\n]*(bukti|kepastian)']
      - id: no-dump
        label: Tidak langsung menuang 15 temuan mentah scanner ke laporan tanpa verifikasi
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain|setelah))[^\n]*(langsung|cukup)[^\n]*(tuang|salin|copy|masukkan)[^\n]*(temuan|hasil) (scanner|mentah)']
    pass_score: 0.5
    solution: |
      ```text
      1. Konfirmasi versi & platform persis — banner bisa dipalsukan/di-backport (b04); pastikan versimu BENAR.
      2. Cross-check ke NVD/advisory vendor: apakah versi persismu ada di daftar terdampak CVE itu?
      3. Buktikan temuan PENTING di lab (uji/exploit dalam scope, hati-hati) → status "confirmed exploitable" (a13).
      4. Buang false-positive: fitur rentan tak aktif, sudah di-patch/backport, atau tak relevan dengan konfigurasi target.
      5. Dokumentasikan tiap temuan dengan BUKTI & tingkat kepastian (confirmed vs potentially) untuk laporan (modul 10).
      ```
      15 "potentially" mentah scanner BUKAN laporan — itu hipotesis. Verifikasi menyaringnya jadi temuan kredibel &
      actionable. Menuang hasil scanner mentah ke klien = laporan penuh false-positive yang merusak kepercayaan.
    tests:
      pass:
        - |
          konfirmasi versi dan platform persis karena banner bisa menyesatkan
          cross-check ke sumber resmi nvd atau advisory apakah versimu benar terdampak
          buktikan yang penting dengan uji atau exploit di lab jadi confirmed bukan potentially
          buang false positive seperti fitur tak aktif atau sudah di-patch backport
          dokumentasikan tiap temuan dengan bukti dan tingkat kepastian
          jangan langsung menuang 15 temuan mentah scanner ke laporan tanpa verifikasi
        - |
          pastikan versi persis, cross check ke nvd, buktikan yang penting di lab jadi confirmed
          singkirkan false positive, dokumentasikan dengan bukti dan kepastian, jangan copy mentah scanner ke laporan
      fail:
        - |
          langsung salin 15 temuan scanner ke laporan
          scanner pasti benar tidak perlu verifikasi
        - |
          percaya semua potentially sebagai confirmed
          tidak usah cross check atau buktikan
        - |
          tuang hasil mentah ke klien
          false positive tidak masalah
    source: https://nvd.nist.gov/

  - id: c03
    tier: C
    type: scenario
    objective: prioritize
    prompt: |
      Purple: dari analisis kerentanan Metasploitable, rancang rekomendasi untuk tim BLUE agar menutup kelas
      kerentanan ini di sistem nyata (satu poin per baris).
    checklist:
      - id: patch
        label: Patching rutin & manajemen kerentanan (n-day lama = jalan masuk utama)
        required: true
        patterns: ['patch(ing)?[^\n]*(rutin|berkala|manajemen)', 'n-?day|servis usang|versi lama[^\n]*(patch|tutup)']
      - id: sca
        label: Scanning kerentanan/SCA otomatis & berkala (trivy, scanner) di pipeline
        patterns: ['(sca|scanning|scanner|trivy)[^\n]*(otomat|berkala|pipeline)?', 'pindai (kerentanan|cve)[^\n]*(rutin|berkala|otomatis)']
      - id: defaults
        label: Hilangkan kredensial default/lemah + MFA (buah rendah penyerang)
        patterns: ['(ganti|hilang|larang)[^\n]*(default|lemah)[^\n]*(kredensial|password)', 'mfa|password kuat']
      - id: reduce
        label: Kurangi eksposur (matikan servis tak perlu, segmentasi) & non-root
        patterns: ['(matikan|tutup|kurangi)[^\n]*(servis|port|eksposur)', 'non-?root|least privilege|segmentasi']
      - id: risk
        label: Prioritaskan berbasis risiko (CVSS + eksposur + eksploitasi aktif/KEV)
        patterns: ['(prioritas|risk-?based)[^\n]*(cvss|eksposur|kev|aktif)', 'berbasis risiko']
      - id: no-ignore
        label: Tidak membiarkan servis usang "karena masih jalan / belum pernah kena"
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain))[^\n]*(biarkan|tetap)[^\n]*(servis usang|versi lama)[^\n]*(masih jalan|belum (pernah )?kena|saja)']
    pass_score: 0.45
    solution: |
      ```text
      1. Patching rutin & manajemen kerentanan — mayoritas kompromi adalah n-day lama yang tak ditambal (b13);
         ini pertahanan paling berdampak (board DevSecOps modul 05).
      2. Scanning kerentanan/SCA otomatis & berkala (trivy di pipeline, modul 05/11) — temukan CVE sebelum penyerang.
      3. Hilangkan kredensial default/lemah + MFA — low-hanging fruit penyerang nomor satu (modul 03/04).
      4. Kurangi eksposur (matikan servis tak perlu, segmentasi) + jalankan non-root (modul 06) → perkecil permukaan & dampak.
      5. Prioritaskan berbasis risiko: CVSS + eksposur internet + eksploitasi aktif (KEV) → tambal yang paling mungkin dipakai.
      6. (Jangan) biarkan servis usang "karena masih jalan" — itu persis kondisi Metasploitable yang jebol di menit pertama.
      ```
      Temuan red = peta prioritas blue. Yang termudah & berdampak dieksploitasi harus ditutup lebih dulu. Patching +
      scanning + hilangkan default + kurangi eksposur menutup sebagian besar jalan masuk yang kamu temukan di lab.
    tests:
      pass:
        - |
          terapkan patching rutin dan manajemen kerentanan karena n-day lama adalah jalan masuk utama
          jalankan scanning kerentanan sca otomatis dan berkala seperti trivy di pipeline
          hilangkan kredensial default dan lemah serta terapkan mfa
          kurangi eksposur matikan servis tak perlu segmentasi dan jalankan non root
          prioritaskan berbasis risiko cvss ditambah eksposur dan eksploitasi aktif kev
          jangan biarkan servis usang karena masih jalan atau belum pernah kena
        - |
          patching rutin plus sca trivy berkala, ganti default creds dan mfa
          kurangi eksposur non root segmentasi, prioritas risk based cvss eksposur kev, jangan biarkan servis usang
      fail:
        - |
          biarkan servis usang karena masih jalan dan belum pernah kena
          tidak perlu patch
        - |
          tambal acak tanpa prioritas
          kredensial default tidak masalah
        - |
          scanning tidak perlu
          servis lama aman saja selama belum diserang
    source: https://www.first.org/cvss/
---

# Analisis Kerentanan

Jembatan antara **enumerasi** (apa yang ada) dan **eksploitasi** (cara masuk): ubah "servis X versi Y" jadi
"exploit Z yang cocok". Modul ini mencocokkan temuan ke kerentanan diketahui, memverifikasinya, dan
memprioritaskannya.

> [!NOTE]
> Modul ini **didokumentasikan dari referensi standar** (Exploit-DB/searchsploit, NVD/CVE, Metasploit) — perintah
> dijalankan **di Kali-mu**, bukan dieksekusi penulis (karena itu `status: draft`). Hanya `nmap --script vuln` yang
> formatnya konsisten dengan nmap terverifikasi di modul 02. Semua pada target milik sendiri.

## 1. Dari versi ke exploit

```text
nmap -sV (modul 02) → "vsftpd 2.3.4"
      │
      ├─ searchsploit vsftpd 2.3.4      → exploit lokal (Exploit-DB)
      ├─ NVD (nvd.nist.gov)             → CVE, versi terdampak, CVSS
      ├─ msfconsole: search vsftpd       → modul Metasploit (search cve:2007-2447)
      └─ nmap --script vuln <ip>         → deteksi otomatis (verifikasi!)
```

`searchsploit -x <id>` **baca** exploit dulu · `searchsploit -m <id>` salin · `searchsploit --update` segarkan DB.

## 2. CVE & CVSS

- **CVE-YYYY-NNNNN** = identifier unik kerentanan publik (bahasa bersama red & blue).
- **CVSS 0.0–10.0** = severity teknis (Low/Medium/High/Critical). Prioritas awal, bukan risiko final.
- **Risiko nyata** = severity × konteks: nilai aset, eksposur internet, **eksploitasi aktif (CISA KEV)**, mitigasi ada.

## 3. Verifikasi (jangan percaya buta)

- Scanner bisa **false-positive/negative** — banner bisa menyesatkan, backdoor tak selalu di tiap build (vsftpd 2.3.4).
- Cocokkan **versi & platform** persis sebelum menjalankan exploit (yang tak cocok gagal/men-crash).
- Bedakan **"potentially vulnerable"** (dugaan scanner) dari **"confirmed exploitable"** (terbukti) di laporan.
- **Baca exploit sebelum menjalankan** (`-x`) — kode publik itu tak tepercaya (bisa berisi backdoor).

## 4. Prioritas (dampak × kemudahan)

Kejar **low-hanging fruit berdampak tinggi** dulu: exploit matang/rank tinggi, kredensial default, misconfig fatal,
CVE critical remote tanpa auth. Metasploitable: MySQL root kosong, vsftpd 2.3.4 backdoor, Samba usermap (CVE-2007-2447),
UnrealIRCd backdoor — semua akses root cepat.

## 5. n-day vs 0-day — pelajaran terbesar

Semua kerentanan Metasploitable adalah **n-day** (diketahui, ada patch, tapi tak ditambal). **Mayoritas kompromi
dunia nyata = n-day lama, bukan 0-day canggih.** Maka pertahanan paling berdampak itu membosankan: **patch your stuff**.

## 6. Purple — jembatan dua-arah

| Red (serang) | Blue (pertahanan — DevSecOps) |
|---|---|
| searchsploit / Exploit-DB | SCA / `trivy image` (05) |
| NVD / CVE lookup | feed CVE, manajemen kerentanan (05/11) |
| CVSS untuk pilih target | CVSS + KEV untuk prioritas patch (05) |
| temuan = jalan masuk | temuan = urutan patch (prioritas pertahanan) |
| versi usang = low-hanging fruit | patching rutin menutupnya |

> [!WARNING]
> Analisis pada target milik sendiri. Jangan jalankan exploit yang belum dibaca/dipahami — bahkan di lab, kode
> exploit tak tepercaya bisa merusak VM-mu atau berisi jebakan. Prinsip supply chain (board DevSecOps 05/11) berlaku
> untuk tool-mu sendiri.

> [!NOTE]
> Lanjut ke modul 05 (Eksploitasi dengan Metasploit) — mengubah kerentanan prioritas jadi akses nyata: alur
> msfconsole (use/set/exploit), payload, dan mendapatkan shell pertama di Metasploitable.
