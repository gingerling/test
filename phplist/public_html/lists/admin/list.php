
<hr/>

<?php
require_once dirname(__FILE__).'/accesscheck.php';

print formStart('class="listListing"');
$some = 0;
if (isset($_GET['s'])) {
  $s = sprintf('%d',$_GET['s']);
} else {
  $s = 0;
}
$baseurl = './?page=list';

$actionresult = '';

## quick DB fix
if (!Sql_Table_Column_Exists($tables['list'],'category')) {
  Sql_Query('alter table '.$tables['list'].' add column category varchar(255) default ""');
}

if (isset($_POST['listorder']) && is_array($_POST['listorder']))
  while (list($key,$val) = each ($_POST['listorder'])) {
    $active = empty($_POST['active'][$key]) ? '0' : '1';
    $query
    = ' update %s'
    . ' set listorder = ?, active = ?'
    . ' where id = ?';
    $query = sprintf($query, $tables['list']);
    Sql_Query_Params($query, array($val, $active, $key));
  }

$access = accessLevel('list');
switch ($access) {
  case 'owner':
    $subselect = ' where owner = ' . $_SESSION['logindetails']['id'];
    $subselect_and = ' and owner = ' . $_SESSION['logindetails']['id'];
    break;
  case 'all':
    $subselect = '';
    $subselect_and = '';
    break;
  case 'none':
  default:
    $subselect = ' where id = 0';
    $subselect_and = ' and id = 0';
    break;
}

print '<div class="actions">';
print PageLinkButton('catlists',$I18N->get('Categorise lists'));
$canaddlist = false;
if ($GLOBALS['require_login'] && !isSuperUser()) {
  $numlists = Sql_Fetch_Row_query("select count(*) from {$tables['list']} where owner = " . $_SESSION['logindetails']['id']);
  if ($numlists[0] < MAXLIST) {
    print PageLinkButton("editlist",$GLOBALS['I18N']->get('Add a list'));
    $canaddlist = true;
  }
} else {
  print PageLinkButton('editlist',$GLOBALS['I18N']->get('Add a list'));
  $canaddlist = true;
}
print '</div>';

if (isset($_GET['delete'])) {
  $delete = sprintf('%d',$_GET['delete']);
  # delete the index in delete
  $actionresult = $GLOBALS['I18N']->get('Deleting') . " $delete ..\n";
  $result = Sql_query(sprintf('delete from '.$tables['list'].' where id = %d %s',$delete,$subselect_and));
  $done = Sql_Affected_Rows();
  if ($done) {
    $result = Sql_query('delete from '.$tables['listuser']." where listid = $delete $subselect_and");
    $result = Sql_query('delete from '.$tables['listmessage']." where listid = $delete $subselect_and");
  }
  $actionresult .= '..' . $GLOBALS['I18N']->get('Done') . "<br /><hr /><br />\n";
  print ActionResult($actionresult);
}

if (!empty($_POST['importcontent'])) {
  include dirname(__FILE__).'/importsimple.php';
}

$html = '';

$aConfiguredListCategories = listCategories();
  
$aListCategories = array();
$req = Sql_Query(sprintf('select distinct category from %s',$tables['list']));
while ($row = Sql_Fetch_Row($req)) {
  array_push($aListCategories,$row[0]);
}

if (sizeof($aListCategories)) {
  if (isset($_GET['tab'])) {
    $current = $_GET['tab'];
  } elseif (isset($_SESSION['last_list_category'])) {
    $current = $_SESSION['last_list_category'];
  } else {
    $current = '';
  }
/*
 *
 * hmm, if lists are marked for a category, which is then removed, this would
 * cause them to not show up
  if (!in_array($current,$aConfiguredListCategories)) {
    $current = '';#$aListCategories[0];
  }
*/
  $_SESSION['last_list_category'] = $current;
  
  if ($subselect == '') {
    $subselect = ' where category = "'.$current.'"';
  } else {
    $subselect .= ' and category = "'.$current.'"';
  }
  $tabs = new WebblerTabs();
  foreach ($aListCategories as $category) {
    $category = trim($category);
    if ($category == '') {
      $category = $GLOBALS['I18N']->get('Uncategorised');
    }

    $tabs->addTab($category,$baseurl.'&amp;tab='.urlencode($category));
  }
  $tabs->setCurrent($current);
  print $tabs->display();
}
$countquery
= ' select *'
. ' from ' . $tables['list']
. $subselect;
$countresult = Sql_query($countquery);
$total = Sql_Num_Rows($countresult);

print '<p>'.$total .' '. $GLOBALS['I18N']->get('Lists').'</p>';
$limit = '';


