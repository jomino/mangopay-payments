<?php

namespace Util;

class MangopayUtility
{

    const METHOD_BANCONTACT = 'BCMC';
    const METHOD_SOFORT = 'SOFORT';
    const METHOD_IDEAL = 'IDEAL';
    // const METHOD_IBAN = 'iban';

    const DEFAULT_CURRENCY = 'EUR';
    const DEFAULT_COUNTRY = 'BE';
    
    const DEFAULT_BANK_STATEMENT = 'WEB_PAYMENTS';

    const SESSION_LOGIN = 'client_login';
    const SESSION_REMOTE = 'remote';
    const SESSION_DOMAIN = 'domain';
    const SESSION_REFERRER = 'referrer';
    const SESSION_SELECTION = 'selection';
    const SESSION_AMOUNT = 'amount';
    const SESSION_PRODUCT = 'product_ref';
    const SESSION_METHOD = 'payment_type';
    const SESSION_TOKEN = 'event_token';

    const SESSION_PERSON_TYPE = 'person_type';
    const SESSION_PERSON_EMAIL = 'person_email';
    const SESSION_PERSON_NATIONALITY = 'person_nationality';
    const SESSION_PERSON_RESIDENCE = 'person_residence';

    public static function createWebhooks($ckey,$akey,$wh_url,$tmp_dir)
    {
        $api = new \MangoPay\MangoPayApi();
        
        // configuration
        $api->Config->ClientId = $ckey;
        $api->Config->ClientPassword = $akey;
        $api->Config->TemporaryFolder = $tmp_dir;

        $events = [
            \MangoPay\EventType::PayinNormalCreated,
            \MangoPay\EventType::PayinNormalSucceeded,
            \MangoPay\EventType::PayinNormalFailed,
            \MangoPay\EventType::PayoutNormalCreated,
            \MangoPay\EventType::PayoutNormalSucceeded,
            \MangoPay\EventType::PayoutNormalFailed,
            \MangoPay\EventType::PayoutRefundSucceeded,
            \MangoPay\EventType::TransferNormalCreated,
            \MangoPay\EventType::TransferNormalSucceeded,
            \MangoPay\EventType::TransferNormalFailed
        ];

        $success = true;

        try {

            foreach ($events as $event) {

                $hook = new \MangoPay\Hook();
                $hook->EventType = $event;
                $hook->Url = $wh_url;
                
                $result = $api->Hooks->Create($hook);

                $success = $success && empty($result->error);

            }

            return $success;
        
        } catch(\MangoPay\Libraries\ResponseException $e) {
            return $e->GetErrorDetails();
        } catch(\MangoPay\Libraries\Exception $e) {
            return $e->getMessage();
        }
    }

    public static function createUser($ckey,$akey,$tmp_dir,$options=[])
    {
        $api = new \MangoPay\MangoPayApi();
        
        // configuration
        $api->Config->ClientId = $ckey;
        $api->Config->ClientPassword = $akey;
        $api->Config->TemporaryFolder = $tmp_dir;
        
        $default_datas = [
            'PersonType',
            'KYCLevel'
        ];

        switch($options['PersonType']){
            case \MangoPay\PersonType::Natural:
                $user = new \MangoPay\UserNatural();
                $udatas = [
                    'FirstName',
                    'LastName',
                    'Birthday',
                    'Nationality',
                    'CountryOfResidence',
                    'Email'
                ];
            break;
            case \MangoPay\PersonType::Legal:
                $user = new \MangoPay\UserLegal();
                $udatas = [
                    'Name',
                    'Email',
                    'LegalPersonType',
                    'LegalRepresentativeBirthday',
                    'LegalRepresentativeNationality',
                    'LegalRepresentativeCountryOfResidence',
                    'LegalRepresentativeFirstName',
                    'LegalRepresentativeLastName'
                ];
            break;
            default:
                return 'INVALID_PERSON_TYPE';
        }

        $udatas = array_merge($udatas,$default_datas);

        if(sizeof($options)==sizeof($udatas)){

            foreach ($udatas as $key) {
                if(isset($options[$key])){
                    $user->{$key} = $options[$key];
                }
            }

            try {
                $response = $api->Users->create($user);
                return (int) $response->Id;
            } catch(\MangoPay\Libraries\ResponseException $e) {
                return $e->getMessage();
            } catch(\MangoPay\Libraries\Exception $e) {
                return $e->getMessage();
            }

        }

        return 'INVALID_OPTIONS_PAYLOAD';

    }

