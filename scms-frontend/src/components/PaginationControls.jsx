import { ChevronLeft, ChevronRight } from 'lucide-react';

const PaginationControls = ({
  currentPage = 1,
  totalPages = 1,
  totalItems = 0,
  pageSize = 10,
  onPageChange,
}) => {
  if (totalPages <= 1 && totalItems <= pageSize) return null;

  const startItem = (currentPage - 1) * pageSize + 1;
  const endItem = Math.min(currentPage * pageSize, totalItems);

  return (
    <div className="px-6 py-4 border-t border-slate-200 bg-slate-50/50 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm">
      <div className="text-xs text-slate-500 font-medium">
        Showing <span className="font-semibold text-slate-900">{totalItems > 0 ? startItem : 0}</span> to{' '}
        <span className="font-semibold text-slate-900">{endItem}</span> of{' '}
        <span className="font-semibold text-slate-900">{totalItems}</span> results
      </div>

      <div className="flex items-center gap-1.5">
        <button
          onClick={() => onPageChange(currentPage - 1)}
          disabled={currentPage <= 1}
          className="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-slate-200 rounded-md text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-slate-900 disabled:opacity-40 disabled:cursor-not-allowed transition-colors shadow-2xs"
        >
          <ChevronLeft size={14} />
          Previous
        </button>

        <span className="px-3 py-1 text-xs font-semibold text-slate-600 bg-slate-100 rounded-md">
          {currentPage} / {Math.max(totalPages, 1)}
        </span>

        <button
          onClick={() => onPageChange(currentPage + 1)}
          disabled={currentPage >= totalPages}
          className="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-slate-200 rounded-md text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-slate-900 disabled:opacity-40 disabled:cursor-not-allowed transition-colors shadow-2xs"
        >
          Next
          <ChevronRight size={14} />
        </button>
      </div>
    </div>
  );
};

export default PaginationControls;
