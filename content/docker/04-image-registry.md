---
format: 1
id: docker-image-registry
course:
  slug: docker
  title: Docker
  description: Container dari nol sampai production — CLI, image, storage, network, Compose, keamanan, dan operasional.
title: Image & Registry
summary: Nama image, tag vs digest, push/pull ke registry, API registry, image multi-arsitektur, transfer offline, scanning CVE dengan Trivy, SBOM, dan strategi tag untuk rilis.
order: 4
version: 1
status: verified
updated: '2026-11-23'
tested_on:
  - Docker Desktop (Windows) — Docker Engine 29.4.1, containerd image store, BuildKit 0.29
  - Registry lokal registry:2 (127.0.0.1:5005), Docker Hub (header rate limit), Trivy (aquasec/trivy:latest)
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: naming
    text: Membaca nama image lengkap (registry, namespace, repo, tag, digest)
  - id: local-images
    text: Mengelola image lokal (ls, tag, rmi, inspect, dangling)
  - id: registry
    text: Login, push, dan pull ke registry; memahami API registry
  - id: tag-digest
    text: Membedakan tag yang bisa berpindah dengan digest yang tetap
  - id: multiarch
    text: Memahami image index multi-arsitektur dan attestation
  - id: transfer
    text: Memindahkan image tanpa registry (save/load) dan bedanya dengan export/import
  - id: scanning
    text: Memindai CVE, membuat SBOM, dan memasang gerbang di CI
  - id: release
    text: Merancang strategi tag dan promosi image yang bisa dilacak
