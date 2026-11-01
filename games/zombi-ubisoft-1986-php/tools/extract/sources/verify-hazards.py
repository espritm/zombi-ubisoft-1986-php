"""Check the observed native fall destinations, shock byte order and death cleanup."""
from pathlib import Path
import json
root=Path('/workspace/analysis');checks=[]
def check(label,ok):
 assert ok,label
 checks.append(label)
cases=('fall35','fall38','fall46','fall-full35','fall-sparse35','held35','fall-last35','rope35','f19','f20','h9','h10','both','both-under','h-under','zero','gloves','gloves-held','normal','last','f147','f148','h137','h138','full')
for case in cases:
 a,b=json.loads((root/f'runs/hazard-{case}/hazards.json').read_text())
 fall=case.startswith(('fall','held'));rope=case.startswith('rope')
 destination={35:64,38:66,46:65}[a['mapRoom']] if fall or rope else 38
 expected=a['gauges'][:];fatal=fall
 if not fall and not rope and case!='gloves':
  for i,loss in enumerate((20,10)):
   value=(expected[i]-loss)&255
   if value>=128:fatal=True;break
   expected[i]=value
 if not rope:
  check(case+' gauge write order',b['gauges']==expected)
  check(case+' retains party map position',b['position']==a['position'])
  check(case+' state',b['state']==(255 if fatal else 0))
 if fatal:
  transfer=case!='last'
  items=[x for x in a['items'] if x];bag=a['bodyBag'][:];bag[:len(items)]=items
  check(case+' transfer order or last-shock early exit',b['bodyBag']==(bag if transfer else a['bodyBag']))
  check(case+' inventory cleanup',b['items']==([0]*5 if transfer else a['items']))
  check(case+' clears used item',b['used']==0)
  cells=a['rooms'][str(destination)][10:];after=b['rooms'][str(destination)][10:];indices=[]
  if transfer:
   for i in range(39,0,-1):
    if cells[i]==cells[i-1]==0:indices=[i-1,i];break
  check(case+' correct body placement',[i for i,x in enumerate(after) if x==32]==indices)
  check(case+' no ordinary corpse quota',b['rooms'][str(destination)][8]==a['rooms'][str(destination)][8])
  if fall:check(case+' no body on upper floor',32 not in b['rooms'][str(a['mapRoom'])][10:])
 elif not rope:
  check(case+' retains items except installed fuse',b['items']==([0]+a['items'][1:] if case=='gloves' else a['items']))
  check(case+' fuse installed only with active gloves',b['fuse']==(51 if case=='gloves' else 0))
  check(case+' no body inventory',b['bodyBag']==a['bodyBag'])
 else:
  check('active rope reaches room below',b['state']==0 and b['mapRoom']==64)
  check('active rope consumed',b['items'][-1]==0 and b['used']==0)
  check('rope left at source',41 in b['rooms']['35'][10:])
  check('rope no body transfer',b['bodyBag']==a['bodyBag'])
(root/'extracted/hazard-rules.json').write_text(json.dumps({'checks':checks},indent=2))
print(str(len(checks))+' native hazard checks passed')
