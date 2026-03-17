<?php

// Sanitize and validate input parameters
$fileset = isset($_GET["fileset"]) ? preg_replace('/[^\w\-]/', '', $_GET["fileset"]) : '';
$cluster = isset($_GET["cluster"]) ? preg_replace('/[^\w\-]/', '', $_GET["cluster"]) : '';

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

$pie = array();
$rows = array();

$pie['cols'] = array(
    array('id'=>'','label'=>'Experiment','type'=>'string'),
    array('id'=>'','label'=>'Allocated size','type'=>'number'),
);

$sql = "SELECT Name,blocks,quota,type FROM quota_all WHERE Name like ? and type='FILESET' and cluster=? order by quota,blocks DESC";
$stmt = $mysqli->prepare($sql);
$like_fileset = $fileset . '%';
$stmt->bind_param('ss', $like_fileset, $cluster);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $name = $row['Name'];
    $space = round(($row['quota']/1024/1024/1024),2);
    $temp = array();
    $temp[] = array('v' => (string) $name);
    $temp[] = array('v' => $space);
    $rows[] = array('c' => $temp);
}

$pie['rows'] = $rows;

$stmt->close();
$mysqli->close();

echo json_encode($pie);
?>
