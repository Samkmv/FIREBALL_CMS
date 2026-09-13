<?php

namespace FBL\Plugins;

/** Optional schema/data upgrade contract. Called only by explicit maintenance. */
interface PluginMigrationInterface
{
    /** Owns migration ordering, journaling, locking and any data backfills. */
    public function migrateSchema(): void;
}
