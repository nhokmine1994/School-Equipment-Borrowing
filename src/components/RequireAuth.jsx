import CircularProgress from '@mui/material/CircularProgress';
import Box from '@mui/material/Box';
import { Navigate, useLocation } from 'react-router-dom';
import { useSelector } from 'react-redux';

export default function RequireAuth({ children }) {
  const location = useLocation();
  const authStatus = useSelector((state) => state.app.authStatus);
  const user = useSelector((state) => state.app.user);

  if (authStatus === 'loading') {
    return (
      <Box sx={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        <CircularProgress />
      </Box>
    );
  }

  if (authStatus !== 'authenticated' || !user?.username) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  return children;
}