---
format: 1
id: sec-detection-logging
course:
  slug: devsecops
  title: DevSecOps
  description: Keamanan untuk DevOps dari dua sisi — cara kerja red team dan cara blue team mendeteksi serta menutupnya. Model ancaman, hardening server & container, keamanan web, ketersediaan (anti-DDoS), deteksi, respons insiden, dan keamanan pipeline. Semua praktik di lab terisolasi milik sendiri.
title: Deteksi & Logging
summary: "Pencegahan pasti ada yang lolos — kamu harus bisa melihat serangan saat terjadi. Sisi red: menghindari deteksi dan menghapus jejak (ATT&CK T1070). Sisi blue: logging terstruktur (JSON) yang bisa di-query, log terpusat yang tak bisa dihapus penyerang, deteksi pola serangan dari log (scanning, brute force, lonjakan), deteksi runtime (auditd/Falco) untuk hal yang tak tercatat aplikasi, serta metrik + alert untuk menurunkan waktu deteksi. Query deteksi dibuktikan di lab."
order: 9
version: 1
status: verified
updated: '2026-11-25'
tested_on:
  - Docker Desktop (Windows), Engine 29.4.1, kernel 6.18 (WSL2) — nginx JSON access log + 2 klien (user/attacker) + collector netcat di net dj-sec09
  - Query deteksi (grep/sort/uniq) atas log JSON membedakan IP penyerang dari user; log dikirim ke collector terpisah
  - 'Catatan jujur: auditd tidak tersedia di kernel WSL2 lab ini (auditctl → "Operation not permitted"), jadi auditd & Falco dibahas di level konsep, bukan di-lab'
objectives:
  - id: logging
    text: Menerapkan logging terstruktur (JSON) dan terpusat
  - id: detection
    text: Mendeteksi pola serangan dari log (scanning, brute force, lonjakan)
  - id: integrity
    text: Menjaga integritas log agar penyerang tak bisa menghapus jejak
  - id: runtime
    text: Memahami deteksi runtime (auditd, Falco) sebagai pelengkap log aplikasi
  - id: metrics
    text: Memakai metrik & alerting untuk menurunkan waktu deteksi (MTTD)
  - id: pipeline
    text: Memahami SIEM, korelasi, dan detection-as-code
