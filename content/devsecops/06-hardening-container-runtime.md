---
format: 1
id: sec-hardening-container-runtime
course:
  slug: devsecops
  title: DevSecOps
  description: Keamanan untuk DevOps dari dua sisi — cara kerja red team dan cara blue team mendeteksi serta menutupnya. Model ancaman, hardening server & container, keamanan web, ketersediaan (anti-DDoS), deteksi, respons insiden, dan keamanan pipeline. Semua praktik di lab terisolasi milik sendiri.
title: Hardening Container & Runtime
summary: "Setelah image bersih, batasi apa yang boleh dilakukan container saat berjalan agar satu kompromi tidak menjadi penguasaan host. Sisi red: container escape lewat docker socket dan --privileged, eskalasi setuid, resource exhaustion. Sisi blue: jalankan non-root, drop semua capability lalu tambah seperlunya, read-only rootfs + tmpfs, no-new-privileges, seccomp/AppArmor, dan resource limit (pids/memory). Semua efeknya dibuktikan di lab."
order: 6
version: 1
status: verified
updated: '2026-11-25'
tested_on:
  - Docker Desktop (Windows), Engine 29.4.1 — capabilities, docker.sock escape, --read-only, --pids-limit, no-new-privileges
  - alpine:3.20 + biner setuid-root (gcc) untuk uji eskalasi; "host" di sini adalah VM Docker Desktop (WSL2)
objectives:
  - id: caps
    text: Mengelola Linux capabilities — drop semua, tambah seperlunya
  - id: privileged
    text: Memahami bahaya --privileged dan mount docker socket (container escape)
  - id: nonroot
    text: Menjalankan container sebagai user non-root
  - id: filesystem
    text: Memakai read-only rootfs + tmpfs dan memahami kepemilikan volume
  - id: nnp
    text: Mencegah eskalasi dengan no-new-privileges, seccomp, dan AppArmor
  - id: limits
    text: Membatasi sumber daya (pids, memory) agar container tidak melumpuhkan host
