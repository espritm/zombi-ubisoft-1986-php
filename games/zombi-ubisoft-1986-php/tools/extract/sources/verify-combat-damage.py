"""Cross-check native damage and timed-key shots, including the original #8A58 branch."""
from pathlib import Path
import json
root=Path('/workspace/analysis');checks=[]
def check(name,ok):
 assert ok,name
 checks.append(name)
for weapon,bonus in ((0,0),(34,6),(35,6),(36,8),(37,6),(38,10),(39,8)):
 r=json.loads((root/f'runs/melee-damage-{weapon}/melee.json').read_text());b=r['before']
 check(f'weapon-{weapon}-threshold',b['threshold']==9)
 check(f'weapon-{weapon}-bonus',b['bonus']==bonus)
 check(f'weapon-{weapon}-damage',r['damage']==((20-9)//2+70//16+bonus)//2)
cases=((34,0,1),(34,0,26),(34,0,27),(34,0,28),(34,0,29),(34,1,28),(36,0,28),(36,1,28),(35,0,28),(35,1,28),(36,0,26),(34,1,26))
for weapon,trained,cursor in cases:
 s=json.loads((root/f'runs/aim-{weapon}-{trained}-{cursor}/aim-states.json').read_text())[-1]
 width=(80+(20 if weapon==36 else 0)+40*trained+19)//20;folded=cursor if cursor<29 else 57-cursor
 killed=folded<=28-(width+1)//2
 check(f'aim-{weapon}-{trained}-{cursor}-width',s['width']==width)
 check(f'aim-{weapon}-{trained}-{cursor}-health',s['hp']==(0 if killed else 19))
 check(f'aim-{weapon}-{trained}-{cursor}-enemy-count',s['count']==(0 if killed else 1))
 check(f'aim-{weapon}-{trained}-{cursor}-equipment',s['held']==weapon and s['used']==weapon)
for delay in (0,1,2,3,12,24,36,48,60,72,84):
 r=json.loads((root/f'runs/natural-shot-{delay}/shots.json').read_text());s=r['shots'][0]
 folded=s['cursor'] if s['cursor']<29 else 57-s['cursor'];killed=folded<=28-(s['width']+1)//2
 check(f'natural-copy-{delay}-health',r['hp']==(0 if killed else 19))
 check(f'natural-copy-{delay}-count',r['count']==(0 if killed else 1))
check('natural-central-shot-wounds',json.loads((root/'runs/natural-shot-2/shots.json').read_text())['hp']==19)
(root/'extracted/combat-damage-rules.json').write_text(json.dumps({'controlled':True,'checks':checks},indent=2))
print(len(checks),'original combat checks passed')
