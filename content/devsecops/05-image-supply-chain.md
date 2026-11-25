---
format: 1
id: sec-image-supply-chain
course:
  slug: devsecops
  title: DevSecOps
  description: Keamanan untuk DevOps dari dua sisi — cara kerja red team dan cara blue team mendeteksi serta menutupnya. Model ancaman, hardening server & container, keamanan web, ketersediaan (anti-DDoS), deteksi, respons insiden, dan keamanan pipeline. Semua praktik di lab terisolasi milik sendiri.
title: Keamanan Image & Supply Chain
summary: "Image dan dependensi yang kamu tarik membawa kode orang lain — dan kerentanannya. Modul ini memindai image dengan trivy (image EOL nginx: 219 CVE vs terbaru 12 vs alpine minimal 2), membuat SBOM sebagai inventori komponen, mem-pin base image dengan digest (tag itu mutable), dan membuktikan provenance dengan menandatangani image memakai cosign lalu memverifikasinya (tanda tangan terikat ke digest, bukan tag). Plus serangan supply chain: typosquatting, dependency confusion, dan pentingnya lockfile."
order: 5
version: 1
status: verified
updated: '2026-11-25'
tested_on:
  - Docker Desktop (Windows), Engine 29.4.1 — trivy (aquasec/trivy) scan daemon images via socket
  - cosign v2.4.3 (container) + registry:2 lokal — sign/verify key-based, tanpa transparency log
objectives:
  - id: scan-image
    text: Memindai image untuk kerentanan (CVE) dengan trivy
  - id: patch
    text: Memperbaiki kerentanan dengan update/rebuild dan menghindari image EOL
  - id: minimal
    text: Memakai base image minimal/distroless untuk memperkecil attack surface
  - id: pinning
    text: Mem-pin image dengan digest (bukan tag yang mutable)
  - id: sbom
    text: Membuat SBOM sebagai inventori komponen untuk audit kerentanan
  - id: signing
    text: Menandatangani & memverifikasi image (cosign) dan mengenali serangan supply chain
