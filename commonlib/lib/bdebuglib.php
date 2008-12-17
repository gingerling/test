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

# DEVSITE is not set! so this code doesn't work. Why is DEVSITE not set?
//if (defined('DEVSITE') && DEVSITE && array_key_exists('bdebug', $GLOBALS['config']) ) {

// the above doens't work, because this lib is loaded before DEVSITE is set.
// it's also loaded before euserverconfig, so you'll have to set it in the site config instead
// instead using a function or object and initialising it from inside bbg, would be better

$GLOBALS['config']['head']['bbginfo'] = '<!-- init bdebug -->';

if( !isset($GLOBALS['config']['delay_debug_output']) ) {
  $GLOBALS['config']['delay_debug_output'] = false;
}

//$GLOBALS['config']['head']['jquery'] = sprintf('<script type="text/javascript" src="%s"></script>', 
//  '/codelib/js/' . $GLOBALS['config']['jquery'] );
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
.bbg_trace {
  font-size: 5px;
} 
.bbg ul{
  border:1px solid #a0a0a0;
  margin:1px;
  padding : 0px;
  list-style : none;
  width : 80em;
}

.bbg li{
  color: #000;
    backgroud-color:#0cf;
  
}

a.info{
  position:relative; /*this is the key*/
/*  z-index:24; */ 
  color:#000;
  text-decoration:none
}

a.info:hover{
/*  z-index:25; */ 
}

a.info span{
  display: none;
}

a.info:hover span{
  /*the span will display just on :hover state*/
  display:block;
  position:absolute;
  top:2em; left:1em; width:60em;
  border:1px solid #ccf;
  background-color:#cff;
}
    
</style>
';
//}

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

  if ($nestingLevel == 0) {
    addDebug("<div class='bbg'>\n");
    addDebug("<ul class='bbg_values'><a class='info'>\n");
  
    # Do a backtrace in a hidden span for tooltip
    $aBackTrace = debug_backtrace();
    addDebug("<span>\n");
    for($iIndex=1; $iIndex < count($aBackTrace); $iIndex++){
//    $iIndex = count($aBackTrace) - 4;
//    $iIndex = 2;

      addDebug(sprintf("\n<li>%s#%d:%s()</li> ",
      $aBackTrace[$iIndex]['file'],
      $aBackTrace[$iIndex]['line'],
      $aBackTrace[$iIndex]['function']));
    }
    addDebug("</span>\n");
  }
  
//  print "<br/>smartDebug($variable, $description , $nestingLevel) called";
  # Recurse into array or object
  if ($nestingLevel >= 0) {
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
	    addDebug("</li>\n");
  }
  
  # Wrap it nicely in a div
  if ( $nestingLevel == 0 ) {
    addDebug("</a></ul>\n");
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
