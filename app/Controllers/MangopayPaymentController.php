<?php

namespace App\Controllers;

class MangopayPaymentController extends \Core\Controller
{
    private $errors = [];

    public function start($request, $response, $args)
    {
        $uri = $request->getUri();
        $amount = $args['amount'];
        $product = $args['product'];
        $token = (string) ltrim($uri->getQuery(),'?');
        $ip = $this->session->get(\Util\MangopayUtility::SESSION_REMOTE);
        $domain = $this->session->get(\Util\MangopayUtility::SESSION_DOMAIN);
        if(empty($token) || strlen($token)<2){ $token = ltrim($args['token'],'?'); }
        $this->setSessionVar(\Util\MangopayUtility::SESSION_REFERRER,$token);
        $this->setSessionVar(\Util\MangopayUtility::SESSION_REMOTE,$ip);
        if($this->isValidUser()){
            $this->logger->info('['.$ip.'] PAYMENT_START_SUCCEED '.$domain);
            $this->setSessionVar(\Util\MangopayUtility::SESSION_AMOUNT,$amount);
            $this->setSessionVar(\Util\MangopayUtility::SESSION_PRODUCT,$product);
            $display_amount = number_format((float) $amount/100, 2, ',', ' ');
            return $this->view->render($response, 'Home/paystart.html.twig',[
                'product' => $product,
                'amount' => $display_amount.' &euro;'
            ]);
        }else{
            $this->logger->info('['.$ip.'] NOT_VALID_USER_REFERRER -> TOKEN: '.$token);
            return $response->write($this->getSecurityAlert('NOT_VALID_USER '.$token.' WITH '.$this->session->get(\Util\MangopayUtility::SESSION_DOMAIN)));
        }
    }

    public function identify($request, $response, $args)
    {
        $ip = $this->session->get(\Util\MangopayUtility::SESSION_REMOTE);
        if(false === $request->getAttribute('csrf_status')){
            $this->logger->info('['.$ip.'] PAYMENT_CSRF_ERROR -> EXIT_WITH_403');
            return $response->write($this->getSecurityAlert('CSRF_ERROR'))->withStatus(403);
        }
        if(!$this->isValidUser()){
            $this->logger->info('['.$ip.'] NOT_VALID_USER_REFERRER -> EXIT_WITH_403');
            return $response->write($this->getSecurityAlert('NOT_VALID_USER_REFERRER'))->withStatus(403);
        }
        $payment_type = $request->getParsedBodyParam('payment-type');
        $this->setSessionVar(\Util\MangopayUtility::SESSION_METHOD,$payment_type);
        $this->logger->info('['.$ip.'] PAYMENT_START_IDENTIFY -> METHOD_TYPE: '.$payment_type);
        return $this->view->render($response, 'Home/payidentify.html.twig');
    }

