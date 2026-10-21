# Plan: Audit Sistem MOORA Aplikasi vs Excel

Status: **MENUNGGU REVIEW** — plan saja, belum ada perubahan kode.
Tanggal: 21 Oktober 2026
Proyek: `D:\huda\project\laravel\skripsi\lakmud-penilaian`
Berkas Excel: `Database IPNU IPPNU PAC Kabupaten Malang - MOORA Kehadiran COST Final - MAKESTA ke LAKMUD.xlsx`

## 1. Tujuan

Memastikan perhitungan MOORA di aplikasi web memakai rumus yang benar **dan** menghasilkan angka yang identik dengan Excel, untuk seluruh 702 peserta. Audit bersifat **mengukur, bukan mengubah**: bila ditemukan selisih, dilaporkan lebih dulu, rumus tidak dipatch tanpa persetujuan.

## 2. Rumus yang diterapkan aplikasi (hasil pembacaan kode)

| Tahap | Aturan | Lokasi kode |
|---|---|---|
| C1 (cost) | jumlah materi dengan absensi `absent` | `app/Services/PanelService.php:115` |
| C2–C6 (benefit) | jumlah 15 nilai per materi: pretest, keaktifan, etika, pemahaman, posttest | `app/Services/PanelService.php:114-118` |
| Bobot | `[0.10, 0.10, 0.20, 0.15, 0.20, 0.25]` (konstanta) | `app/Services/MooraCalculator.php:9` |
| Normalisasi | vektor: `N_ij = C_ij / D_j`, `D_j = sqrt(Σ_i C_ij²)` | `MooraCalculator.php:17-30,42` |
| Terbobot | `N_ij × W_j` | `MooraCalculator.php:43` |
| Yi | `Σ(W_j·N_ij, j=2..6) − W_1·N_i1` | `MooraCalculator.php:45-52` |
| Peringkat | Yi menurun, **tanpa pembulatan**, seri = peringkat kompetisi (lompat) | `MooraCalculator.php:56-65` |
| Nilai sertifikat | rata-rata 15 jumlah-materi, dibulatkan 2 desimal HALF_UP | `CertificateScoreCalculator.php:25-31` |
| Predikat | dari nilai **belum** dibulatkan, ambang 91/81/71/61, HALF_UP, LULUS untuk A/B/C | `CertificateScoreCalculator.php:42-57` |
| Snapshot | `moora_runs`: `formula_version` `moora-v1`, `weights`, `divisors`, `alternative_count` | `PanelService.php:126` |

Guard yang ada: tepat 15 materi aktif, 90 input/peserta, nilai 10–20 maks 2 desimal, absensi harus `present`/`absent`, D2–D6 tidak boleh nol (`MooraCalculator.php:31-35`).

## 3. SUDAH terbukti sebelum seeding (verifikasi offline, 702/702 identik)

Reproduksi rumus di atas dengan Python (openpyxl) dari sheet `PENILAIAN 15 MATERI`, lalu dibandingkan dengan sheet `REKAP TOTAL ASPEK`:

| Yang dibandingkan | Hasil |
|---|---|
| C1–C6 vs kolom `Total Aspek 1 - Tidak Hadir`, `Total Aspek 2 - Pretest` … `Total Aspek 6 - Post test` | **0 sel berbeda** (702 peserta × 6 kriteria) |
| Normalisasi vs kolom `Norm - Ketidakhadiran (Cost)`, `Norm - Pretest` … `Norm - Post test` | **0 sel berbeda**, selisih terbesar `0.0` |
| Yi vs kolom `Skor MOORA Yi` | **0 baris berbeda**, selisih terbesar `6,94e-18` (galat floating point, bukan rumus) |
| Peringkat vs kolom `Rank MOORA` | **0 baris berbeda** dari 702 |
| Pembagi `D_j` (hasil hitungan aplikasi) | `43.046486500062`, `5571.595103738247`, `5976.068691037613`, `5970.857894808752`, `5989.572104917012`, `6338.641889237789` |
| Bobot Excel (kolom `Bobot (W) 6 Aspek` baris awal) | `0,1 \| 0,1 \| 0,2 \| 0,15 \| 0,2 \| 0,25` → **sama** dengan `MooraCalculator::WEIGHTS` |
| 10 peringkat teratas | aplikasi = Excel (No 47, 614, 280, 60, 620, 532, 476, 150, 286, 197) |

**Kesimpulan §3:** Excel memakai normalisasi vektor (akar jumlah kuadrat), bobot yang sama, Yi cost/benefit, dan peringkat kompetisi — identik dengan yang diterapkan `MooraCalculator`. Selisih hanya derau floating point ~1e-18.

Catatan penting tentang Excel (perlu diketahui saat membandingkan manual):

- Excel **tidak** punya kolom predikat atau kelulusan. Yang ada hanya `Total Nilai Aspek 2–6` (mis. peserta 1 = `1165`) dan `Total 6 Aspek Raw` (`1166` = 1165 + 1 ketidakhadiran).
- `Total Nilai Aspek 2–6` adalah **jumlah** 15 materi, sedangkan `certificate_score` aplikasi adalah **rata-rata** per materi. Pembanding: `1165 ÷ 15 = 77,666…` → `77,67` → predikat **C**.
- Kolom `Status Pretest ke Posttest` (`MENINGKAT`) dan `Selisih Aspek 6 - Aspek 2` tidak dipakai aplikasi — di luar rumus MOORA.

