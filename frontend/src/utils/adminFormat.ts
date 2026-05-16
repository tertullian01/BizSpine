export function formatMoney(amount: number): string {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(amount);
}

export function formatDate(iso: string, withTime = false): string {
  try {
    return withTime ? new Date(iso).toLocaleString() : new Date(iso).toLocaleDateString();
  } catch {
    return iso;
  }
}
