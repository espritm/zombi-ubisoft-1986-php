"""Native timed COPY shots: no cursor or damage pokes; only firearm/enemy preconditions."""
from importlib.machinery import SourceFileLoader
import sys,json
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
from cpc import Dump
delay=int(sys.argv[1]);e=runner.Experiment(f'natural-shot-{delay}',41,position_start=True)
e.pokes += ['11000:66c7=22','8500:704c=00']
e.pc_pokes += ['949e:704c=01','949e:670c=1f','949e:670d=1b']
e.mark('before',0);e.select(3);e.key('copy');e.mark('aiming',150);e.frame+=delay;e.key('copy');e.mark('after',100)
run=runner.subprocess.run;runner.subprocess.run=lambda cmd,**kwargs:run(cmd+['-b','8a04','-b-after','11200'],**kwargs)
e.run(watch='670c-670d');out=runner.ROOT/f'analysis/runs/{e.name}'
shots=[]
for path in out.glob('bp8a04-*.bin'):
 d=Dump(path);shots.append({'cursor':d.mem[0x8a63],'width':d.mem[0x8a64],'hp':d.mem[0x670c]})
d=Dump(out/f'dump-{e.marks[-1][0]:05d}.bin');result={'shots':shots,'hp':d.mem[0x670c],'count':d.mem[0x704c]}
(out/'shots.json').write_text(json.dumps(result,indent=2));print(delay,result)
