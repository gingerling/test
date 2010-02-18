<?php
require_once dirname(__FILE__).'/accesscheck.php';

$access = accessLevel("send");
switch ($access) {
  case "owner":
    $subselect = " where owner = ".$_SESSION["logindetails"]["id"];
    $ownership = ' and owner = '.$_SESSION["logindetails"]["id"];
    break;
  case "all":
    $subselect = "";
    $ownership = '';
    break;
  case "none":
  default:
    $subselect = " where id = 0";
    $ownership = " and id = 0";
    break;
}
$some = 0;

# handle commandline
if ($GLOBALS["commandline"]) {
#  error_reporting(63);
  $cline = parseCline();
  reset($cline);
  if (!$cline || !is_array($cline) || !$cline["s"] || !$cline["l"]) {
    clineUsage("-s subject -l list [-f from] < message");
    exit;
  }

  $listnames = explode(" ",$cline["l"]);
  $listids = array();
  foreach ($listnames as $listname) {
    if (!is_numeric($listname)) {
      $listid = Sql_Fetch_Array_Query(sprintf('select * from %s where name = "%s"',
        $tables["list"],$listname));
      if ($listid["id"]) {
        $listids[$listid["id"]] = $listname;
      }
     } else {
      $listid = Sql_Fetch_Array_Query(sprintf('select * from %s where id = %d',
        $tables["list"],$listname));
      if ($listid["id"]) {
        $listids[$listid["id"]] = $listid["name"];
      }
    }
  }

  $_POST["targetlist"] = array();
  foreach ($listids as $key => $val) {
    $_POST["targetlist"][$key] = "signup";
    $lists .= '"'.$val.'"' . " ";
  }

  if ($cline["f"]) {
    $_POST["from"] = $cline["f"];
  } else {
    $_POST["from"] = getConfig("message_from_name") . ' '.getConfig("message_from_address");
  }
  $_POST["subject"] = $cline["s"];
  $_POST["send"] = "1";
  $_POST["footer"] = getConfig("messagefooter");
  while (!feof (STDIN)) {
    $_POST["message"] .= fgets(STDIN, 4096);
  }

#  print clineSignature();
#  print "Sending message with subject ".$_POST["subject"]. " to ". $lists."\n";
}
ob_start();

### check for draft messages

if (!empty($_GET['delete'])) {
  if ($_GET['delete'] == 'alldraft') {
    $req = Sql_Query(sprintf('select id from %s where status = "draft" %s',$GLOBALS['tables']['message'],$ownership));
    while ($row = Sql_Fetch_Row($req)) {
      deleteMessage($row[0]);
    }
  } else {
    deleteMessage(sprintf('%d',$_GET['delete']));
  }
}


$req = Sql_Query(sprintf('select id,entered,subject,unix_timestamp(now()) - unix_timestamp(entered) as age from %s where status = "draft" %s',$GLOBALS['tables']['message'],$ownership));
$numdraft = Sql_Num_Rows($req);
if ($numdraft > 0 && !isset($_GET['id']) && !isset($_GET['new'])) {
  print '<p class="button">'.PageLink2('send&amp;new=1',$I18N->get('start a new message')).'</p>';
  print '<h3>'.$I18N->get('Choose an existing draft message to work on').'</h3>';
  $ls = new WebblerListing($I18N->get('Draft messages'));
  while ($row = Sql_Fetch_Array($req)) {
    $element = '<!--'.$row['id'].'-->'.$row['subject'];
    $ls->addElement($element);
    $ls->addColumn($element,$I18N->get('edit'),PageLink2('send&amp;id='.$row['id'],$I18N->get('edit')));
    $ls->addColumn($element,$I18N->get('entered'),$row['entered']);
    $ls->addColumn($element,$I18N->get('age'),secs2time($row['age']));
    $ls->addColumn($element,$I18N->get('del'),PageLink2('send&amp;delete='.$row['id'],$I18N->get('delete')));
  }
  $ls->addButton($I18N->get('delete all'),PageUrl2('send&amp;delete=alldraft'));
  print $ls->display();
  return;
}

include "send_core.php";

