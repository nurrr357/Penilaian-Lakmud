@extends('layout')
@section('content')
@php($titles=['dashboard'=>'Dashboard penilaian','kegiatan'=>'Data kegiatan','materi'=>'Data materi','peserta'=>'Data peserta','absensi'=>'Absensi','pretest'=>'Pretest','posttest'=>'Posttest','keaktifan'=>'Keaktifan','etika'=>'Etika','pemahaman'=>'Pemahaman','perhitungan'=>'Perhitungan MOORA','hasil'=>'Hasil Perangkingan','panduan'=>'Panduan singkat'])
<h1>{{ $titles[$page] }}</h1>
@if(session('success'))<p class="notice" role="status">{{ session('success') }}</p>@endif
@if($errors->any() && !old('_modal'))<div class="error" role="alert"><strong>Data belum disimpan</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if($page!=='kegiatan' && $events->isNotEmpty())<form method="get" class="filter"><input type="hidden" name="q" value="{{ $q }}"><input type="hidden" name="per_page" value="{{ $listing->perPage() }}"><label>Kegiatan<select name="event_id">@foreach($events as $option)<option value="{{ $option->id }}" @selected($event?->id===$option->id)>{{ $option->name }}</option>@endforeach</select></label><button class="secondary">Tampilkan</button></form>@endif
@if($page!=='panduan') @include('table-filter')
@if($listing->total()===0 && $q!=='')<p class="notice" role="status">Tidak ada data sesuai pencarian. Ubah kata pencarian.</p>@endif
@endif
@if($event?->finalized)<p class="notice">Kegiatan dikunci. Buka kunci di Hitung & hasil untuk melakukan koreksi.</p>@endif
@if($page==='panduan')
<section class="card"><h2>Aturan penilaian</h2><ul><li>Siapkan tepat 15 materi aktif dan seluruh peserta kegiatan.</li><li>Isi absensi secara eksplisit: hadir atau tidak hadir. Kosong berarti belum diisi.</li><li>Pretest, keaktifan, etika, pemahaman, dan posttest masing-masing 10 sampai 20, maksimal dua desimal.</li><li>Setiap peserta harus lengkap 90 input sebelum hitung.</li><li>C1 adalah jumlah tidak hadir (cost 0,10). C2 sampai C6 adalah jumlah nilai (benefit 0,10; 0,20; 0,15; 0,20; 0,25).</li><li>Yi menentukan ranking, bukan nilai sertifikat. Nilai sertifikat adalah rata-rata total lima aspek dari 15 materi.</li><li>Predikat setelah pembulatan integer: A 91-100, B 81-90, C 71-80, D 61-70, E 50-60. A/B/C lulus.</li><li>Finalisasi mengunci data. Buka kunci memerlukan alasan dan membuat hasil stale. Setelah koreksi, hitung ulang.</li></ul></section>
@elseif(in_array($page,['kegiatan','materi','peserta']))
@include('management')
@elseif(!$event)
<section class="card"><h2>Belum ada kegiatan</h2><p>Buat kegiatan, materi, dan peserta sebelum mengisi penilaian.</p><a class="button" href="/kegiatan">Tambah kegiatan</a></section>
@elseif($page==='dashboard')
<section class="card"><h2>Kelengkapan {{ $event->name }}</h2><p>{{ $active->count() }} / 15 materi aktif. {{ $participants->count() }} peserta. Status hasil: <strong>{{ $run?->status ?? 'belum dihitung' }}</strong>.</p>
<div class="table-wrap"><table><thead><tr><th>No.</th><th>Peserta</th><th>Input lengkap</th><th>Yang perlu diisi</th></tr></thead><tbody>@forelse($listing as $participant)<tr><td>{{ $listing->firstItem()+$loop->index }}</td><th>{{ $participant->name }}</th><td>{{ $completion[$participant->id]['count'] }} / 90</td><td>@if($completion[$participant->id]['missing'])<details><summary>Lihat input kosong</summary><ul>@foreach($completion[$participant->id]['missing'] as $missing)<li>{{ $missing }}</li>@endforeach</ul></details>@elseif($active->count()===15) Lengkap @else Siapkan tepat 15 materi aktif. @endif</td></tr>@empty<tr><td colspan="4">Belum ada peserta. Tambahkan melalui Data kegiatan.</td></tr>@endforelse</tbody></table></div><a class="button" href="/perhitungan?event_id={{ $event->id }}">Buka perhitungan</a></section>
@elseif(in_array($page,['absensi','pretest','posttest','keaktifan','etika','pemahaman']))
@include('assessment-table')
@elseif($page==='perhitungan')
<section class="card"><h2>Status: {{ $run?->status ?? 'belum dihitung' }}</h2><p>{{ $complete ? 'Semua peserta sudah lengkap.' : 'Perhitungan memerlukan tepat 15 materi aktif dan 90 input setiap peserta.' }}</p><div class="actions">@can('results.calculate')<form method="post" action="/hasil/{{ $event->id }}/calculate">@csrf<button @disabled(!$complete || $event->finalized)>Hitung MOORA</button></form>@endcan</div></section>
@if($run)
<section class="card formula-section" aria-labelledby="moora-detail-title">
<h2 id="moora-detail-title">Detail Perhitungan MOORA</h2>
<p>Snapshot run #{{ $run->id }}, {{ $run->formula_version }}, {{ $run->created_at }}. @if($run->status==='stale')Hasil arsip tidak berlaku untuk finalisasi. Hitung ulang setelah koreksi.@endif</p>
<p>C1 adalah jumlah ketidakhadiran. C2 sampai C6 adalah jumlah 15 nilai per materi untuk pretest, keaktifan, etika, pemahaman, dan posttest.</p>
@foreach([
['C_{i1}=\\sum_{m=1}^{15} [\\text{absen}_{im}]','C1 sama dengan jumlah ketidakhadiran peserta pada 15 materi.'],
['C_{ij}=\\sum_{m=1}^{15} x_{imj},\\quad j=2,\\ldots,6','C2 sampai C6 sama dengan jumlah nilai peserta pada 15 materi.'],
['D_j=\\sqrt{\\sum_i C_{ij}^2}','Pembagi kriteria adalah akar jumlah kuadrat nilai seluruh peserta.'],
['N_{ij}=\\frac{C_{ij}}{D_j}','Nilai normalisasi adalah nilai kriteria dibagi pembagi kriteria.'],
['V_{ij}=N_{ij}\\,w_j','Nilai terbobot adalah nilai normalisasi dikali bobot kriteria.'],
['Y_i=\\sum_{j=2}^{6}V_{ij}-V_{i1}','Yi adalah jumlah manfaat C2 sampai C6 dikurangi biaya C1.'],
] as [$formula,$fallback])
<div class="formula" role="img" aria-label="{{ $fallback }}"><span data-katex="{{ $formula }}" data-katex-display="true">{{ $fallback }}</span></div>
@endforeach
<p><strong>Bobot snapshot:</strong> {{ implode(' | ',json_decode($run->weights,true)) }}. Urutan C1-C6: 0,10; 0,10; 0,20; 0,15; 0,20; 0,25.</p>
<p><strong>Pembagi snapshot:</strong> {{ implode(' | ',json_decode($run->divisors,true)) }}</p>
<p>Jika D1 = 0, N<sub>i1</sub> = 0. Jika D2 sampai D6 bernilai 0, perhitungan ditolak.</p>
<h3>Cara Perangkingan</h3>
<p>Yi diurutkan menurun tanpa pembulatan sebelum perbandingan. Nilai Yi yang sama memperoleh peringkat kompetisi yang sama, sehingga peringkat berikutnya melompati jumlah posisi yang terikat.</p>
</section>
<section aria-labelledby="criteria-title"><h2 id="criteria-title">Snapshot Peserta</h2><div class="table-wrap" tabindex="0" aria-label="Snapshot perhitungan peserta"><table><thead><tr><th>Peserta</th><th>Kriteria dan hasil tersimpan</th></tr></thead><tbody>@foreach($listing as $result)<tr><th>{{ $result->participant_name }}</th><td><p>C1-C6: {{ implode(' | ',json_decode($result->criteria,true)) }}</p><p>Normalisasi: {{ implode(' | ',json_decode($result->normalized,true)) }}</p><p>Terbobot: {{ implode(' | ',json_decode($result->weighted,true)) }}</p><p>Benefit: {{ $result->benefit_total }} | Cost: {{ $result->cost_total }} | Yi: {{ $result->yi }} | Peringkat: {{ $result->rank }}</p></td></tr>@endforeach</tbody></table></div></section>
@else<p class="card">Belum ada hasil tersimpan.</p>@endif
@elseif($page==='hasil')
<section class="card"><h2>Status hasil: {{ $run?->status ?? 'belum dihitung' }}</h2><p>Finalisasi mengunci seluruh input pada snapshot hasil saat ini.</p><div class="actions">@can('results.finalize')<form method="post" action="/hasil/{{ $event->id }}/finalize" data-confirm="Finalisasi akan mengunci semua input. Lanjutkan?">@csrf<button @disabled($run?->status!=='valid' || $event->finalized)>Finalisasi</button></form>@endcan</div>@if($event->finalized && auth()->user()->can('results.unlock'))<form method="post" action="/hasil/{{ $event->id }}/unlock">@csrf<label>Alasan koreksi<input name="reason" minlength="5" maxlength="500" required></label><button class="danger">Buka kunci</button></form>@endif</section>
@if($run)<section aria-labelledby="ranking-title"><h2 id="ranking-title">Peringkat MOORA</h2><p>Snapshot run #{{ $run->id }}, {{ $run->formula_version }}.</p>
<div class="table-wrap" tabindex="0" aria-label="Peringkat MOORA"><table><thead><tr><th>Ranking</th><th>Peserta</th><th>Yi</th><th>Nilai sertifikat</th><th>Predikat</th><th>Status</th><th>Detail</th></tr></thead><tbody>@foreach($listing as $result)<tr><td>{{ $result->rank }}</td><th>{{ $result->participant_name }}</th><td>{{ sprintf('%.10f',$result->yi) }}</td><td>{{ $result->certificate_score }}</td><td>{{ $result->certificate_predicate }}</td><td>{{ $result->certificate_status }}</td><td><details><summary>Lihat detail</summary><p>C1-C6: {{ implode(' | ',json_decode($result->criteria,true)) }}</p><p>Benefit: {{ $result->benefit_total }} | Cost: {{ $result->cost_total }}</p></details></td></tr>@endforeach</tbody></table></div></section>@else<p class="card">Belum ada hasil tersimpan. Jalankan perhitungan terlebih dahulu.</p>@endif
@endif
@if($page!=='panduan') @include('table-pagination') @endif
@endsection
