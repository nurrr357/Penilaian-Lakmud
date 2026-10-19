const { chromium } = require('C:/Users/muham/AppData/Local/hermes/cache/scratch/lakmud-ui-tools/node_modules/playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const root = 'D:/huda/project/laravel/skripsi/lakmud-penilaian';
const php = 'C:/laragon/bin/php/php-8.5.10-nts-Win32-vs17-x64/php.exe';
const base = 'http://127.0.0.1:8013';
(async () => {
 const cookie = JSON.parse(execFileSync(php,[root+'/scripts/ui-session.php'],{cwd:root,encoding:'utf8'}));
 const browser = await chromium.launch({executablePath:'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:true});
 const context = await browser.newContext({viewport:{width:1440,height:960}});
 await context.addCookies([{name:cookie.name,value:cookie.value,url:base}]);
 const page = await context.newPage(); const errors=[]; const evidence=[];
 page.on('pageerror', e=>errors.push(e.message));
 page.on('dialog', d=>d.accept());
 const go = async path=>{const response=await page.goto(base+path);assert.equal(response.status(),200);};
 const submit = async selector=>{await Promise.all([page.waitForNavigation(),page.locator(selector).click()]);};
 const add = async (kind,values)=>{await page.locator('[data-modal-open="create"]').click();for(const [key,value] of Object.entries(values))await page.locator('#create [name="'+key+'"]').fill(String(value));await submit('#create button:not([type="button"])');assert.equal(await page.locator('dialog[open]').count(),0);};
 await go('/kegiatan');
 await page.locator('[data-modal-open="create"]').click();await page.keyboard.press('Escape');assert.equal(await page.locator('dialog[open]').count(),0);assert.equal(await page.evaluate(()=>document.activeElement.dataset.modalOpen),'create');evidence.push('Escape closes modal and returns focus');
 await page.locator('[data-modal-open="create"]').click();
 for(let i=0;i<9;i++){await page.keyboard.press('Tab');assert(await page.evaluate(()=>document.activeElement.closest('dialog')!==null));}
 await page.locator('#create [name="name"]').fill('TEST invalid event');await page.locator('#create [name="organization"]').fill('TEST');await page.locator('#create [name="venue"]').fill('TEST');await page.locator('#create [name="start_date"]').fill('2026-10-02');await page.locator('#create [name="end_date"]').fill('2026-10-01');await submit('#create button:not([type="button"])');assert.equal(await page.locator('dialog[open]').count(),1);await page.locator('#create [data-modal-close]').click();evidence.push('Event server validation reopens modal; Tab stays inside; cancel closes');
 await add('kegiatan',{name:'TEST UI TABLES fixture',organization:'TEST',venue:'TEST',start_date:'2026-10-01',end_date:'2026-10-02'});
 const event=new URL(page.url()).searchParams.get('event_id'); assert(event);
 const row=page.locator('tbody tr').filter({hasText:'TEST UI TABLES fixture'});await row.locator('[data-modal-open]').click();await page.locator('dialog[open] [name="venue"]').fill('TEST edited');await submit('dialog[open] button:not([type="button"])');assert(await page.locator('tbody').innerText().then(t=>t.includes('TEST edited')));evidence.push('Event create/edit persisted');
 for(const kind of ['materi','peserta']){
  await go('/'+kind+'?event_id='+event);
  const count=kind==='materi'?15:12;
  for(let i=1;i<=count;i++)await add(kind,{name:`TEST ${kind} ${String(i).padStart(2,'0')}`,sequence:i});
  assert.equal(await page.locator('tbody tr').count(),10);await page.getByRole('link',{name:'Berikutnya',exact:true}).click();await page.waitForLoadState();assert.equal(await page.locator('tbody tr').count(),count-10);
  await page.locator('tbody tr').first().locator('[data-modal-open]').click();await page.locator('dialog[open] [name="name"]').fill(`TEST ${kind} edited`);await submit('dialog[open] button:not([type="button"])');assert(new URL(page.url()).searchParams.get('page')==='2');assert((await page.locator('tbody').innerText()).includes('edited'));
  await page.locator('.table-filter [name="q"]').fill('edited');await submit('.table-filter button');assert.equal(await page.locator('tbody tr').count(),1);
  await page.locator('[data-modal-open="create"]').click();await page.locator('#create [name="name"]').fill('TEST duplicate');await page.locator('#create [name="sequence"]').fill('1');await submit('#create button:not([type="button"])');assert.equal(await page.locator('dialog[open]').count(),1);assert.equal(await page.locator('dialog[open] [name="name"]').inputValue(),'TEST duplicate');assert(await page.locator('dialog[open] .error').count());await page.locator('[data-modal-close]').first().click();
  evidence.push(kind+': create, edit page 2, search reset, server validation retained in reopened modal');
 }
 for(const path of ['/','/absensi','/pretest','/posttest','/keaktifan','/etika','/pemahaman','/peserta']){
  await go(path+'?event_id='+event);assert.equal(await page.locator('table').first().locator('tbody tr').count(),10);await page.getByRole('link',{name:'Berikutnya',exact:true}).click();await page.waitForLoadState();assert.equal(await page.locator('table').first().locator('tbody tr').count(),2);
  evidence.push(path+': 10 rows then 2 rows');
 }
 await go('/pretest?event_id='+event+'&page=2');await page.locator('[data-unsaved] input[type="number"]').first().fill('18');await submit('[data-unsaved] button');assert(new URL(page.url()).searchParams.get('page')==='2');assert.equal(await page.locator('[data-unsaved] input[type="number"]').first().inputValue(),'18.00');evidence.push('Page 2 input persists and returns to page 2');
 for(const path of ['/kegiatan','/materi','/peserta','/','/absensi','/pretest','/posttest','/keaktifan','/etika','/pemahaman','/hasil']){
  await page.setViewportSize({width:390,height:844});await go(path+'?event_id='+event);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));evidence.push(path+': mobile 390px no document overflow');
 }
 await page.setViewportSize({width:1440,height:960});
 await go('/pretest?event_id='+event+'&per_page=50');
 const ids=await page.locator('[data-unsaved] input[type="number"]').evaluateAll(nodes=>nodes.map(n=>n.name.match(/rows\[(\d+)\]/)[1]));
 const materials=await page.locator('select[name="material_id"] option').evaluateAll(nodes=>nodes.map(n=>n.value));
 const token=await page.locator('[data-unsaved] [name="_token"]').inputValue();
 for(const material of materials)for(const kind of ['absensi','pretest','posttest','keaktifan','etika','pemahaman']){
  const fields=kind==='absensi'?{attendance:'present'}:kind==='keaktifan'?{activity:'18'}:kind==='etika'?{ethics:'18'}:kind==='pemahaman'?{understanding:'18'}:{[kind]:'18'};
  const form={_token:token,material_id:material};for(const id of ids)for(const [field,value]of Object.entries(fields))form[`rows[${id}][${field}]`]=value;
  const response=await context.request.post(base+`/input/${event}/${kind}`,{form});assert.equal(response.status(),200);
 }
 await go('/hasil?event_id='+event+'&q=edited');await submit('form[action$="/calculate"] button');assert.equal(await page.locator('table tbody tr').count(),10);await page.getByRole('link',{name:'Berikutnya',exact:true}).click();await page.waitForLoadState();assert.equal(await page.locator('table tbody tr').count(),2);evidence.push('All-event MOORA: 12 alternatives despite prior filtered view; ranking 10+2');
 for(const kind of ['materi','peserta']){await go('/'+kind+'?event_id='+event);await submit('tbody tr:first-child form button');assert.equal(await page.locator('tbody').innerText().then(t=>t.includes('TEST '+kind+' 01')),false);evidence.push(kind+': delete readback absent');}
 await go('/kegiatan?q=TEST+UI+TABLES');await submit('tbody tr:first-child form button');assert(!(await page.locator('tbody').innerText()).includes('TEST UI TABLES fixture'));evidence.push('Event delete readback absent');
 assert.deepEqual(errors,[]);
 fs.writeFileSync('D:/huda/backups/lakmud-penilaian/browser-ui-tables.json',JSON.stringify({event,user:cookie.user,evidence,errors},null,2));
 console.log(JSON.stringify({event,user:cookie.user,evidence,errors},null,2));await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
