# Windows / WSL

**Importante:** su Windows "puro" restano validi gli script PowerShell, ma **menu interattivo** e **target Make** non sono disponibili.
Se vuoi usare `make` e il menu `dialog`, usa WSL o Linux.

## Avvio rapido (Windows puro)

PowerShell:
```powershell
git clone https://github.com/fragolinux/magazzino.git
cd magazzino
.\scripts\start.ps1
```

## Note WSL

- Per prestazioni migliori, tieni il repo dentro il filesystem WSL e lancia `docker compose` da WSL.
- Se usi Docker Desktop, assicurati che l'integrazione WSL sia attiva (altrimenti non serve).

### Avvio pulito (Windows + WSL Ubuntu 24.04, usando lo script di setup)

```powershell
wsl --install -d Ubuntu-24.04
wsl -d Ubuntu-24.04
```

Nota importante: al primo `wsl -d Ubuntu-24.04` ti verrà chiesto di creare utente e password.
Completa quel passo, poi continua con i comandi successivi (non copiare tutto in un unico blocco).

```powershell
wsl --terminate Ubuntu-24.04
wsl -d Ubuntu-24.04 -u root -- bash -lc "curl -fsSL https://raw.githubusercontent.com/fragolinux/magazzino/refs/heads/main/setup_wsl_ubuntu.sh -o /tmp/setup_wsl_ubuntu.sh && bash /tmp/setup_wsl_ubuntu.sh --with-zsh"
wsl --terminate Ubuntu-24.04
wsl -d Ubuntu-24.04
```

Se usi una distro Linux non-WSL, apri la porta 80 nel firewall del sistema (es. `ufw`, `firewalld`) se necessario.
Se usi Docker Desktop su Windows, la porta 80 viene pubblicata direttamente sull'host, ma potresti comunque dover aprire il firewall di Windows per l'accesso dalla LAN.

### Accesso da LAN (WSL su Windows)

Se vuoi accedere dall'esterno (LAN), serve esporre la porta 80 con un portproxy di Windows
(punta all'IP WSL, che può cambiare dopo reboot).
Apri PowerShell come amministratore e lancia:

```powershell
Set-Location "\\wsl.localhost\<Distro>\home\<utente>\magazzino"
powershell -ExecutionPolicy Bypass -File .\scripts\win-expose-80.ps1
```

Sostituisci `<Distro>` con il nome della tua distribuzione WSL (es. `Ubuntu-24.04`) e `<utente>`
con il tuo username in WSL.
Se non sai il nome della distribuzione, puoi scoprirlo da PowerShell con `wsl -l -v`.

