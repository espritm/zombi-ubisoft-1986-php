// Full video supply route from a fresh game. The PHP bridge only reads state into a temporary JSON file.
const puppeteer=require('puppeteer');const assert=require('node:assert/strict');
(async()=>{
 const browser=await puppeteer.launch({headless:true,args:['--no-sandbox','--use-gl=swiftshader','--enable-unsafe-swiftshader','--autoplay-policy=no-user-gesture-required']});
 try{
  const page=await browser.newPage();await page.setViewport({width:960,height:600});const errors=[];
  page.on('pageerror',e=>errors.push(e.message));page.on('console',m=>{if(/CRASH|BOOT FAILED|Fatal error|Uncaught/.test(m.text()))errors.push(m.text());});
  const wait=ms=>new Promise(r=>setTimeout(r,ms));
  const key=async k=>{await page.keyboard.down(k);await wait(45);await page.keyboard.up(k);await wait(65);};
  const ready=async()=>{await page.waitForFunction(()=>document.getElementById('loader')?.style.display==='none',{timeout:60000});await page.mouse.click(480,100);await wait(100);};
  const state=()=>page.evaluate(()=>{
   window.JumPHP.php(`$s=$GLOBALS['app']->engine->scenes->current();$w=(new ReflectionProperty($s,'world'))->getValue($s);
    file_put_contents('/tmp/zombi-route.json',json_encode(['save'=>$w->export(),'room'=>$w->roomId(),'action'=>$w->action,'choice'=>$w->choice,'row'=>$w->row,'col'=>$w->col,'mode'=>$w->mode]));`);
   return JSON.parse(window.JumPHP.FS.readFile('/tmp/zombi-route.json',{encoding:'utf8'}));
  });
  const travel=async room=>{
   let s=await state();const map=s.save.map,rooms=s.save.rooms,start=s.save.party[s.save.active].position;
   const goal=Number(Object.keys(map).find(p=>map[p]===room));assert.ok(Number.isFinite(goal));
   const q=[[start,[]]],seen=new Set([start]);let route;const ds=[8,56,-8,1,-56,-1],fs=[0,5,2,1,4,3];
   for(let i=0;i<q.length;i++){
    const [p,steps]=q[i];if(p===goal){route=steps;break;}
    for(let d=0;d<6;d++){const n=p+ds[d];if(seen.has(n)||!map[n]||![0,20].includes(rooms[map[p]].prefix[fs[d]]))continue;
     if(d===0&&map[n]===59&&![0,20].includes(rooms[59].prefix[2]))continue;seen.add(n);q.push([n,[...steps,d]]);}
   }
   assert.ok(route,'No route to '+room);
   for(const d of route){await key('m');s=await state();for(let i=0;i<(d-s.choice+6)%6;i++)await key('ArrowDown');await key('Enter');}
   assert.equal((await state()).room,room);console.log('Reached room',room,'with',route.length,'ordinary moves');
  };
  const select=async action=>{const s=await state();assert.equal(s.mode,'normal');let delta=(action-s.action+18)%18;
   const k=delta<=9?'ArrowDown':'ArrowUp';if(delta>9)delta=18-delta;for(let i=0;i<delta;i++)await key(k);await key('Enter');};
  const target=async id=>{const s=await state();assert.equal(s.mode,'object');const cell=s.save.rooms[s.room].cells.indexOf(id);assert.ok(cell>=0,'Object '+id);
   for(let i=0;i<(Math.floor(cell/10)-s.row+4)%4;i++)await key('ArrowDown');
   for(let i=0;i<(cell%10-s.col+10)%10;i++)await key('ArrowRight');await key('Enter');};
  const object=async(action,id)=>{await select(action);await target(id);};
  await page.goto(process.argv[2],{waitUntil:'load'});await ready();await key('Enter');await wait(400);
  assert.equal((await state()).room,62);await travel(48);await object(1,50);
  assert.ok((await state()).save.party[3].bag.includes(50));await travel(29);
  await object(4,60);await select(2);assert.equal((await state()).mode,'inventory');await key('Enter');await target(65);
  assert.equal((await state()).save.videoCassette,50);await object(4,61);
  const before=(await state()).save.minutes;await object(4,65);const played=await state();
  assert.ok(played.save.minutes-before>=80);assert.equal(played.save.party[3].trained,true);assert.equal(played.save.party[0].trained,false);
  await page.screenshot({path:process.argv[3]});await key('F5');await wait(2300);await page.reload({waitUntil:'load'});await ready();await key('c');await wait(400);
  assert.equal((await state()).save.videoCassette,50);assert.equal((await state()).save.party[3].trained,true);await travel(62);
  assert.equal((await state()).save.party[3].state,0);assert.equal((await state()).save.party[0].held,47);
  assert.equal(errors.length,0,errors.join('\n'));console.log('Fresh-game video route OK: roof → cassette → video → save/reload → roof. No position, inventory, enemy or health injection.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
