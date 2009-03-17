<?

/**
 * Bas deBuG system ##
 *
 * Bas' Debugging system, needs $debug = TRUE and $verbose = TRUE or $debug_log = {path} in config.php
 * Hint: When using log make sure the file gets write permissions
 * Will either show debugmessages inline or at shutdown when $GLOBALS['config']['delay_debug_output'] is true
 *
 * @todo make an object!
 * @todo dump to floating  frame
 * @todo dump to logfile if not in devmode
 * @todo colapsable output
 */
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
$GLOBALS['config']['head']['bbgstyles'] = '<link rel="StyleSheet" href="/codelib/css/bbg.css" type="text/css" />';
//}


$sDebugResult; # This holds the debugmessages when delay is on

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
//  print "<br/>devsite = " . DEVSITE;
  if ( defined('DEVSITE') && !DEVSITE) return;
  
//  print "<br/>Safe mode = " . ini_get("safe_mode");
  if (ini_get("safe_mode")) return;
  
//  print "<br/>autologin = " . array_key_exists('tincanautologin', $GLOBALS['config']); 
//  print "<br/>REMOTE_ADDR = " . $_SERVER['REMOTE_ADDR'];
  if (array_key_exists('tincanautologin', $GLOBALS['config']) && !in_array($_SERVER['REMOTE_ADDR'],array_keys($GLOBALS['config']['tincanautologin']))) return;
  
//  print "<br/>bdebug = " . array_key_exists('bdebug', $GLOBALS['config']);
  if ( !array_key_exists('bdebug', $GLOBALS['config']) || ( array_key_exists('bdebug', $GLOBALS['config']) && !$GLOBALS['config']["bdebug"] ) )  return;
  
//  print "<br>smartDebug($variable, $description, $printBuffer)"; 
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
