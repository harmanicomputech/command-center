{{-- Influence note fields. Expects $wards [id => name]; optional $influencer, $wardId. --}}
<div class="space-y-5">
    <x-select name="ward_id" label="Ward" :options="$wards" :value="$influencer->ward_id ?? ($wardId ?? null)" placeholder="Choose the ward" required />
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-select name="kind" label="Kind" :options="config('structure.influencer_kinds')" :value="$influencer->kind ?? null" required />
        <x-select name="relationship" label="Our relationship" :options="collect(config('structure.relationships'))->map(fn ($r) => $r['label'])->all()" :value="$influencer->relationship ?? 'unknown'" required />
    </div>
    <x-input name="name" label="Name" :value="$influencer->name ?? null" placeholder="e.g. the market women’s association" required />
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-input name="contact_name" label="Contact person" :value="$influencer->contact_name ?? null" optional />
        <x-input name="contact_phone" type="tel" label="Contact phone" :value="isset($influencer) && $influencer->contact_phone ? \App\Support\Phone::local($influencer->contact_phone) : null" optional />
    </div>
    <x-textarea name="notes" label="Notes" :value="$influencer->notes ?? null" optional rows="3" />
</div>
