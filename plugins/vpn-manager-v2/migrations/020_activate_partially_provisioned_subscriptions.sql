UPDATE vpn_v2_subscriptions sub
SET sub.status = 'partial_sync'
WHERE sub.status IN ('provisioning', 'provisioning_failed')
  AND EXISTS (
      SELECT 1
      FROM vpn_v2_subscription_nodes n
      WHERE n.subscription_id = sub.id AND n.status = 'active' AND n.is_obsolete = 0
  );
