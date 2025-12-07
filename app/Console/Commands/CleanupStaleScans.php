<?php

namespace App\Console\Commands;

use App\Models\Scan;
use Illuminate\Console\Command;

class CleanupStaleScans extends Command
{
    protected $signature = 'scans:cleanup';

    protected $description = 'Mark stale/orphaned scans as failed';

    public function handle(): int
    {
        $count = Scan::markStaleAsFailed();

        if ($count > 0) {
            $this->info("Marked {$count} stale scan(s) as failed.");
        } else {
            $this->info('No stale scans found.');
        }

        return Command::SUCCESS;
    }
}
