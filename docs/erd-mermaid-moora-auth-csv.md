# ERD LAKMUD untuk Mermaid

Dokumen ini berisi blok `erDiagram` yang siap ditempel ke <https://mermaid.live>. Setiap blok berdiri sendiri: salin satu blok, tempel apa adanya, tidak perlu tambahan apa pun.

Sumber: `information_schema` database `db_lakmud_penilaian` (MySQL 8.0.30, InnoDB, utf8mb4_unicode_ci). Semua nama tabel dan kolom dibaca dari server, bukan dari berkas migration. Total 25 tabel, 173 kolom.

Isi dokumen:

1. ERD inti MOORA
2. ERD autentikasi dan otorisasi
3. ERD pipeline CSV (sumber data awal)
4. Peta fitur ke tabel
5. Catatan yang perlu diketahui

## 1. ERD inti MOORA

Sepuluh tabel inti plus `users` sebagai pemilik jejak pengisian. Yang disimpan di sini: kegiatan, peserta, nilai enam kriteria, bobot, dan hasil perangkingan.

```mermaid
erDiagram
    lakmud_events ||--o{ event_materials : "punya 15 materi"
    lakmud_events ||--o{ event_participants : "punya 702 pendaftar"
    lakmud_events ||--o{ moora_runs : "punya riwayat hitung"
    participants ||--o{ event_participants : "terdaftar lewat"
    event_participants ||--o{ attendance_records : "absensi C1 per materi"
    event_materials ||--o{ attendance_records : "diabsen per materi"
    event_participants ||--o{ assessment_scores : "pretest C2 dan posttest C6"
    event_materials ||--o{ assessment_scores : "dinilai per materi"
    event_participants ||--o{ moora_aspect_scores : "keaktifan C3 etika C4 pemahaman C5"
    event_materials ||--o{ moora_aspect_scores : "dinilai per materi"
    moora_runs ||--o{ moora_results : "menghasilkan peringkat"
    event_participants ||--o{ moora_results : "satu baris per peserta"
    users ||--o{ attendance_records : "marked_by, set null"
    users ||--o{ assessment_scores : "scored_by, set null"
    users ||--o{ moora_aspect_scores : "scored_by, set null"
    users ||--o{ moora_runs : "calculated_by, set null"
    users ||--o{ moora_weights : "updated_by, set null"

    lakmud_events {
        bigint id PK "auto increment"
        varchar name "nama kegiatan"
        varchar organization "penyelenggara"
        varchar venue "tempat"
        date start_date "tanggal mulai"
        date end_date "tanggal selesai"
        tinyint finalized "1 berarti terkunci"
        timestamp created_at
        timestamp updated_at
    }

    event_materials {
        bigint id PK
        bigint lakmud_event_id FK "ke lakmud_events id, cascade"
        varchar name "nama materi"
        int sequence UK "1 sampai 15, unik per kegiatan"
        tinyint is_active "hanya aktif yang dihitung"
        timestamp created_at
        timestamp updated_at
        varchar instructor_name "opsional"
        date material_date "opsional"
        time start_time "opsional"
        time end_time "opsional"
    }

    participants {
        bigint id PK
        varchar full_name "nama lengkap, ada index"
        timestamp created_at
        timestamp updated_at
    }

    event_participants {
        bigint id PK
        bigint lakmud_event_id FK "ke lakmud_events id, cascade"
        int sequence_number UK "nomor urut otomatis, unik per kegiatan"
        timestamp created_at
        timestamp updated_at
        bigint participant_id FK "ke participants id, restrict"
        varchar group_name "opsional"
        varchar delegation "opsional"
        varchar status "terisi registered"
        timestamp registered_at "opsional"
    }

    attendance_records {
        bigint id PK
        bigint event_participant_id FK "ke event_participants id, cascade"
        bigint event_material_id FK "ke event_materials id, cascade"
        varchar status "present atau absent"
        varchar notes "opsional"
        bigint marked_by FK "ke users id, set null"
        timestamp marked_at "opsional"
        timestamp created_at
        timestamp updated_at
    }

    assessment_scores {
        bigint id PK
        bigint event_participant_id FK "ke event_participants id, cascade"
        bigint event_material_id FK "ke event_materials id, cascade"
        varchar assessment_type UK "pretest atau posttest"
        decimal score "desimal 6,2, rentang 10 sampai 20"
        bigint scored_by FK "ke users id, set null"
        timestamp scored_at "opsional"
        timestamp created_at
        timestamp updated_at
    }

    moora_aspect_scores {
        bigint id PK
        bigint event_participant_id FK "ke event_participants id, cascade"
        bigint event_material_id FK "ke event_materials id, cascade"
        decimal activity "C3 keaktifan, desimal 6,2"
        decimal ethics "C4 etika, desimal 6,2"
        decimal understanding "C5 pemahaman, desimal 6,2"
        bigint scored_by FK "ke users id, set null"
        timestamp created_at
        timestamp updated_at
    }

    moora_runs {
        bigint id PK
        bigint lakmud_event_id FK "ke lakmud_events id, cascade"
        varchar status "valid, stale, atau locked"
        varchar formula_version "terisi moora-v1"
        json weights "salinan bobot C1 sampai C6 saat dihitung"
        json divisors "pembagi D1 sampai D6"
        int alternative_count "jumlah peserta yang dihitung"
        bigint calculated_by FK "ke users id, set null"
        timestamp locked_at "opsional"
        timestamp created_at
        timestamp updated_at
    }

    moora_results {
        bigint id PK
        bigint moora_run_id FK "ke moora_runs id, cascade"
        bigint event_participant_id FK "ke event_participants id, cascade"
        json criteria "larik C1 sampai C6"
        json normalized "larik N1 sampai N6"
        json weighted "larik N dikali bobot"
        json material_scores "rincian nilai per materi"
        decimal yi "desimal 18,12, dasar peringkat"
        decimal benefit_total "desimal 18,12"
        decimal cost_total "desimal 18,12"
        int rank "peringkat 1 sampai n"
        decimal certificate_score "desimal 6,2"
        varchar certificate_predicate "A, B, C, atau D"
        varchar certificate_status "LULUS atau TIDAK LULUS"
        timestamp created_at
        timestamp updated_at
    }

    moora_weights {
        bigint id PK
        json weights "setelan global C1 sampai C6"
        bigint updated_by FK "ke users id, set null"
        timestamp created_at
        timestamp updated_at
    }
```

