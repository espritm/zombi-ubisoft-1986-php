"""Reusable original-game experiments; all additional controlled pokes are recorded."""
from pathlib import Path
import json, subprocess, sys
sys.path.insert(0,'/workspace/dsk2jumphp/tools')
from cpc import Dump
ROOT=Path('/workspace')

class Experiment:
    def __init__(self,name,room,frames=0,lighting=False,position_start=False):
        self.name,self.room,self.lighting=name,room,lighting
        self.events=['8200:return+','8220:return-','8300:enter+','8320:enter-']
        for f in range(8600,9500,100): self.events += [f'{f}:right+',f'{f+8}:right-']
        self.events += ['9700:copy+','9708:copy-','10000:copy+','10008:copy-']
        self.frame=11200;self.action=10;self.row=1;self.col=1;self.marks=[]
        self.pokes=[]
        self.pc_pokes=[]
        self.position_start=position_start
    def key(self,name):
        self.events += [f'{self.frame}:{name}+',f'{self.frame+8}:{name}-']
        self.frame+=100
    def select(self,action):
        delta=(action-self.action)%18
        if delta<=9:
            for _ in range(delta): self.key('left')
        else:
            for _ in range(18-delta): self.key('right')
        self.action=action
    def cell(self,row,col):
        for _ in range(abs(row-self.row)): self.key('down' if row>self.row else 'up')
        for _ in range(abs(col-self.col)): self.key('right' if col>self.col else 'left')
        self.row,self.col=row,col
    def mark(self,label,delay=250):
        self.frame+=delay;self.marks.append((self.frame,label));self.frame+=100
    def run(self,watch=None):
        out=ROOT/f'analysis/runs/{self.name}';out.mkdir(exist_ok=True,parents=True)
        frames=','.join(str(f) for f,_ in self.marks)
        cmd=[str(ROOT/'dsk2jumphp/emulator/cpc'),str(ROOT/'Zombi__FRENCH__Side_A.dsk'),str(out),str(self.frame+100),
             '-rom',str(ROOT/'ROM CPC 6128 OS + BASIC et AMSDOS/CPC6128.ROM'),
             '-urom','7:'+str(ROOT/'ROM CPC 6128 OS + BASIC et AMSDOS/amsdos.rom'),
             '-type','150:RUN3WO*BI\\n','-swap','8100:'+str(ROOT/'Zombi__FRENCH__Side_B.dsk'),
             '-k',','.join(self.events),'-s',frames,'-d',frames]
        if self.position_start:
            world=json.loads((ROOT/'analysis/extracted/world.json').read_text())
            pos=int(next(r['map_address'] for r in world['rooms'] if r['id']==self.room),16)
            cmd+=['-F',f'8500:66ab={pos&255:02x}','-F',f'8500:66ac={pos>>8:02x}']
        else:
            cmd+=['-F',f'8500:6862={self.room:02x}']
        if self.lighting:cmd+=['-F','8500:6712=01']
        for poke in self.pokes:cmd+=['-F',poke]
        for poke in self.pc_pokes:cmd+=['-p',poke]
        if watch:cmd+=['-w',watch]
        subprocess.run(cmd,check=True)
        results=[]
        for frame,label in self.marks:
            d=Dump(out/f'dump-{frame:05d}.bin');d.screen().save(out/f'{label}.png')
            word=lambda a:int.from_bytes(d.mem[a:a+2],'little')
            cp,rp=word(0x6615),word(0x6617)
            result={'label':label,'frame':frame,'active_character':hex(cp),'action':d.mem[0x6601],
                    'program_counter':hex(d.pc),
                    'inventory':list(d.mem[cp+24:cp+29]),'held':d.mem[cp+28],
                    'used_item':d.mem[cp+39],'gauges_F_H_S':list(d.mem[cp+18:cp+20])+[d.mem[cp+17]],
                    'training_bonus':d.mem[cp+38],
                    'fuel_can':d.mem[0x6710],'fuel_tank':d.mem[0x6711],
                    'quest_storage':list(d.mem[0x66e8:0x66f0]),
                    'container_records':list(d.mem[0x66d3:0x66fd]),
                    'max_gauges_F_H_S':list(d.mem[cp+21:cp+23])+[d.mem[cp+20]],
                    'state':d.mem[cp+33],'state_aux':d.mem[cp+32],
                    'room_id':d.mem[word(cp)],'room_record':list(d.mem[rp:rp+50]),
                    'electricity_flag':d.mem[0x6712],'clock_bytes':list(d.mem[0x661e:0x6620]),
                    'combat_bytes':list(d.mem[0x6700:0x6714]),
                    'party':[{'name':d.mem[a+2:a+11].decode('ascii').strip(),
                              'position':hex(word(a)), 'room_id':d.mem[word(a)],
                              'gauges_F_H_S':[d.mem[a+18],d.mem[a+19],d.mem[a+17]],
                              'state':d.mem[a+33],'state_aux':d.mem[a+32],
                              'timer':list(d.mem[a+35:a+38])} for a in range(0x6633,0x66d3,40)]}
            results.append(result)
        report={'name':self.name,'controlled_start_room':self.room,'lighting_override':self.lighting,
                'position_start':self.position_start,'additional_pokes':self.pokes,
                'pc_pokes':self.pc_pokes,'keys':','.join(self.events),'results':results}
        (out/'results.json').write_text(json.dumps(report,indent=2))
        print(json.dumps({'scenario':self.name,'states':[{k:v for k,v in r.items() if k!='room_record'} for r in results]},indent=2))
        return results

if __name__=='__main__':
    e=Experiment('equipment-38',38)
    e.mark('initial')
    e.select(1);e.key('copy');e.cell(3,6);e.key('copy');e.mark('taken')
    e.select(18);e.key('copy');e.key('copy');e.mark('equipped')
    e.select(3);e.key('copy');e.mark('used')
    e.select(18);e.key('copy');e.key('copy');e.mark('unequipped')
    e.select(3);e.key('copy');e.mark('deactivated')
    r=e.run()
    assert r[1]['inventory']==[38,0,0,0,0]
    assert r[2]['inventory']==[0,0,0,0,38]
    assert r[3]['used_item']==38
    assert r[4]['inventory']==[38,0,0,0,0]
    assert r[5]['used_item']==0

    e=Experiment('door-38',38)
    e.mark('initial')
    e.select(8);e.key('copy');e.cell(0,8);e.key('copy');e.mark('opened')
    e.select(9);e.key('copy');e.key('copy');e.mark('closed')
    r=e.run()
    assert [v['room_record'][18] for v in r]==[8,21,8]
    assert [v['room_record'][1] for v in r]==[240,250,240]
