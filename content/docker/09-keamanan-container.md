---
format: 1
id: docker-container-security
course:
  slug: docker
  title: Docker
  description: Container dari nol sampai production — CLI, image, storage, network, Compose, keamanan, dan operasional.
title: Keamanan Container
summary: Model ancaman container, user non-root, capabilities, --privileged, docker.sock, seccomp, no-new-privileges, read-only rootfs, batas resource, dan hardening service di Compose — semua dengan bukti lab.
order: 9
version: 1
status: verified
updated: '2026-11-23'
tested_on:
  - Docker Desktop (Windows) — Docker Engine 29.4.1 (VM Linux sebagai "host" container)
  - Image alpine:3.20, docker:cli, nginx:1.27-alpine, nginxinc/nginx-unprivileged:1.27-alpine
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: threat-model
    text: Memahami batas isolasi container dan apa yang terjadi bila dibobol
  - id: user
    text: Menjalankan container sebagai user non-root
  - id: capabilities
    text: Mengurangi Linux capabilities (cap_drop/cap_add) dan memahami --privileged
  - id: daemon-access
    text: Memahami risiko grup docker dan docker.sock
  - id: syscall
    text: Memahami seccomp dan no-new-privileges
  - id: limits
    text: Membatasi resource (pids, memori) dan filesystem (read-only)
  - id: hardening
    text: Menerapkan hardening service di docker run dan Compose
  - id: audit
    text: Mengaudit container yang berjalan untuk konfigurasi berbahaya
