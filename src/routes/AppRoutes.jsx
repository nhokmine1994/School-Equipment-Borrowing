import { Navigate, Outlet, Route, Routes, useLocation } from 'react-router-dom';
import Box from '@mui/material/Box';
import CircularProgress from '@mui/material/CircularProgress';
import HomePage from '../pages/HomePage';
import LoginPage from '../pages/LoginPage';
import DashboardPage from '../pages/DashboardPage';
import DevicesPage from '../pages/DevicesPage';
import BorrowPage from '../pages/BorrowPage';
import RoomsPage from '../pages/RoomsPage';
import ProfilePage from '../pages/ProfilePage';
import PersonalPage from '../pages/PersonalPage';
import NewsPage from '../pages/NewsPage';
import AboutPage from '../pages/AboutPage';
import TeamPage from '../pages/TeamPage';
import ProjectPage from '../pages/ProjectPage';
import RequireAuth from '../components/RequireAuth';
import { useSelector } from 'react-redux';

function AuthOutlet() {
  return <Outlet />;
}

export default function AppRoutes() {
  const location = useLocation();
  const authStatus = useSelector((state) => state.app.authStatus);
  const user = useSelector((state) => state.app.user);

  if (authStatus === 'loading' && location.pathname !== '/') {
    return (
      <Box sx={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        <CircularProgress />
      </Box>
    );
  }

  return (
    <Routes>
      <Route path="/" element={<HomePage />} />
      <Route path="/news" element={<NewsPage />} />
      <Route path="/about" element={<AboutPage />} />
      <Route path="/ve-chung-toi" element={<TeamPage />} />
      <Route path="/ve-du-an" element={<ProjectPage />} />
      <Route path="/devices" element={<DevicesPage />} />

      <Route
        path="/login"
        element={authStatus === 'authenticated' && user?.username ? <Navigate to="/dashboard" replace /> : <LoginPage />}
      />

      <Route
        element={
          <RequireAuth>
            <AuthOutlet />
          </RequireAuth>
        }
      >
        <Route path="/dashboard" element={<DashboardPage />} />
        <Route path="/borrow" element={<BorrowPage />} />
        <Route path="/rooms" element={<RoomsPage />} />
        <Route path="/personal" element={<PersonalPage />} />
        <Route path="/profile" element={<ProfilePage />} />
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
