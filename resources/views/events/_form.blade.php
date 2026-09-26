{{-- Event fields. Expects $lgaOptions, $wardOptions; optional $event. --}}
@php
    $start = isset($event) ? $event->starts_at->copy()->setTimezone(\App\Support\Time::zone()) : null;
    $lgaNow = (string) old('lga_id', $event->lga_id ?? (auth()->user()->lga_id ?? array_key_first($lgaOptions)));
    $wardNow = (string) old('ward_id', $event->ward_id ?? auth()->user()->ward_id ?? '');
@endphp
<div class="space-y-5" x-data="{ lga: @js($lgaNow), ward: @js($wardNow), wards: @js($wardOptions) }">
    <x-input name="title" label="Title" :value="$event->title ?? null" placeholder="e.g. Ward executive meeting" required />
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-select name="type" label="Type" :options="config('structure.event_types')" :value="$event->type ?? 'meeting'" required />
        <x-input name="venue" label="Venue" :value="$event->venue ?? null" optional />
    </div>
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <label class="label" for="ev-lga">LGA</label>
            <select id="ev-lga" name="lga_id" class="input" x-model="lga" x-on:change="ward = ''">
                @foreach ($lgaOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="label" for="ev-ward">Ward</label>
            <select id="ev-ward" name="ward_id" class="input" x-model="ward">
                @if (auth()->user()->atLeast(\App\Enums\UserRole::LgaLeader))<option value="">Whole LGA</option>@endif
                <template x-for="w in wards.filter(w => String(w.lga_id) === lga)" :key="w.id">
                    <option :value="String(w.id)" x-text="w.name" :selected="String(w.id) === ward"></option>
                </template>
            </select>
            @error('ward_id')<p class="field-error"><x-icon name="circle-alert" />{{ $message }}</p>@enderror
        </div>
    </div>
    <div class="grid grid-cols-2 gap-5 sm:grid-cols-3">
        <x-input name="date" type="date" label="Date" :value="$start?->format('Y-m-d') ?? \App\Support\Time::now()->addDay()->format('Y-m-d')" required />
        <x-input name="time" type="time" label="Time" :value="$start?->format('H:i') ?? '10:00'" required />
        <x-input name="expected" type="number" label="Expected" :value="$event->expected ?? null" min="0" optional class="col-span-2 sm:col-span-1" />
    </div>
</div>
