<?php

namespace App\Console\Commands;

use App\Services\AssociateCommissionStructure;
use Illuminate\Console\Command;

/**
 * Restores associate_v1 DB rows if missing. Never enables posting/snapshot.
 */
class EnsureAssociateCommissionStructure extends Command
{
    protected $signature = 'associate:ensure-structure';

    protected $description = 'Restore associate_v1 structure rows if missing after a wipe. Does not enable posting.';

    public function handle(AssociateCommissionStructure $structure): int
    {
        $created = $structure->ensureCanonicalStructureStored();
        $errors = $structure->integrityErrors();

        if ($created) {
            $this->info('associate_v1 rows restored. Posting remains disabled. Snapshot remains disabled.');
        } else {
            $this->info('associate_v1 already present or tables unavailable. No rows were rewritten.');
        }

        if ($errors === []) {
            $this->info('Integrity check: OK');
        } else {
            foreach ($errors as $error) {
                $this->warn('• '.$error);
            }
        }

        $this->line('posting_enabled='.($structure->postingEnabled() ? 'true' : 'false'));
        $this->line('snapshot_on_approval='.($structure->snapshotOnApprovalEnabled() ? 'true' : 'false'));

        return $errors === [] || $created ? self::SUCCESS : self::FAILURE;
    }
}
