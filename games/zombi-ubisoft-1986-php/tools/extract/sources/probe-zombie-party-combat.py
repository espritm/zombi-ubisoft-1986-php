from importlib.machinery import SourceFileLoader
import sys
r=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
case=sys.argv[1] if len(sys.argv)>1 else 'low'
e=r.Experiment('zombie-party-'+case,62,position_start=True)
# Move Alexandre into Patrick's room and set the documented zombie state.
e.pokes += ['10050:6654=80','10050:6645='+('05' if case=='low' else '32'),'10050:6646=28']
if case=='other': e.pokes += ['10050:6633=b1','10050:6634=67']
if case=='vehicle': e.pokes += ['10050:6653=01']
e.mark('before',0)
if case=='low':
 for f in range(11400,14500,4):e.events += [f'{f}:'+('right+' if ((f-11400)//4)%2==0 else 'left+'),f'{f+2}:'+('right-' if ((f-11400)//4)%2==0 else 'left-')]
e.mark('after',4500)
e.run(watch='6645-665a')
