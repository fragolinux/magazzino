#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
backup_root="$root/backup"

if ! command -v dialog >/dev/null 2>&1; then
  echo "dialog non trovato. Installa il pacchetto 'dialog' e riprova." >&2
  exit 1
fi

export DIALOGOPTS="--no-shadow"

run_and_show() {
  local title="$1"
  shift
  local tmp status_tmp status
  tmp="$(mktemp)"
  status_tmp="$(mktemp)"

  dialog --title "$title" --programbox "Esecuzione in corso..." 25 100 < <(
    set +e
    "$@" 2>&1 | tee "$tmp"
    code=${PIPESTATUS[0]}
    printf '%s' "$code" >"$status_tmp"
  ) || true

  status=1
  if [[ -s "$status_tmp" ]]; then
    status="$(cat "$status_tmp")"
  fi

  if [[ "$status" -eq 0 ]]; then
    dialog --title "$title (OK)" --textbox "$tmp" 0 0
  else
    dialog --title "$title (ERRORE)" --textbox "$tmp" 0 0
  fi

  rm -f "$tmp" "$status_tmp"
}

select_backup_dir() {
  local mode="${1:-all}"
  local choice
  local custom
  local -a items
  local -a paths
  local latest_path=""
  local latest_key=""
  items=()
  paths=()

  if [[ -d "$backup_root" ]]; then
    while IFS= read -r fullpath; do
      [[ -z "$fullpath" ]] && continue
      if [[ "$mode" == "db" && ! -f "$fullpath/db.sql" ]]; then
        continue
      fi
      if [[ "$mode" == "file" && ! -f "$fullpath/files.tar.gz" ]]; then
        continue
      fi
      if [[ "$mode" == "all" && ( ! -f "$fullpath/db.sql" || ! -f "$fullpath/files.tar.gz" ) ]]; then
        continue
      fi

      local base formatted
      base="$(basename "$fullpath")"
      if [[ "$base" =~ ^([0-9]{8})_([0-9]{6})$ ]]; then
        formatted="${base:0:4}-${base:4:2}-${base:6:2} ${base:9:2}:${base:11:2}:${base:13:2}"
      else
        formatted="$base"
      fi

      if [[ -z "$latest_path" ]]; then
        latest_path="$fullpath"
        latest_key="$base"
      fi
      paths+=("$base" "$fullpath")
      items+=("$base" "$formatted")
    done < <(ls -1dt "$backup_root"/* 2>/dev/null || true)
  fi

  if [[ ${#items[@]} -eq 0 ]]; then
    dialog --msgbox "Nessun backup valido trovato in $backup_root" 7 70
    return 1
  fi

  local -a menu_items
  menu_items=()
  if [[ -n "$latest_path" ]]; then
    menu_items+=("latest" "Ultimo backup disponibile")
  fi

  local idx=1
  local i=0
  while [[ $i -lt ${#items[@]} ]]; do
    menu_items+=("$idx" "${items[$((i+1))]}")
    idx=$((idx + 1))
    i=$((i + 2))
  done
  menu_items+=("custom" "Percorso personalizzato")

  choice="$(dialog --backtitle "Menu Magazzino Componenti" --title "Menu Magazzino Componenti" --menu "Scegli il backup da ripristinare:" 0 0 10 "${menu_items[@]}" 2>&1 >/dev/tty)"
  if [[ -z "$choice" ]]; then
    return 1
  fi

  if [[ "$choice" == "latest" ]]; then
    echo "$latest_path"
    return 0
  fi

  if [[ "$choice" == "custom" ]]; then
    custom="$(dialog --backtitle "Menu Magazzino Componenti" --title "Menu Magazzino Componenti" --inputbox "Inserisci il percorso del backup:" 0 0 2>&1 >/dev/tty)"
    if [[ -z "$custom" ]]; then
      return 1
    fi
    echo "$custom"
    return 0
  fi

  local sel_index=$((choice - 1))
  local sel_key_index=$((sel_index * 2))
  local sel_key="${items[$sel_key_index]}"
  if [[ -z "$sel_key" ]]; then
    return 1
  fi
  echo "$backup_root/$sel_key"
}

main_menu() {
  dialog --backtitle "Menu Magazzino Componenti" --title "Menu Magazzino Componenti" --menu "Seleziona un'azione:" 0 0 10 \
    "up" "Avvia stack" \
    "down" "Ferma stack" \
    "run" "Aggiorna repo e riavvia stack" \
    "run-safe" "Backup + aggiorna repo + riavvia stack" \
    "clone" "Clona ultimo tag e copia dati utente" \
    "dbcheck" "Verifica migrazioni pendenti (dev o prod)" \
    "backup" "Backup" \
    "restore" "Restore" \
    "logs" "Log stack attivo (Ctrl+C esci)" \
    "exit" "Esci" \
    2>&1 >/dev/tty
}

backup_menu() {
  dialog --backtitle "Menu Magazzino Componenti" --title "Menu Magazzino Componenti" --menu "Seleziona il tipo di backup:" 0 0 6 \
    "all" "Completo (DB + file)" \
    "db" "Solo DB" \
    "file" "Solo file (uploads + config)" \
    "back" "Indietro" \
    2>&1 >/dev/tty
}

restore_menu() {
  dialog --backtitle "Menu Magazzino Componenti" --title "Menu Magazzino Componenti" --menu "Seleziona il tipo di restore:" 0 0 6 \
    "all" "Completo (DB + file)" \
    "db" "Solo DB" \
    "file" "Solo file (uploads + config)" \
    "back" "Indietro" \
    2>&1 >/dev/tty
}

while true; do
  choice="$(main_menu)"
  case "$choice" in
    up)
      run_and_show "Avvio" make up
      ;;
    down)
      run_and_show "Stop" make down
      ;;
    run)
      run_and_show "Run" make run
      ;;
    run-safe)
      run_and_show "Run-safe" make run-safe
      ;;
    dbcheck)
      run_and_show "DB Check" make dbcheck
      ;;
    backup)
      bchoice="$(backup_menu)"
      case "$bchoice" in
        all)
          run_and_show "Backup completo" "$root/scripts/backup.sh"
          ;;
        db)
          run_and_show "Backup DB" "$root/scripts/backup_db.sh"
          ;;
        file)
          run_and_show "Backup file" "$root/scripts/backup_files.sh"
          ;;
      esac
      ;;
    restore)
      rchoice="$(restore_menu)"
      case "$rchoice" in
        db|file|all)
          bdir="$(select_backup_dir "$rchoice")" || continue
          case "$rchoice" in
            all)
              run_and_show "Restore completo" "$root/scripts/restore.sh" "$bdir"
              ;;
            db)
              run_and_show "Restore DB" "$root/scripts/restore_db.sh" "$bdir"
              ;;
            file)
              run_and_show "Restore file" "$root/scripts/restore_files.sh" "$bdir"
              ;;
          esac
          ;;
      esac
      ;;
    logs)
      clear
      if command -v ccze >/dev/null 2>&1; then
        make logs 2>&1 | ccze -A || true
      else
        make logs || true
      fi
      ;;
    clone)
      run_and_show "Clone" make clone
      ;;
    exit|*)
      break
      ;;
  esac
done

clear
exit 0