sources:
  - title: Trivy — vulnerability & SBOM scanner
    url: https://trivy.dev/latest/docs/
  - title: Sigstore cosign — sign & verify container images
    url: https://docs.sigstore.dev/cosign/signing/signing_with_containers/
  - title: CycloneDX — SBOM standard
    url: https://cyclonedx.org/
  - title: SLSA — Supply-chain Levels for Software Artifacts
    url: https://slsa.dev/
  - title: Docker — image tags vs digests (pinning)
    url: https://docs.docker.com/dhi/core-concepts/digests/
  - title: MITRE ATT&CK — Supply Chain Compromise (T1195)
    url: https://attack.mitre.org/techniques/T1195/
  - title: OWASP — Vulnerable and Outdated Components (A06:2021)
    url: https://owasp.org/Top10/A06_2021-Vulnerable_and_Outdated_Components/
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: command
    objective: scan-image
    prompt: Pindai image `nginx:1.27-alpine` untuk kerentanan (CVE) dengan trivy.
    answers: ['trivy image nginx:1.27-alpine']
    patterns: ['^trivy image( (--[a-z-]+[= ]\S+|-[a-zA-Z] ?\S+|--[a-z-]+))* nginx:1\.27-alpine$']
    tests:
      pass: ['trivy image --severity HIGH,CRITICAL nginx:1.27-alpine', 'trivy image nginx:1.27-alpine']
      fail: ['trivy nginx:1.27-alpine', 'docker scan nginx:1.27-alpine', 'trivy fs nginx:1.27-alpine', 'trivy image']
    explanation: 'Di lab, `trivy image nginx:1.27-alpine` menemukan **219 CVE** (3 CRITICAL, 67 HIGH) di 68 paket. trivy membandingkan paket OS + dependensi aplikasi di image dengan database kerentanan. Ini otomatisasi recon versi (modul 02) dari sisi blue: kamu menemukan CVE-mu sendiri sebelum penyerang. Jalankan di CI agar image rentan tidak pernah sampai production (modul 11). trivy bisa dijalankan sebagai biner atau container (`aquasec/trivy`).'
    source: https://trivy.dev/latest/docs/

  - id: a02
    tier: A
    type: text
    objective: patch
    prompt: Di lab, `nginx:1.27-alpine` (EOL) punya 219 CVE sedangkan `nginx:mainline-alpine` (terbaru) hanya 12. Pelajaran utamanya apa?
    answers: ['patch/update image']
    patterns: ['(patch|update|upgrade|perbarui|pembaruan|rebuild)[^\n]*(image|versi|rutin)?', '(pakai|gunakan)[^\n]*versi (baru|terbaru)', 'jangan (pakai )?(image )?(eol|lama|usang)']
    tests:
      fail: ['scan lebih sering', 'ganti ke nginx lain', 'sembunyikan versi', 'tambah RAM']
    explanation: 'Di lab: EOL `1.27-alpine` → 219 CVE; `mainline-alpine` → 12. Satu-satunya pertahanan sejati terhadap CVE adalah **mem-patch**: pakai versi yang didukung dan rebuild rutin agar menyerap perbaikan upstream. Image EOL tidak lagi menerima patch, jadi CVE-nya menumpuk selamanya (temuan kita soal nginx EOL di modul Docker 04). Ini juga OWASP A06: Vulnerable and Outdated Components.'
    source: https://owasp.org/Top10/A06_2021-Vulnerable_and_Outdated_Components/

  - id: a03
    tier: A
    type: command
    objective: scan-image
    prompt: Pindai image `myapp:latest` dengan trivy tapi **hanya tampilkan** kerentanan severity HIGH dan CRITICAL.
    answers: ['trivy image --severity HIGH,CRITICAL myapp:latest']
    patterns: ['^(?=.*\s--severity (HIGH,CRITICAL|CRITICAL,HIGH)(\s|$))trivy image( (--[a-z-]+[= ]\S+|-[a-zA-Z] ?\S+|--[a-z-]+))* myapp:latest$']
    tests:
      pass: ['trivy image --severity CRITICAL,HIGH myapp:latest', 'trivy image --severity HIGH,CRITICAL --quiet myapp:latest']
      fail: ['trivy image myapp:latest', 'trivy image --severity high,critical myapp:latest', 'trivy image -s HIGH,CRITICAL myapp:latest']
    explanation: '`--severity HIGH,CRITICAL` menyaring agar tim fokus pada yang paling berisiko dulu (nilainya HURUF BESAR). Tapi hati-hati "CVE fatigue": ratusan temuan LOW/MEDIUM bisa menenggelamkan yang penting. Praktik: gagalkan build CI hanya untuk HIGH/CRITICAL yang **ada fix-nya** (`--ignore-unfixed`), triase sisanya. Tujuannya bisa ditindaklanjuti, bukan sekadar daftar panjang yang diabaikan.'
    source: https://trivy.dev/latest/docs/

  - id: a04
    tier: A
    type: text
    objective: minimal
    prompt: Di lab, `alpine:3.20` hanya punya 14 paket & 2 CVE, jauh di bawah image lain. Prinsip apa yang membuatnya lebih aman?
    answers: ['base image minimal']
    patterns: ['(base )?image minimal', 'minimal(isasi)?', 'sedikit (paket|komponen)', 'distroless', 'kecil', 'permukaan (serang )?kecil']
    tests:
      fail: ['image populer', 'image resmi', 'image berbayar', 'image besar']
    explanation: 'Makin sedikit paket di image, makin sedikit yang bisa rentan dan harus di-patch — attack surface mengecil (modul 02). `alpine` (14 paket) jauh lebih ramping dari base penuh. Lebih ekstrem lagi **distroless** (hanya runtime + aplikasi, tanpa shell/package manager): tidak ada `sh` untuk penyerang pakai, dan hampir tidak ada paket OS untuk di-scan. "Paket yang tidak ada tidak bisa punya CVE."'
    source: https://owasp.org/Top10/A06_2021-Vulnerable_and_Outdated_Components/

  - id: a05
    tier: A
    type: text
    objective: pinning
    prompt: '`FROM nginx:alpine` bisa menunjuk image yang berbeda dari waktu ke waktu karena tag bersifat apa?'
    answers: ['mutable']
    patterns: ['\bmutable\b', 'bisa (berubah|diganti|ditimpa|dipindah)', 'tidak tetap', 'berubah[- ]ubah']
    tests:
      fail: ['immutable', 'unik', 'permanen', 'terenkripsi']
    explanation: 'Tag seperti `alpine` atau `latest` adalah pointer yang bisa dipindahkan ke image baru kapan saja oleh publisher. Jadi dua build `FROM nginx:alpine` di waktu berbeda bisa menarik isi berbeda — build tidak reproducible, dan image bisa diam-diam berubah (vektor supply chain). Di lab, `docker inspect` menunjukkan tag mengarah ke `nginx@sha256:fb46...` — digest itulah identitas sebenarnya yang immutable.'
    source: https://docs.docker.com/dhi/core-concepts/digests/

  - id: a06
    tier: A
    type: text
    objective: pinning
    prompt: Untuk membuat base image **reproducible** dan tidak bisa diam-diam diganti, `FROM` sebaiknya menyebut image dengan apa (bukan tag)?
    answers: ['digest']
    patterns: ['digest', 'sha256', '@sha256', 'hash']
    tests:
      fail: ['tag latest', 'nama lengkap', 'nomor versi', 'label']
    explanation: 'Pin dengan digest: `FROM nginx:1.27-alpine@sha256:fb46...`. Digest adalah hash isi image — immutable, jadi build selalu menarik byte yang sama persis, dan publisher tidak bisa menukar image di bawah kakimu. Di lab, `docker pull alpine@sha256:d9e8...` menarik image yang sama berulang. Trade-off: kamu harus sengaja memperbarui digest saat ingin update (idealnya otomatis via Dependabot/Renovate) — itu fitur, bukan beban: update jadi eksplisit & terlacak.'
    source: https://docs.docker.com/dhi/core-concepts/digests/

  - id: a07
    tier: A
    type: command
    objective: pinning
    prompt: Tampilkan **digest** (RepoDigest) dari image lokal `alpine:3.20` dengan docker inspect.
    answers: ['docker inspect --format {{index .RepoDigests 0}} alpine:3.20']
    patterns: ['^docker inspect (--format|-f) \{\{index \.RepoDigests 0\}\} alpine:3\.20$']
    tests:
      pass: ['docker inspect -f {{index .RepoDigests 0}} alpine:3.20']
      fail: ['docker inspect alpine:3.20', 'docker inspect --format {{.Id}} alpine:3.20', 'docker digest alpine:3.20']
    explanation: 'Di lab → `alpine@sha256:d9e853e8...`. `RepoDigests` adalah digest image sebagaimana tersimpan di registry (identitas isinya). Salin digest ini ke `FROM` untuk pinning (a06). Beda dari `.Id` (digest konfigurasi lokal). Catatan: digest bisa berbeda antar-registry/platform; ambil dari registry yang kamu pakai, dan untuk multi-arch rujuk digest manifest list.'
    source: https://docs.docker.com/dhi/core-concepts/digests/

  - id: a08
    tier: A
    type: text
    objective: sbom
    prompt: Dokumen yang mendaftar **semua komponen/paket** penyusun sebuah image/aplikasi (untuk audit), sering disebut dengan singkatan apa?
    answers: ['SBOM']
    patterns: ['^sbom$', 'software bill of materials', 'bill of materials']
    tests:
      fail: ['CVE', 'CVSS', 'SLSA', 'EULA']
    explanation: 'SBOM (Software Bill of Materials) = "daftar bahan" perangkat lunak: setiap library + versinya. Di lab, `trivy image --format cyclonedx` menghasilkan SBOM CycloneDX berisi komponen (OS alpine 3.20.10 + library seperti busybox, apk-tools). Gunanya: saat CVE baru muncul (mis. Log4Shell), kamu bisa menjawab "apakah kita memakai komponen itu?" dalam hitungan detik dengan mencari di SBOM, bukan menebak.'
    source: https://cyclonedx.org/

  - id: a09
    tier: A
    type: command
    objective: sbom
    prompt: Hasilkan SBOM dari image `myapp:latest` dalam format **CycloneDX** memakai trivy.
    answers: ['trivy image --format cyclonedx myapp:latest']
    patterns: ['^(?=.*\s--format (cyclonedx|spdx|spdx-json)(\s|$))trivy image( (--[a-z-]+[= ]\S+|-[a-zA-Z] ?\S+|--[a-z-]+))* myapp:latest$']
    tests:
      pass: ['trivy image --format spdx-json myapp:latest', 'trivy image --format cyclonedx --output sbom.json myapp:latest']
      fail: ['trivy image myapp:latest', 'trivy sbom myapp:latest', 'trivy image --format table myapp:latest']
    explanation: 'Di lab → `bomFormat=CycloneDX specVersion=1.7 components=15`. Dua format umum: **CycloneDX** (OWASP) dan **SPDX** (Linux Foundation). Simpan SBOM sebagai artefak build tiap rilis, sehingga bisa di-scan ulang terhadap CVE yang **baru ditemukan belakangan** tanpa membangun ulang image. SBOM juga syarat makin umum dalam kepatuhan (mis. regulasi pemerintah).'
    source: https://trivy.dev/latest/docs/

  - id: a10
    tier: A
    type: command
    objective: signing
    prompt: Buat pasangan kunci untuk menandatangani image dengan cosign.
    answers: ['cosign generate-key-pair']
    patterns: ['^cosign generate-key-pair( \S+)?$']
    tests:
      pass: ['cosign generate-key-pair']
      fail: ['cosign keygen', 'cosign sign', 'ssh-keygen -t cosign', 'cosign generate-keys']
    explanation: 'Di lab → `cosign.key` (privat, dilindungi password/`COSIGN_PASSWORD`) + `cosign.pub` (publik). Private key dipakai menandatangani image; public key dibagikan agar orang lain bisa memverifikasi. Untuk produksi, lebih baik **keyless signing** (OIDC + Fulcio + Rekor) yang mengikat tanda tangan ke identitas (mis. workflow CI) tanpa mengelola kunci, atau simpan kunci di KMS. Lab ini memakai key-based agar bisa offline.'
    source: https://docs.sigstore.dev/cosign/signing/signing_with_containers/

  - id: a11
    tier: A
    type: command
    objective: signing
    prompt: Tanda tangani image `registry.lab/app:v1` memakai private key `cosign.key`.
    answers: ['cosign sign --key cosign.key registry.lab/app:v1']
    patterns: ['^(?=.*\s--key \S*\.key(\s|$))cosign sign( (--[a-z-]+[= ]\S+|--[a-z-]+|-[a-zA-Z] ?\S+|-y))* registry\.lab/app:v1$']
    tests:
      pass: ['cosign sign --key cosign.key -y registry.lab/app:v1', 'cosign sign -y --key cosign.key registry.lab/app:v1']
      fail: ['cosign sign registry.lab/app:v1', 'cosign sign --key cosign.pub registry.lab/app:v1', 'docker sign registry.lab/app:v1']
    explanation: 'Di lab, `cosign sign --key cosign.key ...` → `Pushing signature to: registry.lab/app`. Tanda tangan disimpan sebagai artefak terpisah di registry, **terikat ke digest** image (bukan tag). Tandatangani dengan **privat** (`.key`), verifikasi dengan **publik** (`.pub`) — tertukar = gagal. Idealnya tanda tangani by-digest hasil build di CI, bukan tag yang mutable.'
    source: https://docs.sigstore.dev/cosign/signing/signing_with_containers/

  - id: a12
    tier: A
    type: command
    objective: signing
    prompt: Verifikasi tanda tangan image `registry.lab/app:v1` memakai public key `cosign.pub`.
    answers: ['cosign verify --key cosign.pub registry.lab/app:v1']
    patterns: ['^(?=.*\s--key \S*\.pub(\s|$))cosign verify( (--[a-z-]+[= ]\S+|--[a-z-]+|-[a-zA-Z] ?\S+))* registry\.lab/app:v1$']
    tests:
      pass: ['cosign verify --key cosign.pub registry.lab/app:v1', 'cosign verify --key cosign.pub --insecure-ignore-tlog=true registry.lab/app:v1']
      fail: ['cosign verify registry.lab/app:v1', 'cosign verify --key cosign.key registry.lab/app:v1', 'cosign check registry.lab/app:v1']
    explanation: 'Di lab, image bertanda tangan → `The signatures were verified against the specified public key` (lolos); image lain tanpa tanda tangan → `Error: no signatures found` (gagal). Verifikasi membuktikan image **benar dari penanda tangan tepercaya dan belum diubah**. Jalankan di deploy/admission (mis. Kubernetes admission controller) agar hanya image bertanda tangan sah yang boleh jalan — menutup image palsu/ter-tamper.'
    source: https://docs.sigstore.dev/cosign/signing/signing_with_containers/

  - id: a13
    tier: A
    type: text
    objective: signing
    prompt: Serangan yang menyusupkan kode jahat lewat dependensi, image dasar, atau tool build (bukan menyerang aplikasimu langsung) disebut apa?
    answers: ['supply chain attack']
    patterns: ['supply[- ]?chain', 'rantai pasok(an)?', 'serangan (rantai )?(pasok|supply)']
    tests:
      fail: ['brute force', 'ddos', 'phishing', 'sql injection']
    explanation: 'Serangan supply chain (ATT&CK T1195) menyerang titik yang kamu **percaya** secara default: paket npm/pip, base image, action CI, atau sistem build vendor. Karena kamu menariknya dengan sukarela, ia melewati banyak pertahanan (contoh terkenal: SolarWinds, event-stream, xz-utils). Pertahanan: scan (trivy), pin digest, verifikasi tanda tangan (cosign), SBOM untuk audit, dan minimalkan dependensi. Modul 11 memperluas ini ke pipeline.'
    source: https://attack.mitre.org/techniques/T1195/

  - id: a14
    tier: A
    type: text
    objective: signing
    prompt: Serangan supply chain yang mendaftarkan paket bernama **mirip** paket populer (mis. `reqeusts` alih-alih `requests`) berharap orang salah ketik, disebut apa?
    answers: ['typosquatting']
    patterns: ['typo[- ]?squatting', 'typosquat', 'salah ketik']
    tests:
      fail: ['dependency confusion', 'credential stuffing', 'phishing', 'clickjacking']
    explanation: 'Typosquatting menaruh paket jahat dengan nama yang sering salah ketik di registry publik. Satu typo di `requirements.txt`/`package.json` bisa menarik malware yang berjalan saat install. Pertahanan: pin versi + hash di lockfile, review dependensi baru, pakai registry/proxy internal yang hanya meneruskan paket yang disetujui, dan scan dependensi. Sepupunya **dependency confusion** (b-tier) mengeksploitasi resolusi nama internal vs publik.'
    source: https://attack.mitre.org/techniques/T1195/

  - id: a15
    tier: A
    type: text
    objective: patch
    prompt: Agar instalasi dependensi aplikasi (npm/pip/composer) menghasilkan versi yang sama persis & terverifikasi hash-nya di setiap build, kamu harus commit berkas apa?
    answers: ['lockfile']
    patterns: ['lock[- ]?file', 'package-lock', 'composer\.lock', 'yarn\.lock', 'poetry\.lock', 'pnpm-lock', 'berkas (kunci|lock)']
    tests:
      fail: ['.gitignore', 'Dockerfile', 'README', '.env']
    explanation: 'Lockfile (`package-lock.json`, `composer.lock`, `poetry.lock`, dll) merekam versi persis + hash tiap dependensi (termasuk transitif), sehingga build reproducible dan dependensi tidak diam-diam berubah. Ini padanan pinning digest (a06) di level paket aplikasi. Commit lockfile ke git, dan install dengan mode yang **menghormati**-nya (`npm ci`, bukan `npm install`) agar tidak diam-diam meng-upgrade.'
    source: https://owasp.org/Top10/A06_2021-Vulnerable_and_Outdated_Components/

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: scan-image
    prompt: Opsi trivy yang mengabaikan CVE yang **belum ada perbaikannya** (agar temuan bisa ditindaklanjuti) adalah apa?
    answers: ['--ignore-unfixed']
    patterns: ['--ignore-unfixed', 'ignore[- ]?unfixed', 'abaikan (yang )?(belum|tidak) (ada )?fix']
    tests:
      fail: ['--severity LOW', '--skip-update', '--no-progress', '--exit-code 0']
    explanation: '`--ignore-unfixed` menyembunyikan CVE yang vendornya belum merilis perbaikan — kamu tidak bisa berbuat apa-apa untuk itu selain mitigasi lain, jadi menampilkannya di gate build hanya menimbulkan kebisingan. Fokuskan build-breaking pada HIGH/CRITICAL yang **ada fix**-nya (bisa langsung di-patch). CVE unfixed tetap dicatat & dipantau, tapi tidak menghentikan rilis. Ini mencegah "CVE fatigue" yang membuat tim mengabaikan scanner sama sekali.'
    source: https://trivy.dev/latest/docs/

  - id: b02
    tier: B
    type: text
    objective: minimal
    prompt: Base image yang hanya berisi aplikasi + runtime-nya, **tanpa shell dan package manager**, secara umum disebut apa?
    answers: ['distroless']
    patterns: ['distroless', 'tanpa (shell|distro)', 'scratch']
    tests:
      fail: ['alpine', 'slim', 'full', 'busybox']
    explanation: 'Distroless (atau `scratch` untuk biner statis) menghapus shell, package manager, dan utilitas OS. Dampak keamanan ganda: (1) nyaris tidak ada paket OS → sangat sedikit CVE untuk di-scan/patch; (2) tidak ada `sh`/`curl`/`apk` untuk disalahgunakan penyerang yang sudah masuk (mempersulit Execution & Lateral Movement, modul 06). Trade-off: debugging lebih sulit (tidak ada shell) — pakai ephemeral debug container atau image "debug" terpisah.'
    source: https://owasp.org/Top10/A06_2021-Vulnerable_and_Outdated_Components/

  - id: b03
    tier: B
    type: text
    objective: signing
    prompt: Di lab, dua tag berbeda yang menunjuk **digest yang sama** dua-duanya lolos `cosign verify`. Tanda tangan cosign sebenarnya terikat ke apa?
    answers: ['digest image']
    patterns: ['digest', 'sha256', '(isi|konten|content)[^\n]*image', 'bukan tag']
    tests:
      fail: ['nama tag', 'nama repository', 'nama image', 'tanggal build']
    explanation: 'Di lab, menandatangani satu image lalu mem-verify tag lain yang menunjuk digest sama → tetap lolos, karena tanda tangan cosign mengikat **digest** (hash isi), bukan tag. Implikasinya: selalu deploy & verifikasi **by-digest**, karena tag bisa dipindahkan ke image lain yang tidak bertanda tangan. "Tanda tangan untuk konten, bukan untuk nama." Ini juga kenapa image dengan isi berbeda (digest berbeda) gagal verify walau namanya sama.'
    source: https://docs.sigstore.dev/cosign/signing/signing_with_containers/

  - id: b04
    tier: B
    type: text
    objective: signing
    prompt: Di lab, `cosign verify` pada image yang tidak ditandatangani menghasilkan error apa (menandakan verifikasi gagal)?
    answers: ['no signatures found']
    patterns: ['no signatures( found)?', 'tidak ada (tanda ?tangan|signature)', 'tidak ditemukan (signature|tanda ?tangan)']
    tests:
      fail: ['access denied', 'image not found', 'invalid key format', 'connection refused']
    explanation: 'Di lab, image tanpa tanda tangan → `Error: no signatures found` (exit non-zero). Inilah inti kebijakan **deny-by-default** untuk deploy: jika verifikasi gagal (tidak ada tanda tangan, atau tanda tangan dari kunci lain), tolak jalankan image. Dipasang di admission controller (mis. Kyverno/Sigstore policy-controller di Kubernetes), ini mencegah image yang tidak dikenal/ter-tamper masuk ke production.'
    source: https://docs.sigstore.dev/cosign/signing/signing_with_containers/

  - id: b05
    tier: B
    type: text
    objective: signing
    prompt: Penandatanganan cosign **keyless** mengikat tanda tangan ke identitas (mis. workflow CI) lewat OIDC + Fulcio, dan mencatatnya di transparency log bernama apa?
    answers: ['Rekor']
    patterns: ['^rekor$', 'rekor']
    tests:
      fail: ['Fulcio', 'Vault', 'Notary', 'Ledger']
    explanation: 'Keyless signing menghilangkan masalah mengelola & merotasi private key: Fulcio menerbitkan sertifikat berumur pendek berbasis identitas OIDC, dan **Rekor** adalah transparency log publik yang mencatat setiap penandatanganan (bisa diaudit, tidak bisa diam-diam dihapus). Di lab kita memakai key-based + `--insecure-ignore-tlog` agar bisa offline; di produksi, keyless + Rekor memberi provenance yang lebih kuat tanpa kunci yang bisa dicuri.'
    source: https://docs.sigstore.dev/cosign/signing/signing_with_containers/

  - id: b06
    tier: B
    type: text
    objective: sbom
    prompt: Keunggulan utama menyimpan SBOM sebagai artefak rilis adalah bisa menjawab pertanyaan apa saat CVE **baru** terungkap (mis. Log4Shell)?
    answers: ['apakah kita memakai komponen itu']
    patterns: ['(apakah|apa)[^\n]*(pakai|memakai|terdampak|terpengaruh|punya)[^\n]*(komponen|library|paket|versi)', '(komponen|library|paket)[^\n]*(mana|apa)[^\n]*(terdampak|dipakai)', 'terdampak atau tidak']
    tests:
      fail: ['berapa harga lisensi', 'siapa yang build', 'kapan image dibuat', 'berapa ukuran image']
    explanation: 'Saat CVE kritis baru muncul, pertanyaan pertama incident response adalah "apakah kita terdampak — apakah kita memakai komponen/versi itu, dan di mana?". Dengan SBOM per rilis, jawabannya instan: cari nama + versi. Tanpa SBOM, tim menghabiskan berjam-jam memeriksa tiap layanan secara manual. SBOM lama juga bisa di-scan ulang terhadap database CVE terbaru tanpa rebuild — kerentanan "diam" yang baru diketahui jadi terlihat.'
    source: https://cyclonedx.org/

  - id: b07
    tier: B
    type: text
    objective: signing
    prompt: Serangan supply chain yang mengeksploitasi resolver paket yang memilih versi **publik** lebih tinggi ketimbang paket **internal** bernama sama disebut apa?
    answers: ['dependency confusion']
    patterns: ['dependency[- ]?confusion', 'substitution attack', 'kebingungan dependensi', 'namespace confusion']
    tests:
      fail: ['typosquatting', 'dependency pinning', 'lockfile poisoning', 'privilege escalation']
    explanation: 'Dependency confusion: penyerang menerbitkan paket publik dengan nama **sama** seperti paket internal perusahaan, dengan versi lebih tinggi. Resolver yang mencari di registry publik + internal sekaligus bisa menarik versi publik (jahat) karena "lebih baru". Pertahanan: scoped packages/namespace, konfigurasi registry agar nama internal **hanya** dari registry internal, dan pin versi + hash di lockfile. Ini membobol banyak perusahaan besar saat pertama diungkap (2021).'
    source: https://attack.mitre.org/techniques/T1195/

  - id: b08
    tier: B
    type: text
    objective: patch
    prompt: Tool otomatis yang membuka pull request untuk memperbarui dependensi/base image yang usang atau rentan disebut (sebutkan salah satu) apa?
    answers: ['Dependabot']
    patterns: ['dependabot', 'renovate', '(bot )?update dependensi', 'auto[- ]?update']
    tests:
      fail: ['Jenkins', 'SonarQube', 'Prometheus', 'Ansible']
    explanation: 'Dependabot (GitHub) atau Renovate memantau dependensi & digest base image, lalu otomatis membuka PR saat ada versi baru/patch keamanan. Ini menjawab keberatan pinning digest (a06): "update jadi merepotkan" — bot yang melakukannya untukmu, dengan PR yang bisa di-review & diuji CI. Hasilnya: image/dependensi tetap ter-patch (a02) **dan** reproducible (pinned) sekaligus, dengan jejak perubahan yang jelas.'
    source: https://owasp.org/Top10/A06_2021-Vulnerable_and_Outdated_Components/

  - id: b09
    tier: B
    type: text
    objective: minimal
    prompt: Selain lebih aman, kenapa build **multi-stage** (modul Docker 03) membantu keamanan image akhir?
    answers: ['tool build & dependensi build tidak ikut ke image akhir']
    patterns: ['(tool|build[- ]?tool|compiler|dependensi build|sdk|secret)[^\n]*(tidak|tak|bukan)[^\n]*(ikut|masuk|ada)[^\n]*(image|akhir|final|runtime)', '(image|stage) akhir[^\n]*(hanya|cuma)[^\n]*(runtime|artefak|hasil)', 'buang[^\n]*(build|compiler|tool)']
    tests:
      fail: ['image jadi lebih besar', 'build lebih lambat', 'tidak perlu base image', 'otomatis menandatangani']
    explanation: 'Multi-stage memisahkan tahap build (compiler, SDK, dev dependencies, mungkin secret build) dari image akhir yang hanya berisi runtime + artefak. Hasilnya image akhir jauh lebih kecil **dan** lebih aman: tidak ada compiler/`git`/`curl` untuk disalahgunakan penyerang, lebih sedikit paket untuk CVE, dan secret build (dengan `--mount=type=secret`, modul 04) tidak tertinggal. Ini menggabungkan prinsip minimal (a04) + pemisahan build/runtime.'
    source: https://owasp.org/Top10/A06_2021-Vulnerable_and_Outdated_Components/

  - id: b10
    tier: B
    type: text
    objective: pinning
    prompt: Risiko memakai tag `latest` untuk image di production (selain tidak reproducible) saat pod/container restart adalah apa?
    answers: ['bisa menarik versi berbeda tanpa sengaja']
    patterns: ['(tarik|pull|dapat)[^\n]*(versi|image)[^\n]*(berbeda|baru|lain|tak terduga)', '(berubah|berganti)[^\n]*(diam-diam|tanpa|tak)', 'tidak konsisten antar (node|restart|replica)', 'drift']
    tests:
      fail: ['lebih lambat', 'butuh lebih banyak disk', 'tidak bisa di-scan', 'otomatis aman']
    explanation: '`latest` di-resolve saat pull. Saat container restart atau pod dijadwalkan ke node lain (yang mem-pull ulang), ia bisa mendapat image yang **berbeda** dari yang diuji — menimbulkan drift antar-replica, bug yang sulit direproduksi, atau menarik image yang diam-diam dikompromikan. Pin digest memastikan setiap node menjalankan byte yang sama persis. Di Kubernetes, pasangkan dengan `imagePullPolicy` yang sesuai.'
    source: https://docs.docker.com/dhi/core-concepts/digests/

  - id: b11
    tier: B
    type: text
    objective: signing
    prompt: Kerangka kerja (framework) yang mendefinisikan tingkat jaminan integritas untuk proses build/supply chain (mis. build tidak bisa dimanipulasi, ada provenance) disebut apa?
    answers: ['SLSA']
    patterns: ['^slsa$', 'salsa', 'supply[- ]?chain levels']
    tests:
      fail: ['SBOM', 'CVSS', 'STRIDE', 'OWASP']
    explanation: 'SLSA (Supply-chain Levels for Software Artifacts, dibaca "salsa") adalah tangga tingkat jaminan: dari sekadar ada provenance, hingga build yang terisolasi & tidak bisa dimanipulasi dengan provenance terverifikasi. Ia menjawab "bisakah aku percaya cara artefak ini dibuat?". Cosign (tanda tangan) + attestation provenance + build CI yang terkunci (modul 11) adalah langkah-langkah menaiki SLSA. SBOM menjawab "apa isinya"; SLSA menjawab "bagaimana ia dibuat".'
    source: https://slsa.dev/

  - id: b12
    tier: B
    type: text
    objective: scan-image
    prompt: Selain memindai image, trivy juga bisa memindai kerentanan di direktori kode/proyek (dependensi aplikasi) memakai subcommand apa?
    answers: ['fs']
    patterns: ['^fs$', 'trivy fs', 'filesystem', 'repo(sitory)?']
    tests:
      fail: ['scan', 'inspect', 'audit', 'ls']
    explanation: '`trivy fs .` memindai filesystem/proyek: lockfile (`package-lock.json`, dll) untuk CVE dependensi, plus misconfig IaC dan secret. Jadi trivy menutup banyak permukaan: `image` (image jadi), `fs` (kode & dependensi), `repo` (git), dan `config` (Dockerfile/K8s/Terraform, modul 11). Jalankan `trivy fs` di CI sebelum build untuk menangkap dependensi rentan lebih awal, dan `trivy image` setelah build untuk image akhir.'
    source: https://trivy.dev/latest/docs/

  - id: b13
    tier: B
    type: text
    objective: minimal
    prompt: 'Benar/salah: image dengan ukuran file besar selalu berarti lebih banyak kerentanan daripada image kecil.'
    answers: ['salah']
    patterns: ['^salah$', '^false$', '^tidak( selalu| benar)?$']
    tests:
      fail: ['benar', 'true', 'ya']
    explanation: 'Salah — yang menentukan adalah **jumlah & versi paket**, bukan ukuran byte. Di lab, `nginx:mainline-alpine` (72 paket, 12 CVE) sedikit lebih banyak paketnya dari `nginx:1.27-alpine` EOL (68 paket) tetapi jauh lebih sedikit CVE-nya, karena versinya ter-patch. Ukuran berkorelasi kasar dengan jumlah paket, tapi image kecil yang EOL tetap berbahaya. Fokus pada paket minimal **dan** ter-patch, bukan sekadar byte terkecil.'
    source: https://owasp.org/Top10/A06_2021-Vulnerable_and_Outdated_Components/

  - id: b14
    tier: B
    type: text
    objective: signing
    prompt: Agar hanya image bertanda tangan sah yang boleh berjalan di cluster, verifikasi cosign dipasang di komponen Kubernetes apa?
    answers: ['admission controller']
    patterns: ['admission( controller| webhook)?', 'policy controller', 'kyverno', 'gatekeeper', 'validating webhook']
    tests:
      fail: ['kubelet', 'scheduler', 'service mesh', 'ingress']
    explanation: 'Admission controller (mis. Sigstore policy-controller, Kyverno, atau Gatekeeper) mencegat setiap permintaan menjalankan pod dan menolaknya bila image tidak lolos `cosign verify` terhadap kunci/identitas tepercaya. Ini menegakkan deny-by-default (b04) di gerbang cluster — image tak bertanda tangan atau ter-tamper tidak pernah dijadwalkan. Gabungan dengan pin digest (a06), ini memastikan "apa yang jalan = persis apa yang kamu tandatangani".'
    source: https://docs.sigstore.dev/cosign/signing/signing_with_containers/

  - id: b15
    tier: B
    type: text
    objective: patch
    prompt: Dalam ATT&CK, menyusupkan kode jahat ke produk/komponen sebelum sampai ke korban (lewat dependensi, image, atau vendor) adalah teknik T1195 dengan nama apa?
    answers: ['Supply Chain Compromise']
    patterns: ['supply[- ]?chain compromise', 'kompromi (rantai )?(pasok|supply)', 't1195']
    tests:
      fail: ['Valid Accounts', 'Exploit Public-Facing Application', 'Phishing', 'External Remote Services']
    explanation: 'Supply Chain Compromise (T1195) mencakup sub-teknik: kompromi dependensi & alat dev, kompromi rantai distribusi software, dan kompromi hardware. Penyerang menyukainya karena satu kompromi menjangkau **banyak** korban hilir sekaligus (contoh: SolarWinds, xz-utils 2024). Pertahanan berlapis board ini — scan (trivy), pin (digest/lockfile), verifikasi (cosign), audit (SBOM), minimal (distroless) — semuanya menyerang permukaan T1195 ini dari sudut berbeda.'
    source: https://attack.mitre.org/techniques/T1195/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: scan-image
    prompt: |
      Audit menemukan image production kamu memakai `FROM node:16` (EOL) via tag `latest`, tanpa scanning, tanpa SBOM,
      dan dependensi npm di-install dengan `npm install` tanpa commit lockfile. Rancang perbaikan supply chain-nya
      (satu poin per baris).
    checklist:
      - id: patch-base
        label: Ganti base ke versi yang didukung & ter-patch (bukan EOL)
        required: true
        patterns: ['(ganti|upgrade|update|pakai)[^\n]*(base|node|versi)[^\n]*(baru|terbaru|didukung|lts|ter-?patch)', '(jangan|hindari|stop)[^\n]*eol', 'node (18|20|lts)']
      - id: pin-digest
        label: Pin base image dengan digest (@sha256), bukan tag latest
        required: true
        patterns: ['pin[^\n]*digest', '@sha256', 'digest[^\n]*(from|base)', '(jangan|bukan)[^\n]*(tag )?latest']
      - id: scan-ci
        label: Scan image dengan trivy di CI (gagalkan build HIGH/CRITICAL fixable)
        required: true
        patterns: ['trivy', '(scan|pindai)[^\n]*(ci|image|build)', '(gagalkan|fail|blokir)[^\n]*(build|high|critical)']
      - id: lockfile
        label: Commit lockfile + install dengan npm ci (bukan npm install)
        patterns: ['lock(file)?|package-lock', 'npm ci', '(commit|pakai)[^\n]*lock']
      - id: sbom
        label: Hasilkan & simpan SBOM per rilis
        patterns: ['sbom', 'cyclonedx|spdx', 'bill of materials']
      - id: minimal
        label: Pakai base minimal/distroless + multi-stage
        patterns: ['distroless', 'minimal', 'slim', 'multi-?stage']
      - id: no-eol
        label: Tidak membiarkan image EOL / tag latest di production
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|hindari|ganti|upgrade))[^\n]*(node:?16|eol|tag latest)[^\n]*(cukup|aman|biarkan|tetap)']
    pass_score: 0.5
    solution: |
      ```text
      1. Base ter-patch: ganti node:16 (EOL) → versi LTS yang masih didukung (mis. node:20-...), rebuild rutin.
      2. Pin digest: FROM node:20-bookworm-slim@sha256:... (bukan tag latest/floating) → reproducible & tak bisa
         diam-diam diganti. Otomatiskan update digest dengan Dependabot/Renovate.
      3. Scan di CI: trivy image --severity HIGH,CRITICAL --ignore-unfixed → gagalkan build untuk yang fixable;
         trivy fs untuk dependensi npm.
      4. Lockfile: commit package-lock.json; install dengan `npm ci` (menghormati lockfile, reproducible), bukan
         `npm install` yang bisa meng-upgrade diam-diam.
      5. SBOM: trivy image --format cyclonedx → simpan per rilis untuk audit CVE mendatang.
      6. Minimal + multi-stage: build di stage terpisah, image akhir slim/distroless (tanpa toolchain & dev deps).
      ```
      Enam langkah ini menutup supply chain berlapis: patch (CVE sekarang), pin (reproducible & anti-tamper), scan
      (temukan dini), lockfile (dependensi aplikasi terkunci), SBOM (audit masa depan), minimal (perkecil surface).
      Membiarkan node:16 EOL + tag latest adalah dua masalah berbeda yang keduanya harus ditutup.
    tests:
      pass:
        - |
          ganti base node:16 EOL ke versi LTS yang masih didukung dan ter-patch lalu rebuild rutin
          pin base image dengan digest @sha256 bukan tag latest, otomatiskan dengan renovate
          scan image pakai trivy di ci dan gagalkan build untuk HIGH CRITICAL yang fixable plus trivy fs untuk npm
          commit package-lock.json dan install dengan npm ci bukan npm install
          hasilkan sbom cyclonedx dan simpan per rilis
          pakai base distroless minimal dengan multi-stage build
        - |
          upgrade node ke lts yang didukung dan pin digest sha256 bukan latest
          trivy scan di ci gagalkan build critical fixable
          commit lockfile pakai npm ci, simpan sbom, base slim multi-stage
      fail:
        - |
          node:16 masih jalan kok, biarkan saja dengan tag latest
          yang penting aplikasinya tidak error
        - |
          pakai npm install biar dapat versi terbaru otomatis
          tidak perlu lockfile atau scan
        - |
          scan sekali manual di laptop
          tidak perlu pin digest
    source: https://owasp.org/Top10/A06_2021-Vulnerable_and_Outdated_Components/

  - id: c02
    tier: C
    type: scenario
    objective: signing
    prompt: |
      Tim ingin memastikan hanya image yang dibangun pipeline resmi (bukan image acak/ter-tamper) yang boleh berjalan
      di cluster. Rancang alur sign & verify dengan cosign beserta penegakannya (satu poin per baris).
    checklist:
      - id: sign-ci
        label: Pipeline menandatangani image (by-digest) setelah build, dgn kunci/identitas tepercaya
        required: true
        patterns: ['(pipeline|ci)[^\n]*(sign|tanda ?tangan)', 'cosign sign', '(sign|tanda ?tangan)[^\n]*(digest|by-?digest)']
      - id: keyless
        label: Pakai keyless (OIDC/Fulcio + Rekor) atau kunci di KMS, bukan kunci di laptop
        patterns: ['keyless', 'oidc|fulcio|rekor', 'kms', '(kunci|key)[^\n]*(ci|kms|aman)']
      - id: verify-admission
        label: Verifikasi di admission controller (deny-by-default) sebelum pod jalan
        required: true
        patterns: ['admission( controller| webhook)?', 'policy[- ]?controller|kyverno|gatekeeper', '(verify|verifikasi)[^\n]*(deploy|cluster|pod|admission)']
      - id: by-digest
        label: Deploy & verifikasi by-digest (karena tanda tangan terikat digest, tag mutable)
        patterns: ['by-?digest', 'digest[^\n]*(deploy|verify)', '(jangan|bukan)[^\n]*tag[^\n]*(deploy|verify)', 'tag mutable']
      - id: deny-default
        label: Image tanpa tanda tangan sah → ditolak jalan (no signatures = gagal)
        required: true
        patterns: ['(tolak|reject|deny|blokir|gagal)[^\n]*(image|tanpa tanda|tidak (sah|valid))', 'no signatures', 'deny[- ]?by[- ]?default']
      - id: scan-too
        label: Tetap scan (trivy) + SBOM — signing membuktikan asal, bukan bebas CVE
        patterns: ['trivy|scan|sbom', '(signing|tanda ?tangan)[^\n]*(bukan|tidak)[^\n]*(bebas cve|aman dari|menjamin aman)', 'tetap (scan|pindai)']
      - id: not-signing-enough
        label: Tidak menganggap image bertanda tangan pasti bebas kerentanan
        forbid: true
        patterns: ['^(?![^\n]*(bukan|jangan|tidak|selain|tetap))[^\n]*(bertanda ?tangan|signed|sign)[^\n]*(pasti|otomatis|berarti)[^\n]*(aman|bebas cve|tanpa kerentanan)']
    pass_score: 0.5
    solution: |
      ```text
      1. Pipeline resmi menandatangani image BY-DIGEST setelah build: cosign sign (idealnya keyless via OIDC/Fulcio
         sehingga tanda tangan terikat identitas workflow CI, tercatat di Rekor) — atau kunci di KMS, bukan di laptop.
      2. Admission controller (Sigstore policy-controller / Kyverno) memverifikasi cosign pada setiap pod:
         - hanya terima tanda tangan dari identitas/kunci pipeline resmi,
         - DENY-BY-DEFAULT: image tanpa tanda tangan sah → "no signatures found" → ditolak jalan.
      3. Deploy & verifikasi BY-DIGEST (bukan tag), karena tanda tangan terikat digest dan tag bisa dipindah.
      4. Signing membuktikan ASAL & INTEGRITAS, bukan bebas CVE → tetap jalankan trivy + simpan SBOM.
      ```
      Hasil lab mendasari ini: image bertanda tangan → "signatures verified against the specified public key"; tanpa
      tanda tangan → "no signatures found" (gagal). Karena tanda tangan terikat digest (dua tag ke digest sama
      dua-duanya lolos), penegakan harus by-digest. Dan jangan rancu: image bertanda tangan sah bisa saja tetap punya
      CVE — signing dan scanning menjawab pertanyaan berbeda (asal vs isi).
    tests:
      pass:
        - |
          pipeline resmi menandatangani image by-digest dengan cosign sign setelah build
          pakai keyless oidc fulcio rekor atau kunci di kms bukan kunci di laptop
          admission controller seperti kyverno memverifikasi cosign sebelum pod jalan
          deploy dan verifikasi by-digest karena tanda tangan terikat digest dan tag mutable
          image tanpa tanda tangan sah ditolak jalan deny-by-default no signatures found
          tetap scan trivy dan simpan sbom karena signing membuktikan asal bukan bebas cve
        - |
          ci sign image by-digest keyless lewat fulcio rekor
          policy controller verify deny-by-default, image tanpa signature ditolak
          deploy by-digest, dan tetap scan trivy karena signing bukan jaminan bebas kerentanan
      fail:
        - |
          tandatangani image maka otomatis aman dan bebas cve
          tidak perlu scan lagi
        - |
          simpan cosign.key di laptop developer
          verify manual sesekali saja
        - |
          verify pakai tag latest saja
          image bertanda tangan pasti tidak punya kerentanan
    source: https://docs.sigstore.dev/cosign/signing/signing_with_containers/

  - id: c03
    tier: C
    type: scenario
    objective: patch
    prompt: |
      CVE kritis baru (anggap "libfoo RCE") diumumkan hari ini. Manajer bertanya: "apakah kita terdampak, dan di
      layanan mana?" Rancang cara menjawab cepat + mencegah berulang, memakai perkakas supply chain (satu poin per
      baris).
    checklist:
      - id: sbom-search
        label: Cari di SBOM tiap rilis apakah libfoo (& versi) dipakai, dan di mana
        required: true
        patterns: ['sbom', '(cari|search|grep)[^\n]*(komponen|libfoo|versi|library)', '(komponen|library)[^\n]*(terdampak|dipakai)']
      - id: rescan
        label: Scan ulang image/fs dengan trivy (DB CVE terbaru) untuk konfirmasi
        required: true
        patterns: ['trivy', '(scan|pindai)[^\n]*(ulang|lagi|terbaru|update)', '(update|perbarui)[^\n]*(db|database)[^\n]*cve']
      - id: prioritize
        label: Prioritaskan layanan yang terekspos/terdampak (berbasis dampak)
        patterns: ['prioritas(kan)?', '(fokus|dahulukan)[^\n]*(terekspos|publik|kritis|terdampak)', 'triase']
      - id: patch-rebuild
        label: Patch (update libfoo) + rebuild + redeploy image terdampak
        required: true
        patterns: ['(patch|update|upgrade)[^\n]*(libfoo|dependensi|versi)', 'rebuild[^\n]*(image|redeploy)', '(rebuild|bangun ulang|redeploy)']
      - id: verify-after
        label: Scan ulang setelah patch untuk konfirmasi CVE hilang
        patterns: ['(scan|trivy|verifikasi)[^\n]*(ulang|lagi|setelah|konfirmasi)', 'pastikan[^\n]*(hilang|fixed|clear)']
      - id: prevent
        label: Otomatisasi — scan terjadwal + Dependabot/Renovate + SBOM tiap rilis
        patterns: ['(dependabot|renovate)', '(scan|pindai)[^\n]*(terjadwal|berkala|otomat)', 'sbom[^\n]*(tiap|setiap|rilis)']
      - id: not-guess
        label: Tidak menebak manual tanpa SBOM/scan (lambat & rawan salah)
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|daripada|alih-alih))[^\n]*(tebak|nebak|kira-?kira|manual satu per satu)[^\n]*(cukup|saja|aman)']
    pass_score: 0.5
    solution: |
      ```text
      Menjawab cepat "apakah terdampak & di mana":
        1. Cari di SBOM tiap rilis/layanan apakah libfoo (dan versi rentannya) ada → jawaban dalam detik, bukan jam.
        2. Scan ulang image & fs dengan trivy (DB CVE sudah diperbarui) untuk konfirmasi + menemukan jalur transitif.
        3. Prioritaskan berbasis dampak: layanan yang terekspos publik / memproses data sensitif lebih dulu.
      Perbaikan:
        4. Patch: update libfoo ke versi ber-fix → rebuild image terdampak → redeploy.
        5. Scan ulang setelah patch untuk memastikan CVE benar hilang.
      Mencegah berulang:
        6. Otomatisasi: trivy terjadwal pada image yang sedang berjalan (bukan hanya saat build), Dependabot/Renovate
           untuk PR patch, dan SBOM disimpan tiap rilis agar pertanyaan berikutnya terjawab instan.
      ```
      SBOM (a08) + scanning (a01) mengubah "apakah kita terdampak?" dari investigasi manual berjam-jam menjadi query
      detik. Scan terjadwal penting karena image yang kemarin "bersih" bisa jadi rentan hari ini saat CVE baru
      terungkap — kerentanannya diam, bukan muncul dari perubahan image. Menebak manual tanpa data adalah lambat dan
      mudah salah (melewatkan dependensi transitif).
    tests:
      pass:
        - |
          cari di sbom tiap rilis apakah libfoo dan versinya dipakai dan di layanan mana
          scan ulang image dan fs dengan trivy yang db cve-nya sudah terbaru untuk konfirmasi
          prioritaskan layanan yang terekspos publik dan terdampak lebih dulu
          patch update libfoo lalu rebuild image terdampak dan redeploy
          scan ulang setelah patch untuk memastikan cve hilang
          otomatisasi scan terjadwal dependabot dan sbom tiap rilis supaya tidak berulang
        - |
          query sbom tiap layanan untuk libfoo, scan ulang trivy dengan db terbaru
          dahulukan layanan publik terdampak, update libfoo rebuild dan redeploy
          verifikasi scan ulang setelah patch, pasang renovate dan scan terjadwal
      fail:
        - |
          tebak saja layanan mana yang mungkin pakai libfoo
          kalau tidak ingat berarti aman
        - |
          tunggu sampai ada yang komplain error
          baru dicek satu per satu manual
        - |
          scan sekali saat build dulu sudah cukup
          cve baru tidak mempengaruhi image lama
    source: https://cyclonedx.org/
