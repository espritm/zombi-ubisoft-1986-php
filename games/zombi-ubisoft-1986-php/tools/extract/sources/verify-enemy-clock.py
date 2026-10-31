"""Verify native observed moves, entrance controls, quota growth and room caps."""
from pathlib import Path
import json,re
root=Path('/workspace/analysis');world=json.loads((root/'extracted/world.json').read_text())
positions={r['id']:int(r['map_address'],16) for r in world['rooms']};checks=[]
def check(label,ok):
    assert ok,label
    checks.append(label)
def states(case):return json.loads((root/f'runs/enemy-{case}/enemy-states.json').read_text())
baseline=states('clock')
check('four-periodic-updates-with-one-saturated-entrance',baseline[-1]['target']==45 and baseline[0]['target']==34)
check('periodic-timer-below-15104',all(0<=s['timer']<15104 for s in baseline))
for case in ('blocked','closed','full'):
    before,after=states(case)
    expected=3 if case=='closed' else 0
    check(case+'-quota',after['target']-before['target']==expected)
    check(case+'-enemy-conservation',sum(r[6] for r in after['rooms'].values())-sum(r[6] for r in before['rooms'].values())==expected)
    if case=='full':check('entrance-cap-five',all(after['rooms'][str(i)][6]==5 for i in (19,31,32)))
for case in ('clock','blocked','closed','full'):
    s=states(case)[0];pending=None;moves=0;spawns=0
    for line in (root/f'runs/enemy-{case}/log.txt').read_text().splitlines():
        m=re.fullmatch(r'write ([0-9a-f]+)=([0-9a-f]+) pc=([0-9a-f]+) f=(\d+)',line)
        if not m or int(m[4])<=11200:continue
        addr,value,pc=(int(m[i],16) for i in (1,2,3));offset=addr-0x6876-6
        if pc not in (0x7e64,0x7e67,0x7e99):continue
        assert offset%50==0
        room=offset//50+1
        if pc==0x7e64:
            assert 1<=room<=62 and value<=5
            pending=room
        elif pc==0x7e67:
            assert pending is not None and 1<=room<=62
            delta=positions[pending]-positions[room];field=(8,1,-8,-1,-56,56).index(delta)
            assert s['rooms'][str(room)][field] in (0,20)
            pending=None;moves+=1
        else:
            assert room in (19,31,32) and value<=5
            spawns+=1
    check(case+'-valid-native-moves',moves>0 and pending is None)
    check(case+'-spawn-count',spawns==({'clock':11,'closed':3}.get(case,0)))
(root/'extracted/enemy-clock-rules.json').write_text(json.dumps({'controlled':True,'checks':checks},indent=2))
print(len(checks),'original enemy-clock checks passed')
