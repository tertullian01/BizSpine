import { FormEvent, useCallback, useEffect, useState } from 'react';
import { apiRequest, ApiRequestError } from '../../api/client';
import type { BookkeepingExpense, BookkeepingIncome, BookkeepingSummary } from '../../api/types';
import { AdminFeedback } from '../../components/admin/AdminFeedback';
import { AdminPageHeader } from '../../components/admin/AdminPageHeader';
import { formatDate, formatMoney } from '../../utils/adminFormat';

export function AdminBookkeepingPage() {
  const [summary, setSummary] = useState<BookkeepingSummary | null>(null);
  const [income, setIncome] = useState<BookkeepingIncome[]>([]);
  const [expenses, setExpenses] = useState<BookkeepingExpense[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [category, setCategory] = useState('Operations');
  const [amount, setAmount] = useState('');
  const [vendor, setVendor] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [summaryData, incomeData, expenseData] = await Promise.all([
        apiRequest<BookkeepingSummary>('/bookkeeping/summary'),
        apiRequest<BookkeepingIncome[]>('/bookkeeping/income'),
        apiRequest<BookkeepingExpense[]>('/bookkeeping/expenses'),
      ]);
      setSummary(summaryData);
      setIncome(incomeData);
      setExpenses(expenseData);
    } catch (err) {
      setSummary(null);
      setIncome([]);
      setExpenses([]);
      setError(err instanceof ApiRequestError ? err.message : 'Failed to load bookkeeping');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const addExpense = async (event: FormEvent) => {
    event.preventDefault();
    setError(null);
    setSuccess(null);
    try {
      await apiRequest('/bookkeeping/expenses', {
        method: 'POST',
        body: JSON.stringify({
          category: category.trim(),
          amount: parseFloat(amount),
          vendor: vendor.trim() || undefined,
        }),
      });
      setSuccess('Expense recorded.');
      setAmount('');
      setVendor('');
      await load();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : 'Failed to add expense');
    }
  };

  return (
    <div className="page">
      <AdminPageHeader eyebrow="Finance" title="Bookkeeping" description="Income, expenses, and profit summary." />
      <AdminFeedback error={error} success={success} />
      {loading ? <p className="muted">Loading…</p> : null}

      {summary && !loading ? (
        <>
          <div className="stat-grid">
            <div className="stat-card stat-card-static">
              <span className="stat-label">Income</span>
              <span className="stat-value">{formatMoney(summary.total_income)}</span>
            </div>
            <div className="stat-card stat-card-static">
              <span className="stat-label">Expenses</span>
              <span className="stat-value">{formatMoney(summary.total_expenses)}</span>
            </div>
            <div className="stat-card stat-card-static">
              <span className="stat-label">Profit</span>
              <span className={`stat-value${summary.profit < 0 ? ' stat-value-warn' : ''}`}>
                {formatMoney(summary.profit)}
              </span>
            </div>
          </div>

          <section className="card admin-form-card">
            <h2>Record expense</h2>
            <form className="admin-form" onSubmit={addExpense}>
              <label>
                Category
                <input required value={category} onChange={(e) => setCategory(e.target.value)} />
              </label>
              <label>
                Amount
                <input type="number" step="0.01" required value={amount} onChange={(e) => setAmount(e.target.value)} />
              </label>
              <label>
                Vendor
                <input value={vendor} onChange={(e) => setVendor(e.target.value)} />
              </label>
              <button type="submit" className="btn btn-primary">
                Add expense
              </button>
            </form>
          </section>

          <div className="admin-two-col">
            <section className="card">
              <h2>Recent income</h2>
              {income.length === 0 ? (
                <p className="muted">No income records.</p>
              ) : (
                <div className="table-wrap">
                  <table className="data-table">
                    <thead>
                      <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Category</th>
                        <th scope="col" className="num">
                          Amount
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      {income.slice(0, 15).map((row) => (
                        <tr key={row.id}>
                          <td>{row.payment_date ? formatDate(row.payment_date) : '—'}</td>
                          <td>{row.category ?? '—'}</td>
                          <td className="num">{formatMoney(row.amount)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>

            <section className="card">
              <h2>Recent expenses</h2>
              {expenses.length === 0 ? (
                <p className="muted">No expense records.</p>
              ) : (
                <div className="table-wrap">
                  <table className="data-table">
                    <thead>
                      <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Category</th>
                        <th scope="col" className="num">
                          Amount
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      {expenses.slice(0, 15).map((row) => (
                        <tr key={row.id}>
                          <td>{row.expense_date ? formatDate(row.expense_date) : '—'}</td>
                          <td>{row.category ?? '—'}</td>
                          <td className="num">{formatMoney(row.amount)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>
          </div>
        </>
      ) : null}
    </div>
  );
}
