import { useState } from 'react';
import { Link, useLocation, useNavigate, Outlet } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import NotificationBell from './NotificationBell';
import { 
  LayoutDashboard, 
  AlertTriangle, 
  User, 
  LogOut, 
  BarChart2, 
  Activity, 
  Menu, 
  X, 
  Briefcase 
} from 'lucide-react';

const DeptStaffLayout = ({ children }) => {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const navItems = [
    { name: 'Dashboard',           path: '/dept-staff',          icon: LayoutDashboard },
    { name: 'Concern Queue',       path: '/dept-staff/concerns', icon: AlertTriangle },
    { name: 'Reports',             path: '/dept-staff/reports',  icon: BarChart2 },
    { name: 'Sentiment Analytics', path: '/analytics',           icon: Activity },
    { name: 'Profile',             path: '/dept-staff/profile',  icon: User },
  ];

  const initials = user?.name?.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) || 'S';

  const renderNavLinks = (onItemClick) => (
    <ul className="space-y-1">
      {navItems.map((item) => {
        const Icon = item.icon;
        const isActive = location.pathname === item.path || 
          (item.path === '/dept-staff/concerns' && location.pathname.startsWith('/dept-staff/concerns/'));

        return (
          <li key={item.name}>
            <Link
              to={item.path}
              onClick={onItemClick}
              className={`flex items-center px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors ${
                isActive
                  ? 'bg-blue-50 text-[#1e3a8a] border-l-4 border-[#1e3a8a]'
                  : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 border-l-4 border-transparent'
              }`}
            >
              <Icon size={18} className={`mr-3 shrink-0 ${isActive ? 'text-[#1e3a8a]' : 'text-slate-400'}`} />
              {item.name}
            </Link>
          </li>
        );
      })}
    </ul>
  );

  return (
    <div className="min-h-screen bg-[#f8fafc] flex">
      {/* Desktop Sidebar */}
      <aside className="w-64 bg-white border-r border-slate-200 flex-col hidden md:flex shrink-0">
        <div className="p-6 border-b border-slate-100">
          <div className="flex items-center gap-3">
            <div className="h-10 w-10 bg-[#1e3a8a] text-white rounded-lg flex items-center justify-center font-bold text-sm shrink-0 shadow-xs">
              <Briefcase size={20} />
            </div>
            <div className="min-w-0">
              <h1 className="text-base font-bold text-[#1e3a8a] truncate leading-tight">Department Portal</h1>
              <p className="text-xs text-slate-500 font-medium">Operations & Academic</p>
            </div>
          </div>
        </div>

        <nav className="flex-1 px-3 py-4">
          {renderNavLinks()}
        </nav>

        <div className="p-4 border-t border-slate-200">
          <div className="flex items-center gap-3 px-3 py-2 mb-2">
            <div className="h-8 w-8 rounded-full bg-blue-100 text-[#1e3a8a] flex items-center justify-center font-bold text-xs shrink-0">
              {initials}
            </div>
            <div className="min-w-0 flex-1">
              <p className="text-xs font-bold text-slate-900 truncate">{user?.name || 'Staff'}</p>
              <p className="text-[11px] text-slate-500 truncate">Department Staff</p>
            </div>
          </div>
          <button
            onClick={handleLogout}
            className="flex items-center w-full px-3 py-2 text-sm font-semibold text-slate-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
          >
            <LogOut size={16} className="mr-3 text-slate-400" />
            Sign Out
          </button>
        </div>
      </aside>

      {/* Mobile Drawer Backdrop */}
      {mobileMenuOpen && (
        <div
          className="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-40 md:hidden animate-in fade-in duration-200"
          onClick={() => setMobileMenuOpen(false)}
        />
      )}

      {/* Mobile Drawer Sidebar */}
      <aside
        className={`fixed inset-y-0 left-0 w-64 bg-white border-r border-slate-200 z-50 flex flex-col md:hidden transform transition-transform duration-200 ease-in-out ${
          mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="p-5 flex items-center justify-between border-b border-slate-100">
          <div className="flex items-center gap-2.5">
            <div className="h-9 w-9 rounded-lg bg-[#1e3a8a] text-white flex items-center justify-center font-bold text-sm shrink-0">
              <Briefcase size={18} />
            </div>
            <div>
              <h1 className="text-base font-bold text-[#1e3a8a] leading-tight">Department Portal</h1>
              <p className="text-xs text-slate-500">Staff Management</p>
            </div>
          </div>
          <button
            onClick={() => setMobileMenuOpen(false)}
            className="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100"
            aria-label="Close navigation"
          >
            <X size={20} />
          </button>
        </div>

        <nav className="flex-1 px-3 py-4">
          {renderNavLinks(() => setMobileMenuOpen(false))}
        </nav>

        <div className="p-4 border-t border-slate-200">
          <button
            onClick={handleLogout}
            className="flex items-center w-full px-3 py-2 text-sm font-semibold text-slate-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
          >
            <LogOut size={16} className="mr-3 text-slate-400" />
            Sign Out
          </button>
        </div>
      </aside>

      {/* Main Content Area */}
      <main className="flex-1 flex flex-col min-w-0">
        {/* Top Header */}
        <header className="h-16 border-b border-slate-200 flex items-center justify-between px-4 sm:px-6 bg-white sticky top-0 z-30 shadow-2xs">
          <div className="flex items-center gap-3">
            <button
              onClick={() => setMobileMenuOpen(true)}
              className="p-2 text-slate-600 hover:text-slate-900 md:hidden rounded-lg hover:bg-slate-100"
              aria-label="Open navigation"
            >
              <Menu size={20} />
            </button>
            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider hidden sm:inline-block">
              Department Operations & Incident Queue
            </span>
          </div>

          <div className="flex items-center gap-3">
            <NotificationBell />
            <Link
              to="/dept-staff/profile"
              className="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-100 transition-colors"
              title="Department Staff Profile"
            >
              <div className="h-8 w-8 rounded-full bg-blue-100 text-[#1e3a8a] flex items-center justify-center font-bold text-xs">
                {initials}
              </div>
              <span className="text-xs font-bold text-slate-700 hidden sm:inline-block">
                {user?.name?.split(' ')[0] || 'Staff'}
              </span>
            </Link>
          </div>
        </header>

        {/* Page Content */}
        <div className="flex-1 overflow-auto bg-[#f8fafc] p-4 sm:p-6 lg:p-8">
          <div className="max-w-7xl mx-auto">
            {children || <Outlet />}
          </div>
        </div>
      </main>
    </div>
  );
};

export default DeptStaffLayout;
