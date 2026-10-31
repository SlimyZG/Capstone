import { Sparkles, Info } from 'lucide-react';

const AiSummaryCard = ({ summary, title = 'AI Issue Summary', className = '' }) => {
  if (!summary) return null;

  return (
    <div className={`bg-blue-50/70 border border-blue-200/90 rounded-xl p-5 shadow-2xs ${className}`}>
      <div className="flex items-center justify-between mb-2">
        <div className="flex items-center gap-2 text-[#1e3a8a] font-bold text-xs uppercase tracking-wider">
          <Sparkles size={14} className="text-blue-600 shrink-0" />
          <span>{title}</span>
        </div>
        <span className="text-[11px] text-blue-600 font-medium bg-blue-100/60 px-2 py-0.5 rounded-full">
          Automated
        </span>
      </div>
      <p className="text-sm text-slate-800 leading-relaxed font-normal break-words whitespace-normal">
        {summary}
      </p>
    </div>
  );
};

export default AiSummaryCard;
