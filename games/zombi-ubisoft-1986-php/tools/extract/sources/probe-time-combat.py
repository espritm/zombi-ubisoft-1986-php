"""Original idle and attack observations, without patched health or enemies."""
import sys,json
sys.path.insert(0,'/workspace/analysis')
from importlib.machinery import SourceFileLoader
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()

if '--combat-only' not in sys.argv:
    e=runner.Experiment('idle-gauges',38)
    e.mark('initial')
    e.mark('after_30000',30000)
    e.mark('after_60000',30000)
    e.mark('after_90000',30000)
    e.run(watch='66bc-66c1')

e=runner.Experiment('combat-idle',41,position_start=True)
e.mark('initial',0)
for i in range(1,7): e.mark(f'attack_{i}',1500)
e.mark('before-switch',40000-e.frame)
e.key('right');e.key('copy');e.mark('alexandre-active')
e.mark('after_40000_more',40000)
r=e.run(watch='66bc-66c1')
assert r[-3]['party'][3]['state']==64
assert r[-2]['active_character']=='0x6633'
assert r[-1]['party'][3]['state']==128
