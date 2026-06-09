<?php

namespace Tests\Feature\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

trait CreatesUsers
{
    protected function owner(string $email = 'owner@butik.test'): User
    {
        return User::query()->create([
            'name'     => 'Owner Butik',
            'email'    => $email,
            'password' => Hash::make('password'),
            'role'     => 'owner',
        ]);
    }

    protected function cashier(string $email = 'kasir@butik.test'): User
    {
        return User::query()->create([
            'name'     => 'Kasir Utama',
            'email'    => $email,
            'password' => Hash::make('password'),
            'role'     => 'cashier',
        ]);
    }
}
