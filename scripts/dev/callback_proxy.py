"""Allow-list proxy: exposes ONLY the Daraja callback path of the local site.

Everything else (login pages, admin, the rest of the site) is answered 404 here,
so a public tunnel pointed at this proxy never exposes the local Drupal site.
"""
import datetime
import http.server
import json
import pathlib
import re
import sys
import urllib.error
import urllib.request

UPSTREAM = "http://athari.ddev.site"
ALLOWED = re.compile(r"^/athari/mpesa/callback/[0-9a-f]{32}$")
LOG = pathlib.Path(sys.argv[1])
PORT = int(sys.argv[2]) if len(sys.argv) > 2 else 8765


class Handler(http.server.BaseHTTPRequestHandler):
    def log_message(self, *args):
        pass

    def note(self, text):
        with LOG.open("a", encoding="utf-8") as f:
            f.write(f"{datetime.datetime.now().isoformat(timespec='seconds')} {text}\n")

    def do_POST(self):
        path = self.path.split("?")[0]
        masked = re.sub(r"[0-9a-f]{32}$", "<token>", path)
        if not ALLOWED.match(path):
            self.note(f"BLOCKED POST {masked}")
            self.send_response(404)
            self.end_headers()
            return
        length = min(int(self.headers.get("Content-Length") or 0), 65536)
        body = self.rfile.read(length)
        try:
            data = json.loads(body)
            cb = data["Body"]["stkCallback"]
            summary = f"ResultCode={cb.get('ResultCode')} ResultDesc={cb.get('ResultDesc')!r}"
        except Exception:
            summary = "unparseable body"
        request = urllib.request.Request(
            UPSTREAM + path, data=body, method="POST",
            headers={"Content-Type": "application/json", "X-Forwarded-Proto": "https"},
        )
        try:
            with urllib.request.urlopen(request, timeout=20) as response:
                status, answer = response.status, response.read()
        except urllib.error.HTTPError as e:
            status, answer = e.code, e.read()
        except Exception as e:  # noqa: BLE001
            status, answer = 502, str(e).encode()
        self.note(f"FORWARDED POST {masked} -> {status} | {summary}")
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.end_headers()
        self.wfile.write(answer)

    def do_GET(self):
        self.note(f"BLOCKED GET {re.sub(r'[0-9a-f]{32}', '<token>', self.path.split('?')[0])}")
        self.send_response(404)
        self.end_headers()

    do_PUT = do_DELETE = do_HEAD = do_PATCH = do_GET


if __name__ == "__main__":
    http.server.ThreadingHTTPServer(("127.0.0.1", PORT), Handler).serve_forever()
