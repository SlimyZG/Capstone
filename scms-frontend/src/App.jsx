import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import Layout from './components/Layout';
import StudentLayout from './components/StudentLayout';
import DeptStaffLayout from './components/DeptStaffLayout';
import DeanLayout from './components/DeanLayout';
import SaoLayout from './components/SaoLayout';
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
import DeanConcernDetails from './pages/DeanConcernDetails';
import DeanClassList from './pages/DeanClassList';
import DeanReports from './pages/DeanReports';
import DeptStaffReports from './pages/DeptStaffReports';
import SaoDashboard from './pages/SaoDashboard';
import SaoUserManagement from './pages/SaoUserManagement';
import SaoConcernOversight from './pages/SaoConcernOversight';
import SaoAssignedConcerns from './pages/SaoAssignedConcerns';
import SaoConcernDetails from './pages/SaoConcernDetails';
import RecurringIncidentDetails from './pages/RecurringIncidentDetails';

const HomeRedirect = () => {
  const { user, loading } = useAuth();
  if (loading) return <div>Loading...</div>;
  if (!user) return <Navigate to="/login" replace />;
  switch (user.role) {
    case 'student': return <Navigate to="/student" replace />;
    case 'admin': return <Navigate to="/admin" replace />;
    case 'sao': return <Navigate to="/sao" replace />;
    case 'dean': return <Navigate to="/dean" replace />;
    case 'dept_staff': return <Navigate to="/dept-staff" replace />;
    default: return <Navigate to="/login" replace />;
  }
};

const RoleAwareAnalytics = () => {
  const { user } = useAuth();
  if (user?.role === 'sao') {
    return (
      <SaoLayout>
        <SentimentDashboard />
      </SaoLayout>
    );
  }
  if (user?.role === 'dean') {
    return (
      <DeanLayout>
        <SentimentDashboard />
      </DeanLayout>
    );
  }
  if (user?.role === 'dept_staff') {
    return (
      <DeptStaffLayout>
        <SentimentDashboard />
      </DeptStaffLayout>
    );
  }
  return (
    <Layout>
      <SentimentDashboard />
    </Layout>
  );
};

const RoleAwareProfile = () => {
  const { user } = useAuth();
  if (user?.role === 'sao') {
    return (
      <SaoLayout>
        <ProfilePage />
      </SaoLayout>
    );
  }
  return (
    <Layout>
      <ProfilePage />
    </Layout>
  );
};

function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />
          
          {/* Admin & Root Redirect Routes */}
          <Route element={<Layout />}>
            <Route path="/" element={<HomeRedirect />} />
            <Route path="/admin" element={<ProtectedRoute allowedRoles={['admin']}><AdminDashboard /></ProtectedRoute>} />
          </Route>

          {/* SAO Routes (Sidebar Layout) */}
          <Route element={<SaoLayout />}>
            <Route path="/sao" element={<ProtectedRoute allowedRoles={['sao']}><SaoDashboard /></ProtectedRoute>} />
            <Route path="/sao/assigned-concerns" element={<ProtectedRoute allowedRoles={['sao']}><SaoAssignedConcerns /></ProtectedRoute>} />
            <Route path="/sao/concerns/:id" element={<ProtectedRoute allowedRoles={['sao']}><SaoConcernDetails /></ProtectedRoute>} />
            <Route path="/sao/users" element={<ProtectedRoute allowedRoles={['sao']}><SaoUserManagement /></ProtectedRoute>} />
            <Route path="/sao/oversight" element={<ProtectedRoute allowedRoles={['sao']}><SaoConcernOversight /></ProtectedRoute>} />
            <Route path="/sao/recurring-incidents/:id" element={<ProtectedRoute allowedRoles={['sao']}><RecurringIncidentDetails /></ProtectedRoute>} />
            <Route path="/sao/profile" element={<ProtectedRoute allowedRoles={['sao']}><ProfilePage /></ProtectedRoute>} />
          </Route>

          {/* Shared /profile Route (Role-Aware Layout for SAO & Admin) */}
          <Route path="/profile" element={<ProtectedRoute allowedRoles={['admin', 'sao']}><RoleAwareProfile /></ProtectedRoute>} />

          {/* Dean Routes (Sidebar Layout) */}
          <Route element={<DeanLayout />}>
            <Route path="/dean" element={<ProtectedRoute allowedRoles={['dean']}><DeanDashboard /></ProtectedRoute>} />
            <Route path="/dean/escalated" element={<ProtectedRoute allowedRoles={['dean']}><DeanEscalatedConcerns /></ProtectedRoute>} />
            <Route path="/dean/concerns/:id" element={<ProtectedRoute allowedRoles={['dean']}><DeanConcernDetails /></ProtectedRoute>} />
            <Route path="/dean/recurring-incidents/:id" element={<ProtectedRoute allowedRoles={['dean']}><RecurringIncidentDetails /></ProtectedRoute>} />
            <Route path="/dean/class-list" element={<ProtectedRoute allowedRoles={['dean']}><DeanClassList /></ProtectedRoute>} />
            <Route path="/dean/reports" element={<ProtectedRoute allowedRoles={['dean']}><DeanReports /></ProtectedRoute>} />
            <Route path="/dean/profile" element={<ProtectedRoute allowedRoles={['dean']}><ProfilePage /></ProtectedRoute>} />
          </Route>

          {/* Unified Analytics & Sentiment Route (Role-Aware Layout) */}
          <Route path="/analytics" element={<ProtectedRoute allowedRoles={['admin', 'sao', 'dean', 'dept_staff']}><RoleAwareAnalytics /></ProtectedRoute>} />

          {/* Dept Staff Routes (Sidebar Layout) */}
          <Route element={<DeptStaffLayout />}>
            <Route path="/dept-staff" element={<ProtectedRoute allowedRoles={['dept_staff']}><DeptStaffDashboard /></ProtectedRoute>} />
            <Route path="/dept-staff/concerns" element={<ProtectedRoute allowedRoles={['dept_staff']}><DeptStaffConcernsList /></ProtectedRoute>} />
            <Route path="/dept-staff/concerns/:id" element={<ProtectedRoute allowedRoles={['dept_staff']}><DeptStaffConcernDetails /></ProtectedRoute>} />
            <Route path="/dept-staff/recurring-incidents/:id" element={<ProtectedRoute allowedRoles={['dept_staff']}><RecurringIncidentDetails /></ProtectedRoute>} />
            <Route path="/dept-staff/reports" element={<ProtectedRoute allowedRoles={['dept_staff']}><DeptStaffReports /></ProtectedRoute>} />
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

          {/* Fallback routes to prevent blank screens on untracked links */}
          <Route path="/concerns/:id" element={<HomeRedirect />} />
          <Route path="*" element={<HomeRedirect />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}

export default App;
