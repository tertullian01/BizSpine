import { useCallback, useEffect, useState } from 'react';
import { apiRequest, ApiRequestError } from '../../api/client';
import type { InventoryRow, PaginatedInventory } from '../../api/types';
import { AdminFeedback } from '../../components/admin/AdminFeedback';
import { AdminPageHeader } from '../../components/admin/AdminPageHeader';
import { formatDate } from '../../utils/adminFormat';

export function AdminInventoryPage() {
  const [rows, setRows] = useState<InventoryRow[]>([]);
  const [lowStock, setLowStock] = useState<InventoryRow[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [adjustments, setAdjustments] = useState<Record<number, string>>({});
  const [busyId, setBusyId] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [inventory, low] = await Promise.all([
        apiRequest<PaginatedInventory>('/inventory?limit=100'),
        apiRequest<InventoryRow[]>('/inventory/low-stock'),
      ]);
      setRows(inventory.data);
      setTotal(inventory.pagination.total);
      setLowStock(low);
    } catch (err) {
      setRows([]);
      setLowStock([]);
      setTotal(0);
      setError(err instanceof ApiRequestError ? err.message : 'Failed to load inventory');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const adjust = async (id: number) => {
    const raw = adjustments[id];
    const adjustment = parseInt(raw, 10);
    if (!raw || Number.isNaN(adjustment)) {
      setError('Enter a valid adjustment (+/- quantity).');
      return;
    }
    setBusyId(id);
    setError(null);
    setSuccess(null);
    try {
      await apiRequest(`/inventory/${id}/adjust`, {
        method: 'POST',
        body: JSON.stringify({ adjustment }),
      });
      setSuccess('Stock adjusted.');
      setAdjustments((prev) => ({ ...prev, [id]: '' }));
      await load();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : 'Adjustment failed');
    } finally {
      setBusyId(null);
    }
  };

  return (
    <div className="page">
      <AdminPageHeader eyebrow="Stock" title="Inventory" description="View stock levels and post adjustments." />
      <AdminFeedback error={error} success={success} />
      {loading ? <p className="muted">Loading inventory…</p> : null}

      {!loading ? (
        <>
          {lowStock.length > 0 ? (
            <section className="card card-warn">
              <h2>Low stock ({lowStock.length})</h2>
              <InventoryTable rows={lowStock} showAdjust={false} />
            </section>
          ) : null}

          <section className="card">
            <h2>All locations</h2>
            <p className="results-meta">{total} inventory row{total === 1 ? '' : 's'}</p>
            <InventoryTable
              rows={rows}
              showAdjust
              adjustments={adjustments}
              busyId={busyId}
              onAdjustChange={(id, value) => setAdjustments((prev) => ({ ...prev, [id]: value }))}
              onAdjust={(id) => void adjust(id)}
            />
          </section>
        </>
      ) : null}
    </div>
  );
}


function InventoryTable({
  rows,
  showAdjust,
  adjustments,
  busyId,
  onAdjustChange,
  onAdjust,
}: {
  rows: InventoryRow[];
  showAdjust?: boolean;
  adjustments?: Record<number, string>;
  busyId?: number | null;
  onAdjustChange?: (id: number, value: string) => void;
  onAdjust?: (id: number) => void;
}) {
  return (
    <div className="table-wrap">
      <table className="data-table">
        <thead>
          <tr>
            <th scope="col">Product</th>
            <th scope="col">Store</th>
            <th scope="col" className="num">
              Qty
            </th>
            <th scope="col" className="num">
              Min
            </th>
            <th scope="col">Last restocked</th>
            {showAdjust ? <th scope="col">Adjust</th> : null}
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.id} className={isLow(row) ? 'row-warn' : undefined}>
              <td>{row.product_name ?? `Product #${row.product_id}`}</td>
              <td>{row.store_name ?? `Store #${row.store_id}`}</td>
              <td className="num">{row.quantity}</td>
              <td className="num">{row.min_quantity ?? '—'}</td>
              <td>{row.last_restocked ? formatDate(row.last_restocked) : '—'}</td>
              {showAdjust && adjustments && onAdjustChange && onAdjust ? (
                <td>
                  <div className="admin-inline-adjust">
                    <input
                      type="number"
                      placeholder="±"
                      className="admin-input-sm"
                      value={adjustments[row.id] ?? ''}
                      onChange={(e) => onAdjustChange(row.id, e.target.value)}
                    />
                    <button
                      type="button"
                      className="btn btn-secondary btn-sm"
                      disabled={busyId === row.id}
                      onClick={() => onAdjust(row.id)}
                    >
                      Apply
                    </button>
                  </div>
                </td>
              ) : null}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function isLow(row: InventoryRow): boolean {
  if (row.min_quantity == null) return false;
  return row.quantity <= row.min_quantity;
}
