#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../../../.."
bin/jumphp shot zombi-ubisoft-1986-php 60 var/screenshots/title.png
JUMPHP_INPUT='[[5,"confirm"]]' bin/jumphp shot zombi-ubisoft-1986-php 240 var/screenshots/play.png
# Run images.py with Pillow installed; images remain excluded from the public repository.
