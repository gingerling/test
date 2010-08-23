

<?php
require_once 'accesscheck.php';

$find_url = '';
$where = '';
$filter = '';
$exclude = '';
$s = 0;
if (isset($_GET['s'])) {
  $s = sprintf('%d',$_GET['s']);
}
$start = !empty($_GET['start']) ? sprintf('%d',$_GET['start']) : 0;

if (isset($_GET['filter'])) {
  $filter = removeXss($_GET['filter']);
  if (isset($_GET['exclude'])) {
    $exclude = $_GET['exclude'];
    $where = ' where page not like "%'.$filter.'%" and entry not like "%'.$filter.'%"';
    $exclude_url = '&exclude='.sprintf('%d',$_GET["exclude"]);
  } else {
    $where = ' where page like "%'.$filter.'%" or entry like "%'.$filter.'%"';
    $exclude_url = '';
  }
  $find_url = '&amp;filter='.urlencode($filter).$exclude_url;
}
$order = ' order by entered desc, id desc';

if (isset($_GET['delete']) && $_GET['delete']) {
  # delete the index in delete
  $delete = sprintf('%d',$_GET['delete']);
  print $GLOBALS['I18N']->get('Deleting') . ' ' . $delete . "..\n";
  if ($require_login && !isSuperUser()) {
  } else {
    Sql_query(sprintf('delete from %s where id = %d',$tables['eventlog'],$delete));
  }
  print '..' . $GLOBALS['I18N']->get('Done') . "<br /><hr/><br />\n";
}

if (isset($_GET['action']) && $_GET['action']) {
  switch($_GET['action']) {
    case 'deleteprocessed':
      Sql_Query(sprintf('delete from %s where date_add(entered,interval 2 month) < current_timestamp',$tables["eventlog"]));
      break;
    case 'deleteall':
      Sql_Query(sprintf('delete from %s %s',$tables["eventlog"],$where));
      break;
  }
}

# view events
$count = Sql_Query("select count(*) from {$tables['eventlog']} $where");
$totalres = Sql_fetch_Row($count);
$total = $totalres[0];

print $total . ' ' .  $GLOBALS['I18N']->get('events') . '<br/>';
if ($total > MAX_USER_PP) {
  if (isset($start) && $start) {
    $limit = "limit $start," . MAX_USER_PP;
  } else {
    $limit = "limit 0,50";
    $start = 0;
  }
  print simplePaging("eventlog$find_url",$start,$total,MAX_USER_PP);

  $query = 'select * from %s %s order by entered desc, id desc %s';
  $query = sprintf($query, $tables['eventlog'], $where, $limit);
  $result = Sql_query($query);
} else {
  $query = 'select * from %s %s order by entered desc, id desc';
  $query = sprintf($query, $tables['eventlog'], $where);
  $result = Sql_Query($query);
}

printf("<a href=\"javascript:deleteRec2('%s','%s');\">%s</a> 
   <a href=\"javascript:deleteRec2('%s','%s');\">%s</a>",
   $GLOBALS['I18N']->get('Are you sure you want to delete all events older than 2 months?'),
   PageURL2("eventlog","Delete","start=$start&amp;action=deleteprocessed"),
   $GLOBALS['I18N']->get('Delete all (&gt; 2 months old)'),

   $GLOBALS['I18N']->get('Are you sure you want to delete all events matching this filter?'),
   PageURL2("eventlog","Delete","start=$start&amp;action=deleteall$find_url"),
   $GLOBALS['I18N']->get('Delete all'));

   if (!Sql_Num_Rows($result)) {
     print '<p class="information">' . $GLOBALS['I18N']->get('No events available') . '</p>';
   }
print '<br/><br/>';
printf('<form method="get" action="">
<input type="hidden" name="page" value="eventlog" />
<input type="hidden" name="start" value="%d" />
%s: <input type="text" name="filter" value="%s" /> %s <input type="checkbox" name="exclude" value="1" %s />
</form><br/>',$start,
$GLOBALS['I18N']->get('Filter'),
htmlspecialchars(stripslashes($filter)),
$GLOBALS['I18N']->get('Exclude filter'),
$exclude == 1 ? 'checked="checked"':'');

$ls = new WebblerListing($GLOBALS['I18N']->get('events'));

# @@@@ Looks like there are a few del, page, date, message which may not be i18nable.

while ($event = Sql_fetch_array($result)) {
  $ls->addElement($event["id"]);
  $ls->addColumn($event["id"],$GLOBALS['I18N']->get('del'),
    sprintf('<a href="javascript:deleteRec(\'%s\');">%s</a>',
      PageURL2("eventlog","delete","start=$start&amp;delete=".$event["id"]),$GLOBALS['I18N']->get('del')));
  $ls->addColumn($event["id"],$GLOBALS['I18N']->get('page'),$event["page"]);
  $ls->addColumn($event["id"],$GLOBALS['I18N']->get('date'),$event["entered"]);
  $ls->addColumn($event["id"],$GLOBALS['I18N']->get('message'),$event["entry"]);
}
print $ls->display();

?>
