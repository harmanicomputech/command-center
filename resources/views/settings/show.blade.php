<x-layouts.app title="Settings">
    <x-page-header title="Settings" eyebrow="Admin" description="Placeholders stand in for decisions the campaign hasn’t made yet. Everything that uses them updates as soon as you save." />

    <div class="space-y-6">
        @foreach ($groups as $key => $group)
            <x-card :title="$group['title']" :description="$group['description']" id="{{ $key }}">
                <form method="post" action="{{ route('settings.update', $key) }}">
                    @csrf @method('put')
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        @foreach ($group['fields'] as $field => $definition)
                            @php
                                $input = str_replace('.', '__', $field);
                                $numeric = in_array($definition['type'], ['int', 'number'], true);
                            @endphp
                            <div>
                                <label for="s-{{ $input }}" class="label">{{ $definition['label'] }}</label>
                                <div class="relative">
                                    <input id="s-{{ $input }}" name="{{ $input }}" class="input {{ isset($definition['suffix']) ? 'pr-20' : '' }} {{ $numeric ? 'num' : '' }}"
                                        type="{{ $numeric ? 'number' : 'text' }}" @if ($numeric) inputmode="decimal" step="{{ $definition['type'] === 'int' ? 1 : 'any' }}" min="{{ $definition['min'] ?? 0 }}" max="{{ $definition['max'] ?? '' }}" @endif
                                        value="{{ old($input, \App\Support\Settings::get($field)) }}" placeholder="{{ $definition['default'] }}" @if ($errors->has($input)) aria-invalid="true" @endif>
                                    @isset($definition['suffix'])<span class="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-sm text-subtle">{{ $definition['suffix'] }}</span>@endisset
                                </div>
                                @error($input)
                                    <p class="field-error"><x-icon name="circle-alert" />{{ $message }}</p>
                                @else
                                    @if ($definition['help'] ?? null)<p class="hint">{{ $definition['help'] }}</p>@endif
                                @enderror
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-6 flex justify-end"><x-button icon="check">Save {{ strtolower($group['title']) }}</x-button></div>
                </form>
            </x-card>
        @endforeach
    </div>
</x-layouts.app>
