#!/bin/bash
# Uploads the three built sites to the cPanel server over FTPS (run by .github/workflows/deploy-build.yml).
# Works like extracting the zips: files are added and replaced, nothing on the server is deleted, and
# app/config.php and storage/ data are never uploaded.
#
# Needs (GitHub → Settings → Secrets and variables → Actions):
#   secrets FTP_SERVER, FTP_USERNAME, FTP_PASSWORD   an FTP account whose folder is your home folder
#   variables STUSYN_DIR, MARKETING_DIR, CRM_DIR      optional; folder paths from that home folder.
#     Left empty, each is found by checking the usual cPanel folder names for the site's own files.
#   variable FTP_VERIFY_CERT=no                       only if your host's certificate doesn't match FTP_SERVER
# Usage: ftp-deploy.sh <folder with stusyn/ marketing/ crm/> [--check]   (--check finds folders, uploads nothing)
set -euo pipefail
BUILD=${1:?build folder}; CHECK=${2:-}
: "${FTP_SERVER:?}" "${FTP_USERNAME:?}" "${FTP_PASSWORD:?}"
TLS="set ftp:ssl-force true; set ftp:ssl-protect-data true; set ssl:verify-certificate ${FTP_VERIFY_CERT:-yes};"
[ "${FTP_PLAIN:-}" = "1" ] && TLS="set ftp:ssl-allow false;"   # local testing only
export LFTP_PASSWORD="$FTP_PASSWORD"
OPEN="$TLS set net:max-retries 2; set net:timeout 30; set ftp:passive-mode true; open --env-password -u $(printf %q "$FTP_USERNAME") ${FTP_PORT:+-p $FTP_PORT} $(printf %q "$FTP_SERVER")"
ftp() { lftp -c "$OPEN; $1"; }
has() { ftp "cls -1 $(printf %q "$1")" >/dev/null 2>&1; }

if ! err=$(ftp "set net:max-retries 1; cls -1 -D" 2>&1 >/dev/null); then
  echo "Couldn't sign in to $FTP_SERVER as $FTP_USERNAME. The server said:" >&2; echo "$err" | sed 's/^/  /' | tail -5 >&2
  case "$err" in
    *certificate*|*Certificate*)
      echo "That's the security certificate. It was issued to these names; put one of them in the FTP_SERVER secret:" >&2
      timeout 20 openssl s_client -connect "$FTP_SERVER:${FTP_PORT:-21}" -starttls ftp </dev/null 2>/dev/null \
        | openssl x509 -noout -subject -ext subjectAltName 2>/dev/null | grep -oE '(CN ?= ?|DNS:)[^,/ ]+' | sed 's/^CN \?= \?//; s/^DNS://' | sort -u | sed 's/^/  /' >&2 || true ;;
    *530*|*Login*|*login*) echo "That's the username or password: FTP_USERNAME must be the full name, like deploy@stusyn.com." >&2 ;;
    *) echo "If it timed out, the host may be blocking connections from GitHub; ask them to allow FTP from outside, or use a different server name." >&2 ;;
  esac
  exit 1
fi

# find <given> <marker files…> -- <candidate folders…>: the first folder that has every marker file.
find_dir() {
  local given=$1; shift; local markers=(); while [ "$1" != "--" ]; do markers+=("$1"); shift; done; shift
  local cands=("$@"); [ -n "$given" ] && cands=("$given")
  for d in "${cands[@]}"; do
    local ok=1; for m in "${markers[@]}"; do has "$d/$m" || { ok=0; break; }; done
    [ $ok = 1 ] && { echo "$d"; return 0; }
  done
  return 1
}
fail() {
  echo "STOPPED, nothing was uploaded: $1" >&2
  echo "Folders in the FTP account's home:" >&2; ftp "cls -1 -D" 2>/dev/null | sed 's/^/  /' >&2 || true
  echo "Folders in public_html:" >&2; ftp "cls -1 -D public_html/" 2>/dev/null | grep "/$" | sed 's/^/  /' >&2 || true
  echo "Set STUSYN_DIR, MARKETING_DIR or CRM_DIR (repo variables) to the right folder and run it again." >&2
  exit 1
}
CRM=$(find_dir "${CRM_DIR:-}" app/config.php portal.php -- crm.stusyn.com public_html/crm.stusyn.com crm public_html/crm) || fail "couldn't find the CRM folder (one with app/config.php and portal.php)."
STUSYN=$(find_dir "${STUSYN_DIR:-}" app/config.php careers.php -- public_html stusyn.com public_html/stusyn.com) || fail "couldn't find the stusyn.com folder (one with app/config.php and careers.php)."
MARKETING=$(find_dir "${MARKETING_DIR:-}" checkout.html -- syndicatemarketing.co public_html/syndicatemarketing.co) || fail "couldn't find the syndicatemarketing.co folder (one with checkout.html)."
[ "$CRM" != "$STUSYN" ] && [ "$CRM" != "$MARKETING" ] && [ "$STUSYN" != "$MARKETING" ] || fail "two sites point at the same folder."
for d in stusyn marketing crm; do [ -f "$BUILD/$d/index.html" ] || [ -f "$BUILD/$d/index.php" ] || fail "the build is missing $d/."; done
echo "stusyn.com            → $STUSYN"
echo "syndicatemarketing.co → $MARKETING"
echo "crm.stusyn.com        → $CRM  (ads and wiki are inside it)"
[ "$CHECK" = "--check" ] && { echo "Check only, nothing uploaded."; exit 0; }

# Never upload server-only things, even if one slipped into a build. No --delete: nothing on the server is removed.
EXCL="--exclude-glob app/config.php --exclude-glob storage/*.sqlite* --exclude-glob storage/*.key --exclude-glob storage/*.log \
  --exclude storage/sessions/ --exclude storage/portal/ --exclude storage/portal-sessions/ --exclude storage/ref-sessions/ --exclude storage/ads/ --exclude storage/demo/ \
  --exclude storage/mail/ --exclude storage/builds/ --exclude storage/backups/ --exclude storage/resumes/"
up() { # up <build folder> <server folder>
  ftp "mirror --reverse --no-perms --parallel=4 $EXCL $(printf %q "$BUILD/$1") $(printf %q "$2")" >/dev/null
  echo "  ✓ $1 → $2 ($(find "$BUILD/$1" -type f | wc -l | tr -d ' ') files)"
}
up crm "$CRM"
up stusyn "$STUSYN"
up marketing "$MARKETING"
echo "Done. The CRM database updates itself on the next page load."
