#!/usr/bin/env python3
"""Generate PDF using markdown -> HTML, then use macOS built-in tools."""
import markdown
import subprocess
import os

# Read markdown
with open('/Users/haztech/medos/docs/MedOS-System-Lifecycle.md', 'r') as f:
    md_content = f.read()

# Convert to HTML
html_body = markdown.markdown(md_content, extensions=['tables', 'fenced_code', 'toc'])

html_full = f"""<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body {{
        font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
        font-size: 11px;
        line-height: 1.6;
        color: #1e293b;
        max-width: 800px;
        margin: 0 auto;
        padding: 40px;
    }}
    h1 {{
        font-size: 28px;
        color: #1e40af;
        border-bottom: 3px solid #1e40af;
        padding-bottom: 8px;
        margin-top: 35px;
    }}
    h2 {{
        font-size: 20px;
        color: #1e40af;
        border-bottom: 1px solid #cbd5e1;
        padding-bottom: 5px;
        margin-top: 30px;
    }}
    h3 {{
        font-size: 15px;
        color: #334155;
        margin-top: 20px;
    }}
    table {{
        width: 100%;
        border-collapse: collapse;
        margin: 15px 0;
        font-size: 10px;
    }}
    th {{
        background: #1e293b;
        color: white;
        padding: 8px 10px;
        text-align: left;
        font-weight: 600;
    }}
    td {{
        padding: 6px 10px;
        border-bottom: 1px solid #e2e8f0;
    }}
    tr:nth-child(even) {{
        background: #f8fafc;
    }}
    code {{
        background: #f1f5f9;
        padding: 1px 5px;
        border-radius: 3px;
        font-size: 10px;
        font-family: 'SF Mono', Monaco, monospace;
    }}
    pre {{
        background: #1e293b;
        color: #e2e8f0;
        padding: 15px;
        border-radius: 8px;
        font-size: 9px;
        line-height: 1.5;
        overflow-x: auto;
        white-space: pre-wrap;
        word-wrap: break-word;
    }}
    pre code {{
        background: none;
        color: inherit;
        padding: 0;
    }}
    hr {{
        border: none;
        border-top: 2px solid #e2e8f0;
        margin: 25px 0;
    }}
    strong {{ color: #0f172a; }}
</style>
</head>
<body>
<div style="text-align:center; padding:80px 0 60px 0;">
    <div style="font-size:48px; font-weight:800; color:#1e40af; margin-bottom:10px;">MedOS</div>
    <div style="font-size:18px; color:#64748b; margin-bottom:40px;">Hospital Operating System</div>
    <div style="font-size:14px; color:#94a3b8;">Complete System Lifecycle Document</div>
    <div style="font-size:12px; color:#94a3b8; margin-top:10px;">Version 1.0 | March 2026</div>
    <div style="font-size:12px; color:#94a3b8; margin-top:40px;">Built by <strong style="color:#1e40af;">Haztech</strong> Digital Innovation Agency</div>
</div>
<hr>
{html_body}
</body>
</html>"""

# Write HTML
html_path = '/Users/haztech/medos/docs/MedOS-System-Lifecycle.html'
pdf_path = '/Users/haztech/medos/docs/MedOS-System-Lifecycle.pdf'

with open(html_path, 'w') as f:
    f.write(html_full)

# Use macOS cupsfilter or textutil, or just use /usr/sbin/cupsfilter
# Actually, simplest: use the lp command or just open in Safari for print
# Best option: use the open command to open HTML, user prints to PDF
#
# Alternative: use wkhtmltopdf if available, or headless Chrome
chrome_paths = [
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    '/Applications/Chromium.app/Contents/MacOS/Chromium',
    '/Applications/Brave Browser.app/Contents/MacOS/Brave Browser',
    '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
]

chrome = None
for p in chrome_paths:
    if os.path.exists(p):
        chrome = p
        break

if chrome:
    # Use headless Chrome to generate PDF
    result = subprocess.run([
        chrome,
        '--headless',
        '--disable-gpu',
        '--no-sandbox',
        f'--print-to-pdf={pdf_path}',
        '--print-to-pdf-no-header',
        f'file://{html_path}'
    ], capture_output=True, text=True, timeout=30)
    if os.path.exists(pdf_path):
        size = os.path.getsize(pdf_path)
        print(f"PDF generated: {pdf_path} ({size:,} bytes)")
    else:
        print(f"Chrome failed: {result.stderr[:200]}")
        print(f"HTML saved: {html_path}")
        print("Open this HTML file and use File > Print > Save as PDF")
else:
    print(f"HTML saved: {html_path}")
    print("No Chrome/Edge/Brave found. Opening HTML for manual print-to-PDF...")
    subprocess.run(['open', html_path])