sources:
  - title: Docker Docs — Image naming (docker image tag)
    url: https://docs.docker.com/reference/cli/docker/image/tag/
  - title: docker image ls / inspect / rm / save / load
    url: https://docs.docker.com/reference/cli/docker/image/
  - title: docker login (--password-stdin, credential helpers)
    url: https://docs.docker.com/reference/cli/docker/login/
  - title: Docker Docs — Pull by digest
    url: https://docs.docker.com/reference/cli/docker/image/pull/#pull-an-image-by-digest-immutable-identifier
  - title: Docker Hub — Usage and pull rate limits
    url: https://docs.docker.com/docker-hub/usage/
  - title: Distribution (registry) — HTTP API V2
    url: https://distribution.github.io/distribution/spec/api/
  - title: Distribution — Deleting an image (storage.delete)
    url: https://distribution.github.io/distribution/about/configuration/#delete
  - title: docker buildx imagetools
    url: https://docs.docker.com/reference/cli/docker/buildx/imagetools/
  - title: Docker Docs — Build attestations (SBOM & provenance)
    url: https://docs.docker.com/build/metadata/attestations/
  - title: Docker Docs — containerd image store
    url: https://docs.docker.com/desktop/features/containerd/
  - title: GitHub Docs — Working with the Container registry (ghcr.io)
    url: https://docs.github.com/en/packages/working-with-a-github-packages-registry/working-with-the-container-registry
  - title: Trivy — Container image scanning
    url: https://trivy.dev/latest/docs/target/container_image/
  - title: OCI Image Spec — Image Index
    url: https://github.com/opencontainers/image-spec/blob/main/image-index.md
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: naming
    prompt: Nama image tanpa hostname (mis. `nginx` atau `myorg/api`) diambil dari registry apa secara default?
    answers: ['docker.io', 'docker hub', 'registry-1.docker.io']
    tests:
      fail: ['ghcr.io', 'quay.io', 'localhost', 'gcr.io']
    explanation: 'Bagian pertama dianggap hostname registry bila mengandung `.` atau `:`, atau bernilai `localhost`. Selain itu, Docker memakai Docker Hub. Di lab, `docker buildx imagetools inspect alpine:3.20` → `Name: docker.io/library/alpine:3.20`.'
    source: https://docs.docker.com/reference/cli/docker/image/tag/

  - id: a02
    tier: A
    type: text
    objective: naming
    prompt: Tulis nama **lengkap** (registry/namespace/repo:tag) dari image `nginx:1.27-alpine`.
    answers: ['docker.io/library/nginx:1.27-alpine']
    tests:
      fail: ['docker.io/nginx:1.27-alpine', 'library/nginx:1.27-alpine', 'nginx.org/nginx:1.27-alpine', 'docker.io/nginx/nginx:1.27-alpine']
    explanation: 'Image resmi Docker Hub berada di namespace `library`. Format umum: `[registry[:port]/][namespace/]repo[:tag][@sha256:digest]`, mis. `ghcr.io/acme/api:1.4.2` atau `127.0.0.1:5005/demo/app:1.0` (lab). Tanpa tag = `:latest`.'
    source: https://docs.docker.com/reference/cli/docker/image/tag/

  - id: a03
    tier: A
    type: command
    objective: local-images
    prompt: Tampilkan daftar image lokal **beserta digest**-nya.
    answers: ['docker images --digests']
    patterns: ['^docker (images|image ls|image list) --digests$']
    tests:
      pass: ['docker image ls --digests']
      fail: ['docker images', 'docker image inspect', 'docker ps --digests']
    explanation: 'Di lab: `alpine:3.20 sha256:d9e853e8... d9e853e87e55`. Opsi lain: `--filter reference=''myapp*''`, `--filter dangling=true`, dan `--tree` (eksperimental, untuk image multi-platform).'
    source: https://docs.docker.com/reference/cli/docker/image/ls/

  - id: a04
    tier: A
    type: command
    objective: local-images
    prompt: Beri nama tambahan `ghcr.io/acme/myapp:1.4.2` pada image lokal `myapp:1.4.2` supaya bisa di-push ke GHCR.
    answers: ['docker tag myapp:1.4.2 ghcr.io/acme/myapp:1.4.2']
    patterns: ['^docker (image )?tag myapp:1\.4\.2 ghcr\.io/acme/myapp:1\.4\.2$']
    tests:
      pass: ['docker image tag myapp:1.4.2 ghcr.io/acme/myapp:1.4.2']
      fail: ['docker tag ghcr.io/acme/myapp:1.4.2 myapp:1.4.2', 'docker rename myapp:1.4.2 ghcr.io/acme/myapp:1.4.2', 'docker push myapp:1.4.2 ghcr.io/acme/myapp:1.4.2']
    explanation: 'Format `docker tag SUMBER TARGET`. Tag hanyalah **alias**: di lab, `dj-lab/myalpine:1` dan `alpine:3.20` menunjuk ID yang sama, dan `docker rmi dj-lab/myalpine:1` hanya mencetak `Untagged` tanpa menghapus image. Registry tujuan ditentukan dari nama.'
    source: https://docs.docker.com/reference/cli/docker/image/tag/

  - id: a05
    tier: A
    type: command
    objective: registry
    prompt: Unggah image `ghcr.io/acme/myapp:1.4.2` ke registry-nya.
    answers: ['docker push ghcr.io/acme/myapp:1.4.2']
    patterns: ['^docker (image )?push ghcr\.io/acme/myapp:1\.4\.2$']
    tests:
      pass: ['docker image push ghcr.io/acme/myapp:1.4.2']
      fail: ['docker upload ghcr.io/acme/myapp:1.4.2', 'docker push myapp:1.4.2', 'docker commit ghcr.io/acme/myapp:1.4.2']
    explanation: 'Hanya layer yang belum ada di registry yang dikirim. Di lab, layer alpine bersama di-skip, dan setelah push digest-nya muncul di `RepoDigests`. `docker push myapp:1.4.2` akan mencoba `docker.io/library/myapp` dan ditolak.'
    source: https://docs.docker.com/reference/cli/docker/image/push/

  - id: a06
    tier: A
    type: command
    objective: registry
    prompt: Login ke `ghcr.io` sebagai `acme-bot` dengan token di variabel `GHCR_TOKEN`, **tanpa** menulis token sebagai argumen perintah.
    answers: ['echo "$GHCR_TOKEN" | docker login ghcr.io -u acme-bot --password-stdin']
    patterns:
      - '^(echo|printf %s) \$\{?GHCR_TOKEN\}? ?\| ?docker login ghcr\.io (-u|--username)[= ]?acme-bot --password-stdin$'
      - '^(echo|printf %s) \$\{?GHCR_TOKEN\}? ?\| ?docker login ghcr\.io --password-stdin (-u|--username)[= ]?acme-bot$'
      - '^(echo|printf %s) \$\{?GHCR_TOKEN\}? ?\| ?docker login (-u|--username)[= ]?acme-bot --password-stdin ghcr\.io$'
    tests:
      pass: ['printf %s "$GHCR_TOKEN" | docker login ghcr.io --username acme-bot --password-stdin']
      fail: ['docker login ghcr.io -u acme-bot -p $GHCR_TOKEN', 'docker login -u acme-bot ghcr.io', 'docker login ghcr.io --password-stdin']
    explanation: '`-p TOKEN` membuat token terlihat di `ps`, history shell, dan log CI (dan Docker sendiri memperingatkannya). Untuk GHCR, pakai Personal Access Token dengan scope `write:packages`, atau `GITHUB_TOKEN` di Actions. Di Docker Desktop Windows, kredensial disimpan lewat `"credsStore": "desktop"` (lab), bukan plaintext di `config.json`.'
    source: https://docs.docker.com/reference/cli/docker/login/

  - id: a07
    tier: A
    type: command
    objective: tag-digest
    prompt: 'Pull `alpine` berdasarkan **digest** `sha256:d9e853e87e55526f6b2917df91a2115c36dd7c696a35be12163d44e6e2a4b6bc` (bukan tag).'
    answers: ['docker pull alpine@sha256:d9e853e87e55526f6b2917df91a2115c36dd7c696a35be12163d44e6e2a4b6bc']
    patterns: ['^docker (image )?pull alpine(:3\.20)?@sha256:d9e853e87e55526f6b2917df91a2115c36dd7c696a35be12163d44e6e2a4b6bc$']
    tests:
      pass: ['docker pull alpine:3.20@sha256:d9e853e87e55526f6b2917df91a2115c36dd7c696a35be12163d44e6e2a4b6bc']
      fail: ['docker pull alpine:sha256:d9e853e87e55526f6b2917df91a2115c36dd7c696a35be12163d44e6e2a4b6bc', 'docker pull sha256:d9e853e87e55526f6b2917df91a2115c36dd7c696a35be12163d44e6e2a4b6bc', 'docker pull alpine#sha256:d9e853e87e55526f6b2917df91a2115c36dd7c696a35be12163d44e6e2a4b6bc']
    explanation: 'Digest = hash isi manifest/index, jadi tidak bisa berubah. Kalau isinya berbeda, digest-nya pun berbeda. `nama:tag@sha256:...` sah, dan tag-nya hanya untuk dibaca manusia: yang dipakai adalah digest.'
    source: https://docs.docker.com/reference/cli/docker/image/pull/#pull-an-image-by-digest-immutable-identifier

  - id: a08
    tier: A
    type: command
    objective: local-images
    prompt: Hapus **image** lokal `myapp:1.0`.
    answers: ['docker rmi myapp:1.0']
    patterns: ['^docker (rmi|image rm|image remove) myapp:1\.0$']
    tests:
      pass: ['docker image rm myapp:1.0']
      fail: ['docker rm myapp:1.0', 'docker image prune myapp:1.0', 'docker delete myapp:1.0']
    explanation: '`rmi` menghapus tag. Datanya baru dihapus bila tidak ada tag lain dan tidak ada container yang memakai image itu. `docker rm` untuk container. `docker image prune` menghapus **semua** image dangling di mesin, bukan satu image tertentu.'
    source: https://docs.docker.com/reference/cli/docker/image/rm/

  - id: a09
    tier: A
    type: command
    objective: transfer
    prompt: Simpan image `myapp:1.0` (lengkap dengan layer dan metadata) ke file `myapp.tar`.
    answers: ['docker save -o myapp.tar myapp:1.0']
    patterns: ['^docker (image )?save ((-o|--output)[= ]?myapp\.tar myapp:1\.0|myapp:1\.0 (-o|--output)[= ]?myapp\.tar|myapp:1\.0 >myapp\.tar)$']
    tests:
      pass: ['docker save myapp:1.0 > myapp.tar', 'docker image save --output myapp.tar myapp:1.0']
      fail: ['docker export -o myapp.tar myapp:1.0', 'docker save myapp:1.0', 'docker cp myapp:1.0 myapp.tar']
    explanation: 'Di lab, isi tar berformat OCI (`blobs/sha256/...`, `index.json`). Setelah `load`, image ID-nya identik. gzip hampir tidak berguna di sini (3.646.976 → 3.624.989 byte), karena layer di containerd store sudah terkompresi.'
    source: https://docs.docker.com/reference/cli/docker/image/save/

  - id: a10
    tier: A
    type: command
    objective: transfer
    prompt: Muat kembali image dari file `myapp.tar` hasil `docker save`.
    answers: ['docker load -i myapp.tar']
    patterns: ['^docker (image )?load ((-i|--input)[= ]?myapp\.tar|<myapp\.tar)$']
    tests:
      pass: ['docker load < myapp.tar', 'docker image load --input myapp.tar']
      fail: ['docker import myapp.tar', 'docker run myapp.tar', 'docker pull myapp.tar']
    explanation: '`load` memulihkan tag dan semua metadata (lab: `Loaded image: 127.0.0.1:5005/demo/app:1.0`, dan container mencetak `version 2`). `load` juga menerima file `.tar.gz` langsung. `docker import` adalah perintah yang berbeda (soal tier B).'
    source: https://docs.docker.com/reference/cli/docker/image/load/

  - id: a11
    tier: A
    type: command
    objective: local-images
    prompt: Tampilkan **arsitektur CPU** image lokal `myapp:1.0` dengan template Go.
    answers: ['docker image inspect -f ''{{.Architecture}}'' myapp:1.0']
    patterns: ['^docker (image )?inspect (-f|--format)[= ]?\{\{ ?\.Architecture ?\}\} myapp:1\.0$']
    tests:
      pass: ['docker inspect --format "{{.Architecture}}" myapp:1.0']
      fail: ['docker inspect myapp:1.0 arch', 'uname -m', 'docker history myapp:1.0']
    explanation: 'Di lab: `Arch=amd64 OS=linux`. Image `arm64` di server x86 tanpa emulasi gagal dengan `exec format error`, sebuah gejala klasik saat image dibangun di Mac M-series lalu dijalankan di server x86. Field lain yang berguna: `.Created`, `.RepoDigests`, `.Config.Env`.'
    source: https://docs.docker.com/reference/cli/docker/image/inspect/

  - id: a12
    tier: A
    type: command
    objective: multiarch
    prompt: Tampilkan daftar platform (manifest per arsitektur) di dalam image index `alpine:3.20` langsung dari registry.
    answers: ['docker buildx imagetools inspect alpine:3.20']
    patterns: ['^docker buildx imagetools inspect( --raw)? alpine:3\.20$', '^docker manifest inspect alpine:3\.20$']
    tests:
      pass: ['docker manifest inspect alpine:3.20']
      fail: ['docker image inspect alpine:3.20', 'docker pull --all alpine:3.20', 'docker images alpine:3.20']
    explanation: 'Di lab: `MediaType: application/vnd.oci.image.index.v1+json` berisi manifest `linux/amd64`, `linux/arm/v6`, dst., masing-masing dengan digest sendiri. `docker pull` memilih manifest yang cocok dengan arsitektur mesinmu secara otomatis.'
    source: https://docs.docker.com/reference/cli/docker/buildx/imagetools/inspect/

  - id: a13
    tier: A
    type: text
    objective: registry
    prompt: Hostname registry container milik GitHub (GitHub Packages)?
    answers: ['ghcr.io']
    tests:
      fail: ['github.io', 'docker.github.com', 'registry.github.com', 'pkg.github.com']
    explanation: 'Format `ghcr.io/OWNER/IMAGE:TAG`. Registry populer lain: Docker Hub (`docker.io`), GitLab (`registry.gitlab.com`), AWS ECR (`<akun>.dkr.ecr.<region>.amazonaws.com`), Google Artifact Registry (`<region>-docker.pkg.dev`), dan Quay (`quay.io`).'
    source: https://docs.github.com/en/packages/working-with-a-github-packages-registry/working-with-the-container-registry

  - id: a14
    tier: A
    type: command
    objective: registry
    prompt: Jalankan registry lokal `registry:2` di background bernama `registry`, port 5000 di-publish **hanya ke localhost**.
    answers: ['docker run -d --name registry -p 127.0.0.1:5000:5000 registry:2']
    patterns: ['^docker (container )?run (?=.*(-d|--detach)( |$))(?=.*--name[= ]registry( |$))(?=.*(-p|--publish)[= ]?127\.0\.0\.1:5000:5000( |$))((-d|--detach|--name[= ]registry|(-p|--publish)[= ]?127\.0\.0\.1:5000:5000|--restart[= ]\S+) )+registry:2$']
    tests:
      pass: ['docker run -p 127.0.0.1:5000:5000 --name registry -d registry:2']
      fail: ['docker run -d -p 5000 registry:2', 'docker registry start', 'docker run -d registry:2 -p 127.0.0.1:5000:5000']
    explanation: 'Registry ini (proyek CNCF Distribution) tanpa autentikasi dan tanpa TLS, sehingga hanya aman di localhost. Docker mengizinkan HTTP polos untuk `localhost`/`127.0.0.1`. Di lab: `GET /v2/` → `{}`. Untuk dipakai dari host lain, butuh TLS + auth, atau pakai registry terkelola.'
    source: https://distribution.github.io/distribution/

  - id: a15
    tier: A
    type: command
    objective: scanning
    prompt: Pindai image `nginx:1.27-alpine` dengan Trivy, tampilkan hanya kerentanan severity **HIGH dan CRITICAL**.
    answers: ['trivy image --severity HIGH,CRITICAL nginx:1.27-alpine']
    patterns:
      - '^trivy image (?=.*--severity[= ](HIGH,CRITICAL|CRITICAL,HIGH)( |$))((--severity[= ](HIGH,CRITICAL|CRITICAL,HIGH)|--quiet|-q) )+nginx:1\.27-alpine$'
      - '^trivy image nginx:1\.27-alpine --severity[= ](HIGH,CRITICAL|CRITICAL,HIGH)$'
      - '^docker run --rm( -v \S+)* aquasec/trivy(:\S+)? image (--severity[= ](HIGH,CRITICAL|CRITICAL,HIGH) )+nginx:1\.27-alpine$'
    tests:
      pass: ['trivy image nginx:1.27-alpine --severity CRITICAL,HIGH', 'docker run --rm aquasec/trivy:latest image --severity HIGH,CRITICAL nginx:1.27-alpine']
      fail: ['trivy nginx:1.27-alpine', 'docker scan nginx:1.27-alpine', 'trivy fs nginx:1.27-alpine']
    explanation: 'Di lab: `Total: 70 (HIGH: 67, CRITICAL: 3)`, termasuk `libcrypto3 CVE-2026-31789 CRITICAL` dengan versi perbaikan `3.3.7-r0`. Trivy bisa dijalankan sebagai container (lab: `docker run aquasec/trivy:latest image ...` dengan volume cache untuk database CVE). `docker scan` lama sudah dihapus. Alternatif lain: Docker Scout dan Grype.'
    source: https://trivy.dev/latest/docs/target/container_image/

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: tag-digest
    prompt: Kamu push `demo/app:1.0`, lalu build lain di-push ke tag `demo/app:1.0` yang **sama**. Apakah digest yang ditunjuk tag itu berubah? (ya/tidak)
    answers: ['ya', 'yes']
    tests:
      fail: ['tidak', 'no']
    explanation: 'Di lab: v1 → `sha256:dc55863e...`, v2 dengan tag yang sama → `sha256:c33d0a60...`. Tag adalah pointer yang bisa dipindah siapa pun yang punya akses push. Server yang menarik `:1.0` hari ini bisa mendapat isi berbeda dari kemarin. Beberapa registry mendukung *immutable tags* (ECR, Harbor, GHCR via kebijakan).'
    source: https://docs.docker.com/reference/cli/docker/image/pull/#pull-an-image-by-digest-immutable-identifier

  - id: b02
    tier: B
    type: text
    objective: tag-digest
    prompt: Setelah tag `1.0` dipindah ke build baru, apakah build **lama** masih bisa di-pull lewat digest lamanya? (ya/tidak)
    answers: ['ya', 'yes', 'bisa']
    tests:
      fail: ['tidak', 'no', 'tidak bisa']
    explanation: 'Di lab, `docker run 127.0.0.1:5005/demo/app@sha256:dc55863e...` → `version 1`, sedangkan `:1.0` → `version 2`. Manifest lama tetap tersimpan sampai dihapus dan di-garbage-collect. Inilah dasar deploy berdasarkan digest: rollback pasti ke artefak yang sama persis.'
    source: https://distribution.github.io/distribution/spec/api/

  - id: b03
    tier: B
    type: text
    objective: registry
    prompt: Header HTTP apa di respons `GET/HEAD /v2/<name>/manifests/<tag>` yang berisi digest manifest yang ditunjuk tag?
    answers: ['Docker-Content-Digest']
    tests:
      fail: ['ETag', 'Content-Type', 'X-Digest', 'Content-Digest']
    explanation: 'Di lab: `Docker-Content-Digest: sha256:c33d0a60...` dan `Content-Type: application/vnd.oci.image.index.v1+json`. Header `Accept` menentukan format yang diminta (OCI index/manifest atau Docker v2). Dengan cara ini, digest sebuah tag bisa diketahui tanpa pull image.'
    source: https://distribution.github.io/distribution/spec/api/

  - id: b04
    tier: B
    type: command
    objective: registry
    prompt: Lewat API registry v2, tampilkan daftar repository di registry lokal `http://127.0.0.1:5005`.
    answers: ['curl http://127.0.0.1:5005/v2/_catalog']
    patterns: ['^curl (-s |-sS |-fsS )?http://127\.0\.0\.1:5005/v2/_catalog$']
    tests:
      pass: ['curl -s http://127.0.0.1:5005/v2/_catalog']
      fail: ['curl http://127.0.0.1:5005/_catalog', 'docker search 127.0.0.1:5005', 'curl http://127.0.0.1:5005/v2/']
    explanation: 'Di lab: `{"repositories":["demo/app","mirror/alpine"]}`. Tag sebuah repo: `/v2/demo/app/tags/list` → `{"name":"demo/app","tags":["1.0"]}`. Registry publik biasanya membatasi `_catalog`, dan Docker Hub tidak menyediakannya.'
    source: https://distribution.github.io/distribution/spec/api/

  - id: b05
    tier: B
    type: text
    objective: transfer
    prompt: Dibanding `save`/`load`, apa yang **hilang** bila image dipindah dengan `docker export` (dari container) lalu `docker import`?
    answers: ['metadata (CMD, ENV, label) dan riwayat layer']
    patterns: ['(metadata|cmd|entrypoint|\benv\b|label|layer|history|riwayat|config)']
    tests:
      pass: ['CMD dan label', 'layer jadi satu, metadata hilang']
      fail: ['tidak ada', 'hanya tag', 'data volume saja']
    explanation: 'Di lab, image hasil import: `Cmd=null Labels=null layers=1`, sedangkan aslinya `Cmd=["cat","/version.txt"] Labels={"dj-lab":"1"} layers=2`. Menjalankannya → `no command specified`. `export` hanya mengambil filesystem container (termasuk perubahan di writable layer). Untuk memindahkan image, pakai `save`/`load`.'
    source: https://docs.docker.com/reference/cli/docker/container/export/

  - id: b06
    tier: B
    type: text
    objective: registry
    prompt: 'Pull anonim ke Docker Hub membalas header `ratelimit-limit: 100;w=3600`. Apa artinya?'
    answers: ['100 pull per jam']
    patterns: ['^100( ?x| pull| request| kali| permintaan)?( per| / ?|/| setiap) ?(1 )?(jam|hour|3600 ?(detik|s|seconds)?)']
    tests:
      pass: ['100 pull per 3600 detik', '100 request per hour']
      fail: ['100 per hari', '3600 pull per 100 detik', 'unlimited', '100 per menit']
    explanation: 'Diukur di lab dengan endpoint resmi `ratelimitpreview/test`: `ratelimit-limit: 100;w=3600`, `ratelimit-remaining: 100;w=3600`, dan `docker-ratelimit-source` berisi IP publikmu. Limit anonim dihitung **per IP**, sehingga runner CI di balik NAT yang sama berbagi jatah. Login, mirror, atau registry sendiri menghindari error `toomanyrequests` di tengah deploy. Angka ini bisa berubah sesuai kebijakan Docker.'
    source: https://docs.docker.com/docker-hub/usage/

  - id: b07
    tier: B
    type: text
    objective: scanning
    prompt: 'Trivy menemukan 70 CVE HIGH/CRITICAL di `nginx:1.27-alpine`, tetapi hanya 20 di `nginx:1.25-alpine`, dan 1 di `nginx:stable-alpine`. Kenapa tag 1.27 bisa lebih buruk?'
    answers: ['tag 1.27 tidak lagi di-rebuild karena sudah EOL']
    patterns: ['(tidak (lagi )?(di-?)?rebuild|\beol\b|end of life|tidak (lagi )?(dipelihara|di-?maintain|diperbarui|di-?update|didukung)|berhenti (di-?)?(update|rebuild|dipelihara|diperbarui)|frozen|beku|usang|lama tidak (di-?)?(update|rebuild))']
    tests:
      pass: ['versi 1.27 sudah EOL', 'image sudah lama tidak di-rebuild']
      fail: ['1.27 kodenya lebih buruk', 'trivy salah', 'alpine baru lebih rentan']
    explanation: 'Di lab: `nginx:1.27-alpine` terakhir dibuat **2025-04-16** (Alpine 3.21.3), `nginx:stable-alpine` dibuat 2026-11-18 (nginx 1.30.7, Alpine 3.24.4). Begitu sebuah versi tidak dipelihara, tag-nya tidak di-rebuild lagi, sedangkan database CVE terus bertambah. Pin versi tetap benar, tetapi pin ke versi yang **masih dipelihara**, dan rebuild/upgrade secara terjadwal. Catatan: contoh `nginx:1.27-alpine` di modul sebelumnya mewakili pola pin, bukan versi yang disarankan hari ini.'
    source: https://trivy.dev/latest/docs/target/container_image/

  - id: b08
    tier: B
    type: command
    objective: scanning
    prompt: 'Jadikan Trivy **gerbang CI**: gagal (exit 1) bila `myapp:1.0` punya CVE **CRITICAL** yang **sudah ada perbaikannya**.'
    answers: ['trivy image --severity CRITICAL --ignore-unfixed --exit-code 1 myapp:1.0']
    patterns: ['^trivy image (?=.*--severity[= ]CRITICAL( |$))(?=.*--ignore-unfixed( |$))(?=.*--exit-code[= ]1( |$))((--severity[= ]CRITICAL|--ignore-unfixed|--exit-code[= ]1|--quiet|-q) )+myapp:1\.0$']
    tests:
      pass: ['trivy image --exit-code 1 --ignore-unfixed --severity CRITICAL myapp:1.0']
      fail: ['trivy image myapp:1.0', 'trivy image --exit-code 0 --severity CRITICAL --ignore-unfixed myapp:1.0', 'trivy image --severity CRITICAL myapp:1.0']
    explanation: 'Default Trivy selalu exit 0 walau ada temuan. Di lab, perintah ini pada `nginx:1.27-alpine` → `exit=1`. `--ignore-unfixed` menyaring CVE yang belum punya patch (tidak bisa kamu perbaiki dengan upgrade), supaya gerbangnya bisa ditindaklanjuti. Pengecualian yang disengaja dicatat di `.trivyignore` beserta alasannya.'
    source: https://trivy.dev/latest/docs/configuration/filtering/

  - id: b09
    tier: B
    type: text
    objective: multiarch
    prompt: '`docker buildx imagetools inspect alpine:3.20` menampilkan beberapa entri dengan `Platform: unknown/unknown`. Apa itu?'
    answers: ['attestation manifest (SBOM/provenance)']
    patterns: ['(attestation|provenance|sbom)']
    tests:
      pass: ['attestation', 'provenance dan sbom']
      fail: ['arsitektur rusak', 'windows', 'image corrupt', 'manifest kosong']
    explanation: 'Index image modern tidak hanya berisi manifest per platform, tetapi juga **attestation** (SBOM dan provenance SLSA) yang ditandai `unknown/unknown` dan menunjuk ke manifest platform yang dijelaskannya. BuildKit membuat provenance secara default saat push. Tool supply chain (Scout, cosign, policy engine) membacanya.'
    source: https://docs.docker.com/build/metadata/attestations/

  - id: b10
    tier: B
    type: command
    objective: multiarch
    prompt: Salin `alpine:3.20` beserta **semua platform**-nya ke `127.0.0.1:5005/mirror/alpine:3.20` langsung antar registry, tanpa pull ke lokal.
    answers: ['docker buildx imagetools create -t 127.0.0.1:5005/mirror/alpine:3.20 alpine:3.20']
    patterns: ['^docker buildx imagetools create (-t|--tag)[= ]?127\.0\.0\.1:5005/mirror/alpine:3\.20 alpine:3\.20$']
    tests:
      pass: ['docker buildx imagetools create --tag 127.0.0.1:5005/mirror/alpine:3.20 alpine:3.20']
      fail: ['docker tag alpine:3.20 127.0.0.1:5005/mirror/alpine:3.20', 'docker cp alpine:3.20 127.0.0.1:5005/mirror/alpine:3.20', 'docker pull 127.0.0.1:5005/mirror/alpine:3.20']
    explanation: 'Di lab: `pushing sha256:d9e853e8... to 127.0.0.1:5005/mirror/alpine:3.20`, dengan digest index yang sama dengan sumbernya. `docker tag` + `push` hanya membawa platform yang ada di lokal (biasanya satu). Cocok untuk mirror image dasar ke registry internal (menghindari rate limit Docker Hub). Tool lain: `crane copy`, `skopeo copy`.'
    source: https://docs.docker.com/reference/cli/docker/buildx/imagetools/create/

  - id: b11
    tier: B
    type: text
    objective: local-images
    prompt: Di Docker 29 dengan **containerd image store**, image ID `alpine:3.20` sama dengan nilai apa?
    answers: ['digest index image (repo digest)']
    patterns: ['(digest|repodigest|repo digest)']
    tests:
      pass: ['digest-nya', 'sama dengan RepoDigest']
      fail: ['hash config', 'layer pertama', 'nama tag', 'nomor acak']
    explanation: 'Di lab: `ID=sha256:d9e853e87e55...` dan `RepoDigests=["alpine@sha256:d9e853e87e55..."]`, persis sama. Di storage klasik (overlay2 tanpa containerd store), image ID adalah hash **config** dan berbeda dari repo digest, sehingga banyak tutorial lama membedakan keduanya. Perilaku daftar image juga berbeda: di lab, rebuild tag yang sama tidak meninggalkan image `<none>` dangling.'
    source: https://docs.docker.com/desktop/features/containerd/

  - id: b12
    tier: B
    type: text
    objective: registry
    prompt: Di Docker Desktop Windows, kredensial hasil `docker login` disimpan lewat credential helper apa (nilai `credsStore` di `~/.docker/config.json`)?
    answers: ['desktop']
    tests:
      fail: ['config.json', 'plaintext', 'pass', 'osxkeychain', 'wincred']
    explanation: 'Di lab: `"credsStore": "desktop"`, yaitu helper Docker Desktop yang menitipkan ke Windows Credential Manager. Di server Linux tanpa helper, `docker login` menyimpan token **base64** (bukan enkripsi) di `config.json`. Lindungi file itu (mode 600) atau pakai helper (`pass`, `secretservice`), dan logout di runner CI bersama.'
    source: https://docs.docker.com/reference/cli/docker/login/#credential-stores

  - id: b13
    tier: B
    type: command
    objective: tag-digest
    prompt: Jalankan `myapp:1.0` sambil **selalu** memeriksa registry lebih dulu (pull bila ada versi baru di tag itu).
    answers: ['docker run --pull always myapp:1.0']
    patterns: ['^docker (container )?run (--rm |-d )*--pull[= ]always (--rm |-d )*myapp:1\.0$']
    tests:
      pass: ['docker run --rm --pull=always myapp:1.0']
      fail: ['docker run --always-pull myapp:1.0', 'docker pull --run myapp:1.0', 'docker run --pull missing myapp:1.0']
    explanation: 'Nilai `--pull`: `missing` (default; pull hanya bila belum ada), `always`, dan `never`. Di lab, `--pull always` → `Status: Image is up to date`. Ini menyelesaikan masalah "tag sudah dipindah tetapi server masih menjalankan yang lama", tetapi juga membuat start container bergantung pada registry. Deploy berdasarkan digest lebih pasti.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#pull

  - id: b14
    tier: B
    type: text
    objective: registry
    prompt: 'Registry `registry:2` dengan konfigurasi default: `DELETE /v2/<name>/manifests/<digest>` membalas kode HTTP berapa?'
    answers: ['405']
    tests:
      fail: ['200', '202', '404', '401']
    explanation: 'Di lab: `DELETE manifest -> 405` (Method Not Allowed). Penghapusan harus diaktifkan (`storage.delete.enabled: true` / `REGISTRY_STORAGE_DELETE_ENABLED=true`), lalu ruang disk baru kembali setelah garbage collection (`registry garbage-collect`). Registry terkelola punya *retention policy* untuk membuang tag lama secara otomatis.'
    source: https://distribution.github.io/distribution/about/configuration/#delete

  - id: b15
    tier: B
    type: command
    objective: scanning
    prompt: Buat **SBOM** format CycloneDX untuk `myapp:1.0` dengan Trivy, simpan ke `sbom.json`.
    answers: ['trivy image --format cyclonedx -o sbom.json myapp:1.0']
    patterns: ['^trivy image (?=.*--format[= ](cyclonedx|spdx-json)( |$))(?=.*(-o|--output)[= ]?sbom\.json( |$))((--format[= ](cyclonedx|spdx-json)|(-o|--output)[= ]?sbom\.json|--quiet|-q) )+myapp:1\.0$']
    tests:
      pass: ['trivy image -o sbom.json --format cyclonedx myapp:1.0', 'trivy image --format spdx-json --output sbom.json myapp:1.0']
      fail: ['trivy image myapp:1.0 > sbom.json', 'docker sbom myapp:1.0', 'trivy sbom myapp:1.0']
    explanation: 'SBOM (Software Bill of Materials) adalah daftar semua paket di image. Di lab, output diawali `"bomFormat": "CycloneDX"`, `"specVersion": "1.7"`. Bila ada CVE baru, SBOM yang tersimpan bisa dipindai ulang (`trivy sbom sbom.json`) tanpa pull image. Alternatifnya, BuildKit bisa menempelkan SBOM sebagai attestation (`docker buildx build --sbom=true`).'
    source: https://trivy.dev/latest/docs/supply-chain/sbom/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: release
    prompt: |
      Rancang alur rilis image `ghcr.io/acme/api` dari CI (satu perintah per baris): login dengan aman, beri tag yang
      bisa dilacak ke commit **dan** versi rilis, push, catat digest yang dihasilkan, lalu deploy ke staging dan production
      memakai artefak yang **persis sama**, dengan rollback yang pasti.
    checklist:
      - id: login
        label: docker login dengan --password-stdin
        required: true
        patterns: ['docker\s+login\s+.*--password-stdin']
      - id: tag-sha
        label: Tag commit SHA
        required: true
        patterns: ['ghcr\.io/acme/api:\$\{?\{?\s*(github\.sha|github_sha|git_sha|ci_commit_sha|ci_commit_short_sha|sha|commit\w*)\s*\}?\}?', ':\$\(git\s+rev-parse']
      - id: tag-semver
        label: Tag versi rilis (semver)
        patterns: ['ghcr\.io/acme/api:v?\d+\.\d+\.\d+']
      - id: push
        label: Push ke registry
        required: true
        patterns: ['docker\s+(image\s+)?push\s+ghcr\.io/acme/api', '--push\b']
      - id: digest
        label: Mengambil digest hasil push (inspect RepoDigests / imagetools / metadata)
        patterns: ['repodigests', 'imagetools\s+inspect', 'docker-content-digest', 'metadata-file', '--format.*digest']
      - id: deploy-digest
        label: Deploy staging/production memakai @sha256:digest
        patterns: ['@sha256:', '@\$\{?\w*digest\w*\}?']
      - id: no-password-arg
        label: Tidak memberi password/token sebagai argumen -p
        forbid: true
        patterns: ['docker\s+login\s+.*\s(-p|--password)\s']
      - id: no-latest-deploy
        label: Tidak deploy production dari :latest
        forbid: true
        patterns: ['(docker\s+(run|pull)\s+\S*|image:\s*\S*)ghcr\.io/acme/api:latest\b']
    pass_score: 0.6
    solution: |
      ```bash
      echo "$GHCR_TOKEN" | docker login ghcr.io -u acme-bot --password-stdin
      docker buildx build -t ghcr.io/acme/api:${GITHUB_SHA} -t ghcr.io/acme/api:1.4.2 --push .
      DIGEST=$(docker buildx imagetools inspect ghcr.io/acme/api:${GITHUB_SHA} --format '{{json .Manifest.Digest}}' | tr -d '"')
      # staging
      docker pull ghcr.io/acme/api@${DIGEST}
      # production (setelah staging OK) — digest yang SAMA
      docker run -d --name api ghcr.io/acme/api@${DIGEST}
      # rollback: deploy digest sebelumnya yang tercatat
      ```
      Build **sekali**, promosikan digest yang sama ke semua lingkungan. Jangan build ulang per lingkungan, karena
      hasilnya bisa berbeda.
      - Tag SHA menjawab "kode mana yang ada di image ini".
      - Tag semver memudahkan manusia.
      - **Digest** menjamin isinya.

      Di lab, tag yang sama terbukti bisa berpindah ke isi lain (`dc55863e` → `c33d0a60`), sedangkan digest lama tetap
      bisa ditarik. Karena itu rollback berdasarkan digest selalu pasti. Simpan digest setiap rilis, mis. di release notes,
      file state deploy, atau Git. Di Compose/Kubernetes: `image: ghcr.io/acme/api:1.4.2@sha256:...`.
    tests:
      pass:
        - |
          echo "$GHCR_TOKEN" | docker login ghcr.io -u acme-bot --password-stdin
          docker build -t ghcr.io/acme/api:${GITHUB_SHA} -t ghcr.io/acme/api:1.4.2 .
          docker push ghcr.io/acme/api:${GITHUB_SHA}
          docker push ghcr.io/acme/api:1.4.2
          docker image inspect -f '{{index .RepoDigests 0}}' ghcr.io/acme/api:1.4.2
          docker run -d ghcr.io/acme/api@sha256:3f1c...
        - |
          printf %s "$TOKEN" | docker login ghcr.io -u bot --password-stdin
          docker buildx build -t ghcr.io/acme/api:$(git rev-parse --short HEAD) --push .
          docker buildx imagetools inspect ghcr.io/acme/api:abc1234
      fail:
        - |
          docker login ghcr.io -u acme-bot -p $GHCR_TOKEN
          docker build -t ghcr.io/acme/api:latest .
          docker push ghcr.io/acme/api:latest
        - |
          echo "$GHCR_TOKEN" | docker login ghcr.io -u acme-bot --password-stdin
          docker build -t ghcr.io/acme/api:latest .
          docker push ghcr.io/acme/api:latest
          docker pull ghcr.io/acme/api:latest
        - |
          docker build -t api .
          docker run -d api
    source: https://docs.docker.com/reference/cli/docker/image/pull/#pull-an-image-by-digest-immutable-identifier

  - id: c02
    tier: C
    type: scenario
    objective: scanning
    prompt: |
      Audit keamanan melaporkan image nginx production penuh CVE. Tulis langkah (satu perintah/baris per baris) untuk
      membuktikan penyebabnya, memperbaiki base image, memverifikasi hasilnya, dan memastikan masalah ini tertangkap
      otomatis di masa depan.
    context: |
      $ grep FROM docker/nginx.Dockerfile
      FROM nginx:1.27-alpine
      $ trivy image --severity HIGH,CRITICAL --quiet registry.local/web:2.3.0 | grep Total
      Total: 70 (HIGH: 67, CRITICAL: 3)
    checklist:
      - id: scan
        label: Memindai image dengan trivy
        required: true
        patterns: ['trivy\s+image\s+']
      - id: age
        label: Memeriksa umur/versi base image (Created, nginx -v, alpine-release)
        patterns: ['image\s+inspect\s+.*created', '\{\{\s*\.created\s*\}\}', 'nginx\s+-v', 'alpine-release']
      - id: update-base
        label: Pindah ke versi nginx yang masih dipelihara (stable/mainline/1.30+)
        required: true
        patterns: ['nginx:(stable|mainline)(-alpine)?\b', 'nginx:1\.(29|[3-9]\d)(\.\d+)?(-alpine)?\b']
      - id: rebuild
        label: Rebuild dengan --pull
        patterns: ['docker\s+(buildx\s+)?build\s+.*--pull']
      - id: gate
        label: Gerbang CI (--exit-code 1)
        required: true
        patterns: ['--exit-code[= ]1\b']
      - id: unfixed
        label: Fokus ke yang bisa diperbaiki (--ignore-unfixed)
        patterns: ['--ignore-unfixed']
      - id: schedule
        label: Rebuild/scan terjadwal atau bot update dependency (Renovate/Dependabot)
        patterns: ['\bcron\b', 'schedule', 'renovate', 'dependabot', 'terjadwal', 'setiap (hari|minggu|malam)']
      - id: no-gate-off
        label: Tidak mematikan gerbang (exit-code 0)
        forbid: true
        patterns: ['--exit-code[= ]0\b']
    pass_score: 0.5
    solution: |
      ```bash
      docker image inspect -f '{{.Created}}' nginx:1.27-alpine        # 2025-04-16 → base sudah beku
      docker run --rm nginx:1.27-alpine cat /etc/alpine-release      # 3.21.3
      # docker/nginx.Dockerfile
      FROM nginx:1.30-alpine                                          # baris stable yang masih dipelihara
      docker build --pull -f docker/nginx.Dockerfile -t registry.local/web:2.3.1 .
      trivy image --severity HIGH,CRITICAL --ignore-unfixed --exit-code 1 registry.local/web:2.3.1
      # CI: jalankan scan yang sama di setiap build + jadwal mingguan; Renovate/Dependabot untuk update tag base image
      ```
      Diukur di lab:
      - `nginx:1.27-alpine` (dibuat 2025-04-16, Alpine 3.21.3) → **70** HIGH/CRITICAL.
      - `nginx:stable-alpine` (2026-11-18, nginx 1.30.7, Alpine 3.24.4) → **1**.

      Penyebabnya bukan kode nginx 1.27, tetapi tag yang sudah berhenti di-rebuild setelah versi itu tidak dipelihara.
      Pin tetap disarankan (`1.30-alpine`, bahkan dengan digest), asalkan ada proses yang menaikkannya secara rutin.
      Trivy di lab keluar dengan `exit=1` untuk CVE CRITICAL yang sudah ada perbaikannya, sehingga bisa langsung
      memblokir pipeline.
    tests:
      pass:
        - |
          trivy image --severity HIGH,CRITICAL nginx:1.27-alpine
          docker image inspect -f '{{.Created}}' nginx:1.27-alpine
          FROM nginx:1.30-alpine
          docker build --pull -t registry.local/web:2.3.1 .
          trivy image --severity CRITICAL --ignore-unfixed --exit-code 1 registry.local/web:2.3.1
        - |
          trivy image registry.local/web:2.3.0
          FROM nginx:stable-alpine
          trivy image --exit-code 1 --severity HIGH,CRITICAL registry.local/web:2.3.1
          # renovate untuk update base image
      fail:
        - |
          trivy image registry.local/web:2.3.0
          FROM nginx:1.27-alpine
          trivy image --exit-code 0 registry.local/web:2.3.0
        - |
          FROM nginx:latest
          docker build -t registry.local/web:2.3.1 .
        - |
          trivy image registry.local/web:2.3.0
          docker build --pull -t registry.local/web:2.3.1 .
    source: https://trivy.dev/latest/docs/target/container_image/

  - id: c03
    tier: C
    type: scenario
    objective: transfer
    prompt: |
      Server production berada di jaringan tertutup (tanpa akses internet dan tanpa registry). Kamu harus mengirim image
      `ghcr.io/acme/api:1.4.2` dari laptop ke server dan menjalankannya dengan metadata (CMD, ENV, label) utuh. Tulis
      langkahnya (satu perintah per baris), termasuk verifikasi bahwa image yang sampai identik.
    checklist:
      - id: save
        label: docker save image (bukan export)
        required: true
        patterns: ['docker\s+(image\s+)?save\s+']
      - id: transfer
        label: Transfer file (scp/rsync)
        patterns: ['\b(scp|rsync)\b']
      - id: checksum
        label: Checksum file / bandingkan image ID-digest
        patterns: ['sha256sum', 'shasum', 'docker\s+(image\s+)?inspect\s+.*\.id', '\{\{\s*\.id\s*\}\}']
      - id: load
        label: docker load di server
        required: true
        patterns: ['docker\s+(image\s+)?load\b']
      - id: run
        label: Jalankan dengan tag/ID yang sama
        patterns: ['docker\s+(container\s+)?run\s+.*ghcr\.io/acme/api:1\.4\.2', 'docker\s+compose\s+up']
      - id: no-export
        label: Tidak memakai docker export/import (metadata hilang)
        forbid: true
        patterns: ['docker\s+(container\s+)?export\b', 'docker\s+(image\s+)?import\b']
    pass_score: 0.6
    solution: |
      ```bash
      docker save -o api-1.4.2.tar ghcr.io/acme/api:1.4.2
      sha256sum api-1.4.2.tar > api-1.4.2.tar.sha256
      docker image inspect -f '{{.Id}}' ghcr.io/acme/api:1.4.2       # catat ID
      scp api-1.4.2.tar api-1.4.2.tar.sha256 ops@prod:/tmp/
      ssh ops@prod 'cd /tmp && sha256sum -c api-1.4.2.tar.sha256 && docker load -i api-1.4.2.tar'
      ssh ops@prod "docker image inspect -f '{{.Id}}' ghcr.io/acme/api:1.4.2"   # harus sama
      ssh ops@prod 'docker run -d --name api ghcr.io/acme/api:1.4.2'
      ```
      Hasil lab:
      - `save`/`load` mempertahankan tag dan metadata, dan image ID setelah load **identik**.
      - `export`/`import` membuat `Cmd=null Labels=null` dan satu layer, sehingga container gagal dengan
        `no command specified`.
      - Layer di file `save` (containerd store) sudah terkompresi. Di lab, gzip hanya menghemat 0,6%, jadi kompresi
        tambahan jarang sepadan.
      - Perhatikan arsitektur: image dari laptop arm64 tidak jalan di server amd64 (`exec format error`). Build untuk
        platform server (`--platform linux/amd64`).

      Untuk banyak server atau banyak rilis, registry internal (mis. `registry:2` dengan TLS+auth, atau Harbor) lebih
      praktis daripada memindahkan file tar.
    tests:
      pass:
        - |
          docker save -o api.tar ghcr.io/acme/api:1.4.2
          sha256sum api.tar
          scp api.tar ops@prod:/tmp/
          docker load -i /tmp/api.tar
          docker run -d ghcr.io/acme/api:1.4.2
        - |
          docker save ghcr.io/acme/api:1.4.2 | gzip > api.tar.gz
          rsync -av api.tar.gz prod:/srv/images/
          docker load < /srv/images/api.tar.gz
          docker image inspect -f '{{.Id}}' ghcr.io/acme/api:1.4.2
      fail:
        - |
          docker create --name tmp ghcr.io/acme/api:1.4.2
          docker export tmp > api.tar
          scp api.tar prod:/tmp/
          docker import /tmp/api.tar ghcr.io/acme/api:1.4.2
        - |
          docker save -o api.tar ghcr.io/acme/api:1.4.2
          scp api.tar prod:/tmp/
        - |
          docker push ghcr.io/acme/api:1.4.2
          docker pull ghcr.io/acme/api:1.4.2
    source: https://docs.docker.com/reference/cli/docker/image/save/

