@props(['value', 'limit' => 30])

<span title="{{ $value }}" {{ $attributes->merge(['class' => 'inline-block max-w-full overflow-hidden text-ellipsis whitespace-nowrap align-bottom']) }}>
    {{ \Illuminate\Support\Str::limit($value, $limit) }}
</span>
