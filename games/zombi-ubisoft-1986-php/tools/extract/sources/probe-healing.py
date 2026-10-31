"""Native medkit selector, healing, shared cooldown and sickness roll."""
from importlib.machinery import SourceFileLoader
import sys
r=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
case=sys.argv[1];e=r.Experiment('heal-'+case,62,position_start=True)
state={'sleep':16,'dead':64,'zombie':128,'gone':255}.get(case,0)
f,h=(68,67) if case=='cap' else (40,30)
e.pokes += [f'10050:6654={state:02x}',f'10050:6645={f:02x}',f'10050:6646={h:02x}','10050:6655=02','11000:66c7=21']
if case=='other':e.pokes+=['10050:6633=b1','10050:6634=67']
if case=='vehicle':e.pokes+=['10050:6653=01']
if case=='low-score':e.pokes+=['10050:8af0=3e','10050:8af1=00']
if case=='high-score':e.pokes+=['10050:8af0=3e','10050:8af1=3f']
for t in range(10050,12100,300):e.pokes += [f'{t}:6717=00',f'{t}:6718=00']
if case=='self':e.pokes += ['11000:66bd=28','11000:66be=1e']
e.mark('before',0);e.select(3);e.key('copy')
if case!='self':e.key('right')
e.key('copy');e.mark('healed',100)
# A second ordinary use must remain blocked even if a different target is selected.
e.key('copy');e.mark('repeat',100)
if case=='cooldown':
 e.pokes += [f'{e.frame}:6715=96',f'{e.frame}:6716=3a']
 e.mark('expired',10);e.key('copy');e.key('right');e.key('copy');e.mark('again',100)
e.run(watch='6633-6718')
from cpc import Dump
out=r.ROOT/f'analysis/runs/{e.name}'
a=[]
for frame,label in e.marks:
 d=Dump(out/f'dump-{frame:05d}.bin');a.append({'label':label,'flag':d.mem[0x6714],'timer':int.from_bytes(d.mem[0x6715:0x6717],'little'),'illness':d.mem[0x6655],'held':d.mem[0x66c7]})
(out/'healing.json').write_text(r.json.dumps(a,indent=2))


