---
format: 1
id: docker-container-cli
course:
  slug: docker
  title: Docker
  description: Container dari nol sampai production — CLI, image, storage, network, Compose, keamanan, dan operasional.
title: Container & CLI Dasar
summary: Image vs container, siklus hidup container, run/ps/logs/exec/inspect, port publishing, environment, restart policy, dan kenapa perubahan di dalam container hilang.
order: 1
version: 1
status: verified
updated: '2026-11-20'
tested_on:
  - Docker Desktop (Windows) — Docker Engine & CLI 29.4.1
  - Image nginx:1.27-alpine, alpine:3.20, redis:7-alpine
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: concepts
    text: Membedakan image, container, dan registry; memahami container sebagai proses
  - id: lifecycle
    text: Menjalankan, menghentikan, dan menghapus container (run, ps, stop, rm)
  - id: observe
    text: Mengamati container (logs, exec, inspect, top, stats, diff)
  - id: ports
    text: Mempublikasikan port dengan benar (-p, EXPOSE, bind localhost)
  - id: env
    text: Memberi konfigurasi lewat environment (-e, --env-file) dan risikonya
  - id: restart
    text: Memilih restart policy yang tepat
  - id: ephemeral
    text: Memahami writable layer yang hilang saat container dihapus
  - id: troubleshoot
    text: Mendiagnosis container yang restart terus atau tidak bisa diakses
