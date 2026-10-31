// Controlled party state and shot cursor; real frame updates, keyboard, storage and reload.
const puppeteer=require('puppeteer'),assert=require('node:assert/strict');
(async()=>{
 const b=await puppeteer.launch({headless:true,args:['--no-sandbox','--use-gl=swiftshader','--enable-unsafe-swiftshader','--autoplay-policy=no-user-gesture-required']});
 try{
 const p=await b.newPage();await p.setViewport({width:960,height:600});const errors=[];
 p.on('pageerror',e=>errors.push(e.message));p.on('console',m=>{if(/CRASH|BOOT FAILED|Fatal error|Uncaught/.test(m.text()))errors.push(m.text());});
 const wait=ms=>new Promise(r=>setTimeout(r,ms));
 const key=async k=>{await p.keyboard.down(k);await wait(45);await p.keyboard.up(k);await wait(65);};
 const ready=async()=>{await p.waitForFunction(()=>document.getElementById('loader')?.style.display==='none',{timeout:180000});await p.mouse.click(480,100);};
 const php=code=>p.evaluate(code=>window.JumPHP.php(`$s=$GLOBALS['app']->engine->scenes->current();$w=(new ReflectionProperty($s,'world'))->getValue($s);${code}`),code);
 const state=async()=>{await php(`file_put_contents('/tmp/zparty.json',json_encode(['save'=>$w->export(),'mode'=>$w->mode,'opponent'=>$w->opponent()]));`);return p.evaluate(()=>JSON.parse(window.JumPHP.FS.readFile('/tmp/zparty.json',{encoding:'utf8'})));};
 const shot=async cell=>{await p.evaluate(cell=>{const h=e=>{if(e.key!=='Enter')return;window.removeEventListener('keydown',h,true);window.JumPHP.php(`$s=$GLOBALS['app']->engine->scenes->current();$w=(new ReflectionProperty($s,'world'))->getValue($s);$w->aim=${(cell-2+.1)/56};`);};window.addEventListener('keydown',h,true);},cell);await key('Enter');};
 await p.goto(process.argv[2],{waitUntil:'load'});await ready();await key('Enter');await wait(300);
 const base=Buffer.from(JSON.stringify((await state()).save)).toString('base64');
 await php(`$save=json_decode(base64_decode('${base}'),true);$save['party'][0]['state']=64;$save['party'][0]['gauges'][0]=0;$save['party'][0]['ticks']=29951.0;$save['party'][3]['held']=34;$save['alertTicks']=0.0;if(!$w->restore($save))throw new Exception('Fixture refused');`);
 await wait(200);let s=await state();assert.equal(s.save.party[0].state,128);assert.deepEqual(s.save.party[0].gauges.slice(0,2),[70,50]);assert.equal(s.opponent.person,0);
 await php(`$save=$w->export();$save['party'][0]['gauges']=[50,40,80];$save['alertTicks']=500.0;$w->restore($save);`);
 await wait(400);assert.equal((await state()).mode,'combat');await p.screenshot({path:process.argv[3]});
 await key('u');assert.equal((await state()).mode,'aim');await shot(28);s=await state();assert.deepEqual(s.save.party[0].gauges,[38,28,80]);
 await key('F5');await wait(2300);await p.reload({waitUntil:'load'});await ready();await key('c');await wait(300);
 s=await state();assert.deepEqual(s.save.party[0].gauges,[38,28,80]);assert.equal(s.save.party[0].state,128);
 const room=JSON.stringify(s.save.rooms[62]);await key('u');await shot(2);s=await state();
 assert.deepEqual(s.save.party[0].gauges,[0,28,80]);assert.equal(s.save.party[0].state,255);assert.equal(s.opponent,null);assert.equal(JSON.stringify(s.save.rooms[62]),room);
 await p.screenshot({path:process.argv[3].replace('.png','-dead.png')});
 await key('F5');await wait(2300);await p.reload({waitUntil:'load'});await ready();await key('c');await wait(300);s=await state();assert.equal(s.save.party[0].state,255);assert.equal(s.opponent,null);
 assert.equal(errors.length,0,errors.join('\n'));console.log('Zombie party browser OK: reanimation, automatic combat, portrait, partial shot, wounds saved, permanent death saved, no ordinary corpse.');
 }finally{await b.close();}
})().catch(e=>{console.error(e);process.exit(1);});
