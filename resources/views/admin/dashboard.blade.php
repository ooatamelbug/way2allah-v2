@extends('layouts.admin')

{{--
    `/admincp/` Login + Dashboard Completion — originally a minimal,
    functional dashboard deliberately NOT a port of legacy
    `admincp/home.php` (confirmed, via full-file read, to contain zero
    database queries anywhere — every number/name/timestamp below is
    literally hardcoded in the legacy HTML, never wired to real data;
    the Morris chart/sparkline/region-map containers have no
    initialization code anywhere in the bundled JS either, confirmed by
    a repo-wide grep, and are correspondingly inert/empty in real
    production too, not just here).

    Corrected 2026-08-26 (owner-supplied side-by-side screenshots, real
    production vs local Laravel): the owner explicitly asked for an exact
    visual copy of production's real dashboard content, hardcoded demo
    numbers included, not the navigation-list substitute this page used
    to show. Every string/number/image path below is transcribed
    verbatim from `admincp/home.php`, not invented — see that file for
    the byte-for-byte source. The navigation-list-of-accessible-modules
    this page previously rendered here is dropped (real legacy has no
    such content on this page; the sidebar already provides full,
    permission-filtered navigation).
--}}
@section('title', 'لوحة التحكم')

@section('content')
    <div class="row margin-top-10">
        <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
            <div class="dashboard-stat2">
                <div class="display">
                    <div class="number">
                        <h3 class="font-green-sharp">17800<small class="font-green-sharp">زيارة</small></h3>
                        <small>الزيارات الشهرية</small>
                    </div>
                    <div class="icon"><i class="icon-pie-chart"></i></div>
                </div>
                <div class="progress-info">
                    <div class="progress">
                        <span style="width: 76%;" class="progress-bar progress-bar-success green-sharp">
                            <span class="sr-only">76% من المستهدف</span>
                        </span>
                    </div>
                    <div class="status">
                        <div class="status-title">من المستهدف</div>
                        <div class="status-number">76%</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
            <div class="dashboard-stat2">
                <div class="display">
                    <div class="number">
                        <h3 class="font-red-haze">1349</h3>
                        <small>إعجاب بالمحتوى</small>
                    </div>
                    <div class="icon"><i class="icon-like"></i></div>
                </div>
                <div class="progress-info">
                    <div class="progress">
                        <span style="width: 85%;" class="progress-bar progress-bar-success red-haze">
                            <span class="sr-only">85% تغير</span>
                        </span>
                    </div>
                    <div class="status">
                        <div class="status-title">تغير</div>
                        <div class="status-number">85%</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
            <div class="dashboard-stat2">
                <div class="display">
                    <div class="number">
                        <h3 class="font-blue-sharp">567</h3>
                        <small>رد جديد بالمنتدى</small>
                    </div>
                    <div class="icon"><i class="icon-basket"></i></div>
                </div>
                <div class="progress-info">
                    <div class="progress">
                        <span style="width: 45%;" class="progress-bar progress-bar-success blue-sharp">
                            <span class="sr-only">45% زيادة</span>
                        </span>
                    </div>
                    <div class="status">
                        <div class="status-title">زيادة</div>
                        <div class="status-number">45%</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
            <div class="dashboard-stat2">
                <div class="display">
                    <div class="number">
                        <h3 class="font-purple-soft">276</h3>
                        <small>عضو جديد</small>
                    </div>
                    <div class="icon"><i class="icon-user"></i></div>
                </div>
                <div class="progress-info">
                    <div class="progress">
                        <span style="width: 57%;" class="progress-bar progress-bar-success purple-soft">
                            <span class="sr-only">56% زيادة</span>
                        </span>
                    </div>
                    <div class="status">
                        <div class="status-title">زيادة</div>
                        <div class="status-number">57%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 col-sm-12">
            <div class="portlet light ">
                <div class="portlet-title">
                    <div class="caption caption-md">
                        <span class="caption-subject theme-font-color bold uppercase">إحصائيات مختصرة</span>
                    </div>
                    <div class="actions">
                        <div class="btn-group btn-group-devided" data-toggle="buttons">
                            <label class="btn btn-transparent grey-salsa btn-circle btn-sm active"><input type="radio" name="options1" class="toggle">يوم</label>
                            <label class="btn btn-transparent grey-salsa btn-circle btn-sm"><input type="radio" name="options1" class="toggle">اسبوع</label>
                            <label class="btn btn-transparent grey-salsa btn-circle btn-sm"><input type="radio" name="options1" class="toggle">شهر</label>
                        </div>
                    </div>
                </div>
                <div class="portlet-body">
                    <div class="row list-separated">
                        <div class="col-md-3 col-sm-3 col-xs-6">
                            <div class="font-grey-mint font-sm">اجمالي الزيارات</div>
                            <div class="uppercase font-hg font-red-flamingo">13,760</div>
                        </div>
                        <div class="col-md-3 col-sm-3 col-xs-6">
                            <div class="font-grey-mint font-sm">زوار جدد</div>
                            <div class="uppercase font-hg theme-font-color">4,760</div>
                        </div>
                        <div class="col-md-3 col-sm-3 col-xs-6">
                            <div class="font-grey-mint font-sm">مجموع الزوار</div>
                            <div class="uppercase font-hg font-purple">11,760</div>
                        </div>
                        <div class="col-md-3 col-sm-3 col-xs-6">
                            <div class="font-grey-mint font-sm">زائر يومي</div>
                            <div class="uppercase font-hg font-blue-sharp">9,760</div>
                        </div>
                    </div>
                    <div id="sales_statistics" class="portlet-body-morris-fit morris-chart" style="height: 260px"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-sm-12">
            <div class="portlet light ">
                <div class="portlet-title">
                    <div class="caption caption-md">
                        <span class="caption-subject theme-font-color bold uppercase">نشاط الأعضاء</span>
                    </div>
                    <div class="actions">
                        <div class="btn-group btn-group-devided" data-toggle="buttons">
                            <label class="btn btn-transparent grey-salsa btn-circle btn-sm active"><input type="radio" name="options2" class="toggle">يوم</label>
                            <label class="btn btn-transparent grey-salsa btn-circle btn-sm"><input type="radio" name="options2" class="toggle">اسبوع</label>
                            <label class="btn btn-transparent grey-salsa btn-circle btn-sm"><input type="radio" name="options2" class="toggle">شهر</label>
                        </div>
                    </div>
                </div>
                <div class="portlet-body">
                    <div class="row number-stats margin-bottom-30">
                        <div class="col-md-6 col-sm-6 col-xs-6">
                            <div class="stat-left">
                                <div class="stat-chart"><div id="sparkline_bar"></div></div>
                                <div class="stat-number">
                                    <div class="title">الأجمالي</div>
                                    <div class="number">2460</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-sm-6 col-xs-6">
                            <div class="stat-right">
                                <div class="stat-chart"><div id="sparkline_bar2"></div></div>
                                <div class="stat-number">
                                    <div class="title">جديد</div>
                                    <div class="number">719</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-scrollable table-scrollable-borderless">
                        <table class="table table-hover table-light">
                            <thead>
                                <tr class="uppercase">
                                    <th colspan="2">عضو</th>
                                    <th>الموضوعات</th>
                                    <th>المواد</th>
                                    <th>المقالات</th>
                                    <th>المعدل</th>
                                </tr>
                            </thead>
                            <tr>
                                <td class="fit"><img class="user-pic" src="{{ asset('assets/admin/layout4/img/avatar4.jpg') }}"></td>
                                <td><a href="javascript:;" class="primary-link">المدير العام</a></td>
                                <td>345</td>
                                <td>45</td>
                                <td>124</td>
                                <td><span class="bold theme-font-color">80%</span></td>
                            </tr>
                            <tr>
                                <td class="fit"><img class="user-pic" src="{{ asset('assets/admin/layout4/img/avatar7.jpg') }}"></td>
                                <td><a href="javascript:;" class="primary-link">أبوسلمى المصري</a></td>
                                <td>645</td>
                                <td>50</td>
                                <td>98</td>
                                <td><span class="bold theme-font-color">98%</span></td>
                            </tr>
                            <tr>
                                <td class="fit"><img class="user-pic" src="{{ asset('assets/admin/layout4/img/avatar5.jpg') }}"></td>
                                <td><a href="javascript:;" class="primary-link">الأدمن</a></td>
                                <td>560</td>
                                <td>12</td>
                                <td>24</td>
                                <td><span class="bold theme-font-color">67%</span></td>
                            </tr>
                            <tr>
                                <td class="fit"><img class="user-pic" src="{{ asset('assets/admin/layout4/img/avatar6.jpg') }}"></td>
                                <td><a href="javascript:;" class="primary-link">مصطفى سلطان</a></td>
                                <td>1,345</td>
                                <td>450</td>
                                <td>46</td>
                                <td><span class="bold theme-font-color">98%</span></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 col-sm-6">
            <div class="portlet light ">
                <div class="portlet-title">
                    <div class="caption caption-md">
                        <span class="caption-subject theme-font-color bold uppercase">إحصائيات بالدولة</span>
                    </div>
                    <div class="actions">
                        <a class="btn btn-circle btn-icon-only btn-default" href="javascript:;"><i class="icon-cloud-upload"></i></a>
                        <a class="btn btn-circle btn-icon-only btn-default" href="javascript:;"><i class="icon-wrench"></i></a>
                        <a class="btn btn-circle btn-icon-only btn-default fullscreen" href="#"></a>
                        <a class="btn btn-circle btn-icon-only btn-default" href="javascript:;"><i class="icon-trash"></i></a>
                    </div>
                </div>
                <div class="portlet-body">
                    <div id="region_statistics_loading">
                        <img src="{{ asset('assets/admin/layout/img/loading.gif') }}" alt="loading">
                    </div>
                    <div id="region_statistics_content" class="display-none">
                        <div class="btn-toolbar margin-bottom-10">
                            <div class="btn-group btn-group-circle" data-toggle="buttons">
                                <a href="" class="btn grey-salsa btn-sm active">الأعضاء</a>
                                <a href="" class="btn grey-salsa btn-sm">الترتيب</a>
                            </div>
                            <div class="btn-group pull-right">
                                <a href="" class="btn btn-circle grey-salsa btn-sm dropdown-toggle" data-toggle="dropdown" data-hover="dropdown" data-close-others="true">
                                    اختر منطقة <span class="fa fa-angle-down"></span>
                                </a>
                                <ul class="dropdown-menu pull-right">
                                    <li><a href="javascript:;" id="regional_stat_world">العالم</a></li>
                                    <li><a href="javascript:;" id="regional_stat_usa">أمريكا</a></li>
                                    <li><a href="javascript:;" id="regional_stat_europe">أوروبا</a></li>
                                    <li><a href="javascript:;" id="regional_stat_russia">روسيا</a></li>
                                    <li><a href="javascript:;" id="regional_stat_germany">ألمانيا</a></li>
                                </ul>
                            </div>
                        </div>
                        <div id="vmap_world" class="vmaps display-none"></div>
                        <div id="vmap_usa" class="vmaps display-none"></div>
                        <div id="vmap_europe" class="vmaps display-none"></div>
                        <div id="vmap_russia" class="vmaps display-none"></div>
                        <div id="vmap_germany" class="vmaps display-none"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-sm-6">
            <div class="portlet light">
                <div class="portlet-title tabbable-line">
                    <div class="caption caption-md">
                        <span class="caption-subject theme-font-color bold uppercase">السجلات</span>
                    </div>
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#tab_1_1" data-toggle="tab">النظام</a></li>
                        <li><a href="#tab_1_2" data-toggle="tab">الأنشطة</a></li>
                    </ul>
                </div>
                <div class="portlet-body">
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab_1_1">
                            <div class="scroller" style="height: 337px;" data-always-visible="1" data-rail-visible1="0" data-handle-color="#D7DCE2">
                                <ul class="feeds">
                                    @foreach ([
                                        ['label' => 'success', 'icon' => 'fa-bell-o', 'desc' => 'لديك أربع مهام بإنتظارك', 'action' => 'ابدأ الآن', 'date' => 'الآن'],
                                        ['label' => 'success', 'icon' => 'fa-bell-o', 'desc' => 'تم تطوير قسم الصوتيات بالموقع', 'date' => '20 دقيقة'],
                                        ['label' => 'danger', 'icon' => 'fa-bolt', 'desc' => 'خطأ بقواعد بيانات الموقع وتم إصلاحه', 'date' => '24 دقيقة'],
                                        ['label' => 'info', 'icon' => 'fa-bullhorn', 'desc' => 'تم تلقي تبرع بمبلغ 500$ بتحويل بنكي', 'date' => '30 دقيقة'],
                                        ['label' => 'success', 'icon' => 'fa-bullhorn', 'desc' => 'تعليق جديد بإنتظار النشر', 'date' => '40 دقيقة'],
                                        ['label' => 'warning', 'icon' => 'fa-plus', 'desc' => 'عضوية جديدة بإنتظار التفعيل.', 'date' => '1.5 ساعة'],
                                        ['label' => 'success', 'icon' => 'fa-bell-o', 'desc' => 'نحن بحاجه لتطوير سيرفرات الموقع', 'action' => 'زيادة أحمال', 'date' => '2 ساعة'],
                                        ['label' => 'default', 'icon' => 'fa-bullhorn', 'desc' => 'ضغط شديد على قواعد بياناتا لموقع وصل إلى 90%.', 'date' => '3 ساعة'],
                                        ['label' => 'warning', 'icon' => 'fa-bullhorn', 'desc' => 'تم إنشاء مجموعة جديدة بالمنتدى باسم دورة المعهد القرآني', 'date' => '5 ساعة'],
                                        ['label' => 'info', 'icon' => 'fa-bullhorn', 'desc' => 'إلغاء مؤتمر جامعة المنصورة لدواعي امنية', 'date' => '18 ساعة'],
                                        ['label' => 'default', 'icon' => 'fa-bullhorn', 'desc' => 'إضافة الشيخ فلان لقواعد بيانات الموقع.', 'date' => '21 ساعة'],
                                        ['label' => 'info', 'icon' => 'fa-bullhorn', 'desc' => 'تم عمل إعادة تشغيل للسيرفر، برجاء مراجعة الحركات الأخيرة.', 'date' => '22 ساعة'],
                                        ['label' => 'default', 'icon' => 'fa-bullhorn', 'desc' => 'عضوية جديدة بإنتظار التفعيل', 'date' => '21 ساعة'],
                                        ['label' => 'info', 'icon' => 'fa-bullhorn', 'desc' => 'على جميع السادة المشرفين التواجد اليوم بإجتماع الغرفة', 'date' => '22 ساعة'],
                                        ['label' => 'default', 'icon' => 'fa-bullhorn', 'desc' => 'لا تنسوا الدعاء لأخيكم الأدمن رحمه الله.', 'date' => '21 ساعة'],
                                        ['label' => 'info', 'icon' => 'fa-bullhorn', 'desc' => 'تم الإنتهاء من تصوير حلقات برنامج رمضان قرب.', 'date' => '22 ساعة'],
                                        ['label' => 'default', 'icon' => 'fa-bullhorn', 'desc' => 'تم نشر مقطع رمضان بمجموعة الفيسبوك الرسمية', 'date' => '21 ساعة'],
                                        ['label' => 'info', 'icon' => 'fa-bullhorn', 'desc' => 'تم رفع المواد المطلوبة بقناة اليوتيوب.', 'date' => '22 ساعة'],
                                    ] as $item)
                                        <li>
                                            <div class="col1">
                                                <div class="cont">
                                                    <div class="cont-col1">
                                                        <div class="label label-sm label-{{ $item['label'] }}"><i class="fa {{ $item['icon'] }}"></i></div>
                                                    </div>
                                                    <div class="cont-col2">
                                                        <div class="desc">
                                                            {{ $item['desc'] }}
                                                            @isset($item['action'])
                                                                <span class="label label-sm label-info">{{ $item['action'] }} <i class="fa fa-share"></i></span>
                                                            @endisset
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col2"><div class="date">{{ $item['date'] }}</div></div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        <div class="tab-pane" id="tab_1_2">
                            <div class="scroller" style="height: 337px;" data-always-visible="1" data-rail-visible1="0" data-handle-color="#D7DCE2">
                                <ul class="feeds">
                                    @foreach ([
                                        ['label' => 'success', 'icon' => 'fa-bell-o', 'desc' => 'تسجيل عضوية جديدة', 'date' => 'الآن'],
                                        ['label' => 'success', 'icon' => 'fa-bell-o', 'desc' => 'New order received', 'date' => '10 دقيقة'],
                                        ['label' => 'danger', 'icon' => 'fa-bolt', 'desc' => 'سيرفر الغرفة لا يعمل برجاء اتخاذ الازم.', 'action' => 'تم الإصلاح', 'date' => '24 دقيقة'],
                                        ['label' => 'success', 'icon' => 'fa-bell-o', 'desc' => 'تسجيل عضوية جديدة', 'date' => 'الآن'],
                                        ['label' => 'success', 'icon' => 'fa-bell-o', 'desc' => 'تسجيل عضوية جديدة', 'date' => 'الآن'],
                                        ['label' => 'success', 'icon' => 'fa-bell-o', 'desc' => 'تسجيل عضوية جديدة', 'date' => 'الآن'],
                                        ['label' => 'success', 'icon' => 'fa-bell-o', 'desc' => 'تسجيل عضوية جديدة', 'date' => 'الآن'],
                                        ['label' => 'success', 'icon' => 'fa-bell-o', 'desc' => 'تسجيل عضوية جديدة', 'date' => 'الآن'],
                                        ['label' => 'success', 'icon' => 'fa-bell-o', 'desc' => 'تسجيل عضوية جديدة', 'date' => 'الآن'],
                                    ] as $item)
                                        <li>
                                            <a href="javascript:;">
                                                <div class="col1">
                                                    <div class="cont">
                                                        <div class="cont-col1">
                                                            <div class="label label-sm label-{{ $item['label'] }}"><i class="fa {{ $item['icon'] }}"></i></div>
                                                        </div>
                                                        <div class="cont-col2">
                                                            <div class="desc">
                                                                {{ $item['desc'] }}
                                                                @isset($item['action'])
                                                                    <span class="label label-sm label-danger">{{ $item['action'] }} <i class="fa fa-share"></i></span>
                                                                @endisset
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col2"><div class="date">{{ $item['date'] }}</div></div>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
