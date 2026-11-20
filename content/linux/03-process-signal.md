---
format: 1
id: linux-process-signal
course:
  slug: linux
  title: Linux
  description: Fondasi Linux untuk DevOps/DevSecOps — dari filesystem sampai hardening.
title: Process & Signal
summary: Melihat, mengendalikan, dan menghentikan proses dengan benar — sinyal, job control, zombie, prioritas, PID 1 di container, dan exit code 137.
order: 3
version: 1
status: verified
updated: '2026-11-20'
tested_on:
  - Ubuntu 24.04.5 LTS — procps-ng 4.0.4, psmisc 23.7, util-linux 2.39.3, bash 5.2.21, dash 0.5.12, lsof 4.95.0
  - Docker Engine 29.4.1 (Docker Desktop) — docker stop, --init, batas memori/OOM
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: proc-model
    text: Memahami PID, PPID, pohon proses, dan PID 1
  - id: inspect
    text: Melihat proses dengan `ps`, `pgrep`, `pstree`, dan `/proc/PID`
  - id: states
    text: Membaca status proses (R, S, D, Z, T) dan load average
  - id: signals
    text: Mengirim sinyal yang tepat dengan `kill`, `pkill`, dan process group
  - id: exit-codes
    text: Membaca exit code, termasuk 128+N, 124, dan 137
  - id: jobs
    text: Mengendalikan job shell (`&`, Ctrl+Z, `bg`, `fg`, `nohup`)
  - id: priority
    text: Mengatur prioritas CPU dengan `nice` dan `renice`
  - id: zombie
    text: Memahami zombie & orphan dan cara membersihkannya
  - id: container
    text: Menangani sinyal dan PID 1 di container (exec form, `exec`, `--init`)
  - id: security
    text: Menyadari kebocoran secret lewat argumen dan environment proses
  - id: troubleshoot
    text: Mendiagnosis proses yang menumpuk, berhenti lambat, atau dibunuh OOM killer
