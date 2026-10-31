"""Verify native stat distributions, shooter decision, pending scheduling and short-circuit damage."""
from pathlib import Path
import json
root=Path('/workspace/analysis');checks=[]
def check(name,ok):
 assert ok,name
 checks.append(name)
for phase,base,total in ((1,26,58),(0,30,65)):
 natural=json.loads((root/f'runs/encounter-{phase}-natural/stats.json').read_text())['pairs']
 check(f'phase-{phase}-all-sixteen-natural-values',set(map(tuple,natural))=={(base+i,total-base-i) for i in range(16)})
 for raw in (0,1,7,15):
  pairs=json.loads((root/f'runs/encounter-{phase}-{raw}/stats.json').read_text())['pairs']
  check(f'phase-{phase}-input-{raw}',all(p==[base+raw,total-base-raw] for p in pairs))
def read(case):return json.loads((root/f'runs/ranged-{case}/ranged.json').read_text())
r=read('cadence')
check('two-native-menu-shots',len(r['shots'])==2)
check('first-six-damage',r['shots'][0]['F']==64 and r['shots'][0]['H']==74)
check('second-six-damage',r['shots'][1]['F']==58 and r['shots'][1]['H']==68)
check('interrupt-period-256',r['ticks'][1][0]-r['ticks'][0][0]==256)
check('pending-consumed-on-hit',all(s['pending']==0 for s in r['shots']))
check('weak-score-keeps-pending',not read('low')['shots'] and read('low')['pending']==1)
for case in ('unarmed','unarmed-edge','empty','zombie'):
 check(case+'-no-shot',not read(case)['shots'])
check('score-73-armed',read('armed-edge')['shooter']==1 and bool(read('armed-edge')['shots']))
check('score-76-unarmed',read('unarmed-edge')['shooter']==0)
check('fatal-F-keeps-H',read('low-f')['shots'][0]['F']==0 and read('low-f')['shots'][0]['H']==80)
check('fatal-H-keeps-old-H',read('low-h')['shots'][0]['F']==0 and read('low-h')['shots'][0]['H']==6)
(root/'extracted/ranged-stats-rules.json').write_text(json.dumps({'controlled':True,'checks':checks},indent=2))
print(len(checks),'original stat/ranged checks passed')
