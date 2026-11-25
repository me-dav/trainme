---
format: 1
id: docker-networking
course:
  slug: docker
  title: Docker
  description: Container dari nol sampai production — CLI, image, storage, network, Compose, keamanan, dan operasional.
title: Networking Container
summary: Bridge default vs user-defined network, DNS antar container, jebakan localhost dan port, akses ke host, segmentasi frontend/backend, network internal, alias, dan troubleshooting koneksi antar container.
order: 6
version: 1
status: verified
updated: '2026-11-23'
tested_on:
  - Docker Desktop (Windows) — Docker Engine 29.4.1, driver bridge
  - Image alpine:3.20 (wget, nc, nslookup busybox), nginx:1.27-alpine, redis:7-alpine
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: drivers
    text: Membedakan driver bridge, host, none, dan bridge default vs user-defined
  - id: network-cli
    text: Mengelola network (create, ls, inspect, connect, disconnect, rm)
  - id: dns
    text: Memahami DNS bawaan Docker (127.0.0.11), nama container, dan alias
  - id: ports
    text: Membedakan port container dan port host, termasuk jebakan localhost
  - id: host-access
    text: Mengakses service di host dari container (host.docker.internal, host-gateway)
  - id: segmentation
    text: Mendesain segmentasi network (multi-network, --internal)
  - id: troubleshoot
    text: Mendiagnosis kegagalan koneksi antar container
