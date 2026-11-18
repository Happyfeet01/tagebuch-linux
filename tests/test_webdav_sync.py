from datetime import date

import pytest

from tagebuch.model import Entry
from tagebuch.store import JournalStore
from tagebuch.sync import SyncEngine, conflict_name
from tagebuch.webdav import WebDAVClient, WebDAVError

from .fake_webdav import FakeWebDAVServer


@pytest.fixture
def env(tmp_path):
    store = JournalStore(tmp_path / "local")
    store.init()
    server = FakeWebDAVServer(tmp_path / "remote").start()
    client = WebDAVClient(server.url, "testuser", "passwort")
    engine = SyncEngine(store, client, tmp_path / "state.json")
    yield store, client, server, engine, tmp_path
    server.stop()


def test_client_basics(env):
    _, client, _, _, _ = env
    client.mkcol("unterordner")
    etag = client.upload("unterordner/a.txt", b"hallo")
    assert etag
    items = client.list("unterordner")
    assert any(i.path == "unterordner/a.txt" and not i.is_dir for i in items)
    assert client.download("unterordner/a.txt") == b"hallo"
    with pytest.raises(WebDAVError):
        client.upload("unterordner/a.txt", b"neu", etag="falscher-etag")
    client.delete("unterordner/a.txt")
    with pytest.raises(WebDAVError):
        client.download("unterordner/a.txt")


def test_initial_push_and_idempotency(env):
    store, _, _, engine, tmp_path = env
    store.save_entry(Entry(date=date(2026, 11, 18), body="Eins", rating=5))
    store.save_entry(Entry(date=date(2026, 11, 17), body="Zwei"))
    result = engine.sync()
    assert sorted(result.uploaded) == ["2026-11-17.md", "2026-11-18.md"]
    assert not result.errors
    assert (tmp_path / "remote" / "2026-11-18.md").is_file()

    again = engine.sync()
    assert again.uploaded == [] and again.downloaded == []
    assert not again.errors


def test_pull_new_remote_file(env):
    store, _, server, engine, tmp_path = env
    (tmp_path / "remote" / "2026-01-01.md").write_text(
        "---\ndate: 2026-01-01\n---\n\nvon unterwegs\n", encoding="utf-8"
    )
    result = engine.sync()
    assert "2026-01-01.md" in result.downloaded
    loaded = store.get_entry(date(2026, 1, 1))
    assert loaded is not None and loaded.body == "von unterwegs"


def test_remote_change_is_pulled(env):
    store, _, _, engine, tmp_path = env
    store.save_entry(Entry(date=date(2026, 11, 18), body="alt"))
    engine.sync()
    (tmp_path / "remote" / "2026-11-18.md").write_text(
        "---\ndate: 2026-11-18\n---\n\ngeaendert auf dem handy\n", encoding="utf-8"
    )
    result = engine.sync()
    assert "2026-11-18.md" in result.downloaded
    assert store.get_entry(date(2026, 11, 18)).body == "geaendert auf dem handy"


def test_local_change_is_pushed(env):
    store, _, _, engine, tmp_path = env
    store.save_entry(Entry(date=date(2026, 11, 18), body="alt"))
    engine.sync()
    entry = store.get_entry(date(2026, 11, 18))
    entry.body = "lokal neu"
    store.save_entry(entry)
    result = engine.sync()
    assert "2026-11-18.md" in result.uploaded
    remote_text = (tmp_path / "remote" / "2026-11-18.md").read_text(encoding="utf-8")
    assert "lokal neu" in remote_text


def test_conflict_keeps_both_sides(env):
    store, _, _, engine, tmp_path = env
    store.save_entry(Entry(date=date(2026, 11, 18), body="lokal"))
    engine.sync()
    entry = store.get_entry(date(2026, 11, 18))
    entry.body = "lokal geaendert"
    store.save_entry(entry)
    (tmp_path / "remote" / "2026-11-18.md").write_text(
        "---\ndate: 2026-11-18\n---\n\nremote geaendert\n", encoding="utf-8"
    )
    result = engine.sync()
    assert "2026-11-18.md" in result.conflicts
    remote_text = (tmp_path / "remote" / "2026-11-18.md").read_text(encoding="utf-8")
    assert "lokal geaendert" in remote_text
    conflict = tmp_path / "local" / conflict_name("2026-11-18.md", engine.host)
    assert conflict.is_file()
    assert "remote geaendert" in conflict.read_text(encoding="utf-8")
    assert store.get_entry(date(2026, 11, 18)).body == "lokal geaendert"


def test_delete_remote_propagates_to_local(env):
    store, client, _, engine, tmp_path = env
    store.save_entry(Entry(date=date(2026, 11, 18), body="x"))
    engine.sync()
    client.delete("2026-11-18.md")
    result = engine.sync()
    assert "2026-11-18.md" in result.deleted_local
    assert store.get_entry(date(2026, 11, 18)) is None


def test_delete_local_propagates_to_remote(env):
    store, _, _, engine, tmp_path = env
    store.save_entry(Entry(date=date(2026, 11, 18), body="x"))
    engine.sync()
    (tmp_path / "local" / "2026-11-18.md").unlink()
    result = engine.sync()
    assert "2026-11-18.md" in result.deleted_remote
    assert not (tmp_path / "remote" / "2026-11-18.md").exists()


def test_attachments_sync_both_ways(env):
    store, _, _, engine, tmp_path = env
    store.save_entry(Entry(date=date(2026, 11, 18), body="mit foto"))
    src = tmp_path / "bild.jpg"
    src.write_bytes(b"\xff\xd8\xff\xd9")
    rel = store.add_attachment(date(2026, 11, 18), src)
    result = engine.sync()
    assert rel in result.uploaded
    assert (tmp_path / "remote" / rel).read_bytes() == b"\xff\xd8\xff\xd9"

    store2 = JournalStore(tmp_path / "local2")
    store2.init()
    engine2 = SyncEngine(store2, env[1], tmp_path / "state2.json")
    pulled = engine2.sync()
    assert "2026-11-18.md" in pulled.downloaded
    assert rel in pulled.downloaded
    assert (store2.root / rel).read_bytes() == b"\xff\xd8\xff\xd9"


def test_templates_sync(env):
    store, _, _, engine, tmp_path = env
    tpl = tmp_path / "dankbarkeit.md"
    tpl.write_text("Wofuer bist du heute dankbar?", encoding="utf-8")
    name = store.add_template(tpl)
    engine.sync()
    assert (tmp_path / "remote" / ".templates" / name).is_file()


def test_sync_unreachable_records_error(tmp_path):
    store = JournalStore(tmp_path / "local")
    store.init()
    store.save_entry(Entry(date=date(2026, 11, 18), body="x"))
    client = WebDAVClient("http://127.0.0.1:1/none", "u", "p", timeout=2)
    engine = SyncEngine(store, client, tmp_path / "state.json")
    result = engine.sync()
    assert result.errors
    assert result.uploaded == []
