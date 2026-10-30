"""Drive the three original trucks with controlled starting position/key/passengers."""
from importlib.machinery import SourceFileLoader
from pathlib import Path
import json,sys
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
from cpc import Dump
root=Path('/workspace');world=json.loads((root/'analysis/extracted/world.json').read_text())
rooms={r['id']:r for r in world['rooms']+world['vehicle_interiors']}
source=int(sys.argv[1]);key=int(sys.argv[2]);door={72:2,77:1,82:3}[source]
inside=door+86;target={72:66,77:65,82:64}[source]
e=runner.Experiment(f'truck-{source}-key-{key}',source,position_start=True)
def press(name):
    e.events += [f'{e.frame}:{name}+',f'{e.frame+24}:{name}-'];e.frame+=220
e.key=press
e.pokes += [f'8500:{0x6876+(source-1)*50+6:04x}=00']
e.pokes += [f'8500:{0x6876+(target-1)*50+6:04x}=00']
if key:e.pokes += ['11000:66c7=30']
# Alexandre is in the same cab; Sylvie in a different cab. These are controlled riders.
pos=int(rooms[source]['map_address'],16)
e.pokes += [f'11000:6633={pos&255:02x}',f'11000:6634={pos>>8:02x}',f'11000:6653={inside:02x}',
    f'11000:667b={87 if inside==89 else 89:02x}']
def cell(room,obj):
    y,x=next((y,x) for y,row in enumerate(rooms[room]['objects_grid']) for x,v in enumerate(row) if v==obj)
    e.cell(y,x)
e.mark('initial');e.select(8);e.key('copy');cell(source,door);e.key('copy');e.mark('open')
e.select(16);e.key('copy');e.mark('inside')
e.select(4);e.key('copy');cell(inside,63);e.key('copy');e.mark('contact')
e.key('copy');cell(inside,71);e.key('copy');e.mark('driven',1800)
e.select(17);e.key('copy');e.mark('outside',500)
e.pokes += [f'{e.frame}:66ab={pos&255:02x}',f'{e.frame}:66ac={pos>>8:02x}']
e.select(10);e.key('copy');e.key('copy');e.mark('source-after',500)
run=runner.subprocess.run
runner.subprocess.run=lambda cmd,**kwargs:run(cmd+['-b','9d98','-b-after','11200'],**kwargs)
e.run()
out=root/f'analysis/runs/{e.name}';reports=[]
for frame,label in e.marks:
    d=Dump(out/f'dump-{frame:05d}.bin')
    reports.append({'label':label,'rooms':{str(i):list(d.mem[0x6876+(i-1)*50:0x6876+i*50]) for i in (19,31,32,64,65,66,72,77,82,87,88,89)},
        'positions':[int.from_bytes(d.mem[a:a+2],'little')-0x6726 for a in range(0x6633,0x66d3,40)],
        'interiors':[d.mem[a+32] for a in range(0x6633,0x66d3,40)]})
(out/'truck-states.json').write_text(json.dumps(reports,indent=2))
if key:
    for room,variant in ((source,'empty'),(target,'parked')):
        records=[]
        for p in out.glob('bp9d98-*.bin'):
            d=Dump(p);rp=int.from_bytes(d.mem[0x6617:0x6619],'little')
            if rp==0x6876+(room-1)*50 and d.mem[rp+9]==(0 if variant=='empty' else 77):
                records.append((int(p.stem.split('-')[-1]),d))
        assert records,(room,variant)
        background=max(records,key=lambda p:p[0])[1].screen().crop((8,48,168,128))
        assets=root/'analysis/extracted/trucks';assets.mkdir(exist_ok=True)
        background.save(assets/f'background-{room:02d}-{variant}.png')
print('Truck observation',source,'key',key)
