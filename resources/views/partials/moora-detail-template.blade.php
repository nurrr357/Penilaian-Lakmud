@php
    // Isi modal detail perhitungan, dipakai tabel Snapshot Peserta (halaman Perhitungan)
    // dan tabel Peringkat MOORA (halaman Hasil). Dibagi jadi satu sumber agar kedua tabel
    // tidak pernah berbeda isi. Variabel yang wajib ada: $result, $run, $rumusHasil.
    $criteria = json_decode($result->criteria, true);
    $normal = json_decode($result->normalized, true);
    $weighted = json_decode($result->weighted, true);
    $divisors = json_decode($run->divisors, true);
    $weights = json_decode($run->weights, true);
    $namaKriteria = ['Absensi', 'Pretest', 'Keaktifan', 'Etika', 'Pemahaman', 'Posttest'];
@endphp
<p class="lak-modal__meta">{{ $result->participant_name }} · peringkat {{ $result->rank }} · Yi {{ sprintf('%.10f',$result->yi) }}</p>
<h3 class="lak-modal__subtitle">Hasil akhir</h3>
@foreach($rumusHasil as [$formula,$fallback])
<div class="formula" role="img" aria-label="{{ $fallback }}"><span data-katex="{{ $formula }}" data-katex-display="true">{{ $fallback }}</span></div>
@endforeach
<p>Benefit = <strong>{{ number_format((float)$result->benefit_total,8,',','.') }}</strong> · Cost = <strong>{{ number_format((float)$result->cost_total,8,',','.') }}</strong> · Yi = <strong>{{ number_format((float)$result->yi,10,',','.') }}</strong> · peringkat <strong>{{ $result->rank }}</strong>. Yi diurutkan menurun tanpa pembulatan sebelum perbandingan.</p>
<h3 class="lak-modal__subtitle">Substitusi angka per kriteria</h3>
<div class="table-wrap" role="region" aria-label="Substitusi angka per kriteria untuk {{ $result->participant_name }}" tabindex="0">
<table class="lak-table">
<thead><tr><th scope="col">Kriteria</th><th scope="col">Jenis</th><th scope="col" class="t-right">C<sub>ij</sub></th><th scope="col" class="t-right">D<sub>j</sub></th><th scope="col" class="t-right">N<sub>ij</sub></th><th scope="col" class="t-right">Bobot</th><th scope="col" class="t-right">V<sub>ij</sub></th></tr></thead>
<tbody>
@foreach($criteria as $i => $nilai)
<tr><th scope="row">C{{ $i+1 }}</th><td>{{ $i === 0 ? 'Cost' : 'Benefit' }} · {{ $namaKriteria[$i] }}</td><td class="t-right">{{ number_format((float)$nilai,2,',','.') }}</td><td class="t-right">{{ number_format((float)$divisors[$i],8,',','.') }}</td><td class="t-right">{{ number_format((float)$normal[$i],8,',','.') }}</td><td class="t-right">{{ number_format((float)$weights[$i]*100,0,',','.') }}%</td><td class="t-right">{{ number_format((float)$weighted[$i],8,',','.') }}</td></tr>
@endforeach
</tbody>
</table>
</div>
