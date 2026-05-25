<button {{
    $attributes->merge([
        'type' => 'submit',
        'class' => '
            inline-flex items-center justify-center
            rounded-lg
            px-3 py-2
            text-sm font-medium
            text-white
            bg-red-600
            hover:bg-red-700
            focus:outline-none
            focus:ring-2
            focus:ring-red-500
            transition
        '
    ])
}}>
    {{ $slot }}
</button>

