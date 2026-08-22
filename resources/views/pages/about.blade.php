@extends('layouts.app')

{{-- Content copied verbatim from legacy help/about.php, including its
     inline MS-Word-pasted styling — not cleaned up. Preserving ugly markup
     exactly is a deliberate application of rule 1 (legacy is the source of
     truth unless there is a confirmed bug or an approved ADR-0010
     redesign): inline styles are not a functional bug, so they are not
     "fixed" as a side effect of migrating the page. A content redesign is
     a future, explicit business decision, not something to do silently
     while porting.

     Chrome/Portlet Gap Closure (2026-08-22): this view also serves
     /landing_page.htm (routes/pages.php, same AboutController) — this
     restoration applies to both routes identically, matching legacy's own
     single `help/about.php` source. about.php:6-18 uses the shared
     `title()`/`breadcrumb()` mechanism (functions.php:453-543), a single
     breadcrumb item (`['title'=>'من نحن','url'=>'']` — present-but-empty
     url, renders `<a href="">`, not plain text), reproduced via the
     already-existing <x-page-chrome> component whose DOM was directly
     re-verified against functions.php this task, not assumed. about.php:22-27's
     `w2a_open_div(['title'=>'من نحن','width'=>'12','icon'=>'fa-child'])`
     wraps this page's own already-correct content in a real portlet —
     the content itself (below) is untouched. --}}

@section('title', 'من نحن')

@section('content')
    <x-page-chrome heading="من نحن" :breadcrumb="[['title' => 'من نحن', 'url' => '']]" />

    <div class="row service-box margin-bottom-40">
        <div id="" class="col-md-12 col-sm-12">
            <div class="portlet box blue">
                <div class="portlet-title">
                    <div class="caption"><i class="fa fa-child"></i> من نحن</div>
                </div>
                <div class="portlet-body ">
    <div dir="rtl">
        <p class="MsoNormal" dir="RTL"><b><u><span lang="AR-EG" style="font-size:18pt;font-family:'Traditional Arabic',serif;color:red">الرؤية :</span></u></b><b><u><span dir="LTR" style="font-size:18pt;font-family:'Traditional Arabic',serif;color:red"></span></u></b></p>
        <p class="MsoNormal" dir="RTL" style="text-align:justify"><b><span lang="AR-EG" style="font-size:14pt;font-family:'Traditional Arabic',serif;color:rgb(0,32,96)">رؤيتنا دلالة الخلق كل الخلق على الله مسلمين وغير مسلمين ناطقين
باللغة العربية وغيرها من</span></b><span dir="LTR"></span><span dir="LTR"></span><b><span lang="AR-EG" dir="LTR" style="font-size:14pt;font-family:'Traditional Arabic',serif;color:rgb(0,32,96)"><span dir="LTR"></span><span dir="LTR"></span> </span></b><b><span lang="AR-EG" style="font-size:14pt;font-family:'Traditional Arabic',serif;color:rgb(0,32,96)">اللغات العالمية والوصول بالدعوة إلى الله عز وجل إلى جميع الأقطار
والأمصار على منهج أهل السنة والجماعة.</span></b><b><span lang="AR-SA" style="font-size:14pt;font-family:'Traditional Arabic',serif;color:rgb(0,32,96)"></span></b></p>
        <p class="MsoNormal" dir="RTL"><b><u><span lang="AR-EG" style="font-size:18pt;font-family:'Traditional Arabic',serif;color:red">المهمة :</span></u></b><b><span lang="AR-EG" style="font-size:18pt;font-family:'Traditional Arabic',serif;color:red"></span></b></p>
        <p class="MsoNormal" dir="RTL"><b><span lang="AR-EG" style="font-size:14pt;font-family:'Traditional Arabic',serif;color:rgb(0,32,96)">&nbsp; نشر معانى الدين الإسلامى وتربية المجتمع عليها.</span></b></p>
        <p class="MsoNormal" dir="RTL"><b><u><span lang="AR-EG" style="font-size:18pt;font-family:'Traditional Arabic',serif;color:red">الأهداف :</span></u></b></p>
        <p class="MsoNormal" dir="RTL" style="margin-right:0.5in"><b><span style="font-size:14pt;font-family:'Traditional Arabic',serif;color:rgb(0,32,96)">1-<span style="font-weight:normal;font-size:7pt;font-family:'Times New Roman'">&nbsp; </span></span></b><span dir="RTL"></span><b><span lang="AR-EG" style="font-size:14pt;font-family:'Traditional Arabic',serif;color:rgb(0,32,96)">بث
