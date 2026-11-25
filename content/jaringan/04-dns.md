---
format: 1
id: net-dns-deep
course:
  slug: jaringan
  title: Jaringan
  description: Konsep jaringan untuk DevOps — TCP/IP, subnetting, routing, TCP/UDP, DNS, HTTP, TLS, proxy, firewall, VPN, dan troubleshooting.
title: DNS Mendalam
summary: Hierarki dan resolusi DNS (+trace), jenis record, authoritative vs resolver, TTL dan cache (kenapa "propagasi" lambat), negative caching, NXDOMAIN vs NODATA vs SERVFAIL, search domain dan ndots, truncation ke TCP, CNAME di apex, wildcard, dan split-horizon — dibuktikan dengan BIND + Unbound di container.
order: 4
version: 1
status: verified
updated: '2026-11-24'
tested_on:
  - Docker Desktop (Windows) — BIND 9 (authoritative dojo.test), Unbound (resolver rekursif), klien alpine:3.20 + bind-tools, tcpdump
  - dig +trace ke internet (root → .com → example.com)
quiz:
  A: 10
  B: 8
  C: 2
objectives:
  - id: hierarchy
    text: Memahami hierarki DNS dan proses resolusi rekursif
  - id: records
    text: Mengenal jenis record dan kegunaannya
  - id: cache
    text: Memahami TTL, cache resolver, dan negative caching
  - id: status
    text: Membedakan NXDOMAIN, NODATA, SERVFAIL, dan timeout
  - id: client
    text: Memahami resolver klien (search domain, ndots, nama absolut)
  - id: transport
    text: Memahami DNS di atas UDP/TCP dan truncation
  - id: ops
    text: Merencanakan perubahan DNS dan split-horizon
sources:
  - title: RFC 1034 — Domain Names, Concepts and Facilities
    url: https://www.rfc-editor.org/rfc/rfc1034
  - title: RFC 1035 — Domain Names, Implementation and Specification
    url: https://www.rfc-editor.org/rfc/rfc1035
  - title: RFC 2308 — Negative Caching of DNS Queries
    url: https://www.rfc-editor.org/rfc/rfc2308
  - title: RFC 2181 — Clarifications to the DNS Specification (CNAME and other data)
    url: https://www.rfc-editor.org/rfc/rfc2181
  - title: RFC 2782 — DNS SRV RR
    url: https://www.rfc-editor.org/rfc/rfc2782
  - title: RFC 8659 — DNS CAA Resource Record
    url: https://www.rfc-editor.org/rfc/rfc8659
  - title: RFC 6761 — Special-Use Domain Names (.test, .invalid)
    url: https://www.rfc-editor.org/rfc/rfc6761
  - title: RFC 7766 — DNS Transport over TCP
    url: https://www.rfc-editor.org/rfc/rfc7766
  - title: DNS Flag Day 2020 — EDNS buffer size 1232
    url: https://www.dnsflagday.net/2020/
  - title: resolv.conf(5) — search, ndots
    url: https://man7.org/linux/man-pages/man5/resolv.conf.5.html
  - title: Kubernetes — DNS for Services and Pods (ndots:5, dnsConfig)
    url: https://kubernetes.io/docs/concepts/services-networking/dns-pod-service/
  - title: unbound.conf(5) dan unbound-control(8)
    url: https://unbound.docs.nlnetlabs.nl/en/latest/manpages/unbound.conf.html
  - title: BIND 9 Administrator Reference Manual
    url: https://bind9.readthedocs.io/
