<?php
declare(strict_types=1);
require __DIR__ . '/favorites_unit.php';
require dirname(__DIR__) . '/plugins/subscriptions/Plugin.php';
function htmlSC(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
db()->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, role TEXT); INSERT INTO users VALUES (1, "user"), (2, "creator");
    CREATE TABLE subscription_content_rules (id INTEGER PRIMARY KEY, content_type TEXT, content_id TEXT, access_mode TEXT, show_title INTEGER, show_excerpt INTEGER, show_image INTEGER, hide_video INTEGER, required_permission TEXT);
    CREATE TABLE subscription_content_plans (content_rule_id INTEGER, plan_id INTEGER);
    CREATE TABLE subscriptions (id INTEGER, user_id INTEGER, plan_id INTEGER, archived_at TEXT, status TEXT, starts_at TEXT, ends_at TEXT, grace_ends_at TEXT);
    CREATE TABLE subscription_plans (id INTEGER, name TEXT, slug TEXT, grace_period_days INTEGER);
    CREATE TABLE subscription_plan_permissions (plan_id INTEGER, permission_key TEXT, permission_value TEXT);
    INSERT INTO subscription_content_rules VALUES (1, "post", "1", "subscribers", 0, 0, 0, 1, "posts.view_paid"), (2, "post", "3", "plans", 1, 1, 1, 0, NULL);
    INSERT INTO subscription_content_plans VALUES (2, 7), (2, 9)');
$checks = 0;
use Fireball\Subscriptions\Repositories\ContentRuleRepository;
use Fireball\Subscriptions\Services\AccessService;
$start = db()->queries;
$rules = (new ContentRuleRepository())->findMany('post', [1, 3, 4, 3]);
check(db()->queries === $start + 1 && $rules[4] === null, 'Single batch includes missing-rule state');
check($rules[1]['plan_ids'] === [] && $rules[3]['plan_ids'] === [7, 9], 'No lost rules or allowed plans in join');
$posts = (new App\Models\UserFavorite())->paginatedPosts(1)['items'];
// Ensure the protected post is in this 20-card page.
$posts[0] = ['id' => 1, 'title' => 'Secret', 'excerpt' => 'Private', 'image' => 'private.jpg', 'show_post_image' => true];
$start = db()->queries;
$filtered = FireballPluginSubscriptions::filterPublicPosts($posts, ['id' => 1, 'role' => 'user']);
check(db()->queries - $start === 3, 'Twenty-card access policy: one rule join, one role, one subscription lookup; no N+1');
check($filtered[0]['title'] === 'subscriptions_protected_content' && $filtered[0]['excerpt'] === '' && !$filtered[0]['show_post_image'], 'Favorites do not reveal protected title, excerpt or image');
$start = db()->queries;
$creator = FireballPluginSubscriptions::filterPublicPosts($posts, ['id' => 2, 'role' => 'creator']);
check(db()->queries - $start === 2 && $creator[0]['title'] === 'Secret', 'Administrator policy retained with fixed query count');
$access = new AccessService(); $access->prefetchContentRules('post', [1, 3, 4]);
check($access->contentDecision(1, 'post', 4)['allowed'], 'Missing rule remains public');
check(!$access->contentDecision(1, 'post', 3)['allowed'], 'Plan-protected post remains denied without subscription');
db()->pdo->exec('INSERT INTO subscription_plans VALUES (7, "Plan", "plan", 0);
    INSERT INTO subscriptions VALUES (1, 1, 7, NULL, "active", "2026-01-01", "2040-01-01", NULL);
    INSERT INTO subscription_plan_permissions VALUES (7, "posts.view_paid", "true")');
$access = new AccessService(); $access->prefetchContentRules('post', [1, 3]);
check($access->contentDecision(1, 'post', 3)['allowed'] && $access->contentDecision(1, 'post', 1)['allowed'], 'Active permitted subscription retains access');
echo "$checks favorites subscription/access checks passed\n";