---

# Keamanan Image & Supply Chain

Setiap `docker pull` dan `npm install` menarik kode orang lain ke sistemmu — beserta kerentanan dan, kadang, niat
jahatnya. Kamu memercayai base image, dependensi, dan tool build secara default; penyerang tahu itu. Modul ini
memindai, mem-pin, mengaudit, dan menandatangani agar yang kamu jalankan benar-benar yang kamu maksud dan aman.

> [!NOTE]
> Lab: `trivy` (container `aquasec/trivy`) memindai image daemon lewat socket; `cosign` v2.4.3 + `registry:2` lokal
> untuk sign/verify (key-based, tanpa transparency log agar offline). Semua lokal milik sendiri.

## 1. Scanning: temukan CVE-mu sendiri (hasil lab)

```text
trivy image nginx:1.27-alpine   →  68 paket, 219 CVE (3 CRIT, 67 HIGH)   ← EOL
trivy image nginx:mainline-alpine → 72 paket,  12 CVE (0 CRIT, 1 HIGH)   ← ter-patch
trivy image alpine:3.20         →  14 paket,   2 CVE                      ← minimal
trivy image python:3.12-alpine  →  39 paket,  38 CVE
```

Dua pelajaran langsung: **patch** (EOL 219 vs terbaru 12) dan **minimal** (alpine 14 paket). Jalankan di CI dengan
`--severity HIGH,CRITICAL --ignore-unfixed` agar gate-nya bisa ditindaklanjuti, bukan banjir noise.

