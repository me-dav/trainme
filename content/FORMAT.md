# Format file modul — v1

Kontrak antara pembuat konten (Claude, kamu, atau orang lain) dan aplikasi.
**Satu file = satu modul**: Markdown dengan frontmatter YAML. Frontmatter berisi metadata
dan soal; body Markdown berisi materi.

Validasi & self-test: `php artisan dojo:import path/ke/file.md --dry-run`, atau tombol **Impor modul**
di halaman board. File yang tidak lolos tidak akan tersimpan sama sekali.

Cara termudah membuat modul baru: buka board, klik **Impor modul**, unduh template (file ini dalam
bentuk contoh yang lolos validasi), lalu salin prompt yang disediakan ke AI.

## Board

Modul dikelompokkan per **board** (Linux, Docker, ...), dibuat di aplikasi.

| Cara impor | Board ditentukan oleh | `status` |
|---|---|---|
| Unggah di halaman board | board tempat mengunggah; `course` di file diabaikan (ada peringatan bila beda) | selalu `draft`, tandai terverifikasi di halaman modul |
| `php artisan dojo:import` | `course.slug` di file (board dibuat bila belum ada) | sesuai file |

Impor lewat terminal tidak mengubah nama/deskripsi board yang sudah ada. Mengunggah modul yang sudah
ada ke board lain memindahkan modul itu (pratinjau memberi peringatan).

## Kerangka

```yaml
---
format: 1                      # wajib, versi format ini
id: linux-permission-ownership # wajib, slug unik global — jangan diganti setelah diimpor
course:                        # hanya untuk impor lewat terminal; diabaikan saat unggah ke board
  slug: linux                  # slug board
  title: Linux                 # dipakai bila board belum ada
  description: ...             # opsional
title: Permission & Ownership  # wajib
summary: ...                   # opsional, tampil di dashboard
order: 2                       # urutan di dalam course (default 0)
version: 1                     # wajib, naikkan setiap kali isi berubah
status: verified               # draft | verified
updated: '2026-11-19'          # string (pakai tanda kutip)
tested_on:                     # di mana command diverifikasi
  - Ubuntu 24.04.5 LTS — GNU coreutils 9.4
quiz:                          # jumlah soal per sesi (default A:10 B:8 C:2)
  A: 10
  B: 8
  C: 2
objectives:                    # id dipakai oleh field `objective` di soal
  - id: chmod-symbolic
    text: Mengubah permission dengan mode simbolik
sources:                       # sumber utama materi (http/https)
  - title: GNU Coreutils — File permissions
    url: https://www.gnu.org/software/coreutils/manual/html_node/File-permissions.html
questions:
  - ...                        # lihat di bawah
---

# Judul materi

Markdown biasa (tabel GFM, code block, ```mermaid, callout).
```

## Tier

| Tier | Isi | Lulus |
|---|---|---|
| `A` | Recall syntax & konsep dasar | ≥ 80% membuka B |
| `B` | Kombinasi opsi, audit, debugging | ≥ 80% membuka C |
| `C` | Studi kasus multi-langkah (`scenario`) | — |

Batas lulus diatur lewat `DOJO_PASS_SCORE` di `.env`.

## Field umum setiap soal

| Field | Wajib | Keterangan |
|---|---|---|
| `id` | ya | unik di modul, mis. `a01`. Soal dicocokkan lewat id saat impor ulang |
| `tier` | ya | `A`, `B`, `C` |
| `type` | ya | `command`, `text`, `scenario` |
| `objective` | — | harus ada di `objectives` |
| `prompt` | ya | Markdown |
| `context` | — | blok teks monospace (output terminal, log, config) |
| `explanation` | — | Markdown, tampil setelah menjawab |
| `source` | disarankan | URL dokumentasi; peringatan bila kosong |
| `tests.pass` | — | contoh jawaban yang **harus** dinilai benar |
| `tests.fail` | — | contoh jawaban yang **harus** dinilai salah |

Semua nilai jawaban harus **string**. Tulis `'0644'`, bukan `0644` (YAML akan
mengubahnya jadi angka). Pakai tanda kutip tunggal untuk command dan regex; kutip tunggal di
dalamnya ditulis dua kali (`''{}''`).

## `type: command` — ketik perintah

```yaml
- id: b02
  tier: B
  type: command
  prompt: ...
  answers: ['chmod -R u=rwX,go=rX /srv/app']   # jawaban pertama = jawaban yang ditampilkan
  flags_any_order: true                         # opsional
  patterns: ['^chmod (-R|--recursive) ... /srv/app/?$']
  tests:
    pass: ['chmod u=rwX,go=rX -R /srv/app']
    fail: ['chmod -R 755 /srv/app']
