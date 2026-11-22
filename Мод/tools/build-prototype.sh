#!/usr/bin/env bash
# Reproducible build of the independent VOSTERA RP prototype.
# This does not compile the unreleased v0.10 development mod.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TOOL_DIR="${RUNNER_TEMP:-${TMPDIR:-/tmp}}/vostera-pawn-build"
mkdir -p "$TOOL_DIR"
COMPILER_URL="https://github.com/openmultiplayer/compiler/releases/download/v3.10.11/pawnc-3.10.11-linux.tar.gz"
COMPILER_SHA256="13977433acb1dfb23e3ec6354f622115d5073e97aced9395058a9db59d2564c2"
STDLIB_REV="17c1b0191195cb0b59a820135fbca622c84129dd"
TARBALL="$TOOL_DIR/pawnc-3.10.11-linux.tar.gz"
if [ ! -f "$TARBALL" ]; then
  curl --fail --location --silent --show-error --retry 3 --output "$TARBALL" "$COMPILER_URL"
fi
printf '%s  %s\n' "$COMPILER_SHA256" "$TARBALL" | sha256sum --check --status || {
  echo "ERROR: Pawn compiler SHA256 mismatch" >&2
  exit 1
}
mkdir -p "$TOOL_DIR/compiler"
tar -xzf "$TARBALL" -C "$TOOL_DIR/compiler"
PAWNCC="$(find "$TOOL_DIR/compiler" -type f -name pawncc -print -quit)"
if [ -z "$PAWNCC" ]; then
  echo "ERROR: pawncc not found in compiler package" >&2
  exit 1
fi
chmod +x "$PAWNCC"
INCLUDES="$TOOL_DIR/omp-stdlib"
if [ ! -d "$INCLUDES/.git" ]; then
  git clone --quiet https://github.com/openmultiplayer/omp-stdlib.git "$INCLUDES"
fi
git -C "$INCLUDES" -c advice.detachedHead=false checkout --quiet "$STDLIB_REV"
mkdir -p "$ROOT/Мод/build"
OUTPUT="$ROOT/Мод/build/vostera_starter.amx"
PAWNLIB="$(find "$TOOL_DIR/compiler" -type f -name libpawnc.so -print -quit)"
if [ -z "$PAWNLIB" ]; then
  echo "ERROR: libpawnc.so missing from official archive" >&2
  exit 1
fi
export LD_LIBRARY_PATH="$(dirname "$PAWNLIB"):$(dirname "$PAWNCC"):${LD_LIBRARY_PATH:-}"
echo "Компиляция оригинального VOSTERA Starter (не полного игрового мода)..."
"$PAWNCC" -Z- "-i$INCLUDES" "-o$OUTPUT" "$ROOT/Мод/prototypes/vostera_starter.pwn" 2>&1 | tee "$TOOL_DIR/compile.log"
if grep -Eiq '(^|[[:space:]])(warning|error) [0-9]+:' "$TOOL_DIR/compile.log"; then
  echo "ERROR: Pawn compilation produced warnings/errors; correct them before release" >&2
  exit 1
fi
if [ ! -s "$OUTPUT" ]; then
  echo "ERROR: AMX was not generated" >&2
  exit 1
fi
sha256sum "$OUTPUT"
echo "OK: $OUTPUT"
