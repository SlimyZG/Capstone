import { useState, useEffect, useCallback } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { 
  FolderOpen, 
  Hourglass, 
  CheckCircle2, 
  RotateCcw, 
  RefreshCw,
  PlusSquare,
  ArrowRight
} from 'lucide-react';
import api from '../api';
import PageHeader from '../components/PageHeader';
import StatCard from '../components/StatCard';
import StatusBadge from '../components/StatusBadge';
import AiCategoryBadge from '../components/AiCategoryBadge';
import EmptyState from '../components/EmptyState';
import LoadingSpinner from '../components/LoadingSpinner';

const StudentDashboard = () => {
  const { user } = useAuth();
  const location = useLocation();
  const [concerns, setConcerns] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchConcerns = useCallback(async (isManualRefresh = false) => {
    if (isManualRefresh) setRefreshing(true);
    try {
      const res = await api.get(`/concerns?_t=${Date.now()}`);
      setConcerns(res.data.concerns || []);
    } catch (err) {
      console.error('Failed to load concerns', err);
    } finally {
      setLoading(false);
      if (isManualRefresh) setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    fetchConcerns();
  }, [fetchConcerns, location.key]);

  const totalConcerns = concerns.length;
  const resolvedConcerns = concerns.filter(c => c.status === 'resolved' || c.status === 'closed').length;
  const pendingConcerns = concerns.filter(c => 
    ['pending', 'in_progress', 'escalated', 'approved', 'returned'].includes(c.status)
  ).length;

  const recentConcerns = [...concerns]
    .sort((a, b) => {
      const dateA = new Date(a.last_updated || a.submitted_at || a.created_at || 0);
      const dateB = new Date(b.last_updated || b.submitted_at || b.created_at || 0);
      return dateB - dateA;
    })
    .slice(0, 5);

  const formatDate = (dateString) => {
    if (!dateString) return '—';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '—';
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24)); 
    
    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Yesterday';
    if (diffDays <= 7) return `${diffDays} days ago`;
    
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  };

  if (loading) {
    return <LoadingSpinner message="Loading your student dashboard..." fullPage />;
  }

  const headerActions = (
    <>
      <button
        onClick={() => fetchConcerns(true)}
        disabled={refreshing}
        className="btn-secondary text-xs py-2 px-3"
        title="Refresh dashboard data"
      >
        <RefreshCw size={13} className={`mr-1.5 ${refreshing ? 'animate-spin text-blue-600' : 'text-slate-500'}`} />
        <span>{refreshing ? 'Refreshing...' : 'Refresh'}</span>
      </button>
      <Link
        to="/student/submit"
        className="btn-primary text-xs py-2 px-3.5"
      >
        <PlusSquare size={14} className="mr-1.5" />
        New Concern
      </Link>
    </>
  );

  return (
    <div className="space-y-6 sm:space-y-8">
      {/* Page Header */}
      <PageHeader
        category="Student Portal"
        title={`Welcome, ${user?.name || 'Student'}`}
        subtitle="Track your academic inquiries, administrative support tickets, and department resolutions in real time."
        actions={headerActions}
      />

      {/* KPI Stat Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
        <StatCard
          title="Total Concerns"
          value={totalConcerns}
          icon={FolderOpen}
          subtitle="All submitted support requests"
          iconBg="bg-blue-50 text-[#1e3a8a] border-blue-100"
        />
        <StatCard
          title="Pending / In Progress"
          value={pendingConcerns}
          icon={Hourglass}
          badge={pendingConcerns > 0 ? `${pendingConcerns} Active` : undefined}
          subtitle="Awaiting review or currently investigated"
          iconBg="bg-amber-50 text-amber-700 border-amber-100"
        />
        <StatCard
          title="Resolved Concerns"
          value={resolvedConcerns}
          icon={CheckCircle2}
          subtitle="Successfully addressed and closed"
          iconBg="bg-emerald-50 text-emerald-700 border-emerald-100"
        />
      </div>

      {/* Recent Activity Section */}
      <div className="card">
        <div className="px-5 sm:px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h2 className="text-base font-bold text-slate-900">Recent Activity</h2>
            <p className="text-xs text-slate-500 mt-0.5">Your most recent submitted concerns and progress updates</p>
          </div>
          <Link
            to="/student/concerns"
            className="text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors inline-flex items-center gap-1"
          >
            <span>View All ({totalConcerns})</span>
            <ArrowRight size={13} />
          </Link>
        </div>
        
        <div className="divide-y divide-slate-100">
          {recentConcerns.length === 0 ? (
            <EmptyState
              icon={FolderOpen}
              title="No concerns submitted yet"
              description="You have not submitted any concerns. If you need assistance with academic or administrative matters, submit a concern using the button below."
              action={
                <Link to="/student/submit" className="btn-primary text-xs">
                  <PlusSquare size={14} className="mr-1.5" />
                  Submit Your First Concern
                </Link>
              }
              className="border-0 shadow-none py-10"
            />
          ) : (
            recentConcerns.map(concern => {
              const departmentLabel = concern.department?.name 
                || concern.academic_unit?.name 
                || (concern.workflow_type === 'academic' ? 'Academic College' : 'Department');
              
              return (
                <Link
                  key={concern.concern_id}
                  to={`/student/concerns/${concern.concern_id}`}
                  className="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 hover:bg-slate-50/80 transition-colors group"
                >
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2 mb-1">
                      <span className="text-xs font-mono font-bold text-slate-400">
                        #REQ-{concern.concern_id.toString().padStart(4, '0')}
                      </span>
                      <span className={`text-[10px] font-semibold px-2 py-0.5 rounded-full ${
                        concern.workflow_type === 'academic' 
                          ? 'bg-blue-50 text-blue-700 border border-blue-200' 
                          : 'bg-indigo-50 text-indigo-700 border border-indigo-200'
                      }`}>
                        {concern.workflow_type === 'academic' ? 'Academic' : 'Non-Academic'}
                      </span>
                      {concern.issue_category && (
                        <AiCategoryBadge
                          category={concern.issue_category}
                          subcategory={concern.issue_subcategory}
                          size="xs"
                        />
                      )}
                    </div>
                    <h3 className="text-sm sm:text-base font-bold text-slate-900 group-hover:text-blue-600 transition-colors truncate">
                      {concern.title}
                    </h3>
                    <p className="text-xs text-slate-500 mt-1 flex flex-wrap items-center gap-2">
                      <span>Submitted: {formatDate(concern.submitted_at || concern.created_at)}</span>
                      <span className="w-1 h-1 bg-slate-300 rounded-full"></span>
                      <span className="truncate max-w-[220px] sm:max-w-xs font-medium text-slate-700">{departmentLabel}</span>
                      {concern.last_updated && concern.last_updated !== concern.submitted_at && (
                        <>
                          <span className="w-1 h-1 bg-slate-300 rounded-full"></span>
                          <span className="text-slate-400">Updated {formatDate(concern.last_updated)}</span>
                        </>
                      )}
                    </p>
                  </div>
                  <div className="self-start sm:self-center shrink-0">
                    <StatusBadge status={concern.status} />
                  </div>
                </Link>
              );
            })
          )}
        </div>
      </div>
    </div>
  );
};

export default StudentDashboard;
