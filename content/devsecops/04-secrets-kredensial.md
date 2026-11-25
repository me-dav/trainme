---
format: 1
id: sec-secrets-credentials
course:
  slug: devsecops
  title: DevSecOps
  description: Keamanan untuk DevOps dari dua sisi — cara kerja red team dan cara blue team mendeteksi serta menutupnya. Model ancaman, hardening server & container, keamanan web, ketersediaan (anti-DDoS), deteksi, respons insiden, dan keamanan pipeline. Semua praktik di lab terisolasi milik sendiri.
title: Secrets & Kredensial
summary: "Kredensial yang bocor melewati semua lapisan pertahanan lain — penyerang tinggal login. Modul ini menunjukkan di mana secret bocor (riwayat git, layer image, variabel ENV, config cache) dan cara menutupnya: scanning (detect-secrets/gitleaks + pre-commit), rotasi (secret yang pernah bocor = bocor selamanya), dan injeksi saat runtime (pola *_FILE, Docker/BuildKit secrets, secret manager) alih-alih menanamkannya ke image atau git."
order: 4
version: 1
status: verified
updated: '2026-11-25'
tested_on:
  - Docker Desktop (Windows), Engine 29.4.1, BuildKit — leak layer vs RUN --mount=type=secret
  - python:3.12-alpine — git 2.54, detect-secrets 1.5.0 (pip); demo di repo lab lokal
objectives:
  - id: leak-vectors
    text: Mengenali tempat secret bocor — git history, layer image, ENV, config cache, log
  - id: scanning
    text: Mendeteksi secret dengan detect-secrets/gitleaks dan pre-commit hook
  - id: rotation
    text: Memahami bahwa secret yang pernah bocor harus dirotasi, bukan sekadar dihapus
  - id: injection
    text: Menyuntikkan secret saat runtime (pola *_FILE, Docker/BuildKit secrets) bukan di image
  - id: storage
    text: Menyimpan secret di secret manager, tidak pernah di git atau image
