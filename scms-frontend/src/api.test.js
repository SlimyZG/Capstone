import { afterEach, test } from 'node:test';
import assert from 'node:assert/strict';
import api, { isPublicAuthPath } from './api.js';

const hasOwnLocalStorage = Object.prototype.hasOwnProperty.call(globalThis, 'localStorage');
const originalLocalStorage = globalThis.localStorage;
const hasOwnWindow = Object.prototype.hasOwnProperty.call(globalThis, 'window');
const originalWindow = globalThis.window;

function createMockStorage(initialEntries = {}) {
  const store = new Map(Object.entries(initialEntries));
  return {
    getItem: (key) => (store.has(key) ? store.get(key) : null),
    setItem: (key, value) => store.set(key, String(value)),
    removeItem: (key) => store.delete(key),
    has: (key) => store.has(key),
  };
}

afterEach(() => {
  if (hasOwnLocalStorage) {
    globalThis.localStorage = originalLocalStorage;
  } else {
    delete globalThis.localStorage;
  }

  if (hasOwnWindow) {
    globalThis.window = originalWindow;
  } else {
    delete globalThis.window;
  }
});

test('isPublicAuthPath recognizes login, registration, and password recovery routes', () => {
  const publicPaths = [
    '/login',
    '/login/',
    '/register',
    '/register/',
    '/forgot-password',
    '/forgot-password/',
    '/reset-password',
    '/reset-password/',
    '/reset-password/abc123token',
  ];

  for (const path of publicPaths) {
    assert.equal(isPublicAuthPath(path), true, `Expected ${path} to be treated as public auth route`);
  }

  const protectedOrOtherPaths = [
    '/',
    '/student',
    '/sao',
    '/dean',
    '/dept-staff',
    '/admin',
    '/reset-password-other',
    '',
  ];

  for (const path of protectedOrOtherPaths) {
    assert.equal(isPublicAuthPath(path), false, `Expected ${path} NOT to be treated as public auth route`);
  }
});

test('401 interceptor clears stale storage without redirecting away from password recovery and public auth routes', async () => {
  const publicRoutes = [
    '/forgot-password',
    '/reset-password',
    '/reset-password/abc123token',
    '/login',
    '/register',
  ];

  for (const pathname of publicRoutes) {
    const storage = createMockStorage({
      token: 'stale-bearer-token',
      scms_token: 'stale-scms-token',
      scms_user: JSON.stringify({ id: 1, role: 'student' }),
    });
    const initialHref = `https://scms.local${pathname}`;
    const mockWindow = {
      location: {
        pathname,
        href: initialHref,
      },
    };

    globalThis.localStorage = storage;
    globalThis.window = mockWindow;

    await assert.rejects(
      () =>
        api.get('/user', {
          adapter: () =>
            Promise.reject({
              response: { status: 401, data: { message: 'Unauthenticated.' } },
              config: {},
            }),
        }),
      (err) => err?.response?.status === 401
    );

    assert.equal(storage.getItem('token'), null);
    assert.equal(storage.getItem('scms_token'), null);
    assert.equal(storage.getItem('scms_user'), null);
    assert.equal(
      mockWindow.location.href,
      initialHref,
      `Expected stale 401 on ${pathname} not to redirect away`
    );
  }
});

test('401 interceptor clears stale storage and redirects to /login on protected routes', async () => {
  const protectedRoutes = ['/student', '/sao/oversight'];

  for (const pathname of protectedRoutes) {
    const storage = createMockStorage({
      token: 'stale-bearer-token',
      scms_token: 'stale-scms-token',
      scms_user: JSON.stringify({ id: 1, role: 'student' }),
    });
    const mockWindow = {
      location: {
        pathname,
        href: `https://scms.local${pathname}`,
      },
    };

    globalThis.localStorage = storage;
    globalThis.window = mockWindow;

    await assert.rejects(
      () =>
        api.get('/user', {
          adapter: () =>
            Promise.reject({
              response: { status: 401, data: { message: 'Unauthenticated.' } },
              config: {},
            }),
        }),
      (err) => err?.response?.status === 401
    );

    assert.equal(storage.getItem('token'), null);
    assert.equal(storage.getItem('scms_token'), null);
    assert.equal(storage.getItem('scms_user'), null);
    assert.equal(
      mockWindow.location.href,
      '/login',
      `Expected stale 401 on ${pathname} to redirect to /login`
    );
  }
});
