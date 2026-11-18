from __future__ import annotations

import shutil
from datetime import date, datetime, time
from pathlib import Path

from .model import Entry, parse_filename

TEMPLATES_DIR = ".templates"


def assets_dirname(day: date) -> str:
    return f"{day.isoformat()}-assets"


class JournalStore:
    def __init__(self, root: str | Path):
        self.root = Path(root).expanduser()

    def init(self) -> None:
        (self.root / TEMPLATES_DIR).mkdir(parents=True, exist_ok=True)

    @property
    def exists(self) -> bool:
        return self.root.is_dir()

    def _iter_entry_paths(self):
        if not self.exists:
            return
        for path in sorted(self.root.glob("*.md")):
            if ".conflict-" in path.name:
                continue
            try:
                parse_filename(path.name)
            except ValueError:
                continue
            yield path

    def list_entries(self) -> list[Entry]:
        entries = [
            Entry.from_markdown(path.read_text(encoding="utf-8"), path)
            for path in self._iter_entry_paths()
        ]
        entries.sort(key=lambda entry: entry.sort_key, reverse=True)
        return entries

    def list_days(self) -> list[date]:
        return sorted({entry.date for entry in self.list_entries()}, reverse=True)

    def entries_for_day(self, day: date) -> list[Entry]:
        return [entry for entry in self.list_entries() if entry.date == day]

    def get_entry(self, day: date, at: time | None = None) -> Entry | None:
        for entry in self.entries_for_day(day):
            if entry.time == at:
                return entry
        return None

    def save_entry(self, entry: Entry) -> Path:
        self.root.mkdir(parents=True, exist_ok=True)
        now = datetime.now().astimezone()
        if entry.created is None:
            entry.created = now
        entry.modified = now
        path = self.root / entry.filename
        entry.path = path
        path.write_text(entry.to_markdown(), encoding="utf-8")
        return path

    def delete_entry(self, day: date, at: time | None = None) -> bool:
        entry = self.get_entry(day, at)
        if entry is None or entry.path is None:
            return False
        entry.path.unlink()
        return True

    def add_attachment(self, day: date, source: str | Path) -> str:
        src = Path(source).expanduser()
        if not src.is_file():
            raise FileNotFoundError(src)
        assets = self.root / assets_dirname(day)
        assets.mkdir(parents=True, exist_ok=True)
        target = assets / src.name
        counter = 1
        while target.exists():
            target = assets / f"{src.stem}-{counter}{src.suffix}"
            counter += 1
        shutil.copy2(src, target)
        return f"{assets_dirname(day)}/{target.name}"

    def attachments_for_day(self, day: date) -> list[Path]:
        assets = self.root / assets_dirname(day)
        if not assets.is_dir():
            return []
        return sorted(path for path in assets.iterdir() if path.is_file())

    def add_template(self, source: str | Path) -> str:
        src = Path(source).expanduser()
        if not src.is_file():
            raise FileNotFoundError(src)
        templates = self.root / TEMPLATES_DIR
        templates.mkdir(parents=True, exist_ok=True)
        name = src.name if src.name.endswith(".md") else f"{src.name}.md"
        (templates / name).write_text(src.read_text(encoding="utf-8"), encoding="utf-8")
        return name

    def list_templates(self) -> list[str]:
        templates = self.root / TEMPLATES_DIR
        if not templates.is_dir():
            return []
        return sorted(path.name for path in templates.glob("*.md"))

    def get_template(self, name: str) -> str:
        path = self.root / TEMPLATES_DIR / name
        if not path.is_file():
            raise FileNotFoundError(path)
        return path.read_text(encoding="utf-8")
