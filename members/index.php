<?php
/**
 * Member Portal - View Only + Change Password
 * Path: public_html/members/index.php
 */
session_start();

// 1. Database Connection
$conn = new mysqli('localhost', 'shapnach_wp2026', 'shapnach_wp2026', 'shapnach_moderator09');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// 3. Handle Login Logic
$error_message = '';
$success_message = '';

if (isset($_POST['login'])) {
    $memberID = $_POST['memberID'];
    $password = $_POST['password'];

    // Check credentials against the DB
    $stmt = $conn->prepare("SELECT * FROM memberCredentials WHERE member_id = ? AND password = ?");
    $stmt->bind_param("ss", $memberID, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['member_loggedin'] = true;
        $_SESSION['member_id'] = $memberID;
        header("Location: index.php");
        exit();
    } else {
        $error_message = "Invalid Member ID or Password. (Note: Your account must be created by the moderator first)";
    }
}

// 4. Handle Password Change Logic
if (isset($_POST['change_password']) && isset($_SESSION['member_loggedin'])) {
    $memberID = $_SESSION['member_id'];
    $oldPass = $_POST['old_password'];
    $newPass = $_POST['new_password'];

    // Verify old password first
    $stmt = $conn->prepare("SELECT * FROM memberCredentials WHERE member_id = ? AND password = ?");
    $stmt->bind_param("ss", $memberID, $oldPass);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update to new password
        $upd = $conn->prepare("UPDATE memberCredentials SET password = ? WHERE member_id = ?");
        $upd->bind_param("ss", $newPass, $memberID);
        if ($upd->execute()) {
            $success_message = "Password changed successfully!";
        } else {
            $error_message = "Error updating password.";
        }
    } else {
        $error_message = "Old password is incorrect.";
    }
}

// 5. Fetch Data (Only if logged in)
$member_data = null;
$details_data = [];
$totals = [
    'Share' => 0, 'Deposit' => 0, 'Land_Advance' => 0, 
    'Soil_Test' => 0, 'Boundary' => 0, 'Others' => 0, 'Total' => 0
];

