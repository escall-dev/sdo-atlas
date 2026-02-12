# setup-word-com.ps1
# One-time setup: creates Desktop folders required by Word COM automation
# when running under a service account (Apache / IIS / SYSTEM).
#
# *** RUN THIS SCRIPT AS ADMINISTRATOR ***
# Right-click PowerShell -> "Run as Administrator", then:
#   powershell -ExecutionPolicy Bypass -File setup-word-com.ps1

$ErrorActionPreference = "Stop"

Write-Host ""
Write-Host "=== Word COM Automation Setup ===" -ForegroundColor Cyan
Write-Host ""

# Check if running as admin
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator
)

if (-not $isAdmin) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    Write-Host "Right-click PowerShell -> 'Run as Administrator', then re-run this script." -ForegroundColor Yellow
    exit 1
}

# Desktop folders that Word COM needs under service-account profiles
$desktopPaths = @(
    "$env:SystemRoot\System32\config\systemprofile\Desktop",
    "$env:SystemRoot\SysWOW64\config\systemprofile\Desktop"
)

foreach ($path in $desktopPaths) {
    if (Test-Path $path) {
        Write-Host "[OK]      $path (already exists)" -ForegroundColor Green
    } else {
        try {
            New-Item -ItemType Directory -Path $path -Force | Out-Null
            Write-Host "[CREATED] $path" -ForegroundColor Green
        }
        catch {
            Write-Host "[FAILED]  $path - $_" -ForegroundColor Red
        }
    }
}

Write-Host ""
Write-Host "=== Setup Complete ===" -ForegroundColor Cyan
Write-Host "You can now use 'Save as PDF' from the SDO ATLAS application." -ForegroundColor White
Write-Host ""
