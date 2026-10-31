// Controlled starting positions/gauges; first sleep/wake uses real bed and character controls.
const puppeteer=require('puppeteer'),assert=require('node:assert/strict');
(async()=>{
 const b=await puppeteer.launch({headless:true,args:['--no-sandbox','--use-gl=swiftshader','--enable-unsafe-swiftshader','--autoplay-policy=no-user-gesture-required']});
 try{
 const p=await b.newPage();await p.setViewport({width:960,height:600});const errors=[];p.on('pageerror',e=>errors.push(e.message));p.on('console',m=>{if(/CRASH|BOOT FAILED|Fatal error|Uncaught/.test(m.text()))errors.push(m.text());});
 const wait=ms=>new Promise(r=>setTimeout(r,ms)),key=async k=>{await p.keyboard.down(k);await wait(45);await p.keyboard.up(k);await wait(65);};
 const ready=async()=>{await p.waitForFunction(()=>document.getElementById('loader')?.style.display==='none',{timeout:180000});await p.mouse.click(480,100);};
 const php=code=>p.evaluate(code=>window.JumPHP.php(`$s=$GLOBALS['app']->engine->scenes->current();$w=(new ReflectionProperty($s,'world'))->getValue($s);${code}`),code);
 const state=async()=>{await php(`file_put_contents('/tmp/manual-wake.json',json_encode(['save'=>$w->export(),'mode'=>$w->mode,'action'=>$w->action,'row'=>$w->row,'col'=>$w->col]));`);return p.evaluate(()=>JSON.parse(window.JumPHP.FS.readFile('/tmp/manual-wake.json',{encoding:'utf8'})));};
 const select=async action=>{const s=await state();let n=(action-s.action+18)%18,k=n<=9?'ArrowDown':'ArrowUp';if(n>9)n=18-n;for(let i=0;i<n;i++)await key(k);await key('Enter');};
 await p.goto(process.argv[2],{waitUntil:'load'});await ready();await key('Enter');await wait(300);
 const base=Buffer.from(JSON.stringify((await state()).save)).toString('base64');
 const setup=extra=>php(`$save=json_decode(base64_decode('${base}'),true);$save['electricity']=true;$save['party'][0]['position']=$save['party'][3]['position']=array_search(34,$save['map'],true);$save['party'][3]['gauges'][2]=50;$save['party'][3]['awakeHours']=20;$save['party'][3]['foodHours']=5;${extra}if(!$w->restore($save))throw new Exception('Fixture refused');`);
 await setup('');await select(6);await p.mouse.click(192,312);await wait(200);let s=await state();assert.equal(s.save.party[3].state,16);assert.equal(s.save.party[3].bed,57);
 await key('1');await select(7);await p.screenshot({path:process.argv[3]});const elapsed=(await state()).save.party[3].ticks;await key('Enter');s=await state();assert.equal(s.save.active,0);assert.equal(s.save.party[3].state,0);assert.equal(s.save.party[3].bed,undefined);assert.equal(s.save.party[3].gauges[2],50);assert.equal(s.save.party[3].awakeHours,20);assert.equal(s.save.party[3].foodHours,5);assert.ok(s.save.party[3].ticks>=elapsed);assert.equal(s.save.rooms[34].cells[23],56);
 const stopped=s.save.party[3].ticks;await wait(200);assert.equal((await state()).save.party[3].ticks,stopped);await key('F5');await wait(2300);await p.reload({waitUntil:'load'});await ready();await key('c');await wait(300);s=await state();assert.equal(s.save.party[3].ticks,stopped);assert.equal(s.save.party[3].bed,undefined);
 const sleeper="$save['active']=0;$save['party'][3]['state']=16;$save['party'][3]['bed']=57;$save['party'][3]['ticks']=321.0;$save['rooms'][34]['cells'][23]=$save['rooms'][34]['cells'][24]=57;";
 await setup(sleeper+"$save['party'][3]['position']=array_search(62,$save['map'],true);");await select(7);await p.mouse.click(192,312);await wait(200);s=await state();assert.equal(s.save.party[3].state,0);assert.equal(s.save.map[s.save.party[3].position],62);assert.equal(s.save.rooms[34].cells[23],56);
 await setup(sleeper+"$save['party'][0]['bed']=57;");await select(7);await p.mouse.click(192,312);await wait(200);s=await state();assert.equal(s.save.party[0].bed,undefined);assert.equal(s.save.party[3].state,16);assert.equal(s.save.party[3].bed,57);assert.equal(s.save.rooms[34].cells[23],56);
 await setup(sleeper+"unset($save['party'][3]['bed']);");await select(7);await p.mouse.click(192,312);await wait(200);s=await state();assert.equal(s.mode,'normal');assert.equal(s.save.party[3].state,16);assert.equal(s.save.rooms[34].cells[23],57);
 assert.equal(errors.length,0,errors.join('\n'));console.log('Manual wake browser OK: real sleep/bed, survivor switch, keyboard wake, retained S/fatigue/timer, cleared bed saved, remote target, first-match rule and bounded orphan-bed rejection.');
 }finally{await b.close();}
})().catch(e=>{console.error(e);process.exit(1);});
