import axios from 'axios';

const apiOrigin =
  (typeof import.meta !== 'undefined' && import.meta.env && import.meta.env.VITE_API_URL) ||
  'http://localhost:8000';

export const isPublicAuthPath = (pathname = '') => {
  const normalized = String(pathname || '').replace(/\/+$/, '') || '/';
  return (
    normalized === '/login' ||
    normalized === '/register' ||
    normalized === '/forgot-password' ||
    normalized === '/reset-password' ||
    normalized.startsWith('/reset-password/')
  );
};

const api = axios.create({
  baseURL: `${apiOrigin}/api`,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

api.interceptors.request.use((config) => {
  const token = typeof localStorage !== 'undefined' ? localStorage.getItem('token') : null;

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      if (typeof localStorage !== 'undefined') {
        localStorage.removeItem('token');
        localStorage.removeItem('scms_token');
        localStorage.removeItem('scms_user');
      }

      if (typeof window !== 'undefined' && !isPublicAuthPath(window.location?.pathname)) {
        window.location.href = '/login';
      }
    }

    return Promise.reject(error);
  }
);

export default api;