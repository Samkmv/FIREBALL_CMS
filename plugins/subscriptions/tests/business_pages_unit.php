<?php
declare(strict_types=1);

final class BusinessTestDb
{
    public PDO $pdo;
    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('PRAGMA foreign_keys=ON; CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT); CREATE TABLE subscriptions (id INTEGER PRIMARY KEY, user_id INTEGER, plan_id INTEGER, status TEXT, archived_at TEXT, starts_at TEXT, ends_at TEXT, grace_ends_at TEXT); CREATE TABLE subscription_plan_permissions (plan_id INTEGER, permission_key TEXT, permission_value TEXT); CREATE TABLE subscription_plans (id INTEGER PRIMARY KEY, name TEXT);');
        // Execute the actual additive migration; adapt MySQL DDL syntax for SQLite only.
        $sql = file_get_contents(__DIR__ . '/../migrations/013_add_business_pages.sql');
        $sql = preg_replace('/id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT/', 'id INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        $sql = preg_replace('/PRIMARY KEY \(id\),?/', '', $sql);
        $sql = preg_replace('/UNIQUE KEY \w+ (\([^\n]+\))/', 'UNIQUE $1', $sql);
        $sql = preg_replace('/KEY \w+ \([^\n]+\),/', '', $sql);
        $sql = preg_replace('/\) ENGINE=[^;]+;/', ');', $sql);
        $sql = preg_replace('/\sUNSIGNED/', '', $sql);
        $this->pdo->exec($sql);
        $this->pdo->exec(file_get_contents(__DIR__ . '/../migrations/014_add_business_camera_links.sql'));
        $this->pdo->exec(file_get_contents(__DIR__ . '/../migrations/015_add_business_slug.sql'));
    }
    public function query(string $sql, array $params = []): object
    {
        $stmt=$this->pdo->prepare($sql); $stmt->execute($params);
        return new class($stmt) {
            public function __construct(private PDOStatement $stmt) {}
            public function get(): array { return $this->stmt->fetchAll(PDO::FETCH_ASSOC); }
            public function getOne(): array|false { return $this->stmt->fetch(PDO::FETCH_ASSOC); }
            public function getColumn(): mixed { return $this->stmt->fetchColumn(); }
        };
    }
    public function getInsertId(): int { return (int)$this->pdo->lastInsertId(); }
}
function db(): BusinessTestDb { static $db; return $db ??= new BusinessTestDb(); }
final class FireballPluginSubscriptions { public static function businessPublicEnabled(): bool { return $GLOBALS['businessPublicEnabled'] ?? true; } public static function t(string $key): string { return $key; } public static function viewData(array $data): array { return $data; } public static function tabs(string $key): array { return []; } }
final class FireballPluginCameraManager { public static function camera(int $id): ?array { return in_array($id,[3,4],true) ? ['id'=>$id] : null; } }
$helpers = file_get_contents(__DIR__ . '/../../../helpers/helpers.php');
$slugStart = strpos($helpers, 'function make_slug(');
$slugEnd = strpos($helpers, 'function get_csrf_field(', $slugStart);
eval(substr($helpers, $slugStart, $slugEnd - $slugStart));
require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../src/Search/BusinessSearchProvider.php';
function search_sync_entity(string $provider, int|string $id): void { $GLOBALS['businessSearchSynced'][]=[$provider,$id]; }
function search_remove_entity(string $provider, int|string $id): void { $GLOBALS['businessSearchRemoved'][]=[$provider,$id]; }
function plugin_setting_set(string $plugin,string $key,mixed $value): void { $GLOBALS['businessPublicEnabled']=$value; }
function search_register_provider(string $name,mixed $provider,?string $owner=null): void { $GLOBALS['businessSearchProvider']=[$name,$owner]; }
function search_indexer(): object { return new class {
    public function removeProvider(string $name): void { $GLOBALS['businessSearchDocuments']=[]; }
    public function save(string $name,App\Search\SearchDocument $doc): void { $GLOBALS['businessSearchDocuments'][]=$doc; }
}; }
function abort(string $message='',int $status=404): never { throw new RuntimeException('abort:'.$status); }
require __DIR__ . '/../src/Repositories/BusinessRepository.php';
$repo = new Fireball\Subscriptions\Repositories\BusinessRepository();
$checks=0;
function expect(bool $condition, string $message): void { global $checks; $checks++; if (!$condition) { throw new RuntimeException($message); } }
function rejects(callable $callback, string $message): void { try { $callback(); } catch (Throwable) { expect(true,$message); return; } expect(false,$message); }
foreach ([1,2,3] as $id) { db()->query('INSERT INTO users VALUES (?,?)',[$id,'User '.$id]); }
db()->query("INSERT INTO subscriptions VALUES (1,1,10,'active',NULL,'2020-01-01',NULL,NULL), (2,1,11,'active',NULL,'2020-01-01','2099-01-01',NULL), (3,2,11,'active',NULL,'2020-01-01','2099-01-01',NULL)");
db()->query("INSERT INTO subscription_plan_permissions VALUES (11,'business.manage','true')");
db()->query("INSERT INTO subscription_plans VALUES (10,'Household'),(11,'Business Pro')");
expect($repo->businessSubscription(1)['plan_name']==='Business Pro', 'Dashboard shows the qualifying business plan');
expect($repo->canManage(1), 'Business access survives a second household subscription');
rejects(fn()=>$repo->saveCameraSettings(3,['camera_title'=>'Forged']), 'Camera mutation requires entitlement');
expect(!$repo->canManage(3) && !$repo->canManage(0), 'No access without business entitlement');
rejects(fn()=>$repo->save(3,['name'=>'Forbidden']), 'Creation requires entitlement');
$data=['name'=>'Coffee <script>', 'description'=>'Local coffee', 'socials'=>['vk'=>'https://vk.com/example'], 'is_published'=>1, 'show_camera'=>1, 'camera_id'=>4, 'user_id'=>3];
$id=$repo->save(1,$data);
expect((int)$repo->find($id)['user_id']===1 && $repo->find($id)['camera_id']===null, 'Client cannot forge owner or assign camera');
$second=$repo->save(2,['name'=>'Second']);
expect($repo->find($second,true)===null, 'Draft page cannot be read publicly');
$repo->save(1,array_replace($data,['name'=>'Updated']));
expect($repo->forUser(1)['id']===$id, 'Saving updates the same page');
foreach (['javascript:alert(1)','data:text/html,evil','https://user:pass@example.com','//example.com'] as $url) {
    rejects(fn()=>$repo->save(1,array_replace($data,['website'=>$url])), 'Unsafe URL rejected');
}
rejects(fn()=>$repo->save(1,['name'=>'']), 'Name is required');
rejects(fn()=>$repo->save(1,['name'=>str_repeat('a',191)]), 'Oversized text rejected');
$repo->savePost(1,['title'=>'News','kind'=>'news','body'=>'<script>bad</script>']);
$post=$repo->posts($id)[0];
rejects(fn()=>$repo->savePost(2,['post_id'=>$post['id'],'title'=>'Stolen','kind'=>'news']), 'Another owner cannot edit post');
$repo->deletePost(2,(int)$post['id']);
expect($repo->countPosts($id)===1,'Another owner cannot delete post');
$repo->savePost(1,['post_id'=>$post['id'],'title'=>'Offer','kind'=>'promotion']);
expect($repo->posts($id)[0]['kind']==='promotion','Owner can edit a publication');
rejects(fn()=>$repo->savePost(1,['title'=>'Missing photo','kind'=>'photo']), 'Photo requires image');
$repo->savePost(1,['title'=>'Photo','kind'=>'photo'],'uploads/business/1/image.jpg');
expect($repo->countPosts($id)===2,'Photo publication is stored');
expect($repo->countPosts($id,'photo')===1 && count($repo->posts($id,0,'promotion'))===1, 'Gallery and offers have filtered queries');
$repo->saveCameraSettings(1,['camera_title'=>'Entrance','show_camera'=>1,'camera_id'=>4,'name'=>'Forged rename']);
expect($repo->find($id)['camera_title']==='Entrance' && $repo->find($id)['name']==='Updated' && $repo->find($id)['camera_id']===null, 'Camera settings cannot change identity or assignment');
$repo->saveCameraSettings(2,['camera_title'=>'Other owner']);
expect($repo->find($id)['camera_title']==='Entrance','Camera settings only affect own business');
$repo->saveReview($id,3,['rating'=>5,'body'=>'Great']);
$repo->saveReview($id,3,['rating'=>3,'body'=>'Updated']);
expect((int)$repo->rating($id)['total']===1 && (float)$repo->rating($id)['average']===3.0,'One editable rating per visitor');
rejects(fn()=>$repo->saveReview($id,1,['rating'=>5]), 'Owner cannot rate own business');
rejects(fn()=>$repo->saveReview($id,3,['rating'=>6]), 'Rating range is enforced');
rejects(fn()=>$repo->saveReview($second,3,['rating'=>5]), 'Cannot review draft');
$review=$repo->reviewForUser($id,3);
$repo->reply(2,(int)$review['id'],['reply'=>'Forged']);
expect($repo->reviewForUser($id,3)['reply']==='', 'Other owner cannot reply');
$repo->reply(1,(int)$review['id'],['reply'=>'Thank you']);
db()->query('UPDATE subscription_business_reviews SET is_hidden=1 WHERE id=?',[$review['id']]);
$repo->saveReview($id,3,['rating'=>4,'body'=>'Edited hidden']);
expect($repo->reviews($id)===[] && (int)$repo->rating($id)['total']===0, 'Hidden reviews excluded from feed and rating');
expect($repo->reviewForUser($id,3)['reply']==='Thank you' && (int)$repo->reviewForUser($id,3)['is_hidden']===1,'Editing retains moderation and reply');
$repo->assignCamera($id,3);
rejects(fn()=>$repo->assignCamera($second,3), 'Camera cannot belong to two businesses');
rejects(fn()=>$repo->assignCamera($id,999), 'Unknown camera rejected');
$repo->assignCamera($id,null);
expect($repo->find($id)['camera_id']===null, 'Admin can remove camera');
db()->query("UPDATE subscriptions SET ends_at='2020-01-01' WHERE user_id=1 AND plan_id=11");
expect(!$repo->canManage(1), 'Expired business plan blocks management');
rejects(fn()=>$repo->deletePost(1,(int)$post['id']), 'Expired owner cannot mutate publications');
expect($repo->find($id,true)!==null, 'Expiry preserves published business data');
db()->query("UPDATE subscriptions SET status='grace_period',grace_ends_at='2099-01-01' WHERE user_id=1 AND plan_id=11");
expect($repo->canManage(1),'Grace period permits management');
db()->query("UPDATE subscriptions SET archived_at='2026-01-01' WHERE user_id=1 AND plan_id=11");
expect(!$repo->canManage(1),'Archived subscription grants no management access');
$repo->deleteReview($id,2);
expect($repo->reviewForUser($id,3)!==null,'Visitors cannot delete another review');
$repo->deleteReview($id,3);
expect($repo->reviewForUser($id,3)===null,'Visitor can delete own review');
$routes=file_get_contents(__DIR__.'/../routes.php');
foreach (['manage','review','admin'] as $action) {
    expect((bool)preg_match('/\$router->post\([^\n]+BusinessController::class, \''.$action.'\'\]\)->middleware\(\[\'auth\'/', $routes), 'Mutations use auth middleware');
}
expect(!str_contains(substr($routes,strpos($routes,"$"."router->get('/account/business'")), 'withoutCSRFToken'), 'Business mutations retain standard CSRF protection');
// Exercise the real controller's GET data projection, including filtering and navigation.
function get_user(): array { return ['id'=>1, 'name'=>'Owner', 'login'=>'owner', 'email'=>'owner@example.com']; }
function base_href(string $path): string { return $path; }
function session(): object { return new class {
    public function get(string $key,mixed $default=null): mixed { return $GLOBALS['testSession'][$key] ?? $default; }
    public function remove(string $key): void { unset($GLOBALS['testSession'][$key]); }
    public function set(string $key,mixed $value): void { $GLOBALS['testSession'][$key]=$value; }
    public function setFlash(string $key,mixed $value): void { $GLOBALS['testFlash'][$key]=$value; }
}; }
function request(): object { return new class { public string $uri='/account/business'; public function isPost(): bool { return !empty($GLOBALS['testPost']); }
public function post(string $key,mixed $default=null): mixed { return $GLOBALS['testPost'][$key] ?? $default; }
public function getData(): array { return $GLOBALS['testPost'] ?? []; }
public function get(string $key,mixed $default=null): mixed { return $key==='section' ? ($GLOBALS['testBusinessSection'] ?? 'overview') : ($GLOBALS['testGet'][$key] ?? $default); } }; }
function view(): object { return new class { public function renderPartial(string $name,array $data=[]): string { return ''; } }; }
function plugin_view(string $slug,string $view,array $data): string { $GLOBALS['testBusinessView']=$data; return $view; }
define('PAGINATION_SETTINGS',['perPage'=>20,'midSize'=>2,'maxPages'=>5,'tpl'=>'pagination']);
require __DIR__.'/../../../core/Pagination.php';
require __DIR__.'/../src/Controllers/BusinessController.php';
db()->query("UPDATE subscriptions SET archived_at=NULL,status='active',ends_at='2099-01-01' WHERE user_id=1 AND plan_id=11");
$controller=new Fireball\Subscriptions\Controllers\BusinessController();
$GLOBALS['testBusinessSection']='promotions';
$controller->manage();
expect($GLOBALS['testBusinessView']['section']==='promotions' && count($GLOBALS['testBusinessView']['posts'])===1,'Controller filters offers separately');
expect($GLOBALS['testBusinessView']['business_subscription']['plan_name']==='Business Pro','Controller projects the correct subscription');
expect($GLOBALS['testBusinessView']['business_stats']['posts']===2 && $GLOBALS['testBusinessView']['business_stats']['photos']===1,'Dashboard statistics use actual totals');
$GLOBALS['testBusinessSection']='unknown';
$controller->manage();
expect($GLOBALS['testBusinessView']['section']==='overview','Unknown navigation section falls back to overview');
$repo->saveCameraLinks($id,['camera_url'=>'https://example.com/live/index.m3u8','camera_poster'=>'https://example.com/camera.jpg']);
$source=$repo->find($id);
expect($source['camera_url']==='https://example.com/live/index.m3u8' && $source['camera_poster']==='https://example.com/camera.jpg' && $source['camera_id']===null,'Admin can store a direct stream and poster without manager binding');
$cameraMethod=new ReflectionMethod($controller,'camera');
$cameraMethod->setAccessible(true);
$direct=$cameraMethod->invoke($controller,$source,true);
expect($direct['url']===$source['camera_url'] && $direct['poster']===$source['camera_poster'],'Built-in player receives saved stream and poster');
$repo->save(1,['name'=>'Updated','show_camera'=>1,'camera_url'=>'https://attacker.example/forged','camera_poster'=>'https://attacker.example/poster']);
expect($repo->find($id)['camera_url']===$source['camera_url'],'Owner cannot replace the admin-controlled stream URL');
$hidden=array_replace($repo->find($id),['show_camera'=>0]);
expect($cameraMethod->invoke($controller,$hidden)===null && $cameraMethod->invoke($controller,$hidden,true)!==null,'Visibility applies to public playback while retaining owner preview');
foreach (['javascript:alert(1)','rtsp://example.com/camera','https://user:pass@example.com/video'] as $bad) {
    rejects(fn()=>$repo->saveCameraLinks($id,['camera_url'=>$bad]),'Unsafe or unsupported stream URL rejected');
}
rejects(fn()=>$repo->saveCameraLinks($id,['camera_url'=>'https://example.com/live','camera_poster'=>'data:image/png;base64,evil']),'Unsafe poster URL rejected');
rejects(fn()=>$repo->saveCameraLinks($id,['camera_url'=>'','camera_poster'=>'https://example.com/poster.jpg']),'Poster without stream rejected');
$repo->saveCameraLinks($id,['camera_url'=>'https://example.com/video.mp4','camera_poster'=>'']);
expect($cameraMethod->invoke($controller,$repo->find($id),true)['poster']==='','A stream can play without a poster');
$repo->saveCameraLinks($id,['camera_url'=>'','camera_poster'=>'']);
expect($cameraMethod->invoke($controller,$repo->find($id),true)===null,'Clearing both fields disconnects the camera');
expect($repo::slugFromName('Макси Папа') === 'maksi-papa', 'Cyrillic business names transliterate');
expect($repo::slugFromName('123') === 'business-123', 'Numeric names do not conflict with legacy routes');
expect($repo::slugFromName('🍕') === 'business', 'Empty transliteration has fallback');
$repo->save(1,['name'=>'Макси Папа','slug'=>'forged','is_published'=>1]);
$repo->save(2,['name'=>'Макси Папа']);
expect($repo->find($id)['slug'] === 'maksi-papa', 'Posted slug is ignored');
expect($repo->find($second)['slug'] === 'maksi-papa-2', 'Duplicate names get unique URLs');
expect($repo->findBySlug('maksi-papa',true)['id'] === $id, 'Public slug resolves business');
expect($repo->findBySlug('maksi-papa-2',true) === null, 'Slug cannot expose drafts');
db()->query('UPDATE subscription_business_pages SET slug=NULL WHERE id=?',[$second]);
$repo->backfillSlugs();
expect($repo->find($second)['slug'] === 'maksi-papa-2','Existing pages get unique generated slugs');
$repo->save(2,['name'=>'Макси Папа']);
expect($repo->find($second)['slug'] === 'maksi-papa-2','Unchanged name retains canonical URL');
$repo->save(1,['name'=>'Новое название','is_published'=>1]);
expect($repo->find($id)['slug'] === 'novoe-nazvanie','Changing business name updates generated slug');
function get_route_param(string $key): string { return $GLOBALS['testRouteSlug']; }
function response(): object { return new class { public function redirect(string $url): never { throw new RuntimeException('redirect:' . $url); } }; }
$GLOBALS['testRouteSlug']='novoe-nazvanie';
$controller->show();
expect($GLOBALS['testBusinessView']['page']['id']===$id,'Controller renders by slug');
expect(count($GLOBALS['testBusinessView']['photos'])===1 && $GLOBALS['testBusinessView']['photo_total']===1,'Public gallery loads photos independently of mixed publication pagination');
$GLOBALS['testBusinessSection']='promotions';
$controller->show();
expect($GLOBALS['testBusinessView']['public_section']==='promotions' && count($GLOBALS['testBusinessView']['posts'])===1 && $GLOBALS['testBusinessView']['posts'][0]['kind']==='promotion','Public offer tab filters and paginates offers');
$GLOBALS['testBusinessSection']='unknown';
$controller->show();
expect($GLOBALS['testBusinessView']['public_section']==='home','Unknown public tab returns to homepage');
$GLOBALS['testRouteSlug']=(string)$id;
try { $controller->show(); expect(false,'Legacy URL redirects'); }
catch (RuntimeException $e) { expect($e->getMessage()==='redirect:/business/novoe-nazvanie','Legacy ID redirects to canonical URL'); }
try { $repo->saveCameraLinks($id,['camera_poster'=>'https://example.com/poster.jpg']); }
catch (InvalidArgumentException $e) { expect($e->getMessage()==='business_camera_url_required','Missing stream has precise validation message'); }
$GLOBALS['testPost']=['action'=>'camera-links','business_id'=>$id,'camera_url'=>'','camera_poster'=>'https://example.com/poster.jpg'];
try { $controller->admin(); } catch (RuntimeException $e) { expect($e->getMessage()==='redirect:/admin/subscriptions/business?business='.$id,'Camera error returns to selected business'); }
expect($GLOBALS['testFlash']['error']==='business_camera_url_required','Admin shows precise camera error');
$GLOBALS['testPost']=[];
$GLOBALS['testGet']=['business'=>$id];
$controller->admin();
$display=array_values(array_filter($GLOBALS['testBusinessView']['pages'],fn($page)=>(int)$page['id']===$id))[0];
expect($display['camera_url']==='' && $display['camera_poster']==='https://example.com/poster.jpg','Admin keeps failed camera inputs after redirect');
expect(!isset($GLOBALS['testSession']['subscriptions.business_camera_form']),'Failed camera data is consumed once');
expect($repo->publicCount()===1,'Directory excludes draft businesses');
expect($repo->publicCount('Новое')===1 && $repo->publicCount('unknown')===0,'Directory search filters business names');
expect($repo->publicCount("' OR 1=1 --")===0,'Directory search is parameterized');
$repo->saveReview($id,2,['rating'=>5,'body'=>'Visible']);
$repo->saveReview($id,3,['rating'=>1,'body'=>'Hidden']);
db()->query('UPDATE subscription_business_reviews SET is_hidden=1 WHERE business_id=? AND user_id=3',[$id]);
$catalog=$repo->publicPages();
expect((int)$catalog[0]['review_count']===1 && (float)$catalog[0]['average_rating']===5.0,'Catalog ratings exclude hidden reviews');
expect(!array_key_exists('camera_url',$catalog[0]) && !array_key_exists('user_id',$catalog[0]),'Directory projection includes only card data');
$repo->save(2,['name'=>'Shop 100%_!','description'=>'Books on Main street','address'=>'Main street 9','is_published'=>1]);
expect($repo->publicCount('%')===1 && $repo->publicCount('_')===1 && $repo->publicCount('100%_!')===1,'Search treats wildcard characters literally');
expect($repo->publicCount('Books')===1 && $repo->publicCount('Main street')===1,'Search finds descriptions and addresses');
expect(count($repo->publicPages('', 'newest',1,1))===1 && $repo->publicPages('','newest',1,1)[0]['id']===$id,'Directory pagination uses offset and limit');
expect($repo->publicPages('','rating')[0]['id']===$id,'Directory orders by actual visitor rating');
expect(count($repo->publicPages('', 'name; DROP TABLE users'))===2,'Sort input cannot alter SQL');
$GLOBALS['testGet']=['q'=>'Main street','sort'=>'name'];
$controller->directory();
expect($GLOBALS['testBusinessView']['total_businesses']===1 && $GLOBALS['testBusinessView']['businesses'][0]['id']===$second,'Directory controller projects filtered published cards');
$provider=new Fireball\Subscriptions\Search\BusinessSearchProvider();
$document=$provider->getDocument($second);
expect($document->title==='Shop 100%_!' && $document->url==='/business/shop-100','Search documents link to the current business slug');
expect($document->content==='Books on Main street' && $document->subtitle==='Main street 9','Search includes public descriptions and addresses');
expect(count(iterator_to_array($provider->getDocuments()))===2,'Search enumeration includes all published businesses');
expect($provider->canAccess($document),'Published business is searchable');
db()->query('UPDATE subscription_business_pages SET is_published=0 WHERE id=?',[$second]);
expect(!$provider->canAccess($document) && $provider->getDocument($second)===null,'Stale search documents cannot expose unpublished businesses');
expect(count(iterator_to_array($provider->getDocuments()))===1,'Draft businesses are omitted from rebuilds');
$repo->save(2,['name'=>'Updated shop','description'=>'Public text','is_published'=>1,'email'=>'private-owner@example.com']);
expect(end($GLOBALS['businessSearchSynced'])===[$provider::NAME,$second],'Page saves synchronize the business search document');
expect($provider->getDocument($second)->url==='/business/updated-shop','Renaming updates the search destination');
expect(!str_contains(json_encode($provider->getDocument($second)),'private-owner@example.com'),'Owner contact data is not indexed');
require __DIR__.'/../src/Services/SettingsService.php';
$settingsService=new Fireball\Subscriptions\Services\SettingsService();
$settingsService->saveBusinessPublicSettings([]);
expect(!FireballPluginSubscriptions::businessPublicEnabled() && $GLOBALS['businessSearchDocuments']===[],'Disabling businesses clears their search index without payment credentials');
expect($provider->getDocument($second)===null && !$provider->canAccess($document) && iterator_to_array($provider->getDocuments())===[],'Disabled businesses are never searchable');
foreach (['directory','show','review'] as $action) {
    try { $controller->$action(); expect(false,'Disabled public route must fail'); }
    catch (RuntimeException $e) { expect($e->getMessage()==='abort:404','Disabled public routes return 404 before processing data'); }
}
expect($repo->forUser(2)['name']==='Updated shop','Disabling the public section preserves business data');
$settingsService->saveBusinessPublicSettings(['business_public_enabled'=>1]);
expect(FireballPluginSubscriptions::businessPublicEnabled() && count($GLOBALS['businessSearchDocuments'])===2,'Reenabling rebuilds published businesses immediately');
expect($GLOBALS['businessSearchProvider']===[$provider::NAME,'subscriptions'],'Business search provider belongs to subscriptions');
echo "Business page tests passed: {$checks} checks.\n";
