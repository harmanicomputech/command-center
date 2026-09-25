{{-- A labelled text input with hint and validation error. --}}
@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null, 'id' => null, 'icon' => null, 'optional' => false])
@php
    $id ??= 'f-'.str_replace(['[', ']', '.'], '-', $name);
    $dotted = str_replace(['[', ']'], ['.', ''], $name);
    $error = $errors->first($dotted);
    $current = $type === 'password' ? null : old($dotted, $value);
@endphp
<div {{ $attributes->only('class')->merge(['class' => 'min-w-0']) }}>
    @if ($label)
        <label for="{{ $id }}" class="label">{{ $label }}@if ($optional) <span class="font-normal text-subtle">(optional)</span>@endif</label>
    @endif
    <div class="relative">
        @if ($icon)
            <x-icon :name="$icon" size="18" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-subtle" />
        @endif
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $current }}"
            {{ $attributes->except('class')->merge(['class' => 'input'.($icon ? ' pl-10' : '')]) }}
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif>
    </div>
    @if ($error)
        <p id="{{ $id }}-error" class="field-error"><x-icon name="circle-alert" />{{ $error }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-hint" class="hint">{{ $hint }}</p>
    @endif
</div>
