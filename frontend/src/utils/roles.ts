const STAFF_ROLES = new Set(['admin', 'employee']);

export function isStaffRole(role: string | null | undefined): boolean {
  return Boolean(role && STAFF_ROLES.has(role));
}

export function isAdminRole(role: string | null | undefined): boolean {
  return role === 'admin';
}
