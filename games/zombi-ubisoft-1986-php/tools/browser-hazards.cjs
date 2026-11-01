// Controlled initial equipment/health/positions; hazards, body retrieval and saves use actual UI.
const puppeteer=require('puppeteer'),assert=require('node:assert/strict');
(async()=>{
 const b=await puppeteer.launch({headless:true,args:['--no-sandbox','--use-gl=swiftshader','--enable-unsafe-swiftshader','--autoplay-policy=no-user-gesture-required']});
 try{
 const p=await b.newPage();await p.setViewport({width:960,height:600});const errors=[];
 p.on('pageerror',e=>errors.push(e.message));p.on('console',m=>{if(/CRASH|BOOT FAILED|Fatal error|Uncaught/.test(m.text()))errors.push(m.text());});
 const wait=ms=>new Promise(r=>setTimeout(r,ms)),key=async k=>{await p.keyboard.down(k);await wait(45);await p.keyboard.up(k);await wait(65);};
 const ready=async()=>{await p.waitForFunction(()=>document.getElementById('loader')?.style.display==='none',{timeout:180000});await p.mouse.click(480,100);};
 const php=code=>p.evaluate(code=>window.JumPHP.php(`$s=$GLOBALS['app']->engine->scenes->current();$w=(new ReflectionProperty($s,'world'))->getValue($s);${code}`),code);
 const state=async()=>{await php(`file_put_contents('/tmp/hazards.json',json_encode(['save'=>$w->export(),'mode'=>$w->mode,'action'=>$w->action,'room'=>$w->roomId(),'ending'=>$w->ending]));`);return p.evaluate(()=>JSON.parse(window.JumPHP.FS.readFile('/tmp/hazards.json',{encoding:'utf8'})));};
 const select=async action=>{const s=await state();let n=(action-s.action+18)%18,k=n<=9?'ArrowDown':'ArrowUp';if(n>9)n=18-n;for(let i=0;i<n;i++)await key(k);await key('Enter');};
 const cell=async i=>{await p.mouse.click((16+i%10*16)*3,(72+Math.floor(i/10)*16)*3);await wait(150);};
 const direction=async i=>{await key('m');await p.mouse.click(700,(58+i*10)*3);await wait(150);};
 await p.goto(process.argv[2],{waitUntil:'load'});await ready();console.log('Browser runtime ready');await key('Enter');await wait(300);
 assert.equal((await state()).save.version,'0.17.0');const base=Buffer.from(JSON.stringify((await state()).save)).toString('base64');
 const setup=(room,extra='')=>php(`$save=json_decode(base64_decode('${base}'),true);$save['electricity']=true;$save['party'][3]['position']=array_search(${room},$save['map'],true);$save['party'][3]['bag']=[51,33,45,48];$save['party'][3]['held']=43;$save['party'][3]['used']=43;$save['rooms'][${room}]['prefix'][6]=0;$save['alertTicks']=0.0;${extra}if(!$w->restore($save))throw new Exception('Fixture refused');`);
 const shock=async()=>{await select(2);await key('Enter');await cell(32);};
 for(const [f,h,ef,eh,fatal] of [[19,80,19,80,true],[70,9,50,9,true],[20,80,0,70,false],[20,10,0,0,false]]){
  await setup(38,`$save['party'][3]['gauges']=[${f},${h},80];$save['rooms'][38]['cells'][32]=22;`);await shock();const s=await state();
  assert.deepEqual(s.save.party[3].gauges,[ef,eh,80]);assert.equal(s.save.party[3].state,fatal?255:0);assert.deepEqual(s.save.containers[22],[0]);
  assert.deepEqual(s.save.containers[32],fatal?[51,33,45,48,43]:[0,0,0,0,0]);assert.equal(s.mode,fatal?'character':'normal');
 }
 for(const active of [false,true]){
  await setup(38,`$save['rooms'][38]['cells'][32]=22;$save['party'][3]['held']=45;$save['party'][3]['used']=${active?45:43};`);await shock();const s=await state();
  assert.deepEqual(s.save.party[3].gauges,active?[70,80,80]:[50,70,80]);assert.deepEqual(s.save.containers[22],[active?51:0]);
 }
 for(const [source,destination,dir] of [[35,64,5],[38,66,3],[46,65,0]]){
  await setup(source);let s=await state();const originalPosition=s.save.party[3].position;
  if(source!==46){const grid=s.save.rooms[source].cells;await select(8);await cell(grid.findIndex(x=>x===7||x===8));}
  await direction(dir);s=await state();assert.equal(s.save.party[3].state,255);assert.equal(s.save.party[3].position,originalPosition);
  assert.deepEqual(s.save.party[3].gauges,[70,80,80]);assert.ok(s.save.rooms[destination].cells.includes(32));assert.ok(!s.save.rooms[source].cells.includes(32));assert.deepEqual(s.save.containers[32],[51,33,45,48,43]);
 }
 await setup(46,"$save['party'][1]['position']=array_search(65,$save['map'],true);$save['party'][1]['bag']=[0,0,0,0];$save['party'][1]['held']=0;");
 await direction(0);await key('2');assert.equal((await state()).room,65);await select(1);await cell((await state()).save.rooms[65].cells.indexOf(32));
 await p.screenshot({path:process.argv[3]});await key('Enter');assert.ok((await state()).save.party[1].bag.includes(32));
 await key('F5');await wait(2300);await p.reload({waitUntil:'load'});await ready();await key('c');await wait(300);let s=await state();
 assert.equal(s.room,65);assert.equal(s.save.party[3].state,255);assert.ok(s.save.party[1].bag.includes(32));assert.deepEqual(s.save.containers[32],[51,33,45,48,43]);
 await setup(46,"$save['party'][3]['held']=41;$save['party'][3]['used']=0;");await key('u');await direction(0);s=await state();assert.equal(s.room,65);assert.equal(s.save.party[3].state,0);assert.deepEqual(s.save.containers[32],[0,0,0,0,0]);
 const last="for($i=0;$i<3;$i++)$save['party'][$i]['state']=255;";
 await setup(38,last+"$save['party'][3]['gauges'][0]=19;$save['rooms'][38]['cells'][32]=22;");await shock();s=await state();assert.equal(s.ending,'defeat');assert.deepEqual(s.save.party[3].bag,[51,33,45,48]);assert.deepEqual(s.save.containers[32],[0,0,0,0,0]);
 await setup(46,last);await direction(0);s=await state();assert.equal(s.ending,'defeat');assert.deepEqual(s.save.party[3].bag,[0,0,0,0]);assert.deepEqual(s.save.containers[32],[51,33,45,48,43]);
 assert.equal(errors.length,0,errors.join('\n'));console.log('Hazards browser OK: sequential fatal shocks, exact-zero survival, held/active gloves, all three fall destinations, body retrieval below, save/reload, active rope, last-survivor cleanup order.');
 }finally{await b.close();}
})().catch(e=>{console.error(e);process.exit(1);});
