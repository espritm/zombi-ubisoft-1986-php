"""Controlled held firearm and cursor at the native shot calculation; damage code is untouched."""
from importlib.machinery import SourceFileLoader
import json,sys
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
from cpc import Dump
weapon,trained,cursor=map(int,sys.argv[1:]);e=runner.Experiment(f'aim-{weapon}-{trained}-{cursor}',41,position_start=True)
e.pokes += [f'11000:66c7={weapon:02x}',f'11000:66d1={trained:02x}','8500:704c=00']
e.pc_pokes += ['949e:704c=01','949e:670c=1f','949e:670d=1b',f'8a04:8a63={cursor:02x}']
e.mark('before',0);e.select(3);e.key('copy');e.mark('aiming',150);e.key('copy');e.mark('after',100)
e.run(watch='670c-670d');out=runner.ROOT/f'analysis/runs/{e.name}';states=[]
for frame,label in e.marks:
 d=Dump(out/f'dump-{frame:05d}.bin');states.append({'label':label,'hp':d.mem[0x670c],'strength':d.mem[0x670d],
 'count':d.mem[0x704c],'cursor':d.mem[0x8a63],'width':d.mem[0x8a64],'F':d.mem[0x66bd],'H':d.mem[0x66be],
 'held':d.mem[0x66c7],'used':d.mem[0x66d2],'pc':d.pc})
(out/'aim-states.json').write_text(json.dumps(states,indent=2));print(e.name,states[-1])
