<?

print '<script language="Javascript" src="js/progressbar.js" type="text/javascript"></script>';

ignore_user_abort();
set_time_limit(500);
#error_reporting(63);
if (!isset($GLOBALS["tmpdir"])) {
	$GLOBALS["tmpdir"] = ini_get("upload_tmp_dir");
}
if (!is_dir($GLOBALS["tmpdir"]) || !is_writable($GLOBALS["tmpdir"])) {
	$GLOBALS["tmpdir"] = ini_get("upload_tmp_dir");
}
#if (ini_get("open_basedir")) {
if (!is_dir($GLOBALS["tmpdir"]) || !is_writable($GLOBALS["tmpdir"])) {
	Warn("The temporary directory for uploading (".$GLOBALS["tmpdir"].") is not writable, so import will fail");
}

if (!isset($GLOBALS["assign_invalid_default"]))
	$GLOBALS["assign_invalid_default"] = 'Invalid Email [number]';


?>
<p>

<?php
function my_shutdown () {
#	print "Shutting down";
#	print connection_status(); # with PHP 4.2.1 buggy. http://bugs.php.net/bug.php?id=17774
}

function parsePlaceHolders($templ,$data) {
	$retval = $templ;
	foreach ($data as $key => $val) {
  	if (!is_array($val)) {
      $retval = preg_replace('/\['.preg_quote($key).'\]/i',$val,$retval);
   	}
 	}
  return $retval;
}

function clearImport() {
	if (is_file($_SESSION["import_file"])) {
  	unlink($_SESSION["import_file"]);
  }
  $_SESSION["import_file"] = "";
	$_SESSION["systemindex"] = "";
	$_SESSION["import_attribute"] = "";
  $_SESSION["test_import"] = "";
  $_SESSION["assign_invalid"] = "";
  $_SESSION["overwrite"] = "";
}

register_shutdown_function("my_shutdown");
require_once $GLOBALS["coderoot"] . "structure.php";

# identify system values from the database structure
$system_attributes = array();
reset($DBstruct["user"]);
while (list ($key,$val) = each ($DBstruct["user"])) {
  if (!ereg("^sys",$val[1])) {
    $system_attributes[strtolower($val[1])] = $key;
  } #elseif (ereg("sysexp:(.*)",$val[1],$regs)) {
    #$system_attributes[strtolower($regs[1])] = $key;
  #}
}

ob_end_flush();

if ($_GET["reset"] == "yes") {
	clearImport();
  print '<h1>Import cleared</h1>';
  print PageLink2($_GET["page"],"Continue");
  return;
} else {
	if ($_SESSION["test_import"])
		print PageLink2($_GET["page"]."&amp;reset=yes","Reset Import session");
}

