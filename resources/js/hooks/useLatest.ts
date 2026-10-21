import { useEffect, useRef } from 'react';

// For keyed initialization effects: read the latest props without resetting
// in-progress form edits whenever the server refreshes the same record.
export function useLatest<T>(value: T) {
  const ref = useRef(value);
  useEffect(() => {
    ref.current = value;
  }, [value]);
  return ref;
}
