@extends('layout')
@section('content')
<div class="page-header"><div><h1>Log Aktivitas</h1><p class="subtitle">Catatan semua yang terjadi di panel: siapa mengubah apa, kapan, dan pada kegiatan mana.</p></div></div>
<section class="lak-card" aria-labelledby="log-title">
<h2 class="lak-card__title" id="log-title">Riwayat aktivitas</h2>
<div class="table-wrap" tabindex="0" role="region" aria-label="Tabel log aktivitas">
<table class="lak-table">
<thead><tr><th>Waktu</th><th>Aktivitas</th><th>Oleh</th><th>Kegiatan</th><th>Alasan</th></tr></thead>
<tbody>
@forelse($logs as $log)
@php
    // Nama aksi diterjemahkan di sini, bukan saat pencatatan: log tetap menyimpan kode
    // yang stabil untuk kebutuhan audit, tampilan yang menerjemahkannya untuk manusia.
    $namaAksi = [
        'event.created' => 'Menambah kegiatan',
        'event.updated' => 'Mengubah data kegiatan',
        'event.deleted' => 'Menghapus kegiatan',
        'event_materials.POST' => 'Menambah materi',
        'event_materials.PUT' => 'Mengubah materi',
        'event_materials.DELETE' => 'Menghapus materi',
        'event_participants.POST' => 'Menambah peserta',
        'event_participants.PUT' => 'Mengubah peserta',
        'event_participants.DELETE' => 'Menghapus peserta',
        'inputs.absensi' => 'Mengisi absensi',
        'inputs.pretest' => 'Mengisi nilai pretest',
        'inputs.posttest' => 'Mengisi nilai posttest',
        'inputs.keaktifan' => 'Mengisi nilai keaktifan',
        'inputs.etika' => 'Mengisi nilai etika',
        'inputs.pemahaman' => 'Mengisi nilai pemahaman',
        'calculate' => 'Menghitung hasil',
        'finalize' => 'Mengunci (finalisasi) hasil',
        'unlock' => 'Membuka kunci hasil',
        'auth.login' => 'Masuk ke panel',
        'auth.logout' => 'Keluar dari panel',
    ];
    $props = $log->properties;
    $eventId = $props->get('lakmud_event_id') ?? $props->get('event_id');
    $reason = $props->get('reason');
    $identifier = $props->get('identifier');
    // Nama kegiatan dicari dari daftar events yang sudah dimuat controller, tanpa query baru.
    $namaKegiatan = $eventId ? ($events?->firstWhere('id', $eventId)?->name ?? 'Kegiatan #'.$eventId) : null;
@endphp
<tr>
<td>{{ $log->created_at?->format('d-m-Y H:i') }}</td>
<td>{{ $namaAksi[$log->description] ?? $log->description }}@if($identifier)<span class="hint"> · {{ $identifier }}</span>@endif</td>
<td>{{ $log->causer?->name ?? 'Tidak tercatat' }}</td>
<td>{{ $namaKegiatan ?? 'Tidak terkait kegiatan' }}</td>
<td>{{ $reason ?: 'Tidak ada catatan' }}</td>
</tr>
@empty
<tr><td colspan="5">Belum ada aktivitas tercatat.</td></tr>
@endforelse
</tbody>
</table>
</div>
</section>
@endsection
