<?php
// Start session management
session_start();

// Load WordPress environment
require_once('wp-load.php');

// Database connection
$conn = new mysqli('localhost', 'shapnach_ahad', 'xD9Er_S4)kIl', 'shapnach_moderator09');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Login
if (isset($_POST['login'])) {
    $adminID = $_POST['adminID'];
    $password = $_POST['adminPassword'];
    
    $sql = "SELECT * FROM moderatorCredential WHERE adminID = ? AND adminPassword = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $adminID, $password);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['loggedin'] = true;
        header("Location: moderator.php");
        exit();
    } else {
        $error_message = "Invalid credentials!";
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: moderator.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Moderator System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .login-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .dashboard-container {
            max-width: 800px;
            margin: 50px auto;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 10px;
            min-width: 150px;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .logout-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
        }
        .welcome-text {
            text-align: center;
            font-weight: bold;
            font-size: 24px;
            margin-bottom: 20px;
        }
        .description {
            text-align: center;
            margin-bottom: 30px;
            color: #666;
        }
        .error-message {
            color: red;
            text-align: center;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <?php if (!isset($_SESSION['loggedin'])): ?>
        <!-- Login Form -->
        <div class="login-container">
            <h1 class="welcome-text">Welcome Moderator</h1>
            <p class="description">Please log in to continue.</p>
            
            <?php if (isset($error_message)): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="adminID">ID</label>
                    <input type="text" id="adminID" name="adminID" placeholder="Enter Your ID" required>
                </div>
                
                <div class="form-group">
                    <label for="adminPassword">Password</label>
                    <input type="password" id="adminPassword" name="adminPassword" placeholder="Enter your Password" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="login" class="btn">Log In</button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <!-- Dashboard -->
        <?php if (isset($_GET['module'])): ?>
            <?php 
                switch($_GET['module']) {
                    case 'userDetails':
                        include('moderatorModules/memberBasicDetails.php');
                        break;
                    case 'memberAdvancedDetails':
                        include('moderatorModules/memberAdvancedDetails.php');
                        break;
                    case 'allUsers':
                        include('moderatorModules/allUsers.php');
                        break;
                    case 'depositCalculation':
                        include('moderatorModules/depositCalculation.php');
                        break;
                    default:
                        // Show dashboard or error message
                        break;
                }
            ?>
            <?php else: ?>
                <div class="dashboard-container">
                    <h1 class="welcome-text">Moderator Dashboard</h1>
                    <div>
                        <a href="?module=allUsers" class="btn">All Users</a>
                        <a href="?module=userDetails" class="btn">User Details</a>
                        <a href="?module=depositCalculation" class="btn">Deposit Calculation</a>
                        <a href="#" class="btn" onclick="showDevelopmentWarning()">More</a>
                    </div>
                </div>
                
                <script>
                function showDevelopmentWarning() {
                    alert('This feature is currently under development. Please check back later.');
                }
                </script>
            </div>
        <?php endif; ?>
        <a href="?logout" class="btn logout-btn">Log Out</a>
    <?php endif; ?>
</body>
</html>

<?php
$conn->close();
?>