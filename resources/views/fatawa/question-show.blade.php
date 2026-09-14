@extends('layouts.app')

@section('title', 'فتوى | ' . $fatwaQuestion->question_text)

@push('styles')
    <link href="https://fonts.googleapis.com/css?family=Cairo|Reem+Kufi" rel="stylesheet">
    <style>
        .w2a-fatwa-single-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 24px;
            overflow: hidden;
        }

        .w2a-fatwa-single-header {
            padding: 24px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 1px solid #e2e8f0;
        }

        .w2a-fatwa-single-title {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.6;
            margin: 0 0 16px;
        }

        .w2a-fatwa-single-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 14px;
            color: #64748b;
            font-size: 13px;
        }

        .w2a-fatwa-single-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .w2a-fatwa-single-body {
            padding: 28px 24px;
        }

        .w2a-fatwa-answer-box {
            background: #f8fafc;
            border-right: 4px solid #0284c7;
            border-radius: 8px;
            padding: 20px 24px;
            margin: 16px 0 24px;
            font-size: 16px;
            line-height: 2.2;
            color: #1e293b;
        }

        .w2a-fatwa-answer-label {
            font-weight: 700;
            color: #0284c7;
            font-size: 17px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .w2a-fatwa-author-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #e0f2fe;
            color: #0369a1;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13.5px;
            text-decoration: none !important;
            transition: all 0.2s;
        }

        .w2a-fatwa-author-badge:hover {
            background: #bae6fd;
            color: #0284c7;
        }
    </style>
@endpush

