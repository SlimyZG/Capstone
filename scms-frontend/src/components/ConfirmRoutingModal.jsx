import { Building2, BookOpen, Loader2 } from 'lucide-react';

const ConfirmRoutingModal = ({
  departmentName,
  targetName,
  workflowType = 'non_academic',
  onConfirm,
  onCancel,
  loading = false,
}) => {
  const isAcademic = workflowType === 'academic';
  const displayTarget = targetName || departmentName || 'Designated Office';

  return (
    <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex justify-center items-center p-4">
      <div className="bg-white rounded-xl shadow-lg border border-slate-200 w-full max-w-sm overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div className="p-6 sm:p-8 text-center flex flex-col items-center">
          
          <div className="w-14 h-14 bg-blue-50 text-[#1e3a8a] rounded-full flex items-center justify-center mb-4 shadow-2xs border border-blue-100">
            {isAcademic ? <BookOpen size={26} /> : <Building2 size={26} />}
          </div>

          <span className="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider mb-2 bg-slate-100 text-slate-700">
            {isAcademic ? 'Academic Review Track' : 'Operational Department Track'}
          </span>
          
          <h2 className="text-lg font-bold text-slate-900 mb-1.5">Confirm Routing Destination</h2>
          
          <p className="text-xs sm:text-sm text-slate-600 mb-6 leading-relaxed">
            Your concern will be routed directly to: <br />
            <span className="font-bold text-[#1e3a8a] text-sm sm:text-base inline-block mt-1">
              {displayTarget}
            </span>
          </p>

          <div className="flex gap-3 w-full">
            <button 
              type="button"
              disabled={loading}
              onClick={onCancel}
              className="flex-1 py-2.5 px-4 bg-white border border-slate-200 text-slate-700 font-semibold text-xs sm:text-sm rounded-lg hover:bg-slate-50 transition-colors shadow-2xs disabled:opacity-50"
            >
              Cancel
            </button>
            <button 
              type="button"
              disabled={loading}
              onClick={onConfirm}
              className="flex-1 py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs sm:text-sm rounded-lg transition-colors shadow-2xs flex items-center justify-center gap-1.5 disabled:opacity-70 disabled:cursor-not-allowed cursor-pointer"
            >
              {loading ? (
                <>
                  <Loader2 size={15} className="animate-spin" />
                  <span>Submitting...</span>
                </>
              ) : (
                <span>Confirm & Submit</span>
              )}
            </button>
          </div>

        </div>
      </div>
    </div>
  );
};

export default ConfirmRoutingModal;
