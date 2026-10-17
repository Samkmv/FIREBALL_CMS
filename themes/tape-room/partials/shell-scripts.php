<?php if(empty($skip_bootstrap)): ?><script src="<?= htmlSC(theme_asset_versioned('bootstrap/bootstrap.bundle.min.js')) ?>" defer></script><?php endif; ?>
<?php if(empty($skip_bootstrap)): ?><script src="<?= htmlSC(theme_asset_versioned('js/genres.js')) ?>" defer></script><?php endif; ?>
<script src="<?= htmlSC(theme_asset_versioned('js/drive-ui.js')) ?>" defer></script>
<script src="<?= htmlSC(theme_asset_versioned('js/shell.js')) ?>" defer></script>
<script src="<?= htmlSC(theme_asset_versioned('js/player-view.js')) ?>" defer></script>
<script src="<?= htmlSC(theme_asset_versioned('js/site-player.js')) ?>" defer></script>
<script src="<?= htmlSC(theme_asset_versioned('js/artist-marquee.js')) ?>" defer></script>
<script src="<?= htmlSC(theme_asset_versioned('js/profile-ui.js')) ?>" defer></script>

<?php if(empty($skip_bootstrap) && function_exists('pwa_head_data')): $shellPwa=pwa_head_data(); ?>
<script>if(<?= !empty($shellPwa['enabled'])?'true':'false' ?> && window.isSecureContext && 'serviceWorker' in navigator)navigator.serviceWorker.register(<?= json_encode($shellPwa['service_worker_url'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,{scope:<?= json_encode(base_url('/'),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,updateViaCache:'none'}).catch(()=>{});</script>
<?php endif; ?>
