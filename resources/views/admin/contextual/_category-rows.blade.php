{{-- Contextual administration: a category list with its per-category controls
     (OLD Tree() / ListCats()). Rendered only inside @contextualAdmin, and only
     when at least one control is visible to this admin. --}}
@php($contextualRows = array_values(array_filter($rows, fn (array $row) => app(\App\Domain\Admin\Contextual\ContextualAdmin::class)->visible($row['controls']) !== [])))
@if ($contextualRows !== [])
    <div class="w2a-admin-ctx" dir="rtl" data-contextual-admin data-contextual-categories>
        <span class="w2a-admin-ctx__title"><i class="fa fa-sitemap" aria-hidden="true"></i> {{ $heading }}</span>
        @foreach ($contextualRows as $row)
            <span>
                @if ($row['title'] !== null)
                    <a href="{{ $linkPrefix ?? '/category-' }}{{ $row['id'] }}.htm">{{ $row['title'] }}</a>
                @else
                    تصنيف غير معروف #{{ $row['id'] }}
                @endif
                <x-admin.contextual-controls variant="inline" :controls="$row['controls']" />
            </span>
        @endforeach
    </div>
@endif
