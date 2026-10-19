const { chromium } = require('C:/Users/muham/AppData/Local/hermes/hermes-agent/node_modules/playwright');
const assert = require('node:assert/strict');
(async () => {
 const browser = await chromium.launch({headless:true, executablePath:'C:/Users/muham/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe'});
 const page = await browser.newPage(); const errors=[]; const evidence=[];
 page.on('pageerror', e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:8013/login');
 await page.locator('[name=email]').fill('operator@example.test');
 if (!process.env.DEMO_PASSWORD) throw new Error('DEMO_PASSWORD is required');
 await page.locator('[name=password]').fill(process.env.DEMO_PASSWORD);
 await page.getByRole('button',{name:'Masuk',exact:true}).click(); await page.waitForURL('http://127.0.0.1:8013/');
 await page.goto('http://127.0.0.1:8013/kegiatan');
 let form=page.locator('form[action="/kegiatan"]');
 for(const [name,value] of Object.entries({name:'Reconciliation disposable QA',organization:'Test fixture',venue:'QA',start_date:'2026-10-18',end_date:'2026-10-19'})) await form.locator(`[name=${name}]`).fill(value);
 await form.getByRole('button').click(); await page.waitForLoadState('networkidle');
 const event=new URL(page.url()).searchParams.get('event_id'); assert.ok(event);
 form=page.locator(`form[action="/kegiatan/${event}"]`).filter({has:page.locator('[name=name]')});
 await form.locator('[name=name]').fill('Reconciliation edited QA'); await form.getByRole('button').click(); await page.waitForLoadState('networkidle'); assert.match(await page.locator('main').innerText(),/Reconciliation edited QA/); evidence.push('event create/edit persisted');
 for(const type of ['materi','peserta']) {
   form=page.locator(`form[action="/kegiatan/${event}/${type}"]`); await form.locator('[name=name]').fill(`QA ${type}`); await form.locator('[name=sequence]').fill('1'); await form.getByRole('button').click(); await page.waitForLoadState('networkidle');
   const detail=page.locator('details').filter({hasText:`QA ${type}`}); await detail.locator('summary').click();
   form=detail.locator('form').filter({has:page.locator('[name=name]')}); await form.locator('[name=name]').fill(`Edited ${type}`); await form.getByRole('button').click(); await page.waitForLoadState('networkidle'); assert.match(await page.locator('main').innerText(),new RegExp(`Edited ${type}`)); evidence.push(`${type} create/edit persisted`);
 }
 form=page.locator(`form[action="/kegiatan/${event}/materi"]`); await form.locator('[name=name]').fill('Duplicate'); await form.locator('[name=sequence]').fill('1'); await form.getByRole('button').click(); await page.waitForLoadState('networkidle'); assert.equal(await page.locator('[role=alert]').count(),1); evidence.push('duplicate sequence visible server error');
 await page.goto(`http://127.0.0.1:8013/pretest?event_id=${event}`);
 form=page.locator(`form[action="/input/${event}/pretest"]`); await form.locator('input[type=number]').fill('9.99'); await form.evaluate(f=>f.noValidate=true); await form.getByRole('button').click(); await page.waitForLoadState('networkidle'); assert.equal(await page.locator('[role=alert]').count(),1); evidence.push('out-of-range score visible server error');
 await page.goto(`http://127.0.0.1:8013/hasil?event_id=${event}`); assert.equal(await page.getByRole('button',{name:'Hitung MOORA',exact:true}).isDisabled(),true); evidence.push('incomplete calculation disabled');
 await page.setViewportSize({width:390,height:844}); await page.goto(`http://127.0.0.1:8013/peserta?event_id=${event}`);
 await page.keyboard.press('Tab'); assert.equal(await page.locator(':focus').getAttribute('href'),'#content');
 assert.notEqual(await page.locator(':focus').evaluate(e=>getComputedStyle(e).outlineStyle),'none');
 await page.keyboard.press('Tab'); assert.equal(await page.locator(':focus').getAttribute('id'),'menu'); await page.keyboard.press('Enter'); assert.equal(await page.locator('#menu').getAttribute('aria-expanded'),'true'); await page.keyboard.press('Escape'); assert.equal(await page.locator('#menu').getAttribute('aria-expanded'),'false'); evidence.push('Tab focus outline, Enter mobile menu, Escape close');
 const contrast=await page.evaluate(()=>{
   const lum=c=>{const a=c.match(/[\d.]+/g).slice(0,3).map(Number).map(v=>v/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4);return a[0]*.2126+a[1]*.7152+a[2]*.0722;};
   const rows=[]; for(const e of document.querySelectorAll('h1,h2,h3,p,label,summary,button,a,th,td')) {
    if(!e.checkVisibility() || !e.textContent.trim() || e.disabled) continue;
    let n=e,bg=''; while(n){bg=getComputedStyle(n).backgroundColor;if(bg!=='rgba(0, 0, 0, 0)' && bg!=='transparent')break;n=n.parentElement;} if(!n)bg='rgb(255,255,255)';
    const s=getComputedStyle(e),a=lum(s.color),b=lum(bg),ratio=(Math.max(a,b)+.05)/(Math.min(a,b)+.05);rows.push({tag:e.tagName,ratio,required:parseFloat(s.fontSize)>=24 || (parseFloat(s.fontSize)>=18.66 && parseInt(s.fontWeight)>=700)?3:4.5});
   } return rows;
 }); assert.ok(contrast.every(x=>x.ratio>=x.required),JSON.stringify(contrast.filter(x=>x.ratio<x.required))); evidence.push(`computed contrast ${contrast.length} visible text elements passed`);
 await page.setViewportSize({width:1440,height:900}); await page.goto(`http://127.0.0.1:8013/kegiatan?event_id=${event}`);
 for(const type of ['materi','peserta']) {
  let detail=page.locator('details').filter({hasText:`Edited ${type}`}); await detail.locator('summary').click();
  page.once('dialog',d=>d.dismiss()); await detail.getByRole('button',{name:`Hapus ${type}`}).click(); assert.equal(await detail.count(),1);
  page.once('dialog',d=>d.accept()); await detail.getByRole('button',{name:`Hapus ${type}`}).click(); await page.waitForLoadState('networkidle'); assert.equal(await page.locator('details').filter({hasText:`Edited ${type}`}).count(),0); evidence.push(`${type} delete cancel/confirm persisted`);
 }
 page.once('dialog',d=>d.accept()); await page.getByRole('button',{name:'Hapus kegiatan',exact:true}).click(); await page.waitForLoadState('networkidle'); assert.equal(await page.locator(`select[name=event_id] option[value="${event}"]`).count(),0); evidence.push('event delete persisted');
 assert.deepEqual(errors,[]); console.log(JSON.stringify({event_id:event,evidence,contrast,console_errors:errors})); await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
