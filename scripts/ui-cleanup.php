<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'db_lakmud_penilaian') {
    throw new RuntimeException('Wrong database');
}
$eventIds = DB::table('lakmud_events')->whereIn('name', ['TEST UI TABLES fixture', 'TEST ASPEK MOORA BROWSER'])->pluck('id');
DB::table('lakmud_events')->whereIn('id', $eventIds)->delete();
$names = ['TEST peserta edited'];
for ($i = 1; $i <= 12; $i++) {
    $names[] = sprintf('TEST peserta %02d', $i);
    $names[] = sprintf('TEST Peserta %02d', $i);
}
$ids = DB::table('participants')->whereIn('full_name', $names)->whereNotIn('id', DB::table('event_participants')->select('participant_id'))->pluck('id');
DB::table('participants')->whereIn('id', $ids)->delete();
$user = User::where('email', 'ui-table-test@example.test')->first();
if ($user) {
    DB::table('sessions')->where('user_id', $user->id)->delete();
    $user->delete();
}
echo json_encode(['removed_test_events' => $eventIds->count(), 'removed_test_people' => $ids->count(), 'remaining_test_people' => DB::table('participants')->whereIn('id', $ids)->count(), 'remaining_test_events' => DB::table('lakmud_events')->whereIn('name', ['TEST UI TABLES fixture', 'TEST ASPEK MOORA BROWSER'])->count(), 'remaining_test_user' => User::where('email', 'ui-table-test@example.test')->count()]);