@section('content')
    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li>
                <i class="fa fa-home"></i>
                <a href="/">الرئيسية</a>
                <i class="fa fa-angle-right"></i>
            </li>
            <li>
                <a href="/fatawa.htm">الفتاوى المرئية</a>
                <i class="fa fa-angle-right"></i>
            </li>
            @if ($categoryModel)
                <li>
                    <a href="/fatawa-topics-{{ $categoryModel->id }}-1.htm">{{ $categoryModel->title }}</a>
                    @if ($topicModel)
                        <i class="fa fa-angle-right"></i>
                    @endif
                </li>
            @endif
            @if ($topicModel && $categoryModel)
                <li>
                    <a href="/fatawa-group-{{ $topicModel->id }}-{{ $categoryModel->id }}.htm">موضوع
                        {{ $topicModel->topic_name }}</a>
                    <i class="fa fa-angle-right"></i>
                </li>
            @endif
            <li>
                <span>{{ \Illuminate\Support\Str::limit($fatwaQuestion->question_text, 60) }}</span>
            </li>
        </ul>
    </div>

    <div class="row service-box margin-bottom-40 w2a-media-layout">
        <div class="col-lg-9 col-md-8 col-sm-12">
            <article class="w2a-fatwa-single-card">
                <header class="w2a-fatwa-single-header">
                    <h1 class="w2a-fatwa-single-title">{{ $fatwaQuestion->question_text }}</h1>

                    <div class="w2a-fatwa-single-meta">
                        @if ($fatwaQuestion->author)
                            <a href="/auther-questions-{{ $fatwaQuestion->auther_id }}.htm" class="w2a-fatwa-author-badge">
                                <i class="fa fa-user-circle" aria-hidden="true"></i>
                                <span>{{ $fatwaQuestion->author->prename }} {{ $fatwaQuestion->author->name }}</span>
                            </a>
                        @endif

                        @if ($fatwaQuestion->date_of_fatwa)
                            <span class="w2a-fatwa-single-meta-item">
                                <i class="fa fa-calendar-check-o" aria-hidden="true"></i>
                                <span>تاريخ الفتوى:
                                    {{ \App\Domain\Content\Support\ArabicDateConverter::convert($fatwaQuestion->date_of_fatwa) }}</span>
                            </span>
                        @endif

                        @if ($fatwaQuestion->channel)
                            <span class="w2a-fatwa-single-meta-item">
                                <i class="fa fa-tv" aria-hidden="true"></i>
                                <span>قناة: {{ $fatwaQuestion->channel->title }}</span>
                            </span>
                        @endif

                        @if (isset($fatwaQuestion->num_download) && $fatwaQuestion->num_download > 0)
                            <span class="w2a-fatwa-single-meta-item">
                                <i class="fa fa-download" aria-hidden="true"></i>
                                <span>{{ number_format((int) $fatwaQuestion->num_download) }} تحميل</span>
                            </span>
                        @endif
                    </div>
                </header>

                <div class="w2a-fatwa-single-body">
                    @if (($fatwaQuestion->answer_text ?? '') !== '' && $fatwaQuestion->answer_text !== '.')
                        <div class="w2a-fatwa-answer-label">
                            <i class="fa fa-check-circle" aria-hidden="true"></i>
                            <span>نص الفتوى والجواب:</span>
                        </div>
                        <div class="w2a-fatwa-answer-box" aria-label="الإجابة">
                            {!! $fatwaQuestion->answer_text !!}
                        </div>
                    @endif

                    <div class="w2a-fatwa-actions">
                        <button type="button" class="w2a-action-btn w2a-action-play"
                            onclick="w2a_play({{ $fatwaQuestion->id }}, "fatawa")">
                            <i class="fa fa-play-circle" aria-hidden="true"></i> مشاهدة / استماع
                        </button>
                        <a href="/fatawa-download-{{ $fatwaQuestion->id }}.htm" target="_blank" rel="noopener"
                            class="w2a-action-btn w2a-action-download">
                            <i class="fa fa-download" aria-hidden="true"></i> حفظ الفتوى
                        </a>
                        <button type="button" data-toggle="modal" data-target="#sendFriendModal{{ $fatwaQuestion->id }}"
                            class="w2a-action-btn w2a-action-share send-friend-btn">
                            <i class="fa fa-paper-plane" aria-hidden="true"></i> أرسل لصديق
                        </button>
                        <button type="button" onclick="window.print()" class="w2a-action-btn w2a-action-print"
                            title="طباعة الفتوى">
                            <i class="fa fa-print" aria-hidden="true"></i> طباعة
                        </button>
                    </div>
                </div>
            </article>

            {{-- Send to friend modal --}}
            <div class="modal fade" id="sendFriendModal{{ $fatwaQuestion->id }}" tabindex="-1" role="dialog"
                aria-modal="true" aria-labelledby="sendFriendModalLabel{{ $fatwaQuestion->id }}">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق النافذة"> <span
                                    aria-hidden="true">&times;</span> </button>
                            <h4 class="modal-title" id="sendFriendModalLabel{{ $fatwaQuestion->id }}">إرسال مادة :
                                {{ $fatwaQuestion->question_text }}</h4>
                        </div>
                        <form action="{{ route('fatawa.question.send-to-friend', $fatwaQuestion->id) }}" method="post">
                            @csrf
                            <div class="modal-body">
                                <div class="alert alert-info">
                                    <p><strong>أرسل هذه الفتوى لصديقك: </strong>{{ $fatwaQuestion->question_text }}</p>
                                </div>
                                <div class="form-group">
                                    <label for="your_name{{ $fatwaQuestion->id }}">اسمك</label>
                                    <input type="text" name="your_name" id="your_name{{ $fatwaQuestion->id }}"
                                        class="form-control" placeholder="اسمك الكريم" required>
                                </div>
                                <div class="form-group">
                                    <label for="your_email{{ $fatwaQuestion->id }}">بريدك الإلكتروني</label>
                                    <input type="email" name="your_email" id="your_email{{ $fatwaQuestion->id }}"
                                        class="form-control" placeholder="example@domain.com" required>
                                </div>
                                <div class="form-group">
                                    <label for="friend_name{{ $fatwaQuestion->id }}">اسم صديقك</label>
                                    <input type="text" name="friend_name" id="friend_name{{ $fatwaQuestion->id }}"
                                        class="form-control" placeholder="اسم الصديق" required>
                                </div>
                                <div class="form-group">
                                    <label for="friend_email{{ $fatwaQuestion->id }}">بريد صديقك الإلكتروني</label>
                                    <input type="email" name="friend_email" id="friend_email{{ $fatwaQuestion->id }}"
                                        class="form-control" placeholder="friend@domain.com" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-primary">إرسال الفتوى</button>
                                <button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <x-content.media-player-panel />
            <x-content.media-player-script />
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-12" aria-label="الشريط الجانبي">
            @if ($fatwaQuestion->author)
                <section class="w2a-refresh-panel">
                    <header class="w2a-refresh-panel__header">
                        <span class="w2a-refresh-panel__icon" aria-hidden="true"><i class="fa fa-user"></i></span>
                        <div class="w2a-refresh-panel__heading">
                            <h2>المفتي المجيب</h2>
                        </div>
                    </header>
                    <div class="w2a-refresh-panel__body text-center" style="padding: 20px;">
                        <h4 style="font-weight: 700; color: #0f172a; margin-bottom: 12px;">
                            {{ $fatwaQuestion->author->prename }} {{ $fatwaQuestion->author->name }}
                        </h4>
                        <a href="/auther-questions-{{ $fatwaQuestion->auther_id }}.htm" class="btn btn-primary btn-sm"
                            style="border-radius: 20px; padding: 6px 18px;">
                            <i class="fa fa-search" style="margin-left: 6px;" aria-hidden="true"></i> جميع فتاوى الشيخ
                        </a>
                    </div>
                </section>
            @endif

            @if ($categoryModel)
                <section class="w2a-refresh-panel">
                    <header class="w2a-refresh-panel__header">
                        <span class="w2a-refresh-panel__icon" aria-hidden="true"><i class="fa fa-folder-open"></i></span>
                        <div class="w2a-refresh-panel__heading">
                            <h2>التصنيف</h2>
                        </div>
                    </header>
                    <div class="w2a-refresh-panel__body">
                        <a href="/fatawa-topics-{{ $categoryModel->id }}-1.htm"
                            style="display: block; font-weight: 600; color: #0284c7; text-decoration: none;">
                            <i class="fa fa-angle-left"></i> {{ $categoryModel->title }}
                        </a>
                        @if ($topicModel)
                            <a href="/fatawa-group-{{ $topicModel->id }}-{{ $categoryModel->id }}.htm"
                                style="display: block; margin-top: 10px; color: #475569; text-decoration: none; font-size: 13.5px;">
                                <i class="fa fa-angle-left"></i> موضوع {{ $topicModel->topic_name }}
                            </a>
                        @endif
                    </div>
                </section>
            @endif
        </aside>
    </div>
@endsection
