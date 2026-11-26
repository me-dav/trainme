---
format: 1
id: rt-password-attacks
course:
  slug: redteam
  title: Red Team (Lab Pentest)
  description: Sisi ofensif dari DevSecOps — metodologi red team dipelajari di lab terisolasi milik sendiri (VirtualBox host-only, Kali Linux + Metasploitable 2) untuk memahami cara penyerang bekerja dan membangun pertahanannya (purple team). Recon, enumerasi, analisis kerentanan, eksploitasi, post-exploitation, dan pelaporan. SEMUA hanya pada target milik sendiri dengan izin; teknik dipetakan ke kontrol pertahanan di board DevSecOps.
title: Password Attacks
summary: "Kredensial lemah adalah jalur bobol dunia-nyata #1. Modul ini: serangan online (Hydra — brute/spray login SSH/FTP/HTTP) vs offline (John the Ripper/hashcat — crack hash yang dicuri), wordlist & aturan mangling (rockyou), identifikasi jenis hash, dan strategi (default→spraying→brute). Hydra & John dibuktikan di lab penulis. Tiap serangan dipetakan ke pertahanan blue: password kuat/unik, MFA, lockout/rate-limit, hash lambat ber-salt, dan key-only auth (board DevSecOps modul 03/04)."
order: 7
version: 1
status: verified
updated: '2026-11-26'
tested_on:
  - 'Diverifikasi penulis di lab terisolasi: John the Ripper crack raw-MD5 "password123" (bob) dari wordlist; Hydra brute SSH menemukan bob:password123 ("1 valid password found")'
  - 'hashcat, mangling rules, HTTP-form brute, dan wordlist besar (rockyou) DIDOKUMENTASIKAN — dijalankan learner di Kali'
  - Semua pada target/hash milik sendiri di lab terisolasi; tiap teknik dipetakan ke pertahanan board DevSecOps
objectives:
  - id: online
    text: Melakukan serangan online (Hydra) terhadap servis login
  - id: offline
    text: Meng-crack hash secara offline (John/hashcat)
  - id: wordlists
    text: Memakai wordlist & aturan mangling secara efektif
  - id: hashes
    text: Mengidentifikasi jenis hash & memilih mode crack
  - id: strategy
    text: Memilih strategi (default/spraying/brute) sesuai konteks
  - id: purple
    text: Memetakan serangan password ke pertahanannya (blue)