sources:
  - title: ps(1) — procps-ng
    url: https://man7.org/linux/man-pages/man1/ps.1.html
  - title: pgrep(1) / pkill(1)
    url: https://man7.org/linux/man-pages/man1/pgrep.1.html
  - title: kill(1)
    url: https://man7.org/linux/man-pages/man1/kill.1.html
  - title: signal(7) — daftar sinyal dan aksi default
    url: https://man7.org/linux/man-pages/man7/signal.7.html
  - title: proc(5) — /proc/PID, hidepid
    url: https://man7.org/linux/man-pages/man5/proc.5.html
  - title: pid_namespaces(7) — PID 1 dan sinyal
    url: https://man7.org/linux/man-pages/man7/pid_namespaces.7.html
  - title: wait(2) — zombie
    url: https://man7.org/linux/man-pages/man2/wait.2.html
  - title: nice(1), renice(1), setpriority(2)
    url: https://man7.org/linux/man-pages/man2/setpriority.2.html
  - title: GNU Coreutils — timeout, nohup
    url: https://www.gnu.org/software/coreutils/manual/html_node/timeout-invocation.html
  - title: GNU Bash Manual — Job Control & Exit Status
    url: https://www.gnu.org/software/bash/manual/html_node/Job-Control-Basics.html
  - title: flock(1)
    url: https://man7.org/linux/man-pages/man1/flock.1.html
  - title: Dockerfile reference — ENTRYPOINT shell vs exec form
    url: https://docs.docker.com/reference/dockerfile/#entrypoint
  - title: docker container stop
    url: https://docs.docker.com/reference/cli/docker/container/stop/
  - title: docker run — --init dan capabilities
    url: https://docs.docker.com/engine/containers/run/
  - title: Laravel 13 — Queues (sinyal, --memory, --max-jobs)
    url: https://laravel.com/docs/13.x/queues
  - title: Laravel 13 — Task Scheduling (withoutOverlapping)
    url: https://laravel.com/docs/13.x/scheduling
  - title: Brendan Gregg — Linux Load Averages, Solving the Mystery
    url: https://www.brendangregg.com/blog/2017-08-08/linux-load-averages.html
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: proc-model
    prompt: Di sebuah host Linux, proses init (mis. systemd) selalu punya PID berapa?
    answers: ['1', 'pid 1']
    tests:
      fail: ['0', '2', '100']
    explanation: 'PID 1 adalah proses pertama di user space, induk akhir semua proses, dan yang "mengadopsi" proses yatim. Di container, PID 1 adalah perintah utama container itu.'
    source: https://man7.org/linux/man-pages/man7/pid_namespaces.7.html

  - id: a02
    tier: A
    type: command
    objective: inspect
    prompt: Tampilkan **semua** proses dengan format BSD (kolom USER, %CPU, %MEM, RSS, STAT, COMMAND).
    answers: ['ps aux']
    patterns: ['^ps (aux|axu|uax|xau)$']
    tests:
      pass: ['ps axu']
      fail: ['ps', 'ps -a', 'ps -u', 'top']
    explanation: '`a` = semua user, `x` = termasuk proses tanpa terminal (daemon), `u` = format berorientasi user. `ps` saja hanya menampilkan proses di terminal kamu.'
    source: https://man7.org/linux/man-pages/man1/ps.1.html

  - id: a03
    tier: A
    type: command
    objective: inspect
    prompt: Tampilkan semua proses dengan format standar UNIX lengkap, yang menyertakan kolom **PPID**.
    answers: ['ps -ef']
    flags_any_order: true
    patterns: ['^ps (-ef|-fe|-e -f|-f -e)$']
    tests:
      pass: ['ps -fe', 'ps -e -f']
      fail: ['ps aux', 'ps -e', 'ps -f']
    explanation: '`-e` = semua proses, `-f` = full format (UID, PID, PPID, STIME, CMD). `ps aux` tidak menampilkan PPID.'
    source: https://man7.org/linux/man-pages/man1/ps.1.html

  - id: a04
    tier: A
    type: command
    objective: inspect
    prompt: Tampilkan PID beserta **command line lengkap** semua proses bernama `nginx`, tanpa ikut menampilkan proses pencarinya sendiri.
    answers: ['pgrep -a nginx']
    patterns: ['^pgrep (-a|--list-full) nginx$']
    tests:
      pass: ['pgrep --list-full nginx']
      fail: ['ps aux | grep nginx', 'pgrep nginx', 'pgrep -l nginx', 'pidof nginx']
    explanation: '`ps aux | grep x` selalu ikut menampilkan baris `grep x` itu sendiri (terbukti di lab). `pgrep` tidak pernah mencocokkan dirinya. `-l` hanya menampilkan nama, `-a` command line lengkap.'
    source: https://man7.org/linux/man-pages/man1/pgrep.1.html

  - id: a05
    tier: A
    type: command
    objective: signals
    prompt: Minta proses PID `4321` berhenti dengan **sopan** — sinyal yang bisa ditangkap supaya aplikasi sempat cleanup.
    answers: ['kill 4321']
    patterns: ['^kill (-TERM |-15 |-SIGTERM |-s TERM |-s SIGTERM |-s 15 )?4321$']
    tests:
      pass: ['kill -TERM 4321', 'kill -15 4321', 'kill -s TERM 4321']
      fail: ['kill -9 4321', 'kill -KILL 4321', 'kill -HUP 4321']
    explanation: 'Tanpa opsi, `kill` mengirim SIGTERM (15). Aplikasi yang baik menangkap SIGTERM untuk menyelesaikan request/job dan menutup koneksi.'
    source: https://man7.org/linux/man-pages/man1/kill.1.html

  - id: a06
    tier: A
    type: command
    objective: signals
    prompt: PID `4321` mengabaikan SIGTERM. Paksa hentikan dengan sinyal yang **tidak bisa** ditangkap atau diabaikan.
    answers: ['kill -9 4321']
    patterns: ['^kill (-9|-KILL|-SIGKILL|-s KILL|-s SIGKILL|-s 9) 4321$']
    tests:
      pass: ['kill -KILL 4321', 'kill -s KILL 4321']
      fail: ['kill 4321', 'kill -15 4321', 'kill -STOP 4321']
    explanation: 'SIGKILL (dan SIGSTOP) tidak bisa di-handle. Di lab, skrip dengan `trap ... TERM` tetap hidup setelah `kill`, lalu mati oleh `kill -9` dengan exit 137. Konsekuensinya: tidak ada cleanup, file sementara dan lock bisa tertinggal.'
    source: https://man7.org/linux/man-pages/man7/signal.7.html

  - id: a07
    tier: A
    type: text
    objective: signals
    prompt: Sinyal apa yang dikirim `kill PID` bila tidak diberi opsi sinyal? (nama atau nomor)
    answers: ['SIGTERM', 'TERM', '15']
    patterns: ['^(sig)?term( ?\(?15\)?)?$']
    tests:
      pass: ['sigterm (15)', 'term']
      fail: ['SIGKILL', '9', 'SIGHUP', 'SIGINT']
    explanation: 'Nomor sinyal di Linux x86: HUP=1, INT=2, QUIT=3, KILL=9, USR1=10, TERM=15, CONT=18, STOP=19, TSTP=20 (`kill -l`). Nomor bisa berbeda di arsitektur lain, jadi lebih aman memakai nama.'
    source: https://man7.org/linux/man-pages/man7/signal.7.html

  - id: a08
    tier: A
    type: command
    objective: jobs
    prompt: Jalankan `./backup.sh` di **background**, supaya prompt shell langsung kembali.
    answers: ['./backup.sh &']
    patterns: ['^(\./backup\.sh|bash backup\.sh|sh backup\.sh|bash \./backup\.sh) ?&$']
    tests:
      pass: ['./backup.sh&', 'bash backup.sh &']
      fail: ['./backup.sh', 'bg ./backup.sh', 'nohup ./backup.sh']
    explanation: '`&` di akhir menjalankan perintah sebagai background job. PID-nya tersimpan di `$!`. Job ini tetap anak dari shell, jadi bisa ikut mati saat kamu logout (lihat `nohup`).'
    source: https://www.gnu.org/software/bash/manual/html_node/Job-Control-Basics.html

  - id: a09
    tier: A
    type: command
    objective: jobs
    prompt: Kamu menekan **Ctrl+Z** pada job yang sedang berjalan (statusnya Stopped). Lanjutkan job itu di **background**.
    answers: ['bg']
    patterns: ['^bg( %1| %\+| %%)?$']
    tests:
      pass: ['bg %1', 'bg %%']
      fail: ['fg', 'jobs', 'kill -CONT']
    explanation: 'Ctrl+Z mengirim SIGTSTP (job berhenti, status `T`). `bg` melanjutkannya di background, `fg` membawanya kembali ke foreground.'
    source: https://www.gnu.org/software/bash/manual/html_node/Job-Control-Builtins.html

  - id: a10
    tier: A
    type: command
    objective: jobs
    prompt: Tampilkan daftar job di shell saat ini **beserta PID**-nya.
    answers: ['jobs -l']
    patterns: ['^jobs -l$']
    tests:
      fail: ['jobs', 'ps', 'bg']
    explanation: 'Di lab: `[1]-   375 Running   sleep 50 &`. `jobs` tanpa `-l` tidak menampilkan PID. Job direferensikan dengan `%1`, `%2`, dst.'
    source: https://www.gnu.org/software/bash/manual/html_node/Job-Control-Builtins.html

  - id: a11
    tier: A
    type: text
    objective: states
    prompt: Di kolom `STAT` milik `ps`, huruf apa yang menandai proses **zombie**?
    answers: ['Z']
    tests:
      fail: ['D', 'T', 'S', 'X']
    explanation: '`R` running, `S` sleeping (bisa diinterupsi), `D` uninterruptible sleep (biasanya I/O), `T` stopped, `Z` zombie (`<defunct>`). Huruf tambahan: `s` session leader, `N` nice positif, `<` prioritas tinggi, `+` foreground.'
    source: https://man7.org/linux/man-pages/man1/ps.1.html

  - id: a12
    tier: A
    type: command
    objective: priority
    prompt: Jalankan `./report.sh` dengan prioritas CPU **lebih rendah**, yaitu nilai nice 10.
    answers: ['nice -n 10 ./report.sh']
    patterns: ['^nice (-n ?10|-10|--adjustment=10) \./report\.sh$']
    tests:
      pass: ['nice -10 ./report.sh', 'nice --adjustment=10 ./report.sh']
      fail: ['nice -n -10 ./report.sh', 'renice 10 ./report.sh', './report.sh &']
    explanation: 'Nice berkisar -20 (paling diutamakan) sampai 19 (paling mengalah). Angka lebih besar = lebih "baik hati" ke proses lain. Hati-hati: `nice -10` berarti +10, bukan -10.'
    source: https://man7.org/linux/man-pages/man1/nice.1.html

  - id: a13
    tier: A
    type: command
    objective: exit-codes
    prompt: Tampilkan exit code dari perintah yang **terakhir** dijalankan.
    answers: ['echo $?']
    patterns: ['^echo \$\?$']
    tests:
      pass: ['echo "$?"']
      fail: ['echo $!', 'echo $$', 'echo $0']
    explanation: '`$?` = exit code terakhir (0 = sukses). `$!` = PID background job terakhir, `$$` = PID shell, `$0` = nama skrip/shell.'
    source: https://www.gnu.org/software/bash/manual/html_node/Special-Parameters.html

  - id: a14
    tier: A
    type: command
    objective: inspect
    prompt: Tampilkan proses PID `4321` **saja** dalam full format (UID, PID, PPID, STIME, CMD) dengan `ps`.
    answers: ['ps -fp 4321']
    patterns: ['^ps (-fp ?4321|-f -p ?4321|-p ?4321 -f|-f --pid[= ]4321|--pid[= ]4321 -f)$']
    tests:
      pass: ['ps -f -p 4321', 'ps -p 4321 -f']
      fail: ['ps -ef', 'ps 4321 -f', 'ps -p 4321']
    explanation: '`-p` memilih PID (bisa daftar: `-p 1,4321`). Kombinasi berguna lainnya: `ps -o pid,ppid,etime,cmd -p 4321` — `etime` = sudah berapa lama proses berjalan.'
    source: https://man7.org/linux/man-pages/man1/ps.1.html

  - id: a15
    tier: A
    type: command
    objective: signals
    prompt: Cek apakah PID `4321` masih ada **tanpa mengirim sinyal apa pun** (hasilnya dilihat dari exit code).
    answers: ['kill -0 4321']
    patterns: ['^kill (-0|-s 0) 4321$', '^ps -p ?4321$']
    tests:
      pass: ['ps -p 4321']
      fail: ['kill 4321', 'kill -9 4321', 'kill -STOP 4321']
    explanation: 'Sinyal 0 hanya mengecek keberadaan dan izin. Di lab: exit 0 selama proses hidup, lalu `kill: (265) - No such process` dengan exit 1. Pola umum di skrip: `while kill -0 "$pid" 2>/dev/null; do sleep 1; done`.'
    source: https://man7.org/linux/man-pages/man2/kill.2.html

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: exit-codes
    prompt: Sebuah container/proses keluar dengan exit code **137**. Sinyal apa yang membunuhnya?
    answers: ['SIGKILL', 'KILL', '9']
    patterns: ['^(sig)?kill( ?\(?9\)?)?$']
    tests:
      pass: ['sigkill (9)', 'kill']
      fail: ['SIGTERM', '15', 'SIGSEGV', 'OOM']
    explanation: 'Proses yang mati karena sinyal N dilaporkan shell/Docker sebagai 128+N: 137 = 128+9 (SIGKILL), 143 = 128+15 (SIGTERM). Di lab: `kill` → 143, `kill -9` → 137, `timeout` → 124. Penyebab 137 yang paling sering: OOM killer, atau `docker stop` yang habis masa tunggunya.'
    source: https://www.gnu.org/software/bash/manual/html_node/Exit-Status.html

  - id: b02
    tier: B
    type: text
    objective: container
    prompt: Secara default, `docker stop` menunggu berapa **detik** setelah SIGTERM sebelum mengirim SIGKILL?
    answers: ['10', '10 detik', '10s']
    patterns: ['^10 ?(detik|s|sec|seconds?)?$']
    tests:
      fail: ['30', '5', '0', '60']
    explanation: 'Default 10 detik, bisa diubah dengan `docker stop -t`, `--stop-timeout`, atau `stop_grace_period` di Compose. Di lab `docker stop -t 10` tepat 10,3 detik. Catatan: container di Docker Desktop lab kami ternyata mendapat `StopTimeout=1` — cek nilainya dengan `docker inspect -f ''{{.Config.StopTimeout}}''`. Kubernetes memakai `terminationGracePeriodSeconds` (default 30).'
    source: https://docs.docker.com/reference/cli/docker/container/stop/

  - id: b03
    tier: B
    type: command
    objective: inspect
    prompt: |
      Dengan `ps`, tampilkan kolom **PID, PPID, USER, %MEM, dan command** untuk semua proses,
      diurutkan dari pemakaian memori **terbesar**.
    answers: ['ps -eo pid,ppid,user,%mem,cmd --sort=-%mem']
    patterns:
      - '^ps (-eo|-e -o|-Ao|-A -o|ax -o|-axo) (?=(?:\S*,)?pid(?:,|\s))(?=\S*ppid)(?=\S*user)(?=\S*(%mem|pmem))(?=\S*(cmd|args|comm|command))\S+ --sort[= ]-(%mem|rss|pmem)$'
      - '^ps (-e|-A|ax) --sort[= ]-(%mem|rss|pmem) -o (?=(?:\S*,)?pid(?:,|$))(?=\S*ppid)(?=\S*user)(?=\S*(%mem|pmem))(?=\S*(cmd|args|comm|command))\S+$'
    tests:
      pass:
        - 'ps -e -o pid,ppid,user,%mem,args --sort -rss'
        - 'ps ax --sort=-%mem -o pid,ppid,user,%mem,comm'
        - 'ps -eo user,pid,ppid,%mem,cmd --sort=-%mem'
      fail:
        - 'ps aux --sort=-%mem'
        - 'ps -eo pid,user,%mem,cmd --sort=%mem'
        - 'ps -eo pid,ppid,user,%mem,cmd --sort=-%cpu'
    explanation: '`-o` memilih kolom, `--sort=-kolom` mengurutkan menurun. Ketiga varian di atas diuji di lab. Tambahkan `| head` untuk 10 teratas.'
    source: https://man7.org/linux/man-pages/man1/ps.1.html

  - id: b04
    tier: B
    type: command
    objective: signals
    prompt: Kirim **SIGHUP** ke semua proses bernama `nginx` dalam satu perintah, tanpa mencari PID secara manual.
    answers: ['pkill -HUP nginx']
    patterns: ['^(pkill (-HUP|-SIGHUP|-1|--signal[= ](HUP|SIGHUP|1)) nginx|killall (-HUP|-SIGHUP|-1|-s HUP|-s SIGHUP) nginx)$']
    tests:
      pass: ['pkill --signal HUP nginx', 'killall -HUP nginx']
      fail: ['pkill nginx', 'kill -HUP nginx', 'pkill -9 nginx']
    explanation: 'Banyak daemon menafsirkan SIGHUP sebagai "baca ulang konfigurasi". Di production lebih aman `nginx -s reload` atau `systemctl reload nginx`, yang memvalidasi lebih dulu. `kill` hanya menerima PID, bukan nama. Hati-hati: opsi `-s` di `pkill` berarti *session*, bukan sinyal.'
    source: https://man7.org/linux/man-pages/man1/pgrep.1.html

  - id: b05
    tier: B
    type: command
    objective: signals
    prompt: Kirim SIGTERM ke **seluruh process group** dengan PGID `2500` (parent dan semua anaknya) memakai `kill`.
    answers: ['kill -TERM -- -2500']
    patterns:
      - '^kill (-TERM |-15 |-SIGTERM |-s TERM )?-- -2500$'
      - '^kill (-TERM|-15|-SIGTERM|-s TERM) -2500$'
    tests:
      pass: ['kill -- -2500', 'kill -15 -2500', 'kill -TERM -2500']
      fail: ['kill 2500', 'kill -2500', 'kill -9 2500', 'pkill 2500']
    explanation: 'PID negatif = process group. Di lab, `kill -15 -PGID` menghentikan parent beserta semua `sleep` anaknya. `kill -PGID` tanpa sinyal **ditolak** (`invalid signal specification`), karena `-2500` dibaca sebagai nomor sinyal. Pakai `--` atau sebutkan sinyalnya. PGID terlihat di `ps -o pid,pgid,cmd`.'
    source: https://man7.org/linux/man-pages/man2/kill.2.html

  - id: b06
    tier: B
    type: command
    objective: exit-codes
    prompt: Jalankan `curl -s https://api.example.com/health` dengan batas waktu **5 detik** memakai perintah `timeout`.
    answers: ['timeout 5 curl -s https://api.example.com/health']
    patterns: ['^timeout ((-s|--signal|-k|--kill-after) ?\S+ |--(signal|kill-after)=\S+ )*5s? curl -s https://api\.example\.com/health$']
    tests:
      pass: ['timeout 5s curl -s https://api.example.com/health', 'timeout -k 2 5 curl -s https://api.example.com/health']
      fail: ['curl -s https://api.example.com/health &', 'timeout curl -s https://api.example.com/health', 'sleep 5; curl -s https://api.example.com/health']
    explanation: '`timeout` mengirim SIGTERM saat waktu habis dan keluar dengan **124** (diuji di lab). `-k 2` menambahkan SIGKILL bila 2 detik kemudian proses masih hidup. Berguna di healthcheck, cron, dan CI supaya job macet tidak menggantung selamanya.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/timeout-invocation.html

  - id: b07
    tier: B
    type: command
    objective: priority
    prompt: Proses PID `4321` sudah berjalan dan terlalu rakus CPU. Ubah nilai nice-nya menjadi **15**.
    answers: ['renice -n 15 -p 4321']
    patterns: ['^renice (-n )?15 (-p )?4321$']
    tests:
      pass: ['renice 15 -p 4321', 'renice -n 15 4321']
      fail: ['nice -n 15 4321', 'renice -n -15 -p 4321', 'kill -STOP 4321']
    explanation: '`nice` untuk proses baru, `renice` untuk yang sudah berjalan. User biasa hanya bisa **menaikkan** nice. Di lab, mengembalikannya ke 0 ditolak dengan `Permission denied`. Di container Docker, bahkan root tidak bisa menurunkannya, karena `CAP_SYS_NICE` tidak diberikan secara default.'
    source: https://man7.org/linux/man-pages/man1/renice.1.html

  - id: b08
    tier: B
    type: command
    objective: security
    prompt: Tampilkan environment variable milik proses PID `4321` **satu per baris**, dibaca dari `/proc`.
    answers: ['tr ''\0'' ''\n'' < /proc/4321/environ']
    patterns:
      - '^tr \\0{1,3} \\n </proc/4321/environ$'
      - '^cat /proc/4321/environ ?\| ?tr \\0{1,3} \\n$'
      - '^xargs -0 (-n ?1|-L ?1) -a /proc/4321/environ$'
    tests:
      pass: ['tr "\0" "\n" < /proc/4321/environ', 'cat /proc/4321/environ | tr ''\000'' ''\n''']
      fail: ['cat /proc/4321/environ', 'env', 'printenv']
    explanation: 'Entri di `environ` dipisah byte NUL, jadi perlu `tr`. File ini ber-mode `0400` (di lab: `-r--------`), hanya terbaca pemilik proses atau root. Environment yang dibaca adalah yang ada **saat proses dimulai**.'
    source: https://man7.org/linux/man-pages/man5/proc.5.html

  - id: b09
    tier: B
    type: text
    objective: zombie
    prompt: Ada proses zombie (`<defunct>`) PID 777 dengan parent PID 700. Kamu menjalankan `kill -9 777`. Apakah zombie itu hilang? (ya/tidak)
    answers: ['tidak', 'no', 'tidak hilang']
    tests:
      fail: ['ya', 'yes', 'hilang']
    explanation: 'Zombie sudah mati — yang tersisa hanya entri di tabel proses berisi exit status, menunggu parent memanggil `wait()`. Di lab, `kill -9` tidak berpengaruh. Zombie baru hilang setelah parent-nya keluar, karena zombie lalu diadopsi PID 1 yang memanggil `wait()`. Solusinya: perbaiki atau restart **parent**.'
    source: https://man7.org/linux/man-pages/man2/wait.2.html

  - id: b10
    tier: B
    type: command
    objective: proc-model
    prompt: Tampilkan **pohon proses** lengkap beserta PID dengan `pstree`.
    answers: ['pstree -p']
    patterns: ['^pstree (-p|-pa|-ap|-p -a|-a -p|--show-pids)$']
    tests:
      pass: ['pstree -pa', 'pstree --show-pids']
      fail: ['pstree', 'ps aux', 'top']
    explanation: '`-p` menampilkan PID, `-a` argumen. Tanpa `pstree` (paket psmisc), pakai `ps -ef --forest` atau `ps axjf`.'
    source: https://man7.org/linux/man-pages/man1/pstree.1.html

  - id: b11
    tier: B
    type: command
    objective: inspect
    prompt: Deploy gagal karena `address already in use`. Dengan `lsof`, cari proses yang memakai port **8080**.
    answers: ['lsof -i :8080']
    patterns:
      - '^lsof (-nP |-n -P |-Pn )?-i ?(TCP|tcp)?:8080( -sTCP:LISTEN)?$'
      - '^lsof -i ?(TCP|tcp)?:8080 (-nP|-n -P|-Pn)$'
      - '^fuser (-v )?8080/tcp$'
    tests:
      pass: ['lsof -nP -iTCP:8080 -sTCP:LISTEN', 'fuser -v 8080/tcp']
      fail: ['lsof 8080', 'lsof -p 8080', 'kill 8080']
    explanation: 'Di lab: `nc 265 root 3u IPv4 ... TCP *:8080 (LISTEN)`. `-nP` mencegah lookup DNS/nama port (lebih cepat). `lsof -p` memilih PID, bukan port. Alternatif dari modul jaringan: `ss -ltnp`.'
    source: https://github.com/lsof-org/lsof

  - id: b12
    tier: B
    type: command
    objective: signals
    prompt: |
      Worker berjalan sebagai `php artisan queue:work`. Kirim SIGTERM ke semua proses yang **command line**-nya
      mengandung `queue:work` (nama prosesnya sendiri `php`).
    answers: ['pkill -f queue:work']
    patterns: ['^pkill ((-TERM|-15|-SIGTERM|--signal[= ](TERM|SIGTERM|15)) )?(-f|--full)( (-TERM|-15|-SIGTERM))? queue:work$']
    tests:
      pass: ['pkill -TERM -f queue:work', 'pkill --full queue:work']
      fail: ['pkill queue:work', 'kill queue:work', 'pkill -9 -f queue:work']
    explanation: 'Tanpa `-f`, pgrep/pkill hanya mencocokkan **nama** proses (`php`, maksimal 15 karakter; di lab nama lebih panjang memicu peringatan dan zero match). Bahaya `-f`: pola juga cocok dengan shell yang menjalankannya. Di lab, `bash -c ''pkill -f "sleep 300"; echo survived''` ikut membunuh `bash`-nya sendiri, sehingga `survived` tidak pernah tercetak.'
    source: https://man7.org/linux/man-pages/man1/pgrep.1.html

  - id: b13
    tier: B
    type: command
    objective: jobs
    prompt: Jalankan `./migrate.sh` di background agar **tetap berjalan setelah kamu logout** dari SSH, dengan stdout dan stderr ke `migrate.log`. Pakai `nohup`.
    answers: ['nohup ./migrate.sh > migrate.log 2>&1 &']
    patterns:
      - '^nohup \./migrate\.sh >>?migrate\.log 2>&1 ?&$'
      - '^nohup \./migrate\.sh &>>? ?migrate\.log ?&$'
    tests:
      pass: ['nohup ./migrate.sh >migrate.log 2>&1 &', 'nohup ./migrate.sh &> migrate.log &']
      fail: ['./migrate.sh &', 'nohup ./migrate.sh', './migrate.sh > migrate.log 2>&1 &', 'nohup ./migrate.sh 2>&1 > migrate.log &']
    explanation: '`nohup` membuat proses mengabaikan SIGHUP yang dikirim saat terminal ditutup. Urutan redirect penting: `> file 2>&1` (stderr ikut ke file), bukan `2>&1 > file`. Untuk pekerjaan panjang yang interaktif, `tmux`/`screen` lebih nyaman. Untuk service permanen, pakai unit systemd.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/nohup-invocation.html

  - id: b14
    tier: B
    type: text
    objective: states
    prompt: |
      Server 4 core, load average 1 menit = 8.0, tapi `top` menunjukkan CPU idle 90% dan banyak proses berstatus `D`.
      Bottleneck yang paling mungkin: CPU atau I/O (disk/NFS)?
    answers: ['I/O', 'io', 'disk']
    patterns: ['^(i/?o|disk|storage|nfs|(disk )?i/?o( disk)?|i/?o \(disk(/nfs)?\))$']
    tests:
      pass: ['i/o (disk)', 'disk i/o']
      fail: ['cpu', 'memori', 'ram', 'network']
    explanation: 'Load average Linux menghitung proses runnable (`R`) **dan** uninterruptible (`D`, biasanya menunggu disk/NFS). Load tinggi dengan CPU idle mengarah ke I/O. Cek `vmstat 1` (kolom `wa`, `b`) dan `iostat -x`.'
    source: https://www.brendangregg.com/blog/2017-08-08/linux-load-averages.html

  - id: b15
    tier: B
    type: text
    objective: container
    prompt: '`docker run ... sleep 300` selalu berhenti lewat SIGKILL (exit 137), karena PID 1 tanpa handler mengabaikan SIGTERM. Opsi `docker run` apa yang memasang init kecil sebagai PID 1 untuk meneruskan sinyal dan me-reap zombie?'
    answers: ['--init']
    patterns: ['^(docker run )?--init$']
    tests:
      fail: ['--rm', '-d', '--privileged', '--restart', '--pid=host']
    explanation: 'Kernel tidak menerapkan aksi default sinyal ke PID 1 di namespace-nya. Di lab: `sleep 300` → exit **137** (dibunuh setelah timeout), dengan `--init` → exit **143** dalam 0,5 detik. Proses dengan handler sendiri (seperti `queue:work`) tidak butuh `--init`, asal ia benar-benar PID 1.'
    source: https://man7.org/linux/man-pages/man7/pid_namespaces.7.html

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: troubleshoot
    prompt: |
      Server terasa lambat. Job laporan dijalankan cron setiap menit, tapi sekarang satu kali jalannya butuh
      lebih dari satu menit, sehingga prosesnya menumpuk. Tulis perintah (satu per baris) untuk memeriksa
      proses yang menumpuk, menghentikannya dengan **sopan**, dan mencegah tumpang tindih — tanpa mengganggu
      PHP-FPM yang melayani web.
    context: |
      $ crontab -l -u app
      * * * * * cd /srv/app && php artisan report:generate >> storage/logs/report.log 2>&1
      $ uptime
       10:42:01 up 12 days,  3:10,  1 user,  load average: 23.41, 19.87, 12.02
      $ pgrep -c -f report:generate
      58
    checklist:
      - id: inspect
        label: Memeriksa proses yang menumpuk (pgrep -af, ps ... etime, pstree)
        patterns: ['pgrep\s+(-\S+\s+)*-\S*[af]\S*\s+.*report', 'ps\s+.*\|\s*grep\s+.*report', 'ps\s+.*(etime|lstart)', 'pstree']
      - id: stop
        label: Menghentikan hanya job laporan dengan SIGTERM (pkill -f report:generate)
        required: true
        patterns:
          - 'pkill\s+((-term|-15|-sigterm)\s+)?(-f|--full)\s+((-term|-15)\s+)?\S*report:generate'
          - 'kill\s+((-term|-15)\s+)?\$\(pgrep\s+-f\s+\S*report:generate\)'
      - id: prevent
        label: Mencegah tumpang tindih (flock -n di crontab, atau withoutOverlapping)
        required: true
        patterns: ['flock\s+(-\S*n\S*|--nonblock|-w\s*\d+|--timeout)', 'withoutoverlapping']
      - id: root-cause
        label: Mencari kenapa job jadi lambat (log job, strace, /proc/PID)
        patterns: ['tail\s+.*report\.log', 'strace\s+-p', '/proc/\d+/(fd|stack|status|wchan)']
      - id: no-kill-php
        label: Tidak membunuh semua proses php (PHP-FPM ikut mati)
        forbid: true
        patterns: ['pkill\s+(-\S+\s+)*php\s*$', 'killall\s+(-\S+\s+)*php', 'pkill\s+(-\S+\s+)*-f\s+php\s*$', 'kill\s+(-\S+\s+)?-1\s*$']
    solution: |
      ```bash
      pgrep -af report:generate                       # juga menghitung "sh -c" dari cron
      ps -o pid,etime,cmd -p "$(pgrep -d, -f report:generate)"
      pkill -TERM -f report:generate                  # sopan; cek lagi, -KILL hanya bila perlu
      tail -n 50 /srv/app/storage/logs/report.log     # kenapa tiba-tiba lambat?
      ```
      ```text
      * * * * * cd /srv/app && flock -n /tmp/report.lock php artisan report:generate >> storage/logs/report.log 2>&1
      ```
      `flock -n` langsung keluar (exit 1, diuji di lab) bila lock masih dipegang run sebelumnya, jadi paling
      banyak satu instance yang berjalan. Di Laravel, cara idiomatiknya adalah
      `Schedule::command('report:generate')->everyMinute()->withoutOverlapping();`.
      Angka `pgrep -c -f` juga menghitung shell `sh -c` yang dibuat cron, karena command line-nya ikut berisi
      `report:generate`.
    tests:
      pass:
        - |
          pgrep -af report:generate
          ps -o pid,etime,cmd -p $(pgrep -d, -f report:generate)
          pkill -TERM -f report:generate
          * * * * * cd /srv/app && flock -n /tmp/report.lock php artisan report:generate >> storage/logs/report.log 2>&1
        - |
          ps aux | grep report:generate
          pkill -f report:generate
          tail -n 50 /srv/app/storage/logs/report.log
          # routes/console.php: Schedule::command('report:generate')->everyMinute()->withoutOverlapping();
      fail:
        - pkill php
        - killall -9 php
        - |
          pgrep -af report:generate
          pkill -f report:generate
    source: https://man7.org/linux/man-pages/man1/flock.1.html

  - id: c02
    tier: C
    type: scenario
    objective: container
    prompt: |
      Setiap deploy, container worker butuh 10 detik untuk berhenti, dan job yang sedang berjalan terputus
      di tengah jalan. Laravel seharusnya menyelesaikan job dulu saat menerima SIGTERM. Tulis perintah
      diagnosis dan perubahan file (satu per baris; baris Dockerfile/skrip boleh ditulis apa adanya) supaya
      worker benar-benar menerima SIGTERM.
    context: |
      $ cat docker/entrypoint.sh
      #!/bin/sh
      php artisan migrate --force
      php artisan queue:work --tries=3
      $ grep -E '^(ENTRYPOINT|CMD)' Dockerfile
      ENTRYPOINT /usr/local/bin/entrypoint.sh
      $ time docker stop worker
      worker
      real    0m10.41s
      $ docker inspect -f '{{.State.ExitCode}}' worker
      137
    checklist:
      - id: diagnose
        label: Melihat siapa PID 1 / exit code (docker top, docker exec ... ps, docker inspect)
        patterns: ['docker\s+top\s+\S+', 'docker\s+exec\s+.*\bps\b', 'docker\s+inspect\s+.*(exitcode|state)']
      - id: exec-script
        label: Memakai exec di entrypoint (exec php artisan queue:work / exec "$@")
        required: true
        patterns: ['^exec\s+php\s+artisan\s+queue:work', '^exec\s+"?\$@"?']
      - id: exec-form
        label: ENTRYPOINT/CMD dalam exec form (JSON array)
        required: true
        patterns: ['^(entrypoint|cmd)\s*\[']
      - id: init
        label: Init kecil untuk reaping zombie (--init / tini / init true)
        patterns: ['--init', 'tini', 'dumb-init', 'init:\s*true']
      - id: grace
        label: Masa tunggu sesuai durasi job (stop_grace_period / docker stop -t)
        patterns: ['stop_grace_period', 'docker\s+stop\s+(-t|--time(out)?)[= ]?\s*\d+', '--stop-timeout', 'terminationgraceperiodseconds']
      - id: no-force
        label: Tidak "memperbaiki" dengan mematikan paksa (docker kill / rm -f / kill -9)
        forbid: true
        patterns: ['docker\s+kill\b', 'docker\s+rm\s+-f', 'kill\s+-9']
    solution: |
      ```dockerfile
      ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
      CMD ["php", "artisan", "queue:work", "--tries=3"]
      ```
      ```bash
      #!/bin/sh
      set -e
      php artisan migrate --force
      exec "$@"            # php menggantikan sh dan menjadi PID 1
      ```
      ```yaml
      # compose.yaml
      stop_grace_period: 60s   # >= durasi job terpanjang
      ```
      Ada dua lapis masalah. **Shell form** `ENTRYPOINT skrip` dijalankan sebagai `/bin/sh -c "skrip"`, dan
      di lab `sh` (dash 0.5.12) **tidak** meng-`exec` perintah terakhir: `sh` tetap jadi parent. Lalu skripnya
      menjalankan `php` sebagai anak. SIGTERM dari `docker stop` hanya sampai ke PID 1 (`sh`), yang tidak
      meneruskannya, sehingga 10 detik kemudian semua dibunuh SIGKILL (137).
      Hasil lab dengan aplikasi yang menangani SIGTERM:
      - Tanpa `exec`: exit 137, aplikasi tidak sempat cleanup.
      - Dengan `exec`: exit 0 dan log "graceful shutdown" tercetak.
      - `--init` **saja**: container cepat berhenti (143), tapi log "graceful shutdown" tetap tidak muncul,
        karena tini meneruskan sinyal ke `sh`, bukan ke aplikasi di belakangnya.
      Jadi `--init` adalah pelengkap untuk reaping zombie, bukan pengganti `exec`.
    tests:
      pass:
        - |
          docker top worker
          ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
          exec php artisan queue:work --tries=3
        - |
          docker exec worker ps -ef
          exec "$@"
          ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
          CMD ["php", "artisan", "queue:work", "--tries=3"]
          stop_grace_period: 60s
      fail:
        - docker kill worker
        - docker run --init worker-image
        - |
          docker top worker
          ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
          docker stop -t 60 worker
    source: https://docs.docker.com/reference/dockerfile/#entrypoint

  - id: c03
    tier: C
    type: scenario
    objective: exit-codes
    prompt: |
      Container worker restart terus saat memproses import CSV besar. Tulis perintah (satu per baris) untuk
      memastikan penyebabnya, melihat pemakaian memorinya, dan memperbaikinya dengan benar.
    context: |
      $ docker ps -a --filter name=worker --format '{{.Names}}  {{.Status}}'
      worker  Restarting (137) 8 seconds ago
      $ docker logs --tail 1 worker
        2026-11-20 11:02:10 App\Jobs\ImportCsv ................ RUNNING
      $ grep -B1 -A1 'memory:' compose.yaml
              limits:
                memory: 256M
    checklist:
      - id: confirm
        label: Memastikan OOM (docker inspect .State.OOMKilled, dmesg, journalctl -k)
        required: true
        patterns: ['docker\s+inspect\s+.*oomkilled', 'docker\s+inspect\s+.*\.state', 'dmesg.*(oom|kill)', 'journalctl\s+(-\S+\s+)*(-k|--dmesg)', 'docker\s+events']
      - id: interpret
        label: Mengartikan 137 = 128 + 9 (SIGKILL)
        patterns: ['137', '128\s*\+\s*9', 'sigkill']
      - id: usage
        label: Melihat pemakaian memori (docker stats, ps rss, free)
        patterns: ['docker\s+stats', 'ps\s+.*(rss|%mem)', '^top\b', 'free\s+-']
      - id: fix
        label: Memperbaiki (limit realistis, --memory/--max-jobs worker, atau proses CSV per chunk)
        required: true
        patterns: ['memory:\s*\d+', '--memory[= ]\d+', 'mem_limit', '--max-jobs', '--max-time', 'chunk', 'lazy\(', 'cursor\(']
      - id: no-disable-oom
        label: Tidak mematikan OOM killer (--oom-kill-disable / oom_score_adj -1000)
        forbid: true
        patterns: ['oom-kill-disable', 'oom_score_adj\D*-1000']
    solution: |
      ```bash
      docker inspect -f '{{.State.OOMKilled}} {{.State.ExitCode}}' worker   # true 137
      journalctl -k | grep -i 'out of memory'          # atau: dmesg | grep -i oom
      docker stats --no-stream worker
      ```
      Di lab, container dengan `--memory 64m` yang menghabiskan memori menghasilkan `OOMKilled=true ExitCode=137`,
      dan log kernel: `Memory cgroup out of memory: Killed process 2585 (bash) ...`. 137 = 128 + 9: OOM killer
      memakai SIGKILL, jadi aplikasi tidak sempat menulis log apa pun — itulah sebabnya log berhenti di `RUNNING`.
      Perbaikan:
      - Proses CSV per chunk (`LazyCollection`/`chunk`) supaya memori tidak tumbuh mengikuti ukuran file.
      - Naikkan limit memori ke angka yang terukur dari `docker stats`.
      - Jalankan worker dengan `--memory`/`--max-jobs` agar ia restart sendiri secara terkendali sebelum
        menyentuh limit.

      Mematikan OOM killer hanya memindahkan masalah: host yang kehabisan memori.
    tests:
      pass:
        - |
          docker inspect -f '{{.State.OOMKilled}} {{.State.ExitCode}}' worker
          # 137 = 128 + 9 (SIGKILL dari OOM killer)
          docker stats --no-stream worker
          memory: 512M
        - |
          journalctl -k | grep -i oom
          docker stats worker
          php artisan queue:work --max-jobs=100 --memory=200
      fail:
        - docker restart worker
        - |
          docker inspect worker
          docker run --oom-kill-disable worker-image
        - |
          docker stats worker
          docker logs worker
    source: https://docs.docker.com/engine/containers/run/

