<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'db_lakmud_penilaian') {
    throw new RuntimeException('Wrong database');
}
$user = User::firstOrCreate(['email' => 'ui-table-test@example.test'], ['name' => 'TEST UI tables', 'password' => Hash::make(bin2hex(random_bytes(32)))]);
$user->assignRole('operator_penilaian');
$session = app('session')->driver();
$session->start();
Auth::login($user);
$session->save();
$name = config('session.cookie');
$prefix = CookieValuePrefix::create($name, app('encrypter')->getKey());
echo json_encode(['name' => $name, 'value' => app('encrypter')->encrypt($prefix.$session->getId(), false), 'url' => 'http://127.0.0.1:8013', 'user' => $user->id]);
