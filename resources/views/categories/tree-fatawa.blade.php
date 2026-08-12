@extends('layouts.app')

@section('title', 'شجرة تصنيفات الفتاوى')

@section('content')
    {{--
        categories/tree.php's op=fatawa branch -> showtree(), same function
        as categories/tree.blade.php (op-less/default) and
        categories/tree-anasheed.blade.php (op=var), parameterized
        differently by the legacy file itself ($tb_field='q_count',
        $slug='fatawa-category-', $title_ex='الفتاوى'). Breadcrumb/markup
        quirks are the same as the other two branches — see IF-037
        (per-sibling <ul> wrapper, empty-href breadcrumb items) — not
        re-documented here, same finding.

        The fatawa-category-{id}.htm links generated below are NOT
        expected to resolve — their own legacy source (fatawa/category.php)
        is confirmed unrecoverable (IF-038). This is the tree page's own,
        independently complete and verified capability; the missing detail
        page is a separate, still-open item, not fixed or worked around
        here.
    --}}
    <i class="fa fa-gift"></i><h3 class="page-title">شجرة تصنيفات الفتاوى</h3>

    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>
            <li><a href="">الفتاوى</a><i class="fa fa-angle-right"></i></li>
            <li><a href="">التصنيفات الموضوعية</a></li>
        </ul>
    </div>

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-12">
            <div class="portlet blue-hoki box">
                <div class="portlet-title">
                    <div class="caption">
                        <i class="fa fa-cogs"></i>شجرة التصنيفات
                    </div>
                </div>
                <div class="portlet-body">
                    <div id="tree_1" class="tree-demo nav">
                        <ul class="list">
                            @foreach ($categories->where('main_cat', 0) as $topLevel)
                                <li>
                                    @if ($categories->contains('main_cat', $topLevel->id))
                                        <input id="group{{ $loop->iteration }}" type="checkbox" hidden>
                                        <label for="group{{ $loop->iteration }}">
                                            <div class="arrowStyle">
                                                <img src="/assets/img/reading-quran.png">
                                                <a class="catAncor" href="/fatawa-category-{{ $topLevel->id }}.htm">{{ $topLevel->title }}</a>
                                                <span class="fa fa-angle-right"></span>
                                            </div>
                                        </label>
                                    @else
                                        <img src="/assets/img/star (1).png">
                                        <a href="/fatawa-category-{{ $topLevel->id }}.htm">{{ $topLevel->title }}</a>
                                    @endif
                                    <div class="list-container">
                                        @foreach ($categories->where('main_cat', $topLevel->id) as $group)
                                            <ul class="group_list">
                                                <li>
                                                    @if ($categories->contains('main_cat', $group->id))
                                                        <input id="sub-group{{ $group->id }}" type="checkbox" hidden>
                                                        <label for="sub-group{{ $group->id }}">
                                                            <div class="arrowStyle">
                                                                <img src="/assets/img/star.png">
                                                                <a class="catAncor" href="/fatawa-category-{{ $group->id }}.htm">{{ $group->title }}</a>
                                                                <span class="fa fa-angle-right"></span>
                                                            </div>
                                                        </label>
                                                    @else
                                                        <img src="/assets/img/star (1).png">
                                                        <a href="/fatawa-category-{{ $group->id }}.htm">{{ $group->title }}</a>
                                                    @endif
                                                    <div class="sub-group-list-container">
                                                        @foreach ($categories->where('main_cat', $group->id) as $leaf)
                                                            <ul class="sub_group_list">
                                                                <li>
                                                                    <img src="/assets/img/star (1).png">
                                                                    <a href="/fatawa-category-{{ $leaf->id }}.htm">{{ $leaf->title }}</a>
                                                                </li>
                                                            </ul>
                                                        @endforeach
                                                    </div>
                                                </li>
                                            </ul>
                                        @endforeach
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
