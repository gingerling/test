<?
require_once dirname(__FILE__).'/accesscheck.php';

# experiment, see whether we can correct the magic quotes centrally

function addSlashesArray($array) {
  foreach ($array as $key => $val) {
    if (is_array($val)) {
      $array[$key] = addSlashesArray($val);
    } else {
      $array[$key] = addslashes($val);
    }
  }
  return $array;
}

#$_POST = addSlashesArray($_POST);
#$_GET = addSlashesArray($_GET);
#$_REQUEST = addSlashesArray($_REQUEST);

?>  