    public function legal($request, $response, $args)
    {
        $ip = $this->session->get(\Util\MangopayUtility::SESSION_REMOTE);
        if(false === $request->getAttribute('csrf_status')){
            $this->logger->info('['.$ip.'] PAYMENT_CSRF_ERROR -> EXIT_WITH_403');
            return $response->write($this->getSecurityAlert('CSRF_ERROR'))->withStatus(403);
        }
        if(!$this->isValidUser()){
            $this->logger->info('['.$ip.'] NOT_VALID_USER_REFERRER -> EXIT_WITH_403');
            return $response->write($this->getSecurityAlert('NOT_VALID_USER_REFERRER'))->withStatus(403);
        }
        $data = [];
        $user = $this->getCurrentUser();
        $person_email = $request->getParsedBodyParam('person-email');
        $is_buyer = $user->buyers()->where('email',$person_email)->count() > 0;
        if($is_buyer){
            $buyer = $user->buyers()->where('email',$person_email)->first();
            $person_type = $buyer->person_type;
            $person_nationality = $buyer->nationality;
            $person_residence = $buyer->residence;
            $data['first_name'] = \ucfirst($buyer->first_name);
            $data['last_name'] = \ucfirst($buyer->last_name);
            $data['birthday'] = $buyer->birthday->format('d/m/Y');
            if($buyer->person_type==\MangoPay\PersonType::Legal){
                $data['legal_type'] = $buyer->legal_type;
                $data['legal_name'] = $buyer->legal_name;
            }
            $this->logger->info('['.$ip.'] PAYMENT_START_LEGAL -> KNOW_BUYER_ID '.$buyer->id,$data);
        }else{
            $person_type = $request->getParsedBodyParam('person-type');
            $person_nationality = $request->getParsedBodyParam('person-national');
            $person_residence = $request->getParsedBodyParam('person-residence');
        }
        $this->setSessionVar(\Util\MangopayUtility::SESSION_PERSON_TYPE,$person_type);
        $this->setSessionVar(\Util\MangopayUtility::SESSION_PERSON_EMAIL,$person_email);
        $this->setSessionVar(\Util\MangopayUtility::SESSION_PERSON_NATIONALITY,$person_nationality);
        $this->setSessionVar(\Util\MangopayUtility::SESSION_PERSON_RESIDENCE,$person_residence);
        $this->logger->info('['.$ip.'] PAYMENT_START_LEGAL -> PERSON_TYPE: '.$person_type);
        switch($person_type){
            case \MangoPay\PersonType::Legal:
                return $this->view->render($response, 'Home/payidentify-legal.html.twig',$data);
            case \MangoPay\PersonType::Natural:
                return $this->view->render($response, 'Home/payidentify-natural.html.twig',$data);
        }
    }

    public function finalize($request, $response, $args)
    {
        $uri = $request->getUri();
        $ip = $this->session->get(\Util\MangopayUtility::SESSION_REMOTE);
        if(false!==$request->getAttribute('csrf_status')){
            if($user=$this->getCurrentUser()){
                if($this->isValidUser($user)){
                    if($buyer=$this->getBuyer($user,$request->getParsedBody())){
                        if($event=$this->createNewEvent($buyer)){
                            $this->logger->info('['.$ip.'] CREATED_BUYER_EVENT -> ID: '.$event->id);
                            $payment_method = $this->session->get(\Util\MangopayUtility::SESSION_METHOD);
                            switch($payment_method){
                                case \Util\MangopayUtility::METHOD_CVM:
                                    $cards_list = $this->getCards($buyer);
                                    if(!is_null($cards_list)){
                                        if(\sizeof($cards_list)==0){
                                            $this->logger->info('['.$ip.'] FINALIZE_BUYER_PROCESS -> NEW_CARD_REG');
                                            return $response->write($this->getCardRegPage($event,$uri));
                                        }else{
                                            $this->logger->info('['.$ip.'] FINALIZE_BUYER_PROCESS -> CARD_LIST: ',$cards_list);
                                            return $this->view->render($response, 'Home/cardscheck.html.twig',[
                                                'cards_list' => $cards_list,
                                                'token' => $event->token
                                            ]);
                                        }
                                    }
                                default:
                                    $payin_response = $this->createPayin($event,$uri);
                                    if(is_object($payin_response) && ($payin_response->Status==\MangoPay\PayInStatus::Created || $payin_response->Status==\MangoPay\PayInStatus::Succeeded)){
                                        return $this->view->render($response, 'Home/payredir.html.twig',[
                                            'redir_url' => \stripslashes($payin_response->ExecutionDetails->RedirectURL)
                                        ]);
                                    }else{
                                        $this->errors[] = is_string($payin_response) ? $payin_response:$payin_response->ResultMessage??'UNKNOW_ERROR';
                                    }
                            }
                        }else{
                            $this->errors[] = 'CANNOT_CREATE_LOCAL_EVENT';
                        }
                    }
                }else{
                    $this->errors[] = 'INVALID_USER_DOMAIN';
                }
            }else{
                $this->errors[] = 'USER_NOT_FOUND';
            }
        }else{
            $this->errors[] = 'CSRF_ERROR';
        }
        $error = implode('<br>',$this->errors);
        $this->logger->info('['.$ip.'] FINALIZE_PROCESS_ERROR -> EXIT_WITH_500: '.$error);
        return $response->write($this->getSecurityAlert($error))->withStatus(500);
    }

