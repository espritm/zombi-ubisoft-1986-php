"""Verify native storage/portable-container rules from controlled captures."""
from pathlib import Path
import json
root=Path('/workspace/analysis');checks=[]
def check(label,ok):
 assert ok,label
 checks.append(label)
cases=('put-cupboard','put-body','full-cupboard','full-body','nested-cupboard','nested-body','roundtrip-cupboard','roundtrip-body','drink','food','duplicate-bag','duplicate-held','guard0','guard1-held','guard1-bag','guard2','guard2-bag','take-portable27','take-portable28')
for case in cases:
 d=json.loads((root/f'runs/storage-{case}/storage.json').read_text());a=d['states'][0];b=next(x for x in d['states'] if x['label']=='after')
 check(case+' preserves nested identity contents',a['body30']==b['body30'])
 check(case+' preserves unused portable contents',a['portable27']==b['portable27'] and a['portable28']==b['portable28'])
 if case.startswith('guard'):
  allowed=case.startswith('guard2');check(case+' object selector guard',d['enteredTake']==allowed)
  items=a['items'][:]
  if allowed:items[items.index(0)]=29
  check(case+' inventory result',b['items']==items)
  check(case+' body contents retained',a['body29']==b['body29'])
  check(case+' body markers removed only on success',(29 in b['cells'])!=allowed)
 elif case.startswith('take-portable'):
  item=int(case[-2:]);check(case+' identity picked up',b['items']==[43,item,0,0,0]);check(case+' markers cleared',item not in b['cells'])
 elif case in ('full-cupboard','full-body','drink','food'):
  check(case+' rejected without mutation',a|{'label':'after'}==b)
 else:
  field='body29' if 'body' in case else 'cup';item=30 if case.startswith(('nested','roundtrip')) else 43
  slots=a[field][:];slots[slots.index(0)]=item
  check(case+' fills first empty slot',b[field]==slots)
  items=a['items'][:];items[items.index(item)]=0
  check(case+' removes first bag match',b['items']==items)
  check(case+' active item cleared by value',b['used']==(0 if item==a['used'] else a['used']))
  if case.startswith('roundtrip'):
   c=d['states'][-1];check(case+' identity retrieved',c['items'][0]==30)
   check(case+' source emptied',c[field]==a[field]);check(case+' nested contents still retained',c['body30']==a['body30'])
(root/'extracted/storage-rules.json').write_text(json.dumps({'checks':checks},indent=2))
print(str(len(checks))+' native storage checks passed')
