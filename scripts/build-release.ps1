# Build a BizSpine release ZIP for shared hosting (no Composer/Node on the server).
# Usage: .\scripts\build-release.ps1 [-Subdir BizSpine] [-SiteUrl https://example.com/BizSpine]

param(
    [string]$Subdir = "BizSpine",
    [string]$SiteUrl = ""
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Backend = Join-Path $Root "backend"
$Frontend = Join-Path $Root "frontend"
$Deploy = Join-Path $Root "deploy"
$Out = Join-Path $Root "release"
$ZipName = "BizSpine-release.zip"

Write-Host "BizSpine release build" -ForegroundColor Cyan
Write-Host "======================" -ForegroundColor Cyan

if (-not (Test-Path (Join-Path $Backend "composer.json"))) {
    throw "backend/ not found at $Backend"
}

# Frontend build
$basePath = "/$Subdir/"
$apiBase = if ($SiteUrl) { "$($SiteUrl.TrimEnd('/'))/api" } else { "" }

Write-Host "Building frontend (VITE_BASE_PATH=$basePath)..." -ForegroundColor Yellow
Push-Location $Frontend
if (-not (Test-Path "node_modules")) {
    npm ci
}
$env:VITE_BASE_PATH = $basePath
if ($apiBase) {
    $env:VITE_API_BASE_URL = $apiBase
} else {
    Remove-Item Env:VITE_API_BASE_URL -ErrorAction SilentlyContinue
}
npm run build
Pop-Location

# Backend dependencies (production)
Write-Host "Installing PHP dependencies (production)..." -ForegroundColor Yellow
Push-Location $Backend
composer install --no-dev --optimize-autoloader --no-interaction
Pop-Location

# Assemble release tree
if (Test-Path $Out) {
    Remove-Item $Out -Recurse -Force
}
$Staging = Join-Path $Out "staging"
$BackendOut = Join-Path $Staging "bizspine-backend"
$WebOut = Join-Path $Staging "public_html" $Subdir
$ApiOut = Join-Path $WebOut "api"

New-Item -ItemType Directory -Path $BackendOut -Force | Out-Null
New-Item -ItemType Directory -Path $WebOut -Force | Out-Null
New-Item -ItemType Directory -Path $ApiOut -Force | Out-Null

Write-Host "Copying backend..." -ForegroundColor Yellow
$backendExclude = @("coverage-report", "tests", ".phpunit.cache", "node_modules")
Get-ChildItem $Backend -Force | Where-Object { $_.Name -notin $backendExclude } | ForEach-Object {
    Copy-Item $_.FullName -Destination $BackendOut -Recurse -Force
}
# Never ship a dev database or secrets — install.php treats any DB with users as "already installed"
@(
    (Join-Path $BackendOut ".env"),
    (Join-Path $BackendOut "protected\db\database.sqlite"),
    (Join-Path $BackendOut "protected\database\database.sqlite")
) | ForEach-Object {
    if (Test-Path $_) { Remove-Item $_ -Force }
}
Get-ChildItem $BackendOut -Recurse -Filter ".bizspine-installed" -ErrorAction SilentlyContinue |
    Remove-Item -Force
New-Item -ItemType Directory -Path (Join-Path $BackendOut "protected\db") -Force | Out-Null

Write-Host "Copying frontend dist..." -ForegroundColor Yellow
Copy-Item (Join-Path $Frontend "dist\*") -Destination $WebOut -Recurse -Force

# Deploy assets
Copy-Item (Join-Path $Deploy "BizSpine-frontend.htaccess") (Join-Path $WebOut ".htaccess") -Force
$htaccess = Get-Content (Join-Path $WebOut ".htaccess") -Raw
$htaccess = $htaccess -replace '/BizSpine/', "/$Subdir/"
Set-Content (Join-Path $WebOut ".htaccess") $htaccess -NoNewline

Copy-Item (Join-Path $Deploy "BizSpine-api-index.php") (Join-Path $ApiOut "index.php") -Force
Copy-Item (Join-Path $Deploy "BizSpine-api.htaccess") (Join-Path $ApiOut ".htaccess") -Force
$apiHt = Get-Content (Join-Path $ApiOut ".htaccess") -Raw
$apiHt = $apiHt -replace '/BizSpine/', "/$Subdir/"
Set-Content (Join-Path $ApiOut ".htaccess") $apiHt -NoNewline

Copy-Item (Join-Path $Deploy "install.php") (Join-Path $WebOut "install.php") -Force
Copy-Item (Join-Path $Deploy "INSTALL.html") (Join-Path $WebOut "INSTALL.html") -Force

# ZIP
$ZipPath = Join-Path $Out $ZipName
if (Test-Path $ZipPath) {
    Remove-Item $ZipPath -Force
}
Write-Host "Creating $ZipName..." -ForegroundColor Yellow
Compress-Archive -Path (Join-Path $Staging "*") -DestinationPath $ZipPath -Force

Remove-Item $Staging -Recurse -Force

Write-Host ""
Write-Host "Done: $ZipPath" -ForegroundColor Green
Write-Host "Upload and extract on the server, then open /$Subdir/install.php"
