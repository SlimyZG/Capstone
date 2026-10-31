<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class TransitionAdminToSao extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scms:transition-admin-to-sao 
                            {--email= : The email of the admin account to convert to SAO}
                            {--all : Convert all existing admin accounts to SAO}
                            {--dry-run : Preview accounts that would be transitioned without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely transition existing administrator accounts to the SAO role';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = User::where('role', 'admin');

        if ($email = $this->option('email')) {
            $query->where('email', $email);
        }

        $admins = $query->get();

        if ($admins->isEmpty()) {
            $this->info('No matching administrator accounts found.');
            return self::SUCCESS;
        }

        $this->info("Found {$admins->count()} administrator account(s):");
        $headers = ['ID', 'Name', 'Email', 'Current Role'];
        $rows = $admins->map(fn ($u) => [$u->id, $u->name, $u->email, $u->role])->toArray();
        $this->table($headers, $rows);

        // If dry-run or no explicit conversion flag passed, report and exit safely
        if ($this->option('dry-run') || (!$this->option('email') && !$this->option('all'))) {
            $this->warn('DRY-RUN / PREVIEW MODE: No database changes were made.');
            $this->line('To safely execute the transition for a specific account, run:');
            $this->line('  php artisan scms:transition-admin-to-sao --email=<account_email>');
            $this->line('To transition all admin accounts, run:');
            $this->line('  php artisan scms:transition-admin-to-sao --all');
            return self::SUCCESS;
        }

        if (!$this->confirm('Are you sure you want to transition these account(s) to the SAO role?')) {
            $this->info('Transition canceled by user.');
            return self::SUCCESS;
        }

        foreach ($admins as $admin) {
            $admin->update(['role' => 'sao']);
            $this->info("Successfully converted {$admin->email} (ID {$admin->id}) to role: sao.");
        }

        return self::SUCCESS;
    }
}
