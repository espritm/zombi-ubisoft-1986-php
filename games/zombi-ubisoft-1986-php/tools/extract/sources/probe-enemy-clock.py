"""Observe enemy room counts across the original clock; no enemy or time pokes."""
from importlib.machinery import SourceFileLoader
import json
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
from cpc import Dump
e=runner.Experiment('enemy-clock',62,position_start=True)
e.mark('initial',0)
for i in range(1,5):e.mark(f'interval-{i}',15500)
e.run(watch='6876-7941')
out=runner.ROOT/'analysis/runs/enemy-clock';result=[]
for frame,label in e.marks:
    d=Dump(out/f'dump-{frame:05d}.bin')
    rooms={str(i):list(d.mem[0x6876+(i-1)*50:0x6876+i*50]) for i in range(1,91)}
    result.append({'label':label,'frame':frame,'clock':list(d.mem[0x661e:0x6620]),'target':d.mem[0x6621],'timer':int.from_bytes(d.mem[0x6720:0x6722],'little'),'phase':d.mem[0x6713],'rooms':rooms})
    print(label,result[-1]['clock'],'enemies',sum(v[6] for v in rooms.values()),'bodies',sum(v[8] for v in rooms.values()))
(out/'enemy-states.json').write_text(json.dumps(result,indent=2))
