"""Prepare deprivation counters and one real CPC periodic pass, or a sleep wake boundary."""
from importlib.machinery import SourceFileLoader
import sys
r=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
case=sys.argv[1];e=r.Experiment('deprivation-'+case,62,position_start=True)
food=int(case[4:]) if case.startswith('food') else (5 if case.startswith('wake') else 0)
awake=int(case[5:]) if case.startswith('awake') else (26 if case in ('forced-sleep','forced-dead','forced-gone','repeat','wake-period') else (20 if case.startswith('wake') else 0))
state={'forced-sleep':16,'forced-dead':64,'forced-gone':255,'wake':16,'wake-cap':16}.get(case,16 if case.startswith('wake') else 0)
s=3 if case in ('starve','combined-ill','combined-corpse') else (50 if case in ('wake','wake-period') else (77 if case=='wake-cap' else 80))
if case in ('starve','combined-ill','combined-corpse'):food=6
ill=1 if case=='combined-ill' else 0
e.pokes += [f'11000:6650={food:02x}',f'11000:6651={awake:02x}',f'11000:6654={state:02x}',f'11000:6644={s:02x}',f'11000:6655={ill:02x}']
if case=='combined-corpse':e.pokes+=['11000:7468=01']
for t in range(10050,12500,300):e.pokes += [f'{t}:6717=00',f'{t}:6718=00']
e.mark('before',0)
if case=='wake-period':e.pokes += [f'{e.frame}:6720=ff',f'{e.frame}:6721=3a']
if case.startswith('wake'):
 e.pokes += [f'{e.frame}:6656=ff',f'{e.frame}:6657=d3',f'{e.frame}:6658=01'];e.mark('after',250)
else:
 e.pokes += [f'{e.frame}:6720=ff',f'{e.frame}:6721=3a'];e.mark('after',250)
if case=='repeat':
 e.pokes += [f'{e.frame}:6720=ff',f'{e.frame}:6721=3a'];e.mark('again',250)
e.run(watch='6644-665a')
from cpc import Dump
out=r.ROOT/f'analysis/runs/{e.name}';vals=[]
for f,label in e.marks:
 d=Dump(out/f'dump-{f:05d}.bin');vals.append({'label':label,'S':d.mem[0x6644],'state':d.mem[0x6654],'food':d.mem[0x6650],'awake':d.mem[0x6651],'sleepTicks':int.from_bytes(d.mem[0x6656:0x6659],'little')})
(out/'deprivation.json').write_text(r.json.dumps(vals,indent=2))



