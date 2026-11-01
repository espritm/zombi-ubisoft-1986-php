"""Controlled window/fuse-box fixtures; native movement and shock/death code stay intact."""
from importlib.machinery import SourceFileLoader
import contextlib,io,sys
r=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
case=sys.argv[1];fall=case.startswith(('fall','rope','held'));room=int(case[-2:]) if fall else 38
world=r.json.loads((r.ROOT/'analysis/extracted/world.json').read_text());record=next(x for x in world['rooms'] if x['id']==room)
e=r.Experiment('hazard-'+case,room,position_start=True,lighting=True)
base=0x6876+(room-1)*50
e.pokes += [f'8500:{base+6:04x}=00']
for i,item in enumerate([51,0,45,0,43] if 'sparse' in case else [51,33,45,48,43]):e.pokes += [f'11000:{0x66c3+i:04x}={item:02x}']
e.pokes += ['11000:66d2=2b']
if case=='fall-full35':
 for i in range(40):e.pokes += [f'11000:{0x74ce+i:04x}=ff']
if 'last' in case:
 for a in (0x6654,0x667c,0x66a4):e.pokes += [f'11000:{a:04x}=ff']
if fall:
 def key(name):
  e.events += [f'{e.frame}:{name}+',f'{e.frame+24}:{name}-'];e.frame+=220
 e.key=key
 if case.startswith(('rope','held')):e.pokes += ['11000:66c7=29']
 if case.startswith('rope'):e.pokes += ['11000:66d2=29']
 direction=next(x for x in record['exits'] if x['flag'] in (240,250))['direction']
 choice=['north','up','south','east','down','west'].index(direction)
 obj=7 if any(7 in row for row in record['objects_grid']) else 8
 cell=next(((y,x) for y,row in enumerate(record['objects_grid']) for x,v in enumerate(row) if v==obj),(0,0))
 if any(x['flag']==240 for x in record['exits']):
  e.select(8);e.key('copy');e.cell(*cell);e.key('copy')
 e.select(15);e.key('copy')
 for _ in range(choice):e.key('space')
 e.mark('before',0);e.key('copy');e.mark('after',1600)
else:
 values={'f19':(19,80),'f20':(20,80),'h9':(70,9),'h10':(70,10),'both':(20,10),'both-under':(19,9),'h-under':(20,9),'zero':(0,80),'f147':(147,80),'f148':(148,80),'h137':(70,137),'h138':(70,138),'full':(19,80)}
 f,h=values.get(case,(70,80))
 e.pokes += [f'11000:66bd={f:02x}',f'11000:66be={h:02x}',f'11000:{base+42:04x}=16']
 if case=='last':e.pokes += ['11000:66bd=13']
 if case=='gloves':e.pokes += ['11000:66c7=2d','11000:66d2=2d']
 if case=='gloves-held':e.pokes += ['11000:66c7=2d']
 if case=='full':
  for i in range(40):
   if i!=32:e.pokes += [f'11000:{base+10+i:04x}=ff']
 e.select(2);e.key('copy');e.key('copy');e.cell(3,2);e.mark('before',0);e.key('copy');e.mark('after',1800)
with contextlib.redirect_stdout(io.StringIO()):e.run(watch='66ab-66d2')
from cpc import Dump
out=r.ROOT/f'analysis/runs/{e.name}';vals=[]
for frame,label in e.marks:
 d=Dump(out/f'dump-{frame:05d}.bin');a=0x66ab;pos=int.from_bytes(d.mem[a:a+2],'little')
 vals.append({'label':label,'state':d.mem[a+33],'position':pos,'mapRoom':d.mem[pos],'gauges':[d.mem[a+18],d.mem[a+19],d.mem[a+17]],
  'items':list(d.mem[a+24:a+29]),'used':d.mem[a+39],'bodyBag':list(d.mem[0x66fc:0x6701]),'fuse':d.mem[0x66eb],
  'rooms':{str(i):list(d.mem[0x6876+(i-1)*50:0x6876+i*50]) for i in (35,38,46,64,65,66)}})
(out/'hazards.json').write_text(r.json.dumps(vals,indent=2))
print(r.json.dumps({'case':case,'states':[{k:v for k,v in x.items() if k!='rooms'} for x in vals]}))
