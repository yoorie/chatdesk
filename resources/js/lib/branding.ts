import { brandName } from './brand';

export const DEFAULT_PLATFORM_NAME = brandName();

export function hexToRgb(color?: string): string | null {
  if (!color || !/^#[a-f0-9]{6}$/i.test(color)) return null;
  return [1, 3, 5].map((offset) => parseInt(color.slice(offset, offset + 2), 16)).join(' ');
}

export function formatPageTitle(title: string, platformName = DEFAULT_PLATFORM_NAME): string {
  const name = platformName.trim() || DEFAULT_PLATFORM_NAME;
  if (!title || title === name || title.includes(`${name} |`) || title.endsWith(` - ${name}`))
    return title || name;
  return `${title} - ${name}`;
}