sources:
  - title: Hydra — network login cracker
    url: https://github.com/vanhauser-thc/thc-hydra
  - title: John the Ripper
    url: https://www.openwall.com/john/
  - title: hashcat
    url: https://hashcat.net/hashcat/
  - title: OWASP — Authentication & Credential Stuffing
    url: https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html
  - title: OWASP — Password Storage Cheat Sheet
    url: https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html
  - title: MITRE ATT&CK — Brute Force (T1110)
    url: https://attack.mitre.org/techniques/T1110/
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: online
    prompt: Apa beda serangan password "online" dan "offline"?
    answers: ['online: mencoba login langsung ke servis hidup (terbatas kecepatan & terdeteksi/lockout); offline: crack hash yang sudah dicuri di mesin sendiri (sangat cepat, tak terdeteksi target)']
    patterns: ['online[^\n]*(login|servis)[^\n]*(langsung|hidup|terbatas|lockout|terdeteksi)', 'offline[^\n]*(hash|dicuri)[^\n]*(mesin sendiri|cepat|tak terdeteksi)']
    tests:
      fail: ['keduanya sama', 'online lebih cepat dari offline', 'offline butuh koneksi ke target', 'tidak ada bedanya']
    explanation: '**Online**: mencoba login **langsung ke servis hidup** (SSH/FTP/web) — dibatasi kecepatan jaringan & respons servis, dan mudah **terdeteksi/memicu lockout** (board DevSecOps modul 03: fail2ban). Alat: Hydra. **Offline**: kamu sudah **mencuri hash** (dari DB via SQLi modul 06, `/etc/shadow`, hashdump) dan meng-crack-nya di **mesin sendiri** — sangat cepat (jutaan/miliar tebakan/detik dengan GPU), **tak terdeteksi** target (tak ada interaksi), tak ada lockout. Alat: John/hashcat. Offline jauh lebih kuat jika kamu punya hash-nya — itulah kenapa kebocoran hash (modul 06) berbahaya. Pertahanan beda: online → lockout/MFA; offline → hash lambat ber-salt (a09).'
    source: https://attack.mitre.org/techniques/T1110/

  - id: a02
    tier: A
    type: command
    objective: online
    prompt: Brute-force login SSH user `bob` di `192.168.56.101` dengan Hydra memakai wordlist `pw.txt`.
    answers: ['hydra -l bob -P pw.txt ssh://192.168.56.101']
    patterns: ['^(?=.*-l[ \t]+\S+)(?=.*-P[ \t]+\S+)hydra[ \t].*ssh://192\.168', '^(?=.*-l[ \t]+\S+)(?=.*-P[ \t]+\S+)hydra[ \t].*ssh']
    tests:
      pass:
        - 'hydra -l bob -P pw.txt ssh://192.168.56.101'
        - 'hydra -l bob -P pw.txt 192.168.56.101 ssh'
      fail:
        - 'hydra 192.168.56.101'
        - 'hydra -l bob ssh://192.168.56.101'
        - 'john ssh://192.168.56.101'
    explanation: '`hydra -l <user> -P <wordlist> <servis>://<target>` mencoba tiap password dari wordlist untuk user itu. Diverifikasi penulis: Hydra menemukan `bob:password123` ("1 valid password found"). `-l` user tunggal, `-L` file user (jamak); `-p` password tunggal, `-P` file password. `-s <port>` untuk port non-standar. Hydra mendukung banyak protokol (ssh, ftp, http-get/post-form, smb, mysql, rdp, ...). Ini serangan **online** (a01) — berisik & memicu lockout. Butuh daftar user (enumerasi modul 03) + wordlist. Sisi blue (board DevSecOps modul 03, yang kamu buktikan!): key-only auth, fail2ban, MaxAuthTries, MFA menggagalkan ini.'
    source: https://github.com/vanhauser-thc/thc-hydra

  - id: a03
    tier: A
    type: command
    objective: offline
    prompt: Crack file hash `hashes.txt` dengan John the Ripper memakai wordlist `rockyou.txt`.
    answers: ['john --wordlist=rockyou.txt hashes.txt']
    patterns: ['^(?=.*--wordlist=\S+)john[ \t].*\S+', '^john[ \t].*--wordlist=\S+']
    tests:
      pass:
        - 'john --wordlist=rockyou.txt hashes.txt'
        - 'john --wordlist=/usr/share/wordlists/rockyou.txt hashes.txt'
      fail:
        - 'john hashes.txt --crack'
        - 'hydra hashes.txt'
        - 'john'
    explanation: '`john --wordlist=<list> <hashfile>` meng-crack hash secara **offline** dengan mencoba tiap kata dari wordlist (+ aturan mangling, a07). Diverifikasi penulis: John meng-crack raw-MD5 `password123` untuk user `bob`. John auto-deteksi banyak format hash; untuk format spesifik pakai `--format=` (mis. `Raw-MD5`, `sha512crypt`). Tanpa `--wordlist`, John jalan mode default (single → wordlist bawaan → incremental/brute). **rockyou.txt** (~14 juta password dari kebocoran nyata) adalah wordlist klasik. Hasil tersimpan di `john.pot`. Lihat dengan `john --show`. Offline cracking = cepat & senyap bila kamu sudah punya hash.'
    source: https://www.openwall.com/john/

  - id: a04
    tier: A
    type: command
    objective: offline
    prompt: Tampilkan password yang sudah berhasil di-crack dari `hashes.txt` dengan John.
    answers: ['john --show hashes.txt']
    patterns: ['^(?=.*--show)john[ \t].*\S+', '^john[ \t]+--show[ \t]+\S+']
    tests:
      pass:
        - 'john --show hashes.txt'
        - 'john --show --format=Raw-MD5 hashes.txt'
      fail:
        - 'john hashes.txt'
        - 'cat hashes.txt'
        - 'john --crack hashes.txt'
    explanation: '`john --show <hashfile>` menampilkan hash yang sudah di-crack (dari `john.pot`) dalam format `user:password`. Diverifikasi penulis: `bob:password123`. Berguna karena proses crack bisa lama/di-background — `--show` membaca hasil kapan saja. Untuk format tertentu tambahkan `--format=`. Hasil crack = kredensial valid yang bisa dipakai login (credential reuse/lateral movement, modul 05 b11) atau masuk laporan sebagai bukti "password lemah" (modul 10). Catatan: `john.pot` menyimpan crack sebelumnya — hapus bila ingin mengulang dari nol. Mengelola hasil crack rapi = bagian engagement.'
    source: https://www.openwall.com/john/

  - id: a05
    tier: A
    type: text
    objective: wordlists
    prompt: Apa itu wordlist "rockyou.txt" dan kenapa sangat efektif?
    answers: ['daftar ~14 juta password dari kebocoran nyata (rockyou); efektif karena orang memakai password umum yang sama berulang']
    patterns: ['(juta|14 ?juta|jutaan)[^\n]*password', '(kebocoran|breach|bocor) (nyata|asli|sungguhan)?', 'password (umum|sama|populer)[^\n]*(berulang|dipakai)']
    tests:
      fail: ['daftar acak tanpa makna', 'password buatan sendiri', 'tidak efektif', 'hanya 10 password']
    explanation: 'rockyou.txt adalah ~14 juta password dari **kebocoran nyata** (breach RockYou 2009) — password yang orang **benar-benar pakai**. Sangat efektif karena manusia memilih password yang bisa diprediksi & memakainya berulang (`password`, `123456`, `qwerty`, nama+tahun). Jadi wordlist dari breach nyata "menebak" password nyata jauh lebih baik dari brute acak. Di Kali: `/usr/share/wordlists/rockyou.txt` (sering perlu `gunzip`). Wordlist lain: SecLists (koleksi besar). Pelajaran blue (board DevSecOps modul 03/04): **larang password umum/bocoran** (cek terhadap daftar seperti ini / Have I Been Pwned), wajibkan panjang & keunikan — agar password tak ada di wordlist mana pun.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html

  - id: a06
    tier: A
    type: text
    objective: hashes
    prompt: Mengapa penting mengidentifikasi JENIS hash sebelum mencoba meng-crack-nya?
    answers: ['tool perlu tahu algoritma (md5/sha/bcrypt/ntlm) untuk memilih mode crack yang benar; salah format = gagal atau sangat lambat']
    patterns: ['(algoritma|format|jenis)[^\n]*(md5|sha|bcrypt|ntlm|crypt)', '(mode|format)[^\n]*(crack|benar|cocok)', 'salah (format|mode)[^\n]*(gagal|lambat|tidak)']
    tests:
      fail: ['jenis hash tidak penting', 'semua hash sama', 'tool menebak sendiri selalu benar', 'tidak perlu identifikasi']
    explanation: 'Hash punya banyak algoritma (MD5, SHA-1/256/512, bcrypt, NTLM, sha512crypt, dll) dengan format berbeda. Tool crack (John/hashcat) perlu tahu algoritmanya untuk memilih **mode/format** yang benar (`--format=` di John, `-m <mode>` di hashcat) — salah format = gagal atau sangat lambat. Identifikasi dari: panjang & karakter hash, prefix (`$2y$`=bcrypt, `$6$`=sha512crypt, `$1$`=md5crypt), konteks (NTLM dari Windows). Alat bantu: `hashid`, `hash-identifier`. Penting juga untuk estimasi: bcrypt/argon2 **sengaja lambat** (sulit di-crack — a09), MD5/SHA cepat (mudah). Mengenali hash = memilih serangan yang tepat & menilai apakah worth it.'
    source: https://hashcat.net/hashcat/

  - id: a07
    tier: A
    type: text
    objective: wordlists
    prompt: Apa itu "mangling rules" (aturan transformasi) dalam cracking, dan kenapa berguna?
    answers: ['aturan yang memodifikasi kata wordlist (tambah angka/simbol, kapital, leetspeak) untuk menebak variasi — menangkap password seperti Password123!']
    patterns: ['(modifikasi|ubah|transformasi)[^\n]*(kata|wordlist)[^\n]*(angka|simbol|kapital|leet)', '(tambah|append)[^\n]*(angka|simbol|tahun)', 'leet|Password123|variasi[^\n]*password']
    tests:
      fail: ['aturan untuk memperlambat', 'tidak mengubah apa pun', 'hanya untuk online attack', 'tidak berguna']
    explanation: 'Mangling rules memodifikasi tiap kata wordlist untuk menebak **variasi** yang orang pakai: tambah angka/tahun (`password`→`password123`, `password2024`), simbol (`password!`), kapitalisasi (`Password`), leetspeak (`p@ssw0rd`), balik, gabung. Berguna karena orang "memperkuat" password dengan pola yang bisa diprediksi — rules menangkapnya tanpa wordlist raksasa. John: `--rules` (mis. `--rules=Jumbo`); hashcat: `-r rules/best64.rule`. Ini mengubah wordlist 14 juta jadi miliaran kandidat cerdas. Pelajaran blue (board DevSecOps modul 03): "Password123!" terasa kuat bagi user tapi lemah bagi cracker (pola umum) — wajibkan passphrase panjang/acak (password manager), bukan kata+angka+simbol yang bisa ditebak rules.'
    source: https://www.openwall.com/john/

  - id: a08
    tier: A
    type: command
    objective: online
    prompt: Brute-force form login HTTP POST di `192.168.56.101` dengan Hydra (path /login, field user/pass, penanda gagal "Invalid").
    answers: ['hydra -l admin -P pw.txt 192.168.56.101 http-post-form "/login:user=^USER^&pass=^PASS^:Invalid"']
    patterns: ['^(?=.*-P[ \t]+\S+)(?=.*http-post-form)hydra[ \t].*(\^USER\^|\^PASS\^)', '^(?=.*http-post-form)hydra[ \t]']
    tests:
      pass:
        - 'hydra -l admin -P pw.txt 192.168.56.101 http-post-form "/login:user=^USER^&pass=^PASS^:Invalid"'
        - 'hydra -L users.txt -P pw.txt 192.168.56.101 http-post-form "/login:user=^USER^&pass=^PASS^:F=Invalid"'
      fail:
        - 'hydra -l admin -P pw.txt ssh://192.168.56.101'
        - 'hydra 192.168.56.101 http-post-form'
        - 'sqlmap -u http://192.168.56.101/login'
    explanation: 'Hydra tak hanya SSH — `http-post-form` mem-brute form login web. Formatnya: `"<path>:<body dgn ^USER^ & ^PASS^>:<penanda gagal/sukses>"`. `^USER^`/`^PASS^` diganti kandidat; penanda (`Invalid`/`F=...` gagal, `S=...` sukses) memberi tahu Hydra cara membedakan berhasil vs gagal. Dapatkan body & field dari Burp (modul 06 a06). Berguna untuk form login aplikasi (DVWA, admin panel) tanpa lockout. Berisik (board DevSecOps modul 08/09: rate limit & deteksi menangkap ini). Untuk CSRF token dinamis, Hydra kurang cocok — pakai Burp Intruder. Ini credential attack terhadap lapisan web (melengkapi SQLi auth-bypass modul 06).'
    source: https://github.com/vanhauser-thc/thc-hydra

  - id: a09
    tier: A
    type: text
    objective: purple
    prompt: Mengapa hash lambat ber-salt (bcrypt/argon2) jauh lebih tahan terhadap offline cracking daripada MD5/SHA biasa?
    answers: ['sengaja lambat (banyak iterasi) → tebakan per detik anjlok; salt unik per user mencegah rainbow table & crack massal sekaligus']
    patterns: ['(lambat|iterasi|work factor|cost)[^\n]*(tebakan|per detik|anjlok|turun)', 'salt[^\n]*(unik|per user)[^\n]*(rainbow|massal|precompute)', 'md5[^\n]*(cepat|miliar)|bcrypt[^\n]*lambat']
    tests:
      fail: ['bcrypt lebih cepat dari md5', 'salt mempercepat crack', 'tidak ada bedanya', 'md5 lebih aman']
    explanation: 'MD5/SHA cepat by design (miliaran tebakan/detik dengan GPU) → mudah di-crack. **bcrypt/argon2/scrypt sengaja LAMBAT** (work factor/iterasi tinggi) → tebakan per detik anjlok drastis (ribuan, bukan miliaran) → crack jadi tak praktis. **Salt unik per user** memastikan: tak bisa pakai rainbow table (precomputed), dan dua user dengan password sama punya hash beda → harus crack satu per satu (tak bisa massal sekaligus). Pelajaran blue (board DevSecOps modul 04 — Password Storage): simpan password dengan **bcrypt/argon2 + salt unik**, bukan MD5/SHA/plaintext. Ini pertahanan **data-at-rest** (assume breach, modul 06 b15): bila hash bocor, crack-nya tetap mahal. Red yang meng-crack MD5 instan vs gagal di bcrypt membuktikannya.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html

  - id: a10
    tier: A
    type: text
    objective: strategy
    prompt: Sebelum brute-force masif, strategi apa yang lebih cepat & lebih hemat dicoba dulu?
    answers: ['coba kredensial default/umum & password spraying (satu password umum ke banyak user) — sering berhasil tanpa memicu lockout']
    patterns: ['(default|umum)[^\n]*(kredensial|password)[^\n]*(dulu|coba)', '(spraying|spray)[^\n]*(satu password|banyak user)?', '(hindari|tanpa)[^\n]*lockout']
    tests:
      fail: ['langsung brute masif selalu', 'tidak ada strategi lebih cepat', 'coba semua password sekaligus', 'brute buta paling efisien']
    explanation: 'Sebelum brute masif (lambat, berisik, memicu lockout), coba yang **cepat & hemat** (modul 03 b06): **kredensial default/umum** (`admin:admin`, `msfadmin:msfadmin`, `root:toor` — sering langsung berhasil, modul 03 b12), lalu **password spraying** (satu password umum seperti `Password123` ke SEMUA user valid — menghindari lockout per-akun karena tiap akun cuma dicoba sekali, dan selalu ada yang pakai password lemah). Baru jika gagal, brute terarah (cek metode auth dulu, modul 03 b09). Urutan: default → spraying → brute. Ini efisien & lebih senyap. Pelajaran blue: MFA + larang password umum + lockout cerdas + deteksi spraying (banyak user satu password) mematikan semua strategi ini.'
    source: https://attack.mitre.org/techniques/T1110/

  - id: a11
    tier: A
    type: command
    objective: offline
    prompt: Crack hash NTLM (dari Windows) di `ntlm.txt` dengan hashcat (mode 1000) memakai rockyou.
    answers: ['hashcat -m 1000 ntlm.txt rockyou.txt']
    patterns: ['^(?=.*-m[ \t]+1000)hashcat[ \t].*\S+', '^hashcat[ \t].*-m[ \t]+[0-9]+']
    tests:
      pass:
        - 'hashcat -m 1000 ntlm.txt rockyou.txt'
        - 'hashcat -m 1000 -a 0 ntlm.txt rockyou.txt'
      fail:
        - 'hashcat ntlm.txt'
        - 'john -m 1000 ntlm.txt'
        - 'hashcat rockyou.txt'
    explanation: '`hashcat -m <mode> <hashfile> <wordlist>` meng-crack offline, memanfaatkan **GPU** (jauh lebih cepat dari John untuk banyak format). `-m <mode>` memilih algoritma: `0`=MD5, `100`=SHA1, `1000`=NTLM, `1800`=sha512crypt, `3200`=bcrypt, `22000`=WPA. `-a 0` = attack mode wordlist (default), `-a 3` = brute mask. NTLM (mode 1000) umum dari dump Windows (SAM/NTDS). hashcat & John saling melengkapi — John fleksibel/multi-format, hashcat tercepat dengan GPU. Mengidentifikasi hash (a06) menentukan `-m`. Sisi blue: NTLM lemah (cepat di-crack) → migrasi ke mekanisme lebih kuat, MFA, dan lindungi dump hash (jangan biarkan SAM/shadow bocor).'
    source: https://hashcat.net/hashcat/

  - id: a12
    tier: A
    type: text
    objective: online
    prompt: Mengapa serangan brute online sering kurang efektif dibanding offline terhadap target yang dikonfigurasi baik?
    answers: ['target punya rate-limit/lockout/MFA & deteksi → brute online lambat dan cepat diblokir/terdeteksi; offline tak dibatasi target']
    patterns: ['(rate-?limit|lockout|mfa)[^\n]*(brute|online|blokir)', '(lambat|diblokir|terdeteksi)[^\n]*(online|target)', 'offline[^\n]*(tak dibatasi|tanpa batas|bebas)[^\n]*target']
    tests:
      fail: ['online selalu lebih cepat', 'target baik tidak berpengaruh', 'offline dibatasi target', 'tidak ada bedanya']
    explanation: 'Target yang dikonfigurasi baik melawan brute **online** dengan: **rate-limit** (perlambat percobaan), **account lockout** (kunci setelah N gagal), **MFA** (password saja tak cukup), **fail2ban/IDS** (blokir & alert IP penyerang — board DevSecOps modul 03 yang kamu buktikan), CAPTCHA. Jadi brute online jadi lambat & cepat diblokir/terdeteksi. **Offline** (a01) tak dibatasi target sama sekali (kamu crack di mesin sendiri), jadi jauh lebih kuat — TAPI butuh hash dulu (dicuri via SQLi/dump). Inilah kenapa: (red) lebih baik curi hash & crack offline daripada brute online yang berisik; (blue) lockout/MFA mematikan online, sementara hash lambat ber-salt (a09) melindungi bila hash bocor. Dua lapisan, dua serangan.'
    source: https://attack.mitre.org/techniques/T1110/

  - id: a13
    tier: A
    type: text
    objective: strategy
    prompt: Dari mana penyerang biasanya mendapat hash untuk offline cracking?
    answers: ['dari kebocoran DB (SQLi/dump, modul 06), file /etc/shadow atau SAM/NTDS setelah akses, atau hashdump via meterpreter (post-ex)']
    patterns: ['(db|database)[^\n]*(sqli|dump|bocor)', '/etc/shadow|sam|ntds', 'hashdump|meterpreter|post-?ex[^\n]*(hash)?']
    tests:
      fail: ['hash muncul sendiri', 'tidak perlu akses apa pun', 'hash selalu publik', 'dari google']
    explanation: 'Offline cracking butuh hash dulu. Sumber umum: **kebocoran DB** (SQLi/UNION dump tabel users, modul 06 — hash password aplikasi), **`/etc/shadow`** (Linux, setelah akses root — modul 08), **SAM/NTDS** (Windows), atau **hashdump** via Meterpreter/mimikatz setelah kompromi (post-exploitation, modul 08). Jadi offline cracking biasanya langkah **pasca-kompromi** atau pasca-kebocoran — kamu sudah punya hash, lalu crack untuk mendapat password plaintext (untuk login ke sistem lain = credential reuse/lateral, modul 05 b11). Pelajaran blue (board DevSecOps modul 04/06): lindungi hash (jangan biarkan bocor via SQLi/shadow accessible), hash dengan algoritma lambat, dan password unik per sistem agar crack satu tak membuka lainnya.'
    source: https://attack.mitre.org/techniques/T1110/

  - id: a14
    tier: A
    type: text
    objective: purple
    prompt: Apa pertahanan TUNGGAL paling efektif terhadap serangan password (online maupun yang berujung pakai kredensial)?
    answers: ['mfa / autentikasi multi-faktor — password yang ditebak/dicrack/dicuri saja tak cukup untuk masuk']
    patterns: ['mfa|multi-?faktor|2fa|two-?factor', '(password|kredensial)[^\n]*(saja|tebak|crack|curi)[^\n]*(tak cukup|tidak cukup|belum)']
    tests:
      fail: ['password lebih panjang saja', 'ganti password tiap hari', 'tidak ada pertahanan efektif', 'captcha saja']
    explanation: '**MFA (Multi-Factor Authentication)** adalah pertahanan tunggal paling berdampak: meski penyerang menebak/meng-crack/mencuri password, ia **tetap tak bisa masuk** tanpa faktor kedua (TOTP/kunci hardware/push). Ini mematahkan hampir semua serangan password — brute online, credential stuffing, password dari kebocoran, bahkan phishing password (dengan FIDO2/passkey). Bukan berarti yang lain tak perlu (password kuat, lockout, hash lambat tetap penting berlapis), tapi MFA memberi perlindungan terbesar per usaha. Pelajaran blue (board DevSecOps modul 03): aktifkan MFA di mana pun bisa, terutama akun admin & akses jarak jauh (SSH, VPN, panel). Password akan bocor suatu saat — MFA membuat itu tidak langsung berarti kompromi.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html

  - id: a15
    tier: A
    type: text
    objective: strategy
    prompt: Password attacks sering disebut membuktikan jalur bobol "dunia nyata #1". Mengapa demikian?
    answers: ['kredensial lemah/default/bocor/dipakai-ulang adalah penyebab breach paling umum — sering tak butuh exploit canggih, cukup login dengan password yang bisa ditebak']
    patterns: ['kredensial (lemah|default|bocor|dipakai ulang)[^\n]*(breach|bobol|umum|penyebab)', '(tak|tanpa) (butuh )?exploit (canggih|rumit)', '(login|masuk)[^\n]*(password)[^\n]*(ditebak|lemah|umum)']
    tests:
      fail: ['password tidak relevan', 'semua breach pakai 0-day', 'kredensial kuat selalu', 'tidak umum']
    explanation: 'Kredensial lemah/default/bocor/dipakai-ulang konsisten jadi **penyebab breach teratas** (lihat laporan tahunan DBIR, dll). Sering tak butuh exploit canggih — penyerang cukup **login** dengan password yang bisa ditebak, default yang tak diganti, atau kredensial dari kebocoran lain (credential stuffing karena orang pakai ulang). Metasploitable (`msfadmin:msfadmin`) & lab ini mengajarkannya; dunia nyata sama. Inilah mengapa password attacks berdampak besar dengan teknik "membosankan". Pelajaran blue (board DevSecOps modul 03/04): MFA (a14), password unik+kuat (password manager), larang default & password bocoran, lockout, dan hash kuat — menutup jalur #1 ini lebih berdampak daripada mengejar 0-day eksotis. Serangan tersukses sering paling sederhana.'
    source: https://attack.mitre.org/techniques/T1110/

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: command
    objective: online
    prompt: Brute-force FTP di `192.168.56.101` dengan daftar user `users.txt` dan password `pw.txt` via Hydra.
    answers: ['hydra -L users.txt -P pw.txt ftp://192.168.56.101']
    patterns: ['^(?=.*-L[ \t]+\S+)(?=.*-P[ \t]+\S+)hydra[ \t].*ftp', '^(?=.*-P[ \t]+\S+)hydra[ \t].*ftp://192\.168']
    tests:
      pass:
        - 'hydra -L users.txt -P pw.txt ftp://192.168.56.101'
        - 'hydra -L users.txt -P pw.txt 192.168.56.101 ftp'
      fail:
        - 'hydra -l admin ssh://192.168.56.101'
        - 'hydra ftp://192.168.56.101'
        - 'ftp 192.168.56.101'
    explanation: '`-L <file>` = daftar user (jamak), `-P <file>` = daftar password → Hydra mencoba semua kombinasi user×password terhadap FTP. Dengan daftar user dari enumerasi (modul 03) + wordlist, ini brute menyeluruh. Metasploitable FTP (vsftpd) bisa jadi target. Hati-hati: user×password besar = sangat banyak percobaan (lambat & sangat berisik, memicu lockout/deteksi). Lebih efisien: spraying (satu password, banyak user, a10) atau coba default dulu. `-t <n>` mengatur paralelisme (hati-hati terlalu agresif → false negative/ban). Hydra serbaguna — protokol apa pun yang punya login bisa di-brute. Sisi blue: sama seperti SSH — lockout, rate-limit, MFA, matikan servis/anon yang tak perlu.'
    source: https://github.com/vanhauser-thc/thc-hydra

  - id: b02
    tier: B
    type: command
    objective: offline
    prompt: Gabungkan /etc/passwd dan /etc/shadow (yang sudah kamu curi) menjadi format yang bisa di-crack John dengan unshadow.
    answers: ['unshadow passwd.txt shadow.txt > unshadowed.txt']
    patterns: ['^unshadow[ \t]+\S+[ \t]+\S+', '^unshadow[ \t]']
    tests:
      pass:
        - 'unshadow passwd.txt shadow.txt > unshadowed.txt'
        - 'unshadow /etc/passwd /etc/shadow > crack.txt'
      fail:
        - 'cat shadow.txt'
        - 'john shadow.txt'
        - 'unshadow shadow.txt'
    explanation: '`unshadow <passwd> <shadow>` (utilitas John) menggabungkan `/etc/passwd` (user info) dan `/etc/shadow` (hash password) menjadi satu file yang John bisa crack — karena hash ada di shadow tapi info user di passwd. Setelah akses root ke target Linux (modul 08: baca `/etc/shadow`), `unshadow` lalu `john unshadowed.txt` meng-crack password akun sistem → bisa dipakai lateral/persistence. Hash shadow modern biasanya `sha512crypt` (`$6$`) — lebih lambat dari MD5 tapi masih crackable bila password lemah. Pelajaran blue (board DevSecOps): `/etc/shadow` hanya readable root (lindungi akses root!), password kuat (crack gagal meski shadow bocor), dan hash lambat (a09). Ini alur post-ex → offline crack yang klasik.'
    source: https://www.openwall.com/john/

  - id: b03
    tier: B
    type: text
    objective: hashes
    prompt: Bagaimana mengidentifikasi jenis hash yang tidak kamu kenal?
    answers: ['pakai tool seperti hashid/hash-identifier, lihat prefix ($2y$=bcrypt, $6$=sha512crypt, $1$=md5crypt) dan panjang/karakter hash']
    patterns: ['hashid|hash-?identifier|hash-?id', '(prefix|awalan)[^\n]*(\$2y|\$6|\$1|bcrypt|sha512crypt)', '(panjang|karakter)[^\n]*hash']
    tests:
      fail: ['tidak bisa diidentifikasi', 'semua hash terlihat sama', 'tebak acak format', 'hash tidak punya ciri']
    explanation: 'Identifikasi hash dengan: **tool** (`hashid`, `hash-identifier`, `nth`/name-that-hash) yang menebak dari pola; **prefix** (format crypt: `$1$`=md5crypt, `$2a$/$2y$`=bcrypt, `$5$`=sha256crypt, `$6$`=sha512crypt, `$argon2`=argon2); dan **panjang/charset** (MD5=32 hex, SHA-1=40 hex, SHA-256=64 hex, NTLM=32 hex). Konteks membantu (NTLM dari Windows, sha512crypt dari `/etc/shadow`). Setelah tahu jenisnya, pilih mode: John `--format=`, hashcat `-m`. Salah tebak = crack gagal/sia-sia (a06). Ini langkah kecil tapi menentukan — meng-crack hash membutuhkan pencocokan algoritma yang benar lebih dulu, seperti mencocokkan versi→exploit (modul 04).'
    source: https://hashcat.net/hashcat/

  - id: b04
    tier: B
    type: text
    objective: strategy
    prompt: Mengapa password spraying lebih "aman dari lockout" dibanding brute force klasik?
    answers: ['spraying mencoba satu password ke banyak user (tiap akun cuma 1-2 percobaan) → tak memicu lockout per-akun; brute mencoba banyak password satu akun → cepat terkunci']
    patterns: ['spraying[^\n]*(satu password|banyak user)[^\n]*(tiap akun|sedikit|1)', '(tak|tidak) (memicu|kena)[^\n]*lockout', 'brute[^\n]*(banyak password|satu akun)[^\n]*(terkunci|lockout)']
    tests:
      fail: ['spraying mencoba banyak password satu user', 'keduanya sama memicu lockout', 'brute lebih aman dari lockout', 'spraying selalu terkunci']
    explanation: 'Account lockout mengunci akun setelah N kegagalan **per akun**. **Brute force klasik** (banyak password → satu akun) memicu lockout cepat (akun terkunci setelah ~3-5 coba). **Password spraying** (satu password umum → BANYAK akun) membuat tiap akun hanya dicoba 1-2 kali → **di bawah ambang lockout** → tak terkunci, sambil tetap menemukan akun yang pakai password lemah itu. Lebih senyap & efektif terhadap organisasi (selalu ada yang pakai `Musim2024!`). Jeda antar-putaran (slow spray) menghindari deteksi berbasis-waktu. Pelajaran blue (board DevSecOps modul 03/09): lockout saja tak cukup — deteksi **pola spraying** (satu password dicoba ke banyak user, banyak akun gagal dengan password sama), MFA, dan larang password umum. Lockout per-akun buta terhadap spraying.'
    source: https://attack.mitre.org/techniques/T1110/

  - id: b05
    tier: B
    type: command
    objective: offline
    prompt: Jalankan John mode default (tanpa wordlist) dengan aturan mangling pada `hashes.txt`.
    answers: ['john --rules hashes.txt']
    patterns: ['^(?=.*--rules)john[ \t].*\S+', '^john[ \t].*--rules']
    tests:
      pass:
        - 'john --rules hashes.txt'
        - 'john --wordlist=rockyou.txt --rules hashes.txt'
      fail:
        - 'john hashes.txt'
        - 'john --show hashes.txt'
        - 'hashcat --rules hashes.txt'
    explanation: '`--rules` mengaktifkan aturan mangling (a07) — John memodifikasi tiap kata (tambah angka/simbol, kapital, leet) untuk menebak variasi. Digabung wordlist: `john --wordlist=rockyou.txt --rules` menjadi serangan sangat kuat (14 juta kata × puluhan transformasi). `--rules=Jumbo`/`--rules=KoreLogic` = set aturan lebih agresif. Tanpa `--wordlist`, John jalan mode "single" (pakai info user) lalu wordlist bawaan + rules, lalu incremental (brute murni, sangat lambat untuk password panjang). Memahami mode John penting: wordlist+rules menangkap password manusiawi; incremental hanya realistis untuk password sangat pendek. Pelajaran blue: passphrase panjang/acak (password manager) lolos dari wordlist+rules DAN tak praktis untuk incremental.'
    source: https://www.openwall.com/john/

  - id: b06
    tier: B
    type: text
    objective: online
    prompt: Hydra punya opsi -t (jumlah task paralel). Mengapa terlalu agresif (-t tinggi) bisa kontraproduktif?
    answers: ['terlalu banyak koneksi paralel bisa membebani/men-crash servis, memicu lockout/ban lebih cepat, dan menghasilkan false negative (koneksi gagal dianggap password salah)']
    patterns: ['(banyak|tinggi)[^\n]*(koneksi|task|paralel)[^\n]*(bebani|crash|overload|lockout|ban)', 'false negative|koneksi gagal[^\n]*(dianggap|salah)', '(memicu|cepat)[^\n]*(lockout|ban|deteksi)']
    tests:
      fail: ['semakin tinggi selalu lebih baik', 'tidak ada risiko', 't tidak berpengaruh', 'paralel selalu aman']
    explanation: '`-t <n>` mengatur task paralel. Terlalu tinggi kontraproduktif: (1) **membebani/men-crash** servis (do no harm — modul 01; di produksi bisa DoS tak sengaja), (2) memicu **lockout/ban/rate-limit lebih cepat** (deteksi makin agresif), (3) **false negative** — koneksi yang gagal karena overload bisa dilaporkan sebagai "password salah" padahal belum tercoba, membuatmu melewatkan password benar. Jadi seimbangkan kecepatan vs keandalan & kesenyapan (mirip timing nmap, modul 02 a14). Default Hydra (16) sering sudah cukup; turunkan untuk servis rapuh/senyap. Pelajaran blue: rate-limit & lockout justru memanfaatkan keagresifan ini untuk mendeteksi & memblokir brute (board DevSecOps modul 03/08).'
    source: https://github.com/vanhauser-thc/thc-hydra

  - id: b07
    tier: B
    type: text
    objective: offline
    prompt: Apa itu "rainbow table" dan kenapa salt membuatnya tidak berguna?
    answers: ['tabel precomputed hash→password untuk crack instan; salt unik per hash membuat precompute mustahil (tiap password+salt beda) sehingga rainbow table tak berlaku']
    patterns: ['(precompute|tabel)[^\n]*(hash|password)[^\n]*(instan|cepat|lookup)', 'salt[^\n]*(unik|beda)[^\n]*(precompute|rainbow|mustahil|tak berlaku)', 'rainbow table[^\n]*(tak berguna|gagal|salt)']
    tests:
      fail: ['rainbow table mempercepat dengan salt', 'salt tidak berpengaruh', 'rainbow table selalu berfungsi', 'salt adalah rainbow table']
    explanation: 'Rainbow table = tabel **precomputed** pemetaan hash→password (untuk algoritma cepat tanpa salt seperti MD5/NTLM) → crack jadi lookup instan, tanpa menghitung ulang. **Salt unik per hash** mematikannya: karena tiap password di-hash dengan salt berbeda (`hash(password+salt)`), tabel precomputed harus dibuat ulang untuk SETIAP salt — mustahil secara praktis. Itu sebabnya salt wajib (board DevSecOps modul 04). Modern (bcrypt/argon2) menggabung salt + lambat (a09) = pertahanan ganda: rainbow table tak berlaku (salt) DAN brute per-hash lambat (work factor). Pelajaran: hash cepat tanpa salt (MD5 polos) = rentan rainbow table + GPU; selalu pakai algoritma lambat ber-salt untuk menyimpan password.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html

  - id: b08
    tier: B
    type: command
    objective: offline
    prompt: Crack dengan hashcat memakai brute-force mask (attack mode 3) untuk 6 digit angka pada hash MD5.
    answers: ['hashcat -m 0 -a 3 hashes.txt ?d?d?d?d?d?d']
    patterns: ['^(?=.*-a[ \t]+3)hashcat[ \t].*\?d', '^(?=.*-a[ \t]+3)hashcat[ \t]']
    tests:
      pass:
        - 'hashcat -m 0 -a 3 hashes.txt ?d?d?d?d?d?d'
        - 'hashcat -a 3 -m 0 hashes.txt ?d?d?d?d?d?d'
      fail:
        - 'hashcat -m 0 hashes.txt rockyou.txt'
        - 'hashcat -a 0 hashes.txt'
        - 'john --mask ?d hashes.txt'
    explanation: '`-a 3` (mask attack) brute-force dengan pola karakter: `?d`=digit, `?l`=huruf kecil, `?u`=besar, `?s`=simbol, `?a`=semua. `?d?d?d?d?d?d` = semua kombinasi 6 digit (PIN/kode). Lebih terarah dari brute penuh — pakai saat kamu tahu pola password (mis. "6 digit", atau "kata+tahun" dengan mask `?l?l?l?l?d?d?d?d`). Efisien untuk ruang kecil/terstruktur; tak praktis untuk password panjang acak (ruang meledak). Lengkap: wordlist (`-a 0`) untuk password manusiawi, mask (`-a 3`) untuk pola tahu, hybrid (`-a 6/7`) gabungan. Pelajaran blue: password pendek/berpola (PIN, kata+angka) jatuh cepat ke mask attack — wajibkan panjang & keacakan (passphrase) agar ruang pencarian tak terjangkau.'
    source: https://hashcat.net/hashcat/

  - id: b09
    tier: B
    type: text
    objective: strategy
    prompt: Bagaimana enumerasi (modul 03) dan password attacks saling terkait?
    answers: ['enumerasi memberi daftar user valid & servis login; password attack memakainya untuk brute/spray terarah ke akun & servis yang ADA, bukan menebak buta']
    patterns: ['enumeras[^\n]*(user valid|daftar user|servis login)', '(brute|spray|password attack)[^\n]*(user|akun|servis)[^\n]*(ada|valid|terarah)', '(bukan|tanpa) menebak buta']
    tests:
      fail: ['keduanya tidak terkait', 'password attack tidak butuh user', 'enumerasi tidak membantu', 'menebak buta sama efektif']
    explanation: 'Password attacks hanya sebaik target & daftar inputnya — dan itu datang dari **enumerasi** (modul 03): daftar **user valid** (enum4linux SMB, SMTP VRFY, web) → `-L userlist` untuk Hydra; **servis login** yang terbuka (SSH/FTP/web/MySQL dari nmap, modul 02) → target brute; metode auth (ssh-auth-methods → apakah password aktif, modul 03 b09). Jadi brute/spray **terarah** ke akun & servis yang ADA, bukan menebak buta (jauh lebih efisien & berhasil). Alur: recon → enumerasi (user+servis) → analisis → password attack. Pelajaran blue (board DevSecOps): kurangi kebocoran user (modul 03 — jangan bocorkan username valid), matikan password auth (key-only SSH) → menghilangkan baik daftar user maupun jalur brute. Putus rantai di enumerasi.'
    source: https://attack.mitre.org/techniques/T1110/

  - id: b10
    tier: B
    type: text
    objective: purple
    prompt: "Ringkas peta purple: serangan online (Hydra) dan offline (John/hashcat) ke pertahanan blue-nya."
    answers: ['online → lockout/rate-limit/MFA/key-only/deteksi; offline → hash lambat ber-salt (bcrypt/argon2) + lindungi hash agar tak bocor; keduanya + password kuat/unik & larang bocoran']
    patterns: ['online[^\n]*(lockout|rate-?limit|mfa|key-?only|deteksi)', 'offline[^\n]*(hash lambat|bcrypt|argon2|salt|lindungi hash)', 'password (kuat|unik)|larang[^\n]*(umum|bocoran)|mfa']
    tests:
      fail: ['satu pertahanan untuk semua', 'tidak bisa dipetakan', 'online dan offline sama pertahanannya', 'tidak ada pertahanan']
    explanation: 'Peta purple password attacks (board DevSecOps modul 03/04): **Online (Hydra)** dilawan di titik login → account lockout, rate-limit (modul 08), **MFA** (a14), key-only SSH auth (modul 03 — matikan password), fail2ban + deteksi spraying (modul 09). **Offline (John/hashcat)** dilawan di penyimpanan → **hash lambat ber-salt** (bcrypt/argon2, a09/b07) agar crack mahal, + **lindungi hash** agar tak bocor (prepared statement anti-SQLi modul 06, shadow readable root saja, jangan dump bocor). **Keduanya** + fondasi: **password kuat & unik** (password manager), **larang password umum/bocoran** (cek HIBP). Dua serangan, dua lapisan pertahanan — plus MFA sebagai payung terkuat. Inilah kenapa board DevSecOps memisahkan "auth hardening" (modul 03) dan "secret/password storage" (modul 04).'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html

  - id: b11
    tier: B
    type: text
    objective: online
    prompt: Mengapa memakai daftar user dari enumerasi untuk spraying lebih baik daripada memasukkan sembarang username?
    answers: ['hanya akun yang benar-benar ada yang dicoba → tak buang waktu pada user tak ada, mengurangi noise, dan menghindari sinyal "user tidak dikenal" yang mencurigakan']
    patterns: ['(akun|user)[^\n]*(benar-benar ada|valid|nyata)[^\n]*(dicoba|target)', '(tak|tidak) buang waktu[^\n]*(user tak ada|akun palsu)', '(kurangi|mengurangi) noise|mencurigakan']
    tests:
      fail: ['sembarang username sama baiknya', 'enumerasi tidak membantu spraying', 'lebih baik tebak username acak', 'user tidak perlu valid']
    explanation: 'Spraying dengan **user valid** (dari enumerasi, modul 03) jauh lebih baik daripada username acak karena: (1) hanya akun yang **benar-benar ada** dicoba → tiap percobaan berpeluang, tak buang waktu; (2) **mengurangi noise** (lebih sedikit percobaan total); (3) **menghindari sinyal mencurigakan** — banyak percobaan ke "user tidak dikenal" adalah IOC yang jelas bagi blue (board DevSecOps modul 09), sedangkan ke user valid lebih menyatu. Ini menegaskan "enumeration is key" (modul 01/03): kualitas recon menentukan efisiensi serangan. Pelajaran blue: deteksi tetap menangkap spraying (satu password → banyak user valid, lonjakan gagal), dan MFA mematikannya apa pun daftar user-nya. Enumerasi mempertajam, pertahanan menutup.'
    source: https://attack.mitre.org/techniques/T1110/

  - id: b12
    tier: B
    type: text
    objective: offline
    prompt: Mengapa GPU (hashcat) jauh lebih cepat dari CPU untuk cracking, dan apa implikasinya bagi pilihan algoritma hash?
    answers: ['GPU memparalelkan ribuan hash cepat (MD5/SHA) per detik → implikasi: pakai hash yang sengaja lambat & tahan GPU (bcrypt/argon2) agar crack tetap mahal']
    patterns: ['gpu[^\n]*(paralel|ribuan|cepat)[^\n]*(hash|tebakan)', '(hash|algoritma)[^\n]*(lambat|tahan gpu|bcrypt|argon2)', '(md5|sha)[^\n]*(cepat|mudah)[^\n]*gpu']
    tests:
      fail: ['cpu lebih cepat dari gpu', 'algoritma tidak berpengaruh', 'gpu tidak membantu cracking', 'semua hash sama tahan gpu']
    explanation: 'GPU punya ribuan core yang memparalelkan komputasi hash sederhana (MD5/SHA/NTLM) — miliaran tebakan/detik, jauh melampaui CPU. Implikasi untuk penyimpanan password: hash cepat (MD5/SHA) **jatuh cepat** ke GPU cracking → jangan dipakai untuk password. Pakai algoritma **sengaja lambat & tahan-paralelisme**: **bcrypt** (lambat), **scrypt/argon2** (lambat + butuh banyak MEMORI → melawan keunggulan GPU yang memori-terbatas per-core). Ini memperkuat a09/b07: pilihan algoritma menentukan apakah hash yang bocor bisa di-crack praktis. Pelajaran blue (board DevSecOps modul 04): argon2id adalah rekomendasi modern (memory-hard) justru untuk melawan cracking GPU/ASIC. Red yang punya GPU meng-crack MD5 instan tapi menyerah di argon2 — bukti pilihan algoritma krusial.'
    source: https://hashcat.net/hashcat/

  - id: b13
    tier: B
    type: text
    objective: purple
    prompt: Bagaimana sisi blue mendeteksi serangan brute/spraying yang sedang berlangsung?
    answers: ['banyak login gagal (brute: satu akun banyak gagal; spraying: banyak akun gagal dengan password sama) dari sumber tertentu di log auth → alert/fail2ban']
    patterns: ['(banyak|lonjakan)[^\n]*(login gagal|gagal login|failed)', '(satu akun|banyak akun|banyak user)[^\n]*(gagal|password sama)', '(log auth|fail2ban|alert|siem)[^\n]*(gagal|brute|spray)']
    tests:
      fail: ['brute tidak terdeteksi', 'tidak ada jejak di log', 'blue team buta terhadap brute', 'login gagal tidak dicatat']
    explanation: 'Brute/spraying meninggalkan jejak jelas di **log auth** (board DevSecOps modul 09, yang kamu buktikan): **brute** = banyak "Failed password" untuk **satu akun** dari satu IP (fail2ban menangkap ini — modul 03); **spraying** = banyak akun gagal dengan **password yang sama** dalam jendela waktu (pola yang lockout per-akun lewatkan, butuh deteksi korelasi — modul 09); **credential stuffing** = banyak user:pass unik dari banyak IP. Blue men-deteksi via: ambang login gagal, korelasi (satu password lintas akun), geo/velocity anomali, dan alert. Respons: fail2ban/IP block, lockout, step-up MFA, alert SOC. Ini menutup lingkaran: red brute = berisik; blue mendeteksi & memblokir. Deteksi + MFA + password kuat = serangan password tersendat di banyak titik.'
    source: https://attack.mitre.org/techniques/T1110/

  - id: b14
    tier: B
    type: text
    objective: strategy
    prompt: "Setelah meng-crack satu password, teknik apa yang memperbesar dampaknya ke banyak sistem?"
    answers: ['credential reuse / password spraying dengan kredensial itu — coba di servis/host lain karena orang pakai ulang password (lateral movement)']
    patterns: ['credential reuse|pakai ulang[^\n]*(password|kredensial)', '(coba|pakai)[^\n]*(kredensial|password)[^\n]*(servis|host|sistem) (lain)?', 'lateral movement']
    tests:
      fail: ['satu password hanya untuk satu sistem', 'tidak bisa diperbesar', 'password tidak pernah dipakai ulang', 'tidak ada teknik lanjut']
    explanation: 'Satu password yang di-crack sering membuka banyak pintu karena orang **memakai ulang password** lintas sistem — **credential reuse**. Teknik: coba kredensial itu di servis/host lain (SSH, email, panel admin, VPN) — via modul login Metasploit (modul 05 b11: `ssh_login`/`smb_login` dengan `creds`), `psexec`, atau manual. Ini **lateral movement** (bergerak dari satu sistem ke lainnya). Satu kredensial lemah bisa jadi penguasaan luas. Pelajaran blue (board DevSecOps modul 03/04): **password unik per sistem** (password manager — reuse mematikan ini), MFA (kredensial saja tak cukup), least privilege & segmentasi (batasi ke mana satu kredensial bisa dipakai). Mengubah "satu akun jebol" dari "semua jatuh" jadi "satu akun terbatas".'
    source: https://attack.mitre.org/techniques/T1110/

  - id: b15
    tier: B
    type: text
    objective: offline
    prompt: Password "P@ssw0rd2024!" terasa kuat bagi pengguna. Mengapa cracker modern bisa menebaknya relatif mudah?
    answers: ['ia mengikuti pola umum (kata+leet+tahun+simbol) yang ditangkap mangling rules/wordlist; kompleksitas berpola ≠ keacakan — entropi sebenarnya rendah']
    patterns: ['pola (umum|predictable|yang bisa ditebak)[^\n]*(kata|leet|tahun|simbol)', '(mangling|rules|wordlist)[^\n]*(tangkap|cover|menebak)', '(kompleksitas|complexity)[^\n]*(bukan|≠|tidak sama)[^\n]*(keacakan|entropi|random)']
    tests:
      fail: ['password itu benar-benar acak', 'cracker tidak bisa menebaknya', 'kompleksitas sama dengan keacakan', 'aman dari mangling rules']
    explanation: '"P@ssw0rd2024!" terasa kuat (ada huruf besar/kecil, angka, simbol — memenuhi "aturan kompleksitas"), tapi mengikuti **pola yang sangat umum**: kata dasar (`Password`) + leetspeak (`@`,`0`) + tahun (`2024`) + simbol di akhir (`!`). **Mangling rules** (a07) & wordlist tepat menangkap pola ini — cracker modern menebaknya relatif cepat. **Kompleksitas berpola ≠ keacakan**: entropi sebenarnya rendah karena bisa diprediksi. Pelajaran blue terbesar (board DevSecOps modul 03/04): **aturan kompleksitas tradisional menyesatkan** — dorong **passphrase panjang** (beberapa kata acak) atau **password acak dari manager** (entropi tinggi, tak berpola), bukan "kata+angka+simbol" yang terasa kuat tapi lemah. NIST modern merekomendasikan panjang > kompleksitas berpola. Red membuktikan mengapa.'
    source: https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: strategy
    prompt: |
      Enumerasi memberi daftar user (msfadmin, user, service) dan servis login terbuka (SSH 22, FTP 21, web 80).
      Rancang strategi serangan password yang efisien & berurutan (satu poin per baris).
    checklist:
      - id: default
        label: Coba kredensial default/umum dulu (msfadmin:msfadmin, dll) — tercepat
        required: true
        patterns: ['(default|umum)[^\n]*(kredensial|password)[^\n]*(dulu|coba)', 'msfadmin|admin:admin|root:toor']
      - id: spray
        label: Password spraying (satu password umum ke semua user) — hindari lockout
        required: true
        patterns: ['spraying|spray', '(satu password|password umum)[^\n]*(banyak|semua) (user|akun)', 'hindari lockout']
      - id: targetservice
        label: Arahkan Hydra ke servis yang menerima password (cek metode auth dulu)
        patterns: ['hydra', '(servis|ssh|ftp|web)[^\n]*(terima|password auth)|cek[^\n]*(metode auth|ssh-auth)']
      - id: offline
        label: Jika dapat hash (SQLi/shadow) → crack offline (John/hashcat) lebih kuat
        patterns: ['(hash|shadow)[^\n]*(crack|offline)', 'john|hashcat', 'offline[^\n]*(kuat|cepat)']
      - id: reuse
        label: Pakai ulang kredensial yang didapat ke servis/host lain (lateral)
        patterns: ['credential reuse|pakai ulang[^\n]*(kredensial|servis|host)', 'lateral']
      - id: defense
        label: Catat pertahanan blue (MFA, lockout, key-only, hash kuat, password unik)
        patterns: ['mfa|lockout|key-?only|hash (kuat|lambat)|password unik', 'pertahanan|blue|remediasi']
      - id: no-blind
        label: Tidak langsung brute masif buta tanpa coba default/spraying dulu
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain|sebelum))[^\n]*(langsung|asal)[^\n]*brute[^\n]*(masif|buta|semua|tanpa)']
    pass_score: 0.5
    solution: |
      ```text
      1. Coba DEFAULT/umum dulu: msfadmin:msfadmin, admin:admin, root:toor — tercepat, sering langsung berhasil (modul 03).
      2. Password SPRAYING: satu password umum (Password123) ke SEMUA user valid → hindari lockout per-akun.
      3. Arahkan Hydra ke servis yang terima password (cek ssh-auth-methods dulu, modul 03 b09): hydra -L users -P pw ssh://...
      4. Jika dapat HASH (SQLi modul 06 / /etc/shadow via akses): crack OFFLINE (John/hashcat) — jauh lebih kuat & senyap.
      5. Kredensial yang didapat → REUSE ke servis/host lain (ssh_login/smb_login, modul 05 b11) = lateral movement.
      6. (Blue) pertahanan: MFA, lockout cerdas + deteksi spraying, key-only SSH, hash lambat ber-salt, password unik.
      ```
      Urutan efisien: default → spraying → brute terarah → (bila dapat hash) offline → reuse. Brute masif buta itu lambat,
      berisik, memicu lockout, dan sering tak perlu. Biarkan enumerasi mengarahkan.
    tests:
      pass:
        - |
          coba kredensial default dan umum dulu seperti msfadmin msfadmin yang tercepat
          lakukan password spraying satu password umum ke semua user untuk hindari lockout
          arahkan hydra ke servis yang menerima password setelah cek metode auth
          jika dapat hash dari sqli atau shadow crack offline dengan john atau hashcat yang lebih kuat
          pakai ulang kredensial yang didapat ke servis atau host lain untuk lateral movement
          catat pertahanan blue mfa lockout key only hash kuat dan password unik
          jangan langsung brute masif buta tanpa coba default atau spraying dulu
        - |
          default dulu lalu spraying hindari lockout, hydra ke ssh setelah cek auth
          kalau dapat hash crack offline john hashcat, reuse kredensial ke host lain, pertahanan mfa lockout key only hash kuat, jangan brute buta
      fail:
        - |
          langsung brute masif buta semua user dan password
          tidak perlu default atau spraying
        - |
          tebak acak tanpa daftar user
          pertahanan tidak dicatat
        - |
          asal hydra semua servis tanpa cek
          offline cracking tidak perlu
    source: https://attack.mitre.org/techniques/T1110/

  - id: c02
    tier: C
    type: scenario
    objective: offline
    prompt: |
      Via SQLi (modul 06) kamu mengekstrak tabel users berisi hash password. Rancang proses meng-crack-nya offline &
      memanfaatkan hasilnya (satu poin per baris).
    checklist:
      - id: identify
        label: Identifikasi jenis hash (prefix/panjang; hashid) untuk pilih mode
        required: true
        patterns: ['identifikasi[^\n]*(hash|jenis)', 'hashid|prefix|\$2y|\$6|panjang hash']
      - id: crack
        label: Crack dengan John/hashcat + wordlist (rockyou) + rules
        required: true
        patterns: ['john|hashcat', 'wordlist|rockyou', '--rules|rules|mangling'
        ]
      - id: wordlist
        label: Pakai wordlist dari breach nyata + aturan mangling (variasi)
        patterns: ['rockyou|seclists|breach', 'mangling|rules|variasi']
      - id: reuse
        label: Pakai password yang di-crack untuk login (reuse/lateral ke servis lain)
        patterns: ['(pakai|login)[^\n]*(password|kredensial)[^\n]*(crack|didapat)', 'reuse|lateral']
      - id: document
        label: Dokumentasikan (hash lemah/terkrack) sebagai temuan laporan
        patterns: ['dokumentasi|catat|laporan', '(hash|password) (lemah|terkrack|berhasil)']
      - id: defense
        label: Pertahanan blue - hash lambat ber-salt (bcrypt/argon2) + password kuat + jangan bocor (prepared stmt)
        required: true
        patterns: ['(bcrypt|argon2|hash lambat|salt)', '(prepared statement|jangan bocor|lindungi hash)|password kuat']
      - id: no-plaintext
        label: Tidak menyimpulkan "aman" bila hash ternyata MD5/plaintext (justru temuan kritis)
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain))[^\n]*(aman|tidak masalah)[^\n]*(md5|plaintext|hash cepat)']
    pass_score: 0.5
    solution: |
      ```text
      1. Identifikasi jenis hash: prefix ($2y$=bcrypt, $6$=sha512crypt, 32-hex=MD5/NTLM) / hashid → pilih mode (--format / -m).
      2. Crack: john --wordlist=rockyou.txt --rules hashes.txt (atau hashcat -m <mode> dgn GPU). (lab: John crack MD5 instan.)
      3. Wordlist dari breach nyata (rockyou) + mangling rules → tangkap password manusiawi berpola.
      4. Password yang di-crack → login & reuse ke servis/host lain (lateral, modul 05 b11).
      5. Dokumentasikan: "N hash lemah di-crack, algoritma <X>" — temuan laporan (modul 10). Hash MD5/plaintext = CRITICAL.
      6. (Blue) pertahanan: simpan password dengan bcrypt/argon2 + salt unik (lambat), jangan biarkan hash bocor
         (prepared statement anti-SQLi modul 06), password kuat/unik + MFA (modul 03/04).
      ```
      Alur: identifikasi → crack (wordlist+rules) → reuse → dokumentasi + pertahanan. Jika hash ternyata MD5/plaintext,
      itu BUKAN "aman" — itu temuan kritis (board DevSecOps modul 04: password storage).
    tests:
      pass:
        - |
          identifikasi jenis hash dari prefix dan panjang pakai hashid untuk pilih mode
          crack dengan john atau hashcat memakai wordlist rockyou dan aturan rules
          pakai wordlist dari breach nyata plus mangling untuk tangkap variasi
          pakai password yang di-crack untuk login reuse dan lateral ke servis lain
          dokumentasikan hash lemah yang terkrack sebagai temuan laporan
          pertahanan blue hash lambat ber-salt bcrypt argon2 password kuat dan jangan bocor prepared statement
          jangan simpulkan aman bila hash ternyata md5 atau plaintext itu temuan kritis
        - |
          hashid untuk jenis, john wordlist rockyou plus rules atau hashcat gpu
          reuse password ke host lain, dokumentasikan hash lemah, pertahanan bcrypt argon2 salt dan prepared statement anti sqli, md5 plaintext itu kritis bukan aman
      fail:
        - |
          crack tanpa identifikasi jenis hash
          kalau md5 berarti aman tidak masalah
        - |
          tebak mode acak di hashcat
          pertahanan hash tidak dicatat
        - |
          plaintext di db tidak masalah
          tidak perlu bcrypt atau salt
    source: https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html

  - id: c03
    tier: C
    type: scenario
    objective: purple
    prompt: |
      Purple: kamu baru membuktikan serangan password berhasil (Hydra online + John offline). Rancang rekomendasi
      pertahanan menyeluruh untuk tim BLUE (dipetakan ke board DevSecOps) (satu poin per baris).
    checklist:
      - id: mfa
        label: MFA di mana pun bisa (password saja tak cukup) — pertahanan terkuat
        required: true
        patterns: ['mfa|multi-?faktor|2fa', '(password saja|kredensial saja)[^\n]*(tak cukup|tidak cukup)?']
      - id: lockout
        label: Lockout cerdas + rate-limit + deteksi (brute & spraying) — lawan online
        required: true
        patterns: ['lockout|rate-?limit', '(deteksi|fail2ban)[^\n]*(brute|spray|login gagal)']
      - id: keyonly
        label: SSH key-only (matikan PasswordAuthentication) — hilangkan jalur brute
        patterns: ['key-?only|passwordauthentication no', '(matikan|nonaktif)[^\n]*password[^\n]*(auth|ssh)']
      - id: stronghash
        label: Simpan password dengan hash lambat ber-salt (bcrypt/argon2) — lawan offline
        required: true
        patterns: ['(bcrypt|argon2|scrypt|hash lambat)', 'salt']
      - id: strongpw
        label: Password kuat/unik (passphrase/manager) + larang umum/bocoran + default
        patterns: ['password (kuat|unik|passphrase)|password manager', 'larang[^\n]*(umum|bocoran|default)|hibp']
      - id: protecthash
        label: Lindungi hash agar tak bocor (anti-SQLi, shadow root-only, dump aman)
        patterns: ['(lindungi|jangan bocor)[^\n]*hash', 'prepared statement|anti-?sqli|shadow[^\n]*root']
      - id: no-complexity
        label: Tidak mengandalkan "aturan kompleksitas" berpola sebagai jaminan
        forbid: true
        patterns: ['^(?![^\n]*(jangan|bukan|tidak|selain))[^\n]*(cukup|andalkan|hanya)[^\n]*(aturan kompleksitas|kata\+angka\+simbol)[^\n]*(cukup|aman|jaminan)']
    pass_score: 0.45
    solution: |
      ```text
      1. MFA di mana pun (terutama admin/akses jarak jauh) — pertahanan terkuat; password yang bocor/di-crack tak cukup (a14).
      2. Lawan ONLINE: lockout cerdas + rate-limit (modul 08) + deteksi brute/spraying + fail2ban (modul 03/09).
      3. SSH key-only (PasswordAuthentication no) — hilangkan jalur brute password sepenuhnya (modul 03, kamu buktikan).
      4. Lawan OFFLINE: simpan password dengan bcrypt/argon2 + salt unik (lambat → crack mahal; modul 04).
      5. Password kuat/unik (passphrase/password manager) + larang password umum/bocoran (HIBP) + ganti semua default.
      6. Lindungi hash agar tak bocor: prepared statement (anti-SQLi, modul 06), /etc/shadow root-only, amankan dump.
      ```
      Berlapis: MFA (payung), lockout/key-only (online), hash lambat+lindungi (offline), password kuat (fondasi).
      JANGAN andalkan "aturan kompleksitas" berpola (kata+angka+simbol) — terasa kuat tapi lemah (b15); dorong panjang/keacakan.
    tests:
      pass:
        - |
          terapkan mfa di mana pun bisa karena password saja tak cukup pertahanan terkuat
          lawan online dengan lockout cerdas rate limit dan deteksi brute serta spraying fail2ban
          ssh key only matikan passwordauthentication untuk hilangkan jalur brute
          simpan password dengan hash lambat ber-salt bcrypt atau argon2 untuk lawan offline
          password kuat unik passphrase atau manager larang password umum bocoran dan default
          lindungi hash agar tak bocor dengan prepared statement anti sqli dan shadow root only
          jangan mengandalkan aturan kompleksitas berpola sebagai jaminan
        - |
          mfa payung utama, lockout rate limit dan deteksi untuk online, ssh key only
          bcrypt argon2 salt untuk offline, passphrase manager larang bocoran, lindungi hash anti sqli, jangan andalkan kompleksitas berpola
      fail:
        - |
          cukup aturan kompleksitas kata angka simbol sudah aman
          tidak perlu mfa atau hash kuat
        - |
          password panjang saja cukup tanpa mfa atau lockout
          hash md5 tidak masalah
        - |
          andalkan lockout saja untuk semua
          offline cracking tidak bisa dicegah
    source: https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html
