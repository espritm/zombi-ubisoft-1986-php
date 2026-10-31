// Controlled counters/timers and food; actual frames, selectors, recovery and storage.
const puppeteer=require('puppeteer'),assert=require('node:assert/strict');
(async()=>{
 const b=await puppeteer.launch({headless:true,args:['--no-sandbox','--use-gl=swiftshader','--enable-unsafe-swiftshader','--autoplay-policy=no-user-gesture-required']});
 try{
 const p=await b.newPage();await p.setViewport({width:960,height:600});const errors=[];p.on('pageerror',e=>errors.push(e.message));p.on('console',m=>{if(/CRASH|BOOT FAILED|Fatal error|Uncaught/.test(m.text()))errors.push(m.text());});
 const wait=ms=>new Promise(r=>setTimeout(r,ms)),key=async k=>{await p.keyboard.down(k);await wait(45);await p.keyboard.up(k);await wait(65);};
 const ready=async()=>{await p.waitForFunction(()=>document.getElementById('loader')?.style.display==='none',{timeout:180000});await p.mouse.click(480,100);};
 const php=code=>p.evaluate(code=>window.JumPHP.php(`$s=$GLOBALS['app']->engine->scenes->current();$w=(new ReflectionProperty($s,'world'))->getValue($s);${code}`),code);
 const read=async expr=>{await php(`file_put_contents('/tmp/deprivation.json',json_encode(${expr}));`);return p.evaluate(()=>JSON.parse(window.JumPHP.FS.readFile('/tmp/deprivation.json',{encoding:'utf8'})));};
 const state=()=>read("['save'=>$w->export(),'mode'=>$w->mode]");
 await p.goto(process.argv[2],{waitUntil:'load'});await ready();await key('Enter');await wait(300);
 const base=Buffer.from(JSON.stringify((await state()).save)).toString('base64');
 const setup=extra=>php(`$save=json_decode(base64_decode('${base}'),true);${extra}if(!$w->restore($save))throw new Exception('Fixture refused');`);
 await setup("$save['party'][0]['foodHours']=6;$save['party'][0]['awakeHours']=21;$save['healthTicks']=15000.0;");await key('i');await wait(2400);let s=await state();assert.equal(s.save.party[0].gauges[2],80);assert.equal(s.save.party[0].foodHours,6);
 await key('F5');const stored=await read("$GLOBALS['app']->engine->storage->readJson('zombi-v02.json')");assert.equal(stored.party[0].foodHours,6);assert.equal(stored.party[0].gauges[2],80);
 await wait(2300);await p.reload({waitUntil:'load'});await ready();await key('c');await wait(300);s=await state();assert.equal(s.save.party[0].foodHours,7);assert.equal(s.save.party[0].awakeHours,22);assert.equal(s.save.party[0].gauges[2],64);
 await php('$w->minutes+=120;');await wait(200);assert.equal((await state()).save.party[0].foodHours,7);
 await setup("$save['party'][3]['awakeHours']=26;$save['healthTicks']=15103.0;");await wait(200);s=await state();assert.equal(s.save.party[3].state,16);assert.equal(s.mode,'character');await p.screenshot({path:process.argv[3]});await key('1');assert.equal((await state()).save.active,0);
 await php("$save=$w->export();$save['party'][3]['ticks']=1000.0;$save['healthTicks']=15103.0;$w->restore($save);");await wait(200);s=await state();assert.equal(s.save.party[3].awakeHours,28);assert.ok(s.save.party[3].ticks<100);
 await php("$save=$w->export();$save['party'][3]['awakeHours']=20;$save['party'][3]['foodHours']=5;$save['party'][3]['ticks']=119807.0;$save['party'][3]['gauges'][2]=50;$save['healthTicks']=0.0;$w->restore($save);");await wait(200);s=await state();assert.equal(s.save.party[3].state,0);assert.equal(s.save.party[3].awakeHours,0);assert.equal(s.save.party[3].foodHours,5);assert.equal(s.save.party[3].gauges[2],58);
 await php("$w->party[0]['bag'][0]=55;$w->party[0]['gauges'][2]=50;$w->party[0]['foodHours']=6;$w->party[0]['awakeHours']=20;$w->chooseAction(5);");await key('Enter');await key('Enter');s=await state();assert.equal(s.save.party[0].foodHours,0);assert.equal(s.save.party[0].awakeHours,20);assert.equal(s.save.party[0].gauges[2],54);
 await php("$save=$w->export();$save['party'][3]['state']=16;$save['party'][3]['awakeHours']=26;$save['party'][3]['foodHours']=5;$save['party'][3]['ticks']=119807.0;$save['party'][3]['gauges'][2]=50;$save['healthTicks']=15103.0;$w->restore($save);");await wait(200);s=await state();assert.equal(s.save.party[3].state,0);assert.equal(s.save.party[3].gauges[2],58);assert.equal(s.save.party[3].awakeHours,1);assert.equal(s.save.party[3].foodHours,6);
 assert.equal(errors.length,0,errors.join('\n'));console.log('Deprivation browser OK: saved deferred hunger/fatigue, display-clock independence, forced sleep, repeated timer reset, natural wake boundary, food recovery and separate counters.');
 }finally{await b.close();}
})().catch(e=>{console.error(e);process.exit(1);});