    public function cardreg($request, $response, $args)
    {
        $uri = $request->getUri();
        $query = $uri->getQuery();
        $ip = $request->getServerParam('REMOTE_ADDR');
        $event = $this->getEvent($args['token']);
        $params = \Util\Tools::queryGetValues($query);
        $this->logger->info('['.$ip.'] CREATED_CARDGEG_RESPONSE: ',$params);
        $rkey = $this->session->get(\Util\MangopayUtility::SESSION_REGID);
        $rdata = isset($params['data']) ? 'data='.$params['data']:'errorCode='.$params['errorCode'];
        $reg_response = $this->updateCardReg($event,$rkey,$rdata);
        $this->logger->info('['.$ip.'] UPDATE_CARDGEG_RESPONSE: '.\json_encode($reg_response));
        if(!is_null($reg_response) && !empty($reg_response->CardId)){
            $this->setSessionVar(\Util\MangopayUtility::SESSION_CARDID,$reg_response->CardId);
            $payin_response = $this->createPayin($event,$uri);
            if(is_object($payin_response) && ($payin_response->Status==\MangoPay\PayInStatus::Created || $payin_response->Status==\MangoPay\PayInStatus::Succeeded)){
                if(true === (bool) $payin_response->ExecutionDetails->SecureModeNeeded){
                    return $this->view->render($response, 'Home/payredir.html.twig',[
                        'redir_url' => \stripslashes($payin_response->ExecutionDetails->SecureModeRedirectURL)
                    ]);
                }else{
                    return $response->withRedirect($this->router->pathFor('payment_redirect',[
                        'token' => $args['token']
                    ]));
                }
            }else{
                $this->errors[] = is_string($payin_response) ? $payin_response:$payin_response->ResultMessage??'UNKNOW_ERROR';
            }
        }
        $error = implode('<br>',$this->errors);
        return $response->write($this->getSecurityAlert($error))->withStatus(500);
    }

    public function accepted($request, $response, $args)
    {
        $uri = $request->getUri();
        $params = $request->getParsedBody();
        $ip = $this->session->get(\Util\MangopayUtility::SESSION_REMOTE);
        if(false!==$request->getAttribute('csrf_status')){
            $cid = $params['cid'];
            $token = $params['token'];
            $event = $this->getEvent($token);
            $this->setSessionVar(\Util\MangopayUtility::SESSION_CARDID,$cid);
            $payin_response = $this->createPayin($event,$uri);
            if(is_object($payin_response) && ($payin_response->Status==\MangoPay\PayInStatus::Created || $payin_response->Status==\MangoPay\PayInStatus::Succeeded)){
                if(true === (bool) $payin_response->ExecutionDetails->SecureModeNeeded){
                    return $this->view->render($response, 'Home/payredir.html.twig',[
                        'redir_url' => \stripslashes($payin_response->ExecutionDetails->SecureModeRedirectURL)
                    ]);
                }else{
                    return $response->withRedirect($this->router->pathFor('payment_redirect',[
                        'token' => $token
                    ]));
                }
            }else{
                $this->errors[] = is_string($payin_response) ? $payin_response:$payin_response->ResultMessage??'UNKNOW_ERROR';
            }
        }else{
            $this->errors[] = 'CSRF_ERROR';
        }
        $error = implode('<br>',$this->errors);
        $this->logger->info('['.$ip.'] ACCEPTCARD_PROCESS_ERROR -> EXIT_WITH_500: '.$error);
        return $response->write($this->getSecurityAlert($error))->withStatus(500);
    }

    public function addcard($request, $response, $args)
    {
        $uri = $request->getUri();
        $params = $request->getParsedBody();
        $ip = $this->session->get(\Util\MangopayUtility::SESSION_REMOTE);
        if(false!==$request->getAttribute('csrf_status')){
            $event = $this->getEvent($params['token']);
            return $response->write($this->getCardRegPage($event,$uri));
        }else{
            $this->errors[] = 'CSRF_ERROR';
        }
        $error = implode('<br>',$this->errors);
        $this->logger->info('['.$ip.'] ADDCARD_PROCESS_ERROR -> EXIT_WITH_500: '.$error);
        return $response->write($this->getSecurityAlert($error))->withStatus(500);
    }

