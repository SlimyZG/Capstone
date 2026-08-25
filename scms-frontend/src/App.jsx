import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import Layout from './components/Layout';
import StudentLayout from './components/StudentLayout';
import DeptStaffLayout from './components/DeptStaffLayout';
import DeanLayout from './components/DeanLayout';
import ProtectedRoute from './components/ProtectedRoute';

// Pages
import LoginPage from './pages/LoginPage';
import RegisterPage from './pages/RegisterPage';
import StudentDashboard from './pages/StudentDashboard';
import SubmitConcern from './pages/SubmitConcern';
import DeptStaffDashboard from './pages/DeptStaffDashboard';
import DeanDashboard from './pages/DeanDashboard';
import AdminDashboard from './pages/AdminDashboard';
import SentimentDashboard from './pages/SentimentDashboard';
import ProfilePage from './pages/ProfilePage';
import StudentConcernsList from './pages/StudentConcernsList';
import StudentConcernDetails from './pages/StudentConcernDetails';
import DeptStaffConcernsList from './pages/DeptStaffConcernsList';
import DeptStaffConcernDetails from './pages/DeptStaffConcernDetails';
import DeanEscalatedConcerns from './pages/DeanEscalatedConcerns';
import DeanClassList from './pages/DeanClassList';
import DeanReports from './pages/DeanReports';

const HomeRedirect = () => {
  const { user, loading } = useAuth();
  if (loading) return <div>Loading...</div>;
  if (!user) return <Navigate to="/login" replace />;
  switch (user.role) {
    case 'student': return <Navigate to="/student" replace />;
    case 'admin': return <Navigate to="/admin" replace />;
    case 'dean': return <Navigate to="/dean" replace />;
    case 'dept_staff': return <Navigate to="/dept-staff" replace />;
    default: return <Navigate to="/login" replace />;
  }
};

function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />
          
          {/* Staff Routes (Top Navbar) - Admin only */}
          <Route element={<Layout />}>
            <Route path="/" element={<HomeRedirect />} />
            {/* Admin Routes */}
            <Route path="/admin" element={<ProtectedRoute allowedRoles={['admin']}><AdminDashboard /></ProtectedRoute>} />
            <Route path="/profile" element={<ProtectedRoute allowedRoles={['admin']}><ProfilePage /></ProtectedRoute>} />
          </Route>

          {/* Dean Routes (Sidebar Layout) */}
          <Route element={<DeanLayout />}>
            <Route path="/dean" element={<ProtectedRoute allowedRoles={['dean']}><DeanDashboard /></ProtectedRoute>} />
            <Route path="/dean/escalated" element={<ProtectedRoute allowedRoles={['dean']}><DeanEscalatedConcerns /></ProtectedRoute>} />
            <Route path="/dean/class-list" element={<ProtectedRoute allowedRoles={['dean']}><DeanClassList /></ProtectedRoute>} />
            <Route path="/dean/reports" element={<ProtectedRoute allowedRoles={['dean']}><DeanReports /></ProtectedRoute>} />
            <Route path="/analytics" element={<ProtectedRoute allowedRoles={['admin', 'dean']}><SentimentDashboard /></ProtectedRoute>} />
            <Route path="/dean/profile" element={<ProtectedRoute allowedRoles={['dean']}><ProfilePage /></ProtectedRoute>} />
          </Route>

          {/* Dept Staff Routes (Sidebar Layout) */}
          <Route element={<DeptStaffLayout />}>
            <Route path="/dept-staff" element={<ProtectedRoute allowedRoles={['dept_staff']}><DeptStaffDashboard /></ProtectedRoute>} />
            <Route path="/dept-staff/concerns" element={<ProtectedRoute allowedRoles={['dept_staff']}><DeptStaffConcernsList /></ProtectedRoute>} />
            <Route path="/dept-staff/concerns/:id" element={<ProtectedRoute allowedRoles={['dept_staff']}><DeptStaffConcernDetails /></ProtectedRoute>} />
            <Route path="/dept-staff/profile" element={<ProtectedRoute allowedRoles={['dept_staff']}><ProfilePage /></ProtectedRoute>} />
          </Route>

          {/* Student Routes (Sidebar Layout) */}
          <Route element={<StudentLayout />}>
            <Route path="/student" element={<ProtectedRoute allowedRoles={['student']}><StudentDashboard /></ProtectedRoute>} />
            <Route path="/student/submit" element={<ProtectedRoute allowedRoles={['student']}><SubmitConcern /></ProtectedRoute>} />
            <Route path="/student/concerns" element={<ProtectedRoute allowedRoles={['student']}><StudentConcernsList /></ProtectedRoute>} />
            <Route path="/student/concerns/:id" element={<ProtectedRoute allowedRoles={['student']}><StudentConcernDetails /></ProtectedRoute>} />
            <Route path="/student/profile" element={<ProtectedRoute allowedRoles={['student']}><ProfilePage /></ProtectedRoute>} />
          </Route>
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}

export default App;
