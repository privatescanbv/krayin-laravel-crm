// Expected labels/order verified against the four local CRM boards on 3 November 2026.
async function checkPipelines(page) {
  const base = 'http://127.0.0.1:8765/';
  const key = 'privatescan-design-v1';
  const expected = {
    orders: {
      Privatescan: ['Order bevestigen', 'Ingepland, wacht op akkoord', 'Order akkoord', 'Wachten op uitvoering', 'Uitgevoerd, wachten op rapporten', 'Rapporten vertalen', 'Gewonnen', 'Verloren'],
      Herniapoli: ['Order bevestigen', 'Ingepland', 'Order akkoord', 'Wachten op uitvoering', 'Uitgevoerd, wachten op operatieverslag', 'Rapporten vertalen', 'Gewonnen', 'Verloren'],
    },
    sales: {
      Privatescan: ['In behandeling', 'Met succes afgerond', 'Niet succesvol afgerond'],
      Herniapoli: ['Onderzoek via Privatescan', 'Casus bij arts ter beoordeling', 'Beoordeling gereed', 'Patient bedenktijd', 'Gepland voor aanvullend onderzoek', 'Wachten op zorgverzekeraar', 'Behandeling bevestigen', 'Behandeling gepland', '1e Nazorg', '2e Nazorg', 'Gepland voor fysieke nacontrole', 'Met succes afgerond', 'Niet succesvol afgerond'],
    },
  };
  const equal = (actual, wanted, label) => {
    if (JSON.stringify(actual) !== JSON.stringify(wanted)) throw new Error(`${label}: ${JSON.stringify(actual)}`);
  };
  await page.goto(base);
  const backup = await page.evaluate(key => localStorage.getItem(key), key);
  try {
    for (const module of ['sales', 'orders']) {
      await page.goto(`${base}#${module}`);
      for (const pipeline of ['Privatescan', 'Herniapoli']) {
        const lanes = expected[module][pipeline];
        await page.locator(`[data-pipeline="${pipeline}"]`).click();
        await page.locator('#closed').uncheck();
        equal(await page.locator('.column-title strong').allTextContents(), lanes.slice(0, -2), `${module}/${pipeline} open board`);
        await page.locator('#closed').check();
        equal(await page.locator('.column-title strong').allTextContents(), lanes, `${module}/${pipeline} full board`);
        await page.locator('#create').click();
        await page.waitForURL(/new$/);
        await page.locator('#editor-page').waitFor();
        equal(await page.locator('[name=stage] option').allTextContents(), lanes, 'Create stages');
        await page.locator('[name=title]').fill('Pipelinecontrole');
        await page.locator('[name=person]').fill('Pipelinevoorbeeld');
        await page.locator('[name=amount]').fill('100');
        await page.locator('[name=stage]').selectOption(lanes[1]);
        await page.locator('#save').click();
        await page.waitForURL(/algemeen$/);
        await page.locator('.record-toolbar').waitFor();
        equal(await page.locator('[data-stage]').allTextContents(), lanes.slice(0, -2), 'Detail stages');
        equal(await page.locator('[data-terminal-stage] option').allTextContents(), ['Gewonnen/Verloren', ...lanes.slice(-2)], 'Closing stages');
        await page.locator('[data-edit-record]').first().click();
        await page.waitForURL(/edit$/);
        await page.locator('#editor-page').waitFor();
        equal(await page.locator('[name=stage] option').allTextContents(), lanes, 'Edit stages');
        const other = pipeline === 'Privatescan' ? 'Herniapoli' : 'Privatescan';
        await page.locator('[name=pipeline]').selectOption(other);
        equal(await page.locator('[name=stage] option').allTextContents(), expected[module][other], 'Changed pipeline stages');
        equal(await page.locator('[name=stage]').inputValue(), expected[module][other].includes(lanes[1]) ? lanes[1] : expected[module][other][0], 'Stage after pipeline change');
        await page.locator('#save').click();
        await page.waitForURL(/algemeen$/);
        await page.reload();
        equal(await page.locator('[data-stage]').allTextContents(), expected[module][other].slice(0, -2), 'Saved pipeline stages');
        await page.locator('[data-terminal-stage]').selectOption(expected[module][other].at(-1));
        await page.reload();
        equal(await page.locator('.stage-pill').innerText(), expected[module][other].at(-1), 'Saved closing stage');
        await page.locator('[data-delete-record]').click();
        await page.locator('#confirm-delete').click();
        await page.waitForURL(`${base}#${module}`);
        await page.locator('#overview').waitFor();
      }
    }
    const legacy = [
      { id: 'legacy-sales', type: 'sales', pipeline: 'Privatescan', stage: 'Beoordeling gereed' },
      { id: 'legacy-won', type: 'sales', pipeline: 'Herniapoli', stage: 'Gewonnen' },
      { id: 'legacy-planned', type: 'orders', pipeline: 'Herniapoli', stage: 'Ingepland, wacht op akkoord' },
      { id: 'legacy-report', type: 'orders', pipeline: 'Herniapoli', stage: 'Uitgevoerd, wachten op rapporten' },
    ].map(r => ({ ...r, person: 'Bestaande voorbeeldpersoon', title: 'Bewaarde titel', number: r.id, email: 'test@example.com', phone: '', owner: 'Sanne', amount: 123, date: '', payment: 'Niet betaald', description: 'Bewaren', combine: true }));
    await page.evaluate(({ key, legacy }) => localStorage.setItem(key, JSON.stringify(legacy)), { key, legacy });
    for (const [i, wanted] of ['In behandeling', 'Met succes afgerond', 'Ingepland', 'Uitgevoerd, wachten op operatieverslag'].entries()) {
      const r = legacy[i];
      await page.goto(`${base}#${r.type}/${r.id}/algemeen`);
      await page.reload();
      equal(await page.locator('.stage-pill').innerText(), wanted, 'Legacy stage migration');
      equal(await page.locator('.contact-title h2').innerText(), r.person, 'Existing record retained');
    }
    return { result: 'passed', pipelines: 4, legacyRecords: 4 };
  } finally {
    await page.evaluate(({ key, backup }) => backup === null ? localStorage.removeItem(key) : localStorage.setItem(key, backup), { key, backup });
    await page.goto(`${base}#orders`);
    await page.reload();
  }
}
