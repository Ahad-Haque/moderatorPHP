<?php
// moderatorModules\add_member.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin'])) {
    die(json_encode(['success' => false, 'message' => 'Not authorized']));
}

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['id_no', 'name', 'designation', 'fathers_name','mobile_no', 'address', 'admit_date'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        die(json_encode(['success' => false, 'message' => 'Missing required field: ' . $field]));
    }
}

try {
    // The module can use the $conn variable from the parent file
    if (!isset($conn)) {
        require_once('../wp-load.php');
        $conn = new mysqli('localhost', 'shapnach_ahad', 'xD9Er_S4)kIl', 'shapnach_moderator09');
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
    }

    // Prepare the SQL statement based on whether resign_date is provided
    if (empty($data['resign_date'])) {
        $sql = "INSERT INTO memberBasicDetails (id_no, name, designation, fathers_name, address, mobile_no, admit_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssss", 
            $data['id_no'],
            $data['name'],
            $data['designation'],
            $data['fathers_name'],
            $data['address'],
            $data['mobile_no'],
            $data['admit_date']
        );
    } else {
        $sql = "INSERT INTO memberBasicDetails (id_no, name, designation, fathers_name, address, mobile_no, admit_date, resign_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssss", 
            $data['id_no'],
            $data['name'],
            $data['designation'],
            $data['fathers_name'],
            $data['address'],
            $data['mobile_no'],
            $data['admit_date'],
            $data['resign_date']
        );
    }

    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add member']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Close connection if we created it in this file
if (!isset($GLOBALS['conn'])) {
    $conn->close();
}
?>