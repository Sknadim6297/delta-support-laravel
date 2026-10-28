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
        $this->package('DEMO-COAST', [
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => 'Coastal Weekend',
            'description' => 'Coastal Weekend. Daily ROI is 0.2500 USDT for 800 days. The commission pool is 0.2400 USDT for 400 days.',
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'tour_title' => 'Coastal weekend',
            'tour_details' => 'Puri and Konark.',
            'destinations' => 'Puri and Konark',
            'duration' => '2 days / 1 night',
            'benefits' => 'Hotel stay and local transfers',
            'inclusions' => 'Breakfast and sightseeing',
            'exclusions' => 'Flights and personal expenses',
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 20,
        ]);
        $this->package('DEMO-HILLS', [
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => 'Hill Station Retreat',
            'description' => 'Hill Station Retreat. Daily ROI is 2.5000 USDT for 1000 days. The commission pool is 2.4000 USDT for 400 days.',
            'price_usd' => '1000.0000',
            'denomination' => 'USDT',
            'tour_title' => 'Hill station retreat',
            'tour_details' => 'Darjeeling.',
            'destinations' => 'Darjeeling',
            'duration' => '3 days / 2 nights',
            'benefits' => 'Hotel stay and a local tour',
            'inclusions' => 'Breakfast and transfers',
            'exclusions' => 'Flights and personal expenses',
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 21,
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
