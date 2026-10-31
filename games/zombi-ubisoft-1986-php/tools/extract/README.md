# Zombi CPC extraction

This directory contains the extraction and observation source, not the original data.
Use your own French two-sided discs and French CPC6128 OS+BASIC (32768 bytes) and AMSDOS (16384 bytes).
No discs, ROMs, memory dumps, graphics or original recordings belong in Git.

Prerequisites: Docker, PowerShell and a checkout of
https://github.com/davidnurdin/dsk2jumphp at `7df03ec2b1e75765a5fcd5e744175292d914e90d`.
The included patch adds generic frame-triggered disc swapping and delayed breakpoint capture.
Build the analysis container once (from this directory):

```powershell
docker build -t jumphp-cpc-analysis:local -f Dockerfile.cpc .
./rebuild.ps1 -SideA 'C:/discs/A.dsk' -SideB 'C:/discs/B.dsk' -CpcRom 'C:/roms/CPC6128.ROM' -AmsdosRom 'C:/roms/amsdos.rom' -Dsk2Jumphp 'C:/tools/dsk2jumphp'
```

The script copies inputs into ignored `var/extraction/`, runs the original under the upstream emulator,
exports/compares the scenes, checks observed rules, renders the AY recording and packages `assets/original/`.
It takes several minutes and several GB for all 90 controlled renders and observation dumps.
`-ReuseCaptures` reuses those private captures. Existing user inputs are never edited.
For the development workspace, `package.py` alone can repackage an existing parent `analysis/` tree.

| Address / layout | Meaning / evidence |
| --- | --- |
| `#6633`, stride 40 | Four character records; position, name, gauges, bag and held item |
| `#6601`, `#6615`, `#6617` | Action ID, active character pointer, active room pointer |
| `#6726`, 336 bytes | Map: strides 1, 8 and 56; 86 physical rooms |
| `#6876`, stride 50 | 90 room records, including vehicle interiors 87–90; prefix 10 + grid 4×10 |
| `#7A0A`, stride 4 | Object graphics pointers and dimensions; mapping code `#90BE–#90F0` |
| `#4901`, stride 256 | Four portraits, four undead portraits, cross |
| `#5401` | 18 carousel icons, 144-byte scanline, mode 1 |
| `#A26C` | Container contents pointer table for objects 22–32 |
| `#6712`, `#6710–#6711` | Electricity, can and helicopter tank flags |
| `#9BB7–#9CA2` | Observed movement selector and exit checks |
| `#901B`, `#9D98` | Original room/object drawing, captured before object composition |
| OS ROM offset `#3800` | 256 characters × 8 rows, CPC font |

`sources/capture-rooms.py` temporarily changes the starting map cell and forces lighting to obtain clean
backgrounds. These captures establish rendering, not natural access to each room.
`verify-extraction.py` compares all 90 original scenes to their reconstruction pixel for pixel.
`REGLES-VERIFIEES.md` and `QUETES-ET-FINS.md` explain the controlled preconditions for rules and endings.
`ASCENSEUR-ET-CORDE.md` documents the eight additional lift/rope checks and controlled starting conditions.
`CAMIONS-ET-TERMINAL.md` documents three truck routes, six exact background variants and the computer commands.
`VIDEO-ET-PARCOURS.md` documents seven video checks and a fresh-game browser route without game-state injection.
`EVASION.md` documents ten original quest-gate checks and the prototype's fresh-game escape route,
including shared lighting, the freezer and automatic arrival. Exact combat balancing and periodic enemy
migration/respawning remain incomplete; the prototype route does not establish full CPC parity.
Private resources must be uploaded with JumPHP's `upload_private_files` before publication.
