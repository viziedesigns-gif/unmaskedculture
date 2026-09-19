// Usage: node challenge/tests/avatar_parity.cjs /path/to/php
const {execFileSync} = require('node:child_process');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const data = JSON.parse(execFileSync(process.argv[2] || 'php', [join(__dirname, 'avatar_fixtures.php')], {encoding: 'utf8'}));
const context = {window: {KINTO_AVATAR: data}, document: {addEventListener() {}}};
vm.createContext(context);
vm.runInContext(readFileSync(join(__dirname, '../assets/js/kinto-avatar.js'), 'utf8'), context);
for (const item of data.cases) {
    assert.equal(context.window.KintoAvatar.render(item.config, {size: 'md', animate: false}), item.svg, item.id + ': preview and saved avatar differ');
}
console.log('PASS: all ' + data.cases.length + ' avatar items match between preview and server');
