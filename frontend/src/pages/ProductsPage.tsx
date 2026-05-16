import { useEffect, useState } from 'react';
import { apiRequest, ApiRequestError } from '../api/client';
import type { PaginatedProducts, Product } from '../api/types';

export function ProductsPage() {
  const [products, setProducts] = useState<Product[]>([]);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState('');
  const [query, setQuery] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      setError(null);
      try {
        const params = new URLSearchParams({ limit: '24' });
        if (query) {
          params.set('search', query);
        }
        const result = await apiRequest<PaginatedProducts>(`/products?${params}`);
        setProducts(result.data);
        setTotal(result.pagination.total);
      } catch (err) {
        setProducts([]);
        setTotal(0);
        setError(err instanceof ApiRequestError ? err.message : 'Failed to load products');
      } finally {
        setLoading(false);
      }
    };
    void load();
  }, [query]);

  const onSearch = (event: React.FormEvent) => {
    event.preventDefault();
    setQuery(search.trim());
  };

  return (
    <div className="page">
      <header className="page-header">
        <h1>Products</h1>
        <p className="muted">Public catalog from the API. Staff can create products via the API or Swagger docs.</p>
      </header>

      <form className="search-bar" onSubmit={onSearch}>
        <input
          type="search"
          placeholder="Search by name, type, or description…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          aria-label="Search products"
        />
        <button type="submit" className="btn btn-primary">
          Search
        </button>
      </form>

      {loading ? <p className="muted">Loading products…</p> : null}
      {error ? (
        <div className="alert alert-error" role="alert">
          {error}
        </div>
      ) : null}

      {!loading && !error ? (
        <>
          <p className="results-meta">{total} product{total === 1 ? '' : 's'} found</p>
          {products.length === 0 ? (
            <p className="muted">No products yet. Add some via the API or backend setup.</p>
          ) : (
            <ul className="product-grid">
              {products.map((product) => (
                <li key={product.id} className="product-card">
                  {product.image_url ? (
                    <img src={product.image_url} alt="" className="product-image" loading="lazy" />
                  ) : (
                    <div className="product-image product-image-placeholder" aria-hidden>
                      {product.name.charAt(0)}
                    </div>
                  )}
                  <div className="product-body">
                    <h2>{product.name}</h2>
                    {product.type ? <span className="tag">{product.type}</span> : null}
                    {product.description ? <p>{product.description}</p> : null}
                    <footer>
                      {product.cost != null ? (
                        <strong>${Number(product.cost).toFixed(2)}</strong>
                      ) : (
                        <span className="muted">Price on request</span>
                      )}
                      {product.state ? <span className="muted">{product.state}</span> : null}
                    </footer>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </>
      ) : null}
    </div>
  );
}
