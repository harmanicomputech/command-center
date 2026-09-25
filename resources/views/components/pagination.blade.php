@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-3" aria-label="Pages">
        <p class="text-sm text-muted">
            <span class="num font-medium text-ink">{{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }}</span>
            @if (method_exists($paginator, 'total')) of <span class="num font-medium text-ink">{{ number_format($paginator->total()) }}</span>@endif
        </p>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary btn-sm" aria-disabled="true"><x-icon name="chevron-left" />Previous</span>
            @else
                <a class="btn btn-secondary btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev"><x-icon name="chevron-left" />Previous</a>
            @endif
            @if ($paginator->hasMorePages())
                <a class="btn btn-secondary btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Next<x-icon name="chevron-right" /></a>
            @else
                <span class="btn btn-secondary btn-sm" aria-disabled="true">Next<x-icon name="chevron-right" /></span>
            @endif
        </div>
    </nav>
@endif