مواد دعوية مختلفة من إنتاج المؤسسة أو من خارجها.</span></b></p>
        <p class="MsoNormal" dir="RTL" style="margin-right:0.5in"><b><span style="font-size:14pt;font-family:'Traditional Arabic',serif;color:rgb(0,32,96)">2-<span style="font-weight:normal;font-size:7pt;font-family:'Times New Roman'">&nbsp; </span></span></b><span dir="RTL"></span><b><span lang="AR-EG" style="font-size:14pt;font-family:'Traditional Arabic',serif;color:rgb(0,32,96)">عمل
بيئة إيمانية تربوية صالحة لجميع المراحل العمرية.</span></b></p>
        <p class="MsoNormal" dir="RTL" style="margin-right:0.25in"><span lang="AR-EG">&nbsp;</span></p>
        <p class="MsoNormal" dir="RTL"><b><u><span lang="AR-EG" style="font-size:18pt;font-family:'Traditional Arabic',serif;color:red">نبذة تاريخية عن الشبكة</span></u></b><span lang="AR-EG"> :</span>&nbsp;</p>
        <p class="MsoNormal" dir="RTL"><b><span lang="AR-EG" style="font-size:14pt;font-family:'Traditional Arabic',serif;color:rgb(0,32,96)">شبكة الطريق إلى الله
بدأت في شهر يوليو من عام 2005 كموقع دعوي وقفي لله يعرض مشاريع الدعاة ومحاضراتهم
بمساجد المنصورة أحد مدن مصر.</span></b></p>
        <p class="MsoNormal" dir="RTL"><b><span lang="AR-EG" style="font-size:14pt;font-family:'Traditional Arabic',serif;color:rgb(0,32,96)">&nbsp;</span></b></p>
        <p class="MsoNormal" dir="RTL"><b><span lang="AR-EG" style="font-size:14pt;font-family:'Traditional Arabic',serif;color:rgb(0,32,96)">ثم تطورت الفكرة لتشمل
مرئيات الفضائيات الإسلامية كلها من محاضرات ودروس وبرامج ومنوعات وأناشيد ومقاطع
وأفلام وثائقية وكارتون و مواد مرئية دعوية أخرى.</span></b></p>
        <p class="MsoNormal" dir="RTL"><b><span lang="AR-EG" style="font-size:14pt;font-family:'Traditional Arabic',serif;color:rgb(0,32,96)">&nbsp;</span></b></p>
        <p class="MsoNormal" dir="RTL"><b><span lang="AR-EG" style="font-size:14pt;font-family:'Traditional Arabic',serif;color:rgb(0,32,96)">وكذلك تم إفتتاح ساحة
للحوار ((منتدى دعوي)) &nbsp;يحتضن أعضاء وزوار
الموقع من مختلف دول العالم إيمانيا ودعويا وتنظيمهم في فرق عمل للتصميم والبرمجة
والتفريغ والترجمة وغيرها من الفرق الفنية من خلال دورات يلقيها متخصصين محترفين
في هذه المجالات.</span></b></p>
        <p class="MsoNormal" dir="RTL"><b><span lang="AR-EG" style="font-size:14pt;font-family:'Traditional Arabic',serif;color:rgb(0,32,96)">&nbsp;</span></b></p>
        <b><span lang="AR-EG" dir="RTL" style="font-size:14pt;font-family:'Traditional Arabic',serif;color:rgb(0,32,96)">ثم تطورت الفكرة لتصبح من
موقع الطريق الى الله الى شبكة مواقع الطريق إلى الله لنستهدف فئات جديدة لم نصل إليها
بعد وعمل مواقع موجهه إلى هذه الفئات للوصول إلى الرؤية الشمولية للشبكة ألا وهي
دلالة الخلق كل الخلق على الله من المسلمين وغير المسلمين الناطقين بالعربية أو
بغيرها من اللغات إما بإدارة فريق عمل الشبكة أو بالاستعانة من المتخصصين في
المجالات الدعوية المختلفة.&nbsp;</span></b>
        <div class="yj6qo"></div>
        <div class="adL"><br>
        </div>
    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
