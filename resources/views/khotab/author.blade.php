@extends('layouts.app')

@section('title', $pageTitle)

@section('content')
    <x-page-chrome :heading="$pageTitle" :breadcrumb="[
        ['title' => $opTitle, 'url' => '/khotab-' . $op . '.htm'],
        ['title' => 'قائمة الدعاة', 'url' => '/khotab-' . $op . '.htm'],
        ['title' => trim(($authorModel->prename ?? '') . ' ' . ($authorModel->name ?? '')), 'url' => ''],
    ]" />

    {{-- Contextual administration (OLD Author(): «لوحة تحكم الداعية», author_cp.htm) — video and audio pages only. --}}
    @if ($op !== 'pdf')
        @contextualAdmin
            <x-admin.contextual-controls title="لوحة تحكم الداعية" :controls="\App\Domain\Admin\Contextual\Controls\KhotabAuthorControls::panel($authorModel, $op)" />
        @endcontextualAdmin

        {{-- OLD apply_search("author", $id) (way2allah_functions.php): «بحث متقدم في سلاسل و مواد الداعية» toggles a
             form, for every visitor, searching this sheikh's videos on advanced_search.htm (GET, decision-log #21). --}}
        <details class="w2a-author-search" data-author-advanced-search>
            <summary class="link_style">بحث متقدم في سلاسل و مواد الداعية</summary>
            <div class="w2a-author-search__panel">
                <h3 class="w2a-author-search__title"><i class="fa fa-search" aria-hidden="true"></i> بحث متقدم</h3>
                <form class="w2a-advanced-search" method="get" action="/advanced_search.htm">
                    <input type="hidden" name="author" value="{{ $authorModel->id }}">
                    <div class="w2a-field w2a-field--wide">
                        <label for="author_search_title">اسم السلسلة أو المادة :</label>
                        <span class="w2a-field__control">
                            <input type="search" class="form-control" id="author_search_title" name="title" autocomplete="off">
                        </span>
                    </div>
                    <div class="w2a-field">
                        <label for="author_search_channel">القناة :</label>
                        <span class="w2a-field__control">
                            <select class="form-control" id="author_search_channel" name="channel">
                                <option value="0">إختر</option>
                                @foreach ($searchChannels as $searchChannel)
                                    <option value="{{ $searchChannel->id }}">{{ $searchChannel->title }}</option>
                                @endforeach
                            </select>
                        </span>
                    </div>
                    <fieldset class="w2a-field w2a-field--dates">
                        <legend>تاريخ الإضافة</legend>
                        <div class="w2a-date-range">
                            <label for="author_search_from">من</label>
                            <span class="w2a-field__control">
                                <input type="date" class="form-control" id="author_search_from" name="from">
                            </span>
                            <label for="author_search_to">إلى</label>
                            <span class="w2a-field__control">
                                <input type="date" class="form-control" id="author_search_to" name="to">
                            </span>
                        </div>
                    </fieldset>
                    <div class="w2a-search-actions">
                        <button type="submit" class="w2a-primary-action">بــحــث</button>
                    </div>
                </form>
            </div>
        </details>
    @endif

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding flex flex-column gap-5">
            @if ($op !== 'pdf')
                <x-content.premium-panel title="قائمة المجموعات" icon="fa-folder">
                    <x-content.media-collection-grid :items="$groups" type="group" secondary="channel" mark-hidden />
                </x-content.premium-panel>

                <x-content.premium-panel title="قائمة السلاسل" icon="fa-list-ol">
                    <x-content.media-collection-grid :items="$series" secondary="channel" mark-hidden />
                </x-content.premium-panel>
            @endif

            <x-content.premium-panel title="قائمة المواد" icon="fa-play-circle">
                <x-content.khotab-item-list :items="$items" :video="$op === 'video'" :pdf="$op === 'pdf'" />
            </x-content.premium-panel>

            @if (!empty($authorModel->description))
                <x-content.premium-panel title="نبذة عن الداعية" icon="fa-user">
                    <section aria-label="نبذة عن الداعية">{{ $authorModel->description }}</section>
                </x-content.premium-panel>
            @endif
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding flex flex-column gap-5" aria-label="الشريط الجانبي">
            <section class="w2a-refresh-panel w2a-author-profile-card">
                <img class="w2a-author-avatar" src="{{ $authorModel->displayImageUrl() }}" alt="{{ $authorModel->name }}">
                <h3 class="w2a-author-name">{{ $authorModel->prename }} {{ $authorModel->name }}</h3>
            </section>

            @if ($op === 'video')
                <a href="/khotab-video-{{ $authorModel->id }}.htm" class="w2a-author-banner w2a-author-banner--video">
                    <i class="fa fa-video-camera" aria-hidden="true"></i>
                    <span>مرئيات الداعية</span>
                </a>
            @elseif($op === 'audio')
                <a href="/khotab-audio-{{ $authorModel->id }}.htm" class="w2a-author-banner w2a-author-banner--audio">
                    <i class="fa fa-volume-up" aria-hidden="true"></i>
                    <span>صوتيات الداعية</span>
                </a>
            @endif

            <x-content.premium-panel title="اخترنا لك هذه المادة" icon="fa-star">
                <x-content.featured-items :items="$randomFeatured" />
            </x-content.premium-panel>

            <x-content.sidebar-ranking title="الأكثر تحميلاً" icon="fa-fire" :items="$mostDownloaded" type="khotab" :show-thumbs="$op !== 'pdf'"
                meta="downloads" />

            <x-content.sidebar-ranking title="جديد المواد" icon="fa-history" :items="$mostRecent" type="khotab" :show-thumbs="$op !== 'pdf'"
                meta="recent" />
        </aside>
    </div>
@endsection