if(isset($_POST["import"])) {
  $test_import = (isset($_POST["import_test"]) && $_POST["import_test"] == "yes");
  $_SESSION["test_import"] = $test_import;

  if(!$_FILES["import_file"]) {
    Fatal_Error("File is either too large or does not exist.");
    return;
  }
  if(empty($_FILES["import_file"])) {
    Fatal_Error("No file was specified. Maybe the file is too big? ");
    return;
  }
  if (filesize($_FILES["import_file"]['tmp_name']) > 1000000) {
    # if we allow more, we will certainly run out of memory
  	Fatal_Error("File too big, please split it up into smaller ones");
    return;
  }
  if( !preg_match("/^[0-9A-Za-z_\.\-\/\s \(\)]+$/", $_FILES["import_file"]["name"]) ) {
    Fatal_Error("Use of wrong characters in filename: ".$_FILES["import_file"]["name"]);
    return;
  }
  if (!$_POST["notify"] && !$test_import) {
    Fatal_Error("Please choose whether to sign up immediately or to send a notification");
    return;
  } else {
  	$_SESSION["notify"] = $_POST["notify"];
  }

  if ($_FILES["import_file"] && $_FILES["import_file"]['size'] > 10) {
		$newfile = $GLOBALS['tmpdir'].'/'. $_FILES['import_file']['name'].time();
		move_uploaded_file($_FILES['import_file']['tmp_name'], $newfile);
    $_SESSION["import_file"] = $newfile;
		if( !($fp = fopen ($newfile, "r"))) {
			Fatal_Error("Cannot read ".$newfile." is not readable !");
      return;
   	}
    fclose($fp);
  } elseif ($_FILES["import_file"]) {
    Fatal_Error("Something went wrong while uploading the file. Empty file received. Maybe the file is too big, or you have no permissions to read it.");
    return;
  }
  if(isset($_POST["import_record_delimiter"]) && $_POST["import_record_delimiter"] != "") {
  	$_SESSION["import_record_delimiter"] = $_POST["import_record_delimiter"];
  } else {
  	$_SESSION["import_record_delimiter"] = "\n";
  }

  if (!isset($_POST["import_field_delimiter"]) || $_POST["import_field_delimiter"] == "" || $_POST["import_field_delimiter"] == "TAB") {
    $_SESSION["import_field_delimiter"] = "\t";
  } else {
    $_SESSION["import_field_delimiter"] = $_POST["import_field_delimiter"];
  }
  $_SESSION["show_warnings"] = $_POST["show_warnings"];
	$_SESSION["assign_invalid"] = $_POST["assign_invalid"];
  $_SESSION["omit_invalid"] = $_POST["omit_invalid"];
	$_SESSION["lists"] = $_POST["lists"];
	$_SESSION["groups"] = $_POST["groups"];
  $_SESSION["overwrite"] = $_POST["overwrite"];
  $_SESSION["notify"] = $_POST["notify"];
  $_SESSION["listname"] = $_POST["listname"];
  $_SESSION["retainold"] = $_POST["retainold"];
}

if ($_GET["confirm"]) {
	$_SESSION["test_import"] = '';
}

if ($_SESSION["import_file"]) {
	print "<p>Reading emails from file ... ";
  flush();
  $fp =  fopen ($_SESSION["import_file"], "r");
  $email_list = fread($fp, filesize ($_SESSION["import_file"]));
  fclose($fp);
  print "..ok</p>";
  flush();

  // Clean up email file
  $email_list = trim($email_list);
  $email_list = str_replace("\r","\n",$email_list);
  $email_list = str_replace("\n\r","\n",$email_list);
  $email_list = str_replace("\n\n","\n",$email_list);

  if ($_SESSION["import_record_delimiter"] != "\n") {
    $email_list = str_replace($_SESSION["import_record_delimiter"],"\n",$email_list);
  };

  // Split file/emails into array
  $email_list = explode("\n",$email_list);
  $header = array_shift($email_list);
  $header = str_replace('"','',$header);
  $total = sizeof($email_list);
  $headers = explode($_SESSION["import_field_delimiter"],$header);
  $headers = array_unique($headers);

  $req = Sql_Query(sprintf('select * from %s order by listorder,name',$tables["attribute"]));
  while ($row = Sql_Fetch_Array($req)) {
    $attributes[$row["id"]] = $row["name"];
  }
  $used_systemattr = array();
  $used_attributes = array();
  for ($i=0;$i<sizeof($headers);$i++) {
    $column = clean($headers[$i]);
#    print "<h1>$column</h1>";
    $column = preg_replace('#/#','',$column);
    if (in_array(strtolower($column),array_keys($system_attributes))) {
      $_SESSION["systemindex"][strtolower($column)] = $i;
      array_push($used_systemattr,strtolower($column));
  #  	print "$column => $i<br/>";
    } elseif (strtolower($column) == "list membership") {
      # skip this one
      array_push($used_systemattr,strtolower($column));
    } else {
    	if ($_SESSION["import_attribute"]["$column"]["record"]) {
      	# mapping has been defined
     	} elseif (isset($_POST["column$i"])) {
#        print "$column -> ".$_POST["column$i"].'<br/>';
        $_SESSION["import_attribute"]["$column"] = array("index"=>$i,"record"=>$_POST["column$i"],"column" => "$column");
      } else {
        $existing = Sql_Fetch_Row_Query("select id from ".$tables["attribute"]." where name = \"$column\"");
        $_SESSION["import_attribute"]["$column"] = array("index"=>$i,"record"=>$existing[0],"column" => $column);
        array_push($used_attributes,$existing[0]);
      }
    }
  }
  if (!isset($_SESSION["systemindex"]["email"])) {
    Fatal_Error("Cannot find column with email");
    return;
  }

  $unused_systemattr = array_diff(array_keys($system_attributes),$used_systemattr);
  $unused_attributes = array_diff(array_keys($attributes),$used_attributes);
  $options = '<option value="new">-- Create new one</option>';
  foreach ($unused_systemattr as $sysindex) {
    $options .= sprintf('<option value="%s">%s</option>',$sysindex,substr($system_attributes[$sysindex],0,25));
  }
  foreach ($unused_attributes as $attindex) {
    $options .= sprintf('<option value="%s">%s</option>',$attindex,substr($attributes[$attindex],0,25));
  }

  $ls = new WebblerListing("Import Attributes");
  $request_mapping = 0;
  foreach ($_SESSION["import_attribute"] as $column => $rec) {
    if (!$rec["record"]) {
      $request_mapping = 1;
      $ls->addElement($column);
      $ls->addColumn($column,"select",'<select name="column'.$rec["index"].'">'.$options.'</select>');
    }
  }
  if ($request_mapping) {
    $ls->addButton('Continue','javascript:document.importform.submit()');
    print '<p>Please identify the target of the following unknown columns</p>';
    print '<form name="importform" method=post>';
    print $ls->display();
    print '</form>';
    return;
  }
}

