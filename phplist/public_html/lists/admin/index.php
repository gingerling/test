<?php

@ob_start();
$er = error_reporting(0);
# check for commandline and cli version
if (!isset($_SERVER["SERVER_NAME"]) && !PHP_SAPI == "cli") {
  print "Warning: commandline only works well with the cli version of PHP";
}

if (isset($_REQUEST['_SERVER'])) { exit; }
$cline = array();
$GLOBALS['commandline'] = 0;

require_once dirname(__FILE__) .'/commonlib/lib/unregister_globals.php';
require_once dirname(__FILE__) .'/commonlib/lib/magic_quotes.php';

# setup commandline
if (php_sapi_name() == "cli") {
  for ($i=0; $i<$_SERVER['argc']; $i++) {
    $my_args = array();
    if (ereg("(.*)=(.*)",$_SERVER['argv'][$i], $my_args)) {
      $_GET[$my_args[1]] = $my_args[2];
      $_REQUEST[$my_args[1]] = $my_args[2];
    }
  }
  $GLOBALS["commandline"] = 1;
  $cline = parseCLine();
  $dir = dirname($_SERVER["SCRIPT_FILENAME"]);
  chdir($dir);
  
  if (!is_file($cline['c'])) {
    print "Cannot find config file\n";
    exit;
  }
  
} else {
  $GLOBALS["commandline"] = 0;
  header("Cache-Control: no-cache, must-revalidate");           // HTTP/1.1
  header("Pragma: no-cache");                                   // HTTP/1.0
}

$configfile = '';

if (isset($_SERVER["ConfigFile"]) && is_file($_SERVER["ConfigFile"])) {
  #print '<!-- using (server)'.$_SERVER["ConfigFile"].'-->'."\n";
   $configfile = $_SERVER["ConfigFile"];
} elseif (isset($cline["c"]) && is_file($cline["c"])) {
  #print '<!-- using (cline)'.$cline["c"].' -->'."\n";
  $configfile = $cline["c"];
# obsolete, set Config in Linux environment, use -c /path/to/config instead
/*} elseif (isset($_ENV["CONFIG"]) && is_file($_ENV["CONFIG"]) && filesize($_ENV["CONFIG"]) > 1) {
#  print '<!-- using '.$_ENV["CONFIG"].'-->'."\n";
  include $_ENV["CONFIG"];*/
} elseif (is_file(dirname(__FILE__).'/../config/config.php')) {
#  print '<!-- using (common)../config/config.php -->'."\n";
   $configfile = "../config/config.php";
} else {
  $configfile = "../config/config.php";
}

if (is_file($configfile) && filesize($configfile) > 20) {
  print '<!-- using config '.$configfile.'-->';
  include $configfile;
} elseif ($GLOBALS["commandline"]) {
  print 'Cannot find config file'."\n";
} else {
  $GLOBALS['installer'] = 1;
  include(dirname(__FILE__).'/install.php');
  exit;
}

$ajax = isset($_GET['ajaxed']);

if (!isset($database_host) || !isset($database_user) || !isset($database_password) || !isset($database_name)) {
 # print $GLOBALS['I18N']->get('Database details incomplete, please check your config file');
  print 'Database details incomplete, please check your config file';
  exit;
}
#exit;
# record the start time(usec) of script
$now =  gettimeofday();
$GLOBALS["pagestats"] = array();
$GLOBALS["pagestats"]["time_start"] = $now["sec"] * 1000000 + $now["usec"];
$GLOBALS["pagestats"]["number_of_queries"] = 0;

if (!$GLOBALS["commandline"] && isset($GLOBALS["developer_email"]) && $_SERVER['HTTP_HOST'] != 'dev.phplist.com' && !empty($GLOBALS['show_dev_errors'])) {
#  error_reporting(E_ALL & ~E_NOTICE);
  ## in developer mode, show all errors and force "registered globals off"
  error_reporting(E_ALL);
  ini_set('display_errors',1);
  foreach ($_REQUEST as $key => $val) {
    unset($$key);
  }
} else {
#  error_reporting($er);
  error_reporting(0);
}

