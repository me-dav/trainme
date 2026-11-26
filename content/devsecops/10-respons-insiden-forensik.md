---
format: 1
id: sec-incident-response-forensics
course:
  slug: devsecops
  title: DevSecOps
  description: Keamanan untuk DevOps dari dua sisi — cara kerja red team dan cara blue team mendeteksi serta menutupnya. Model ancaman, hardening server & container, keamanan web, ketersediaan (anti-DDoS), deteksi, respons insiden, dan keamanan pipeline. Semua praktik di lab terisolasi milik sendiri.
title: Respons Insiden & Forensik Dasar
summary: "Deteksi menemukan serangan; sekarang kamu harus bertindak tanpa merusak bukti. Modul ini mengikuti siklus IR (PICERL): tahan penyebaran (isolasi container tanpa membunuhnya), kumpulkan bukti volatil lebih dulu sesuai urutan volatilitas (proses, perubahan file, snapshot, hash untuk chain of custody), berantas (hancurkan & redeploy dari image bersih, rotasi rahasia), pulihkan, lalu pelajari. Semua langkah forensik container dibuktikan di lab — docker diff/top/commit/export, network disconnect, pause, sha256sum."
order: 10
version: 1
status: verified
updated: '2026-11-25'
tested_on:
  - Docker Desktop (Windows), Engine 29.4.1 — container nginx:alpine "terkompromi" (webshell, user backdoor di /etc/passwd, proses beacon) di net dj-sec10
  - Forensik container diverifikasi - docker diff (artefak), docker top (proses nakal), docker network disconnect (isolasi), docker pause (freeze), docker commit + docker export + sha256sum (preservasi & chain of custody), stat (timeline)
  - Pemulihan diverifikasi - container baru dari image bersih tidak punya satu pun artefak penyerang; semua di lab milik sendiri
objectives:
  - id: phases
    text: Memahami siklus respons insiden (PICERL/NIST)
  - id: containment
    text: Menahan insiden dengan isolasi tanpa menghancurkan bukti
  - id: evidence
    text: Mengumpulkan & mempreservasi bukti sesuai urutan volatilitas + chain of custody
  - id: eradication
    text: Memberantas foothold penyerang (redeploy dari image bersih, rotasi rahasia)
  - id: timeline
    text: Membangun timeline & menemukan akar masalah (initial access)
  - id: lessons
    text: Menarik pelajaran tanpa menyalahkan (blameless) dan memperbaiki sistem
