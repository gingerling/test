<?php
require_once dirname(__FILE__).'/accesscheck.php';
ob_end_flush();
$upgrade_required = 0;

if (Sql_Table_exists($tables["config"],1)) {
  $dbversion = getConfig("version");
  if ($dbversion != VERSION && !defined("IN_WEBBLER")&& !defined("WEBBLER")) {
    Error($GLOBALS['I18N']->get('Your database is out of date, please make sure to upgrade').'<br/>'.
     $GLOBALS['I18N']->get('Your version').' : '.$dbversion.'<br/>'.
     $GLOBALS['I18N']->get('phplist version').' : '.VERSION.
    '<br/>'.PageURL2("upgrade")
     );
    $upgrade_required = 1;
  }
} else {
  Error($GLOBALS['I18N']->get('Database has not been initialised').', '.
  $GLOBALS['I18N']->get('go to').' '.
  PageLink2("initialise&firstinstall=1",$GLOBALS['I18N']->get('Initialise Database')). ' '.
  $GLOBALS['I18N']->get('to continue'));
  $GLOBALS["firsttime"] = 1;
  $_SESSION["firstinstall"] = 1;
  return;
}

// if (WARN_ABOUT_PHP_SETTINGS && (version_compare('4.3.11',PHP_VERSION)>0 || version_compare('5.0.4',PHP_VERSION)>0))
//   Warn($GLOBALS['I18N']->get('globalvulnwarning'));

# check for latest version
$checkinterval = sprintf('%d',getConfig("check_new_version"));
if (!isset($checkinterval)) {
  $checkinterval = 7;
}
if ($checkinterval && !defined('IN_WEBBLER') && !defined('WEBBLER')) {
  $query
  = ' select cast(value as date) + \'%d days\' < current_timestamp as needscheck'
  . ' from %s'
  . ' where item = ?';
  $query = sprintf( $query, $checkinterval, $tables["config"] );
  $req = Sql_Query_Params($query, array('updatelastcheck'));
  $needscheck = Sql_Fetch_Row($req);
  if ($needscheck[0]) {
    @ini_set("user_agent",NAME." (phplist version ".VERSION.")");
    @ini_set("default_socket_timeout",5);
    if ($fp = @fopen ("http://www.phplist.com/files/LATESTVERSION","r")) {
      $latestversion = fgets ($fp);
      $latestversion = preg_replace("/[^\.\d]/","",$latestversion);
      @fclose($fp);
      $thisversion = VERSION;
      $thisversion = preg_replace("/[^\.\d]/","",$thisversion);
      if (!versionCompare($thisversion,$latestversion)) {
        print '<div class="newversion">';
        print $GLOBALS['I18N']->get('A new version of PHPlist is available!');
        print '<br/>';
        print '<br/>'.$GLOBALS['I18N']->get('The new version may have fixed security issues,<br/>so it is recommended to upgrade as soon as possible');
        print '<br/>'.$GLOBALS['I18N']->get('Your version').': <b>'.$thisversion.'</b>';
        print '<br/>'.$GLOBALS['I18N']->get('Latest version').': <b>'.$latestversion.'</b><br/>  ';
        print '<a href="http://mantis.phplist.com/changelog_page.php">'.$GLOBALS['I18N']->get('View what has changed').'</a>&nbsp;&nbsp;';
        print '<a href="http://www.phplist.com/download">'.$GLOBALS['I18N']->get('Download').'</a></div>';
      }
    }
    $values = array('item'=>"'updatelastcheck'", 'value'=>'current_timestamp', 'editable'=>'0');
    Sql_Replace($tables['config'], $values, 'item', false);
  }
}

if (!stristr($_SERVER['HTTP_USER_AGENT'],'firefox')) {
  print '<div align="right"><a href="http://www.spreadfirefox.com/?q=affiliates&amp;id=131358&amp;t=81"><img border="0" alt="Get Firefox!" title="Get Firefox!" src="images/getff.gif"/></a></div>';
}

?>

<?php
#$ls = new WebblerListing("System Functions");

print '<div class="accordion">';

