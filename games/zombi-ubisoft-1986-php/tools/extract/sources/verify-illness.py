from pathlib import Path
import json
root=Path('/workspace/analysis');checks=[]
def ck(k,v):
 assert v,k
 checks.append(k)
cases=['healthy','ill','zero','under','sleep','dead','zombie','gone','exposure-low','exposure-high','corpse','corpse-low','illcorpse','marker29','marker30','marker31','marker32','elsewhere']
for c in cases:
 x=json.loads((root/f'runs/illness-{c}/health.json').read_text())[-1]
 s=70 if c in ('ill','sleep') else (0 if c in ('zero','under') else (76 if c=='corpse' or c.startswith('marker') else (255 if c=='corpse-low' else (252 if c=='illcorpse' else 80))))
 st={'sleep':16,'dead':64,'zombie':128,'gone':255,'under':64,'corpse-low':255,'illcorpse':255}.get(c,0)
 ck(c+' S',x['S']==s);ck(c+' state',x['state']==st);ck(c+' exposure retained',x['exposure']==255)
 ck(c+' no new illness',x['illness']==(2 if c in ('ill','zero','under','sleep','dead','zombie','gone','illcorpse') else 0))
for c,expected in {'zombie':(67,77,6),'raider':(67,77,250),'party':(67,77,6),'wrap':(67,77,0),'fatal-f':(0,80,250),'fatal-h':(0,2,250)}.items():
 x=json.loads((root/f'runs/exposure-{c}/exposure.json').read_text())
 for k,v in zip(('F','H','exposure'),expected):ck(c+' '+k,x[k]==v)
x=json.loads((root/'runs/illness-busy/health.json').read_text());ck('busy defers damage',x[1]['S']==80);ck('busy timer still advances',x[1]['healthTimer']>=15104);ck('released damage',x[2]['S']==70)
(root/'extracted/illness-rules.json').write_text(json.dumps({'checks':checks},indent=2));print(str(len(checks))+' native illness/exposure checks passed')
