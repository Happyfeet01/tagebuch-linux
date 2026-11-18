from __future__ import annotations

import re
from datetime import date, datetime, time, timedelta

TIME_RE = re.compile(r"^(\d{1,2}):(\d{2})$")


def parse_time(value: str) -> time:
    match = TIME_RE.match(value.strip())
    if not match:
        raise ValueError(f"Ungueltige Uhrzeit: {value!r} (erwartet HH:MM)")
    hour, minute = int(match.group(1)), int(match.group(2))
    if not (0 <= hour <= 23 and 0 <= minute <= 59):
        raise ValueError(f"Uhrzeit ausserhalb des gueltigen Bereichs: {value!r}")
    return time(hour, minute)


def next_fire(now: datetime, time_str: str) -> datetime:
    at = parse_time(time_str)
    candidate = now.replace(hour=at.hour, minute=at.minute, second=0, microsecond=0)
    if candidate <= now:
        candidate += timedelta(days=1)
    return candidate


def should_fire(now: datetime, time_str: str, last_fired: date | None,
                has_entry_today: bool, remind_anyway: bool = False) -> bool:
    at = parse_time(time_str)
    if now.time() < at:
        return False
    if last_fired == now.date():
        return False
    return not (has_entry_today and not remind_anyway)
