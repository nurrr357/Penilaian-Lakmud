# Plan: Seed Data LAKMUD dari Excel (Materi + Peserta + 6 Aspek)

Status: **REVISI-1** — sudah memuat jawaban brief. Belum ada kode yang ditulis.
Tanggal: 21 Oktober 2026
Proyek: `D:\huda\project\laravel\skripsi\lakmud-penilaian`

## 0. Perubahan pada revisi ini

| Hal | Sebelum | Sesudah (brief) |
|---|---|---|
| Tempat/venue | asumsi sama dengan organisasi | **ikuti kolom sistem sekarang** (organisasi dan tempat diisi sama); nama tempat sebenarnya dikirim user menyusul, tinggal diubah lewat menu Kegiatan |
| Tanggal | mulai = selesai = hari ini | **mulai hari ini, selesai 4 hari ke depan** |
| Biodata | menunggu brief | **ikuti sistem sekarang, ambil yang perlu saja** → hanya `full_name`, tanpa migrasi kolom baru |
| Data demo lama | dibiarkan | **dihapus** |
| Pembagian kegiatan | 1 kegiatan | **1 kegiatan** (dikonfirmasi) |

## 1. Tujuan

Memuat data dari berkas Excel sumber ke satu kegiatan di aplikasi, sehingga:

- 15 **materi** tersedia di menu Materi;
- 702 **peserta** terdaftar di menu Peserta;
- nilai **6 aspek MOORA** setiap peserta × setiap materi tersedia di menu Absensi, Pretest, Posttest, Keaktifan, Etika, dan Pemahaman;
- tombol **Hitung MOORA** bisa dijalankan user sendiri untuk menguji perhitungan.

**Hasil perhitungan (`moora_runs`, `moora_results`) TIDAK diisi** — user ingin menguji sendiri perhitungan MOORA.

## 2. Sumber data

Berkas: `Database IPNU IPPNU PAC Kabupaten Malang - MOORA Kehadiran COST Final - MAKESTA ke LAKMUD.xlsx`

| Sheet | Dipakai untuk | Baris |
|---|---|---|
| DATABASE PESERTA | daftar peserta | 702 |
| PENILAIAN 15 MATERI | materi + 6 aspek per peserta per materi | 10.530 |

Sheet `KETENTUAN PENILAIAN`, `REKAP TOTAL ASPEK`, `CONTOH MOORA 5 DATA` **tidak di-seed**; REKAP hanya jadi pembanding saat verifikasi.

Kunci penghubung: kolom **`No`** (1–702). Nama **tidak** dipakai sebagai kunci karena ada 3 peserta berbeda bernama "Nurul Hidayah".

## 3. Kegiatan (event) yang dibuat

| Field | Nilai |
|---|---|
| `name` | `Lakmud` |
| `organization` | `IpnuIppnu PC Kabupaten Malang` |
| `venue` | `IpnuIppnu PC Kabupaten Malang` — mengikuti kolom sistem saat ini; **nama tempat sebenarnya menyusul dari user**, diubah lewat menu Kegiatan |
| `start_date` | `2026-10-21` (hari ini) |
| `end_date` | `2026-10-25` (hari ini + 4 hari) |
| `finalized` | `false` (terbuka) |

## 4. Pemetaan data ke tabel

| Sumber Excel | Tabel | Isi | Baris |
|---|---|---|---|
| `No. Materi` + `Materi` | `event_materials` | `sequence` 1–15, `name`, `is_active` = 1 | 15 |
| `Nama` | `participants` | `full_name` saja (kolom lain belum ada di skema) | 702 |
| `No` | `event_participants` | `sequence_number` = `No`, `status` = `registered`, `registered_at` = waktu seed | 702 |
| `Aspek 1` (0/1) | `attendance_records` | `0` → `present`, `1` → `absent` | 10.530 |
| `Aspek 2` | `assessment_scores` | `type` = `pretest` | 10.530 |
| `Aspek 6` | `assessment_scores` | `type` = `posttest` | 10.530 |
| `Aspek 3` | `moora_aspect_scores` | `activity` | 10.530 |
| `Aspek 4` | `moora_aspect_scores` | `ethics` | (baris yang sama) |
| `Aspek 5` | `moora_aspect_scores` | `understanding` | (baris yang sama) |

Total ± **43.539 baris**. `marked_by`/`scored_by` dikosongkan (nullable).

## 5. Hapus data demo lama (brief: dihapus)

Dijalankan **sebelum** seed, setelah backup, dengan mencetak daftar target lebih dulu:

1. Kegiatan demo `Demo verifikasi MOORA` (id 6) beserta turunannya (materi, pendaftaran peserta, input) — terhapus lewat `cascadeOnDelete` pada foreign key.
2. Peserta yang tidak terdaftar di kegiatan mana pun setelah langkah 1 (saat ini ada 9 baris `participants`, hanya 3 yang terdaftar).