sources:
  - title: Docker — runtime security & capabilities
    url: https://docs.docker.com/engine/security/
  - title: Docker — seccomp profiles
    url: https://docs.docker.com/engine/security/seccomp/
  - title: Docker — AppArmor profiles
    url: https://docs.docker.com/engine/security/apparmor/
  - title: capabilities(7) — Linux capability sets
    url: https://man7.org/linux/man-pages/man7/capabilities.7.html
  - title: no_new_privs (kernel) & --security-opt no-new-privileges
    url: https://docs.kernel.org/userspace-api/no_new_privs.html
  - title: OWASP — Docker Security Cheat Sheet
    url: https://cheatsheetseries.owasp.org/cheatsheets/Docker_Security_Cheat_Sheet.html
  - title: MITRE ATT&CK — Escape to Host (T1611)
    url: https://attack.mitre.org/techniques/T1611/
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: privileged
    prompt: Keluar dari isolasi container untuk menguasai host disebut dengan istilah apa (ATT&CK T1611)?
    answers: ['container escape']
    patterns: ['container[- ]?escape', 'escape to host', 'break ?out', 'keluar (dari|ke) host', 'lolos (dari )?container']
    tests:
      fail: ['privilege escalation biasa', 'lateral movement', 'sandbox', 'port scanning']
    explanation: 'Container escape (Escape to Host, T1611) mengubah "satu container jebol" menjadi "host jebol" — dan di host ada semua container lain. Container bukan batas keamanan sekuat VM: ia berbagi kernel host. Modul ini menutup jalur-jalur escape yang umum: docker socket, `--privileged`, mount host, dan capability berlebih. Prinsip "assume breach" (modul 01): anggap aplikasi dalam container bisa dikuasai, lalu batasi kerusakannya.'
    source: https://attack.mitre.org/techniques/T1611/

  - id: a02
    tier: A
    type: text
    objective: privileged
    prompt: Mem-mount `/var/run/docker.sock` ke dalam container berbahaya karena memberi container kemampuan apa?
    answers: ['mengendalikan daemon docker host']
    patterns: ['(kendali|kontrol|mengendalikan|akses penuh)[^\n]*(daemon|docker|host)', 'kendali[^\n]*host', 'jalankan container[^\n]*(host|privileged)', 'root (di|ke) host']
    tests:
      fail: ['melihat log saja', 'mengatur DNS', 'mempercepat build', 'membaca environment']
    explanation: 'Di lab, container dengan `docker.sock` ter-mount memanggil daemon host, lalu meluncurkan container baru `-v /:/host` dan membaca `/etc/shadow` host serta hostname VM (`debuerreotype`). Akses ke docker socket = **root di host**, karena kamu bisa menjalankan container privileged apa pun yang mem-mount seluruh host. Jangan pernah mount docker socket ke container yang tidak tepercaya (banyak image CI/CD melakukannya — itu risiko besar). Alternatif: socket proxy berizin terbatas, atau rootless/BuildKit.'
    source: https://attack.mitre.org/techniques/T1611/

  - id: a03
    tier: A
    type: text
    objective: privileged
    prompt: Flag `docker run` yang memberi container hampir semua capability + akses device host (sangat berbahaya) adalah apa?
    answers: ['--privileged']
    patterns: ['--privileged', 'privileged']
    tests:
      fail: ['--cap-add ALL', '--root', '--host', '--admin']
    explanation: 'Di lab, `--privileged` memberi CapEff `000001ffffffffff` — **semua** ~40 capability (termasuk `sys_admin`, `sys_module`, `mknod`), ditambah akses ke device host. Ini praktis setara root di host dan jalur escape termudah. Hampir tidak pernah benar-benar dibutuhkan; kalau sebuah container "perlu privileged", biasanya ia hanya butuh satu-dua capability spesifik (mis. `NET_ADMIN`) atau satu device — berikan itu saja, bukan `--privileged`.'
    source: https://docs.docker.com/engine/security/

  - id: a04
    tier: A
    type: command
    objective: caps
    prompt: Jalankan image `myapp` dengan **membuang semua Linux capability**.
    answers: ['docker run --cap-drop ALL myapp']
    patterns: ['^(?=.*\s--cap-drop ALL(\s|$))docker run( \S+)* myapp$']
    tests:
      pass: ['docker run --rm --cap-drop ALL myapp', 'docker run --cap-drop ALL -d myapp']
      fail: ['docker run myapp', 'docker run --cap-add ALL myapp', 'docker run --privileged myapp', 'docker run --cap-drop NET_RAW myapp']
    explanation: 'Di lab, `--cap-drop ALL` membuat CapEff `0000000000000000` (nol capability). Default container sudah punya 14 capability (`CapEff a80425fb`: chown, setuid, net_raw, dll) yang sering tidak diperlukan aplikasi web biasa. Prinsip least privilege: buang semua, lalu `--cap-add` hanya yang benar-benar dibutuhkan (a05). Banyak aplikasi berjalan baik tanpa satu pun capability.'
    source: https://man7.org/linux/man-pages/man7/capabilities.7.html

  - id: a05
    tier: A
    type: command
    objective: caps
    prompt: Jalankan `myapp` tanpa capability apa pun **kecuali** `NET_BIND_SERVICE` (agar bisa listen di port < 1024).
    answers: ['docker run --cap-drop ALL --cap-add NET_BIND_SERVICE myapp']
    patterns: ['^(?=.*\s--cap-drop ALL(\s|$))(?=.*\s--cap-add NET_BIND_SERVICE(\s|$))docker run( \S+)* myapp$']
    tests:
      pass: ['docker run --cap-add NET_BIND_SERVICE --cap-drop ALL myapp', 'docker run --cap-drop ALL --cap-add NET_BIND_SERVICE --rm myapp']
      fail: ['docker run --cap-add NET_BIND_SERVICE myapp', 'docker run --cap-drop ALL myapp', 'docker run --privileged myapp']
    explanation: 'Pola least privilege: `--cap-drop ALL` lalu `--cap-add` yang perlu. `NET_BIND_SERVICE` mengizinkan bind ke port < 1024 (mis. 80/443) tanpa root — alternatif aman daripada menjalankan seluruh proses sebagai root. Urutan drop-dulu-lalu-add memastikan hanya capability yang kamu sebut yang ada. Di Compose: `cap_drop: [ALL]` + `cap_add: [NET_BIND_SERVICE]`.'
    source: https://man7.org/linux/man-pages/man7/capabilities.7.html

  - id: a06
    tier: A
    type: text
    objective: nonroot
    prompt: Secara default, proses di dalam container berjalan sebagai user apa jika Dockerfile tidak menyetel `USER`?
    answers: ['root']
    patterns: ['^root$', 'uid 0', 'uid=0', 'root \(uid 0\)']
    tests:
      fail: ['nobody', 'uid 1000', 'appuser', 'daemon']
    explanation: 'Di lab, `docker run alpine id` → `uid=0(root)`. Default root berbahaya: jika aplikasi dikompromikan, penyerang adalah root **di dalam** container — lebih mudah memanfaatkan capability, menulis ke mount, dan mencoba escape. Setel `USER appuser` di Dockerfile (atau `--user`) agar proses non-root. Banyak CVE container escape hanya bisa dieksploitasi dari root dalam container.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Docker_Security_Cheat_Sheet.html

  - id: a07
    tier: A
    type: command
    objective: nonroot
    prompt: Jalankan `myapp` sebagai user **non-root** dengan UID dan GID `1000`.
    answers: ['docker run --user 1000:1000 myapp']
    patterns: ['^(?=.*\s(--user|-u) [1-9]\d*(:[1-9]\d*)?(\s|$))docker run( \S+)* myapp$']
    tests:
      pass: ['docker run -u 1000:1000 myapp', 'docker run --user 1000 --rm myapp']
      fail: ['docker run --user 0:0 myapp', 'docker run --user root myapp', 'docker run myapp']
    explanation: 'Di lab, `--user 1000:1000` → `uid=1000 gid=1000` (non-root). Ini menimpa `USER` image saat runtime. Lebih baik lagi set `USER` di Dockerfile sejak build + jalankan `--user` sebagai penegakan. Catatan: aplikasi harus dirancang untuk non-root (tidak menulis ke path yang butuh root, tidak bind port < 1024 tanpa `NET_BIND_SERVICE`). `--user 0:0` adalah root — bukan hardening.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Docker_Security_Cheat_Sheet.html

  - id: a08
    tier: A
    type: command
    objective: filesystem
    prompt: Jalankan `myapp` dengan **filesystem root read-only**, tetapi sediakan `/tmp` yang bisa ditulis via tmpfs.
    answers: ['docker run --read-only --tmpfs /tmp myapp']
    patterns: ['^(?=.*\s--read-only(\s|$))(?=.*\s--tmpfs /tmp(\s|$))docker run( \S+)* myapp$']
    tests:
      pass: ['docker run --tmpfs /tmp --read-only myapp', 'docker run --read-only --tmpfs /tmp --rm myapp']
      fail: ['docker run --read-only myapp', 'docker run --tmpfs /tmp myapp', 'docker run -v /tmp myapp']
    explanation: 'Di lab, `--read-only` → `touch /oops` gagal `Read-only file system`; tambah `--tmpfs /tmp` → `/tmp` bisa ditulis. Root read-only mencegah penyerang menulis webshell/binary ke dalam container dan membuatnya mendekati immutable. Beri jalur tulis yang spesifik lewat `--tmpfs` (RAM, hilang saat stop) atau volume bernama untuk data yang harus persisten. Di Compose: `read_only: true` + `tmpfs: [/tmp]`.'
    source: https://docs.docker.com/engine/security/

  - id: a09
    tier: A
    type: text
    objective: filesystem
    prompt: Di lab, container yang berjalan sebagai root menulis file ke volume, lalu file itu dimiliki oleh UID berapa di volume tersebut?
    answers: ['0']
    patterns: ['^0$', 'uid 0', 'root', 'uid=0']
    tests:
      fail: ['1000', 'nobody', 'acak', 'tidak ada pemilik']
    explanation: 'Di lab, `ls -ln` menunjukkan file dimiliki `0 0` (root). Kepemilikan UID lintas container/host bersifat **numerik** — UID 0 di container = UID 0 di host (kernel sama). Jadi container root yang menulis ke bind mount host membuat file root-owned di host, dan sebaliknya file sensitif host (mis. yang di-mount) bisa diakses root container. Ini alasan lain menjalankan non-root + berhati-hati dengan mount.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Docker_Security_Cheat_Sheet.html

  - id: a10
    tier: A
    type: command
    objective: nnp
    prompt: Jalankan `myapp` dengan opsi yang **mencegah proses menaikkan privilege** (mis. lewat binary setuid).
    answers: ['docker run --security-opt=no-new-privileges myapp']
    patterns: ['^(?=.*\s--security-opt[= ]no-new-privileges(:true)?(\s|$))docker run( \S+)* myapp$']
    tests:
      pass: ['docker run --security-opt no-new-privileges myapp', 'docker run --security-opt=no-new-privileges:true --rm myapp']
      fail: ['docker run myapp', 'docker run --privileged myapp', 'docker run --security-opt seccomp=unconfined myapp']
    explanation: 'Di lab, biner setuid-root membuat user `uid=1000` menjadi `euid=0` (ESCALATED) secara default; dengan `--security-opt=no-new-privileges`, hasilnya `euid=1000` dan `setuid(0) FAILED`. Flag ini menyetel bit kernel `no_new_privs` sehingga proses (dan anaknya) tidak pernah bisa menambah privilege via setuid/setgid/file capabilities. Pasangkan dengan non-root + cap-drop: bahkan jika ada binary setuid nyasar, ia tak bisa dipakai naik ke root.'
    source: https://docs.kernel.org/userspace-api/no_new_privs.html

  - id: a11
    tier: A
    type: command
    objective: limits
    prompt: Jalankan `myapp` dengan batas **maksimum 100 proses** (agar fork bomb tidak melumpuhkan host).
    answers: ['docker run --pids-limit 100 myapp']
    patterns: ['^(?=.*\s--pids-limit[= ]100(\s|$))docker run( \S+)* myapp$']
    tests:
      pass: ['docker run --pids-limit=100 myapp', 'docker run --pids-limit 100 --rm myapp']
      fail: ['docker run myapp', 'docker run --cpus 1 myapp', 'docker run --pids 100 myapp']
    explanation: 'Di lab, `--pids-limit=50` membuat skrip yang men-spawn 200 proses gagal dengan `can''t fork: Resource temporarily unavailable`, sedangkan `--pids-limit=300` berjalan normal. Tanpa batas, fork bomb di satu container bisa menghabiskan PID host dan melumpuhkan **semua** container (serangan Availability, modul 08). Batasi juga `--memory` dan `--cpus`. Ini pertahanan DoS internal: satu container nakal tidak boleh menjatuhkan tetangganya.'
    source: https://docs.docker.com/engine/security/

  - id: a12
    tier: A
    type: text
    objective: nnp
    prompt: Mekanisme kernel yang memfilter **system call** apa saja yang boleh dipanggil container (Docker punya profil default) disebut apa?
    answers: ['seccomp']
    patterns: ['seccomp', 'secure computing']
    tests:
      fail: ['selinux', 'apparmor', 'cgroups', 'namespaces']
    explanation: 'seccomp (secure computing) membatasi syscall yang boleh dipakai proses. Docker memasang profil seccomp **default** yang sudah memblokir ~44 syscall berbahaya (mis. `mount`, `reboot`, `kexec_load`) — itu salah satu alasan container tanpa `--privileged` lebih aman. `--security-opt seccomp=profil.json` memakai profil ketat buatanmu; `seccomp=unconfined` mematikannya (jangan!). Makin sedikit syscall yang diizinkan, makin kecil permukaan eksploit kernel.'
    source: https://docs.docker.com/engine/security/seccomp/

  - id: a13
    tier: A
    type: text
    objective: caps
    prompt: Di lab, default container (tanpa flag) memiliki 14 capability termasuk `cap_setuid` dan `cap_net_raw`. Apakah aplikasi web biasa membutuhkan semua itu?
    answers: ['tidak']
    patterns: ['^tidak$', '^tidak,?.*', '^no$', 'tidak (perlu|butuh)']
    tests:
      fail: ['ya', 'ya perlu', 'wajib']
    explanation: 'Tidak. Aplikasi web biasa (mendengarkan port, membaca file, query DB) umumnya tidak butuh `setuid`, `net_raw` (membuat paket mentah), `mknod`, atau `chown`. Capability default ada untuk kompatibilitas luas, bukan karena kebutuhan. `cap_net_raw` saja, misalnya, memungkinkan spoofing paket & sniffing bila container dikuasai. Buang semua (`--cap-drop ALL`) lalu uji — kebanyakan aplikasi tetap jalan; tambah hanya yang terbukti perlu.'
    source: https://man7.org/linux/man-pages/man7/capabilities.7.html

  - id: a14
    tier: A
    type: text
    objective: nonroot
    prompt: Instruksi Dockerfile untuk menetapkan user non-root yang menjalankan container sejak build?
    answers: ['USER']
    patterns: ['^user$', 'user appuser', 'instruksi user']
    tests:
      fail: ['RUN', 'ENTRYPOINT', 'ARG', 'SETUID']
    explanation: '`USER appuser` (setelah membuat user itu dengan `adduser`) membuat container berjalan non-root secara bawaan, tanpa bergantung pada orang ingat menambahkan `--user` saat run. Letakkan `USER` di akhir Dockerfile setelah semua langkah yang butuh root (install paket). Pastikan file yang perlu ditulis aplikasi dimiliki user itu. Image resmi yang baik (mis. `nginx` unprivileged, node) makin sering menyediakan varian non-root.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Docker_Security_Cheat_Sheet.html

  - id: a15
    tier: A
    type: command
    objective: caps
    prompt: Di dalam container, tampilkan capability efektif proses dengan membaca `/proc/self/status`.
    answers: ['grep CapEff /proc/self/status']
    patterns: ['^grep Cap(Eff|...)? /proc/(self|1)/status$', '^grep -i cap /proc/(self|1)/status$', '^cat /proc/(self|1)/status$']
    tests:
      pass: ['grep CapEff /proc/self/status', 'grep CapEff /proc/1/status', 'cat /proc/self/status']
      fail: ['capsh --print', 'grep CapEff /etc/status', 'id', 'getcap /proc/self']
    explanation: 'Di lab, `grep CapEff /proc/self/status` → `a80425fb` (default), `1ffffffffff` (privileged), atau `0` (cap-drop ALL). `CapEff` adalah capability yang benar-benar aktif. Decode dengan `capsh --decode=<hex>` untuk melihat namanya. Ini cara memverifikasi hardening-mu benar-benar berlaku — jangan berasumsi, periksa (seperti `sshd -T` di modul 03). `capsh --print` juga menampilkannya bila `libcap` terpasang.'
    source: https://man7.org/linux/man-pages/man7/capabilities.7.html

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: privileged
    prompt: Kenapa escape dari container Docker "ke host" di laptop Docker Desktop sebenarnya membawamu ke mana, bukan langsung ke Windows?
    answers: ['VM Linux Docker Desktop']
    patterns: ['vm( linux)?', 'wsl2?', 'virtual machine', 'mesin virtual', 'bukan windows']
    tests:
      fail: ['langsung ke Windows', 'ke BIOS', 'ke router', 'ke cloud']
    explanation: 'Di lab, escape membaca hostname `debuerreotype` — itu **VM Linux** Docker Desktop (di WSL2), bukan Windows host. Container berbagi kernel dengan VM itu. Di server production Linux, "host" adalah mesin production sungguhan, jadi escape jauh lebih berbahaya di sana. Pelajarannya tetap sama: perlakukan container escape sebagai penguasaan host; hanya saja "host" di laptopmu adalah lapisan VM, yang memberi sedikit isolasi ekstra kebetulan.'
    source: https://attack.mitre.org/techniques/T1611/

  - id: b02
    tier: B
    type: text
    objective: caps
    prompt: Capability yang hampir setara root karena memungkinkan mount, akses namespace, dan banyak operasi kernel — sering jadi jalur escape — adalah apa?
    answers: ['CAP_SYS_ADMIN']
    patterns: ['cap_?sys_?admin', 'sys_admin', 'sysadmin']
    tests:
      fail: ['CAP_NET_RAW', 'CAP_CHOWN', 'CAP_KILL', 'CAP_SETUID']
    explanation: '`CAP_SYS_ADMIN` begitu luas (mount, pivot_root, operasi namespace, dll) hingga dijuluki "the new root". Menambahkannya (`--cap-add SYS_ADMIN`) sering membuka jalur escape, dan banyak panduan keliru menyarankannya untuk hal yang sebenarnya butuh capability lebih sempit. Aturan: jika sesuatu "perlu SYS_ADMIN", selidiki apa yang sebenarnya dibutuhkan — sering ada capability atau device spesifik yang jauh lebih sempit.'
    source: https://man7.org/linux/man-pages/man7/capabilities.7.html

  - id: b03
    tier: B
    type: text
    objective: nnp
    prompt: Di lab, biner setuid-root default membuat `euid=0`, tetapi shell interaktif seperti `bash -p` sering tetap `euid=1000`. Kenapa shell berbeda?
    answers: ['shell sengaja melepas privilege setuid']
    patterns: ['(shell|bash)[^\n]*(lepas|drop|buang|turunkan)[^\n]*(privilege|setuid|euid)', '(drop|melepas)[^\n]*privilege', 'sengaja[^\n]*(turun|drop|lepas)']
    tests:
      fail: ['Docker memblokir shell', 'setuid tidak pernah bekerja', 'kernel berbeda', 'shell lebih aman otomatis']
    explanation: 'setuid **bekerja** di container (lab: biner C setuid → `euid=0`). Yang terjadi dengan shell adalah bash/dash sengaja **melepaskan** euid istimewa saat start (fitur keamanan shell), kecuali dipaksa `-p` dan kondisi tertentu. Jadi "setuid tidak eskalasi" lewat shell itu perilaku shell, bukan bukti container aman. Tetap pasang `no-new-privileges` — biner setuid non-shell (atau yang ditanam penyerang) tetap bisa eskalasi tanpa itu.'
    source: https://docs.kernel.org/userspace-api/no_new_privs.html

  - id: b04
    tier: B
    type: text
    objective: privileged
    prompt: Alternatif aman untuk pipeline/aplikasi yang "butuh Docker" tanpa memberi akses docker socket penuh adalah apa?
    answers: ['socket proxy berizin terbatas']
    patterns: ['socket proxy', 'docker-socket-proxy', '(proxy)[^\n]*(socket|terbatas|izin)', 'rootless', 'buildkit', 'sysbox', 'kaniko']
    tests:
      fail: ['beri socket ke semua', 'jalankan privileged', 'pakai root', 'matikan firewall']
    explanation: 'Beberapa pendekatan: (1) socket proxy (mis. tecnativa/docker-socket-proxy) yang hanya mengizinkan endpoint API tertentu (read-only, tanpa `create`/`exec`); (2) build tanpa daemon istimewa seperti BuildKit/kaniko/buildah (rootless); (3) runtime terisolasi seperti sysbox untuk Docker-in-Docker yang lebih aman. Mount docker.sock mentah = memberi root host (a02); pilih yang memberi hanya kemampuan minimal yang benar-benar diperlukan.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Docker_Security_Cheat_Sheet.html

  - id: b05
    tier: B
    type: text
    objective: nnp
    prompt: Mekanisme Mandatory Access Control (profil per-program, mis. membatasi path & kemampuan) yang dipakai Docker di Debian/Ubuntu disebut apa?
    answers: ['AppArmor']
    patterns: ['apparmor', 'app armor']
    tests:
      fail: ['seccomp', 'cgroups', 'iptables', 'namespaces']
    explanation: 'AppArmor (dan SELinux di RHEL/Fedora) adalah Mandatory Access Control: profil yang membatasi apa yang boleh diakses sebuah program (path, capability, jaringan) terlepas dari UID. Docker memasang profil AppArmor default (`docker-default`). Bersama seccomp (filter syscall) dan capabilities, mereka berlapis: seccomp membatasi *syscall mana*, AppArmor/SELinux membatasi *resource mana*, capabilities membatasi *operasi istimewa mana*. Jangan jalankan `--security-opt apparmor=unconfined` tanpa alasan kuat.'
    source: https://docs.docker.com/engine/security/apparmor/

  - id: b06
    tier: B
    type: text
    objective: limits
    prompt: Selain `--pids-limit`, flag untuk membatasi RAM sebuah container (agar tidak menghabiskan memori host) adalah apa?
    answers: ['--memory']
    patterns: ['--memory', '-m\b', 'mem_limit', '--memory=']
    tests:
      fail: ['--cpus', '--pids-limit', '--ulimit nofile', '--swap']
    explanation: '`--memory 512m` (atau `-m`) membatasi RAM; bila terlampaui, container di-OOM-kill (bukan menyeret host). Lengkap: `--cpus` (kuota CPU), `--pids-limit` (proses), `--ulimit nofile` (file descriptor). Tanpa limit, satu container bocor memori / fork bomb / sibuk-CPU bisa melumpuhkan semua tetangga di host — serangan (atau bug) Availability. Resource limit = isolasi "blast radius" antar-container. Di Compose/K8s, set requests & limits.'
    source: https://docs.docker.com/engine/security/

  - id: b07
    tier: B
    type: text
    objective: filesystem
    prompt: Keuntungan keamanan utama dari root filesystem **read-only** terhadap penyerang yang sudah masuk container adalah apa?
    answers: ['tidak bisa menulis webshell/binary ke container']
    patterns: ['(tidak|tak|ga)[^\n]*(bisa|dapat)[^\n]*(tulis|menulis|menanam|drop)[^\n]*(webshell|binary|payload|tool|malware|file)', '(tidak|tak)[^\n]*(ubah|modif)[^\n]*(filesystem|container)', 'immutable', 'tidak bisa menulis']
    tests:
      fail: ['container jadi lebih cepat', 'hemat disk', 'otomatis non-root', 'mencegah OOM']
    explanation: 'Dengan rootfs read-only, penyerang yang mengeksekusi kode tetap tidak bisa menanam webshell, mengunduh tool, menulis cron, atau memodifikasi binary aplikasi — langkah lanjutan mereka (Persistence, Execution) jadi jauh lebih sulit. Container mendekati immutable: apa yang jalan = apa yang kamu build. Beri jalur tulis sempit via `tmpfs` (sementara) atau volume (data), bukan membuka seluruh `/`. Gabung dengan non-root + cap-drop untuk efek berlapis.'
    source: https://docs.docker.com/engine/security/

  - id: b08
    tier: B
    type: text
    objective: nnp
    prompt: 'Benar/salah: menjalankan container sebagai non-root sudah cukup; capability, seccomp, dan read-only tidak perlu lagi.'
    answers: ['salah']
    patterns: ['^salah$', '^false$', '^tidak( benar)?$']
    tests:
      fail: ['benar', 'true', 'ya']
    explanation: 'Salah. Non-root mengurangi risiko tapi tidak cukup sendiri: capability default masih memberi `setuid`/`net_raw`; tanpa `no-new-privileges` binary setuid bisa eskalasi (lab: `euid=0`); tanpa read-only penyerang menanam tool; tanpa seccomp syscall kernel berbahaya terbuka. Hardening berlapis (modul 01): non-root + cap-drop ALL + no-new-privileges + read-only + seccomp + limits bekerja bersama. Masing-masing menutup jalur berbeda.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Docker_Security_Cheat_Sheet.html

  - id: b09
    tier: B
    type: text
    objective: privileged
    prompt: Selain docker socket, mount berbahaya lain yang sering jadi jalur escape adalah mem-mount direktori host apa ke container?
    answers: ['/ (root filesystem host) atau /proc, /var/run']
    patterns: ['(root|/)[^\n]*(filesystem|host)|mount /\b|/proc|/var/run|/dev|host (path|root|/)', 'seluruh host', 'root host']
    tests:
      fail: ['/tmp container', '/app', '/usr/share', 'volume bernama kosong']
    explanation: 'Mount host yang berbahaya: `-v /:/host` (akses seluruh host), `/proc` & `/sys` (manipulasi kernel/host), `/var/run/docker.sock` (a02), `/dev` (akses device & disk mentah → baca/tulis filesystem host langsung). Semuanya memberi jalan ke host. Aturan: mount sesempit mungkin, read-only (`:ro`) bila bisa, dan tidak pernah mount path sistem host ke container yang tidak tepercaya. Audit `volumes:` di Compose dengan kecurigaan.'
    source: https://attack.mitre.org/techniques/T1611/

  - id: b10
    tier: B
    type: command
    objective: caps
    prompt: Decode nilai capability hex `a80425fb` menjadi nama-nama capability dengan capsh.
    answers: ['capsh --decode=a80425fb']
    patterns: ['^capsh --decode=0?x?a80425fb$']
    tests:
      pass: ['capsh --decode=a80425fb', 'capsh --decode=0xa80425fb']
      fail: ['capsh --print a80425fb', 'capsh a80425fb', 'getcap a80425fb', 'capsh --decode a80425fb']
    explanation: 'Di lab, `capsh --decode=a80425fb` → `cap_chown,cap_dac_override,...,cap_setuid,cap_net_raw,...` (14 capability default). `--decode` menerjemahkan bitmask hex dari `CapEff` (a15) menjadi nama yang bisa dibaca, berguna saat mengaudit container yang sedang berjalan. Perhatikan sintaksnya pakai `=` (`--decode=HEX`). `capsh --print` menampilkan capability proses capsh sendiri, berbeda tujuan.'
    source: https://man7.org/linux/man-pages/man7/capabilities.7.html

  - id: b11
    tier: B
    type: text
    objective: nonroot
    prompt: Teknologi Docker yang menjalankan **daemon dan container sebagai user non-root di host** (sehingga root container ≠ root host) disebut apa?
    answers: ['rootless']
    patterns: ['rootless( mode| docker)?', 'docker rootless', 'user namespace']
    tests:
      fail: ['privileged mode', 'root squash', 'sudo docker', 'docker swarm']
    explanation: 'Rootless Docker (dan user-namespace remapping) memetakan root di dalam container ke user biasa di host, sehingga kompromi container + escape hanya memberi penyerang user tak-istimewa di host, bukan root. Ini mitigasi kuat terhadap seluruh kelas container escape. Trade-off: beberapa fitur terbatas (port < 1024, beberapa storage driver). Untuk banyak beban kerja, rootless + hardening lain adalah default yang jauh lebih aman.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Docker_Security_Cheat_Sheet.html

  - id: b12
    tier: B
    type: text
    objective: limits
    prompt: Mekanisme kernel Linux yang membatasi & mengisolasi penggunaan resource (CPU, memori, PID) antar-grup proses, dasar dari `--memory`/`--pids-limit`, disebut apa?
    answers: ['cgroups']
    patterns: ['cgroups?', 'control groups?']
    tests:
      fail: ['namespaces', 'seccomp', 'capabilities', 'apparmor']
    explanation: 'cgroups (control groups) membatasi & menghitung resource per grup proses — fondasi `--memory`, `--cpus`, `--pids-limit`. Berbeda peran dengan **namespaces** (yang mengisolasi *apa yang dilihat* container: PID, network, mount, user). Keduanya adalah inti container: namespaces untuk isolasi pandangan, cgroups untuk batas resource. Membatasi resource bukan hanya soal stabilitas — ia mencegah satu container dipakai untuk DoS seluruh host (modul 08).'
    source: https://docs.docker.com/engine/security/

  - id: b13
    tier: B
    type: text
    objective: nnp
    prompt: Mematikan profil seccomp default dengan `--security-opt seccomp=unconfined` berdampak buruk apa?
    answers: ['membuka semua syscall berbahaya ke container']
    patterns: ['(buka|membuka|izinkan|expose)[^\n]*(syscall|system call)', '(syscall|system call)[^\n]*(berbahaya|terbuka|penuh)', 'permukaan (eksploit|serang) kernel (naik|membesar|lebih besar)', 'hilang[^\n]*proteksi']
    tests:
      fail: ['container lebih lambat', 'container tidak bisa jalan', 'otomatis privileged', 'kehilangan jaringan']
    explanation: '`seccomp=unconfined` menghapus filter sehingga container bisa memanggil **semua** syscall, termasuk ~44 yang diblokir default (mount, reboot, kexec_load, syscall debugging keyring, dll). Ini memperbesar permukaan eksploit kernel — banyak CVE container escape butuh syscall yang profil default sudah blokir. Beberapa alat (debugger, gdb) meminta ini; bila terpaksa, batasi ke container itu saja, jangan jadikan default, dan dampingi dengan hardening lain.'
    source: https://docs.docker.com/engine/security/seccomp/

  - id: b14
    tier: B
    type: text
    objective: filesystem
    prompt: 'Di Docker Compose, kunci untuk root read-only adalah `read_only: true`; kunci untuk membuang semua capability adalah apa?'
    answers: ['cap_drop']
    patterns: ['cap_drop', 'cap_drop:\s*\[?all', 'cap-drop']
    tests:
      fail: ['privileged: true', 'cap_add', 'security_opt saja', 'user: root']
    explanation: 'Di Compose: `read_only: true`, `cap_drop: [ALL]` (+ `cap_add` seperlunya), `security_opt: ["no-new-privileges:true"]`, `user: "1000:1000"`, `pids_limit: 100`, `mem_limit`/`tmpfs`. Menuliskan hardening di Compose/manifest membuatnya konsisten & ter-review (tidak bergantung orang ingat flag `docker run`). Ini juga yang dipindai oleh scanner misconfig (mis. `trivy config`, modul 11) — hardening sebagai kode.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Docker_Security_Cheat_Sheet.html

  - id: b15
    tier: B
    type: text
    objective: privileged
    prompt: Dalam ATT&CK, teknik penyerang keluar dari container ke sistem host (via privileged, mount, atau socket) adalah T1611 dengan nama apa?
    answers: ['Escape to Host']
    patterns: ['escape to host', 'escape[- ]?to[- ]?host', 't1611']
    tests:
      fail: ['Exploitation for Privilege Escalation', 'Container Administration Command', 'Deploy Container', 'Lateral Movement']
    explanation: 'Escape to Host (T1611) adalah tujuan penyerang di lingkungan container: dari satu container, menguasai host dan dari sana semua container lain. Jalur umumnya persis yang kita tutup di modul ini: `--privileged`, mount docker.sock / host paths, capability berlebih (SYS_ADMIN), dan eksploit kernel via syscall yang tak di-seccomp. Pertahanan berlapis (non-root, cap-drop, read-only, seccomp, no-new-privileges, rootless) membuat T1611 jauh lebih sulit.'
    source: https://attack.mitre.org/techniques/T1611/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: caps
    prompt: |
      Sebuah container aplikasi web (Node/PHP) di production dijalankan dengan `docker run -d myapp` polos — root,
      semua default. Rancang konfigurasi runtime yang di-hardening untuk container ini (satu poin per baris).
    checklist:
      - id: nonroot
        label: Jalankan non-root (USER di image + --user / user key di compose)
        required: true
        patterns: ['non-?root', '--user [1-9]', 'user:\s*["]?[1-9]', 'USER [a-z]']
      - id: capdrop
        label: --cap-drop ALL, tambah hanya yang perlu (mis. NET_BIND_SERVICE)
        required: true
        patterns: ['cap[-_]drop[^\n]*all', 'drop[^\n]*(semua )?cap', '--cap-drop ALL']
      - id: nnp
        label: no-new-privileges
        required: true
        patterns: ['no-new-privileges', 'no_new_privs', 'security_opt[^\n]*no-new']
      - id: readonly
        label: read-only rootfs + tmpfs/volume untuk jalur tulis
        patterns: ['read[-_]only', '--read-only', 'tmpfs']
      - id: limits
        label: Resource limit (--pids-limit, --memory, --cpus)
        patterns: ['--pids-limit|pids_limit', '--memory|mem_limit|-m ', '--cpus']
      - id: nosocket
        label: Tidak mount docker.sock / host path; mount sempit & read-only
        patterns: ['(tidak|jangan|tanpa)[^\n]*(docker\.sock|socket|host path|mount /)', 'mount[^\n]*(sempit|:ro|read-?only)', ':ro']
      - id: no-privileged
        label: Tidak memakai --privileged
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tanpa|hindari|tidak))[^\n]*(--privileged|privileged: true|privileged true)']
    pass_score: 0.5
    solution: |
      ```text
      docker run -d \
        --user 1000:1000 \                      # non-root (USER juga di-set di Dockerfile)
        --cap-drop ALL \                         # buang semua capability...
        --cap-add NET_BIND_SERVICE \             # ...tambah hanya jika perlu bind port < 1024
        --security-opt=no-new-privileges \       # blokir eskalasi setuid (lab: euid tetap 1000)
        --read-only --tmpfs /tmp \               # rootfs immutable + scratch sementara
        --pids-limit 200 --memory 512m --cpus 1 \# batas resource (anti fork bomb/OOM/DoS)
        myapp
      # TIDAK ada -v /var/run/docker.sock, tidak ada --privileged, mount data pakai volume :ro bila cukup
      ```
      Compose setara: `user`, `cap_drop: [ALL]`, `cap_add`, `security_opt: [no-new-privileges:true]`, `read_only:
      true`, `tmpfs: [/tmp]`, `pids_limit`, `mem_limit`. Berlapis: tiap kontrol menutup jalur berbeda — non-root +
      cap-drop + no-new-privileges mematikan eskalasi (lab membuktikan setuid gagal), read-only mematikan Persistence,
      limit mematikan DoS internal, dan tidak ada socket/privileged mematikan jalur escape termudah (T1611).
    tests:
      pass:
        - |
          jalankan non-root dengan USER di image dan --user 1000:1000
          --cap-drop ALL lalu --cap-add NET_BIND_SERVICE hanya kalau perlu bind port rendah
          --security-opt=no-new-privileges untuk blokir eskalasi setuid
          --read-only rootfs dengan --tmpfs /tmp untuk jalur tulis
          batas resource --pids-limit 200 --memory 512m --cpus 1
          tidak mount docker.sock atau host path, mount data read-only :ro
          jangan pakai --privileged
        - |
          user non-root, cap_drop ALL tambah seperlunya
          no-new-privileges, read_only true plus tmpfs
          pids_limit dan mem_limit, tanpa docker socket dan tanpa privileged
      fail:
        - |
          jalankan --privileged biar aplikasi tidak error permission
          mount docker.sock supaya bisa deploy sendiri
        - |
          biarkan root saja yang penting cepat
          tidak perlu cap-drop atau read-only
        - |
          tambahkan --cap-add ALL
          mount / host ke container
    source: https://cheatsheetseries.owasp.org/cheatsheets/Docker_Security_Cheat_Sheet.html

  - id: c02
    tier: C
    type: scenario
    objective: privileged
    prompt: |
      Saat review, kamu menemukan container CI runner dijalankan dengan `-v /var/run/docker.sock:/var/run/docker.sock`
      dan `--privileged` agar bisa membangun image. Jelaskan risikonya dan rancang alternatif yang aman (satu poin per
      baris).
    checklist:
      - id: risk
        label: Risiko — socket/privileged = root di host (container escape T1611)
        required: true
        patterns: ['(socket|privileged|docker\.sock)[^\n]*(root|host|escape|kuasa|kendali)', 'root (di|ke) host', 'container escape', 't1611']
      - id: blast
        label: Dampak — job CI jahat/ter-kompromi bisa menguasai semua container di host
        patterns: ['(job|ci|pr|build)[^\n]*(jahat|kompromi|malicious|tak tepercaya)', '(semua|seluruh)[^\n]*container', 'blast radius|host (jatuh|dikuasai)']
      - id: rootless-build
        label: Pakai builder rootless tanpa daemon (BuildKit/kaniko/buildah)
        required: true
        patterns: ['buildkit|kaniko|buildah|img\b', 'rootless', 'tanpa daemon', 'daemonless']
      - id: proxy
        label: Jika butuh API docker, pakai socket proxy berizin terbatas (read-only)
        patterns: ['socket proxy', 'docker-socket-proxy', 'proxy[^\n]*(terbatas|read-only|izin)', 'batasi[^\n]*api']
      - id: isolate
        label: Isolasi runner (ephemeral, non-privileged, hak minimal, jaringan terbatas)
        patterns: ['ephemeral|sekali pakai', 'non-?privileged', 'runner[^\n]*(terisolasi|hak minimal|terbatas)', 'least privilege']
      - id: no-keep
        label: Tidak mempertahankan socket+privileged "karena praktis"
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|hindari|ganti))[^\n]*(biarkan|tetap|pertahankan)[^\n]*(socket|privileged)[^\n]*(praktis|mudah|cepat|saja)']
    pass_score: 0.5
    solution: |
      ```text
      Risiko:
        - Mount docker.sock = akses penuh daemon host = bisa jalankan container privileged yang mount / → ROOT di host
          (lab: baca /etc/shadow host). --privileged menambah semua capability + device. Ini container escape (T1611).
        - CI runner menjalankan kode dari banyak PR/commit; satu job jahat atau dependency ter-kompromi (modul 05) =
          penguasaan host + semua container (blast radius maksimum).
      Alternatif aman:
        - Bangun image TANPA daemon istimewa: BuildKit (rootless), kaniko, atau buildah/img — tidak perlu socket.
        - Jika benar-benar perlu API docker: socket proxy (tecnativa/docker-socket-proxy) yang hanya izinkan endpoint
          tertentu, read-only, tanpa create/exec privileged.
        - Runner ephemeral & non-privileged, token ber-scope sempit, jaringan terbatas; hapus setelah tiap job.
      ```
      Prinsipnya: "butuh membangun image" TIDAK berarti "butuh root host". Builder rootless memenuhi kebutuhan nyata
      (membuat image) tanpa kemampuan berbahaya (kendali daemon). Mempertahankan socket+privileged "karena praktis"
      adalah menukar keamanan paling kritis demi kenyamanan — persis target yang dicari penyerang supply chain.
    tests:
      pass:
        - |
          risiko mount docker.sock dan privileged sama dengan root di host yaitu container escape t1611
          ci runner menjalankan kode dari banyak pr, satu job jahat bisa menguasai semua container di host
          pakai builder rootless tanpa daemon seperti buildkit kaniko atau buildah
          kalau butuh api docker pakai socket proxy yang izinnya terbatas dan read-only
          runner ephemeral non-privileged dengan hak minimal dan jaringan terbatas
          jangan pertahankan socket dan privileged hanya karena praktis
        - |
          socket dan privileged memberi root host, escape t1611, blast radius seluruh host
          ganti ke kaniko atau buildkit rootless, atau socket proxy read-only
          runner sekali pakai non-privileged hak minimal
      fail:
        - |
          biarkan socket dan privileged karena paling praktis untuk build
          yang penting pipeline jalan
        - |
          tambahkan password di runner saja
          socket tetap di-mount
        - |
          pakai --privileged tapi runner-nya dipercaya kok
    source: https://attack.mitre.org/techniques/T1611/

  - id: c03
    tier: C
    type: scenario
    objective: limits
    prompt: |
      Di satu host, beberapa container berbagi resource. Satu container (bug atau diserang) tiba-tiba fork bomb +
      memakan semua RAM, dan container lain ikut mati. Rancang pencegahan isolasi resource & dampaknya (satu poin per
      baris).
    checklist:
      - id: pids
        label: --pids-limit untuk tiap container (stop fork bomb)
        required: true
        patterns: ['--pids-limit|pids_limit', '(batas|limit)[^\n]*(pid|proses)', 'fork bomb']
      - id: mem
        label: --memory limit (OOM-kill container nakal, bukan host)
        required: true
        patterns: ['--memory|mem_limit|-m ', '(batas|limit)[^\n]*(ram|memori|memory)', 'oom']
      - id: cpu
        label: --cpus / cpu quota agar tidak monopoli CPU
        patterns: ['--cpus|cpu_?quota|cpu limit', '(batas|limit)[^\n]*cpu']
      - id: cgroups
        label: Pahami ini ditegakkan cgroups (isolasi resource antar-container)
        patterns: ['cgroups?', 'control groups?']
      - id: default-all
        label: Terapkan limit sebagai default ke SEMUA container (bukan satu saja)
        patterns: ['(semua|setiap|tiap)[^\n]*container', 'default[^\n]*(limit|semua)', 'konsisten']
      - id: monitor
        label: Monitoring + alert saat container mendekati limit (deteksi dini)
        patterns: ['(monitor|alert|pantau)[^\n]*(resource|limit|ram|cpu|pid)', 'metrics|cadvisor|prometheus']
      - id: not-unlimited
        label: Tidak membiarkan container tanpa limit "agar tidak ribet"
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|hindari))[^\n]*(tanpa|tidak pakai)[^\n]*(limit|batas)[^\n]*(ribet|mudah|praktis|saja)']
    pass_score: 0.5
    solution: |
      ```text
      1. --pids-limit per container → fork bomb mentok di cap-nya (lab: pids-limit=50 → "can't fork") tanpa menyeret
         host atau tetangga.
      2. --memory (mis. 512m) → container yang bocor di-OOM-kill sendiri, bukan menghabiskan RAM host.
      3. --cpus / kuota CPU → container sibuk tidak memonopoli core; tetangga tetap responsif.
      4. Semua ini ditegakkan cgroups (isolasi resource) — pasangan dari namespaces (isolasi pandangan).
      5. Terapkan sebagai DEFAULT untuk SEMUA container (template run / Compose / limits K8s), bukan menunggu insiden.
      6. Monitoring + alert (cAdvisor/Prometheus) saat container mendekati limit → deteksi dini bug/serangan sebelum
         memukul limit keras.
      ```
      Inti: tanpa limit, kegagalan/serangan di satu container adalah masalah seluruh host — "blast radius" tak
      terbatas. Resource limit mengubahnya jadi masalah lokal satu container (yang di-OOM-kill / dibatasi), menjaga
      tetangga tetap hidup. Ini Availability (modul 08) di level host, dan alasan K8s mewajibkan requests/limits.
      Membiarkan container tanpa limit "agar tidak ribet" berarti mengundang satu bug melumpuhkan semuanya.
    tests:
      pass:
        - |
          pasang --pids-limit tiap container supaya fork bomb mentok di capnya
          --memory limit agar container nakal di-oom-kill sendiri bukan host
          --cpus untuk mencegah monopoli cpu
          pahami ini ditegakkan cgroups isolasi resource antar container
          terapkan limit sebagai default untuk semua container bukan satu saja
          monitoring dan alert saat container mendekati limit untuk deteksi dini
          jangan biarkan container tanpa limit
        - |
          batasi pid dan memory tiap container, cpu quota juga
          cgroups yang menegakkan, set default semua container di compose
          pasang monitoring prometheus untuk alert mendekati limit
      fail:
        - |
          biarkan container tanpa limit agar tidak ribet
          kalau ada yang mati tinggal restart
        - |
          tambah ram host saja
          tidak perlu pids-limit atau memory limit
        - |
          kasih limit di satu container yang bermasalah saja
          yang lain tidak usah
    source: https://docs.docker.com/engine/security/
