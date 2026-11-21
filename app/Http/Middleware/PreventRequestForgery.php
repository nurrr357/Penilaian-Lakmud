<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as BasePreventRequestForgery;

/**
 * Panel LAKMUD memakai form HTML biasa dan selalu mengirim _token di body.
 * Cookie XSRF-TOKEN hanya diperlukan klien JavaScript/Axios; cookie itu
 * sengaja tidak dibuat supaya tidak ada cookie yang bisa dibaca JavaScript.
 */
class PreventRequestForgery extends BasePreventRequestForgery
{
    protected $addHttpCookie = false;
}
