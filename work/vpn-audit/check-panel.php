<?php
require __DIR__.'/../../config/config.php';
require ROOT.'/vendor/autoload.php';
require HELPERS.'/helpers.php';
new FBL\Application();
require_once ROOT.'/plugins/vpn-manager-v2/Plugin.php';
FBL\Language::registerPluginLanguage('vpn-manager-v2',ROOT.'/plugins/vpn-manager-v2/lang');
try {
    $server=(new Fireball\VpnManagerV2\Repositories\ServerRepository())->findWithSecrets(1);
    $config=(new Fireball\VpnManagerV2\Services\ServerSecretService())->clientConfig($server,3,10);
    $client=new Fireball\VpnManagerV2\Clients\ThreeXuiClient($config);
    $inbounds=$client->listInbounds();
    $node=(new Fireball\VpnManagerV2\Repositories\SubscriptionRepository())->connectionForProvisioning(1);
    $verifier=new Fireball\VpnManagerV2\Services\ClientVerifier();
    $credential=(new Fireball\VpnManagerV2\Services\RemoteClientCredentialService())->credential($node);
    $matched=[];
    foreach($inbounds as $inbound) {
        $remote=$verifier->findInInbound($inbound,$credential,(string)$node['client_email']);
        if($remote!==null) {$matched[]=array_intersect_key($remote,array_flip(['enable','expiryTime','limitIp','limitHwid','reset','resetDay','resetMax']));}
    }
    echo json_encode(['status'=>'ok','auth_type'=>$config->authType,'inbounds'=>count($inbounds),'subscription_id'=>1,'matched_client_state'=>$matched],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch(Throwable $e) {
    echo json_encode(['status'=>'error','type'=>get_class($e),'message'=>$e instanceof Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception?$e->getMessage():'Panel check failed'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
}