sources:
  - title: Docker Docs — Docker Engine security
    url: https://docs.docker.com/engine/security/
  - title: Docker Docs — Docker daemon attack surface (grup docker)
    url: https://docs.docker.com/engine/security/#docker-daemon-attack-surface
  - title: Docker Docs — Runtime privilege and Linux capabilities
    url: https://docs.docker.com/engine/containers/run/#runtime-privilege-and-linux-capabilities
  - title: Docker Docs — Seccomp security profiles
    url: https://docs.docker.com/engine/security/seccomp/
  - title: Docker Docs — Isolate containers with a user namespace
    url: https://docs.docker.com/engine/security/userns-remap/
  - title: Docker Docs — Rootless mode
    url: https://docs.docker.com/engine/security/rootless/
  - title: docker container run — --security-opt, --pids-limit, --read-only, --sysctl
    url: https://docs.docker.com/reference/cli/docker/container/run/
  - title: capabilities(7)
    url: https://man7.org/linux/man-pages/man7/capabilities.7.html
  - title: OWASP Docker Security Cheat Sheet
    url: https://cheatsheetseries.owasp.org/cheatsheets/Docker_Security_Cheat_Sheet.html
  - title: CIS Docker Benchmark
    url: https://www.cisecurity.org/benchmark/docker
  - title: nginxinc/docker-nginx-unprivileged
    url: https://github.com/nginxinc/docker-nginx-unprivileged
  - title: Linux kernel — ping_group_range / ICMP datagram sockets (icmp(7))
    url: https://man7.org/linux/man-pages/man7/icmp.7.html
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: command
    objective: user
    prompt: Jalankan `alpine:3.20` sekali pakai sebagai **UID/GID 10001**, lalu jalankan `id`.
    answers: ['docker run --rm --user 10001:10001 alpine:3.20 id']
    patterns: ['^docker (container )?run (--rm (--user|-u)[= ]?10001(:10001)?|(--user|-u)[= ]?10001(:10001)? --rm) alpine:3\.20 id$']
    tests:
      pass: ['docker run --rm -u 10001:10001 alpine:3.20 id', 'docker run -u 10001 --rm alpine:3.20 id']
      fail: ['docker run --rm --uid 10001 alpine:3.20 id', 'docker run --rm -e USER=10001 alpine:3.20 id', 'docker run --rm alpine:3.20 su 10001 id']
    explanation: 'UID tidak harus ada di `/etc/passwd` image. Di lab, nginx-unprivileged dengan `--user 10001:10001` → `uid=10001 gid=10001`. UID tinggi (≥10000) mengurangi risiko bentrok dengan user asli di host bila terjadi breakout atau bind mount.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#user

  - id: a02
    tier: A
    type: text
    objective: user
    prompt: Tanpa instruksi `USER` di Dockerfile dan tanpa `--user`, proses di container berjalan sebagai user apa?
    answers: ['root', 'uid 0', '0']
    tests:
      fail: ['nobody', 'www-data', 'docker', 'user biasa']
    explanation: 'Di lab, `docker run alpine id` → `uid=0(root)`. Root di container dibatasi capabilities, seccomp, dan namespace, tetapi tetap UID 0 milik kernel yang sama dengan host. Satu celah kernel atau runtime, atau satu salah konfigurasi (bind mount `/`, `--privileged`), mengubahnya menjadi root host.'
    source: https://docs.docker.com/engine/security/

  - id: a03
    tier: A
    type: command
    objective: capabilities
    prompt: Jalankan image `myapp` (sekali pakai) dengan **semua** capabilities dibuang, kecuali `NET_BIND_SERVICE`.
    answers: ['docker run --rm --cap-drop ALL --cap-add NET_BIND_SERVICE myapp']
    patterns: ['^docker (container )?run (?=.*--cap-drop[= ]ALL( |$))(?=.*--cap-add[= ](CAP_)?NET_BIND_SERVICE( |$))((--rm|-d|--cap-drop[= ]ALL|--cap-add[= ](CAP_)?NET_BIND_SERVICE) )+myapp$']
    tests:
      pass: ['docker run --rm --cap-add NET_BIND_SERVICE --cap-drop ALL myapp', 'docker run --rm --cap-drop=ALL --cap-add=CAP_NET_BIND_SERVICE myapp']
      fail: ['docker run --rm --cap-drop NET_BIND_SERVICE myapp', 'docker run --rm --privileged myapp', 'docker run --rm --cap-add ALL myapp']
    explanation: 'Di lab: default `CapEff: 00000000a80425fb` (14 capability, termasuk CHOWN, SETUID, NET_RAW), `--cap-drop ALL` → `0000000000000000`, dan dengan `--cap-add NET_BIND_SERVICE` saja → `0000000000000400`. Mulai dari nol, lalu tambahkan hanya yang terbukti dibutuhkan.'
    source: https://docs.docker.com/engine/containers/run/#runtime-privilege-and-linux-capabilities

  - id: a04
    tier: A
    type: command
    objective: syscall
    prompt: Jalankan image `myapp` (sekali pakai) dengan opsi yang mencegah proses **memperoleh hak lebih tinggi** lewat binary setuid.
    answers: ['docker run --rm --security-opt no-new-privileges:true myapp']
    patterns: ['^docker (container )?run (--rm |-d )*--security-opt[= ]no-new-privileges(:true|=true)?( --rm| -d)* myapp$']
    tests:
      pass: ['docker run --rm --security-opt=no-new-privileges myapp']
      fail: ['docker run --rm --no-new-privileges myapp', 'docker run --rm --security-opt seccomp=unconfined myapp', 'docker run --rm --cap-drop ALL myapp']
    explanation: 'Flag kernel `no_new_privs` membuat `execve` tidak lagi menaikkan hak dari bit setuid/setgid atau file capability. Di lab, `/proc/self/status` → `NoNewPrivs: 1`, sedangkan default `0`. Pada lingkungan Docker Desktop di lab, eskalasi setuid sendiri tidak bisa direproduksi, jadi efek pemblokirannya di sini berdasarkan dokumentasi kernel dan Docker.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#security-opt

  - id: a05
    tier: A
    type: command
    objective: limits
    prompt: Jalankan image `myapp` di background dengan batas maksimal **100 proses** di container (melindungi dari fork bomb).
    answers: ['docker run -d --pids-limit 100 myapp']
    patterns: ['^docker (container )?run (-d |--detach )?--pids-limit[= ]100( -d| --detach)? myapp$']
    tests:
      pass: ['docker run --pids-limit=100 -d myapp']
      fail: ['docker run -d --max-procs 100 myapp', 'docker run -d --memory 100 myapp', 'docker run -d --ulimit nofile=100 myapp']
    explanation: 'Di lab, `--pids-limit 30` lalu mencoba 60 proses → `sh: can''t fork: Resource temporarily unavailable`. Tanpa batas, satu container yang dibobol (atau bug loop) bisa menghabiskan tabel proses **seluruh host**. Di Compose: `pids_limit: 100` atau `deploy.resources.limits.pids`.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#pids-limit

  - id: a06
    tier: A
    type: text
    objective: capabilities
    prompt: Opsi `docker run` apa yang memberi container **semua** capability dan akses ke **semua device** host, sehingga hampir semua isolasi hilang?
    answers: ['--privileged', 'privileged']
    tests:
      fail: ['--root', '--admin', '--host', '--unconfined']
    explanation: 'Di lab: device di `/dev` 15 → **157**, `CapEff` → `000001ffffffffff`, dan `mount -t tmpfs` yang tadinya `permission denied` menjadi `mount-ok`. Container privileged bisa me-mount disk host dan memuat modul kernel. Jangan pernah memakainya untuk aplikasi biasa.'
    source: https://docs.docker.com/engine/containers/run/#runtime-privilege-and-linux-capabilities

  - id: a07
    tier: A
    type: text
    objective: daemon-access
    prompt: Di host Linux, user anggota grup `docker` secara efektif setara dengan user apa?
    answers: ['root']
    tests:
      fail: ['user biasa', 'docker', 'nobody', 'www-data']
    explanation: 'Dokumentasi Docker menyatakan hanya user tepercaya yang boleh mengontrol daemon. Di lab, `docker run -v /:/host alpine head -1 /host/etc/shadow` → `root:*:20549...`, sehingga filesystem root host terbaca dari container biasa. Di Docker Desktop, "host" yang dimaksud adalah VM Linux. Di server Linux, itu adalah server-nya sendiri.'
    source: https://docs.docker.com/engine/security/#docker-daemon-attack-surface

  - id: a08
    tier: A
    type: text
    objective: daemon-access
    prompt: Path Unix socket default yang dipakai Docker CLI untuk berbicara ke daemon di Linux?
    answers: ['/var/run/docker.sock', '/run/docker.sock']
    tests:
      fail: ['/var/lib/docker', '/run/containerd/containerd.sock', '/etc/docker', '/var/run/docker.pid']
    explanation: 'Siapa pun yang bisa menulis ke socket ini mengontrol daemon sepenuhnya. Me-mount-nya ke container (pola umum untuk Traefik, Portainer, runner CI) sama dengan memberi container itu root host (soal tier B).'
    source: https://docs.docker.com/engine/security/#docker-daemon-attack-surface

  - id: a09
    tier: A
    type: text
    objective: syscall
    prompt: Mekanisme kernel apa yang dipakai profil default Docker untuk **memfilter system call** berbahaya dari container?
    answers: ['seccomp']
    tests:
      fail: ['apparmor', 'selinux', 'capabilities', 'cgroups']
    explanation: 'Profil seccomp default memblokir puluhan syscall (mis. pembuatan user namespace, `kexec_load`, `mount` tanpa capability). Di lab, `unshare -U -r id` di container default → `Operation not permitted`, sedangkan dengan `--security-opt seccomp=unconfined` → berhasil (`uid=0`). AppArmor/SELinux membatasi akses file/resource, dan bekerja berlapis dengan seccomp.'
    source: https://docs.docker.com/engine/security/seccomp/

  - id: a10
    tier: A
    type: command
    objective: audit
    prompt: Tampilkan **capability efektif** proses utama di container `web` yang sedang berjalan, dibaca dari `/proc`.
    answers: ['docker exec web grep CapEff /proc/1/status']
    patterns: ['^docker (container )?exec web grep (-i )?CapEff /proc/(1|self)/status$', '^docker (container )?exec web cat /proc/(1|self)/status$']
    tests:
      pass: ['docker exec web grep CapEff /proc/self/status', 'docker exec web cat /proc/1/status']
      fail: ['docker inspect web', 'docker exec web capsh', 'docker top web']
    explanation: 'Nilai heksadesimal adalah bitmask capability. `0000000000000000` = tidak ada, `000001ffffffffff` = semua (privileged). Decode dengan `capsh --decode=00000000a80425fb` di host. Field lain di file yang sama: `NoNewPrivs` dan `Seccomp` (2 = filter aktif).'
    source: https://man7.org/linux/man-pages/man7/capabilities.7.html

  - id: a11
    tier: A
    type: text
    objective: user
    prompt: Image resmi dari tim nginx yang berjalan sebagai **non-root** secara default (repository Docker Hub)?
    answers: ['nginxinc/nginx-unprivileged']
    patterns: ['^nginxinc/nginx-unprivileged(:\S+)?$']
    tests:
      pass: ['nginxinc/nginx-unprivileged:1.27-alpine']
      fail: ['nginx:alpine', 'nginx-rootless', 'bitnami/nginx-root']
    explanation: 'Di lab, `nginx:1.27-alpine` dengan `--read-only --user 10001 --cap-drop ALL` → `configuration file ... test failed` (butuh menulis `/var/cache/nginx` dan PID sebagai root). `nginxinc/nginx-unprivileged:1.27-alpine` dengan opsi yang sama → `test is successful`. Image ini listen di **8080** (soal berikutnya).'
    source: https://github.com/nginxinc/docker-nginx-unprivileged

  - id: a12
    tier: A
    type: text
    objective: user
    prompt: Image `nginx-unprivileged` secara default listen di port berapa di dalam container?
    answers: ['8080']
    tests:
      fail: ['80', '443', '8443', '9000']
    explanation: 'Di image itu, nginx berjalan sebagai user `nginx` (UID 101) dan dikonfigurasi listen di 8080. Publish-nya dengan `-p 80:8080`. Di Docker modern sebenarnya non-root pun bisa bind port 80 di container (soal tier B), tetapi image ini tetap memakai 8080 supaya kompatibel dengan runtime lain (Kubernetes dengan setelan ketat, Podman).'
    source: https://github.com/nginxinc/docker-nginx-unprivileged

  - id: a13
    tier: A
    type: text
    objective: capabilities
    prompt: Nama parameter sysctl yang menentukan port terkecil yang boleh di-bind user non-root (di container Docker modern bernilai 0)?
    answers: ['net.ipv4.ip_unprivileged_port_start']
    tests:
      fail: ['net.ipv4.ip_forward', 'net.core.somaxconn', 'net.ipv4.ip_local_port_range']
    explanation: 'Di lab: `cat /proc/sys/net/ipv4/ip_unprivileged_port_start` → `0`, dan `nc -l -p 80` sebagai UID 1000 → `LISTEN :::80`. Dengan `--sysctl net.ipv4.ip_unprivileged_port_start=1024` → `nc: bind: Permission denied`. Artinya, capability `NET_BIND_SERVICE` biasanya tidak dibutuhkan lagi di container.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#sysctl

  - id: a14
    tier: A
    type: text
    objective: hardening
    prompt: Tulis key Compose (beserta nilainya) untuk membuang **semua** capabilities dari sebuah service.
    answers: ['cap_drop: [ALL]', 'cap_drop: [all]']
    patterns: ['^cap_drop:\s*\[?\s*"?all"?\s*\]?$']
    tests:
      pass: ['cap_drop: ["ALL"]', 'cap_drop: ALL']
      fail: ['cap_add: [ALL]', 'drop_caps: all', 'capabilities: none', 'cap_drop: []']
    explanation: 'Padanan `--cap-drop ALL`. Pasangannya `cap_add: [NET_BIND_SERVICE]` bila memang perlu. Key Compose keamanan lainnya: `user`, `read_only`, `security_opt`, `pids_limit`, `tmpfs`.'
    source: https://docs.docker.com/reference/compose-file/services/#cap_add

  - id: a15
    tier: A
    type: text
    objective: threat-model
    prompt: Mode instalasi Docker di mana **daemon** dan container berjalan sebagai user biasa (bukan root) di host disebut apa?
    answers: ['rootless', 'rootless mode', 'docker rootless']
    tests:
      fail: ['privileged', 'swarm', 'userless', 'root mode']
    explanation: 'Di mode rootless, breakout dari container hanya menghasilkan hak user biasa, bukan root host. Keterbatasannya ada pada beberapa fitur jaringan/storage. Alternatif yang lebih ringan adalah `userns-remap` (root container dipetakan ke UID tidak istimewa di host). Tidak diuji di lab, karena Docker Desktop memakai VM.'
    source: https://docs.docker.com/engine/security/rootless/

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: capabilities
    prompt: Container berjalan sebagai **root** dengan `--cap-drop ALL`, lalu menjalankan `chown 1000 /tmp/f`. Apa hasilnya?
    answers: ['Operation not permitted']
    patterns: ['(operation not permitted|ditolak|gagal|tidak (bisa|diizinkan|boleh)|eperm|denied)']
    tests:
      pass: ['gagal, operation not permitted', 'ditolak']
      fail: ['berhasil', 'sukses', 'ok']
    explanation: 'Di lab: `chown: /tmp/f: Operation not permitted` (rc=1), sedangkan dengan capability default → `chown-ok`. "Root" tanpa capability tidak bisa banyak hal. Inilah sebabnya `cap_drop: [ALL]` efektif bahkan untuk image yang terpaksa berjalan sebagai root. Sebagian entrypoint (mis. postgres yang melakukan chown data dir lalu pindah user) justru butuh `CHOWN`/`SETUID`/`SETGID`, jadi uji dulu.'
    source: https://man7.org/linux/man-pages/man7/capabilities.7.html

  - id: b02
    tier: B
    type: text
    objective: capabilities
    prompt: Nilai `CapEff` di `/proc/1/status` untuk container yang dijalankan dengan `--cap-drop ALL`?
    answers: ['0000000000000000']
    tests:
      fail: ['00000000a80425fb', '000001ffffffffff', '0000000000000400']
    explanation: 'Di lab: default `00000000a80425fb`, `--cap-drop ALL` → `0000000000000000`, `ALL` + `NET_BIND_SERVICE` → `0000000000000400` (bit 10), dan `--privileged` → `000001ffffffffff`. Membaca nilai ini adalah cara cepat memverifikasi bahwa hardening di Compose benar-benar diterapkan.'
    source: https://man7.org/linux/man-pages/man7/capabilities.7.html

  - id: b03
    tier: B
    type: text
    objective: capabilities
    prompt: Dengan `--privileged`, apakah root di container bisa menjalankan `mount` (mis. tmpfs atau disk host)? (ya/tidak)
    answers: ['ya', 'yes', 'bisa']
    tests:
      fail: ['tidak', 'no']
    explanation: 'Di lab: default → `mount: permission denied (are you root?)` walau sebagai root, `--privileged` → `mount-ok`. Dengan 157 device di `/dev` terlihat, container privileged bisa me-mount disk host dan membaca/mengubah apa pun di dalamnya. Ini breakout paling sederhana yang ada.'
    source: https://docs.docker.com/engine/containers/run/#runtime-privilege-and-linux-capabilities

  - id: b04
    tier: B
    type: text
    objective: daemon-access
    prompt: 'Container dengan `-v /var/run/docker.sock:/var/run/docker.sock` (image `docker:cli`). Bisakah ia menjalankan container **baru** yang `--privileged`? (ya/tidak)'
    answers: ['ya', 'yes', 'bisa']
    tests:
      fail: ['tidak', 'no']
    explanation: 'Di lab: dari dalam container itu, `docker run --rm --privileged alpine ...` → `nested-privileged-ok`, `id -u` → `0`. Container itu juga melihat container lain (`dj-victim`) dan bisa `docker exec` ke dalamnya. Mount `docker.sock` = root di host, ditambah kontrol atas semua container. Pakai hanya untuk tool yang benar-benar tepercaya, atau pakai proxy socket yang membatasi API.'
    source: https://docs.docker.com/engine/security/#docker-daemon-attack-surface

  - id: b05
    tier: B
    type: text
    objective: daemon-access
    prompt: Bila `docker.sock` di-mount **read-only** (`:/var/run/docker.sock:ro`), apakah container itu tetap bisa mengontrol daemon (mis. menjalankan container)? (ya/tidak)
    answers: ['ya', 'yes', 'bisa']
    tests:
      fail: ['tidak', 'no']
    explanation: 'Di lab, lewat socket `:ro`, `docker run --rm alpine echo ...` → `started a container through a :ro socket`. `:ro` hanya mencegah file socket itu dihapus/diganti, sementara koneksi ke socket tetap memungkinkan semua panggilan API. Mitos ":ro membuatnya aman" sering ditemukan di tutorial Traefik/monitoring.'
    source: https://docs.docker.com/engine/security/#docker-daemon-attack-surface

  - id: b06
    tier: B
    type: text
    objective: capabilities
    prompt: Di Docker modern (default), bisakah proses **UID 1000** di dalam container bind port **80**? (ya/tidak)
    answers: ['ya', 'yes', 'bisa']
    tests:
      fail: ['tidak', 'no']
    explanation: 'Di lab: `ip_unprivileged_port_start` = `0`, dan `nc -l -p 80` sebagai UID 1000 → `LISTEN`. Sejak Docker 20.10, sysctl ini di-set 0 di network namespace container. Jadi alasan "aplikasi harus root agar bisa listen di port 80" tidak berlaku lagi di Docker.'
    source: https://docs.docker.com/engine/release-notes/20.10/

  - id: b07
    tier: B
    type: text
    objective: capabilities
    prompt: Container Alpine dijalankan dengan `--cap-drop NET_RAW`. Apakah `ping -c1 127.0.0.1` (busybox) masih berhasil? (ya/tidak)
    answers: ['ya', 'yes', 'masih']
    tests:
      fail: ['tidak', 'no']
    explanation: 'Di lab, ping tetap berhasil (`round-trip min/avg/max = 1.728...`). Busybox ping memakai **ICMP datagram socket** (`SOCK_DGRAM`), yang diizinkan lewat `net.ipv4.ping_group_range` tanpa `NET_RAW`. `NET_RAW` tetap layak dibuang karena mencegah raw socket untuk spoofing/sniffing, tetapi jangan memakai "ping masih jalan" sebagai bukti capability belum dibuang.'
    source: https://man7.org/linux/man-pages/man7/icmp.7.html

  - id: b08
    tier: B
    type: text
    objective: syscall
    prompt: 'Di container default, `unshare -U -r id` gagal dengan `Operation not permitted`, tetapi berhasil dengan `--security-opt seccomp=unconfined`. Mekanisme apa yang memblokirnya?'
    answers: ['seccomp']
    tests:
      fail: ['apparmor', 'capabilities', 'cgroups', 'selinux']
    explanation: 'Profil seccomp default memblokir pembuatan user namespace dari dalam container (`unshare(CLONE_NEWUSER)`), karena itu adalah jalur umum eksploit kernel. Di lab, dengan `seccomp=unconfined` → `uid=0(root)` di namespace baru. Menonaktifkan seccomp untuk "memperbaiki" error aplikasi membuka permukaan serangan kernel. Buat profil khusus yang mengizinkan syscall spesifik saja.'
    source: https://docs.docker.com/engine/security/seccomp/

  - id: b09
    tier: B
    type: text
    objective: limits
    prompt: Container dengan `--pids-limit 30` mencoba membuat 60 proses. Pesan error apa yang muncul?
    answers: ['can''t fork: Resource temporarily unavailable']
    patterns: ['(can''?t fork|resource temporarily unavailable|eagain|fork (gagal|failed))']
    tests:
      pass: ['resource temporarily unavailable', 'cant fork']
      fail: ['out of memory', 'killed', 'oom', 'permission denied']
    explanation: 'Di lab: `sh: can''t fork: Resource temporarily unavailable`, dan container tetap hidup dengan sedikit proses. Pesan ini sama dengan saat batas `ulimit -u` tercapai di Linux biasa. Pasang `pids_limit` untuk setiap service, dengan nilai cukup longgar untuk worker php-fpm/queue.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#pids-limit

  - id: b10
    tier: B
    type: text
    objective: user
    prompt: Apakah image `nginx:1.27-alpine` (resmi) bisa berjalan dengan `--read-only --tmpfs /tmp --user 10001:10001 --cap-drop ALL`, tanpa penyesuaian lain? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab: `nginx -t` → `test failed`. Image resmi berasumsi berjalan sebagai root (membuat `/var/cache/nginx/*`, menulis PID di `/run`, listen 80 lalu turun ke user `nginx`). Pilihannya: image `nginx-unprivileged` (lab: `test is successful`), atau mount tmpfs/volume ke path yang perlu ditulis dan ubah config. Hardening sering butuh image yang dirancang untuk itu.'
    source: https://github.com/nginxinc/docker-nginx-unprivileged

  - id: b11
    tier: B
    type: text
    objective: hardening
    prompt: 'Nilai yang ditulis di `security_opt` Compose untuk mengaktifkan no-new-privileges?'
    answers: ['no-new-privileges:true', 'no-new-privileges']
    patterns: ['^"?no-new-privileges(:true|=true)?"?$']
    tests:
      pass: ['"no-new-privileges:true"']
      fail: ['seccomp:unconfined', 'apparmor:unconfined', 'privileged:false', 'no-root:true']
    explanation: 'Contoh: `security_opt: ["no-new-privileges:true"]`. Bisa juga diaktifkan untuk semua container lewat `daemon.json` (`"no-new-privileges": true`). Opsi `seccomp:unconfined` dan `apparmor:unconfined` justru **mematikan** proteksi.'
    source: https://docs.docker.com/reference/compose-file/services/#security_opt

  - id: b12
    tier: B
    type: text
    objective: syscall
    prompt: Dengan `--security-opt no-new-privileges:true`, berapa nilai `NoNewPrivs` di `/proc/self/status` proses container?
    answers: ['1']
    tests:
      fail: ['0', '2', 'true']
    explanation: 'Di lab: default `NoNewPrivs: 0`, dengan opsi → `NoNewPrivs: 1`. Flag ini diwariskan ke semua proses anak dan tidak bisa dimatikan lagi dari dalam. Membaca field ini adalah cara memverifikasi hardening di container yang sedang berjalan.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#security-opt

  - id: b13
    tier: B
    type: text
    objective: threat-model
    prompt: Dengan `userns-remap` di daemon, UID 0 (root) di dalam container dipetakan ke apa di host?
    answers: ['UID tidak istimewa dari rentang subuid (mis. 100000)']
    patterns: ['(uid (tinggi|biasa|tidak istimewa|non-?root|lain)|user biasa|unprivileged|subuid|100000|non-?root|bukan root)']
    tests:
      pass: ['uid 100000', 'user biasa di host']
      fail: ['root', 'uid 0', 'tetap root']
    explanation: 'Dengan `userns-remap`, root container berjalan sebagai, mis., UID 100000 di host (dari `/etc/subuid`). Breakout menghasilkan user tanpa hak, dan file di bind mount dimiliki UID tinggi itu. Fitur ini harus diaktifkan di daemon, dan tidak diuji di lab.'
    source: https://docs.docker.com/engine/security/userns-remap/

  - id: b14
    tier: B
    type: command
    objective: hardening
    prompt: |
      Jalankan `myapp` di background dengan hardening lengkap: rootfs read-only + tmpfs `/tmp`, user `10001:10001`,
      semua capability dibuang, no-new-privileges, maksimal 100 proses, dan memori 256 MB.
    answers: ['docker run -d --read-only --tmpfs /tmp --user 10001:10001 --cap-drop ALL --security-opt no-new-privileges:true --pids-limit 100 --memory 256m myapp']
    patterns: ['^docker (container )?run (?=.*--read-only( |$))(?=.*--tmpfs[= ]/tmp(:\S+)?( |$))(?=.*(--user|-u)[= ]?10001:10001( |$))(?=.*--cap-drop[= ]ALL( |$))(?=.*--security-opt[= ]no-new-privileges(:true)?( |$))(?=.*--pids-limit[= ]100( |$))(?=.*(--memory|-m)[= ]?256[mM]( |$))((-d|--detach|--read-only|--tmpfs[= ]\S+|(--user|-u)[= ]?\S+|--cap-drop[= ]ALL|--security-opt[= ]\S+|--pids-limit[= ]100|(--memory|-m)[= ]?256[mM]) )+myapp$']
    tests:
      pass: ['docker run -d --user 10001:10001 --read-only --tmpfs /tmp --cap-drop=ALL --security-opt=no-new-privileges --pids-limit 100 -m 256m myapp']
      fail:
        - 'docker run -d --read-only --user 10001:10001 --cap-drop ALL --security-opt no-new-privileges:true --pids-limit 100 --memory 256m myapp'
        - 'docker run -d --read-only --tmpfs /tmp --cap-drop ALL --security-opt no-new-privileges:true --pids-limit 100 --memory 256m myapp'
        - 'docker run -d --privileged --read-only --tmpfs /tmp --user 10001:10001 --pids-limit 100 --memory 256m myapp'
    explanation: 'Setiap opsi menutup satu jalur dan semuanya diuji di modul ini atau modul Storage:
      - `--read-only` mencegah binary ditanam.
      - `--user` membuat proses bukan root.
      - `--cap-drop ALL` membuat root tanpa kuasa.
      - `no-new-privileges` mencegah setuid.
      - `--pids-limit` menahan fork bomb.
      - `--memory` menahan kebocoran memori.

      Nginx-unprivileged dengan kombinasi serupa lolos `nginx -t` di lab.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Docker_Security_Cheat_Sheet.html

  - id: b15
    tier: B
    type: text
    objective: daemon-access
    prompt: Seorang anggota grup `docker` menjalankan `docker run -v /:/host alpine cat /host/etc/shadow`. Apakah ia bisa membaca hash password host? (ya/tidak)
    answers: ['ya', 'yes', 'bisa']
    tests:
      fail: ['tidak', 'no']
    explanation: 'Di lab (VM Docker Desktop): `root:*:20549:0:99999`. Bind mount `/` memberi container seluruh filesystem host, dan proses container adalah root. Tidak butuh exploit apa pun. Karena itu, akses ke Docker diperlakukan sama dengan akses sudo (modul Linux User & sudo), dan sebaiknya diaudit.'
    source: https://docs.docker.com/engine/security/#docker-daemon-attack-surface

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: hardening
    prompt: |
      Hardening service `app` (php-fpm Laravel) di `compose.yaml`. Tulis key yang ditambahkan (baris YAML, satu per baris).
      Batasannya:
      - app hanya perlu menulis ke `storage/` (volume) dan `/tmp`
      - tidak butuh capability khusus
      - tidak boleh bisa menaikkan hak
      - tidak boleh bisa menghabiskan proses atau memori host
    checklist:
      - id: user
        label: user non-root (www-data / UID)
        required: true
        patterns: ['^user:\s*"?(www-data|\d{3,})(:\S+)?"?\s*$']
      - id: read-only
        label: read_only true
        required: true
        patterns: ['^read_only:\s*true\s*$']
      - id: tmpfs
        label: tmpfs untuk /tmp (dan path runtime lain)
        patterns: ['^tmpfs:', '^-\s*/tmp(:\S+)?\s*$', 'tmpfs:\s*\[\s*"?/tmp']
      - id: storage
        label: Volume untuk storage (satu-satunya path yang bisa ditulis)
        patterns: [':/var/www/html/storage\S*', 'target:\s*/var/www/html/storage']
      - id: cap-drop
        label: cap_drop ALL
        required: true
        patterns: ['cap_drop:\s*\[?\s*"?all"?']
      - id: nnp
        label: security_opt no-new-privileges
        patterns: ['no-new-privileges(:true)?']
      - id: limits
        label: pids_limit / memory limit
        patterns: ['pids_limit:\s*\d+', 'pids:\s*\d+', 'memory:\s*"?\d+[mg]', 'mem_limit:\s*\d+']
      - id: no-privileged
        label: Tidak privileged
        forbid: true
        patterns: ['privileged:\s*true']
      - id: no-sock
        label: Tidak mount docker.sock
        forbid: true
        patterns: ['docker\.sock']
      - id: no-cap-add-admin
        label: Tidak menambah SYS_ADMIN/ALL
        forbid: true
        patterns: ['cap_add:\s*\[?\s*"?(all|sys_admin)']
    pass_score: 0.6
    solution: |
      ```yaml
      services:
        app:
          image: ghcr.io/acme/dojo-app:${TAG:?}
          user: "www-data"
          read_only: true
          tmpfs:
            - /tmp
          volumes:
            - storage:/var/www/html/storage
          cap_drop: [ALL]
          security_opt: ["no-new-privileges:true"]
          pids_limit: 200
          deploy:
            resources:
              limits: { memory: 384m }
      ```
      Diuji di lab:
      - Tanpa capability, root pun tidak bisa `chown` (`Operation not permitted`).
      - `NoNewPrivs: 1` aktif.
      - `pids_limit` menghentikan fork bomb (`can't fork`).
      - Rootfs read-only menolak penulisan (`Read-only file system`), sementara tmpfs dan volume tetap bisa ditulis.

      php-fpm master yang berjalan sebagai `www-data` (lab modul 03: `1 www-data php-fpm: master process`) tidak butuh
      `SETUID`/`SETGID`. Path tulis lain yang mungkin dibutuhkan Laravel (`bootstrap/cache` saat entrypoint menjalankan
      `config:cache`) harus dibuat saat build atau diberi tmpfs/volume. Uji dulu di staging, karena read-only sering
      memunculkan path tulis yang terlupa.
    tests:
      pass:
        - |
          user: "www-data"
          read_only: true
          tmpfs:
            - /tmp
          volumes:
            - storage:/var/www/html/storage
          cap_drop: [ALL]
          security_opt: ["no-new-privileges:true"]
          pids_limit: 200
          deploy: { resources: { limits: { memory: 384m } } }
        - |
          user: "10001:10001"
          read_only: true
          tmpfs: ["/tmp"]
          cap_drop: ["ALL"]
          mem_limit: 256m
      fail:
        - |
          privileged: true
          user: root
        - |
          user: "www-data"
          read_only: true
          cap_drop: [ALL]
          volumes:
            - /var/run/docker.sock:/var/run/docker.sock
        - |
          user: "www-data"
          cap_add: [SYS_ADMIN]
          read_only: true
    source: https://docs.docker.com/reference/compose-file/services/

  - id: c02
    tier: C
    type: scenario
    objective: daemon-access
    prompt: |
      Runner CI self-hosted menjalankan job di container yang me-mount `docker.sock` host (untuk `docker build`), dengan
      alasan "aman, socket-nya sudah :ro". Server yang sama juga menjalankan production. Tulis penilaian risiko dan
      langkah perbaikan (satu poin/perintah per baris).
    checklist:
      - id: identify
        label: Menyatakan docker.sock = akses root ke host/daemon
        required: true
        patterns: ['docker\.sock.*(root|setara|host|kontrol (penuh|daemon))', '(root|setara root).*docker\.sock']
      - id: ro-myth
        label: Menjelaskan :ro tidak melindungi API
        required: true
        patterns: [':ro.*(tidak|bukan|tak)\b', 'read-?only.*(tidak|bukan)\s+(mencegah|melindungi|aman)']
      - id: separate
        label: Memisahkan runner dari server production (host/VM terpisah, ephemeral)
        patterns: ['(pisah|terpisah|dedicated|khusus|vm|ephemeral|sekali pakai)']
      - id: alt-build
        label: Build tanpa socket host (BuildKit rootless/remote builder/kaniko/buildah)
        patterns: ['rootless', 'buildkit', 'kaniko', 'buildah', 'remote builder', 'buildx\s+create']
      - id: remove
        label: Melepas mount docker.sock dari job
        patterns: ['(hapus|lepas|cabut|tanpa|jangan|stop).*docker\.sock', 'docker\.sock.*(dihapus|dilepas|dicabut)']
      - id: rotate
        label: Rotasi secret yang mungkin sudah terekspos
        patterns: ['rotasi|rotate|ganti (secret|token|kredensial)']
      - id: no-privileged
        label: Tidak menyarankan --privileged / DinD privileged sebagai pengganti
        forbid: true
        patterns: ['^(docker\s+run\s+.*)?--privileged\b', 'privileged:\s*true']
    pass_score: 0.5
    solution: |
      ```text
      Risiko: docker.sock = kontrol penuh daemon = root di host (lab: dari container ber-socket, menjalankan container
              --privileged berhasil, dan bisa exec ke container lain seperti production).
      Mitos:  ":ro" hanya melindungi file socket; panggilan API tetap jalan (lab: docker run lewat socket :ro sukses).
      Perbaikan:
        1. Lepas docker.sock dari job CI.
        2. Pisahkan runner ke VM/host khusus yang ephemeral — jangan satu host dengan production.
        3. Build tanpa socket host: BuildKit rootless / remote builder (docker buildx create --driver remote ...), atau kaniko/buildah.
        4. Rotasi semua secret di host itu (registry token, .env production) — anggap sudah terekspos.
      ```
      Studi kasus ini menggabungkan beberapa temuan lab:
      - Mount socket memberi kemampuan menjalankan container privileged dan `docker exec` ke container lain (`dj-victim`).
      - `:ro` tidak mengubah apa pun pada akses API.
      - Bind `/` dari container mana pun membaca `/etc/shadow` host.

      Job CI menjalankan kode dari banyak branch (termasuk dependency pihak ketiga). Siapa pun yang bisa membuat branch,
      atau paket npm yang disusupi, mendapat root di server production. DinD dengan `--privileged` bukan solusi, karena
      container privileged juga bisa me-mount disk host.
    tests:
      pass:
        - |
          docker.sock memberi akses root ke host
          :ro tidak melindungi API, hanya file socket
          hapus mount docker.sock dari job
          pindahkan runner ke VM terpisah yang ephemeral
          build dengan buildkit rootless atau kaniko
          rotasi token registry dan secret production
        - |
          mount docker.sock setara root di host
          read-only tidak mencegah panggilan API
          pakai remote builder: docker buildx create --driver remote tcp://builder:1234
      fail:
        - |
          ganti dengan docker run --privileged docker:dind
          docker.sock aman karena :ro
        - |
          tambahkan :ro pada docker.sock
          batasi memory runner
        - |
          privileged: true
          docker.sock setara root di host
          :ro tidak melindungi
    source: https://docs.docker.com/engine/security/#docker-daemon-attack-surface

  - id: c03
    tier: C
    type: scenario
    objective: audit
    prompt: |
      Kamu mewarisi server dengan puluhan container dari berbagai tim. Tulis perintah audit (satu per baris) untuk
      menemukan container yang berbahaya: privileged, berjalan sebagai root, menambah capability, me-mount `docker.sock`
      atau `/` host, mem-publish port ke semua interface, dan image yang penuh CVE — **tanpa** menghentikan apa pun.
    checklist:
      - id: privileged
        label: Memeriksa HostConfig.Privileged
        required: true
        patterns: ['\.hostconfig\.privileged']
      - id: user
        label: Memeriksa Config.User (kosong = root)
        patterns: ['\.config\.user']
      - id: caps
        label: Memeriksa HostConfig.CapAdd
        patterns: ['\.hostconfig\.capadd']
      - id: mounts
        label: Memeriksa mount (docker.sock / bind /)
        required: true
        patterns: ['\.mounts', '--filter[= ]volume=/var/run/docker\.sock', 'docker\.sock']
      - id: ports
        label: Memeriksa port publik (0.0.0.0)
        patterns: ['0\.0\.0\.0', 'docker\s+ps\s+.*\.ports', '--format\s+.*ports']
      - id: scan
        label: Memindai image (trivy / scout / grype)
        patterns: ['\b(trivy|grype)\b', 'docker\s+scout']
      - id: no-destroy
        label: Tidak menghentikan/menghapus container saat audit
        forbid: true
        patterns: ['docker\s+(rm|stop|kill)\b', 'docker\s+(system|container)\s+prune']
    pass_score: 0.5
    solution: |
      ```bash
      docker ps -q | xargs docker inspect -f \
        '{{.Name}} priv={{.HostConfig.Privileged}} user={{.Config.User}} capadd={{.HostConfig.CapAdd}} ro={{.HostConfig.ReadonlyRootfs}}'
      docker ps -q | xargs docker inspect -f '{{.Name}} {{range .Mounts}}{{.Source}}->{{.Destination}} {{end}}' | grep -E 'docker\.sock| /->'
      docker ps --format '{{.Names}}  {{.Ports}}' | grep '0.0.0.0'
      for img in $(docker ps --format '{{.Image}}' | sort -u); do trivy image --quiet --severity CRITICAL --ignore-unfixed "$img"; done
      ```
      Setiap field di atas terbukti bermakna di lab:
      - `Privileged=true` → semua capability dan 157 device.
      - `User` kosong → root.
      - `CapAdd` memperlihatkan capability yang ditambahkan.
      - Mount `docker.sock` → kontrol daemon.
      - Port `0.0.0.0` → terbuka ke semua interface, dan di Linux melewati ufw (modul Networking).
      - Trivy menemukan 70 CVE pada image nginx yang sudah EOL (modul Image & Registry).

      Audit dilakukan read-only. Hasilnya diserahkan ke pemilik setiap service untuk diperbaiki, bukan langsung
      dimatikan. Tool otomatis untuk pemeriksaan serupa adalah docker-bench-security (CIS Docker Benchmark).
    tests:
      pass:
        - |
          docker ps -q | xargs docker inspect -f '{{.Name}} {{.HostConfig.Privileged}} {{.Config.User}} {{.HostConfig.CapAdd}}'
          docker ps -q | xargs docker inspect -f '{{.Name}} {{json .Mounts}}' | grep docker.sock
          docker ps --format '{{.Names}} {{.Ports}}' | grep 0.0.0.0
          trivy image --severity CRITICAL nginx:1.27-alpine
        - |
          docker inspect -f '{{.HostConfig.Privileged}}' $(docker ps -q)
          docker ps --filter volume=/var/run/docker.sock
          docker scout cves myapp:1.0
      fail:
        - |
          docker ps
          docker rm -f $(docker ps -q --filter label=team=legacy)
        - |
          docker inspect -f '{{.HostConfig.Privileged}}' web
          docker stop web
        - |
          docker images
          docker system prune -af
    source: https://www.cisecurity.org/benchmark/docker

