import { useState, useEffect } from 'react';
// import { PieChart, Pie, Cell, Tooltip, ResponsiveContainer, LineChart, Line, XAxis, YAxis, CartesianGrid, Legend } from 'recharts';
// import ReactWordcloud from 'react-wordcloud';
import api from '../api';

const SentimentDashboard = () => {
  const [data, setData] = useState({
    distribution: { positive: 0, neutral: 0, negative: 0, urgent: 0 },
    flagged: [],
    trend: []
  });
  const [trends, setTrends] = useState({ stats: null, narrative: '' });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchAnalytics();
  }, []);

  const fetchAnalytics = async () => {
    try {
      setLoading(true);
      const [sentimentRes, trendsRes] = await Promise.all([
        api.get('/analytics/sentiment'),
        api.get('/analytics/trends')
      ]);
      setData(sentimentRes.data);
      setTrends(trendsRes.data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const pieData = [
    { name: 'Positive', value: data.distribution.positive || 0, color: '#10b981' },
    { name: 'Neutral', value: data.distribution.neutral || 0, color: '#94a3b8' },
    { name: 'Negative', value: data.distribution.negative || 0, color: '#f59e0b' },
    { name: 'Urgent', value: data.distribution.urgent || 0, color: '#ef4444' }
  ].filter(d => d.value > 0);

  // Mock word cloud data based on common terms
  const wordCloudData = [
    { text: 'exam', value: 64 },
    { text: 'grade', value: 55 },
    { text: 'schedule', value: 43 },
    { text: 'professor', value: 32 },
    { text: 'tuition', value: 28 },
    { text: 'facilities', value: 25 },
    { text: 'wifi', value: 22 },
    { text: 'enrollment', value: 18 },
    { text: 'advising', value: 15 },
  ];

  if (loading) return <div className="p-8 text-center text-slate-500">Loading analytics...</div>;

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">Analytics & Sentiment Dashboard</h1>
        <p className="text-slate-500 text-sm mt-1">Real-time AI analysis of student concerns, trends, and urgencies.</p>
      </div>

      <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
        <h2 className="text-lg font-bold text-slate-800 mb-2">AI Trend Analysis</h2>
        <p className="text-slate-700 italic leading-relaxed bg-slate-50 p-4 rounded-lg border border-slate-100">
          "{trends.narrative}"
        </p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
          <h2 className="text-lg font-bold text-slate-800 mb-4">Sentiment Distribution</h2>
          <div className="h-64 flex items-center justify-center bg-slate-50 rounded-lg">
             <span className="text-slate-500">Pie Chart Temporarily Disabled</span>
          </div>
        </div>

        <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
          <h2 className="text-lg font-bold text-slate-800 mb-4">Common Themes (Word Cloud)</h2>
          <div className="h-64">
             <div className="text-center text-slate-500 pt-20">Word Cloud Disabled Temporarily</div>
          </div>
        </div>
      </div>

      <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
        <h2 className="text-lg font-bold text-slate-800 mb-4">Average Sentiment Over Time (Last 30 Days)</h2>
        <div className="h-64 flex items-center justify-center bg-slate-50 rounded-lg">
           <span className="text-slate-500">Line Chart Temporarily Disabled</span>
        </div>
      </div>

      <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
        <h2 className="text-lg font-bold text-slate-800 mb-4">Flagged High-Urgency Concerns</h2>
        {data.flagged.length === 0 ? (
          <p className="text-slate-500 text-sm">No high-urgency concerns flagged recently.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-200">
              <thead>
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Concern</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Student</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Sentiment</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Priority</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-slate-200">
                {data.flagged.map((concern) => (
                  <tr key={concern.concern_id} className="hover:bg-slate-50">
                    <td className="px-6 py-4">
                      <div className="text-sm font-medium text-slate-900">{concern.title}</div>
                      <div className="text-xs text-slate-500 truncate max-w-xs">{concern.ai_summary}</div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                      {concern.student?.name}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className="text-xs font-medium px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-800 capitalize">
                        {concern.sentiment_label} ({concern.sentiment_score})
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                        ${concern.priority === 'critical' ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800'}`}>
                        {concern.priority}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
};

export default SentimentDashboard;
