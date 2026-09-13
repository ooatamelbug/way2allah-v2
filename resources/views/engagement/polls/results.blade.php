@extends('layouts.app')

@section('title', $poll->pollTitle)

@section('content')
    <x-page-chrome heading="نتائج استطلاع الرأي" :breadcrumb="[
        ['title' => 'الإستفتاءات', 'url' => route('engagement.polls.index')],
        ['title' => $poll->pollTitle, 'url' => ''],
    ]" />

    <div class="row service-box margin-bottom-40">
        <div class="col-md-8 col-md-offset-2">
            <x-content.premium-panel title="نتائج التصويت" icon="fa-bar-chart">
                <h3 style="font-size: 20px; font-weight: 700; color: #0f172a; line-height: 1.6; margin: 0 0 24px;">
                    {{ $poll->pollTitle }}
                </h3>

                <div style="display: flex; flex-direction: column; gap: 18px; margin-bottom: 28px;">
                    @foreach ($options as $option)
                        @if ($option->optionText)
                            @php $percent = $totalVotes > 0 ? round(100 * $option->optionCount / $totalVotes) : 0; @endphp
                            <div
                                style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px;">
                                <div
                                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <span
                                        style="font-size: 15px; font-weight: 600; color: #1e293b;">{{ $option->optionText }}</span>
                                    <span style="font-weight: 700; color: #0284c7; font-size: 14px;">{{ $percent }}%
                                        ({{ number_format((int) $option->optionCount) }} صوت)
                                    </span>
                                </div>
                                <div style="background: #e2e8f0; height: 10px; border-radius: 6px; overflow: hidden;">
                                    <div
                                        style="background: linear-gradient(90deg, #0284c7, #0ea5e9); width: {{ $percent }}%; height: 100%; border-radius: 6px; transition: width 0.6s ease;">
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                <div
                    style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                    <span style="font-weight: 700; color: #475569; font-size: 14px;">
                        <i class="fa fa-users" aria-hidden="true"></i> مجموع الأصوات: {{ number_format((int) $totalVotes) }}
                    </span>

                    <div style="display: flex; align-items: center; gap: 14px; font-size: 13.5px;">
                        <a href="{{ route('engagement.polls.show', $poll) }}" class="btn btn-primary btn-sm"
                            style="border-radius: 8px; padding: 5px 16px;">
                            <i class="fa fa-pencil" style="margin-left: 6px;" aria-hidden="true"></i> صفحة التصويت
                        </a>
                        <a href="{{ route('engagement.polls.index') }}" style="color: #64748b; font-weight: 500;">
                            <i class="fa fa-list" style="margin-left: 6px;" aria-hidden="true"></i> تصويتات أخرى
                        </a>
                    </div>
                </div>
            </x-content.premium-panel>

            @if ($latestFive->isNotEmpty())
                <x-content.premium-panel title="أحدث الاستفتاءات في شبكة الطريق إلى الله" icon="fa-clock-o">
                    <ul class="w2a-ranking-list" role="list">
                        @foreach ($latestFive as $recent)
                            <li class="w2a-ranking-item">
                                <a href="{{ route('engagement.polls.results', $recent) }}" class="w2a-ranking-item__link">
                                    <span class="w2a-ranking-badge w2a-ranking-badge--default">
                                        {{ $loop->iteration }}
                                    </span>
                                    <div class="w2a-ranking-item__content">
                                        <span class="w2a-ranking-item__title">{{ $recent->pollTitle }}</span>
                                        <div class="w2a-ranking-item__meta">
                                            <span class="w2a-ranking-meta-tag"><i class="fa fa-users"></i>
                                                {{ number_format((int) $recent->voters) }} صوت</span>
                                        </div>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-content.premium-panel>
            @endif
        </div>
    </div>
@endsection
