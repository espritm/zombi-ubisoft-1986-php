"""Controlled original lamp holder fixture; no electricity or active-character lamp is injected."""
from pathlib import Path
from importlib.machinery import SourceFileLoader
import json
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
from cpc import Dump
from PIL import Image
root=Path('/workspace');world=json.loads((root/'analysis/extracted/world.json').read_text())
pos=lambda room:int(next(r['map_address'] for r in world['rooms'] if r['id']==room),16)
e=runner.Experiment('shared-light',2,position_start=True)
def press(name):
    e.events += [f'{e.frame}:{name}+',f'{e.frame+24}:{name}-'];e.frame+=220
e.key=press
e.mark('alone-dark')
for room,label in ((2,'shared-lit'),(62,'separated-dark')):
    p=pos(room);e.pokes += [f'{e.frame}:6633={p&255:02x}',f'{e.frame}:6634={p>>8:02x}',f'{e.frame}:665a=2b']
    e.select(10);e.key('copy');e.key('copy');e.mark(label,500)
e.run()
out=root/'analysis/runs/shared-light'
for label,black in (('alone-dark',True),('shared-lit',False),('separated-dark',True)):
    crop=Image.open(out/f'{label}.png').crop((8,48,168,128))
    assert (crop.getextrema()==((0,0),(0,0),(0,0)))==black,label
print('Original shared lighting: same room lights, separated room stays dark')