sources:
  - title: NIST SP 800-61r2 — Computer Security Incident Handling Guide
    url: https://csrc.nist.gov/pubs/sp/800/61/r2/final
  - title: SANS — Incident Handler's Handbook (PICERL)
    url: https://www.sans.org/white-papers/33901/
  - title: RFC 3227 — Guidelines for Evidence Collection and Archiving (order of volatility)
    url: https://www.rfc-editor.org/rfc/rfc3227
  - title: Docker — docker diff / export / commit
    url: https://docs.docker.com/engine/reference/commandline/diff/
  - title: MITRE ATT&CK — Indicator Removal (T1070)
    url: https://attack.mitre.org/techniques/T1070/
  - title: Google SRE — Postmortem culture (blameless)
    url: https://sre.google/sre-book/postmortem-culture/
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: phases
    prompt: "Sebutkan minimal tiga fase dari siklus respons insiden (PICERL/NIST)."
    answers: ['preparation, identification/detection, containment, eradication, recovery, lessons learned']
    patterns: ['(persiapan|preparation)', '(deteksi|identifikasi|identification|detection)', '(containment|penahanan|isolasi)', '(eradication|pemberantasan|berantas)', '(recovery|pemulihan)', '(lessons|pelajaran|evaluasi)']
    tests:
      fail: ['instalasi, konfigurasi, deploy', 'build, test, release', 'scan, patch, reboot', 'login, logout, restart']
    explanation: 'Siklus IR (SANS **PICERL** / NIST SP 800-61): **Preparation** (siap sebelum insiden), **Identification/Detection** (sadari & pastikan, modul 09), **Containment** (tahan penyebaran), **Eradication** (buang foothold penyerang), **Recovery** (pulihkan layanan bersih), **Lessons Learned** (perbaiki sistem). Urutannya penting: kamu menahan **sebelum** memberantas, dan memberantas **sebelum** memulihkan. Punya peta fase ini membuat respons tenang & terstruktur saat panik — bukan reaksi serabutan.'
    source: https://www.sans.org/white-papers/33901/

  - id: a02
    tier: A
    type: text
    objective: phases
    prompt: Mengapa containment (penahanan) dilakukan sebelum eradication (pemberantasan)?
    answers: ['hentikan dulu penyebaran/kerusakan agar tidak meluas, baru bersihkan; sekaligus jaga bukti']
    patterns: ['(hentikan|stop|tahan|cegah)[^\n]*(sebar|meluas|menyebar|kerusakan|spread)', '(batasi|kurangi)[^\n]*(dampak|blast|kerusakan)[^\n]*(dulu|sebelum)', '(jaga|preserve)[^\n]*bukti']
    tests:
      fail: ['eradication selalu lebih dulu', 'urutan tidak penting', 'langsung hapus semuanya dulu', 'recovery sebelum containment']
    explanation: 'Containment lebih dulu untuk **menghentikan penyebaran** — selama penyerang masih punya akses & konektivitas, ia bisa bergerak lateral, menanam lebih banyak backdoor, atau menghancurkan bukti. Menahan dulu (mis. memutus jaringan) membatasi kerusakan, lalu kamu bisa memberantas dengan tenang. Jika kamu langsung "bersih-bersih" tanpa menahan, penyerang bisa kembali atau meluas saat kamu bekerja. Urutan PICERL melindungi dari reaksi gegabah yang justru memperparah.'
    source: https://csrc.nist.gov/pubs/sp/800/61/r2/final

  - id: a03
    tier: A
    type: text
    objective: containment
    prompt: Apa tujuan utama fase containment?
    answers: ['membatasi penyebaran & kerusakan sambil mempertahankan bukti']
    patterns: ['(batasi|membatasi|cegah|tahan|kurangi)[^\n]*(sebar|menyebar|meluas|kerusakan|dampak)', '(isolasi|isolate)[^\n]*(sistem|container|host)', '(jaga|pertahan|preserve)[^\n]*bukti']
    tests:
      fail: ['menghapus semua log', 'mematikan seluruh perusahaan', 'mengabaikan serangan', 'mempercepat server']
    explanation: 'Containment = **membatasi penyebaran & kerusakan** tanpa merusak bukti. Caranya: isolasi sistem terdampak (putus jaringan/segmentasi), nonaktifkan akun yang disalahgunakan, blokir IP C2, cabut token yang bocor. Tujuannya mencegah penyerang meluas (lateral movement) dan membeli waktu untuk investigasi. Keseimbangan penting: cukup agresif untuk menghentikan serangan, tapi hati-hati agar tidak memicu penyerang menghancurkan data atau kehilangan bukti volatil.'
    source: https://csrc.nist.gov/pubs/sp/800/61/r2/final

  - id: a04
    tier: A
    type: command
    objective: containment
    prompt: Isolasi container terkompromi `web` dari jaringan `prod` **tanpa membunuhnya** (putus konektivitas, proses tetap hidup).
    answers: ['docker network disconnect prod web']
    patterns: ['^docker network disconnect[ \t]+\S+[ \t]+\S+']
    tests:
      pass:
        - 'docker network disconnect prod web'
        - 'docker network disconnect -f prod web'
      fail:
        - 'docker rm -f web'
        - 'docker stop web'
        - 'docker network rm prod'
    explanation: '`docker network disconnect <net> <container>` memutus container dari jaringan tapi **membiarkannya berjalan** — konektivitas (termasuk ke C2 & peer) terputus, sementara proses, memori, dan state tetap hidup untuk diperiksa. Di lab, setelah disconnect container tetap `Up` tetapi `wget` ke peer gagal ("NO (isolated)"). Ini containment ideal: hentikan penyebaran tanpa kehilangan bukti volatil. Lawannya `docker stop/rm` membunuh proses & membuang memori — bukti hilang.'
    source: https://docs.docker.com/engine/reference/commandline/network_disconnect/

  - id: a05
    tier: A
    type: text
    objective: containment
    prompt: Mengapa sebaiknya TIDAK langsung `docker rm -f` (hapus) container yang terkompromi?
    answers: ['menghapusnya membuang bukti volatil — proses berjalan, memori, koneksi jaringan, state runtime']
    patterns: ['(hilang|buang|musnah|lenyap)[^\n]*(bukti|memori|proses|state|volatil)', '(bukti|memori|proses|koneksi)[^\n]*(volatil|hilang|tak bisa)', 'tidak bisa (diperiksa|investigasi|forensik)']
    tests:
      fail: ['menghapus selalu paling aman', 'tidak ada ruginya menghapus', 'bukti tetap ada setelah dihapus', 'memori tersimpan otomatis']
    explanation: 'Menghapus/mematikan container membuang semua yang **volatil**: proses yang sedang berjalan (beacon/reverse shell), isi memori (kredensial, payload, kunci yang hanya ada di RAM), koneksi jaringan aktif (ke mana C2 terhubung), dan state runtime. Setelah `rm`, itu semua lenyap dan tak bisa dipulihkan. Isolasi dulu (a04), kumpulkan bukti volatil (a06), baru hancurkan untuk eradication. "Panik lalu hapus" adalah kesalahan IR klasik yang menghapus jejak sendiri.'
    source: https://www.rfc-editor.org/rfc/rfc3227

  - id: a06
    tier: A
    type: text
    objective: evidence
    prompt: Prinsip "order of volatility" (RFC 3227) mengatakan kamu harus mengumpulkan bukti dengan urutan bagaimana?
    answers: ['yang paling volatil lebih dulu — memori/proses/koneksi sebelum disk/file']
    patterns: ['(paling )?volatil[^\n]*(dulu|lebih dulu|pertama)', '(memori|ram|proses|koneksi)[^\n]*(sebelum|dulu)[^\n]*(disk|file|storage)', 'cepat hilang[^\n]*dulu']
    tests:
      fail: ['file di disk dulu', 'urutan acak', 'yang paling tidak penting dulu', 'backup dulu baru memori']
    explanation: 'Order of volatility: kumpulkan data yang **paling cepat hilang lebih dulu**. Urutan kira-kira: register/cache → **memori (RAM)** → **proses & koneksi jaringan** → state kernel/routing → **berkas sementara** → **disk/filesystem** → arsip/backup. Memori hilang saat power-off; file di disk relatif awet. Jadi tangkap proses (`docker top`), koneksi, dan memori sebelum mengekspor filesystem. Dokumentasikan setiap langkah & waktunya. Melanggar urutan ini = kehilangan bukti paling berharga.'
    source: https://www.rfc-editor.org/rfc/rfc3227

  - id: a07
    tier: A
    type: command
    objective: evidence
    prompt: Tampilkan **perubahan filesystem** yang dilakukan di dalam container `web` dibanding image aslinya (temukan file yang ditanam/diubah).
    answers: ['docker diff web']
    patterns: ['^docker diff[ \t]+\S+']
    tests:
      pass:
        - 'docker diff web'
      fail:
        - 'docker inspect web'
        - 'diff web'
        - 'docker logs web'
    explanation: '`docker diff <container>` membandingkan filesystem container dengan image dasarnya dan menandai: **A** (Added/ditambah), **C** (Changed/diubah), **D** (Deleted/dihapus). Di lab, ia langsung mengungkap artefak penyerang: `A /usr/share/nginx/html/shell.php` (webshell), `C /etc/passwd` (user backdoor ditambahkan), `A /tmp/.beacon.sh` (skrip persistence). Ini salah satu alat forensik container paling cepat & kuat — ia menunjukkan persis apa yang berubah sejak container dibuat dari image bersih.'
    source: https://docs.docker.com/engine/reference/commandline/diff/

  - id: a08
    tier: A
    type: command
    objective: evidence
    prompt: Lihat daftar **proses yang sedang berjalan** di dalam container `web` (untuk menemukan proses nakal).
    answers: ['docker top web']
    patterns: ['^docker top[ \t]+\S+']
    tests:
      pass:
        - 'docker top web'
        - 'docker top web -o pid,args'
      fail:
        - 'docker ps'
        - 'top web'
        - 'docker stats web'
    explanation: '`docker top <container>` menampilkan proses yang berjalan di dalam container (dilihat dari host). Di lab, ia mengungkap proses nakal `/bin/sh /tmp/.beacon.sh` + `sleep 60` di antara proses nginx yang sah — persis jenis "proses tak dikenal" yang menjadi IOC. Ini bukti **volatil** (hilang saat container dimatikan), jadi tangkap lebih dulu. `docker top` membaca dari host sehingga tak bisa ditipu oleh `ps` yang mungkin sudah dimodifikasi penyerang di dalam container.'
    source: https://docs.docker.com/engine/reference/commandline/top/

  - id: a09
    tier: A
    type: command
    objective: evidence
    prompt: Buat **snapshot** container terkompromi `web` menjadi sebuah image (`evidence:incident`) untuk dianalisis nanti.
    answers: ['docker commit web evidence:incident']
    patterns: ['^docker commit[ \t]+\S+[ \t]+\S+']
    tests:
      pass:
        - 'docker commit web evidence:incident'
        - 'docker commit -p web evidence:incident'
      fail:
        - 'docker save web'
        - 'docker tag web evidence:incident'
        - 'docker commit web'
    explanation: '`docker commit <container> <image>` membekukan state filesystem container menjadi image yang bisa disimpan, dipindah, dan diperiksa tanpa menyentuh yang asli. Di lab, `docker commit dj-sec10-web dj-sec10/evidence-snapshot:incident` membuat salinan bukti. `-p` (default) menjeda container saat commit agar konsisten. Image ini mempertahankan layer & metadata; pasangannya `docker export` (a10) menghasilkan tar filesystem datar. Simpan snapshot sebelum eradication menghancurkan aslinya.'
    source: https://docs.docker.com/engine/reference/commandline/commit/

  - id: a10
    tier: A
    type: command
    objective: evidence
    prompt: Ekspor **seluruh filesystem** container `web` ke sebuah arsip tar (`evidence.tar`) untuk analisis offline.
    answers: ['docker export web -o evidence.tar']
    patterns: ['^docker export[ \t].*\S+', '^docker export[ \t]+\S+[ \t]*>[ \t]*\S+']
    tests:
      pass:
        - 'docker export web -o evidence.tar'
        - 'docker export web > evidence.tar'
        - 'docker export -o evidence.tar web'
      fail:
        - 'docker save web -o evidence.tar'
        - 'tar -cf evidence.tar web'
        - 'docker cp web evidence.tar'
    explanation: '`docker export <container>` menghasilkan tar filesystem container yang datar (flattened) — cocok untuk analisis offline: ekstrak di mesin terpisah, pindai webshell/malware, periksa file tanpa menyentuh host yang terkompromi. Di lab, tar 62M berisi `usr/share/nginx/html/shell.php` dan `tmp/.beacon.sh`, dan isi webshell (`<?php system($_GET[c]); ?>`) bisa dipulihkan dari tar. Beda dengan `docker save` (yang mengekspor *image* beserta layer, bukan filesystem container berjalan).'
    source: https://docs.docker.com/engine/reference/commandline/export/

  - id: a11
    tier: A
    type: command
    objective: evidence
    prompt: Hitung hash kriptografis dari file bukti `evidence.tar` untuk membuktikan integritasnya (chain of custody).
    answers: ['sha256sum evidence.tar']
    patterns: ['^sha256sum[ \t]+\S+', '^shasum[ \t]+-a[ \t]+256[ \t]+\S+', '^sha512sum[ \t]+\S+']
    tests:
      pass:
        - 'sha256sum evidence.tar'
        - 'shasum -a 256 evidence.tar'
      fail:
        - 'md5 evidence.tar'
        - 'ls -l evidence.tar'
        - 'cat evidence.tar'
    explanation: 'Hash kriptografis (mis. `sha256sum`) menghasilkan sidik jari unik file bukti. Catat hash saat pengumpulan; siapa pun dapat memverifikasi nanti bahwa file **tidak berubah** sejak dikumpulkan — inti dari **chain of custody**. Di lab, `sha256sum evidence.tar` → `3c5ea43e...`. Hindari MD5/SHA-1 untuk bukti (rawan kolisi); pakai SHA-256+. Simpan hash terpisah dari file (mis. di catatan insiden) agar tak bisa diubah bersama filenya.'
    source: https://www.rfc-editor.org/rfc/rfc3227

  - id: a12
    tier: A
    type: text
    objective: evidence
    prompt: Apa itu "chain of custody" dalam konteks bukti insiden?
    answers: ['catatan siapa menangani bukti, kapan, dan bagaimana — menjamin bukti utuh & bisa dipercaya']
    patterns: ['(catatan|rekam|dokumentasi)[^\n]*(siapa|kapan|penanganan|handle)[^\n]*(bukti)', 'bukti[^\n]*(utuh|tidak berubah|terpercaya|sah)', '(jejak|lacak)[^\n]*(bukti|penanganan)']
    tests:
      fail: ['cara menghapus bukti', 'urutan reboot server', 'daftar user yang login', 'rantai suplai software']
    explanation: 'Chain of custody adalah catatan terdokumentasi tentang **siapa** yang mengumpulkan/menangani/menyimpan bukti, **kapan**, dan **bagaimana** — sejak dikumpulkan sampai dianalisis. Didukung hash (a11), timestamp, dan akses terbatas. Tujuannya: membuktikan bukti **tidak dirusak** dan bisa dipercaya (penting bila ada tindakan hukum). Bahkan untuk insiden internal, disiplin ini menjaga kesimpulan investigasi tetap solid. Bukti yang penanganannya tak tercatat mudah dipertanyakan.'
    source: https://www.rfc-editor.org/rfc/rfc3227

  - id: a13
    tier: A
    type: text
    objective: eradication
    prompt: Untuk container/infrastruktur immutable, cara eradication yang benar atas host terkompromi adalah?
    answers: ['hancurkan dan redeploy dari image/konfigurasi bersih yang diketahui baik, bukan "membersihkan" di tempat']
    patterns: ['(hancurkan|hapus|buang|ganti|destroy)[^\n]*(redeploy|deploy ulang|image bersih|dari awal|known.?good)', '(redeploy|bangun ulang|recreate)[^\n]*(image|bersih|known.?good)', '(bukan|jangan)[^\n]*(bersihkan|clean)[^\n]*(tempat|in.?place)', 'cattle not pets|sapi bukan']
    tests:
      fail: ['hapus file jahat satu per satu lalu lanjutkan', 'reboot saja sudah cukup', 'biarkan container yang sama berjalan', 'ganti password admin saja']
    explanation: 'Dengan infrastruktur immutable, jangan mencoba "membersihkan" container terkompromi di tempat — kamu tak pernah yakin sudah membuang **semua** foothold (backdoor tersembunyi, cron, binari ter-trojan). Sebaliknya: **hancurkan** container itu dan **redeploy dari image/IaC bersih** yang diketahui baik. Di lab, container baru dari `nginx:alpine` asli punya **0** artefak penyerang (tidak ada shell.php, tidak ada user backdoor). "Cattle, not pets": ganti, jangan rawat yang sakit. Bukti tetap aman di snapshot/tar (a09/a10).'
    source: https://csrc.nist.gov/pubs/sp/800/61/r2/final

  - id: a14
    tier: A
    type: text
    objective: eradication
    prompt: Setelah breach, mengapa semua rahasia/kredensial yang mungkin terpapar harus dirotasi?
    answers: ['anggap penyerang sudah mencurinya; kredensial lama bisa dipakai untuk masuk kembali']
    patterns: ['(anggap|asumsi|mungkin)[^\n]*(curi|dicuri|bocor|dikuasai)[^\n]*(penyerang|attacker)?', '(masuk|akses) (lagi|kembali|ulang)|re-?entry|persistence', 'kredensial lama[^\n]*(tak aman|bahaya|dipakai)']
    tests:
      fail: ['kredensial pasti aman setelah breach', 'tidak perlu rotasi', 'cukup ganti satu password', 'rahasia tidak terpengaruh breach']
    explanation: 'Asumsikan setiap rahasia yang mungkin terjangkau penyerang **sudah dicuri** (assume breach): password, API key, token, kunci SSH/TLS, secret DB. Jika tidak dirotasi, penyerang bisa memakainya untuk **masuk kembali** meski kamu sudah membangun ulang sistem — eradication jadi sia-sia. Rotasi semuanya (bersambung dengan modul 04), cabut sesi/token aktif, dan periksa apakah kredensial dipakai di tempat lain. Ini sebabnya `*_FILE`/secret manager mempermudah rotasi massal saat insiden.'
    source: https://csrc.nist.gov/pubs/sp/800/61/r2/final

  - id: a15
    tier: A
    type: text
    objective: lessons
    prompt: Apa tujuan fase "lessons learned" / post-incident review?
    answers: ['mempelajari akar masalah dan memperbaiki sistem/proses agar insiden serupa tak terulang']
    patterns: ['(belajar|pelajari|evaluasi)[^\n]*(akar|penyebab|proses)', '(perbaiki|cegah|tingkatkan)[^\n]*(sistem|proses|kontrol)', '(tidak|tak) terulang|agar tidak terjadi lagi']
    tests:
      fail: ['mencari siapa yang harus dipecat', 'melupakan insiden secepatnya', 'menyembunyikan dari manajemen', 'tidak ada gunanya']
    explanation: 'Lessons learned mengubah insiden jadi perbaikan: apa akar masalahnya, apa yang berjalan/gagal dalam respons, kontrol/proses apa yang harus diperbaiki agar tak terulang. Hasilnya daftar tindakan konkret (tambal celah, perbaiki deteksi, update runbook) dengan pemilik & tenggat. Lakukan **blameless** (b10) — fokus pada sistem, bukan menyalahkan orang — agar orang jujur dan akar masalah sebenarnya muncul. Insiden yang tak dievaluasi pasti berulang.'
    source: https://sre.google/sre-book/postmortem-culture/

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: command
    objective: containment
    prompt: "**Bekukan** semua proses di dalam container `web` (hentikan sementara tanpa mematikan) untuk menangkap state konsisten."
    answers: ['docker pause web']
    patterns: ['^docker pause[ \t]+\S+']
    tests:
      pass:
        - 'docker pause web'
      fail:
        - 'docker stop web'
        - 'docker kill web'
        - 'docker rm web'
    explanation: '`docker pause <container>` membekukan semua proses via **cgroup freezer** (SIGSTOP tingkat cgroup) — proses berhenti seketika tanpa dimatikan, state RAM & proses tetap utuh. Di lab, statusnya jadi `Up (Paused)`, lalu `docker unpause` melanjutkannya. Berguna untuk "memotret" state di satu titik (mencegah penyerang mengubah apa pun selama kamu mengumpulkan bukti). Beda dengan `stop/kill` yang **mengakhiri** proses (bukti volatil hilang). Pasangkan dengan `network disconnect` (a04) untuk isolasi.'
    source: https://docs.docker.com/engine/reference/commandline/pause/

  - id: b02
    tier: B
    type: text
    objective: containment
    prompt: Bandingkan singkat `docker network disconnect`, `docker pause`, dan `docker rm` sebagai aksi terhadap container terkompromi.
    answers: ['disconnect: putus jaringan tapi proses hidup; pause: bekukan proses tapi state utuh; rm: hancurkan & buang bukti']
    patterns: ['disconnect[^\n]*(jaringan|network|konektivitas)[^\n]*(hidup|jalan|berjalan)', 'pause[^\n]*(bekukan|freeze|hentikan)[^\n]*(state|proses)', 'rm[^\n]*(hancur|hapus|buang)[^\n]*(bukti|state|memori)']
    tests:
      fail: ['ketiganya sama saja', 'rm paling aman untuk bukti', 'pause menghapus container', 'disconnect mematikan proses']
    explanation: 'Tiga aksi, tiga efek: **`network disconnect`** memutus konektivitas (hentikan C2/lateral) tapi **proses & memori tetap hidup** untuk diperiksa; **`pause`** membekukan proses (freeze state di satu titik) tapi tidak mematikan; **`rm/stop/kill`** mengakhiri & membuang state volatil. Untuk IR: **disconnect dan/atau pause dulu** (tahan + preserve), kumpulkan bukti, baru `rm` saat eradication. Memilih `rm` sebagai langkah pertama = kehilangan bukti paling berharga. Ketiganya punya tempat, tapi urutannya menentukan.'
    source: https://csrc.nist.gov/pubs/sp/800/61/r2/final

  - id: b03
    tier: B
    type: text
    objective: evidence
    prompt: Sebutkan dua jenis data volatil yang harus ditangkap sebelum sistem dimatikan.
    answers: ['proses yang berjalan, koneksi jaringan aktif, isi memori (ram), user yang login, file yang terbuka']
    patterns: ['(proses|process)[^\n]*(jaringan|koneksi|memori|ram|login|terbuka)', '(memori|ram)[^\n]*(proses|koneksi|jaringan)', 'koneksi (jaringan|aktif)[^\n]*(proses|memori|login)', '(proses berjalan|open files|user login)']
    tests:
      fail: ['file backup lama', 'dokumentasi di wiki', 'kode sumber di git', 'logo perusahaan']
    explanation: 'Data volatil yang hilang saat power-off: **proses yang berjalan** (`docker top`/`ps`), **koneksi jaringan aktif** (ke mana penyerang terhubung — `netstat`/`ss`), **isi memori/RAM** (kredensial, payload, kunci yang hanya ada di memori), **user yang login**, dan **file/soket yang terbuka**. Tangkap ini **sebelum** menyentuh disk atau mematikan (order of volatility, a06). Memori adalah harta forensik — banyak malware hanya ada di RAM (fileless). Di container, `docker top` + `docker diff` menangkap proses & perubahan file tanpa mengganggu.'
    source: https://www.rfc-editor.org/rfc/rfc3227

  - id: b04
    tier: B
    type: command
    objective: timeline
    prompt: Lihat **waktu modifikasi (mtime)** file `/tmp/.beacon.sh` untuk menempatkannya pada timeline insiden.
    answers: ['stat /tmp/.beacon.sh']
    patterns: ['^stat[ \t].*\.beacon\.sh', '^stat[ \t]+\S+']
    tests:
      pass:
        - 'stat /tmp/.beacon.sh'
        - 'stat -c %y /tmp/.beacon.sh'
      fail:
        - 'cat /tmp/.beacon.sh'
        - 'rm /tmp/.beacon.sh'
        - 'ls /tmp'
    explanation: '`stat <file>` menampilkan metadata termasuk mtime (modify), atime (access), ctime (change) — berguna menempatkan artefak pada **timeline**. Di lab, `stat` menunjukkan webshell & beacon dibuat pada `2026-11-25 15:26:37`, sehingga kamu tahu kapan foothold ditanam dan bisa mencari aktivitas lain di sekitar waktu itu di log. Hati-hati: timestamp bisa dipalsukan penyerang (anti-forensik "timestomping"), jadi korelasikan dengan log server & bukti lain, jangan percaya mtime sendirian.'
    source: https://www.rfc-editor.org/rfc/rfc3227

  - id: b05
    tier: B
    type: text
    objective: timeline
    prompt: Bagaimana cara membangun timeline insiden, dan kenapa sinkronisasi waktu (NTP) penting untuknya?
    answers: ['gabungkan log + mtime file lintas sistem berdasarkan waktu; ntp memastikan timestamp antar-host konsisten untuk diurutkan']
    patterns: ['(gabung|korelasi|satukan|urutkan)[^\n]*(log|mtime|event|bukti)[^\n]*(waktu|timeline)', '(ntp|sinkron(isasi)? waktu)[^\n]*(konsisten|selaras|sama|urut|timestamp|akurat)', 'timestamp[^\n]*(konsisten|selaras|sama)[^\n]*(host|sistem|urut)']
    tests:
      fail: ['timeline tidak perlu waktu', 'jam berbeda tidak masalah', 'cukup tebak urutannya', 'ntp tidak berhubungan']
    explanation: 'Timeline dibangun dengan **mengurutkan event dari banyak sumber berdasarkan waktu**: log akses/auth (modul 09), mtime file (b04), log sistem, log cloud. Ini merekonstruksi alur serangan: kapan masuk, apa yang diakses, kapan menanam backdoor. **NTP** krusial — jika jam antar-host tidak selaras, event dari server A dan B tak bisa diurutkan dengan benar, dan timeline jadi kacau. Karena itu NTP (dibahas di modul 09) adalah prasyarat forensik, bukan sekadar kerapian.'
    source: https://www.rfc-editor.org/rfc/rfc3227

  - id: b06
    tier: B
    type: text
    objective: timeline
    prompt: Mengapa menemukan "initial access vector" (cara penyerang pertama masuk) lebih penting daripada sekadar menghapus artefak yang terlihat?
    answers: ['kalau jalan masuknya tidak ditutup, penyerang bisa masuk lagi lewat cara yang sama meski artefak dibersihkan']
    patterns: ['(jalan masuk|vektor|celah|cara masuk)[^\n]*(tidak|belum) ditutup[^\n]*(masuk lagi|kembali)', '(masuk|kembali) (lagi|ulang)[^\n]*(cara|celah|jalan) (sama|yang sama)', '(akar|root cause)[^\n]*(bukan|lebih dari)[^\n]*(gejala|artefak)']
    tests:
      fail: ['artefak yang terlihat sudah cukup dihapus', 'initial access tidak penting', 'penyerang tidak akan kembali', 'gejala sama dengan akar masalah']
    explanation: 'Menghapus webshell & backdoor menghilangkan **gejala**, tapi jika **jalan masuknya** (SQLi, dependency rentan, kredensial bocor, port terbuka) tidak ditutup, penyerang tinggal masuk lagi lewat cara yang sama — dan menanam backdoor baru. Investigasi harus menemukan **initial access vector** (akar masalah) dan menutupnya: itulah eradication sejati. Di modul ini artefak lab (shell.php, beacon) adalah gejala; yang harus dicari adalah "lewat mana mereka menaruhnya?". Memperbaiki akar > membersihkan gejala.'
    source: https://csrc.nist.gov/pubs/sp/800/61/r2/final

  - id: b07
    tier: B
    type: text
    objective: evidence
    prompt: Apa beda `docker commit` dan `docker export` sebagai cara mempreservasi bukti container?
    answers: ['commit menghasilkan image (dengan layer & konfigurasi); export menghasilkan tar filesystem datar']
    patterns: ['commit[^\n]*(image|layer|konfigurasi|metadata)', 'export[^\n]*(tar|filesystem|datar|flat)', '(image|layer)[^\n]*(vs|versus|sedangkan)[^\n]*(tar|filesystem|datar)']
    tests:
      fail: ['keduanya identik', 'export menghasilkan image berlapis', 'commit menghapus container', 'keduanya hanya mengekspor log']
    explanation: '`docker commit` membuat **image** dari container — mempertahankan layer dan metadata/konfigurasi (env, cmd), bisa dijalankan ulang di sandbox untuk analisis dinamis. `docker export` menghasilkan **tar filesystem datar** (satu lapisan, tanpa history) — ringan untuk analisis statis offline (ekstrak, scan, grep). Untuk forensik sering pakai **keduanya**: commit untuk analisis perilaku di sandbox, export untuk pemeriksaan file. Keduanya dibuat **sebelum** container dihancurkan, dan di-hash untuk chain of custody.'
    source: https://docs.docker.com/engine/reference/commandline/commit/

  - id: b08
    tier: B
    type: text
    objective: eradication
    prompt: Sebelum mengembalikan sistem yang sudah dibangun ulang ke produksi, apa yang harus dipastikan?
    answers: ['sistem benar-benar bersih dari foothold, akar masalah sudah ditutup, dan dipantau ketat untuk reinfeksi']
    patterns: ['(bersih|clean)[^\n]*(foothold|backdoor|artefak|penyerang)', '(akar|celah|vektor)[^\n]*(ditutup|diperbaiki|ditambal)', '(pantau|monitor|awasi)[^\n]*(reinfeksi|kembali|ulang|ketat)']
    tests:
      fail: ['langsung online tanpa cek', 'tidak perlu dipantau', 'biarkan celah lama terbuka', 'cukup reboot lalu online']
    explanation: 'Sebelum recovery penuh: pastikan sistem benar-benar **bersih** (dari image known-good, bukan hasil "dibersihkan"), **akar masalah sudah ditutup** (initial access vector, b06) agar tidak langsung dibobol lagi, kredensial sudah dirotasi (a14), dan **pantau ketat untuk reinfeksi** — penyerang sering mencoba kembali, dan backdoor yang terlewat bisa aktif. Kembalikan layanan bertahap sambil mengawasi sinyal (modul 09). Terburu-buru online tanpa verifikasi = mengundang insiden ulang dalam hitungan jam.'
    source: https://csrc.nist.gov/pubs/sp/800/61/r2/final

  - id: b09
    tier: B
    type: text
    objective: phases
    prompt: Fase "Preparation" dilakukan sebelum insiden terjadi. Sebutkan dua hal yang disiapkan di fase ini.
    answers: ['rencana ir & runbook, kontak/peran/eskalasi, backup teruji, logging & deteksi siap, akses untuk isolasi cepat']
    patterns: ['(rencana|plan|runbook|playbook)[^\n]*(ir|insiden|respons)?', '(kontak|peran|role|eskalasi|tim)', 'backup[^\n]*(teruji|offline|siap)', '(logging|deteksi|monitoring)[^\n]*(siap|ada)', 'tabletop|latihan|simulasi']
    tests:
      fail: ['tidak perlu persiapan', 'improvisasi saat kejadian', 'hapus semua backup', 'matikan logging']
    explanation: 'Preparation menentukan seberapa baik respons saat panik: **rencana IR + runbook** (langkah per jenis insiden), **peran/kontak/eskalasi** (siapa melakukan apa, siapa dihubungi), **backup teruji & offline** (untuk recovery/ransomware), **logging & deteksi yang sudah jalan** (modul 09 — tak bisa menyelidiki tanpa log), dan **akses/izin** untuk cepat mengisolasi & snapshot. Plus latihan (tabletop). Insiden bukan saat yang tepat untuk pertama kali memikirkan ini — persiapan adalah fase terpenting yang paling sering diabaikan.'
    source: https://www.sans.org/white-papers/33901/

  - id: b10
    tier: B
    type: text
    objective: lessons
    prompt: Apa itu "blameless postmortem" dan kenapa penting?
    answers: ['evaluasi yang fokus memperbaiki sistem/proses, bukan menyalahkan individu — agar orang jujur & akar masalah muncul']
    patterns: ['(tanpa|bukan|tidak)[^\n]*(salah|blame|menyalahkan)[^\n]*(orang|individu|personal)', 'fokus[^\n]*(sistem|proses|perbaikan)', '(jujur|terbuka|aman)[^\n]*(akar|cerita|melapor)']
    tests:
      fail: ['mencari orang untuk dipecat', 'menghukum yang membuat error', 'menyembunyikan penyebab', 'menyalahkan tim lain']
    explanation: 'Blameless postmortem berfokus pada **mengapa sistem/proses mengizinkan insiden**, bukan **siapa** yang salah. Alasannya praktis: bila orang takut disalahkan, mereka menyembunyikan detail — dan kamu kehilangan akar masalah sebenarnya. Dengan budaya blameless, orang jujur menceritakan apa yang terjadi, sehingga kamu bisa memperbaiki penyebab sistemik (kontrol, otomasi, desain) yang akan mencegah seluruh kelas insiden. Kesalahan manusia hampir selalu gejala dari sistem yang membiarkannya terjadi.'
    source: https://sre.google/sre-book/postmortem-culture/

  - id: b11
    tier: B
    type: text
    objective: evidence
    prompt: Apa saja "indicators of compromise" (IOC) yang terlihat pada container terkompromi di lab ini?
    answers: ['webshell yang ditanam, user backdoor di /etc/passwd, proses beacon tak dikenal, perubahan file tak terduga']
    patterns: ['webshell|shell\.php|file (ditanam|asing)', 'user backdoor|/etc/passwd|akun (baru|asing)', 'proses[^\n]*(nakal|asing|tak dikenal|beacon)', 'koneksi keluar[^\n]*(tak terduga|c2)|perubahan file']
    tests:
      fail: ['penggunaan cpu normal', 'login user sah', 'response 200 biasa', 'request ke homepage']
    explanation: 'IOC = artefak yang menandakan kompromi. Di lab: **webshell** (`/usr/share/nginx/html/shell.php`), **user backdoor** uid 0 di `/etc/passwd`, **proses beacon tak dikenal** (`/tmp/.beacon.sh`), dan **perubahan file tak terduga** (terlihat via `docker diff`). IOC lain umumnya: koneksi keluar ke IP C2, file di `/tmp` yang disembunyikan (nama berawalan titik), cron/systemd baru, binari ter-trojan. Kumpulkan IOC lalu **buru** (threat hunt) di sistem lain — jika satu host punya IOC itu, host lain mungkin juga.'
    source: https://attack.mitre.org/techniques/T1070/

  - id: b12
    tier: B
    type: text
    objective: containment
    prompt: Di Kubernetes/cloud, prinsip containment yang sama diterapkan dengan mekanisme apa?
    answers: ['cordon/drain node, isolasi pod via network policy, snapshot disk/volume, cabut iam/role']
    patterns: ['cordon|drain', 'network ?policy|isolasi pod', 'snapshot[^\n]*(disk|volume|ebs)', '(cabut|revoke|nonaktif)[^\n]*(iam|role|token|service account)']
    tests:
      fail: ['tidak ada cara di kubernetes', 'matikan seluruh cluster', 'hapus semua pod langsung', 'prinsipnya berbeda total']
    explanation: 'Prinsipnya identik, mekanismenya menyesuaikan platform: **cordon/drain node** (cegah penjadwalan baru, pindahkan beban sah), **NetworkPolicy** untuk mengisolasi pod terdampak (deny all egress/ingress), **snapshot disk/volume** (EBS/PD) untuk bukti, **cabut IAM role/service account/token** yang disalahgunakan, dan **cordon** sebelum menghapus pod agar bisa diperiksa. Pola PICERL + order of volatility tetap sama; yang berubah hanya perintahnya. Jangan langsung `kubectl delete pod` — itu "docker rm" versi K8s (bukti hilang).'
    source: https://csrc.nist.gov/pubs/sp/800/61/r2/final

  - id: b13
    tier: B
    type: text
    objective: lessons
    prompt: Kapan sebuah insiden mungkin perlu melibatkan pihak legal/hukum atau notifikasi regulasi?
    answers: ['bila data pribadi/sensitif bocor, ada kewajiban notifikasi breach (gdpr/pci), atau potensi tindakan hukum']
    patterns: ['data (pribadi|sensitif|pelanggan)[^\n]*(bocor|dicuri|terpapar)', '(notifikasi|wajib lapor|kewajiban)[^\n]*(breach|pelanggaran|regulasi)', 'gdpr|pci', '(tindakan|potensi|proses)[^\n]*(hukum|legal)', 'penegak hukum|law enforcement']
    tests:
      fail: ['tidak pernah perlu legal', 'sembunyikan semua breach selalu', 'legal hanya memperlambat', 'regulasi tidak berlaku']
    explanation: 'Libatkan legal/compliance bila: **data pribadi/sensitif pelanggan bocor** (banyak yurisdiksi mewajibkan notifikasi breach dalam tenggat — mis. GDPR 72 jam, aturan PCI untuk data kartu), ada potensi **tindakan hukum** (mengejar pelaku, klaim asuransi), atau **kewajiban kontraktual** memberi tahu mitra. Mereka memandu notifikasi yang benar & tepat waktu, dan memastikan bukti dikumpulkan dengan cara yang sah (chain of custody, a12). Menyembunyikan breach yang wajib dilaporkan sering memperburuk konsekuensi hukumnya.'
    source: https://csrc.nist.gov/pubs/sp/800/61/r2/final

  - id: b14
    tier: B
    type: text
    objective: eradication
    prompt: Mengapa backup yang offline/immutable dan teruji penting untuk recovery (terutama terhadap ransomware)?
    answers: ['penyerang/ransomware sering menghapus atau mengenkripsi backup yang online; backup offline/immutable & teruji menjamin bisa dipulihkan']
    patterns: ['(backup online|backup)[^\n]*(hapus|enkripsi|dirusak|diserang)[^\n]*(ransomware|penyerang)?', '(offline|immutable|air.?gap|worm)[^\n]*(backup|aman|tak bisa)', '(teruji|test restore|uji pulih)[^\n]*(backup|bisa dipulihkan)']
    tests:
      fail: ['backup online selalu cukup', 'backup tidak perlu diuji', 'ransomware tidak menyentuh backup', 'satu backup sudah cukup']
    explanation: 'Ransomware & penyerang canggih sengaja **mencari & menghancurkan backup** (menghapus/mengenkripsi) sebelum memicu serangan — supaya kamu tak punya pilihan selain membayar. Karena itu backup harus **offline/air-gapped atau immutable (WORM)** sehingga tak bisa dirusak dari jaringan yang terkompromi, dan **teruji** (restore yang tak pernah dicoba sering gagal saat dibutuhkan). Pola **3-2-1**: 3 salinan, 2 media, 1 off-site/offline. Recovery hanya sebaik backup terakhir yang benar-benar bisa dipulihkan.'
    source: https://csrc.nist.gov/pubs/sp/800/61/r2/final

  - id: b15
    tier: B
    type: text
    objective: timeline
    prompt: Mengapa `docker top` (dari host) lebih tepercaya daripada menjalankan `ps` di dalam container terkompromi?
    answers: ['ps di dalam bisa sudah dimodifikasi/ditrojan penyerang; docker top membaca dari host di luar jangkauan mereka']
    patterns: ['ps[^\n]*(dalam|container)[^\n]*(modifikasi|trojan|diganti|ditipu|disembunyikan)', 'docker top[^\n]*(host|luar|kernel|tidak bisa ditipu)', '(biner|tool)[^\n]*(dalam container)[^\n]*(tak dipercaya|dimodifikasi)']
    tests:
      fail: ['keduanya sama persis', 'ps di dalam selalu jujur', 'docker top kurang akurat', 'tidak ada bedanya untuk forensik']
    explanation: 'Penyerang yang menguasai container bisa mengganti utilitas di dalamnya (rootkit userland): `ps`/`ls`/`netstat` yang dimodifikasi menyembunyikan proses/file mereka. `docker top` membaca daftar proses dari **host/kernel di luar container**, sehingga tak bisa ditipu oleh biner yang dirusak di dalam. Prinsip forensik umum: percayai pengamatan dari **lapisan di bawah** yang diserang (host untuk container, hypervisor untuk VM). Di lab, `docker top` menampilkan beacon dari sisi host — pandangan yang tak bisa dipalsukan dari dalam.'
    source: https://docs.docker.com/engine/reference/commandline/top/

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: containment
    prompt: |
      Monitoring (modul 09) memastikan sebuah container web terkompromi: ada webshell dan koneksi keluar ke C2.
      Rancang respons insiden dari penahanan sampai pemulihan (satu poin per baris).
    checklist:
      - id: contain
        label: Tahan — isolasi container (network disconnect / pause), JANGAN langsung hapus
        required: true
        patterns: ['(isolasi|disconnect|pause|putus jaringan)', '(jangan|tanpa|bukan)[^\n]*(hapus|rm|bunuh|kill)[^\n]*(dulu|langsung)', 'tahan[^\n]*(sebar|container)']
      - id: evidence
        label: Kumpulkan bukti volatil dulu (docker top/diff), snapshot/export, hash (chain of custody)
        required: true
        patterns: ['docker (top|diff)', '(snapshot|commit|export)[^\n]*(bukti|container|tar)', '(hash|sha256)[^\n]*(bukti|chain of custody)', 'bukti volatil[^\n]*dulu']
      - id: rootcause
        label: Cari initial access vector / akar masalah (bukan hanya artefak)
        patterns: ['initial access|akar (masalah|penyebab)|cara masuk|vektor masuk', '(bukan|lebih dari)[^\n]*(artefak|gejala)']
      - id: eradicate
        label: Berantas — hancurkan & redeploy dari image bersih (bukan clean-in-place)
        patterns: ['(hancurkan|hapus|ganti|redeploy|bangun ulang)[^\n]*(image bersih|known.?good|dari awal)', '(bukan|jangan)[^\n]*(bersihkan)[^\n]*(tempat|in.?place)']
      - id: rotate
        label: Rotasi semua rahasia/kredensial yang mungkin terpapar
        required: true
        patterns: ['rotasi[^\n]*(rahasia|kredensial|secret|token|password)', '(cabut|ganti)[^\n]*(token|kunci|password|kredensial)', 'anggap[^\n]*(dicuri|bocor)']
      - id: recover
        label: Pulihkan dari bersih + pantau reinfeksi
        patterns: ['pulih[^\n]*(bersih|known.?good|backup)', '(pantau|monitor|awasi)[^\n]*(reinfeksi|kembali|ulang)']
      - id: review
        label: Post-incident review (lessons learned) → perbaikan konkret
        patterns: ['(lessons|pelajaran|post-?incident|postmortem|evaluasi)', 'perbaikan[^\n]*(konkret|sistem|proses)']
      - id: no-nuke-first
        label: Tidak langsung menghapus/reboot container lalu lanjut seolah selesai
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|setelah|selain))[^\n]*(langsung|cukup)[^\n]*(hapus|rm|reboot|restart)[^\n]*(container|lalu|saja|selesai)']
    pass_score: 0.5
    solution: |
      ```text
      1. TAHAN: docker network disconnect (putus C2/lateral, proses tetap hidup) dan/atau docker pause (freeze).
         JANGAN docker rm dulu — bukti volatil hilang. (lab: disconnect → isolated tapi Up.)
      2. BUKTI (volatil dulu, order of volatility): docker top (proses beacon), docker diff (shell.php, /etc/passwd,
         .beacon.sh), docker commit + docker export → tar, sha256sum tar (chain of custody). (lab: semua terverifikasi.)
      3. AKAR: temukan initial access vector (SQLi? dependency rentan? kredensial bocor?) — tutup, bukan hanya hapus artefak.
      4. BERANTAS: hancurkan container, redeploy dari image/IaC bersih known-good. (lab: container baru = 0 artefak.)
      5. ROTASI: anggap semua rahasia yang terjangkau sudah dicuri → rotasi password/token/kunci (modul 04).
      6. PULIHKAN dari bersih, kembalikan bertahap, PANTAU ketat untuk reinfeksi (modul 09).
      7. REVIEW blameless → daftar perbaikan konkret dengan pemilik & tenggat.
      ```
      Urutan PICERL melindungi bukti dan mencegah serangan kembali. "Langsung hapus container lalu lanjut" membuang
      bukti DAN meninggalkan jalan masuk terbuka — insiden akan terulang.
    tests:
      pass:
        - |
          tahan dengan isolasi docker network disconnect atau pause jangan langsung hapus container
          kumpulkan bukti volatil dulu docker top dan docker diff lalu snapshot commit export dan sha256sum untuk chain of custody
          cari initial access vector akar masalah bukan hanya artefak yang terlihat
          berantas dengan menghancurkan dan redeploy dari image bersih known good bukan clean in place
          rotasi semua rahasia dan kredensial yang mungkin terpapar anggap sudah dicuri
          pulihkan dari bersih dan pantau ketat reinfeksi
          lakukan post incident review lessons learned jadi perbaikan konkret
          jangan langsung hapus atau reboot container lalu lanjut seolah selesai
        - |
          isolasi container pause dan network disconnect dulu, jangan rm
          docker top dan diff untuk bukti, commit dan export lalu hash untuk custody
          temukan vektor masuk, redeploy dari image bersih, rotasi semua secret
          pantau reinfeksi dan buat postmortem blameless dengan perbaikan
      fail:
        - |
          langsung docker rm container lalu lanjut kerja seperti biasa
          tidak perlu bukti atau rotasi
        - |
          reboot server saja sudah cukup
          hapus webshell lalu online lagi tanpa cari jalan masuk
        - |
          bersihkan file jahat di container yang sama lalu biarkan jalan
          secret lama tetap dipakai
    source: https://www.sans.org/white-papers/33901/

  - id: c02
    tier: C
    type: scenario
    objective: phases
    prompt: |
      Tim kamu belum pernah menyiapkan respons insiden. Rancang fase "Preparation" agar siap SEBELUM insiden pertama
      terjadi (satu poin per baris).
    checklist:
      - id: plan
        label: Rencana IR + runbook per jenis insiden (langkah jelas)
        required: true
        patterns: ['(rencana|plan)[^\n]*(ir|insiden|respons)', 'runbook|playbook', 'langkah[^\n]*(jelas|insiden)']
      - id: roles
        label: Peran, kontak, dan jalur eskalasi (siapa melakukan/menghubungi apa)
        patterns: ['(peran|role|tanggung jawab)', 'kontak|eskalasi|on-?call', '(siapa)[^\n]*(melakukan|menghubungi|bertanggung)']
      - id: backups
        label: Backup teruji + offline/immutable (untuk recovery/ransomware)
        required: true
        patterns: ['backup[^\n]*(teruji|offline|immutable|air.?gap|test)', '(uji|test)[^\n]*restore', '3-2-1']
      - id: logging
        label: Logging & deteksi terpusat sudah jalan (tak bisa investigasi tanpa log)
        patterns: ['(logging|log|deteksi|monitoring)[^\n]*(terpusat|siap|jalan|aktif)', 'siem', 'centralized']
      - id: access
        label: Akses & izin untuk cepat mengisolasi/snapshot saat insiden
        patterns: ['(akses|izin|hak)[^\n]*(isolasi|snapshot|insiden|cepat)', '(break.?glass|akses darurat)']
      - id: exercise
        label: Latihan/tabletop + definisi severity insiden
        patterns: ['(tabletop|latihan|simulasi|drill)', '(severity|tingkat|klasifikasi)[^\n]*(insiden|keparahan)']
      - id: no-wing
        label: Tidak "improvisasi saat kejadian / tanpa rencana"
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|hindari))[^\n]*(improvisasi|spontan|tanpa rencana|wing it|mikir nanti)[^\n]*(saat|kejadian|insiden)?']
    pass_score: 0.5
    solution: |
      ```text
      1. Rencana IR + runbook per jenis insiden (web compromise, ransomware, kebocoran kredensial) — langkah konkret,
         tak perlu berpikir dari nol saat panik.
      2. Peran, kontak, eskalasi: incident commander, siapa mengisolasi, siapa komunikasi, siapa hubungi legal/manajemen.
      3. Backup teruji + offline/immutable (3-2-1) — recovery hanya sebaik backup yang benar-benar bisa dipulihkan.
      4. Logging & deteksi terpusat sudah jalan (modul 09) — tak bisa menyelidiki tanpa bukti.
      5. Akses/izin siap (break-glass) untuk cepat network-disconnect, pause, snapshot, cabut token saat insiden.
      6. Latihan tabletop + definisi severity (SEV1/2/3) agar respons terkalibrasi.
      ```
      Preparation adalah fase paling menentukan dan paling sering diabaikan: keputusan dibuat lebih baik sebelum krisis
      daripada di tengahnya. "Improvisasi saat kejadian" berarti kehilangan menit-menit kritis, membuat keputusan
      panik, dan menemukan backup tak bisa dipulihkan tepat saat paling dibutuhkan.
    tests:
      pass:
        - |
          siapkan rencana ir dan runbook per jenis insiden dengan langkah jelas
          tetapkan peran kontak dan jalur eskalasi siapa melakukan dan menghubungi apa
          backup teruji dan offline immutable pola 3-2-1 untuk recovery dan ransomware
          pastikan logging dan deteksi terpusat sudah jalan sebelum insiden
          siapkan akses dan izin untuk cepat mengisolasi dan snapshot saat insiden
          lakukan latihan tabletop dan definisikan severity insiden
          jangan improvisasi tanpa rencana saat kejadian
        - |
          rencana dan runbook, peran dan eskalasi jelas
          backup offline teruji, siem jalan, akses break glass siap, tabletop rutin
      fail:
        - |
          improvisasi saja saat kejadian tanpa rencana
          backup tidak perlu diuji
        - |
          tidak usah runbook atau peran
          nanti dipikir saat ada insiden
        - |
          matikan logging biar hemat
          tidak perlu latihan
    source: https://www.sans.org/white-papers/33901/

  - id: c03
    tier: C
    type: scenario
    objective: lessons
    prompt: |
      Setelah insiden ditangani, kamu harus merekonstruksi apa yang terjadi dan memastikan tidak terulang. Rancang
      pembuatan timeline, analisis akar masalah, dan lessons learned (satu poin per baris).
    checklist:
      - id: timeline
        label: Bangun timeline dari log + mtime file lintas sistem (NTP agar konsisten)
        required: true
        patterns: ['timeline', '(log|mtime|stat)[^\n]*(gabung|korelasi|urut|waktu)', 'ntp|sinkron(isasi)? waktu']
      - id: rootcause
        label: Tentukan akar masalah / initial access vector
        required: true
        patterns: ['akar (masalah|penyebab)|root cause|initial access|cara masuk|vektor masuk']
      - id: scope
        label: Tentukan ruang lingkup/blast radius (apa yang diakses, sistem lain terdampak?)
        patterns: ['(ruang lingkup|scope|blast radius|cakupan)', '(apa|data) (yang )?(diakses|dicuri|terdampak)', 'sistem lain[^\n]*(terdampak|dicek)']
      - id: ioc
        label: Dokumentasikan IOC dan buru di sistem lain (threat hunt)
        patterns: ['ioc|indicator of compromise|indikator', '(buru|hunt|cek|periksa)[^\n]*(sistem lain|host lain)', 'webshell|user backdoor|proses asing']
      - id: custody
        label: Dokumentasi timeline + chain of custody bukti (hash, siapa/kapan)
        patterns: ['chain of custody', '(dokumentasi|catat)[^\n]*(bukti|timeline|hash)', 'hash[^\n]*bukti']
      - id: lessons
        label: Lessons learned blameless → perbaikan konkret (pemilik + tenggat)
        required: true
        patterns: ['(lessons|pelajaran|postmortem)[^\n]*(blameless|tanpa salah)?', 'perbaikan[^\n]*(konkret|pemilik|tenggat|sistem)', 'blameless']
      - id: no-blame
        label: Tidak sekadar memperbaiki gejala / menyalahkan satu orang lalu selesai
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain|lebih dari))[^\n]*(cukup|hanya)[^\n]*(perbaiki gejala|salahkan|menyalahkan|pecat)[^\n]*(satu orang|individu|lalu selesai|saja)?']
    pass_score: 0.45
    solution: |
      ```text
      1. TIMELINE: gabungkan log akses/auth (modul 09) + mtime file (stat) lintas sistem, diurutkan per waktu — NTP
         memastikan timestamp antar-host konsisten. (lab: stat → webshell ditanam 15:26:37.)
      2. AKAR: tentukan initial access vector (bagaimana penyerang pertama masuk) — ini yang ditutup, bukan gejala.
      3. SCOPE: apa yang diakses/dicuri, data apa terpapar, sistem lain terdampak? → menentukan notifikasi (legal, b13).
      4. IOC: dokumentasikan indikator (webshell, user backdoor, proses/koneksi asing) lalu BURU di host lain (threat hunt).
      5. DOKUMENTASI: timeline lengkap + chain of custody bukti (hash, siapa/kapan) — solid bila ada tindak lanjut hukum.
      6. LESSONS LEARNED (blameless): ubah temuan jadi perbaikan konkret (tambal vektor, perbaiki deteksi, update
         runbook) dengan pemilik & tenggat.
      ```
      Tujuan: mengerti FULL story (bagaimana masuk, apa dilakukan, seberapa luas) lalu memperbaiki penyebab sistemik.
      "Perbaiki gejala / salahkan satu orang lalu selesai" menjamin insiden yang sama datang lagi.
    tests:
      pass:
        - |
          bangun timeline dari log dan mtime file lintas sistem dengan ntp agar timestamp konsisten
          tentukan akar masalah dan initial access vector cara penyerang pertama masuk
          tentukan ruang lingkup dan blast radius apa yang diakses dan sistem lain yang terdampak
          dokumentasikan ioc seperti webshell dan user backdoor lalu buru di sistem lain threat hunt
          dokumentasikan timeline dan chain of custody bukti dengan hash siapa dan kapan
          buat lessons learned blameless jadi perbaikan konkret dengan pemilik dan tenggat
          jangan sekadar perbaiki gejala atau menyalahkan satu orang lalu selesai
        - |
          timeline dari log plus mtime, ntp selaras, cari root cause initial access
          tentukan scope data yang terpapar, dokumentasi ioc dan hunt di host lain
          chain of custody bukti, postmortem blameless dengan perbaikan konkret
      fail:
        - |
          cukup perbaiki gejala lalu selesai
          salahkan intern yang bikin error dan tutup kasus
        - |
          tidak perlu timeline atau akar masalah
          hapus webshell sudah cukup
        - |
          pecat satu orang biar jera
          tidak usah dokumentasi atau lessons learned
    source: https://sre.google/sre-book/postmortem-culture/
