"""Prepare active equipment and one pressure peak; native health/damage/random code is untouched."""
from importlib.machinery import SourceFileLoader
import json,re,sys
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
from cpc import Dump
weapon=int(sys.argv[1]);e=runner.Experiment(f'melee-damage-{weapon}',41,position_start=True)
e.pokes += [f'8500:66c7={weapon:02x}',f'8500:66d2={weapon:02x}']
e.pc_pokes += ['880d:6703=14'];e.mark('after',0)
run=runner.subprocess.run;runner.subprocess.run=lambda cmd,**kwargs:run(cmd+['-b','880d','-b-after','10000'],**kwargs)
e.run(watch='670c-670d');out=runner.ROOT/f'analysis/runs/{e.name}'
paths=sorted(out.glob('bp880d-*.bin'),key=lambda p:int(p.stem.split('-')[-1]));assert paths
d=Dump(paths[0]);before={'F':d.mem[0x66bd],'H':d.mem[0x66be],'hp':d.mem[0x670c],
 'threshold':d.mem[0x6704],'bonus':d.mem[0x6706],'peak':d.mem[0x6703]}
writes=[]
for line in (out/'log.txt').read_text().splitlines():
 m=re.fullmatch(r'write 670c=([0-9a-f]+) pc=888e f=(\d+)',line)
 if m and int(m[2])>=10000:writes.append(int(m[1],16))
assert writes
result={'weapon':weapon,'before':before,'afterHP':writes[0],'damage':before['hp']-writes[0]}
(out/'melee.json').write_text(json.dumps(result,indent=2));print(result)
