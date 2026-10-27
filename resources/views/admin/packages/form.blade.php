@extends('admin.layouts.app')
@section('content')
<div>
    <header>
        <h1>{{ $package->exists ? 'Edit Package' : 'Add Package' }}</h1>
        <p>New packages are priced in USDT. Saving an existing package keeps its stored denomination and does not convert the amount. Earlier purchases keep their own snapshot. ROI and level commission stay inactive.</p>
    </header>
    <form method="POST" action="{{ $package->exists ? route('admin.packages.update', $package) : route('admin.packages.store') }}" enctype="multipart/form-data">
        @csrf
        @if($package->exists) @method('PUT') @endif
        <div>
            <label><span>Package code</span><input name="code" value="{{ old('code', $package->code) }}" required maxlength="32"></label>
            <label><span>Package name</span><input name="name" value="{{ old('name', $package->name) }}" required maxlength="120"></label>
            <label>
                <span>Package type</span>
                <select name="package_type">
                    @foreach(\App\Models\Package::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(old('package_type', $package->package_type ?: \App\Models\Package::TYPE_TOUR) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label><span>{{ $package->exists ? 'Stored price ('.($package->denomination ?: 'USD').')' : 'Package price (USDT)' }}</span><input name="price_usd" value="{{ old('price_usd', $package->price_usd) }}" required inputmode="decimal" autocomplete="off"></label>
            <label><span>Display order</span><input type="number" name="sort_order" value="{{ old('sort_order', $package->sort_order ?? 0) }}" required min="0"></label>
            <label><span>Duration (days / nights)</span><input name="duration" value="{{ old('duration', $package->duration) }}" maxlength="80" placeholder="Entered by admin"></label>
            <label><span>Short description</span><textarea name="description" rows="3" maxlength="500">{{ old('description', $package->description) }}</textarea></label>
            <label><span>Full description</span><textarea name="full_description" rows="5" maxlength="10000">{{ old('full_description', $package->full_description) }}</textarea></label>
            <label><span>Tour title</span><input name="tour_title" value="{{ old('tour_title', $package->tour_title) }}" maxlength="160"></label>
            <label><span>Tour destinations</span><textarea name="destinations" rows="3" maxlength="2000">{{ old('destinations', $package->destinations) }}</textarea></label>
            <label><span>Tour details</span><textarea name="tour_details" rows="4">{{ old('tour_details', $package->tour_details) }}</textarea></label>
            <label><span>Package benefits</span><textarea name="benefits" rows="4">{{ old('benefits', $package->benefits) }}</textarea></label>
            <label><span>Inclusions</span><textarea name="inclusions" rows="3">{{ old('inclusions', $package->inclusions) }}</textarea></label>
            <label><span>Exclusions</span><textarea name="exclusions" rows="3">{{ old('exclusions', $package->exclusions) }}</textarea></label>
            <label><span>Package image</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
            <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $package->is_active))> Active</label>
            <p>Tour + ROI and Premium types only reserve future ROI and level-commission metadata. They do not credit income.</p>
            @if($errors->any())<div>{{ $errors->first() }}</div>@endif
        </div>
        <div>
            <button type="submit">Save package</button>
            <a href="{{ route('admin.packages.index') }}">Cancel</a>
        </div>
    </form>
</div>
@endsection
