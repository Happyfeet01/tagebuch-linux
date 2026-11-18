import json
from datetime import date, time

import pytest

from tagebuch.exchange import (
    export_html,
    export_json,
    export_markdown,
    export_txt,
    import_csv,
    import_json,
    import_markdown_dir,
    render_markdown,
    stars,
)
from tagebuch.model import Entry, Location
from tagebuch.store import JournalStore


@pytest.fixture
def store(tmp_path):
    s = JournalStore(tmp_path / "tb")
    s.init()
    s.save_entry(Entry(date=date(2026, 11, 18), time=time(20, 15), heading="Abend",
                       body="Guter Tag.\n\n- a\n- b", rating=4, tags=["Familie"],
                       trackers={"Schlaf": 7}, location=Location(name="Zuhause", lat=47.1, lon=8.5),
                       attachments=["2026-11-18-assets/f.jpg"]))
    s.save_entry(Entry(date=date(2026, 11, 17), body="Anderer Tag", rating=2))
    return s


def test_json_roundtrip(store, tmp_path):
    dest = tmp_path / "out.json"
    count = export_json(store, dest)
    assert count == 2
    payload = json.loads(dest.read_text(encoding="utf-8"))
    assert payload["app"] == "tagebuch-linux"
    assert len(payload["entries"]) == 2
    assert payload["entries"][0]["date"] == "2026-11-17"

    other = JournalStore(tmp_path / "tb2")
    other.init()
    assert import_json(other, dest) == 2
    entry = other.get_entry(date(2026, 11, 18), time(20, 15))
    assert entry is not None
    assert entry.heading == "Abend"
    assert entry.rating == 4
    assert entry.tags == ["Familie"]
    assert entry.trackers == {"Schlaf": 7}
    assert entry.location.name == "Zuhause"
    assert entry.attachments == ["2026-11-18-assets/f.jpg"]


def test_csv_import(store, tmp_path):
    csv_file = tmp_path / "import.csv"
    csv_file.write_text(
        "date,time,heading,text,rating,tags\n"
        "2026-05-01,08:30,Morgen,\"Erster Mai, alles gut\",5,Freizeit;Wetter\n"
        "02.05.2026,,,,3,\n",
        encoding="utf-8",
    )
    assert import_csv(store, csv_file) == 2
    e1 = store.get_entry(date(2026, 5, 1), time(8, 30))
    assert e1.heading == "Morgen" and e1.rating == 5 and e1.tags == ["Freizeit", "Wetter"]
    e2 = store.get_entry(date(2026, 5, 2))
    assert e2 is not None and e2.rating == 3


def test_import_markdown_dir(store, tmp_path):
    src = tmp_path / "alt"
    src.mkdir()
    (src / "2024-12-24.md").write_text(
        "---\ndate: 2024-12-24\nheading: Weihnachten\n---\n\nBescherung\n", encoding="utf-8"
    )
    (src / "unbekannt.md").write_text("kein Datum", encoding="utf-8")
    assert import_markdown_dir(store, src) == 1
    entry = store.get_entry(date(2024, 12, 24))
    assert entry is not None and entry.heading == "Weihnachten"


def test_export_txt(store, tmp_path):
    dest = tmp_path / "out.txt"
    assert export_txt(store, dest) == 2
    text = dest.read_text(encoding="utf-8")
    assert "=== 2026-11-18 20:15 – Abend ===" in text
    assert "★★★★☆ (4/5)" in text
    assert "Tags: Familie" in text
    assert "Ort: Zuhause" in text
    assert "Tracker: Schlaf=7" in text


def test_export_markdown(store, tmp_path):
    dest = tmp_path / "out.md"
    assert export_markdown(store, dest) == 2
    text = dest.read_text(encoding="utf-8")
    assert "## 2026-11-18 20:15" in text
    assert "#Familie" in text


def test_export_html(store, tmp_path):
    dest = tmp_path / "out.html"
    assert export_html(store, dest) == 2
    text = dest.read_text(encoding="utf-8")
    assert "<h2>Abend</h2>" in text
    assert "★★★★☆" in text
    assert "<li>a</li>" in text


def test_render_markdown():
    out = render_markdown(
        "# Titel\n\nText mit **fett** und *kursiv* und `code`.\n\n- eins\n- zwei\n"
    )
    assert "<h1>Titel</h1>" in out
    assert "<strong>fett</strong>" in out
    assert "<em>kursiv</em>" in out
    assert "<code>code</code>" in out
    assert "<ul>" in out and "<li>eins</li>" in out and "<li>zwei</li>" in out


def test_render_markdown_escapes_html():
    out = render_markdown("<script>alert(1)</script>")
    assert "<script>" not in out
    assert "&lt;script&gt;" in out


def test_stars():
    assert stars(4) == "★★★★☆"
    assert stars(None) == ""
