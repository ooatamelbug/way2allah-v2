{{-- Phase 2 — recursive, escaped rendering of already-presented metadata.
     Every key and value goes through {{ }}; nothing here is ever output
     raw, so a stored value cannot become markup on this page. --}}
<ul class="list-unstyled" style="margin:0 0 0 12px">
    @foreach ($data as $key => $value)
        <li>
            <strong>{{ $key }}</strong>:
            @if (is_array($value))
                @if ($value === [])
                    <span class="text-muted">—</span>
                @else
                    @include('admin.audit._metadata_tree', ['data' => $value])
                @endif
            @else
                <span dir="auto" style="word-break:break-all">{{ \App\Domain\Admin\Support\AuditMetadataPresenter::scalar($value) }}</span>
            @endif
        </li>
    @endforeach
</ul>
