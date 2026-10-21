@extends('layout', ['title'=>'Masuk'])
@section('content')
<div class="lak-card auth-card">
<p class="auth-brand"><md-icon aria-hidden="true">school</md-icon> LAKMUD</p>
<h1 class="auth-card__title">Masuk penilaian</h1>
<p class="subtitle">Gunakan akun operator untuk mengelola penilaian kegiatan.</p>
@if($errors->any())<div role="alert" tabindex="-1" class="lak-alert lak-alert--error"><p class="lak-alert__head">Tidak dapat masuk.</p>{{ $errors->first() }}</div>@endif
<form action="/login" method="post" class="auth-form">@csrf
<div class="lak-field"><label for="email">Email</label><input class="lak-input" id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></div>
<div class="lak-field"><label for="password">Kata sandi</label><input class="lak-input" id="password" type="password" name="password" autocomplete="current-password" required></div>
<button type="submit" class="lak-btn lak-btn--filled"><md-icon aria-hidden="true">login</md-icon>Masuk</button>
</form>
</div>
@endsection
