<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $title ?? 'Penilaian' }} | LAKMUD</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body><a class="skip" href="#content">Lewati navigasi</a>
@auth
<div class="shell"><aside id="sidebar" class="sidebar"><div class="brand">LAKMUD <small>Panel penilaian</small></div><nav aria-label="Menu utama">
@can('events.view')<a class="nav-link {{ request()->is('/') ? 'active' : '' }}" href="/">Dashboard</a>@endcan
@if(auth()->user()->can('events.view') || auth()->user()->can('participants.view'))
<section class="nav-group" aria-labelledby="data-kegiatan-heading"><h2 id="data-kegiatan-heading">Data kegiatan</h2>
@foreach(['kegiatan'=>'Kegiatan','materi'=>'Materi','peserta'=>'Peserta'] as $path=>$label)
@can(\App\Services\PanelPermissions::PAGES[$path])<a class="nav-link {{ request()->is($path) ? 'active' : '' }}" @if(request()->is($path)) aria-current="page" @endif href="/{{ $path }}{{ isset($event) && $event ? '?event_id='.$event->id : '' }}">{{ $label }}</a>@endcan
@endforeach
</section>@endif
@if(collect(['absensi','pretest','posttest','keaktifan','etika','pemahaman'])->contains(fn($path) => auth()->user()->can(\App\Services\PanelPermissions::PAGES[$path])))
<section class="nav-group" aria-labelledby="aspek-moora-heading"><h2 id="aspek-moora-heading">ASPEK MOORA</h2>
@foreach(['absensi'=>'Absensi','pretest'=>'Pretest','posttest'=>'Posttest','keaktifan'=>'Keaktifan','etika'=>'Etika','pemahaman'=>'Pemahaman'] as $path=>$label)
@can(\App\Services\PanelPermissions::PAGES[$path])<a class="nav-link {{ request()->is($path) ? 'active' : '' }}" @if(request()->is($path)) aria-current="page" @endif href="/{{ $path }}{{ isset($event) && $event ? '?event_id='.$event->id : '' }}">{{ $label }}</a>@endcan
@endforeach
</section>@endif
@can('results.view')<section class="nav-group" aria-labelledby="hasil-heading"><h2 id="hasil-heading">HASIL</h2>
@foreach(['perhitungan'=>'Perhitungan','hasil'=>'Hasil Perangkingan'] as $path=>$label)<a class="nav-link {{ request()->is($path) ? 'active' : '' }}" @if(request()->is($path)) aria-current="page" @endif href="/{{ $path }}{{ isset($event) && $event ? '?event_id='.$event->id : '' }}">{{ $label }}</a>@endforeach
</section>@endcan
@can('events.view')<a class="nav-link {{ request()->is('panduan') ? 'active' : '' }}" @if(request()->is('panduan')) aria-current="page" @endif href="/panduan{{ isset($event) && $event ? '?event_id='.$event->id : '' }}">Panduan singkat</a>@endcan

</nav><p class="sidebar-note">Operator penilaian<br>Formula moora-v1</p></aside><button id="backdrop" class="backdrop" aria-label="Tutup menu" hidden></button><div class="main"><header class="topbar"><button type="button" id="menu" aria-controls="sidebar" aria-expanded="true">Menu</button><strong>Penilaian LAKMUD</strong><form action="/logout" method="post">@csrf<button class="secondary">Keluar</button></form></header>
<main id="content">@yield('content')</main></div></div>
@else<main id="content" class="login">@yield('content')</main>@endauth
</body></html>
