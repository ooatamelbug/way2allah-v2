@extends('layouts.app')

@section('title', 'قائمة الدعاة بقسم الفتاوى المرئية')

@section('content')
    {{--
        `fatawa-by-authers.htm`'s real legacy source is
        `fatawa/fatawa-by-authers.php`'s default (`else`) branch — the only
        branch reachable through this pretty URL (G-07-03, Phase 1 audit).
        Authors are grouped under their first Arabic letter
        (`mb_substr($Author->name,0,1,"utf-8")`, with 'ه' normalized to
        'هـ' — fatawa-by-authers.php:67-68), each group heading rendered
        once, the moment the running letter changes down an
        already-name-ordered list — not a second grouped query.
    --}}
    <nav aria-label="فهرس الحروف">
        <ul>
            @php($runningLetter = null)
            @foreach ($authors as $author)
                @php($letter = mb_substr((string) $author->name, 0, 1, 'utf-8'))
                @php($letter = $letter === 'ه' ? 'هـ' : $letter)
                @continue($letter === $runningLetter)
                @php($runningLetter = $letter)
                <li><a href="#letter-{{ $loop->index }}">{{ $letter }}</a></li>
            @endforeach
        </ul>
    </nav>

    <section aria-label="قائمة الدعاة">
        @php($runningLetter = null)
        @foreach ($authors as $author)
            @php($letter = mb_substr((string) $author->name, 0, 1, 'utf-8'))
            @php($letter = $letter === 'ه' ? 'هـ' : $letter)
            @if ($letter !== $runningLetter)
                @php($runningLetter = $letter)
                <h2 id="letter-{{ $loop->index }}">{{ $letter }}</h2>
            @endif
            <div class="author">
                {{-- fatawa-by-authers.php:83-85 — get_author_img() is a
                     pure filesystem-existence check (no `author_image`
                     column priority, unlike khotab/author.php's own
                     convention) — Author::fallbackImageUrl() reproduces
                     that exact check. Legacy's OWN outer getimagesize()
                     double-check (re-verifying the returned URL loads
                     before trusting it) is not reproduced: a live network
                     call from inside a view is not a pattern used
                     anywhere else in this app, and is redundant here — if
                     the file doesn't exist on disk, fallbackImageUrl()
                     already returns the same no-image fallback either way. --}}
                <img src="{{ $author->fallbackImageUrl() }}" alt="{{ $author->prename }} {{ $author->name }}">
                <div>
                    <span><a href="/auther-questions-{{ $author->id }}.htm">{{ $author->name }}</a></span>
                    <span>{{ $author->count }} فتوى</span>
                </div>
            </div>
        @endforeach
    </section>
@endsection
