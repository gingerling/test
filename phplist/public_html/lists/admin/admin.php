
<script language="Javascript" src="js/jslib.js" type="text/javascript"></script>

<?php
#Variable initialisation to avoid PHP notices.
if (isset($_GET['admin']))
	$admin = (int) $_GET['admin'];
else
	$admin = 0;

require_once dirname(__FILE__).'/accesscheck.php';

$start = sprintf('%d',!empty($_GET['start'])?$_GET['start']:0);
print PageLink2("admins",$GLOBALS['I18N']->get('listofadministrators'),"start=$start");

require dirname(__FILE__) . "/structure.php";
$struct = $DBstruct["admin"];
$id = !empty($_REQUEST["id"]) ? sprintf('%d',$_REQUEST["id"]) : 0;

echo "<hr /><br />";

$noaccess = 0;
$accesslevel = accessLevel("admin");
switch ($accesslevel) {
  case "owner":
    $id = $_SESSION["logindetails"]["id"];break;
  case "all":
    $subselect = "";break;
  case "none":
  default:
    $noaccess = 1;
}
if ($noaccess) {
  print Error($GLOBALS['I18N']->get('No Access'));
  return;
}

#Update password?
if (isset($_POST['update']) && $_POST['update'] && isset($id)){
  $adminId = $id;
  if (ENCRYPT_ADMIN_PASSWORDS){
  	//Send token email.
  	sendPasswordMailTo($_POST['id'], $_POST['email']);
  }
  /*else {
  	//Retrieve the actual password and send a reminder.
  	$query = sprintf("select password from %s where id = %d", $tables["admin"], $adminId);
  	$row = SQL_fetch_row_query($query);
  	
    sendMail ($_POST['email'],$GLOBALS['I18N']->get('yourpassword'),"\n\n".$GLOBALS['I18N']->get('yourpasswordis')." $row[0]");    
  }
  print $GLOBALS['I18N']->get("An email was sent to your address. This will allow you to ".(ENCRYPT_ADMIN_PASSWORDS?"change":"recover")." your password").".<BR></BR>";*/
}