---

# Hardening Container & Runtime

Image yang bersih (modul 05) belum cukup: saat berjalan, container masih bisa diberi terlalu banyak kuasa. Jika
aplikasi di dalamnya dikompromikan, kuasa itulah yang dipakai penyerang untuk **escape ke host** (ATT&CK T1611).
Modul ini membatasi apa yang boleh dilakukan container agar satu kompromi tetap terkurung.

> [!NOTE]
> Lab: Docker Desktop (Engine 29.4.1). "Host" di sini adalah VM Linux Docker Desktop (WSL2, hostname
> `debuerreotype`) — di server production, "host" adalah mesin sungguhan. Semua di lab lokal milik sendiri.

## 1. Jalur escape (hasil lab)

```text
# docker.sock ter-mount → kendali daemon host → jalankan container yang mount host /
docker run -v /:/host alpine cat /host/etc/shadow   →  root:*:...   (BACA SHADOW HOST)

# capability sets (CapEff dari /proc/self/status, decode capsh):
default        a80425fb   = 14 cap (chown, setuid, net_raw, ...)
--privileged   1ffffffffff = ~40 cap (sys_admin, sys_module, ...)  ← praktis root host
--cap-drop ALL 0           = tidak ada capability
```

Jalur escape utama: **docker.sock**, **--privileged**, **mount host** (`/`, `/proc`, `/dev`), dan **capability
berlebih** (terutama `SYS_ADMIN`).

