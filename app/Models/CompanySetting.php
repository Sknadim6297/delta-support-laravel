<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $fillable = [
        'company_name',
        'address',
        'email',
        'phone',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate([], [
            'company_name' => (string) config('app.name'),
        ]);
    }
}
