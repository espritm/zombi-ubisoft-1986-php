"""Observe six choices of the original's direction selector; no game patches."""
from pathlib import Path
import subprocess
import sys
from PIL import Image

root = Path('/workspace')
from_stairs = '--stairs' in sys.argv
from_terrace = '--terrace' in sys.argv or from_stairs
sys.path.insert(0, str(root / 'dsk2jumphp/tools'))
from cpc import Dump

for choice in range(6):
    out = root / f'analysis/runs/{"stairs" if from_stairs else "terrace" if from_terrace else "direction"}-{choice}'
    out.mkdir(exist_ok=True, parents=True)
    events = ['8200:return+', '8220:return-', '8300:enter+', '8320:enter-']
    for f in (8500, 8600, 8700, 8800):
        events.extend([f'{f}:right+', f'{f+8}:right-'])
    events.extend(['9000:copy+', '9008:copy-'])
    for i in range(3 if from_terrace else choice):
        f = 9300 + 150 * i
        events.extend([f'{f}:space+', f'{f+8}:space-'])
    events.extend(['11000:copy+', '11008:copy-'])
    if from_terrace:
        events.extend(['13000:copy+', '13008:copy-'])
        for i in range(1 if from_stairs else choice):
            f = 13300 + 150*i
            events.extend([f'{f}:space+', f'{f+8}:space-'])
        events.extend(['15000:copy+', '15008:copy-'])
    if from_stairs:
        events.extend(['17000:copy+', '17008:copy-'])
        for i in range(choice):
            f = 17300 + 150*i
            events.extend([f'{f}:space+', f'{f+8}:space-'])
        events.extend(['19000:copy+', '19008:copy-'])
    last = 16999 if from_terrace else 12499
    if from_stairs:
        last = 20999
    args = [str(root/'dsk2jumphp/emulator/cpc'), str(root/'Zombi__FRENCH__Side_A.dsk'),
            str(out), str(last+1), '-rom', str(root/'ROM CPC 6128 OS + BASIC et AMSDOS/CPC6128.ROM'),
            '-urom', '7:'+str(root/'ROM CPC 6128 OS + BASIC et AMSDOS/amsdos.rom'),
            '-type', '150:RUN3WO*BI\\n', '-swap', '8100:'+str(root/'Zombi__FRENCH__Side_B.dsk'),
            '-k', ','.join(events), '-s', f'{14900 if from_terrace else 10900},{last}', '-d', f'8900,{last}']
    subprocess.run(args, check=True)
    for p in out.glob('*.ppm'):
        Image.open(p).save(p.with_suffix('.png'))
    d = Dump(out/f'dump-{last:05d}.bin')
    character = int.from_bytes(d.mem[0x6615:0x6617], 'little')
    room = int.from_bytes(d.mem[character:character+2], 'little')
    print('choice', choice, 'character', hex(character), 'map pointer', hex(room),
          'room id', d.mem[room], 'state', d.mem[character:character+40].hex(), flush=True)
