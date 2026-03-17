<?php
//
// jQuery File Tree PHP Connector
//
// Originally developed by Costin
// 1.00 - released (6 June 2015)
// Output a list of quotas
//
// Redesigned, Migrated to RH9/PHP8.2 and MySQLi by Alexander Undrus
// March 2026
//

// Decode the directory parameter from POST request
$dir = isset($_POST['dir']) ? urldecode($_POST['dir']) : '';
// Only allow safe characters (alphanumeric, slash, dash, underscore)
if (!preg_match('/^[\w\/-]*$/', $dir)) {
    http_response_code(400);
    die('Invalid directory parameter.');
}

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

// Fetch unique experiment names for a given cluster
function get_experiments($cluster, $mysqli) {
    $sql = "SELECT Name FROM quota_all WHERE type='FILESET' and Name !='root' and Name !='admin' and cluster=?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('s', $cluster);
    $stmt->execute();
    $result = $stmt->get_result();
    $experiments = array();
    while ($row = $result->fetch_assoc()) {
        // Extract experiment name before '-' if present
        if (strpos($row['Name'], '-')) {
            $experiments[] = strstr($row['Name'], "-", true);
        } else {
            $experiments[] = $row['Name'];
        }
    }
    $stmt->close();
    // Remove duplicates
    $experiments = array_unique($experiments);
    return $experiments;
}

// Fetch all experiment fileset names matching a prefix for a cluster
function get_experiment($cluster, $experiment, $mysqli) {
    $like = $experiment . '%';
    $sql = "SELECT Name FROM quota_all WHERE Name like ? and type='FILESET' and Name !='root' and Name !='admin' and cluster=? order by Name";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ss', $like, $cluster);
    $stmt->execute();
    $result = $stmt->get_result();
    $exp_names = array();
    while ($row = $result->fetch_assoc()) {
        $exp_names[] = $row['Name'];
    }
    $stmt->close();
    return $exp_names;
}

// Fetch all available cluster names
function get_cluster($mysqli) {
    $sql = "SELECT distinct(cluster) FROM quota_all order by cluster";
    $result = $mysqli->query($sql);
    $cluster_names = array();
    while ($row = $result->fetch_assoc()) {
        $cluster_names[] = $row['cluster'];
    }
    return $cluster_names;
}

// Get all clusters for root directory
$cluster_names = get_cluster($mysqli);

// Render directory tree based on depth of dir parameter
if (substr_count($dir, '/') == 1) {
    // Root: show clusters and 'all' option
    echo "<ul class=\"jqueryFileTree\" style=\"display: none;\">";
    foreach ($cluster_names as $cluster_name) {
        echo "<li class=\"directory collapsed\"><a href=\"#\" onclick=\"clearBoxes();drawTableGpfs('$cluster_name');drawColumnGpfs('$cluster_name');drawPieGpfs('$cluster_name');\" rel=\"../" . htmlentities($cluster_name) . "/\">" . htmlentities($cluster_name) . "</a></li>";
    }
    echo "<li class=\"directory collapsed\"><a href=\"#\" onclick=\"clearBoxes();drawTableAll();\" rel=\"../all/\">all</a></li>";
    echo "</ul>";
} elseif (substr_count($dir, '/') == 2) {
    // Cluster: show experiments
    $cluster = explode("/", $dir);
    $experiments = get_experiments($cluster[1], $mysqli);
    echo "<ul class=\"jqueryFileTree\" style=\"display: none;\">";
    foreach ($experiments as $experiment) {
        echo "<li class=\"directory collapsed\"><a href=\"#\" onclick=\"clearBoxes();drawTableExperiments('$cluster[1]','$experiment');drawColumnExperiments('$cluster[1]','$experiment');drawPieExperiments('$cluster[1]','$experiment');\" rel=\"../" . $cluster[1] . "/" . $experiment . "/\">" . htmlentities($experiment) . "</a></li>";
    }
    echo "</ul>";
} elseif (substr_count($dir, '/') == 3) {
    // Experiment: show experiment filesets
    $experiments = explode("/", $dir);
    $cluster = $experiments[1];
    $exp_names = get_experiment($cluster, $experiments[2], $mysqli);
    echo "<ul class=\"jqueryFileTree\" style=\"display: none;\">";
    foreach ($exp_names as $exp_name) {
        echo "<li class=\"directory collapsed\"><a href=\"#\" onclick=\"clearBoxes();drawTableUsers('$cluster','$exp_name');drawColumnUsers('$cluster','$exp_name');drawTree('$cluster','$exp_name');\" rel=\"../" . $cluster . "/" . $experiments[2] . "/" . $exp_name . "/\">" . htmlentities($exp_name) .  "</a></li>";
    }
    echo "</ul>";
}

// Close MySQL connection
$mysqli->close();
?>