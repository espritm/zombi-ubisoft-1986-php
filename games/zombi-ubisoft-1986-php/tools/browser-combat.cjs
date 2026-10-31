// Controlled equipment/position and shot-cursor fixtures; actual combat inputs go through the keyboard.
const puppeteer=require('puppeteer'),assert=require('node:assert/strict');
(async()=>{
 const b=await puppeteer.launch({headless:true,args:['--no-sandbox','--use-gl=swiftshader','--enable-unsafe-swiftshader','--autoplay-policy=no-user-gesture-required']});
 try{
 const p=await b.newPage();await p.setViewport({width:960,height:600});const errors=[];
 p.on('pageerror',e=>errors.push(e.message));p.on('console',m=>{if(/CRASH|BOOT FAILED|Fatal error|Uncaught/.test(m.text()))errors.push(m.text());});
 const wait=ms=>new Promise(r=>setTimeout(r,ms));
 const key=async(k,down=45,up=65)=>{await p.keyboard.down(k);await wait(down);await p.keyboard.up(k);await wait(up);};
 const ready=async()=>{await p.waitForFunction(()=>document.getElementById('loader')?.style.display==='none',{timeout:180000});await p.mouse.click(480,100);};
 const php=code=>p.evaluate(code=>window.JumPHP.php(`$s=$GLOBALS['app']->engine->scenes->current();$w=(new ReflectionProperty($s,'world'))->getValue($s);${code}`),code);
 const state=async()=>{await php(`file_put_contents('/tmp/combat.json',json_encode(['save'=>$w->export(),'mode'=>$w->mode,'pressure'=>$w->pressure,'delay'=>$w->shotDelay,'width'=>$w->aimWidth()]));`);return p.evaluate(()=>JSON.parse(window.JumPHP.FS.readFile('/tmp/combat.json',{encoding:'utf8'})));};
 await p.goto(process.argv[2],{waitUntil:'load'});await ready();await key('Enter');await wait(200);
 const base=Buffer.from(JSON.stringify((await state()).save)).toString('base64');
 const setup=async(weapon,trained=false)=>{await php(`$base=json_decode(base64_decode('${base}'),true);$base['enemySeed']=0;$w->restore($base);$w->party[3]['position']=array_search(41,$w->map,true);$w->party[3]['held']=${weapon};$w->party[3]['trained']=${trained?'true':'false'};$w->chooseAction(3);`);await key('Enter');};
 const shot=async cell=>{
  await p.evaluate(cell=>{const handler=e=>{if(e.key!=='Enter')return;window.removeEventListener('keydown',handler,true);window.JumPHP.php(`$s=$GLOBALS['app']->engine->scenes->current();$w=(new ReflectionProperty($s,'world'))->getValue($s);$w->aim=${(cell-2+.1)/56};`);};window.addEventListener('keydown',handler,true);},cell);
  await key('Enter');
 };
 await setup(37);for(let i=0;i<50;i++)await key(i%2?'ArrowLeft':'ArrowRight');
 let s=await state();assert.equal(s.pressure,0);assert.equal(s.save.rooms[41].hp,31);console.log('Slow alternation does not build pressure or damage the enemy');
 await setup(37);
 for(let i=0;i<48;i++)await key(i%2?'ArrowLeft':'ArrowRight',35,15);
 s=await state();assert.ok(s.pressure>0);assert.equal(s.save.rooms[41].hp,31);
 for(let batch=0;batch<60;batch++){
  if((await state()).save.rooms[41].prefix[6]===0)break;
  for(let i=0;i<12;i++)await key(i%2?'ArrowLeft':'ArrowRight',35,15);
 }
 s=await state();assert.equal(s.save.rooms[41].prefix[6],0);assert.equal(s.save.party[3].state,0);assert.equal(s.save.rooms[41].prefix[8],1);console.log('Rapid alternation wins through timed damage rounds');
 await setup(34);await shot(28);s=await state();assert.equal(s.save.rooms[41].hp,19);assert.equal(s.save.rooms[41].strength,15);assert.equal(s.mode,'aim');
 await p.screenshot({path:process.argv[3]});await key('F5');await wait(2300);await p.reload({waitUntil:'load'});await ready();await key('c');await wait(200);
 s=await state();assert.equal(s.save.rooms[41].hp,19);assert.equal(s.save.rooms[41].strength,15);
 await php('$w->chooseAction(3);');await key('Enter');await shot(28);assert.equal((await state()).save.rooms[41].hp,7);await wait(2000);await shot(28);assert.equal((await state()).save.rooms[41].prefix[6],0);
 await setup(34);await shot(26);assert.equal((await state()).save.rooms[41].prefix[6],0);
 await setup(34,true);assert.equal((await state()).width,6);await shot(26);assert.equal((await state()).save.rooms[41].hp,19);
 await setup(36);assert.equal((await state()).width,5);await shot(26);assert.equal((await state()).save.rooms[41].hp,19);
 assert.equal(errors.length,0,errors.join('\n'));console.log('Combat browser OK: rhythm, timed rounds, partial/outside shots, rifle/training widths and damaged-enemy save/reload.');
 }finally{await b.close();}
})().catch(e=>{console.error(e);process.exit(1);});
