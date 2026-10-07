# VPN operation safety — 1.5.7

## Scope and evidence

The reported production queue referenced VPN subscription #14, which the operator
reported as absent on maxipapa.ru. Production database rows and panel responses
were not available to this audit. Queue screenshots alone cannot establish why a
specific live subscription was disabled. No production panels or subscriptions
were modified, and no live database integration tests were run.

Isolated tests reproduced two code defects before the changes:

- A connection task followed its connection ID without checking the queued
  subscription/server relationship. A stale task could scan or update a different
  target instead of terminating.
- Inventory comparison omitted aggregate subscription traffic usage, while the
  worker included it. Once the aggregate limit was exhausted, inventory and worker
  disagreed about `enable`, repeatedly scheduling updates.

Confirmed connection status also used `desired_enabled` instead of the confirmed
panel `enable` result; the two may legitimately differ for expiry/quota rules.

## Changes

- Node operations validate the subscription, connection and server association.
  Updates/repairs do not revive deleted, deleting or obsolete targets. Deletion
  retries retain their existing cleanup behavior for valid associated targets.
- Obsolete targets become `cancelled`, retain a translated safe explanation and
  release their queue key. Temporary panel failures still retry normally.
- Dependency-generated client scans reference the connection's actual owner;
  the triggering parent remains in `dependency_parent_id`.
- Batch responses distinguish successful, failed/retry and cancelled tasks.
- Inventory and worker use the same aggregate usage when applying access policy.
- Snapshot and worker-confirmation statuses reflect factual panel enable; manual
  desired-enable flags, subscription expiry and access limits are not reset.
- Panel global client state is read before storing its factual snapshot.

## Verification

Isolated regression entry points (PHP 8.2, no CMS bootstrap/database/panel access):

```sh
php plugins/vpn-manager-v2/tests/operation_target_unit.php
php plugins/vpn-manager-v2/tests/sync_policy_unit.php
php plugins/vpn-manager-v2/tests/operation_batch_unit.php
```

Existing completed rows are historical records and are not retroactively
rewritten. The fix applies after installing version 1.5.7. Live verification still
requires the actual affected subscription ID, its current term/traffic/status and
the associated operation/connection IDs; do not re-enable clients indiscriminately.
