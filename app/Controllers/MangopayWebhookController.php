<?php

namespace App\Controllers;

class MangopayWebhookController extends \Core\Controller
{
    public function __invoke($request, $response, $args)
    {
        $status = 'SUCCESS';
        $message = 'EVENT_REQUEST_SUCCEEDED';
        $ip = $request->getServerParam('REMOTE_ADDR');
        $c_token = $args['token'];
        $request_query = $request->getUri()->getQuery();
        $params = \Util\Tools::queryGetValues($request_query);
        $event_type = $params['EventType']??'';
        $ressource_id = $params['RessourceId']??'';
        $this->logger->info('['.$ip.'] WEBHOOK_REQUEST_RECEIVED -> REQUEST_QUERY ',$params);
        //$this->logger->info('['.$ip.'] WEBHOOK_REQUEST_RECEIVED -> EVENT_TYPE '.$event_type);
        //$this->logger->info('['.$ip.'] WEBHOOK_REQUEST_RECEIVED -> RESSOURCE_ID '.$ressource_id);
        if($client=$this->getClient($c_token)){
            $ressource = $this->getRessource($client,$event_type,$ressource_id);
            if(is_object($ressource) && $ressource->ResultMessage=='Success'){
                $e_token = $ressource->Tag;
                if($event=$this->getEvent($e_token)){
                    switch($event_type){
                        case \MangoPay\EventType::PayinNormalCreated:
                            if($ressource->Status==\MangoPay\PayInStatus::Created){
                                $event->pikey = $ressource_id;
                                $message = $event_type.' -> PAYIN_ID '.$ressource_id;
                            }
                        break;
                        case \MangoPay\EventType::PayinNormalSucceeded:
                            $transfer = $this->initiateTransfer($client,$event);
                            if(is_object($transfer) && $transfer->Status==\MangoPay\TransactionStatus::Created){
                                $message = $event_type.' -> TRANSFER_CREATED -> ID '.$transfer->Id;
                            }else{
                                $status = 'ERROR';
                                $message = 'CREATE_TRANSFER_FAILED '.(is_string($transfer)?$transfer:$transfer->ResultMessage);
                                $this->sendBuyerMail($event,\MangoPay\TransactionStatus::Failed,$message);
                            }
                        break;
                        case \MangoPay\EventType::PayinNormalFailed:
                            if($ressource->Status==\MangoPay\PayInStatus::Failed){
                                $status = 'ERROR';
                                $message = $event_type.' -> PAYIN_ID '.$ressource_id;
                                $this->sendBuyerMail($event,\MangoPay\PayInStatus::Failed,$message);
                            }
                        break;
                        case \MangoPay\EventType::TransferNormalCreated:
                            if($ressource->Status==\MangoPay\TransactionStatus::Created){
                                $event->trkey = $ressource_id;
                                $message = $event_type.' -> TRANSFER_ID '.$ressource_id;
                            }
                        break;
                        case \MangoPay\EventType::TransferNormalSucceeded:
                            $payout = $this->initiatePayout($client,$event);
                            if(is_object($payout) && $payout->Status==\MangoPay\PayOutStatus::Created){
                                $message = $event_type.' -> PAYOUT_CREATED -> ID '.$ressource_id;
                            }else{
                                $status = 'ERROR';
                                $message = 'CREATE_PAYOUT_FAILED '.(is_string($payout)?$payout:$payout->ResultMessage);
                                $this->sendBuyerMail($event,\MangoPay\TransactionStatus::Failed,$message);
                            }
                        break;
                        case \MangoPay\EventType::TransferNormalFailed:
                            if($ressource->Status==\MangoPay\TransactionStatus::Failed){
                                $status = 'ERROR';
                                $message = $event_type.' -> TRANSFER_ID '.$ressource_id;
                                $this->sendBuyerMail($event,\MangoPay\TransactionStatus::Failed);
                            }
                        break;
                        case \MangoPay\EventType::PayoutNormalCreated:
                            if($ressource->Status==\MangoPay\PayOutStatus::Created){
                                $event->pokey = $ressource_id;
                                $message = $event_type.' -> PAYOUT_ID '.$ressource_id;
                            }
                        break;
                        case \MangoPay\EventType::PayoutNormalSucceeded:
                            if($ressource->Status==\MangoPay\PayOutStatus::Succeeded){
                                $message = $event_type.' -> PAYOUT_ID '.$ressource_id;
                                $this->sendBuyerMail($event,\MangoPay\PayOutStatus::Succeeded);
                                $this->sendCellerMail($event);
                            }
                        break;
                        case \MangoPay\EventType::PayoutNormalFailed:
                            if($ressource->Status==\MangoPay\PayOutStatus::Failed){
                                $status = 'ERROR';
                                $message = $event_type.' -> PAYOUT_ID '.$ressource_id;
                                $this->sendBuyerMail($event,\MangoPay\PayOutStatus::Failed);
                            }
                        break;
                    }
                    $event->status = $event_type;
                    $event->save();
                }else{
                    $status = 'ERROR';
                    $message = 'EVENT_NOT_FOUND -> TOKEN '.$e_token;
                }
            }else{
                $status = 'ERROR';
                $message = 'NOT_VALID_RESSOURCE '.(\is_string($ressource)?$ressource:$ressource->ResultMessage);
            }
        }else{
            $status = 'ERROR';
            $message = 'CLIENT_NOT_FOUND -> TOKEN: '.$c_token;
        }
        $this->logger->info('['.$ip.'] STATUS_'.$status.' -> MESSAGE '.$message);
        $result = ['status'=>$status,'message'=>$message];
        return $response->withJson($result)->withStatus(200);
    }

