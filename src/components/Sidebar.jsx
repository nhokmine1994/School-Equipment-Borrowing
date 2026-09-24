import Drawer from '@mui/material/Drawer';
import List from '@mui/material/List';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import DashboardRoundedIcon from '@mui/icons-material/DashboardRounded';
import DevicesRoundedIcon from '@mui/icons-material/DevicesRounded';
import AssignmentReturnRoundedIcon from '@mui/icons-material/AssignmentReturnRounded';
import MeetingRoomRoundedIcon from '@mui/icons-material/MeetingRoomRounded';
import AccountCircleRoundedIcon from '@mui/icons-material/AccountCircleRounded';
import Inventory2RoundedIcon from '@mui/icons-material/Inventory2Rounded';
import HomeRoundedIcon from '@mui/icons-material/HomeRounded';
import { NavLink } from 'react-router-dom';

const drawerWidth = 240;

const navItems = [
  { label: 'Trang chủ', path: '/', icon: HomeRoundedIcon },
  { label: 'Dashboard', path: '/dashboard', icon: DashboardRoundedIcon },
  { label: 'Thiết bị', path: '/devices', icon: DevicesRoundedIcon },
  { label: 'Mượn trả', path: '/borrow', icon: AssignmentReturnRoundedIcon },
  { label: 'Phòng học', path: '/rooms', icon: MeetingRoomRoundedIcon },
  { label: 'Kho cá nhân', path: '/personal', icon: Inventory2RoundedIcon },
  { label: 'Hồ sơ', path: '/profile', icon: AccountCircleRoundedIcon },
];

export default function Sidebar() {
  return (
    <Drawer
      variant="permanent"
      sx={{
        width: drawerWidth,
        flexShrink: 0,
        '& .MuiDrawer-paper': {
          width: drawerWidth,
          boxSizing: 'border-box',
          borderRight: '1px solid',
          borderColor: 'divider',
          backgroundColor: '#f8fafc',
          pt: 2,
        },
      }}
    >
      <Box sx={{ px: 2, pb: 2 }}>
        <Typography variant="subtitle1" sx={{ fontWeight: 700, color: 'primary.main' }}>
          School Equipment Borrowing
        </Typography>
      </Box>

      <List sx={{ px: 1 }}>
        {navItems.map(({ label, path, icon: Icon }) => (
          <ListItemButton
            key={path}
            component={NavLink}
            to={path}
            end={path === '/'}
            sx={{
              borderRadius: 2,
              mb: 0.5,
              '&.active': {
                backgroundColor: 'rgba(25, 118, 210, 0.08)',
                color: 'primary.main',
                '& .MuiListItemIcon-root': {
                  color: 'primary.main',
                },
              },
            }}
          >
            <ListItemIcon>
              <Icon />
            </ListItemIcon>
            <ListItemText primary={label} />
          </ListItemButton>
        ))}
      </List>
    </Drawer>
  );
}
