"""Native stat formula: optional replacement of LD A,R by LD A,n supplies only the random input."""
from importlib.machinery import SourceFileLoader
import json,re,sys
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
phase=int(sys.argv[1]);raw=sys.argv[2];e=runner.Experiment(f'encounter-{phase}-{raw}',41,position_start=True)
e.pokes += [f'8500:6713={phase:02x}']
if raw!='natural':e.pokes += ['8500:8aff=3e',f'8500:8b00={int(raw):02x}']
e.mark('after',0);e.run(watch='670c-670e');out=runner.ROOT/f'analysis/runs/{e.name}';pairs=[];hp=None
for line in (out/'log.txt').read_text().splitlines():
 m=re.fullmatch(r'write (670c|670d)=([0-9a-f]+) pc=(8b04|8b09) f=(\d+)',line)
 if not m or int(m[4])<10000:continue
 if m[1]=='670c':hp=int(m[2],16)
 elif hp is not None:pairs.append([hp,int(m[2],16)])
assert pairs
(out/'stats.json').write_text(json.dumps({'phase':phase,'random':raw,'pairs':pairs},indent=2))
print(e.name,'pairs',sorted(set(map(tuple,pairs))))
