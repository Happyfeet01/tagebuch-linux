from __future__ import annotations

import base64
import hashlib
import hmac
import os

from .config import Config

PBKDF2_ITERATIONS = 600_000
MIN_PIN_LENGTH = 4


def hash_pin(pin: str, salt: bytes | None = None) -> tuple[str, str]:
    if len(pin) < MIN_PIN_LENGTH:
        raise ValueError(f"PIN muss mindestens {MIN_PIN_LENGTH} Zeichen haben")
    if salt is None:
        salt = os.urandom(16)
    digest = hashlib.pbkdf2_hmac("sha256", pin.encode("utf-8"), salt, PBKDF2_ITERATIONS)
    return base64.b64encode(salt).decode("ascii"), base64.b64encode(digest).decode("ascii")


def verify_pin(pin: str, salt_b64: str, expected_hash_b64: str) -> bool:
    try:
        salt = base64.b64decode(salt_b64)
        expected = base64.b64decode(expected_hash_b64)
    except Exception:
        return False
    digest = hashlib.pbkdf2_hmac("sha256", pin.encode("utf-8"), salt, PBKDF2_ITERATIONS)
    return hmac.compare_digest(digest, expected)


class LockManager:
    def __init__(self, config: Config):
        self.config = config
        self.locked = False

    @property
    def enabled(self) -> bool:
        lock = self.config.data.get("lock", {})
        return bool(lock.get("enabled")) and bool(lock.get("pin_hash"))

    @property
    def is_locked(self) -> bool:
        return self.enabled and self.locked

    def set_pin(self, pin: str) -> None:
        salt_b64, hash_b64 = hash_pin(pin)
        lock = self.config.data.setdefault("lock", {})
        lock["pin_salt"] = salt_b64
        lock["pin_hash"] = hash_b64
        lock["enabled"] = True
        self.config.save()
        self.locked = False

    def change_pin(self, old_pin: str, new_pin: str) -> None:
        if not self.verify(old_pin):
            raise ValueError("Alte PIN ist falsch")
        self.set_pin(new_pin)

    def disable(self, pin: str) -> None:
        if not self.verify(pin):
            raise ValueError("PIN ist falsch")
        lock = self.config.data.setdefault("lock", {})
        lock["enabled"] = False
        lock["pin_salt"] = ""
        lock["pin_hash"] = ""
        self.config.save()
        self.locked = False

    def verify(self, pin: str) -> bool:
        lock = self.config.data.get("lock", {})
        ok = verify_pin(pin, lock.get("pin_salt", ""), lock.get("pin_hash", ""))
        if ok:
            self.locked = False
        return ok

    def lock(self) -> None:
        if self.enabled:
            self.locked = True
