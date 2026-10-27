<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    protected $fillable = [
        'currency_label',
        'network',
        'receiving_address',
        'qr_image_path',
        'instructions',
        'reference_pattern',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate([], [
            'is_enabled' => false,
        ]);
    }

    public function isReady(): bool
    {
        return $this->is_enabled
            && trim((string) $this->currency_label) !== ''
            && trim((string) $this->network) !== ''
            && trim((string) $this->instructions) !== '';
    }
}