if (isset($_SESSION['member_loggedin'])) {
    $member_id = $_SESSION['member_id'];

    // A. Fetch Basic Details
    $stmt = $conn->prepare("SELECT * FROM memberBasicDetails WHERE id_no = ?");
    $stmt->bind_param("s", $member_id);
    $stmt->execute();
    $basic_result = $stmt->get_result();
    $member_data = $basic_result->fetch_assoc();

    // B. Fetch Transaction Details
    if ($member_data) {
        $table_name = str_replace('-', '_', $member_id);
        
        $check_table = $conn->query("SHOW TABLES LIKE '$table_name'");
        
        if ($check_table && $check_table->num_rows > 0) {
            $sql = "SELECT * FROM $table_name ORDER BY Submission_Date ASC, No ASC";
            $result = $conn->query($sql);
            
            while($row = $result->fetch_assoc()) {
                $details_data[] = $row;
                $totals['Share'] += $row['Share'];
                $totals['Deposit'] += $row['Deposit'];
                $totals['Land_Advance'] += $row['Land_Advance'];
                $totals['Soil_Test'] += $row['Soil_Test'];
                $totals['Boundary'] += $row['Boundary'];
                $totals['Others'] += $row['Others'];
                
                $row_sum = $row['Share'] + $row['Deposit'] + $row['Land_Advance'] + 
                           $row['Soil_Test'] + $row['Boundary'] + $row['Others'];
                $totals['Total'] += $row_sum;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Portal | Shapnachura</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
        
        /* Login Styles */
        .login-container {
            max-width: 400px; margin: 80px auto; padding: 30px;
            background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .login-header { text-align: center; margin-bottom: 20px; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #666; }
        .form-control {
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;
            box-sizing: border-box; font-size: 16px;
        }
        .btn-primary {
            width: 100%; padding: 12px; background-color: #007bff; color: white;
            border: none; border-radius: 4px; cursor: pointer; font-size: 16px;
        }
        .btn-primary:hover { background-color: #0056b3; }
        .btn-secondary {
            padding: 8px 15px; background-color: #6c757d; color: white;
            border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 14px;
        }
        .error { color: #dc3545; text-align: center; margin-bottom: 15px; padding: 10px; background: #f8d7da; border-radius: 4px; }
        .success { color: #28a745; text-align: center; margin-bottom: 15px; padding: 10px; background: #d4edda; border-radius: 4px; }

        /* Dashboard Styles */
        .dashboard-container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .header-section { border-bottom: 2px solid #007bff; padding-bottom: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-start; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; }
        .info-item { margin-bottom: 10px; }
        .info-label { font-weight: bold; color: #555; width: 120px; display: inline-block; }
        
        .header-actions { display: flex; gap: 10px; align-items: center; }
        .logout-btn {
            background-color: #dc3545; color: white; padding: 8px 20px;
            text-decoration: none; border-radius: 4px; font-size: 14px;
        }

        /* Modal for Password Change */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 300px; border-radius: 8px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        
        /* Table Styles */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 20px; border: 1px solid #dee2e6; border-radius: 4px; }
        th { background-color: #f8f9fa; color: #495057; font-weight: 600; padding: 15px; text-align: left; border-bottom: 2px solid #dee2e6; }
        td { padding: 12px 15px; border-bottom: 1px solid #dee2e6; color: #444; }
        tr:last-child td { border-bottom: none; }
        
        .readonly-field {
            background-color: #fff; border: 1px solid #e2e5e9; padding: 8px 12px;
            border-radius: 4px; color: #495057; display: block; width: 100%;
            box-sizing: border-box; font-size: 14px;
        }
        
        .totals-row td { background-color: #f1f3f5; font-weight: bold; color: #28a745; border-top: 2px solid #dee2e6; }
        .status-active { color: #28a745; font-weight: bold; }
        .status-retired { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>

<?php if (!isset($_SESSION['member_loggedin'])): ?>
    
    <div class="login-container">
        <h2 class="login-header">Member Login</h2>
        <?php if ($error_message): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Member ID</label>
                <input type="text" name="memberID" class="form-control" placeholder="e.g. MEM04" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter Password" required>
            </div>
            <button type="submit" name="login" class="btn-primary">View My Details</button>
        </form>
    </div>

<?php else: ?>

    <div class="dashboard-container">
        <?php if ($success_message): ?>
            <div class="success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="header-section">
            <div style="width: 100%;">
                <h2 style="margin-top:0; color:#333;">Member Basic Information</h2>
                <div class="info-grid">
                    <div class="info-column">
                        <div class="info-item"><span class="info-label">ID:</span> <?php echo htmlspecialchars($member_data['id_no']); ?></div>
                        <div class="info-item"><span class="info-label">Name:</span> <?php echo htmlspecialchars($member_data['name']); ?></div>
                        <div class="info-item"><span class="info-label">Designation:</span> <?php echo htmlspecialchars($member_data['designation']); ?></div>
                        <div class="info-item"><span class="info-label">Father's Name:</span> <?php echo htmlspecialchars($member_data['fathers_name']); ?></div>
                    </div>
                    <div class="info-column">
                        <div class="info-item"><span class="info-label">Address:</span> <?php echo htmlspecialchars($member_data['address']); ?></div>
                        <div class="info-item"><span class="info-label">Mobile No:</span> <?php echo htmlspecialchars($member_data['mobile_no']); ?></div>
                        <div class="info-item"><span class="info-label">Admit Date:</span> <?php echo htmlspecialchars($member_data['admit_date']); ?></div>
                        <div class="info-item">
                            <span class="info-label">Status:</span>
                            <?php if($member_data['resign_date']): ?>
                                <span class="status-retired">Retired</span> on <?php echo htmlspecialchars($member_data['resign_date']); ?>
                            <?php else: ?>
                                <span class="status-active">Active</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="header-actions">
                <button onclick="document.getElementById('passModal').style.display='block'" class="btn-secondary">Change Password</button>
                <a href="?logout" class="logout-btn">Log Out</a>
            </div>
        </div>

        <div id="passModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="document.getElementById('passModal').style.display='none'">&times;</span>
                <h3>Change Password</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Old Password</label>
                        <input type="password" name="old_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <button type="submit" name="change_password" class="btn-primary">Update Password</button>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th width="5%">No.</th>
                        <th width="12%">Submission Date</th>
                        <th>Share</th>
                        <th>Deposit</th>
                        
                        <?php 
                        $show_service_charge = false;
                        if($member_data['resign_date']) {
                            $admit = new DateTime($member_data['admit_date']);
                            $resign = new DateTime($member_data['resign_date']);
                            $interval = $admit->diff($resign);
                            if($interval->y < 5) $show_service_charge = true;
                        }
                        
                        if($show_service_charge): ?>
                            <th>Service Charge (20%)</th>
                            <th>Total Receivable</th>
                        <?php endif; ?>

                        <th>Land Advance</th>
                        <th>Soil Test</th>
                        <th>Boundary</th>
                        <th>Others</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grand_total_calculated = 0;
                    
                    if (empty($details_data)): ?>
                        <tr><td colspan="10" style="text-align:center;">No records found.</td></tr>
                    <?php else: ?>
                        <?php foreach($details_data as $row): 
                            $row_share_deposit = $row['Share'] + $row['Deposit'];
                            $row_costs = $row['Land_Advance'] + $row['Soil_Test'] + $row['Boundary'] + $row['Others'];
                            $row_display_total = 0;
                        ?>
                        <tr>
                            <td><?php echo $row['No']; ?></td>
                            <td><div class="readonly-field"><?php echo $row['Submission_Date']; ?></div></td>
                            <td><div class="readonly-field"><?php echo number_format($row['Share'], 2); ?></div></td>
                            <td><div class="readonly-field"><?php echo number_format($row['Deposit'], 2); ?></div></td>
                            
                            <?php if($show_service_charge): 
                                $s_charge = $row_share_deposit * 0.20;
                                $receivable = $row_share_deposit - $s_charge;
                                $row_display_total = $receivable + $row_costs;
                            ?>
                                <td><div class="readonly-field" style="color:#dc3545;"><?php echo number_format($s_charge, 2); ?></div></td>
                                <td><div class="readonly-field"><?php echo number_format($receivable, 2); ?></div></td>
                            <?php else: 
                                $row_display_total = $row_share_deposit + $row_costs;
                            endif; ?>
                            
                            <?php $grand_total_calculated += $row_display_total; ?>

                            <td><div class="readonly-field"><?php echo number_format($row['Land_Advance'], 2); ?></div></td>
                            <td><div class="readonly-field"><?php echo number_format($row['Soil_Test'], 2); ?></div></td>
                            <td><div class="readonly-field"><?php echo number_format($row['Boundary'], 2); ?></div></td>
                            <td><div class="readonly-field"><?php echo number_format($row['Others'], 2); ?></div></td>
                            <td style="font-weight:bold; color:#28a745;"><?php echo number_format($row_display_total, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="totals-row">
                        <td colspan="2">Column Totals</td>
                        <td><?php echo number_format($totals['Share'], 2); ?></td>
                        <td><?php echo number_format($totals['Deposit'], 2); ?></td>
                        
                        <?php if($show_service_charge): 
                            $total_share_deposit = $totals['Share'] + $totals['Deposit'];
                            $total_s_charge = $total_share_deposit * 0.20;
                            $total_receivable = $total_share_deposit - $total_s_charge;
                        ?>
                            <td><?php echo number_format($total_s_charge, 2); ?></td>
                            <td><?php echo number_format($total_receivable, 2); ?></td>
                        <?php endif; ?>

                        <td><?php echo number_format($totals['Land_Advance'], 2); ?></td>
                        <td><?php echo number_format($totals['Soil_Test'], 2); ?></td>
                        <td><?php echo number_format($totals['Boundary'], 2); ?></td>
                        <td><?php echo number_format($totals['Others'], 2); ?></td>
                        <td><?php echo number_format($grand_total_calculated, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

<?php endif; ?>

<script>
// Close modal if clicked outside
window.onclick = function(event) {
    if (event.target == document.getElementById('passModal')) {
        document.getElementById('passModal').style.display = "none";
    }
}
</script>

</body>
</html>