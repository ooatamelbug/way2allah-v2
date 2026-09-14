@extends('layouts.app')

@section('title', 'من نحن')

@section('content')
    <x-page-chrome heading="من نحن" :breadcrumb="[['title' => 'من نحن', 'url' => '']]" />

    <div class="row service-box margin-bottom-40">
        <div class="col-md-12 col-sm-12">
            <x-content.premium-panel title="من نحن" icon="fa-info-circle">
                <div class="w2a-about-container" dir="rtl">
                    <!-- Hero Banner -->
                    <div class="w2a-about-hero">
                        <span class="w2a-about-hero__badge">
                            <i class="fa fa-heart" aria-hidden="true"></i>
                            <span>صرح دعوي وقفي لله تعالى</span>
                        </span>
                        <h1 class="w2a-about-hero__title">شبكة الطريق إلى الله</h1>
                        <p class="w2a-about-hero__lead">
                            بوابة دعوية وإعلامية شاملة تهدف إلى نشر صحيح الدين وتربية المجتمع على قيم الإسلام السمحة وفق منهج أهل السنة والجماعة.
                        </p>
                    </div>

                    <!-- 3 Pillars Grid: Vision, Mission, Goals -->
                    <div class="w2a-about-grid">
                        <!-- Vision -->
                        <div class="w2a-about-card">
                            <div class="w2a-about-card__header">
                                <span class="w2a-about-card__icon w2a-about-card__icon--vision" aria-hidden="true">
                                    <i class="fa fa-eye"></i>
                                </span>
                                <h3 class="w2a-about-card__title">الرؤية</h3>
                            </div>
                            <div class="w2a-about-card__body">
                                رؤيتنا دلالة الخلق كل الخلق على الله مسلمين وغير مسلمين ناطقين باللغة العربية وغيرها من اللغات العالمية والوصول بالدعوة إلى الله عز وجل إلى جميع الأقطار والأمصار على منهج أهل السنة والجماعة.
                            </div>
                        </div>

                        <!-- Mission -->
                        <div class="w2a-about-card">
                            <div class="w2a-about-card__header">
                                <span class="w2a-about-card__icon w2a-about-card__icon--mission" aria-hidden="true">
                                    <i class="fa fa-bullseye"></i>
                                </span>
                                <h3 class="w2a-about-card__title">المهمة</h3>
                            </div>
                            <div class="w2a-about-card__body">
                                نشر معانى الدين الإسلامى وتربية المجتمع عليها من خلال محتوى إيماني هادف ومنضبط.
                            </div>
                        </div>

                        <!-- Goals -->
                        <div class="w2a-about-card">
                            <div class="w2a-about-card__header">
                                <span class="w2a-about-card__icon w2a-about-card__icon--goals" aria-hidden="true">
                                    <i class="fa fa-flag"></i>
                                </span>
                                <h3 class="w2a-about-card__title">الأهداف</h3>
                            </div>
                            <div class="w2a-about-card__body">
                                <ul class="w2a-about-goals-list">
                                    <li>
                                        <i class="fa fa-check-circle" aria-hidden="true"></i>
                                        <span>بث مواد دعوية مختلفة من إنتاج المؤسسة أو من خارجها.</span>
                                    </li>
                                    <li>
                                        <i class="fa fa-check-circle" aria-hidden="true"></i>
                                        <span>عمل بيئة إيمانية تربوية صالحة لجميع المراحل العمرية.</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- History Timeline Card -->
                    <div class="w2a-about-history-card">
                        <div class="w2a-about-history-card__header">
                            <span class="w2a-about-history-card__icon" aria-hidden="true">
                                <i class="fa fa-history"></i>
                            </span>
                            <h3 class="w2a-about-card__title">نبذة تاريخية عن الشبكة</h3>
                        </div>

                        <div class="w2a-about-timeline">
                            <div class="w2a-about-timeline__item">
                                <span class="w2a-about-timeline__dot" aria-hidden="true"></span>
                                <h4 class="w2a-about-timeline__title">الانطلاقة والتأسيس (يوليو 2005)</h4>
                                <p class="w2a-about-timeline__text">
                                    شبكة الطريق إلى الله بدأت في شهر يوليو من عام 2005 كموقع دعوي وقفي لله يعرض مشاريع الدعاة ومحاضراتهم بمساجد المنصورة أحد مدن مصر.
                                </p>
                            </div>

                            <div class="w2a-about-timeline__item">
                                <span class="w2a-about-timeline__dot" aria-hidden="true"></span>
                                <h4 class="w2a-about-timeline__title">توسع المرئيات والإنتاج الدعوي</h4>
                                <p class="w2a-about-timeline__text">
                                    ثم تطورت الفكرة لتشمل مرئيات الفضائيات الإسلامية كلها من محاضرات ودروس وبرامج ومنوعات وأناشيد ومقاطع وأفلام وثائقية وكارتون ومواد مرئية دعوية أخرى.
                                </p>
                            </div>

                            <div class="w2a-about-timeline__item">
                                <span class="w2a-about-timeline__dot" aria-hidden="true"></span>
                                <h4 class="w2a-about-timeline__title">منتدى الحوار وفرق العمل المتخصصة</h4>
                                <p class="w2a-about-timeline__text">
                                    وكذلك تم إفتتاح ساحة للحوار ((منتدى دعوي)) يحتضن أعضاء وزوار الموقع من مختلف دول العالم إيمانيا ودعويا وتنظيمهم في فرق عمل للتصميم والبرمجة والتفريغ والترجمة وغيرها من الفرق الفنية من خلال دورات يلقيها متخصصين محترفين في هذه المجالات.
                                </p>
                            </div>

                            <div class="w2a-about-timeline__item">
                                <span class="w2a-about-timeline__dot w2a-about-timeline__dot--final" aria-hidden="true"></span>
                                <h4 class="w2a-about-timeline__title">شبكة متكاملة ورؤية شمولية</h4>
                                <p class="w2a-about-timeline__text">
                                    ثم تطورت الفكرة لتصبح من موقع الطريق الى الله الى شبكة مواقع الطريق إلى الله لنستهدف فئات جديدة لم نصل إليها بعد وعمل مواقع موجهه إلى هذه الفئات للوصول إلى الرؤية الشمولية للشبكة ألا وهي دلالة الخلق كل الخلق على الله من المسلمين وغير المسلمين الناطقين بالعربية أو بغيرها من اللغات إما بإدارة فريق عمل الشبكة أو بالاستعانة من المتخصصين في المجالات الدعوية المختلفة.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </x-content.premium-panel>
        </div>
    </div>
@endsection
