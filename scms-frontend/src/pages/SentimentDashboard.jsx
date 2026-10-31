import React, { useState, useEffect, useMemo, useCallback, Component } from 'react';
import { Link } from 'react-router-dom';
import {
  PieChart,
  Pie,
  Cell,
  Tooltip,
  ResponsiveContainer,
  LineChart,
  Line,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Legend,
} from 'recharts';
import {
  Sparkles,
  PieChart as PieIcon,
  TrendingUp,
  Tag,
  AlertTriangle,
  RefreshCw,
  Info,
  Activity,
  BarChart3,
  ShieldCheck,
  Filter,
  CheckCircle2,
  Clock,
  Layers,
  ArrowLeft,
  MessageSquareText,
  Hash,
  Quote,
  Building2,
  ExternalLink,
  SlidersHorizontal,
} from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';
import api from '../api';
import StatusBadge from '../components/StatusBadge';
import PriorityBadge from '../components/PriorityBadge';
import SentimentBadge from '../components/SentimentBadge';
import AiCategoryBadge from '../components/AiCategoryBadge';
import EmptyState from '../components/EmptyState';
import LoadingSpinner from '../components/LoadingSpinner';
import ResolutionAnalyticsSection from '../components/ResolutionAnalyticsSection';

// ─── Error Boundary to prevent any chart from crashing the whole page ─────────

class ChartErrorBoundary extends Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, error: null };
  }

  static getDerivedStateFromError(error) {
    return { hasError: true, error };
  }

  componentDidCatch(error, errorInfo) {
    console.error('ChartErrorBoundary caught error:', error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="h-56 flex flex-col items-center justify-center bg-slate-50 rounded-xl border border-slate-200 text-slate-400 p-4 text-center">
          <AlertTriangle size={24} className="text-amber-500 mb-2" />
          <p className="text-xs font-semibold text-slate-700">Unable to display this visualization</p>
          <p className="text-[11px] text-slate-400 mt-0.5">The chart encountered an issue, but your data is safe.</p>
        </div>
      );
    }
    return this.props.children;
  }
}

// ─── Unified SCMS Color Palette (#2563EB, #1E3A8A, #10B981, #F59E0B, #EF4444) ─

const SENTIMENT_COLORS = {
  positive: '#10B981', // Success Green
  neutral:  '#64748B', // Secondary Slate
  negative: '#F59E0B', // Warning Amber for Negative sentiment
  urgent:   '#EF4444', // Danger Red for Urgent Emotion Flag
};

// Word cloud tag colors strictly aligned with SCMS Blue/Navy/Teal/Slate/Amber palette
const THEME_PALETTE = [
  '#1E3A8A', // Dark Navy
  '#2563EB', // Primary Blue
  '#0284C7', // Sky Blue
  '#0D9488', // Teal
  '#059669', // Emerald
  '#475569', // Slate
  '#D97706', // Amber
];

// ─── Weighted Visual Word Cloud Component ────────────────────────────────────

