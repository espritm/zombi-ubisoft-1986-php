"""One native attack with controlled RNG inputs; record exposure and fatal write order."""
from importlib.machinery import SourceFileLoader
import sys
r=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
case=sys.argv[1];e=r.Experiment('exposure-'+case,62 if case=='party' else 41,position_start=True)
e.pokes += ['8500:8af0=3e','8500:8af1=00','8500:8815=3e','8500:8816=00',f'8500:6713={0 if case=="raider" else 1:02x}',f'10050:66c2={0 if case in ("zombie","party") else 250:02x}']
if case=='party':e.pokes+=['10050:6654=80']
if case=='fatal-f':e.pokes+=['10050:66bd=02']
if case=='fatal-h':e.pokes+=['10050:66be=02']
e.mark('after',1000)
run=r.subprocess.run;r.subprocess.run=lambda cmd,**kw:run(cmd+['-b','88a2','-b-after','10050'],**kw)
e.run(watch='66bc-66d2')
from cpc import Dump
out=r.ROOT/f'analysis/runs/{e.name}';paths=sorted(out.glob('bp88a2-*.bin'),key=lambda p:int(p.stem.split('-')[-1]));assert paths
x=Dump(paths[0]);v={'case':case,'F':x.mem[0x66bd],'H':x.mem[0x66be],'exposure':x.mem[0x66c2]};(out/'exposure.json').write_text(r.json.dumps(v,indent=2));print(v)
