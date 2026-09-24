import os
import re
from pathlib import Path

def find_files(root_dir, extensions):
    """Find all files with given extensions"""
    files = []
    for ext in extensions:
        for fpath in Path(root_dir).rglob(f"*{ext}"):
            if fpath.is_file():
                files.append(str(fpath))
    return files

def extract_refs(content):
    """Extract src and href references from HTML/PHP/JS content"""
    matches = re.findall(r'(?:src|href)\s*=\s*"([^"]+)"', content, re.IGNORECASE)
    return matches

def main():
    root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    extensions = ['.php', '.html', '.htm', '.js', '.css']
    files = find_files(root, extensions)
    
    missing = []
    
    for fpath in files:
        try:
            with open(fpath, 'r', encoding='utf-8', errors='ignore') as f:
                content = f.read()
        except:
            continue
        
        refs = extract_refs(content)
        if not refs:
            continue
        
        base_dir = os.path.dirname(fpath)
        
        for ref in refs:
            # Skip external URLs, anchors, mailto, data URIs
            if ref.startswith('http') or ref.startswith('//'):
                continue
            if ref.startswith('mailto:') or ref.startswith('data:'):
                continue
            if ref.startswith('#'):
                continue
            
            # Remove query string and fragment
            clean = re.sub(r'[\?#].*$', '', ref)
            
            # Resolve target path
            if clean.startswith('/'):
                target = os.path.join(root, clean.lstrip('/'))
            else:
                target = os.path.normpath(os.path.join(base_dir, clean))
            
            if not os.path.exists(target):
                rel_source = os.path.relpath(fpath, root)
                rel_target = os.path.relpath(target, root) if os.path.exists(os.path.dirname(target)) else 'UNRESOLVED'
                missing.append({
                    'source': rel_source,
                    'ref': ref,
                    'resolved': rel_target
                })
    
    if not missing:
        print("✓ No missing local references found.")
        return
    
    print(f"✗ Found {len(missing)} missing references:\n")
    for m in missing:
        print(f"  Source:  {m['source']}")
        print(f"  Ref:     {m['ref']}")
        print(f"  Resolved: {m['resolved']}\n")
    
    print(f"Total missing: {len(missing)}")

if __name__ == '__main__':
    main()
