import json
from datetime import date
from pathlib import Path

import pytest

from tagebuch.cli import main
from tagebuch.config import Config
from tagebuch.store import JournalStore
from tagebuch.webdav import WebDAVClient

from .fake_webdav import FakeWebDAVServer


@pytest.fixture
def config(tmp_path, monkeypatch):
    monkeypatch.setenv("XDG_DATA_HOME", str(tmp_path / "data"))
    cfg = Config(path=tmp_path / "config.json")
    cfg.set("journal_path", str(tmp_path / "Tagebuch"))
    return cfg


def run(argv, config):
    return main(argv, config=config)


def test_init_and_new_and_list(config, capsys):
    assert run(["init", str(Path(config.get("journal_path")))], config) == 0
    assert run(["new", "Heute", "war", "gut", "--tag", "Familie", "--rating", "5",
                "--date", "2026-11-18", "--heading", "Abend"], config) == 0
    assert run(["new", "Zweiter Eintrag", "--date", "2026-11-18", "--time", "20:15"], config) == 0
    assert run(["list"], config) == 0
    out = capsys.readouterr().out
    assert "2026-11-18" in out and "★★★★★" in out and "#Familie" in out and "Abend" in out
    assert "20:15" in out


def test_show_and_search_and_stats(config, capsys):
    run(["init", config.get("journal_path")], config)
    run(["new", "Spaziergang im Park", "--date", "2026-11-18", "--tag", "Natur"], config)
    run(["new", "Meeting", "--date", "2026-11-17", "--rating", "2"], config)
    assert run(["show", "2026-11-18"], config) == 0
    out = capsys.readouterr().out
    assert "Spaziergang im Park" in out and "date: 2026-11-18" in out
    assert run(["search", "spaziergang"], config) == 0
    assert "1 Treffer" in capsys.readouterr().out
    assert run(["search", "xyz", "--tag", "Natur"], config) == 0
    assert "0 Treffer" in capsys.readouterr().out
    assert run(["stats"], config) == 0
    out = capsys.readouterr().out
    assert "Eintraege gesamt:   2" in out
    assert "Aktuelle Serie:" in out
    assert run(["tags"], config) == 0
    assert "#Natur" in capsys.readouterr().out


def test_export_import_roundtrip(config, tmp_path, capsys):
    run(["init", config.get("journal_path")], config)
    run(["new", "Export mich", "--date", "2026-11-18", "--rating", "3"], config)
    dest = tmp_path / "backup.json"
    assert run(["export", str(dest), "--format", "json"], config) == 0
    assert "1 Eintraege exportiert" in capsys.readouterr().out
    cfg2 = Config(path=tmp_path / "config2.json")
    cfg2.set("journal_path", str(tmp_path / "Tagebuch2"))
    run(["init", cfg2.get("journal_path")], cfg2)
    assert run(["import", str(dest)], cfg2) == 0
    store2 = JournalStore(cfg2.get("journal_path"))
    assert store2.get_entry(date(2026, 11, 18)).body == "Export mich"


def test_template_commands(config, tmp_path, capsys):
    run(["init", config.get("journal_path")], config)
    tpl = tmp_path / "dankbarkeit.md"
    tpl.write_text("Wofuer bist du dankbar?", encoding="utf-8")
    assert run(["template", "add", str(tpl)], config) == 0
    assert run(["template", "list"], config) == 0
    assert "dankbarkeit.md" in capsys.readouterr().out
    assert run(["template", "show", "dankbarkeit.md"], config) == 0
    assert "dankbar" in capsys.readouterr().out


def test_lock_commands(config, monkeypatch, capsys):
    answers = iter(["1234", "1234", "1234", "9999", "9999"])
    monkeypatch.setattr("tagebuch.cli.getpass.getpass", lambda *a, **k: next(answers))
    assert run(["lock", "set-pin"], config) == 0
    assert "Sperre aktiv" in capsys.readouterr().out
    assert run(["lock", "status"], config) == 0
    assert "Sperre aktiv: True" in capsys.readouterr().out
    assert run(["lock", "change-pin"], config) == 0
    cfg = Config(path=config.path)
    from tagebuch.lock import LockManager
    assert LockManager(cfg).verify("9999")


def test_webdav_setup_and_sync_via_cli(config, tmp_path, monkeypatch, capsys):
    server = FakeWebDAVServer(tmp_path / "remote").start()
    try:
        monkeypatch.setattr("tagebuch.cli.getpass.getpass", lambda *a, **k: "app-passwort")
        assert run(["webdav", "setup", "--url", server.url, "--username", "testuser",
                    "--remote-path", "Tagebuch"], config) == 0
        out = capsys.readouterr().out
        assert "WebDAV verbunden" in out
        run(["init", config.get("journal_path")], config)
        run(["new", "Vom CLI gesynct", "--date", "2026-11-18"], config)
        assert run(["sync"], config) == 0
        out = capsys.readouterr().out
        assert "Hochgeladen:   1" in out
        remote_file = tmp_path / "remote" / "Tagebuch" / "2026-11-18.md"
        assert remote_file.is_file()
        assert "Vom CLI gesynct" in remote_file.read_text(encoding="utf-8")
        client = WebDAVClient(f"{server.url}/Tagebuch", "testuser", "app-passwort")
        assert any(i.path == "2026-11-18.md" for i in client.list_tree().values())
    finally:
        server.stop()


def test_config_get_set(config, capsys):
    assert run(["config", "reminder.time", "07:45"], config) == 0
    capsys.readouterr()
    assert run(["config", "reminder.time"], config) == 0
    assert "07:45" in capsys.readouterr().out
    assert run(["config", "reminder"], config) == 0
    payload = json.loads(capsys.readouterr().out)
    assert payload["time"] == "07:45" and payload["enabled"] is False
