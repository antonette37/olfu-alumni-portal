# CCS Alumni Portal - Expo dev server (LAN, SDK 53)
# Usage: .\start-dev.ps1
# Run from the AlumniApp folder in PowerShell.

$ErrorActionPreference = "Continue"
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Root

$Port = 8081

# Remove export output — prevents Metro watcher crash (dist\_expo invalid paths on Windows)
if (Test-Path -LiteralPath "dist") {
    Write-Host "Removing dist/ (export cache)..." -ForegroundColor Yellow
    cmd /c "if exist dist rmdir /s /q dist" 2>$null
    Start-Sleep -Milliseconds 500
}

Write-Host "Checking for process on port $Port..." -ForegroundColor Cyan

$portPid = (netstat -ano | Select-String ":$Port\s" | ForEach-Object {
    ($_ -split '\s+')[-1]
} | Select-Object -First 1)

if ($portPid -and $portPid -match '^\d+$') {
    Write-Host "Killing PID $portPid on port $Port..." -ForegroundColor Yellow
    taskkill /PID $portPid /F | Out-Null
    Start-Sleep -Seconds 1
} else {
    Write-Host "Port $Port is free." -ForegroundColor Green
}

$lanIp = $null
try {
    $lanIp = (
        Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object {
            $_.IPAddress -notlike "127.*" -and
            $_.IPAddress -notlike "169.254.*" -and
            $_.PrefixOrigin -ne "WellKnown"
        } |
        Select-Object -First 1 -ExpandProperty IPAddress
    )
} catch { }

$fwRule = Get-NetFirewallRule -DisplayName "Expo Metro Bundler (8081-8082)" -ErrorAction SilentlyContinue

Write-Host ""
Write-Host "Starting Expo (LAN, port $Port)..." -ForegroundColor Cyan
if (-not $fwRule) {
    Write-Host "WARNING: No Windows Firewall rule for port $Port." -ForegroundColor Red
    Write-Host "  Phones often cannot connect until you run (as Administrator):" -ForegroundColor Red
    Write-Host "  .\enable-expo-firewall.ps1" -ForegroundColor Yellow
}
Write-Host "Phone and PC must use the SAME Wi-Fi (not mobile data)." -ForegroundColor Yellow
Write-Host ""
if ($lanIp) {
    Write-Host "Expo Go URL (this network only): exp://${lanIp}:${Port}" -ForegroundColor Green
} else {
    Write-Host "Expo Go: scan the QR code in this terminal, or use exp://<your-pc-ip>:${Port}" -ForegroundColor Green
}
Write-Host "Tip: Connect PC to your phone's mobile hotspot if you have no shared Wi-Fi." -ForegroundColor DarkGray
Write-Host ""

npx expo start --lan --clear --port $Port