`moora_weights` sengaja tidak punya relasi ke tabel mana pun selain `users`. Isinya satu baris setelan global untuk semua kegiatan, dan tidak ada foreign key yang menunjuk ke sana.

Garis `users` ke lima tabel nilai adalah jejak siapa yang mengisi atau menghitung, dengan aturan `ON DELETE SET NULL`. Menghapus user tidak menghapus data penilaian, hanya mengosongkan kolom pelakunya. Entitas `users` sendiri dirinci penuh di blok 2.

Kunci unik yang menjaga data tidak dobel:

| Tabel | Kunci unik |
|---|---|
| `event_materials` | (`lakmud_event_id`, `sequence`) dan (`id`, `lakmud_event_id`) |
| `event_participants` | (`lakmud_event_id`, `sequence_number`), (`lakmud_event_id`, `participant_id`), dan (`id`, `lakmud_event_id`) |
| `attendance_records` | (`event_participant_id`, `event_material_id`) |
| `assessment_scores` | (`event_participant_id`, `event_material_id`, `assessment_type`) |
| `moora_aspect_scores` | (`event_participant_id`, `event_material_id`) |
| `moora_results` | (`moora_run_id`, `event_participant_id`) |

## 2. ERD autentikasi dan otorisasi

Sembilan tabel. Enam tabel pertama adalah paket izin `spatie/laravel-permission`, tiga sisanya menangani sesi, token reset, dan jejak audit.

