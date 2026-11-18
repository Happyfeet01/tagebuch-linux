from __future__ import annotations

import json
import os
from copy import deepcopy
from pathlib import Path

APP_NAME = "tagebuch-linux"

DEFAULTS: dict = {
    "journal_path": str(Path.home() / "Tagebuch"),
    "webdav": {
        "url": "",
        "username": "",
        "remote_path": "Tagebuch",
        "password": "",
        "use_keyring": False,
    },
    "reminder": {"enabled": False, "time": "21:00"},
    "lock": {"enabled": False, "pin_salt": "", "pin_hash": "", "auto_lock_minutes": 5},
    "appearance": {"theme": "system", "accent": "blue", "font": "Cantarell 11"},
    "sync": {"auto_sync": False},
}


def config_dir() -> Path:
    base = os.environ.get("XDG_CONFIG_HOME") or str(Path.home() / ".config")
    return Path(base) / APP_NAME


def _deep_merge(base: dict, override: dict) -> dict:
    result = deepcopy(base)
    for key, value in override.items():
        if key in result and isinstance(result[key], dict) and isinstance(value, dict):
            result[key] = _deep_merge(result[key], value)
        else:
            result[key] = value
    return result


def _keyring_available() -> bool:
    try:
        import keyring  # noqa: F401

        return True
    except Exception:
        return False


class Config:
    def __init__(self, path: Path | None = None):
        self.path = path or (config_dir() / "config.json")
        self.data: dict = deepcopy(DEFAULTS)
        self.load()

    def load(self) -> None:
        if self.path.is_file():
            loaded = json.loads(self.path.read_text(encoding="utf-8"))
            if isinstance(loaded, dict):
                self.data = _deep_merge(DEFAULTS, loaded)

    def save(self) -> None:
        self.path.parent.mkdir(parents=True, exist_ok=True)
        self.path.write_text(
            json.dumps(self.data, indent=2, ensure_ascii=False) + "\n", encoding="utf-8"
        )
        os.chmod(self.path, 0o600)

    def get(self, *args):
        if len(args) == 1:
            section, _, key = args[0].partition(".")
        else:
            section, key = args[0], args[1] if len(args) > 1 else ""
        value = self.data.get(section)
        if key and isinstance(value, dict):
            return value.get(key)
        return value

    def set(self, *args) -> None:
        if len(args) == 2:
            section, _, key = args[0].partition(".")
            if not key:
                self.data[section] = args[1]
                self.save()
                return
            value = args[1]
        else:
            section, key, value = args
        self.data.setdefault(section, {})[key] = value
        self.save()

    def webdav_password(self) -> str:
        webdav = self.data.get("webdav", {})
        if webdav.get("use_keyring") and _keyring_available():
            import keyring

            stored = keyring.get_password(APP_NAME, webdav.get("username", ""))
            if stored:
                return stored
        return webdav.get("password", "")

    def set_webdav_password(self, password: str) -> None:
        webdav = self.data.setdefault("webdav", {})
        username = webdav.get("username", "")
        stored = False
        if _keyring_available():
            try:
                import keyring

                keyring.set_password(APP_NAME, username, password)
                stored = True
            except Exception:
                stored = False
        webdav["use_keyring"] = stored
        if not stored:
            webdav["password"] = password
        self.save()
