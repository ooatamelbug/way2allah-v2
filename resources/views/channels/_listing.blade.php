{{--
    Shared groups/series/items listing markup for channels/show.blade.php
    and channels/author.blade.php (pre-Wave-4 decision #5 — small, local
    Blade partial extraction, no behavioral change). $showAuthorLinks
    controls the one real difference between the two call sites: show.blade.php
    (channel.php, unfiltered) links each row to its author; author.blade.php
    (author.php, already filtered to one author) doesn't repeat that link.
--}}
@php($showAuthorLinks = $showAuthorLinks ?? false)

<section aria-label="قائمة المجموعات">
    <ul>
        @foreach ($groups as $group)
            <li>
                <a href="/khotab-group-{{ $group->id }}.htm">{{ $group->title }}</a>
                @if ($showAuthorLinks)
                    — <a href="/channel-{{ $channelModel->id }}-{{ $group->author_id }}.htm">{{ $group->author }}</a>
                @endif
            </li>
        @endforeach
    </ul>
</section>

<section aria-label="قائمة السلاسل">
    <ul>
        @foreach ($series as $item)
            <li>
                <a href="/khotab-series-{{ $item->id }}.htm">{{ $item->title }}</a>
                @if ($showAuthorLinks)
                    — <a href="/channel-{{ $item->channel_id }}-{{ $item->author_id }}.htm">{{ $item->author }}</a>
                @endif
            </li>
        @endforeach
    </ul>
</section>

<section aria-label="قائمة المواد">
    <ul>
        @foreach ($items as $item)
            <li>
                <a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a>
                @if ($showAuthorLinks)
                    — <a href="/channel-{{ $channelModel->id }}-{{ $item->author_id }}.htm">{{ $item->author }}</a>
                @endif
            </li>
        @endforeach
    </ul>
</section>
