<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateInitialSaoAccount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scms:create-initial-sao 
                            {--email=sao@admin.com : Email of the SAO account}
                            {--name=Student Affairs Office : Display name of the SAO account}
                            {--password= : Plaintext password to hash securely (prompts if omitted)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely create the initial Student Affairs Office (SAO) account';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) $this->option('email');
        $name = (string) $this->option('name');

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            if ($existingUser->role === 'sao') {
                $this->info("SAO account already exists with email [{$email}]. No duplicate created.");
                $this->table(
                    ['ID', 'Name', 'Email', 'Role', 'Status'],
                    [[$existingUser->id, $existingUser->name, $existingUser->email, $existingUser->role, 'Existing Account Retained']]
                );
                return self::SUCCESS;
            }

            $this->error("CONFLICT: Account with email [{$email}] already exists with role [{$existingUser->role}] (User ID: {$existingUser->id}).");
            $this->error("Automatic overwrite or role change was prevented. Resolve conflict manually.");
            return self::FAILURE;
        }

        $password = (string) $this->option('password');
        if (empty($password)) {
            $password = (string) $this->secret('Enter password for the new SAO account:');
        }

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters in length.');
            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'sao',
        ]);

        // Verify Sanctum token creation can succeed immediately
        $testToken = $user->createToken('initial-sao-test')->plainTextToken;
        $user->tokens()->delete();

        $this->info("Initial SAO account created successfully.");
        $this->table(
            ['ID', 'Name', 'Email', 'Role', 'Sanctum Auth Tested'],
            [[$user->id, $user->name, $user->email, $user->role, !empty($testToken) ? 'PASS' : 'FAIL']]
        );

        return self::SUCCESS;
    }
}
