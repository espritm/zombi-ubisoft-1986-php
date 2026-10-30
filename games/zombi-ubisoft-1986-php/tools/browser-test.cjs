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
        'mode'=>$w->mode,'minutes'=>$w->minutes,'save'=>$w->export()]));`);
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
    assert.equal(errors.length,0,errors.join('\n'));
    console.log('Browser OK: movement, independent survivors, save/load, IndexedDB reload, pause, help, restart; no PHP/JS errors.');
  } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exit(1);});
