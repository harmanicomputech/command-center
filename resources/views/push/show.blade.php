@php
    $layout = auth()->user()->role->usesFieldApp() ? 'layouts.field' : 'layouts.app';
@endphp
<x-dynamic-component :component="$layout" title="Notifications">
    <x-page-header title="Notifications" description="Alerts on this device, even when the app is closed. Turn them on for each phone or computer you use." />

    @if (! $configured)
        <x-alert tone="warn" title="Notifications aren’t set up yet">An admin turns them on once, on the System page.</x-alert>
    @else
        <div x-data="pushPanel(@js($publicKey), @js(array_keys($topics)))" class="max-w-2xl space-y-6">
            <x-alert tone="info" x-show="! supported" x-cloak title="This browser can’t receive notifications">
                On iPhone, add the app to your Home Screen first (Share → Add to Home Screen, iOS 16.4 or later), then open it from there.
            </x-alert>
            <x-card title="This device" icon="bell" x-show="supported">
                <div class="flex flex-wrap items-center gap-3">
                    <x-badge x-bind:class="on ? 'badge-good' : ''" dot><span x-text="on ? 'On' : 'Off'">Off</span></x-badge>
                    <x-button type="button" icon="bell" x-show="! on" x-on:click="enable()" x-bind:disabled="busy">Turn on</x-button>
                    <x-button type="button" variant="secondary" x-show="on" x-cloak x-on:click="test()">Send a test</x-button>
                    <x-button type="button" variant="ghost" x-show="on" x-cloak x-on:click="disable()" x-bind:disabled="busy">Turn off</x-button>
                </div>
                <p class="mt-3 text-sm text-muted" x-show="message" x-text="message"></p>
            </x-card>
            <x-card title="What to send" description="Change these, then press Turn on (or turn off and on) to save them for this device." icon="sliders-horizontal">
                <div class="space-y-4">
                    @foreach ($topics as $key => $label)
                        <label class="flex cursor-pointer items-center justify-between gap-4">
                            <span class="text-sm font-medium">{{ $label }}</span>
                            <input type="checkbox" class="switch" value="{{ $key }}" x-model="topics">
                        </label>
                    @endforeach
                </div>
            </x-card>
        </div>
    @endif
</x-dynamic-component>
