/**
 * Brand assets in /public. Pick one canonical file per role; SVG preferred for UI.
 *
 * - Symbol: icon mark only
 * - SymbolName: icon + “BizSpine” wordmark
 * - Logo: full lockup (icon, name, tagline)
 */
const asset = (path: string) => `${import.meta.env.BASE_URL}${path.replace(/^\//, '')}`;

export const branding = {
  /** Browser tab — icon only, PNG for broad support */
  favicon: asset('BizSpine_Symbol_32x32.png'),
  /** Nav header — compact symbol + name (3∶4) */
  header: asset('BizSpine_SymbolName_300x400.svg'),
  /** Home hero — full brand lockup */
  hero: asset('BizSpine_Logo_2048x2048.svg'),
  /** iOS home screen (optional) */
  appleTouchIcon: asset('BizSpine_Logo_320x320.png'),
} as const;
