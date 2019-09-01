<?php

namespace App\Controllers;

class MangopayWebhookController extends \Core\Controller
{
    public function __invoke($request, $response, $args)
    {
        $params = \Util\Tools::queryGetValues($request->getUri()->getQuery());
        $ip = $request->getServerParam('REMOTE_ADDR');
        $this->logger->info('['.$ip.'] WEBHOOK_REQUEST_SUCCEED -> EVENT_TYPE: '.$params['EventType']);
        $result = ['status'=>'success','message'=>'event_registered'];
        return $response->withJson($result)->withStatus(200);
    }
}