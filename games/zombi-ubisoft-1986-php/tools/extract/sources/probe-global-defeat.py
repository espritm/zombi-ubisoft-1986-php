"""Preset three unavailable survivors, then let the last one's original fight end."""
from importlib.machinery import SourceFileLoader
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
e=runner.Experiment('global-defeat',41,position_start=True)
e.pokes += ['11000:6654=ff','11000:667c=ff','11000:66a4=ff']
e.mark('last-survivor',0)
e.mark('defeat',30000)
r=e.run()
assert r[0]['party'][3]['state']==0
assert r[-1]['program_counter']=='0x851f'
