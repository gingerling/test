<?

### bdebuglib.php ### BdeBuG system ##
#
# Bas' Debugging system, needs $debug = TRUE and $verbose = TRUE or $debug_log = {path} in config.php
# Hint: When using log make sure the file gets write permissions
# Will either show debugmessages inline or at shutdown when $GLOBALS['config']['delay_debug_output'] is true
#
#TODO make an object!
#TODO dump to floating  frame
#TODO dump to logfile if not in devmode
#TODO colapsable output

################################################################################
# Init

if (defined('DEVSITE') && DEVSITE && $GLOBALS['config']['debug']) {
  $GLOBALS['config']['head']['bbginfo'] = '<!-- init bdebug -->';

  if( !isset($GLOBALS['config']['delay_debug_output']) ) {
    $GLOBALS['config']['delay_debug_output'] = false;
  }

  ## @@ instead of inline CSS, which may break validation, use a point to a CSS file
  $GLOBALS['config']['head']['bbgstyles'] = '
  <style type="text/css">
  .bbg {
    background-color: #ffc;
    background-image: none;
    cursor: pointer;
    display: -moz-inline-box;
    font-size: 8px;
    text-align: left;
    padding : 0px;
    font-weight: normal;
    color: #000;
    font-style: normal;
    font-family: verdana, sans-serif;
    text-decoration: none;
  }

  .bbg ul{
    border:1px solid #a0a0a0;
    margin:1px;
    padding : 0px;
    list-style : none;
    width : 400px;
  }
  </style>
  ';
}

static $sDebugResult; # This holds the debugmessages when delay is on

function bbg_shutdown () {
  bbg(0,0,-1);
}

register_shutdown_function("bbg_shutdown");

################################################################################
# Utilities

function addDebug($msg) {
  $GLOBALS['sDebugResult'] .= "\n" . $msg;
}

################################################################################
# Main

function bbg($variable, $description = 'Value', $printBuffer = 0) {
	#Safety bailouts
  if (!DEVSITE) return;
  if (ini_get("safe_mode")) return;
  if (array_key_exists('tincanautologin', $GLOBALS['config']) && !in_array($_SERVER['REMOTE_ADDR'],array_keys($GLOBALS['config']['tincanautologin']))) return;
  if ( !array_key_exists('bdebug', $GLOBALS['config']) || ( array_key_exists('bdebug', $GLOBALS['config']) && !$GLOBALS['config']["bdebug"] ) )  return;
  smartDebug($variable, $description, $printBuffer);
}

function smartDebug($variable, $description = 'Value', $nestingLevel = 0) {
  # WARNING recursive
  global $sDebugResult;

  $nestingLevelMax = 5;

//  print "<br/>smartDebug($variable, $description , $nestingLevel) called";
  # Do a backtrace
  if ($nestingLevel == 0) {
    $aBackTrace = debug_backtrace();
    addDebug("<div class='bbg'>\n");
    addDebug("<ul class='bbg_trace'>\n");
    for($iIndex=1; $iIndex < count($aBackTrace); $iIndex++){
//    $iIndex = count($aBackTrace) - 4;
//    $iIndex = 2;

    	addDebug(sprintf("\n<li>%s#%d:%s()</li> ",
      $aBackTrace[$iIndex]['file'],
      $aBackTrace[$iIndex]['line'],
      $aBackTrace[$iIndex]['function']));
    }
    addDebug("</ul>\n");
  }

  # Recurse into array or object
  if ($nestingLevel >= 0) {
    if (!$nestingLevel)
      addDebug("<ul class='bbg_values'>\n");
  	addDebug("<i>$description</i>: ");
	  if (is_array($variable) || is_object($variable)) {
	    if (is_array($variable)) {
	      addDebug("(array)[" . count($variable) . "]");
	    } else {
	      addDebug("<B>(object)</B>[" . count($variable) . "]");
	    }
	    addDebug("<ul>\n");
	    foreach ($variable as $key => $value) {
	      if ($nestingLevel > $nestingLevelMax) {
	        addDebug("<li>\"{$key}\"");
	        //        output ( "Nesting level $nestingLevel reached.\n" );
	      } else {
	        addDebug("<li>\"{$key}\" => ");
	        smartDebug($value, '', $nestingLevel ? $nestingLevel + 1 : 1);
	      }
	      addDebug("</li>\n");
	    }
	    addDebug("</ul>\n");
	  } else
	    addDebug("(" . gettype($variable) . ") '{$variable}'\n");
	  if (!$nestingLevel)
	    addDebug("</li></ul>\n");
  }

  # Wrap it nicely in a div
  if ( $nestingLevel == 0 ) {
    addDebug("</div>\n");
  }

  # Print result when requested
  if ( $GLOBALS['config']['delay_debug_output'] && $nestingLevel == -1
   || !$GLOBALS['config']['delay_debug_output'] && $nestingLevel == 0 ) {
   	echo $sDebugResult;
    $sDebugResult = '';
  }
}

?>