function WeightedWordCloud({ words = [], onSelectWord = null, activeWord = '' }) {
  if (!words || words.length === 0) {
    return (
      <div className="flex-1 flex flex-col items-center justify-center py-12 text-slate-400 bg-slate-50/60 rounded-xl border border-slate-200">
        <Tag size={32} strokeWidth={1.5} className="mb-2 text-slate-300" />
        <p className="text-xs font-semibold text-slate-600">No frequent words found for this filter</p>
        <p className="text-[11px] text-slate-400 mt-0.5 max-w-xs text-center">
          Words are extracted dynamically from real concern titles and descriptions, excluding stop words and personal identifiers.
        </p>
      </div>
    );
  }

  const values = words.map((w) => w.value || w.count || 1);
  const maxVal = Math.max(...values, 1);
  const minVal = Math.min(...values, 0);
  const range = maxVal - minVal || 1;

  return (
    <div className="flex-1 flex flex-col justify-between">
      {/* Weighted Tag Cloud Canvas */}
      <div
        data-testid="sentiment-word-cloud"
        className="min-h-[240px] max-h-[300px] p-4 bg-slate-50/70 rounded-xl border border-slate-200 flex flex-wrap items-center justify-center content-start gap-2 overflow-y-auto"
      >
        {words.slice(0, 22).map((item, idx) => {
          const val = item.value || item.count || 1;
          const weight = (val - minVal) / range; // 0.0 to 1.0
          const fontSize = Math.round(12 + weight * 12); // 12px to 24px
          const color = THEME_PALETTE[idx % THEME_PALETTE.length];
          const fontWeight = weight > 0.6 ? 800 : weight > 0.3 ? 700 : 600;
          const isSelected = activeWord === item.text;

          return (
            <button
              type="button"
              key={item.text}
              onClick={() => onSelectWord && onSelectWord(isSelected ? '' : item.text)}
              style={{
                fontSize: `${fontSize}px`,
                color: isSelected ? '#FFFFFF' : color,
                backgroundColor: isSelected ? '#2563EB' : '#FFFFFF',
                fontWeight,
              }}
              className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-lg border shadow-2xs transition-all select-none ${
                isSelected
                  ? 'border-blue-700 ring-2 ring-blue-300 scale-105'
                  : 'border-slate-200 hover:border-blue-400 hover:shadow-sm hover:scale-105'
              }`}
              title={`${item.text}: ${val} occurrence${val > 1 ? 's' : ''}${
                item.percentage ? ` (${item.percentage}% of analyzed concerns)` : ''
              }`}
            >
              <span>{item.text}</span>
              <span
                className={`text-[10px] font-semibold px-1.5 py-0.2 rounded-full ${
                  isSelected ? 'bg-blue-800 text-blue-100' : 'bg-slate-100 text-slate-500'
                }`}
              >
                {val}
              </span>
            </button>
          );
        })}
      </div>

      {/* Top Keywords Summary Strip */}
      <div className="pt-3 mt-3 border-t border-slate-100 flex flex-wrap items-center gap-1.5">
        <span className="text-[10px] uppercase font-bold text-slate-400 mr-1">Top Terms:</span>
        {words.slice(0, 8).map((w) => (
          <span
            key={`badge-${w.text}`}
            className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md bg-blue-50 text-blue-700 text-[11px] font-semibold border border-blue-100"
          >
            <span>{w.text}</span>
            <span className="text-blue-500 text-[10px]">({w.value || w.count})</span>
          </span>
        ))}
      </div>
    </div>
  );
}

// ─── Main Analytics & Sentiment Dashboard Component ──────────────────────────

const SentimentDashboard = () => {
  const { user } = useAuth();

  // Primary Analytics State
  const [data, setData] = useState({
    distribution: { positive: 0, neutral: 0, negative: 0, urgent: 0 },
    flagged: [],
    flagged_summary: { shown_count: 0, total_scope_flagged: 0, sao_privacy_restricted: false },
    trend: [],
    themes: [],
    text_analytics: {
      total_concerns_analyzed: 0,
      total_meaningful_words: 0,
      unique_meaningful_words: 0,
      word_cloud: [],
      top_words: [],
      top_phrases: [],
      filters_applied: {},
    },
    available_categories: [],
    available_subcategories: [],
    issue_categories: [],
    issue_subcategories: [],
    overview_analytics: null,
    resolution_analytics: null,
  });

  // AI Narrative State (loaded non-blockingly so charts render immediately)
  const [trends, setTrends] = useState({ stats: null, narrative: '', ai_provider: null });
  const [loading, setLoading] = useState(true);
  const [textLoading, setTextLoading] = useState(false);
  const [trendsLoading, setTrendsLoading] = useState(true);
  const [error, setError] = useState(null);

  // Global Dashboard Filters
  const [filters, setFilters] = useState({
    date_from: '',
    date_to: '',
    priority: '',
    status: '',
    issue_category: '',
    timePreset: 'all',
  });

  // Dedicated Word Frequency & Text Analytics Section Filters
  const [textFilters, setTextFilters] = useState({
    sentiment: 'all',
    category: '',
    subcategory: '',
    searchWord: '',
  });

  const handleTimePreset = (preset) => {
    let from = '';
    const now = new Date();
    if (preset === '7d') {
      const d = new Date();
      d.setDate(now.getDate() - 7);
      from = d.toISOString().slice(0, 10);
    } else if (preset === '30d') {
      const d = new Date();
      d.setDate(now.getDate() - 30);
      from = d.toISOString().slice(0, 10);
    } else if (preset === '90d') {
      const d = new Date();
      d.setDate(now.getDate() - 90);
      from = d.toISOString().slice(0, 10);
    }
    setFilters((prev) => ({
      ...prev,
      timePreset: preset,
      date_from: from,
      date_to: preset === 'all' ? '' : now.toISOString().slice(0, 10),
    }));
  };

  // Fetch main sentiment + text + overview analytics
  const fetchSentimentData = useCallback(
    async (isTextFilterOnly = false) => {
      try {
        if (isTextFilterOnly) {
          setTextLoading(true);
        } else {
          setLoading(true);
        }
        setError(null);

        const params = {};
        if (filters.date_from) params.date_from = filters.date_from;
        if (filters.date_to) params.date_to = filters.date_to;
        if (filters.priority) params.priority = filters.priority;
        if (filters.status) params.status = filters.status;
        if (filters.issue_category) params.issue_category = filters.issue_category;

        // Text analytics section filters
        if (textFilters.sentiment && textFilters.sentiment !== 'all') {
          params.text_sentiment = textFilters.sentiment;
        }
        if (textFilters.category) {
          params.text_category = textFilters.category;
        }
        if (textFilters.subcategory) {
          params.text_subcategory = textFilters.subcategory;
        }

        const sentimentRes = await api.get('/analytics/sentiment', { params });
        setData((prev) => ({
          ...prev,
          ...(sentimentRes.data || {}),
        }));
      } catch (err) {
        console.error('Failed to load sentiment analytics:', err);
        setError(err.response?.data?.message ?? 'Failed to load analytics and sentiment data.');
      } finally {
        setLoading(false);
        setTextLoading(false);
      }
    },
    [
      filters.date_from,
      filters.date_to,
      filters.priority,
      filters.status,
      filters.issue_category,
      textFilters.sentiment,
      textFilters.category,
      textFilters.subcategory,
    ]
  );

  // Fetch AI narrative asynchronously without blocking chart rendering
  const fetchTrendNarrative = useCallback(async () => {
    try {
      setTrendsLoading(true);
      const res = await api.get('/analytics/trends');
      setTrends(res.data || { stats: null, narrative: '', ai_provider: null });
    } catch (err) {
      console.error('Failed to load trend narrative:', err);
    } finally {
      setTrendsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchSentimentData(false);
  }, [fetchSentimentData]);

  useEffect(() => {
    fetchTrendNarrative();
  }, [fetchTrendNarrative]);

  const handleRefreshAll = () => {
    fetchSentimentData(false);
    fetchTrendNarrative();
  };

  const handleResetFilters = () => {
    setFilters({
      date_from: '',
      date_to: '',
      priority: '',
      status: '',
      issue_category: '',
      timePreset: 'all',
    });
    setTextFilters({
      sentiment: 'all',
      category: '',
      subcategory: '',
      searchWord: '',
    });
  };

  // Sentiment Distribution Calculations
  const positiveCount = data.distribution?.positive || 0;
  const neutralCount  = data.distribution?.neutral || 0;
  const negativeCount = data.distribution?.negative || 0;
  const urgentCount   = data.distribution?.urgent || 0;
  const negativeOrUrgentCount = negativeCount + urgentCount;
  const totalSentimentCount = positiveCount + neutralCount + negativeCount + urgentCount;

  const positivePct  = totalSentimentCount > 0 ? Math.round((positiveCount / totalSentimentCount) * 100) : 0;
  const neutralPct   = totalSentimentCount > 0 ? Math.round((neutralCount / totalSentimentCount) * 100) : 0;
  const negativePct  = totalSentimentCount > 0 ? Math.round((negativeCount / totalSentimentCount) * 100) : 0;
  const urgentPct    = totalSentimentCount > 0 ? Math.round((urgentCount / totalSentimentCount) * 100) : 0;
  const negUrgentPct = totalSentimentCount > 0 ? Math.round((negativeOrUrgentCount / totalSentimentCount) * 100) : 0;

  const pieData = useMemo(() => {
    return [
      { name: 'Positive',            key: 'positive', value: positiveCount, color: SENTIMENT_COLORS.positive },
      { name: 'Neutral',             key: 'neutral',  value: neutralCount,  color: SENTIMENT_COLORS.neutral },
      { name: 'Negative',            key: 'negative', value: negativeCount, color: SENTIMENT_COLORS.negative },
      { name: 'Urgent Emotion Flag', key: 'urgent',   value: urgentCount,   color: SENTIMENT_COLORS.urgent },
    ].filter((d) => d.value > 0);
  }, [positiveCount, neutralCount, negativeCount, urgentCount]);

  // Role Navigation & Scope Metadata
  const getBackLink = () => {
    if (user?.role === 'sao') return { path: '/sao', label: 'Back to SAO Dashboard' };
    if (user?.role === 'dean') return { path: '/dean', label: 'Back to Dean Dashboard' };
    if (user?.role === 'dept_staff') return { path: '/dept-staff', label: 'Back to Department Dashboard' };
    return { path: '/', label: 'Back to Dashboard' };
  };
  const backLink = getBackLink();

  const roleScopeName = useMemo(() => {
    if (!user) return 'Authorized Personnel';
    if (user.role === 'sao') return 'Student Affairs Office (SAO) — Institution-Wide Scope';
    if (user.role === 'dean') return `Academic Dean — ${user.academic_unit?.name || 'Academic Unit Scope'}`;
    if (user.role === 'dept_staff') {
      const assignment = user.department?.name || user.academic_unit?.name || 'Assigned Department';
      return `Department Staff — ${assignment}`;
    }
    return user.role;
  }, [user]);

  const getConcernDetailPath = (concern) => {
    if (!concern?.concern_id) return null;
    if (user?.role === 'dean') return `/dean/concerns/${concern.concern_id}`;
    if (user?.role === 'dept_staff') return `/dept-staff/concerns/${concern.concern_id}`;
    if (user?.role === 'sao') return `/sao/concerns/${concern.concern_id}`;
    return null;
  };

  // Word Cloud & Text Analytics Data
  const textAnalytics = data.text_analytics || {};
  const wordCloudItems = useMemo(() => {
    if (textAnalytics.word_cloud && textAnalytics.word_cloud.length > 0) {
      return textAnalytics.word_cloud;
    }
    return data.themes || [];
  }, [textAnalytics.word_cloud, data.themes]);

  const topWordsList = useMemo(() => {
    const raw = textAnalytics.top_words || [];
    if (!textFilters.searchWord) return raw;
    return raw.filter((w) => w.word === textFilters.searchWord || w.text === textFilters.searchWord);
  }, [textAnalytics.top_words, textFilters.searchWord]);

  const topPhrasesList = useMemo(() => {
    const raw = textAnalytics.top_phrases || [];
    if (!textFilters.searchWord) return raw;
    return raw.filter((p) => p.phrase.includes(textFilters.searchWord));
  }, [textAnalytics.top_phrases, textFilters.searchWord]);

  const filteredSubcategories = useMemo(() => {
    const allSubs = data.available_subcategories || [];
    if (!textFilters.category) return allSubs;
    return allSubs.filter((s) => s.category === textFilters.category);
  }, [data.available_subcategories, textFilters.category]);

  // Overview Analytics Data
  const overview = data.overview_analytics || {};
  const overviewSummary = overview.summary || {};
  const priorityList = overview.by_priority || [];
  const organizationList = overview.by_organization || [];
  const volumeTrend = overview.volume_trend || [];

  // Synthesized fallback narrative if external LLM is offline
  const fallbackNarrative = useMemo(() => {
    if (totalSentimentCount === 0) {
      return 'No sentiment-evaluated concerns are currently recorded for the selected filters. Submit or broaden your filter criteria to view longitudinal sentiment insights.';
    }
    const topCategory = data.issue_categories?.[0]?.category || 'general student operations';
    const topWord = wordCloudItems?.[0]?.text ? `"${wordCloudItems[0].text}"` : 'operational issues';
    const topPhrase = textAnalytics.top_phrases?.[0]?.phrase ? `"${textAnalytics.top_phrases[0].phrase}"` : null;
    return `Across ${totalSentimentCount} evaluated concern(s) in your scope, ${positivePct}% express positive tone, ${neutralPct}% are neutral inquiries, and ${negUrgentPct}% carry negative or urgent emotional tone (${urgentCount} urgent emotion flag${urgentCount === 1 ? '' : 's'}). The most frequent issue category is ${topCategory}, with student descriptions most frequently mentioning ${topWord}${topPhrase ? ` and phrases such as ${topPhrase}` : ''}.`;
  }, [
    totalSentimentCount,
    positivePct,
    neutralPct,
    negUrgentPct,
    urgentCount,
    data.issue_categories,
    wordCloudItems,
    textAnalytics.top_phrases,
  ]);

  if (loading && !data.overview_analytics && totalSentimentCount === 0) {
    return <LoadingSpinner label="Loading Analytics & Sentiment Dashboard..." />;
  }

  const maxWordCount = Math.max(...(topWordsList.map((w) => w.count) || [1]), 1);
  const maxPhraseCount = Math.max(...(topPhrasesList.map((p) => p.count) || [1]), 1);
  const maxCategoryCount = Math.max(...((data.issue_categories || []).map((c) => c.count) || [1]), 1);

  return (
    <div className="space-y-6 pb-10">
      {/* ── 0. Role-Aware Return Breadcrumb ── */}
      <div className="flex items-center justify-between flex-wrap gap-2">
        <Link
          to={backLink.path}
          className="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-blue-600 transition-colors"
        >
          <ArrowLeft size={14} /> {backLink.label}
        </Link>
        <span className="text-[11px] font-medium text-slate-400">
          Real-time database analytics &bull; Zero synthetic fabrication
        </span>
      </div>

      {/* ── 1. Institutional Blue Welcome Banner (#1E3A8A to #2563EB) ── */}
      <div className="rounded-2xl p-6 bg-gradient-to-r from-[#1E3A8A] to-[#2563EB] text-white shadow-sm border border-blue-800">
        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
          <div className="space-y-2">
            <div className="flex items-center gap-2.5 flex-wrap">
              <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-white/15 text-white border border-white/20">
                <ShieldCheck size={14} className="text-blue-200" />
                {roleScopeName}
              </span>
              <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-blue-900/50 text-blue-100 border border-blue-400/30">
                {user?.role === 'sao' ? 'Option A Privacy Enforced' : 'Role-Scoped Access'}
              </span>
            </div>
            <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight">
              Analytics &amp; Sentiment Dashboard
            </h1>
            <p className="text-blue-100 text-sm max-w-3xl leading-relaxed">
              Comprehensive student sentiment distribution, longitudinal emotional trends, word &amp; phrase frequency analysis, and resolution performance across evaluated concerns.
            </p>
          </div>

          <div className="flex flex-col sm:flex-row lg:flex-col items-start sm:items-center lg:items-end gap-2 shrink-0">
            <div className="flex items-center gap-2 bg-white/10 backdrop-blur-xs px-4 py-2 rounded-xl border border-white/15 text-xs">
              <span className="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse" />
              <span className="text-blue-100">AI Analytics Engine:</span>
              <span className="font-bold text-white">
                {trends.ai_provider === 'gemini' ? 'Google Gemini Flash' : 'Active & Operational'}
              </span>
            </div>
            <span className="text-[11px] text-blue-200">
              {totalSentimentCount} Sentiment-Evaluated Concern{totalSentimentCount === 1 ? '' : 's'} in Scope
            </span>
          </div>
        </div>
      </div>

      {/* ── 2. Error Banner (if any) ── */}
      {error && (
        <div className="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700 flex items-start gap-2.5 shadow-2xs">
          <AlertTriangle size={18} className="shrink-0 mt-0.5 text-red-600" />
          <div className="flex-1">
            <p className="font-bold">Unable to refresh analytics</p>
            <p className="text-xs mt-0.5">{error}</p>
          </div>
          <button onClick={handleRefreshAll} className="btn-secondary py-1 px-2.5 text-xs">
            Retry
          </button>
        </div>
      )}

      {/* ── 3. Global Filters & Action Toolbar ── */}
      <div className="bg-white rounded-xl border border-slate-200 p-4 shadow-xs flex flex-col xl:flex-row xl:items-center xl:justify-between gap-3">
        <div className="flex items-center gap-2.5 flex-wrap">
          <span className="text-xs font-bold text-slate-600 uppercase tracking-wider flex items-center gap-1.5 mr-1">
            <Filter size={14} className="text-blue-600" /> Dashboard Filters:
          </span>

          {/* Time Presets */}
          <div className="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5 text-xs">
            {[
              { id: '7d', label: '7 Days' },
              { id: '30d', label: '30 Days' },
              { id: '90d', label: '90 Days' },
              { id: 'all', label: 'All Time' },
            ].map((preset) => (
              <button
                key={preset.id}
                type="button"
                onClick={() => handleTimePreset(preset.id)}
                className={`px-3 py-1.5 rounded-md font-semibold transition-colors ${
                  filters.timePreset === preset.id
                    ? 'bg-[#2563EB] text-white shadow-2xs'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                {preset.label}
              </button>
            ))}
          </div>

          {/* Date Range Inputs */}
          <div className="flex items-center gap-1.5 text-xs">
            <input
              type="date"
              aria-label="Start Date"
              value={filters.date_from}
              onChange={(e) =>
                setFilters((prev) => ({ ...prev, date_from: e.target.value, timePreset: 'custom' }))
              }
              className="input-field py-1.5 px-2.5 text-xs w-auto bg-white"
            />
            <span className="text-slate-400">to</span>
            <input
              type="date"
              aria-label="End Date"
              value={filters.date_to}
              onChange={(e) =>
                setFilters((prev) => ({ ...prev, date_to: e.target.value, timePreset: 'custom' }))
              }
              className="input-field py-1.5 px-2.5 text-xs w-auto bg-white"
            />
          </div>

          {/* Issue Category Filter */}
          <select
            aria-label="Filter by Issue Category"
            value={filters.issue_category}
            onChange={(e) => setFilters((prev) => ({ ...prev, issue_category: e.target.value }))}
            className="input-field py-1.5 text-xs w-auto bg-white"
          >
            <option value="">All Issue Categories</option>
            {(data.available_categories || []).map((cat) => (
              <option key={cat} value={cat}>
                {cat}
              </option>
            ))}
          </select>

          {/* Priority Filter */}
          <select
            aria-label="Filter by Priority"
            value={filters.priority}
            onChange={(e) => setFilters((prev) => ({ ...prev, priority: e.target.value }))}
            className="input-field py-1.5 text-xs w-auto bg-white"
          >
            <option value="">All Priorities</option>
            <option value="critical">Critical Priority</option>
            <option value="high">High Priority</option>
            <option value="medium">Medium Priority</option>
            <option value="low">Low Priority</option>
          </select>

          {/* Status Filter */}
          <select
            aria-label="Filter by Status"
            value={filters.status}
            onChange={(e) => setFilters((prev) => ({ ...prev, status: e.target.value }))}
            className="input-field py-1.5 text-xs w-auto bg-white"
          >
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="in_progress">In Progress</option>
            <option value="escalated">Escalated</option>
            <option value="resolved">Resolved</option>
          </select>
        </div>

        <div className="flex items-center gap-2 self-start xl:self-auto shrink-0">
          {(filters.date_from ||
            filters.date_to ||
            filters.priority ||
            filters.status ||
            filters.issue_category ||
            textFilters.sentiment !== 'all' ||
            textFilters.category ||
            textFilters.subcategory) && (
            <button
              type="button"
              onClick={handleResetFilters}
              className="text-xs font-semibold text-slate-500 hover:text-slate-800 px-2.5 py-1.5"
            >
              Reset All
            </button>
          )}
          <button
            type="button"
            onClick={handleRefreshAll}
            className="btn-secondary py-1.5 px-3 text-xs inline-flex items-center gap-1.5"
          >
            <RefreshCw size={14} className={loading ? 'animate-spin text-blue-600' : ''} />
            <span>Refresh Data</span>
          </button>
        </div>
      </div>

      {/* ── 4. Unified Sentiment KPI Cards Row ── */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Card 1: Total Evaluated */}
        <div className="bg-white rounded-xl border border-slate-200 p-5 shadow-xs flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-blue-50 text-[#2563EB] border border-blue-100 flex items-center justify-center shrink-0">
            <BarChart3 size={24} />
          </div>
          <div>
            <div className="text-xs font-bold text-slate-500 uppercase tracking-wider">
              Total Evaluated
            </div>
            <div className="text-2xl font-black text-slate-900 mt-0.5">{totalSentimentCount}</div>
            <div className="text-[11px] text-slate-500 mt-0.5">
              {overviewSummary.total_concerns || totalSentimentCount} total concern(s) in scope
            </div>
          </div>
        </div>

        {/* Card 2: Positive Sentiment */}
        <div className="bg-white rounded-xl border border-slate-200 p-5 shadow-xs flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center shrink-0">
            <TrendingUp size={24} />
          </div>
          <div>
            <div className="text-xs font-bold text-slate-500 uppercase tracking-wider">
              Positive Sentiment
            </div>
            <div className="text-2xl font-black text-slate-900 flex items-baseline gap-2 mt-0.5">
              <span>{positiveCount}</span>
              <span className="text-xs font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                {positivePct}%
              </span>
            </div>
            <div className="text-[11px] text-slate-500 mt-0.5">Constructive &amp; satisfied tone</div>
          </div>
        </div>

        {/* Card 3: Neutral Inquiries */}
        <div className="bg-white rounded-xl border border-slate-200 p-5 shadow-xs flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 border border-slate-200 flex items-center justify-center shrink-0">
            <Activity size={24} />
          </div>
          <div>
            <div className="text-xs font-bold text-slate-500 uppercase tracking-wider">
              Neutral Inquiries
            </div>
            <div className="text-2xl font-black text-slate-900 flex items-baseline gap-2 mt-0.5">
              <span>{neutralCount}</span>
              <span className="text-xs font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded-full border border-slate-200">
                {neutralPct}%
              </span>
            </div>
            <div className="text-[11px] text-slate-500 mt-0.5">Standard operational requests</div>
          </div>
        </div>

        {/* Card 4: Negative & Urgent Emotion Flags */}
        <div className="bg-white rounded-xl border border-slate-200 p-5 shadow-xs flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-red-50 text-red-600 border border-red-100 flex items-center justify-center shrink-0">
            <AlertTriangle size={24} />
          </div>
          <div>
            <div className="text-xs font-bold text-slate-500 uppercase tracking-wider">
              Negative &amp; Urgent Tone
            </div>
            <div className="text-2xl font-black text-slate-900 flex items-baseline gap-2 mt-0.5">
              <span>{negativeOrUrgentCount}</span>
              <span className="text-xs font-bold text-red-700 bg-red-50 px-2 py-0.5 rounded-full border border-red-200">
                {negUrgentPct}%
              </span>
            </div>
            <div className="text-[11px] text-slate-500 mt-0.5">
              {negativeCount} Negative &bull; {urgentCount} Urgent Emotion Flag{urgentCount === 1 ? '' : 's'}
            </div>
          </div>
        </div>
      </div>

      {/* ── 5. AI Longitudinal Trend Narrative & Sentiment Distribution (Donut + Tone Spectrum) ── */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Left: AI Longitudinal Trend Assessment Card */}
        <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-xs flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between gap-2 mb-4">
              <div className="flex items-center gap-2.5">
                <div className="w-9 h-9 rounded-lg bg-blue-50 text-[#2563EB] border border-blue-100 flex items-center justify-center font-bold">
                  <Sparkles size={18} />
                </div>
                <div>
                  <h2 className="text-base font-bold text-slate-900">
                    AI Longitudinal Trend Assessment
                  </h2>
                  <p className="text-xs text-slate-500">
                    Automated executive synthesis of student emotional tone and submission patterns
                  </p>
                </div>
              </div>
              <span className="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200 shrink-0">
                {trends.ai_provider === 'gemini' ? 'Gemini Flash AI' : 'Live Synthesis'}
              </span>
            </div>

            <div className="bg-blue-50/50 p-4 rounded-xl border border-blue-100 text-slate-800 text-sm leading-relaxed">
              <p className="italic font-medium text-slate-800">
                &ldquo;{trends?.narrative || fallbackNarrative}&rdquo;
              </p>
              {trendsLoading && (
                <div className="mt-2 flex items-center gap-1.5 text-[11px] text-blue-600 font-medium">
                  <RefreshCw size={11} className="animate-spin shrink-0" />
                  <span>Checking AI narrative engine for additional insights...</span>
                </div>
              )}
            </div>

            {/* 30-Day Quick Pulse Pills */}
            <div className="grid grid-cols-3 gap-3 mt-4">
              <div className="p-3 rounded-lg bg-slate-50 border border-slate-200/80">
                <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                  30-Day Intake
                </span>
                <span className="text-lg font-extrabold text-slate-900">
                  {trends?.stats?.total_concerns_last_30_days ?? overviewSummary.total_concerns ?? 0}
                </span>
                <span className="text-[11px] text-slate-500 block">concerns logged</span>
              </div>
              <div className="p-3 rounded-lg bg-slate-50 border border-slate-200/80">
                <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                  30-Day Resolved
                </span>
                <span className="text-lg font-extrabold text-emerald-600">
                  {trends?.stats?.total_resolved ?? overviewSummary.resolved_concerns ?? 0}
                </span>
                <span className="text-[11px] text-slate-500 block">completed cases</span>
              </div>
              <div className="p-3 rounded-lg bg-slate-50 border border-slate-200/80">
                <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                  Resolution Rate
                </span>
                <span className="text-lg font-extrabold text-[#1E3A8A]">
                  {overviewSummary.resolution_rate ?? 0}%
                </span>
                <span className="text-[11px] text-slate-500 block">in active scope</span>
              </div>
            </div>
          </div>

          <div className="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
            <span className="flex items-center gap-1.5">
              <CheckCircle2 size={14} className="text-emerald-500" />
              Grounded in verified database records
            </span>
            <span className="font-semibold text-slate-600">Role-Scoped</span>
          </div>
        </div>

        {/* Right: Sentiment Distribution Donut Chart + Tone Spectrum Bar */}
        <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-xs flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2.5">
                <div className="w-9 h-9 rounded-lg bg-blue-50 text-[#2563EB] border border-blue-100 flex items-center justify-center font-bold">
                  <PieIcon size={18} />
                </div>
                <div>
                  <h2 className="text-base font-bold text-slate-900">Sentiment Distribution</h2>
                  <p className="text-xs text-slate-500">
                    Proportional breakdown of Positive, Neutral, Negative, and Urgent Emotion Flags
                  </p>
                </div>
              </div>
              <span className="px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                {totalSentimentCount} Evaluated
              </span>
            </div>

            {totalSentimentCount === 0 ? (
              <div className="h-56 flex flex-col items-center justify-center text-slate-400 bg-slate-50/60 rounded-xl border border-slate-200">
                <PieIcon size={32} strokeWidth={1.5} className="mb-2 text-slate-300" />
                <p className="text-xs font-semibold text-slate-700">No sentiment data recorded yet</p>
                <p className="text-[11px] text-slate-400 mt-0.5">
                  Sentiments will appear as student concerns are submitted and evaluated.
                </p>
              </div>
            ) : (
              <ChartErrorBoundary>
                <div className="flex flex-col sm:flex-row items-center justify-between gap-5">
                  <div className="w-full sm:w-1/2 h-52 min-h-[190px]">
                    <ResponsiveContainer width="100%" height="100%">
                      <PieChart>
                        <Pie
                          data={pieData}
                          cx="50%"
                          cy="50%"
                          innerRadius={48}
                          outerRadius={78}
                          paddingAngle={3}
                          dataKey="value"
                        >
                          {pieData.map((entry) => (
                            <Cell key={`cell-${entry.key}`} fill={entry.color} />
                          ))}
                        </Pie>
                        <Tooltip
                          contentStyle={{
                            backgroundColor: '#ffffff',
                            borderRadius: '8px',
                            border: '1px solid #e2e8f0',
                            fontSize: '12px',
                            boxShadow: '0 2px 6px rgba(15, 23, 42, 0.08)',
                          }}
                          formatter={(val, name) => [
                            `${val} concern${val === 1 ? '' : 's'} (${Math.round(
                              (val / totalSentimentCount) * 100
                            )}%)`,
                            name,
                          ]}
                        />
                      </PieChart>
                    </ResponsiveContainer>
                  </div>

                  <div className="w-full sm:w-1/2 space-y-2">
                    <div className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
                      Tone Breakdown (Click to Filter Words)
                    </div>
                    {[
                      { label: 'Positive', key: 'positive', count: positiveCount, pct: positivePct, color: SENTIMENT_COLORS.positive },
                      { label: 'Neutral', key: 'neutral', count: neutralCount, pct: neutralPct, color: SENTIMENT_COLORS.neutral },
                      { label: 'Negative', key: 'negative', count: negativeCount, pct: negativePct, color: SENTIMENT_COLORS.negative },
                      { label: 'Urgent Emotion Flag', key: 'urgent', count: urgentCount, pct: urgentPct, color: SENTIMENT_COLORS.urgent },
                    ].map((item) => {
                      const isSelected = textFilters.sentiment === item.key;
                      return (
                        <button
                          type="button"
                          key={item.key}
                          onClick={() =>
                            setTextFilters((prev) => ({
                              ...prev,
                              sentiment: prev.sentiment === item.key ? 'all' : item.key,
                            }))
                          }
                          className={`w-full flex items-center justify-between text-xs p-2 rounded-lg border transition-all ${
                            isSelected
                              ? 'bg-blue-50 border-blue-300 ring-1 ring-blue-300'
                              : 'bg-slate-50 border-slate-200/70 hover:bg-slate-100/80'
                          }`}
                        >
                          <div className="flex items-center gap-2">
                            <span
                              className="w-2.5 h-2.5 rounded-full shrink-0"
                              style={{ backgroundColor: item.color }}
                            />
                            <span className="text-slate-800 font-semibold">{item.label}</span>
                          </div>
                          <div className="text-slate-900 font-bold">
                            {item.count}{' '}
                            <span className="font-normal text-slate-500 text-[11px]">
                              ({item.pct}%)
                            </span>
                          </div>
                        </button>
                      );
                    })}
                  </div>
                </div>
              </ChartErrorBoundary>
            )}
          </div>

          {/* Tone Spectrum Stacked Visual Bar */}
          {totalSentimentCount > 0 && (
            <div className="mt-4 pt-3 border-t border-slate-100">
              <div className="flex items-center justify-between text-[11px] text-slate-500 mb-1.5">
                <span className="font-semibold text-slate-700">Student Emotional Tone Spectrum</span>
                <span>100% ({totalSentimentCount} Evaluated)</span>
              </div>
              <div className="h-3 w-full rounded-full bg-slate-100 overflow-hidden flex border border-slate-200/80">
                {positivePct > 0 && (
                  <div
                    style={{ width: `${positivePct}%`, backgroundColor: SENTIMENT_COLORS.positive }}
                    title={`Positive: ${positiveCount} (${positivePct}%)`}
                  />
                )}
                {neutralPct > 0 && (
                  <div
                    style={{ width: `${neutralPct}%`, backgroundColor: SENTIMENT_COLORS.neutral }}
                    title={`Neutral: ${neutralCount} (${neutralPct}%)`}
                  />
                )}
                {negativePct > 0 && (
                  <div
                    style={{ width: `${negativePct}%`, backgroundColor: SENTIMENT_COLORS.negative }}
                    title={`Negative: ${negativeCount} (${negativePct}%)`}
                  />
                )}
                {urgentPct > 0 && (
                  <div
                    style={{ width: `${urgentPct}%`, backgroundColor: SENTIMENT_COLORS.urgent }}
                    title={`Urgent Emotion Flag: ${urgentCount} (${urgentPct}%)`}
                  />
                )}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* ── 6. Average Sentiment Over Time (LineChart) & AI Problem Categories ── */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Left: Sentiment Trend Line Chart */}
        <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-xs flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2.5">
                <div className="w-9 h-9 rounded-lg bg-blue-50 text-[#2563EB] border border-blue-100 flex items-center justify-center font-bold">
                  <TrendingUp size={18} />
                </div>
                <div>
                  <h2 className="text-base font-bold text-slate-900">Average Sentiment Over Time</h2>
                  <p className="text-xs text-slate-500 mt-0.5">
                    Score Scale: -1.0 (Very Negative) &bull; 0.0 (Neutral) &bull; +1.0 (Very Positive)
                  </p>
                </div>
              </div>
              <span className="px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                Daily Timeline
              </span>
            </div>

            {!data?.trend || data.trend.length === 0 ? (
              <div className="h-64 flex flex-col items-center justify-center text-slate-400 bg-slate-50/60 rounded-xl border border-slate-200">
                <TrendingUp size={32} strokeWidth={1.5} className="mb-2 text-slate-300" />
                <p className="text-xs font-semibold text-slate-700">
                  No daily sentiment trend points in the last 30 days
                </p>
                <p className="text-[11px] text-slate-400 mt-0.5">
                  Daily sentiment averages plot automatically on days with evaluated submissions.
                </p>
              </div>
            ) : (
              <ChartErrorBoundary>
                <div className="h-64 w-full min-h-[220px]">
                  <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={data.trend} margin={{ top: 10, right: 20, left: -10, bottom: 5 }}>
                      <CartesianGrid strokeDasharray="3 3" stroke="#E2E8F0" />
                      <XAxis
                        dataKey="date"
                        tick={{ fontSize: 11, fill: '#64748B' }}
                        tickFormatter={(val) => {
                          try {
                            const d = new Date(val);
                            return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
                          } catch {
                            return val;
                          }
                        }}
                      />
                      <YAxis
                        domain={[-1, 1]}
                        ticks={[-1.0, -0.5, 0, 0.5, 1.0]}
                        tick={{ fontSize: 11, fill: '#64748B' }}
                        tickFormatter={(v) => (v > 0 ? `+${v.toFixed(1)}` : `${v.toFixed(1)}`)}
                      />
                      <Tooltip
                        contentStyle={{
                          backgroundColor: '#ffffff',
                          borderRadius: '8px',
                          border: '1px solid #e2e8f0',
                          fontSize: '12px',
                          boxShadow: '0 2px 6px rgba(15, 23, 42, 0.08)',
                        }}
                        formatter={(value, name, item) => {
                          const count = item?.payload?.count;
                          const countStr = count ? ` (${count} concern${count > 1 ? 's' : ''})` : '';
                          return [`${value > 0 ? '+' : ''}${value}${countStr}`, 'Avg Sentiment Score'];
                        }}
                        labelFormatter={(lbl) => `Date: ${lbl}`}
                      />
                      <Line
                        type="monotone"
                        dataKey="avg_score"
                        name="Avg Sentiment"
                        stroke="#1E3A8A"
                        strokeWidth={2.5}
                        dot={{ r: 4, fill: '#2563EB' }}
                        activeDot={{ r: 6, fill: '#1E3A8A' }}
                      />
                    </LineChart>
                  </ResponsiveContainer>
                </div>
              </ChartErrorBoundary>
            )}
          </div>

          <div className="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
            <span>Scores above 0.0 reflect net positive emotional tone</span>
            <span className="font-semibold text-slate-700">Days with Submissions Only</span>
          </div>
        </div>

        {/* Right: AI Problem Categories & Subcategories Breakdown */}
        <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-xs flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2.5">
                <div className="w-9 h-9 rounded-lg bg-blue-50 text-[#2563EB] border border-blue-100 flex items-center justify-center font-bold">
                  <Layers size={18} />
                </div>
                <div>
                  <h2 className="text-base font-bold text-slate-900">
                    AI Problem Categories &amp; Subcategories
                  </h2>
                  <p className="text-xs text-slate-500 mt-0.5">
                    Click any category to filter the Word &amp; Phrase Analytics below
                  </p>
                </div>
              </div>
              <span className="px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                {data.issue_categories?.length || 0} Categories
              </span>
            </div>

            {!data.issue_categories || data.issue_categories.length === 0 ? (
              <div className="h-64 flex flex-col items-center justify-center text-slate-400 bg-slate-50/60 rounded-xl border border-slate-200">
                <Layers size={32} strokeWidth={1.5} className="mb-2 text-slate-300" />
                <p className="text-xs font-semibold text-slate-700">No classified categories in range</p>
              </div>
            ) : (
              <div className="space-y-3">
                {data.issue_categories.slice(0, 6).map((cat) => {
                  const barWidth = Math.max(6, Math.round((cat.count / maxCategoryCount) * 100));
                  const isSelected = textFilters.category === cat.category;
                  const relatedSubs = (data.issue_subcategories || []).filter(
                    (s) => s.category === cat.category
                  );

                  return (
                    <div
                      key={cat.category}
                      onClick={() =>
                        setTextFilters((prev) => ({
                          ...prev,
                          category: prev.category === cat.category ? '' : cat.category,
                          subcategory: '',
                        }))
                      }
                      className={`p-3 rounded-xl border transition-all cursor-pointer ${
                        isSelected
                          ? 'bg-blue-50/70 border-blue-300 ring-1 ring-blue-300'
                          : 'bg-slate-50/70 border-slate-200/80 hover:border-blue-300 hover:bg-white'
                      }`}
                    >
                      <div className="flex items-center justify-between text-xs mb-1.5">
                        <span className="font-bold text-slate-900 flex items-center gap-1.5">
                          <span>{cat.category}</span>
                          {isSelected && (
                            <span className="text-[10px] px-1.5 py-0.2 rounded bg-blue-600 text-white font-semibold">
                              Filtered
                            </span>
                          )}
                        </span>
                        <span className="font-bold text-slate-800">
                          {cat.count}{' '}
                          <span className="text-slate-500 font-normal">
                            ({cat.percentage ?? Math.round((cat.count / Math.max(1, totalSentimentCount)) * 100)}%)
                          </span>
                        </span>
                      </div>
                      <div className="h-2 w-full bg-slate-200/70 rounded-full overflow-hidden">
                        <div
                          className="h-full bg-[#2563EB] rounded-full transition-all"
                          style={{ width: `${barWidth}%` }}
                        />
                      </div>
                      {relatedSubs.length > 0 && (
                        <div className="flex flex-wrap gap-1.5 mt-2">
                          {relatedSubs.slice(0, 4).map((sub) => (
                            <span
                              key={`${cat.category}-${sub.subcategory}`}
                              className="px-2 py-0.5 rounded bg-white text-slate-600 border border-slate-200 text-[10px] font-medium"
                            >
                              {sub.subcategory} ({sub.count})
                            </span>
                          ))}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            )}
          </div>

          <div className="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
            <span>Automated AI issue taxonomy classification</span>
            <span className="font-semibold text-[#2563EB]">Interactive Drill-Down</span>
          </div>
        </div>
      </div>

      {/* ── 7. WORD FREQUENCY & TEXT ANALYTICS SECTION (Word Cloud + Top Words + Top Phrases) ── */}
      <div
        id="word-frequency-analytics-section"
        className="bg-white rounded-xl border border-slate-200 p-6 shadow-xs space-y-5"
      >
        {/* Section Header + Interactive Sentiment & Category Text Filters */}
        <div className="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4 pb-4 border-b border-slate-200">
          <div className="flex items-start gap-3">
            <div className="w-10 h-10 rounded-xl bg-blue-50 text-[#2563EB] border border-blue-100 flex items-center justify-center font-bold shrink-0 mt-0.5">
              <MessageSquareText size={20} />
            </div>
            <div>
              <div className="flex items-center gap-2 flex-wrap">
                <h2 className="text-lg font-extrabold text-slate-900">
                  Word Frequency &amp; Text Analytics
                </h2>
                <span className="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                  {textAnalytics.total_concerns_analyzed ?? 0} Concerns Analyzed
                </span>
                <span className="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                  {textAnalytics.unique_meaningful_words ?? 0} Unique Meaningful Words
                </span>
              </div>
              <p className="text-xs text-slate-500 mt-1">
                Identifies the most frequently used words and recurring 2-word to 3-word phrases from real student concern descriptions. Student names, IDs, and stop words are automatically excluded.
              </p>
            </div>
          </div>

          {/* Sentiment & Category Drill-Down Controls for Text Analytics */}
          <div className="flex flex-wrap items-center gap-2">
            {/* Sentiment Filter Pills */}
            <div
              role="group"
              aria-label="Filter word analytics by sentiment"
              className="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5 text-xs flex-wrap"
            >
              {[
                { id: 'all', label: 'All Sentiments' },
                { id: 'positive', label: 'Positive' },
                { id: 'neutral', label: 'Neutral' },
                { id: 'negative', label: 'Negative' },
                { id: 'urgent', label: 'Urgent Emotion Flag' },
              ].map((s) => (
                <button
                  key={s.id}
                  type="button"
                  onClick={() => setTextFilters((prev) => ({ ...prev, sentiment: s.id }))}
                  className={`px-2.5 py-1 rounded-md font-semibold transition-colors ${
                    textFilters.sentiment === s.id
                      ? 'bg-[#2563EB] text-white shadow-2xs'
                      : 'text-slate-600 hover:text-slate-900'
                  }`}
                >
                  {s.label}
                </button>
              ))}
            </div>

            {/* Category Filter for Text Analytics */}
            <select
              aria-label="Filter text analytics by Issue Category"
              value={textFilters.category}
              onChange={(e) =>
                setTextFilters((prev) => ({
                  ...prev,
                  category: e.target.value,
                  subcategory: '',
                }))
              }
              className="input-field py-1.5 text-xs w-auto bg-white"
            >
              <option value="">All Categories</option>
              {(data.available_categories || []).map((cat) => (
                <option key={`text-cat-${cat}`} value={cat}>
                  {cat}
                </option>
              ))}
            </select>

            {/* Subcategory Filter for Text Analytics */}
            <select
              aria-label="Filter text analytics by Issue Subcategory"
              value={textFilters.subcategory}
              onChange={(e) =>
                setTextFilters((prev) => ({
                  ...prev,
                  subcategory: e.target.value,
                }))
              }
              className="input-field py-1.5 text-xs w-auto bg-white"
            >
              <option value="">All Subcategories</option>
              {filteredSubcategories.map((sub) => (
                <option
                  key={`text-sub-${sub.category}-${sub.subcategory}`}
                  value={sub.subcategory}
                >
                  {sub.subcategory}
                </option>
              ))}
            </select>

            {(textFilters.sentiment !== 'all' ||
              textFilters.category ||
              textFilters.subcategory ||
              textFilters.searchWord) && (
              <button
                type="button"
                onClick={() =>
                  setTextFilters({
                    sentiment: 'all',
                    category: '',
                    subcategory: '',
                    searchWord: '',
                  })
                }
                className="text-xs font-bold text-blue-600 hover:text-blue-800 px-2 py-1"
              >
                Clear Text Filter
              </button>
            )}
          </div>
        </div>

        {/* Active Filter Pill Notice */}
        {(textFilters.sentiment !== 'all' ||
          textFilters.category ||
          textFilters.subcategory ||
          textFilters.searchWord) && (
          <div className="flex items-center justify-between bg-blue-50/70 border border-blue-200 rounded-lg px-4 py-2 text-xs text-blue-900">
            <div className="flex items-center gap-2 flex-wrap">
              <SlidersHorizontal size={14} className="text-blue-600" />
              <span className="font-semibold">Active Text Filter:</span>
              {textFilters.sentiment !== 'all' && (
                <span className="px-2 py-0.5 rounded bg-white border border-blue-200 font-bold capitalize">
                  Sentiment: {textFilters.sentiment === 'urgent' ? 'Urgent Emotion Flag' : textFilters.sentiment}
                </span>
              )}
              {textFilters.category && (
                <span className="px-2 py-0.5 rounded bg-white border border-blue-200 font-bold">
                  Category: {textFilters.category}
                </span>
              )}
              {textFilters.subcategory && (
                <span className="px-2 py-0.5 rounded bg-white border border-blue-200 font-bold">
                  Subcategory: {textFilters.subcategory}
                </span>
              )}
              {textFilters.searchWord && (
                <span className="px-2 py-0.5 rounded bg-white border border-blue-200 font-bold">
                  Word: &ldquo;{textFilters.searchWord}&rdquo;
                </span>
              )}
            </div>
            {textLoading && (
              <span className="inline-flex items-center gap-1 text-blue-700 font-semibold">
                <RefreshCw size={12} className="animate-spin" /> Updating...
              </span>
            )}
          </div>
        )}

        {/* 3-Column Layout: Word Cloud + Most Frequently Used Words + Frequently Mentioned Phrases */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Panel 1: Visual Word Cloud */}
          <div className="bg-slate-50/40 rounded-xl border border-slate-200 p-5 flex flex-col justify-between">
            <div>
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2">
                  <Tag size={16} className="text-[#2563EB]" />
                  <h3 className="text-sm font-bold text-slate-900">Common Themes Word Cloud</h3>
                </div>
                <span className="text-[11px] font-semibold text-slate-500">
                  Size = Frequency
                </span>
              </div>
              <ChartErrorBoundary>
                <WeightedWordCloud
                  words={wordCloudItems}
                  activeWord={textFilters.searchWord}
                  onSelectWord={(word) =>
                    setTextFilters((prev) => ({ ...prev, searchWord: word }))
                  }
                />
              </ChartErrorBoundary>
            </div>
            <div className="mt-3 pt-2.5 border-t border-slate-200/70 flex items-center justify-between text-[11px] text-slate-500">
              <span>Click any word to filter phrases</span>
              <span className="font-semibold text-slate-600">PII &amp; Stop Words Removed</span>
            </div>
          </div>

          {/* Panel 2: Most Frequently Used Words (Ranked List) */}
          <div className="bg-slate-50/40 rounded-xl border border-slate-200 p-5 flex flex-col justify-between">
            <div>
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2">
                  <Hash size={16} className="text-[#2563EB]" />
                  <h3 className="text-sm font-bold text-slate-900">Most Frequently Used Words</h3>
                </div>
                <span className="text-[11px] font-semibold text-slate-500">
                  Count &amp; Concern Share
                </span>
              </div>

              {topWordsList.length === 0 ? (
                <div className="py-12 text-center text-xs text-slate-400">
                  No meaningful words matched the current filter.
                </div>
              ) : (
                <div
                  data-testid="most-frequent-words-list"
                  className="space-y-2.5 max-h-[340px] overflow-y-auto pr-1"
                >
                  {topWordsList.slice(0, 12).map((item, idx) => {
                    const barPct = Math.max(8, Math.round((item.count / maxWordCount) * 100));
                    return (
                      <div
                        key={item.word || item.text}
                        className="p-2.5 rounded-lg bg-white border border-slate-200/80 shadow-2xs"
                      >
                        <div className="flex items-center justify-between text-xs mb-1">
                          <div className="flex items-center gap-2">
                            <span className="w-5 h-5 rounded bg-blue-50 text-[#1E3A8A] font-bold text-[10px] flex items-center justify-center border border-blue-100">
                              #{item.rank || idx + 1}
                            </span>
                            <span className="font-bold text-slate-900">{item.word || item.text}</span>
                          </div>
                          <div className="text-right">
                            <span className="font-extrabold text-slate-900">{item.count}</span>
                            <span className="text-[11px] text-slate-500 ml-1">
                              ({item.percentage}% of concerns)
                            </span>
                          </div>
                        </div>
                        <div className="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                          <div
                            className="h-full bg-[#2563EB] rounded-full"
                            style={{ width: `${barPct}%` }}
                          />
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
            <div className="mt-3 pt-2.5 border-t border-slate-200/70 flex items-center justify-between text-[11px] text-slate-500">
              <span>Normalized root terms</span>
              <span className="font-semibold text-slate-600">
                Top {Math.min(12, topWordsList.length)} of {textAnalytics.unique_meaningful_words || 0} words
              </span>
            </div>
          </div>

          {/* Panel 3: Frequently Mentioned Phrases (2-Word & 3-Word Phrases) */}
          <div className="bg-slate-50/40 rounded-xl border border-slate-200 p-5 flex flex-col justify-between">
            <div>
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2">
                  <Quote size={16} className="text-[#2563EB]" />
                  <h3 className="text-sm font-bold text-slate-900">Frequently Mentioned Phrases</h3>
                </div>
                <span className="text-[11px] font-semibold text-slate-500">
                  2-Word &amp; 3-Word Phrases
                </span>
              </div>

              {topPhrasesList.length === 0 ? (
                <div className="py-12 text-center text-xs text-slate-400">
                  No multi-word phrases available for the selected filter.
                </div>
              ) : (
                <div
                  data-testid="frequent-phrases-list"
                  className="space-y-2.5 max-h-[340px] overflow-y-auto pr-1"
                >
                  {topPhrasesList.slice(0, 12).map((item, idx) => {
                    const barPct = Math.max(8, Math.round((item.count / maxPhraseCount) * 100));
                    return (
                      <div
                        key={item.phrase}
                        className="p-2.5 rounded-lg bg-white border border-slate-200/80 shadow-2xs"
                      >
                        <div className="flex items-center justify-between text-xs mb-1 gap-2">
                          <div className="flex items-center gap-2 min-w-0">
                            <span className="w-5 h-5 rounded bg-slate-100 text-slate-700 font-bold text-[10px] flex items-center justify-center border border-slate-200 shrink-0">
                              #{item.rank || idx + 1}
                            </span>
                            <span className="font-bold text-slate-900 truncate">
                              &ldquo;{item.phrase}&rdquo;
                            </span>
                            <span className="px-1.5 py-0.2 rounded text-[9px] font-semibold bg-blue-50 text-blue-700 border border-blue-100 shrink-0">
                              {item.word_count}w
                            </span>
                          </div>
                          <div className="text-right shrink-0">
                            <span className="font-extrabold text-[#1E3A8A]">{item.count}x</span>
                            <span className="text-[11px] text-slate-500 ml-1">
                              ({item.percentage}%)
                            </span>
                          </div>
                        </div>
                        <div className="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                          <div
                            className="h-full bg-[#1E3A8A] rounded-full"
                            style={{ width: `${barPct}%` }}
                          />
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
            <div className="mt-3 pt-2.5 border-t border-slate-200/70 flex items-center justify-between text-[11px] text-slate-500">
              <span>Extracted from real concern text</span>
              <span className="font-semibold text-slate-600">Bigrams &amp; Trigrams</span>
            </div>
          </div>
        </div>
      </div>

      {/* ── 8. ANALYTICS OVERVIEW: Volume & Resolution Trends, Priority & Unit Comparisons ── */}
      {data.overview_analytics && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Left: Concern Volume & Resolution Trend (BarChart) */}
            <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-xs flex flex-col justify-between">
              <div>
                <div className="flex items-center justify-between mb-4">
                  <div className="flex items-center gap-2.5">
                    <div className="w-9 h-9 rounded-lg bg-blue-50 text-[#2563EB] border border-blue-100 flex items-center justify-center font-bold">
                      <BarChart3 size={18} />
                    </div>
                    <div>
                      <h2 className="text-base font-bold text-slate-900">
                        Concern Volume &amp; Resolution Trend
                      </h2>
                      <p className="text-xs text-slate-500 mt-0.5">
                        Daily submitted concerns vs. resolved concerns over the last 30 days
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2 text-xs">
                    <span className="px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200 font-bold">
                      {overviewSummary.unresolved_concerns ?? 0} Unresolved
                    </span>
                    {overviewSummary.overdue_concerns > 0 && (
                      <span className="px-2.5 py-1 rounded-full bg-red-50 text-red-700 border border-red-200 font-bold">
                        {overviewSummary.overdue_concerns} Overdue
                      </span>
                    )}
                  </div>
                </div>

                {volumeTrend.length === 0 ? (
                  <div className="h-60 flex flex-col items-center justify-center text-slate-400 bg-slate-50/60 rounded-xl border border-slate-200">
                    <BarChart3 size={30} className="mb-2 text-slate-300" />
                    <p className="text-xs font-semibold text-slate-700">
                      No submission or resolution activity recorded in the last 30 days
                    </p>
                  </div>
                ) : (
                  <ChartErrorBoundary>
                    <div className="h-60 w-full min-h-[210px]">
                      <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={volumeTrend} margin={{ top: 10, right: 15, left: -15, bottom: 5 }}>
                          <CartesianGrid strokeDasharray="3 3" stroke="#E2E8F0" />
                          <XAxis
                            dataKey="date"
                            tick={{ fontSize: 11, fill: '#64748B' }}
                            tickFormatter={(val) => {
                              try {
                                const d = new Date(val);
                                return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
                              } catch {
                                return val;
                              }
                            }}
                          />
                          <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: '#64748B' }} />
                          <Tooltip
                            contentStyle={{
                              backgroundColor: '#ffffff',
                              borderRadius: '8px',
                              border: '1px solid #e2e8f0',
                              fontSize: '12px',
                            }}
                          />
                          <Legend wrapperStyle={{ fontSize: '12px' }} />
                          <Bar dataKey="submitted" name="Submitted" fill="#2563EB" radius={[4, 4, 0, 0]} />
                          <Bar dataKey="resolved" name="Resolved" fill="#10B981" radius={[4, 4, 0, 0]} />
                        </BarChart>
                      </ResponsiveContainer>
                    </div>
                  </ChartErrorBoundary>
                )}
              </div>

              {/* Unresolved Status Breakdown Strip */}
              <div className="mt-4 pt-3 border-t border-slate-100 grid grid-cols-4 gap-2 text-center text-xs">
                <div className="p-2 rounded-lg bg-slate-50 border border-slate-200/70">
                  <span className="text-[10px] font-bold text-slate-400 uppercase block">Pending</span>
                  <span className="font-extrabold text-amber-700">{overviewSummary.pending_concerns ?? 0}</span>
                </div>
                <div className="p-2 rounded-lg bg-slate-50 border border-slate-200/70">
                  <span className="text-[10px] font-bold text-slate-400 uppercase block">In Progress</span>
                  <span className="font-extrabold text-blue-700">{overviewSummary.in_progress_concerns ?? 0}</span>
                </div>
                <div className="p-2 rounded-lg bg-slate-50 border border-slate-200/70">
                  <span className="text-[10px] font-bold text-slate-400 uppercase block">Escalated</span>
                  <span className="font-extrabold text-orange-700">{overviewSummary.escalated_concerns ?? 0}</span>
                </div>
                <div className="p-2 rounded-lg bg-slate-50 border border-slate-200/70">
                  <span className="text-[10px] font-bold text-slate-400 uppercase block">Resolved</span>
                  <span className="font-extrabold text-emerald-700">{overviewSummary.resolved_concerns ?? 0}</span>
                </div>
              </div>
            </div>

            {/* Right: Priority Distribution & Department / Academic Unit Comparison */}
            <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-xs flex flex-col justify-between space-y-5">
              {/* Top Half: Administrative Priority Distribution */}
              <div>
                <div className="flex items-center justify-between mb-3">
                  <div className="flex items-center gap-2">
                    <AlertTriangle size={17} className="text-[#2563EB]" />
                    <h2 className="text-base font-bold text-slate-900">
                      Administrative Priority Distribution
                    </h2>
                  </div>
                  <span className="text-xs text-slate-500">Impact-Based Priority</span>
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                  {priorityList.map((p) => {
                    const colorMap = {
                      critical: 'bg-red-50 border-red-200 text-red-700',
                      high: 'bg-amber-50 border-amber-200 text-amber-800',
                      medium: 'bg-blue-50 border-blue-200 text-blue-700',
                      low: 'bg-slate-50 border-slate-200 text-slate-700',
                    };
                    return (
                      <div
                        key={p.priority}
                        className={`p-3 rounded-xl border ${colorMap[p.priority] || colorMap.medium}`}
                      >
                        <div className="text-[10px] font-bold uppercase tracking-wider opacity-80">
                          {p.priority}
                        </div>
                        <div className="text-xl font-black mt-0.5">{p.count}</div>
                        <div className="text-[11px] font-medium opacity-75">{p.percentage}% of total</div>
                      </div>
                    );
                  })}
                </div>
              </div>

              {/* Bottom Half: Department / Academic Unit Comparison */}
              <div className="pt-4 border-t border-slate-100">
                <div className="flex items-center justify-between mb-3">
                  <div className="flex items-center gap-2">
                    <Building2 size={17} className="text-[#2563EB]" />
                    <h3 className="text-sm font-bold text-slate-900">
                      Department &amp; Academic Unit Comparison
                    </h3>
                  </div>
                  <span className="text-[11px] text-slate-500">
                    Volume, Resolution &amp; Negative/Urgent Tone
                  </span>
                </div>

                {organizationList.length === 0 ? (
                  <p className="text-xs text-slate-400 py-4 text-center">
                    No organizational unit data available for this filter.
                  </p>
                ) : (
                  <div className="space-y-2 max-h-44 overflow-y-auto pr-1">
                    {organizationList.slice(0, 6).map((org, idx) => (
                      <div
                        key={`${org.type}-${org.name}-${idx}`}
                        className="p-2.5 rounded-lg bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-3 text-xs"
                      >
                        <div className="min-w-0">
                          <div className="font-bold text-slate-900 truncate">{org.name}</div>
                          <div className="text-[10px] text-slate-500">{org.type}</div>
                        </div>
                        <div className="flex items-center gap-2 shrink-0">
                          <span className="px-2 py-0.5 rounded bg-white border border-slate-200 font-bold text-slate-800">
                            {org.total} Total
                          </span>
                          <span className="px-2 py-0.5 rounded bg-emerald-50 border border-emerald-200 font-semibold text-emerald-700">
                            {org.resolved} Resolved
                          </span>
                          {org.unresolved > 0 && (
                            <span className="px-2 py-0.5 rounded bg-amber-50 border border-amber-200 font-semibold text-amber-800">
                              {org.unresolved} Open
                            </span>
                          )}
                          {org.negative_or_urgent > 0 && (
                            <span className="px-2 py-0.5 rounded bg-red-50 border border-red-200 font-semibold text-red-700">
                              {org.negative_or_urgent} Neg/Urg
                            </span>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Historical & Predictive Resolution-Time Analytics Section */}
          {data.resolution_analytics && (
            <ResolutionAnalyticsSection
              analytics={data.resolution_analytics}
              role={user?.role}
            />
          )}
        </div>
      )}

      {/* ── 9. FLAGGED HIGH-URGENCY & NEGATIVE CONCERNS TABLE ── */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
          <div>
            <h2 className="text-base font-bold text-slate-900 flex items-center gap-2">
              <AlertTriangle size={18} className="text-red-600" />
              <span>Flagged High-Urgency &amp; Strong Negative Concerns</span>
            </h2>
            <p className="text-xs text-slate-500 mt-0.5">
              Concerns evaluated with strong negative/urgent emotional tone (score &lt; -0.7) or Critical administrative priority
            </p>
          </div>
          <div className="flex items-center gap-2">
            <span className="px-3 py-1 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200">
              {data?.flagged?.length || 0} Flagged Shown
            </span>
          </div>
        </div>

        {/* Explicit Distinction Callout: Emotional Tone (Sentiment) vs Administrative Priority */}
        <div className="px-6 py-3 bg-blue-50/60 border-b border-blue-100 flex items-start gap-2.5 text-xs text-slate-700">
          <Info size={16} className="text-[#2563EB] shrink-0 mt-0.5" />
          <div>
            <span className="font-bold text-slate-900">
              Emotional Tone vs. Administrative Priority:
            </span>{' '}
            Negative sentiment or an <span className="font-semibold text-red-700">Urgent Emotion Flag</span> reflects the emotional tone of the student&apos;s message and{' '}
            <span className="font-semibold underline">does not automatically assign Critical administrative priority</span>. Administrative priority is determined separately by operational impact, safety, and scope.
          </div>
        </div>

        {/* SAO Option A Governance Notice */}
        {data.flagged_summary?.sao_privacy_restricted && (
          <div className="px-6 py-2.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between text-xs text-slate-600">
            <span>
              <strong>SAO Option A Privacy Governance:</strong> Institution-wide flagged total is{' '}
              <strong>{data.flagged_summary.total_scope_flagged}</strong> concern(s). Individual concern rows below are displayed only for concerns directly assigned or routed to SAO ({data.flagged_summary.shown_count} shown).
            </span>
          </div>
        )}

        {!data?.flagged || data.flagged.length === 0 ? (
          <div className="p-8">
            <EmptyState
              icon={CheckCircle2}
              title="No high-urgency concerns flagged in your individual view"
              description={
                user?.role === 'sao'
                  ? 'No SAO-assigned concerns are currently flagged with critical priority or strong negative sentiment.'
                  : 'All evaluated concerns within your current scope and filters are within normal sentiment and priority parameters.'
              }
            />
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-200 text-left text-xs">
              <thead className="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                <tr>
                  <th scope="col" className="px-6 py-3.5">Concern &amp; AI Summary</th>
                  <th scope="col" className="px-6 py-3.5">Student / Unit</th>
                  <th scope="col" className="px-6 py-3.5">Emotional Tone (Sentiment)</th>
                  <th scope="col" className="px-6 py-3.5">Admin Priority</th>
                  <th scope="col" className="px-6 py-3.5">Status</th>
                  <th scope="col" className="px-6 py-3.5 text-right">Action</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-slate-100">
                {data.flagged.map((concern) => {
                  const detailPath = getConcernDetailPath(concern);
                  return (
                    <tr key={concern.concern_id} className="hover:bg-slate-50/90 transition-colors">
                      <td className="px-6 py-4 max-w-md">
                        <div className="flex items-center gap-2 flex-wrap mb-1">
                          <span className="font-bold text-slate-900 text-sm">{concern.title}</span>
                          {concern.issue_category && (
                            <AiCategoryBadge
                              category={concern.issue_category}
                              subcategory={concern.issue_subcategory}
                              size="xs"
                            />
                          )}
                        </div>
                        <p className="text-slate-600 text-xs leading-relaxed break-words whitespace-normal">
                          {concern.ai_summary || concern.description}
                        </p>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="font-semibold text-slate-900">
                          {concern.is_anonymous ? 'Anonymous' : concern.student?.name || 'Anonymous'}
                        </div>
                        <div className="text-[11px] text-slate-500 mt-0.5">
                          {concern.academic_unit?.name || concern.department?.name || 'General'}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <SentimentBadge
                          label={concern.sentiment_label}
                          score={concern.sentiment_score}
                          showScore={true}
                          size="xs"
                        />
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <PriorityBadge
                          priority={concern.priority}
                          isOverridden={concern.is_priority_overridden}
                          size="xs"
                        />
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <StatusBadge status={concern.status} size="xs" />
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-right">
                        {detailPath ? (
                          <Link
                            to={detailPath}
                            className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold text-[#2563EB] bg-blue-50 hover:bg-blue-100 border border-blue-200 transition-colors"
                          >
                            <span>View</span>
                            <ExternalLink size={12} />
                          </Link>
                        ) : (
                          <span className="text-slate-400">—</span>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
};

export default SentimentDashboard;
