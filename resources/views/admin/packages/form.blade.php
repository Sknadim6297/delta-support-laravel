@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl">
    <header class="mb-5">
        <h1 class="ds-page-title">{{ $package->exists ? 'Edit Package' : 'Add Package' }}</h1>
        <p class="ds-page-copy">USD price is stored with the package. Earlier purchases keep the price saved at purchase time.</p>
    </header>
    <form method="POST" action="{{ $package->exists ? route('admin.packages.update', $package) : route('admin.packages.store') }}" enctype="multipart/form-data" class="ds-card">
        @csrf
        @if($package->exists) @method('PUT') @endif
        <div class="ds-form ds-form-grid">
            <label class="ds-field"><span>Code</span><input name="code" value="{{ old('code', $package->code) }}" required></label>
            <label class="ds-field"><span>Name</span><input name="name" value="{{ old('name', $package->name) }}" required></label>
            <label class="ds-field"><span>USD price</span><input name="price_usd" value="{{ old('price_usd', $package->price_usd) }}" required inputmode="decimal"></label>
            <label class="ds-field"><span>Sort order</span><input type="number" name="sort_order" value="{{ old('sort_order', $package->sort_order ?? 0) }}" required></label>
            <label class="ds-field ds-span-2"><span>Description</span><textarea name="description" rows="3">{{ old('description', $package->description) }}</textarea></label>
            <label class="ds-field"><span>Tour title</span><input name="tour_title" value="{{ old('tour_title', $package->tour_title) }}"></label>
            <label class="ds-field"><span>Duration</span><input name="duration" value="{{ old('duration', $package->duration) }}"></label>
            <label class="ds-field ds-span-2"><span>Tour details</span><textarea name="tour_details" rows="3">{{ old('tour_details', $package->tour_details) }}</textarea></label>
            <label class="ds-field ds-span-2"><span>Benefits</span><textarea name="benefits" rows="3">{{ old('benefits', $package->benefits) }}</textarea></label>
            <label class="ds-field ds-span-2"><span>Image</span><input type="file" name="image" accept="image/*" class="h-auto py-2"></label>
            <label class="flex items-center gap-2 text-sm font-semibold text-navy-900"><input type="checkbox" name="is_active" value="1" class="h-4 w-4" @checked(old('is_active', $package->is_active))> Active</label>
            <label class="flex items-center gap-2 text-sm font-semibold text-navy-900"><input type="checkbox" name="roi_eligible" value="1" class="h-4 w-4" @checked(old('roi_eligible', $package->roi_eligible))> ROI eligibility metadata only. This does not credit ROI.</label>
            @if($errors->any())<div class="ds-error ds-span-2">{{ $errors->first() }}</div>@endif
        </div>
        <div class="ds-actions">
            <button class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white" type="submit">Save package</button>
            <a href="{{ route('admin.packages.index') }}" class="text-sm font-semibold text-navy-900">Cancel</a>
        </div>
    </form>
</div>
@endsection
