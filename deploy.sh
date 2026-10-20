#!/bin/bash
# Runs on the server when you press "Deploy HEAD Commit" in cPanel → Git Version Control (see .cpanel.yml).
# Copies each built site over its live folder the way extracting the zips does: files are added and replaced,
# nothing is deleted, and server data (storage/ databases, keys, uploads) and app/config.php are never copied.
#
# Live folders are looked up from cPanel by domain. To set one by hand, create ~/.syndicate-deploy with lines like
#   CRM_DIR=/home/USERNAME/crm.stusyn.com
#   STUSYN_DIR=/home/USERNAME/public_html
#   MARKETING_DIR=/home/USERNAME/syndicatemarketing.co
# Add DRY_RUN=1 to that file to see where everything would go without copying anything.
set -euo pipefail
cd "$(dirname "$0")"

STUSYN_DIR=""; MARKETING_DIR=""; CRM_DIR=""; DRY_RUN=""
[ -f "$HOME/.syndicate-deploy" ] && . "$HOME/.syndicate-deploy"

PHP=$(command -v php || echo /usr/local/bin/php)
docroot() {
  command -v uapi >/dev/null || return 0
  uapi --output=json DomainInfo single_domain_data domain="$1" 2>/dev/null \
    | "$PHP" -r '$j = json_decode(stream_get_contents(STDIN), true); echo $j["result"]["data"]["documentroot"] ?? "";' || true
}
[ -n "$STUSYN_DIR" ] || STUSYN_DIR=$(docroot stusyn.com)
[ -n "$MARKETING_DIR" ] || MARKETING_DIR=$(docroot syndicatemarketing.co)
[ -n "$CRM_DIR" ] || CRM_DIR=$(docroot crm.stusyn.com)

echo "Deploying $(head -1 BUILD.txt 2>/dev/null || echo 'this build')"
echo "  stusyn.com            → ${STUSYN_DIR:-?}"
echo "  syndicatemarketing.co → ${MARKETING_DIR:-?}"
echo "  crm.stusyn.com        → ${CRM_DIR:-?}  (with ads.stusyn.com and wiki.stusyn.com inside it)"

fail() { echo "STOPPED, nothing was copied: $1" >&2; exit 1; }
# Check every folder before copying anything, so a wrong path never gets half a deploy.
for pair in "STUSYN_DIR:$STUSYN_DIR" "MARKETING_DIR:$MARKETING_DIR" "CRM_DIR:$CRM_DIR"; do
  name=${pair%%:*}; dir=${pair#*:}
  [ -n "$dir" ] || fail "couldn't find the folder for $name. Set it in ~/.syndicate-deploy."
  [ -d "$dir" ] || fail "$name ($dir) isn't a folder."
  case "$dir" in "$HOME"/*) ;; *) fail "$name ($dir) isn't inside your home folder." ;; esac
done
[ "$STUSYN_DIR" != "$MARKETING_DIR" ] && [ "$STUSYN_DIR" != "$CRM_DIR" ] && [ "$MARKETING_DIR" != "$CRM_DIR" ] || fail "two sites point at the same folder."
[ -f "$CRM_DIR/app/config.php" ] || fail "$CRM_DIR has no app/config.php, so it doesn't look like the live CRM."
[ -f "$STUSYN_DIR/app/config.php" ] || fail "$STUSYN_DIR has no app/config.php, so it doesn't look like the live stusyn.com."
[ -f "$MARKETING_DIR/index.html" ] || fail "$MARKETING_DIR has no index.html, so it doesn't look like the live syndicatemarketing.co."
for d in stusyn marketing crm; do [ -f "$d/index.html" ] || [ -f "$d/index.php" ] || fail "the build is missing $d/."; done

if [ -n "$DRY_RUN" ]; then echo "DRY_RUN is on in ~/.syndicate-deploy, so nothing was copied."; exit 0; fi

copy() { # copy <build folder> <live folder>, overwriting files and skipping server-only data
  tar -C "$1" -cf - \
    --exclude='./app/config.php' --exclude='./storage/*.sqlite*' --exclude='./storage/*.key' \
    --exclude='./storage/sessions' --exclude='./storage/portal' --exclude='./storage/portal-sessions' \
    --exclude='./storage/ads' --exclude='./storage/demo' --exclude='./storage/mail' --exclude='./storage/backups' \
    --exclude='./storage/resumes' . | tar -C "$2" -xf - --no-same-owner
  echo "  ✓ $1 → $2 ($(find "$1" -type f | wc -l | tr -d ' ') files)"
}
copy crm "$CRM_DIR"
copy stusyn "$STUSYN_DIR"
copy marketing "$MARKETING_DIR"
echo "Done. The CRM database updates itself on the next page load."
