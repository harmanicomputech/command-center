@php
    $pages = [
        'register' => ['Register a voter', 'user-plus', 'The registration form arrives in the next update. It works offline and takes under a minute.'],
        'tasks' => ['My tasks', 'list-todo', 'Tasks from your coordinator will show here, with progress and proof.'],
        'issues' => ['Report an issue', 'triangle-alert', 'Report bad roads, water, security and more, with a photo.'],
    ];
    [$title, $icon, $text] = $pages[$page];
@endphp
<x-layouts.field :title="$title">
    <h1 class="text-2xl font-bold tracking-tight">{{ $title }}</h1>
    <div class="card mt-5">
        <x-empty :icon="$icon" title="Coming in the next update" :description="$text">
            <x-button :href="route('field.home')" variant="secondary" icon="arrow-left">Back home</x-button>
        </x-empty>
    </div>
</x-layouts.field>