Langkah wajib sebelum menghapus:

- `mysqldump` seluruh `db_lakmud_penilaian` ke `storage/app/backup/`.
- Cetak daftar kegiatan dan peserta yatim yang akan dihapus, lalu jumlah baris sebelum/sesudah.
- Hapus peserta yatim hanya yang **tidak** punya baris `event_participants`; peserta yang masih terdaftar di kegiatan lain tidak disentuh.

## 6. Yang TIDAK dikerjakan

- Tidak mengisi `moora_runs` dan `moora_results`.
- Tidak mengisi `TOTAL NILAI`/`PREDIKAT`/`KET.`/`KELULUSAN` dari Excel.
- Tidak menambah kolom biodata baru dan tidak ada migrasi skema.
- Tidak menambah route, controller, view, permission, atau dependency baru.
- Tidak mengisi `activity_log` (seed langsung ke database).

## 7. Cara pengerjaan

### 7.1 Konverter Excel → CSV (sekali, di lokal)

Berkas baru `python_instruct/excel_to_seed_csv.py` (Python + openpyxl, sudah tersedia) menghasilkan:

- `storage/app/seed/peserta.csv` → `no,nama`
- `storage/app/seed/penilaian.csv` → `no,materi_no,materi_nama,aspek1,aspek2,aspek3,aspek4,aspek5,aspek6` (kolom `materi_nama` ditambahkan saat eksekusi supaya nama 15 materi ikut terseed tanpa berkas ketiga)

`storage/app/*` sudah tercakup `.gitignore` (terverifikasi `git check-ignore`), jadi data pribadi 702 orang tidak masuk git. Saat deploy, CSV diunggah manual. Seeder membaca CSV dengan `fgetcsv` — **tanpa dependency baru**.

### 7.2 Seeder

Berkas baru `database/seeders/LakmudDataSeeder.php`, satu DB transaction, semua penulisan memakai `upsert` batch pada kunci unik komposit (idempoten). Penyimpangan dari draf awal (`updateOrCreate` per baris) dicatat di sini: 31.590 baris nilai membuat `updateOrCreate` menjadi ±63.000 query, sedangkan `upsert` (`INSERT ... ON DUPLICATE KEY UPDATE`) cukup 11 query per tabel — terukur 3,8–8,5 detik dengan kunci unik dan semantik idempotensi yang sama.

| Tabel | Kunci |
|---|---|
| `lakmud_events` | `name` + `organization` |
| `event_materials` | `lakmud_event_id` + `sequence` |
| `event_participants` | `lakmud_event_id` + `sequence_number` |
| `participants` | diambil dari `event_participant.participant_id`, hanya `full_name` yang di-update |
| `attendance_records` | `event_participant_id` + `event_material_id` |
| `assessment_scores` | `+ assessment_type` |
| `moora_aspect_scores` | `event_participant_id` + `event_material_id` |

Peserta di-resolve lewat `sequence_number` (bukan nama) sehingga 3 peserta "Nurul Hidayah" tetap terpisah. Bila CSV tidak ada, seeder berhenti dengan pesan jelas. Seeder memverifikasi ulang setiap nilai (aspek 2–6 ∈ [10,20] dan maks 2 desimal; aspek 1 ∈ {0,1}) dan berhenti bila ada yang gagal.

```bash
php artisan db:seed --class=LakmudDataSeeder
```

Tidak ada perubahan pada `app/`, `routes/`, `resources/`, `config/`, `composer.json`.

## 8. Verifikasi

1. Backup `mysqldump` sebelum semua langkah.
2. Data demo lama: cetak sebelum/sesudah (0 kegiatan demo, peserta yatim 0).
3. Hitung baris setelah seed: materi 15; `event_participants` 702 dengan `sequence_number` 1–702 tanpa duplikat/bolong; `attendance_records` 10.530; `assessment_scores` 21.060; `moora_aspect_scores` 10.530; **`moora_runs` 0 dan `moora_results` 0**.
4. Kelengkapan: setiap peserta 90/90 — dashboard menampilkan `90 / 90` tanpa "Yang perlu diisi".
5. Cross-check agregat C1–C6 aplikasi vs kolom REKAP untuk **seluruh 702 peserta** (harus identik).
6. Idempotensi: jalankan seeder dua kali, hitungan tidak berubah.
7. `php artisan test` → 41 test tetap lulus.
8. Uji manual user: Hitung MOORA lalu bandingkan dengan sheet REKAP. Perhatikan: kolom `Total Nilai Aspek 2–6` REKAP adalah **jumlah** 15 materi, sedangkan `certificate_score` aplikasi adalah **rata-rata** per materi (1165 ÷ 15 = 77,67 → C).

## 9. Risiko

