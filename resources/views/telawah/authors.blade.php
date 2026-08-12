@extends('layouts.app')

@section('title', 'قائمة القراء بقسم التلاوات')

@section('content')
    {{--
        Live-Reference Comparison Report: live recite.htm shows each reciter
        as a card (name, stats: الأقسام الفرعية/التلاوات/الزيارات, a
        description field) rather than a plain link list — brought closer
        to that here using only fields the controller already selects
        (id, title, hits, child, telawah, des; TelawahAuthorController::index()
        untouched). No per-author image accessor exists on TelawahGroup and
        none of the site's placeholder images are reachable through the
        current public/ symlinks, so the image slot from live is not
        reproduced here — text/stats only, not a redesign of what data exists.
    --}}
    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>
            <li>التلاوات<i class="fa fa-angle-right"></i></li>
            <li>قائمة القراء</li>
        </ul>
    </div>

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-12">
            <div class="portlet box blue">
                <div class="portlet-title">
                    <div class="caption">قائمة القراء</div>
                </div>
                <div class="portlet-body">
                    <div class="row">
                        @foreach ($groups as $group)
                            <div class="col-md-6 col-sm-12">
                                <div class="portlet box blue">
                                    <div class="portlet-title">
                                        <div class="caption">
                                            <a href="/recite-group-{{ $group->id }}.htm">{{ $group->title }}</a>
                                        </div>
                                    </div>
                                    <div class="portlet-body">
                                        <p>
                                            <i class="fa fa-folder-o"></i> الأقسام الفرعية: {{ $group->child }}
                                            &nbsp;|&nbsp;
                                            <i class="fa fa-play-circle-o"></i> التلاوات: {{ $group->telawah }}
                                            &nbsp;|&nbsp;
                                            <i class="fa fa-eye"></i> الزيارات: {{ $group->hits }}
                                        </p>
                                        @if(!empty($group->des))
                                            <p>{{ $group->des }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
