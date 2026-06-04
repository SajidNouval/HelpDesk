<button {{
    $attributes->merge([
        'type' => 'submit',
        'class' => '
            inline-flex items-center justify-center
            h-10 px-5
            rounded-xl
            bg-red-600
            hover:bg-red-700
            text-white
            text-sm font-medium
            focus:outline-none
            focus:ring-2
            focus:ring-red-500
            focus:ring-offset-2
            disabled:bg-gray-400
            disabled:cursor-not-allowed
            transition
        '
    ])
}}>
    {{ $slot }}
</button>


