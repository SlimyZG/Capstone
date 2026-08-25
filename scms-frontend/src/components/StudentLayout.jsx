import { Link, useLocation, useNavigate, Outlet } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { LayoutDashboard, PlusSquare, ListTodo, User, LogOut, Bell, HelpCircle } from 'lucide-react';

const StudentLayout = () => {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const navItems = [
    { name: 'Dashboard', path: '/student', icon: LayoutDashboard },
    { name: 'Submit Concern', path: '/student/submit', icon: PlusSquare },
    { name: 'My Concerns', path: '/student/concerns', icon: ListTodo },
    { name: 'Profile', path: '/student/profile', icon: User },
  ];

  return (
    <div className="min-h-screen bg-[#f8fafc] flex">
      {/* Sidebar */}
      <aside className="w-64 bg-[#f8fafc] border-r border-slate-200 flex flex-col hidden md:flex">
        <div className="p-6">
          <h1 className="text-xl font-bold text-[#1e3a8a]">Student Portal</h1>
          <p className="text-xs text-slate-500 mt-1">Academic Year 2023-2024</p>
        </div>

        <nav className="flex-1 mt-6">
          <ul className="space-y-1">
            {navItems.map((item) => {
              const Icon = item.icon;
              const isActive = location.pathname === item.path || (item.path === '/student/concerns' && location.pathname.startsWith('/student/concerns/'));
              
              return (
                <li key={item.name} className="px-3">
                  <Link
                    to={item.path}
                    className={`flex items-center px-3 py-2.5 rounded-md text-sm font-medium transition-colors ${
                      isActive 
                        ? 'bg-[#e2e8f0] text-[#1e3a8a] border-l-4 border-[#1e3a8a]' 
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 border-l-4 border-transparent'
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

        <div className="p-4 border-t border-slate-200">
          <button 
            onClick={handleLogout}
            className="flex items-center w-full px-3 py-2 text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors"
          >
            <LogOut size={18} className="mr-3 text-slate-400" />
            Logout
          </button>
        </div>
      </aside>

      {/* Main Content Area */}
      <main className="flex-1 flex flex-col min-w-0 bg-white">
        {/* Top Header */}
        <header className="h-16 border-b border-slate-200 flex items-center justify-between px-6 bg-white">
          <div className="flex items-center">
            {/* Mobile menu button could go here */}
            <h2 className="text-lg font-bold text-slate-900 hidden sm:block">
              {/* Dynamic title based on route if needed, or just Student Support */}
              <div className="flex items-center">
                 <div className="h-6 w-6 mr-2 bg-blue-100 rounded flex items-center justify-center">
                    <img src="/logo.png" alt="Logo" className="h-4 w-4" />
                 </div>
                 Student Support
              </div>
            </h2>
          </div>
          <div className="flex items-center space-x-4">
            <button className="text-slate-400 hover:text-slate-600">
              <Bell size={20} />
            </button>
            <button className="text-slate-400 hover:text-slate-600">
              <HelpCircle size={20} />
            </button>
            <div className="h-8 w-8 rounded-full bg-slate-200 overflow-hidden border border-slate-300 flex items-center justify-center">
              {/* Avatar placeholder */}
              <div className="w-full h-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xs">
                {user?.name?.charAt(0) || 'U'}
              </div>
            </div>
          </div>
        </header>

        {/* Page Content */}
        <div className="flex-1 overflow-auto bg-[#f8fafc] p-6">
          <div className="max-w-5xl mx-auto">
            <Outlet />
          </div>
        </div>
      </main>
    </div>
  );
};

export default StudentLayout;
