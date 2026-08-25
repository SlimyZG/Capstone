import { Navigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

const ProtectedRoute = ({ children, allowedRoles }) => {
  const { user, loading } = useAuth();

  if (loading) {
    return <div className="flex h-screen items-center justify-center">Loading...</div>;
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (allowedRoles && !allowedRoles.includes(user.role)) {
    // Redirect based on role if unauthorized
    if (user.role === 'student') return <Navigate to="/student" replace />;
    if (user.role === 'admin') return <Navigate to="/admin" replace />;
    if (user.role === 'dean') return <Navigate to="/dean" replace />;
    if (user.role === 'dept_staff') return <Navigate to="/dept-staff" replace />;
  }

  return children;
};

export default ProtectedRoute;
