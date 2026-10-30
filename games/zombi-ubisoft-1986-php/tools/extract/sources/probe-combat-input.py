"""Test the alternating left/right combat inputs read by #8707 and #871B."""
import sys
from importlib.machinery import SourceFileLoader
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
e=runner.Experiment('combat-alternating',41,position_start=True)
e.mark('initial',0)
for stage in range(3):
    for i in range(400):
        name='right' if i%2==0 else 'left'
        e.events += [f'{e.frame}:{name}+',f'{e.frame+2}:{name}-']
        e.frame+=4
    e.mark(f'after_inputs_{stage+1}')
r=e.run(watch='6700-6705')
print('Enemy counts:',[v['room_record'][6] for v in r])
assert r[0]['room_record'][6]==1
assert r[-1]['room_record'][6]==0 and r[-1]['combat_bytes'][12]==0
assert r[-1]['party'][3]['state']==0
