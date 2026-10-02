<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Production bootstrap: create the first super admin without demo data. */
class CreateSuperAdmin extends Command
{
    protected $signature = 'app:create-super-admin {email} {--name=System Administrator}';

    protected $description = 'Create (or promote) a super admin account';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $password = (string) $this->secret('Password (min 8 characters, letters and numbers)');

        $validator = Validator::make(['email' => $email, 'password' => $password], [
            'email' => ['required', 'email'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $role = Role::where('slug', Role::SUPER_ADMIN)->first();
        if (! $role) {
            $this->error('Roles are missing. Run: php artisan db:seed --class=RolePermissionSeeder');

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->fill(['name' => $user->name ?? $this->option('name'), 'password' => $password, 'status' => 'active']);
        $user->role_id = $role->id;
        $user->school_id = null;
        $user->save();

        $this->info("Super admin {$email} is ready.");

        return self::SUCCESS;
    }
}
