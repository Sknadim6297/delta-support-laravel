<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PackageController extends Controller
{
    public function index(Request $request): View
    {
        $packages = Package::query()
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

        return redirect()->route('admin.packages.index')->with('success', 'Package updated. Earlier purchases keep their saved price.');
    }

    public function image(Package $package)
    {
        abort_unless($package->image_path && Storage::disk('local')->exists($package->image_path), 404);

        return response()->file(Storage::disk('local')->path($package->image_path));
    }

    private function validated(Request $request, ?Package $package = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('packages', 'code')->ignore($package)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price_usd' => ['required', 'regex:/^\d{1,12}(\.\d{1,4})?$/'],
            'tour_title' => ['nullable', 'string', 'max:160'],
            'tour_details' => ['nullable', 'string', 'max:5000'],
            'duration' => ['nullable', 'string', 'max:80'],
            'benefits' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'max:2048'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
            'roi_eligible' => ['nullable', 'boolean'],
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active');
        $data['roi_eligible'] = $request->boolean('roi_eligible');
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
}
