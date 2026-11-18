from __future__ import annotations

import re
from dataclasses import dataclass, field
from datetime import date, datetime, time
from pathlib import Path

import yaml

FILENAME_RE = re.compile(r"^(\d{4})-(\d{2})-(\d{2})(?:_(\d{2})-(\d{2}))?\.md$")
FRONTMATTER_DELIM = "---"


def parse_filename(name: str) -> tuple[date, time | None]:
    match = FILENAME_RE.match(name)
    if not match:
        raise ValueError(f"Ungueltiger Eintrags-Dateiname: {name!r}")
    entry_date = date(int(match.group(1)), int(match.group(2)), int(match.group(3)))
    if match.group(4):
        return entry_date, time(int(match.group(4)), int(match.group(5)))
    return entry_date, None


def _as_float(value) -> float | None:
    if value is None or value == "":
        return None
    try:
        return float(value)
    except (TypeError, ValueError):
        return None


def _as_date(value) -> date | None:
    if isinstance(value, datetime):
        return value.date()
    if isinstance(value, date):
        return value
    try:
        return date.fromisoformat(str(value))
    except ValueError:
        return None


def _as_time(value) -> time | None:
    if isinstance(value, time):
        return value
    if isinstance(value, int):
        value = f"{value // 100:02d}:{value % 100:02d}"
    try:
        return time.fromisoformat(str(value))
    except ValueError:
        return None


def _as_datetime(value) -> datetime | None:
    if isinstance(value, datetime):
        return value
    if value is None:
        return None
    try:
        return datetime.fromisoformat(str(value))
    except ValueError:
        return None


@dataclass
class Location:
    name: str = ""
    lat: float | None = None
    lon: float | None = None

    def to_dict(self) -> dict:
        data: dict = {"name": self.name}
        if self.lat is not None:
            data["lat"] = self.lat
        if self.lon is not None:
            data["lon"] = self.lon
        return data

    @classmethod
    def from_dict(cls, data) -> Location:
        if not isinstance(data, dict):
            return cls()
        return cls(
            name=str(data.get("name", "") or ""),
            lat=_as_float(data.get("lat")),
            lon=_as_float(data.get("lon")),
        )


@dataclass
class Entry:
    date: date
    time: time | None = None
    heading: str = ""
    body: str = ""
    rating: int | None = None
    tags: list[str] = field(default_factory=list)
    trackers: dict = field(default_factory=dict)
    location: Location | None = None
    attachments: list[str] = field(default_factory=list)
    created: datetime | None = None
    modified: datetime | None = None
    path: Path | None = None

    def __post_init__(self) -> None:
        if self.rating is not None and not 1 <= int(self.rating) <= 5:
            raise ValueError("rating muss zwischen 1 und 5 liegen")
        self.tags = [str(tag) for tag in self.tags]

    @property
    def filename(self) -> str:
        base = self.date.isoformat()
        if self.time is not None:
            return f"{base}_{self.time.hour:02d}-{self.time.minute:02d}.md"
        return f"{base}.md"

    @property
    def sort_key(self) -> tuple[date, time]:
        return (self.date, self.time or time.min)

    def word_count(self) -> int:
        text = f"{self.heading} {self.body}"
        return len([word for word in text.split() if word.strip()])

    def to_markdown(self) -> str:
        meta: dict = {"version": 1, "date": self.date.isoformat()}
        if self.time is not None:
            meta["time"] = self.time.strftime("%H:%M")
        if self.created is not None:
            meta["created"] = self.created.isoformat()
        if self.modified is not None:
            meta["modified"] = self.modified.isoformat()
        if self.heading:
            meta["heading"] = self.heading
        if self.rating is not None:
            meta["rating"] = int(self.rating)
        if self.tags:
            meta["tags"] = list(self.tags)
        if self.trackers:
            meta["trackers"] = dict(self.trackers)
        if self.location is not None:
            meta["location"] = self.location.to_dict()
        if self.attachments:
            meta["attachments"] = list(self.attachments)
        front = yaml.safe_dump(meta, sort_keys=False, allow_unicode=True).strip()
        body = self.body.rstrip("\n")
        return f"{FRONTMATTER_DELIM}\n{front}\n{FRONTMATTER_DELIM}\n\n{body}\n"

    @classmethod
    def from_markdown(cls, text: str, path: Path | None = None) -> Entry:
        meta: dict = {}
        body = text
        stripped = text.lstrip("\n")
        if stripped.startswith(FRONTMATTER_DELIM):
            end = stripped.find(f"\n{FRONTMATTER_DELIM}", len(FRONTMATTER_DELIM))
            if end == -1:
                raise ValueError("Frontmatter nicht geschlossen (--- fehlt)")
            front = stripped[len(FRONTMATTER_DELIM):end]
            body = stripped[end + 1 + len(FRONTMATTER_DELIM):].strip("\n")
            loaded = yaml.safe_load(front)
            meta = loaded if isinstance(loaded, dict) else {}
        entry_date: date | None = None
        entry_time: time | None = None
        if path is not None:
            try:
                entry_date, entry_time = parse_filename(path.name)
            except ValueError:
                entry_date, entry_time = None, None
        raw_date = meta.get("date")
        if raw_date is not None:
            entry_date = _as_date(raw_date) or entry_date
        raw_time = meta.get("time")
        if raw_time is not None:
            entry_time = _as_time(raw_time) or entry_time
        if entry_date is None:
            raise ValueError("Eintrag hat kein Datum (Dateiname oder Frontmatter fehlt)")
        rating = meta.get("rating")
        location = meta.get("location")
        return cls(
            date=entry_date,
            time=entry_time,
            heading=str(meta.get("heading", "") or ""),
            body=body,
            rating=int(rating) if rating is not None else None,
            tags=list(meta.get("tags") or []),
            trackers=dict(meta.get("trackers") or {}),
            location=Location.from_dict(location) if location else None,
            attachments=[str(a) for a in (meta.get("attachments") or [])],
            created=_as_datetime(meta.get("created")),
            modified=_as_datetime(meta.get("modified")),
            path=path,
        )
