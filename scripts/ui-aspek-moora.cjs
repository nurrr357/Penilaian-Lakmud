const { chromium } = require('C:/Users/muham/AppData/Local/hermes/cache/scratch/lakmud-ui-tools/node_modules/playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const assert = require('node:assert/strict');

const root = 'D:/huda/project/laravel/skripsi/lakmud-penilaian';
const php = 'C:/laragon/bin/php/php-8.5.10-nts-Win32-vs17-x64/php.exe';
const base = 'http://127.0.0.1:8013';
const pages = {
    absensi: ['attendance', 'present', 'absent'],
    pretest: ['pretest', '18', '19'],
    posttest: ['posttest', '18', '19'],
    keaktifan: ['activity', '18', '19'],
    etika: ['ethics', '18', '19'],
    pemahaman: ['understanding', '18', '19'],
};

(async () => {
    const cookie = JSON.parse(execFileSync(php, [root + '/scripts/ui-session.php'], { cwd: root, encoding: 'utf8' }));
    const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 960 } });
    await context.addCookies([{ name: cookie.name, value: cookie.value, url: base }]);
    const page = await context.newPage();
    const errors = [];
    const evidence = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    page.on('dialog', dialog => dialog.accept());

    const go = async path => {
        const response = await page.goto(base + path);
        assert.equal(response.status(), 200, path);
    };
    const submit = async selector => {
        await Promise.all([page.waitForNavigation(), page.locator(selector).click()]);
    };

    await go('/kegiatan');
    await page.locator('[data-modal-open="create"]').click();
    await page.locator('#create [name="name"]').fill('TEST ASPEK MOORA BROWSER');
    await page.locator('#create [name="organization"]').fill('TEST');
    await page.locator('#create [name="venue"]').fill('TEST');
    await page.locator('#create [name="start_date"]').fill('2026-10-01');
    await page.locator('#create [name="end_date"]').fill('2026-10-02');
    await submit('#create button:not([type="button"])');
    const event = new URL(page.url()).searchParams.get('event_id');
    assert(event);
    const eventRow = page.locator('tbody tr').filter({ hasText: 'TEST ASPEK MOORA BROWSER' });
    assert.equal(await eventRow.locator('a', { hasText: 'Materi' }).count(), 0);
    assert.equal(await eventRow.locator('a', { hasText: 'Peserta' }).count(), 0);
    assert.equal(await page.locator('nav a[href^="/materi"]').count(), 1);
    assert.equal(await page.locator('nav a[href^="/peserta"]').count(), 1);
    evidence.push('Kegiatan actions omit Materi/Peserta while sidebar links remain');
    const csrf = await page.locator('form[action="/logout"] [name="_token"]').inputValue();

    for (let i = 1; i <= 15; i++) {
        const response = await context.request.post(`${base}/kegiatan/${event}/materi`, { form: { _token: csrf, name: `TEST Materi ${i}`, sequence: i, is_active: 1 } });
        assert.equal(response.status(), 200);
    }
    for (let i = 1; i <= 12; i++) {
        const response = await context.request.post(`${base}/kegiatan/${event}/peserta`, { form: { _token: csrf, name: `TEST Peserta ${String(i).padStart(2, '0')}`, sequence: i } });
        assert.equal(response.status(), 200);
    }

    let participantIds;
    let materialIds;
    for (const [path, [field, firstValue, editedValue]] of Object.entries(pages)) {
        await go(`/${path}?event_id=${event}`);
        assert.equal(await page.locator('tbody tr').count(), 10);
        assert.equal(await page.locator('nav a, nav [aria-current="page"]').filter({ hasText: path === 'absensi' ? 'Absensi' : path[0].toUpperCase() + path.slice(1) }).count() > 0, true);
        const firstButton = page.locator('tbody tr').first().locator('[data-modal-open]');

        await firstButton.click();
        const dialog = page.locator('dialog[open]');
        assert.equal(await dialog.count(), 1);
        const firstControl = dialog.locator('input:not([type="hidden"]), select').first();
        const lastControl = dialog.locator('[data-modal-close]');
        await firstControl.focus(); await page.keyboard.press('Shift+Tab');
        assert.equal(await lastControl.evaluate(el => el === document.activeElement), true);
        await page.keyboard.press('Tab');
        assert.equal(await firstControl.evaluate(el => el === document.activeElement), true);
        await dialog.locator('[data-modal-close]').click();
        assert.equal(await page.locator('dialog[open]').count(), 0);

        await firstButton.click();
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('dialog[open]').count(), 0);
        assert.equal(await page.evaluate(() => document.activeElement.hasAttribute('data-modal-open')), true);

        await firstButton.click();
        await page.locator('dialog[open]').evaluate(dialog => dialog.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: 0, clientY: 0 })));
        assert.equal(await page.locator('dialog[open]').count(), 0);

        await firstButton.click();
        const control = page.locator('dialog[open] [name^="rows["]');
        if (field === 'attendance') await control.selectOption(firstValue); else await control.fill(firstValue);
        await submit('dialog[open] button:not([type="button"])');
        assert((await page.locator('tbody tr').first().innerText()).includes(field === 'attendance' ? 'Hadir' : '18.00'));

        await page.locator('tbody tr').first().locator('[data-modal-open]').click();
        const editControl = page.locator('dialog[open] [name^="rows["]');
        if (field === 'attendance') await editControl.selectOption(editedValue); else await editControl.fill(editedValue);
        await submit('dialog[open] button:not([type="button"])');
        assert((await page.locator('tbody tr').first().innerText()).includes(field === 'attendance' ? 'Tidak hadir' : '19.00'));

        await page.getByRole('link', { name: 'Berikutnya', exact: true }).click();
        await page.waitForLoadState();
        assert.equal(await page.locator('tbody tr').count(), 2);
        assert((await page.locator('tbody').innerText()).includes('TEST Peserta 12'));

        await page.setViewportSize({ width: 390, height: 844 });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        await page.setViewportSize({ width: 1440, height: 960 });
        evidence.push(`${path}: add, edit, Batal, Escape/focus return, backdrop close, Tab and Shift+Tab wrap, pagination 10+2, mobile 390px`);
    }

    await go(`/keaktifan?event_id=${event}`);
    await page.locator('tbody tr').nth(1).locator('[data-modal-open]').click();
    await page.locator('dialog[open] [name^="rows["]').fill('20.001');
    await page.locator('dialog[open] form').evaluate(form => { form.noValidate = true; });
    await submit('dialog[open] button:not([type="button"])');
    assert.equal(await page.locator('dialog[open]').count(), 1);
    assert.equal(await page.locator('dialog[open] [name^="rows["]').inputValue(), '20.001');
    assert.equal(await page.locator('dialog[open] [name^="rows["]').getAttribute('aria-invalid'), 'true');
    assert.equal(await page.locator('dialog[open] [name^="rows["]').evaluate(el => el === document.activeElement), true);
    assert.equal(await page.locator('dialog[open] .field-error').isVisible(), true);
    assert.equal(await page.locator('dialog[open] .error').count(), 1);
    await page.locator('dialog[open] [data-modal-close]').click();
    evidence.push('Keaktifan validation error retained and modal reopened');

    await go(`/pretest?event_id=${event}&per_page=50`);
    participantIds = await page.locator('dialog [name^="rows["]').evaluateAll(nodes => [...new Set(nodes.map(node => node.name.match(/rows\[(\d+)\]/)[1]))]);
    materialIds = await page.locator('select[name="material_id"] option').evaluateAll(nodes => nodes.map(node => node.value));
    for (const material of materialIds) {
        for (const [kind, [field]] of Object.entries(pages)) {
            const form = { _token: csrf, material_id: material };
            for (const id of participantIds) form[`rows[${id}][${field}]`] = field === 'attendance' ? 'present' : '18';
            const response = await context.request.post(`${base}/input/${event}/${kind}`, { form });
            assert.equal(response.status(), 200, `${kind}/${material}`);
        }
    }

    await go(`/perhitungan?event_id=${event}`);
    await submit('form[action$="/calculate"] button');
    await go(`/perhitungan?event_id=${event}`);
    assert((await page.locator('body').innerText()).includes('Status: valid'));
    assert.equal(await page.locator('.formula .katex').count(), 6);
    assert.equal(await page.locator('#moora-detail-title').innerText(), 'Detail Perhitungan MOORA');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    await go(`/hasil?event_id=${event}`);
    assert.equal(await page.locator('#ranking-title').innerText(), 'Peringkat MOORA');
    assert.equal(await page.locator('table tbody tr').count(), 10);
    await page.getByRole('link', { name: 'Berikutnya', exact: true }).click();
    await page.waitForLoadState();
    assert.equal(await page.locator('table tbody tr').count(), 2);
    evidence.push('MOORA calculation used all 12 participants across pagination (10+2)');
    evidence.push('KaTeX rendered 6 trusted static formulas; snapshot detail and ranking headings visible without overflow');

    await go(`/etika?event_id=${event}`);
    await page.locator('tbody tr').first().locator('[data-modal-open]').click();
    await page.locator('dialog[open] [name^="rows["]').fill('19');
    await submit('dialog[open] button:not([type="button"])');
    await go(`/perhitungan?event_id=${event}`);
    assert((await page.locator('body').innerText()).includes('Status: stale'));
    await submit('form[action$="/calculate"] button');
    await go(`/hasil?event_id=${event}`);
    await submit('form[action$="/finalize"] button');
    assert((await page.locator('body').innerText()).includes('Status hasil: locked'));

    await go(`/pemahaman?event_id=${event}`);
    assert.equal(await page.locator('[data-modal-open]:not(:disabled)').count(), 0);
    assert.equal(await page.locator('dialog').count(), 0);
    const lockedResponse = await context.request.post(`${base}/input/${event}/pemahaman`, {
        form: { _token: csrf, material_id: materialIds[0], [`rows[${participantIds[0]}][understanding]`]: '19' },
    });
    assert.equal(lockedResponse.status(), 200);
    assert(lockedResponse.url().includes('/pemahaman'));
    await go(`/pemahaman?event_id=${event}`);
    assert((await page.locator('tbody tr').first().innerText()).includes('18.00'));
    evidence.push('Edit invalidated result to stale; recalculation/finalization locked UI and rejected server mutation');

    await go(`/hasil?event_id=${event}`);
    await page.locator('input[name="reason"]').fill('Cleanup fixture browser');
    await submit('form[action$="/unlock"] button');
    await go(`/kegiatan?q=TEST+ASPEK+MOORA+BROWSER&per_page=50`);
    await submit(`form[action="/kegiatan/${event}"] button.danger`);
    assert.equal(await page.locator(`form[action="/kegiatan/${event}"]`).count(), 0);
    evidence.push('Labelled event fixture deleted through confirmed UI; cascade readback absent');

    assert.deepEqual(errors, []);
    const result = { event, user: cookie.user, evidence, errors };
    fs.writeFileSync('D:/huda/backups/lakmud-penilaian/browser-aspek-moora.json', JSON.stringify(result, null, 2));
    console.log(JSON.stringify(result, null, 2));
    await browser.close();
})().catch(error => {
    console.error(error);
    process.exit(1);
});