---

# Image & Registry

Image adalah artefak yang kamu rilis, dan registry adalah gudangnya. Modul ini membahas cara membaca nama image,
mengelola image lokal, bekerja dengan registry (login, push, pull, API), perbedaan tag dan digest yang menentukan
reproducibility, image multi-arsitektur, transfer tanpa registry, serta keamanan supply chain: scanning CVE dan SBOM.

> [!NOTE]
> Lab memakai Docker Engine 29.4.1 dengan **containerd image store** (default di Docker Desktop terbaru), registry lokal
> `registry:2` di `127.0.0.1:5005`, Docker Hub untuk pemeriksaan rate limit, dan Trivy yang dijalankan sebagai container.
> Jumlah CVE berubah setiap hari seiring database diperbarui. Angka di modul ini adalah hasil pemindaian 23 November 2026.

## 1. Anatomi nama image

```text
ghcr.io / acme / api : 1.4.2 @ sha256:c33d0a60c9c7...
└──┬──┘   └─┬─┘  └┬┘   └─┬─┘   └───────┬─────────┘
registry namespace repo  tag          digest (opsional)
```

| Ditulis | Artinya |
|---|---|
| `nginx` | `docker.io/library/nginx:latest` |
| `nginx:1.27-alpine` | `docker.io/library/nginx:1.27-alpine` |
| `bitnami/redis:7.4` | `docker.io/bitnami/redis:7.4` |
| `ghcr.io/acme/api:1.4.2` | GitHub Container Registry |
| `127.0.0.1:5005/demo/app:1.0` | Registry lokal (lab) |
| `alpine:3.20@sha256:d9e8...` | Tag + digest, dan yang dipakai adalah **digest** |

