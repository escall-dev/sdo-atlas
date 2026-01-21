<?php
/**
 * Admin Panel Configuration
 * SDO ATLAS - Schools Division Office Authority to Travel and Locator Approval System
 */

// Session configuration
define('ADMIN_SESSION_NAME', 'SDO_ATLAS_ADMIN');
define('ADMIN_SESSION_LIFETIME', 3600 * 8); // 8 hours
define('TOKEN_LIFETIME', 3600 * 8); // 8 hours for session tokens

// Admin panel settings
define('ADMIN_TITLE', 'SDO ATLAS');
define('ADMIN_FULL_TITLE', 'Schools Division Office - Authority to Travel and Locator Approval System');
define('ITEMS_PER_PAGE', 15);

// Base URL
define('BASE_URL', '/SDO-atlas');
define('ADMIN_URL', '/SDO-atlas/admin');

// Role IDs
define('ROLE_SUPERADMIN', 1);
define('ROLE_ASDS', 2);
define('ROLE_AOV', 3);
define('ROLE_USER', 4);

// Status configuration for requests
define('STATUS_CONFIG', [
    'pending' => [
        'label' => 'Pending',
        'color' => '#f59e0b',
        'bg' => '#fef3c7',
        'icon' => '<i class="fas fa-clock"></i>'
    ],
    'approved' => [
        'label' => 'Approved',
        'color' => '#10b981',
        'bg' => '#d1fae5',
        'icon' => '<i class="fas fa-check-circle"></i>'
    ],
    'rejected' => [
        'label' => 'Rejected',
        'color' => '#ef4444',
        'bg' => '#fee2e2',
        'icon' => '<i class="fas fa-times-circle"></i>'
    ]
]);

// Travel types for Locator Slip
define('TRAVEL_TYPES', [
    'official_business' => 'Official Business',
    'official_time' => 'Official Time'
]);

// Travel categories for Authority to Travel
define('AT_CATEGORIES', [
    'official' => 'Official',
    'personal' => 'Personal'
]);

// Travel scope for Authority to Travel (Official only)
define('AT_SCOPES', [
    'local' => 'Local',
    'national' => 'National'
]);

// DOCX Templates
define('DOCX_TEMPLATES', [
    'locator_slip' => 'Locator-Slip.docx',
    'at_local' => 'AUTHORITY-TO-TRAVEL-SAMPLE.docx',
    'at_national' => 'ANNEX-D-PERSONAL-TRAVEL-AUTHORITY_SAMPLE.docx',
    'at_personal' => 'ANNEX-D-PERSONAL-TRAVEL-AUTHORITY_SAMPLE.docx'
]);

// Template directory
define('TEMPLATE_DIR', __DIR__ . '/../reference-forms/doc-forms/');
define('GENERATED_DIR', __DIR__ . '/../uploads/generated/');

// Permission definitions
define('PERMISSIONS', [
    'requests.file' => 'File LS/AT requests',
    'requests.own' => 'View own requests',
    'requests.view' => 'View all requests',
    'requests.approve' => 'Approve/Reject requests',
    'users.view' => 'View users',
    'users.create' => 'Create users',
    'users.update' => 'Update users',
    'users.delete' => 'Delete users',
    'users.approve' => 'Approve user registrations',
    'logs.view' => 'View activity logs',
    'analytics.view' => 'View analytics',
    'settings.manage' => 'Manage system settings'
]);

// Offices/Units in SDO
define('SDO_OFFICES', [
    'SDS' => 'Office of the Schools Division Superintendent',
    'ASDS' => 'Office of the Assistant Schools Division Superintendent',
    'CID' => 'Curriculum Implementation Division',
    'SGOD' => 'School Governance and Operations Division',
    'Admin' => 'Administrative Division',
    'Finance' => 'Finance Division',
    'ICTO' => 'Information and Communication Technology Office',
    'Legal' => 'Legal Office',
    'Records' => 'Records Section',
    'Personnel' => 'Personnel Section',
    'Supply' => 'Supply Section',
    'Cashier' => 'Cashier Section'
]);

/**
 * Helper function to check if user is an approver (ASDS, AOV, or Superadmin)
 */
function isApprover($roleId) {
    return in_array($roleId, [ROLE_SUPERADMIN, ROLE_ASDS, ROLE_AOV]);
}

/**
 * Helper function to check if user is regular employee
 */
function isEmployee($roleId) {
    return $roleId == ROLE_USER;
}

/**
 * Helper function to get status badge HTML
 */
function getStatusBadge($status) {
    $config = STATUS_CONFIG[$status] ?? STATUS_CONFIG['pending'];
    return '<span class="status-badge status-' . $status . '">' . $config['icon'] . ' ' . $config['label'] . '</span>';
}

/**
 * Helper function to format tracking number display
 */
function formatTrackingNo($trackingNo) {
    return '<span class="tracking-no">' . htmlspecialchars($trackingNo) . '</span>';
}
