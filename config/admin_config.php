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

// Role IDs - Aligned with SQL admin_roles
define('ROLE_SUPERADMIN', 1);  // SDS - Executive Override
define('ROLE_ASDS', 2);        // ASDS - Final Approver
define('ROLE_OSDS_CHIEF', 3);  // AO V - Recommending for OSDS units
define('ROLE_CID_CHIEF', 4);   // CID Chief - Recommending for CID
define('ROLE_SGOD_CHIEF', 5);  // SGOD Chief - Recommending for SGOD
define('ROLE_USER', 6);        // Regular Employee

// Unit Head Roles Array
define('UNIT_HEAD_ROLES', [ROLE_OSDS_CHIEF, ROLE_CID_CHIEF, ROLE_SGOD_CHIEF]);

// OSDS Units (under AO V supervision)
define('OSDS_UNITS', ['OSDS', 'Supply', 'Records', 'HR', 'Admin', 'Personnel', 'Cashier', 'Finance']);

// Role to Office Mapping for Routing
define('ROLE_OFFICE_MAP', [
    'CID' => ROLE_CID_CHIEF,
    'SGOD' => ROLE_SGOD_CHIEF,
    // OSDS units map to OSDS_CHIEF
    'OSDS' => ROLE_OSDS_CHIEF,
    'Supply' => ROLE_OSDS_CHIEF,
    'Records' => ROLE_OSDS_CHIEF,
    'HR' => ROLE_OSDS_CHIEF,
    'Admin' => ROLE_OSDS_CHIEF,
    'Personnel' => ROLE_OSDS_CHIEF,
    'Cashier' => ROLE_OSDS_CHIEF,
    'Finance' => ROLE_OSDS_CHIEF
]);

// Offices supervised by each Unit Head Role (reverse mapping)
define('UNIT_HEAD_OFFICES', [
    ROLE_CID_CHIEF => ['CID'],
    ROLE_SGOD_CHIEF => ['SGOD'],
    ROLE_OSDS_CHIEF => ['OSDS', 'Supply', 'Records', 'HR', 'Admin', 'Personnel', 'Cashier', 'Finance']
]);

// Recommending Authority Names by Role
define('RECOMMENDING_AUTHORITY_MAP', [
    ROLE_CID_CHIEF => 'CID Chief',
    ROLE_SGOD_CHIEF => 'SGOD Chief',
    ROLE_OSDS_CHIEF => 'AO V'
]);

// Approving Authority Names
define('APPROVING_AUTHORITY_MAP', [
    ROLE_ASDS => 'ASDS',
    ROLE_SUPERADMIN => 'SDS'
]);

// Status configuration for requests
define('STATUS_CONFIG', [
    'pending' => [
        'label' => 'Pending',
        'color' => '#f59e0b',
        'bg' => '#fef3c7',
        'icon' => '<i class="fas fa-clock"></i>'
    ],
    'recommended' => [
        'label' => 'Recommended',
        'color' => '#3b82f6',
        'bg' => '#dbeafe',
        'icon' => '<i class="fas fa-thumbs-up"></i>'
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
 * Helper function to check if user is a final approver (ASDS or Superadmin)
 */
function isApprover($roleId) {
    return in_array($roleId, [ROLE_SUPERADMIN, ROLE_ASDS]);
}

/**
 * Helper function to check if user is a unit head (can recommend)
 */
function isUnitHead($roleId) {
    return in_array($roleId, UNIT_HEAD_ROLES);
}

/**
 * Helper function to check if user is regular employee
 */
function isEmployee($roleId) {
    return $roleId == ROLE_USER;
}

/**
 * Helper function to determine recommending authority based on office
 */
function getRecommendingRoleForOffice($office) {
    return ROLE_OFFICE_MAP[$office] ?? ROLE_OSDS_CHIEF; // Default to OSDS Chief
}

/**
 * Helper function to get recommending authority name by role
 */
function getRecommendingAuthorityName($roleId) {
    return RECOMMENDING_AUTHORITY_MAP[$roleId] ?? null;
}

/**
 * Helper function to get approving authority name by role
 */
function getApprovingAuthorityName($roleId) {
    return APPROVING_AUTHORITY_MAP[$roleId] ?? 'ASDS';
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
