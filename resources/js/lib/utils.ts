import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function isSameAccountId(left?: string | number | null, right?: string | number | null) {
  if (left == null || right == null) return false;
  const leftNum = Number(left);
  const rightNum = Number(right);
  if (!Number.isNaN(leftNum) && !Number.isNaN(rightNum)) {
    return leftNum === rightNum;
  }
  return String(left) === String(right);
}
