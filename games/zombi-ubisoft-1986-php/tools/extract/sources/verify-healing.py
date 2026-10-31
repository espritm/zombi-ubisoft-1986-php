from pathlib import Path
import json
root=Path('/workspace/analysis');checks=[]
def check(k,v):
 assert v,k
 checks.append(k)
for case in ('normal','cap','sleep','dead','zombie','gone','other','vehicle','low-score','high-score','cooldown','self'):
 p=root/f'runs/heal-{case}';s=json.loads((p/'results.json').read_text())['results'];h=json.loads((p/'healing.json').read_text())
 check(case+' starts shared cooldown',h[1]['flag']==1)
 check(case+' retains kit',h[-1]['held']==33)
 i=3 if case=='self' else 0
 expected=[50,40,80] if case in ('normal','vehicle','low-score','high-score','cooldown','self') else ([70,70,80] if case=='cap' else [40,30,80])
 check(case+' healing result',s[1]['party'][i]['gauges_F_H_S']==expected)
 check(case+' repeat blocked',s[2]['party'][i]['gauges_F_H_S']==expected)
 if case in ('low-score','high-score'):check(case+' sickness unchanged',h[1]['illness']==2)
h=json.loads((root/'runs/heal-cooldown/healing.json').read_text());s=json.loads((root/'runs/heal-cooldown/results.json').read_text())['results']
check('cooldown expires at 15000 ticks',h[3]['flag']==0 and h[3]['timer']>=15000)
check('healing after expiry',s[-1]['party'][0]['gauges_F_H_S']==[60,50,80])
(root/'extracted/healing-rules.json').write_text(json.dumps({'checks':checks},indent=2));print(str(len(checks))+' native healing checks passed')

