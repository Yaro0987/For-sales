<?php
session_start();

// Configuration
$PASSWORD = '00';
$BASE_DIR = realpath(__DIR__);
$MAX_FILE_SIZE = 0; // 0 means no limit

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('upload_max_filesize', '0');
ini_set('post_max_size', '0');
ini_set('max_execution_time', 0);
ini_set('max_input_time', 0);

// Authentication
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    if (isset($_POST['password'])) {
        if ($_POST['password'] === $PASSWORD) {
            $_SESSION['authenticated'] = true;
            $_SESSION['current_path'] = $BASE_DIR;
        } else {
            $error = "Invalid password!";
        }
    }
    
    if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
        displayLoginForm($error ?? '');
        exit;
    }
}

// Initialize current path
if (!isset($_SESSION['current_path'])) {
    $_SESSION['current_path'] = $BASE_DIR;
}

// Security: Prevent directory traversal
$current_path = realpath($_SESSION['current_path']);
if (!$current_path || strpos($current_path, $BASE_DIR) !== 0) {
    $current_path = $BASE_DIR;
}
$_SESSION['current_path'] = $current_path;

// Handle AJAX requests
if (isset($_POST['ajax']) || isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $response = ['success' => false];
    
    try {
        $action = $_POST['action'] ?? $_GET['action'] ?? '';
        
        switch ($action) {
            case 'get_files':
                $response = getFilesList($current_path);
                break;
            case 'create_folder':
                $response = createFolder($current_path, $_POST['name']);
                break;
            case 'rename':
                $response = renameItem($current_path, $_POST['old_name'], $_POST['new_name']);
                break;
            case 'delete':
                $response = deleteItems($current_path, json_decode($_POST['items'], true));
                break;
            case 'copy':
                $response = copyItems($current_path, json_decode($_POST['items'], true), $_POST['target']);
                break;
            case 'move':
                $response = moveItems($current_path, json_decode($_POST['items'], true), $_POST['target']);
                break;
            case 'upload':
                $response = uploadFile($current_path);
                break;
            case 'get_file_info':
                $response = getFileInfo($current_path, $_POST['path']);
                break;
            case 'edit_file':
                $response = editFile($current_path, $_POST['path'], $_POST['content']);
                break;
        }
    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// Handle navigation
if (isset($_GET['path'])) {
    $new_path = realpath($current_path . DIRECTORY_SEPARATOR . $_GET['path']);
    if ($new_path && strpos($new_path, $BASE_DIR) === 0) {
        $_SESSION['current_path'] = $new_path;
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Functions
function getFilesList($path) {
    $files = [];
    $total_size = 0;
    $total_files = 0;
    $total_folders = 0;
    
    if (!is_dir($path)) {
        throw new Exception('Directory not found');
    }
    
    if ($handle = opendir($path)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry == '.' || $entry == '..') continue;
            
            $full_path = $path . DIRECTORY_SEPARATOR . $entry;
            
            // Skip if we can't access
            if (!is_readable($full_path)) continue;
            
            $is_dir = is_dir($full_path);
            $size = $is_dir ? getFolderSize($full_path) : (file_exists($full_path) ? filesize($full_path) : 0);
            $total_size += $size;
            
            if ($is_dir) $total_folders++; else $total_files++;
            
            $files[] = [
                'name' => $entry,
                'path' => $full_path,
                'is_dir' => $is_dir,
                'size' => $size,
                'formatted_size' => formatSize($size),
                'modified' => date('Y-m-d H:i:s', filemtime($full_path)),
                'permissions' => getPermissions($full_path),
                'icon' => getFileIcon($entry, $is_dir),
                'readable' => is_readable($full_path),
                'writable' => is_writable($full_path)
            ];
        }
        closedir($handle);
    }
    
    // Sort: folders first, then files
    usort($files, function($a, $b) {
        if ($a['is_dir'] && !$b['is_dir']) return -1;
        if (!$a['is_dir'] && $b['is_dir']) return 1;
        return strcasecmp($a['name'], $b['name']);
    });
    
    return [
        'success' => true,
        'files' => $files,
        'current_path' => $path,
        'parent_path' => dirname($path),
        'stats' => [
            'total_size' => formatSize($total_size),
            'total_files' => $total_files,
            'total_folders' => $total_folders
        ]
    ];
}

function getFolderSize($path) {
    $total_size = 0;
    try {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isFile()) {
                $total_size += $file->getSize();
            }
        }
    } catch (Exception $e) {
        // Some folders might not be accessible
    }
    return $total_size;
}

