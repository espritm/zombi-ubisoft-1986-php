# Zombi Ubisoft (1986) PHP

Le jeu Amstrad CPC de Zombi (Ubisoft 1986).

## A remake of an Amstrad CPC game: use dsk2jumphp

This game is the remake of an Amstrad CPC game, **with its original graphics, music, sounds, levels and
rules**, taken from its disc. Follow `AGENTS.md` section 12 to the letter:

- use **[dsk2jumphp](https://github.com/davidnurdin/dsk2jumphp)** (headless CPC emulator, DSK / dump / Z80 /
  sound tools, the method): clone it **outside this repository**, `make -C <it> test`, link its Claude skill
  (`.claude/skills/dsk2jumphp`), read its `AGENTS.md` and `docs/method.md`. Do not write an emulator, a
  disassembler or a DSK reader yourself;
- **ask the user for the path of the game's `.dsk` on their computer** (never download a disc or CPC ROMs;
  ROMs only from the user's machine if the game needs the firmware);
- keep the extraction reproducible in `games/zombi-ubisoft-1986-php/tools/extract/` (scripts + README of the addresses + one
  script that rebuilds `assets/` from the .dsk path); **never commit the .dsk, ROMs or memory dumps**;
- the original's screen size, rules and timings (50 Hz); check it side by side with the emulator
  (`cpc -s` against `bin/jumphp shot`);
- if the extracted assets must not be public, keep them out of git (`.gitignore`) and give them to the
  playground as **private files** (MCP tool `upload_private_files`, or Studio → the project → "Fichiers
  privés"): the game loads them, no repository holds them.
- Example: the Rainbow Islands remake, https://github.com/davidnurdin/rainbow-island (`tools/extract/`).

---

## The JumPHP playground

This project was made on the JumPHP playground: the game's page is https://jumphp.net/zombi-ubisoft-1986-php
(category: Remakes).

- The game is `games/zombi-ubisoft-1986-php/`. Read `AGENTS.md` (the JumPHP contract) first; replace the skeleton with the game
  described above; follow the definition of done (tests, screenshots you look at, browser build).
- **Publishing**: every push to `main` is online within a minute (GitHub webhook). The MCP tool
  `sync_project` (server `jumphp`) publishes at once and returns the errors: a commit with a PHP syntax error
  is not published (the previous version stays online).
- The playground loads the game file by file with its shared engine: Composer packages other than
  `jumphp/engine` are not available there.
- **The game's home page**: make `games/zombi-ubisoft-1986-php/site/` (AGENTS.md section 13: themed with the game's own graphics,
  SEO, a link to `play/`); it is the game's page on the playground. Also `games/zombi-ubisoft-1986-php/assets/thumbnail.png`.
- When the game is done: MCP `update_project` with `listed: true` shows it in the playground's directory.