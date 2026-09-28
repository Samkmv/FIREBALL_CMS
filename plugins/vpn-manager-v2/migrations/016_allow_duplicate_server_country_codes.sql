-- FIREBALL_VPN_DUPLICATE_COUNTRY_CODE_V145
-- country_code is metadata and must NOT be unique.
-- Server `code` remains unique via uq_vpn_v2_servers_code.
--
-- Remove accidental UNIQUE indexes whose complete column set is exactly
-- (country_code), regardless of their index name. Then ensure the intended
-- ordinary lookup index exists.

SET @vpn_v2_country_unique_idx = (
    SELECT candidate.index_name
    FROM (
        SELECT
            s.INDEX_NAME AS index_name,
            MAX(s.NON_UNIQUE) AS non_unique,
            COUNT(*) AS column_count,
            GROUP_CONCAT(s.COLUMN_NAME ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS columns_list
        FROM information_schema.STATISTICS s
        WHERE s.TABLE_SCHEMA = DATABASE()
          AND s.TABLE_NAME = 'vpn_v2_servers'
          AND s.INDEX_NAME <> 'PRIMARY'
        GROUP BY s.INDEX_NAME
    ) candidate
    WHERE candidate.non_unique = 0
      AND candidate.column_count = 1
      AND candidate.columns_list = 'country_code'
    ORDER BY candidate.index_name
    LIMIT 1
);

SET @vpn_v2_drop_country_unique_sql = IF(
    @vpn_v2_country_unique_idx IS NULL,
    'SELECT 1',
    CONCAT(
        'ALTER TABLE `vpn_v2_servers` DROP INDEX `',
        REPLACE(@vpn_v2_country_unique_idx, '`', '``'),
        '`'
    )
);
PREPARE vpn_v2_drop_country_unique_stmt FROM @vpn_v2_drop_country_unique_sql;
EXECUTE vpn_v2_drop_country_unique_stmt;
DEALLOCATE PREPARE vpn_v2_drop_country_unique_stmt;

-- A broken database could contain more than one redundant UNIQUE(country_code)
-- index with different names. Repeat safely to clear another one if present.
SET @vpn_v2_country_unique_idx = (
    SELECT candidate.index_name
    FROM (
        SELECT
            s.INDEX_NAME AS index_name,
            MAX(s.NON_UNIQUE) AS non_unique,
            COUNT(*) AS column_count,
            GROUP_CONCAT(s.COLUMN_NAME ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS columns_list
        FROM information_schema.STATISTICS s
        WHERE s.TABLE_SCHEMA = DATABASE()
          AND s.TABLE_NAME = 'vpn_v2_servers'
          AND s.INDEX_NAME <> 'PRIMARY'
        GROUP BY s.INDEX_NAME
    ) candidate
    WHERE candidate.non_unique = 0
      AND candidate.column_count = 1
      AND candidate.columns_list = 'country_code'
    ORDER BY candidate.index_name
    LIMIT 1
);

SET @vpn_v2_drop_country_unique_sql = IF(
    @vpn_v2_country_unique_idx IS NULL,
    'SELECT 1',
    CONCAT(
        'ALTER TABLE `vpn_v2_servers` DROP INDEX `',
        REPLACE(@vpn_v2_country_unique_idx, '`', '``'),
        '`'
    )
);
PREPARE vpn_v2_drop_country_unique_stmt FROM @vpn_v2_drop_country_unique_sql;
EXECUTE vpn_v2_drop_country_unique_stmt;
DEALLOCATE PREPARE vpn_v2_drop_country_unique_stmt;

SET @vpn_v2_country_regular_idx = (
    SELECT INDEX_NAME
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vpn_v2_servers'
      AND INDEX_NAME = 'idx_vpn_v2_servers_country_code'
    LIMIT 1
);

-- If an incorrectly unique index used the intended regular index name, it was
-- dropped above. Create the normal non-unique lookup index when missing.
SET @vpn_v2_create_country_regular_sql = IF(
    @vpn_v2_country_regular_idx IS NULL,
    'CREATE INDEX `idx_vpn_v2_servers_country_code` ON `vpn_v2_servers` (`country_code`)',
    'SELECT 1'
);
PREPARE vpn_v2_create_country_regular_stmt FROM @vpn_v2_create_country_regular_sql;
EXECUTE vpn_v2_create_country_regular_stmt;
DEALLOCATE PREPARE vpn_v2_create_country_regular_stmt;
