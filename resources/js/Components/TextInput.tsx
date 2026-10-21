import { Input } from '@/Components/UI/Input';
import { forwardRef, InputHTMLAttributes, useEffect, useImperativeHandle, useRef } from 'react';

export default forwardRef<
  HTMLInputElement,
  InputHTMLAttributes<HTMLInputElement> & { isFocused?: boolean }
>(function TextInput({ isFocused = false, value, ...props }, ref) {
  const localRef = useRef<HTMLInputElement>(null);
  useImperativeHandle(ref, () => localRef.current!);
  useEffect(() => {
    if (isFocused) localRef.current?.focus();
  }, [isFocused]);

  return <Input {...props} ref={localRef} value={value === null ? '' : value} />;
});