questions:
  # ---------------------------------------------------------------- Tier A
  - id: a01
    tier: A
    type: text
    objective: records
    prompt: Jenis record DNS untuk alamat **IPv6**?
    answers: ['AAAA']
    tests:
      fail: ['A', 'A6', 'IPV6', 'PTR']
    explanation: 'A = IPv4, AAAA ("quad-A") = IPv6. Di lab, `dig AAAA api.dojo.test` → status `NOERROR` dengan 0 jawaban, karena nama ada tetapi tidak punya IPv6 (NODATA, tier B). Klien modern sering menanyakan A dan AAAA bersamaan.'
    source: https://www.rfc-editor.org/rfc/rfc1035

  - id: a02
    tier: A
    type: text
    objective: records
    prompt: Jenis record yang membuat satu nama menjadi **alias** dari nama lain?
    answers: ['CNAME']
    tests:
      fail: ['ALIAS', 'A', 'PTR', 'DNAME']
    explanation: 'Di lab, `www.dojo.test CNAME dojo.test.`, sehingga `dig +short A www.dojo.test` → `dojo.test.` lalu `10.62.0.100`, karena resolver mengikuti alias. CNAME tidak boleh berdampingan dengan record lain di nama yang sama, termasuk di apex (tier B). "ALIAS/ANAME/flattening" adalah fitur khusus provider, bukan record standar.'
    source: https://www.rfc-editor.org/rfc/rfc1034

  - id: a03
    tier: A
    type: text
    objective: records
    prompt: Jenis record yang menunjuk server penerima email domain (beserta prioritasnya)?
    answers: ['MX']
    tests:
      fail: ['MAIL', 'TXT', 'SRV', 'A']
    explanation: 'Di lab: `dig +short MX dojo.test` → `10 mail.dojo.test.`. Angka kecil = prioritas lebih tinggi. Target MX harus berupa nama yang punya A/AAAA, bukan IP dan bukan CNAME. Record email lainnya disimpan sebagai TXT (SPF, DKIM, DMARC).'
    source: https://www.rfc-editor.org/rfc/rfc1035

  - id: a04
    tier: A
    type: command
    objective: records
    prompt: Dengan `dig`, lakukan **reverse lookup** (PTR) untuk IP `10.62.0.101`, tampilkan ringkas.
    answers: ['dig +short -x 10.62.0.101']
    patterns: ['^dig (@\S+ )?(\+short -x|-x 10\.62\.0\.101 \+short|-x) ?(10\.62\.0\.101)?( \+short)?$']
    tests:
      pass: ['dig -x 10.62.0.101 +short', 'dig @10.62.0.54 +short -x 10.62.0.101']
      fail: ['dig PTR 10.62.0.101', 'dig +short 10.62.0.101', 'nslookup -x 10.62.0.101']
    explanation: '`-x` membalik IP menjadi `101.0.62.10.in-addr.arpa.` lalu menanyakan PTR. Di lab → `api.dojo.test.`. Reverse DNS dikelola pemilik blok IP (ISP/cloud), bukan pemilik domain. Itu penting untuk mail server, karena banyak penerima menolak IP tanpa PTR yang cocok.'
    source: https://www.rfc-editor.org/rfc/rfc1035

  - id: a05
    tier: A
    type: command
    objective: hierarchy
    prompt: Dengan `dig`, telusuri resolusi `example.com` dari **root server** ke bawah (bukan lewat cache resolver).
    answers: ['dig +trace example.com']
    patterns: ['^dig (\+trace( \+nodnssec)?|\+nodnssec \+trace) example\.com\.?( a)?$']
    tests:
      pass: ['dig +trace +nodnssec example.com', 'dig +trace example.com.']
      fail: ['dig example.com', 'dig +short example.com', 'traceroute example.com']
    explanation: 'Di lab: `. NS j.root-servers.net.` → `com. NS i.gtld-servers.net.` → `example.com. NS hera.ns.cloudflare.com.` → `example.com. A 172.66.147.243`, masing-masing dari server yang berbeda (b.root-servers.net, k.gtld-servers.net, elliott.ns.cloudflare.com). Inilah yang dilakukan resolver rekursif untuk setiap nama yang belum ada di cache.'
    source: https://www.rfc-editor.org/rfc/rfc1034

  - id: a06
    tier: A
    type: text
    objective: hierarchy
    prompt: Flag di header `dig` yang menandakan jawaban berasal dari server **authoritative** untuk zone itu?
    answers: ['aa']
    tests:
      fail: ['ra', 'rd', 'qr', 'ad']
    explanation: 'Di lab: `dig @10.62.0.53` (BIND) → `flags: qr aa rd`, sedangkan `dig @10.62.0.54` (Unbound) → `flags: qr rd ra` tanpa `aa`. `ra` = server mau melakukan rekursi, `rd` = klien meminta rekursi, `ad` = tervalidasi DNSSEC. Bila ragu apakah jawaban basi, tanya server authoritative langsung, karena jawabannya ber-`aa`.'
    source: https://www.rfc-editor.org/rfc/rfc1035

  - id: a07
    tier: A
    type: text
    objective: status
    prompt: Status (RCODE) DNS ketika nama yang ditanyakan **tidak ada sama sekali**?
    answers: ['NXDOMAIN']
    tests:
      fail: ['NOERROR', 'SERVFAIL', 'REFUSED', 'NOTFOUND']
    explanation: 'Di lab: `dig nope.dojo.test` → `status: NXDOMAIN`, disertai record SOA di bagian AUTHORITY. SOA itu menentukan berapa lama jawaban negatif ini di-cache (tier B). NXDOMAIN adalah jawaban yang **pasti**, bukan kegagalan.'
    source: https://www.rfc-editor.org/rfc/rfc2308

  - id: a08
    tier: A
    type: text
    objective: hierarchy
    prompt: Ada berapa **nama** root server DNS (a.root-servers.net sampai ...)?
    answers: ['13']
    tests:
      fail: ['12', '26', '1', '255']
    explanation: 'a sampai m.root-servers.net, 13 nama (lab `+trace`: daftar `. NS a..m.root-servers.net.`). Masing-masing dioperasikan sebagai ratusan instance anycast di seluruh dunia, jadi "13 server" adalah 13 identitas, bukan 13 mesin. Batas 13 berasal dari ukuran respons UDP 512 byte di desain awal.'
    source: https://www.iana.org/domains/root/servers

  - id: a09
    tier: A
    type: text
    objective: records
    prompt: Jenis record untuk menyimpan teks bebas, dipakai SPF, DKIM, DMARC, dan verifikasi kepemilikan domain?
    answers: ['TXT']
    tests:
      fail: ['SPF', 'MX', 'CAA', 'NOTE']
    explanation: 'Di lab: `dojo.test TXT "v=spf1 mx -all"`. Record TXT bisa sangat panjang (lab: `big.dojo.test` berisi 12 string), sampai melebihi batas UDP, sehingga jawabannya pindah ke TCP (tier B). Record tipe SPF khusus sudah usang. SPF ditulis sebagai TXT.'
    source: https://www.rfc-editor.org/rfc/rfc1035

  - id: a10
    tier: A
    type: text
    objective: records
    prompt: Jenis record yang membatasi **CA mana** yang boleh menerbitkan sertifikat TLS untuk domain?
    answers: ['CAA']
    tests:
      fail: ['TXT', 'TLSA', 'CERT', 'DS']
    explanation: 'Di lab: `dojo.test CAA 0 issue "letsencrypt.org"`. CA wajib memeriksa CAA sebelum menerbitkan sertifikat. Bila hanya letsencrypt.org yang diizinkan, CA lain harus menolak. Lupa menambahkan CA baru di CAA adalah penyebab umum "penerbitan sertifikat gagal" (modul TLS).'
    source: https://www.rfc-editor.org/rfc/rfc8659

  - id: a11
    tier: A
    type: text
    objective: records
    prompt: 'Record SRV `_sip._tcp.dojo.test` berisi `10 60 5060 sip.dojo.test.`. Port layanannya berapa?'
    answers: ['5060']
    tests:
      fail: ['10', '60', '53', '443']
    explanation: 'Format SRV: `prioritas bobot port target`, jadi prioritas 10, bobot 60, port **5060**, host `sip.dojo.test`. SRV dipakai untuk penemuan layanan (SIP, XMPP, LDAP, Kerberos, `_minecraft._tcp`, dan service discovery Kubernetes/Consul). Nama selalu berpola `_service._proto.nama`.'
    source: https://www.rfc-editor.org/rfc/rfc2782

  - id: a12
    tier: A
    type: text
    objective: cache
    prompt: Apa arti nilai TTL (mis. `30`) pada sebuah record DNS?
    answers: ['berapa detik record boleh di-cache oleh resolver']
    patterns: ['(detik|second).*(cache|simpan|disimpan)', '(cache|simpan|disimpan).*(detik|second|lama)']
    tests:
      pass: ['lama detik record boleh disimpan di cache', 'record di-cache selama 30 detik']
      fail: ['jumlah hop', 'waktu respons server', 'prioritas record', 'umur domain']
    explanation: 'Di lab: authoritative selalu menjawab `30`, sedangkan resolver menjawab `29`, lalu `22` tujuh detik kemudian (menghitung mundur). Selama TTL berlaku, resolver tidak bertanya lagi ke authoritative. Ini berbeda dari TTL di header IP (jumlah hop, modul 02).'
    source: https://www.rfc-editor.org/rfc/rfc1035

  - id: a13
    tier: A
    type: text
    objective: cache
    prompt: 'SOA `dojo.test` = `ns1.dojo.test. admin.dojo.test. 1 3600 600 86400 20`. Nilai terakhir (`20`) dipakai untuk apa (RFC 2308)?'
    answers: ['TTL negative caching (berapa lama NXDOMAIN di-cache)']
    patterns: ['(negati|nxdomain|tidak ada|nodata)']
    tests:
      pass: ['negative caching TTL', 'lama nxdomain di-cache']
      fail: ['serial', 'refresh', 'retry', 'expire']
    explanation: 'Urutan SOA: serial, refresh, retry, expire, **minimum**. Field terakhir sekarang berarti TTL cache untuk jawaban negatif. Di lab, `dig nope.dojo.test` menyertakan SOA dengan TTL 20, dan NXDOMAIN tetap dijawab ~20 detik walaupun record-nya sudah dibuat (tier B). Serial harus dinaikkan setiap kali zone diubah (lab: 1 → 2 → 3).'
    source: https://www.rfc-editor.org/rfc/rfc2308

  - id: a14
    tier: A
    type: text
    objective: records
    prompt: Zone punya `*.apps.dojo.test A 10.62.0.199`. Apakah `foo.bar.apps.dojo.test` (dua label di depan) juga dijawab `10.62.0.199`? (ya/tidak)
    answers: ['ya', 'yes']
    tests:
      fail: ['tidak', 'no']
    explanation: 'Di lab: `x.apps.dojo.test` → `10.62.0.199` dan `foo.bar.apps.dojo.test` → `10.62.0.199`. Wildcard DNS mencocokkan **satu atau lebih** label (selama tidak ada nama yang lebih spesifik). Ini berbeda dari sertifikat wildcard TLS `*.apps.dojo.test`, yang hanya mencakup **satu** label (modul TLS). Kombinasi keduanya sering membingungkan.'
    source: https://www.rfc-editor.org/rfc/rfc4592

  - id: a15
    tier: A
    type: text
    objective: transport
    prompt: Port standar DNS (UDP dan TCP)?
    answers: ['53']
    tests:
      fail: ['853', '443', '5353', '54']
    explanation: 'Query biasa memakai UDP 53. TCP 53 dipakai untuk jawaban besar (truncation, tier B) dan zone transfer, dan **wajib** didukung (RFC 7766), jadi firewall yang hanya membuka UDP 53 akan memutus jawaban besar. 853 = DNS-over-TLS, 443 = DNS-over-HTTPS, 5353 = mDNS.'
    source: https://www.rfc-editor.org/rfc/rfc7766

  # ---------------------------------------------------------------- Tier B
  - id: b01
    tier: B
    type: text
    objective: cache
    prompt: Record `api` diubah di authoritative (`10.62.0.101` → `10.62.0.111`) dan zone di-reload. Resolver yang sudah meng-cache `api` dengan TTL tersisa 21 detik akan menjawab apa?
    answers: ['10.62.0.101 (alamat lama) sampai TTL habis']
    patterns: ['(10\.62\.0\.101|lama|old|cache)']
    tests:
      pass: ['10.62.0.101', 'masih alamat lama sampai ttl habis']
      fail: ['10.62.0.111', 'nxdomain', 'servfail']
    explanation: 'Di lab: `auth: 10.62.0.111  resolver: 21 10.62.0.101`. Yang disebut "propagasi DNS" sebenarnya adalah cache di ribuan resolver yang kedaluwarsa satu per satu, paling lama selama TTL lama. Jadi TTL harus **diturunkan sebelum** perubahan, bukan saat perubahan (studi kasus C).'
    source: https://www.rfc-editor.org/rfc/rfc1034

  - id: b02
    tier: B
    type: command
    objective: cache
    prompt: Hapus entry `api.dojo.test` dari cache resolver **Unbound** (lewat alat kontrolnya).
    answers: ['unbound-control flush api.dojo.test']
    patterns: ['^unbound-control flush api\.dojo\.test\.?$']
    tests:
      pass: ['unbound-control flush api.dojo.test.']
      fail: ['unbound-control flush_zone', 'rndc flush api.dojo.test', 'systemctl restart unbound']
    explanation: 'Di lab: setelah `flush`, resolver langsung menjawab `10.62.0.111`. `flush_zone dojo.test` menghapus seluruh zone, `flush_infra all` menghapus status server upstream. Padanannya: BIND `rndc flushname`, systemd-resolved `resolvectl flush-caches`. Kamu hanya bisa mem-flush resolver milikmu sendiri, bukan resolver ISP pengguna.'
    source: https://unbound.docs.nlnetlabs.nl/en/latest/manpages/unbound-control.html

  - id: b03
    tier: B
    type: text
    objective: status
    prompt: '`dig AAAA api.dojo.test` → `status: NOERROR`, `ANSWER: 0`. Apa arti kombinasi ini?'
    answers: ['NODATA, nama ada tetapi tidak punya record tipe itu']
    patterns: ['(nodata|nama (ada|exist)|tidak (punya|ada) (record )?aaaa|tipe (record )?(itu )?tidak ada)']
    tests:
      pass: ['NODATA', 'nama ada tapi tidak punya AAAA']
      fail: ['nxdomain', 'nama tidak ada', 'server error', 'timeout']
    explanation: 'NODATA bukan RCODE tersendiri. Bentuknya NOERROR dengan jawaban kosong (plus SOA untuk negative caching). Di lab, `api.dojo.test` punya A tetapi tidak punya AAAA. Bedakan dari NXDOMAIN (nama sama sekali tidak ada). Klien dual-stack yang mendapat NODATA untuk AAAA akan memakai IPv4.'
    source: https://www.rfc-editor.org/rfc/rfc2308

  - id: b04
    tier: B
    type: text
    objective: cache
    prompt: '`new.dojo.test` ditanyakan **sebelum** dibuat (NXDOMAIN), lalu segera ditambahkan di authoritative. Apa jawaban resolver di detik-detik berikutnya?'
    answers: ['masih NXDOMAIN sampai TTL negatif (SOA minimum) habis']
    patterns: ['nxdomain', '(masih|tetap).*(tidak ada|negatif|cache)']
    tests:
      pass: ['masih NXDOMAIN', 'tetap nxdomain dari cache negatif']
      fail: ['10.62.0.120', 'servfail', 'langsung muncul']
    explanation: 'Di lab: `auth now: 10.62.0.120`, `resolver now: status: NXDOMAIN` (sisa TTL negatif 18), lalu baru setelah 21 detik → `10.62.0.120`. Jebakan klasik saat menerbitkan sertifikat atau mengecek record baru: kamu mengecek terlalu cepat, resolver meng-cache NXDOMAIN, lalu record yang sudah benar terlihat "belum muncul".'
    source: https://www.rfc-editor.org/rfc/rfc2308

  - id: b05
    tier: B
    type: text
    objective: status
    prompt: 'Resolver di-stub ke server yang **bukan** authoritative untuk zone `lame.test` (server itu membalas `REFUSED`). Status apa yang diterima klien dari resolver?'
    answers: ['SERVFAIL']
    tests:
      fail: ['NXDOMAIN', 'REFUSED', 'NOERROR', 'timeout']
    explanation: 'Di lab: `dig @10.62.0.53 www.lame.test` → `REFUSED`, sedangkan lewat resolver → `SERVFAIL` dalam 0 detik. Kondisi ini disebut **lame delegation**. Penyebab SERVFAIL lainnya: DNSSEC tidak valid, dan semua authoritative tidak bisa dihubungi. SERVFAIL berarti "resolver gagal mendapat jawaban", bukan "nama tidak ada".'
    source: https://www.rfc-editor.org/rfc/rfc1035

  - id: b06
    tier: B
    type: text
    objective: status
    prompt: Satu-satunya server authoritative `dojo.test` dimatikan dan cache resolver dikosongkan. Di lab, apa yang dialami klien yang bertanya ke resolver Unbound?
    answers: ['timeout, tidak ada balasan']
    patterns: ['(timeout|timed out|tidak ada (balasan|jawaban)|no response|hang|menunggu)']
    tests:
      pass: ['timeout', 'tidak ada balasan sampai klien menyerah']
      fail: ['nxdomain', 'jawaban lama', 'refused']
    explanation: 'Di lab, `dig +time=90` → tidak ada balasan sama sekali dalam 91 detik, dan query berikutnya juga 30 detik tanpa jawaban. Resolver terus mencoba upstream yang mati. Di sisi aplikasi, ini terlihat sebagai DNS lambat atau timeout, bukan error yang jelas. Karena itu zone production wajib punya ≥ 2 server authoritative di jaringan berbeda.'
    source: https://www.rfc-editor.org/rfc/rfc1034

  - id: b07
    tier: B
    type: text
    objective: client
    prompt: '`/etc/resolv.conf` berisi `search dojo.test svc.cluster.local` dan `options ndots:5`. Berapa query A yang dikirim untuk `getent hosts example.com` (yang hanya ada di internet)?'
    answers: ['3']
    tests:
      fail: ['1', '2', '5', '4']
    explanation: 'Di lab tcpdump: `example.com.dojo.test.`, `example.com.svc.cluster.local.`, lalu `example.com.`, jadi 3 query A. Nama dengan titik kurang dari `ndots` dicoba dengan setiap search domain **lebih dulu**. Kubernetes memakai `ndots:5`, sehingga setiap panggilan ke API eksternal bisa memicu banyak query yang sia-sia (studi kasus C).'
    source: https://man7.org/linux/man-pages/man5/resolv.conf.5.html

  - id: b08
    tier: B
    type: text
    objective: client
    prompt: Dengan `search dojo.test` dan `options ndots:1`, query apa yang dikirim untuk `example.com`?
    answers: ['example.com. saja']
    patterns: ['^example\.com\.?( saja| langsung| saja dulu)?$', '(hanya|langsung|cuma).*example\.com']
    tests:
      pass: ['example.com.', 'hanya example.com']
      fail: ['example.com.dojo.test.', '3 query', 'tidak ada']
    explanation: 'Di lab: hanya `A? example.com.`. `example.com` punya 1 titik ≥ ndots 1, sehingga dicoba sebagai nama absolut lebih dulu. Nama tanpa titik (`api`) tetap memakai search (lab: `api` → `api.dojo.test`). Menulis nama dengan titik di akhir (`example.com.`) membuatnya absolut dan selalu melewati search.'
    source: https://man7.org/linux/man-pages/man5/resolv.conf.5.html

  - id: b09
    tier: B
    type: text
    objective: transport
    prompt: Jawaban TXT `big.dojo.test` (1493 byte) diminta lewat UDP dengan buffer EDNS 1232. Flag apa yang muncul di jawaban UDP, dan apa yang dilakukan `dig` kemudian?
    answers: ['tc (truncated), lalu mengulang lewat TCP']
    patterns: ['(?=.*\btc\b|.*truncat)(?=.*tcp)']
    tests:
      pass: ['tc, lalu retry via TCP', 'truncated lalu pakai tcp']
      fail: ['aa saja', 'servfail', 'fragmentasi udp', 'tc tanpa retry']
    explanation: 'Di lab, dengan `+ignore`: `flags: qr aa tc rd`, `ANSWER: 0`, `MSG SIZE rcvd: 70`. Tanpa `+ignore`: `;; Truncated, retrying in TCP mode.`, lalu `MSG SIZE rcvd: 1493`. Bila TCP 53 diblokir firewall, nama dengan jawaban besar (banyak TXT, DNSSEC, banyak A) gagal di-resolve, sementara nama kecil lancar.'
    source: https://www.rfc-editor.org/rfc/rfc7766

  - id: b10
    tier: B
    type: text
    objective: transport
    prompt: Ukuran buffer UDP EDNS default yang diiklankan `dig` modern (dan direkomendasikan DNS Flag Day 2020)?
    answers: ['1232']
    tests:
      fail: ['512', '4096', '1500', '1472']
    explanation: 'Di lab: `; EDNS: version: 0, flags:; udp: 1232`. 1232 dipilih agar paket DNS hampir tidak pernah terfragmentasi (muat di MTU IPv6 minimum 1280 dikurangi header), karena fragmen IP sering hilang dan bisa dipalsukan. Dulu nilainya 4096, dan tanpa EDNS batasnya 512.'
    source: https://www.dnsflagday.net/2020/

  - id: b11
    tier: B
    type: text
    objective: records
    prompt: '`named-checkzone` menolak zone yang punya `@ IN CNAME other.example.net.` di apex. Pesan errornya?'
    answers: ['CNAME and other data']
    patterns: ['cname and other data']
    tests:
      pass: ['loading failed: CNAME and other data']
      fail: ['syntax error', 'bad owner name', 'out of zone data']
    explanation: 'Di lab: `loading from master file /tmp/bad.zone failed: CNAME and other data`. Pesan yang sama muncul untuk `api` yang punya CNAME dan A sekaligus. Apex selalu punya SOA dan NS, sehingga CNAME tidak bisa dipasang di sana. Untuk mengarahkan `example.com` (tanpa www) ke load balancer, pakai A/AAAA, atau fitur ALIAS/flattening dari provider DNS.'
    source: https://www.rfc-editor.org/rfc/rfc2181

  - id: b12
    tier: B
    type: text
    objective: ops
    prompt: Resolver internal menjawab `app.dojo.test` dengan `10.62.0.250` (local-data), sementara authoritative publik menjawab NXDOMAIN. Konsep apa ini?
    answers: ['split-horizon DNS']
    patterns: ['split[- ]?(horizon|brain|dns|view)', 'dns internal (berbeda|lain)', 'view']
    tests:
      pass: ['split horizon', 'split-brain dns', 'views berbeda internal/eksternal']
      fail: ['dns poisoning', 'round robin', 'anycast', 'dnssec']
    explanation: 'Di lab: `auth app: status: NXDOMAIN`, `resolver app: 10.62.0.250`. Nama yang sama dijawab berbeda tergantung siapa yang bertanya (lewat resolver internal vs internet). Ini berguna untuk IP privat di dalam kantor/VPC, tetapi membingungkan saat debugging: selalu catat **resolver mana** yang kamu tanya (`dig @...`).'
    source: https://bind9.readthedocs.io/

  - id: b13
    tier: B
    type: text
    objective: hierarchy
    prompt: Di `dig +trace example.com`, setelah root server memberi referral, server **jenis apa** yang memberi referral berikutnya ke name server `example.com`?
    answers: ['server TLD .com (gtld-servers.net)']
    patterns: ['(tld|gtld|\.com|com\.|\bcom\b)']
    tests:
      pass: ['TLD .com', 'gtld-servers.net', 'name server com.']
      fail: ['root lagi', 'resolver isp', 'cloudflare', 'registrar']
    explanation: 'Di lab: root → `com. NS i.gtld-servers.net.` (jawaban dari `b.root-servers.net`) → `example.com. NS hera.ns.cloudflare.com.` (dari `k.gtld-servers.net`) → A dari `elliott.ns.cloudflare.com`. Saat domain dipindah provider DNS, yang diubah di registrar adalah delegasi NS di TLD ini, dan cache delegasi di TLD punya TTL sendiri (sering 2 hari).'
    source: https://www.rfc-editor.org/rfc/rfc1034

  - id: b14
    tier: B
    type: text
    objective: client
    prompt: 'Kenapa nama `dojo.test` tidak bisa di-resolve oleh Unbound default (jawaban `aa` kosong) sampai ditambahkan `local-zone: "test." nodefault`?'
    answers: ['.test adalah special-use domain (RFC 6761), diblokir default oleh resolver']
    patterns: ['(special[- ]use|6761|reserved|dicadangkan|khusus|local-zone (bawaan|default)|static)']
    tests:
      pass: ['.test special-use domain RFC 6761', 'unbound punya local-zone default static untuk test.']
      fail: ['zone salah', 'firewall', 'ttl', 'dnssec gagal']
    explanation: 'Di lab: `unbound-control list_local_zones` → `test. static` dan `invalid. static`. Resolver memperlakukan TLD khusus (RFC 6761) secara lokal agar tidak bocor ke root. Pakai `.test`, `.example`, `.invalid`, atau `home.arpa` untuk jaringan internal. Jangan pakai TLD karangan seperti `.local` (mDNS) atau `.dev` (TLD sungguhan milik Google).'
    source: https://www.rfc-editor.org/rfc/rfc6761

  - id: b15
    tier: B
    type: text
    objective: cache
    prompt: '`dig @resolver api.dojo.test` dua kali berselang 7 detik menunjukkan TTL `29` lalu `22`, sementara `dig @authoritative` selalu `30`. Kenapa berbeda?'
    answers: ['resolver menampilkan sisa TTL cache yang menghitung mundur']
    patterns: ['(cache|sisa|mundur|berkurang|countdown|dari cache)']
    tests:
      pass: ['resolver menampilkan sisa waktu cache', 'ttl di cache menghitung mundur']
      fail: ['resolver salah konfigurasi', 'record berubah', 'jaringan lambat']
    explanation: 'Resolver menyimpan record beserta waktu kedaluwarsanya, lalu menjawab dengan sisa TTL. Di lab: 29 (baru di-cache) → 22 (7 detik kemudian). Authoritative selalu menjawab TTL penuh dari zone file. Membaca TTL di jawaban resolver langsung memberi tahu kapan paling lambat ia akan bertanya lagi.'
    source: https://www.rfc-editor.org/rfc/rfc1035

  # ---------------------------------------------------------------- Tier C
  - id: c01
    tier: C
    type: scenario
    objective: ops
    prompt: |
      Aplikasi `app.example.com` (record A, TTL 86400) akan dipindah ke server baru dengan IP berbeda Sabtu malam.
      Target: hampir tidak ada pengguna yang diarahkan ke server lama setelah migrasi. Tulis rencana langkah demi
      langkah (satu poin per baris) beserta verifikasinya.
    checklist:
      - id: lower-ttl
        label: Menurunkan TTL (mis. 300/60) jauh hari sebelumnya
        required: true
        patterns: ['(turunkan|kecilkan|ubah|set|lower).*ttl', 'ttl.*(300|60|120|600)']
      - id: wait-old
        label: Menunggu TTL lama (≥ 24 jam) habis sebelum migrasi
        required: true
        patterns: ['(tunggu|menunggu|wait).*(24|86400|sehari|ttl lama)', '(24 jam|86400|1 hari|sehari).*(sebelum|tunggu|dulu)']
      - id: change
        label: Mengubah record A ke IP baru (dan naikkan serial)
        patterns: ['(ubah|ganti|update|change).*(record|a)\b', 'serial']
      - id: verify-auth
        label: Verifikasi di authoritative dan beberapa resolver (dig @ns, @1.1.1.1, @8.8.8.8)
        required: true
        patterns: ['dig\s+@\S+']
      - id: keep-old
        label: Server lama tetap hidup (atau proxy ke baru) sampai TTL pendek habis
        patterns: ['(server lama|old server).*(tetap|jalan|hidup|proxy|redirect)', '(jangan matikan|biarkan).*(lama)']
      - id: raise
        label: Menaikkan TTL lagi setelah stabil
        patterns: ['(naikkan|kembalikan|raise).*ttl']
      - id: no-flush-claim
        label: Tidak berasumsi bisa flush cache resolver publik / mengandalkan /etc/hosts
        forbid: true
        patterns: ['flush.*(semua|seluruh|isp|publik|internet)', '/etc/hosts']
    pass_score: 0.6
    solution: |
      ```text
      H-2 (≥ 1×TTL lama sebelum migrasi): turunkan TTL app.example.com 86400 → 300, naikkan serial
      tunggu ≥ 24 jam agar semua cache yang memegang TTL 86400 kedaluwarsa
      verifikasi: dig @ns1.example.com app.example.com (aa, TTL 300); dig @1.1.1.1 / @8.8.8.8 → TTL ≤ 300
      hari H: siapkan server baru, uji langsung via IP / curl --resolve app.example.com:443:<IP_BARU>
      ubah A → IP baru (serial +1), cek authoritative langsung menjawab IP baru
      server lama tetap hidup (atau proxy ke server baru) ≥ 300 s + margin; pantau log akses di server lama
      setelah stabil: kembalikan TTL ke 3600–86400
      ```
      Semua langkah ini berangkat dari perilaku cache yang diukur di lab:
      - Resolver menjawab alamat **lama** selama sisa TTL (lab: `resolver: 21 10.62.0.101` padahal authoritative
        sudah `10.62.0.111`).
      - Menurunkan TTL saat migrasi tidak berguna, karena resolver baru tahu TTL baru setelah cache lamanya habis.

      Kamu tidak bisa mem-flush cache resolver publik atau ISP. Mengedit `/etc/hosts` hanya menipu mesinmu sendiri.
      Server lama yang dibiarkan hidup adalah jaring pengaman untuk resolver yang tidak patuh TTL.
    tests:
      pass:
        - |
          turunkan ttl ke 300 dua hari sebelum
          tunggu 24 jam agar ttl lama 86400 habis
          ubah record a ke ip baru dan naikkan serial
          dig @ns1.example.com app.example.com
          dig @1.1.1.1 app.example.com
          server lama tetap hidup sampai ttl habis
          naikkan ttl lagi
        - |
          set ttl 60 sehari sebelumnya, tunggu 86400 detik
          ganti record A
          dig @8.8.8.8 app.example.com
      fail:
        - |
          ubah record a ke ip baru
          turunkan ttl ke 60
          flush cache semua resolver isp
        - |
          ubah record a
          tambahkan ke /etc/hosts semua laptop
          dig app.example.com
        - |
          turunkan ttl ke 300
          ubah record
    source: https://www.rfc-editor.org/rfc/rfc1034

  - id: c02
    tier: C
    type: scenario
    objective: client
    prompt: |
      Di cluster Kubernetes (`ndots:5`, search `ns.svc.cluster.local svc.cluster.local cluster.local`), service Laravel
      memanggil `api.payment-provider.com` ribuan kali per menit. Latency tinggi dan CoreDNS sibuk. Tulis diagnosis
      dan perbaikannya (satu poin per baris).
    checklist:
      - id: inspect
        label: Melihat resolv.conf pod / dnsConfig
        required: true
        patterns: ['resolv\.conf', 'dnsconfig']
      - id: capture
        label: Menangkap query DNS (tcpdump port 53 / log CoreDNS)
        patterns: ['tcpdump', 'port 53', 'log.*coredns', 'coredns.*log']
      - id: explain
        label: Menjelaskan ndots:5 → nama dicoba dengan setiap search domain dulu (NXDOMAIN berulang)
        required: true
        patterns: ['ndots', 'search domain']
      - id: fqdn
        label: Memakai FQDN dengan titik di akhir
        patterns: ['api\.payment-provider\.com\.(\s|$|/|:)', 'titik di akhir', 'trailing dot', 'fqdn']
      - id: dnsconfig
        label: Menurunkan ndots (dnsConfig options ndots 1–2)
        required: true
        patterns: ['ndots[ \t:"]*[12]\b', 'dnsconfig']
      - id: cache
        label: Cache lokal / NodeLocal DNSCache / reuse koneksi HTTP
        patterns: ['nodelocal', 'cache', 'keep-?alive', 'pool']
      - id: no-hardcode
        label: Tidak meng-hardcode IP provider di /etc/hosts
        forbid: true
        patterns: ['/etc/hosts', 'hostaliases', 'hardcode ip']
    pass_score: 0.5
    solution: |
      ```text
      kubectl exec deploy/app -- cat /etc/resolv.conf     → search 3 domain + options ndots:5
      tcpdump -nn port 53 (di node) / log CoreDNS        → per panggilan: api.payment-provider.com.ns.svc.cluster.local,
                                                            .svc.cluster.local, .cluster.local (NXDOMAIN) lalu baru yang asli,
                                                            dikali A + AAAA
      Penyebab: nama dengan < 5 titik dicoba dengan setiap search domain lebih dulu
      Perbaikan:
        - pakai FQDN: https://api.payment-provider.com./ (titik di akhir → nama absolut)
        - spec.dnsConfig.options: [{ name: ndots, value: "2" }] untuk pod yang banyak memanggil nama eksternal
        - NodeLocal DNSCache; reuse koneksi HTTP (keep-alive) agar lookup jarang
      ```
      Lab mereproduksi pola ini dengan search 2 domain + `ndots:5`. Satu `getent hosts example.com` menghasilkan
      `example.com.dojo.test.`, `example.com.svc.cluster.local.`, lalu baru `example.com.`. Dengan `ndots:1` → hanya
      `example.com.`.

      Meng-hardcode IP provider pembayaran di `/etc/hosts`/`hostAliases` memutus failover provider dan sertifikat
      mereka saat IP berubah.
    tests:
      pass:
        - |
          kubectl exec app -- cat /etc/resolv.conf
          tcpdump -nn port 53
          ndots:5 membuat nama dicoba dengan setiap search domain dulu
          pakai fqdn api.payment-provider.com. dengan titik di akhir
          dnsConfig options ndots 2
          aktifkan nodelocal dns cache
        - |
          cek resolv.conf pod
          search domain dan ndots menyebabkan query nxdomain berulang
          set dnsConfig ndots: 1
      fail:
        - |
          cek resolv.conf
          ndots tinggi
          tambahkan ip provider ke /etc/hosts
          set ndots 1
        - |
          scale coredns jadi 10 replica
          restart pod
        - |
          tcpdump port 53
          ganti provider
    source: https://kubernetes.io/docs/concepts/services-networking/dns-pod-service/

  - id: c03
    tier: C
    type: scenario
    objective: status
    prompt: |
      Pengguna melapor "`shop.dojo.test` tidak bisa dibuka". Tulis langkah diagnosis DNS berurutan (satu perintah/poin
      per baris) untuk membedakan: nama tidak ada, record tipe tertentu tidak ada, resolver gagal, dan jawaban basi.
    checklist:
      - id: resolver
        label: Tanya resolver yang dipakai pengguna & baca status
        required: true
        patterns: ['dig\s+(@\S+\s+)?shop\.dojo\.test', 'status']
      - id: auth
        label: Tanya authoritative langsung (dig @ns ... cek aa / TTL)
        required: true
        patterns: ['dig\s+@ns\S*', 'authoritative', '\baa\b']
      - id: map
        label: Memetakan NXDOMAIN / NODATA / SERVFAIL / timeout
        required: true
        patterns: ['(?=.*nxdomain)(?=.*servfail)']
      - id: types
        label: Memeriksa tipe A dan AAAA (NODATA)
        patterns: ['aaaa', 'nodata']
      - id: trace
        label: Menelusuri delegasi (+trace / NS)
        patterns: ['\+trace', 'dig\s+.*\bns\b']
      - id: ttl
        label: Mengecek TTL/cache (jawaban basi, negative cache)
        patterns: ['ttl', 'cache']
      - id: no-hosts
        label: Tidak menjadikan /etc/hosts solusi
        forbid: true
        patterns: ['/etc/hosts']
    pass_score: 0.5
    solution: |
      ```bash
      dig shop.dojo.test                       # lewat resolver pengguna: status? ANSWER? TTL?
      dig AAAA shop.dojo.test                  # NOERROR + 0 jawaban = NODATA (hanya tipe ini yang tidak ada)
      dig @ns1.dojo.test shop.dojo.test        # authoritative: flag aa, TTL penuh, isi sebenarnya
      dig +trace shop.dojo.test                # delegasi NS benar di setiap level?
      ```
      Petakan hasilnya:
      - **NXDOMAIN** di authoritative → nama memang tidak ada (typo/record belum dibuat). NXDOMAIN hanya di
        resolver → negative cache, tunggu TTL SOA minimum (lab: 20 s).
      - **NOERROR + 0 jawaban** → NODATA (lab: `AAAA api.dojo.test`).
      - **SERVFAIL** → resolver gagal: lame delegation (lab: `REFUSED` di auth → `SERVFAIL`), DNSSEC rusak, atau
        semua NS mati.
      - **Timeout** → authoritative/resolver tidak menjawab (lab: auth mati → tidak ada balasan 90 s).
      - **Jawaban berbeda** antara resolver dan authoritative → cache basi (lihat sisa TTL) atau split-horizon.

      `/etc/hosts` hanya menyembunyikan masalah di satu mesin.
    tests:
      pass:
        - |
          dig shop.dojo.test lihat status
          dig AAAA shop.dojo.test untuk cek nodata
          dig @ns1.dojo.test shop.dojo.test cek flag aa dan ttl
          dig +trace shop.dojo.test
          nxdomain = nama tidak ada, servfail = resolver gagal, beda jawaban = cache ttl
        - |
          dig @10.62.0.54 shop.dojo.test
          dig @ns1.dojo.test shop.dojo.test
          bedakan nxdomain dan servfail
      fail:
        - |
          tambahkan shop.dojo.test ke /etc/hosts
          dig shop.dojo.test
          dig @ns1.dojo.test shop.dojo.test
          nxdomain vs servfail
        - |
          ping shop.dojo.test
          restart browser
        - |
          dig shop.dojo.test
          nxdomain
    source: https://www.rfc-editor.org/rfc/rfc2308

