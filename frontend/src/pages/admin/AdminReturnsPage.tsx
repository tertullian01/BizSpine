import { useCallback, useEffect, useState } from 'react';
import { apiRequest, ApiRequestError } from '../../api/client';
import type { AdminReturn } from '../../api/types';
import { AdminFeedback } from '../../components/admin/AdminFeedback';
import { AdminPageHeader } from '../../components/admin/AdminPageHeader';
import { formatMoney } from '../../utils/adminFormat';

export function AdminReturnsPage() {
  const [returns, setReturns] = useState<AdminReturn[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await apiRequest<AdminReturn[]>('/returns');
      setReturns(data);
    } catch (err) {
      setReturns([]);
      setError(err instanceof ApiRequestError ? err.message : 'Failed to load returns');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const approve = async (id: number) => {
    setBusyId(id);
    setError(null);
    setSuccess(null);
    try {
      await apiRequest(`/returns/${id}/approve`, { method: 'POST' });
      setSuccess('Return approved.');
      await load();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : 'Approve failed');
    } finally {
      setBusyId(null);
    }
  };

  const refund = async (id: number) => {
    setBusyId(id);
    setError(null);
    setSuccess(null);
    try {
      await apiRequest(`/returns/${id}/refund`, { method: 'POST', body: JSON.stringify({}) });
      setSuccess('Refund processed.');
      await load();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : 'Refund failed');
    } finally {
      setBusyId(null);
    }
  };

  return (
    <div className="page">
      <AdminPageHeader eyebrow="After sales" title="Returns" description="Approve returns and process refunds." />
      <AdminFeedback error={error} success={success} />
      {loading ? <p className="muted">Loading returns…</p> : null}
      {!loading ? (
        <section className="card">
          {returns.length === 0 ? (
            <p className="muted">No return requests.</p>
          ) : (
            <div className="table-wrap">
              <table className="data-table">
                <thead>
                  <tr>
                    <th scope="col">Return</th>
                    <th scope="col">Order</th>
                    <th scope="col">Customer</th>
                    <th scope="col">Status</th>
                    <th scope="col" className="num">
                      Refund
                    </th>
                    <th scope="col" />
                  </tr>
                </thead>
                <tbody>
                  {returns.map((r) => (
                    <tr key={r.id}>
                      <td>{r.return_number ?? `#${r.id}`}</td>
                      <td>{r.order_number ?? r.order_id}</td>
                      <td>{r.user_email ?? '—'}</td>
                      <td>
                        <span className="badge">{r.status ?? 'pending'}</span>
                      </td>
                      <td className="num">{r.refund_amount != null ? formatMoney(r.refund_amount) : '—'}</td>
                      <td className="admin-row-actions">
                        {r.status === 'requested' ? (
                          <button
                            type="button"
                            className="btn btn-secondary btn-sm"
                            disabled={busyId === r.id}
                            onClick={() => void approve(r.id)}
                          >
                            Approve
                          </button>
                        ) : null}
                        {r.status === 'approved' ? (
                          <button
                            type="button"
                            className="btn btn-primary btn-sm"
                            disabled={busyId === r.id}
                            onClick={() => void refund(r.id)}
                          >
                            Refund
                          </button>
                        ) : null}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      ) : null}
    </div>
  );
}
