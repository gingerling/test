<?
require_once "accesscheck.php";

print Help("preparemessage","What is prepare a message");

include "send_core.php";

if (!$done)  {
  print '<p><input type=submit name=send value="Add message"></form>';
}

?>