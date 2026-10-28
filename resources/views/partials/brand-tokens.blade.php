@php($brand = \App\Support\BrandTokens::palette())
<style id="ds-brand-tokens">
:root {
    --ds-primary: {{ $brand['primary'] }};
    --ds-primary-hover: {{ $brand['primary_hover'] }};
    --ds-foreground: {{ $brand['navy'] }};
    --ds-canvas: {{ $brand['canvas'] }};
    --ds-border: {{ $brand['border'] }};
    --ds-focus: {{ \App\Support\BrandTokens::focus($brand['primary']) }};
    --color-brand: {{ $brand['primary'] }};
    --color-brand-dark: {{ $brand['primary_hover'] }};
    --color-navy: {{ $brand['navy'] }};
    --color-canvas: {{ $brand['canvas'] }};
    --color-line: {{ $brand['border'] }};
    --color-brand-500: {{ $brand['primary'] }};
    --color-brand-700: {{ $brand['primary_hover'] }};
    --color-navy-900: {{ $brand['navy'] }};
    --primary: {{ $brand['primary'] }};
    --navy: {{ $brand['navy'] }};
    --primary-dark: {{ $brand['primary_hover'] }};
    --login-primary: {{ $brand['primary'] }};
    --login-primary-dark: {{ $brand['primary_hover'] }};
}
</style>
