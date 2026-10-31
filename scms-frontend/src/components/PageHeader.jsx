import { Link } from 'react-router-dom';
import { ChevronRight } from 'lucide-react';

const PageHeader = ({
  title,
  subtitle,
  category,
  breadcrumbs = [],
  actions,
  className = 'mb-6',
}) => {
  return (
    <div className={className}>
      {/* Optional Breadcrumbs */}
      {breadcrumbs.length > 0 && (
        <nav className="flex items-center space-x-1.5 text-xs text-slate-500 mb-2">
          {breadcrumbs.map((crumb, idx) => (
            <span key={idx} className="flex items-center space-x-1.5">
              {idx > 0 && <ChevronRight size={12} className="text-slate-400 shrink-0" />}
              {crumb.path ? (
                <Link to={crumb.path} className="hover:text-blue-600 transition-colors">
                  {crumb.label}
                </Link>
              ) : (
                <span className="text-slate-700 font-medium">{crumb.label}</span>
              )}
            </span>
          ))}
        </nav>
      )}

      {category && (
        <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">
          {category}
        </span>
      )}

      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">
            {title}
          </h1>
          {subtitle && (
            <p className="text-slate-500 text-sm mt-1 max-w-3xl leading-relaxed">
              {subtitle}
            </p>
          )}
        </div>

        {actions && (
          <div className="flex items-center gap-2.5 shrink-0 flex-wrap">
            {actions}
          </div>
        )}
      </div>
    </div>
  );
};

export default PageHeader;
