from __future__ import annotations

import email.utils
import hashlib
import shutil
import threading
from datetime import UTC, datetime
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import quote, unquote, urlparse

DEFAULT_PREFIX = "/remote.php/dav/files/testuser/Tagebuch"


class FakeWebDAVServer:
    def __init__(self, root: Path, prefix: str = DEFAULT_PREFIX):
        self.root = Path(root)
        self.prefix = prefix
        self.root.mkdir(parents=True, exist_ok=True)
        server = self

        class Handler(BaseHTTPRequestHandler):
            def log_message(self, *args):
                pass

            def _rel(self) -> str:
                path = unquote(urlparse(self.path).path)
                if path.startswith(server.prefix):
                    path = path[len(server.prefix):]
                return path.lstrip("/")

            def _fs(self) -> Path:
                rel = self._rel()
                return server.root / rel if rel else server.root

            def _etag(self, fs: Path) -> str:
                if fs.is_dir():
                    return ""
                return hashlib.sha256(fs.read_bytes()).hexdigest()

            def _prop_entry(self, name: str, fs: Path, is_dir: bool) -> str:
                href = server.prefix + "/" + quote(name)
                etag = "" if is_dir else self._etag(fs)
                lastmod = email.utils.format_datetime(
                    datetime.fromtimestamp(fs.stat().st_mtime, UTC), usegmt=True
                )
                size = 0 if is_dir else fs.stat().st_size
                collection = "<D:collection/>" if is_dir else ""
                return (
                    "<D:response>"
                    f"<D:href>{href}</D:href>"
                    "<D:propstat><D:prop>"
                    f'<D:getetag>"{etag}"</D:getetag>'
                    f"<D:getlastmodified>{lastmod}</D:getlastmodified>"
                    f"<D:getcontentlength>{size}</D:getcontentlength>"
                    f"<D:resourcetype>{collection}</D:resourcetype>"
                    "</D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat>"
                    "</D:response>"
                )

            def _respond(self, code: int, body: bytes, content_type: str = "text/plain"):
                self.send_response(code)
                self.send_header("Content-Type", content_type)
                self.send_header("Content-Length", str(len(body)))
                self.end_headers()
                self.wfile.write(body)

            def do_PROPFIND(self):
                fs = self._fs()
                if not fs.exists():
                    self.send_error(404)
                    return
                depth = self.headers.get("Depth", "1")
                base_rel = self._rel()
                prefix_rel = base_rel + "/" if base_rel else ""
                entries = []
                if fs.is_dir():
                    entries.append(self._prop_entry(prefix_rel, fs, True))
                    if depth == "1":
                        for child in sorted(fs.iterdir()):
                            name = prefix_rel + child.name + ("/" if child.is_dir() else "")
                            entries.append(self._prop_entry(name, child, child.is_dir()))
                else:
                    entries.append(self._prop_entry(prefix_rel.rstrip("/"), fs, False))
                body = (
                    "<?xml version='1.0' encoding='utf-8'?>\n"
                    '<D:multistatus xmlns:D="DAV:">' + "".join(entries) + "</D:multistatus>"
                )
                self._respond(207, body.encode("utf-8"), "application/xml; charset=utf-8")

            def do_GET(self):
                fs = self._fs()
                if not fs.is_file():
                    self.send_error(404)
                    return
                data = fs.read_bytes()
                self.send_response(200)
                self.send_header("Content-Length", str(len(data)))
                self.send_header("ETag", f'"{self._etag(fs)}"')
                self.send_header("Content-Type", "application/octet-stream")
                self.end_headers()
                self.wfile.write(data)

            def do_PUT(self):
                fs = self._fs()
                if fs.is_dir():
                    self.send_error(405)
                    return
                fs.parent.mkdir(parents=True, exist_ok=True)
                if_match = self.headers.get("If-Match")
                if if_match:
                    if fs.exists():
                        if if_match.strip('"') != self._etag(fs):
                            self.send_error(412)
                            return
                    elif if_match != "*":
                        self.send_error(412)
                        return
                length = int(self.headers.get("Content-Length", 0))
                data = self.rfile.read(length)
                fs.write_bytes(data)
                self.send_response(201)
                self.send_header("ETag", f'"{self._etag(fs)}"')
                self.send_header("Content-Length", "0")
                self.end_headers()

            def do_MKCOL(self):
                fs = self._fs()
                if fs.exists():
                    self.send_error(405)
                    return
                fs.mkdir(parents=True)
                self.send_response(201)
                self.send_header("Content-Length", "0")
                self.end_headers()

            def do_DELETE(self):
                fs = self._fs()
                if not fs.exists():
                    self.send_error(404)
                    return
                if fs.is_dir():
                    shutil.rmtree(fs)
                else:
                    fs.unlink()
                self.send_response(204)
                self.send_header("Content-Length", "0")
                self.end_headers()

        self.httpd = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
        self.thread = threading.Thread(target=self.httpd.serve_forever, daemon=True)

    @property
    def url(self) -> str:
        host, port = self.httpd.server_address
        return f"http://{host}:{port}{self.prefix}"

    def start(self) -> FakeWebDAVServer:
        self.thread.start()
        return self

    def stop(self) -> None:
        self.httpd.shutdown()
        self.httpd.server_close()
        self.thread.join(timeout=5)
