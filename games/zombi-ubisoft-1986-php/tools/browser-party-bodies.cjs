// Controlled inventories/party states; elimination, looting, transport and saves use real UI.
const puppeteer=require('puppeteer'),assert=require('node:assert/strict');
(async()=>{
 const b=await puppeteer.launch({headless:true,args:['--no-sandbox','--use-gl=swiftshader','--enable-unsafe-swiftshader','--autoplay-policy=no-user-gesture-required']});
 try{
 const p=await b.newPage();await p.setViewport({width:960,height:600});const errors=[];
 p.on('pageerror',e=>errors.push(e.message));p.on('console',m=>{if(/CRASH|BOOT FAILED|Fatal error|Uncaught/.test(m.text()))errors.push(m.text());});
 const wait=ms=>new Promise(r=>setTimeout(r,ms)),key=async k=>{await p.keyboard.down(k);await wait(45);await p.keyboard.up(k);await wait(65);};
 const ready=async()=>{await p.waitForFunction(()=>document.getElementById('loader')?.style.display==='none',{timeout:180000});await p.mouse.click(480,100);};
 const php=code=>p.evaluate(code=>window.JumPHP.php(`$s=$GLOBALS['app']->engine->scenes->current();$w=(new ReflectionProperty($s,'world'))->getValue($s);${code}`),code);
 const state=async()=>{await php(`file_put_contents('/tmp/party-bodies.json',json_encode(['save'=>$w->export(),'mode'=>$w->mode,'action'=>$w->action,'choices'=>$w->containerChoices(),'room'=>$w->roomId()]));`);return p.evaluate(()=>JSON.parse(window.JumPHP.FS.readFile('/tmp/party-bodies.json',{encoding:'utf8'})));};
 const select=async action=>{const s=await state();let n=(action-s.action+18)%18,k=n<=9?'ArrowDown':'ArrowUp';if(n>9)n=18-n;for(let i=0;i<n;i++)await key(k);await key('Enter');};
 const cell=async(row,col)=>{await p.mouse.click((16+col*16)*3,(72+row*16)*3);await wait(150);};
 const takeBody=async()=>{await select(1);await cell(3,8);assert.equal((await state()).mode,'container');};
 await p.goto(process.argv[2],{waitUntil:'load'});await ready();console.log('Browser runtime ready');await key('Enter');await wait(300);
 let s=await state();assert.equal(s.save.version,'0.16.0');const base=Buffer.from(JSON.stringify(s.save)).toString('base64');
 const setup=extra=>php(`$save=json_decode(base64_decode('${base}'),true);$save['party'][3]['held']=34;$save['party'][0]['bag']=[33,37,45,48];$save['party'][0]['held']=43;$save['party'][0]['used']=43;$save['alertTicks']=0.0;${extra||''}if(!$w->restore($save))throw new Exception('Fixture refused');`);
 await setup();await key('u');await p.mouse.click(120,174);await wait(200);s=await state();
 assert.equal(s.save.party[0].state,255);assert.deepEqual(s.save.party[0].bag,[0,0,0,0]);assert.equal(s.save.party[0].held,0);assert.equal(s.save.party[0].used,0);
 assert.deepEqual(s.save.containers[29],[33,37,45,48,43]);assert.deepEqual(s.save.rooms[62].cells.slice(38,40),[29,29]);assert.equal(s.save.rooms[62].prefix[8],0);
 await takeBody();assert.deepEqual((await state()).choices,[29,33,37,45,48,43]);await p.screenshot({path:process.argv[3]});
 await p.mouse.click(700,204);await wait(150);s=await state();assert.equal(s.save.party[3].bag[0],33);assert.equal(s.save.containers[29][0],0);assert.equal(s.save.rooms[62].cells[38],29);
 await takeBody();await key('Enter');s=await state();assert.equal(s.save.party[3].bag[1],29);assert.ok(!s.save.rooms[62].cells.includes(29));
 await key('m');for(let i=0;i<3;i++)await key('ArrowRight');await key('Enter');assert.equal((await state()).room,61);
 await select(2);await key('ArrowRight');await key('Enter');assert.equal((await state()).mode,'object');await cell(3,8);s=await state();assert.deepEqual(s.save.rooms[61].cells.slice(38,40),[29,29]);assert.ok(!s.save.party[3].bag.includes(29));assert.equal(s.save.frozenBodies,0);
 await key('F5');await wait(2300);await p.reload({waitUntil:'load'});await ready();await key('c');await wait(300);s=await state();
 assert.equal(s.room,61);assert.deepEqual(s.save.containers[29],[0,37,45,48,43]);assert.deepEqual(s.save.rooms[61].cells.slice(38,40),[29,29]);
 await takeBody();await p.mouse.click(700,294);await wait(150);s=await state();assert.ok(s.save.party[3].bag.includes(43));assert.equal(s.save.containers[29][4],0);
 await setup();await key('u');await key('4');await key('Enter');s=await state();assert.equal(s.save.party[3].state,255);assert.equal(s.mode,'character');assert.equal(s.save.containers[32][0],34);assert.deepEqual(s.save.rooms[62].cells.slice(38,40),[32,32]);await key('2');assert.equal((await state()).save.active,1);
 await setup("$save['party'][0]['state']=128;$save['party'][0]['gauges']=[50,40,80];");await key('u');await key('1');await key('Enter');assert.equal((await state()).mode,'aim');
 await p.evaluate(()=>{const h=e=>{if(e.key!=='Enter')return;window.removeEventListener('keydown',h,true);window.JumPHP.php(`$s=$GLOBALS['app']->engine->scenes->current();$w=(new ReflectionProperty($s,'world'))->getValue($s);$w->aim=0;`);};window.addEventListener('keydown',h,true);});
 await key('Enter');s=await state();assert.equal(s.save.party[0].state,255);assert.deepEqual(s.save.rooms[62].cells.slice(38,40),[29,29]);assert.deepEqual(s.save.containers[29],[33,37,45,48,43]);
 assert.equal(errors.length,0,errors.join('\n'));console.log('Party bodies browser OK: allied elimination, five retained items, separate corpse/loot choices, mouse loot, body transport to another room, drop, save/reload, fifth item recovered, self elimination/survivor switch, zombie elimination.');
 }finally{await b.close();}
})().catch(e=>{console.error(e);process.exit(1);});
