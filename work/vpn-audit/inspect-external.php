<?php
require __DIR__.'/../../config/config.php';
$dsn='mysql:host='.DB_SETTINGS['host'].';dbname='.DB_SETTINGS['database'].';charset='.DB_SETTINGS['charset'];
if(!empty(DB_SETTINGS['port']))$dsn.=';port='.(int)DB_SETTINGS['port'];
$db=new PDO($dsn,DB_SETTINGS['username'],DB_SETTINGS['password'],DB_SETTINGS['options']);
foreach(['vpn_v2_external_sources','vpn_v2_subscription_items'] as $table) {
 $columns=$db->query('SHOW COLUMNS FROM '.$table)->fetchAll(PDO::FETCH_COLUMN);
 $safe=array_values(array_intersect($columns,['id','subscription_id','parent_subscription_id','child_subscription_id','connection_id','source_type','type','is_enabled','status','last_sync_at','synced_at','config_count','ownership_type']));
 echo json_encode([$table=>$db->query('SELECT '.implode(',',$safe).' FROM '.$table.' LIMIT 20')->fetchAll(PDO::FETCH_ASSOC)]).PHP_EOL;
}
