<?php

namespace App\Controllers;

class MangopayWebhookController extends \Core\Controller
{
    public function __invoke($request, $response, $args)
    {
        $status = 'success';
        $message = 'event_registered';
        $ip = $request->getServerParam('REMOTE_ADDR');
        $token = $args['token'];
        $params = \Util\Tools::queryGetValues($request->getUri()->getQuery());
        $event_type = $params['EventType']??'';
        $ressource_id = $params['RessourceId']??'';
        $this->logger->info('['.$ip.'] WEBHOOK_REQUEST_RECEIVED -> EVENT_TYPE: '.$event_type);
        if($event=$this->getEvent($token)){
            $ressource = $this->getRessource($event,$event_type,$ressource_id);
            if(is_object($ressource) && $ressource->ResultMessage=='Success'){
                switch($event_type){
                    case \MangoPay\EventType::PayinNormalCreated:
                        $event->pikey = $ressource_id;
                    break;
                    case \MangoPay\EventType::TransferNormalCreated:
                        $event->trkey = $ressource_id;
                    break;
                    case \MangoPay\EventType::PayoutNormalCreated:
                        $event->pokey = $ressource_id;
                    break;
                    case \MangoPay\EventType::PayoutNormalSucceeded:
                        // todo
                    break;
                }
                $event->status = $event_type;
                $event->save();
            }else{
                $status = 'error';
                $message = 'ressource_not_found';
            }
        }else{
            $status = 'error';
            $message = 'event_not_found';
        }
        $result = ['status'=>$status,'message'=>$message];
        return $response->withJson($result)->withStatus(200);
    }

    private function getEvent($token='')
    {
        try{
            $event = \App\Models\Event::where('token',$token)->firstOrFail();
            return $event;
        }catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e){
            return null;
        }
    }

    private function getRessource($event,$event_type,$ressource_id)
    {
        $settings = $this->settings['mangopay'];
        if(!empty($ressource_id) && !empty($event_type)){
            $buyer = $event->buyer;
            $user = $buyer->user;
            $client = $user->client;
            $client_akey = $client->akey;
            $client_ckey = $client->ckey;
            switch(true){
                case \MangoPay\EventType::PayinNormalCreated==$event_type ||
                    \MangoPay\EventType::PayinNormalSucceeded==$event_type ||
                    \MangoPay\EventType::PayinNormalFailed==$event_type:
                return \Util\MangopayUtility::getPayin($client_ckey,$client_akey,$settings['tempdir'],$ressource_id);
                case \MangoPay\EventType::TransferNormalCreated==$event_type ||
                    \MangoPay\EventType::TransferNormalSucceeded==$event_type ||
                    \MangoPay\EventType::TransferNormalFailed==$event_type:
                return \Util\MangopayUtility::getTransfer($client_ckey,$client_akey,$settings['tempdir'],$ressource_id);
                case \MangoPay\EventType::PayoutNormalCreated==$event_type ||
                    \MangoPay\EventType::PayoutNormalSucceeded==$event_type ||
                    \MangoPay\EventType::PayoutNormalFailed==$event_type:
                return \Util\MangopayUtility::getPayout($client_ckey,$client_akey,$settings['tempdir'],$ressource_id);
            }
        }
        return null;
    }

    private function sendClientMail($event,$user,$error='')
    {
        $status = $event->status;
        $event_tpl = [
            \Util\StripeUtility::STATUS_SUCCEEDED => 'Email/email-pay-succeed.html.twig',
            \Util\StripeUtility::STATUS_WAITING => 'Email/email-pay-pending.html.twig',
            \Util\StripeUtility::STATUS_FAILED => 'Email/email-pay-rejected.html.twig'
        ];

        $subject_tpl = [
            \Util\StripeUtility::STATUS_SUCCEEDED => $user->name.': Merci pour votre achat',
            \Util\StripeUtility::STATUS_WAITING => $user->name.': Votre payement est en cours de traitement',
            \Util\StripeUtility::STATUS_FAILED => $user->name.': '.$error
        ];

        $template = $event_tpl[$status];
        $subject = $subject_tpl[$status];

        $event_date = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $event->updated_at);

        $amount = number_format((float) $event->amount/100, 2, ',', ' ');
        
        $data = [
            'name' => $event->name,
            'product' => $event->product,
            'method' => ucfirst($event->method),
            'client_name' => $user->name,
            'client_email' => $user->email,
            'amount' => $amount.' &euro;',
            'token' => $event->token,
            'datetime' => $event_date->format('d/m/Y H:i:s'),
            'error' => $error
        ];
        
        $content = $this->view->fetch($template,$data);

        $mailer = new \Util\PhpMailer();
        return $mailer->send($event->email,$subject,$content);

    }

    private function sendUserMail($event,$user)
    {
        $template = 'Email/email-pay-recept.html.twig';
        $subject = 'Un nouveau payement est arrivé';

        $event_date = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $event->updated_at);

        $amount = number_format((float) $event->amount/100, 2, ',', ' ');
        
        $data = [
            'product' => $event->product,
            'method' => ucfirst($event->method),
            'client_name' => $event->name,
            'client_email' => $event->email,
            'amount' => $amount.' &euro;',
            'token' => $event->token,
            'datetime' => $event_date->format('d/m/Y H:i:s')
        ];
        
        $content = $this->view->fetch($template,$data);


        $mailer = new \Util\PhpMailer();
        return $mailer->send($user->email,$subject,$content);
    }
}