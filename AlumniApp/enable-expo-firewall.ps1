# Allows phones on the same Wi-Fi to reach Metro (ports 8081-8082).
# Double-click enable-expo-firewall.cmd OR run this script as Administrator.

$ruleName = "Expo Metro Bundler (8081-8082)"

function Test-IsAdmin {
    $id = [Security.Principal.WindowsIdentity]::GetCurrent()
    $p = New-Object Security.Principal.WindowsPrincipal($id)
    return $p.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

if (-not (Test-IsAdmin)) {
    Write-Host "Requesting Administrator approval..." -ForegroundColor Yellow
    $scriptPath = $MyInvocation.MyCommand.Path
    Start-Process powershell.exe -Verb RunAs -ArgumentList @(
        "-NoProfile",
        "-ExecutionPolicy", "Bypass",
        "-File", "`"$scriptPath`""
    )
    exit 0
}

$existing = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
if ($existing) {
    Write-Host "Firewall rule already exists: $ruleName" -ForegroundColor Green
} else {
    New-NetFirewallRule `
        -DisplayName $ruleName `
        -Direction Inbound `
        -Protocol TCP `
        -LocalPort 8081-8082 `
        -Action Allow `
        -Profile Private,Domain | Out-Null
    Write-Host "Created firewall rule: $ruleName" -ForegroundColor Green
}

Write-Host "Done. Restart start-dev.ps1, then open exp://192.168.1.17:8081 in Expo Go." -ForegroundColor Cyan
Read-Host "Press Enter to close"
