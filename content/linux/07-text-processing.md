---
format: 1
id: linux-text-processing
course:
  slug: linux
  title: Linux
  description: Fondasi Linux untuk DevOps/DevSecOps — dari filesystem sampai hardening.
title: Text Processing (grep, sed, awk, jq)
summary: Membaca log dan mengubah config dari terminal — grep, cut, sort/uniq, awk, sed, tail, dan jq — beserta jebakan yang sering merusak data atau hasil analisis.
order: 7
version: 1
status: verified
updated: '2026-11-20'
tested_on:
  - Ubuntu 24.04.5 LTS (container Docker) — GNU grep 3.11, GNU sed 4.9, mawk 1.3.4 (awk default), coreutils 9.4, jq 1.7, OpenSSH 9.6p1
  - Log akses nginx sintetis 2.000 baris (format combined + request time) untuk semua contoh angka
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: grep
    text: Mencari pola dengan grep (case, invert, konteks, rekursif, literal, regex extended)
  - id: fields
    text: Mengambil kolom dengan cut dan awk
  - id: aggregate
    text: Menghitung dan meranking dengan sort, uniq, dan awk
  - id: sed
    text: Mengubah teks dan file config dengan sed secara aman
  - id: follow
    text: Mengikuti log yang berjalan dan dirotasi (tail -f / -F)
  - id: jq
    text: Memfilter dan meringkas log JSON dengan jq
  - id: pitfalls
    text: Menghindari jebakan (redirect ke file sendiri, CRLF, uniq tanpa sort, BRE vs ERE)
  - id: incident
    text: Menganalisis log saat insiden dan mengedit config kritis dengan verifikasi
