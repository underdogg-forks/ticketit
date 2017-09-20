<?php

$router = $this->app['router'];

$router->group(['middleware' => 'auth'], function () {
    //Ticket public route
    get(config('ticketit.main_route') . '/complete', 'Albertoesquitino\Ticketit\Controllers\TicketsController@indexComplete')
        ->name(config('ticketit.main_route') . '-complete');
    resource(config('ticketit.main_route'), 'Albertoesquitino\Ticketit\Controllers\TicketsController');

    //Ticket Comments public route
    resource(config('ticketit.main_route') . '-comment', 'Albertoesquitino\Ticketit\Controllers\CommentsController');

    //Ticket complete route for permitted user.
    get(config('ticketit.main_route') . '/{id}/complete', 'Albertoesquitino\Ticketit\Controllers\TicketsController@complete')
        ->name(config('ticketit.main_route') . '.complete');

    //Ticket reopen route for permitted user.
    get(config('ticketit.main_route') . '/{id}/reopen', 'Albertoesquitino\Ticketit\Controllers\TicketsController@reopen')
        ->name(config('ticketit.main_route') . '.reopen');
});

$router->group(['middleware' => 'Albertoesquitino\Ticketit\Middleware\IsAgentMiddleware'], function () {

    //API return list of agents in particular category
    get(config('ticketit.main_route') . '/agents/list/{category_id?}/{ticket_id?}', [
        'as' => config('ticketit.main_route') . 'agentselectlist',
        'uses' => 'Albertoesquitino\Ticketit\Controllers\TicketsController@agentSelectList',
    ]);
});

$router->group(['middleware' => 'Albertoesquitino\Ticketit\Middleware\IsAdminMiddleware'], function () {
    //Ticket admin index route (ex. http://url/tickets-admin/)
    get(config('ticketit.admin_route'), 'Albertoesquitino\Ticketit\Controllers\AdminController@index');

    //Ticket statuses admin routes (ex. http://url/tickets-admin/status)
    resource(config('ticketit.admin_route') . '/status', 'Albertoesquitino\Ticketit\Controllers\StatusesController');

    //Ticket priorities admin routes (ex. http://url/tickets-admin/priority)
    resource(config('ticketit.admin_route') . '/priority', 'Albertoesquitino\Ticketit\Controllers\PrioritiesController');

    //Agents management routes (ex. http://url/tickets-admin/agent)
    resource(config('ticketit.admin_route') . '/agent', 'Albertoesquitino\Ticketit\Controllers\AgentsController');

    //Agents management routes (ex. http://url/tickets-admin/agent)
    resource(config('ticketit.admin_route') . '/category', 'Albertoesquitino\Ticketit\Controllers\CategoriesController');
});