---

# DNS Mendalam

Modul Linux 08 mengajarkan *cara memakai* `dig`. Modul ini membahas *apa yang sebenarnya terjadi* di balik setiap
jawaban: siapa yang menjawab, kenapa jawabannya bisa basi, dan apa arti setiap status. Semua dibuktikan di ekosistem
DNS kecil yang dibangun di container.

```mermaid
flowchart LR
    C["klien<br/>10.62.0.10<br/>resolv.conf"] -->|rekursif, rd| R["resolver Unbound<br/>10.62.0.54<br/>cache"]
    R -->|iteratif| A["authoritative BIND<br/>10.62.0.53<br/>zone dojo.test (aa)"]
    R -->|iteratif| I["internet:<br/>root → TLD → NS domain"]
```

> [!NOTE]
> Lab: BIND sebagai authoritative `dojo.test` (TTL 30, negative TTL 20), Unbound sebagai resolver rekursif
> (stub-zone ke BIND, internet lewat root hints), klien `dig` + tcpdump. `.test` adalah TLD khusus (RFC 6761), jadi
> aman dipakai untuk lab.

## 1. Resolusi: authoritative vs resolver

| | Authoritative | Resolver rekursif |
|---|---|---|
| Tugas | Pemilik jawaban untuk zone-nya | Mencari jawaban untuk klien, lalu menyimpannya di cache |
| Flag (lab) | `qr aa rd` | `qr rd ra` |
| TTL | Selalu penuh (30) | Sisa cache (29 → 22) |
| Contoh | BIND, NSD, PowerDNS, Route 53, Cloudflare | Unbound, systemd-resolved, 1.1.1.1, 8.8.8.8, CoreDNS |