sources:
  - title: Docker Docs — Networking overview
    url: https://docs.docker.com/engine/network/
  - title: Docker Docs — Bridge network driver (default vs user-defined)
    url: https://docs.docker.com/engine/network/drivers/bridge/
  - title: Docker Docs — Host network driver
    url: https://docs.docker.com/engine/network/drivers/host/
  - title: Docker Docs — None network driver
    url: https://docs.docker.com/engine/network/drivers/none/
  - title: docker network (create, connect, inspect, rm)
    url: https://docs.docker.com/reference/cli/docker/network/
  - title: docker network create — --internal
    url: https://docs.docker.com/reference/cli/docker/network/create/#internal
  - title: Docker Docs — Container networking (DNS, aliases, --dns, --add-host)
    url: https://docs.docker.com/engine/network/#dns-services
  - title: Docker Docs — Port publishing and mapping
    url: https://docs.docker.com/engine/network/port-publishing/
  - title: Docker Desktop — Networking (host.docker.internal)
    url: https://docs.docker.com/desktop/features/networking/
  - title: docker container ls — filter publish / network
    url: https://docs.docker.com/reference/cli/docker/container/ls/#filter
  - title: Docker Docs — Legacy container links
    url: https://docs.docker.com/engine/network/links/
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: command
    objective: network-cli
    prompt: Buat user-defined network (driver bridge) bernama `appnet`.
    answers: ['docker network create appnet']
    patterns: ['^docker network create( (-d|--driver)[= ]?bridge)? appnet$']
    tests:
      pass: ['docker network create --driver bridge appnet']
      fail: ['docker create network appnet', 'docker network new appnet', 'docker net create appnet']
    explanation: 'Default driver-nya `bridge`. Di lab, network baru mendapat subnet sendiri (`172.18.0.0/16`, gateway `172.18.0.1`). Container di network ini mendapat DNS berdasarkan nama, yang tidak tersedia di bridge default.'
    source: https://docs.docker.com/reference/cli/docker/network/create/

  - id: a02
    tier: A
    type: command
    objective: network-cli
    prompt: Tampilkan daftar semua network Docker.
    answers: ['docker network ls']
    patterns: ['^docker network (ls|list)( --filter[= ]\S+)?$']
    tests:
      fail: ['docker networks', 'docker ls network', 'docker ps --network']
    explanation: 'Selalu ada `bridge` (default), `host`, dan `none`. Compose membuat network `<project>_default` untuk setiap project.'
    source: https://docs.docker.com/reference/cli/docker/network/ls/

  - id: a03
    tier: A
    type: command
    objective: network-cli
    prompt: Jalankan `redis:7-alpine` di background bernama `db`, langsung terhubung ke network `appnet`.
    answers: ['docker run -d --name db --network appnet redis:7-alpine']
    patterns: ['^docker (container )?run (?=.*(-d|--detach)( |$))(?=.*--name[= ]db( |$))(?=.*(--network|--net)[= ]appnet( |$))((-d|--detach|--name[= ]db|(--network|--net)[= ]appnet) )+redis:7-alpine$']
    tests:
      pass: ['docker run --network appnet -d --name db redis:7-alpine', 'docker run -d --name=db --network=appnet redis:7-alpine']
      fail: ['docker run -d --name db --network-alias appnet redis:7-alpine', 'docker run -d --name db redis:7-alpine --network appnet', 'docker run -d --name db -p appnet redis:7-alpine']
    explanation: 'Container lain di `appnet` bisa memanggilnya dengan nama `db`. `--network` bisa ditulis lebih dari sekali untuk menghubungkan ke beberapa network sekaligus (lab: `dj-back dj-front`).'
    source: https://docs.docker.com/reference/cli/docker/container/run/#network

  - id: a04
    tier: A
    type: command
    objective: network-cli
    prompt: Hubungkan container `web` yang **sudah berjalan** ke network `appnet`.
    answers: ['docker network connect appnet web']
    patterns: ['^docker network connect( --alias[= ]\S+)? appnet web$']
    tests:
      pass: ['docker network connect --alias www appnet web']
      fail: ['docker network connect web appnet', 'docker connect appnet web', 'docker network attach appnet web']
    explanation: 'Urutannya **network dulu, baru container**. Container tidak perlu dibuat ulang, dan ia mendapat interface serta IP tambahan. Di lab, nginx di bridge default yang di-connect ke `dj-net` langsung bisa dipanggil dengan nama dari container di `dj-net`.'
    source: https://docs.docker.com/reference/cli/docker/network/connect/

  - id: a05
    tier: A
    type: command
    objective: network-cli
    prompt: Lepaskan container `web` dari network `appnet`.
    answers: ['docker network disconnect appnet web']
    patterns: ['^docker network disconnect( (-f|--force))? appnet web$']
    tests:
      fail: ['docker network disconnect web appnet', 'docker network rm appnet web', 'docker disconnect appnet web']
    explanation: 'Berguna untuk mengisolasi container yang dicurigai terkompromi tanpa mematikannya (bukti di memori tetap ada), atau untuk memindahkan container antar segmen.'
    source: https://docs.docker.com/reference/cli/docker/network/disconnect/

  - id: a06
    tier: A
    type: command
    objective: network-cli
    prompt: Tampilkan detail network `appnet` (driver, subnet, gateway, container yang terhubung).
    answers: ['docker network inspect appnet']
    patterns: ['^docker network inspect( (-f|--format)[= ]?\S+)? appnet$']
    tests:
      fail: ['docker inspect network appnet', 'docker network ls appnet', 'docker network show appnet']
    explanation: 'Di lab: `bridge subnet=172.18.0.0/16 gw=172.18.0.1 containers=3`. Field `.Internal` menunjukkan apakah network memblokir akses keluar (soal tier B).'
    source: https://docs.docker.com/reference/cli/docker/network/inspect/

  - id: a07
    tier: A
    type: text
    objective: dns
    prompt: Di container yang terhubung ke **user-defined network**, alamat DNS server apa yang tertulis di `/etc/resolv.conf`?
    answers: ['127.0.0.11']
    tests:
      fail: ['127.0.0.1', '8.8.8.8', '192.168.65.7', '127.0.0.53']
    explanation: 'Docker menjalankan DNS bawaan di `127.0.0.11`. DNS ini menjawab nama container/alias di network yang sama dan meneruskan nama lain ke DNS host. Di lab: user-defined → `nameserver 127.0.0.11`, sedangkan bridge default → `nameserver 192.168.65.7` (DNS Docker Desktop, salinan dari host).'
    source: https://docs.docker.com/engine/network/#dns-services

  - id: a08
    tier: A
    type: text
    objective: drivers
    prompt: Dua container sama-sama di network `bridge` **default**. Bisakah satu memanggil yang lain memakai **nama container**? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab: `wget http://dj-a/` dari bridge default → `bad address ''dj-a''`, sedangkan lewat IP berhasil. Resolusi nama hanya tersedia di user-defined network. Itulah sebabnya dokumentasi Docker menyarankan user-defined network untuk semua aplikasi, dan Compose selalu membuatnya.'
    source: https://docs.docker.com/engine/network/drivers/bridge/#differences-between-user-defined-bridges-and-the-default-bridge

  - id: a09
    tier: A
    type: text
    objective: host-access
    prompt: Di Docker Desktop, hostname apa yang dipakai dari **dalam container** untuk menjangkau service yang berjalan di mesin host?
    answers: ['host.docker.internal']
    tests:
      fail: ['localhost', 'host.local', 'docker.host', 'gateway.docker.internal']
    explanation: 'Di lab: `host.docker.internal` → `192.168.65.254`, dan service di host yang di-publish ke `127.0.0.1:8098` bisa dijangkau dari container lewat nama itu (dari bridge default maupun user-defined). Contoh kasus: aplikasi di container memakai MySQL/PostgreSQL dari Laragon di Windows.'
    source: https://docs.docker.com/desktop/features/networking/

  - id: a10
    tier: A
    type: text
    objective: ports
    prompt: Dari dalam sebuah container, `localhost` (127.0.0.1) menunjuk ke mana?
    answers: ['container itu sendiri']
    patterns: ['^(container( itu| ini| tersebut)?( sendiri)?|dirinya( sendiri)?|itself|diri sendiri)$']
    tests:
      pass: ['dirinya sendiri', 'container itu']
      fail: ['host', 'laptop', 'container lain', 'server docker']
    explanation: 'Setiap container punya network namespace sendiri, termasuk loopback-nya. Di lab: `wget http://localhost:80` dari container alpine → `Connection refused` (tidak ada nginx di container alpine), dan `wget http://127.0.0.1:8098` ke port host juga ditolak. `DB_HOST=127.0.0.1` dari `.env` laptop adalah penyebab nomor satu "app di container tidak bisa konek ke DB".'
    source: https://docs.docker.com/engine/network/

  - id: a11
    tier: A
    type: command
    objective: drivers
    prompt: Jalankan `alpine:3.20` sekali pakai **tanpa network sama sekali** (hanya loopback), lalu jalankan `ip addr`.
    answers: ['docker run --rm --network none alpine:3.20 ip addr']
    patterns: ['^docker (container )?run --rm (--network|--net)[= ]none alpine:3\.20 ip (addr|a)( show)?$', '^docker (container )?run (--network|--net)[= ]none --rm alpine:3\.20 ip (addr|a)( show)?$']
    tests:
      pass: ['docker run --network=none --rm alpine:3.20 ip a']
      fail: ['docker run --rm --network off alpine:3.20 ip addr', 'docker run --rm --no-network alpine:3.20 ip addr', 'docker run --rm --network host alpine:3.20 ip addr']
    explanation: 'Di lab: hanya `lo 127.0.0.1/8`, dan `wget example.com` → `bad address`. Cocok untuk job yang memproses file tanpa butuh jaringan (konversi, scanning), karena kode jahat di dalamnya tidak bisa "menelepon pulang".'
    source: https://docs.docker.com/engine/network/drivers/none/

  - id: a12
    tier: A
    type: command
    objective: segmentation
    prompt: Buat network bernama `backend` yang **tidak punya akses keluar** (internet).
    answers: ['docker network create --internal backend']
    patterns: ['^docker network create (--internal (backend|(-d|--driver)[= ]?bridge backend)|((-d|--driver)[= ]?bridge )?--internal backend|backend --internal)$']
    tests:
      pass: ['docker network create backend --internal']
      fail: ['docker network create --private backend', 'docker network create backend --no-internet', 'docker network create --isolated backend']
    explanation: 'Di lab, container di network internal: `wget example.com` → `bad address`, sementara antar container di network itu tetap bisa saling memanggil (`dj-i1` menjawab). Cocok untuk database/Redis yang tidak pernah perlu keluar.'
    source: https://docs.docker.com/reference/cli/docker/network/create/#internal

  - id: a13
    tier: A
    type: command
    objective: dns
    prompt: 'Jalankan `myorg/api:1.0` di background, di network `appnet`, dengan **nama DNS tambahan** (alias) `api`.'
    answers: ['docker run -d --network appnet --network-alias api myorg/api:1.0']
    patterns: ['^docker (container )?run (?=.*(-d|--detach)( |$))(?=.*(--network|--net)[= ]appnet( |$))(?=.*--network-alias[= ]api( |$))((-d|--detach|(--network|--net)[= ]appnet|--network-alias[= ]api|--name[= ]\S+) )+myorg/api:1\.0$']
    tests:
      pass: ['docker run -d --name api-blue --network appnet --network-alias api myorg/api:1.0']
      fail: ['docker run -d --network appnet --alias api myorg/api:1.0', 'docker run -d --network-alias api myorg/api:1.0', 'docker run -d --network appnet myorg/api:1.0 --network-alias api']
    explanation: 'Alias memungkinkan beberapa container dengan nama berbeda dijawab oleh satu nama DNS (soal tier B). Pola blue/green sederhana: container `api-blue` dan `api-green`, dengan alias `api` dipindah dari satu ke yang lain. Alias hanya berlaku di network tempat ia didefinisikan.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#network-alias

  - id: a14
    tier: A
    type: command
    objective: ports
    prompt: Jalankan `nginx:1.27-alpine` di background dan publish **semua** port EXPOSE ke port host **acak**.
    answers: ['docker run -d -P nginx:1.27-alpine']
    patterns: ['^docker (container )?run (-d -P|-P -d|-dP|-Pd|--publish-all -d|-d --publish-all|--publish-all --detach|--detach --publish-all) nginx:1\.27-alpine$']
    tests:
      pass: ['docker run -dP nginx:1.27-alpine', 'docker run --publish-all -d nginx:1.27-alpine']
      fail: ['docker run -d -p nginx:1.27-alpine', 'docker run -d --expose 80 nginx:1.27-alpine', 'docker run -d nginx:1.27-alpine']
    explanation: 'Di lab: `80/tcp -> 0.0.0.0:32768`. Lihat port-nya dengan `docker port`. Berguna untuk test paralel di CI (tidak ada bentrok port). Di production, port acak menyulitkan konfigurasi firewall dan proxy.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#publish-all

  - id: a15
    tier: A
    type: command
    objective: host-access
    prompt: Jalankan `alpine:3.20` sekali pakai dengan entri `/etc/hosts` yang membuat `host.docker.internal` menunjuk ke **gateway host** (diperlukan di Docker Engine Linux), lalu tampilkan `/etc/hosts`.
    answers: ['docker run --rm --add-host=host.docker.internal:host-gateway alpine:3.20 cat /etc/hosts']
    patterns: ['^docker (container )?run --rm --add-host[= ]host\.docker\.internal:host-gateway alpine:3\.20 cat /etc/hosts$']
    tests:
      pass: ['docker run --rm --add-host host.docker.internal:host-gateway alpine:3.20 cat /etc/hosts']
      fail: ['docker run --rm --add-host=host.docker.internal alpine:3.20 cat /etc/hosts', 'docker run --rm --dns host-gateway alpine:3.20 cat /etc/hosts', 'docker run --rm --network host alpine:3.20 cat /etc/hosts']
    explanation: '`host-gateway` adalah nilai khusus yang diganti Docker dengan IP gateway host. Di lab (Docker Desktop): `--add-host=myhost:host-gateway` → `192.168.65.254 myhost` (plus IPv6). Di Docker Engine Linux, `host.docker.internal` tidak ada secara default, sehingga opsi ini (atau `extra_hosts` di Compose) yang membuatnya.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#add-host

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: ports
    prompt: 'Container `app` dan `db` (PostgreSQL) ada di network yang sama. `db` dijalankan dengan `-p 127.0.0.1:15432:5432`. Dari dalam `app`, ke **port** berapa ia harus konek?'
    answers: ['5432']
    tests:
      fail: ['15432', '80', '6432']
    explanation: 'Antar container di network yang sama, yang dipakai adalah **port container** (yang di-listen proses). Port host (`15432`) hanya berlaku dari host. Di lab: `wget http://dj-a:8085` (port host) → `Connection refused`, `wget http://dj-a:80` → halaman nginx. Container lain tidak butuh `-p` sama sekali untuk saling bicara.'
    source: https://docs.docker.com/engine/network/port-publishing/

  - id: b02
    tier: B
    type: text
    objective: dns
    prompt: 'Lanjutan soal sebelumnya: container database bernama `db`. Nilai `DB_HOST` apa yang benar di environment container `app`?'
    answers: ['db']
    tests:
      fail: ['localhost', '127.0.0.1', 'host.docker.internal', '0.0.0.0']
    explanation: 'Nama container (atau alias) di-resolve oleh DNS Docker ke IP container saat itu. Di lab: `nslookup dj-a` → `172.18.0.2`. Jangan menulis IP container, karena IP berubah saat container dibuat ulang (lab: `172.18.0.3` → `172.18.0.5`). Di Compose, namanya adalah **nama service**.'
    source: https://docs.docker.com/engine/network/#dns-services

  - id: b03
    tier: B
    type: text
    objective: segmentation
    prompt: '`db` hanya terhubung ke network `back`. Sebuah container yang hanya terhubung ke network `front` mencoba `nc -zv db 6379`. Bisakah nama `db` di-resolve? (ya/tidak)'
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'DNS Docker hanya menjawab nama container yang berbagi network dengan penanya. Di lab, dari `dj-front` → `nc: bad address ''dj-db''`, sedangkan dari `dj-app` (terhubung ke `dj-back` juga) → `dj-db (172.20.0.2:6379) open`. Segmentasi seperti ini membatasi siapa yang bisa melihat database.'
    source: https://docs.docker.com/engine/network/drivers/bridge/

  - id: b04
    tier: B
    type: text
    objective: segmentation
    prompt: Container `app` terhubung ke network `back` **dan** `front`. Berapa alamat IP yang dimilikinya?
    answers: ['2', 'dua']
    tests:
      fail: ['1', 'satu', '0', '3']
    explanation: 'Satu interface dan satu IP per network. Di lab: `dj-back=172.20.0.3 dj-front=172.19.0.2`. Dengan cara ini, `app` bisa menjadi jembatan antara proxy (front) dan database (back), tanpa proxy bisa menjangkau database secara langsung.'
    source: https://docs.docker.com/reference/cli/docker/network/connect/

  - id: b05
    tier: B
    type: text
    objective: segmentation
    prompt: Container yang **hanya** terhubung ke network yang dibuat dengan `--internal` mencoba `wget http://example.com`. Apa hasilnya?
    answers: ['gagal (bad address)']
    patterns: ['(gagal|tidak bisa|bad address|error|ditolak|tidak ada (akses|koneksi|internet)|timeout|fail)']
    tests:
      pass: ['bad address', 'tidak bisa akses internet']
      fail: ['berhasil', 'bisa', 'halaman example.com tampil']
    explanation: 'Di lab: `wget: bad address ''example.com''`, karena DNS ke luar pun tidak dijawab. Container yang sama di network biasa → `outbound-ok`. Kalau sebuah service di network internal ternyata perlu memanggil API luar, hubungkan ke network kedua yang biasa, atau lewat proxy egress.'
    source: https://docs.docker.com/reference/cli/docker/network/create/#internal

  - id: b06
    tier: B
    type: text
    objective: segmentation
    prompt: 'Container yang **hanya** ada di network `--internal` dijalankan dengan `-p 127.0.0.1:8086:80`. Bisakah port itu diakses dari host? (ya/tidak)'
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab: `curl 127.0.0.1:8086` → `000` (tidak terhubung). Network internal tidak punya jalur ke host, sehingga port publishing tidak efektif. Konsekuensinya bagus untuk keamanan (database di network internal tidak bisa tidak sengaja ter-publish), tetapi membingungkan bila tidak disadari. Service yang perlu di-publish (nginx) harus juga terhubung ke network biasa.'
    source: https://docs.docker.com/reference/cli/docker/network/create/#internal

  - id: b07
    tier: B
    type: text
    objective: dns
    prompt: 'Dua container di network yang sama sama-sama memakai `--network-alias web`. `nslookup web` mengembalikan berapa alamat container?'
    answers: ['2', 'dua']
    tests:
      fail: ['1', 'satu', '0', 'error']
    explanation: 'DNS Docker mengembalikan semua IP pemilik alias, dan urutannya berganti-ganti (round-robin sederhana). Di lab: `172.18.0.4` dan `172.18.0.3`. Ini bukan load balancer sungguhan: klien yang meng-cache DNS bisa terus memakai satu IP, dan tidak ada health check. Gunakan reverse proxy untuk distribusi beban yang benar.'
    source: https://docs.docker.com/engine/network/#dns-services

  - id: b08
    tier: B
    type: text
    objective: dns
    prompt: Bila container dibuat ulang (rm lalu run lagi dengan nama sama di network yang sama), apakah IP-nya dijamin sama? (ya/tidak)
    answers: ['tidak', 'no', 'tidak selalu']
    patterns: ['^(tidak( selalu| dijamin)?|no|belum tentu)$']
    tests:
      fail: ['ya', 'yes', 'selalu sama']
    explanation: 'Di lab: `w1 before=172.18.0.3 after=172.18.0.5` karena container lain mengambil IP yang kosong. Konfigurasi yang menulis IP container (config nginx `proxy_pass http://172.18.0.3`, `pg_hba.conf`) akan rusak secara acak. Pakai nama/alias. Untuk aturan berbasis IP, pakai subnet network (`172.18.0.0/16`).'
    source: https://docs.docker.com/engine/network/

  - id: b09
    tier: B
    type: command
    objective: network-cli
    prompt: '`docker network rm appnet` gagal karena `has active endpoints`. Tampilkan container yang memakai network `appnet` (termasuk yang berhenti) dengan `docker ps`.'
    answers: ['docker ps -a --filter network=appnet']
    patterns: ['^docker (ps|container ls) ((-a|--all) )?--filter[= ]network=appnet( (-a|--all))?$', '^docker network inspect (-f|--format)[= ]?\{\{ ?range \.Containers ?\}\}\{\{ ?\.Name ?\}\}( |\{\{println\}\})?\{\{ ?end ?\}\} appnet$']
    tests:
      pass: ['docker ps --filter network=appnet -a', 'docker network inspect -f "{{range .Containers}}{{.Name}} {{end}}" appnet']
      fail: ['docker network ls appnet', 'docker ps appnet', 'docker network rm -f appnet']
    explanation: 'Di lab, pesan errornya: `network dj-net has active endpoints (name:"dj-x" ..., name:"dj-w1" ...)`. Putuskan container dengan `docker network disconnect` atau hapus container-nya. Tidak ada `network rm --force`.'
    source: https://docs.docker.com/reference/cli/docker/container/ls/#filter

  - id: b10
    tier: B
    type: text
    objective: dns
    prompt: 'Container di **user-defined network** dijalankan dengan `--dns 1.1.1.1`. Nameserver apa yang tertulis di `/etc/resolv.conf` container itu?'
    answers: ['127.0.0.11']
    tests:
      fail: ['1.1.1.1', '8.8.8.8', '192.168.65.7']
    explanation: 'Di lab: `nameserver 127.0.0.11` dengan komentar `# Overrides: [nameservers]`. DNS bawaan tetap dipakai (supaya nama container tetap bisa di-resolve), dan `1.1.1.1` menjadi upstream-nya untuk nama luar. Di bridge default, `--dns 1.1.1.1` langsung tertulis sebagai nameserver (lab). Jadi jangan heran bila `cat /etc/resolv.conf` tidak menampilkan DNS yang kamu set.'
    source: https://docs.docker.com/engine/network/#dns-services

  - id: b11
    tier: B
    type: text
    objective: host-access
    prompt: Di **Docker Engine Linux** (bukan Desktop), opsi `docker run` apa yang membuat nama `host.docker.internal` me-resolve ke host?
    answers: ['--add-host=host.docker.internal:host-gateway']
    patterns: ['--add-host[= ]host\.docker\.internal:host-gateway', '^host-gateway$']
    tests:
      pass: ['--add-host host.docker.internal:host-gateway']
      fail: ['--network host', '--dns host', '--link host', '--add-host=host.docker.internal']
    explanation: 'Docker Desktop menyediakan nama itu secara otomatis (lab: `192.168.65.254`), sedangkan Engine Linux tidak. Catatan penting di Linux: `host-gateway` adalah IP bridge (biasanya `172.17.0.1`), sehingga service host yang hanya listen di `127.0.0.1` **tidak** terjangkau. Service itu harus listen di IP bridge/0.0.0.0, dengan firewall membatasi akses (modul Linux Networking). Di Docker Desktop (lab), service yang di-bind ke `127.0.0.1` host tetap terjangkau.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#add-host

  - id: b12
    tier: B
    type: text
    objective: network-cli
    prompt: Apa **driver** default yang dipakai `docker network create` bila tidak menyebut `--driver`?
    answers: ['bridge']
    tests:
      fail: ['host', 'overlay', 'macvlan', 'none']
    explanation: 'Driver lain: `host` (berbagi network stack host, tanpa isolasi port), `none`, `overlay` (antar host, untuk Swarm), dan `macvlan`/`ipvlan` (container mendapat IP di LAN fisik). Untuk satu server, user-defined bridge sudah cukup untuk hampir semua kebutuhan.'
    source: https://docs.docker.com/engine/network/drivers/

  - id: b13
    tier: B
    type: text
    objective: drivers
    prompt: Opsi lama `docker run` apa yang dulu dipakai agar container di bridge default bisa saling memanggil dengan nama, dan kini **deprecated**?
    answers: ['--link', 'link']
    tests:
      fail: ['--alias', '--dns', '--network-alias', '--hostname']
    explanation: '`--link` menulis entri ke `/etc/hosts` dan environment variable, satu arah, serta tidak diperbarui bila IP berubah. Dokumentasi menyebutnya legacy dan bisa dihapus kapan saja. Penggantinya user-defined network. Tutorial atau docker-compose lama yang memakai `links:` sebaiknya dimigrasikan.'
    source: https://docs.docker.com/engine/network/links/

  - id: b14
    tier: B
    type: command
    objective: ports
    prompt: Deploy gagal karena port host **8080** sudah dipakai. Tampilkan container yang mem-publish port 8080 dengan filter `docker ps`.
    answers: ['docker ps --filter publish=8080']
    patterns: ['^docker (ps|container ls) ((-a|--all) )?--filter[= ]publish=8080(/tcp)?( (-a|--all))?$']
    tests:
      pass: ['docker ps --filter publish=8080/tcp']
      fail: ['docker port 8080', 'docker ps --port 8080', 'docker network inspect 8080']
    explanation: 'Di lab: `docker ps --filter publish=8098` → `dj-hostsvc 127.0.0.1:8098->80/tcp`. Bila tidak ada container yang cocok, berarti proses lain di host yang memakai port itu, jadi lanjutkan dengan `ss -ltnp` atau `netstat` (modul Linux Networking). Di Windows dengan Laragon, port 80/443/3306 sering sudah terpakai.'
    source: https://docs.docker.com/reference/cli/docker/container/ls/#publish-and-expose

  - id: b15
    tier: B
    type: text
    objective: host-access
    prompt: 'Di Docker Desktop (lab), service di host di-publish/bind ke `127.0.0.1:8098`. Dari dalam container, apakah `http://host.docker.internal:8098` berhasil? (ya/tidak)'
    answers: ['ya', 'yes', 'berhasil']
    tests:
      fail: ['tidak', 'no', 'gagal']
    explanation: 'Di lab: dari host `200`, dari container lewat `host.docker.internal:8098` → halaman nginx, sedangkan lewat `127.0.0.1:8098` → `Connection refused`. Docker Desktop meneruskan trafik `host.docker.internal` ke loopback host. Di Engine Linux perilakunya berbeda (soal b11), jadi konfigurasi yang jalan di laptop belum tentu jalan di server.'
    source: https://docs.docker.com/desktop/features/networking/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: troubleshoot
    prompt: |
      Aplikasi Laravel dipindah ke container (`app`), PostgreSQL juga di container (`db`). `php artisan migrate` di `app`
      gagal. `.env` disalin dari laptop. Tulis perbaikan (perintah dan nilai env, satu per baris) supaya `app` bisa konek
      ke `db` dengan benar dan aman, lalu verifikasi.
    context: |
      $ docker ps --format '{{.Names}}  {{.Networks}}  {{.Ports}}'
      app  bridge
      db   bridge   0.0.0.0:15432->5432/tcp
      $ grep ^DB_ .env
      DB_HOST=127.0.0.1
      DB_PORT=15432
      $ docker exec app php artisan migrate
      SQLSTATE[08006] [7] connection to server at "127.0.0.1", port 15432 failed: Connection refused
    checklist:
      - id: network
        label: Menaruh app dan db di user-defined network yang sama
        required: true
        patterns: ['docker\s+network\s+create\s+\S+', 'docker\s+network\s+connect\s+\S+\s+(app|db)\b', '--network[= ][a-z][\w-]*']
      - id: host
        label: DB_HOST = nama container/service (db)
        required: true
        patterns: ['^db_host\s*=\s*(db|postgres|pgsql|database)\s*$']
      - id: port
        label: DB_PORT = port container 5432 (bukan port host)
        required: true
        patterns: ['^db_port\s*=\s*5432\s*$']
      - id: unpublish
        label: Tidak mem-publish 5432 ke semua interface (hapus -p atau bind 127.0.0.1)
        patterns: ['-p\s+127\.0\.0\.1:\d+:5432\b', 'tanpa\s+-p', 'hapus\s+.*-p', 'tidak\s+(perlu\s+)?(di-?)?publish', 'docker\s+(rm|stop)\s+.*\bdb\b']
      - id: verify
        label: Verifikasi dari dalam app (getent/nc/pg_isready/artisan)
        patterns: ['docker\s+exec\s+app\s+.*(getent|nc\s+-\S*z|pg_isready|artisan\s+(migrate|db:show))']
      - id: no-localhost
        label: Tidak tetap memakai 127.0.0.1/localhost sebagai DB_HOST
        forbid: true
        patterns: ['^db_host\s*=\s*(127\.0\.0\.1|localhost)']
      - id: no-host-net
        label: Tidak "memperbaiki" dengan --network host
        forbid: true
        patterns: ['--network[= ]host', '--net[= ]host']
    pass_score: 0.6
    solution: |
      ```bash
      docker network create appnet
      docker network connect appnet db
      docker network connect appnet app
      # .env di container app (lalu recreate app, karena env tidak bisa diubah di tempat):
      DB_HOST=db
      DB_PORT=5432
      docker exec app getent hosts db
      docker exec app php artisan migrate --force
      # db tidak perlu di-publish untuk app; hapus -p atau bind ke 127.0.0.1 bila butuh akses dari host:
      # docker run ... -p 127.0.0.1:15432:5432 ...
      ```
      Tiga kesalahan sekaligus, dan semuanya direproduksi di lab:
      1. `127.0.0.1` di dalam `app` adalah `app` itu sendiri (`Connection refused`).
      2. `15432` adalah port **host**, sedangkan antar container yang dipakai port 5432 (lab: port host → refused,
         port container → berhasil).
      3. Bridge default tidak menyediakan DNS nama container (lab: `bad address`).

      Selain itu, `0.0.0.0:15432` membuka database ke semua interface host, dan di Linux melewati ufw (modul Linux
      Networking). Database yang hanya dipakai app tidak perlu di-publish sama sekali.
    tests:
      pass:
        - |
          docker network create appnet
          docker network connect appnet db
          docker network connect appnet app
          DB_HOST=db
          DB_PORT=5432
          docker exec app php artisan migrate --force
        - |
          docker run -d --name db2 --network appnet postgres:17-alpine
          DB_HOST=db
          DB_PORT=5432
          docker exec app nc -zv db 5432
      fail:
        - |
          DB_HOST=localhost
          DB_PORT=5432
          docker restart app
        - |
          docker run -d --name app --network host myapp
          DB_HOST=127.0.0.1
          DB_PORT=15432
        - |
          docker network create appnet
          DB_HOST=db
          DB_PORT=15432
    source: https://docs.docker.com/engine/network/drivers/bridge/

  - id: c02
    tier: C
    type: scenario
    objective: segmentation
    prompt: |
      Rancang network untuk stack di satu server: `nginx` (publik, port 80/443), `app` (php-fpm), `db` (PostgreSQL),
      `redis`. Hanya nginx yang boleh diakses dari luar; nginx tidak boleh bisa menjangkau db/redis; db dan redis
      tidak butuh internet. Tulis perintah `docker network create`/`docker run` yang relevan (boleh disingkat ke opsi
      network & port saja), satu per baris, lalu satu perintah verifikasi isolasi.
    checklist:
      - id: front
        label: Network front (biasa) untuk nginx & app
        patterns: ['docker\s+network\s+create\s+(?!.*--internal)\S*front']
      - id: back-internal
        label: Network back dibuat --internal
        required: true
        patterns: ['docker\s+network\s+create\s+(.*\s)?--internal\s+\S*back', 'docker\s+network\s+create\s+\S*back\S*\s+--internal']
      - id: nginx-publish
        label: Hanya nginx yang mem-publish 80/443
        required: true
        patterns: ['(-p|--publish)\s+\S*\b(80|443):(80|443)\b.*nginx', 'nginx.*(-p|--publish)\s+\S*\b(80|443):(80|443)\b']
      - id: app-both
        label: app terhubung ke front dan back
        required: true
        patterns: ['docker\s+network\s+connect\s+\S*(front|back)\S*\s+app\b', '--name\s+app\s+.*--network[= ]\S*front.*--network[= ]\S*back', '--name\s+app\s+.*--network[= ]\S*back.*--network[= ]\S*front']
      - id: data-back
        label: db/redis hanya di network back
        patterns: ['--name\s+(db|redis)\s+.*--network[= ]\S*back', '--network[= ]\S*back\S*\s+.*--name\s+(db|redis)\b']
      - id: verify
        label: Verifikasi isolasi (dari nginx ke db gagal)
        patterns: ['docker\s+exec\s+\S*nginx\S*\s+.*(nc|wget|curl|getent|nslookup|ping)\b']
      - id: no-publish-data
        label: Tidak mem-publish port database/redis
        forbid: true
        patterns: ['(-p|--publish)\s+\S*(5432|6379):(5432|6379)']
      - id: no-host
        label: Tidak memakai --network host
        forbid: true
        patterns: ['--network[= ]host', '--net[= ]host']
    pass_score: 0.6
    solution: |
      ```bash
      docker network create front
      docker network create --internal back
      docker run -d --name db    --network back postgres:17-alpine
      docker run -d --name redis --network back redis:7-alpine
      docker run -d --name app   --network front --network back myorg/app:1.4.2
      docker run -d --name nginx --network front -p 80:80 -p 443:443 myorg/nginx:1.4.2
      docker exec nginx getent hosts db || echo "nginx tidak bisa melihat db — sesuai desain"
      ```
      Hasil lab untuk pola ini:
      - Container yang hanya di `front` → `bad address 'dj-db'`, sedangkan `app` yang terhubung ke keduanya →
        `dj-db:6379 open`, dengan dua IP (satu per network).
      - Network `--internal` memblokir akses keluar (`bad address 'example.com'`).
      - Port yang di-publish pada container yang hanya di network internal tidak bisa diakses dari host (`000`).
        Jadi database tidak mungkin "tidak sengaja" terekspos.

      `app` tetap bisa memanggil API luar lewat `front`. Bila nginx dibobol, penyerang hanya melihat `app`, bukan
      database. Compose menulis desain yang sama secara deklaratif (`networks:` dengan `internal: true`, modul Compose).
    tests:
      pass:
        - |
          docker network create front
          docker network create --internal back
          docker run -d --name db --network back postgres:17-alpine
          docker run -d --name redis --network back redis:7-alpine
          docker run -d --name app --network front --network back myorg/app
          docker run -d --name nginx --network front -p 80:80 -p 443:443 nginx:1.30-alpine
          docker exec nginx getent hosts db
        - |
          docker network create back --internal
          docker network connect back app
          docker run -d --name nginx -p 443:443 --network front nginx:1.30-alpine
          docker exec nginx nc -zv db 5432
      fail:
        - |
          docker run -d --name db -p 5432:5432 postgres:17-alpine
          docker run -d --name nginx -p 80:80 nginx:1.30-alpine
        - |
          docker network create back
          docker run -d --name nginx --network host nginx:1.30-alpine
        - |
          docker network create --internal back
          docker run -d --name redis --network back -p 6379:6379 redis:7-alpine
    source: https://docs.docker.com/reference/cli/docker/network/create/#internal

  - id: c03
    tier: C
    type: scenario
    objective: troubleshoot
    prompt: |
      Container `worker` tiba-tiba gagal memanggil API pembayaran setelah seseorang merapikan network di server.
      Tulis langkah diagnosis dari dalam ke luar (satu perintah per baris), temukan penyebabnya, perbaiki tanpa membuka
      isolasi berlebihan, dan verifikasi.
    context: |
      $ docker logs --tail 1 worker
      cURL error 6: Could not resolve host: api.stripe.com
      $ docker inspect -f '{{range $k,$v := .NetworkSettings.Networks}}{{$k}} {{end}}' worker
      back
    checklist:
      - id: resolv
        label: Melihat DNS di container (cat /etc/resolv.conf)
        patterns: ['cat\s+/etc/resolv\.conf']
      - id: lookup
        label: Uji resolusi nama (nslookup/getent/dig)
        patterns: ['\b(nslookup|getent\s+hosts|dig)\b']
      - id: inspect
        label: Memeriksa properti network (Internal=true)
        required: true
        patterns: ['docker\s+network\s+inspect\s+.*\binternal\b', '\{\{\s*\.internal\s*\}\}', 'docker\s+network\s+inspect\s+back']
      - id: fix
        label: Menghubungkan worker ke network yang punya akses keluar (bukan membuat back jadi non-internal)
        required: true
        patterns: ['docker\s+network\s+connect\s+(?!back\b)\S+\s+worker\b']
      - id: verify
        label: Verifikasi dari dalam worker (wget/curl/nslookup)
        patterns: ['docker\s+exec\s+worker\s+.*(wget|curl|nslookup|getent)']
      - id: no-host-net
        label: Tidak memindahkan worker ke --network host / --privileged
        forbid: true
        patterns: ['--network[= ]host', '--net[= ]host', '--privileged']
    pass_score: 0.6
    solution: |
      ```bash
      docker exec worker cat /etc/resolv.conf            # nameserver 127.0.0.11
      docker exec worker nslookup api.stripe.com         # gagal
      docker network inspect -f '{{.Internal}}' back     # true
      docker network connect egress worker               # network biasa (non-internal) untuk akses keluar
      docker exec worker wget -q -O /dev/null https://api.stripe.com && echo ok
      ```
      Gejala `Could not resolve host` di container yang hanya terhubung ke network `--internal` direproduksi di lab
      (`bad address 'example.com'`): network internal tidak meneruskan DNS maupun trafik ke luar. Perbaikan yang tepat
      adalah memberi `worker` jalur keluar lewat network **kedua**, bukan mengubah `back` menjadi non-internal (yang akan
      membuka internet untuk db/redis juga), dan bukan `--network host` (menghapus isolasi sepenuhnya). Biasakan
      mendiagnosis berlapis: DNS di container → properti network → jalur keluar → API tujuan.
    tests:
      pass:
        - |
          docker exec worker cat /etc/resolv.conf
          docker exec worker nslookup api.stripe.com
          docker network inspect -f '{{.Internal}}' back
          docker network connect egress worker
          docker exec worker wget -qO- https://api.stripe.com
        - |
          docker network inspect back
          docker network create egress
          docker network connect egress worker
          docker exec worker getent hosts api.stripe.com
      fail:
        - |
          docker rm -f worker
          docker run -d --name worker --network host myorg/worker
        - |
          docker exec worker nslookup api.stripe.com
          docker network connect back worker
        - |
          docker restart worker
          docker logs worker
    source: https://docs.docker.com/reference/cli/docker/network/create/#internal

