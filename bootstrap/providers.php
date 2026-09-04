<?php

use App\Domain\Admin\Providers\AdminServiceProvider;
use App\Domain\Content\Providers\ContentServiceProvider;
use App\Domain\Engagement\Providers\EngagementServiceProvider;
use App\Domain\Identity\Providers\IdentityServiceProvider;
use App\Domain\Pages\Providers\PagesServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\PerformanceServiceProvider;
use App\Support\LegacyUrlCompatibility\UrlMapServiceProvider;

return [
    AppServiceProvider::class,
    PerformanceServiceProvider::class,
    AdminServiceProvider::class,
    ContentServiceProvider::class,
    EngagementServiceProvider::class,
    IdentityServiceProvider::class,
    PagesServiceProvider::class,
    UrlMapServiceProvider::class,
];
