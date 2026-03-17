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
$link = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($link->connect_error) {
    // Stop execution if connection fails
    // In production, log this error instead of displaying it
    die('Could not connect: ' . $link->connect_error);
}

$table = array();
$rows = array();

$table['cols'] = array(
    array('id' => '', 'label' => 'User', 'type' => 'string'),
    array('id' => '', 'label' => 'Used TB', 'type' => 'number'),
    array('id' => '', 'label' => 'Free TB', 'type' => 'number')
);

$sql = "SELECT Name,blocks,quota,type FROM quota_all WHERE fileset=? and type='USR' and cluster=? order by quota,blocks DESC";
$stmt = $link->prepare($sql);
$stmt->bind_param('ss', $fileset, $cluster);
$stmt->execute();
$result = $stmt->get_result();

// Build table rows for each user and calculate used/free space
while ($row = $result->fetch_assoc()) {
    $name = $row['Name'];
    $space = round(($row['quota'] / 1024 / 1024 / 1024), 2); // Convert quota to TB
    $used = round(($row['blocks'] / 1024 / 1024 / 1024), 2); // Convert used blocks to TB
    $free = round(($space - $used), 2); // Calculate free space
    $temp = array();
    if ($used > 0) {
        if ($space == 0) {
            $temp[] = array('v' => (string) $name);
            $temp[] = array('v' => $used);
            $temp[] = array('v' => 0);
            $rows[] = array('c' => $temp);
        } else {
            $temp[] = array('v' => (string) $name);
            $temp[] = array('v' => $used);
            $temp[] = array('v' => $free);
            $rows[] = array('c' => $temp);
        }
    }
}

$table['rows'] = $rows;

// Close DB connection and output JSON
$stmt->close();
$link->close();

echo json_encode($table);
?>
