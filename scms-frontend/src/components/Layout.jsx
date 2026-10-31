import { Outlet } from 'react-router-dom';
import Navbar from './Navbar';

const Layout = ({ children }) => {
  return (
    <div className="min-h-screen flex flex-col bg-[#f8fafc]">
      <Navbar />
      <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        {children || <Outlet />}
      </main>
      <footer className="bg-white border-t border-slate-200 mt-auto py-5">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-slate-500">
          <div>
            &copy; {new Date().getFullYear()} University of Cebu - Lapu-Lapu and Mandaue. All rights reserved.
          </div>
          <div className="font-medium text-slate-400">
            Student Concern Management System (SCMS)
          </div>
        </div>
      </footer>
    </div>
  );
};

export default Layout;
