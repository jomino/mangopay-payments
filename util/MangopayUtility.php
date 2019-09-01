<?php

namespace Util;

class MangopayUtility
{

    const METHOD_BANCONTACT = 'bancontact';
    const METHOD_SOFORT = 'sofort';
    const METHOD_IDEAL = 'ideal';
    const METHOD_IBAN = 'iban';

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
    const SESSION_OAUTH = 'bearer_token';
    const SESSION_TIMEOUT = 'bearer_timeout';

    const EVENT_PAYIN_CREATED = 'PAYIN_NORMAL_CREATED';
    const EVENT_PAYIN_SUCCEEDED = 'PAYIN_NORMAL_SUCCEEDED';
    const EVENT_PAYIN_FAILED = 'PAYIN_NORMAL_FAILED';

    const EVENT_PAYOUT_CREATED = 'PAYOUT_NORMAL_CREATED';
    const EVENT_PAYOUT_SUCCEEDED = 'PAYOUT_NORMAL_SUCCEEDED';
    const EVENT_PAYOUT_FAILED = 'PAYOUT_NORMAL_FAILED';

    const EVENT_TRANSFER_CREATED = 'TRANSFER_NORMAL_CREATED';
    const EVENT_TRANSFER_SUCCEEDED = 'TRANSFER_NORMAL_SUCCEEDED';
    const EVENT_TRANSFER_FAILED = 'TRANSFER_NORMAL_FAILED';

    public static function createWebhooks($ckey,$akey,$wh_url,$tmp_dir)
    {
        $api = new \MangoPay\MangoPayApi();
        
        // configuration
        $api->Config->ClientId = $ckey;
        $api->Config->ClientPassword = $akey;
        $api->Config->TemporaryFolder = $tmp_dir;

        $events = [
            static::EVENT_PAYIN_CREATED,
            static::EVENT_PAYIN_SUCCEEDED,
            static::EVENT_PAYIN_FAILED,
            static::EVENT_PAYOUT_CREATED,
            static::EVENT_PAYOUT_SUCCEEDED,
            static::EVENT_PAYOUT_FAILED,
            static::EVENT_TRANSFER_CREATED,
            static::EVENT_TRANSFER_SUCCEEDED,
            static::EVENT_TRANSFER_FAILED
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

}