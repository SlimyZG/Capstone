import React, { useState } from 'react';
import { Upload, X, CheckCircle2, AlertTriangle, FileText, Calendar } from 'lucide-react';
import api from '../api';

const CadDecisionUploadModal = ({
  isOpen,
  onClose,
  concern,
  onDecisionRecorded
}) => {
  const [decision, setDecision] = useState('approved');
  const [decisionDate, setDecisionDate] = useState(new Date().toISOString().split('T')[0]);
  const [decisionNotes, setDecisionNotes] = useState('');
  const [file, setFile] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState(null);

  if (!isOpen || !concern) return null;

  const handleFileChange = (e) => {
    const selectedFile = e.target.files[0];
    if (selectedFile) {
      if (selectedFile.size > 10 * 1024 * 1024) {
        setError('Selected file exceeds the 10MB limit.');
        return;
      }
      const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
      if (!allowedTypes.includes(selectedFile.type)) {
        setError('Please upload a valid PDF, JPG, or PNG document.');
        return;
      }
      setError(null);
      setFile(selectedFile);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!file) {
      setError('Please upload the scanned or photographed signed CAD document.');
      return;
    }

    setIsSubmitting(true);
    setError(null);

    const formData = new FormData();
    formData.append('decision', decision);
    formData.append('decision_date', decisionDate);
    formData.append('decision_notes', decisionNotes);
    formData.append('signed_document', file);

    try {
      const response = await api.post(`/dept-staff/concerns/${concern.concern_id}/cad/upload-decision`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      if (onDecisionRecorded) {
        onDecisionRecorded(response.data.concern);
      }
      onClose();
    } catch (err) {
      console.error('Failed to record CAD decision:', err);
      setError(err.response?.data?.message || 'Failed to upload signed document and record CAD decision.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-lg overflow-hidden my-4">
        {/* Modal Header */}
        <div className="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <div className="p-2 bg-blue-100 text-[#1e3a8a] rounded-lg">
              <Upload size={18} />
            </div>
            <div>
              <h3 className="text-base font-bold text-slate-900">Record Physical CAD Decision</h3>
              <p className="text-xs text-slate-500">Upload signed paper document and record Campus Administrator's determination</p>
            </div>
          </div>
          <button
            onClick={onClose}
            type="button"
            className="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-200 transition-colors"
          >
            <X size={18} />
          </button>
        </div>

        {/* Form Body */}
        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          {error && (
            <div className="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-xs flex items-center gap-2">
              <AlertTriangle size={15} className="shrink-0" />
              <span>{error}</span>
            </div>
          )}

          {/* Decision Selection */}
          <div>
            <label className="block text-xs font-bold text-slate-900 uppercase tracking-wider mb-2">
              Campus Administrator's Physical Determination *
            </label>
            <div className="grid grid-cols-3 gap-2">
              <button
                type="button"
                onClick={() => setDecision('approved')}
                className={`py-2 px-3 rounded-lg border text-xs font-bold flex flex-col items-center gap-1 transition-all ${
                  decision === 'approved'
                    ? 'bg-emerald-50 border-emerald-500 text-emerald-800 ring-2 ring-emerald-500/20'
                    : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
                }`}
              >
                <CheckCircle2 size={16} className={decision === 'approved' ? 'text-emerald-600' : 'text-slate-400'} />
                <span>Approved</span>
              </button>

              <button
                type="button"
                onClick={() => setDecision('rejected')}
                className={`py-2 px-3 rounded-lg border text-xs font-bold flex flex-col items-center gap-1 transition-all ${
                  decision === 'rejected'
                    ? 'bg-red-50 border-red-500 text-red-800 ring-2 ring-red-500/20'
                    : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
                }`}
              >
                <X size={16} className={decision === 'rejected' ? 'text-red-600' : 'text-slate-400'} />
                <span>Disapproved</span>
              </button>

              <button
                type="button"
                onClick={() => setDecision('returned')}
                className={`py-2 px-3 rounded-lg border text-xs font-bold flex flex-col items-center gap-1 transition-all ${
                  decision === 'returned'
                    ? 'bg-amber-50 border-amber-500 text-amber-800 ring-2 ring-amber-500/20'
                    : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
                }`}
              >
                <AlertTriangle size={16} className={decision === 'returned' ? 'text-amber-600' : 'text-slate-400'} />
                <span>Returned</span>
              </button>
            </div>
          </div>

          {/* Decision Date */}
          <div>
            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              Date on Physical Document
            </label>
            <div className="relative">
              <input
                type="date"
                value={decisionDate}
                onChange={(e) => setDecisionDate(e.target.value)}
                className="input-field text-xs pl-9"
                required
              />
              <Calendar size={15} className="absolute left-3 top-2.5 text-slate-400 pointer-events-none" />
            </div>
          </div>

          {/* Upload File */}
          <div>
            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              Scanned / Photographed Signed Document * (PDF, PNG, JPG - max 10MB)
            </label>
            <div className="border-2 border-dashed border-slate-300 rounded-xl p-4 text-center hover:bg-slate-50 transition-colors">
              <input
                type="file"
                id="signed-cad-file"
                accept=".pdf,.png,.jpg,.jpeg"
                onChange={handleFileChange}
                className="hidden"
              />
              <label htmlFor="signed-cad-file" className="cursor-pointer block">
                <FileText size={24} className="mx-auto text-blue-600 mb-1" />
                <p className="text-xs font-bold text-slate-800">
                  {file ? file.name : 'Click to select signed document file'}
                </p>
                <p className="text-[11px] text-slate-400 mt-0.5">
                  {file ? `${(file.size / (1024 * 1024)).toFixed(2)} MB` : 'Physical ink-signed copy from CAD'}
                </p>
              </label>
            </div>
          </div>

          {/* Directives / Remarks Notes */}
          <div>
            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              CAD Remarks &amp; Administrative Directives
            </label>
            <textarea
              rows={3}
              value={decisionNotes}
              onChange={(e) => setDecisionNotes(e.target.value)}
              placeholder="Transcribe any physical handwritten directives, instructions, or conditions from the Campus Administrator..."
              className="input-field text-xs"
            />
          </div>

          {/* Informational Notice */}
          <div className="p-3 bg-blue-50 border border-blue-200 rounded-lg text-[11px] text-blue-900 leading-relaxed">
            <span className="font-bold">Administrative Note: </span>
            Recording CAD approval verifies institutional authorization. The concern remains open so department staff can execute and finalize the resolution.
          </div>

          {/* Footer Buttons */}
          <div className="pt-2 flex justify-end gap-2">
            <button
              type="button"
              onClick={onClose}
              disabled={isSubmitting}
              className="btn-secondary py-1.5 px-4 text-xs"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isSubmitting || !file}
              className="btn-dark py-1.5 px-4 text-xs flex items-center gap-1.5"
            >
              <CheckCircle2 size={14} />
              <span>{isSubmitting ? 'Recording Decision...' : 'Save & Record Decision'}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default CadDecisionUploadModal;

