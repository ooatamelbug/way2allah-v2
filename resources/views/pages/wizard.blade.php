<!DOCTYPE html>
<html lang="en" dir="rtl">
{{-- wizard.php — a standalone page, deliberately not @extends('layouts.app');
     see WizardController's own docblock for why. --}}

<head>
    <meta charset="utf-8" />
    <title>استبيان الدعاة | شبكة الطريق إلى الله</title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <meta http-equiv="Content-type" content="text/html; charset=utf-8">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all" rel="stylesheet"
        type="text/css">
    <link href="/assets/global/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css">
    <link href="/assets/global/plugins/simple-line-icons/simple-line-icons.min.css" rel="stylesheet" type="text/css">
    <link href="/assets/global/plugins/bootstrap/css/bootstrap-rtl.min.css" rel="stylesheet" type="text/css">
    <link href="/assets/global/plugins/uniform/css/uniform.default.css" rel="stylesheet" type="text/css">
    <link href="/assets/global/plugins/bootstrap-switch/css/bootstrap-switch.min.css" rel="stylesheet"
        type="text/css" />
    <link rel="stylesheet" type="text/css" href="/assets/global/plugins/select2/css/select2.min.css" />
    <link href="/assets/global/css/components-rounded-rtl.css" id="style_components" rel="stylesheet" type="text/css" />
    <link href="/assets/global/css/plugins-rtl.css" rel="stylesheet" type="text/css" />
    <link href="/assets/admin/layout4/css/layout-rtl.css" rel="stylesheet" type="text/css" />
    <link id="style_color" href="/assets/admin/layout4/css/themes/light-rtl.css" rel="stylesheet" type="text/css" />
    <link href="/assets/admin/layout4/css/custom-rtl.css" rel="stylesheet" type="text/css" />
    <link rel="shortcut icon" href="/favicon.ico" />

    <style>
        body {
            background-color: #f8fafc !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
        }
        .page-content {
            overflow-x: hidden !important;
            background: transparent !important;
        }
        .logo {
            padding: 24px 0 16px !important;
        }
        .logo img {
            max-height: 80px;
            filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.06));
        }
        #form_wizard_1 {
            max-width: 920px;
            margin: 0 auto 40px !important;
            background: #ffffff !important;
            border-radius: 18px !important;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08) !important;
            border: 1px solid #e2e8f0 !important;
            overflow: hidden !important;
        }
        #form_wizard_1 .portlet-title {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important;
            padding: 18px 28px !important;
            border-bottom: none !important;
            margin-bottom: 0 !important;
        }
        #form_wizard_1 .portlet-title .caption {
            color: #ffffff !important;
            font-size: 18px !important;
            font-weight: 700 !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
        }
        #form_wizard_1 .portlet-title .step-title {
            color: #e0f2fe !important;
            font-weight: 500 !important;
            font-size: 15px !important;
        }
        .form-wizard .steps {
            background: #f8fafc !important;
            padding: 16px 20px !important;
            border-bottom: 1px solid #e2e8f0 !important;
            margin-bottom: 0 !important;
        }
        .form-wizard .steps > li > a.step {
            border-radius: 12px !important;
            padding: 10px 14px !important;
            background: transparent !important;
            transition: all 0.2s ease !important;
        }
        .form-wizard .steps > li > a.step .number {
            background: #e2e8f0 !important;
            color: #475569 !important;
            font-weight: 700 !important;
            border-radius: 50% !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        .form-wizard .steps > li.active > a.step {
            background: #e0f2fe !important;
        }
        .form-wizard .steps > li.active > a.step .number {
            background: #0284c7 !important;
            color: #ffffff !important;
            box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.2) !important;
        }
        .form-wizard .steps > li.done > a.step .number {
            background: #059669 !important;
            color: #ffffff !important;
        }
        .form-wizard .steps > li > a.step .desc {
            color: #334155 !important;
            font-weight: 600 !important;
        }
        #bar {
            height: 5px !important;
            margin: 0 !important;
            background: #f1f5f9 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }
        #bar .progress-bar-success {
            background: linear-gradient(90deg, #0284c7, #059669) !important;
        }
        .portlet-body.form .form-body {
            padding: 32px 28px !important;
        }
        .form-title {
            font-size: 18px !important;
            font-weight: 700 !important;
            color: #0f172a !important;
            margin-bottom: 24px !important;
            padding-bottom: 12px !important;
            border-bottom: 2px solid #f1f5f9 !important;
        }
        .form-horizontal .control-label {
            font-size: 14px !important;
            font-weight: 700 !important;
            color: #1e293b !important;
            padding-top: 10px !important;
            text-align: right !important;
        }
        .form-horizontal .control-label .required {
            color: #ef4444 !important;
            margin-left: 4px;
        }
        .form-control {
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 10px !important;
            padding: 10px 14px !important;
            font-size: 14px !important;
            min-height: 44px !important;
            box-shadow: none !important;
            transition: border-color 0.2s ease, box-shadow 0.2s ease !important;
        }
        .form-control:focus {
            border-color: #0284c7 !important;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15) !important;
        }
        .help-block {
            font-size: 12.5px !important;
            color: #64748b !important;
            margin-top: 6px !important;
        }
        .form-actions {
            background: #f8fafc !important;
            border-top: 1px solid #e2e8f0 !important;
            padding: 20px 28px !important;
            margin: 0 !important;
        }
        .form-actions .btn {
            border-radius: 10px !important;
            padding: 10px 24px !important;
            font-size: 14px !important;
            font-weight: 700 !important;
            transition: all 0.2s ease !important;
        }
        .form-actions .btn.button-previous {
            background: #ffffff !important;
            border: 1.5px solid #cbd5e1 !important;
            color: #334155 !important;
        }
        .form-actions .btn.button-previous:hover {
            background: #f1f5f9 !important;
        }
        .form-actions .btn.button-next {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important;
            color: #ffffff !important;
            border: none !important;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25) !important;
        }
        .form-actions .btn.button-next:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(2, 132, 199, 0.35) !important;
        }
        .form-actions .btn.button-submit {
            background: linear-gradient(135deg, #059669 0%, #047857 100%) !important;
            color: #ffffff !important;
            border: none !important;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25) !important;
        }
        .form-actions .btn.button-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(5, 150, 105, 0.35) !important;
        }
        .page-footer {
            background: transparent !important;
            text-align: center !important;
            padding: 20px 0 30px !important;
            color: #64748b !important;
            font-size: 13px !important;
        }

        @media (max-width: 767px) {
            .page-container {
                padding: 0 16px !important;
            }
            #form_wizard_1 {
                margin: 10px auto 30px !important;
                border-radius: 14px !important;
            }
            .form-wizard .steps > li {
                width: 50% !important;
                float: right !important;
                margin-bottom: 8px !important;
            }
            .form-wizard .steps > li > a.step {
                padding: 8px !important;
                font-size: 12px !important;
            }
            .form-wizard .steps > li > a.step .number {
                width: 28px !important;
                height: 28px !important;
                line-height: 26px !important;
                font-size: 13px !important;
            }
            .form-wizard .steps > li > a.step .desc {
                font-size: 11px !important;
            }
            .form-horizontal .control-label {
                text-align: right !important;
                margin-bottom: 6px !important;
            }
            .portlet-body.form .form-body {
                padding: 20px 14px !important;
            }
            .form-actions {
                padding: 16px 14px !important;
                text-align: center !important;
            }
            .form-actions .col-md-offset-3 {
                margin: 0 !important;
            }
            .form-actions .btn {
                margin: 4px !important;
                display: inline-block !important;
            }
        }
    </style>