sources:
  - title: Docker Docs — What is a container?
    url: https://docs.docker.com/get-started/docker-concepts/the-basics/what-is-a-container/
  - title: docker container run
    url: https://docs.docker.com/reference/cli/docker/container/run/
  - title: Docker Docs — Running containers (restart policies, ports, env)
    url: https://docs.docker.com/engine/containers/run/
  - title: Docker Docs — Start containers automatically (restart policies)
    url: https://docs.docker.com/engine/containers/start-containers-automatically/
  - title: docker container logs
    url: https://docs.docker.com/reference/cli/docker/container/logs/
  - title: docker container exec
    url: https://docs.docker.com/reference/cli/docker/container/exec/
  - title: docker container inspect & Go templates (--format)
    url: https://docs.docker.com/engine/cli/formatting/
  - title: docker container diff
    url: https://docs.docker.com/reference/cli/docker/container/diff/
  - title: docker container update
    url: https://docs.docker.com/reference/cli/docker/container/update/
  - title: Docker Docs — Port publishing and mapping
    url: https://docs.docker.com/engine/network/port-publishing/
  - title: Dockerfile reference — EXPOSE
    url: https://docs.docker.com/reference/dockerfile/#expose
  - title: nginx official image — logs ke stdout/stderr
    url: https://github.com/nginxinc/docker-nginx/blob/master/mainline/alpine-slim/Dockerfile
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: command
    objective: lifecycle
    prompt: Jalankan `nginx:1.27-alpine` di **background** dengan nama `web`, dan publish port 80 container ke port **8080** host.
    answers: ['docker run -d --name web -p 8080:80 nginx:1.27-alpine']
    patterns: ['^docker (container )?run (?=.*(-d|--detach)( |$))(?=.*--name[= ]web( |$))(?=.*(-p|--publish)[= ]?8080:80( |$))((-d|--detach|--name[= ]web|(-p|--publish)[= ]?8080:80) )+nginx:1\.27-alpine$']
    tests:
      pass: ['docker run --name web -d -p 8080:80 nginx:1.27-alpine', 'docker container run -d -p 8080:80 --name=web nginx:1.27-alpine']
      fail: ['docker run -d --name web -p 80:8080 nginx:1.27-alpine', 'docker run --name web nginx:1.27-alpine', 'docker start web -p 8080:80']
    explanation: 'Format `-p HOST:CONTAINER`. Di lab: `0.0.0.0:8080->80/tcp`, lalu `curl localhost:8080` → 200. Semua opsi ditulis **sebelum** nama image, karena apa pun setelah image dianggap perintah untuk container.'
    source: https://docs.docker.com/reference/cli/docker/container/run/

  - id: a02
    tier: A
    type: command
    objective: lifecycle
    prompt: Tampilkan container yang **sedang berjalan**.
    answers: ['docker ps']
    patterns: ['^docker (ps|container ls|container ps|container list)$']
    tests:
      pass: ['docker container ls']
      fail: ['docker ps -a', 'docker images', 'docker container']
    explanation: 'Kolom penting: NAMES, IMAGE, STATUS (`Up 2 minutes`, `Restarting (1)`), dan PORTS. Format sendiri: `docker ps --format ''table {{.Names}}\t{{.Status}}''`.'
    source: https://docs.docker.com/reference/cli/docker/container/ls/

  - id: a03
    tier: A
    type: command
    objective: lifecycle
    prompt: Tampilkan **semua** container, termasuk yang sudah berhenti (Exited/Created).
    answers: ['docker ps -a']
    patterns: ['^docker (ps|container ls) (-a|--all)$']
    tests:
      pass: ['docker ps --all', 'docker container ls -a']
      fail: ['docker ps', 'docker images -a', 'docker ps -q']
    explanation: 'Container yang berhenti tetap ada (dan memakan disk) sampai di-`rm`. Di lab, `docker run alpine echo hello` langsung `Exited (0)`, karena prosesnya selesai.'
    source: https://docs.docker.com/reference/cli/docker/container/ls/

  - id: a04
    tier: A
    type: command
    objective: observe
    prompt: Tampilkan **50 baris terakhir** log container `web`, lalu terus ikuti baris baru.
    answers: ['docker logs -f --tail 50 web']
    patterns: ['^docker (container )?logs (?=.*(-f|--follow)( |$))(?=.*(--tail|-n)[= ]?50( |$))((-f|--follow|(--tail|-n)[= ]?50|-t|--timestamps) )+web$']
    tests:
      pass: ['docker logs --tail 50 -f web', 'docker logs -n 50 --follow web']
      fail: ['docker logs web', 'docker logs --tail 50 web', 'docker attach web']
    explanation: '`docker logs` menampilkan stdout/stderr milik proses utama container. Opsi lain: `--since 10m`, `-t` (timestamp). `docker attach` menyambung ke terminal proses itu, dan Ctrl+C di sana bisa menghentikan container.'
    source: https://docs.docker.com/reference/cli/docker/container/logs/

  - id: a05
    tier: A
    type: command
    objective: observe
    prompt: Buka **shell interaktif** (`sh`) di dalam container `web` yang sedang berjalan.
    answers: ['docker exec -it web sh']
    patterns: ['^docker (container )?exec (-it|-ti|-i -t|-t -i) web (sh|/bin/sh|bash|/bin/bash|ash)$']
    tests:
      pass: ['docker exec -ti web /bin/sh']
      fail: ['docker run -it web sh', 'docker attach web', 'docker exec web']
    explanation: '`-i` menjaga stdin tetap terbuka, `-t` mengalokasikan TTY. Image Alpine tidak punya bash, jadi pakai `sh`. `docker run` membuat container **baru** dari image, bukan masuk ke container yang sudah ada.'
    source: https://docs.docker.com/reference/cli/docker/container/exec/

  - id: a06
    tier: A
    type: command
    objective: lifecycle
    prompt: Hentikan container `web` dengan **sopan** (sinyal stop dulu, baru SIGKILL bila tidak berhenti).
    answers: ['docker stop web']
    patterns: ['^docker (container )?stop( (-t|--timeout)[= ]?\d+)? web$']
    tests:
      pass: ['docker stop -t 30 web']
      fail: ['docker kill web', 'docker rm web', 'docker pause web']
    explanation: '`stop` mengirim `StopSignal` image (nginx: `SIGQUIT`, mayoritas lainnya `SIGTERM`), lalu SIGKILL setelah timeout. Di lab: nginx berhenti dalam <1 detik dengan exit 0, sedangkan `docker kill` → exit 137. Detail PID 1 dan sinyal ada di modul Linux Process & Signal.'
    source: https://docs.docker.com/reference/cli/docker/container/stop/

  - id: a07
    tier: A
    type: command
    objective: lifecycle
    prompt: Hapus container `web` yang **sudah berhenti**.
    answers: ['docker rm web']
    patterns: ['^docker (container )?rm web$']
    tests:
      pass: ['docker container rm web']
      fail: ['docker rmi web', 'docker stop web', 'docker delete web']
    explanation: '`rm` menghapus container, sedangkan `rmi` menghapus **image**. Container yang dihapus kehilangan writable layer-nya. Data yang perlu bertahan harus ada di volume (modul Storage).'
    source: https://docs.docker.com/reference/cli/docker/container/rm/

  - id: a08
    tier: A
    type: command
    objective: lifecycle
    prompt: Hapus container `web` yang **masih berjalan** dalam satu perintah.
    answers: ['docker rm -f web']
    patterns: ['^docker (container )?rm (-f|--force) web$']
    tests:
      pass: ['docker rm --force web']
      fail: ['docker rm web', 'docker rmi -f web', 'docker kill -f web']
    explanation: 'Di lab, `docker rm` pada container yang berjalan ditolak: `container is running: stop the container before removing or force remove`. `-f` mengirim SIGKILL, jadi tidak ada graceful shutdown. Untuk aplikasi yang butuh cleanup, lebih baik `stop` lalu `rm`.'
    source: https://docs.docker.com/reference/cli/docker/container/rm/

  - id: a09
    tier: A
    type: command
    objective: ephemeral
    prompt: Jalankan `cat /etc/alpine-release` di container `alpine:3.20` sekali pakai yang **otomatis terhapus** setelah selesai.
    answers: ['docker run --rm alpine:3.20 cat /etc/alpine-release']
    patterns: ['^docker (container )?run --rm alpine:3\.20 cat /etc/alpine-release$']
    tests:
      fail: ['docker run alpine:3.20 cat /etc/alpine-release', 'docker exec --rm alpine:3.20 cat /etc/alpine-release', 'docker run alpine:3.20 --rm cat /etc/alpine-release']
    explanation: 'Di lab: `3.20.10`, dan tidak ada container tersisa di `docker ps -a`. Tanpa `--rm`, setiap perintah sekali pakai meninggalkan container `Exited` yang menumpuk. `--rm` setelah nama image dikirim sebagai argumen ke container, bukan ke Docker.'
    source: https://docs.docker.com/reference/cli/docker/container/run/

  - id: a10
    tier: A
    type: command
    objective: env
    prompt: Jalankan image `myapp` di background dengan environment variable `APP_ENV=production`.
    answers: ['docker run -d -e APP_ENV=production myapp']
    patterns: ['^docker (container )?run (?=.*(-d|--detach)( |$))(?=.*(-e|--env)[= ]?APP_ENV=production( |$))((-d|--detach|(-e|--env)[= ]?APP_ENV=production) )+myapp$']
    tests:
      pass: ['docker run -e APP_ENV=production -d myapp', 'docker run -d --env APP_ENV=production myapp']
      fail: ['docker run -d APP_ENV=production myapp', 'docker run -d myapp -e APP_ENV=production', 'docker run -d --env-file APP_ENV=production myapp']
    explanation: '`-e NAMA=NILAI` bisa diulang. Setelah container dibuat, environment **tidak bisa diubah**, karena `docker update` tidak punya opsi env (dicek di lab). Untuk mengubahnya, container harus dibuat ulang.'
    source: https://docs.docker.com/engine/containers/run/#environment-variables

  - id: a11
    tier: A
    type: command
    objective: ports
    prompt: Tampilkan pemetaan port (container → host) milik container `web`.
    answers: ['docker port web']
    patterns: ['^docker (container )?port web( \d+(/tcp)?)?$']
    tests:
      pass: ['docker port web 80']
      fail: ['docker ps web', 'docker inspect web', 'netstat web']
    explanation: 'Di lab: `80/tcp -> 0.0.0.0:8080` dan `80/tcp -> [::]:8080`. Artinya port terbuka di **semua** interface host, termasuk IPv6.'
    source: https://docs.docker.com/reference/cli/docker/container/port/

  - id: a12
    tier: A
    type: text
    objective: concepts
    prompt: Bila kamu menulis `docker run alpine` tanpa tag, tag apa yang dipakai?
    answers: ['latest', ':latest']
    tests:
      fail: ['stable', 'newest', '1.0', 'edge']
    explanation: 'Di lab, `docker run alpine` menarik `alpine:latest`, terpisah dari `alpine:3.20` yang sudah ada. `latest` hanyalah nama tag. Ia tidak dijamin terbaru dan bisa berubah kapan saja, sehingga build dan deploy jadi tidak reproducible. Selalu pin tag versi.'
    source: https://docs.docker.com/reference/cli/docker/image/tag/

  - id: a13
    tier: A
    type: text
    objective: concepts
    prompt: Sebuah container berhenti ketika proses dengan **PID berapa** di dalamnya keluar?
    answers: ['1', 'pid 1']
    tests:
      fail: ['0', '2', '100']
    explanation: 'Container adalah proses yang terisolasi, bukan VM. Di lab, `docker run --rm alpine ps` menunjukkan `1 root ps`: perintahmu sendiri menjadi PID 1. Karena itu `docker run ubuntu` langsung Exited (bash tanpa TTY selesai), sedangkan nginx tetap berjalan (`daemon off;` di foreground).'
    source: https://docs.docker.com/get-started/docker-concepts/the-basics/what-is-a-container/

  - id: a14
    tier: A
    type: command
    objective: observe
    prompt: Tampilkan pemakaian CPU dan memori container **sekali saja** (tidak live terus-menerus).
    answers: ['docker stats --no-stream']
    patterns: ['^docker (container )?stats --no-stream( \S+)*$']
    tests:
      pass: ['docker stats --no-stream web']
      fail: ['docker stats', 'docker top', 'docker ps -s']
    explanation: 'Di lab: `dj-web cpu=0.00% mem=11.4MiB / 5.645GiB`. Tanpa `--no-stream`, outputnya refresh terus seperti `top`, sehingga tidak cocok untuk skrip. `docker top web` menampilkan proses di dalam container.'
    source: https://docs.docker.com/reference/cli/docker/container/stats/

  - id: a15
    tier: A
    type: command
    objective: concepts
    prompt: Unduh image `redis` dengan tag `7-alpine` dari registry tanpa menjalankannya.
    answers: ['docker pull redis:7-alpine']
    patterns: ['^docker (image )?pull redis:7-alpine$']
    tests:
      pass: ['docker image pull redis:7-alpine']
      fail: ['docker pull redis', 'docker run redis:7-alpine', 'docker get redis:7-alpine']
    explanation: 'Image disimpan sebagai layer, dan layer yang sama dipakai bersama antar image. `docker run` otomatis pull bila image belum ada. Pull lebih dulu berguna untuk memperkecil downtime saat deploy.'
    source: https://docs.docker.com/reference/cli/docker/image/pull/

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: command
    objective: ports
    prompt: Jalankan `redis:7-alpine` di background dengan port 6379 yang di-publish **hanya ke localhost** host.
    answers: ['docker run -d -p 127.0.0.1:6379:6379 redis:7-alpine']
    patterns: ['^docker (container )?run (?=.*(-d|--detach)( |$))(?=.*(-p|--publish)[= ]?127\.0\.0\.1:6379:6379( |$))((-d|--detach|(-p|--publish)[= ]?127\.0\.0\.1:6379:6379|--name[= ]\S+) )+redis:7-alpine$']
    tests:
      pass: ['docker run -p 127.0.0.1:6379:6379 -d redis:7-alpine', 'docker run -d --name cache -p 127.0.0.1:6379:6379 redis:7-alpine']
      fail: ['docker run -d -p 6379:6379 redis:7-alpine', 'docker run -d -p 6379:127.0.0.1:6379 redis:7-alpine', 'docker run -d --expose 6379 redis:7-alpine']
    explanation: 'Tanpa IP, Docker bind ke `0.0.0.0` dan `[::]` (lab: `docker port`). Di Linux, port itu juga melewati ufw (modul Linux Networking). Redis tanpa password yang terbuka ke internet adalah target otomatis. Bila hanya diakses container lain, jangan publish sama sekali (modul Networking).'
    source: https://docs.docker.com/engine/network/port-publishing/

  - id: b02
    tier: B
    type: text
    objective: ports
    prompt: 'Image nginx punya `EXPOSE 80`. Kamu menjalankannya **tanpa** `-p`. Apakah port itu bisa diakses dari host lewat `localhost`? (ya/tidak)'
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: '`EXPOSE` hanya dokumentasi/metadata. Di lab, kolom PORTS hanya `80/tcp` tanpa pemetaan, dan curl ke host gagal (`Could not connect to server`). Port baru terbuka ke host dengan `-p` (atau `-P` untuk publish semua port EXPOSE ke port acak).'
    source: https://docs.docker.com/reference/dockerfile/#expose

  - id: b03
    tier: B
    type: command
    objective: observe
    prompt: Tampilkan **alamat IP** container `web` dengan `docker inspect` dan template Go, melalui map `.NetworkSettings.Networks` (bekerja di Docker 29).
    answers: ['docker inspect -f ''{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}'' web']
    patterns: ['^docker (container )?inspect (-f|--format)[= ]?\{\{ ?range \.NetworkSettings\.Networks ?\}\}\{\{ ?\.IPAddress ?\}\}( |\{\{println\}\})?\{\{ ?end ?\}\} web$']
    tests:
      pass: ['docker inspect --format "{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}" web']
      fail: ['docker inspect -f ''{{.NetworkSettings.IPAddress}}'' web', 'docker port web', 'docker inspect web.IPAddress']
    explanation: 'Temuan lab: di Docker 29, `{{.NetworkSettings.IPAddress}}` gagal dengan `map has no entry for key "IPAddress"`, karena field lama di level atas sudah dihapus. IP kini hanya ada per network (lab: `bridge=172.17.0.2`). Banyak tutorial dan skrip lama rusak karena hal ini. IP container berubah setiap kali container dibuat ulang, jadi pakai nama/DNS (modul Networking).'
    source: https://docs.docker.com/engine/cli/formatting/

  - id: b04
    tier: B
    type: command
    objective: observe
    prompt: Tampilkan **exit code** terakhir container `web` dengan `docker inspect` dan template Go.
    answers: ['docker inspect -f ''{{.State.ExitCode}}'' web']
    patterns: ['^docker (container )?inspect (-f|--format)[= ]?\{\{ ?\.State\.ExitCode ?\}\} web$']
    tests:
      pass: ['docker inspect --format "{{.State.ExitCode}}" web']
      fail: ['docker logs web', 'docker ps web', 'docker inspect web.State.ExitCode']
    explanation: 'Field `.State` lain: `.Status`, `.OOMKilled`, `.StartedAt`, `.FinishedAt`, dan `.RestartCount` (di level atas). Di lab: `docker stop` nginx → 0, `docker kill` → 137. Ingat: 137 = SIGKILL (OOM atau timeout stop), 143 = SIGTERM.'
    source: https://docs.docker.com/engine/cli/formatting/

  - id: b05
    tier: B
    type: text
    objective: lifecycle
    prompt: Sinyal apa yang dikirim `docker kill` secara default?
    answers: ['SIGKILL', 'KILL', '9']
    patterns: ['^(sig)?kill( ?\(?9\)?)?$']
    tests:
      fail: ['SIGTERM', 'SIGQUIT', '15', 'SIGINT']
    explanation: 'Di lab, `docker kill` → exit **137**, sedangkan `docker stop` nginx → exit 0 (graceful, karena `StopSignal=SIGQUIT`). `docker kill -s HUP web` mengirim sinyal lain, mis. untuk reload config.'
    source: https://docs.docker.com/reference/cli/docker/container/kill/

  - id: b06
    tier: B
    type: text
    objective: restart
    prompt: Restart policy apa yang me-restart container saat crash dan saat Docker daemon/host menyala ulang, **kecuali** container itu sebelumnya dihentikan manual?
    answers: ['unless-stopped']
    tests:
      fail: ['always', 'on-failure', 'no', 'on-abnormal']
    explanation: 'Restart policy: `no` (default, lab: `policy=no`), `on-failure[:N]` (hanya exit ≠ 0), `always` (selalu, termasuk setelah daemon restart walaupun sebelumnya di-stop manual), dan `unless-stopped`. `docker stop` menghentikan restart loop untuk keduanya (lab: `always` → `exited` setelah stop).'
    source: https://docs.docker.com/engine/containers/start-containers-automatically/

  - id: b07
    tier: B
    type: text
    objective: restart
    prompt: Container dengan `--restart on-failure` keluar dengan **exit code 0**. Berapa kali Docker me-restart-nya?
    answers: ['0', 'nol', 'tidak pernah']
    patterns: ['^(0|nol|tidak (pernah|sama sekali)|0 kali)$']
    tests:
      fail: ['1', '3', 'terus', 'selamanya']
    explanation: 'Di lab: `on-failure` + `exit 0` → `restarts=0`. `on-failure:3` + `exit 1` → `restarts=3`, total 4 kali jalan (4 baris log), lalu berhenti dengan status `exited`. `always` + `exit 0` → 4 restart dalam 6 detik.'
    source: https://docs.docker.com/engine/containers/start-containers-automatically/

  - id: b08
    tier: B
    type: command
    objective: restart
    prompt: Ubah restart policy container `web` yang **sudah ada** menjadi `unless-stopped` tanpa membuat ulang container.
    answers: ['docker update --restart unless-stopped web']
    patterns: ['^docker (container )?update --restart[= ]unless-stopped web$']
    tests:
      pass: ['docker update --restart=unless-stopped web']
      fail: ['docker restart --policy unless-stopped web', 'docker run --restart unless-stopped web', 'docker update unless-stopped web']
    explanation: '`docker update` bisa mengubah restart policy dan limit resource (CPU, memori) pada container yang ada (lab: `unless-stopped`), tetapi **tidak** bisa mengubah image, port, volume, atau environment. Untuk itu, container harus dibuat ulang. Ini salah satu alasan memakai Compose.'
    source: https://docs.docker.com/reference/cli/docker/container/update/

  - id: b09
    tier: B
    type: command
    objective: env
    prompt: Jalankan image `myapp` di background dengan semua variable dari file `.env` di direktori saat ini.
    answers: ['docker run -d --env-file .env myapp']
    patterns: ['^docker (container )?run (?=.*(-d|--detach)( |$))(?=.*--env-file[= ]\.?/?\.env( |$))((-d|--detach|--env-file[= ]\.?/?\.env) )+myapp$']
    tests:
      pass: ['docker run --env-file .env -d myapp', 'docker run -d --env-file=./.env myapp']
      fail: ['docker run -d -e .env myapp', 'docker run -d myapp --env-file .env', 'docker run --env .env -d myapp']
    explanation: 'Di lab, file berisi `APP_ENV=prod` dan `APP_DEBUG=false` → container mencetak `prod false`. Pastikan file `.env` tidak ikut masuk ke image (`.dockerignore`, modul Dockerfile) atau ke git.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#env

  - id: b10
    tier: B
    type: text
    objective: env
    prompt: '`.env` berisi `QUOTED="with quotes"`. Setelah `docker run --env-file .env ...`, apakah nilai `$QUOTED` di container **termasuk** tanda kutipnya? (ya/tidak)'
    answers: ['ya', 'yes']
    tests:
      fail: ['tidak', 'no']
    explanation: 'Di lab, container mencetak `["with quotes"]`, dengan tanda kutip ikut menjadi bagian nilai. `docker run --env-file` membaca setiap baris apa adanya, tidak seperti shell yang membuang kutip. Password yang ditulis `DB_PASSWORD="abc"` akan gagal autentikasi. Jangan berasumsi semua tool membaca `.env` dengan cara yang sama.'
    source: https://docs.docker.com/reference/cli/docker/container/run/#env

  - id: b11
    tier: B
    type: text
    objective: env
    prompt: Apakah nilai yang diberikan lewat `-e DB_PASSWORD=...` bisa dibaca siapa pun yang punya akses `docker inspect` ke container itu? (ya/tidak)
    answers: ['ya', 'yes', 'bisa']
    tests:
      fail: ['tidak', 'no', 'tidak bisa']
    explanation: 'Di lab, `docker inspect -f ''{{range .Config.Env}}...''` langsung menampilkan `REDIS_PASSWORD=S3cret`. Siapa pun di grup `docker` bisa melihatnya (dan grup itu setara root). Environment juga bisa bocor lewat log crash atau `/proc/1/environ`. Untuk production, pakai mekanisme secret (Compose/Swarm secrets, file mount mode 400) — dibahas di modul Keamanan.'
    source: https://docs.docker.com/engine/containers/run/#environment-variables

  - id: b12
    tier: B
    type: command
    objective: observe
    prompt: Salin file `/etc/nginx/conf.d/default.conf` dari container `web` ke direktori saat ini di host.
    answers: ['docker cp web:/etc/nginx/conf.d/default.conf .']
    patterns: ['^docker (container )?cp web:/etc/nginx/conf\.d/default\.conf (\.|\./|\./default\.conf|default\.conf)$']
    tests:
      pass: ['docker cp web:/etc/nginx/conf.d/default.conf ./default.conf']
      fail: ['docker cp ./default.conf web:/etc/nginx/conf.d/default.conf', 'cp web:/etc/nginx/conf.d/default.conf .', 'docker copy web:/etc/nginx/conf.d/default.conf .']
    explanation: 'Format `docker cp CONTAINER:PATH HOST_PATH` (atau sebaliknya). Container tidak harus berjalan. Di Windows, path host harus path Windows. Di lab, `/tmp/...` dari Git Bash gagal: `directory "D:\tmp" does not exist`.'
    source: https://docs.docker.com/reference/cli/docker/container/cp/

  - id: b13
    tier: B
    type: text
    objective: observe
    prompt: 'Container nginx resmi menulis access log ke `/var/log/nginx/access.log`, tetapi lognya tetap muncul di `docker logs`. File itu sebenarnya symlink ke apa?'
    answers: ['/dev/stdout', 'stdout']
    tests:
      fail: ['/dev/null', '/var/log/syslog', 'journald', '/dev/stderr']
    explanation: 'Di lab: `access.log -> /dev/stdout` dan `error.log -> /dev/stderr`. `docker logs` hanya menangkap stdout/stderr proses utama. Aplikasi yang menulis ke file biasa di dalam container (mis. `storage/logs/laravel.log`) tidak terlihat di `docker logs`, dan lognya hilang saat container dihapus.'
    source: https://github.com/nginxinc/docker-nginx/blob/master/mainline/alpine-slim/Dockerfile

  - id: b14
    tier: B
    type: command
    objective: observe
    prompt: Jalankan `php artisan cache:clear` di container `app` yang sedang berjalan, **sebagai user `www-data`**.
    answers: ['docker exec -u www-data app php artisan cache:clear']
    patterns: ['^docker (container )?exec (-u|--user)[= ]?www-data (-it |-i |-t )?app php artisan cache:clear$', '^docker (container )?exec (-it |-i |-t )?(-u|--user)[= ]?www-data app php artisan cache:clear$']
    tests:
      pass: ['docker exec --user www-data app php artisan cache:clear', 'docker exec -it -u www-data app php artisan cache:clear']
      fail: ['docker exec app php artisan cache:clear', 'docker run -u www-data app php artisan cache:clear', 'docker exec app su www-data php artisan cache:clear']
    explanation: 'Tanpa `-u`, exec berjalan sebagai user default image, seringkali root (lab: `uid=0(root)`, dengan `-u nginx` → `uid=101(nginx)`). File cache baru jadi milik root, dan aplikasi yang berjalan sebagai `www-data` error 500, persis seperti di modul Linux User & sudo.'
    source: https://docs.docker.com/reference/cli/docker/container/exec/

  - id: b15
    tier: B
    type: text
    objective: ephemeral
    prompt: Kamu mengedit file di dalam container lewat `docker exec`, lalu container di-`rm` dan dibuat ulang dari image yang sama. Apakah perubahanmu masih ada? (ya/tidak)
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Setiap container punya writable layer tipis di atas layer image yang read-only (lab: `size=81.9kB (virtual 52.7MB)`). Di lab, `new.html` yang dibuat lewat exec → 200; setelah container dibuat ulang → 404. Konfigurasi harus ada di image (Dockerfile) atau di-mount; data harus ada di volume.'
    source: https://docs.docker.com/get-started/docker-concepts/the-basics/what-is-a-container/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: troubleshoot
    prompt: |
      Container Laravel `api` restart terus. Tulis langkah (satu perintah per baris) untuk mendiagnosis penyebabnya,
      lalu memperbaikinya dengan benar, dan memverifikasi hasilnya. Variabel yang kurang ada di file `.env.production`.
    context: |
      $ docker ps -a --filter name=api --format '{{.Names}}  {{.Status}}'
      api  Restarting (1) 4 seconds ago
      $ docker inspect -f '{{.RestartCount}} {{.HostConfig.RestartPolicy.Name}}' api
      27 always
    checklist:
      - id: status
        label: Melihat status semua container (docker ps -a)
        patterns: ['docker\s+(ps|container\s+ls)\s+(.*\s)?(-\S*a\b|--all)']
      - id: logs
        label: Membaca log container (docker logs)
        required: true
        patterns: ['docker\s+(container\s+)?logs\s+']
      - id: inspect
        label: Memeriksa state/exit code/env (docker inspect)
        patterns: ['docker\s+(container\s+)?inspect\s+.*(state|exitcode|restartcount|env|oomkilled)']
      - id: remove
        label: Menghapus container lama (env tidak bisa diubah di tempat)
        required: true
        patterns: ['docker\s+(container\s+)?rm\s+(-f\s+|--force\s+)?api\b', 'docker\s+(container\s+)?stop\s+api\b']
      - id: recreate
        label: Membuat ulang dengan env yang benar (--env-file / -e)
        required: true
        patterns: ['docker\s+(container\s+)?run\s+.*(--env-file[= ]\S*\.env|-e\s+app_key=|--env\s+app_key=)']
      - id: verify
        label: Verifikasi (docker ps / docker logs -f)
        patterns: ['docker\s+ps\b', 'docker\s+(container\s+)?logs\s+(-\S+\s+)*-f']
      - id: no-exec-export
        label: Tidak mencoba memperbaiki env lewat docker exec export (tidak persisten)
        forbid: true
        patterns: ['docker\s+exec\s+.*export\s+']
      - id: no-prune
        label: Tidak menjalankan prune global (bisa menghapus container/volume orang lain)
        forbid: true
        patterns: ['docker\s+(system|container|volume)\s+prune']
    pass_score: 0.6
    solution: |
      ```bash
      docker logs --tail 20 api
      #   No application encryption key has been specified.
      docker inspect -f '{{.State.ExitCode}} {{.RestartCount}}' api
      docker inspect -f '{{range .Config.Env}}{{println .}}{{end}}' api | grep -c APP_KEY    # 0
      docker rm -f api
      docker run -d --name api --restart unless-stopped --env-file .env.production myorg/api:1.4.2
      docker logs -f --tail 20 api
      docker ps --filter name=api
      ```
      `Restarting (1)` berarti proses utama keluar dengan exit 1, lalu Docker langsung me-restart-nya (`always`,
      sudah 27 kali). Jawabannya hampir selalu ada di `docker logs`. Environment adalah bagian dari **konfigurasi
      container** yang ditetapkan saat `docker run`. `docker update` tidak bisa mengubahnya (dicek di lab), dan
      `docker exec ... export` hanya berlaku di proses exec itu. Jadi container harus dibuat ulang. Hindari
      `docker container prune`/`system prune` saat troubleshooting: perintah itu menghapus **semua** container yang
      berhenti di host, bukan hanya milikmu.
    tests:
      pass:
        - |
          docker ps -a
          docker logs --tail 50 api
          docker inspect -f '{{.State.ExitCode}}' api
          docker rm -f api
          docker run -d --name api --env-file .env.production myorg/api:1.4.2
          docker logs -f api
        - |
          docker logs api
          docker stop api
          docker run -d --name api2 -e APP_KEY=base64:xxx myorg/api:1.4.2
          docker ps
      fail:
        - |
          docker logs api
          docker exec api sh -c 'export APP_KEY=base64:xxx'
          docker restart api
        - |
          docker restart api
          docker ps
        - |
          docker logs api
          docker system prune -af
          docker run -d --name api --env-file .env.production myorg/api:1.4.2
    source: https://docs.docker.com/reference/cli/docker/container/update/

  - id: c02
    tier: C
    type: scenario
    objective: ports
    prompt: |
      Developer menjalankan nginx di laptop, tapi `curl localhost:8000` gagal. Jelaskan lewat perintah diagnosis apa yang
      salah, lalu tulis perintah perbaikan dan verifikasinya (satu per baris). Container ini hanya untuk akses lokal.
    context: |
      $ docker run -d --name web -p 8000:8080 nginx:1.27-alpine
      $ docker ps --filter name=web --format '{{.Ports}}'
      0.0.0.0:8000->8080/tcp, [::]:8000->8080/tcp
      $ curl -sS localhost:8000
      curl: (56) Recv failure: Connection was aborted
    checklist:
      - id: diagnose
        label: Memeriksa pemetaan port / port yang listen di container (docker port, inspect, exec ss/netstat)
        required: true
        patterns: ['docker\s+(container\s+)?port\s+web', 'docker\s+(container\s+)?inspect\s+.*(port|exposedports)', 'docker\s+exec\s+web\s+.*(netstat|ss|cat\s+/etc/nginx)', 'docker\s+ps\b']
      - id: remove
        label: Menghapus container yang salah (port tidak bisa diubah di tempat)
        required: true
        patterns: ['docker\s+(container\s+)?rm\s+(-f\s+|--force\s+)?web\b', 'docker\s+(container\s+)?stop\s+web\b']
      - id: recreate
        label: Membuat ulang dengan -p HOST:80 (port container yang benar)
        required: true
        patterns: ['docker\s+(container\s+)?run\s+.*(-p|--publish)[= ]?(127\.0\.0\.1:)?8000:80(\s|/tcp|$)']
      - id: localhost
        label: Bind ke 127.0.0.1 karena hanya untuk akses lokal
        patterns: ['(-p|--publish)[= ]?127\.0\.0\.1:8000:80\b']
      - id: verify
        label: Verifikasi dengan curl / docker logs
        patterns: ['curl\s+.*(localhost|127\.0\.0\.1):8000', 'docker\s+(container\s+)?logs\s+web']
      - id: no-host-network
        label: Tidak "memperbaiki" dengan --network host atau --privileged
        forbid: true
        patterns: ['--network[= ]host', '--net[= ]host', '--privileged']
    pass_score: 0.6
    solution: |
      ```bash
      docker port web                     # 8080/tcp -> 0.0.0.0:8000
      docker exec web grep -m1 listen /etc/nginx/conf.d/default.conf    # listen 80;
      docker rm -f web
      docker run -d --name web -p 127.0.0.1:8000:80 nginx:1.27-alpine
      curl -sI localhost:8000 | head -1   # HTTP/1.1 200 OK
      ```
      Urutan `-p` adalah `HOST:CONTAINER`. Mapping `8000:8080` meneruskan trafik ke port 8080 **di dalam**
      container, padahal nginx listen di 80. Di lab (Docker Desktop, Windows), port host diterima lalu koneksinya
      diputus: `Recv failure: Connection was aborted`. Di Linux pesannya biasanya `Connection reset by peer` atau
      `Empty reply from server`. Gejala itu berbeda dari tidak publish sama sekali (`Could not connect to server`).
      Pemetaan port tidak bisa diubah pada container yang ada, jadi container harus dibuat ulang. `127.0.0.1` menjaga
      port tetap lokal. `--network host` di Docker Desktop tidak berperilaku seperti di Linux, dan menghilangkan isolasi.
    tests:
      pass:
        - |
          docker port web
          docker rm -f web
          docker run -d --name web -p 127.0.0.1:8000:80 nginx:1.27-alpine
          curl -I localhost:8000
        - |
          docker ps
          docker stop web
          docker run -d --name web2 -p 8000:80 nginx:1.27-alpine
      fail:
        - |
          docker rm -f web
          docker run -d --name web --network host nginx:1.27-alpine
        - |
          docker port web
          docker restart web
          curl localhost:8000
        - |
          docker rm -f web
          docker run -d --name web -p 8000:8080 nginx:1.27-alpine
    source: https://docs.docker.com/engine/network/port-publishing/

  - id: c03
    tier: C
    type: scenario
    objective: ephemeral
    prompt: |
      Container `proxy` (nginx:1.25-alpine) pernah dikonfigurasi seseorang lewat `docker exec` + `vi`. Sekarang harus
      di-upgrade ke `nginx:1.27-alpine` **tanpa kehilangan** konfigurasi itu, dan supaya kejadian ini tidak terulang.
      Tulis langkahnya (satu perintah per baris).
    checklist:
      - id: diff
        label: Melihat file apa saja yang diubah di container (docker diff)
        required: true
        patterns: ['docker\s+(container\s+)?diff\s+proxy']
      - id: cp-out
        label: Menyalin config yang diubah keluar (docker cp proxy:/etc/nginx/...)
        required: true
        patterns: ['docker\s+(container\s+)?cp\s+proxy:/etc/nginx\S*\s+\S+']
      - id: pull
        label: Pull image versi baru lebih dulu
        patterns: ['docker\s+(image\s+)?pull\s+nginx:1\.27']
      - id: run-mounted
        label: Menjalankan nginx:1.27 dengan config di-mount (-v ...:/etc/nginx/conf.d)
        required: true
        patterns: ['docker\s+(container\s+)?run\s+.*(-v|--volume|--mount)\s*[= ]?\S*/etc/nginx/conf\.d.*nginx:1\.27']
      - id: readonly
        label: Mount read-only (:ro)
        patterns: [':/etc/nginx/conf\.d:ro\b', 'readonly']
      - id: verify
        label: Verifikasi (nginx -t, curl, docker logs)
        patterns: ['nginx\s+-t', 'curl\s+', 'docker\s+(container\s+)?logs\s+']
      - id: cleanup
        label: Menghentikan/menghapus container lama
        patterns: ['docker\s+(container\s+)?(rm|stop)\s+(-f\s+)?proxy\b']
      - id: no-commit
        label: Tidak memakai docker commit sebagai solusi (image tidak reproducible)
        forbid: true
        patterns: ['docker\s+(container\s+)?commit\s+']
    pass_score: 0.6
    solution: |
      ```bash
      docker diff proxy | grep /etc/nginx           # C /etc/nginx/conf.d/default.conf
      docker cp proxy:/etc/nginx/conf.d ./nginx-conf
      docker pull nginx:1.27-alpine
      docker stop proxy && docker rename proxy proxy-old
      docker run -d --name proxy -p 80:80 -v "$PWD/nginx-conf:/etc/nginx/conf.d:ro" nginx:1.27-alpine
      docker exec proxy nginx -t && curl -sI localhost/health
      docker rm proxy-old                           # setelah yakin
      ```
      `docker diff` menunjukkan perubahan writable layer terhadap image. `C` = changed, `A` = added, `D` = deleted.
      Di lab, setelah `sed -i` di dalam container, `docker diff` menampilkan `C /etc/nginx/conf.d/default.conf`
      (beserta file runtime seperti `/run/nginx.pid`). Config disalin keluar dengan `docker cp`, lalu container
      baru memakai config itu lewat bind mount `:ro`. Di lab, `nginx -t` lolos dan `/health` → `ok`, sedangkan
      menulis ke file itu dari dalam container gagal (`Read-only file system`). Untuk jangka panjang, simpan config
      di git dan bake ke image (modul Dockerfile). `docker commit` membuat image yang tidak bisa direproduksi dari
      kode dan menyembunyikan perubahan manual.
    tests:
      pass:
        - |
          docker diff proxy
          docker cp proxy:/etc/nginx/conf.d ./nginx-conf
          docker pull nginx:1.27-alpine
          docker rm -f proxy
          docker run -d --name proxy -p 80:80 -v ./nginx-conf:/etc/nginx/conf.d:ro nginx:1.27-alpine
          docker exec proxy nginx -t
        - |
          docker diff proxy
          docker cp proxy:/etc/nginx/conf.d/default.conf ./default.conf
          docker stop proxy
          docker run -d --name proxy2 --mount type=bind,src=$PWD/conf,dst=/etc/nginx/conf.d,readonly nginx:1.27-alpine
          curl -I localhost
      fail:
        - |
          docker commit proxy myproxy:1.27
          docker rm -f proxy
          docker run -d --name proxy myproxy:1.27
        - |
          docker rm -f proxy
          docker run -d --name proxy nginx:1.27-alpine
        - |
          docker diff proxy
          docker exec proxy vi /etc/nginx/conf.d/default.conf
    source: https://docs.docker.com/reference/cli/docker/container/diff/