---

# Networking Container

Setiap container punya network stack sendiri: interface, IP, loopback, dan tabel routing. Docker menghubungkannya
lewat network virtual dan menyediakan DNS supaya container bisa saling memanggil dengan nama. Modul ini membahas jenis
network, DNS bawaan, perbedaan port container vs port host (sumber kebingungan nomor satu), akses ke host,
segmentasi untuk keamanan, dan cara troubleshooting koneksi.

> [!NOTE]
> Diuji di Docker Desktop (Windows), Docker Engine 29.4.1, dengan `alpine:3.20` (busybox `wget`, `nc`, `nslookup`) sebagai
> klien. Docker Desktop menjalankan Docker di VM, sehingga beberapa hal berbeda dari Engine Linux: `host.docker.internal`,
> akses ke loopback host, dan `--network host`. Perbedaan ditandai di setiap bagian.

## 1. Jenis network

| Driver / network | Isolasi | DNS nama container | Kegunaan |
|---|---|---|---|
| `bridge` **default** | Ya | **Tidak** (lab: `bad address`) | Warisan, `docker run` tanpa `--network` |
| User-defined `bridge` | Ya | **Ya** (127.0.0.11) | Default yang disarankan untuk aplikasi |
| `bridge --internal` | Ya, tanpa akses keluar | Ya (antar anggota) | Database, cache |
| `host` | Tidak (berbagi stack host) | — | Performa jaringan ekstrem, tool monitoring |
| `none` | Total (hanya `lo`) | — | Job yang tidak butuh jaringan |
| `overlay` | Antar host | Ya | Swarm |
| `macvlan`/`ipvlan` | IP di LAN fisik | — | Integrasi jaringan lama |