</head>


<body class="page-full-width page-header-fixed page-sidebar-closed-hide-logo ">
    <div class="logo" style="margin:0px !important;">
        <center>
            <a href="https://way2allah.com">
                <img src="/login-logo.png" alt="" />
            </a>
        </center>
    </div>
    <div class="clearfix"></div>
    <div class="page-container"
        style="margin-top:0px !important; padding-top:0px !important; border-top:0px !important;">
        <div class="page-content-wrapper">
            <div class="page-content">
                <div class="row">
                    <div class="col-md-12">
                        <div class="portlet box blue" id="form_wizard_1">
                            <div class="portlet-title">
                                <div class="caption">
                                    استبيان الدعاة - <span class="step-title">الخطوة الأولى</span>
                                </div>
                            </div>
                            <div class="portlet-body form">
                                <form action="/wizard.php" class="form-horizontal" id="submit_form" method="POST">
                                    @csrf
                                    <div class="form-wizard">
                                        <div class="form-body">
                                            <ul class="nav nav-pills nav-justified steps">
                                                <li><a href="#tab1" data-toggle="tab" class="step"><span
                                                            class="number">1</span><span class="desc"><i
                                                                class="fa fa-check"></i> بيانات عامة</span></a></li>
                                                <li><a href="#tab2" data-toggle="tab" class="step"><span
                                                            class="number">2</span><span class="desc"><i
                                                                class="fa fa-check"></i> المؤهلات العلمية و
                                                            الدعوية</span></a></li>
                                                <li><a href="#tab3" data-toggle="tab" class="step active"><span
                                                            class="number">3</span><span class="desc"><i
                                                                class="fa fa-check"></i> مجالات التعاون و
                                                            المشاركة</span></a></li>
                                                <li><a href="#tab4" data-toggle="tab" class="step"><span
                                                            class="number">4</span><span class="desc"><i
                                                                class="fa fa-check"></i> تأكيد البيانات</span></a></li>
                                            </ul>
                                            <div id="bar" class="progress progress-striped" role="progressbar">
                                                <div class="progress-bar progress-bar-success"></div>
                                            </div>
                                            <div class="tab-content">
                                                <div class="alert alert-danger display-none">
                                                    <button class="close" data-dismiss="alert"></button>
                                                    لديك بعض البيانات غير مكتملة !
                                                </div>
                                                <div class="alert alert-success display-none">
                                                    <button class="close" data-dismiss="alert"></button>
                                                    Your form validation is successful!
                                                </div>
                                                <div class="tab-pane active" id="tab1">
                                                    <h3 class="block">فضلاً اكتب بيانات الشخصية</h3>
                                                    <div class="form-group">
                                                        <label class="control-label col-md-3">الاسم <span
                                                                class="required">*</span></label>
                                                        <div class="col-md-4">
                                                            <input type="text" class="form-control"
                                                                name="username" />
                                                            <span class="help-block">الاسم ثلاثياً</span>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="control-label col-md-3">رقم الجوال <span
                                                                class="required">*</span></label>
                                                        <div class="col-md-4">
                                                            <input type="text" class="form-control"
                                                                name="password" id="submit_form_password" />
                                                            <span class="help-block">رقم هاتفك الجوال</span>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="control-label col-md-3">تأكيد رقم الجوال<span
                                                                class="required">*</span></label>
                                                        <div class="col-md-4">
                                                            <input type="text" class="form-control"
                                                                name="rpassword" />
                                                            <span class="help-block">أعد كتابة رقم جوالك للتأكيد</span>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="control-label col-md-3">الفيسبوك<span
                                                                class="required">*</span></label>
                                                        <div class="col-md-4">
                                                            <input type="text" class="form-control"
                                                                name="facebook" />
                                                            <span class="help-block">عنوان حسابك على الفيسبوك</span>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="control-label col-md-3">البريد الإلكتروني <span
                                                                class="required">*</span></label>
                                                        <div class="col-md-4">
                                                            <input type="text" class="form-control"
                                                                name="email" />
                                                            <span class="help-block">عنوان بريدك الإلكتروني
                                                                للمراسلات</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="tab-pane" id="tab2">
                                                    <h3 class="block">مؤهلاتك العلمية و الدعوية</h3>
                                                    <div class="form-group">
                                                        <label class="control-label col-md-3">المؤهلات الدراسية
                                                            الاكاديمية الشرعية وغيرها</label>
                                                        <div class="col-md-4">
                                                            <textarea class="form-control" rows="3" name="remarks1"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="control-label col-md-3">المواد الشرعية التي تم
                                                            دراستها</label>
                                                        <div class="col-md-4">
                                                            <textarea class="form-control" rows="3" name="remarks2"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="control-label col-md-3">التخصص العلمي (فقه ،
                                                            عقيده ، اصول .....)</label>
                                                        <div class="col-md-4">
                                                            <textarea class="form-control" rows="3" name="remarks3"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="control-label col-md-3">الخبرة العلمية الشرعية
                                                            (مستشار علمى لموقع كذا او ....)</label>
                                                        <div class="col-md-4">
                                                            <textarea class="form-control" rows="3" name="remarks4"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="control-label col-md-3">التخصص الدعوي (تزكية ،
                                                            تربية ، دعوة فردية ، دعوة عامة ، دعوة شبابية ، كلمات وخطب
                                                            مسجدية ...)</label>
                                                        <div class="col-md-4">
                                                            <textarea class="form-control" rows="3" name="remarks5"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="control-label col-md-3">الخبرة الدعوية (اهم
                                                            المشروعات او السلاسل الدعوية التي قمت فضيلتكم بها او شاركتم
                                                            فيها )</label>
                                                        <div class="col-md-4">
                                                            <textarea class="form-control" rows="3" name="remarks6"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="control-label col-md-3">الاجازات والتزكيات ان
                                                            وجدت</label>
                                                        <div class="col-md-4">
                                                            <textarea class="form-control" rows="3" name="remarks7"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="tab-pane" id="tab3">
                                                    <h3 class="block">مجالات التعاون و المشاركة</h3>
                                                    <div class="form-group">
                                                        <label class="control-label col-md-3">المواد الشرعية التى تود
                                                            فضيلتكم ان تُدرِّسها ان تيسرت الفرصة لذلك</label>
                                                        <div class="col-md-4">
                                                            <textarea class="form-control" rows="3" name="remarks8"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="control-label col-md-3">الفئات الدعوية المستهدفة
                                                            بالنسبة لك</label>
                                                        <div class="col-md-4">
                                                            <textarea class="form-control" rows="3" name="remarks9"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="control-label col-md-3">المادة الدعوية التى تود
                                                            ان تُلقيها ان تيسرت الفرصة لذلك</label>
                                                        <div class="col-md-4">
                                                            <textarea class="form-control" rows="3" name="remarks10"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="control-label col-md-3">اقتراحات فضيلتكم لاهم
                                                            الافكار و المشروعات الدعوية</label>
                                                        <div class="col-md-4">
                                                            <textarea class="form-control" rows="3" name="remarks11"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="tab-pane" id="tab4">
                                                    <h3 class="block">تأكيد البيانات</h3>
                                                    <h4 class="form-section">البيانات الشخصية</h4>
                                                    <div class="form-group"><label
                                                            class="control-label col-md-3">الاسم:</label>
                                                        <div class="col-md-4">
                                                            <p class="form-control-static" data-display="username">
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="form-group"><label
                                                            class="control-label col-md-3">الجوال:</label>
                                                        <div class="col-md-4">
                                                            <p class="form-control-static" data-display="password">
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="form-group"><label
                                                            class="control-label col-md-3">الفيسبوك:</label>
                                                        <div class="col-md-4">
                                                            <p class="form-control-static" data-display="facebook">
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="form-group"><label
                                                            class="control-label col-md-3">البريد الإلكتروني:</label>
                                                        <div class="col-md-4">
                                                            <p class="form-control-static" data-display="email"></p>
                                                        </div>
                                                    </div>

                                                    <h4 class="form-section">مؤهلاتك العلمية و الدعوية</h4>
                                                    <div class="form-group"><label
                                                            class="control-label col-md-3">المؤهلات الدراسية الاكاديمية
                                                            الشرعية وغيرها:</label>
                                                        <div class="col-md-4">
                                                            <p class="form-control-static" data-display="remarks1">
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="form-group"><label
                                                            class="control-label col-md-3">المواد الشرعية التي تم
                                                            دراستها:</label>
                                                        <div class="col-md-4">
                                                            <p class="form-control-static" data-display="remarks2">
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="form-group"><label
                                                            class="control-label col-md-3">التخصص العلمي:</label>
                                                        <div class="col-md-4">
                                                            <p class="form-control-static" data-display="remarks3">
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="form-group"><label
                                                            class="control-label col-md-3">الخبرة العلمية
                                                            الشرعية:</label>
                                                        <div class="col-md-4">
                                                            <p class="form-control-static" data-display="remarks4">
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="form-group"><label
                                                            class="control-label col-md-3">التخصص الدعوي:</label>
                                                        <div class="col-md-4">
                                                            <p class="form-control-static" data-display="remarks5">
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="form-group"><label
                                                            class="control-label col-md-3">الخبرة الدعوية:</label>
                                                        <div class="col-md-4">
                                                            <p class="form-control-static" data-display="remarks6">
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="form-group"><label
                                                            class="control-label col-md-3">الاجازات والتزكيات ان
                                                            وجدت:</label>
                                                        <div class="col-md-4">
                                                            <p class="form-control-static" data-display="remarks7">
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <h4 class="form-section">مجالات التعاون و المشاركة</h4>
                                                    <div class="form-group"><label
                                                            class="control-label col-md-3">المواد الشرعية التى تود
                                                            فضيلتكم ان تُدرِّسها ان تيسرت الفرصة لذلك:</label>
                                                        <div class="col-md-4">
                                                            <p class="form-control-static" data-display="remarks8">
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="form-group"><label
                                                            class="control-label col-md-3">الفئات الدعوية المستهدفة
                                                            بالنسبة لك:</label>
                                                        <div class="col-md-4">
                                                            <p class="form-control-static" data-display="remarks9">
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="form-group"><label
                                                            class="control-label col-md-3">المادة الدعوية التى تود ان
                                                            تُلقيها ان تيسرت الفرصة لذلك:</label>
                                                        <div class="col-md-4">
                                                            <p class="form-control-static" data-display="remarks10">
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="form-group"><label
                                                            class="control-label col-md-3">اقتراحات فضيلتكم لاهم
                                                            الافكار و المشروعات الدعوية:</label>
                                                        <div class="col-md-4">
                                                            <p class="form-control-static" data-display="remarks11">
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <h4 class="form-section">&nbsp;</h4>
                                                    <br>
                                                    وعليه ،،، <br>
                                                    فهذا مجرد استبيان عام ليس إلا<br>
                                                    سائلين الله عز وجل ان يستعملنا واياكم فيما يحب ويرضى والله من وراء
                                                    القصد ،،،<br>
                                                    <br><br>
                                                    ادارة شبكة الطريق الى الله <br><br>
                                                    <button type="submit" title="OK"></button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-actions">
                                            <div class="row">
                                                <div class="col-md-offset-3 col-md-9">
                                                    <a href="javascript:;" class="btn default button-previous"><i
                                                            class="m-icon-swapleft"></i> عودة</a>
                                                    <a href="javascript:;" class="btn blue button-next">استكمال <i
                                                            class="m-icon-swapright m-icon-white"></i></a>
                                                    <a href="javascript:;" class="btn green button-submit">حفظ
                                                        الاستبيان <i class="m-icon-swapright m-icon-white"></i></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="page-footer">
        <div class="page-footer-inner">2015 &copy; جميع الحقوق محفوظة موقع الطريق إلى الله.</div>
        <div class="scroll-to-top"><i class="icon-arrow-up"></i></div>
    </div>
    <script src="/assets/global/plugins/jquery.min.js" type="text/javascript"></script>
    <script src="/assets/global/plugins/jquery-migrate.min.js" type="text/javascript"></script>
    <script src="/assets/global/plugins/jquery-ui/jquery-ui.min.js" type="text/javascript"></script>
    <script src="/assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="/assets/global/plugins/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js" type="text/javascript">
    </script>
    <script src="/assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js" type="text/javascript"></script>
    <script src="/assets/global/plugins/jquery.blockui.min.js" type="text/javascript"></script>
    <script src="/assets/global/plugins/jquery.cokie.min.js" type="text/javascript"></script>
    <script src="/assets/global/plugins/uniform/jquery.uniform.min.js" type="text/javascript"></script>
    <script src="/assets/global/plugins/bootstrap-switch/js/bootstrap-switch.min.js" type="text/javascript"></script>
    <script type="text/javascript" src="/assets/global/plugins/jquery-validation/js/jquery.validate.min.js"></script>
    <script type="text/javascript" src="/assets/global/plugins/jquery-validation/js/additional-methods.min.js"></script>
    <script type="text/javascript" src="/assets/global/plugins/bootstrap-wizard/jquery.bootstrap.wizard.min.js"></script>
    <script type="text/javascript" src="/assets/global/plugins/select2/js/select2.min.js"></script>
    <script src="/assets/global/scripts/app.js" type="text/javascript"></script>
    <script>
        window.Way2allah = typeof App !== "undefined" ? App : {};
        window.WayToAllah = window.Way2allah;
    </script>
    <script src="/assets/admin/layout4/scripts/layout.js" type="text/javascript"></script>
    <script src="/assets/admin/layout4/scripts/demo.js" type="text/javascript"></script>
    <script src="/assets/admin/pages/scripts/form-wizard.js"></script>
    <script>
        jQuery(document).ready(function() {
            if (typeof App !== "undefined") { App.init(); }
            if (typeof Layout !== "undefined") { Layout.init(); }
            if (typeof Demo !== "undefined") { Demo.init(); }
            if (typeof FormWizard !== "undefined") { FormWizard.init(); }
        });
    </script>
</body>

</html>