sources:
  - title: OWASP — Secrets Management Cheat Sheet
    url: https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html
  - title: detect-secrets (Yelp)
    url: https://github.com/Yelp/detect-secrets
  - title: gitleaks — scan git history for secrets
    url: https://github.com/gitleaks/gitleaks
  - title: Docker — build secrets (RUN --mount=type=secret)
    url: https://docs.docker.com/build/building/secrets/
  - title: Docker Compose — secrets & the _FILE convention
    url: https://docs.docker.com/compose/how-tos/use-secrets/
  - title: MITRE ATT&CK — Unsecured Credentials (T1552)
    url: https://attack.mitre.org/techniques/T1552/
  - title: git — removing sensitive data (filter-repo / BFG)
    url: https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: leak-vectors
    prompt: Kenapa kredensial yang bocor dianggap paling berbahaya dibanding kerentanan lain?
    answers: ['melewati semua pertahanan lain']
    patterns: ['(lewati|melewati|lewat|bypass|lolos)[^\n]*(pertahanan|lapisan|kontrol|keamanan)', 'penyerang (tinggal|langsung) (login|masuk|pakai)', 'akses sah|login langsung']
    tests:
      fail: ['karena sulit diperbaiki', 'karena jarang terjadi', 'karena butuh exploit', 'karena merusak data']
    explanation: 'Dengan kredensial sah, penyerang login sebagai user/sistem yang valid — tidak perlu exploit, dan aktivitasnya terlihat "normal" sehingga sulit dibedakan dari pengguna asli. Kredensial bocor melewati firewall, hardening SSH, dan semua lapisan modul sebelumnya sekaligus (ATT&CK T1552, Unsecured Credentials). Itu sebabnya mengelola secret adalah salah satu kontrol berdampak tertinggi.'
    source: https://attack.mitre.org/techniques/T1552/

  - id: a02
    tier: A
    type: command
    objective: leak-vectors
    prompt: Tampilkan seluruh riwayat git **beserta diff isinya** (untuk mencari secret yang pernah ada lalu "dihapus").
    answers: ['git log -p']
    patterns: ['^git log( --all)? -p( --all)?$', '^git log -p --all$']
    tests:
      pass: ['git log -p --all', 'git log --all -p']
      fail: ['git log', 'git log --oneline', 'git show', 'git diff']
    explanation: 'Di lab, secret di-commit lalu "dihapus" di commit berikutnya. File saat ini bersih (`git grep` di HEAD → nihil), tetapi `git log -p --all` masih memperlihatkan `-AWS_SECRET_ACCESS_KEY = "..."` di diff lama. **Menghapus secret dari file tidak menghapusnya dari riwayat.** Siapa pun yang mengkloning repo mendapat seluruh riwayat. `--all` mencakup semua branch & ref, karena secret bisa bersembunyi di branch yang tak di-merge.'
    source: https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository

  - id: a03
    tier: A
    type: text
    objective: rotation
    prompt: Sebuah API key ter-commit ke git lalu dihapus di commit berikutnya. Tindakan **wajib** yang utama adalah apa?
    answers: ['rotasi key']
    patterns: ['rotasi', 'rotate', 'ganti (key|kunci|kredensial|secret|token)', 'cabut|revoke|invalidate']
    tests:
      fail: ['hapus file-nya', 'git commit --amend', 'tambah ke .gitignore', 'ubah permission file']
    explanation: 'Secret yang pernah ter-ekspos harus dianggap **bocor permanen** → segera rotasi/cabut (terbitkan yang baru, matikan yang lama). Membersihkan riwayat git (filter-repo/BFG) itu sekunder dan tidak menjamin — fork, clone, cache CI, dan mirror mungkin sudah menyalinnya. Urutan benar: rotasi dulu (menutup risiko nyata), baru pertimbangkan membersihkan riwayat. Ini sama dengan pelajaran `.env`/`.git` bocor di modul 02.'
    source: https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository

  - id: a04
    tier: A
    type: command
    objective: scanning
    prompt: Pindai direktori kerja proyek untuk mencari secret dengan **detect-secrets**.
    answers: ['detect-secrets scan']
    patterns: ['^detect-secrets scan( \S+)*$']
    tests:
      pass: ['detect-secrets scan', 'detect-secrets scan config.py', 'detect-secrets scan .']
      fail: ['detect-secrets', 'detect-secrets audit', 'git secrets scan', 'detectsecrets scan']
    explanation: 'Di lab, `detect-secrets scan secrets.py` menemukan `AWS Access Key`, `GitHub Token`, `Base64 High Entropy String`, dan `Secret Keyword`. Ia memakai banyak plugin: pola spesifik (AWS, GitHub, Stripe, ...) + deteksi string ber-entropi tinggi + kata kunci (`password =`, `secret =`). Jalankan sebagai pre-commit hook agar secret ketahuan **sebelum** masuk ke commit (dan ke riwayat selamanya).'
    source: https://github.com/Yelp/detect-secrets

  - id: a05
    tier: A
    type: text
    objective: scanning
    prompt: Agar secret tidak pernah masuk ke commit sejak awal, scanner seperti detect-secrets/gitleaks paling baik dipasang sebagai apa (berjalan otomatis sebelum setiap commit)?
    answers: ['pre-commit hook']
    patterns: ['pre[- ]?commit( hook)?', 'git hook', 'hook (sebelum )?commit']
    tests:
      fail: ['cron job', 'post-merge hook', 'CI saja', 'systemd timer']
    explanation: 'Pre-commit hook menjalankan scanner pada perubahan sebelum commit dibuat; jika ada secret, commit ditolak. Ini mencegah secret masuk riwayat sejak awal — jauh lebih murah daripada membersihkannya nanti. Pasang juga di CI (sebagai jaring pengaman, karena hook lokal bisa dilewati `--no-verify`). Dua lapis: lokal cepat untuk developer, CI wajib untuk merge (modul 11).'
    source: https://github.com/Yelp/detect-secrets

  - id: a06
    tier: A
    type: command
    objective: leak-vectors
    prompt: Tampilkan daftar **layer** sebuah image `dj-lab/app` (untuk melihat perintah build tiap layer).
    answers: ['docker history dj-lab/app']
    patterns: ['^docker history( --no-trunc| -H=\S+| --format \S+)* dj-lab/app$']
    tests:
      pass: ['docker history --no-trunc dj-lab/app', 'docker history dj-lab/app']
      fail: ['docker inspect dj-lab/app', 'docker images dj-lab/app', 'docker layers dj-lab/app', 'docker history']
    explanation: 'Di lab, `docker history` memperlihatkan tiap layer beserta instruksinya. Ini penting karena **image terdiri dari banyak layer, dan setiap layer menyimpan isinya sendiri**. Secret yang di-`COPY` lalu di-`rm` di layer berikutnya tetap ada di layer awal. `docker history` adalah langkah pertama memeriksa apakah ada perintah build yang membocorkan secret (mis. `ARG`/`ENV` secret, `COPY .env`).'
    source: https://docs.docker.com/build/building/secrets/

  - id: a07
    tier: A
    type: text
    objective: leak-vectors
    prompt: Di lab, sebuah secret di-`COPY` ke image lalu di-`rm` pada layer berikutnya. Apakah secret itu hilang dari image?
    answers: ['tidak']
    patterns: ['^tidak$', '^tidak,?.*', '^no$', 'masih ada', 'tetap ada']
    tests:
      fail: ['ya', 'ya hilang', 'hilang']
    explanation: 'Tidak. Di lab, `docker save dj-lab/leaky | tar -x` lalu memeriksa blob layer tetap menemukan `SUPER_SECRET_TOKEN=ghp_...`, meski `ls /tmp/secret.txt` di image final berkata "No such file". `rm` di layer berikutnya hanya menandai file terhapus di layer itu — isi layer sebelumnya utuh dan bisa diekstrak. Siapa pun yang punya image (mis. dari registry) bisa memanennya. Jangan pernah menaruh secret di layer image, titik.'
    source: https://docs.docker.com/build/building/secrets/

  - id: a08
    tier: A
    type: text
    objective: injection
    prompt: Instruksi Dockerfile (BuildKit) yang menyediakan secret ke sebuah `RUN` **tanpa** menyimpannya ke layer image mana pun?
    answers: ['RUN --mount=type=secret']
    patterns: ['--mount=type=secret', 'mount[^\n]*type=?secret', 'run --mount .*secret']
    tests:
      fail: ['ARG SECRET', 'ENV SECRET', 'COPY secret.txt', 'VOLUME /secret']
    explanation: 'Di lab, `RUN --mount=type=secret,id=tok ...` (dibangun dengan `docker build --secret id=tok,src=secret.txt`) memakai secret saat build, dan pemeriksaan semua layer membuktikan secret **tidak** ter-bake ke image (CLEAN). Secret di-mount sementara di `/run/secrets/<id>` hanya selama RUN itu. Ini cara benar memakai token (mis. untuk `pip install` dari repo privat) tanpa meninggalkan jejak. Bandingkan dengan `ARG`/`ENV` yang justru tersimpan di metadata/layer.'
    source: https://docs.docker.com/build/building/secrets/

  - id: a09
    tier: A
    type: command
    objective: leak-vectors
    prompt: Tampilkan variabel environment yang tersimpan di konfigurasi container `dj-web` (untuk mengecek apakah secret bocor lewat ENV).
    answers: ['docker inspect -f {{.Config.Env}} dj-web']
    patterns: ['^docker inspect (-f|--format)[= ]?\{\{[^}]*\.Config\.Env[^}]*\}\} dj-web$']
    tests:
      pass: ['docker inspect --format {{.Config.Env}} dj-web', 'docker inspect -f {{.Config.Env}} dj-web']
      fail: ['docker inspect dj-web', 'docker env dj-web', 'docker inspect -f {{.Config.Cmd}} dj-web']
    explanation: 'Di lab, container dengan `-e DB_PASSWORD=supersecret123` → `docker inspect -f ''{{.Config.Env}}''` menampilkan `DB_PASSWORD=supersecret123`, dan `/proc/1/environ` di dalamnya juga. Variabel ENV bukan tempat rahasia: ia terlihat di `docker inspect`, log orkestrator, dan bisa dibaca proses lain. Siapa pun dengan akses Docker API (atau yang bisa `exec` ke container) membacanya. Pakai pola `*_FILE` (a10) agar nilainya tidak nyangkut di sini.'
    source: https://docs.docker.com/compose/how-tos/use-secrets/

  - id: a10
    tier: A
    type: text
    objective: injection
    prompt: Konvensi umum (dipakai image Postgres, MySQL, dll) untuk memberi path file berisi secret lewat variabel env, bukan nilainya langsung, memakai suffix apa?
    answers: ['_FILE']
    patterns: ['_file', 'suffix _file', 'xxx_file']
    tests:
      fail: ['_SECRET', '_PATH', '_KEY', '_ENV']
    explanation: 'Di lab, `DB_PASSWORD_FILE=/run/secrets/db_password` + entrypoint yang membaca file itu → `docker inspect .Config.Env` hanya memperlihatkan **path**-nya, bukan `supersecret123`. Nilai sebenarnya diinjeksikan saat runtime dari file (mis. Docker/Swarm/K8s secret yang di-mount di `/run/secrets`). Ini menutup kebocoran lewat `docker inspect`/image/registry. Inilah fitur yang **hilang** di entrypoint aplikasi contoh kita (temuan modul Docker 11).'
    source: https://docs.docker.com/compose/how-tos/use-secrets/

  - id: a11
    tier: A
    type: text
    objective: storage
    prompt: Sistem khusus untuk menyimpan, mengenkripsi, memberi akses terkontrol, dan merotasi secret (mis. HashiCorp Vault, AWS Secrets Manager) disebut apa secara umum?
    answers: ['secret manager']
    patterns: ['secret(s)?[- ]?manager', 'secret(s)?[- ]?store', 'vault', 'manajer (secret|rahasia)']
    tests:
      fail: ['password di .env', 'config map', 'environment file', 'keyring browser']
    explanation: 'Secret manager menyimpan secret terenkripsi, memberi akses berbasis identitas (bukan file yang ikut repo), mencatat siapa mengaksesnya (audit), dan memudahkan rotasi. Aplikasi mengambil secret saat runtime lewat API/agent, bukan dari file di repo atau ENV di image. Untuk tim kecil, minimal: secret di CI/CD secret store + injeksi saat deploy, dengan `.env` hanya di server (tidak pernah di git).'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html

  - id: a12
    tier: A
    type: text
    objective: storage
    prompt: Berkas apa yang harus memuat `.env` (dan file secret lain) agar tidak pernah ikut ter-commit ke git?
    answers: ['.gitignore']
    patterns: ['\.gitignore', 'gitignore']
    tests:
      fail: ['.dockerignore', '.gitattributes', '.npmignore', 'README']
    explanation: '`.env` wajib ada di `.gitignore` sejak repo dibuat. Tapi hati-hati: `.gitignore` hanya mencegah file **baru** ter-track — jika `.env` sudah terlanjur ter-commit, ia tetap di riwayat (a02) sampai dirotasi + dibersihkan. Lengkapi dengan `.dockerignore` (agar `.env` tidak ikut `COPY . .` ke image, modul Docker 03) dan pre-commit scanner sebagai jaring pengaman.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html

  - id: a13
    tier: A
    type: command
    objective: leak-vectors
    prompt: Baca environment milik proses PID 1 di dalam container (dari shell di dalamnya), untuk membuktikan secret ENV bisa dibaca proses lain.
    answers: ['tr \0 \n < /proc/1/environ']
    patterns: ['^(cat|tr[^<]*|strings) ?.*/proc/1/environ.*$', '^tr .*/proc/1/environ$', '^cat /proc/1/environ$']
    tests:
      pass: ['cat /proc/1/environ', 'strings /proc/1/environ', 'tr "\\0" "\\n" < /proc/1/environ']
      fail: ['cat /proc/1/cmdline', 'env', 'printenv', 'cat /proc/self/status']
    explanation: 'Di lab, `tr "\0" "\n" < /proc/1/environ | grep DB_PASSWORD` menampilkan `DB_PASSWORD=supersecret123`. `/proc/<pid>/environ` berisi environment proses itu (dipisah NUL). Artinya secret di ENV bisa dibaca proses lain dalam container yang sama, dan oleh siapa pun yang bisa `exec`. Bahkan dengan pola `*_FILE`, nilai yang sudah di-`export` ke proses tetap terbaca di sini — idealnya aplikasi membaca file secret langsung tanpa menaruhnya di env.'
    source: https://attack.mitre.org/techniques/T1552/

  - id: a14
    tier: A
    type: text
    objective: leak-vectors
    prompt: Di aplikasi contoh Laravel kita, `php artisan config:cache` menulis nilai config (termasuk password DB) ke `bootstrap/cache` dalam bentuk apa?
    answers: ['plaintext']
    patterns: ['plain[- ]?text', 'teks (biasa|polos|jelas)', 'tidak terenkripsi', 'apa adanya']
    tests:
      fail: ['terenkripsi', 'hash', 'base64 aman', 'terkompresi']
    explanation: 'Temuan dari modul Docker 11: `config:cache` mengevaluasi `env()` dan menuliskan hasilnya (termasuk `DB_PASSWORD`) sebagai **plaintext** ke `bootstrap/cache/config.php`. Jika file cache itu ikut ke image atau terekspos (mis. web root salah, backup), password bocor. Ini contoh "secret bocor ke artefak turunan". Mitigasi: jangan commit/COPY folder cache, bangun cache saat runtime setelah secret diinjeksikan, dan batasi akses filesystem.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html

  - id: a15
    tier: A
    type: text
    objective: scanning
    prompt: Selain pola spesifik (AWS, GitHub), detect-secrets juga menandai string acak panjang memakai deteksi berbasis apa?
    answers: ['entropi']
    patterns: ['entrop', 'high[- ]?entropy', 'keacakan|randomness']
    tests:
      fail: ['panjang string', 'jumlah huruf besar', 'bahasa', 'ukuran file']
    explanation: 'Di lab, detect-secrets menandai secret acak sebagai `Base64 High Entropy String`. Entropi mengukur "keacakan" — token/kunci asli terlihat acak (entropi tinggi), beda dari teks biasa. Karena itu di lab, token "contoh" yang sekuensial (`1234...abcd`) justru **tidak** terdeteksi (difilter sebagai sequential), sedangkan token ber-entropi realistis tertangkap. Pelajaran: scanner bukan sihir — pahami false negative/positive-nya, dan jangan hanya mengandalkan satu alat.'
    source: https://github.com/Yelp/detect-secrets

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: scanning
    prompt: detect-secrets di lab memindai **working tree** dan melewatkan secret yang sudah dihapus dari HEAD tapi masih di riwayat. Tool apa yang khusus memindai seluruh **riwayat git**?
    answers: ['gitleaks']
    patterns: ['gitleaks', 'trufflehog', 'git-secrets']
    tests:
      fail: ['detect-secrets', 'grep', 'nmap', 'trivy']
    explanation: 'Di lab, `detect-secrets scan` pada file bersih mengembalikan `{}` meski riwayatnya memuat secret. gitleaks (dan trufflehog) memindai commit demi commit sepanjang riwayat, sehingga menemukan secret yang sudah "dihapus". Praktik: gitleaks di CI untuk riwayat + detect-secrets pre-commit untuk mencegah yang baru. Keduanya saling melengkapi; gunakan sesuai apa yang ingin kamu lihat (sekarang vs sepanjang waktu).'
    source: https://github.com/gitleaks/gitleaks

  - id: b02
    tier: B
    type: text
    objective: rotation
    prompt: Tool yang dipakai menulis-ulang riwayat git untuk benar-benar menghapus berkas/secret dari semua commit (pengganti `filter-branch`) adalah git-filter-repo dan satu lagi yang bernama apa?
    answers: ['BFG']
    patterns: ['\bbfg\b', 'bfg repo[- ]?cleaner']
    tests:
      fail: ['git gc', 'git rebase', 'git stash', 'git prune']
    explanation: 'git-filter-repo dan BFG Repo-Cleaner menulis-ulang riwayat untuk menghapus secret dari semua commit. Tapi ini **sekunder** setelah rotasi: menulis ulang riwayat mengubah semua hash commit (mengganggu kolaborator), dan tidak menjangkau fork/clone/cache yang sudah menyebar. Anggap secret yang pernah publik sudah bocor permanen — rotasi adalah perbaikan sejati; pembersihan riwayat hanya merapikan agar tidak terulang/terindeks.'
    source: https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository

  - id: b03
    tier: B
    type: command
    objective: injection
    prompt: Bangun image `dj-lab/app` dengan menyuntikkan secret dari file `secret.txt` ke build lewat BuildKit (id `tok`), tanpa menanamkannya ke layer.
    answers: ['docker build --secret id=tok,src=secret.txt -t dj-lab/app .']
    patterns: ['^(?=.* --secret id=tok,src=secret\.txt(\s|$))docker build( .+)? \.$']
    tests:
      pass: ['docker build -t dj-lab/app --secret id=tok,src=secret.txt .', 'docker build --secret id=tok,src=secret.txt -t dj-lab/app .']
      fail: ['docker build -t dj-lab/app .', 'docker build --build-arg SECRET=x -t dj-lab/app .', 'docker build --secret secret.txt -t dj-lab/app .']
    explanation: 'Di lab, build ini (dengan `# syntax=docker/dockerfile:1` + `RUN --mount=type=secret,id=tok`) menghasilkan image yang **bersih** dari secret di semua layer. `--secret id=tok,src=secret.txt` menyediakan file ke mount `/run/secrets/tok` hanya saat RUN. Bandingkan `--build-arg` yang nilainya tersimpan di `docker history` dan metadata image — jangan pakai build-arg untuk secret.'
    source: https://docs.docker.com/build/building/secrets/

  - id: b04
    tier: B
    type: text
    objective: injection
    prompt: Kenapa `ARG` dan `--build-arg` **tidak** aman untuk secret di Docker?
    answers: ['nilainya tersimpan di history/metadata image']
    patterns: ['(tersimpan|tercatat|ada|muncul|bocor)[^\n]*(history|histori|metadata|image|layer)', 'docker history[^\n]*(bocor|tampil|lihat)', 'image menyimpan']
    tests:
      fail: ['terlalu lambat', 'tidak didukung BuildKit', 'hanya untuk angka', 'otomatis terenkripsi']
    explanation: 'Nilai `ARG` muncul di `docker history` dan tersimpan di metadata image, jadi siapa pun yang punya image bisa membacanya — sama buruknya dengan `ENV` atau `COPY secret`. `ARG` dirancang untuk parameter build non-rahasia (versi, flag), bukan secret. Untuk secret saat build, satu-satunya cara bersih adalah `RUN --mount=type=secret` (BuildKit). Pelajaran umum: segala yang masuk ke layer/metadata harus dianggap publik.'
    source: https://docs.docker.com/build/building/secrets/

  - id: b05
    tier: B
    type: text
    objective: injection
    prompt: Di orkestrator, secret yang di-mount ke container biasanya muncul sebagai file di direktori mana (konvensi `tmpfs` in-memory)?
    answers: ['/run/secrets']
    patterns: ['/run/secrets', 'run/secrets']
    tests:
      fail: ['/etc/secrets', '/var/secrets', '/tmp/secrets', '/secrets']
    explanation: 'Docker/Swarm/Kubernetes me-mount secret sebagai file di `/run/secrets/<name>`, biasanya di `tmpfs` (RAM, tidak menyentuh disk). Aplikasi membacanya saat runtime. Keuntungannya vs ENV: tidak muncul di `docker inspect .Config.Env`, tidak ter-bake ke image, dan hilang saat container mati. Pola `*_FILE` (a10) memberi tahu aplikasi path ini. Tetap batasi siapa yang bisa `exec` ke container, karena file itu terbaca dari dalam.'
    source: https://docs.docker.com/compose/how-tos/use-secrets/

  - id: b06
    tier: B
    type: text
    objective: scanning
    prompt: detect-secrets memakai berkas `.secrets.baseline` untuk apa?
    answers: ['menandai temuan yang sudah ditinjau/diizinkan']
    patterns: ['(temuan|finding)[^\n]*(sudah|telah)[^\n]*(tinjau|review|audit|izin|terima)', 'allowlist|whitelist|baseline', 'false positive[^\n]*(tandai|catat|abaikan)', 'membedakan (baru|lama)']
    tests:
      fail: ['menyimpan secret terenkripsi', 'mempercepat scan', 'konfigurasi database', 'daftar plugin saja']
    explanation: 'Baseline mencatat temuan yang sudah ditinjau (mis. false positive atau placeholder test) agar scan berikutnya hanya memunculkan secret **baru**. Alurnya: `detect-secrets scan > .secrets.baseline`, lalu `detect-secrets audit .secrets.baseline` untuk menandai mana yang asli/palsu. Di CI, bandingkan dengan baseline → gagalkan hanya jika ada temuan baru. Ini membuat scanner bisa dipakai di repo nyata tanpa banjir false positive.'
    source: https://github.com/Yelp/detect-secrets

  - id: b07
    tier: B
    type: text
    objective: leak-vectors
    prompt: Selain git/image/ENV, tempat umum secret tak sengaja bocor yang sering dipantau penyerang & SIEM adalah apa (output runtime aplikasi)?
    answers: ['log']
    patterns: ['^log(s|ging)?$', 'berkas log', 'application log', 'stdout', 'stack trace']
    tests:
      fail: ['CPU cache', 'swap saja', 'DNS', 'ARP table']
    explanation: 'Secret sering bocor ke log: query yang di-log dengan password, header `Authorization` yang tercetak, exception yang memuat token, atau `curl -v` di skrip CI. Log lalu dikirim ke sistem terpusat yang banyak orang bisa baca. Mitigasi: redaksi/masking di logger, jangan log request/response mentah, jangan log env, dan scan log untuk pola secret. Modul 09 membahas logging aman lebih dalam.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html

  - id: b08
    tier: B
    type: text
    objective: rotation
    prompt: Praktik merotasi secret secara **berkala dan otomatis** (bukan hanya saat bocor) mengurangi apa bila sebuah secret diam-diam sudah dicuri?
    answers: ['jendela waktu penyalahgunaan']
    patterns: ['(jendela|window|masa|rentang|durasi)[^\n]*(waktu|serang|penyalahgunaan|valid|hidup)', 'secret cepat (kadaluarsa|expire|tidak berlaku)', 'memperpendek[^\n]*(waktu|masa)']
    tests:
      fail: ['ukuran secret', 'jumlah user', 'biaya server', 'kecepatan login']
    explanation: 'Rotasi berkala memperpendek masa berlaku tiap secret, sehingga secret yang dicuri diam-diam hanya berguna sampai rotasi berikutnya. Secret berumur pendek (short-lived, mis. token OIDC yang hidup menit-an) adalah bentuk ekstrem & ideal: bahkan bila bocor, cepat tidak berlaku. Ini melengkapi deteksi — kamu tidak selalu tahu kapan bocor, jadi batasi umur sebagai asumsi default (assume breach).'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html

  - id: b09
    tier: B
    type: text
    objective: storage
    prompt: Di pipeline CI/CD, secret (token deploy, kredensial registry) seharusnya disimpan di mana, bukan di dalam berkas `.yml` pipeline?
    answers: ['CI secret store']
    patterns: ['(ci|cd|pipeline)[^\n]*(secret|variable|vault)', 'secret(s)? (store|manager|variable)', 'masked variable', 'repository secret|actions secret', 'vault']
    tests:
      fail: ['di README', 'hardcode di yml', 'di nama branch', 'di commit message']
    explanation: 'Platform CI (GitHub Actions secrets, GitLab CI variables, dll) menyimpan secret terenkripsi dan menyuntikkannya sebagai env **ter-masking** saat job jalan — tidak ikut repo, tidak tampil di log (dimask). Jangan hardcode di `.yml` (itu ikut git = a02). Hati-hati tetap bisa bocor jika job mencetaknya (mis. `echo $TOKEN`) atau lewat PR dari fork yang jahat — batasi scope token & trigger (modul 11).'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html

  - id: b10
    tier: B
    type: command
    objective: leak-vectors
    prompt: Ekstrak isi sebuah image `dj-lab/leaky` ke direktori saat ini untuk memeriksa layer-nya (menyalurkan `docker save` langsung ke `tar`).
    answers: ['docker save dj-lab/leaky | tar -x']
    patterns: ['^docker save( -o \S+)? dj-lab/leaky \| tar (-x|-xv|-xf -|xf -|-x -)$', '^docker save dj-lab/leaky \| tar -x$']
    tests:
      pass: ['docker save dj-lab/leaky | tar -xv', 'docker save dj-lab/leaky | tar -x']
      fail: ['docker export dj-lab/leaky | tar -x', 'docker save dj-lab/leaky', 'tar -x dj-lab/leaky', 'docker cp dj-lab/leaky:/ .']
    explanation: 'Di lab, perintah ini membongkar image menjadi `blobs/`, `index.json`, `manifest.json`; memeriksa tiap blob layer menemukan `tmp/secret.txt` yang "sudah dihapus". Perhatikan beda `docker save` (seluruh image + semua layer + metadata, untuk analisis ini) vs `docker export` (hanya filesystem container yang berjalan, tanpa riwayat layer). Teknik ini dipakai penyerang yang mendapat image dari registry, maupun blue team yang mengaudit image.'
    source: https://docs.docker.com/build/building/secrets/

  - id: b11
    tier: B
    type: text
    objective: injection
    prompt: Pendekatan paling aman agar secret tidak nyangkut di env sama sekali adalah aplikasi membaca secret langsung dari mana?
    answers: ['file secret saat runtime']
    patterns: ['(baca|read)[^\n]*(file|berkas)[^\n]*(secret|runtime|/run/secrets)', '(file|berkas) secret', '/run/secrets', 'secret manager[^\n]*(api|runtime)']
    tests:
      fail: ['variabel ENV', 'argumen command line', 'build arg', 'nama file image']
    explanation: 'Di lab, pola `*_FILE` tetap meng-`export DB_PASSWORD`, sehingga nilainya muncul di `/proc/1/environ` (a13). Lebih aman lagi: aplikasi membaca file `/run/secrets/db_password` (atau memanggil secret manager) langsung ke memori saat butuh, tanpa pernah menaruhnya di env. Dengan begitu `docker inspect`, env dump, dan child process tidak bisa memanennya. Trade-off: butuh sedikit perubahan kode aplikasi.'
    source: https://docs.docker.com/compose/how-tos/use-secrets/

  - id: b12
    tier: B
    type: text
    objective: leak-vectors
    prompt: Menaruh secret di dalam **ConfigMap** Kubernetes (alih-alih Secret) berbahaya karena ConfigMap tidak melakukan apa terhadap nilainya?
    answers: ['tidak dienkripsi/di-encode sebagai rahasia']
    patterns: ['(tidak|tanpa|ga|gak)[^\n]*(enkrip|encode|rahasia|proteksi|lindung)', 'plaintext', 'terlihat (biasa|jelas|polos)', 'bukan (secret|rahasia)']
    tests:
      fail: ['tidak bisa di-mount', 'terlalu besar', 'hanya untuk angka', 'otomatis terhapus']
    explanation: 'ConfigMap untuk konfigurasi non-rahasia dan menyimpan nilai apa adanya (plaintext), tanpa perlakuan khusus, RBAC ketat, atau enkripsi at-rest. Secret Kubernetes setidaknya base64-encoded, bisa dienkripsi at-rest (etcd), dan diatur RBAC terpisah. Walau base64 **bukan** enkripsi (mudah di-decode), memilih objek yang tepat + enkripsi at-rest + RBAC adalah dasar. Idealnya tetap pakai secret manager eksternal (a11).'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html

  - id: b13
    tier: B
    type: text
    objective: scanning
    prompt: Hook pre-commit lokal bisa dilewati dengan flag `git commit --no-verify`. Karena itu scanning secret juga harus dijalankan di mana sebagai jaring pengaman wajib?
    answers: ['CI']
    patterns: ['\bci\b', 'ci/?cd', 'pipeline', 'server[- ]?side hook']
    tests:
      fail: ['editor', 'cron lokal', 'IDE plugin', 'README']
    explanation: 'Hook lokal membantu developer tapi bisa dilewati (`--no-verify`) atau tidak terpasang di mesin tertentu. CI menjalankan scanner pada setiap push/PR secara wajib dan tidak bisa dilewati oleh developer — menjadi gerbang sebelum merge. Pola umum: pre-commit cepat lokal (UX) + gitleaks/detect-secrets di CI (penegakan). Satu untuk kenyamanan, satu untuk jaminan (modul 11 Pipeline security).'
    source: https://github.com/gitleaks/gitleaks

  - id: b14
    tier: B
    type: text
    objective: storage
    prompt: 'Praktik yang salah: menyimpan `.env` production sebagai lampiran di chat tim atau tiket. Risiko utamanya apa?'
    answers: ['secret tersebar ke banyak tempat tak terkontrol']
    patterns: ['(tersebar|menyebar|banyak)[^\n]*(tempat|salinan|orang|sistem)', '(tidak|tak|sulit)[^\n]*(terkontrol|dilacak|dirotasi|dihapus)', 'banyak salinan', 'di luar kendali']
    tests:
      fail: ['chat terlalu lambat', 'format file rusak', 'terlalu besar', 'tidak kompatibel']
    explanation: 'Begitu `.env` masuk chat/tiket/email/drive, ia tersalin ke backup, indeks pencarian, dan perangkat banyak orang — tidak lagi bisa dilacak atau ditarik, dan rotasi jadi satu-satunya jalan bila ada yang keluar tim. Secret harus mengalir lewat jalur yang terkontrol & ter-audit (secret manager, CI secret store), bukan lewat komunikasi manusia. Perlakukan permintaan "kirim .env dong" sebagai red flag.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html

  - id: b15
    tier: B
    type: text
    objective: leak-vectors
    prompt: Dalam ATT&CK, teknik penyerang mencari kredensial yang tersimpan tanpa proteksi (di file, env, history, image) disebut apa (T1552)?
    answers: ['Unsecured Credentials']
    patterns: ['unsecured[- ]?credentials', 'kredensial (tak|tidak)[^\n]*(aman|terproteksi)', 't1552']
    tests:
      fail: ['Brute Force', 'Credential Stuffing', 'Phishing', 'Keylogging']
    explanation: 'Unsecured Credentials (T1552) mencakup sub-teknik seperti Credentials In Files, Credentials in Registry, dan Bash History — persis yang kita demonstrasikan (git history, layer image, ENV). Setelah Initial Access, penyerang menyisir host untuk kredensial yang memudahkan Lateral Movement & Privilege Escalation. Blue team memangkasnya dengan tidak pernah menyimpan secret di tempat-tempat itu + rotasi + deteksi.'
    source: https://attack.mitre.org/techniques/T1552/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: rotation
    prompt: |
      Seorang developer tidak sengaja mem-push file `config.py` berisi AWS secret key dan token GitHub ke repo
      (repo privat perusahaan). Ketahuan 2 hari kemudian. Susun respons yang benar beserta urutannya (satu poin per
      baris).
    checklist:
      - id: rotate-now
        label: Prioritas 1 — rotasi/cabut AWS key & token GitHub SEGERA (anggap bocor)
        required: true
        patterns: ['(rotasi|cabut|revoke|ganti|matikan|invalidate)[^\n]*(key|token|kredensial|aws|github)', '(aws|token)[^\n]*(rotasi|cabut|revoke|ganti)']
      - id: assess
        label: Cek penyalahgunaan (log akses AWS/GitHub selama 2 hari terbuka)
        patterns: ['(cek|periksa|audit|lihat)[^\n]*(log|akses|aktivitas|cloudtrail|penyalahgunaan)', 'cloudtrail', '(ada|apakah)[^\n]*(dipakai|disalahgunakan)']
      - id: history
        label: Bersihkan riwayat git (filter-repo/BFG) — sekunder, bukan pengganti rotasi
        patterns: ['(filter-repo|bfg|filter-branch)', '(bersih|hapus|rewrite)[^\n]*(riwayat|history)']
      - id: prevent
        label: Pasang pencegahan — pre-commit + gitleaks di CI, .gitignore, baseline
        required: true
        patterns: ['(pre-?commit|gitleaks|detect-secrets)', '(scan|scanner)[^\n]*(ci|commit)', '\.gitignore']
      - id: move-secret
        label: Pindahkan secret ke secret manager / injeksi runtime (jangan di repo)
        patterns: ['secret(s)?[- ]?manager', 'vault', '(injeksi|inject)[^\n]*runtime', '(jangan|tidak)[^\n]*(di )?(repo|git|code)']
      - id: not-just-delete
        label: Tidak menganggap menghapus file / commit baru sudah menyelesaikan
        forbid: true
        patterns: ['^(?![^\n]*(bukan|jangan|tidak cukup|selain|setelah rotasi))[^\n]*(hapus file|commit baru|git rm|amend)[^\n]*(cukup|selesai|aman|beres)']
    pass_score: 0.5
    solution: |
      ```text
      P1 (menit pertama): ROTASI/CABUT keduanya SEKARANG — di AWS IAM (nonaktifkan/hapus access key, buat baru) dan
          GitHub (revoke token). Secret sudah terbuka 2 hari di repo (yang punya banyak clone/CI) → anggap bocor.
      P2: Nilai dampak — periksa AWS CloudTrail & GitHub audit log selama jendela 2 hari untuk tanda penyalahgunaan
          (akses tak dikenal, resource baru). Bila ada, eskalasi ke incident response (modul 10).
      P3: Bersihkan riwayat (git-filter-repo / BFG) agar secret tak terus terindeks — SEKUNDER, karena fork/clone/
          cache CI mungkin sudah menyalinnya; ini merapikan, bukan mengamankan.
      P4: Cegah terulang — .gitignore untuk file secret, pre-commit (detect-secrets) + gitleaks di CI (wajib, tak
          bisa --no-verify), baseline untuk false positive.
      P5: Pindahkan secret ke secret manager / CI secret store + injeksi runtime; repo tidak boleh memuat secret.
      ```
      Urutan ini menegaskan prinsip modul: rotasi adalah perbaikan sejati (menutup risiko nyata), sementara menghapus
      file atau membersihkan riwayat **tidak** cukup — begitu secret pernah terbuka, ia harus dianggap bocor permanen.
      Lalu pasang pencegahan berlapis agar kejadian yang sama tidak terulang.
    tests:
      pass:
        - |
          prioritas pertama rotasi dan cabut AWS access key dan token GitHub segera karena dianggap sudah bocor
          periksa CloudTrail dan audit log GitHub selama 2 hari untuk tanda penyalahgunaan
          bersihkan riwayat git dengan filter-repo atau BFG sebagai langkah sekunder bukan pengganti rotasi
          pasang pencegahan pre-commit detect-secrets dan gitleaks di CI plus .gitignore dan baseline
          pindahkan secret ke secret manager dan injeksi saat runtime jangan di repo
        - |
          cabut kedua kredensial dulu di aws iam dan github
          cek log akses apakah sudah dipakai penyerang
          baru bersihkan history pakai bfg
          tambah gitleaks di ci dan pindahkan secret ke vault
      fail:
        - |
          hapus config.py dan buat commit baru
          sudah aman karena file sudah tidak ada
        - |
          git rm config.py lalu amend commit
          selesai, tidak perlu ganti key karena repo privat
        - |
          tambahkan config.py ke .gitignore
          beres
    source: https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository

  - id: c02
    tier: C
    type: scenario
    objective: injection
    prompt: |
      Aplikasi Laravel kita saat ini menaruh `DB_PASSWORD` sebagai `ENV` di image, dan `config:cache` menuliskannya
      plaintext ke `bootstrap/cache`. Rancang cara memperbaiki penanganan secret untuk container production (satu poin
      per baris).
    checklist:
      - id: no-env-image
        label: Jangan taruh password sebagai ENV di image / Dockerfile
        required: true
        patterns: ['(jangan|tidak|hindari|stop|hentikan|matikan|cabut|lepas|pindah)[^\n]*(env|environment)[^\n]*(image|dockerfile|password|secret)', '(password|secret)[^\n]*(jangan|tidak)[^\n]*(env|image)', 'tidak (bake|tanam)[^\n]*secret']
      - id: file-pattern
        label: Pakai pola *_FILE / mount secret di /run/secrets saat runtime
        required: true
        patterns: ['_file', '/run/secrets', 'docker secret', 'mount[^\n]*secret', 'compose secrets']
      - id: cache-runtime
        label: Bangun config:cache saat runtime (setelah secret diinjeksi), jangan di build
        patterns: ['config:cache[^\n]*(runtime|entrypoint|setelah|saat jalan)', '(jangan|tidak)[^\n]*cache[^\n]*(build|image)', 'cache[^\n]*(entrypoint|runtime)']
      - id: dockerignore
        label: .dockerignore untuk .env & bootstrap/cache (jangan ikut COPY)
        patterns: ['\.dockerignore', '(bootstrap/cache|\.env)[^\n]*(ignore|jangan (copy|ikut))', 'jangan copy[^\n]*(\.env|cache)']
      - id: buildkit
        label: Bila butuh secret saat build (composer/npm privat) pakai RUN --mount=type=secret
        patterns: ['--mount=type=secret', 'buildkit[^\n]*secret', 'build secret']
      - id: inspect-clean
        label: Verifikasi docker inspect .Config.Env tak memuat password
        patterns: ['docker inspect[^\n]*(env|config)', '(inspect|verifikasi|cek)[^\n]*(tidak|tak)[^\n]*(password|secret)', 'config.env[^\n]*(bersih|kosong|path)']
      - id: no-plaintext-commit
        label: Tidak membangun/commit cache berisi plaintext ke image
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|hindari))[^\n]*(commit|copy|bake)[^\n]*(bootstrap/cache|config cache|config:cache)[^\n]*(image|git)']
    pass_score: 0.5
    solution: |
      ```text
      1. Hapus DB_PASSWORD sebagai ENV di Dockerfile/image (bocor via docker inspect, /proc, registry).
      2. Injeksi saat runtime: pola *_FILE → DB_PASSWORD_FILE=/run/secrets/db_password, mount secret via
         Docker/Compose/Swarm secret (tmpfs /run/secrets). Entrypoint membaca file (idealnya aplikasi membaca
         langsung tanpa export ke env).
      3. Jangan build config:cache saat image build (saat itu secret belum ada / akan ter-bake plaintext). Jalankan
         `php artisan config:cache` di ENTRYPOINT setelah secret diinjeksi, lalu bersihkan saat stop bila perlu.
      4. .dockerignore memuat .env dan bootstrap/cache/* agar tidak ikut `COPY . .` ke image.
      5. Jika build butuh kredensial (composer repo privat / npm token): RUN --mount=type=secret, bukan ARG/ENV.
      6. Verifikasi: docker inspect -f '{{.Config.Env}}' → hanya DB_PASSWORD_FILE (path), bukan nilai; scan image
         layer (docker save | tar) tak memuat password.
      ```
      Ini memperbaiki tiga temuan modul Docker 11 sekaligus: ENV secret (bocor di inspect/proc), config:cache
      plaintext (bocor di artefak), dan .env ikut ke image. Prinsipnya: secret masuk **saat runtime**, dari sumber
      terkontrol, dan tidak pernah mengendap di image, metadata, atau cache yang bisa tersebar.
    tests:
      pass:
        - |
          jangan taruh DB_PASSWORD sebagai ENV di image atau Dockerfile karena bocor via docker inspect dan proc
          pakai pola DB_PASSWORD_FILE dengan mount secret di /run/secrets saat runtime via compose secrets
          jalankan config:cache di entrypoint setelah secret diinjeksi, jangan saat build image
          .dockerignore untuk .env dan bootstrap/cache supaya tidak ikut COPY
          kalau build butuh token composer privat pakai RUN --mount=type=secret bukan ARG
          verifikasi docker inspect Config.Env hanya memuat path bukan nilai password
        - |
          hentikan env password di image, pindah ke pola _FILE dan docker secret di /run/secrets
          config cache dibuat runtime di entrypoint bukan di build
          dockerignore .env dan folder cache
          build secret pakai buildkit mount, lalu cek inspect env bersih
      fail:
        - |
          biarkan DB_PASSWORD di ENV tapi passwordnya dibuat kuat
          config:cache tetap di build biar cepat
        - |
          commit bootstrap/cache ke image supaya tidak perlu generate lagi
          env password tidak masalah karena image privat
        - |
          pakai ARG DB_PASSWORD saat build
          selesai
    source: https://docs.docker.com/compose/how-tos/use-secrets/

  - id: c03
    tier: C
    type: scenario
    objective: scanning
    prompt: |
      Tim kecil ingin memastikan tidak ada lagi secret masuk ke repo, dan menemukan yang mungkin sudah terlanjur di
      riwayat. Rancang program secret-scanning berlapis beserta penanganan temuannya (satu poin per baris).
    checklist:
      - id: precommit
        label: Pre-commit hook (detect-secrets) di mesin developer
        required: true
        patterns: ['pre-?commit', 'hook[^\n]*commit', 'detect-secrets[^\n]*(lokal|developer|commit)']
      - id: ci-gate
        label: Scanner di CI sebagai gerbang wajib (tak bisa --no-verify)
        required: true
        patterns: ['(ci|pipeline)[^\n]*(scan|gitleaks|detect-secrets|gate|gerbang|wajib)', '(gitleaks|detect-secrets)[^\n]*ci', 'gerbang[^\n]*(merge|ci)']
      - id: history-scan
        label: Scan seluruh riwayat sekali (gitleaks) untuk secret lama
        required: true
        patterns: ['(gitleaks|trufflehog)[^\n]*(histor|riwayat|history|seluruh)', '(scan|pindai)[^\n]*(riwayat|history|semua commit)']
      - id: baseline
        label: Baseline untuk false positive (hanya temuan baru yang menggagalkan)
        patterns: ['baseline', 'false positive', '(hanya|cuma)[^\n]*(temuan|secret) baru']
      - id: rotate-found
        label: Setiap temuan asli → rotasi, bukan hanya dihapus
        required: true
        patterns: ['(temuan|secret) (asli|nyata|ditemukan)[^\n]*(rotasi|cabut|ganti)', 'rotasi[^\n]*temuan', '(semua|tiap)[^\n]*(temuan|secret)[^\n]*rotasi']
      - id: prevent-future
        label: Pindahkan secret ke manager + .gitignore + edukasi tim
        patterns: ['secret(s)?[- ]?manager|vault', '\.gitignore', '(edukasi|latih|ajari|awareness)[^\n]*tim']
      - id: not-only-scan
        label: Tidak menganggap scanning saja cukup tanpa rotasi temuan
        forbid: true
        patterns: ['^(?![^\n]*(bukan|jangan|tidak cukup|selain|lalu rotasi))[^\n]*(cukup|hanya)[^\n]*(scan|scanner|pindai)[^\n]*(aman|beres|selesai)']
    pass_score: 0.5
    solution: |
      ```text
      Pencegahan (secret baru):
        - Pre-commit hook detect-secrets di mesin developer → commit berisi secret ditolak lebih awal.
        - Scanner di CI (gitleaks/detect-secrets) sebagai gerbang WAJIB pada setiap PR/push — tidak bisa dilewati
          --no-verify seperti hook lokal.
        - Baseline (.secrets.baseline) agar hanya temuan BARU yang menggagalkan build (kurangi false positive).
      Penemuan (secret lama):
        - Scan seluruh riwayat sekali dengan gitleaks (ia membaca semua commit, tak seperti detect-secrets yang
          hanya working tree).
      Penanganan temuan:
        - Setiap temuan asli → ROTASI/cabut kredensialnya (menghapus dari kode tidak membatalkan yang sudah bocor).
      Jangka panjang:
        - Pindahkan semua secret ke secret manager / CI secret store, .env di .gitignore, edukasi tim ("jangan
          pernah commit/kirim secret").
      ```
      Berlapis: pre-commit (UX cepat) + CI (penegakan) mencegah yang baru; gitleaks riwayat menemukan yang lama;
      baseline menjaga agar bisa dipakai di repo nyata. Yang krusial: scanning hanya **menemukan** — tiap temuan
      nyata tetap harus dirotasi. Scanner tanpa rotasi hanya memberi rasa aman palsu.
    tests:
      pass:
        - |
          pre-commit hook detect-secrets di mesin developer supaya commit bersecret ditolak awal
          scanner gitleaks di ci sebagai gerbang wajib yang tidak bisa dilewati no-verify
          scan seluruh riwayat sekali dengan gitleaks untuk menemukan secret lama
          baseline untuk false positive supaya hanya temuan baru yang menggagalkan
          setiap temuan asli dirotasi bukan hanya dihapus
          pindahkan secret ke manager, .gitignore, dan edukasi tim
        - |
          pasang detect-secrets pre-commit lokal dan gitleaks di ci wajib
          scan history penuh pakai gitleaks untuk secret lama
          pakai baseline, lalu rotasi semua temuan asli
          jangka panjang pindah ke vault dan latih tim
      fail:
        - |
          jalankan gitleaks sekali
          kalau tidak ada temuan berarti aman selamanya
        - |
          pasang scanner di ci
          temuan tinggal dihapus dari kode, tidak perlu rotasi
        - |
          scan working tree dengan detect-secrets
          itu sudah mencakup seluruh riwayat
    source: https://github.com/gitleaks/gitleaks
