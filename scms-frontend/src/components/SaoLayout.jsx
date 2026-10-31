import { useState } from 'react';
import { Link, useLocation, useNavigate, Outlet } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import {
  LayoutDashboard,
  ShieldCheck,
  Users,
  FileText,
  Activity,
  GraduationCap,
  LogOut,
  Menu,
  X,
  User
} from 'lucide-react';
import NotificationBell from './NotificationBell';

const SaoLayout = ({ children }) => {
  const { user, logout } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const navLinks = [
    {
      label: 'Dashboard',
      path: '/sao',
      icon: LayoutDashboard,
      exact: true
    },
    {
      label: 'Assigned Concerns',
      path: '/sao/assigned-concerns',
      icon: ShieldCheck,
      matchPrefixes: ['/sao/assigned-concerns', '/sao/concerns']
    },
    {
      label: 'User Management',
      path: '/sao/users',
      icon: Users,
      matchPrefixes: ['/sao/users']
    },
    {
      label: 'Concern Oversight',
      path: '/sao/oversight',
      icon: FileText,
      matchPrefixes: ['/sao/oversight', '/sao/recurring-incidents']
    },
    {
      label: 'Analytics & Sentiment',
      path: '/analytics',
      icon: Activity,
      matchPrefixes: ['/analytics']
    }
  ];

  const isActive = (link) => {
    if (link.exact) return location.pathname === link.path;
    if (link.matchPrefixes) {
      return link.matchPrefixes.some((prefix) => location.pathname.startsWith(prefix));
    }
    return location.pathname.startsWith(link.path);
  };

  const initials = (user?.name || 'SA')
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);

  return (
    <div className="min-h-screen bg-[#f8fafc] flex">
      {/* Desktop Sidebar */}
      <aside
        aria-label="SAO Sidebar Navigation"
        className="w-64 bg-white border-r border-slate-200 flex-col hidden md:flex shrink-0 sticky top-0 h-screen"
      >
        <div className="p-6 border-b border-slate-100">
          <Link to="/sao" className="flex items-center gap-3">
            <div className="w-9 h-9 bg-[#1e3a8a] rounded-lg flex items-center justify-center text-white shadow-xs shrink-0">
              <GraduationCap size={20} />
            </div>
            <div>
              <h1 className="font-bold text-slate-900 text-base leading-tight tracking-tight">SCMS</h1>
              <p className="text-[11px] font-semibold text-blue-600 uppercase tracking-wider">SAO Oversight</p>
            </div>
          </Link>
        </div>

        <nav className="flex-1 px-3 py-4 overflow-y-auto">
          <div className="px-3 mb-2 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
            Navigation
          </div>
          <ul className="space-y-1">
            {navLinks.map((link) => {
              const Icon = link.icon;
              const active = isActive(link);
              return (
                <li key={link.path}>
                  <Link
                    to={link.path}
                    className={`flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium text-sm transition-colors ${
                      active
                        ? 'bg-blue-50 text-[#1e3a8a] border-l-4 border-[#1e3a8a]'
                        : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 border-l-4 border-transparent'
                    }`}
                  >
                    <Icon size={18} className={active ? 'text-[#1e3a8a]' : 'text-slate-400'} />
                    <span>{link.label}</span>
                  </Link>
                </li>
              );
            })}
          </ul>
        </nav>

        <div className="p-4 border-t border-slate-200">
          <div className="flex items-center gap-3 px-2 py-2 mb-2">
            <div className="w-9 h-9 rounded-full bg-blue-50 border border-blue-200 flex items-center justify-center text-[#1e3a8a] font-bold text-xs shrink-0">
              {initials}
            </div>
            <div className="overflow-hidden flex-1">
              <p className="text-sm font-semibold text-slate-900 truncate">{user?.name || 'SAO Officer'}</p>
              <p className="text-xs text-slate-500 truncate">Student Affairs Office</p>
            </div>
          </div>
          <button
            onClick={handleLogout}
            className="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs font-semibold text-slate-600 hover:text-red-600 hover:bg-red-50 border border-slate-200 hover:border-red-200 transition-colors cursor-pointer"
          >
            <LogOut size={14} />
            <span>Sign Out</span>
          </button>
        </div>
      </aside>

      {/* Mobile Drawer Backdrop */}
      {mobileMenuOpen && (
        <div
          className="fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-40 md:hidden"
          onClick={() => setMobileMenuOpen(false)}
        />
      )}

      {/* Mobile Drawer */}
      {mobileMenuOpen && (
        <aside
          aria-label="SAO Mobile Sidebar Navigation"
          className="fixed inset-y-0 left-0 w-64 bg-white border-r border-slate-200 z-50 flex flex-col md:hidden shadow-xl"
        >
          <div className="p-5 border-b border-slate-100 flex items-center justify-between">
            <Link to="/sao" onClick={() => setMobileMenuOpen(false)} className="flex items-center gap-2.5">
              <div className="w-8 h-8 bg-[#1e3a8a] rounded-lg flex items-center justify-center text-white">
                <GraduationCap size={18} />
              </div>
              <div>
                <h1 className="font-bold text-slate-900 text-sm leading-tight">SCMS</h1>
                <p className="text-[10px] font-semibold text-blue-600 uppercase tracking-wider">SAO Oversight</p>
              </div>
            </Link>
            <button
              onClick={() => setMobileMenuOpen(false)}
              aria-label="Close navigation menu"
              className="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
            >
              <X size={20} />
            </button>
          </div>
          <nav className="flex-1 px-3 py-4 overflow-y-auto">
            <div className="px-3 mb-2 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
              Navigation
            </div>
            <ul className="space-y-1">
              {navLinks.map((link) => {
                const Icon = link.icon;
                const active = isActive(link);
                return (
                  <li key={link.path}>
                    <Link
                      to={link.path}
                      onClick={() => setMobileMenuOpen(false)}
                      className={`flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium text-sm transition-colors ${
                        active
                          ? 'bg-blue-50 text-[#1e3a8a] border-l-4 border-[#1e3a8a]'
                          : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 border-l-4 border-transparent'
                      }`}
                    >
                      <Icon size={18} className={active ? 'text-[#1e3a8a]' : 'text-slate-400'} />
                      <span>{link.label}</span>
                    </Link>
                  </li>
                );
              })}
            </ul>
          </nav>
          <div className="p-4 border-t border-slate-200 space-y-2">
            <div className="flex items-center gap-3 px-2 py-1.5">
              <div className="w-8 h-8 rounded-full bg-blue-50 border border-blue-200 flex items-center justify-center text-[#1e3a8a] font-bold text-xs shrink-0">
                {initials}
              </div>
              <div className="overflow-hidden flex-1">
                <p className="text-xs font-semibold text-slate-900 truncate">{user?.name || 'SAO Officer'}</p>
                <p className="text-[11px] text-slate-500 truncate">SAO</p>
              </div>
            </div>
            <Link
              to="/profile"
              onClick={() => setMobileMenuOpen(false)}
              className="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50 border border-slate-200"
            >
              <User size={14} />
              <span>Profile</span>
            </Link>
            <button
              onClick={handleLogout}
              className="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs font-semibold text-red-600 hover:bg-red-50 border border-red-200"
            >
              <LogOut size={14} />
              <span>Sign Out</span>
            </button>
          </div>
        </aside>
      )}

      {/* Main Content */}
      <main className="flex-1 flex flex-col min-w-0 overflow-x-hidden">
        {/* Simplified Top Header */}
        <header className="h-16 border-b border-slate-200 flex items-center justify-between gap-2 px-4 sm:px-6 bg-white sticky top-0 z-30 shadow-2xs">
          <div className="flex items-center gap-2.5 min-w-0">
            <button
              onClick={() => setMobileMenuOpen(true)}
              aria-label="Open navigation menu"
              className="md:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100 shrink-0"
            >
              <Menu size={20} />
            </button>
            <Link to="/sao" className="flex items-center gap-2 min-w-0">
              <div className="w-8 h-8 bg-[#1e3a8a] rounded-lg flex items-center justify-center text-white shadow-xs md:hidden shrink-0">
                <GraduationCap size={17} />
              </div>
              <div className="flex items-center gap-2 min-w-0">
                <span className="text-sm font-bold text-slate-900 tracking-tight shrink-0">SCMS</span>
                <span className="text-slate-300 shrink-0">•</span>
                <span className="text-xs sm:text-sm font-semibold text-[#1e3a8a] truncate">
                  SAO Oversight
                </span>
                <span className="hidden xl:inline-block text-xs text-slate-500 font-medium truncate">
                  — Student Affairs Office
                </span>
              </div>
            </Link>
          </div>

          <div className="flex items-center gap-2 sm:gap-2.5 shrink-0">
            <NotificationBell />

            <div className="h-6 w-px bg-slate-200 hidden sm:block" />

            {/* SAO Profile Avatar + Account Name & Role */}
            <div className="hidden sm:flex items-center gap-2">
              <div className="w-8 h-8 rounded-full bg-blue-50 border border-blue-200 flex items-center justify-center text-[#1e3a8a] font-bold text-xs shrink-0">
                {initials}
              </div>
              <div className="hidden lg:flex flex-col text-right leading-tight">
                <span className="text-xs font-semibold text-slate-900 truncate max-w-[140px]">
                  {user?.name || 'SAO Officer'}
                </span>
                <span className="text-[11px] text-slate-500 font-medium uppercase tracking-wider">
                  SAO
                </span>
              </div>
            </div>

            {/* Profile Button */}
            <Link
              to="/profile"
              className={`flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1.5 rounded-lg border transition-colors shrink-0 ${
                location.pathname === '/profile'
                  ? 'bg-blue-50 text-[#1e3a8a] border-blue-200'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 border-slate-200'
              }`}
              title="Account Profile"
            >
              <User size={14} />
              <span className="hidden sm:inline">Profile</span>
            </Link>
          </div>
        </header>

        {/* Page Content */}
        <div className="flex-1 overflow-auto bg-[#f8fafc] p-4 sm:p-6 lg:p-8">
          <div className="max-w-7xl mx-auto">
            {children || <Outlet />}
          </div>
        </div>

        <footer className="bg-white border-t border-slate-200 py-4 px-6">
          <div className="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-slate-400">
            <span>&copy; {new Date().getFullYear()} SCMS — Student Affairs Office Oversight</span>
            <span>Confidentiality &bull; Accountability &bull; Timely Resolution</span>
          </div>
        </footer>
      </main>
    </div>
  );
};

export default SaoLayout;