if (!empty($_POST["change"])) {
  if (empty($_POST["id"])) {
    # new one
    $result = Sql_query(sprintf('SELECT count(*) FROM %s WHERE namelc="%s"',
      $tables["admin"],strtolower(normalize($_POST["loginname"]))));
    $totalres = Sql_fetch_Row($result);
    $total = $totalres[0]; 
    if (!$total) {
      Sql_Query(sprintf('insert into %s (loginname,namelc,created) values("%s","%s",current_timestamp)',
        $tables["admin"],strtolower(normalize($_POST["loginname"])),strtolower(normalize($_POST["loginname"]))));
      $id = Sql_Insert_Id($tables['admin'], 'id');
    } else {
      $id = 0;
    }
  } else {
    $id = sprintf('%d',$_POST["id"]);
  }

  if ($id) {
    reset($struct);
    while (list ($key,$val) = each ($struct)) {
      $a = $b = '';
      if (strstr($val[1],':'))
        list($a,$b) = explode(":",$val[1]);
      if ($a != "sys" && isset($_POST[$key])){
        Sql_Query("update {$tables["admin"]} set $key = \"".addslashes($_POST[$key])."\" where id = $id");        
      }
    }
    ## check for password changes
    if (isset($_POST['password'])) {
      Sql_Query("update {$tables["admin"]} set password = \"".addslashes($_POST['password'])."\" where id = $id");
    }
    if (is_array($_POST["attribute"]))
      while (list($key,$val) = each ($_POST["attribute"])) {
        Sql_Query(sprintf('replace into %s (adminid,adminattributeid,value)
          values(%d,%d,"%s")',$tables["admin_attribute"],$id,$key,addslashes($val)));
      }
    Sql_Query(sprintf('update %s set modifiedby = "%s" where id = %d',$tables["admin"],adminName($_SESSION["logindetails"]["id"]),$id));

    if ($accesslevel == "all" && isset($_POST['access']) && is_array($_POST["access"])) {
      Sql_Query("delete from {$tables["admin_task"]} where adminid = $id");
      if ( is_array($_POST["access"]))
        while (list($key,$val) = each ($_POST["access"]))
          Sql_Query(sprintf('replace into %s (adminid,taskid,level) values(%d,%d,%d)',$GLOBALS['tables']["admin_task"],$id,$key,$val));
    }
    Info($GLOBALS['I18N']->get('Changes saved'));
  } else {
    Info($GLOBALS['I18N']->get('Error adding new admin'));
  }
}

if (!empty($_POST["setdefault"])) {
  Sql_Query("delete from {$tables["admin_task"]} where adminid = 0");
  if (is_array($_POST["access"]))
    while (list($key,$val) = each ($_POST["access"]))
      Sql_Query("insert into {$tables["admin_task"]} (adminid,taskid,level) values(0,$key,$val)");
  Info($GLOBALS['I18N']->get('Current set of permissions made default'));
}

if (!empty($_POST["resetaccess"])) {
  $reverse_accesscodes = array_flip($access_levels);
  $req = Sql_Query("select * from {$tables["task"]} order by type");
  while ($row = Sql_Fetch_Array($req)) {
    $level = $system_pages[$row["type"]][$row["page"]];
    Sql_Query(sprintf('replace into %s (adminid,taskid,level) values(%d,%d,%d)',
      $tables["admin_task"],$id,$row["id"],$reverse_accesscodes[$level]));
  }
}

if (!empty($_GET["delete"])) {
  $delete = sprintf('%d',$_GET['delete']);
  # delete the index in delete
  print $GLOBALS['I18N']->get('Deleting')." $delete ..\n";
  Sql_query(sprintf('delete from %s where id = %d',$GLOBALS["tables"]["admin"],$delete));
  Sql_query(sprintf('delete from %s where adminid = %d',$GLOBALS["tables"]["admin_attribute"],$delete));
  Sql_query(sprintf('delete from %s where adminid = %d',$GLOBALS["tables"]["admin_task"],$delete));
  print '..'.$GLOBALS['I18N']->get('Done')."<br /><hr><br />\n";
}

if ($id) {
  print $GLOBALS['I18N']->get('Edit Administrator').': ';
  $result = Sql_query("SELECT * FROM {$tables["admin"]} where id = $id");
  $data = sql_fetch_array($result);
  print $data["loginname"];
  if ($data["loginname"] != "admin" && $accesslevel == "all")
    printf( "<br /><li><a href=\"javascript:deleteRec('%s');\">Delete</a> %s\n",PageURL2("admin","","delete=$id"),$admin["loginname"]);
} else {
  $data = array();
  print $GLOBALS['I18N']->get('Add a new Administrator');
}
print "<br/>";
print '<p>'.$GLOBALS['I18N']->get('Admin Details').':'.formStart().'<table border=1>';
printf('<input type=hidden name="id" value="%d">',$id);

reset($struct);
while (list ($key,$val) = each ($struct)) {
  $a = $b = '';
  if (empty($data[$key])) $data[$key] = '';
  if (strstr($val[1],':'))
    list($a,$b) = explode(":",$val[1]);
  if ($a == "sys") {
  	#If key is 'password' and the passwords are encrypted, locate two radio buttons to allow an update.
  	if ($b == 'Password' && ENCRYPT_ADMIN_PASSWORDS){
  	  printf('<tr><td>%s (%s)</td><td>%s<input type=radio name="update" value=\'0\' checked>%s</input><input type=radio name="update" value=\'1\'>%s</input></td></tr>', $GLOBALS['I18N']->get('Password'), $GLOBALS['I18N']->get('hidden'), (ENCRYPT_ADMIN_PASSWORDS?$GLOBALS['I18N']->get('Update it?'):$GLOBALS['I18N']->get('Remind it?')), $GLOBALS['I18N']->get('No'), $GLOBALS['I18N']->get('Yes'));
  	} else {
  		if ($b != 'Password'){
    	  printf('<tr><td>%s</td><td>%s</td></tr>',$GLOBALS['I18N']->get($b),$data[$key]);
    	} else {
    		printf('<tr><td>%s</td><td><input type="text" name="%s" value="%s" size=30></td></tr>'."\n",$GLOBALS['I18N']->get('Password'),$key,stripslashes($data[$key]));
		}
    }
  } elseif ($key == "loginname" && $data[$key] == "admin") {
    printf('<tr><td>'.$GLOBALS['I18N']->get('Login Name').'</td><td>admin</td></tr>');
    print('<input type=hidden name="loginname" value="admin">');
  } 
  	elseif ($key == "superuser" || $key == "disabled") {
      if ($accesslevel == "all") {
      	#If key is 'superuser' or 'disable' locate a boolean combo box.
        printf('<tr><td>%s</td><td>', $GLOBALS['I18N']->get($val[1]));
	    printf('<select name="%s" size="1">', $key);
	    print('<option value="1" '.(!empty($data[$key])?' selected':'').'>'.$GLOBALS['I18N']->get('Yes').'</option>');
	    print('<option value="0" '.(empty($data[$key])?' selected':'').'>'.$GLOBALS['I18N']->get('No').'</option></select>');
		print('</td></tr>'."\n");
      }
  } elseif (!empty($val[1]) && !strpos($key,'_')) {
      printf('<tr><td>%s</td><td><input type="text" name="%s" value="%s" size=30></td></tr>'."\n",$GLOBALS['I18N']->get($val[1]),$key,stripslashes($data[$key]));
  }
}
$res = Sql_Query("select
  {$tables["adminattribute"]}.id,
  {$tables["adminattribute"]}.name,
  {$tables["adminattribute"]}.type,
  {$tables["adminattribute"]}.tablename from
  {$tables["adminattribute"]}
  order by {$tables["adminattribute"]}.listorder");
while ($row = Sql_fetch_array($res)) {
  if ($id) {
    $val_req = Sql_Fetch_Row_Query("select value from {$tables["admin_attribute"]}
      where adminid = $id and adminattributeid = $row[id]");
    $row["value"] = $val_req[0];
  } else {
    $row['value'] = '';
  }

  if ($row["type"] == "checkbox") {
    $checked_index_req = Sql_Fetch_Row_Query("select id from $table_prefix"."adminattr_".$row["tablename"]." where name = \"Checked\"");
    $checked_index = $checked_index_req[0];
    $checked = $checked_index == $row["value"]?"checked":"";
    printf('<tr><td>%s</td><td><input style="attributeinput" type=hidden name="cbattribute[]" value="%d"><input style="attributeinput" type=checkbox name="attribute[%d]" value="Checked" %s></td></tr>'."\n",$row["name"],$row["id"],$row["id"],$checked);
  }
  else
  if ($row["type"] != "textline" && $row["type"] != "hidden")
    printf ("<tr><td>%s</td><td>%s</td></tr>\n",$row["name"],AttributeValueSelect($row["id"],$row["tablename"],$row["value"],"adminattr"));
  else
    printf('<tr><td>%s</td><td><input style="attributeinput" type=text name="attribute[%d]" value="%s" size=30></td></tr>'."\n",$row["name"],$row["id"],htmlspecialchars(stripslashes($row["value"])));
}
print '<tr><td colspan=2><input type=submit name=change value="'.$GLOBALS['I18N']->get('Save Changes').'"></table>';

# what pages can this administrator see:
if (!$data["superuser"] && $accesslevel == "all") {
  print $I18N->get('strAccessExplain');
  print '<p>'.$GLOBALS['I18N']->get('Access Details').':</p><table border=1>';
  reset($access_levels);
  printf ('<tr><td colspan="%d" align=center>'.$GLOBALS['I18N']->get('Access Privileges').'</td></tr>',sizeof($access_levels)+2);
  print '<tr><td>'.$GLOBALS['I18N']->get('Type').'</td><td>'.$GLOBALS['I18N']->get('Page')."</td>\n";
  foreach ($access_levels as $level)
    printf('<td>%s</td>',$GLOBALS['I18N']->get($level));
  print "</tr>\n";
  $req = Sql_Query("select * from {$tables["task"]} order by type");
  while ($row = Sql_Fetch_Array($req)) {
    printf('<tr><td>%s</td><td>%s</td>',$row["type"],$row["page"]);
    reset($access_levels);
    while (list($key,$level) = each ($access_levels)) {
      $current_level_req = Sql_Query(sprintf('
        select level from %s where adminid = %d and taskid = %d',$tables["admin_task"],$id,$row["id"]));
      if (!Sql_Affected_Rows()) {
        # take a default
        $default = $system_pages[$row["type"]][$row["page"]];
     #   if ($row["type"] == "system") {
     #     $curval = 0;
     #   } else {
     #     $curval = 4;
     #   }
          # by default disable everything
          $curval = 0;
        if ($level == $default) $curval = $key;
      } else {
        $current_level = Sql_Fetch_Row($current_level_req);
        $curval = $current_level[0];
      }
      printf('<td><input type=radio name="access[%d]" value="%s" %s></td>',$row["id"],$key,$key == $curval ? "checked":"");
    }
    print "</tr>\n";
  }

  printf('<tr><td colspan="%d"><input type=submit name=setdefault value="'.$GLOBALS['I18N']->get('Set these permissions as default').'"><input type=submit name=change value="'.$GLOBALS['I18N']->get('Save Changes').'"></table>',sizeof($access_levels)+2);
  print '<input type=submit name="resetaccess" value="'.$GLOBALS['I18N']->get('Reset to Default').'">';
}
print "</form>";
?>


