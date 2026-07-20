import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { adminService } from '../../services';
import { Card, Table, Pagination, Input, PageHeader, Spinner, fmtDateTime } from '../../components/ui';
import type { AuditLog } from '../../types';

export default function AuditPage() {
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState({ user_id: '', action: '', from: '', to: '' });

  const { data, isLoading } = useQuery({
    queryKey: ['audit-logs', page, filters],
    queryFn: () => adminService.auditLogs({ page: String(page), ...Object.fromEntries(Object.entries(filters).filter(([, v]) => v)) }),
  });

  const logs: AuditLog[] = Array.isArray(data?.data) ? data.data : [];
  const meta = data?.meta || { current_page: 1, last_page: 1, total: 0 };

  return (
    <div>
      <PageHeader title="Audit Logs" subtitle="Full activity log for all users" />

      {/* Filters */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <Input placeholder="User ID" value={filters.user_id} onChange={(e) => setFilters({ ...filters, user_id: e.target.value })} />
        <Input placeholder="Action (e.g. order.approved)" value={filters.action} onChange={(e) => setFilters({ ...filters, action: e.target.value })} />
        <Input type="date" value={filters.from} onChange={(e) => setFilters({ ...filters, from: e.target.value })} />
        <Input type="date" value={filters.to} onChange={(e) => setFilters({ ...filters, to: e.target.value })} />
      </div>

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'user', header: 'User', render: (l) => <span className="font-medium">{l.user?.name}</span> },
                { key: 'action', header: 'Action', render: (l) => <code className="text-xs bg-gray-100 px-2 py-0.5 rounded">{l.action}</code> },
                { key: 'model', header: 'Record', render: (l) => <span className="text-xs text-gray-500">{l.model_type?.split('\\').pop()} #{l.model_id}</span> },
                {
                  key: 'changes', header: 'Changes', render: (l) => (
                    <div className="text-xs text-gray-500 max-w-xs truncate">
                      {JSON.stringify(l.new_values)}
                    </div>
                  ),
                },
                { key: 'created_at', header: 'When', render: (l) => <span className="text-xs text-gray-400">{fmtDateTime(l.created_at)}</span> },
              ]}
              data={logs}
              keyField="id"
            />
            {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
          </>
        )}
      </Card>
    </div>
  );
}
