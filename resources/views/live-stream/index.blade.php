@extends('layouts.app')

@section('title', 'البث المباشر للقنوات الفضائية الإسلامية')

@section('content')
    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>
            <li><span>البث المباشر للقنوات الفضائية الإسلامية</span></li>
        </ul>
    </div>

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding flex flex-column gap-5">
            <x-content.premium-panel title="قائمة القنوات الفضائية الإسلامية" icon="fa-tv">
                <div class="row" style="display: flex; flex-wrap: wrap; gap: 16px 0;">
                    @forelse ($channels as $channel)
                        <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                            <a href="/live-channel-{{ $channel->id }}.htm" class="w2a-live-channel-card"
                                style="display: block; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px; text-align: center; text-decoration: none !important; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                                <div
                                    style="position: relative; width: 100px; height: 75px; margin: 0 auto 12px; display: flex; align-items: center; justify-content: center;">
                                    <img src="/images/channels/{{ $channel->id }}.png" alt="{{ $channel->title }}"
                                        style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                </div>
                                <h4 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0 0 8px;">
                                    {{ $channel->title }}</h4>
                                <span class="badge badge-success"
                                    style="background: #10b981; border-radius: 20px; font-size: 11px; padding: 3px 10px;">
                                    <i class="fa fa-circle" style="font-size: 8px; margin-left: 4px;"></i> بث مباشر
                                </span>
                            </a>
                        </div>
                    @empty
                        <div class="col-xs-12 text-center" style="padding: 40px; color: #64748b;">
                            لا توجد قنوات متاحة حالياً
                        </div>
                    @endforelse
                </div>
            </x-content.premium-panel>
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding flex flex-column gap-5" aria-label="أكثر القنوات مشاهدة">
            <section class="w2a-refresh-panel w2a-sidebar-ranking-panel">
                <header class="w2a-refresh-panel__header">
                    <span class="w2a-refresh-panel__icon" aria-hidden="true"><i class="fa fa-eye"></i></span>
                    <div class="w2a-refresh-panel__heading">
                        <h2>أكثر القنوات مشاهدة</h2>
                    </div>
                </header>
                <div class="w2a-refresh-panel__body">
                    <ul class="w2a-ranking-list" role="list">
                        @foreach ($mostViewedChannels as $channel)
                            <li class="w2a-ranking-item">
                                <a href="/live-channel-{{ $channel->id }}.htm" class="w2a-ranking-item__link">
                                    <span
                                        class="w2a-ranking-badge w2a-ranking-badge--{{ $loop->iteration <= 3 ? $loop->iteration : 'default' }}">
                                        {{ $loop->iteration }}
                                    </span>
                                    <div class="w2a-ranking-item__content">
                                        <span class="w2a-ranking-item__title">{{ $channel->title }}</span>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        </aside>
    </div>
@endsection
