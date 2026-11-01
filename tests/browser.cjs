const { chromium } = require('C:/Users/muham/AppData/Local/hermes/hermes-agent/node_modules/playwright');
const assert = require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Users/muham/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe'}); const page=await browser.newPage(); const errors=[]; page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:8013/login');
 if (!process.env.DEMO_PASSWORD) throw new Error('DEMO_PASSWORD is required');
 await page.locator('[name=email]').fill('operator@example.test'); await page.locator('[name=password]').fill(process.env.DEMO_PASSWORD); await page.getByRole('button',{name:'Masuk',exact:true}).click(); await page.waitForURL('http://127.0.0.1:8013/dashboard');
 await page.getByRole('link',{name:'Kegiatan',exact:true}).click();
 let form=page.locator('form[action="/kegiatan"]');
 for(const [name,value] of Object.entries({name:'Demo verifikasi MOORA',organization:'Data uji',venue:'Aula uji',start_date:'2026-10-18',end_date:'2026-10-19'})) await form.locator(`[name=${name}]`).fill(value);
 await form.getByRole('button',{name:'Tambah kegiatan'}).click(); await page.waitForURL(/event_id=/); const event=new URL(page.url()).searchParams.get('event_id');
 for(let i=1;i<=15;i++){form=page.locator(`form[action="/kegiatan/${event}/materi"]`); await form.locator('[name=name]').fill(`Materi uji ${i}`); await form.locator('[name=sequence]').fill(String(i)); await form.getByRole('button').click(); await page.waitForLoadState('networkidle');}
 form=page.locator(`form[action="/kegiatan/${event}/peserta"]`); await form.locator('[name=name]').fill('Peserta demonstrasi'); await form.locator('[name=sequence]').fill('1'); await form.getByRole('button').click(); await page.waitForLoadState('networkidle');
 for(const kind of ['absensi','pretest','posttest','keaktifan','etika','pemahaman']){
   await page.goto(`http://127.0.0.1:8013/${kind}?event_id=${event}`);
   const ids=await page.locator('select[name=material_id] option').evaluateAll(nodes=>nodes.map(n=>n.value));
   for(const id of ids){await page.goto(`http://127.0.0.1:8013/${kind}?event_id=${event}&material_id=${id}`); const scoreform=page.locator(`form[action="/input/${event}/${kind}"]`); if(kind==='absensi') await scoreform.locator('select').selectOption('present'); else for(const input of await scoreform.locator('input[type=number]').all()) await input.fill('18'); await scoreform.getByRole('button').click(); await page.waitForLoadState('networkidle'); assert.equal(await page.locator('[role=alert]').count(),0);}
 }
 await page.goto(`http://127.0.0.1:8013/hasil?event_id=${event}`); await page.getByRole('button',{name:'Hitung MOORA',exact:true}).click(); await page.waitForLoadState('networkidle'); assert.match(await page.locator('main').innerText(),/Status: valid/);
 page.on('dialog',d=>d.accept()); await page.getByRole('button',{name:'Finalisasi',exact:true}).click(); await page.waitForLoadState('networkidle'); assert.match(await page.locator('main').innerText(),/Status: locked/);
 await page.locator('[name=reason]').fill('Verifikasi koreksi'); await page.getByRole('button',{name:'Buka kunci',exact:true}).click(); await page.waitForLoadState('networkidle'); assert.match(await page.locator('main').innerText(),/Status: stale/);
 await page.getByRole('button',{name:'Hitung MOORA',exact:true}).click(); await page.waitForLoadState('networkidle');
 const visited=[];
 for(const width of [390,1440]){ await page.setViewportSize({width,height:900}); for(const path of ['', 'kegiatan','absensi','pretest','posttest','keaktifan','etika','pemahaman','hasil','kriteria']) {await page.goto(`http://127.0.0.1:8013/${path}?event_id=${event}`); assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`overflow ${path} ${width}`); visited.push(`${path||'dashboard'}@${width}`);} await page.locator('#menu').click(); assert.equal(await page.locator('#menu').getAttribute('aria-expanded'),width===390?'true':'false'); if(width===390){await page.keyboard.press('Escape'); assert.equal(await page.locator('#menu').getAttribute('aria-expanded'),'false');} }
 await page.getByRole('button',{name:'Keluar',exact:true}).click(); await page.waitForURL('**/login'); assert.deepEqual(errors,[]); console.log(JSON.stringify({event_id:event,visited,workflow:'login, CRUD creation, 60 input saves, calculate, finalize, unlock, recalculate, menu, escape, logout passed',console_errors:errors})); await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
