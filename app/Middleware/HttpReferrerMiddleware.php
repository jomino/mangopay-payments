<?php

namespace App\Middleware;

class HttpReferrerMiddleware
{

    public $container;

    public function __construct($app)
    {
        $this->container = $app->getContainer();
    }

    public function __invoke($request, $response, $next){
        $session = $this->container->get('session');
        $logger = $this->container->get('logger');
        if(!$session->exists(\Util\MangopayUtility::SESSION_DOMAIN)){
            $domain = $request->getHeaderLine('Referer');
            if(empty($domain)){
                $domain = $request->getServerParam('HTTP_REFERER', '');
            }
            $domain = preg_replace('#^(?:http[s]?://)?([a-z0-9\-._~%]+)(?:/?.*)$#i','$1',$domain);
            $session->set(\Util\MangopayUtility::SESSION_DOMAIN,$domain);
        }
        if(!$session->exists(\Util\MangopayUtility::SESSION_REMOTE)){
            $session->set(\Util\MangopayUtility::SESSION_REMOTE,$request->getServerParam('REMOTE_ADDR'));
        }
        //$logger->info('['.$session->get(\Util\MangopayUtility::SESSION_REMOTE).'] SESSION_HTTP_REFERER -> DOMAIN: '.$session->get(\Util\MangopayUtility::SESSION_DOMAIN));
        return $next($request, $response);
    }

}