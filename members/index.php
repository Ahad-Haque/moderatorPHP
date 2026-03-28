<?php
// members/index.php
session_start();

$conn = new mysqli('localhost', 'shapnach_wp2026', 'shapnach_wp2026', 'shapnach_moderator09');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

$error_message = '';
$success_message = '';

if (isset($_POST['login'])) {
    $memberID = $_POST['memberID'];
    $password = $_POST['password'];

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

if (isset($_POST['change_password']) && isset($_SESSION['member_loggedin'])) {
    $memberID = $_SESSION['member_id'];
    $oldPass = $_POST['old_password'];
    $newPass = $_POST['new_password'];

    $stmt = $conn->prepare("SELECT * FROM memberCredentials WHERE member_id = ? AND password = ?");
    $stmt->bind_param("ss", $memberID, $oldPass);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
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

$member_data = null;
$details_data = [];
$totals = [
    'Share' => 0, 'Deposit' => 0, 'Land_Advance' => 0, 
    'Soil_Test' => 0, 'Boundary' => 0, 'Others' => 0, 'Total' => 0
];

$photo_url = '';
$photo_exists = false;

if (isset($_SESSION['member_loggedin'])) {
    $member_id = $_SESSION['member_id'];

    $stmt = $conn->prepare("SELECT * FROM memberBasicDetails WHERE id_no = ?");
    $stmt->bind_param("s", $member_id);
    $stmt->execute();
    $basic_result = $stmt->get_result();
    $member_data = $basic_result->fetch_assoc();

    $safe_member_id = strtoupper(preg_replace('/[^a-zA-Z0-9_\-]/', '', $member_id));
    $photo_path = dirname(__DIR__) . '/uploads/members/' . $safe_member_id . '.jpg';
    $photo_exists = file_exists($photo_path);
    $photo_url = $photo_exists ? '../uploads/members/' . $safe_member_id . '.jpg?t=' . filemtime($photo_path) : '';

    if ($member_data) {
        $table_name = strtoupper(str_replace('-', '_', $member_id));

        $sql = "SELECT * FROM `$table_name` ORDER BY Submission_Date ASC, No ASC";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
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

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Portal | Shapnachura</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
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
        .dashboard-container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .header-section { border-bottom: 2px solid #007bff; padding-bottom: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px; }
        .header-info-area { flex: 1; min-width: 0; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; }
        .info-item { margin-bottom: 10px; }
        .info-label { font-weight: bold; color: #555; width: 120px; display: inline-block; }
        .header-right { display: flex; flex-direction: column; align-items: center; gap: 12px; flex-shrink: 0; }
        .header-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .logout-btn {
            background-color: #dc3545; color: white; padding: 8px 20px;
            text-decoration: none; border-radius: 4px; font-size: 14px;
        }
        .photo-container { width: 120px; height: 120px; border-radius: 8px; overflow: hidden; cursor: pointer; position: relative; border: 2px solid #dee2e6; background: #e9ecef; }
        .photo-container img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .photo-container .photo-overlay { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.6); color: white; text-align: center; padding: 5px 0; font-size: 11px; opacity: 0; transition: opacity 0.2s; }
        .photo-container:hover .photo-overlay { opacity: 1; }
        .photo-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
        .photo-placeholder svg { width: 60%; height: 60%; fill: #adb5bd; }
        .photo-uploading { opacity: 0.5; pointer-events: none; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 300px; border-radius: 8px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 20px; border: 1px solid #dee2e6; border-radius: 4px; }
        th { background-color: #f8f9fa; color: #495057; font-weight: 600; padding: 15px; text-align: left; border-bottom: 2px solid #dee2e6; white-space: nowrap; }
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
        @media screen and (max-width: 768px) {
            body { padding: 10px; }
            .dashboard-container { padding: 15px; }
            .header-section { flex-direction: column; align-items: center; }
            .header-right { width: 100%; flex-direction: row; justify-content: space-between; align-items: center; }
            .header-actions { width: auto; justify-content: flex-end; }
            .header-info-area { width: 100%; }
            th, td { padding: 8px 10px; font-size: 14px; }
            .info-label { width: 100px; }
            .photo-container { width: 80px; height: 80px; }
        }
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
            <div class="header-info-area">
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
            
            <div class="header-right">
                <div class="photo-container" id="photoContainer" onclick="document.getElementById('photoInput').click()">
                    <?php if ($photo_exists): ?>
                        <img id="memberPhoto" src="<?php echo $photo_url; ?>" alt="Member Photo">
                    <?php else: ?>
                        <div class="photo-placeholder" id="photoPlaceholder">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="8" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>
                        </div>
                        <img id="memberPhoto" src="" alt="Member Photo" style="display:none;">
                    <?php endif; ?>
                    <div class="photo-overlay">Change Photo</div>
                </div>
                <input type="file" id="photoInput" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="uploadPhoto(this)">
                <div class="header-actions">
                    <button onclick="document.getElementById('passModal').style.display='block'" class="btn-secondary">Change Password</button>
                    <a href="?logout" class="logout-btn">Log Out</a>
                </div>
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
                        <th>No.</th>
                        <th>Submission Date</th>
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
                        <tr><td colspan="<?php echo $show_service_charge ? '12' : '10'; ?>" style="text-align:center;">No records found.</td></tr>
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
window.onclick = function(event) {
    var modal = document.getElementById('passModal');
    if (modal && event.target == modal) {
        modal.style.display = "none";
    }
}

function uploadPhoto(input) {
    if (!input.files || !input.files[0]) return;

    var file = input.files[0];
    if (file.size > 2 * 1024 * 1024) {
        alert('File too large. Max 2MB.');
        input.value = '';
        return;
    }

    var container = document.getElementById('photoContainer');
    container.classList.add('photo-uploading');

    var fd = new FormData();
    fd.append('photo', file);
    fd.append('member_id', '<?php echo isset($safe_member_id) ? $safe_member_id : ''; ?>');

    fetch('../upload_member_image.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            container.classList.remove('photo-uploading');
            if (data.success) {
                var img = document.getElementById('memberPhoto');
                img.src = '../' + data.path;
                img.style.display = 'block';
                var ph = document.getElementById('photoPlaceholder');
                if (ph) ph.style.display = 'none';
            } else {
                alert(data.message || 'Upload failed');
            }
        })
        .catch(function(err) {
            container.classList.remove('photo-uploading');
            alert('Upload error: ' + err.message);
        });

    input.value = '';
}
</script>

</body>
</html>