## 2. Kontrol hardening (hasil lab)

| Kontrol | Flag | Bukti lab |
|---|---|---|
| Non-root | `--user 1000:1000` / `USER` | default `uid=0`, jadi `uid=1000` |
| Drop capability | `--cap-drop ALL [--cap-add ...]` | `CapEff 0` |
| Cegah eskalasi | `--security-opt=no-new-privileges` | setuid: `euid=0` → `euid=1000` (FAILED) |
| Root read-only | `--read-only --tmpfs /tmp` | `touch /oops` → `Read-only file system` |
| Batas proses | `--pids-limit 100` | 200 fork → `can't fork` (cap 50) |
| Batas RAM/CPU | `--memory`, `--cpus` | OOM-kill container, bukan host |
| Filter syscall | profil seccomp default | ~44 syscall berbahaya diblokir |
| MAC | AppArmor/SELinux (`docker-default`) | batasi resource per-program |

> [!WARNING]
> Kepemilikan file lintas container/host bersifat **numerik**: UID 0 di container = UID 0 di host (lab: file volume
> dimiliki `0`). Container root + bind mount host = file root-owned di host. Alasan lain untuk non-root.

## 3. setuid & no-new-privileges (hasil lab)

```text
tanpa hardening:                 ruid=1000 euid=0   → setuid(0) OK  (ESCALATED)
--security-opt=no-new-privileges: ruid=1000 euid=1000 → setuid(0) FAILED
```

