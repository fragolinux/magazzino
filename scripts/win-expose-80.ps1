# Expose WSL port 80 to LAN on Windows via portproxy.
# Run this in an elevated PowerShell (Run as Administrator).

$listenAddress = "0.0.0.0"
$listenPort = 80
$connectPort = 80

$wslIpRaw = (wsl hostname -I 2>$null)
if (-not $wslIpRaw) {
    Write-Error "Impossibile leggere l'IP WSL (wsl hostname -I). WSL è attivo?"
    exit 1
}
$connectAddress = ($wslIpRaw.Trim().Split(' ') | Where-Object { $_ })[0]
if (-not $connectAddress) {
    Write-Error "IP WSL non valido: $wslIpRaw"
    exit 1
}

Write-Host "Removing existing portproxy ${listenAddress}:${listenPort} (if any)"
netsh interface portproxy delete v4tov4 listenaddress=$listenAddress listenport=$listenPort | Out-Null

Write-Host "Adding portproxy ${listenAddress}:${listenPort} -> ${connectAddress}:${connectPort}"
netsh interface portproxy add v4tov4 listenaddress=$listenAddress listenport=$listenPort connectaddress=$connectAddress connectport=$connectPort | Out-Null

Write-Host "Opening Windows Firewall for TCP port 80"
if (-not (Get-NetFirewallRule -DisplayName "Magazzino HTTP 80" -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -DisplayName "Magazzino HTTP 80" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow | Out-Null
}
Set-NetFirewallRule -DisplayName "Magazzino HTTP 80" -EdgeTraversalPolicy Allow | Out-Null

Write-Host "Current portproxy rules:"
netsh interface portproxy show v4tov4

Write-Host "Current listeners on port 80:"
Get-NetTCPConnection -LocalPort 80 | Format-Table -AutoSize