`dig +trace example.com` (lab, internet):

```text
.            NS  j.root-servers.net.        ← dari resolver (daftar root)
com.         NS  i.gtld-servers.net.        ← dari b.root-servers.net
example.com. NS  hera.ns.cloudflare.com.    ← dari k.gtld-servers.net
example.com. A   172.66.147.243             ← dari elliott.ns.cloudflare.com
```

## 2. Jenis record

| Tipe | Lab (`dojo.test`) | Catatan |
|---|---|---|
| A / AAAA | `10.62.0.100` | IPv4 / IPv6 |
| CNAME | `www → dojo.test.` | Tidak boleh berdampingan dengan record lain, dan tidak di apex |
| MX | `10 mail.dojo.test.` | Target berupa nama, bukan IP |
| TXT | `"v=spf1 mx -all"` | SPF, DKIM, DMARC, verifikasi |
| NS / SOA | `ns1.dojo.test.` / `1 3600 600 86400 20` | Delegasi / metadata zone |
| SRV | `10 60 5060 sip.dojo.test.` | prioritas bobot port target |
| CAA | `0 issue "letsencrypt.org"` | CA yang boleh menerbitkan sertifikat |
| PTR | `101 → api.dojo.test.` | Reverse (`dig -x`) |
| Wildcard | `*.apps → 10.62.0.199` | Cocok untuk `x.apps` **dan** `foo.bar.apps` |

