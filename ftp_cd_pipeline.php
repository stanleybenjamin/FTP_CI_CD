<?php
// 1. Settings
$localFolder = '';      // Path to the folder you want to zip
$zipFilePath = '';  // Where to save the zip
$ftpServer = '';
$ftpUser = '';
$ftpPass = '';
$ftpTargetPath = ''; // Remote path on the FTP server
$deployment_key = '';

function zipAndDeploy(
    string $localFolder,
    string $zipFilePath,
    string $ftpServer,
    string $ftpUser,
    string $ftpPass,
    string $ftpTargetPath,
    string $deployUrl,
    array $ignore = []
): bool {
    // === 1. Create ZIP Archive ===
    if (!extension_loaded('zip') || !file_exists($localFolder)) {
        echo "❌ Invalid source folder.\n";
        return false;
    }

    $zip = new ZipArchive();
    if (!$zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
        echo "❌ Failed to open zip file for writing.\n";
        return false;
    }

    $source = str_replace('\\', '/', realpath($localFolder));
    $ignore = array_map(function ($pattern) {
        return str_replace('\\', '/', trim($pattern, '/'));
    }, $ignore);


    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        if (!$file->isFile()) continue;

        $filePath = str_replace('\\', '/', $file->getRealPath());
        $relativePath = ltrim(substr($filePath, strlen($source)), '/');

        // === Ignore logic using wildcard + prefix match ===
        $skip = false;
        foreach ($ignore as $pattern) {
            $pattern = str_replace('\\', '/', $pattern);

            // Support wildcards (*.log, etc.)
            if (fnmatch($pattern, $relativePath)) {
                //echo "⏩ Ignored (wildcard): $relativePath\n";
                $skip = true;
                break;
            }

            // Prefix match (folder/file starts with pattern)
            if (strpos($relativePath, $pattern) === 0) {
                //echo "⏩ Ignored (prefix): $relativePath\n";
                $skip = true;
                break;
            }
        }

        if ($skip) continue;

        $zip->addFile($filePath, $relativePath);
        //echo "✅ Added: $relativePath\n";
    }


    if ($zip->numFiles === 0) {
        $zip->close();
        unlink($zipFilePath); // Remove empty zip file
        echo "❌ No files to zip. Zip file has been deleted.\n";
        return false;
    }

    $zip->close();
    echo "✅ Zip archive created: $zipFilePath\n";

    // === 2. FTP Upload ===
    $ftp = ftp_connect($ftpServer);
    if (!$ftp) {
        echo "❌ Could not connect to FTP server: $ftpServer\n";
        return false;
    }

    if (!ftp_login($ftp, $ftpUser, $ftpPass)) {
        ftp_close($ftp);
        echo "❌ FTP login failed.\n";
        return false;
    }

    ftp_pasv($ftp, true);

    if (!ftp_put($ftp, $ftpTargetPath, $zipFilePath, FTP_BINARY)) {
        ftp_close($ftp);
        echo "❌ FTP upload failed.\n";
        return false;
    }

    ftp_close($ftp);
    echo "✅ FTP upload successful to: $ftpTargetPath\n";

    // === 3. Trigger Deployment ===
    $response = @file_get_contents($deployUrl);
    if ($response === false) {
        $error = error_get_last();
        echo "❌ Failed to trigger deployment.\n";
        echo "Error: " . ($error['message'] ?? 'No response') . "\n";
        return false;
    }

    echo "✅ Deployment triggered successfully:\n$response\n";
    return true;
}


zipAndDeploy(
    $localFolder,
    $zipFilePath,
    $ftpServer,
    $ftpUser,
    $ftpPass,
    $ftpTargetPath,
    "https://inventory.aloh.ng/deploy.php?key={$deployment_key}",
    ['.env', 'storage', 'vendor']
);
