<?php

namespace App\Console\Commands;

use App\Services\IndependentLevelUnlockAuditor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Production-safe read-only verification of Level Unlock for every registered member.
 * Does not authorize/revoke, credit wallets, mutate purchases, or clear caches.
 */
class VerifyAllMembersLevelUnlock extends Command
{
    protected $signature = 'deltasupport:verify-all-members-level-unlock
                            {--rule-version= : Explicit level_income_rule_versions.version_code}
                            {--business-date= : Asia/Kolkata business date (YYYY-MM-DD); defaults to today}
                            {--output= : Optional markdown report path}
                            {--json : Emit JSON to stdout}
                            {--fail-on-mismatch : Exit 1 when any member FAIL}';

    protected $description = 'Read-only: independently verify unlocked levels for ALL registered members (no financial writes).';

    public function handle(IndependentLevelUnlockAuditor $auditor): int
    {
        $tz = $auditor->businessTimezone();
        $businessDate = trim((string) $this->option('business-date'));
        if ($businessDate === '') {
            $businessDate = now($tz)->toDateString();
        }

        $resolved = $auditor->resolveVersion($this->option('rule-version') ? (string) $this->option('rule-version') : null);
        if (! $resolved['found'] || $resolved['version'] === null) {
            $requested = $resolved['requested'] ?? '(current authorized)';
            $this->error('Rule version not found: '.$requested);

            return self::FAILURE;
        }

        $version = $resolved['version'];
        $this->info(sprintf(
            'Verifying ALL members against %s (auth=%s eff=%s app=%s) as-of %s %s',
            $version->version_code,
            $version->posting_authorized ? 'yes' : 'no',
            optional($version->effective_from)?->toDateString() ?? '-',
            $version->applicability,
            $businessDate,
            $tz
        ));

        $rows = $auditor->verifyAllMembers($version, $businessDate);
        $summary = $auditor->summarize($rows);

        $this->table(
            ['Member', 'Join', 'Purch', 'Act', 'Legacy', 'Expected', 'Protected', 'Runtime', 'Dash', 'Result'],
            array_map(static fn (array $r): array => [
                $r['member_id'],
                $r['joining'],
                $r['approved_purchases'],
                $r['activated'] ? 'Y' : 'N',
                $r['legacy_level'],
                $r['expected_level'],
                $r['protected_level'],
                $r['runtime_level'],
                $r['dashboard_level'],
                $r['result'],
            ], $rows)
        );

        $this->line(sprintf(
            'Summary: total=%d pass=%d fail=%d all_pass=%s',
            $summary['total_members'],
            $summary['pass'],
            $summary['fail'],
            $summary['all_pass'] ? 'YES' : 'NO'
        ));

        if ($summary['mismatches'] !== []) {
            $this->warn('Mismatches:');
            foreach ($summary['mismatches'] as $m) {
                $this->line(sprintf(
                    ' - %s: %s (expected/protected/runtime=%d/%d/%d)',
                    $m['member_id'],
                    implode('; ', $m['fail_reasons']),
                    $m['expected_level'],
                    $m['protected_level'],
                    $m['runtime_level']
                ));
            }
        }

        $payload = [
            'generated_at' => now($tz)->toIso8601String(),
            'read_only' => true,
            'business_timezone' => $tz,
            'business_date' => $businessDate,
            'rule_selection' => [
                'requested' => $resolved['requested'],
                'selection' => $resolved['selection'],
                'version_code' => $version->version_code,
                'status' => $version->status,
                'posting_authorized' => (bool) $version->posting_authorized,
                'applicability' => $version->applicability,
                'effective_from' => optional($version->effective_from)?->toDateString(),
                'direct_qualification_basis' => $version->direct_qualification_basis,
                'combined_purchase_scope' => $version->combined_purchase_scope,
                'combined_repeat_purchase_mode' => $version->combined_repeat_purchase_mode,
                'tour_only_qualifies_for_unlock' => (bool) $version->tour_only_qualifies_for_unlock,
                'grandfather_existing_unlock' => (bool) $version->grandfather_existing_unlock,
            ],
            'formula' => [
                'expected' => 'activated ? min(20, 4 × (joining + approved_personal_purchases)) : 0',
                'protected' => 'max(proposed, min(snapshot_legacy, current_legacy)) when grandfather+snapshot; else proposed',
                'pending_rejected' => 'excluded from approved purchase counts',
                'tour_only' => 'counts toward unlock when tour_only_qualifies_for_unlock; does not enable ROI/commission generation',
            ],
            'summary' => $summary,
            'members' => $rows,
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT));
        }

        $output = trim((string) $this->option('output'));
        if ($output !== '') {
            $markdown = $this->toMarkdown($payload);
            File::ensureDirectoryExists(dirname($output));
            File::put($output, $markdown);
            $this->info('Wrote '.$output);
        }

        if ((bool) $this->option('fail-on-mismatch') && ! $summary['all_pass']) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function toMarkdown(array $payload): string
    {
        $s = $payload['summary'];
        $v = $payload['rule_selection'];
        $lines = [];
        $lines[] = '# Final All-Members Level Unlock Verification Report';
        $lines[] = '';
        $lines[] = '**Generated:** '.$payload['generated_at'];
        $lines[] = '**Read-only:** yes';
        $lines[] = '**Business date:** '.$payload['business_date'].' ('.$payload['business_timezone'].')';
        $lines[] = '**Rule:** `'.$v['version_code'].'` (requested: '.($v['requested'] ?? 'current authorized').'; selection='.$v['selection'].')';
        $lines[] = '**Authorized:** '.($v['posting_authorized'] ? 'yes' : 'no').' | **Effective:** '.($v['effective_from'] ?? '-').' | **Applicability:** '.$v['applicability'];
        $lines[] = '';
        $lines[] = '## Summary';
        $lines[] = '';
        $lines[] = '| Metric | Value |';
        $lines[] = '|--------|-------|';
        $lines[] = '| Members verified | '.$s['total_members'].' |';
        $lines[] = '| PASS | '.$s['pass'].' |';
        $lines[] = '| FAIL | '.$s['fail'].' |';
        $lines[] = '| All pass | '.($s['all_pass'] ? 'YES' : 'NO').' |';
        $lines[] = '';
        $lines[] = '## Formula (independent DB calculation)';
        $lines[] = '';
        $lines[] = '- Expected: `'.$payload['formula']['expected'].'`';
        $lines[] = '- Protected: `'.$payload['formula']['protected'].'`';
        $lines[] = '- Pending/rejected: '.$payload['formula']['pending_rejected'];
        $lines[] = '- Tour Only: '.$payload['formula']['tour_only'];
        $lines[] = '';
        $lines[] = '## Per-member comparison (complete; not truncated)';
        $lines[] = '';
        $lines[] = '| Member ID | Joining | Approved Purchases | Activated | Legacy Level | Expected Level | Protected Level | Runtime Level | Dashboard Level | PASS/FAIL |';
        $lines[] = '|-----------|---------|--------------------|-----------|--------------|----------------|-----------------|---------------|-----------------|-----------|';

        foreach ($payload['members'] as $row) {
            $lines[] = sprintf(
                '| %s | %d | %d | %s | %d | %d | %d | %d | %d | %s |',
                $row['member_id'],
                $row['joining'],
                $row['approved_purchases'],
                $row['activated'] ? 'Y' : 'N',
                $row['legacy_level'],
                $row['expected_level'],
                $row['protected_level'],
                $row['runtime_level'],
                $row['dashboard_level'],
                $row['result']
            );
        }

        $lines[] = '';
        $lines[] = '## Exact mismatches';
        $lines[] = '';
        if ($s['mismatches'] === []) {
            $lines[] = 'None. Every registered member was independently verified and matched.';
        } else {
            foreach ($s['mismatches'] as $m) {
                $lines[] = sprintf(
                    '- **%s**: %s (joining=%d purchases=%d expected=%d protected=%d runtime=%d)',
                    $m['member_id'],
                    implode('; ', $m['fail_reasons']),
                    $m['joining'],
                    $m['approved_purchases'],
                    $m['expected_level'],
                    $m['protected_level'],
                    $m['runtime_level']
                );
            }
        }

        $lines[] = '';

        return implode("\n", $lines)."\n";
    }
}
