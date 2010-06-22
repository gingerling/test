<?php
require_once dirname(__FILE__).'/accesscheck.php';

### add "Ajaxable actions" that just return the result, but show in the full page when not ajaxed

$ajax = isset($_GET['ajaxed']);

if ($ajax) {
  @ob_end_clean();
}
$status = $GLOBALS['I18N']->get('Failed');
if (!empty($_GET['action'])) {
  $action = basename($_GET['action']);
  if (is_file(dirname(__FILE__).'/actions/'.$action.'.php')) {
    include dirname(__FILE__).'/actions/'.$action.'.php';
  }
}

print $status;
if (!empty($GLOBALS['developer_email'])) {
  print '<br/><a href="'.$_SERVER['REQUEST_URI'].'" target="_blank">'.$_SERVER['REQUEST_URI'].'</a>';
}

if ($ajax) {
  exit;  
}