---

# Password Attacks

Kredensial lemah adalah **jalur bobol dunia-nyata #1** — sering tak butuh exploit canggih, cukup login dengan password
yang bisa ditebak. Modul ini: serangan online (Hydra), offline cracking (John/hashcat), wordlist & rules, dan
strategi — lalu dipetakan ke pertahanan blue.

> [!NOTE]
> Diverifikasi penulis di lab terisolasi: **John** meng-crack raw-MD5 `password123` (user bob) dari wordlist;
> **Hydra** brute SSH menemukan `bob:password123` ("1 valid password found"). hashcat, mangling rules, HTTP-form
> brute, rockyou didokumentasikan — kamu jalankan di Kali. Semua pada target/hash milik sendiri.

## 1. Online vs Offline

| | Online (Hydra) | Offline (John/hashcat) |
|---|---|---|
| Cara | login langsung ke servis hidup | crack hash dicuri di mesin sendiri |
| Kecepatan | lambat (dibatasi servis/jaringan) | sangat cepat (GPU: miliaran/detik) |
| Terdeteksi? | ya (log, lockout, fail2ban) | tidak (tak sentuh target) |
| Syarat | daftar user + servis login | punya hash dulu (SQLi/shadow/dump) |
| Lawan (blue) | lockout, rate-limit, MFA, key-only | hash lambat ber-salt + lindungi hash |