```mermaid
erDiagram
    users ||..o{ model_has_roles : "memegang peran, polimorfik"
    roles ||--o{ model_has_roles : "diberikan ke"
    roles ||--o{ role_has_permissions : "mengandung izin"
    permissions ||--o{ role_has_permissions : "dipakai peran"
    users ||..o{ model_has_permissions : "izin langsung, tabel kosong, tanpa FK"
    permissions ||--o{ model_has_permissions : "diberikan langsung"
    users ||..o{ sessions : "sesi login, tanpa FK fisik"
    users ||..o{ activity_log : "pelaku perubahan, polimorfik"
    users ||..o{ password_reset_tokens : "lewat email, tanpa FK"

    users {
        bigint id PK
        varchar name "nama tampilan"
        varchar email UK "unik, dipakai login"
        timestamp email_verified_at "opsional, tidak dipakai"
        varchar password "hash bcrypt"
        varchar remember_token "opsional"
        timestamp created_at
        timestamp updated_at
    }

    roles {
        bigint id PK
        varchar name UK "unik per guard"
        varchar guard_name "terisi web"
        timestamp created_at
        timestamp updated_at
    }

    permissions {
        bigint id PK
        varchar name UK "unik per guard"
        varchar guard_name "terisi web"
        timestamp created_at
        timestamp updated_at
    }

    model_has_roles {
        bigint role_id PK "ke roles id, cascade"
        varchar model_type PK "kelas model, misal App Models User"
        bigint model_id PK "id user"
    }

    model_has_permissions {
        bigint permission_id PK "ke permissions id, cascade"
        varchar model_type PK "kelas model"
        bigint model_id PK "id user"
    }

    role_has_permissions {
        bigint permission_id PK "ke permissions id, cascade"
        bigint role_id PK "ke roles id, cascade"
    }

    password_reset_tokens {
        varchar email PK "email pemohon"
        varchar token "token reset"
        timestamp created_at "opsional"
    }

    sessions {
        varchar id PK "id sesi"
        bigint user_id "index, kosong untuk tamu"
        varchar ip_address "opsional"
        text user_agent "opsional"
        longtext payload "isi sesi terenkode"
        int last_activity "index, timestamp unix"
    }

    activity_log {
        bigint id PK
        varchar log_name "index, terisi default"
        text description "nama aksi, misal calculate"
        varchar subject_type "index, opsional"
        bigint subject_id "opsional"
        varchar event "opsional"
        varchar causer_type "index, opsional"
        bigint causer_id "opsional, id user pelaku"
        json attribute_changes "opsional"
        json properties "berisi lakmud_event_id dan payload"
        timestamp created_at
        timestamp updated_at
    }
```

Tiga hal yang mudah salah dibaca di sini:

- **Garis putus-putus berarti relasi logis tanpa foreign key fisik.** `model_has_roles.model_id`, `model_has_permissions.model_id`, `sessions.user_id`, `activity_log.causer_id`, dan `password_reset_tokens.email` semuanya diisi aplikasi, bukan dijaga database. Hanya garis solid yang punya constraint nyata. Blok ini memuat 4 dari 21 foreign key: `model_has_roles.role_id` ke `roles`, `model_has_permissions.permission_id` ke `permissions`, dan dua di `role_has_permissions`.
- `activity_log.causer_id` **bukan** foreign key fisik. Relasinya polimorfik lewat `causer_type`, jadi tidak ada constraint ke `users.id` dan penghapusan user tidak menyentuh log.
- `model_has_roles` dan `model_has_permissions` memakai primary key gabungan tiga kolom (`role_id` atau `permission_id`, `model_type`, `model_id`). Kolom `model_type` yang membuatnya bisa dipakai model apa pun, bukan hanya `users`.

## 3. ERD pipeline CSV (sumber data awal)

Aplikasi **tidak punya fitur export CSV**. Yang ada adalah import satu arah: berkas Excel dikonversi ke dua CSV oleh `python_instruct/excel_to_seed_csv.py`, lalu `LakmudDataSeeder` memasukkannya ke database. Diagram ini memetakan kolom CSV ke kolom tabel tujuan.

Entitas `csv_peserta` dan `csv_penilaian` adalah **pseudo-entity**: keduanya bukan tabel di database, hanya bentuk berkas masukan. Tabel DB di blok ini sengaja hanya menampilkan kolom yang terlibat pipeline, bukan seluruh kolom. Skema lengkap tiap tabel ada di blok 1 dan blok 2.