## 2. Image lokal

```bash
docker images --digests                         # tag, digest, ID
docker image inspect -f '{{.Architecture}} {{.Created}}' alpine:3.20
docker tag myapp:1.4.2 ghcr.io/acme/myapp:1.4.2 # alias, ID sama
docker rmi ghcr.io/acme/myapp:1.4.2             # Untagged — data tetap bila ada tag lain
docker history myapp:1.4.2
```

Catatan khusus **containerd image store** (lab):
- Image ID = digest index (`d9e853e87e55` untuk keduanya). Di storage klasik, ID adalah hash config.
- Rebuild dengan tag yang sama tidak meninggalkan image `<none>:<none>` di daftar (storage klasik meninggalkannya
  sebagai *dangling*).
- `docker images --tree` (eksperimental) menampilkan platform di dalam index.

> [!WARNING]
> `docker image prune`, `docker system prune`, dan `docker builder prune` berlaku untuk **seluruh mesin**, termasuk image,
> container, dan cache project lain. Di server bersama, filter dengan label atau umur
> (`--filter label=app=api`, `--filter until=168h`), atau hapus image secara spesifik.

## 3. Registry: login, push, pull

```bash
echo "$GHCR_TOKEN" | docker login ghcr.io -u acme-bot --password-stdin
docker push ghcr.io/acme/api:1.4.2
docker pull ghcr.io/acme/api:1.4.2
docker pull ghcr.io/acme/api@sha256:...
docker logout ghcr.io
```

