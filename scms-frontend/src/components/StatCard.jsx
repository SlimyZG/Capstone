const StatCard = ({
  title,
  value,
  icon: Icon,
  subtitle,
  badge,
  iconBg = 'bg-blue-50 text-blue-600 border-blue-100',
  loading = false,
  onClick,
}) => {
  return (
    <div
      onClick={onClick}
      className={`bg-white rounded-xl border border-slate-200 p-5 shadow-xs transition-all ${
        onClick ? 'cursor-pointer hover:border-blue-300 hover:shadow-sm' : ''
      }`}
    >
      <div className="flex justify-between items-start mb-3">
        {Icon && (
          <div className={`h-10 w-10 rounded-lg flex items-center justify-center border ${iconBg}`}>
            <Icon size={20} />
          </div>
        )}
        {badge && (
          <span className="px-2 py-0.5 text-[11px] font-semibold rounded-full border bg-slate-50 text-slate-600 border-slate-200">
            {badge}
          </span>
        )}
      </div>

      <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
        {title}
      </p>

      <div className="text-3xl font-bold text-slate-900 tracking-tight">
        {loading ? (
          <span className="text-slate-300 animate-pulse text-2xl">—</span>
        ) : (
          value
        )}
      </div>

      {subtitle && (
        <p className="text-xs text-slate-500 mt-2 font-normal flex items-center gap-1">
          {subtitle}
        </p>
      )}
    </div>
  );
};

export default StatCard;
