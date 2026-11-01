@extends('layout')
@section('content')
@php
    $titles = ['dashboard'=>'Dashboard penilaian','kegiatan'=>'Data kegiatan','materi'=>'Data materi','peserta'=>'Data peserta','absensi'=>'Absensi','pretest'=>'Pretest','posttest'=>'Posttest','keaktifan'=>'Keaktifan','etika'=>'Etika','pemahaman'=>'Pemahaman','perhitungan'=>'Perhitungan MOORA','hasil'=>'Hasil Perangkingan'];
    // Satu sumber rumus: halaman Perhitungan dan modal hasil memakai daftar yang sama.
    $rumusMoora = [
        ['C_{i1}=\\sum_{m=1}^{15} [\\text{absen}_{im}]', 'C1 sama dengan jumlah ketidakhadiran peserta pada 15 materi.'],
        ['C_{ij}=\\sum_{m=1}^{15} x_{imj},\\quad j=2,\\ldots,6', 'C2 sampai C6 sama dengan jumlah nilai peserta pada 15 materi.'],
        ['D_j=\\sqrt{\\sum_i C_{ij}^2}', 'Pembagi kriteria adalah akar jumlah kuadrat nilai seluruh peserta.'],
        ['N_{ij}=\\frac{C_{ij}}{D_j}', 'Nilai normalisasi adalah nilai kriteria dibagi pembagi kriteria.'],
        ['V_{ij}=N_{ij}\\,w_j', 'Nilai terbobot adalah nilai normalisasi dikali bobot kriteria.'],
        ['Y_i=\\sum_{j=2}^{6}V_{ij}-V_{i1}', 'Yi adalah jumlah manfaat C2 sampai C6 dikurangi biaya C1.'],
    ];
    // Urutan di modal hasil: hasil akhir dulu, lalu langkah yang mendahuluinya.
    $rumusHasil = [$rumusMoora[5], $rumusMoora[4], $rumusMoora[3]];
