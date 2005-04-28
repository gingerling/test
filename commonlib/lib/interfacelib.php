<?
require_once dirname(__FILE__).'/accesscheck.php';

# interface functions

class WebblerListing {
  var $title;
  var $help;
  var $elements = array();
  var $columns = array();
  var $sortby = array();
  var $sort = 0;
  var $buttons = array();
  var $initialstate = "block";

  function WebblerListing($title,$help = "") {
    $this->title = $title;
    $this->help = $help;
  }

  function addElement($name,$url = "",$colsize="") {
    if (!isset($this->elements[$name])) {
      $this->elements[$name] = array(
        "name" => $name,
        "url" => $url,
        "colsize" => $colsize,
        "columns" => array(),
        "rows" => array(),
      );
    }
  }

  function deleteElement($name) {
    unset($this->elements[$name]);
  }
  function addSort() {
    $this->sort = 1;
  }

  function addColumn($name,$column_name,$value,$url="",$align="") {
    if (!isset($name))
      return;
    $this->columns[$column_name] = $column_name;
        $this->sortby[$column_name] = $column_name;
    # @@@ should make this a callable function
    $this->elements[$name]["columns"]["$column_name"] = array(
      "value" => $value,
      "url" => $url,
      "align"=> $align,
    );
  }
  
  function renameColumn($oldname,$newname) {
    $this->columns[$oldname] = $newname;
  }
    
  function removeGetParam($remove) {
    $res = "";
    foreach ($_GET as $key => $val) {
      if ($key != $remove) {
        $res .= "$key=".urlencode($val)."&amp;";
      }
    }
    return $res;
  }

  function addRow($name,$row_name,$value,$url="",$align="") {
    if (!isset($name))
      return;
    $this->elements[$name]["rows"]["$row_name"] = array(
      "name" => $row_name,
      "value" => $value,
      "url" => $url,
      "align"=> $align,
    );
  }

  function addInput ($name,$value) {
    $this->addElement($name);
    $this->addColumn($name,"value",
      sprintf('<input type=text name="%s" value="%s" size=40 class="listinginput">',
      strtolower($name),$value));
  }

  function addButton($name,$url) {
    $this->buttons[$name] = $url;
  }

  function listingStart() {
    return '<table cellpadding="0" cellspacing="0" border="0" width="100%">';
  }

  function listingHeader() {
    $tophelp = '';
    if (!sizeof($this->columns)) {
      $tophelp = $this->help;
    }
    $html = '<tr valign="top">';
    $html .= sprintf('<td><a name="%s"></a><div class="listinghdname">%s%s</div></td>',strtolower($this->title),$tophelp,$this->title);
    $c = 1;
    foreach ($this->columns as $column => $columnname) {
      if ($c == sizeof($this->columns)) {
        $html .= sprintf('<td><div class="listinghdelement">%s%s</div></td>',$columnname,$this->help);
      } else {
        if ($this->sortby[$columnname] && $this->sort) {
          $display = sprintf('<a href="./?%s&amp;sortby=%s">%s</a>',$this->removeGetParam("sortby"),urlencode($columnname),$columnname);
        } else {
          $display = $columnname;
        }
        $html .= sprintf('<td><div class="listinghdelement">%s</div></td>',$display);
      }
      $c++;
 
    }
  #  $html .= sprintf('<td align="right"><span class="listinghdelementright">%s</span></td>',$lastelement);
    $html .= '</tr>';
    return $html;
  }