sources:
  - title: MITRE ATT&CK — Indicator Removal (T1070)
    url: https://attack.mitre.org/techniques/T1070/
  - title: OWASP — Logging Cheat Sheet
    url: https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html
  - title: OWASP — Logging Vocabulary Cheat Sheet
    url: https://cheatsheetseries.owasp.org/cheatsheets/Logging_Vocabulary_Cheat_Sheet.html
  - title: Falco — runtime security
    url: https://falco.org/docs/
  - title: Linux audit (auditd / auditctl)
    url: https://man7.org/linux/man-pages/man8/auditd.8.html
  - title: Google SRE — Monitoring distributed systems (golden signals)
    url: https://sre.google/sre-book/monitoring-distributed-systems/
  - title: Prometheus — alerting rules
    url: https://prometheus.io/docs/prometheus/latest/configuration/alerting_rules/
  - title: Sigma — generic signature format for SIEM
    url: https://github.com/SigmaHQ/sigma
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: logging
    prompt: Mengapa logging penting untuk keamanan (sebutkan satu fungsi utamanya)?
    answers: ['untuk mendeteksi serangan, menyelidiki insiden, dan akuntabilitas']
    patterns: ['deteksi|mendeteksi', 'investigas|selidik|forensik|insiden', 'akuntabilitas|audit trail|jejak|bukti']
    tests:
      fail: ['agar server lebih cepat', 'untuk menghemat disk', 'hanya formalitas', 'supaya aplikasi tampil bagus']
    explanation: 'Tanpa log kamu "buta": tidak tahu serangan sedang/telah terjadi, tidak bisa menyelidiki apa yang dilakukan penyerang, dan tidak punya bukti. Logging mendukung **deteksi** (melihat serangan berlangsung), **investigasi/forensik** (apa yang terjadi, modul 10), dan **akuntabilitas** (siapa melakukan apa). Pencegahan (modul 02–08) pasti ada yang lolos — "assume breach" (modul 01) berarti kamu wajib bisa melihat. Deteksi adalah jaring saat pencegahan gagal.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html

  - id: a02
    tier: A
    type: text
    objective: logging
    prompt: Apa keunggulan logging terstruktur (mis. JSON) dibanding log teks bebas?
    answers: ['bisa di-parse mesin dan di-query/difilter per-field dengan andal']
    patterns: ['(di-?)?parse[^\n]*mesin|machine[- ]?readable', '(query|filter|cari|olah)[^\n]*(field|per-?field|otomatis|andal)', 'terstruktur[^\n]*(query|filter|olah)', 'json[^\n]*(query|parse|field)']
    tests:
      fail: ['lebih pendek', 'lebih indah dibaca manusia saja', 'tidak ada bedanya', 'menghemat cpu']
    explanation: 'Log teks bebas ("User alice gagal login dari 1.2.3.4") susah di-query andal dengan skala. Log **terstruktur** (JSON: `{"event":"login_fail","user":"alice","ip":"1.2.3.4"}`) punya field konsisten yang bisa di-filter, dihitung, dan dikorelasikan mesin. Di lab, nginx dikonfigurasi `log_format json` sehingga tiap baris adalah JSON `{"client":...,"status":...,"uri":...}` — membuat query deteksi (hitung 404 per IP) langsung mungkin. Terstruktur = bisa di-query; itu fondasi deteksi otomatis & SIEM.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Logging_Vocabulary_Cheat_Sheet.html

  - id: a03
    tier: A
    type: text
    objective: logging
    prompt: Apa itu centralized logging dan sebutkan satu alasan utama memakainya?
    answers: ['mengirim log dari banyak host ke satu collector pusat; agar bisa di-query lintas host dan tahan dihapus penyerang']
    patterns: ['(kirim|ship|kumpul|forward)[^\n]*(log)[^\n]*(pusat|collector|terpusat|satu tempat)', '(lintas|semua|banyak) host[^\n]*(satu|pusat|query)', '(tak|tidak|susah)[^\n]*(hapus|ubah)[^\n]*(penyerang|attacker|lokal)']
    tests:
      fail: ['menyimpan log hanya di mesin masing-masing', 'menghapus log lama', 'mematikan logging', 'log di memori saja']
    explanation: 'Centralized logging mengirim log dari semua host/container ke satu **collector/SIEM** pusat. Manfaat besar: (1) **query lintas host** — korelasikan kejadian di banyak mesin; (2) **tahan dihapus** — penyerang yang menguasai satu host tak bisa menghapus log yang sudah terkirim keluar (lihat T1070). Di lab, host web mengirim baris log ke container **collector terpisah** lewat jaringan, dan collector menerimanya — bukti log bisa "keluar dari kotak" yang diserang. Tanpa sentralisasi, bukti bisa hilang bersama host yang jatuh.'
    source: https://attack.mitre.org/techniques/T1070/

  - id: a04
    tier: A
    type: text
    objective: integrity
    prompt: Penyerang menghapus/mengedit log untuk menutupi jejak (ATT&CK T1070). Apa pertahanan utamanya?
    answers: ['kirim log keluar ke collector terpusat / append-only dengan akses terbatas']
    patterns: ['(kirim|ship|forward)[^\n]*(keluar|pusat|collector|remote|off-?box)', 'append[- ]?only|write[- ]?once|worm', '(akses|hak)[^\n]*(terbatas|minimal)[^\n]*log', 'log[^\n]*(remote|terpusat|tak bisa dihapus)']
    tests:
      fail: ['simpan log hanya lokal', 'beri semua orang akses tulis ke log', 'matikan log saat diserang', 'percaya log lokal apa adanya']
    explanation: 'T1070 (Indicator Removal) — penyerang membersihkan `/var/log`, mengosongkan history, memodifikasi log agar tak terlihat. Pertahanan: **kirim log keluar secara real-time** ke collector/SIEM terpusat (begitu terkirim, tak bisa dihapus dari sana), simpan **append-only/WORM**, dan batasi hak tulis/hapus log (user aplikasi tidak boleh menghapus log). Tambahkan sinkronisasi waktu (NTP) agar timestamp lintas host bisa dikorelasikan. Jangan pernah mengandalkan hanya log lokal di mesin yang mungkin dikuasai.'
    source: https://attack.mitre.org/techniques/T1070/

  - id: a05
    tier: A
    type: command
    objective: detection
    prompt: Dari log akses JSON `access.json`, **hitung jumlah baris** yang berstatus `404` (sinyal scanning).
    answers: ['grep -c "status":404 access.json']
    patterns: ['^(?=.*[ \t]-c([ \t]|$))grep[ \t].*404[^\n]*access\.json']
    tests:
      pass:
        - 'grep -c ''"status":404'' access.json'
        - 'grep -c "status\":404" access.json'
      fail:
        - 'grep "status":404 access.json'
        - 'cat access.json'
        - 'grep -v 404 access.json'
    explanation: '`grep -c` menghitung baris yang cocok. Lonjakan 404 (banyak request ke path yang tak ada) adalah sinyal **scanning/enumerasi**: penyerang menebak `/admin`, `/.env`, `/wp-login.php`, dll. Di lab, query ini atas log JSON menemukan **12** permintaan 404 dari IP penyerang dan **0** dari user sah — pemisahan yang jelas. Log terstruktur membuat query seperti ini sederhana dan andal; itulah inti "deteksi dari log".'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html

  - id: a06
    tier: A
    type: text
    objective: detection
    prompt: Banyak request 404 ke path aneh (`/.env`, `/wp-login.php`, `/phpmyadmin`) dari satu IP menandakan apa?
    answers: ['scanning / enumerasi — penyerang mencari path & kerentanan yang bisa dimasuki']
    patterns: ['scanning|pemindaian|enumeras', '(cari|mencari|nebak|tebak|probe)[^\n]*(path|endpoint|kerentanan|celah)', 'recon|reconnaissance']
    tests:
      fail: ['user biasa salah ketik', 'server lambat', 'cache penuh', 'koneksi putus']
    explanation: 'Ini pola **scanning/enumerasi** (bersambung dengan modul 02 Recon): bot/alat otomatis mencoba daftar path umum untuk menemukan file sensitif atau aplikasi rentan (WordPress, phpMyAdmin, `.env`, `.git`). Di lab, URI 404 teratas persis seperti ini (`/xmlrpc.php`, `/wp-login.php`, `/shell.php`, `/phpmyadmin`, ...). Mendeteksinya dini memberi peringatan sebelum penyerang menemukan sesuatu yang berhasil — dan bisa memicu blokir otomatis (fail2ban, modul 03).'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html

  - id: a07
    tier: A
    type: text
    objective: detection
    prompt: Banyak percobaan login gagal berturut-turut dari satu IP menandakan serangan apa?
    answers: ['brute force / credential stuffing']
    patterns: ['brute[- ]?force', 'credential stuffing', '(tebak|coba)[^\n]*(password|kata sandi|kredensial)[^\n]*(berulang|banyak)', 'password spraying']
    tests:
      fail: ['user lupa password sekali', 'serangan ddos murni', 'sql injection', 'phishing']
    explanation: 'Banyak kegagalan login beruntun = **brute force** (menebak password satu akun berulang) atau **credential stuffing** (mencoba banyak kombinasi bocoran). Di lab, IP penyerang menghantam `/login` **20 kali** sementara user sah hanya **5** — selisih yang jelas terlihat di log. Sinyal ini memicu kontrol modul 03 (fail2ban, MaxAuthTries) dan modul 08 (rate limit). Catat login **gagal maupun berhasil**: login berhasil setelah 100 gagal = kemungkinan akun jebol.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html

  - id: a08
    tier: A
    type: command
    objective: logging
    prompt: Tampilkan log (stdout/stderr) dari container bernama `web`.
    answers: ['docker logs web']
    patterns: ['^docker logs( [^\n]*)? web$', '^docker logs web$']
    tests:
      pass:
        - 'docker logs web'
        - 'docker logs -f web'
        - 'docker logs --tail 100 web'
      fail:
        - 'docker ps web'
        - 'cat web'
        - 'docker exec web logs'
    explanation: '`docker logs <container>` menampilkan stdout/stderr yang ditangkap driver log (default `json-file`). Di lab, log mentah container tersimpan di host pada `/var/lib/docker/containers/<id>/<id>-json.log`. **Penting:** itu lokal — bisa hilang jika container dihapus atau host dikuasai. Untuk produksi, kirim ke collector (log driver `syslog`/`fluentd`/`gelf`, atau agen seperti Fluent Bit/Vector) agar terpusat & tahan hapus. `docker logs -f` mengikuti real-time.'
    source: https://docs.docker.com/engine/logging/

  - id: a09
    tier: A
    type: text
    objective: runtime
    prompt: Framework audit kernel Linux yang mencatat panggilan syscall & akses file (mis. siapa membaca `/etc/shadow`) bernama apa?
    answers: ['auditd']
    patterns: ['auditd', 'linux audit', 'auditctl', 'audit framework']
    tests:
      fail: ['syslog', 'journald', 'logrotate', 'cron']
    explanation: '`auditd` (Linux Audit) adalah subsistem kernel yang mencatat event tingkat-OS: akses file yang diawasi (`auditctl -w /etc/shadow -p wa`), eksekusi perintah, perubahan konfigurasi, panggilan syscall. Ia melihat hal yang **tak tercatat log aplikasi** — mis. proses membaca file sensitif atau memodifikasi biner sistem. (Catatan lab: kernel WSL2 di sini menolak auditd — `auditctl` memberi "Operation not permitted" — jadi di modul ini auditd dijelaskan di level konsep. Di server Linux sungguhan ia aktif di host.)'
    source: https://man7.org/linux/man-pages/man8/auditd.8.html

  - id: a10
    tier: A
    type: text
    objective: runtime
    prompt: Alat open-source deteksi ancaman runtime yang memantau syscall container/host (mis. "shell dibuka di dalam container") bernama apa?
    answers: ['falco']
    patterns: ['falco']
    tests:
      fail: ['trivy', 'cosign', 'fail2ban', 'prometheus']
    explanation: 'Falco (CNCF) memantau syscall secara real-time (lewat eBPF/kernel module) dan memicu alert saat perilaku mencurigakan terjadi: **shell interaktif di dalam container**, membaca file sensitif, menulis ke direktori biner, koneksi keluar tak terduga, atau menjalankan proses tak dikenal. Ini melengkapi log aplikasi & WAF: ia menangkap tahap **pasca-eksploit** (apa yang dilakukan penyerang setelah masuk) yang sering tak muncul di log HTTP. Aturannya ditulis sebagai kode (detection-as-code) dan bisa di-versioning.'
    source: https://falco.org/docs/

  - id: a11
    tier: A
    type: text
    objective: metrics
    prompt: Apa beda mendasar antara log dan metrik (metrics)?
    answers: ['log = catatan event diskret; metrik = angka teragregasi dari waktu ke waktu']
    patterns: ['log[^\n]*(event|kejadian|baris|diskret|peristiwa)', 'metri(k|c)[^\n]*(angka|agregat|nilai|time ?series|deret waktu|ukur)', '(event|kejadian)[^\n]*(vs|versus|sedangkan|sementara)[^\n]*(angka|agregat|metrik)']
    tests:
      fail: ['sama saja', 'metrik adalah log yang dihapus', 'log lebih cepat dari metrik', 'keduanya hanya teks']
    explanation: 'Keduanya sinyal berbeda yang saling melengkapi. **Log** = catatan event diskret ("request X dari IP Y jam Z, status 404") — detail, bagus untuk investigasi. **Metrik** = angka teragregasi sepanjang waktu (deret waktu: "request/detik", "rasio 5xx", "CPU%") — ringkas, bagus untuk dashboard & alert cepat. Deteksi yang baik memakai keduanya: metrik memberi tahu *bahwa* ada yang aneh (lonjakan error), log memberi tahu *apa* persisnya. (Ada juga **trace** untuk melacak satu request lintas layanan.)'
    source: https://sre.google/sre-book/monitoring-distributed-systems/

  - id: a12
    tier: A
    type: text
    objective: metrics
    prompt: "Sebutkan minimal dua dari \"empat golden signals\" monitoring (Google SRE)."
    answers: ['latency, traffic, errors, saturation']
    patterns: ['latency|latensi', 'traffic|trafik', 'error', 'saturation|saturasi|jenuh']
    tests:
      fail: ['warna, ukuran, berat, harga', 'cpu saja', 'jumlah user login', 'kecepatan disk saja']
    explanation: 'Empat golden signals: **Latency** (waktu respons), **Traffic** (permintaan/detik), **Errors** (rasio kegagalan), **Saturation** (seberapa penuh resource). Memantau keempatnya memberi gambaran kesehatan & sinyal serangan: lonjakan traffic + errors + saturation = kemungkinan DDoS (modul 08); lonjakan errors tanpa traffic naik = kemungkinan eksploit/bug. Alert yang di-set pada ambang signal ini mendeteksi masalah jauh sebelum user mengeluh.'
    source: https://sre.google/sre-book/monitoring-distributed-systems/

  - id: a13
    tier: A
    type: text
    objective: metrics
    prompt: Singkatan MTTD (yang ingin diturunkan dengan monitoring & alerting) adalah?
    answers: ['mean time to detect — rata-rata waktu untuk mendeteksi']
    patterns: ['mean time to detect', 'waktu[^\n]*(untuk )?(deteksi|mendeteksi)', 'rata-?rata[^\n]*deteksi']
    tests:
      fail: ['maximum transfer time daily', 'multi thread detection daemon', 'mean time to deploy', 'minimum time to delete']
    explanation: 'MTTD = **Mean Time To Detect**, rata-rata waktu dari serangan mulai sampai kamu menyadarinya. "Dwell time" penyerang yang panjang (berminggu/berbulan tak terdeteksi) adalah mimpi buruk — makin lama, makin besar kerusakan & penyebaran. Monitoring + alerting menurunkan MTTD dari "pelanggan yang memberi tahu kami" (buruk) jadi "alert otomatis dalam menit". Pasangannya MTTR (Mean Time To Respond/Recover) di modul 10. Deteksi cepat = kerusakan kecil.'
    source: https://sre.google/sre-book/monitoring-distributed-systems/

  - id: a14
    tier: A
    type: command
    objective: detection
    prompt: Ikuti (pantau real-time) isi file log `/var/log/nginx/access.json` saat baris baru ditambahkan.
    answers: ['tail -f /var/log/nginx/access.json']
    patterns: ['^(?=.*[ \t]-f([ \t]|$))tail[ \t].*/var/log/nginx/access\.json', '^(?=.*[ \t]-F([ \t]|$))tail[ \t].*access\.json']
    tests:
      pass:
        - 'tail -f /var/log/nginx/access.json'
        - 'tail -n 50 -f /var/log/nginx/access.json'
      fail:
        - 'tail /var/log/nginx/access.json'
        - 'cat /var/log/nginx/access.json'
        - 'head -f /var/log/nginx/access.json'
    explanation: '`tail -f` ("follow") menampilkan baris log baru secara real-time — berguna untuk memantau serangan saat berlangsung atau mengawasi efek perubahan. Untuk investigasi interaktif ini cepat; tapi pemantauan serius butuh alat yang mem-parse & memberi alert otomatis (tidak ada manusia menatap `tail` 24/7). `-F` (kapital) juga mengikuti meski file di-rotate. Gabungkan dengan `grep` untuk menyaring: `tail -f ... | grep 404`.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html

  - id: a15
    tier: A
    type: text
    objective: pipeline
    prompt: Sistem terpusat yang mengumpulkan, mengkorelasikan, dan memberi alert atas log keamanan dari banyak sumber disebut apa?
    answers: ['siem']
    patterns: ['\bsiem\b', 'security information (and|&) event management']
    tests:
      fail: ['cdn', 'waf', 'vpn', 'orm']
    explanation: 'SIEM (Security Information and Event Management) — mis. Elastic/ELK, Wazuh, Splunk, Loki — mengumpulkan log dari aplikasi, server, container, firewall, lalu **mengkorelasikan** dan memberi **alert** atas pola mencurigakan lintas sumber. Kekuatannya korelasi: WAF 403 + lonjakan 5xx + kegagalan login dari IP yang sama = satu insiden terkoordinasi, bukan tiga anomali terpisah. SIEM adalah "pusat deteksi" tempat centralized logging (a03) bermuara.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: command
    objective: detection
    prompt: Cari semua percobaan login SSH gagal di `/var/log/auth.log` (baris berisi `Failed password`).
    answers: ['grep "Failed password" /var/log/auth.log']
    patterns: ['^grep[ \t].*Failed password[^\n]*/var/log/auth\.log']
    tests:
      pass:
        - 'grep "Failed password" /var/log/auth.log'
        - 'grep -i "Failed password" /var/log/auth.log'
      fail:
        - 'cat /var/log/auth.log'
        - 'grep "Accepted password" /var/log/auth.log'
        - 'tail /var/log/auth.log'
    explanation: 'Baris `Failed password for <user> from <IP>` (dari modul 03) adalah sinyal brute force paling langsung. `grep` menyaringnya dari auth.log. Ini langkah pertama triase: siapa yang diserang, dari IP mana, seberapa sering. Lanjutkan dengan menghitung per-IP untuk menemukan penyerang teratas (b02). Di produksi, alirkan ke SIEM yang memberi alert otomatis saat laju kegagalan melewati ambang.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html

  - id: b02
    tier: B
    type: command
    objective: detection
    prompt: Dari file `ips.txt` (satu IP per baris), tampilkan **IP yang paling sering muncul beserta jumlahnya** (idiom hitung-lalu-urutkan).
    answers: ['sort ips.txt | uniq -c | sort -rn']
    patterns: ['^(?=.*uniq[ \t]+-c)(?=.*sort[ \t]+-(rn|nr))sort[ \t]+ips\.txt[^\n]*uniq[ \t]+-c']
    tests:
      pass:
        - 'sort ips.txt | uniq -c | sort -rn'
        - 'sort ips.txt | uniq -c | sort -nr'
      fail:
        - 'uniq -c ips.txt'
        - 'sort ips.txt'
        - 'cat ips.txt | uniq'
    explanation: 'Idiom klasik analisis log: `sort | uniq -c | sort -rn`. `sort` mengelompokkan baris sama berdampingan, `uniq -c` menghitung tiap grup, `sort -rn` mengurutkan menurun berdasarkan jumlah → penyerang teratas muncul di baris pertama. Di lab, teknik serupa atas log JSON memisahkan IP penyerang (12 request 404) dari user (0). `uniq -c` **wajib** didahului `sort` (ia hanya menggabung baris yang bersebelahan). Ini "SIEM mini" dengan alat Unix.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html

  - id: b03
    tier: B
    type: text
    objective: detection
    prompt: Mengapa deteksi berbasis anomali memerlukan "baseline" lebih dulu?
    answers: ['harus tahu apa yang normal agar bisa mengenali yang abnormal']
    patterns: ['(tahu|kenali|paham)[^\n]*normal[^\n]*(abnormal|anomali|aneh|menyimpang)', 'baseline[^\n]*(normal|bandingkan|acuan)', '(tanpa|kalau tidak)[^\n]*normal[^\n]*(tak|tidak)[^\n]*(tahu|kenali)']
    tests:
      fail: ['baseline tidak diperlukan', 'anomali selalu jelas tanpa konteks', 'cukup blokir semua', 'normal dan abnormal sama saja']
    explanation: 'Deteksi anomali = "ini menyimpang dari biasanya". Tanpa **baseline** (profil trafik/perilaku normal), kamu tak bisa membedakan lonjakan serangan dari lonjakan sah (promo, viral). Baseline membuat alert bermakna: "500 login/menit" mencurigakan hanya jika normalnya 5. Karena itu ukur dulu pola normal (seperti saat tuning rate limit, modul 08), lalu set ambang di atasnya. Baseline juga membuat alert lebih sedikit tapi lebih akurat — mengurangi alert fatigue (b08).'
    source: https://sre.google/sre-book/monitoring-distributed-systems/

  - id: b04
    tier: B
    type: text
    objective: logging
    prompt: Apa yang TIDAK boleh ikut tercatat ke dalam log?
    answers: ['rahasia/kredensial seperti password, token, dan data sensitif/pii']
    patterns: ['password|kata sandi', 'token|api key|kunci|secret|rahasia|kredensial', 'pii|data (pribadi|sensitif)|kartu kredit|nomor kartu']
    tests:
      fail: ['waktu kejadian', 'alamat ip', 'kode status http', 'nama endpoint']
    explanation: 'Log sering dibaca banyak orang & dikirim ke sistem lain (SIEM, pihak ketiga) — jadi **jangan pernah mencatat rahasia**: password, token/API key, session cookie, atau data sensitif/PII (nomor kartu, data kesehatan). Mencatatnya membuat log jadi sasaran bernilai tinggi dan bisa melanggar regulasi (GDPR/PCI). Bersambung dengan modul 04 (secrets): rahasia tak boleh bocor, termasuk lewat log. Catat *peristiwa* (login gagal untuk user X dari IP Y), bukan *rahasianya* (password yang dicoba).'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html

  - id: b05
    tier: B
    type: text
    objective: integrity
    prompt: Selain mengirim log keluar, sebutkan satu kontrol lain untuk menjaga log bisa dipercaya saat investigasi.
    answers: ['sinkronisasi waktu (ntp) agar timestamp lintas host konsisten; dan penyimpanan append-only/akses terbatas']
    patterns: ['ntp|sinkron(isasi)? waktu|time ?sync|jam[^\n]*(selaras|sama|konsisten)', 'append[- ]?only|worm|write[- ]?once', '(akses|hak)[^\n]*(terbatas|minimal|read-?only)', 'tanda tangan|hash|integritas[^\n]*log']
    tests:
      fail: ['biarkan tiap server punya jam berbeda', 'beri akses hapus ke semua', 'simpan tanpa timestamp', 'log boleh diedit siapa saja']
    explanation: 'Agar log bisa dipercaya: (1) **sinkronisasi waktu (NTP)** di semua host — tanpa jam yang selaras, kamu tak bisa mengurutkan kejadian lintas mesin saat korelasi/forensik; (2) **append-only/WORM** + akses tulis-hapus yang dibatasi (user aplikasi tak boleh menghapus log); (3) opsional **hashing/signing** untuk membuktikan log tak diubah. Kombinasi ini melawan T1070 dan memastikan timeline insiden (modul 10) akurat. Log yang timestamp-nya kacau atau bisa diedit bebas = bukti yang lemah.'
    source: https://attack.mitre.org/techniques/T1070/

  - id: b06
    tier: B
    type: text
    objective: runtime
    prompt: Mengapa deteksi runtime (auditd/Falco) diperlukan padahal sudah ada log aplikasi & WAF?
    answers: ['menangkap aktivitas pasca-eksploit tingkat-os (shell, baca file sensitif, koneksi keluar) yang tak muncul di log aplikasi']
    patterns: ['(pasca|setelah|post)[- ]?(eksploit|exploit|masuk)', '(syscall|os|kernel|sistem)[^\n]*(aktivitas|perilaku|level)', '(tak|tidak)[^\n]*(muncul|tercatat|terlihat)[^\n]*(log )?aplikasi', 'shell|reverse shell|baca[^\n]*(shadow|sensitif)']
    tests:
      fail: ['runtime detection tidak berguna', 'log aplikasi mencatat semua syscall', 'waf melihat aktivitas os', 'cukup log http saja']
    explanation: 'Log aplikasi & WAF melihat **lapisan HTTP** — request masuk. Tapi setelah penyerang mendapat pijakan (mis. lewat RCE), aktivitasnya pindah ke **tingkat OS**: membuka shell, membaca `/etc/shadow`, mengunduh tool, membuat koneksi keluar (reverse shell), memodifikasi biner. Itu **tak muncul** di log HTTP. auditd/Falco memantau syscall sehingga menangkap tahap pasca-eksploit ini. Defense-in-depth deteksi: HTTP log (apa yang masuk) + runtime (apa yang dilakukan di dalam).'
    source: https://falco.org/docs/

  - id: b07
    tier: B
    type: command
    objective: detection
    prompt: Dari `access.json`, ekstrak **semua nilai field client** (IP) saja — satu per kecocokan — dengan grep.
    answers: ['grep -oE "client":"[0-9.]+" access.json']
    patterns: ['^(?=.*[ \t]-[A-Za-z]*o[A-Za-z]*([ \t]|$|E))grep[ \t].*client[^\n]*access\.json']
    tests:
      pass:
        - 'grep -oE ''"client":"[0-9.]+"'' access.json'
        - 'grep -o ''"client":"[0-9.]*"'' access.json'
      fail:
        - 'grep "client" access.json'
        - 'cat access.json'
        - 'grep -v client access.json'
    explanation: '`grep -o` ("only matching") mencetak hanya bagian yang cocok (bukan seluruh baris), dan `-E` mengaktifkan regex extended. `grep -oE ''"client":"[0-9.]+"''` mengeluarkan setiap nilai client, yang lalu bisa di-pipe ke `sort | uniq -c | sort -rn` (b02) untuk menghitung per-IP. Di lab, teknik ini membangun "SIEM mini": ekstrak field → kelompokkan → hitung → urutkan. Dengan log terstruktur, ekstraksi field jadi mudah; `jq -r .client access.json` melakukan hal serupa jika log satu-objek-per-baris.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html

  - id: b08
    tier: B
    type: text
    objective: metrics
    prompt: Apa itu "alert fatigue" dan bagaimana menguranginya?
    answers: ['terlalu banyak alert/noise membuat tim mengabaikannya; kurangi dengan tuning ambang, severity, dan dedupe']
    patterns: ['(terlalu banyak|banjir|noise|berisik)[^\n]*(alert|notifikasi)', '(abai|lewat|diabaikan|lelah|numb)[^\n]*(alert|tim)', '(tuning|setel|severity|prioritas|dedup|group|kurangi false)[^\n]*(alert|ambang)']
    tests:
      fail: ['alert sebanyak mungkin selalu baik', 'matikan semua alert', 'alert fatigue tidak nyata', 'abaikan saja semuanya']
    explanation: 'Alert fatigue: saat alert terlalu banyak/berisik/false-positive, tim mulai mengabaikannya — lalu **alert yang benar-benar penting ikut terlewat** (persis skenario insiden yang lolos). Kurangi dengan: set ambang dari baseline (b03), beri **severity** dan rutekan (kritis → telepon, rendah → dashboard), **dedupe/group** alert terkait (Alertmanager), buat tiap alert **actionable** (apa artinya, apa yang dilakukan, tautan runbook), dan tinjau/iterasi berkala. Sedikit alert bermakna > banyak alert diabaikan.'
    source: https://sre.google/sre-book/monitoring-distributed-systems/

  - id: b09
    tier: B
    type: text
    objective: metrics
    prompt: Dalam tumpukan Prometheus, komponen apa yang mengevaluasi aturan ambang lalu mengirim notifikasi (email/Slack/PagerDuty)?
    answers: ['alertmanager (prometheus mengevaluasi rule, alertmanager merutekan notifikasi)']
    patterns: ['alert ?manager', 'prometheus[^\n]*(rule|aturan|evaluasi)[^\n]*alert ?manager', 'alert ?manager[^\n]*(rute|kirim|notifikasi)']
    tests:
      fail: ['grafana saja', 'node exporter', 'nginx', 'kibana']
    explanation: 'Prometheus (model **pull**: ia men-scrape metrik dari target) mengevaluasi **alerting rules** (mis. `rate(http_5xx[5m]) > 0.1`); saat rule menyala, ia mengirim alert ke **Alertmanager**, yang menangani deduplikasi, grouping, silencing, dan **routing** ke penerima (email/Slack/PagerDuty). Pemisahan ini penting: Prometheus memutuskan *apa* yang abnormal, Alertmanager memutuskan *siapa* diberi tahu & *bagaimana*. Grafana biasanya untuk visualisasi dashboard di atas data yang sama.'
    source: https://prometheus.io/docs/prometheus/latest/configuration/alerting_rules/

  - id: b10
    tier: B
    type: text
    objective: pipeline
    prompt: Apa itu "detection as code" dan apa manfaatnya?
    answers: ['aturan deteksi ditulis sebagai kode di version control, bisa di-review, diuji, dan di-deploy seperti kode']
    patterns: ['(aturan|rule) deteksi[^\n]*(kode|file|version control|git)', '(version control|git|repo)[^\n]*(review|uji|test|deploy)', 'detection[- ]as[- ]code', 'rule[^\n]*(di-?review|diuji|di-?versioning)']
    tests:
      fail: ['aturan deteksi dibuat manual di ui tanpa jejak', 'tidak perlu version control', 'deteksi tidak bisa diuji', 'kode dan deteksi tidak berhubungan']
    explanation: 'Detection-as-code memperlakukan aturan deteksi (rule Falco, query SIEM, aturan Sigma) sebagai **kode**: disimpan di version control, di-review lewat PR, **diuji** (apakah menangkap serangan contoh? apakah false-positive pada trafik normal?), dan di-deploy lewat pipeline (modul 11). Manfaat: aturan konsisten, terlacak (siapa mengubah apa & kapan), bisa di-rollback, dan tak tergantung orang yang "tahu caranya di UI". Ini membawa disiplin DevOps ke deteksi keamanan.'
    source: https://github.com/SigmaHQ/sigma

  - id: b11
    tier: B
    type: text
    objective: pipeline
    prompt: Sigma adalah format aturan deteksi yang bersifat "vendor-neutral". Apa keuntungannya?
    answers: ['satu aturan ditulis sekali lalu dikonversi ke berbagai siem, tak terkunci satu vendor']
    patterns: ['(satu|sekali)[^\n]*(aturan|rule)[^\n]*(berbagai|banyak|macam)[^\n]*(siem|backend|platform)', 'vendor[- ]?neutral|tidak terkunci|vendor lock', 'konversi|portable|dibagikan[^\n]*(komunitas|lintas)']
    tests:
      fail: ['hanya untuk satu siem tertentu', 'tidak bisa dibagikan', 'mengunci ke satu vendor', 'format biner rahasia']
    explanation: 'Sigma adalah format generik (YAML) untuk aturan deteksi log. Keuntungan: tulis aturan **sekali**, lalu konversi ke query SIEM apa pun (Elastic, Splunk, Loki, Sentinel) — tidak terkunci satu vendor. Komunitas berbagi aturan Sigma untuk teknik ATT&CK umum, jadi kamu bisa memakai deteksi matang tanpa menulis dari nol. Bersama Falco (runtime) dan aturan WAF, Sigma (log) adalah bagian dari pustaka deteksi yang di-maintain sebagai kode (b10).'
    source: https://github.com/SigmaHQ/sigma

  - id: b12
    tier: B
    type: command
    objective: detection
    prompt: Pada sistem systemd, tampilkan log layanan SSH (`ssh`) lewat journald.
    answers: ['journalctl -u ssh']
    patterns: ['^journalctl[ \t].*-u[ \t]+ssh(d)?', '^journalctl[ \t].*-u[ \t]+ssh']
    tests:
      pass:
        - 'journalctl -u ssh'
        - 'journalctl -u sshd'
        - 'journalctl -u ssh -f'
      fail:
        - 'systemctl status ssh'
        - 'cat /var/log/ssh'
        - 'journalctl'
    explanation: '`journalctl -u <service>` menampilkan log sebuah unit systemd dari journald (log biner terpusat per-host systemd). `journalctl -u ssh` (atau `sshd`) menyaring event SSH termasuk kegagalan autentikasi. Tambah `-f` untuk follow, `--since "1 hour ago"` untuk rentang waktu, `-p err` untuk level. journald adalah logging **per-host**; untuk sentralisasi, forward ke collector (systemd-journal-remote, rsyslog, atau agen). Berguna saat triase cepat di satu server.'
    source: https://man7.org/linux/man-pages/man1/journalctl.1.html

  - id: b13
    tier: B
    type: text
    objective: logging
    prompt: Mengapa retensi log (menyimpan cukup lama) penting untuk keamanan?
    answers: ['penyerang sering tak terdeteksi berminggu/bulan (dwell time); log lama diperlukan untuk menyelidiki ke belakang']
    patterns: ['dwell time|tak terdeteksi[^\n]*(lama|minggu|bulan)', '(selidik|investigasi|forensik)[^\n]*(ke belakang|lampau|lama|historis)', '(retensi|simpan)[^\n]*(cukup lama|panjang|historis|berbulan|berminggu)[^\n]*(investigasi|insiden|selidik)']
    tests:
      fail: ['log cukup disimpan satu jam', 'retensi tidak penting', 'hapus log setiap hari', 'semakin sedikit history semakin baik']
    explanation: 'Breach sering baru ketahuan berminggu atau berbulan setelah penyerang masuk (dwell time panjang). Saat insiden terdeteksi, investigasi harus menengok **ke belakang** — kapan masuk? apa yang diakses? Jika log sudah dihapus, timeline tak bisa direkonstruksi. Karena itu simpan log cukup lama (sesuai kebutuhan & regulasi; keamanan sering butuh berbulan). Seimbangkan dengan biaya penyimpanan: log panas (cepat, pendek) + log dingin/arsip (murah, panjang). Retensi terlalu pendek = buta terhadap serangan lambat.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html

  - id: b14
    tier: B
    type: text
    objective: runtime
    prompt: Apa yang dilakukan File Integrity Monitoring (FIM, mis. AIDE/Tripwire) dan kenapa berguna?
    answers: ['memantau perubahan file sistem penting dan memberi alert bila berubah tak terduga (webshell, biner diganti)']
    patterns: ['(pantau|deteksi|monitor)[^\n]*(perubahan|ubah|modifikasi)[^\n]*(file|biner|sistem)', '(alert|peringatan)[^\n]*(file|berubah|dimodifikasi)', 'webshell|biner (diganti|dimodifikasi)|checksum|hash file']
    tests:
      fail: ['mempercepat file sistem', 'mengenkripsi semua file', 'menghapus file lama', 'mengatur izin file saja']
    explanation: 'FIM menyimpan "sidik jari" (hash) file sistem penting (biner, konfigurasi, direktori web) lalu memeriksanya berkala; jika berubah tak terduga ia memberi alert. Berguna menangkap tanda kompromi: **webshell** baru di direktori web, biner sistem diganti (rootkit), atau konfigurasi dirusak. Ini deteksi berbasis **integritas** (modul 01: huruf I di CIA) — melengkapi deteksi berbasis perilaku (Falco) dan log. Di pipeline container, pendekatan setara: image read-only (modul 06) + bandingkan runtime dengan image asli.'
    source: https://falco.org/docs/

  - id: b15
    tier: B
    type: text
    objective: pipeline
    prompt: Beri contoh korelasi lintas-sumber yang mengubah beberapa anomali kecil menjadi satu deteksi insiden yang kuat.
    answers: ['waf 403 + lonjakan 5xx + banyak login gagal dari ip yang sama = serangan terkoordinasi']
    patterns: ['(waf|403)[^\n]*(5xx|login|gagal|scanning)', '(ip|sumber)[^\n]*(sama)[^\n]*(beberapa|banyak|lintas)', 'korelasi[^\n]*(sumber|log|sinyal)[^\n]*(insiden|serangan)']
    tests:
      fail: ['satu log saja sudah cukup selalu', 'korelasi tidak berguna', 'anomali tidak perlu digabung', 'abaikan sinyal dari sumber lain']
    explanation: 'Masing-masing bisa terlihat kecil: beberapa 403 WAF (modul 07), sedikit kenaikan 5xx, beberapa login gagal (modul 03). Tapi bila **dari IP/rentang yang sama dalam jendela waktu yang sama**, korelasinya mengungkap satu serangan terkoordinasi (scan → eksploit → brute). SIEM (a15) ada untuk ini: menggabungkan sinyal lintas WAF, app, auth, dan runtime jadi satu alert insiden ber-konteks, bukan puluhan alert terpisah yang mudah diabaikan. Deteksi kuat lahir dari korelasi, bukan satu sumber.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: logging
    prompt: |
      Kamu menyiapkan logging untuk aplikasi web ber-container agar tim bisa mendeteksi & menyelidiki serangan.
      Rancang strategi logging yang baik (satu poin per baris).
    checklist:
      - id: structured
        label: Logging terstruktur (JSON) agar bisa di-query per-field
        required: true
        patterns: ['terstruktur|structured', 'json', 'per-?field|bisa di-?query']
      - id: central
        label: Kirim log ke collector/SIEM terpusat (keluar dari host)
        required: true
        patterns: ['(kirim|ship|forward|kumpul)[^\n]*(collector|siem|pusat|terpusat|keluar)', 'centralized|sentralisasi', 'log driver|fluent|vector|rsyslog|loki']
      - id: nosecrets
        label: Jangan log rahasia/PII (password, token, data sensitif)
        patterns: ['(jangan|tidak|hindari)[^\n]*(log|catat)[^\n]*(password|token|rahasia|secret|pii|sensitif)', 'redaksi|mask[^\n]*(password|token|pii)']
      - id: timesync
        label: Sinkronisasi waktu (NTP) + timestamp konsisten untuk korelasi
        patterns: ['ntp|sinkron(isasi)? waktu|time ?sync', 'timestamp[^\n]*(konsisten|utc|selaras)']
      - id: retention
        label: Retensi memadai + rotasi (cukup lama untuk investigasi)
        patterns: ['retensi|simpan[^\n]*(lama|cukup)', 'rotasi|rotate', 'arsip']
      - id: integrity
        label: Append-only / akses tulis-hapus dibatasi (anti T1070)
        patterns: ['append[- ]?only|worm', '(akses|hak)[^\n]*(terbatas|minimal|read-?only)[^\n]*log', 'tak bisa (dihapus|diubah)[^\n]*(penyerang|lokal)']
      - id: alert
        label: Alert atas sinyal kunci (brute force, lonjakan 4xx/5xx, scanning)
        patterns: ['alert[^\n]*(brute|404|4xx|5xx|scanning|login gagal|anomali)', '(monitor|pantau)[^\n]*(sinyal|serangan|anomali)']
      - id: no-localonly
        label: Tidak menyimpan log hanya lokal di container/host tanpa dikirim keluar
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain|juga))[^\n]*(log|simpan)[^\n]*(hanya|cuma)[^\n]*(lokal|di (host|container|mesin))[^\n]*(saja|cukup)']
    pass_score: 0.5
    solution: |
      ```text
      1. Logging terstruktur (JSON) — field konsisten (client, status, uri, event) agar bisa di-query/korelasi
         (lab: log_format json → query 404-per-IP langsung jalan).
      2. Kirim ke collector/SIEM terpusat (log driver syslog/fluentd/gelf atau agen Fluent Bit/Vector) — keluar dari
         host (lab: host web → collector terpisah menerima log).
      3. Jangan log rahasia/PII (password, token, data sensitif) — redaksi bila perlu (modul 04).
      4. NTP + timestamp UTC konsisten → korelasi lintas host akurat.
      5. Retensi memadai + rotasi (panas pendek + arsip panjang) untuk investigasi dwell-time panjang.
      6. Append-only + akses tulis-hapus dibatasi (user app tak boleh menghapus log) → anti T1070.
      7. Alert atas sinyal kunci: brute force, lonjakan 4xx/5xx (scanning), login sukses setelah banyak gagal.
      ```
      Prinsip: log harus TERSTRUKTUR (bisa di-query), TERPUSAT (keluar dari host yang mungkin dikuasai), BERSIH
      (tanpa rahasia), dan TERPERCAYA (waktu selaras, tak bisa dihapus). Log yang hanya lokal hilang bersama host.
    tests:
      pass:
        - |
          pakai logging terstruktur json agar bisa di-query per field
          kirim log ke collector atau siem terpusat keluar dari host
          jangan log password token atau data sensitif pii
          sinkronisasi waktu ntp dan timestamp konsisten untuk korelasi
          retensi memadai dan rotasi agar cukup lama untuk investigasi
          simpan append-only dengan akses tulis hapus dibatasi anti t1070
          alert atas brute force lonjakan 4xx 5xx dan scanning
          jangan simpan log hanya lokal di host tanpa dikirim keluar
        - |
          json terstruktur, forward ke siem terpusat via fluent bit
          redaksi rahasia, ntp selaras, retensi panjang, append only
          alert login gagal beruntun dan spike 5xx
      fail:
        - |
          simpan log hanya lokal di container saja sudah cukup
          log juga password yang dicoba biar lengkap
        - |
          matikan log kalau disk penuh
          tidak perlu kirim ke mana-mana
        - |
          biarkan tiap server jam berbeda
          siapa saja boleh hapus log
    source: https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html

  - id: c02
    tier: C
    type: scenario
    objective: runtime
    prompt: |
      Kamu curiga ada upaya pembobolan dan ingin memastikan punya cakupan deteksi berlapis — bukan hanya satu sumber.
      Rancang cakupan deteksi lintas lapisan (satu poin per baris).
    checklist:
      - id: applogs
        label: Log aplikasi/akses untuk scanning & brute force (4xx, login gagal)
        required: true
        patterns: ['(log|akses)[^\n]*(scanning|brute|4xx|404|login gagal)', '(deteksi|pantau)[^\n]*(http|aplikasi|akses)']
      - id: runtime
        label: Deteksi runtime (auditd/Falco) untuk syscall/shell/baca file sensitif
        required: true
        patterns: ['auditd|falco', 'runtime[^\n]*(deteksi|syscall)', '(syscall|shell|os)[^\n]*(pantau|deteksi|runtime)']
      - id: metrics
        label: Metrik + alert atas anomali (lonjakan error/traffic/saturation)
        patterns: ['metri(k|c)[^\n]*(alert|anomali|lonjakan)', 'golden signal|prometheus|alert[^\n]*(error|traffic|5xx)']
      - id: fim
        label: File Integrity Monitoring (webshell/biner berubah)
        patterns: ['file integrity|fim|aide|tripwire', '(perubahan|ubah)[^\n]*(file|biner|sistem)', 'webshell']
      - id: central
        label: Centralize + korelasi di SIEM (gabungkan sinyal lintas sumber)
        patterns: ['siem|centralize|terpusat', 'korelasi[^\n]*(sumber|sinyal|log)']
      - id: baseline
        label: Tetapkan baseline normal agar anomali bermakna
        patterns: ['baseline', '(tahu|profil|acuan)[^\n]*normal', 'bandingkan[^\n]*normal']
      - id: no-avonly
        label: Tidak mengandalkan satu kontrol saja (mis. antivirus) tanpa monitoring
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain|lebih dari))[^\n]*(cukup|hanya|andalkan)[^\n]*(antivirus|av|satu (alat|kontrol))[^\n]*(saja|cukup)']
    pass_score: 0.5
    solution: |
      ```text
      LAPISAN DETEKSI (berlapis, saling melengkapi):
        - Log aplikasi/akses: scanning (lonjakan 404 per IP), brute force (login gagal beruntun) — lab membuktikan
          query ini memisahkan penyerang dari user.
        - Runtime (auditd/Falco): syscall & perilaku pasca-eksploit — shell di container, baca /etc/shadow, koneksi
          keluar tak terduga. Menangkap yang tak muncul di log HTTP.
        - Metrik + alert: golden signals (latency/traffic/errors/saturation) → anomali terdeteksi cepat (turunkan MTTD).
        - FIM: webshell baru / biner sistem berubah = tanda kompromi (integritas, CIA).
        - Centralize + korelasi di SIEM: gabungkan WAF 403 + 5xx spike + login gagal dari IP sama = satu insiden.
        - Baseline normal lebih dulu → anomali bermakna, alert tidak berisik.
      ```
      Tidak ada satu sumber yang melihat semuanya: HTTP log melihat yang masuk, runtime melihat yang dilakukan di
      dalam, metrik melihat bentuk agregat, FIM melihat perubahan file. Mengandalkan satu kontrol (mis. "ada
      antivirus") meninggalkan lubang besar. Deteksi berlapis + korelasi = peluang menangkap di salah satu titik.
    tests:
      pass:
        - |
          pakai log aplikasi dan akses untuk mendeteksi scanning 404 dan brute force login gagal
          deteksi runtime auditd atau falco untuk syscall shell dan baca file sensitif
          metrik dan alert atas anomali lonjakan error traffic dan saturation
          file integrity monitoring untuk webshell atau biner yang berubah
          centralize dan korelasi di siem gabungkan sinyal lintas sumber
          tetapkan baseline normal agar anomali bermakna
          jangan andalkan satu kontrol seperti antivirus saja tanpa monitoring
        - |
          http log untuk scan dan brute, falco untuk aktivitas os pasca eksploit
          metrik golden signal plus alert, fim untuk perubahan file, siem korelasi lintas sumber
          baseline dulu biar alert tidak berisik
      fail:
        - |
          cukup pasang antivirus saja sudah aman
          tidak perlu monitoring atau log
        - |
          lihat satu log http saja sudah cukup
          runtime dan metrik tidak perlu
        - |
          tidak usah korelasi, alih-alih matikan alert biar tenang
    source: https://falco.org/docs/

  - id: c03
    tier: C
    type: scenario
    objective: metrics
    prompt: |
      Tim keamanan kebanjiran alert dan mulai mengabaikannya; sebuah insiden nyata terlewat karena tenggelam di antara
      ribuan notifikasi (alert fatigue). Rancang perbaikan alerting (satu poin per baris).
    checklist:
      - id: tune
        label: Tuning ambang dari baseline agar alert bermakna (kurangi false positive)
        required: true
        patterns: ['(tuning|setel|atur)[^\n]*(ambang|threshold)', 'baseline', '(kurangi|turunkan)[^\n]*false positive']
      - id: severity
        label: Severity + routing (kritis → telepon, rendah → dashboard)
        required: true
        patterns: ['severity|prioritas|tingkat', '(rute|routing|kirim)[^\n]*(kritis|rendah|penerima|channel)', 'kritis[^\n]*(telepon|page)[^\n]*(rendah|dashboard)']
      - id: dedupe
        label: Dedupe/grouping alert terkait (mis. Alertmanager)
        patterns: ['dedup|deduplikasi', 'group(ing)?|gabung[^\n]*alert', 'alert ?manager']
      - id: actionable
        label: Alert actionable + tautan runbook (apa artinya, apa yang dilakukan)
        patterns: ['actionable|bisa ditindak', 'runbook|playbook', '(apa (artinya|yang dilakukan)|langkah)[^\n]*alert']
      - id: review
        label: Tinjau & iterasi berkala (hapus alert berisik, tambah yang kurang)
        patterns: ['(tinjau|review|evaluasi|iterasi)[^\n]*(alert|berkala)', '(hapus|matikan)[^\n]*alert[^\n]*(berisik|noise|sering salah)']
      - id: slo
        label: Berbasis gejala/SLO (alert saat dampak nyata, bukan tiap fluktuasi)
        patterns: ['slo|sli', '(gejala|symptom|dampak)[^\n]*(alert|bukan)', 'alert saat[^\n]*(user|dampak|nyata)']
      - id: no-alertall
        label: Tidak "alert atas segalanya" dan tidak mematikan alert sepenuhnya
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain|hindari))[^\n]*(alert (atas )?(semua|segala)|matikan (semua )?alert)']
    pass_score: 0.45
    solution: |
      ```text
      1. Tuning ambang dari baseline → alert hanya saat benar-benar menyimpang (kurangi false positive yang membuat
         tim mati rasa).
      2. Severity + routing: kritis (dampak user/breach) → telepon/page; peringatan → chat; info → dashboard saja.
      3. Dedupe & grouping (Alertmanager): 100 alert dari satu insiden jadi SATU notifikasi ber-konteks.
      4. Setiap alert actionable: judul jelas, apa artinya, langkah awal, tautan runbook (modul 10).
      5. Tinjau & iterasi: hapus alert yang sering false-positive, tambah cakupan yang kurang — perlakukan sbg produk.
      6. Berbasis gejala/SLO: alert saat ada dampak nyata (error rate user naik), bukan tiap CPU 1 detik melonjak.
      ```
      Tujuan: setiap alert yang berbunyi PANTAS ditangani. Alert fatigue bukan masalah "kurang alert" — justru
      "terlalu banyak yang tak bermakna". "Alert atas segalanya" menyembunyikan yang penting; "matikan alert" membuat
      buta. Jalan tengahnya: sedikit, bermakna, actionable, dan terus di-tune.
    tests:
      pass:
        - |
          tuning ambang dari baseline agar alert bermakna dan kurangi false positive
          beri severity dan routing kritis ke telepon rendah ke dashboard
          dedupe dan grouping alert terkait pakai alertmanager
          buat tiap alert actionable dengan tautan runbook
          tinjau dan iterasi berkala hapus alert berisik tambah yang kurang
          berbasis gejala atau slo alert saat dampak nyata bukan tiap fluktuasi
          jangan alert atas segalanya dan jangan matikan alert sepenuhnya
        - |
          setel ambang dari baseline, severity dan routing jelas
          group alert di alertmanager, tiap alert actionable dengan runbook
          alert berbasis slo saat ada dampak user, review berkala
      fail:
        - |
          alert atas segala hal biar tidak ada yang terlewat
          tim nanti belajar mengabaikan yang tidak penting
        - |
          matikan semua alert biar tenang
          cek manual seminggu sekali saja
        - |
          naikkan semua alert jadi kritis
          tidak perlu runbook atau tuning
    source: https://sre.google/sre-book/monitoring-distributed-systems/
