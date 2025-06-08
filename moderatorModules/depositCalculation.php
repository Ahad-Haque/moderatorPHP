<?php
// moderatorModules\depositCalculation.php
// Check if user is logged in
if (!isset($_SESSION['loggedin'])) {
    header("Location: ../moderator.php");
    exit();
}

// At the very top of your file, right after session check
if (isset($_POST['fetch_totals']) && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // Turn off output buffering and clean any existing output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Prevent any session writing
    session_write_close();
    
    // Set JSON header
    header('Content-Type: application/json');
    
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    if(empty($start_date) || empty($end_date)) {
        echo json_encode(['error' => 'Missing dates']);
        exit;
    }
    
    try {
        $totals = calculateRangeTotals($conn, $start_date, $end_date);
        error_log('Calculated totals: ' . json_encode($totals));
        echo json_encode($totals);
    } catch (Exception $e) {
        error_log('Error calculating totals: ' . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }


    exit;
}

require_once('moderatorModules/loadingModule.php');
insertLoadingStyles();

// Check and add date range columns if they don't exist
$column_check = $conn->query("SHOW COLUMNS FROM depositCalculation LIKE 'range_start'");
if($column_check->num_rows == 0) {
    $conn->query("ALTER TABLE depositCalculation ADD COLUMN range_start DATE, ADD COLUMN range_end DATE");
}
function validateDates($start_date, $end_date) {
    if (empty($start_date) || empty($end_date)) {
        throw new Exception('Missing dates');
    }
    
    // Check if dates are already in Y-m-d format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) && 
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        return [$start_date, $end_date];
    }
    
    // If not, try to convert them
    $start = date('Y-m-d', strtotime($start_date));
    $end = date('Y-m-d', strtotime($end_date));
    
    if (!$start || !$end) {
        throw new Exception('Invalid date format');
    }
    
    if (strtotime($start) > strtotime($end)) {
        throw new Exception('Start date cannot be after end date');
    }
    
    return [$start, $end];
}

