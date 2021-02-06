#!/usr/bin/env bash

echo "[ 1 / 2 ] SVG → PNG" >&2;
inkscape -o src/favicon.png -w 512 src/status.svg;
optipng -o9 src/favicon.png &
echo "[ 2 / 2 ] PNG → ICO" >&2;
convert src/favicon.png -resize 256x256 src/favicon.ico;

wait

echo "done" >&2;
