<?php
/**
 * Notification Count API
 * Returns pending request counts for dashboard badges
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/LocatorSlip.php';
require_once __DIR__ . '/../../models/AuthorityToTravel.php';

header('Content-Type: application/json');

$auth = auth();

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $response = [
        'success' => true,
        'counts' => [
            'ls_pending' => 0,
            'at_pending' => 0,
            'total_pending' => 0
        ],
        'hasNew' => false
    ];
    
    if ($auth->isApprover()) {
        $lsModel = new LocatorSlip();
        $atModel = new AuthorityToTravel();
        
        $lsStats = $lsModel->getStatistics();
        $atStats = $atModel->getStatistics();
        
        $response['counts']['ls_pending'] = $lsStats['pending'] ?? 0;
        $response['counts']['at_pending'] = $atStats['pending'] ?? 0;
        $response['counts']['total_pending'] = $response['counts']['ls_pending'] + $response['counts']['at_pending'];
        
        // Check for new requests today
        $response['counts']['today'] = ($lsStats['today'] ?? 0) + ($atStats['today'] ?? 0);
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
