import { useState, useEffect, useCallback, useMemo } from 'react';
import { Link } from 'react-router-dom';
import {
  HelpCircle,
  Search,
  BookOpen,
  FileCheck2,
  Wallet,
  CreditCard,
  Library,
  Building2,
  Laptop,
  Users,
  Stethoscope,
  HeartHandshake,
  ShieldAlert,
  MoreHorizontal,
  CheckCircle2,
  ThumbsUp,
  ThumbsDown,
  ArrowRight,
  PlusSquare,
  ListTodo,
  FileText,
  Info,
  XCircle,
  Bookmark,
  Compass
} from 'lucide-react';
import api from '../api';
import PageHeader from '../components/PageHeader';
import LoadingSpinner from '../components/LoadingSpinner';

const CATEGORY_ICONS = {
  'Academic': BookOpen,
  'Enrollment': FileCheck2,
  'Financial': Wallet,
  'Student ID': CreditCard,
  'Library': Library,
  'Facilities': Building2,
  'Technology': Laptop,
  'Student Services': Users,
  'Medical': Stethoscope,
  'Guidance': HeartHandshake,
  'Safety and Security': ShieldAlert,
  'Other Concerns': MoreHorizontal,
};

const StudentNeedHelp = () => {
  const [categories, setCategories] = useState([]);
  const [selectedCategory, setSelectedCategory] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [submittedSearch, setSubmittedSearch] = useState('');

  const [entries, setEntries] = useState([]);
  const [allPublishedEntries, setAllPublishedEntries] = useState([]);
  const [sensitiveGuardrail, setSensitiveGuardrail] = useState(null);
  const [unavailableNotice, setUnavailableNotice] = useState(null);

  const [selectedEntry, setSelectedEntry] = useState(null);
  const [feedbackState, setFeedbackState] = useState({});

  const [loadingInitial, setLoadingInitial] = useState(true);
  const [searching, setSearching] = useState(false);
  const [errorMessage, setErrorMessage] = useState(null);

  const fetchCategories = useCallback(async () => {
    try {
      const catRes = await api.get('/self-service/categories');
      setCategories(catRes.data.categories || []);
    } catch (err) {
      console.error('Failed to load student guide categories', err);
      setErrorMessage(err.response?.data?.message || 'Unable to load UCLM Student Guide categories.');
    }
  }, []);

  const fetchEntries = useCallback(async (queryText = '', categoryId = '', isInitial = false) => {
    setSearching(true);
    setErrorMessage(null);
    try {
      const params = new URLSearchParams();
      if (queryText.trim()) params.set('q', queryText.trim());
      if (categoryId) params.set('category', categoryId);

      const res = await api.get(`/self-service/entries?${params.toString()}`);
      const fetchedEntries = res.data.entries || [];
      setEntries(fetchedEntries);
      if (isInitial && !queryText.trim() && !categoryId) {
        setAllPublishedEntries(fetchedEntries);
      }
      setSensitiveGuardrail(res.data.sensitive_guardrail || null);
      setUnavailableNotice(res.data.unavailable_notice || null);

      if (fetchedEntries.length > 0) {
        setSelectedEntry((prev) =>
          prev && fetchedEntries.some((e) => e.id === prev.id) ? prev : fetchedEntries[0]
        );
      } else {
        setSelectedEntry(null);
      }
    } catch (err) {
      console.error('Failed to load student guide articles', err);
      setErrorMessage(err.response?.data?.message || 'Unable to load UCLM Student Guide articles.');
    } finally {
      setSearching(false);
      setLoadingInitial(false);
    }
  }, []);

  useEffect(() => {
    fetchCategories();
    fetchEntries('', '', true);
  }, [fetchCategories, fetchEntries]);

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    setSubmittedSearch(searchQuery);
    fetchEntries(searchQuery, selectedCategory);
  };

  const handleSelectCategory = (categoryId) => {
    const nextCategory = selectedCategory === categoryId ? '' : categoryId;
    setSelectedCategory(nextCategory);
    fetchEntries(searchQuery, nextCategory);
  };

  const handleClearSearchAndFilters = () => {
    setSearchQuery('');
    setSubmittedSearch('');
    setSelectedCategory('');
    fetchEntries('', '');
  };

  const handleSubmitFeedback = async (kbEntryId, isHelpful) => {
    try {
      await api.post('/self-service/feedback', {
        kb_entry_id: kbEntryId,
        is_helpful: isHelpful,
      });
      setFeedbackState((prev) => ({
        ...prev,
        [kbEntryId]: { submitted: true, isHelpful },
      }));
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Could not submit helpfulness feedback.');
    }
  };

  // Related topics in the same category (or other published articles if none in same category)
  const relatedTopics = useMemo(() => {
    if (!selectedEntry) return [];
    const pool = allPublishedEntries.length > 0 ? allPublishedEntries : entries;
    const sameCategory = pool.filter(
      (item) => item.id !== selectedEntry.id && item.category === selectedEntry.category
    );
    if (sameCategory.length > 0) {
      return sameCategory.slice(0, 4);
    }
    return pool.filter((item) => item.id !== selectedEntry.id).slice(0, 4);
  }, [selectedEntry, allPublishedEntries, entries]);

  if (loadingInitial) {
    return <LoadingSpinner message="Loading UCLM Student Guide..." fullPage />;
  }

  return (
    <div className="space-y-6 sm:space-y-8 pb-12">
      {/* 1. Welcoming Handbook Header with Prominent Submit a Concern Option */}
      <PageHeader
        category="Digital University Handbook • Need Help?"
        title="UCLM Student Guide"
        subtitle="A simplified digital guide to the University of Cebu Lapu-Lapu and Mandaue (UCLM) Student Manual. Search questions, browse topics, review documentary requirements, and identify the responsible university office."
        breadcrumbs={[
          { label: 'Dashboard', path: '/student' },
          { label: 'UCLM Student Guide' },
        ]}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <Link to="/student/concerns" className="btn-secondary text-xs py-2 px-3">
              <ListTodo size={14} className="mr-1.5" />
              <span>My Concerns</span>
            </Link>
            <Link to="/student/submit" className="btn-primary text-xs py-2 px-3.5">
              <PlusSquare size={14} className="mr-1.5" />
              <span>Submit a Concern</span>
            </Link>
          </div>
        }
      />

      {/* Short Explanation of the Guide & Independent Concern Submission */}
      <div className="card p-4 sm:p-5 bg-gradient-to-r from-blue-50/80 via-white to-slate-50 border border-blue-200/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div className="flex items-start gap-3.5">
          <div className="w-9 h-9 rounded-xl bg-[#1e3a8a] text-white flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
            <BookOpen size={18} />
          </div>
          <div className="text-xs sm:text-sm text-slate-700 leading-relaxed space-y-0.5">
            <p className="font-bold text-slate-900">About the Digital UCLM Student Guide</p>
            <p className="text-xs text-slate-600">
              This guide provides verified reference articles based on the official Student Manual so you can quickly check requirements, step-by-step procedures, and responsible offices. Reading this guide is optional — you can submit a formal concern at any time.
            </p>
          </div>
        </div>
        <Link
          to="/student/submit"
          className="inline-flex items-center gap-1.5 text-xs font-bold text-[#1e3a8a] hover:text-blue-800 bg-white px-3.5 py-2 rounded-lg border border-blue-200 shadow-2xs shrink-0 transition-colors"
        >
          <span>Go to Submit a Concern</span>
          <ArrowRight size={13} />
        </Link>
      </div>

      {/* Error Alert */}
      {errorMessage && (
        <div className="p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm flex items-start justify-between gap-3">
          <div className="flex items-start gap-2.5">
            <XCircle size={18} className="shrink-0 mt-0.5 text-red-600" />
            <span>{errorMessage}</span>
          </div>
          <button onClick={() => setErrorMessage(null)} className="text-xs font-bold underline">
            Dismiss
          </button>
        </div>
      )}

      {/* Prominent Search Bar */}
      <div className="card p-5 sm:p-6 bg-white border border-slate-200/90 shadow-2xs">
        <form onSubmit={handleSearchSubmit} className="space-y-3">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <label className="block text-xs font-bold text-slate-800 uppercase tracking-wider">
              Search the UCLM Student Guide
            </label>
            <span className="text-[11px] text-slate-500">
              Ask a natural question or search by keyword, document, or office
            </span>
          </div>
          <div className="flex flex-col sm:flex-row gap-3">
            <div className="relative flex-1">
              <Search size={18} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="e.g., How do I replace a lost school ID, request my Transcript of Records, or validate a medical certificate?"
                className="input-field pl-10"
              />
            </div>
            <button
              type="submit"
              disabled={searching}
              className="btn-primary text-xs sm:text-sm px-5 py-2.5 shrink-0"
            >
              {searching ? 'Searching Guide...' : 'Search Guide'}
            </button>
            {(submittedSearch || selectedCategory) && (
              <button
                type="button"
                onClick={handleClearSearchAndFilters}
                className="btn-secondary text-xs px-3.5 py-2.5 shrink-0"
              >
                Reset
              </button>
            )}
          </div>
        </form>
      </div>

      {/* Sensitive & Urgent Reporting Guardrail (S1–S7) */}
      {sensitiveGuardrail?.triggered && (
        <div className="card p-6 border-2 border-red-300 bg-red-50/90 space-y-4">
          <div className="flex items-start gap-3">
            <div className="w-10 h-10 rounded-xl bg-red-600 text-white flex items-center justify-center shrink-0">
              <ShieldAlert size={22} />
            </div>
            <div className="space-y-1">
              <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-red-100 text-red-800 border border-red-300">
                Formal Reporting & Emergency Assistance Pathway
              </span>
              <h2 className="text-base sm:text-lg font-extrabold text-red-950">
                Direct Formal Reporting or University Personnel Assistance Recommended
              </h2>
              <p className="text-xs sm:text-sm text-red-900 leading-relaxed">
                Safety, security, harassment, violence, hazing, formal disciplinary complaints, and medical emergencies are handled through official University reporting channels rather than ordinary self-help articles. Please review the official Student Manual pathways below or submit a formal concern directly.
              </p>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4">
            {(sensitiveGuardrail.pathways || []).map((pathway) => (
              <div key={pathway.entry_code} className="p-4 rounded-xl bg-white border border-red-200 space-y-2.5">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <h3 className="text-sm font-bold text-slate-900">{pathway.title}</h3>
                  <span className="text-[11px] font-semibold text-slate-500">{pathway.source_reference}</span>
                </div>
                <p className="text-xs sm:text-sm text-slate-700 leading-relaxed">{pathway.message}</p>
                <div className="text-xs text-slate-800 bg-slate-50 p-3 rounded-lg border border-slate-200 space-y-1">
                  <div>
                    <span className="font-bold">Responsible University Office / Authority:</span> {pathway.responsible_office}
                  </div>
                  {pathway.privacy_notice && (
                    <div className="text-slate-600">
                      <span className="font-bold text-slate-800">Privacy & Policy Note:</span> {pathway.privacy_notice}
                    </div>
                  )}
                </div>
              </div>
            ))}
          </div>

          <div className="pt-2 flex flex-wrap items-center justify-between gap-3 border-t border-red-200">
            <p className="text-xs text-red-900 font-medium">
              Need to submit an official report or concern right away?
            </p>
            <Link
              to="/student/submit"
              className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-red-700 hover:bg-red-800 text-white text-xs sm:text-sm font-bold shadow-xs transition-colors"
            >
              <PlusSquare size={16} />
              <span>Submit a Formal Concern Now</span>
              <ArrowRight size={15} />
            </Link>
          </div>
        </div>
      )}

      {/* Organized Student-Friendly Categories (12 Categories) */}
      <div className="space-y-3">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-base font-bold text-slate-900">Browse Handbook Categories</h2>
            <p className="text-xs text-slate-500">
              Select a category to filter Student Manual topics and university procedures
            </p>
          </div>
          {selectedCategory && (
            <button
              type="button"
              onClick={() => handleSelectCategory(selectedCategory)}
              className="text-xs font-bold text-blue-600 hover:text-blue-800 cursor-pointer"
            >
              Show All Categories
            </button>
          )}
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3.5">
          {categories.map((cat) => {
            const Icon = CATEGORY_ICONS[cat.id] || HelpCircle;
            const isSelected = selectedCategory === cat.id;
            const isSafety = cat.id === 'Safety and Security';

            return (
              <button
                key={cat.id}
                type="button"
                onClick={() => handleSelectCategory(cat.id)}
                className={`text-left p-4 rounded-xl border transition-all cursor-pointer flex flex-col justify-between gap-2.5 ${
                  isSelected
                    ? isSafety
                      ? 'border-2 border-red-600 bg-red-50/60 shadow-xs'
                      : 'border-2 border-[#1e3a8a] bg-blue-50/60 shadow-xs'
                    : 'border-slate-200 bg-white hover:border-blue-300 hover:shadow-2xs'
                }`}
              >
                <div className="space-y-2">
                  <div className="flex items-center justify-between gap-2">
                    <div
                      className={`w-9 h-9 rounded-lg flex items-center justify-center shrink-0 ${
                        isSafety
                          ? 'bg-red-50 text-red-700 border border-red-200'
                          : 'bg-blue-50 text-[#1e3a8a] border border-blue-100'
                      }`}
                    >
                      <Icon size={18} />
                    </div>
                    <span
                      className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                        isSafety
                          ? 'bg-red-100 text-red-800 border border-red-200'
                          : cat.published_entries_count > 0
                            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                            : 'bg-slate-100 text-slate-600 border border-slate-200'
                      }`}
                    >
                      {isSafety
                        ? 'Formal Reporting'
                        : `${cat.published_entries_count} ${cat.published_entries_count === 1 ? 'Article' : 'Articles'}`}
                    </span>
                  </div>
                  <div>
                    <h3 className="text-sm font-bold text-slate-900">{cat.name}</h3>
                    <p className="text-[11px] text-slate-500 mt-0.5 line-clamp-2 leading-relaxed">
                      {(cat.subtopics || []).join(' • ')}
                    </p>
                  </div>
                </div>
              </button>
            );
          })}
        </div>
      </div>

      {/* Guide Articles & Reading View */}
      <div className="space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div>
            <h2 className="text-base font-bold text-slate-900">
              Student Guide Articles
              {selectedCategory ? ` — ${selectedCategory}` : ''}
              {submittedSearch ? ` (Search: "${submittedSearch}")` : ''}
            </h2>
            <p className="text-xs text-slate-500">
              Verified policies, requirements, and step-by-step procedures from the UCLM Student Manual
            </p>
          </div>
          <span className="text-xs font-bold text-slate-600">
            {entries.length} {entries.length === 1 ? 'Published Article' : 'Published Articles'}
          </span>
        </div>

        {/* Empty / Verified Guidance Not Currently Available State */}
        {entries.length === 0 && unavailableNotice && !sensitiveGuardrail?.triggered && (
          <div className="card p-6 sm:p-8 text-center space-y-4 border border-slate-200 bg-white">
            <div className="w-12 h-12 rounded-2xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center mx-auto">
              <FileText size={24} />
            </div>
            <div className="max-w-xl mx-auto space-y-2">
              <h3 className="text-base font-bold text-slate-900">
                Verified Guide Articles Are Not Currently Available
              </h3>
              <p className="text-xs sm:text-sm text-slate-600 leading-relaxed">
                {unavailableNotice.message}
              </p>
            </div>
            <div className="pt-2 flex flex-wrap items-center justify-center gap-3">
              {(submittedSearch || selectedCategory) && (
                <button
                  type="button"
                  onClick={handleClearSearchAndFilters}
                  className="btn-secondary text-xs py-2.5 px-4"
                >
                  View All Categories
                </button>
              )}
              <Link to="/student/submit" className="btn-primary text-xs py-2.5 px-4">
                <PlusSquare size={14} className="mr-1.5" />
                <span>Submit a Formal Concern</span>
              </Link>
            </div>
          </div>
        )}

        {/* Topic / Article Cards + Clean Handbook Article Reader */}
        {entries.length > 0 && (
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {/* Left Sidebar: Topic / Article Cards */}
            <div className="space-y-3 lg:col-span-4">
              <div className="text-xs font-bold text-slate-500 uppercase tracking-wider px-1">
                Topics ({entries.length})
              </div>
              {entries.map((entry) => {
                const isCurrent = selectedEntry?.id === entry.id;
                return (
                  <button
                    key={entry.id}
                    type="button"
                    onClick={() => setSelectedEntry(entry)}
                    className={`w-full text-left p-4 rounded-xl border transition-all cursor-pointer space-y-2 ${
                      isCurrent
                        ? 'border-2 border-[#1e3a8a] bg-blue-50/50 shadow-xs'
                        : 'border-slate-200 bg-white hover:border-blue-300'
                    }`}
                  >
                    <div className="flex items-center justify-between gap-2">
                      <span className="text-[11px] font-semibold px-2.5 py-0.5 rounded-full bg-blue-50 text-[#1e3a8a] border border-blue-200">
                        {entry.category}
                      </span>
                      <span className="text-[11px] text-slate-400 font-medium">
                        {entry.manual_page_number}
                      </span>
                    </div>
                    <h3 className="text-sm font-bold text-slate-900 leading-snug">{entry.title}</h3>
                    <p className="text-xs text-slate-600 line-clamp-2 leading-relaxed">
                      {entry.approved_answer}
                    </p>
                    <div className="text-[11px] text-slate-500 pt-1.5 border-t border-slate-100 flex items-center justify-between gap-2">
                      <span className="truncate">
                        <strong className="text-slate-700">Office:</strong> {entry.responsible_office}
                      </span>
                      <ArrowRight size={12} className="text-[#1e3a8a] shrink-0" />
                    </div>
                  </button>
                );
              })}
            </div>

            {/* Right Column: Digital Handbook Article Reading Panel */}
            {selectedEntry && (
              <article className="card p-6 sm:p-8 lg:col-span-8 space-y-6 bg-white border border-slate-200">
                {/* 1. Topic Title & Handbook Metadata Header */}
                <header className="space-y-3 border-b border-slate-200 pb-5">
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-[#1e3a8a] border border-blue-200">
                      {selectedEntry.category}
                    </span>
                    <span className="px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">
                      Topic {selectedEntry.entry_code}
                    </span>
                  </div>
                  <h1 className="text-xl sm:text-2xl font-extrabold text-slate-900 leading-tight">
                    {selectedEntry.title}
                  </h1>
                  <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                    <span>
                      <strong className="text-slate-700">Source:</strong> {selectedEntry.source_reference}
                    </span>
                    <span>•</span>
                    <span>
                      <strong className="text-slate-700">Manual Reference:</strong> {selectedEntry.manual_page_number}
                    </span>
                  </div>
                </header>

                {/* 2. Short, Understandable Explanation & 3. What Students Need to Know */}
                <section className="space-y-3">
                  <h2 className="text-xs font-bold text-[#1e3a8a] uppercase tracking-wider flex items-center gap-1.5">
                    <Info size={14} />
                    <span>Overview & What You Need to Know</span>
                  </h2>
                  <div className="p-4 sm:p-5 rounded-xl bg-slate-50/80 border border-slate-200/90 text-sm text-slate-800 leading-relaxed space-y-3">
                    <p>{selectedEntry.approved_answer}</p>

                    {selectedEntry.common_questions?.length > 0 && (
                      <div className="pt-3 border-t border-slate-200/80 space-y-1.5">
                        <span className="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">
                          Common Questions Covered by This Topic
                        </span>
                        <ul className="list-disc list-inside text-xs text-slate-700 space-y-1">
                          {selectedEntry.common_questions.map((q, idx) => (
                            <li key={idx}>{q}</li>
                          ))}
                        </ul>
                      </div>
                    )}
                  </div>
                </section>

                {/* 6. Responsible University Office */}
                <section className="p-4 sm:p-5 rounded-xl bg-blue-50/60 border border-blue-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div className="space-y-1">
                    <span className="text-[11px] font-bold text-[#1e3a8a] uppercase tracking-wider block">
                      Responsible University Office
                    </span>
                    <p className="text-sm sm:text-base font-bold text-slate-900">
                      {selectedEntry.responsible_office}
                    </p>
                    <p className="text-xs text-slate-600">
                      Visit or coordinate with this office for official processing, verification, or questions about this procedure.
                    </p>
                  </div>
                </section>

                {/* 4. Requirements (Where Applicable) */}
                {selectedEntry.requirements?.length > 0 && (
                  <section className="space-y-3">
                    <h2 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                      <FileCheck2 size={14} className="text-[#1e3a8a]" />
                      <span>Requirements & Documents to Prepare</span>
                    </h2>
                    <div className="p-4 sm:p-5 rounded-xl bg-white border border-slate-200">
                      <ul className="space-y-2.5 text-xs sm:text-sm text-slate-700">
                        {selectedEntry.requirements.map((req, idx) => (
                          <li key={idx} className="flex items-start gap-2.5">
                            <span className="w-1.5 h-1.5 rounded-full bg-[#1e3a8a] shrink-0 mt-2" />
                            <span className="leading-relaxed">{req}</span>
                          </li>
                        ))}
                      </ul>
                    </div>
                  </section>
                )}

                {/* 5. Step-by-Step Procedure (Displayed as Clean Handbook Reading Content) */}
                {selectedEntry.steps?.length > 0 && (
                  <section className="space-y-3">
                    <h2 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                      <Compass size={14} className="text-[#1e3a8a]" />
                      <span>Step-by-Step Procedure</span>
                    </h2>
                    <ol className="space-y-3">
                      {selectedEntry.steps.map((step, idx) => {
                        const stepNum = step.step_number || idx + 1;
                        return (
                          <li
                            key={stepNum}
                            className="p-4 sm:p-5 rounded-xl bg-slate-50/70 border border-slate-200 flex items-start gap-3.5"
                          >
                            <div className="w-7 h-7 rounded-lg bg-[#1e3a8a] text-white text-xs font-bold flex items-center justify-center shrink-0 mt-0.5">
                              {stepNum}
                            </div>
                            <div className="space-y-1.5 flex-1 min-w-0">
                              <div className="flex flex-wrap items-center justify-between gap-2">
                                <h3 className="text-sm font-bold text-slate-900">
                                  {step.title}
                                </h3>
                                {step.office && (
                                  <span className="text-[11px] font-semibold px-2.5 py-0.5 rounded-md bg-white text-slate-700 border border-slate-200">
                                    {step.office}
                                  </span>
                                )}
                              </div>
                              <p className="text-xs sm:text-sm text-slate-700 leading-relaxed">
                                {step.instructions}
                              </p>
                              {step.requirements?.length > 0 && (
                                <div className="pt-1.5">
                                  <span className="text-[11px] font-bold text-slate-600 block">
                                    What to bring / prepare for this step:
                                  </span>
                                  <ul className="list-disc list-inside text-xs text-slate-600 mt-0.5 space-y-0.5">
                                    {step.requirements.map((r, rIdx) => (
                                      <li key={rIdx}>{r}</li>
                                    ))}
                                  </ul>
                                </div>
                              )}
                            </div>
                          </li>
                        );
                      })}
                    </ol>
                  </section>
                )}

                {/* 7. Source Reference & Original Student Manual Page Number */}
                <section className="p-4 rounded-xl bg-slate-50 border border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-slate-600">
                  <div className="flex items-center gap-2">
                    <Bookmark size={14} className="text-[#1e3a8a] shrink-0" />
                    <span>
                      <strong className="text-slate-800">Handbook Reference:</strong> {selectedEntry.source_reference}
                    </span>
                  </div>
                  <span className="font-semibold text-slate-800">
                    {selectedEntry.manual_page_number}
                  </span>
                </section>

                {/* 8. Related Topics (When Applicable) */}
                {relatedTopics.length > 0 && (
                  <section className="space-y-2.5 pt-2 border-t border-slate-100">
                    <h2 className="text-xs font-bold text-slate-700 uppercase tracking-wider">
                      Related Student Guide Topics
                    </h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                      {relatedTopics.map((rel) => (
                        <button
                          key={rel.id}
                          type="button"
                          onClick={() => setSelectedEntry(rel)}
                          className="text-left p-3 rounded-xl border border-slate-200 hover:border-blue-300 bg-slate-50/50 hover:bg-blue-50/40 transition-colors cursor-pointer flex items-center justify-between gap-2"
                        >
                          <div className="min-w-0">
                            <span className="text-[10px] font-bold text-[#1e3a8a] uppercase block">
                              {rel.category} • {rel.manual_page_number}
                            </span>
                            <span className="text-xs font-bold text-slate-900 truncate block mt-0.5">
                              {rel.title}
                            </span>
                          </div>
                          <ArrowRight size={14} className="text-slate-400 shrink-0" />
                        </button>
                      ))}
                    </div>
                  </section>
                )}

                {/* Optional Article Helpfulness Feedback */}
                <footer className="p-4 rounded-xl bg-slate-50 border border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div>
                    <h4 className="text-xs font-bold text-slate-900">Was this guide article helpful?</h4>
                    <p className="text-[11px] text-slate-500">
                      Your feedback helps improve the UCLM Student Guide and does not create a concern ticket.
                    </p>
                  </div>

                  {feedbackState[selectedEntry.id]?.submitted ? (
                    <span className="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-200">
                      <CheckCircle2 size={14} />
                      <span>Thank you for your feedback!</span>
                    </span>
                  ) : (
                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        onClick={() => handleSubmitFeedback(selectedEntry.id, true)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-white hover:bg-emerald-50 text-slate-700 hover:text-emerald-700 border border-slate-200 hover:border-emerald-300 transition-colors cursor-pointer"
                      >
                        <ThumbsUp size={14} />
                        <span>Yes, Helpful</span>
                      </button>
                      <button
                        type="button"
                        onClick={() => handleSubmitFeedback(selectedEntry.id, false)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-white hover:bg-amber-50 text-slate-700 hover:text-amber-800 border border-slate-200 hover:border-amber-300 transition-colors cursor-pointer"
                      >
                        <ThumbsDown size={14} />
                        <span>Not Helpful</span>
                      </button>
                    </div>
                  )}
                </footer>
              </article>
            )}
          </div>
        )}
      </div>

      {/* Prominent Bottom Option to Submit a Concern */}
      <div className="card p-6 bg-white border border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div className="space-y-1">
          <h3 className="text-base font-bold text-slate-900">Need Formal Assistance from a University Office?</h3>
          <p className="text-xs sm:text-sm text-slate-500 max-w-2xl">
            If your question is not covered in the UCLM Student Guide or your concern requires direct action by your College Dean or an Operational Department, submit a formal concern anytime.
          </p>
        </div>
        <Link
          to="/student/submit"
          className="btn-primary text-xs sm:text-sm py-2.5 px-5 shrink-0"
        >
          <PlusSquare size={15} className="mr-1.5" />
          <span>Submit a Concern</span>
        </Link>
      </div>
    </div>
  );
};

export default StudentNeedHelp;
