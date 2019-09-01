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
