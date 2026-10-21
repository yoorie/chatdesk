import axios, { AxiosError, InternalAxiosRequestConfig } from 'axios';
import { appUrl, pathIncludes } from './lib/appUrl';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Function to get CSRF token from meta tag
function getCsrfToken(): string | null {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || null;
}

// Function to update CSRF token in meta tag and axios headers
function updateCsrfToken(token: string): void {
  const metaTag = document.querySelector('meta[name="csrf-token"]');
  if (metaTag) {
    metaTag.setAttribute('content', token);
  }
  window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
}

// Set initial CSRF token
const initialCsrfToken = getCsrfToken();
if (initialCsrfToken) {
  window.axios.defaults.headers.common['X-CSRF-TOKEN'] = initialCsrfToken;
}

// Request interceptor to ensure CSRF token is always included
window.axios.interceptors.request.use(
  (config) => {
    // Always ensure CSRF token is in headers
    const token = getCsrfToken() || window.axios.defaults.headers.common['X-CSRF-TOKEN'];
    if (token && config.headers) {
      config.headers['X-CSRF-TOKEN'] = token as string;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Flag to prevent multiple simultaneous refresh attempts
let isRefreshing = false;
let failedQueue: Array<{
  resolve: (value?: any) => void;
  reject: (error?: any) => void;
}> = [];

const processQueue = (error: any, token: string | null = null) => {
  failedQueue.forEach((prom) => {
    if (error) {
      prom.reject(error);
    } else {
      prom.resolve(token);
    }
  });

  failedQueue = [];
};

// Axios response interceptor to handle 419 errors
window.axios.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const originalRequest = error.config as InternalAxiosRequestConfig & { _retry?: boolean };

    // Handle 419 Page Expired errors
    if (error.response?.status === 419 && !originalRequest._retry) {
      if (isRefreshing) {
        // If already refreshing, queue this request
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject });
        })
          .then((token) => {
            if (originalRequest.headers) {
              originalRequest.headers['X-CSRF-TOKEN'] = token as string;
            }
            return window.axios(originalRequest);
          })
          .catch((err) => {
            return Promise.reject(err);
          });
      }

      originalRequest._retry = true;
      isRefreshing = true;

      try {
        // Try to refresh the CSRF token
        const response = await window.axios.get(appUrl('/csrf-token/refresh'), {
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
          },
          withCredentials: true,
        });

        const newToken = response.data.token;
        if (newToken) {
          updateCsrfToken(newToken);
          processQueue(null, newToken);

          // Retry the original request with the new token
          // Preserve all original headers and add the new CSRF token
          if (originalRequest.headers) {
            originalRequest.headers['X-CSRF-TOKEN'] = newToken;
            // Ensure credentials are included
            originalRequest.withCredentials = true;
          }
          return window.axios(originalRequest);
        } else {
          throw new Error('No token received from refresh endpoint');
        }
      } catch (refreshError: any) {
        processQueue(refreshError, null);

        // If refresh fails with 401, session is expired - redirect to login
        if (refreshError.response?.status === 401 || refreshError.response?.status === 419) {
          if (!pathIncludes(window.location.pathname, '/login')) {
            // Store the current URL to redirect back after login
            sessionStorage.setItem('redirect_after_login', window.location.href);
            window.location.href = appUrl('/login');
          }
        }
        return Promise.reject(refreshError);
      } finally {
        isRefreshing = false;
      }
    }

    return Promise.reject(error);
  }
);

// Periodically refresh CSRF token to prevent expiration (every 30 minutes)
// This is a proactive measure to keep the token fresh
if (typeof window !== 'undefined') {
  let refreshInterval: NodeJS.Timeout | null = null;

  // Only set up periodic refresh if we have a valid token
  if (initialCsrfToken) {
    refreshInterval = setInterval(
      async () => {
        // Skip if we're already refreshing
        if (isRefreshing) {
          return;
        }

        try {
          const currentToken = getCsrfToken();
          if (!currentToken) {
            // No token available, clear interval
            if (refreshInterval) {
              clearInterval(refreshInterval);
              refreshInterval = null;
            }
            return;
          }

          const response = await window.axios.get(appUrl('/csrf-token/refresh'), {
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': currentToken,
            },
          });

          const newToken = response.data?.token;
          if (newToken) {
            updateCsrfToken(newToken);
          }
        } catch (error: any) {
          // If it's a 401/403, session expired - clear interval
          if (error.response?.status === 401 || error.response?.status === 403) {
            if (refreshInterval) {
              clearInterval(refreshInterval);
              refreshInterval = null;
            }
          }
          // Silently fail for other errors - we'll handle it on the next request
          console.debug('CSRF token refresh failed (this is normal if session expired):', error);
        }
      },
      30 * 60 * 1000
    ); // 30 minutes
  }
}