---

# Respons Insiden & Forensik Dasar

Modul 09 membuatmu **melihat** serangan. Sekarang: bertindak tanpa merusak bukti dan tanpa membuat panik memperparah
keadaan. Modul ini mengikuti siklus **PICERL** (SANS) / NIST SP 800-61 dan memperlihatkan forensik container yang
nyata — menahan, mengumpulkan bukti sesuai urutan volatilitas, memberantas, memulihkan, lalu belajar.

> [!NOTE]
> Lab: container `nginx:alpine` "terkompromi" (webshell `shell.php`, user backdoor di `/etc/passwd`, proses
> `.beacon.sh`) di net `dj-sec10` (Docker Desktop). Semua simulasi pada target milik sendiri — jangan pernah uji pada
> sistem orang lain.

## 1. Siklus PICERL

**P**reparation → **I**dentification/Detection → **C**ontainment → **E**radication → **R**ecovery → **L**essons learned.
Urutannya melindungi: tahan **sebelum** berantas, berantas **sebelum** pulih. Preparation (sebelum insiden) & Lessons
(sesudah) adalah fase yang paling sering diabaikan dan paling menentukan.

## 2. Containment — isolasi, jangan hancurkan (hasil lab)

```text
docker network disconnect <net> <container>  → putus C2/lateral, proses TETAP hidup
   (lab: setelah disconnect, container Up tetapi wget ke peer = "NO (isolated)")
docker pause <container>                      → bekukan proses via cgroup freezer (Up (Paused))
docker rm/stop/kill                           → ✗ membuang bukti volatil (JANGAN dulu)
```

