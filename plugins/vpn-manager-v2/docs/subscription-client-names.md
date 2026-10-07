# VPN subscriber naming (1.5.11)

Open **VPN → Subscriptions → Edit → VPN client name**, enter the new name and choose **Save and synchronize name**. This independent form does not save or modify the tariff, access or term fields. Leaving it blank restores automatic naming from the CMS account or manual customer's name.

The separate subscription field `client_display_name` does not edit the CMS user. The established name generator transliterates the display name and retains the login/manual-profile identifier, country code and server/inbound suffix so different targets keep distinct panel names.

The operation changes desired labels only on connections actually owned by the subscription. It does not rename external provider clients or dependencies owned by other subscriptions. Unprovisioned connections and future tariff additions/restoration use the saved name too.

Existing active/disabled connections are added to the native `rename_client` queue. The normal configuration worker processes it, or an administrator can execute pending operations. The interface reports **queued**, not “confirmed in panel”. Each execution reads the latest desired name; rapid changes reuse pending tasks instead of replaying stale labels. Panel errors retain the operation for the normal retry workflow. A queue insertion failure can be retried by saving the same name again and does not change connection access status.

The normal read/merge/update/read path confirms the label against the original credential, without deleting/recreating the client, rotating UUID/password, resetting traffic, extending expiry or enabling a disabled subscription. Normal configuration inventory preserves the desired name. Cache/revision invalidation also reaches dependent parent subscriptions.

Since 1.5.11, the modern API lookup and update URL use the **current panel email** read by the stable credential, not the new desired email already stored locally. The new email belongs only in the update payload. The adapter checks the current record's credential again before writing, preserves its global metadata, and retains the UUID-addressed legacy endpoint only for unsupported modern endpoints (HTTP 404/405). Missing records, identity changes, rejected writes and unconfirmed updates remain errors; they do not trigger creation, deletion, traffic resets or a false success. After installing the update, use **Retry failed operations** to retry existing failed tasks for clients that still exist.

Migration `018_add_subscription_client_display_name.sql` adds a nullable label field and is automatically applied during the standard plugin update. Existing subscriptions keep automatic naming until explicitly renamed.

Offline tests: `tests/subscription_naming_unit.php` exercises the actual persistence, queue dedup/retry, ownership, future identity generation and confirmed panel update using isolated database/panel boundaries.

`tests/client_rename_transport_unit.php` additionally exercises the actual synchronization service **and** HTTP adapter together with a simulated transport. It verifies old-name request addressing, encoded names, modern and legacy updates, UUID/password and traffic preservation, disabled access, idempotent retries, missing/malformed/unavailable records, identity races, rejected writes and read-back confirmation. It uses neither a live database nor real network requests.
