from __future__ import annotations

import hashlib
import json
import socket
from dataclasses import dataclass, field
from pathlib import Path

from .store import JournalStore
from .webdav import RemoteItem, WebDAVClient


@dataclass
class SyncResult:
    uploaded: list[str] = field(default_factory=list)
    downloaded: list[str] = field(default_factory=list)
    deleted_remote: list[str] = field(default_factory=list)
    deleted_local: list[str] = field(default_factory=list)
    conflicts: list[str] = field(default_factory=list)
    errors: list[str] = field(default_factory=list)


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with open(path, "rb") as handle:
        for chunk in iter(lambda: handle.read(65536), b""):
            digest.update(chunk)
    return digest.hexdigest()


def is_conflict_name(name: str) -> bool:
    return ".conflict-" in name


def conflict_name(rel: str, host: str) -> str:
    path = Path(rel)
    return f"{path.stem}.conflict-{host}{path.suffix}"


class SyncEngine:
    def __init__(self, store: JournalStore, client: WebDAVClient, state_path: str | Path):
        self.store = store
        self.client = client
        self.state_path = Path(state_path)
        self.host = socket.gethostname() or "localhost"

    def _local_files(self) -> dict[str, Path]:
        result: dict[str, Path] = {}
        root = self.store.root
        if not root.is_dir():
            return result
        for path in sorted(root.rglob("*")):
            if not path.is_file() or is_conflict_name(path.name):
                continue
            result[path.relative_to(root).as_posix()] = path
        return result

    def _load_state(self) -> dict:
        if self.state_path.is_file():
            try:
                loaded = json.loads(self.state_path.read_text(encoding="utf-8"))
                if isinstance(loaded, dict):
                    return loaded
            except (OSError, json.JSONDecodeError):
                pass
        return {}

    def _save_state(self, state: dict) -> None:
        self.state_path.parent.mkdir(parents=True, exist_ok=True)
        self.state_path.write_text(
            json.dumps(state, indent=1, sort_keys=True) + "\n", encoding="utf-8"
        )

    def _upload(self, rel: str, local_path: Path, old: dict | None, result: SyncResult,
                new_state: dict, force: bool = False) -> None:
        parent = str(Path(rel).parent)
        if parent != ".":
            self.client.ensure_dir(parent)
        etag = None if (force or old is None) else old.get("etag") or None
        new_etag = self.client.upload(rel, local_path.read_bytes(), etag=etag) or (
            old.get("etag") if old else ""
        )
        new_state[rel] = {"etag": new_etag, "sha256": sha256_file(local_path)}
        result.uploaded.append(rel)

    def _download(self, rel: str, item: RemoteItem, result: SyncResult, new_state: dict) -> None:
        data = self.client.download(rel)
        target = self.store.root / rel
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(data)
        new_state[rel] = {"etag": item.etag, "sha256": sha256_file(target)}
        result.downloaded.append(rel)

    def _handle_conflict(self, rel: str, local_path: Path, item: RemoteItem,
                         result: SyncResult, new_state: dict) -> None:
        remote_data = self.client.download(rel)
        conflict_rel = conflict_name(rel, self.host)
        conflict_path = self.store.root / conflict_rel
        conflict_path.parent.mkdir(parents=True, exist_ok=True)
        conflict_path.write_bytes(remote_data)
        self._upload(rel, local_path, None, result, new_state, force=True)
        result.conflicts.append(rel)

    def sync(self) -> SyncResult:
        result = SyncResult()
        state = self._load_state()
        try:
            remote = self.client.list_tree()
        except Exception as exc:
            result.errors.append(f"Remote nicht erreichbar: {exc}")
            return result
        local = self._local_files()
        new_state: dict = {}

        for rel in sorted(set(remote) | set(local) | set(state)):
            item = remote.get(rel)
            local_path = local.get(rel)
            old = state.get(rel)
            if item is not None and item.is_dir:
                continue
            remote_changed = item is not None and (old is None or item.etag != old.get("etag"))
            local_hash = sha256_file(local_path) if local_path is not None else None
            local_changed = local_path is not None and (
                old is None or local_hash != old.get("sha256")
            )
            local_deleted = local_path is None and old is not None
            remote_deleted = item is None and old is not None
            try:
                if local_path is not None and item is not None:
                    if local_changed and remote_changed:
                        self._handle_conflict(rel, local_path, item, result, new_state)
                    elif local_changed:
                        self._upload(rel, local_path, old, result, new_state)
                    elif remote_changed:
                        self._download(rel, item, result, new_state)
                    else:
                        new_state[rel] = {"etag": item.etag, "sha256": local_hash}
                elif local_path is not None:
                    if remote_deleted and not local_changed:
                        local_path.unlink()
                        result.deleted_local.append(rel)
                    else:
                        self._upload(rel, local_path, old, result, new_state)
                elif item is not None:
                    if local_deleted and not remote_changed:
                        self.client.delete(rel)
                        result.deleted_remote.append(rel)
                    else:
                        self._download(rel, item, result, new_state)
            except Exception as exc:
                result.errors.append(f"{rel}: {exc}")

        self._save_state(new_state)
        return result
