import { Config } from 'ziggy-js';

export interface User {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
  email_verified_at?: string;
  is_platform_admin?: boolean;
  is_super_admin?: boolean;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
  auth: {
    user: User;
  };
  ziggy: Config & { location: string };
};
