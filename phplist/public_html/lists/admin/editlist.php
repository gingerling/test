<?php

require_once 'accesscheck.php';

if ($GLOBALS["require_login"] && !isSuperUser()) {
  $access = accessLevel("list");
  switch ($access) {
    case "owner":
      $subselect = " where owner = ".$_SESSION["logindetails"]["id"];
      if ($id) {
        Sql_Query("select id from ".$tables["list"]. $subselect . " and id = $id");
        if (!Sql_Affected_Rows()) {
          Fatal_Error($GLOBALS['I18N']->get('You do not have enough priviliges to view this page'));
          return;
        }
      }
      break;
    case "all":
      $subselect = ""; break;
    case "none":
    default:
      if ($id) {
        Fatal_Error($GLOBALS['I18N']->get('You do not have enough priviliges to view this page'));
        return;
      }
      $subselect = " where id = 0";
      break;
  }
}

if (!empty($_GET['id'])) {
  $id = sprintf('%d',$_GET["id"]);
} else {
  $id = 0;
}
if ($id)
  echo "<br />".PageLink2("members",$GLOBALS['I18N']->get('Members of this list'),"id=$id");
echo "<hr />";
if (isset($_POST["save"]) && $_POST["save"] == $GLOBALS['I18N']->get('Save') && isset($_POST["listname"]) && $_POST["listname"]) {
  if ($GLOBALS["require_login"] && !isSuperUser())
    $owner = $_SESSION["logindetails"]["id"];

  if (!isset($_POST["active"])) $_POST["active"] = 0;
  $_POST['listname'] = removeXss($_POST['listname']);
  ## prefix isn't used any more
  $_POST['prefix'] = '';

  if ($id) {
    $query
    = ' update %s'
    . ' set name = ?, description = ?, active = ?,'
    . '     listorder = ?, prefix = ?, owner = ?'
    . ' where id = ?';
    $query = sprintf($query, $tables['list']);
    $result = Sql_Query_Params($query, array($_POST['listname'],
       $_POST['description'], $_POST['active'], $_POST['listorder'],
       $_POST['prefix'], $_POST['owner'], $id));
  } else {
    $query
    = ' insert into %s'
    . '    (name, description, entered, listorder, owner, prefix, active)'
    . ' values'
    . '    (?, ?, current_timestamp, ?, ?, ?, ?)';
    $query = sprintf($query, $tables['list']);
  }
#  print $query;
  $result = Sql_Query_Params($query, array($_POST['listname'],
     $_POST['description'], $_POST['listorder'], $_POST['owner'],
     $_POST['prefix'], $_POST['active']));
  if (!$id)
    $id = Sql_Insert_Id($tables['list'], 'id');
  ## allow plugins to save their fields
  foreach ($GLOBALS['plugins'] as $plugin) {
    $result = $result && $plugin->processEditList($id);
  }

  $_SESSION['action_result'] = $GLOBALS['I18N']->get('Record Saved') . ": $id";
  Redirect('list');
}

if (!empty($id)) {
  $result = Sql_Query("SELECT * FROM $tables[list] where id = $id");
  $list = Sql_Fetch_Array($result);
} else {
  $list = array(
    'name' => '',
//    'rssfeed' => '',  //Obsolete by rssmanager plugin
    'active' => 0,
    'listorder' => 0,
    'description' => '',
  );
}
ob_end_flush();

?>

<?php echo formStart()?>
<input type=hidden name=id value="<?php echo $id ?>">
<table border=0>
<tr><td><?php echo $GLOBALS['I18N']->get('List name'); ?>:</td><td><input type=text name="listname" value="<?php echo  htmlspecialchars(StripSlashes($list["name"]))?>"></td></tr>
<tr><td><?php echo $GLOBALS['I18N']->get('Check this box to make this list active (listed)'); ?></td><td><input type="checkbox" name="active" value="1" <?php echo $list["active"] ? 'checked' : ""; ?>></td></tr>
<tr><td><?php echo $GLOBALS['I18N']->get('Order for listing'); ?></td><td><input type=text name="listorder" value="<?php echo $list["listorder"] ?>" size="5"></td></tr>
<!--tr><td><?php echo $GLOBALS['I18N']->get('Subject Prefix'); ?></td><td><input type=text name="prefix" value="<?php echo $list["prefix"]; ?>" size="5"></td></tr>-->
<?php if ($GLOBALS["require_login"] && (isSuperUser() || accessLevel("editlist") == "all")) {
  print '<tr><td>' . $GLOBALS['I18N']->get('Owner') . '</td><td><select name="owner">';
  $admins = $GLOBALS["admin_auth"]->listAdmins();
  foreach ($admins as $adminid => $adminname) {
    printf ('<option value="%d" %s>%s</option>',$adminid,$adminid == $list["owner"]? "selected":"",$adminname);
  }
  print '</select></td></tr>';
} else {
  print '<input type=hidden name="owner" value="'.$_SESSION["logindetails"]["id"].'">';
}
//obsolete, moved to rssmanager plugin
//if (ENABLE_RSS) {
// if (!empty($list["rssfeed"])) {
//   $validate = sprintf('(<a href="http://feedvalidator.org/check?url=%s" target="_blank">%s</a>)',urlencode($list["rssfeed"]),$GLOBALS['I18N']->get('validate'));
//   $viewitems = PageLink2("viewrss&id=".$id,$GLOBALS['I18N']->get('View Items'));
// } else {
//   $validate = '';
//   $viewitems = '';
// }
// printf('<tr><td>%s %s %s</td><td><input type=text name="rssfeed" value="%s" size=50></td></tr>',
//   $GLOBALS['I18N']->get('rss Source'), $validate,$viewitems,htmlspecialchars($list["rssfeed"]));
//}

  ### allow plugins to add rows
  foreach ($GLOBALS['plugins'] as $plugin) {
    print $plugin->displayEditList($list);
  }

?>
<tr><td colspan=2><?php echo $GLOBALS['I18N']->get('List Description'); ?></td></tr>
<tr><td colspan=2><textarea name="description" cols="55" rows="15"><?php echo htmlspecialchars(StripSlashes($list["description"])) ?></textarea></td></tr>
<tr><td align="center"><input type="submit" name="save" value="<?php echo $GLOBALS['I18N']->get('Save'); ?>"></td><td align="right"><input type="reset"></td></tr>
</table>
</form>
