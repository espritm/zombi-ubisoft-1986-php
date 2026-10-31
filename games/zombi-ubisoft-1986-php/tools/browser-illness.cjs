// Controlled illness/body/timer fixtures; actual frames, menus and storage apply effects.
const puppeteer=require('puppeteer'),assert=require('node:assert/strict');
(async()=>{
 const b=await puppeteer.launch({headless:true,args:['--no-sandbox','--use-gl=swiftshader','--enable-unsafe-swiftshader','--autoplay-policy=no-user-gesture-required']});
 try{
 const p=await b.newPage();await p.setViewport({width:960,height:600});const errors=[];p.on('pageerror',e=>errors.push(e.message));p.on('console',m=>{if(/CRASH|BOOT FAILED|Fatal error|Uncaught/.test(m.text()))errors.push(m.text());});
 const wait=ms=>new Promise(r=>setTimeout(r,ms)),key=async k=>{await p.keyboard.down(k);await wait(45);await p.keyboard.up(k);await wait(65);};
 const ready=async()=>{await p.waitForFunction(()=>document.getElementById('loader')?.style.display==='none',{timeout:180000});await p.mouse.click(480,100);};
 const php=code=>p.evaluate(code=>window.JumPHP.php(`$s=$GLOBALS['app']->engine->scenes->current();$w=(new ReflectionProperty($s,'world'))->getValue($s);${code}`),code);
 const read=async expr=>{await php(`file_put_contents('/tmp/illness.json',json_encode(${expr}));`);return p.evaluate(()=>JSON.parse(window.JumPHP.FS.readFile('/tmp/illness.json',{encoding:'utf8'})));};
 const state=()=>read("['save'=>$w->export(),'mode'=>$w->mode]");
 await p.goto(process.argv[2],{waitUntil:'load'});await ready();await key('Enter');await wait(300);
 const base=Buffer.from(JSON.stringify((await state()).save)).toString('base64');
 const setup=extra=>php(`$save=json_decode(base64_decode('${base}'),true);$save['enemySeed']=0;${extra}if(!$w->restore($save))throw new Exception('Fixture refused');`);
 await setup("$save['party'][0]['illness']=2;$save['healthTicks']=15000.0;");await key('i');await wait(2400);let s=await state();assert.equal(s.mode,'inventory');assert.equal(s.save.party[0].gauges[2],80);assert.ok(s.save.healthTicks>=15104);
 await key('F5');const stored=await read("$GLOBALS['app']->engine->storage->readJson('zombi-v02.json')");assert.equal(stored.party[0].gauges[2],80);assert.ok(stored.healthTicks>=15104);
 await wait(2300);await p.reload({waitUntil:'load'});await ready();await key('c');await wait(300);s=await state();assert.equal(s.save.party[0].gauges[2],70);assert.equal(s.save.party[0].illness,2);await wait(300);assert.equal((await state()).save.party[0].gauges[2],70);
 await setup("$save['party'][0]['illness']=1;$save['party'][0]['gauges'][2]=10;$save['healthTicks']=15103.0;");await wait(200);s=await state();assert.equal(s.save.party[0].state,0);assert.equal(s.save.party[0].gauges[2],0);
 await php("$save=$w->export();$save['healthTicks']=15103.0;$w->restore($save);");await wait(200);s=await state();assert.equal(s.save.party[0].state,64);assert.deepEqual(s.save.party[0].gauges,[70,70,0]);
 await setup("$save['rooms'][62]['prefix'][8]=1;$save['party'][3]['gauges'][2]=3;$save['healthTicks']=15103.0;");await wait(200);s=await state();assert.equal(s.save.party[3].state,255);assert.deepEqual(s.save.party[3].gauges,[70,80,255]);assert.equal(s.mode,'character');await p.screenshot({path:process.argv[3]});
 await setup("$save['party'][3]['position']=array_search(41,$save['map'],true);$save['rooms'][41]['hp']=255;");await php("$w->mode='combat';");await wait(4200);s=await state();const hit=70-s.save.party[3].gauges[0];assert.ok(hit>=3&&hit<=6);assert.equal(s.save.party[3].exposure,2*hit);assert.equal(s.save.party[3].illness,0);
 assert.equal(errors.length,0,errors.join('\n'));console.log('Illness browser OK: deferred period saved/reloaded once, zero then delayed death, corpse byte underflow/permanent death, zombie melee exposure and no invented infection.');
 }finally{await b.close();}
})().catch(e=>{console.error(e);process.exit(1);});
