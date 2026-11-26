@extends('layout')
@section('content')
@php
    $meta = $data['meta'];
    $decimal = fn (float $value, int $places): string => number_format($value, $places, ',', '.');
    $trimmed = function (float $value, int $places) use ($decimal): string {
        $text = rtrim(rtrim($decimal($value, $places), '0'), ',');

        return $text === '-0' ? '0' : $text;
    };
    $rank = fn (float $value): string => $trimmed($value, 1);
    $scientific = fn (float $value): string => str_replace('.', ',', sprintf('%.2e', $value));
    $imageStyle = 'display:block;max-width:100%;height:auto;margin-top:12px';
@endphp
<div class="page-header"><div><h1>Evaluasi Spearman</h1><p class="subtitle">Template 5 Aspek vs MOORA 6 Kriteria</p></div></div>

<div class="lak-metric-grid">
<section class="lak-card lak-metric">
<p class="lak-metric__label">Jumlah peserta</p>
<p class="lak-metric__value">{{ number_format((int) $meta['n'], 0, ',', '.') }}</p>
<p class="lak-metric__note">pasangan peringkat</p>
</section>
<section class="lak-card lak-metric">
<p class="lak-metric__label">Koefisien Spearman</p>
<p class="lak-metric__value">{{ $decimal((float) $meta['rho'], 4) }}</p>
<p class="lak-metric__note">skala -1 sampai 1</p>
</section>
<section class="lak-card lak-metric">
<p class="lak-metric__label">p-value</p>
<p class="lak-metric__value">{{ $scientific((float) $meta['p_value']) }}</p>
<p class="lak-metric__note">hubungan tidak nol</p>
</section>
<section class="lak-card lak-metric">
<p class="lak-metric__label">95% CI</p>
<p class="lak-metric__value">{{ $decimal((float) $meta['ci_low'], 4) }} - {{ $decimal((float) $meta['ci_high'], 4) }}</p>
<p class="lak-metric__note">batas bawah dan batas atas (Fisher z)</p>
</section>
<section class="lak-card lak-metric">
<p class="lak-metric__label">rho kuadrat</p>
<p class="lak-metric__value">{{ $decimal((float) $meta['rho_squared'], 2) }}</p>
<p class="lak-metric__note">{{ $decimal((float) $meta['rho_squared'] * 100, 2) }}% variasi peringkat</p>
</section>
</div>

<div class="lak-alert lak-alert--info" role="status">
<p>Hubungan peringkat lemah dan positif: rho {{ $decimal((float) $meta['rho'], 4) }}. p-value sangat kecil karena n={{ number_format((int) $meta['n'], 0, ',', '.') }}, jadi hubungan tidak nol, bukan hubungan kuat. Spearman mengukur kesamaan urutan, bukan akurasi nilai.</p>
</div>

<section class="lak-card" aria-labelledby="spearman-scatter-title">
<h2 class="lak-card__title" id="spearman-scatter-title">Sebaran peringkat</h2>
<p class="subtitle">Rank Template 5 Aspek dibanding Rank MOORA 6 Kriteria. Garis putus-putus menandai peringkat identik.</p>
<img src="/images/spearman-scatter.png" width="1580" height="1274" alt="Diagram sebar Rank Template 5 Aspek dan Rank MOORA 6 Kriteria untuk 702 peserta. Garis diagonal putus-putus menandai peringkat yang identik." loading="lazy" style="{{ $imageStyle }}">
</section>

<section class="lak-card" aria-labelledby="spearman-histogram-title">
<h2 class="lak-card__title" id="spearman-histogram-title">Distribusi selisih peringkat</h2>
<p class="subtitle">Selisih absolut Rank Template 5 Aspek dan Rank MOORA 6 Kriteria, beserta rata-rata dan median.</p>
<img src="/images/spearman-histogram.png" width="1580" height="1077" alt="Histogram selisih absolut peringkat Template 5 Aspek dan MOORA 6 Kriteria untuk 702 peserta. Garis rata-rata dan median ditandai pada diagram." loading="lazy" style="{{ $imageStyle }}">
</section>

