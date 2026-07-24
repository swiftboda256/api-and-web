<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

#[Signature('app:deploy {--branch= : The git branch to pull from}')]
#[Description('Deploy application')]
class Deploy extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('🚀 Deployment started ...');

        // Step 1: Enter maintenance mode
        $this->info('🛠️ Entering maintenance mode ...');
        try {
            Process::run('php artisan down --render="errors::503"');
        } catch (\Throwable $e) {
            $this->warn('Maintenance mode failed or already active. Continuing...');
        }

        // Step 2: Pull the latest code
        $branch = $this->option('branch');
        $this->info("📥 Pulling from $branch branch ...");
        $this->runShellCommand("git pull origin $branch");

        // Step 3: Install Composer dependencies
        $this->info('📦 Installing composer dependencies ...');
        $this->runShellCommand('composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader', 300);

        // Step 4: Install npm dependencies and build assets
        //        $this->info('📦 Installing npm dependencies ...');
        //        $this->runShellCommand('npm ci --verbose', 200);

        //        $this->info('🏗️ Building frontend assets ...');
        //        $this->runShellCommand('npm run build --verbose', 200);

        // Step 5: Optimize application
        $this->info('⚙️ Optimizing cache ...');
        //        $this->runShellCommand('php artisan filament:optimize');
        $this->runShellCommand('php artisan optimize');

        // Step 6: Run migrations
        $this->info('🧬 Running migrations ...');
        $this->runShellCommand('php artisan migrate --force');

        // Step 7: Restart queue workers and Reverb so they pick up the new code
        //        $this->info('🔄 Restarting queue workers and Reverb ...');
        //        $this->runShellCommand('php artisan queue:restart');
        //        $this->runShellCommand('php artisan reverb:restart');

        // Step 8: Exit maintenance mode
        $this->info('✅ Exiting maintenance mode ...');
        $this->runShellCommand('php artisan up');

        $this->info('🎉 Deployment finished!');
    }

    protected function runShellCommand(string $command, int $timeout = 60): void
    {
        $result = Process::timeout($timeout)->run($command);

        if ($result->failed()) {
            $this->error("❌ Command failed: $command");
            $this->error($result->errorOutput());
            exit(1);
        }

        $this->line(trim($result->output()));
    }
}
