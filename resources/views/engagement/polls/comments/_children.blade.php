{{-- OLD DisplayKids(): thread = a nested list of links (subject بواسطة name في
     date); nested = full comments, indented; flat = full comments, one after
     another. nocomments shows children as thread does. --}}
@if ($nodes !== [])
    @if ($prefs['mode'] === 'nested')
        <ul style="list-style:none;padding-right:24px">
            @foreach ($nodes as $node)
                <li>
                    @include('engagement.polls.comments._comment', ['comment' => $node['comment'], 'showParent' => false])
                    @include('engagement.polls.comments._children', ['nodes' => $node['children']])
                </li>
            @endforeach
        </ul>
    @elseif ($prefs['mode'] === 'flat')
        @foreach ($nodes as $node)
            @include('engagement.polls.comments._comment', ['comment' => $node['comment'], 'showParent' => false])
            @include('engagement.polls.comments._children', ['nodes' => $node['children']])
        @endforeach
    @else
        <ul>
            @foreach ($nodes as $node)
                @php($k = $node['comment'])
                <li>
                    <a href="{{ \App\Domain\Engagement\Services\PollCommentUrls::showReply((int) $poll->pollID, (int) $k->tid, (int) $k->pid, $prefs) }}">{{ trim((string) $k->subject) !== '' ? $k->subject : '[لا يوجد موضوع]' }}</a>
                    بواسطة {{ trim((string) $k->name) !== '' ? $k->name : $settings['anonymous'] }} في {{ $k->date ? substr((string) $k->date, 0, 16) : '' }}
                    @include('engagement.polls.comments._children', ['nodes' => $node['children']])
                </li>
            @endforeach
        </ul>
    @endif
@endif
