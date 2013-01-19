<?php
define('FS_ROOT', realpath(dirname(__FILE__)));
require_once(FS_ROOT."/../../www/config.php");
require_once(FS_ROOT."/../../www/lib/framework/db.php");
require_once(FS_ROOT."/../../www/lib/releases.php");
require_once(FS_ROOT."/../../www/lib/sphinx.php");
require_once(FS_ROOT."/../../www/lib/backfill.php");

function do_releases() {
	$releases = new Releases;
	$sphinx = new Sphinx();
	$releases->processReleases();
	$sphinx->update();
}

function get_count($db) {
	$sql = "select COUNT(*) AS ToDo from releases r left join category c on c.ID = r.categoryID where (r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0)";
	$res= $db->query($sql);
	return $res[0]['ToDo'];
}

//this is the maximum days the script will go up to. change it if you desire
$backfill_target = 365;

//see what the oldest group is
$db = new Db;
$sql = "SELECT backfill_target FROM groups WHERE active=1 ORDER BY backfill_target DESC LIMIT 1";
$res= $db->query($sql);
$highest = $res[0]['backfill_target'];

//increase the backfill days by 1 for all active groups less that the oldest
//this will allow groups to catch up so retention is the same for the whole site
$sql = "UPDATE groups SET backfill_target=backfill_target+1 WHERE active=1 AND backfill_target < '$backfill_target' AND backfill_target <= '$highest'";
$res= $db->query($sql);

//get binaries
$backfill = new Backfill();
$backfill->backfillAllGroups();

//process releases
$proc = do_releases();

//if there are still releases to process run again
$count = get_count($db);
while ($count > 0) { 
	do_releases();
	$count = get_count($db);
}
?>
