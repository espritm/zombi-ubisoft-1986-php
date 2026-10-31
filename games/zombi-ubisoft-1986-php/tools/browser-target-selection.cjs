// Controlled party/equipment fixtures and one shot cursor; selection uses real input.
const puppeteer=require('puppeteer'),assert=require('node:assert/strict');
(async()=>{
 const b=await puppeteer.launch({headless:true,args:['--no-sandbox','--use-gl=swiftshader','--enable-unsafe-swiftshader','--autoplay-policy=no-user-gesture-required']});
 try{
 const p=await b.newPage();await p.setViewport({width:960,height:600});const errors=[];
 p.on('pageerror',e=>errors.push(e.message));p.on('console',m=>{if(/CRASH|BOOT FAILED|Fatal error|Uncaught/.test(m.text()))errors.push(m.text());});
 const wait=ms=>new Promise(r=>setTimeout(r,ms)),key=async k=>{await p.keyboard.down(k);await wait(45);await p.keyboard.up(k);await wait(65);};
 const ready=async()=>{await p.waitForFunction(()=>document.getElementById('loader')?.style.display==='none',{timeout:180000});await p.mouse.click(480,100);};
 const php=code=>p.evaluate(code=>window.JumPHP.php(`$s=$GLOBALS['app']->engine->scenes->current();$w=(new ReflectionProperty($s,'world'))->getValue($s);${code}`),code);
 const state=async()=>{await php(`file_put_contents('/tmp/target.json',json_encode(['save'=>$w->export(),'mode'=>$w->mode,'choice'=>$w->choice,'opponent'=>$w->opponent()]));`);return p.evaluate(()=>JSON.parse(window.JumPHP.FS.readFile('/tmp/target.json',{encoding:'utf8'})));};
 await p.goto(process.argv[2],{waitUntil:'load'});await ready();await key('Enter');await wait(300);
 const base=Buffer.from(JSON.stringify((await state()).save)).toString('base64');
 const setup=extra=>php(`$save=json_decode(base64_decode('${base}'),true);$save['party'][3]['held']=34;$save['alertTicks']=0.0;$w->restore($save);${extra||''}`);
 await setup();await key('u');let s=await state();assert.equal(s.mode,'target');assert.equal(s.choice,3);await key('ArrowRight');assert.equal((await state()).choice,0);await p.screenshot({path:process.argv[3]});
 await key('Tab');s=await state();assert.equal(s.mode,'normal');assert.equal(s.save.party[0].state,0);
 await key('u');await p.mouse.click(120,174);await wait(200);s=await state();assert.equal(s.save.party[0].state,255);assert.deepEqual(s.save.party[0].gauges,[70,70,80]);assert.equal(s.save.active,3);
 await setup('$w->party[0]["state"]=128;$w->party[1]["state"]=128;$w->party[1]["gauges"]=[50,40,80];');
 await key('u');await key('2');assert.equal((await state()).save.active,3);await key('Enter');assert.equal((await state()).opponent.person,1);
 await p.evaluate(()=>{const h=e=>{if(e.key!=='Enter')return;window.removeEventListener('keydown',h,true);window.JumPHP.php(`$s=$GLOBALS['app']->engine->scenes->current();$w=(new ReflectionProperty($s,'world'))->getValue($s);$w->aim=26.1/56;`);};window.addEventListener('keydown',h,true);});
 await key('Enter');s=await state();assert.deepEqual(s.save.party[1].gauges,[38,28,80]);assert.equal(s.save.party[0].gauges[0],70);
 await key('F5');await wait(2300);await p.reload({waitUntil:'load'});await ready();await key('c');await wait(300);s=await state();assert.deepEqual(s.save.party[1].gauges,[38,28,80]);assert.equal(s.opponent.person,0);
 await setup();await key('u');await key('4');await key('Enter');s=await state();assert.equal(s.save.party[3].state,255);assert.equal(s.mode,'character');await key('2');assert.equal((await state()).save.active,1);
 assert.equal(errors.length,0,errors.join('\n'));console.log('Target browser OK: keyboard cycling, mouse selection, cancel, allied elimination, second zombie chosen without switching actor, partial damage, reload clears target, self target and survivor switch.');
 }finally{await b.close();}
})().catch(e=>{console.error(e);process.exit(1);});