    public function redirect($request, $response, $args)
    {
        $ip = $request->getServerParam('REMOTE_ADDR');
        $event = $this->getEvent($args['token']);
        $buyer = $event->buyer;
        $user = $buyer->user;
        $method = $event->method;
        $amount = number_format((float) $event->amount/100, 2, ',', ' ');
        $message = '<strong>Produit:</strong> '.$event->product.'<br>';
        $message .= '<strong>Méthode:</strong> '.$method.'<br>';
        $message .= '<strong>Date:</strong> '.$event->updated_at->format('d/m/Y H:i:s').'<br>';
        $message .= '<strong>Bénéficiaire:</strong> '.$user->name.'<br>';
        $message .= '<strong>Montant:</strong> '.$amount.' &euro;<br>';
        $message .= '<strong>ID transaction:</strong> '.$event->token;
        $this->logger->info('['.$ip.'] RECEIVE_PAYMENT_RESULT -> STATUS '.$event->status);
        return $this->view->render($response, 'Home/payresult.html.twig',[
            'bank_logo' => $method,
            'message' => $message,
            'status' => $buyer->status,
            'check_url' => $event->token
        ]);
    }

    public function check($request, $response, $args)
    {
        $title = '';
        $event = $this->getEvent($args['token']);
        $ip = $this->session->get(\Util\MangopayUtility::SESSION_REMOTE);
        if(!is_null($event)){
            $status = $event->payin;
            if($status==\MangoPay\EventType::PayinNormalSucceeded){
                $title = 'Merci, votre payement nous est bien arrivé.';
            }
            if($status==\MangoPay\EventType::PayinNormalCreated){
                $title = 'Merci, votre payement est en cour de traitement.';
            }
            if($status==\MangoPay\EventType::PayinNormalFailed){
                $title = 'Désolé, votre payement ne nous est pas parvenu.';
            }
            $this->logger->info('['.$ip.'] CHECK_PAYMENT_RESPONSE: STATUS -> '.$status);
        }else{
            $this->logger->info('['.$ip.'] CHECK_PAYMENT_ERROR -> EVENT_NOT_FOUND');
        }
        return $response->withJson([
            'status' => $title!='' ? $title : 'UNKNOW'
        ]);
    }

    public function print($request, $response, $args)
    {
        $ip = $this->session->get(\Util\MangopayUtility::SESSION_REMOTE);
        $this->logger->info('['.$ip.'] PRINT_PAYMENT_RESULT -> TOKEN: '.$args['token']);
        $event = $this->getEvent($args['token']);
        $html = $this->getPrintContent($event);
        return $response->withJson([
            'html' => \base64_encode(utf8_decode($html))
        ]);
    }

    private function getPrintContent($event)
    {
        $buyer = $event->buyer;
        $user = $buyer->user;

        $template = 'Print/print-recept.html.twig';

        $amount = \number_format((float) $event->amount/100, 2, ',', ' ');
        
        $data = [
            'name' => $buyer->name,
            'product' => $event->product,
            'method' => $event->method,
            'client_name' => $user->name,
            'client_email' => $user->email,
            'amount' => $amount.' &euro;',
            'token' => $event->token,
            'datetime' => $event->updated_at->format('d/m/Y H:i:s'),
            'error' => ''
        ];

        return $this->view->fetch($template,$data);

    }

    private function setSessionVar($name,$value)
    {
        if($this->session->exists($name)){
            $this->session->delete($name);
        }
        $this->session->set($name,$value);
    }

