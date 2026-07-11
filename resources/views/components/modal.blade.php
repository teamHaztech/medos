@props([
    'show',                 // Alpine boolean expression that controls visibility, e.g. "showEditModal"
    'title' => null,        // static header title
    'titleExpr' => null,    // OR an Alpine expression for a dynamic title (x-text)
    'max' => 'lg',          // sm | md | lg | xl | 2xl | 3xl
    'bodyClass' => 'p-6',   // set to '' when the slot content brings its own padding
])
@php
    $widths = [
        'sm' => 'max-w-sm', 'md' => 'max-w-md', 'lg' => 'max-w-lg',
        'xl' => 'max-w-xl', '2xl' => 'max-w-2xl', '3xl' => 'max-w-3xl',
    ];
    $mw = $widths[$max] ?? 'max-w-lg';
    $hasHeader = $title !== null || $titleExpr !== null;
@endphp
<div x-show="{{ $show }}" x-transition.opacity style="display:none; background:rgba(0,0,0,0.5)"
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     @keydown.escape.window="{{ $show }} = false">
    <div @click.outside="{{ $show }} = false"
         class="relative bg-white rounded-2xl shadow-xl w-full {{ $mw }} overflow-y-auto" style="max-height:90vh">
        @if($hasHeader)
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
            <h3 class="text-lg font-bold text-slate-900" @if($titleExpr) x-text="{{ $titleExpr }}" @endif>{{ $title }}</h3>
            <button type="button" @click="{{ $show }} = false" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">&times;</button>
        </div>
        @endif
        <div class="{{ $bodyClass }}">
            {{ $slot }}
        </div>
    </div>
</div>
