<button {{
    $attributes->merge([
        'type' => 'submit',
        'class' => '
            inline-flex items-center justify-center
            rounded-lg
            px-3 py-2
            text-sm font-medium
            text-white
            bg-indigo-600
            hover:bg-indigo-700
            focus:outline-none
            focus:ring-2
            focus:ring-indigo-500
            transition
        '
    ])
}}>
    {{ $slot }}
</button>


