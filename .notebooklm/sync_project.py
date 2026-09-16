#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script đồng bộ tài liệu dự án vào Google NotebookLM phục vụ nghiên cứu & làm báo cáo.
"""

import sys
import os
import json
import subprocess
import argparse
from pathlib import Path

# Đảm bảo in tiếng Việt mượt mà trên Windows console
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')
if hasattr(sys.stderr, 'reconfigure'):
    sys.stderr.reconfigure(encoding='utf-8')

WORKSPACE_ROOT = Path(__file__).resolve().parent.parent
NLM_EXE = Path(__file__).resolve().parent / "venv" / "Scripts" / "nlm.exe"

def run_nlm(args, capture_json=False):
    cmd = [str(NLM_EXE)] + args
    if capture_json:
        cmd.append("--json")
    result = subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8")
    if result.returncode != 0:
        print(f"Lỗi khi chạy lệnh nlm: {' '.join(args)}", file=sys.stderr)
        print(result.stderr, file=sys.stderr)
        return None
    if capture_json:
        try:
            return json.loads(result.stdout)
        except json.JSONDecodeError:
            return result.stdout
    return result.stdout

def list_notebooks():
    output = run_nlm(["notebook", "list"], capture_json=True)
    return output

def find_project_docs():
    """Tìm các tài liệu Markdown và Schema SQL trong dự án."""
    docs = []
    # 1. Các file markdown ở root
    for f in WORKSPACE_ROOT.glob("*.md"):
        if f.is_file():
            docs.append(f)
    
    # 2. Các file tài liệu trong text/ nếu có
    text_dir = WORKSPACE_ROOT / "text"
    if text_dir.exists():
        for f in text_dir.glob("**/*.*"):
            if f.is_file() and f.suffix in [".txt", ".md", ".json"]:
                docs.append(f)

    # 3. Các file SQL trong sql/
    sql_dir = WORKSPACE_ROOT / "sql"
    if sql_dir.exists():
        for f in sql_dir.glob("*.sql"):
            if f.is_file():
                docs.append(f)

    return sorted(list(set(docs)))

def main():
    parser = argparse.ArgumentParser(description="Đẩy tài liệu dự án lên Google NotebookLM.")
    parser.add_argument("--notebook", "-n", help="Notebook ID hoặc title (nếu không nhập sẽ hiển thị danh sách để chọn hoặc tạo mới)")
    parser.add_argument("--create", "-c", help="Tạo notebook mới với tiêu đề chỉ định và đẩy tài liệu vào")
    parser.add_argument("--file", "-f", action="append", help="Chỉ định đường dẫn cụ thể của file muốn đẩy lên")
    parser.add_argument("--all-docs", action="store_true", default=True, help="Tự động tìm tất cả file *.md và *.sql dự án để đẩy lên")
    args = parser.parse_args()

    print("====================================================")
    print("  Đồng bộ tài liệu School-Equipment-Borrowing lên NotebookLM")
    print("====================================================\n")

    notebook_id = args.notebook

    if args.create:
        print(f"[+] Đang tạo Notebook mới: '{args.create}'...")
        res = run_nlm(["notebook", "create", args.create], capture_json=True)
        if res:
            if isinstance(res, dict) and "id" in res:
                notebook_id = res["id"]
            elif isinstance(res, str):
                # Parse ID from output text if needed
                for part in res.split():
                    if len(part) > 20 and ("-" in part or "_" in part):
                        notebook_id = part
                        break
            print(f"[✓] Đã tạo thành công Notebook ID: {notebook_id}\n")
        else:
            print("[-] Không thể tạo notebook. Vui lòng kiểm tra lại 'nlm login'.")
            sys.exit(1)

    if not notebook_id:
        print("[i] Đang tải danh sách Notebook hiện có...")
        res = run_nlm(["notebook", "list"])
        print(res if res else "Chưa có notebook nào hoặc chưa đăng nhập.")
        print("\nĐể đẩy tài liệu, hãy chạy:")
        print("  .\\sync_project.bat --notebook <notebook_id>")
        print("  hoặc:")
        print("  .\\sync_project.bat --create \"Tên Notebook Mới\"")
        sys.exit(0)

    # Xác định danh sách file cần đẩy
    files_to_push = []
    if args.file:
        for fpath in args.file:
            p = Path(fpath)
            if not p.is_absolute():
                p = WORKSPACE_ROOT / p
            if p.exists():
                files_to_push.append(p)
            else:
                print(f"[!] Không tìm thấy file: {fpath}")
    else:
        files_to_push = find_project_docs()

    if not files_to_push:
        print("[!] Không tìm thấy tài liệu nào phù hợp để đồng bộ.")
        sys.exit(0)

    print(f"[i] Tìm thấy {len(files_to_push)} tài liệu dự án để đẩy lên Notebook '{notebook_id}':")
    for f in files_to_push:
        print(f"  - {f.relative_to(WORKSPACE_ROOT)} ({f.stat().st_size} bytes)")
    print()

    SUPPORTED_FILE_EXTS = {'.pdf', '.docx', '.txt', '.md', '.csv', '.epub', '.jpg', '.jpeg', '.png', '.webp', '.mp3', '.mp4', '.wav', '.ogg'}

    for idx, f in enumerate(files_to_push, 1):
        print(f"[{idx}/{len(files_to_push)}] Đang đẩy file: {f.name}...")
        if f.suffix.lower() in SUPPORTED_FILE_EXTS:
            res = run_nlm(["source", "add", notebook_id, "--file", str(f), "--wait"])
        else:
            try:
                content = f.read_text(encoding="utf-8", errors="replace")
                res = run_nlm(["source", "add", notebook_id, "--title", f.name, "--text", content, "--wait"])
            except Exception as e:
                print(f"  [!] Lỗi khi đọc file text: {e}")
                res = None

        if res is not None:
            print(f"  [✓] Thành công: {f.name}")
        else:
            print(f"  [✗] Thất bại: {f.name}")

    print("\n[✓] Hoàn tất đồng bộ tài liệu!")
    print(f"Bây giờ bạn có thể truy vấn trực tiếp bằng:")
    print(f"  .\\query.bat {notebook_id} \"Tóm tắt kiến trúc của dự án này\"")

if __name__ == "__main__":
    main()
