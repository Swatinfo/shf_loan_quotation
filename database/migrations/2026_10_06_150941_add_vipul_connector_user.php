<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seed a connector account: "vipul connector" (vipulconnector@shfworld.com),
 * assigned the connector role. Default password `password` — should be changed
 * on first login. Idempotent (keyed by email).
 */
return new class extends Migration
{
    private const EMAIL = 'vipulconnector@shfworld.com';

    public function up(): void
    {
        $now = now();

        DB::table('users')->updateOrInsert(
            ['email' => self::EMAIL],
            [
                'name' => 'vipul connector',
                'is_active' => true,
                'password' => Hash::make('password'),
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $userId = DB::table('users')->where('email', self::EMAIL)->value('id');
        $roleId = DB::table('roles')->where('slug', 'connector')->value('id');

        if ($userId && $roleId) {
            DB::table('role_user')->insertOrIgnore([
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        $userId = DB::table('users')->where('email', self::EMAIL)->value('id');
        if ($userId) {
            DB::table('role_user')->where('user_id', $userId)->delete();
            DB::table('users')->where('id', $userId)->delete();
        }
    }
};
