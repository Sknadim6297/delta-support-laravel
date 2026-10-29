<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\MemberTransfer;
use App\Models\MemberTransferSetting;
use App\Services\MemberTransferWorkflow;
use App\Support\MoneyDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberTransferAdminController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->filtered($request);
        $completed = (clone $query)->where('status', MemberTransfer::COMPLETED);

        return view('admin.transfers.index', [
            'transfers' => $query->latest('id')->paginate(20)->withQueryString(),
            'volume' => MoneyDisplay::amount((clone $completed)->sum('amount')),
            'count' => (clone $completed)->count(),
            'settings' => MemberTransferSetting::current(),
            'statuses' => MemberTransfer::STATUSES,
        ]);
    }

    public function show(MemberTransfer $memberTransfer): View
    {
        return view('admin.transfers.show', [
            'transfer' => $memberTransfer->load(['sender', 'receiver']),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'member_transfers_enabled' => ['nullable', 'boolean'],
            'minimum_amount' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
            'maximum_amount' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
            'daily_limit' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
            'fee_amount' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
        ]);
        $minimum = bcadd($validated['minimum_amount'], '0', 4);
        $maximum = bcadd($validated['maximum_amount'], '0', 4);
        $daily = bcadd($validated['daily_limit'], '0', 4);
        $fee = bcadd($validated['fee_amount'], '0', 4);
        $errors = [];
        if (bccomp($minimum, '0', 4) !== 1) {
            $errors['minimum_amount'] = 'The minimum transfer must be greater than 0.';
        }
        if (bccomp($maximum, $minimum, 4) === -1) {
            $errors['maximum_amount'] = 'The maximum transfer must be at least the minimum.';
        }
        if (bccomp($daily, $maximum, 4) === -1) {
            $errors['daily_limit'] = 'The daily limit must be at least the maximum transfer.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        MemberTransferSetting::current()->forceFill([
            'member_transfers_enabled' => $request->boolean('member_transfers_enabled'),
            'minimum_amount' => $minimum,
            'maximum_amount' => $maximum,
            'daily_limit' => $daily,
            'fee_amount' => $fee,
            'denomination' => MoneyDisplay::USDT,
        ])->save();

        AdminActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'member_transfer_settings_updated',
            'subject' => 'member_transfer',
            'format' => null,
            'scope' => 'admin',
            'filters' => [
                'member_transfers_enabled' => $request->boolean('member_transfers_enabled'),
                'minimum_amount' => $minimum,
                'maximum_amount' => $maximum,
                'daily_limit' => $daily,
                'fee_amount' => $fee,
            ],
            'row_count' => 1,
        ]);

        return redirect()
            ->route('admin.member-transfers.index')
            ->with('status', 'Member transfer settings saved. Other financial switches were not changed.');
    }

    public function reverse(Request $request, MemberTransfer $memberTransfer, MemberTransferWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $workflow->reverse($memberTransfer, $request->user(), $validated['reason']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['reason' => $exception->getMessage()]);
        }

        AdminActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'member_transfer_reversed',
            'subject' => $memberTransfer->transfer_reference,
            'format' => null,
            'scope' => 'admin',
            'filters' => ['reference' => $memberTransfer->transfer_reference],
            'row_count' => 1,
        ]);

        return redirect()
            ->route('admin.member-transfers.show', $memberTransfer)
            ->with('status', 'The transfer was reversed. The original amount was not edited.');
    }

    public function export(Request $request): StreamedResponse
    {
        $transfers = $this->filtered($request)->latest('id')->get();
        AdminActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'member_transfer_export',
            'subject' => 'member_transfer',
            'format' => 'csv',
            'scope' => 'admin',
            'filters' => $request->only(['sender_member_id', 'receiver_member_id', 'reference', 'status', 'from_date', 'to_date']),
            'row_count' => $transfers->count(),
        ]);

        return response()->streamDownload(function () use ($transfers): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Reference', 'Business date', 'Completed at', 'Sender', 'Receiver', 'Amount', 'Fee', 'Currency', 'Status']);
            foreach ($transfers as $transfer) {
                fputcsv($output, [
                    $this->csv($transfer->transfer_reference),
                    $transfer->business_date?->toDateString(),
                    $transfer->completed_at?->timezone('Asia/Kolkata')->format('Y-m-d H:i:s'),
                    $this->csv($transfer->sender_member_id),
                    $this->csv($transfer->receiver_member_id),
                    MoneyDisplay::amount($transfer->amount),
                    MoneyDisplay::amount($transfer->fee_amount),
                    $transfer->currency,
                    $transfer->status,
                ]);
            }
            fclose($output);
        }, 'member-transfers-'.now()->timezone('Asia/Kolkata')->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function filtered(Request $request)
    {
        $validated = $request->validate([
            'sender_member_id' => ['nullable', 'string', 'max:32'],
            'receiver_member_id' => ['nullable', 'string', 'max:32'],
            'reference' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'in:'.implode(',', MemberTransfer::STATUSES)],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        return MemberTransfer::query()
            ->when($validated['sender_member_id'] ?? null, fn ($query, $sender) => $query->where('sender_member_id', trim($sender)))
            ->when($validated['receiver_member_id'] ?? null, fn ($query, $receiver) => $query->where('receiver_member_id', trim($receiver)))
            ->when($validated['reference'] ?? null, fn ($query, $reference) => $query->where('transfer_reference', trim($reference)))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['from_date'] ?? null, fn ($query, $from) => $query->whereDate('business_date', '>=', $from))
            ->when($validated['to_date'] ?? null, fn ($query, $to) => $query->whereDate('business_date', '<=', $to));
    }

    private function csv(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
