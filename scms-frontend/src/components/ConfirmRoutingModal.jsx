import { Building2 } from 'lucide-react';

const ConfirmRoutingModal = ({ departmentName, onConfirm, onCancel }) => {
  return (
    <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex justify-center items-center p-4">
      <div className="bg-white rounded-xl shadow-xl w-full max-w-sm overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        <div className="p-8 text-center flex flex-col items-center">
          
          <div className="w-16 h-16 bg-blue-50 text-[#1e3a8a] rounded-full flex items-center justify-center mb-6 shadow-sm border border-blue-100">
            <Building2 size={28} />
          </div>
          
          <h2 className="text-xl font-bold text-slate-900 mb-2">Department Matched</h2>
          
          <p className="text-sm text-slate-600 mb-8 leading-relaxed">
            Based on your description, your concern will be routed to the <span className="font-bold text-slate-900">{departmentName}</span>.
          </p>

          <div className="flex gap-3 w-full">
            <button 
              onClick={onCancel}
              className="flex-1 py-2.5 px-4 bg-white border border-slate-200 text-slate-700 font-semibold text-sm rounded-md hover:bg-slate-50 transition-colors shadow-sm"
            >
              Cancel
            </button>
            <button 
              onClick={onConfirm}
              className="flex-1 py-2.5 px-4 bg-[#20429f] hover:bg-[#1a3680] text-white font-semibold text-sm rounded-md transition-colors shadow-sm"
            >
              Confirm Routing
            </button>
          </div>

        </div>
      </div>
    </div>
  );
};

export default ConfirmRoutingModal;
