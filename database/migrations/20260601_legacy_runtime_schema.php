<?php

/** One-time compatibility upgrade before historical SQL migrations on older installations. */
return static function (): void {
    (new \App\Services\SqlFileRunner())->executeDatabase((string)file_get_contents(ROOT . '/database/schema.sql'));
    (new ReflectionMethod(\App\Models\SiteSetting::class, 'ensureTableExists'))->invoke(new \App\Models\SiteSetting());
    (new ReflectionMethod(\App\Models\User::class, 'ensureUsersTableExists'))->invoke(new \App\Models\User());
    (new ReflectionMethod(\App\Models\Page::class, 'ensureSchema'))->invoke(new \App\Models\Page());
    (new ReflectionMethod(\App\Models\Post::class, 'ensureSchema'))->invoke(new \App\Models\Post());
    (new ReflectionMethod(\App\Models\Category::class, 'ensureSchema'))->invoke(new \App\Models\Category());
    (new ReflectionMethod(\App\Models\Admin::class, 'ensureSchema'))->invoke(new \App\Models\Admin());
    (new ReflectionMethod(\App\Models\Analytics::class, 'ensureSchema'))->invoke(new \App\Models\Analytics());
    (new ReflectionMethod(\App\Models\ChatMessage::class, 'ensureTableExists'))->invoke(new \App\Models\ChatMessage());
    (new ReflectionMethod(\App\Models\ContactRequest::class, 'ensureTableExists'))->invoke(new \App\Models\ContactRequest());
    (new ReflectionMethod(\App\Models\ContactSubject::class, 'ensureTableExists'))->invoke(new \App\Models\ContactSubject());
    (new ReflectionMethod(\App\Models\Support::class, 'ensureTableExists'))->invoke(new \App\Models\Support());
    (new ReflectionMethod(\App\Models\MailLog::class, 'ensureTableExists'))->invoke(new \App\Models\MailLog());
    (new ReflectionMethod(\App\Models\SecurityLog::class, 'ensureTableExists'))->invoke(new \App\Models\SecurityLog());
    (new ReflectionMethod(\App\Repositories\AnalyticsRepository::class, 'ensureSchema'))->invoke(new \App\Repositories\AnalyticsRepository());
    (new ReflectionMethod(\App\Services\NotificationService::class, 'ensureTables'))->invoke(new \App\Services\NotificationService());
    (new ReflectionMethod(\App\Services\PwaService::class, 'ensureTables'))->invoke(new \App\Services\PwaService());
    (new ReflectionMethod(\App\Services\Maintenance\MaintenanceLogService::class, 'ensureLogTable'))->invoke(new \App\Services\Maintenance\MaintenanceLogService());
    (new ReflectionMethod(\FBL\Plugins\PluginManager::class, 'ensureSchema'))->invoke(new \FBL\Plugins\PluginManager());
    (new \App\Search\SearchIndexer(new \App\Search\SearchRegistry()))->ensureSchema();
};
