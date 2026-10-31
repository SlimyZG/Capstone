import { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { Bell, Check, Clock, AlertCircle, ArrowUpRight, CheckCircle2, RotateCcw, X, MessageSquare } from 'lucide-react';
import api from '../api';

const NotificationBell = () => {
  const { user } = useAuth();
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [isOpen, setIsOpen] = useState(false);
  const [toast, setToast] = useState(null);
  const dropdownRef = useRef(null);
  const knownNotificationIds = useRef(new Set());
  const initialLoadDone = useRef(false);
  const navigate = useNavigate();

  const extractData = (notif) => {
    if (!notif || !notif.data) return {};
    if (typeof notif.data === 'string') {
      try {
        return JSON.parse(notif.data);
      } catch (e) {
        return {};
      }
    }
    return notif.data;
  };

  const resolveUrl = (notifData) => {
    if (!notifData) return null;
    const concernId = notifData.concern_id;
    const incidentId = notifData.recurring_incident_id || notifData.incident_id;
    const event = notifData.event;
    const isMessage = event === 'new_message' || notifData.action_url?.includes('#messages');
    const hash = isMessage ? '#messages' : '';

    // Handle recurring incident notifications
    if (incidentId || ['recurring_incident_high', 'recurring_incident_critical', 'recurring_incident_major_critical'].includes(event)) {
      if (incidentId) {
        if (user?.role === 'sao') return `/sao/recurring-incidents/${incidentId}`;
        if (user?.role === 'dean') return `/dean/recurring-incidents/${incidentId}`;
        if (user?.role === 'dept_staff') return `/dept-staff/recurring-incidents/${incidentId}`;
      }
      if (user?.role === 'sao') return '/sao/recurring-incidents';
    }

    // Critical concern alert for SAO respecting Option A
    if (event === 'critical_concern_alert' && user?.role === 'sao') {
      return notifData.action_url || '/sao/oversight';
    }

    // Derive canonical route for the current user's role to prevent layout or permission mismatches
    if (concernId) {
      if (user?.role === 'student') return `/student/concerns/${concernId}${hash}`;
      if (user?.role === 'dept_staff') return `/dept-staff/concerns/${concernId}${hash}`;
      if (user?.role === 'dean') return `/dean/concerns/${concernId}${hash}`;
      if (user?.role === 'sao') return notifData.action_url || '/sao/oversight';
    }
    return notifData.action_url || null;
  };

  const fetchNotifications = async () => {
    try {
      const res = await api.get('/notifications');
      const items = res.data.notifications || [];
      const count = res.data.unread_count || 0;
      setNotifications(items);
      setUnreadCount(count);

      // Check for incoming notifications while using the dashboard to trigger a toast
      if (initialLoadDone.current) {
        const newlyArrived = items.filter(
          item => !knownNotificationIds.current.has(item.id) && !item.read_at
        );
        if (newlyArrived.length > 0) {
          const latest = newlyArrived[0];
          const data = extractData(latest);
          setToast({
            id: latest.id,
            title: data.title || 'New Notification',
            message: data.message || 'Workflow status updated.',
            url: resolveUrl(data),
          });
          // Auto dismiss toast after 5 seconds
          setTimeout(() => {
            setToast(current => (current?.id === latest.id ? null : current));
          }, 5000);
        }
      }

      // Record known IDs to avoid duplicate toasts
      items.forEach(item => knownNotificationIds.current.add(item.id));
      initialLoadDone.current = true;
    } catch (err) {
      console.error('Failed to fetch notifications', err);
    }
  };

  useEffect(() => {
    fetchNotifications();
    // Poll every 10 seconds for real-time notification updates
    const interval = setInterval(fetchNotifications, 10000);
    return () => clearInterval(interval);
  }, []);

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleNotificationClick = async (notif) => {
    // Mark as read in backend
    if (!notif.read_at) {
      try {
        await api.post(`/notifications/${notif.id}/read`);
        setNotifications(prev =>
          prev.map(n => (n.id === notif.id ? { ...n, read_at: new Date().toISOString() } : n))
        );
        setUnreadCount(prev => Math.max(0, prev - 1));
      } catch (err) {
        console.error(err);
      }
    }

    setIsOpen(false);

    const data = extractData(notif);
    const targetUrl = resolveUrl(data);
    if (targetUrl) {
      navigate(targetUrl);
    }
  };

  const handleMarkAllRead = async () => {
    try {
      await api.post('/notifications/read-all');
      setNotifications(prev => prev.map(n => ({ ...n, read_at: new Date().toISOString() })));
      setUnreadCount(0);
    } catch (err) {
      console.error(err);
    }
  };

  const getEventIcon = (event) => {
    switch (event) {
      case 'new_message':
        return <MessageSquare size={14} className="text-blue-600" />;
      case 'submitted':
        return <Clock size={14} className="text-blue-600" />;
      case 'forwarded_to_dean':
      case 'forwarded_to_cad':
      case 'sla_auto_escalated':
        return <ArrowUpRight size={14} className="text-purple-600" />;
      case 'sla_followup':
        return <Clock size={14} className="text-orange-600" />;
      case 'sla_overdue':
        return <AlertCircle size={14} className="text-rose-600" />;
      case 'approved':
      case 'cad_approved':
      case 'dean_approved':
        return <CheckCircle2 size={14} className="text-indigo-600" />;
      case 'returned':
      case 'cad_returned':
      case 'dean_returned':
        return <RotateCcw size={14} className="text-orange-600" />;
      case 'resolved':
        return <CheckCircle2 size={14} className="text-emerald-600" />;
      case 'critical_concern_alert':
      case 'recurring_incident_critical':
      case 'recurring_incident_major_critical':
        return <AlertCircle size={14} className="text-red-600" />;
      case 'recurring_incident_high':
        return <AlertCircle size={14} className="text-amber-600" />;
      default:
        return <AlertCircle size={14} className="text-slate-600" />;
    }
  };

  const formatRelativeTime = (isoString) => {
    if (!isoString) return '';
    const date = new Date(isoString);
    if (isNaN(date.getTime())) return '';
    const diff = Math.floor((Date.now() - date.getTime()) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
  };

  // Display top 5 latest notifications
  const topFive = notifications.slice(0, 5);

  return (
    <div className="relative" ref={dropdownRef}>
      {/* Toast Notification Popup */}
      {toast && (
        <div 
          onClick={() => {
            if (toast.url) navigate(toast.url);
            setToast(null);
          }}
          className="fixed top-5 right-5 z-50 max-w-sm bg-white rounded-xl shadow-xl border border-blue-200 p-4 flex items-start gap-3 cursor-pointer hover:bg-slate-50 transition-all animate-in slide-in-from-top-4 duration-300"
        >
          <div className="h-8 w-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center flex-shrink-0 mt-0.5">
            <Bell size={16} />
          </div>
          <div className="flex-1 min-w-0 pr-2">
            <p className="text-xs font-bold text-slate-900 truncate">{toast.title}</p>
            <p className="text-xs text-slate-600 mt-0.5 line-clamp-2 leading-relaxed">{toast.message}</p>
          </div>
          <button 
            onClick={(e) => {
              e.stopPropagation();
              setToast(null);
            }} 
            className="text-slate-400 hover:text-slate-600 p-1"
          >
            <X size={14} />
          </button>
        </div>
      )}

      {/* Bell Button */}
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="relative text-slate-500 hover:text-slate-700 p-2 rounded-lg hover:bg-slate-100 transition-colors"
        title="Notifications"
        aria-label="View notifications"
      >
        <Bell size={20} />
        {unreadCount > 0 && (
          <span className="absolute top-1 right-1 flex h-4 min-w-[16px] px-1 items-center justify-center rounded-full bg-red-600 text-[10px] font-bold text-white shadow-sm animate-pulse">
            {unreadCount > 9 ? '9+' : unreadCount}
          </span>
        )}
      </button>

      {/* Dropdown Menu */}
      {isOpen && (
        <div className="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-xl shadow-lg border border-slate-200 z-50 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
          <div className="px-4 py-3 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
            <div className="flex items-center gap-2">
              <span className="text-xs font-bold text-slate-900 uppercase tracking-wider">Notifications</span>
              {unreadCount > 0 && (
                <span className="px-2 py-0.5 bg-blue-100 text-blue-800 rounded-full text-[10px] font-bold">
                  {unreadCount} new
                </span>
              )}
            </div>
            {unreadCount > 0 && (
              <button
                onClick={handleMarkAllRead}
                className="text-[11px] font-semibold text-[#1e3a8a] hover:underline"
              >
                Mark all read
              </button>
            )}
          </div>

          <div className="max-h-80 overflow-y-auto divide-y divide-slate-100">
            {topFive.length === 0 ? (
              <div className="py-8 text-center text-slate-400 text-xs">
                No notifications right now.
              </div>
            ) : (
              topFive.map((n) => {
                const isUnread = !n.read_at;
                const data = extractData(n);
                return (
                  <div
                    key={n.id}
                    onClick={() => handleNotificationClick(n)}
                    className={`p-3.5 hover:bg-slate-50 transition-colors cursor-pointer flex items-start gap-3 ${
                      isUnread ? 'bg-blue-50/40' : 'bg-white'
                    }`}
                  >
                    <div className="mt-0.5 h-7 w-7 rounded-full bg-slate-100 flex items-center justify-center flex-shrink-0">
                      {getEventIcon(data.event)}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center justify-between gap-1">
                        <p className={`text-xs truncate ${isUnread ? 'font-bold text-slate-900' : 'font-semibold text-slate-700'}`}>
                          {data.title || 'Workflow Update'}
                        </p>
                        <span className="text-[10px] text-slate-400 flex-shrink-0">
                          {formatRelativeTime(n.created_at || data.created_at)}
                        </span>
                      </div>
                      <p className="text-xs text-slate-600 mt-0.5 line-clamp-2 leading-relaxed">
                        {data.message}
                      </p>
                    </div>
                    {isUnread && (
                      <span className="w-2 h-2 rounded-full bg-blue-600 mt-2 flex-shrink-0" />
                    )}
                  </div>
                );
              })
            )}
          </div>

          <div className="p-2 border-t border-slate-100 bg-slate-50 text-center">
            <span className="text-[10px] text-slate-400 font-medium">
              Showing {topFive.length} latest notifications
            </span>
          </div>
        </div>
      )}
    </div>
  );
};

export default NotificationBell;
