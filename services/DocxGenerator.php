<?php
/**
 * DocxGenerator Service
 * Generates DOCX documents from templates using PHPWord
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/admin_config.php';

use PhpOffice\PhpWord\TemplateProcessor;

class DocxGenerator
{
    private $templateDir;
    private $outputDir;

    public function __construct()
    {
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
    public function generateLocatorSlip($data)
    {
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
            // Show only the time portion so the approval date does not repeat
            'approving_time' => $this->formatTime($data['approving_time'] ?? ''),
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
    public function generateATLocal($data)
    {
        $templateFile = $this->templateDir . DOCX_TEMPLATES['at_local'];

        if (!file_exists($templateFile)) {
            throw new Exception("AT Local template not found: " . $templateFile);
        }

        return $this->generateATDocument($templateFile, $data, 'AT-LOCAL');
    }

    /**
     * Generate Authority to Travel DOCX (Official - National)
     */
    public function generateATNational($data)
    {
        $templateFile = $this->templateDir . DOCX_TEMPLATES['at_national'];

        if (!file_exists($templateFile)) {
            throw new Exception("AT National template not found: " . $templateFile);
        }

        return $this->generateATDocument($templateFile, $data, 'AT-NATL');
    }

    /**
     * Generate Authority to Travel DOCX (Personal)
     */
    public function generateATPersonal($data)
    {
        $templateFile = $this->templateDir . DOCX_TEMPLATES['at_personal'];

        if (!file_exists($templateFile)) {
            throw new Exception("AT Personal template not found: " . $templateFile);
        }

        return $this->generateATDocument($templateFile, $data, 'AT-PERS');
    }

    /**
     * Generate AT document based on category and scope
     */
    public function generateAT($data)
    {
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
    private function generateATDocument($templateFile, $data, $prefix)
    {
        $templateProcessor = new TemplateProcessor($templateFile);

        // Helper function to ensure value is a string and not null
        $getValue = function ($key, $default = '') use ($data) {
            $value = $data[$key] ?? $default;
            return $value !== null ? (string) $value : '';
        };

        // Set all placeholders - ensure all values are strings
        $placeholders = [
            'at_tracking_no' => $getValue('at_tracking_no', ''),
            'employee_name' => $getValue('employee_name', ''),
            'employee_position' => $getValue('employee_position', ''),
            'permanent_station' => $getValue('permanent_station', ''),
            'purpose_of_travel' => $getValue('purpose_of_travel', ''),
            'host_of_activity' => $getValue('host_of_activity', ''),
            'date_from' => $this->formatDate($getValue('date_from', '')),
            'date_to' => $this->formatDate($getValue('date_to', '')),
            'destination' => $getValue('destination', ''),
            'fund_source' => $getValue('fund_source', ''),
            'inclusive_dates' => $getValue('inclusive_dates', '') ?: $this->formatDateRange($getValue('date_from', ''), $getValue('date_to', '')),
            'requesting_employee_name' => $getValue('requesting_employee_name', '') ?: $getValue('employee_name', ''),
            'request_date' => $this->formatDate($getValue('request_date', '')),
            'recommending_authority_name' => $getValue('recommending_authority_name', ''),
            'recommending_date' => $this->formatDate($getValue('recommending_date', '')),
            'approving_authority_name' => $getValue('approving_authority_name', ''),
            'approval_date' => $this->formatDate($getValue('approval_date', '')),
            // Show only the time portion so the approval date does not repeat
            'approving_time' => $this->formatTime($getValue('approving_time', ''))
        ];

        // Replace all placeholders - use setValue for each
        // Note: Template must have placeholders in format ${key} (e.g., ${employee_name})
        foreach ($placeholders as $key => $value) {
            // Ensure value is always a string, never null
            $stringValue = $value !== null ? (string) $value : '';
            // PHPWord TemplateProcessor will replace ${key} in template with the value
            $templateProcessor->setValue($key, $stringValue);
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
    private function formatDate($date)
    {
        if (empty($date)) {
            return '';
        }
        $timestamp = strtotime($date);
        return $timestamp ? date('F j, Y', $timestamp) : $date;
    }

    /**
     * Format datetime for display
     */
    private function formatDateTime($datetime)
    {
        if (empty($datetime)) {
            return '';
        }
        $timestamp = strtotime($datetime);
        return $timestamp ? date('F j, Y - g:i A', $timestamp) : $datetime;
    }

    /**
     * Format time only for display
     */
    private function formatTime($datetime)
    {
        if (empty($datetime)) {
            return '';
        }
        $timestamp = strtotime($datetime);
        return $timestamp ? date('g:i A', $timestamp) : $datetime;
    }

    /**
     * Format date range for inclusive_dates
     */
    private function formatDateRange($dateFrom, $dateTo)
    {
        if (empty($dateFrom) || empty($dateTo)) {
            return '';
        }
        return $this->formatDate($dateFrom) . ' to ' . $this->formatDate($dateTo);
    }

    /**
     * Build checkbox-like marks for travel type
     * Use placeholder ${travel_type_marks} in the Locator Slip template
     */
    private function formatTravelTypeMarks($travelType)
    {
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
    private function getTravelMark($travelType, $target)
    {
        return $travelType === $target ? '☑' : '☐';
    }

    /**
     * Normalize stored travel type values to current codes.
     * Old DB values like "official" should still mark Official Business.
     */
    private function normalizeTravelType($travelType)
    {
        $t = strtolower(trim((string) $travelType));

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
    public function cleanupOldFiles($hoursOld = 24)
    {
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
    public function getDownloadUrl($filePath)
    {
        $filename = basename($filePath);
        return BASE_URL . '/uploads/generated/' . $filename;
    }

    /**
     * Convert DOCX to PDF using Microsoft Word COM Automation via PowerShell
     * Requires: Windows + Microsoft Word installed + PowerShell access
     *
     * @param string $docxPath Path to the DOCX file
     * @return string Path to the generated PDF file
     * @throws Exception if conversion fails
     */
    public function convertToPDF($docxPath)
    {
        if (!file_exists($docxPath)) {
            throw new Exception("DOCX file not found: " . $docxPath);
        }

        // Get PowerShell script path from config
        $scriptPath = defined('WORD_CONVERT_SCRIPT')
            ? WORD_CONVERT_SCRIPT
            : __DIR__ . '/../scripts/convert-to-pdf.ps1';

        if (!file_exists($scriptPath)) {
            throw new Exception("Word conversion script not found: " . $scriptPath);
        }

        // Determine the PDF output path
        $pdfPath = $this->outputDir . basename($docxPath, '.docx') . '.pdf';

        // Convert to absolute Windows paths for PowerShell
        $absDocx = realpath($docxPath);
        $absPdf = str_replace('/', '\\', $this->outputDir) . basename($docxPath, '.docx') . '.pdf';
        $absScript = realpath($scriptPath);

        if ($absDocx === false) {
            throw new Exception("Cannot resolve DOCX path: " . $docxPath);
        }

        // Build the PowerShell command
        $command = sprintf(
            'powershell -ExecutionPolicy Bypass -NoProfile -NonInteractive -File "%s" -inputPath "%s" -outputPath "%s" 2>&1',
            $absScript,
            $absDocx,
            $absPdf
        );

        // Get timeout from config (default 120 seconds)
        $timeout = defined('WORD_CONVERT_TIMEOUT') ? WORD_CONVERT_TIMEOUT : 120;

        // Execute the conversion with timeout handling
        $output = [];
        $returnCode = null;

        // Use proc_open for timeout support
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            $this->logConversionError("Failed to start PowerShell process", $docxPath);
            throw new Exception("Failed to start Word conversion process");
        }

        // Close stdin
        fclose($pipes[0]);

        // Set streams to non-blocking
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $startTime = time();
        $stdout = '';
        $stderr = '';

        // Wait for process to complete with timeout
        while (true) {
            $status = proc_get_status($process);

            // Read available output
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);

            if (!$status['running']) {
                $returnCode = $status['exitcode'];
                break;
            }

            if ((time() - $startTime) > $timeout) {
                // Kill the process on timeout
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                $this->logConversionError("Conversion timed out after {$timeout} seconds", $docxPath);

                // Clean up the DOCX file
                if (file_exists($docxPath)) {
                    unlink($docxPath);
                }

                throw new Exception("Word conversion timed out after {$timeout} seconds");
            }

            usleep(250000); // 250ms polling interval
        }

        // Read any remaining output
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        // Check for errors
        if ($returnCode !== 0) {
            $errorMsg = trim($stderr ?: $stdout);
            $this->logConversionError("Exit code {$returnCode}: {$errorMsg}", $docxPath);
            throw new Exception("Word conversion failed (exit code {$returnCode}): " . $errorMsg);
        }

        if (!file_exists($absPdf)) {
            $this->logConversionError("PDF file was not created", $docxPath);
            throw new Exception("PDF file was not created: " . $absPdf);
        }

        // Clean up the original DOCX file
        if (file_exists($docxPath)) {
            unlink($docxPath);
        }

        return $absPdf;
    }

    /**
     * Log conversion errors for troubleshooting
     *
     * @param string $message Error message
     * @param string $docxPath Path to the DOCX that failed
     */
    private function logConversionError($message, $docxPath)
    {
        $logDir = defined('CONVERSION_LOG_DIR')
            ? CONVERSION_LOG_DIR
            : __DIR__ . '/../logs/';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . 'pdf_conversion.log';
        $timestamp = date('Y-m-d H:i:s');
        $entry = "[{$timestamp}] ERROR: {$message} | File: {$docxPath}" . PHP_EOL;

        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Generate Locator Slip PDF
     */
    public function generateLocatorSlipPDF($data)
    {
        $docxPath = $this->generateLocatorSlip($data);
        return $this->convertToPDF($docxPath);
    }

    /**
     * Generate Authority to Travel PDF
     */
    public function generateATPDF($data)
    {
        $docxPath = $this->generateAT($data);
        return $this->convertToPDF($docxPath);
    }

    /**
     * Clean up old generated files (older than 24 hours) - now includes PDFs
     */
    public function cleanupOldFilesPDF($hoursOld = 24)
    {
        $files = array_merge(
            glob($this->outputDir . '*.docx'),
            glob($this->outputDir . '*.pdf')
        );
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
     * Generate Pass Slip DOCX
     * Template has Employee Copy + HRMO Copy (side-by-side on same page)
     * Placeholders use ${variable_name} format
     */
    public function generatePassSlip($data)
    {
        $templateFile = $this->templateDir . DOCX_TEMPLATES['pass_slip'];

        if (!file_exists($templateFile)) {
            throw new Exception("Pass Slip template not found: " . $templateFile);
        }

        $templateProcessor = new TemplateProcessor($templateFile);

        // Format times
        $intendedDeparture = !empty($data['idt'])
            ? date('g:i A', strtotime($data['idt'])) : '';
        $intendedArrival = !empty($data['iat'])
            ? date('g:i A', strtotime($data['iat'])) : '';
        $actualDeparture = !empty($data['actual_departure_time'])
            ? date('g:i A', strtotime($data['actual_departure_time'])) : '';
        $actualArrival = !empty($data['actual_arrival_time'])
            ? date('g:i A', strtotime($data['actual_arrival_time'])) : '';

        $placeholders = [
            // Employee / Request info
            'ps_control_no' => $data['ps_control_no'] ?? '',
            'employee_name' => $data['employee_name'] ?? '',
            'employee_position' => $data['employee_position'] ?? '',
            'employee_office' => $data['employee_office'] ?? '',
            'date' => $this->formatDate($data['date'] ?? ''),
            'destination' => $data['destination'] ?? '',
            'idt' => $intendedDeparture,
            'iat' => $intendedArrival,
            'purpose' => $data['purpose'] ?? '',
            // Approval info
            'approver_name' => $data['approver_name'] ?? '',
            'approver_position' => $data['approver_position'] ?? '',
            'approval_date' => $this->formatDate($data['approval_date'] ?? ''),
            'approving_time' => $this->formatTime($data['approving_time'] ?? ''),
            'requesting_employee_name' => $data['requesting_employee_name'] ?? $data['employee_name'] ?? '',
            // Guard fields (blank if not yet filled)
            'actual_departure_time' => $actualDeparture,
            'actual_arrival_time' => $actualArrival,
        ];

        foreach ($placeholders as $key => $value) {
            $templateProcessor->setValue($key, $value);
        }

        // Generate output filename
        $outputFile = $this->outputDir . 'PS_' . ($data['ps_control_no'] ?? 'unknown') . '_' . time() . '.docx';
        $templateProcessor->saveAs($outputFile);

        return $outputFile;
    }

    /**
     * Generate Pass Slip PDF
     */
    public function generatePassSlipPDF($data)
    {
        $docxPath = $this->generatePassSlip($data);
        return $this->convertToPDF($docxPath);
    }
}
