{{-- The media team's "add a report" form (also used on a narrative's page). --}}
<form method="post" action="{{ route('narratives.reports.store') }}" enctype="multipart/form-data" class="space-y-4">
    @csrf
    @if ($narrativeId ?? null)<input type="hidden" name="narrative_id" value="{{ $narrativeId }}">@endif
    <x-textarea name="summary" label="What are people saying?" rows="3" placeholder="e.g. A WhatsApp voice note claims the candidate will close the Abakaliki rice mill" required />
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-select name="source" label="Where was it seen?" :options="config('messaging.sources')" />
        <x-select name="topic" label="Topic" :options="config('messaging.narrative_topics')" />
    </div>
    <x-segmented name="tone" label="Tone" :options="collect(config('messaging.narrative_tones'))->map(fn ($t) => $t['label'])->all()" value="negative" />
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-select name="lga_id" label="LGA" :options="$lgas->pluck('name', 'id')->all()" :placeholder="auth()->user()->role->isStatewide() ? 'Statewide' : null" optional />
        <x-select name="ward_id" label="Ward" :options="$wards->mapWithKeys(fn ($w) => [$w->id => $w->name.' ('.$w->lga->name.')'])->all()" placeholder="Not specific" optional />
    </div>
    <x-input name="link" type="url" label="Link" placeholder="https://" optional />
    <div>
        <label for="n-photo" class="label">Screenshot or photo <span class="font-normal text-subtle">(optional)</span></label>
        <input id="n-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="input file:mr-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-sm file:font-medium">
    </div>
    <x-button icon="plus" class="w-full sm:w-auto">Add report</x-button>
</form>
