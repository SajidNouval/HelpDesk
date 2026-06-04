@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'block w-full px-4 h-11 border border-gray-300 bg-white text-sm text-gray-900 placeholder-gray-400 rounded-xl focus:border-red-500 focus:ring-1 focus:ring-red-500 focus:outline-none transition-colors duration-200 disabled:bg-gray-100 disabled:text-gray-500 disabled:cursor-not-allowed']) !!}>