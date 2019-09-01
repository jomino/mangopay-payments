<?php

namespace App\Middleware;

class LoginMiddleware
{

    public $container;

    public function __construct($app)
    {
        $this->container = $app->getContainer();
    }

    public function __invoke($request, $response, $next){
        $session = $this->container->get('session');
        if(!$session->exists(\Util\MangopayUtility::SESSION_REMOTE)){
            $session->set(\Util\MangopayUtility::SESSION_REMOTE,$request->getServerParam('REMOTE_ADDR'));
        }
        $hash = hash('sha256', $session->get(\Util\MangopayUtility::SESSION_LOGIN).'-'.\App\Parameters::SECURITY['secret']);
        $cookie = \Util\Tools::cookieGetValue(\Dflydev\FigCookies\FigRequestCookies::get($request, \App\Parameters::SECURITY['cookie'], 'none'));
        $request = $request->withAttribute(\App\Parameters::SECURITY['status'], $cookie==$hash);
        return $next($request, $response);
    }

}