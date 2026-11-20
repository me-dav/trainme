---
format: 1
id: linux-bash-scripting
course:
  slug: linux
  title: Linux
  description: Fondasi Linux untuk DevOps/DevSecOps — dari filesystem sampai hardening.
title: Bash Scripting yang Aman
summary: Menulis skrip otomasi yang gagal dengan jelas, bukan diam-diam — quoting, set -euo pipefail dan jebakannya, kondisi, loop, fungsi, trap, cron, dan shellcheck.
order: 9
version: 1
status: verified
updated: '2026-11-20'
tested_on:
  - Ubuntu 24.04.5 LTS (container Docker) — GNU bash 5.2.21, dash 0.5.12 (/bin/sh), ShellCheck 0.9.0, cron 3.0pl1
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: basics
    text: Menyiapkan skrip (shebang, bash -n/-x, shellcheck)
  - id: quoting
    text: Memakai variabel, quoting, dan parameter expansion dengan benar
  - id: strict
    text: Memahami set -euo pipefail beserta celah-celahnya
  - id: tests
    text: Menulis kondisi dengan [ ], [[ ]], dan (( )) tanpa jebakan
  - id: loops
    text: Melakukan loop atas file dan baris tanpa memecahkan nama berspasi
  - id: functions
    text: Membuat fungsi, return value, local, dan argumen
  - id: robust
    text: Membuat skrip tahan gagal (trap, mktemp, cd || exit, redirect)
  - id: portability
    text: Membedakan bash vs sh (dash) dan lingkungan cron
