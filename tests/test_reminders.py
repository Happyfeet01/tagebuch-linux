from datetime import date, datetime, time

import pytest

from tagebuch.reminders import next_fire, parse_time, should_fire


def test_parse_time():
    assert parse_time("21:00") == time(21, 0)
    assert parse_time("7:05") == time(7, 5)
    with pytest.raises(ValueError):
        parse_time("25:00")
    with pytest.raises(ValueError):
        parse_time("abc")


def test_next_fire_today_or_tomorrow():
    now = datetime(2026, 11, 18, 10, 0)
    assert next_fire(now, "21:00") == datetime(2026, 11, 18, 21, 0)
    now = datetime(2026, 11, 18, 21, 0)
    assert next_fire(now, "21:00") == datetime(2026, 11, 19, 21, 0)
    now = datetime(2026, 11, 18, 22, 30)
    assert next_fire(now, "21:00") == datetime(2026, 11, 19, 21, 0)


def test_should_fire():
    now = datetime(2026, 11, 18, 21, 5)
    assert should_fire(now, "21:00", None, has_entry_today=False) is True
    assert should_fire(now, "21:00", date(2026, 11, 18), has_entry_today=False) is False
    assert should_fire(now, "21:00", None, has_entry_today=True) is False
    assert should_fire(now, "21:00", None, has_entry_today=True, remind_anyway=True) is True
    assert should_fire(datetime(2026, 11, 18, 20, 59), "21:00", None, False) is False