# load all required files
require_once dirname(__FILE__).'/init.php';
require_once dirname(__FILE__).'/'.$GLOBALS["database_module"];
require_once dirname(__FILE__)."/../texts/english.inc";
include_once dirname(__FILE__)."/../texts/".$GLOBALS["language_module"];
require_once dirname(__FILE__)."/defaultconfig.inc";
require_once dirname(__FILE__).'/connect.php';
include_once dirname(__FILE__)."/languages.php";
include_once dirname(__FILE__)."/lib.php";
require_once dirname(__FILE__)."/commonlib/lib/interfacelib.php";

if (!empty($_SESSION['hasconf']) || Sql_Table_exists($tables["config"],1)) {
  $_SESSION['hasconf'] = true;
  ### Activate all plugins
  foreach ($GLOBALS['plugins'] as $plugin) {
    $plugin->activate();
  }
}
## send a header for IE
header('X-UA-Compatible: IE=EmulateIE8');

if (!$ajax) {
  include_once dirname(__FILE__).'/ui/'.$GLOBALS['ui'].'/pagetop.php';
}

if ($GLOBALS["commandline"]) {
  if (!isset($_SERVER["USER"]) && sizeof($GLOBALS["commandline_users"])) {
    clineError("USER environment variable is not defined, cannot do access check. Please make sure USER is defined.");
    exit;
  }
  if (is_array($GLOBALS["commandline_users"]) && sizeof($GLOBALS["commandline_users"]) && !in_array($_SERVER["USER"],$GLOBALS["commandline_users"])) {
    clineError("Sorry, You (".$_SERVER["USER"].") do not have sufficient permissions to run phplist on commandline");
    exit;
  }
  $GLOBALS["require_login"] = 0;

  # getopt is actually useless
  #$opt = getopt("p:");
  $IsCommandlinePlugin = isset($cline['p']) && array_key_exists($cline['p'],$GLOBALS["commandlinePlugins"]);
  if ($cline['p']) {
    if (isset($cline['p']) && !in_array($cline['p'],$GLOBALS["commandline_pages"]) && !$IsCommandlinePlugin ) {
      clineError($cline['p']." does not process commandline");
    } elseif (isset($cline['p'])) {
      $_GET['page'] = $cline['p'];
    }
  } else {
    clineUsage(" [other parameters]");
    exit;
  }
}

# fix for old PHP versions, although not failsafe :-(
if (!isset($_POST) && isset($HTTP_POST_VARS)) {
  include_once dirname(__FILE__) ."/commonlib/lib/oldphp_vars.php";
}

if (!isset($_GET['page'])) {
  $page = 'home';
} else {
  $page = $_GET['page'];
}

preg_match("/([\w_]+)/",$page,$regs);
$page = $regs[1];
if (!is_file($page.'.php') && !isset($_GET['pi'])) {
  $page = 'home';
}

if (!$GLOBALS["admin_auth_module"]) {
  # stop login system when no admins exist
  if (!Sql_Table_Exists($tables["admin"])) {
    $GLOBALS["require_login"] = 0;
  } else {
    $num = Sql_Query("select * from {$tables["admin"]}");
    if (!Sql_Affected_Rows())
      $GLOBALS["require_login"] = 0;
  }
} elseif (!Sql_Table_exists($GLOBALS['tables']['config'])) {
  $GLOBALS['require_login'] = 0;
}

$page_title = NAME;
@include_once dirname(__FILE__)."/lan/".$_SESSION['adminlanguage']['iso']."/pagetitles.php";

// These two meta tags are included on page-top.php
// print '<meta http-equiv="Cache-Control" content="no-cache, must-revalidate" />';           // HTTP/1.1
// print '<meta http-equiv="Pragma" content="no-cache" />';           // HTTP/1.1
print "<title>".NAME." :: ";
if (isset($GLOBALS["installation_name"])) {
  print $GLOBALS["installation_name"] .' :: ';
}
print "$page_title</title>";