function formatSize($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

function getPermissions($path) {
    return substr(sprintf('%o', fileperms($path)), -4);
}

function getFileIcon($filename, $is_dir) {
    if ($is_dir) return '📁';
    
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'php' => '🐘', 'html' => '🌐', 'htm' => '🌐', 'css' => '🎨', 'js' => '📜', 'json' => '📋',
        'txt' => '📄', 'pdf' => '📕', 'doc' => '📘', 'docx' => '📘', 'xls' => '📗', 'xlsx' => '📗',
        'zip' => '📦', 'rar' => '📦', '7z' => '📦', 'tar' => '📦', 'gz' => '📦',
        'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️', 'bmp' => '🖼️', 'svg' => '🖼️',
        'mp3' => '🎵', 'wav' => '🎵', 'ogg' => '🎵',
        'mp4' => '🎬', 'avi' => '🎬', 'mov' => '🎬', 'mkv' => '🎬', 'wmv' => '🎬',
        'exe' => '⚙️', 'msi' => '⚙️',
        'sql' => '🗃️', 'xml' => '🗃️', 'csv' => '🗃️'
    ];
    
    return $icons[$extension] ?? '📄';
}

function createFolder($path, $name) {
    $name = sanitizeFilename($name);
    $full_path = $path . DIRECTORY_SEPARATOR . $name;
    
    if (file_exists($full_path)) {
        throw new Exception('Folder already exists');
    }
    if (!mkdir($full_path, 0755, true)) {
        throw new Exception('Failed to create folder');
    }
    return ['success' => true, 'message' => 'Folder created successfully'];
}

function renameItem($path, $old_name, $new_name) {
    $old_name = sanitizeFilename($old_name);
    $new_name = sanitizeFilename($new_name);
    
    $old_path = $path . DIRECTORY_SEPARATOR . $old_name;
    $new_path = $path . DIRECTORY_SEPARATOR . $new_name;
    
    if (!file_exists($old_path)) {
        throw new Exception('File/folder not found');
    }
    if (file_exists($new_path)) {
        throw new Exception('Target name already exists');
    }
    if (!rename($old_path, $new_path)) {
        throw new Exception('Rename failed');
    }
    return ['success' => true, 'message' => 'Renamed successfully'];
}

