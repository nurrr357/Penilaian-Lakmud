<?php

use App\Http\Controllers\PanelController;
use App\Services\PanelPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

// '/' bukan halaman: pengunjung yang belum masuk dikirim ke login, operator yang sudah
// masuk dikirim ke dashboard. Panel tetap dijaga autentikasi dan izin di grup route bawah.
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('login'))->name('login');
    Route::post('/login', function (Request $request) {
        $data = $request->validate(['email' => 'required|string', 'password' => 'required|string']);
        // Field "email" menerima email ATAU username; kolom ditentukan dari bentuk isinya.
        $field = filter_var($data['email'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        if (! Auth::attempt([$field => $data['email'], 'password' => $data['password']])) {
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi salah.']);
        }
        $request->session()->regenerate();
        // Jejak audit login: hanya identifier dan IP. Password/token tidak pernah dicatat.
        activity()->causedBy(Auth::user())->withProperties([
            'identifier' => $data['email'],
            'ip' => $request->ip(),
        ])->log('auth.login');

        // Dashboard, bukan '/': '/' sekarang halaman publik, jadi operator yang selesai
        // masuk harus diarahkan ke panelnya sendiri.
        return redirect()->intended('/dashboard');
    })->middleware('throttle:6,1');
});
Route::post('/logout', function (Request $request) {
    activity()->causedBy($request->user())->withProperties([
        'identifier' => $request->user()->username ?? $request->user()->email,
        'ip' => $request->ip(),
    ])->log('auth.logout');
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
})->middleware('auth');
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [PanelController::class, 'page'])
        ->defaults('page', 'dashboard')
        ->middleware('permission:'.PanelPermissions::PAGES['dashboard'])
        ->name('dashboard');
    Route::get('/log', [PanelController::class, 'activityLog'])->middleware('permission:'.PanelPermissions::ACTIVITY_VIEW)->name('activity.log');
    foreach (['/kegiatan' => 'kegiatan', '/materi' => 'materi', '/peserta' => 'peserta', '/absensi' => 'absensi', '/pretest' => 'pretest', '/posttest' => 'posttest', '/keaktifan' => 'keaktifan', '/etika' => 'etika', '/pemahaman' => 'pemahaman', '/perhitungan' => 'perhitungan', '/hasil' => 'hasil'] as $url => $page) {
        Route::get($url, [PanelController::class, 'page'])->defaults('page', $page)->middleware('permission:'.PanelPermissions::PAGES[$page]);
    }
    Route::get('/kriteria', [PanelController::class, 'criteria'])->middleware('permission:'.PanelPermissions::WEIGHTS_VIEW);
    Route::post('/kriteria', [PanelController::class, 'saveWeights'])->middleware('permission:'.PanelPermissions::WEIGHTS_MANAGE);
    Route::post('/kegiatan', [PanelController::class, 'event'])->middleware('permission:events.manage');
    Route::match(['put', 'delete'], '/kegiatan/{event}', [PanelController::class, 'event'])->whereNumber('event')->middleware('permission:events.manage');
    Route::post('/kegiatan/{event}/{type}', [PanelController::class, 'child'])->whereNumber('event')->whereIn('type', ['materi', 'peserta']);
    Route::match(['put', 'delete'], '/kegiatan/{event}/{type}/{record}', [PanelController::class, 'child'])->whereNumber(['event', 'record'])->whereIn('type', ['materi', 'peserta']);
    Route::post('/input/{event}/{kind}', [PanelController::class, 'input'])->whereNumber('event')->whereIn('kind', ['absensi', 'pretest', 'posttest', 'keaktifan', 'etika', 'pemahaman']);
    Route::post('/hasil/{event}/{action}', [PanelController::class, 'result'])->whereNumber('event')->whereIn('action', ['calculate', 'finalize', 'unlock']);
});
