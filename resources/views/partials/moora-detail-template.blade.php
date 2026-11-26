@php
    // Isi modal detail perhitungan, dipakai tabel Snapshot Peserta (halaman Perhitungan)
    // dan tabel Peringkat MOORA (halaman Hasil). Dibagi jadi satu sumber agar kedua tabel
    // tidak pernah berbeda isi. Variabel yang wajib ada: $result, $run, $rumusHasil.
    $criteria = json_decode($result->criteria, true);
    $normal = json_decode($result->normalized, true);
    $weighted = json_decode($result->weighted, true);
    $divisors = json_decode($run->divisors, true);
    $weights = json_decode($run->weights, true);
    $namaKriteria = ['Ketidakhadiran', 'Pretest', 'Keaktifan', 'Etika', 'Pemahaman', 'Posttest'];
@endphp
<p class="lak-modal__meta">{{ $result->participant_name }} · Ranking {{ $result->rank }} · Y_i = {{ number_format((float)$result->yi,10,',','.') }}</p>
<h3 class="lak-modal__subtitle">Nilai Optimasi <span data-katex="Y_i">Y_i</span></h3>
@foreach($rumusHasil as [$formula,$fallback])
<div class="formula" role="img" aria-label="{{ $fallback }}"><span data-katex="{{ $formula }}" data-katex-display="true">{{ $fallback }}</span></div>
@endforeach
<p><span data-katex="Y_i^{+}">Y_i^+</span> = <strong>{{ number_format((float)$result->benefit_total,8,',','.') }}</strong> · <span data-katex="Y_i^{-}">Y_i^-</span> = <strong>{{ number_format((float)$result->cost_total,8,',','.') }}</strong> · <span data-katex="Y_i">Y_i</span> = <strong>{{ number_format((float)$result->yi,10,',','.') }}</strong> · Ranking <strong>{{ $result->rank }}</strong>. Nilai Y_i diurutkan menurun tanpa pembulatan sebelum perbandingan.</p>
<p class="hint"><strong>Keterangan:</strong> <span data-katex="x_{ij}">x_ij</span> = nilai awal kriteria ke-j pada alternatif ke-i; <span data-katex="D_j">D_j</span> = akar jumlah kuadrat seluruh nilai kriteria ke-j; <span data-katex="x_{ij}^{*}">x*_ij</span> = nilai normalisasi; <span data-katex="w_j">w_j</span> = bobot kriteria ke-j; <span data-katex="v_{ij}">v_ij</span> = nilai terbobot; <span data-katex="Y_i^{+}">Y_i^+</span> = total nilai benefit; <span data-katex="Y_i^{-}">Y_i^-</span> = total nilai cost; <span data-katex="Y_i">Y_i</span> = nilai optimasi akhir. Pada sistem ini C1 adalah cost dan C2–C6 adalah benefit.</p>
<h3 class="lak-modal__subtitle">Substitusi Nilai <span data-katex="x_{ij}">x_ij</span>, <span data-katex="D_j">D_j</span>, <span data-katex="x_{ij}^{*}">x*_ij</span>, dan <span data-katex="v_{ij}">v_ij</span> per Kriteria</h3>
<div class="table-wrap" role="region" aria-label="Substitusi nilai x_ij, D_j, x*_ij, dan v_ij per kriteria untuk {{ $result->participant_name }}" tabindex="0">
<table class="lak-table">
<thead><tr><th scope="col">Kriteria (j)</th><th scope="col">Jenis Atribut</th><th scope="col" class="t-right"><span data-katex="x_{ij}">x_ij</span></th><th scope="col" class="t-right"><span data-katex="D_j">D_j</span></th><th scope="col" class="t-right"><span data-katex="x_{ij}^{*}">x*_ij</span></th><th scope="col" class="t-right"><span data-katex="w_j">w_j</span></th><th scope="col" class="t-right"><span data-katex="v_{ij}">v_ij</span></th></tr></thead>
<tbody>
@foreach($criteria as $i => $nilai)
<tr><th scope="row">C{{ $i+1 }}</th><td>{{ $i === 0 ? 'Cost' : 'Benefit' }} · {{ $namaKriteria[$i] }}</td><td class="t-right">{{ number_format((float)$nilai,2,',','.') }}</td><td class="t-right">{{ number_format((float)$divisors[$i],8,',','.') }}</td><td class="t-right">{{ number_format((float)$normal[$i],8,',','.') }}</td><td class="t-right">{{ number_format((float)$weights[$i]*100,0,',','.') }}%</td><td class="t-right">{{ number_format((float)$weighted[$i],8,',','.') }}</td></tr>
@endforeach
</tbody>
</table>
</div>