function deleteItems($path, $items) {
    $deleted = [];
    $errors = [];
    
    foreach ($items as $item) {
        $item = sanitizeFilename($item);
        $full_path = $path . DIRECTORY_SEPARATOR . $item;
        
        if (!file_exists($full_path)) {
            $errors[] = "$item not found";
            continue;
        }
        
        try {
            if (is_dir($full_path)) {
                deleteRecursive($full_path);
            } else {
                if (!unlink($full_path)) {
                    throw new Exception("Could not delete $item");
                }
            }
            $deleted[] = $item;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
    
    $result = ['success' => true, 'message' => 'Operation completed'];
    if ($deleted) {
        $result['deleted'] = $deleted;
    }
    if ($errors) {
        $result['errors'] = $errors;
        $result['success'] = count($errors) < count($items);
    }
    
    return $result;
}

function deleteRecursive($dir) {
    if (!is_dir($dir)) return false;
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        is_dir($path) ? deleteRecursive($path) : unlink($path);
    }
    return rmdir($dir);
}

function copyItems($path, $items, $target) {
    $copied = [];
    $errors = [];
    
    foreach ($items as $item) {
        $item = sanitizeFilename($item);
        $source = $path . DIRECTORY_SEPARATOR . $item;
        $destination = $target . DIRECTORY_SEPARATOR . $item;
        
        if (!file_exists($source)) {
            $errors[] = "$item not found";
            continue;
        }
        
        try {
            if (is_dir($source)) {
                copyRecursive($source, $destination);
            } else {
                if (!copy($source, $destination)) {
                    throw new Exception("Could not copy $item");
                }
            }
            $copied[] = $item;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
    
    $result = ['success' => true, 'message' => 'Operation completed'];
    if ($copied) {
        $result['copied'] = $copied;
    }
    if ($errors) {
        $result['errors'] = $errors;
        $result['success'] = count($errors) < count($items);
    }
    
    return $result;
}

function moveItems($path, $items, $target) {
    $moved = [];
    $errors = [];
    
    foreach ($items as $item) {
        $item = sanitizeFilename($item);
        $source = $path . DIRECTORY_SEPARATOR . $item;
        $destination = $target . DIRECTORY_SEPARATOR . $item;
        
        if (!file_exists($source)) {
            $errors[] = "$item not found";
            continue;
        }
        
        try {
            if (!rename($source, $destination)) {
                throw new Exception("Could not move $item");
            }
            $moved[] = $item;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
    
    $result = ['success' => true, 'message' => 'Operation completed'];
    if ($moved) {
        $result['moved'] = $moved;
    }
    if ($errors) {
        $result['errors'] = $errors;
        $result['success'] = count($errors) < count($items);
    }
    
    return $result;
}

function copyRecursive($source, $dest) {
    if (is_dir($source)) {
        if (!file_exists($dest)) {
            mkdir($dest, 0755, true);
        }
        
        $files = array_diff(scandir($source), ['.', '..']);
        foreach ($files as $file) {
            copyRecursive($source . DIRECTORY_SEPARATOR . $file, $dest . DIRECTORY_SEPARATOR . $file);
        }
    } else {
        copy($source, $dest);
    }
}

function uploadFile($path) {
    if (!isset($_FILES['file'])) {
        throw new Exception('No file uploaded');
    }
    
    $file = $_FILES['file'];
    
    // Handle multiple files
    if (is_array($file['name'])) {
        $results = [];
        foreach ($file['name'] as $index => $name) {
            if ($file['error'][$index] !== UPLOAD_ERR_OK) {
                $results[] = ['name' => $name, 'success' => false, 'error' => getUploadError($file['error'][$index])];
                continue;
            }
            
            $target_path = $path . DIRECTORY_SEPARATOR . basename($name);
            if (move_uploaded_file($file['tmp_name'][$index], $target_path)) {
                $results[] = ['name' => $name, 'success' => true];
            } else {
                $results[] = ['name' => $name, 'success' => false, 'error' => 'Failed to move uploaded file'];
            }
        }
        return ['success' => true, 'results' => $results, 'message' => 'Upload completed'];
    } else {
        // Single file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload error: ' . getUploadError($file['error']));
        }
        
        $target_path = $path . DIRECTORY_SEPARATOR . basename($file['name']);
        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            throw new Exception('Failed to move uploaded file');
        }
        
        return ['success' => true, 'message' => 'File uploaded successfully'];
    }
}

function getUploadError($error_code) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
    ];
    return $errors[$error_code] ?? 'Unknown upload error';
}

function getFileInfo($path, $file_path) {
    if (!file_exists($file_path)) {
        throw new Exception('File not found');
    }
    
    $info = [
        'name' => basename($file_path),
        'path' => $file_path,
        'size' => formatSize(filesize($file_path)),
        'modified' => date('Y-m-d H:i:s', filemtime($file_path)),
        'created' => date('Y-m-d H:i:s', filectime($file_path)),
        'permissions' => getPermissions($file_path),
        'is_dir' => is_dir($file_path),
        'readable' => is_readable($file_path),
        'writable' => is_writable($file_path),
        'type' => mime_content_type($file_path)
    ];
    
    if (!is_dir($file_path) && in_array(pathinfo($file_path, PATHINFO_EXTENSION), ['txt', 'php', 'html', 'css', 'js', 'json', 'xml', 'csv'])) {
        $content = file_get_contents($file_path);
        if ($content !== false) {
            $info['content'] = $content;
            $info['line_count'] = count(explode("\n", $content));
        }
    }
    
    if (is_dir($file_path)) {
        $info['item_count'] = count(glob($file_path . DIRECTORY_SEPARATOR . '*'));
    }
    
    return ['success' => true, 'info' => $info];
}

function editFile($path, $file_path, $content) {
    if (!file_exists($file_path)) {
        throw new Exception('File not found');
    }
    
    if (!is_writable($file_path)) {
        throw new Exception('File is not writable');
    }
    
    if (file_put_contents($file_path, $content) === false) {
        throw new Exception('Failed to save file');
    }
    
    return ['success' => true, 'message' => 'File saved successfully'];
}

function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9\.\-\_]/', '', $filename);
}

