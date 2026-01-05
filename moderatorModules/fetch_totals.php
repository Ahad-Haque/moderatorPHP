<?php
// moderatorModules\fetch_totals.php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['loggedin'])) {
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

// Connect to database
require_once('../wp-load.php');
$conn = new mysqli('localhost', 'shapnach_wp2026', 'shapnach_wp2026', 'shapnach_moderator09');

if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

function calculateRangeTotals($conn, $start_date, $end_date) {
    $total_share = 0;
    $total_deposit = 0;
    $total_service_charge = 0;

    // Get all members
    $member_sql = "SELECT * FROM memberBasicDetails";
    $member_result = $conn->query($member_sql);

    while($member = $member_result->fetch_assoc()) {
        $table_name = str_replace('-', '_', $member['id_no']);
        
        // Get totals for this member within date range
        $detail_sql = "SELECT 
            SUM(Share) as member_share,
            SUM(Deposit) as member_deposit
            FROM `$table_name`
            WHERE Submission_Date BETWEEN ? AND ?";
            
        $stmt = $conn->prepare($detail_sql);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $detail_result = $stmt->get_result();
        $details = $detail_result->fetch_assoc();
        
        // Add to totals
        $total_share += floatval($details['member_share']);
        $total_deposit += floatval($details['member_deposit']);
        
        // Calculate service charge if applicable
        if($member['resign_date']) {
            $admit_date = new DateTime($member['admit_date']);
            $resign_date = new DateTime($member['resign_date']);
            $interval = $admit_date->diff($resign_date);
            
            if($interval->y < 5) {
                $member_total = floatval($details['member_share']) + floatval($details['member_deposit']);
                $total_service_charge += ($member_total * 0.2);
            }
        }
    }

    return [
        'total_share' => $total_share,
        'total_deposit' => $total_deposit,
        'total_service_charge' => $total_service_charge
    ];
}

// Handle the request
if(isset($_POST['start_date']) && isset($_POST['end_date'])) {
    try {
        $totals = calculateRangeTotals($conn, $_POST['start_date'], $_POST['end_date']);
        echo json_encode($totals);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Missing date parameters']);
}

$conn->close();
?>