<?php
declare(strict_types=1);
spl_autoload_register(static function(string $class):void{
 $prefix='Fireball\\VpnManagerV2\\';
 if(str_starts_with($class,$prefix))require dirname(__DIR__).'/plugins/vpn-manager-v2/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
});
final class FireballPluginVpnManagerV2{public static function t(string $key):string{return $key;}}
use Fireball\VpnManagerV2\Services\XrayAutoConfigService;
use Fireball\VpnManagerV2\Services\RoscomVpnRouting;
use Fireball\VpnManagerV2\Services\VpnSubscriptionEndpointService;
use Fireball\VpnManagerV2\Support\XrayAutoSettings;
$uris=[];
for($i=1;$i<=3;$i++){
 $key=rtrim(strtr(base64_encode(str_repeat(chr($i),32)),'+/','-_'),'=');
 $uris[]='vless://00000000-0000-4000-8000-00000000000'.$i.'@192.0.2.'.$i.':443?security=reality&type=tcp&encryption=none&flow=xtls-rprx-vision&fp=chrome&sni=www.microsoft.com&pbk='.$key.'&sid=0'.$i.'#Germany-'.$i;
}
$settings=array_replace(XrayAutoSettings::defaults(),['enabled'=>true,'routing'=>'roscomvpn','fallback'=>'first','strategy'=>'leastLoad']);
$builder=new XrayAutoConfigService();$result=$builder->build($uris,$settings,'Test Auto Select');$profile=$result['profile'];
if(($argv[1]??'')==='export'){echo json_encode($profile,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),PHP_EOL;return;}
if(($argv[1]??'')==='profiles-export'){
 echo json_encode($builder->buildProfiles($uris,array_replace($settings,['manual_servers'=>true]),'Auto Select',$uris)['profiles'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),PHP_EOL;return;
}
$count=0;$check=static function(bool $ok,string $message)use(&$count):void{$count++;if(!$ok)throw new RuntimeException($message);};
$check($result['count']===3,'All three authorized IP nodes exported');
$check($profile['routing']['balancers'][0]===['tag'=>'auto','selector'=>['vpn-node-'],'fallbackTag'=>'vpn-block','strategy'=>['type'=>'leastPing']],'Roscom always uses leastPing and block fallback');
$check(isset($profile['observatory'])&&!isset($profile['burstObservatory']),'Native observatory is used');
$check($profile['routing']['domainStrategy']==='IPIfNonMatch','IPIfNonMatch preserved');
$source=(new RoscomVpnRouting())->source();$seen=[];
foreach($profile['routing']['rules'] as $rule){
 if(isset($rule['domain']))$seen[]=[$rule['domain'],$rule['outboundTag']??$rule['balancerTag']];
}
$check($seen===[[$source['BlockSites'],'vpn-block'],[$source['ProxySites'],'auto'],[$source['DirectSites'],'direct']],'Exact ordered original domain rules');
$rules=$profile['routing']['rules'];$last=end($rules);
$check($last['ip']===['0.0.0.0/0','::/0']&&!isset($last['network'])&&$last['balancerTag']==='auto','Catch-all permits IPIfNonMatch second pass');
$check($profile['dns']['hosts']===$source['DnsHosts'],'Original static DNS hosts');
$check($profile['dns']['servers'][0]['address']==='https://8.8.8.8/dns-query'&&$profile['dns']['servers'][0]['finalQuery']===true,'Remote DoH has no cross-fallback');
$check($profile['dns']['servers'][1]['address']==='https://77.88.8.8/dns-query'&&$profile['dns']['servers'][1]['skipFallback']===true,'Domestic DoH only for explicit domains');
$direct=array_values(array_filter($profile['outbounds'],static fn(array $o):bool=>$o['tag']==='direct'))[0];
$check($direct['settings']['finalRules'][0]===['action'=>'block','ip'=>['8.8.8.8'],'port'=>'443'],'Defensive direct DNS block');
$check($builder->build([str_replace('192.0.2.1','endpoint.example',$uris[0])],$settings,'test')['count']===0,'Domain endpoint cannot introduce a bootstrap loop');
$empty=$builder->build([],$settings,'none')['profile'];
$check(count($empty['outbounds'])===1&&$empty['outbounds'][0]['protocol']==='blackhole'&&!isset($empty['dns']),'No eligible nodes stays fail closed');
$reflection=new ReflectionMethod(VpnSubscriptionEndpointService::class,'xrayResponse');
$allSettings=['xray_auto'=>$settings,'subscription_name'=>'My VPN'];
$response=$reflection->invoke(new VpnSubscriptionEndpointService(),['expires_at'=>null],$allSettings,[],$uris);
$check($response->status===200&&$response->headers['X-Fireball-VPN-Routing']==='RoscomVPN','Main Xray endpoint applies Roscom');
$check($response->headers['profile-title']==='base64:'.base64_encode('My VPN'),'Configured subscription name preserved');
$check($response->headers['dns-from-json-enable']==='true'&&str_starts_with($response->headers['routing'],'happ://routing/onadd/'),'JSON is bundled with client Geo metadata');
$payload=json_decode(base64_decode(substr($response->headers['routing'],strlen('happ://routing/onadd/'))),true);
$check($payload['Geoipurl']===$source['Geoipurl']&&$payload['Geositeurl']===$source['Geositeurl'],'Original Roscom asset URLs delivered');
$check(json_decode($response->body,true)[0]['routing']['balancers'][0]['tag']==='auto','Endpoint delivers the production builder result');
$incy=$reflection->invoke(new VpnSubscriptionEndpointService(),[], $allSettings,[],$uris,'active',true);
$check($incy->status===409&&json_decode($incy->body,true)['error']==='incy_roscomvpn_dns_patch','Unsafe Incy JSON explicitly rejected');
$check(XrayAutoSettings::defaults()['routing']==='none','Existing Auto Select stays unchanged until opt-in');
$check(XrayAutoSettings::normalize(['routing'=>'roscomvpn'])['routing']==='roscomvpn','Routing option persists');
$bundle=$builder->buildProfiles($uris,array_replace($settings,['manual_servers'=>true]),'Test Auto Select',$uris);
$check(count($bundle['profiles'])===4&&$bundle['manual_count']===3,'One Auto Select plus all three manual profiles');
$check($bundle['profiles'][0]===$result['profile'],'Original Auto Select configuration preserved');
foreach(array_slice($bundle['profiles'],1) as $i=>$manual){
 $proxy=array_values(array_filter($manual['outbounds'],static fn(array $o):bool=>$o['protocol']==='vless'));
 $check(count($proxy)===1&&$proxy[0]['settings']['vnext'][0]['address']==='192.0.2.'.($i+1),'Manual profile pins exactly one server');
 $check($manual['outbounds'][0]['protocol']==='vless'&&$manual['remarks']==='Germany-'.($i+1),'Manual profile has real primary outbound and server name');
}
$check(count($builder->buildProfiles($uris,$settings,'Auto',$uris)['profiles'])===1,'Opt-in required before existing subscribers see manual profiles');
$limited=$builder->buildProfiles([$uris[0]],array_replace($settings,['manual_servers'=>true]),'Auto',$uris);
$check($limited['count']===1&&$limited['manual_count']===3,'Manual tariff entries are independent of Auto Select node limit');
$allSettings['xray_auto']['manual_servers']=true;
$combined=$reflection->invoke(new VpnSubscriptionEndpointService(),[],$allSettings,[],$uris,'active',false,$uris);
$check(count(json_decode($combined->body,true))===4&&$combined->headers['X-Fireball-Xray-Manual']==='3','Current endpoint emits full profile array');
$manualOnly=$reflection->invoke(new VpnSubscriptionEndpointService(),[],$allSettings,[],[],'active',false,$uris);
$check($manualOnly->headers['X-Fireball-VPN-Routing']==='RoscomVPN'
 && count(json_decode($manualOnly->body,true))===4,'Manual profiles retain Geo metadata when Auto Select has no nodes');
$sameCredential=array_map(static fn(string $uri):string=>preg_replace('~00000000-0000-4000-8000-00000000000[123]~','00000000-0000-4000-8000-000000000001',$uri),$uris);
$stable=$builder->buildProfiles($sameCredential,array_replace($settings,['manual_servers'=>true]),'Auto',$sameCredential);
$check($stable['manual_count']===3&&count(array_unique(array_map('json_encode',array_slice($stable['profiles'],1))))===3,'Shared stable UUID does not collapse distinct servers');
$sameNames=array_map(static fn(string $uri):string=>preg_replace('~#Germany-[123]$~','#Germany',$uri),$uris);
$names=$builder->buildProfiles($sameNames,array_replace($settings,['manual_servers'=>true]),'Auto',$sameNames);
$check(count(array_unique(array_column($names['profiles'],'remarks')))===4,'Distinct full profiles have unambiguous names even with duplicate server labels');
echo json_encode(['status'=>'ok','assertions'=>$count]),PHP_EOL;
