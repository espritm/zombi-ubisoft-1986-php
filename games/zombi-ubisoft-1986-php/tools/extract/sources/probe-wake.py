"""Manual wake after real bed use; only case preconditions are patched before the action."""
from importlib.machinery import SourceFileLoader
import sys
r=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
case=sys.argv[1];world=r.json.loads((r.ROOT/'analysis/extracted/world.json').read_text())
pos=int(next(x['map_address'] for x in world['rooms'] if x['id']==34),16)
e=r.Experiment('wake-'+case,34,position_start=True,lighting=True)
e.pokes += [f'10050:6633={pos&255:02x}',f'10050:6634={pos>>8:02x}','11000:66bc=32','11000:66c9=14','11000:66c8=05']
e.mark('initial',0);e.select(6);e.key('copy');e.cell(2,5 if case=='bed59' else 3);e.key('copy');e.mark('sleeping',100)
e.key('right');e.key('copy');e.mark('alexandre',100)
t=e.frame
if case=='remote':e.pokes += [f'{t}:66ab=62',f'{t}:66ac=68']
if case in ('awake','dead','zombie','gone'):e.pokes += [f'{t}:66cc='+{'awake':'00','dead':'40','zombie':'80','gone':'ff'}[case]]
if case=='duplicate':e.pokes += [f'{t}:6653=39']
for f in range(t,t+600,100):e.pokes += [f'{f}:6717=00',f'{f}:6718=00']
e.select(7);e.key('copy');e.mark('menu',100);e.cell(2,5) if case=='free' else None
e.key('copy');e.mark('after',100)
run=r.subprocess.run;r.subprocess.run=lambda cmd,**kw:run(cmd+['-b','a19c','-b-after','10000'],**kw)
e.run(watch='66bc-66d2')
from cpc import Dump
out=r.ROOT/f'analysis/runs/{e.name}';vals=[]
for f,label in e.marks:
 d=Dump(out/f'dump-{f:05d}.bin');vals.append({'label':label,'party':[{'state':d.mem[a+33],'bed':d.mem[a+32],'S':d.mem[a+17],'food':d.mem[a+29],'awake':d.mem[a+30],'ticks':int.from_bytes(d.mem[a+35:a+38],'little')} for a in range(0x6633,0x66d3,40)],'bedCells':[d.mem[0x6ee8+i] for i in (33,34,35,36)]})
# The breakpoint follows the state write and precedes clearing the bed association.
paths=sorted(out.glob('bpa19c-*.bin'),key=lambda p:int(p.stem.split('-')[-1]));assert paths or case=='free'
if paths:
 x=Dump(paths[0]);vals.append({'label':'at-wake-write','patrickTicks':int.from_bytes(x.mem[0x66ce:0x66d1],'little')})
(out/'wake.json').write_text(r.json.dumps(vals,indent=2))