## 3. TTL, cache, dan "propagasi"

```text
resolver: api.dojo.test. 29 IN A 10.62.0.101
(7 detik)  api.dojo.test. 22 IN A 10.62.0.101
auth diubah → 10.62.0.111
auth: 10.62.0.111   resolver: 21 10.62.0.101      ← basi sampai TTL habis
unbound-control flush api.dojo.test → 10.62.0.111
```

> [!WARNING]
> "Propagasi DNS" sebenarnya adalah cache yang kedaluwarsa. Turunkan TTL **sebelum** perubahan (minimal satu TTL
> lama sebelumnya), karena menurunkan TTL saat perubahan tidak mempercepat apa pun.

### Negative caching

```text
dig new.dojo.test           → NXDOMAIN (SOA minimum 20 → di-cache 20 s)
record new dibuat di auth   → auth: 10.62.0.120
resolver                    → masih NXDOMAIN (sisa 18 s)
setelah 21 s                → 10.62.0.120
```

## 4. Status jawaban

| Hasil (lab) | Arti | Langkah berikutnya |
|---|---|---|
| `NXDOMAIN` + SOA | Nama tidak ada (pasti) | Typo? record belum dibuat? negative cache? |
| `NOERROR`, `ANSWER: 0` | **NODATA**: nama ada, tipe tidak ada | Mis. tidak ada AAAA |
| `SERVFAIL` | Resolver gagal mendapat jawaban | Lame delegation (lab: REFUSED → SERVFAIL), DNSSEC, NS mati |
| Timeout | Tidak ada balasan | Lab: auth mati → resolver diam 90 s |
| `REFUSED` | Server menolak (bukan authoritative / tidak melayani rekursi) | Salah server |