---

# Container & CLI Dasar

Container adalah cara paling umum mengemas dan menjalankan aplikasi di dunia DevOps. Satu image yang sama berjalan
identik di laptop, CI, dan production. Modul ini membangun pemahaman dasarnya: apa sebenarnya container itu,
bagaimana siklus hidupnya, dan perintah CLI yang dipakai setiap hari untuk menjalankan, mengamati, dan membuang
container, beserta jebakan yang paling sering memakan korban.

> [!NOTE]
> Semua output diuji di Docker Desktop (Windows) dengan Docker Engine & CLI 29.4.1. Di Linux, perilaku
> container sama. Perbedaannya ada di sisi host, mis. path untuk `--env-file`/`docker cp` dan pesan error curl. Modul
> Linux **Process & Signal** (PID 1, exit code) dan **Networking** (bind address, ufw) adalah prasyarat yang berguna.

## 1. Image, container, registry

```mermaid
flowchart LR
    R[(Registry<br/>Docker Hub, GHCR, ECR)] -- docker pull --> I[Image<br/>layer read-only]
    I -- docker run --> C1[Container web<br/>+ writable layer]
    I -- docker run --> C2[Container web2<br/>+ writable layer]
    C1 -- docker rm --> X[writable layer hilang]
```

| | Image | Container |
|---|---|---|
| Apa | Template read-only (filesystem + metadata) | Instance yang berjalan dari image |
| Analogi | Class / file ISO | Object / proses yang sedang jalan |
| Perintah | `pull`, `images`, `rmi`, `build` | `run`, `ps`, `stop`, `rm`, `exec`, `logs` |
| Ukuran (lab) | nginx:1.27-alpine: 52.7MB | writable layer: 81.9kB |