```

Urutan penilaian:

1. **Normalisasi** jawaban: hapus prompt `$ `, hapus awalan `sudo ` (kecuali `sudo -u ...`),
   rapikan spasi, buang tanda kutip shell (`'x'` → `x`, `"*.sh"` → `*.sh`),
   selesaikan escape (`\;` → `;`), gabungkan redirect (`2> /dev/null` → `2>/dev/null`),
   sambung baris `\`.
2. Sama persis dengan salah satu `answers` (setelah normalisasi) → benar.
3. Jika `flags_any_order: true`: urutan opsi diabaikan dan opsi gabungan dipecah
   (`-ld` = `-dl` = `-l -d`). Argumen posisi tetap harus berurutan. **Jangan** dipakai bila ada
   opsi yang menerima argumen (`find`, `setfacl -m`, `tar -f`), karena bisa salah menerima.
4. Cocok dengan salah satu `patterns` (regex PCRE, case-sensitive, dicocokkan ke bentuk
   ternormalisasi, beri jangkar `^...$`) → benar.

## `type: text` — isian singkat

Sama seperti `command`, tetapi tidak peka huruf besar/kecil, spasi dirapikan, dan tanda kutip/backtick
pembungkus serta titik di akhir dibuang. `patterns` juga case-insensitive.

## `type: scenario` — studi kasus

Jawaban multi-baris dinilai dengan checklist regex (case-insensitive, `^`/`$` per baris).

```yaml
- id: c01
  tier: C
  type: scenario
  prompt: ...
  context: |
    $ ls -ld /srv/app/storage
    drwxr-xr-x 5 root root 4096 ...
  checklist:
    - id: diagnose
      label: Memeriksa permission sepanjang path
      patterns: ['namei\s+-\S*[lmo]', 'ls\s+-\S*d']
    - id: give-access
      label: Memberi akses ke www-data
      required: true                 # wajib ada agar lulus
      patterns: ['(chown|chgrp)\s+.*www-data.*storage']
    - id: no-777
      label: Tidak memakai 777
      forbid: true                   # anti-pattern: bila terdeteksi, otomatis gagal
      patterns: ['chmod\s+(-\S+\s+)*0?777\b']
  pass_score: 0.6                    # opsional, default 0.6
  solution: |                        # Markdown, tampil setelah menjawab
    ```bash
    ...
    ```
  tests:
    pass: ['...']                    # WAJIB minimal satu
    fail: ['chmod -R 777 /srv/app/storage']
```

Lulus bila: rasio item non-`forbid` yang terpenuhi ≥ `pass_score`, semua `required` terpenuhi, dan
tidak ada `forbid` yang terdeteksi. Lulus = nilai penuh; gagal = setengah dari rasio.

## Self-test (otomatis saat impor)

- Setiap `answers` dan `tests.pass` harus dinilai **benar**.
- Setiap `tests.fail` harus dinilai **salah**.
- Setiap regex harus valid.

Kalau ada yang meleset, impor ditolak dengan pesan yang menyebut soal dan contohnya. Isi
`tests.fail` dengan kesalahan umum yang realistis. Itu cara paling murah untuk memastikan
pola regex tidak terlalu longgar.

## Body materi

- Markdown GFM: tabel, code block berbahasa (`bash`, `yaml`, `dockerfile`, `ini`, `json`, `text`).
- Diagram: blok ```` ```mermaid ````. Dirender di browser, dan HTML mentah di-escape.
- Callout: `> [!NOTE]`, `> [!TIP]`, `> [!WARNING]`, `> [!DANGER]`, `> [!INSIGHT]`.
- `## Heading` level 2 menjadi daftar isi.

## Aturan kualitas konten

1. **Sumber primer dulu**: man page, dokumentasi resmi, standar (CIS, NIST, OWASP).
2. **Insight lapangan** (`[!INSIGHT]`) wajib punya link sumber yang bisa diperiksa: postmortem publik,
   thread Q&A, blog engineering, atau dokumentasi. Insight tanpa sumber tidak dimasukkan.
3. **Command diverifikasi dengan dijalankan** di container/VM, lalu tulis lingkungannya di `tested_on`.
   Yang belum diverifikasi: `status: draft`.
4. Tulis perbedaan antar-distro bila ada.
5. Naikkan `version` setiap ada perubahan isi.

## Impor ulang

- Modul dicocokkan lewat `id`, soal lewat `id` soal.
- Soal yang hilang dari file **diarsipkan** (tidak muncul di kuis, tetapi riwayat jawaban tetap ada).
  Bila dikembalikan ke file, soal itu dipulihkan.
- Pratinjau menunjukkan soal baru, berubah, diarsipkan, dan tetap sebelum kamu konfirmasi.
