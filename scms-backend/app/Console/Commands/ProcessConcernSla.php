<?php

namespace App\Console\Commands;

use App\Services\ConcernSlaService;
use Illuminate\Console\Command;

/**
 * Evaluates unresolved concerns against their configured resolution targets.
 *
 * Scheduled in routes/console.php. Safe to run repeatedly: every follow-up
 * and deadline event is recorded once in concern_routing and never repeated.
 */
class ProcessConcernSla extends Command
{
    protected $signature = 'concerns:process-sla';

    protected $description = 'Send automatic 75% follow-ups and apply deadline escalation for unresolved concerns';

    public function handle(ConcernSlaService $sla): int
    {
        if (!$sla->isEnabled()) {
            $this->info('SLA monitoring is disabled (SLA_ENABLED=false). Nothing processed.');
            return self::SUCCESS;
        }

        $stats = $sla->process();

        $this->info(sprintf(
            'SLA run complete: %d scanned, %d follow-up(s), %d escalation(s), %d overdue notice(s).',
            $stats['scanned'],
            $stats['followups'],
            $stats['escalations'],
            $stats['overdue_notices']
        ));

        return self::SUCCESS;
    }
}
