<?php

namespace App\Controllers;

use \App\Models\User;
use \App\Models\Client;

class NewUserController extends \Core\Controller
{

    private $errors = [];

    public function __invoke($request, $response, $args)
    {
        $ip = $this->session->get(\Util\MangopayUtility::SESSION_REMOTE);

        $datas = $request->getParsedBody();

        if(false === $request->getAttribute('csrf_status')){
            $this->logger->info('['.$ip.'] NEWUSER_CSRF_REJECTED -> EXIT_WITH_403');
            return $response->write($this->getSecurityAlert())->withStatus(403);
        }elseif(false === $request->getAttribute(\App\Parameters::SECURITY['status'])){
            $this->logger->info('['.$ip.'] NEWUSER_TIMEOUT_REJECTED -> HOME_REDIR');
            return $response->withRedirect($this->router->pathFor('home'));
        }else{

            if($user=$this->createNewUser($datas)){
                $uri = $request->getUri();
                $this->logger->info('['.$ip.'] ADDUSER_SUCCESS -> EMAIL '.$user->email);
                $datas['generated_link'] = $uri->getScheme().'://'.$uri->getHost().'/'.$user->uuid.'/';
            }

            if(sizeof($this->errors)>0){
                $errors = $this->getErrors();
                $this->logger->info('['.$ip.'] ADDUSER_CREATE_ERROR -> WITH_ERRORS: '.$errors);
                $datas['error'] = $errors;
            }

            return $this->view->render($response, 'Home/newuser.html.twig', $datas);

        }
    }

    private function getRelatedClient()
    {
        try{
            $email = $this->session->get(\Util\MangopayUtility::SESSION_LOGIN);
            $client = Client::where('email',$email)->firstOrFail();
            return $client;
        }catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e){
            return null;
        }
    }

    private function createNewUser($datas)
    {
        if($this->validateUser($datas['domain'])){
            if($client=$this->getRelatedClient()){
                try{
                    $user = new User();
                    $user->active = 1;
                    $user->name = $datas['domain'];
                    $user->email = $datas['email'];
                    $user->uuid = \Util\UuidGenerator::v4();
                    $user->ukey = $datas['ukey'];
                    $user->wkey = $datas['wkey'];
                    $user->bkey = $datas['bkey'];
                    $user->client()->associate($client);
                    $user->save();
                    return $user;
                }catch(\Exception $e){
                    $this->errors[] = 'Impossible d\'écrire dans la base de donnée';
                }
            }else{
                $this->errors[] = 'Le client est inexistant';
            }
        }else{
            $this->errors[] = 'Cet utilisateur est déjà inscrit';
        }
        return null;
    }

    private function validateUser($domain)
    {
        try{
            $count = User::where('name',$domain)->count();
            return $count==0;
        }catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e){
            return false;
        }

    }

    private function getErrors()
    {
        $error_str = '';
        $errors = $this->errors;
        array_map(function($error) use(&$error_str){
            $error_str .= $error."\n";
        },$errors);
        return $error_str;
    }

    private function getSecurityAlert()
    {
        $alert = '<h4 class="result mid-red">Alerte de sécurité  <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span></h4>';
        $message = 'Il nous est impossible de valider votre demande.<br>';
        $message .= 'Cela peut arriver dans les cas suivants:<br>';
        $message .= '&nbsp;&nbsp;&nbsp;&nbsp;-&nbsp;Une tentative de ré-utilisation d\'un formulaire.<br>';
        $message .= '&nbsp;&nbsp;&nbsp;&nbsp;-&nbsp;Un autre problème d\'ordre technique.<br>';
        $message .= 'Vous pouvez contacter nos services à l\'adresse <a href="mailto:info@ipefix.com">info@ipefix.com</a>';
        $content = $this->view->fetch('Home/paymess.html.twig',[
            'alert' => $alert,
            'message' => $message
        ]);
        return $content;
    }

}
