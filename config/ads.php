<?php

/*
|--------------------------------------------------------------------------
| Public advertising (F01 / B6c)
|--------------------------------------------------------------------------
|
| Deployment-safety switches, not features. Both default OFF. While
| `rendering_enabled` is off, no public advertising markup is rendered and no
| advertising table (`nuke_ads`, `nuke_banners_ads`, `nuke_banners`) is
| queried or written. Only an explicit literal true value
| (`ADS_RENDERING_ENABLED=true`) turns it on, and only by owner decision.
|
| `popup_enabled` additionally gates position 12 (OLD's on-load popup); it
| has no effect while `rendering_enabled` is off. B6c-1 never opens the popup:
| it renders an inert, closed <dialog> and no script.
|
| B6c-1 writes nothing in either state: no view counter, no click counter,
| no auto-hide (`show = 0`) write-back.
|
*/

return [
    'rendering_enabled' => filter_var(env('ADS_RENDERING_ENABLED', false), FILTER_VALIDATE_BOOL),

    'popup_enabled' => filter_var(env('ADS_POPUP_ENABLED', false), FILTER_VALIDATE_BOOL),
];
