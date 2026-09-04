@extends('layouts.app')

@section('title', 'شجرة تصنيفات المنوعات')

@section('content')
    {{--
        categories/tree.php's op=var branch -> showtree(), same function as
        categories/tree.blade.php (op-less/default), parameterized
        differently by the legacy file itself ($tb_field='anasheed_count',
        $slug='var-category-', $title_ex='المنوعات'). Breadcrumb/markup
        quirks are the same as the default branch — see IF-037 (per-sibling
        <ul> wrapper, empty-href breadcrumb items) — not re-documented here,
        same finding.
    --}}
    <i class="fa fa-gift"></i><h3 class="page-title">شجرة تصنيفات المنوعات</h3>

    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>
            <li><a href="">المنوعات</a><i class="fa fa-angle-right"></i></li>
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
                    <x-category-tree.index :categories-by-parent="$categoriesByParent" route-prefix="/var-category-" />
                </div>
            </div>
        </div>
    </div>
@endsection