$query
= ' select *'
. ' from ' . $tables['list']
. $subselect
. ' order by listorder '.$limit;
$result = Sql_query($query);
$ls = new WebblerListing($GLOBALS['I18N']->get('Lists'));
while ($row = Sql_fetch_array($result)) {
  $query
  = ' select count(*)'
  . ' from ' . $tables['listuser']
  . ' where listid = ?';
  $rsc = Sql_Query_Params($query, array($row["id"]));
  $membercount = Sql_Fetch_Row($rsc);
  if ($membercount[0]<=0) {
    $members = $GLOBALS['I18N']->get('None yet');
  } else {
    $members = $membercount[0];
  }

/*
  $query = sprintf('
  select count(distinct userid) as bouncecount from %s listuser,
  %s umb where listuser.userid = umb.user and listuser.listid = ? ',
  $GLOBALS['tables']['listuser'],$GLOBALS['tables']['user_message_bounce'],$row['id'])

  print $query;
*/
  $bouncecount =
    Sql_Fetch_Row_Query(sprintf('select count(distinct userid) as bouncecount from %s listuser, %s umb where listuser.userid = umb.user and listuser.listid = %s ',$GLOBALS['tables']['listuser'],$GLOBALS['tables']['user_message_bounce'],$row['id']));
  if ($bouncecount[0]<=0) {
    $bounces = $GLOBALS['I18N']->get('None yet');
  } else {
    $bounces = $bouncecount[0];
  }

  $desc = stripslashes($row['description']);

//Obsolete by rssmanager plugin 
//  if ($row['rssfeed']) {
//    $feed = $row['rssfeed'];
//    # reformat string, so it wraps if it's very long
//    $feed = ereg_replace("/","/ ",$feed);
//    $feed = ereg_replace("&","& ",$feed);
//    $desc = sprintf('%s: <a href="%s" target="_blank">%s</a><br /> ', $GLOBALS['I18N']->get('rss source'), $row['rssfeed'], $feed) .
//    PageLink2("viewrss&amp;id=".$row["id"],$GLOBALS['I18N']->get('(View Items)')) . '<br />'.
//    $desc;
//  }

  ## allow plugins to add columns
  foreach ($GLOBALS['plugins'] as $plugin) {
    $desc = $plugin->displayLists($row) . $desc;
  }

  $element = '<!-- '.$row['id'].'-->'.$row['name'];
  $ls->addElement($element,PageUrl2("editlist&amp;id=".$row["id"]));
  
  $ls->addColumn($element,
    $GLOBALS['I18N']->get('Members'),
    PageLink2("members",'<span class="membercount">'.$members.'</span>',"id=".$row["id"]).' '.PageLinkDialog('importsimple&list='.$row["id"],$GLOBALS['I18N']->get('add')));
  $ls->addColumn($element,
    $GLOBALS['I18N']->get('Bounces'),
    PageLink2("listbounces",'<span class="bouncecount">'.$bounces.'</span>',"id=".$row["id"]));#.' '.PageLink2('listbounces&id='.$row["id"],$GLOBALS['I18N']->get('view'))
  $ls->addColumn($element,
    $GLOBALS['I18N']->get('Public'),sprintf('<input type="checkbox" name="active[%d]" value="1" %s />',$row["id"],
  $row["active"] ? 'checked="checked"' : ''));
  $owner = adminName($row['owner']);
  if (!empty($owner)) {
    $ls->addColumn($element,
      $GLOBALS['I18N']->get('Owner'),$GLOBALS['require_login'] ? adminName($row['owner']):$GLOBALS['I18N']->get('n/a'));
  }
  if (trim($desc) != '') {
    $ls->addRow($element,
      $GLOBALS['I18N']->get('Description'),$desc);
  }
  $ls->addColumn($element,
    $GLOBALS['I18N']->get('Order'),
    sprintf('<input type="text" name="listorder[%d]" value="%d" size="3" class="listorder" />',$row['id'],$row['listorder']));


  $some = 1;
}
$ls->addSubmitButton('update',$GLOBALS['I18N']->get('Save Changes'));

if (!$some) {
  echo $GLOBALS['I18N']->get('No lists, use Add List to add one');
}  else {
  print $ls->display();
}
/*
  echo '<table class="x" border="0">
      <tr>
        <td>'.$GLOBALS['I18N']->get('No').'</td>
        <td>'.$GLOBALS['I18N']->get('Name').'</td>
        <td>'.$GLOBALS['I18N']->get('Order').'</td>
        <td>'.$GLOBALS['I18N']->get('Functions').'</td>
        <td>'.$GLOBALS['I18N']->get('Active').'</td>
        <td>'.$GLOBALS['I18N']->get('Owner').'</td>
        <td>'.$html . '
    <tr>
        <td colspan="6" align="center">
        <input type="submit" name="update" value="'.$GLOBALS['I18N']->get('Save Changes').'"></td>
      </tr>
    </table>';
}
*/
?>

</form>
<p>
<?php
if ($canaddlist) {
  print PageLinkButton('editlist',$GLOBALS['I18N']->get('Add a list'));
}
?>
</p>
