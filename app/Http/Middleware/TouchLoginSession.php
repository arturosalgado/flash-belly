<?php

namespace App\Http\Middleware;

use App\Models\LoginSession;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TouchLoginSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if ($user instanceof User) {
            LoginSession::touchCurrent($user);
        }

        return $response;
    }
}