Catatan: shell seperti `bash -p` sering tetap `euid=1000` karena **shell sengaja melepas** euid istimewa — itu
perilaku shell, bukan bukti container aman. Biner setuid non-shell (atau yang ditanam penyerang) tetap eskalasi tanpa
`no-new-privileges`.

## 4. Berlapis, bukan satu kontrol

Non-root saja tidak cukup. Gabungkan:

```bash
docker run -d \
  --user 1000:1000 --cap-drop ALL --cap-add NET_BIND_SERVICE \
  --security-opt=no-new-privileges --read-only --tmpfs /tmp \
  --pids-limit 200 --memory 512m --cpus 1 \
  myapp              # TANPA -v docker.sock, TANPA --privileged
```

Compose: `user`, `cap_drop: [ALL]`, `cap_add`, `security_opt: [no-new-privileges:true]`, `read_only: true`,
`tmpfs: [/tmp]`, `pids_limit`, `mem_limit`. Tulis hardening sebagai **kode** agar konsisten & ter-scan (modul 11).

## 5. Fondasi: namespaces & cgroups

- **namespaces** → isolasi *pandangan* (PID, network, mount, user). Rootless/user-ns memetakan root container ke user
  biasa host.
- **cgroups** → batas *resource* (CPU, RAM, PID). Dasar `--memory`/`--pids-limit`; mencegah satu container
  mem-DoS host.

## 6. Ringkasan: tutup T1611

| Jalur escape | Pertahanan |
|---|---|
| docker.sock | jangan mount; socket proxy / builder rootless |
| --privileged | jangan; beri cap/device spesifik |
| mount host (`/`, `/proc`, `/dev`) | mount sempit, `:ro`, hindari path sistem |
| capability berlebih | `--cap-drop ALL` + add seperlunya |
| eskalasi setuid | `no-new-privileges` + non-root |
| eksploit kernel via syscall | seccomp default (jangan `unconfined`) |
| DoS resource | `--pids-limit`, `--memory`, `--cpus` |

> [!NOTE]
> Lanjut ke modul 07 (Keamanan web aplikasi) — menutup kelas kerentanan di lapisan aplikasi (OWASP), tempat
> penyerang paling sering mendapat pijakan pertama sebelum mencoba escape.
