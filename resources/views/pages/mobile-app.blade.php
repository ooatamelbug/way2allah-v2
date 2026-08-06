@extends('layouts.app')

{{-- Content, inline CSS, and external links (Play Store / App Store /
     YouTube trailer) copied verbatim from legacy pages/mobile-app.php.
     Legacy references these 3 images one directory above pages/ (site
     root): app-mockup.png, google-play.svg, app-store.svg — reproduced
     here as root-relative paths (public/) to match. --}}

@section('title', 'تطبيق شبكة الطريق إلى الله')

@section('content')
    <style>

        @media (max-width: 767.98px) {
            .hero-mockup img{
                height: 350px;
            }
            .app-detail {
                height: auto!important;
                margin: 65px 0;
                text-align: center;
            }
            .download-app {
                justify-content: center;
            }
            .about_w2a_app {
                margin-top: 0!important;
            }
            .about_app_content {
                display: block!important;
            }
            .about-app-dec {
                margin-top: 30px;
            }
        }
        .app-detail {
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: 500px;
        }
        .app-detail h1{
            font-size: 28px;
        }
        .app-detail p  {
            font-weight: 600;
            margin-bottom: 30px !important;
        }
        .download-app {
            display: flex;
            align-items: center;
        }

        .download-app img {
            height: 40px;
            margin-left: 10px;
            -webkit-border-radius: 6px;
            -moz-border-radius: 6px;
            border-radius: 6px;
            -moz-background-clip: padding-box;
            -webkit-background-clip: padding-box;
            background-clip: padding-box;
        }

        .about_w2a_app {
            margin-top: 100px;
            margin-bottom: 50px;
        }
        .about_app_content {
            display: flex;
            align-items: center;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 16px;
            margin-bottom: 10px;
        }
        .section-content {
            font-size: 1.2em;
        }
        .highlight {
            color: #d9534f;
        }
    </style>
    <div class="row service-box margin-bottom-40 sh-w2a-block" id="app-container">
        <div class="col-xs-12 col-sm-12 col-md-12 nopadding">
            <div class="row">
                <div class="col-md-6 col-sm-12 text-center">
                    <div class="hero-mockup">
                        <img src="/app-mockup.png" class="img-fluid mx-auto" alt="تطبيق الطريق إلى الله">
                    </div>
                </div>
                <div class="col-md-6 col-sm-12 app-detail">
                    <h1>تطبيق الطريق إلى الله</h1>
                    <p>إطلاق التطبيق الرسمي على جوجل بلاي وآب ستور</p>

                    <div class="download-app">
                        <a href="https://play.google.com/store/apps/details?id=com.way2allah.app" target="_blank"><img src="/google-play.svg" class="img-fluid" alt="تطبيق الطريق إلى الله"></a>
                        <a href="https://apps.apple.com/us/app/%D8%A7%D9%84%D8%B7%D8%B1%D9%8A%D9%82-%D8%A5%D9%84%D9%89-%D8%A7%D9%84%D9%84%D9%87/id6480062523" target="_blank"><img src="/app-store.svg" class="img-fluid" alt="تطبيق الطريق إلى الله"></a>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12 text-center about_w2a_app">
                    <h2>عن التطبيق</h2>
                </div>
            </div>
            <div class="row about_app_content">
                <div class="col-md-6 col-sm-12 text-center">
                    <div class="hero-mockup">
                        <iframe width="100%" height="247" src="https://www.youtube.com/embed/us6sUGf2Wjs?si=y9oX0aMoeG-4cecY" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                    </div>
                </div>
                <div class="col-md-6 col-sm-12 about-app-dec">
                    <div class="section">
                        <span class="highlight">🔹</span>
                        <span class="section-title">أكبر مكتبة مرئية إسلامية على شبكة الإنترنت لأكثر من ٧٠٠ داعية.</span>
                    </div>
                    <div class="section">
                        <span class="highlight">🔹</span>
                        <span class="section-title">أكبر مكتبة للكارتون الإسلامي والأفلام الوثائقية الهادفة.</span>
                    </div>
                    <div class="section">
                        <span class="highlight">🔸</span>
                        <span class="section-title">آلاف المقاطع الدعوية القصيرة والاناشيد الإسلامية المميزة.</span>
                    </div>
                    <div class="section">
                        <span class="highlight">🔹</span>
                        <span class="section-title">آلاف التلاوات القرآنية للعديد من قراء العالم الإسلامي.</span>
                    </div>
                    <div class="section">
                        <span class="highlight">🔸</span>
                        <span class="section-title">مئات التصميمات الدعوية المنوعة.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
