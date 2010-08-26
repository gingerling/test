<?php
require_once dirname(__FILE__).'/accesscheck.php';

$spb ='<li>';
$spe = '</li>';

print '<ul>';
print $spb.PageLink2("users",$GLOBALS['I18N']->get('users')).$spe;
print $spb.PageLink2("attributes",$GLOBALS['I18N']->get('userattributes')).$spe;
if ($tables["attribute"] && Sql_Table_Exists($tables["attribute"])) {
  $attrmenu = array();
  $res = Sql_Query("select * from {$tables['attribute']}",1);
  while ($row = Sql_Fetch_array($res)) {
    if ($row["type"] == "checkboxgroup" || $row["type"] == "select" || $row["type"] == "radio")
      $attrmenu["editattributes&amp;id=".$row["id"]] = strip_tags($row["name"]);
  }
}
$html = '';
foreach ($attrmenu as $page => $desc) {
  $link = PageLink2($page,$desc);
  if ($link) {
    $html .= $spb.$link.$spe;
  }
}
print $spb.$GLOBALS['I18N']->get('control').$spe.$html.$spb.'&nbsp;<br/>'.$spe;

print $spb.PageLink2("reconcileusers",$GLOBALS['I18N']->get('reconcile')).$spe;
print $spb.PageLink2("usercheck",$GLOBALS['I18N']->get('check')).$spe;
print $spb.PageLink2("massunconfirm",$GLOBALS['I18N']->get('mass unconfirm users')).$spe;
print $spb.PageLink2("massremove",$GLOBALS['I18N']->get('mass remove users')).$spe;
if (ALLOW_IMPORT) {
  print $spb.PageLink2("import",$GLOBALS['I18N']->get('import')).$spe;
}
print $spb.PageLink2("export",$GLOBALS['I18N']->get('export')).$spe;
print '</ul>';
?>
