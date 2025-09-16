<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateApiToken extends Command
{
    protected $signature = 'api:token {email} {password} {--name= : Token name}';
    protected $description = 'Create an API token for a user';

    public function handle()
    {
        $email = $this->argument('email');
        $password = $this->argument('password');
        $tokenName = $this->option('name') ?? 'api-token';

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            $this->error('Invalid credentials');
            return 1;
        }

        $token = $user->createToken($tokenName)->plainTextToken;

        $this->info("API Token created successfully:");
        $this->line($token);

        return 0;
    }
}