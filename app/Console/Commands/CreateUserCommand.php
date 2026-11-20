<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('dojo:user {email} {--name= : Display name} {--password= : Password (omit to be prompted)}')]
#[Description('Create a learner account, or reset the password of an existing one')]
class CreateUserCommand extends Command
{
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $password = $this->option('password') ?? $this->secret('Password (min. 12 karakter)');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => ['required', 'string', 'min:12']],
        );

        if ($validator->fails()) {
            $this->components->bulletList($validator->errors()->all());

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $isNew = ! $user->exists;
        $user->name = $this->option('name') ?? $user->name ?? strstr($email, '@', true);
        $user->password = $password;
        $user->save();

        $this->components->info($isNew ? "User {$email} dibuat." : "Password {$email} diperbarui.");

        return self::SUCCESS;
    }
}