@endphp
<div class="page-header"><div><h1>{{ $titles[$page] }}</h1>@if($event)<p class="subtitle">{{ $event->name }} · {{ $event->organization }}</p>@endif</div></div>
@if(session('success'))<div class="lak-alert lak-alert--success" role="status">{{ session('success') }}</div>@endif
@if($errors->any() && !old('_modal'))<div class="lak-alert lak-alert--error" role="alert"><p class="lak-alert__head">Data belum disimpan</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if($page!=='kegiatan' && $events->isNotEmpty())<form method="get" class="lak-filter-bar lak-form">{{-- Dashboard tidak meneruskan q dan per_page: tabel kelengkapan dan kolom pencariannya sudah dihapus di halaman itu, jadi dua parameter itu tidak punya arti di sana. --}}@if($page!=='dashboard')<input type="hidden" name="q" value="{{ $q }}"><input type="hidden" name="per_page" value="{{ $listing->perPage() }}">@endif<div class="lak-field"><label for="event_id">Kegiatan</label><select id="event_id" class="lak-input" name="event_id">@foreach($events as $option)<option value="{{ $option->id }}" @selected($event?->id===$option->id)>{{ $option->name }}</option>@endforeach</select></div><button type="submit" class="lak-btn lak-btn--outlined">Tampilkan</button></form>@endif
@if(!in_array($page, ['dashboard'], true)) @include('table-filter')
@if($listing->total()===0 && $q!=='')<div class="lak-alert lak-alert--info" role="status">Tidak ada data sesuai pencarian. Ubah kata pencarian.</div>@endif
@endif
@if($event?->finalized)<div class="lak-alert lak-alert--warning" role="status">Kegiatan dikunci. Buka kunci di Hitung &amp; hasil untuk melakukan koreksi.</div>@endif
@if(in_array($page,['kegiatan','materi','peserta']))
@include('management')
@elseif(!$event)
<section class="lak-card"><h2 class="lak-card__title">Belum ada kegiatan</h2><p>Buat kegiatan, materi, dan peserta sebelum mengisi penilaian.</p><a class="lak-btn lak-btn--filled" href="/kegiatan"><md-icon aria-hidden="true">add</md-icon>Tambah kegiatan</a></section>
@elseif($page==='dashboard')
<div class="lak-metric-grid">
<section class="lak-card lak-metric"><p class="lak-metric__label">Peserta</p><p class="lak-metric__value">{{ $dashboard['participants'] }}</p><p class="lak-metric__note">terdaftar di kegiatan ini</p></section>
<section class="lak-card lak-metric"><p class="lak-metric__label">Materi</p><p class="lak-metric__value">{{ $dashboard['materials'] }}</p><p class="lak-metric__note">{{ $dashboard['materials_active'] }} aktif</p></section>
<section class="lak-card lak-metric"><p class="lak-metric__label">Kegiatan</p><p class="lak-metric__value">{{ $dashboard['events'] }}</p><p class="lak-metric__note">ada di panel</p></section>
</div>
@if(config('panel.show_results'))
@if($dashboard['results'] === 0)
<section class="lak-card"><h2 class="lak-card__title">Grafik belum tersedia</h2><p>Belum ada hasil MOORA untuk kegiatan ini. Lengkapi 90 input setiap peserta, lalu jalankan hitung.</p><div class="lak-form__actions"><a class="lak-btn lak-btn--filled" href="/perhitungan?event_id={{ $event->id }}"><md-icon aria-hidden="true">calculate</md-icon>Buka perhitungan</a></div></section>
@else
<section class="lak-card">
<h2 class="lak-card__title">Rata-rata nilai normalisasi 6 aspek</h2>
<p class="subtitle">{{ $dashboard['results'] }} peserta · status hasil: <span class="lak-badge lak-badge--{{ $dashboard['status_class'] }}">{{ $run?->status ?? 'belum dihitung' }}</span></p>
<div class="lak-chart" data-chart="{{ json_encode($dashboard['aspect']['chart'], JSON_UNESCAPED_SLASHES) }}" role="img" aria-label="{{ $dashboard['aspect']['summary'] }}"></div>
<table class="visually-hidden">
<caption>Rata-rata nilai normalisasi per aspek</caption>
<thead><tr><th scope="col">Aspek</th><th scope="col">Rata-rata N</th></tr></thead>
<tbody>@foreach($dashboard['aspect']['rows'] as $row)<tr><th scope="row">{{ $row['nama'] }}</th><td>{{ $row['nilai'] }}</td></tr>@endforeach</tbody>
</table>
</section>
<section class="lak-card">
<h2 class="lak-card__title">Lulus dan tidak lulus</h2>
<div class="lak-chart-wrap">
<div class="lak-chart lak-chart--donut" data-chart="{{ json_encode($dashboard['passed']['chart'], JSON_UNESCAPED_SLASHES) }}" role="img" aria-label="{{ $dashboard['passed']['summary'] }}"></div>
<p class="lak-chart__center" aria-hidden="true">{{ $dashboard['results'] }}<span class="lak-chart__center-note">peserta</span></p>
</div>
<ul class="lak-chart__legend">
@foreach($dashboard['passed']['rows'] as $row)<li><span class="lak-chart__swatch lak-chart__swatch--{{ $row['swatch'] }}" aria-hidden="true"></span>{{ $row['nama'] }}: <strong>{{ $row['jumlah'] }}</strong> <span class="muted">({{ $row['persen'] }}%)</span></li>@endforeach
</ul>
</section>
@endif
@endif
@elseif(in_array($page,['absensi','pretest','posttest','keaktifan','etika','pemahaman']))
@include('assessment-table')
@elseif($page==='perhitungan')
<section class="lak-card"><h2 class="lak-card__title">Status: {{ $run?->status ?? 'belum dihitung' }}</h2><p>{{ $complete ? 'Semua peserta sudah lengkap.' : 'Perhitungan memerlukan tepat 15 materi aktif dan 90 input setiap peserta.' }}</p><div class="lak-form__actions">@can('results.calculate')<form method="post" action="/hasil/{{ $event->id }}/calculate">@csrf<button type="submit" class="lak-btn lak-btn--filled" @disabled(!$complete || $event->finalized)><md-icon aria-hidden="true">calculate</md-icon>Hitung MOORA</button></form>@endcan</div></section>
@if($run)
<section class="lak-card" aria-labelledby="criteria-title"><h2 class="lak-card__title" id="criteria-title">Snapshot Peserta</h2><p class="subtitle">Rincian kriteria, normalisasi, dan nilai akhir ada di modal detail per peserta.</p><div class="table-wrap" tabindex="0" role="region" aria-label="Snapshot perhitungan peserta"><table class="lak-table"><thead><tr><th>No</th><th>Peserta</th><th>Detail</th></tr></thead><tbody>@foreach($listing as $result)<tr><td>{{ $loop->iteration }}</td><th scope="row">{{ $result->participant_name }}</th><td><button type="button" class="lak-btn lak-btn--outlined lak-btn--sm" data-modal-open="moora-detail-{{ $result->id }}" aria-label="Lihat detail perhitungan {{ $result->participant_name }}"><md-icon aria-hidden="true">visibility</md-icon>Lihat detail</button><template id="moora-detail-{{ $result->id }}">@include('partials.moora-detail-template')</template></td></tr>@endforeach</tbody></table></div></section>
@else<p class="lak-card">Belum ada hasil tersimpan.</p>@endif
@elseif($page==='hasil')
<section class="lak-card"><h2 class="lak-card__title">Status hasil: {{ $run?->status ?? 'belum dihitung' }}</h2><p>Finalisasi mengunci seluruh input pada snapshot hasil saat ini.</p><div class="lak-form__actions">@can('results.finalize')<form method="post" action="/hasil/{{ $event->id }}/finalize" data-confirm="Finalisasi akan mengunci semua input. Lanjutkan?">@csrf<button type="submit" class="lak-btn lak-btn--filled" @disabled($run?->status!=='valid' || $event->finalized)><md-icon aria-hidden="true">lock</md-icon>Finalisasi</button></form>@endcan</div>@if($event->finalized && auth()->user()->can('results.unlock'))<form method="post" action="/hasil/{{ $event->id }}/unlock" class="lak-form">@csrf<div class="lak-field"><label for="reason">Alasan koreksi</label><input id="reason" class="lak-input" name="reason" minlength="5" maxlength="500" required><span class="hint">Buka kunci akan menandai hasil saat ini sebagai arsip dan perlu hitung ulang.</span></div><div class="lak-form__actions"><button type="submit" class="lak-btn lak-btn--danger"><md-icon aria-hidden="true">lock_open</md-icon>Buka kunci</button></div></form>@endif</section>
@if($run)<section class="lak-card" aria-labelledby="ranking-title"><h2 class="lak-card__title" id="ranking-title">Peringkat MOORA</h2><p>Snapshot run #{{ $run->id }}, {{ $run->formula_version }}.</p>
<div class="table-wrap" tabindex="0" role="region" aria-label="Peringkat MOORA"><table class="lak-table"><thead><tr><th>Ranking</th><th>Peserta</th><th>Yi</th><th>Nilai sertifikat</th><th>Predikat</th><th>Status</th><th>Detail</th></tr></thead><tbody>@foreach($listing as $result)<tr><td>{{ $result->rank }}</td><th scope="row">{{ $result->participant_name }}</th><td>{{ sprintf('%.10f',$result->yi) }}</td><td>{{ $result->certificate_score }}</td><td><span class="lak-badge lak-badge--predicate-{{ strtolower($result->certificate_predicate) }}">{{ $result->certificate_predicate }}</span></td><td><span class="lak-status-text">{{ $result->certificate_status }}</span></td><td><button type="button" class="lak-btn lak-btn--outlined lak-btn--sm" data-modal-open="moora-detail-{{ $result->id }}" aria-label="Lihat detail perhitungan {{ $result->participant_name }}"><md-icon aria-hidden="true">visibility</md-icon>Lihat detail</button><template id="moora-detail-{{ $result->id }}">@include('partials.moora-detail-template')</template></td></tr>@endforeach</tbody></table></div></section>@else<p class="lak-card">Belum ada hasil tersimpan. Jalankan perhitungan terlebih dahulu.</p>@endif
@endif
@if($page === 'hasil' || $page === 'perhitungan')
<dialog class="lak-modal" id="hasil-detail-modal" aria-labelledby="hasil-detail-modal-title" data-modal>
<div class="lak-modal__head"><h2 class="lak-modal__title" id="hasil-detail-modal-title">Detail perhitungan peserta</h2><button type="button" class="app-icon-btn" data-modal-close aria-label="Tutup detail"><md-icon aria-hidden="true">close</md-icon></button></div>
<div class="lak-modal__body" data-modal-body></div>
</dialog>
@endif
@if($page !== 'dashboard') @include('table-pagination') @endif
@endsection
