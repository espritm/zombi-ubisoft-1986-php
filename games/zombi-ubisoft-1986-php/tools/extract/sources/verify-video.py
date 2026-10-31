"""Check actual original tape insertion, preconditions, training and clock jump."""
from pathlib import Path
import json
root=Path('/workspace/analysis');checks=[]
def check(name,ok):
    assert ok,name
    checks.append(name)
def states(tape,tv):
    path=root/f'runs/video-tape-{tape}-tv-{tv}'
    return {r['label']:r for r in json.loads((path/'video-states.json').read_text())}
on=states(1,1)
check('off-player-refuses-tape',on['insert-off-refused']['tape']==0)
check('original-insertion-stores-tape',on['tape-inserted']['tape']==50)
check('all-present-trained',on['after']['trained']==[1,0,0,1])
check('session-adds-eighty-minutes',80<=on['after']['minutes']-on['play']['minutes']<=88)
check('tape-remains-in-player',on['after']['tape']==50)
for tape,tv in ((1,0),(0,1)):
    s=states(tape,tv)
    check(f'refused-{tape}-{tv}',s['after']['trained']==[0,0,0,0]
        and s['after']['minutes']-s['play']['minutes']<10)
(root/'extracted/video-rules.json').write_text(json.dumps({'controlled':True,'checks':checks},indent=2))
print(len(checks),'original video checks passed')
