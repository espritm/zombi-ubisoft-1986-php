"""Isolate one native periodic health pass by preparing its timer near the threshold."""
from importlib.machinery import SourceFileLoader
import sys
r=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
case=sys.argv[1];e=r.Experiment('illness-'+case,62,position_start=True)
state={'sleep':16,'dead':64,'zombie':128,'gone':255}.get(case,0)
s=10 if case=='zero' else (9 if case in ('under','illcorpse') else (3 if case=='corpse-low' else 80))
ill=2 if case in ('ill','zero','under','sleep','dead','zombie','gone','illcorpse','busy') else 0
e.pokes += [f'11000:6654={state:02x}',f'11000:6644={s:02x}',f'11000:6655={ill:02x}','11000:664a=ff']
if case in ('exposure-low','exposure-high'):e.pokes += ['11000:8af0=3e',f'11000:8af1={0 if case=="exposure-low" else 63:02x}']
if case.startswith('corpse') or case=='illcorpse':e.pokes+=['11000:7468=01']
if case.startswith('marker'):e.pokes += [f'11000:746a={int(case[6:]):02x}']
if case=='elsewhere':e.pokes+=['11000:704e=01']
for t in range(10050,11700,300):e.pokes += [f'{t}:6717=00',f'{t}:6718=00']
if case=='busy':e.pokes+=['11000:66c3=22']
e.mark('before',0)
if case=='busy':e.select(18);e.key('copy')
e.pokes += [f'{e.frame}:6720=ff',f'{e.frame}:6721=3a'];e.mark('after',250)
if case=='busy':e.key('copy');e.mark('released',250)
e.run(watch='6644-665a')
from cpc import Dump
out=r.ROOT/f'analysis/runs/{e.name}';vals=[]
for f,label in e.marks:
 d=Dump(out/f'dump-{f:05d}.bin');vals.append({'label':label,'S':d.mem[0x6644],'state':d.mem[0x6654],'exposure':d.mem[0x664a],'illness':d.mem[0x6655],'healthTimer':int.from_bytes(d.mem[0x6720:0x6722],'little')})
(out/'health.json').write_text(r.json.dumps(vals,indent=2))

