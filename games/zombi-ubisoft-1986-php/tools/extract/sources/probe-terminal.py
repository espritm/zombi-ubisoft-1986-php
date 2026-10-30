"""Type commands into the original terminal; no command result or training flags patched."""
from importlib.machinery import SourceFileLoader
from pathlib import Path
import json,sys
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
root=Path('/workspace');world=json.loads((root/'analysis/extracted/world.json').read_text())
command=sys.argv[1].replace('_',' ') if len(sys.argv)>1 else 'LIST'
e=runner.Experiment('terminal-'+command.replace(' ','-').lower(),39,position_start=True)
def key(name):
    e.events += [f'{e.frame}:{name}+',f'{e.frame+24}:{name}-'];e.frame+=220
e.key=key
e.pokes += [f'8500:{0x6876+38*50+6:04x}=00']
room=next(r for r in world['rooms'] if r['id']==39)
row,col=next((y,x) for y,line in enumerate(room['objects_grid']) for x,v in enumerate(line) if v==75)
e.mark('initial');e.select(4);e.key('copy');e.cell(row,col);e.key('copy');e.mark('login',500)
e.key('copy');e.mark('ready');e.key('copy');e.mark('prompt')
typing=f'{e.frame}:{command}\\n';e.frame+=len(command)*6+500;e.mark('response',500)
if command=='LIST':e.key('copy');e.mark('list-second')
e.key('copy');e.key('copy');e.mark('after-acknowledge')
run=runner.subprocess.run
runner.subprocess.run=lambda cmd,**kwargs:run(cmd+['-type',typing],**kwargs)
e.run(watch='66d1')
