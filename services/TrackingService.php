<?php
/**
 * TrackingService
 * Generates unique tracking numbers for LS and AT requests
 */

require_once __DIR__ . '/../config/database.php';

class TrackingService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Generate next Locator Slip control number
     * Format: LS-YYYY-NNNNNN (e.g., LS-2026-000001)
     */
    public function generateLSNumber()
    {
        return $this->generateNumber('LS');
    }

    /**
     * Generate next Authority to Travel tracking number
     * Format: AT-YYYY-NNNNN (e.g., AT-2026-00001)
     */
    public function generateATNumber($category = null, $scope = null)
    {
        return $this->generateNumber('AT');
    }

    /**
     * Generate next Pass Slip control number
     * Format: PS-YYYY-NNNNNN (e.g., PS-2026-000001)
     */
    public function generatePSNumber()
    {
        return $this->generateNumber('PS');
    }

    /**
     * Legacy method - redirects to unified AT number
     */
    public function generateATLocalNumber()
    {
        return $this->generateATNumber();
    }

    /**
     * Legacy method - redirects to unified AT number
     */
    public function generateATNationalNumber()
    {
        return $this->generateATNumber();
    }

    /**
     * Legacy method - redirects to unified AT number
     */
    public function generateATPersonalNumber()
    {
        return $this->generateATNumber();
    }

    /**
     * Core number generation with atomic increment
     */
    private function generateNumber($prefix)
    {
        $year = date('Y');
        $conn = $this->db->getConnection();

        try {
            $conn->beginTransaction();

            // Lock the row for update
            $sql = "SELECT last_number FROM tracking_sequences 
                    WHERE prefix = ? AND year = ? FOR UPDATE";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$prefix, $year]);
            $row = $stmt->fetch();

            if ($row) {
                // Increment existing sequence
                $newNumber = $row['last_number'] + 1;
                $updateSql = "UPDATE tracking_sequences SET last_number = ? 
                              WHERE prefix = ? AND year = ?";
                $conn->prepare($updateSql)->execute([$newNumber, $prefix, $year]);
            } else {
                // Create new sequence for this year
                $newNumber = 1;
                $insertSql = "INSERT INTO tracking_sequences (prefix, year, last_number) 
                              VALUES (?, ?, ?)";
                $conn->prepare($insertSql)->execute([$prefix, $year, $newNumber]);
            }

            $conn->commit();

            // Format: PREFIX-YYYY-NNNNNN
            return sprintf("%s-%d-%06d", $prefix, $year, $newNumber);

        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Parse tracking number to get components
     */
    public static function parseTrackingNumber($trackingNo)
    {
        // Handle different formats
        if (preg_match('/^(LS)-(\d{4})-(\d+)$/', $trackingNo, $matches)) {
            return [
                'type' => 'locator_slip',
                'prefix' => $matches[1],
                'year' => $matches[2],
                'number' => intval($matches[3])
            ];
        }

        if (preg_match('/^(PS)-(\d{4})-(\d+)$/', $trackingNo, $matches)) {
            return [
                'type' => 'pass_slip',
                'prefix' => $matches[1],
                'year' => $matches[2],
                'number' => intval($matches[3])
            ];
        }

        if (preg_match('/^(AT-LOCAL|AT-NATL|AT-OR|AT-PERS|AT)-(\d{4})-(\d+)$/', $trackingNo, $matches)) {
            $scope = 'local';
            $category = 'official';
            $travelType = 'within_region';

            if ($matches[1] === 'AT-PERS') {
                $category = 'personal';
                $scope = null;
                $travelType = null;
            } elseif ($matches[1] === 'AT-NATL' || $matches[1] === 'AT-OR') {
                $travelType = 'outside_region';
            }

            return [
                'type' => 'authority_to_travel',
                'prefix' => $matches[1],
                'year' => $matches[2],
                'number' => intval($matches[3]),
                'category' => $category,
                'scope' => $scope,
                'travel_type' => $travelType
            ];
        }

        return null;
    }

    /**
     * Validate tracking number format
     */
    public static function isValidTrackingNumber($trackingNo)
    {
        return self::parseTrackingNumber($trackingNo) !== null;
    }
}
