<?php
// moderatorModules\allUsers.php
// Check if user is logged in
if (!isset($_SESSION['loggedin'])) {
    header("Location: ../moderator.php");
    exit();
}

require_once('moderatorModules/loadingModule.php');
insertLoadingStyles();

// Fetch all users and their latest submission dates
function getAllUsersData($conn) {
    $users_data = array();
    
    // First get all members
    $sql = "SELECT * FROM memberBasicDetails ORDER BY no ASC";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $user = $row;
            $table_name = str_replace('-', '_', $row['id_no']);
            
            // Get last entry date and totals for each user
            $detail_sql = "SELECT 
                MAX(Submission_Date) as last_entry_date,
                SUM(Share) as total_share,
                SUM(Deposit) as total_deposit,
                SUM(Land_Advance) as total_land_advance,
                SUM(Soil_Test) as total_soil_test,
                SUM(Boundary) as total_boundary,
                SUM(Others) as total_others
                FROM `$table_name`";
                
            $detail_result = $conn->query($detail_sql);
            if ($detail_result && $detail_row = $detail_result->fetch_assoc()) {
                $user = array_merge($user, $detail_row);
                
                // Calculate membership duration for resigned members
                if ($user['resign_date']) {
                    $admit_date = new DateTime($user['admit_date']);
                    $resign_date = new DateTime($user['resign_date']);
                    $interval = $admit_date->diff($resign_date);
                    $years = $interval->y;
                    
                    // Apply 20% service charge if membership < 5 years
                    if ($years < 5) {
                        $total_credit = floatval($user['total_share']) + floatval($user['total_deposit']);
                        $service_charge = $total_credit * 0.2;
                        $total_receivable = $total_credit - $service_charge;
                        
                        // Update the grand total with the deducted amount
                        $total_cost = floatval($user['total_land_advance']) + 
                                    floatval($user['total_soil_test']) + 
                                    floatval($user['total_boundary']) + 
                                    floatval($user['total_others']);
                                    
                        $user['grand_total'] = $total_receivable + $total_cost;
                    } else {
                        $user['grand_total'] = floatval($user['total_share']) + 
                                             floatval($user['total_deposit']) + 
                                             floatval($user['total_land_advance']) + 
                                             floatval($user['total_soil_test']) + 
                                             floatval($user['total_boundary']) + 
                                             floatval($user['total_others']);
                    }
                } else {
                    $user['grand_total'] = floatval($user['total_share']) + 
                                         floatval($user['total_deposit']) + 
                                         floatval($user['total_land_advance']) + 
                                         floatval($user['total_soil_test']) + 
                                         floatval($user['total_boundary']) + 
                                         floatval($user['total_others']);
                }
            }
            
            $users_data[] = $user;
        }
    }
    
    return $users_data;
}

$users_data = getAllUsersData($conn);
?>