    private function getCurrentUser($token='')
    {
        if(!empty($token)){
            $s_token = $token;
        }elseif($this->session->exists(\Util\MangopayUtility::SESSION_REFERRER)){
            $s_token = $this->session->get(\Util\MangopayUtility::SESSION_REFERRER);
        }
        try{
            $user = \App\Models\User::where('uuid',$s_token)->firstOrFail();
            return $user;
        }catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e){
            return null;
        }
    }

    private function isValidUser($user=null)
    {
        if(is_null($user)){ $user = $this->getCurrentUser(); }
        if($this->session->exists(\Util\MangopayUtility::SESSION_DOMAIN)){
            $domain = \Util\Tools::getTLD($this->session->get(\Util\MangopayUtility::SESSION_DOMAIN));
            return \Util\Tools::getTLD($user->name)==$domain && (int) $user->active==1;
        }
        return false;
    }

    private function getBuyer($user,$params)
    {
        $ip = $this->session->get(\Util\MangopayUtility::SESSION_REMOTE);
        $person_email = $this->session->get(\Util\MangopayUtility::SESSION_PERSON_EMAIL);
        $is_buyer = $user->buyers()->where('email',$person_email)->count() > 0;
        if($is_buyer){
            $buyer = $user->buyers()->where('email',$person_email)->first();
            $this->logger->info('['.$ip.'] FOUND_BUYER -> ID: '.$buyer->id);
            return $buyer;
        }else{
            if($buyer=$this->createNewBuyer($user,$params)){
                $client = $user->client;
                $akey = $client->akey;
                $ckey = $client->ckey;
                $person_type = $buyer->person_type;
                $settings = $this->settings['mangopay'];
                $this->logger->info('['.$ip.'] CREATED_BUYER -> ID: '.$buyer->id);
                $this->logger->info('['.$ip.'] CREATED_BUYER_USER -> ID: '.$user->id);
                $this->logger->info('['.$ip.'] CREATED_BUYER_CLIENT -> ID: '.$client->id);
                $kyc_level = $this->getKYCLevel();
                switch($person_type){
                    case \MangoPay\PersonType::Legal:
                        $buyer_options = [
                            'Tag' => $user->name,
                            'PersonType' => $person_type,
                            'KYCLevel' => $kyc_level,
                            'LegalPersonType' => $buyer->legal_type,
                            'Name' => $buyer->legal_name,
                            'Email' => $buyer->email,
                            'LegalRepresentativeBirthday' => $buyer->birthday->timestamp+1,
                            'LegalRepresentativeNationality' => $buyer->nationality,
                            'LegalRepresentativeCountryOfResidence' => $buyer->residence,
                            'LegalRepresentativeFirstName' => $buyer->first_name,
                            'LegalRepresentativeLastName' => $buyer->last_name
                        ];
                    break;
                    case \MangoPay\PersonType::Natural:
                        $buyer_options = [
                            'Tag' => $user->name,
                            'PersonType' => $person_type,
                            'KYCLevel' => $kyc_level,
                            'FirstName' => $buyer->first_name,
                            'LastName' => $buyer->last_name,
                            'Birthday' => $buyer->birthday->timestamp+1,
                            'Nationality' => $buyer->nationality,
                            'CountryOfResidence' => $buyer->residence,
                            'Email' => $buyer->email
                        ];
                    break;
                }
                $this->logger->info('['.$ip.'] CREATE_BUYER_SEND ',$buyer_options);
                $buyer_result = \Util\MangopayUtility::createUser($ckey,$akey,$settings['tempdir'],$buyer_options);
                $this->logger->info('['.$ip.'] CREATE_BUYER_RESULT '.$buyer_result);
                if(is_int($buyer_result)){
                    $buyer->ukey = $buyer_result;
                    $this->logger->info('['.$ip.'] CREATE_BUYER_WALLET_SEND '.$buyer_result);
                    $wallet_result = \Util\MangopayUtility::createWallet($ckey,$akey,$buyer_result,$settings['tempdir']);
                    $this->logger->info('['.$ip.'] CREATE_BUYER_WALLET_RESULT '.$wallet_result);
                    if(is_int($wallet_result)){
                        $buyer->wkey = $wallet_result;
                        $buyer->save();
                        return $buyer;
                    }else{
                        $this->errors[] = $wallet_result;
                    }
                }else{
                    $this->errors[] = $buyer_result;
                }
            }else{
                $this->errors[] = 'CANNOT_CREATE_LOCAL_BUYER';
            }
            return null;
        }
    }

    private function createNewBuyer($user,$params)
    {
        try{
            $buyer = new \App\Models\Buyer();
            $buyer->user()->associate($user);
            $buyer->person_type = $this->session->get(\Util\MangopayUtility::SESSION_PERSON_TYPE);
            $buyer->nationality = $this->session->get(\Util\MangopayUtility::SESSION_PERSON_NATIONALITY);
            $buyer->residence = $this->session->get(\Util\MangopayUtility::SESSION_PERSON_RESIDENCE);
            $buyer->email = $this->session->get(\Util\MangopayUtility::SESSION_PERSON_EMAIL);
            $buyer->legal_type = $params['legal-type'] ?? '';
            $buyer->legal_name = $params['legal-name'] ?? \strtoupper($params['first-name']).' '.\strtoupper($params['last-name']);
            $buyer->first_name = \strtolower($params['first-name']);
            $buyer->last_name = \strtolower($params['last-name']);
            $buyer->birthday = (\Carbon\Carbon::createFromFormat('d/m/Y',$params['birthday']))->format('Y-m-d');
            $buyer->save();
            return $buyer;
        }catch(\Exception $e){
            return null;
        }

    }

    private function getCards($buyer)
    {
        $cards = [];
        $client = $buyer->user->client;
        $akey = $client->akey;
        $ckey = $client->ckey;
        $ukey = $buyer->ukey;
        $settings = $this->settings['mangopay'];
        $result = \Util\MangopayUtility::getCards($ckey,$akey,$settings['tempdir'],$ukey);
        if(is_array($result) || is_object($result)){
            for ($i=0; $i < sizeof((array) $result); $i++) { 
                $card = $result[$i];
                if($card->Validity==\MangoPay\CardValidity::Valid){
                    $cards[] = [
                        'cid' => $card->Id,
                        'exp' => (\Carbon\Carbon::createFromFormat('my',$card->ExpirationDate))->format('m/Y'),
                        'num' => $card->Alias,
                        'bank' => $card->CardProvider
                    ];
                }
            }
            return $cards;
        }else{
            $this->errors[] = is_string($result) ? $result:'UNKNOW_ERROR';
        }
        return null;
    }

    private function getCardRegPage($event,$uri)
    {
        $ip = $this->session->get(\Util\MangopayUtility::SESSION_REMOTE);
        $result = $this->createNewCardReg($event);
        if(!is_null($result) && !empty($result->Id)){
            $this->setSessionVar(\Util\MangopayUtility::SESSION_REGID,$result->Id);
            return $this->view->fetch( 'Home/cardreg.html.twig',[
                'post_url' => $result->CardRegistrationURL,
                'reg_data' => $result->PreregistrationData,
                'key_ref' => $result->AccessKey,
                'ret_url' => $uri->getScheme().'://'.$uri->getHost().$this->router->pathFor('payment_cardreg',[
                    'token' => $event->token
                ]),
                'expiry_years' => \Util\Tools::getExpiryYears()
            ]);
        }
        $error = implode('<br>',$this->errors);
        $this->logger->info('['.$ip.'] CARDREG_PROCESS_ERROR : '.$error);
        return $this->getSecurityAlert($error);
    }

    private function createNewCardReg($event)
    {
        $buyer = $event->buyer;
        $user = $buyer->user;
        $client = $user->client;
        $akey = $client->akey;
        $ckey = $client->ckey;
        $ukey = $buyer->ukey;
        $settings = $this->settings['mangopay'];
        $ip = $this->session->get(\Util\MangopayUtility::SESSION_REMOTE);
        $this->logger->info('['.$ip.'] CREATE_CARDREG_SEND -> USER_ID: '.$ukey);
        $result = \Util\MangopayUtility::createCardReg($ckey,$akey,$ukey,$settings['tempdir']);
        $this->logger->info('['.$ip.'] CREATED_CARDREG_RESULT: '.\json_encode($result));
        if(is_object($result) && $result->Status==\MangoPay\CardRegistrationStatus::Created){
            return $result;
        }else{
            $this->errors[] = is_string($result) ? $result:$result->ResultMessage??'UNKNOW_ERROR';
        }
        return null;
    }

    private function updateCardReg($event,$rkey,$rdata)
    {
        $buyer = $event->buyer;
        $user = $buyer->user;
        $client = $user->client;
        $akey = $client->akey;
        $ckey = $client->ckey;
        $settings = $this->settings['mangopay'];
        $result = \Util\MangopayUtility::updateCardReg($ckey,$akey,$settings['tempdir'],$rkey,$rdata);
        if(is_object($result) && $result->Status==\MangoPay\CardRegistrationStatus::Validated){
            return $result;
        }else{
            $this->errors[] = is_string($result) ? $result:$result->ResultMessage??'UNKNOW_ERROR';
        }
        return null;
    }

    private function createPayin($event,$uri)
    {
        $payin_options = [];
        $buyer = $event->buyer;
        $user = $buyer->user;
        $client = $user->client;
        $akey = $client->akey;
        $ckey = $client->ckey;
        $settings = $this->settings['mangopay'];
        $payment_method = $this->session->get(\Util\MangopayUtility::SESSION_METHOD);
        switch($payment_method){
            case \Util\MangopayUtility::METHOD_SOFORT:
                $payin_options['PaymentType'] = \MangoPay\PayInPaymentType::DirectDebit;
                $payin_options['ExecutionType'] = \MangoPay\PayInExecutionType::Web;
                $payin_options['DirectDebitType'] = $payment_method;
            break;
            case \Util\MangopayUtility::METHOD_CVM:
                $payin_options['PaymentType'] = \MangoPay\PayInPaymentType::Card;
                $payin_options['ExecutionType'] = \MangoPay\PayInExecutionType::Direct;
                $payin_options['CardId'] = $this->session->get(\Util\MangopayUtility::SESSION_CARDID);
            break;
            default:
                $payin_options['PaymentType'] = \MangoPay\PayInPaymentType::Card;
                $payin_options['ExecutionType'] = \MangoPay\PayInExecutionType::Web;
                $payin_options['CardType'] = $payment_method;
        }
        $return_url = $uri->getScheme().'://'.$uri->getHost().$this->router->pathFor('payment_redirect',[
            'token' => $event->token
        ]);
        $payin_options = array_merge( $payin_options, [
            'Tag' => $event->token,
            'ReturnURL' => $return_url,
            'AuthorId' => $buyer->ukey,
            'DebitedFunds' => $event->amount,
            'Fees' => $this->getFees(),
            'CreditedWalletId' => $buyer->wkey,
            'Culture' => $this->language ? \strtoupper($this->language):'EN'
        ]);
        $ip = $this->session->get(\Util\MangopayUtility::SESSION_REMOTE);
        $this->logger->info('['.$ip.'] CREATE_PAYIN_SEND: ',$payin_options);
        $result = \Util\MangopayUtility::createPayin($ckey,$akey,$settings['tempdir'],$payin_options);
        $this->logger->info('['.$ip.'] CREATED_PAYIN_RESULT: '.(is_string($result) ? $result:\json_encode($result)));
        return $result;
    }

    private function createNewEvent($buyer)
    {
        try{
            $event = new \App\Models\Event();
            $event->buyer()->associate($buyer);
            $event->token = \Util\UuidGenerator::v4();
            $event->amount = $this->session->get(\Util\MangopayUtility::SESSION_AMOUNT);
            $event->product = $this->session->get(\Util\MangopayUtility::SESSION_PRODUCT);
            $event->method = $this->session->get(\Util\MangopayUtility::SESSION_METHOD);
            $event->save();
            return $event;
        }catch(\Exception $e){
            return null;
        }

    }

    private function getEvent($token)
    {
        try{
            $event = \App\Models\Event::where('token',$token)->firstOrFail();
            return $event;
        }catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e){
            return null;
        }
    }

    private function getFees($event='')
    {
        return 0;
    }

    private function getKYCLevel($event='')
    {
        return \MangoPay\KycLevel::Light;
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