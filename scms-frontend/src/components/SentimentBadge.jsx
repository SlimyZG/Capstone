import { Smile, Meh, Frown, AlertOctagon } from 'lucide-react';

const SentimentBadge = ({ label, score, size = 'sm' }) => {
  const normLabel = (label || 'neutral').toLowerCase().trim();

  const getSentimentConfig = () => {
    switch (normLabel) {
      case 'negative':
        return {
          icon: Frown,
          display: 'Negative',
          classes: 'bg-rose-50 text-rose-700 border-rose-200',
        };
      case 'urgent':
        return {
          icon: AlertOctagon,
          display: 'Urgent Emotion',
          classes: 'bg-red-50 text-red-700 border-red-200',
        };
      case 'positive':
        return {
          icon: Smile,
          display: 'Positive',
          classes: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        };
      case 'neutral':
      default:
        return {
          icon: Meh,
          display: 'Neutral',
          classes: 'bg-slate-50 text-slate-700 border-slate-200',
        };
    }
  };

  const config = getSentimentConfig();
  const Icon = config.icon;
  const sizeClasses = size === 'xs' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-1 text-xs';

  return (
    <span
      className={`inline-flex items-center gap-1.5 font-medium rounded-md border shadow-2xs ${sizeClasses} ${config.classes}`}
      title={score !== undefined && score !== null ? `Sentiment Score: ${score}` : undefined}
    >
      <Icon size={13} className="shrink-0" />
      <span>
        <span className="font-semibold text-slate-500 mr-1 text-[10px] uppercase tracking-wider">Sentiment:</span>
        {config.display}
        {score !== undefined && score !== null && (
          <span className="ml-1 opacity-75 font-mono text-[10px]">({Number(score).toFixed(2)})</span>
        )}
      </span>
    </span>
  );
};

export default SentimentBadge;