---

# Process & Signal

Setiap perintah yang kamu jalankan, setiap worker, web server, dan container adalah **proses**. Banyak insiden
production sebenarnya soal proses: job yang menumpuk, worker yang tidak mau berhenti, container yang selalu
keluar dengan 137, atau zombie yang memenuhi tabel proses. Modul ini membahas cara melihat proses, berbicara
dengannya lewat **sinyal**, dan perilaku khususnya di dalam container.

> [!NOTE]
> Semua contoh output dan klaim perilaku dijalankan ulang di Ubuntu 24.04 (procps-ng 4.0.4, bash 5.2, dash 0.5.12)
> dan Docker Engine 29.4. Image `ubuntu:24.04` tidak membawa `ps` — pasang `procps` (dan `psmisc` untuk `pstree`,
> `killall`, `fuser`). Image minimal lain (distroless, scratch) bahkan tidak punya shell.

## 1. Model proses

- Setiap proses punya **PID** dan **PPID** (PID parent-nya).
- Proses baru lahir lewat `fork()`, yang menggandakan parent, lalu biasanya `exec()`, yang mengganti isinya dengan
  program lain. Shell menjalankan `ls` persis seperti itu.
- **PID 1** (systemd di host, perintah utama di container) adalah akar pohon proses dan mengadopsi proses yatim.

