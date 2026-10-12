<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // リクエストがAPI（JSON形式）でない場合は、ログイン画面(/login)のパスを返します
        return $request->expectsJson() ? null : route('login');
    }
}