sources:
  - title: GNU grep manual
    url: https://www.gnu.org/software/grep/manual/grep.html
  - title: GNU sed manual
    url: https://www.gnu.org/software/sed/manual/sed.html
  - title: mawk(1) — awk default Ubuntu
    url: https://manpages.ubuntu.com/manpages/noble/man1/mawk.1.html
  - title: The GNU Awk User's Guide
    url: https://www.gnu.org/software/gawk/manual/gawk.html
  - title: GNU Coreutils — sort, uniq, cut, tail, wc, tr
    url: https://www.gnu.org/software/coreutils/manual/coreutils.html
  - title: jq 1.7 Manual
    url: https://jqlang.org/manual/v1.7/
  - title: nginx — log_format combined
    url: https://nginx.org/en/docs/http/ngx_http_log_module.html#log_format
  - title: sshd_config(5) — first obtained value is used
    url: https://man.openbsd.org/sshd_config
  - title: GNU Bash Manual — Redirections
    url: https://www.gnu.org/software/bash/manual/html_node/Redirections.html
  - title: Git — gitattributes (eol)
    url: https://git-scm.com/docs/gitattributes#_eol
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: command
    objective: grep
    prompt: Cari baris yang mengandung `error` di `app.log`, **tanpa** membedakan huruf besar/kecil.
    answers: ['grep -i error app.log']
    patterns: ['^grep (-i|--ignore-case) error app\.log$']
    tests:
      pass: ['grep --ignore-case error app.log']
      fail: ['grep error app.log', 'grep -v error app.log', 'grep -I error app.log']
    explanation: '`-i` mencocokkan `error`, `ERROR`, `Error`. Jangan tertukar dengan `-I` besar, yang berarti abaikan file binary.'
    source: https://www.gnu.org/software/grep/manual/grep.html

  - id: a02
    tier: A
    type: command
    objective: grep
    prompt: Tampilkan baris `/etc/ssh/sshd_config` yang **bukan** komentar (tidak diawali `#`).
    answers: ['grep -v ''^#'' /etc/ssh/sshd_config']
    patterns:
      - '^grep (-v|--invert-match) \^# /etc/ssh/sshd_config$'
      - '^grep -(vE|Ev) \^\((#\|\$|\$\|#)\) /etc/ssh/sshd_config$'
    tests:
      pass: ['grep -v "^#" /etc/ssh/sshd_config', 'grep -Ev ''^(#|$)'' /etc/ssh/sshd_config']
      fail: ['grep ^# /etc/ssh/sshd_config', 'cat /etc/ssh/sshd_config', 'grep -v # /etc/ssh/sshd_config']
    explanation: '`^` = awal baris. Tanpa `^`, baris berisi `#` di mana pun ikut terbuang. Kutip polanya: `#` tanpa kutip dianggap komentar oleh shell, sehingga `grep -v # file` sebenarnya tidak membawa pola sama sekali. `-Ev ''^(#|$)''` sekalian membuang baris kosong.'
    source: https://www.gnu.org/software/grep/manual/grep.html

  - id: a03
    tier: A
    type: command
    objective: grep
    prompt: Cari `Failed password` di `/var/log/auth.log` dan tampilkan **nomor baris** setiap temuan.
    answers: ['grep -n "Failed password" /var/log/auth.log']
    patterns: ['^grep (-n|--line-number) Failed password /var/log/auth\.log$']
    tests:
      pass: ['grep --line-number ''Failed password'' /var/log/auth.log']
      fail: ['grep -c "Failed password" /var/log/auth.log', 'grep -l "Failed password" /var/log/auth.log']
    explanation: 'Pola yang berisi spasi **wajib** dikutip. Tanpa kutip, `password` dianggap nama file. Nomor baris memudahkan lompat ke konteks dengan `sed -n ''120,140p''`.'
    source: https://www.gnu.org/software/grep/manual/grep.html

  - id: a04
    tier: A
    type: command
    objective: grep
    prompt: Hitung berapa **baris** di `access.log` yang mengandung `/wp-login.php`.
    answers: ['grep -c /wp-login.php access.log']
    patterns:
      - '^grep (-F )?(-c|--count) (-F )?/wp-login(\\)?\.php access\.log$'
      - '^grep (-F )?/wp-login(\\)?\.php access\.log ?\| ?wc -l$'
      - '^grep -(cF|Fc) /wp-login(\\)?\.php access\.log$'
    tests:
      pass: ['grep -Fc /wp-login.php access.log', 'grep /wp-login.php access.log | wc -l']
      fail: ['wc -l access.log', 'grep -o /wp-login.php access.log', 'grep -vc /wp-login.php access.log']
    explanation: '`-c` menghitung **baris**, bukan kemunculan. Di lab, file berisi `error error` / `ok` / `error` → `grep -c` = 2, `grep -o error | wc -l` = 3. Di log lab ada 289 baris `/wp-login.php`.'
    source: https://www.gnu.org/software/grep/manual/grep.html

  - id: a05
    tier: A
    type: command
    objective: grep
    prompt: Cari `DB_PASSWORD` secara **rekursif** di direktori `src`, tampilkan **nama file**-nya saja.
    answers: ['grep -rl DB_PASSWORD src']
    patterns: ['^grep -(rl|lr|Rl|lR) DB_PASSWORD src/?$', '^grep (-r|-R) -l DB_PASSWORD src/?$', '^grep -l (-r|-R) DB_PASSWORD src/?$']
    tests:
      pass: ['grep -lr DB_PASSWORD src', 'grep -R -l DB_PASSWORD src/']
      fail: ['grep -r DB_PASSWORD src', 'grep -l DB_PASSWORD src', 'find src -name DB_PASSWORD']
    explanation: 'Di lab hasilnya `src/a/.env` dan `src/a/c.php`. grep rekursif ikut membaca dotfile. Batasi jenis file dengan `--include=''*.php''`, dan abaikan direktori besar dengan `--exclude-dir=vendor`.'
    source: https://www.gnu.org/software/grep/manual/grep.html

  - id: a06
    tier: A
    type: command
    objective: fields
    prompt: Dengan `cut`, tampilkan hanya **username** (kolom 1) dari `/etc/passwd`.
    answers: ['cut -d: -f1 /etc/passwd']
    patterns: ['^cut (-d ?:|--delimiter[= ]:) (-f ?1|--fields[= ]1) /etc/passwd$', '^cut (-f ?1|--fields[= ]1) (-d ?:|--delimiter[= ]:) /etc/passwd$']
    tests:
      pass: ['cut -f1 -d: /etc/passwd', 'cut -d '':'' -f 1 /etc/passwd']
      fail: ['cut -f1 /etc/passwd', 'cut -d: -f7 /etc/passwd', 'awk /etc/passwd']
    explanation: 'Delimiter default `cut` adalah TAB, jadi tanpa `-d:` seluruh baris yang tercetak. `cut` hanya mengenal **satu** karakter pemisah. Untuk kolom yang dipisah spasi bervariasi (output `ps`, `df`), pakai awk.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/cut-invocation.html

  - id: a07
    tier: A
    type: command
    objective: fields
    prompt: Dengan `awk`, tampilkan kolom pertama (alamat IP) dari setiap baris `access.log`.
    answers: ['awk ''{print $1}'' access.log']
    patterns: ['^awk \{ ?print \$1;? ?\} access\.log$']
    tests:
      pass: ['awk "{print \$1}" access.log', 'awk ''{ print $1 }'' access.log']
      fail: ['awk ''{print $0}'' access.log', 'awk print $1 access.log', 'cut -f1 access.log']
    explanation: 'awk memisah kolom pada spasi/TAB berapa pun banyaknya. `$0` = seluruh baris, `$NF` = kolom terakhir. Kutip tunggal wajib, supaya shell tidak mengganti `$1` dengan argumen skrip.'
    source: https://manpages.ubuntu.com/manpages/noble/man1/mawk.1.html

  - id: a08
    tier: A
    type: command
    objective: aggregate
    prompt: '`ips.txt` berisi satu IP per baris, **belum terurut**. Hitung berapa kali tiap IP muncul.'
    answers: ['sort ips.txt | uniq -c']
    patterns: ['^sort ips\.txt ?\| ?uniq -c$', '^cat ips\.txt ?\| ?sort ?\| ?uniq -c$', '^sort ips\.txt ?\| ?uniq --count$']
    tests:
      pass: ['cat ips.txt | sort | uniq -c']
      fail: ['uniq -c ips.txt', 'sort -u ips.txt', 'sort ips.txt | uniq']
    explanation: '`uniq` hanya menggabungkan baris kembar yang **berurutan**. Di lab, 2.000 baris dengan 6 IP berbeda menghasilkan 1.640 "grup" tanpa `sort`, dan 6 grup dengan `sort`.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/uniq-invocation.html

  - id: a09
    tier: A
    type: command
    objective: follow
    prompt: Tampilkan **50 baris terakhir** `app.log`, lalu terus ikuti baris baru yang masuk.
    answers: ['tail -n 50 -f app.log']
    patterns: ['^tail (-n ?50 (-f|-F|--follow)|(-f|-F|--follow) -n ?50|-50f|-fn ?50|-Fn ?50|-50 -f|-f -50) app\.log$']
    tests:
      pass: ['tail -f -n 50 app.log', 'tail -n50 -F app.log']
      fail: ['tail -50 app.log', 'head -n 50 app.log', 'tail -f app.log']
    explanation: 'Default `tail -f` hanya 10 baris konteks. Untuk log yang dirotasi, `-F` lebih aman (soal tier B).'
    source: https://www.gnu.org/software/coreutils/manual/html_node/tail-invocation.html

  - id: a10
    tier: A
    type: command
    objective: sed
    prompt: 'Ganti **semua** `http://` menjadi `https://` di `links.txt` dan tampilkan hasilnya ke layar (file tidak diubah). Pakai delimiter selain `/`.'
    answers: ['sed ''s#http://#https://#g'' links.txt']
    patterns: ['^sed (-e )?s([#|,@_!])http://\2https://\2g links\.txt$']
    tests:
      pass: ['sed ''s|http://|https://|g'' links.txt', 'sed -e "s#http://#https://#g" links.txt']
      fail: ['sed -i ''s#http://#https://#g'' links.txt', 'sed ''s#http://#https://#'' links.txt', 'tr http https links.txt']
    explanation: 'Karakter setelah `s` menjadi delimiter, jadi path atau URL tidak perlu di-escape (`s/http:\/\//...` sulit dibaca). Tanpa `g`, hanya kemunculan **pertama** per baris yang diganti. Tanpa `-i`, file tidak berubah.'
    source: https://www.gnu.org/software/sed/manual/sed.html#The-_0022s_0022-Command

  - id: a11
    tier: A
    type: command
    objective: aggregate
    prompt: Hitung jumlah **baris** `access.log`.
    answers: ['wc -l access.log']
    patterns: ['^(wc -l access\.log|wc -l <access\.log|cat access\.log ?\| ?wc -l|wc --lines access\.log)$']
    tests:
      pass: ['wc -l < access.log']
      fail: ['wc access.log', 'wc -c access.log', 'grep -c access.log']
    explanation: '`wc -l` menghitung karakter **newline**. Di lab, file `a\nb` tanpa newline di akhir terhitung `1`. Varian `< file` hanya mencetak angka tanpa nama file, sehingga mudah dipakai di skrip.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/wc-invocation.html

  - id: a12
    tier: A
    type: command
    objective: aggregate
    prompt: Urutkan angka di `sizes.txt` dari **terbesar** ke terkecil.
    answers: ['sort -rn sizes.txt']
    flags_any_order: true
    patterns: ['^sort (-rn|-nr|-r -n|-n -r|--numeric-sort --reverse|--reverse --numeric-sort) sizes\.txt$']
    tests:
      pass: ['sort -nr sizes.txt', 'sort -n -r sizes.txt']
      fail: ['sort -r sizes.txt', 'sort sizes.txt', 'sort -n sizes.txt']
    explanation: 'Tanpa `-n`, sort membandingkan teks: `10` < `9`. Untuk ukuran ber-sufiks (`1.2G`, `900M`) pakai `-h`, seperti di modul Filesystem.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/sort-invocation.html

  - id: a13
    tier: A
    type: command
    objective: grep
    prompt: 'Cari `10.0.0.5` di `ip.txt` sebagai **string literal**, sehingga tidak ikut cocok dengan `10a0b0c5`.'
    answers: ['grep -F 10.0.0.5 ip.txt']
    patterns: ['^grep (-F|--fixed-strings) 10\.0\.0\.5 ip\.txt$', '^fgrep 10\.0\.0\.5 ip\.txt$', '^grep 10\\\.0\\\.0\\\.5 ip\.txt$']
    tests:
      pass: ['grep --fixed-strings 10.0.0.5 ip.txt', 'grep ''10\.0\.0\.5'' ip.txt']
      fail: ['grep 10.0.0.5 ip.txt', 'grep -E 10.0.0.5 ip.txt', 'grep -f 10.0.0.5 ip.txt', 'grep -w 10.0.0.5 ip.txt']
    explanation: 'Dalam regex, `.` = karakter apa pun. Di lab, `grep -c ''10.0.0.5''` → 2 (ikut `10a0b0c5`), `grep -cF` → 1. `-F` juga lebih cepat untuk mencari banyak string tetap (`-F -f daftar.txt`).'
    source: https://www.gnu.org/software/grep/manual/grep.html

  - id: a14
    tier: A
    type: command
    objective: grep
    prompt: Cari baris yang mengandung `ERROR` **atau** `CRITICAL` di `app.log`, memakai regex extended.
    answers: ['grep -E ''ERROR|CRITICAL'' app.log']
    patterns:
      - '^(grep -E|egrep|grep --extended-regexp) (ERROR\|CRITICAL|CRITICAL\|ERROR) app\.log$'
      - '^grep -e ERROR -e CRITICAL app\.log$'
      - '^grep (ERROR\\\|CRITICAL|CRITICAL\\\|ERROR) app\.log$'
    tests:
      pass: ['grep -E "CRITICAL|ERROR" app.log', 'grep -e ERROR -e CRITICAL app.log', 'grep ''ERROR\|CRITICAL'' app.log']
      fail: ['grep ERROR app.log', 'grep -E ERROR app.log', 'grep -F ''ERROR|CRITICAL'' app.log']
    explanation: 'Di regex basic (default), `|` hanyalah karakter biasa, kecuali ditulis `\|` (ekstensi GNU). `-E` mengaktifkan `|`, `+`, `?`, `{n}`, dan `()` tanpa backslash.'
    source: https://www.gnu.org/software/grep/manual/grep.html#Basic-vs-Extended

  - id: a15
    tier: A
    type: command
    objective: jq
    prompt: '`app.json` berisi satu objek JSON per baris. Tampilkan nilai `.level` setiap baris **tanpa tanda kutip**.'
    answers: ['jq -r .level app.json']
    patterns: ['^jq (-r|--raw-output) \.level app\.json$', '^jq \.level (-r|--raw-output) app\.json$']
    tests:
      pass: ['jq -r ''.level'' app.json', 'jq --raw-output .level app.json']
      fail: ['jq .level app.json', 'jq -r level app.json', 'grep level app.json']
    explanation: 'Tanpa `-r`, jq mencetak string JSON (`"info"`). Dengan `-r`, mencetak `info`, sehingga aman dipakai di pipeline atau `if [ "$x" = info ]`. jq membaca banyak objek berturut-turut (NDJSON) tanpa opsi tambahan.'
    source: https://jqlang.org/manual/v1.7/

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: command
    objective: aggregate
    prompt: Tampilkan **5 IP** dengan request terbanyak di `access.log` beserta jumlahnya, urut dari yang terbanyak.
    answers: ['awk ''{print $1}'' access.log | sort | uniq -c | sort -rn | head -5']
    patterns: ['^(awk \{ ?print \$1;? ?\} access\.log|cut -d {1,2}-f ?1 access\.log) ?\| ?sort ?\| ?uniq -c ?\| ?sort (-rn|-nr|-r -n|-n -r) ?\| ?head (-5|-n ?5)$']
    tests:
      pass: ['awk ''{print $1}'' access.log | sort | uniq -c | sort -nr | head -n 5', 'cut -d" " -f1 access.log | sort | uniq -c | sort -rn | head -5']
      fail: ['awk ''{print $1}'' access.log | uniq -c | sort -rn | head -5', 'awk ''{print $1}'' access.log | sort | uniq -c | head -5', 'sort access.log | uniq -c | sort -rn | head -5']
    explanation: 'Pola paling sering dipakai di insiden: ambil kolom → `sort` → `uniq -c` → `sort -rn` → `head`. Di lab, juaranya `596 203.0.113.9`. `sort -rn` kedua mengurutkan berdasarkan angka hasil hitungan.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/uniq-invocation.html

  - id: b02
    tier: B
    type: command
    objective: fields
    prompt: Dengan `awk`, tampilkan baris `access.log` yang status HTTP-nya (kolom 9) **≥ 500**.
    answers: ['awk ''$9 >= 500'' access.log']
    patterns: ['^awk \$9 ?>= ?500( ?\{ ?print( \$0)?;? ?\})? access\.log$', '^awk \$9 ?> ?499 access\.log$', '^awk \$9 ?~ ?/\^5/ access\.log$']
    tests:
      pass: ['awk ''$9>=500'' access.log', 'awk ''$9 ~ /^5/'' access.log', 'awk ''$9 >= 500 {print}'' access.log']
      fail: ['grep 500 access.log', 'awk ''$9 > 500'' access.log', 'awk ''{print $9}'' access.log']
    explanation: 'Pola tanpa aksi = cetak barisnya. Di lab: 275 baris (130 × 500, 145 × 502). `grep 500` juga mencocokkan ukuran byte atau waktu yang mengandung "500" — awk membandingkan **kolom**.'
    source: https://manpages.ubuntu.com/manpages/noble/man1/mawk.1.html

  - id: b03
    tier: B
    type: command
    objective: aggregate
    prompt: Dengan `awk`, jumlahkan kolom 10 (bytes terkirim) seluruh `access.log` dan cetak totalnya sekali di akhir.
    answers: ['awk ''{s+=$10} END {print s}'' access.log']
    patterns: ['^awk \{ ?(\w+) ?\+= ?\$10;? ?\} ?END ?\{ ?print \1;? ?\} access\.log$']
    tests:
      pass: ['awk ''{ total += $10 } END { print total }'' access.log']
      fail: ['awk ''{print $10}'' access.log', 'awk ''{s+=$10; print s}'' access.log', 'wc -c access.log']
    explanation: 'Blok tanpa pola berjalan per baris, `END` berjalan sekali setelah semua baris. Di lab: `4798869`. Variabel awk otomatis bernilai 0. Rata-rata: `END {print s/NR}` (`NR` = jumlah baris).'
    source: https://www.gnu.org/software/gawk/manual/gawk.html#Using-BEGIN_002fEND

  - id: b04
    tier: B
    type: command
    objective: fields
    prompt: Dari `/etc/passwd`, tampilkan username user manusia saja (UID ≥ 1000 **dan** bukan `nobody` 65534) dengan awk.
    answers: ['awk -F: ''$3 >= 1000 && $3 < 65534 {print $1}'' /etc/passwd']
    patterns: ['^awk -F ?: \$3 ?>= ?1000 ?&& ?\$3 ?(< ?65534|<= ?65533|!= ?65534) ?\{ ?print \$1;? ?\} /etc/passwd$']
    tests:
      pass: ['awk -F'':'' ''$3>=1000 && $3!=65534 {print $1}'' /etc/passwd']
      fail: ['awk -F: ''$3 >= 1000 {print $1}'' /etc/passwd', 'cut -d: -f1 /etc/passwd', 'awk ''$3 >= 1000 {print $1}'' /etc/passwd']
    explanation: 'Tanpa `-F:`, awk memisah pada spasi, sehingga `$3` kosong. `nobody` ber-UID 65534, jadi filter `>= 1000` saja ikut mencetaknya. Di lab hasilnya: `ubuntu`.'
    source: https://manpages.ubuntu.com/manpages/noble/man1/mawk.1.html

  - id: b05
    tier: B
    type: command
    objective: aggregate
    prompt: Urutkan `/etc/passwd` berdasarkan UID (kolom 3, pemisah `:`), secara **numerik**.
    answers: ['sort -t: -k3,3n /etc/passwd']
    patterns:
      - '^sort (-t ?:|--field-separator[= ]:) (-k ?3,3n|-k ?3n|-n -k ?3(,3)?|-k ?3(,3)? -n|--key[= ]3,3n) /etc/passwd$'
      - '^sort -n (-t ?:) -k ?3(,3)? /etc/passwd$'
    tests:
      pass: ['sort -t: -n -k3 /etc/passwd', 'sort -n -t: -k3,3 /etc/passwd']
      fail: ['sort -k3 /etc/passwd', 'sort -t: -k3 /etc/passwd', 'sort -n /etc/passwd']
    explanation: '`-k3` berarti "dari kolom 3 sampai akhir baris", sedangkan `-k3,3` hanya kolom 3. Di lab, `sort -k2` pada `b 10`, `a 9`, `c 100` menghasilkan `b 10, c 100, a 9` (urutan teks). `-k2,2n` menghasilkan `a 9, b 10, c 100`.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/sort-invocation.html

  - id: b06
    tier: B
    type: command
    objective: sed
    prompt: Ubah `APP_DEBUG=true` menjadi `APP_DEBUG=false` **langsung di file** `.env`, sekaligus membuat cadangan `.env.bak`.
    answers: ['sed -i.bak ''s/APP_DEBUG=true/APP_DEBUG=false/'' .env']
    patterns: ['^sed (-i\.bak|--in-place=\.bak) (-e )?s([/#|])\^?APP_DEBUG=(true|\.\*)\3APP_DEBUG=false\3g? \.env$']
    tests:
      pass: ['sed -i.bak ''s/^APP_DEBUG=.*/APP_DEBUG=false/'' .env', 'sed --in-place=.bak ''s#APP_DEBUG=true#APP_DEBUG=false#'' .env']
      fail: ['sed ''s/APP_DEBUG=true/APP_DEBUG=false/'' .env', 'sed -i .bak ''s/APP_DEBUG=true/APP_DEBUG=false/'' .env', 'sed -i ''s/APP_DEBUG=true/APP_DEBUG=false/'' .env.bak']
    explanation: 'Sufiks **menempel** ke `-i`. `-i .bak` (dengan spasi) di GNU sed berarti `-i` tanpa backup, dengan `.bak` dibaca sebagai script, sehingga error. Di lab, `.env` menjadi `APP_ENV=production` dan `.env.bak` tetap `APP_ENV=local`. Di macOS/BSD sed, `-i` **wajib** diberi argumen (`-i ''''`).'
    source: https://www.gnu.org/software/sed/manual/sed.html#Command_002dLine-Options

  - id: b07
    tier: B
    type: command
    objective: sed
    prompt: Tampilkan **hanya** baris 100 sampai 120 dari `app.log`.
    answers: ['sed -n ''100,120p'' app.log']
    patterns: ['^sed -n (-e )?100,120p app\.log$', '^head -n ?120 app\.log ?\| ?tail -n ?21$', '^awk NR ?>= ?100 ?&& ?NR ?<= ?120 app\.log$']
    tests:
      pass: ['head -n 120 app.log | tail -n 21', 'awk ''NR>=100 && NR<=120'' app.log']
      fail: ['sed ''100,120p'' app.log', 'head -n 120 app.log', 'sed -n ''100p'' app.log']
    explanation: 'Tanpa `-n`, sed mencetak **semua** baris, dan baris 100–120 tercetak dua kali. Kombinasikan dengan `grep -n` untuk melompat ke konteks sebuah error.'
    source: https://www.gnu.org/software/sed/manual/sed.html

  - id: b08
    tier: B
    type: command
    objective: grep
    prompt: Tampilkan setiap baris `ERROR` di `app.log` beserta **2 baris sebelum** dan **3 baris sesudahnya**.
    answers: ['grep -B2 -A3 ERROR app.log']
    patterns: ['^grep (-B ?2 -A ?3|-A ?3 -B ?2|--before-context[= ]2 --after-context[= ]3|--after-context[= ]3 --before-context[= ]2) ERROR app\.log$']
    tests:
      pass: ['grep -A 3 -B 2 ERROR app.log', 'grep --before-context=2 --after-context=3 ERROR app.log']
      fail: ['grep -C3 ERROR app.log', 'grep -A2 -B3 ERROR app.log', 'grep ERROR app.log']
    explanation: '`-B` before, `-A` after, `-C` keduanya sama besar. Stack trace biasanya ada **setelah** baris error, jadi `-A` yang besar sering lebih berguna.'
    source: https://www.gnu.org/software/grep/manual/grep.html

  - id: b09
    tier: B
    type: command
    objective: pitfalls
    prompt: '`status.txt` berisi satu kode HTTP per baris. `grep -c ''^5[0-9]{2}$'' status.txt` mengembalikan **0**, padahal ada ratusan 5xx. Perbaiki perintahnya.'
    answers: ['grep -cE ''^5[0-9]{2}$'' status.txt']
    patterns:
      - '^grep (-cE|-Ec|-c -E|-E -c|-cP|-Pc|-c -P|-P -c|-c --extended-regexp|--extended-regexp -c) \^5\[0-9\]\{2\}\$ status\.txt$'
      - '^grep -c \^5\[0-9\]\\\{2\\\}\$ status\.txt$'
    tests:
      pass: ['grep -E -c ''^5[0-9]{2}$'' status.txt', 'grep -c ''^5[0-9]\{2\}$'' status.txt']
      fail: ['grep -c ''^5[0-9]{2}$'' status.txt', 'grep -cF ''^5[0-9]{2}$'' status.txt', 'grep -ce ''^5[0-9]{2}$'' status.txt']
    explanation: 'Di regex basic, `{2}` adalah teks literal "{2}". Di lab, pola serupa pada `access.log`: tanpa `-E` → 0, dengan `-E` → 275, dan `5[0-9]\{2\}` (BRE) → 275. Hasil 0 tidak menimbulkan error. Bila hitungan tampak terlalu kecil, cek apakah regex-mu memang aktif.'
    source: https://www.gnu.org/software/grep/manual/grep.html#Basic-vs-Extended

  - id: b10
    tier: B
    type: text
    objective: pitfalls
    prompt: '`sort users.txt > users.txt` membuat file menjadi **kosong**. Opsi `sort` apa yang aman untuk menulis hasilnya kembali ke file yang sama?'
    answers: ['-o', '--output', 'sort -o']
    patterns: ['^(sort )?(-o|--output)( users\.txt users\.txt)?$']
    tests:
      pass: ['sort -o users.txt users.txt']
      fail: ['-u', '>>', '-r', 'tee']
    explanation: 'Shell membuka dan **mengosongkan** `users.txt` (redirect `>`) sebelum `sort` sempat membacanya. Di lab: `0 s.txt`. `sort -o f f` aman karena sort membaca semua input dulu. Hal yang sama berlaku untuk `grep ... f > f` dan `sed ... f > f`. Pakai `sed -i` atau tulis ke file sementara lalu `mv`.'
    source: https://www.gnu.org/software/bash/manual/html_node/Redirections.html

  - id: b11
    tier: B
    type: command
    objective: follow
    prompt: '`app.log` dirotasi oleh logrotate (file lama di-rename, file baru dibuat dengan nama yang sama). Ikuti `app.log` sehingga `tail` **tetap mengikuti nama file** setelah rotasi.'
    answers: ['tail -F app.log']
    patterns: ['^tail (-F|--follow=name --retry|--retry --follow=name|--follow=name)( -n ?\d+)? app\.log$', '^tail -n ?\d+ -F app\.log$']
    tests:
      pass: ['tail --follow=name --retry app.log', 'tail -n 100 -F app.log']
      fail: ['tail -f app.log', 'tail app.log', 'tail --follow app.log', 'tail -n 100 app.log']
    explanation: 'Di lab, setelah `mv app.log app.log.1` dan file baru dibuat, `tail -f` diam (masih membaca file yang sudah di-rename). `tail -F` mencetak `''app.log'' has been replaced; following new file` lalu menampilkan baris baru. `-F` = `--follow=name --retry`.'
    source: https://www.gnu.org/software/coreutils/manual/html_node/tail-invocation.html

  - id: b12
    tier: B
    type: command
    objective: jq
    prompt: Dari `app.json` (NDJSON), tampilkan dalam format **compact** hanya objek yang `level`-nya `"error"`.
    answers: ['jq -c ''select(.level == "error")'' app.json']
    patterns: ['^jq (-c |--compact-output )?(\. ?\| ?)?select\( ?\.level ?== ?"error" ?\)( ?\| ?\.)? app\.json$']
    tests:
      pass: ['jq -c ''select(.level=="error")'' app.json', 'jq -c ''. | select(.level == "error")'' app.json']
      fail: ['jq .level app.json', 'grep error app.json', 'jq -c ''select(.level = "error")'' app.json']
    explanation: '`select(kondisi)` meneruskan input bila kondisinya true. `=` tunggal adalah **assignment** di jq — salah ketik yang klasik. `-c` mencetak satu objek per baris, cocok untuk di-pipe lagi. Gabungkan: `select(.level=="error") | {ts, msg}`.'
    source: https://jqlang.org/manual/v1.7/#select

  - id: b13
    tier: B
    type: command
    objective: jq
    prompt: Dengan jq, hitung **jumlah objek** di `app.json` (satu objek per baris) — gabungkan semua input menjadi satu array dulu.
    answers: ['jq -s length app.json']
    patterns: ['^jq (-s|--slurp) (length|\. ?\| ?length) app\.json$']
    tests:
      pass: ['jq --slurp length app.json', 'jq -s ''. | length'' app.json']
      fail: ['jq length app.json', 'jq .length app.json', 'jq -c length app.json']
    explanation: 'Tanpa `-s`, `length` dihitung per objek (= jumlah key, di lab `4` per baris). `-s` (slurp) membungkus semua input dalam array. Di lab: `5`. Agregasi lain: `jq -s ''group_by(.level) | map({level: .[0].level, n: length})''`.'
    source: https://jqlang.org/manual/v1.7/#invoking-jq

  - id: b14
    tier: B
    type: command
    objective: pitfalls
    prompt: |
      `deploy.sh` dibuat di Windows. Saat dijalankan muncul `ls: cannot access '/nope'$'\r': No such file or directory`.
      Hapus karakter CR di akhir setiap baris **langsung di file** dengan sed.
    answers: ['sed -i ''s/\r$//'' deploy.sh']
    patterns: ['^sed -i (-e )?s/\\r\$?//g? deploy\.sh$', '^dos2unix deploy\.sh$']
    tests:
      pass: ['sed -i ''s/\r//g'' deploy.sh', 'dos2unix deploy.sh']
      fail: ['sed -i ''s/\n$//'' deploy.sh', 'chmod +x deploy.sh', 'sed ''s/\r$//'' deploy.sh']
    explanation: '`$''\r''` di pesan error adalah tanda line ending CRLF. Di lab, setelah `sed -i ''s/\r$//''` skrip berjalan normal. Cegah di sumbernya dengan `.gitattributes`: `*.sh text eol=lf`, terutama bila kamu bekerja di Windows dengan `core.autocrlf=true`.'
    source: https://git-scm.com/docs/gitattributes#_eol

  - id: b15
    tier: B
    type: text
    objective: sed
    prompt: '`link.conf` adalah symlink ke `cfg/real.conf`. Setelah `sed -i ''s/80/8080/'' link.conf` (GNU sed), apa yang terjadi pada `link.conf`?'
    answers: ['jadi file biasa', 'menjadi file biasa', 'file biasa']
    patterns: ['^(jadi |menjadi |diganti (jadi |menjadi |dengan )?)?(file biasa|regular file|file reguler)( baru)?$']
    tests:
      pass: ['diganti dengan file biasa', 'regular file']
      fail: ['tetap symlink', 'target diubah', 'error', 'real.conf berubah']
    explanation: '`sed -i` menulis ke file sementara lalu me-rename-nya ke nama aslinya, sehingga **symlink itu tergantikan**. Di lab, `link.conf` menjadi `-rw-r--r--` dan `real.conf` tetap `port=80`. Dengan `--follow-symlinks`, link tetap ada dan `real.conf` yang berubah. Ini relevan untuk config yang di-symlink (mis. `sites-enabled`).'
    source: https://www.gnu.org/software/sed/manual/sed.html#Command_002dLine-Options

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: incident
    prompt: |
      Sejak jam 10:00, server web lambat dan ada laporan error. Kamu hanya punya `access.log` (format combined
      ditambah request time di kolom terakhir). Tulis pipeline analisis (satu perintah per baris) untuk menemukan
      IP paling agresif, apa yang mereka akses, path mana yang paling sering menghasilkan 5xx, dan daftar IP
      dengan lebih dari 100 request untuk diblokir.
    context: |
      $ head -n 2 access.log
      203.0.113.9 - - [20/Nov/2026:10:01:01 +0000] "GET /wp-login.php HTTP/1.1" 404 4388 "-" "curl/8.5.0" 0.035
      10.0.0.5 - - [20/Nov/2026:10:02:02 +0000] "GET / HTTP/1.1" 304 153 "-" "curl/8.5.0" 0.789
      $ wc -l access.log
      2000 access.log
    checklist:
      - id: top-ip
        label: Ranking IP (kolom 1 → sort → uniq -c → sort -rn)
        required: true
        patterns: ['(awk\s+.*\$1|cut\s+.*-f\s*1).*\|\s*sort\s*\|\s*uniq\s+-c\s*\|\s*sort\s+-\S*n']
      - id: what
        label: Melihat path yang diakses IP/scanner (kolom 7, grep -F path atau filter IP)
        patterns: ['grep\s+(-\S+\s+)*\S*(wp-login|203\.0\.113\.9)', 'awk\s+.*\$7']
      - id: status-5xx
        label: Memfilter 5xx dengan benar (awk $9 >= 500 atau grep -E)
        required: true
        patterns: ['awk\s+.*\$9\s*(>=\s*500|>\s*499|~\s*/\^5/)', 'grep\s+(-\S+\s+)*-\S*E\S*\s+.*5\[0-9\]\{2\}', 'grep\s+.*5\[0-9\]\\\{2\\\}']
      - id: per-path
        label: 5xx per path (kolom 7 → sort → uniq -c)
        patterns: ['awk\s+.*\$9.*\$7.*\|\s*sort\s*\|\s*uniq\s+-c', 'awk\s+.*\$7.*\|\s*sort\s*\|\s*uniq\s+-c']
      - id: threshold
        label: Memilih IP dengan hitungan > 100 (awk $1 > 100 {print $2})
        patterns: ['awk\s+.*\$1\s*>\s*\d+.*\$2']
      - id: no-tamper
        label: Tidak mengubah/menghapus log (bukti insiden)
        forbid: true
        patterns: ['sed\s+-i\S*\s+.*access\.log', '>\s*access\.log\s*$', 'rm\s+(-\S+\s+)*access\.log', 'truncate\s+.*access\.log']
    solution: |
      ```bash
      awk '{print $1}' access.log | sort | uniq -c | sort -rn | head -5
      #    596 203.0.113.9
      #    305 198.51.100.23
      grep -F '/wp-login.php' access.log | awk '{print $1}' | sort | uniq -c
      #    289 203.0.113.9            ← scanner WordPress (aplikasi ini Laravel)
      awk '$9 >= 500 {print $7}' access.log | sort | uniq -c | sort -rn | head -3
      #     55 /login
      #     51 /
      awk '{print $1}' access.log | sort | uniq -c | awk '$1 > 100 {print $2}' > block.txt
      awk '{s+=$NF; if ($NF>m) m=$NF} END {printf "avg=%.3f max=%s\n", s/NR, m}' access.log
      ```
      Angka di atas berasal dari log sintetis 2.000 baris di lab. Kolom format combined (dipisah spasi oleh awk):
      `$1` IP, `$4` waktu, `$7` path, `$9` status, `$10` bytes, `$NF` request time (bila ditambahkan ke
      `log_format`). Dua pengingat:
      - Saring IP internal (`10.0.0.0/8`) dari `block.txt` sebelum memblokir.
      - Analisis salinan log, jangan mengubah file aslinya, karena log itu bukti insiden.
    tests:
      pass:
        - |
          awk '{print $1}' access.log | sort | uniq -c | sort -rn | head -5
          grep -F /wp-login.php access.log | awk '{print $1}' | sort | uniq -c
          awk '$9 >= 500 {print $7}' access.log | sort | uniq -c | sort -rn | head
          awk '{print $1}' access.log | sort | uniq -c | awk '$1 > 100 {print $2}'
        - |
          cut -d' ' -f1 access.log | sort | uniq -c | sort -nr | head
          grep -E '" 5[0-9]{2} ' access.log | wc -l
          awk '{print $7}' access.log | sort | uniq -c
      fail:
        - |
          grep 500 access.log | wc -l
          sort access.log | uniq -c
        - |
          awk '{print $1}' access.log | sort | uniq -c | sort -rn
          sed -i '/203.0.113.9/d' access.log
        - |
          awk '{print $1}' access.log | uniq -c | head
          grep -c '5[0-9]{2}' access.log
    source: https://nginx.org/en/docs/http/ngx_http_log_module.html#log_format

  - id: c02
    tier: C
    type: scenario
    objective: jq
    prompt: |
      Aplikasi menulis log JSON satu objek per baris ke `app.json`. Tulis perintah jq (satu per baris) untuk:
      menampilkan error saja (timestamp + pesan, tanpa tanda kutip), request yang lebih lambat dari 100 ms,
      jumlah entri per level, dan export ke TSV untuk spreadsheet.
    context: |
      $ head -n 2 app.json
      {"ts":"2026-11-20T10:01:00Z","level":"info","msg":"m1","ctx":{"user_id":10,"ms":37}}
      {"ts":"2026-11-20T10:02:00Z","level":"error","msg":"m2","ctx":{"user_id":20,"ms":74}}
    checklist:
      - id: select-error
        label: Memfilter level error dengan select(.level == "error")
        required: true
        patterns: ['jq\s+.*select\(\s*\.level\s*==\s*"error"\s*\)']
      - id: raw
        label: Output tanpa kutip (-r) atau string interpolation
        patterns: ['jq\s+(-\S*r\S*|--raw-output)\s', '\\\(\.']
      - id: slow
        label: Filter numerik bertingkat (.ctx.ms > 100)
        patterns: ['select\(\s*\.ctx\.ms\s*>\s*100\s*\)']
      - id: aggregate
        label: Agregasi per level (-s + group_by, atau jq -r .level | sort | uniq -c)
        patterns: ['jq\s+(-\S*s\S*|--slurp)\s+.*group_by\(\s*\.level\s*\)', 'jq\s+(-\S+\s+)*\.level.*\|\s*sort\s*\|\s*uniq\s+-c']
      - id: tsv
        label: Export @tsv / @csv
        patterns: ['@tsv', '@csv']
      - id: no-assign
        label: Tidak memakai = (assignment) sebagai perbandingan
        forbid: true
        patterns: ['select\(\s*\.\w+\s*=\s*[^=]']
    solution: |
      ```bash
      jq -r 'select(.level == "error") | "\(.ts) \(.msg)"' app.json
      jq -c 'select(.ctx.ms > 100) | {ts, ms: .ctx.ms}' app.json
      jq -s -c 'group_by(.level) | map({level: .[0].level, n: length})' app.json
      #   [{"level":"error","n":2},{"level":"info","n":3}]
      jq -r '[.ts, .level, .msg, .ctx.ms] | @tsv' app.json > app.tsv
      ```
      Semua perintah diuji di lab dengan jq 1.7. Catatan:
      - Baris yang bukan JSON menghentikan jq. Di lab: `parse error: Invalid numeric literal at line 2` dengan exit code
        **5**, setelah baris valid sebelumnya tercetak. Untuk log campuran, saring dulu (`grep '^{'`) atau pakai
        `jq -R 'fromjson? // empty'`.
      - Log `journalctl -o json` dan `docker inspect` bisa diolah dengan cara yang sama.
    tests:
      pass:
        - |
          jq -r 'select(.level == "error") | "\(.ts) \(.msg)"' app.json
          jq -c 'select(.ctx.ms > 100)' app.json
          jq -s 'group_by(.level) | map({level: .[0].level, n: length})' app.json
          jq -r '[.ts, .level, .msg] | @tsv' app.json
        - |
          jq -r 'select(.level=="error") | .ts + " " + .msg' app.json
          jq -r .level app.json | sort | uniq -c
          jq -r '[.ts, .msg] | @csv' app.json
      fail:
        - |
          grep error app.json
          grep ms app.json
        - |
          jq 'select(.level = "error")' app.json
          jq -s 'group_by(.level)' app.json
          jq -r '[.ts] | @tsv' app.json
        - jq . app.json
    source: https://jqlang.org/manual/v1.7/

  - id: c03
    tier: C
    type: scenario
    objective: incident
    prompt: |
      Audit meminta login root lewat SSH dimatikan (`PermitRootLogin no`) di server Ubuntu 24.04.
      Tulis langkah (satu perintah per baris) untuk mencadangkan config, mengubah directive itu dengan sed **tanpa**
      menyentuh baris lain, memastikan tidak ada file lain yang menimpanya, memvalidasi, lalu menerapkan.
    context: |
      $ grep -nE 'PermitRootLogin|^Include' /etc/ssh/sshd_config
      12:Include /etc/ssh/sshd_config.d/*.conf
      42:#PermitRootLogin prohibit-password
      90:# the setting of "PermitRootLogin prohibit-password".
      $ grep -c yes /etc/ssh/sshd_config
      19
    checklist:
      - id: backup
        label: Cadangan (cp -a atau sed -i.bak)
        patterns: ['cp\s+(-\S+\s+)*/etc/ssh/sshd_config\s+\S+', 'sed\s+-i\.\S+']
      - id: sed
        label: sed -i yang menargetkan directive PermitRootLogin
        required: true
        patterns: ['sed\s+(-\S+\s+)*-i\S*\s+.*permitrootlogin.*no']
      - id: anchor
        label: Pola ter-anchor di awal baris (^#? ...), bukan s/yes/no/
        patterns: ['s([/#|])\^']
      - id: dropins
        label: Memeriksa sshd_config.d / konfigurasi efektif (grep -r, sshd -T)
        patterns: ['sshd_config\.d', 'sshd\s+-T']
      - id: test
        label: Validasi syntax (sshd -t)
        required: true
        patterns: ['sshd\s+-t\b']
      - id: reload
        label: Menerapkan (systemctl reload/restart ssh)
        patterns: ['systemctl\s+(reload|restart)\s+(ssh|sshd)(\.service)?\b']
      - id: no-global-yes
        label: Tidak mengganti semua yes menjadi no
        forbid: true
        patterns: ['s([/#|])yes\1no\1']
      - id: no-truncate
        label: Tidak me-redirect ke file yang sedang dibaca
        forbid: true
        patterns: ['>\s*/etc/ssh/sshd_config\s*$']
    pass_score: 0.5
    solution: |
      ```bash
      cp -a /etc/ssh/sshd_config /etc/ssh/sshd_config.bak
      sed -i -E 's/^#?[[:space:]]*PermitRootLogin[[:space:]].*/PermitRootLogin no/' /etc/ssh/sshd_config
      grep -rn PermitRootLogin /etc/ssh/sshd_config /etc/ssh/sshd_config.d/
      sshd -t && sshd -T | grep -i permitrootlogin      # nilai EFEKTIF
      systemctl reload ssh
      ```
      Diuji di lab: baris 42 menjadi `PermitRootLogin no`, baris komentar 90 tidak tersentuh, dan `sshd -T` menunjukkan
      `permitrootlogin no`. Jebakannya: sshd memakai nilai **pertama** yang ia temukan, dan `Include` ada di baris 12.
      Setelah file `/etc/ssh/sshd_config.d/50-cloud.conf` berisi `PermitRootLogin yes` ditambahkan, `sshd -T` di lab
      kembali menunjukkan `yes`, walau file utama sudah `no`. Image cloud sering membawa drop-in semacam itu.
      `sshd -t` menangkap typo (lab: `Bad configuration option: PermitRootLogn`, exit 255) sebelum reload. Jangan
      tutup sesi SSH yang sedang terbuka sampai login baru berhasil.
      `s/yes/no/g` akan mengubah 19 baris lain.
    tests:
      pass:
        - |
          cp -a /etc/ssh/sshd_config /etc/ssh/sshd_config.bak
          sed -i -E 's/^#?\s*PermitRootLogin\s.*/PermitRootLogin no/' /etc/ssh/sshd_config
          grep -r PermitRootLogin /etc/ssh/sshd_config.d/
          sshd -t
          systemctl reload ssh
        - |
          sed -i.bak 's/^#\?PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config
          sshd -t
          sshd -T | grep -i permitrootlogin
      fail:
        - |
          sed -i 's/yes/no/g' /etc/ssh/sshd_config
          sshd -t
        - |
          grep -v PermitRootLogin /etc/ssh/sshd_config > /etc/ssh/sshd_config
          echo 'PermitRootLogin no' >> /etc/ssh/sshd_config
        - |
          sed -i 's/^#PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config
          systemctl restart ssh
    source: https://man.openbsd.org/sshd_config

---

# Text Processing (grep, sed, awk, jq)

Log, config, output `ps`/`df`/`docker` — hampir semua yang kamu baca di server adalah teks. Merangkai
`grep | awk | sort | uniq` dengan cepat adalah skill troubleshooting yang paling sering dipakai saat insiden,
dan `sed -i` adalah cara tercepat (dan paling berbahaya) untuk mengubah config. Modul ini membahas alat-alatnya
beserta jebakan yang diam-diam menghasilkan angka salah atau file rusak.

> [!NOTE]
> Semua contoh diuji di container `ubuntu:24.04`: GNU grep 3.11, GNU sed 4.9, **mawk** 1.3.4 (perintah `awk` di
> Ubuntu adalah mawk, bukan gawk), coreutils 9.4, dan jq 1.7. Angka di contoh berasal dari log nginx **sintetis**
> 2.000 baris yang dibuat di lab. macOS memakai versi BSD, dengan opsi yang berbeda (mis. `sed -i ''`).

## 1. Pipeline: berpikir dalam aliran baris

```mermaid
flowchart LR
    F[access.log] --> G["grep / awk 'filter'"]
    G --> C["awk '{print $1}'<br/>cut -d' ' -f1"]
    C --> S[sort]
    S --> U[uniq -c]
    U --> R[sort -rn]
    R --> H[head]
```

Setiap alat melakukan satu hal: **pilih baris** (grep, awk pattern), **pilih kolom** (cut, awk),
**urutkan/hitung** (sort, uniq, awk), **ubah** (sed, tr), **struktur** (jq). Bangun pipeline sedikit demi
sedikit, dan lihat output setiap tahap dengan `| head`.

## 2. grep — memilih baris

| Opsi | Fungsi | Catatan dari lab |
|---|---|---|
| `-i` | Abaikan huruf besar/kecil | |
| `-v` | Baris yang **tidak** cocok | `grep -v '^#'` membuang komentar |
| `-n` | Nomor baris | |
| `-c` | Hitung **baris** yang cocok | Bukan jumlah kemunculan: 2 vs 3 |
| `-o` | Hanya bagian yang cocok | `grep -oE '([0-9]{1,3}\.){3}[0-9]{1,3}'` → IP |
| `-w` | Kata utuh | `error` tidak cocok dengan `errors`/`noerror` (3 → 1) |
| `-F` | String literal | `10.0.0.5` tidak cocok dengan `10a0b0c5` |
| `-E` | Regex extended (`\|`, `+`, `?`, `{n}`) | Tanpa `-E`, `{2}` literal → hasil 0 |
| `-A`/`-B`/`-C` | Konteks sesudah/sebelum/keduanya | |
| `-r`, `-l` | Rekursif, nama file saja | Ikut membaca dotfile (`.env`) |
| `--include`, `--exclude-dir` | Batasi jenis file / skip direktori | `--include='*.php'`, `--exclude-dir=vendor` |
| `-a` / `-I` | Perlakukan binary sebagai teks / lewati binary | Lab: `grep: b.dat: binary file matches` |

> [!WARNING]
> Kutip polamu. Tanpa kutip, shell lebih dulu mengolahnya: `*` diekspansi menjadi nama file, `#` menjadi komentar,
> dan pola berspasi terpecah menjadi nama file. `grep -v # file` tidak membawa pola sama sekali.

## 3. Kolom: `cut` dan `awk`

```bash
cut -d: -f1,7 /etc/passwd                    # root:/bin/bash
awk '{print $1, $9}' access.log              # IP dan status
awk -F: '$3 >= 1000 && $3 < 65534 {print $1}' /etc/passwd
awk '$9 >= 500' access.log                   # pola tanpa aksi = cetak baris
awk '{print $NF}' access.log                 # kolom terakhir
```

Kolom format log nginx `combined` (dipisah spasi):

```text
203.0.113.9 - - [20/Nov/2026:10:01:01 +0000] "GET /wp-login.php HTTP/1.1" 404 4388 "-" "curl/8.5.0" 0.035
$1          $2 $3 $4                    $5     $6   $7             $8       $9  $10  $11 $12        $NF
```

`cut` hanya bisa memakai satu karakter pemisah dan tidak menggabungkan spasi berturut-turut. awk memisah pada spasi
berapa pun banyaknya, sehingga lebih cocok untuk output `ps`, `df`, dan `ss`.

> [!INSIGHT]
> Di Ubuntu, `awk` adalah **mawk** (`readlink -f /usr/bin/awk` → `/usr/bin/mawk`), bukan gawk. Di lab, mawk 1.3.4
> mendukung `strftime` dan interval regex `{n}`, tetapi `gensub()` gagal (`function gensub never defined`), begitu
> juga `awk -i inplace` (`not an option: -i`). Skrip yang ditulis di laptop dengan gawk bisa gagal di server.
> Pasang `gawk` dan panggil `gawk` secara eksplisit bila butuh fitur GNU.
> Sumber: [mawk(1)](https://manpages.ubuntu.com/manpages/noble/man1/mawk.1.html)

## 4. Menghitung dan meranking

```bash
awk '{print $1}' access.log | sort | uniq -c | sort -rn | head -5   # top IP
awk '{print $9}' access.log | sort | uniq -c | sort -rn             # distribusi status
awk '$9 ~ /^5/ {print $7}' access.log | sort | uniq -c | sort -rn | head
awk '{s+=$10} END {print s}' access.log                             # total bytes
awk '{s+=$NF; if ($NF>m) m=$NF} END {printf "avg=%.3f max=%s n=%d\n", s/NR, m, NR}' access.log
```

Hasil dari log lab:

```text
    996 200          ← distribusi status
    450 404
    145 502
    140 301
    139 304
    130 500
avg=0.506 max=0.999 n=2000
```

| Jebakan | Hasil di lab |
|---|---|
| `uniq -c` tanpa `sort` | 1.640 "grup" untuk 6 IP |
| `sort -k2` vs `sort -k2,2n` | `b 10, c 100, a 9` vs `a 9, b 10, c 100` |
| `sort f > f` | File menjadi **0 byte** — pakai `sort -o f f` |
| `wc -l` pada file tanpa newline akhir | Satu baris tidak terhitung |

## 5. sed — mengubah teks

```bash
sed 's/lama/baru/g' file                         # tampilkan, file tidak berubah
sed -i.bak 's/^APP_DEBUG=.*/APP_DEBUG=false/' .env   # ubah file + cadangan
sed -n '100,120p' app.log                        # cetak rentang baris
sed '/^#/d;/^$/d' config                          # buang komentar & baris kosong
sed 's#/api/orders#/v2/orders#g' access.log      # delimiter lain untuk path
sed -i 's/\r$//' deploy.sh                       # CRLF → LF
```

| Hal | Catatan dari lab |
|---|---|
| `-i.bak` menempel | `-i .bak` (dengan spasi) bukan backup |
| `-i` pada symlink | Link **diganti file biasa**, target tidak berubah. Pakai `--follow-symlinks` |
| Anchor `^` | Pola tanpa anchor bisa mengenai komentar atau baris lain |
| macOS/BSD | `sed -i '' ...` — skrip lintas OS sering gagal di sini |

> [!DANGER]
> Jangan `sed -i 's/yes/no/g'` pada config. `sshd_config` Ubuntu mengandung **19** baris berisi `yes` (lab), dan
> mayoritasnya komentar atau directive lain. Targetkan directive-nya dengan anchor:
> `s/^#?[[:space:]]*PermitRootLogin[[:space:]].*/PermitRootLogin no/`, cadangkan dulu, lalu validasi dengan tool
> milik aplikasinya (`sshd -t`, `nginx -t`, `visudo -c`) sebelum reload.

## 6. Mengikuti log: `tail -f` vs `tail -F`

```bash
tail -n 100 -F /var/log/nginx/access.log | grep --line-buffered ' 50[0-9] '
```

Di lab, setelah `mv app.log app.log.1` dan file baru dibuat:
- `tail -f` diam, karena masih mengikuti inode lama.
- `tail -F` mencetak `'app.log' has been replaced; following new file`, lalu baris baru.

`--line-buffered` membuat grep langsung mencetak setiap baris, tidak menunggu buffer penuh, saat di-pipe.

## 7. jq — log dan API berbentuk JSON

```bash
jq -r '.level' app.json
jq -c 'select(.level == "error") | {ts, msg}' app.json
jq -r 'select(.ctx.ms > 100) | "\(.ts) \(.ctx.ms)"' app.json
jq -s 'length' app.json
jq -s -c 'group_by(.level) | map({level: .[0].level, n: length})' app.json
jq -r '[.ts, .level, .msg] | @tsv' app.json
docker inspect web | jq -r '.[0].State.Status'
journalctl -u nginx -o json | jq -r '.MESSAGE'
```

| Hal | Catatan dari lab |
|---|---|
| Tanpa `-r` | String bertanda kutip (`"info"`) |
| `length` tanpa `-s` | Jumlah key per objek (4), bukan jumlah baris |
| `=` vs `==` | `=` adalah assignment |
| Baris bukan JSON | `parse error ... at line 2`, exit **5** |

## 8. Jebakan lintas platform: CRLF

File yang disimpan di Windows memakai akhiran baris `\r\n`. Bash menganggap `\r` bagian dari argumen. Di lab:
`ls: cannot access '/nope'$'\r': No such file or directory`. Deteksi dengan `od -c file | head` (terlihat `\r \n`),
perbaiki dengan `sed -i 's/\r$//'` atau `dos2unix`, dan cegah dengan `.gitattributes`:

```text
*.sh   text eol=lf
*.conf text eol=lf
```

## 9. Ringkasan kebiasaan aman

| Kebiasaan | Kenapa |
|---|---|
| Kutip pola dan program awk/sed/jq dengan kutip tunggal | Shell tidak mengubahnya |
| `sort` sebelum `uniq` | uniq hanya menggabungkan baris yang berurutan |
| `grep -E` untuk `{n}`, `+`, `\|` | BRE diam-diam menghasilkan 0 |
| `grep -F` untuk IP, path, string tetap | `.` adalah wildcard |
| Tidak pernah `cmd file > file` | File dikosongkan sebelum dibaca |
| `sed -i.bak` + pola ter-anchor + validator aplikasi | Edit config bisa diulang dan dibatalkan |
| `tail -F` untuk log yang dirotasi | `-f` mengikuti file lama |
| Cek `sshd -T` / config efektif | Drop-in bisa menimpa file utama |
| `eol=lf` untuk skrip di repo | CRLF memecahkan skrip di Linux |
