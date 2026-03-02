<?php
header("Content-Type: application/json");
session_start();

/* ===============================
   DATABASE CONNECTION
================================ */
$host = "localhost";
$user = "root";
$pass = "";
$db   = "inventory_system";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed"]));
}

/* ===============================
   GLOBALS
================================ */
if (!isset($_SESSION['ANOMALY_QUEUE'])) {
    $_SESSION['ANOMALY_QUEUE'] = [];
}

if (!isset($_SESSION['LAST_ANOMALY_TIME'])) {
    $_SESSION['LAST_ANOMALY_TIME'] = 0;
}

$COOLDOWN = 60; // seconds

/* ===============================
   SEND DATA TO PYTHON ML
================================ */
function call_python_ml($data) {

    $options = [
        'http' => [
            'header'  => "Content-type: application/json",
            'method'  => 'POST',
            'content' => json_encode($data),
            'timeout' => 5
        ]
    ];

    $context = stream_context_create($options);

    $result = @file_get_contents(
        "http://127.0.0.1:5000/predict",
        false,
        $context
    );

    if ($result === FALSE) {
        return ["failure_prob" => 0.0, "thoughts" => "ML service unavailable"];
    }

    return json_decode($result, true);
}

/* ===============================
   ANALYZE SINGLE ASSET
================================ */
function analyze_asset($conn, $asset_row) {

    $asset_id = $asset_row['asset_id'];
    $date_added = $asset_row['date_added'];

    $age_days = 0;
    if ($date_added) {
        $age_days = floor((time() - strtotime($date_added)) / 86400);
    }

    // Fetch damaged components
    $stmt = $conn->prepare("
        SELECT component FROM reported_items
        WHERE asset_id = ? AND status = 'Damaged'
    ");
    $stmt->bind_param("s", $asset_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $components_dict = [];
    $d_count = 0;

    while ($row = $result->fetch_assoc()) {
        $components = explode(",", $row['component']);
        foreach ($components as $c) {
            $c = trim($c);
            if ($c !== "") {
                $key = strtolower(str_replace(" ", "_", $c)) . "_failures";
                $components_dict[$key] = ($components_dict[$key] ?? 0) + 1;
                $d_count++;
            }
        }
    }

    // Prepare full feature set for Python
    $data = [
        "d_count" => $d_count,
        "age_days" => $age_days,
        "environment_score" => 5,
        "mishandling_score" => 0,
        "screen_failures" => $components_dict['screen_failures'] ?? 0,
        "battery_failures" => $components_dict['battery_failures'] ?? 0,
        "keyboard_failures" => $components_dict['keyboard_failures'] ?? 0,
        "motherboard_failures" => $components_dict['motherboard_failures'] ?? 0
    ];

    $ml_response = call_python_ml($data);

    return [
        "asset_id" => $asset_id,
        "db_id" => $asset_row['id'],
        "d_count" => $d_count,
        "failure_prob" => $ml_response['failure_prob'] ?? 0,
        "thoughts" => $ml_response['thoughts'] ?? ""
    ];
}

/* ===============================
   REFRESH ANOMALY QUEUE
================================ */
function refresh_anomaly_queue($conn) {

    $_SESSION['ANOMALY_QUEUE'] = [];

    $result = $conn->query("
        SELECT id, asset_id, date_added 
        FROM assets 
        WHERE deleted = 0
    ");

    while ($row = $result->fetch_assoc()) {

        $analysis = analyze_asset($conn, $row);

        if (
            $analysis['d_count'] >= 3 ||
            $analysis['failure_prob'] >= 0.8
        ) {
            $_SESSION['ANOMALY_QUEUE'][] = $analysis;
        }
    }
}

/* ===============================
   ROUTER
================================ */
$endpoint = $_GET['endpoint'] ?? '';

/* ===============================
   SCAN (Notification Bubble)
================================ */
if ($endpoint === "scan") {

    if (empty($_SESSION['ANOMALY_QUEUE'])) {
        refresh_anomaly_queue($conn);
    }

    $current_time = time();
    $response = [
        "messages" => [],
        "critical" => false,
        "anomaly" => null
    ];

    if (
        !empty($_SESSION['ANOMALY_QUEUE']) &&
        ($current_time - $_SESSION['LAST_ANOMALY_TIME']) >= $COOLDOWN
    ) {
        $anomaly = array_shift($_SESSION['ANOMALY_QUEUE']);
        $_SESSION['LAST_ANOMALY_TIME'] = $current_time;

        $response['critical'] = true;
        $response['anomaly'] = [
            "id" => $anomaly['db_id'],
            "asset_id" => $anomaly['asset_id'],
            "damage_count" => $anomaly['d_count'],
            "failure_prob" => $anomaly['failure_prob'],
            "summary" => "Asset has {$anomaly['d_count']} damage reports.",
            "thoughts" => $anomaly['thoughts']
        ];
    }

    echo json_encode($response);
    exit;
}

/* ===============================
   ALL ANOMALIES
================================ */
if ($endpoint === "all_anomalies") {

    if (empty($_SESSION['ANOMALY_QUEUE'])) {
        refresh_anomaly_queue($conn);
    }

    echo json_encode([
        "anomalies" => $_SESSION['ANOMALY_QUEUE'],
        "count" => count($_SESSION['ANOMALY_QUEUE'])
    ]);
    exit;
}

/* ===============================
   MANUAL FULL SCAN
================================ */
if ($endpoint === "scan_all") {

    refresh_anomaly_queue($conn);

    echo json_encode([
        "message" => "Full system scan completed.",
        "total_anomalies" => count($_SESSION['ANOMALY_QUEUE'])
    ]);
    exit;
}

echo json_encode(["error" => "Invalid endpoint"]);