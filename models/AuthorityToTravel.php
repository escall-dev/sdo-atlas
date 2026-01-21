<?php
/**
 * AuthorityToTravel Model
 * Handles CRUD operations for Authority to Travel requests
 */

require_once __DIR__ . '/../config/database.php';

class AuthorityToTravel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create a new Authority to Travel request
     */
    public function create($data) {
        $sql = "INSERT INTO authority_to_travel (
            at_tracking_no, employee_name, employee_position, permanent_station,
            purpose_of_travel, host_of_activity, date_from, date_to,
            destination, fund_source, inclusive_dates,
            requesting_employee_name, request_date,
            travel_category, travel_scope, user_id, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        
        $this->db->query($sql, [
            $data['at_tracking_no'],
            $data['employee_name'],
            $data['employee_position'] ?? null,
            $data['permanent_station'] ?? null,
            $data['purpose_of_travel'],
            $data['host_of_activity'] ?? null,
            $data['date_from'],
            $data['date_to'],
            $data['destination'],
            $data['fund_source'] ?? null,
            $data['inclusive_dates'] ?? null,
            $data['requesting_employee_name'] ?? $data['employee_name'],
            $data['request_date'] ?? date('Y-m-d'),
            $data['travel_category'],
            $data['travel_scope'] ?? null,
            $data['user_id']
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Get Authority to Travel by ID
     */
    public function getById($id) {
        $sql = "SELECT at.*, 
                       u.full_name as filed_by_name, u.email as filed_by_email,
                       a.full_name as approved_by_name
                FROM authority_to_travel at
                LEFT JOIN admin_users u ON at.user_id = u.id
                LEFT JOIN admin_users a ON at.approved_by = a.id
                WHERE at.id = ?";
        return $this->db->query($sql, [$id])->fetch();
    }

    /**
     * Get Authority to Travel by tracking number
     */
    public function getByTrackingNo($trackingNo) {
        $sql = "SELECT at.*, 
                       u.full_name as filed_by_name,
                       a.full_name as approved_by_name
                FROM authority_to_travel at
                LEFT JOIN admin_users u ON at.user_id = u.id
                LEFT JOIN admin_users a ON at.approved_by = a.id
                WHERE at.at_tracking_no = ?";
        return $this->db->query($sql, [$trackingNo])->fetch();
    }

    /**
     * Get all Authority to Travel requests with filters
     */
    public function getAll($filters = [], $limit = 15, $offset = 0) {
        $sql = "SELECT at.*, 
                       u.full_name as filed_by_name, u.email as filed_by_email,
                       a.full_name as approved_by_name
                FROM authority_to_travel at
                LEFT JOIN admin_users u ON at.user_id = u.id
                LEFT JOIN admin_users a ON at.approved_by = a.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= " AND at.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND at.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['travel_category'])) {
            $sql .= " AND at.travel_category = ?";
            $params[] = $filters['travel_category'];
        }

        if (!empty($filters['travel_scope'])) {
            $sql .= " AND at.travel_scope = ?";
            $params[] = $filters['travel_scope'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(at.created_at) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(at.created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (at.at_tracking_no LIKE ? OR at.employee_name LIKE ? OR at.destination LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY at.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->query($sql, $params)->fetchAll();
    }

    /**
     * Get count of Authority to Travel requests with filters
     */
    public function getCount($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM authority_to_travel at WHERE 1=1";
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= " AND at.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND at.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['travel_category'])) {
            $sql .= " AND at.travel_category = ?";
            $params[] = $filters['travel_category'];
        }

        if (!empty($filters['travel_scope'])) {
            $sql .= " AND at.travel_scope = ?";
            $params[] = $filters['travel_scope'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(at.created_at) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(at.created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (at.at_tracking_no LIKE ? OR at.employee_name LIKE ? OR at.destination LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $result = $this->db->query($sql, $params)->fetch();
        return $result['total'];
    }

    /**
     * Approve an Authority to Travel request
     */
    public function approve($id, $approverId, $approverName, $recommenderName = null) {
        $sql = "UPDATE authority_to_travel SET 
                status = 'approved',
                approved_by = ?,
                approving_authority_name = ?,
                approval_date = CURDATE(),
                recommending_authority_name = ?,
                recommending_date = CURDATE()
                WHERE id = ?";
        
        return $this->db->query($sql, [$approverId, $approverName, $recommenderName ?? $approverName, $id]);
    }

    /**
     * Reject an Authority to Travel request
     */
    public function reject($id, $approverId, $reason = null) {
        $sql = "UPDATE authority_to_travel SET 
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
                SUM(CASE WHEN YEARWEEK(created_at) = YEARWEEK(CURDATE()) THEN 1 ELSE 0 END) as this_week,
                SUM(CASE WHEN travel_category = 'official' AND travel_scope = 'local' THEN 1 ELSE 0 END) as local_official,
                SUM(CASE WHEN travel_category = 'official' AND travel_scope = 'national' THEN 1 ELSE 0 END) as national_official,
                SUM(CASE WHEN travel_category = 'personal' THEN 1 ELSE 0 END) as personal
                FROM authority_to_travel WHERE 1=1" . $userCondition;

        return $this->db->query($sql, $params)->fetch();
    }

    /**
     * Get recent Authority to Travel requests for dashboard
     */
    public function getRecent($limit = 5, $userId = null) {
        $sql = "SELECT at.*, u.full_name as filed_by_name
                FROM authority_to_travel at
                LEFT JOIN admin_users u ON at.user_id = u.id
                WHERE 1=1";
        $params = [];

        if ($userId) {
            $sql .= " AND at.user_id = ?";
            $params[] = $userId;
        }

        $sql .= " ORDER BY at.created_at DESC LIMIT ?";
        $params[] = $limit;

        return $this->db->query($sql, $params)->fetchAll();
    }

    /**
     * Get pending requests for approvers
     */
    public function getPending($limit = 10) {
        $sql = "SELECT at.*, u.full_name as filed_by_name, u.email as filed_by_email
                FROM authority_to_travel at
                LEFT JOIN admin_users u ON at.user_id = u.id
                WHERE at.status = 'pending'
                ORDER BY at.created_at ASC
                LIMIT ?";
        
        return $this->db->query($sql, [$limit])->fetchAll();
    }

    /**
     * Delete an Authority to Travel request (admin only)
     */
    public function delete($id) {
        $sql = "DELETE FROM authority_to_travel WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }

    /**
     * Get type label for display
     */
    public static function getTypeLabel($category, $scope = null) {
        if ($category === 'personal') {
            return 'Personal';
        }
        if ($scope === 'national') {
            return 'Official - National';
        }
        return 'Official - Local';
    }
}
