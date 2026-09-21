#!/usr/bin/env node
'use strict';

const assert = require('assert');
const { createRequestTracker } = require('./request-tracker');

const url = 'https://example.test/wp-json/wpsocialreviews/items';
const first = { url: () => url };
const second = { url: () => url };
const tracker = createRequestTracker(() => true);

tracker.start(first);
tracker.start(second);
assert.strictEqual(tracker.size, 2, 'same-URL request identities must both remain pending');

tracker.finish(first);
assert.strictEqual(tracker.size, 1, 'first completion must leave second same-URL request pending');
assert.deepStrictEqual(tracker.urls(), [url]);

tracker.finish(second);
assert.strictEqual(tracker.size, 0, 'tracker must become idle after second completion');

console.log('request tracker self-test passed: pending 2 -> 1 -> 0');
