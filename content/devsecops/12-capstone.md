---
format: 1
id: sec-capstone
course:
  slug: devsecops
  title: DevSecOps
  description: Keamanan untuk DevOps dari dua sisi — cara kerja red team dan cara blue team mendeteksi serta menutupnya. Model ancaman, hardening server & container, keamanan web, ketersediaan (anti-DDoS), deteksi, respons insiden, dan keamanan pipeline. Semua praktik di lab terisolasi milik sendiri.
title: Capstone — Pertahanan Menyeluruh
summary: "Modul penutup menyatukan seluruh perjalanan. Kamu memetakan tahap serangan (Kill Chain/ATT&CK) ke pertahanan tiap modul, menerapkan defense-in-depth (tak ada satu titik gagal), menyeimbangkan cegah–deteksi–respons (assume breach), menggeser keamanan ke kiri sebagai kode & budaya, dan menimbang trade-off keamanan vs ketersediaan. Semua fakta yang dirujuk telah dibuktikan di lab modul 01–11; capstone ini sintesis, bukan lab baru."
order: 12
version: 1
status: verified
updated: '2026-11-25'
tested_on:
  - Sintesis modul 01–11 (DevSecOps) — tidak ada lab baru; setiap teknik yang dirujuk sudah diverifikasi di lab modulnya
  - Semua kontrol dibingkai defensif, pada target milik sendiri di lingkungan terisolasi (aturan etika modul 01)
objectives:
  - id: killchain
    text: Memetakan tahap serangan (Kill Chain/ATT&CK) ke pertahanan tiap modul
  - id: layering
    text: Menerapkan defense-in-depth — berlapis, tanpa satu titik gagal
  - id: assumebreach
    text: Menyeimbangkan cegah, deteksi, dan respons (assume breach)
  - id: shiftleft
    text: Membangun keamanan sebagai kode & budaya (shift-left)
  - id: tradeoffs
    text: Menimbang trade-off keamanan vs ketersediaan/kegunaan (risk-based)
  - id: endtoend
    text: Merancang pertahanan menyeluruh untuk sistem nyata
