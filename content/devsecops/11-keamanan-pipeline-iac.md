---
format: 1
id: sec-pipeline-iac-security
course:
  slug: devsecops
  title: DevSecOps
  description: Keamanan untuk DevOps dari dua sisi — cara kerja red team dan cara blue team mendeteksi serta menutupnya. Model ancaman, hardening server & container, keamanan web, ketersediaan (anti-DDoS), deteksi, respons insiden, dan keamanan pipeline. Semua praktik di lab terisolasi milik sendiri.
title: Keamanan Pipeline & IaC
summary: "Cara terbaik menangani insiden adalah mencegahnya lahir — geser keamanan ke kiri (shift-left) ke dalam pipeline. Modul ini memasang gerbang otomatis: pindai misconfig IaC/Dockerfile (trivy config), kebijakan-sebagai-kode kustom (OPA/conftest), pindai rahasia (trivy secret), dan lint (hadolint) — semua menggagalkan build lewat exit code sebelum sampai produksi. Plus mengamankan pipeline itu sendiri (token hak-minimal, pin versi, runner terisolasi) karena CI memegang kredensial produksi. Semua gerbang dibuktikan di lab."
order: 11
version: 1
status: verified
updated: '2026-11-25'
tested_on:
  - Docker Desktop (Windows), Engine 29.4.1 — scanner dijalankan sebagai container --rm atas IaC sengaja-rentan (Dockerfile + pod.yaml) di /tmp/sec11
  - trivy config (misconfig), trivy fs --scanners secret, conftest/OPA (Rego kustom), hadolint — semua diverifikasi menggagalkan build (exit 1) vs versi diperbaiki lolos (exit 0)
  - 'Catatan - board CI/CD runner (act) ditunda sejak 2026-11-23 (unduhan act diblokir classifier); modul ini menjalankan GERBANG-nya langsung sebagai container, bukan runner lokal'
objectives:
  - id: shiftleft
    text: Memahami shift-left — menutup celah di pipeline sebelum produksi
  - id: iacscan
    text: Memindai misconfig IaC/Dockerfile otomatis (trivy config/checkov)
  - id: policy
    text: Menegakkan kebijakan-sebagai-kode kustom (OPA/conftest)
  - id: secrets
    text: Memasang gerbang pemindai rahasia di CI
  - id: gates
    text: Membuat gerbang CI yang menggagalkan build (exit code), bukan sekadar peringatan
  - id: supplychain
    text: Mengamankan pipeline itu sendiri (token hak-minimal, pin versi, runner terisolasi)
