<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PackageController extends Controller
{
    public function index(Request $request): View
    {
        $packages = Package::query()
            ->withCount('purchases')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%' . trim((string) $request->query('q')) . '%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)->orWhere('code', 'like', $term);
                });
            })
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('is_active', $request->query('status') === 'active');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.packages.index', compact('packages'));
    }

    public function create(): View
    {
        return view('admin.packages.form', ['package' => new Package(['is_active' => true, 'sort_order' => 0, 'roi_eligible' => false])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $package = Package::query()->create($this->validated($request));
        $this->storeImage($request, $package);

        return redirect()->route('admin.packages.index')->with('success', 'Package saved.');
    }

    public function edit(Package $package): View
    {
        return view('admin.packages.form', compact('package'));
    }

    public function update(Request $request, Package $package): RedirectResponse
    {
        $package->update($this->validated($request, $package));
        $this->storeImage($request, $package);

        return redirect()->route('admin.packages.index')->with('success', 'Package updated. Earlier purchases keep their saved price and tour details.');
    }

    public function updateStatus(Request $request, Package $package): RedirectResponse
    {
        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $package->update(['is_active' => $request->boolean('is_active')]);

        return back()->with('success', $package->is_active
            ? 'Package activated.'
            : 'Package deactivated. Existing purchases were not changed.');
    }

    public function image(Package $package): BinaryFileResponse
    {
        return $this->imageResponse($package, false);
    }

    public function publicImage(Package $package): BinaryFileResponse
    {
        return $this->imageResponse($package, true);
    }

    private function validated(Request $request, ?Package $package = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('packages', 'code')->ignore($package)],
            'name' => ['required', 'string', 'max:120'],
            'package_type' => ['nullable', Rule::in(array_keys(Package::TYPES))],
            'description' => ['nullable', 'string', 'max:500'],
            'full_description' => ['nullable', 'string', 'max:10000'],
            'price_usd' => ['required', 'regex:/^\d{1,12}(\.\d{1,4})?$/'],
            'tour_title' => ['nullable', 'string', 'max:160'],
            'tour_details' => ['nullable', 'string', 'max:5000'],
            'destinations' => ['nullable', 'string', 'max:2000'],
            'duration' => ['nullable', 'string', 'max:80'],
            'benefits' => ['nullable', 'string', 'max:5000'],
            'inclusions' => ['nullable', 'string', 'max:5000'],
            'exclusions' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
            'roi_eligible' => ['nullable', 'boolean'],
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['package_type'] = $data['package_type'] ?? Package::TYPE_TOUR;
        $data['is_active'] = $request->boolean('is_active');
        $reservesIncome = $data['package_type'] !== Package::TYPE_TOUR;
        $data['roi_eligible'] = $request->exists('package_type') ? $reservesIncome : $request->boolean('roi_eligible');
        $data['level_commission_eligible'] = $request->exists('package_type') ? $reservesIncome : false;
        unset($data['image']);

        return $data;
    }

    private function storeImage(Request $request, Package $package): void
    {
        if (! $request->hasFile('image')) {
            return;
        }

        if ($package->image_path) {
            Storage::disk('local')->delete($package->image_path);
        }

        $path = $request->file('image')->store('packages', 'local');
        $package->forceFill(['image_path' => $path])->save();
    }

    private function imageResponse(Package $package, bool $activeOnly): BinaryFileResponse
    {
        abort_unless(
            (! $activeOnly || $package->is_active)
                && $package->image_path
                && Storage::disk('local')->exists($package->image_path),
            404
        );

        return response()->file(Storage::disk('local')->path($package->image_path));
    }
}
