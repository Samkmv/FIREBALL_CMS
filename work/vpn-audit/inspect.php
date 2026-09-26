<?php
require __DIR__ . '/../../config/config.php';
try {
    $dsn = 'mysql:host=' . DB_SETTINGS['host'] . ';dbname=' . DB_SETTINGS['database'] . ';charset=' . DB_SETTINGS['charset'];
    if (!empty(DB_SETTINGS['port'])) {$dsn .= ';port=' . (int)DB_SETTINGS['port'];}
    $db = new PDO($dsn, DB_SETTINGS['username'], DB_SETTINGS['password'], DB_SETTINGS['options']);
    $queries = [
        'clock' => "SELECT NOW() AS database_now, @@session.time_zone AS database_timezone",
        'settings' => "SELECT setting_key, setting_value FROM plugin_settings WHERE plugin_slug='vpn-manager-v2' AND setting_key IN ('sync_enabled','retry_failed_operations','sync_interval_minutes','server_check_interval_minutes')",
        'subscriptions' => "SELECT s.id,s.status,s.expires_at,s.updated_at, COUNT(n.id) nodes, SUM(n.status='active') active_nodes,SUM(n.status='sync_error') failed_nodes FROM vpn_v2_subscriptions s LEFT JOIN vpn_v2_subscription_nodes n ON n.subscription_id=s.id WHERE s.expires_at<=NOW() AND s.status NOT IN ('deleted','deleting') GROUP BY s.id ORDER BY s.expires_at DESC LIMIT 30",
        'counts' => "SELECT status,COUNT(*) count FROM vpn_v2_subscriptions GROUP BY status",
        'nodes' => "SELECT id,subscription_id,status,sync_status,desired_enabled,last_sync_at,updated_at,last_error FROM vpn_v2_subscription_nodes WHERE subscription_id=1",
        'servers' => "SELECT id,name,is_enabled,status,last_sync_at,last_error FROM vpn_v2_servers",
        'events' => "SELECT event_type,subscription_id,node_id,created_at FROM vpn_v2_events WHERE event_type LIKE '%expir%' OR event_type LIKE '%failed%' ORDER BY id DESC LIMIT 20"
    ];
    foreach ($queries as $name=>$sql) {
        try {echo json_encode([$name=>$db->query($sql)->fetchAll(PDO::FETCH_ASSOC)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;}
        catch(Throwable $e) {echo json_encode([$name=>['error_type'=>get_class($e),'code'=>$e->getCode()]]).PHP_EOL;}
    }
    echo json_encode(['php_timezone'=>date_default_timezone_get(),'php_now'=>date(DATE_ATOM)]).PHP_EOL;
} catch (Throwable $e) {fwrite(STDERR,json_encode(['error_type'=>get_class($e),'code'=>$e->getCode()]).PHP_EOL);exit(1);}
