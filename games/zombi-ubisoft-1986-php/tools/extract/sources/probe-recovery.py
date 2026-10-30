"""Controlled recovery assays with explicit low-gauge preconditions."""
import sys
from importlib.machinery import SourceFileLoader
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()

for obj,row,patch,before,after,index in ([] if '--sleep-only' in sys.argv else [(54,0,'66bd=3c',60,62,0),(55,1,'66bc=46',70,74,2),
                                       (54,0,'66bd=45',69,70,0),(55,1,'66bc=4f',79,80,2)]):
    e=runner.Experiment(f'consume-{obj}-{before}',20)
    e.pokes.append('11000:'+patch)
    e.mark('initial')
    e.select(1);e.key('copy');e.cell(row,1);e.key('copy');e.mark('taken')
    e.select(5);e.key('copy');e.key('copy');e.mark('consumed')
    r=e.run()
    assert r[0]['gauges_F_H_S'][index]==before
    assert r[1]['inventory'][0]==obj
    assert r[2]['gauges_F_H_S'][index]==after
    assert r[2]['inventory']==[0]*5

e=runner.Experiment('sleep-34',34,position_start=True)
e.pokes.append('11000:66bc=40')
e.mark('initial')
e.select(6);e.key('copy');e.cell(2,3);e.key('copy');e.mark('sleep-start')
e.key('right');e.key('copy');e.mark('alexandre-active')
e.mark('sleep-middle',10000)
e.mark('sleep-later',20000)
e.mark('before-wake',131900-e.frame)
e.mark('after-wake',132400-e.frame)
e.mark('sleep-end',11100)
r=e.run(watch='66bc-66d2')
assert r[1]['state']==16
states={v['label']:v for v in r}
before=states['before-wake']['party'][3]
after=states['after-wake']['party'][3]
assert before['state']==16 and after['state']==0
assert after['gauges_F_H_S'][2]==min(before['gauges_F_H_S'][2]+8,80)
