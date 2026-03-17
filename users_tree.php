<?php
$xml_url = 'https://www.star.bnl.gov/public_pnb/service/?q=/service/quota/format:xml';

# Sanitize and validate input parameters
$fileset = isset($_GET["fileset"]) ? preg_replace('/[^\w\-]/', '', $_GET["fileset"]) : '';
$cluster = isset($_GET["cluster"]) ? preg_replace('/[^\w\-]/', '', $_GET["cluster"]) : '';

if ($fileset != 'star-pwg') {
    exit;
}

# Load DB credentials from environment variables for security
# These should be set in your web server environment and not checked into version control
$db_host = getenv('GPFS_DB_HOST'); # Database host
$db_user = getenv('GPFS_DB_USER'); # Database user
$db_pass = getenv('GPFS_DB_PASS'); # Database password
$db_name = getenv('GPFS_DB_NAME'); # Database name

# Establish MySQLi connection for PHP 8+ compatibility
$link = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($link->connect_error) {
    # Stop execution if connection fails
    # In production, log this error instead of displaying it
    die('Could not connect: ' . $link->connect_error);
}

$sql = "SELECT Name,blocks,quota,type FROM quota_all WHERE fileset=? and type='USR' and cluster=? and blocks>0 order by quota,blocks DESC";
$stmt = $link->prepare($sql);
$stmt->bind_param('ss', $fileset, $cluster);
$stmt->execute();
$result = $stmt->get_result();

$author_quota = 1600/1024;

$table = array();
$rows = array();

$table['cols'] = array(
    array('id' => '', 'label' => 'Group', 'type' => 'string'),
    array('id' => '', 'label' => 'Parent', 'type' => 'string'),
    array('id' => '', 'label' => 'Quota', 'type' => 'number'),
    array('id' => '', 'label' => 'Free', 'type' => 'number'),
);


#returns array of users that have quota defined
#
function get_db_users($cluster, $fileset, $link) {
    $db_users = array();
    $sql = "SELECT Name FROM quota_all WHERE fileset=? and type='USR' and blocks>0 and cluster=?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param('ss', $fileset, $cluster);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $db_users[] = $row['Name'];
    }
    $stmt->close();
    return $db_users;
}

#returns array of institutions/groups
#print_r(get_institutions($xml));
function get_institutions($xml){
    $group = array();
    foreach ($xml->children() as $institutions){
	array_push($group,(string)$institutions->attributes());
    }
    return $group;
}

#print get_num_authors($xml,'WSU'); returns int number of authors
function get_num_authors($xml,$group){
    $i=0;
    foreach ($xml->children() as $institutions){
	if ($group == $institutions->attributes()) {
	    foreach ($institutions->members->member as $key => $value){
		if ($value['isAuthor'] == "yes") {
		    $i++;
		}
	    }
	}
    }
    return $i;
}

#returns array of users with login
#print_r(get_group_users($xml,'WUT'));
function get_group_users($xml,$group){
    $users=array();
    foreach ($xml->children() as $institutions){
	if ($group == $institutions->attributes()) {
	    foreach ($institutions->members->member as $key => $value){
		if (! empty($value['login'])) {
		    array_push($users, (string)($value['login']));
		}
	    }
	}
    }
    return $users;
}

#returns array: (name,space,used,free)
function get_db_user($cluster, $fileset, $user, $link) {
    $db_user = array();
    $sql = "SELECT Name,blocks,quota,type FROM quota_all WHERE fileset=? and type='USR' and Name=? and cluster=? and blocks>0";
    $stmt = $link->prepare($sql);
    $stmt->bind_param('sss', $fileset, $user, $cluster);
    $stmt->execute();
    $result = $stmt->get_result();
    $name = $space = $used = $free = 0;
    while ($row = $result->fetch_assoc()) {
        $name = $row['Name'];
        $space = $row['quota'] / 1024 / 1024 / 1024;
        $space = round($space, 2);
        $used = $row['blocks'] / 1024 / 1024 / 1024;
        $used = round($used, 2);
        $free = $space - $used;
        $free = round($free, 2);
    }
    $stmt->close();
    $db_user = array($name, $space, $used, $free);
    return $db_user;
}

function get_filestet_quota($cluster, $fileset, $link) {
    $sql = "SELECT Name,quota FROM quota WHERE type='FILESET' and Name=? and cluster=? order by quota DESC";
    $stmt = $link->prepare($sql);
    $stmt->bind_param('ss', $fileset, $cluster);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_row();
    $stmt->close();
    return $row[1];
}

if ( $fileset == 'star-pwg' ) {
    if(!$xml = simplexml_load_file($xml_url)) {
	exit('Failed to open '.$xml_url);
    }
    $global_quota=0;
    $global_free=0;
    $global_num_authors=0;
    $db_users=get_db_users($cluster,$fileset,$link);
    $institutions = get_institutions($xml);
    // Loop through institutions and aggregate user quotas
    foreach ($institutions as $institution) {
	// Count authors and get group users
	$num_authors = get_num_authors($xml, $institution);
	$group_users = get_group_users($xml, $institution);
	// Find users present in both XML and DB
	$users_intersect = array_intersect($group_users, $db_users);
	$quota = 0;
	$free = 0;
	foreach ($users_intersect as $user) {
	    $temp = array();
	    // Get quota info for each user
	    $db_user = get_db_user($cluster, $fileset, $user, $link);
	    // Calculate free percentage for user
	    $free_percentage = ($db_user[1] > 0) ? round(($db_user[3] * 100) / $db_user[1], 2) : 0;
	    $temp[] = array('v' => (string) $user);
	    $temp[] = array('v' => (string) $institution);
	    $temp[] = array('v' => $db_user[1]);
	    $temp[] = array('v' => $free_percentage);
	    $rows[] = array('c' => $temp);
	    $quota += $db_user[1];
	    $free += $db_user[3];
	}
	// Add institution summary row
	$temp2 = array();
	$free_percentage = ($quota == 0) ? 0 : round(($free * 100) / $quota, 2);
	$temp2[] = array('v' => (string) $institution);
	$temp2[] = array('v' => 'Global');
	$temp2[] = array('v' => $quota);
	$temp2[] = array('v' => $free_percentage);
	$rows[] = array('c' => $temp2);
	$global_quota += $quota;
	$global_free += $free;
	$global_num_authors += $num_authors;
    }
    // Add global summary row
    $temp3 = array();
    $temp3[] = array('v' => 'Global');
    $temp3[] = array('v' => '');
    $temp3[] = array('v' => $global_quota);
    $temp3[] = array('v' => 'NA');
    $rows[] = array('c' => $temp3);
    $table['rows'] = $rows;
}

$stmt->close();
$link->close();

if ($fileset == 'star-pwg') {
    echo json_encode($table);
}
?>