Container **bukan VM**. Ia adalah proses biasa di kernel host, diisolasi dengan namespaces (PID, network, mount)
dan dibatasi cgroups. Di lab, `docker run --rm alpine ps` hanya menampilkan satu proses (`1 root ps`), sedangkan
`docker top` dari host menampilkan PID host yang berbeda (2649).

## 2. Siklus hidup

```mermaid
stateDiagram-v2
    [*] --> Created: docker create / run
    Created --> Running: start
    Running --> Exited: PID 1 keluar / stop / kill
    Exited --> Running: start / restart policy
    Running --> Paused: pause
    Paused --> Running: unpause
    Exited --> [*]: rm
```

```bash
docker run -d --name web -p 8080:80 nginx:1.27-alpine
docker ps                       # yang berjalan
docker ps -a                    # semua, termasuk Exited
docker stop web                 # sinyal stop → tunggu → SIGKILL
docker start web
docker restart web
docker rm web                   # harus berhenti dulu (atau -f)
docker run --rm alpine:3.20 cat /etc/alpine-release    # sekali pakai
```

> [!TIP]
> Opsi Docker ditulis **sebelum** nama image. Apa pun setelah nama image adalah perintah dan argumen untuk
> container. `docker run myapp -e X=1` tidak memberi environment, karena `-e X=1` dikirim sebagai argumen ke
> aplikasimu.

