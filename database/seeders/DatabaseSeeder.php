<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\PanelPermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = PanelPermissions::ALL;
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $role = Role::findOrCreate('operator_penilaian', 'web');
        $role->syncPermissions($permissions);
        $user = User::firstOrCreate(['email' => 'operator@example.test'], ['name' => 'Operator Penilaian', 'password' => env('DEMO_PASSWORD') ?: Str::random(40)]);
        $user->syncRoles([$role]);
    }
}
