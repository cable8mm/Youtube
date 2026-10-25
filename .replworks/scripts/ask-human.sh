#!/usr/bin/env bash
# ask-human.sh ask pr    "<question>"   -> prints a token
# ask-human.sh ask issue "<question>"   -> prints a token
# ask-human.sh wait <token>             -> prints the answer, or PENDING
set -euo pipefail
HUMAN="${HUMAN_LOGIN:-cable8mm}"
AGENT="${AGENT_NAME:-agent}"
WAIT_SECS="${WAIT_SECS:-90}"
REPO="${ASK_REPO:-$(gh repo view --json nameWithOwner -q .nameWithOwner)}"

case "${1:-}" in
  ask)
    kind="${2:?pr|issue}"; q="${3:?question}"
    body="@$HUMAN

$q

— From $AGENT"
    case "$kind" in
      pr)
        pr=$(gh pr view --json number -q .number 2>/dev/null || true)
        [ -n "$pr" ] || { echo "No PR on the current branch. Open a draft PR first." >&2; exit 1; }
        cid=$(gh api "repos/$REPO/issues/$pr/comments" -f body="$body" --jq .id)
        echo "pr/$pr/$cid" ;;
      issue)
        title=$(printf '%s' "$q" | head -n1 | cut -c1-80)
        url=$(gh issue create -R "$REPO" -t "[ask] $title" -b "$body")
        echo "issue/${url##*/}/0" ;;
      *) echo "usage: ask pr|issue \"<question>\"" >&2; exit 1 ;;
    esac ;;
  wait)
    IFS=/ read -r kind num after <<<"${2:?token}"
    end=$((SECONDS + WAIT_SECS)); interval=10
    while [ "$SECONDS" -lt "$end" ]; do
      id=$(gh api --paginate "repos/$REPO/issues/$num/comments?per_page=100" \
        --jq ".[] | select(.id > $after and .user.login == \"$HUMAN\") | .id" | head -n1 || true)
      if [ -n "$id" ]; then
        gh api "repos/$REPO/issues/comments/$id" --jq .body
        if [ "$kind" = issue ]; then gh issue close "$num" -R "$REPO" >/dev/null 2>&1 || true; fi
        exit 0
      fi
      sleep "$interval"
      [ "$interval" -lt 30 ] && interval=$((interval + 10))
    done
    echo "PENDING" ;;
  *) echo "usage: ask pr|issue \"<question>\" | wait <token>" >&2; exit 1 ;;
esac