<style>
    .container {
        padding: 20px;
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

    .main-header {
        background-color: #007bff;
        color: white;
        text-align: center;
    }

    tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    tr:hover {
        background-color: #f5f5f5;
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

    .column-total {
        font-weight: bold;
        background-color: #f2f2f2;
    }

    .grand-total {
        color: #28a745;
        font-weight: bold;
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

    @media print {
        .back-btn, .print-btn {
            display: none;
        }
        .container {
            padding: 0;
        }
        table {
            box-shadow: none;
        }
        @page {
            size: landscape;
        }
    }

    h2 {
        color: #333;
        text-align: center;
        margin-bottom: 30px;
    }

    .table-responsive {
        overflow-x: auto;
        margin-bottom: 20px;
    }

    td[data-value] {
        text-align: right;
    }

    tfoot td {
        border-top: 2px solid #007bff;
    }
</style>

<div class="container">
    <?php insertLoadingHTML(); ?>
    
    <div id="mainContent">
        <a href="moderator.php" class="back-btn">← Back</a>
        <button onclick="window.print()" class="print-btn">Print Report</button>
        <h2>All User's Information</h2>

        <div class="table-responsive">
            <table id="usersTable">
                <thead>
                    <tr>
                        <th colspan="5" class="main-header">User Details</th>
                        <th colspan="3" class="main-header">Deposit Calculation</th>
                        <th colspan="5" class="main-header">Cost Calculation</th>
                        <th rowspan="2" class="main-header">Grand Total (Deposit)</th>
                    </tr>
                    <tr>
                        <!-- User Details -->
                        <th>No.</th>
                        <th>Name</th>
                        <th>ID No.</th>
                        <th>Mobile No.</th>
                        <th>Last Entry Date</th>
                        
                        <!-- Deposit Calculation -->
                        <th>Share</th>
                        <th>Deposit</th>
                        <th>Total Credit</th>
                        
                        <!-- Cost Calculation -->
                        <th>Land Advance</th>
                        <th>Soil Test</th>
                        <th>Boundary</th>
                        <th>Others</th>
                        <th>Total Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_share = 0;
                    $total_deposit = 0;
                    $total_land_advance = 0;
                    $total_soil_test = 0;
                    $total_boundary = 0;
                    $total_others = 0;
                    $total_credit = 0;
                    $total_cost = 0;
                    $total_grand = 0;
                    $serial_number = 1;
                    
                    foreach($users_data as $row) {
                        // Calculate row totals based on resignation status and duration
                        if ($row['resign_date']) {
                            $admit_date = new DateTime($row['admit_date']);
                            $resign_date = new DateTime($row['resign_date']);
                            $interval = $admit_date->diff($resign_date);
                            $years = $interval->y;
                            
                            if ($years < 5) {
                                $total_credit_row = floatval($row['total_share']) + floatval($row['total_deposit']);
                                $service_charge = $total_credit_row * 0.2;
                                $total_credit_row = $total_credit_row - $service_charge;
                            } else {
                                $total_credit_row = floatval($row['total_share']) + floatval($row['total_deposit']);
                            }
                        } else {
                            $total_credit_row = floatval($row['total_share']) + floatval($row['total_deposit']);
                        }
                        
                        $total_cost_row = floatval($row['total_land_advance']) + 
                                          floatval($row['total_soil_test']) + 
                                          floatval($row['total_boundary']) + 
                                          floatval($row['total_others']);
                        
                        $grand_total_row = $total_credit_row + $total_cost_row;
                        
                        // Add to column totals
                        $total_share += floatval($row['total_share']);
                        $total_deposit += floatval($row['total_deposit']);
                        $total_land_advance += floatval($row['total_land_advance']);
                        $total_soil_test += floatval($row['total_soil_test']);
                        $total_boundary += floatval($row['total_boundary']);
                        $total_others += floatval($row['total_others']);
                        $total_credit += $total_credit_row;
                        $total_cost += $total_cost_row;
                        $total_grand += $grand_total_row;
                        ?>
                        <tr>
                            <td><?php echo $serial_number . '.'; ?></td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['id_no']); ?></td>
                            <td><?php echo htmlspecialchars($row['mobile_no']); ?></td>
                            <td><?php echo $row['last_entry_date'] ? htmlspecialchars($row['last_entry_date']) : 'No entries'; ?></td>
                            <td data-value="<?php echo $row['total_share']; ?>"><?php echo number_format(floatval($row['total_share']), 2); ?></td>
                            <td data-value="<?php echo $row['total_deposit']; ?>"><?php echo number_format(floatval($row['total_deposit']), 2); ?></td>
                            <td data-value="<?php echo $total_credit_row; ?>"><?php echo number_format($total_credit_row, 2); ?></td>
                            <td data-value="<?php echo $row['total_land_advance']; ?>"><?php echo number_format(floatval($row['total_land_advance']), 2); ?></td>
                            <td data-value="<?php echo $row['total_soil_test']; ?>"><?php echo number_format(floatval($row['total_soil_test']), 2); ?></td>
                            <td data-value="<?php echo $row['total_boundary']; ?>"><?php echo number_format(floatval($row['total_boundary']), 2); ?></td>
                            <td data-value="<?php echo $row['total_others']; ?>"><?php echo number_format(floatval($row['total_others']), 2); ?></td>
                            <td data-value="<?php echo $total_cost_row; ?>"><?php echo number_format($total_cost_row, 2); ?></td>
                            <td class="grand-total" data-value="<?php echo $grand_total_row; ?>"><?php echo number_format($grand_total_row, 2); ?></td>
                        </tr>
                        <?php
                        $serial_number++;
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr class="column-total">
                        <td colspan="5"><strong>Column Totals</strong></td>
                        <td><?php echo number_format($total_share, 2); ?></td>
                        <td><?php echo number_format($total_deposit, 2); ?></td>
                        <td><?php echo number_format($total_credit, 2); ?></td>
                        <td><?php echo number_format($total_land_advance, 2); ?></td>
                        <td><?php echo number_format($total_soil_test, 2); ?></td>
                        <td><?php echo number_format($total_boundary, 2); ?></td>
                        <td><?php echo number_format($total_others, 2); ?></td>
                        <td><?php echo number_format($total_cost, 2); ?></td>
                        <td class="grand-total"><?php echo number_format($total_grand, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php insertLoadingScript(); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loader = DataLoader.getInstance();
    if (loader) {
        loader.init('mainContent');
        
        loader.startLoading(
            async () => {
                // Simulate some loading time
                await new Promise(resolve => setTimeout(resolve, 500));
                const table = document.getElementById('usersTable');
                if (table) {
                    await calculateTotals();
                }
            },
            (error) => {
                console.error('Failed to load data:', error);
                alert('Failed to load user data. Please refresh the page.');
            }
        );
    }
});

async function calculateTotals() {
    try {
        const rows = document.querySelectorAll('#usersTable tbody tr');
        if (!rows.length) return;

        let totals = {
            share: 0,
            deposit: 0,
            landAdvance: 0,
            soilTest: 0,
            boundary: 0,
            others: 0,
            credit: 0,
            cost: 0,
            grand: 0
        };

        rows.forEach(row => {
            const cells = row.cells;
            if (!cells.length) return;

            const getValue = (cell) => parseFloat(cell.getAttribute('data-value')) || 0;

            totals.share += getValue(cells[5]);
            totals.deposit += getValue(cells[6]);
            totals.landAdvance += getValue(cells[8]);
            totals.soilTest += getValue(cells[9]);
            totals.boundary += getValue(cells[10]);
            totals.others += getValue(cells[11]);
            
            const creditTotal = getValue(cells[7]);
            const costTotal = getValue(cells[12]);
            const grandTotal = getValue(cells[13]);

            totals.credit += creditTotal;
            totals.cost += costTotal;
            totals.grand += grandTotal;
        });

        return totals;
    } catch (error) {
        console.error('Error in calculateTotals:', error);
        throw error;
    }
}
</script>