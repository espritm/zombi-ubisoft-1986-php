from importlib.machinery import SourceFileLoader
import sys
r=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
cell=int(sys.argv[1]);e=r.Experiment('zombie-party-shot-'+str(cell),62,position_start=True)
e.pokes += ['10050:6654=80','10050:6645=32','10050:6646=28','11000:66c7=22']
for f in range(10050,12100,300):e.pokes += [f'{f}:6717=00',f'{f}:6718=00']
e.pc_pokes += [f'8a04:8a63={cell:02x}']
e.mark('before',0);e.select(3);e.key('copy');e.key('right');e.key('copy');e.mark('aiming',100);e.key('copy');e.mark('after',20)
e.run(watch='6645-665a')