sources:
  - title: MITRE ATT&CK — matriks taktik & teknik
    url: https://attack.mitre.org/
  - title: Lockheed Martin — Cyber Kill Chain
    url: https://www.lockheedmartin.com/en-us/capabilities/cyber/cyber-kill-chain.html
  - title: NIST Cybersecurity Framework (Identify/Protect/Detect/Respond/Recover)
    url: https://www.nist.gov/cyberframework
  - title: OWASP — Top 10 & CI/CD Security
    url: https://owasp.org/
  - title: CIS Controls
    url: https://www.cisecurity.org/controls
  - title: Google SRE & BeyondProd (keamanan cloud-native)
    url: https://cloud.google.com/docs/security/beyondprod
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: killchain
    prompt: Pertahanan terhadap scanning/enumerasi awal penyerang (modul 02) terutama adalah?
    answers: ['mengurangi attack surface/eksposur — sembunyikan banner & path, tutup port, server_tokens off']
    patterns: ['(kurangi|reduksi|perkecil)[^\n]*(attack surface|eksposur|permukaan)', '(sembunyi|tutup)[^\n]*(banner|path|port|versi)', 'server_tokens|return 404|minim(al|kan) (info|eksposur)']
    tests:
      fail: ['biarkan semua terbuka', 'pasang lebih banyak port', 'umumkan versi software', 'abaikan scanning']
    explanation: 'Tahap pertama Kill Chain adalah **reconnaissance** — penyerang memetakan apa yang terekspos. Pertahanannya (modul 02): kurangi attack surface — `server_tokens off` (sembunyikan versi), `return 404` untuk path sensitif (bukan 403 yang mengonfirmasi keberadaan), tutup port tak perlu, jangan bocorkan banner. Yang tak terlihat lebih sulit diserang. Deteksi scanning (modul 09, lonjakan 404) melengkapi: kamu tahu sedang dipetakan. Mengecilkan & menyamarkan permukaan membuat tahap berikut lebih mahal bagi penyerang.'
    source: https://www.lockheedmartin.com/en-us/capabilities/cyber/cyber-kill-chain.html

  - id: a02
    tier: A
    type: text
    objective: layering
    prompt: Apa inti dari prinsip "defense-in-depth"?
    answers: ['banyak lapisan kontrol independen sehingga jebolnya satu lapisan tidak langsung berarti kalah']
    patterns: ['(banyak|berlapis|beberapa) (lapis|kontrol|pertahanan)', '(satu|satu lapis)[^\n]*(jebol|gagal|lolos)[^\n]*(tidak|bukan|masih)[^\n]*(kalah|tembus|aman)', 'tidak (ada )?(satu|single)[^\n]*(titik gagal|point of failure|kontrol cukup)']
    tests:
      fail: ['satu firewall sudah cukup', 'hanya butuh satu kontrol kuat', 'satu perimeter melindungi semua', 'cukup satu lapisan']
    explanation: 'Defense-in-depth = **banyak lapisan kontrol independen** sehingga kompromi satu lapisan tidak berarti kalah total. Tema ini berulang di SETIAP modul: WAF + prepared statement + DB least-privilege (modul 07); non-root + cap-drop + no-new-privileges + seccomp (modul 06); pencegahan + deteksi + respons (modul 08–10); CI gate + admission control (modul 11). Tak ada kontrol tunggal yang sempurna — masing-masing bisa di-bypass. Berlapis berarti penyerang harus menembus semuanya, dan kamu punya banyak kesempatan menangkapnya.'
    source: https://www.cisecurity.org/controls

  - id: a03
    tier: A
    type: command
    objective: layering
    prompt: Jalankan image `myapp` dengan membuang semua capability Linux (hardening runtime, modul 06).
    answers: ['docker run --cap-drop ALL myapp']
    patterns: ['^(?=.*[ \t]--cap-drop ALL(\s|$))docker run( \S+)* myapp$']
    tests:
      pass:
        - 'docker run --cap-drop ALL myapp'
        - 'docker run --cap-drop ALL --read-only myapp'
      fail:
        - 'docker run --privileged myapp'
        - 'docker run --cap-add ALL myapp'
        - 'docker run myapp'
    explanation: '`--cap-drop ALL` (lalu `--cap-add` seperlunya) adalah kontrol tanda tangan modul 06 — di lab, CapEff jadi `0`. Ia satu lapisan dari banyak: gabungkan `--user` non-root, `--security-opt=no-new-privileges` (lab: setuid gagal eskalasi), `--read-only --tmpfs`, `--pids-limit`, dan TANPA `docker.sock`/`--privileged`. Jika aplikasi dalam container dikompromi lewat celah web (modul 07), lapisan-lapisan ini membatasi apa yang bisa dilakukan penyerang — mengubah "jebol aplikasi" dari "jebol host" (container escape, T1611) jadi sekadar "jebol satu container terkurung".'
    source: https://docs.docker.com/engine/security/

  - id: a04
    tier: A
    type: command
    objective: shiftleft
    prompt: Pindai image `myapp:1.0` untuk kerentanan (CVE) sebelum rilis (modul 05/11).
    answers: ['trivy image myapp:1.0']
    patterns: ['^trivy[ \t]+image[ \t]+\S+']
    tests:
      pass:
        - 'trivy image myapp:1.0'
        - 'trivy image --severity HIGH,CRITICAL myapp:1.0'
      fail:
        - 'trivy config myapp:1.0'
        - 'trivy fs myapp:1.0'
        - 'docker scan myapp:1.0'
    explanation: '`trivy image` memindai CVE pada paket image — di lab modul 05, `nginx:1.27-alpine` (EOL) punya 70 CVE vs `alpine:3.20` hanya 2. Di pipeline (modul 11) ia jadi gerbang SCA dengan `--exit-code 1`. Pasangkan dengan `trivy config` (misconfig IaC), `trivy fs --scanners secret` (rahasia), cosign sign + SBOM, dan jalankan **berkala** (CVE baru muncul untuk image lama yang tak berubah). Shift-left: temukan image rentan di PR, bukan setelah di-deploy ke produksi.'
    source: https://trivy.dev/

  - id: a05
    tier: A
    type: text
    objective: killchain
    prompt: Penyerang mendapat pijakan awal lewat kerentanan aplikasi web (OWASP, modul 07). Kontrol apa yang mencegah kelas ini?
    answers: ['prepared statement, otorisasi server-side, output encoding, allowlist ssrf, security headers, dan waf sebagai lapisan']
    patterns: ['prepared statement|query ter-?parameter', '(otorisasi|authz|access control)[^\n]*(server|objek)', 'output encoding|escap|csp', 'allowlist|waf|security header']
    tests:
      fail: ['tidak ada pertahanan', 'cukup sembunyikan tombol di ui', 'percaya input user', 'hanya pakai https']
    explanation: 'Aplikasi web adalah tempat pijakan pertama paling umum (ATT&CK T1190, Exploit Public-Facing Application). Pertahanan (modul 07), akarnya di kode: **prepared statement** (injection), **otorisasi server-side deny-by-default** (broken access control/IDOR), **output encoding + CSP** (XSS), **allowlist host** (SSRF), **security headers**, dengan **WAF** sebagai lapisan tambahan (bukan pengganti). Jika tahap ini tertutup, penyerang tak pernah dapat pijakan untuk mencoba escape (modul 06) atau bergerak lateral. Akar semua kelas itu sama: jangan percaya input.'
    source: https://owasp.org/www-project-top-ten/

  - id: a06
    tier: A
    type: text
    objective: assumebreach
    prompt: Apa makna prinsip "assume breach" dan konsekuensinya pada desain pertahanan?
    answers: ['anggap pencegahan akan gagal/sudah ada yang jebol; maka investasikan juga di deteksi, pembatasan dampak, dan respons']
    patterns: ['(anggap|asumsi)[^\n]*(jebol|gagal|tembus|sudah masuk|breach)', '(deteksi|respons|pembatasan|limit dampak|containment)[^\n]*(juga|penting|investasi)', '(bukan hanya|tak cukup) (cegah|pencegahan)']
    tests:
      fail: ['pencegahan sempurna mungkin', 'tidak perlu deteksi jika mencegah', 'abaikan kemungkinan jebol', 'cukup bangun tembok tinggi']
    explanation: '"Assume breach" (modul 01) mengakui bahwa pencegahan **pasti ada yang lolos** — celah baru, kesalahan konfigurasi, zero-day, kesalahan manusia. Konsekuensinya: jangan hanya berinvestasi pada tembok (pencegahan), tapi juga pada **deteksi** (modul 09 — lihat serangan yang lolos), **pembatasan dampak** (least privilege, segmentasi, cap-drop — perkecil blast radius), dan **respons** (modul 10 — tangani cepat). Pola pikir ini mengubah desain dari "cegah semua" (mustahil) jadi "cegah banyak, deteksi sisanya, batasi kerusakan, pulih cepat".'
    source: https://cloud.google.com/docs/security/beyondprod

  - id: a07
    tier: A
    type: command
    objective: assumebreach
    prompt: Isolasi container `web` yang dikonfirmasi terkompromi dari jaringan `prod` tanpa membunuhnya (containment, modul 10).
    answers: ['docker network disconnect prod web']
    patterns: ['^docker network disconnect[ \t]+\S+[ \t]+\S+']
    tests:
      pass:
        - 'docker network disconnect prod web'
      fail:
        - 'docker rm -f web'
        - 'docker stop web'
        - 'docker kill web'
    explanation: 'Saat breach terjadi (assume breach jadi nyata), containment lebih dulu: `docker network disconnect` memutus C2/lateral tapi membiarkan container hidup untuk bukti (modul 10 — lab: isolated tapi tetap Up). JANGAN langsung `rm` (buang bukti volatil). Lalu kumpulkan bukti (`docker top`, `docker diff`), preservasi (`commit`/`export` + `sha256sum`), berantas (redeploy image bersih), rotasi rahasia, pulih, dan pelajari (blameless). Respons yang terlatih mengubah breach dari bencana jadi insiden terkelola.'
    source: https://docs.docker.com/engine/reference/commandline/network_disconnect/

  - id: a08
    tier: A
    type: text
    objective: shiftleft
    prompt: Bagaimana "shift left" mengubah pengetahuan keamanan modul 02–10 menjadi sesuatu yang bertahan lama?
    answers: ['mengkodekannya menjadi gerbang pipeline otomatis sehingga ditegakkan konsisten di setiap build, bukan bergantung ingatan orang']
    patterns: ['(kode|gerbang|gate|otomat|scan|policy)[^\n]*(pipeline|ci|build)', 'ditegakkan[^\n]*(konsisten|otomatis|setiap)', '(bukan|tak)[^\n]*(bergantung|ingat|manual)']
    tests:
      fail: ['mengandalkan setiap orang mengingat', 'cek manual sesekali', 'tidak ada cara mempertahankan', 'tulis di dokumen lalu lupakan']
    explanation: 'Pengetahuan di kepala akan terlupakan atau tak diterapkan konsisten. Shift-left (modul 11) **mengkodekan** pelajaran keamanan jadi gerbang otomatis: "jangan root" → aturan trivy/Rego; "jangan hardcode rahasia" → secret scan + pre-commit; "pindai CVE & tandatangani image" → SCA gate + cosign. Ditegakkan di **setiap** build, pada **setiap** kontributor, **selamanya** — tak bergantung siapa yang ingat. Ini juga memberi umpan balik cepat ke developer (di PR), menjadikan keamanan bagian alur kerja, bukan hambatan di akhir.'
    source: https://owasp.org/www-project-top-10-ci-cd-security-risks/

  - id: a09
    tier: A
    type: text
    objective: tradeoffs
    prompt: Rate limit yang terlalu ketat atau kontrol yang terlalu agresif bisa menimbulkan masalah apa, dan bagaimana menyikapinya?
    answers: ['merusak ketersediaan/kegunaan bagi user sah (false positive); setel berbasis risiko & baseline, pantau, jangan asal maksimal']
    patterns: ['(user|pengguna) sah[^\n]*(terblokir|kena|tolak|terganggu)|false positive', '(ketersediaan|availability|kegunaan|usability)[^\n]*(terganggu|rusak|turun)', '(berbasis risiko|baseline|setel|tuning|seimbang)[^\n]*(bukan|jangan)?[^\n]*(asal|maksimal)?']
    tests:
      fail: ['selalu set kontrol paling ketat', 'keamanan tanpa pertimbangan apa pun', 'abaikan user sah', 'tidak ada trade-off']
    explanation: 'Keamanan punya trade-off: rate limit terlalu ketat menolak user sah (modul 08), MFA di mana-mana melelahkan, enkripsi/scan menambah latensi, lock-down berlebihan mendorong orang mencari jalan pintas tak aman. Kontrol yang merusak ketersediaan/kegunaan **juga** kegagalan (availability = CIA). Sikapi **berbasis risiko**: lindungi aset bernilai tinggi lebih ketat, setel dari baseline trafik nyata, pantau false positive, dan sediakan degradasi anggun. Keamanan yang baik seimbang & proporsional, bukan "semaksimal mungkin" membabi buta.'
    source: https://www.nist.gov/cyberframework

  - id: a10
    tier: A
    type: text
    objective: killchain
    prompt: Setelah penyerang masuk, mereka mencoba "persistence" (mempertahankan akses). Sebutkan satu deteksi/pertahanannya.
    answers: ['file integrity monitoring/read-only rootfs untuk webshell/biner berubah, deteksi runtime (falco) untuk proses asing, dan redeploy dari image bersih menghapusnya']
    patterns: ['file integrity|fim|read-?only[^\n]*(rootfs|container)', 'falco|auditd|deteksi runtime[^\n]*(proses|asing|shell)', 'redeploy[^\n]*(image bersih|known.?good)|immutable']
    tests:
      fail: ['persistence tidak bisa dideteksi', 'biarkan saja', 'tidak ada pertahanan', 'reboot pasti menghapusnya']
    explanation: 'Persistence (Kill Chain tahap akhir) = penyerang menanam backdoor agar bisa kembali: webshell, user baru, cron/systemd, biner ter-trojan. Pertahanan & deteksi lintas-modul: **read-only rootfs** (modul 06 — mencegah menulis backdoor), **File Integrity Monitoring** (modul 09 — alert file sistem berubah), **deteksi runtime** Falco/auditd (proses/shell asing), dan secara arsitektural **infrastruktur immutable** (modul 10 — redeploy dari image bersih menghapus persistence karena container diganti, bukan dirawat). Beberapa lapisan menutup satu taktik.'
    source: https://attack.mitre.org/

  - id: a11
    tier: A
    type: text
    objective: assumebreach
    prompt: Tujuan akhir banyak serangan adalah exfiltrasi data. Kontrol apa yang membatasi kemampuan container/host mengirim data keluar?
    answers: ['kontrol egress — jaringan --internal/deny egress, allowlist tujuan, dan monitoring koneksi keluar tak terduga']
    patterns: ['egress|keluar[^\n]*(blokir|batasi|kontrol|deny)', '--internal|network policy|allowlist[^\n]*(tujuan|egress|keluar)', '(monitor|deteksi)[^\n]*(koneksi keluar|outbound|c2)']
    tests:
      fail: ['biarkan semua koneksi keluar bebas', 'tidak bisa dibatasi', 'exfiltrasi tak terhindarkan', 'cukup blokir inbound saja']
    explanation: 'Exfiltrasi butuh jalur keluar. Batasi **egress**: jaringan Docker `--internal` memblokir egress sepenuhnya (modul 01 — lab: ping 8.8.8.8 Network unreachable, curl exit 6), NetworkPolicy di K8s, atau allowlist tujuan keluar. Lengkapi dengan **monitoring koneksi keluar** (modul 09 — koneksi ke IP C2 tak dikenal adalah IOC). Banyak sistem terlalu fokus memblokir *inbound* dan membiarkan *outbound* bebas — padahal membatasi egress menghentikan exfiltrasi, C2, dan SSRF ke metadata (modul 07). Pertahanan bekerja di kedua arah.'
    source: https://attack.mitre.org/

  - id: a12
    tier: A
    type: text
    objective: endtoend
    prompt: Framework NIST CSF membagi keamanan menjadi lima fungsi. Sebutkan minimal tiga.
    answers: ['identify, protect, detect, respond, recover']
    patterns: ['identif(y|ikasi)', 'protect|lindungi|proteksi', 'detect|deteksi', 'respond|respons', 'recover|pulih']
    tests:
      fail: ['scan, patch, reboot', 'build, test, deploy', 'login, logout, lock', 'red, blue, purple']
    explanation: 'NIST Cybersecurity Framework: **Identify** (kenali aset & risiko — threat model, modul 01), **Protect** (cegah — hardening/web/secrets, modul 03–07), **Detect** (lihat serangan — logging/monitoring, modul 09), **Respond** (tangani insiden, modul 10), **Recover** (pulihkan — backup, modul 10). Perjalanan modul ini memetakan ke kelima fungsi, dan Availability (modul 08) menopang semuanya. Framework seperti CSF memberi struktur agar kamu tak lupa satu dimensi — bukan hanya menumpuk kontrol "Protect" sambil buta di "Detect".'
    source: https://www.nist.gov/cyberframework

  - id: a13
    tier: A
    type: command
    objective: shiftleft
    prompt: Periksa security headers sebuah aplikasi dengan curl (hanya header, modul 07/02).
    answers: ['curl -I https://app.example']
    patterns: ['^(?=.*[ \t](-[A-Za-z]*I[A-Za-z]*|--head)([ \t]|$))curl[ \t].*\S+']
    tests:
      pass:
        - 'curl -I https://app.example'
        - 'curl -sI https://app.example'
      fail:
        - 'curl https://app.example'
        - 'wget https://app.example'
        - 'curl -X POST https://app.example'
    explanation: '`curl -I` menampilkan hanya header — cara cepat mengaudit CSP, HSTS, X-Content-Type-Options, X-Frame-Options, dan memastikan `Server`/`X-Powered-By` tidak membocorkan versi (modul 02 & 07, dibuktikan di lab). Perintah kecil yang menyentuh banyak lapisan: reduksi eksposur (banner), anti-XSS (CSP), anti-clickjacking (XFO), anti-downgrade (HSTS). Jadikan bagian dari checklist rilis / gerbang pipeline. Keamanan sering soal memeriksa hal sederhana secara konsisten.'
    source: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers

  - id: a14
    tier: A
    type: text
    objective: layering
    prompt: Prinsip "least privilege" muncul di hampir setiap modul. Beri dua contoh penerapannya dari modul berbeda.
    answers: ['ssh allowusers/non-root (03), --cap-drop all & --user non-root (06), db user hak minimal (07), token ci berumur pendek (11)']
    patterns: ['(--cap-drop|non-?root|--user)[^\n]*', 'db user[^\n]*(hak minimal|minimal)|least privilege (db|database)', '(ci|token)[^\n]*(hak minimal|pendek|scope)|allowusers|permitrootlogin no']
    tests:
      fail: ['beri semua akses penuh', 'root di mana-mana', 'satu kredensial admin untuk semua', 'least privilege tidak berguna']
    explanation: 'Least privilege — beri hanya hak yang benar-benar dibutuhkan — adalah benang merah: **SSH** `PermitRootLogin no` + `AllowUsers` (modul 03), **container** `--cap-drop ALL` + `--user` non-root + no-new-privileges (modul 06), **DB** user aplikasi non-superuser agar SQLi yang lolos terbatas (modul 07), **CI** token ber-scope sempit & berumur pendek (modul 11), **egress** hanya ke yang perlu (modul 01). Setiap penerapan **memperkecil blast radius**: ketika satu komponen jebol, hak yang minimal membatasi apa yang bisa dilakukan penyerang. Prinsip tunggal, diterapkan di mana-mana.'
    source: https://www.cisecurity.org/controls

  - id: a15
    tier: A
    type: text
    objective: tradeoffs
    prompt: Mengapa keamanan harus "risk-based" (berbasis risiko) dan bukan memperlakukan semua aset sama?
    answers: ['sumber daya terbatas; fokuskan kontrol terkuat pada aset & ancaman bernilai/berdampak tertinggi']
    patterns: ['(sumber daya|waktu|anggaran|resource)[^\n]*terbatas[^\n]*(fokus|prioritas|arahkan|jadi|maka)', '(fokus|prioritas)[^\n]*(aset|ancaman|risiko)[^\n]*(penting|tinggi|bernilai|berdampak)', 'tidak semua[^\n]*(sama|setara)[^\n]*(risiko|penting)']
    tests:
      fail: ['lindungi semua sama rata selalu', 'risiko tidak relevan', 'sumber daya tak terbatas', 'abaikan dampak bisnis']
    explanation: 'Sumber daya (waktu, orang, anggaran, toleransi gesekan) terbatas — kamu tak bisa melindungi semua maksimal. **Risk-based** berarti memprioritaskan: identifikasi aset bernilai tertinggi (data pelanggan, kredensial prod, sistem kritis) dan ancaman paling mungkin/berdampak (threat model, modul 01), lalu arahkan kontrol terkuat ke sana. Database pelanggan butuh pertahanan berlapis; halaman "tentang kami" statis tidak. Ini mengubah keamanan dari "pasang semua tool" jadi "tutup risiko terbesar dulu" — lebih efektif dan berkelanjutan.'
    source: https://www.nist.gov/cyberframework

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: killchain
    prompt: Petakan secara ringkas tahap Kill Chain (recon → eksploit → escape → persistence → exfiltrasi) ke modul pertahanannya.
    answers: ['recon→02, eksploit web→07, escape container→06, persistence→09/06, exfiltrasi→01/09 (egress & monitoring)']
    patterns: ['recon[^\n]*(02|eksposur|reduksi)', '(eksploit|web)[^\n]*(07|owasp|prepared)', '(escape|container)[^\n]*(06|cap-drop|hardening)', '(persistence|exfil)[^\n]*(09|06|01|egress|fim|deteksi)']
    tests:
      fail: ['semua tahap dilawan satu kontrol', 'tidak ada pemetaan', 'hanya firewall untuk semua', 'tahapan tidak relevan']
    explanation: 'Pemetaan tahap → pertahanan (defense-in-depth sepanjang Kill Chain): **Recon** → reduksi eksposur & deteksi scanning (modul 02, 09). **Eksploit web** → kontrol OWASP + WAF (modul 07). **Escape container** → hardening runtime cap-drop/no-new-priv/no-socket (modul 06). **Persistence** → FIM/read-only/deteksi runtime + redeploy immutable (modul 09, 06, 10). **Exfiltrasi** → kontrol egress `--internal`/allowlist + monitoring koneksi keluar (modul 01, 09). Setiap tahap punya lapisan pertahanan **dan** peluang deteksi — penyerang harus menang di setiap tahap; kamu cukup menangkap di satu.'
    source: https://attack.mitre.org/

  - id: b02
    tier: B
    type: text
    objective: layering
    prompt: Beri contoh konkret "berlapis" dari modul 07 (web) di mana tiga kontrol berbeda menutup satu kelas serangan.
    answers: ['sqli: prepared statement (akar) + db user hak minimal (batasi dampak) + waf (lapisan) + logging (deteksi)']
    patterns: ['^(?=.*prepared statement)(?=.*(waf|hak minimal|db user|database|logging|deteksi)).*', '^(?=.*waf)(?=.*(prepared statement|hak minimal|logging|deteksi)).*']
    tests:
      fail: ['hanya satu kontrol untuk sqli', 'waf saja cukup', 'tidak perlu berlapis', 'prepared statement menggantikan semua']
    explanation: 'Contoh berlapis untuk SQLi (modul 07): **(1) prepared statement** menutup akar (data tak jadi kode); **(2) DB user hak-minimal** membatasi dampak jika toh ada yang lolos (tak bisa DROP/baca tabel lain); **(3) WAF (ModSecurity/CRS)** memblokir payload umum sebagai lapisan tambahan — di lab memblokir SQLi dengan 403; **(4) logging** mendeteksi percobaan. Tak ada yang sempurna sendiri (prepared statement bisa terlewat di satu query; WAF bisa di-bypass), tapi bersama-sama satu kelemahan tidak langsung berarti kebocoran. Itulah defense-in-depth dalam satu kelas serangan.'
    source: https://owasp.org/www-project-top-ten/

  - id: b03
    tier: B
    type: text
    objective: assumebreach
    prompt: Jelaskan hubungan antara "memperkecil blast radius" dan asumsi bahwa suatu komponen akan jebol.
    answers: ['karena sesuatu pasti jebol, rancang agar kompromi satu komponen terbatas dampaknya (least privilege, segmentasi, isolasi)']
    patterns: ['(sesuatu|satu komponen|pasti)[^\n]*(jebol|kompromi|gagal)', '(batasi|perkecil|kurangi)[^\n]*(dampak|blast radius|kerusakan)', 'least privilege|segmentasi|isolasi|containment']
    tests:
      fail: ['blast radius tidak penting', 'kalau mencegah tak perlu membatasi', 'biarkan satu kompromi menyebar', 'semua komponen saling percaya penuh']
    explanation: 'Assume breach (a06) + blast radius adalah pasangan: karena kamu **menganggap** suatu komponen akan jebol, kamu merancang agar **kerusakannya terbatas**. Caranya: least privilege (modul 03/06/07/11 — komponen jebol punya sedikit hak), segmentasi jaringan & `--internal` (modul 01 — tak bisa menjangkau yang lain), isolasi container (modul 06 — escape sulit), kredensial ber-scope & berumur pendek (modul 11 — kebocoran terbatas), dan containment cepat (modul 10). Tujuannya mengubah "satu jebol = semua jatuh" jadi "satu jebol = satu terkurung". Membatasi dampak adalah pencegahan yang realistis.'
    source: https://cloud.google.com/docs/security/beyondprod

  - id: b04
    tier: B
    type: text
    objective: tradeoffs
    prompt: 'Modul 08 menyebut "jujur soal batas" — contoh di mana kontrol host saja TIDAK cukup, dan apa jawabannya?'
    answers: ['flood volumetrik yang menjenuhkan uplink tak bisa dihentikan iptables/rate limit di server; perlu mitigasi upstream (cdn/scrubbing)']
    patterns: ['volumetrik[^\n]*(uplink|bandwidth|jenuh)', '(iptables|rate limit|host|server)[^\n]*(tidak|tak)[^\n]*(cukup|menolong|bisa)', '(upstream|cdn|scrubbing|anycast)[^\n]*(cdn|scrubbing|provider|anycast|mitigasi|upstream)', 'mitigasi[^\n]*upstream']
    tests:
      fail: ['iptables selalu cukup', 'tidak ada batas kontrol host', 'host bisa menghentikan segala serangan', 'upstream tidak perlu']
    explanation: 'Kejujuran teknis penting dalam keamanan — mengetahui batas kontrolmu. Contoh (modul 08): **flood volumetrik** yang menjenuhkan uplink **tak bisa** dihentikan `iptables`/rate limit di server yang diserang, karena paket sudah memenuhi pipa sebelum sampai. Jawabannya ada di **upstream**: CDN/anycast + scrubbing/proteksi provider. Mengklaim "firewall kami melindungi dari DDoS" adalah rasa aman palsu. Insinyur keamanan yang baik tahu apa yang **tidak** bisa dilakukan kontrolnya dan merancang lapisan yang tepat — bukan berpura-pura satu kotak menyelesaikan segalanya.'
    source: https://www.cloudflare.com/learning/ddos/what-is-a-ddos-attack/

  - id: b05
    tier: B
    type: text
    objective: shiftleft
    prompt: Bagaimana "security as code" (policy-as-code, detection-as-code, gerbang CI) sejalan dengan budaya DevOps?
    answers: ['keamanan di-versioning, di-review via pr, diuji, dan di-deploy seperti kode — jadi bagian alur dev, bukan gerbang terpisah di akhir']
    patterns: ['(version control|git|di-?review|pr|diuji|di-?deploy)[^\n]*(keamanan|policy|rule|gerbang)', 'seperti kode|as code', '(bagian|masuk)[^\n]*(alur|workflow|dev|pipeline)[^\n]*(bukan|tidak)[^\n]*(akhir|terpisah)']
    tests:
      fail: ['keamanan terpisah dari dev', 'keamanan hanya di akhir', 'tim keamanan bekerja sendiri', 'keamanan tidak bisa otomatis']
    explanation: 'DevSecOps menyatukan Dev, Sec, Ops. "Security as code" mewujudkannya: aturan policy (OPA/Rego, modul 11), aturan deteksi (Sigma/Falco, modul 09), dan gerbang CI diperlakukan **seperti kode aplikasi** — di-versioning, di-review lewat PR, diuji, bisa di-rollback, di-deploy otomatis. Keamanan jadi **bagian alur kerja developer** (umpan balik di PR) alih-alih gerbang manual yang menghambat di akhir. Ini menggeser keamanan dari "tim terpisah yang bilang tidak" jadi "guardrail otomatis yang membantu melakukan hal benar secara default". Budaya + otomasi.'
    source: https://owasp.org/www-project-top-10-ci-cd-security-risks/

  - id: b06
    tier: B
    type: text
    objective: assumebreach
    prompt: Deteksi tanpa respons, atau respons tanpa deteksi — mengapa keduanya harus ada bersama?
    answers: ['deteksi tanpa respons = tahu diserang tapi tak berbuat (sia-sia); respons tanpa deteksi = tak pernah tahu harus bertindak']
    patterns: ['deteksi tanpa respons[^\n]*(sia-sia|tahu[^\n]*tak|percuma)', 'respons tanpa deteksi[^\n]*(tak tahu|buta|tidak sadar)', '(saling|harus) (bersama|melengkapi)']
    tests:
      fail: ['cukup salah satu', 'deteksi saja sudah aman', 'respons tidak butuh deteksi', 'keduanya tidak berhubungan']
    explanation: 'Deteksi (modul 09) dan respons (modul 10) adalah pasangan tak terpisahkan. **Deteksi tanpa respons**: alert berbunyi tapi tak ada yang bertindak — kamu hanya menonton diri dibobol (persis alert fatigue, modul 09). **Respons tanpa deteksi**: kamu punya rencana hebat tapi tak pernah tahu kapan memakainya — penyerang bebas berbulan-bulan (dwell time). Keduanya harus ada: deteksi memicu respons, respons memberi makna pada deteksi. Ditambah pencegahan (cegah yang bisa) dan recovery (pulih), inilah siklus lengkap assume-breach.'
    source: https://www.nist.gov/cyberframework

  - id: b07
    tier: B
    type: text
    objective: endtoend
    prompt: Untuk aplikasi web ber-container baru, urutkan secara logis dari mana memulai pengamanan dan mengapa.
    answers: ['mulai dari threat model (kenali aset & ancaman), lalu bangun kontrol berlapis sesuai risiko, lalu deteksi/respons, dan otomasi di pipeline']
    patterns: ['(mulai|awali)[^\n]*(threat model|model ancaman|identifikasi (aset|risiko))', '(lalu|kemudian)[^\n]*(kontrol|hardening|berlapis)', '(lalu|kemudian|terakhir)[^\n]*(deteksi|respons|pipeline|otomasi)']
    tests:
      fail: ['beli tool dulu tanpa rencana', 'mulai dari mana saja tak masalah', 'langsung pasang semua kontrol acak', 'deteksi tidak perlu']
    explanation: 'Urutan logis (sintesis seluruh board): **(1) Threat model** (modul 01) — kenali aset, siapa penyerangnya, apa yang dilindungi; ini mengarahkan sisanya. **(2) Kontrol berlapis berbasis risiko** — kurangi eksposur (02), hardening host/akses/container (03/06), amankan kode web & secrets & image (07/04/05), jaga ketersediaan (08). **(3) Deteksi & respons** (09/10) karena assume breach. **(4) Otomasikan sebagai gerbang pipeline** (11) agar bertahan. Memulai dari threat model mencegah "memasang tool acak" — kamu menutup risiko nyata, bukan menebak.'
    source: https://www.nist.gov/cyberframework

  - id: b08
    tier: B
    type: text
    objective: tradeoffs
    prompt: Mengapa "security by obscurity" saja (hanya mengandalkan kerahasiaan desain) bukan pertahanan yang cukup?
    answers: ['obscurity bisa dibongkar dan tak menghentikan penyerang yang menemukannya; harus didukung kontrol nyata, boleh sebagai lapisan tambahan']
    patterns: ['obscurity[^\n]*(dibongkar|ditemukan|tak cukup|bukan pertahanan)', '(bukan|tak) (pengganti|cukup)[^\n]*(kontrol (nyata|asli)|pertahanan)', '(boleh|hanya) (sebagai )?lapisan (tambahan)?']
    tests:
      fail: ['obscurity saja sudah cukup', 'sembunyikan desain pasti aman', 'tidak perlu kontrol nyata', 'kerahasiaan mengalahkan semua']
    explanation: 'Security by obscurity (menyembunyikan cara kerja/path/port sebagai satu-satunya pertahanan) rapuh: begitu ditemukan (dan penyerang gigih akan menemukan — modul 02 scanning, enumerasi), tak ada lagi yang menahan. Contoh di board: UUID "sulit ditebak" BUKAN kontrol akses (modul 07 — tetap butuh otorisasi server-side); port SSH non-standar menunda tapi tak mengganti hardening (modul 03). Obscurity **boleh** sebagai lapisan tambahan (mengurangi noise/otomasi), tapi harus di atas kontrol nyata. Pertahanan harus tetap kuat meski desainnya diketahui penyerang (prinsip Kerckhoffs).'
    source: https://www.cisecurity.org/controls

  - id: b09
    tier: B
    type: text
    objective: killchain
    prompt: Mengapa membatasi egress (koneksi keluar) menutup beberapa teknik serangan sekaligus?
    answers: ['egress ketat menghentikan exfiltrasi data, komunikasi c2, ssrf ke internal/metadata, dan unduhan tool pasca-eksploit']
    patterns: ['exfil(trasi|)', 'c2|command.?and.?control|beacon', 'ssrf|metadata|169\.254', '(unduh|download|pull)[^\n]*(tool|payload|malware)']
    tests:
      fail: ['egress tidak berpengaruh', 'hanya inbound yang penting', 'membatasi egress tidak berguna', 'satu teknik saja terpengaruh']
    explanation: 'Membatasi egress adalah kontrol dengan "banyak hasil": ia menghentikan **exfiltrasi data** (jalur keluar data curian), **komunikasi C2** (beacon tak bisa menelepon pulang — modul 09/10), **SSRF** ke layanan internal/metadata cloud (modul 07 — tak bisa menjangkau 169.254.169.254), dan **unduhan tool pasca-eksploit** (penyerang tak bisa menarik malware). Di lab modul 01, jaringan `--internal` memblokir semua egress. Banyak arsitektur lupa ini — fokus inbound, egress bebas. Padahal default-deny egress + allowlist adalah salah satu kontrol berdampak tertinggi melawan tahap-tahap akhir serangan.'
    source: https://attack.mitre.org/

  - id: b10
    tier: B
    type: text
    objective: layering
    prompt: Bagaimana modul 05 (image/supply chain) dan modul 11 (pipeline) bekerja sama mengamankan rantai pasok?
    answers: ['modul 05 memberi teknik (pin digest, scan cve, sbom, cosign sign/verify); modul 11 mengotomasikannya sebagai gerbang pipeline + pin action & verifikasi saat deploy']
    patterns: ['(pin|digest|scan|sbom|cosign|sign)[^\n]*(image|digest|cve|artefak|05)', '(pipeline|gerbang)[^\n]*(otomat|gate|verifikasi|ci|sca)', 'verifikasi[^\n]*deploy|admission']
    tests:
      fail: ['keduanya tidak berhubungan', 'cukup satu modul', 'supply chain tidak bisa diamankan', 'pin dan scan tidak perlu']
    explanation: 'Keamanan rantai pasok butuh teknik **dan** penegakan. **Modul 05** memberi tekniknya (diverifikasi di lab): pin digest (immutable), `trivy image` untuk CVE, SBOM, `cosign sign`/`verify` (signature terikat digest). **Modul 11** mengotomasikannya jadi gerbang: SCA scan dengan `--exit-code 1`, pin versi action/dependency (cegah dependency confusion & action ter-kompromi, T1195), generate SBOM + sign di pipeline, verifikasi tanda tangan saat deploy/admission. Satu memberi "bagaimana mengamankan artefak", satu memberi "bagaimana memastikan itu selalu terjadi". Bersama = rantai tepercaya dari commit sampai runtime.'
    source: https://slsa.dev/

  - id: b11
    tier: B
    type: text
    objective: assumebreach
    prompt: Apa peran "lessons learned" blameless (modul 10) dalam memperkuat seluruh sistem pertahanan dari waktu ke waktu?
    answers: ['mengubah tiap insiden jadi perbaikan sistemik (tutup akar, perbaiki deteksi, update gerbang) tanpa menyalahkan orang sehingga akar sebenarnya muncul']
    patterns: ['(insiden|kejadian)[^\n]*(perbaikan|pelajaran|perkuat)[^\n]*(sistem|akar|deteksi|gerbang)', 'blameless|tanpa (salah|menyalahkan)', '(akar|root cause)[^\n]*(muncul|ketahuan|jujur)']
    tests:
      fail: ['lessons learned hanya formalitas', 'salahkan orang lalu selesai', 'insiden tidak memberi pelajaran', 'tidak perlu memperbaiki sistem']
    explanation: 'Pertahanan bukan statis — ia membaik lewat umpan balik. **Lessons learned blameless** (modul 10) mengubah setiap insiden/near-miss jadi perbaikan sistemik: tutup akar masalah, perbaiki deteksi yang meleset, tambah/perketat gerbang pipeline (modul 11) agar kelas masalah itu tertangkap otomatis lain kali. **Blameless** krusial — jika orang takut disalahkan, mereka menyembunyikan detail dan akar sebenarnya tak muncul. Ini loop peningkatan berkelanjutan: insiden → pelajaran → kontrol baru sebagai kode → sistem lebih kuat. Keamanan yang tak belajar dari insidennya akan mengulanginya.'
    source: https://sre.google/sre-book/postmortem-culture/

  - id: b12
    tier: B
    type: text
    objective: tradeoffs
    prompt: Seorang rekan ingin menonaktifkan sebuah gerbang keamanan "karena memperlambat rilis". Bagaimana kamu menyikapinya secara seimbang?
    answers: ['pahami kebutuhan kecepatan, tapi jangan matikan buta; tuning (baseline/severity/pengecualian ter-review), perbaiki akar yang bikin lambat, jaga gerbang tetap bermakna']
    patterns: ['(pahami|akui)[^\n]*(kecepatan|rilis|kebutuhan)', '(jangan|tak) (matikan|disable)[^\n]*(buta|begitu saja)', '(tuning|baseline|severity|pengecualian|perbaiki akar)[^\n]*(bukan|daripada)?[^\n]*(matikan)?']
    tests:
      fail: ['langsung matikan gerbangnya', 'keamanan selalu menang mutlak', 'abaikan kebutuhan rilis', 'rilis tidak boleh cepat']
    explanation: 'Jawaban seimbang mengakui **kedua** sisi (gesekan vs keamanan adalah trade-off nyata, a09): pahami tekanan rilis, tapi jangan matikan gerbang membabi buta (meninggalkan rasa aman palsu, modul 11). Cari akar kelambatan — gerbang berisik? → baseline + severity + pengecualian ter-review (modul 11 b13); scan lambat? → cache/paralel/scope. Jadikan gerbang **cepat & bermakna**, bukan dimatikan. Tunjukkan biaya alternatifnya (insiden produksi jauh lebih lambat, a02). DevSecOps yang baik membuat jalan aman juga jalan cepat — bukan memaksa memilih antara keduanya.'
    source: https://owasp.org/www-project-top-10-ci-cd-security-risks/

  - id: b13
    tier: B
    type: text
    objective: endtoend
    prompt: Mengapa threat modeling (modul 01) sebaiknya diulang, bukan dilakukan sekali di awal?
    answers: ['sistem, fitur, dependency, dan lanskap ancaman berubah; model ancaman harus diperbarui agar kontrol tetap relevan']
    patterns: ['(sistem|fitur|arsitektur|dependency|lanskap)[^\n]*(berubah|berkembang|baru|bertambah)', '(model ancaman|threat model)[^\n]*(perbarui|diperbarui|update|ulang|berkala)', 'tetap relevan|mengikuti perubahan']
    tests:
      fail: ['sekali di awal sudah cukup selamanya', 'ancaman tidak pernah berubah', 'threat model statis', 'tidak perlu diulang']
    explanation: 'Threat model bukan dokumen sekali-jadi. Sistem berevolusi: fitur baru (endpoint baru = permukaan baru), dependency baru (CVE baru), integrasi baru (kepercayaan baru), dan **lanskap ancaman** bergeser (teknik & aktor baru — ikuti ATT&CK). Model ancaman yang dibuat setahun lalu mungkin melewatkan risiko hari ini. Ulangi secara berkala dan saat perubahan besar (fitur/arsitektur), agar kontrol (dan gerbang pipeline) tetap menutup risiko **nyata saat ini**. Ini menghubungkan kembali ke modul 01 — Identify dalam NIST CSF adalah aktivitas berkelanjutan, bukan checkbox.'
    source: https://www.nist.gov/cyberframework

  - id: b14
    tier: B
    type: text
    objective: shiftleft
    prompt: Selain alat & gerbang, elemen manusia/budaya apa yang membuat program DevSecOps berhasil?
    answers: ['pelatihan & kesadaran, keamanan jadi tanggung jawab bersama (bukan satu tim di akhir), budaya blameless, dan membuat jalan aman jadi jalan mudah']
    patterns: ['(pelatihan|training|kesadaran|awareness|edukasi)', '(tanggung jawab bersama|shared responsibility|semua orang|bukan satu tim)', 'blameless|jalan aman[^\n]*(mudah|default)|secure by default']
    tests:
      fail: ['hanya alat yang penting', 'keamanan tugas satu orang saja', 'budaya tidak relevan', 'beli tool lalu selesai']
    explanation: 'Alat tanpa budaya gagal. Elemen manusia: **pelatihan & kesadaran** (developer paham mengapa, bukan sekadar mematuhi), **tanggung jawab bersama** (keamanan milik semua, bukan "tim keamanan di akhir" yang bilang tidak), **budaya blameless** (orang jujur melaporkan masalah/near-miss, modul 10), dan **membuat jalan aman jadi jalan mudah** (template/default aman, guardrail yang membantu, bukan menghambat). DevSecOps = Dev + Sec + Ops menyatu secara budaya. Program terbaik memiliki alat hebat **dan** orang yang mau memakainya karena memahami & dimudahkan — bukan dipaksa.'
    source: https://cloud.google.com/docs/security/beyondprod

  - id: b15
    tier: B
    type: text
    objective: layering
    prompt: "Ringkas dalam satu kalimat: apa benang merah yang menghubungkan seluruh board DevSecOps ini?"
    answers: ['jangan percaya satu kontrol/input/komponen — berlapis, hak minimal, assume breach, dan otomasikan sebagai kode']
    patterns: ['(jangan|tak) percaya[^\n]*(satu|input|komponen|tunggal)', 'berlapis|defense.?in.?depth|least privilege|assume breach', '(otomat|kode|pipeline|konsisten)']
    tests:
      fail: ['satu kontrol ajaib menyelesaikan semua', 'percaya semua input', 'tidak ada benang merah', 'keamanan itu satu produk']
    explanation: 'Benang merah board ini: **jangan percaya satu hal apa pun** — bukan satu kontrol (berlapis/defense-in-depth), bukan input user (validasi/encoding/prepared statement), bukan satu komponen (least privilege + blast radius + assume breach), bukan ingatan manusia (otomasikan sebagai kode di pipeline). Dari model ancaman (01) sampai gerbang pipeline (11), setiap modul adalah penerapan prinsip ini pada lapisan berbeda: kurangi kepercayaan, perkecil hak, tambah lapisan, deteksi yang lolos, dan jadikan semuanya konsisten lewat kode. Itu esensi berpikir seperti blue team yang memahami red team.'
    source: https://attack.mitre.org/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: endtoend
    prompt: |
      Kamu memimpin keamanan untuk aplikasi web ber-container BARU (Laravel + Postgres di belakang nginx) yang akan
      go-live. Rancang pertahanan menyeluruh end-to-end, dari awal sampai operasi (satu poin per baris).
    checklist:
      - id: threatmodel
        label: Mulai dari threat model — aset, penyerang, data yang dilindungi (modul 01)
        required: true
        patterns: ['threat model|model ancaman', '(identifikasi|kenali)[^\n]*(aset|ancaman|risiko)', 'cia|stride']
      - id: harden
        label: Hardening host/akses + container non-root, cap-drop, no-new-priv, no docker.sock (03/06)
        required: true
        patterns: ['(non[- ]?root|--user|cap[- ]?drop|cap_drop)', 'no[- ]?new[- ]?privileges|read[- ]?only|seccomp', '(ssh|akses)[^\n]*(harden|permitrootlogin|allowusers|key)|tanpa docker\.?sock']
      - id: webapp
        label: Kontrol web — prepared statement, authz server-side, encoding/CSP, headers, WAF (07)
        required: true
        patterns: ['prepared statement|parameter', '(otorisasi|authz|access control)[^\n]*(server|objek)', 'encoding|csp|security header|waf']
      - id: secretsimage
        label: Secrets dikelola (bukan hardcode) + image pinned/scanned/signed (04/05)
        patterns: ['secret[^\n]*(manager|_FILE|bukan hardcode|rotasi)', '(pin|digest|scan|sign|cosign|sbom)[^\n]*image', 'trivy']
      - id: availability
        label: Ketersediaan — rate limit/conn limit, cache, timeout, rencana anti-DDoS upstream (08)
        patterns: ['rate limit|limit_req|limit_conn', 'cache|microcache', 'anti-?ddos|scrubbing|cdn|timeout']
      - id: detectrespond
        label: Deteksi & respons — logging terstruktur terpusat, alert, rencana IR + backup (09/10)
        required: true
        patterns: ['logging[^\n]*(terstruktur|terpusat|json)|siem', '(alert|deteksi|monitor)', '(rencana ir|incident response|backup)']
      - id: pipeline
        label: Gerbang pipeline shift-left — secret/IaC/SCA scan + policy-as-code, fail build (11)
        required: true
        patterns: ['(gerbang|gate|scan)[^\n]*(pipeline|ci)', 'secret scan|trivy config|sca|policy.as.code|conftest|opa', 'shift.?left|fail build|exit.?code']
      - id: no-single
        label: Tidak mengandalkan satu kontrol/perimeter saja
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain|lebih dari))[^\n]*(cukup|hanya|andalkan)[^\n]*(satu|firewall|perimeter|waf)[^\n]*(kontrol|saja|cukup)']
    pass_score: 0.5
    solution: |
      ```text
      1. THREAT MODEL (01): aset (data pelanggan, kredensial), penyerang, jalur; arahkan sisanya berbasis risiko.
      2. HARDEN (03/06): SSH key-only + least privilege; container non-root + --cap-drop ALL + no-new-privileges +
         read-only + pids/mem limit + TANPA docker.sock/privileged.
      3. WEB (07): prepared statement, otorisasi server-side deny-by-default, output encoding + CSP, security headers,
         WAF sebagai lapisan.
      4. SECRETS & IMAGE (04/05): secret via manager/*_FILE (bukan hardcode, rotasi mudah); image pin digest, trivy
         scan CVE, SBOM, cosign sign/verify.
      5. AVAILABILITY (08): limit_req/limit_conn, cache/microcache, timeout anti-slowloris, syncookies; rencana
         scrubbing/CDN upstream untuk volumetrik.
      6. DETEKSI & RESPONS (09/10): logging JSON terpusat (anti-T1070), alert sinyal kunci, egress dibatasi; rencana IR
         + backup offline teruji 3-2-1.
      7. PIPELINE (11): gerbang secret/IaC/SCA scan + policy-as-code (fail build via exit-code), pin & sign, admission
         control; jalan tiap PR + berkala.
      ```
      Satu sistem, banyak lapisan, dari desain sampai operasi. Tak ada kontrol tunggal yang cukup — setiap lapisan
      menutup kelas berbeda dan memberi peluang deteksi. Inilah seluruh board dalam satu desain.
    tests:
      pass:
        - |
          mulai dari threat model kenali aset penyerang dan data yang dilindungi
          hardening host dan ssh plus container non root cap-drop no-new-privileges read-only tanpa docker socket
          kontrol web prepared statement otorisasi server-side output encoding csp security headers dan waf
          secrets dikelola lewat manager bukan hardcode dan image pinned discan trivy ditandatangani cosign dengan sbom
          ketersediaan rate limit limit_conn cache timeout dan rencana anti ddos scrubbing upstream
          deteksi dan respons logging json terpusat alert rencana ir dan backup offline teruji
          gerbang pipeline shift left secret iac dan sca scan plus policy as code conftest yang menggagalkan build
          jangan mengandalkan satu kontrol atau perimeter saja
        - |
          threat model dulu, lalu harden host dan container non root cap drop no new privileges
          web prepared statement authz encoding headers waf, secret manager, image scan dan sign
          rate limit cache dan rencana scrubbing, logging terpusat alert dan rencana ir backup
          gerbang ci secret iac sca dan opa fail build, admission control, defense in depth bukan satu kontrol
      fail:
        - |
          pasang satu firewall saja sudah cukup aman
          tidak perlu threat model atau pipeline
        - |
          fokus ke satu waf dan abaikan sisanya
          deteksi dan backup tidak penting
        - |
          beli satu tool keamanan lalu go-live
          hardening dan secrets nanti saja
    source: https://www.nist.gov/cyberframework

  - id: c02
    tier: C
    type: scenario
    objective: killchain
    prompt: |
      Analisis sebuah serangan multi-tahap pada sistemmu: recon → eksploit kerentanan web → escape container →
      persistence → exfiltrasi data. Untuk tiap tahap, sebutkan pertahanan DAN titik deteksinya (satu poin per baris).
    checklist:
      - id: recon
        label: Recon → kurangi eksposur (banner/path/port) + deteksi scanning (404 spike) (02/09)
        required: true
        patterns: ['recon|scanning|enumeras', '(kurangi|sembunyi|tutup)[^\n]*(eksposur|banner|path|port)|server_tokens', '(deteksi|alert)[^\n]*(scan|404|enumeras)']
      - id: exploit
        label: Eksploit web → kontrol OWASP + WAF; deteksi via log 403/5xx & percobaan payload (07/09)
        required: true
        patterns: ['(prepared statement|otorisasi|encoding|allowlist|waf)', 'owasp|injection|xss|ssrf|idor', '(deteksi|log)[^\n]*(403|5xx|payload|waf)']
      - id: escape
        label: Escape container → cap-drop/no-new-priv/non-root/no socket; deteksi syscall (Falco) (06/09)
        required: true
        patterns: ['(cap-drop|non-?root|no-new-privileges|read-only|tanpa docker\.?sock)', 'container escape|t1611', 'falco|auditd|runtime[^\n]*(deteksi|syscall)']
      - id: persistence
        label: Persistence → read-only rootfs/FIM/redeploy immutable; deteksi file/proses asing (06/09/10)
        patterns: ['read-?only|fim|file integrity|immutable|redeploy', '(deteksi|alert)[^\n]*(file|proses|webshell|user baru)', 'docker diff']
      - id: exfil
        label: Exfiltrasi → batasi egress (--internal/allowlist); deteksi koneksi keluar/C2 (01/09)
        patterns: ['egress|--internal|network policy|allowlist[^\n]*(keluar|egress)', '(deteksi|monitor)[^\n]*(koneksi keluar|outbound|c2|exfil)']
      - id: respond
        label: "Lintas-tahap → IR: contain (network disconnect), bukti, rotasi rahasia, lessons (10)"
        patterns: ['(contain|isolasi|network disconnect)', 'bukti|docker diff|export|commit|rotasi rahasia', 'lessons|postmortem|ir']
      - id: no-perimeter
        label: Tidak menganggap satu perimeter/satu tahap melindungi segalanya
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain))[^\n]*(cukup|hanya)[^\n]*(satu (perimeter|tahap|firewall))[^\n]*(lindungi|cukup|saja)']
    pass_score: 0.5
    solution: |
      ```text
      RECON        pertahanan: server_tokens off, return 404, tutup port (02)   | deteksi: lonjakan 404/scan (09)
      EKSPLOIT WEB pertahanan: prepared stmt, authz, encoding, allowlist, WAF (07)| deteksi: 403 WAF, spike 5xx, payload di log (09)
      ESCAPE       pertahanan: --cap-drop ALL, no-new-priv, non-root, no sock (06)| deteksi: Falco syscall/shell di container (09)
      PERSISTENCE  pertahanan: read-only rootfs, immutable redeploy (06/10)      | deteksi: FIM, docker diff, proses/user asing (09)
      EXFILTRASI   pertahanan: egress --internal/allowlist (01)                  | deteksi: koneksi keluar/C2 tak dikenal (09)
      LINTAS-TAHAP respons (10): contain (network disconnect, bukan rm) → bukti (top/diff/commit/export+hash) →
                   eradicate (redeploy bersih) → rotasi rahasia → recover + pantau → lessons learned blameless.
      ```
      Setiap tahap punya pertahanan DAN titik deteksi — penyerang harus lolos di SETIAP tahap, sedangkan kamu cukup
      menangkap di SATU. Itulah kekuatan defense-in-depth sepanjang Kill Chain: banyak jala, bukan satu tembok.
    tests:
      pass:
        - |
          recon kurangi eksposur banner path port dengan server_tokens off dan deteksi lonjakan 404 scanning
          eksploit web kontrol owasp prepared statement otorisasi encoding allowlist dan waf dengan deteksi log 403 dan spike 5xx
          escape container cap-drop no-new-privileges non root tanpa docker socket dengan deteksi syscall falco
          persistence read only rootfs fim dan redeploy immutable dengan deteksi file dan proses asing docker diff
          exfiltrasi batasi egress internal allowlist dengan deteksi koneksi keluar c2
          lintas tahap respons ir contain network disconnect kumpulkan bukti rotasi rahasia dan lessons learned
          jangan anggap satu perimeter atau satu tahap melindungi segalanya
        - |
          recon reduksi eksposur plus deteksi scan 404, eksploit kontrol owasp dan waf plus log 403 5xx
          escape cap drop no new privileges non root no socket plus falco, persistence read only fim docker diff
          exfil egress internal allowlist plus monitor outbound c2, respons contain bukti rotasi lessons
      fail:
        - |
          satu firewall perimeter melindungi semua tahap
          tidak perlu deteksi di tiap tahap
        - |
          fokus hanya menghentikan recon lalu abaikan sisanya
          egress dan escape tidak penting
        - |
          anggap kalau tahap pertama lolos sudah pasti kalah
          tidak ada yang bisa dilakukan
    source: https://attack.mitre.org/

  - id: c03
    tier: C
    type: scenario
    objective: shiftleft
    prompt: |
      Tim startup kamu belum punya praktik keamanan sama sekali. Rancang program DevSecOps dari nol — teknis dan budaya
      — yang realistis dan berkelanjutan, bukan sekadar membeli tool (satu poin per baris).
    checklist:
      - id: threatmodel
        label: Mulai berbasis risiko — threat model aset bernilai tinggi dulu (01)
        required: true
        patterns: ['threat model|model ancaman|berbasis risiko', '(aset|data)[^\n]*(bernilai|tinggi|kritis|penting)', 'prioritas[^\n]*risiko']
      - id: gates
        label: Shift-left — gerbang CI dasar dulu (secret + image/IaC scan), perluas bertahap (11)
        required: true
        patterns: ['shift.?left|gerbang (ci|pipeline)', 'secret scan|trivy|iac scan|sca', 'bertahap|mulai (dasar|kecil)|incremental']
      - id: assumebreach
        label: Assume breach — logging/deteksi & rencana IR + backup sejak awal (09/10)
        required: true
        patterns: ['assume breach', 'logging|deteksi|monitoring', 'rencana ir|incident response|backup']
      - id: leastpriv
        label: Least privilege & secrets management sebagai default (03/04/06/11)
        patterns: ['least privilege|hak minimal', 'secret[^\n]*(manager|bukan hardcode|rotasi)', 'non-?root|cap-drop|token (pendek|scope)']
      - id: culture
        label: Budaya — tanggung jawab bersama, pelatihan, blameless, jalan aman = jalan mudah
        required: true
        patterns: ['(tanggung jawab bersama|shared responsibility|semua orang)', 'pelatihan|training|kesadaran|awareness', 'blameless|secure by default|jalan aman[^\n]*mudah']
      - id: metrics
        label: Ukur & iterasi — MTTD, cakupan gerbang, temuan; perbaiki berkelanjutan
        patterns: ['(ukur|metrik|metric)[^\n]*(mttd|cakupan|coverage|temuan)', '(iterasi|perbaiki|tingkatkan)[^\n]*(berkelanjutan|berkala|terus)', 'kpi|measure']
      - id: no-buytool
        label: Tidak sekadar membeli satu tool / menyerahkan keamanan ke satu orang di akhir
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain|lebih dari))[^\n]*(cukup|hanya|sekadar)[^\n]*(beli|pasang)[^\n]*(satu )?tool[^\n]*(selesai|saja|lalu)?']
    pass_score: 0.45
    solution: |
      ```text
      TEKNIS:
        1. Threat model berbasis risiko — lindungi aset bernilai tertinggi dulu (01); jangan sebar tipis.
        2. Shift-left bertahap — mulai gerbang CI dasar (secret scan + image/IaC scan), perluas (SCA, policy, sign) (11).
        3. Assume breach sejak awal — logging terpusat + alert + rencana IR sederhana + backup teruji (09/10).
        4. Least privilege & secrets management sebagai DEFAULT (non-root, token ber-scope, secret manager) (03/04/06/11).
      BUDAYA:
        5. Tanggung jawab bersama + pelatihan + blameless + "jalan aman = jalan mudah" (template/default aman).
        6. Ukur & iterasi — MTTD, cakupan gerbang, waktu perbaikan; perbaiki berkelanjutan dari insiden & metrik.
      ```
      Program yang berkelanjutan dimulai kecil & berbasis risiko, mengkodekan kontrol jadi guardrail otomatis, dan
      membangun budaya di mana keamanan milik semua. "Beli satu tool lalu selesai" atau "satu orang keamanan di akhir"
      gagal — keamanan adalah praktik berkelanjutan yang tertanam di proses & orang, bukan produk yang dibeli sekali.
    tests:
      pass:
        - |
          mulai berbasis risiko dengan threat model aset bernilai tinggi dulu
          shift left dengan gerbang ci dasar secret dan image iac scan lalu perluas bertahap
          assume breach dengan logging deteksi rencana ir dan backup sejak awal
          least privilege dan secrets management sebagai default non root token ber-scope
          budaya tanggung jawab bersama pelatihan blameless dan jalan aman jadi jalan mudah
          ukur dan iterasi mttd cakupan gerbang dan temuan untuk perbaikan berkelanjutan
          jangan sekadar membeli satu tool atau menyerahkan keamanan ke satu orang di akhir
        - |
          threat model risiko dulu, gerbang ci secret dan scan bertahap, logging dan rencana ir sejak awal
          least privilege dan secret manager default, budaya shared responsibility pelatihan dan blameless
          ukur mttd dan cakupan lalu iterasi, bukan sekadar beli tool
      fail:
        - |
          cukup beli satu tool keamanan mahal lalu selesai
          serahkan semua ke satu orang di akhir rilis
        - |
          tidak perlu budaya atau threat model
          pasang antivirus sudah cukup
        - |
          keamanan itu produk yang dibeli sekali
          tidak perlu deteksi ir atau iterasi
    source: https://cloud.google.com/docs/security/beyondprod
