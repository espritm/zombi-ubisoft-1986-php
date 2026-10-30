# Zombi CPC — notes for agents

Read `../../AGENTS.md` first. PHP and Composer run only through `bin/jumphp` in Docker.
This is a 320×200 French prototype of the 1986 CPC game, not the former platformer skeleton.

- `Model/World.php`: engine-independent state machine and observed rules. Tables come from private `assets/original/game.json`.
- `View/CpcView.php`: original texture atlases and CPC font, carousel and selection overlays.
- `Scene/TitleScene.php`: original title/music, new game, continue, help and 90-room gallery.
- `Scene/PlayScene.php`: update/render only; keyboard/mouse, pause/restart/help and local versioned saves.
- Every scene resets state in `enter()`. Sleeping characters remain in the party while others play.
- Controls: arrows + Return/Space, Tab cancel, Escape pause, F1 help, M movement, I equip, C/1–4 character, F5 save, F9 load.
- Smoke: `PlayScene`, `demo:true`; moves roof → terrace → stairs → level 3, then switches survivor.
- Rendering test params: `gallery:true`, `room:1..90`. Gallery pauses simulation and does not establish ordinary access to a room.
- `tests/WorldTest.php`: observed movement, party independence, key/fuel ending gate, containers, equipment activation,
  fuse/gloves, darkness, consumption, sleep/reanimation/defeat and atomic save validation.
- `tools/extract/`: reproducible source, emulator patch, original observations and controlled-precondition notes.
- `tools/site/`: screenshots and ignored image generation. Private website pictures use `play/private/assets/site/`;
  rerun `images.py` after `build:site` to mirror that route locally before `siteshot`.

## Validation

Run `bin/jumphp test zombi-ubisoft-1986-php` (install engine dev tooling once for PHPStan/PHPUnit),
`bin/jumphp lint`, and inspect title and play screenshots produced by `tools/site/shots.sh`.
Then `bin/jumphp build:remote zombi-ubisoft-1986-php` and
`JUMPHP_WEB=remote bin/jumphp webshot zombi-ubisoft-1986-php 7 '[[1,"Enter"]]'`.
Check the home page on desktop and phone with `siteshot`. Publish only after private assets have been uploaded.

## Scope and remaining work

Ordinary exploration and core object state are implemented. Lifts, special exits, truck puzzles, terminals,
secondary item uses and the full natural quest remain incomplete and are labelled in game and on the page.
Combat/clock balancing is provisional. Do not claim full original parity or a natural walkthrough.
Never commit original data (DSK, ROM, dumps, PNG atlases, font or recordings), including screenshots derived from them.
Keep these ignored and use JumPHP private uploads; the repository contains only code, recipes and observations.
