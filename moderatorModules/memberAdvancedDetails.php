<?php
// moderatorModules/memberAdvancedDetails.php
if (!isset($_SESSION['loggedin'])) {
    header("Location: ../moderator.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: moderator.php?module=userDetails");
    exit();
}

$member_id = $_GET['id'];

if (isset($_POST['update'])) {
    
    if (isset($_POST['update_basic_info']) && $_POST['update_basic_info'] === '1') {
        $update_basic_sql = "UPDATE memberBasicDetails SET 
            name = ?, designation = ?, fathers_name = ?, address = ?, mobile_no = ?, admit_date = ?
            WHERE id_no = ?";
        $stmt = $conn->prepare($update_basic_sql);
        $stmt->bind_param("sssssss", 
            $_POST['edit_name'],
            $_POST['edit_designation'],
            $_POST['edit_fathers_name'],
            $_POST['edit_address'],
            $_POST['edit_mobile_no'],
            $_POST['edit_admit_date'],
            $member_id
        );
        $stmt->execute();
    }

    if (isset($_POST['resign_status'])) {
        $status = $_POST['resign_status'];
        $resign_date = $_POST['resign_date']; 
        $new_password = isset($_POST['set_password']) ? trim($_POST['set_password']) : '';

        if ($status === 'active') {
            $status_sql = "UPDATE memberBasicDetails SET resign_date = NULL WHERE id_no = ?";
            $stmt = $conn->prepare($status_sql);
            $stmt->bind_param("s", $member_id);
            $stmt->execute();

            if (!empty($new_password)) {
                $check_sql = "SELECT id FROM memberCredentials WHERE member_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("s", $member_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($check_result->num_rows > 0) {
                    $upd_sql = "UPDATE memberCredentials SET password = ? WHERE member_id = ?";
                    $upd_stmt = $conn->prepare($upd_sql);
                    $upd_stmt->bind_param("ss", $new_password, $member_id);
                    $upd_stmt->execute();
                } else {
                    $ins_sql = "INSERT INTO memberCredentials (member_id, password) VALUES (?, ?)";
                    $ins_stmt = $conn->prepare($ins_sql);
                    $ins_stmt->bind_param("ss", $member_id, $new_password);
                    $ins_stmt->execute();
                }
            }
        } elseif ($status === 'retired') {
            $final_resign_date = !empty($resign_date) ? $resign_date : date('Y-m-d');
            $status_sql = "UPDATE memberBasicDetails SET resign_date = ? WHERE id_no = ?";
            $stmt = $conn->prepare($status_sql);
            $stmt->bind_param("ss", $final_resign_date, $member_id);
            $stmt->execute();

            $del_sql = "DELETE FROM memberCredentials WHERE member_id = ?";
            $del_stmt = $conn->prepare($del_sql);
            $del_stmt->bind_param("s", $member_id);
            $del_stmt->execute();
        }
    }

    $table_name = str_replace('-', '_', $member_id);
    
    $result = $conn->query("SHOW TABLES LIKE '$table_name'");
    if ($result->num_rows == 0) {
        $create_table_sql = "CREATE TABLE $table_name (
            No INT AUTO_INCREMENT PRIMARY KEY,
            Submission_Date DATE NOT NULL,
            Share DECIMAL(10,2) DEFAULT 0.00,
            Deposit DECIMAL(10,2) DEFAULT 0.00,
            Land_Advance DECIMAL(10,2) DEFAULT 0.00,
            Soil_Test DECIMAL(10,2) DEFAULT 0.00,
            Boundary DECIMAL(10,2) DEFAULT 0.00,
            Others DECIMAL(10,2) DEFAULT 0.00,
            Total DECIMAL(10,2) GENERATED ALWAYS AS 
                (Share + Deposit + Land_Advance + Soil_Test + Boundary + Others) STORED
        )";
        $conn->query($create_table_sql);
    }

    if(isset($_POST['rows']) && is_array($_POST['rows'])) {
        foreach($_POST['rows'] as $row) {
            if(empty($row['Submission_Date'])) continue;

            if(isset($row['No']) && $row['No'] != '' && is_numeric($row['No'])) {
                $update_sql = "UPDATE $table_name SET 
                    Submission_Date = ?, Share = ?, Deposit = ?, Land_Advance = ?, 
                    Soil_Test = ?, Boundary = ?, Others = ? WHERE No = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("sddddddi", 
                    $row['Submission_Date'], $row['Share'], $row['Deposit'], 
                    $row['Land_Advance'], $row['Soil_Test'], $row['Boundary'], 
                    $row['Others'], $row['No']
                );
                $stmt->execute();
            } else {
                $insert_sql = "INSERT INTO $table_name 
                    (Submission_Date, Share, Deposit, Land_Advance, Soil_Test, Boundary, Others)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("sdddddd", 
                    $row['Submission_Date'], $row['Share'], $row['Deposit'], 
                    $row['Land_Advance'], $row['Soil_Test'], $row['Boundary'], $row['Others']
                );
                $stmt->execute();
            }
        }
    }
    
    echo "<script>window.location.href = window.location.href;</script>";
    exit();
}