---

# Capstone — Pertahanan Menyeluruh

Sebelas modul, satu cara berpikir: **jangan percaya satu hal apa pun**. Modul penutup ini menyatukan perjalanan —
dari model ancaman (01) sampai gerbang pipeline (11) — menjadi gambaran pertahanan yang utuh. Tidak ada lab baru di
sini; setiap teknik yang dirujuk sudah kamu buktikan di lab modulnya. Ini saatnya **menghubungkan**.

> [!NOTE]
> Semua tetap defensif, pada target milik sendiri di lingkungan terisolasi (aturan etika modul 01). Memahami cara
> red team bekerja hanya untuk membangun pertahanan (blue) yang lebih baik.

## 1. Kill Chain → pertahanan berlapis tiap modul

| Tahap serangan | Pertahanan | Titik deteksi |
|---|---|---|
| Recon (02) | kurangi eksposur: `server_tokens off`, `return 404`, tutup port | lonjakan 404/scan (09) |
| Eksploit web (07) | prepared stmt, authz server-side, encoding/CSP, allowlist, WAF | 403 WAF, spike 5xx (09) |
| Escape container (06) | `--cap-drop ALL`, no-new-priv, non-root, tanpa `docker.sock` | Falco syscall/shell (09) |
| Persistence (06/10) | read-only rootfs, redeploy immutable | FIM, `docker diff`, proses asing (09) |
| Exfiltrasi (01) | egress `--internal`/allowlist | koneksi keluar/C2 (09) |

