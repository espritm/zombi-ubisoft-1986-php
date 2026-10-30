"""Controlled room-38 assay: take object 38, then drop it; no inventory patch."""
from pathlib import Path
import subprocess,sys,json
root=Path('/workspace')
sys.path.insert(0,str(root/'dsk2jumphp/tools'))
from cpc import Dump
out=root/'analysis/runs/inventory-38';out.mkdir(parents=True,exist_ok=True)
events=['8200:return+','8220:return-','8300:enter+','8320:enter-']
def key(frame,name): events.extend([f'{frame}:{name}+',f'{frame+8}:{name}-'])
for f in range(8600,9500,100): key(f,'right')
key(9700,'copy');key(10000,'copy')
# Selected action 10 -> 1, through the carousel.
for f in range(11600,12500,100): key(f,'right')
key(12600,'copy');key(12800,'down');key(12900,'down')
for f in range(13000,13500,100): key(f,'right')
key(13600,'copy')
# Action 2 (drop), choose the first inventory item with COPY, then a scene cell.
key(14500,'left');key(14700,'copy');key(15000,'copy');key(15300,'copy')
frames=[11499,13500,13900,14900,15200,15999]
subprocess.run([str(root/'dsk2jumphp/emulator/cpc'),str(root/'Zombi__FRENCH__Side_A.dsk'),str(out),'16000',
    '-rom',str(root/'ROM CPC 6128 OS + BASIC et AMSDOS/CPC6128.ROM'),
    '-urom','7:'+str(root/'ROM CPC 6128 OS + BASIC et AMSDOS/amsdos.rom'),
    '-type','150:RUN3WO*BI\\n','-swap','8100:'+str(root/'Zombi__FRENCH__Side_B.dsk'),
    '-F','8500:6862=26','-k',','.join(events),'-s',','.join(map(str,frames)),'-d',','.join(map(str,frames))],check=True)
results=[]
for frame in frames:
    d=Dump(out/f'dump-{frame:05d}.bin');d.screen().save(out/f'screen-{frame:05d}.png')
    char=int.from_bytes(d.mem[0x6615:0x6617],'little');room=int.from_bytes(d.mem[0x6617:0x6619],'little')
    results.append({'frame':frame,'action':d.mem[0x6601],'cursor':list(d.mem[0x661a:0x661c]),
        'inventory':list(d.mem[char+24:char+29]),'object38_cell':d.mem[room+46],
        'object_selection_pointer':hex(int.from_bytes(d.mem[0x661c:0x661e],'little'))})
(out/'results.json').write_text(json.dumps(results,indent=2))
assert results[0]['inventory']==[0]*5 and results[0]['object38_cell']==38
assert results[2]['inventory']==[38,0,0,0,0] and results[2]['object38_cell']==0
assert results[-1]['inventory']==[0]*5 and results[-1]['object38_cell']==38
print(json.dumps(results,indent=2))
