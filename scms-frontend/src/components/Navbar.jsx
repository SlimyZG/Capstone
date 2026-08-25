import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

const Navbar = () => {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const getDashboardLink = () => {
    if (!user) return '/login';
    switch (user.role) {
      case 'student': return '/student';
      case 'admin': return '/admin';
      case 'dean': return '/dean';
      case 'dept_staff': return '/dept-staff';
      default: return '/';
    }
  };

  return (
    <nav className="bg-white border-b border-slate-200 sticky top-0 z-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between h-16">
          <div className="flex">
            <div className="flex-shrink-0 flex items-center">
              <Link to={getDashboardLink()} className="text-xl font-bold text-blue-600">SCMS</Link>
            </div>
            {user && (
              <div className="hidden sm:ml-6 sm:flex sm:space-x-8">
                <Link to={getDashboardLink()} className="border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                  Dashboard
                </Link>
                {user.role === 'student' && (
                  <>
                    <Link to="/student/submit" className="border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                      Submit Concern
                    </Link>
                    <Link to="/student/concerns" className="border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                      My Concerns
                    </Link>
                  </>
                )}
                {['admin', 'dean'].includes(user.role) && (
                  <Link to="/analytics" className="border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                    Analytics & Sentiment
                  </Link>
                )}
              </div>
            )}
          </div>
          <div className="flex items-center">
            {user ? (
              <div className="flex items-center space-x-4">
                <div className="text-sm">
                  <span className="font-medium text-slate-900">{user.name}</span>
                  <span className="ml-2 text-xs text-slate-500 uppercase px-2 py-1 bg-slate-100 rounded-full">{user.role}</span>
                </div>
                <Link to="/profile" className="text-sm font-medium text-blue-600 hover:text-blue-800">
                  Profile
                </Link>
                <button onClick={handleLogout} className="text-sm font-medium text-red-600 hover:text-red-500">
                  Logout
                </button>
              </div>
            ) : (
              <div className="space-x-4">
                <Link to="/login" className="text-sm font-medium text-slate-500 hover:text-slate-900">Login</Link>
                <Link to="/register" className="btn-primary py-1.5 text-sm">Register</Link>
              </div>
            )}
          </div>
        </div>
      </div>
    </nav>
  );
};

export default Navbar;
