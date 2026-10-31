"""Isolate native ranged scheduling/decision; RNG inputs and optional pending flag are explicit fixtures."""
from importlib.machinery import SourceFileLoader
import json,re,sys
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
from cpc import Dump
case=sys.argv[1];e=runner.Experiment('ranged-'+case,41,position_start=True)
raw={'low':5,'unarmed':63,'armed-edge':51,'unarmed-edge':52}.get(case,6)
e.pokes += [f'8500:6713={1 if case=="zombie" else 0:02x}','8500:8aff=3e','8500:8b00=05','8500:8af0=3e',f'8500:8af1={raw:02x}']
if case not in ('cadence','unarmed','armed-edge','unarmed-edge','zombie'):e.pokes += ['10060:670b=01']
if case=='empty':e.pokes += ['8500:704c=00']
if case=='low-f':e.pokes += ['8500:66bd=06']
if case=='low-h':e.pokes += ['8500:66be=06']
e.mark('after',0)
run=runner.subprocess.run;runner.subprocess.run=lambda cmd,**kwargs:run(cmd+['-b','8aae','-b-after','10000'],**kwargs)
e.run(watch='66bd-6718');out=runner.ROOT/f'analysis/runs/{e.name}'
shots=[]
for path in sorted(out.glob('bp8aae-*.bin'),key=lambda p:int(p.stem.split('-')[-1])):
 d=Dump(path);shots.append({'F':d.mem[0x66bd],'H':d.mem[0x66be],'hp':d.mem[0x670c],'strength':d.mem[0x670d],'pending':d.mem[0x670b]})
ticks=[]
for line in (out/'log.txt').read_text().splitlines():
 m=re.fullmatch(r'write 670b=([0-9a-f]+) pc=802c f=(\d+)',line)
 if m and int(m[2])>=10000:ticks.append([int(m[2]),int(m[1],16)])
d=Dump(out/f'dump-{e.marks[-1][0]:05d}.bin')
r={'case':case,'raw':raw,'shots':shots,'ticks':ticks,'pending':d.mem[0x670b],'shooter':d.mem[0x670e]}
(out/'ranged.json').write_text(json.dumps(r,indent=2));print(case,'shots',shots[:3],'ticks',ticks[:4],'pending',r['pending'])
