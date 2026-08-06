<?php

use App\Support\LegacyUrlCompatibility\UrlMapRouteRegistrar;

/*
|--------------------------------------------------------------------------
| Legacy URL Compatibility Routes (Blueprint v1.0 §11, Roadmap task 0.7)
|--------------------------------------------------------------------------
|
| Registers every rule in config/legacy-url-map.php as a real route. Empty
| in Wave 0 since the map itself is currently empty — see that file.
|
*/

UrlMapRouteRegistrar::registerAll(config('legacy-url-map', []));
