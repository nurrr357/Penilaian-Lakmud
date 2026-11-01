@extends('layout')
@section('content')
@php
    // Nama dan jenis kriteria sengaja ditulis di sini, bukan diambil dari tabel: enam kriteria
    // ini adalah definisi skripsi dan tidak dapat diubah dari panel. Yang bisa diubah hanya
    // angka bobotnya.
    $kriteria = [
        ['kode' => 'C1', 'nama' => 'Ketidakhadiran', 'jenis' => 'Cost', 'sumber' => 'Jumlah materi dengan absensi tidak hadir, maksimal 15.'],
        ['kode' => 'C2', 'nama' => 'Pretest', 'jenis' => 'Benefit', 'sumber' => 'Jumlah nilai pretest 15 materi, masing-masing 10 sampai 20.'],
        ['kode' => 'C3', 'nama' => 'Keaktifan', 'jenis' => 'Benefit', 'sumber' => 'Jumlah nilai keaktifan 15 materi, masing-masing 10 sampai 20.'],
        ['kode' => 'C4', 'nama' => 'Etika', 'jenis' => 'Benefit', 'sumber' => 'Jumlah nilai etika 15 materi, masing-masing 10 sampai 20.'],
        ['kode' => 'C5', 'nama' => 'Pemahaman', 'jenis' => 'Benefit', 'sumber' => 'Jumlah nilai pemahaman 15 materi, masing-masing 10 sampai 20.'],
        ['kode' => 'C6', 'nama' => 'Posttest', 'jenis' => 'Benefit', 'sumber' => 'Jumlah nilai posttest 15 materi, masing-masing 10 sampai 20.'],
    ];
    $jumlah = array_sum($bobot);
    $bacaSaja = $terkunci !== null || ! auth()->user()->can('criteria.manage');
@endphp
<div class="page-header"><div><h1>Kriteria &amp; Bobot</h1><p class="subtitle">Bobot berlaku untuk semua kegiatan, bukan per kegiatan.</p></div></div>
@if(session('success'))<div class="lak-alert lak-alert--success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="lak-alert lak-alert--error" role="alert"><p class="lak-alert__head">Bobot belum disimpan</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

@if($terkunci)
<section class="lak-card"><h2 class="lak-card__title">Bobot sedang terkunci</h2><p>{{ $terkunci }}</p><p>Bobot di bawah tetap bisa dibaca, tetapi form dinonaktifkan sampai hasilnya menjadi arsip.</p></section>
@endif

<section class="lak-card" aria-labelledby="bobot-title">
<h2 class="lak-card__title" id="bobot-title">Bobot kriteria MOORA</h2>
<p class="subtitle">Jumlah C1 sampai C6 harus tepat 1,00. Setiap kriteria harus lebih besar dari nol.</p>
<form method="post" action="/kriteria" class="lak-form">
@csrf
<div class="table-wrap" tabindex="0" role="region" aria-label="Bobot enam kriteria MOORA">
<table class="lak-table">
<thead><tr><th scope="col">Kode</th><th scope="col">Kriteria</th><th scope="col">Jenis</th><th scope="col">Sumber nilai</th><th scope="col" class="t-right">Bobot</th></tr></thead>
<tbody>
@foreach($kriteria as $index => $baris)
<tr>
<th scope="row">{{ $baris['kode'] }}</th>
<td>{{ $baris['nama'] }}</td>
<td>{{ $baris['jenis'] }}</td>
<td>{{ $baris['sumber'] }}</td>
<td class="t-right">
<input id="weights-{{ $index }}" class="lak-input" type="number" name="weights[{{ $index }}]" aria-label="Bobot {{ $baris['kode'] }} {{ $baris['nama'] }}" value="{{ old('weights.'.$index, $bobot[$index]) }}" min="0.0001" max="1" step="any" required @readonly($bacaSaja) @disabled($bacaSaja)>
</td>
</tr>
@endforeach
</tbody>
</table>
</div>
<div class="lak-form__actions lak-form__actions--split">
<p class="lak-form__total">Jumlah bobot saat ini <strong>{{ number_format($jumlah, 2, ',', '.') }}</strong></p>
@can('criteria.manage')
<button type="submit" class="lak-btn lak-btn--filled" @disabled($terkunci !== null)><md-icon aria-hidden="true">save</md-icon>Simpan bobot</button>
@endcan
</div>
@cannot('criteria.manage')
<div class="lak-field"><p class="hint">Akun Anda hanya dapat membaca bobot. Perubahan bobot memerlukan izin kriteria kelola.</p></div>
@endcannot
</form>
</section>
@endsection
