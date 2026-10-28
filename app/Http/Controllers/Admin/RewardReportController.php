<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RewardAssignment;
use App\Models\RewardCampaign;
use App\Models\RewardDelivery;
use App\Models\RewardQualification;
use App\Services\Reports\TabularExport;
use App\Services\Rewards\RewardEligibilityQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RewardReportController extends Controller
{
    public function index(): View
    {
        $campaigns = RewardCampaign::query()
            ->withCount([
                'qualifications as qualified_count' => fn ($query) => $query->whereIn('status', [
                    RewardQualification::QUALIFIED,
                    RewardQualification::SELECTED,
                ]),
                'assignments as selected_count' => fn ($query) => $query->where('status', RewardAssignment::SELECTED),
                'assignments as superseded_count' => fn ($query) => $query->where('status', RewardAssignment::SUPERSEDED),
            ])
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get();

        $deliveries = RewardDelivery::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.rewards.report', [
            'campaigns' => $campaigns,
            'deliveries' => $deliveries,
        ]);
    }

    public function export(Request $request, RewardEligibilityQuery $query, TabularExport $export): StreamedResponse
    {
        $format = (string) $request->query('format', 'csv');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        $rows = $query->filtered($request)->get();
        if ($format === 'pdf') {
            $rows = $rows->take((int) config('deltasupport.report_pdf_row_limit', 500));
        }

        $exportRows = $rows->map(function (RewardQualification $qualification): array {
            return [
                $qualification->member_id,
                $qualification->member_name,
                (string) $qualification->sponsor_id,
                $qualification->campaign?->title,
                $qualification->assignment?->gift_name ?: $qualification->campaign?->gift_name,
                (string) $qualification->target_amount,
                (string) $qualification->achieved_amount,
                'USDT',
                optional($qualification->qualified_at)?->timezone('Asia/Kolkata')->format('d M Y H:i') ?? '',
                $qualification->statusLabel(),
                $qualification->assignment?->statusLabel() ?? '',
                $qualification->assignment?->delivery?->statusLabel() ?? '',
            ];
        });

        return $export->download(
            'rewards',
            $format,
            'filtered',
            array_filter([
                'q' => (string) $request->query('q', ''),
                'campaign_id' => (string) $request->query('campaign_id', ''),
                'status' => (string) $request->query('status', ''),
                'member_id' => (string) $request->query('member_id', ''),
                'from' => (string) $request->query('from', ''),
                'to' => (string) $request->query('to', ''),
            ], fn ($value) => $value !== ''),
            [
                'Member ID',
                'Member name',
                'Sponsor',
                'Campaign',
                'Reward',
                'Target business',
                'Achieved business',
                'Currency',
                'Qualification',
                'Eligibility',
                'Selected reward',
                'Delivery',
            ],
            $exportRows,
            (int) $request->user()->id,
        );
    }
}
