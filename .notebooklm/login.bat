@echo off
chcp 65001 >nul
echo ====================================================
echo   Dang nhap Google NotebookLM cho workspace nay
echo ====================================================
echo Trinh duyet se mo ra de ban dang nhap tai khoan Google.
echo Sau khi dang nhap xong, token/cookies se duoc luu an toan tai local.
echo.
"%~dp0venv\Scripts\nlm.exe" login
echo.
pause
