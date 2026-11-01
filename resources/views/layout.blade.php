<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $title ?? 'Penilaian' }} | LAKMUD</title>{{-- Panel operator dan halaman login bukan halaman publik: halaman ini tidak diindeks, dan pembatas aksesnya tetap autentikasi + izin pada route, bukan meta robots. --}}<meta name="robots" content="noindex, nofollow">@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body>
@auth
<a class="skip-link" href="#content">Lewati ke konten utama</a>
<div class="app-shell">
<aside id="sidebar" class="app-sidebar" data-sidebar aria-label="Navigasi utama" inert>
<div class="app-sidebar__brand"><span class="brand-initial" aria-hidden="true">L</span><span class="brand-label">LAKMUD</span></div>
<nav class="app-sidebar__nav" aria-label="Menu utama">
@can('events.view')<a class="app-nav-link {{ request()->is('dashboard') ? 'is-active' : '' }}" @if(request()->is('dashboard')) aria-current="page" @endif href="/dashboard"><md-icon aria-hidden="true">dashboard</md-icon><span class="label">Dashboard</span></a>@endcan
@if(auth()->user()->can('events.view') || auth()->user()->can('participants.view'))
<section class="app-nav-group" aria-labelledby="data-kegiatan-heading"><h2 class="app-nav-group__title" id="data-kegiatan-heading">Data kegiatan</h2>
@foreach(['kegiatan'=>'Kegiatan','materi'=>'Materi','peserta'=>'Peserta'] as $path=>$label)
@can(\App\Services\PanelPermissions::PAGES[$path])<a class="app-nav-link {{ request()->is($path) ? 'is-active' : '' }}" @if(request()->is($path)) aria-current="page" @endif href="/{{ $path }}{{ isset($event) && $event ? '?event_id='.$event->id : '' }}"><md-icon aria-hidden="true">{{ ['kegiatan'=>'event','materi'=>'menu_book','peserta'=>'groups'][$path] }}</md-icon><span class="label">{{ $label }}</span></a>@endcan
@endforeach
</section>@endif
@if(collect(['absensi','pretest','posttest','keaktifan','etika','pemahaman'])->contains(fn($path) => auth()->user()->can(\App\Services\PanelPermissions::PAGES[$path])))
<section class="app-nav-group" aria-labelledby="aspek-moora-heading"><h2 class="app-nav-group__title" id="aspek-moora-heading">ASPEK MOORA</h2>
@foreach(['absensi'=>'Absensi','pretest'=>'Pretest','posttest'=>'Posttest','keaktifan'=>'Keaktifan','etika'=>'Etika','pemahaman'=>'Pemahaman'] as $path=>$label)
@can(\App\Services\PanelPermissions::PAGES[$path])<a class="app-nav-link {{ request()->is($path) ? 'is-active' : '' }}" @if(request()->is($path)) aria-current="page" @endif href="/{{ $path }}{{ isset($event) && $event ? '?event_id='.$event->id : '' }}"><md-icon aria-hidden="true">{{ ['absensi'=>'how_to_reg','pretest'=>'quiz','posttest'=>'task_alt','keaktifan'=>'bolt','etika'=>'verified_user','pemahaman'=>'psychology'][$path] }}</md-icon><span class="label">{{ $label }}</span></a>@endcan
@endforeach
</section>@endif
@if(config('panel.show_results'))
@can('results.view')<section class="app-nav-group" aria-labelledby="hasil-heading"><h2 class="app-nav-group__title" id="hasil-heading">HASIL</h2>
@foreach(['perhitungan'=>'Perhitungan','hasil'=>'Hasil Perangkingan'] as $path=>$label)<a class="app-nav-link {{ request()->is($path) ? 'is-active' : '' }}" @if(request()->is($path)) aria-current="page" @endif href="/{{ $path }}{{ isset($event) && $event ? '?event_id='.$event->id : '' }}"><md-icon aria-hidden="true">{{ $path === 'perhitungan' ? 'calculate' : 'leaderboard' }}</md-icon><span class="label">{{ $label }}</span></a>@endforeach
</section>
@endcan
@endif
@can('criteria.view')<section class="app-nav-group" aria-labelledby="kriteria-heading"><h2 class="app-nav-group__title" id="kriteria-heading">PENGATURAN</h2>
<a class="app-nav-link {{ request()->is('kriteria') ? 'is-active' : '' }}" @if(request()->is('kriteria')) aria-current="page" @endif href="/kriteria"><md-icon aria-hidden="true">tune</md-icon><span class="label">Kriteria &amp; Bobot</span></a>
</section>@endcan
@can(\App\Services\PanelPermissions::ACTIVITY_VIEW)<a class="app-nav-link {{ request()->is('log') ? 'is-active' : '' }}" @if(request()->is('log')) aria-current="page" @endif href="/log"><md-icon aria-hidden="true">history</md-icon><span class="label">Log Aktivitas</span></a>@endcan
</nav>
<div class="app-sidebar__footer">{{ auth()->user()->getRoleNames()->implode(', ') ?: 'Pengguna' }}<br>Formula moora-v1</div>
</aside><button id="backdrop" class="app-backdrop" data-sidebar-backdrop tabindex="-1" aria-label="Tutup menu" hidden></button>
<div class="app-main"><header class="app-topbar">
<button id="menu" class="app-icon-btn" data-sidebar-toggle type="button" aria-controls="sidebar" aria-expanded="true" aria-label="Menu"><md-icon aria-hidden="true">menu</md-icon></button>
<p class="app-topbar__title">Penilaian LAKMUD</p><span class="app-topbar__spacer"></span>
<form action="/logout" method="post">@csrf<button class="lak-btn lak-btn--outlined lak-btn--sm"><md-icon aria-hidden="true">logout</md-icon>Keluar</button></form>
</header>
<main id="content" class="app-content">@yield('content')</main></div></div>
@else
<div class="auth-split">
<aside class="auth-identity">
<div class="auth-identity__inner">
<p class="auth-brand"><md-icon aria-hidden="true">school</md-icon> LAKMUD</p>
<h2>Panel penilaian kegiatan</h2>
<p class="auth-identity__tag">Penilaian 15 materi, enam aspek MOORA, satu peringkat akhir.</p>
<ul>
<li><md-icon aria-hidden="true">checklist</md-icon> Absensi, pretest, posttest, keaktifan, etika, dan pemahaman</li>
<li><md-icon aria-hidden="true">functions</md-icon> Perhitungan MOORA moora-v1 dengan snapshot tersimpan</li>
<li><md-icon aria-hidden="true">workspace_premium</md-icon> Nilai sertifikat dan predikat per peserta</li>
</ul>
</div>
<p class="auth-identity__foot">LAKMUD · Penilaian IPNU IPPNU</p>
</aside>
<main id="content" class="auth-wrap">@yield('content')</main>
</div>
@endauth
</body></html>
