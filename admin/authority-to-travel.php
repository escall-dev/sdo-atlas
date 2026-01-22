<?php
/**
 * Authority to Travel Management Page
 * SDO ATLAS - View, create, and approve AT requests
 */

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../models/AuthorityToTravel.php';
require_once __DIR__ . '/../services/TrackingService.php';

$atModel = new AuthorityToTravel();
$trackingService = new TrackingService();

$action = $_GET['action'] ?? '';
$viewId = $_GET['view'] ?? '';
$type = $_GET['type'] ?? 'local'; // local, national, personal
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'create') {
        try {
            $category = $_POST['travel_category'];
            $scope = ($category === 'official') ? $_POST['travel_scope'] : null;
            
            $trackingNo = $trackingService->generateATNumber($category, $scope);
            
            $data = [
                'at_tracking_no' => $trackingNo,
                'employee_name' => $_POST['employee_name'],
                'employee_position' => $_POST['employee_position'],
                'permanent_station' => $_POST['permanent_station'],
                'purpose_of_travel' => $_POST['purpose_of_travel'],
                'host_of_activity' => $_POST['host_of_activity'] ?? null,
                'date_from' => $_POST['date_from'],
                'date_to' => $_POST['date_to'],
                'destination' => $_POST['destination'],
                'fund_source' => $_POST['fund_source'] ?? null,
                'inclusive_dates' => $_POST['inclusive_dates'] ?? null,
                'requesting_employee_name' => $_POST['requesting_employee_name'] ?? $_POST['employee_name'],
                'request_date' => date('Y-m-d'),
                'travel_category' => $category,
                'travel_scope' => $scope,
                'user_id' => $auth->getUserId()
            ];
            
            $id = $atModel->create($data);
            $auth->logActivity('create', 'authority_to_travel', $id, 'Created AT: ' . $trackingNo);
            
            $message = 'Authority to Travel filed successfully! Tracking Number: ' . $trackingNo;
            $action = '';
        } catch (Exception $e) {
            $error = 'Failed to create Authority to Travel: ' . $e->getMessage();
        }
    }
    
    if ($postAction === 'approve' && $auth->isApprover()) {
        $id = $_POST['id'];
        $at = $atModel->getById($id);
        
        if ($at && $at['status'] === 'pending') {
            // Expand common acronyms to full titles for approver position
            $posRaw = trim($currentUser['employee_position'] ?? '');
            $posKey = strtoupper($posRaw);
            $positionMap = [
                'ASDS' => 'Assistant Schools Division Superintendent',
                'AOV'  => 'Administrative Officer V',
                'SDS'  => 'Schools Division Superintendent',
                'SUPERADMIN' => 'Superadmin',
            ];
            $approverPosition = $positionMap[$posKey] ?? ($posRaw ?: $currentUser['role_name'] ?? '');
            
            $atModel->approve($id, $auth->getUserId(), $currentUser['full_name'], $currentUser['full_name']);
            $auth->logActivity('approve', 'authority_to_travel', $id, 'Approved AT: ' . $at['at_tracking_no']);
            $message = 'Authority to Travel approved successfully!';
        }
    }
    
    if ($postAction === 'reject' && $auth->isApprover()) {
        $id = $_POST['id'];
        $reason = $_POST['rejection_reason'] ?? null;
        $at = $atModel->getById($id);
        
        if ($at && $at['status'] === 'pending') {
            $atModel->reject($id, $auth->getUserId(), $reason);
            $auth->logActivity('reject', 'authority_to_travel', $id, 'Rejected AT: ' . $at['at_tracking_no']);
            $message = 'Authority to Travel rejected.';
        }
    }
}

// View single request
$viewData = null;
if ($viewId) {
    $viewData = $atModel->getById($viewId);
    if (!$viewData) {
        $error = 'Authority to Travel not found.';
    } elseif ($auth->isEmployee() && $viewData['user_id'] != $auth->getUserId()) {
        $error = 'You do not have permission to view this request.';
        $viewData = null;
    }
}