    public static function createWallet($ckey,$akey,$ukey,$tmp_dir)
    {
        $api = new \MangoPay\MangoPayApi();
        
        // configuration
        $api->Config->ClientId = $ckey;
        $api->Config->ClientPassword = $akey;
        $api->Config->TemporaryFolder = $tmp_dir;

        $wallet = new \MangoPay\Wallet();
        $wallet->Owners = [$ukey];
        $wallet->Currency = static::DEFAULT_CURRENCY;
        $wallet->Description = 'IPEFIX_PAYMENTS_SOLUTION';

        try {
            $response = $api->Wallets->Create($wallet);
            return (int) $response->Id;
        } catch(\MangoPay\Libraries\ResponseException $e) {
            return $e->getMessage();
        } catch(\MangoPay\Libraries\Exception $e) {
            return $e->getMessage();
        }

        return 'UNKNOW_ERROR';

    }

    public static function createPayin($ckey,$akey,$tmp_dir,$options=[])
    {
        $api = new \MangoPay\MangoPayApi();
        
        // configuration
        $api->Config->ClientId = $ckey;
        $api->Config->ClientPassword = $akey;
        $api->Config->TemporaryFolder = $tmp_dir;

        $udatas = [
            'Tag',
            'PaymentType',
            'ExecutionType',
            'AuthorId',
            'DebitedFunds',
            'Fees',
            'CreditedWalletId'
        ];

        if($options['PaymentType']==\MangoPay\PayInPaymentType::DirectDebit){
            $udatas[] = 'DirectDebitType';
        }else{
            $udatas[] = 'CardType';
        }

        $payin = new \MangoPay\PayIn();

        foreach ($udatas as $key) {
            switch(true){
                case in_array($key,['DebitedFunds','Fees']):
                    $money = new \MangoPay\Money();
                    $money->Amount = $options[$key];
                    $money->Currency = static::DEFAULT_CURRENCY;
                    $payin->{$key} = $money;
                break;
                case $key=='CardType':
                    if($options['PaymentType']==\MangoPay\PayInPaymentType::Card){
                        $p_details = new \MangoPay\PayInPaymentDetailsCard();
                        $p_details->CardType = $options['CardType'];
                        $payin->PaymentDetails = $p_details;
                        $payin->CardType = $options['CardType'];
                    }
                    if($options['PaymentType']==\MangoPay\PayInPaymentType::DirectDebit){
                        $p_details = new \MangoPay\PayInPaymentDetailsDirectDebit();
                        $p_details->DirectDebitType = $options['DirectDebitType'];
                        $payin->PaymentDetails = $p_details;
                        $payin->DirectDebitType = $options['DirectDebitType'];
                    }
                break;
                case $key=='ExecutionType':
                    if($options['ExecutionType']==\MangoPay\PayInExecutionType::Web){
                        $exe_type = new \MangoPay\PayInExecutionDetailsWeb();
                        $exe_type->ReturnURL = $options['ReturnURL'];
                        $exe_type->Culture = $options['Culture'];
                        $payin->ExecutionDetails = $exe_type;
                        $payin->ExecutionType = $options['ExecutionType'];
                    }
                break;
                default:
                    $payin->{$key} = $options[$key];

            }
        }

        try {
            $response = $api->PayIns->Create($payin);
            return $response;
        } catch(\MangoPay\Libraries\ResponseException $e) {
            return $e->getMessage();
        } catch(\MangoPay\Libraries\Exception $e) {
            return $e->getMessage();
        }

    }

    public static function getPayin($ckey,$akey,$tmp_dir,$pikey)
    {
        $api = new \MangoPay\MangoPayApi();
        
        // configuration
        $api->Config->ClientId = $ckey;
        $api->Config->ClientPassword = $akey;
        $api->Config->TemporaryFolder = $tmp_dir;

        try {
            $response = $api->PayIns->Get($pikey);
            return $response;
        } catch(\MangoPay\Libraries\ResponseException $e) {
            return $e->getMessage();
        } catch(\MangoPay\Libraries\Exception $e) {
            return $e->getMessage();
        }

    }

