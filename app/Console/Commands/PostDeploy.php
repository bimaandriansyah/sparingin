<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Finishes an FTP deploy on hosting without SSH.
 *
 * The CI upload drops storage/app/deploy-pending; a cPanel cron runs this command every few
 * minutes. When the flag is there it migrates the database and clears stale caches, once.
 * Without the flag it does nothing, so the cron is cheap.
 */
#[Signature('sparingin:post-deploy')]
#[Description('Run migrations + clear caches after an FTP deploy (triggered by storage/app/deploy-pending)')]
class PostDeploy extends Command
{
    public function handle(): int
    {
        $flag = storage_path('app/deploy-pending');
        if (! is_file($flag)) {
            return self::SUCCESS;
        }

        // Claim the flag first so an overlapping cron run can't migrate twice.
        $running = $flag.'.running';
        if (! @rename($flag, $running)) {
            return self::SUCCESS;
        }
        $release = trim((string) file_get_contents($running));

        try {
            Artisan::call('migrate', ['--force' => true]);
            $migrated = trim(Artisan::output());
            foreach (['config:clear', 'route:clear', 'view:clear'] as $command) {
                Artisan::call($command);
            }
            Log::info("Post-deploy {$release} done", ['migrate' => $migrated]);
            $this->info("Post-deploy {$release} done.");
            @unlink($running);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            // Put the flag back so the next cron run retries.
            @rename($running, $flag);
            Log::error("Post-deploy {$release} failed: {$e->getMessage()}");
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
