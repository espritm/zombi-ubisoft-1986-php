from pathlib import Path
import json
root=Path('/workspace/analysis');checks=[]
def ck(k,v):
 assert v,k
 checks.append(k)
cases='food5 food6 food7 food254 food255 awake17 awake18 awake19 awake20 awake21 awake22 awake25 awake26 awake27 awake254 awake255 starve combined-ill combined-corpse forced-sleep forced-dead forced-gone repeat wake wake-cap'.split()
for c in cases:
 vals=json.loads((root/f'runs/deprivation-{c}/deprivation.json').read_text());x=vals[1];food=1;awake=1;s=80;st=0
 if c.startswith('food'):food=(int(c[4:])+1)&255;s=76 if food>=7 else 80
 elif c.startswith('awake'):
  awake=(int(c[5:])+1)&255
  if awake>=27:st=16
  elif awake>=19:s-=4 if awake<=20 else (8 if awake==21 else 12)
 elif c in ('starve','combined-ill','combined-corpse'):
  food=7;s,st={'starve':(255,255),'combined-ill':(0,64),'combined-corpse':(251,255)}[c]
 elif c.startswith('forced') or c=='repeat':awake=27;st=16
 elif c.startswith('wake'):food=5;awake=0;s=58 if c=='wake' else 80
 for k,v in [('S',s),('state',st),('food',food),('awake',awake)]:ck(c+' '+k,x[k]==v)
 if st==16:ck(c+' timer restarted',x['sleepTicks']<500)
 if c=='repeat':
  y=vals[2]
  for k,v in [('S',80),('state',16),('food',2),('awake',28)]:ck('repeat next '+k,y[k]==v)
  ck('repeat timer reset again',y['sleepTicks']<500)
for item in (54,55):
 x=json.loads((root/f'runs/deprivation-consume-{item}/consumption.json').read_text())
 for k,v in [('F',52 if item==54 else 50),('S',50 if item==54 else 54),('food',6 if item==54 else 0),('awake',20),('bag',[0,0,0,0])]:ck(str(item)+' '+k,x[k]==v)
x=json.loads((root/'runs/deprivation-wake-period/deprivation.json').read_text())[1]
for k,v in [('S',58),('state',0),('food',6),('awake',1)]:ck('wake before period '+k,x[k]==v)
(root/'extracted/deprivation-rules.json').write_text(json.dumps({'checks':checks},indent=2));print(str(len(checks))+' native deprivation checks passed')

