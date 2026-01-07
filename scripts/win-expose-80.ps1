# Expose localhost:80 to LAN on Windows when running via WSL2/Docker
# Run this in an elevated PowerShell (Run as Administrator).

$listenAddress = "0.0.0.0"
$listenPort = 80
$connectAddress = "127.0.0.1"
$connectPort = 80

Write-Host "Adding portproxy ${listenAddress}:${listenPort} -> ${connectAddress}:${connectPort}"
netsh interface portproxy add v4tov4 listenaddress=$listenAddress listenport=$listenPort connectaddress=$connectAddress connectport=$connectPort | Out-Null

Write-Host "Opening Windows Firewall for TCP port 80"
if (-not (Get-NetFirewallRule -DisplayName "Magazzino HTTP 80" -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -DisplayName "Magazzino HTTP 80" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow | Out-Null
}

Write-Host "Current portproxy rules:"
netsh interface portproxy show v4tov4

Write-Host "Current listeners on port 80:"
Get-NetTCPConnection -LocalPort 80 | Format-Table -AutoSize
