<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>أداة التثبيت — الطريق إلى الله</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        body { font-family: -apple-system, "Segoe UI", Tahoma, Arial, sans-serif; background: #f4f6f8; color: #222; max-width: 640px; margin: 40px auto; padding: 0 16px; }
        .box { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 24px; margin-bottom: 16px; }
        h1 { font-size: 20px; }
        p.warn { background: #fff3cd; border: 1px solid #ffe08a; padding: 12px; border-radius: 6px; }
        label { display: block; margin: 12px 0 4px; font-weight: 600; }
        input[type=password] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 15px; box-sizing: border-box; }
        button { margin-top: 16px; padding: 10px 20px; background: #1a7f37; color: #fff; border: 0; border-radius: 6px; font-size: 15px; cursor: pointer; }
        .error { color: #b00020; margin-top: 8px; }
        ul.steps { list-style: none; padding: 0; }
        ul.steps li { padding: 8px 12px; border-radius: 6px; margin-bottom: 6px; }
        .status-ok { background: #e6f4ea; color: #1a7f37; }
        .status-failed { background: #fdecea; color: #b00020; }
        .status-skipped { background: #f1f1f1; color: #777; }
        .success-banner { background: #e6f4ea; border: 1px solid #1a7f37; padding: 16px; border-radius: 6px; }
        .fail-banner { background: #fdecea; border: 1px solid #b00020; padding: 16px; border-radius: 6px; }
    </style>
</head>
<body>
    <h1>أداة تثبيت النشر — تُستخدم مرة واحدة فقط</h1>

    @isset($results)
        <div class="box">
            @if ($succeeded)
                <div class="success-banner">
                    <strong>✅ اكتمل التثبيت بنجاح.</strong><br>
                    تم إنشاء ملف القفل — هذه الصفحة لن تعمل مرة أخرى بعد الآن.
                    <br><br>
                    <strong>الخطوة التالية:</strong> عدّل ملف <code>.env</code> عبر مدير الملفات واجعل
                    <code>DEPLOY_INSTALLER_ENABLED=false</code>، ثم احفظ الملف. راجع
                    <code>CPANEL-NO-SSH-DEPLOYMENT.md</code> لخطوات الاختبار النهائية.
                </div>
            @else
                <div class="fail-banner">
                    <strong>❌ فشل التثبيت في إحدى الخطوات.</strong><br>
                    لم يتم إنشاء ملف القفل — يمكنك تصحيح الإعدادات في <code>.env</code> وإعادة المحاولة بأمان
                    (جميع الخطوات آمنة لإعادة التشغيل من البداية).
                </div>
            @endif

            <ul class="steps">
                @foreach ($results as $r)
                    <li class="status-{{ $r['status'] }}">
                        @if ($r['status'] === 'ok') ✅
                        @elseif ($r['status'] === 'failed') ❌
                        @else ⏭
                        @endif
                        {{ $r['label'] }}
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <p class="warn">
            هذه الأداة تُنفّذ إعداد قاعدة البيانات وتفعيل التخزين المؤقت للموقع.
            استخدمها مرة واحدة فقط بعد رفع الموقع وضبط ملف <code>.env</code> بالكامل.
            لن تظهر أي بيانات حساسة (كلمات مرور أو إعدادات) في هذه الصفحة.
        </p>

        <div class="box">
            @if (isset($tokenError))
                <p class="error">{{ $tokenError }}</p>
            @endif
            <form method="POST" action="{{ route('deploy.install') }}">
                @csrf
                <label for="token">رمز التثبيت (DEPLOY_INSTALLER_TOKEN من ملف .env)</label>
                <input type="password" id="token" name="token" required autocomplete="off">
                <button type="submit">بدء التثبيت</button>
            </form>
        </div>
    @endisset
</body>
</html>
