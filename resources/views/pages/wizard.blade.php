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
    <link rel="stylesheet" type="text/css" href="/assets/global/plugins/select2/select2.css" />
    <link href="/assets/global/css/components-rounded-rtl.css" id="style_components" rel="stylesheet" type="text/css" />
    <link href="/assets/global/css/plugins-rtl.css" rel="stylesheet" type="text/css" />
    <link href="/assets/admin/layout4/css/layout-rtl.css" rel="stylesheet" type="text/css" />
    <link id="style_color" href="/assets/admin/layout4/css/themes/light-rtl.css" rel="stylesheet" type="text/css" />
    <link href="/assets/admin/layout4/css/custom-rtl.css" rel="stylesheet" type="text/css" />
    <link rel="shortcut icon" href="/favicon.ico" />
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
    <script type="text/javascript" src="/assets/global/plugins/select2/select2.min.js"></script>
    <script src="/assets/global/scripts/metronic.js" type="text/javascript"></script>
    <script src="/assets/admin/layout4/scripts/layout.js" type="text/javascript"></script>
    <script src="/assets/admin/layout4/scripts/demo.js" type="text/javascript"></script>
    <script src="/assets/admin/pages/scripts/form-wizard.js"></script>
    <script>
        jQuery(document).ready(function() {
            WayToAllah.init();
            Layout.init();
            Demo.init();
            FormWizard.init();
        });
    </script>
</body>

</html>
