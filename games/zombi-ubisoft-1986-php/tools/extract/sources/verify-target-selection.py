"""Check native selector outcomes; fixtures and input script are recorded in each capture."""
from pathlib import Path
import json
root=Path('/workspace/analysis');checks=[]
def check(label,ok):
 assert ok,label
 checks.append(label)
for case in ('living','sleep','dead','zombie','gone','other','vehicle','self'):
 s=json.loads((root/f'runs/target-{case}/results.json').read_text())['results'];a,b=s[0],s[-1]
 check(case+' preserves active identity',b['active_character']=='0x66ab')
 if case in ('living','sleep','dead'):
  check(case+' permanent elimination',b['party'][0]['state']==255)
  check(case+' gauges unchanged',b['party'][0]['gauges_F_H_S']==[50,40,80])
 elif case in ('zombie','vehicle'):
  check(case+' partial damage',b['party'][0]['gauges_F_H_S']==[38,28,80])
  check(case+' remains zombie',b['party'][0]['state']==128)
 elif case in ('gone','other'):
  check(case+' rejected unchanged',b['party'][0]==a['party'][0])
 else:
  check('self permanent death',b['party'][3]['state']==255)
  check('self gauges unchanged',b['party'][3]['gauges_F_H_S']==[70,80,80])
 check(case+' no ordinary corpse',b['room_record'][8]==0 and b['room_record'][6]==0)
(root/'extracted/target-selection-rules.json').write_text(json.dumps({'checks':checks},indent=2));print(str(len(checks))+' native target checks passed')