## 3. Mengamati container

| Perintah | Untuk |
|---|---|
| `docker logs -f --tail 50 web` | stdout/stderr proses utama |
| `docker exec -it web sh` | Masuk ke container yang berjalan |
| `docker exec -u www-data app ...` | Sebagai user tertentu |
| `docker inspect web` | Semua konfigurasi & state (JSON) |
| `docker inspect -f '{{.State.ExitCode}}' web` | Satu field dengan template Go |
| `docker top web` / `docker stats --no-stream` | Proses / resource |
| `docker diff web` | File yang berubah dibanding image |
| `docker cp web:/path ./` | Salin file keluar/masuk |
| `docker port web` | Pemetaan port |

> [!WARNING]
> **Docker 29 menghapus `.NetworkSettings.IPAddress`.** Di lab, `docker inspect -f '{{.NetworkSettings.IPAddress}}'`
> gagal dengan `map has no entry for key "IPAddress"`. Field itu sudah lama deprecated, dan kini IP hanya ada per network:
> `{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}` (lab: `172.17.0.2`). Skrip monitoring lama yang memakai
> field ini akan rusak setelah upgrade.
> Sumber: [Docker Docs — Formatting](https://docs.docker.com/engine/cli/formatting/)

`docker logs` hanya melihat stdout/stderr. Image nginx resmi menautkan `access.log -> /dev/stdout` dan
`error.log -> /dev/stderr` (lab). Itulah konvensi container: log ke stdout, bukan ke file.

## 4. Port publishing

| Opsi | Hasil (lab) |
|---|---|
| `-p 8080:80` | `0.0.0.0:8080->80/tcp, [::]:8080->80/tcp` — semua interface |
| `-p 127.0.0.1:6379:6379` | Hanya localhost host |
| tanpa `-p` | `80/tcp` — tidak bisa diakses dari host (`Could not connect`) |
| `-p 8000:8080` (salah port container) | Port host terbuka, koneksi diputus (`Connection was aborted`) |
| port host sudah dipakai | `Bind for 0.0.0.0:8080 failed: port is already allocated`, container tertinggal `Created` |

`EXPOSE` di Dockerfile **tidak** membuka apa pun ke host. Ia hanya dokumentasi port yang dipakai aplikasi.

## 5. Environment

```bash
docker run -d -e APP_ENV=production myapp
docker run -d --env-file .env myapp
docker inspect -f '{{range .Config.Env}}{{println .}}{{end}}' myapp
```

Catatan dari lab:
- `--env-file` tidak membuang tanda kutip: `QUOTED="with quotes"` → nilainya `"with quotes"`.
- Nilai `-e` terlihat jelas di `docker inspect` (`REDIS_PASSWORD=S3cret`).
- Environment tidak bisa diubah setelah container dibuat, karena `docker update` tidak punya opsi env. Untuk
  mengubahnya, container harus dibuat ulang.

## 6. Restart policy

| Policy | Crash (exit ≠ 0) | Exit 0 | Setelah `docker stop` | Saat daemon/host start ulang |
|---|---|---|---|---|
| `no` (default) | — | — | — | — |
| `on-failure[:N]` | restart (maks N) | tidak | tetap berhenti | restart bila sebelumnya berjalan |
| `always` | restart | restart | tetap berhenti | **restart**, walau sebelumnya di-stop |
| `unless-stopped` | restart | restart | tetap berhenti | restart, kecuali sebelumnya di-stop |

Hasil lab:
- `on-failure:3` + exit 1 → 3 restart (4 kali jalan).
- `on-failure` + exit 0 → 0 restart.
- `always` + exit 0 → 4 restart dalam 6 detik.

`docker update --restart unless-stopped web` mengubah policy container yang sudah ada.

> [!INSIGHT]
> Restart policy menyembunyikan crash. Container `Restarting (1)` dengan `RestartCount` 27 tampak "hidup" di
> dashboard sederhana, padahal tidak pernah melayani satu request pun. Di lab, Docker me-restart container `always`
> empat kali dalam enam detik. Monitor `RestartCount` dan state `restarting`, jangan hanya cek apakah container ada.
> Sumber: [Docker Docs — Start containers automatically](https://docs.docker.com/engine/containers/start-containers-automatically/)

## 7. Writable layer: perubahan yang hilang

Setiap container punya writable layer di atas image. Perubahan lewat `docker exec` hanya hidup selama container itu
ada. Di lab, `new.html` yang dibuat lewat exec → 200. Setelah `rm` dan `run` ulang → 404.

```bash
docker diff web       # C /etc/nginx/conf.d/default.conf, A /run/nginx.pid, ...
```

| Yang ingin bertahan | Tempatnya |
|---|---|
| Kode & config aplikasi | Image (Dockerfile) — modul berikutnya |
| Config per lingkungan | Environment / file yang di-mount `:ro` |
| Data (DB, upload) | Volume — modul Storage |
| Log | stdout/stderr → log driver / agregator |

## 8. Ringkasan kebiasaan aman

| Kebiasaan | Kenapa |
|---|---|
| Pin tag versi (`nginx:1.27-alpine`), bukan `latest` | Reproducible, tidak berubah diam-diam |
| `--rm` untuk perintah sekali pakai | Tidak menumpuk container Exited |
| `-p 127.0.0.1:...` untuk service lokal/internal | `-p` biasa membuka ke semua interface |
| `docker logs` dulu saat container bermasalah | Jawabannya hampir selalu di sana |
| Jangan konfigurasi lewat `docker exec` | Hilang saat container dibuat ulang |
| `docker exec -u <user aplikasi>` | File milik root merusak aplikasi |
| Secret bukan di `-e` (terlihat di inspect) | Modul Keamanan |
| Hapus container lab dengan nama/label spesifik, bukan `prune` global | Prune ikut menghapus container orang lain |
