<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TourInterestRequest;
use App\Models\User;
use App\Services\TourInterestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TourInterestRequestController extends Controller
{
    public function index(Request $request, TourInterestService $tours): View
    {
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('q', ''));

        $query = TourInterestRequest::query()
            ->with(['member', 'assignee'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search): void {
                $like = '%'.addcslashes($search, '\\%_').'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('member_id', 'like', $like)
                        ->orWhereHas('member', function ($member) use ($like): void {
                            $member->where('member_name', 'like', $like)
                                ->orWhere('email', 'like', $like)
                                ->orWhere('mobile_no', 'like', $like);
                        });
                });
            })
            ->latest('submitted_at');

        $requests = $query->paginate(20)->withQueryString();
        $packageMap = [];
        foreach ($requests as $row) {
            $packageMap[$row->member_id] = $tours->approvedPackagesFor($row->member_id);
        }

        return view('admin.tour-requests.index', [
            'requests' => $requests,
            'kpis' => $tours->kpis(),
            'packageMap' => $packageMap,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function show(TourInterestRequest $tourRequest, TourInterestService $tours): View
    {
        $tourRequest->load(['member', 'assignee', 'events.admin']);

        return view('admin.tour-requests.show', [
            'tourRequest' => $tourRequest,
            'packages' => $tours->approvedPackagesFor($tourRequest->member_id),
            'admins' => User::query()->where('is_admin', true)->orderBy('email')->get(['id', 'email', 'name']),
            'events' => $tourRequest->events,
        ]);
    }

    public function update(Request $request, TourInterestRequest $tourRequest, TourInterestService $tours): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,contacted,confirmed,completed,cancelled'],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'contacted_at' => ['nullable', 'date'],
            'assigned_admin_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $tours->updateStatus($tourRequest, [
            'status' => $data['status'],
            'admin_notes' => $data['admin_notes'] ?? null,
            'note' => $data['note'] ?? null,
            'contacted_at' => $data['contacted_at'] ?? null,
            'assigned_admin_id' => $data['assigned_admin_id'] ?? null,
        ], $request->user());

        return redirect()
            ->route('admin.tour-requests.show', $tourRequest)
            ->with('status', 'Tour request updated.');
    }

    public function export(Request $request, TourInterestService $tours): Response
    {
        $status = trim((string) $request->query('status', ''));
        $rows = TourInterestRequest::query()
            ->with(['member', 'assignee'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByDesc('submitted_at')
            ->limit(5000)
            ->get();

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, [
            'ID', 'Member ID', 'Name', 'Mobile', 'Email', 'Status', 'Submitted At', 'Contacted At', 'Assigned Admin', 'Packages',
        ]);
        foreach ($rows as $row) {
            $packages = collect($tours->approvedPackagesFor($row->member_id))
                ->map(fn ($p) => $p['code'])
                ->implode('; ');
            fputcsv($csv, [
                $row->id,
                $row->member_id,
                $row->member?->member_name,
                $row->member?->mobile_no,
                $row->member?->email,
                $row->status,
                $row->submitted_at?->timezone('Asia/Kolkata')->format('Y-m-d H:i:s'),
                $row->contacted_at?->timezone('Asia/Kolkata')->format('Y-m-d H:i:s'),
                $row->assignee?->email,
                $packages,
            ]);
        }
        rewind($csv);
        $content = stream_get_contents($csv) ?: '';
        fclose($csv);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="tour-interest-requests-'.now()->format('Ymd-His').'.csv"',
        ]);
    }
}
