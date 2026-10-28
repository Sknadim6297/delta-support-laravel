<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RewardCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'gift_name' => ['required', 'string', 'max:160'],
            'gift_description' => ['nullable', 'string', 'max:5000'],
            'target_amount' => ['required', 'regex:/^\d{1,12}(\.\d{1,4})?$/'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $target = (string) $this->input('target_amount', '0');
            if (preg_match('/^\d{1,12}(\.\d{1,4})?$/', $target) && bccomp($target, '0', 4) <= 0) {
                $validator->errors()->add('target_amount', 'The team business target must be greater than zero.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function campaignAttributes(): array
    {
        $stock = $this->input('stock_quantity');

        return [
            'title' => trim((string) $this->input('title')),
            'description' => $this->blank($this->input('description')),
            'gift_name' => trim((string) $this->input('gift_name')),
            'gift_description' => $this->blank($this->input('gift_description')),
            'target_amount' => bcadd((string) $this->input('target_amount'), '0', 4),
            'denomination' => 'USDT',
            'starts_on' => $this->input('starts_on'),
            'ends_on' => $this->input('ends_on'),
            'stock_quantity' => $stock === null || $stock === '' ? null : (int) $stock,
            'terms' => $this->blank($this->input('terms')),
            'admin_notes' => $this->blank($this->input('admin_notes')),
        ];
    }

    private function blank(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