```mermaid
flowchart TD
    I["PID 1 — systemd"] --> S["sshd"]
    I --> N["nginx: master"]
    N --> W1["nginx: worker"]
    N --> W2["nginx: worker"]
    S --> B["bash (sesi kamu)"]
    B --> P["ps -ef"]
    I --> C["cron"] --> SH["sh -c 'php artisan ...'"] --> PHP["php"]
```

> [!TIP]
> `exec` di shell **tidak** membuat proses baru: program menggantikan shell dengan PID yang sama. Ini kunci
> agar aplikasi menjadi PID 1 di container (bagian 8).

## 2. Melihat proses

```bash
ps aux                                    # BSD: USER %CPU %MEM RSS STAT COMMAND
ps -ef                                    # UNIX: UID PID PPID STIME CMD
ps -eo pid,ppid,user,%mem,etime,cmd --sort=-%mem | head
ps -fp 4321                               # satu proses
pgrep -a nginx                            # PID + command line, tanpa dirinya sendiri
pstree -p                                 # pohon proses dengan PID
top                                       # interaktif: P (CPU), M (memori), k (kill), 1 (per core)
```

| Kolom | Arti |
|---|---|
| `RSS` | Memori fisik yang sedang dipakai (KiB) |
| `VSZ` | Ukuran virtual (termasuk yang belum disentuh) — jarang berguna untuk kapasitas |
| `STAT` | Status (bagian 3) |
| `etime` | Lama berjalan — bagus untuk menemukan job yang "nyangkut" |
| `%CPU` di `ps` | Rata-rata sepanjang umur proses, **bukan** saat ini; pakai `top` untuk nilai sesaat |

