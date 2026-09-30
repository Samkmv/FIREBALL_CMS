<?php
declare(strict_types=1);
$mode=$argv[1] ?? 'public'; $locale=$argv[2] ?? 'ru'; $theme=$argv[3] ?? 'light';
final class FireballPluginSubscriptions {
    public static array $translations;
    public static function t(string $key): string { return self::$translations[$key] ?? $key; }
}
FireballPluginSubscriptions::$translations=require __DIR__.'/../../lang/'.$locale.'.php';
$helpers = file_get_contents(__DIR__ . '/../../../../helpers/helpers.php');
$slugStart = strpos($helpers, 'function make_slug(');
$slugEnd = strpos($helpers, 'function get_csrf_field(', $slugStart);
eval(substr($helpers, $slugStart, $slugEnd - $slugStart));
require __DIR__.'/../../src/Repositories/BusinessRepository.php';
function htmlSC(mixed $text): string { return htmlspecialchars((string)$text,ENT_QUOTES,'UTF-8'); }
function base_href(string $path): string {
    if (str_contains($path,'uploads/')) { return 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="300"><rect width="800" height="300" fill="#61442f"/><circle cx="400" cy="140" r="90" fill="#dfb987"/></svg>'); }
    return $path;
}
function get_csrf_field(): string { return '<input type="hidden" name="csrf_token" value="fixture-token">'; }
function get_alerts(): void {}
function view(): object { return new class { public function renderPartial(string $name,array $data=[]): string { return ''; } }; }
function request(): object { return new class { public string $uri='/business/1'; public function get(string $key,mixed $default=null): mixed { return $default; } }; }
define('PAGINATION_SETTINGS',['perPage'=>20,'midSize'=>2,'maxPages'=>5,'tpl'=>'pagination']);
require __DIR__.'/../../../../core/Pagination.php';
$pagination=$posts_pagination=$reviews_pagination=new FBL\Pagination(0,20);
$page=['id'=>1,'slug'=>'kofeynya-na-uglu','user_id'=>1,'name'=>'Кофейня «На углу»','description'=>"Кофе, выпечка и уютные встречи.\n<script>alert('unsafe')</script>",'address'=>'Пятигорск, ул. Кирова, 15','phone'=>'+7 (999) 123-45-67','email'=>'coffee@example.com','website'=>'https://example.com','hours'=>'Ежедневно 08:00–22:00','socials_json'=>'{"vk":"https://vk.com/example","telegram":"https://t.me/example"}','avatar'=>'uploads/avatar.png','cover'=>'uploads/cover.png','camera_id'=>3,'camera_url'=>'https://example.com/live/index.m3u8','camera_poster'=>'https://example.com/poster.jpg','camera_title'=>'Камера кофейни','show_camera'=>1,'is_published'=>1];
$section=match($mode) { 'manage'=>'company', 'owner-posts'=>'posts', 'owner-camera'=>'camera', 'owner-statistics'=>'statistics', 'owner-gallery'=>'gallery', default=>'overview' };
$owner=['name'=>'Владелец','login'=>'coffee','email'=>'owner@example.com'];
$business_subscription=['plan_name'=>'Бизнес Pro','status'=>'active','ends_at'=>'2026-12-31 23:59:59'];
$business_stats=['posts'=>3,'photos'=>1,'promotions'=>1,'reviews'=>1,'rating'=>5];
$form_data=[]; $allowed=true; $can_manage=true; $user_id=$mode==='guest' ? 0 : 2;
$posts=[['id'=>1,'kind'=>'promotion','title'=>'Утренний кофе со скидкой 20%','body'=>'Каждый будний день до 11:00.','image'=>'uploads/coffee.jpg','created_at'=>'2026-09-30 08:00:00'],['id'=>2,'kind'=>'news','title'=>'Очень длинный заголовок '.str_repeat('А',120),'body'=>'Новая сезонная выпечка.','image'=>'','created_at'=>'2026-09-29 12:00:00'],['id'=>3,'kind'=>'photo','title'=>'Наш интерьер','body'=>'','image'=>'uploads/interior.png','created_at'=>'2026-09-29 12:00:00']];
$reviews=[['id'=>1,'user_id'=>2,'author'=>'Посетитель','rating'=>5,'body'=>'Отличный кофе! <img src=x onerror=alert(1)>','reply'=>'Спасибо, будем рады видеть вас снова.','is_hidden'=>0,'created_at'=>'2026-09-30 09:00:00']];
$own_review=$reviews[0]; $rating=['total'=>1,'average'=>5];
$camera=['title'=>'Камера кофейни','url'=>'https://example.com/stream-test/index.m3u8','poster'=>'https://example.com/poster.jpg','stream_key'=>'test'];
$business_camera=$camera; $business_camera['poster']=base_href('/uploads/camera.png'); $latest_posts=$posts; $latest_promotions=[$posts[0]];
$pages=[$page+['owner'=>'Владелец']];$selected=$page;$cameras=[['id'=>3,'site_name'=>'Кофейня','name'=>'Вход','stream_key'=>'coffee']];$tabs=[];
if ($mode==='locked') { $allowed=false; }
$root=dirname(__DIR__,4);
$iconCss=file_get_contents($root.'/public/assets/default/icons/cartzilla-icons.min.css');
$iconCss=preg_replace('~url\([^)]*cartzilla-icons\.woff2[^)]*\)~', 'url(data:font/woff2;base64,'.base64_encode(file_get_contents($root.'/public/assets/default/icons/cartzilla-icons.woff2')).')', $iconCss);
$css=$iconCss.file_get_contents($root.'/public/assets/default/css/theme.min.css').file_get_contents($root.'/public/assets/default/css/profile.css').file_get_contents(__DIR__.'/../../assets/subscriptions.css');
echo '<!doctype html><html data-bs-theme="'.htmlSC($theme).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>'.$css.'</style></head><body>';
require __DIR__.'/../../views/'.($mode==='admin' ? 'admin/business' : (in_array($mode,['manage','locked','owner-overview','owner-posts','owner-camera','owner-statistics','owner-gallery']) ? 'public/business-manage' : 'public/business')).'.php';
echo '</body></html>';
