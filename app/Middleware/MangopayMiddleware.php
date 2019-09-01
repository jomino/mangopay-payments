<?php

namespace App\Middleware;

class MangopayMiddleware
{

    public $container;

    public function __construct($app)
    {
        $this->container = $app->getContainer();
    }

    public function __invoke($request, $response, $next)
    {
        return $next($request, $response);
    }

}