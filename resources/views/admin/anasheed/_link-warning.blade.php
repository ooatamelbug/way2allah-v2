{{-- B2 / F07-A: a warning (never a block) under a Variety link field when its current value — the stored link, or
     the value just submitted when the form is redisplayed — is not an absolute http(s) URL with a host. --}}
@if (is_string($link ?? null) && (($showWhenEmpty ?? false) || trim($link) !== '') && ! \App\Domain\Content\Support\VarietyMediaLink::isPlayable($link))
    <p class="alert alert-warning" dir="rtl" role="note" data-admin-link-warning>
        <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
        تنبيه: هذه الوصلة ليست رابطاً كاملاً يبدأ بـ http:// أو https:// ويتضمن اسم الموقع، لذلك لن تعمل المشاهدة أو التحميل للزوار. يمكنك الحفظ رغم ذلك.
    </p>
@endif
