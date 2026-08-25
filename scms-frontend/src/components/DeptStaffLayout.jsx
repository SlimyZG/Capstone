import { Link, useLocation, useNavigate, Outlet } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { LayoutDashboard, AlertTriangle, User, LogOut, Settings, Bell, HelpCircle } from 'lucide-react';

const DeptStaffLayout = () => {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const navItems = [
    { name: 'Dashboard', path: '/dept-staff', icon: LayoutDashboard },
    { name: 'Concerns', path: '/dept-staff/concerns', icon: AlertTriangle },
    { name: 'Profile', path: '/dept-staff/profile', icon: User },
  ];

  return (
    <div className="min-h-screen bg-[#f8fafc] flex">
      {/* Sidebar */}
      <aside className="w-64 bg-white border-r border-slate-200 flex flex-col hidden md:flex">
        <div className="p-6">
          <div className="flex items-center mb-1">
             <div className="h-8 w-8 mr-3 bg-[#1e3a8a] rounded-lg flex items-center justify-center shadow-sm">
                <img src="/logo.png" alt="Logo" className="h-5 w-5 invert" />
             </div>
             <h1 className="text-xl font-bold text-[#1e3a8a]">Head Portal</h1>
          </div>
          <p className="text-xs text-slate-500 ml-11">Department Head</p>
        </div>

        <div className="px-4 mt-2 mb-6">
           <Link to="/dept-staff/concerns" className="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-[#1e3a8a] hover:bg-[#152960] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
              <span className="mr-2 text-lg leading-none font-light">+</span> New Report
           </Link>
        </div>

        <nav className="flex-1">
          <ul className="space-y-1">
            {navItems.map((item) => {
              const Icon = item.icon;
              const isActive = location.pathname === item.path || (item.path === '/dept-staff/concerns' && location.pathname.startsWith('/dept-staff/concerns/'));
              
              return (
                <li key={item.name} className="px-3">
                  <Link
                    to={item.path}
                    className={`flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors ${
                      isActive 
                        ? 'bg-[#e2e8f0] text-[#1e3a8a] border-l-4 border-[#1e3a8a]' 
                        : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 border-l-4 border-transparent'
                    }`}
                  >
                    <Icon size={18} className={`mr-3 ${isActive ? 'text-[#1e3a8a]' : 'text-slate-400'}`} />
                    {item.name}
                  </Link>
                </li>
              );
            })}
          </ul>
        </nav>

        <div className="p-4 border-t border-slate-200 space-y-1">
          <button 
            className="flex items-center w-full px-3 py-2 text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-50 rounded-lg transition-colors"
          >
            <Settings size={18} className="mr-3 text-slate-400" />
            Settings
          </button>
          <button 
            onClick={handleLogout}
            className="flex items-center w-full px-3 py-2 text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-50 rounded-lg transition-colors"
          >
            <LogOut size={18} className="mr-3 text-slate-400" />
            Logout
          </button>
        </div>
      </aside>

      {/* Main Content Area */}
      <main className="flex-1 flex flex-col min-w-0 bg-[#f8fafc]">
        {/* Top Header */}
        <header className="h-16 border-b border-slate-200 flex items-center justify-between px-6 bg-white">
          <div className="flex items-center flex-1">
             <div className="relative w-full max-w-md hidden sm:block">
                 <SearchIcon className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 h-4 w-4" />
                 <input type="text" placeholder="Search portal..." className="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-full text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-slate-900 placeholder:text-slate-400" />
             </div>
          </div>
          <div className="flex items-center space-x-4">
            <button className="text-slate-400 hover:text-slate-600">
              <HelpCircle size={20} />
            </button>
            <button className="text-slate-400 hover:text-slate-600 relative">
              <Bell size={20} />
              <span className="absolute top-0 right-0 block h-2 w-2 rounded-full bg-red-500 ring-2 ring-white"></span>
            </button>
            <div className="h-8 w-8 rounded-full bg-slate-200 overflow-hidden border border-slate-300 flex items-center justify-center ml-2">
              <div className="w-full h-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xs">
                {user?.name?.charAt(0) || 'H'}
              </div>
            </div>
          </div>
        </header>

        {/* Page Content */}
        <div className="flex-1 overflow-auto p-6 lg:p-8">
          <div className="max-w-6xl mx-auto">
            <Outlet />
          </div>
        </div>
      </main>
    </div>
  );
};

// Simple search icon component
const SearchIcon = (props) => (
  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}>
    <circle cx="11" cy="11" r="8"></circle>
    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
  </svg>
);

export default DeptStaffLayout;
