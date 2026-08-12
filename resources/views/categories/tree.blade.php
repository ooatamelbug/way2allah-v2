@extends('layouts.app')

@section('title', 'شجرة تصنيفات المرئيات')

@section('content')
    {{--
        categories/tree.php's default (op-less) branch -> showtree().
        Breadcrumb reproduces tree.php:37-38 exactly: both non-Home items
        are built with `'url' => ''` (not omitted), which the shared
        breadcrumb() function (functions.php:453) renders as a literal
        empty href (isset() is true for ''), not a plain unlinked label —
        a real, if slightly odd, legacy quirk, preserved as found.
    --}}
    <i class="fa fa-gift"></i><h3 class="page-title">شجرة تصنيفات المرئيات</h3>

    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>
            <li><a href="">المرئيات</a><i class="fa fa-angle-right"></i></li>
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
                            {{--
                                showtree() (categories/tree.php:119-208): 3 fixed
                                levels, matched by main_cat over one flat
                                collection. Each level-2/level-3 sibling gets its
                                OWN <ul> wrapper (confirmed by reading the source:
                                $ul/$ul2 reset to true on every loop iteration,
                                before the match check — not one shared <ul> per
                                parent) — reproduced exactly, not "fixed" into a
                                single wrapping <ul>.
                            --}}
                            @foreach ($categories->where('main_cat', 0) as $topLevel)
                                <li>
                                    @if ($categories->contains('main_cat', $topLevel->id))
                                        <input id="group{{ $loop->iteration }}" type="checkbox" hidden>
                                        <label for="group{{ $loop->iteration }}">
                                            <div class="arrowStyle">
                                                <img src="/assets/img/reading-quran.png">
                                                <a class="catAncor" href="/category-{{ $topLevel->id }}.htm">{{ $topLevel->title }}</a>
                                                <span class="fa fa-angle-right"></span>
                                            </div>
                                        </label>
                                    @else
                                        <img src="/assets/img/star (1).png">
                                        <a href="/category-{{ $topLevel->id }}.htm">{{ $topLevel->title }}</a>
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
                                                                <a class="catAncor" href="/category-{{ $group->id }}.htm">{{ $group->title }}</a>
                                                                <span class="fa fa-angle-right"></span>
                                                            </div>
                                                        </label>
                                                    @else
                                                        <img src="/assets/img/star (1).png">
                                                        <a href="/category-{{ $group->id }}.htm">{{ $group->title }}</a>
                                                    @endif
                                                    <div class="sub-group-list-container">
                                                        @foreach ($categories->where('main_cat', $group->id) as $leaf)
                                                            <ul class="sub_group_list">
                                                                <li>
                                                                    <img src="/assets/img/star (1).png">
                                                                    <a href="/category-{{ $leaf->id }}.htm">{{ $leaf->title }}</a>
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
