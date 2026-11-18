from __future__ import annotations

import csv
import html
import json
import re
from datetime import date, datetime, time
from pathlib import Path

from .model import FILENAME_RE, Entry, Location
from .store import JournalStore

EXPORT_FORMAT = 1


def stars(rating: int | None) -> str:
    if rating is None:
        return ""
    return "★" * rating + "☆" * (5 - rating)


def _entry_to_dict(entry: Entry) -> dict:
    return {
        "date": entry.date.isoformat(),
        "time": entry.time.strftime("%H:%M") if entry.time else None,
        "heading": entry.heading,
        "body": entry.body,
        "rating": entry.rating,
        "tags": list(entry.tags),
        "trackers": dict(entry.trackers),
        "location": entry.location.to_dict() if entry.location else None,
        "attachments": list(entry.attachments),
        "created": entry.created.isoformat() if entry.created else None,
        "modified": entry.modified.isoformat() if entry.modified else None,
    }


def _entry_from_dict(data: dict) -> Entry:
    raw_date = data.get("date")
    if not raw_date:
        raise ValueError("Eintrag ohne Datum im JSON")
    entry_date = date.fromisoformat(str(raw_date)[:10])
    raw_time = data.get("time")
    entry_time = time.fromisoformat(str(raw_time)) if raw_time else None
    location = data.get("location")
    rating = data.get("rating")
    created = data.get("created")
    modified = data.get("modified")
    return Entry(
        date=entry_date,
        time=entry_time,
        heading=str(data.get("heading", "") or ""),
        body=str(data.get("body", data.get("text", "")) or ""),
        rating=int(rating) if rating is not None else None,
        tags=[str(t) for t in (data.get("tags") or [])],
        trackers=dict(data.get("trackers") or {}),
        location=Location.from_dict(location) if location else None,
        attachments=[str(a) for a in (data.get("attachments") or [])],
        created=datetime.fromisoformat(str(created)) if created else None,
        modified=datetime.fromisoformat(str(modified)) if modified else None,
    )


def export_json(store: JournalStore, dest: str | Path) -> int:
    entries = sorted(store.list_entries(), key=lambda e: e.sort_key)
    payload = {
        "app": "tagebuch-linux",
        "format": EXPORT_FORMAT,
        "exported_at": datetime.now().astimezone().isoformat(),
        "entries": [_entry_to_dict(e) for e in entries],
    }
    text = json.dumps(payload, indent=2, ensure_ascii=False) + "\n"
    Path(dest).write_text(text, encoding="utf-8")
    return len(entries)


def import_json(store: JournalStore, src: str | Path) -> int:
    payload = json.loads(Path(src).read_text(encoding="utf-8"))
    entries = payload.get("entries", payload) if isinstance(payload, dict) else payload
    count = 0
    for item in entries:
        store.save_entry(_entry_from_dict(item))
        count += 1
    return count


def _parse_date_flexible(value: str) -> date:
    value = value.strip()
    try:
        return date.fromisoformat(value)
    except ValueError:
        pass
    match = re.match(r"^(\d{1,2})\.(\d{1,2})\.(\d{4})$", value)
    if match:
        return date(int(match.group(3)), int(match.group(2)), int(match.group(1)))
    raise ValueError(f"Ungueltiges Datum: {value!r}")


def import_csv(store: JournalStore, src: str | Path) -> int:
    count = 0
    with open(src, newline="", encoding="utf-8-sig") as handle:
        reader = csv.DictReader(handle)
        for row in reader:
            lowered = { (k or "").strip().casefold(): v for k, v in row.items() }
            raw_date = lowered.get("date") or lowered.get("datum")
            if not raw_date:
                continue
            raw_time = lowered.get("time") or lowered.get("zeit") or ""
            raw_tags = lowered.get("tags") or ""
            raw_rating = (lowered.get("rating") or lowered.get("bewertung") or "").strip()
            entry = Entry(
                date=_parse_date_flexible(raw_date),
                time=time.fromisoformat(raw_time.strip()) if raw_time.strip() else None,
                heading=(
                    lowered.get("heading") or lowered.get("ueberschrift")
                    or lowered.get("titel") or ""
                ).strip(),
                body=(lowered.get("text") or lowered.get("body") or "").rstrip("\n"),
                rating=int(raw_rating) if raw_rating.isdigit() else None,
                tags=[t.strip() for t in re.split(r"[;,]", raw_tags) if t.strip()],
            )
            store.save_entry(entry)
            count += 1
    return count


def import_markdown_dir(store: JournalStore, src_dir: str | Path) -> int:
    src_dir = Path(src_dir)
    count = 0
    for path in sorted(src_dir.glob("*.md")):
        if ".conflict-" in path.name or not FILENAME_RE.match(path.name):
            continue
        entry = Entry.from_markdown(path.read_text(encoding="utf-8"), path)
        store.save_entry(entry)
        count += 1
    return count


