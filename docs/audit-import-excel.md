# Audit Fitur Upload Excel (Import Peserta) — LAKMUD Penilaian

Tanggal audit: 20 Oktober 2026
Aplikasi: `D:\huda\project\laravel\skripsi\lakmud-penilaian` (target production)
Metode: `php artisan route:list`, inspeksi `composer.json`, `app/`, `resources/views/`, `information_schema`, dan HTTP probe ke server lokal `http://127.0.0.1:8013`.

## 1. Kesimpulan

**Fitur upload Excel tidak ada di aplikasi ini.** Statusnya BLOCKED, bukan bug.

Yang tersedia saat ini hanya penambahan peserta manual satu per satu melalui modal "Tambah peserta" dengan dua field: **Nama peserta** dan **Nomor urut**.

## 2. Bukti

| Pemeriksaan | Hasil |
|---|---|
| Route import (`/peserta/import`, `/participants/import`, `/peserta/import/preview/{token}`, `/peserta/create`) | HTTP **404** (semuanya) |
| `php artisan route:list --except-vendor` | 22 route; tidak satu pun untuk import |
| `composer.json` `require` | hanya `laravel/framework`, `laravel/tinker`, `spatie/laravel-activitylog`, `spatie/laravel-permission` — **tanpa** `maatwebsite/excel` |
| `PanelPermissions::ALL` | 21 permission; `participants.view` dan `participants.manage` ada, **`participants.import` tidak ada** |
| Controller / Service / Imports | tidak ada kelas import; `app/` hanya berisi 18 file (PanelController, PanelService, PanelPermissions, MooraCalculator, CertificateScoreCalculator, 10 model, InputRequest) |
| Blade | tidak ada `enctype="multipart/form-data"` maupun `<input type="file">` di seluruh `resources/views` |
| Kata kunci di halaman (`import`, `unggah`, `upload`, `excel`, `xlsx`, `csv`) | **0 kemunculan** di `/`, `/kegiatan`, `/materi`, `/peserta`, `/absensi`, `/panduan` |

## 3. Skema database saat ini tidak mendukung import biodata

Tabel `participants` pada `db_lakmud_penilaian` hanya punya:

```
id, full_name, created_at, updated_at
```

Tabel `event_participants`:

```
id, lakmud_event_id, participant_id, sequence_number, group_name, delegation, status, registered_at, created_at, updated_at
```

Kolom biodata yang dibutuhkan alur import (FR-008 "setara field `data base`") **tidak ada sama sekali**: `member_number`, `birth_place`, `birth_date`, `phone`, `email`, `occupation`, `address_detail`.

## 4. Alur yang ada sekarang (manual)

1. Operator membuka `/peserta?event_id=N`.
2. Klik **Tambah peserta** → modal berisi `name` (maks 200 karakter) dan `sequence` (angka, unik per kegiatan).
3. `POST /kegiatan/{event}/peserta` → `PanelController@child` → `PanelService`.
4. Baris masuk tabel `event_participants` dengan `sequence_number`.
5. `PUT`/`DELETE /kegiatan/{event}/peserta/{record}` untuk ubah/hapus. Terkunci bila kegiatan sudah final.

Tidak ada jalur bulk/massal. Untuk 100 peserta, operator harus membuka modal 100 kali.

## 5. Alur import referensi (ada di repo lama, bukan di repo production ini)

Implementasi lengkap pernah ada di `D:\huda\project\laravel\lakmud`:
`app/Http/Controllers/Admin/ParticipantImportController.php`, `app/Services/ParticipantImportService.php`, `app/Imports/ParticipantsToArray.php`, dengan `maatwebsite/excel`.

### 5.1 Endpoint

| Method | Endpoint | Permission |
|---|---|---|
| GET | `/admin/participants/import` | `participants.import` |
| POST | `/admin/participants/import` | `participants.import` |
| GET | `/admin/participants/import/preview/{token}` | `participants.import` |
| POST | `/admin/participants/import/commit` | `participants.import` |

### 5.2 Langkah alur

1. **Unggah.** `POST /import` memvalidasi: `file` → `required`, `file`, `extensions:xlsx,xls,csv`, `max:5120` (5 MB); plus `register_to_event` opsional (hanya kegiatan berstatus ACTIVE).
   - Catatan penting: memakai `extensions:` **bukan** `mimes:`. `mimes` menebak tipe dari isi berkas, dan CSV polos terbaca `text/plain` seperti `.txt` sehingga CSV valid ikut ditolak. `extensions` memeriksa nama berkas; parser tetap memvalidasi isi saat membaca.
