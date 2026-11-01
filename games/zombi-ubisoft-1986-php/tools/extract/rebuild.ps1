param(
    [Parameter(Mandatory=$true)][string]$SideA,
    [Parameter(Mandatory=$true)][string]$SideB,
    [Parameter(Mandatory=$true)][string]$CpcRom,
    [Parameter(Mandatory=$true)][string]$AmsdosRom,
    [Parameter(Mandatory=$true)][string]$Dsk2Jumphp,
    [switch]$ReuseCaptures
)
$ErrorActionPreference = 'Stop'
$game = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$scratch = Join-Path $game 'var/extraction'
$analysis = Join-Path $scratch 'analysis'
New-Item -ItemType Directory -Force $analysis | Out-Null
Copy-Item "$PSScriptRoot/sources/*" $analysis -Force
Copy-Item "$PSScriptRoot/package.py" $analysis -Force
$roms = Join-Path $scratch 'ROM CPC 6128 OS + BASIC et AMSDOS'
New-Item -ItemType Directory -Force $roms | Out-Null
foreach ($pair in @(@($SideA,'Zombi__FRENCH__Side_A.dsk'), @($SideB,'Zombi__FRENCH__Side_B.dsk'),
    @($CpcRom,'ROM CPC 6128 OS + BASIC et AMSDOS/CPC6128.ROM'), @($AmsdosRom,'ROM CPC 6128 OS + BASIC et AMSDOS/amsdos.rom'))) {
    Copy-Item -LiteralPath (Resolve-Path -LiteralPath $pair[0]).Path -Destination (Join-Path $scratch $pair[1]) -Force
}
if ((Get-Item "$roms/CPC6128.ROM").Length -ne 32768 -or (Get-Item "$roms/amsdos.rom").Length -ne 16384) { throw 'Wrong ROM sizes.' }
$sourceTool = (Resolve-Path -LiteralPath $Dsk2Jumphp).Path
$targetTool = Join-Path $scratch 'dsk2jumphp'
New-Item -ItemType Directory -Force $targetTool | Out-Null
foreach ($item in Get-ChildItem -LiteralPath $sourceTool -Force | Where-Object Name -ne '.git') {
    Copy-Item -LiteralPath $item.FullName -Destination $targetTool -Recurse -Force
}
Copy-Item "$PSScriptRoot/dsk2jumphp.patch" "$analysis/dsk2jumphp.patch" -Force
if (-not (Select-String -LiteralPath "$targetTool/emulator/cpc.c" -SimpleMatch 'b-after' -Quiet)) {
    & docker run --rm --network none --mount "type=bind,source=$scratch,target=/workspace" -w /workspace/dsk2jumphp jumphp-cpc-analysis:local sh -c 'patch -p1 < /workspace/analysis/dsk2jumphp.patch'
    if ($LASTEXITCODE -ne 0) { throw 'Could not apply emulator patch.' }
}
function Step([string[]]$Arguments) {
    & docker run --rm --network none --mount "type=bind,source=$scratch,target=/workspace" -w /workspace/dsk2jumphp jumphp-cpc-analysis:local @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Failed: $($Arguments -join ' ')" }
}
Step @('make','test')
if (-not $ReuseCaptures) {
    foreach ($argsForProbe in @(@('probe-directions.py'),@('probe-directions.py','--terrace'),@('probe-directions.py','--stairs'),
        @('probe-inventory.py'),@('capture-rooms.py','38'),@('capture-rooms.py','--lit'),@('rule-runner.py'),
        @('probe-recovery.py'),@('probe-time-combat.py'),@('probe-combat-input.py'),@('probe-light.py'),
        @('probe-weapons.py'),@('probe-electricity.py'),@('probe-vehicle.py'),@('probe-fuel-chain.py'),@('probe-global-defeat.py'),
        @('probe-lifts.py','36','0','--passenger'),@('probe-lifts.py','63','0'),@('probe-lifts.py','63','1'),
        @('probe-rope.py','35','1'),@('probe-rope.py','38','0'),@('probe-rope.py','38','1'),@('probe-rope.py','46','1'),
        @('probe-trucks.py','72','1'),@('probe-trucks.py','77','0'),@('probe-trucks.py','77','1'),@('probe-trucks.py','82','1'),
        @('probe-terminal.py','LIST'),@('probe-terminal.py','ELECTRICITE'),@('probe-terminal.py','NUIT'),
        @('probe-terminal.py','ZOMBI_DEAD'),@('probe-terminal.py','BYE'),@('probe-terminal.py','BIDULE'),
        @('probe-video.py','1','1'),@('probe-video.py','1','0'),@('probe-video.py','0','1'),
        @('probe-quest-gates.py','ready'),@('probe-quest-gates.py','short'),@('probe-quest-gates.py','unparked'),
        @('probe-quest-gates.py','unlock1'),@('probe-quest-gates.py','unlock0'),@('probe-shared-light.py'),
        @('probe-enemy-clock.py'),@('probe-enemy-gates.py','blocked'),@('probe-enemy-gates.py','closed'),@('probe-enemy-gates.py','full'))) {
        Step (@('python3','-I',"/workspace/analysis/$($argsForProbe[0])") + @($argsForProbe | Select-Object -Skip 1))
        # Position-controlled experiments below need the world table from the first boot.
        if ($argsForProbe[0] -eq 'probe-directions.py' -and $argsForProbe.Count -eq 1) {
            Step @('python3','-I','/workspace/analysis/export-world.py')
        }
    }
    foreach ($weapon in @(0,34,35,36,37,38,39)) {
        Step @('python3','-I','/workspace/analysis/probe-melee-damage.py',"$weapon")
    }
    foreach ($shot in @(@(34,0,1),@(34,0,26),@(34,0,27),@(34,0,28),@(34,0,29),@(34,1,28),
        @(36,0,28),@(36,1,28),@(35,0,28),@(35,1,28),@(36,0,26),@(34,1,26))) {
        Step @('python3','-I','/workspace/analysis/probe-aim-damage.py',"$($shot[0])","$($shot[1])","$($shot[2])")
    }
    foreach ($delay in @(0,1,2,3,12,24,36,48,60,72,84)) {
        Step @('python3','-I','/workspace/analysis/probe-natural-shots.py',"$delay")
    }
    foreach ($phase in @(1,0)) {
        foreach ($randomInput in @('natural','0','1','7','15')) {
            Step @('python3','-I','/workspace/analysis/probe-encounter-stats.py',"$phase",$randomInput)
        }
    }
    foreach ($case in @('cadence','low','unarmed','empty','low-f','low-h','armed-edge','unarmed-edge','zombie')) {
        Step @('python3','-I','/workspace/analysis/probe-ranged-reply.py',$case)
    }
    foreach ($case in @('low','idle','other','vehicle')) {
        Step @('python3','-I','/workspace/analysis/probe-zombie-party-combat.py',$case)
    }
    foreach ($cell in @(28,1)) {
        Step @('python3','-I','/workspace/analysis/probe-zombie-party-shot.py',"$cell")
    }
    foreach ($case in @('living','sleep','dead','zombie','gone','other','vehicle','self')) {
        Step @('python3','-I','/workspace/analysis/probe-target-selection.py',$case)
    }
    foreach ($case in @('normal','cap','sleep','dead','zombie','gone','other','vehicle','low-score','high-score','cooldown','self')) {
        Step @('python3','-I','/workspace/analysis/probe-healing.py',$case)
    }
    foreach ($case in @('healthy','ill','zero','under','sleep','dead','zombie','gone','exposure-low','exposure-high','corpse','corpse-low','illcorpse','marker29','marker30','marker31','marker32','elsewhere','busy')) {
        Step @('python3','-I','/workspace/analysis/probe-illness.py',$case)
    }
    foreach ($case in @('zombie','raider','party','wrap','fatal-f','fatal-h')) {
        Step @('python3','-I','/workspace/analysis/probe-exposure.py',$case)
    }
    foreach ($case in @('food5','food6','food7','food254','food255','awake17','awake18','awake19','awake20','awake21','awake22','awake25','awake26','awake27','awake254','awake255','starve','combined-ill','combined-corpse','forced-sleep','forced-dead','forced-gone','repeat','wake','wake-cap','wake-period')) {
        Step @('python3','-I','/workspace/analysis/probe-deprivation.py',$case)
    }
    foreach ($item in @(54,55)) { Step @('python3','-I','/workspace/analysis/probe-deprivation-consume.py',"$item") }
    foreach ($case in @('normal','bed59','remote','awake','dead','zombie','gone','duplicate','free')) {
        Step @('python3','-I','/workspace/analysis/probe-wake.py',$case)
    }
    foreach ($case in @('living','sparse','full','split','rowwrap','stale','stale-sparse','self','sleep','vehicle','loot','loot-item','transport','zombie','combat','reanimate')) {
        Step @('python3','-I','/workspace/analysis/probe-party-bodies.py',$case)
    }
    foreach ($case in @('fall35','fall38','fall46','fall-full35','fall-sparse35','held35','fall-last35','rope35','f19','f20','h9','h10','both','both-under','h-under','zero','gloves','gloves-held','normal','last','f147','f148','h137','h138','full')) {
        Step @('python3','-I','/workspace/analysis/probe-hazards.py',$case)
    }
    foreach ($case in @('put-cupboard','put-body','full-cupboard','full-body','nested-cupboard','nested-body','roundtrip-cupboard','roundtrip-body','drink','food','duplicate-bag','duplicate-held','guard0','guard1-held','guard1-bag','guard2','guard2-bag','take-portable27','take-portable28')) {
        Step @('python3','-I','/workspace/analysis/probe-storage.py',$case)
    }
}
foreach ($script in @('export-observed.py','export-world.py','export-sprites.py','verify-extraction.py','verify-rules.py','verify-quests.py','verify-lifts-rope.py','verify-trucks-terminal.py','verify-video.py','verify-quest-gates.py','verify-enemy-clock.py','verify-combat-damage.py','verify-ranged-stats.py','verify-zombie-party.py','verify-target-selection.py','verify-healing.py','verify-illness.py','verify-deprivation.py','verify-wake.py','verify-party-bodies.py','verify-hazards.py','verify-storage.py')) {
    Step @('python3','-I',"/workspace/analysis/$script")
}
New-Item -ItemType Directory -Force "$analysis/runs/rom-boot" | Out-Null
Step @('emulator/cpc','/workspace/Zombi__FRENCH__Side_A.dsk','/workspace/analysis/runs/rom-boot','3001',
    '-rom','/workspace/ROM CPC 6128 OS + BASIC et AMSDOS/CPC6128.ROM','-urom','7:/workspace/ROM CPC 6128 OS + BASIC et AMSDOS/amsdos.rom',
    '-type','150:RUN3WO*BI\n','-s','3000')
Step @('python3','-I','/workspace/analysis/finish.py')
Step @('python3','-I','tools/psg.py','render','/workspace/analysis/runs/direction-3/psg.bin','1961','5213','/workspace/analysis/zombi-intro.wav')
Step @('python3','-I','/workspace/analysis/package.py')
$generated = Join-Path $scratch 'zombi-ubisoft-1986-php/games/zombi-ubisoft-1986-php/assets/original'
New-Item -ItemType Directory -Force "$game/assets/original" | Out-Null
Copy-Item "$generated/*" "$game/assets/original/" -Force
Write-Output "Rebuilt private resources in $game/assets/original"
