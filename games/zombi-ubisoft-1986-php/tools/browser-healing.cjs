// Controlled medkit/gauges; real input, frame timer, shared cooldown and storage.
const puppeteer=require('puppeteer'),assert=require('node:assert/strict');
(async()=>{
 const b=await puppeteer.launch({headless:true,args:['--no-sandbox','--use-gl=swiftshader','--enable-unsafe-swiftshader','--autoplay-policy=no-user-gesture-required']});
 try{
 const p=await b.newPage();await p.setViewport({width:960,height:600});const errors=[];p.on('pageerror',e=>errors.push(e.message));
 p.on('console',m=>{if(/CRASH|BOOT FAILED|Fatal error|Uncaught/.test(m.text()))errors.push(m.text());});
 const wait=ms=>new Promise(r=>setTimeout(r,ms)),key=async k=>{await p.keyboard.down(k);await wait(45);await p.keyboard.up(k);await wait(65);};
 const ready=async()=>{await p.waitForFunction(()=>document.getElementById('loader')?.style.display==='none',{timeout:180000});await p.mouse.click(480,100);};
 const php=code=>p.evaluate(code=>window.JumPHP.php(`$s=$GLOBALS['app']->engine->scenes->current();$w=(new ReflectionProperty($s,'world'))->getValue($s);${code}`),code);
 const state=async()=>{await php(`file_put_contents('/tmp/heal.json',json_encode(['save'=>$w->export(),'mode'=>$w->mode,'pending'=>$w->pending]));`);return p.evaluate(()=>JSON.parse(window.JumPHP.FS.readFile('/tmp/heal.json',{encoding:'utf8'})));};
 await p.goto(process.argv[2],{waitUntil:'load'});await ready();await key('Enter');await wait(300);
 await php('$w->party[3]["held"]=33;$w->party[0]["gauges"]=[40,30,80];');
 await key('u');let s=await state();assert.equal(s.mode,'target');assert.equal(s.pending,'heal');await p.screenshot({path:process.argv[3]});
 await key('1');await key('Enter');s=await state();assert.deepEqual(s.save.party[0].gauges,[50,40,80]);assert.equal(s.save.party[3].held,33);assert.equal(s.save.party[0].state,0);
 await key('u');assert.equal((await state()).mode,'normal');await key('F5');await wait(2300);await p.reload({waitUntil:'load'});await ready();await key('c');await wait(300);
 s=await state();assert.deepEqual(s.save.party[0].gauges,[50,40,80]);assert.ok(s.save.healingTicks>14000);await key('u');assert.equal((await state()).mode,'normal');
 await key('2');await php('$w->party[1]["held"]=33;');await key('u');assert.equal((await state()).mode,'normal');
 await php('$save=$w->export();$save["healingTicks"]=1.0;$w->restore($save);');await wait(150);await key('u');assert.equal((await state()).mode,'target');
 await key('Tab');await key('u');assert.equal((await state()).mode,'normal');
 await php('$save=$w->export();$save["healingTicks"]=0.0;$save["party"][0]["state"]=16;$w->restore($save);');await key('u');await key('1');await key('Enter');s=await state();assert.equal(s.save.party[0].state,16);assert.deepEqual(s.save.party[0].gauges,[50,40,80]);assert.ok(s.save.healingTicks>14000);
 assert.equal(errors.length,0,errors.join('\n'));console.log('Healing browser OK: selector, +10 F/H, retained kit, cooldown shared across survivors, save/reload, expiry, cancellation and rejected sleeping target.');
 }finally{await b.close();}
})().catch(e=>{console.error(e);process.exit(1);});
