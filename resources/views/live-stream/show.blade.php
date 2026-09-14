@extends('layouts.app')

@section('title', 'البث المباشر - قناة ' . $channel->title)

@section('content')
    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>
            <li><a href="/live-channels.htm">البث المباشر</a><i class="fa fa-angle-right"></i></li>
            <li><span>قناة {{ $channel->title }}</span></li>
        </ul>
    </div>

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding flex flex-column gap-5">
            <x-content.premium-panel title="البث المباشر لقناة {{ $channel->title }}" icon="fa-video-camera">
                <div class="channel-script-container text-center"
                    style="background: #000; border-radius: 12px; overflow: hidden; min-height: 280px; display: flex; align-items: center; justify-content: center;">
                    {!! $channel->streamcode !!}
                </div>
            </x-content.premium-panel>

            <x-content.premium-panel title="بيانات وتردد قناة {{ $channel->title }}" icon="fa-desktop">
                <div class="row" style="margin-bottom: 20px;">
                    <div class="col-sm-4 text-center">
                        <div
                            style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
                            <img src="/images/channels/{{ $channel->id }}.png" alt="{{ $channel->title }}"
                                style="max-width: 100%; height: 80px; object-fit: contain;">
                            <h4 style="font-weight: 700; color: #0f172a; margin: 10px 0 4px;">{{ $channel->title }}</h4>
                        </div>
                    </div>
                    <div class="col-sm-8">
                        <div class="w2a-table-responsive-wrapper">
                            <table class="table table-striped table-hover">
                                <tbody>
                                    <tr>
                                        <th width="30%">القمر الصناعي</th>
                                        <td>{{ $channel->satellite->title ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>الموقع المداري</th>
                                        <td>{{ str_replace(['W', 'E'], ['غرباً ', 'شرقاً '], $channel->satellite->pos ?? '-') }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>التردد</th>
                                        <td style="font-weight: 700; color: #0284c7;">{{ $channel->freq }}</td>
                                    </tr>
                                    <tr>
                                        <th>الاستقطاب</th>
                                        <td>{{ str_replace(['H', 'V'], ['أفقي', 'رأسي'], (string) $channel->polar) }}</td>
                                    </tr>
                                    <tr>
                                        <th>معدل الترميز</th>
                                        <td>{{ $channel->srate }}</td>
                                    </tr>
                                    <tr>
                                        <th>معامل التصويب</th>
                                        <td>{{ $channel->fec }}</td>
                                    </tr>
                                    <tr>
                                        <th>التشفير</th>
                                        <td>{{ str_replace('FREE', 'مجانية (غير مشفرة)', (string) $channel->enc) }}</td>
                                    </tr>
                                    <tr>
                                        <th>عدد الدروس</th>
                                        <td>{{ number_format((int) $channel->khotab) }} درس</td>
                                    </tr>
                                    <tr>
                                        <th>عدد الزيارات</th>
                                        <td>{{ number_format((int) $channel->ch_visits + 1) }} زيارة</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                @if ($channel->beamForDisplay())
                    <div class="text-center" style="margin-top: 16px;">
                        <h5 style="font-weight: 700; color: #475569; margin-bottom: 12px;">نطاق التغطية القمرية</h5>
                        <img src="/images/beams/{{ $channel->beamForDisplay() }}.png" alt="مجال التغطية"
                            class="img-responsive" style="margin: 0 auto; border-radius: 8px; border: 1px solid #e2e8f0;">
                    </div>
                @endif
            </x-content.premium-panel>
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding flex flex-column gap-5" aria-label="الشريط الجانبي">
            <x-content.sidebar-ranking title="أكثر المواد مشاهدة" icon="fa-fire" :items="$mostViewed" type="khotab"
                meta="views" />

            <x-content.sidebar-ranking title="جديد القناة" icon="fa-clock-o" :items="$mostRecent" type="khotab"
                meta="recent" />
        </aside>
    </div>
@endsection
