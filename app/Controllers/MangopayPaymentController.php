<?php

namespace App\Controllers;

class MangopayPaymentController extends \Core\Controller
{

    public function start($request, $response, $args)
    {
        $uri = $request->getUri();
        $amount = $args['amount'];
        $product = $args['product'];
        $token = (string) ltrim($uri->getQuery(),'?');
        $ip = $this->session->get(\Util\MangopayUtility::SESSION_REMOTE);
        if(empty($token) || strlen($token)<2){ $token = ltrim($args['token'],'?'); }
        $this->setSessionVar(\Util\MangopayUtility::SESSION_REFERRER,$token);
        $this->setSessionVar(\Util\MangopayUtility::SESSION_REMOTE,$ip);
        if($this->isValidUser()){
            $this->logger->info('['.$ip.'] PAYMENT_START_SUCCEED');
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
            $data['first_name'] = $buyer->first_name;
            $data['last_name'] = $buyer->last_name;
            $data['birthday'] = $buyer->birthday->format('d/m/Y');
            if($buyer->person_type==\MangoPay\PersonType::Legal){
                $data['legal_type'] = $buyer->legal_type;
                $data['legal_name'] = $buyer->legal_name;
            }
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
        $error = '';
        $settings = $this->settings['mangopay'];
        $ip = $this->session->get(\Util\MangopayUtility::SESSION_REMOTE);
        if(false!==$request->getAttribute('csrf_status')){
            if($user=$this->getCurrentUser()){
                if($this->isValidUser($user)){
                    $person_email = $this->session->get(\Util\MangopayUtility::SESSION_PERSON_EMAIL);
                    $is_buyer = $user->buyers()->where('email',$person_email)->count() > 0;
                    if($is_buyer){
                        $buyer = $user->buyers()->where('email',$person_email)->first();
                        $this->logger->info('['.$ip.'] PAYMENT_FINALIZE_FOUND_BUYER -> ID: '.$buyer->id);
                    }else{
                        $buyer = $this->createNewBuyer($user,$request->getParsedBody());
                        $this->logger->info('['.$ip.'] PAYMENT_FINALIZE_CREATED_BUYER -> ID: '.$buyer->id);
                        $client = $user->client;
                        $akey = $client->akey;
                        $ckey = $client->ckey;
                        $person_type = $buyer->person_type;
                        $this->logger->info('['.$ip.'] CREATED_BUYER_CLIENT -> ID: '.$client->id);
                        $kyc_level = \MangoPay\KycLevel::Light;
                        switch($person_type){
                            case \MangoPay\PersonType::Legal:
                                $buyer_options = [
                                    'PersonType' => $person_type,
                                    'KYCLevel' => $kyc_level,
                                    'LegalPersonType' => $buyer->legal_type,
                                    'Name' => $buyer->legal_name,
                                    'Email' => $buyer->email,
                                    'LegalRepresentativeBirthday' => $buyer->birthday->timestamp,
                                    'LegalRepresentativeNationality' => $buyer->nationality,
                                    'LegalRepresentativeCountryOfResidence' => $buyer->residence,
                                    'LegalRepresentativeFirstName' => $buyer->first_name,
                                    'LegalRepresentativeLastName' => $buyer->last_name
                                ];
                            break;
                            case \MangoPay\PersonType::Natural:
                                $buyer_options = [
                                    'PersonType' => $person_type,
                                    'KYCLevel' => $kyc_level,
                                    'FirstName' => $buyer->first_name,
                                    'LastName' => $buyer->last_name,
                                    'Birthday' => $buyer->birthday->timestamp,
                                    'Nationality' => $buyer->nationality,
                                    'CountryOfResidence' => $buyer->residence,
                                    'Email' => $buyer->email
                                ];
                            break;
                        }
                        $this->logger->info('['.$ip.'] CREATED_BUYER_USER -> DATA ',$buyer_options);
                        $buyer_response = \Util\MangopayUtility::createUser($ckey,$akey,$settings['tempdir'],$buyer_options);
                        if(is_int($buyer_response)){
                            $buyer->ukey = $buyer_response;
                            $wallet_response = \Util\MangopayUtility::createWallet($ckey,$akey,$buyer_response,$settings['tempdir']);
                            if(is_int($wallet_response)){
                                $buyer->wkey = $wallet_response;
                                $buyer->save();
                            }else{
                                $error = $wallet_response;
                            }
                        }else{
                            $error = $buyer_response;
                        }
                    }
                    if(empty($error)){
                        $event = $this->createNewEvent($buyer);
                        $this->logger->info('['.$ip.'] CREATED_BUYER_EVENT -> ID: '.$event->id);
                        $payin_response = $this->createNewPayin($event,$request->getUri());
                        if(is_object($payin_response) && $payin_response->Status==\MangoPay\PayInStatus::Created){
                            return $this->view->render($response, 'Home/payredir.html.twig',[
                                'redir_url' => $payin_response->RedirectURL
                            ]);
                        }else{
                            $error = is_string($payin_response) ? $payin_response:$payin_response->ResultMessage??'UNKNOW_ERROR';
                        }
                    }
                }
            }
        }
        $this->logger->info('['.$ip.'] FINALIZE_PROCESS_ERROR -> EXIT_WITH_500: '.$error);
        return $response->write($this->getSecurityAlert($error))->withStatus(500);
    }

    public function redirect($request, $response, $args)
    {
        $ip = $request->getServerParam('REMOTE_ADDR');
        $event = $this->getEvent($args['token']);
        $user = $event->buyer->user;
        $method = $event->method;
        $event_date = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $event->updated_at);
        $amount = number_format((float) $event->amount/100, 2, ',', ' ');
        $message = '<strong>Produit:</strong> '.$event->product.'<br>';
        $message .= '<strong>Méthode:</strong> '.$method.'<br>';
        $message .= '<strong>Date:</strong> '.$event_date->format('d/m/Y H:i:s').'<br>';
        $message .= '<strong>Bénéficiaire:</strong> '.$user->name.'<br>';
        $message .= '<strong>Montant:</strong> '.$amount.' &euro;<br>';
        $message .= '<strong>ID transaction:</strong> '.$event->token;
        $this->logger->info('['.$ip.'] RECEIVE_PAYMENT_RESULT');
        return $this->view->render($response, 'Home/payresult.html.twig',[
            'bank_logo' => $method,
            'message' => $message,
            'status' => $event->status,
            'check_url' => $event->token
        ]);
    }