if ($done) {
  if ($GLOBALS["commandline"]) {
    ob_end_clean();
    print clineSignature();
    print "Message with subject ".$_POST["subject"]. " was sent to ". $lists."\n";
    exit;
  }
  return;
}

/*if (!$_GET["id"]) {
  Sql_Query(sprintf('insert into %s (subject,status,entered)
    values("(no subject)","draft",current_timestamp)',$GLOBALS["tables"]["message"]));
  $id = Sql_Insert_Id($GLOBALS['tables']['message'], 'id');
  Redirect("send&amp;id=$id");
}
*/
$list_content = '
<p class="button">'.$GLOBALS['I18N']->get('selectlists').':</p>
<ul>
<li><input type="checkbox" name="targetlist[all]"';
  if (isset($_POST["targetlist"]["all"]) && $_POST["targetlist"]["all"])
    $list_content .= "checked";
$list_content .= ' />'.$GLOBALS['I18N']->get('alllists').'</li>';

$list_content .= '<li><input type="checkbox" name="targetlist[allactive]"';
  if (isset($_POST["targetlist"]["allactive"]) && $_POST["targetlist"]["allactive"])
    $list_content .= 'checked="checked"';
$list_content .= ' />'.$GLOBALS['I18N']->get('All Active Lists').'</li>';

$result = Sql_query("SELECT * FROM $tables[list] $subselect order by name");
while ($row = Sql_fetch_array($result)) {
  # check whether this message has been marked to send to a list (when editing)
  $checked = 0;
  if ($_GET["id"]) {
    $sendtolist = Sql_Query(sprintf('select * from %s where
      messageid = %d and listid = %d',$tables["listmessage"],$_GET["id"],$row["id"]));
    $checked = Sql_Num_Rows($sendtolist);
  }
  $list_content .= sprintf('<li><input type="checkbox" name="targetlist[%d]" value="%d" ',$row["id"],$row["id"]);
  if ($checked || (isset($_POST["targetlist"][$row["id"]]) && $_POST["targetlist"][$row["id"]]))
    $list_content .= 'checked="checked"';
  $list_content .= " />".stripslashes($row["name"]);
  if ($row["active"])
    $list_content .= ' ('.$GLOBALS['I18N']->get('listactive').')';
  else
    $list_content .= ' ('.$GLOBALS['I18N']->get('listnotactive').')';

  $desc = nl2br(stripslashes($row["description"]));

  $list_content .= "<br/>$desc</li>";
  $some = 1;
}
$list_content .= '</ul>';

if (USE_LIST_EXCLUDE) {
  $list_content .= '
    <hr/><h3>'.$GLOBALS['I18N']->get('selectexcludelist').'</h3><p class="button">'.$GLOBALS['I18N']->get('excludelistexplain').'</p>
    <ul>';

  $dbdata = Sql_Fetch_Row_Query(sprintf('select data from %s where name = "excludelist" and id = %d',
    $GLOBALS["tables"]["messagedata"],$_GET["id"]));
  $excluded_lists = explode(",",$dbdata[0]);

  $result = Sql_query(sprintf('SELECT * FROM %s %s order by name',$GLOBALS["tables"]["list"],$subselect));
  while ($row = Sql_fetch_array($result)) {
    $checked = in_array($row["id"],$excluded_lists);
    $list_content .= sprintf('<li><input type="checkbox" name="excludelist[%d]" value="%d" ',$row["id"],$row["id"]);
    if ($checked || isset($_POST["excludelist"][$row["id"]]))
      $list_content .= 'checked="checked"';
    $list_content .= " />".stripslashes($row["name"]);
    if ($row["active"])
      $list_content .= ' ('.$GLOBALS['I18N']->get('listactive').')';
    else
      $list_content .= ' ('.$GLOBALS['I18N']->get('listnotactive').')';

    $desc = nl2br(stripslashes($row["description"]));

    $list_content .= "<br/>$desc</li>";
  }
  $list_content .= '</ul>';
}

if (!$some)
  $list_content = $GLOBALS['I18N']->get('nolistsavailable');

$list_content .= '

<input class="submit" type="submit" name="send" value="'.$GLOBALS['I18N']->get('sendmessage').'" />

';

if (isset($show_lists) && $show_lists) {
  print $list_content;
} 

print '</form>';