---

# Keamanan Container

Container memberi isolasi yang nyata, tetapi bukan isolasi sekuat VM. Semua container berbagi **satu kernel** dengan host,
dan sebagian besar keamanannya berasal dari lapisan yang bisa dikurangi atau dimatikan lewat satu opsi: user, capabilities,
seccomp, namespace, dan batas resource. Modul ini membahas setiap lapisan itu, apa yang terjadi bila dilepas (dengan
bukti lab), dan cara menerapkan hardening di `docker run` maupun Compose.

> [!NOTE]
> Diuji di Docker Desktop (Windows) dengan Docker Engine 29.4.1. Di Docker Desktop, container berjalan di VM Linux, sehingga
> "host" di hasil lab adalah VM itu (mis. `/etc/shadow` milik VM). Di server Linux, host-nya adalah server itu sendiri,
> dan dampaknya jauh lebih besar. Satu percobaan (eskalasi setuid) tidak bisa direproduksi di lingkungan ini, dan ditandai.

## 1. Model ancaman

```mermaid
flowchart TB
    A[Penyerang masuk ke aplikasi<br/>RCE, dependency berbahaya] --> C[Proses di container]
    C --> L1{user root?}
    L1 --> L2{capabilities?}
    L2 --> L3{seccomp / no-new-privs?}
    L3 --> L4{rootfs read-only?<br/>pids & memori dibatasi?}
    L4 --> L5{mount berbahaya?<br/>docker.sock, /, --privileged}
    L5 -- ya --> H[Root di host<br/>+ semua container lain]
    L5 -- tidak --> K[Terkurung di container<br/>dampak terbatas]
```