if ($_SESSION["test_import"]) {
	$ls = new WebblerListing("Summary");
  foreach ($_SESSION["import_attribute"] as $column => $rec) {
    $ls->addElement($column);
    if ($rec["record"] == "new") {
	    $ls->addColumn($column,"maps to","Create new Attribute");
    } else {
			$ls->addColumn($column,"maps to",$attributes[$rec["record"]]);
   	}
  }
  print $ls->display();
  print '<h3>'.$total.' lines will be imported</h3>';
  print '<p>'.PageLink2($_GET["page"]."&amp;confirm=yes","Confirm Import").'</p>';
}

if (sizeof($email_list)) {
  $import_field_delimiter = $_SESSION["import_field_delimiter"];

  if (sizeof($email_list) > 300 && !$_SESSION["test_import"]) {
    # this is a possibly a time consuming process, so show a progress bar
    print '<script language="Javascript" type="text/javascript"> document.write(progressmeter); start();</script>';
    flush();
    # increase the memory to make sure we are not running out
    ini_set("memory_limit","16M");
  }

#	print "A: ".sizeof($import_attribute);
  reset($system_attributes);
  foreach ($system_attributes as $key => $val) {
 #   print "<br/>$key => $val ".$_SESSION["systemindex"][$key];
    if (isset($_SESSION["systemindex"][$key]))
      $system_attribute_mapping[$key] = $_SESSION["systemindex"][$key];
  }

  // Parse the lines into records

#  print "<br/>Loading emails .. ";
  flush();
  $count = array();
  $count["email_add"] = 0;
  $count["exist"] = 0;
  $count["list_add"] = 0;
  $count["group_add"] = 0;
  $c = 1;
  $count["invalid_email"] = 0;
  $num_lists = sizeof($_SESSION["lists"]);
  $total = sizeof($email_list);
  $cnt = 0;
  $count["emailmatch"] = 0;
  $count["fkeymatch"] = 0;
  $count["dataupdate"] = 0;
  $additional_emails = 0;
  foreach ($email_list as $line) {
  #	print $line.'<br/>';
		$user = array();
    # get rid of text delimiters generally added by spreadsheet apps
  	$line = str_replace('"','',$line);

    $values = explode($_SESSION["import_field_delimiter"],$line);

    reset($system_attribute_mapping);
    $system_values = array();
    foreach ($system_attribute_mapping as $column => $index) {
    	#print "$column = ".$values[$index]."<br/>";
      $system_values[$column] = $values[$index];
    }
    $index = clean($system_values["email"]);
    $invalid = 0;
    if (!$index) {
      if ($_SESSION["show_warnings"])
        Warn("Record has no email: $c -> $line");
      $index = "Invalid Email $c";
			$system_values["email"] = $_SESSION["assign_invalid"];
      $invalid = 1;
      $count["invalid_email"]++;
    }
    if (sizeof($values) != (sizeof($_SESSION["import_attribute"]) + sizeof($_SESSION["system_attributes"]))
    	&& $test_import && $_POST["show_warnings"])
      Warn("Record has more values than header indicated (".
      	sizeof($values). "!=". 
        (sizeof($_SESSION["import_attribute"]) + sizeof($_SESSION["system_attributes"]))
      ."), this may cause trouble: $index");
    if (!$invalid || ($invalid && $_SESSION["omit_invalid"] != "yes")) {
      $user["systemvalues"] = $system_values;
      reset($_SESSION["import_attribute"]);
      $replace = array();
			while (list($key,$val) = each ($_SESSION["import_attribute"])) {
        $user[$val["index"]] = addslashes($values[$val["index"]]);
        $replace[$key] = addslashes($values[$val["index"]]);
   		}
		} else {
     # Warn("Omitting invalid one: $email");
    }
    $user["systemvalues"]["email"] = parsePlaceHolders($system_values["email"],array_merge($replace,$system_values,array("number" => $c)));
    $user["systemvalues"]["email"] = clean($user["systemvalues"]["email"]);
    $c++;
    if ($_SESSION["test_import"]) {
     # print "<br/><b>$index</b><br/>";
      $html = '';
      foreach ($user["systemvalues"] as $column => $value) {
      	if ($value) {
	        $html .= "$column -> $value<br/>\n";
        } else {
        	$html .= "$column -> clear value<br/>\n";
        }
      }
      reset($_SESSION["import_attribute"]);
      foreach ($_SESSION["import_attribute"] as $column => $item) {
        if ($user[$item["index"]]) {
        	if ($item["record"] == "new") {
          	$html .= ' New Attribute: '.$item["column"];
          } else {
          	$html .= $attributes[$item["record"]];
          }
          $html .= " -> ".$user[$item["index"]]."<br>";
        }
      }
      #if ($html) print "<blockquote>$html</blockquote>";
    } else {
    	# do import
      # create new attributes
      foreach ($_SESSION["import_attribute"] as $column => $item) {
       	if ($item["record"] == "new") {
        	Sql_Query(sprintf('insert into %s (name,type) values("%s","textline")',
          	$tables["attribute"],addslashes($column)));
         	$attid = Sql_Insert_id();
          Sql_Query(sprintf('update %s set tablename = "attr%d" where id = %d',
          	$tables["attribute"],$attid,$attid));
          Sql_Query("create table ".$GLOBALS["table_prefix"]."listattr_attr".$attid." 
          	(id integer not null primary key auto_increment, name varchar(255) unique,
            listorder integer default 0)");
          $_SESSION["import_attribute"][$column]["record"] = $attid;
        }
      }
			$new = 0;
			$cnt++;
      if ($cnt % 25 == 0) {
      	print "<br/>\n$cnt/$total";
        flush();
      }
      if ($user["systemvalues"]["foreign key"]) {
        $result = Sql_query(sprintf('select id,uniqid from %s where foreignkey = "%s"',
          $tables["user"],$user["systemvalues"]["foreign key"]));
      #	print "<br/>Using foreign key for matching: ".$user["systemvalues"]["foreign key"];
        $count["fkeymatch"]++;
        $exists = Sql_Affected_Rows();
        $existing_user = Sql_fetch_array($result);
        # check whether the email will clash
        $clashcheck = Sql_Fetch_Row_Query(sprintf('select id from %s
          where email = "%s"',$tables["user"],$user["systemvalues"]["email"]));
        if ($clashcheck[0] != $existing_user["id"]) {
        	$duplicatecount++;
          $notduplicate = 0;
          $c=0;
          while (!$notduplicate) {
            $c++;
            $req = Sql_Query(sprintf('select id from %s where email = "%s"',
              $tables["user"],"duplicate$c ".$user["systemvalues"]["email"]));
            $notduplicate = !Sql_Affected_Rows();
          }
          if (!$_SESSION["retainold"]) {
          	Sql_Query(sprintf('update %s set email = "%s" where email = "%s"',
							$tables["user"],"duplicate$c ".$user["systemvalues"]["email"],$user["systemvalues"]["email"]));
     		    addUserHistory("duplicate$c ".$user["systemvalues"]["email"],"Duplication clash ",' User marked duplicate email after clash with imported record');
					} else {
	          if ($_SESSION["show_warnings"]) print Warn("Duplicate Email".$user["systemvalues"]["email"]. " user imported as &quot;duplicate$c ".$user["systemvalues"]["email"]."&quot;");
	          $user["systemvalues"]["email"] = "duplicate$c ".$user["systemvalues"]["email"];
     			}
        }
      } else {
        $result = Sql_query(sprintf('select id,uniqid from %s where email = "%s"',$tables["user"],$user["systemvalues"]["email"]));
      #	print "<br/>Using email for matching: ".$user["systemvalues"]["email"];
        $count["emailmatch"]++;
        $exists = Sql_Affected_Rows();
        $existing_user = Sql_fetch_array($result);
      }
      if ($exists) {
        // User exist, remember some values to add them to the lists
        $count["exist"]++;
        $userid = $existing_user["id"];
        $uniqid = $existing_user["uniqid"];
      } else {
        // user does not exist
        $new = 1;
        // Create unique number
        mt_srand((double)microtime()*1000000);
        $randval = mt_rand();
        # this is very time consuming when importing loads of users as it does a lookup
        # needs speeding up if possible
        $uniqid = getUniqid();
        $confirmed = $_SESSION["notify"] != "yes" && !preg_match("/Invalid Email/i",$index);

        $query = sprintf('INSERT INTO %s (email,entered,confirmed,uniqid)
          values("%s",now(),%d,"%s")',
          $tables["user"],$user["systemvalues"]["email"],$confirmed,$uniqid);
        $result = Sql_query($query,1);
        $userid = Sql_insert_id();
        if (!$userid) {
          # no id returned, so it must have been a duplicate entry
          if ($_SESSION["show_warnings"]) print Warn("Duplicate Email".$user["systemvalues"]["email"]);
          $c = 0;
          while (!$userid) {
            $c++;
            $query = sprintf('INSERT INTO %s (email,entered,confirmed,uniqid)
              values("%s",now(),%d,"%s")',
              $tables["user"],$user["systemvalues"]["email"]." ($c)",0,$uniqid);
            $result = Sql_query($query,1);
            $userid = Sql_insert_id();
          }
          $user["systemvalues"]["email"] = $user["systemvalues"]["email"]." ($c)";
        }

        $count["email_add"]++;
        $some = 1;
      }

      reset($_SESSION["import_attribute"]);
      if ($new || (!$new && $_SESSION["overwrite"] == "yes")) {
        $query = "";
        $count["dataupdate"]++;
        $old_data = Sql_Fetch_Array_Query(sprintf('select * from %s where id = %d',$tables["user"],$userid));
  			$old_data = array_merge($old_data,getUserAttributeValues('',$userid));
  			$history_entry = 'http://'.getConfig("website").$GLOBALS["adminpages"].'/?page=user&id='.$userid."\n\n";

        foreach ($user["systemvalues"] as $column => $value) {
          $query .= sprintf('%s = "%s",',$system_attributes[$column],$value);
        }
        if ($query) {
          $query = substr($query,0,-1);
          # this may cause a duplicate error on email, so add ignore
          Sql_Query("update ignore {$tables["user"]} set $query where id = $userid");
        }
        foreach ($_SESSION["import_attribute"] as $item) {
          if ($user[$item["index"]]) {
            $attribute_index = $item["record"];
            $uservalue = $user[$item["index"]];
            # check whether this is a textline or a selectable item
            $att = Sql_Fetch_Row_Query("select type,tablename,name from ".$tables["attribute"]." where id = $attribute_index");
            switch ($att[0]) {
              case "select":
              case "radio":
                $val = Sql_Query("select id from $table_prefix"."listattr_$att[1] where name = \"$uservalue\"");
                # if we do not have this value add it
                if (!Sql_Affected_Rows()) {
									# check the highest listorder and add one extra
                  $highestreq = Sql_Fetch_Row_Query("select listorder from $table_prefix"."listattr_$att[1] order by listorder desc limit 1");
                  $highest = sprintf('%d',$highestreq[0] +1);
                  Sql_Query("insert into $table_prefix"."listattr_$att[1] (name,listorder) values(\"$uservalue\",$highest)");
                  Warn("Value $uservalue added to attribute $att[2]");
                  $user_att_value = Sql_Insert_Id();
                } else {
                  $d = Sql_Fetch_Row($val);
                  $user_att_value = $d[0];
                }
                break;
              case "checkbox":
                if ($uservalue && $uservalue != "off")
                  $user_att_value = "on";
                else
                  $user_att_value = "off";
                break;
              default:
                $user_att_value = $uservalue;
                break;
            }

            Sql_query(sprintf('replace into %s (attributeid,userid,value) values("%s","%s","%s")',
              $tables["user_attribute"],$attribute_index,$userid,$user_att_value));
          }
        }
        $current_data = Sql_Fetch_Array_Query(sprintf('select * from %s where id = %d',$tables["user"],$userid));
        $current_data = array_merge($current_data,getUserAttributeValues('',$userid));
        foreach ($current_data as $key => $val) {
          if (!is_numeric($key))
          if ($old_data[$key] != $val && $old_data[$key] && $key != "password" && $key != "modified") {
            $information_changed = 1;
            $history_entry .= "$key = $val\n*changed* from $old_data[$key]\n";
          }
        }
        if (!$information_changed) {
          $history_entry .= "\nNo user details changed";
        }
      	addUserHistory($user["systemvalues"]["email"],"Import by ".adminName(),$history_entry);
      }

      #add this user to the lists identified
      if (is_array($_SESSION["lists"])) {
        reset($_SESSION["lists"]);
        $addition = 0;
        $listoflists = "";
        while (list($key,$listid) = each($_SESSION["lists"])) {
          $query = "replace INTO ".$tables["listuser"]." (userid,listid,entered) values($userid,$listid,now())";
          $result = Sql_query($query,1);
          # if the affected rows is 2, the user was already subscribed
          $addition = $addition || Sql_Affected_Rows() == 1;
          $listoflists .= "  * ".$_SESSION["listname"][$key]."\n";
        }
        if ($addition)
          $count["list_add"]++;
        if (!TEST && $_SESSION["notify"] == "yes" && $addition) {
          $subscribemessage = ereg_replace('\[LISTS\]', $listoflists, getUserConfig("subscribemessage",$userid));
          sendMail($user["systemvalues"]["email"], getConfig("subscribesubject"), $subscribemessage,system_messageheaders(),$envelope);
        }
      }
      if (!is_array($_SESSION["groups"])) {
        $groups = array();
      } else {
        $groups = $_SESSION["groups"];
      }
      if (isset($everyone_groupid) && !in_array($everyone_groupid,$groups)) {
        array_push($groups,$everyone_groupid);
      }
      if (is_array($groups)) {
        #add this user to the groups identified
        reset($groups);
        $groupaddition = 0;
        while (list($key,$groupid) = each($groups)) {
          if ($groupid) {
            $query = "replace INTO user_group (userid,groupid) values($userid,$groupid)";
            $result = Sql_query($query);
            # if the affected rows is 2, the user was already subscribed
            $groupaddition = $groupaddition || Sql_Affected_Rows() == 1;
          }
        }
        if ($groupaddition)
          $count["group_add"]++;
      }
		} // end else
    if ($_SESSION["test_import"] && $c > 50) break;
  }

	if (!$_SESSION["test_import"]) {
    print '<script language="Javascript" type="text/javascript"> finish(); </script>';
    # be gramatically correct :-)
    $displists = ($num_lists == 1) ? "list": "lists";
    $dispemail = ($count["email_add"] == 1) ? "new email was ": "new emails were ";
    $dispemail2 = ($count["list_add"] == 1) ? "email was ":"emails were ";

    $report = "";
    if(!$some && !$count["list_add"]) {
      $report .= "<br>All the emails already exist in the database and are member of the $displists.";
    } else {
      $report .= "<br/>".$count["email_add"]." $dispemail succesfully imported to the database and added to $num_lists $displists.<br>".$count["list_add"]." $dispemail2 subscribed to the $displists";
      if ($count["exist"])
        $report .= "<br/>".$count["exist"]." emails already existed in the database";
    }
    if ($count["invalid_email"]) {
      $report .= "<br/>".$count["invalid_email"] ." Invalid Emails found.";
      if (!$_SESSION["omit_invalid"])
        $report .= " These records were added, but the email has been made up from ".$_SESSION["assign_invalid"];
      else
        $report .= " These records were deleted. Check your source and reimport the data. Duplicates will be identified.";
    }
    if ($_SESSION["overwrite"] == "yes") {
      $report .= "<br/>User data was updated for ".$count["dataupdate"]." users";
    }
    $report .= sprintf('<br/>%d users were matched by foreign key, %d by email',$count["fkeymatch"],$count["emailmatch"]);
    print $report;
    sendMail (getConfig("admin_address"),"phplist Import Results",$report);
    clearImport();
  } else {
	  print 'Test output<br/>If the output looks ok, click '.PageLink2($_GET["page"]."&amp;confirm=yes","Confirm Import").' to submit for real<br/><br/>';
  }

	print '<p>'.PageLink2($_GET["page"],"Import some more emails");

  return;
}
?>


<ul>
<?=formStart('enctype="multipart/form-data" name="import"');?>
<?php
if ($GLOBALS["require_login"] && !isSuperUser()) {
  $access = accessLevel("import2");
  if ($access == "owner")
    $subselect = " where owner = ".$_SESSION["logindetails"]["id"];
  elseif ($access == "all")
    $subselect = "";
  elseif ($access == "none")
    $subselect = " where id = 0";
}

if (Sql_Table_Exists($tables["list"])) {
  $result = Sql_query("SELECT id,name FROM ".$tables["list"]." $subselect ORDER BY listorder");
  $c=0;
  if (Sql_Affected_Rows() == 1) {
    $row = Sql_fetch_array($result);
    printf('<input type=hidden name="listname[%d]" value="%s"><input type=hidden name="lists[%d]" value="%d">Adding users to list <b>%s</b>',$c,stripslashes($row["name"]),$c,$row["id"],stripslashes($row["name"]));
  } else {
    print '<p>Select the lists to add the emails to</p>';
    while ($row = Sql_fetch_array($result)) {
      printf('<li><input type=hidden name="listname[%d]" value="%s"><input type=checkbox name="lists[%d]" value="%d">%s',$c,stripslashes($row["name"]),$c,$row["id"],stripslashes($row["name"]));
      $some = 1;$c++;
    }

    if (!$some)
      echo 'No lists available, '.PageLink2("editlist","Add a list");
  }
}

if (Sql_Table_Exists("groups")) {
  $result = Sql_query("SELECT id,name FROM groups ORDER BY listorder");
  $c=0;
  if (Sql_Affected_Rows() == 1) {
    $row = Sql_fetch_array($result);
    printf('<p><input type=hidden name="groupname[%d]" value="%s"><input type=hidden name="groups[%d]" value="%d">Adding users to group <b>%s</b></p>',$c,$row["name"],$c,$row["id"],$row["name"]);;
  } else {
    print '<p>Select the groups to add the users to</p>';
    while ($row = Sql_fetch_array($result)) {
      if ($row["id"] == $everyone_groupid) {
        printf('<li><input type=hidden name="groupname[%d]" value="%s"><input type=hidden name="groups[%d]" value="%d"><b>%s</b> - automatically added',$c,$row["name"],$c,$row["id"],$row["name"]);;
      } else {
        printf('<li><input type=hidden name="groupname[%d]" value="%s"><input type=checkbox name="groups[%d]" value="%d">%s',$c,$row["name"],$c,$row["id"],$row["name"]);;
      }
      $some = 1;$c++;
    }
  }
}

?>

</ul>

<table border="1">
<tr><td colspan=2><p>
The file you upload will need to have the attributes of the records on the first line.
Make sure that the email column is called "email" and not something like "e-mail" or
"Email Address".
Case is not important.
</p>
If you have a column called "Foreign Key", this will be used for synchronisation between an
external database and the PHPlist database. The foreignkey will take precedence when matching
an existing user. This will slow down the import process. If you use this, it is allowed to have
records without email, but an "Invalid Email" will be created instead. You can then do
a search on "invalid email" to find those records. Maximum size of a foreign key is 100.
<br/><br/>
<b>Warning</b>: the file needs to be plain text. Do not upload binary files like a Word Document.
<br/>
</td></tr>
<tr><td>File containing emails:<br/>
</td><td><input type="file" name="import_file">
<br/>The following limits are set by your server:<br/>
Maximum size of a total data sent to server: <b><?=ini_get("post_max_size")?></b><br/>
Maximum size of each individual file: <b><?=ini_get("upload_max_filesize")?></b>
<br/>PHPlist will not process files larger that 1Mb
</td></tr>
<tr><td>Field Delimiter:</td><td><input type="text" name="import_field_delimiter" size=5> (default is TAB)</td></tr>
<tr><td>Record Delimiter:</td><td><input type="text" name="import_record_delimiter" size=5> (default is line break)</td></tr>
<tr><td colspan=2>If you check "Test Output", you will get the list of parsed emails on screen, and the database will not be filled with the information. This is useful to find out whether the format of your file is correct. It will only show the first 50 records.</td></tr>
<tr><td>Test output:</td><td><input type="checkbox" name="import_test" value="yes"></td></tr>
<tr><td colspan=2>If you check "Show Warnings", you will get warnings for invalid records. Warnings will only be shown if you check "Test Output". They will be ignored when actually importing. </td></tr>
<tr><td>Show Warnings:</td><td><input type="checkbox" name="show_warnings" value="yes"></td></tr>
<tr><td colspan=2>If you check "Omit Invalid", invalid records will not be added. Invalid records are records without an email. Any other attributes will be added automatically, ie if the country of a record is not found, it will be added to the list of countries.</td></tr>
<tr><td>Omit Invalid:</td><td><input type="checkbox" name="omit_invalid" value="yes"></td></tr>
<tr><td colspan=2>Assign Invalid will be used to create an email for users with an invalid email address.
You can use values between [ and ] to make up a value for the email. For example if your import file contains a column "First Name" and one called "Last Name", you can use
"[first name] [last name]" to construct a new value for the email for this user containing their first name and last name.
The value [number] can be used to insert the sequence number for importing.
</td></tr>
<tr><td>Assign Invalid:</td><td><input type="text" name="assign_invalid" value="<?=$GLOBALS["assign_invalid_default"]?>"></td></tr>
<tr><td colspan=2>If you check "Overwrite Existing", information about a user in the database will be replaced by the imported information. Users are matched by email or foreign key.</td></tr>
<tr><td>Overwrite Existing:</td><td><input type="checkbox" name="overwrite" value="yes"></td></tr>
<tr><td colspan=2>If you check "Retain Old User Email", a conflict of two emails being the same will keep the old one and add "duplicate" to the new one. If you don't check it, the old one will get "duplicate" and the new one will take precedence.</td></tr>
<tr><td>Retain Old User Email:</td><td><input type="checkbox" name="retainold" value="yes"></td></tr>
<tr><td colspan=2>If you choose "send notification email" the users you are adding will be sent the request for confirmation of subscription to which they will have to reply. This is recommended, because it will identify invalid emails.</td></tr>
<tr><td>Send&nbsp;Notification&nbsp;email&nbsp;<input type="radio" name="notify" value="yes"></td><td>Make confirmed immediately&nbsp;<input type="radio" name="notify" value="no"></td></tr>

<tr><td><input type="submit" name="import" value="Import"></td><td>&nbsp;</td></tr>
</table>
</p>
