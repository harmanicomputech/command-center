@php
    $progress = $task->progressFor($user);
    $done = $task->doneBy($user);
@endphp
<x-layouts.field :title="$task->title" :back="route('field.tasks')">
    <div x-data="taskReportForm(@js(['id' => $task->id, 'title' => $task->title, 'proof' => $task->proof]))">
        <section x-show="saved" x-cloak class="pt-6 text-center">
            <div class="mx-auto mb-5 grid size-20 place-items-center rounded-full bg-brand text-on-brand shadow-raised"><x-icon name="check" size="34" /></div>
            <h1 class="text-2xl font-bold tracking-tight" x-text="done ? 'Task done. Great work.' : 'Update saved'"></h1>
            <p class="mx-auto mt-2 max-w-xs text-sm text-muted">It sends to your coordinator when there’s network.</p>
            <p x-show="done" class="mt-5 inline-flex items-center gap-2 rounded-full bg-accent-soft px-4 py-2 font-bold text-accent-fg"><x-icon name="star" size="18" />+{{ \App\Support\Settings::int('points.task') }} points</p>
            <div class="mt-8 flex flex-col gap-3">
                <x-button :href="route('field.tasks')" size="xl" class="w-full">Back to my tasks</x-button>
            </div>
        </section>

        <div x-show="! saved">
            <p class="eyebrow">{{ $task->typeLabel() }}{{ $task->assignee_id ? '' : ' · whole ward' }}</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">{{ $task->title }}</h1>
            <div class="mt-3 flex flex-wrap gap-1.5">
                @if ($done)<x-badge tone="good">You’ve done this</x-badge>@endif
                @if ($task->due_on)<x-badge :tone="$task->isOverdue() ? 'bad' : null" dot>Due {{ $task->due_on->format('D j M') }}</x-badge>@endif
                <x-badge>Proof: {{ strtolower(config('field.proofs.'.$task->proof)) }}</x-badge>
            </div>
            @if ($task->description)<p class="mt-4 text-[15px] leading-relaxed text-muted">{{ $task->description }}</p>@endif

            @if ($task->target)
                <div class="card mt-5 p-4">
                    <div class="mb-2 flex items-baseline justify-between"><span class="text-sm font-semibold">Your progress</span><span class="num text-sm text-muted"><strong class="text-ink">{{ number_format($progress) }}</strong> of {{ $task->targetLabel() }}</span></div>
                    <x-progress :value="$progress" :max="$task->target" />
                </div>
            @endif

            @if ($task->reports->isNotEmpty())
                <h2 class="mt-6 mb-2 text-sm font-semibold">Your updates</h2>
                <div class="card divide-y divide-line text-sm">
                    @foreach ($task->reports as $report)
                        <div class="flex items-center justify-between gap-3 p-3">
                            <span class="min-w-0 truncate">{{ $report->count ? '+'.number_format($report->count).' · ' : '' }}{{ $report->note ?? ($report->done ? 'Marked done' : 'Update') }}</span>
                            <span class="flex-none text-xs text-subtle">{{ \App\Support\Time::local($report->reported_at, 'j M') }}{{ $report->photos->isNotEmpty() ? ' · photo' : '' }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($task->status === 'open')
                <form x-ref="form" x-on:submit="submit($event)" class="mt-6 space-y-5">
                    <h2 class="text-lg font-semibold">Add an update</h2>
                    @if ($task->target || $task->proof === 'count')
                        <div>
                            <label for="t-count" class="label">How many {{ $task->target_unit ?? 'done' }} since your last update?</label>
                            <input id="t-count" name="count" type="number" inputmode="numeric" min="0" class="input" placeholder="0">
                            <p class="field-error" x-show="errors.count" x-cloak><x-icon name="circle-alert" /><span x-text="errors.count?.[0]"></span></p>
                        </div>
                    @endif
                    <div>
                        <label for="t-note" class="label">Note <span class="font-normal text-subtle">(optional)</span></label>
                        <textarea id="t-note" name="note" class="input" rows="3" maxlength="1000" placeholder="What happened, what you saw"></textarea>
                    </div>
                    <x-photo-field :label="$task->proof === 'photo' ? 'Photo proof' : 'Photo'" :optional="$task->proof !== 'photo'" />
                    <p class="field-error -mt-3" x-show="errors.photo" x-cloak><x-icon name="circle-alert" /><span x-text="errors.photo?.[0]"></span></p>
                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-2xl border-[1.5px] border-line-strong bg-surface p-4">
                        <span><span class="block font-semibold">The task is finished</span><span class="text-sm text-muted">Earns {{ \App\Support\Settings::int('points.task') }} points with proof.</span></span>
                        <input type="checkbox" class="switch" x-model="done">
                    </label>
                    <x-button size="xl" icon="check" class="w-full"><span x-text="done ? 'Mark as done' : 'Save update'">Save update</span></x-button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.field>
