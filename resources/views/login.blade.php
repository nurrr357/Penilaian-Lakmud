@extends('layout', ['title'=>'Masuk'])
@section('content')
<section class="card"><p class="brand">LAKMUD</p><h1>Masuk penilaian</h1><p>Gunakan akun operator untuk mengelola penilaian kegiatan.</p>
@if($errors->any())<div role="alert" class="error">{{ $errors->first() }}</div>@endif
<form action="/login" method="post">@csrf<label>Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></label><label>Kata sandi<input type="password" name="password" autocomplete="current-password" required></label><button>Masuk</button></form></section>
@endsection
