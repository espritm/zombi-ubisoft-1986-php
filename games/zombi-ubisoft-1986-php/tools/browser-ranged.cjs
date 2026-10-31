// Controlled phase, enemy and timer fixtures; frame updates dispatch replies and normal keys drive selectors/save/use.
const puppeteer=require('puppeteer'),assert=require('node:assert/strict');
(async()=>{
 const b=await puppeteer.launch({headless:true,args:['--no-sandbox','--use-gl=swiftshader','--enable-unsafe-swiftshader','--autoplay-policy=no-user-gesture-required']});
 try{
 const p=await b.newPage();await p.setViewport({width:960,height:600});const errors=[];
 p.on('pageerror',e=>errors.push(e.message));p.on('console',m=>{if(/CRASH|BOOT FAILED|Fatal error|Uncaught/.test(m.text()))errors.push(m.text());});
 const wait=ms=>new Promise(r=>setTimeout(r,ms));const key=async k=>{await p.keyboard.down(k);await wait(45);await p.keyboard.up(k);await wait(65);};
 const ready=async()=>{await p.waitForFunction(()=>document.getElementById('loader')?.style.display==='none',{timeout:180000});await p.mouse.click(480,100);};
 const php=code=>p.evaluate(code=>window.JumPHP.php(`$s=$GLOBALS['app']->engine->scenes->current();$w=(new ReflectionProperty($s,'world'))->getValue($s);${code}`),code);
 const read=async(code='$w->export()')=>{await php(`file_put_contents('/tmp/ranged.json',json_encode(${code}));`);return p.evaluate(()=>JSON.parse(window.JumPHP.FS.readFile('/tmp/ranged.json',{encoding:'utf8'})));};
 const state=()=>read("['save'=>$w->export(),'mode'=>$w->mode]");
 await p.goto(process.argv[2],{waitUntil:'load'});await ready();await key('Enter');await wait(200);
 const baseSave=(await state()).save;for(const r of Object.values(baseSave.rooms)){if(!r.prefix[6])continue;assert.ok(r.hp>=26&&r.hp<=41);assert.equal(r.hp+r.strength,58);assert.equal(r.shooter,false);}
 const base=Buffer.from(JSON.stringify(baseSave)).toString('base64');
 const setup=async(shooter=true,ticks=210,f=70,h=80)=>php(`$save=json_decode(base64_decode('${base}'),true);$save['enemySeed']=57;$save['raidersArrived']=true;$save['party'][3]['position']=array_search(41,$save['map'],true);$save['party'][3]['held']=34;$save['party'][3]['gauges'][0]=${f};$save['party'][3]['gauges'][1]=${h};$save['rooms'][41]['hp']=35;$save['rooms'][41]['strength']=30;$save['rooms'][41]['shooter']=${shooter?'true':'false'};$save['alertTicks']=${ticks}.0;$save['rangedPending']=false;$save['rangedArmed']=${shooter?'true':'false'};if(!$w->restore($save))throw new Exception('Fixture refused');`);
 await setup(false);await wait(1200);assert.deepEqual((await state()).save.party[3].gauges,[70,80,80]);
 await setup();await wait(1200);let s=await state();assert.deepEqual(s.save.party[3].gauges,[64,74,80]);assert.equal(s.save.rangedPending,false);await p.screenshot({path:process.argv[3]});
 await setup();await key('i');await wait(1200);s=await state();assert.equal(s.mode,'inventory');assert.equal(s.save.rangedPending,true);assert.deepEqual(s.save.party[3].gauges,[70,80,80]);
 await key('F5');const saved=await read("$GLOBALS['app']->engine->storage->readJson('zombi-v02.json')");assert.equal(saved.rangedPending,true);assert.deepEqual(saved.party[3].gauges,[70,80,80]);
 await wait(2300);await p.reload({waitUntil:'load'});await ready();await key('c');await wait(200);s=await state();assert.deepEqual(s.save.party[3].gauges,[64,74,80]);assert.equal(s.save.rangedPending,false);
 console.log('Pending reply deferred by inventory, saved and dispatched once after reload');
 await setup(false,500);await wait(400);assert.equal((await state()).mode,'combat');await key('u');assert.equal((await state()).mode,'aim');
 await setup(true,250,6,80);await wait(400);s=await state();assert.equal(s.save.party[3].state,64);assert.deepEqual(s.save.party[3].gauges,[0,80,80]);
 assert.equal(errors.length,0,errors.join('\n'));console.log('Ranged browser OK: stat ranges, armed/unarmed, six-point reply, deferred save, automatic melee, U shortcut and fatal write order.');
 }finally{await b.close();}
})().catch(e=>{console.error(e);process.exit(1);});
