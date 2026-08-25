import { Link, useLocation, useNavigate, Outlet } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { LayoutDashboard, List, AlertTriangle, BarChart2, LogOut, Bell, User } from 'lucide-react';

const DeanLayout = () => {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const navItems = [
    { name: 'Dashboard', path: '/dean', icon: LayoutDashboard },
    { name: 'Class List', path: '/dean/class-list', icon: List },
    { name: 'Escalated Concerns', path: '/dean/escalated', icon: AlertTriangle },
    { name: 'Reports', path: '/dean/reports', icon: BarChart2 },
  ];

  const initials = user?.name?.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) || 'D';

  return (
    <div className="min-h-screen bg-[#f5f6fa] flex">
      {/* Sidebar */}
      <aside className="w-56 bg-white border-r border-slate-200 flex flex-col hidden md:flex">
        <div className="p-5 pb-4">
          <div className="flex items-center gap-3 mb-1">
            <div className="h-9 w-9 rounded-full bg-[#1e3a8a] text-white flex items-center justify-center font-bold text-sm flex-shrink-0">
              {initials}
            </div>
            <div>
              <h1 className="text-base font-bold text-[#1e3a8a] leading-tight">Academic Affairs</h1>
              <p className="text-[10px] text-slate-500 leading-tight">Dean's Office</p>
            </div>
          </div>
        </div>

        <nav className="flex-1 px-3 mt-2">
          <ul className="space-y-0.5">
            {navItems.map((item) => {
              const Icon = item.icon;
              const isActive = location.pathname === item.path;
              return (
                <li key={item.name}>
                  <Link
                    to={item.path}
                    className={`flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors ${
                      isActive
                        ? 'bg-[#1e3a8a] text-white'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                    }`}
                  >
                    <Icon size={16} className={`mr-2.5 flex-shrink-0 ${isActive ? 'text-white' : 'text-slate-400'}`} />
                    {item.name}
                  </Link>
                </li>
              );
            })}
          </ul>
        </nav>

        <div className="p-4 border-t border-slate-200">
          <button
            onClick={handleLogout}
            className="flex items-center w-full px-3 py-2.5 text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-50 rounded-lg transition-colors"
          >
            <LogOut size={16} className="mr-2.5 text-slate-400" />
            Logout
          </button>
        </div>
      </aside>

      {/* Main Content */}
      <main className="flex-1 flex flex-col min-w-0">
        {/* Top Header */}
        <header className="h-14 border-b border-slate-200 flex items-center justify-end px-6 bg-white gap-4">
          <button className="text-slate-400 hover:text-slate-600">
            <Bell size={20} />
          </button>
          <button className="text-slate-400 hover:text-slate-600">
            <User size={20} />
          </button>
        </header>

        {/* Page Content */}
        <div className="flex-1 overflow-auto bg-[#f5f6fa] p-6 lg:p-8">
          <div className="max-w-5xl mx-auto">
            <Outlet />
          </div>
        </div>
      </main>
    </div>
  );
};

export default DeanLayout;
