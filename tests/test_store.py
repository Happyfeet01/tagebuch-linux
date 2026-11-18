from datetime import date, time

import pytest

from tagebuch.model import Entry
from tagebuch.store import JournalStore, assets_dirname


@pytest.fixture
def store(tmp_path):
    s = JournalStore(tmp_path / "Tagebuch")
    s.init()
    return s


def test_init_creates_templates_dir(store, tmp_path):
    assert (tmp_path / "Tagebuch" / ".templates").is_dir()


def test_save_and_get_entry(store):
    entry = Entry(date=date(2026, 11, 18), body="Erster Eintrag")
    path = store.save_entry(entry)
    assert path.name == "2026-11-18.md"
    assert entry.created is not None
    assert entry.modified is not None

    loaded = store.get_entry(date(2026, 11, 18))
    assert loaded is not None
    assert loaded.body == "Erster Eintrag"
    assert loaded.path == path


def test_multiple_entries_per_day(store):
    store.save_entry(Entry(date=date(2026, 11, 18), body="morgens"))
    store.save_entry(Entry(date=date(2026, 11, 18), time=time(20, 15), body="abends"))
    day_entries = store.entries_for_day(date(2026, 11, 18))
    assert len(day_entries) == 2
    assert {e.time for e in day_entries} == {None, time(20, 15)}
    assert store.get_entry(date(2026, 11, 18), time(20, 15)).body == "abends"
    assert store.get_entry(date(2026, 11, 18)).body == "morgens"


def test_list_entries_sorted_desc(store):
    store.save_entry(Entry(date=date(2026, 11, 16), body="alt"))
    store.save_entry(Entry(date=date(2026, 11, 18), body="neu"))
    store.save_entry(Entry(date=date(2026, 11, 17), body="mitte"))
    bodies = [e.body for e in store.list_entries()]
    assert bodies == ["neu", "mitte", "alt"]
    assert store.list_days() == [date(2026, 11, 18), date(2026, 11, 17), date(2026, 11, 16)]


def test_delete_entry(store):
    store.save_entry(Entry(date=date(2026, 11, 18), body="weg damit"))
    assert store.delete_entry(date(2026, 11, 18)) is True
    assert store.get_entry(date(2026, 11, 18)) is None
    assert store.delete_entry(date(2026, 11, 18)) is False


def test_conflict_files_are_ignored(store):
    store.save_entry(Entry(date=date(2026, 11, 18), body="original"))
    (store.root / "2026-11-18.conflict-laptop.md").write_text(
        "---\ndate: 2026-11-18\n---\nkonflikt\n", encoding="utf-8"
    )
    (store.root / "notiz.md").write_text("kein eintrag")
    entries = store.list_entries()
    assert len(entries) == 1
    assert entries[0].body == "original"


def test_add_attachment(store, tmp_path):
    src = tmp_path / "foto.jpg"
    src.write_bytes(b"\xff\xd8\xff")
    rel = store.add_attachment(date(2026, 11, 18), src)
    assert rel == f"{assets_dirname(date(2026, 11, 18))}/foto.jpg"
    assert (store.root / rel).read_bytes() == b"\xff\xd8\xff"
    assert len(store.attachments_for_day(date(2026, 11, 18))) == 1

    rel2 = store.add_attachment(date(2026, 11, 18), src)
    assert rel2.endswith("foto-1.jpg")

    with pytest.raises(FileNotFoundError):
        store.add_attachment(date(2026, 11, 18), tmp_path / "gibts-nicht.png")


def test_templates(store, tmp_path):
    assert store.list_templates() == []
    src = tmp_path / "dankbarkeit.txt"
    src.write_text("Wofuer bist du dankbar?", encoding="utf-8")
    name = store.add_template(src)
    assert name == "dankbarkeit.txt.md"
    assert store.get_template(name) == "Wofuer bist du dankbar?"
    assert store.list_templates() == [name]
    with pytest.raises(FileNotFoundError):
        store.get_template("unbekannt.md")


def test_saved_file_is_plain_markdown(store):
    store.save_entry(Entry(date=date(2026, 11, 18), body="Hallo **Welt**", rating=5, tags=["a"]))
    text = (store.root / "2026-11-18.md").read_text(encoding="utf-8")
    assert text.startswith("---\n")
    assert "rating: 5" in text
    assert "Hallo **Welt**" in text