## 2. Pinning: tag mutable, digest immutable (hasil lab)

```dockerfile
FROM nginx:1.27-alpine                       # ❌ tag bisa dipindah publisher kapan saja
FROM nginx:1.27-alpine@sha256:fb4658584bd9…  # ✅ digest = hash isi, reproducible
```

```text
docker inspect -f '{{index .RepoDigests 0}}' alpine:3.20  →  alpine@sha256:d9e853e8…
docker pull alpine@sha256:d9e853e8…                       →  selalu byte yang sama
```

Otomatiskan update digest dengan Dependabot/Renovate → tetap ter-patch **dan** reproducible. Padanannya di level
aplikasi: **lockfile** (`npm ci`, bukan `npm install`).

## 3. SBOM: inventori komponen (hasil lab)

```text
trivy image --format cyclonedx alpine:3.20  →  CycloneDX 1.7, 15 components (OS + library)
```

Saat CVE baru meledak (Log4Shell, xz), SBOM menjawab "apakah kita memakai komponen itu, di mana?" dalam detik. Simpan
SBOM tiap rilis; ia bisa di-scan ulang terhadap CVE yang baru diketahui tanpa rebuild.

## 4. Signing: provenance (hasil lab)

```text
cosign generate-key-pair                              →  cosign.key (privat) + cosign.pub (publik)
cosign sign   --key cosign.key  registry/app:v1       →  Pushing signature to registry
cosign verify --key cosign.pub  registry/app:v1       →  ✅ signatures verified against public key
cosign verify --key cosign.pub  registry/app:lain     →  ❌ Error: no signatures found
```

