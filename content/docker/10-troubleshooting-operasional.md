---
format: 1
id: docker-troubleshooting-ops
course:
  slug: docker
  title: Docker
  description: Container dari nol sampai production — CLI, image, storage, network, Compose, keamanan, dan operasional.
title: Troubleshooting & Operasional
summary: Membaca exit code dan sinyal, restart loop, log dan rotasinya, healthcheck, docker events, debug container tanpa shell, error jaringan yang umum, serta mengelola disk Docker — dengan pesan error asli dari lab.
order: 10
version: 1
status: verified
updated: '2026-11-23'
tested_on:
  - Docker Desktop (Windows) — Docker Engine 29.4.1, Compose v5.1.3
  - Image alpine:3.20, busybox:1.36, nginx:1.27-alpine
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: exit
    text: Membaca exit code dan sinyal (125/126/127/137/139/143)
  - id: restart
    text: Mendiagnosis restart loop dan OOM
  - id: logs
    text: Membaca log dengan filter dan mengatur rotasi log
  - id: inspect
    text: Mengambil state, health, dan event container
  - id: debug
    text: Men-debug container tanpa shell atau yang terus crash
  - id: network
    text: Membedakan error jaringan (DNS, refused, timeout, port terpakai)
  - id: disk
    text: Mengelola penggunaan disk Docker dengan aman
