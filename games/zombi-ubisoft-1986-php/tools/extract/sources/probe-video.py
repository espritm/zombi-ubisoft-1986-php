"""Insert an actual held tape through the original inventory and player controls."""
from importlib.machinery import SourceFileLoader
from pathlib import Path
import json,sys
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
tape=int(sys.argv[1]);tv=int(sys.argv[2]);root=Path('/workspace')
world=json.loads((root/'analysis/extracted/world.json').read_text());room=next(r for r in world['rooms'] if r['id']==29)
e=runner.Experiment(f'video-tape-{tape}-tv-{tv}',29,position_start=True)
def key(name):
    e.events += [f'{e.frame}:{name}+',f'{e.frame+24}:{name}-'];e.frame+=220
e.key=key
pos=int(room['map_address'],16)
e.pokes += [f'11000:6633={pos&255:02x}',f'11000:6634={pos>>8:02x}']
if tape:e.pokes += ['11000:66c3=32']
def cell(obj):
    row,col=next((y,x) for y,line in enumerate(room['objects_grid']) for x,v in enumerate(line) if v==obj)
    e.cell(row,col)
e.mark('initial')
if tape and tv:
    e.select(2);e.key('copy');e.key('copy');cell(60);e.key('copy');e.mark('insert-off-refused')
e.select(4);e.key('copy');cell(60);e.key('copy');e.mark('player-on')
if tape:
    e.select(2);e.key('copy');e.key('copy');cell(60);e.key('copy');e.mark('tape-inserted')
if tv:
    e.select(4);e.key('copy');cell(61);e.key('copy');e.mark('tv-on')
e.select(4);e.key('copy');cell(60);e.key('copy');e.mark('play',10);e.mark('after',1500)
e.run(watch='66e9')
from cpc import Dump
out=root/f'analysis/runs/{e.name}';states=[]
for frame,label in e.marks:
    d=Dump(out/f'dump-{frame:05d}.bin')
    states.append({'label':label,'tape':d.mem[0x66e9],'trained':[d.mem[a+38] for a in range(0x6633,0x66d3,40)],
        'minutes':d.mem[0x661e]+d.mem[0x661f]*60})
(out/'video-states.json').write_text(json.dumps(states,indent=2))
