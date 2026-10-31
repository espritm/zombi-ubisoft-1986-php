"""Isolate original freezer/arrival and top-landing unlock conditions, without patching phase/fuel flags."""
from pathlib import Path
from importlib.machinery import SourceFileLoader
import json,sys
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
from cpc import Dump
root=Path('/workspace');case=sys.argv[1];world=json.loads((root/'analysis/extracted/world.json').read_text())
rooms={r['id']:r for r in world['rooms']+world['vehicle_interiors']}
unlock=case.startswith('unlock');key=int(case[-1]) if unlock else 0
e=runner.Experiment('quest-'+case,63 if unlock else 11,position_start=True)
def press(name):
    e.events += [f'{e.frame}:{name}+',f'{e.frame+24}:{name}-'];e.frame+=220
e.key=press
if unlock:
    e.pokes += ['8500:7498=00'] # room 63 enemy count
    if key:e.pokes += ['11000:66c7=31']
    e.mark('initial');e.select(4);e.key('copy');e.cell(0,0);e.key('copy')
    for _ in range(3):e.key('up')
    e.key('copy');e.mark('top-locked')
    e.select(8);e.key('copy');e.key('f');e.key('copy');e.mark('opened')
    e.select(15);e.key('copy');e.key('copy');e.mark('entered')
else:
    target=Dump(root/'analysis/runs/direction-3/dump-08900.bin').mem[0x6621]
    count=target-(2 if case=='short' else 1)
    e.pokes += [f'11000:6620={count:02x}','11000:66c3=35']
    for room in (64,65,66):e.pokes += [f'11000:{0x6876+(room-1)*50+9:04x}={0 if case=="unparked" and room==66 else 77:02x}']
    e.mark('initial');e.select(2);e.key('copy');e.key('copy');e.mark('deposited',700)
    if case=='ready':
        p=int(rooms[78]['map_address'],16)
        e.pokes += [f'{e.frame}:66ab={p&255:02x}',f'{e.frame}:66ac={p>>8:02x}',f'{e.frame}:7786=00']
        e.select(10);e.key('copy');e.key('copy');e.mark('fuel-truck',500)
run=runner.subprocess.run
runner.subprocess.run=lambda cmd,**kwargs:run(cmd+['-b','9d98','-b-after','11200'],**kwargs)
e.run(watch='6713')
out=root/f'analysis/runs/{e.name}';states=[]
for frame,label in e.marks:
    d=Dump(out/f'dump-{frame:05d}.bin')
    states.append({'label':label,'frozen':d.mem[0x6620],'target':d.mem[0x6621],'phase':d.mem[0x6713],
        'rooms':{str(r):list(d.mem[0x6876+(r-1)*50:0x6876+r*50]) for r in (11,19,21,22,23,59,63,64,65,66,77,78)},
        'fuel':[d.mem[0x6710],d.mem[0x6711]],'used':d.mem[0x66d2]})
(out/'quest-states.json').write_text(json.dumps(states,indent=2))
if case=='ready':
    candidates=[]
    for path in out.glob('bp9d98-*.bin'):
        d=Dump(path);p=int.from_bytes(d.mem[0x6617:0x6619],'little')
        if p==0x7780 and d.mem[p+9]==78:candidates.append((int(path.stem.split('-')[-1]),d))
    assert candidates
    max(candidates,key=lambda v:v[0])[1].screen().crop((8,48,168,128)).save(root/'analysis/extracted/raiders-background.png')
