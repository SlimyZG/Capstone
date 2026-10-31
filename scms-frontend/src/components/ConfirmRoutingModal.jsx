import { Building2 } from 'lucide-react';

const ConfirmRoutingModal = ({ departmentName, onConfirm, onCancel }) => {
  return (
    <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex justify-center items-center p-4">
      <div className="bg-white rounded-xl shadow-lg border border-slate-200 w-full max-w-sm overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div className="p-6 sm:p-8 text-center flex flex-col items-center">
          
          <div className="w-14 h-14 bg-blue-50 text-[#1e3a8a] rounded-full flex items-center justify-center mb-5 shadow-2xs border border-blue-100">
            <Building2 size={26} />
          </div>
          
          <h2 className="text-lg font-bold text-slate-900 mb-1.5">Confirm Routing Target</h2>
          
          <p className="text-sm text-slate-600 mb-6 leading-relaxed">
            Your concern will be routed directly to: <br />
            <span className="font-bold text-[#1e3a8a] text-base inline-block mt-1">{departmentName}</span>
          </p>

          <div className="flex gap-3 w-full">
            <button 
              type="button"
              onClick={onCancel}
              className="flex-1 py-2.5 px-4 bg-white border border-slate-200 text-slate-700 font-semibold text-sm rounded-lg hover:bg-slate-50 transition-colors shadow-2xs"
            >
              Cancel
            </button>
            <button 
              type="button"
              onClick={onConfirm}
              className="flex-1 py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm rounded-lg transition-colors shadow-2xs"
            >
              Confirm & Submit
            </button>
          </div>

        </div>
      </div>
    </div>
  );
};

export default ConfirmRoutingModal;
