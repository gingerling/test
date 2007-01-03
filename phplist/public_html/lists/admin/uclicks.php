<?php

# click stats per url
require_once dirname(__FILE__).'/accesscheck.php';

if (isset($_GET['id'])) {
  $id = sprintf('%d',$_GET['id']);
} else {
  $id = 0;
}

$access = accessLevel('uclicks');
switch ($access) {
  case 'owner':
    $select_tables = $GLOBALS['tables']['linktrack_ml']. ' as ml, '.$GLOBALS['tables']['message'].' as message, '.$GLOBALS['tables']['linktrack_forward']. ' as forward ';
    $owner_and = ' and message.id = ml.messageid and message.owner = '.$_SESSION['logindetails']['id'];
    break;
  case 'all':
    $select_tables = $GLOBALS['tables']['linktrack_ml']. ' as ml, '.$GLOBALS['tables']['linktrack_forward']. ' as forward ';
    $owner_and = '';
    break;
    break;
  case 'none':
  default:
    print $GLOBALS['I18N']->get('You do not have access to this page');
    return;
    break;
}

if (!$id) {
  print $GLOBALS['I18N']->get('Select URL to view');
  $req = Sql_Query(sprintf('select forward.id,url, sum(clicked) as numclicks, count(messageid) as msgs from %s
    where clicked %s and forward.id = ml.forwardid group by url order by numclicks desc limit 50',
    $select_tables,$owner_and));
  $ls = new WebblerListing($GLOBALS['I18N']->get('Available URLs'));
  while ($row = Sql_Fetch_Array($req)) {
    $ls->addElement($row['url'],PageURL2('uclicks&amp;id='.$row['id']));
    $ls->addColumn($row['url'],$GLOBALS['I18N']->get('msgs'),$row['msgs']);
    $ls->addColumn($row['url'],$GLOBALS['I18N']->get('clicks'),$row['numclicks']);
  }
  print $ls->display();
  return;
}

$ls = new WebblerListing($GLOBALS['I18N']->get('URL Click Statistics'));

$urldata = Sql_Fetch_Array_Query(sprintf('select url from %s where id = %d',
  $GLOBALS['tables']['linktrack_forward'],$id));
print '<h1>'.$GLOBALS['I18N']->get('Click Details for a URL').' <b>'.$urldata['url'].'</b></h1>';

$req = Sql_Query(sprintf('select messageid,firstclick,date_format(latestclick,
  "%%e %%b %%Y %%H:%%i") as latestclick,total,clicked from %s where forwardid = %d 
  ',$GLOBALS['tables']['linktrack_ml'],$id));
$summary = array();
while ($row = Sql_Fetch_Array($req)) {
  $msgsubj = Sql_Fetch_Row_query(sprintf('select subject from %s where id = %d',$GLOBALS['tables']['message'],$row['messageid']));
  $element = $GLOBALS['I18N']->get('msg').' '.$row['messageid'].': '.substr($msgsubj[0],0,25);
#  $element = sprintf('<a href="%s" target="_blank" class="url" title="%s">%s</a>',$row['url'],$row['url'],substr(str_replace('http://','',$row['url']),0,50));
#  $total = Sql_Verbose_Query(sprintf('select count(*) as total from %s where messageid = %d and url = "%s"',
#    $GLOBALS['tables']['linktrack'],$id,$row['url']));

  if (CLICKTRACK_SHOWDETAIL) {
    $uniqueclicks = Sql_Fetch_Array_Query(sprintf('select count(distinct userid) as users from %s
      where messageid = %d and forwardid = %d',
      $GLOBALS['tables']['linktrack_uml_click'],$row['messageid'],$id));
  }

  $ls->addElement($element,PageUrl2('mclicks&id='.$row['messageid']));
  $ls->addColumn($element,$GLOBALS['I18N']->get('firstclick'),formatDateTime($row['firstclick'],1));
  $ls->addColumn($element,$GLOBALS['I18N']->get('latestclick'),$row['latestclick']);
  $ls->addColumn($element,$GLOBALS['I18N']->get('sent'),$row['total']);
  $ls->addColumn($element,$GLOBALS['I18N']->get('clicks'),$row['clicked']);
  $perc = sprintf('%0.2f',($row['clicked'] / $row['total'] * 100));
  $ls->addColumn($element,$GLOBALS['I18N']->get('clickrate'),$perc.'%');
  if (CLICKTRACK_SHOWDETAIL) {
    $ls->addColumn($element,$GLOBALS['I18N']->get('unique clicks'),$uniqueclicks['users']);
    $perc = sprintf('%0.2f',($uniqueclicks['users'] / $row['total'] * 100));
    $ls->addColumn($element,$GLOBALS['I18N']->get('unique clickrate'),$perc.'%');
    $summary['uniqueclicks'] += $uniqueclicks['users'];
  }
  $ls->addColumn($element,$GLOBALS['I18N']->get('who'),PageLink2('userclicks&amp;msgid='.$row['messageid'].'&amp;fwdid='.$id,$GLOBALS['I18N']->get('view users')));
  $summary['totalclicks'] += $row['numclicks'];
}
$ls->addElement('total');
$ls->addColumn('total',$GLOBALS['I18N']->get('clicks'),$summary['totalclicks']);
if (CLICKTRACK_SHOWDETAIL) {
  $ls->addColumn('total',$GLOBALS['I18N']->get('unique clicks'),$summary['uniqueclicks']);
}
print $ls->display();
?>