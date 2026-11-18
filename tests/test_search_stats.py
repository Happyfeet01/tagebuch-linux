from datetime import date, time

import pytest

from tagebuch.model import Entry, Location
from tagebuch.search import SearchFilter, all_tags, search_entries
from tagebuch.stats import compute_stats, on_this_day
from tagebuch.store import JournalStore


@pytest.fixture
def store(tmp_path):
    s = JournalStore(tmp_path / "tb")
    s.init()
    s.save_entry(Entry(date=date(2026, 11, 18), time=time(20, 0), body="Spaziergang im Park",
                       heading="Abend", rating=4, tags=["Familie", "Natur"],
                       trackers={"Schlaf": 7}, location=Location(name="Zuerich")))
    s.save_entry(Entry(date=date(2026, 11, 17), body="Arbeitstag mit Meeting", rating=2,
                       tags=["Arbeit"]))
    s.save_entry(Entry(date=date(2025, 11, 18), body="Vor einem Jahr: Geburtstag", rating=5,
                       tags=["Familie"]))
    return s


def test_search_fulltext(store):
    result = search_entries(store, SearchFilter(query="spaziergang"))
    assert len(result) == 1 and result[0].date == date(2026, 11, 18)


def test_search_is_case_insensitive_and_searches_tags(store):
    assert len(search_entries(store, SearchFilter(query="FAMILIE"))) == 2
    assert len(search_entries(store, SearchFilter(query="meeting"))) == 1


def test_search_filter_tag(store):
    result = search_entries(store, SearchFilter(tag="arbeit"))
    assert len(result) == 1 and result[0].date == date(2026, 11, 17)


def test_search_filter_date_range_and_rating(store):
    result = search_entries(store, SearchFilter(since=date(2026, 1, 1), until=date(2026, 12, 31)))
    assert len(result) == 2
    result = search_entries(store, SearchFilter(min_rating=4))
    assert {e.date for e in result} == {date(2026, 11, 18), date(2025, 11, 18)}


def test_all_tags(store):
    assert all_tags(store) == ["Arbeit", "Familie", "Natur"]


def test_stats(store):
    stats = compute_stats(store.list_entries(), today=date(2026, 11, 18))
    assert stats["total_entries"] == 3
    assert stats["total_days"] == 3
    assert stats["current_streak"] == 2
    assert stats["longest_streak"] == 2
    assert stats["per_month"] == {"2025-11": 1, "2026-11": 2}
    assert stats["average_rating"] == pytest.approx((4 + 2 + 5) / 3, abs=0.01)
    assert stats["total_words"] > 0


def test_streak_when_today_missing(store):
    stats = compute_stats(store.list_entries(), today=date(2026, 11, 19))
    assert stats["current_streak"] == 2
    stats = compute_stats(store.list_entries(), today=date(2026, 11, 20))
    assert stats["current_streak"] == 0


def test_on_this_day(store):
    result = on_this_day(store.list_entries(), today=date(2026, 11, 18))
    assert len(result) == 1
    assert result[0].date == date(2025, 11, 18)
