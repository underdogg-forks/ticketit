<?php

namespace Albertoesquitino\Ticketit\Middleware;

use \Closure;
use Albertoesquitino\Ticketit\Models\Agent;

class IsAdminMiddleware
{
    /**
     * Run the request filter.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (Agent::isAdmin())
            return $next($request);

        return redirect()->action('\Albertoesquitino\Ticketit\Controllers\TicketsController@index')
            ->with('warning', 'You are not permitted to access this page!');
    }

}