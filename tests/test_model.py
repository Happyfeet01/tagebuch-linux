from datetime import date, datetime, time
from pathlib import Path

import pytest

from tagebuch.model import Entry, Location, parse_filename


def test_parse_filename_date_only():
    day, at = parse_filename("2026-11-18.md")
    assert day == date(2026, 11, 18)
    assert at is None


def test_parse_filename_with_time():
    day, at = parse_filename("2026-11-18_20-15.md")
    assert day == date(2026, 11, 18)
    assert at == time(20, 15)


def test_parse_filename_invalid():
    with pytest.raises(ValueError):
        parse_filename("nicht-ein-eintrag.md")
    with pytest.raises(ValueError):
        parse_filename("18-11-2026.md")


def test_entry_filename_roundtrip():
    entry = Entry(date=date(2026, 11, 18))
    assert entry.filename == "2026-11-18.md"
    entry2 = Entry(date=date(2026, 11, 18), time=time(9, 5))
    assert entry2.filename == "2026-11-18_09-05.md"
    day, at = parse_filename(entry2.filename)
    assert day == entry2.date and at == entry2.time


def test_markdown_roundtrip_full():
    entry = Entry(
        date=date(2026, 11, 18),
        time=time(20, 15),
        heading="Abendrückblick",
        body="Heute war ein **guter** Tag.\n\n- Eins\n- Zwei",
        rating=4,
        tags=["Familie", "Spaziergang"],
        trackers={"Schlaf": 7, "Sport": "ja"},
        location=Location(name="Zuhause", lat=47.3769, lon=8.5417),
        attachments=["2026-11-18-assets/foto.jpg"],
        created=datetime(2026, 11, 18, 8, 12, tzinfo=None),
        modified=datetime(2026, 11, 18, 20, 16, tzinfo=None),
    )
    text = entry.to_markdown()
    assert text.startswith("---\n")
    loaded = Entry.from_markdown(text, Path("/tmp/2026-11-18_20-15.md"))
    assert loaded.date == entry.date
    assert loaded.time == entry.time
    assert loaded.heading == entry.heading
    assert loaded.body == entry.body
    assert loaded.rating == 4
    assert loaded.tags == ["Familie", "Spaziergang"]
    assert loaded.trackers == {"Schlaf": 7, "Sport": "ja"}
    assert loaded.location is not None
    assert loaded.location.name == "Zuhause"
    assert loaded.location.lat == pytest.approx(47.3769)
    assert loaded.attachments == ["2026-11-18-assets/foto.jpg"]


def test_markdown_roundtrip_minimal():
    entry = Entry(date=date(2026, 1, 2), body="Nur Text.")
    loaded = Entry.from_markdown(entry.to_markdown(), Path("/x/2026-01-02.md"))
    assert loaded.date == date(2026, 1, 2)
    assert loaded.time is None
    assert loaded.rating is None
    assert loaded.tags == []
    assert loaded.body == "Nur Text."


def test_from_markdown_without_frontmatter_uses_filename():
    text = "# Ueberschrift\n\nFreier Text ohne Frontmatter.\n"
    loaded = Entry.from_markdown(text, Path("/x/2026-03-04.md"))
    assert loaded.date == date(2026, 3, 4)
    assert loaded.time is None
    assert "Freier Text" in loaded.body


def test_from_markdown_without_date_raises():
    with pytest.raises(ValueError):
        Entry.from_markdown("---\nheading: x\n---\ntext\n", Path("/x/foo.md"))


def test_rating_validation():
    with pytest.raises(ValueError):
        Entry(date=date(2026, 1, 1), rating=0)
    with pytest.raises(ValueError):
        Entry(date=date(2026, 1, 1), rating=6)
    Entry(date=date(2026, 1, 1), rating=5)


def test_no_yaml_anchors_when_created_equals_modified():
    now = datetime(2026, 11, 18, 10, 0)
    entry = Entry(date=date(2026, 11, 18), body="x", created=now, modified=now)
    text = entry.to_markdown()
    assert "&id" not in text and "*id" not in text
    assert "date: 2026-11-18" in text


def test_word_count():
    entry = Entry(date=date(2026, 1, 1), heading="Hallo", body="eins zwei\ndrei")
    assert entry.word_count() == 4


def test_sort_key_orders_time():
    a = Entry(date=date(2026, 1, 1), time=time(8, 0))
    b = Entry(date=date(2026, 1, 1))
    c = Entry(date=date(2026, 1, 2))
    assert sorted([a, c, b], key=lambda e: e.sort_key) == [b, a, c]
