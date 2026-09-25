{{-- Big one-tap choices (radio buttons styled as segments), for field forms. --}}
@props(['name', 'label' => null, 'options' => [], 'value' => null, 'cols' => 3, 'required' => false, 'hint' => null])
@php
    $error = $errors->first($name);
    $current = (string) old($name, $value);
@endphp
<fieldset {{ $attributes->merge(['class' => 'min-w-0']) }}>
    @if ($label)<legend class="label">{{ $label }}</legend>@endif
    <div class="segmented" style="--cols: {{ $cols }}">
        @foreach ($options as $key => $option)
            <label>
                <input type="radio" name="{{ $name }}" value="{{ $key }}" @checked($current === (string) $key) @required($required)>
                {{ $option }}
            </label>
        @endforeach
    </div>
    @if ($error)
        <p class="field-error"><x-icon name="circle-alert" />{{ $error }}</p>
    @elseif ($hint)
        <p class="hint">{{ $hint }}</p>
    @endif
</fieldset>
