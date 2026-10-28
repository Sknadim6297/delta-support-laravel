<?php

namespace App\Services\Rewards;

use App\Models\RewardDelivery;
use App\Models\RewardDeliveryEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RewardFulfillmentService
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        RewardDelivery::PENDING => [RewardDelivery::APPROVED, RewardDelivery::CANCELLED],
        RewardDelivery::APPROVED => [RewardDelivery::PROCESSING, RewardDelivery::CANCELLED],
        RewardDelivery::PROCESSING => [RewardDelivery::SHIPPED, RewardDelivery::FAILED, RewardDelivery::CANCELLED],
        RewardDelivery::SHIPPED => [RewardDelivery::DELIVERED, RewardDelivery::FAILED],
        RewardDelivery::FAILED => [RewardDelivery::PROCESSING, RewardDelivery::CANCELLED],
        RewardDelivery::DELIVERED => [],
        RewardDelivery::CANCELLED => [],
    ];

    public function choices(RewardDelivery $delivery): array
    {
        return self::TRANSITIONS[$delivery->status] ?? [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function transition(RewardDelivery $delivery, array $data, ?User $actor, bool $system = false): RewardDelivery
    {
        $from = (string) $delivery->status;
        $to = (string) ($data['status'] ?? '');

        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => 'That delivery status change is not allowed.',
            ]);
        }

        if (! $system && $actor === null) {
            throw ValidationException::withMessages([
                'status' => 'An admin must confirm this delivery change.',
            ]);
        }

        $confirmation = trim((string) ($data['recipient_confirmation'] ?? $delivery->recipient_confirmation ?? ''));
        if ($to === RewardDelivery::DELIVERED && $confirmation === '') {
            throw ValidationException::withMessages([
                'recipient_confirmation' => 'Delivered status needs a recipient confirmation.',
            ]);
        }

        $dispatchedAt = $this->instant($data['dispatched_at'] ?? null) ?? $delivery->dispatched_at;
        if ($to === RewardDelivery::SHIPPED && $dispatchedAt === null) {
            throw ValidationException::withMessages([
                'dispatched_at' => 'A dispatch date is required. A tracking reference is optional.',
            ]);
        }

        $deliveredAt = $this->instant($data['delivered_at'] ?? null) ?? $delivery->delivered_at;
        if ($to === RewardDelivery::DELIVERED && $deliveredAt === null) {
            throw ValidationException::withMessages([
                'delivered_at' => 'A delivery date is required.',
            ]);
        }

        return DB::transaction(function () use ($delivery, $data, $actor, $system, $from, $to, $confirmation, $dispatchedAt, $deliveredAt): RewardDelivery {
            $locked = RewardDelivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== $from) {
                throw ValidationException::withMessages([
                    'status' => 'This delivery changed before the update was saved.',
                ]);
            }

            $locked->forceFill([
                'status' => $to,
                'method' => array_key_exists('method', $data) ? $this->blankToNull($data['method']) : $locked->method,
                'tracking_reference' => array_key_exists('tracking_reference', $data) ? $this->blankToNull($data['tracking_reference']) : $locked->tracking_reference,
                'dispatched_at' => $dispatchedAt,
                'delivered_at' => $deliveredAt,
                'recipient_confirmation' => $to === RewardDelivery::DELIVERED || $confirmation !== '' ? ($confirmation !== '' ? $confirmation : null) : $locked->recipient_confirmation,
                'internal_notes' => array_key_exists('internal_notes', $data) ? $this->blankToNull($data['internal_notes']) : $locked->internal_notes,
            ])->save();

            RewardDeliveryEvent::query()->create([
                'delivery_id' => $locked->id,
                'user_id' => $actor?->id,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $system
                    ? 'Recorded by reward selection.'
                    : $this->blankToNull($data['internal_notes'] ?? null),
                'created_at' => now(),
            ]);

            return $locked->refresh();
        });
    }

    public function reopenCancelled(RewardDelivery $delivery): void
    {
        if ($delivery->status !== RewardDelivery::CANCELLED) {
            return;
        }

        DB::transaction(function () use ($delivery): void {
            $locked = RewardDelivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== RewardDelivery::CANCELLED) {
                return;
            }

            $locked->forceFill(['status' => RewardDelivery::PENDING])->save();
            RewardDeliveryEvent::query()->create([
                'delivery_id' => $locked->id,
                'user_id' => null,
                'from_status' => RewardDelivery::CANCELLED,
                'to_status' => RewardDelivery::PENDING,
                'note' => 'Selected again for this campaign.',
                'created_at' => now(),
            ]);
        });
    }

    private function instant(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value, 'Asia/Kolkata')->utc();
    }

    private function blankToNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
