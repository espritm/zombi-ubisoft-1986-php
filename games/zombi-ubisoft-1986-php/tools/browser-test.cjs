// Run with Puppeteer in Docker: node browser-test.cjs <direct game URL> <screenshot.png>
const puppeteer = require('puppeteer');
const assert = require('node:assert/strict');
(async () => {
  const browser = await puppeteer.launch({headless: true, args: ['--no-sandbox','--use-gl=swiftshader','--enable-unsafe-swiftshader','--autoplay-policy=no-user-gesture-required']});
  try {
    const page = await browser.newPage();
    await page.setViewport({width:960,height:600});
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', msg => { if (/CRASH|BOOT FAILED|Fatal error|Uncaught/.test(msg.text())) errors.push(msg.text()); });
    const wait = ms => new Promise(resolve => setTimeout(resolve,ms));
    const ready = async () => {
      await page.waitForFunction(() => document.getElementById('loader')?.style.display === 'none', {timeout:60000});
      await page.mouse.click(480,100); await wait(200);
    };
    const key = async name => { await page.keyboard.down(name); await wait(60); await page.keyboard.up(name); await wait(120); };
    const state = () => page.evaluate(() => {
      window.JumPHP.php(`$scene=$GLOBALS['app']->engine->scenes->current();
        $w=(new ReflectionProperty($scene,'world'))->getValue($scene);
        file_put_contents('/tmp/zombi-browser-state.json',json_encode(['room'=>$w->roomId(),'active'=>$w->active,
        'mode'=>$w->mode,'choice'=>$w->choice,'minutes'=>$w->minutes,'save'=>$w->export()]));`);
      return JSON.parse(window.JumPHP.FS.readFile('/tmp/zombi-browser-state.json',{encoding:'utf8'}));
    });
    await page.goto(process.argv[2],{waitUntil:'load'}); await ready();
    await key('Enter'); await wait(500);
    assert.equal((await state()).room,62);
    await key('m'); for(let i=0;i<3;i++) await key('ArrowDown'); await key('Enter');
    assert.equal((await state()).room,61);
    await key('F5'); const saved = await state();
    await key('m'); await key('ArrowDown'); await key('Enter'); assert.equal((await state()).room,60);
    await key('F9'); assert.equal((await state()).room,61);
    await key('1'); assert.equal((await state()).room,62); assert.equal((await state()).save.party[0].held,47);
    await key('4'); assert.equal((await state()).room,61);
    await key('Escape'); const paused = await state(); assert.equal(paused.mode,'pause');
    await wait(500); assert.equal((await state()).minutes,paused.minutes);
    await key('Escape'); await key('F1'); await key('Enter');
    await page.screenshot({path:process.argv[3]});
    await wait(2300); // IndexedDB flush, then reload and continue through the title UI.
    await page.reload({waitUntil:'load'}); await ready(); await key('c'); await wait(500);
    const continued=await state(); assert.equal(continued.room,61); assert.equal(continued.active,saved.active);
    await key('Escape'); await key('r'); await key('Enter');
    const restarted=await state(); assert.equal(restarted.room,62); assert.equal(restarted.active,3);
    // Controlled starting positions isolate the new mechanisms; all interactions below use the UI.
    const setup = code => page.evaluate(code => window.JumPHP.php(`$scene=$GLOBALS['app']->engine->scenes->current();
      $w=(new ReflectionProperty($scene,'world'))->getValue($scene);${code}`),code);
    const move = async direction => {
      await key('m'); const current=(await state()).choice;
      for(let i=0;i<(direction-current+6)%6;i++) await key('ArrowDown');
      await key('Enter');
    };
    await setup("$w->party[3]['position']=122;$w->party[0]['position']=58;$w->rooms[63]['prefix'][6]=0;$w->chooseAction(4);$w->row=0;$w->col=3;");
    await key('Enter'); await key('Enter');
    const called=await state(); assert.equal(called.save.map[114],63); assert.equal(called.save.party[0].position,114);
    await move(2); assert.equal((await state()).room,63);
    await setup('$w->chooseAction(4);$w->row=0;$w->col=0;');
    await key('Enter'); await key('Enter'); assert.equal((await state()).mode,'lift');
    await page.screenshot({path:process.argv[3].replace(/\.png$/,'-lift-menu.png')});
    await key('ArrowDown'); await key('Enter');
    const lifted=await state(); assert.equal(lifted.save.party[3].position,170); assert.equal(lifted.save.party[0].position,170);
    await page.screenshot({path:process.argv[3].replace(/\.png$/,'-lift.png')});
    await key('F5'); await wait(2300);
    await page.reload({waitUntil:'load'}); await ready(); await key('c'); await wait(500);
    assert.equal((await state()).save.map[170],63); assert.equal((await state()).room,63);
    await setup("$w->party[3]['position']=131;$w->party[3]['held']=41;$w->party[3]['used']=0;$w->chooseAction(3);");
    await key('Enter'); assert.equal((await state()).save.party[3].used,41);
    await setup('$w->chooseAction(8);$cell=array_search(8,$w->current()[\'cells\'],true);$w->row=intdiv($cell,10);$w->col=$cell%10;');
    await key('Enter'); await key('Enter'); await move(3);
    const rope=await state(); assert.equal(rope.room,66); assert.equal(rope.save.party[3].held,0); assert.equal(rope.save.party[3].used,0);
    assert.ok(rope.save.rooms[38].cells.includes(41));
    assert.equal(errors.length,0,errors.join('\n'));
    console.log('Browser OK: movement, survivors, saves/reload, pause/help/restart, lift call/panel/passengers/persistence and rope UI; no PHP/JS errors.');
  } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exit(1);});
