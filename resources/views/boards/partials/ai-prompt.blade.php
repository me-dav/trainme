Kamu adalah penulis materi belajar DevOps/DevSecOps. Buat SATU modul untuk board "{{ $board->title }}" dengan topik: [TULIS TOPIKNYA DI SINI].

File terlampir (template-modul-{{ $board->slug }}.md) adalah format WAJIB. Ikuti strukturnya persis, ganti semua isi contoh, lalu keluarkan hasilnya sebagai SATU file .md utuh di dalam satu code block, tanpa penjelasan lain.

Aturan isi:
1. Materi dari sumber primer: dokumentasi resmi, man page, atau standar (CIS, NIST, OWASP). Cantumkan URL-nya di `sources` dan di `source` setiap soal. Jangan mengarang opsi, flag, atau output. Kalau ragu sebuah perilaku benar, jangan dimasukkan.
2. Callout [!INSIGHT] hanya untuk pengalaman lapangan yang punya link sumber nyata (postmortem publik, thread Q&A, blog engineering). Tanpa sumber, jangan tulis insight.
3. Wajib ada bagian troubleshooting: urutan diagnosis yang benar dan kesalahan umum beserta akibatnya. Tulis perbedaan antar-distro/versi bila ada.
4. Soal dijawab dengan MENGETIK, bukan pilihan ganda:
   - Tier A, minimal 12 soal (type command/text): recall syntax dan konsep dasar.
   - Tier B, minimal 10 soal (type command/text): kombinasi opsi, audit, debugging.
   - Tier C, minimal 3 soal (type scenario): studi kasus multi-langkah, dengan `context` berisi output terminal/log yang realistis.
5. Soal command/text: tulis semua variasi jawaban benar yang wajar di `answers` atau `patterns`. Isi `tests.pass` dengan variasi benar dan `tests.fail` dengan kesalahan umum yang realistis. Semua `answers` dan `tests.pass` harus cocok, semua `tests.fail` tidak boleh cocok.
6. Soal scenario: checklist regex untuk langkah diagnosis dan perbaikan, item `required` untuk langkah yang wajib, item `forbid` untuk anti-pattern (mis. chmod 777, disable firewall). Minimal satu `tests.pass` berisi jawaban lengkap.
7. Semua nilai jawaban berupa string. Pakai kutip tunggal untuk command dan regex; kutip tunggal di dalamnya ditulis dua kali. Regex beri jangkar ^...$.
8. `flags_any_order: true` hanya boleh kalau tidak ada opsi yang menerima argumen.
9. `id` modul diawali `{{ $board->slug }}-`, `status: draft`, `version: 1`, `order: {{ $board->modules->count() + 1 }}`, `updated` = tanggal hari ini.
10. Penjelasan dalam Bahasa Indonesia; istilah teknis, command, dan output tetap dalam bentuk aslinya.
