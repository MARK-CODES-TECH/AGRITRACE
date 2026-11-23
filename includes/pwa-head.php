<?php
/* REPLACE includes/pwa-head.php. Include before </head> on every page:
   pages/ files:  <?php include __DIR__.'/../includes/pwa-head.php'; ?>
   root files:    <?php include __DIR__.'/includes/pwa-head.php'; ?>        */
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
$__uid = '';
foreach (['user_id', 'farmer_id', 'id', 'uid'] as $__k) { if (!empty($_SESSION[$__k]) && is_scalar($_SESSION[$__k])) { $__uid = (string)$_SESSION[$__k]; break; } }
$__role = '';
foreach (['role', 'user_role', 'type', 'user_type'] as $__k) { if (!empty($_SESSION[$__k]) && is_string($_SESSION[$__k])) { $__role = $_SESSION[$__k]; break; } }
?>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#2e7d32">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="AgriTrace+">
<link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
<link rel="apple-touch-icon" href="/icons/icon-192.png">
<?php if ($__uid !== ''): ?>
<meta name="agri-user" content="<?= htmlspecialchars($__uid, ENT_QUOTES) ?>">
<meta name="agri-role" content="<?= htmlspecialchars($__role, ENT_QUOTES) ?>">
<?php endif; ?>
<script src="/assets/js/pwa.js" defer></script>
<style>
  button,.btn,input[type=submit],select,input,textarea{min-height:44px}
  img,video{max-width:100%}
</style>
