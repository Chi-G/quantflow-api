<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Traits\HasApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RoleMiddleware
{
    use HasApiResponse;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return $this->error('Unauthenticated.', [], 401);
        }

        if (!in_array($request->user()->role->value, $roles, true)) {
            return $this->error('Unauthorized to access this resource.', [], 403);
        }

        return $next($request);
    }
}
