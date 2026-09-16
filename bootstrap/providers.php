<?php

return [
    App\Providers\AppServiceProvider::class,
    // Filament admin panel disabled; /admin now redirects to the external frontend (see routes/web.php).
    App\Providers\HorizonServiceProvider::class,
];
