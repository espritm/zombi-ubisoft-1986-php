// Controlled timer/seed fixture only; NPC counts, positions and quota are changed by the running game.
const puppeteer=require('puppeteer'),assert=require('node:assert/strict');
(async()=>{
 const browser=await puppeteer.launch({headless:true,args:['--no-sandbox','--use-gl=swiftshader','--enable-unsafe-swiftshader','--autoplay-policy=no-user-gesture-required']});
 try{
 const page=await browser.newPage();await page.setViewport({width:960,height:600});const errors=[];
 page.on('pageerror',e=>errors.push(e.message));page.on('console',m=>{if(/CRASH|BOOT FAILED|Fatal error|Uncaught/.test(m.text()))errors.push(m.text());});
 const wait=ms=>new Promise(r=>setTimeout(r,ms));
 const key=async k=>{await page.keyboard.down(k);await wait(45);await page.keyboard.up(k);await wait(65);};
 const ready=async()=>{await page.waitForFunction(()=>document.getElementById('loader')?.style.display==='none',{timeout:60000});await page.mouse.click(480,100);};
 const state=()=>page.evaluate(()=>{
  window.JumPHP.php(`$s=$GLOBALS['app']->engine->scenes->current();$w=(new ReflectionProperty($s,'world'))->getValue($s);file_put_contents('/tmp/enemy-clock.json',json_encode($w->export()));`);
  return JSON.parse(window.JumPHP.FS.readFile('/tmp/enemy-clock.json',{encoding:'utf8'}));
 });
 await page.goto(process.argv[2],{waitUntil:'load'});await ready();await key('Enter');await wait(300);
 await page.evaluate(()=>window.JumPHP.php(`$s=$GLOBALS['app']->engine->scenes->current();$w=(new ReflectionProperty($s,'world'))->getValue($s);$save=$w->export();$save['enemyTicks']=15004.0;$save['enemySeed']=0;if(!$w->restore($save)){throw new Exception('Timer fixture refused');}`));
 const before=await state();assert.equal(before.zombieTarget,34);await wait(2400);const after=await state();
 assert.notEqual(after.enemySeed,before.enemySeed);assert.ok(after.enemyTicks<500);assert.ok(after.zombieTarget>34);
 const counts=s=>Object.values(s.rooms).map(r=>r.prefix[6]);assert.notDeepEqual(counts(after),counts(before));
 const sum=s=>counts(s).reduce((a,b)=>a+b,0);assert.equal(sum(after)-sum(before),after.zombieTarget-34);
 console.log('Periodic NPC movement and entrants driven by browser frames; quota',after.zombieTarget);
 await key('Escape');const paused=await state();await wait(1200);assert.deepEqual(await state(),paused);await key('Escape');
 await key('F5');const saved=await state();await wait(2300);await page.reload({waitUntil:'load'});await ready();await key('c');await wait(300);const loaded=await state();
 assert.equal(loaded.enemySeed,saved.enemySeed);assert.equal(loaded.zombieTarget,saved.zombieTarget);assert.deepEqual(counts(loaded),counts(saved));
 assert.ok(loaded.enemyTicks>=saved.enemyTicks-15&&loaded.enemyTicks<saved.enemyTicks+100);
 await page.screenshot({path:process.argv[3]});assert.equal(errors.length,0,errors.join('\n'));
 console.log('Enemy clock browser OK: periodic movement, quota growth, pause and save/reload.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
