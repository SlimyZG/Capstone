import { useEffect, useId, useState } from 'react';
import { Sparkles, Cpu, Bot, AlertCircle, Clock, ChevronDown, ChevronUp } from 'lucide-react';
import { buildSummaryPreview } from '../utils/summaryPreview';

const AiSummaryCard = ({ summary, metadata = null, title = 'AI Issue Summary', className = '' }) => {
  const [isExpanded, setIsExpanded] = useState(false);
  const summaryId = useId();

  useEffect(() => {
    setIsExpanded(false);
  }, [summary]);

  if (!summary) return null;

  const { preview, full, isCollapsible } = buildSummaryPreview(summary);
  if (!full) return null;

  // Determine source badge styling and label
  let sourceBadge = (
    <span className="inline-flex items-center gap-1 text-[11px] text-blue-600 font-medium bg-blue-100/70 px-2 py-0.5 rounded-full border border-blue-200/60">
      <Sparkles size={11} className="text-blue-500 shrink-0" />
      Automated
    </span>
  );

  if (metadata) {
    if (metadata.status === 'failed') {
      sourceBadge = (
        <span className="inline-flex items-center gap-1 text-[11px] text-red-700 font-medium bg-red-50 px-2 py-0.5 rounded-full border border-red-200">
          <AlertCircle size={11} className="text-red-500 shrink-0" />
          Fallback Inactive
        </span>
      );
    } else if (metadata.status === 'pending') {
      sourceBadge = (
        <span className="inline-flex items-center gap-1 text-[11px] text-amber-700 font-medium bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200 animate-pulse">
          <Clock size={11} className="text-amber-500 shrink-0" />
          Processing...
        </span>
      );
    } else if (metadata.source === 'gemini' && !metadata.is_fallback) {
      sourceBadge = (
        <span className="inline-flex items-center gap-1 text-[11px] text-indigo-700 font-semibold bg-indigo-50 px-2.5 py-0.5 rounded-full border border-indigo-200" title="Generated using Google Gemini AI">
          <Bot size={11} className="text-indigo-600 shrink-0" />
          Gemini AI
        </span>
      );
    } else if (metadata.is_fallback || metadata.source === 'local_fallback') {
      sourceBadge = (
        <span className="inline-flex items-center gap-1 text-[11px] text-slate-700 font-medium bg-slate-100 px-2.5 py-0.5 rounded-full border border-slate-200" title="Generated using rule-based local analysis engine">
          <Cpu size={11} className="text-slate-500 shrink-0" />
          Rule-based Engine
        </span>
      );
    }
  }

  return (
    <div className={`bg-blue-50/70 border border-blue-200/90 rounded-2xl p-5 shadow-2xs ${className}`}>
      <div className="flex items-center justify-between mb-2.5">
        <div className="flex items-center gap-2 text-[#1e3a8a] font-bold text-xs uppercase tracking-wider">
          <div className="h-6 w-6 rounded-md bg-blue-600 flex items-center justify-center text-white shrink-0 shadow-xs">
            <Sparkles size={13} />
          </div>
          <span>{title}</span>
        </div>
        <div>
          {sourceBadge}
        </div>
      </div>
      <p
        id={summaryId}
        className="text-sm text-slate-800 leading-relaxed font-normal break-words whitespace-normal"
      >
        {isExpanded || !isCollapsible ? full : preview}
      </p>
      {isCollapsible && (
        <div className="mt-2.5 flex items-center justify-between gap-2">
          <button
            type="button"
            aria-expanded={isExpanded}
            aria-controls={summaryId}
            onClick={() => setIsExpanded((prev) => !prev)}
            className="inline-flex items-center gap-1 text-xs font-bold text-[#1e3a8a] hover:text-blue-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 rounded cursor-pointer"
          >
            {isExpanded ? (
              <>
                <ChevronUp size={14} />
                <span>Show Less</span>
              </>
            ) : (
              <>
                <ChevronDown size={14} />
                <span>Show Full Summary</span>
              </>
            )}
          </button>
        </div>
      )}
    </div>
  );
};

export default AiSummaryCard;
