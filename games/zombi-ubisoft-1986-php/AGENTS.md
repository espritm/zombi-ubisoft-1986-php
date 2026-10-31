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
- Lift: room 63 moves between map indices `2 + 56 * floor`, with five floors. Calls transport passengers,
  update landing exits and preserve special locks. Floor selection and mouse input use mode `lift`.
- Rope: three observed routes 35→64, 38→66, 46→65; item 41 must be active, is left at the source and consumed.
- Saves 0.5 retain installed `videoCassette` (0 or 50); 0.4/0.3/0.2 migrate with empty player. Keep the existing save filename.
- Video: 60→65 powers player, put item 50 on 65, 61→66 powers TV; interact 65 advances 80 minutes and trains occupants of room 29.
- `QuestRouteTest.php` and `tools/browser-route.cjs` test roof→tape→video→roof from a fresh game without state injection.
  This validates the video supply route. The new full escape route is in `QuestRouteTest` and `tools/browser-escape.cjs`.
- Truck routes: interior 87 maps 77→65, 88 maps 72→66, 89 maps 82→64; contact 68 is required.
  Copy first 30 source object cells, clear destination 32–39, update prefix +9 and blocked exits, transport matching interiors.
- `assets/original/trucks.png`: six private background variants, selected by room ID and prefix +9.
- Terminal mode must consume keyboard input before global shortcuts: Space/H/M/C/I are text. Public command menu excludes the hidden training command.
- Corpses use object 53 in grid cells 30/31 and increment room prefix +8; never spawn key 48 as combat loot.
- `tools/browser-test.cjs` tests these mechanisms through the browser UI with controlled start positions.
- `tools/site/`: screenshots and ignored image generation. Private website pictures use `play/private/assets/site/`;
  rerun `images.py` after `build:site` to mirror that route locally before `siteshot`.

## Validation

Run `bin/jumphp test zombi-ubisoft-1986-php` (install engine dev tooling once for PHPStan/PHPUnit),
`bin/jumphp lint`, and inspect title and play screenshots produced by `tools/site/shots.sh`.
Then `bin/jumphp build:remote zombi-ubisoft-1986-php` and
`JUMPHP_WEB=remote bin/jumphp webshot zombi-ubisoft-1986-php 7 '[[1,"Enter"]]'`.
Check the home page on desktop and phone with `siteshot`. Publish only after private assets have been uploaded.

## Scope and remaining work

Version 0.6 adds shared torch lighting, tool-49 door direction selection (F), corpse pickup/drop/freezing,
and automatic raider arrival once 34 bodies are frozen and three trucks are parked. See `tools/extract/EVASION.md`.
The new-game escape test uses ordinary actions without injecting positions, inventory, health or enemies.
Saves retain frozenBodies, raidersArrived and zombieTarget; older formats 0.2–0.5 migrate with default counters.
Ordinary exploration, lifts, rope routes, trucks, computer, video and the prototype escape chain are implemented.
Version 0.7 adds NPC circulation every 15104 ticks at 50 Hz, scanning rooms 1–62 in place.
Directions use native exit-field order N/E/S/W/down/up, not the player's direction-menu order.
Targets must be 1–62 with fewer than five enemies; invalid RNG values 6/7 mean stay, other failures retry at most ten times.
Entrances 19N/31E/32W add an enemy and raise the pre-arrival quota unless their flag is 10 or their room is full.
Saves retain enemyTicks and a portable 16-bit enemySeed; old 0.2–0.6 saves start this timer at zero.
After raider arrival the completed quota is retained for progress, unlike the repurposed native counter.
See `tools/extract/ENNEMIS-ET-HORLOGE.md` and `tools/browser-enemy-clock.cjs` for isolated timer/seed controls.
The escape test now follows the variable quota and can revisit rooms after enemy movement.
Other special exits, secondary item uses, exact combat and ammunition remain incomplete.
Combat/clock balancing is provisional. Do not claim full original parity or a natural walkthrough.
Never commit original data (DSK, ROM, dumps, PNG atlases, font or recordings), including screenshots derived from them.
Keep these ignored and use JumPHP private uploads; the repository contains only code, recipes and observations.