Hardening tidak bertujuan membuat container mustahil dibobol. Tujuannya membuat penyerang yang sudah masuk **tidak
bisa berbuat banyak**.

## 2. User non-root

```dockerfile
RUN addgroup -S -g 10001 app && adduser -S -u 10001 -G app app
USER 10001:10001
```

```bash
docker run --user 10001:10001 myapp
```

- Default tanpa `USER`: `uid=0(root)` (lab).
- Image yang dirancang untuk root sering gagal saat dipaksa non-root. Di lab, `nginx:1.27-alpine` → `test failed`,
  sedangkan `nginxinc/nginx-unprivileged` → `test is successful` (listen 8080).
- Non-root bisa bind port 80 di container (lab: `ip_unprivileged_port_start=0`), jadi itu bukan alasan memakai root.

## 3. Capabilities

| Container | `CapEff` (lab) | Artinya |
|---|---|---|
| Default | `00000000a80425fb` | 14 capability (CHOWN, SETUID, SETGID, NET_RAW, KILL, ...) |
| `--cap-drop ALL` | `0000000000000000` | Tidak ada. Root pun tidak bisa `chown` |
| `--cap-drop ALL --cap-add NET_BIND_SERVICE` | `0000000000000400` | Hanya bind port rendah |
| `--privileged` | `000001ffffffffff` | **Semua**, plus 157 device, plus `mount` |