| Risiko | Penanganan |
|---|---|
| Nama peserta tidak unik (3× "Nurul Hidayah") | key lewat `sequence_number` dari kolom `No` |
| PII 702 orang masuk git | CSV di `storage/app/seed/` yang gitignored, unggah manual ke server |
| Penghapusan demo mengenai data lain | backup + cetak daftar target + hapus peserta hanya yang tanpa pendaftaran |
| Nilai di luar aturan lolos | seeder memvalidasi ulang setiap nilai dan berhenti bila gagal |
| Seed tidak lewat FormRequest, tidak menulis activity_log | diterima untuk pemuatan data sekali jalan |

## 10. Urutan eksekusi

1. Tulis konverter, jalankan, hasilkan 2 CSV di `storage/app/seed/`.
2. Verifikasi CSV: 702 dan 10.530 baris + rentang nilai.
3. Tulis `LakmudDataSeeder` + prosedur hapus data demo.
4. Backup database.
5. Hapus data demo lama (dengan cetak daftar target).
6. Jalankan seeder.
7. Jalankan verifikasi §8.3–§8.7, laporkan angka nyatanya.
8. Serahkan ke user untuk uji Hitung MOORA (§8.8).
9. Commit setelah user selesai menguji. CSV tidak ikut commit.

## 11. Sisa pertanyaan

1. **Nama tempat (venue)** sebenarnya — dibutuhkan untuk mengganti nilai sementara; mohon dikirim saat tersedia.
2. **Organisasi** — ditulis `IpnuIppnu PC Kabupaten Malang` (persis seperti brief). Konfirmasi bila sebaiknya `IPNU IPPNU PC Kabupaten Malang`.

## 12. Status eksekusi (2026-10-21 13:0x SEAST)

Semua langkah §10 selesai, kecuali commit (menunggu user menguji) dan butir §8.8.

| Butir verifikasi §8 | Hasil terukur |
|---|---|
| 1 Backup | `storage/app/backup/db_lakmud_penilaian_20261021_130257.sql`, 640 KB, gitignored |
| 2 Data demo | kegiatan #6 "Demo verifikasi MOORA" (15 materi, 3 pendaftaran, 15 absensi, 31 nilai, 15 aspek, 2 snapshot) dihapus; lalu 9 peserta yatim (sisa uji browser: "Peserta demonstrasi" ×3, "Edited peserta" ×4, "huda", "fiqri") dihapus → `participants` 9 → 3 → **702** (hanya peserta Lakmud) |
| 3 Baris | kegiatan 1; materi 15 (aktif 15); `event_participants` 702 dengan `sequence_number` 1–702 unik tanpa bolong; absensi 10.530; nilai 21.060; aspek 10.530; **`moora_runs` 0, `moora_results` 0** |
| 4 Kelengkapan | dashboard "15 / 15 materi aktif · 702 peserta · Status hasil: belum dihitung"; setiap baris "90 / 90 Lengkap"; tombol Hitung MOORA ada dan **aktif** (`disabled=false`) |
| 5 Cross-check Excel | 702 peserta: **C1–C6 = 0 sel berbeda**, normalisasi = 0 sel, Yi = 0 baris (selisih maks 6,9e-18), peringkat = 0 baris |
| 6 Idempotensi | seeder dijalankan dua kali; hitungan identik, tanpa baris kembar |
| 7 Test | `artisan test --compact` → 41 test / 743 assertion lulus |
| 8 Uji Hitung MOORA | **menunggu user** — snapshot hasil sengaja tidak diisi |

Angka pembanding dari database (moora-v1, identik dengan Excel):

- Pembagi `D` = 43,046486500062 | 5571,595103738247 | 5976,068691037613 | 5970,857894808752 | 5989,572104917012 | 6338,641889237789
- Peringkat 1–5 = peserta No 47, 614, 280, 60, 620
- Contoh: No 1 → Yi 0,032782704385449, peringkat 190

Berkas hasil eksekusi:

| Berkas | Isi |
|---|---|
| `python_instruct/excel_to_seed_csv.py` | konverter + validasi 702/15/10.530 sebelum menulis CSV |
| `database/seeders/LakmudDataSeeder.php` | pengisi satu kegiatan dari CSV (transaksi tunggal, upsert batch) |
| `database/seeders/PurgeDemoDataSeeder.php` | penghapus kegiatan demo + peserta yatim, mencetak daftar target lebih dulu |
| `storage/app/seed/{peserta,penilaian}.csv` | 17 KB + 632 KB, **gitignored**, unggah manual saat deploy |
| `storage/app/backup/db_lakmud_penilaian_20261021_130257.sql` | cadangan sebelum penghapusan |

Catatan kualitas data: 702 peserta dengan **696 nama unik** (6 baris bernama sama persis). Kunci join tetap kolom `No`, bukan nama.
