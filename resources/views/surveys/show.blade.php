@php
    $small = config('surveys.small_sample');
@endphp
<x-layouts.app :title="$survey->title" wide>
    <x-page-header :title="$survey->title" eyebrow="Survey" :back="route('surveys')" :description="$survey->targetLabel().' · '.collect($survey->channels)->map(fn ($c) => config('surveys.channels.'.$c))->implode(', ')">
        <x-slot:actions>
            <x-badge :tone="['live' => 'good', 'draft' => null, 'closed' => 'info'][$survey->status]" dot>{{ ucfirst($survey->status) }}</x-badge>
            @if ($canEdit)
                @if ($survey->status === 'draft')
                    <x-button :href="route('surveys.edit', $survey)" variant="secondary" icon="pencil">Edit</x-button>
                    <form method="post" action="{{ route('surveys.status', $survey) }}">@csrf<input type="hidden" name="status" value="live"><x-button icon="send">Launch</x-button></form>
                @elseif ($survey->status === 'live')
                    <form method="post" action="{{ route('surveys.status', $survey) }}" x-data x-on:submit="if (! confirm('Close this survey? No more answers will be accepted.')) $event.preventDefault()">@csrf<input type="hidden" name="status" value="closed"><x-button variant="secondary" icon="lock">Close</x-button></form>
                @endif
            @endif
            @if (auth()->user()->isAdmin())<x-button :href="route('surveys.export', $survey)" variant="ghost" icon="download">CSV</x-button>@endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="min-w-0 space-y-6">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="card col-span-2 p-5 sm:col-span-1"><p class="text-sm text-muted">Responses</p><p class="num mt-1 text-3xl font-bold">{{ number_format($summary['n']) }}</p></div>
                @foreach (['field' => 'Field', 'web' => 'Web', 'ussd' => 'USSD', 'sms' => 'SMS'] as $key => $label)
                    @if (isset($summary['byChannel'][$key]))
                        <div class="card p-5"><p class="text-sm text-muted">{{ $label }}</p><p class="num mt-1 text-xl font-semibold">{{ number_format($summary['byChannel'][$key]) }}</p></div>
                    @endif
                @endforeach
            </div>

            @foreach ($summary['questions'] as $i => $row)
                <x-card :title="($i + 1).'. '.$row['question']->prompt" :description="config('surveys.types.'.$row['question']->type).' · n = '.number_format($row['n'])">
                    @if ($row['n'] > 0 && $row['n'] < $small)
                        <x-slot:actions><x-badge tone="warn">Small sample</x-badge></x-slot:actions>
                    @endif
                    @if ($row['question']->type === 'text')
                        @forelse ($row['texts'] as $text)
                            <p class="border-b border-line py-2 text-sm last:border-0">“{{ $text }}”</p>
                        @empty
                            <p class="text-sm text-muted">No answers yet.</p>
                        @endforelse
                    @elseif ($row['n'] === 0)
                        <p class="text-sm text-muted">No answers yet.</p>
                    @else
                        @if ($row['average'] !== null)<p class="mb-3 text-sm">Average <span class="num text-lg font-bold">{{ $row['average'] }}</span> <span class="text-muted">out of 5</span></p>@endif
                        @include('surveys._bars', ['counts' => $row['counts'], 'choices' => $row['question']->choices(), 'n' => $row['n']])
                    @endif
                </x-card>
            @endforeach

            @if ($breakdownQuestion)
                <x-card title="Compare groups" :description="$breakdownQuestion->prompt">
                    <x-slot:actions>
                        <form method="get" class="flex flex-wrap gap-2">
                            <select name="question" class="input w-56" aria-label="Question" onchange="this.form.submit()">
                                @foreach ($survey->questions->where('type', '!=', 'text') as $q)<option value="{{ $q->id }}" @selected($q->id === $breakdownQuestion->id)>{{ \Illuminate\Support\Str::limit($q->prompt, 40) }}</option>@endforeach
                            </select>
                            <select name="by" class="input w-40" aria-label="By" onchange="this.form.submit()">
                                @foreach (\App\Services\SurveyResults::BREAKDOWNS as $key => $label)<option value="{{ $key }}" @selected($breakdownBy === $key)>By {{ $key === 'lga_id' ? $label : strtolower($label) }}</option>@endforeach
                            </select>
                        </form>
                    </x-slot:actions>
                    @forelse ($breakdown as $group)
                        <div class="border-b border-line py-4 first:pt-0 last:border-0 last:pb-0">
                            <p class="mb-2 flex items-center gap-2 text-sm font-semibold">{{ $group['label'] }} <span class="num font-normal text-muted">n = {{ $group['n'] }}</span>@if ($group['n'] < $small)<x-badge tone="warn">Small sample</x-badge>@endif</p>
                            @include('surveys._bars', ['counts' => $group['counts'], 'choices' => $breakdownQuestion->choices(), 'n' => $group['n']])
                        </div>
                    @empty
                        <p class="text-sm text-muted">No answers yet.</p>
                    @endforelse
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            @if ($survey->hasChannel('web'))
                <x-card title="Web link" icon="external-link">
                    <div x-data="copy(@js($webUrl))" class="flex gap-2">
                        <input readonly value="{{ $webUrl }}" class="input min-w-0 flex-1 text-xs" aria-label="Web link" x-on:focus="$el.select()">
                        <button type="button" class="btn btn-secondary btn-icon" x-on:click="copy()" aria-label="Copy"><x-icon name="copy" /></button>
                    </div>
                    <p class="hint">Share it anywhere. One answer per phone (and per browser).</p>
                </x-card>
            @endif
            @if ($survey->hasChannel('sms'))
                <x-card title="SMS and USSD" icon="smartphone">
                    <p class="text-sm text-muted">In Africa’s Talking, point the poll’s USSD code and the SMS shortcode’s inbound messages at these URLs.</p>
                    <p class="label mt-3">USSD callback</p>
                    <input readonly value="{{ $poll['ussd'] }}" class="input text-xs" aria-label="USSD callback" x-data x-on:focus="$el.select()">
                    <p class="label mt-3">SMS inbound</p>
                    <input readonly value="{{ $poll['sms'] }}" class="input text-xs" aria-label="SMS callback" x-data x-on:focus="$el.select()">
                    <p class="hint">{{ $survey->sms_keyword ? 'Texting “'.$survey->sms_keyword.' 1” answers the first question.' : 'Set an SMS keyword to take text answers.' }}</p>
                    @if ($poll['isUssdSurvey'])
                        <x-badge tone="good" class="mt-3" dot>The USSD poll runs this survey</x-badge>
                    @elseif ($canEdit && $survey->status === 'live')
                        <form method="post" action="{{ route('surveys.ussd', $survey) }}" class="mt-3">@csrf<x-button variant="secondary" size="sm">Run this survey on USSD</x-button></form>
                    @endif
                </x-card>
            @endif
            @if ($quotas->isNotEmpty())
                <x-card title="Quota by ward" :description="$survey->quota_per_ward.' responses wanted in each ward'" icon="target">
                    <div class="max-h-96 overflow-y-auto pr-1">
                        @foreach ($quotas as $row)
                            <div class="mb-2.5">
                                <div class="mb-1 flex justify-between gap-2 text-sm"><span class="truncate">{{ $row['ward']->name }} <span class="text-subtle">· {{ $row['ward']->lga->name }}</span></span><span class="num flex-none text-muted">{{ $row['n'] }}/{{ $survey->quota_per_ward }}</span></div>
                                <x-progress :value="$row['n']" :max="$survey->quota_per_ward" :tone="$row['n'] >= $survey->quota_per_ward ? 'good' : 'brand'" />
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.app>
