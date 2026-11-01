"""Native POSER/PRENDRE with controlled containers and inventory, no transfer-code patches."""
from importlib.machinery import SourceFileLoader
import contextlib,io,sys
r=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
case=sys.argv[1];e=r.Experiment('storage-'+case,62,position_start=True,lighting=True)
body='body' in case;guard=case.startswith('guard');portable=case.startswith('take-portable');target=29 if body or guard else (int(case[-2:]) if portable else 23)
item=30 if case.startswith(('nested','roundtrip')) else (54 if case=='drink' else (55 if case=='food' else 43))
items=[item,0,0,0,0]
if case.startswith('duplicate'):items=[43,33,0,0,43]
if guard:items={'guard0':[43,33,45,48,34],'guard1-held':[43,33,45,48,0],'guard1-bag':[43,33,45,0,34],'guard2':[43,33,45,0,0],'guard2-bag':[43,33,0,0,34]}[case]
for i,v in enumerate(items):e.pokes += [f'11000:{0x66c3+i:04x}={v:02x}']
e.pokes += ['11000:66d2=2b','11000:7482=17','11000:7484=1d','11000:7485=1d']
if portable:e.pokes += [f'11000:7482={target:02x}']
cup=[33,45] if case=='full-cupboard' else ([0,0] if case.startswith(('nested','roundtrip')) else [33,0])
bag=[33,45,48,50] if case=='full-body' else ([0,0,0,0] if case.startswith(('nested','roundtrip')) else [33,0,45,0])
for a,values in ((0x66d4,cup),(0x66e1,[33,0]),(0x66e4,[37,38,0,0]),(0x66ed,bag),(0x66f2,[37,38,0,0])):
 for i,v in enumerate(values):e.pokes += [f'11000:{a+i:04x}={v:02x}']
e.mark('before',0)
if guard or portable:
 e.select(1);e.key('copy');e.mark('opened',100);e.cell(2,6 if guard else 4);e.key('copy');e.key('copy');e.mark('after',100)
else:
 e.select(2);e.key('copy')
 if case=='duplicate-held':
  for _ in range(3):e.key('right')
 e.key('copy');e.cell(2,6 if body else 4);e.key('copy');e.mark('after',100)
 if case.startswith('roundtrip'):
  e.select(1);e.key('copy');e.key('copy')
  if body:e.key('right')
  e.key('copy');e.mark('returned',100)
run=r.subprocess.run;r.subprocess.run=lambda cmd,**kw:run(cmd+(['-b','a1c6','-b-after','10000'] if guard else []),**kw)
with contextlib.redirect_stdout(io.StringIO()):e.run(watch='66d3-6700')
from cpc import Dump
out=r.ROOT/f'analysis/runs/{e.name}';vals=[]
for frame,label in e.marks:
 d=Dump(out/f'dump-{frame:05d}.bin');vals.append({'label':label,'items':list(d.mem[0x66c3:0x66c8]),'used':d.mem[0x66d2],
  'cup':list(d.mem[0x66d4:0x66d6]),'portable27':list(d.mem[0x66e1:0x66e3]),'portable28':list(d.mem[0x66e4:0x66e8]),'body29':list(d.mem[0x66ed:0x66f1]),'body30':list(d.mem[0x66f2:0x66f6]),'cells':list(d.mem[0x746a:0x7492])})
(out/'storage.json').write_text(r.json.dumps({'case':case,'enteredTake':bool(list(out.glob('bpa1c6-*.bin'))),'states':vals},indent=2))
print(r.json.dumps({'case':case,'states':[{k:v for k,v in x.items() if k!='cells'} for x in vals]}))
