<?php
/**
 * Password Reset Management Page
 * SDO ATLAS - Superadmin only
 * 
 * View and manage forgot password rate limits per user.
 * Reset blocked users' request limits.
 */

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Auth check before header (for redirect)
require_once __DIR__ . '/../includes/auth.php';
$authCheck = auth();
$authCheck->requireLogin();

if (!$authCheck->isSuperAdmin()) {
    header('Location: ' . ADMIN_URL . '/');
    exit;
}

require_once __DIR__ . '/../models/PasswordReset.php';
$resetModel = new PasswordReset();
$message = '';
$error = '';

// Handle POST actions before header output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $targetUserId = intval($_POST['user_id'] ?? 0);

    if ($targetUserId > 0) {
        $actionMessages = [
            'reset_limit'        => ['method' => 'adminResetLimit',           'log' => 'Superadmin reset forgot password rate limit',           'msg' => 'Password reset rate limit has been cleared.'],
            'reset_resend'       => ['method' => 'adminResetResendLimit',     'log' => 'Superadmin reset OTP resend limit',                     'msg' => 'OTP resend limit has been reset.'],
            'reset_otp_input'    => ['method' => 'adminResetOTPInputAttempts','log' => 'Superadmin reset OTP input attempts',                   'msg' => 'OTP input attempts have been reset.'],
            'reset_verification' => ['method' => 'adminResetVerificationCount','log' => 'Superadmin reset verification access count',           'msg' => 'Verification access count has been reset.'],
        ];

        if (isset($actionMessages[$action])) {
            $cfg = $actionMessages[$action];
            $resetModel->{$cfg['method']}($targetUserId);
            $authCheck->logActivity($action, 'user', $targetUserId, $cfg['log'] . ' for user ID ' . $targetUserId);
            $message = $cfg['msg'];
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
// Get filters
$filters = [];
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}
if (isset($_GET['blocked']) && $_GET['blocked'] !== '') {
    $filters['blocked'] = $_GET['blocked'];
}

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = ITEMS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$attempts = $resetModel->getAllAttempts($filters, $perPage, $offset);
$totalAttempts = $resetModel->getAttemptsCount($filters);
$totalPages = ceil($totalAttempts / $perPage);
$stats = $resetModel->getAttemptStats();
?>

<?php if ($message): ?>
<div class="alert alert-success" style="display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #6ee7b7;">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger" style="display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5;">
    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 24px;">
    <?php
    $statCards = [
        ['icon' => 'fa-users',       'color' => '#3b82f6', 'value' => (int)($stats['total_users_with_attempts'] ?? 0), 'label' => 'Users with Requests'],
        ['icon' => 'fa-ban',         'color' => '#ef4444', 'value' => (int)($stats['blocked_users'] ?? 0),               'label' => 'Blocked Users'],
        ['icon' => 'fa-check-circle','color' => '#10b981', 'value' => (int)($stats['active_users'] ?? 0),                'label' => 'Active (Under Limit)'],
        ['icon' => 'fa-envelope',    'color' => '#f59e0b', 'value' => (int)($stats['total_attempts'] ?? 0),              'label' => 'Total OTP Requests'],
        ['icon' => 'fa-eye',         'color' => '#8b5cf6', 'value' => (int)($stats['total_verification_accesses'] ?? 0), 'label' => 'Page Accesses'],
        ['icon' => 'fa-redo',        'color' => '#06b6d4', 'value' => (int)($stats['total_resends'] ?? 0),               'label' => 'Total Resends'],
    ];
    foreach ($statCards as $sc): ?>
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 18px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 38px; height: 38px; border-radius: 10px; background: <?php echo $sc['color']; ?>18; display: flex; align-items: center; justify-content: center;">
                <i class="fas <?php echo $sc['icon']; ?>" style="color: <?php echo $sc['color']; ?>; font-size: 1rem;"></i>
            </div>
            <div>
                <div style="font-size: 1.4rem; font-weight: 700; color: var(--text-primary);"><?php echo $sc['value']; ?></div>
                <div style="font-size: 0.73rem; color: var(--text-muted);"><?php echo $sc['label']; ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter Bar -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 16px 20px; margin-bottom: 20px;">
    <form method="GET" action="" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <?php if (!empty($currentToken)): ?>
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($currentToken); ?>">
        <?php endif; ?>
        <div style="flex: 1; min-width: 200px;">
            <input type="text" name="search" class="form-control" placeholder="Search by name or email..." 
                   value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>"
                   style="width: 100%; padding: 9px 14px; font-size: 0.88rem;">
        </div>
        <div style="min-width: 160px;">
            <select name="blocked" class="form-control" style="padding: 9px 14px; font-size: 0.88rem;">
                <option value="">All Status</option>
                <option value="1" <?php echo (isset($filters['blocked']) && $filters['blocked'] === '1') ? 'selected' : ''; ?>>Blocked</option>
                <option value="0" <?php echo (isset($filters['blocked']) && $filters['blocked'] === '0') ? 'selected' : ''; ?>>Active</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="padding: 9px 18px; font-size: 0.88rem;">
            <i class="fas fa-search"></i> Filter
        </button>
        <a href="<?php echo navUrl('/password-resets.php'); ?>" class="btn btn-outline" style="padding: 9px 18px; font-size: 0.88rem;">
            <i class="fas fa-times"></i> Clear
        </a>
    </form>
</div>

<!-- Table -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; overflow: hidden;">
    <div style="padding: 16px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin: 0; font-size: 1rem; color: var(--text-primary);">
            <i class="fas fa-key" style="color: var(--primary); margin-right: 8px;"></i>
            Password Reset Rate Limits
        </h3>
        <span style="font-size: 0.82rem; color: var(--text-muted);">
            Showing <?php echo count($attempts); ?> of <?php echo $totalAttempts; ?> records
        </span>
    </div>

    <?php if (empty($attempts)): ?>
    <div style="padding: 60px 20px; text-align: center;">
        <i class="fas fa-shield-alt" style="font-size: 2.5rem; color: var(--text-muted); margin-bottom: 12px;"></i>
        <p style="color: var(--text-muted); font-size: 0.9rem;">No password reset requests found.</p>
    </div>
    <?php else: ?>
    <div style="overflow-x: auto;">
        <table class="data-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: rgba(0,0,0,0.15);">
                    <?php
                    $headers = ['User','Role','Page Visits','OTP Requests','OTP Input','Resends','Status','Last Activity','Actions'];
                    foreach ($headers as $h): ?>
                    <th style="padding: 11px 12px; text-align: <?php echo in_array($h,['User','Role','Last Activity']) ? 'left' : 'center'; ?>; font-size: 0.72rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.4px; white-space: nowrap;"><?php echo $h; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attempts as $a): ?>
                <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.15s;">
                    <!-- User -->
                    <td style="padding: 12px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 32px; height: 32px; border-radius: 50%; background: <?php echo $a['is_blocked'] ? 'rgba(239,68,68,0.15)' : 'rgba(59,130,246,0.15)'; ?>; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.78rem; color: <?php echo $a['is_blocked'] ? '#ef4444' : '#3b82f6'; ?>; flex-shrink: 0;">
                                <?php echo strtoupper(substr($a['full_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <div style="font-weight: 600; font-size: 0.84rem; color: var(--text-primary);"><?php echo htmlspecialchars($a['full_name']); ?></div>
                                <div style="font-size: 0.72rem; color: var(--text-muted);"><?php echo htmlspecialchars($a['email']); ?></div>
                            </div>
                        </div>
                    </td>
                    <!-- Role -->
                    <td style="padding: 12px;"><span style="font-size: 0.78rem; color: var(--text-muted);"><?php echo htmlspecialchars($a['role_name']); ?></span></td>
                    <!-- Page Visits -->
                    <td style="padding: 12px; text-align: center;">
                        <span style="font-weight: 600; font-size: 0.92rem; color: #8b5cf6;"><?php echo (int)$a['verification_access_count']; ?></span>
                    </td>
                    <!-- OTP Requests (attempt_count / 3) -->
                    <td style="padding: 12px; text-align: center;">
                        <div style="display: flex; align-items: center; justify-content: center; gap: 3px;">
                            <span style="font-weight: 700; font-size: 0.92rem; color: <?php echo $a['attempt_count'] >= 3 ? '#ef4444' : ($a['attempt_count'] >= 2 ? '#f59e0b' : 'var(--text-primary)'); ?>;"><?php echo (int)$a['attempt_count']; ?></span>
                            <span style="font-size: 0.72rem; color: var(--text-muted);">/ 3</span>
                        </div>
                        <div style="width: 50px; height: 3px; background: rgba(255,255,255,0.08); border-radius: 2px; margin: 3px auto 0; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo min(100, ($a['attempt_count'] / 3) * 100); ?>%; background: <?php echo $a['attempt_count'] >= 3 ? '#ef4444' : ($a['attempt_count'] >= 2 ? '#f59e0b' : '#10b981'); ?>; border-radius: 2px;"></div>
                        </div>
                    </td>
                    <!-- OTP Input Attempts -->
                    <td style="padding: 12px; text-align: center;">
                        <div style="display: flex; align-items: center; justify-content: center; gap: 3px;">
                            <span style="font-weight: 600; font-size: 0.92rem; color: <?php echo $a['otp_input_attempts'] >= 5 ? '#ef4444' : ($a['otp_input_attempts'] >= 4 ? '#f59e0b' : 'var(--text-primary)'); ?>;"><?php echo (int)$a['otp_input_attempts']; ?></span>
                            <span style="font-size: 0.72rem; color: var(--text-muted);">/ 5</span>
                        </div>
                    </td>
                    <!-- Resends -->
                    <td style="padding: 12px; text-align: center;">
                        <div style="display: flex; align-items: center; justify-content: center; gap: 3px;">
                            <span style="font-weight: 600; font-size: 0.92rem; color: <?php echo $a['resend_blocked'] ? '#ef4444' : ($a['resend_count'] >= 2 ? '#f59e0b' : 'var(--text-primary)'); ?>;"><?php echo (int)$a['resend_count']; ?></span>
                            <span style="font-size: 0.72rem; color: var(--text-muted);">/ 3</span>
                        </div>
                        <?php if ($a['resend_blocked']): ?>
                        <div style="font-size: 0.65rem; color: #ef4444; margin-top: 2px;">blocked</div>
                        <?php elseif ($a['resend_window_start']): 
                            $wEnd = (new DateTime($a['resend_window_start']))->modify('+60 minutes');
                            $now = new DateTime();
                            if ($wEnd > $now):
                                $diff = $now->diff($wEnd);
                        ?>
                        <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 2px;"><?php echo $diff->i; ?>m left</div>
                        <?php endif; endif; ?>
                    </td>
                    <!-- Status -->
                    <td style="padding: 12px; text-align: center;">
                        <?php if ($a['is_blocked']): ?>
                        <span style="display: inline-flex; align-items: center; gap: 3px; padding: 3px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 600; background: rgba(239,68,68,0.12); color: #ef4444; border: 1px solid rgba(239,68,68,0.2);">
                            <i class="fas fa-lock" style="font-size: 0.6rem;"></i> Blocked
                        </span>
                        <?php else: ?>
                        <span style="display: inline-flex; align-items: center; gap: 3px; padding: 3px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 600; background: rgba(16,185,129,0.12); color: #10b981; border: 1px solid rgba(16,185,129,0.2);">
                            <i class="fas fa-unlock" style="font-size: 0.6rem;"></i> Active
                        </span>
                        <?php endif; ?>
                    </td>
                    <!-- Last Activity -->
                    <td style="padding: 12px;">
                        <div style="font-size: 0.78rem; color: var(--text-muted);">
                            <?php 
                            $lastAct = $a['last_attempt_at'] ?? $a['last_request_at'];
                            if ($lastAct) {
                                $d = new DateTime($lastAct);
                                echo $d->format('M j, Y');
                                echo '<br><span style="font-size: 0.7rem; opacity: 0.7;">' . $d->format('g:i A') . '</span>';
                            } else {
                                echo '—';
                            }
                            ?>
                        </div>
                    </td>
                    <!-- Actions -->
                    <td style="padding: 12px; text-align: center;">
                        <div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">
                            <?php
                            $userName = htmlspecialchars($a['full_name'], ENT_QUOTES);
                            $uid = (int)$a['user_id'];
                            $actions = [
                                ['action' => 'reset_limit',        'icon' => 'fa-redo-alt',   'label' => 'OTP Limit',     'color' => '#3b82f6', 'confirm' => "Reset the OTP request limit for {$userName}? This restores their ability to use Forgot Password."],
                                ['action' => 'reset_otp_input',    'icon' => 'fa-keyboard',   'label' => 'Input Tries',   'color' => '#f59e0b', 'confirm' => "Reset the OTP input attempts for {$userName}?"],
                                ['action' => 'reset_resend',       'icon' => 'fa-envelope-open','label' => 'Resend Limit','color' => '#06b6d4', 'confirm' => "Reset the OTP resend limit for {$userName}?"],
                                ['action' => 'reset_verification', 'icon' => 'fa-eye-slash',  'label' => 'Page Visits',   'color' => '#8b5cf6', 'confirm' => "Reset the verification page access count for {$userName}?"],
                            ];
                            foreach ($actions as $act): ?>
                            <button type="button" class="prm-action-btn" 
                                    data-action="<?php echo $act['action']; ?>"
                                    data-user-id="<?php echo $uid; ?>"
                                    data-user-name="<?php echo $userName; ?>"
                                    data-confirm-msg="<?php echo htmlspecialchars($act['confirm'], ENT_QUOTES); ?>"
                                    style="padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; border: 1px solid <?php echo $act['color']; ?>40; background: <?php echo $act['color']; ?>15; color: <?php echo $act['color']; ?>; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s; white-space: nowrap; width: 100%; justify-content: center;"
                                    onmouseover="this.style.background='<?php echo $act['color']; ?>30'"
                                    onmouseout="this.style.background='<?php echo $act['color']; ?>15'">
                                <i class="fas <?php echo $act['icon']; ?>" style="font-size: 0.62rem;"></i> <?php echo $act['label']; ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="padding: 16px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: center; gap: 4px;">
        <?php if ($page > 1): ?>
        <a href="<?php echo navUrl('/password-resets.php?' . http_build_query(array_merge($filters, ['page' => $page - 1]))); ?>" 
           style="padding: 6px 12px; border-radius: 6px; font-size: 0.82rem; color: var(--text-muted); text-decoration: none; border: 1px solid var(--border-color);">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <a href="<?php echo navUrl('/password-resets.php?' . http_build_query(array_merge($filters, ['page' => $i]))); ?>" 
           style="padding: 6px 12px; border-radius: 6px; font-size: 0.82rem; text-decoration: none; <?php echo $i === $page ? 'background: var(--primary); color: white;' : 'color: var(--text-muted); border: 1px solid var(--border-color);'; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <a href="<?php echo navUrl('/password-resets.php?' . http_build_query(array_merge($filters, ['page' => $page + 1]))); ?>"
           style="padding: 6px 12px; border-radius: 6px; font-size: 0.82rem; color: var(--text-muted); text-decoration: none; border: 1px solid var(--border-color);">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Confirmation Modal -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal" style="max-width: 440px;">
        <div class="modal-header">
            <h3><i class="fas fa-shield-alt" style="color: var(--primary); margin-right: 8px;"></i> Confirm Action</h3>
            <button class="modal-close" onclick="closeConfirmModal()">&times;</button>
        </div>
        <div class="modal-body" style="overflow-y: auto;">
            <p id="confirmMsg" style="color: var(--text-secondary); font-size: 0.92rem; line-height: 1.6;"></p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeConfirmModal()" style="padding: 8px 20px; font-size: 0.88rem; border: 1px solid var(--border-color); background: transparent; color: var(--text-secondary); border-radius: 8px; cursor: pointer;">Cancel</button>
            <button class="btn btn-primary" id="confirmBtn" style="padding: 8px 20px; font-size: 0.88rem; border-radius: 8px; cursor: pointer;">Confirm</button>
        </div>
    </div>
</div>

<!-- Hidden form for submitting actions -->
<form id="actionForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="_token" value="<?php echo htmlspecialchars($currentToken); ?>">
    <input type="hidden" name="action" id="actionField" value="">
    <input type="hidden" name="user_id" id="userIdField" value="">
</form>

<!-- Success notification modal -->
<div class="modal-overlay" id="successModal">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-body" style="text-align: center; padding: 40px 24px; overflow-y: auto;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(16,185,129,0.15); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                <i class="fas fa-check-circle" style="font-size: 2rem; color: #10b981;"></i>
            </div>
            <h3 style="color: var(--text-primary); font-size: 1.1rem; margin-bottom: 8px;">Limit Reset Successful</h3>
            <p id="successMsg" style="color: var(--text-secondary); font-size: 0.88rem; line-height: 1.5;"></p>
        </div>
        <div class="modal-footer" style="justify-content: center;">
            <button class="btn btn-primary" onclick="closeSuccessModal()" style="padding: 8px 28px; font-size: 0.88rem; border-radius: 8px; cursor: pointer;">OK</button>
        </div>
    </div>
</div>

<script>
// Confirmation modal logic — replaces browser confirm()
let pendingAction = null;

document.querySelectorAll('.prm-action-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const action = this.dataset.action;
        const userId = this.dataset.userId;
        const confirmMsg = this.dataset.confirmMsg;
        
        pendingAction = { action, userId };
        document.getElementById('confirmMsg').textContent = confirmMsg;
        document.getElementById('confirmModal').classList.add('active');
    });
});

document.getElementById('confirmBtn').addEventListener('click', function() {
    if (!pendingAction) return;
    document.getElementById('actionField').value = pendingAction.action;
    document.getElementById('userIdField').value = pendingAction.userId;
    closeConfirmModal();
    document.getElementById('actionForm').submit();
});

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.remove('active');
    pendingAction = null;
}

// Close modal on overlay click
document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) closeConfirmModal();
});

// Success modal (shown for POST message if present)
<?php if ($message): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('successMsg').textContent = <?php echo json_encode($message); ?>;
    document.getElementById('successModal').classList.add('active');
});
<?php endif; ?>

function closeSuccessModal() {
    document.getElementById('successModal').classList.remove('active');
}

document.getElementById('successModal').addEventListener('click', function(e) {
    if (e.target === this) closeSuccessModal();
});

// Keyboard: Escape closes modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeConfirmModal();
        closeSuccessModal();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
