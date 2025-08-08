<?php
// === CONFIGURATION === //
$secretKey = '';  // Keep this secret
$zipFile = __DIR__ . '/../uploads/deploy_package.zip';  // Adjust as needed
$extractTo = __DIR__ . '/../deployments';               // Adjust as needed
$dirPermissions = 0755;

// === AUTHENTICATION === //
if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// === CHECK ZIP FILE EXISTS === //
if (!file_exists($zipFile)) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'ZIP file not found']);
    exit;
}

// === CREATE DEPLOYMENT DIRECTORY IF NEEDED === //
if (!is_dir($extractTo)) {
    if (!mkdir($extractTo, $dirPermissions, true)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to create deployment directory']);
        exit;
    }
}

// === EXTRACT THE ZIP === //
$zip = new ZipArchive;
if ($zip->open($zipFile) === TRUE) {
    $zip->extractTo($extractTo);
    $zip->close();

    // Optional: delete the ZIP file after deployment
    // unlink($zipFile);

    echo json_encode(['status' => 'success', 'message' => 'Deployment completed']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to open ZIP archive']);
}
