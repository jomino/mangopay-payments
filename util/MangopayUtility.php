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

        switch($options['PersonType']){
            case \MangoPay\PersonType::Natural:
                $user = new \MangoPay\UserNatural();
                $udatas = [
                    'PersonType',
                    'KYCLevel',
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
                    'PersonType',
                    'KYCLevel',
                    'LegalPersonType',
                    'Name',
                    'Email',
                    'LegalRepresentativeBirthday',
                    'LegalRepresentativeNationality',
                    'LegalRepresentativeCountryOfResidence',
                    'LegalRepresentativeFirstName',
                    'LegalRepresentativeLastName'
                ];
            break;
            default:
                return false;
        }

        if(sizeof($options)==sizeof($udatas)){

            foreach ($udatas as $key) {
                if(isset($options[$key]) && property_exists(get_class($user),$key)){
                    $user->{$key} = $options[$key];
                }
            }

            try {
                $response = $api->Users->create($user);
                return (int) $response->Id;
            } catch(MangoPay\Libraries\ResponseException $e) {
                return $e->getMessage();
            } catch(MangoPay\Libraries\Exception $e) {
                return $e->getMessage();
            }

        }

        return false;

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
            return $response->Id;
        } catch(MangoPay\Libraries\ResponseException $e) {
            return $e->getMessage();
        } catch(MangoPay\Libraries\Exception $e) {
            return $e->getMessage();
        }

        return false;

    }

    public static function createPayin($ckey,$akey,$tmp_dir,$options=[])
    {
        $api = new \MangoPay\MangoPayApi();
        
        // configuration
        $api->Config->ClientId = $ckey;
        $api->Config->ClientPassword = $akey;
        $api->Config->TemporaryFolder = $tmp_dir;

        $udatas = [
            'PaymentType',
            'ExecutionType',
            'ReturnURL',
            'AuthorId',
            'DebitedFunds',
            'Fees',
            'CreditedWalletId',
            'Culture'
        ];

        if(isset($options['PaymentType']) && $options['PaymentType']==\MangoPay\PayInPaymentType::DirectDebit){
            array_push($udatas,'DirectDebitType');
        }else{
            array_push($udatas,'CardType');
        }

        $payin = new \MangoPay\PayIn();

        if(sizeof($options)==sizeof($udatas)){

            foreach ($udatas as $key) {
                if(isset($options[$key]) && property_exists(get_class($payin),$key)){
                    switch(true){
                        case in_array($key,['DebitedFunds','Fees']):
                            $money = new \MangoPay\Money();
                            $money->Amount = $options[$key];
                            $money->Currency = static::DEFAULT_CURRENCY;
                            $payin->{$key} = $money;
                        break;
                        default:
                            $payin->{$key} = $options[$key];

                    }
                }
            }

            try {
                $response = $api->PayIns->Create($payin);
                return $response;
            } catch(MangoPay\Libraries\ResponseException $e) {
                return $e->getMessage();
            } catch(MangoPay\Libraries\Exception $e) {
                return $e->getMessage();
            }

        }

        return false;

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
        } catch(MangoPay\Libraries\ResponseException $e) {
            return $e->getMessage();
        } catch(MangoPay\Libraries\Exception $e) {
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
            'AuthorId',
            'DebitedFunds',
            'Fees',
            'DebitedWalletId',
            'CreditedWalletId'
        ];

        $transfer = new \MangoPay\Transfer();

        if(sizeof($options)==sizeof($udatas)){

            foreach ($udatas as $key) {
                if(isset($options[$key]) && property_exists(get_class($transfer),$key)){
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
            } catch(MangoPay\Libraries\ResponseException $e) {
                return $e->getMessage();
            } catch(MangoPay\Libraries\Exception $e) {
                return $e->getMessage();
            }

        }

        return false;

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
        } catch(MangoPay\Libraries\ResponseException $e) {
            return $e->getMessage();
        } catch(MangoPay\Libraries\Exception $e) {
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
            'AuthorId',
            'PaymentType',
            'BankWireRef',
            'BankAccountId',
            'DebitedFunds',
            'Fees',
            'DebitedWalletId'
        ];

        $payout = new \MangoPay\PayOut();

        if(sizeof($options)==sizeof($udatas)){

            foreach ($udatas as $key) {
                if(isset($options[$key]) && property_exists(get_class($payout),$key)){
                    switch(true){
                        case in_array($key,['DebitedFunds','Fees']):
                            $money = new \MangoPay\Money();
                            $money->Amount = $options[$key];
                            $money->Currency = static::DEFAULT_CURRENCY;
                            $payout->{$key} = $money;
                        break;
                        default:
                            $payout->{$key} = $options[$key];

                    }
                }
            }

            try {
                $response = $api->PayOuts->Create($payout);
                return $response;
            } catch(MangoPay\Libraries\ResponseException $e) {
                return $e->getMessage();
            } catch(MangoPay\Libraries\Exception $e) {
                return $e->getMessage();
            }

        }

        return false;

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
        } catch(MangoPay\Libraries\ResponseException $e) {
            return $e->getMessage();
        } catch(MangoPay\Libraries\Exception $e) {
            return $e->getMessage();
        }

    }

}