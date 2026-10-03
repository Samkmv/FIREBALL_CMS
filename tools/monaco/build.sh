#!/bin/sh
# Requires monaco-editor@0.55.1 and esbuild@0.25.12 in a development-only node_modules.
set -eu
project=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
deps=${MONACO_NODE_MODULES:?Set MONACO_NODE_MODULES to development node_modules}
esbuild=${ESBUILD_BIN:-"$deps/.bin/esbuild"}
output="$project/public/assets/default/vendor/monaco-0.55.1"
mkdir -p "$output"
NODE_PATH="$deps" "$esbuild" "$project/tools/monaco/editor.js" --bundle --format=esm --minify --target=es2020 --loader:.ttf=file --asset-names='[name]-[hash]' --outfile="$output/editor.js"
for kind in editor json css html ts; do
    case "$kind" in
        editor) source="$deps/monaco-editor/esm/vs/editor/editor.worker.js" ;;
        ts) source="$deps/monaco-editor/esm/vs/language/typescript/ts.worker.js" ;;
        *) source="$deps/monaco-editor/esm/vs/language/$kind/$kind.worker.js" ;;
    esac
    "$esbuild" "$source" --bundle --format=esm --minify --target=es2020 --outfile="$output/$kind.worker.js"
done
cp "$deps/monaco-editor/LICENSE" "$output/LICENSE"
cp "$deps/monaco-editor/ThirdPartyNotices.txt" "$output/ThirdPartyNotices.txt"
