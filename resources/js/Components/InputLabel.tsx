import { Label } from '@/Components/UI/Label';
import { LabelHTMLAttributes } from 'react';

export default function InputLabel({
  value,
  className = '',
  children,
  ...props
}: LabelHTMLAttributes<HTMLLabelElement> & { value?: string }) {
  return (
    <Label {...props} className={`block leading-normal ` + className}>
      {value ? value : children}
    </Label>
  );
}