    private function getClient($token='')
    {
        try{
            $client = \App\Models\Client::where('uuid',$token)->firstOrFail();
            return $client;
        }catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e){
            return null;
        }
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

    private function initiateTransfer($client,$event)
    {
        $buyer = $event->buyer;
        $user = $buyer->user;
        $client_akey = $client->akey;
        $client_ckey = $client->ckey;
        $settings = $this->settings['mangopay'];
        $options_datas = [
            'Tag' => $event->token,
            'AuthorId' => $buyer->ukey,
            'DebitedFunds' => $event->amount,
            'Fees' => 0,
            'DebitedWalletId' => $buyer->wkey,
            'CreditedWalletId' => $user->wkey
        ];
        $transfer = \Util\MangopayUtility::createTransfer($client_ckey,$client_akey,$settings['tempdir'],$options_datas);
        return $transfer;
    }

    private function initiatePayout($client,$event)
    {
        $buyer = $event->buyer;
        $user = $buyer->user;
        $client_akey = $client->akey;
        $client_ckey = $client->ckey;
        $settings = $this->settings['mangopay'];
        $options_datas = [
            'Tag' => $event->token,
            'AuthorId' => $buyer->ukey,
            'PaymentType' => \MangoPay\PayOutPaymentType::BankWire,
            'BankWireRef' => \Util\MangopayUtility::DEFAULT_BANK_STATEMENT,
            'BankAccountId' => $user->bkey,
            'DebitedFunds' => $event->amount,
            'Fees' => 0,
            'DebitedWalletId' => $buyer->wkey
        ];
        $payout = \Util\MangopayUtility::createPayout($client_ckey,$client_akey,$settings['tempdir'],$options_datas);
        return $payout;
    }

    private function getRessource($client,$event_type,$ressource_id)
    {
        if(!empty($ressource_id) && !empty($event_type)){
            $settings = $this->settings['mangopay'];
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

    private function sendBuyerMail($event,$status,$error='')
    {
        $buyer = $event->buyer;
        $user = $buyer->user;

        $b_name = \ucfirst($buyer->first_name).' '.\ucfirst($buyer->last_name);

        $event_tpl = [
            'SUCCEEDED' => 'Email/email-succeed.html.twig',
            'CREATED' => 'Email/email-pending.html.twig',
            'FAILED' => 'Email/email-rejected.html.twig'
        ];

        $subject_tpl = [
            'SUCCEEDED' => $b_name.': Merci pour votre achat',
            'CREATED' => $b_name.': Votre payement est en cours de traitement',
            'FAILED' => $b_name.': '.$error
        ];

        $template = $event_tpl[$status];
        $subject = $subject_tpl[$status];

        $event_date = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $event->updated_at);

        $amount = number_format((float) $event->amount/100, 2, ',', ' ');
        
        $data = [
            'name' => $b_name,
            'product' => $event->product,
            'method' => $event->method,
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

    private function sendCellerMail($event)
    {
        $buyer = $event->buyer;
        $user = $buyer->user;

        $template = 'Email/email-recept.html.twig';
        $subject = 'Un nouveau payement est arrivé';

        $event_date = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $event->updated_at);

        $amount = number_format((float) $event->amount/100, 2, ',', ' ');
        
        $data = [
            'name' => \ucfirst($user->name),
            'product' => $event->product,
            'method' => $event->method,
            'client_name' => \ucfirst($buyer->first_name).' '.\ucfirst($buyer->last_name),
            'client_email' => $buyer->email,
            'amount' => $amount.' &euro;',
            'token' => $event->token,
            'datetime' => $event_date->format('d/m/Y H:i:s')
        ];
        
        $content = $this->view->fetch($template,$data);

        $mailer = new \Util\PhpMailer();
        return $mailer->send($user->email,$subject,$content);
    }
}