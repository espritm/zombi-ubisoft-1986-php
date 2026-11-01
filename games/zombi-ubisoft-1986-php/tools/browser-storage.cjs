// Controlled storage fixtures; all deposits, retrievals and persistence use actual UI.
const puppeteer=require('puppeteer'),assert=require('node:assert/strict');
(async()=>{
 const b=await puppeteer.launch({headless:true,args:['--no-sandbox','--use-gl=swiftshader','--enable-unsafe-swiftshader','--autoplay-policy=no-user-gesture-required']});
 try{
 const p=await b.newPage();await p.setViewport({width:960,height:600});const errors=[];
 p.on('pageerror',e=>errors.push(e.message));p.on('console',m=>{if(/CRASH|BOOT FAILED|Fatal error|Uncaught/.test(m.text()))errors.push(m.text());});
 const wait=ms=>new Promise(r=>setTimeout(r,ms)),key=async k=>{await p.keyboard.down(k);await wait(45);await p.keyboard.up(k);await wait(65);};
 const ready=async()=>{await p.waitForFunction(()=>document.getElementById('loader')?.style.display==='none',{timeout:180000});await p.mouse.click(480,100);};
 const php=code=>p.evaluate(code=>window.JumPHP.php(`$s=$GLOBALS['app']->engine->scenes->current();$w=(new ReflectionProperty($s,'world'))->getValue($s);${code}`),code);
 const state=async()=>{await php(`file_put_contents('/tmp/storage.json',json_encode(['save'=>$w->export(),'mode'=>$w->mode,'action'=>$w->action,'choices'=>$w->containerChoices()]));`);return p.evaluate(()=>JSON.parse(window.JumPHP.FS.readFile('/tmp/storage.json',{encoding:'utf8'})));};
 const select=async action=>{const s=await state();let n=(action-s.action+18)%18,k=n<=9?'ArrowDown':'ArrowUp';if(n>9)n=18-n;for(let i=0;i<n;i++)await key(k);await key('Enter');};
 const cell=async i=>{await p.mouse.click((16+i%10*16)*3,(72+Math.floor(i/10)*16)*3);await wait(150);};
 const put=async target=>{await select(2);await key('Enter');await cell(target===29?26:24);};
 const open=async target=>{await select(1);await cell(target===29?26:24);assert.equal((await state()).mode,'container');};
 await p.goto(process.argv[2],{waitUntil:'load'});await ready();console.log('Browser runtime ready');await key('Enter');await wait(300);
 assert.equal((await state()).save.version,'0.18.0');const base=Buffer.from(JSON.stringify((await state()).save)).toString('base64');
 const setup=extra=>php(`$save=json_decode(base64_decode('${base}'),true);$save['electricity']=true;$save['rooms'][62]['cells'][24]=23;$save['rooms'][62]['cells'][26]=$save['rooms'][62]['cells'][27]=29;$save['party'][3]['bag']=[30,0,0,0];$save['party'][3]['held']=0;$save['containers'][23]=[0,0];$save['containers'][29]=[0,0,0,0,0];$save['containers'][30]=[37,38,0,0,43];${extra||''}if(!$w->restore($save))throw new Exception('Fixture refused');`);
 for(const target of [23,29]){
  await setup();await put(target);let s=await state();assert.equal(s.save.containers[target][0],30);assert.equal(s.save.party[3].bag[0],0);
  await key('F5');await wait(2300);await p.reload({waitUntil:'load'});await ready();await key('c');await wait(300);await open(target);
  if(target===29){await p.screenshot({path:process.argv[3]});await key('ArrowRight');}
  await key('Enter');s=await state();assert.equal(s.save.party[3].bag[0],30);assert.equal(s.save.containers[target][0],0);assert.deepEqual(s.save.containers[30],[37,38,0,0,43]);
 }
 for(const id of [27,28]){
  await setup(`$save['party'][3]['bag']=[0,0,0,0];foreach([24,25,34,35] as $i)$save['rooms'][62]['cells'][$i]=${id};$save['containers'][${id}][0]=33;`);
  const contents=(await state()).save.containers[id];await open(id);assert.equal((await state()).choices[0],id);await key('Enter');let s=await state();assert.equal(s.save.party[3].bag[0],id);assert.ok(!s.save.rooms[62].cells.includes(id));
  await put(id);s=await state();assert.ok(s.save.rooms[62].cells.includes(id));assert.deepEqual(s.save.containers[id],contents);
 }
 await setup("$save['party'][3]['bag']=[43,0,0,0];$save['containers'][29]=[33,45,48,50,0];");await put(29);let s=await state();assert.equal(s.save.party[3].bag[0],43);assert.deepEqual(s.save.containers[29],[33,45,48,50,0]);
 await setup("$save['party'][3]['bag']=[45,0,0,0];$save['containers'][29]=[33,0,48,50,43];");await put(29);s=await state();assert.deepEqual(s.save.containers[29],[33,45,48,50,43]);
 await open(29);await key('ArrowLeft');await key('Enter');s=await state();assert.equal(s.save.party[3].bag[0],43);assert.equal(s.save.containers[29][4],0);
 await setup("$save['party'][3]['bag']=[43,33,0,0];$save['party'][3]['held']=43;$save['party'][3]['used']=43;");await select(2);for(let i=0;i<3;i++)await key('ArrowRight');await key('Enter');await cell(24);s=await state();assert.deepEqual(s.save.party[3].bag,[0,33,0,0]);assert.equal(s.save.party[3].held,43);assert.equal(s.save.party[3].used,0);
 for(const item of [54,55]){await setup(`$save['party'][3]['bag']=[${item},0,0,0];`);await select(2);await key('Enter');s=await state();assert.equal(s.mode,'normal');assert.equal(s.save.party[3].bag[0],item);assert.deepEqual(s.save.containers[23],[0,0]);}
 for(const [bag,held,allowed] of [[[43,33,45,48],34,false],[[43,33,45,48],0,false],[[43,33,45,0],34,false],[[43,33,45,0],0,true],[[43,33,0,0],34,true]]){
  await setup(`$save['party'][3]['bag']=[${bag.join(',')}];$save['party'][3]['held']=${held};`);await select(1);s=await state();assert.equal(s.mode,allowed?'object':'normal');assert.deepEqual(s.save.party[3].bag,bag);
 }
 assert.equal(errors.length,0,errors.join('\n'));console.log('Storage browser OK: nested bodies in cupboard/body, save/reload, portable containers, four-slot deposit limit, fifth rescued item recovered, duplicate held item, rejected food/drink and two-free-slot guard.');
 }finally{await b.close();}
})().catch(e=>{console.error(e);process.exit(1);});
