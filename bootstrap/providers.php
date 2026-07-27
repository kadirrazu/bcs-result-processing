<?php

use App\Providers\AppServiceProvider;
use App\Providers\ExaminationServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    ExaminationServiceProvider::class,
];
