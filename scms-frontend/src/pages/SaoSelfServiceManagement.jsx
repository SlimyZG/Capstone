import { useState, useEffect, useCallback } from 'react';
import {
  BookOpen,
  CheckCircle2,
  Clock,
  AlertTriangle,
  Search,
  UploadCloud,
  ShieldAlert,
  FileCheck2,
  Edit3,
  History,
  X,
  Plus,
  Trash2,
  XCircle,
  Save
} from 'lucide-react';
import api from '../api';
import PageHeader from '../components/PageHeader';
import StatCard from '../components/StatCard';
import LoadingSpinner from '../components/LoadingSpinner';

const getApprovalBadgeStyle = (status) => {
  switch (status) {
    case 'Approved':
      return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
    case 'Needs Revision':
      return 'bg-red-50 text-red-700 border border-red-200';
    case 'Rejected':
      return 'bg-rose-100 text-rose-800 border border-rose-300';
    case 'Verbal Confirmation Pending Formal Approval':
      return 'bg-indigo-50 text-indigo-700 border border-indigo-200';
    default:
      return 'bg-amber-50 text-amber-800 border border-amber-200';
  }
};

const getPublicationBadgeStyle = (status) => {
  switch (status) {
    case 'Published':
      return 'bg-blue-50 text-[#1e3a8a] border border-blue-200';
    case 'Archived':
      return 'bg-slate-200 text-slate-700 border border-slate-300';
    default:
      return 'bg-slate-100 text-slate-600 border border-slate-200';
  }
};

const isPublishEligible = (entry) => {
  if (!entry) return false;
  if (entry.approval_status !== 'Approved') return false;
  const requiredFields = ['validated_by', 'validated_role', 'validated_office', 'supporting_reference'];
  return requiredFields.every((field) => String(entry[field] || '').trim() !== '');
};

const getPublishDisabledReason = (entry) => {
  if (!entry) return 'No article selected.';
  if (entry.approval_status !== 'Approved') {
    return `Publishing requires Approval Status to be "Approved" (currently "${entry.approval_status}"). Save formal UCLM approval first.`;
  }
  const missing = [];
  if (!String(entry.validated_by || '').trim()) missing.push('Validated By');
  if (!String(entry.validated_role || '').trim()) missing.push('Validator Position / Role');
  if (!String(entry.validated_office || '').trim()) missing.push('Validating UCLM Office');
  if (!String(entry.supporting_reference || '').trim()) missing.push('Supporting Official Reference / Memo');
  if (missing.length > 0) {
    return `Missing saved approval evidence required before publishing: ${missing.join(', ')}.`;
  }
  return 'Publish article to the UCLM Student Guide';
};

const formatApiError = (err, fallbackMessage) => {
  const responseData = err?.response?.data;
  if (!responseData) {
    return fallbackMessage;
  }
  const baseMessage = responseData.message || fallbackMessage;
  if (responseData.errors && typeof responseData.errors === 'object') {
    const details = Object.values(responseData.errors)
      .flat()
      .filter(Boolean);
    if (details.length > 0) {
      return `${baseMessage} (${details.join(' ')})`;
    }
  }
  return baseMessage;
};

const buildFormStateFromEntry = (entry) => ({
  title: entry.title || '',
  manual_page_number: entry.manual_page_number || '',
  approval_status: entry.approval_status || 'Pending UCLM Validation',
  validated_by: entry.validated_by || '',
  validated_role: entry.validated_role || '',
  validated_office: entry.validated_office || entry.primary_review_office || '',
  supporting_reference: entry.supporting_reference || '',
  validation_notes: entry.validation_notes || '',
  approved_answer: entry.approved_answer || '',
  responsible_office: entry.responsible_office || '',
  requirements_text: Array.isArray(entry.requirements) ? entry.requirements.join('\n') : '',
  steps: Array.isArray(entry.steps)
    ? entry.steps.map((s, idx) => ({
        step_number: Number(s.step_number || idx + 1),
        title: s.title || '',
        instructions: s.instructions || '',
        office: s.office || '',
        requirements: Array.isArray(s.requirements) ? s.requirements : [],
        source_reference: s.source_reference || null,
        explanation: s.explanation || null,
      }))
    : [],
  revision_summary: '',
});

