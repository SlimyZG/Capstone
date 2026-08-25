import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { FolderOpen, Hourglass, CheckCircle2, Clock } from 'lucide-react';
import api from '../api';

const StudentDashboard = () => {
  const { user } = useAuth();
  const [concerns, setConcerns] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchConcerns = async () => {
      try {
        const res = await api.get('/concerns');
        setConcerns(res.data.concerns);
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchConcerns();
  }, []);

  const totalConcerns = concerns.length;
  const pendingConcerns = concerns.filter(c => ['submitted', 'in_progress'].includes(c.status)).length;
  const resolvedConcerns = concerns.filter(c => c.status === 'resolved').length;
  
  // Sort by created_at desc
  const recentConcerns = [...concerns].sort((a, b) => new Date(b.created_at) - new Date(a.created_at)).slice(0, 3);

  const getStatusBadge = (status) => {
    switch (status) {
      case 'submitted': return <span className="px-2.5 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-medium">Pending Review</span>;
      case 'in_progress': return <span className="px-2.5 py-1 bg-amber-50 text-amber-600 rounded-full text-xs font-medium border border-amber-200">Action Required</span>;
      case 'resolved': return <span className="px-2.5 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-medium">Resolved</span>;
      default: return <span className="px-2.5 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-medium">{status}</span>;
    }
  };

  const getStatusIcon = (status) => {
    switch (status) {
      case 'submitted': return <Clock size={16} className="text-amber-600" />;
      case 'in_progress': return <Hourglass size={16} className="text-amber-600" />;
      case 'resolved': return <CheckCircle2 size={16} className="text-blue-600" />;
      default: return <Clock size={16} className="text-slate-600" />;
    }
  };

  const getStatusIconBg = (status) => {
    switch (status) {
      case 'resolved': return 'bg-blue-50';
      default: return 'bg-amber-50';
    }
  };

  const formatDate = (dateString) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 
    
    if (diffDays === 0) return 'Submitted today';
    if (diffDays === 1) return 'Submitted yesterday';
    if (diffDays <= 7) return `Submitted ${diffDays} days ago`;
    
    return `Submitted on ${date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
  };

  if (loading) return <div className="text-slate-500">Loading dashboard...</div>;

  return (
    <div>
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-slate-900 tracking-tight">Welcome, {user?.name}</h1>
        <p className="text-slate-600 mt-2 text-sm max-w-2xl leading-relaxed">
          Here is a summary of your recent support requests and concerns. Keep track of your academic and administrative inquiries here.
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm relative overflow-hidden">
          <div className="bg-blue-50 h-10 w-10 rounded-lg flex items-center justify-center mb-10">
            <FolderOpen size={20} className="text-[#1e3a8a]" />
          </div>
          <p className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Total Concerns Submitted</p>
          <h3 className="text-3xl font-bold text-slate-900">{totalConcerns}</h3>
        </div>

        <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm relative overflow-hidden">
          <div className="flex justify-between items-start mb-10">
            <div className="bg-amber-50 h-10 w-10 rounded-lg flex items-center justify-center border border-amber-100">
              <Hourglass size={20} className="text-amber-700" />
            </div>
            {pendingConcerns > 0 && (
              <span className="px-2 py-1 bg-amber-50 text-amber-700 text-[10px] font-bold uppercase tracking-wider rounded-md border border-amber-100">
                Action Required
              </span>
            )}
          </div>
          <p className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Pending Concerns</p>
          <h3 className="text-3xl font-bold text-slate-900">{pendingConcerns}</h3>
        </div>

        <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm relative overflow-hidden">
          <div className="bg-blue-50 h-10 w-10 rounded-lg flex items-center justify-center mb-10 border border-blue-100">
            <CheckCircle2 size={20} className="text-blue-700" />
          </div>
          <p className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Resolved Concerns</p>
          <h3 className="text-3xl font-bold text-slate-900">{resolvedConcerns}</h3>
          
          <div className="absolute -right-4 -bottom-4 w-24 h-24 bg-blue-50 rounded-full blur-2xl opacity-70"></div>
        </div>
      </div>

      <div className="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div className="px-6 py-5 border-b border-slate-100 flex justify-between items-center">
          <h2 className="text-lg font-bold text-slate-900">Recent Activity</h2>
          <Link to="/student/concerns" className="text-sm font-bold text-[#1e3a8a] hover:text-blue-800">View All</Link>
        </div>
        
        <div className="divide-y divide-slate-100">
          {recentConcerns.length === 0 ? (
            <div className="px-6 py-8 text-center text-slate-500 text-sm">
              No recent activity.
            </div>
          ) : (
            recentConcerns.map(concern => (
              <div key={concern.concern_id} className="p-6 flex items-start justify-between hover:bg-slate-50 transition-colors">
                <div className="flex gap-4">
                  <div className={`mt-1 h-8 w-8 rounded-full flex items-center justify-center flex-shrink-0 ${getStatusIconBg(concern.status)}`}>
                    {getStatusIcon(concern.status)}
                  </div>
                  <div>
                    <h4 className="text-sm font-bold text-slate-900">{concern.title}</h4>
                    <p className="text-xs text-slate-500 mt-1 flex items-center gap-2">
                      {formatDate(concern.created_at)} 
                      <span className="w-1 h-1 bg-slate-300 rounded-full"></span> 
                      {concern.department ? concern.department.name : concern.academic_unit?.name}
                    </p>
                  </div>
                </div>
                <div>
                  {getStatusBadge(concern.status)}
                </div>
              </div>
            ))
          )}
        </div>
      </div>
    </div>
  );
};

export default StudentDashboard;
