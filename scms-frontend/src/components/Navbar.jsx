import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import NotificationBell from './NotificationBell';
import { 
  ShieldCheck, 
  Users, 
  FileText, 
  Activity, 
  Menu, 
  X, 
  LogOut, 
  User, 
  Layers, 
  GraduationCap 
} from 'lucide-react';

const Navbar = () => {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const getDashboardLink = () => {
    if (!user) return '/login';
    switch (user.role) {
      case 'student': return '/student';
      case 'admin': return '/admin';
      case 'sao': return '/sao';
      case 'dean': return '/dean';
      case 'dept_staff': return '/dept-staff';
      default: return '/';
    }
  };

  const initials = user?.name?.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) || 'U';

  const navLinks = [];
  if (user) {
    if (user.role === 'sao') {
      navLinks.push(
        { name: 'Dashboard', path: '/sao', icon: Layers },
        { name: 'Assigned Concerns', path: '/sao/assigned-concerns', icon: ShieldCheck },
        { name: 'User Management', path: '/sao/users', icon: Users },
        { name: 'Concern Oversight', path: '/sao/oversight', icon: FileText }
      );
    }
    if (user.role === 'admin') {
      navLinks.push(
        { name: 'User Administration', path: '/admin', icon: Users }
      );
    }
    if (['admin', 'dean', 'sao', 'dept_staff'].includes(user.role)) {
      navLinks.push(
        { name: 'Analytics & Sentiment', path: '/analytics', icon: Activity }
      );
    }
  }

  return (
    <nav className="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-2xs">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between h-16">
          {/* Left: Brand & Desktop Nav Links */}
          <div className="flex items-center">
            <Link to={getDashboardLink()} className="flex items-center gap-2.5 shrink-0 mr-8">
              <div className="h-9 w-9 bg-[#1e3a8a] text-white rounded-lg flex items-center justify-center font-bold text-sm shadow-xs">
                <GraduationCap size={20} />
              </div>
              <div className="leading-tight">
                <span className="text-lg font-black text-[#1e3a8a] tracking-tight">SCMS</span>
                <span className="hidden sm:inline-block ml-1.5 text-xs text-slate-500 font-medium">
                  {user?.role === 'sao' ? 'SAO Oversight' : 'Administration'}
                </span>
              </div>
            </Link>

            {/* Desktop Nav Links */}
            <div className="hidden lg:flex lg:space-x-1">
              {navLinks.map((link) => {
                const Icon = link.icon;
                const isActive = location.pathname === link.path || 
                  (link.path === '/sao/assigned-concerns' && location.pathname.startsWith('/sao/concerns/'));

                return (
                  <Link
                    key={link.path}
                    to={link.path}
                    className={`inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-colors ${
                      isActive
                        ? 'bg-blue-50 text-[#1e3a8a]'
                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                    }`}
                  >
                    <Icon size={16} className={isActive ? 'text-[#1e3a8a]' : 'text-slate-400'} />
                    {link.name}
                  </Link>
                );
              })}
            </div>
          </div>

          {/* Right: Notification Bell, Profile, Sign Out */}
          <div className="flex items-center gap-3">
            {user ? (
              <>
                <NotificationBell />

                <div className="hidden sm:flex items-center gap-2.5 pl-2 border-l border-slate-200">
                  <div className="h-8 w-8 rounded-full bg-blue-100 text-[#1e3a8a] flex items-center justify-center font-bold text-xs shrink-0">
                    {initials}
                  </div>
                  <div className="min-w-0 text-left">
                    <p className="text-xs font-bold text-slate-900 truncate max-w-[120px]">{user.name}</p>
                    <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider block">
                      {user.role}
                    </span>
                  </div>
                </div>

                <Link
                  to="/profile"
                  className="hidden sm:inline-flex items-center px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors shadow-2xs"
                >
                  <User size={13} className="mr-1.5 text-slate-400" />
                  Profile
                </Link>

                <button
                  onClick={handleLogout}
                  className="hidden sm:inline-flex items-center px-3 py-1.5 text-xs font-semibold text-red-600 bg-red-50/60 border border-red-200/60 rounded-lg hover:bg-red-100/80 transition-colors"
                >
                  <LogOut size={13} className="mr-1.5 text-red-500" />
                  Sign Out
                </button>

                {/* Mobile Menu Toggle Button */}
                <button
                  onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                  className="p-2 text-slate-600 hover:text-slate-900 lg:hidden rounded-lg hover:bg-slate-100 transition-colors"
                  aria-label="Toggle navigation"
                >
                  {mobileMenuOpen ? <X size={20} /> : <Menu size={20} />}
                </button>
              </>
            ) : (
              <div className="space-x-3">
                <Link to="/login" className="text-sm font-semibold text-slate-600 hover:text-slate-900">
                  Sign In
                </Link>
                <Link to="/register" className="btn-primary py-1.5 text-xs">
                  Register
                </Link>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Mobile Menu Dropdown */}
      {mobileMenuOpen && user && (
        <div className="lg:hidden border-t border-slate-200 bg-white px-4 pt-3 pb-4 space-y-1 animate-in fade-in slide-in-from-top-2 duration-150">
          <div className="pb-3 mb-2 border-b border-slate-100 flex items-center gap-3">
            <div className="h-9 w-9 rounded-full bg-blue-100 text-[#1e3a8a] flex items-center justify-center font-bold text-xs shrink-0">
              {initials}
            </div>
            <div>
              <p className="text-sm font-bold text-slate-900">{user.name}</p>
              <p className="text-xs text-slate-500 font-medium uppercase tracking-wider">{user.role}</p>
            </div>
          </div>

          {navLinks.map((link) => {
            const Icon = link.icon;
            const isActive = location.pathname === link.path;
            return (
              <Link
                key={link.path}
                to={link.path}
                onClick={() => setMobileMenuOpen(false)}
                className={`flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors ${
                  isActive
                    ? 'bg-blue-50 text-[#1e3a8a]'
                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                }`}
              >
                <Icon size={18} className={isActive ? 'text-[#1e3a8a]' : 'text-slate-400'} />
                {link.name}
              </Link>
            );
          })}

          <div className="pt-3 border-t border-slate-100 flex gap-2">
            <Link
              to="/profile"
              onClick={() => setMobileMenuOpen(false)}
              className="flex-1 py-2 text-center text-xs font-semibold text-slate-700 bg-slate-50 rounded-lg border border-slate-200"
            >
              Profile
            </Link>
            <button
              onClick={handleLogout}
              className="flex-1 py-2 text-center text-xs font-semibold text-red-600 bg-red-50 rounded-lg border border-red-200"
            >
              Sign Out
            </button>
          </div>
        </div>
      )}
    </nav>
  );
};

export default Navbar;