function displayLoginForm($error = '') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Advanced File Manager - Login</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
            body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; justify-content: center; align-items: center; }
            .login-container { background: rgba(255, 255, 255, 0.95); padding: 40px; border-radius: 15px; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2); width: 100%; max-width: 400px; text-align: center; }
            .login-container h1 { color: #333; margin-bottom: 30px; font-size: 28px; }
            .form-group { margin-bottom: 20px; text-align: left; }
            .form-group label { display: block; margin-bottom: 8px; color: #555; font-weight: 500; }
            .form-group input { width: 100%; padding: 12px 15px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px; transition: all 0.3s; }
            .form-group input:focus { border-color: #667eea; outline: none; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
            .btn { width: 100%; padding: 12px; background: linear-gradient(to right, #667eea, #764ba2); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
            .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4); }
            .error { background: #ffebee; color: #c62828; padding: 10px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #c62828; }
            .loading { display: none; margin-top: 15px; }
            .spinner { border: 3px solid #f3f3f3; border-top: 3px solid #667eea; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: 0 auto; }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h1>🔐 Advanced File Manager</h1>
            <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label for="password">Enter Password:</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn" onclick="showLoading()">Access File Manager</button>
                <div class="loading" id="loading"><div class="spinner"></div><p>Authenticating...</p></div>
            </form>
        </div>
        <script>
            function showLoading() { document.getElementById('loading').style.display = 'block'; }
            document.getElementById('loginForm').addEventListener('submit', showLoading);
        </script>
    </body>
    </html>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced PHP File Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #f5f7fb;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header */
        header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header-left h1 {
            color: var(--dark);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 5px;
            font-size: 14px;
            color: #666;
        }
        
        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .header-right {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-warning { background: var(--warning); color: black; }
        .btn-info { background: var(--info); color: white; }
        .btn-light { background: var(--light); color: var(--dark); }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        /* Stats */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .stat-card i {
            font-size: 24px;
            margin-bottom: 10px;
            display: block;
        }
        
        .stat-card .number {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary);
        }
        
        .stat-card .label {
            color: #666;
            font-size: 14px;
        }
        
        /* File Manager */
        .file-manager {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .file-header {
            display: grid;
            grid-template-columns: 40px 1fr 120px 150px 100px 150px;
            gap: 15px;
            padding: 15px 20px;
            background: var(--light);
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
        }
        
        .file-item {
            display: grid;
            grid-template-columns: 40px 1fr 120px 150px 100px 150px;
            gap: 15px;
            padding: 12px 20px;
            border-bottom: 1px solid #e9ecef;
            align-items: center;
            transition: background 0.2s;
            cursor: pointer;
            user-select: none;
        }
        
        .file-item:hover {
            background: #f8f9fa;
        }
        
        .file-item.selected {
            background: #e3f2fd;
            border-left: 3px solid var(--primary);
        }
        
        .file-item:last-child {
            border-bottom: none;
        }
        
        .file-icon {
            font-size: 20px;
            text-align: center;
        }
        
        .file-name {
            font-weight: 500;
            word-break: break-all;
        }
        
        .file-size, .file-modified, .file-permissions {
            color: #666;
            font-size: 14px;
        }
        
        .file-actions {
            display: flex;
            gap: 5px;
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .file-item:hover .file-actions {
            opacity: 1;
        }
        
        .action-btn {
            padding: 5px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            opacity: 0.9;
            transform: scale(1.05);
        }
        
        /* Selection Controls */
        .selection-controls {
            background: var(--light);
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            display: none;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .selection-info {
            font-weight: 500;
            color: var(--primary);
        }
        
        /* Context Menu */
        .context-menu {
            position: fixed;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            display: none;
            min-width: 200px;
        }
        
        .context-menu-item {
            padding: 12px 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.2s;
        }
        
        .context-menu-item:hover {
            background: var(--light);
        }
        
        .context-menu-item:first-child {
            border-radius: 8px 8px 0 0;
        }
        
        .context-menu-item:last-child {
            border-radius: 0 0 8px 8px;
        }
        
        .context-menu-divider {
            height: 1px;
            background: #e9ecef;
            margin: 5px 0;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h2 {
            color: var(--dark);
        }
        
        .close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .close:hover {
            color: var(--dark);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group textarea {
            min-height: 200px;
            font-family: monospace;
            resize: vertical;
        }
        
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        /* Loading */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 3000;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        
        .loading-spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--primary);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        /* Toast */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            z-index: 4000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            max-width: 400px;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast.success { background: var(--success); }
        .toast.error { background: var(--danger); }
        .toast.info { background: var(--info); }
        
        /* Upload Progress */
        .upload-progress {
            margin: 20px 0;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--success);
            transition: width 0.3s ease;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .file-header, .file-item {
                grid-template-columns: 30px 1fr 80px;
            }
            
            .file-modified, .file-permissions {
                display: none;
            }
            
            .header-right {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-left">
                <h1><i class="fas fa-folder"></i> Advanced File Manager</h1>
                <div class="breadcrumb" id="breadcrumb">
                    <!-- Breadcrumb will be populated by JavaScript -->
                </div>
            </div>
            <div class="header-right">
                <button class="btn btn-primary" onclick="openUploadModal()">
                    <i class="fas fa-upload"></i> Upload
                </button>
                <button class="btn btn-success" onclick="openCreateFolderModal()">
                    <i class="fas fa-folder-plus"></i> New Folder
                </button>
                <button class="btn btn-info" onclick="refreshFiles()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <a href="?logout" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>
        
        <div class="stats" id="stats">
            <!-- Stats will be populated by JavaScript -->
        </div>
        
        <div class="selection-controls" id="selectionControls">
            <div class="selection-info" id="selectionInfo">0 items selected</div>
            <div class="selection-actions">
                <button class="btn btn-light" onclick="clearSelection()">
                    <i class="fas fa-times"></i> Clear
                </button>
                <button class="btn btn-danger" onclick="deleteSelected()">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
                <button class="btn btn-warning" onclick="copySelected()">
                    <i class="fas fa-copy"></i> Copy
                </button>
                <button class="btn btn-info" onclick="moveSelected()">
                    <i class="fas fa-cut"></i> Move
                </button>
            </div>
        </div>
        
        <div class="file-manager">
            <div class="file-header">
                <div></div>
                <div>Name</div>
                <div>Size</div>
                <div>Modified</div>
                <div>Permissions</div>
                <div>Actions</div>
            </div>
            <div id="fileList">
                <!-- Files will be populated by JavaScript -->
            </div>
        </div>
    </div>
    
    <!-- Context Menu -->
    <div class="context-menu" id="contextMenu">
        <div class="context-menu-item" onclick="openSelected()">
            <i class="fas fa-folder-open"></i> Open
        </div>
        <div class="context-menu-item" onclick="renameSelected()">
            <i class="fas fa-edit"></i> Rename
        </div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item" onclick="copySelected()">
            <i class="fas fa-copy"></i> Copy
        </div>
        <div class="context-menu-item" onclick="moveSelected()">
            <i class="fas fa-cut"></i> Move
        </div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item" onclick="downloadSelected()">
            <i class="fas fa-download"></i> Download
        </div>
        <div class="context-menu-item" onclick="getFileInfo()">
            <i class="fas fa-info-circle"></i> Properties
        </div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item text-danger" onclick="deleteSelected()">
            <i class="fas fa-trash"></i> Delete
        </div>
    </div>
    
    <!-- Modals -->
    <div id="createFolderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Folder</h2>
                <button class="close" onclick="closeCreateFolderModal()">&times;</button>
            </div>
            <form id="createFolderForm">
                <div class="form-group">
                    <label for="folder_name">Folder Name:</label>
                    <input type="text" name="name" id="folder_name" required placeholder="Enter folder name">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-light" onclick="closeCreateFolderModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">Create</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Upload Files</h2>
                <button class="close" onclick="closeUploadModal()">&times;</button>
            </div>
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="file">Select Files:</label>
                    <input type="file" name="file" id="file" multiple required>
                    <p style="margin-top: 5px; font-size: 14px; color: #666;">
                        No file size restrictions - supports large files
                    </p>
                </div>
                <div id="uploadProgress" style="display: none;">
                    <div class="upload-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" id="uploadProgressBar" style="width: 0%"></div>
                        </div>
                        <div id="uploadStatus">Preparing upload...</div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-light" onclick="closeUploadModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="uploadButton">Upload</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="renameModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Rename</h2>
                <button class="close" onclick="closeRenameModal()">&times;</button>
            </div>
            <form id="renameForm">
                <div class="form-group">
                    <label for="new_name">New Name:</label>
                    <input type="text" name="new_name" id="new_name" required>
                    <input type="hidden" name="old_name" id="old_name">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-light" onclick="closeRenameModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Rename</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="fileInfoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>File Properties</h2>
                <button class="close" onclick="closeFileInfoModal()">&times;</button>
            </div>
            <div id="fileInfoContent">
                <!-- File info will be populated by JavaScript -->
            </div>
        </div>
    </div>
    
    <div id="editorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit File</h2>
                <button class="close" onclick="closeEditorModal()">&times;</button>
            </div>
            <form id="editorForm">
                <div class="form-group">
                    <label for="file_content">File Content:</label>
                    <textarea name="content" id="file_content"></textarea>
                    <input type="hidden" name="path" id="file_path">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-light" onclick="closeEditorModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner"></div>
        <p id="loadingText">Processing...</p>
    </div>
    
    <!-- Toast -->
    <div id="toast" class="toast"></div>
    
    <script>
        let selectedItems = new Set();
        let currentFiles = [];
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadFiles();
            setupEventListeners();
        });
        
        function setupEventListeners() {
            // Forms
            document.getElementById('createFolderForm').addEventListener('submit', handleCreateFolder);
            document.getElementById('uploadForm').addEventListener('submit', handleUpload);
            document.getElementById('renameForm').addEventListener('submit', handleRename);
            document.getElementById('editorForm').addEventListener('submit', handleEditFile);
            
            // Context menu
            document.addEventListener('click', function() {
                hideContextMenu();
            });
        }
        
        function loadFiles() {
            showLoading('Loading files...');
            fetch('?ajax=true&action=get_files', {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayFiles(data);
                    updateBreadcrumb(data.current_path);
                    updateStats(data.stats);
                } else {
                    showToast(data.error || 'Failed to load files', 'error');
                }
            })
            .catch(error => {
                showToast('Network error: ' + error, 'error');
            })
            .finally(() => {
                hideLoading();
            });
        }
        
        function displayFiles(data) {
            currentFiles = data.files;
            const fileList = document.getElementById('fileList');
            
            if (data.files.length === 0) {
                fileList.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h3>This folder is empty</h3>
                        <p>Upload files or create a folder to get started</p>
                    </div>
                `;
                return;
            }
            
            fileList.innerHTML = data.files.map(file => `
                <div class="file-item ${selectedItems.has(file.name) ? 'selected' : ''}" 
                     data-name="${file.name}" 
                     data-path="${file.path}"
                     data-is-dir="${file.is_dir}"
                     ondblclick="openItem('${file.name}', ${file.is_dir})"
                     onclick="handleFileClick(event, '${file.name}')">
                    <div class="file-icon">${file.icon}</div>
                    <div class="file-name">${file.name}</div>
                    <div class="file-size">${file.formatted_size}</div>
                    <div class="file-modified">${file.modified}</div>
                    <div class="file-permissions">${file.permissions}</div>
                    <div class="file-actions">
                        ${!file.is_dir ? `<button class="action-btn btn-info" onclick="downloadFile('${file.name}')" title="Download">
                            <i class="fas fa-download"></i>
                        </button>` : ''}
                        ${!file.is_dir && ['txt', 'php', 'html', 'css', 'js', 'json'].includes(file.name.split('.').pop().toLowerCase()) ? 
                          `<button class="action-btn btn-warning" onclick="editFile('${file.name}')" title="Edit">
                            <i class="fas fa-edit"></i>
                          </button>` : ''}
                        <button class="action-btn btn-danger" onclick="deleteItem('${file.name}')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');
        }
        
        function updateBreadcrumb(currentPath) {
            const basePath = '<?php echo $BASE_DIR; ?>';
            const breadcrumb = document.getElementById('breadcrumb');
            const parts = currentPath.replace(basePath, '').split('/').filter(p => p);
            
            let html = '<a href="?path="><i class="fas fa-home"></i> Root</a>';
            let accumulatedPath = '';
            
            parts.forEach(part => {
                accumulatedPath += '/' + part;
                html += ` <i class="fas fa-chevron-right"></i> <a href="?path=${accumulatedPath}">${part}</a>`;
            });
            
            breadcrumb.innerHTML = html;
        }
        
        function updateStats(stats) {
            document.getElementById('stats').innerHTML = `
                <div class="stat-card">
                    <i class="fas fa-hdd text-primary"></i>
                    <div class="number">${stats.total_size}</div>
                    <div class="label">Total Size</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-file text-success"></i>
                    <div class="number">${stats.total_files}</div>
                    <div class="label">Files</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-folder text-warning"></i>
                    <div class="number">${stats.total_folders}</div>
                    <div class="label">Folders</div>
                </div>
            `;
        }
        
        function handleFileClick(event, fileName) {
            if (event.ctrlKey || event.metaKey) {
                toggleSelection(fileName);
            } else {
                if (selectedItems.size > 0 && !selectedItems.has(fileName)) {
                    clearSelection();
                }
            }
        }
        
        function toggleSelection(fileName) {
            if (selectedItems.has(fileName)) {
                selectedItems.delete(fileName);
            } else {
                selectedItems.add(fileName);
            }
            updateSelectionUI();
        }
        
        function clearSelection() {
            selectedItems.clear();
            updateSelectionUI();
        }
        
        function updateSelectionUI() {
            // Update file items
            document.querySelectorAll('.file-item').forEach(item => {
                if (selectedItems.has(item.dataset.name)) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            });
            
            // Update selection controls
            const controls = document.getElementById('selectionControls');
            const info = document.getElementById('selectionInfo');
            
            if (selectedItems.size > 0) {
                controls.style.display = 'flex';
                info.textContent = `${selectedItems.size} item${selectedItems.size > 1 ? 's' : ''} selected`;
            } else {
                controls.style.display = 'none';
            }
        }
        
        function showContextMenu(e) {
            const contextMenu = document.getElementById('contextMenu');
            contextMenu.style.display = 'block';
            contextMenu.style.left = e.pageX + 'px';
            contextMenu.style.top = e.pageY + 'px';
            e.preventDefault();
        }
        
        function hideContextMenu() {
            document.getElementById('contextMenu').style.display = 'none';
        }
        
        // File operations
        function openItem(name, isDir) {
            if (isDir) {
                window.location.href = `?path=${encodeURIComponent(name)}`;
            } else {
                window.open(name, '_blank');
            }
        }
        
        function openSelected() {
            if (selectedItems.size === 1) {
                const item = currentFiles.find(f => f.name === Array.from(selectedItems)[0]);
                if (item) {
                    openItem(item.name, item.is_dir);
                }
            }
        }
        
        function downloadFile(name) {
            window.open(name, '_blank');
        }
        
        function downloadSelected() {
            selectedItems.forEach(name => {
                if (!currentFiles.find(f => f.name === name)?.is_dir) {
                    downloadFile(name);
                }
            });
        }
        
        function deleteItem(name) {
            if (confirm(`Are you sure you want to delete "${name}"?`)) {
                performAction('delete', [name]);
            }
        }
        
        function deleteSelected() {
            if (selectedItems.size === 0) return;
            
            const itemList = Array.from(selectedItems).join(', ');
            if (confirm(`Are you sure you want to delete ${selectedItems.size} item(s)?\n\n${itemList}`)) {
                performAction('delete', Array.from(selectedItems));
            }
        }
        
        function renameSelected() {
            if (selectedItems.size === 1) {
                const oldName = Array.from(selectedItems)[0];
                document.getElementById('old_name').value = oldName;
                document.getElementById('new_name').value = oldName;
                openRenameModal();
            }
        }
        
        function copySelected() {
            if (selectedItems.size > 0) {
                // For simplicity, copy to current directory with _copy suffix
                const items = Array.from(selectedItems);
                const target = '<?php echo $current_path; ?>';
                performAction('copy', items, target);
            }
        }
        
        function moveSelected() {
            if (selectedItems.size > 0) {
                // For simplicity, implement basic move
                showToast('Move functionality would be implemented here', 'info');
            }
        }
        
        function getFileInfo() {
            if (selectedItems.size === 1) {
                const name = Array.from(selectedItems)[0];
                const file = currentFiles.find(f => f.name === name);
                
                showLoading('Loading file info...');
                fetch('?', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `ajax=true&action=get_file_info&path=${encodeURIComponent(file.path)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayFileInfo(data.info);
                    } else {
                        showToast(data.error, 'error');
                    }
                })
                .catch(error => {
                    showToast('Network error', 'error');
                })
                .finally(() => {
                    hideLoading();
                });
            }
        }
        
        function editFile(name) {
            const file = currentFiles.find(f => f.name === name);
            if (file && !file.is_dir) {
                showLoading('Loading file...');
                fetch('?', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `ajax=true&action=get_file_info&path=${encodeURIComponent(file.path)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.info.content !== undefined) {
                        document.getElementById('file_path').value = file.path;
                        document.getElementById('file_content').value = data.info.content;
                        openEditorModal();
                    } else {
                        showToast('Cannot edit this file type', 'error');
                    }
                })
                .catch(error => {
                    showToast('Network error', 'error');
                })
                .finally(() => {
                    hideLoading();
                });
            }
        }
        
        // Modal functions
        function openCreateFolderModal() {
            document.getElementById('createFolderModal').style.display = 'flex';
            document.getElementById('folder_name').focus();
        }
        
        function closeCreateFolderModal() {
            document.getElementById('createFolderModal').style.display = 'none';
            document.getElementById('createFolderForm').reset();
        }
        
        function openUploadModal() {
            document.getElementById('uploadModal').style.display = 'flex';
            document.getElementById('uploadProgress').style.display = 'none';
        }
        
        function closeUploadModal() {
            document.getElementById('uploadModal').style.display = 'none';
            document.getElementById('uploadForm').reset();
        }
        
        function openRenameModal() {
            document.getElementById('renameModal').style.display = 'flex';
            document.getElementById('new_name').focus();
            document.getElementById('new_name').select();
        }
        
        function closeRenameModal() {
            document.getElementById('renameModal').style.display = 'none';
            document.getElementById('renameForm').reset();
        }
        
        function openEditorModal() {
            document.getElementById('editorModal').style.display = 'flex';
        }
        
        function closeEditorModal() {
            document.getElementById('editorModal').style.display = 'none';
        }
        
        function openFileInfoModal() {
            document.getElementById('fileInfoModal').style.display = 'flex';
        }
        
        function closeFileInfoModal() {
            document.getElementById('fileInfoModal').style.display = 'none';
        }
        
        // Form handlers
        function handleCreateFolder(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            showLoading('Creating folder...');
            fetch('?', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax=true&action=create_folder&name=${encodeURIComponent(formData.get('name'))}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeCreateFolderModal();
                    loadFiles();
                } else {
                    showToast(data.error, 'error');
                }
            })
            .catch(error => {
                showToast('Network error', 'error');
            })
            .finally(() => {
                hideLoading();
            });
        }
        
        function handleUpload(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('ajax', 'true');
            formData.append('action', 'upload');
            
            // Show progress
            document.getElementById('uploadProgress').style.display = 'block';
            document.getElementById('uploadButton').disabled = true;
            
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    document.getElementById('uploadProgressBar').style.width = percentComplete + '%';
                    document.getElementById('uploadStatus').textContent = `Uploading: ${Math.round(percentComplete)}%`;
                }
            });
            
            xhr.addEventListener('load', () => {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        showToast('Files uploaded successfully', 'success');
                        closeUploadModal();
                        loadFiles();
                    } else {
                        showToast(response.error || 'Upload failed', 'error');
                    }
                } catch (e) {
                    showToast('Upload failed', 'error');
                }
                document.getElementById('uploadButton').disabled = false;
            });
            
            xhr.addEventListener('error', () => {
                showToast('Network error during upload', 'error');
                document.getElementById('uploadButton').disabled = false;
            });
            
            xhr.open('POST', window.location.href);
            xhr.send(formData);
        }
        
        function handleRename(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            showLoading('Renaming...');
            fetch('?', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax=true&action=rename&old_name=${encodeURIComponent(formData.get('old_name'))}&new_name=${encodeURIComponent(formData.get('new_name'))}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeRenameModal();
                    loadFiles();
                    clearSelection();
                } else {
                    showToast(data.error, 'error');
                }
            })
            .catch(error => {
                showToast('Network error', 'error');
            })
            .finally(() => {
                hideLoading();
            });
        }
        
        function handleEditFile(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            showLoading('Saving file...');
            fetch('?', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax=true&action=edit_file&path=${encodeURIComponent(formData.get('path'))}&content=${encodeURIComponent(formData.get('content'))}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeEditorModal();
                } else {
                    showToast(data.error, 'error');
                }
            })
            .catch(error => {
                showToast('Network error', 'error');
            })
            .finally(() => {
                hideLoading();
            });
        }
        
        function performAction(action, items, target = null) {
            showLoading('Processing...');
            const params = new URLSearchParams({
                ajax: 'true',
                action: action,
                items: JSON.stringify(items)
            });
            
            if (target) {
                params.append('target', target);
            }
            
            fetch('?', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    loadFiles();
                    clearSelection();
                } else {
                    showToast(data.error, 'error');
                }
            })
            .catch(error => {
                showToast('Network error', 'error');
            })
            .finally(() => {
                hideLoading();
            });
        }
        
        function displayFileInfo(info) {
            const content = document.getElementById('fileInfoContent');
            content.innerHTML = `
                <div class="form-group">
                    <label>Name:</label>
                    <input type="text" value="${info.name}" readonly>
                </div>
                <div class="form-group">
                    <label>Path:</label>
                    <input type="text" value="${info.path}" readonly>
                </div>
                <div class="form-group">
                    <label>Size:</label>
                    <input type="text" value="${info.size}" readonly>
                </div>
                <div class="form-group">
                    <label>Last Modified:</label>
                    <input type="text" value="${info.modified}" readonly>
                </div>
                <div class="form-group">
                    <label>Created:</label>
                    <input type="text" value="${info.created}" readonly>
                </div>
                <div class="form-group">
                    <label>Permissions:</label>
                    <input type="text" value="${info.permissions}" readonly>
                </div>
                <div class="form-group">
                    <label>Type:</label>
                    <input type="text" value="${info.type}" readonly>
                </div>
                <div class="form-group">
                    <label>Readable:</label>
                    <input type="text" value="${info.readable ? 'Yes' : 'No'}" readonly>
                </div>
                <div class="form-group">
                    <label>Writable:</label>
                    <input type="text" value="${info.writable ? 'Yes' : 'No'}" readonly>
                </div>
                ${info.is_dir ? `
                <div class="form-group">
                    <label>Items Count:</label>
                    <input type="text" value="${info.item_count}" readonly>
                </div>
                ` : ''}
                ${info.line_count ? `
                <div class="form-group">
                    <label>Lines:</label>
                    <input type="text" value="${info.line_count}" readonly>
                </div>
                ` : ''}
            `;
            openFileInfoModal();
        }
        
        function refreshFiles() {
            loadFiles();
            showToast('Files refreshed', 'info');
        }
        
        // UI helpers
        function showLoading(message = 'Processing...') {
            document.getElementById('loadingText').textContent = message;
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        function showToast(message, type) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = `toast ${type} show`;
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
        
        // Global click handler for modals
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let modal of modals) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>