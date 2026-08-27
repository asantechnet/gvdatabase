<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class CheckSuperAdmin extends Command
{
    protected $signature = 'check:superadmin';
    protected $description = 'Check if super admin user exists';

    public function handle()
    {
        $user = User::where('email', 'superadmin@example.com')->first();

        if (!$user) {
            $this->error('Super admin user not found!');
            return 1;
        }

        $this->info('Super admin user found:');
        $this->table(['Field', 'Value'], [
            ['ID', $user->id],
            ['Name', $user->name],
            ['Email', $user->email],
            ['Password Hash', $user->password],
            ['Account Status', $user->acc_status],
            ['Roles', implode(', ', $user->roleNames())],
            ['Created At', $user->created_at],
        ]);

        return 0;
    }
}
