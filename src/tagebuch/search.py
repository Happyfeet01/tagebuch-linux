from __future__ import annotations

from dataclasses import dataclass
from datetime import date

from .model import Entry
from .store import JournalStore


@dataclass
class SearchFilter:
    query: str = ""
    tag: str | None = None
    since: date | None = None
    until: date | None = None
    min_rating: int | None = None


def search_entries(store: JournalStore, filt: SearchFilter) -> list[Entry]:
    query = filt.query.casefold().strip()
    result: list[Entry] = []
    for entry in store.list_entries():
        if filt.since is not None and entry.date < filt.since:
            continue
        if filt.until is not None and entry.date > filt.until:
            continue
        if filt.tag is not None:
            tags = [tag.casefold() for tag in entry.tags]
            if filt.tag.casefold() not in tags:
                continue
        if filt.min_rating is not None and (entry.rating is None or entry.rating < filt.min_rating):
            continue
        if query:
            haystack = "\n".join(
                [
                    entry.heading,
                    entry.body,
                    " ".join(entry.tags),
                    " ".join(f"{k} {v}" for k, v in entry.trackers.items()),
                    entry.location.name if entry.location else "",
                ]
            ).casefold()
            if query not in haystack:
                continue
        result.append(entry)
    return result


def all_tags(store: JournalStore) -> list[str]:
    tags: set[str] = set()
    for entry in store.list_entries():
        tags.update(entry.tags)
    return sorted(tags, key=str.casefold)
