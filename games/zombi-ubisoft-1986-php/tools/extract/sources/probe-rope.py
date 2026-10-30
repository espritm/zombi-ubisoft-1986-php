"""Window traversal with controlled rope activation, no destination or health patches."""
from importlib.machinery import SourceFileLoader
from pathlib import Path
import json,sys
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
room=int(sys.argv[1]);rope=int(sys.argv[2]);world=json.loads(Path('/workspace/analysis/extracted/world.json').read_text())
r=next(r for r in world['rooms'] if r['id']==room)
direction=next(e for e in r['exits'] if e['flag'] in (240,250))['direction']
choices=['north','up','south','east','down','west'];choice=choices.index(direction)
e=runner.Experiment(f'rope-{room}-{rope}',room,position_start=True,lighting=True)
def key(name):
    e.events += [f'{e.frame}:{name}+',f'{e.frame+24}:{name}-'];e.frame+=220
e.key=key
base=0x6876+(room-1)*50;e.pokes += [f'8500:{base+6:04x}=00']
if rope:e.pokes += ['11000:66c7=29','11000:66d2=29']
obj=7 if any(7 in row for row in r['objects_grid']) else 8
cell=next(((y,x) for y,row in enumerate(r['objects_grid']) for x,v in enumerate(row) if v==obj),(0,0))
e.mark('initial')
if any(e['flag']==240 for e in r['exits']):
    e.select(8);e.key('copy');e.cell(*cell);e.key('copy')
e.mark('opened')
e.select(15);e.key('copy')
for _ in range(choice):e.key('space')
e.key('copy');e.mark('crossed',1600)
e.run()
