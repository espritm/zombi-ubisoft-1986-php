from pathlib import Path
import json
root=Path('/workspace/analysis');checks=[]
def ck(k,v):
 assert v,k
 checks.append(k)
for c in ('normal','bed59','remote','awake','dead','zombie','gone'):
 p=root/f'runs/wake-{c}';v={x['label']:x for x in json.loads((p/'wake.json').read_text())};a=v['after'];x=a['party'][3]
 for k,n in [('state',0),('bed',0),('S',50),('food',5),('awake',20)]:ck(c+' '+k,x[k]==n)
 ck(c+' timer retained',x['ticks']==v['at-wake-write']['patrickTicks'])
 ck(c+' selected bed freed',a['bedCells']==[56,56,58,58])
 s=json.loads((p/'results.json').read_text())['results'];ck(c+' actor unchanged',s[-1]['active_character']=='0x6633')
 ck(c+' target not moved',s[-1]['party'][3]['position']==s[-2]['party'][3]['position'])
p=root/'runs/wake-duplicate';v={x['label']:x for x in json.loads((p/'wake.json').read_text())};a=v['after'];x=a['party'][3]
for k,n in [('state',16),('bed',57),('S',50),('food',5),('awake',20)]:ck('duplicate Patrick '+k,x[k]==n)
ck('first record bed cleared',a['party'][0]['bed']==0);ck('first record awake',a['party'][0]['state']==0);ck('second timer continues',x['ticks']>v['menu']['party'][3]['ticks']);ck('duplicate clicked bed freed',a['bedCells']==[56,56,58,58]);ck('duplicate actor unchanged',json.loads((p/'results.json').read_text())['results'][-1]['active_character']=='0x6633')
p=root/'runs/wake-free';v={x['label']:x for x in json.loads((p/'wake.json').read_text())};a=v['after'];x=a['party'][3]
for k,n in [('state',16),('bed',57),('S',50),('food',5),('awake',20)]:ck('free bed preserves '+k,x[k]==n)
ck('free bed timer continues',x['ticks']>v['menu']['party'][3]['ticks']);ck('free bed no replacement',a['bedCells']==[57,57,58,58]);ck('free bed actor unchanged',json.loads((p/'results.json').read_text())['results'][-1]['active_character']=='0x6633')
(root/'extracted/manual-wake-rules.json').write_text(json.dumps({'checks':checks},indent=2));print(str(len(checks))+' native manual wake checks passed')
