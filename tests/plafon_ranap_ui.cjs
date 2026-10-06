const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const page = fs.readFileSync(require('node:path').join(__dirname, '../kunjungan_ranap.php'), 'utf8');
const script = page.match(/<script>([\s\S]*?)<\/script>/)[1].replace(/<\?php[\s\S]*?\?>/g, '"test-token"');
const handlers = {};
let request;
let statusText;
let rowHtml;
let rowOver;
const chain = {
    ready() { return this; },
    on(event, selector, handler) { handlers[event] = handler; return this; },
    closest() { return this; }, find() { return this; },
    removeClass() { return this; }, addClass() { return this; },
    text(value) { statusText = value; return this; },
    children() { return this; }, eq() { return this; },
    html(value) { rowHtml = value; return this; },
    toggleClass(name, value) { rowOver = value; return this; }
};
const jquery = () => chain;
jquery.ajax = options => { request = options; };
const ctx = {
    $, // assigned below
    Intl, console,
    localStorage: { getItem: () => null },
    document: { querySelectorAll: () => [], documentElement: { setAttribute() {}, classList: { add() {}, remove() {} } } }
};
function $(value) { return jquery(value); }
ctx.$ = $;
ctx.$.ajax = jquery.ajax;
vm.createContext(ctx);
vm.runInContext(script, ctx);
assert.match(ctx.renderSelisihHtml(1500000, 2500000), /text-success/);
assert.match(ctx.renderSelisihHtml(1500000, 1000000), /text-danger/);
assert.match(ctx.renderSelisihHtml(1500000, 0), /text-danger/);
assert.match(ctx.renderSelisihHtml(0, 0), /text-success/);
assert.match(ctx.renderSelisihHtml(1500000, null), />-</);
assert.match(ctx.renderPlafonInput('2026/10/06/000001', 2500000, false), /value="2\.500\.000"/);
assert.match(ctx.renderPlafonInput('2026/10/06/000001', null, true), / disabled/);
const input = { value: '2.500.000', disabled: false, dataset: { norawat: '2026/10/06/000001', saved: '1000000' } };
ctx._billingCache[input.dataset.norawat] = { estimasi_raw: 1500000 };
handlers.change.call(input);
assert.equal(request.data.nominal, '2500000');
assert.equal(input.disabled, true);
request.success({ success: true, plafon_raw: 2500000, has_plafon: true });
request.complete();
assert.equal(input.disabled, false);
assert.equal(ctx._billingCache[input.dataset.norawat].selisih_raw, 1000000);
assert.equal(rowOver, false);
assert.match(rowHtml, /text-success/);
input.value = '0';
handlers.change.call(input);
request.success({ success: true, plafon_raw: 0, has_plafon: true });
request.complete();
assert.equal(ctx._billingCache[input.dataset.norawat].selisih_raw, -1500000);
assert.equal(rowOver, true);
input.value = '';
handlers.change.call(input);
request.success({ success: true, plafon_raw: null, has_plafon: false });
request.complete();
assert.equal(ctx._billingCache[input.dataset.norawat].selisih_raw, null);
assert.equal(rowOver, false);
for (const value of ['-1', '1,5', '12.34', '1000000000000000']) {
    request = null;
    input.value = value;
    handlers.change.call(input);
    assert.equal(request, null);
}
input.value = '500';
handlers.change.call(input);
request.error({ responseJSON: { message: 'Save failed' } });
request.complete();
assert.equal(input.dataset.saved, '');
assert.equal(input.disabled, false);
assert.equal(statusText, 'Save failed');
assert.equal(ctx._plafonSaving, 0);
console.log('PASS UI: formatted input, balance, zero, clear, validation, failed save');
