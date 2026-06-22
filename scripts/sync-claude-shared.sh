#!/usr/bin/env bash
# sync-claude-shared.sh — rigenera il blocco SHARED in CLAUDE.md locale da
# docs/CLAUDE-core.md (symlink al SoT henaxis-playbook). Idempotente.
# Eseguire dalla ROOT del repo. Generalizzato da henaxis-mutua-docs (Option D).
set -euo pipefail

LOCAL_CLAUDE="CLAUDE.md"
SHARED_SYMLINK="docs/CLAUDE-core.md"
BEGIN_MARKER="<!-- BEGIN SHARED (auto-generato da docs/CLAUDE-core.md, NON editare a mano) -->"
END_MARKER="<!-- END SHARED -->"

[ -f "$LOCAL_CLAUDE" ] || { echo "✗ $LOCAL_CLAUDE non trovato (esegui da root repo)" >&2; exit 1; }
[ -e "$SHARED_SYMLINK" ] || { echo "✗ $SHARED_SYMLINK mancante (esegui setup-repo.sh)" >&2; exit 1; }
grep -qF "$BEGIN_MARKER" "$LOCAL_CLAUDE" || { echo "✗ marker BEGIN SHARED mancante in $LOCAL_CLAUDE" >&2; exit 1; }
grep -qF "$END_MARKER"   "$LOCAL_CLAUDE" || { echo "✗ marker END SHARED mancante in $LOCAL_CLAUDE" >&2; exit 1; }

TMP=$(mktemp); trap 'rm -f "$TMP"' EXIT
awk -v begin="$BEGIN_MARKER" -v end="$END_MARKER" -v src="$SHARED_SYMLINK" '
  $0 == begin { print; while ((getline line < src) > 0) print line; close(src); in_shared=1; next }
  $0 == end   { in_shared=0; print; next }
  !in_shared  { print }
' "$LOCAL_CLAUDE" > "$TMP"
mv "$TMP" "$LOCAL_CLAUDE"
echo "✓ $LOCAL_CLAUDE syncato da $SHARED_SYMLINK"
