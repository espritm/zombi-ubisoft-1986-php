// Fresh-game escape using keyboard input only. The PHP bridge reads state for assertions.
const puppeteer=require('puppeteer'),assert=require('node:assert/strict');
(async()=>{
 const browser=await puppeteer.launch({headless:true,args:['--no-sandbox','--use-gl=swiftshader','--enable-unsafe-swiftshader','--autoplay-policy=no-user-gesture-required']});
 try{
 const page=await browser.newPage();await page.setViewport({width:960,height:600});const errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 page.on('console',m=>{if(/CRASH|BOOT FAILED|Fatal error|Uncaught/.test(m.text()))errors.push(m.text());});
 const wait=ms=>new Promise(r=>setTimeout(r,ms));
 const key=async k=>{await page.keyboard.down(k);await wait(45);await page.keyboard.up(k);await wait(65);};
 const ready=async()=>{await page.waitForFunction(()=>document.getElementById('loader')?.style.display==='none',{timeout:60000});await page.mouse.click(480,100);};
 const state=()=>page.evaluate(()=>{
  window.JumPHP.php(`$s=$GLOBALS['app']->engine->scenes->current();$w=(new ReflectionProperty($s,'world'))->getValue($s);file_put_contents('/tmp/escape.json',json_encode(['save'=>$w->export(),'room'=>$w->roomId(),'action'=>$w->action,'choice'=>$w->choice,'row'=>$w->row,'col'=>$w->col,'mode'=>$w->mode,'aim'=>$w->aim,'ending'=>$w->ending,'message'=>$w->message,'inventory'=>$w->inventoryChoices()]));`);
  return JSON.parse(window.JumPHP.FS.readFile('/tmp/escape.json',{encoding:'utf8'}));
 });
 const person=s=>s.save.party[s.save.active];
 const choose=async(n,count)=>{let s=await state();for(let i=0;i<(n-s.choice+count)%count;i++)await key('ArrowDown');await key('Enter');};
 const select=async n=>{const s=await state();assert.equal(s.mode,'normal',s.message);let d=(n-s.action+18)%18,k=d<=9?'ArrowDown':'ArrowUp';if(d>9)d=18-d;for(let i=0;i<d;i++)await key(k);await key('Enter');};
 const fight=async()=>{
  for(let i=0;i<300;i++){
   let s=await state();assert.equal(person(s).state,0,s.message);if(s.save.rooms[s.room].prefix[6]===0)return;
   if([34,35,36].includes(person(s).held)){
    await select(3);for(let j=0;j<120;j++){s=await state();const a=s.aim%2,c=a<=1?a:2-a;if(c>.43&&c<.52)break;await wait(15);}await key('Enter');
   }else await key(i%2?'ArrowLeft':'ArrowRight');
  }throw Error('Combat timeout');
 };
 const move=async d=>{const before=await state(),expected=person(before).position+[8,56,-8,1,-56,-1][d];await key('m');await choose(d,6);let after=await state();assert.equal(person(after).position,expected,JSON.stringify({actor:after.save.active,d,room:after.room,mode:after.mode,message:after.message}));await fight();};
 const travel=async room=>{
  let s=await state(),map=s.save.map,rooms=s.save.rooms,start=person(s).position,goal=Number(Object.keys(map).find(p=>map[p]===room));
  const q=[[start,[]]],seen=new Set([start]),ds=[8,56,-8,1,-56,-1],fs=[0,5,2,1,4,3];let route;
  for(let i=0;i<q.length;i++){const[p,steps]=q[i];if(p===goal){route=steps;break;}for(let d=0;d<6;d++){
   const n=p+ds[d];if(seen.has(n)||!map[n]||![0,20].includes(rooms[map[p]].prefix[fs[d]]))continue;
   if(d===0&&map[n]===59&&![0,20].includes(rooms[59].prefix[2]))continue;seen.add(n);q.push([n,[...steps,d]]);
  }}assert.ok(route,'No route to '+room);for(const d of route)await move(d);assert.equal((await state()).room,room);
 };
 const cell=async n=>{const s=await state();assert.equal(s.mode,'object',s.message);for(let i=0;i<(Math.floor(n/10)-s.row+4)%4;i++)await key('ArrowDown');for(let i=0;i<(n%10-s.col+10)%10;i++)await key('ArrowRight');await key('Enter');};
 const target=async id=>{const s=await state(),n=s.save.rooms[s.room].cells.indexOf(id);assert.ok(n>=0,'Missing object '+id+' in '+s.room);await cell(n);};
 const object=async(a,id)=>{await select(a);await target(id);};
 const inventory=async(a,id)=>{await select(a);const s=await state(),i=s.inventory.findIndex(e=>e.item===id);assert.ok(i>=0,'Missing item '+id);await choose(i,s.inventory.length);};
 const equip=async id=>inventory(18,id);
 const floor=async id=>{await inventory(2,id);const s=await state();await cell(s.save.rooms[s.room].cells.lastIndexOf(0));};
 const actor=async n=>{await key(String(n+1));assert.equal((await state()).save.active,n);};
 const lift=async n=>{await object(4,74);await choose(n,5);};
 const freeze=async()=>{await travel(11);for(let i=0;i<4;i++){if(!person(await state()).bag.includes(53))break;await inventory(2,53);}console.log('Frozen:',(await state()).save.frozenBodies);};
 await page.goto(process.argv[2],{waitUntil:'load'});await ready();await key('Enter');await wait(300);
 await actor(2);await travel(38);await object(8,13);await object(1,26);await key('Enter');await equip(43);await select(3);await travel(17);
 await actor(1);await travel(41);await object(1,35);await equip(35);await travel(47);await object(1,49);await travel(63);await lift(4);
 await equip(49);await select(8);await key('f');await choose(0,6);await move(0);await object(1,48);await floor(49);await move(2);await lift(1);await equip(35);
 await travel(58);await object(8,12);await object(1,25);await key('Enter');await travel(52);await object(8,11);await object(1,24);await key('Enter');await object(1,24);await key('Enter');
 await travel(17);await object(8,9);await equip(45);await select(3);await inventory(2,51);await target(22);await equip(35);
 await travel(63);await lift(4);await move(0);await object(4,62);assert.equal((await state()).save.electricity,true);await move(2);await lift(1);console.log('Power restored through shared torch, gloves and fuse');
 await travel(26);await object(1,42);
 for(const[source,door]of[[77,1],[72,2],[82,3]]){await equip(35);await travel(source);await object(8,door);await select(16);await equip(48);await object(4,63);await object(4,71);await select(17);console.log('Truck parked from',source);}
 await actor(3);await travel(41);await object(1,34);await equip(34);let picked=0;
 for(let id=1;id<=63;id++){
  let s=await state();if(!s.save.rooms[id].prefix[6]&&!s.save.rooms[id].prefix[8])continue;await travel(id);
  for(let j=0;j<40;j++){
   s=await state();if(!s.save.rooms[id].prefix[8])break;
   if(!person(s).bag.includes(0)){await freeze();await travel(id);s=await state();}
   const n=s.save.rooms[id].prefix[8];await object(1,53);assert.equal((await state()).save.rooms[id].prefix[8],n-1);picked++;
  }
 }
 await freeze();assert.equal(picked,34);assert.equal((await state()).save.raidersArrived,true);await key('F5');await wait(2300);await page.reload({waitUntil:'load'});await ready();await key('c');await wait(300);assert.equal((await state()).save.frozenBodies,34);assert.equal((await state()).save.raidersArrived,true);console.log('Arrival and save/reload verified');
 await actor(1);await equip(35);await travel(78);await page.screenshot({path:process.argv[3].replace('.png','-truck.png')});await object(8,5);await floor(52);await equip(42);await select(3);assert.equal((await state()).save.fuelCan,1);await object(1,52);
 await travel(62);await object(8,6);await floor(52);await select(3);assert.equal((await state()).save.fuelTank,1);await object(8,4);await select(16);
 for(const n of[3,2,0]){console.log('Boarding survivor',n);await actor(n);await travel(62);await select(16);}
 assert.deepEqual((await state()).save.party.map(p=>p.inside),[90,90,90,90]);await object(4,64);await object(4,72);assert.equal((await state()).ending,'victory');
 await page.screenshot({path:process.argv[3]});assert.equal(errors.length,0,errors.join('\n'));console.log('Complete fresh-game escape OK: four survivors, no game-state injection.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