$some = 0;
$ls = new WebblerListing($GLOBALS['I18N']->get('Main'));
if (checkAccess("setup") && !$_GET["pi"]) {
  if (!empty($_SESSION['firstinstall'])) {
    $some = 1;
    $element = $GLOBALS['I18N']->get('Continue Configuration');
    $ls->addElement($element,PageURL2("setup"));
    $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Continue the Configuration process of phpList'));
  }
}
if (checkAccess("send") && !$_GET["pi"]) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('Send a campaign');
  $ls->addElement($element,PageURL2("send"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Start or continue a campaign'));
}
if (checkAccess("messages")) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('Manage Campaigns');
  $ls->addElement($element,PageURL2("messages"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('View current campaigns'));
}
if (checkAccess("users")) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('Manage Subscribers');
  $ls->addElement($element,PageURL2("users"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Search, edit and add Subscribers'));
}

if ($some) {
  print '<h3><a name="main">'.$GLOBALS['I18N']->get('Main').'</a></h3>';
  $ls->noShader();
  $ls->noHeader();
  print '<div>'. $ls->display() .'</div>';
}

$some = 0;
$ls = new WebblerListing($GLOBALS['I18N']->get('List and user functions'));
if (checkAccess("list")) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('Manage Lists');
  $ls->addElement($element,PageURL2("list"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('View, edit and add lists, that your subscribers can sign up to'));
}
if (checkAccess("users")) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('users');
  $ls->addElement($element,PageURL2("users"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('List all Users'));
}
if (checkAccess("reconcileusers")) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('reconcileusers');
  $ls->addElement($element,PageURL2("reconcileusers"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Reconcile the User Database'));
}
if (ALLOW_IMPORT && checkAccess("import") && !$_GET["pi"]) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('import');
  $ls->addElement($element,PageURL2("import"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Import Users'));
}
if (checkAccess("export") && !$_GET["pi"]) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('export');
  $ls->addElement($element,PageURL2("export"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Export Users'));
}
if ($some) {
  print '<h3><a name="list">'.$GLOBALS['I18N']->get('List and user functions').'</a></h3>';
  $ls->noShader();
  $ls->noHeader();
  print '<div>'. $ls->display() .'</div>';
}
$some = 0;
$ls = new WebblerListing($GLOBALS['I18N']->get('Configuration Functions'));
if (checkAccess("configure")) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('configure');
  $ls->addElement($element,PageURL2("configure"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Configure').' '.NAME);
}
if (checkAccess("attributes") && !$_GET["pi"]) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('attributes');
  $ls->addElement($element,PageURL2("attributes"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Configure Attributes'));
  if (Sql_table_exists($tables["attribute"])) {
    $res = Sql_Query("select * from ".$tables["attribute"],0);
    while ($row = Sql_Fetch_array($res)) {
      if ($row["type"] != "checkbox" && $row["type"] != "textarea" && $row["type"] != "textline" && $row["type"] != "hidden") {
        $ls->addElement($row["name"],PageURL2("editattributes&amp;id=".$row["id"]));
        $ls->addColumn($row["name"],"&nbsp;",$GLOBALS['I18N']->get('Control values for').' '.$row["name"]);
      }
    }
  }
}
if (checkAccess("spage")) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('spage');
  $ls->addElement($element,PageURL2("spage"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Configure Subscribe Pages'));
}

if ($some) {
  print '<h3><a name="config">'.$GLOBALS['I18N']->get('Configuration Functions').'</a></h3>';
  $ls->noShader();
  $ls->noHeader();
  print '<div>'. $ls->display() .'</div>';
}

$some = 0;
$ls = new WebblerListing($GLOBALS['I18N']->get('Administrator Functions'));
if (checkAccess("admins")) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('admins');
  $ls->addElement($element,PageURL2("admins"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Add, edit and remove Administrators'));
}
if (checkAccess("adminattributes")) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('adminattributes');
  $ls->addElement($element,PageURL2("adminattributes"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Configure Attributes for administrators'));
}
if ($some) {
  print '<h3><a name="admin">'.$GLOBALS['I18N']->get('Administrator Functions').'</a></h3>';
  $ls->noShader();
  $ls->noHeader();
  print '<div>'. $ls->display() .'</div>';
}

$some = 0;
$ls = new WebblerListing($GLOBALS['I18N']->get('Message Functions'));
if (checkAccess("send")) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('send');
  $ls->addElement($element,PageURL2("send"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Send a Message'));
}
if (USE_PREPARE) {
  if (checkAccess("preparesend")) {
    $some = 1;
    $element = $GLOBALS['I18N']->get('preparesend');
    $ls->addElement($element,PageURL2("preparesend"));
    $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Prepare a Message'));
  }
  if (checkAccess("sendprepared")) {
    $some = 1;
    $element = $GLOBALS['I18N']->get('sendprepared');
    $ls->addElement($element,PageURL2("sendprepared"));
    $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Send a Prepared Message'));
  }
}
if (checkAccess("templates")) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('templates');
  $ls->addElement($element,PageURL2("templates"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Configure Templates'));
}
if (checkAccess("messages")) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('messages');
  $ls->addElement($element,PageURL2("messages"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('List all Messages'));
}
if (checkAccess("processqueue") && MANUALLY_PROCESS_QUEUE) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('processqueue');
  $ls->addElement($element,PageURL2("processqueue"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Process the Message Queue'));
  if (TEST) {
   $ls->addColumn($element,$GLOBALS['I18N']->get('warning'),$GLOBALS['I18N']->get('You have set TEST in config.php to 1, so it will only show what would be sent'));
  }
}
if (checkAccess("processbounces") && MANUALLY_PROCESS_BOUNCES) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('processbounces');
  $ls->addElement($element,PageURL2("processbounces"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Process Bounces'));
}
if (checkAccess("bounces")) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('bounces');
  $ls->addElement($element,PageURL2("bounces"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('View Bounces'));
}
if ($some) {
  print '<h3><a name="msg">'.$GLOBALS['I18N']->get('Message Functions').'</a></h3>';
  $ls->noShader();
  $ls->noHeader();
  print '<div>'. $ls->display() .'</div>';
}

//obsolete, moved to rssmanager plugin
//$some = 0;
//$ls = new WebblerListing($GLOBALS['I18N']->get('rss Functions'));
//if (checkAccess("getrss") && MANUALLY_PROCESS_rss) {
//  $some = 1;
//  $element = $GLOBALS['I18N']->get('getrss');
//  $ls->addElement($element,PageURL2("getrss"));
//  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Get rss feeds'));
//}
//if (checkAccess("viewrss")) {
//  $some = 1;
//  $element = $GLOBALS['I18N']->get('viewrss');
//  $ls->addElement($element,PageURL2("viewrss"));
//  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('View rss items'));
//}
//if (checkAccess("purgerss")) {
//  $some = 1;
//  $element = $GLOBALS['I18N']->get('purgerss');
//  $ls->addElement($element,PageURL2("purgerss"));
//  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Purge rss items'));
//}
//
//obsolete, moved to rssmanager plugin 
//if ($some && ENABLE_RSS && !array_key_exists("rssmanager", $GLOBALS["plugins"]))
//  print $ls->display();

$some = 0;
$ls = new WebblerListing($GLOBALS['I18N']->get('Plugins'));
if (sizeof($GLOBALS["plugins"])) {
  foreach ($GLOBALS["plugins"] as $pluginName => $plugin) {
    $menu = $plugin->adminmenu();
    if (is_array($menu)) {
      foreach ($menu as $page => $desc) {
        $some = 1;
        $ls->addElement($desc,PageUrl2("$page&amp;pi=$pluginName"));
#        $ls->addColumn($page,"&nbsp;",$desc);
      }
    }
  }
}
if ($some) {
  print '<h3><a name="plugins">'.$GLOBALS['I18N']->get('Plugins').'</a></h3>';
  $ls->noShader();
  $ls->noHeader();
  print '<div>'. $ls->display() .'</div>';
}

$some = 0;
$ls = new WebblerListing($GLOBALS['I18N']->get('System Functions'));
if (checkAccess("initialise") && !$_GET["pi"]) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('setup');
  $ls->addElement($element,PageURL2("setup"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Setup ').NAME);
}
if (checkAccess("upgrade") && !$_GET["pi"] && $upgrade_required) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('upgrade');
  $ls->addElement($element,PageURL2("upgrade"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Upgrade'));
}
if (checkAccess("dbcheck")) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('dbcheck');
  $ls->addElement($element,PageURL2("dbcheck"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Check Database structure'));
}

if (checkAccess("eventlog")) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('eventlog');
  $ls->addElement($element,PageURL2("eventlog"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('View the eventlog'));
}
if (checkAccess("admin") && $GLOBALS["require_login"] && !isSuperUser()) {
  $some = 1;
  $element = $GLOBALS['I18N']->get('admin');
  $ls->addElement($element,PageURL2("admin"));
  $ls->addColumn($element,"&nbsp;",$GLOBALS['I18N']->get('Change your details (e.g. password)'));;
}
if ($some) {
  print '<h3><a name="system">'.$GLOBALS['I18N']->get('System Functions').'</a></h3>';
  $ls->noShader();
  $ls->noHeader();
  print '<div>'. $ls->display() .'</div>';
}

print '</div>';
?>