if (isset($GLOBALS["require_login"]) && $GLOBALS["require_login"]) {
  if ($GLOBALS["admin_auth_module"] && is_file("auth/".$GLOBALS["admin_auth_module"])) {
    require_once "auth/".$GLOBALS["admin_auth_module"];
  } elseif ($GLOBALS["admin_auth_module"] && is_file($GLOBALS["admin_auth_module"])) {
    require_once $GLOBALS["admin_auth_module"];
  } else {
    if ($GLOBALS["admin_auth_module"]) {
      logEvent("Warning: unable to use ".$GLOBALS["admin_auth_module"]. " for admin authentication, reverting back to phplist authentication");
      $GLOBALS["admin_auth_module"] = 'phplist_auth.inc';
    }
    require_once 'auth/phplist_auth.inc';
  }
  if (class_exists('admin_auth')) {
    $GLOBALS["admin_auth"] = new admin_auth();
  } else {
    print Fatal_Error($GLOBALS['I18N']->get('admininitfailure'));
    return;
  }
  if ((!isset($_SESSION["adminloggedin"]) || !$_SESSION["adminloggedin"]) && isset($_REQUEST["login"]) && isset($_REQUEST["password"])) {
    $loginresult = $GLOBALS["admin_auth"]->validateLogin($_REQUEST["login"],$_REQUEST["password"]);
    if (!$loginresult[0]) {
      $_SESSION["adminloggedin"] = "";
      $_SESSION["logindetails"] = "";
      $page = "login";
      logEvent(sprintf($GLOBALS['I18N']->get('invalid login from %s, tried logging in as %s'),$_SERVER['REMOTE_ADDR'],$_REQUEST["login"]));
      $msg = $loginresult[1];
    } else {
      $_SESSION["adminloggedin"] = $_SERVER["REMOTE_ADDR"];
      $_SESSION["logindetails"] = array(
        "adminname" => $_REQUEST["login"],
        "id" => $loginresult[0],
        "superuser" => $admin_auth->isSuperUser($loginresult[0]),
        "passhash" => sha1($_REQUEST["password"]),
      );
      if ($_POST["page"] && $_POST["page"] != "") {
        $page = $_POST["page"];
      }
    }
  #If passwords are encrypted and a password recovery request was made, send mail to the admin of the given email address.
  } elseif (ENCRYPT_ADMIN_PASSWORDS && isset($_REQUEST["forgotpassword"])){
  	  $adminId = $GLOBALS["admin_auth"]->adminIdForEmail($_REQUEST['forgotpassword']);
      if($adminId){
      	$msg = sendAdminPasswordToken($adminId);
      } else {
      	$msg = $GLOBALS['I18N']->get('cannotsendpassword');
      }
      $page = "login";
  } elseif (isset($_REQUEST["forgotpassword"])) {
    $pass = '';
    if (is_email($_REQUEST["forgotpassword"])) {
      $pass = $GLOBALS["admin_auth"]->getPassword($_REQUEST["forgotpassword"]);
    } 
    if ($pass) {
      sendMail ($_REQUEST["forgotpassword"],$GLOBALS['I18N']->get('yourpassword'),"\n\n".$GLOBALS['I18N']->get('yourpasswordis')." $pass");
      $msg = $GLOBALS['I18N']->get('passwordsent');
      logEvent(sprintf($GLOBALS['I18N']->get('successful password request from %s for %s'),$_SERVER['REMOTE_ADDR'],$_REQUEST["forgotpassword"]));
    } else {
      $msg = $GLOBALS['I18N']->get('cannotsendpassword');
      logEvent(sprintf($GLOBALS['I18N']->get('failed password request from %s for %s'),$_SERVER['REMOTE_ADDR'],$_REQUEST["forgotpassword"]));
    }
    $page = "login";
  } elseif (!isset($_SESSION["adminloggedin"]) || !$_SESSION["adminloggedin"]) {
    #$msg = 'Not logged in';
    $page = "login";
  } elseif (CHECK_SESSIONIP && $_SESSION["adminloggedin"] && $_SESSION["adminloggedin"] != $_SERVER["REMOTE_ADDR"]) {
    logEvent(sprintf($GLOBALS['I18N']->get('login ip invalid from %s for %s (was %s)'),$_SERVER['REMOTE_ADDR'],$_SESSION["logindetails"]['adminname'],$_SESSION["adminloggedin"]));
    $msg = $GLOBALS['I18N']->get('ipchanged');
    $_SESSION["adminloggedin"] = "";
    $_SESSION["logindetails"] = "";
    $page = "login";
  } elseif ($_SESSION["adminloggedin"] && $_SESSION["logindetails"]) {
    $validate = $GLOBALS["admin_auth"]->validateAccount($_SESSION["logindetails"]["id"]);
    if (!$validate[0]) {
      logEvent(sprintf($GLOBALS['I18N']->get('invalidated login from %s for %s (error %s)'),$_SERVER['REMOTE_ADDR'],$_SESSION["logindetails"]['adminname'],$validate[1]));
      $_SESSION["adminloggedin"] = "";
      $_SESSION["logindetails"] = "";
      $page = "login";
      $msg = $validate[1];
    }
  }
}

