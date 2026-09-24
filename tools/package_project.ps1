# tools/package_project.ps1 — Script đóng gói dự án SEB
# Chạy từ thư mục gốc SEB trên máy cũ (PowerShell hoặc command line):
#     powershell -ExecutionPolicy Bypass -File tools\package_project.ps1

$src  = (Get-Location).Path
$zipOut = Join-Path (Split-Path $src -Parent) "SEB_sourcecode.zip"

$stage = "D:\SEB_stage_zip"
if (Test-Path $stage) { Remove-Item $stage -Recurse -Force }
New-Item -ItemType Directory -Path $stage | Out-Null

Write-Host "Đang copy dự án (bỏ node_modules/dist_bak/tmp/.git)..."
$excludeDirs = "node_modules",".git",".cursor",".vscode","dist_bak","dist_bak_backup","tmp","log"
Copy-Item (Join-Path $src "*") $stage -Recurse -ErrorAction SilentlyContinue

foreach ($d in $excludeDirs) {
    $path = Join-Path $stage $d
    if (Test-Path $path) {
        Remove-Item $path -Recurse -Force
        Write-Host "  - đã loại: $d"
    }
}

# Nén thành zip
if (Test-Path $zipOut) { Remove-Item $zipOut -Force }
Compress-Archive -Path (Join-Path $stage "*") -DestinationPath $zipOut -Force

# dọn stage
Remove-Item $stage -Recurse -Force

$sizeMB = [math]::Round((Get-Item $zipOut).Length / 1MB, 2)
Write-Host ""
Write-Host "✓ Đóng gói xong: $zipOut (${sizeMB}MB)"
Write-Host "  Copy file zip này junto với SEB_BAK.bak (DB backup) sang máy mới."