---

# Secrets & Kredensial

Kredensial yang bocor adalah mimpi buruk: penyerang tinggal login sebagai user/sistem sah, melewati firewall,
hardening SSH, dan semua lapisan modul sebelumnya sekaligus (ATT&CK T1552). Modul ini menunjukkan **di mana** secret
bocor dan cara menutupnya — deteksi, rotasi, dan menyuntikkannya saat runtime alih-alih menanamkannya ke git atau
image.

> [!NOTE]
> Lab: `python:3.12-alpine` dengan `git` + `detect-secrets` (pip) untuk demo repo; Docker BuildKit untuk image
> leak vs secret mount. Semua di lab lokal milik sendiri.

## 1. Di mana secret bocor (hasil lab)

| Vektor | Bukti lab | Catatan |
|---|---|---|
| Riwayat git | `git log -p --all` masih memuat `AWS_SECRET...` yang "dihapus" | HEAD bersih, riwayat tidak |
| Layer image | `docker save \| tar` → `SUPER_SECRET_TOKEN=...` di blob layer | `rm` di layer berikut tak menghapus |
| ENV | `docker inspect .Config.Env` & `/proc/1/environ` → `DB_PASSWORD=...` | terbaca inspect & proses lain |
| Config cache | `config:cache` tulis password plaintext ke `bootstrap/cache` | artefak turunan (modul Docker 11) |
| Log | password di query/header yang ter-log | tersebar ke sistem log terpusat |

