<?php
/**
 * Unit Routing Configuration Page
 * SDO ATLAS - Superadmin only
 * Manage unit-to-approver mappings for Authority to Travel approval routing
 */

// Superadmin only - check before header.php outputs anything
require_once __DIR__ . '/../includes/auth.php';
$authCheck = auth();
$authCheck->requireLogin();

if (!$authCheck->isSuperAdmin()) {
    header('Location: 403.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin_config.php';

$db = Database::getInstance();
$message = '';
$error = '';

// Fetch master office list (active only) to keep routing aligned with user dropdowns
$offices = getSDOOfficesFromDB(true);

// Get all roles that can be approvers (unit heads)
$roles = $db->query("SELECT id, role_name, description FROM admin_roles WHERE id IN (?, ?, ?) ORDER BY id", 
    [ROLE_OSDS_CHIEF, ROLE_CID_CHIEF, ROLE_SGOD_CHIEF])->fetchAll();

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $officeId = intval($_POST['office_id'] ?? 0);
        $approverRoleId = intval($_POST['approver_role_id'] ?? 0);
        $travelScope = $_POST['travel_scope'] ?? 'all';
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $office = $officeId ? getOfficeById($officeId) : null;
        $unitName = $office['office_code'] ?? '';
        $unitDisplayName = $office['office_name'] ?? '';

        if (!$office || $unitName === '' || $unitDisplayName === '' || $approverRoleId <= 0) {
            $error = 'Please select an office/unit and an approving authority.';
        } else {
            try {
                // Check for duplicate office assignment
                $existing = $db->query("SELECT id FROM unit_routing_config WHERE (office_id = ? OR unit_name = ?)", [$officeId, $unitName])->fetch();
                if ($existing) {
                    $error = 'A routing configuration for this office/unit already exists.';
                } else {
                    $db->query("INSERT INTO unit_routing_config (unit_name, unit_display_name, office_id, approver_role_id, travel_scope, sort_order, is_active) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)", 
                        [$unitName, $unitDisplayName, $officeId, $approverRoleId, $travelScope, $sortOrder, $isActive]);
                    
                    $auth->logActivity('create_unit_routing', 'unit_routing_config', $db->lastInsertId(), 
                        'Created unit routing: ' . $unitName . ' → Role ID ' . $approverRoleId);
                    $message = 'Unit routing configuration created successfully!';
                }
            } catch (Exception $e) {
                $error = 'Failed to create unit routing configuration.';
            }
        }
    }

    if ($action === 'update' && !empty($_POST['id'])) {
        $id = intval($_POST['id']);
        $officeId = intval($_POST['office_id'] ?? 0);
        $approverRoleId = intval($_POST['approver_role_id'] ?? 0);
        $travelScope = $_POST['travel_scope'] ?? 'all';
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $office = $officeId ? getOfficeById($officeId) : null;
        $unitName = $office['office_code'] ?? '';
        $unitDisplayName = $office['office_name'] ?? '';

        if (!$office || $unitName === '' || $unitDisplayName === '' || $approverRoleId <= 0) {
            $error = 'Please select an office/unit and an approving authority.';
        } else {
            try {
                // Check for duplicate unit_name (excluding current record)
                $existing = $db->query("SELECT id FROM unit_routing_config WHERE (office_id = ? OR unit_name = ?) AND id != ?", [$officeId, $unitName, $id])->fetch();
                if ($existing) {
                    $error = 'A routing configuration for this office/unit already exists.';
                } else {
                    $db->query("UPDATE unit_routing_config 
                                SET unit_name = ?, unit_display_name = ?, office_id = ?, approver_role_id = ?, 
                                    travel_scope = ?, sort_order = ?, is_active = ?
                                WHERE id = ?", 
                        [$unitName, $unitDisplayName, $officeId, $approverRoleId, $travelScope, $sortOrder, $isActive, $id]);
                    
                    $auth->logActivity('update_unit_routing', 'unit_routing_config', $id, 
                        'Updated unit routing: ' . $unitName . ' → Role ID ' . $approverRoleId);
                    $message = 'Unit routing configuration updated successfully!';
                }
            } catch (Exception $e) {
                $error = 'Failed to update unit routing configuration.';
            }
        }
    }

    if ($action === 'delete' && !empty($_POST['id'])) {
        $id = intval($_POST['id']);
        try {
            $config = $db->query("SELECT unit_name FROM unit_routing_config WHERE id = ?", [$id])->fetch();
            $db->query("DELETE FROM unit_routing_config WHERE id = ?", [$id]);
            
            $auth->logActivity('delete_unit_routing', 'unit_routing_config', $id, 
                'Deleted unit routing: ' . ($config['unit_name'] ?? 'Unknown'));
            $message = 'Unit routing configuration deleted.';
        } catch (Exception $e) {
            $error = 'Failed to delete unit routing configuration.';
        }
    }

    if ($action === 'toggle_status' && !empty($_POST['id'])) {
        $id = intval($_POST['id']);
        try {
            $db->query("UPDATE unit_routing_config SET is_active = NOT is_active WHERE id = ?", [$id]);
            
            $auth->logActivity('toggle_unit_routing', 'unit_routing_config', $id, 'Toggled unit routing status');
            $message = 'Unit routing status updated.';
        } catch (Exception $e) {
            $error = 'Failed to update unit routing status.';
        }
    }
}

