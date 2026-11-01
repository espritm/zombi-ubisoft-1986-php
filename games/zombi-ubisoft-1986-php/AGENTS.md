# Zombi CPC — notes for agents

Read `../../AGENTS.md` first. PHP and Composer run only through `bin/jumphp` in Docker.
This is a 320×200 French prototype of the 1986 CPC game, not the former platformer skeleton.

- `Model/World.php`: engine-independent state machine and observed rules. Tables come from private `assets/original/game.json`.
- `View/CpcView.php`: original texture atlases and CPC font, carousel and selection overlays.
- `Scene/TitleScene.php`: original title/music, new game, continue, help and 90-room gallery.
- `Scene/PlayScene.php`: update/render only; keyboard/mouse, pause/restart/help and local versioned saves.
- Every scene resets state in `enter()`. Sleeping characters remain in the party while others play.
- Controls: arrows + Return/Space, Tab cancel, Escape pause, F1 help, M movement, I equip, U use held item, C/1–4 character, F5 save, F9 load.
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
Version 0.8 adds native melee batches (0.2 s), attack rounds (4 s), weapon bonuses and conditional 3–6 damage.
Projectile width uses H, rifle +20, training +40. Central shots reduce hp/strength by 12, with 1.92 s delay.
Preserve the original #8A58 outside-window kill: controlled and natural timed-COPY runs both confirm it.
See `tools/extract/COMBAT-ET-TIRS.md` (92 native checks) and `tools/browser-combat.cjs` (controlled equipment/cursor fixtures).
Saves 0.8 require per-room strength; 0.2–0.7 migrate with reference strength 27. Combat controls reset on load, damage persists.
Fresh-game browser escape uses rapid alternating keyboard input for melee; its PHP bridge remains read-only.
Version 0.9 rolls zombie hp 26–41/strength 58-hp and raider hp 30–45/strength 65-hp.
Raider shooter eligibility uses native randomScore <75. Stats/trait persist per room opponent to preserve saved damage;
the original rerolls more often in its main menu loop. See `tools/extract/PROFILS-ET-RIPOSTES.md` (24 native checks).
The global 16-bit alert counter queues a single reply each 256 ticks; busy modes defer it and failed rolls retain it.
A successful reply subtracts 6 sequentially from F then H. An idle normal encounter engages melee at 512 ticks.
Aim timeout joins melee directly without dispatching a queued shot first. U can activate a held weapon from combat.
Saves 0.9 retain alertTicks, rangedPending, rangedArmed and per-room shooter; older saves default these without deleting wounds.
Version 0.10 fights reanimated party members (state 128) in the same room and vehicle, after ordinary enemies.
Their F/H gauges are combat hp/strength; fatal damage sets F=0 and state=255, preserving H and clearing used.
Permanent death adds no ordinary enemy corpse or freezer quota. State 255 renders the cross, not a zombie portrait.
See `tools/extract/SURVIVANTS-ZOMBIFIES.md` (17 native checks) and `tools/browser-zombie-party.cjs` (controlled fixtures).
Saves retain party wounds/death; 0.2–0.9 still load. U selects the first present zombie in party order.
Version 0.11 opens a four-person firearm target selector when no ordinary enemy is present.
Arrows/numbers choose without switching actor; Return or clicking a row confirms, Tab cancels.
Manual fire checks the map room but ignores vehicle state (#94D4); automatic combat still checks both.
States 0/16/64 and self target go directly to 255 without gauge damage. State 128 enters aim; 255/absent are rejected.
The chosen zombie stays targeted through aim timeout and NPC arrival; the target is transient and not saved.
Save/cancel/action/person changes clear it, wounds remain. Saves 0.2–0.10 still load.
See `tools/extract/SELECTION-DES-CIBLES.md` (30 native checks) and `tools/browser-target-selection.cjs`.
Exact firmware timing, frequent native stat rerolls and elimination object cleanup remain provisional.
Version 0.12 implements medkit 33: U opens the same selector with healing intent; state 0 and identical map position required.
Vehicle state is ignored; heal F/H by 10 with separate caps, retain the kit. The shared 15000-tick cooldown starts before selection.
Invalid targets and modern cancellation do not refund it; pause stops it and saves retain healingTicks. Old 0.2–0.11 saves default to zero.
Native #8AF0 clobbers B, so #943F cannot decrement sickness; do not invent a cure. Periodic illness remains unported.
See `tools/extract/SOINS.md` (52 native checks), HealingTest and `tools/browser-healing.cjs` (controlled equipment/gauges).
Other special exits and secondary item uses also remain incomplete.
Version 0.13 persists exposure (+23), illness (+34) and a separate 16-bit healthTicks clock.
At 15104 ticks, the normal loop processes one health pass; menus/combat defer it, pause stops it.
Illness in state <32 subtracts 10 S; S=10 stays alive at zero, S<10 becomes state 64/S0 without changing F/H.
Nearby room corpse count or markers 29–32 subtract 4 S once, regardless of count/vehicle; byte underflow preserves wrapped S and sets state 255.
Nonfatal zombie/party-zombie melee adds 2*damage to exposure modulo 256. Raider, ranged and fatal hits do not add it.
Native #8AF0 also clobbers the infection threshold in #7D88, so this roll cannot generate new illness; do not invent a probability.
Older saves 0.2–0.12 default the two bytes to zero and healthTicks to enemyTicks. Existing wounds remain untouched.
NPC timing remains separately ported; hunger/fatigue and party-body cleanup remain partial.
See `tools/extract/MALADIE-ET-CADAVRES.md` (93 native checks), IllnessTest and `tools/browser-illness.cjs` (controlled fixtures).
Do not assume a separate ammunition system exists without observing it in the original.
Version 0.14 moves foodHours/awakeHours from display-hour changes into periodicHealth, incrementing modulo 256 before applying penalties.
Food >=7 subtracts 4 S. Awake 19–20 subtracts 4, 21 subtracts 8, 22–26 subtracts 12; >=27 writes state 16 and restarts sleep ticks every pass.
This native branch does not filter previous states; preserve it rather than inventing an unconditional eight-hour recovery.
Apply byte arithmetic in deprivation → illness → corpse order, then final S>=128 sets state 255.
Wake/reanimation precede periodicHealth; wake clears awakeHours and adds up to 8 S, food 55 clears only foodHours.
Current saves require both counters <=255; 0.2–0.13 legacy values retain their low byte. No display-time catch-up.
See `tools/extract/FAIM-ET-FATIGUE.md` (126 native checks), DeprivationTest and `tools/browser-deprivation.cjs` (controlled counters/timers).
Combat/clock balancing is provisional. Do not claim full original parity or a natural walkthrough.
Version 0.15 follows native manual wake #A16C: choose occupied bed 57/59, clear state and bed association on the first matching party record.
No state/room filter; do not wake every matching character. Keep ticks, S, foodHours and awakeHours unchanged.
Only the clicked room's bed is freed. A bounded four-record search rejects orphan beds instead of reproducing the native unbounded scan.
Current saves accept optional bed 57/59; legacy 0.2–0.14 remove stale associations from already-awake characters and empty bed=0.
Existing sleeping associations remain; old discarded sleep ticks cannot be reconstructed.
See `tools/extract/REVEIL-MANUEL.md` (81 native checks), ManualWakeTest and `tools/browser-manual-wake.cjs` (controlled starting positions, real sleep/wake controls).
Version 0.16 connects manual firearm elimination, party-zombie kills and active combat death to identity corpses 29–32.
Scan the flat 40-cell room grid backwards for two zeros, even across row boundaries; transfer inventory even without room.
Pack nonzero bag items then held into the body's container, preserving its unused suffix; clear bag/held/used.
Use independent five-slot body containers: deliberately preserve the fifth item instead of native overflow into the next header.
PRENDRE offers the body first, then loot. Taking the body clears its room markers but retains contents; ordinary put places it again.
No ordinary corpse count or freezer quota for party bodies. Reanimation clears only this identity's markers in its map room.
Definitive firearm elimination frees an associated bed in the shooter's room but keeps the association in the party record.
Old 0.2–0.15 four-slot containers gain a fifth zero; retain valid 0.15 bed associations without old awake-state cleanup.
See CORPS-ET-INVENTAIRES.md (103 native checks), PartyBodyTest and tools/browser-party-bodies.cjs (controlled fixtures).
Fall/electrical death cleanup, native two-free-slot guard, container/body exchanges and last-party-death details remain partial.
Version 0.17 ports fall death: state 255, gauges/party position retained, body/inventory below 35→64,38→66,46→65.
Transfer before last-survivor defeat, even without room for the marker; held rope is insufficient unless used=41.
Shock without active gloves45 tests (F-20)&255 >=128 before writing, then does the same with H-10; exact zero stays alive.
Fatal shock preserves the failing gauge and prior writes, sets 255/used0, and checks for another state<32 BEFORE body transfer.
Last fatal shock retains inventory and makes no body; other fatal shocks leave a local body. Active gloves install fuse51 unchanged.
The electricShock return must preserve character selection/defeat instead of being overwritten by objectAction's normal mode.
No new save fields; accept version0.16. See CHUTES-ET-ELECTROCUTION.md (187 native checks), HazardTest and browser-hazards.cjs.
Other corpse/container exchanges, further death causes and firmware timing remain incomplete.
Never commit original data (DSK, ROM, dumps, PNG atlases, font or recordings), including screenshots derived from them.
Keep these ignored and use JumPHP private uploads; the repository contains only code, recipes and observations.
