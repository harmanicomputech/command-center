{{-- A labelled select. $options: [value => label] or [group => [value => label]]. --}}
@props(['name', 'label' => null, 'options' => [], 'value' => null, 'hint' => null, 'id' => null, 'placeholder' => null, 'optional' => false])
@php
    $id ??= 'f-'.str_replace(['[', ']', '.'], '-', $name);
    $error = $errors->first($name);
    $current = (string) old($name, $value);
@endphp
<div {{ $attributes->only('class')->merge(['class' => 'min-w-0']) }}>
    @if ($label)
        <label for="{{ $id }}" class="label">{{ $label }}@if ($optional) <span class="font-normal text-subtle">(optional)</span>@endif</label>
    @endif
    <select id="{{ $id }}" name="{{ $name }}" {{ $attributes->except('class')->merge(['class' => 'input']) }} @if ($error) aria-invalid="true" @endif>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $key => $option)
            @if (is_array($option))
                <optgroup label="{{ $key }}">
                    @foreach ($option as $optionValue => $optionLabel)
                        <option value="{{ $optionValue }}" @selected($current === (string) $optionValue)>{{ $optionLabel }}</option>
                    @endforeach
                </optgroup>
            @else
                <option value="{{ $key }}" @selected($current === (string) $key)>{{ $option }}</option>
            @endif
        @endforeach
    </select>
    @if ($error)
        <p class="field-error"><x-icon name="circle-alert" />{{ $error }}</p>
    @elseif ($hint)
        <p class="hint">{{ $hint }}</p>
    @endif
</div>
