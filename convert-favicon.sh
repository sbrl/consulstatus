#!/usr/bin/env bash

echo "[ 1 / 2 ] SVG → PNG" >&2;
inkscape -o src/favicon.png -w 512 src/status.svg;
echo "[ 2 / 2 ] PNG → ICO" >&2;
convert src/favicon.png -resize 256x256 src/favicon.ico;
echo "done" >&2;