## 2. DNS bawaan

```bash
docker network create appnet
docker run -d --name db  --network appnet redis:7-alpine
docker run -d --name api --network appnet --network-alias backend myorg/api:1.0
docker run --rm --network appnet alpine:3.20 nslookup db         # 172.18.0.x
```

- Nama container dan alias di-resolve oleh `127.0.0.11`, sedangkan nama lain diteruskan ke DNS host.
- `--dns 1.1.1.1` di user-defined network menjadi **upstream**. `resolv.conf` tetap `127.0.0.11` (lab).
- Alias yang sama di beberapa container memberi beberapa IP (lab: 2 alamat), yaitu round-robin DNS sederhana.
- IP berubah saat container dibuat ulang (lab: `.3` → `.5`). Pakai nama, jangan IP.

## 3. Port: container vs host

```mermaid
flowchart LR
    U[Browser di host] -- "localhost:8080" --> H[(Host)]
    H -- "-p 8080:80" --> N[nginx :80]
    N -- "app:9000" --> A[app :9000]
    A -- "db:5432" --> D[db :5432]
    A -. "localhost:5432 ✗<br/>(= app sendiri)" .-> A
```

| Dari | Ke | Alamat yang benar |
|---|---|---|
| Host | Container yang di-publish `-p 15432:5432` | `127.0.0.1:15432` |
| Container | Container lain di network yang sama | `db:5432` (nama + **port container**) |
| Container | Dirinya sendiri | `localhost:<port>` |
| Container | Service di host (Desktop) | `host.docker.internal:<port>` |