```mermaid
erDiagram
    csv_peserta ||--|| participants : "kolom nama"
    csv_peserta ||--|| event_participants : "kolom no jadi nomor urut"
    csv_penilaian ||--|| attendance_records : "aspek1 jadi status"
    csv_penilaian ||--|| assessment_scores : "aspek2 pretest, aspek6 posttest"
    csv_penilaian ||--|| moora_aspect_scores : "aspek3 aspek4 aspek5"
    csv_penilaian }o--|| event_materials : "materi_no dan materi_nama"

    csv_peserta {
        int no "nomor urut 1 sampai 702"
        string nama "nama lengkap peserta"
    }

    csv_penilaian {
        int no "nomor urut peserta"
        int materi_no "1 sampai 15"
        string materi_nama "nama materi"
        int aspek1 "0 atau 1, 1 berarti tidak hadir"
        decimal aspek2 "pretest 10 sampai 20"
        decimal aspek3 "keaktifan 10 sampai 20"
        decimal aspek4 "etika 10 sampai 20"
        decimal aspek5 "pemahaman 10 sampai 20"
        decimal aspek6 "posttest 10 sampai 20"
    }

    participants {
        bigint id PK
        varchar full_name "dari kolom nama"
    }

    event_participants {
        bigint id PK
        int sequence_number "dari kolom no"
        bigint participant_id FK "hasil insert participants"
    }

    event_materials {
        bigint id PK
        int sequence UK "dari materi_no"
        varchar name "dari materi_nama"
    }

    attendance_records {
        bigint event_participant_id FK
        bigint event_material_id FK
        varchar status "aspek1 1 jadi absent, 0 jadi present"
    }

    assessment_scores {
        bigint event_participant_id FK
        bigint event_material_id FK
        varchar assessment_type "pretest atau posttest"
        decimal score "aspek2 atau aspek6"
    }

    moora_aspect_scores {
        bigint event_participant_id FK
        bigint event_material_id FK
        decimal activity "aspek3"
        decimal ethics "aspek4"
        decimal understanding "aspek5"
    }
```

Pemetaan kolom CSV ke kolom database, sesuai kode `LakmudDataSeeder::isiNilai()`:

| Kolom CSV | Tabel tujuan | Kolom tujuan | Aturan |
|---|---|---|---|
| `peserta.csv` `no` | `event_participants` | `sequence_number` | dipakai juga sebagai kunci upsert |
| `peserta.csv` `nama` | `participants` | `full_name` | di-trim |
| `penilaian.csv` `materi_no` | `event_materials` | `sequence` | wajib tepat 15 nilai unik |
| `penilaian.csv` `materi_nama` | `event_materials` | `name` | diambil dari baris pertama tiap materi |
| `penilaian.csv` `aspek1` | `attendance_records` | `status` | hanya boleh 0 atau 1, 1 menjadi `absent` |
| `penilaian.csv` `aspek2` | `assessment_scores` | `score` dengan `assessment_type` = `pretest` | 10 sampai 20, maksimal dua desimal |
| `penilaian.csv` `aspek3` | `moora_aspect_scores` | `activity` | 10 sampai 20, maksimal dua desimal |
| `penilaian.csv` `aspek4` | `moora_aspect_scores` | `ethics` | 10 sampai 20, maksimal dua desimal |
| `penilaian.csv` `aspek5` | `moora_aspect_scores` | `understanding` | 10 sampai 20, maksimal dua desimal |
| `penilaian.csv` `aspek6` | `assessment_scores` | `score` dengan `assessment_type` = `posttest` | 10 sampai 20, maksimal dua desimal |

`moora_runs` dan `moora_results` sengaja tidak diisi seeder. Keduanya hanya terisi lewat tombol Hitung MOORA, sehingga hasil perhitungan selalu berasal dari data yang benar-benar tersimpan.

## 4. Peta fitur ke tabel

Setiap halaman panel dan tabel yang dibacanya. Berguna saat menelusuri dari layar ke skema.