if (LANGUAGE_SWITCH && empty($logoutontop) && !$ajax) {
    $languageswitcher = '
 <div id="languageswitcher">
       <form name="languageswitchform" method="post" action="">';
    $languageswitcher .= '
           <select name="setlanguage" onchange="document.languageswitchform.submit()">';
    $lancount = 0;
    foreach ($GLOBALS['LANGUAGES'] as $iso => $rec) {
      if (is_dir(dirname(__FILE__).'/lan/'.$iso)) {
        $languageswitcher .= sprintf('
                 <option value="%s" %s>%s</option>',$iso,$_SESSION['adminlanguage']['iso'] == $iso ? 'selected="selected"':'',$rec[0]);
        $lancount++;
      }
    }
    $languageswitcher .= '
            </select>
       </form>
 </div>';
    if ($lancount <= 1) {
      $languageswitcher = '';
    }
}

$include = '';
if (!$ajax) {
  include 'ui/'.$GLOBALS['ui']."/header.inc";
}
if ($page != '' && $page != 'install') {
  if ($IsCommandlinePlugin) {
    $include =  'plugins/' . $GLOBALS["commandlinePlugins"][$page];
  } else {
    preg_match("/([\w_]+)/",$page,$regs);
    $include = $regs[1];
    $include .= ".php";
    $include = $page . ".php";
  }
} else {
  $include = "home.php";
}

if (!$ajax) {
  print '<h4 class="pagetitle">'.NAME.' - '.strtolower($page_title).'</h4>';
}

if ($GLOBALS["require_login"] && $page != "login") {
  if ($page == 'logout') {
    $greeting = $GLOBALS['I18N']->get('goodbye');
  } else {
    $hr = date("G");
    if ($hr > 0 && $hr < 12) {
      $greeting = $GLOBALS['I18N']->get('goodmorning');
    } elseif ($hr <= 18) {
      $greeting = $GLOBALS['I18N']->get('goodafternoon');
    } else {
      $greeting = $GLOBALS['I18N']->get('goodevening');
    }
  }

  if ($page != "logout" && empty($logoutontop) && !$ajax) {
    print '<div class="right">'.PageLink2("logout",$GLOBALS['I18N']->get('logout')).'</div>';
    if (!empty($_SESSION['firstinstall']) && $page != 'setup') {
      print '<div class="info right">'.PageLink2("setup",$GLOBALS['I18N']->get('Continue Configuration')).'</div>';
    }
  }
}

if (!$ajax && $page != "login") {
  if (strpos(VERSION,"dev") && !TEST) {#
    if ($GLOBALS["developer_email"]) {
      Info("Running DEV version. All emails will be sent to ".$GLOBALS["developer_email"]);
    } else {
      Info("Running DEV version, but developer email is not set");
    }
  }
  if (TEST) {
    print Info($GLOBALS['I18N']->get('Running in testmode, no emails will be sent. Check your config file.'));
  }

  if (ini_get("register_globals") == "on" && WARN_ABOUT_PHP_SETTINGS) {
    Error($GLOBALS['I18N']->get('It is safer to set Register Globals in your php.ini to be <b>off</b> instead of ').ini_get("register_globals") );
  }
  if (ini_get("safe_mode") && WARN_ABOUT_PHP_SETTINGS)
    Warn($GLOBALS['I18N']->get('safemodewarning'));

    /* this needs checking 
  if (!ini_get("magic_quotes_gpc") && WARN_ABOUT_PHP_SETTINGS)
    Warn($GLOBALS['I18N']->get('magicquoteswarning'));
    
  if (ini_get("magic_quotes_runtime") && WARN_ABOUT_PHP_SETTINGS)
    Warn($GLOBALS['I18N']->get('magicruntimewarning'));
    */
  if (defined("ENABLE_RSS") && ENABLE_RSS && !function_exists("xml_parse") && WARN_ABOUT_PHP_SETTINGS)
    Warn($GLOBALS['I18N']->get('noxml'));

  if (ALLOW_ATTACHMENTS && WARN_ABOUT_PHP_SETTINGS && (!is_dir($GLOBALS["attachment_repository"]) || !is_writable ($GLOBALS["attachment_repository"]))) {
    if (ini_get("open_basedir")) {
      Warn($GLOBALS['I18N']->get('warnopenbasedir'));
    }
    Warn($GLOBALS['I18N']->get('warnattachmentrepository'));
  }
}

# always allow access to the about page
if (isset($_GET['page']) && $_GET['page'] == 'about') {
  $page = 'about';
  $include = 'about.php';
}

$noteid = basename($include,'.php');
if (isset($_GET['note'.$noteid]) && $_GET['note'.$noteid] == 'hide') {
  if (!isset($_SESSION['suppressinfo']) || !is_array($_SESSION['suppressinfo'])) {
    $_SESSION['suppressinfo'] = array();
  }
  $_SESSION['suppressinfo'][$noteid] = 'hide';
}

if (!$ajax && empty($_SESSION['suppressinfo'][$noteid])) {
  
  print '<div class="note '.$noteid.'">';
  print '<a href="./?page='.$page.'&amp;note'.$noteid.'=hide" class="hide" />'.$GLOBALS['I18N']->get('Hide').'</a>';

  # include some information
  if (empty($_GET['pi'])) {
    if (is_file("info/".$_SESSION['adminlanguage']['info']."/$include")) {
      @include "info/".$_SESSION['adminlanguage']['info']."/$include";
    } else {
      @include "info/en/$include";
    }

  } elseif (isset($_GET['pi']) && !empty($GLOBALS['plugins'][$_GET['pi']]) && is_object($GLOBALS['plugins'][$_GET['pi']])) {
    if (is_file($GLOBALS['plugins'][$_GET['pi']]->coderoot.'/info/'.$_SESSION['adminlanguage']['info']."/$include")) {
      @include $GLOBALS['plugins'][$_GET['pi']]->coderoot .'/info/'.$_SESSION['adminlanguage']['info']."/$include";
    }
  } else {
    @include "info/en/$include";
  #  print "Not a file: "."info/".$adminlanguage["info"]."/$include";
  }
  print '</div>'; ## end of info div
}



if (!empty($_GET['action']) && $_GET['page'] != 'pageaction') {
  $action = basename($_GET['action']);
  if (is_file(dirname(__FILE__).'/actions/'.$action.'.php')) {
    include dirname(__FILE__).'/actions/'.$action.'.php';
    print '<div id="actionresult">'.$status.'</div>';
  }
}

/*
if (USEFCK) {
  $imgdir = getenv("DOCUMENT_ROOT").$GLOBALS["pageroot"].'/'.FCKIMAGES_DIR.'/';
  if (!is_dir($imgdir) || !is_writeable ($imgdir)) {
    Warn("The FCK image directory does not exist, or is not writable");
  }
}
*/

if (!empty($_COOKIE['browsetrail'])) {
  if (!isset($_SESSION['browsetrail']) || !is_array($_SESSION['browsetrail'])) {
    $_SESSION['browsetrail'] = array();
  }
  if (!in_array($_COOKIE['browsetrail'],$_SESSION['browsetrail'])) {
    $_SESSION['browsetrail'][] = $_COOKIE['browsetrail'];
  }
}

if (defined("USE_PDF") && USE_PDF && !defined('FPDF_VERSION')) {
  Warn($GLOBALS['I18N']->get('nofpdf'));
}

$this_doc = getenv("REQUEST_URI");
if (preg_match("#(.*?)/admin?$#i",$this_doc,$regs)) {
  $check_pageroot = $pageroot;
  $check_pageroot = preg_replace('#/$#','',$check_pageroot);
  if ($check_pageroot != $regs[1] && WARN_ABOUT_PHP_SETTINGS)
    Warn($GLOBALS['I18N']->get('warnpageroot'));
}

clearstatcache();
if (checkAccess($page,"") || $page == 'about') {
  if (!$_GET['pi'] && (is_file($include) || is_link($include))) {
    # check whether there is a language file to include
    if (is_file("lan/".$_SESSION['adminlanguage']['iso']."/".$include)) {
      include "lan/".$_SESSION['adminlanguage']['iso']."/".$include;
    }
  #  print "Including $include<br/>";

    # hmm, pre-parsing and capturing the error would be nice
    #$parses_ok = eval(@file_get_contents($include));
    $parses_ok = 1;

    if (!$parses_ok) {
      print Error("cannot parse $include");
      print '<p class="error">Sorry, an error occurred. This is a bug. Please <a href="http://mantis.tincan.co.uk">report the bug to the Bug Tracker</a><br/>Sorry for the inconvenience</a></p>';
    } else {
      if (!empty($_SESSION['action_result'])) {
        print '<div class="actionresult">'.$_SESSION['action_result'].'</div>';
        unset($_SESSION['action_result']);
      }


      if (isset($GLOBALS['developer_email'])) {
        include $include;
      } else {
        @include $include;
      }
    }
  #  print "End of inclusion<br/>";
  } elseif ($_GET['pi'] && isset($GLOBALS['plugins']) && is_array($GLOBALS['plugins']) && is_object($GLOBALS['plugins'][$_GET['pi']])) {
    $plugin = $GLOBALS["plugins"][$_GET["pi"]];
    $menu = $plugin->adminmenu();
    if (is_file($plugin->coderoot . $include)) {
      include ($plugin->coderoot . $include);
    } elseif ($include == 'main.php') {
      print '<h3>'.$plugin->name.'</h3><ul>';
      foreach ($menu as $page => $desc) {
        print '<li>'.PageLink2($page,$desc).'</li>';
      }
      print '</ul>';
    } else {
      print '<br/>'."$page -&gt; ".$I18N->get("pagenotfoundinplugin").'<br/>';#.' '.$plugin->coderoot.$include.'<br/>';
      #print $plugin->coderoot . "$include";
    }
  } else {
    if ($GLOBALS["commandline"]) {
      clineError("Sorry, that module does not exist");
      exit;
    }

    print "$page -&gt; ".$GLOBALS['I18N']->get('notimplemented');
  }
} else {
  Error($GLOBALS['I18N']->get('noaccess'));
}

# some debugging stuff
if (strpos(VERSION,"dev") !== false) {
  $now =  gettimeofday();
  $finished = $now["sec"] * 1000000 + $now["usec"];
  $elapsed = $finished - $GLOBALS["pagestats"]["time_start"];
  $elapsed = ($elapsed / 1000000);
#  print "\n\n".'<!--';
  print '<br clear="all" />';
  print $GLOBALS["pagestats"]["number_of_queries"]." db queries in $elapsed seconds";
  if (function_exists('memory_get_peak_usage')) {
    $memory_usage = 'Peak: ' .memory_get_peak_usage();
  } elseif (function_exists("memory_get_usage")) {
    $memory_usage = memory_get_usage();
  } else {
    $memory_usage = 'Cannot determine with this PHP version';
  }
  print '<br/>Memory usage: '.$memory_usage;
  
  if (isset($GLOBALS["statslog"])) {
    if ($fp = @fopen($GLOBALS["statslog"],"a")) {
      @fwrite($fp,getenv("REQUEST_URI")."\t".$GLOBALS["pagestats"]["number_of_queries"]."\t$elapsed\n");
    }
  }
#  print '-->';
}

if ($ajax || (isset($GLOBALS["commandline"]) && $GLOBALS["commandline"])) {
  @ob_clean();
  exit;
} elseif (!isset($_GET["omitall"])) {
  if (!$GLOBALS['compression_used']) {
    @ob_end_flush();
  }
  include_once 'ui/'.$GLOBALS['ui']."/footer.inc";
}

function parseCline() {
  $res = array();
  $cur = "";
  foreach ($GLOBALS["argv"] as $clinearg) {
    if (substr($clinearg,0,1) == "-") {
      $par = substr($clinearg,1,1);
      $clinearg = substr($clinearg,2,strlen($clinearg));
     # $res[$par] = "";
      $cur = strtolower($par);
      $res[$cur] .= $clinearg;
     } elseif ($cur) {
      if ($res[$cur])
        $res[$cur] .= ' '.$clinearg;
      else
        $res[$cur] .= $clinearg;
    }
  }
/*  ob_end_clean();
  foreach ($res as $key => $val) {
    print "$key = $val\n";
  }
  ob_start();*/
  return $res;
}

