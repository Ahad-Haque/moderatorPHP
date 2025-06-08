<?php
// Check if user is logged in
if (!isset($_SESSION['loggedin'])) {
    header("Location: ../moderator.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: moderator.php?module=userDetails");
    exit();
}

$member_id = $_GET['id'];

// Get member basic details
$sql = "SELECT * FROM memberBasicDetails WHERE id_no = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $member_id);
$stmt->execute();
$basic_result = $stmt->get_result();
$member_data = $basic_result->fetch_assoc();


function calculateMembershipDuration($admit_date, $resign_date) {
    $admit = new DateTime($admit_date);
    $resign = new DateTime($resign_date);
    $interval = $admit->diff($resign);
    return $interval->y; 
}

// Check if member details table exists
$table_name = str_replace('-', '_', $member_id);
$table_exists = false;
$result = $conn->query("SHOW TABLES LIKE '$table_name'");
if($result->num_rows > 0) {
    $table_exists = true;
}

// After getting member data
$show_service_charge = false;
if($member_data['resign_date']) {
    $duration = calculateMembershipDuration($member_data['admit_date'], $member_data['resign_date']);
    $show_service_charge = ($duration < 5);
}

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    // Prevent any output before JSON response
    ob_clean();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete from memberBasicDetails first
        $delete_member_sql = "DELETE FROM memberBasicDetails WHERE id_no = ?";
        $stmt = $conn->prepare($delete_member_sql);
        $stmt->bind_param("s", $member_id);
        $stmt->execute();
        
        // Check if the member-specific table exists and delete it
        $table_name = str_replace('-', '_', $member_id);
        $drop_table_sql = "DROP TABLE IF EXISTS $table_name";
        $conn->query($drop_table_sql);
        
        // If everything is successful, commit the transaction
        $conn->commit();
        
        // Ensure headers are set correctly
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
        
    } catch (Exception $e) {
        // If there's an error, rollback the transaction
        $conn->rollback();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// Handle form submissions
if(isset($_POST['update'])) {
    if(!$table_exists) {
        // Create table if it doesn't exist
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
        $table_exists = true;
    }

    // Handle updates for existing rows and insertion of new rows
    if(isset($_POST['rows']) && is_array($_POST['rows'])) {
        foreach($_POST['rows'] as $row) {
            if(isset($row['No']) && $row['No'] != '') {
                // Update existing row
                $update_sql = "UPDATE $table_name SET 
                    Submission_Date = ?,
                    Share = ?,
                    Deposit = ?,
                    Land_Advance = ?,
                    Soil_Test = ?,
                    Boundary = ?,
                    Others = ?
                    WHERE No = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("sddddddi", 
                    $row['Submission_Date'],
                    $row['Share'],
                    $row['Deposit'],
                    $row['Land_Advance'],
                    $row['Soil_Test'],
                    $row['Boundary'],
                    $row['Others'],
                    $row['No']
                );
                $stmt->execute();
            } else {
                // Insert new row
                $insert_sql = "INSERT INTO $table_name 
                    (Submission_Date, Share, Deposit, Land_Advance, Soil_Test, Boundary, Others)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("sdddddd", 
                    $row['Submission_Date'],
                    $row['Share'],
                    $row['Deposit'],
                    $row['Land_Advance'],
                    $row['Soil_Test'],
                    $row['Boundary'],
                    $row['Others']
                );
                $stmt->execute();
            }
        }
    }
}

// Fetch existing data
$details_data = [];
if($table_exists) {
    $sql = "SELECT * FROM $table_name ORDER BY No ASC";
    $result = $conn->query($sql);
    while($row = $result->fetch_assoc()) {
        $details_data[] = $row;
    }
}
?>

<style>
    /* General table styling */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        background-color: white;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    th, td {
        border: 1px solid #ddd;
        padding: 12px;
        text-align: left;
    }
    th {
        background-color: #f2f2f2;
        font-weight: bold;
        color: #333;
    }
    tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    tr:hover {
        background-color: #f5f5f5;
    }

    /* Member details section */
    .member-details {
        background-color: white;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 25px;
        margin: 20px 0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .member-details h3 {
        margin-top: 0;
        color: #333;
        border-bottom: 2px solid #007bff;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
    .member-details p {
        margin: 10px 0;
        line-height: 1.6;
    }
    .member-details strong {
        color: #444;
        width: 150px;
        display: inline-block;
    }

    /* Input fields */
    input[type="date"],
    input[type="number"] {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
    }
    input[type="number"]:focus,
    input[type="date"]:focus {
        border-color: #007bff;
        outline: none;
        box-shadow: 0 0 5px rgba(0,123,255,0.2);
    }

    /* Buttons */
    .add-row {
        background-color: #f8f9fa;
        border: 2px dashed #007bff;
        color: #007bff;
        padding: 15px;
        margin: 20px 0;
        border-radius: 4px;
        cursor: pointer;
        text-align: center;
        font-size: 18px;
        transition: all 0.3s ease;
    }
    
    .column-totals {
        background-color: #f2f2f2;
        font-weight: bold;
    }
    .col-total {
        color: #28a745;
    }
    .add-row:hover {
        background-color: #e9ecef;
        transform: translateY(-1px);
    }

    .print-btn {
        float: right;
        background-color: #28a745;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        margin-bottom: 20px;
    }
    .print-btn:hover {
        background-color: #218838;
    }

    .update-btn {
        background-color: #28a745;
        color: white;
        padding: 12px 25px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        margin-top: 20px;
        transition: background-color 0.3s ease;
    }
    .update-btn:hover {
        background-color: #218838;
    }

    .back-btn {
        background-color: #007bff;
        color: white;
        padding: 10px 20px;
        text-decoration: none;
        border-radius: 4px;
        display: inline-block;
        margin-bottom: 20px;
        transition: background-color 0.3s ease;
    }
    .back-btn:hover {
        background-color: #0056b3;
        text-decoration: none;
        color: white;
    }

    .delete-btn {
        background-color: #dc3545;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        float: right;
        font-size: 16px;
        transition: background-color 0.3s ease;
    }
    .delete-btn:hover {
        background-color: #c82333;
    }

    /* Total column */
    .total {
        font-weight: bold;
        background-color: #f8f9fa;
        color: #28a745;
    }

    /* Responsive design */
    @media screen and (max-width: 1024px) {
        .member-details {
            padding: 15px;
        }
        th, td {
            padding: 8px;
        }
    }

    /* Print styles */
    @media print {
        /* Hide non-printable elements */
        .back-btn, 
        .print-btn, 
        .add-row, 
        .update-btn,
        .delete-btn {
            display: none;
        }
        .logout-btn,    /* Added this */
        .page-link {    /* Added this for links */
            display: none !important;
        }

        /* Reset styles for printing */
        body {
            padding: 0;
            margin: 0;
        }

        .member-details,
        .container {
            padding: 10px;
            margin: 0;
            box-shadow: none;
        }

        /* Table styles for print */
        table {
            width: 100%;
            page-break-inside: auto;
            border-collapse: collapse;
            box-shadow: none;
        }

        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        th {
            background-color: #f2f2f2 !important;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
        }

        td, th {
            border: 1px solid #000 !important;
            padding: 8px;
        }

        /* Header for each printed page */
        .member-details {
            position: relative;
            page-break-inside: avoid;
        }

        /* Footer for each printed page */
        @page {
            size: landscape;
            margin: 20mm;
        }

        /* Make text black for better printing */
        * {
            color: black !important;
        }

        /* Number formatting */
        td[data-value] {
            text-align: right;
        }

        /* Column totals row */
        .column-totals {
            font-weight: bold;
            background-color: #f2f2f2 !important;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
        }

        /* Remove hover effects */
        tr:hover {
            background-color: transparent;
        }

        /* Ensure input values are visible */
        input {
            border: none;
            padding: 0;
            margin: 0;
            width: auto;
        }

        input[type="date"],
        input[type="number"] {
            -webkit-appearance: none;
            margin: 0;
            padding: 0;
            border: none;
            background: transparent;
        }
        
        tfoot {
        display: table-footer-group;
        }

        /* Hide page links/URLs */
        @page {
            margin: 20mm;
            size: landscape;
        }
    
        /* Remove URLs from printing */
        a[href]:after {
            content: none !important;
        }
    
        /* Force column totals to stay together */
        .column-totals {
            break-inside: avoid;
        }
        
        /* Hide any system-generated URLs */
        a {
            text-decoration: none;
        }
    }
</style>

<a href="moderator.php?module=userDetails" class="back-btn">← Back</a>

<button class="delete-btn" onclick="confirmDelete()">Delete Member</button>

<div class="member-details">
    <h3>Member Basic Information</h3>
    <button onclick="window.print()" class="print-btn">Print</button>
    <p><strong>ID:</strong> <?php echo htmlspecialchars($member_data['id_no']); ?></p>
    <p><strong>Name:</strong> <?php echo htmlspecialchars($member_data['name']); ?></p>
    <p><strong>Designation:</strong> <?php echo htmlspecialchars($member_data['designation']); ?></p>
    <p><strong>Father's Name:</strong> <?php echo htmlspecialchars($member_data['fathers_name']); ?></p>
    <p><strong>Address:</strong> <?php echo htmlspecialchars($member_data['address']); ?></p>
    <p><strong>Mobile No:</strong> <?php echo htmlspecialchars($member_data['mobile_no']); ?></p>
    <p><strong>Admit Date:</strong> <?php echo htmlspecialchars($member_data['admit_date']); ?></p>
    <p><strong>Status:</strong> <?php echo $member_data['resign_date'] ? 'Resigned on ' . htmlspecialchars($member_data['resign_date']) : 'Active'; ?></p>
</div>

<form id="detailsForm" method="POST">
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
            <tfoot class="column-totals" style="page-break-inside: avoid;">
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
function addNewRow() {
    const tbody = document.getElementById('detailsTableBody');
    const newRow = document.createElement('tr');
    const rowIndex = 'new_' + Date.now();
    const isResigned = document.querySelector('.service-charge') !== null; // Check if member is resigned
    
    let rowHTML = `
        <td>New
            <input type="hidden" name="rows[${rowIndex}][No]" value="">
        </td>
        <td><input type="date" name="rows[${rowIndex}][Submission_Date]" required></td>
        <td><input type="number" step="0.01" name="rows[${rowIndex}][Share]" value="0.00" oninput="calculateTotal(this)"></td>
        <td><input type="number" step="0.01" name="rows[${rowIndex}][Deposit]" value="0.00" oninput="calculateTotal(this)"></td>`;
        
        if (isResigned && <?php echo $show_service_charge ? 'true' : 'false' ?>) {
            rowHTML += `
                <td class="service-charge">0.00</td>
                <td class="total-receivable">0.00</td>`;
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
   const inputs = row.querySelectorAll('input[type="number"]');
   let rowTotal = 0;
   
   const shareValue = parseFloat(row.querySelector('input[name$="[Share]"]').value) || 0;
   const depositValue = parseFloat(row.querySelector('input[name$="[Deposit]"]').value) || 0;
   const landAdvValue = parseFloat(row.querySelector('input[name$="[Land_Advance]"]').value) || 0;
   const soilTestValue = parseFloat(row.querySelector('input[name$="[Soil_Test]"]').value) || 0;
   const boundaryValue = parseFloat(row.querySelector('input[name$="[Boundary]"]').value) || 0;
   const othersValue = parseFloat(row.querySelector('input[name$="[Others]"]').value) || 0;
   
   const serviceChargeCell = row.querySelector('.service-charge');
   const totalReceivableCell = row.querySelector('.total-receivable');
   
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
    let shareTot = 0, depositTot = 0, landAdvTot = 0, 
        soilTestTot = 0, boundaryTot = 0, othersTot = 0, grandTot = 0,
        serviceChargeTot = 0, totalReceivableTot = 0;

    const rows = document.querySelectorAll('#detailsTableBody tr');
    
    rows.forEach(row => {
        shareTot += parseFloat(row.querySelector('input[name$="[Share]"]').value) || 0;
        depositTot += parseFloat(row.querySelector('input[name$="[Deposit]"]').value) || 0;
        landAdvTot += parseFloat(row.querySelector('input[name$="[Land_Advance]"]').value) || 0;
        soilTestTot += parseFloat(row.querySelector('input[name$="[Soil_Test]"]').value) || 0;
        boundaryTot += parseFloat(row.querySelector('input[name$="[Boundary]"]').value) || 0;
        othersTot += parseFloat(row.querySelector('input[name$="[Others]"]').value) || 0;
        
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
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const memberId = urlParams.get('id');
            
            // Make DELETE request as a form submission to avoid JSON parsing issues
            const response = await fetch(`moderator.php?module=memberAdvancedDetails&id=${memberId}&action=delete`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });
            
            // Check if response was successful
            if (response.ok) {
                // Redirect after successful deletion
                window.location.href = 'https://shapnachurasociety.com/moderator.php?module=userDetails&deleted=success';
            } else {
                alert('Error deleting member. Please try again.');
            }
        } catch (error) {
            alert('Error during deletion: ' + error.message);
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Calculate totals for all existing rows
    document.querySelectorAll('#detailsTableBody tr').forEach(row => {
        const firstInput = row.querySelector('input[type="number"]');
        if (firstInput) {
            calculateTotal(firstInput);
        }
    });
    calculateColumnTotals();
});
</script>