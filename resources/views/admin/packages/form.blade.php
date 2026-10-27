@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl">
    <header class="mb-5">
        <h1 class="ds-page-title">{{ $package->exists ? 'Edit Package' : 'Add Package' }}</h1>
        <p class="ds-page-copy">Set the USD price and tour details here. Earlier purchases keep the price and tour snapshot saved at purchase time. ROI and level commission stay inactive.</p>
    </header>
    <form method="POST" action="{{ $package->exists ? route('admin.packages.update', $package) : route('admin.packages.store') }}" enctype="multipart/form-data" class="ds-card">
        @csrf
        @if($package->exists) @method('PUT') @endif
        <div class="ds-form ds-form-grid">
            <label class="ds-field"><span>Package code</span><input name="code" value="{{ old('code', $package->code) }}" required maxlength="32"></label>
            <label class="ds-field"><span>Package name</span><input name="name" value="{{ old('name', $package->name) }}" required maxlength="120"></label>
            <label class="ds-field">
                <span>Package type</span>
                <select name="package_type">
                    @foreach(\App\Models\Package::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(old('package_type', $package->package_type ?: \App\Models\Package::TYPE_TOUR) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="ds-field"><span>USD price</span><input name="price_usd" value="{{ old('price_usd', $package->price_usd) }}" required inputmode="decimal" autocomplete="off"></label>
            <label class="ds-field"><span>Display order</span><input type="number" name="sort_order" value="{{ old('sort_order', $package->sort_order ?? 0) }}" required min="0"></label>
            <label class="ds-field"><span>Duration (days / nights)</span><input name="duration" value="{{ old('duration', $package->duration) }}" maxlength="80" placeholder="Entered by admin"></label>
            <label class="ds-field ds-span-2"><span>Short description</span><textarea name="description" rows="3" maxlength="500">{{ old('description', $package->description) }}</textarea></label>
            <label class="ds-field ds-span-2"><span>Full description</span><textarea name="full_description" rows="5" maxlength="10000">{{ old('full_description', $package->full_description) }}</textarea></label>
            <label class="ds-field"><span>Tour title</span><input name="tour_title" value="{{ old('tour_title', $package->tour_title) }}" maxlength="160"></label>
            <label class="ds-field ds-span-2"><span>Tour destinations</span><textarea name="destinations" rows="3" maxlength="2000">{{ old('destinations', $package->destinations) }}</textarea></label>
            <label class="ds-field ds-span-2"><span>Tour details</span><textarea name="tour_details" rows="4">{{ old('tour_details', $package->tour_details) }}</textarea></label>
            <label class="ds-field ds-span-2"><span>Package benefits</span><textarea name="benefits" rows="4">{{ old('benefits', $package->benefits) }}</textarea></label>
            <label class="ds-field ds-span-2"><span>Inclusions</span><textarea name="inclusions" rows="3">{{ old('inclusions', $package->inclusions) }}</textarea></label>
            <label class="ds-field ds-span-2"><span>Exclusions</span><textarea name="exclusions" rows="3">{{ old('exclusions', $package->exclusions) }}</textarea></label>
            <label class="ds-field ds-span-2"><span>Package image</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="h-auto py-2"></label>
            <label class="flex items-center gap-2 text-sm font-semibold text-navy-900"><input type="checkbox" name="is_active" value="1" class="h-4 w-4" @checked(old('is_active', $package->is_active))> Active</label>
            <p class="ds-span-2 text-sm text-slate-600">Tour + ROI and Premium types only reserve future ROI and level-commission metadata. They do not credit income.</p>
            @if($errors->any())<div class="ds-error ds-span-2">{{ $errors->first() }}</div>@endif
        </div>
        <div class="ds-actions">
            <button class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white" type="submit">Save package</button>
            <a href="{{ route('admin.packages.index') }}" class="text-sm font-semibold text-navy-900">Cancel</a>
        </div>
    </form>
</div>
@endsection