const SaoSelfServiceManagement = () => {
  const [activeTab, setActiveTab] = useState('entries'); // 'entries' | 'analytics'
  const [entries, setEntries] = useState([]);
  const [summary, setSummary] = useState(null);
  const [analytics, setAnalytics] = useState(null);

  const [filterCategory, setFilterCategory] = useState('All');
  const [filterApproval, setFilterApproval] = useState('All');
  const [filterPublication, setFilterPublication] = useState('All');
  const [searchQuery, setSearchQuery] = useState('');

  const [selectedEntry, setSelectedEntry] = useState(null);
  const [entryRevisions, setEntryRevisions] = useState([]);
  const [loadingEntryDetails, setLoadingEntryDetails] = useState(false);

  const [approvalForm, setApprovalForm] = useState(buildFormStateFromEntry({}));
  const [revisionReason, setRevisionReason] = useState('');

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [statusMessage, setStatusMessage] = useState(null);
  const [errorMessage, setErrorMessage] = useState(null);
  const [modalSuccessMessage, setModalSuccessMessage] = useState(null);
  const [modalErrorMessage, setModalErrorMessage] = useState(null);

  const fetchEntries = useCallback(async () => {
    try {
      const params = new URLSearchParams();
      if (filterCategory !== 'All') params.set('category', filterCategory);
      if (filterApproval !== 'All') params.set('approval_status', filterApproval);
      if (filterPublication !== 'All') params.set('publication_status', filterPublication);
      if (searchQuery.trim()) params.set('q', searchQuery.trim());

      const res = await api.get(`/sao/self-service/entries?${params.toString()}`);
      setEntries(res.data.entries || []);
      setSummary(res.data.summary || null);
    } catch (err) {
      console.error('Failed to load SAO Student Guide entries', err);
      setErrorMessage(formatApiError(err, 'Unable to load Student Guide handbook articles.'));
    }
  }, [filterCategory, filterApproval, filterPublication, searchQuery]);

  const fetchAnalytics = useCallback(async () => {
    try {
      const res = await api.get('/sao/self-service/analytics');
      setAnalytics(res.data);
    } catch (err) {
      console.error('Failed to load Student Guide analytics', err);
    }
  }, []);

  useEffect(() => {
    Promise.all([fetchEntries(), fetchAnalytics()]).finally(() => setLoading(false));
  }, [fetchEntries, fetchAnalytics]);

  const fetchEntryDetailAndRevisions = useCallback(async (entryId, syncForm = false) => {
    setLoadingEntryDetails(true);
    try {
      const res = await api.get(`/sao/self-service/entries/${entryId}`);
      if (res.data?.entry) {
        const freshEntry = res.data.entry;
        setSelectedEntry((prev) => (prev && prev.id === entryId ? freshEntry : prev));
        setEntryRevisions(freshEntry.revisions || []);
        if (syncForm) {
          setApprovalForm(buildFormStateFromEntry(freshEntry));
        }
      }
    } catch (err) {
      console.error('Failed to load entry revision history', err);
    } finally {
      setLoadingEntryDetails(false);
    }
  }, []);

  const handleOpenEntryModal = (entry) => {
    setSelectedEntry(entry);
    setEntryRevisions(entry.revisions || []);
    setApprovalForm(buildFormStateFromEntry(entry));
    setRevisionReason('');
    setModalErrorMessage(null);
    setModalSuccessMessage(null);
    fetchEntryDetailAndRevisions(entry.id, false);
  };

  const handleCloseEntryModal = useCallback(() => {
    if (saving) return;
    setSelectedEntry(null);
    setEntryRevisions([]);
    setModalErrorMessage(null);
    setModalSuccessMessage(null);
  }, [saving]);

  // Prevent background page scroll and support Escape key while modal is open
  useEffect(() => {
    if (!selectedEntry) return undefined;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    const handleKeyDown = (event) => {
      if (event.key === 'Escape' && !saving) {
        handleCloseEntryModal();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [selectedEntry, saving, handleCloseEntryModal]);

  const handleAddStep = () => {
    setApprovalForm((prev) => {
      const nextStepNumber = prev.steps.length + 1;
      return {
        ...prev,
        steps: [
          ...prev.steps,
          {
            step_number: nextStepNumber,
            title: '',
            instructions: '',
            office: prev.responsible_office || '',
            requirements: [],
            source_reference: null,
            explanation: null,
          },
        ],
      };
    });
  };

  const handleUpdateStepField = (index, field, value) => {
    setApprovalForm((prev) => {
      const updatedSteps = prev.steps.map((step, idx) =>
        idx === index ? { ...step, [field]: value } : step
      );
      return { ...prev, steps: updatedSteps };
    });
  };

  const handleRemoveStep = (index) => {
    setApprovalForm((prev) => {
      const filtered = prev.steps
        .filter((_, idx) => idx !== index)
        .map((step, idx) => ({ ...step, step_number: idx + 1 }));
      return { ...prev, steps: filtered };
    });
  };

  const handleSaveEntryUpdate = async (e) => {
    if (e && e.preventDefault) {
      e.preventDefault();
    }
    if (!selectedEntry || saving) return;

    setModalErrorMessage(null);
    setModalSuccessMessage(null);
    setErrorMessage(null);
    setStatusMessage(null);

    // Client-side validation for approval evidence ONLY when setting Approval Status === 'Approved'
    if (approvalForm.approval_status === 'Approved') {
      const missingFields = [];
      if (!approvalForm.validated_by.trim()) missingFields.push('Validated By (Authorized Name)');
      if (!approvalForm.validated_role.trim()) missingFields.push('Validator Position / Role');
      if (!approvalForm.validated_office.trim()) missingFields.push('Validating UCLM Office');
      if (!approvalForm.supporting_reference.trim()) missingFields.push('Supporting Official Reference / Memo');

      if (missingFields.length > 0) {
        setModalErrorMessage(
          `Recording an Approved decision requires all four official sign-off fields: ${missingFields.join(', ')}.`
        );
        return;
      }
    }

    if (!approvalForm.title.trim()) {
      setModalErrorMessage('Article Title is required.');
      return;
    }
    if (!approvalForm.approved_answer.trim()) {
      setModalErrorMessage('Approved Guide Article Overview / Answer Text is required.');
      return;
    }
    if (!approvalForm.responsible_office.trim()) {
      setModalErrorMessage('Responsible Office is required.');
      return;
    }

    // Validate steps if present
    for (let i = 0; i < approvalForm.steps.length; i += 1) {
      const s = approvalForm.steps[i];
      if (!String(s.title || '').trim() || !String(s.instructions || '').trim()) {
        setModalErrorMessage(
          `Step ${i + 1} requires both a step title and step instructions before saving.`
        );
        return;
      }
    }

    const parsedRequirements = approvalForm.requirements_text
      .split('\n')
      .map((line) => line.trim())
      .filter(Boolean);

    const normalizedSteps = approvalForm.steps.map((s, idx) => ({
      step_number: idx + 1,
      title: String(s.title || '').trim(),
      instructions: String(s.instructions || '').trim(),
      office: String(s.office || '').trim() || null,
      requirements: Array.isArray(s.requirements) ? s.requirements : [],
      source_reference: s.source_reference || null,
      explanation: s.explanation || null,
    }));

    const payload = {
      title: approvalForm.title.trim(),
      manual_page_number: approvalForm.manual_page_number.trim() || selectedEntry.manual_page_number,
      approval_status: approvalForm.approval_status,
      validated_by: approvalForm.validated_by.trim() || null,
      validated_role: approvalForm.validated_role.trim() || null,
      validated_office: approvalForm.validated_office.trim() || null,
      supporting_reference: approvalForm.supporting_reference.trim() || null,
      validation_notes: approvalForm.validation_notes.trim() || null,
      approved_answer: approvalForm.approved_answer.trim(),
      responsible_office: approvalForm.responsible_office.trim(),
      requirements: parsedRequirements,
      steps: normalizedSteps,
      revision_summary: approvalForm.revision_summary.trim() || null,
    };

    setSaving(true);
    try {
      const res = await api.put(`/sao/self-service/entries/${selectedEntry.id}`, payload);
      const updatedEntry = res.data.entry;
      setSelectedEntry(updatedEntry);
      setApprovalForm(buildFormStateFromEntry(updatedEntry));
      setModalSuccessMessage(res.data.message || 'Student Guide article saved successfully.');
      setStatusMessage(res.data.message || 'Student Guide article saved successfully.');
      await Promise.all([fetchEntries(), fetchEntryDetailAndRevisions(selectedEntry.id, false)]);
    } catch (err) {
      const formatted = formatApiError(err, 'Failed to save Student Guide article changes.');
      setModalErrorMessage(formatted);
      setErrorMessage(formatted);
    } finally {
      setSaving(false);
    }
  };

  const handlePublishEntry = async (entryId) => {
    if (saving) return;
    setSaving(true);
    setErrorMessage(null);
    setStatusMessage(null);
    setModalErrorMessage(null);
    setModalSuccessMessage(null);
    try {
      const res = await api.post(`/sao/self-service/entries/${entryId}/publish`);
      if (selectedEntry?.id === entryId) {
        setSelectedEntry(res.data.entry);
        setApprovalForm(buildFormStateFromEntry(res.data.entry));
        setModalSuccessMessage(res.data.message);
        await fetchEntryDetailAndRevisions(entryId, false);
      }
      setStatusMessage(res.data.message);
      await Promise.all([fetchEntries(), fetchAnalytics()]);
    } catch (err) {
      const formatted = formatApiError(err, 'Failed to publish article.');
      setErrorMessage(formatted);
      if (selectedEntry?.id === entryId) {
        setModalErrorMessage(formatted);
      }
    } finally {
      setSaving(false);
    }
  };

  const handleUnpublishEntry = async (entryId) => {
    if (saving) return;
    setSaving(true);
    setErrorMessage(null);
    setStatusMessage(null);
    setModalErrorMessage(null);
    setModalSuccessMessage(null);
    try {
      const res = await api.post(`/sao/self-service/entries/${entryId}/unpublish`, {
        reason: revisionReason || 'Unpublished by SAO.',
      });
      if (selectedEntry?.id === entryId) {
        setSelectedEntry(res.data.entry);
        setApprovalForm(buildFormStateFromEntry(res.data.entry));
        setModalSuccessMessage(res.data.message);
        await fetchEntryDetailAndRevisions(entryId, false);
      }
      setStatusMessage(res.data.message);
      await Promise.all([fetchEntries(), fetchAnalytics()]);
    } catch (err) {
      const formatted = formatApiError(err, 'Failed to unpublish article.');
      setErrorMessage(formatted);
      if (selectedEntry?.id === entryId) {
        setModalErrorMessage(formatted);
      }
    } finally {
      setSaving(false);
    }
  };

  const handleMarkNeedsRevision = async (entryId) => {
    if (saving) return;
    if (!revisionReason.trim()) {
      const msg = 'Please enter a reason in the "Reason for marking Needs Revision" field first.';
      setModalErrorMessage(msg);
      setErrorMessage(msg);
      return;
    }
    setSaving(true);
    setErrorMessage(null);
    setStatusMessage(null);
    setModalErrorMessage(null);
    setModalSuccessMessage(null);
    try {
      const res = await api.post(`/sao/self-service/entries/${entryId}/needs-revision`, {
        reason: revisionReason.trim(),
      });
      if (selectedEntry?.id === entryId) {
        setSelectedEntry(res.data.entry);
        setApprovalForm(buildFormStateFromEntry(res.data.entry));
        setModalSuccessMessage(res.data.message);
        await fetchEntryDetailAndRevisions(entryId, false);
      }
      setRevisionReason('');
      setStatusMessage(res.data.message);
      await Promise.all([fetchEntries(), fetchAnalytics()]);
    } catch (err) {
      const formatted = formatApiError(err, 'Failed to mark article as Needs Revision.');
      setErrorMessage(formatted);
      if (selectedEntry?.id === entryId) {
        setModalErrorMessage(formatted);
      }
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <LoadingSpinner message="Loading UCLM Student Guide Management..." fullPage />;
  }

  const canPublishSelected = isPublishEligible(selectedEntry);
  const selectedPublishReason = getPublishDisabledReason(selectedEntry);

  return (
    <div className="space-y-6 sm:space-y-8 pb-12">
      <PageHeader
        category="SAO Institutional Governance • Digital Student Handbook"
        title="UCLM Student Guide Management"
        subtitle="Manage digital UCLM Student Manual handbook articles, record authorized university office validations, control student-facing publication of procedures, and monitor guide usage."
        actions={
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => setActiveTab('entries')}
              className={`px-3.5 py-2 rounded-lg text-xs font-bold transition-colors cursor-pointer ${
                activeTab === 'entries'
                  ? 'bg-[#1e3a8a] text-white'
                  : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50'
              }`}
            >
              Handbook Articles ({summary?.total_entries || 46})
            </button>
            <button
              type="button"
              onClick={() => setActiveTab('analytics')}
              className={`px-3.5 py-2 rounded-lg text-xs font-bold transition-colors cursor-pointer ${
                activeTab === 'analytics'
                  ? 'bg-[#1e3a8a] text-white'
                  : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50'
              }`}
            >
              Guide Usage & Feedback Analytics
            </button>
          </div>
        }
      />

      {/* Top KPI Cards */}
      {summary && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard
            title="Total Handbook Articles"
            value={summary.total_entries}
            icon={BookOpen}
            subtitle={`${summary.standard_entries} Standard • ${summary.sensitive_entries} Sensitive Pathways`}
            iconBg="bg-blue-50 text-[#1e3a8a] border-blue-100"
          />
          <StatCard
            title="Pending UCLM Validation"
            value={summary.pending_validation}
            icon={Clock}
            subtitle={
              summary.needs_revision > 0
                ? `${summary.needs_revision} Marked Needs Revision`
                : 'Awaiting signed office review packets'
            }
            iconBg="bg-amber-50 text-amber-700 border-amber-100"
          />
          <StatCard
            title="Formally Approved"
            value={summary.approved_entries}
            icon={FileCheck2}
            subtitle="Verified by authorized UCLM personnel"
            iconBg="bg-indigo-50 text-indigo-700 border-indigo-100"
          />
          <StatCard
            title="Published in Student Guide"
            value={summary.published_entries}
            icon={CheckCircle2}
            subtitle="Live in UCLM Student Guide"
            iconBg="bg-emerald-50 text-emerald-700 border-emerald-100"
          />
        </div>
      )}

      {errorMessage && (
        <div className="p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm flex items-center justify-between gap-3">
          <span>{errorMessage}</span>
          <button onClick={() => setErrorMessage(null)} className="font-bold underline shrink-0">Dismiss</button>
        </div>
      )}

      {statusMessage && (
        <div className="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm flex items-center justify-between gap-3">
          <span>{statusMessage}</span>
          <button onClick={() => setStatusMessage(null)} className="font-bold underline shrink-0">Dismiss</button>
        </div>
      )}

      {activeTab === 'entries' ? (
        <div className="space-y-6">
          {/* Filters */}
          <div className="card p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
            <div>
              <label className="block text-[11px] font-bold text-slate-600 uppercase mb-1">Search Article</label>
              <div className="relative">
                <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                <input
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Topic code, title, office..."
                  className="input-field pl-9 text-xs"
                />
              </div>
            </div>

            <div>
              <label className="block text-[11px] font-bold text-slate-600 uppercase mb-1">Handbook Category</label>
              <select
                value={filterCategory}
                onChange={(e) => setFilterCategory(e.target.value)}
                className="input-field text-xs bg-white"
              >
                <option value="All">All 12 Categories</option>
                {['Academic', 'Enrollment', 'Financial', 'Student ID', 'Library', 'Facilities', 'Technology', 'Student Services', 'Medical', 'Guidance', 'Safety and Security', 'Other Concerns'].map(c => (
                  <option key={c} value={c}>{c}</option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-[11px] font-bold text-slate-600 uppercase mb-1">Approval Status</label>
              <select
                value={filterApproval}
                onChange={(e) => setFilterApproval(e.target.value)}
                className="input-field text-xs bg-white"
              >
                <option value="All">All Approval Statuses</option>
                <option value="Pending UCLM Validation">Pending UCLM Validation</option>
                <option value="Verbal Confirmation Pending Formal Approval">Verbal Confirmation Pending Formal Approval</option>
                <option value="Approved">Approved</option>
                <option value="Needs Revision">Needs Revision</option>
                <option value="Rejected">Rejected</option>
              </select>
            </div>

            <div>
              <label className="block text-[11px] font-bold text-slate-600 uppercase mb-1">Publication Status</label>
              <select
                value={filterPublication}
                onChange={(e) => setFilterPublication(e.target.value)}
                className="input-field text-xs bg-white"
              >
                <option value="All">All Publication States</option>
                <option value="Unpublished">Unpublished</option>
                <option value="Published">Published</option>
                <option value="Archived">Archived</option>
              </select>
            </div>
          </div>

          {/* Entries Table */}
          <div className="card overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-600 uppercase tracking-wider">
                    <th className="py-3.5 px-4">Code</th>
                    <th className="py-3.5 px-4">Handbook Topic & Manual Page</th>
                    <th className="py-3.5 px-4">Category</th>
                    <th className="py-3.5 px-4">Reviewing Office</th>
                    <th className="py-3.5 px-4">Approval Status</th>
                    <th className="py-3.5 px-4">Publication</th>
                    <th className="py-3.5 px-4 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-xs">
                  {entries.map((entry) => {
                    const rowPublishEligible = isPublishEligible(entry);
                    const rowPublishReason = getPublishDisabledReason(entry);

                    return (
                      <tr key={entry.id} className="hover:bg-slate-50/80">
                        <td className="py-3.5 px-4 font-mono font-bold text-[#1e3a8a]">
                          {entry.entry_code}
                          <span className="block text-[10px] text-slate-400">v{entry.version}</span>
                        </td>
                        <td className="py-3.5 px-4 max-w-xs">
                          <div className="font-bold text-slate-900 flex items-center gap-1.5 flex-wrap">
                            {entry.is_sensitive && (
                              <span
                                className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-50 text-red-700 border border-red-200 shrink-0"
                                title="Sensitive Formal Reporting Pathway"
                              >
                                <ShieldAlert size={11} className="text-red-600 shrink-0" />
                                <span>Sensitive Pathway</span>
                              </span>
                            )}
                            <span>{entry.title}</span>
                          </div>
                          <div className="text-[11px] text-slate-500 mt-0.5">{entry.manual_page_number}</div>
                        </td>
                        <td className="py-3.5 px-4 font-medium text-slate-700">{entry.category}</td>
                        <td className="py-3.5 px-4 text-slate-600 max-w-[200px]">
                          <div className="font-semibold text-slate-800">{entry.primary_review_office || entry.responsible_office}</div>
                          {entry.validated_by && (
                            <div className="text-[11px] text-emerald-700">
                              Signed: {entry.validated_by} ({entry.validated_role})
                            </div>
                          )}
                        </td>
                        <td className="py-3.5 px-4">
                          <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${getApprovalBadgeStyle(entry.approval_status)}`}>
                            {entry.approval_status}
                          </span>
                        </td>
                        <td className="py-3.5 px-4">
                          <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${getPublicationBadgeStyle(entry.publication_status)}`}>
                            {entry.publication_status}
                          </span>
                        </td>
                        <td className="py-3.5 px-4 text-right space-x-2 whitespace-nowrap">
                          <button
                            type="button"
                            onClick={() => handleOpenEntryModal(entry)}
                            className="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-800 cursor-pointer"
                          >
                            <Edit3 size={12} />
                            <span>Review / Edit</span>
                          </button>

                          {entry.publication_status === 'Published' ? (
                            <button
                              type="button"
                              onClick={() => handleUnpublishEntry(entry.id)}
                              disabled={saving}
                              className="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold bg-amber-100 hover:bg-amber-200 text-amber-900 cursor-pointer"
                            >
                              <span>Unpublish</span>
                            </button>
                          ) : (
                            <button
                              type="button"
                              onClick={() => handlePublishEntry(entry.id)}
                              disabled={saving || !rowPublishEligible}
                              title={rowPublishReason}
                              className="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold bg-[#1e3a8a] hover:bg-blue-800 text-white disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                            >
                              <UploadCloud size={12} />
                              <span>Publish</span>
                            </button>
                          )}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      ) : (
        /* Aggregated Student Guide Usage & Feedback Analytics Tab */
        <div className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="card p-5 space-y-1">
              <span className="text-xs font-bold text-slate-500 uppercase">Historical Guided Sessions</span>
              <div className="text-2xl font-extrabold text-slate-900">
                {analytics?.usage_summary?.total_tasks_started || 0}
              </div>
              <p className="text-xs text-slate-500">
                {analytics?.usage_summary?.completed_tasks || 0} completed • {analytics?.usage_summary?.in_progress_tasks || 0} active • {analytics?.usage_summary?.blocked_tasks || 0} blocked
              </p>
            </div>

            <div className="card p-5 space-y-1">
              <span className="text-xs font-bold text-slate-500 uppercase">Student Guide Searches</span>
              <div className="text-2xl font-extrabold text-slate-900">
                {analytics?.usage_summary?.total_searches || 0}
              </div>
              <p className="text-xs text-slate-500">
                {analytics?.usage_summary?.unmatched_searches || 0} unmatched • {analytics?.usage_summary?.sensitive_guardrail_triggers || 0} safety pathway triggers
              </p>
            </div>

            <div className="card p-5 space-y-1">
              <span className="text-xs font-bold text-slate-500 uppercase">Article Helpfulness Rating</span>
              <div className="text-2xl font-extrabold text-emerald-700">
                {analytics?.feedback_summary?.helpful_rate !== null && analytics?.feedback_summary?.helpful_rate !== undefined
                  ? `${analytics.feedback_summary.helpful_rate}%`
                  : '—'}
              </div>
              <p className="text-xs text-slate-500">
                {analytics?.feedback_summary?.helpful_count || 0} helpful • {analytics?.feedback_summary?.not_helpful_count || 0} not helpful
              </p>
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Commonly Searched Issues */}
            <div className="card p-5 space-y-3">
              <h3 className="text-sm font-bold text-slate-900">Commonly Searched Student Guide Topics (Aggregated)</h3>
              {(analytics?.top_searches || []).length === 0 ? (
                <p className="text-xs text-slate-500">No search queries recorded yet.</p>
              ) : (
                <ul className="divide-y divide-slate-100 text-xs">
                  {analytics.top_searches.map((item, idx) => (
                    <li key={idx} className="py-2.5 flex items-center justify-between">
                      <span className="font-semibold text-slate-800">"{item.query}"</span>
                      <span className="text-slate-500">
                        {item.search_count} searches ({item.matched_count} matched)
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </div>

            {/* Historical Unresolved / Paused Sessions */}
            <div className="card p-5 space-y-3">
              <h3 className="text-sm font-bold text-slate-900">Historical Self-Service Session Records (Aggregated)</h3>
              {(analytics?.unresolved_sessions || []).length === 0 ? (
                <p className="text-xs text-slate-500">No historical unresolved sessions recorded.</p>
              ) : (
                <ul className="divide-y divide-slate-100 text-xs">
                  {analytics.unresolved_sessions.map((s, idx) => (
                    <li key={idx} className="py-2.5 flex items-center justify-between gap-2">
                      <div>
                        <span className="font-mono font-bold text-[#1e3a8a] mr-1.5">{s.entry_code}</span>
                        <span className="font-semibold text-slate-800">{s.task_title}</span>
                      </div>
                      <span className="text-slate-600 shrink-0">
                        {s.session_count} {s.status} ({Math.round(s.avg_progress || 0)}% avg)
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Centered Modal Dialog for Reviewing / Editing a Student Guide Article */}
      {selectedEntry && (
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby="sao-student-guide-modal-title"
          className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-2 sm:p-4"
          onClick={(e) => {
            if (e.target === e.currentTarget && !saving) {
              handleCloseEntryModal();
            }
          }}
        >
          <div className="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            {/* Fixed Modal Header */}
            <div className="px-5 py-4 sm:px-6 border-b border-slate-200 bg-slate-50/80 flex items-start justify-between gap-4 shrink-0">
              <div className="space-y-1 min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <span className="text-xs font-mono font-bold text-[#1e3a8a] bg-blue-50 px-2.5 py-0.5 rounded border border-blue-200">
                    Article {selectedEntry.entry_code} • v{selectedEntry.version}
                  </span>
                  <span className={`px-2.5 py-0.5 rounded-full text-[11px] font-bold ${getApprovalBadgeStyle(selectedEntry.approval_status)}`}>
                    Approval: {selectedEntry.approval_status}
                  </span>
                  <span className={`px-2.5 py-0.5 rounded-full text-[11px] font-bold ${getPublicationBadgeStyle(selectedEntry.publication_status)}`}>
                    Publication: {selectedEntry.publication_status}
                  </span>
                  {selectedEntry.is_sensitive && (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-red-50 text-red-700 border border-red-200">
                      <ShieldAlert size={12} />
                      <span>Sensitive Formal Reporting Pathway</span>
                    </span>
                  )}
                </div>
                <h2
                  id="sao-student-guide-modal-title"
                  className="text-base sm:text-lg font-extrabold text-slate-900 truncate"
                >
                  {selectedEntry.title}
                </h2>
              </div>

              <button
                type="button"
                onClick={handleCloseEntryModal}
                disabled={saving}
                aria-label="Close article editor modal"
                className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/70 transition-colors disabled:opacity-40 cursor-pointer shrink-0"
              >
                <X size={20} />
              </button>
            </div>

            {/* Scrollable Internal Modal Content Area */}
            <div className="flex-1 overflow-y-auto px-5 py-5 sm:px-6 space-y-6">
              {/* In-Modal Feedback Banner (Immediately Visible Inside the Modal!) */}
              {modalErrorMessage && (
                <div className="p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm flex items-start justify-between gap-3">
                  <div className="flex items-start gap-2.5">
                    <XCircle size={18} className="text-red-600 shrink-0 mt-0.5" />
                    <span>{modalErrorMessage}</span>
                  </div>
                  <button
                    type="button"
                    onClick={() => setModalErrorMessage(null)}
                    className="font-bold underline text-xs shrink-0"
                  >
                    Dismiss
                  </button>
                </div>
              )}

              {modalSuccessMessage && (
                <div className="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm flex items-start justify-between gap-3">
                  <div className="flex items-start gap-2.5">
                    <CheckCircle2 size={18} className="text-emerald-600 shrink-0 mt-0.5" />
                    <span>{modalSuccessMessage}</span>
                  </div>
                  <button
                    type="button"
                    onClick={() => setModalSuccessMessage(null)}
                    className="font-bold underline text-xs shrink-0"
                  >
                    Dismiss
                  </button>
                </div>
              )}

              {/* SECTION 1: Article Information */}
              <section className="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider">
                  1. Article Information
                </h3>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                  <div className="p-2.5 rounded-lg bg-white border border-slate-200">
                    <span className="text-[10px] font-bold text-slate-500 uppercase block">Entry Code</span>
                    <span className="font-mono font-bold text-[#1e3a8a] text-sm">{selectedEntry.entry_code}</span>
                  </div>
                  <div className="p-2.5 rounded-lg bg-white border border-slate-200">
                    <span className="text-[10px] font-bold text-slate-500 uppercase block">Version</span>
                    <span className="font-bold text-slate-900 text-sm">Version {selectedEntry.version}</span>
                  </div>
                  <div className="p-2.5 rounded-lg bg-white border border-slate-200">
                    <span className="text-[10px] font-bold text-slate-500 uppercase block">Lifecycle Stage</span>
                    <span className="font-bold text-slate-900">{selectedEntry.lifecycle_stage}</span>
                  </div>
                  <div className="p-2.5 rounded-lg bg-white border border-slate-200">
                    <span className="text-[10px] font-bold text-slate-500 uppercase block">Handbook Category</span>
                    <span className="font-bold text-slate-900">{selectedEntry.category}</span>
                  </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                  <div className="sm:col-span-2">
                    <label className="block text-xs font-bold text-slate-700 mb-1">Article Title</label>
                    <input
                      type="text"
                      value={approvalForm.title}
                      onChange={(e) => setApprovalForm({ ...approvalForm, title: e.target.value })}
                      className="input-field text-xs bg-white"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">Manual Page Reference</label>
                    <input
                      type="text"
                      value={approvalForm.manual_page_number}
                      onChange={(e) => setApprovalForm({ ...approvalForm, manual_page_number: e.target.value })}
                      className="input-field text-xs bg-white"
                    />
                  </div>
                </div>
                <p className="text-[11px] text-slate-500">
                  <strong>Source Document:</strong> {selectedEntry.source_reference || 'University of Cebu Student Manual (June 2019 Edition)'}
                </p>
              </section>

              {/* SECTION 2: Approval and Verification */}
              <section className="p-4 rounded-xl bg-white border border-slate-200 space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider">
                    2. Approval and Verification
                  </h3>
                  <span className="text-[11px] text-slate-500">
                    Evidence fields are only mandatory when changing Approval Status to <strong>Approved</strong>
                  </span>
                </div>

                <div className="p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-xs space-y-1">
                  <div className="font-bold flex items-center gap-1.5">
                    <AlertTriangle size={14} className="text-amber-700 shrink-0" />
                    <span>UCLM Office Verification & Reapproval Policy</span>
                  </div>
                  <p>
                    You may save ordinary article edits or draft notes while an entry remains <strong>Pending UCLM Validation</strong>. Setting an article to <strong>Approved</strong> requires verifying the signed UCLM office review packet and completing all four evidence fields below. Substantive edits to an already approved or published article will snapshot the previous version, unpublish it, and reset it to <strong>Pending UCLM Validation</strong>.
                  </p>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">Approval Status</label>
                    <select
                      value={approvalForm.approval_status}
                      onChange={(e) => setApprovalForm({ ...approvalForm, approval_status: e.target.value })}
                      className="input-field text-xs bg-white"
                    >
                      <option value="Pending UCLM Validation">Pending UCLM Validation</option>
                      <option value="Verbal Confirmation Pending Formal Approval">Verbal Confirmation Pending Formal Approval</option>
                      <option value="Approved">Approved (Requires Official Sign-Off)</option>
                      <option value="Needs Revision">Needs Revision</option>
                      <option value="Rejected">Rejected</option>
                    </select>
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Validated By (Authorized Name) {approvalForm.approval_status === 'Approved' && <span className="text-red-600">*</span>}
                    </label>
                    <input
                      type="text"
                      value={approvalForm.validated_by}
                      onChange={(e) => setApprovalForm({ ...approvalForm, validated_by: e.target.value })}
                      placeholder={approvalForm.approval_status === 'Approved' ? 'Required for Approved status' : 'Optional until Approved'}
                      className="input-field text-xs"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Validator Position / Role {approvalForm.approval_status === 'Approved' && <span className="text-red-600">*</span>}
                    </label>
                    <input
                      type="text"
                      value={approvalForm.validated_role}
                      onChange={(e) => setApprovalForm({ ...approvalForm, validated_role: e.target.value })}
                      placeholder="e.g., UCLM Registrar / SAO Director"
                      className="input-field text-xs"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Validating UCLM Office {approvalForm.approval_status === 'Approved' && <span className="text-red-600">*</span>}
                    </label>
                    <input
                      type="text"
                      value={approvalForm.validated_office}
                      onChange={(e) => setApprovalForm({ ...approvalForm, validated_office: e.target.value })}
                      placeholder="e.g., Office of the Registrar"
                      className="input-field text-xs"
                    />
                  </div>

                  <div className="sm:col-span-2">
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Supporting Official Reference / Memorandum {approvalForm.approval_status === 'Approved' && <span className="text-red-600">*</span>}
                    </label>
                    <input
                      type="text"
                      value={approvalForm.supporting_reference}
                      onChange={(e) => setApprovalForm({ ...approvalForm, supporting_reference: e.target.value })}
                      placeholder="e.g., Signed UCLM Review Packet 01 / Official Memorandum Reference"
                      className="input-field text-xs"
                    />
                  </div>
                </div>
              </section>

              {/* SECTION 3: Student Guide Article Content */}
              <section className="p-4 rounded-xl bg-white border border-slate-200 space-y-4">
                <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider">
                  3. Student Guide Article Content
                </h3>

                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">
                    Responsible University Office
                  </label>
                  <input
                    type="text"
                    value={approvalForm.responsible_office}
                    onChange={(e) => setApprovalForm({ ...approvalForm, responsible_office: e.target.value })}
                    className="input-field text-xs"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">
                    Approved Guide Article Overview / Answer
                  </label>
                  <textarea
                    rows={4}
                    value={approvalForm.approved_answer}
                    onChange={(e) => setApprovalForm({ ...approvalForm, approved_answer: e.target.value })}
                    className="input-field text-xs leading-relaxed"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">
                    Requirements & Documents to Prepare (One requirement per line)
                  </label>
                  <textarea
                    rows={3}
                    value={approvalForm.requirements_text}
                    onChange={(e) => setApprovalForm({ ...approvalForm, requirements_text: e.target.value })}
                    placeholder="Enter each documentary or policy requirement on its own line..."
                    className="input-field text-xs leading-relaxed"
                  />
                </div>

                {/* Steps and Instructions (Where Applicable) */}
                {!selectedEntry.is_sensitive ? (
                  <div className="space-y-3 pt-2 border-t border-slate-100">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <div>
                        <span className="text-xs font-bold text-slate-800 block">
                          Step-by-Step Procedure ({approvalForm.steps.length} {approvalForm.steps.length === 1 ? 'Step' : 'Steps'})
                        </span>
                        <span className="text-[11px] text-slate-500">
                          Displayed as sequential reading content in the UCLM Student Guide
                        </span>
                      </div>
                      <button
                        type="button"
                        onClick={handleAddStep}
                        className="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold bg-blue-50 text-[#1e3a8a] hover:bg-blue-100 border border-blue-200 cursor-pointer"
                      >
                        <Plus size={13} />
                        <span>Add Step</span>
                      </button>
                    </div>

                    {approvalForm.steps.length === 0 ? (
                      <p className="text-xs text-slate-500 italic p-3 rounded-lg bg-slate-50 border border-slate-200">
                        No steps configured for this article. Click "Add Step" to add a procedural step.
                      </p>
                    ) : (
                      <div className="space-y-3">
                        {approvalForm.steps.map((step, idx) => (
                          <div
                            key={idx}
                            className="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2.5"
                          >
                            <div className="flex items-center justify-between gap-2">
                              <span className="text-xs font-bold text-[#1e3a8a]">
                                Step {idx + 1}
                              </span>
                              <button
                                type="button"
                                onClick={() => handleRemoveStep(idx)}
                                className="inline-flex items-center gap-1 text-[11px] font-bold text-red-600 hover:text-red-800 cursor-pointer"
                              >
                                <Trash2 size={12} />
                                <span>Remove</span>
                              </button>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                              <div>
                                <label className="block text-[11px] font-bold text-slate-700 mb-0.5">
                                  Step Title
                                </label>
                                <input
                                  type="text"
                                  value={step.title}
                                  onChange={(e) => handleUpdateStepField(idx, 'title', e.target.value)}
                                  placeholder="e.g., Secure Affidavit of Loss"
                                  className="input-field text-xs bg-white"
                                />
                              </div>
                              <div>
                                <label className="block text-[11px] font-bold text-slate-700 mb-0.5">
                                  Office for This Step (Optional)
                                </label>
                                <input
                                  type="text"
                                  value={step.office || ''}
                                  onChange={(e) => handleUpdateStepField(idx, 'office', e.target.value)}
                                  placeholder="e.g., Student Affairs Office (SAO)"
                                  className="input-field text-xs bg-white"
                                />
                              </div>
                            </div>

                            <div>
                              <label className="block text-[11px] font-bold text-slate-700 mb-0.5">
                                Step Instructions
                              </label>
                              <textarea
                                rows={2}
                                value={step.instructions}
                                onChange={(e) => handleUpdateStepField(idx, 'instructions', e.target.value)}
                                className="input-field text-xs bg-white leading-relaxed"
                              />
                            </div>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                ) : (
                  <div className="p-3 rounded-lg bg-red-50/70 border border-red-200 text-xs text-red-900">
                    <strong>Sensitive Formal Reporting Pathway:</strong> This topic directs students to immediate university reporting authorities rather than a step-by-step self-service procedure.
                  </div>
                )}
              </section>

              {/* SECTION 4: Validation and Revision */}
              <section className="p-4 rounded-xl bg-white border border-slate-200 space-y-4">
                <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider">
                  4. Validation Notes & Revision History
                </h3>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Validation Notes from Reviewing Office
                    </label>
                    <input
                      type="text"
                      value={approvalForm.validation_notes}
                      onChange={(e) => setApprovalForm({ ...approvalForm, validation_notes: e.target.value })}
                      placeholder="Official verification notes or remarks from the UCLM reviewing office"
                      className="input-field text-xs"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Revision Summary (For Version Audit Log)
                    </label>
                    <input
                      type="text"
                      value={approvalForm.revision_summary}
                      onChange={(e) => setApprovalForm({ ...approvalForm, revision_summary: e.target.value })}
                      placeholder="Optional summary of procedural updates in this save"
                      className="input-field text-xs"
                    />
                  </div>
                </div>

                {/* Historical Revision Snapshots */}
                <div className="pt-3 border-t border-slate-100 space-y-2.5">
                  <div className="flex items-center justify-between gap-2">
                    <h4 className="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                      <History size={14} className="text-[#1e3a8a]" />
                      <span>Historical Revision Snapshots ({entryRevisions.length})</span>
                    </h4>
                    <span className="text-[11px] text-slate-500">
                      Current Active Version: v{selectedEntry.version}
                    </span>
                  </div>

                  {loadingEntryDetails ? (
                    <p className="text-xs text-slate-500">Loading revision history...</p>
                  ) : entryRevisions.length === 0 ? (
                    <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600">
                      No prior revision snapshots recorded for <strong>{selectedEntry.entry_code}</strong>. When substantive procedural guidance is modified on an article, its previous version and approval metadata are automatically archived here.
                    </div>
                  ) : (
                    <div className="space-y-2.5">
                      {entryRevisions.map((rev) => (
                        <div
                          key={rev.id}
                          className="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-1.5"
                        >
                          <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="flex flex-wrap items-center gap-2">
                              <span className="font-mono font-bold text-[#1e3a8a] bg-blue-50 px-2 py-0.5 rounded border border-blue-200">
                                Snapshot v{rev.version}
                              </span>
                              <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${getApprovalBadgeStyle(rev.approval_status)}`}>
                                {rev.approval_status}
                              </span>
                              <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${getPublicationBadgeStyle(rev.publication_status)}`}>
                                {rev.publication_status}
                              </span>
                            </div>
                            <span className="text-[11px] text-slate-500">
                              {rev.created_at ? new Date(rev.created_at).toLocaleString() : '—'}
                              {rev.changer?.name ? ` • by ${rev.changer.name}` : ''}
                            </span>
                          </div>

                          <div className="font-bold text-slate-900">{rev.title}</div>

                          {rev.change_notes && (
                            <div className="text-slate-700">
                              <strong className="text-slate-800">Revision Notes:</strong> {rev.change_notes}
                            </div>
                          )}

                          {rev.validated_by && (
                            <div className="text-[11px] text-slate-600">
                              <strong>Prior Approval Record:</strong> {rev.validated_by} ({rev.validated_role}, {rev.validated_office})
                              {rev.supporting_reference ? ` • Ref: ${rev.supporting_reference}` : ''}
                            </div>
                          )}
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </section>
            </div>

            {/* SECTION 5: Fixed Action Footer */}
            <div className="px-5 py-4 sm:px-6 border-t border-slate-200 bg-slate-50 shrink-0 space-y-2.5">
              {selectedEntry.publication_status !== 'Published' && !canPublishSelected && (
                <div className="text-[11px] text-amber-800 bg-amber-50 border border-amber-200 px-3 py-1.5 rounded-lg flex items-center gap-2">
                  <AlertTriangle size={13} className="text-amber-700 shrink-0" />
                  <span>{selectedPublishReason}</span>
                </div>
              )}

              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                {/* Mark Needs Revision Controls */}
                <div className="flex flex-wrap items-center gap-2">
                  <input
                    type="text"
                    value={revisionReason}
                    onChange={(e) => setRevisionReason(e.target.value)}
                    placeholder="Reason for marking Needs Revision..."
                    className="input-field text-xs w-full sm:w-60 bg-white"
                  />
                  <button
                    type="button"
                    onClick={() => handleMarkNeedsRevision(selectedEntry.id)}
                    disabled={saving}
                    className="btn-secondary text-xs text-red-700 border-red-200 hover:bg-red-50 shrink-0"
                  >
                    Mark Needs Revision
                  </button>
                </div>

                {/* Primary Modal Actions: Close, Save Changes, Publish / Unpublish */}
                <div className="flex flex-wrap items-center justify-end gap-2">
                  <button
                    type="button"
                    onClick={handleCloseEntryModal}
                    disabled={saving}
                    className="btn-secondary text-xs py-2 px-3.5"
                  >
                    Close
                  </button>

                  <button
                    type="button"
                    onClick={handleSaveEntryUpdate}
                    disabled={saving}
                    className="btn-primary text-xs py-2 px-4 inline-flex items-center gap-1.5"
                  >
                    <Save size={14} />
                    <span>{saving ? 'Saving Changes...' : 'Save Changes'}</span>
                  </button>

                  {selectedEntry.publication_status === 'Published' ? (
                    <button
                      type="button"
                      onClick={() => handleUnpublishEntry(selectedEntry.id)}
                      disabled={saving}
                      className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-bold bg-amber-100 hover:bg-amber-200 text-amber-900 disabled:opacity-40 cursor-pointer"
                    >
                      <span>Unpublish</span>
                    </button>
                  ) : (
                    <button
                      type="button"
                      onClick={() => handlePublishEntry(selectedEntry.id)}
                      disabled={saving || !canPublishSelected}
                      title={selectedPublishReason}
                      className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-bold bg-emerald-700 hover:bg-emerald-800 text-white disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                    >
                      <UploadCloud size={14} />
                      <span>Publish</span>
                    </button>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default SaoSelfServiceManagement;
