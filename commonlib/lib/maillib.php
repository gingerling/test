<?php
/**
 * library with mail functions
 * 
 * this file is shared between the webbler and PHPlist via commonlib
 * 
 * @package Common
 * @subpackage maillib 
 */
require_once dirname(__FILE__).'/accesscheck.php';

function stripHTML($text) {

  # strip HTML, and turn links into the full URL
  $text = preg_replace("/\r/","",$text);

  #$text = preg_replace("/\n/","###NL###",$text);
  $text = preg_replace("/<script[^>]*>(.*?)<\/script\s*>/is","",$text);
  $text = preg_replace("/<style[^>]*>(.*?)<\/style\s*>/is","",$text);

  # would prefer to use < and > but the strip tags below would erase that.
#  $text = preg_replace("/<a href=\"(.*?)\"[^>]*>(.*?)<\/a>/is","\\2\n{\\1}",$text,100);

#  $text = preg_replace("/<a href=\"(.*?)\"[^>]*>(.*?)<\/a>/is","[URLTEXT]\\2[/URLTEXT][LINK]\\1[/LINK]",$text,100);

  $text = preg_replace("/<a.*href=[\"\'](.*)[\"\'][^>]*>(.*)<\/a>/Umis","[URLTEXT]\\2[ENDURLTEXT][LINK]\\1[ENDLINK]\n",$text);

  $text = preg_replace("/<b>(.*?)<\/b\s*>/is","*\\1*",$text);
  $text = preg_replace("/<h[\d]>(.*?)<\/h[\d]\s*>/is","**\\1**\n",$text);
#  $text = preg_replace("/\s+/"," ",$text);
  $text = preg_replace("/<i>(.*?)<\/i\s*>/is","/\\1/",$text);
  $text = preg_replace("/<\/tr\s*?>/i","<\/tr>\n\n",$text);
  $text = preg_replace("/<\/p\s*?>/i","<\/p>\n\n",$text);
  $text = preg_replace("/<br[^>]*?>/i","<br>\n",$text);
  $text = preg_replace("/<br[^>]*?\/>/i","<br\/>\n",$text);
  $text = preg_replace("/<table/i","\n\n<table",$text);
  $text = strip_tags($text);

  # find all URLs and replace them back
  preg_match_all('~\[URLTEXT\](.*)\[ENDURLTEXT\]\[LINK\](.*)\[ENDLINK\]~Umis', $text, $links);
  foreach ($links[0] as $matchindex => $fullmatch) {
    $linktext = $links[1][$matchindex];
    $linkurl = $links[2][$matchindex];
    # check if the text linked is a repetition of the URL
    if (trim($linktext) == trim($linkurl) ||
      'http://'.trim($linktext) == trim($linkurl)) {
        $linkreplace = $linkurl;
    } else {
      $linkreplace = $linktext.' <'.$linkurl.'>';
    }
  #  $text = preg_replace('~'.preg_quote($fullmatch).'~',$linkreplace,$text);
    $text = str_replace($fullmatch,$linkreplace,$text);
  }
  $text = preg_replace("/<a href=[\"\'](.*?)[\"\'][^>]*>(.*?)<\/a>/is","[URLTEXT]\\2[ENDURLTEXT][LINK]\\1[ENDLINK]",$text,500);

  $text = replaceChars($text);

  $text = preg_replace("/###NL###/","\n",$text);
  # reduce whitespace
  while (preg_match("/  /",$text))
    $text = preg_replace("/  /"," ",$text);
  while (preg_match("/\n\s*\n\s*\n/",$text))
    $text = preg_replace("/\n\s*\n\s*\n/","\n\n",$text);
  $text = wordwrap($text,70);

  return $text;
}

?>