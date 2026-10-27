@props(['items' => []])

<nav {{ $attributes->merge(['class' => 'ds-crumb']) }} aria-label="Breadcrumb">
    <ol>
        @foreach ($items as $item)
            <li>
                @if (! empty($item['href']) && ! $loop->last)
                    <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                @else
                    <span @if ($loop->last) aria-current="page" @endif>{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
