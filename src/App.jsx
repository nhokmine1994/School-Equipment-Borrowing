import AppRoutes from './routes/AppRoutes';
import usePageTitle from './hooks/usePageTitle';
import { useEffect } from 'react';
import { useDispatch } from 'react-redux';
import { api } from './services/api';
import { setAuthStatus, setUser } from './store/slices/appSlice';
import ErrorBoundary from './components/ErrorBoundary';

export default function App() {
  usePageTitle();
  const dispatch = useDispatch();

  useEffect(() => {
    let active = true;

    api.getCurrentUser().then((result) => {
      if (!active || !result?.success) {
        if (active) {
          dispatch(setAuthStatus('guest'));
        }
        return;
      }

      dispatch(setAuthStatus(result.loggedIn ? 'authenticated' : 'guest'));
      dispatch(
        setUser(
          result.loggedIn
            ? result.data
            : { name: 'Khách', role: 'Guest', username: '' },
        ),
      );
    });

    return () => {
      active = false;
    };
  }, [dispatch]);

  return (
    <ErrorBoundary>
      <AppRoutes />
    </ErrorBoundary>
  );
}