- Kredensial: Docker Desktop memakai `"credsStore": "desktop"` (lab). Di Linux tanpa helper, kredensial tersimpan
  base64 di `~/.docker/config.json`.
- Docker Hub anonim: `ratelimit-limit: 100;w=3600` (lab: 100 pull per jam per IP). Error `toomanyrequests` saat
  deploy adalah alasan umum untuk login, memakai mirror, atau registry internal.

### API registry (Distribution v2)

```bash
curl -s http://127.0.0.1:5005/v2/                          # {} = API hidup
curl -s http://127.0.0.1:5005/v2/_catalog                  # {"repositories":["demo/app",...]}
curl -s http://127.0.0.1:5005/v2/demo/app/tags/list        # {"name":"demo/app","tags":["1.0"]}
curl -sI -H 'Accept: application/vnd.oci.image.index.v1+json' \
  http://127.0.0.1:5005/v2/demo/app/manifests/1.0          # Docker-Content-Digest: sha256:c33d...
```

`DELETE` manifest pada `registry:2` default → **405** (lab). Penghapusan harus diaktifkan dan disusul garbage collection.

## 4. Tag vs digest

```mermaid
sequenceDiagram
    participant CI
    participant R as Registry
    participant S as Server
    CI->>R: push demo/app:1.0 (v1) → digest dc55…
    S->>R: pull demo/app:1.0 → dc55… (version 1)
    CI->>R: push demo/app:1.0 (v2) → digest c33d…
    S->>R: pull demo/app:1.0 → c33d… (version 2!)
    S->>R: pull demo/app@sha256:dc55… → tetap version 1
```

