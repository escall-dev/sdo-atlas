<?php
/**
 * LocatorSlip Model
 * Handles CRUD operations for Locator Slip requests
 */

require_once __DIR__ . '/../config/database.php';

class LocatorSlip {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create a new Locator Slip request
     */
    public function create($data) {
        $sql = "INSERT INTO locator_slips (
            ls_control_no, employee_name, employee_position, employee_office,
            purpose_of_travel, travel_type, date_time, destination,
            requesting_employee_name, request_date, user_id, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        
        $this->db->query($sql, [
            $data['ls_control_no'],
            $data['employee_name'],
            $data['employee_position'] ?? null,
            $data['employee_office'] ?? null,
            $data['purpose_of_travel'],
            $data['travel_type'],
            $data['date_time'],
            $data['destination'],
            $data['requesting_employee_name'] ?? $data['employee_name'],
            $data['request_date'] ?? date('Y-m-d'),
            $data['user_id']
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Get Locator Slip by ID
     */
    public function getById($id) {
        $sql = "SELECT ls.*, 
                       u.full_name as filed_by_name, u.email as filed_by_email,
                       a.full_name as approved_by_name
                FROM locator_slips ls
                LEFT JOIN admin_users u ON ls.user_id = u.id
                LEFT JOIN admin_users a ON ls.approved_by = a.id
                WHERE ls.id = ?";
        return $this->db->query($sql, [$id])->fetch();
    }

    /**
     * Get Locator Slip by control number
     */
    public function getByControlNo($controlNo) {
        $sql = "SELECT ls.*, 
                       u.full_name as filed_by_name,
                       a.full_name as approved_by_name
                FROM locator_slips ls
                LEFT JOIN admin_users u ON ls.user_id = u.id
                LEFT JOIN admin_users a ON ls.approved_by = a.id
                WHERE ls.ls_control_no = ?";
        return $this->db->query($sql, [$controlNo])->fetch();
    }

    /**
     * Get all Locator Slips with filters
     */
    public function getAll($filters = [], $limit = 15, $offset = 0) {
        $sql = "SELECT ls.*, 
                       u.full_name as filed_by_name, u.email as filed_by_email,
                       a.full_name as approved_by_name
                FROM locator_slips ls
                LEFT JOIN admin_users u ON ls.user_id = u.id
                LEFT JOIN admin_users a ON ls.approved_by = a.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= " AND ls.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND ls.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(ls.created_at) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(ls.created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (ls.ls_control_no LIKE ? OR ls.employee_name LIKE ? OR ls.destination LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY ls.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->query($sql, $params)->fetchAll();
    }

    /**
     * Get count of Locator Slips with filters
     */
    public function getCount($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM locator_slips ls WHERE 1=1";
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= " AND ls.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND ls.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(ls.created_at) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(ls.created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (ls.ls_control_no LIKE ? OR ls.employee_name LIKE ? OR ls.destination LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $result = $this->db->query($sql, $params)->fetch();
        return $result['total'];
    }

    /**
     * Approve a Locator Slip
     */
    public function approve($id, $approverId, $approverName, $approverPosition) {
        $sql = "UPDATE locator_slips SET 
                status = 'approved',
                approved_by = ?,
                approver_name = ?,
                approver_position = ?,
                approval_date = CURDATE()
                WHERE id = ?";
        
        return $this->db->query($sql, [$approverId, $approverName, $approverPosition, $id]);
    }

    /**
     * Reject a Locator Slip
     */
    public function reject($id, $approverId, $reason = null) {
        $sql = "UPDATE locator_slips SET 
                status = 'rejected',
                approved_by = ?,
                rejection_reason = ?
                WHERE id = ?";
        
        return $this->db->query($sql, [$approverId, $reason, $id]);
    }

    /**
     * Get statistics for dashboard
     */
    public function getStatistics($userId = null) {
        $params = [];
        $userCondition = '';
        
        if ($userId) {
            $userCondition = ' AND user_id = ?';
            $params[] = $userId;
        }

        $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
                SUM(CASE WHEN YEARWEEK(created_at) = YEARWEEK(CURDATE()) THEN 1 ELSE 0 END) as this_week
                FROM locator_slips WHERE 1=1" . $userCondition;

        return $this->db->query($sql, $params)->fetch();
    }

    /**
     * Get recent Locator Slips for dashboard
     */
    public function getRecent($limit = 5, $userId = null) {
        $sql = "SELECT ls.*, u.full_name as filed_by_name
                FROM locator_slips ls
                LEFT JOIN admin_users u ON ls.user_id = u.id
                WHERE 1=1";
        $params = [];

        if ($userId) {
            $sql .= " AND ls.user_id = ?";
            $params[] = $userId;
        }

        $sql .= " ORDER BY ls.created_at DESC LIMIT ?";
        $params[] = $limit;

        return $this->db->query($sql, $params)->fetchAll();
    }

    /**
     * Get pending requests for approvers
     */
    public function getPending($limit = 10) {
        $sql = "SELECT ls.*, u.full_name as filed_by_name, u.email as filed_by_email
                FROM locator_slips ls
                LEFT JOIN admin_users u ON ls.user_id = u.id
                WHERE ls.status = 'pending'
                ORDER BY ls.created_at ASC
                LIMIT ?";
        
        return $this->db->query($sql, [$limit])->fetchAll();
    }

    /**
     * Delete a Locator Slip (admin only)
     */
    public function delete($id) {
        $sql = "DELETE FROM locator_slips WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }
}