sources:
  - title: OWASP — CI/CD Security / Top 10 CI/CD Security Risks
    url: https://owasp.org/www-project-top-10-ci-cd-security-risks/
  - title: Trivy — misconfiguration & secret scanning
    url: https://trivy.dev/latest/docs/scanner/misconfiguration/
  - title: Open Policy Agent (OPA) & Rego
    url: https://www.openpolicyagent.org/docs/latest/
  - title: Conftest — test configuration with OPA policies
    url: https://www.conftest.dev/
  - title: hadolint — Dockerfile linter
    url: https://github.com/hadolint/hadolint
  - title: SLSA — Supply-chain Levels for Software Artifacts
    url: https://slsa.dev/
  - title: MITRE ATT&CK — Supply Chain Compromise (T1195)
    url: https://attack.mitre.org/techniques/T1195/
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: shiftleft
    prompt: Apa arti "shift left" dalam DevSecOps?
    answers: ['menggeser pemeriksaan keamanan lebih awal ke tahap dev/ci, bukan menunggu produksi']
    patterns: ['(geser|pindah|lebih awal|dini|early)[^\n]*(keamanan|security|cek|pemeriksaan|uji)', '(dev|ci|pipeline|coding|lebih awal)[^\n]*(bukan|sebelum)[^\n]*(produksi|akhir|rilis)', 'kiri[^\n]*(awal|dini|dev)']
    tests:
      fail: ['memindahkan server ke kiri', 'menunda keamanan sampai produksi', 'menyerahkan keamanan ke tim lain', 'mematikan pemeriksaan']
    explanation: '"Shift left" = menggeser aktivitas keamanan **lebih awal** (ke kiri) dalam siklus pengembangan — ke saat coding, commit, dan CI — alih-alih baru memeriksa di produksi (kanan). Daripada menemukan container privileged atau rahasia hardcoded setelah di-deploy (dan jadi insiden, modul 10), kamu menangkapnya di pull request. Modul 01–10 mengajari cara menutup celah; modul ini mengotomasi penutupan itu sebagai **gerbang pipeline** sehingga celah tak pernah lolos ke produksi. Cepat, konsisten, dan tak bergantung ingatan manusia.'
    source: https://owasp.org/www-project-top-10-ci-cd-security-risks/

  - id: a02
    tier: A
    type: text
    objective: shiftleft
    prompt: Mengapa memperbaiki masalah keamanan lebih awal (di pipeline) jauh lebih murah daripada di produksi?
    answers: ['di produksi sudah jadi insiden — kerusakan, downtime, respons, kehilangan data; di pipeline hanya perbaikan kode kecil']
    patterns: ['(produksi|akhir)[^\n]*(insiden|kerusakan|mahal|downtime|breach|data)', '(awal|pipeline|pr)[^\n]*(murah|kecil|cepat|mudah)[^\n]*(perbaik|fix)', 'makin (lama|lambat|jauh)[^\n]*makin mahal']
    tests:
      fail: ['biaya sama saja di mana pun', 'produksi lebih murah', 'tidak ada perbedaan biaya', 'lebih mahal memperbaiki di pipeline']
    explanation: 'Biaya perbaikan naik tajam semakin jauh ke kanan. Di **pipeline**: celah = baris kode/konfigurasi yang diperbaiki sebelum merge — menit, tanpa dampak. Di **produksi**: celah yang sama bisa jadi breach — downtime, data bocor, respons insiden (modul 10), rotasi kredensial, kehilangan kepercayaan, mungkin denda regulasi. Gerbang otomatis yang menangkap di PR mengubah "insiden mahal" jadi "komentar review murah". Ini argumen bisnis shift-left: investasi kecil di kiri menghindari biaya besar di kanan.'
    source: https://owasp.org/www-project-top-10-ci-cd-security-risks/

  - id: a03
    tier: A
    type: text
    objective: gates
    prompt: Bagaimana sebuah scanner keamanan "menggagalkan" (mem-block) pipeline CI?
    answers: ['mengembalikan exit code non-zero sehingga step CI gagal dan merge/deploy diblokir']
    patterns: ['exit code[^\n]*(non-?zero|bukan nol|1|gagal)', '(kembalikan|return|keluar)[^\n]*(non-?zero|kode 1|gagal)', '(step|job|build)[^\n]*(gagal|fail)[^\n]*(blokir|hentikan|merge|deploy)']
    tests:
      fail: ['mengirim email saja', 'menampilkan warning lalu lanjut', 'tidak bisa memblokir apa pun', 'mematikan server']
    explanation: 'CI menilai sukses/gagal tiap step dari **exit code**-nya: `0` = sukses, non-zero = gagal. Scanner yang menemukan masalah dikonfigurasi mengembalikan exit code non-zero (mis. `--exit-code 1`), sehingga step gagal → pipeline merah → merge/deploy diblokir. Di lab, `trivy config` dengan temuan keluar dengan **exit 1** (build harus gagal), sedangkan versi yang diperbaiki keluar `0` (lolos). Ini mekanisme inti gerbang: temuan = build merah, bukan sekadar peringatan yang bisa diabaikan.'
    source: https://trivy.dev/latest/docs/scanner/misconfiguration/

  - id: a04
    tier: A
    type: text
    objective: iacscan
    prompt: Apa itu Infrastructure as Code (IaC), dan kenapa IaC bisa (dan harus) dipindai keamanannya?
    answers: ['infrastruktur didefinisikan sebagai file kode (dockerfile, k8s yaml, terraform); karena berupa teks, bisa dipindai misconfig otomatis sebelum deploy']
    patterns: ['infrastruktur[^\n]*(kode|file|teks|dockerfile|terraform|yaml)', '(karena|berupa) (kode|teks|file)[^\n]*(pindai|scan|periksa)', '(misconfig|salah konfigurasi)[^\n]*(sebelum|dini|deploy)']
    tests:
      fail: ['iac adalah bahasa pemrograman baru', 'infrastruktur tidak bisa dipindai', 'iac hanya dokumentasi', 'kode aplikasi saja']
    explanation: 'IaC mendefinisikan infrastruktur sebagai **file kode** — Dockerfile, Compose, manifest Kubernetes, Terraform, dll. Karena infrastruktur kini teks yang di-versioning, ia bisa **dipindai otomatis** untuk salah-konfigurasi sebelum pernah di-deploy: container berjalan root, image `:latest`, port sensitif terbuka, pod privileged. Di lab, Dockerfile & pod.yaml yang sengaja buruk dipindai dan misconfig-nya ditemukan di pipeline — bukan setelah jadi masalah di produksi. "Infrastruktur sebagai kode" berarti "keamanan infrastruktur sebagai pemeriksaan kode".'
    source: https://trivy.dev/latest/docs/scanner/misconfiguration/

  - id: a05
    tier: A
    type: command
    objective: iacscan
    prompt: Pindai direktori `iac/` yang berisi Dockerfile & manifest Kubernetes untuk salah-konfigurasi dengan Trivy.
    answers: ['trivy config iac/']
    patterns: ['^trivy[ \t]+config[ \t]+\S+']
    tests:
      pass:
        - 'trivy config iac/'
        - 'trivy config ./iac'
        - 'trivy config --exit-code 1 iac/'
      fail:
        - 'trivy image iac/'
        - 'trivy fs iac/'
        - 'trivy iac/'
    explanation: '`trivy config <dir>` memindai file IaC (Dockerfile, Compose, k8s, Terraform, Helm) untuk salah-konfigurasi keamanan. Di lab, atas `iac/` ia menemukan **6 misconfig di Dockerfile** (1 CRITICAL, 2 HIGH: `:latest` tanpa pin, tidak ada `USER` non-root, port 22 terbuka, tanpa HEALTHCHECK) dan **20 di pod.yaml** (privileged, runAsNonRoot belum di-set, dll). Tambah `--exit-code 1` agar menggagalkan build. Beda `trivy config` (IaC) dengan `trivy image` (CVE image, modul 05) dan `trivy fs` (filesystem/secret).'
    source: https://trivy.dev/latest/docs/scanner/misconfiguration/

  - id: a06
    tier: A
    type: text
    objective: iacscan
    prompt: Sebutkan satu contoh salah-konfigurasi Dockerfile yang akan ditangkap pemindai IaC.
    answers: ['pakai tag :latest tanpa pin, tidak ada USER non-root, atau membuka port 22']
    patterns: [':latest|tag[^\n]*(latest|tidak di-?pin|mengambang)', '(tidak ada|tanpa)[^\n]*(user|non-?root)|jalan sebagai root', 'port 22|expose 22|ssh', 'tanpa healthcheck|rahasia[^\n]*(hardcoded|env)']
    tests:
      fail: ['memakai multi-stage build', 'menambahkan LABEL', 'memakai COPY bukan ADD', 'menjalankan sebagai non-root']
    explanation: 'Contoh misconfig Dockerfile (dari lab, bersambung dengan modul 05 & 06): **`FROM python:latest`** (tag mengambang → build tak deterministik, bisa menarik versi rentan — pin ke digest/versi), **tidak ada `USER`** (container berjalan root → risiko escape, modul 06), **`EXPOSE 22`** (SSH dalam container tak wajar), **tanpa HEALTHCHECK**, dan **rahasia di `ENV`** (modul 04). Pemindai IaC menyandikan praktik-praktik modul sebelumnya menjadi aturan otomatis — kamu tak perlu mengingat semuanya, pipeline yang ingat.'
    source: https://trivy.dev/latest/docs/scanner/misconfiguration/

  - id: a07
    tier: A
    type: text
    objective: policy
    prompt: Apa itu "policy as code" (kebijakan sebagai kode)?
    answers: ['aturan/kebijakan organisasi ditulis sebagai kode yang bisa dievaluasi otomatis (mis. OPA/rego)']
    patterns: ['(aturan|kebijakan|policy)[^\n]*(ditulis|dituliskan|sebagai|jadi|menjadi|dikodekan jadi)[^\n]*(kode|file)', 'opa|rego|conftest|gatekeeper|kyverno', '(tulis|definisikan)[^\n]*(aturan|kebijakan)[^\n]*(kode|versi)']
    tests:
      fail: ['kebijakan di dokumen word', 'aturan di kepala admin', 'kebijakan tidak bisa dikodekan', 'hanya untuk aplikasi']
    explanation: 'Policy-as-code menuliskan aturan organisasi (mis. "tidak boleh container privileged", "image wajib ber-tag tetap", "wajib ada label owner") sebagai **kode** yang bisa dievaluasi otomatis — bukan dokumen kebijakan yang diabaikan. Alat umum: **OPA** (Open Policy Agent) dengan bahasa **Rego**, dijalankan via **conftest** di CI atau **Gatekeeper/Kyverno** di admission Kubernetes. Manfaatnya seperti detection-as-code (modul 09): konsisten, di-versioning, di-review, diuji. Kebijakan jadi gerbang yang menegakkan dirinya, bukan imbauan.'
    source: https://www.openpolicyagent.org/docs/latest/

  - id: a08
    tier: A
    type: command
    objective: policy
    prompt: Uji file `iac/pod.yaml` terhadap kebijakan Rego di direktori `policy/` menggunakan conftest.
    answers: ['conftest test iac/pod.yaml -p policy/']
    patterns: ['^(?=.*[ \t]-p[ \t]+\S+)conftest[ \t]+test[ \t]+\S+']
    tests:
      pass:
        - 'conftest test iac/pod.yaml -p policy/'
        - 'conftest test -p policy/ iac/pod.yaml'
      fail:
        - 'conftest test iac/pod.yaml'
        - 'opa test policy/'
        - 'conftest verify policy/'
    explanation: '`conftest test <file> -p <policy-dir>` mengevaluasi file konfigurasi terhadap aturan Rego (OPA). Di lab, kebijakan kustom menolak pod privileged dan image `:latest`; terhadap `pod.yaml` yang buruk conftest memberi **2 FAIL** (`must not run privileged`, `must not use :latest`) dan keluar **exit 1** (build gagal), sedangkan `pod-fixed.yaml` (tag di-pin, non-root, tidak privileged) **2 passed, exit 0**. Inilah policy-as-code dalam aksi: aturan organisasimu sendiri jadi gerbang otomatis, melengkapi aturan bawaan scanner.'
    source: https://www.conftest.dev/

  - id: a09
    tier: A
    type: text
    objective: policy
    prompt: Bahasa yang dipakai untuk menulis kebijakan Open Policy Agent (OPA) bernama apa?
    answers: ['rego']
    patterns: ['\brego\b']
    tests:
      fail: ['yaml', 'python', 'hcl', 'json']
    explanation: 'Rego adalah bahasa deklaratif OPA untuk menyatakan kebijakan: kamu mendefinisikan aturan `deny`/`allow` yang mengevaluasi input (JSON/YAML konfigurasi). Di lab, aturan Rego sederhana seperti `deny contains msg if { input.kind == "Pod"; some c in input.spec.containers; c.securityContext.privileged == true; ... }` menangkap pod privileged. Rego dipakai lintas konteks: conftest (CI), Gatekeeper (admission k8s), hingga otorisasi aplikasi — satu bahasa kebijakan untuk banyak titik penegakan.'
    source: https://www.openpolicyagent.org/docs/latest/

  - id: a10
    tier: A
    type: command
    objective: secrets
    prompt: Pindai direktori `iac/` untuk rahasia yang ter-hardcode (mis. API key) dengan Trivy.
    answers: ['trivy fs --scanners secret iac/']
    patterns: ['^(?=.*--scanners[ \t]+secret)trivy[ \t]+fs[ \t]']
    tests:
      pass:
        - 'trivy fs --scanners secret iac/'
        - 'trivy fs --scanners secret --exit-code 1 iac/'
      fail:
        - 'trivy config iac/'
        - 'trivy fs iac/'
        - 'trivy image --scanners secret iac/'
    explanation: '`trivy fs --scanners secret <path>` memindai berkas untuk kredensial ter-hardcode. Di lab, ia menemukan `ENV API_KEY=sk_live_...` dan melaporkannya sebagai **CRITICAL: Stripe (stripe-secret-token)** — dengan nilainya **otomatis disamarkan** di output (tidak membocorkan rahasia lebih jauh). Jadikan ini gerbang CI (bersambung dengan modul 04): rahasia tak boleh pernah masuk repo. Pasangkan dengan pre-commit hook agar tertangkap bahkan sebelum commit. Alat lain: detect-secrets, gitleaks, trufflehog.'
    source: https://trivy.dev/latest/docs/scanner/secret/

  - id: a11
    tier: A
    type: text
    objective: secrets
    prompt: Selain di CI, di titik mana lagi pemindaian rahasia sebaiknya dipasang agar rahasia tak pernah sampai ke repo?
    answers: ['pre-commit hook (dan server-side/pre-receive) — sebelum commit/push diterima']
    patterns: ['pre-?commit', 'pre-?receive|server-?side hook', '(sebelum|saat) (commit|push)[^\n]*(tolak|cegah|tangkap)', 'git hook']
    tests:
      fail: ['hanya di produksi', 'setelah deploy', 'tidak perlu di tempat lain', 'di log saja']
    explanation: 'Pasang pemindai rahasia di **pre-commit hook** (lokal, menolak commit yang mengandung rahasia sebelum tercatat di history) dan idealnya juga **server-side/pre-receive hook** (menolak push). Sekali rahasia masuk history git, menghapusnya sulit (dan harus dianggap sudah bocor → rotasi, modul 04/10). Berlapis: pre-commit (paling kiri) → CI gate (jaring kedua) → pemindaian berkala. Tujuannya mencegah rahasia **pernah** tercatat, bukan sekadar menemukannya setelah terlambat.'
    source: https://owasp.org/www-project-top-10-ci-cd-security-risks/

  - id: a12
    tier: A
    type: command
    objective: iacscan
    prompt: Lint sebuah `Dockerfile` untuk praktik buruk dengan hadolint.
    answers: ['hadolint Dockerfile']
    patterns: ['^hadolint[ \t]+\S+']
    tests:
      pass:
        - 'hadolint Dockerfile'
        - 'hadolint ./Dockerfile'
      fail:
        - 'docker lint Dockerfile'
        - 'lint Dockerfile'
        - 'hadolint'
    explanation: '`hadolint <Dockerfile>` memeriksa Dockerfile terhadap aturan praktik terbaik. Di lab, ia menandai `DL3007` (pakai `latest`), `DL3008` (pin versi paket apt), `DL3064` (data sensitif di `ENV`/`ARG`), dll, dan keluar **exit 1**. hadolint cepat dan ringan — gerbang CI murah yang menangkap banyak masalah Dockerfile dasar sebelum build. Jalankan bersama `trivy config` (misconfig keamanan) dan secret scan; masing-masing menangkap kelas masalah berbeda.'
    source: https://github.com/hadolint/hadolint

  - id: a13
    tier: A
    type: text
    objective: supplychain
    prompt: Mengapa versi action CI dan dependency harus di-pin (ke versi/digest tetap), bukan dibiarkan mengambang (`@latest`/floating)?
    answers: ['versi mengambang bisa berubah diam-diam menjadi versi ter-kompromi/rentan; pin membuat build deterministik & auditable']
    patterns: ['(mengambang|latest|floating)[^\n]*(berubah|diam-diam|kompromi|rentan|diganti)', 'pin[^\n]*(deterministik|tetap|auditable|konsisten)', '(digest|hash|versi tetap)[^\n]*(aman|kontrol|tak berubah)']
    tests:
      fail: ['latest selalu paling aman', 'pin tidak ada gunanya', 'versi tidak pernah berubah', 'mengambang lebih deterministik']
    explanation: 'Action/dependency yang mengambang (`actions/checkout@main`, `image:latest`, `pkg@^1.0`) bisa **berubah diam-diam** — maintainer meng-update, atau akunnya diretas dan versi ter-trojan didorong (supply chain, T1195). Build-mu tiba-tiba menjalankan kode berbeda tanpa kamu mengubah apa pun. **Pin ke versi/digest tetap** (`@v4.1.1` atau `@<sha>`) membuat build **deterministik** dan **auditable**: kamu tahu persis apa yang berjalan, dan perubahan harus eksplisit lewat PR (yang bisa di-review). Bersambung dengan digest pinning image di modul 05.'
    source: https://attack.mitre.org/techniques/T1195/

  - id: a14
    tier: A
    type: text
    objective: supplychain
    prompt: Prinsip apa yang harus diterapkan pada token/kredensial yang dipakai pipeline CI?
    answers: ['least privilege — hak minimal, scope sempit, dan berumur pendek/ephemeral']
    patterns: ['least privilege|hak minimal|privilege minimal', '(scope|lingkup)[^\n]*(sempit|terbatas|minimal)', '(pendek|sementara|ephemeral|short-?lived|sekali pakai)[^\n]*(token|kredensial)']
    tests:
      fail: ['beri akses admin penuh', 'token berumur selamanya', 'satu token untuk semua', 'scope seluas mungkin']
    explanation: 'CI sering memegang kredensial kuat (deploy ke prod, push image, akses cloud) — jadikan ia **least privilege**: scope sekecil mungkin (hanya repo/registry/resource yang perlu), **berumur pendek/ephemeral** (token sementara via OIDC, bukan kunci statis abadi), dan terpisah per-pipeline. Jika pipeline atau satu job jahat ter-kompromi, kerusakan terbatas. Token admin abadi di CI = satu kebocoran, kiamat. Ini prinsip modul 03/06 (least privilege) diterapkan pada identitas pipeline.'
    source: https://owasp.org/www-project-top-10-ci-cd-security-risks/

  - id: a15
    tier: A
    type: text
    objective: supplychain
    prompt: Bagaimana memastikan artefak yang di-deploy benar-benar yang dibangun pipeline tepercaya (bukan diselundupkan)?
    answers: ['tandatangani artefak/image di pipeline (mis. cosign) dan verifikasi tanda tangan + provenance saat deploy']
    patterns: ['(tanda ?tangan|sign|signing)[^\n]*(image|artefak|artifact)', 'cosign|sigstore', '(verifikasi|verify)[^\n]*(tanda ?tangan|signature|provenance|deploy)', 'provenance|slsa|attestation']
    tests:
      fail: ['percaya saja semua image', 'tidak perlu verifikasi', 'tag sudah cukup membuktikan', 'deploy apa pun yang ada']
    explanation: 'Tandatangani artefak di pipeline (mis. `cosign sign`, modul 05) dan **verifikasi tanda tangan + provenance** saat deploy — hanya image yang ditandatangani oleh pipeline tepercaya yang boleh berjalan. Ini mencegah penyerang menyelundupkan image jahat ke registry/cluster. Tambahkan **provenance/attestation** (SLSA): bukti terverifikasi tentang *bagaimana & dari mana* artefak dibangun (commit, builder). Bersama pin versi, SBOM (modul 05), dan verifikasi di admission, ini membentuk rantai pasok yang bisa dipercaya dari commit sampai runtime.'
    source: https://slsa.dev/

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: command
    objective: gates
    prompt: Jalankan pemindai rahasia pada `iac/` sebagai **gerbang yang menggagalkan build** bila ada temuan.
    answers: ['trivy fs --scanners secret --exit-code 1 iac/']
    patterns: ['^(?=.*--scanners[ \t]+secret)(?=.*--exit-code[ \t]+1)trivy[ \t]+fs[ \t]']
    tests:
      pass:
        - 'trivy fs --scanners secret --exit-code 1 iac/'
        - 'trivy fs --exit-code 1 --scanners secret iac/'
      fail:
        - 'trivy fs --scanners secret iac/'
        - 'trivy fs --exit-code 0 --scanners secret iac/'
        - 'trivy config --exit-code 1 iac/'
    explanation: '`--exit-code 1` membuat Trivy keluar dengan kode 1 **jika ada temuan** — sehingga step CI gagal dan merge diblokir. Tanpa flag ini, Trivy melaporkan tapi tetap keluar `0` (build hijau, temuan diabaikan) — itu sekadar "peringatan", bukan gerbang. Inti dari gerbang keamanan: temuan harus **memblokir**, bukan hanya dicatat. Setel ambang keparahan bila perlu (`--severity CRITICAL,HIGH`) agar gerbang memblokir yang serius dan tidak berisik untuk yang remeh.'
    source: https://trivy.dev/latest/docs/scanner/misconfiguration/

  - id: b02
    tier: B
    type: text
    objective: gates
    prompt: Kapan dan seberapa sering gerbang keamanan pipeline sebaiknya dijalankan?
    answers: ['pada setiap pull request/commit dan secara berkala (scheduled), bukan sesekali saja']
    patterns: ['(setiap|tiap)[^\n]*(pr|pull request|commit|merge|push)', 'berkala|scheduled|terjadwal|rutin', '(bukan|tidak) (sesekali|kadang|manual)']
    tests:
      fail: ['sekali setahun', 'hanya saat ingat', 'tidak perlu otomatis', 'hanya setelah insiden']
    explanation: 'Gerbang harus jalan **pada setiap PR/commit** (menangkap sebelum merge) dan **berkala/terjadwal** (karena CVE baru muncul untuk kode yang tak berubah — image kemarin bersih bisa rentan hari ini, modul 05). Otomatis & konsisten adalah kuncinya — pemindaian "saat ingat" meninggalkan celah. Temuan ditangani: diperbaiki, atau dikecualikan secara **eksplisit & ter-review** (baseline/ignore file dengan alasan), bukan diabaikan diam-diam. Konsistensi membuat keamanan terukur, bukan untung-untungan.'
    source: https://owasp.org/www-project-top-10-ci-cd-security-risks/

  - id: b03
    tier: B
    type: text
    objective: policy
    prompt: Apa beda scanner bawaan (mis. trivy config) dengan policy-as-code (OPA/conftest)?
    answers: ['scanner punya aturan umum siap pakai; policy-as-code menegakkan aturan kustom spesifik organisasimu']
    patterns: ['scanner[^\n]*(umum|bawaan|siap pakai|generik|known)', 'policy[^\n]*(menegakkan|tulis|definisikan|untuk|berisi)[^\n]*(kustom|spesifik|organisasi|sendiri|custom)', '(aturan sendiri|aturanmu|kebijakan sendiri|aturan kustom)']
    tests:
      fail: ['keduanya identik', 'policy-as-code tidak bisa dikustom', 'scanner lebih fleksibel dari opa', 'tidak ada bedanya']
    explanation: 'Keduanya saling melengkapi. **Scanner** (trivy config, checkov, kics) datang dengan pustaka **aturan umum siap pakai** (praktik terbaik industri: jangan root, jangan `:latest`, dll) — cepat dipasang, cakupan luas. **Policy-as-code** (OPA/conftest) kamu tulis sendiri untuk **aturan spesifik organisasimu** yang tak ada di scanner umum: "semua image harus dari registry internal", "wajib label `cost-center`", "hanya base image yang disetujui". Pakai scanner untuk dasar + policy-as-code untuk kebijakan khususmu.'
    source: https://www.conftest.dev/

  - id: b04
    tier: B
    type: text
    objective: secrets
    prompt: Jika pemindai menemukan rahasia yang sudah terlanjur di-commit, apa yang WAJIB dilakukan (selain menghapusnya)?
    answers: ['anggap rahasia sudah bocor dan rotasi/cabut segera; menghapus dari history saja tidak cukup']
    patterns: ['(rotasi|cabut|ganti|revoke)[^\n]*(rahasia|secret|token|kunci|kredensial)', '(anggap|asumsi)[^\n]*(bocor|dicuri|kompromi)', '(hapus (dari )?history)[^\n]*(tidak cukup|saja (tidak|tak))']
    tests:
      fail: ['cukup hapus dari commit terakhir', 'rahasia masih aman dipakai', 'tidak perlu rotasi', 'ganti nama variabelnya saja']
    explanation: 'Rahasia yang pernah masuk repo harus **dianggap sudah bocor** — ia mungkin sudah ter-clone, ter-cache, ter-index, atau terlihat orang. Menghapusnya dari commit/history **tidak cukup** (dan history git sulit benar-benar dibersihkan). Yang WAJIB: **rotasi/cabut rahasia itu segera** (seperti modul 04/10), lalu bersihkan history dan tambahkan ke pemindai agar tak terulang. "Sudah kuhapus dari Git" adalah rasa aman palsu — kredensialnya tetap valid sampai kamu mencabutnya.'
    source: https://owasp.org/www-project-top-10-ci-cd-security-risks/

  - id: b05
    tier: B
    type: text
    objective: shiftleft
    prompt: Jelaskan singkat beda SAST, DAST, dan SCA.
    answers: ['sast: analisis kode sumber statis; dast: uji aplikasi yang berjalan dari luar; sca: pindai dependency/library untuk cve']
    patterns: ['sast[^\n]*(kode|source|statis)', 'dast[^\n]*(berjalan|runtime|luar|dinamis)', 'sca[^\n]*(dependency|library|dependensi|cve|komponen)']
    tests:
      fail: ['ketiganya sama', 'semua menguji kode sumber', 'tidak ada bedanya', 'semua hanya untuk jaringan']
    explanation: 'Tiga kelas uji keamanan aplikasi di pipeline: **SAST** (Static Application Security Testing) menganalisis **kode sumber** tanpa menjalankannya — menemukan pola rawan (SQLi, XSS, modul 07). **DAST** (Dynamic) menguji **aplikasi yang berjalan** dari luar seperti penyerang. **SCA** (Software Composition Analysis) memindai **dependency/library** untuk CVE yang diketahui (modul 05 — `trivy image` sebagian masuk kategori ini). Ketiganya menangkap kelas berbeda; pipeline matang memakai gabungan, plus IaC scan & secret scan.'
    source: https://owasp.org/www-project-top-10-ci-cd-security-risks/

  - id: b06
    tier: B
    type: text
    objective: supplychain
    prompt: Kontrol repository apa yang memastikan kode hanya masuk ke branch utama setelah di-review dan lolos gerbang CI?
    answers: ['protected branch dengan required reviews + required status checks sebelum merge']
    patterns: ['protected branch|branch protection|branch terlindung', 'required review|wajib (review|approval)|butuh persetujuan', 'required (status )?check|wajib[^\n]*(ci|check|lolos)[^\n]*(sebelum|merge)']
    tests:
      fail: ['siapa saja boleh push langsung ke main', 'tidak perlu review', 'merge tanpa cek', 'nonaktifkan semua proteksi']
    explanation: '**Protected branches** mencegah push langsung ke `main` dan mewajibkan: **pull request + review/approval** (mata kedua, cegah perubahan jahat/keliru) dan **required status checks** (gerbang CI — scan, test, lint — harus hijau sebelum merge). Tanpa ini, seseorang (atau penyerang dengan kredensial curian) bisa mendorong kode langsung ke produksi, melewati semua pemeriksaan. Kombinasikan dengan pembatasan siapa yang boleh approve/merge dan signed commits. Gerbang CI hanya berguna jika merge **tak bisa** terjadi tanpa melewatinya.'
    source: https://owasp.org/www-project-top-10-ci-cd-security-risks/

  - id: b07
    tier: B
    type: text
    objective: supplychain
    prompt: Mengapa pipeline CI/CD sendiri merupakan target serangan bernilai tinggi, dan satu cara mengerasnya?
    answers: ['ci memegang kredensial prod & bisa deploy; kuatkan dgn runner ephemeral/terisolasi, tanpa docker.sock, hak minimal']
    patterns: ['(ci|pipeline)[^\n]*(kredensial|akses|deploy)[^\n]*(prod|produksi)', '(runner)[^\n]*(ephemeral|terisolasi|sekali pakai|non-?privileged)', '(tanpa|jangan)[^\n]*docker\.?sock|hak minimal|least privilege']
    tests:
      fail: ['ci tidak menarik bagi penyerang', 'runner harus privileged penuh', 'mount docker.sock selalu aman', 'ci tidak punya akses apa pun']
    explanation: 'Pipeline adalah target empuk: ia memegang **kredensial produksi**, bisa **men-deploy kode ke mana saja**, dan menjalankan kode dari banyak kontributor. Kompromi CI = kompromi semua yang bisa di-deploy-nya. Keraskan: **runner ephemeral & terisolasi** (sekali pakai, hancur setelah job), **tanpa mount docker.sock / bukan privileged** (modul 06 — itu jalan escape ke host), kredensial **hak minimal & berumur pendek** (a14), dan jaringan terbatas. Perlakukan CI seperti sistem produksi paling sensitif, karena memang begitu.'
    source: https://owasp.org/www-project-top-10-ci-cd-security-risks/

  - id: b08
    tier: B
    type: text
    objective: supplychain
    prompt: Apa risiko menjalankan CI dengan akses ke rahasia pada pull request dari kontributor yang tak tepercaya?
    answers: ['pr jahat bisa menjalankan kode di pipeline untuk mencuri rahasia (poisoned pipeline execution)']
    patterns: ['pr[^\n]*(jahat|tak tepercaya|asing)[^\n]*(jalankan|eksekusi|curi)', '(curi|exfil|bocor)[^\n]*(rahasia|secret|kredensial|token)', 'poisoned pipeline|ppe|pipeline execution']
    tests:
      fail: ['pr dari luar selalu aman', 'rahasia tidak bisa dicuri lewat ci', 'tidak ada risiko', 'kode pr tidak dijalankan']
    explanation: 'Jika PR dari fork/kontributor tak tepercaya memicu pipeline yang **punya akses ke rahasia**, penyerang bisa menyisipkan kode (dalam build script/test) yang **mengeksfiltrasi rahasia itu** — ini "Poisoned Pipeline Execution". Mitigasi: jangan ekspos rahasia ke workflow yang dipicu PR fork, wajibkan approval maintainer sebelum CI jalan untuk kontributor baru, pisahkan pipeline "build & test PR" (tanpa rahasia) dari "deploy" (dengan rahasia, hanya dari branch tepercaya). Perlakukan kode PR eksternal sebagai tak tepercaya — karena memang begitu.'
    source: https://owasp.org/www-project-top-10-ci-cd-security-risks/

  - id: b09
    tier: B
    type: text
    objective: iacscan
    prompt: Saat scanner menemukan misconfig (mis. container root), apa pendekatan yang benar — dan apa yang harus dihindari?
    answers: ['perbaiki di sumber (tambah USER, pin tag); hindari menekan/suppress temuan tanpa alasan']
    patterns: ['perbaiki[^\n]*(sumber|dockerfile|kode|akar)', '(tambah|set)[^\n]*(user|non-?root)|pin[^\n]*(tag|versi)', '(jangan|hindari)[^\n]*(suppress|abaikan|matikan|skip)[^\n]*(temuan|aturan|tanpa alasan)']
    tests:
      fail: ['selalu suppress semua temuan', 'matikan scanner-nya', 'abaikan tanpa catatan', 'turunkan severity diam-diam']
    explanation: 'Pendekatan benar: **perbaiki di sumber** — tambahkan `USER` non-root, pin tag/digest, buang port/secret (modul 05/06). Yang harus dihindari: **menekan temuan secara membabi buta** (menonaktifkan aturan, menurunkan severity diam-diam, atau meng-ignore tanpa alasan) hanya agar build hijau — itu mengubah gerbang jadi teater. Jika sebuah temuan memang false-positive atau risiko yang diterima, catat pengecualian **eksplisit dengan alasan & pemilik** (ignore file ter-review). Gerbang yang di-bypass diam-diam lebih buruk daripada tak ada gerbang (memberi rasa aman palsu).'
    source: https://trivy.dev/latest/docs/scanner/misconfiguration/

  - id: b10
    tier: B
    type: text
    objective: policy
    prompt: Apa itu admission controller (OPA Gatekeeper/Kyverno) di Kubernetes, dan hubungannya dengan gerbang CI?
    answers: ['menegakkan kebijakan saat deploy (menolak resource yang melanggar) — gerbang runtime pelengkap gerbang ci']
    patterns: ['(tolak|blokir|cegah)[^\n]*(resource|pod|deploy)[^\n]*(melanggar|langgar|policy)', '(saat|waktu) (deploy|admission|apply)[^\n]*(kebijakan|policy|tegak)', 'gatekeeper|kyverno', 'runtime gate|gerbang (runtime|deploy)[^\n]*(ci|pipeline)']
    tests:
      fail: ['hanya untuk logging', 'sama persis dengan ci dan menggantikannya', 'tidak menegakkan apa pun', 'hanya mempercepat deploy']
    explanation: 'Admission controller (OPA **Gatekeeper**, **Kyverno**) mengevaluasi resource Kubernetes **saat di-apply** ke cluster dan **menolak** yang melanggar kebijakan (mis. pod privileged, image tak ditandatangani). Ia gerbang **di titik deploy/runtime** — pelengkap gerbang **CI** (saat build/PR). Kenapa butuh keduanya? Seseorang bisa `kubectl apply` langsung melewati CI, atau config berubah di luar pipeline; admission control menegakkan kebijakan **apa pun jalurnya**. Defense-in-depth: policy-as-code di kiri (CI) DAN di kanan (admission).'
    source: https://www.openpolicyagent.org/docs/latest/

  - id: b11
    tier: B
    type: text
    objective: shiftleft
    prompt: Mengapa gerbang keamanan sebaiknya ditulis & dikelola sebagai kode di version control (bukan diklik di UI)?
    answers: ['agar konsisten, di-review lewat pr, diuji, bisa di-rollback, dan terlacak — seperti kode aplikasi']
    patterns: ['(version control|git|repo|kode)[^\n]*(review|pr|uji|rollback|terlacak)', '(konsisten|reproducible|terlacak|auditable)', '(seperti|layaknya) kode (aplikasi)?']
    tests:
      fail: ['klik manual di ui lebih baik', 'tidak perlu version control', 'gerbang tidak bisa diuji', 'konfigurasi manual lebih aman']
    explanation: 'Gerbang & kebijakan sebagai kode di version control (config pipeline, aturan Rego, baseline scanner) memberi manfaat yang sama seperti detection-as-code (modul 09): **konsisten** (sama di semua repo/lingkungan), **di-review** lewat PR (perubahan keamanan diawasi), **diuji** (apakah menangkap kasus buruk & meloloskan yang baik), **bisa di-rollback**, dan **terlacak** (siapa mengubah apa & kapan). Konfigurasi yang diklik manual di UI mudah menyimpang, sulit diaudit, dan hilang bersama orang yang membuatnya. Perlakukan keamanan seperti kode.'
    source: https://www.conftest.dev/

  - id: b12
    tier: B
    type: text
    objective: supplychain
    prompt: Apa peran SBOM dan penandatanganan (cosign) ketika dibuat di dalam pipeline?
    answers: ['pipeline menghasilkan sbom (daftar komponen) & menandatangani artefak, lalu keduanya diverifikasi saat deploy']
    patterns: ['sbom[^\n]*(komponen|daftar|dependency|bom)', '(tanda ?tangan|sign|cosign)[^\n]*(artefak|image)', '(verifikasi|cek)[^\n]*(sbom|tanda ?tangan|deploy)']
    tests:
      fail: ['sbom tidak berguna di pipeline', 'cosign hanya untuk login', 'tidak perlu verifikasi apa pun', 'sbom menggantikan scanning']
    explanation: 'Di pipeline (bersambung dengan modul 05): hasilkan **SBOM** (Software Bill of Materials — daftar lengkap komponen/dependency artefak) sehingga saat CVE baru muncul kamu bisa langsung tahu apakah terdampak; dan **tandatangani artefak** (`cosign sign`) sehingga asal-usulnya terbukti. Saat deploy, **verifikasi** tanda tangan + (opsional) SBOM/provenance sebelum menjalankan. Rantai: build → SBOM + sign → simpan → verify di deploy/admission. Ini mengubah "percaya saja image di registry" jadi "buktikan image ini dibangun pipeline kita dan ketahui isinya".'
    source: https://slsa.dev/

  - id: b13
    tier: B
    type: text
    objective: gates
    prompt: Bagaimana menangani gerbang yang "berisik" (banyak temuan lama/false-positive) tanpa sekadar mematikannya?
    answers: ['pakai baseline/ignore eksplisit ter-review untuk temuan yang diterima, perbaiki sisanya, dan gagalkan hanya pada temuan baru/serius']
    patterns: ['baseline|ignore file|\.trivyignore|pengecualian eksplisit', '(temuan baru|hanya[^\n]*(baru|serius|critical|high))', '(review|alasan|pemilik|ter-?dokumentasi)[^\n]*(pengecualian|ignore|exception)']
    tests:
      fail: ['matikan gerbangnya saja', 'turunkan semua ke warning', 'abaikan semua temuan', 'hapus scanner dari ci']
    explanation: 'Gerbang berisik membuat tim ingin mematikannya (seperti alert fatigue, modul 09) — tapi itu melepas perlindungan. Lebih baik: tetapkan **baseline** (catat temuan lama yang sudah diketahui) sehingga gerbang **gagal hanya pada temuan BARU**; **kecualikan** false-positive/risiko-diterima secara **eksplisit, ter-review, dengan alasan & pemilik** (mis. `.trivyignore`); batasi blocking ke severity serius (CRITICAL/HIGH) dulu; dan cicil perbaikan sisanya. Gerbang tetap menyala & bermakna, bukan dimatikan. Tuning > disable.'
    source: https://trivy.dev/latest/docs/configuration/filtering/

  - id: b14
    tier: B
    type: text
    objective: shiftleft
    prompt: Bagaimana modul-modul sebelumnya (hardening container, secrets, image) terhubung ke keamanan pipeline?
    answers: ['praktik modul-modul itu dikodekan jadi aturan gerbang otomatis sehingga ditegakkan konsisten di setiap build']
    patterns: ['(praktik|aturan|kontrol)[^\n]*(modul|sebelum)[^\n]*(gerbang|otomat|scan|kode)', '(dikodekan|diotomasi|ditegakkan)[^\n]*(konsisten|setiap|otomatis)', 'shift.?left[^\n]*(modul|kontrol)']
    tests:
      fail: ['tidak ada hubungan', 'pipeline menggantikan semua modul lain', 'modul lain tidak relevan', 'keamanan pipeline berdiri sendiri']
    explanation: 'Keamanan pipeline adalah tempat pelajaran modul lain menjadi **otomatis & konsisten**: "jangan root / jangan privileged" (modul 06) → aturan trivy config & policy Rego; "jangan hardcode rahasia" (modul 04) → secret scan + pre-commit; "pin digest, pindai CVE, tandatangani image" (modul 05) → gerbang SCA + cosign; "otorisasi & no-latest" → policy-as-code. Tanpa pipeline, kontrol itu bergantung ingatan tiap orang; dengan pipeline, ditegakkan di **setiap** build. Shift-left = mengubah pengetahuan keamanan jadi gerbang kode.'
    source: https://owasp.org/www-project-top-10-ci-cd-security-risks/

  - id: b15
    tier: B
    type: text
    objective: supplychain
    prompt: Apa itu "dependency confusion" dan bagaimana mencegahnya?
    answers: ['penyerang mem-publish paket publik bernama sama dengan paket internal agar resolver menariknya; cegah dgn namespace/registry terkunci & pin']
    patterns: ['(paket|package)[^\n]*(publik|public)[^\n]*(nama sama|internal)', '(resolver|installer|npm|pip)[^\n]*(tarik|ambil|prioritas)[^\n]*(publik|salah)', '(namespace|scope|registry)[^\n]*(internal|terkunci|privat)|pin[^\n]*versi']
    tests:
      fail: ['dependency confusion tidak nyata', 'tidak bisa dicegah', 'paket publik selalu aman', 'cukup pakai latest']
    explanation: 'Dependency confusion: penyerang mem-publish paket **publik** dengan **nama sama** seperti paket **internal**-mu (dan versi lebih tinggi). Resolver yang salah konfigurasi menarik versi publik jahat alih-alih internal → kode penyerang masuk build (supply chain, T1195). Cegah dengan: **namespace/scope** yang dicadangkan, **registry internal yang dikunci** (jangan fallback diam-diam ke publik), **pin versi & verifikasi integritas** (lockfile + hash), dan memeriksa sumber tiap dependency. Bersama pin action (a13) dan SBOM (b12), ini menutup jalur rantai pasok via dependency.'
    source: https://attack.mitre.org/techniques/T1195/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: gates
    prompt: |
      Kamu menambahkan gerbang keamanan ke pipeline CI untuk aplikasi ber-container. Rancang gerbang-gerbang yang
      dipasang dan bagaimana mereka memblokir (satu poin per baris).
    checklist:
      - id: secret
        label: Pemindai rahasia (trivy secret / gitleaks) — blokir bila ada kredensial
        required: true
        patterns: ['(pindai|scan)[^\n]*rahasia|secret scan', 'trivy[^\n]*secret|gitleaks|detect-secrets|trufflehog']
      - id: iac
        label: Pemindai misconfig IaC/Dockerfile (trivy config / checkov / hadolint)
        required: true
        patterns: ['trivy config|checkov|kics|hadolint', '(pindai|scan)[^\n]*(iac|misconfig|dockerfile|konfigurasi)']
      - id: sca
        label: Pemindai CVE dependency/image (SCA — trivy image)
        patterns: ['trivy image|sca|dependency[^\n]*(cve|rentan)|pindai[^\n]*(image|dependency)[^\n]*cve']
      - id: policy
        label: Policy-as-code kustom (OPA/conftest) untuk aturan organisasi
        patterns: ['opa|conftest|rego|policy.as.code|kebijakan sebagai kode']
      - id: failbuild
        label: Gagalkan build pada temuan via exit code (bukan sekadar warning)
        required: true
        patterns: ['exit.?code|--exit-code|gagalkan build|fail build|blokir merge', '(bukan|tidak) (sekadar|hanya) (warning|peringatan)']
      - id: signsbom
        label: Hasilkan SBOM + tandatangani image (cosign) untuk verifikasi saat deploy
        patterns: ['sbom', 'cosign|tanda ?tangan|sign[^\n]*image', 'provenance|slsa']
      - id: every-pr
        label: Jalankan di setiap PR + berkala
        patterns: ['(setiap|tiap)[^\n]*(pr|commit|merge)', 'berkala|scheduled|terjadwal']
      - id: no-warnonly
        label: Tidak "cuma warning lalu merge" / tidak mematikan gerbang agar hijau
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|hindari))[^\n]*(cuma|hanya|sekadar)[^\n]*(warning|peringatan|catat)[^\n]*(lalu|tetap)?[^\n]*(merge|lanjut|lolos)']
    pass_score: 0.5
    solution: |
      ```text
      1. Secret scan (trivy fs --scanners secret / gitleaks) → blokir bila ada kredensial (lab: menemukan Stripe key CRITICAL).
      2. IaC/Dockerfile misconfig (trivy config / hadolint / checkov) → root, :latest, privileged, port sensitif
         (lab: 6 Dockerfile + 20 k8s misconfig; hadolint DL3007/DL3064).
      3. SCA: pindai CVE dependency/image (trivy image, modul 05).
      4. Policy-as-code (conftest/OPA) untuk aturan organisasi khusus (lab: deny privileged & :latest, exit 1 → fixed exit 0).
      5. Gagalkan build pada temuan serius via --exit-code 1 (bukan warning) — temuan = build merah, merge diblokir.
      6. Hasilkan SBOM + cosign sign image; verifikasi saat deploy/admission.
      7. Jalankan di SETIAP PR + terjadwal (CVE baru untuk kode lama).
      ```
      Tiap gerbang menangkap kelas masalah berbeda; bersama-sama + exit-code yang memblokir = celah tak lolos ke
      produksi. "Cuma warning lalu merge" atau mematikan gerbang agar hijau mengubahnya jadi teater keamanan.
    tests:
      pass:
        - |
          pasang pemindai rahasia trivy secret atau gitleaks dan blokir bila ada kredensial
          pindai misconfig iac dan dockerfile dengan trivy config hadolint atau checkov
          pindai cve dependency dan image sca dengan trivy image
          tegakkan policy as code kustom dengan opa conftest untuk aturan organisasi
          gagalkan build pada temuan lewat exit code bukan sekadar warning
          hasilkan sbom dan tandatangani image cosign untuk verifikasi saat deploy
          jalankan di setiap pr dan secara berkala
          jangan cuma warning lalu merge atau matikan gerbang agar hijau
        - |
          secret scan, trivy config iac, hadolint, trivy image untuk cve, conftest policy kustom
          semua pakai exit-code 1 untuk blokir merge, sbom plus cosign sign dan verify, jalan tiap pr dan terjadwal
      fail:
        - |
          cukup tampilkan warning lalu merge saja
          gerbang dimatikan kalau bikin build merah
        - |
          tidak perlu scan apa-apa di ci
          deploy saja langsung
        - |
          scan sekali setahun secara manual
          temuan diabaikan biar cepat rilis
    source: https://owasp.org/www-project-top-10-ci-cd-security-risks/

  - id: c02
    tier: C
    type: scenario
    objective: supplychain
    prompt: |
      Pipeline CI/CD kamu memegang kredensial produksi dan menjalankan kode dari banyak kontributor. Rancang cara
      mengeraskan pipeline itu sendiri (satu poin per baris).
    checklist:
      - id: leastpriv
        label: Token/kredensial CI hak-minimal, scope sempit, berumur pendek (OIDC)
        required: true
        patterns: ['least privilege|hak minimal|scope (sempit|terbatas)', '(pendek|ephemeral|short-?lived|oidc|sementara)[^\n]*(token|kredensial)']
      - id: pin
        label: Pin action/dependency ke versi/digest tetap (bukan @latest)
        required: true
        patterns: ['pin[^\n]*(versi|digest|action|dependency|sha)', '(bukan|jangan|hindari)[^\n]*(latest|mengambang|floating)']
      - id: protect
        label: Protected branch + required review + required checks sebelum merge
        patterns: ['protected branch|branch protection', 'required (review|check)|wajib (review|lolos|persetujuan)']
      - id: runner
        label: Runner ephemeral/terisolasi, tanpa docker.sock, bukan privileged
        patterns: ['runner[^\n]*(ephemeral|terisolasi|sekali pakai|non-?privileged)', '(tanpa|jangan)[^\n]*docker\.?sock', 'bukan privileged|non-?privileged']
      - id: untrusted-pr
        label: Jangan ekspos rahasia ke CI yang dipicu PR fork/tak tepercaya
        patterns: ['(jangan|tidak)[^\n]*(rahasia|secret)[^\n]*(pr|fork|tak tepercaya)', 'pr (fork|luar)[^\n]*(tanpa|tak)[^\n]*(rahasia|secret)', 'poisoned pipeline|ppe']
      - id: audit
        label: Audit log pipeline + pemisahan build/deploy
        patterns: ['audit (log|trail)', '(pisah|separasi)[^\n]*(build|deploy)', 'log[^\n]*pipeline']
      - id: no-admin
        label: Tidak memberi CI akses admin penuh / kunci abadi / runner root+socket
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|hindari))[^\n]*(beri|kasih|pakai)[^\n]*(admin penuh|akses penuh|kunci abadi|root)[^\n]*(ci|runner|pipeline)?']
    pass_score: 0.5
    solution: |
      ```text
      1. Kredensial CI least-privilege: scope sekecil mungkin, berumur pendek/ephemeral (OIDC, bukan kunci statis abadi).
      2. Pin semua action/dependency ke versi/digest tetap (bukan @latest/floating) → build deterministik, auditable.
      3. Protected branch + required review + required status checks → tak ada yang masuk main tanpa review & gerbang hijau.
      4. Runner ephemeral & terisolasi, TANPA docker.sock, bukan privileged (modul 06 — itu jalan escape ke host).
      5. Jangan ekspos rahasia ke workflow yang dipicu PR fork/tak tepercaya (cegah Poisoned Pipeline Execution);
         wajibkan approval maintainer; pisahkan pipeline test-PR (tanpa rahasia) dari deploy (dengan rahasia, branch tepercaya).
      6. Audit log semua aksi pipeline + pisahkan tahap build dari deploy.
      ```
      Pipeline = sistem produksi paling sensitif (ia bisa men-deploy apa saja & memegang kredensial prod). Memberi CI
      akses admin penuh + kunci abadi + runner root ber-socket = satu kebocoran menjatuhkan segalanya.
    tests:
      pass:
        - |
          pakai token ci hak minimal scope sempit dan berumur pendek lewat oidc bukan kunci statis abadi
          pin semua action dan dependency ke versi atau digest tetap bukan latest
          protected branch dengan required review dan required check sebelum merge
          runner ephemeral terisolasi tanpa docker.sock dan bukan privileged
          jangan ekspos rahasia ke ci yang dipicu pr fork tak tepercaya cegah poisoned pipeline
          audit log pipeline dan pisahkan tahap build dari deploy
          jangan beri ci akses admin penuh kunci abadi atau runner root bersocket
        - |
          kredensial oidc short lived least privilege, pin action ke sha
          branch protection required checks, runner sekali pakai non privileged tanpa socket
          pr fork tanpa secret, audit log, pisah build dan deploy
      fail:
        - |
          beri ci akses admin penuh dan kunci yang tidak pernah kedaluwarsa
          runner privileged dengan docker.sock biar gampang
        - |
          pakai action @latest biar selalu update
          siapa saja boleh push ke main tanpa review
        - |
          jalankan pr siapa saja dengan akses penuh ke semua rahasia
          tidak perlu audit log
    source: https://owasp.org/www-project-top-10-ci-cd-security-risks/

  - id: c03
    tier: C
    type: scenario
    objective: shiftleft
    prompt: |
      Sebuah rahasia hardcoded DAN konfigurasi container privileged lolos ke produksi tanpa tertangkap. Rancang
      shift-left agar kelas masalah ini tertangkap lebih awal lain kali — plus respons segera (satu poin per baris).
    checklist:
      - id: rotate
        label: Respons segera — rotasi/cabut rahasia yang bocor (anggap sudah dicuri)
        required: true
        patterns: ['(rotasi|cabut|ganti|revoke)[^\n]*(rahasia|secret|kredensial|kunci)', 'anggap[^\n]*(bocor|dicuri)']
      - id: secretgate
        label: Tambah gerbang pemindai rahasia (blocking) di CI + pre-commit
        required: true
        patterns: ['(gerbang|gate|scan)[^\n]*rahasia|secret scan', 'pre-?commit', 'ci[^\n]*(secret|rahasia)[^\n]*(blokir|gate)']
      - id: iacgate
        label: Tambah gerbang IaC/policy yang menolak privileged (trivy config/conftest)
        required: true
        patterns: ['trivy config|conftest|opa|checkov|policy', '(tolak|blokir|deny)[^\n]*privileged']
      - id: source
        label: Perbaiki di sumber (buang secret, drop privileged → cap minimal, modul 04/06)
        patterns: ['perbaiki[^\n]*(sumber|dockerfile|kode|config)', '(buang|hapus)[^\n]*(secret|rahasia)[^\n]*(env|kode)', '(drop|hapus)[^\n]*privileged|cap minimal|non-?root']
      - id: admission
        label: Admission control (Gatekeeper/Kyverno) agar tak bisa di-apply langsung melewati CI
        patterns: ['admission|gatekeeper|kyverno', '(tak bisa|cegah)[^\n]*(apply|deploy)[^\n]*(lewati|bypass|langsung)[^\n]*ci']
      - id: lessons
        label: Lessons learned → gerbang jadi required check + baseline, cegah terulang
        patterns: ['(lessons|pelajaran|postmortem|evaluasi)', 'required check|wajib[^\n]*(gerbang|check)|baseline', '(cegah|agar tidak)[^\n]*terulang']
      - id: no-quickfix
        label: Tidak sekadar hapus dari commit terakhir / matikan scanner karena berisik
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|hindari|selain))[^\n]*(cukup|hanya|sekadar)[^\n]*(hapus (dari )?commit|matikan scanner|disable)[^\n]*(terakhir|berisik|saja)?']
    pass_score: 0.45
    solution: |
      ```text
      RESPONS SEGERA:
        - Rotasi/cabut rahasia yang bocor — anggap sudah dicuri (modul 04/10); menghapus dari commit saja tak cukup.
        - Ganti deploy privileged dengan konfigurasi hardened (drop cap, non-root, modul 06); redeploy.
      SHIFT-LEFT (agar tak terulang):
        - Gerbang secret scan (trivy secret/gitleaks) di CI (blocking) + pre-commit hook (paling kiri).
        - Gerbang IaC/policy (trivy config + conftest/OPA) yang MENOLAK privileged & :latest (lab: deny → exit 1).
        - Perbaiki di sumber: rahasia via *_FILE/secret manager (modul 04), privileged → cap spesifik (modul 06).
        - Admission control (Gatekeeper/Kyverno) di cluster → config melanggar ditolak meski di-apply melewati CI.
        - Lessons learned: jadikan gerbang required status check + protected branch; baseline agar fokus temuan baru.
      ```
      Kuncinya menutup dua lubang: respons (rotasi + hardening sekarang) DAN pencegahan sistemik (gerbang kiri +
      admission kanan). "Hapus dari commit terakhir" meninggalkan rahasia tetap valid & jalur tetap terbuka;
      "matikan scanner karena berisik" menjamin terulang. Perbaiki akar, lalu jadikan gerbang permanen.
    tests:
      pass:
        - |
          respons segera rotasi dan cabut rahasia yang bocor anggap sudah dicuri
          tambah gerbang pemindai rahasia blocking di ci dan pre-commit hook
          tambah gerbang iac dan policy trivy config conftest yang menolak privileged
          perbaiki di sumber buang secret pakai secret manager dan drop privileged jadi cap minimal non root
          pasang admission control gatekeeper atau kyverno agar config melanggar ditolak meski di-apply melewati ci
          lessons learned jadikan gerbang required check dan baseline agar tidak terulang
          jangan sekadar hapus dari commit terakhir atau matikan scanner karena berisik
        - |
          rotasi secret bocor sekarang dan redeploy versi hardened non privileged
          secret scan dan iac policy gate blocking di ci plus pre-commit, perbaiki di sumber
          admission kyverno menolak privileged, postmortem jadikan required check dan baseline
      fail:
        - |
          cukup hapus rahasia dari commit terakhir
          matikan scanner-nya karena berisik
        - |
          biarkan saja secretnya toh sudah di-deploy
          privileged tidak masalah
        - |
          ganti nama variabel rahasianya
          tidak perlu gerbang atau admission
    source: https://owasp.org/www-project-top-10-ci-cd-security-risks/
