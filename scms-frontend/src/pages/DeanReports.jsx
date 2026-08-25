import { useState } from 'react';
import { GraduationCap, Users, AlertTriangle, CheckSquare, FileText, Download, BarChart2 } from 'lucide-react';

const DeanReports = () => {
  const [dateRange, setDateRange] = useState('Fall Semester 2023');
  const [metricType, setMetricType] = useState('Academic Performance');

  return (
    <div>
      {/* Header */}
      <div className="flex justify-between items-start mb-6">
        <div>
          <h1 className="text-xl font-bold text-slate-900">Reports & Analytics</h1>
          <p className="text-slate-500 text-sm mt-0.5">Generate and export institutional performance metrics.</p>
        </div>
        <div className="flex gap-2">
          <button className="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm font-medium text-slate-600 hover:bg-slate-50 shadow-sm">
            <FileText size={14} /> Export PDF
          </button>
          <button className="flex items-center gap-2 px-4 py-2 bg-[#1e3a8a] rounded-lg text-sm font-semibold text-white hover:bg-[#152960] shadow-sm">
            <BarChart2 size={14} /> Generate Report
          </button>
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-xl border border-slate-200 p-5 shadow-sm mb-6">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-xs font-semibold text-slate-500 mb-1.5">Date Range</label>
            <select
              className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
              value={dateRange}
              onChange={e => setDateRange(e.target.value)}
            >
              <option>Fall Semester 2023</option>
              <option>Spring Semester 2023</option>
              <option>Academic Year 2022-2023</option>
              <option>Q1 2024</option>
            </select>
          </div>
          <div>
            <label className="block text-xs font-semibold text-slate-500 mb-1.5">Metric Type</label>
            <select
              className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
              value={metricType}
              onChange={e => setMetricType(e.target.value)}
            >
              <option>Academic Performance</option>
              <option>Concern Resolution Rate</option>
              <option>Student Engagement</option>
              <option>Escalation Trends</option>
            </select>
          </div>
        </div>
      </div>

      {/* Metric Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div className="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
          <div className="flex items-center gap-2 mb-3">
            <GraduationCap size={14} className="text-slate-400" />
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Avg GPA</span>
          </div>
          <p className="text-2xl font-bold text-slate-900">3.51</p>
          <p className="text-[10px] text-emerald-500 font-semibold mt-1 flex items-center gap-0.5">
            <span>↗</span> +0.12 from last semester
          </p>
        </div>

        <div className="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
          <div className="flex items-center gap-2 mb-3">
            <Users size={14} className="text-slate-400" />
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Attendance Rate</span>
          </div>
          <p className="text-2xl font-bold text-slate-900">92.8%</p>
          <p className="text-[10px] text-emerald-500 font-semibold mt-1 flex items-center gap-0.5">
            <span>↗</span> +1.5% from last semester
          </p>
        </div>

        <div className="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
          <div className="flex items-center gap-2 mb-3">
            <AlertTriangle size={14} className="text-slate-400" />
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">At-Risk Students</span>
          </div>
          <p className="text-2xl font-bold text-slate-900">145</p>
          <p className="text-[10px] text-red-500 font-semibold mt-1 flex items-center gap-0.5">
            <span>↗</span> +12 from last semester
          </p>
        </div>

        <div className="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
          <div className="flex items-center gap-2 mb-3">
            <CheckSquare size={14} className="text-slate-400" />
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Course Completion</span>
          </div>
          <p className="text-2xl font-bold text-slate-900">88.4%</p>
          <p className="text-[10px] text-slate-400 font-medium mt-1">— Stable</p>
        </div>
      </div>

      {/* Chart + Department Breakdown */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
        {/* GPA Chart Placeholder */}
        <div className="md:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 className="text-base font-bold text-slate-800">GPA Distribution Trend</h2>
            <button className="text-slate-400 hover:text-slate-600 text-lg leading-none">⋮</button>
          </div>
          <div className="flex flex-col items-center justify-center h-56 bg-slate-50/50 text-slate-300">
            <BarChart2 size={36} strokeWidth={1} />
            <p className="text-sm mt-3 font-medium">Visualization Data Area</p>
            <p className="text-xs mt-1">Report generation required.</p>
          </div>
        </div>

        {/* Department Breakdown */}
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div className="px-5 py-4 border-b border-slate-100">
            <h2 className="text-base font-bold text-slate-800">Department Breakdown</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-xs text-left">
              <thead className="text-[10px] uppercase text-slate-400 font-semibold bg-slate-50/60 border-b border-slate-100">
                <tr>
                  <th className="px-4 py-3">Dept</th>
                  <th className="px-4 py-3">Students</th>
                  <th className="px-4 py-3">Avg GPA</th>
                </tr>
              </thead>
              <tbody>
                <tr className="border-b border-slate-100 hover:bg-slate-50">
                  <td className="px-4 py-3 font-semibold text-slate-700">Computer Sci</td>
                  <td className="px-4 py-3 text-slate-600">1,240</td>
                  <td className="px-4 py-3">
                    <span className="px-2 py-1 bg-blue-50 text-blue-700 rounded text-[10px] font-bold">3.51</span>
                  </td>
                </tr>
                <tr className="border-b border-slate-100 hover:bg-slate-50">
                  <td className="px-4 py-3 font-semibold text-slate-700">Engineering</td>
                  <td className="px-4 py-3 text-slate-600">980</td>
                  <td className="px-4 py-3">
                    <span className="px-2 py-1 bg-blue-50 text-blue-700 rounded text-[10px] font-bold">3.44</span>
                  </td>
                </tr>
                <tr className="hover:bg-slate-50">
                  <td className="px-4 py-3 font-semibold text-slate-700">Business</td>
                  <td className="px-4 py-3 text-slate-600">756</td>
                  <td className="px-4 py-3">
                    <span className="px-2 py-1 bg-blue-50 text-blue-700 rounded text-[10px] font-bold">3.62</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
};

export default DeanReports;