## 2. Perintah inti (hasil lab)

```text
hydra -l bob -P pw.txt ssh://192.168.56.101           → online brute SSH (lab: dapat bob:password123)
hydra -L users -P pw 192.168.56.101 http-post-form "..."→ brute form web
john --wordlist=rockyou.txt hashes.txt                 → offline crack (lab: crack password123)
john --show hashes.txt                                 → tampilkan hasil (lab: bob:password123)
john --wordlist=rockyou.txt --rules hashes.txt         → + mangling (variasi: Password123!)
hashcat -m 1000 ntlm.txt rockyou.txt                   → GPU crack (NTLM mode 1000)
unshadow passwd shadow > out ; john out                → crack /etc/shadow
```

## 3. Wordlist, rules, hash

- **rockyou.txt** (~14 juta password dari breach nyata) — efektif karena orang pakai password umum berulang.
- **Mangling rules** — variasi kata (`password`→`Password123!`); kompleksitas berpola ≠ keacakan (b15).
- **Identifikasi hash** (prefix `$2y$`=bcrypt, `$6$`=sha512crypt; `hashid`) → pilih mode (`--format`/`-m`).

## 4. Strategi: default → spraying → brute

Coba **default/umum** dulu (tercepat, modul 03 b12) → **spraying** (satu password, banyak user → hindari lockout,
a10/b04) → brute terarah (cek metode auth dulu). Dapat hash? → **offline** jauh lebih kuat. Kredensial didapat →
**reuse/lateral** ke sistem lain (b14).

