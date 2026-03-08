#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  exit 0
fi

status="$(git status --porcelain --untracked-files=all -- magazzino || true)"
if [ -z "$status" ]; then
  exit 0
fi

echo
echo "ATTENZIONE: rilevate modifiche locali sotto 'magazzino/'"
echo "Questi file possono bloccare 'git pull --rebase' durante 'make run'."
echo
echo "File coinvolti:"
while IFS= read -r line; do
  code="${line:0:2}"
  path="${line:3}"
  echo " - [$code] $path"
done <<< "$status"
echo
echo "Scegli come procedere:"
echo "  1) Crea stash delle modifiche in magazzino/ e continua update"
echo "  2) Salva patch + backup untracked, ripristina magazzino/ e continua update"
echo "  3) Annulla update"
echo

while true; do
  read -r -p "Seleziona [1/2/3]: " choice
  case "$choice" in
    1)
      stash_name="pre-run-magazzino-$(date +%Y%m%d_%H%M%S)"
      git stash push -u -m "$stash_name" -- magazzino >/dev/null
      echo
      echo "Stash creato: $stash_name"
      echo "Per riprendere in seguito:"
      echo " - git stash list"
      echo " - git stash show -p stash@{0}"
      echo " - git stash pop stash@{0}"
      echo
      break
      ;;
    2)
      ts="$(date +%Y%m%d_%H%M%S)"
      backup_dir="$root/backup"
      patch_file="$backup_dir/magazzino-local-${ts}.patch"
      untracked_txt="$backup_dir/magazzino-untracked-${ts}.txt"
      untracked_tar="$backup_dir/magazzino-untracked-${ts}.tar.gz"

      mkdir -p "$backup_dir"
      git diff --binary --no-color -- magazzino > "$patch_file"

      mapfile -t untracked_files < <(git ls-files --others --exclude-standard -- magazzino)
      if [ "${#untracked_files[@]}" -gt 0 ]; then
        printf "%s\n" "${untracked_files[@]}" > "$untracked_txt"
        tar -czf "$untracked_tar" -- "${untracked_files[@]}"
      fi

      git restore --worktree --staged -- magazzino

      echo
      echo "Patch salvata: $patch_file"
      if [ -f "$untracked_tar" ]; then
        echo "Backup untracked: $untracked_tar"
      fi
      echo "Cartella 'magazzino/' ripristinata, update può continuare."
      echo "Per riprendere in seguito:"
      echo " - git apply \"$patch_file\""
      if [ -f "$untracked_tar" ]; then
        echo " - tar -xzf \"$untracked_tar\" -C \"$root\""
      fi
      echo
      break
      ;;
    3)
      echo "Update annullato su richiesta utente."
      exit 1
      ;;
    *)
      echo "Scelta non valida. Inserisci 1, 2 o 3."
      ;;
  esac
done

exit 0
