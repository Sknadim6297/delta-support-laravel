<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Services\PackagePurchaseService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoCatalogSeeder extends Seeder
{
    public const PASSWORD = 'DemoPass9101';

    public function run(): void
    {
        $this->call(RankSeeder::class);
        $admin = $this->admin();
        $tour = $this->package('DEMO-COAST', [
            'package_type' => Package::TYPE_TOUR,
            'name' => 'Coastal Weekend',
            'description' => 'A short demo tour package for local testing.',
            'price_usd' => '49.0000',
            'denomination' => 'USDT',
            'tour_title' => 'Coastal weekend',
            'tour_details' => 'Demo itinerary stored for the package catalog.',
            'destinations' => 'Puri and Konark',
            'duration' => '2 days / 1 night',
            'benefits' => 'Hotel stay and local transfers',
            'inclusions' => 'Breakfast and sightseeing',
            'exclusions' => 'Flights and personal expenses',
            'roi_eligible' => false,
            'level_commission_eligible' => false,
            'is_active' => true,
            'sort_order' => 20,
        ]);
        $retreat = $this->package('DEMO-HILLS', [
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => 'Hill Station Retreat',
            'description' => 'A demo tour package. It does not credit ROI or commission.',
            'price_usd' => '99.0000',
            'denomination' => 'USDT',
            'tour_title' => 'Hill station retreat',
            'tour_details' => 'Demo itinerary stored for the package catalog.',
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

        $this->paymentSettings();
        CompanySetting::current();

        $active = $this->member('ST910101', 'Demo Active', 'demo-active-910101@example.test', '9101010001');
        $pending = $this->member('ST910102', 'Demo Pending', 'demo-pending-910102@example.test', '9101020002');
        $this->member('ST910103', 'Demo Visitor', 'demo-visitor-910103@example.test', '9101030003');

        $this->purchase($active, $tour, '11111111-1111-4111-8111-000000910101', 'DEMOAPPROVED910101', true, $admin);
        $this->purchase($pending, $retreat, '11111111-1111-4111-8111-000000910102', 'DEMOPENDING910102', false, $admin);
    }

    private function admin(): User
    {
        $admin = User::query()->where('is_admin', true)->first();
        if ($admin) {
            return $admin;
        }

        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        if (is_string($email) && $email !== '' && is_string($password) && $password !== '') {
            $this->call(AdminUserSeeder::class);

            return User::query()->where('is_admin', true)->firstOrFail();
        }

        return User::factory()->create([
            'name' => 'Delta Support Admin',
            'email' => 'demo-admin@example.test',
            'is_admin' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function package(string $code, array $attributes): Package
    {
        $package = Package::query()->firstOrCreate(['code' => $code], $attributes);
        $package->update(['image_path' => $this->cover($code, $package->name)]);

        return $package;
    }

    private function paymentSettings(): void
    {
        $settings = PaymentSetting::current();
        $settings->currency_label = trim((string) $settings->currency_label) !== '' ? $settings->currency_label : 'USDT';

        if (trim((string) $settings->network) === '' || PaymentSetting::isPlaceholder((string) $settings->network)) {
            $settings->network = 'BEP20 Demo Network';
        }
        if (trim((string) $settings->receiving_address) === '' || PaymentSetting::isPlaceholder((string) $settings->receiving_address)) {
            $settings->receiving_address = 'DEMO-BEP20-PUBLIC-ADDRESS-NOT-LIVE';
        }
        if (trim((string) $settings->instructions) === '' || stripos((string) $settings->instructions, 'USD package amount') !== false) {
            $settings->instructions = PaymentSetting::USDT_INSTRUCTION;
        }
        if (! $settings->qr_image_path || ! Storage::disk('local')->exists($settings->qr_image_path) || $settings->qr_image_path === 'payment-qr/demo-payment-qr.png') {
            $settings->qr_image_path = $this->qr();
        }
        if ($settings->enablementErrors() === []) {
            $settings->is_enabled = true;
        }

        $settings->save();
    }

    private function member(string $memberId, string $name, string $email, string $mobile): Member
    {
        $member = Member::query()->where('member_id', $memberId)->first();
        if ($member) {
            return $member;
        }

        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => $name,
            'mobile_no' => $mobile,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'status' => 'inactive',
            'activation_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'roi_wallet_amount' => '0.0000',
        ]);
    }

    private function purchase(Member $member, Package $package, string $idempotencyKey, string $reference, bool $approve, User $admin): void
    {
        $existing = PackagePurchase::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return;
        }

        $purchase = app(PackagePurchaseService::class)->submit($member, $package, $reference, $idempotencyKey);
        if ($approve) {
            app(PackagePurchaseService::class)->approve($purchase, $admin);
        }
    }

    private function cover(string $code, string $name): string
    {
        $path = 'package-images/'.$code.'.png';
        Storage::disk('local')->put($path, $this->png(720, 420, $name));

        return $path;
    }

    private function qr(): string
    {
        $path = 'payment-qr/demo-payment-qr.png';
        Storage::disk('local')->put($path, $this->png(360, 360, 'DEMO QR'));

        return $path;
    }

    private function png(int $width, int $height, string $label): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        }

        $width = max($width, $height);
        $height = $width;
        $image = imagecreatetruecolor($width, $height);
        $navy = imagecolorallocate($image, 9, 39, 71);
        $blue = imagecolorallocate($image, 8, 120, 209);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, $width, $height, $blue);
        imagefilledrectangle($image, 24, 24, $width - 25, $height - 25, $navy);
        $text = strtoupper(Str::limit($label, 28, ''));
        $font = 'C:\\Windows\\Fonts\\arialbd.ttf';
        if (function_exists('imagettftext') && is_file($font)) {
            $size = 32;
            do {
                $box = imagettfbbox($size, 0, $font, $text);
                $textWidth = abs($box[2] - $box[0]);
                $size -= 2;
            } while ($textWidth > $width - 80 && $size > 16);
            $size += 2;
            imagettftext($image, $size, 0, (int) (($width - $textWidth) / 2), (int) ($height / 2) + 12, $white, $font, $text);
        } else {
            $x = max(16, (int) (($width - (strlen($text) * 9)) / 2));
            imagestring($image, 5, $x, (int) ($height / 2) - 8, $text, $white);
        }
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes === false ? '' : $bytes;
    }
}
