<?php
require('configvars.php');
require('requiredvars.php');

$requiredVars3 = $requiredVars;

writeToConfig($_SESSION, $requiredVars3);

$yourPath = $_SESSION['adminpages'];

print $GLOBALS["I18N"]->get(sprintf('<br><br>%s<a href="%s">here</a>',$GLOBALS['strGoToInitialiseDb'], $yourPath));

include('install/footer.inc');

cleanSession();

exit;

?>
