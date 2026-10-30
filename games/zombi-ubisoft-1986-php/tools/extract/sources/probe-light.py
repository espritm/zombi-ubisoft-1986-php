"""Controlled lamp assay. The lamp is injected explicitly, not claimed found in play."""
from importlib.machinery import SourceFileLoader
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
from PIL import Image
e=runner.Experiment('light-item-43',2,position_start=True)
e.pokes.append('11000:66c3=2b')
e.mark('dark')
e.select(18);e.key('copy');e.key('copy');e.mark('held-but-dark')
e.select(3);e.key('copy');e.mark('illuminated')
e.select(18);e.key('copy');e.key('copy')
e.select(3);e.key('copy');e.mark('dark-again')
r=e.run()
assert r[1]['held']==43 and r[1]['used_item']==0
assert r[2]['used_item']==43 and r[3]['used_item']==0
out=runner.ROOT/'analysis/runs/light-item-43'
for name,black in [('dark',True),('held-but-dark',True),('illuminated',False),('dark-again',True)]:
    crop=Image.open(out/f'{name}.png').crop((8,48,168,128))
    assert (crop.getextrema()==((0,0),(0,0),(0,0)))==black
