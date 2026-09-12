CREATE TABLE IF NOT EXISTS calendar_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_id INT(10) UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    description TEXT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    all_day TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    color CHAR(7) NOT NULL DEFAULT '#6f5ef9',
    recurrence VARCHAR(20) NOT NULL DEFAULT 'none',
    recurrence_until DATETIME NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
    visibility VARCHAR(20) NOT NULL DEFAULT 'personal',
    audience_role VARCHAR(50) NULL,
    created_by INT(10) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_calendar_events_owner (owner_id, deleted_at, starts_at),
    KEY idx_calendar_events_schedule (status, starts_at, recurrence_until),
    KEY idx_calendar_events_visibility (visibility, audience_role),
    CONSTRAINT fk_calendar_events_owner FOREIGN KEY (owner_id)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_calendar_events_creator FOREIGN KEY (created_by)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_event_users (
    event_id BIGINT UNSIGNED NOT NULL,
    user_id INT(10) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (event_id, user_id),
    KEY idx_calendar_event_users_user (user_id, event_id),
    CONSTRAINT fk_calendar_event_users_event FOREIGN KEY (event_id)
        REFERENCES calendar_events(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_calendar_event_users_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_reminders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    offset_value INT(10) UNSIGNED NOT NULL,
    offset_unit VARCHAR(20) NOT NULL,
    remind_time CHAR(5) NULL,
    site_notification TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    push_notification TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_calendar_reminders_event (event_id, status),
    CONSTRAINT fk_calendar_reminders_event FOREIGN KEY (event_id)
        REFERENCES calendar_events(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_reminder_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reminder_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,
    user_id INT(10) UNSIGNED NOT NULL,
    occurrence_start DATETIME NOT NULL,
    scheduled_at DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'processing',
    attempts INT(10) UNSIGNED NOT NULL DEFAULT 1,
    site_notification_id BIGINT UNSIGNED NULL,
    push_sent_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    sent_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_calendar_delivery (reminder_id, occurrence_start, user_id),
    KEY idx_calendar_deliveries_status (status, scheduled_at),
    KEY idx_calendar_deliveries_user (user_id, sent_at),
    CONSTRAINT fk_calendar_deliveries_reminder FOREIGN KEY (reminder_id)
        REFERENCES calendar_reminders(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_calendar_deliveries_event FOREIGN KEY (event_id)
        REFERENCES calendar_events(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_calendar_deliveries_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
