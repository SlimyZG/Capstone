const StatusBadge = ({ status }) => {
  const getStatusStyles = () => {
    switch (status) {
      case 'pending': return 'bg-amber-100 text-amber-800';
      case 'in_progress': return 'bg-blue-100 text-blue-800';
      case 'escalated': return 'bg-purple-100 text-purple-800';
      case 'approved': return 'bg-emerald-100 text-emerald-800';
      case 'rejected': return 'bg-red-100 text-red-800';
      case 'resolved': return 'bg-emerald-100 text-emerald-800';
      case 'closed': return 'bg-slate-100 text-slate-800';
      default: return 'bg-slate-100 text-slate-800';
    }
  };

  const formatStatus = (s) => s.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());

  return (
    <span className={`badge ${getStatusStyles()}`}>
      {formatStatus(status)}
    </span>
  );
};

export default StatusBadge;
