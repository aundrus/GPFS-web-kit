<?php

// Sanitize and validate input parameters
$cluster = isset($_GET["fileset"]) ? preg_replace('/[^\w\-]/', '', $_GET["fileset"]) : '';

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

// Fetch experiment names for a given cluster, removing duplicates and extracting base name
function get_experiments($cluster, $mysqli) {
    $sql = "SELECT Name FROM quota_all WHERE type='FILESET' and Name !='root' and cluster=?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('s', $cluster);
    $stmt->execute();
    $result = $stmt->get_result();
    $experiments = array();
    while ($row = $result->fetch_assoc()) {
        // If experiment name contains '-', extract base experiment name
        if (strpos($row['Name'], '-')) {
            $experiments[] = strstr($row['Name'], "-", true);
        } else {
            $experiments[] = $row['Name'];
        }
    }
    $stmt->close();
    // Remove duplicate experiment names
    $experiments = array_unique($experiments);
    return $experiments;
}

// Aggregate quota and usage for a given experiment
function get_experiment($experiment, $cluster, $mysqli) {
    $sql = "SELECT Name,blocks,quota FROM quota_all WHERE Name like ? and type='FILESET' and Name !='root' and cluster=?";
    $stmt = $mysqli->prepare($sql);
    $like_experiment = $experiment . '%';
    $stmt->bind_param('ss', $like_experiment, $cluster);
    $stmt->execute();
    $result = $stmt->get_result();
    $space = 0;
    $used = 0;
    while ($row = $result->fetch_assoc()) {
        $space += $row['quota'];
        $used += $row['blocks'];
    }
    $stmt->close();
    // Convert bytes to TB and round
    $space = round(($space/1024/1024/1024),2);
    $used = round(($used/1024/1024/1024),2);
    $free = round(($space-$used),2);
    $temp = array("Experiment" => $experiment, "Space" => $space, "Used" => $used, "Free" => $free);
    return $temp;
}

$gpfs_space = array("gpfs" => 1529275596800, "gpfs02" => 1125182767104, "gpfs03" => 917565358080, "gpfs04" => 1125182767104);
$table = array();
$table['cols'] = array(
    array('id'=>'','label'=>'Experiment','type'=>'string'),
    array('id'=>'','label'=>'Size TB','type'=>'number'),
);
$rows = array();
$total_space = 0;
$total_used = 0;
$total_free = 0;
$total_gpfs = round(($gpfs_space[$cluster]/1024/1024/1024),2);

$experiments = get_experiments($cluster, $mysqli);

// Build table rows for each experiment and calculate totals
foreach ($experiments as $experiment) {
    $temp = array();
    $value = get_experiment($experiment, $cluster, $mysqli);
    $temp[] = array('v' => (string) $value['Experiment']);
    $temp[] = array('v' => $value['Space']);
    $rows[] = array('c' => $temp);
    $total_space += $value['Space'];
}

// Add unallocated space row
$total_unallocated = max($total_gpfs - $total_space, 0); // Ensure Unallocated is never negative
$temp = array();
$temp[] = array('v' => "Unallocated");
$temp[] = array('v' => $total_unallocated);
$rows[] = array('c' => $temp);

$table['rows'] = $rows;

// Close DB connection and output JSON
$mysqli->close();

// Output the result as JSON for frontend consumption
echo json_encode($table);
?>