> [!WARNING]
> **Menghapus secret ≠ menghilangkannya.** Dari file (riwayat git tetap ada), dari layer (`rm` tak menghapus layer
> sebelumnya). Begitu pernah terbuka → anggap **bocor permanen** → rotasi.

## 2. Deteksi (hasil lab)

```text
detect-secrets scan secrets.py
  → AWS Access Key, GitHub Token, Base64 High Entropy String, Secret Keyword
```

- detect-secrets memakai pola spesifik + **entropi** + kata kunci. Token "contoh" yang sekuensial justru difilter;
  token ber-entropi realistis tertangkap — pahami false negative/positive-nya.
- Ia memindai **working tree**, bukan riwayat → secret yang sudah dihapus dari HEAD terlewat. Pakai **gitleaks**
  untuk memindai seluruh riwayat.
- Pasang berlapis: **pre-commit** (cepat, lokal, bisa dilewati `--no-verify`) + **CI** (wajib, jaring pengaman).

## 3. Rotasi lebih dulu

```text
Secret ter-commit/ter-ekspos  →  1) ROTASI/cabut (perbaikan sejati)
                                  2) cek penyalahgunaan (audit log)
                                  3) bersihkan riwayat (filter-repo/BFG) — sekunder
```

Membersihkan riwayat mengubah semua hash dan tak menjangkau fork/clone/cache. Rotasi menutup risiko nyata; itu yang
utama. Rotasi berkala/short-lived memperpendek jendela penyalahgunaan bila bocor diam-diam.

