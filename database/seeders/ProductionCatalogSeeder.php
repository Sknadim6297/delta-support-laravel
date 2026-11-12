<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\Package;
use App\Models\PaymentSetting;
use Illuminate\Database\Seeder;

class ProductionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->package('DST-ROI-002', [
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => 'Delta Support Digha Tour + Monthly Income',
            'description' => 'Digha Tour + Monthly Income. Daily ROI is 0.2500 USDT for 800 days. The commission pool is 0.2400 USDT for 400 days.',
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'tour_title' => 'Digha holiday after 30 days',
            'tour_details' => 'Digha holiday; 3 Nights / 4 Days.',
            'destinations' => 'Digha, West Bengal',
            'duration' => '3 Days / 2 Nights',
            'benefits' => 'Digha holiday; 3 Nights / 4 Days; proposed monthly payout of 0.25$ *800 days, subject to the approved plan.',
            'inclusions' => null,
            'exclusions' => null,
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 20,
        ]);
        $this->package('DST-TOUR-003', [
            'package_type' => Package::TYPE_TOUR,
            'name' => 'Delta Support 3-Year Tour Package',
            'description' => 'Three holiday trips over three years. This package does not earn daily ROI or level commission.',
            'price_usd' => '1000.0000',
            'denomination' => 'USDT',
            'tour_title' => 'Enjoy digha',
            'tour_details' => 'Three holiday trips over three years; Digha tour after 30 days; Goa and Gangtok tours in subsequent years.',
            'destinations' => 'Digha, West Bengal;',
            'duration' => '4 Days / 3 Nights per trip',
            'benefits' => 'Three holiday trips over three years; Digha tour after 30 days; Goa and Gangtok tours in subsequent years.',
            'inclusions' => null,
            'exclusions' => 'Daily ROI and level commission',
            'roi_eligible' => false,
            'level_commission_eligible' => false,
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $this->package('VERIFY-1', [
            'package_type' => Package::TYPE_TOUR,
            'name' => 'Verification Package',
            'description' => 'Membership verification. This package does not earn ROI or level commission.',
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'tour_title' => 'Verification',
            'tour_details' => 'Account verification only.',
            'destinations' => null,
            'duration' => '1 day',
            'benefits' => 'Membership verification',
            'inclusions' => null,
            'exclusions' => 'ROI and level commission',
            'roi_eligible' => false,
            'level_commission_eligible' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $payment = PaymentSetting::current();
        $payment->fill([
            'currency_label' => 'USDT',
            'instructions' => PaymentSetting::USDT_INSTRUCTION,
            'is_enabled' => false,
        ])->save();

        CompanySetting::current();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function package(string $code, array $attributes): void
    {
        Package::query()->updateOrCreate(['code' => $code], $attributes);
    }
}
