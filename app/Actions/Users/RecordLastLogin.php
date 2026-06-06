<?php

namespace App\Actions\Users;

use Illuminate\Support\Facades\DB;
use Webkul\User\Models\User;

class RecordLastLogin
{
    public function __invoke(User|int $user): void
    {
        $userId = $user instanceof User ? $user->id : $user;

        DB::table('users')
            ->where('id', $userId)
            ->update(['last_login_at' => now()]);
    }
}
