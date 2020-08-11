<?php

namespace LaravelAmp\Middleware;

use Closure;
use Illuminate\Http\Request;
use LaravelAmp\AMP;

class LaravelAmpMiddleware
{

    /**
     * @param $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($request->segment(1) === 'amp') {
            $response = AMP::convert($response);
        }

        return $response;
    }
}
