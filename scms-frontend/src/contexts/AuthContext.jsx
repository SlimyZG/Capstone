import { createContext, useContext, useState, useEffect } from 'react';
import api from '../api';

const AuthContext = createContext();

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(() => {
    try {
      const token = localStorage.getItem('token');
      const cached = localStorage.getItem('scms_user');
      return (token && cached) ? JSON.parse(cached) : null;
    } catch {
      return null;
    }
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const initAuth = async () => {
      const token = localStorage.getItem('token');
      if (token) {
        try {
          const res = await api.get('/user');
          setUser(res.data.user);
          localStorage.setItem('scms_user', JSON.stringify(res.data.user));
        } catch (error) {
          localStorage.removeItem('token');
          localStorage.removeItem('scms_token');
          localStorage.removeItem('scms_user');
          setUser(null);
        }
      } else {
        localStorage.removeItem('scms_user');
        localStorage.removeItem('scms_token');
        setUser(null);
      }
      setLoading(false);
    };
    initAuth();
  }, []);

  const login = async (credentials) => {
    const res = await api.post('/login', credentials);
    localStorage.setItem('token', res.data.token);
    localStorage.setItem('scms_user', JSON.stringify(res.data.user));
    setUser(res.data.user);
    return res.data;
  };

  const logout = async () => {
    try {
      await api.post('/logout');
    } finally {
      localStorage.removeItem('token');
      localStorage.removeItem('scms_token');
      localStorage.removeItem('scms_user');
      setUser(null);
    }
  };

  return (
    <AuthContext.Provider value={{ user, setUser, login, logout, loading }}>
      {loading ? (
        <div className="min-h-screen bg-[#f8fafc] flex items-center justify-center p-4">
          <div className="flex flex-col items-center gap-3">
            <div className="w-9 h-9 border-3 border-blue-200 border-t-[#1e3a8a] rounded-full animate-spin"></div>
            <p className="text-xs font-semibold text-slate-500">Loading SCMS...</p>
          </div>
        </div>
      ) : (
        children
      )}
    </AuthContext.Provider>
  );
};

export const useAuth = () => useContext(AuthContext);
