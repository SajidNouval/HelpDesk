@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border border-gray-300 bg-white text-sm text-gray-700 placeholder:text-gray-400 rounded-xl px-4 py-3 h-11 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition']) !!}>