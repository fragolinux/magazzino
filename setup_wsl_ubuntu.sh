#!/usr/bin/env bash
set -euo pipefail

# Setup WSL/Ubuntu for this repo (Linux/WSL).
# Installs prerequisites: git, make, dialog, docker, docker compose.
# Optional: Zsh + Oh My Zsh + Docker completions.

timestamp(){ date +"%Y%m%d%H%M%S"; }
have(){ command -v "$1" >/dev/null 2>&1; }
say(){ echo -e "$@"; }

NEED_WSL_RESTART=0

as_root(){
  if [[ $EUID -eq 0 ]]; then
    "$@"
  else
    sudo "$@"
  fi
}

apt_install(){
  local pkgs=("$@")
  if [[ $EUID -eq 0 ]]; then
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
      -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" \
      "${pkgs[@]}"
  else
    DEBIAN_FRONTEND=noninteractive sudo apt-get install -y --no-install-recommends \
      -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" \
      "${pkgs[@]}"
  fi
}

detect_target_user(){
  if [[ -n "${SUDO_USER:-}" && "${SUDO_USER}" != "root" ]]; then
    echo "$SUDO_USER"
    return
  fi
  if id -u ubuntu >/dev/null 2>&1; then
    echo "ubuntu"
    return
  fi
  # pick first regular user (uid >= 1000)
  getent passwd | awk -F: '$3>=1000 && $1!="nobody"{print $1; exit}'
}

detect_uid1000_user(){
  getent passwd 1000 2>/dev/null | cut -d: -f1
}

run_as_user(){
  local user="$1"
  shift
  if [[ -n "$user" ]]; then
    su - "$user" -c "$*"
  else
    "$@"
  fi
}

pkg_installed(){
  dpkg -s "$1" >/dev/null 2>&1
}

backup_file(){
  local f="$1"
  if [[ -e "$f" ]]; then
    local b="${f}.bak.$(timestamp)"
    cp -a "$f" "$b"
    say "   ✓ Backup creato: $b"
  fi
}

is_wsl(){
  grep -qi microsoft /proc/version 2>/dev/null
}

