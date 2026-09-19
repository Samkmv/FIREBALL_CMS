<?php

/** Refreshes the built-in public help catalog during the normal CMS update flow. */
return static function (): void {
    (new \App\Models\Support())->ensureTableExists();
    (new \App\Services\DefaultKnowledgeBaseSeeder())->seed();
};
