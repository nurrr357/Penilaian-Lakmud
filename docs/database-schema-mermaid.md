# Skema Database LAKMUD

Sumber: `information_schema` dari database `db_lakmud_penilaian` (MySQL 8.0.30), dibaca langsung dari server, bukan dari berkas migration. Total **25 tabel, 173 kolom**.

Diagram dibagi tiga: tabel domain penilaian, tabel akses & audit, dan tabel bawaan framework. Pemisahan ini hanya untuk keterbacaan; semuanya ada di satu database.

## 1. Tabel domain penilaian

```mermaid
erDiagram
    lakmud_events ||--o{ event_materials : "punya materi"
    lakmud_events ||--o{ event_participants : "punya peserta"
    lakmud_events ||--o{ moora_runs : "punya run hitung"
    participants ||--o{ event_participants : "terdaftar di"
    event_participants ||--o{ assessment_scores : "nilai pretest sampai posttest"
    event_materials ||--o{ assessment_scores : "dinilai per materi"
    event_participants ||--o{ attendance_records : "absensi per materi"
    event_materials ||--o{ attendance_records : "diabsen per materi"
    event_participants ||--o{ moora_aspect_scores : "nilai aspek C3-C5"
    event_materials ||--o{ moora_aspect_scores : "per materi"
    moora_runs ||--o{ moora_results : "menghasilkan peringkat"
    event_participants ||--o{ moora_results : "hasil per peserta"

    lakmud_events {
        bigint id PK
        varchar name "nama kegiatan"
        varchar organization
        varchar venue
        date start_date
        date end_date
        tinyint finalized "1 = terkunci"
        timestamp created_at
        timestamp updated_at
    }

    event_materials {
        bigint id PK
        bigint lakmud_event_id FK "-> lakmud_events.id"
        varchar name
        int sequence "1-15, unik per kegiatan"
        tinyint is_active "hanya aktif yang dihitung"
        timestamp created_at
        timestamp updated_at
        varchar instructor_name "nullable, ditambahkan belakangan"
        date material_date "nullable, ditambahkan belakangan"
        time start_time "nullable, ditambahkan belakangan"
        time end_time "nullable, ditambahkan belakangan"
    }

    participants {
        bigint id PK
        varchar full_name
        timestamp created_at
        timestamp updated_at
    }

    event_participants {
        bigint id PK
        bigint lakmud_event_id FK "-> lakmud_events.id"
        int sequence_number "nomor urut otomatis"
        timestamp created_at
        timestamp updated_at
        bigint participant_id FK "-> participants.id"
        varchar group_name "nullable, ditambahkan belakangan"
        varchar delegation "nullable, ditambahkan belakangan"
        varchar status "ditambahkan belakangan"
        timestamp registered_at "nullable, ditambahkan belakangan"
    }

    attendance_records {
        bigint id PK
        bigint event_participant_id FK "-> event_participants.id"
        bigint event_material_id FK "-> event_materials.id"
        varchar status "present atau absent"
        varchar notes "nullable"
        bigint marked_by FK "-> users.id, nullable"
        timestamp marked_at "nullable"
        timestamp created_at
        timestamp updated_at
    }

    assessment_scores {
        bigint id PK
        bigint event_participant_id FK "-> event_participants.id"
        bigint event_material_id FK "-> event_materials.id"
        varchar assessment_type "pretest, posttest, dst"
        decimal score "6,2"
        bigint scored_by FK "-> users.id, nullable"
        timestamp scored_at "nullable"
        timestamp created_at
        timestamp updated_at
    }

    moora_aspect_scores {
        bigint id PK
        bigint event_participant_id FK "-> event_participants.id"
        bigint event_material_id FK "-> event_materials.id"
        decimal activity "6,2 nullable, C3"
        decimal ethics "6,2 nullable, C4"
        decimal understanding "6,2 nullable, C5"
        bigint scored_by FK "-> users.id, nullable"
        timestamp created_at
        timestamp updated_at
    }

    moora_runs {
        bigint id PK
        bigint lakmud_event_id FK "-> lakmud_events.id"
        varchar status "valid, stale, locked"
        varchar formula_version
        json weights "snapshot bobot C1-C6"
        json divisors "pembagi D1-D6"
        int alternative_count
        bigint calculated_by FK "-> users.id, nullable"
        timestamp locked_at "nullable"
        timestamp created_at
        timestamp updated_at
    }

    moora_results {
        bigint id PK
        bigint moora_run_id FK "-> moora_runs.id"
        bigint event_participant_id FK "-> event_participants.id"
        json criteria "C1-C6 per peserta"
        json normalized "N1-N6"
        json weighted "N dikali bobot"
        json material_scores "rincian per materi"
        decimal yi "18,12"
        decimal benefit_total "18,12"
        decimal cost_total "18,12"
        int rank
        decimal certificate_score "6,2"
        varchar certificate_predicate "A, B, dst"
        varchar certificate_status
        timestamp created_at
        timestamp updated_at
    }

    moora_weights {
        bigint id PK
        json weights "setelan global C1-C6"
        bigint updated_by FK "-> users.id, nullable"
        timestamp created_at
        timestamp updated_at
    }
```

`moora_weights` sengaja tidak punya relasi ke tabel lain: isinya satu baris setelan global untuk semua kegiatan, dan tidak ada foreign key yang menunjuk ke sana.

## 2. Tabel akses dan audit

