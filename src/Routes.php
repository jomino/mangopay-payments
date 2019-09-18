<?php

namespace Core;

class Routes
{
    public function __construct($app)
    {

        $container = $app->getContainer();

        // Assets
        $app->get('/{path:js|css|fonts|images}/{file:[^/]+}', \App\Controllers\AssetsController::class);

        // Login
        $app->get('/', \App\Controllers\HomeController::class)->setName('home');
        $app->post('/login', \App\Controllers\LoginController::class)->setName('login');

        // Admin
        $app->group( '', function($app){
            $app->get('/addclient', \App\Controllers\AddClientController::class)->setName('addclient');
            $app->get('/adduser', \App\Controllers\AddUserController::class)->setName('adduser');
            $app->post('/newclient', \App\Controllers\NewClientController::class)->setName('newclient');
            $app->post('/newuser', \App\Controllers\NewUserController::class)->setName('newuser');
            $app->map(['GET','POST'],'/validate/{id:[0-9]+}/{token:\??[0-9a-zA-Z-]*}', \App\Controllers\RegisterClientController::class)->setName('validate');
            $app->map(['GET','POST'],'/register/{id:[0-9]+}/{token:\??[0-9a-zA-Z-]*}', \App\Controllers\RegisterUserController::class)->setName('register');
        })->add($container->get('csrf'))->add(new \App\Middleware\LoginMiddleware($app));
        
        // Webhook
        $app->get('/1/{token:[0-9a-zA-Z-]*}', \App\Controllers\MangopayWebhookController::class)->setName('webhooks');

        // Main Controller Class def
        $payment_controller_class = \App\Controllers\MangopayPaymentController::class;
        
        // Payments
        $app->group( '', function($app) use($payment_controller_class){
            $app->get('/{token:[0-9a-zA-Z-]*}/{amount:[0-9]*}/{product:[0-9a-zA-Z-_]+}', $payment_controller_class.':start')->setName('payment_start');
            $app->post('/identify', $payment_controller_class.':identify')->setName('payment_identify');
            $app->post('/legal', $payment_controller_class.':legal')->setName('payment_legal');
            $app->post('/finalize', $payment_controller_class.':finalize')->setName('payment_finalize');
            $app->post('/accepted', $payment_controller_class.':accepted')->setName('payment_accepted');
            $app->post('/addcard', $payment_controller_class.':addcard')->setName('payment_addcard');
        })->add($container->get('csrf'))->add(new \App\Middleware\HttpReferrerMiddleware($app));
        
        // return url
        $app->get('/redirect/{token:[0-9a-zA-Z-]*}', $payment_controller_class.':redirect')->setName('payment_redirect');
        $app->get('/cardreg/{token:[0-9a-zA-Z-]*}', $payment_controller_class.':cardreg')->setName('payment_cardreg');

        // check url
        $app->get('/check/{token:[0-9a-zA-Z-]*}', $payment_controller_class.':check')->setName('payment_check');

        // print url
        $app->get('/print/{token:[0-9a-zA-Z-]*}', $payment_controller_class.':print')->setName('payment_print');

        // Infos
        $app->get('/infos', function($request, $response, $args){
            /* ob_start();
            phpinfo();
            $content = ob_get_contents();
            ob_end_flush();
            return $response->write($content); */
            $notFoundHandler = $this->notFoundHandler;
            return $notFoundHandler($request, $response);
        });
        
        // Debug
        $app->get('/debug', function($request, $response, $args){
            $notFoundHandler = $this->notFoundHandler;
            return $notFoundHandler($request, $response);
        });

    }
}
