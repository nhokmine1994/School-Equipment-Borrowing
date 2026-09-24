import AppBar from '@mui/material/AppBar';
import Avatar from '@mui/material/Avatar';
import Box from '@mui/material/Box';
import IconButton from '@mui/material/IconButton';
import Toolbar from '@mui/material/Toolbar';
import Typography from '@mui/material/Typography';
import { useSelector } from 'react-redux';
import NotificationsNoneOutlinedIcon from '@mui/icons-material/NotificationsNoneOutlined';
import LogoutRoundedIcon from '@mui/icons-material/LogoutRounded';
import { useNavigate } from 'react-router-dom';
import { useDispatch } from 'react-redux';
import { api } from '../services/api';
import { setAuthStatus, setUser } from '../store/slices/appSlice';

export default function Header() {
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const user = useSelector((state) => state.app.user);
  const initials = (user?.name ?? 'K')
    .split(' ')
    .filter(Boolean)
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();

  const handleLogout = async () => {
    await api.logoutUser();
    dispatch(setUser({ name: 'Khách', role: 'Guest', username: '' }));
    dispatch(setAuthStatus('guest'));
    navigate('/login', { replace: true });
  };

  return (
    <AppBar
      position="fixed"
      color="default"
      elevation={0}
      sx={{
        zIndex: (theme) => theme.zIndex.drawer + 1,
        borderBottom: '1px solid',
        borderColor: 'divider',
        backgroundColor: 'rgba(255,255,255,0.92)',
        backdropFilter: 'blur(8px)',
      }}
    >
      <Toolbar sx={{ minHeight: 72 }}>
        <Typography variant="h6" component="div" sx={{ flexGrow: 1, fontWeight: 700 }}>
          {user?.role ? `SEB ${user.role}` : 'SEB'}
        </Typography>

        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
          <IconButton aria-label="notifications" color="inherit">
            <NotificationsNoneOutlinedIcon />
          </IconButton>
          <IconButton aria-label="logout" color="inherit" onClick={handleLogout}>
            <LogoutRoundedIcon />
          </IconButton>
          <Avatar sx={{ width: 34, height: 34, bgcolor: 'primary.main' }}>{initials || 'K'}</Avatar>
        </Box>
      </Toolbar>
    </AppBar>
  );
}
