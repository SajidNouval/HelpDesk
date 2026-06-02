<button {{
    $attributes->merge([
        'type' => 'button',
        'class' => '
            inline-flex items-center justify-center
            rounded-lg
            px-3 py-2
            text-sm font-medium
            text-gray-700
            bg-white
            border border-gray-300
            hover:bg-gray-50
            focus:outline-none
            focus:ring-2
            focus:ring-red-500
            transition
        '
    ])
}}>
    {{ $slot }}
</button>

