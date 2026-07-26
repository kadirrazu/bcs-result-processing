<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base controller for all HTTP controllers.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
