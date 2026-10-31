"""Controlled entrance states, with the native hourly algorithm left intact."""
from importlib.machinery import SourceFileLoader
import json,sys
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
from cpc import Dump
case=sys.argv[1];e=runner.Experiment('enemy-'+case,62,position_start=True)
for room,field in ((19,0),(31,1),(32,3)):
    base=0x6876+(room-1)*50
    if case=='full':
        for i in range(6):e.pokes.append(f'11000:{base+i:04x}=1e')
        e.pokes.append(f'11000:{base+6:04x}=05')
    else:e.pokes.append(f'11000:{base+field:04x}={10 if case=="blocked" else 30:02x}')
e.mark('before',0);e.mark('after',15500);e.run(watch='6620-7941')
out=runner.ROOT/f'analysis/runs/enemy-{case}';states=[]
for frame,label in e.marks:
    d=Dump(out/f'dump-{frame:05d}.bin')
    rooms={str(i):list(d.mem[0x6876+(i-1)*50:0x6876+i*50]) for i in range(1,91)}
    states.append({'label':label,'clock':list(d.mem[0x661e:0x6620]),'target':d.mem[0x6621],'timer':int.from_bytes(d.mem[0x6720:0x6722],'little'),'rooms':rooms})
(out/'enemy-states.json').write_text(json.dumps(states,indent=2))
print(case,'quota',[s['target'] for s in states],'entry counts',[[s['rooms'][str(i)][6] for i in (19,31,32)] for s in states])
