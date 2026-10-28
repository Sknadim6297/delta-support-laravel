@extends('admin.layouts.app')

@section('title', 'Branding')
@section('page-title', 'Branding')

@section('content')
<div class="ds-catalog">
    <x-ui.page-header
        kicker="Settings"
        title="Branding"
        lead="The public site and both panels use these colors and the current logo."
    />

    <div class="ds-brand-layout">
        <x-ui.card title="Current logo" :subtitle="$settings->company_name ?: 'Company name not set'">
            <div class="ds-brand-logo">
                <img id="brandLogoPreview" src="{{ $logoUrl }}" alt="Delta Support" data-original="{{ $logoUrl }}">
            </div>

            <form class="ds-brand-form" method="POST" action="{{ route('admin.settings.branding.logo.update') }}" enctype="multipart/form-data" id="brandLogoForm">
                @csrf
                <input class="ds-brand-file" type="file" id="brandLogoInput" name="logo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                @error('logo')<small>{{ $message }}</small>@enderror
                <div class="ds-brand-actions">
                    <button type="button" id="brandLogoChange">Change Logo</button>
                    <button type="submit" id="brandLogoSave" hidden>Save Logo</button>
                    <button type="button" id="brandLogoCancel" class="ds-btn-secondary" hidden>Cancel</button>
                </div>
            </form>

            @if ($settings->hasCustomLogo())
                <form class="ds-brand-form" method="POST" action="{{ route('admin.settings.branding.logo.destroy') }}">
                    @csrf
                    @method('DELETE')
                    <div class="ds-brand-actions">
                        <button type="submit" class="ds-btn-secondary">Remove Logo</button>
                    </div>
                </form>
            @endif
        </x-ui.card>

        <x-ui.card title="Live colors" subtitle="Taken from the design tokens the panels are using now.">
            <form class="ds-brand-form" method="POST" action="{{ route('admin.settings.branding.colors.update') }}" id="brandColorForm">
                @csrf
                @method('PUT')
                <div class="ds-swatches">
                    @foreach ($colors as $key => $value)
                        <div class="ds-swatch" data-brand-color>
                            <label class="ds-swatch-picker">
                                <span data-swatch style="background: {{ $value }}"></span>
                                <input type="color" value="{{ $value }}" aria-label="{{ \App\Support\BrandTokens::LABELS[$key] }}">
                            </label>
                            <label class="ds-swatch-copy">
                                <span>{{ \App\Support\BrandTokens::LABELS[$key] }}</span>
                                <input type="text" name="colors[{{ $key }}]" value="{{ old('colors.'.$key, $value) }}" maxlength="7" spellcheck="false" autocomplete="off" aria-label="{{ \App\Support\BrandTokens::LABELS[$key] }} HEX">
                                @error('colors.'.$key)<small>{{ $message }}</small>@enderror
                            </label>
                        </div>
                    @endforeach
                </div>
                <div class="ds-brand-actions">
                    <button type="submit">Save Colors</button>
                </div>
            </form>
            <form class="ds-brand-form" method="POST" action="{{ route('admin.settings.branding.colors.reset') }}">
                @csrf
                @method('DELETE')
                <div class="ds-brand-actions">
                    <button type="submit" class="ds-btn-secondary">Reset to Default</button>
                </div>
            </form>
        </x-ui.card>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function () {
        const input = document.getElementById('brandLogoInput');
        const preview = document.getElementById('brandLogoPreview');
        const change = document.getElementById('brandLogoChange');
        const save = document.getElementById('brandLogoSave');
        const cancel = document.getElementById('brandLogoCancel');
        let previewUrl = '';

        function clearPreview() {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = '';
            }
        }

        change.addEventListener('click', function () {
            input.click();
        });

        input.addEventListener('change', function () {
            const file = input.files && input.files[0];
            if (!file) {
                return;
            }
            clearPreview();
            previewUrl = URL.createObjectURL(file);
            preview.src = previewUrl;
            save.hidden = false;
            cancel.hidden = false;
        });

        cancel.addEventListener('click', function () {
            input.value = '';
            clearPreview();
            preview.src = preview.dataset.original;
            save.hidden = true;
            cancel.hidden = true;
        });

        document.querySelectorAll('[data-brand-color]').forEach(function (row) {
            const picker = row.querySelector('input[type="color"]');
            const hex = row.querySelector('input[type="text"]');
            const swatch = row.querySelector('[data-swatch]');

            function paint(value) {
                const normalized = (value || '').trim().toLowerCase();
                if (!/^#[0-9a-f]{6}$/.test(normalized)) {
                    return;
                }
                picker.value = normalized;
                hex.value = normalized;
                swatch.style.background = normalized;
            }

            picker.addEventListener('input', function () {
                paint(picker.value);
            });
            hex.addEventListener('input', function () {
                paint(hex.value);
            });
        });
    })();
</script>
@endsection