Hasil lab:
- `wget localhost:80` dari container alpine → `Connection refused`.
- `wget dj-a:8085` (port host) → refused, `wget dj-a:80` → nginx.

Antar container tidak butuh `-p` sama sekali. Publish hanya untuk yang harus dijangkau dari luar host.

## 4. Akses ke host

```bash
# Docker Desktop: tersedia otomatis
docker run --rm alpine:3.20 wget -qO- http://host.docker.internal:8098/
# Docker Engine Linux: tambahkan sendiri
docker run --rm --add-host=host.docker.internal:host-gateway alpine:3.20 cat /etc/hosts
```

| | Docker Desktop (lab) | Docker Engine Linux (dokumentasi) |
|---|---|---|
| `host.docker.internal` | Otomatis (`192.168.65.254`) | Perlu `--add-host ...:host-gateway` / `extra_hosts` |
| Service host yang listen di `127.0.0.1` | **Terjangkau** | Tidak terjangkau (perlu listen di IP bridge/0.0.0.0) |

> [!WARNING]
> Konfigurasi "jalan di laptop" belum tentu jalan di server. Di Docker Desktop, container bisa menjangkau database Laragon
> yang hanya listen di `127.0.0.1` lewat `host.docker.internal` (lab). Di server Linux, cara yang sama gagal sampai
> service host di-bind ke IP bridge, dan saat itu firewall wajib membatasinya. Lebih baik jalankan dependency di container
> di network yang sama.

