from pathlib import Path
import json
root=Path('/workspace/analysis');checks=[]
def states(case):return json.loads((root/f'runs/zombie-party-{case}/results.json').read_text())['results']
def check(label,condition):
 assert condition,label
 checks.append(label)
low=states('low');check('melee zombie starts with F5/H40',low[0]['party'][0]['gauges_F_H_S']==[5,40,80]);check('melee permanent death',low[-1]['party'][0]['state']==255);check('melee sequential gauges',low[-1]['party'][0]['gauges_F_H_S']==[0,37,80]);check('no ordinary corpse count',low[-1]['room_record'][8]==0);check('no enemy count underflow',low[-1]['room_record'][6]==0)
idle=states('idle');check('zombie attacks living survivor',idle[-1]['party'][3]['gauges_F_H_S'][0]<70);check('idle zombie not damaged',idle[-1]['party'][0]['gauges_F_H_S']==[50,40,80])
for case in ('other','vehicle'):
 s=states(case);check(case+' excludes combat',s[-1]['party'][3]['gauges_F_H_S']==[70,80,80]);check(case+' preserves zombie',s[-1]['party'][0]['state']==128)
for case,gauges,state in [('shot-28',[38,28,80],128),('shot-1',[0,40,80],255)]:
 s=states(case);check(case+' native gauges',s[-1]['party'][0]['gauges_F_H_S']==gauges);check(case+' state',s[-1]['party'][0]['state']==state);check(case+' no corpse',s[-1]['room_record'][8]==0)
(root/'extracted/zombie-party-rules.json').write_text(json.dumps({'checks':checks},indent=2));print(str(len(checks))+' native zombie party checks passed')