    public function check($request, $response, $args)
    {
        $ip = $this->session->get(\Util\StripeUtility::SESSION_REMOTE);
        $event = $this->getEvent($args['token']);
        $status = $event->status;
        $title = '';
        if($status==\MangoPay\EventType::PayoutNormalSucceeded){
            $title = 'Merci, votre payement nous est bien arrivé.';
        }
        if($status==\MangoPay\EventType::PayoutNormalCreated){
            $title = 'Merci, votre payement est en cour de traitement.';
        }
        if($status==\MangoPay\EventType::PayoutNormalFailed){
            $title = 'Désolé, votre payement ne nous est pas parvenu.';
        }
        $this->logger->info('['.$ip.'] CHECK_PAYMENT_RESPONSE: STATUS -> '.$status);
        return $response->withJson([
            'status' => $title
        ]);
    }

    public function print($request, $response, $args)
    {
        $ip = $this->session->get(\Util\StripeUtility::SESSION_REMOTE);
        $this->logger->info('['.$ip.'] PRINT_PAYMENT_RESULT');
        $event = $this->getEvent($args['token']);
        $html = $this->getPrintContent($event);
        return $response->withJson([
            'html' => \base64_encode($html)
        ]);
    }

    private function getPrintContent($event)
    {
        $status = $event->status;
        $user = $event->buyer->user;
        $event_tpl = [
            \MangoPay\EventType::PayoutNormalSucceeded => 'Email/email-pay-succeed.html.twig',
            \MangoPay\EventType::PayoutNormalCreated => 'Email/email-pay-pending.html.twig',
            \MangoPay\EventType::PayoutNormalFailed => 'Email/email-pay-rejected.html.twig'
        ];

        $template = $event_tpl[$status];

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
            $domain = $this->session->get(\Util\MangopayUtility::SESSION_DOMAIN);
            return $user->name==$domain && (int) $user->active==1;
        }
        return false;
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

    private function createNewPayin($event,$uri)
    {
        $payin_options = [];
        $buyer = $event->buyer;
        $user = $buyer->user;
        $client = $user->client;
        $settings = $this->settings['mangopay'];
        $ip = $this->session->get(\Util\MangopayUtility::SESSION_REMOTE);
        $payment_method = $this->session->get(\Util\MangopayUtility::SESSION_METHOD);
        switch($payment_method){
            case \Util\MangopayUtility::METHOD_SOFORT:
                $payment_type = \MangoPay\PayInPaymentType::DirectDebit;
                $payin_options['DirectDebitType'] = $payment_method;
            break;
            default:
                $payment_type = \MangoPay\PayInPaymentType::Card;
                $payin_options['CardType'] = $payment_method;
        }
        $return_url = $uri->getScheme().'://'.$uri->getHost().$this->router->pathFor('payment_redirect',[
            'token' => $event->token
        ]);
        $payin_options = array_merge( $payin_options, [
            'Tag' => $event->token,
            'PaymentType' => $payment_type,
            'ExecutionType' => \MangoPay\PayInExecutionType::Web,
            'ReturnURL' => $return_url,
            'AuthorId' => $buyer->ukey,
            'DebitedFunds' => $event->amount,
            'Fees' => 0,
            'CreditedWalletId' => $buyer->wkey,
            'Culture' => !empty($this->language)? \strtoupper($this->language):'EN'
        ]);
        $akey = $client->akey;
        $ckey = $client->ckey;
        $this->logger->info('['.$ip.'] CREATE_PAYIN -> DATA: ',$payin_options);
        $payin = \Util\MangopayUtility::createPayin($ckey,$akey,$settings['tempdir'],$payin_options);
        return $payin;
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