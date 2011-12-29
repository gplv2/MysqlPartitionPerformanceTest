<?php

require_once('cliargs.php');

include_once("class.exceptions.php");
include_once("class.timing.php");
include_once("class.db.php");

include_once('table_defs.php');

class MysqlException extends CustomException {};
class ProgramException extends CustomException {};

/* Enable logging */
$verbose=3;

/* DB config data */
$conf['host']="10.10.10.184:3330";
$conf['user']="backup";
$conf['password']="restore";
// $conf['default_db']="test_db";
$conf['default_db']="test_db_drop";

$cliargs = array(
	'dryrun' => array(
		'short' => 'n',
		'type' => 'switch',
		'description' => "\t\tThis is a dryrun flag, no queries will be executed, just printed to screen.\n"
	),
	'all' => array(
		'short' => 'a',
		'type' => 'switch',
		'description' => "\tDo it all, Create a test DB, create test tables, insert data, perform select timings, drop table, drop test db\n"
	),
);	


/* command line errors are thrown hereafter */
$options = cliargs_get_options($cliargs);
$conf['verbose']=$verbose;

if (empty($options['dryrun'])) {
   $dry_run = 0;
   $conf['dry_run']=0;
} else {
   $dry_run = $options['dryrun'];
   $conf['dry_run']=1;
}

if (empty($options['all'])) {
   $all_actions = 0 ;
} else {
   $all_actions  = $options['all'];
}

/* unknown option bucket */
$unnamed          = $options['unnamed'];

/* check for arguments we don't know about indicating something is perhaps not ok */
if (empty($unnamed)) {
    logtrace(5, "No unnamed arguments found");
} else {
    logtrace(0, "Unnamed arguments: ".print_r($unnamed, true));
    exit;
}
/* done with command line stuff */


/* New feature here
if (!empty($new_feature)) {
   logtrace(0, "Not implemented yet");
   exit;
}
*/

/* Limit stuff to these actions Eg. One of them needs to be set to know what to do now */
if (empty($all_actions) and empty($dry_run) ) {
   logtrace(0, "\n\nAllright  Captain!!! All diagnostics and selfchecks passed. Shields are 100% and the engine is at warp 11, Now what exactly do you want me to do now ???\nIt seems like we have some options to consider:\n");
   cliargs_print_usage_and_exit($cliargs);
}

/* Things to do for 'all' argument */
if (!empty($all_actions)) {
   logtrace(1, "Doing all actions.");
   /* Implicit options set */
   $provision=1;
   $insert=1;
   $select=1;
   $drop=1;
}

/* We are good to go, so lets start */
$DB = new DBM($conf);

/* Set the DB connection up */
try {
   logtrace(1,"Connecting to DB server ... ");
   $DB->connect();
    
	if(!$DB->getLink()) {
		throw new MysqlException('Cannot connect to database.');
	} else {
	   logtrace(0,"Connected with connection ID : " . $DB->getLink());
		$success=1;
	}
}
/* Catch those nasty exceptions */
catch (MysqlException $e) {
	$success=0;
	logtrace(0,"Error with connection ID : " . $DB->getLink());
  	logtrace(0,"Could not connect to server: " . $conf['host']);
  	logtrace(0,sprintf("DB error %d: %s",$DB->errno(),$DB->error()));
  	logtrace(0,"Caught MysqlException ('$e')");
}

catch (Exception $e) {
	$success=0;
	logtrace(0, "Caught Exception ('$e')");
}
/* It's bullshit that a try block HAS to be followed by a catch block every freaking time if you want them to work */

/* Selecting the default DB */
try {
   /* Selecting  DB : */
   if($success==1) {
      $selected=0; 
      while (!$selected) {
         logtrace(1, "Selecting default database " . $conf['default_db']);
         if (!$DB->setDB()) {
            logtrace(0,sprintf("DB error %d: %s",$DB->errno(),$DB->error()));
            // 2011-12-23 18:25:39 [17839]:[0]error - DB error 1049: Unknown database 'test_db_drop'
            if ($DB->errno()== 1049) {
               /* Create the test DB */   
               logtrace(1, "Creating default database " . $conf['default_db']);
               $DB->exeQry(sprintf($events_db_create,$conf['default_db']));
            } else {
               throw new MysqlException('Cannot connect/create database.');
               $success=0;
               break;
            }
         } else {
            $selected=1;
            $success=1;
         }
      }
   } else {
      logtrace(0,"This connection failed.");
   }
}

/* Catch those nasty exceptions */
catch (MysqlException $e) {
	$success=0;
	logtrace(0,"Error with connection ID : " . $DB->getLink());
  	logtrace(0,"Could not connect to server: " . $conf['host']);
  	logtrace(0,sprintf("DB error %d: %s",$DB->errno(),$DB->error()));
  	logtrace(0,"Caught MysqlException ('$e')");
}

catch (Exception $e) {
	$success=0;
	logtrace(0, "Caught Exception ('$e')");
}
/* It's bullshit that a try block HAS to be followed by a catch block every freaking time if you want them to work */

if(!$success) {
   logtrace(0,"Bailout.");
   exit;
}

