// Mounts the real <v-date-picker> from its blade file in a bare page (no Laravel needed).
// Run: cd packages/Webkul/Admin && npm test
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../..', import.meta.url));
const blade = readFileSync(`${root}src/Resources/views/components/flat-picker/date.blade.php`, 'utf8');
const template = blade.match(/id="v-date-picker-template"\s*>([\s\S]*?)<\/script>/)[1].replaceAll('@{{', '{{');
const component = blade.match(/<script type="module">([\s\S]*?)<\/script>/)[1];

async function mountPicker(page, value = '') {
    await page.setContent(`<div id="app"></div><script type="text/x-template" id="v-date-picker-template">${template}</script>`);
    await page.addScriptTag({ path: `${root}node_modules/vue/dist/vue.global.js` });
    await page.addScriptTag({ path: `${root}node_modules/flatpickr/dist/flatpickr.js` });
    await page.evaluate(({ component, value }) => {
        window.Flatpickr = window.flatpickr;
        // Save button above the field like in the CRM, so the error line disappearing doesn't shift it mid-click
        window.submits = 0;
        window.app = Vue.createApp({
            template: `<form @submit.prevent="submitted"><button>Opslaan</button><v-date-picker value="${value}"><input name="date_of_birth"></v-date-picker></form>`,
            methods: { submitted: () => window.submits++ },
        });
        new Function(component)();
        app.mount('#app');
    }, { component, value });

    const typed = page.locator('#app input:not([type=hidden])');
    const hidden = page.locator('input[name=date_of_birth]');

    return {
        hidden,
        error: page.locator('[data-date-picker-error]'),
        submits: () => page.evaluate(() => window.submits),
        typeAndSave: async (text) => {
            await typed.fill(text);
            await page.getByRole('button', { name: 'Opslaan' }).click();
        },
        type: async (text) => {
            await typed.fill(text);
            await typed.press('Enter');
        },
    };
}

for (const [input, expected] of [
    ['01-01-72', '1972-01-01'],
    ['010172', '1972-01-01'],
    ['26-03-26', '2026-03-26'],
    ['26-03-1972', '1972-03-26'],
    ['26031972', '1972-03-26'],
]) {
    test(`parses ${input} as ${expected}`, async ({ page }) => {
        const picker = await mountPicker(page);

        await picker.type(input);

        await expect(picker.hidden).toHaveValue(expected);
        await expect(picker.error).toHaveCount(0);
    });
}

for (const input of ['abc', '31-02-72', '26-13-1972', '26-03-0072']) {
    test(`shows an error and keeps the previous date for ${input}`, async ({ page }) => {
        const picker = await mountPicker(page, '1972-03-26');

        await picker.type(input);

        await expect(picker.error).toContainText(`"${input}" is geen geldige datum`);
        await expect(picker.hidden).toHaveValue('1972-03-26');
    });
}

test('clears the error once a valid date is entered', async ({ page }) => {
    const picker = await mountPicker(page);

    await picker.type('abc');
    await expect(picker.error).toHaveCount(1);

    await picker.type('01-01-72');
    await expect(picker.error).toHaveCount(0);
    await expect(picker.hidden).toHaveValue('1972-01-01');
});

test('blocks saving while the date is invalid, also when clicking save straight after typing', async ({ page }) => {
    const picker = await mountPicker(page, '1972-03-26');

    await picker.typeAndSave('01-01-972655');
    await expect(picker.error).toContainText('"01-01-972655" is geen geldige datum');
    expect(await picker.submits()).toBe(0);

    await picker.type('abc');
    await page.getByRole('button', { name: 'Opslaan' }).click();
    expect(await picker.submits()).toBe(0);

    await picker.typeAndSave('01-01-72');
    await expect(picker.error).toHaveCount(0);
    expect(await picker.submits()).toBe(1);
});
