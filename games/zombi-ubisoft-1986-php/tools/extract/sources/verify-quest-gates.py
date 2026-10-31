"""Verify controlled original CPC quest gates; this is not a native full walkthrough."""
from pathlib import Path
import json
root=Path('/workspace/analysis');checks=[]
def states(case):
    return {s['label']:s for s in json.loads((root/f'runs/quest-{case}/quest-states.json').read_text())}
def check(name,ok):
    assert ok,name
    checks.append(name)
r=states('ready');before=r['initial'];after=r['deposited']
check('initial-quota-34',before['target']==34 and before['frozen']==33 and before['phase']==1)
check('last-body-triggers-arrival',after['frozen']==34 and after['phase']==0)
check('new-truck-fuel-cap',before['rooms']['78'][27]==255 and after['rooms']['78'][27]==5 and after['rooms']['78'][9]==78)
check('raiders-occupy-new-truck',after['rooms']['78'][6]==2)
check('middle-entrance-reopens',after['rooms']['65'][2]==20 and after['rooms']['65'][9]==0 and after['rooms']['19'][0]==20)
check('old-truck-returns',after['rooms']['77'][9]==77 and after['rooms']['77'][6]==1)
for case in ('short','unparked'):
    s=states(case)['deposited']
    check(case+'-refuses-arrival',s['phase']==1 and s['rooms']['78'][27]==255)
check('key-opens-top-landing',states('unlock1')['opened']['rooms']['59'][2]==20)
check('no-key-refuses-top-landing',states('unlock0')['opened']['rooms']['59'][2]==30)
(root/'extracted/quest-gates.json').write_text(json.dumps({'controlled':True,'checks':checks},indent=2))
print(len(checks),'original quest gate checks passed')