Isolasi dulu supaya penyerang berhenti menyebar, tapi memori & proses tetap bisa diperiksa.

## 3. Bukti — urutan volatilitas & chain of custody (hasil lab)

```text
# volatil dulu (hilang saat mati): proses & perubahan file
docker top  dj-sec10-web     → /bin/sh /tmp/.beacon.sh   + sleep 60   (proses nakal)
docker diff dj-sec10-web     → A .../shell.php   C /etc/passwd   A /tmp/.beacon.sh

# preservasi untuk analisis offline
docker commit <c> evidence:incident     → snapshot image (layer + konfigurasi)
docker export <c> > evidence.tar        → tar filesystem datar (62M; berisi shell.php, .beacon.sh)
sha256sum evidence.tar                   → 3c5ea43e...  (chain of custody — bukti tak berubah)

# timeline
stat /tmp/.beacon.sh         → mtime 2026-11-25 15:26:37   (kapan foothold ditanam)
```

`docker top` membaca dari **host**, jadi tak bisa ditipu `ps` yang mungkin sudah dimodifikasi di dalam container.
Order of volatility (RFC 3227): tangkap memori/proses/koneksi **sebelum** disk.

## 4. Eradication — hancurkan & bangun ulang (hasil lab)

```text
container terkompromi  → HANCURKAN (bukti sudah diamankan di snapshot/tar)
redeploy dari image bersih (nginx:alpine asli) → 0 shell.php, 0 user backdoor, docker diff bersih
```

