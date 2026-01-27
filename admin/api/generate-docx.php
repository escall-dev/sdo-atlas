<?php
/**
 * PDF Generation API
 * Generates and downloads PDF files for approved requests
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/LocatorSlip.php';
require_once __DIR__ . '/../../models/AuthorityToTravel.php';
require_once __DIR__ . '/../../services/DocxGenerator.php';

$auth = auth();
$auth->requireLogin();

$type = $_GET['type'] ?? '';
$id = intval($_GET['id'] ?? 0);

if (!$type || !$id) {
    http_response_code(400);
    die('Invalid request');
}

try {
    $generator = new DocxGenerator();
    $outputFile = null;
    $filename = '';
    
    if ($type === 'ls') {
        $lsModel = new LocatorSlip();
        $data = $lsModel->getById($id);
        
        if (!$data) {
            http_response_code(404);
            die('Locator Slip not found');
        }
        
        // Check permission - users can only download their own approved requests
        if ($auth->isEmployee() && $data['user_id'] != $auth->getUserId()) {
            http_response_code(403);
            die('Access denied');
        }
        
        if ($data['status'] !== 'approved') {
            http_response_code(400);
            die('Only approved requests can be downloaded');
        }
        
        $outputFile = $generator->generateLocatorSlipPDF($data);
        $filename = 'Locator-Slip-' . $data['ls_control_no'] . '.pdf';
        
    } elseif ($type === 'at') {
        $atModel = new AuthorityToTravel();
        $data = $atModel->getById($id);
        
        if (!$data) {
            http_response_code(404);
            die('Authority to Travel not found');
        }
        
        // Check permission
        if ($auth->isEmployee() && $data['user_id'] != $auth->getUserId()) {
            http_response_code(403);
            die('Access denied');
        }
        
        if ($data['status'] !== 'approved') {
            http_response_code(400);
            die('Only approved requests can be downloaded');
        }
        
        $outputFile = $generator->generateATPDF($data);
        $filename = 'Authority-to-Travel-' . $data['at_tracking_no'] . '.pdf';
        
    } else {
        http_response_code(400);
        die('Invalid type');
    }
    
    if (!$outputFile || !file_exists($outputFile)) {
        http_response_code(500);
        die('Failed to generate document');
    }
    
    // Log the download
    $auth->logActivity('download', $type === 'ls' ? 'locator_slip' : 'authority_to_travel', $id, 'Downloaded document');
    
    // Send file for download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($outputFile));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    readfile($outputFile);
    
    // Clean up the generated file after sending
    unlink($outputFile);
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    die('Error generating document: ' . $e->getMessage());
}