---

# Deteksi & Logging

Modul 02–08 mencegah dan menahan. Tapi "assume breach" (modul 01) berarti **sesuatu pasti lolos** — dan kalau kamu
tak bisa melihatnya, penyerang bebas bergerak. Modul ini membangun kemampuan **melihat**: logging yang bisa di-query,
terpusat, dan terpercaya; deteksi pola serangan dari log; deteksi runtime; serta metrik & alert untuk menyadari
serangan dengan cepat. Sisi red yang dilawan: menghindari deteksi & menghapus jejak (ATT&CK T1070).

> [!NOTE]
> Lab: nginx dengan **access log JSON** + dua klien (user & attacker, IP berbeda) + container **collector** di net
> `dj-sec09` (Docker Desktop). `auditd` **tidak tersedia** di kernel WSL2 lab ini (`auditctl` → "Operation not
> permitted"), jadi auditd & Falco dibahas di level konsep — di server Linux sungguhan keduanya berjalan di host.

## 1. Logging terstruktur = bisa di-query (hasil lab)

```nginx
log_format json escape=json '{"time":"$time_iso8601","client":"$remote_addr",'
    '"method":"$request_method","uri":"$uri","status":$status,"ua":"$http_user_agent"}';
access_log /var/log/nginx/access.json json;
```

Tiap baris jadi JSON → bisa di-query andal. Log teks bebas tidak. Ini fondasi semua deteksi otomatis & SIEM.

## 2. Deteksi dari log — "SIEM mini" (hasil lab)

```text
# scanning: hitung 404 per IP
grep '"status":404' access.json | grep -oE '"client":"[0-9.]+"' | sort | uniq -c | sort -rn
   12 "client":"172.18.0.4"   ← penyerang      (user sah: 0)

# path yang diprobe (404 teratas): /xmlrpc.php /wp-login.php /shell.php /phpmyadmin /config.php ...
# brute force: hits /login per IP
   20 "client":"172.18.0.4"   ← penyerang      5 "client":"172.18.0.3" (user)
# lonjakan: 32 request dalam 1 detik (2026-...T15:11:30) dari IP penyerang
```

Idiom `sort | uniq -c | sort -rn` memisahkan penyerang dari user dengan jelas. `uniq -c` **wajib** didahului `sort`.

## 3. Log terpusat & integritas (hasil lab)

```text
host web  ──(kirim via jaringan)──▶  collector terpisah
collector menerima: "<13>nginx: ALERT scanning detected from 172.18.0.4"

log mentah container (lokal, bisa hilang): /var/lib/docker/containers/<id>/<id>-json.log
```

- **Centralized**: query lintas host + **tahan dihapus** penyerang (T1070). Begitu terkirim keluar, aman dari host
  yang dikuasai.
- **Integritas**: append-only, akses tulis-hapus dibatasi, **NTP** agar timestamp lintas host selaras untuk korelasi.

## 4. Deteksi runtime melengkapi log HTTP

| Sumber | Melihat | Tidak melihat |
|---|---|---|
| Log akses/WAF (HTTP) | request masuk, scanning, brute, payload | aktivitas di dalam setelah masuk |
| Runtime (auditd/Falco, syscall) | shell di container, baca `/etc/shadow`, koneksi keluar | isi request HTTP |
| FIM (AIDE/Tripwire) | file sistem/biner/webshell berubah | perilaku proses |

Setelah penyerang masuk (mis. RCE), aktivitasnya pindah ke tingkat OS — **tak muncul di log HTTP**. auditd/Falco
menangkap tahap pasca-eksploit; FIM menangkap perubahan file. Deteksi berlapis = peluang menangkap di salah satu titik.

## 5. Log vs metrik, dan menurunkan MTTD

- **Log** = event diskret (detail, untuk investigasi). **Metrik** = angka teragregasi/deret waktu (ringkas, untuk
  alert cepat). **Trace** = satu request lintas layanan.
- **Golden signals** (SRE): Latency, Traffic, Errors, Saturation. Lonjakan Traffic+Errors+Saturation ≈ DDoS (modul 08).
- Alert pada ambang signal menurunkan **MTTD** (Mean Time To Detect) dari "pelanggan memberi tahu kami" jadi "menit".
- **Prometheus** (pull, evaluasi rule) → **Alertmanager** (dedupe/group/route notifikasi).

## 6. Jadikan deteksi sebagai kode & korelasi

- **Detection-as-code**: aturan (Falco rule, query SIEM, **Sigma**) di version control, di-review, diuji, di-deploy
  (modul 11). Sigma = format vendor-neutral: tulis sekali, konversi ke SIEM apa pun.
- **Korelasi (SIEM)**: WAF 403 + lonjakan 5xx + login gagal dari IP sama = **satu** insiden terkoordinasi, bukan tiga
  anomali terpisah. Deteksi kuat lahir dari korelasi lintas sumber.

> [!WARNING]
> Lawan **alert fatigue**: terlalu banyak alert berisik → tim mengabaikan → insiden nyata terlewat. Sedikit alert
> bermakna + severity + dedupe + actionable (tautan runbook) > ribuan alert yang diabaikan.

## 7. Ringkasan

| Kebutuhan | Kontrol |
|---|---|
| Bisa di-query | logging terstruktur (JSON) |
| Deteksi pola | query log (scanning/brute/lonjakan), SIEM |
| Tahan dihapus (T1070) | centralized, append-only, akses terbatas, NTP |
| Lihat pasca-eksploit | runtime (auditd/Falco), FIM |
| Sadar cepat (MTTD↓) | metrik, golden signals, alert, Alertmanager |
| Terawat & akurat | detection-as-code (Sigma), korelasi, lawan alert fatigue |

> [!NOTE]
> Lanjut ke modul 10 (Respons insiden & forensik dasar) — setelah **mendeteksi**, apa yang dilakukan: menahan,
> memberantas, memulihkan, dan menyelidiki dengan bukti (log yang sudah kamu amankan di modul ini).
