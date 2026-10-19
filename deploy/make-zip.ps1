# Builds the deploy/transfer zip of the files listed in deploy/MANIFEST.txt.
# Paths resolve relative to THIS script's own folder, so it works from any CWD:
#   powershell -File deploy/make-zip.ps1           (from the repo root)
#   powershell -File make-zip.ps1                  (from inside deploy)
# The zip name carries a ddMMyyHHmmss timestamp.
param(
    [string]$Root,       # repo root (default: the parent of deploy)
    [string]$Manifest,   # file list (default: deploy/MANIFEST.txt)
    [string]$Out         # output zip (default: deploy/shf-payout-changes-<ddMMyyHHmmss>.zip)
)

$ScriptDir = $PSScriptRoot
if (-not $ScriptDir) { $ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path }
if (-not $Root)     { $Root     = (Resolve-Path (Join-Path $ScriptDir '..')).Path }
if (-not $Manifest) { $Manifest = Join-Path $ScriptDir 'MANIFEST.txt' }
if (-not $Out)      { $Out      = Join-Path $ScriptDir ('shf-payout-changes-' + (Get-Date -Format 'ddMMyyHHmmss') + '.zip') }

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$rootFull = (Resolve-Path $Root).Path
# Keep only the newest build — remove any previous shf-payout-changes-*.zip.
Get-ChildItem -Path $ScriptDir -Filter 'shf-payout-changes-*.zip' -ErrorAction SilentlyContinue | Remove-Item -Force -ErrorAction SilentlyContinue
if (Test-Path $Out) { Remove-Item $Out -Force }

$fs = [System.IO.File]::Open($Out, [System.IO.FileMode]::Create)
$arch = New-Object System.IO.Compression.ZipArchive($fs, [System.IO.Compression.ZipArchiveMode]::Create)
$count = 0
foreach ($line in Get-Content $Manifest) {
    $rel = $line.Trim()
    if ($rel -eq "") { continue }
    $src = Join-Path $rootFull ($rel -replace "/", "\")
    if (-not (Test-Path $src)) { Write-Host "MISSING: $rel"; continue }
    # entryName uses the forward-slash path from the manifest (zip-spec correct, Linux-safe)
    [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($arch, $src, $rel, [System.IO.Compression.CompressionLevel]::Optimal)
    $count++
}
$arch.Dispose()
$fs.Dispose()
Write-Host "Wrote $Out with $count entries."
