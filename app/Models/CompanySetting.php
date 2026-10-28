<?php

namespace App\Models;

use App\Support\BrandTokens;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $fillable = [
        'company_name',
        'address',
        'email',
        'phone',
        'logo_path',
        'primary_color',
        'primary_hover_color',
        'navy_color',
        'canvas_color',
        'border_color',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate([], [
            'company_name' => (string) config('app.name'),
        ]);
    }

    public function hasCustomLogo(): bool
    {
        return BrandTokens::isManagedLogo($this->logo_path);
    }
}
