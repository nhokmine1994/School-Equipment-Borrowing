@echo off
chcp 65001 >nul
if "%~1"=="" (
    echo ====================================================
    echo   Truy vấn NotebookLM (Hỏi đáp tài liệu)
    echo ====================================================
    echo Cách dùng: query.bat ^<notebook_id^> "^<cau_hoi^>"
    echo.
    echo Danh sách notebook hiện có:
    "%~dp0venv\Scripts\nlm.exe" notebook list
    echo.
    exit /b 1
)
"%~dp0venv\Scripts\nlm.exe" query notebook %*
