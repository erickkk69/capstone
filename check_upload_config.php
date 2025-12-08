<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Upload Configuration Check</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #0066cc;
            padding-bottom: 10px;
        }
        .setting {
            margin: 15px 0;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #0066cc;
            border-radius: 4px;
        }
        .setting-name {
            font-weight: bold;
            color: #0066cc;
            font-size: 16px;
        }
        .setting-value {
            font-size: 18px;
            color: #333;
            margin-top: 5px;
        }
        .status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 4px;
            font-weight: bold;
            margin-left: 10px;
        }
        .status-ok {
            background: #4caf50;
            color: white;
        }
        .status-warning {
            background: #ff9800;
            color: white;
        }
        .status-error {
            background: #f44336;
            color: white;
        }
        .note {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
        }
        .delete-warning {
            background: #ffebee;
            border: 1px solid #f44336;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
            color: #c62828;
            font-weight: bold;
        }
        .recommendation {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
        }
        .recommendation h3 {
            margin-top: 0;
            color: #1976d2;
        }
        .recommendation ul {
            margin: 10px 0;
        }
        .recommendation li {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 PHP Upload Configuration Check</h1>
        <p>This diagnostic tool shows the current PHP configuration for file uploads.</p>
        
        <?php
        // Get PHP configuration values
        $uploadMaxFilesize = ini_get('upload_max_filesize');
        $postMaxSize = ini_get('post_max_size');
        $maxExecutionTime = ini_get('max_execution_time');
        $memoryLimit = ini_get('memory_limit');
        $maxFileUploads = ini_get('max_file_uploads');
        $fileUploadsEnabled = ini_get('file_uploads');
        
        // Convert to bytes for comparison
        function convertToBytes($val) {
            $val = trim($val);
            $last = strtolower($val[strlen($val)-1]);
            $val = (int)$val;
            switch($last) {
                case 'g': $val *= 1024;
                case 'm': $val *= 1024;
                case 'k': $val *= 1024;
            }
            return $val;
        }
        
        $uploadMaxBytes = convertToBytes($uploadMaxFilesize);
        $postMaxBytes = convertToBytes($postMaxSize);
        $requiredBytes = 10 * 1024 * 1024; // 10MB
        
        // Check if configuration is adequate
        $uploadOk = $uploadMaxBytes >= $requiredBytes;
        $postOk = $postMaxBytes >= $requiredBytes;
        $execOk = $maxExecutionTime >= 300 || $maxExecutionTime == 0;
        $uploadsEnabled = $fileUploadsEnabled == 1;
        
        $allOk = $uploadOk && $postOk && $execOk && $uploadsEnabled;
        ?>
        
        <div class="setting">
            <div class="setting-name">File Uploads Enabled</div>
            <div class="setting-value">
                <?php echo $fileUploadsEnabled ? 'Yes' : 'No'; ?>
                <span class="status <?php echo $uploadsEnabled ? 'status-ok' : 'status-error'; ?>">
                    <?php echo $uploadsEnabled ? '✓ OK' : '✗ DISABLED'; ?>
                </span>
            </div>
        </div>
        
        <div class="setting">
            <div class="setting-name">upload_max_filesize</div>
            <div class="setting-value">
                <?php echo $uploadMaxFilesize; ?>
                <span class="status <?php echo $uploadOk ? 'status-ok' : 'status-warning'; ?>">
                    <?php echo $uploadOk ? '✓ OK (10MB+)' : '⚠ Too Low (Need 10MB)'; ?>
                </span>
            </div>
        </div>
        
        <div class="setting">
            <div class="setting-name">post_max_size</div>
            <div class="setting-value">
                <?php echo $postMaxSize; ?>
                <span class="status <?php echo $postOk ? 'status-ok' : 'status-warning'; ?>">
                    <?php echo $postOk ? '✓ OK (10MB+)' : '⚠ Too Low (Need 12MB)'; ?>
                </span>
            </div>
        </div>
        
        <div class="setting">
            <div class="setting-name">max_execution_time</div>
            <div class="setting-value">
                <?php echo $maxExecutionTime; ?> seconds
                <span class="status <?php echo $execOk ? 'status-ok' : 'status-warning'; ?>">
                    <?php echo $execOk ? '✓ OK' : '⚠ Low (Recommend 300s)'; ?>
                </span>
            </div>
        </div>
        
        <div class="setting">
            <div class="setting-name">memory_limit</div>
            <div class="setting-value">
                <?php echo $memoryLimit; ?>
                <span class="status status-ok">ℹ Info</span>
            </div>
        </div>
        
        <div class="setting">
            <div class="setting-name">max_file_uploads</div>
            <div class="setting-value">
                <?php echo $maxFileUploads; ?>
                <span class="status status-ok">ℹ Info</span>
            </div>
        </div>
        
        <?php if ($allOk): ?>
            <div class="note" style="background: #e8f5e9; border-color: #4caf50;">
                <strong>✓ Configuration Status: GOOD</strong><br>
                Your PHP configuration is properly set for 10MB file uploads.
            </div>
        <?php else: ?>
            <div class="recommendation">
                <h3>⚠ Configuration Needs Adjustment</h3>
                <p>Your current configuration may prevent uploading files larger than a few MB. Here's what needs to be changed:</p>
                <ul>
                    <?php if (!$uploadsEnabled): ?>
                        <li><strong>Enable file_uploads</strong> in php.ini or .htaccess</li>
                    <?php endif; ?>
                    
                    <?php if (!$uploadOk): ?>
                        <li><strong>Increase upload_max_filesize</strong> to at least <strong>10M</strong> (currently: <?php echo $uploadMaxFilesize; ?>)</li>
                    <?php endif; ?>
                    
                    <?php if (!$postOk): ?>
                        <li><strong>Increase post_max_size</strong> to at least <strong>12M</strong> (currently: <?php echo $postMaxSize; ?>)</li>
                    <?php endif; ?>
                    
                    <?php if (!$execOk): ?>
                        <li><strong>Increase max_execution_time</strong> to at least <strong>300</strong> seconds (currently: <?php echo $maxExecutionTime; ?>)</li>
                    <?php endif; ?>
                </ul>
                
                <h3>How to Fix:</h3>
                <p><strong>Method 1: Using .htaccess</strong> (in your website root folder)</p>
                <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto;">
php_value upload_max_filesize 10M
php_value post_max_size 12M
php_value max_execution_time 300
php_value memory_limit 128M</pre>
                
                <p><strong>Method 2: Using php.ini</strong> (in your website root folder)</p>
                <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto;">
upload_max_filesize = 10M
post_max_size = 12M
max_execution_time = 300
memory_limit = 128M
file_uploads = On</pre>
                
                <p><strong>Method 3: Contact Hosting Support</strong></p>
                <p>If the above methods don't work, contact your hosting provider and ask them to increase these PHP limits.</p>
            </div>
        <?php endif; ?>
        
        <div class="note">
            <strong>📝 Note:</strong> Some shared hosting providers restrict PHP configuration changes. 
            If you cannot modify these settings, contact your hosting support.
        </div>
        
        <div class="delete-warning">
            <strong>🔒 SECURITY WARNING:</strong> 
            DELETE THIS FILE after checking your configuration! This file exposes PHP configuration information.
            <br><br>
            File to delete: <code><?php echo __FILE__; ?></code>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px;">
            <p>Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
            <p>PHP Version: <?php echo PHP_VERSION; ?></p>
            <p>Server Time: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
</body>
</html>