  function listingElement($element) {
    if ($element["colsize"])
      $width = 'width='.$element["colsize"];
    else
      $width = "";
    $html = '<tr valign="middle">';
    if ($element["url"]) {
      $html .= sprintf('<td valign="top" %s class="listingname"><span class="listingname"><a href="%s" class="listingname">%s</a></span></td>',$width,$element["url"],$element["name"]);
    } else {
      $html .= sprintf('<td valign="top" %s class="listingname"><span class="listingname">%s</span></td>',$width,$element["name"]);
    }
    foreach ($this->columns as $column) {
      if (isset($element["columns"][$column]) && $element["columns"][$column]["value"]) {
        $value = $element["columns"][$column]["value"];
      } else {
        $value = $column;
      }
      if (isset($element["columns"][$column]) && $element["columns"][$column]["align"]) {
        $align = $element["columns"][$column]["align"];
      } else {
        $align = '';
      }
      if (isset($element["columns"][$column]) && $element["columns"][$column]["url"]) {
        $html .= sprintf('<td valign="top" class="listingelement%s"><span class="listingelement%s"><a href="%s" class="listingelement">%s</a></span></td>',$align,$align,$element["columns"][$column]["url"],$value);
      } elseif (isset($element["columns"][$column])) {
        $html .= sprintf('<td valign="top" class="listingelement%s"><span class="listingelement%s">%s</span></td>',$align,$align,$element["columns"][$column]["value"]);
      } else {
        $html .= sprintf('<td valign="top" class="listingelement%s"><span class="listingelement%s">%s</span></td>',$align,$align,'');
      }
    }
    $html .= '</tr>';
    foreach ($element["rows"] as $row) {
      if ($row["value"]) {
        $value = $row["value"];
      } else {
        $value = "";
      }
      if ($element["rows"][$row]["align"]) {
        $align = $element["rows"][$row]["align"];
      } else {
        $align = 'left';
      }
      if ($element["rows"][$row]["url"]) {
        $html .= sprintf('<tr><td valign="top" class="listingrowname">
          <span class="listingrowname"><a href="%s" class="listinghdname">%s</a></span>
          </td><td valign="top" class="listingelement%s" colspan=%d>
          <span class="listingelement%s">%s</span>
          </td></tr>',$row["url"],$row["name"],$align,sizeof($this->columns),$align,$value);
      } else {
        $html .= sprintf('<tr><td valign="top" class="listingrowname">
          <span class="listingrowname">%s</span>
          </td><td valign="top" class="listingelement%s" colspan=%d>
          <span class="listingelement%s">%s</span>
          </td></tr>',$row["name"],$align,sizeof($this->columns),$align,$value);
      }
    }
    $html .= sprintf('<!--greenline start-->
      <tr valign="middle">
      <td colspan="%d" bgcolor="#CCCC99"><img height=1 alt="" src="images/transparent.png" width=1 border=0></td></td>
      </tr>
      <!--greenline end-->
    ',sizeof($this->columns)+2);
    return $html;
  }

  function listingEnd() {
    $html = '';$buttons = "";
    if (sizeof($this->buttons)) {
      foreach ($this->buttons as $button => $url) {
        $buttons .= sprintf('<a class="button" href="%s">%s</a>',$url,strtoupper($button));
      }
      $html .= sprintf('
    <tr><td colspan="2">&nbsp;</td></tr>
    <tr><td colspan="%d" align="right">%s</td></tr>
    <tr><td colspan="2">&nbsp;</td></tr>
    ',sizeof($this->columns)+2,$buttons);
    }
    $html .= '</table>';
    return $html;
  }

  function index() {
    return "<a name=top>Index:</a><br />";
  }
                                                                                                                            
  function cmp($a,$b) {
    $sortcol = urldecode($_GET["sortby"]);
    if (!is_array($a) || !is_array($b)) return 0;
    $val1 = strip_tags($a["columns"][$sortcol]["value"]);
    $val2 = strip_tags($b["columns"][$sortcol]["value"]);
    if ($val1 == $val2) return 0;
    return $val1 < $val2 ? -1 : 1;
  }

  function collapse() {
    $this->initialstate = "none";
  }

  function display($add_index = 0) {
    $html = "";
    if (!sizeof($this->elements))
      return "";
#   if ($add_index)
#     $html = $this->index();

    $html .= $this->listingStart();
    $html .= $this->listingHeader();
#    global $float_menu;
#    $float_menu .= "<a style=\"display: block;\" href=\"#".htmlspecialchars($this->title)."\">$this->title</a>";
    if ($this->sort) {
      usort($this->elements,array("WebblerListing","cmp"));
    }
    foreach ($this->elements as $element) {
      $html .= $this->listingElement($element);
    }
    $html .= $this->listingEnd();
 
    $shader = new WebblerShader($this->title);
    $shader->addContent($html);
    $shader->display = $this->initialstate;
    $html = $shader->shaderStart();
    $html .= $shader->header();
    $html .= $shader->dividerRow();
    $html .= $shader->contentDiv();
    $html .= $shader->footer();
    return $html;
  }
}

class topBar {
  var $type = '';

  function topBar($type) {
    $this->type = $type;
  }

  function display($lid,$bid) {
    if ($this->type == "admin") {
      return $this->adminBar($lid,$bid);
    } else {
      return $this->defaultBar();
    }
  }

  function defaultBar() {
    return '';
  }

  function adminBar($lid,$bid) {
    global $config;
    $uri = "http://".$config["websiteurl"].'/?lid='.$lid.'&validate=1';
    if ($config["validator"] && in_array($_SESSION["me"]["loginname"],$config["validator_users"])) {
      $validate = sprintf ('<a href="http://%s/check?uri=%s" class="adminbutton" target="_validate">validate</a>',
      $config["validator"],urlencode($uri));
    }
    return '
<STYLE TYPE="text/css">
   <!--
   a.adminbutton:link {font-family: verdana, sans-serif;font-size : 10px; color : white;background-color : #ff9900; font-weight: normal; border-top: 1px black solid; border-right: 1px black solid; border-left: 1px black solid; text-align : center; text-decoration : none; padding: 2px; width : 80px;}
   a.adminbutton:active {font-family: verdana, sans-serif;font-size : 10px; color : white;background-color : #ff9900; font-weight: normal; border-top: 1px black solid; border-right: 1px black solid; border-left: 1px black solid; text-align : center; text-decoration : none; padding: 2px; width : 80px;}
   a.adminbutton:visited {font-family: verdana, sans-serif;font-size : 10px; color : white;background-color : #ff9900; font-weight: normal; border-top: 1px black solid; border-right: 1px black solid; border-left: 1px black solid; text-align : center; text-decoration : none; padding: 2px; width : 80px;}
   a.adminbutton:hover {font-family: verdana, sans-serif;font-size : 10px; color : white;background-color : #ff9900; font-weight: normal; border-top: 1px black solid; border-right: 1px black solid; border-left: 1px black solid; text-align : center; text-decoration : none; padding: 2px; width : 80px;}
   #admineditline {
     position:absolute;
     top:0px; left:0px;
     width:100%;
     background-color:#CCCC99;
     border-style:none;
     border-bottom: 3px #ff9900 solid;
     z-index: 1000;
   }
   -->
</STYLE>
<script language="Javascript" type="text/javascript" src="/codelib/js/cookielib.js"></script>
<script language="Javascript" type="text/javascript">
function hideadminbar() {
  if (document.getElementById) {
    document.getElementById(\'admineditline\').style.visibility="hidden";
  } else {
    alert("To hide the bar, you need to logout");
  }
}
function closeadminbar() {
  if (document.getElementById) {
    document.getElementById(\'admineditline\').style.visibility="hidden";
    SetCookie("adminbar","hide");
  } else {
    alert("To hide the bar, you need to logout");
  }
}

</script>

<div id="admineditline">
<!--EDIT TAB TABLE starts-->
<table cellpadding="0" cellspacing="0" border="0" width="100%">
<tr><td bgcolor="#CCCC99" height="20" width="70">&nbsp;&nbsp;&nbsp;<a class="adminbutton"
href="'.$config["uploader_dir"]."/?page=edit&b=$bid&id=$lid".'" title="use this link to edit this page">edit page</a></td>
<td bgcolor="#CCCC99" height="20" width="60">&nbsp;&nbsp;&nbsp;'.$validate.'</td>
<!--td bgcolor="#CCCC99" height="20" width="110">&nbsp;&nbsp;&nbsp;<a class="adminbutton" href="%s">change template</a></td-->
<td bgcolor="#CCCC99" height="20" width="70">&nbsp;&nbsp;&nbsp;<a class="adminbutton"
href="'.$config["uploader_dir"]."/?page=logout&return=".urlencode("lid=$lid").'" title="You are logged in as an administrator, click this link to logout">logout</a></td>
<td bgcolor="#CCCC99" height="20">&nbsp;Template: '.getLeafTemplate($lid).'</td>
<td bgcolor="#CCCC99" height="20" width="70"><a href="'.$config["uploader_dir"].'/" class="adminbutton">admin home</a></td>
<td bgcolor="#CCCC99" height="20" width="50"><a href="'.$config["uploader_dir"].'/?page=sitemap" class="adminbutton">sitemap</a></td>
<td bgcolor="#CCCC99" height="20" width="50"><a href="'.$config["uploader_dir"].'/?page=list&id='.$bid.'" class="adminbutton">branch</a></td>
<td bgcolor="#CCCC99" height="20" width="60">&nbsp;&nbsp;&nbsp;<a class="adminbutton"
href="javascript:hideadminbar();" title="hide the administrative bar on this page">hide bar</a></td>
<td bgcolor="#CCCC99" height="20" width="60">&nbsp;&nbsp;&nbsp;<a class="adminbutton"
href="javascript:closeadminbar();" title="hide the administrative bar permanently">close bar</a></td></tr>
</table>
<!--EDIT TAB TABLE ends-->
</div>
';
  }
}

class WebblerTabs {
  var $tabs = array();
  var $current = "";
  var $linkcode = "";
  
  function addTab($name,$url = "") {
    $this->tabs[$name] = $url;
  }
  
  function setCurrent($name) {
    $this->current = strtolower($name);
  }

  function addLinkCode($code) {
    $this->linkcode = $code;
  }

  function display() {
    $html = '<style type=text/css media=screen>@import url( styles/tabs.css );</style>';
    $html .= '<div id="webblertabs">';
    $html .= '<ul>';
    reset($this->tabs);
    foreach ($this->tabs as $tab => $url) {
      if (strtolower($tab) == $this->current) {
        $html .= '<li id=current>';
      } else {
        $html .= '<li>';
      }
      $html .= sprintf('<a href="%s" %s>%s</a>',$url,$this->linkcode,$tab);
      $html .= '</li>';
    }
    $html .= '</ul>';
    $html .= '</div>';
#    $html .= '<span class="faderight">&nbsp;</span>';
    $html .= '<br clear="all" />';
    return $html;
 }
}

class WebblerShader {
  var $name = "Untitled";
  var $content = "";
  var $num = 0;
  var $isfirst = 0;
  var $display = "block";
  var $initialstate = "open";

  function WebblerShader($name) {
    $this->name = $name;
    if (!isset($GLOBALS["shadercount"])) {
      $GLOBALS["shadercount"] = 0;
      $this->isfirst = 1;
    }
    $this->num = $GLOBALS["shadercount"];
    $GLOBALS["shadercount"]++;
  }

  function addContent($content) {
    $this->content = $content;
  }

  function hide() {
    $this->display = 'none';
  }

  function show() {
    $this->display = 'block';
  }

  function shaderJavascript() {
    if ($_SERVER["QUERY_STRING"]) {
      $cookie = "WS?".$_SERVER["QUERY_STRING"];
    } else {
      $cookie = "WS";
    }
    if (!isset($_COOKIE[$cookie])) {
      $_COOKIE[$cookie] = '';
    }

    return '
  <script language="Javascript" type="text/javascript">

  <!--
  var states = Array("'.join('","',split(",",$_COOKIE[$cookie])).'");
  var cookieloaded = 0;
  var expireDate = new Date;
  expireDate.setDate(expireDate.getDate()+365);

  function cookieVal(cookieName) {
    var thisCookie = document.cookie.split("; ")
    for (var i = 0; i < thisCookie.length; i++) {
      if (cookieName == thisCookie[i].split("=")[0]) {
        return thisCookie[i].split("=")[1];
      }
    }
    return 0;
  }

  function saveStates() {
    document.cookie = "WS"+escape(this.location.search)+"="+states+";expires=" + expireDate.toGMTString();
  }

  var agt = navigator.userAgent.toLowerCase();
  var is_major = parseInt(navigator.appVersion);
  var is_nav = ((agt.indexOf(\'mozilla\') != -1) && (agt.indexOf(\'spoofer\') == -1) && (agt.indexOf(\'compatible\') == -1) && (agt.indexOf(\'opera\') == -1) && (agt.indexOf(\'webtv\') == -1));
  var is_nav4up = (is_nav && (is_major >= 4));
  var is_ie = (agt.indexOf("msie") != -1);
  var is_ie3  = (is_ie && (is_major < 4));
  var is_ie4  = (is_ie && (is_major == 4) && (agt.indexOf("msie 5") == -1) && (agt.indexOf("msie 6") == -1));
  var is_ie4up = (is_ie && (is_major >= 4));
  var is_ie5up  = (is_ie  && !is_ie3 && !is_ie4);
  var is_mac = (agt.indexOf("mac") != -1);
  var is_gecko = (agt.indexOf("gecko") != -1);
  var view;

  function getItem (id) {
    if (is_ie4) {
      view = eval(id);
    }
    if (is_ie5up || is_gecko) {
      view = document.getElementById(id);
    }
    return view;
  }

  function shade(id) {
    if(is_ie4up || is_gecko) {

      var shaderDiv = getItem(\'shader\'+id);
      var shaderSpan = getItem(\'shaderspan\'+id);
      var shaderImg = getItem(\'shaderimg\'+id);
      var footerTitle = getItem(\'title\'+id);
      if(shaderDiv.style.display == \'block\') {
        states[id] = "closed";
        shaderDiv.style.display = \'none\';
        shaderSpan.innerHTML = \'<span class="shadersmall">open&nbsp;</span><img src="images/shaderdown.gif" height="9" width="9" border="0">\';
        footerTitle.style.visibility = \'visible\';
        if (shaderImg)
          shaderImg.src = \'images/expand.gif\';
      } else {
        states[id] = "open";
        shaderDiv.style.display = \'block\';
        footerTitle.style.visibility = \'hidden\';
        shaderSpan.innerHTML = \'<span class="shadersmall">close&nbsp;</span><img src="images/shaderup.gif" height="9" width="9" border="0">\';
        if (shaderImg)
          shaderImg.src = \'images/collapse.gif\';
      }
    }
    saveStates();
  }

  function getPref(number) {
    if (states[number] == "open") {
      return "block";
    } else if (states[number] == "closed") {
      return "none";
    }
    return "";
  }

  function start_div(number, default_status) {
    if (is_ie4up || is_gecko) {
      var pref = getPref(number);
      if (pref) {
        default_status = pref;
      }

      document.writeln("<div id=\'shader" + number + "\' name=\'shader" + number + "\' class=\'shader\' style=\'display: " + default_status + ";\'>");
    }
  }


  function end_div(number, default_status) {
    if (is_ie4up || is_gecko) {
      document.writeln("</div>");
    }
  }
  var title_text = "";
  var span_text = "";
  var title_class = "";

  function open_span(number, default_status) {
    if (is_ie4up || is_gecko) {
      var pref = getPref(number);
      if (pref) {
        default_status = pref;
      }
      if(default_status == \'block\') {
        span_text = \'<span class="shadersmall">close&nbsp;</span><img src="images/shaderup.gif" height="9" width="9" border="0">\';
      } else {
        span_text = \'<span class="shadersmall">open&nbsp;</span><img src="images/shaderdown.gif" height="9" width="9" border="0">\';
      }
      document.writeln("<a href=\'javascript: shade(" + number + ");\'><span id=\'shaderspan" + number + "\' class=\'shadersmalltext\'>" + span_text + "</span></a>");
    }
  }

  function title_span(number,default_status,title) {
    if (is_ie4up || is_gecko) {
      var pref = getPref(number);
      if (pref) {
        default_status = pref;
      }
      if(default_status == \'none\') {
        title_text = \'<img src="images/expand.gif" height="9" width="9" border="0">  \'+title;
        title_class = "shaderfootertextvisible";
      } else {
        title_text = \'<img src="images/collapse.gif" height="9" width="9" border="0">   \'+title;
        title_class = "shaderfootertexthidden";
      }
      document.writeln("<a href=\'javascript: shade(" + number + ");\'><span id=\'title" + number + "\' class=\'"+title_class+"\'>" + title_text + "</span></a>");
    }
  }
//-->
</script>
    ';
  }

  function header() {
    $html = sprintf('
<table width="98%%" align="center" cellpadding="0" cellspacing="0" border="0">');
    return $html;
  }
  
  function shadeIcon() {
    return sprintf('
<a href="javascript:shade(%d);" style="text-decoration:none;">&nbsp;<img id="shaderimg%d" src="images/collapse.gif" height="9" width="9" border="0">
    ',$this->num,$this->num);
  }
  
  function titleBar() {
    return sprintf('
  <tr>
      <td colspan="4" class="shaderheader">%s
          <span class="shaderheadertext">&nbsp;%s</span>
         </a>
    </td>
  </tr>',$this->shadeIcon(),$this->name);
  }
  
  function dividerRow() {
    return '
  <tr>
      <td colspan="4" class="shaderdivider"><img src="images/transparent.png" height="1" border="0" width="1"></td>
  </tr>
    ';
  }
  
  function footer() {
    $html = sprintf('

  <tr>
    <td class="shaderborder"><img src="images/transparent.png" height="1" border="0" width="1"></td>
    <td class="shaderfooter"><script language="javascript">title_span(%d,\'%s\',\'%s\');</script>&nbsp;</td>
    <td class="shaderfooterright"><script language="javascript">open_span(%d,\'%s\');</script>&nbsp;</td>
    <td class="shaderborder"><img src="images/transparent.png" height="1" border="0" width="1"></td>
  </tr>
'.$this->dividerRow().'
</table><br/><br/>
    ',$this->num,$this->display,addslashes($this->name),$this->num,$this->display);
    return $html;
  }
  
  function contentDiv() {
    $html = sprintf('  
  <tr>
      <td class="shaderdivider"><img src="images/transparent.png" height="1" border="0" width="1"></td>
      <td colspan=2>
      <script language="javascript">start_div(%d,\'%s\')</script>',$this->num,$this->display);
    $html .= $this->content;
    
    $html .= '
    <script language="javascript">end_div();</script>
    </td>

    <td class="shaderdivider"><img src="images/transparent.png" height="1" border="0" width="1"></td>
  </tr>';
    return $html;
  }
  
  function shaderStart() {
    if (!isset($GLOBALS["shaderJSset"])) {
      $html = $this->shaderJavascript();
      $GLOBALS["shaderJSset"] = 1;
    } else {
      $html = "";
    }
    return $html;
  }  
  
  function display() {
    $html = $this->shaderStart();
    $html .= $this->header();
    $html .= $this->titleBar();
    $html .= $this->dividerRow();
    $html .= $this->contentDiv();
    $html .= $this->footer();
    return $html;
  }
    
}  
  

?>