```mermaid
erDiagram
    users ||--o{ model_has_roles : "diberi peran"
    roles ||--o{ model_has_roles : "dimiliki"
    roles ||--o{ role_has_permissions : "punya izin"
    permissions ||--o{ role_has_permissions : "diberikan ke"
    users ||--o{ model_has_permissions : "izin langsung"
    permissions ||--o{ model_has_permissions : "diberikan ke"
    users ||--o{ activity_log : "causer"

    users {
        bigint id PK
        varchar name
        varchar email "UNIQUE"
        timestamp email_verified_at "nullable"
        varchar password "bcrypt"
        varchar remember_token "nullable"
        timestamp created_at
        timestamp updated_at
    }

    roles {
        bigint id PK
        varchar name
        varchar guard_name
        timestamp created_at
        timestamp updated_at
    }

    permissions {
        bigint id PK
        varchar name
        varchar guard_name
        timestamp created_at
        timestamp updated_at
    }

    model_has_roles {
        bigint role_id PK "FK -> roles.id"
        varchar model_type PK
        bigint model_id PK
    }

    model_has_permissions {
        bigint permission_id PK "FK -> permissions.id"
        varchar model_type PK
        bigint model_id PK
    }

    role_has_permissions {
        bigint permission_id PK "FK -> permissions.id"
        bigint role_id PK "FK -> roles.id"
    }

    password_reset_tokens {
        varchar email PK
        varchar token
        timestamp created_at "nullable"
    }

    sessions {
        varchar id PK
        bigint user_id "IDX, nullable"
        varchar ip_address "nullable"
        text user_agent "nullable"
        longtext payload
        int last_activity "IDX"
    }

    activity_log {
        bigint id PK
        varchar log_name "IDX, nullable"
        text description
        varchar subject_type "IDX, nullable"
        bigint subject_id "nullable"
        varchar event "nullable"
        varchar causer_type "IDX, nullable"
        bigint causer_id "nullable"
        json attribute_changes "nullable"
        json properties "nullable"
        timestamp created_at "nullable"
        timestamp updated_at "nullable"
    }
```

`activity_log.causer_id` bukan foreign key fisik ke `users.id` (polimorfik lewat `causer_type`), jadi garisnya hanya penanda konseptual.

## 3. Tabel bawaan framework

Tidak dipakai untuk logika penilaian, tetapi ada di database yang sama.

```mermaid
erDiagram
    cache {
        varchar key PK
        mediumtext value
        bigint expiration "IDX"
    }

    cache_locks {
        varchar key PK
        varchar owner
        bigint expiration "IDX"
    }

    jobs {
        bigint id PK
        varchar queue "IDX"
        longtext payload
        smallint attempts
        int reserved_at "nullable"
        int available_at
        int created_at
    }

    job_batches {
        varchar id PK
        varchar name
        int total_jobs
        int pending_jobs
        int failed_jobs
        longtext failed_job_ids
        mediumtext options "nullable"
        int cancelled_at "nullable"
        int created_at
        int finished_at "nullable"
    }

    failed_jobs {
        bigint id PK
        varchar uuid "UNIQUE"
        varchar connection "IDX"
        varchar queue
        longtext payload
        longtext exception
        timestamp failed_at
    }

    migrations {
        int id PK
        varchar migration
        int batch
    }
```

## 4. Isi tabel saat ini

Angka baris dibaca bersamaan dengan skema, jadi ini kondisi database lokal saat dokumen dibuat.

| Tabel | Baris | Kolom |
|---|---:|---:|
| `lakmud_events` | 1 | 9 |
| `event_materials` | 15 | 11 |
| `participants` | 702 | 4 |
| `event_participants` | 702 | 10 |
| `attendance_records` | 10.530 | 9 |
| `assessment_scores` | 21.060 | 9 |
| `moora_aspect_scores` | 10.530 | 9 |
| `moora_runs` | 4 | 11 |
| `moora_results` | 2.808 | 16 |
| `moora_weights` | 0 | 5 |
| `users` | 2 | 8 |
| `roles` | 1 | 5 |
| `permissions` | 29 | 5 |
| `role_has_permissions` | 23 | 2 |
| `model_has_roles` | 7 | 3 |
| `model_has_permissions` | 0 | 3 |
| `activity_log` | 1.926 | 12 |
| `sessions` | 2 | 6 |
| `cache` | 3 | 3 |
| `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens` | 0 | 3-10 |
| `migrations` | 8 | 3 |

## 5. Catatan yang mudah salah dibaca

- **Tiga tabel menyimpan nilai penilaian, bukan satu.** `attendance_records` untuk absensi (C1), `assessment_scores` untuk pretest dan posttest (C2, C6), `moora_aspect_scores` untuk keaktifan, etika, dan pemahaman (C3, C4, C5). Ketiganya berpasangan unik pada (`event_participant_id`, `event_material_id`).
- **Bobot ada di dua tempat.** `moora_weights.weights` adalah setelan global yang bisa diubah operator di halaman Kriteria & Bobot. `moora_runs.weights` adalah salinan saat run dihitung, sehingga hasil lama tidak berubah ketika bobot global diganti.
- **`moora_results` menyimpan JSON, bukan kolom terpisah per kriteria.** `criteria`, `normalized`, `weighted`, dan `material_scores` berisi larik enam nilai C1-C6 sesuai urutan tetap.
- **`event_materials.sequence` dan `event_participants.sequence_number` sekarang diisi otomatis** oleh panel; kolomnya tetap ada karena dipakai untuk urutan tampil dan label M1-M15.
- **`lakmud_events.finalized` adalah kunci tingkat kegiatan.** Selama bernilai 1, semua penulisan data anak ditolak di `PanelService::mutate()`.
