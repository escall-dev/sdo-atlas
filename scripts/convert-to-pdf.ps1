# convert-to-pdf.ps1
# Converts DOCX to PDF using Microsoft Word COM Automation
# Usage: powershell -ExecutionPolicy Bypass -File convert-to-pdf.ps1 -inputPath "C:\path\to\file.docx" -outputPath "C:\path\to\output.pdf"

param(
    [Parameter(Mandatory=$true)]
    [string]$inputPath,

    [Parameter(Mandatory=$true)]
    [string]$outputPath
)

# Resolve to absolute paths (Word COM requires full paths)
$inputPath  = [System.IO.Path]::GetFullPath($inputPath)
$outputPath = [System.IO.Path]::GetFullPath($outputPath)

# Validate input file exists
if (-not (Test-Path $inputPath)) {
    Write-Error "Input file not found: $inputPath"
    exit 1
}

# Ensure output directory exists
$outputDir = Split-Path -Parent $outputPath
if (-not (Test-Path $outputDir)) {
    New-Item -ItemType Directory -Path $outputDir -Force | Out-Null
}

# ── Word COM requires a Desktop folder under the service-account profile ──
# Without these folders, Documents.Open() silently returns $null when
# Word is launched by Apache / IIS / SYSTEM / a service account.
foreach ($profileDesktop in @(
    "$env:SystemRoot\System32\config\systemprofile\Desktop",
    "$env:SystemRoot\SysWOW64\config\systemprofile\Desktop"
)) {
    if (-not (Test-Path $profileDesktop)) {
        try { New-Item -ItemType Directory -Path $profileDesktop -Force | Out-Null }
        catch { }   # may fail without admin rights – that is OK if folder already exists
    }
}

# Constants
$wdFormatPDF         = 17
$wdDoNotSaveChanges  = 0

# Use a mutex to prevent multiple simultaneous Word instances
$mutexName = "Global\SDOAtlasWordConversion"
$mutex     = $null
$acquired  = $false
$word      = $null
$doc       = $null

try {
    # Acquire mutex lock (wait up to 120 seconds)
    $mutex = New-Object System.Threading.Mutex($false, $mutexName)
    $acquired = $mutex.WaitOne(120000)

    if (-not $acquired) {
        Write-Error "Timeout: Could not acquire conversion lock within 120 seconds."
        exit 2
    }

    # Instantiate Word COM object
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0            # wdAlertsNone
    $word.AutomationSecurity = 3       # msoAutomationSecurityForceDisable – block all macros

    # Open the DOCX file
    #   FileName, ConfirmConversions, ReadOnly, AddToRecentFiles, PasswordDocument,
    #   PasswordTemplate, Revert, WritePasswordDocument, WritePasswordTemplate,
    #   Format, Encoding, Visible, OpenAndRepair
    # Explicitly passing $false for dialog-triggering params to guarantee silence.
    $doc = $word.Documents.Open(
        $inputPath,                    # FileName
        $false,                        # ConfirmConversions
        $false,                        # ReadOnly
        $false                         # AddToRecentFiles
    )

    if ($null -eq $doc) {
        Write-Error "Word failed to open document: $inputPath"
        exit 3
    }

    # Save as PDF (wdFormatPDF = 17)
    # SaveAs2 is the modern API (Word 2010+). Fall back to SaveAs for older installs.
    try {
        $doc.SaveAs2($outputPath, $wdFormatPDF)
    }
    catch {
        $doc.SaveAs($outputPath, $wdFormatPDF)
    }

    # Close the document without saving changes
    $doc.Close($wdDoNotSaveChanges)
    $doc = $null

    # Verify the PDF was created
    if (-not (Test-Path $outputPath)) {
        Write-Error "PDF file was not created at: $outputPath"
        exit 3
    }

    Write-Output "SUCCESS: PDF created at $outputPath"
    exit 0
}
catch {
    Write-Error "Conversion failed: $($_.Exception.Message)"
    exit 4
}
finally {
    # Clean up COM objects
    if ($null -ne $doc) {
        try { $doc.Close($wdDoNotSaveChanges) } catch { }
    }

    if ($null -ne $word) {
        try { $word.Quit() } catch { }
        try {
            [System.Runtime.InteropServices.Marshal]::ReleaseComObject($word) | Out-Null
        }
        catch { }
    }

    # Release mutex
    if ($null -ne $mutex) {
        try {
            if ($acquired) { $mutex.ReleaseMutex() }
            $mutex.Dispose()
        }
        catch { }
    }

    # Force garbage collection to ensure COM cleanup
    [System.GC]::Collect()
    [System.GC]::WaitForPendingFinalizers()
}
