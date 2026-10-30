"""Regression evidence from the original; all starts and electrical/enemy overrides are controlled."""
from pathlib import Path
import json
root=Path('/workspace/analysis');checks=[]
def load(name,file='results.json'):
    return json.loads((root/'runs'/name/file).read_text())
def check(name,condition):
    assert condition,name
    checks.append(name)
s=load('lift-36-power-0-passenger','lift-states.json')
check('call-with-power-zero',s[0]['lift_cells']==[58] and s[1]['lift_cells']==[114]
    and s[1]['landing_south']['33']==10 and s[1]['landing_south']['36']==0)
check('passenger-transport',s[0]['positions']==[58,316,316,122] and s[1]['positions']==[114,316,316,122])
for power in (0,1):
    s=load(f'lift-63-power-{power}','lift-states.json')
    check(f'cabin-to-basement-power-{power}',s[0]['lift_cells']==[58] and s[-1]['lift_cells']==[2]
        and s[-1]['positions'][3]==2 and s[-1]['landing_south']['8']==0
        and s[-1]['landing_south']['33']==10 and s[-1]['landing_south']['59']==30)
for room,target in ((35,64),(38,66),(46,65)):
    r=load(f'rope-{room}-1')['results']
    check(f'rope-route-{room}',r[0]['held']==41 and r[-1]['held']==0 and r[-1]['used_item']==0
        and r[-1]['room_id']==target and r[-1]['state']==0)
r=load('rope-38-0')['results']
check('window-without-active-rope',r[0]['state']==0 and r[-1]['state']==255 and r[-1]['room_id']==38)
(root/'extracted/lifts-rope-rules.json').write_text(json.dumps({'controlled':True,'checks':checks},indent=2))
print(len(checks),'original lift/rope checks passed')