sources:
  - title: docker container logs
    url: https://docs.docker.com/reference/cli/docker/container/logs/
  - title: Docker Docs — Configure logging drivers
    url: https://docs.docker.com/engine/logging/configure/
  - title: Docker Docs — JSON File logging driver
    url: https://docs.docker.com/engine/logging/drivers/json-file/
  - title: docker container inspect
    url: https://docs.docker.com/reference/cli/docker/container/inspect/
  - title: docker system events
    url: https://docs.docker.com/reference/cli/docker/system/events/
  - title: docker container diff
    url: https://docs.docker.com/reference/cli/docker/container/diff/
  - title: docker container run — exit status, --init, --restart
    url: https://docs.docker.com/reference/cli/docker/container/run/
  - title: Docker Docs — Start containers automatically (restart policies)
    url: https://docs.docker.com/engine/containers/start-containers-automatically/
  - title: docker system df
    url: https://docs.docker.com/reference/cli/docker/system/df/
  - title: docker builder prune
    url: https://docs.docker.com/reference/cli/docker/builder/prune/
  - title: docker compose ps / logs
    url: https://docs.docker.com/reference/cli/docker/compose/ps/
  - title: signal(7) — nilai sinyal Linux
    url: https://man7.org/linux/man-pages/man7/signal.7.html
  - title: nicolaka/netshoot — container debug jaringan
    url: https://github.com/nicolaka/netshoot
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: command
    objective: logs
    prompt: Tampilkan log container `web` dari **15 menit terakhir**, lengkap dengan **timestamp**.
    answers: ['docker logs --since 15m -t web']
    patterns: ['^docker (container )?logs ((--since[= ]15m|-t|--timestamps) ){2}web$']
    tests:
      pass: ['docker logs -t --since 15m web', 'docker logs --timestamps --since=15m web']
      fail: ['docker logs --tail 15 -t web', 'docker logs --since 15m web', 'docker logs -f -t web']
    explanation: 'Di lab, `docker logs -t --tail 1` → `2026-11-23T08:34:14.917460445Z err5` (timestamp RFC3339 dari daemon, bukan dari aplikasi). `--since` menerima durasi relatif (`15m`, `2h`) atau waktu absolut, dan bisa digabung dengan `--until`. Saat insiden, rentang waktu lebih berguna daripada `--tail`.'
    source: https://docs.docker.com/reference/cli/docker/container/logs/

  - id: a02
    tier: A
    type: text
    objective: exit
    prompt: Container berhenti dengan exit code **127**. Apa artinya?
    answers: ['command not found']
    patterns: ['(command|perintah|executable|binary|file).*(not found|tidak (ada|ditemukan))', 'not found in \$?path']
    tests:
      pass: ['perintah tidak ditemukan', 'executable file not found in $PATH']
      fail: ['permission denied', 'out of memory', 'segfault', 'dibunuh sigkill']
    explanation: 'Di lab, `docker run alpine nosuchcmd` → exit `127` dengan pesan `exec: "nosuchcmd": executable file not found in $PATH`. Penyebab umum: typo pada `CMD`, binary yang tidak ada di image akhir multi-stage, atau image `-slim`/distroless tanpa `bash`.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#exit-status

  - id: a03
    tier: A
    type: text
    objective: exit
    prompt: Exit code **137** berasal dari 128 + nomor sinyal apa?
    answers: ['9', 'SIGKILL', 'KILL']
    tests:
      fail: ['15', 'SIGTERM', '11', 'SIGSEGV']
    explanation: 'Exit 128+N berarti proses dimatikan sinyal N. 137 = SIGKILL. Di lab, dua penyebab utamanya: **OOM** (`-m 16m` → exit 137, `OOMKilled=true`) dan **stop timeout** (proses tidak berhenti saat SIGTERM, lalu Docker mengirim SIGKILL). Bedakan keduanya lewat `.State.OOMKilled`.'
    source: https://man7.org/linux/man-pages/man7/signal.7.html

  - id: a04
    tier: A
    type: command
    objective: restart
    prompt: Tampilkan hanya nilai `OOMKilled` dari state container `app` dengan `docker inspect`.
    answers: ['docker inspect -f ''{{.State.OOMKilled}}'' app']
    patterns: ['^docker (container )?inspect (-f|--format)[= ]?\{\{ ?\.State\.OOMKilled ?\}\} app$']
    tests:
      pass: ['docker inspect --format {{.State.OOMKilled}} app', 'docker container inspect -f "{{ .State.OOMKilled }}" app']
      fail: ['docker inspect -f {{.OOMKilled}} app', 'docker inspect -f {{.State.ExitCode}} app', 'docker stats app']
    explanation: 'Di lab: `oom exit=137 OOMKilled=true`. Exit 137 dengan `OOMKilled=false` artinya SIGKILL dari tempat lain (stop timeout, `docker kill`, atau OOM killer host di luar cgroup). Langkah berikutnya: naikkan limit atau cari kebocoran memori lewat `docker stats`.'
    source: https://docs.docker.com/reference/cli/docker/container/inspect/

  - id: a05
    tier: A
    type: command
    objective: restart
    prompt: Tampilkan berapa kali container `app` sudah **di-restart** oleh restart policy (dengan `docker inspect`).
    answers: ['docker inspect -f ''{{.RestartCount}}'' app']
    patterns: ['^docker (container )?inspect (-f|--format)[= ]?\{\{ ?\.RestartCount ?\}\} app$']
    tests:
      pass: ['docker inspect --format={{ .RestartCount }} app']
      fail: ['docker inspect -f {{.State.RestartCount}} app', 'docker ps --restarts app', 'docker inspect -f {{.State.Restarting}} app']
    explanation: '`RestartCount` ada di level atas, bukan di `.State`. Di lab, `--restart on-failure:3` + `exit 2` → `status=exited restarts=3 exit=2`. Angka yang terus naik adalah tanda restart loop. Restart menutupi masalah, jadi baca log dari percobaan pertama.'
    source: https://docs.docker.com/reference/cli/docker/container/inspect/

  - id: a06
    tier: A
    type: command
    objective: debug
    prompt: Tampilkan daftar file yang **ditambah, diubah, atau dihapus** di filesystem container `web` dibanding image-nya.
    answers: ['docker diff web']
    patterns: ['^docker (container )?diff web$']
    tests:
      pass: ['docker container diff web']
      fail: ['docker inspect web', 'docker history web', 'docker cp web:/ .']
    explanation: 'Di lab, setelah `echo x > /etc/hack; rm /etc/motd` → `C /etc`, `A /etc/hack`, `D /etc/motd` (A = added, C = changed, D = deleted). Berguna untuk mengecek "siapa mengubah apa" di container yang dicurigai, atau menemukan path yang ditulis aplikasi sebelum menerapkan `read_only`.'
    source: https://docs.docker.com/reference/cli/docker/container/diff/

  - id: a07
    tier: A
    type: command
    objective: debug
    prompt: Jalankan image `myapp` sekali pakai secara interaktif, tetapi **ganti entrypoint**-nya dengan `sh` agar bisa memeriksa isi image yang gagal start.
    answers: ['docker run --rm -it --entrypoint sh myapp']
    patterns: ['^docker (container )?run (?=.*--entrypoint[= ](/bin/)?sh( |$))((--rm|-it|-ti|-i|-t|--entrypoint[= ](/bin/)?sh) )+myapp$']
    tests:
      pass: ['docker run -it --rm --entrypoint=/bin/sh myapp']
      fail: ['docker run --rm -it myapp sh', 'docker exec -it myapp sh', 'docker run --rm -it --cmd sh myapp']
    explanation: 'Bila `ENTRYPOINT` langsung crash, `docker run ... myapp sh` tidak membantu karena `sh` hanya menjadi argumen untuk entrypoint itu. `--entrypoint` menggantinya. Di lab, `--entrypoint sh nginx:1.27-alpine -c ...` → `overridden`. `docker exec` hanya bekerja pada container yang **sedang berjalan**.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#entrypoint

  - id: a08
    tier: A
    type: command
    objective: inspect
    prompt: Pantau secara live event container yang **mati** (`die`) di seluruh daemon.
    answers: ['docker events --filter event=die']
    patterns: ['^docker (system )?events (--filter|-f)[= ]event=die$']
    tests:
      pass: ['docker events -f event=die', 'docker system events --filter=event=die']
      fail: ['docker logs --filter die', 'docker events die', 'docker ps --filter status=exited']
    explanation: 'Di lab, satu `docker run ... exit 5` menghasilkan `create attach start die destroy`, dan `--format ''{{.Action}} exit={{.Actor.Attributes.exitCode}}''` → `die exit=5`. Gunakan `--since 1h --until 0s` untuk melihat riwayat tanpa menunggu.'
    source: https://docs.docker.com/reference/cli/docker/system/events/

  - id: a09
    tier: A
    type: command
    objective: disk
    prompt: Tampilkan ringkasan pemakaian disk Docker (image, container, volume, build cache) beserta yang bisa dibebaskan.
    answers: ['docker system df']
    patterns: ['^docker system df( -v| --verbose)?$']
    tests:
      pass: ['docker system df -v']
      fail: ['docker df', 'docker system prune', 'du -sh /var/lib/docker']
    explanation: 'Di laptop lab: Images 7.694GB (2.962GB reclaimable), Build Cache 5.673GB (3.672GB). Build cache sering menjadi pemakan disk terbesar yang tidak terlihat oleh `docker images`. Perintah ini hanya membaca, tidak menghapus apa pun.'
    source: https://docs.docker.com/reference/cli/docker/system/df/

  - id: a10
    tier: A
    type: command
    objective: logs
    prompt: Jalankan `myapp` di background dengan rotasi log json-file **maksimal 10 MB per file, 3 file**.
    answers: ['docker run -d --log-opt max-size=10m --log-opt max-file=3 myapp']
    patterns: ['^docker (container )?run (?=.*--log-opt[= ]max-size=10[mM]( |$))(?=.*--log-opt[= ]max-file=3( |$))((-d|--detach|--log-driver[= ]json-file|--log-opt[= ]\S+) )+myapp$']
    tests:
      pass: ['docker run --log-opt max-file=3 --log-opt max-size=10m -d myapp', 'docker run -d --log-driver json-file --log-opt max-size=10m --log-opt max-file=3 myapp']
      fail: ['docker run -d --log-max-size 10m myapp', 'docker run -d --log-opt max-size=10m myapp', 'docker run -d --log-opt max-file=3 myapp']
    explanation: 'Di lab, `max-size=1k max-file=2` dengan 2000 baris → hanya **18 baris terakhir** yang tersisa (pertama `line-1982`). Tanpa opsi ini, log json-file **tidak pernah dirotasi** (lab: `cfg=map[]`). Di Compose: `logging: { driver: json-file, options: { max-size: "10m", max-file: "3" } }`.'
    source: https://docs.docker.com/engine/logging/drivers/json-file/

  - id: a11
    tier: A
    type: command
    objective: restart
    prompt: Tampilkan semua container project Compose saat ini, **termasuk yang sudah berhenti**.
    answers: ['docker compose ps -a']
    patterns: ['^docker compose ps (-a|--all)$']
    tests:
      pass: ['docker compose ps --all']
      fail: ['docker compose ps', 'docker ps', 'docker compose ls -a']
    explanation: 'Di lab, `docker compose ps` biasa tidak menampilkan service `worker` yang sudah `exited 3`. Dengan `-a`: `app created`, `db running unhealthy`, `worker exited 3`. Service yang "hilang" dari `compose ps` biasanya sudah crash.'
    source: https://docs.docker.com/reference/cli/docker/compose/ps/

  - id: a12
    tier: A
    type: command
    objective: logs
    prompt: Tampilkan **50 baris terakhir** log service `app` dan `worker` dari project Compose.
    answers: ['docker compose logs --tail 50 app worker']
    patterns: ['^docker compose logs ((--tail[= ]50|-n[= ]?50|--no-color|-t|--timestamps|--no-log-prefix) )+app worker$', '^docker compose logs ((--tail[= ]50|-n[= ]?50|--no-color|-t|--timestamps|--no-log-prefix) )+worker app$']
    tests:
      pass: ['docker compose logs --tail=50 worker app', 'docker compose logs -n 50 app worker']
      fail: ['docker compose logs app worker', 'docker logs --tail 50 app worker', 'docker compose logs --tail 50']
    explanation: 'Di lab, log setiap baris diberi prefix nama container (`worker-1  | working`). `--no-log-prefix` menghapusnya, berguna saat menyalin log ke grep/jq. Tanpa nama service, semua service digabung sehingga sulit dibaca saat insiden.'
    source: https://docs.docker.com/reference/cli/docker/compose/logs/

  - id: a13
    tier: A
    type: text
    objective: exit
    prompt: Exit code **125** dari `docker run` menandakan error di mana?
    answers: ['docker daemon / perintah docker itu sendiri']
    patterns: ['(docker|daemon|cli|flag|opsi)']
    tests:
      pass: ['daemon', 'flag docker salah', 'perintah docker itu sendiri']
      fail: ['aplikasi', 'command not found', 'oom']
    explanation: 'Konvensi `docker run`: 125 = docker sendiri gagal (lab: flag tidak dikenal `--nosuchflag` → 125), 126 = perintah tidak bisa dieksekusi, 127 = perintah tidak ditemukan. Selain itu = exit code proses di container.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#exit-status

  - id: a14
    tier: A
    type: command
    objective: debug
    prompt: Tampilkan proses yang berjalan di container `web` dari sisi host (tanpa masuk ke container).
    answers: ['docker top web']
    patterns: ['^docker (container )?top web( -o \S+| aux| -ef)?$']
    tests:
      pass: ['docker top web -o pid,user,args', 'docker container top web']
      fail: ['docker exec web ps', 'docker stats web', 'docker ps web']
    explanation: 'Di lab: master nginx `root`, worker sebagai `statd`, karena UID 101 di container dipetakan ke nama user host yang kebetulan memakai UID yang sama. PID-nya adalah PID host (17642), bukan PID 1. Tidak butuh `ps` di dalam image, jadi tetap bekerja untuk image distroless.'
    source: https://docs.docker.com/reference/cli/docker/container/top/

  - id: a15
    tier: A
    type: text
    objective: logs
    prompt: Logging driver default Docker Engine (yang menyimpan log sebagai file JSON di host)?
    answers: ['json-file']
    tests:
      fail: ['local', 'journald', 'syslog', 'none']
    explanation: 'Di lab: `logdriver=json-file`, path `/var/lib/docker/containers/<id>/<id>-json.log`. Driver `local` lebih hemat dan berotasi otomatis. `journald`/`syslog` mengirim log ke sistem host. Dengan driver `none`, `docker logs` tidak bisa dipakai sama sekali.'
    source: https://docs.docker.com/engine/logging/configure/

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: exit
    prompt: '`docker run alpine /etc/passwd` gagal dengan exit code berapa? (file ada, tetapi tidak executable)'
    answers: ['126']
    tests:
      fail: ['127', '125', '1', '0']
    explanation: 'Di lab: exit `126` dengan pesan `exec: "/etc/passwd": permission denied`. Di dunia nyata biasanya `entrypoint.sh` yang lupa `chmod +x`, atau dibuat di Windows sehingga hilang bit executable-nya. Perbaiki dengan `COPY --chmod=0755` di Dockerfile.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#exit-status

  - id: b02
    tier: B
    type: text
    objective: exit
    prompt: '`docker run alpine sleep 300` lalu `docker stop -t 3`. Container butuh ~4 detik untuk berhenti dengan exit code berapa?'
    answers: ['137']
    tests:
      fail: ['143', '0', '1', '130']
    explanation: 'Di lab: `exit=137 took=4s`. Proses dengan PID 1 tidak menerima sinyal default dari kernel bila tidak memasang handler. SIGTERM diabaikan, Docker menunggu timeout, lalu SIGKILL. Akibatnya setiap deploy tertunda sebesar stop timeout, dan aplikasi tidak sempat shutdown bersih.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#init

  - id: b03
    tier: B
    type: text
    objective: exit
    prompt: Container yang sama dijalankan dengan `--init` lalu di-stop. Exit code berapa yang dihasilkan, dan berapa lama?
    answers: ['143']
    patterns: ['143']
    tests:
      pass: ['143, sekitar 1 detik']
      fail: ['137', '0', '1']
    explanation: 'Di lab: `exit=143 took=1s`. `--init` menjalankan tini sebagai PID 1, yang meneruskan SIGTERM ke `sleep`. `sleep` mati karena SIGTERM (128+15 = 143). Pilihan lain: aplikasi menangani SIGTERM sendiri, atau `exec` di entrypoint. Sebagai pembanding, nginx (punya handler) → `exit=0`. Di Compose: `init: true`.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#init

  - id: b04
    tier: B
    type: text
    objective: exit
    prompt: Proses anak di container mati karena **segmentation fault**. Exit code apa yang dilaporkan shell induk?
    answers: ['139']
    tests:
      fail: ['137', '143', '11', '1']
    explanation: 'Di lab: `Segmentation fault (core dumped)`, `inner=139` (128 + SIGSEGV 11). Biasanya akibat binary yang dikompilasi untuk libc berbeda (glibc vs musl di Alpine), extension PHP yang tidak cocok, atau bug native. Coba image berbasis Debian sebagai pembanding.'
    source: https://man7.org/linux/man-pages/man7/signal.7.html

  - id: b05
    tier: B
    type: text
    objective: restart
    prompt: Dengan `--restart on-failure:3` dan perintah yang selalu `exit 2`, berapa kali total perintah itu **dijalankan**?
    answers: ['4']
    tests:
      fail: ['3', '1', 'tak terbatas', '2']
    explanation: 'Di lab: log berisi `boom` **4 kali** (1 run awal + 3 restart), lalu `status=exited restarts=3`. Dengan `--restart always`, container tidak pernah berhenti mencoba (`Restarting (1) 1 second ago`), dengan jeda yang makin panjang. Untuk worker yang bisa gagal permanen, `on-failure:N` membuat masalah terlihat.'
    source: https://docs.docker.com/engine/containers/start-containers-automatically/

  - id: b06
    tier: B
    type: text
    objective: logs
    prompt: Tanpa `--log-opt` dan tanpa konfigurasi di `daemon.json`, apakah log json-file dirotasi otomatis? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Di lab: `cfg=map[]`, tidak ada `max-size`. File `*-json.log` terus tumbuh sampai disk penuh, salah satu penyebab paling umum server Docker "tiba-tiba mati". Atur default untuk semua container di `/etc/docker/daemon.json` (`"log-opts": {"max-size": "10m", "max-file": "3"}`). Setelan ini hanya berlaku untuk container **baru**.'
    source: https://docs.docker.com/engine/logging/drivers/json-file/

  - id: b07
    tier: B
    type: command
    objective: logs
    prompt: Tampilkan **hanya stderr** dari log container `web` (stdout dibuang).
    answers: ['docker logs web 2>&1 >/dev/null']
    patterns: ['^docker (container )?logs web( 2>&1)? >/dev/null$', '^docker (container )?logs web 1>/dev/null$']
    tests:
      pass: ['docker logs web > /dev/null', 'docker logs web 1>/dev/null']
      fail: ['docker logs web 2>/dev/null', 'docker logs --stderr web', 'docker logs web | grep err']
    explanation: '`docker logs` menulis stdout container ke stdout dan stderr container ke stderr, sehingga kedua aliran bisa dipisah. Di lab, `docker logs dj-log 2>/dev/null | wc -l` → `5` (hanya `outN`). `2>&1 >/dev/null` mengalihkan stderr ke pipe lalu membuang stdout, cocok untuk `| grep`.'
    source: https://docs.docker.com/reference/cli/docker/container/logs/

  - id: b08
    tier: B
    type: text
    objective: logs
    prompt: Container dijalankan dengan `--log-driver none`. Apa yang terjadi saat `docker logs` dipanggil?
    answers: ['error, driver tidak mendukung pembacaan']
    patterns: ['(error|gagal|tidak (bisa|mendukung|ada)|kosong|does not support|tidak muncul)']
    tests:
      pass: ['configured logging driver does not support reading', 'gagal, tidak bisa dibaca']
      fail: ['muncul seperti biasa', 'log tampil', 'berhasil']
    explanation: 'Di lab: `Error response from daemon: configured logging driver does not support reading`. Hal yang sama berlaku untuk driver remote seperti `syslog`/`gelf`/`awslogs` tanpa dual logging. Jangan memakai `none` untuk aplikasi production, karena kamu kehilangan jejak saat insiden.'
    source: https://docs.docker.com/engine/logging/configure/

  - id: b09
    tier: B
    type: command
    objective: debug
    prompt: |
      Container `web` tidak punya shell maupun tool jaringan. Jalankan container debug `nicolaka/netshoot` (sekali pakai,
      interaktif) yang **berbagi PID namespace dan network namespace** dengan `web`.
    answers: ['docker run --rm -it --pid container:web --network container:web nicolaka/netshoot']
    patterns: ['^docker (container )?run (?=.*--pid[= ]container:web( |$))(?=.*(--network|--net)[= ]container:web( |$))((--rm|-it|-ti|-i|-t|--pid[= ]container:web|(--network|--net)[= ]container:web) )+nicolaka/netshoot(:\S+)?$']
    tests:
      pass: ['docker run -it --rm --network=container:web --pid=container:web nicolaka/netshoot']
      fail: ['docker run --rm -it --network container:web nicolaka/netshoot', 'docker exec -it web nicolaka/netshoot', 'docker run --rm -it --pid host --network host nicolaka/netshoot']
    explanation: 'Di lab, sidecar alpine dengan dua opsi itu melihat `PID 1 nginx` milik container target, dan `wget http://127.0.0.1` → `<title>Welcome to nginx!</title>`, karena localhost-nya sama. Filesystem target terbaca lewat `/proc/1/root` (soal berikutnya). Image target tidak diubah sama sekali.'
    source: https://github.com/nicolaka/netshoot

  - id: b10
    tier: B
    type: text
    objective: debug
    prompt: Dari container debug yang berbagi PID namespace, path apa yang dipakai untuk melihat **filesystem** proses utama container target?
    answers: ['/proc/1/root']
    patterns: ['^/proc/1/root/?\S*$']
    tests:
      pass: ['/proc/1/root/etc/nginx']
      fail: ['/proc/1/fs', '/var/lib/docker', '/proc/self/root', '/host']
    explanation: 'Di lab, `ls /proc/1/root/etc/nginx/` dari sidecar → `conf.d fastcgi.conf fastcgi_params ...`. Ini pola standar debug image distroless: bawa tool lewat sidecar, baca file target lewat `/proc/<pid>/root`. Butuh hak yang sama dengan proses target (root vs root).'
    source: https://man7.org/linux/man-pages/man5/proc.5.html

  - id: b11
    tier: B
    type: text
    objective: network
    prompt: '`wget http://db` dari container menghasilkan `bad address ''db''`. Masalahnya ada di lapisan apa?'
    answers: ['DNS / resolusi nama']
    patterns: ['(dns|resolusi|resolve|nama (tidak|tak)|name resolution|nxdomain|jaringan (berbeda|lain)|network (berbeda|lain)|tidak satu network)']
    tests:
      pass: ['dns', 'nama db tidak bisa di-resolve', 'container tidak satu network']
      fail: ['port tertutup', 'firewall', 'database mati', 'connection refused']
    explanation: 'Di lab, `bad address` dan `NXDOMAIN` muncul saat container `db` tidak ada di network yang sama (atau di network `bridge` default, yang tidak punya DNS nama container). Cek dengan `docker network inspect`, nama service/alias, dan apakah target sudah jalan. Ini berbeda dari `Connection refused` (soal berikutnya).'
    source: https://docs.docker.com/engine/network/

  - id: b12
    tier: B
    type: text
    objective: network
    prompt: '`wget http://dj-db:5432` → `can''t connect to remote host (172.18.0.2): Connection refused`. Apa artinya?'
    answers: ['host ditemukan, tetapi tidak ada proses yang listen di port itu']
    patterns: ['(tidak ada|tak ada|belum).*(listen|mendengar|proses|service|aplikasi)', '(port|service).*(tertutup|tidak (listen|jalan|terbuka)|belum (siap|jalan))', 'dns.*(berhasil|ok|benar)']
    tests:
      pass: ['DNS berhasil, tapi tidak ada yang listen di 5432', 'service belum jalan di port itu', 'port tertutup / belum siap']
      fail: ['dns gagal', 'firewall drop', 'timeout']
    explanation: 'Di lab, nama sudah ter-resolve ke `172.18.0.2` tetapi tidak ada proses di port 5432 → **refused** (RST, langsung). Bandingkan dengan `download timed out` ke IP yang tidak menjawab (paket di-drop/host tidak ada). Refused = cek aplikasinya (sudah start? listen di `0.0.0.0` bukan `127.0.0.1`? port benar?). Timeout = cek jalur jaringan/firewall.'
    source: https://docs.docker.com/engine/network/

  - id: b13
    tier: B
    type: text
    objective: network
    prompt: '`docker run -d -p 18555:80 ...` gagal dengan `Bind for 0.0.0.0:18555 failed: port is already allocated`. Status container yang baru itu?'
    answers: ['created']
    tests:
      fail: ['running', 'exited', 'dead', 'restarting']
    explanation: 'Di lab: `p2 status=created`. Container sudah dibuat tetapi gagal start, sehingga namanya terpakai dan `docker run` berikutnya dengan nama sama gagal dengan "name already in use". Cari pemakai port dengan `docker ps --filter publish=18555` (atau `ss -ltnp` di host), lalu hapus container `created` itu.'
    source: https://docs.docker.com/reference/cli/docker/container/run/

  - id: b14
    tier: B
    type: text
    objective: inspect
    prompt: '`docker compose up -d` berhenti dengan `dependency failed to start: container djc10-db-1 is unhealthy`. Status service `app` (yang `depends_on: condition: service_healthy`)?'
    answers: ['created']
    tests:
      fail: ['running', 'exited', 'unhealthy', 'restarting']
    explanation: 'Di lab: `app created`, `db running unhealthy`. App tidak pernah di-start. Lihat penyebabnya di `docker inspect -f ''{{json .State.Health}}'' djc10-db-1` (exit code dan output setiap percobaan healthcheck), bukan di log app. Healthcheck yang salah tulis sama efektifnya dengan database yang mati.'
    source: https://docs.docker.com/reference/compose-file/services/#depends_on

  - id: b15
    tier: B
    type: text
    objective: debug
    prompt: Image amd64 dijalankan di server Linux **arm64** tanpa emulasi (binfmt/QEMU). Pesan error apa yang biasanya muncul?
    answers: ['exec format error']
    patterns: ['exec format error']
    tests:
      pass: ['exec /usr/local/bin/app: exec format error']
      fail: ['permission denied', 'not found', 'illegal instruction', 'segmentation fault']
    explanation: 'Kernel menolak menjalankan binary arsitektur lain. Di Docker Desktop, emulasi QEMU sudah terpasang, sehingga di lab `--platform linux/arm64 alpine uname -m` → `aarch64` (berjalan, tetapi lambat). Di server tanpa emulasi, error ini muncul. Solusinya: build multi-platform (`docker buildx build --platform linux/amd64,linux/arm64`, modul 04).'
    source: https://docs.docker.com/build/building/multi-platform/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: restart
    prompt: |
      Monitoring melaporkan service `app` di server production "naik-turun". `docker ps` menunjukkan
      `Restarting (137) 5 seconds ago`. Tulis langkah diagnosis (satu perintah/poin per baris) sebelum mengubah apa pun,
      lalu satu perbaikan yang masuk akal sesuai temuan.
    checklist:
      - id: logs
        label: Membaca log (termasuk sebelum crash, --tail/--since)
        required: true
        patterns: ['docker\s+(compose\s+)?logs\b']
      - id: oom
        label: Memeriksa OOMKilled / ExitCode
        required: true
        patterns: ['oomkilled', '\.state\.exitcode', '\.state\b']
      - id: restarts
        label: Memeriksa RestartCount / events die
        patterns: ['restartcount', 'docker\s+(system\s+)?events\b']
      - id: stats
        label: Memeriksa pemakaian memori (docker stats / limit)
        patterns: ['docker\s+stats\b', 'hostconfig\.memory', 'mem(ory)?[_ ]?limit']
      - id: fix
        label: Perbaikan sesuai bukti (naikkan limit / perbaiki kebocoran / handle SIGTERM)
        patterns: ['(naikkan|tambah|ubah|set).*(memory|memori|limit)', 'memory:\s*\d+', '(-m|--memory)[= ]?\d+', 'init:\s*true', '--init\b', 'sigterm', 'bocor|leak']
      - id: no-destroy
        label: Tidak menghapus container/volume sebelum diagnosis
        forbid: true
        patterns: ['docker\s+(compose\s+)?(rm|down\s+-v)\b', 'docker\s+(system|volume|container)\s+prune']
    pass_score: 0.6
    solution: |
      ```bash
      docker compose logs --since 30m --tail 200 app                # error sebelum mati?
      docker inspect -f 'exit={{.State.ExitCode}} oom={{.State.OOMKilled}} restarts={{.RestartCount}}' trainme-app-1
      docker events --since 1h --until 0s --filter container=trainme-app-1 --filter event=die \
        --format '{{.Time}} exit={{.Actor.Attributes.exitCode}}'     # seberapa sering?
      docker stats --no-stream trainme-app-1                        # memori mendekati limit?
      ```
      Exit 137 punya dua penyebab utama, dan keduanya terbukti di lab:
      - `OOMKilled=true` (lab: `-m 16m` → 137): naikkan `deploy.resources.limits.memory`, atau cari kebocoran.
        Untuk PHP, cek juga `memory_limit` dan jumlah worker php-fpm × memori per worker.
      - `OOMKilled=false`: SIGKILL karena stop timeout atau dibunuh dari luar. Cek apakah ada proses (healthcheck/
        orkestrator) yang me-restart, dan apakah aplikasi menangani SIGTERM (lab: `sleep` sebagai PID 1 → 137,
        dengan `--init` → 143).

      Restart policy menyembunyikan crash, jadi diagnosis dulu dan jangan `docker compose down -v` (volume ikut terhapus).
    tests:
      pass:
        - |
          docker compose logs --tail 200 app
          docker inspect -f '{{.State.OOMKilled}} {{.State.ExitCode}} {{.RestartCount}}' trainme-app-1
          docker stats --no-stream trainme-app-1
          OOMKilled true, naikkan memory limit ke 512m
        - |
          docker logs --since 30m app
          docker inspect -f '{{json .State}}' app
          docker events --filter event=die --since 1h --until 0s
      fail:
        - |
          docker compose down -v
          docker compose up -d
        - |
          docker restart app
          docker stats
        - |
          docker logs app
          docker system prune -af
    source: https://docs.docker.com/engine/containers/start-containers-automatically/

  - id: c02
    tier: C
    type: scenario
    objective: network
    prompt: |
      Stack Compose `nginx` + `app` (php-fpm, port 9000). Browser mendapat **502 Bad Gateway** dari nginx. Tulis langkah
      diagnosis berurutan (satu perintah/poin per baris), dari container yang jalan sampai koneksi nginx → app:9000.
    checklist:
      - id: ps
        label: Cek status service (compose ps -a)
        required: true
        patterns: ['docker\s+compose\s+ps\b', 'docker\s+ps\b']
      - id: nginx-log
        label: Baca log nginx (upstream error)
        required: true
        patterns: ['docker\s+(compose\s+)?logs\s+.*\bnginx\b']
      - id: app-log
        label: Baca log app
        patterns: ['docker\s+(compose\s+)?logs\s+.*\bapp\b']
      - id: dns
        label: Cek resolusi nama app dari nginx
        patterns: ['(getent\s+hosts|nslookup|ping|dig|host)\s+app\b']
      - id: port
        label: Cek port 9000 terbuka dari nginx
        patterns: ['(nc|netcat)\s+.*\bapp\b.*9000', 'app:9000', '9000.*(listen|terbuka|refused)']
      - id: listen
        label: Cek php-fpm listen di 0.0.0.0/9000 (bukan 127.0.0.1)
        patterns: ['listen\s*=?\s*.*9000', '127\.0\.0\.1', '0\.0\.0\.0']
      - id: network
        label: Cek kedua service di network yang sama
        patterns: ['docker\s+network\s+inspect', 'networks?:']
      - id: no-host-net
        label: Tidak "memperbaiki" dengan network_mode host / privileged
        forbid: true
        patterns: ['network_mode:\s*"?host', '--network[= ]host', 'privileged']
    pass_score: 0.5
    solution: |
      ```bash
      docker compose ps -a                                  # app jalan? healthy? restart loop?
      docker compose logs --tail 50 nginx                   # "connect() failed (111: Connection refused) while connecting to upstream"?
      docker compose logs --tail 50 app                     # php-fpm crash / belum siap?
      docker compose exec nginx getent hosts app            # DNS: nama app → IP?
      docker compose exec nginx nc -zv -w2 app 9000         # port 9000 terbuka?
      docker compose exec app sh -c 'grep -R "^listen" /usr/local/etc/php-fpm.d/'   # listen = 9000, bukan 127.0.0.1:9000
      docker network inspect trainme_internal               # nginx & app satu network?
      ```
      Petakan hasilnya ke pola error dari lab:
      - `bad address` / NXDOMAIN → DNS: service tidak satu network atau salah nama.
      - `Connection refused` → nama ter-resolve, tetapi tidak ada yang listen. php-fpm belum start, crash, atau
        listen di `127.0.0.1`.
      - Timeout → jalur jaringan.

      `network_mode: host` atau privileged menyembunyikan masalah dan membuka port ke luar. Itu bukan perbaikan.
    tests:
      pass:
        - |
          docker compose ps -a
          docker compose logs --tail 50 nginx
          docker compose logs app
          docker compose exec nginx getent hosts app
          docker compose exec nginx nc -zv app 9000
          cek php-fpm listen = 9000 bukan 127.0.0.1
        - |
          docker compose ps
          docker compose logs nginx
          docker compose exec nginx nslookup app
          docker network inspect trainme_internal
      fail:
        - |
          docker compose ps
          docker compose logs nginx
          ganti ke network_mode: host
        - |
          docker compose restart
          docker compose logs app
        - |
          docker compose exec nginx ping app
    source: https://docs.docker.com/engine/network/

  - id: c03
    tier: C
    type: scenario
    objective: disk
    prompt: |
      Server production disk-nya 98% penuh dan Postgres mulai menolak write. Semua disk ada di `/var/lib/docker`.
      Tulis langkah (satu perintah/poin per baris) untuk menemukan pemakan disk dan membebaskan ruang **tanpa** menghapus
      data volume, lalu mencegahnya terulang.
    checklist:
      - id: df
        label: docker system df untuk melihat kategori
        required: true
        patterns: ['docker\s+system\s+df']
      - id: logs
        label: Memeriksa ukuran log container (*-json.log / LogPath)
        required: true
        patterns: ['json\.log', 'logpath', '/var/lib/docker/containers']
      - id: rotate
        label: Mengatur rotasi log (max-size/max-file, daemon.json)
        required: true
        patterns: ['max-size', 'max-file', 'daemon\.json']
      - id: cache
        label: Membersihkan build cache / image tak terpakai dengan filter
        patterns: ['docker\s+(builder|buildx)\s+prune', 'docker\s+image\s+prune', '--filter[= ]until=']
      - id: new-only
        label: Menyadari setelan log baru hanya berlaku untuk container baru (recreate)
        patterns: ['(container\s+baru|recreate|dibuat ulang|up\s+-d\s+--force-recreate|hanya berlaku)']
      - id: no-volumes
        label: Tidak menghapus volume / /var/lib/docker
        forbid: true
        patterns: ['prune\s+.*--volumes', 'docker\s+volume\s+(prune|rm)\b', 'rm\s+-rf?\s+/var/lib/docker', 'down\s+-v\b']
    pass_score: 0.5
    solution: |
      ```bash
      docker system df                                              # kategori mana yang besar?
      sudo du -sh /var/lib/docker/containers/*/*-json.log | sort -h | tail
      docker inspect -f '{{.Name}} {{.LogPath}}' $(docker ps -q)    # log file → container
      sudo truncate -s 0 /var/lib/docker/containers/<id>/<id>-json.log   # darurat: kosongkan, jangan rm
      docker builder prune --filter until=168h                      # build cache > 7 hari
      docker image prune                                            # hanya image dangling
      # pencegahan — /etc/docker/daemon.json:
      #   { "log-driver": "json-file", "log-opts": { "max-size": "10m", "max-file": "3" } }
      sudo systemctl restart docker      # berlaku untuk container BARU → docker compose up -d --force-recreate
      ```
      Temuan lab yang relevan:
      - json-file tanpa opsi tidak berotasi (`cfg=map[]`).
      - Dengan `max-size`/`max-file`, hanya beberapa baris terakhir yang disimpan.
      - Build cache adalah kategori terbesar di laptop lab (5.6 GB).

      `truncate` (bukan `rm`) dipakai karena daemon masih memegang file log yang terbuka. `--volumes`, `docker volume
      prune`, dan `down -v` menghapus data Postgres. Saat disk penuh, satu perintah salah akan mengubah insiden
      menjadi kehilangan data.
    tests:
      pass:
        - |
          docker system df
          du -sh /var/lib/docker/containers/*/*-json.log
          truncate -s 0 log yang besar
          set max-size 10m dan max-file 3 di daemon.json
          recreate container agar setelan berlaku
          docker builder prune --filter until=168h
        - |
          docker system df -v
          docker inspect -f '{{.LogPath}}' app
          tambahkan logging max-size di compose
      fail:
        - |
          docker system df
          docker system prune -af --volumes
        - |
          docker system df
          json.log terlalu besar
          docker volume prune -f
          set max-size 10m
        - |
          rm -rf /var/lib/docker
    source: https://docs.docker.com/engine/logging/drivers/json-file/

---

# Troubleshooting & Operasional

Sebagian besar insiden Docker bisa diselesaikan dengan membaca sinyal yang sudah tersedia: exit code, state, log,
healthcheck, dan event. Modul ini membahas cara membaca setiap sinyal itu, memetakan pesan error yang umum ke
penyebabnya, dan menjaga server tetap sehat (log dan disk). Semua contoh error di bawah adalah keluaran asli dari lab.

> [!NOTE]
> Diuji di Docker Desktop (Windows), Docker Engine 29.4.1, Compose v5.1.3. Path `/var/lib/docker/...` dan `daemon.json`
> berlaku untuk server Linux. Di Docker Desktop, keduanya ada di dalam VM dan diatur lewat Settings → Docker Engine.

## 1. Alur diagnosis

```mermaid
flowchart TD
    S[Service bermasalah] --> P["docker compose ps -a<br/>status? exit code? health?"]
    P -->|exited / restarting| E[Baca exit code + OOMKilled + log]
    P -->|running, unhealthy| H[".State.Health.Log<br/>output healthcheck"]
    P -->|running, healthy| N[Masalah di jalur jaringan<br/>DNS → port → listen]
    P -->|created| C[Gagal start:<br/>port terpakai / dependency unhealthy]
    E --> L["docker logs --since / --tail"]
```

## 2. Exit code

| Exit | Arti | Contoh lab |
|---|---|---|
| 0 | Selesai normal | nginx di-stop (punya handler SIGTERM) → 0 |
| 1–124 | Error dari aplikasi | `exit 3` → 3 |
| **125** | `docker run` sendiri gagal | flag tidak dikenal |
| **126** | Tidak bisa dieksekusi | `exec: "/etc/passwd": permission denied` |
| **127** | Tidak ditemukan | `exec: "nosuchcmd": executable file not found in $PATH` |
| **137** | SIGKILL (128+9) | OOM (`OOMKilled=true`) atau stop timeout |
| **139** | SIGSEGV (128+11) | `Segmentation fault (core dumped)` |
| **143** | SIGTERM (128+15) | `--init` + `sleep` di-stop → 143 dalam 1 detik |

```bash
docker inspect -f 'exit={{.State.ExitCode}} oom={{.State.OOMKilled}} restarts={{.RestartCount}} err={{.State.Error}}' app
```

> [!WARNING]
> **PID 1 mengabaikan SIGTERM** bila tidak memasang handler. Di lab, `sleep` sebagai PID 1 → `docker stop -t 3` butuh
> 4 detik dan exit **137**. Dengan `--init` → 1 detik, exit **143**. Bila setiap deploy terasa "menggantung 10 detik",
> inilah penyebabnya. Perbaiki dengan `init: true`, `exec "$@"` di entrypoint, atau handler SIGTERM di aplikasi.

## 3. Restart policy dan restart loop

| Policy | Perilaku (lab) |
|---|---|
| `no` (default) | Tidak di-restart |
| `on-failure:3` | Dijalankan 4×, lalu `exited`, `RestartCount=3` |
| `always` / `unless-stopped` | Terus mencoba: `Restarting (1) 1 second ago` |

Restart policy membuat service "hidup lagi", tetapi tidak memperbaiki apa pun. Untuk menemukan crash yang tertutup
restart:

```bash
docker events --since 1h --until 0s --filter event=die --format '{{.Time}} {{.Actor.Attributes.name}} exit={{.Actor.Attributes.exitCode}}'
```

## 4. Log

```bash
docker logs --since 15m -t web            # rentang waktu + timestamp
docker logs web 2>&1 >/dev/null           # hanya stderr
docker compose logs --tail 50 app worker  # beberapa service
docker inspect -f '{{.LogPath}}' web      # file log di host
```

| Driver | `docker logs` | Rotasi |
|---|---|---|
| `json-file` (default) | Ya | **Tidak**, kecuali `max-size`/`max-file` (lab: `cfg=map[]`) |
| `local` | Ya | Otomatis |
| `none` | Error: `does not support reading` | — |

```json
// /etc/docker/daemon.json — default untuk container BARU
{ "log-driver": "json-file", "log-opts": { "max-size": "10m", "max-file": "3" } }
```

```yaml
# per service di Compose
logging:
  driver: json-file
  options: { max-size: "10m", max-file: "3" }
```

## 5. Healthcheck dan dependency

```bash
docker inspect -f '{{.State.Health.Status}} streak={{.State.Health.FailingStreak}}' db
docker inspect -f '{{json .State.Health.Log}}' db     # exit code + output setiap percobaan
```

Di lab, healthcheck `wget http://127.0.0.1:81` → `unhealthy`, dengan log
`wget: can't connect to remote host (127.0.0.1): Connection refused`. Karena itu Compose menghentikan `up` dengan
`dependency failed to start: container djc10-db-1 is unhealthy`, dan `app` tertahan di status **created**.

## 6. Debug container

| Situasi | Teknik |
|---|---|
| Container jalan, ada shell | `docker exec -it web sh` |
| Container jalan, **tanpa** shell (distroless/busybox tanpa bash) | Sidecar: `docker run --rm -it --pid container:web --network container:web nicolaka/netshoot` |
| Melihat file container tanpa shell | Dari sidecar: `/proc/1/root/...`, atau `docker cp web:/path -` |
| Entrypoint langsung crash | `docker run --rm -it --entrypoint sh myapp` |
| Container sudah berhenti | `docker logs`, `docker diff`, `docker cp` (masih bisa); `exec` → `is not running` |
| Apa yang diubah di container? | `docker diff web` (A/C/D) |
| Proses & resource | `docker top web`, `docker stats --no-stream` |

## 7. Pesan error jaringan

| Pesan (lab) | Artinya | Cek |
|---|---|---|
| `bad address 'db'` / `NXDOMAIN` | DNS: nama tidak dikenal | Satu network? nama service benar? |
| `Connection refused` | Nama ter-resolve, port tidak ada yang listen | Aplikasi jalan? listen `0.0.0.0`? port benar? |
| `download timed out` | Tidak ada jawaban | Jalur jaringan, firewall, IP salah |
| `port is already allocated` | Port host dipakai container lain | `docker ps --filter publish=PORT`; container baru tertinggal `created` |
| `exec format error` | Arsitektur image ≠ host | Build multi-platform |

## 8. Disk

```bash
docker system df                       # baca dulu (lab: build cache 5.6 GB)
docker builder prune --filter until=168h
docker image prune                     # hanya dangling
```

> [!DANGER]
> Perintah yang menghapus **data**: `docker system prune --volumes`, `docker volume prune`, `docker compose down -v`,
> `rm -rf /var/lib/docker`. Saat disk penuh dan panik, jangan jalankan salah satunya tanpa tahu isi volumenya. Untuk
> log yang membengkak, `truncate -s 0` file-nya (jangan `rm`, karena daemon masih memegangnya), lalu pasang rotasi.

## 9. Ringkasan

| Gejala | Perintah pertama |
|---|---|
| Container hilang dari `ps` | `docker compose ps -a`, lalu exit code |
| Exit 137 | `.State.OOMKilled` |
| Stop lambat | PID 1 + SIGTERM → `init: true` / `exec` |
| Naik-turun | `RestartCount`, `docker events --filter event=die` |
| Unhealthy | `.State.Health.Log` |
| 502 / tidak bisa konek | DNS → refused vs timeout → listen address |
| Disk penuh | `docker system df`, ukuran `*-json.log`, rotasi log |
