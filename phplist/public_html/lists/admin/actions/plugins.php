<?php

$status = 'OK';
$disabled_plugins = unserialize(getConfig('plugins_disabled'));

if (isset($_GET['disable'])) {
  if (isset($GLOBALS['plugins'][$_GET['disable']])) {
    $disabled_plugins[$_GET['disable']] = 1;
  }
  saveConfig('plugins_disabled',serialize($disabled_plugins),0);
  $status = $GLOBALS['img_cross'];
} elseif (isset($_GET['enable'])) {
  if (isset($disabled_plugins[$_GET['enable']])) {
    unset($disabled_plugins[$_GET['enable']]);
  }
  if (isset($GLOBALS['allplugins'][$_GET['enable']])) {
    $plugin_initialised = getConfig(md5('plugin-'.$_GET['enable'].'-initialised'));
    if (empty($plugin_initialised)) {
      if (method_exists($GLOBALS['allplugins'][$_GET['enable']],'initialise')) {
        $GLOBALS['plugins'][$_GET['enable']]->initialise();
      }
      saveConfig(md5('plugin-'.$_GET['enable'].'-initialised'),time(),0);
    }
  }
  saveConfig('plugins_disabled',serialize($disabled_plugins),0);
  $status = $GLOBALS['img_tick'];
}
#var_dump($_GET);

return $status;
