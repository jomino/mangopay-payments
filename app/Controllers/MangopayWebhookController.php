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
            $this->logger->info('['.$ip.'] WEBHOOK_REQUEST_RESSOURCE '.\json_encode($ressource));
            if(is_object($ressource)){
                $e_token = $ressource->Tag;
                if($event=$this->getEvent($e_token)){
                    switch($event_type){
                        case \MangoPay\EventType::PayinNormalCreated:
                            if($event->status!=$event_type){
                                $event->status = $event_type;
                                $event->save();
                                $message = $event_type.' -> PAYIN_ID '.$ressource_id;
                            }else{
                                $status = 'ERROR';
                                $message = $event_type.' -> REDONDANT_API_CALL '.$ressource_id;
                            }
                        break;
                        case \MangoPay\EventType::PayinNormalSucceeded:
                            if($event->status!=$event_type){
                                $event->pikey = $ressource_id;
                                $event->status = $event_type;
                                $event->save();
                                $transfer = $this->initiateTransfer($event);
                                if(is_object($transfer)){
                                    $this->logger->info('['.$ip.'] TRANSFER_CREATED '.\json_encode($transfer));
                                    $message = $event_type.' -> TRANSFER_CREATED_ID '.$transfer->Id;
                                }else{
                                    $status = 'ERROR';
                                    $message = 'CREATE_TRANSFER_FAILED '.(is_string($transfer)?$transfer:$transfer->ResultMessage);
                                }
                                $this->sendBuyerMail($event,\MangoPay\PayInStatus::Succeeded);
                            }else{
                                $status = 'ERROR';
                                $message = $event_type.' -> REDONDANT_API_CALL '.$ressource_id;
                            }
                        break;
                        case \MangoPay\EventType::PayinNormalFailed:
                            if($event->status!=$event_type){
                                $event->status = $event_type;
                                $event->save();
                                $status = 'ERROR';
                                $message = $event_type.' -> PAYIN_ID '.$ressource_id;
                                $this->sendBuyerMail($event,\MangoPay\PayInStatus::Failed,$ressource->ResultMessage);
                            }else{
                                $status = 'ERROR';
                                $message = $event_type.' -> REDONDANT_API_CALL '.$ressource_id;
                            }
                        break;
                        case \MangoPay\EventType::TransferNormalCreated:
                            if($event->status!=$event_type){
                                $event->status = $event_type;
                                $event->save();
                                $message = $event_type.' -> TRANSFER_ID '.$ressource_id;
                            }
                        break;
                        case \MangoPay\EventType::TransferNormalSucceeded:
                            if($event->status!=$event_type){
                                $event->trkey = $ressource_id;
                                $event->status = $event_type;
                                $event->save();
                                $payout = $this->initiatePayout($event);
                                if(is_object($payout)){
                                    $this->logger->info('['.$ip.'] PAYOUT_CREATED '.\json_encode($payout));
                                    $message = $event_type.' -> PAYOUT_CREATED_ID '.$payout->Id;
                                }else{
                                    $status = 'ERROR';
                                    $message = 'CREATE_PAYOUT_FAILED '.(is_string($payout)?$payout:$payout->ResultMessage);
                                }
                            }else{
                                $status = 'ERROR';
                                $message = $event_type.' -> REDONDANT_API_CALL '.$ressource_id;
                            }
                        break;
                        case \MangoPay\EventType::TransferNormalFailed:
                            if($event->status!=$event_type){
                                $event->status = $event_type;
                                $event->save();
                                $status = 'ERROR';
                                $message = $event_type.' -> TRANSFER_ID '.$ressource_id;
                            }else{
                                $status = 'ERROR';
                                $message = $event_type.' -> REDONDANT_API_CALL '.$ressource_id;
                            }
                        break;
                        case \MangoPay\EventType::PayoutNormalCreated:
                            if($event->status!=$event_type){
                                $event->status = $event_type;
                                $event->save();
                                $message = $event_type.' -> PAYOUT_ID '.$ressource_id;
                            }else{
                                $status = 'ERROR';
                                $message = $event_type.' -> REDONDANT_API_CALL '.$ressource_id;
                            }
                        break;
                        case \MangoPay\EventType::PayoutNormalSucceeded:
                            if($event->status!=$event_type){
                                $event->status = $event_type;
                                $event->pokey = $ressource_id;
                                $event->save();
                                $message = $event_type.' -> PAYOUT_ID '.$ressource_id;
                                $this->sendCellerMail($event);
                            }else{
                                $status = 'ERROR';
                                $message = $event_type.' -> REDONDANT_API_CALL '.$ressource_id;
                            }
                        break;
                        case \MangoPay\EventType::PayoutNormalFailed:
                            if($event->status!=$event_type){
                                $event->status = $event_type;
                                $event->save();
                                $status = 'ERROR';
                                $message = $event_type.' -> PAYOUT_ID '.$ressource_id;
                                $this->sendClientMail($event,$ressource->ResultMessage);
                            }else{
                                $status = 'ERROR';
                                $message = $event_type.' -> REDONDANT_API_CALL '.$ressource_id;
                            }
                        break;
                        case \MangoPay\EventType::PayoutRefundSucceeded:
                            if($event->status!=$event_type){
                                $event->status = $event_type;
                                $event->save();
                                $status = 'ERROR';
                                $message = $event_type.' -> PAYOUT_ID '.$ressource_id;
                                $this->sendClientMail($event,$ressource->ResultMessage);
                            }else{
                                $status = 'ERROR';
                                $message = $event_type.' -> REDONDANT_API_CALL '.$ressource_id;
                            }
                        break;
                        default:
                            $status = 'ERROR';
                            $message = $event_type.' -> NON_REGISTERED_API_CALL '.$ressource_id;
                    }
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
        return $response->withStatus(200);
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

    private function initiateTransfer($event)
    {
        $buyer = $event->buyer;
        $user = $buyer->user;
        $client = $user->client;
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

    private function initiatePayout($event)
    {
        $buyer = $event->buyer;
        $user = $buyer->user;
        $client = $user->client;
        $client_akey = $client->akey;
        $client_ckey = $client->ckey;
        $settings = $this->settings['mangopay'];
        $options_datas = [
            'Tag' => $event->token,
            'AuthorId' => $user->ukey,
            'PaymentType' => \MangoPay\PayOutPaymentType::BankWire,
            'BankWireRef' => \Util\MangopayUtility::DEFAULT_BANK_STATEMENT,
            'BankAccountId' => $user->bkey,
            'DebitedFunds' => $event->amount,
            'Fees' => 0,
            'DebitedWalletId' => $user->wkey
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
                case \MangoPay\EventType::PayoutRefundSucceeded:
                        return \Util\MangopayUtility::getRefund($client_ckey,$client_akey,$settings['tempdir'],$ressource_id);
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
            \MangoPay\PayInStatus::Succeeded => 'Email/email-succeed.html.twig',
            \MangoPay\PayInStatus::Created => 'Email/email-pending.html.twig',
            \MangoPay\PayInStatus::Failed => 'Email/email-rejected.html.twig'
        ];

        $subject_tpl = [
            \MangoPay\PayInStatus::Succeeded => $user->name.': Merci pour votre achat',
            \MangoPay\PayInStatus::Created => $user->name.': Votre payement est en cours de traitement',
            \MangoPay\PayInStatus::Failed => $user->name.': '.$error
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
        return $mailer->send($buyer->email,$subject,$content);

    }

    private function sendCellerMail($event)
    {
        $buyer = $event->buyer;
        $user = $buyer->user;

        $b_name = \ucfirst($buyer->first_name).' '.\ucfirst($buyer->last_name);

        $template = 'Email/email-recept.html.twig';
        $subject = 'Un nouveau payement est arrivé';

        $event_date = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $event->updated_at);

        $amount = number_format((float) $event->amount/100, 2, ',', ' ');
        
        $data = [
            'name' => $user->name,
            'product' => $event->product,
            'method' => $event->method,
            'client_name' => $b_name,
            'client_email' => \Util\Tools::obfuscGetValues($buyer->email),
            'amount' => $amount.' &euro;',
            'token' => $event->token,
            'datetime' => $event_date->format('d/m/Y H:i:s')
        ];
        
        $content = $this->view->fetch($template,$data);

        $mailer = new \Util\PhpMailer();
        return $mailer->send($user->email,$subject,$content);
    }

    private function sendClientMail($event,$error='')
    {
        $buyer = $event->buyer;
        $user = $buyer->user;
        $client = $user->client;

        $event_date = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $event->updated_at);

        $template = 'Email/email-client.html.twig';
        $subject = 'Incident lors d\'un payement';
        
        $data = [
            'name' => $client->name,
            'user_name' => $user->name,
            'event_date' => $event_date->format('d/m/Y H:i:s'),
            'error' => $error,
        ];
        
        $content = $this->view->fetch($template,$data);

        $mailer = new \Util\PhpMailer();
        return $mailer->send($client->email,$subject,$content);

    }
}