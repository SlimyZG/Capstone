import React from 'react';

const formatHoursCompact = (hours) => {
  if (hours === null || hours === undefined) return 'N/A (Insufficient Data)';
  const num = Number(hours);
  if (Number.isNaN(num)) return 'N/A (Insufficient Data)';
  if (num >= 24) {
    return `${num.toFixed(1)}h (${(num / 24).toFixed(1)}d)`;
  }
  return `${num.toFixed(1)}h`;
};

const formatDateTimeCompact = (value) => {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
  });
};

const formatFullTimestamp = (value) => {
  if (!value) return new Date().toLocaleString();
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formatCategoryLabel = (raw) => {
  if (!raw) return 'Uncategorized';
  if (raw === 'academic_units') return 'Academic Concerns';
  if (raw === 'operational_departments') return 'Operational Departments';
  return String(raw).replace(/_/g, ' ');
};

const formatStatusLabel = (status) => {
  if (!status) return '—';
  return String(status).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
};

/**
 * Dedicated, compact, A4-optimized institutional report layout for printing and Save-as-PDF.
 * Designed specifically to output a structured academic/administrative report rather than a dashboard screenshot.
 */
const PrintableConcernReport = ({
  report,
  filters = {},
  periodDescription = 'All Recorded Dates',
  currentUser = null,
  defaultReportTitle = 'Department Concern Report',
  defaultScopeLabel = 'Assigned Department / Academic Unit',
  previewMode = false,
}) => {
  if (!report) return null;

  const {
    summary = {},
    by_status = {},
    by_priority = {},
    by_category = [],
    activity_summary = {},
    attention_required = {},
    resolution_analytics = {},
    insights = [],
    concerns = [],
    report_metadata = {},
    generated_at,
  } = report;

  const totalConcerns = summary.total ?? 0;
  const resolvedConcerns = summary.resolved ?? 0;
  const unresolvedConcerns = summary.unresolved ?? summary.pending ?? 0;
  const resolutionRate = summary.resolution_rate ?? 0;
  const overdueCount = summary.overdue ?? 0;
  const criticalUnresolved = attention_required.critical_unresolved_count ?? summary.critical_unresolved ?? 0;
  const highUnresolved = attention_required.high_unresolved_count ?? summary.high_unresolved ?? 0;
  const escalatedCount = attention_required.escalated_count ?? summary.escalated ?? 0;

  const institutionName =
    report_metadata.institution_name || 'University of Cebu Lapu-Lapu and Mandaue';
  const systemName =
    report_metadata.system_name || 'Student Concern Management System (SCMS)';
  const reportTitle = report_metadata.report_title || defaultReportTitle;
  const assignedScope =
    report_metadata.assigned_scope ||
    currentUser?.department?.name ||
    currentUser?.academic_unit?.name ||
    defaultScopeLabel;
  const generatedBy =
    report_metadata.generated_by || currentUser?.name || 'Authorized Staff';
  const generatedByRole =
    report_metadata.generated_by_role ||
    (currentUser?.role === 'dean' ? 'Dean' : 'Department Staff');
  const generatedTimestamp = formatFullTimestamp(generated_at);

  const activeFiltersList = [];
  if (filters.date_from || filters.date_to) {
    activeFiltersList.push(`Date: ${filters.date_from || 'Start'} to ${filters.date_to || 'Present'}`);
  }
  if (filters.category) {
    activeFiltersList.push(`Category: ${formatCategoryLabel(filters.category)}`);
  }
  if (filters.priority) {
    activeFiltersList.push(`Priority: ${formatStatusLabel(filters.priority)}`);
  }
  if (filters.status) {
    activeFiltersList.push(`Status: ${formatStatusLabel(filters.status)}`);
  }
  if (filters.workflow_type) {
    activeFiltersList.push(`Track: ${formatStatusLabel(filters.workflow_type)}`);
  }

  const priorityOrder = ['critical', 'high', 'medium', 'low'];
  const statusEntries = Object.entries(by_status);
  const attentionItems = Array.isArray(attention_required.items) ? attention_required.items : [];
  const analyticsByPriority = Array.isArray(resolution_analytics?.by_priority)
    ? resolution_analytics.by_priority.filter((r) => (r.sample_size || r.count || 0) > 0)
    : [];

  return (
    <div
      id="scms-printable-report"
      data-testid="printable-concern-report"
      className={`${
        previewMode
          ? 'block bg-white border border-slate-300 shadow-lg rounded-xl p-8 max-w-[210mm] mx-auto my-4'
          : 'hidden print:block bg-white p-0 m-0 w-full'
      } text-slate-900 font-sans text-[11px] leading-snug`}
    >
      {/* Print-specific A4 layout & pagination rules */}
      <style>{`
        @media print {
          @page {
            size: A4 portrait;
            margin: 10mm 12mm 12mm 12mm;
          }
          html, body {
            background: #ffffff !important;
            color: #0f172a !important;
            font-size: 10pt !important;
            line-height: 1.35 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
          }
          #scms-printable-report {
            display: block !important;
            width: 100% !important;
            max-width: none !important;
            margin: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
            border: none !important;
          }
          .print-table {
            width: 100% !important;
            border-collapse: collapse !important;
          }
          .print-table thead {
            display: table-header-group !important;
          }
          .print-table tfoot {
            display: table-footer-group !important;
          }
          .print-table tr {
            break-inside: avoid !important;
            page-break-inside: avoid !important;
          }
          .print-avoid-break {
            break-inside: avoid !important;
            page-break-inside: avoid !important;
          }
        }
      `}</style>

      {/* ── 1. REPORT HEADER ── */}
      <header className="border-b-2 border-slate-900 pb-3 mb-4">
        <div className="flex items-start justify-between gap-4">
          <div>
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-600">
              {institutionName}
            </p>
            <p className="text-[11px] font-semibold text-[#1E3A8A]">
              {systemName}
            </p>
            <h1 className="text-lg font-black text-slate-900 uppercase tracking-tight mt-0.5">
              {reportTitle}
            </h1>
          </div>
          <div className="text-right text-[10px] text-slate-700 shrink-0 space-y-0.5">
            <div>
              <span className="font-bold text-slate-900">Generated:</span> {generatedTimestamp}
            </div>
            <div>
              <span className="font-bold text-slate-900">Prepared By:</span> {generatedBy} ({generatedByRole})
            </div>
            <div>
              <span className="font-bold text-slate-900">Document Ref:</span> SCMS-RPT-{new Date().toISOString().slice(0, 10).replace(/-/g, '')}
            </div>
          </div>
        </div>

        {/* Metadata Bar */}
        <div className="mt-2.5 pt-2 border-t border-slate-200 grid grid-cols-12 gap-2 text-[10px] bg-slate-50 px-2.5 py-1.5 rounded border border-slate-200">
          <div className="col-span-5">
            <span className="font-bold text-slate-700 uppercase">Assigned Department / Unit: </span>
            <span className="font-semibold text-slate-900">{assignedScope}</span>
          </div>
          <div className="col-span-4">
            <span className="font-bold text-slate-700 uppercase">Reporting Period: </span>
            <span className="font-semibold text-slate-900">{periodDescription}</span>
          </div>
          <div className="col-span-3 text-right">
            <span className="font-bold text-slate-700 uppercase">Filters: </span>
            <span className="text-slate-800">
              {activeFiltersList.length > 0 ? activeFiltersList.join(' | ') : 'None (All Authorized Records)'}
            </span>
          </div>
        </div>
      </header>

      {/* ── 2. EXECUTIVE SUMMARY (Compact KPI Strip) ── */}
      <section className="mb-4 print-avoid-break" data-testid="print-executive-summary">
        <h2 className="text-[11px] font-black uppercase tracking-wider text-slate-900 border-b border-slate-300 pb-1 mb-2">
          1. Executive Summary &amp; Key Performance Indicators
        </h2>
        <table className="print-table w-full border border-slate-300 text-left text-[10px]">
          <thead className="bg-slate-100 text-slate-700 uppercase font-bold">
            <tr>
              <th className="border border-slate-300 px-2.5 py-1.5">Total Received</th>
              <th className="border border-slate-300 px-2.5 py-1.5">Total Resolved</th>
              <th className="border border-slate-300 px-2.5 py-1.5">Total Unresolved</th>
              <th className="border border-slate-300 px-2.5 py-1.5">Resolution Rate</th>
              <th className="border border-slate-300 px-2.5 py-1.5">Typical Turnaround (Median)</th>
              <th className="border border-slate-300 px-2.5 py-1.5">Average Turnaround (Mean)</th>
            </tr>
          </thead>
          <tbody>
            <tr className="bg-white font-semibold text-slate-900 text-xs">
              <td className="border border-slate-300 px-2.5 py-2 font-black text-sm">{totalConcerns}</td>
              <td className="border border-slate-300 px-2.5 py-2 text-emerald-800 font-bold">{resolvedConcerns}</td>
              <td className="border border-slate-300 px-2.5 py-2 text-amber-800 font-bold">{unresolvedConcerns}</td>
              <td className="border border-slate-300 px-2.5 py-2 font-bold">{resolutionRate}%</td>
              <td className="border border-slate-300 px-2.5 py-2">{formatHoursCompact(summary.median_resolution_hours)}</td>
              <td className="border border-slate-300 px-2.5 py-2">{formatHoursCompact(summary.mean_resolution_hours)}</td>
            </tr>
          </tbody>
        </table>

        {insights.length > 0 && (
          <div className="mt-2 bg-slate-50 border border-slate-200 rounded px-2.5 py-1.5 text-[10px] text-slate-700">
            <span className="font-bold text-slate-900 uppercase mr-1.5">Key Observations:</span>
            {insights.map((ins) => ins.text).join(' • ')}
          </div>
        )}
      </section>

      {/* ── 3. CONCERN STATISTICS (Category, Priority, Status Breakdowns) ── */}
      <section className="mb-4 print-avoid-break" data-testid="print-statistics-section">
        <h2 className="text-[11px] font-black uppercase tracking-wider text-slate-900 border-b border-slate-300 pb-1 mb-2">
          2. Concern Statistical Breakdown
        </h2>
        <div className="grid grid-cols-3 gap-3">
          {/* Category Breakdown Table */}
          <div>
            <h3 className="text-[10px] font-bold uppercase text-slate-700 mb-1">
              By Category (n = {totalConcerns})
            </h3>
            <table className="print-table w-full border border-slate-300 text-[10px]">
              <thead className="bg-slate-100 font-bold text-slate-700">
                <tr>
                  <th className="border border-slate-300 px-2 py-1 text-left">Category</th>
                  <th className="border border-slate-300 px-2 py-1 text-right">Count</th>
                  <th className="border border-slate-300 px-2 py-1 text-right">Share</th>
                </tr>
              </thead>
              <tbody>
                {by_category.length === 0 ? (
                  <tr>
                    <td colSpan={3} className="border border-slate-300 px-2 py-2 text-center text-slate-500">
                      No category records
                    </td>
                  </tr>
                ) : (
                  by_category.map((row) => {
                    const pct = totalConcerns > 0 ? ((row.count / totalConcerns) * 100).toFixed(1) : '0.0';
                    return (
                      <tr key={row.category}>
                        <td className="border border-slate-300 px-2 py-1 font-medium text-slate-900">
                          {formatCategoryLabel(row.category)}
                        </td>
                        <td className="border border-slate-300 px-2 py-1 text-right font-semibold">{row.count}</td>
                        <td className="border border-slate-300 px-2 py-1 text-right text-slate-600">{pct}%</td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>

          {/* Priority Breakdown Table */}
          <div>
            <h3 className="text-[10px] font-bold uppercase text-slate-700 mb-1">
              By Priority Level (n = {totalConcerns})
            </h3>
            <table className="print-table w-full border border-slate-300 text-[10px]">
              <thead className="bg-slate-100 font-bold text-slate-700">
                <tr>
                  <th className="border border-slate-300 px-2 py-1 text-left">Priority</th>
                  <th className="border border-slate-300 px-2 py-1 text-right">Count</th>
                  <th className="border border-slate-300 px-2 py-1 text-right">Share</th>
                </tr>
              </thead>
              <tbody>
                {priorityOrder.map((p) => {
                  const cnt = Number(by_priority[p] ?? 0);
                  const pct = totalConcerns > 0 ? ((cnt / totalConcerns) * 100).toFixed(1) : '0.0';
                  return (
                    <tr key={p}>
                      <td className="border border-slate-300 px-2 py-1 font-medium capitalize text-slate-900">{p}</td>
                      <td className="border border-slate-300 px-2 py-1 text-right font-semibold">{cnt}</td>
                      <td className="border border-slate-300 px-2 py-1 text-right text-slate-600">{pct}%</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {/* Status Breakdown Table */}
          <div>
            <h3 className="text-[10px] font-bold uppercase text-slate-700 mb-1">
              By Workflow Status (n = {totalConcerns})
            </h3>
            <table className="print-table w-full border border-slate-300 text-[10px]">
              <thead className="bg-slate-100 font-bold text-slate-700">
                <tr>
                  <th className="border border-slate-300 px-2 py-1 text-left">Status</th>
                  <th className="border border-slate-300 px-2 py-1 text-right">Count</th>
                  <th className="border border-slate-300 px-2 py-1 text-right">Share</th>
                </tr>
              </thead>
              <tbody>
                {statusEntries.length === 0 ? (
                  <tr>
                    <td colSpan={3} className="border border-slate-300 px-2 py-2 text-center text-slate-500">
                      No status records
                    </td>
                  </tr>
                ) : (
                  statusEntries.map(([statusKey, countVal]) => {
                    const cnt = Number(countVal ?? 0);
                    const pct = totalConcerns > 0 ? ((cnt / totalConcerns) * 100).toFixed(1) : '0.0';
                    return (
                      <tr key={statusKey}>
                        <td className="border border-slate-300 px-2 py-1 font-medium text-slate-900">
                          {formatStatusLabel(statusKey)}
                        </td>
                        <td className="border border-slate-300 px-2 py-1 text-right font-semibold">{cnt}</td>
                        <td className="border border-slate-300 px-2 py-1 text-right text-slate-600">{pct}%</td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </div>
      </section>

      {/* ── 4. RESOLUTION AND FOLLOW-UP SUMMARY ── */}
      <section className="mb-4 print-avoid-break" data-testid="print-resolution-followup-section">
        <h2 className="text-[11px] font-black uppercase tracking-wider text-slate-900 border-b border-slate-300 pb-1 mb-2">
          3. Resolution Turnaround &amp; Follow-up Attention Summary
        </h2>

        <div className="grid grid-cols-12 gap-3 mb-2">
          <div className="col-span-7">
            <table className="print-table w-full border border-slate-300 text-[10px]">
              <thead className="bg-slate-100 font-bold text-slate-700">
                <tr>
                  <th className="border border-slate-300 px-2 py-1 text-left">Attention &amp; Backlog Metric</th>
                  <th className="border border-slate-300 px-2 py-1 text-right">Count</th>
                  <th className="border border-slate-300 px-2 py-1 text-left">Operational Status</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td className="border border-slate-300 px-2 py-1 font-medium">Critical Priority Unresolved</td>
                  <td className="border border-slate-300 px-2 py-1 text-right font-bold text-red-700">{criticalUnresolved}</td>
                  <td className="border border-slate-300 px-2 py-1 text-slate-600">
                    {criticalUnresolved > 0 ? 'Requires immediate intervention' : 'None open'}
                  </td>
                </tr>
                <tr>
                  <td className="border border-slate-300 px-2 py-1 font-medium">High Priority Unresolved</td>
                  <td className="border border-slate-300 px-2 py-1 text-right font-bold text-amber-800">{highUnresolved}</td>
                  <td className="border border-slate-300 px-2 py-1 text-slate-600">
                    {highUnresolved > 0 ? 'Active priority monitoring' : 'None open'}
                  </td>
                </tr>
                <tr>
                  <td className="border border-slate-300 px-2 py-1 font-medium">Overdue Concerns (SLA Target Exceeded)</td>
                  <td className="border border-slate-300 px-2 py-1 text-right font-bold text-red-700">{overdueCount}</td>
                  <td className="border border-slate-300 px-2 py-1 text-slate-600">
                    {overdueCount > 0 ? 'Past target resolution window' : 'Within SLA target'}
                  </td>
                </tr>
                <tr>
                  <td className="border border-slate-300 px-2 py-1 font-medium">Escalated / Referred Cases</td>
                  <td className="border border-slate-300 px-2 py-1 text-right font-bold">{escalatedCount}</td>
                  <td className="border border-slate-300 px-2 py-1 text-slate-600">
                    {escalatedCount > 0 ? 'Under administrative/Dean review' : 'None currently escalated'}
                  </td>
                </tr>
                <tr>
                  <td className="border border-slate-300 px-2 py-1 font-medium">Period Flow (Submitted vs. Resolved)</td>
                  <td className="border border-slate-300 px-2 py-1 text-right font-semibold">
                    {activity_summary.period_submitted ?? totalConcerns} in / {activity_summary.period_resolved ?? resolvedConcerns} out
                  </td>
                  <td className="border border-slate-300 px-2 py-1 text-slate-600">
                    Net backlog change: {(activity_summary.net_backlog_change ?? 0) > 0 ? `+${activity_summary.net_backlog_change}` : (activity_summary.net_backlog_change ?? 0)}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div className="col-span-5">
            <table className="print-table w-full border border-slate-300 text-[10px]">
              <thead className="bg-slate-100 font-bold text-slate-700">
                <tr>
                  <th className="border border-slate-300 px-2 py-1 text-left">Turnaround by Priority</th>
                  <th className="border border-slate-300 px-2 py-1 text-right">Cases</th>
                  <th className="border border-slate-300 px-2 py-1 text-right">Median</th>
                  <th className="border border-slate-300 px-2 py-1 text-right">Mean</th>
                </tr>
              </thead>
              <tbody>
                {analyticsByPriority.length === 0 ? (
                  <tr>
                    <td colSpan={4} className="border border-slate-300 px-2 py-3 text-center text-slate-500">
                      No completed turnaround records for this scope yet.
                    </td>
                  </tr>
                ) : (
                  analyticsByPriority.map((row) => (
                    <tr key={row.priority}>
                      <td className="border border-slate-300 px-2 py-1 font-medium capitalize">{row.priority}</td>
                      <td className="border border-slate-300 px-2 py-1 text-right">{row.sample_size ?? row.count ?? 0}</td>
                      <td className="border border-slate-300 px-2 py-1 text-right font-semibold">
                        {formatHoursCompact(row.median_hours ?? row.median_resolution_hours)}
                      </td>
                      <td className="border border-slate-300 px-2 py-1 text-right text-slate-600">
                        {formatHoursCompact(row.mean_hours ?? row.mean_resolution_hours)}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Outstanding Concerns Requiring Attention Table (when items exist) */}
        {attentionItems.length > 0 && (
          <div className="mt-2">
            <h3 className="text-[10px] font-bold uppercase text-red-800 mb-1">
              Outstanding Concerns Requiring Immediate Attention ({attentionItems.length})
            </h3>
            <table className="print-table w-full border border-slate-300 text-[9.5px]">
              <thead className="bg-red-50 font-bold text-slate-800">
                <tr>
                  <th className="border border-slate-300 px-2 py-1 text-left w-14">ID</th>
                  <th className="border border-slate-300 px-2 py-1 text-left">Title &amp; Category</th>
                  <th className="border border-slate-300 px-2 py-1 text-left">Attention Reason(s)</th>
                  <th className="border border-slate-300 px-2 py-1 text-left w-20">Priority</th>
                  <th className="border border-slate-300 px-2 py-1 text-left w-20">Status</th>
                  <th className="border border-slate-300 px-2 py-1 text-left w-28">Elapsed / Target</th>
                </tr>
              </thead>
              <tbody>
                {attentionItems.map((item) => (
                  <tr key={item.concern_id}>
                    <td className="border border-slate-300 px-2 py-1 font-bold">#{item.concern_id}</td>
                    <td className="border border-slate-300 px-2 py-1">
                      <div className="font-semibold text-slate-900">{item.title}</div>
                      <div className="text-[9px] text-slate-600">{formatCategoryLabel(item.category)}</div>
                    </td>
                    <td className="border border-slate-300 px-2 py-1 font-medium text-red-800">
                      {(item.reasons || []).join(', ')}
                    </td>
                    <td className="border border-slate-300 px-2 py-1 capitalize font-semibold">{item.priority}</td>
                    <td className="border border-slate-300 px-2 py-1">{formatStatusLabel(item.status)}</td>
                    <td className="border border-slate-300 px-2 py-1">
                      {item.elapsed_hours !== null && item.target_hours
                        ? `${item.elapsed_hours}h / ${item.target_hours}h`
                        : '—'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {/* ── 5. CONCERN RECORDS TABLE ── */}
      <section className="mb-4" data-testid="print-concern-records-section">
        <div className="flex items-center justify-between border-b border-slate-300 pb-1 mb-2">
          <h2 className="text-[11px] font-black uppercase tracking-wider text-slate-900">
            4. Detailed Concern Records ({concerns.length} {concerns.length === 1 ? 'Record' : 'Records'})
          </h2>
          <span className="text-[9.5px] text-slate-600">
            Sorted by submission date (newest first)
          </span>
        </div>

        {concerns.length === 0 ? (
          <div className="border border-slate-300 rounded p-4 text-center text-slate-600 text-[10px]">
            No concern records match the selected reporting period and filter criteria.
          </div>
        ) : (
          <table className="print-table w-full border border-slate-300 text-[9.5px] text-left">
            <thead className="bg-slate-100 text-slate-800 font-bold uppercase">
              <tr>
                <th className="border border-slate-300 px-1.5 py-1.5 w-12">ID</th>
                <th className="border border-slate-300 px-2 py-1.5">Title &amp; Summary</th>
                <th className="border border-slate-300 px-1.5 py-1.5 w-28">Category</th>
                <th className="border border-slate-300 px-1.5 py-1.5 w-16">Priority</th>
                <th className="border border-slate-300 px-1.5 py-1.5 w-20">Status</th>
                <th className="border border-slate-300 px-1.5 py-1.5 w-28">Assigned Dept / Unit</th>
                <th className="border border-slate-300 px-1.5 py-1.5 w-20">Submitted</th>
                <th className="border border-slate-300 px-1.5 py-1.5 w-24">Resolved / Outcome</th>
              </tr>
            </thead>
            <tbody>
              {concerns.map((c) => {
                const catLabel = formatCategoryLabel(c.issue_category || c.category);
                const subcatLabel = c.issue_subcategory ? ` (${c.issue_subcategory})` : '';
                const assignedDept =
                  c.department?.name || c.academic_unit?.name || c.academicUnit?.name || assignedScope;
                const summarySnippet = c.ai_summary || c.description || '';
                const studentDisplay = c.is_anonymous ? 'Anonymous' : (c.student?.name || '—');

                return (
                  <tr key={c.concern_id} className="align-top">
                    <td className="border border-slate-300 px-1.5 py-1.5 font-bold text-slate-900">
                      #{c.concern_id}
                    </td>
                    <td className="border border-slate-300 px-2 py-1.5 break-words">
                      <div className="font-bold text-slate-900 leading-tight">{c.title}</div>
                      {summarySnippet && (
                        <div className="text-[9px] text-slate-600 mt-0.5 leading-snug">
                          {summarySnippet.length > 220 ? `${summarySnippet.slice(0, 220)}…` : summarySnippet}
                        </div>
                      )}
                      <div className="text-[8.5px] text-slate-500 mt-0.5">
                        Student: {studentDisplay}
                      </div>
                    </td>
                    <td className="border border-slate-300 px-1.5 py-1.5 text-slate-800 break-words">
                      <span className="font-semibold">{catLabel}</span>
                      {subcatLabel && <span className="block text-[8.5px] text-slate-500">{subcatLabel}</span>}
                    </td>
                    <td className="border border-slate-300 px-1.5 py-1.5 capitalize font-semibold text-slate-900">
                      {c.priority || '—'}
                    </td>
                    <td className="border border-slate-300 px-1.5 py-1.5 font-medium text-slate-800">
                      {formatStatusLabel(c.status)}
                    </td>
                    <td className="border border-slate-300 px-1.5 py-1.5 text-slate-700 break-words">
                      {assignedDept}
                    </td>
                    <td className="border border-slate-300 px-1.5 py-1.5 whitespace-nowrap text-slate-700">
                      {formatDateTimeCompact(c.submitted_at)}
                    </td>
                    <td className="border border-slate-300 px-1.5 py-1.5 text-slate-700 break-words">
                      {c.resolved_at ? (
                        <div>
                          <div className="font-semibold text-emerald-800 whitespace-nowrap">
                            {formatDateTimeCompact(c.resolved_at)}
                          </div>
                          {c.resolution_notes && (
                            <div className="text-[8.5px] text-slate-600 mt-0.5">
                              {c.resolution_notes.length > 90
                                ? `${c.resolution_notes.slice(0, 90)}…`
                                : c.resolution_notes}
                            </div>
                          )}
                        </div>
                      ) : (
                        <span className="text-slate-400">Open / Pending</span>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </section>

      {/* ── 6. REPORT FOOTER ── */}
      <footer className="mt-6 pt-2.5 border-t border-slate-400 flex items-center justify-between text-[9px] text-slate-600">
        <div>
          <span className="font-bold text-slate-800 uppercase">Confidentiality Notice: </span>
          For authorized institutional use only ({institutionName} — {systemName}).
        </div>
        <div className="text-right shrink-0">
          Generated {generatedTimestamp} &bull; Scope: {assignedScope}
        </div>
      </footer>
    </div>
  );
};

export default PrintableConcernReport;
