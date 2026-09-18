CREATE TABLE IF NOT EXISTS contact_request_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    sender_type VARCHAR(20) NOT NULL,
    sender_user_id INT(10) UNSIGNED NULL,
    sender_name VARCHAR(150) NULL,
    sender_email VARCHAR(190) NULL,
    recipient_email VARCHAR(190) NULL,
    subject VARCHAR(190) NOT NULL DEFAULT '',
    message MEDIUMTEXT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY request_created (request_id, created_at, id),
    KEY sender_user_id (sender_user_id),
    KEY sender_type (sender_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO contact_request_messages
    (request_id, sender_type, sender_user_id, sender_name, sender_email, recipient_email, subject, message, created_at)
SELECT
    cr.id,
    'requester',
    NULL,
    cr.name,
    cr.email,
    NULL,
    cr.subject,
    cr.message,
    cr.created_at
FROM contact_requests cr
WHERE NOT EXISTS (
    SELECT 1
    FROM contact_request_messages crm
    WHERE crm.request_id = cr.id
      AND crm.sender_type = 'requester'
);
