<button {{
    $attributes->merge([
        'type' => 'submit',
        'class' => '
            inline-flex items-center justify-center
            rounded-lg
            px-4 py-3
            text-base font-semibold
            text-white
            bg-red-600
            hover:bg-red-700
            focus:outline-none
            focus:ring-2
            focus:ring-red-500
            focus:ring-offset-2
            disabled:bg-gray-400
            disabled:cursor-not-allowed
            transition-colors
            duration-200
        '
    ])
}}>
    {{ $slot }}
</button>