Penyerang harus menang di **setiap** tahap; kamu cukup menangkap di **satu**. Itu kekuatan defense-in-depth.

## 2. Cegah · Deteksi · Respons (assume breach)

Pencegahan pasti ada yang lolos (modul 01). Maka seimbangkan tiga pilar: **cegah** (modul 02–08, perkecil peluang &
dampak), **deteksi** (modul 09, lihat yang lolos), **respons** (modul 10, tangani cepat). Deteksi tanpa respons =
menonton diri dibobol; respons tanpa deteksi = tak pernah tahu kapan bertindak. Keduanya wajib, bersama recovery.

## 3. Benang merah: least privilege & perkecil blast radius

Satu prinsip, di mana-mana: SSH `AllowUsers`/key-only (03) · `--cap-drop ALL` + non-root (06) · DB non-superuser (07)
· token CI ber-scope & pendek (11) · egress dibatasi (01). Karena kamu **menganggap** sesuatu akan jebol, kamu
merancang agar kompromi satu komponen **terbatas** — "satu jebol = satu terkurung", bukan "semua jatuh".

## 4. Shift-left: pengetahuan → kode → budaya

Pelajaran modul 02–10 bertahan lama hanya jika **dikodekan** jadi gerbang otomatis (modul 11): secret scan, IaC/SCA
scan, policy-as-code, sign+SBOM — ditegakkan di setiap build, bukan bergantung ingatan. Dan alat butuh **budaya**:
tanggung jawab bersama, pelatihan, blameless (10), serta membuat **jalan aman = jalan mudah**.