## 5. Resolver klien: search dan ndots

```text
search dojo.test svc.cluster.local
options ndots:5
getent hosts example.com  →  A? example.com.dojo.test.
                             A? example.com.svc.cluster.local.
                             A? example.com.                    (3 query)
options ndots:1           →  A? example.com.                    (1 query)
getent hosts api          →  api.dojo.test                      (search tetap dipakai)
```

Nama dengan titik di akhir (`example.com.`) adalah nama absolut dan tidak pernah memakai search.

## 6. UDP, TCP, dan truncation

```text
dig TXT big.dojo.test +notcp +ignore  → flags: qr aa tc rd, MSG SIZE 70
dig TXT big.dojo.test                 → ;; Truncated, retrying in TCP mode.  MSG SIZE 1493
EDNS: udp: 1232
```

Firewall **wajib** membuka TCP 53 juga. Tanpa itu, jawaban besar gagal sementara jawaban kecil lancar.

## 7. Aturan zone yang sering dilanggar

- `CNAME and other data`: CNAME di apex, atau CNAME dan A di nama yang sama (lab: `named-checkzone` menolak).
- Naikkan **serial** SOA di setiap perubahan, karena secondary memakai serial untuk tahu ada versi baru.
- Minimal 2 NS di jaringan berbeda. Dengan satu NS mati, resolver diam saja (lab).
- Pakai TLD khusus untuk internal: `.test`, `.example`, `.invalid`, `home.arpa`. Unbound bahkan memblokir `.test`
  secara default (`test. static`).

## 8. Split-horizon

```text
authoritative:      app.dojo.test → NXDOMAIN
resolver internal:  app.dojo.test → 10.62.0.250   (local-data)
```

Selalu sebutkan **resolver mana** yang ditanya saat debugging (`dig @...`), karena jawaban bisa berbeda sesuai
penanyanya.

## 9. Ringkasan diagnosis

```bash
dig name                    # lewat resolver: status, ANSWER, sisa TTL
dig AAAA name               # NODATA?
dig @ns-authoritative name  # aa, TTL penuh = kebenaran
dig +trace name             # delegasi di setiap level
```
