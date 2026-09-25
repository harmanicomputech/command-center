@props(['name', 'label' => null, 'value' => null, 'hint' => null, 'id' => null, 'optional' => false])
@php
    $id ??= 'f-'.str_replace(['[', ']', '.'], '-', $name);
    $error = $errors->first($name);
@endphp
<div {{ $attributes->only('class')->merge(['class' => 'min-w-0']) }}>
    @if ($label)
        <label for="{{ $id }}" class="label">{{ $label }}@if ($optional) <span class="font-normal text-subtle">(optional)</span>@endif</label>
    @endif
    <textarea id="{{ $id }}" name="{{ $name }}" {{ $attributes->except('class')->merge(['class' => 'input', 'rows' => 4]) }} @if ($error) aria-invalid="true" @endif>{{ old($name, $value) }}</textarea>
    @if ($error)
        <p class="field-error"><x-icon name="circle-alert" />{{ $error }}</p>
    @elseif ($hint)
        <p class="hint">{{ $hint }}</p>
    @endif
</div>
