<?php
session_start();
// Include your database connection file (resolve relative to this file)
include_once __DIR__ . '/../db_connect.php';

header('Content-Type: application/json');

// 1. Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$upload_dir_url = 'uploads/profile_pics/'; // web-relative URL to store in DB and return to client
$upload_dir = __DIR__ . '/../' . $upload_dir_url; // filesystem path where files are written
$max_file_size = 5 * 1024 * 1024; // 5MB

// Create the upload directory if it doesn't exist (filesystem path)
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory. Check file system permissions.']);
        exit();
    }
}

// 2. Check for uploaded file
if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    $error_code = $_FILES['profile_picture']['error'] ?? 0;
    $message = 'Unknown upload error occurred.';
    // A quick way to debug common PHP upload errors
    if ($error_code == UPLOAD_ERR_INI_SIZE || $error_code == UPLOAD_ERR_FORM_SIZE) {
        $message = 'File size exceeded server limits.';
    } elseif ($error_code == UPLOAD_ERR_NO_FILE) {
        $message = 'No file was selected for upload.';
    }
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

$file = $_FILES['profile_picture'];

// 3. Basic File Validation
if ($file['size'] > $max_file_size) {
    echo json_encode(['success' => false, 'message' => 'File is too large (max 5MB).']);
    exit();
}

// Check the actual MIME type for better security
$file_info = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($file_info, $file['tmp_name']);
finfo_close($file_info);

$allowed_types = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/gif' => '.gif'];

if (!isset($allowed_types[$mime_type])) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.']);
    exit();
}

// 4. Sanitize and define target path
$extension = $allowed_types[$mime_type];
// Use the user ID to ensure a unique, predictable filename, overwriting any previous photo
$new_file_name = 'user_' . $user_id . $extension;
$target_fs_path = rtrim($upload_dir, '/\\') . DIRECTORY_SEPARATOR . $new_file_name; // filesystem path
$target_db_path = rtrim($upload_dir_url, '/\\') . '/' . $new_file_name; // web-relative path stored in DB

// 5. Move the uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_fs_path)) {
    // 6. Update the database with the new file path using PDO ($pdo provided by db_connect.php)
    try {
            $stmt = $pdo->prepare("UPDATE users SET profile_pic = :path WHERE id = :id");
            if ($stmt->execute([':path' => $target_db_path, ':id' => $user_id])) {
                echo json_encode(['success' => true, 'message' => 'Profile picture updated.', 'filePath' => $target_db_path]);
        } else {
            // If DB update fails, delete the file to prevent clutter
                if (file_exists($target_fs_path)) unlink($target_fs_path);
            $errInfo = $stmt->errorInfo();
            echo json_encode(['success' => false, 'message' => 'Database update failed.', 'error' => $errInfo]);
        }
    } catch (Exception $e) {
            if (file_exists($target_fs_path)) unlink($target_fs_path);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file. Check directory permissions.']);
}

// No explicit close needed for PDO; just exit
exit();
?>