"""Native firearm target selector with controlled party state; damage routines untouched."""
from importlib.machinery import SourceFileLoader
import sys
r=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
case=sys.argv[1];e=r.Experiment('target-'+case,62,position_start=True)
state={'living':0,'sleep':16,'dead':64,'zombie':128,'gone':255}.get(case,128)
e.pokes += [f'10050:6654={state:02x}','10050:6645=32','10050:6646=28','11000:66c7=22']
if case=='other':e.pokes+=['10050:6633=b1','10050:6634=67']
if case=='vehicle':e.pokes+=['10050:6653=01']
for f in range(10050,12100,300):e.pokes += [f'{f}:6717=00',f'{f}:6718=00']
e.pc_pokes += ['8a04:8a63=1c']
e.mark('before',0);e.select(3);e.key('copy')
if case!='self':e.key('right')
e.key('copy');e.mark('selected',100);e.key('copy');e.mark('after',20)
e.run(watch='6633-66d2')
