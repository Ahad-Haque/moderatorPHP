<?php
// Check if user is logged in
if (!isset($_SESSION['loggedin'])) {
    header("Location: ../moderator.php");
    exit();
}

// The module can use the $conn variable from the parent file
$sql = "SELECT * FROM memberBasicDetails ORDER BY no ASC";
$result = $conn->query($sql);
?>

<style>
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    th, td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }
    th {
        background-color: #f2f2f2;
    }
    tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    tr:hover {
        background-color: #e0e0e0;
        cursor: pointer;
    }
    .back-btn {
        padding: 8px 15px;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        margin-bottom: 20px;
    }
    .back-btn:hover {
        background-color: #0056b3;
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
        width: 100%;
    }
    .add-row:hover {
        background-color: #e9ecef;
        transform: translateY(-1px);
    }
    .action-btn {
        padding: 8px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        margin-right: 10px;
        font-size: 14px;
    }
    .add-btn {
        background-color: #28a745;
        color: white;
    }
    .add-btn:hover {
        background-color: #218838;
    }
    .cancel-btn {
        background-color: #dc3545;
        color: white;
    }
    .cancel-btn:hover {
        background-color: #c82333;
    }
    .new-row input {
        width: 100%;
        padding: 6px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    #action-buttons {
        margin-top: 10px;
        text-align: right;
    }
</style>

<a href="moderator.php" class="back-btn">← Back</a>

<h2>Member Basic Details</h2>

<table id="membersTable">
    <thead>
        <tr>
            <th>No.</th>
            <th>ID No.</th>
            <th>Name</th>
            <th>Designation</th>
            <th>Father's Name</th>
            <th>Address</th>
            <th>Mobile No.</th>
            <th>Admit Date</th>
            <th>Resign Date</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if ($result && $result->num_rows > 0) {
            $serial_number = 1;
            while($row = $result->fetch_assoc()) {
                echo "<tr onclick=\"window.location='moderator.php?module=memberAdvancedDetails&id=" . htmlspecialchars($row['id_no']) . "'\">";
                echo "<td>" . $serial_number . "."  . "</td>";
                echo "<td>" . htmlspecialchars($row['id_no']) . "</td>";
                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['designation']) . "</td>";
                echo "<td>" . htmlspecialchars($row['fathers_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['address']) . "</td>";
                echo "<td>" . htmlspecialchars($row['mobile_no']) . "</td>";
                echo "<td>" . htmlspecialchars($row['admit_date']) . "</td>";
                echo "<td>" . ($row['resign_date'] ? htmlspecialchars($row['resign_date']) : 'Active') . "</td>";
                echo "</tr>";
                $serial_number++;
            }
        } else {
            echo "<tr><td colspan='8'>No records found</td></tr>";
        }
        ?>
    </tbody>
</table>

<div class="add-row" onclick="addNewRow()">+</div>
<div id="action-buttons" style="display: none;">
    <button type="button" class="action-btn add-btn" onclick="submitNewMember()">Add</button>
    <button type="button" class="action-btn cancel-btn" onclick="cancelAdd()">Cancel</button>
</div>

<script>
let isAddingRow = false;

function addNewRow() {
    if (isAddingRow) return;
    
    isAddingRow = true;
    const tbody = document.querySelector('#membersTable tbody');
    const newRow = document.createElement('tr');
    newRow.className = 'new-row';
    
    // Remove hover effect and click events from all rows
    const allRows = document.querySelectorAll('#membersTable tr');
    allRows.forEach(row => {
        row.style.cursor = 'default';
        row.onclick = null;
    });

    newRow.innerHTML = `
        <td><input type="text" name="no" placeholder="Auto-generated" disabled></td>
        <td><input type="text" name="id_no" placeholder="MEM###" required></td>
        <td><input type="text" name="name" placeholder="Full Name" required></td>
        <td><input type="text" name="designation" placeholder="Designation" required></td>
        <td><input type="text" name="fathers_name" placeholder="Father's Name" required></td>
        <td><input type="text" name="address" placeholder="Address" required></td>
        <td><input type="text" name="mobile_no" placeholder="Mobile Number" required></td> 
        <td><input type="date" name="admit_date" required></td>
        <td><input type="date" name="resign_date"></td>
    `;

    tbody.appendChild(newRow);
    document.getElementById('action-buttons').style.display = 'block';
    document.querySelector('.add-row').style.visibility = 'hidden';
}

function submitNewMember() {
    const newRow = document.querySelector('.new-row');
    const inputs = newRow.querySelectorAll('input');
    let formData = {};
    let hasEmptyRequired = false;

    inputs.forEach(input => {
        if (input.name !== 'no') {
            formData[input.name] = input.value;
            if (input.required && !input.value) {
                hasEmptyRequired = true;
                input.style.borderColor = 'red';
            }
        }
    });

    if (hasEmptyRequired) {
        alert('Please fill in all required fields');
        return;
    }

    // Send AJAX request
    fetch('/moderatorModules/add_member.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the member');
    });
}

function cancelAdd() {
    isAddingRow = false;
    
    // Remove the new row
    const newRow = document.querySelector('.new-row');
    if (newRow) {
        newRow.remove();
    }
    
    // Hide action buttons and show add row button
    document.getElementById('action-buttons').style.display = 'none';
    document.querySelector('.add-row').style.visibility = 'visible';
    
    // Restore hover and click functionality
    const allRows = document.querySelectorAll('#membersTable tr');
    allRows.forEach(row => {
        if (!row.closest('thead')) {  // Skip header row
            row.style.cursor = 'pointer';
            const idNo = row.cells[1].textContent;
            row.onclick = function() {
                window.location = 'moderator.php?module=memberAdvancedDetails&id=' + idNo;
            };
        }
    });
}
</script>