## 5. Segmentasi

```bash
docker network create front
docker network create --internal back
docker run -d --name db    --network back  postgres:17-alpine
docker run -d --name app   --network front --network back myorg/app:1.4.2
docker run -d --name nginx --network front -p 80:80 -p 443:443 myorg/nginx:1.4.2
```

| Hasil lab | |
|---|---|
| Container di `front` saja → `db` | `bad address 'dj-db'` |
| `app` (front + back) → `db` | `open`, punya 2 IP |
| Network `--internal` → internet | `bad address 'example.com'` |
| Publish port pada container yang hanya di network internal | Tidak terjangkau dari host (`000`) |

> [!INSIGHT]
> `--internal` memblokir dua arah sekaligus: tidak ada jalan keluar (termasuk DNS untuk nama luar) dan port publishing
> tidak efektif. Kombinasi ini membuat database di network internal tidak bisa tidak sengaja terekspos ke internet,
> walau seseorang menambahkan `-p 5432:5432` (lab: `000`). Hal itu adalah risiko nyata di Linux, karena port yang
> di-publish Docker melewati ufw.
> Sumber: [docker network create — --internal](https://docs.docker.com/reference/cli/docker/network/create/#internal)

## 6. Mengelola network

```bash
docker network ls
docker network inspect -f '{{.Driver}} {{.Internal}} {{range .IPAM.Config}}{{.Subnet}}{{end}}' appnet
docker network connect --alias api appnet web
docker network disconnect appnet web
docker ps -a --filter network=appnet
docker ps --filter publish=8080
docker network rm appnet           # gagal bila masih ada endpoint aktif
```

`docker network prune` menghapus semua network yang tidak dipakai di mesin itu. Hal ini aman untuk network, tetapi tetap
ikut menghapus network milik project lain yang sedang dimatikan.

## 7. Troubleshooting koneksi antar container

```mermaid
flowchart TD
    E[Tidak bisa konek] --> N{Satu network?<br/>docker inspect .NetworkSettings.Networks}
    N -- tidak --> C[docker network connect]
    N -- ya --> D{Nama resolve?<br/>getent hosts db}
    D -- bad address --> B[bridge default? beda network?<br/>typo nama?]
    D -- ya --> P{Port container benar?<br/>nc -zv db 5432}
    P -- refused --> L[Service listen di 127.0.0.1 di dalam container?<br/>salah port? belum siap?]
    P -- open --> A[Masalah aplikasi: auth, TLS, pg_hba]
    E --> X{Ke internet?}
    X -- bad address --> I[Network --internal? DNS host?]
```

| Pesan | Arti |
|---|---|
| `bad address 'db'` / `Could not resolve host` | DNS: beda network, bridge default, network internal, atau typo |
| `Connection refused` ke `127.0.0.1` | Memanggil diri sendiri |
| `Connection refused` ke `db:15432` | Memakai port host, bukan port container |
| `Connection refused` ke `db:5432` | Service belum siap, atau listen di localhost container (mis. Redis `bind 127.0.0.1`) |

## 8. Ringkasan kebiasaan aman

| Kebiasaan | Kenapa |
|---|---|
| Selalu user-defined network | DNS nama container (bridge default tidak punya) |
| Panggil `nama:port-container`, jangan IP/localhost/port host | IP berubah, localhost = diri sendiri |
| Publish hanya yang perlu, bind `127.0.0.1` untuk akses lokal | Database tidak terekspos |
| Segmentasi front/back, data di network `--internal` | Proxy yang dibobol tidak melihat database |
| Jalur egress eksplisit untuk service yang perlu internet | Isolasi tetap terjaga |
| Jangan `--network host` sebagai "perbaikan" | Menghapus isolasi |
| Uji konfigurasi akses-ke-host di Linux, bukan hanya Desktop | `host.docker.internal` berbeda perilaku |
| Migrasikan `--link`/`links:` lama | Legacy |
