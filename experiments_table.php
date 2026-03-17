<?php

// Sanitize and validate input parameters
$cluster = isset($_GET["cluster"]) ? preg_replace('/[^\w\-]/', '', $_GET["cluster"]) : '';
$fileset = isset($_GET["fileset"]) ? preg_replace('/[^\w\-]/', '', $_GET["fileset"]) : '';

// Load DB credentials from environment variables for security
// These should be set in your web server environment and not checked into version control
$db_host = getenv('GPFS_DB_HOST'); // Database host
$db_user = getenv('GPFS_DB_USER'); // Database user
$db_pass = getenv('GPFS_DB_PASS'); // Database password
$db_name = getenv('GPFS_DB_NAME'); // Database name

// Establish MySQLi connection for PHP 8+ compatibility
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
    // Stop execution if connection fails
    // In production, log this error instead of displaying it
    die('Could not connect: ' . $mysqli->connect_error);
}

$table = array();
$table['cols'] = array(
    array('id'=>'','label'=>'Experiment','type'=>'string'),
    array('id'=>'','label'=>'Size TB','type'=>'number'),
    array('id'=>'','label'=>'Used TB','type'=>'number'),
    array('id'=>'','label'=>'Free TB','type'=>'number')
);
$rows = array();
$total_space = 0;
$total_used = 0;
$total_free = 0;

$sql = "SELECT Name,blocks,quota FROM quota_all WHERE type='FILESET' and Name LIKE ? and cluster=? order by quota DESC";
$stmt = $mysqli->prepare($sql);
$like_fileset = $fileset . '%';
$stmt->bind_param('ss', $like_fileset, $cluster);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $name = $row['Name'];
    $space = round(($row['quota']/1024/1024/1024),2);
    $used = round(($row['blocks']/1024/1024/1024),2);
    $free = round(($space-$used),2);
    $temp = array();
    $temp[] = array('v' => (string) $name);
    $temp[] = array('v' => $space);
    $temp[] = array('v' => $used);
    $temp[] = array('v' => $free);
    $rows[] = array('c' => $temp);
    $total_space += $space;
    $total_used += $used;
    $total_free += $free;
}
$temp = array();
$temp[] = array('v' => "Total");
$temp[] = array('v' => $total_space);
$temp[] = array('v' => $total_used);
$temp[] = array('v' => $total_free);
$rows[] = array('c' => $temp);

$table['rows'] = $rows;

$stmt->close();
$mysqli->close();

echo json_encode($table);
?>