<?php
/**
 * SessionToken Model
 * Handles token-based session management for multi-account support
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin_config.php';

class SessionToken {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Generate a new session token for a user
     */
    public function create($userId) {
        // Generate secure random token
        $token = bin2hex(random_bytes(32));
        
        // Set expiration
        $expiresAt = date('Y-m-d H:i:s', time() + TOKEN_LIFETIME);
        
        // Get client info
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        
        $sql = "INSERT INTO session_tokens (token, user_id, user_agent, ip_address, expires_at)
                VALUES (?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [$token, $userId, $userAgent, $ipAddress, $expiresAt]);
        
        return $token;
    }

    /**
     * Validate token and return user data
     */
    public function validate($token) {
        if (empty($token)) {
            return false;
        }

        $sql = "SELECT st.*, au.*, ar.role_name, ar.permissions as role_permissions
                FROM session_tokens st
                JOIN admin_users au ON st.user_id = au.id
                JOIN admin_roles ar ON au.role_id = ar.id
                WHERE st.token = ? 
                AND st.expires_at > NOW()
                AND au.status = 'active' 
                AND au.is_active = 1";
        
        $result = $this->db->query($sql, [$token])->fetch();
        
        if ($result) {
            // Extend token expiration on activity (sliding expiration)
            $this->extend($token);
            return $result;
        }
        
        return false;
    }

    /**
     * Extend token expiration
     */
    public function extend($token) {
        $newExpiry = date('Y-m-d H:i:s', time() + TOKEN_LIFETIME);
        $sql = "UPDATE session_tokens SET expires_at = ? WHERE token = ?";
        $this->db->query($sql, [$newExpiry, $token]);
    }

    /**
     * Delete/invalidate a token (logout)
     */
    public function delete($token) {
        $sql = "DELETE FROM session_tokens WHERE token = ?";
        return $this->db->query($sql, [$token]);
    }

    /**
     * Delete all tokens for a user (logout from all devices)
     */
    public function deleteAllForUser($userId) {
        $sql = "DELETE FROM session_tokens WHERE user_id = ?";
        return $this->db->query($sql, [$userId]);
    }

    /**
     * Clean up expired tokens
     */
    public function cleanupExpired() {
        $sql = "DELETE FROM session_tokens WHERE expires_at < NOW()";
        return $this->db->query($sql);
    }

    /**
     * Get active sessions for a user
     */
    public function getActiveSessions($userId) {
        $sql = "SELECT * FROM session_tokens 
                WHERE user_id = ? AND expires_at > NOW()
                ORDER BY created_at DESC";
        return $this->db->query($sql, [$userId])->fetchAll();
    }

    /**
     * Check if user has active sessions
     */
    public function hasActiveSessions($userId) {
        $sql = "SELECT COUNT(*) as count FROM session_tokens 
                WHERE user_id = ? AND expires_at > NOW()";
        $result = $this->db->query($sql, [$userId])->fetch();
        return $result['count'] > 0;
    }
}
