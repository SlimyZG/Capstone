import { Tag, Sparkles } from 'lucide-react';

const AiCategoryBadge = ({ category, subcategory, confidence, showConfidence = false, size = 'sm' }) => {
  if (!category && !subcategory) return null;

  const sizeClasses = size === 'xs' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-1 text-xs';

  return (
    <span className="inline-flex items-center gap-1.5 flex-wrap">
      <span
        className={`inline-flex items-center gap-1.5 font-medium rounded-md bg-blue-50/80 text-[#1e3a8a] border border-blue-200/80 shadow-2xs ${sizeClasses}`}
      >
        <Tag size={12} className="text-blue-600 shrink-0" />
        <span className="font-semibold text-slate-800">{category}</span>
        {subcategory && (
          <>
            <span className="text-blue-400 font-bold" aria-hidden="true">›</span>
            <span className="text-blue-700 font-medium">{subcategory}</span>
          </>
        )}
      </span>

      {showConfidence && confidence !== null && confidence !== undefined && (
        <span
          className="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-semibold rounded bg-slate-100 text-slate-600 border border-slate-200"
          title={`Classification confidence: ${Math.round(Number(confidence) * (Number(confidence) <= 1 ? 100 : 1))}%`}
        >
          <Sparkles size={10} className="text-blue-500" />
          <span>{Math.round(Number(confidence) * (Number(confidence) <= 1 ? 100 : 1))}% conf</span>
        </span>
      )}
    </span>
  );
};

export default AiCategoryBadge;
