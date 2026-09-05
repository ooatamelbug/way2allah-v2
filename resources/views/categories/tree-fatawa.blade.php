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
    <h3 class="page-title">شجرة تصنيفات الفتاوى</h3>

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
                    <x-category-tree.index :categories-by-parent="$categoriesByParent" route-prefix="/fatawa-category-" />
                </div>
            </div>
        </div>
    </div>
@endsection
