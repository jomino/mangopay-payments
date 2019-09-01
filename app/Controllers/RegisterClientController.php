<?php

namespace App\Controllers;

use \App\Models\Client;

class RegisterClientController extends \Core\Controller
{
    private $errors = [];

    public function __invoke($request, $response, $args)
    {
        $ip = $this->session->get(\Util\MangopayUtility::SESSION_REMOTE);
        $uri = $request->getUri();
        $token = (string) ltrim($uri->getQuery(),'?');
        if(empty($token) || strlen($token)<2){ $token = ltrim($args['token'],'?'); }
        $client_id = (int) $args['id']??0;
        if(empty($token) || $client_id==0){
            $this->logger->info('['.$ip.'] REGISTER_NEWCLIENT_BAD_REQUEST -> EXIT_WITH_403');
            return $response->write($this->getSecurityAlert('BAD_REQUEST'))->withStatus(403);
        }
        if($client=$this->validateClient($token,$client_id)){
            $datas = [ 'name' => $client->name, 'email' => $client->email ];
            if($request->isGet()){
                $template_name = 'validate';
            }else{
                if(false === $request->getAttribute('csrf_status')){
                    $this->logger->info('['.$ip.'] REGISTER_NEWCLIENT_CSRF_REJECTED -> EXIT_WITH_403');
                    return $response->write($this->getSecurityAlert('CSRF_REJECTED'))->withStatus(403);
                }else{
                    $template_name = 'validated';
                    $this->register($client,$request->getParsedBody());
                    $this->setupWebhooks($request,$client);
                }
            }
        }
        if(sizeof($this->errors)>0){
            $errors = $this->getErrors();
            $this->logger->info('['.$ip.'] REGISTER_NEWCLIENT_ERROR -> WITH_ERRORS: '.$errors);
            return $response->write($this->getSecurityAlert($errors));
        }else{
            $this->logger->info('['.$ip.'] REGISTER_NEWCLIENT_SUCCEED -> CLIENT_ID: '.$client->id);
            return $this->view->render($response, sprintf('Home/%s.html.twig',$template_name), $datas);
        }
    }

    private function validateClient($token,$client_id)
    {
        try{
            $client = Client::findOrFail($client_id);
            if($client->uuid == $token){
                $dt_max = \Carbon\Carbon::now()->subHour();
                $dt_reg = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $client->updated_at);
                switch(true){
                    case ($dt_reg->timestamp-$dt_max->timestamp)<0:
                        $this->errors[] = 'La date de validité est dépassée';
                        return null;
                    case $client->active==1:
                        $this->errors[] = 'Votre compte est dèjà actif';
                        return null;
                    default:
                        return $client;
                }
            }else{
                $this->errors[] = 'Ceci n\'est pas un lien valide';
            }
        }catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e){
            $this->errors[] = 'Vous n\'êtes pas un utilisateur enregistrer chez nous';
        }
        return null;
    }

    private function register($client,$args)
    {

        $ckey = $args['ckey'];
        $akey = $args['akey'];

        if(!empty($ckey) && !empty($akey)){
            $client->ckey = $ckey;
            $client->akey = $akey;
            $client->active = 1;
            $client->save();
            return true;
        }else{
            $this->errors[] = 'L\'une et/ou l\'autre des clefs sont manquantes';
        }
        return false;

    }

    private function setupWebhooks($request,$client)
    {
        $akey = $client->akey;
        $ckey = $client->ckey;
        $uri = $request->getUri();
        $settings = $this->settings['mangopay'];
        $wh_url = $uri->getScheme().'://'.$uri->getHost().$this->router->pathFor('webhooks',[
            'token' => $client->uuid
        ]);
        $response = \Util\MangopayUtility::createWebhooks($ckey,$akey,$wh_url,$settings['tempdir']);
        if($response!==true){
            $ip = $this->session->get(\Util\MangopayUtility::SESSION_REMOTE);
            $this->logger->info('['.$ip.'] REGISTER_NEWUSER_WEBHOOK_REJECTED -> ERROR: '.$response);
            $this->errors[] = 'Nous sommes dans l\'impossibilité de vous relié à la plateforme MangoPay';
            return false;
        }else{
            return true;
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

    private function getSecurityAlert($errors='')
    {
        $alert = '<h4 class="result mid-red">Alerte de sécurité  <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span></h4>';
        $message = 'Il nous est impossible de valider votre demande.<br>';
        $message .= 'Cela peut arriver dans les cas suivants:<br>';
        $message .= '&nbsp;&nbsp;&nbsp;&nbsp;-&nbsp;Une tentative de ré-utilisation d\'un formulaire.<br>';
        $message .= '&nbsp;&nbsp;&nbsp;&nbsp;-&nbsp;Un autre problème d\'ordre technique.<br>';
        if(!empty($errors)){
            $message .= '&nbsp;&nbsp;&nbsp;&nbsp;<span class="bold mid-red">';
            $message .= $errors;
            $message .= '</span><br>';
        }
        $message .= 'Vous pouvez contacter nos services à l\'adresse <a href="mailto:info@ipefix.com">info@ipefix.com</a>';
        $content = $this->view->fetch('Home/paymess.html.twig',[
            'alert' => $alert,
            'message' => $message
        ]);
        return $content;
    }
}