Hasil lab persis seperti diagram di atas. **Tag bisa berpindah, digest tidak.**

| Kebutuhan | Pakai |
|---|---|
| Dibaca manusia, "versi berapa" | Tag semver (`1.4.2`) |
| Melacak ke kode | Tag commit SHA |
| Menjamin isi, rollback pasti | Digest (`@sha256:...`) |
| "Terbaru" untuk dev | `latest` / `main` — jangan di production |

## 5. Multi-arsitektur dan attestation

```text
$ docker buildx imagetools inspect alpine:3.20
MediaType: application/vnd.oci.image.index.v1+json
  Platform: linux/amd64      → sha256:c64c687c...
  Platform: linux/arm/v6     → sha256:37753b29...
  Platform: unknown/unknown  → attestation (SBOM/provenance)
  ...
```

- `docker pull` memilih manifest sesuai CPU mesin. `--platform` memaksa platform lain.
- Salin image beserta semua platform antar registry: `docker buildx imagetools create -t target sumber` (lab: digest
  index sama).

## 6. Transfer tanpa registry

| | `save` → `load` | `export` → `import` |
|---|---|---|
| Sumber | **Image** | **Container** (filesystem) |
| Layer | Tetap (lab: 2) | Diratakan jadi 1 |
| CMD/ENV/label | Utuh | **Hilang** (lab: `Cmd=null`, `no command specified`) |
| Tag | Ikut | Harus diberi saat import |
| Gunanya | Memindahkan image | Mengambil snapshot filesystem |

