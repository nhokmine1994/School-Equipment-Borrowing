import { Component } from 'react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Container from '@mui/material/Container';
import Typography from '@mui/material/Typography';

export default class ErrorBoundary extends Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError() {
    return { hasError: true };
  }

  componentDidCatch(error) {
    // Keep the app usable even if a page crashes.
    console.error('SEB render error:', error);
  }

  handleReload = () => {
    window.location.reload();
  };

  render() {
    if (this.state.hasError) {
      return (
        <Container maxWidth="sm" sx={{ minHeight: '100vh', display: 'flex', alignItems: 'center' }}>
          <Card sx={{ width: '100%' }}>
            <CardContent sx={{ p: 4 }}>
              <Box sx={{ display: 'grid', gap: 2 }}>
                <Alert severity="error">Đã xảy ra lỗi khi hiển thị giao diện.</Alert>
                <Typography variant="h5" sx={{ fontWeight: 700 }}>
                  SEB tạm thời không thể tải một phần trang.
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  Dữ liệu backend vẫn được giữ nguyên. Bạn có thể tải lại trang để thử lại.
                </Typography>
                <Button variant="contained" onClick={this.handleReload} sx={{ width: 'fit-content' }}>
                  Tải lại trang
                </Button>
              </Box>
            </CardContent>
          </Card>
        </Container>
      );
    }

    return this.props.children;
  }
}