> [!WARNING]
> `ps aux | grep nginx` selalu ikut menampilkan proses `grep nginx` itu sendiri (terlihat di lab), dan skrip
> yang menghitung baris jadi salah. Pakai `pgrep`. Ingat juga bahwa pgrep/pkill tanpa `-f` hanya mencocokkan
> **nama** proses yang maksimal 15 karakter. Di lab, pola 21 karakter memicu peringatan
> `will result in zero matches`.

### `/proc/PID` — semua info mentah

```bash
cat /proc/4321/status | grep -E 'State|PPid|VmRSS|Threads'
tr '\0' ' ' < /proc/4321/cmdline; echo
tr '\0' '\n' < /proc/4321/environ     # hanya pemilik/root (mode 0400)
ls -l /proc/4321/fd                    # file & socket yang dibuka
ls -l /proc/4321/cwd /proc/4321/exe    # direktori kerja dan binary
cat /proc/4321/limits                  # ulimit efektif (mis. Max open files)
```

## 3. Status proses dan load average

| STAT | Arti | Catatan |
|---|---|---|
| `R` | Running / runnable | Memakai atau menunggu CPU |
| `S` | Sleeping (interruptible) | Menunggu event; mayoritas proses |
| `D` | Uninterruptible sleep | Menunggu I/O (disk, NFS); tidak bisa dibunuh sampai I/O kembali |
| `T` | Stopped | Kena SIGSTOP/SIGTSTP (Ctrl+Z) |
| `Z` | Zombie | Sudah mati, menunggu `wait()` dari parent |

