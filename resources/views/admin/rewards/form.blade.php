@extends('admin.layouts.app')
@section('title', $campaign->exists ? 'Edit Reward Campaign' : 'New Reward Campaign')
@section('page-title', $campaign->exists ? 'Edit Reward Campaign' : 'New Reward Campaign')
@push('styles')
    @vite('resources/css/features/rewards.css')
@endpush
@section('content')
<div class="ds-catalog">
    <x-ui.page-header lead="Dates use Asia/Kolkata. The target is USDT team business, including the member and the full downline.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.rewards.index')" variant="secondary">All campaigns</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    @if ($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif
    @if ($campaign->exists && $campaign->rulesLocked())
        <x-ui.alert type="warning">A gift for this campaign is already committed. The target and dates cannot change.</x-ui.alert>
    @endif
    <x-ui.card>
        <form method="POST" enctype="multipart/form-data" action="{{ $campaign->exists ? route('admin.rewards.update', $campaign) : route('admin.rewards.store') }}">
            @csrf
            @if ($campaign->exists)
                @method('PUT')
            @endif
            <x-ui.input name="title" label="Campaign title" :value="old('title', $campaign->title)" required :error="$errors->first('title')" />
            <x-ui.textarea name="description" label="Campaign description" :error="$errors->first('description')">{{ old('description', $campaign->description) }}</x-ui.textarea>
            <x-ui.input name="gift_name" label="Reward gift name" :value="old('gift_name', $campaign->gift_name)" required :error="$errors->first('gift_name')" />
            <x-ui.textarea name="gift_description" label="Gift description" :error="$errors->first('gift_description')">{{ old('gift_description', $campaign->gift_description) }}</x-ui.textarea>
            <x-ui.input name="target_amount" label="Required team business (USDT)" :value="old('target_amount', $campaign->target_amount)" required :error="$errors->first('target_amount')" />
            <x-ui.input name="starts_on" type="date" label="Start date" :value="old('starts_on', $campaign->starts_on?->toDateString())" required :error="$errors->first('starts_on')" />
            <x-ui.input name="ends_on" type="date" label="End date" :value="old('ends_on', $campaign->ends_on?->toDateString())" required :error="$errors->first('ends_on')" />
            <x-ui.input name="stock_quantity" type="number" min="0" label="Gift quantity (leave blank for no limit)" :value="old('stock_quantity', $campaign->stock_quantity)" :error="$errors->first('stock_quantity')" />
            <label class="ds-pkg-check" style="display:flex;gap:8px;align-items:center;margin:12px 0;">
                <input type="checkbox" name="promote_on_login" value="1" @checked(old('promote_on_login', $campaign->promote_on_login ?? true))>
                <span>Show on member login popup when Active</span>
            </label>
            <x-ui.textarea name="terms" label="Terms and conditions" :error="$errors->first('terms')">{{ old('terms', $campaign->terms) }}</x-ui.textarea>
            <x-ui.textarea name="admin_notes" label="Internal admin notes" :error="$errors->first('admin_notes')">{{ old('admin_notes', $campaign->admin_notes) }}</x-ui.textarea>

            <x-ui.card title="Campaign Media" subtitle="Banner and optional video for the member popup. Uploading never changes qualification.">
                <div class="ds-dash-columns">
                    <div>
                        <h3 class="ds-section-title" style="margin:0 0 8px;font-size:14px;">Campaign Banner (Image)</h3>
                        @if ($campaign->exists && $campaign->gift_image_path)
                            <div class="ds-reward-media-preview">
                                <img src="{{ route('admin.rewards.image', $campaign) }}" alt="" class="ds-package-thumb">
                            </div>
                            <label class="ds-pkg-check" style="display:flex;gap:8px;align-items:center;margin:8px 0;">
                                <input type="checkbox" name="remove_image" value="1">
                                <span>Remove banner image</span>
                            </label>
                        @endif
                        <x-ui.input name="image" type="file" label="Upload / Replace banner" accept="image/jpeg,image/png,image/webp" :error="$errors->first('image')" />
                        <p class="ds-pkg-hint">JPEG, PNG or WebP. Max {{ (int) config('deltasupport.rewards.image_max_kb', 2048) }} KB.</p>
                    </div>
                    <div>
                        <h3 class="ds-section-title" style="margin:0 0 8px;font-size:14px;">Campaign Video (Optional)</h3>
                        @if ($campaign->exists && $campaign->video_path)
                            <div class="ds-reward-media-preview">
                                <video class="ds-reward-video-preview" controls preload="metadata" playsinline @if($campaign->gift_image_path) poster="{{ route('admin.rewards.image', $campaign) }}" @endif>
                                    <source src="{{ route('admin.rewards.video', $campaign) }}" type="video/mp4">
                                </video>
                            </div>
                            <label class="ds-pkg-check" style="display:flex;gap:8px;align-items:center;margin:8px 0;">
                                <input type="checkbox" name="remove_video" value="1">
                                <span>Remove video</span>
                            </label>
                        @endif
                        <x-ui.input name="video" type="file" label="Upload / Replace video" accept="video/mp4,video/webm" :error="$errors->first('video')" />
                        <p class="ds-pkg-hint">MP4 or WebM. Max {{ (int) config('deltasupport.rewards.video_max_kb', 204800) }} KB. Shown in the member popup when present.</p>
                    </div>
                </div>
                <x-ui.alert type="info">Deleting an image or video removes it permanently from the server when no other campaign references it.</x-ui.alert>
            </x-ui.card>

            <x-ui.button type="submit">{{ $campaign->exists ? 'Save changes' : 'Save draft' }}</x-ui.button>
        </form>
    </x-ui.card>
</div>
@endsection
