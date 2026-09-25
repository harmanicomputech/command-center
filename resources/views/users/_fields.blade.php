{{-- Account fields; which ones show depends on the role. Expects $user (nullable), $wards, $lgas. --}}
@php
    $roleNow = old('role', $user?->role->value ?? 'agent');
@endphp
<div x-data="{ role: @js($roleNow) }" class="space-y-5">
    <x-input name="name" label="Full name" :value="$user?->name" required autocomplete="off" />
    <x-select name="role" label="Role" :value="$roleNow" x-model="role"
        :options="collect(\App\Enums\UserRole::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label().' — '.$r->description()])->all()" />
    <div x-show="role !== 'agent'" x-cloak>
        <x-input name="email" type="email" label="Email" :value="$user?->email" hint="Staff sign in with this." autocomplete="off" />
    </div>
    <x-input name="phone" type="tel" label="Phone number" :value="$user?->phone ? \App\Support\Phone::local($user->phone) : null" inputmode="tel" placeholder="0803 123 4567"
        x-bind:required="role === 'agent'" hint="Agents sign in with this. Optional for staff." />
    <div x-show="role === 'lga_leader'" x-cloak>
        <x-select name="lga_id" label="LGA" :options="$lgas" :value="$user?->lga_id" placeholder="Choose the LGA" />
    </div>
    <div x-show="role === 'ward_coordinator' || role === 'agent'" x-cloak>
        <x-select name="ward_id" label="Ward" :options="$wards" :value="$user?->ward_id" placeholder="Choose the ward" />
    </div>
    <div>
        <x-input name="password" type="password" label="Password or PIN" autocomplete="new-password"
            :hint="$user ? 'Leave empty to keep the current one. Agents: a 4–6 digit PIN. Staff: 10+ characters.' : 'Agents: a 4–6 digit PIN. Staff: at least 10 characters.'" />
    </div>
</div>
