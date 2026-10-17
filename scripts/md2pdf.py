"""Markdown to PDF through headless Microsoft Edge.

Usage: python scripts/md2pdf.py docs/reports/day-5-report.md out.html
Writes the PDF next to the Markdown file (the HTML path is a scratch file).
"""
import sys, subprocess, pathlib, markdown

src = pathlib.Path(sys.argv[1]).resolve()
pdf = src.with_suffix(".pdf")
html_path = pathlib.Path(sys.argv[2]).resolve()
body = markdown.markdown(src.read_text(encoding="utf-8"), extensions=["tables", "fenced_code", "sane_lists"])
css = """
@page { size: A4; margin: 18mm 16mm; }
body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 10.5pt; line-height: 1.5; color: #1d2733; }
h1 { color: #0b5d3b; border-bottom: 3px solid #0b5d3b; padding-bottom: 6px; font-size: 22pt; }
h2 { color: #0b5d3b; border-bottom: 1px solid #c9d6cf; padding-bottom: 3px; margin-top: 26px; font-size: 15pt; page-break-after: avoid; }
h3 { color: #234; font-size: 12pt; page-break-after: avoid; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; font-size: 9.5pt; page-break-inside: auto; }
tr { page-break-inside: avoid; }
th { background: #0b5d3b; color: #fff; text-align: left; padding: 5px 7px; }
td { border: 1px solid #cfd8d3; padding: 4px 7px; vertical-align: top; }
tr:nth-child(even) td { background: #f4f8f6; }
code { font-family: Consolas, monospace; background: #eef2f0; padding: 1px 4px; border-radius: 3px; font-size: 9pt; }
pre { background: #1e2a26; color: #e8f1ec; padding: 10px; border-radius: 5px; overflow-wrap: anywhere; white-space: pre-wrap; page-break-inside: avoid; }
pre code { background: none; color: inherit; }
img { display: block; max-width: 100%; max-height: 235mm; width: auto; margin: 10px auto; border: 1px solid #cfd8d3; page-break-inside: avoid; }
hr { border: 0; border-top: 1px solid #c9d6cf; margin: 20px 0; }
"""
html_path.write_text(f"<!doctype html><html><head><meta charset='utf-8'><base href='{src.parent.as_uri()}/'><style>{css}</style></head><body>{body}</body></html>", encoding="utf-8")
edge = r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"
subprocess.run([edge, "--headless=new", "--disable-gpu", "--no-pdf-header-footer", f"--print-to-pdf={pdf}", html_path.as_uri()], check=True, timeout=120)
print(pdf, pdf.stat().st_size)
