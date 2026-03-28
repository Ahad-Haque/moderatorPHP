<?php
// upload_member_image.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
header('Content-Type: application/json');

if (!extension_loaded('gd')) {
    echo json_encode(['success' => false, 'message' => 'GD extension not available']);
    exit;
}

$is_moderator = isset($_SESSION['loggedin']);
$is_member = isset($_SESSION['member_loggedin']);

if (!$is_moderator && !$is_member) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$member_id = isset($_POST['member_id']) ? trim($_POST['member_id']) : '';

if (empty($member_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing member ID']);
    exit;
}

if ($is_member && $_SESSION['member_id'] !== $member_id) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload error']);
    exit;
}

$file = $_FILES['photo'];

if ($file['size'] > 2 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large. Max 2MB']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
if (!in_array($ext, $allowed_ext)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Use JPG, PNG or WEBP']);
    exit;
}

$mime_map = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
$mime = $mime_map[$ext];

$upload_dir = __DIR__ . '/uploads/members/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

switch ($mime) {
    case 'image/jpeg': $src = imagecreatefromjpeg($file['tmp_name']); break;
    case 'image/png':  $src = imagecreatefrompng($file['tmp_name']);  break;
    case 'image/webp': $src = imagecreatefromwebp($file['tmp_name']); break;
    default: $src = false;
}

if (!$src) {
    echo json_encode(['success' => false, 'message' => 'Failed to process image']);
    exit;
}

$w = imagesx($src);
$h = imagesy($src);
$min = min($w, $h);
$x = intval(($w - $min) / 2);
$y = intval(($h - $min) / 2);

$dst = imagecreatetruecolor(300, 300);
imagecopyresampled($dst, $src, 0, 0, $x, $y, 300, 300, $min, $min);

$safe_id = strtoupper(preg_replace('/[^a-zA-Z0-9_\-]/', '', $member_id));
$filepath = $upload_dir . $safe_id . '.jpg';

if (imagejpeg($dst, $filepath, 90)) {
    imagedestroy($src);
    imagedestroy($dst);
    echo json_encode([
        'success' => true,
        'path' => 'uploads/members/' . $safe_id . '.jpg?t=' . time()
    ]);
} else {
    imagedestroy($src);
    imagedestroy($dst);
    echo json_encode(['success' => false, 'message' => 'Failed to save image']);
}