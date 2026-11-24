export const DEFAULT_SUMMARY_PREVIEW_LENGTH = 280;

/**
 * Build a readable summary preview on sentence or word boundaries while always keeping
 * the complete summary intact for expansion in AiSummaryCard.
 */
export const buildSummaryPreview = (summary, maxLength = DEFAULT_SUMMARY_PREVIEW_LENGTH) => {
  const full = typeof summary === 'string' ? summary.trim().replace(/\s+/g, ' ') : '';
  if (!full || full.length <= maxLength) {
    return {
      preview: full,
      full,
      isCollapsible: false,
    };
  }

  const minAcceptableBreak = Math.min(90, Math.floor(maxLength * 0.4));
  const candidateSlice = full.slice(0, maxLength + 1);

  let bestSentenceEnd = -1;
  const sentenceEndRegex = /[.?!](?=\s|$)/g;
  let match;
  while ((match = sentenceEndRegex.exec(candidateSlice)) !== null) {
    const endIdx = match.index + 1;
    if (endIdx <= maxLength && endIdx >= minAcceptableBreak) {
      bestSentenceEnd = endIdx;
    }
  }

  let basePreview;
  if (bestSentenceEnd !== -1) {
    basePreview = full.slice(0, bestSentenceEnd).trim();
  } else {
    const slice = full.slice(0, maxLength);
    const lastSpace = slice.lastIndexOf(' ');
    const cleanSlice = lastSpace > 40 ? slice.slice(0, lastSpace) : slice;
    basePreview = cleanSlice.replace(/[,;:\-\s]+$/, '').trim();
  }

  const isCollapsible = Boolean(basePreview && basePreview.length < full.length);
  if (!isCollapsible) {
    return {
      preview: full,
      full,
      isCollapsible: false,
    };
  }

  const endsWithSentencePunctuation = /[.?!]$/.test(basePreview);
  return {
    preview: endsWithSentencePunctuation ? basePreview : `${basePreview}…`,
    full,
    isCollapsible: true,
  };
};
