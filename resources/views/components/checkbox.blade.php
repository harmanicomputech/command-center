@props(['name', 'label', 'description' => null, 'checked' => false, 'value' => '1', 'switch' => false, 'id' => null])
@php($id ??= 'f-'.str_replace(['[', ']', '.'], '-', $name).($switch ? '-s' : ''))
<div {{ $attributes->only('class')->merge(['class' => 'min-w-0']) }}>
    <label for="{{ $id }}" class="flex cursor-pointer items-start gap-3">
        <input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="{{ $value }}" class="{{ $switch ? 'switch' : 'checkbox' }} mt-0.5" @checked(old($name, $checked)) {{ $attributes->except('class') }}>
        <span class="min-w-0">
            <span class="block text-sm font-semibold">{{ $label }}</span>
            @if ($description)<span class="mt-0.5 block text-sm text-muted">{{ $description }}</span>@endif
        </span>
    </label>
    @error($name)<p class="field-error"><x-icon name="circle-alert" />{{ $message }}</p>@enderror
</div>
