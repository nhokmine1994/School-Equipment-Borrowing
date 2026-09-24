import { createSlice } from '@reduxjs/toolkit';

const initialState = {
  appName: 'SEB',
  user: { name: 'Khách', role: 'Guest', username: '' },
  theme: 'light',
  authStatus: 'loading',
};

const appSlice = createSlice({
  name: 'app',
  initialState,
  reducers: {
    setUser: (state, action) => {
      state.user = action.payload;
    },
    setTheme: (state, action) => {
      state.theme = action.payload;
    },
    setAuthStatus: (state, action) => {
      state.authStatus = action.payload;
    },
  },
});

export const { setUser, setTheme, setAuthStatus } = appSlice.actions;
export default appSlice.reducer;