$sql = "SELECT * FROM memberBasicDetails WHERE id_no = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $member_id);
$stmt->execute();
$basic_result = $stmt->get_result();
$member_data = $basic_result->fetch_assoc();

$current_password = '';
$pwd_sql = "SELECT password FROM memberCredentials WHERE member_id = ?";
$pwd_stmt = $conn->prepare($pwd_sql);
$pwd_stmt->bind_param("s", $member_id);
$pwd_stmt->execute();
$pwd_result = $pwd_stmt->get_result();
if ($pwd_row = $pwd_result->fetch_assoc()) {
    $current_password = $pwd_row['password'];
}

function calculateMembershipDuration($admit_date, $resign_date) {
    $admit = new DateTime($admit_date);
    $resign = new DateTime($resign_date);
    $interval = $admit->diff($resign);
    return $interval->y; 
}

$table_name = str_replace('-', '_', $member_id);
$table_exists = false;
$result = $conn->query("SHOW TABLES LIKE '$table_name'");
if($result->num_rows > 0) {
    $table_exists = true;
}

$show_service_charge = false;
if($member_data['resign_date']) {
    $duration = calculateMembershipDuration($member_data['admit_date'], $member_data['resign_date']);
    $show_service_charge = ($duration < 5);
}

if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    ob_clean();
    $conn->begin_transaction();
    try {
        $delete_member_sql = "DELETE FROM memberBasicDetails WHERE id_no = ?";
        $stmt = $conn->prepare($delete_member_sql);
        $stmt->bind_param("s", $member_id);
        $stmt->execute();
        
        $delete_cred_sql = "DELETE FROM memberCredentials WHERE member_id = ?";
        $stmt = $conn->prepare($delete_cred_sql);
        $stmt->bind_param("s", $member_id);
        $stmt->execute();

        $drop_table_sql = "DROP TABLE IF EXISTS $table_name";
        $conn->query($drop_table_sql);
        
        $conn->commit();
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

$details_data = [];
if($table_exists) {
    $sql = "SELECT * FROM $table_name ORDER BY Submission_Date ASC, No ASC";
    $result = $conn->query($sql);
    while($row = $result->fetch_assoc()) {
        $details_data[] = $row;
    }
}
?>

<style>
    table { width: 100%; border-collapse: collapse; margin-top: 20px; background-color: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #f2f2f2; font-weight: bold; color: #333; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    tr:hover { background-color: #f5f5f5; }
    .member-details { background-color: white; border: 1px solid #ddd; border-radius: 4px; padding: 25px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .member-details h3 { margin-top: 0; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 20px; }
    .member-details p { margin: 10px 0; line-height: 1.6; display: flex; align-items: center; } 
    .member-details strong { color: #444; width: 150px; display: inline-block; }
    input[type="date"], input[type="number"], select, input[type="text"] { padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
    input[type="number"]:focus, input[type="date"]:focus, select:focus, input[type="text"]:focus { border-color: #007bff; outline: none; box-shadow: 0 0 5px rgba(0,123,255,0.2); }
    .add-row { background-color: #f8f9fa; border: 2px dashed #007bff; color: #007bff; padding: 15px; margin: 20px 0; border-radius: 4px; cursor: pointer; text-align: center; font-size: 18px; transition: all 0.3s ease; }
    .column-totals { background-color: #f2f2f2; font-weight: bold; }
    .col-total { color: #28a745; }
    .add-row:hover { background-color: #e9ecef; transform: translateY(-1px); }
    .print-btn, .edit-btn { float: right; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-bottom: 20px; margin-left: 10px; }
    .print-btn { background-color: #28a745; color: white; }
    .print-btn:hover { background-color: #218838; }
    .edit-btn { background-color: #ffc107; color: #212529; }
    .edit-btn:hover { background-color: #e0a800; }
    .edit-btn.cancel { background-color: #6c757d; color: white; }
    .edit-btn.cancel:hover { background-color: #5a6268; }
    .update-btn { background-color: #28a745; color: white; padding: 12px 25px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin-top: 20px; transition: background-color 0.3s ease; }
    .update-btn:hover { background-color: #218838; }
    .back-btn { background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin-bottom: 20px; transition: background-color 0.3s ease; }
    .back-btn:hover { background-color: #0056b3; text-decoration: none; color: white; }
    .delete-btn { background-color: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; float: right; font-size: 16px; transition: background-color 0.3s ease; }
    .delete-btn:hover { background-color: #c82333; }
    .total { font-weight: bold; background-color: #f8f9fa; color: #28a745; }
    .status-container { display: inline-flex; align-items: center; gap: 10px; }
    .status-select { padding: 5px; border-radius: 4px; border: 1px solid #ddd; }
    .password-input { width: 200px; padding: 6px; }
    .editable-field { display: none; width: 250px; }
    .editable-field.active { display: inline-block; }
    .field-text.hidden { display: none; }
    .btn-group { float: right; }
    @media screen and (max-width: 768px) {
        .member-details p { flex-direction: column; align-items: flex-start; }
        .member-details strong { width: 100%; margin-bottom: 5px; }
        .editable-field { width: 100%; }
        .btn-group { float: none; display: flex; gap: 10px; margin-bottom: 15px; }
        .print-btn, .edit-btn { float: none; margin: 0; }
    }
    @media screen and (max-width: 1024px) { .member-details { padding: 15px; } th, td { padding: 8px; } }
    @media print {
        .back-btn, .print-btn, .edit-btn, .add-row, .update-btn, .delete-btn, .status-select, .password-row, .editable-field, .btn-group { display: none !important; }
        .field-text { display: inline !important; }
        .print-status-text { display: inline-block !important; }
        .logout-btn, .page-link { display: none !important; }
        body { padding: 0; margin: 0; }
        .member-details, .container { padding: 10px; margin: 0; box-shadow: none; }
        table { width: 100%; page-break-inside: auto; border-collapse: collapse; box-shadow: none; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        th { background-color: #f2f2f2 !important; -webkit-print-color-adjust: exact; color-adjust: exact; }
        td, th { border: 1px solid #000 !important; padding: 8px; }
        .member-details { position: relative; page-break-inside: avoid; }
        @page { size: landscape; margin: 20mm; }
        * { color: black !important; }
        td[data-value] { text-align: right; }
        .column-totals { font-weight: bold; background-color: #f2f2f2 !important; -webkit-print-color-adjust: exact; color-adjust: exact; }
        tr:hover { background-color: transparent; }
        input { border: none; padding: 0; margin: 0; width: auto; }
        input[type="date"], input[type="number"] { -webkit-appearance: none; margin: 0; padding: 0; border: none; background: transparent; }
        tfoot { display: table-footer-group; }
        a[href]:after { content: none !important; }
        .column-totals { break-inside: avoid; }
        a { text-decoration: none; }
    }
    .print-status-text { display: none; }
</style>

<a href="moderator.php?module=userDetails" class="back-btn">← Back</a>
<button class="delete-btn" onclick="confirmDelete()">Delete Member</button>

<form id="detailsForm" method="POST">
<input type="hidden" name="update_basic_info" id="updateBasicInfo" value="0">

<div class="member-details">
    <h3>Member Basic Information</h3>
    <div class="btn-group">
        <button type="button" onclick="window.print()" class="print-btn">Print</button>
        <button type="button" onclick="toggleEditMode()" class="edit-btn" id="editBtn">Edit</button>
    </div>
    
    <p><strong>ID:</strong> <span><?php echo htmlspecialchars($member_data['id_no']); ?></span></p>
    
    <p>
        <strong>Name:</strong> 
        <span class="field-text" data-field="name"><?php echo htmlspecialchars($member_data['name']); ?></span>
        <input type="text" name="edit_name" class="editable-field" data-field="name" value="<?php echo htmlspecialchars($member_data['name']); ?>">
    </p>
    
    <p>
        <strong>Designation:</strong> 
        <span class="field-text" data-field="designation"><?php echo htmlspecialchars($member_data['designation']); ?></span>
        <input type="text" name="edit_designation" class="editable-field" data-field="designation" value="<?php echo htmlspecialchars($member_data['designation']); ?>">
    </p>
    
    <p>
        <strong>Father's Name:</strong> 
        <span class="field-text" data-field="fathers_name"><?php echo htmlspecialchars($member_data['fathers_name']); ?></span>
        <input type="text" name="edit_fathers_name" class="editable-field" data-field="fathers_name" value="<?php echo htmlspecialchars($member_data['fathers_name']); ?>">
    </p>
    
    <p>
        <strong>Address:</strong> 
        <span class="field-text" data-field="address"><?php echo htmlspecialchars($member_data['address']); ?></span>
        <input type="text" name="edit_address" class="editable-field" data-field="address" value="<?php echo htmlspecialchars($member_data['address']); ?>">
    </p>
    
    <p>
        <strong>Mobile No:</strong> 
        <span class="field-text" data-field="mobile_no"><?php echo htmlspecialchars($member_data['mobile_no']); ?></span>
        <input type="text" name="edit_mobile_no" class="editable-field" data-field="mobile_no" value="<?php echo htmlspecialchars($member_data['mobile_no']); ?>">
    </p>
    
    <p>
        <strong>Admit Date:</strong> 
        <span class="field-text" data-field="admit_date"><?php echo htmlspecialchars($member_data['admit_date']); ?></span>
        <input type="date" name="edit_admit_date" class="editable-field" data-field="admit_date" value="<?php echo htmlspecialchars($member_data['admit_date']); ?>">
    </p>
    
    <p>
        <strong>Status:</strong> 
        <span class="status-container">
            <select name="resign_status" id="resignStatus" class="status-select" onchange="toggleStatusDate()">
                <option value="active" <?php echo $member_data['resign_date'] ? '' : 'selected'; ?>>Active</option>
                <option value="retired" <?php echo $member_data['resign_date'] ? 'selected' : ''; ?>>Retired</option>
            </select>
            <span id="dateContainer" style="display: <?php echo $member_data['resign_date'] ? 'inline' : 'none'; ?>;">
                on 
                <input type="date" name="resign_date" id="resignDate" value="<?php echo $member_data['resign_date'] ? $member_data['resign_date'] : ''; ?>">
            </span>
        </span>
        <span class="print-status-text">
            <?php echo $member_data['resign_date'] ? 'Resigned on ' . htmlspecialchars($member_data['resign_date']) : 'Active'; ?>
        </span>
    </p>

    <p class="password-row" id="passwordRow" style="display: <?php echo $member_data['resign_date'] ? 'none' : 'flex'; ?>;">
        <strong>Set Password:</strong>
        <input type="text" name="set_password" class="password-input" placeholder="Set/Update Password" autocomplete="off" value="<?php echo htmlspecialchars($current_password); ?>">
    </p>
</div>

    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Submission Date</th>
                <th>Share</th>
                <th>Deposit</th>
                <?php if($member_data['resign_date'] && $show_service_charge): ?>
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
        <tbody id="detailsTableBody">
            <?php if(!empty($details_data)): ?>
                <?php foreach($details_data as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['No']); ?>
                            <input type="hidden" name="rows[<?php echo $row['No']; ?>][No]" value="<?php echo $row['No']; ?>">
                        </td>
                        <td><input type="date" name="rows[<?php echo $row['No']; ?>][Submission_Date]" value="<?php echo $row['Submission_Date']; ?>" required></td>
                        <td><input type="number" step="0.01" name="rows[<?php echo $row['No']; ?>][Share]" value="<?php echo $row['Share']; ?>" oninput="calculateTotal(this)"></td>
                        <td><input type="number" step="0.01" name="rows[<?php echo $row['No']; ?>][Deposit]" value="<?php echo $row['Deposit']; ?>" oninput="calculateTotal(this)"></td>
                        <?php if($member_data['resign_date'] && $show_service_charge): ?>
                            <td class="service-charge">0.00</td>
                            <td class="total-receivable">0.00</td>
                        <?php endif; ?>
                        <td><input type="number" step="0.01" name="rows[<?php echo $row['No']; ?>][Land_Advance]" value="<?php echo $row['Land_Advance']; ?>" oninput="calculateTotal(this)"></td>
                        <td><input type="number" step="0.01" name="rows[<?php echo $row['No']; ?>][Soil_Test]" value="<?php echo $row['Soil_Test']; ?>" oninput="calculateTotal(this)"></td>
                        <td><input type="number" step="0.01" name="rows[<?php echo $row['No']; ?>][Boundary]" value="<?php echo $row['Boundary']; ?>" oninput="calculateTotal(this)"></td>
                        <td><input type="number" step="0.01" name="rows[<?php echo $row['No']; ?>][Others]" value="<?php echo $row['Others']; ?>" oninput="calculateTotal(this)"></td>
                        <td class="total"><?php echo $row['Total']; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td>New</td>
                    <td><input type="date" name="rows[new][Submission_Date]" required></td>
                    <td><input type="number" step="0.01" name="rows[new][Share]" value="0.00" oninput="calculateTotal(this)"></td>
                    <td><input type="number" step="0.01" name="rows[new][Deposit]" value="0.00" oninput="calculateTotal(this)"></td>
                    <?php if($member_data['resign_date'] && $show_service_charge): ?>
                        <td class="service-charge">0.00</td>
                        <td class="total-receivable">0.00</td>
                    <?php endif; ?>
                    <td><input type="number" step="0.01" name="rows[new][Land_Advance]" value="0.00" oninput="calculateTotal(this)"></td>
                    <td><input type="number" step="0.01" name="rows[new][Soil_Test]" value="0.00" oninput="calculateTotal(this)"></td>
                    <td><input type="number" step="0.01" name="rows[new][Boundary]" value="0.00" oninput="calculateTotal(this)"></td>
                    <td><input type="number" step="0.01" name="rows[new][Others]" value="0.00" oninput="calculateTotal(this)"></td>
                    <td class="total">0.00</td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr class="column-totals" style="page-break-inside: avoid;">
                <td><strong>Column Totals</strong></td>
                <td></td>
                <td class="col-total" id="share-total">0.00</td>
                <td class="col-total" id="deposit-total">0.00</td>
                <?php if($member_data['resign_date'] && $show_service_charge): ?>
                    <td class="col-total" id="service-charge-total">0.00</td>
                    <td class="col-total" id="total-receivable-total">0.00</td>
                <?php endif; ?>
                <td class="col-total" id="land-advance-total">0.00</td>
                <td class="col-total" id="soil-test-total">0.00</td>
                <td class="col-total" id="boundary-total">0.00</td>
                <td class="col-total" id="others-total">0.00</td>
                <td class="col-total" id="grand-total">0.00</td>
            </tr>
        </tfoot>
    </table>
    
    <div class="add-row" onclick="addNewRow()">+</div>
    <button type="submit" name="update" class="update-btn">Update</button>
</form>

<script>
let isEditMode = false;
let originalValues = {};

function toggleEditMode() {
    const editBtn = document.getElementById('editBtn');
    const fields = document.querySelectorAll('.editable-field');
    const texts = document.querySelectorAll('.field-text');
    
    isEditMode = !isEditMode;
    
    if (isEditMode) {
        fields.forEach(field => {
            const fieldName = field.getAttribute('data-field');
            originalValues[fieldName] = field.value;
            field.classList.add('active');
        });
        texts.forEach(text => text.classList.add('hidden'));
        editBtn.textContent = 'Cancel Edit';
        editBtn.classList.add('cancel');
    } else {
        fields.forEach(field => {
            const fieldName = field.getAttribute('data-field');
            field.value = originalValues[fieldName] || field.value;
            field.classList.remove('active');
        });
        texts.forEach(text => text.classList.remove('hidden'));
        editBtn.textContent = 'Edit';
        editBtn.classList.remove('cancel');
        document.getElementById('updateBasicInfo').value = '0';
    }
}

function checkBasicInfoChanges() {
    if (!isEditMode) return false;
    
    const fields = document.querySelectorAll('.editable-field');
    let hasChanges = false;
    
    fields.forEach(field => {
        const fieldName = field.getAttribute('data-field');
        if (field.value !== originalValues[fieldName]) {
            hasChanges = true;
        }
    });
    
    return hasChanges;
}

document.getElementById('detailsForm').addEventListener('submit', function(e) {
    if (isEditMode && checkBasicInfoChanges()) {
        document.getElementById('updateBasicInfo').value = '1';
    }
});

function toggleStatusDate() {
    const status = document.getElementById('resignStatus').value;
    const dateContainer = document.getElementById('dateContainer');
    const dateInput = document.getElementById('resignDate');
    const passwordRow = document.getElementById('passwordRow');
    
    if (status === 'retired') {
        dateContainer.style.display = 'inline';
        dateInput.required = true;
        passwordRow.style.display = 'none';
    } else {
        dateContainer.style.display = 'none';
        dateInput.required = false;
        dateInput.value = '';
        passwordRow.style.display = 'flex';
    }
}

function addNewRow() {
    const tbody = document.getElementById('detailsTableBody');
    const newRow = document.createElement('tr');
    const rowIndex = 'new_' + Date.now();
    const isResigned = document.querySelector('.service-charge') !== null;
    
    let rowHTML = `
        <td>New<input type="hidden" name="rows[${rowIndex}][No]" value=""></td>
        <td><input type="date" name="rows[${rowIndex}][Submission_Date]" required></td>
        <td><input type="number" step="0.01" name="rows[${rowIndex}][Share]" value="0.00" oninput="calculateTotal(this)"></td>
        <td><input type="number" step="0.01" name="rows[${rowIndex}][Deposit]" value="0.00" oninput="calculateTotal(this)"></td>`;
        
    if (isResigned && <?php echo $show_service_charge ? 'true' : 'false' ?>) {
        rowHTML += `<td class="service-charge">0.00</td><td class="total-receivable">0.00</td>`;
    }
    
    rowHTML += `
        <td><input type="number" step="0.01" name="rows[${rowIndex}][Land_Advance]" value="0.00" oninput="calculateTotal(this)"></td>
        <td><input type="number" step="0.01" name="rows[${rowIndex}][Soil_Test]" value="0.00" oninput="calculateTotal(this)"></td>
        <td><input type="number" step="0.01" name="rows[${rowIndex}][Boundary]" value="0.00" oninput="calculateTotal(this)"></td>
        <td><input type="number" step="0.01" name="rows[${rowIndex}][Others]" value="0.00" oninput="calculateTotal(this)"></td>
        <td class="total">0.00</td>`;
    
    newRow.innerHTML = rowHTML;
    tbody.appendChild(newRow);
    calculateColumnTotals();
}

function calculateTotal(input) {
    const row = input.closest('tr');
    const getVal = (name) => {
        const el = row.querySelector(`input[name$="[${name}]"]`);
        return el ? (parseFloat(el.value) || 0) : 0;
    };

    const shareValue = getVal('Share');
    const depositValue = getVal('Deposit');
    const landAdvValue = getVal('Land_Advance');
    const soilTestValue = getVal('Soil_Test');
    const boundaryValue = getVal('Boundary');
    const othersValue = getVal('Others');
    
    const serviceChargeCell = row.querySelector('.service-charge');
    const totalReceivableCell = row.querySelector('.total-receivable');
    let rowTotal = 0;
    
    if (serviceChargeCell && totalReceivableCell && <?php echo $show_service_charge ? 'true' : 'false' ?>) {
        const serviceCharge = (shareValue + depositValue) * 0.2;
        const totalReceivable = (shareValue + depositValue) - serviceCharge;
        serviceChargeCell.textContent = serviceCharge.toFixed(2);
        totalReceivableCell.textContent = totalReceivable.toFixed(2);
        rowTotal = totalReceivable + landAdvValue + soilTestValue + boundaryValue + othersValue;
    } else {
        rowTotal = shareValue + depositValue + landAdvValue + soilTestValue + boundaryValue + othersValue;
    }
    
    row.querySelector('.total').textContent = rowTotal.toFixed(2);
    calculateColumnTotals();
}

function calculateColumnTotals() {
    let shareTot = 0, depositTot = 0, landAdvTot = 0, soilTestTot = 0, boundaryTot = 0, othersTot = 0, grandTot = 0, serviceChargeTot = 0, totalReceivableTot = 0;

    const rows = document.querySelectorAll('#detailsTableBody tr');
    
    rows.forEach(row => {
        const getVal = (name) => {
            const el = row.querySelector(`input[name$="[${name}]"]`);
            return el ? (parseFloat(el.value) || 0) : 0;
        };

        shareTot += getVal('Share');
        depositTot += getVal('Deposit');
        landAdvTot += getVal('Land_Advance');
        soilTestTot += getVal('Soil_Test');
        boundaryTot += getVal('Boundary');
        othersTot += getVal('Others');
        
        const serviceChargeCell = row.querySelector('.service-charge');
        const totalReceivableCell = row.querySelector('.total-receivable');
        if (serviceChargeCell && totalReceivableCell) {
            serviceChargeTot += parseFloat(serviceChargeCell.textContent) || 0;
            totalReceivableTot += parseFloat(totalReceivableCell.textContent) || 0;
            grandTot = totalReceivableTot + landAdvTot + soilTestTot + boundaryTot + othersTot;
        } else {
            grandTot = shareTot + depositTot + landAdvTot + soilTestTot + boundaryTot + othersTot;
        }
    });

    document.getElementById('share-total').textContent = shareTot.toFixed(2);
    document.getElementById('deposit-total').textContent = depositTot.toFixed(2);
    
    if (document.querySelector('.service-charge')) {
        document.getElementById('service-charge-total').textContent = serviceChargeTot.toFixed(2);
        document.getElementById('total-receivable-total').textContent = totalReceivableTot.toFixed(2);
    }
    
    document.getElementById('land-advance-total').textContent = landAdvTot.toFixed(2);
    document.getElementById('soil-test-total').textContent = soilTestTot.toFixed(2);
    document.getElementById('boundary-total').textContent = boundaryTot.toFixed(2);
    document.getElementById('others-total').textContent = othersTot.toFixed(2);
    document.getElementById('grand-total').textContent = grandTot.toFixed(2);
}

async function confirmDelete() {
    if (confirm('Are you sure you want to delete this member? This action cannot be undone!')) {
        try {
            const urlParams = new URLSearchParams(window.location.search);
            const memberId = urlParams.get('id');
            
            const response = await fetch(`moderator.php?module=memberAdvancedDetails&id=${memberId}&action=delete`, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });
            
            if (response.ok) {
                window.location.href = 'moderator.php?module=userDetails&deleted=success';
            } else {
                alert('Error deleting member. Please try again.');
            }
        } catch (error) {
            alert('Error during deletion: ' + error.message);
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#detailsTableBody tr').forEach(row => {
        const firstInput = row.querySelector('input[type="number"]');
        if (firstInput) calculateTotal(firstInput);
    });
    calculateColumnTotals();
});
</script>