---

# Keamanan Pipeline & IaC

Modul 10 menangani insiden *setelah* terjadi. Modul ini mencegahnya lahir: **geser keamanan ke kiri (shift-left)** —
jadikan semua pelajaran modul 02–10 sebagai **gerbang otomatis** di pipeline, sehingga celah tertangkap di pull
request, bukan di produksi. Memperbaiki di PR = komentar review murah; memperbaiki di produksi = insiden mahal.

> [!NOTE]
> Lab: scanner dijalankan sebagai container `--rm` atas IaC sengaja-rentan (`Dockerfile` + `pod.yaml`) di `/tmp/sec11`.
> Board runner CI (`act`) ditunda sejak 2026-11-23 (unduhannya diblokir classifier), jadi modul ini menjalankan
> **gerbang-nya langsung** sebagai container — yang justru inti keamanannya — bukan meniru runner GitHub lokal.

## 1. Gerbang menggagalkan build lewat exit code (hasil lab)

```text
# IaC misconfig
trivy config iac/ --exit-code 1   → 6 misconfig Dockerfile (1 CRIT,2 HIGH) + 20 k8s → exit 1 (BUILD GAGAL)
   DS-0001 :latest · DS-0002 tanpa USER (root) · DS-0004 EXPOSE 22 · DS-0026 tanpa HEALTHCHECK · k8s privileged
# versi diperbaiki → exit 0 (lolos)
```