## 5. Trade-off: keamanan yang proporsional

Keamanan punya biaya (gesekan, latensi, false positive). Kontrol yang merusak ketersediaan/kegunaan **juga**
kegagalan. Sikapi **berbasis risiko**: kontrol terkuat untuk aset & ancaman bernilai tertinggi; setel dari baseline;
jujuri batas (volumetrik butuh upstream, modul 08). Jangan "semaksimal mungkin" membabi buta — jangan pula teater
keamanan (gerbang dimatikan diam-diam). Seimbang, proporsional, dan terus di-tune.

## 6. Peta akhir — seluruh board dalam satu gambar

```text
IDENTIFY  (01) threat model, CIA/STRIDE, assume breach, aturan etika lab
PROTECT   (02) reduksi eksposur · (03) harden server/akses · (04) secrets · (05) image/supply chain ·
          (06) container runtime · (07) web OWASP · (08) availability/anti-DDoS
DETECT    (09) logging terstruktur terpusat, metrik, alert, deteksi runtime
RESPOND   (10) IR PICERL, forensik container, chain of custody, blameless
RECOVER   (10) backup 3-2-1 immutable, redeploy bersih, rotasi rahasia
OTOMASI   (11) shift-left: gerbang pipeline + policy-as-code + admission control
```

> [!NOTE]
> Board DevSecOps selesai (01–12). Kamu kini bisa berpikir seperti penyerang untuk membangun pertahanan yang lebih
> baik — berlapis, berbasis risiko, dengan asumsi bahwa sesuatu pasti jebol, dan dengan keamanan yang tertanam
> sebagai kode & budaya, bukan ditempel di akhir. Itulah DevSecOps.
