#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
AMX="$ROOT/Мод/build/vostera_starter.amx"
if [ ! -s "$AMX" ]; then
  echo "ERROR: отсутствует AMX, сначала соберите прототип" >&2
  exit 1
fi
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
URL="https://github.com/openmultiplayer/open.mp/releases/download/v1.6.7.3218/open.mp-linux-x86.tar.gz"
SHA256="0f7bfe70bb68ea780fe24a910b69412046e53d10feb65a9d4a9de8ff1f7f1329"
curl --fail --location --silent --show-error --retry 3 "$URL" -o "$WORK/openmp.tar.gz"
printf '%s  %s\n' "$SHA256" "$WORK/openmp.tar.gz" | sha256sum --check
mkdir -p "$WORK/server"
tar --no-same-owner -xzf "$WORK/openmp.tar.gz" -C "$WORK/server"
SERVER="$(find "$WORK/server" -maxdepth 5 -type f -name 'omp-server' -print -quit)"
if [ -z "$SERVER" ]; then
  echo "ERROR: omp-server не найден в официальном архиве" >&2
  exit 1
fi
chmod +x "$SERVER"
SERVER_ROOT="$(dirname "$SERVER")"
mkdir -p "$SERVER_ROOT/gamemodes"
cp "$AMX" "$SERVER_ROOT/gamemodes/vostera_starter.amx"
python3 "$ROOT/Мод/tools/smoke-prototype.py" "$SERVER" "$SERVER_ROOT"