| Fitur atau halaman | Tabel utama | Tabel pendukung |
|---|---|---|
| Login dan logout | `users`, `sessions` | `activity_log` tidak dipakai di sini |
| Pemeriksaan izin setiap halaman | `permissions`, `roles`, `model_has_roles`, `role_has_permissions` | `model_has_permissions` (kosong) |
| Dashboard | `lakmud_events`, `event_participants`, `event_materials`, `moora_runs`, `moora_results` | tidak ada |
| Data kegiatan | `lakmud_events` | `activity_log` |
| Data materi | `event_materials` | `lakmud_events` untuk status kunci |
| Data peserta | `event_participants`, `participants` | `lakmud_events` |
| Absensi | `attendance_records` | `event_participants`, `event_materials` |
| Pretest dan posttest | `assessment_scores` | `event_participants`, `event_materials` |
| Keaktifan, etika, pemahaman | `moora_aspect_scores` | `event_participants`, `event_materials` |
| Perhitungan MOORA | `moora_runs`, `moora_results` | tiga tabel nilai di atas |
| Hasil perangkingan | `moora_results`, `moora_runs` | `event_participants`, `participants` |
| Finalisasi dan buka kunci | `moora_runs`, `lakmud_events` | `activity_log` |
| Kriteria dan bobot | `moora_weights` | `moora_runs` untuk status kunci |
| Jejak audit | `activity_log` | `users` lewat `causer_id` |
| Import data awal CSV | semua tabel inti kecuali `moora_runs` dan `moora_results` | tidak ada |

## 5. Catatan yang perlu diketahui

**Tidak ada fitur export CSV.** Pencarian di `app/`, `routes/`, `resources/views/`, `resources/js/`, `config/`, dan `composer.json` untuk kata `csv`, `export`, `download`, `streamDownload`, dan `fputcsv` tidak menemukan satu pun jalur ekspor. CSV hanya dipakai sebagai masukan. Kalau ekspor memang dibutuhkan, itu fitur baru, bukan dokumentasi dari yang sudah ada.

**Ada enam izin lama yang tersisa di database.** `permissions` berisi 29 baris, sedangkan `PanelPermissions::ALL` hanya mendefinisikan 23. Enam sisanya tidak dipakai kode mana pun: `assessments.aspects.view`, `assessments.aspects.input`, `data.manage`, `inputs.manage`, `panel.access`, `results.manage`. `role_has_permissions` berisi 23 baris, jadi peran operator hanya memegang izin yang aktif. Sisa ini tidak mengganggu, tetapi sebaiknya dibersihkan sebelum sidang agar tidak menimbulkan pertanyaan saat skema diperiksa.

**`password_reset_tokens` ada tetapi tidak dipakai.** Tabel ini bawaan Laravel, dan `routes/web.php` tidak punya rute lupa kata sandi maupun reset. Belum ada fitur reset mandiri. Akun tanpa kata sandi harus direset lewat `artisan tinker`.

**`moora_weights` boleh kosong.** Saat tabel ini kosong, `MooraWeights::current()` memakai bobot bawaan `0.10 / 0.10 / 0.20 / 0.15 / 0.20 / 0.25` dari `MooraCalculator::WEIGHTS`. Jadi kosong bukan berarti rusak.

**Bobot tersimpan dua kali dengan tujuan berbeda.** `moora_weights.weights` adalah setelan global yang bisa diubah operator. `moora_runs.weights` adalah salinan saat run dihitung, sehingga hasil lama tidak berubah ketika bobot global diganti. Saat membandingkan hasil antar run, yang menentukan adalah kolom di `moora_runs`, bukan yang di `moora_weights`.

**Nilai enam kriteria tidak ada di satu tabel.** C1 di `attendance_records`, C2 dan C6 di `assessment_scores` dengan pembeda `assessment_type`, C3 sampai C5 di `moora_aspect_scores`. Setelah perhitungan, keenam nilai itu disalin menjadi larik JSON di `moora_results.criteria` sesuai urutan tetap C1 sampai C6.

**Tidak ada view dan tidak ada trigger.** Seluruh logika ada di lapisan aplikasi (`PanelService`, `MooraCalculator`, `MooraWeights`, `CertificateScoreCalculator`), bukan di database.

**Kunci kegiatan ada di `lakmud_events.finalized`.** Selama bernilai 1, `PanelService::mutate()` menolak semua penulisan data anak dan mengembalikan pesan kesalahan, walaupun kolomnya sendiri tidak punya constraint.
