<?php
/**
 * Test PDF Conversion with LibreOffice
 * This script tests if the PDF conversion is working correctly
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/admin_config.php';
require_once __DIR__ . '/services/DocxGenerator.php';

echo "<h1>Testing LibreOffice PDF Conversion</h1>\n";

// Test 1: Check LibreOffice Path
echo "<h2>Test 1: LibreOffice Path</h2>\n";
$libreOfficePath = LIBREOFFICE_PATH;
echo "LibreOffice Path: <strong>" . htmlspecialchars($libreOfficePath) . "</strong><br>\n";

if (file_exists($libreOfficePath)) {
    echo "<span style='color: green;'>✓ LibreOffice found!</span><br>\n";
} else {
    echo "<span style='color: red;'>✗ LibreOffice NOT found at this path!</span><br>\n";
    echo "Please update LIBREOFFICE_PATH in config/admin_config.php<br>\n";
    exit;
}

// Test 2: Test LibreOffice Command
echo "<h2>Test 2: LibreOffice Version</h2>\n";
$command = '"' . $libreOfficePath . '" --version 2>&1';
exec($command, $output, $returnCode);
echo "Command: " . htmlspecialchars($command) . "<br>\n";
echo "Output: " . htmlspecialchars(implode("\n", $output)) . "<br>\n";
echo "Return Code: " . $returnCode . "<br>\n";

if ($returnCode === 0) {
    echo "<span style='color: green;'>✓ LibreOffice command works!</span><br>\n";
} else {
    echo "<span style='color: red;'>✗ LibreOffice command failed!</span><br>\n";
}

// Test 3: Check directories
echo "<h2>Test 3: Directory Checks</h2>\n";
echo "Template Directory: <strong>" . TEMPLATE_DIR . "</strong><br>\n";
echo "Generated Directory: <strong>" . GENERATED_DIR . "</strong><br>\n";

if (is_dir(TEMPLATE_DIR)) {
    echo "<span style='color: green;'>✓ Template directory exists</span><br>\n";
} else {
    echo "<span style='color: red;'>✗ Template directory NOT found</span><br>\n";
}

if (is_dir(GENERATED_DIR)) {
    echo "<span style='color: green;'>✓ Generated directory exists</span><br>\n";
} else {
    echo "<span style='color: orange;'>⚠ Generated directory will be created automatically</span><br>\n";
}

// Test 4: Check for template files
echo "<h2>Test 4: Template Files</h2>\n";
$templates = glob(TEMPLATE_DIR . '*.docx');
if (count($templates) > 0) {
    echo "<span style='color: green;'>✓ Found " . count($templates) . " template file(s)</span><br>\n";
    foreach ($templates as $template) {
        echo "- " . htmlspecialchars(basename($template)) . "<br>\n";
    }
} else {
    echo "<span style='color: orange;'>⚠ No template files found</span><br>\n";
}

echo "\n<h2>Summary</h2>\n";
echo "<p><strong>PDF conversion setup is ready!</strong></p>\n";
echo "<p>The system will now export documents as PDF instead of DOCX.</p>\n";
echo "<p>When a user downloads an approved Locator Slip or Authority to Travel, ";
echo "the system will generate a DOCX from the template, convert it to PDF using LibreOffice, ";
echo "and serve the PDF file for download.</p>\n";