## 4. Yang masih harus diaudit (bagian dinamis di website)

Bergantung pada data hasil seeder (`docs/plan-seed-data-lakmud.md`).

### 4.1 Jalankan perhitungan di aplikasi

1. Kegiatan `Lakmud`, status terbuka, memenuhi syarat lengkap (15 materi, 702 peserta × 90 input).
2. Tekan **Hitung MOORA** di menu Perhitungan.
3. Ekspor hasil: `moora_runs` (1 baris) + `moora_results` (702 baris) ke `storage/app/audit/moora_results.csv`.

### 4.2 Perbandingan angka aplikasi vs Excel (semua 702 peserta)

| Kolom aplikasi | Kolom Excel | Toleransi |
|---|---|---|
| `criteria[0]` … `criteria[5]` | `Total Aspek 1 - Tidak Hadir`, `Total Aspek 2 - Pretest`, … `Total Aspek 6 - Post test` | eksak (0) |
| `normalized[0]` … `[5]` | `Norm - *` | `1e-12` |
| `yi` | `Skor MOORA Yi` | `1e-12` |
| `rank` | `Rank MOORA` | eksak, 702/702 |
| `moora_runs.divisors` | hasil hitungan pembagi | `1e-9` |
| `certificate_score` | `Total Nilai Aspek 2–6` ÷ 15 | `0,005` (pembulatan 2 desimal) |

Batas mutu predikat diuji terpisah dengan contoh buatan pada nilai 60,5 / 70,5 / 80,5 / 90,5 dan nilai dasar 100 untuk memastikan ambang HALF_UP (61/71/81/91).

### 4.3 Uji tampilan (UI)

- Menu **Perhitungan**: snapshot menampilkan run #, `moora-v1`, bobot, pembagi, C1–C6 / normalisasi / terbobot per peserta.
- Menu **Hasil Perangkingan**: kolom Ranking, Peserta, Yi (10 desimal), Nilai sertifikat, Predikat, Status; urut peringkat menaik; 702 baris + paginasi; angka kolom tidak bergeser.
- Angka di UI harus sama dengan angka di database (bukan hasil hitung ulang di view).

### 4.4 Uji ketahanan (data kecil buatan, tidak menyentuh kegiatan Lakmud)

| Kasus | Harapan |
|---|---|
| Dua peserta Yi identik | peringkat sama, peringkat berikutnya melompat |
| Semua hadir (D1 = 0) | N_i1 = 0, perhitungan tetap berjalan |
| D2–D6 = 0 | ditolak dengan pesan jelas |
| Input belum lengkap | Hitung ditolak ("... 90 input ...") |
| Nilai < 10 / > 20 / 3 desimal / absensi kosong | ditolak saat input |
| Hitung ulang setelah koreksi | run baru dibuat, run lama berstatus `stale` tidak boleh difinalisasi |
| Finalisasi lalu unlock | input terkunci saat finalized, bisa dikoreksi setelah unlock |
| Hitung 2× tanpa koreksi | tidak menambah baris hasil ganda yang salah (dicek: jumlah baris setelah hitung ke-2) |

Sebagian sudah tercakup 41 test yang ada; yang belum ditambahkan sebagai skrip audit, bukan test suite permanen.

### 4.5 Bukti audit

`docs/hasil-audit-moora-vs-excel.md`: ringkasan jumlah beda per kolom, 10 baris contoh, daftar kasus §4.4 beserta hasil nyatanya, dan `sha256` berkas CSV/skrip pembanding.

## 5. Artefak yang akan dibuat

| Berkas | Isi |
|---|---|
| `python_instruct/verify_moora_vs_excel.py` | skrip pembanding: baca Excel + dump hasil aplikasi → tabel beda per kolom |
| `storage/app/audit/moora_results.csv` | dump `moora_results` (di luar git) |
| `docs/hasil-audit-moora-vs-excel.md` | laporan hasil audit |

Tidak ada perubahan pada `app/`, `routes/`, `resources/`, `config/`, `composer.json`.

## 6. Urutan dan ketergantungan

1. §3 prathasil — **selesai** (bukti: hasil jalannya skrip pembanding terhadap Excel).
2. Jalankan seeder (`docs/plan-seed-data-lakmud.md`).
3. Hitung MOORA di aplikasi (§4.1).
4. Bandingkan angka (§4.2) + uji UI (§4.3).
5. Uji ketahanan pada data buatan (§4.4).
6. Tulis laporan (§4.5).

## 7. Pertanyaan

1. Bagian §4.1 (tekan **Hitung MOORA**) — Anda jalankan sendiri lewat browser, atau saya jalankan lewat route aplikasi? Saran: saya jalankan supaya pengujian manual Anda di menu Hasil menjadi uji kedua yang independen.
2. Bila ditemukan selisih, laporan dulu saja (tanpa patch rumus) — konfirmasi.
