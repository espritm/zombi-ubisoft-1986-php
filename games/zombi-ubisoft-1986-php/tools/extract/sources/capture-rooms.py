"""Controlled original renders, not claims that rooms were reached in normal play.

Change only the room ID at the known starting map cell, then ask the original's
character-selection action to refresh it. Original loader, drawing and objects run.
"""
from pathlib import Path
import subprocess, sys, json
from PIL import Image
root=Path('/workspace')
sys.path.insert(0,str(root/'dsk2jumphp/tools'))
from cpc import Dump
lit='--lit' in sys.argv
ids=[int(v) for v in sys.argv[1:] if v!='--lit'] or list(range(1,91))
for room in ids:
    if not 1<=room<=90:
        raise ValueError(room)
    out=root/f'analysis/runs/room-capture-{"lit-" if lit else ""}{room:02d}'
    out.mkdir(parents=True,exist_ok=True)
    events=['8200:return+','8220:return-','8300:enter+','8320:enter-']
    for f in range(8600,9500,100):
        events += [f'{f}:right+',f'{f+8}:right-']
    events += ['9700:copy+','9708:copy-','10000:copy+','10008:copy-']
    args=[str(root/'dsk2jumphp/emulator/cpc'),str(root/'Zombi__FRENCH__Side_A.dsk'),
        str(out),'11500','-rom',str(root/'ROM CPC 6128 OS + BASIC et AMSDOS/CPC6128.ROM'),
        '-urom','7:'+str(root/'ROM CPC 6128 OS + BASIC et AMSDOS/amsdos.rom'),
        '-type','150:RUN3WO*BI\\n','-swap','8100:'+str(root/'Zombi__FRENCH__Side_B.dsk'),
        '-F',f'8500:6862={room:02x}','-k',','.join(events),'-s','11499','-d','11499', '-b', '9d98', '-b-after','10000']
    if lit:
        args += ['-F','8500:6712=01']
    subprocess.run(args,check=True)
    d=Dump(out/'dump-11499.bin')
    actual=int.from_bytes(d.mem[0x6617:0x6619],'little')
    expected=0x6876+(room-1)*50
    if actual!=expected:
        raise RuntimeError(f'Room {room}: pointer {actual:x}, expected {expected:x}')
    img=d.screen()
    img.save(out/'screen.png')
    assets=root/f'analysis/extracted/rooms{"-lit" if lit else ""}';assets.mkdir(parents=True,exist_ok=True)
    img.crop((8,48,168,128)).save(assets/f'room-{room:02d}.png')
    candidates=[]
    for p in out.glob('bp9d98-*.bin'):
        before=Dump(p)
        if int.from_bytes(before.mem[0x6617:0x6619],'little') == expected:
            candidates.append((int(p.stem.split('-')[-1]),before))
    if not candidates:
        crop=img.crop((8,48,168,128))
        if crop.getextrema()!=((0,0),(0,0),(0,0)):
            raise RuntimeError(f'Room {room}: no pre-object render captured')
        crop.save(assets/f'background-{room:02d}.png')
    else:
        before=max(candidates,key=lambda v:v[0])[1]
        before.screen().crop((8,48,168,128)).save(assets/f'background-{room:02d}.png')
    print(json.dumps({'controlled_room':room,'record_pointer':hex(actual),'capture':str(assets/f'room-{room:02d}.png')}),flush=True)
