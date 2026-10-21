# Profil Data Excel Peserta & Penilaian (sumber seed)

Tanggal analisis: 20 Oktober 2026
Berkas: `Database IPNU IPPNU PAC Kabupaten Malang - MOORA Kehadiran COST Final - MAKESTA ke LAKMUD.xlsx` (926 KB)
Letak saat analisis: `C:\Users\muham\Downloads\`
Status: dikonfirmasi user sebagai berkas sumber yang dimaksud. Cakupan seed, kolom biodata, dan detail kegiatan masih menunggu brief user.

## 1. Struktur workbook (5 sheet)

| Sheet | Baris data | Isi |
|---|---|---|
| DATABASE PESERTA | 702 | Biodata peserta, 13 kolom |
| PENILAIAN 15 MATERI | 10.530 | 6 aspek × 15 materi per peserta (= 702 × 15) |
| KETENTUAN PENILAIAN | 27 | Rubrik aspek + ambang predikat |
| REKAP TOTAL ASPEK | 702 | Total 6 aspek, normalisasi, Skor MOORA Yi, Rank MOORA |
| CONTOH MOORA 5 DATA | 50 | Contoh perhitungan |

Posisi header: DATABASE PESERTA di baris 3 (data mulai baris 4); PENILAIAN 15 MATERI di baris 4 (data mulai baris 5); REKAP TOTAL ASPEK di baris 4 (data mulai baris 5).

## 2. Kolom

**DATABASE PESERTA:** `No`, `Program`, `Sumber File`, `No. Peserta Sumber`, `No. Sertifikat`, `Nama`, `TTL`, `Alamat`, `Pendidikan`, `HP/WA`, `Email`, `Media Sosial`, `Organisasi`

**PENILAIAN 15 MATERI:** `No`, `Nama`, `Program`, `No. Sertifikat`, `Sumber File`, `No. Materi`, `Materi`, `Aspek 1 - Kehadiran (0=Hadir, 1=Tidak Hadir)`, `Aspek 2 - Wawasan sebelum mengikuti materi`, `Aspek 3 - Keaktifan dan antusias di ruangan`, `Aspek 4 - Etika saat mengikuti materi`, `Aspek 5 - Pemahaman dan kedalaman materi`, `Aspek 6 - Pemahaman selesai menerima materi`, `TOTAL NILAI`, `PREDIKAT`, `KET.`, `KELULUSAN`

**REKAP TOTAL ASPEK:** kolom kunci untuk verifikasi — `Total Aspek 1 - Tidak Hadir`, `Total Hadir`, `Total Aspek 2 - Pretest`, `Total Aspek 3 - Keaktifan`, `Total Aspek 4 - Etika`, `Total Aspek 5 - Pemahaman`, `Total Aspek 6 - Post test`, `Total Nilai Aspek 2–6`, `Skor MOORA Yi`, `Rank MOORA`.

## 3. Hasil pemeriksaan kualitas

| Pemeriksaan | Hasil |
|---|---|
| Jumlah peserta | 702 (nomor 1–702, tidak ada nomor duplikat, tidak ada nomor bolong) |
| Keselarasan antar-sheet | 0 nomor yang hanya ada di satu sheet; 0 pasangan nomor dengan nama berbeda |
| Baris per peserta di sheet penilaian | semua **tepat 15 baris** dan mencakup **15 materi**; 0 peserta tidak lengkap |
| Baris per materi | setiap `No. Materi` 1–15 punya **tepat 702 baris** |
| Aspek 1 (kehadiran) | hanya `0` (hadir) atau `1` (tidak hadir) |
| Aspek 2 | rentang 10–18 |
| Aspek 3, 4, 5, 6 | rentang 10–20 |
| Nilai lebih dari 2 desimal | **0** pada seluruh 10.530 × 6 sel |
| Nilai di luar 10–20 pada aspek 2–6 | **0** |

Kesimpulan: seluruh nilai **sudah sesuai aturan validasi aplikasi** (10–20, maksimal dua desimal), dan kelengkapan 15 materi untuk semua peserta sudah terpenuhi — artinya setelah di-seed, syarat tombol Hitung MOORA langsung terpenuhi.

## 4. Kunci penghubung (join key)

Kolom **`No`** adalah kunci yang aman:

- Berurutan 1–702.
- Tiap nomor tepat 15 baris, mencakup 15 materi.
- Tiap nomor hanya punya satu nama dan satu nomor sertifikat (0 ambiguitas).
- Nama pada `No` yang sama **identik** antara sheet DATABASE PESERTA dan PENILAIAN 15 MATERI.

**Jangan memakai nama sebagai kunci:** 702 peserta hanya menghasilkan **694 nama unik**. Terdapat 3 orang berbeda bernama sama "Nurul Hidayah" dengan `No. Sertifikat` `-` (45 baris = 3 × 15). Jika di-key dengan nama saja, 3 orang itu tergabung menjadi satu peserta.

`No. Sertifikat` juga tidak unik: 389 baris bernilai `-`/kosong, 313 terisi.

## 5. 15 materi (No → nama)

| No | Materi | No | Materi |
|---|---|---|---|
| 1 | Ahlussunnah Wal Jama'ah II | 9 | Scientific Problem Solving (SPS) |
| 2 | Ke-NU-an II | 10 | Networking dan Lobbying |
| 3 | Ke-IPNU-an II | 11 | Teknik Diskusi dan Persidangan |
| 4 | Keindonesiaan II | 12 | Study Gender II |
| 5 | Kepemimpinan II | 13 | Studi Problematika Pendidikan |
| 6 | Manajemen Organisasi | 14 | Analisis Media |
| 7 | Komunikasi dan Kerjasama | 15 | Teknik Proposal dan Fundraising |
| 8 | Manajemen Konflik | | |

## 6. Pemetaan ke tabel aplikasi

| Sumber Excel | Tabel aplikasi | Catatan |
|---|---|---|
| `Nama` (+ biodata bila disetujui) | `participants` | saat ini hanya punya `full_name` |
| `No` → urutan | `event_participants.sequence_number` | unik per kegiatan |
| `Materi` + `No. Materi` | `event_materials.name` / `.sequence`, `is_active = 1` | harus tepat 15 |
| `Aspek 1` (0/1) | `attendance_records.status` | 0 → `present`, 1 → `absent` |
| `Aspek 2` / `Aspek 6` | `assessment_scores` `assessment_type` `pretest` / `posttest` | satu baris per kombinasi |
| `Aspek 3` / `Aspek 4` / `Aspek 5` | `moora_aspect_scores.activity` / `.ethics` / `.understanding` | satu baris per kombinasi |
| `Total Aspek 1 - Tidak Hadir` … `Rank MOORA` | — | **tidak di-seed**; dipakai memverifikasi hasil Hitung MOORA di aplikasi |

Perkiraan volume baris yang ditulis bila cakupan penuh dipilih: 702 `participants` + 702 `event_participants` + 15 `event_materials` + 10.530 `attendance_records` + 21.060 `assessment_scores` + 10.530 `moora_aspect_scores` ≈ **43.539 baris**. Tabel `moora_runs`/`moora_results` **tidak diisi** — aplikasi menghitungnya sendiri lewat tombol Hitung MOORA (aturan: perhitungan hanya di server).

## 7. Constraint yang wajib dipatuhi seeder

Semua unique komposit berikut harus jadi basis `updateOrCreate` agar seeder idempoten:

- `event_participants` — UNIQUE `(lakmud_event_id, participant_id)` dan UNIQUE `(lakmud_event_id, sequence_number)`
- `event_materials` — UNIQUE `(lakmud_event_id, sequence)`
- `attendance_records` — UNIQUE `(event_participant_id, event_material_id)`
- `assessment_scores` — UNIQUE `(event_participant_id, event_material_id, assessment_type)`
- `moora_aspect_scores` — UNIQUE `(event_participant_id, event_material_id)`

Kolom `marked_by`, `scored_by` **nullable** — boleh dikosongkan atau diisi id operator. `event_participants.status` NOT NULL, `registered_at` nullable.

Satu kegiatan (`lakmud_events`) hanya boleh punya **satu** `moora_runs` yang relevan pada satu waktu; `moora_results` punya UNIQUE `(run_id, participant_id)`.

## 8. Rubrik dari sheet KETENTUAN PENILAIAN

- Aspek 1 kehadiran: 0/1, **tidak masuk** TOTAL NILAI.
- Aspek 2–6: masing-masing 10–20; TOTAL NILAI = jumlah aspek 2–6 (rentang 50–100).
- Predikat: A 91–100, B 81–90, C 71–80 (LULUS); D 61–70, E 50–60 (TIDAK LULUS).

Rubrik ini **cocok** dengan yang sudah diimplementasikan aplikasi (`CertificateScoreCalculator`: LULUS untuk A/B/C; predikat dari skor sertifikat, bukan dari ranking).

## 9. Metode yang direncanakan (tanpa dependency baru)

1. Konversi sekali di lokal: Excel → CSV/JSON memakai Python + openpyxl (sudah tersedia: openpyxl 3.1.5).
2. Seeder membaca berkas data dengan `fgetcsv` — **tanpa** menambah `maatwebsite/excel` ke server.
3. Seeder idempoten lewat `updateOrCreate`, dijalankan eksplisit: `php artisan db:seed --class=...Seeder`, bukan lewat `DatabaseSeeder` bawaan.
4. Verifikasi: setelah seed, jalankan Hitung MOORA di aplikasi lalu bandingkan `yi` dan `rank` dengan kolom `Skor MOORA Yi` dan `Rank MOORA` pada sheet REKAP TOTAL ASPEK.

Berkas data bersifat **data pribadi 702 orang** (nama, alamat, HP/WA, email). Sejak 21 Okt 2026 kedua CSV seed (`peserta.csv`, `penilaian.csv`) di-commit ke repository atas keputusan pemilik data, jadi perlakukan repository ini sebagai berisi data pribadi dan jaga tetap privat.

## 10. Keputusan yang masih menunggu brief user

1. Cakupan seed: peserta + penilaian lengkap, atau peserta saja, atau peserta + pretest/posttest saja.
2. Kolom biodata: perlu migrasi tambah kolom, atau cukup `full_name`.
3. Detail kegiatan: nama, organisasi, tempat, tanggal mulai, tanggal selesai.
