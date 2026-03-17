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
$link = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($link->connect_error) {
    // Stop execution if connection fails
    // In production, log this error instead of displaying it
    die('Could not connect: ' . $link->connect_error);
}

// Fetch experiment names for a given cluster, removing duplicates and extracting base name
function get_experiments($cluster, $link) {
    $sql = "SELECT Name FROM quota_all WHERE type='FILESET' and Name !='root' and Name !='admin' and cluster=?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param('s', $cluster);
    $stmt->execute();
    $result = $stmt->get_result();
    $experiments = array();
    while ($row = $result->fetch_assoc()) {
        // If experiment name contains '-', extract base experiment name
        if (strpos($row['Name'], '-')) {
            array_push($experiments, strstr($row['Name'], "-", true));
        } else {
            array_push($experiments, $row['Name']);
        }
    }
    // Remove duplicate experiment names
    $experiments = array_unique($experiments);
    return $experiments;
}

// Aggregate quota and usage for a given experiment
function get_experiment($experiment, $cluster, $link) {
    $sql = "SELECT Name, blocks, quota FROM quota_all WHERE Name like CONCAT(?, '%') and type='FILESET' and Name !='root' and Name !='admin' and cluster=?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param('ss', $experiment, $cluster);
    $stmt->execute();
    $result = $stmt->get_result();
    $space = 0;
    $used = 0;
    $free = 0;
    while ($row = $result->fetch_assoc()) {
        $space += $row['quota'];
        $used += $row['blocks'];
    }
    $free += $space - $used;
    // Convert bytes to TB and round
    $space = round(($space / 1024 / 1024 / 1024), 2);
    $used = round(($used / 1024 / 1024 / 1024), 2);
    $free = round(($space - $used), 2);
    $temp = array("Experiment" => $experiment, "Space" => $space, "Used" => $used, "Free" => $free);
    return ($temp);
}

$gpfs_space = array("gpfs" => 1529275596800, "gpfs02" => 1125182767104, "gpfs03" => 917565358080, "gpfs04" => 1125182767104);
$table = array();
$table['cols'] = array(
    array('id' => '', 'label' => 'Experiment', 'type' => 'string'),
    array('id' => '', 'label' => 'Size TB', 'type' => 'number'),
    array('id' => '', 'label' => 'Used TB', 'type' => 'number'),
    array('id' => '', 'label' => 'Free TB', 'type' => 'number')
);
$rows = array();
$total_space = 0;
$total_used = 0;
$total_free = 0;
$total_gpfs = round(($gpfs_space[$cluster] / 1024 / 1024 / 1024), 2);
$total_unallocated = 0;

$experiments = get_experiments($cluster, $link);

// Build table rows for each experiment and calculate totals
foreach ($experiments as $experiment) {
    $temp = array();
    $value = get_experiment($experiment, $cluster, $link);
    $temp[] = array('v' => (string) $value['Experiment']);
    $temp[] = array('v' => $value['Space']);
    $temp[] = array('v' => $value['Used']);
    $temp[] = array('v' => $value['Free']);
    $rows[] = array('c' => $temp);
    $total_space += $value['Space'];
    $total_used += $value['Used'];
    $total_free += $value['Free'];
}

// Add total allocated row
$temp = array();
$temp[] = array('v' => "Total Allocated");
$temp[] = array('v' => $total_space);
$temp[] = array('v' => $total_used);
$temp[] = array('v' => $total_free);
$rows[] = array('c' => $temp);

// Add unallocated row
$total_unallocated = $total_gpfs - $total_space;
$temp = array();
$real_free = $total_free + $total_unallocated;
$temp[] = array('v' => "Unallocated");
$temp[] = array('v' => $total_unallocated);
$temp[] = array('v' => '');
$temp[] = array('v' => $real_free);
$rows[] = array('c' => $temp);

$table['rows'] = $rows;

// Close DB connection and output JSON
$link->close();

// Output the result as JSON for frontend consumption
echo json_encode($table);
?>