// Get all routing configurations
$routingConfigs = $db->query("SELECT urc.*, ar.role_name, ar.description as role_description, 
                      o.office_code, o.office_name
                  FROM unit_routing_config urc
                  LEFT JOIN admin_roles ar ON urc.approver_role_id = ar.id
                  LEFT JOIN sdo_offices o ON urc.office_id = o.id
                  ORDER BY urc.sort_order ASC, urc.unit_name ASC")->fetchAll();

// Group by approver role for summary display
$configsByRole = [];
foreach ($routingConfigs as $config) {
    $roleId = $config['approver_role_id'];
    if (!isset($configsByRole[$roleId])) {
        $configsByRole[$roleId] = [
            'role_name' => $config['role_name'],
            'role_description' => $config['role_description'],
            'units' => []
        ];
    }
    $configsByRole[$roleId]['units'][] = $config;
}
?>

<div class="page-header">
    <div class="header-content">
        <h2><i class="fas fa-route"></i> Unit Routing Configuration</h2>
        <p class="header-subtitle">Manage unit-to-approver mappings for Authority to Travel approval</p>
    </div>
    <div class="header-actions">
        <button class="btn btn-primary" onclick="showCreateModal()">
            <i class="fas fa-plus"></i> Add Unit Routing
        </button>
    </div>
</div>

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

<!-- Summary Cards by Approver -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <?php foreach ($configsByRole as $roleId => $roleData): ?>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #0f4c75, #1b6ca8);">
            <i class="fas fa-user-tie"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo htmlspecialchars($roleData['role_name']); ?></h3>
            <p class="stat-value"><?php echo count($roleData['units']); ?> Units</p>
            <p class="stat-label"><?php echo htmlspecialchars($roleData['role_description']); ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Routing Configuration Table -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> All Unit Routing Configurations</h3>
    </div>
    <div class="card-body">
        <?php if (empty($routingConfigs)): ?>
        <div class="empty-state">
            <i class="fas fa-route"></i>
            <p>No unit routing configurations found.</p>
            <p class="text-muted">Click "Add Unit Routing" to create the first configuration.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Unit Name</th>
                        <th>Display Name</th>
                        <th>Approving Authority</th>
                        <th>Travel Scope</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($routingConfigs as $config): ?>
                    <tr class="<?php echo $config['is_active'] ? '' : 'row-inactive'; ?>">
                        <td><?php echo $config['sort_order']; ?></td>
                        <td><strong><?php echo htmlspecialchars($config['office_code'] ?? $config['unit_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($config['office_name'] ?? $config['unit_display_name']); ?></td>
                        <td>
                            <span class="badge badge-primary">
                                <?php echo htmlspecialchars($config['role_name']); ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            $scopeLabels = ['all' => 'All Travel', 'local' => 'Local Only', 'international' => 'International Only'];
                            echo $scopeLabels[$config['travel_scope']] ?? 'All Travel';
                            ?>
                        </td>
                        <td>
                            <?php if ($config['is_active']): ?>
                            <span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Active</span>
                            <?php else: ?>
                            <span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-outline" onclick="editConfig(<?php echo htmlspecialchars(json_encode($config)); ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?php echo $config['id']; ?>">
                                    <input type="hidden" name="_token" value="<?php echo $currentToken; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline" title="<?php echo $config['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                        <i class="fas fa-<?php echo $config['is_active'] ? 'toggle-on' : 'toggle-off'; ?>"></i>
                                    </button>
                                </form>
                                <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $config['id']; ?>, '<?php echo htmlspecialchars($config['office_name'] ?? $config['unit_display_name'] ?? $config['unit_name']); ?>')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="routingModal" class="modal" style="display: none;">
    <div class="modal-overlay" onclick="closeModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle"><i class="fas fa-plus"></i> Add Unit Routing</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="routingForm">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="formId" value="">
            <input type="hidden" name="_token" value="<?php echo $currentToken; ?>">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Office / Unit <span class="required">*</span></label>
                    <select class="form-control" name="office_id" id="officeId" required>
                        <option value="">-- Select Office / Unit --</option>
                        <?php foreach ($offices as $office): ?>
                        <option value="<?php echo $office['id']; ?>">
                            <?php echo htmlspecialchars($office['office_name']); ?> (<?php echo htmlspecialchars($office['office_code']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-hint">Matches the master office list used in registration and user management.</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Approving Authority <span class="required">*</span></label>
                    <select class="form-control" name="approver_role_id" id="approverRoleId" required>
                        <option value="">-- Select Approving Authority --</option>
                        <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['id']; ?>">
                            <?php echo htmlspecialchars($role['role_name']); ?> - <?php echo htmlspecialchars($role['description']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Travel Scope</label>
                        <select class="form-control" name="travel_scope" id="travelScope">
                            <option value="all">All Travel (Local & International)</option>
                            <option value="local">Local Travel Only</option>
                            <option value="international">International Travel Only</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Sort Order</label>
                        <input type="number" class="form-control" name="sort_order" id="sortOrder" value="0" min="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" id="isActive" checked>
                        <span>Active</span>
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> Save Configuration
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal" style="display: none;">
    <div class="modal-overlay" onclick="closeDeleteModal()"></div>
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Confirm Delete</h3>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteId" value="">
            <input type="hidden" name="_token" value="<?php echo $currentToken; ?>">
            
            <div class="modal-body">
                <p>Are you sure you want to delete the routing configuration for <strong id="deleteUnitName"></strong>?</p>
                <p class="text-muted">This action cannot be undone.</p>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.row-inactive {
    opacity: 0.6;
    background: rgba(0, 0, 0, 0.05);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.badge-primary {
    background: linear-gradient(135deg, #0f4c75, #1b6ca8);
    color: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-sm {
    padding: 6px 10px;
    font-size: 0.8rem;
}

.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
}

.modal-content {
    position: relative;
    background: var(--bg-card, #111d2e);
    border-radius: 16px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    border: 1px solid var(--border, rgba(187, 225, 250, 0.1));
}

.modal-sm {
    max-width: 400px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border, rgba(187, 225, 250, 0.1));
}

.modal-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--text, #e8f1f8);
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--text-muted, #7a9bb8);
    cursor: pointer;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 24px;
    border-top: 1px solid var(--border, rgba(187, 225, 250, 0.1));
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    color: var(--text, #e8f1f8);
}

.checkbox-label input {
    width: 18px;
    height: 18px;
}

.form-hint {
    font-size: 0.75rem;
    color: var(--text-muted, #7a9bb8);
    margin-top: 4px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted, #7a9bb8);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 16px;
    opacity: 0.5;
}

.text-muted {
    color: var(--text-muted, #7a9bb8);
    font-size: 0.9rem;
}

@media (max-width: 600px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function showCreateModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus"></i> Add Unit Routing';
    document.getElementById('formAction').value = 'create';
    document.getElementById('formId').value = '';
    document.getElementById('officeId').value = '';
    document.getElementById('approverRoleId').value = '';
    document.getElementById('travelScope').value = 'all';
    document.getElementById('sortOrder').value = '0';
    document.getElementById('isActive').checked = true;
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Save Configuration';
    document.getElementById('routingModal').style.display = 'flex';
}

function editConfig(config) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Unit Routing';
    document.getElementById('formAction').value = 'update';
    document.getElementById('formId').value = config.id;
    document.getElementById('officeId').value = config.office_id || '';
    document.getElementById('approverRoleId').value = config.approver_role_id;
    document.getElementById('travelScope').value = config.travel_scope || 'all';
    document.getElementById('sortOrder').value = config.sort_order || 0;
    document.getElementById('isActive').checked = config.is_active == 1;
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Update Configuration';
    document.getElementById('routingModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('routingModal').style.display = 'none';
}

function confirmDelete(id, unitName) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteUnitName').textContent = unitName;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeDeleteModal();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
