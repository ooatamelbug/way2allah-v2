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
                    <x-category-tree.index :categories-by-parent="$categoriesByParent" route-prefix="/category-" />
                </div>
            </div>
        </div>
    </div>
@endsection