is_windows_docker(){
  local p
  p="$(command -v docker 2>/dev/null || true)"
  [[ -n "$p" && "$p" == /mnt/c/* ]]
}

strip_windows_path(){
  local new_path
  new_path="$(printf '%s' "$PATH" | tr ':' '\n' | grep -v '^/mnt/c/' | paste -sd ':' -)"
  if [[ -n "$new_path" ]]; then
    export PATH="$new_path"
  fi
}

ensure_wsl_conf(){
  local desired
  desired=$(cat <<EOF
[interop]
appendWindowsPath=false

[user]
default=${TARGET_USER}

[boot]
systemd=true
EOF
)
  if [[ ! -f /etc/wsl.conf ]] || ! grep -qi '^\[interop\]' /etc/wsl.conf || ! grep -qi '^appendWindowsPath=false' /etc/wsl.conf || ! grep -qi '^\[user\]' /etc/wsl.conf || ! grep -qi "^default=${TARGET_USER}$" /etc/wsl.conf || ! grep -qi '^\[boot\]' /etc/wsl.conf || ! grep -qi '^systemd=true' /etc/wsl.conf; then
    say "⚠️  WSL: imposto appendWindowsPath=false e utente di default (${TARGET_USER})."
    as_root sh -c "printf '%s\n' \"$desired\" > /etc/wsl.conf"
    NEED_WSL_RESTART=1
  fi
}

ensure_wsl_start_home(){
  local f="/etc/profile.d/99-wsl-cd-home.sh"
  local content
  content=$(cat <<'EOF'
# Managed by setup_wsl_ubuntu.sh
# If shell starts in Windows path, jump to Linux home.
if [[ -n "${WSL_DISTRO_NAME:-}" ]]; then
  case "$PWD" in
    /mnt/*) cd "$HOME" ;;
  esac
fi
EOF
)
  if [[ ! -f "$f" ]] || ! grep -q 'setup_wsl_ubuntu.sh' "$f" || ! grep -q '\${WSL_DISTRO_NAME:-}' "$f"; then
    if [[ $EUID -eq 0 ]]; then
      printf '%s\n' "$content" > "$f"
    else
      printf '%s\n' "$content" | sudo tee "$f" >/dev/null
    fi
  fi
}

ensure_bashrc_cd_home(){
  local f="/etc/bash.bashrc"
  local block
  block=$(cat <<'EOF'
# setup_wsl_ubuntu.sh: cd home if in /mnt
if [[ -n "${WSL_DISTRO_NAME:-}" ]]; then
  case "$PWD" in
    /mnt/*) cd "$HOME" ;;
  esac
fi
EOF
)
  if ! grep -q "setup_wsl_ubuntu.sh: cd home if in /mnt" "$f" 2>/dev/null; then
    if [[ $EUID -eq 0 ]]; then
      printf '%s\n' "$block" >> "$f"
    else
      printf '%s\n' "$block" | sudo tee -a "$f" >/dev/null
    fi
  fi
}

ensure_zshrc_cd_home(){
  local f="/etc/zsh/zshrc"
  local block
  block=$(cat <<'EOF'
# setup_wsl_ubuntu.sh: cd home if in /mnt
if [[ -n "${WSL_DISTRO_NAME:-}" ]]; then
  case "$PWD" in
    /mnt/*) cd "$HOME" ;;
  esac
fi
EOF
)
  if [[ -f "$f" ]] && ! grep -q "setup_wsl_ubuntu.sh: cd home if in /mnt" "$f" 2>/dev/null; then
    if [[ $EUID -eq 0 ]]; then
      printf '%s\n' "$block" >> "$f"
    else
      printf '%s\n' "$block" | sudo tee -a "$f" >/dev/null
    fi
  fi
}

ensure_no_root_shell(){
  local user="$1"
  local bash_f="/etc/profile.d/97-wsl-no-root.sh"
  local block
  block=$(cat <<EOF
# setup_wsl_ubuntu.sh: avoid root shell on WSL (set WSL_FORCE_ROOT=1 to bypass)
if [[ -n "\${WSL_DISTRO_NAME:-}" && "\${EUID:-0}" -eq 0 && -z "\${WSL_FORCE_ROOT:-}" ]]; then
  [[ "\$-" == *i* ]] || return
  exec su - ${user}
fi
EOF
)
  if [[ ! -f "$bash_f" ]] || ! grep -q "setup_wsl_ubuntu.sh: avoid root shell on WSL" "$bash_f" 2>/dev/null; then
    if [[ $EUID -eq 0 ]]; then
      printf '%s\n' "$block" > "$bash_f"
    else
      printf '%s\n' "$block" | sudo tee "$bash_f" >/dev/null
    fi
  fi
}

ensure_wsl_docker_autostart(){
  local f="/etc/profile.d/98-wsl-docker-start.sh"
  local block
  block=$(cat <<'EOF'
# setup_wsl_ubuntu.sh: start docker in WSL if needed
if [[ -n "${WSL_DISTRO_NAME:-}" ]]; then
  if ! pgrep -x dockerd >/dev/null 2>&1; then
    if command -v systemctl >/dev/null 2>&1; then
      sudo systemctl start docker >/dev/null 2>&1 || true
    else
      sudo service docker start >/dev/null 2>&1 || true
    fi
  fi
fi
EOF
)
  if [[ ! -f "$f" ]] || ! grep -q 'setup_wsl_ubuntu.sh: start docker in WSL if needed' "$f"; then
    if [[ $EUID -eq 0 ]]; then
      printf '%s\n' "$block" > "$f"
    else
      printf '%s\n' "$block" | sudo tee "$f" >/dev/null
    fi
  fi
}

ensure_uid1000_nopasswd_all(){
  local user
  local f="/etc/sudoers.d/90-wsl-uid1000-nopasswd"
  user="$(detect_uid1000_user)"

  if [[ -z "$user" ]]; then
    return
  fi

  if [[ ! -f "$f" ]] || ! grep -q "^${user} ALL=(ALL:ALL) NOPASSWD:ALL$" "$f" 2>/dev/null; then
    if [[ $EUID -eq 0 ]]; then
      printf '%s\n' "${user} ALL=(ALL:ALL) NOPASSWD:ALL" > "$f"
      chmod 440 "$f"
      visudo -cf "$f" >/dev/null
    else
      printf '%s\n' "${user} ALL=(ALL:ALL) NOPASSWD:ALL" | sudo tee "$f" >/dev/null
      sudo chmod 440 "$f"
      sudo visudo -cf "$f" >/dev/null
    fi
    say "⚠️  sudo NOPASSWD:ALL abilitato per utente UID 1000 (${user})."
  fi
}

if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
  cat <<'EOF'
Uso: ./setup_wsl_ubuntu.sh [--with-zsh]

Installa i prerequisiti per usare il repo su Ubuntu/WSL:
- git, make, dialog, docker, docker compose
- tool base (curl, ca-certificates, gnupg, lsb-release, unzip, tar, gzip, xz-utils, jq, acl)

Opzionale:
  --with-zsh   Installa e configura zsh + Oh My Zsh + completions docker
EOF
  exit 0
fi

if [[ "$(uname -s)" != "Linux" ]]; then
  echo "🛑 Questo script è pensato solo per Linux/WSL."
  exit 1
fi

TARGET_USER="$(detect_target_user)"
if [[ -z "$TARGET_USER" ]]; then
  echo "🛑 Nessun utente non-root trovato. Crea un utente (es. ubuntu) e riprova."
  exit 1
fi
TARGET_HOME="$(getent passwd "$TARGET_USER" | cut -d: -f6)"
if [[ -n "$TARGET_HOME" && "${PWD:-}" == /mnt/* ]]; then
  cd "$TARGET_HOME" || true
fi

WITH_ZSH=0
if [[ "${1:-}" == "--with-zsh" ]]; then
  WITH_ZSH=1
fi

say "\n✅ Setup prerequisiti base (git, make, dialog, docker, compose)...\n"

need_pkgs=(
  git
  make
  dialog
  curl
  ca-certificates
  gnupg
  lsb-release
  unzip
  tar
  gzip
  xz-utils
  jq
  acl
)

missing=()
for p in "${need_pkgs[@]}"; do
  pkg_installed "$p" || missing+=("$p")
done

  if [[ ${#missing[@]} -gt 0 ]]; then
    say "📦 Installazione pacchetti mancanti: ${missing[*]}"
    as_root apt-get update -y
    apt_install "${missing[@]}"
  else
    say "✅ Pacchetti base già presenti."
  fi

if is_wsl; then
  ensure_wsl_start_home
  ensure_bashrc_cd_home
  ensure_no_root_shell "$TARGET_USER"
  ensure_wsl_docker_autostart
  ensure_wsl_conf
  ensure_uid1000_nopasswd_all
fi

say "\n🐳 Setup Docker (engine locale, senza Docker Desktop)...\n"
if is_windows_docker; then
  say "⚠️  Docker in PATH punta a Windows. Lo ignoro per usare l'engine locale."
  strip_windows_path
fi

if ! have docker; then
  say "⬇️  Installazione Docker Engine (pacchetti Ubuntu)..."
  as_root apt-get update -y
  apt_install docker.io
else
  say "✅ Docker CLI già presente."
fi

if ! getent group docker >/dev/null 2>&1; then
  as_root groupadd docker || true
fi

if ! id -nG "$TARGET_USER" | grep -qw docker; then
  as_root usermod -aG docker "$TARGET_USER" || true
  say "   ⚠️  Utente aggiunto al gruppo docker. Potrebbe servire riaprire la shell."
fi

if command -v systemctl >/dev/null 2>&1; then
  as_root systemctl enable --now docker >/dev/null 2>&1 || true
else
  as_root service docker start >/dev/null 2>&1 || true
fi

for i in {1..10}; do
  [[ -S /var/run/docker.sock ]] && break
  sleep 1
done

if [[ -S /var/run/docker.sock ]] && [[ ! -w /var/run/docker.sock ]]; then
  if command -v setfacl >/dev/null 2>&1; then
    as_root setfacl -m "u:${TARGET_USER}:rw" /var/run/docker.sock >/dev/null 2>&1 || true
  else
    as_root chgrp docker /var/run/docker.sock >/dev/null 2>&1 || true
    as_root chmod 660 /var/run/docker.sock >/dev/null 2>&1 || true
  fi
fi

if docker version >/dev/null 2>&1; then
  say "✅ Docker access OK."
else
  say "⚠️  Docker non ancora accessibile. Riapri la shell e riprova."
fi

compose_ok=0
if docker compose version >/dev/null 2>&1; then
  compose_ok=1
elif run_as_user "$TARGET_USER" "docker compose version" >/dev/null 2>&1; then
  compose_ok=1
fi

if [[ "$compose_ok" -eq 1 ]]; then
  say "✅ docker compose disponibile."
else
  say "⬇️  Installazione docker compose plugin..."
  apt_install docker-compose-plugin >/dev/null 2>&1 || true
  if ! docker compose version >/dev/null 2>&1; then
    say "⚠️  docker compose plugin non trovato via apt. Tentativo download diretto..."
    compose_tag="$(curl -fsSL https://api.github.com/repos/docker/compose/releases/latest | jq -r .tag_name 2>/dev/null || echo "")"
    if [[ -n "$compose_tag" ]]; then
      as_root install -d -m 700 -o "$TARGET_USER" -g "$TARGET_USER" "$TARGET_HOME/.docker/cli-plugins"
      curl -fsSL -o "$TARGET_HOME/.docker/cli-plugins/docker-compose.tmp" \
        "https://github.com/docker/compose/releases/download/${compose_tag}/docker-compose-linux-x86_64"
      as_root chmod +x "$TARGET_HOME/.docker/cli-plugins/docker-compose.tmp"
      as_root mv "$TARGET_HOME/.docker/cli-plugins/docker-compose.tmp" "$TARGET_HOME/.docker/cli-plugins/docker-compose"
      as_root chown "$TARGET_USER:$TARGET_USER" "$TARGET_HOME/.docker/cli-plugins/docker-compose"
      say "✅ docker compose plugin installato (${compose_tag})."
    else
      say "⚠️  Impossibile determinare versione docker compose."
    fi
  fi
fi

if [[ "$WITH_ZSH" -eq 1 ]]; then
  say "\n💻 Setup Zsh (opzionale)...\n"

  if ! have zsh; then
    apt_install zsh
  fi

  ZSH_DIR="$TARGET_HOME/.oh-my-zsh"
  if [[ ! -d "$ZSH_DIR" ]]; then
    run_as_user "$TARGET_USER" "git clone --depth=1 https://github.com/ohmyzsh/ohmyzsh.git \"$ZSH_DIR\""
  fi

  CUSTOM_DIR="$ZSH_DIR/custom"
  as_root install -d -o "$TARGET_USER" -g "$TARGET_USER" "$CUSTOM_DIR/completions"
  as_root install -d -o "$TARGET_USER" -g "$TARGET_USER" "$CUSTOM_DIR/plugins"
  as_root install -d -o "$TARGET_USER" -g "$TARGET_USER" "$CUSTOM_DIR/themes"

  # additional zsh plugins/theme (minimal)
  if [[ ! -d "$CUSTOM_DIR/plugins/zsh-autosuggestions" ]]; then
    run_as_user "$TARGET_USER" "git clone --depth=1 https://github.com/zsh-users/zsh-autosuggestions.git \"$CUSTOM_DIR/plugins/zsh-autosuggestions\""
  fi
  if [[ ! -d "$CUSTOM_DIR/plugins/zsh-syntax-highlighting" ]]; then
    run_as_user "$TARGET_USER" "git clone --depth=1 https://github.com/zsh-users/zsh-syntax-highlighting.git \"$CUSTOM_DIR/plugins/zsh-syntax-highlighting\""
  fi
  if [[ ! -d "$CUSTOM_DIR/plugins/zsh-completions" ]]; then
    run_as_user "$TARGET_USER" "git clone --depth=1 https://github.com/zsh-users/zsh-completions.git \"$CUSTOM_DIR/plugins/zsh-completions\""
  fi
  if [[ ! -d "$CUSTOM_DIR/themes/powerlevel10k" ]]; then
    run_as_user "$TARGET_USER" "git clone --depth=1 https://github.com/romkatv/powerlevel10k.git \"$CUSTOM_DIR/themes/powerlevel10k\""
  fi

  # docker completion
  if have docker; then
    run_as_user "$TARGET_USER" "docker completion zsh > \"$CUSTOM_DIR/completions/_docker\" 2>/dev/null || true"
  fi

  # docker completion bootstrap
  cat > "$CUSTOM_DIR/90-docker-completion.zsh" <<'EOF'
# docker completion bootstrap (managed)
if command -v docker >/dev/null 2>&1; then
  _docker_comp="${ZSH_CUSTOM:-$HOME/.oh-my-zsh/custom}/completions/_docker"
  if [[ -f "${_docker_comp}" ]]; then
    source "${_docker_comp}"
  else
    source <(docker completion zsh)
  fi
fi
EOF

  # docker-compose shim + completion
  cat > "$CUSTOM_DIR/90-docker-aliases.zsh" <<'EOF'
# Docker helpers (managed)
if ! command -v docker-compose >/dev/null 2>&1; then
  docker-compose(){
    if [[ $# -eq 0 ]]; then
      command docker compose --help
    else
      command docker compose "$@"
    fi
  }
fi

_docker_compose_wrapper(){
  if type _docker_compose >/dev/null 2>&1; then
    _docker_compose "$@"; return
  fi
  if type _docker >/dev/null 2>&1 && command -v docker >/dev/null 2>&1; then
    local original_current=$CURRENT
    local -a original_words
    original_words=("${words[@]}")
    words=("docker" "compose" "${original_words[@]:1}")
    CURRENT=$(( original_current + 1 ))
    local ret=1
    _docker "$@"; ret=$?
    words=("${original_words[@]}")
    CURRENT=$original_current
    return $ret
  fi
  return 0
}

if typeset -f docker-compose >/dev/null 2>&1; then
  compdef _docker_compose_wrapper docker-compose 2>/dev/null || true
fi
EOF

  cat > "$CUSTOM_DIR/91-docker-compose-compfix.zsh" <<'EOF'
# docker-compose completion fix (managed)
if typeset -f docker-compose >/dev/null 2>&1; then
  autoload -Uz +X compinit
  [[ -n ${_comps+x} ]] || compinit -i
  _docker_comp_file="${ZSH_CUSTOM:-$HOME/.oh-my-zsh/custom}/completions/_docker"
  if [[ -f "${_docker_comp_file}" ]]; then
    source "${_docker_comp_file}"
  fi
  if ! whence -w _docker_compose_wrapper >/dev/null 2>&1; then
    source "${ZSH_CUSTOM:-$HOME/.oh-my-zsh/custom}/90-docker-aliases.zsh" 2>/dev/null || true
  fi
  compdef _docker_compose_wrapper docker-compose 2>/dev/null || true
fi
unset _docker_comp_file
EOF

  # zshrc managed block
  if [[ -f "$TARGET_HOME/.zshrc" ]]; then
    backup_file "$TARGET_HOME/.zshrc"
  fi

  cat > "$TARGET_HOME/.zshrc" <<'EOF'
# >>> zsh-setup (managed) >>>
export ZSH="$HOME/.oh-my-zsh"
# WSL: entra sempre in home se si parte da /mnt
if [[ -n "${WSL_DISTRO_NAME:-}" ]]; then
  case "$PWD" in
    /mnt/*) cd "$HOME" ;;
  esac
fi
# evita wizard p10k automatico: usa tema di default finché l'utente non fa `p10k configure`
export POWERLEVEL9K_DISABLE_CONFIGURATION_WIZARD=true
ZSH_THEME="powerlevel10k/powerlevel10k"
plugins=(git docker zsh-autosuggestions zsh-syntax-highlighting zsh-completions)
fpath=($ZSH/custom/plugins/zsh-completions/src $fpath)
source "$ZSH/oh-my-zsh.sh"
[[ -f $ZSH/custom/90-docker-aliases.zsh ]] && source $ZSH/custom/90-docker-aliases.zsh
[[ -f $ZSH/custom/90-docker-completion.zsh ]] && source $ZSH/custom/90-docker-completion.zsh
[[ -f $ZSH/custom/91-docker-compose-compfix.zsh ]] && source $ZSH/custom/91-docker-compose-compfix.zsh
[[ -f ~/.p10k.zsh ]] && source ~/.p10k.zsh
# <<< zsh-setup (managed) <<<
EOF
  as_root chown "$TARGET_USER:$TARGET_USER" "$TARGET_HOME/.zshrc"

  # minimal p10k config (only if missing)
  if [[ ! -f "$TARGET_HOME/.p10k.zsh" ]]; then
    cat > "$TARGET_HOME/.p10k.zsh" <<'EOF'
# Minimal Powerlevel10k config (managed).
typeset -g POWERLEVEL9K_LEFT_PROMPT_ELEMENTS=(os_icon dir vcs newline prompt_char)
typeset -g POWERLEVEL9K_RIGHT_PROMPT_ELEMENTS=(status command_execution_time background_jobs)
typeset -g POWERLEVEL9K_MODE=nerdfont-v3
typeset -g POWERLEVEL9K_ICON_PADDING=moderate
typeset -g POWERLEVEL9K_PROMPT_ADD_NEWLINE=true

typeset -g POWERLEVEL9K_MULTILINE_FIRST_PROMPT_GAP_CHAR='·'
typeset -g POWERLEVEL9K_MULTILINE_FIRST_PROMPT_GAP_FOREGROUND=240
typeset -g POWERLEVEL9K_LEFT_SUBSEGMENT_SEPARATOR='\uE0B1'
typeset -g POWERLEVEL9K_RIGHT_SUBSEGMENT_SEPARATOR='\uE0B3'
typeset -g POWERLEVEL9K_LEFT_SEGMENT_SEPARATOR='\uE0B0'
typeset -g POWERLEVEL9K_RIGHT_SEGMENT_SEPARATOR='\uE0B2'
typeset -g POWERLEVEL9K_LEFT_PROMPT_LAST_SEGMENT_END_SYMBOL='\uE0B0'
typeset -g POWERLEVEL9K_RIGHT_PROMPT_FIRST_SEGMENT_START_SYMBOL='\uE0B2'
typeset -g POWERLEVEL9K_LEFT_PROMPT_FIRST_SEGMENT_START_SYMBOL=''
typeset -g POWERLEVEL9K_RIGHT_PROMPT_LAST_SEGMENT_END_SYMBOL=''
typeset -g POWERLEVEL9K_EMPTY_LINE_LEFT_PROMPT_LAST_SEGMENT_END_SYMBOL=

typeset -g POWERLEVEL9K_OS_ICON_FOREGROUND=232
typeset -g POWERLEVEL9K_OS_ICON_BACKGROUND=7

typeset -g POWERLEVEL9K_PROMPT_CHAR_BACKGROUND=
typeset -g POWERLEVEL9K_PROMPT_CHAR_OK_{VIINS,VICMD,VIVIS,VIOWR}_FOREGROUND=76
typeset -g POWERLEVEL9K_PROMPT_CHAR_ERROR_{VIINS,VICMD,VIVIS,VIOWR}_FOREGROUND=196
typeset -g POWERLEVEL9K_PROMPT_CHAR_{OK,ERROR}_VIINS_CONTENT_EXPANSION='❯'
typeset -g POWERLEVEL9K_PROMPT_CHAR_{OK,ERROR}_VICMD_CONTENT_EXPANSION='❮'
typeset -g POWERLEVEL9K_PROMPT_CHAR_{OK,ERROR}_VIVIS_CONTENT_EXPANSION='V'
typeset -g POWERLEVEL9K_PROMPT_CHAR_{OK,ERROR}_VIOWR_CONTENT_EXPANSION='▶'
typeset -g POWERLEVEL9K_PROMPT_CHAR_OVERWRITE_STATE=true
typeset -g POWERLEVEL9K_PROMPT_CHAR_LEFT_PROMPT_LAST_SEGMENT_END_SYMBOL=
typeset -g POWERLEVEL9K_PROMPT_CHAR_LEFT_PROMPT_FIRST_SEGMENT_START_SYMBOL=
typeset -g POWERLEVEL9K_PROMPT_CHAR_LEFT_{LEFT,RIGHT}_WHITESPACE=

typeset -g POWERLEVEL9K_DIR_BACKGROUND=4
typeset -g POWERLEVEL9K_DIR_FOREGROUND=254
typeset -g POWERLEVEL9K_SHORTEN_STRATEGY=truncate_to_unique
typeset -g POWERLEVEL9K_SHORTEN_DELIMITER=
typeset -g POWERLEVEL9K_DIR_SHORTENED_FOREGROUND=250
typeset -g POWERLEVEL9K_DIR_ANCHOR_FOREGROUND=255
typeset -g POWERLEVEL9K_DIR_ANCHOR_BOLD=true
typeset -g POWERLEVEL9K_SHORTEN_FOLDER_MARKER='(.git|package.json|composer.json|go.mod|Cargo.toml|.tool-versions|.mise.toml)'
typeset -g POWERLEVEL9K_DIR_TRUNCATE_BEFORE_MARKER=false
typeset -g POWERLEVEL9K_SHORTEN_DIR_LENGTH=1
typeset -g POWERLEVEL9K_DIR_MAX_LENGTH=80
typeset -g POWERLEVEL9K_DIR_MIN_COMMAND_COLUMNS=40
typeset -g POWERLEVEL9K_DIR_MIN_COMMAND_COLUMNS_PCT=50
typeset -g POWERLEVEL9K_DIR_HYPERLINK=false
typeset -g POWERLEVEL9K_DIR_SHOW_WRITABLE=v3

typeset -g POWERLEVEL9K_VCS_CLEAN_BACKGROUND=2
typeset -g POWERLEVEL9K_VCS_MODIFIED_BACKGROUND=3
typeset -g POWERLEVEL9K_VCS_UNTRACKED_BACKGROUND=2
typeset -g POWERLEVEL9K_VCS_CONFLICTED_BACKGROUND=3
typeset -g POWERLEVEL9K_VCS_LOADING_BACKGROUND=8
typeset -g POWERLEVEL9K_VCS_BRANCH_ICON='\uF126 '
typeset -g POWERLEVEL9K_VCS_UNTRACKED_ICON='?'
EOF
    as_root chown "$TARGET_USER:$TARGET_USER" "$TARGET_HOME/.p10k.zsh"
  fi

  # reset completion cache
  rm -f "$TARGET_HOME"/.zcompdump* >/dev/null 2>&1 || true

  if command -v chsh >/dev/null 2>&1 && have zsh; then
    if [[ "${SHELL:-}" != "$(command -v zsh)" ]]; then
      say "⌛ Cambio shell di default a zsh (potrebbe chiedere la password)..."
      as_root chsh -s "$(command -v zsh)" "$TARGET_USER" || true
    fi
  fi

  say "✅ Zsh configurato. Apri un nuovo terminale per caricare le impostazioni."
  say "   Suggerito: esegui 'p10k configure' per configurare il prompt."
fi

say "\n✅ Setup completato.\n"
say "Suggerito: apri una nuova shell per rendere effettivi i permessi docker."
if [[ "$(id -u)" -eq 0 ]]; then
  say "⚠️  Nota: stai eseguendo come root. Per usare l'utente predefinito avvia WSL senza '-u root'."
  say "   Esempio: wsl -d \"${WSL_DISTRO_NAME:-Ubuntu}\""
fi
if [[ "$NEED_WSL_RESTART" -eq 1 ]]; then
  say ""
  say "🔁 Per applicare le impostazioni WSL appena scritte:"
  say "   PowerShell: wsl --terminate \"${WSL_DISTRO_NAME:-Ubuntu}\""
  say "   Poi:        wsl -d \"${WSL_DISTRO_NAME:-Ubuntu}\""
fi