```bash
docker run --cap-drop ALL --cap-add NET_BIND_SERVICE myapp
docker exec web grep -E 'CapEff|NoNewPrivs|Seccomp' /proc/1/status
```

> [!WARNING]
> Jangan menguji capability dengan "apakah ping masih jalan". Di lab, `--cap-drop NET_RAW` **tidak** menghentikan ping
> busybox, karena ia memakai ICMP datagram socket yang diizinkan `ping_group_range`. Verifikasi dengan `CapEff`, bukan
> dengan perilaku satu program.

## 4. `--privileged`, grup docker, dan docker.sock

| Akses | Hasil lab | Setara |
|---|---|---|
| `--privileged` | 157 device, `mount` berhasil, semua capability | Root host (mount disk host) |
| `-v /:/host` | `/host/etc/shadow` terbaca | Root host |
| `-v /var/run/docker.sock:...` | Menjalankan container privileged baru, `exec` ke container lain | Root host + semua container |
| `-v ...docker.sock:...:ro` | **Tetap** bisa `docker run` | Sama, karena `:ro` tidak melindungi API |
| Anggota grup `docker` | Bisa melakukan semua di atas | **root** |

> [!DANGER]
> **docker.sock adalah kunci root.** Pola umum seperti Traefik, Portainer, Watchtower, runner CI, atau "agent monitoring"
> me-mount socket ke container. Di lab, dari container seperti itu kita menjalankan container `--privileged` baru dan
> masuk ke container lain, bahkan saat socket di-mount `:ro`. Bila tool itu atau image-nya dibobol, host ikut jatuh.
> Pakai hanya untuk tool tepercaya, di host yang tidak menjalankan workload sensitif, atau lewat proxy socket yang
> membatasi endpoint API.
> Sumber: [Docker daemon attack surface](https://docs.docker.com/engine/security/#docker-daemon-attack-surface)

## 5. Seccomp dan no-new-privileges

```bash
docker run --security-opt no-new-privileges:true myapp     # NoNewPrivs: 1 (lab)
docker run --security-opt seccomp=/path/profile.json myapp # profil khusus
# JANGAN: --security-opt seccomp=unconfined / apparmor=unconfined
```

- Seccomp default (`Seccomp: 2`) memblokir syscall berisiko. Di lab, `unshare -U -r` → `Operation not permitted`, tetapi
  dengan `seccomp=unconfined` berhasil.
- `no-new-privileges` mencegah eskalasi lewat binary setuid/setgid. Di lab, flag-nya terbukti aktif (`NoNewPrivs: 1`),
  tetapi eskalasi setuid itu sendiri tidak bisa direproduksi di Docker Desktop.

## 6. Batas resource dan filesystem

```bash
docker run --read-only --tmpfs /tmp --pids-limit 100 --memory 256m --cpus 0.5 myapp
```

| Opsi | Melindungi dari | Lab |
|---|---|---|
| `--read-only` + tmpfs/volume | Binary ditanam, file aplikasi diubah | `Read-only file system` |
| `--pids-limit` | Fork bomb | `can't fork: Resource temporarily unavailable` |
| `--memory` | Kebocoran memori → host OOM | Modul Process: OOMKilled, exit 137 |
| `--cpus` | Satu service memonopoli CPU | — |

## 7. Hardening di Compose

```yaml
x-hardened: &hardened
  read_only: true
  cap_drop: [ALL]
  security_opt: ["no-new-privileges:true"]
  pids_limit: 200
  tmpfs: [/tmp]

services:
  app:
    <<: *hardened
    user: "www-data"
    volumes: [storage:/var/www/html/storage]
    deploy: { resources: { limits: { memory: 384m } } }
  nginx:
    <<: *hardened
    image: nginxinc/nginx-unprivileged:1.30-alpine
    ports: ["127.0.0.1:8080:8080"]
```

## 8. Audit cepat container yang berjalan

```bash
docker ps -q | xargs docker inspect -f \
  '{{.Name}} priv={{.HostConfig.Privileged}} user={{.Config.User}} capadd={{.HostConfig.CapAdd}} ro={{.HostConfig.ReadonlyRootfs}}'
docker ps -q | xargs docker inspect -f '{{.Name}} {{range .Mounts}}{{.Source}}->{{.Destination}} {{end}}' | grep docker.sock
docker ps --format '{{.Names}} {{.Ports}}' | grep 0.0.0.0
```

Gabungkan dengan scanning image (Trivy, modul 04) dan CIS Docker Benchmark (docker-bench-security) untuk pemeriksaan
konfigurasi daemon dan host.

## 9. Ringkasan kebiasaan aman

| Kebiasaan | Kenapa (bukti lab) |
|---|---|
| `USER` non-root di image, UID tinggi | Default = `uid=0(root)` |
| `cap_drop: [ALL]`, tambah seperlunya | Root tanpa capability tidak bisa `chown` |
| Jangan `--privileged` untuk aplikasi | 157 device + `mount` = breakout |
| Jangan mount `docker.sock`, dan `:ro` tidak membantu | Bisa menjalankan container privileged |
| Perlakukan grup docker = root | `-v /:/host` membaca `/etc/shadow` |
| `no-new-privileges:true`, jangan `seccomp=unconfined` | Seccomp memblokir `unshare` |
| `read_only` + tmpfs/volume | Penyerang tidak bisa menulis ke rootfs |
| `pids_limit` + limit memori/CPU | Fork bomb berhenti di `can't fork` |
| Pilih image yang dirancang non-root (nginx-unprivileged) | Image resmi sering butuh root |
| Audit rutin: Privileged, User, CapAdd, Mounts, Ports + scan CVE | Konfigurasi berbahaya bisa terdeteksi sebelum dimanfaatkan |