// Get list data
$filters = [];
if ($auth->isEmployee()) {
    $filters['user_id'] = $auth->getUserId();
}
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (!empty($_GET['category'])) {
    $filters['travel_category'] = $_GET['category'];
}
if (!empty($_GET['scope'])) {
    $filters['travel_scope'] = $_GET['scope'];
}
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = ITEMS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$requests = $atModel->getAll($filters, $perPage, $offset);
$totalRequests = $atModel->getCount($filters);
$totalPages = ceil($totalRequests / $perPage);

// Pre-fill form
$formData = [
    'employee_name' => $currentUser['full_name'],
    'employee_position' => $currentUser['employee_position'] ?? '',
    'permanent_station' => 'SDO San Pedro City'
];

// Determine default type for form
$formCategory = 'official';
$formScope = 'local';
if ($type === 'national') {
    $formScope = 'national';
} elseif ($type === 'personal') {
    $formCategory = 'personal';
    $formScope = '';
}
?>

<?php if ($message): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error">
    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<?php if ($viewData): ?>
<!-- View Single Request -->
<div class="page-header">
    <a href="<?php echo navUrl('/authority-to-travel.php'); ?>" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to List
    </a>
</div>

<div class="complaint-detail-grid">
    <div class="complaint-main">
        <!-- Reference Card -->
        <div class="detail-card ref-card">
            <div class="ref-header">
                <div class="ref-number"><?php echo htmlspecialchars($viewData['at_tracking_no']); ?></div>
                <div class="ref-date">Filed on <?php echo date('F j, Y - g:i A', strtotime($viewData['created_at'])); ?></div>
            </div>
            <div class="ref-unit">
                <?php echo getStatusBadge($viewData['status']); ?>
                <span class="unit-badge large"><?php echo AuthorityToTravel::getTypeLabel($viewData['travel_category'], $viewData['travel_scope']); ?></span>
            </div>
        </div>
        
        <!-- Request Details -->
        <div class="detail-card">
            <div class="detail-card-header">
                <h3><i class="fas fa-info-circle"></i> Travel Details</h3>
            </div>
            <div class="detail-card-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Employee Name</label>
                        <span><?php echo htmlspecialchars($viewData['employee_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Position</label>
                        <span><?php echo htmlspecialchars($viewData['employee_position'] ?: '-'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Permanent Station</label>
                        <span><?php echo htmlspecialchars($viewData['permanent_station'] ?: '-'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Destination</label>
                        <span><?php echo htmlspecialchars($viewData['destination']); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Travel Dates</label>
                        <span><?php echo date('M j, Y', strtotime($viewData['date_from'])); ?> to <?php echo date('M j, Y', strtotime($viewData['date_to'])); ?></span>
                    </div>
                    <?php if ($viewData['travel_category'] === 'official'): ?>
                    <div class="detail-item">
                        <label>Host of Activity</label>
                        <span><?php echo htmlspecialchars($viewData['host_of_activity'] ?: '-'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Fund Source</label>
                        <span><?php echo htmlspecialchars($viewData['fund_source'] ?: '-'); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <label>Purpose of Travel</label>
                        <span class="narration-text"><?php echo nl2br(htmlspecialchars($viewData['purpose_of_travel'])); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($viewData['status'] !== 'pending'): ?>
        <!-- Approval/Rejection Details -->
        <div class="detail-card">
            <div class="detail-card-header">
                <h3><i class="fas fa-<?php echo $viewData['status'] === 'approved' ? 'check-circle' : 'times-circle'; ?>"></i> 
                    <?php echo $viewData['status'] === 'approved' ? 'Approval' : 'Rejection'; ?> Details
                </h3>
            </div>
            <div class="detail-card-body">
                <div class="detail-grid">
                    <?php if ($viewData['status'] === 'approved'): ?>
                    <div class="detail-item">
                        <label>Recommending Authority</label>
                        <span><?php echo htmlspecialchars($viewData['recommending_authority_name'] ?: '-'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Approving Authority</label>
                        <span><?php echo htmlspecialchars($viewData['approving_authority_name'] ?: '-'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Approval Date</label>
                        <span><?php echo $viewData['approval_date'] ? date('F j, Y', strtotime($viewData['approval_date'])) : '-'; ?></span>
                    </div>
                    <?php else: ?>
                    <div class="detail-item">
                        <label>Rejection Reason</label>
                        <span><?php echo htmlspecialchars($viewData['rejection_reason'] ?: 'No reason provided'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="complaint-sidebar">
        <!-- Actions -->
        <?php if ($viewData['status'] === 'pending' && $auth->isApprover()): ?>
        <div class="detail-card action-card">
            <div class="detail-card-header">
                <h3><i class="fas fa-tasks"></i> Actions</h3>
            </div>
            <div class="detail-card-body">
                <button type="button" class="btn btn-success btn-block" style="margin-bottom: 10px;" onclick="openApproveModal(<?php echo $viewData['id']; ?>)">
                    <i class="fas fa-check"></i> Approve
                </button>
                
                <button type="button" class="btn btn-danger btn-block" onclick="showRejectModal(<?php echo $viewData['id']; ?>)">
                    <i class="fas fa-times"></i> Reject
                </button>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($viewData['status'] === 'approved'): ?>
        <div class="detail-card">
            <div class="detail-card-header">
                <h3><i class="fas fa-download"></i> Download</h3>
            </div>
            <div class="detail-card-body">
                <a href="<?php echo navUrl('/api/generate-docx.php?type=at&id=' . $viewData['id']); ?>" class="btn btn-primary btn-block">
                    <i class="fas fa-file-word"></i> Download DOCX
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Filed By -->
        <div class="detail-card">
            <div class="detail-card-header">
                <h3><i class="fas fa-user"></i> Filed By</h3>
            </div>
            <div class="detail-card-body">
                <div class="detail-item">
                    <label>Name</label>
                    <span><?php echo htmlspecialchars($viewData['filed_by_name']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Email</label>
                    <span><?php echo htmlspecialchars($viewData['filed_by_email']); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal-overlay" id="approveModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-check-circle" style="margin-right: 8px; color: var(--success);"></i> Approve Authority to Travel</h3>
            <button class="modal-close" type="button" onclick="closeApproveModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="_token" value="<?php echo $currentToken; ?>">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="id" id="approveId" value="">

                <p style="margin-bottom: 10px;">
                    Are you sure you want to approve this Authority to Travel?
                </p>
                <div style="padding: 12px 14px; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border-light);">
                    <div style="font-weight: 700;" id="approveTrackingNo"></div>
                    <div style="color: var(--text-muted); font-size: 0.9rem;" id="approveEmployeeName"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeApproveModal()">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Yes, approve</button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Reject Authority to Travel</h3>
            <button class="modal-close" onclick="closeRejectModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="_token" value="<?php echo $currentToken; ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="id" id="rejectId" value="">
                
                <div class="form-group">
                    <label class="form-label">Reason for Rejection (Optional)</label>
                    <textarea name="rejection_reason" class="form-control" rows="4" placeholder="Enter reason..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Reject Request</button>
            </div>
        </form>
    </div>
</div>

<script>
function openApproveModal(id) {
    document.getElementById('approveId').value = id;
    // Pull details from current page if available
    var trackingNoEl = document.querySelector('.ref-number');
    if (trackingNoEl) document.getElementById('approveTrackingNo').textContent = trackingNoEl.textContent.trim();
    // Find employee name specifically
    var nameLabel = Array.from(document.querySelectorAll('.detail-item label')).find(l => l.textContent.trim() === 'Employee Name');
    if (nameLabel && nameLabel.parentElement) {
        var span = nameLabel.parentElement.querySelector('span');
        if (span) document.getElementById('approveEmployeeName').textContent = span.textContent.trim();
    }
    document.getElementById('approveModal').classList.add('active');
}
function closeApproveModal() {
    document.getElementById('approveModal').classList.remove('active');
}

function showRejectModal(id) {
    document.getElementById('rejectId').value = id;
    document.getElementById('rejectModal').classList.add('active');
}
function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('active');
}
</script>

<?php else: ?>
<!-- List View -->
<div class="page-header">
    <div class="result-count">
        <?php echo $totalRequests; ?> Travel Request<?php echo $totalRequests !== 1 ? 's' : ''; ?>
    </div>
    <button type="button" class="btn btn-primary" onclick="openNewModal()">
        <i class="fas fa-plus"></i> New Travel Request
    </button>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <form class="filter-form" method="GET" action="">
        <input type="hidden" name="token" value="<?php echo $currentToken; ?>">
        
        <div class="filter-group">
            <label>Search</label>
            <input type="text" name="search" class="filter-input" placeholder="Tracking no, name, destination..."
                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
        </div>
        
        <div class="filter-group">
            <label>Category</label>
            <select name="category" class="filter-select">
                <option value="">All Categories</option>
                <option value="official" <?php echo ($_GET['category'] ?? '') === 'official' ? 'selected' : ''; ?>>Official</option>
                <option value="personal" <?php echo ($_GET['category'] ?? '') === 'personal' ? 'selected' : ''; ?>>Personal</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label>Scope</label>
            <select name="scope" class="filter-select">
                <option value="">All Scope</option>
                <option value="local" <?php echo ($_GET['scope'] ?? '') === 'local' ? 'selected' : ''; ?>>Local</option>
                <option value="national" <?php echo ($_GET['scope'] ?? '') === 'national' ? 'selected' : ''; ?>>National</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label>Status</label>
            <select name="status" class="filter-select">
                <option value="">All Status</option>
                <option value="pending" <?php echo ($_GET['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="approved" <?php echo ($_GET['status'] ?? '') === 'approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="rejected" <?php echo ($_GET['status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
        </div>
        
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="<?php echo navUrl('/authority-to-travel.php'); ?>" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
        </div>
    </form>
</div>

<!-- Data Table -->
<div class="data-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tracking No.</th>
                    <th>Employee</th>
                    <th>Type</th>
                    <th>Destination</th>
                    <th>Travel Dates</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <span class="empty-icon"><i class="fas fa-plane"></i></span>
                            <h3>No Travel Requests found</h3>
                            <p>Create a new Authority to Travel to get started</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($requests as $at): ?>
                <tr>
                    <td>
                        <a href="<?php echo navUrl('/authority-to-travel.php?view=' . $at['id']); ?>" class="ref-link">
                            <?php echo htmlspecialchars($at['at_tracking_no']); ?>
                        </a>
                    </td>
                    <td>
                        <div class="cell-primary"><?php echo htmlspecialchars($at['employee_name']); ?></div>
                        <div class="cell-secondary"><?php echo htmlspecialchars($at['employee_position'] ?: ''); ?></div>
                    </td>
                    <td>
                        <span class="unit-badge"><?php echo AuthorityToTravel::getTypeLabel($at['travel_category'], $at['travel_scope']); ?></span>
                    </td>
                    <td>
                        <div class="cell-primary"><?php echo htmlspecialchars($at['destination']); ?></div>
                    </td>
                    <td>
                        <div class="cell-primary"><?php echo date('M j', strtotime($at['date_from'])); ?> - <?php echo date('M j, Y', strtotime($at['date_to'])); ?></div>
                    </td>
                    <td><?php echo getStatusBadge($at['status']); ?></td>
                    <td>
                        <div class="action-buttons">
                            <a href="<?php echo navUrl('/authority-to-travel.php?view=' . $at['id']); ?>" class="btn btn-icon" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if ($at['status'] === 'approved'): ?>
                            <a href="<?php echo navUrl('/api/generate-docx.php?type=at&id=' . $at['id']); ?>" class="btn btn-icon" title="Download" style="color: var(--success);">
                                <i class="fas fa-download"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <div class="pagination-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></div>
        <div class="pagination-links">
            <?php if ($page > 1): ?>
            <a href="<?php echo navUrl('/authority-to-travel.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))); ?>" class="page-link">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <a href="<?php echo navUrl('/authority-to-travel.php?' . http_build_query(array_merge($_GET, ['page' => $i]))); ?>" 
               class="page-link <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <a href="<?php echo navUrl('/authority-to-travel.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))); ?>" class="page-link">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- New AT Modal -->
<div class="modal-overlay" id="newModal" <?php echo $action === 'new' ? 'class="active"' : ''; ?>>
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-plane"></i> New Authority to Travel</h3>
            <button class="modal-close" onclick="closeNewModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="_token" value="<?php echo $currentToken; ?>">
                <input type="hidden" name="action" value="create">
                
                <!-- Travel Type Selection -->
                <div class="form-group">
                    <label class="form-label">Travel Type <span class="required">*</span></label>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <label class="checkbox-label" style="padding: 12px 20px; border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer;">
                            <input type="radio" name="travel_category" value="official" <?php echo $formCategory === 'official' ? 'checked' : ''; ?> onchange="toggleTravelType()">
                            <span>Official</span>
                        </label>
                        <label class="checkbox-label" style="padding: 12px 20px; border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer;">
                            <input type="radio" name="travel_category" value="personal" <?php echo $formCategory === 'personal' ? 'checked' : ''; ?> onchange="toggleTravelType()">
                            <span>Personal</span>
                        </label>
                    </div>
                </div>
                
                <div class="form-group" id="scopeGroup" style="<?php echo $formCategory === 'personal' ? 'display:none;' : ''; ?>">
                    <label class="form-label">Travel Scope <span class="required">*</span></label>
                    <div style="display: flex; gap: 12px;">
                        <label class="checkbox-label" style="padding: 12px 20px; border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer;">
                            <input type="radio" name="travel_scope" value="local" <?php echo $formScope === 'local' ? 'checked' : ''; ?>>
                            <span>Local (Within Region)</span>
                        </label>
                        <label class="checkbox-label" style="padding: 12px 20px; border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer;">
                            <input type="radio" name="travel_scope" value="national" <?php echo $formScope === 'national' ? 'checked' : ''; ?>>
                            <span>National (Outside Region)</span>
                        </label>
                    </div>
                </div>
                
                <hr style="border: none; border-top: 1px solid var(--border-color); margin: 20px 0;">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Employee Name <span class="required">*</span></label>
                        <input type="text" name="employee_name" class="form-control" required
                               value="<?php echo htmlspecialchars($formData['employee_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <input type="text" name="employee_position" class="form-control"
                               value="<?php echo htmlspecialchars($formData['employee_position']); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Permanent Station</label>
                    <input type="text" name="permanent_station" class="form-control"
                           value="<?php echo htmlspecialchars($formData['permanent_station']); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Destination <span class="required">*</span></label>
                    <input type="text" name="destination" class="form-control" required
                           placeholder="Where are you traveling to?">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date From <span class="required">*</span></label>
                        <input type="date" name="date_from" class="form-control" required
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date To <span class="required">*</span></label>
                        <input type="date" name="date_to" class="form-control" required
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div id="officialFields" style="<?php echo $formCategory === 'personal' ? 'display:none;' : ''; ?>">
                    <div class="form-group">
                        <label class="form-label">Host of Activity</label>
                        <input type="text" name="host_of_activity" class="form-control"
                               placeholder="Who is hosting the activity?">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Fund Source</label>
                        <input type="text" name="fund_source" class="form-control"
                               placeholder="e.g., MOOE, Personal, Sponsor">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Purpose of Travel <span class="required">*</span></label>
                    <textarea name="purpose_of_travel" class="form-control" rows="3" required
                              placeholder="Describe the purpose of your travel..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeNewModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
function openNewModal() {
    document.getElementById('newModal').classList.add('active');
}
function closeNewModal() {
    document.getElementById('newModal').classList.remove('active');
    const url = new URL(window.location);
    url.searchParams.delete('action');
    url.searchParams.delete('type');
    window.history.replaceState({}, '', url);
}

function toggleTravelType() {
    const category = document.querySelector('input[name="travel_category"]:checked').value;
    const scopeGroup = document.getElementById('scopeGroup');
    const officialFields = document.getElementById('officialFields');
    
    if (category === 'personal') {
        scopeGroup.style.display = 'none';
        officialFields.style.display = 'none';
    } else {
        scopeGroup.style.display = 'block';
        officialFields.style.display = 'block';
    }
}

<?php if ($action === 'new'): ?>
document.addEventListener('DOMContentLoaded', openNewModal);
<?php endif; ?>
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
