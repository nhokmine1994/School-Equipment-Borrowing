import React from 'react';
import ReactDOM from 'react-dom/client';
import { Provider } from 'react-redux';
import { BrowserRouter } from 'react-router-dom';
import App from './App';
import store from './store/store';
import './index.css';
import '../CSS/main.css';
import '../CSS/kho.css';
import '../CSS/kho-pages.css';
import '../CSS/dang-ky-phong.css';
import '../CSS/news.css';

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <Provider store={store}>
      <BrowserRouter basename={window.location.pathname.startsWith('/SEB') ? '/SEB' : undefined}>
        <App />
      </BrowserRouter>
    </Provider>
  </React.StrictMode>,
);