## 5. Purple — dua serangan, lapisan pertahanan (→ DevSecOps 03/04)

| Serangan (red) | Pertahanan (blue) |
|---|---|
| online brute/spray (Hydra) | lockout + rate-limit (08) + **MFA** + key-only SSH (03) + deteksi (09) |
| offline crack (John/hashcat) | **hash lambat ber-salt** bcrypt/argon2 (04) + lindungi hash (anti-SQLi 06) |
| credential reuse/lateral | password **unik** per sistem + MFA + segmentasi/least-priv |
| wordlist/rules (password berpola) | passphrase panjang/acak + larang umum/bocoran (HIBP) |

> [!WARNING]
> HANYA pada target/hash milik sendiri di lab terisolasi (ATT&CK T1110). Pertahanan TUNGGAL terkuat = **MFA**:
> password akan bocor/di-crack suatu saat; MFA membuat itu tidak langsung berarti kompromi. "Aturan kompleksitas"
> (kata+angka+simbol) terasa kuat tapi lemah terhadap rules — dorong panjang & keacakan.

> [!NOTE]
> Lanjut ke modul 08 (Post-Exploitation & Privilege Escalation) — setelah dapat shell/akses: naik ke root, kumpulkan
> bukti dampak, dan pahami mengapa least-privilege & hardening (blue) membatasi semua ini.
