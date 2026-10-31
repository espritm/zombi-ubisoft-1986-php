from importlib.machinery import SourceFileLoader
import sys
r=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
item=int(sys.argv[1]);e=r.Experiment('deprivation-consume-'+str(item),62,position_start=True)
e.pokes += [f'11000:66c3={item:02x}','11000:66c8=06','11000:66c9=14','11000:66bc=32','11000:66bd=32']
e.mark('before',0);e.select(5);e.key('copy');e.key('copy');e.mark('after',100);e.run(watch='66bc-66d2')
from cpc import Dump
out=r.ROOT/f'analysis/runs/{e.name}';d=Dump(out/f'dump-{e.marks[-1][0]:05d}.bin');(out/'consumption.json').write_text(r.json.dumps({'F':d.mem[0x66bd],'S':d.mem[0x66bc],'food':d.mem[0x66c8],'awake':d.mem[0x66c9],'bag':list(d.mem[0x66c3:0x66c7])},indent=2))