## 4. Injeksi saat runtime, bukan di image (hasil lab)

```dockerfile
# BENAR saat build (BuildKit): secret TIDAK ter-bake ke layer (terbukti CLEAN)
# syntax=docker/dockerfile:1
RUN --mount=type=secret,id=tok  pip install --index-url https://$(cat /run/secrets/tok)@repo ...
# build: docker build --secret id=tok,src=secret.txt -t app .
```

```text
# BENAR saat runtime: pola *_FILE
DB_PASSWORD_FILE=/run/secrets/db_password     # mount via Docker/Compose secret (tmpfs)
# docker inspect .Config.Env → hanya PATH-nya, bukan nilai  ✅
```

| Cara | Aman? | Alasan |
|---|---|---|
| `ENV SECRET=...` / `-e` | ❌ | Bocor di inspect, `/proc`, registry |
| `ARG` / `--build-arg` | ❌ | Tersimpan di `docker history`/metadata |
| `COPY secret` lalu `rm` | ❌ | Tetap di layer sebelumnya |
| `RUN --mount=type=secret` | ✅ | Tidak ter-bake ke layer mana pun |
| `*_FILE` + `/run/secrets` | ✅ | Nilai diinjeksi runtime, bukan di image |
| Aplikasi baca file/manager langsung | ✅✅ | Tidak nyangkut di env sama sekali |

