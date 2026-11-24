import { test } from 'node:test';
import assert from 'node:assert/strict';
import { buildSummaryPreview, DEFAULT_SUMMARY_PREVIEW_LENGTH } from './summaryPreview.js';

test('short summaries are displayed in full without a collapse toggle', () => {
  const shortText = 'The ceiling fan in Room 204 stopped working this morning and the room is very hot.';
  const result = buildSummaryPreview(shortText);

  assert.equal(result.isCollapsible, false);
  assert.equal(result.preview, shortText);
  assert.equal(result.full, shortText);
});

test('multi-sentence summaries preview on a clean complete sentence boundary', () => {
  const longText =
    'Since Monday, November 16, 2026, the main air conditioning unit in Computer Laboratory Room 303 has been leaking water onto Workstations PC-12 through PC-15. ' +
    'Two students nearly slipped on November 22 and four workstations were unplugged to avoid short-circuit hazards. ' +
    'Technical inspection and repair are requested before the November 25 practical examination.';

  const result = buildSummaryPreview(longText, DEFAULT_SUMMARY_PREVIEW_LENGTH);

  assert.equal(result.isCollapsible, true);
  assert.equal(result.full, longText);
  assert.ok(result.preview.length <= DEFAULT_SUMMARY_PREVIEW_LENGTH);
  assert.ok(result.preview.endsWith('.'), `Expected sentence boundary preview, got: ${result.preview}`);
  assert.ok(!result.preview.endsWith('…'));
});

test('overlong single sentences break cleanly at a word boundary without cutting words', () => {
  const oneLongSentence =
    'This administrative discrepancy has created severe academic consequences because the scholarship coordinator indicated that the financial aid renewal allowance for the second term will be automatically forfeited unless all grade encoding records are updated and certified prior to the approaching registration cutoff date.';

  const result = buildSummaryPreview(oneLongSentence, 180);

  assert.equal(result.isCollapsible, true);
  assert.equal(result.full, oneLongSentence);
  assert.ok(result.preview.length <= 181);
  assert.ok(result.preview.endsWith('…'));
  const withoutEllipsis = result.preview.slice(0, -1);
  assert.ok(oneLongSentence.startsWith(withoutEllipsis));
  assert.equal(oneLongSentence[withoutEllipsis.length], ' ');
});

test('empty or non-string summaries normalize safely', () => {
  assert.deepEqual(buildSummaryPreview(null), { preview: '', full: '', isCollapsible: false });
  assert.deepEqual(buildSummaryPreview('   '), { preview: '', full: '', isCollapsible: false });
});
