<?php

use App\Http\Controllers\PanelController;
use App\Services\PanelPermissions;
use App\Services\PublicSitemap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

Route::get('/sitemap.xml', fn () => PublicSitemap::response())->name('sitemap');
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('login'))->name('login');
    Route::post('/login', function (Request $request) {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($data)) {
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi salah.']);
        }
        $request->session()->regenerate();

        return redirect()->intended('/');
    })->middleware('throttle:6,1');
});
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
})->middleware('auth');
Route::middleware('auth')->group(function () {
    foreach (['/' => 'dashboard', '/kegiatan' => 'kegiatan', '/materi' => 'materi', '/peserta' => 'peserta', '/absensi' => 'absensi', '/pretest' => 'pretest', '/posttest' => 'posttest', '/keaktifan' => 'keaktifan', '/etika' => 'etika', '/pemahaman' => 'pemahaman', '/perhitungan' => 'perhitungan', '/hasil' => 'hasil', '/panduan' => 'panduan'] as $url => $page) {
        Route::get($url, [PanelController::class, 'page'])->defaults('page', $page)->middleware('permission:'.PanelPermissions::PAGES[$page]);
    }
    Route::get('/logs', [PanelController::class, 'logs'])->middleware('permission:'.PanelPermissions::AUDIT_LOGS)->name('logs.index');
    Route::post('/kegiatan', [PanelController::class, 'event'])->middleware('permission:events.manage');
    Route::match(['put', 'delete'], '/kegiatan/{event}', [PanelController::class, 'event'])->whereNumber('event')->middleware('permission:events.manage');
    Route::post('/kegiatan/{event}/{type}', [PanelController::class, 'child'])->whereNumber('event')->whereIn('type', ['materi', 'peserta']);
    Route::match(['put', 'delete'], '/kegiatan/{event}/{type}/{record}', [PanelController::class, 'child'])->whereNumber(['event', 'record'])->whereIn('type', ['materi', 'peserta']);
    Route::post('/input/{event}/{kind}', [PanelController::class, 'input'])->whereNumber('event')->whereIn('kind', ['absensi', 'pretest', 'posttest', 'keaktifan', 'etika', 'pemahaman']);
    Route::post('/hasil/{event}/{action}', [PanelController::class, 'result'])->whereNumber('event')->whereIn('action', ['calculate', 'finalize', 'unlock']);
});