def export_txt(store: JournalStore, dest: str | Path) -> int:
    entries = sorted(store.list_entries(), key=lambda e: e.sort_key, reverse=True)
    lines: list[str] = []
    for entry in entries:
        stamp = entry.date.isoformat()
        if entry.time:
            stamp += f" {entry.time.strftime('%H:%M')}"
        title = f" – {entry.heading}" if entry.heading else ""
        lines.append(f"=== {stamp}{title} ===")
        meta = []
        if entry.rating is not None:
            meta.append(f"Bewertung: {stars(entry.rating)} ({entry.rating}/5)")
        if entry.tags:
            meta.append(f"Tags: {', '.join(entry.tags)}")
        if entry.location and entry.location.name:
            meta.append(f"Ort: {entry.location.name}")
        if entry.trackers:
            meta.append("Tracker: " + ", ".join(f"{k}={v}" for k, v in entry.trackers.items()))
        if meta:
            lines.append(" | ".join(meta))
        lines.append("-" * 60)
        lines.append(entry.body)
        lines.append("")
    Path(dest).write_text("\n".join(lines), encoding="utf-8")
    return len(entries)


def export_markdown(store: JournalStore, dest: str | Path) -> int:
    entries = sorted(store.list_entries(), key=lambda e: e.sort_key, reverse=True)
    parts: list[str] = ["# Tagebuch\n"]
    for entry in entries:
        stamp = entry.date.isoformat()
        if entry.time:
            stamp += f" {entry.time.strftime('%H:%M')}"
        parts.append(f"## {stamp}\n")
        if entry.rating is not None:
            parts.append(f"Bewertung: {stars(entry.rating)}  ")
        if entry.tags:
            parts.append(f"Tags: {', '.join(f'#{t}' for t in entry.tags)}  ")
        parts.append("")
        if entry.heading:
            parts.append(f"### {entry.heading}\n")
        parts.append(entry.body + "\n")
    Path(dest).write_text("\n".join(parts), encoding="utf-8")
    return len(entries)


_LIST_RE = re.compile(r"^[-*]\s+")


def _inline(text: str) -> str:
    escaped = html.escape(text)
    escaped = re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", escaped)
    escaped = re.sub(r"(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)", r"<em>\1</em>", escaped)
    escaped = re.sub(r"`(.+?)`", r"<code>\1</code>", escaped)
    escaped = re.sub(r"\[([^\]]+)\]\(([^)\s]+)\)", r'<a href="\2">\1</a>', escaped)
    return escaped


def render_markdown(text: str) -> str:
    out: list[str] = []
    paragraph: list[str] = []
    in_list = False

    def flush_paragraph() -> None:
        nonlocal paragraph
        if paragraph:
            out.append("<p>" + " ".join(paragraph) + "</p>")
            paragraph = []

    def flush_list() -> None:
        nonlocal in_list
        if in_list:
            out.append("</ul>")
            in_list = False

    for raw_line in text.split("\n"):
        line = raw_line.rstrip()
        heading = re.match(r"^(#{1,6})\s+(.*)$", line)
        if heading:
            flush_paragraph()
            flush_list()
            level = len(heading.group(1))
            out.append(f"<h{level}>{_inline(heading.group(2))}</h{level}>")
            continue
        if _LIST_RE.match(line):
            flush_paragraph()
            if not in_list:
                out.append("<ul>")
                in_list = True
            out.append(f"<li>{_inline(_LIST_RE.sub('', line))}</li>")
            continue
        if not line.strip():
            flush_paragraph()
            flush_list()
            continue
        flush_list()
        paragraph.append(_inline(line))
    flush_paragraph()
    flush_list()
    return "\n".join(out)


_HTML_TEMPLATE = """<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Tagebuch</title>
<style>
body {{ font-family: sans-serif; max-width: 46em; margin: 2em auto; padding: 0 1em;
line-height: 1.5; }}
.entry {{ border-bottom: 1px solid #ccc; padding: 1em 0; }}
.meta {{ color: #666; font-size: 0.9em; }}
.stars {{ color: #e6a700; }}
h1 {{ font-size: 1.4em; }}
</style>
</head>
<body>
<h1>Tagebuch</h1>
{entries}
</body>
</html>
"""


def export_html(store: JournalStore, dest: str | Path) -> int:
    entries = sorted(store.list_entries(), key=lambda e: e.sort_key, reverse=True)
    blocks: list[str] = []
    for entry in entries:
        stamp = entry.date.isoformat()
        if entry.time:
            stamp += f" {entry.time.strftime('%H:%M')}"
        meta = [f"<span class='meta'>{html.escape(stamp)}</span>"]
        if entry.rating is not None:
            meta.append(f"<span class='stars'>{stars(entry.rating)}</span>")
        if entry.tags:
            meta.append(f"<span class='meta'>Tags: {html.escape(', '.join(entry.tags))}</span>")
        if entry.location and entry.location.name:
            meta.append(f"<span class='meta'>Ort: {html.escape(entry.location.name)}</span>")
        heading = f"<h2>{html.escape(entry.heading)}</h2>" if entry.heading else ""
        blocks.append(
            "<div class='entry'>"
            + " ".join(meta)
            + heading
            + render_markdown(entry.body)
            + "</div>"
        )
    Path(dest).write_text(_HTML_TEMPLATE.format(entries="\n".join(blocks)), encoding="utf-8")
    return len(entries)
