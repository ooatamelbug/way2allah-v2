@extends('layouts.app')

@section('title', 'تطبيق شبكة الطريق إلى الله')

@section('content')
    <x-page-chrome
        heading="تطبيق الطريق إلى الله"
        :breadcrumb="[['title' => 'تطبيق الجوال', 'url' => '']]"
    />

    <div class="w2a-app-showcase" dir="rtl">
        <!-- Hero Banner -->
        <div class="w2a-app-hero">
            <div class="w2a-app-hero__content">
                <span class="w2a-app-hero__badge">
                    <i class="fa fa-mobile" aria-hidden="true"></i>
                    <span>التطبيق الرسمي المجاني</span>
                </span>
                <h1 class="w2a-app-hero__title">تطبيق الطريق إلى الله</h1>
                <p class="w2a-app-hero__lead">
                    رفيقك الإيماني اليومي على هاتفك الذكي.. آلاف المحاضرات، المرئيات، التلاوات، والأناشيد بلمسة واحدة وبأعلى جودة، متوفر الآن على متجري جوجل بلاي وآب ستور.
                </p>

                <div class="w2a-app-hero__stats">
                    <span class="w2a-app-hero__stat-pill"><i class="fa fa-check-circle" aria-hidden="true"></i> أكثر من 700 داعية</span>
                    <span class="w2a-app-hero__stat-pill"><i class="fa fa-check-circle" aria-hidden="true"></i> تلاوات خاشعة نادرة</span>
                    <span class="w2a-app-hero__stat-pill"><i class="fa fa-check-circle" aria-hidden="true"></i> مجاني 100% بدون إعلانات</span>
                </div>

                <div class="w2a-app-hero__buttons">
                    <a href="https://play.google.com/store/apps/details?id=com.way2allah.app" target="_blank" rel="noopener" class="w2a-app-btn">
                        <img src="/google-play.svg" alt="Google Play" width="162" height="48">
                    </a>
                    <a href="https://apps.apple.com/us/app/%D8%A7%D9%84%D8%B7%D8%B1%D9%8A%D9%82-%D8%A5%D9%84%D9%89-%D8%A7%D9%84%D9%84%D9%87/id6480062523" target="_blank" rel="noopener" class="w2a-app-btn">
                        <img src="/app-store.svg" alt="App Store" width="162" height="48">
                    </a>
                </div>
            </div>

            <div class="w2a-app-hero__mockup">
                <img src="/app-mockup.png" alt="تطبيق الطريق إلى الله" width="340" height="480">
            </div>
        </div>

        <!-- Features Grid -->
        <x-content.premium-panel title="عن التطبيق ومميزاته" icon="fa-th-large">
            <div class="w2a-app-features-grid">
                <div class="w2a-app-feature-card">
                    <span class="w2a-app-feature-card__icon" style="background: #eff6ff; color: #0284c7;" aria-hidden="true">
                        <i class="fa fa-video-camera"></i>
                    </span>
                    <h3 class="w2a-app-feature-card__title">أكبر مكتبة مرئية إسلامية</h3>
                    <p class="w2a-app-feature-card__desc">
                        تضم آلاف الدروس والمحاضرات لأكثر من ٧٠٠ داعية وعالم من مختلف أنحاء العالم الإسلامي بجودة عالية.
                    </p>
                </div>

                <div class="w2a-app-feature-card">
                    <span class="w2a-app-feature-card__icon" style="background: #fdf2f8; color: #db2777;" aria-hidden="true">
                        <i class="fa fa-film"></i>
                    </span>
                    <h3 class="w2a-app-feature-card__title">كارتون وأفلام وثائقية هادفة</h3>
                    <p class="w2a-app-feature-card__desc">
                        محتوى تربوي آمن وممتع للأطفال والناشئة، بالإضافة إلى برامج وأفلام وثائقية تثري الوعي الإسلامي.
                    </p>
                </div>

                <div class="w2a-app-feature-card">
                    <span class="w2a-app-feature-card__icon" style="background: #ecfdf5; color: #059669;" aria-hidden="true">
                        <i class="fa fa-headphones"></i>
                    </span>
                    <h3 class="w2a-app-feature-card__title">مقاطع دعوية وأناشيد</h3>
                    <p class="w2a-app-feature-card__desc">
                        آلاف المقاطع الدعوية المؤثرة والأناشيد الإسلامية الهادفة الخالية من أي محاذير شرعية.
                    </p>
                </div>

                <div class="w2a-app-feature-card">
                    <span class="w2a-app-feature-card__icon" style="background: #fef3c7; color: #d97706;" aria-hidden="true">
                        <i class="fa fa-book"></i>
                    </span>
                    <h3 class="w2a-app-feature-card__title">تلاوات قرآنية خاشعة</h3>
                    <p class="w2a-app-feature-card__desc">
                        مكتبة صوتية عطرة تحوي آلاف التلاوات المسجلة بأصوات نخبة من أشهر وأبرز قراء العالم الإسلامي.
                    </p>
                </div>

                <div class="w2a-app-feature-card">
                    <span class="w2a-app-feature-card__icon" style="background: #f5f3ff; color: #7c3aed;" aria-hidden="true">
                        <i class="fa fa-picture-o"></i>
                    </span>
                    <h3 class="w2a-app-feature-card__title">تصميمات وبطاقات دعوية</h3>
                    <p class="w2a-app-feature-card__desc">
                        مئات التصميمات والبطاقات الإيمانية الجاهزة للنشر والمشاركة عبر منصات التواصل الاجتماعي.
                    </p>
                </div>

                <div class="w2a-app-feature-card">
                    <span class="w2a-app-feature-card__icon" style="background: #f0fdf4; color: #16a34a;" aria-hidden="true">
                        <i class="fa fa-bolt"></i>
                    </span>
                    <h3 class="w2a-app-feature-card__title">تجربة استخدام سلسة</h3>
                    <p class="w2a-app-feature-card__desc">
                        واجهة مستخدم عصرية، بحث سريع وفوري، حفظ المواد المفضلة، وسهولة تامة في الاستماع والمشاهدة.
                    </p>
                </div>
            </div>
        </x-content.premium-panel>

        <!-- Video Showcase -->
        <x-content.premium-panel title="شاهد جولة داخل التطبيق" icon="fa-play-circle-o">
            <div class="w2a-app-video-wrap">
                <iframe src="https://www.youtube.com/embed/us6sUGf2Wjs?si=y9oX0aMoeG-4cecY" title="YouTube video player" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
            </div>
        </x-content.premium-panel>
    </div>
@endsection
