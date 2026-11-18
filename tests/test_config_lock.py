import os
import stat

import pytest

from tagebuch.config import Config
from tagebuch.lock import LockManager, hash_pin, verify_pin


@pytest.fixture
def config(tmp_path):
    return Config(path=tmp_path / "cfg" / "config.json")


def test_defaults_without_file(config):
    assert config.get("reminder", "time") == "21:00"
    assert config.get("lock", "enabled") is False
    assert config.get("appearance", "theme") == "system"


def test_save_load_roundtrip_and_permissions(config):
    config.set("reminder", "time", "07:30")
    config.set("lock", "enabled", True)
    reloaded = Config(path=config.path)
    assert reloaded.get("reminder", "time") == "07:30"
    assert reloaded.get("lock", "enabled") is True
    assert reloaded.get("appearance", "theme") == "system"
    mode = stat.S_IMODE(os.stat(config.path).st_mode)
    assert mode == 0o600


def test_deep_merge_keeps_defaults(config):
    config.path.parent.mkdir(parents=True, exist_ok=True)
    config.path.write_text('{"reminder": {"enabled": true}}', encoding="utf-8")
    loaded = Config(path=config.path)
    assert loaded.get("reminder", "enabled") is True
    assert loaded.get("reminder", "time") == "21:00"


def test_hash_pin_and_verify():
    salt_b64, hash_b64 = hash_pin("1234")
    assert verify_pin("1234", salt_b64, hash_b64)
    assert not verify_pin("1235", salt_b64, hash_b64)
    hash2 = hash_pin("1234")[1]
    assert hash2 != hash_b64
    assert not verify_pin("1234", salt_b64, "ungueltig")


def test_hash_pin_too_short():
    with pytest.raises(ValueError):
        hash_pin("12")


def test_lock_manager_flow(config):
    lock = LockManager(config)
    assert lock.enabled is False
    assert lock.is_locked is False

    lock.set_pin("4711")
    assert lock.enabled is True
    assert lock.verify("4711") is True
    assert lock.is_locked is False

    lock.lock()
    assert lock.is_locked is True
    assert lock.verify("0000") is False
    assert lock.is_locked is True
    assert lock.verify("4711") is True
    assert lock.is_locked is False

    with pytest.raises(ValueError):
        lock.change_pin("falsch", "9999")
    lock.change_pin("4711", "9999")
    assert lock.verify("9999") is True

    lock.disable("9999")
    assert lock.enabled is False
    with pytest.raises(ValueError):
        lock.disable("9999")


def test_webdav_password_fallback_without_keyring(config, monkeypatch):
    monkeypatch.setattr("tagebuch.config._keyring_available", lambda: False)
    config.set("webdav", "username", "lars")
    config.set_webdav_password("geheim123")
    assert config.webdav_password() == "geheim123"
    assert config.get("webdav", "use_keyring") is False