Jangan "membersihkan" di tempat — kamu tak pernah yakin semua foothold terbuang. **Cattle, not pets.** Tutup
**initial access vector** (akar), dan **rotasi semua rahasia** (anggap sudah dicuri — modul 04).

## 5. Recovery & Lessons learned

- **Recovery**: kembalikan dari bersih, pastikan akar sudah ditutup, **pantau ketat reinfeksi** (modul 09), kembalikan
  layanan bertahap. Backup untuk recovery harus **offline/immutable & teruji** (3-2-1) — ransomware memburu backup online.
- **Lessons learned (blameless)**: fokus memperbaiki **sistem/proses**, bukan menyalahkan orang — supaya orang jujur
  dan akar sistemik muncul. Hasilkan perbaikan konkret dengan pemilik & tenggat.

## 6. Ringkasan: peta aksi insiden

| Fase | Aksi container (lab) | Prinsip |
|---|---|---|
| Containment | `network disconnect`, `pause` (bukan `rm`) | tahan, jangan buang bukti |
| Evidence | `docker top`, `docker diff`, `commit`, `export`, `sha256sum`, `stat` | volatil dulu, chain of custody |
| Eradication | hancurkan + redeploy image bersih, rotasi rahasia | cattle not pets, tutup akar |
| Recovery | restore bersih + pantau reinfeksi, backup 3-2-1 | verifikasi sebelum online |
| Lessons | postmortem blameless → perbaikan konkret | perbaiki sistem, bukan orang |

> [!WARNING]
> Kesalahan IR paling umum: **panik lalu `rm`/reboot**. Itu menghapus bukti volatil DAN sering meninggalkan jalan
> masuk terbuka (akar tak ditutup, rahasia tak dirotasi) — insiden terulang. Isolasi → bukti → berantas → pulih.

> [!NOTE]
> Lanjut ke modul 11 (Keamanan pipeline & IaC) — menggeser keamanan ke kiri: menutup celah di CI/CD dan
> infrastructure-as-code sebelum sampai produksi, agar insiden seperti ini tak pernah terjadi sejak awal.
