<?php

#ob_end_clean();
## blacklist an email from commandline

if (!$GLOBALS['commandline']) {
  print 'Error, this can only be called from commandline'."\n";
  exit;
}

$email = $date = $uid = '';

if (isset($cline['e'])) {
  $email = $cline['e'];
}
if (isset($cline['u'])) {
  $uid = $cline['u'];
}
if (isset($cline['d'])) {
  $date = $cline['d'];
}
if (isset($cline['m'])) {
  $messageid = sprintf('%d', $cline['m']);
}

$emailQ = Sql_Fetch_Row_Query(sprintf('select email from %s where uniqid = "%s" or email = "%s"',$GLOBALS['tables']['user'],sql_escape($uid),sql_escape($email)));
$emailDB = $emailQ[0];

if (empty($emailDB)) {
  cl_output('FAIL'); exit;
}

if (isBlackListed($emailDB)) {
  ## do this anyway, just to be sure
  Sql_Query(sprintf('update %s set blacklisted = 1 where email = "%s"',$GLOBALS['tables']['user'],$emailDB));
  cl_output('OK');
  exit;
}
## do this immediately
Sql_Query(sprintf('update %s set blacklisted = 1 where email = "%s"',$GLOBALS['tables']['user'],$emailDB));

if (!empty($messageid)) {
  Sql_Query(sprintf('insert into %s (name,id,data) values("spamcomplaint",%d,1) on duplicate key update data = data + 1',$GLOBALS['tables']['messagedata'],$messageid));
}
addEmailToBlackList($emailDB,'blacklisted due to spam complaints',$date);
cl_output('OK '.$emailDB);
exit;