- Tanda tangan **terikat ke digest**, bukan tag (dua tag ke digest sama dua-duanya lolos) → selalu deploy & verifikasi
  **by-digest**.
- Tegakkan di **admission controller** (deny-by-default): image tanpa tanda tangan sah tidak boleh jalan.
- Produksi: **keyless** (OIDC + Fulcio + Rekor) lebih kuat daripada menyimpan kunci.

> [!WARNING]
> Signing membuktikan **asal & integritas**, bukan bebas CVE. Image bertanda tangan sah tetap bisa rentan — signing
> dan scanning menjawab pertanyaan berbeda (bagaimana ia dibuat vs apa isinya). Lakukan keduanya.

## 5. Serangan supply chain

| Serangan | Cara | Pertahanan |
|---|---|---|
| Image/base ter-tamper | tukar isi di balik tag | pin digest + verify cosign |
| Typosquatting | nama paket mirip (`reqeusts`) | review deps, lockfile + hash |
| Dependency confusion | versi publik > internal senama | scoped/namespace, registry internal |
| Dependensi rentan | library usang (OWASP A06) | trivy fs, lockfile, Dependabot |

Semua menyerang titik yang kamu **percaya** (ATT&CK T1195). SLSA mengukur seberapa tepercaya proses build-mu; SBOM
mencatat isinya.

## 6. Ringkasan

| Pertanyaan | Alat |
|---|---|
| Apa image/depku rentan? | `trivy image` / `trivy fs` |
| Apakah ini byte yang sama tiap build? | pin digest + lockfile |
| Apa saja isinya? | SBOM (CycloneDX/SPDX) |
| Apakah ini benar dari kami & utuh? | cosign sign/verify (by-digest) |
| Seberapa tepercaya cara build-nya? | SLSA + provenance |

> [!NOTE]
> Lanjut ke modul 06 (Hardening container & runtime) — setelah image bersih & tepercaya, kita batasi apa yang bisa
> dilakukan container itu saat berjalan, agar satu kompromi tidak menjadi container escape.
