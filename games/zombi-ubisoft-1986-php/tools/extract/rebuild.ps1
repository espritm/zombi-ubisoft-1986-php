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
        @('probe-quest-gates.py','unlock1'),@('probe-quest-gates.py','unlock0'),@('probe-shared-light.py'))) {
        Step (@('python3','-I',"/workspace/analysis/$($argsForProbe[0])") + @($argsForProbe | Select-Object -Skip 1))
        # Position-controlled experiments below need the world table from the first boot.
        if ($argsForProbe[0] -eq 'probe-directions.py' -and $argsForProbe.Count -eq 1) {
            Step @('python3','-I','/workspace/analysis/export-world.py')
        }
    }
}
foreach ($script in @('export-observed.py','export-world.py','export-sprites.py','verify-extraction.py','verify-rules.py','verify-quests.py','verify-lifts-rope.py','verify-trucks-terminal.py','verify-video.py','verify-quest-gates.py')) {
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
