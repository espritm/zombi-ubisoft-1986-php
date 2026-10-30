"""Observe lift call buttons and cabin panel with explicit electrical preconditions."""
from importlib.machinery import SourceFileLoader
from pathlib import Path
import json,sys
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
world=json.loads(Path('/workspace/analysis/extracted/world.json').read_text())
room=int(sys.argv[1]) if len(sys.argv)>1 else 33
power=int(sys.argv[2]) if len(sys.argv)>2 else 0
passenger='--passenger' in sys.argv
e=runner.Experiment(f'lift-{room}-power-{power}' + ('-passenger' if passenger else ''),room,position_start=True)
def slow_key(name):
    e.events += [f'{e.frame}:{name}+',f'{e.frame+24}:{name}-']
    e.frame+=220
e.key=slow_key
address=0x6876+(room-1)*50
e.pokes += [f'8500:{address+6:04x}=00',f'11000:6712={power:02x}']
if passenger:e.pokes += ['8500:6633=60','8500:6634=67']
e.mark('initial')
e.select(4);e.key('copy');e.cell(0,0 if room==63 else 3 if room in (33,36,49) else 2);e.key('copy');e.mark('activated',500)
e.key('down');e.mark('choice-next')
e.key('copy');e.mark('confirmed',2000)
run=runner.subprocess.run
runner.subprocess.run=lambda cmd,**kwargs:run(cmd+['-r','9ef8,9a2b,9f1b','-X','16'],**kwargs)
e.run(watch='6710-6725')
from cpc import Dump
out=Path(f'/workspace/analysis/runs/{e.name}')
report=[]
for frame,label in e.marks:
    d=Dump(out/f'dump-{frame:05d}.bin')
    cells=list(d.mem[0x6726:0x6876]);base=0x6876+62*50
    report.append({'label':label,'lift_cells':[i for i,v in enumerate(cells) if v==63],
        'lift_prefix':list(d.mem[base:base+10]),
        'landing_south':{str(r['id']):d.mem[0x6876+(r['id']-1)*50+2] for r in world['rooms'] if r['id'] in (8,33,36,49,59)},
        'positions':[int.from_bytes(d.mem[a:a+2],'little')-0x6726 for a in range(0x6633,0x66d3,40)]})
(out/'lift-states.json').write_text(json.dumps(report,indent=2))
