import { useEffect, useState } from 'react';
import { apiRequest, ApiRequestError } from '../../api/client';
import type { AdminClient } from '../../api/types';
import { AdminFeedback } from '../../components/admin/AdminFeedback';
import { AdminPageHeader } from '../../components/admin/AdminPageHeader';
import { formatDate, formatMoney } from '../../utils/adminFormat';

export function AdminClientsPage() {
  const [clients, setClients] = useState<AdminClient[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      setError(null);
      try {
        const data = await apiRequest<AdminClient[]>('/clients');
        setClients(data);
      } catch (err) {
        setClients([]);
        setError(err instanceof ApiRequestError ? err.message : 'Failed to load clients');
      } finally {
        setLoading(false);
      }
    };
    void load();
  }, []);

  return (
    <div className="page">
      <AdminPageHeader
        eyebrow="Customers"
        title="Clients"
        description="Customer profiles with order stats from GET /clients."
      />
      <AdminFeedback error={error} />
      {loading ? <p className="muted">Loading clients…</p> : null}
      {!loading && !error ? (
        <section className="card">
          <p className="results-meta">{clients.length} client{clients.length === 1 ? '' : 's'}</p>
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th scope="col">Email</th>
                  <th scope="col">Name</th>
                  <th scope="col" className="num">
                    Orders
                  </th>
                  <th scope="col" className="num">
                    Spent
                  </th>
                  <th scope="col">Last order</th>
                </tr>
              </thead>
              <tbody>
                {clients.map((c) => (
                  <tr key={c.id}>
                    <td>{c.email ?? '—'}</td>
                    <td>
                      {c.display_name ??
                        ([c.first_name, c.last_name].filter(Boolean).join(' ') || '—')}
                    </td>
                    <td className="num">{c.order_count}</td>
                    <td className="num">{formatMoney(c.total_spent)}</td>
                    <td>{c.last_order_date ? formatDate(c.last_order_date) : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      ) : null}
    </div>
  );
}
