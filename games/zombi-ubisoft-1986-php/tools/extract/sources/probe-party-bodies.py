"""Observe native body placement and packed inventory after real firearm selection.

Only starting fixtures/cursor are patched; death and loot routines are untouched.
"""
from importlib.machinery import SourceFileLoader
import contextlib, io, sys
r=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
case=sys.argv[1];e=r.Experiment('body-'+case,34 if case=='sleep' else 62,position_start=True,lighting=True)
target=3 if case in ('self','combat') else 0;a=0x6633+40*target
items=[33,0,45,0,43] if case in ('sparse','stale-sparse') else [33,37,45,48,43]
for i,v in enumerate(items):e.pokes += [f'11000:{a+24+i:04x}={v:02x}']
e.pokes += [f'11000:{a+39:04x}=2b','11000:66c7=22']
if case=='sleep':
 pos=int(next(x['map_address'] for x in r.json.loads((r.ROOT/'analysis/extracted/world.json').read_text())['rooms'] if x['id']==34),16)
 e.pokes += [f'11000:6633={pos&255:02x}',f'11000:6634={pos>>8:02x}','11000:6654=10','11000:6653=39','11000:6f09=39','11000:6f0a=39']
if case=='vehicle':e.pokes+=['11000:6653=01']
if case in ('full','split','rowwrap'):
 for i in range(40):e.pokes += [f'11000:{0x746a+i:04x}=ff']
 for i in ({'full':[], 'split':[37,39], 'rowwrap':[29,30]}[case]):e.pokes += [f'11000:{0x746a+i:04x}=00']
if case in ('stale','stale-sparse'):
 for i,v in enumerate([50,51,52,53,54]):e.pokes += [f'11000:{0x66ed+i:04x}={v:02x}']
if case=='zombie':
 e.pokes += ['10050:6654=80','10050:6645=32','10050:6646=28']
 e.pc_pokes += ['8a04:8a63=00']
for t in (range(10050,14000,300) if case=='zombie' else (10050,11000,12000)):e.pokes += [f'{t}:6717=00',f'{t}:6718=00']
if case=='combat':
 e.pokes += ['10050:6654=80','11300:66bd=01','11300:66be=01']
 e.mark('before',0);e.mark('after',4500)
elif case=='reanimate':
 e.pokes += ['11000:6654=40','11000:6656=48','11000:6657=71','11000:748c=1d','11000:748d=1d']
 e.mark('before',0);e.mark('after',1500)
else:
 e.mark('before',0);e.select(3);e.key('copy')
 if case!='self':e.key('right')
 e.key('copy');e.mark('selected',100)
 if case=='zombie':e.key('copy')
 e.mark('after',100)
if case in ('loot','loot-item','transport'):
 e.select(1);e.key('copy');e.cell(3,8);e.key('copy');e.mark('loot-menu',100)
 if case=='loot-item':e.key('right')
 e.key('copy');e.mark('looted',100)
 if case=='transport':
  e.select(2);e.key('copy');e.key('copy');e.mark('drop-menu',100);e.key('copy');e.mark('dropped',100)
with contextlib.redirect_stdout(io.StringIO()):e.run(watch='66ec-6700')
from cpc import Dump
out=r.ROOT/f'analysis/runs/{e.name}';vals=[]
for f,label in e.marks:
 d=Dump(out/f'dump-{f:05d}.bin');rp=int.from_bytes(d.mem[0x6617:0x6619],'little')
 vals.append({'label':label,'active':int.from_bytes(d.mem[0x6615:0x6617],'little'),'room':list(d.mem[rp:rp+50]),
  'party':[{'state':d.mem[p+33],'aux':d.mem[p+32],'items':list(d.mem[p+24:p+29]),'used':d.mem[p+39],'F':d.mem[p+18]} for p in range(0x6633,0x66d3,40)],
  'bodyBags':[list(d.mem[0x66ed+5*i:0x66f2+5*i]) for i in range(4)],'beds':list(d.mem[0x6f09:0x6f0d])})
(out/'bodies.json').write_text(r.json.dumps(vals,indent=2))
print(r.json.dumps({'case':case,'states':[{'label':v['label'],'active':v['active'],'target':v['party'][target],'bag':v['bodyBags'][target],'markers':[(i,x) for i,x in enumerate(v['room'][10:]) if x in range(29,33)],'beds':v['beds']} for v in vals]}))
