---
format: 1
id: mini-module
course:
  slug: test-course
  title: Test Course
title: Mini Module
summary: Fixture module for tests.
order: 1
version: 1
status: verified
updated: '2026-11-19'
quiz:
  A: 2
  B: 1
  C: 1
objectives:
  - id: chmod
    text: Memakai `chmod`
sources:
  - title: chmod(1)
    url: https://man7.org/linux/man-pages/man1/chmod.1.html
questions:
  - id: a01
    tier: A
    type: command
    objective: chmod
    prompt: Tambahkan x untuk owner pada `deploy.sh`.
    answers: ['chmod u+x deploy.sh']
    tests:
      fail: ['chmod +x deploy.sh']
    explanation: '`u` = owner.'
    source: https://man7.org/linux/man-pages/man1/chmod.1.html
  - id: a02
    tier: A
    type: text
    objective: chmod
    prompt: Oktal dari `rwxr-x---`?
    answers: ['750']
    source: https://man7.org/linux/man-pages/man1/chmod.1.html
  - id: b01
    tier: B
    type: command
    objective: chmod
    prompt: Tampilkan permission direktori `/srv` itu sendiri.
    answers: ['ls -ld /srv']
    flags_any_order: true
    tests:
      pass: ['ls -dl /srv']
      fail: ['ls -l /srv']
    source: https://man7.org/linux/man-pages/man1/ls.1.html
  - id: c01
    tier: C
    type: scenario
    objective: chmod
    prompt: Perbaiki permission storage tanpa 777.
    context: |
      Permission denied: /srv/app/storage
    checklist:
      - id: diagnose
        label: Cek path dengan namei
        required: true
        patterns: ['namei\s+-l']
      - id: fix
        label: Beri akses lewat group
        patterns: ['chgrp\s+(-R\s+)?www-data']
      - id: no-777
        label: Tidak memakai 777
        forbid: true
        patterns: ['chmod\s+(-R\s+)?777']
    solution: |
      ```bash
      namei -l /srv/app/storage
      chgrp -R www-data /srv/app/storage
      ```
    tests:
      pass:
        - |
          namei -l /srv/app/storage
          chgrp -R www-data /srv/app/storage
      fail:
        - chmod -R 777 /srv/app/storage
    source: https://man7.org/linux/man-pages/man1/namei.1.html
---

# Mini Module

## Bagian satu

Teks materi.

> [!INSIGHT]
> Owner dicek lebih dulu.
> Sumber: [path_resolution(7)](https://man7.org/linux/man-pages/man7/path_resolution.7.html)

```mermaid
flowchart LR
  A --> B
```