Di lab, `kill -STOP` mengubah status `sleep` menjadi `T`, lalu `kill -CONT` mengembalikannya ke `S`.

> [!INSIGHT]
> Load average Linux bukan "pemakaian CPU". Ia menghitung proses `R` **ditambah** proses `D`. Itu sebabnya server
> dengan disk atau NFS yang macet bisa punya load 30 dengan CPU hampir idle. Brendan Gregg menelusuri patch kernel
> tahun 1993 yang menambahkan `D` ke perhitungan dan menunjukkan cara membedakannya.
> Sumber: [Linux Load Averages: Solving the Mystery](https://www.brendangregg.com/blog/2017-08-08/linux-load-averages.html)

## 4. Sinyal

| Sinyal | No. | Default | Dipakai untuk |
|---|---|---|---|
| `SIGHUP` | 1 | Terminate | Terminal ditutup. Banyak daemon memakainya untuk reload config |
| `SIGINT` | 2 | Terminate | Ctrl+C |
| `SIGQUIT` | 3 | Core dump | Ctrl+\\ |
| `SIGKILL` | 9 | Terminate | **Tidak bisa ditangkap.** Pilihan terakhir |
| `SIGUSR1` | 10 | Terminate | Ditentukan aplikasi (mis. reopen log) |
| `SIGTERM` | 15 | Terminate | Permintaan berhenti yang sopan — default `kill`, `docker stop`, systemd |
| `SIGCONT` | 18 | Continue | Melanjutkan proses yang di-stop |
| `SIGSTOP` | 19 | Stop | **Tidak bisa ditangkap** |
| `SIGTSTP` | 20 | Stop | Ctrl+Z |

```bash
kill 4321                 # SIGTERM
kill -9 4321              # SIGKILL — setelah TERM gagal
kill -HUP 4321            # reload (bila aplikasi mendukung)
kill -0 4321              # cek: masih hidup?
pkill -f queue:work       # berdasarkan command line
kill -TERM -- -2500       # seluruh process group 2500
```

Urutan yang benar: **TERM → tunggu → baru KILL**. Itu juga yang dilakukan `docker stop`, `systemctl stop`, dan
Kubernetes. SIGKILL langsung berarti transaksi setengah jalan, file sementara, dan lock yang tertinggal.

```bash
trap 'echo "cleanup"; rm -f /tmp/job.lock; exit 0' TERM INT    # di skrip bash
```

> [!DANGER]
> `pkill -f pola` juga cocok dengan **shell yang menjalankannya** bila command line shell itu mengandung pola
> yang sama, misalnya `ssh server 'pkill -f myapp'` atau `bash -c '...'`. pgrep/pkill memang mengecualikan
> dirinya sendiri, tapi tidak parent-nya. Di lab, `bash -c 'pkill -f "sleep 300"; echo survived'` membunuh
> `bash`-nya sendiri: `Terminated`, exit 143, dan `survived` tidak tercetak.
> Sumber: [pgrep(1)](https://man7.org/linux/man-pages/man1/pgrep.1.html) — "will never report itself as a match"

## 5. Exit code

| Kode | Arti |
|---|---|
| `0` | Sukses |
| `1`–`125` | Gagal; arti ditentukan program |
| `124` | `timeout` habis (GNU coreutils) |
| `126` / `127` | Tidak bisa dieksekusi / command tidak ditemukan |
| `128+N` | Mati oleh sinyal N: **130** Ctrl+C, **137** SIGKILL, **143** SIGTERM |

Semua diuji di lab: `kill` → 143, `kill -9` → 137, `timeout 1 sleep 5` → 124, `timeout -s KILL` → 137.

## 6. Job control, `nohup`, dan sesi SSH

```bash
./backup.sh &            # background; PID di $!
jobs -l                  # daftar job + PID
fg %1                    # ke foreground
# Ctrl+Z, lalu:
bg                       # lanjutkan di background
nohup ./migrate.sh > migrate.log 2>&1 &   # tahan SIGHUP saat logout
disown %1                # lepaskan job dari tabel job shell
```

Untuk pekerjaan panjang lewat SSH, `tmux`/`screen` lebih aman, karena kamu bisa menyambung lagi dan melihat
output. Proses yang harus selalu hidup sebaiknya jadi **unit systemd**, bukan `nohup`.

## 7. Prioritas: `nice` dan `renice`

```bash
nice -n 10 ./report.sh          # proses baru, prioritas lebih rendah
renice -n 15 -p 4321            # proses yang sudah berjalan
ps -o pid,ni,cmd -p 4321
ionice -c3 -p 4321              # prioritas I/O "idle" (CFQ/BFQ)
```

User biasa hanya bisa **menaikkan** nice (mengalah). Di lab, `renice -n 0` oleh user biasa →
`Permission denied`. Di container Docker, bahkan root ditolak saat menurunkan nice, karena capability
`CAP_SYS_NICE` tidak termasuk default.

## 8. Zombie dan orphan

- **Orphan**: parent mati lebih dulu. Proses diadopsi PID 1 (atau subreaper) dan tetap berjalan normal.
- **Zombie**: proses sudah mati, tapi parent belum memanggil `wait()`. Tidak memakan CPU/memori, tetapi memakan
  slot PID. Jutaan zombie bisa menghabiskan `pid_max`.

```text
$ ps -eo pid,ppid,stat,cmd | grep defunct
    324     323 Z    [sleep] <defunct>
```

Di lab, `kill -9 324` tidak berpengaruh. Setelah parent (323) keluar, zombie langsung hilang. Perbaikannya
selalu di **parent**: perbaiki kodenya, atau restart parent.

## 9. Proses di container: PID 1

Perintah utama container adalah PID 1 di namespace-nya, dan ini punya dua konsekuensi:

1. **Kernel tidak menerapkan aksi default sinyal ke PID 1.** Program tanpa handler SIGTERM (mis. `sleep`) tidak
   mati oleh `docker stop`, tetapi menunggu timeout lalu di-SIGKILL.
2. PID 1 wajib me-**reap** zombie. Aplikasi biasa tidak melakukannya.

| Konfigurasi (aplikasi menangani SIGTERM) | Hasil di lab |
|---|---|
| `ENTRYPOINT skrip` (shell form) tanpa `exec` | `sh` = PID 1, aplikasi anak → **137**, tanpa cleanup |
| Skrip dengan `exec aplikasi` | Aplikasi = PID 1 → **exit 0**, log "graceful shutdown" |
| `sleep 300` sebagai PID 1 | **137** (TERM diabaikan) |
| `--init` + `sleep 300` | `docker-init` = PID 1 → **143** dalam 0,5 detik |
| `--init` + `sh` tanpa `exec` | **143** cepat, tapi aplikasi tetap tidak sempat cleanup |

```dockerfile
# Benar
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "artisan", "queue:work"]
```

```bash
#!/bin/sh
set -e
php artisan migrate --force
exec "$@"
```

> [!INSIGHT]
> Di Ubuntu 24.04, `sh` adalah dash 0.5.12, dan di lab `sh -c 'sleep 77'` tetap membiarkan `sh` sebagai parent
> — tidak ada optimasi exec untuk perintah terakhir. Jadi shell form di Dockerfile hampir pasti membuat `sh`
> sebagai PID 1. Dokumentasi Docker: dengan shell form, aplikasi "will not receive Unix signals".
> Sumber: [Dockerfile reference — ENTRYPOINT](https://docs.docker.com/reference/dockerfile/#entrypoint)

> [!INSIGHT]
> Exit code 137 + `OOMKilled=true` berarti cgroup memori container penuh dan kernel memilih proses untuk
> di-SIGKILL. Di lab (`--memory 64m`), log kernel menulis
> `Memory cgroup out of memory: Killed process 2585 (bash)`. Karena SIGKILL, aplikasi tidak bisa mencatat
> apa pun — log hanya "berhenti". Selalu cek `docker inspect -f '{{.State.OOMKilled}}'` sebelum menyalahkan kode.
> Sumber: [docker run — runtime constraints](https://docs.docker.com/engine/containers/run/)

## 10. Keamanan: apa yang bisa dilihat orang lain

> [!DANGER]
> Argumen command line terlihat oleh **semua user** lewat `ps`/`/proc/PID/cmdline`. Di lab, user biasa `u1`
> melihat `mysql -pS3cr3t` milik root di `ps aux`. Sebaliknya, `/proc/1/environ` (mode `0400`) ditolak:
> `Permission denied`. Jangan pernah kirim password lewat argumen. Pakai file config (`~/.my.cnf` mode 600),
> stdin, atau environment variable. Di host bersama, mount `/proc` dengan `hidepid=2`.
> Sumber: [proc(5)](https://man7.org/linux/man-pages/man5/proc.5.html)

## 11. Troubleshooting proses

```mermaid
flowchart TD
    S[Gejala] --> L{Server lambat?}
    L -- ya --> T[top / ps --sort=-%cpu]
    T --> D{Banyak status D?}
    D -- ya --> IO[Bottleneck I/O: vmstat, iostat]
    D -- tidak --> P[Proses rakus / menumpuk:<br/>pgrep -af, etime, flock]
    S --> X{Proses tidak mau berhenti?}
    X -- ya --> TR[TERM → tunggu → KILL<br/>status D? tunggu I/O]
    S --> C{Container exit 137?}
    C -- OOMKilled=true --> OOM[Memori: docker stats, chunk, limit]
    C -- false --> PID1[PID 1 tidak menerima TERM:<br/>exec form + exec di skrip]
    S --> Z{Banyak zombie?}
    Z -- ya --> PA[Perbaiki/restart parent<br/>--init di container]
```

| Kebiasaan | Kenapa |
|---|---|
| `pgrep`/`pkill`, bukan `ps \| grep` | Tidak ikut mencocokkan dirinya sendiri |
| TERM dulu, KILL belakangan | Memberi aplikasi kesempatan cleanup |
| `flock -n` / `withoutOverlapping` untuk cron | Mencegah job menumpuk |
| `timeout` di cron/CI/healthcheck | Job macet tidak menggantung selamanya |
| Exec form + `exec` di entrypoint | Aplikasi menjadi PID 1 dan menerima SIGTERM |
| Cek `OOMKilled` untuk setiap 137 | Membedakan OOM dari timeout stop |
| Jangan taruh secret di argumen | Argumen terlihat semua user lewat `ps` |
