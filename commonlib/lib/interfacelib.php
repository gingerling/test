<?
require_once "accesscheck.php";

# interface functions

class WebblerListing {
  var $title;
  var $elements = array();
  var $columns = array();
  var $buttons = array();

  function WebblerListing($title) {
  	$this->title = $title;
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

  function addColumn($name,$column_name,$value,$url="",$align="") {
  	if (!isset($name))
    	return;
  	$this->columns[$column_name] = $column_name;
    $this->elements[$name]["columns"]["$column_name"] = array(
    	"value" => $value,
      "url" => $url,
      "align"=> $align,
    );
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
    $html = '<tr valign="top">';
    $html .= sprintf('<td><a name="%s"><span class="listinghdname">%s</span></a></td>',strtolower($this->title),$this->title);
    foreach ($this->columns as $column) {
      $html .= sprintf('<td><span class="listinghdelement">%s</span></td>',$column);
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
      if ($element["columns"][$column]["value"]) {
      	$value = $element["columns"][$column]["value"];
      } else {
      	$value = $column;
      }
      if ($element["columns"][$column]["align"]) {
      	$align = $element["columns"][$column]["align"];
      } else {
      	$align = '';
      }
      if ($element["columns"][$column]["url"]) {
        $html .= sprintf('<td valign="top" class="listingelement%s"><span class="listingelement%s"><a href="%s" class="listingelement">%s</a></span></td>',$align,$align,$element["columns"][$column]["url"],$value);
      } else {
        $html .= sprintf('<td valign="top" class="listingelement%s"><span class="listingelement%s">%s</span></td>',$align,$align,$element["columns"][$column]["value"]);
      }
    }
    $html .= '</tr>';
    foreach ($element["rows"] as $row) {
      if ($row["value"]) {
      	$value = $row["value"];
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
#  $html .= sprintf('<td align="right"><span class="listingelementright">%s</span></td>',$lastelement);
    /*
    $html .= <td><a class="branches" href="">title</a></td>
  <td align="left">text box</td>
  <td align="right"><input type="Text" name="listorder" value="1" class="listorder" size="1"></td>
  </tr>
    */
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


  function display($add_index = 0) {
    $html = "";
    if (!sizeof($this->elements))
      return "";
# 	if ($add_index)
#   	$html = $this->index();

    $html .= $this->listingStart();
    $html .= $this->listingHeader();
#    global $float_menu;
#    $float_menu .= "<a style=\"display: block;\" href=\"#".htmlspecialchars($this->title)."\">$this->title</a>";
    foreach ($this->elements as $element) {
      $html .= $this->listingElement($element);
    }
    $html .= $this->listingEnd();
    
    $shader = new WebblerShader($this->title);
    $shader->addContent($html);
    return $shader->display();
    
    
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
<tr><td bgcolor="#CCCC99" height="20" width="60">&nbsp;&nbsp;&nbsp;<a class="adminbutton"
href="'.$config["uploader_dir"]."/?page=edit&b=$bid&id=$lid".'" title="use this link to edit this page">edit page</a></td>
<!--td bgcolor="#CCCC99" height="20" width="70">&nbsp;&nbsp;&nbsp;<a class="adminbutton" href="%s">add images</a></td-->
<!--td bgcolor="#CCCC99" height="20" width="110">&nbsp;&nbsp;&nbsp;<a class="adminbutton" href="%s">change template</a></td-->
<td bgcolor="#CCCC99" height="20" width="70">&nbsp;&nbsp;&nbsp;<a class="adminbutton"
href="'.$config["uploader_dir"]."/?page=logout&return=".urlencode("lid=$lid").'" title="You are logged in as an administrator, click this link to logout">logout</a></td>
<td bgcolor="#CCCC99" height="20">&nbsp;Template: '.getLeafTemplate($lid).'</td>
<td bgcolor="#CCCC99" height="20" width="70"><a href="'.$config["uploader_dir"].'/" class="adminbutton">admin home</a></td>
<td bgcolor="#CCCC99" height="20" width="70">&nbsp;&nbsp;&nbsp;<a class="adminbutton"
href="javascript:hideadminbar();" title="hide the administrative bar on this page">hide bar</a></td>
<td bgcolor="#CCCC99" height="20" width="70">&nbsp;&nbsp;&nbsp;<a class="adminbutton"
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
  
  function addTab($name,$url = "") {
    $this->tabs[$name] = $url;
  }
  
  function setCurrent($name) {
    $this->current = $name;
  }
  
  function display() {
    $html = '<style type=text/css media=screen>@import url( styles/tabs.css );</style>';
    $html .= '<div id="webblertabs">';
    $html .= '<ul>';
    reset($this->tabs);
    foreach ($this->tabs as $tab => $url) {
      if ($tab == $this->current) {
        $html .= '<li id=current>';
      } else {
        $html .= '<li>';
      }
      $html .= sprintf('<a href="%s">%s</a>',$url,$tab);
      $html .= '</li>';
    }
    $html .= '</ul>';
    $html .= '</div>';
    $html .= '<span class="faderight">&nbsp;</span><br/><br/>';
    return $html;
 }

}

class WebblerShader {
  var $name = "Untitled";
  var $content = "";
  var $num = 0;
  var $isfirst = 0;
  var $display = "block";

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
    return '
  <script language="Javascript" type="text/javascript">
  
  <!--
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

	function getItem (id) {
		if (is_ie4) {
			var view = eval(id);
		}
		if (is_ie5up || is_gecko) {
			var view = document.getElementById(id);
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
				shaderDiv.style.display = \'none\';
				shaderSpan.innerHTML = \'<span class="shadersmall">open&nbsp;</span><img src="images/shaderdown.gif" height="9" width="9" border="0">\';
        footerTitle.style.visibility = \'visible\';
				if (shaderImg)
          shaderImg.src = \'images/expand.gif\';
			} else {
				shaderDiv.style.display = \'block\';
        footerTitle.style.visibility = \'hidden\';
				shaderSpan.innerHTML = \'<span class="shadersmall">close&nbsp;</span><img src="images/shaderup.gif" height="9" width="9" border="0">\';
				if (shaderImg)
  				shaderImg.src = \'images/collapse.gif\';
			}
		}
	}

	function start_div(number, default_status) {
		if (is_ie4up || is_gecko) {
			document.writeln("<div id=\'shader" + number + "\' name=\'shader" + number + "\' class=\'shader\' style=\'display: " + default_status + ";\'>");
		}
	}


	function end_div(number, default_status) {
		if (is_ie4up || is_gecko) {
			document.writeln("</div>");
		}
	}


	function open_span(number, default_status) {
		if (is_ie4up || is_gecko) {
			if(default_status == \'block\') {
				var span_text = \'<span class="shadersmall">close&nbsp;</span><img src="images/shaderup.gif" height="9" width="9" border="0">\';
			} else {
				var span_text = \'<span class="shadersmall">open&nbsp;</span><img src="images/shaderdown.gif" height="9" width="9" border="0">\';
			}
			document.writeln("<a href=\'javascript: shade(" + number + ");\'><span id=\'shaderspan" + number + "\' class=\'shadersmalltext\'>" + span_text + "</span></a>");
		}
	}
//-->
</script>
    ';
  }
  
  function header() {
    $html .= sprintf('
<table width="95%%" align="center" cellpadding="0" cellspacing="0" border="0">');
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
	    <td colspan="4" class="shaderborder"><img src="images/transparent.png" height="1" border="0" width="1"></td>
	</tr>
    ';
  }
  
  function footer() {
    $html .= sprintf('

	<tr>
		<td class="shaderborder"><img src="images/transparent.png" height="1" border="0" width="1"></td>
    <td class="shaderfootertext"><div id="title%d" class="footertext">%s</div></td>
		<td class="shaderborderright"><script language="javascript">open_span(%d,\'block\');</script>&nbsp;</td>
		<td class="shaderborder"><img src="images/transparent.png" height="1" border="0" width="1"></td>
	</tr>
	<tr>
	    <td colspan="4" class="shaderdivider"><img src="images/transparent.png" height="1" border="0" width="1"></td>
	</tr>
</table><br/><br/>
    ',$this->num,$this->name,$this->num);
    return $html;
  }
  
  function contentDiv() {
    $html .= sprintf('  
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
  #  $html .= $this->titleBar();
    $html .= $this->dividerRow();
    $html .= $this->contentDiv();
    $html .= $this->footer();
    return $html;
  }
    
}  
  

?>
