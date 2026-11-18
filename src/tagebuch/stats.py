from __future__ import annotations

from collections import Counter
from datetime import date, timedelta

from .model import Entry


def compute_stats(entries: list[Entry], today: date | None = None) -> dict:
    today = today or date.today()
    days = {entry.date for entry in entries}
    ratings = [(entry.date, entry.rating) for entry in entries if entry.rating is not None]
    per_month = Counter(entry.date.strftime("%Y-%m") for entry in entries)
    return {
        "total_entries": len(entries),
        "total_days": len(days),
        "total_words": sum(entry.word_count() for entry in entries),
        "current_streak": _current_streak(days, today),
        "longest_streak": _longest_streak(days),
        "per_month": dict(sorted(per_month.items())),
        "average_rating": round(sum(r for _, r in ratings) / len(ratings), 2) if ratings else None,
        "ratings": sorted(ratings),
        "days": sorted(days),
    }


def _current_streak(days: set[date], today: date) -> int:
    streak = 0
    cursor = today if today in days else today - timedelta(days=1)
    while cursor in days:
        streak += 1
        cursor -= timedelta(days=1)
    return streak


def _longest_streak(days: set[date]) -> int:
    longest = 0
    run = 0
    previous: date | None = None
    for day in sorted(days):
        if previous is not None and (day - previous).days == 1:
            run += 1
        else:
            run = 1
        longest = max(longest, run)
        previous = day
    return longest


def on_this_day(entries: list[Entry], today: date | None = None) -> list[Entry]:
    today = today or date.today()
    return [
        entry
        for entry in entries
        if (entry.date.month, entry.date.day) == (today.month, today.day)
        and entry.date != today
    ]
