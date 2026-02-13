# convert-to-pdf.ps1
# Converts DOCX to PDF using LibreOffice headless mode
# Usage: powershell -ExecutionPolicy Bypass -File convert-to-pdf.ps1 -inputPath "C:\path\to\file.docx" -outputPath "C:\path\to\output.pdf"

param(
    [Parameter(Mandatory=$true)]
    [string]$inputPath,

    [Parameter(Mandatory=$true)]
    [string]$outputPath
)

# Resolve to absolute paths
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

# ── Locate LibreOffice soffice.com (console mode binary) ──
$soffice = $null
$searchPaths = @(
    "C:\Program Files\LibreOffice\program\soffice.com",
    "C:\Program Files (x86)\LibreOffice\program\soffice.com",
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
    Write-Error "LibreOffice not found. Searched: $($searchPaths -join ', ')"
    exit 1
}

# ── Create a dedicated LibreOffice user profile for service-account usage ──
$libreProfile = Join-Path $env:TEMP "sdo-atlas-libreoffice"
if (-not (Test-Path $libreProfile)) {
    New-Item -ItemType Directory -Path $libreProfile -Force | Out-Null
}

# ── Mutex: prevent concurrent LibreOffice instances ──
$mutexName = "Global\SDOAtlasPDFConversion"
$mutex     = $null
$acquired  = $false

try {
    # Acquire mutex lock (wait up to 120 seconds)
    $mutex = New-Object System.Threading.Mutex($false, $mutexName)
    $acquired = $mutex.WaitOne(120000)

    if (-not $acquired) {
        Write-Error "Timeout: Could not acquire conversion lock within 120 seconds."
        exit 2
    }

    # LibreOffice outputs PDF with the same base name as the input file
    $inputBaseName  = [System.IO.Path]::GetFileNameWithoutExtension($inputPath)
    $libreOutputPdf = Join-Path $outputDir "$inputBaseName.pdf"

    # Build the argument list as a proper array
    # Use file:/// URI with forward slashes for -env:UserInstallation
    $profileUri = "file:///" + ($libreProfile -replace '\\', '/')
    $argList = @(
        "--headless",
        "--norestore",
        "--nolockcheck",
        "-env:UserInstallation=$profileUri",
        "--convert-to", "pdf",
        "--outdir", $outputDir,
        $inputPath
    )

    # Run LibreOffice using direct invocation with & operator
    # Capture all output (stdout + stderr merged via 2>&1)
    $conversionOutput = & $soffice $argList 2>&1 | Out-String
    $exitCode = $LASTEXITCODE

    # LibreOffice may return 0 even on some failures, so always check the PDF
    if (($null -ne $exitCode) -and ($exitCode -ne 0)) {
        Write-Error "LibreOffice exited with code $exitCode : $conversionOutput"
        exit 3
    }

    # If the LibreOffice output name differs from the expected output path, rename it
    if ($libreOutputPdf -ne $outputPath) {
        if (Test-Path $libreOutputPdf) {
            Move-Item -Path $libreOutputPdf -Destination $outputPath -Force
        }
    }

    # Verify the PDF was created
    if (-not (Test-Path $outputPath)) {
        Write-Error "PDF file was not created at: $outputPath -- LibreOffice output: $conversionOutput"
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
    # Release mutex
    if ($null -ne $mutex) {
        try {
            if ($acquired) { $mutex.ReleaseMutex() }
            $mutex.Dispose()
        }
        catch { }
    }
}
