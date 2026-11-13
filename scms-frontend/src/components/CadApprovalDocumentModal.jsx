import React, { useState } from 'react';
import { Printer, X, FileText, CheckCircle2, ShieldCheck, AlertCircle } from 'lucide-react';
import api from '../api';

const CadApprovalDocumentModal = ({
  isOpen,
  onClose,
  concern,
  currentUser,
  onGeneratedSuccess
}) => {
  const [departmentNotes, setDepartmentNotes] = useState(concern?.cad_department_notes || '');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState(null);

  if (!isOpen || !concern) return null;

  const referenceNo = `REQ-${new Date(concern.submitted_at || Date.now()).getFullYear()}-${concern.concern_id.toString().padStart(4, '0')}`;
  const studentName = concern.is_anonymous ? 'CONFIDENTIAL (Identity Masked)' : (concern.student?.name || 'N/A');
  const studentId = concern.is_anonymous ? '********' : (concern.student?.student_id_number || 'N/A');
  const program = concern.student?.academic_unit?.name || concern.academic_unit?.name || 'Undergraduate Studies';
  const deptName = concern.department?.name || 'Operational Department';

  const handleGenerate = async () => {
    setIsSubmitting(true);
    setError(null);
    try {
      const response = await api.post(`/dept-staff/concerns/${concern.concern_id}/cad/generate-document`, {
        department_notes: departmentNotes
      });
      if (onGeneratedSuccess) {
        onGeneratedSuccess(response.data.concern);
      }
    } catch (err) {
      console.error('Failed to generate CAD document:', err);
      setError(err.response?.data?.message || 'Failed to initiate CAD document generation.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handlePrint = () => {
    window.print();
  };

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
      {/* Print-specific style */}
      <style>{`
        @media print {
          body * {
            visibility: hidden;
          }
          #cad-printable-doc, #cad-printable-doc * {
            visibility: visible;
          }
          #cad-printable-doc {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            margin: 0;
            padding: 20px;
            background: white !important;
            color: black !important;
            box-shadow: none !important;
            border: none !important;
          }
          .no-print {
            display: none !important;
          }
        }
      `}</style>

      <div className="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-4xl max-h-[92vh] flex flex-col overflow-hidden my-4">
        {/* Header toolbar (Hidden during print) */}
        <div className="no-print px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <div className="p-2 bg-blue-100 text-[#1e3a8a] rounded-lg">
              <FileText size={18} />
            </div>
            <div>
              <h3 className="text-base font-bold text-slate-900">SCMS Administrative Approval Request</h3>
              <p className="text-xs text-slate-500">Provisional routing document for Campus Administrator endorsement</p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={handlePrint}
              type="button"
              className="btn-primary py-1.5 px-3.5 text-xs flex items-center gap-1.5 shadow-xs"
            >
              <Printer size={15} />
              <span>Print Document</span>
            </button>
            <button
              onClick={onClose}
              type="button"
              className="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-200 transition-colors"
            >
              <X size={18} />
            </button>
          </div>
        </div>

        {/* Modal Scrollable Content */}
        <div className="flex-1 overflow-y-auto p-6 bg-slate-100/60">
          {error && (
            <div className="no-print mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-xs flex items-center gap-2">
              <AlertCircle size={15} className="shrink-0" />
              <span>{error}</span>
            </div>
          )}

          {/* Department Assessment Editor (Before generating / printing) */}
          <div className="no-print mb-6 p-4 bg-white rounded-xl border border-blue-200 shadow-xs">
            <label className="block text-xs font-bold text-slate-900 uppercase tracking-wider mb-1.5">
              Department Assessment &amp; Justification for CAD Approval
            </label>
            <p className="text-xs text-slate-500 mb-2">
              Explain why this non-academic concern requires Campus Administrator intervention or administrative approval.
            </p>
            <textarea
              rows={3}
              value={departmentNotes}
              onChange={(e) => setDepartmentNotes(e.target.value)}
              placeholder="e.g., Requested facility replacement exceeds standard departmental discretionary threshold; requires executive authorization..."
              className="input-field text-xs"
            />
            <div className="mt-2.5 flex justify-end gap-2">
              <button
                type="button"
                onClick={handleGenerate}
                disabled={isSubmitting}
                className="btn-dark py-1.5 px-3 text-xs flex items-center gap-1.5"
              >
                <CheckCircle2 size={14} />
                <span>{isSubmitting ? 'Saving Assessment...' : 'Save Assessment to SCMS'}</span>
              </button>
            </div>
          </div>

          {/* Printable Document Sheet */}
          <div
            id="cad-printable-doc"
            className="bg-white p-8 sm:p-10 rounded-xl border border-slate-300 shadow-sm max-w-3xl mx-auto text-slate-900 font-sans"
          >
            {/* Institution Header */}
            <div className="text-center border-b-2 border-slate-900 pb-5 mb-6">
              <h2 className="text-lg font-black tracking-wider text-slate-900 uppercase">
                University of Cebu - Lapu-Lapu and Mandaue
              </h2>
              <p className="text-xs text-slate-600 font-medium">
                Student Concern Management System (SCMS) — Generated Record
              </p>
              <div className="inline-block mt-3 px-3 py-1 bg-slate-100 border border-slate-400 rounded-sm">
                <h3 className="text-xs font-extrabold uppercase tracking-widest text-slate-800">
                  Office of the Campus Administrator (CAD)
                </h3>
              </div>
              <h1 className="text-base sm:text-lg font-black uppercase tracking-wide mt-3 text-slate-900">
                SCMS Administrative Approval Request
              </h1>
              <p className="text-[11px] font-mono text-slate-500 mt-1">
                Tracking Reference: {referenceNo}
              </p>
              <div className="mt-2.5">
                <span className="text-[10px] text-amber-800 bg-amber-50 border border-amber-300 rounded px-2 py-0.5 font-medium tracking-normal">
                  Provisional SCMS-generated routing document pending institutional template confirmation.
                </span>
              </div>
            </div>

            {/* Concern Metadata Table */}
            <div className="border border-slate-400 rounded mb-6 text-xs overflow-hidden">
              <div className="grid grid-cols-2 bg-slate-100 border-b border-slate-400 font-semibold p-2">
                <div>Reference ID: <span className="font-mono font-bold">{referenceNo}</span></div>
                <div className="text-right">Date Generated: {new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
              </div>
              <div className="grid grid-cols-2 p-2.5 border-b border-slate-300 gap-2">
                <div>
                  <span className="text-slate-500 block text-[10px] uppercase font-bold">Student Name:</span>
                  <span className="font-bold text-slate-900">{studentName}</span>
                </div>
                <div>
                  <span className="text-slate-500 block text-[10px] uppercase font-bold">Student ID Number:</span>
                  <span className="font-mono font-bold text-slate-900">{studentId}</span>
                </div>
              </div>
              <div className="grid grid-cols-2 p-2.5 border-b border-slate-300 gap-2">
                <div>
                  <span className="text-slate-500 block text-[10px] uppercase font-bold">Academic Unit / Program:</span>
                  <span className="font-semibold text-slate-800">{program}</span>
                </div>
                <div>
                  <span className="text-slate-500 block text-[10px] uppercase font-bold">Assigned Department:</span>
                  <span className="font-semibold text-slate-800">{deptName}</span>
                </div>
              </div>
              <div className="grid grid-cols-2 p-2.5 gap-2 bg-slate-50">
                <div>
                  <span className="text-slate-500 block text-[10px] uppercase font-bold">Priority Classification:</span>
                  <span className="font-bold uppercase text-slate-900">
                    {concern.priority || 'Normal'}
                    <span className="font-normal text-[10px] text-slate-600 ml-1">
                      (AI Recommendation - Staff Confirmed)
                    </span>
                  </span>
                </div>
                <div>
                  <span className="text-slate-500 block text-[10px] uppercase font-bold">Category / Subcategory:</span>
                  <span className="font-semibold text-slate-800">
                    {concern.issue_category || 'Operational'} &gt; {concern.issue_subcategory || 'General'}
                  </span>
                </div>
              </div>
            </div>

            {/* Concern Details */}
            <div className="mb-6">
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-800 mb-1 border-b border-slate-300 pb-1">
                Concern Summary &amp; Description
              </h4>
              <p className="text-sm font-bold text-slate-900 mb-1">{concern.title}</p>
              <div className="p-3 bg-slate-50 rounded border border-slate-200 text-xs text-slate-800 whitespace-pre-wrap leading-relaxed">
                {concern.description}
              </div>
            </div>

            {/* Department Assessment Section */}
            <div className="mb-8">
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-800 mb-1 border-b border-slate-300 pb-1">
                Department Assessment &amp; Justification for CAD Action
              </h4>
              <div className="p-3 bg-slate-50 min-h-[70px] rounded border border-slate-200 text-xs text-slate-800 whitespace-pre-wrap leading-relaxed italic">
                {departmentNotes || concern.cad_department_notes || 'Pending departmental elaboration.'}
              </div>
            </div>

            {/* Department Endorsement Signature Block */}
            <div className="mb-8 pt-2">
              <div className="grid grid-cols-2 gap-8 text-xs">
                <div>
                  <p className="text-[11px] font-bold uppercase text-slate-600 mb-8">
                    Prepared &amp; Endorsed by Evaluating Staff:
                  </p>
                  <div className="border-t border-slate-900 pt-1">
                    <p className="font-bold text-slate-900">{currentUser?.name || 'Department Staff'}</p>
                    <p className="text-slate-500 text-[10px] uppercase">{deptName} Staff</p>
                    <p className="text-slate-400 text-[10px]">Date: ________________________</p>
                  </div>
                </div>
                <div>
                  <p className="text-[11px] font-bold uppercase text-slate-600 mb-8">
                    Department Head / Unit Supervisor Concurrence:
                  </p>
                  <div className="border-t border-slate-900 pt-1">
                    <p className="font-bold text-slate-900">Department Head Signature</p>
                    <p className="text-slate-500 text-[10px] uppercase">{deptName}</p>
                    <p className="text-slate-400 text-[10px]">Date: ________________________</p>
                  </div>
                </div>
              </div>
            </div>

            {/* Official Campus Administrator Physical Action Block */}
            <div className="border-2 border-slate-900 rounded-lg p-5 bg-white mb-6">
              <h4 className="text-xs font-black uppercase tracking-wider text-center text-slate-900 border-b border-slate-400 pb-2 mb-3">
                For Office of the Campus Administrator (CAD) Official Use Only
              </h4>

              {/* Physical Checkboxes */}
              <div className="flex items-center justify-around py-3 border-b border-slate-300 text-xs font-bold uppercase">
                <label className="flex items-center gap-2">
                  <span className="w-5 h-5 border-2 border-slate-900 inline-block rounded-xs" />
                  <span>Approved</span>
                </label>
                <label className="flex items-center gap-2">
                  <span className="w-5 h-5 border-2 border-slate-900 inline-block rounded-xs" />
                  <span>Disapproved / Rejected</span>
                </label>
                <label className="flex items-center gap-2">
                  <span className="w-5 h-5 border-2 border-slate-900 inline-block rounded-xs" />
                  <span>Returned for Revision</span>
                </label>
              </div>

              {/* Administrative Remarks / Directives */}
              <div className="mt-3">
                <span className="text-[10px] font-bold uppercase text-slate-600 block mb-1">
                  Administrative Directives &amp; Action Notes:
                </span>
                <div className="h-16 border-b border-dashed border-slate-400 mb-2" />
                <div className="h-12 border-b border-dashed border-slate-400 mb-2" />
              </div>

              {/* Administrator Signature Line */}
              <div className="grid grid-cols-2 gap-8 mt-6 pt-4 text-xs">
                <div>
                  <div className="border-t border-slate-900 pt-1">
                    <p className="font-bold text-slate-900">Campus Administrator / Authorized Signatory</p>
                    <p className="text-slate-500 text-[10px]">Office of the Campus Administrator</p>
                  </div>
                </div>
                <div>
                  <div className="border-t border-slate-900 pt-1">
                    <p className="font-bold text-slate-900">Date &amp; Official Seal</p>
                    <p className="text-slate-500 text-[10px]">Physical Endorsement Received</p>
                  </div>
                </div>
              </div>
            </div>

            {/* Document Footer Disclaimer */}
            <div className="text-center text-[10px] text-slate-400 border-t border-slate-200 pt-3">
              Provisional SCMS-generated routing document pending institutional template confirmation. After ink signature by the Campus Administrator,
              this paper document must be scanned or photographed and uploaded back to SCMS for institutional audit.
            </div>
          </div>
        </div>

        {/* Modal Footer (Hidden during print) */}
        <div className="no-print px-6 py-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between">
          <p className="text-xs text-slate-500">
            Print this document on official paper, obtain physical ink signatures, and upload the signed copy when returned.
          </p>
          <div className="flex gap-2">
            <button
              onClick={onClose}
              type="button"
              className="btn-secondary py-1.5 px-4 text-xs"
            >
              Close
            </button>
            <button
              onClick={handlePrint}
              type="button"
              className="btn-primary py-1.5 px-4 text-xs flex items-center gap-1.5"
            >
              <Printer size={15} />
              <span>Print Document</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default CadApprovalDocumentModal;