sources:
  - title: GNU Bash Reference Manual
    url: https://www.gnu.org/software/bash/manual/bash.html
  - title: Bash Manual — The Set Builtin
    url: https://www.gnu.org/software/bash/manual/html_node/The-Set-Builtin.html
  - title: Bash Manual — Shell Parameter Expansion
    url: https://www.gnu.org/software/bash/manual/html_node/Shell-Parameter-Expansion.html
  - title: Bash Manual — Conditional Constructs ([[ ]], (( )))
    url: https://www.gnu.org/software/bash/manual/html_node/Conditional-Constructs.html
  - title: Bash Manual — Shell Arithmetic
    url: https://www.gnu.org/software/bash/manual/html_node/Shell-Arithmetic.html
  - title: BashFAQ/105 — Why doesn't set -e do what I expected?
    url: https://mywiki.wooledge.org/BashFAQ/105
  - title: BashPitfalls (Greg's Wiki)
    url: https://mywiki.wooledge.org/BashPitfalls
  - title: ShellCheck wiki (SC2086, SC2164, SC2045)
    url: https://www.shellcheck.net/wiki/
  - title: Ubuntu Wiki — DashAsBinSh
    url: https://wiki.ubuntu.com/DashAsBinSh
  - title: crontab(5)
    url: https://man7.org/linux/man-pages/man5/crontab.5.html
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: basics
    prompt: Tulis baris shebang yang menjalankan skrip dengan **bash** yang ditemukan lewat `PATH` (portable antar sistem).
    answers: ['#!/usr/bin/env bash', '#!/bin/bash']
    tests:
      fail: ['#!/bin/sh', '#/bin/bash', '!#/bin/bash', '#!bash']
    explanation: '`#!/usr/bin/env bash` mencari bash di PATH (berguna di macOS/NixOS, di mana bash tidak selalu di `/bin`). `#!/bin/bash` juga lazim di Linux. `#!/bin/sh` di Ubuntu berarti **dash**, bukan bash. Fitur bash seperti `[[`, array, dan `pipefail` tidak tersedia di sana.'
    source: https://wiki.ubuntu.com/DashAsBinSh

  - id: a02
    tier: A
    type: command
    objective: basics
    prompt: Periksa **syntax** `deploy.sh` tanpa menjalankan perintah apa pun di dalamnya.
    answers: ['bash -n deploy.sh']
    patterns: ['^bash (-n|-o noexec) (\./)?deploy\.sh$']
    tests:
      fail: ['bash -x deploy.sh', 'sh deploy.sh', 'bash deploy.sh -n']
    explanation: 'Di lab, `if` tanpa `fi` → `syntax error: unexpected end of file` (exit 2). `-n` hanya mengecek syntax. Bug logika (quoting, `cd` gagal) baru tertangkap `shellcheck`.'
    source: https://www.gnu.org/software/bash/manual/html_node/The-Set-Builtin.html

  - id: a03
    tier: A
    type: command
    objective: basics
    prompt: Jalankan `deploy.sh` sambil **mencetak setiap perintah** (setelah ekspansi) sebelum dieksekusi, untuk debugging.
    answers: ['bash -x deploy.sh']
    patterns: ['^bash (-x|-xv|-vx|-o xtrace) (\./)?deploy\.sh$']
    tests:
      pass: ['bash -o xtrace deploy.sh']
      fail: ['bash -n deploy.sh', 'bash -v deploy.sh', 'set -x deploy.sh']
    explanation: 'Di lab: `+ v=1` lalu `+ echo 1`. Di dalam skrip, `set -x` … `set +x` membatasi tracing ke satu bagian. Hati-hati: `-x` ikut mencetak secret yang ada di variabel ke log CI.'
    source: https://www.gnu.org/software/bash/manual/html_node/The-Set-Builtin.html

  - id: a04
    tier: A
    type: command
    objective: quoting
    prompt: Simpan **output** `date +%F` ke variabel `now`.
    answers: ['now=$(date +%F)']
    patterns: ['^now=\$\(date \+%F\)$', '^now=`date \+%F`$']
    tests:
      pass: ['now=`date +%F`']
      fail: ['now=date +%F', 'now = $(date +%F)', 'now=$date']
    explanation: 'Tidak boleh ada spasi di sekitar `=`: `now = ...` menjalankan perintah bernama `now`. `$( )` lebih disukai daripada backtick karena bisa bersarang dan mudah dibaca. Di lab: `2026-11-20`.'
    source: https://www.gnu.org/software/bash/manual/html_node/Command-Substitution.html

  - id: a05
    tier: A
    type: text
    objective: strict
    prompt: Opsi `set` apa yang membuat skrip **berhenti dengan error** saat memakai variabel yang belum di-set?
    answers: ['-u', 'set -u', 'nounset', 'set -o nounset']
    tests:
      fail: ['-e', '-x', '-o pipefail', '-n']
    explanation: 'Di lab: `NOPE: unbound variable`. Ini menangkap typo nama variabel dan argumen yang lupa diberikan, yang seharusnya tidak diam-diam menjadi string kosong. Untuk variabel yang memang opsional, pakai `${VAR:-}` atau `${VAR:-default}`.'
    source: https://www.gnu.org/software/bash/manual/html_node/The-Set-Builtin.html

  - id: a06
    tier: A
    type: command
    objective: strict
    prompt: Tulis baris `set` yang umum dipakai di awal skrip bash untuk mengaktifkan **errexit**, **nounset**, dan **pipefail** sekaligus.
    answers: ['set -euo pipefail']
    patterns: ['^set (-E?euo pipefail|-E?ueo pipefail|-E?eu -o pipefail|-e -u -o pipefail|-o errexit -o nounset -o pipefail)$']
    tests:
      pass: ['set -eu -o pipefail', 'set -Eeuo pipefail', 'set -o errexit -o nounset -o pipefail']
      fail: ['set -e', 'set -euo', 'set -x', 'set -eu']
    explanation: 'Ini jaring pengaman, bukan jaminan. Bagian tier B membahas di mana `set -e` **tidak** berlaku. `-E` membuat trap ERR ikut berlaku di dalam fungsi.'
    source: https://www.gnu.org/software/bash/manual/html_node/The-Set-Builtin.html

  - id: a07
    tier: A
    type: text
    objective: functions
    prompt: Ekspansi apa yang meneruskan **semua argumen** skrip/fungsi apa adanya, sehingga argumen berspasi tetap utuh?
    answers: ['"$@"', '$@']
    tests:
      fail: ['$*', '"$*"', '$#', '$1']
    explanation: 'Di lab, `args.sh "one two" three`: `"$@"` → `[one two] [three]`, `$*` → `[one] [two] [three]`, `"$*"` → `[one two three]`. Hampir selalu yang kamu mau adalah `"$@"` (dengan kutip), mis. `exec "$@"` di entrypoint Docker.'
    source: https://www.gnu.org/software/bash/manual/html_node/Special-Parameters.html

  - id: a08
    tier: A
    type: command
    objective: loops
    prompt: Loop setiap file `*.log` di direktori saat ini dan cetak namanya — **tanpa** mem-parsing output `ls`.
    answers: ['for f in *.log; do echo "$f"; done']
    patterns: ['^for (\w+) in \*\.log; do (echo|printf %s\\n) \$\{?\1\}?; done$']
    tests:
      pass: ['for file in *.log; do printf ''%s\n'' "$file"; done']
      fail: ['for f in $(ls *.log); do echo "$f"; done', 'for f in `ls *.log`; do echo $f; done', 'ls *.log | echo']
    explanation: 'Di lab, dengan file `a b.log`: `for f in $(ls)` → `[a]` `[b.log]` (nama terpecah), `for f in *.log` → `[a b.log]` utuh. Bila tidak ada file yang cocok, glob tetap literal (`[*.txt]`). Pakai `shopt -s nullglob` agar loop dilewati.'
    source: https://mywiki.wooledge.org/BashPitfalls

  - id: a09
    tier: A
    type: command
    objective: loops
    prompt: Baca `hosts.txt` **baris per baris**, tanpa memangkas spasi atau membuang backslash, lalu cetak setiap baris.
    answers: ['while IFS= read -r line; do echo "$line"; done < hosts.txt']
    patterns: ['^while IFS= read -r (\w+); do (echo|printf %s\\n) \$\{?\1\}?; done <hosts\.txt$']
    tests:
      pass: ['while IFS= read -r h; do printf ''%s\n'' "$h"; done < hosts.txt']
      fail: ['for line in $(cat hosts.txt); do echo "$line"; done', 'cat hosts.txt | while read line; do echo "$line"; done', 'while read line; do echo "$line"; done < hosts.txt']
    explanation: '`-r` mencegah backslash diproses (lab: `ew\dir` menjadi `ewdir` tanpa `-r`). `IFS=` mempertahankan spasi di awal dan akhir baris (lab: `  lead` menjadi `lead` tanpa itu). `for x in $(cat f)` memecah per kata, bukan per baris.'
    source: https://mywiki.wooledge.org/BashFAQ/001

  - id: a10
    tier: A
    type: text
    objective: tests
    prompt: Di dalam `[ ]` atau `[[ ]]`, operator apa untuk menguji dua **angka** sama dengan?
    answers: ['-eq']
    tests:
      fail: ['==', '=', '-ne', '-lt']
    explanation: '`=`/`==` membandingkan **string** (`"01" = "1"` salah, `01 -eq 1` benar). Operator angka: `-eq -ne -lt -le -gt -ge`. Untuk aritmetika yang lebih mudah dibaca, pakai `(( a == b ))`.'
    source: https://www.gnu.org/software/bash/manual/html_node/Bash-Conditional-Expressions.html

  - id: a11
    tier: A
    type: command
    objective: tests
    prompt: Cetak `ada` bila `/etc/app.env` ada **dan merupakan file biasa**, dalam satu baris.
    answers: ['[ -f /etc/app.env ] && echo ada']
    patterns:
      - '^(\[ -f /etc/app\.env \]|\[\[ -f /etc/app\.env \]\]|test -f /etc/app\.env) && echo ada$'
      - '^if (\[ -f /etc/app\.env \]|\[\[ -f /etc/app\.env \]\]|test -f /etc/app\.env); then echo ada; fi$'
    tests:
      pass: ['[[ -f /etc/app.env ]] && echo ada', 'test -f /etc/app.env && echo ada', 'if [ -f /etc/app.env ]; then echo ada; fi']
      fail: ['[ -d /etc/app.env ] && echo ada', '[ -z /etc/app.env ] && echo ada', '[-f /etc/app.env] && echo ada']
    explanation: '`[` adalah perintah, jadi spasi setelah `[` dan sebelum `]` wajib. Uji file lain: `-d` direktori, `-e` ada (apa pun jenisnya), `-r/-w/-x` izin, `-s` tidak kosong. Uji string: `-z` kosong, `-n` tidak kosong.'
    source: https://www.gnu.org/software/bash/manual/html_node/Bash-Conditional-Expressions.html

  - id: a12
    tier: A
    type: command
    objective: robust
    prompt: Di skrip, cek apakah perintah `docker` tersedia di `PATH`, dengan builtin yang direkomendasikan.
    answers: ['command -v docker']
    patterns: ['^command -v docker( >/dev/null( 2>&1)?)?$']
    tests:
      pass: ['command -v docker >/dev/null 2>&1']
      fail: ['ls /usr/bin/docker', 'docker', 'command docker']
    explanation: 'Pola umum: `command -v docker >/dev/null || { echo "docker tidak ada" >&2; exit 1; }`. Di lab: `command -v bash` → `/usr/bin/bash`, sedangkan docker tidak ada sehingga exit-nya 1. `command -v` adalah builtin POSIX. `which` adalah program eksternal yang perilakunya berbeda antar distro.'
    source: https://www.gnu.org/software/bash/manual/html_node/Bash-Builtins.html

  - id: a13
    tier: A
    type: command
    objective: quoting
    prompt: Cetak nilai `$ENV`, atau `production` bila `ENV` **kosong atau tidak di-set**.
    answers: ['echo "${ENV:-production}"']
    patterns: ['^echo \$\{ENV:[-=]production\}$']
    tests:
      pass: ['echo ${ENV:-production}', 'echo "${ENV:=production}"']
      fail: ['echo ${ENV-production}', 'echo $ENV:-production', 'echo ${ENV:+production}']
    explanation: '`${V:-x}` → x bila kosong/unset. `${V-x}` (tanpa titik dua) → x **hanya** bila unset, jadi string kosong tetap kosong. `${V:=x}` sekalian meng-assign (lab: `default set`). `${V:+x}` kebalikannya: x bila V **berisi**.'
    source: https://www.gnu.org/software/bash/manual/html_node/Shell-Parameter-Expansion.html

  - id: a14
    tier: A
    type: command
    objective: robust
    prompt: Buat file sementara yang aman (nama acak, tidak menimpa file lain) dan simpan path-nya di variabel `tmp`.
    answers: ['tmp=$(mktemp)']
    patterns: ['^tmp=\$\(mktemp( -t \S+| -p \S+)?\)$', '^tmp=`mktemp`$']
    tests:
      pass: ['tmp=$(mktemp -t backup.XXXXXX)']
      fail: ['tmp=/tmp/file.$$', 'tmp=/tmp/tmpfile', 'tmp=$(touch /tmp/x)']
    explanation: 'Nama tetap seperti `/tmp/tmpfile` bisa bertabrakan antar proses, dan di `/tmp` yang dipakai bersama bisa disiapkan penyerang (symlink attack). `mktemp` membuat file dengan mode 600 (lab: `/tmp/tmp.AEGqIv7PRO`). `mktemp -d` untuk direktori. Pasangkan dengan `trap`.'
    source: https://man7.org/linux/man-pages/man1/mktemp.1.html

  - id: a15
    tier: A
    type: command
    objective: robust
    prompt: Pasang trap yang menjalankan fungsi `cleanup` saat skrip **keluar** — baik sukses, gagal oleh `set -e`, maupun `exit`.
    answers: ['trap cleanup EXIT']
    patterns: ['^trap cleanup (EXIT|0)$']
    tests:
      pass: ['trap cleanup 0']
      fail: ['trap cleanup ERR', 'trap EXIT cleanup', 'trap cleanup INT']
    explanation: 'Urutan: `trap PERINTAH SINYAL`. Di lab, skrip `set -e` yang gagal di tengah tetap menjalankan trap EXIT (`cleaned`), dan exit code aslinya (1) dipertahankan. `ERR` hanya saat ada perintah gagal; `INT`/`TERM` untuk Ctrl+C dan `kill`.'
    source: https://www.gnu.org/software/bash/manual/html_node/Bourne-Shell-Builtins.html

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: strict
    prompt: 'Dengan `set -e`, skrip berisi `if grep -q ERROR app.log; then ...; fi`, dan grep tidak menemukan apa-apa (exit 1). Apakah skrip **berhenti**? (ya/tidak)'
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Perintah yang dipakai sebagai kondisi (`if`, `while`, `&&`, `||`, `!`) dikecualikan dari `set -e`. Di lab: `if false` → `survived-if`, `false && x` → `survived-andand`. Konsekuensinya: kegagalan **di dalam fungsi** yang dipanggil sebagai kondisi juga diabaikan (lab: `still runs inside f`).'
    source: https://mywiki.wooledge.org/BashFAQ/105

  - id: b02
    tier: B
    type: text
    objective: strict
    prompt: 'Skrip `set -e`. Di dalam fungsi: `local out=$(curl -sf "$URL")`, dan curl gagal. Apakah skrip berhenti di baris itu? (ya/tidak)'
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: 'Exit status baris itu adalah milik `local` (selalu 0), bukan milik `curl`. Di lab: `local x=$(false)` → lanjut (`masked`), sedangkan `x=$(false)` tanpa `local` → skrip berhenti. Hal yang sama berlaku untuk `export X=$(...)` dan `readonly`. ShellCheck menandainya SC2155.'
    source: https://www.shellcheck.net/wiki/SC2155

  - id: b03
    tier: B
    type: command
    objective: strict
    prompt: 'Tulis ulang `local out=$(curl -sf "$URL")` menjadi dua perintah (dipisah `;`) supaya kegagalan curl **memicu** `set -e`.'
    answers: ['local out; out=$(curl -sf "$URL")']
    patterns: ['^local (\w+); \1=\$\(curl -sf \$\{?URL\}?\)$']
    tests:
      pass: ['local out; out=$(curl -sf "${URL}")']
      fail: ['local out=$(curl -sf "$URL")', 'out=$(curl -sf "$URL"); local out', 'local out=$(curl -sf "$URL") || true']
    explanation: 'Deklarasikan dulu, baru assign: `local out` lalu `out=$(...)`. Exit status assignment murni adalah exit status command substitution-nya. Urutan terbalik (`out=...; local out`) membuat variabel lokal baru yang kosong.'
    source: https://www.shellcheck.net/wiki/SC2155

  - id: b04
    tier: B
    type: text
    objective: strict
    prompt: '`set -e` aktif, **tanpa** `pipefail`. Pada `curl -s "$URL" | jq .status`, curl gagal (exit 6) dan jq exit 0. Berapa exit status pipeline itu?'
    answers: ['0']
    tests:
      fail: ['6', '1', '2']
    explanation: 'Tanpa `pipefail`, status pipeline = status perintah **terakhir**. Di lab: `false | true` → skrip lanjut, dengan `pipefail` → berhenti. `${PIPESTATUS[@]}` menyimpan status setiap bagian (lab: `1 0 3`). Ini krusial untuk `pg_dump | gzip > backup.gz`.'
    source: https://www.gnu.org/software/bash/manual/html_node/Pipelines.html

  - id: b05
    tier: B
    type: text
    objective: strict
    prompt: '`set -e; i=0; ((i++)); echo selesai` — apakah `selesai` tercetak? (ya/tidak)'
    answers: ['tidak', 'no']
    tests:
      fail: ['ya', 'yes']
    explanation: '`((ekspresi))` mengembalikan exit 1 bila hasilnya 0. `i++` adalah post-increment, nilainya 0 (nilai lama), jadi `set -e` menghentikan skrip. Di lab: exit 1 tanpa output. Pakai `i=$((i+1))` atau `((++i))` — atau `((i++)) || true`. Bug ini sering muncul sebagai "loop counter yang mematikan skrip hanya pada iterasi pertama".'
    source: https://www.gnu.org/software/bash/manual/html_node/Conditional-Constructs.html

  - id: b06
    tier: B
    type: command
    objective: quoting
    prompt: '`m=$(date +%m)` bernilai `08`. Cetak `m + 1` dengan aritmetika bash **tanpa** error oktal.'
    answers: ['echo $((10#$m + 1))']
    patterns: ['^echo \$\(\( ?10#\$\{?m\}? ?\+ ?1 ?\)\)$']
    tests:
      pass: ['echo $(( 10#$m + 1 ))', 'echo $((10#${m}+1))']
      fail: ['echo $((m + 1))', 'echo $(($m+1))', 'echo $((08 + 1))']
    explanation: 'Angka berawalan 0 dibaca sebagai oktal, dan 8/9 bukan digit oktal. Di lab: `08: value too great for base`, sedangkan `10#08` → 9. Muncul hanya di bulan Agustus–September atau tanggal 08/09 — bug yang lolos testing lalu meledak di production.'
    source: https://www.gnu.org/software/bash/manual/html_node/Shell-Arithmetic.html

  - id: b07
    tier: B
    type: text
    objective: functions
    prompt: Skrip diakhiri `exit 256`. Exit code apa yang dilihat pemanggilnya (`echo $?`)?
    answers: ['0']
    tests:
      fail: ['256', '1', '255']
    explanation: 'Exit code hanya 8 bit (0–255) dan dihitung modulo 256. Di lab: `exit 256` → 0, `exit 300` → 44. Jangan memakai exit/return untuk mengembalikan angka besar (jumlah file, dll.). Kembalikan data lewat stdout: `n=$(hitung)`.'
    source: https://www.gnu.org/software/bash/manual/html_node/Exit-Status.html

  - id: b08
    tier: B
    type: text
    objective: tests
    prompt: '`[ 10 > 9 ] && echo ya` mencetak `ya`, padahal bukan perbandingan angka yang terjadi. Apa yang terbentuk di direktori saat ini?'
    answers: ['file 9', 'file bernama 9', '9']
    patterns: ['^((sebuah )?file( bernama| dengan nama)? )?["`'']?9["`'']?$']
    tests:
      pass: ['sebuah file bernama 9']
      fail: ['tidak ada', 'error', 'folder 10', 'file 10']
    explanation: 'Di dalam `[ ]`, `>` adalah **redirect**: `[ 10 ]` (string tidak kosong = true) dengan stdout dialihkan ke file `9`. Di lab, `ls` menampilkan file `9`. Di `[[ ]]`, `<`/`>` membandingkan **string** (lab: `[[ 10 < 9 ]]` true). Untuk angka: `-gt`, atau `(( 10 > 9 ))`.'
    source: https://mywiki.wooledge.org/BashPitfalls

  - id: b09
    tier: B
    type: command
    objective: tests
    prompt: Cetak `besar` bila `$n` lebih besar dari 9 secara **numerik**, memakai `[[ ]]`.
    answers: ['[[ $n -gt 9 ]] && echo besar']
    patterns: ['^\[\[ \$\{?n\}? -gt 9 \]\] && echo besar$', '^\(\( ?\$?n ?> ?9 ?\)\) && echo besar$']
    tests:
      pass: ['(( n > 9 )) && echo besar', '[[ "$n" -gt 9 ]] && echo besar']
      fail: ['[[ $n > 9 ]] && echo besar', '[ $n > 9 ] && echo besar', '[[ $n -lt 9 ]] && echo besar']
    explanation: '`[[ $n > 9 ]]` membandingkan teks: `10 > 9` bernilai false karena "1" < "9". Kelebihan `[[ ]]`: variabel kosong tidak memicu error. Di lab, `[ $v = x ]` dengan `v` kosong → `unary operator expected`, sedangkan `[[ $v = x ]]` aman.'
    source: https://www.gnu.org/software/bash/manual/html_node/Conditional-Constructs.html

  - id: b10
    tier: B
    type: text
    objective: loops
    prompt: '`n=0; cat f | while read -r l; do n=$((n+1)); done; echo $n` — file `f` berisi 3 baris. Apa output-nya?'
    answers: ['0']
    tests:
      fail: ['3', '1', '2']
    explanation: 'Setiap bagian pipeline berjalan di **subshell**, sehingga `n` yang dinaikkan hilang ketika loop selesai. Di lab: `n=0`, sedangkan `while ...; done < f` → `n=3`. Alternatif: `done < <(perintah)` (process substitution) atau `shopt -s lastpipe`.'
    source: https://mywiki.wooledge.org/BashFAQ/024

  - id: b11
    tier: B
    type: command
    objective: quoting
    prompt: '`f=/var/backups/db-2026-11-20.sql.gz`. Cetak **nama file saja** (tanpa direktori) dengan parameter expansion, tanpa memanggil `basename`.'
    answers: ['echo "${f##*/}"']
    patterns: ['^echo \$\{f##\*/\}$']
    tests:
      pass: ['echo ${f##*/}']
      fail: ['echo ${f#*/}', 'echo ${f%/*}', 'basename $f']
    explanation: '`#` hapus prefix terpendek, `##` terpanjang; `%` hapus suffix terpendek, `%%` terpanjang. Di lab: `${f##*/}` → `db-2026-11-20.sql.gz`, `${f%/*}` → `/var/backups`, `${f%.gz}` → `...sql`, `${f%%.*}` → `/var/backups/db-2026-11-20`, `${#f}` → 33. Tanpa proses baru, jadi jauh lebih cepat di dalam loop.'
    source: https://www.gnu.org/software/bash/manual/html_node/Shell-Parameter-Expansion.html

  - id: b12
    tier: B
    type: command
    objective: quoting
    prompt: Cetak `$f` (`/var/backups/db-2026-11-20.sql.gz`) **tanpa** akhiran `.gz` saja (hasil masih berakhiran `.sql`).
    answers: ['echo "${f%.gz}"']
    patterns: ['^echo \$\{f%\.gz\}$', '^echo \$\{f%\.\*\}$']
    tests:
      pass: ['echo ${f%.*}']
      fail: ['echo ${f%%.*}', 'echo ${f#.gz}', 'echo ${f##.gz}']
    explanation: '`%%.*` memotong dari titik **pertama**, sehingga `.sql` ikut hilang (lab: `/var/backups/db-2026-11-20`). `#` bekerja dari depan, jadi `${f#.gz}` tidak mengubah apa pun.'
    source: https://www.gnu.org/software/bash/manual/html_node/Shell-Parameter-Expansion.html

  - id: b13
    tier: B
    type: command
    objective: robust
    prompt: Jalankan `./backup.sh` dan simpan **stdout dan stderr** ke `backup.log`.
    answers: ['./backup.sh > backup.log 2>&1']
    patterns: ['^\./backup\.sh >>?backup\.log 2>&1$', '^\./backup\.sh &>>? ?backup\.log$']
    tests:
      pass: ['./backup.sh >> backup.log 2>&1', './backup.sh &> backup.log']
      fail: ['./backup.sh 2>&1 > backup.log', './backup.sh > backup.log', './backup.sh 2> backup.log']
    explanation: 'Redirect diproses kiri ke kanan. `2>&1 > file` mengarahkan stderr ke stdout **lama** (terminal), baru kemudian stdout ke file. Di lab: dengan `2>&1 >file`, pesan error tetap muncul di layar dan file kosong. `&>` hanya ada di bash.'
    source: https://www.gnu.org/software/bash/manual/html_node/Redirections.html

  - id: b14
    tier: B
    type: text
    objective: portability
    prompt: 'Skrip ber-shebang `#!/bin/sh` di Ubuntu gagal dengan `[[: not found`. Shell apa yang sebenarnya menjalankannya?'
    answers: ['dash']
    tests:
      fail: ['bash', 'zsh', 'ksh', 'busybox']
    explanation: 'Di Ubuntu/Debian, `/bin/sh` → dash (lab: `/usr/bin/dash`). Di lab, dash gagal untuk: `[[` (`not found`), array (`Syntax error: "(" unexpected`), `source` (`not found`; pakai `.`), `set -o pipefail` (`Illegal option`), dan `echo -e` (mencetak `-e`). Kalau butuh fitur bash, tulis `#!/bin/bash` dan jalankan dengan `bash skrip.sh`, bukan `sh skrip.sh`.'
    source: https://wiki.ubuntu.com/DashAsBinSh

  - id: b15
    tier: B
    type: command
    objective: basics
    prompt: Lakukan **analisis statis** pada `deploy.sh` untuk menemukan bug shell umum (quoting, `cd` tanpa cek, parsing `ls`).
    answers: ['shellcheck deploy.sh']
    patterns: ['^shellcheck (-x |-S \w+ |-s bash )*(\./)?deploy\.sh$']
    tests:
      pass: ['shellcheck -x deploy.sh']
      fail: ['bash -n deploy.sh', 'bash -x deploy.sh', 'sh deploy.sh']
    explanation: 'Di lab, skrip 6 baris memunculkan SC2164 (`cd` tanpa `|| exit`), SC2086 (variabel tanpa kutip), SC2045 (iterasi output `ls`), dan SC2035 (glob tanpa `./`). Jalankan shellcheck di CI untuk setiap `*.sh`, karena ia murah dan menangkap bug yang tidak ditangkap `bash -n`.'
    source: https://www.shellcheck.net/wiki/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: robust
    prompt: |
      Review skrip di bawah. Skrip ini dijalankan CI sebagai `./clean.sh "$APP_DIR"`, dan suatu hari `APP_DIR` kosong.
      Jelaskan dengan menulis ulang skripnya (baris per baris, apa adanya) supaya aman: shell yang benar, mode strict,
      validasi argumen, `cd` yang dicek, loop tanpa `ls`, quoting, dan `rm` yang tidak bisa menyasar path lain.
    context: |
      #!/bin/sh
      APP_DIR=$1
      cd $APP_DIR
      for f in $(ls storage/logs/*.log); do
        gzip $f
      done
      rm -rf $APP_DIR/cache/*
    checklist:
      - id: shebang
        label: Shebang bash
        patterns: ['^#!\s*(/usr/bin/env\s+bash|/bin/bash)']
      - id: strict
        label: Mode strict (set -euo pipefail)
        required: true
        patterns: ['^set\s+-[a-z]*e[a-z]*(\s|$)', '^set\s+-o\s+errexit']
      - id: guard
        label: Validasi argumen (${1:?}, [ -z ], [ $# -ne 1 ], [ -d ])
        required: true
        patterns: ['\$\{1:\?', '\$\{app_dir:\?', '\[\[?\s+-z\s+"?\$\{?(1|app_dir)', '\[\[?\s+\$#\s+-(lt|ne|eq)\s', '\[\[?\s+!\s+-d\s+"?\$\{?(1|app_dir)']
      - id: cd-check
        label: cd dengan kutip dan || exit
        required: true
        patterns: ['cd\s+(--\s+)?"\$\{?\w+(:\?[^}]*)?\}?"\s*\|\|\s*(exit|return|\{)']
      - id: glob
        label: Loop dengan glob, bukan $(ls)
        patterns: ['for\s+\w+\s+in\s+"?\S*storage/logs/\*\.log']
      - id: quoted
        label: Variabel dikutip saat dipakai (gzip "$f")
        patterns: ['gzip\s+(-\S+\s+)*(--\s+)?"\$\{?\w+\}?"']
      - id: rm-safe
        label: rm dengan path terkutip/terjaga (${VAR:?}) atau relatif setelah cd yang dicek
        patterns: ['rm\s+-rf\s+"\$\{?\w+(:\?[^}]*)?\}?"/cache/\*', 'rm\s+-rf\s+(\./)?cache/\*']
      - id: no-ls-loop
        label: Tidak tetap mem-parsing ls
        forbid: true
        patterns: ['for\s+\w+\s+in\s+(\$\(|`)ls']
      - id: no-unquoted-rm
        label: Tidak rm -rf dengan variabel tanpa kutip
        forbid: true
        patterns: ['rm\s+-rf\s+\$\{?\w+\}?/']
    pass_score: 0.6
    solution: |
      ```bash
      #!/usr/bin/env bash
      set -euo pipefail
      app_dir=${1:?usage: clean.sh APP_DIR}
      [[ -d "$app_dir/storage" ]] || { echo "bukan direktori app: $app_dir" >&2; exit 1; }
      cd -- "$app_dir" || exit 1
      shopt -s nullglob
      for f in storage/logs/*.log; do
        gzip -- "$f"
      done
      rm -rf -- "${app_dir:?}/cache/"*
      ```
      Apa yang terjadi dengan skrip asli saat `APP_DIR` kosong, direproduksi di lab dengan dash:
      - `cd $APP_DIR` menjadi `cd` tanpa argumen, yang **pindah ke HOME** (lab: `pwd` → `/root`) tanpa error.
      - Baris terakhir menjadi `rm -rf /cache/*`, menghapus `/cache` di root filesystem.
      - Di direktori lain bisa lebih buruk lagi (ingat insiden Steam di modul Filesystem).

      Perbaikan berlapis: `${1:?}` menghentikan skrip sebelum apa pun terjadi, `cd ... || exit` bila direktori tidak
      ada, `${app_dir:?}` sebagai pengaman terakhir di `rm`, dan glob + `nullglob` untuk nama file berspasi atau tidak
      adanya log. Jalankan `shellcheck` di CI. Semua masalah skrip asli ditandai olehnya (SC2164, SC2086, SC2045).
    tests:
      pass:
        - |
          #!/usr/bin/env bash
          set -euo pipefail
          app_dir=${1:?usage}
          cd "$app_dir" || exit 1
          for f in storage/logs/*.log; do
            gzip "$f"
          done
          rm -rf "${app_dir:?}/cache/"*
        - |
          #!/bin/bash
          set -eu
          [ $# -eq 1 ] || exit 2
          cd "$1" || exit 1
          for f in ./storage/logs/*.log; do gzip -- "$f"; done
          rm -rf ./cache/*
      fail:
        - |
          #!/bin/bash
          set -e
          cd $1
          for f in $(ls storage/logs/*.log); do gzip "$f"; done
          rm -rf $1/cache/*
        - |
          #!/bin/bash
          cd "$1" || exit 1
          rm -rf "$1/cache/"*
        - |
          set -euo pipefail
          for f in storage/logs/*.log; do gzip "$f"; done
    source: https://mywiki.wooledge.org/BashPitfalls

  - id: c02
    tier: C
    type: scenario
    objective: robust
    prompt: |
      Tulis skrip backup PostgreSQL harian (baris per baris): pastikan `pg_dump` ada, dump database `appdb` dan
      kompres ke file ber-timestamp di `/var/backups/pg`, jangan pernah meninggalkan file backup **setengah jadi**
      bila dump gagal, hapus backup yang lebih tua dari 7 hari, dan keluar dengan exit code non-zero bila gagal.
    checklist:
      - id: strict
        label: set -euo pipefail (pipefail wajib untuk pg_dump | gzip)
        required: true
        patterns: ['pipefail']
      - id: check
        label: Memastikan pg_dump tersedia (command -v)
        patterns: ['command\s+-v\s+pg_dump']
      - id: timestamp
        label: Nama file ber-timestamp ($(date +...))
        patterns: ['\$\(date\s+\+\S*%']
      - id: pipe
        label: pg_dump | gzip
        required: true
        patterns: ['pg_dump\s+.*\|\s*gzip']
      - id: trap
        label: trap untuk membersihkan file sementara/setengah jadi
        required: true
        patterns: ['^trap\s+.*\b(exit|err|0)\s*$']
      - id: atomic
        label: Tulis ke file sementara lalu mv (atomik)
        patterns: ['\b(mv)\s+(--\s+)?"?\$\{?\w+\}?"?\s+"?\$\{?\w+\}?"?', '\.(partial|tmp)\b', 'mktemp']
      - id: retention
        label: Retensi 7 hari (find ... -mtime +7 -delete)
        patterns: ['find\s+\S+\s+.*-mtime\s+\+[67]\b.*(-delete|-exec\s+rm)']
      - id: no-delete-first
        label: Tidak menaruh -delete sebelum filter find
        forbid: true
        patterns: ['find\s+\S+\s+(\S+\s+)*-delete\s+.*-(name|mtime)']
      - id: no-unquoted-rm
        label: Tidak rm -rf variabel tanpa kutip
        forbid: true
        patterns: ['rm\s+-rf\s+\$\{?\w+\}?/?\*?\s*$']
    pass_score: 0.6
    solution: |
      ```bash
      #!/usr/bin/env bash
      set -euo pipefail
      command -v pg_dump >/dev/null || { echo "pg_dump tidak ditemukan" >&2; exit 1; }
      dir=/var/backups/pg
      final="$dir/appdb-$(date +%Y%m%d-%H%M%S).sql.gz"
      tmp="$final.partial"
      trap 'rm -f -- "$tmp"' EXIT
      pg_dump appdb | gzip > "$tmp"
      mv -- "$tmp" "$final"
      find "$dir" -name 'appdb-*.sql.gz' -mtime +7 -delete
      echo "backup ok: $final"
      ```
      Kenapa setiap baris penting (diuji di lab):
      - Tanpa `pipefail`, `false | gzip > x.gz` dianggap **sukses**. Dengan `pipefail`, skrip berhenti (exit 1),
        tetapi file `x.gz` (20 byte, berisi kompresi dari "tidak ada apa-apa") **tetap tertinggal**. Itu sebabnya
        dump ditulis ke `.partial`, dihapus oleh `trap ... EXIT` bila gagal, dan baru di-`mv` (atomik di
        filesystem yang sama) setelah berhasil.
      - Backup yang "sukses" tapi kosong jauh lebih berbahaya daripada backup yang gagal dengan jelas.
      - Retensi `find` berjalan **setelah** backup baru berhasil.

      Uji restore secara berkala, karena backup yang tidak pernah di-restore belum terbukti bisa dipakai.
    tests:
      pass:
        - |
          #!/usr/bin/env bash
          set -euo pipefail
          command -v pg_dump >/dev/null
          final="/var/backups/pg/appdb-$(date +%F).sql.gz"
          tmp="$final.partial"
          trap 'rm -f -- "$tmp"' EXIT
          pg_dump appdb | gzip > "$tmp"
          mv -- "$tmp" "$final"
          find /var/backups/pg -name '*.sql.gz' -mtime +7 -delete
        - |
          set -eo pipefail
          tmp=$(mktemp)
          trap 'rm -f "$tmp"' ERR
          pg_dump appdb | gzip > "$tmp"
          mv "$tmp" "/var/backups/pg/appdb-$(date +%Y%m%d).sql.gz"
      fail:
        - |
          #!/bin/bash
          pg_dump appdb | gzip > /var/backups/pg/appdb.sql.gz
          find /var/backups/pg -delete -mtime +7
        - |
          set -e
          pg_dump appdb | gzip > "/var/backups/pg/appdb-$(date +%F).sql.gz"
          find /var/backups/pg -mtime +7 -delete
        - |
          set -euo pipefail
          pg_dump appdb | gzip > /var/backups/pg/latest.sql.gz
    source: https://www.gnu.org/software/bash/manual/html_node/Pipelines.html

  - id: c03
    tier: C
    type: scenario
    objective: portability
    prompt: |
      Skrip laporan berjalan normal saat dijalankan manual, tapi dari cron **tidak pernah** menghasilkan apa pun dan
      tidak ada error yang terlihat. Tulis perbaikan (baris crontab dan/atau baris skrip, satu per baris) supaya job
      punya PATH yang benar, direktori kerja yang pasti, log yang bisa dibaca, dan tidak tumpang tindih.
    context: |
      $ crontab -l
      0 2 * * * ~/scripts/report.sh
      $ grep CRON /var/log/syslog | tail -n 2
      ... CRON[4410]: (deploy) CMD (~/scripts/report.sh)
      ... CRON[4409]: (CRON) info (No MTA installed, discarding output)
      $ cat ~/scripts/report.sh
      #!/bin/bash
      cd app
      aws s3 cp storage/report.csv s3://acme-reports/
      $ command -v aws
      /usr/local/bin/aws
    checklist:
      - id: path
        label: PATH yang mencakup /usr/local/bin, atau path absolut ke aws
        required: true
        patterns: ['^path\s*=.*/usr/local/bin', 'export\s+path=.*/usr/local/bin', '/usr/local/bin/aws\b']
      - id: cd
        label: cd ke path absolut dengan pengecekan
        patterns: ['cd\s+(--\s+)?"?(/|\$home|~/)\S+"?\s*\|\|\s*(exit|\{)', 'cd\s+"\$\(dirname']
      - id: log
        label: Output cron diarahkan ke file log (>> file 2>&1)
        required: true
        patterns: ['>>?\s*\S+\.log\s+2>&1']
      - id: strict
        label: Skrip gagal dengan jelas (set -euo pipefail)
        patterns: ['^set\s+-[a-z]*e[a-z]*(\s|$)']
      - id: flock
        label: Mencegah tumpang tindih (flock -n)
        patterns: ['flock\s+-\S*n']
      - id: shell
        label: Menyadari SHELL=/bin/sh di cron (SHELL=/bin/bash atau shebang + bash eksplisit)
        patterns: ['^shell\s*=\s*/bin/bash', '\bbash\s+\S*report\.sh']
      - id: no-777
        label: Tidak "memperbaiki" dengan chmod 777 atau menjalankan sebagai root
        forbid: true
        patterns: ['chmod\s+(-\S+\s+)*0?777', '^\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+root\s']
    pass_score: 0.5
    solution: |
      ```text
      # crontab -e  (user deploy)
      SHELL=/bin/bash
      PATH=/usr/local/bin:/usr/bin:/bin
      0 2 * * * flock -n /tmp/report.lock /home/deploy/scripts/report.sh >> /home/deploy/logs/report.log 2>&1
      ```
      ```bash
      #!/bin/bash
      set -euo pipefail
      cd /home/deploy/app || exit 1
      aws s3 cp storage/report.csv s3://acme-reports/
      ```
      Lingkungan cron di lab (Ubuntu 24.04): `PATH=/usr/bin:/bin`, `SHELL=/bin/sh`, `HOME=/home/deploy`, dan
      direktori kerja = HOME. Ada tiga masalah di skrip ini:
      - `aws` ada di `/usr/local/bin`, yang tidak termasuk PATH cron, sehingga skrip gagal dengan
        `command not found`.
      - `cd app` relatif dan tidak dicek. Kebetulan benar dari HOME, tapi rapuh.
      - Pesan error dikirim lewat email, dan karena tidak ada MTA, dibuang (`discarding output`). Itu
        sebabnya tidak terlihat apa pun.

      `>> log 2>&1` membuat setiap kegagalan bisa dibaca, dan `flock -n` (modul Process) mencegah run yang menumpuk.
    tests:
      pass:
        - |
          PATH=/usr/local/bin:/usr/bin:/bin
          0 2 * * * flock -n /tmp/report.lock /home/deploy/scripts/report.sh >> /home/deploy/logs/report.log 2>&1
          set -euo pipefail
          cd /home/deploy/app || exit 1
        - |
          0 2 * * * /home/deploy/scripts/report.sh >> /home/deploy/report.log 2>&1
          cd "$(dirname "$0")/../app"
          /usr/local/bin/aws s3 cp storage/report.csv s3://acme-reports/
      fail:
        - 0 2 * * * ~/scripts/report.sh
        - |
          chmod 777 ~/scripts/report.sh
          0 2 * * * ~/scripts/report.sh
        - |
          0 2 * * * root /home/deploy/scripts/report.sh
          PATH=/usr/local/bin:/usr/bin:/bin
    source: https://man7.org/linux/man-pages/man5/crontab.5.html

---

# Bash Scripting yang Aman

Skrip shell adalah lem DevOps: deploy, backup, healthcheck, entrypoint Docker, langkah CI. Skrip yang ditulis
asal-asalan tidak gagal dengan jelas. Ia **diam-diam** melakukan hal yang salah: menghapus direktori yang
keliru, menghasilkan backup kosong, atau tidak pernah jalan di cron. Modul ini fokus pada bash yang
*gagal dengan jelas*.

> [!NOTE]
> Semua perilaku diuji di Ubuntu 24.04 dengan GNU bash 5.2.21, dash 0.5.12 (`/bin/sh`), dan ShellCheck 0.9.0.
> Contoh output di bawah adalah hasil lab. Banyak jebakan di sini terdokumentasi di BashPitfalls dan BashFAQ
> (Greg's Wiki), referensi komunitas yang paling sering dikutip untuk bash.

## 1. Kerangka skrip

```bash
#!/usr/bin/env bash
set -euo pipefail                       # gagal cepat (dengan catatan, lihat bagian 3)
IFS=$'\n\t'                             # opsional: word splitting hanya di newline/TAB

readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
log() { printf '[%s] %s\n' "$(date +%T)" "$*" >&2; }
die() { log "ERROR: $*"; exit 1; }

command -v rsync >/dev/null || die "rsync tidak ada"
target=${1:?usage: $0 TARGET}

tmp=$(mktemp -d)
trap 'rm -rf -- "$tmp"' EXIT

log "deploy ke $target"
```

```bash
bash -n skrip.sh        # cek syntax
bash -x skrip.sh        # trace setiap perintah
shellcheck skrip.sh     # analisis statis — jalankan di CI
```

## 2. Variabel dan quoting

| Bentuk | Hasil (lab, `f=/var/backups/db-2026-11-20.sql.gz`) |
|---|---|
| `"$f"` | Satu argumen utuh, walau berisi spasi atau `*` |
| `${f##*/}` | `db-2026-11-20.sql.gz` (basename) |
| `${f%/*}` | `/var/backups` (dirname) |
| `${f%.gz}` / `${f%%.*}` | `.../db-2026-11-20.sql` / `.../db-2026-11-20` |
| `${#f}` | `33` (panjang) |
| `${f/2026/YYYY}` | Ganti kemunculan pertama |
| `${V:-def}` / `${V:=def}` | Default / default + assign |
| `${V:?pesan}` | Berhenti dengan pesan bila kosong — **wajib** di sekitar `rm` |

> [!DANGER]
> **Selalu kutip variabel**: `"$f"`, `"$@"`, `"$(cmd)"`. Variabel tanpa kutip dipecah di spasi **dan** di-glob.
> Di lab, `f="a b.log"; ls $f` → `cannot access 'a'` dan `cannot access 'b.log'`. Untuk `rm`, itu berarti
> menghapus file yang salah. ShellCheck SC2086 menandai setiap kasus ini.

## 3. `set -euo pipefail` — dan celahnya

| Opsi | Efek |
|---|---|
| `-e` | Berhenti bila perintah gagal |
| `-u` | Berhenti bila variabel tidak di-set |
| `-o pipefail` | Status pipeline = kegagalan pertama, bukan perintah terakhir |
| `-E` | Trap ERR ikut berlaku di fungsi/subshell |

`set -e` **tidak** berlaku dalam situasi berikut (semuanya direproduksi di lab):

| Situasi | Hasil |
|---|---|
| `if cmd; then` / `while cmd` / `cmd && x` / `cmd \|\| y` | Lanjut — kegagalan dianggap kondisi |
| Fungsi yang dipanggil sebagai kondisi (`if f; then`) | `set -e` mati **di dalam** fungsi itu |
| `local x=$(false)` / `export x=$(false)` | Lanjut — status milik `local` |
| `false \| true` tanpa pipefail | Lanjut |
| `((i++))` saat `i=0` | **Berhenti** (ekspresi bernilai 0 = gagal) |

> [!INSIGHT]
> `set -e` sering memberi rasa aman yang palsu. Greg's Wiki (BashFAQ/105) mendokumentasikan puluhan kasus di mana
> perilakunya mengejutkan, termasuk berbeda antar versi bash. Perlakukan `set -euo pipefail` sebagai jaring kedua,
> bukan pengganti pengecekan eksplisit: `cmd || die "..."` untuk langkah yang kritis.
> Sumber: [BashFAQ/105](https://mywiki.wooledge.org/BashFAQ/105)

## 4. Kondisi

| Bentuk | Untuk | Catatan dari lab |
|---|---|---|
| `[ ... ]` | POSIX, juga dash | `[ $v = x ]` dengan `v` kosong → `unary operator expected` |
| `[[ ... ]]` | bash | Variabel kosong aman; mendukung pola `== *.log` dan regex `=~` |
| `(( ... ))` | Aritmetika | `(( n > 9 ))` membandingkan **angka** |
| `-eq -ne -lt -gt` | Angka di `[ ]`/`[[ ]]` | `=`/`==`/`<`/`>` membandingkan **string** |

Jebakan yang direproduksi di lab:
- `[ 10 > 9 ]` → true dan **membuat file `9`**, karena `>` di sini adalah redirect.
- `[[ 10 < 9 ]]` → true, karena perbandingan string.
- `$((08 + 1))` → `value too great for base`. Pakai `10#$m`.

## 5. Loop

```bash
for f in /var/log/app/*.log; do gzip -- "$f"; done          # glob, bukan $(ls)
shopt -s nullglob                                          # glob tanpa hasil → loop dilewati
while IFS= read -r line; do ...; done < hosts.txt         # per baris, apa adanya
while IFS= read -r -d '' f; do ...; done < <(find . -name '*.tmp' -print0)
for host in "${hosts[@]}"; do ssh "$host" uptime; done    # array
```

| Jebakan | Hasil di lab |
|---|---|
| `for f in $(ls)` | `a b.log` → `[a]` `[b.log]` |
| Glob tanpa match | Loop jalan sekali dengan teks `*.txt` (tanpa `nullglob`) |
| `cat f \| while read ...; n=$((n+1))` | `n=0` setelah loop (subshell) |
| `read` tanpa `-r` / tanpa `IFS=` | Backslash dibuang, spasi di awal baris hilang |

## 6. Fungsi

```bash
backup_db() {
    local db=$1 out                    # local: tidak bocor ke global
    out=$(mktemp) || return 1          # pisahkan local dan assignment
    pg_dump "$db" > "$out" || return 1
    printf '%s\n' "$out"               # "return value" lewat stdout
}
file=$(backup_db appdb) || die "backup gagal"
```

- `return N` (0–255) untuk status, stdout untuk data. Di lab, `exit 256` → 0 dan `exit 300` → 44.
- Tanpa `local`, variabel fungsi menimpa variabel global (lab: `x` menjadi `changed`).
- `exit` di dalam fungsi menghentikan **seluruh** skrip, kecuali fungsi dijalankan di subshell `( )`.

## 7. Skrip yang tahan gagal

```bash
cd -- "$dir" || exit 1                    # lab: cd gagal tanpa ini → perintah berikutnya jalan di direktori lama
trap 'rm -f -- "$tmp"' EXIT               # lab: tetap jalan saat set -e menghentikan skrip
trap 'echo "error di baris $LINENO: $BASH_COMMAND" >&2' ERR
./job.sh >> job.log 2>&1                   # urutan: > dulu, baru 2>&1
cat <<'EOF' > config.ini                   # 'EOF' dikutip → $var tidak diekspansi
password=$NOT_EXPANDED
EOF
```

> [!WARNING]
> `cd` dengan variabel kosong tidak gagal, melainkan pindah ke HOME. Di lab, dash dengan `APP_DIR=""` menjalankan
> `cd $APP_DIR` → `pwd` = `/root`, tanpa error. Semua perintah relatif setelahnya berjalan di tempat yang salah.
> Validasi argumen di awal (`${1:?}`), kutip, dan cek: `cd -- "$dir" || exit 1`.

## 8. bash vs sh, dan cron

| Fitur | bash | dash (`/bin/sh` Ubuntu) — lab |
|---|---|---|
| `[[ ]]` | ✓ | `[[: not found` |
| Array `a=(1 2)` | ✓ | `Syntax error: "(" unexpected` |
| `source file` | ✓ | `source: not found` (pakai `. file`) |
| `set -o pipefail` | ✓ | `Illegal option -o pipefail` |
| `echo -e "a\tb"` | ✓ | mencetak `-e a  b` — pakai `printf` |

Lingkungan cron di lab: `PATH=/usr/bin:/bin`, `SHELL=/bin/sh`, dan direktori kerja = HOME. Tidak ada
`.bashrc`, tidak ada `/usr/local/bin`. Output yang tidak di-redirect dikirim lewat email, dan dibuang bila tidak ada MTA.
Aturan aman untuk cron:
- Tulis path absolut, atau set `PATH=` di crontab.
- Arahkan output ke log dengan `>> log 2>&1`.
- Pakai `flock -n` agar run tidak menumpuk.

## 9. Ringkasan kebiasaan aman

| Kebiasaan | Kenapa |
|---|---|
| `#!/usr/bin/env bash` + `bash skrip.sh` (bukan `sh`) | `/bin/sh` adalah dash |
| `set -euo pipefail` + cek eksplisit `|| die` | `set -e` punya banyak celah |
| Kutip setiap variabel; `"$@"` | Word splitting dan globbing |
| `${1:?}`, `${VAR:?}` di sekitar `rm` | Variabel kosong → path yang salah |
| `cd -- "$d" || exit` | `cd` gagal / pindah ke HOME |
| Glob + `nullglob`, `while IFS= read -r` | Nama berspasi dan baris apa adanya |
| `mktemp` + `trap ... EXIT`, tulis `.partial` lalu `mv` | Tidak ada file setengah jadi |
| `[[ ]]` untuk string, `(( ))`/`-gt` untuk angka, `10#` untuk angka berawalan 0 | Jebakan `>`, oktal |
| `shellcheck` di CI | Menangkap sebagian besar bug di atas |
