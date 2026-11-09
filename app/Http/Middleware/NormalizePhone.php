<?php

namespace App\Http\Middleware;

use App\Models\Registration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns "0812-3456 7890" / "+62812..." into "628123456789" before Fortify login/register sees it.
 * The typed value is kept as `phone_display`, so a form shown again after an error
 * repopulates "0896…" instead of the normalized "62896…".
 */
class NormalizePhone
{
    public function handle(Request $request, Closure $next): Response
    {
        if (is_string($request->input('phone'))) {
            $request->merge([
                'phone_display' => $request->input('phone'),
                'phone' => Registration::normalizePhone($request->input('phone')),
            ]);
        }

        return $next($request);
    }
}