2. **Parse.** Maatwebsite Excel membaca sheet pertama menjadi array mentah (`ParticipantsToArray`) — tidak ada model yang disimpan pada tahap ini.
3. **Normalisasi header.** Header di-trim dan dipetakan lewat alias Indonesia/Inggris, antara lain: `no anggota`/`member number`/`no`, `nama`/`nama lengkap`/`full name`, `tempat lahir`, `tanggal lahir`/`tgl lahir`, `hp`/`telepon`/`no hp`, `email`, `pekerjaan`, `alamat`. Baris kosong seluruhnya dilewati.
4. **Validasi per baris** di `ParticipantImportService::validateRows` (kelas murni, tanpa HTTP, sehingga bisa unit-test):
   - `full_name` wajib, `Str::squish`, maks 180 karakter.
   - `birth_date` dibaca dengan urutan format `d/m/Y`, `d-m-Y`, `Y-m-d`, `d.m.Y` **dengan round-trip check** (supaya `30/02/2001` tidak lolos menjadi `02/03/2001`); tidak boleh tanggal masa depan; gagal → error per baris, bukan HTTP 500.
   - `phone` dinormalisasi lalu wajib 6+ digit.
   - `email` divalidasi `FILTER_VALIDATE_EMAIL`, di-lowercase.
   - `birth_place` maks 150, `occupation` maks 150, `address_detail` maks 255, `member_number` maks 100.
   - Hasil tiap baris: `{row, raw, data, errors}`.
5. **Pratinjau bertanda tangan.** Belum ada yang ditulis ke database. Payload hasil validasi (`rows`, `event_id`, `created_at`, `owner_id`) disimpan sebagai `storage/app/imports/<token>.json` dengan token 40 karakter acak. Dipilih berkas, bukan session, supaya berkas ~1.000 baris tidak membebani session store. Lalu redirect ke `GET …/preview/{token}`.
6. **Pratinjau.** Halaman menampilkan tabel baris beserta error per baris; user bisa membatalkan atau lanjut commit.
7. **Commit.** `POST …/import/commit` dengan `token`:
   - Validasi token: regex `\A[A-Za-z0-9]{40}\z`, berkas harus ada, `owner_id` harus sama dengan user yang login, dan umur token maksimum **2 jam** (lewat itu berkas dihapus dan user diminta unggah ulang).
   - Dijalankan dalam **DB transaction**; baris kegiatan dikunci `lockForUpdate`; bila kegiatan tidak lagi ACTIVE → impor dibatalkan (409).
   - **Upsert, tanpa duplikasi senyap:** dicocokkan dulu lewat `member_number` (termasuk baris soft-deleted → di-restore, karena `member_number` unik di database), jika tidak ada baru lewat kombinasi `full_name` + `phone`.
   - **Baris error tidak membatalkan seluruh berkas** — hanya baris tanpa error yang di-commit, sisanya dilaporkan sebagai dilewati.
   - Bila `register_to_event` dipilih: peserta di-attach ke kegiatan dengan `sequence_number` lanjut dari nilai maksimum, status `registered`, `registered_at` diisi; dan MOORA run ditandai **stale**.
8. **Selesai.** Berkas token dihapus, redirect ke daftar peserta dengan flash: `"Import selesai: X peserta baru, Y diperbarui, Z didaftarkan ke kegiatan. N baris dilewati karena error."`

## 6. Gap yang harus ditutup bila fitur ini dibangun di aplikasi production ini

1. **Dependency:** `maatwebsite/excel` (membutuhkan `ext-zip` dan `ext-gd` pada PHP server).
2. **Migrasi:** tambah kolom biodata di `participants` (`member_number` unik, `birth_place`, `birth_date`, `phone`, `email`, `occupation`, `address_detail`) dan `softDeletes` bila ingin perilaku restore; sesuaikan `$fillable`/`$guarded` dan cast tanggal.
3. **Permission:** tambah `participants.import` di `PanelPermissions::ALL` + seeder, lalu putuskan apakah masuk role `operator_penilaian` atau role terpisah.
4. **Kode:** controller import, service validasi murni, kelas importer, view form + preview, dan 4 route baru dengan middleware `permission:participants.import`.
5. **Import dari repo lama perlu disesuaikan:** repo lama memakai `Participant::normalizePhone()`, `MooraResultEngine::stale()`, `LakmudEvent::STATUS_ACTIVE`, dan soft delete — model di aplikasi ini belum memiliki method/karakteristik tersebut.
6. **Test minimal:** unggah valid, unggah CSV, unggah `.txt`/berkas besar ditolak, header alias, tanggal tidak valid per baris, token kedaluwarsa, token milik user lain, kegiatan sudah final, baris error dilewati, dan pendaftaran massal menandai hasil stale.

## 7. Keputusan yang perlu diambil

Spesifikasi panel — `docs/admin-panel-1-role-penilaian-moora.md` §8 — menempatkan **"CRUD kegiatan, materi, peserta, users, referensi wilayah"** dan **ekspor** di **luar scope** panel penilaian; import peserta secara eksplisit adalah milik panel admin utama (`00-requirements-scope.md` FR-008, `participants.import` hanya untuk peran admin/operator).

Namun implementasi saat ini sudah memuat `participants.view` + `participants.manage` (tambah/ubah/hapus peserta manual), yang berarti scope sudah bergeser dari spesifikasi asli. Karena itu perlu dipilih salah satu:

- **A.** Tambahkan import Excel ke aplikasi ini (gap §6 ditutup) — panel jadi self-service penuh.
- **B.** Pertahankan panel ini tanpa import; peserta dimasukkan manual atau diimpor dari aplikasi admin terpisah, sesuai spesifikasi asli.
- **C.** Bangun ulang panel admin utama (repo `lakmud`) untuk produksi, dan panel ini tetap khusus penilaian.

Pilihan A menambah dependency, migrasi skema, dan permukaan test; pilihan B paling sesuai spesifikasi dan paling ringan untuk production.