> [!TIP]
> Pola `*_FILE` masih meng-`export` ke env, jadi nilainya tetap muncul di `/proc/1/environ` bagi yang bisa `exec`.
> Paling aman: aplikasi membaca file secret / secret manager langsung ke memori tanpa menaruhnya di env.

## 5. Penyimpanan

- **Secret manager** (Vault, AWS/GCP Secrets Manager): terenkripsi, akses berbasis identitas, ter-audit, mudah
  dirotasi.
- **CI secret store** untuk token pipeline (masked), bukan hardcode di `.yml`.
- `.env` hanya di server, **selalu** di `.gitignore` + `.dockerignore`.
- Jangan pernah mengirim `.env`/secret lewat chat, tiket, atau email — begitu tersebar, tak terlacak.

## 6. Ringkasan

| Prinsip | Aksi |
|---|---|
| Jangan simpan di git/image | `.gitignore`/`.dockerignore`, `*_FILE`, secret mount |
| Deteksi sebelum masuk | pre-commit + gitleaks/detect-secrets di CI |
| Bocor = permanen | rotasi dulu, baru bersihkan riwayat |
| Minimalkan umur | rotasi berkala / short-lived token |
| Pusatkan & audit | secret manager, bukan file tersebar |

> [!NOTE]
> Lanjut ke modul 05 (Image & supply chain) — karena image yang kamu tarik dari registry juga bisa membawa
> kerentanan dan secret orang lain, dan harus di-scan sebelum dipercaya.
