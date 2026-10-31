import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { 
  ShieldCheck, 
  Users, 
  FileText, 
  BarChart3, 
  CheckCircle2, 
  Clock, 
  AlertCircle,
  ArrowRight,
  Layers,
  Activity
} from 'lucide-react';
import api from '../api';
import PageHeader from '../components/PageHeader';
import StatCard from '../components/StatCard';
import LoadingSpinner from '../components/LoadingSpinner';

const SaoDashboard = () => {
  const { user } = useAuth();
  const [backendStatus, setBackendStatus] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchFoundationStatus = async () => {
      try {
        const res = await api.get('/sao/dashboard');
        setBackendStatus(res.data);
      } catch (err) {
        console.error('Failed to connect to SAO endpoint', err);
        setError(err.response?.data?.message || 'Unable to reach SAO backend endpoint.');
      } finally {
        setLoading(false);
      }
    };

    fetchFoundationStatus();
  }, []);

  return (
    <div className="space-y-6 sm:space-y-8">
      {/* Welcome Header */}
      <div className="card p-6 bg-white border border-slate-200">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div className="space-y-1">
            <div className="flex items-center gap-2 mb-1">
              <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-[#1e3a8a] border border-blue-200">
                <ShieldCheck size={14} className="text-blue-600" />
                Student Affairs Office (SAO)
              </span>
              <span className="text-xs text-slate-500 font-medium hidden sm:inline-block">• System Oversight & Governance</span>
            </div>
            <h1 className="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">
              Welcome, {user?.name || 'Student Affairs Office'}
            </h1>
            <p className="text-slate-500 text-sm max-w-2xl leading-relaxed">
              Central administration console for institutional student concern oversight, staff account administration, and university-wide sentiment analytics.
            </p>
          </div>

          <div className="flex items-center gap-2 self-start md:self-center bg-slate-50 px-3.5 py-2 rounded-lg border border-slate-200 text-xs text-slate-600">
            <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
            <span className="font-medium">System Status:</span>
            <span className="font-bold text-slate-900">
              {loading ? 'Checking...' : backendStatus?.status === 'foundation_active' ? 'Operational' : 'Active'}
            </span>
          </div>
        </div>
      </div>

      {/* Backend Notice if any */}
      {error && (
        <div className="bg-amber-50 border border-amber-200 text-amber-800 p-4 rounded-xl flex items-start gap-3">
          <AlertCircle size={18} className="text-amber-600 shrink-0 mt-0.5" />
          <div className="text-xs sm:text-sm">
            <span className="font-bold">Notice:</span> {error}
          </div>
        </div>
      )}

      {/* Core SAO Functions Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 sm:gap-6">
        {/* Card 1: Assigned Concerns */}
        <div className="card p-6 flex flex-col justify-between hover:border-blue-300 transition-all">
          <div className="space-y-3">
            <div className="w-10 h-10 rounded-lg bg-blue-50 text-[#1e3a8a] flex items-center justify-center font-bold border border-blue-100">
              <ShieldCheck size={20} />
            </div>
            <div>
              <div className="flex items-center justify-between mb-1">
                <h3 className="text-base font-bold text-slate-900">Assigned Concerns</h3>
                <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                  Direct Queue
                </span>
              </div>
              <p className="text-slate-500 text-xs leading-relaxed">
                Review student cases officially assigned to the Student Affairs Office and execute authorized priority overrides.
              </p>
            </div>
          </div>

          <div className="mt-5 pt-3 border-t border-slate-100">
            <Link
              to="/sao/assigned-concerns"
              className="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors"
            >
              <span>Manage Assigned Cases</span>
              <ArrowRight size={13} />
            </Link>
          </div>
        </div>

        {/* Card 2: User Management */}
        <div className="card p-6 flex flex-col justify-between hover:border-blue-300 transition-all">
          <div className="space-y-3">
            <div className="w-10 h-10 rounded-lg bg-slate-50 text-slate-700 flex items-center justify-center font-bold border border-slate-200">
              <Users size={20} />
            </div>
            <div>
              <div className="flex items-center justify-between mb-1">
                <h3 className="text-base font-bold text-slate-900">User Management</h3>
                <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                  Accounts
                </span>
              </div>
              <p className="text-slate-500 text-xs leading-relaxed">
                Create and manage Academic Dean and Department Staff accounts, assign organizational units, and reset credentials.
              </p>
            </div>
          </div>

          <div className="mt-5 pt-3 border-t border-slate-100">
            <Link
              to="/sao/users"
              className="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors"
            >
              <span>Manage Accounts</span>
              <ArrowRight size={13} />
            </Link>
          </div>
        </div>

        {/* Card 3: Concern Oversight */}
        <div className="card p-6 flex flex-col justify-between hover:border-blue-300 transition-all">
          <div className="space-y-3">
            <div className="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center font-bold border border-blue-100">
              <FileText size={20} />
            </div>
            <div>
              <div className="flex items-center justify-between mb-1">
                <h3 className="text-base font-bold text-slate-900">Concern Oversight</h3>
                <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                  Option A
                </span>
              </div>
              <p className="text-slate-500 text-xs leading-relaxed">
                Institution-wide oversight of concern volumes, SLA performance metrics, and department routing histories.
              </p>
            </div>
          </div>

          <div className="mt-5 pt-3 border-t border-slate-100">
            <Link
              to="/sao/oversight"
              className="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors"
            >
              <span>Open Oversight Table</span>
              <ArrowRight size={13} />
            </Link>
          </div>
        </div>

        {/* Card 4: Reports & Analytics */}
        <div className="card p-6 flex flex-col justify-between hover:border-blue-300 transition-all">
          <div className="space-y-3">
            <div className="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold border border-emerald-100">
              <BarChart3 size={20} />
            </div>
            <div>
              <div className="flex items-center justify-between mb-1">
                <h3 className="text-base font-bold text-slate-900">Analytics & Sentiment</h3>
                <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                  AI Powered
                </span>
              </div>
              <p className="text-slate-500 text-xs leading-relaxed">
                Real-time sentiment distribution, AI Problem Categories, thematic analysis, and cross-department trends.
              </p>
            </div>
          </div>

          <div className="mt-5 pt-3 border-t border-slate-100">
            <Link
              to="/analytics"
              className="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors"
            >
              <span>Explore Analytics</span>
              <ArrowRight size={13} />
            </Link>
          </div>
        </div>
      </div>

      {/* Option A Policy Reminder Banner */}
      <div className="card p-5 bg-slate-50 border border-slate-200">
        <div className="flex items-start gap-3">
          <CheckCircle2 size={18} className="text-blue-600 shrink-0 mt-0.5" />
          <div className="text-xs leading-relaxed text-slate-600">
            <p className="font-bold text-slate-900 mb-0.5">SAO Option A Privacy & Governance Architecture</p>
            <p>
              SAO maintains institutional aggregate oversight across all departments and academic units. Direct individual concern access and manual priority override authority remain strictly scoped to Student Affairs Office assigned concerns, preserving student privacy and departmental decentralized authority.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default SaoDashboard;
