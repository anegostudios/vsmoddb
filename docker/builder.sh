#!/bin/bash

compile_ts() {
	IN=$1
	OUT=$2
	C=$(mktemp "${TMPDIR:-/tmp/}XXXXXXXXXXXX.js")

	npx tsc --target ES2021 --module none --allowJs --removeComments --alwaysStrict --outFile "$C" --inlineSourceMap "web/_ts/$IN"
	npx terser "$C" -c "passes=3,keep_fargs=false" -m -o "web/js/$OUT" --source-map "url='$OUT.map',content='inline'"
	sed -i "s#\\.\\./app/#/#g" "web/js/$OUT.map"
	
	# <script nonce="{$cspNonce}" type="text/javascript" src="/web/js/script.js?v=28"></script>
	H=$(md5sum "web/js/$OUT" | cut -d " " -f 1)
	sed -i "s#/web/js/${OUT/./\\.}?v=[^\"]*\"#/web/js/$OUT?v=$H\"#" "templates/footer.tpl"
}


compile_scripts() {
	# compile_ts core.ts core.js -- eventually
	compile_ts _script.ts script.js

	echo recompiled scripts
}

compile_styles() {
	npx sass --style=compressed --update web/_sass/_style.scss:web/css/style.css

	# <link nonce="{$cspNonce}" href="/web/css/style.css?v=112" rel="stylesheet" type="text/css">
	H=$(md5sum "web/css/style.css" | cut -d " " -f 1)
	sed -i "s#web/css/style\\.css?v=[^\"]*\"#web/css/style.css?v=$H\"#" "templates/header.tpl"

	echo recompiled styles
}

#TODO(Rennorb) dx: These can be a bit slow to detect changes, but file change events don't work well though 
# docker mounts and this is the most stable approach that actually works.

watch_scripts() {
	echo watching for changes to scripts
	while true; do
		watch -rtg -n 1 "ls -alR --full-time web/_ts | md5sum" && compile_scripts
	done
}

watch_styles() {
	echo watching for changes to styles
	while true; do
		watch -rtg -n 1 "ls -alR --full-time web/_sass | md5sum" && compile_styles
	done
}

trap "echo stopped.; exit;" SIGINT SIGTERM
watch_scripts & watch_styles