<p class="muted">Pencarian mencakup nama dan nomor sertifikat.</p>
@include('table-filter')
@if($listing->total() === 0)
<div class="lak-alert lak-alert--info" role="status">Tidak ada data sesuai pencarian. Ubah kata pencarian.</div>
@else
<section class="lak-card" aria-labelledby="spearman-table-title">
<div class="lak-card__head">
<h2 class="lak-card__title" id="spearman-table-title">Rincian peringkat per peserta</h2>
<p class="subtitle">{{ number_format($listing->total(), 0, ',', '.') }} baris</p>
</div>
<div class="table-wrap" tabindex="0" role="region" aria-label="Rincian peringkat Spearman per peserta">
<table class="lak-table">
<caption class="visually-hidden">Nilai Template, rank Template, Yi MOORA, rank MOORA, selisih peringkat, dan metode pencocokan per peserta</caption>
<thead><tr><th scope="col">No</th><th scope="col">Nama</th><th scope="col">No Sertifikat</th><th scope="col">Nilai Template</th><th scope="col">Rank Template</th><th scope="col">Yi MOORA</th><th scope="col">Rank MOORA</th><th scope="col">d</th><th scope="col">d kuadrat</th><th scope="col">Metode</th></tr></thead>
<tbody>
@foreach($listing as $row)
<tr>
<td>{{ number_format((int) $row['no'], 0, ',', '.') }}</td>
<th scope="row">{{ $row['name'] }}</th>
<td>{{ $row['certificate'] }}</td>
<td>{{ $decimal((float) $row['template_value'], 6) }}</td>
<td>{{ $rank((float) $row['rank_template']) }}</td>
<td>{{ $decimal((float) $row['yi_moora'], 8) }}</td>
<td>{{ $rank((float) $row['rank_moora']) }}</td>
<td>{{ $trimmed((float) $row['d'], 2) }}</td>
<td>{{ $trimmed((float) $row['d2'], 2) }}</td>
<td>{{ $row['match_method'] }}</td>
</tr>
@endforeach
</tbody>
</table>
</div>
</section>
@endif
@include('table-pagination')

<section class="lak-card" aria-labelledby="spearman-method-title">
<h2 class="lak-card__title" id="spearman-method-title">Metode</h2>
<dl class="lak-dl">
@foreach($data['method'] as $item)
<div>
<dt>{{ $item['term'] }}</dt>
<dd>{{ $item['description'] }}</dd>
</div>
@endforeach
</dl>
<h3>Sumber data</h3>
<ul>
@foreach($data['sources'] as $source)
<li style="overflow-wrap:anywhere">{{ $source['workbook'] }} ({{ $source['sheet'] }}): {{ $source['description'] }}. Jumlah {{ $source['count'] }}.</li>
@endforeach
</ul>
</section>

<section class="lak-card" aria-labelledby="spearman-audit-title">
<h2 class="lak-card__title" id="spearman-audit-title">Audit pencocokan data</h2>
<p class="subtitle">Pencocokan baris antara workbook Template 5 Aspek dan workbook MOORA 6 Kriteria.</p>
<div class="table-wrap" tabindex="0" role="region" aria-label="Jumlah pencocokan data per metode">
<table class="lak-table">
<caption class="visually-hidden">Jumlah baris yang cocok per metode pencocokan</caption>
<thead><tr><th scope="col">No</th><th scope="col">Metode</th><th scope="col">Jumlah</th><th scope="col">Keterangan</th></tr></thead>
<tbody>
@forelse($data['match_audit'] as $audit)
<tr>
<td>{{ $loop->iteration }}</td>
<th scope="row">{{ $audit['method'] }}</th>
<td>{{ number_format((int) $audit['count'], 0, ',', '.') }}</td>
<td>{{ $audit['description'] ?? '-' }}</td>
</tr>
@empty
<tr><td colspan="4">Tidak ada data audit pencocokan.</td></tr>
@endforelse
</tbody>
</table>
</div>
<h3>Nama berulang</h3>
<p class="subtitle">{{ count($data['repeated_matches']) }} pasangan nama yang sama dibedakan lewat nomor sertifikat atau nomor HP.</p>
<div class="table-wrap" tabindex="0" role="region" aria-label="Nama berulang dan nomor pembeda">
<table class="lak-table">
<caption class="visually-hidden">Nama berulang dengan nomor LAKMUD, nomor MOORA, dan metode pencocokan</caption>
<thead><tr><th scope="col">No LAKMUD</th><th scope="col">Nama</th><th scope="col">No MOORA</th><th scope="col">Metode</th></tr></thead>
<tbody>
@forelse($data['repeated_matches'] as $match)
<tr>
<td>{{ number_format((int) $match['no_lakmud'], 0, ',', '.') }}</td>
<th scope="row">{{ $match['name'] }}</th>
<td>{{ number_format((int) $match['no_moora'], 0, ',', '.') }}</td>
<td>{{ $match['method'] }}</td>
</tr>
@empty
<tr><td colspan="4">Tidak ada nama berulang.</td></tr>
@endforelse
</tbody>
</table>
</div>
</section>

<section class="lak-card" aria-labelledby="spearman-conclusion-title">
<h2 class="lak-card__title" id="spearman-conclusion-title">Kesimpulan</h2>
<ul>
@foreach($data['conclusion'] as $paragraph)
<li>{{ $paragraph }}</li>
@endforeach
</ul>
</section>
@endsection