/* Modding the default DB */
try {
   /* Create the partitioned tables */
   $table_partitioned="event_part";
   $table_unpartitioned="event_simple";
   $table_engine="MyIsam";
   $created_table=0;

   while(!$created_table) {
      logtrace(1, sprintf("Creating table %s (%s)",$table_partitioned,$table_engine));
      if (!$DB->exeQry(sprintf($events_partitioned ,$table_partitioned,$table_engine))) {
         if ($DB->errno()== 1050) {
            logtrace(0,sprintf("DB error %d: %s",$DB->errno(),$DB->error()));
            logtrace(0, sprintf("Table exists, dropping table %s",$table_partitioned,$table_engine));
            if (!$DB->exeQry(sprintf($events_partitioned_drop ,$table_partitioned,$table_engine))) {
               logtrace(0,sprintf("DB error %d: %s",$DB->errno(),$DB->error()));
               throw new MysqlException('Cannot drop table');
               exit;
            }
         } else {
            logtrace(0,sprintf("DB error %d: %s",$DB->errno(),$DB->error()));
            throw new MysqlException('Cannot create table');
            exit;
         }
      } else {
         logtrace(1, sprintf("Table %s created (%s) ",$table_partitioned,$table_engine));
         $created_table=1;
      }
   }

   /* Create the simple tables */
   $created_table=0;
   while(!$created_table) {
      logtrace(1, sprintf("Creating table %s (%s)",$table_unpartitioned,$table_engine));
      if (!$DB->exeQry(sprintf($events_unpartitioned ,$table_unpartitioned,$table_engine))) {
         if ($DB->errno()== 1050) {
            logtrace(0,sprintf("DB error %d: %s",$DB->errno(),$DB->error()));
            logtrace(0, sprintf("Table exists, dropping table %s",$table_unpartitioned,$table_engine));
            if (!$DB->exeQry(sprintf($events_unpartitioned_drop ,$table_unpartitioned,$table_engine))) {
               logtrace(0,sprintf("DB error %d: %s",$DB->errno(),$DB->error()));
               throw new MysqlException('Cannot drop table');
               exit;
            }
         } else {
            logtrace(0,sprintf("DB error %d: %s",$DB->errno(),$DB->error()));
            throw new MysqlException('Cannot create table');
            exit;
         }
      } else {
         logtrace(1, sprintf("Table %s created (%s) ",$table_unpartitioned,$table_engine));
         $created_table=1;
      }
   }

   logtrace(1, "Dropping default database " . $conf['default_db']);
   // $DB->exeQry(sprintf($events_db_drop,$conf['default_db']));
   exit;
}

/* Catch those nasty exceptions */
catch (MysqlException $e) {
	$success=0;
	logtrace(0,"Error with connection ID : " . $DB->getLink());
  	logtrace(0,"Could not connect to server: " . $conf['host']);
  	logtrace(0,sprintf("DB error %d: %s",$DB->errno(),$DB->error()));
  	logtrace(0,"Caught MysqlException ('$e')");
}

catch (Exception $e) {
	$success=0;
	logtrace(0, "Caught Exception ('$e')");
}
/* It's bullshit that a try block HAS to be followed by a catch block every freaking time if you want them to work */

/* Some helper functions  */
function preg_test($regex) {
   try {
      // Test regexes and throw exceptions if they don't parse well
      if (sprintf("%s",@preg_match($regex,'')) == '') {
         $error = error_get_last();
         throw new ProgramException($error['message']);
      } else {
         return true;
      }
   }

   catch (ProgramException $e) {
      $success=0;
      logtrace(0, "Caught preg_problem ('$e')");
   }

   catch (Exception $e) {
	   logtrace(0, "Caught Exception ('$e')");
   }
}


/* Test for hex string (like RFID numbers ) */
function isHexadecimalString ( $str ) {
	if ( preg_match("/^[a-f0-9]{1,}$/is", $str) ) {
  		return true;
	} else {
		return false;
	}
}

/* A very simple log facility */
function logtrace($level,$msg) {
  /* Globals suck, too lazy to change this */
  global $verbose;
  $DateTime=@date('Y-m-d H:i:s', time());

  if ( $level <= $verbose ) {
      $mylvl=NULL;
      switch($level) {
          case 0:
            $mylvl ="error";
            break;
          case 1:
            $mylvl ="core ";
            break;
          case 2:
            $mylvl ="info ";
            break;
          case 3:
            $mylvl ="notic";
            break;
          case 4:
            $mylvl ="verbs";
            break;
          case 5:
            $mylvl ="dtail";
            break;
          default :
            $mylvl ="exec ";
            break;
        }
      // "posix_getpid()=" . posix_getpid() . ", posix_getppid()=" . posix_getppid();
      $content = $DateTime. " [" .  posix_getpid() ."]:[" . $level . "]" . $mylvl . " - " . $msg . "\n";
      echo $content;
    }
}

/* good old perl stuff */
function mychomp(&$string) {
   // Perl is dearly missed
   if (is_array($string)) {
      foreach($string as $i => $val) {
         $endchar = chomp($string[$i]);
      }
   } else {
      $endchar = substr("$string", strlen("$string") - 1, 1);
      $string = substr("$string", 0, -1);
   }
   return $endchar;
}

?>
