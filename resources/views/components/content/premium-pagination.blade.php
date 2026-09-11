@if ($paginator->hasPages())
    <nav class="w2a-pagination" role="navigation" aria-label="التنقل بين الصفحات">
        <p class="w2a-pagination__summary">
            عرض
            <strong>{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</strong>
            من
            <strong>{{ $paginator->total() }}</strong>
        </p>

        @isset($postFields)
            <form method="post" action="{{ $paginator->path() }}">
                @csrf
                @foreach ($postFields as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
        @endisset
        <ul class="w2a-pagination__list">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="w2a-pagination__control is-disabled" aria-disabled="true">
                        <i class="fa fa-angle-right" aria-hidden="true"></i>
                        <span>السابق</span>
                    </span>
                @else
                    @isset($postFields)
                        <button type="submit" class="w2a-pagination__control" formaction="{{ $paginator->previousPageUrl() }}" aria-label="الصفحة السابقة">
                            <i class="fa fa-angle-right" aria-hidden="true"></i><span>السابق</span>
                        </button>
                    @else
                    <a class="w2a-pagination__control" href="{{ $paginator->previousPageUrl() }}" rel="prev"
                        aria-label="الصفحة السابقة">
                        <i class="fa fa-angle-right" aria-hidden="true"></i>
                        <span>السابق</span>
                    </a>
                    @endisset
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li>
                        <span class="w2a-pagination__ellipsis" aria-hidden="true">{{ $element }}</span>
                        <span class="sr-only">صفحات محذوفة من العرض</span>
                    </li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page === $paginator->currentPage())
                                <span class="w2a-pagination__page is-current" aria-current="page">
                                    <span class="sr-only">الصفحة الحالية:</span>
                                    {{ $page }}
                                </span>
                            @else
                                @isset($postFields)
                                    <button type="submit" class="w2a-pagination__page" formaction="{{ $url }}" aria-label="الانتقال إلى الصفحة {{ $page }}">{{ $page }}</button>
                                @else
                                <a class="w2a-pagination__page" href="{{ $url }}"
                                    aria-label="الانتقال إلى الصفحة {{ $page }}">{{ $page }}</a>
                                @endisset
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    @isset($postFields)
                        <button type="submit" class="w2a-pagination__control" formaction="{{ $paginator->nextPageUrl() }}" aria-label="الصفحة التالية">
                            <span>التالي</span><i class="fa fa-angle-left" aria-hidden="true"></i>
                        </button>
                    @else
                    <a class="w2a-pagination__control" href="{{ $paginator->nextPageUrl() }}" rel="next"
                        aria-label="الصفحة التالية">
                        <span>التالي</span>
                        <i class="fa fa-angle-left" aria-hidden="true"></i>
                    </a>
                    @endisset
                @else
                    <span class="w2a-pagination__control is-disabled" aria-disabled="true">
                        <span>التالي</span>
                        <i class="fa fa-angle-left" aria-hidden="true"></i>
                    </span>
                @endif
            </li>
        </ul>
        @isset($postFields)
            </form>
        @endisset
    </nav>
@endif