`0` = lolos, non-zero = gagal. Temuan harus **memblokir** (`--exit-code 1`), bukan sekadar peringatan yang diabaikan.

## 2. Policy-as-code: aturanmu sendiri (hasil lab)

```text
# kebijakan Rego kustom: tolak pod privileged & image :latest
conftest test iac/pod.yaml  -p policy/   → 2 FAIL (privileged, :latest)  exit 1  (BUILD GAGAL)
conftest test iac/pod-fixed.yaml -p policy/ → 2 passed                   exit 0  (LOLOS)
```

Scanner (trivy/checkov) = aturan umum siap pakai. **OPA/conftest** = aturan khusus organisasimu ("hanya registry
internal", "wajib label owner"). Pakai keduanya. Bahasa OPA: **Rego**.

## 3. Pindai rahasia & lint (hasil lab)

```text
trivy fs --scanners secret iac/  → CRITICAL: Stripe (stripe-secret-token), nilai OTOMATIS DISAMARKAN
hadolint Dockerfile              → DL3007 (:latest), DL3008 (pin apt), DL3064 (data sensitif di ENV) → exit 1
```

Secret scan juga dipasang sebagai **pre-commit hook** (paling kiri) agar rahasia tak pernah masuk history (modul 04).

## 4. Katalog gerbang pipeline

| Gerbang | Alat (lab) | Menangkap | Tautan modul |
|---|---|---|---|
| Secret scan | `trivy fs --scanners secret`, gitleaks | kredensial ter-hardcode | 04 |
| IaC misconfig | `trivy config`, checkov, hadolint | root, `:latest`, privileged, port | 05, 06 |
| SCA (CVE) | `trivy image` | dependency/image rentan | 05 |
| Policy-as-code | conftest/OPA (Rego) | aturan organisasi kustom | 06, 07 |
| SAST / DAST | semgrep / ZAP | bug kode / app berjalan | 07 |
| Sign + SBOM | cosign, SBOM | asal-usul & isi artefak | 05 |

## 5. Amankan pipeline itu sendiri

Pipeline memegang **kredensial produksi** dan bisa men-deploy ke mana saja — target bernilai tinggi (OWASP CI/CD
Top 10, T1195).

- **Token hak-minimal & berumur pendek** (OIDC, bukan kunci abadi); scope sempit.
- **Pin** action/dependency ke versi/digest tetap (bukan `@latest`) → deterministik, auditable; cegah **dependency confusion**.
- **Protected branch** + required review + required checks → tak ada yang masuk `main` tanpa gerbang hijau.
- **Runner ephemeral, terisolasi, tanpa `docker.sock`, bukan privileged** (modul 06 — jalan escape).
- **Jangan ekspos rahasia ke CI dari PR fork** tak tepercaya (Poisoned Pipeline Execution); pisahkan test-PR dari deploy.

## 6. Kiri dan kanan: CI gate + admission control

Policy-as-code dipasang di **dua** titik: **CI** (saat PR/build) dan **admission control** Kubernetes (OPA
**Gatekeeper**/**Kyverno**) saat deploy. Kenapa keduanya? Seseorang bisa `kubectl apply` melewati CI, atau config
berubah di luar pipeline — admission menegakkan kebijakan **apa pun jalurnya**. Defense-in-depth untuk kebijakan.

> [!WARNING]
> Gerbang yang di-bypass diam-diam (di-set warning, scanner dimatikan "karena berisik", temuan di-ignore tanpa
> alasan) lebih buruk daripada tak ada gerbang — ia memberi rasa aman palsu. Tuning (baseline, severity, pengecualian
> ter-review), jangan matikan.

## 7. Ringkasan

Shift-left mengubah pengetahuan keamanan (modul 02–10) menjadi **gerbang kode** yang ditegakkan konsisten di setiap
build — secret scan, IaC misconfig scan, SCA, policy-as-code, sign+SBOM — yang **menggagalkan build** pada temuan,
ditambah pipeline yang dikeraskan sendiri dan admission control di sisi deploy. Celah ditutup sebelum jadi insiden.

> [!NOTE]
> Lanjut ke modul 12 (Capstone) — menyatukan seluruh perjalanan: dari model ancaman sampai gerbang pipeline, dalam
> skenario pertahanan menyeluruh end-to-end.
