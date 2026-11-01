"""Verify recorded native body results, including the unsafe fifth-byte overflow.

The portable game deliberately uses independent five-slot records instead of that overflow.
"""
from pathlib import Path
import json
root=Path('/workspace/analysis');checks=[]
def check(label,ok):
 assert ok,label
 checks.append(label)
def load(case):return json.loads((root/f'runs/body-{case}/bodies.json').read_text())
for case in ('living','sparse','full','split','rowwrap','stale','stale-sparse','self','sleep','vehicle','loot','loot-item','transport','zombie','combat'):
 s=load(case);a,b=s[0],s[-1];idx=3 if case in ('self','combat') else 0
 p,q=a['party'][idx],b['party'][idx];items=[x for x in p['items'] if x];bag=a['bodyBags'][idx][:]
 bag[:len(items)]=items
 transferred=next(x for x in s if x['label']=='after')
 check(case+' transferred items in packed order without clearing tail',transferred['bodyBags'][idx]==bag)
 check(case+' empties bag and held',q['items']==[0]*5)
 check(case+' clears active item',q['used']==0)
 check(case+' state',q['state']==(64 if case=='combat' else 255))
 check(case+' no ordinary corpse count',b['room'][8]==a['room'][8]==0)
 if case not in ('loot','loot-item','transport'):
  cells=b['room'][10:];expected=[] if case in ('full','split') else ([29,30] if case=='rowwrap' else ([37,38] if case=='sleep' else [38,39]))
  check(case+' backward flat grid placement',[i for i,v in enumerate(cells) if v==29+idx]==expected)
 if case=='zombie':check('fatal zombie F zero',q['F']==0)
 if case=='sleep':
  check('sleeping bed freed',b['beds'][:2]==[56,56])
  check('sleeping association retained',q['aux']==57)
 if case=='loot-item':
  check('loot first item stored by actor',b['party'][3]['items'][0]==33)
  check('loot leaves body markers',b['room'][48:50]==[29,29])
 if case in ('loot','transport'):
  taken=next(x for x in s if x['label']=='looted')
  check(case+' body identity in actor bag',taken['party'][3]['items'][0]==29)
  check(case+' pickup removes markers',29 not in taken['room'][10:])
 if case=='transport':
  check('drop restores markers',b['room'][48:50]==[29,29])
  check('drop removes carried identity',29 not in b['party'][3]['items'])
s=load('reanimate');a,b=s[0],s[-1]
check('dead state before reanimation',a['party'][0]['state']==64)
check('zombie after reanimation',b['party'][0]['state']==128)
check('markers existed before reanimation',a['room'][44:46]==[29,29])
check('reanimation clears own markers',29 not in b['room'][10:])
check('reanimation leaves stored inventory',a['bodyBags']==b['bodyBags'])
(root/'extracted/party-body-rules.json').write_text(json.dumps({'checks':checks},indent=2))
print(str(len(checks))+' native party-body checks passed')