// Use it in your AJAX handler:
try {
    list($start_date, $end_date) = validateDates($start_date, $end_date);
    $totals = calculateRangeTotals($conn, $start_date, $end_date);
    echo json_encode($totals);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

// Function to calculate totals for date range
function calculateRangeTotals($conn, $start_date, $end_date) {
    try {
        $total_share = 0;
        $total_deposit = 0;
        $total_service_charge = 0;

        // Get all members
        $member_sql = "SELECT * FROM memberBasicDetails";
        $member_result = $conn->query($member_sql);
        
        if(!$member_result) {
            throw new Exception("Error fetching members: " . $conn->error);
        }

        while($member = $member_result->fetch_assoc()) {
            $table_name = str_replace('-', '_', $member['id_no']);
            
            // Check if table exists
            $table_check = $conn->query("SHOW TABLES LIKE '$table_name'");
            if($table_check->num_rows == 0) {
                continue; // Skip if table doesn't exist
            }
            
            // Get totals for this member within date range
            $detail_sql = "SELECT 
                SUM(Share) as member_share,
                SUM(Deposit) as member_deposit
                FROM `$table_name`
                WHERE Submission_Date BETWEEN ? AND ?";
                
            $stmt = $conn->prepare($detail_sql);
            if(!$stmt) {
                throw new Exception("Error preparing statement: " . $conn->error);
            }
            
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
    } catch (Exception $e) {
        throw new Exception("Error calculating totals: " . $e->getMessage());
    }
}

// Handle AJAX request for date range calculations
if(isset($_POST['fetch_totals'])) {
    // Prevent any previous output
    ob_clean();
    
    header('Content-Type: application/json');
    
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    if(empty($start_date) || empty($end_date)) {
        echo json_encode(['error' => 'Missing dates']);
        exit;
    }
    
    try {
        $totals = calculateRangeTotals($conn, $start_date, $end_date);
        echo json_encode($totals);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Handle form submission
if(isset($_POST['update'])) {
    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'depositCalculation'");
    if($table_check->num_rows == 0) {
        // Create table if it doesn't exist
        $create_table_sql = "CREATE TABLE depositCalculation (
            serial_no INT AUTO_INCREMENT PRIMARY KEY,
            entry_date DATE,
            range_start DATE,
            range_end DATE,
            share DECIMAL(10,2) DEFAULT 0.00,
            deposit DECIMAL(10,2) DEFAULT 0.00,
            asholAday DECIMAL(10,2) DEFAULT 0.00,
            shudAday DECIMAL(10,2) DEFAULT 0.00,
            serviceCharge DECIMAL(10,2) DEFAULT 0.00,
            nirbachonFee DECIMAL(10,2) DEFAULT 0.00,
            vortiFee DECIMAL(10,2) DEFAULT 0.00,
            bibidhFee DECIMAL(10,2) DEFAULT 0.00,
            bankShulko DECIMAL(10,2) DEFAULT 0.00,
            ogrimDenaLbc DECIMAL(10,2) DEFAULT 0.00,
            row_total DECIMAL(10,2) GENERATED ALWAYS AS 
                (share + deposit + asholAday + shudAday + serviceCharge + 
                 nirbachonFee + vortiFee + bibidhFee + bankShulko + ogrimDenaLbc) STORED
        )";
        $conn->query($create_table_sql);
    }

    // Save date range
    if(!empty($_POST['start_date']) && !empty($_POST['end_date'])) {
        $update_range_sql = "UPDATE depositCalculation SET 
            range_start = ?, 
            range_end = ? 
            WHERE serial_no = 1";
        $stmt = $conn->prepare($update_range_sql);
        if(!$stmt) {
            $insert_range_sql = "INSERT INTO depositCalculation 
                (serial_no, range_start, range_end) VALUES (1, ?, ?)";
            $stmt = $conn->prepare($insert_range_sql);
        }
        $stmt->bind_param("ss", $_POST['start_date'], $_POST['end_date']);
        $stmt->execute();
    }

    // Handle updates for existing rows and insertion of new rows
    if(isset($_POST['rows']) && is_array($_POST['rows'])) {
        foreach($_POST['rows'] as $row) {
            if(isset($row['serial_no']) && $row['serial_no'] != '') {
                // Update existing row
                $update_sql = "UPDATE depositCalculation SET 
                    entry_date = ?,
                    share = ?,
                    deposit = ?,
                    asholAday = ?,
                    shudAday = ?,
                    serviceCharge = ?,
                    nirbachonFee = ?,
                    vortiFee = ?,
                    bibidhFee = ?,
                    bankShulko = ?,
                    ogrimDenaLbc = ?
                    WHERE serial_no = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("sddddddddddi", 
                    $row['entry_date'],
                    $row['share'],
                    $row['deposit'],
                    $row['asholAday'],
                    $row['shudAday'],
                    $row['serviceCharge'],
                    $row['nirbachonFee'],
                    $row['vortiFee'],
                    $row['bibidhFee'],
                    $row['bankShulko'],
                    $row['ogrimDenaLbc'],
                    $row['serial_no']
                );
                $stmt->execute();
            } else {
                // Insert new row
                $insert_sql = "INSERT INTO depositCalculation 
                    (entry_date, share, deposit, asholAday, shudAday, serviceCharge, 
                     nirbachonFee, vortiFee, bibidhFee, bankShulko, ogrimDenaLbc)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("sdddddddddd", 
                    $row['entry_date'],
                    $row['share'],
                    $row['deposit'],
                    $row['asholAday'],
                    $row['shudAday'],
                    $row['serviceCharge'],
                    $row['nirbachonFee'],
                    $row['vortiFee'],
                    $row['bibidhFee'],
                    $row['bankShulko'],
                    $row['ogrimDenaLbc']
                );
                $stmt->execute();
            }
        }
    }
}

// Fetch existing data including date range
$data = [];
$date_range = ['start' => '', 'end' => ''];

$range_sql = "SELECT range_start, range_end FROM depositCalculation WHERE serial_no = 1";
$range_result = $conn->query($range_sql);
if($range_result && $row = $range_result->fetch_assoc()) {
    $date_range['start'] = $row['range_start'];
    $date_range['end'] = $row['range_end'];
}

$sql = "SELECT * FROM depositCalculation ORDER BY serial_no ASC";
$result = $conn->query($sql);
if($result) {
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}
?>

<style>
    .container {
        padding: 20px;
    }
    
    #mainContent {
        display: none; /* Initially hidden */
    }
    
    .fade-in {
        animation: fadeIn 0.3s ease-in;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    h2 {
        text-align: center;
        font-weight: bold;
        color: #333;
        margin-bottom: 30px;
        font-size: 24px;  /* You can adjust this size as needed */
        padding: 10px 0;
    }
    
    .date-range {
        margin-bottom: 20px;
        display: flex;
        gap: 20px;
        align-items: center;
    }
    
    .date-range label {
        margin-right: 10px;
    }
    
    .date-range input[type="date"] {
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
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

    input[type="number"] {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
    }

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

    .add-row:hover {
        background-color: #e9ecef;
        transform: translateY(-1px);
    }

    .back-btn {
        background-color: #007bff;
        color: white;
        padding: 10px 20px;
        text-decoration: none;
        border-radius: 4px;
        display: inline-block;
        margin-bottom: 20px;
    }

    .back-btn:hover {
        background-color: #0056b3;
        text-decoration: none;
        color: white;
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
    }

    .update-btn:hover {
        background-color: #218838;
    }

    .column-totals {
        font-weight: bold;
        background-color: #f2f2f2;
    }

    .row-total {
        font-weight: bold;
        color: #28a745;
    }

    @media print {
        .back-btn, .update-btn, .add-row {
            display: none;
        }
    }
</style>

<div class="container">
    <?php insertLoadingHTML(); ?>
    
    <div id="mainContent" class="fade-in">
        <a href="moderator.php" class="back-btn">← Back</a>
        <h2>জমার খরচ</h2>

        <div class="date-range">
            <div>
                <label for="start_date">Starting Date:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo $date_range['start']; ?>">
            </div>
            <div>
                <label for="end_date">Ending Date:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo $date_range['end']; ?>">
            </div>
        </div>

        <form id="calculationForm" method="POST">
            <input type="hidden" name="start_date" id="hidden_start_date" value="<?php echo $date_range['start']; ?>">
            <input type="hidden" name="end_date" id="hidden_end_date" value="<?php echo $date_range['end']; ?>">
            
            <div class="table-responsive">
                <table id="calculationTable">
                    <thead>
                        <tr>
                            <th>ক্রমিক নং</th>
                            <th>তারিখ</th>
                            <th>শেয়ার</th>
                            <th>আমানত</th>
                            <th>আসল আদায় </th>
                            <th>সুদ আদায় </th>
                            <th>সার্ভিস চার্জ</th>
                            <th>নির্বাচন ফি</th>
                            <th>ভর্তি ফি</th>
                            <th>বিবিধ ফি</th>
                            <th>ব্যাংক শুল্ক</th>
                            <th>অগ্রিম দেনা (LBC)</th>
                            <th>মোট</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php foreach($data as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['serial_no']); ?>
                                    <input type="hidden" name="rows[<?php echo $row['serial_no']; ?>][serial_no]" value="<?php echo $row['serial_no']; ?>">
                                </td>
                                <td><input type="date" name="rows[<?php echo $row['serial_no']; ?>][entry_date]" value="<?php echo $row['entry_date']; ?>"></td>
                                <td><input type="number" step="0.01" name="rows[<?php echo $row['serial_no']; ?>][share]" value="<?php echo $row['share']; ?>" oninput="calculateTotal(this)"></td>
                                <td><input type="number" step="0.01" name="rows[<?php echo $row['serial_no']; ?>][deposit]" value="<?php echo $row['deposit']; ?>" oninput="calculateTotal(this)"></td>
                                <td><input type="number" step="0.01" name="rows[<?php echo $row['serial_no']; ?>][asholAday]" value="<?php echo $row['asholAday']; ?>" oninput="calculateTotal(this)"></td>
                                <td><input type="number" step="0.01" name="rows[<?php echo $row['serial_no']; ?>][shudAday]" value="<?php echo $row['shudAday']; ?>" oninput="calculateTotal(this)"></td>
                                <td><input type="number" step="0.01" name="rows[<?php echo $row['serial_no']; ?>][serviceCharge]" value="<?php echo $row['serviceCharge']; ?>" oninput="calculateTotal(this)"></td>
                                <td><input type="number" step="0.01" name="rows[<?php echo $row['serial_no']; ?>][nirbachonFee]" value="<?php echo $row['nirbachonFee']; ?>" oninput="calculateTotal(this)"></td>
                                <td><input type="number" step="0.01" name="rows[<?php echo $row['serial_no']; ?>][vortiFee]" value="<?php echo $row['vortiFee']; ?>" oninput="calculateTotal(this)"></td>
                                <td><input type="number" step="0.01" name="rows[<?php echo $row['serial_no']; ?>][bibidhFee]" value="<?php echo $row['bibidhFee']; ?>" oninput="calculateTotal(this)"></td>
                                <td><input type="number" step="0.01" name="rows[<?php echo $row['serial_no']; ?>][bankShulko]" value="<?php echo $row['bankShulko']; ?>" oninput="calculateTotal(this)"></td>
                                <td><input type="number" step="0.01" name="rows[<?php echo $row['serial_no']; ?>][ogrimDenaLbc]" value="<?php echo $row['ogrimDenaLbc']; ?>" oninput="calculateTotal(this)"></td>
                                <td class="row-total"><?php echo $row['row_total']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="column-totals">
                            <td colspan="2"><strong>মোট</strong></td>
                            <td id="share-total">0.00</td>
                            <td id="deposit-total">0.00</td>
                            <td id="asholAday-total">0.00</td>
                            <td id="shudAday-total">0.00</td>
                            <td id="serviceCharge-total">0.00</td>
                            <td id="nirbachonFee-total">0.00</td>
                            <td id="vortiFee-total">0.00</td>
                            <td id="bibidhFee-total">0.00</td>
                            <td id="bankShulko-total">0.00</td>
                            <td id="ogrimDenaLbc-total">0.00</td>
                            <td id="grand-total" class="row-total">0.00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="add-row" onclick="addNewRow()">+</div>
            <button type="submit" name="update" class="update-btn">Update</button>
        </form>
    </div>
</div>
<?php insertLoadingScript(); ?>

<script>
let rowCount = 0;

function handleDateChange() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    
    if(!startDate || !endDate) return;
    
    // Show loading animation
    const loader = document.getElementById('loader');
    if(loader) loader.style.display = 'flex';
    
    // Update hidden fields
    document.getElementById('hidden_start_date').value = startDate;
    document.getElementById('hidden_end_date').value = endDate;
    
    fetch('moderatorModules/fetch_totals.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'start_date': startDate,
            'end_date': endDate
        })
    })
    .then(response => response.json())
    .then(data => {
        if(data.error) {
            throw new Error(data.error);
        }
        
        console.log('Received data:', data);
        
        // Update first row or create it if it doesn't exist
        let firstRow = document.querySelector('#tableBody tr:first-child');
        if(!firstRow) {
            addNewRow();
            firstRow = document.querySelector('#tableBody tr:first-child');
        }
        
        if(firstRow) {
            // Set the entry date to current date
            const entryDate = firstRow.querySelector('input[type="date"]');
            if(entryDate) {
                entryDate.value = new Date().toISOString().split('T')[0];
            }
            
            // Update the values
            firstRow.querySelector('input[name$="[share]"]').value = parseFloat(data.total_share).toFixed(2);
            firstRow.querySelector('input[name$="[deposit]"]').value = parseFloat(data.total_deposit).toFixed(2);
            firstRow.querySelector('input[name$="[serviceCharge]"]').value = parseFloat(data.total_service_charge).toFixed(2);
            calculateTotal(firstRow.querySelector('input[name$="[share]"]'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error fetching data: ' + error.message);
    })
    .finally(() => {
        if(loader) loader.style.display = 'none';
    });
}

function addNewRow() {
    const tbody = document.getElementById('tableBody');
    const newRow = document.createElement('tr');
    rowCount++;
    
    newRow.innerHTML = `
        <td>New
            <input type="hidden" name="rows[new_${rowCount}][serial_no]" value="">
        </td>
        <td><input type="date" name="rows[new_${rowCount}][entry_date]"></td>
        <td><input type="number" step="0.01" name="rows[new_${rowCount}][share]" value="0.00" oninput="calculateTotal(this)"></td>
        <td><input type="number" step="0.01" name="rows[new_${rowCount}][deposit]" value="0.00" oninput="calculateTotal(this)"></td>
        <td><input type="number" step="0.01" name="rows[new_${rowCount}][asholAday]" value="0.00" oninput="calculateTotal(this)"></td>
        <td><input type="number" step="0.01" name="rows[new_${rowCount}][shudAday]" value="0.00" oninput="calculateTotal(this)"></td>
        <td><input type="number" step="0.01" name="rows[new_${rowCount}][serviceCharge]" value="0.00" oninput="calculateTotal(this)"></td>
        <td><input type="number" step="0.01" name="rows[new_${rowCount}][nirbachonFee]" value="0.00" oninput="calculateTotal(this)"></td>
        <td><input type="number" step="0.01" name="rows[new_${rowCount}][vortiFee]" value="0.00" oninput="calculateTotal(this)"></td>
        <td><input type="number" step="0.01" name="rows[new_${rowCount}][bibidhFee]" value="0.00" oninput="calculateTotal(this)"></td>
        <td><input type="number" step="0.01" name="rows[new_${rowCount}][bankShulko]" value="0.00" oninput="calculateTotal(this)"></td>
        <td><input type="number" step="0.01" name="rows[new_${rowCount}][ogrimDenaLbc]" value="0.00" oninput="calculateTotal(this)"></td>
        <td class="row-total">0.00</td>
    `;
    
    tbody.appendChild(newRow);
    calculateColumnTotals();
}

function calculateTotal(input) {
    const row = input.closest('tr');
    const inputs = row.querySelectorAll('input[type="number"]');
    let rowTotal = 0;
    
    inputs.forEach(input => {
        rowTotal += parseFloat(input.value) || 0;
    });
    
    row.querySelector('.row-total').textContent = rowTotal.toFixed(2);
    calculateColumnTotals();
}

function calculateColumnTotals() {
    const columns = ['share', 'deposit', 'asholAday', 'shudAday', 'serviceCharge', 
                    'nirbachonFee', 'vortiFee', 'bibidhFee', 'bankShulko', 'ogrimDenaLbc'];
    let grandTotal = 0;
    
    columns.forEach(column => {
        let columnTotal = 0;
        document.querySelectorAll(`input[name$="[${column}]"]`).forEach(input => {
            columnTotal += parseFloat(input.value) || 0;
        });
        document.getElementById(`${column}-total`).textContent = columnTotal.toFixed(2);
        grandTotal += columnTotal;
    });
    
    document.getElementById('grand-total').textContent = grandTotal.toFixed(2);
}

document.addEventListener('DOMContentLoaded', function() {
    // Hide the loader immediately when content is ready
    const loaderElement = document.getElementById('loader');
    if (loaderElement) {
        loaderElement.style.display = 'none';
    }

    // Make sure content is visible
    const mainContent = document.getElementById('mainContent');
    if (mainContent) {
        mainContent.style.display = 'block';
    }

    // Add initial row if table is empty
    if (document.getElementById('tableBody').children.length === 0) {
        addNewRow();
    }
    
    // Add event listeners to date inputs
    document.getElementById('start_date').addEventListener('change', handleDateChange);
    document.getElementById('end_date').addEventListener('change', handleDateChange);
    
    // Calculate initial totals
    calculateColumnTotals();
    
    // If both dates are already set, trigger the calculation
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    if(startDate && endDate) {
        handleDateChange();
    }
});
</script>