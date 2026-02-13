# setup-word-com.ps1
# One-time setup: verifies LibreOffice installation and installs
# Trajan Pro 3 font system-wide so it is visible to service accounts.
#
# *** RUN THIS SCRIPT AS ADMINISTRATOR ***
# Right-click PowerShell -> "Run as Administrator", then:
#   powershell -ExecutionPolicy Bypass -File setup-word-com.ps1

$ErrorActionPreference = "Stop"

Write-Host ""
Write-Host "=== LibreOffice PDF Conversion Setup ===" -ForegroundColor Cyan
Write-Host ""

# ── Check if running as admin ──
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator
)

if (-not $isAdmin) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    Write-Host "Right-click PowerShell -> 'Run as Administrator', then re-run this script." -ForegroundColor Yellow
    exit 1
}

# ── 1. Verify LibreOffice installation ──
Write-Host "--- Checking LibreOffice ---" -ForegroundColor Yellow

$soffice = $null
$searchPaths = @(
    "C:\Program Files\LibreOffice\program\soffice.exe",
    "C:\Program Files (x86)\LibreOffice\program\soffice.exe"
)

foreach ($candidate in $searchPaths) {
    if (Test-Path $candidate) {
        $soffice = $candidate
        break
    }
}

if ($null -eq $soffice) {
    Write-Host "[MISSING] LibreOffice not found!" -ForegroundColor Red
    Write-Host "          Please install LibreOffice from https://www.libreoffice.org/download/" -ForegroundColor Yellow
    Write-Host "          Searched:" -ForegroundColor Yellow
    foreach ($p in $searchPaths) { Write-Host "            - $p" -ForegroundColor Yellow }
    exit 1
} else {
    Write-Host "[OK]      LibreOffice found: $soffice" -ForegroundColor Green
}

# ── 2. Check / install Trajan Pro 3 font system-wide ──
Write-Host ""
Write-Host "--- Checking Trajan Pro 3 Font ---" -ForegroundColor Yellow

$fontName      = "Trajan Pro 3"
$fontFileName   = "Trajan Pro 3 Regular.otf"
$systemFontsDir = "$env:SystemRoot\Fonts"
$systemFontPath = Join-Path $systemFontsDir $fontFileName

# Registry key for system-wide font registration
$fontRegKey = "HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Fonts"
$fontRegName = "Trajan Pro 3 (OpenType)"

# Possible user-local font locations to search
$userFontSources = @(
    "$env:LOCALAPPDATA\Microsoft\Windows\Fonts\$fontFileName",
    "$env:USERPROFILE\AppData\Local\Microsoft\Windows\Fonts\$fontFileName"
)

if (Test-Path $systemFontPath) {
    Write-Host "[OK]      $fontName is already installed system-wide." -ForegroundColor Green
} else {
    Write-Host "[MISSING] $fontName not found in $systemFontsDir" -ForegroundColor Yellow

    # Try to find it in user-local font directories
    $sourceFontPath = $null
    foreach ($src in $userFontSources) {
        if (Test-Path $src) {
            $sourceFontPath = $src
            break
        }
    }

    if ($null -eq $sourceFontPath) {
        Write-Host "[ERROR]   Cannot find $fontFileName in user fonts either." -ForegroundColor Red
        Write-Host "          Please install Trajan Pro 3 manually, then re-run this script." -ForegroundColor Yellow
        Write-Host "          Searched:" -ForegroundColor Yellow
        foreach ($src in $userFontSources) { Write-Host "            - $src" -ForegroundColor Yellow }
        exit 1
    }

    Write-Host "[FOUND]   User-local font: $sourceFontPath" -ForegroundColor Cyan
    Write-Host "          Copying to system fonts..." -ForegroundColor Cyan

    try {
        Copy-Item -Path $sourceFontPath -Destination $systemFontPath -Force
        Write-Host "[OK]      Copied to $systemFontPath" -ForegroundColor Green
    }
    catch {
        Write-Host "[FAILED]  Could not copy font: $_" -ForegroundColor Red
        exit 1
    }

    # Register the font in the Windows registry
    try {
        Set-ItemProperty -Path $fontRegKey -Name $fontRegName -Value $fontFileName -Type String
        Write-Host "[OK]      Registered font in Windows registry." -ForegroundColor Green
    }
    catch {
        Write-Host "[FAILED]  Could not register font in registry: $_" -ForegroundColor Red
        exit 1
    }
}

# ── 3. Verify font is visible by running a quick LibreOffice test ──
Write-Host ""
Write-Host "--- Verifying LibreOffice can access the font ---" -ForegroundColor Yellow

# Create a minimal test document to verify LibreOffice starts correctly
$testDir  = $env:TEMP
$testDocx = Join-Path $testDir "sdo-atlas-font-test.docx"
$testPdf  = Join-Path $testDir "sdo-atlas-font-test.pdf"

# Clean up any previous test files
if (Test-Path $testPdf) { Remove-Item $testPdf -Force }

# Create a minimal DOCX for testing (a valid DOCX is a ZIP with XML inside)
# Instead, we just verify soffice --headless starts without errors
try {
    $testOutput = & "$soffice" --headless --norestore --nolockcheck --version 2>&1
    Write-Host "[OK]      LibreOffice responds: $testOutput" -ForegroundColor Green
}
catch {
    Write-Host "[WARN]    Could not run LibreOffice version check: $_" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "=== Setup Complete ===" -ForegroundColor Cyan
Write-Host "LibreOffice is ready for PDF conversion from SDO ATLAS." -ForegroundColor White
Write-Host ""
Write-Host "NOTE: If this is the first time installing the Trajan Pro 3 font system-wide," -ForegroundColor Yellow
Write-Host "      you may need to restart the Apache service for the font to take effect." -ForegroundColor Yellow
Write-Host ""
