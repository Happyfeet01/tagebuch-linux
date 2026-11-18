from __future__ import annotations

import xml.etree.ElementTree as ET
from dataclasses import dataclass
from datetime import datetime
from email.utils import parsedate_to_datetime
from pathlib import PurePosixPath
from urllib.parse import unquote, urlparse

import requests

DAV = "{DAV:}"
USER_AGENT = "tagebuch-linux/0.1"

PROPFIND_BODY = (
    '<?xml version="1.0" encoding="utf-8"?>'
    '<d:propfind xmlns:d="DAV:"><d:prop>'
    "<d:getetag/><d:getlastmodified/><d:getcontentlength/><d:resourcetype/>"
    "</d:prop></d:propfind>"
)


class WebDAVError(Exception):
    pass


@dataclass
class RemoteItem:
    path: str
    is_dir: bool
    etag: str
    last_modified: datetime | None
    size: int


def _href_to_path(href: str, base_prefix: str) -> str:
    parsed = urlparse(href)
    raw = parsed.path if parsed.scheme else href
    path = unquote(raw)
    if base_prefix and path.startswith(base_prefix):
        path = path[len(base_prefix):]
    return path.lstrip("/").rstrip("/")


class WebDAVClient:
    def __init__(self, base_url: str, username: str, password: str, timeout: int = 30):
        self.base_url = base_url.rstrip("/")
        self._base_prefix = urlparse(self.base_url).path.rstrip("/")
        self.session = requests.Session()
        self.session.auth = (username, password)
        self.session.headers["User-Agent"] = USER_AGENT
        self.timeout = timeout

    def _url(self, path: str = "") -> str:
        rel = PurePosixPath(path).as_posix().strip("/") if path else ""
        if not rel:
            return self.base_url + "/"
        return f"{self.base_url}/{rel}"

    def _parse_multistatus(self, xml_text: str) -> list[RemoteItem]:
        root = ET.fromstring(xml_text)
        items: list[RemoteItem] = []
        for response in root.findall(f"{DAV}response"):
            href = response.findtext(f"{DAV}href", "")
            rel = _href_to_path(href, self._base_prefix)
            propstat = response.find(f"{DAV}propstat")
            if propstat is None:
                continue
            status = propstat.findtext(f"{DAV}status", "")
            if " 200 " not in f" {status} ":
                continue
            prop = propstat.find(f"{DAV}prop")
            if prop is None:
                continue
            resourcetype = prop.find(f"{DAV}resourcetype")
            is_dir = (
                resourcetype is not None
                and resourcetype.find(f"{DAV}collection") is not None
            )
            etag = (prop.findtext(f"{DAV}getetag") or "").strip('"')
            lastmod_raw = prop.findtext(f"{DAV}getlastmodified")
            last_modified = None
            if lastmod_raw:
                try:
                    last_modified = parsedate_to_datetime(lastmod_raw)
                except (TypeError, ValueError):
                    last_modified = None
            size_raw = prop.findtext(f"{DAV}getcontentlength") or "0"
            size = int(size_raw) if size_raw.isdigit() else 0
            items.append(RemoteItem(rel, is_dir, etag, last_modified, size))
        return items

    def list(self, path: str = "", depth: int = 1) -> list[RemoteItem]:
        resp = self.session.request(
            "PROPFIND",
            self._url(path),
            data=PROPFIND_BODY.encode("utf-8"),
            headers={"Depth": str(depth), "Content-Type": "application/xml"},
            timeout=self.timeout,
        )
        if resp.status_code not in (200, 207):
            raise WebDAVError(f"PROPFIND {path!r}: HTTP {resp.status_code}")
        return self._parse_multistatus(resp.text)

    def list_tree(self, path: str = "") -> dict[str, RemoteItem]:
        result: dict[str, RemoteItem] = {}

        def walk(current: str) -> None:
            for item in self.list(current, depth=1):
                if not item.path or item.path == current:
                    continue
                result[item.path] = item
                if item.is_dir:
                    walk(item.path)

        walk(path)
        return result

    def download(self, path: str) -> bytes:
        resp = self.session.get(self._url(path), timeout=self.timeout)
        if resp.status_code != 200:
            raise WebDAVError(f"GET {path!r}: HTTP {resp.status_code}")
        return resp.content

    def upload(self, path: str, data: bytes, etag: str | None = None) -> str:
        headers = {}
        if etag:
            headers["If-Match"] = f'"{etag}"'
        resp = self.session.put(self._url(path), data=data, headers=headers, timeout=self.timeout)
        if resp.status_code not in (200, 201, 204):
            raise WebDAVError(f"PUT {path!r}: HTTP {resp.status_code}")
        return resp.headers.get("ETag", "").strip('"')

    def mkcol(self, path: str) -> None:
        resp = self.session.request("MKCOL", self._url(path), timeout=self.timeout)
        if resp.status_code not in (200, 201, 204, 405):
            raise WebDAVError(f"MKCOL {path!r}: HTTP {resp.status_code}")

    def ensure_dir(self, path: str) -> None:
        parts = [part for part in PurePosixPath(path).parts if part not in ("/", ".")]
        current = ""
        for part in parts:
            current = f"{current}/{part}" if current else part
            self.mkcol(current)

    def delete(self, path: str) -> None:
        resp = self.session.delete(self._url(path), timeout=self.timeout)
        if resp.status_code not in (200, 204, 404):
            raise WebDAVError(f"DELETE {path!r}: HTTP {resp.status_code}")
