import assert from 'node:assert/strict';
import { singleValueAttributeKeys } from '../../assets/src/single-option.js';

const a = (key, value) => ({ key, label: key, value });
const keys = vs => [...singleValueAttributeKeys(vs)].sort();

// multi-value attribute stays; single-value hidden
assert.deepEqual(keys([
  { attributes: [a('pa_dob', 'X'), a('pa_size', 'S')] },
  { attributes: [a('pa_dob', 'X'), a('pa_size', 'M')] },
]), ['pa_dob']);
// one variation: all single, but colour stays visible (ADR-026 addendum 2)
assert.deepEqual(keys([{ attributes: [a('pa_boja', 'Latte'), a('pa_model', 'M1')] }]), ['pa_model']);
// two multi-value attributes: nothing hidden
assert.deepEqual(keys([
  { attributes: [a('pa_x', '1'), a('pa_y', 'a')] },
  { attributes: [a('pa_x', '2'), a('pa_y', 'b')] },
]), []);
// "any" value on one variation (attribute missing) is not single
assert.deepEqual(keys([{ attributes: [a('pa_x', '1')] }, { attributes: [] }]), []);
// no variations / no key -> empty
assert.deepEqual(keys([]), []);
assert.deepEqual(keys([{ attributes: [{ label: 'L', value: 'v' }] }]), []);
console.log('single-option tests: OK');