## 7. Scanning CVE dan SBOM

```bash
docker run --rm -v trivy-cache:/root/.cache aquasec/trivy:latest image --severity HIGH,CRITICAL nginx:1.27-alpine
trivy image --severity CRITICAL --ignore-unfixed --exit-code 1 myapp:1.0     # gerbang CI
trivy image --format cyclonedx -o sbom.json myapp:1.0                         # SBOM
```

| Image (dipindai 23 Nov 2026) | Dibuat | Isi | HIGH/CRITICAL |
|---|---|---|---|
| `nginx:1.25-alpine` | 2024-05-03 | nginx 1.25.5, Alpine 3.19.1 | 20 |
| `nginx:1.27-alpine` | 2025-04-16 | nginx 1.27.5, Alpine 3.21.3 | **70** |
| `nginx:stable-alpine` | 2026-11-18 | nginx 1.30.7, Alpine 3.24.4 | **1** |
| `nginx:mainline-alpine` | 2026-11-18 | nginx 1.31.9, Alpine 3.24.4 | 1 |
| `alpine:3.20` | 2026-04-16 | Alpine 3.20.10 | 2 |

> [!INSIGHT]
> **Pin ke versi yang sudah EOL = membeku dengan semua CVE-nya.** `nginx:1.27-alpine` tidak di-rebuild sejak April 2025,
> karena versi 1.27 sudah tidak dipelihara. Di lab, tag itu mengandung 70 CVE HIGH/CRITICAL, lebih banyak dari image
> 1.25 yang lebih tua, karena database CVE terus bertambah sementara isi image tidak berubah. Contoh `nginx:1.27-alpine`
> di modul 01–03 dimaksudkan sebagai contoh pola *pinning*. Untuk dipakai sungguhan, pilih baris versi yang masih
> dipelihara (saat ini 1.30 stable), dan otomatiskan kenaikannya (Renovate/Dependabot).
> Sumber: [Trivy — container image](https://trivy.dev/latest/docs/target/container_image/)

## 8. Strategi tag dan promosi

```mermaid
flowchart LR
    C[commit abc1234] --> B[CI build sekali]
    B --> T1[":abc1234"] & T2[":1.4.2"]
    T1 --> D{{digest sha256:c33d…}}
    T2 --> D
    D --> S[staging]
    S -- lolos --> P[production — digest yang SAMA]
    P -. rollback .-> D0{{digest rilis sebelumnya}}
```

1. Build **sekali** per commit, jangan build ulang per lingkungan.
2. Tag dengan commit SHA (dan semver saat rilis).
3. Catat digest. Deploy dan rollback berdasarkan digest.
4. Pindai di CI (gerbang) dan secara terjadwal (CVE baru untuk image lama).
5. Simpan SBOM per rilis.
6. Atur retensi di registry supaya tag lama tidak menumpuk tanpa batas.

## 9. Ringkasan kebiasaan aman

| Kebiasaan | Kenapa |
|---|---|
| `--password-stdin`, credential helper | Token tidak bocor ke history/proses |
| Deploy/rollback dengan digest | Tag bisa dipindah (lab: dc55… → c33d…) |
| Tag SHA + semver, bukan `latest` di production | Bisa dilacak dan reproducible |
| Pin ke versi yang **masih dipelihara** + update otomatis | Tag EOL membeku dengan CVE-nya (70 vs 1) |
| Trivy `--exit-code 1 --ignore-unfixed` di CI | Gerbang yang bisa ditindaklanjuti |
| SBOM per rilis | Pindai ulang tanpa image saat CVE baru muncul |
| Mirror base image / login ke Docker Hub | 100 pull/jam per IP anonim |
| `save`/`load`, bukan `export`/`import`, untuk memindahkan image | Metadata tetap utuh |
| Prune dengan filter, bukan global | Prune ikut menghapus milik project lain |
