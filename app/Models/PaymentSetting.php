<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    public const USDT_INSTRUCTION = 'Send the exact USDT package amount to the displayed wallet address using the specified blockchain network. Enter your transaction ID after sending payment. Your package will be activated only after administrator verification.';

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
        return $this->is_enabled && $this->enablementErrors() === [];
    }

    /**
     * @return array<string, string>
     */
    public function enablementErrors(): array
    {
        $errors = [];
        $token = trim((string) $this->currency_label);
        $network = trim((string) $this->network);
        $address = trim((string) $this->receiving_address);
        $instructions = trim((string) $this->instructions);

        if (strcasecmp($token, 'USDT') !== 0) {
            $errors['currency_label'] = 'The accepted token must be USDT before submissions can be enabled.';
        }
        if ($network === '' || self::isPlaceholder($network)) {
            $errors['network'] = 'Enter the blockchain network. A placeholder network cannot enable submissions.';
        }
        if ($address === '' || self::isPlaceholder($address) || self::containsSecret($address)) {
            $errors['receiving_address'] = 'Enter the public receiving address. Do not store a private key or seed phrase.';
        }
        if ($instructions === '' || stripos($instructions, 'USD package amount') !== false || self::containsSecret($instructions)) {
            $errors['instructions'] = 'Payment instructions must tell the member to send the exact USDT amount on the configured network.';
        }
        if (trim((string) $this->qr_image_path) === '') {
            $errors['qr_image'] = 'Upload a JPG, PNG, or WebP payment QR before enabling submissions.';
        }

        return $errors;
    }

    public static function isPlaceholder(string $value): bool
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));

        return in_array($normalized, [
            'configured network',
            'configured chain',
            'configured token',
            'configured asset',
            'local-verification-address',
            'public-receiving-address',
        ], true);
    }

    public static function containsSecret(string $value): bool
    {
        return preg_match('/private key|seed phrase|mnemonic/i', $value) === 1;
    }
}