    public static function createTransfer($ckey,$akey,$tmp_dir,$options=[])
    {
        $api = new \MangoPay\MangoPayApi();
        
        // configuration
        $api->Config->ClientId = $ckey;
        $api->Config->ClientPassword = $akey;
        $api->Config->TemporaryFolder = $tmp_dir;

        $udatas = [
            'Tag',
            'AuthorId',
            'DebitedFunds',
            'Fees',
            'DebitedWalletId',
            'CreditedWalletId'
        ];

        $transfer = new \MangoPay\Transfer();

        if(sizeof($options)==sizeof($udatas)){

            foreach ($udatas as $key) {
                if(isset($options[$key])){
                    switch(true){
                        case in_array($key,['DebitedFunds','Fees']):
                            $money = new \MangoPay\Money();
                            $money->Amount = $options[$key];
                            $money->Currency = static::DEFAULT_CURRENCY;
                            $transfer->{$key} = $money;
                        break;
                        default:
                            $transfer->{$key} = $options[$key];

                    }
                }
            }

            try {
                $response = $api->Transfers->Create($transfer);
                return $response;
            } catch(\MangoPay\Libraries\ResponseException $e) {
                return $e->getMessage();
            } catch(\MangoPay\Libraries\Exception $e) {
                return $e->getMessage();
            }

        }

        return 'INVALID_OPTIONS_PAYLOAD';

    }

    public static function getTransfer($ckey,$akey,$tmp_dir,$trkey)
    {
        $api = new \MangoPay\MangoPayApi();
        
        // configuration
        $api->Config->ClientId = $ckey;
        $api->Config->ClientPassword = $akey;
        $api->Config->TemporaryFolder = $tmp_dir;

        try {
            $response = $api->Transfers->Get($trkey);
            return $response;
        } catch(\MangoPay\Libraries\ResponseException $e) {
            return $e->getMessage();
        } catch(\MangoPay\Libraries\Exception $e) {
            return $e->getMessage();
        }

    }

    public static function createPayout($ckey,$akey,$tmp_dir,$options=[])
    {
        $api = new \MangoPay\MangoPayApi();
        
        // configuration
        $api->Config->ClientId = $ckey;
        $api->Config->ClientPassword = $akey;
        $api->Config->TemporaryFolder = $tmp_dir;

        $udatas = [
            'Tag',
            'AuthorId',
            'PaymentType',
            'BankAccountId',
            'DebitedFunds',
            'Fees',
            'DebitedWalletId'
        ];

        $payout = new \MangoPay\PayOut();

        foreach ($udatas as $key) {
            if(isset($options[$key])){
                switch(true){
                    case in_array($key,['DebitedFunds','Fees']):
                        $money = new \MangoPay\Money();
                        $money->Amount = $options[$key];
                        $money->Currency = static::DEFAULT_CURRENCY;
                        $payout->{$key} = $money;
                    break;
                    case $key=='BankAccountId':
                        if($options['PaymentType']==\MangoPay\PayOutPaymentType::BankWire){
                            $p_detail = new \MangoPay\PayOutPaymentDetailsBankWire();
                            $p_detail->BankAccountId = $options['BankAccountId'];
                            $p_detail->BankWireRef = $options['BankWireRef'];
                        }
                        $payout->MeanOfPaymentDetails = $p_detail;
                    break;
                    default:
                        $payout->{$key} = $options[$key];

                }
            }
        }

        try {
            $response = $api->PayOuts->Create($payout);
            return $response;
        } catch(\MangoPay\Libraries\ResponseException $e) {
            return $e->getMessage();
        } catch(\MangoPay\Libraries\Exception $e) {
            return $e->getMessage();
        }

        return 'INVALID_OPTIONS_PAYLOAD';

    }

    public static function getPayout($ckey,$akey,$tmp_dir,$pokey)
    {
        $api = new \MangoPay\MangoPayApi();
        
        // configuration
        $api->Config->ClientId = $ckey;
        $api->Config->ClientPassword = $akey;
        $api->Config->TemporaryFolder = $tmp_dir;

        try {
            $response = $api->PayOuts->Get($pokey);
            return $response;
        } catch(\MangoPay\Libraries\ResponseException $e) {
            return $e->getMessage();
        } catch(\MangoPay\Libraries\Exception $e) {
            return $e->getMessage();
        }

    }

    public static function getRefund($ckey,$akey,$tmp_dir,$id)
    {
        $api = new \MangoPay\MangoPayApi();
        
        // configuration
        $api->Config->ClientId = $ckey;
        $api->Config->ClientPassword = $akey;
        $api->Config->TemporaryFolder = $tmp_dir;

        try {
            $response = $api->Refunds->Get($id);
            return $response;
        } catch(\MangoPay\Libraries\ResponseException $e) {
            return $e->getMessage();
        } catch(\MangoPay\Libraries\Exception $e) {
            return $e->getMessage();
        }

    }

}