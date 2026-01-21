<?php
/**
 * DocxGenerator Service
 * Generates DOCX documents from templates using PHPWord
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/admin_config.php';

use PhpOffice\PhpWord\TemplateProcessor;

class DocxGenerator {
    private $templateDir;
    private $outputDir;

    public function __construct() {
        $this->templateDir = TEMPLATE_DIR;
        $this->outputDir = GENERATED_DIR;
        
        // Ensure output directory exists
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    /**
     * Generate Locator Slip DOCX
     */
    public function generateLocatorSlip($data) {
        $templateFile = $this->templateDir . DOCX_TEMPLATES['locator_slip'];
        
        if (!file_exists($templateFile)) {
            throw new Exception("Locator Slip template not found: " . $templateFile);
        }

        $templateProcessor = new TemplateProcessor($templateFile);

        // Set all placeholders
        $travelTypeRaw = $data['travel_type'] ?? '';
        $travelType = $this->normalizeTravelType($travelTypeRaw);
        
        // Ensure mutual exclusivity: only one checkbox can be checked
        $isOfficialBusiness = ($travelType === 'official_business');
        $isOfficialTime = ($travelType === 'official_time');

        $placeholders = [
            'ls_control_no' => $data['ls_control_no'] ?? '',
            'employee_name' => $data['employee_name'] ?? '',
            'employee_position' => $data['employee_position'] ?? '',
            'employee_office' => $data['employee_office'] ?? '',
            'purpose_of_travel' => $data['purpose_of_travel'] ?? '',
            // Keep raw value available if you still use ${travel_type} in template
            'travel_type' => $travelTypeRaw,
            // Checkbox-style marks for travel type: use ${travel_type_marks} in template
            'travel_type_marks' => $this->formatTravelTypeMarks($travelType),
            // Individual checkbox marks (Method B placeholders) - mutually exclusive
            'cb_ob' => $isOfficialBusiness ? '☑' : '☐',
            'cb_ot' => $isOfficialTime ? '☑' : '☐',
            'date_time' => $this->formatDateTime($data['date_time'] ?? ''),
            'destination' => $data['destination'] ?? '',
            'requesting_employee_name' => $data['requesting_employee_name'] ?? $data['employee_name'] ?? '',
            'request_date' => $this->formatDate($data['request_date'] ?? ''),
            'approver_name' => $data['approver_name'] ?? '',
            'approver_position' => $data['approver_position'] ?? '',
            'approval_date' => $this->formatDate($data['approval_date'] ?? '')
        ];

        foreach ($placeholders as $key => $value) {
            $templateProcessor->setValue($key, $value);
        }

        // Generate output filename
        $outputFile = $this->outputDir . 'LS_' . $data['ls_control_no'] . '_' . time() . '.docx';
        $templateProcessor->saveAs($outputFile);

        return $outputFile;
    }

    /**
     * Generate Authority to Travel DOCX (Official - Local)
     */
    public function generateATLocal($data) {
        $templateFile = $this->templateDir . DOCX_TEMPLATES['at_local'];
        
        if (!file_exists($templateFile)) {
            throw new Exception("AT Local template not found: " . $templateFile);
        }

        return $this->generateATDocument($templateFile, $data, 'AT-LOCAL');
    }

    /**
     * Generate Authority to Travel DOCX (Official - National)
     */
    public function generateATNational($data) {
        $templateFile = $this->templateDir . DOCX_TEMPLATES['at_national'];
        
        if (!file_exists($templateFile)) {
            throw new Exception("AT National template not found: " . $templateFile);
        }

        return $this->generateATDocument($templateFile, $data, 'AT-NATL');
    }

    /**
     * Generate Authority to Travel DOCX (Personal)
     */
    public function generateATPersonal($data) {
        $templateFile = $this->templateDir . DOCX_TEMPLATES['at_personal'];
        
        if (!file_exists($templateFile)) {
            throw new Exception("AT Personal template not found: " . $templateFile);
        }

        return $this->generateATDocument($templateFile, $data, 'AT-PERS');
    }

    /**
     * Generate AT document based on category and scope
     */
    public function generateAT($data) {
        if ($data['travel_category'] === 'personal') {
            return $this->generateATPersonal($data);
        }
        if ($data['travel_scope'] === 'national') {
            return $this->generateATNational($data);
        }
        return $this->generateATLocal($data);
    }

    /**
     * Common AT document generation
     */
    private function generateATDocument($templateFile, $data, $prefix) {
        $templateProcessor = new TemplateProcessor($templateFile);

        // Set all placeholders
        $placeholders = [
            'at_tracking_no' => $data['at_tracking_no'] ?? '',
            'employee_name' => $data['employee_name'] ?? '',
            'employee_position' => $data['employee_position'] ?? '',
            'permanent_station' => $data['permanent_station'] ?? '',
            'purpose_of_travel' => $data['purpose_of_travel'] ?? '',
            'host_of_activity' => $data['host_of_activity'] ?? '',
            'date_from' => $this->formatDate($data['date_from'] ?? ''),
            'date_to' => $this->formatDate($data['date_to'] ?? ''),
            'destination' => $data['destination'] ?? '',
            'fund_source' => $data['fund_source'] ?? '',
            'inclusive_dates' => $data['inclusive_dates'] ?? $this->formatDateRange($data['date_from'] ?? '', $data['date_to'] ?? ''),
            'requesting_employee_name' => $data['requesting_employee_name'] ?? $data['employee_name'] ?? '',
            'request_date' => $this->formatDate($data['request_date'] ?? ''),
            'recommending_authority_name' => $data['recommending_authority_name'] ?? '',
            'recommending_date' => $this->formatDate($data['recommending_date'] ?? ''),
            'approving_authority_name' => $data['approving_authority_name'] ?? '',
            'approval_date' => $this->formatDate($data['approval_date'] ?? '')
        ];

        foreach ($placeholders as $key => $value) {
            $templateProcessor->setValue($key, $value);
        }

        // Generate output filename
        $trackingNo = str_replace(['/', '\\'], '-', $data['at_tracking_no'] ?? 'UNKNOWN');
        $outputFile = $this->outputDir . $prefix . '_' . $trackingNo . '_' . time() . '.docx';
        $templateProcessor->saveAs($outputFile);

        return $outputFile;
    }

    /**
     * Format date for display
     */
    private function formatDate($date) {
        if (empty($date)) {
            return '';
        }
        $timestamp = strtotime($date);
        return $timestamp ? date('F j, Y', $timestamp) : $date;
    }

    /**
     * Format datetime for display
     */
    private function formatDateTime($datetime) {
        if (empty($datetime)) {
            return '';
        }
        $timestamp = strtotime($datetime);
        return $timestamp ? date('F j, Y - g:i A', $timestamp) : $datetime;
    }

    /**
     * Format date range for inclusive_dates
     */
    private function formatDateRange($dateFrom, $dateTo) {
        if (empty($dateFrom) || empty($dateTo)) {
            return '';
        }
        return $this->formatDate($dateFrom) . ' to ' . $this->formatDate($dateTo);
    }

    /**
     * Build checkbox-like marks for travel type
     * Use placeholder ${travel_type_marks} in the Locator Slip template
     */
    private function formatTravelTypeMarks($travelType) {
        $labels = [
            'Official Business',
            'Official Time'
        ];

        $selected = $travelType === 'official_time' ? 'Official Time' : 'Official Business';

        $lines = [];
        foreach ($labels as $label) {
            $mark = ($label === $selected) ? '☑' : '☐';
            $lines[] = $mark . ' ' . $label;
        }

        return implode("\n", $lines);
    }

    /**
     * Return a single checkbox mark (☑/☐) for a specific travel type code
     */
    private function getTravelMark($travelType, $target) {
        return $travelType === $target ? '☑' : '☐';
    }

    /**
     * Normalize stored travel type values to current codes.
     * Old DB values like "official" should still mark Official Business.
     */
    private function normalizeTravelType($travelType) {
        $t = strtolower(trim((string)$travelType));

        // Already normalized codes
        if ($t === 'official_business' || $t === 'official_time') {
            return $t;
        }

        // Legacy / display values
        if ($t === 'official' || $t === 'official business' || $t === 'officialbusiness') {
            return 'official_business';
        }
        if ($t === 'official time' || $t === 'officialtime') {
            return 'official_time';
        }

        // Default fallback
        return 'official_business';
    }

    /**
     * Clean up old generated files (older than 24 hours)
     */
    public function cleanupOldFiles($hoursOld = 24) {
        $files = glob($this->outputDir . '*.docx');
        $threshold = time() - ($hoursOld * 3600);
        $deleted = 0;

        foreach ($files as $file) {
            if (filemtime($file) < $threshold) {
                unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Get download URL for generated file
     */
    public function getDownloadUrl($filePath) {
        $filename = basename($filePath);
        return BASE_URL . '/uploads/generated/' . $filename;
    }
}
