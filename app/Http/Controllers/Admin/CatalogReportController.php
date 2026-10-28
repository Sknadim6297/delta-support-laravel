<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImpersonationLog;
use App\Models\Member;
use App\Services\Reports\CatalogReport;
use App\Services\Reports\TabularExport;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogReportController extends Controller
{
    public function __construct(
        private readonly CatalogReport $reports,
        private readonly TabularExport $exports,
    ) {
    }

    public function show(Request $request, string $report): View
    {
        $filters = $this->reports->filters($request->query(), $report);
        $page = $this->reports->page($report, $filters);
        $rows = $page['rows']->through(fn (object $row): array => $this->reports->presentRow($report, $row));

        return view('admin.reports.catalog', [
            'report' => $report,
            'filters' => $filters,
            'headings' => $this->reports->headings($report, $filters),
            'rows' => $rows,
            'packages' => $page['packages'],
            'totals' => $page['totals'],
            'counts' => $page['counts'],
        ]);
    }

    public function member(string $member): View
    {
        $record = Member::query()->where('member_id', $member)->firstOrFail();
        $purchases = $record->packagePurchases()
            ->with('reviewer:id,name')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(CatalogReport::PER_PAGE)
            ->withQueryString();
        $activity = ImpersonationLog::query()
            ->with('admin:id,name')
            ->where('member_id', $record->member_id)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('admin.reports.member', [
            'member' => $record,
            'purchases' => $purchases,
            'activity' => $activity,
        ]);
    }

    public function export(Request $request, string $report): StreamedResponse
    {
        $choice = $request->validate([
            'format' => ['required', 'in:csv,xlsx,pdf'],
            'scope' => ['required', 'in:filtered,full'],
        ]);
        $filters = $this->reports->filters($request->query(), $report);
        if ($choice['scope'] === 'full') {
            $filters = $this->reports->withoutLimits($filters);
        }
        if ($choice['format'] === 'pdf') {
            $count = $this->reports->exportCount($report, $filters);
            $limit = (int) config('deltasupport.report_pdf_row_limit');
            abort_if($count > $limit, 422, 'PDF exports are limited to '.$limit.' rows. Narrow the filters or choose CSV or Excel.');
        }

        return $this->exports->download(
            $report,
            $choice['format'],
            $choice['scope'],
            $this->reports->describeFilters($filters),
            $this->reports->headings($report, $filters),
            $this->reports->exportRows($report, $filters),
            (int) $request->user()->id,
        );
    }
}
